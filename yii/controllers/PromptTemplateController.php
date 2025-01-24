<?php /** @noinspection DuplicatedCode */

namespace app\controllers;

use app\models\PromptTemplate;
use app\models\PromptTemplateSearch;
use app\services\FieldService;
use app\services\ModelService;
use app\services\ProjectService;
use app\services\PromptTemplateService;
use Yii;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * PromptTemplateController implements the CRUD actions for PromptTemplate model.
 */
class PromptTemplateController extends Controller
{

    private ModelService $modelService;
    private FieldService $fieldService;
    private ProjectService $projectService;
    private PromptTemplateService $promptTemplateService;

    public function __construct(
        $id,
        $module,
        ModelService $modelService,
        FieldService $fieldService,
        ProjectService $projectService,
        PromptTemplateService $promptTemplateService,
        $config = []
    )
    {
        $this->modelService = $modelService;
        $this->fieldService = $fieldService;
        $this->projectService = $projectService;
        $this->promptTemplateService = $promptTemplateService;
        parent::__construct($id, $module, $config);
    }

    protected function accessRules(): array
    {
        return [
            [
                'actions' => ['index', 'view', 'create', 'update', 'delete', 'delete-confirm'],
                'allow' => true,
                'roles' => ['@'],
            ],
        ];
    }

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => $this->accessRules(),
            ],
        ]);
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
        $model = $this->modelService->findModelById($id, PromptTemplate::class);
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * @throws Exception
     */
    public function actionCreate(): Response|string
    {
        return $this->handleForm(new PromptTemplate());
    }

    /**
     * @throws NotFoundHttpException|Exception
     */
    public function actionUpdate(int $id): Response|string
    {
        /** @var PromptTemplate $model */
        $model = $this->modelService->findModelById($id, PromptTemplate::class);
        return $this->handleForm($model);
    }

    /**
     * @throws Exception
     */
    private function handleForm(PromptTemplate $model): Response|string
    {
        if ($this->promptTemplateService->saveModel($model, Yii::$app->request->post())) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $userId = Yii::$app->user->id;
        $view = $model->isNewRecord ? 'create' : 'update';

        return $this->render($view, [
            'model' => $model,
            'projects' => $this->projectService->fetchProjectsList($userId),
            'generalFieldsMap' => $this->fieldService->fetchFieldsMap($userId, null),
            'projectFieldsMap' => $this->fieldService->fetchFieldsMap($userId, $model->project_id),
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionDelete(int $id): Response|string
    {
        /** @var PromptTemplate $model */
        $model = $this->modelService->findModelById($id, PromptTemplate::class);

        if (!Yii::$app->request->post('confirm')) {
            return $this->render('delete-confirm', [
                'model' => $model,
            ]);
        }

        if ($this->modelService->deleteModelSafely($model)) {
            Yii::$app->session->setFlash('success', "Template '$model->name' deleted successfully.");
        } else {
            Yii::$app->session->setFlash(
                'error',
                'Unable to delete the template. Please try again later.'
            );
        }

        return $this->redirect(['index']);
    }

}
