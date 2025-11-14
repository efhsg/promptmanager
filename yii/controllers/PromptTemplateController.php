<?php /** @noinspection PhpUnused */

namespace app\controllers;

use app\models\PromptTemplate;
use app\models\PromptTemplateSearch;
use app\services\EntityPermissionService;
use app\services\FieldService;
use app\services\ModelService;
use app\services\ProjectService;
use app\services\PromptTemplateService;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PromptTemplateController extends Controller
{
    private array $actionPermissionMap;

    public function __construct(
        $id,
        $module,
        private readonly ProjectService $projectService,
        private readonly FieldService $fieldService,
        private readonly ModelService $modelService,
        private readonly PromptTemplateService $promptTemplateService,
        private readonly EntityPermissionService $permissionService,
        $config = []
    )
    {
        parent::__construct($id, $module, $config);
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('promptTemplate');
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
                            $callback = $this->permissionService->isModelBasedAction($action->id)
                                ? fn() => $this->findModel((int)Yii::$app->request->get('id'))
                                : null;
                            return $this->permissionService->hasActionPermission('promptTemplate', $action->id, $callback);
                        },
                    ],
                ],
            ],
        ];
    }

    public function actionIndex(): string|array
    {
        $searchModel = new PromptTemplateSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            Yii::$app->user->id,
            (Yii::$app->projectContext)->getCurrentProject()?->id
        );
    
        if (Yii::$app->request->get('debug') === 'json') {
            Yii::$app->response->format = Response::FORMAT_JSON;
            $models = $dataProvider->getModels();
            $pagination = $dataProvider->getPagination();
            return [
                'models' => $models,
                'count' => $dataProvider->getCount(),
                'totalCount' => $dataProvider->getTotalCount(),
                'pagination' => $pagination ? [
                    'page' => $pagination->getPage(),
                    'pageSize' => $pagination->getPageSize(),
                    'pageCount' => $pagination->getPageCount(),
                ] : null,
            ];
        }

        return $this->render('index', compact('searchModel', 'dataProvider'));
    }

    /**
     * Converts the stored template back to the user-friendly markup before display.
     *
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): string
    {
        /** @var PromptTemplate $model */
        $model = $this->findModel($id);
        $userId = Yii::$app->user->id;
        $generalFieldsMap = $this->fieldService->fetchFieldsMap($userId, null);
        $projectFieldsMap = $this->fieldService->fetchFieldsMap($userId, $model->project_id ?: null);
        $fieldsMapping = array_merge($generalFieldsMap, $projectFieldsMap);
        $model->template_body = $this->promptTemplateService->convertPlaceholdersToLabels(
            $model->template_body,
            $fieldsMapping
        );

        return $this->render('view', [
            'model' => $model,
            'isDeltaFormat' => true
        ]);
    }

    /**
     * @throws Exception
     */
    public function actionCreate(): Response|string
    {
        $model = new PromptTemplate();
        $model->template_body = '{"ops":[{"insert":"\n"}]}';
        return $this->handleForm($model);
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
     * Renders the create/update form.
     * For update requests (non-POST), converts the stored template back to its original markup.
     *
     * @throws Exception
     */
    private function handleForm(PromptTemplate $model): Response|string
    {
        $userId = Yii::$app->user->id;
        $generalFieldsMap = $this->fieldService->fetchFieldsMap($userId, null);
        $projectFieldsMap = $this->fieldService->fetchFieldsMap($userId, $model->project_id ?: null);
        $fieldsMapping = array_merge($generalFieldsMap, $projectFieldsMap);
        $view = $model->isNewRecord ? 'create' : 'update';
        if (!Yii::$app->request->isPost) {
            if ($model->isNewRecord && empty($model->template_body)) {
                $model->template_body = '{"ops":[{"insert":"\n"}]}';
            } elseif (!$model->isNewRecord) {
                $model->template_body = $this->promptTemplateService->convertPlaceholdersToLabels(
                    $model->template_body,
                    $fieldsMapping
                );
            }
            return $this->render($view, [
                'model' => $model,
                'projects' => $this->projectService->fetchProjectsList($userId),
                'generalFieldsMap' => $generalFieldsMap,
                'projectFieldsMap' => $projectFieldsMap,
            ]);
        }
        $postData = Yii::$app->request->post();
        if ($this->promptTemplateService->saveTemplateWithFields($model, $postData, $fieldsMapping)) {
            return $this->redirect(['view', 'id' => $model->id]);
        }
        return $this->render($view, [
            'model' => $model,
            'projects' => $this->projectService->fetchProjectsList($userId),
            'generalFieldsMap' => $generalFieldsMap,
            'projectFieldsMap' => $projectFieldsMap,
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
            return $this->render('delete-confirm', ['model' => $model]);
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

    /**
     * Finds the PromptTemplate model based on its primary key value and verifies that it belongs
     * to the current user (via its associated project).
     *
     * @param int $id
     * @return ActiveRecord
     * @throws NotFoundHttpException if the model cannot be found or is not owned by the user.
     */
    protected function findModel(int $id): ActiveRecord
    {
        return PromptTemplate::find()
            ->joinWith('project')
            ->where([
                'prompt_template.id' => $id,
                'project.user_id' => Yii::$app->user->id,
            ])
            ->one() ?? throw new NotFoundHttpException('The requested prompt template does not exist or is not yours.');
    }
}
