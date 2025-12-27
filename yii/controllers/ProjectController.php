<?php

namespace app\controllers;

use app\components\ProjectContext;
use app\models\Project;
use app\models\ProjectSearch;
use app\services\EntityPermissionService;
use app\services\ProjectService;
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

    public function __construct(
        $id,
        $module,
        EntityPermissionService $permissionService,
        ProjectService $projectService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->permissionService = $permissionService;
        $this->projectService = $projectService;
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

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post()) && $model->save()) {
            $this->projectService->syncLinkedProjects($model, $model->linkedProjectIds);
            return $this->redirect(['view', 'id' => $model->id]);
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

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post()) && $model->save()) {
            $this->projectService->syncLinkedProjects($model, $model->linkedProjectIds);
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $model->linkedProjectIds = array_map(
            static fn($project): int => $project->id,
            $model->linkedProjects
        );

        $availableProjects = $this->projectService->fetchAvailableProjectsForLinking($model->id, Yii::$app->user->id);

        return $this->render('update', [
            'model' => $model,
            'availableProjects' => $availableProjects,
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
     * @return Response
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function actionSetCurrent(): Response
    {
        $projectId = Yii::$app->request->post('project_id');
        $this->projectContext->setCurrentProject((int) $projectId);

        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
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
}
