<?php /** @noinspection DuplicatedCode */

/** @noinspection PhpUnused */


namespace app\controllers;

use app\models\Context;
use app\models\ContextSearch;
use app\models\Project;
use Throwable;
use Yii;
use yii\data\ArrayDataProvider;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * ContextController implements the CRUD actions for Context model.
 */
class ContextController extends Controller
{

    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {
        return array_merge(
            parent::behaviors(),
            [
                'access' => [
                    'class' => AccessControl::class,
                    'rules' => [
                        [
                            'actions' => ['index', 'view', 'create', 'update', 'delete', 'delete-confirm'],
                            'allow' => true,
                            'roles' => ['@'],
                        ],
                    ],
                ],
            ]
        );
    }

    public function actionIndex(): string
    {
        $searchModel = new ContextSearch();
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
     * Displays a single Context model.
     *
     * @param int $id
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView(int $id): string
    {
        $model = $this->findModel($id);
        $model->populateRelation('project', $model->project);

        return $this->render('view', [
            'model' => $model,
        ]);
    }


    /**
     * Creates a new Context model.
     *
     * @return string|Response
     * @throws Exception
     */
    public function actionCreate(): Response|string
    {
        $model = new Context();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
            'projects' => $this->fetchProjectsList(),
        ]);
    }

    /**
     * Updates an existing Context model.
     *
     * @param int $id
     * @return string|Response
     * @throws NotFoundHttpException|Exception
     */
    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
            'projects' => $this->fetchProjectsList(),
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
            Yii::$app->session->setFlash('success', "Context '$model->name' deleted successfully.");
        } catch (Throwable $e) {
            Yii::error($e->getMessage(), 'database');
            Yii::$app->session->setFlash(
                'error',
                'Unable to delete the context. Please try again later.'
            );
        }
        return $this->redirect(['index']);
    }

    /**
     * Finds the Context model based on its primary key value.
     *
     * @param int $id
     * @return Context
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id): Context
    {
        if (($model = Context::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested context does not exist.');
    }

    /**
     * @return array
     */
    private function fetchProjectsList(): array
    {
        return ArrayHelper::map(
            Project::find()
                ->where(['user_id' => Yii::$app->user->id])
                ->all() ?: [],
            'id',
            'name'
        );
    }
}
