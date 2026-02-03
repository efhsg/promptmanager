<?php

/** @noinspection PhpUnused */

namespace app\controllers;

use app\components\ProjectContext;
use app\helpers\MarkdownDetector;
use app\models\Project;
use app\models\ScratchPad;
use app\models\ScratchPadSearch;
use app\models\YouTubeImportForm;
use app\services\ClaudeCliService;
use app\services\CopyFormatConverter;
use app\services\copyformat\MarkdownParser;
use app\services\copyformat\QuillDeltaWriter;
use app\services\EntityPermissionService;
use app\services\YouTubeTranscriptService;
use common\enums\CopyType;
use Throwable;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use RuntimeException;

class ScratchPadController extends Controller
{
    private const CLAUDE_TIMEOUT = 3600;

    private ProjectContext $projectContext;
    private array $actionPermissionMap;
    private readonly EntityPermissionService $permissionService;
    private readonly YouTubeTranscriptService $youtubeService;
    private readonly ClaudeCliService $claudeCliService;

    public function __construct(
        $id,
        $module,
        EntityPermissionService $permissionService,
        YouTubeTranscriptService $youtubeService,
        ClaudeCliService $claudeCliService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->permissionService = $permissionService;
        $this->youtubeService = $youtubeService;
        $this->claudeCliService = $claudeCliService;
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('scratchPad');
    }

    public function init(): void
    {
        parent::init();
        $this->projectContext = Yii::$app->projectContext;
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'run-claude' => ['POST'],
                    'stream-claude' => ['POST'],
                    'cancel-claude' => ['POST'],
                    'save' => ['POST'],
                    'summarize-session' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'create', 'import-markdown', 'import-text', 'import-youtube', 'convert-format', 'save'],
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
                            return $this->permissionService->hasActionPermission('scratchPad', $action->id, $callback);
                        },
                    ],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new ScratchPadSearch();
        $currentProject = $this->projectContext->getCurrentProject();
        $isAllProjects = $this->projectContext->isAllProjectsContext();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            Yii::$app->user->id,
            $currentProject?->id,
            $isAllProjects
        );

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'currentProject' => $currentProject,
            'isAllProjects' => $isAllProjects,
            'projectList' => Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id),
        ]);
    }

    public function actionCreate(): string
    {
        $currentProject = $this->projectContext->getCurrentProject();
        $projectList = Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id);
        return $this->render('create', [
            'currentProject' => $currentProject,
            'projectList' => $projectList,
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): string
    {
        $model = $this->findModel($id);
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        /** @var ScratchPad $model */
        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
            'projectList' => Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id),
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionDelete(int $id): Response|string
    {
        $model = $this->findModel($id);

        if (!Yii::$app->request->post('confirm')) {
            return $this->render('delete-confirm', [
                'model' => $model,
            ]);
        }

        try {
            $model->delete();
            Yii::$app->session->setFlash('success', "Scratch pad '$model->name' deleted successfully.");
        } catch (Throwable $e) {
            Yii::error($e->getMessage(), 'database');
            Yii::$app->session->setFlash('error', 'Unable to delete the scratch pad. Please try again later.');
        }
        return $this->redirect(['index']);
    }

    /**
     * @throws Exception
     */
    public function actionSave(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $data = json_decode(Yii::$app->request->rawBody, true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Invalid JSON data.'];
        }

        $id = $data['id'] ?? null;
        $name = trim($data['name'] ?? '');
        $content = $data['content'] ?? '';
        $response = $data['response'] ?? '';
        $projectId = $data['project_id'] ?? null;

        if ($name === '') {
            return ['success' => false, 'errors' => ['name' => ['Name is required.']]];
        }

        if ($projectId !== null) {
            $projectExists = Project::find()
                ->forUser(Yii::$app->user->id)
                ->andWhere(['id' => $projectId])
                ->exists();
            if (!$projectExists) {
                return ['success' => false, 'message' => 'Project not found.'];
            }
        }

        if ($id !== null) {
            $model = ScratchPad::find()
                ->forUser(Yii::$app->user->id)
                ->andWhere(['id' => $id])
                ->one();
            if ($model === null) {
                return ['success' => false, 'message' => 'Scratch pad not found.'];
            }
        } else {
            $model = new ScratchPad([
                'user_id' => Yii::$app->user->id,
                'project_id' => $projectId,
            ]);
        }

        $model->name = $name;
        $model->content = $content;
        $model->response = $response;

        if ($model->save()) {
            return [
                'success' => true,
                'id' => $model->id,
                'message' => 'Scratch pad saved successfully.',
            ];
        }

        return ['success' => false, 'errors' => $model->getErrors()];
    }

    public function actionImportMarkdown(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $file = UploadedFile::getInstanceByName('mdFile');

        if ($file === null) {
            return [
                'success' => false,
                'errors' => ['mdFile' => ['Please select a file.']],
            ];
        }

        if ($file->size > 1048576) {
            return [
                'success' => false,
                'errors' => ['mdFile' => ['File size must not exceed 1MB.']],
            ];
        }

        $allowedExtensions = ['md', 'markdown', 'txt'];
        if (!in_array(strtolower($file->extension), $allowedExtensions, true)) {
            return [
                'success' => false,
                'errors' => ['mdFile' => ['Invalid file type. Accepted: .md, .markdown, .txt']],
            ];
        }

        $markdownContent = @file_get_contents($file->tempName);
        if ($markdownContent === false) {
            return ['success' => false, 'message' => 'Failed to read uploaded file.'];
        }

        $parser = new MarkdownParser();
        $blocks = $parser->parse($markdownContent);
        $deltaWriter = new QuillDeltaWriter();
        $deltaJson = $deltaWriter->writeFromBlocks($blocks);

        return [
            'success' => true,
            'importData' => [
                'content' => $deltaJson,
            ],
        ];
    }

    public function actionImportText(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $rawBody = Yii::$app->request->rawBody;
        $data = json_decode($rawBody, true);

        if ($data === null) {
            return ['success' => false, 'message' => 'Invalid JSON data.'];
        }

        $text = $data['text'] ?? '';

        if (trim($text) === '') {
            return ['success' => false, 'message' => 'Text content is empty.'];
        }

        $isMarkdown = MarkdownDetector::isMarkdown($text);

        if ($isMarkdown) {
            $parser = new MarkdownParser();
            $blocks = $parser->parse($text);
            $deltaWriter = new QuillDeltaWriter();
            $deltaJson = $deltaWriter->writeFromBlocks($blocks);
        } else {
            $deltaJson = json_encode([
                'ops' => [
                    ['insert' => $text . "\n"],
                ],
            ]);
        }

        return [
            'success' => true,
            'importData' => [
                'content' => $deltaJson,
            ],
            'format' => $isMarkdown ? 'md' : 'txt',
        ];
    }

    public function actionConvertFormat(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $rawBody = Yii::$app->request->rawBody;
        $data = json_decode($rawBody, true);

        if ($data === null) {
            return ['success' => false, 'message' => 'Invalid JSON data.'];
        }

        $content = $data['content'] ?? '';
        $format = $data['format'] ?? 'text';

        $copyType = CopyType::tryFrom($format) ?? CopyType::TEXT;
        $converter = new CopyFormatConverter();
        $convertedContent = $converter->convertFromQuillDelta($content, $copyType);

        return [
            'success' => true,
            'content' => $convertedContent,
        ];
    }

    public function actionImportYoutube(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $rawBody = Yii::$app->request->rawBody;
        $data = json_decode($rawBody, true);

        if ($data === null) {
            return ['success' => false, 'message' => 'Invalid JSON data.'];
        }

        $form = new YouTubeImportForm();
        if (!$form->load($data, '') || !$form->validate()) {
            return ['success' => false, 'errors' => $form->getErrors()];
        }

        if ($form->project_id !== null) {
            $project = Project::find()->findUserProject($form->project_id, Yii::$app->user->id);
            if ($project === null) {
                return ['success' => false, 'errors' => ['project_id' => ['Invalid project selected.']]];
            }
        }

        try {
            $transcriptData = $this->youtubeService->fetchTranscript($form->videoId);
            $deltaJson = $this->youtubeService->convertToQuillDelta($transcriptData);
            $title = $this->youtubeService->getTitle($transcriptData);

            $model = new ScratchPad([
                'user_id' => Yii::$app->user->id,
                'project_id' => $form->project_id,
                'name' => $title,
                'content' => $deltaJson,
            ]);

            if ($model->save()) {
                return [
                    'success' => true,
                    'id' => $model->id,
                    'message' => 'YouTube transcript imported successfully.',
                ];
            }

            return ['success' => false, 'errors' => $model->getErrors()];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionClaude(int $id): string
    {
        $model = $this->findModel($id);

        if ($model->project_id === null) {
            throw new NotFoundHttpException('Claude CLI requires a project.');
        }

        return $this->render('claude', [
            'model' => $model,
            'projectList' => Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id),
            'claudeCommands' => $this->loadClaudeCommands($model->project->root_directory),
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionRunClaude(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

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
        $model = $this->findModel($id);
        $prepared = $this->prepareClaudeRequest($model);

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
                $prepared['sessionId']
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

        $cancelled = $this->claudeCliService->cancelRunningProcess();

        return [
            'success' => true,
            'cancelled' => $cancelled,
        ];
    }

    /**
     * Parses the request body and resolves prompt, options, and working directory for Claude CLI.
     *
     * @return array{markdown: string, options: array, workingDirectory: string, project: ?Project, sessionId: ?string}|array{error: array}
     */
    private function prepareClaudeRequest(ScratchPad $model): array
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
            $markdown = $this->claudeCliService->convertToMarkdown($model->content ?? '');
        }

        if (trim($markdown) === '') {
            $errorMsg = $customPrompt !== null ? 'Prompt is empty.' : 'Scratch pad content is empty.';
            return ['error' => ['success' => false, 'error' => $errorMsg]];
        }

        $project = $model->project;
        $projectDefaults = $project !== null ? $project->getClaudeOptions() : [];

        $allowedKeys = ['model', 'permissionMode', 'appendSystemPrompt', 'allowedTools', 'disallowedTools'];
        $options = array_merge(
            $projectDefaults,
            array_filter(
                array_intersect_key($requestOptions, array_flip($allowedKeys)),
                fn($v) => $v !== null && $v !== ''
            )
        );

        $workingDirectory = $project !== null && !empty($project->root_directory)
            ? $project->root_directory
            : '';

        return [
            'markdown' => $markdown,
            'options' => $options,
            'workingDirectory' => $workingDirectory,
            'project' => $project,
            'sessionId' => $requestOptions['sessionId'] ?? null,
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

    /**
     * @throws NotFoundHttpException
     */
    public function actionSummarizeSession(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        /** @var ScratchPad $model */
        $model = $this->findModel($id);

        $requestData = json_decode(Yii::$app->request->rawBody, true) ?? [];
        if (!is_array($requestData)) {
            return ['success' => false, 'error' => 'Invalid request format.'];
        }
        $conversation = $requestData['conversation'] ?? '';

        if (!is_string($conversation) || trim($conversation) === '') {
            return ['success' => false, 'error' => 'Conversation text is empty.'];
        }

        $project = $model->project;
        $workingDirectory = $project !== null && !empty($project->root_directory)
            ? $project->root_directory
            : '';

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
     * Loads available Claude slash commands from the project's .claude/commands/ directory.
     *
     * @return array<string, string> command name => description, sorted alphabetically
     */
    private function loadClaudeCommands(?string $rootDirectory): array
    {
        if ($rootDirectory === null || trim($rootDirectory) === '') {
            return [];
        }

        $containerPath = $this->claudeCliService->translatePath($rootDirectory);
        $commandsDir = rtrim($containerPath, '/') . '/.claude/commands';
        if (!is_dir($commandsDir)) {
            Yii::debug("Claude commands dir not found: '$commandsDir' (root: '$rootDirectory', mapped: '$containerPath')", 'claude');
            return [];
        }

        $files = glob($commandsDir . '/*.md');
        if ($files === false) {
            return [];
        }

        $commands = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $description = $this->parseCommandDescription($file);
            $commands[$name] = $description;
        }

        ksort($commands);

        return $commands;
    }

    /**
     * Extracts the description from a command file's YAML frontmatter.
     */
    private function parseCommandDescription(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return '';
        }

        if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
            if (preg_match('/^description:\s*(.+)$/m', $matches[1], $descMatch)) {
                return trim($descMatch[1]);
            }
        }

        return '';
    }

    /**
     * @throws NotFoundHttpException
     */
    protected function findModel(int $id): ActiveRecord
    {
        return ScratchPad::find()->where([
            'id' => $id,
            'user_id' => Yii::$app->user->id,
        ])->one() ?? throw new NotFoundHttpException('The requested Scratch Pad does not exist or is not yours.');
    }
}
