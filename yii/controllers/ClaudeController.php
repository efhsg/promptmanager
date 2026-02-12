<?php

namespace app\controllers;

use app\handlers\ClaudeQuickHandler;
use app\models\Project;
use app\services\ClaudeCliService;
use app\services\EntityPermissionService;
use RuntimeException;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Centralised Claude CLI chat interface.
 * Every action operates in the context of a Project (identified by query parameter `p`).
 */
class ClaudeController extends Controller
{
    private const CLAUDE_TIMEOUT = 3600;

    private readonly EntityPermissionService $permissionService;
    private readonly ClaudeCliService $claudeCliService;
    private readonly ClaudeQuickHandler $claudeQuickHandler;

    public function __construct(
        $id,
        $module,
        EntityPermissionService $permissionService,
        ClaudeCliService $claudeCliService,
        ClaudeQuickHandler $claudeQuickHandler,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->permissionService = $permissionService;
        $this->claudeCliService = $claudeCliService;
        $this->claudeQuickHandler = $claudeQuickHandler;
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'run' => ['POST'],
                    'stream' => ['POST'],
                    'cancel' => ['POST'],
                    'summarize-session' => ['POST'],
                    'summarize-prompt' => ['POST'],
                    'summarize-response' => ['POST'],
                    'suggest-name' => ['POST'],
                    'save' => ['POST'],
                    'import-text' => ['POST'],
                    'import-markdown' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['save', 'suggest-name', 'import-text', 'import-markdown'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => [
                            'index', 'run', 'stream', 'cancel',
                            'usage', 'check-config',
                            'summarize-session', 'summarize-prompt', 'summarize-response',
                        ],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            $projectId = (int) Yii::$app->request->get('p');
                            $model = $this->findProject($projectId);
                            return $this->permissionService->checkPermission('viewProject', $model);
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionIndex(int $p, ?string $breadcrumbs = null): string
    {
        $project = $this->findProject($p);
        $rootDir = $project->root_directory;

        return $this->render('index', [
            'project' => $project,
            'projectList' => Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id),
            'claudeCommands' => $this->loadClaudeCommands($rootDir, $project),
            'gitBranch' => $rootDir ? $this->claudeCliService->getGitBranch($rootDir) : null,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionUsage(int $p): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $this->findProject($p);

        return $this->claudeCliService->getSubscriptionUsage();
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionCheckConfig(int $p): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $project = $this->findProject($p);

        if (empty($project->root_directory)) {
            return [
                'success' => false,
                'error' => 'Project has no root directory configured.',
            ];
        }

        $configStatus = $this->claudeCliService->checkClaudeConfigForPath($project->root_directory);

        return [
            'success' => true,
            'hasCLAUDE_MD' => $configStatus['hasCLAUDE_MD'],
            'hasClaudeDir' => $configStatus['hasClaudeDir'],
            'hasAnyConfig' => $configStatus['hasAnyConfig'],
            'pathStatus' => $configStatus['pathStatus'],
            'pathMapped' => $configStatus['pathMapped'],
            'hasPromptManagerContext' => $project->hasClaudeContext(),
            'claudeContext' => $project->getClaudeContextAsMarkdown(),
        ];
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionRun(int $p): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $project = $this->findProject($p);
        $prepared = $this->prepareClaudeRequest($project);

        if (is_array($prepared['error'] ?? null)) {
            return $prepared['error'];
        }

        $result = $this->claudeCliService->execute(
            $prepared['markdown'],
            $prepared['workingDirectory'],
            self::CLAUDE_TIMEOUT,
            $prepared['options'],
            $prepared['project'],
            $prepared['sessionId']
        );

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
            'exitCode' => $result['exitCode'],
            'duration_ms' => $result['duration_ms'] ?? null,
            'model' => $result['model'] ?? null,
            'input_tokens' => $result['input_tokens'] ?? null,
            'cache_tokens' => $result['cache_tokens'] ?? null,
            'output_tokens' => $result['output_tokens'] ?? null,
            'context_window' => $result['context_window'] ?? null,
            'num_turns' => $result['num_turns'] ?? null,
            'tool_uses' => $result['tool_uses'] ?? [],
            'configSource' => $result['configSource'] ?? null,
            'sessionId' => $result['session_id'] ?? null,
            'requestedPath' => $result['requestedPath'] ?? null,
            'effectivePath' => $result['effectivePath'] ?? null,
            'usedFallback' => $result['usedFallback'] ?? null,
            'promptMarkdown' => $prepared['markdown'],
        ];
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionStream(int $p): void
    {
        $project = $this->findProject($p);
        $prepared = $this->prepareClaudeRequest($project);

        if (isset($prepared['error'])) {
            $this->sendSseError($prepared['error']['error']);
            return;
        }

        $markdown = $prepared['markdown'];

        // Release session file lock so other requests from this user are not blocked
        Yii::$app->session->close();

        // Ensure connection abort is detectable inside the streaming loop
        ignore_user_abort(false);

        $this->beginSseResponse();

        // Send prompt markdown as first event so frontend can display the user message
        echo "data: " . json_encode(['type' => 'prompt_markdown', 'markdown' => $markdown]) . "\n\n";
        flush();

        $onLine = function (string $line): void {
            echo "data: " . $line . "\n\n";
            flush();
        };

        try {
            $result = $this->claudeCliService->executeStreaming(
                $markdown,
                $prepared['workingDirectory'],
                $onLine,
                self::CLAUDE_TIMEOUT,
                $prepared['options'],
                $prepared['project'],
                $prepared['sessionId'],
                $prepared['streamToken']
            );

            if ($result['error'] !== '') {
                echo "data: " . json_encode([
                    'type' => 'server_error',
                    'error' => $result['error'],
                    'exitCode' => $result['exitCode'],
                ]) . "\n\n";
                flush();
            }
        } catch (RuntimeException $e) {
            echo "data: " . json_encode([
                'type' => 'server_error',
                'error' => $e->getMessage(),
                'exitCode' => 1,
            ]) . "\n\n";
            flush();
        }

        echo "data: [DONE]\n\n";
        flush();
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionCancel(int $p): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Validate ownership via findProject
        $this->findProject($p);

        $requestData = json_decode(Yii::$app->request->rawBody, true) ?? [];
        $raw = $requestData['streamToken'] ?? null;
        $streamToken = $this->sanitizeStreamToken(is_string($raw) ? $raw : null);

        if ($streamToken === null) {
            return ['success' => true, 'cancelled' => false];
        }

        $cancelled = $this->claudeCliService->cancelRunningProcess($streamToken);

        return [
            'success' => true,
            'cancelled' => $cancelled,
        ];
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionSummarizeSession(int $p): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $project = $this->findProject($p);

        $requestData = json_decode(Yii::$app->request->rawBody, true) ?? [];
        if (!is_array($requestData)) {
            return ['success' => false, 'error' => 'Invalid request format.'];
        }
        $conversation = $requestData['conversation'] ?? '';

        if (!is_string($conversation) || trim($conversation) === '') {
            return ['success' => false, 'error' => 'Conversation text is empty.'];
        }

        $workingDirectory = !empty($project->root_directory) ? $project->root_directory : '';

        $options = [
            'model' => 'sonnet',
            'permissionMode' => 'plan',
            'appendSystemPrompt' => $this->buildSummarizerSystemPrompt(),
        ];

        $result = $this->claudeCliService->execute(
            $conversation,
            $workingDirectory,
            120,
            $options,
            $project,
            null
        );

        $output = $result['output'] ?? '';
        if (!$result['success'] || !is_string($output) || trim($output) === '') {
            Yii::warning('Summarize session failed: ' . ($result['error'] ?? 'empty output'), __METHOD__);
            return [
                'success' => false,
                'error' => $result['error'] ?: 'Summarization returned empty output.',
            ];
        }

        return [
            'success' => true,
            'summary' => $output,
            'duration_ms' => $result['duration_ms'] ?? null,
            'model' => $result['model'] ?? null,
        ];
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionSummarizePrompt(int $p): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $this->findProject($p);

        $requestData = json_decode(Yii::$app->request->rawBody, true) ?? [];
        if (!is_array($requestData)) {
            return ['success' => false, 'error' => 'Invalid request format.'];
        }
        $prompt = $requestData['prompt'] ?? '';

        if (!is_string($prompt) || trim($prompt) === '') {
            return ['success' => false, 'error' => 'Prompt text is empty.'];
        }

        $result = $this->claudeQuickHandler->run('prompt-title', $prompt);

        return $result['success']
            ? ['success' => true, 'title' => $result['output']]
            : $result;
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionSummarizeResponse(int $p): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $this->findProject($p);

        $requestData = json_decode(Yii::$app->request->rawBody, true) ?? [];
        if (!is_array($requestData)) {
            return ['success' => false, 'error' => 'Invalid request format.'];
        }
        $response = $requestData['response'] ?? '';

        if (!is_string($response) || trim($response) === '') {
            return ['success' => false, 'error' => 'Response text is empty.'];
        }

        $result = $this->claudeQuickHandler->run('response-summary', $response);

        return $result['success']
            ? ['success' => true, 'summary' => $result['output']]
            : $result;
    }

    public function actionSuggestName(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $data = json_decode(Yii::$app->request->rawBody, true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Invalid JSON data.'];
        }

        $content = $data['content'] ?? '';

        if (!is_string($content) || trim($content) === '') {
            return ['success' => false, 'error' => 'Content is empty.'];
        }

        $result = $this->claudeQuickHandler->run('note-name', $content);

        if (!$result['success']) {
            return $result;
        }

        $name = mb_substr(trim($result['output']), 0, 255);

        if ($name === '') {
            return ['success' => false, 'error' => 'Could not generate a name.'];
        }

        return ['success' => true, 'name' => $name];
    }

    /**
     * Saves selected messages as a new Note.
     * Delegates to NoteController::actionSave.
     */
    public function actionSave(): array
    {
        return Yii::$app->runAction('note/save', Yii::$app->request->queryParams);
    }

    /**
     * Imports plain text as Quill Delta.
     * Delegates to NoteController::actionImportText.
     */
    public function actionImportText(): array
    {
        return Yii::$app->runAction('note/import-text', Yii::$app->request->queryParams);
    }

    /**
     * Imports markdown as Quill Delta.
     * Delegates to NoteController::actionImportMarkdown.
     */
    public function actionImportMarkdown(): array
    {
        return Yii::$app->runAction('note/import-markdown', Yii::$app->request->queryParams);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * @return array{markdown: string, options: array, workingDirectory: string, project: Project, sessionId: ?string, streamToken: ?string}|array{error: array}
     */
    private function prepareClaudeRequest(Project $project): array
    {
        $requestOptions = json_decode(Yii::$app->request->rawBody, true) ?? [];
        if (!is_array($requestOptions)) {
            return ['error' => ['success' => false, 'error' => 'Invalid request format.']];
        }

        $customPrompt = $requestOptions['prompt'] ?? null;
        $contentDelta = $requestOptions['contentDelta'] ?? null;

        if (is_array($contentDelta)) {
            $contentDelta = json_encode($contentDelta);
        }

        if ($customPrompt !== null) {
            $markdown = $customPrompt;
        } elseif ($contentDelta !== null) {
            $markdown = $this->claudeCliService->convertToMarkdown($contentDelta);
        } else {
            return ['error' => ['success' => false, 'error' => 'No prompt content provided.']];
        }

        if (trim($markdown) === '') {
            return ['error' => ['success' => false, 'error' => 'Prompt is empty.']];
        }

        $projectDefaults = $project->getClaudeOptions();

        $allowedKeys = ['model', 'permissionMode', 'appendSystemPrompt', 'allowedTools', 'disallowedTools'];
        $options = array_merge(
            $projectDefaults,
            array_filter(
                array_intersect_key($requestOptions, array_flip($allowedKeys)),
                fn($v) => $v !== null && $v !== ''
            )
        );

        $workingDirectory = !empty($project->root_directory) ? $project->root_directory : '';

        return [
            'markdown' => $markdown,
            'options' => $options,
            'workingDirectory' => $workingDirectory,
            'project' => $project,
            'sessionId' => $requestOptions['sessionId'] ?? null,
            'streamToken' => $this->sanitizeStreamToken(
                is_string($requestOptions['streamToken'] ?? null) ? $requestOptions['streamToken'] : null
            ),
        ];
    }

    private function beginSseResponse(): void
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->send();

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    private function sendSseError(string $message): void
    {
        $this->beginSseResponse();

        echo "data: " . json_encode(['type' => 'server_error', 'error' => $message, 'exitCode' => 1]) . "\n\n";
        echo "data: [DONE]\n\n";
        flush();
    }

    private function sanitizeStreamToken(?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $token)
            ? $token
            : null;
    }

    private function buildSummarizerSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are a conversation summarizer. Your task is to produce a structured summary
            of the conversation provided. The summary will be used to seed a new chat session,
            so it must contain enough context for the assistant to continue the work seamlessly.

            Produce the summary in the following markdown format:

            ## Context & Goal
            [What the user is trying to accomplish]

            ## Decisions Made
            [Key decisions, conclusions, and agreements reached]

            ## Code Changes & Artifacts
            [File paths modified/created, key code snippets, architectural choices.
            Include actual filenames and brief descriptions.]

            ## Current State
            [What has been completed. What is working. Test results.]

            ## Open Items & Next Steps
            [Unresolved questions, pending tasks, known issues, what to do next]

            Rules:
            - Be concise but semantically rich — preserve all actionable information
            - Include specific file paths, function names, and technical details
            - Do not include pleasantries or conversational filler
            - Do not include full code — only key snippets essential for context
            - The summary must be self-contained for a reader with no prior context
            PROMPT;
    }

    /**
     * Loads available Claude slash commands, applies blacklist and grouping from project config.
     *
     * @return array flat list or grouped structure with optgroup labels
     */
    private function loadClaudeCommands(?string $rootDirectory, Project $project): array
    {
        $commands = $this->claudeCliService->loadCommandsFromDirectory($rootDirectory);
        if ($commands === []) {
            return [];
        }

        $blacklist = $project->getClaudeCommandBlacklist();
        if ($blacklist !== []) {
            $commands = array_diff_key($commands, array_flip($blacklist));
        }

        $groups = $project->getClaudeCommandGroups();
        if ($groups === []) {
            return $commands;
        }

        $grouped = [];
        $assigned = [];

        foreach ($groups as $label => $commandNames) {
            $groupCommands = [];
            foreach ($commandNames as $name) {
                if (isset($commands[$name]) && !isset($assigned[$name])) {
                    $groupCommands[$name] = $commands[$name];
                    $assigned[$name] = true;
                }
            }
            if ($groupCommands !== []) {
                $grouped[$label] = $groupCommands;
            }
        }

        $other = array_diff_key($commands, $assigned);
        if ($other !== []) {
            $grouped['Other'] = $other;
        }

        return $grouped;
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findProject(int $id): Project
    {
        return Project::find()->where([
            'id' => $id,
            'user_id' => Yii::$app->user->id,
        ])->one() ?? throw new NotFoundHttpException('The requested Project does not exist or is not yours.');
    }
}
