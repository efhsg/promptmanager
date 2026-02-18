<?php

namespace app\controllers;

use app\components\ProjectContext;
use app\models\Project;
use app\models\ProjectSearch;
use app\services\ai\AiConfigProviderInterface;
use app\services\ai\AiProviderRegistry;
use app\services\EntityPermissionService;
use app\services\ProjectService;
use common\enums\LogCategory;
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
    private ProjectContext $projectContext;
    private array $actionPermissionMap;
    private readonly EntityPermissionService $permissionService;
    private readonly ProjectService $projectService;
    private readonly AiProviderRegistry $providerRegistry;

    public function __construct(
        $id,
        $module,
        EntityPermissionService $permissionService,
        ProjectService $projectService,
        AiProviderRegistry $providerRegistry,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->permissionService = $permissionService;
        $this->projectService = $projectService;
        $this->providerRegistry = $providerRegistry;
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
            $this->loadAiOptions($model);
            if ($model->save()) {
                $this->projectService->syncLinkedProjects($model, $model->linkedProjectIds);
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $availableProjects = $this->projectService->fetchAvailableProjectsForLinking(null, Yii::$app->user->id);

        return $this->render('create', [
            'model' => $model,
            'availableProjects' => $availableProjects,
            'providers' => $this->buildProviderViewData(),
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
            $this->loadAiOptions($model);
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
            foreach ($this->providerRegistry->all() as $id => $provider) {
                if ($provider instanceof AiConfigProviderInterface) {
                    $configStatus = $provider->checkConfig($model->root_directory);
                    $projectConfigStatus[$id] = array_merge($configStatus, [
                        'providerName' => $provider->getName(),
                        'hasCLAUDE_MD' => $configStatus['hasConfigFile'] ?? false,
                        'hasClaudeDir' => $configStatus['hasConfigDir'] ?? false,
                    ]);
                }
            }
        }

        return $this->render('update', [
            'model' => $model,
            'availableProjects' => $availableProjects,
            'projectConfigStatus' => $projectConfigStatus,
            'providers' => $this->buildProviderViewData(),
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
            Yii::error($e->getMessage(), LogCategory::DATABASE->value);
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
     * Returns available AI slash commands for a project.
     *
     * @throws NotFoundHttpException
     */
    public function actionAiCommands(int $id, ?string $provider = null): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        /** @var Project $model */
        $model = $this->findModel($id);

        if (empty($model->root_directory)) {
            return ['success' => false, 'commands' => []];
        }

        $resolved = $this->providerRegistry->getDefault();
        if ($provider !== null && $this->providerRegistry->has($provider)) {
            $resolved = $this->providerRegistry->get($provider);
        }

        return [
            'success' => true,
            'commands' => $resolved instanceof AiConfigProviderInterface
                ? $resolved->loadCommands($model->root_directory)
                : [],
        ];
    }

    /**
     * @deprecated Use AiChatController::actionIndex instead. Redirects for backward compatibility.
     * @throws NotFoundHttpException
     */
    public function actionClaude(int $id): Response
    {
        $this->findModel($id);
        return $this->redirect(['/ai-chat/index', 'p' => $id]);
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

    /**
     * @return array<string, array{name: string, models: array, permissionModes: array, configSchema: array}>
     */
    private function buildProviderViewData(): array
    {
        $data = [];
        foreach ($this->providerRegistry->all() as $id => $provider) {
            if (!$provider instanceof AiConfigProviderInterface) {
                continue;
            }
            $data[$id] = [
                'name' => $provider->getName(),
                'models' => $provider->getSupportedModels(),
                'permissionModes' => $provider->getSupportedPermissionModes(),
                'configSchema' => $provider->getConfigSchema(),
            ];
        }

        return $data;
    }

    private function loadAiOptions(Project $model): void
    {
        $aiOptions = Yii::$app->request->post('ai_options', []);

        foreach ($aiOptions as $providerId => $providerOptions) {
            if (is_array($providerOptions) && $this->providerRegistry->has($providerId)) {
                $model->setAiOptionsForProvider($providerId, $providerOptions);
            }
        }
    }

}
