<?php /** @noinspection PhpUnused */


namespace app\controllers;

use app\components\ProjectContext;
use app\models\Project;
use app\models\ProjectSearch;
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
 * ProjectController implements the CRUD actions for Project model.
 */
class ProjectController extends Controller
{

    private ProjectContext $projectContext;

    public function init(): void
    {
        parent::init();
        $this->projectContext = Yii::$app->projectContext;
    }

    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'create', 'update', 'delete', 'set-current'],
                        'allow' => true,
                        'roles' => ['@'],
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
     * @return string|Response
     * @throws Exception
     */
    public function actionCreate(): Response|string
    {
        $model = new Project(['user_id' => Yii::$app->user->id]);

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
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
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
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
            Yii::$app->session->setFlash(
                'error',
                'Unable to delete the project. Please try again later.'
            );
        }
        return $this->redirect(['index']);
    }

    /**
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function actionSetCurrent(): Response
    {
        $projectId = Yii::$app->request->post('project_id');
        $this->projectContext->setCurrentProject((int)$projectId);

        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
    }


    /**
     * @throws NotFoundHttpException
     */
    protected function findModel(int $id): array|ActiveRecord
    {
        return Project::find()->where([
            'id' => $id,
            'user_id' => Yii::$app->user->id,
        ])->one() ?? throw new NotFoundHttpException('The requested Project does not exist or is not yours.');
    }
}
