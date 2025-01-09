<?php /** @noinspection PhpUnused */


namespace app\controllers;

use app\models\Project;
use app\models\ProjectSearch;
use Throwable;
use Yii;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * ProjectController implements the CRUD actions for Project model.
 */
class ProjectController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'create', 'update', 'delete', 'delete-confirm'],
                        'allow' => true,
                        'roles' => ['@'], // only logged-in users
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
        $model = new Project();

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
        // If user hasn't confirmed, show the confirm page
        if (!Yii::$app->request->post('confirm')) {
            return $this->actionDeleteConfirm($id);
        }

        $model = $this->findModel($id);
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
     * Show a confirmation page before deleting the Project.
     *
     * @param int $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionDeleteConfirm(int $id): string
    {
        $model = $this->findModel($id);

        return $this->render('delete-confirm', [
            'model' => $model,
        ]);
    }

    /**
     * Finds the Project model by ID,
     * ensuring that it belongs to the logged-in user.
     *
     * @param int $id
     * @return Project
     * @throws NotFoundHttpException
     */
    protected function findModel(int $id): Project
    {
        $model = Project::findOne(['id' => $id, 'user_id' => Yii::$app->user->id]);
        if (!$model) {
            throw new NotFoundHttpException('The requested Project does not exist or is not yours.');
        }
        return $model;
    }
}
