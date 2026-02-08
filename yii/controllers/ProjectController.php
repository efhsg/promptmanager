<?php

namespace app\controllers;

use app\components\ProjectContext;
use app\models\Project;
use app\models\ProjectSearch;
use app\handlers\ClaudeQuickHandler;
use app\services\ClaudeCliService;
use app\services\EntityPermissionService;
use app\services\ProjectService;
use RuntimeException;
use Throwable;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller that provides CRUD actions for Project models and handles the
 * user's current project context. It enforces permissions and renders views
 * for listing, creating, updating and deleting projects.
 */
class ProjectController extends Controller
{
    private const CLAUDE_TIMEOUT = 3600;

    private ProjectContext $projectContext;
    private array $actionPermissionMap;
    private readonly EntityPermissionService $permissionService;
    private readonly ProjectService $projectService;
    private readonly ClaudeCliService $claudeCliService;
    private readonly ClaudeQuickHandler $claudeQuickHandler;

    public function __construct(
        $id,
        $module,
        EntityPermissionService $permissionService,
        ProjectService $projectService,
        ClaudeCliService $claudeCliService,
        ClaudeQuickHandler $claudeQuickHandler,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->permissionService = $permissionService;
        $this->projectService = $projectService;
        $this->claudeCliService = $claudeCliService;
        $this->claudeQuickHandler = $claudeQuickHandler;
        // Load the permission mapping for "project" actions
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('project');
    }

    public function init(): void
    {
        parent::init();
        $this->projectContext = Yii::$app->projectContext;
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'actions' => array_keys($this->actionPermissionMap),
                        'matchCallback' => function ($rule, $action) {
                            $callback = $this->permissionService->isModelBasedAction($action->id)
                                ? fn() => $this->findModel((int) Yii::$app->request->get('id'))
                                : null;
                            return $this->permissionService->hasActionPermission('project', $action->id, $callback);
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists all Project models for the logged-in user.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        $searchModel = new ProjectSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            Yii::$app->user->id
        );

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Project model.
     *
     * @param int $id
     * @return string
     * @throws NotFoundHttpException if the model cannot be found or doesn't belong to the user
     */
    public function actionView(int $id): string
    {
        $model = $this->findModel($id);
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Creates a new Project model.
     *
     * @return string|Response
     * @throws Exception
     */
    public function actionCreate(): Response|string
    {
        $model = new Project(['user_id' => Yii::$app->user->id]);

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $this->loadClaudeOptions($model);
            if ($model->save()) {
                $this->projectService->syncLinkedProjects($model, $model->linkedProjectIds);
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $availableProjects = $this->projectService->fetchAvailableProjectsForLinking(null, Yii::$app->user->id);

        return $this->render('create', [
            'model' => $model,
            'availableProjects' => $availableProjects,
        ]);
    }

    /**
     * Updates an existing Project model.
     *
     * @param int $id
     * @return string|Response
     * @throws NotFoundHttpException|Exception
     */
    public function actionUpdate(int $id): Response|string
    {
        /** @var Project $model */
        $model = $this->findModel($id);

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $this->loadClaudeOptions($model);
            if ($model->save()) {
                $this->projectService->syncLinkedProjects($model, $model->linkedProjectIds);
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $model->linkedProjectIds = array_map(
            static fn($project): int => $project->id,
            $model->linkedProjects
        );

        $availableProjects = $this->projectService->fetchAvailableProjectsForLinking($model->id, Yii::$app->user->id);

        $projectConfigStatus = [];
        if (!empty($model->root_directory)) {
            $projectConfigStatus = $this->claudeCliService->checkClaudeConfigForPath($model->root_directory);
        }

        return $this->render('update', [
            'model' => $model,
            'availableProjects' => $availableProjects,
            'projectConfigStatus' => $projectConfigStatus,
        ]);
    }

    /**
     * Deletes an existing Project model.
     *
     * @param int $id
     * @return Response|string
     * @throws NotFoundHttpException
     */
    public function actionDelete(int $id): Response|string
    {
        /** @var Project $model */
        $model = $this->findModel($id);

        if (!Yii::$app->request->post('confirm')) {
            return $this->render('delete-confirm', [
                'model' => $model,
            ]);
        }

        try {
            $model->delete();
            Yii::$app->session->setFlash('success', "Project '$model->name' deleted successfully.");
        } catch (Throwable $e) {
            Yii::error($e->getMessage(), 'database');
            Yii::$app->session->setFlash('error', 'Unable to delete the project. Please try again later.');
        }
        return $this->redirect(['index']);
    }

    /**
     * Sets the current project in the user's context.
     *
     * For AJAX requests, returns JSON and does not redirect.
     * For regular POST, redirects to referrer (backwards compatibility).
     *
     * @return Response
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function actionSetCurrent(): Response
    {
        $projectId = Yii::$app->request->post('project_id');
        $this->projectContext->setCurrentProject((int) $projectId);

        if (Yii::$app->request->isAjax) {
            return $this->asJson(['success' => true]);
        }

        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
    }

    /**
     * Checks Claude config status for a project's root directory.
     *
     * @throws NotFoundHttpException
     */
    public function actionCheckClaudeConfig(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        /** @var Project $model */
        $model = $this->findModel($id);

        if (empty($model->root_directory)) {
            return [
                'success' => false,
                'error' => 'Project has no root directory configured.',
            ];
        }

        $configStatus = $this->claudeCliService->checkClaudeConfigForPath($model->root_directory);

        return [
            'success' => true,
            'hasCLAUDE_MD' => $configStatus['hasCLAUDE_MD'],
            'hasClaudeDir' => $configStatus['hasClaudeDir'],
            'hasAnyConfig' => $configStatus['hasAnyConfig'],
            'pathStatus' => $configStatus['pathStatus'],
            'pathMapped' => $configStatus['pathMapped'],
            'hasPromptManagerContext' => $model->hasClaudeContext(),
            'claudeContext' => $model->getClaudeContextAsMarkdown(),
        ];
    }

    /**
     * Returns available Claude slash commands for a project.
     *
     * @throws NotFoundHttpException
     */
    public function actionClaudeCommands(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        /** @var Project $model */
        $model = $this->findModel($id);

        if (empty($model->root_directory)) {
            return ['success' => false, 'commands' => []];
        }

        return [
            'success' => true,
            'commands' => $this->claudeCliService->loadCommandsFromDirectory($model->root_directory),
        ];
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionClaudeUsage(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $this->findModel($id);

        return $this->claudeCliService->getSubscriptionUsage();
    }

    /**
     * Renders Claude CLI chat interface for a project.
     * Initial content can be provided via sessionStorage (set by prompt-instance create).
     *
     * @throws NotFoundHttpException
     */
    public function actionClaude(int $id): string
    {
        /** @var Project $model */
        $model = $this->findModel($id);
        $rootDir = $model->root_directory;

        return $this->render('claude', [
            'model' => $model,
            'projectList' => $this->projectService->fetchProjectsList(Yii::$app->user->id),
            'claudeCommands' => $this->loadClaudeCommands($rootDir, $model),
            'gitBranch' => $rootDir ? $this->claudeCliService->getGitBranch($rootDir) : null,
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionRunClaude(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        /** @var Project $model */
        $model = $this->findModel($id);
        $prepared = $this->prepareClaudeRequest($model);

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
    public function actionStreamClaude(int $id): void
    {
        /** @var Project $model */
        $model = $this->findModel($id);
        $prepared = $this->prepareClaudeRequest($model);

        if (isset($prepared['error'])) {
            $this->sendSseError($prepared['error']['error']);
            return;
        }

        $markdown = $prepared['markdown'];

        Yii::$app->session->close();
        ignore_user_abort(false);
        $this->beginSseResponse();

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
    public function actionCancelClaude(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Validate ownership via findModel
        $this->findModel($id);

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
    public function actionSummarizeSession(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        /** @var Project $model */
        $model = $this->findModel($id);

        $requestData = json_decode(Yii::$app->request->rawBody, true) ?? [];
        if (!is_array($requestData)) {
            return ['success' => false, 'error' => 'Invalid request format.'];
        }
        $conversation = $requestData['conversation'] ?? '';

        if (!is_string($conversation) || trim($conversation) === '') {
            return ['success' => false, 'error' => 'Conversation text is empty.'];
        }

        $workingDirectory = !empty($model->root_directory) ? $model->root_directory : '';

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
            $model,
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
     * @return array{markdown: string, options: array, workingDirectory: string, project: Project, sessionId: ?string, streamToken: ?string}|array{error: array}
     */
    private function prepareClaudeRequest(Project $model): array
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

        $projectDefaults = $model->getClaudeOptions();

        $allowedKeys = ['model', 'permissionMode', 'appendSystemPrompt', 'allowedTools', 'disallowedTools'];
        $options = array_merge(
            $projectDefaults,
            array_filter(
                array_intersect_key($requestOptions, array_flip($allowedKeys)),
                fn($v) => $v !== null && $v !== ''
            )
        );

        $workingDirectory = !empty($model->root_directory) ? $model->root_directory : '';

        return [
            'markdown' => $markdown,
            'options' => $options,
            'workingDirectory' => $workingDirectory,
            'project' => $model,
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

    /**
     * @throws NotFoundHttpException
     */
    public function actionSummarizePrompt(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // findModel triggers RBAC ownership check via matchCallback behavior
        $this->findModel($id);

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
     * @throws NotFoundHttpException
     */
    protected function findModel(int $id): ActiveRecord
    {
        return Project::find()->where([
            'id' => $id,
            'user_id' => Yii::$app->user->id,
        ])->one() ?? throw new NotFoundHttpException('The requested Project does not exist or is not yours.');
    }

    private function loadClaudeOptions(Project $model): void
    {
        $claudeOptions = Yii::$app->request->post('claude_options', []);
        $model->setClaudeOptions($claudeOptions);
    }

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
        $ungrouped = [];
        foreach ($commands as $cmd => $desc) {
            $placed = false;
            foreach ($groups as $groupName => $members) {
                if (in_array($cmd, $members, true)) {
                    $grouped[$groupName][$cmd] = $desc;
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                $ungrouped[$cmd] = $desc;
            }
        }

        if ($ungrouped !== []) {
            $grouped['Other'] = $ungrouped;
        }

        return $grouped;
    }
}
