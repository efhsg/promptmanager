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

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $rawBody = Yii::$app->request->rawBody;
        $data = json_decode($rawBody, true);

        if ($data === null) {
            return ['success' => false, 'message' => 'Invalid JSON data.'];
        }

        $id = $data['id'] ?? null;
        $name = trim($data['name'] ?? '');
        $content = $data['content'] ?? '';
        $summation = $data['summation'] ?? '';
        $projectId = $data['project_id'] ?? null;

        if ($name === '') {
            return ['success' => false, 'errors' => ['name' => ['Name is required.']]];
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
        $model->summation = $summation;

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
    public function actionRunClaude(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        /** @var ScratchPad $model */
        $model = $this->findModel($id);

        $markdown = $this->claudeCliService->convertToMarkdown($model->content ?? '');
        if (trim($markdown) === '') {
            return [
                'success' => false,
                'error' => 'Scratch pad content is empty.',
            ];
        }

        // Get project if available
        $project = $model->project;

        // Parse request options and merge with project defaults
        $requestOptions = json_decode(Yii::$app->request->rawBody, true) ?? [];
        $projectDefaults = $project !== null ? $project->getClaudeOptions() : [];

        // Request options override project defaults (filter out empty values)
        $options = array_merge(
            $projectDefaults,
            array_filter($requestOptions, fn($v) => $v !== null && $v !== '')
        );

        // Determine working directory (will be resolved by ClaudeCliService)
        $workingDirectory = $project !== null && !empty($project->root_directory)
            ? $project->root_directory
            : '';

        $result = $this->claudeCliService->execute(
            $markdown,
            $workingDirectory,
            300,
            $options,
            $project
        );

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
            'exitCode' => $result['exitCode'],
            'cost_usd' => $result['cost_usd'] ?? null,
            'duration_ms' => $result['duration_ms'] ?? null,
            'configSource' => $result['configSource'] ?? null,
        ];
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
