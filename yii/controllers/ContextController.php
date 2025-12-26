<?php /** @noinspection DuplicatedCode */

/** @noinspection PhpUnused */

namespace app\controllers;

use app\components\ProjectContext;
use app\models\Context;
use app\models\ContextSearch;
use app\models\Project;
use app\services\ContextService;
use app\services\EntityPermissionService;
use app\services\ProjectService;
use Throwable;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller for managing Context entities: provides CRUD operations and ensures
 * contexts are tied to the user's projects and permission checks are enforced.
 */
class ContextController extends Controller
{
    /**
     * @var array
     */
    private array $actionPermissionMap;

    public function __construct(
        $id,
        $module,
        private readonly ProjectService $projectService,
        private readonly ContextService $contextService,
        private readonly EntityPermissionService $permissionService,
        $config = []
    )
    {
        parent::__construct($id, $module, $config);
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('context');
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'renumber'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'actions' => array_keys($this->actionPermissionMap),
                        'matchCallback' => function ($rule, $action) {
                            $callback = $this->permissionService->isModelBasedAction($action->id)
                                ? fn() => $this->findModel((int)Yii::$app->request->get('id'))
                                : null;
                            return $this->permissionService->hasActionPermission('context', $action->id, $callback);
                        },
                    ],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new ContextSearch();
        $projectContext = Yii::$app->projectContext;

        if ($projectContext->isNoProjectContext()) {
            $projectId = ProjectContext::NO_PROJECT_ID;
        } elseif ($projectContext->isAllProjectsContext()) {
            $projectId = null;
        } else {
            $projectId = $projectContext->getCurrentProject()?->id;
        }

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, Yii::$app->user->id, $projectId);
        return $this->render('index', compact('searchModel', 'dataProvider'));
    }

    /**
     * Displays a single Context model.
     *
     * @param int $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    /**
     * Creates a new Context model.
     *
     * @return Response|string
     * @throws Exception
     */
    public function actionCreate(): Response|string
    {
        $model = new Context();

        if ($model->load(Yii::$app->request->post())) {
            if ($this->contextService->saveContext($model)) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('create', [
            'model' => $model,
            'projects' => $this->projectService->fetchProjectsList(Yii::$app->user->id),
        ]);
    }

    /**
     * Updates an existing Context model.
     *
     * @param int $id
     * @return Response|string
     * @throws NotFoundHttpException|Exception
     */
    public function actionUpdate(int $id): Response|string
    {
        /** @var Context $model */
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            if ($this->contextService->saveContext($model)) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
            'projects' => $this->projectService->fetchProjectsList(Yii::$app->user->id),
        ]);
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws NotFoundHttpException
     */
    public function actionDelete(int $id): Response|string
    {
        /** @var Context $model */
        $model = $this->findModel($id);

        if (!Yii::$app->request->post('confirm')) {
            return $this->render('delete-confirm', ['model' => $model]);
        }

        return $this->contextService->deleteContext($model)
            ? $this->redirect(['index'])
            : $this->redirectWithError();
    }

    private function redirectWithError(): Response
    {
        Yii::$app->session->setFlash('error', 'Unable to delete the context. Please try again later.');
        return $this->redirect(['index']);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionRenumber(int $projectId): Response
    {
        $project = Project::find()->findUserProject($projectId, Yii::$app->user->id);
        if ($project === null) {
            throw new NotFoundHttpException('The requested project does not exist or is not yours.');
        }

        if ($this->contextService->renumberContexts($projectId)) {
            Yii::$app->session->setFlash('success', 'Contexts have been renumbered.');
        } else {
            Yii::$app->session->setFlash('error', 'Unable to renumber contexts. Please try again.');
        }

        return $this->redirect(['index']);
    }

    /**
     * Finds the Context model based on its primary key value and verifies
     * that it belongs to the current user (via its associated project).
     *
     * @param int $id
     * @return ActiveRecord
     * @throws NotFoundHttpException if the model cannot be found or is not owned by the user
     */
    protected function findModel(int $id): ActiveRecord
    {
        return Context::find()
            ->joinWith('project')
            ->where([
                'context.id'      => $id,
                'project.user_id' => Yii::$app->user->id,
            ])
            ->one() ?? throw new NotFoundHttpException('The requested context does not exist or is not yours.');
    }
}
