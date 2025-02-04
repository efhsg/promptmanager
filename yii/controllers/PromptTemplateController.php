<?php

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
    /**
     * @var array
     */
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
        // Retrieve the permission map for the "promptTemplate" entity
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('promptTemplate');
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    // Allow direct access to index
                    [
                        'actions' => ['index'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    // For all other actions, use RBAC checks based on the permission map
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

    public function actionIndex(): string
    {
        $searchModel = new PromptTemplateSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, Yii::$app->user->id);
        return $this->render('index', compact('searchModel', 'dataProvider'));
    }

    /**
     * Displays a single PromptTemplate model.
     *
     * @param int $id
     * @return string
     * @throws NotFoundHttpException if the model cannot be found or is not owned by the current user.
     */
    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    /**
     * Creates a new PromptTemplate model.
     *
     * @return Response|string
     * @throws Exception
     */
    public function actionCreate(): Response|string
    {
        return $this->handleForm(new PromptTemplate());
    }

    /**
     * Updates an existing PromptTemplate model.
     *
     * @param int $id
     * @return Response|string
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionUpdate(int $id): Response|string
    {
        /** @var PromptTemplate $model */
        $model = $this->modelService->findModelById($id, PromptTemplate::class);
        return $this->handleForm($model);
    }

    /**
     * Handles the common form logic for create and update actions.
     *
     * @param PromptTemplate $model
     * @return Response|string
     * @throws Exception
     */
    private function handleForm(PromptTemplate $model): Response|string
    {
        if ($this->promptTemplateService->saveModel($model, Yii::$app->request->post())) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $userId = Yii::$app->user->id;
        $view   = $model->isNewRecord ? 'create' : 'update';

        return $this->render($view, [
            'model'            => $model,
            'projects'         => $this->projectService->fetchProjectsList($userId),
            'generalFieldsMap' => $this->fieldService->fetchFieldsMap($userId, null),
            'projectFieldsMap' => $this->fieldService->fetchFieldsMap($userId, $model->project_id ?: null),
        ]);
    }

    /**
     * Deletes an existing PromptTemplate model.
     *
     * @param int $id
     * @return Response|string
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
