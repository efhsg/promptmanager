<?php /** @noinspection DuplicatedCode */

namespace app\controllers;

use app\models\Field;
use app\models\FieldOption;
use app\models\FieldSearch;
use app\models\Project;
use Throwable;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Yii2\Extensions\DynamicForm\Models\Model;

class FieldController extends Controller
{
    private array $actionPermissionMap = [
        'create' => 'createField',
        'view' => 'viewField',
        'update' => 'updateField',
        'delete' => 'deleteField',
    ];

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
                            $actionName = $action->id;
                            if (!isset($this->actionPermissionMap[$actionName])) {
                                return false;
                            }
                            $permission = $this->actionPermissionMap[$actionName];
                            $id = Yii::$app->request->get('id');
                            $model = in_array($actionName, ['view', 'update', 'delete']) ? Field::findOne($id) : null;
                            if ($actionName !== 'create' && $actionName !== 'index' && $model === null) {
                                throw new NotFoundHttpException('The requested page does not exist.');
                            }
                            if ($actionName === 'create') {
                                return Yii::$app->user->can($permission);
                            }
                            return Yii::$app->user->can($permission, ['model' => $model]);
                        },
                    ],
                ],
            ],
        ];
    }

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
     * @throws Throwable
     * @throws Exception
     */
    public function actionCreate(): Response|array|string
    {
        $modelField = new Field(['user_id' => Yii::$app->user->id]);
        $modelsFieldOption = [new FieldOption()];

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
     * @throws Throwable
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionUpdate(int $id): Response|string
    {
        /** @var Field $modelField */
        $modelField = $this->findModel($id);
        $modelsFieldOption = $modelField->fieldOptions;

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
     */
    public function actionDelete(int $id): Response|string
    {
        /** @var Field $model */
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
            Yii::$app->session->setFlash('error', 'Unable to delete the context. Please try again later.');
        }
        return $this->redirect(['index']);
    }

    /**
     * @throws NotFoundHttpException
     */
    protected function findModel(int $id): array|ActiveRecord
    {
        if (($model = Field::find()->with('project')->where(['id' => $id])->one()) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
