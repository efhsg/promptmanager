<?php

namespace app\controllers;

use app\models\Field;
use app\models\FieldOption;
use app\models\FieldSearch;
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
    private array $actionPermissionMap = [
        'create' => 'createField',
        'view' => 'viewField',
        'update' => 'updateField',
        'delete' => 'deleteField',
    ];

    public function __construct(
        $id,
        $module,
        private readonly ProjectService $projectService,
        private readonly FieldService $fieldService,
        $config = []
    )
    {
        parent::__construct($id, $module, $config);
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
                        'matchCallback' => fn($rule, $action) => $this->hasPermission($action->id),
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws NotFoundHttpException
     */
    private function hasPermission(string $actionName): bool
    {
        if (!isset($this->actionPermissionMap[$actionName])) {
            return false;
        }

        $permission = $this->actionPermissionMap[$actionName];
        $model = in_array($actionName, ['view', 'update', 'delete'])
            ? $this->findModel(Yii::$app->request->get('id'))
            : null;

        return Yii::$app->user->can($permission, $model ? ['model' => $model] : []);
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

    public function actionCreate(): Response|array|string
    {
        $modelField = new Field(['user_id' => Yii::$app->user->id]);
        $modelsFieldOption = [new FieldOption()];

        return $this->handleCreateOrUpdate($modelField, $modelsFieldOption);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionUpdate(int $id): Response|string
    {
        /** @var Field $modelField */
        $modelField = $this->findModel($id);
        $modelsFieldOption = $modelField->fieldOptions;

        return $this->handleCreateOrUpdate($modelField, $modelsFieldOption);
    }

    private function handleCreateOrUpdate(Field $modelField, array $modelsFieldOption): Response|array|string
    {
        if ($modelField->load(Yii::$app->request->post())) {
            $modelsFieldOption = Model::createMultiple(FieldOption::class, $modelsFieldOption);
            Model::loadMultiple($modelsFieldOption, Yii::$app->request->post());

            if ($this->fieldService->saveFieldWithOptions($modelField, $modelsFieldOption)) {
                return $this->redirect(['view', 'id' => $modelField->id]);
            }
        }

        return $this->render($modelField->isNewRecord ? 'create' : 'update', [
            'modelField' => $modelField,
            'modelsFieldOption' => empty($modelsFieldOption) ? [new FieldOption()] : $modelsFieldOption,
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
        Yii::$app->session->setFlash('error', 'Unable to delete the context. Please try again later.');
        return $this->redirect(['index']);
    }

    /**
     * @throws NotFoundHttpException
     */
    protected function findModel(int $id): ActiveRecord
    {
        return Field::find()->with('project')->where(['id' => $id])->one()
            ?? throw new NotFoundHttpException('The requested page does not exist.');
    }
}
