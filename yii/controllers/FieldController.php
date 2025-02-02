<?php

namespace app\controllers;

use app\models\Field;
use app\models\FieldOption;
use app\models\FieldSearch;
use app\services\EntityPermissionService;
use app\services\FieldService;
use app\services\ProjectService;
use Yii;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Yii2\Extensions\DynamicForm\Models\Model;

class FieldController extends Controller
{
    private array $actionPermissionMap;

    public function __construct(
        $id,
        $module,
        private readonly ProjectService $projectService,
        private readonly FieldService $fieldService,
        private readonly EntityPermissionService $permissionService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('field');
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
                            $callback = in_array($action->id, ['view', 'update', 'delete'], true)
                                ? fn() => $this->findModel((int)Yii::$app->request->get('id'))
                                : null;
                            return $this->permissionService->hasActionPermission('field', $action->id, $callback);
                        },
                    ],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new FieldSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, Yii::$app->user->id);
        return $this->render('index', compact('searchModel', 'dataProvider'));
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionCreate(): Response|string
    {
        return $this->handleCreateOrUpdate(new Field(['user_id' => Yii::$app->user->id]), [new FieldOption()]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionUpdate(int $id): Response|string
    {
        /** @var Field $model */
        $model = $this->findModel($id);
        return $this->handleCreateOrUpdate($model, $model->fieldOptions);
    }

    private function handleCreateOrUpdate(Field $modelField, array $modelsFieldOption): Response|string
    {
        $postData = Yii::$app->request->post();
        if ($modelField->load($postData)) {
            $modelsFieldOption = Model::createMultiple(FieldOption::class, $modelsFieldOption);
            Model::loadMultiple($modelsFieldOption, $postData);
            if ($this->fieldService->saveFieldWithOptions($modelField, $modelsFieldOption)) {
                return $this->redirect(['view', 'id' => $modelField->id]);
            }
        }
        return $this->render($modelField->isNewRecord ? 'create' : 'update', [
            'modelField' => $modelField,
            'modelsFieldOption' => $modelsFieldOption ?: [new FieldOption()],
            'projects' => $this->projectService->fetchProjectsList(Yii::$app->user->id),
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
            return $this->render('delete-confirm', compact('model'));
        }
        return $this->fieldService->deleteField($model)
            ? $this->redirect(['index'])
            : $this->redirectWithError();
    }

    private function redirectWithError(): Response
    {
        Yii::$app->session->setFlash('error', 'Unable to delete the field. Please try again later.');
        return $this->redirect(['index']);
    }

    /**
     * @throws NotFoundHttpException
     */
    protected function findModel(int $id): ActiveRecord
    {
        return Field::find()->with('project')->where([
            'id' => $id,
            'user_id' => Yii::$app->user->id,
        ])->one() ?? throw new NotFoundHttpException('The requested field does not exist or is not yours.');
    }
}
