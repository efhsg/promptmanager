<?php /** @noinspection DuplicatedCode */

namespace app\controllers;

use app\models\Field;
use app\models\FieldOption;
use app\models\FieldSearch;
use app\models\Project;
use Throwable;
use Yii;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Yii2\Extensions\DynamicForm\Models\Model;

/**
 * FieldController implements the CRUD actions for Field model.
 */
class FieldController extends Controller
{
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
                        'actions' => ['index', 'view', 'create', 'update', 'delete'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists all Field models.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        $searchModel = new FieldSearch();
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
     * Displays a single Field model.
     * @param int $id ID
     * @return string
     * @throws ForbiddenHttpException
     */
    public function actionView(int $id): string
    {
        $model = Field::find()->where(['field.id' => $id])->joinWith('project')->one();

        if (!Yii::$app->user->can('viewField', ['model' => $model])) {
            throw new ForbiddenHttpException('You are not allowed to view this field.');
        }

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * @throws Throwable
     * @throws Exception
     */
    public function actionCreate(): Response|array|string
    {
        $modelField = new Field(['user_id' => Yii::$app->user->id]);
        $modelsFieldOption = [new FieldOption()];

        if (!Yii::$app->user->can('createField')) {
            throw new ForbiddenHttpException('You are not allowed to create a field.');
        }

        if ($modelField->load(Yii::$app->request->post())) {

            $modelsFieldOption = Model::createMultiple(FieldOption::class);
            Model::loadMultiple($modelsFieldOption, Yii::$app->request->post());

            $valid = $modelField->validate();
            $valid = Model::validateMultiple($modelsFieldOption) && $valid;

            if ($valid) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    if ($flag = $modelField->save(false)) {
                        foreach ($modelsFieldOption as $modelFieldOption) {
                            $modelFieldOption->field_id = $modelField->id;
                            if (!($flag = $modelFieldOption->save(false))) {
                                $transaction->rollBack();
                                break;
                            }
                        }
                    }
                    if ($flag) {
                        $transaction->commit();
                        return $this->redirect(['view', 'id' => $modelField->id]);
                    }
                } catch (Throwable $e) {
                    $transaction->rollBack();
                    throw $e;
                }
            }
        }

        return $this->render('create', [
            'modelField' => $modelField,
            'modelsFieldOption' => empty($modelsFieldOption) ? [new FieldOption()] : $modelsFieldOption,
            'projects' => $this->fetchProjectsList(),
        ]);
    }

    /**
     * Fetches a list of projects for the logged-in user.
     *
     * @return array
     */
    private function fetchProjectsList(): array
    {
        return ArrayHelper::map(
            Project::find()
                ->where(['user_id' => Yii::$app->user->id])
                ->all(),
            'id',
            'name'
        );
    }


    /**
     * @throws Exception
     * @throws Throwable
     * @throws NotFoundHttpException
     */
    public function actionUpdate(int $id): Response|string
    {
        $modelField = $this->findModel($id);
        $modelsFieldOption = $modelField->fieldOptions;

        if (!Yii::$app->user->can('updateField', ['model' => $modelField])) {
            throw new ForbiddenHttpException('You are not allowed to update this field.');
        }

        if ($modelField->load(Yii::$app->request->post())) {

            $oldIDs = ArrayHelper::map($modelsFieldOption, 'id', 'id');
            $modelsFieldOption = Model::createMultiple(FieldOption::class, $modelsFieldOption);
            Model::loadMultiple($modelsFieldOption, Yii::$app->request->post());

            $newIDs = ArrayHelper::map($modelsFieldOption, 'id', 'id');
            $deletedIDs = array_diff($oldIDs, array_filter($newIDs));

            $valid = $modelField->validate();
            $valid = Model::validateMultiple($modelsFieldOption) && $valid;

            if ($valid) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    if ($flag = $modelField->save(false)) {

                        if (!empty($deletedIDs)) {
                            FieldOption::deleteAll(['id' => $deletedIDs]);
                        }

                        foreach ($modelsFieldOption as $modelFieldOption) {
                            $modelFieldOption->field_id = $modelField->id;
                            if (!($flag = $modelFieldOption->save(false))) {
                                $transaction->rollBack();
                                break;
                            }
                        }
                    }

                    if ($flag) {
                        $transaction->commit();
                        return $this->redirect(['view', 'id' => $modelField->id]);
                    }
                } catch (Throwable $e) {
                    $transaction->rollBack();
                    throw $e;
                }
            }
        }

        return $this->render('update', [
            'modelField' => $modelField,
            'modelsFieldOption' => (empty($modelsFieldOption)) ? [new FieldOption()] : $modelsFieldOption,
            'projects' => $this->fetchProjectsList(),
        ]);
    }


    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionDelete(int $id): Response|string
    {

        $model = $this->findModel($id);

        if (!Yii::$app->user->can('deleteField', ['model' => $model])) {
            throw new ForbiddenHttpException('You are not allowed to delete this field.');
        }

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
     * Finds the Field model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Field the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id): Field
    {
        if (($model = Field::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
