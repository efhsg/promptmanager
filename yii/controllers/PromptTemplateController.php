<?php /** @noinspection DuplicatedCode */

namespace app\controllers;

use app\models\Field;
use app\models\Project;
use app\models\PromptTemplate;
use app\models\PromptTemplateSearch;
use Throwable;
use Yii;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * PromptTemplateController implements the CRUD actions for PromptTemplate model.
 */
class PromptTemplateController extends Controller
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
        $searchModel = new PromptTemplateSearch();
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
     * Displays a single PromptTemplate model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView(int $id): string
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * @throws Exception
     */
    public function actionCreate(): Response|string
    {
        $model = new PromptTemplate();

        if ($this->isPostRequest($model)) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $userId = Yii::$app->user->id;
        return $this->render('create', [
            'model' => $model,
            'projects' => $this->fetchProjectsList(),
            'generalFieldsMap' => $this->fetchFieldsMap($userId, null),
            'projectFieldsMap' => $this->fetchFieldsMap($userId, $model->project_id),
        ]);
    }

    /**
     * @throws Exception
     */
    private function isPostRequest(PromptTemplate $model): bool
    {
        return $model->load(Yii::$app->request->post()) && $model->save();
    }

    private function fetchFieldsMap(int $userId, ?int $projectId): array
    {
        $query = Field::find()
            ->where(['user_id' => $userId]);

        if ($projectId === null) {
            $query->andWhere(['project_id' => null]);
        } else {
            $query->andWhere(['project_id' => $projectId]);
        }

        $rawFields = $query->all();

        return array_reduce($rawFields, function (array $fieldsMap, Field $field) {
            $prefix = $field->project_id ? 'PRJ:' : 'GEN:';
            $placeholder = $prefix . '{{' . $field->name . '}}';
            $label = $field->label ?: $field->name;
            $fieldsMap[$placeholder] = [
                'label' => $label,
                'isProjectSpecific' => $field->project_id !== null
            ];
            return $fieldsMap;
        }, []);
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

        $userId = Yii::$app->user->id;
        return $this->render('update', [
            'model' => $model,
            'projects' => $this->fetchProjectsList(),
            'generalFieldsMap' => $this->fetchFieldsMap($userId, null),
            'projectFieldsMap' => $this->fetchFieldsMap($userId, $model->project_id),
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
            Yii::$app->session->setFlash('success', "Template '$model->name' deleted successfully.");
        } catch (Throwable $e) {
            Yii::error($e->getMessage(), 'database');
            Yii::$app->session->setFlash(
                'error',
                'Unable to delete the template. Please try again later.'
            );
        }
        return $this->redirect(['index']);
    }

    /**
     * Finds the PromptTemplate model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return PromptTemplate the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id): PromptTemplate
    {
        if (($model = PromptTemplate::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }


    /**
     * Fetches the list of projects for dropdown selection.
     *
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
