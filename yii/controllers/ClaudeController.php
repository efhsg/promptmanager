<?php

namespace app\controllers;

use app\handlers\ClaudeQuickHandler;
use app\jobs\RunClaudeJob;
use app\models\AiRun;
use app\models\AiRunSearch;
use app\models\Project;
use app\services\ClaudeCliService;
use app\services\ClaudeRunCleanupService;
use app\services\ClaudeStreamRelayService;
use app\services\EntityPermissionService;
use common\enums\AiRunStatus;
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
    private readonly ClaudeStreamRelayService $streamRelayService;
    private readonly ClaudeRunCleanupService $cleanupService;

    public function __construct(
        $id,
        $module,
        EntityPermissionService $permissionService,
        ClaudeCliService $claudeCliService,
        ClaudeQuickHandler $claudeQuickHandler,
        ClaudeStreamRelayService $streamRelayService,
        ClaudeRunCleanupService $cleanupService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->permissionService = $permissionService;
        $this->claudeCliService = $claudeCliService;
        $this->claudeQuickHandler = $claudeQuickHandler;
        $this->streamRelayService = $streamRelayService;
        $this->cleanupService = $cleanupService;
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'stream' => ['POST'],
                    'cancel' => ['POST'],
                    'start-run' => ['POST'],
                    'cancel-run' => ['POST'],
                    'delete-session' => ['POST'],
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
                        // Run-based endpoints — ownership validated via AiRunQuery::forUser()
                        'actions' => ['stream-run', 'cancel-run', 'run-status', 'runs', 'delete-session', 'cleanup'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => [
                            'index', 'stream', 'cancel',
                            'start-run', 'active-runs',
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
     * Releases the PHP session lock immediately after access control for long-running actions.
     *
     * PHP's file-based session handler acquires an exclusive lock. SSE streaming
     * and async run actions hold connections open for minutes; without early release,
     * concurrent requests from the same browser session block on the lock.
     *
     * The user identity is already cached in memory by the access control filter,
     * so Yii::$app->user->id remains available after session close.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $sessionFreeActions = ['stream', 'start-run', 'stream-run', 'cancel-run', 'run-status', 'active-runs'];
        if (in_array($action->id, $sessionFreeActions, true)) {
            Yii::$app->session->close();
        }

        return true;
    }

    public function actionRuns(): string
    {
        $userId = Yii::$app->user->id;
        $searchModel = new AiRunSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            $userId
        );

        $projectList = Yii::$app->projectService->fetchProjectsList($userId);
        $defaultProjectId = Yii::$app->projectContext->getCurrentProject()?->id;

        // Fall back to first project if context project is not in the list
        if ($defaultProjectId === null || !isset($projectList[$defaultProjectId])) {
            $defaultProjectId = array_key_first($projectList);
        }

        return $this->render('runs', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'projectList' => $projectList,
            'defaultProjectId' => $defaultProjectId,
        ]);
    }

    /**
     * Deletes a session (all terminal runs with the same session_id).
     *
     * @throws NotFoundHttpException
     */
    public function actionDeleteSession(int $id): Response
    {
        $run = AiRun::find()
            ->forUser(Yii::$app->user->id)
            ->andWhere(['id' => $id])
            ->one() ?? throw new NotFoundHttpException('Run not found.');

        $deleted = $this->cleanupService->deleteSession($run);
        Yii::$app->session->setFlash('success', "$deleted run(s) deleted.");

        return $this->redirect(['runs']);
    }

    /**
     * GET: shows confirmation page with counts. POST: executes bulk cleanup.
     *
     * @return string|Response
     */
    public function actionCleanup()
    {
        $userId = Yii::$app->user->id;

        if (!Yii::$app->request->isPost) {
            return $this->render('cleanup-confirm', [
                'sessionCount' => $this->cleanupService->countTerminalSessions($userId),
                'runCount' => $this->cleanupService->countTerminalRuns($userId),
            ]);
        }

        $deleted = $this->cleanupService->bulkCleanup($userId);
        Yii::$app->session->setFlash('success', "$deleted run(s) deleted.");

        return $this->redirect(['runs']);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionIndex(int $p, ?string $breadcrumbs = null, ?string $s = null, ?int $run = null): string
    {
        $project = $this->findProject($p);
        $rootDir = $project->root_directory;

        $replayRunId = null;
        $replayRunSummary = null;
        $sessionHistory = [];
        if ($run !== null) {
            $userId = Yii::$app->user->id;
            $replayRun = AiRun::find()
                ->forUser($userId)
                ->andWhere(['id' => $run])
                ->one();
            if ($replayRun !== null) {
                $replayRunSummary = $replayRun->prompt_summary;
                $sessionId = $replayRun->session_id ?? $s;

                if ($sessionId !== null) {
                    $allRuns = AiRun::find()
                        ->forUser($userId)
                        ->forSession($sessionId)
                        ->terminal()
                        ->orderedByCreated()
                        ->all();

                    foreach ($allRuns as $sessionRun) {
                        $sessionHistory[] = $this->buildRunHistoryEntry($sessionRun);
                    }
                }

                if (!$replayRun->isTerminal()) {
                    $replayRunId = $replayRun->id;
                } elseif ($sessionId === null) {
                    $sessionHistory[] = $this->buildRunHistoryEntry($replayRun);
                }
            }
        }

        return $this->render('index', [
            'project' => $project,
            'projectList' => Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id),
            'claudeCommands' => $this->loadClaudeCommands($rootDir, $project),
            'gitBranch' => $rootDir ? $this->claudeCliService->getGitBranch($rootDir) : null,
            'breadcrumbs' => $breadcrumbs,
            'resumeSessionId' => $s,
            'replayRunId' => $replayRunId,
            'replayRunSummary' => $replayRunSummary,
            'sessionHistory' => $sessionHistory,
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
     * Streaming endpoint — Phase 1 migration wrapper.
     *
     * Creates an async run and relays the stream, giving the frontend
     * a runId for reconnect support while keeping the same SSE format.
     *
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

        $userId = Yii::$app->user->id;
        $activeCount = AiRun::find()->forUser($userId)->active()->count();
        if ($activeCount >= AiRun::MAX_CONCURRENT_RUNS) {
            $this->sendSseError('Maximum concurrent runs reached (' . AiRun::MAX_CONCURRENT_RUNS . ').');
            return;
        }

        $run = $this->createRun($project, $prepared);
        if ($run === null) {
            $this->sendSseError('Failed to create run.');
            return;
        }

        $this->relayRunStream($run, 0);
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

    // ---------------------------------------------------------------
    // Async run endpoints
    // ---------------------------------------------------------------

    /**
     * Creates a new AiRun and pushes it to the queue.
     *
     * @throws NotFoundHttpException
     */
    public function actionStartRun(int $p): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $project = $this->findProject($p);
        $prepared = $this->prepareClaudeRequest($project);

        if (isset($prepared['error'])) {
            return $prepared['error'];
        }

        $userId = Yii::$app->user->id;
        $activeCount = AiRun::find()->forUser($userId)->active()->count();

        if ($activeCount >= AiRun::MAX_CONCURRENT_RUNS) {
            Yii::$app->response->statusCode = 429;
            return [
                'success' => false,
                'error' => 'Maximum concurrent runs reached (' . AiRun::MAX_CONCURRENT_RUNS . ').',
            ];
        }

        $run = $this->createRun($project, $prepared);
        if ($run === null) {
            return [
                'success' => false,
                'error' => 'Failed to create run.',
            ];
        }

        return [
            'success' => true,
            'runId' => $run->id,
            'promptMarkdown' => $prepared['markdown'],
        ];
    }

    /**
     * SSE endpoint that relays stream events from a run's NDJSON file.
     *
     * @throws NotFoundHttpException
     */
    public function actionStreamRun(int $runId, int $offset = 0): void
    {
        $run = AiRun::find()
            ->forUser(Yii::$app->user->id)
            ->andWhere(['id' => $runId])
            ->one();

        if ($run === null) {
            throw new NotFoundHttpException('Run not found.');
        }

        $this->relayRunStream($run, $offset);
    }

    /**
     * Cancels a running run via DB status update.
     *
     * @throws NotFoundHttpException
     */
    public function actionCancelRun(int $runId): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $run = AiRun::find()
            ->forUser(Yii::$app->user->id)
            ->andWhere(['id' => $runId])
            ->one();

        if ($run === null) {
            throw new NotFoundHttpException('Run not found.');
        }

        if (!$run->isActive()) {
            return ['success' => true, 'cancelled' => false, 'reason' => 'Run is not active.'];
        }

        $run->markCancelled();

        return ['success' => true, 'cancelled' => true];
    }

    /**
     * Returns the current status and metadata of a run.
     *
     * @throws NotFoundHttpException
     */
    public function actionRunStatus(int $runId): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $run = AiRun::find()
            ->forUser(Yii::$app->user->id)
            ->andWhere(['id' => $runId])
            ->one();

        if ($run === null) {
            throw new NotFoundHttpException('Run not found.');
        }

        return [
            'success' => true,
            'id' => $run->id,
            'status' => $run->status,
            'sessionId' => $run->session_id,
            'resultMetadata' => $run->getDecodedResultMetadata(),
            'errorMessage' => $run->error_message,
            'startedAt' => $run->started_at,
            'completedAt' => $run->completed_at,
        ];
    }

    /**
     * Returns active and recent runs for a project.
     *
     * @throws NotFoundHttpException
     */
    public function actionActiveRuns(int $p): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $this->findProject($p);
        $userId = Yii::$app->user->id;

        $runs = AiRun::find()
            ->forUser($userId)
            ->forProject($p)
            ->andWhere(['or',
                ['status' => AiRunStatus::activeValues()],
                ['and',
                    ['status' => AiRunStatus::COMPLETED->value],
                    ['>=', 'completed_at', date('Y-m-d H:i:s', time() - 3600)],
                ],
            ])
            ->orderedByCreated()
            ->all();

        $result = [];
        foreach ($runs as $run) {
            $result[] = [
                'id' => $run->id,
                'status' => $run->status,
                'promptSummary' => $run->prompt_summary,
                'sessionId' => $run->session_id,
                'startedAt' => $run->started_at,
                'createdAt' => $run->created_at,
            ];
        }

        return [
            'success' => true,
            'runs' => $result,
        ];
    }

    // ---------------------------------------------------------------
    // Existing endpoints
    // ---------------------------------------------------------------

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

        if (!$result['success']) {
            return $result;
        }

        $title = $result['output'];
        $runId = $requestData['runId'] ?? null;

        if ($runId !== null && is_numeric($runId)) {
            $this->updatePromptSummary((int) $runId, $title);
        }

        return ['success' => true, 'title' => $title];
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

    private function buildRunHistoryEntry(AiRun $run): array
    {
        $meta = $run->getDecodedResultMetadata();

        return [
            'id' => $run->id,
            'status' => $run->status,
            'promptMarkdown' => $run->prompt_markdown,
            'promptSummary' => $run->prompt_summary,
            'resultText' => $run->result_text,
            'errorMessage' => $run->error_message,
            'metadata' => [
                'duration_ms' => $meta['duration_ms'] ?? null,
                'model' => $meta['model'] ?? null,
                'input_tokens' => $meta['input_tokens'] ?? null,
                'cache_tokens' => $meta['cache_tokens'] ?? null,
                'output_tokens' => $meta['output_tokens'] ?? null,
                'num_turns' => $meta['num_turns'] ?? null,
                'total_cost_usd' => $meta['total_cost_usd'] ?? null,
            ],
        ];
    }

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

    /**
     * Creates a AiRun record and pushes the job to the queue.
     *
     * @return AiRun|null The saved run, or null on validation failure
     */
    private function createRun(Project $project, array $prepared): ?AiRun
    {
        $run = new AiRun();
        $run->user_id = Yii::$app->user->id;
        $run->project_id = $project->id;
        $run->prompt_markdown = $prepared['markdown'];
        $run->prompt_summary = mb_substr($prepared['markdown'], 0, 255);
        $run->session_id = $prepared['sessionId'];
        $run->options = json_encode($prepared['options']);
        $run->working_directory = $prepared['workingDirectory'];

        if (!$run->save()) {
            return null;
        }

        $job = new RunClaudeJob();
        $job->runId = $run->id;
        Yii::$app->queue->push($job);

        return $run;
    }

    private function updatePromptSummary(int $runId, string $title): void
    {
        $run = AiRun::find()
            ->forUser(Yii::$app->user->id)
            ->andWhere(['id' => $runId])
            ->one();

        if ($run === null) {
            return;
        }

        $run->prompt_summary = mb_substr($title, 0, 255);
        $run->save(false, ['prompt_summary']);
    }

    /**
     * Waits for the stream file, relays NDJSON events via SSE, and sends [DONE].
     */
    private function relayRunStream(AiRun $run, int $offset): void
    {
        ignore_user_abort(false);

        $this->beginSseResponse();

        echo "data: " . json_encode([
            'type' => 'prompt_markdown',
            'markdown' => $run->prompt_markdown,
            'runId' => $run->id,
        ]) . "\n\n";
        flush();

        $streamFilePath = $run->getStreamFilePath();

        // Wait for stream file (max 10s)
        $waitStart = time();
        while (!file_exists($streamFilePath) && (time() - $waitStart) < 10) {
            echo "data: " . json_encode(['type' => 'waiting']) . "\n\n";
            flush();
            sleep(1);

            if (connection_aborted()) {
                return;
            }

            $run->refresh();
            if ($run->isTerminal()) {
                break;
            }

            clearstatcache(true, $streamFilePath);
        }

        clearstatcache(true, $streamFilePath);

        // Relay stream events from file
        $linesSent = 0;
        $this->streamRelayService->relay(
            $streamFilePath,
            $offset,
            function (string $line) use (&$linesSent): void {
                echo "data: " . $line . "\n\n";
                flush();
                $linesSent++;
            },
            function () use ($run): bool {
                if (connection_aborted()) {
                    return false;
                }

                $run->refresh();
                return $run->isActive();
            },
            self::CLAUDE_TIMEOUT
        );

        // DB fallback: send any lines the relay missed due to cross-container filesystem sync delays
        $run->refresh();
        if ($run->isTerminal() && $run->stream_log !== null) {
            $allLines = array_values(array_filter(
                array_map('trim', explode("\n", $run->stream_log)),
                fn(string $l): bool => $l !== '' && $l !== '[DONE]'
            ));
            $missedLines = array_slice($allLines, $linesSent);
            foreach ($missedLines as $line) {
                echo "data: " . $line . "\n\n";
                flush();
            }
        }

        echo "data: [DONE]\n\n";
        flush();
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
