<?php

/** @noinspection DuplicatedCode */

/** @noinspection PhpUnused */

namespace app\controllers;

use app\components\ProjectContext;
use app\models\Field;
use app\models\FieldOption;
use app\models\FieldSearch;
use app\models\Project;
use app\services\EntityPermissionService;
use app\services\FieldService;
use app\services\PathService;
use app\services\ProjectService;
use common\constants\FieldConstants;
use common\enums\LogCategory;
use UnexpectedValueException;
use Yii;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Yii2\Extensions\DynamicForm\Models\Model;

/**
 * Controller for managing Field entities: listing, creating, updating and
 * deleting fields and their options within a user's projects.
 */
class FieldController extends Controller
{
    private array $actionPermissionMap;

    public function __construct(
        $id,
        $module,
        private readonly ProjectService $projectService,
        private readonly FieldService $fieldService,
        private readonly PathService $pathService,
        private readonly EntityPermissionService $permissionService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('field');
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (in_array($action->id, ['path-list', 'path-preview'], true)) {
            Yii::$app->session->close();
        }

        return true;
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'path-list', 'path-preview'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'actions' => array_keys($this->actionPermissionMap),
                        'matchCallback' => function ($rule, $action) {
                            $callback = $this->permissionService->isModelBasedAction($action->id)
                                ? fn() => $this->findModel((int) Yii::$app->request->get('id'))
                                : null;
                            return $this->permissionService->hasActionPermission('field', $action->id, $callback);
                        },
                    ],
                ],
            ],
        ];
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

    public function actionIndex(): string
    {
        $searchModel = new FieldSearch();
        $projectContext = Yii::$app->projectContext;

        if ($projectContext->isNoProjectContext()) {
            $projectId = ProjectContext::NO_PROJECT_ID;
        } elseif ($projectContext->isAllProjectsContext()) {
            $projectId = null;
        } else {
            $projectId = $projectContext->getCurrentProject()?->id;
        }

        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            Yii::$app->user->id,
            $projectId
        );
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
        return $this->handleCreateOrUpdate(new Field(['user_id' => Yii::$app->user->id]), []);
    }

    private function handleCreateOrUpdate(Field $modelField, array $modelsFieldOption): Response|string
    {
        $postData = Yii::$app->request->post();
        if ($modelField->load($postData)) {
            if (isset($postData['FieldOption']) && is_array($postData['FieldOption'])) {
                $modelsFieldOption = Model::createMultiple(FieldOption::class, $modelsFieldOption);
                Model::loadMultiple($modelsFieldOption, $postData);
            } else {
                $modelsFieldOption = [];
            }

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
    public function actionUpdate(int $id): Response|string
    {
        /** @var Field $model */
        $model = $this->findModel($id);
        return $this->handleCreateOrUpdate($model, $model->fieldOptions);
    }

    public function actionPathList(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $projectId = (int) Yii::$app->request->get('projectId');
        $type = (string) Yii::$app->request->get('type', '');

        if ($projectId <= 0 || !in_array($type, FieldConstants::PATH_FIELD_TYPES, true)) {
            return ['success' => false, 'message' => 'Invalid request.'];
        }

        /** @var Project|null $project */
        $project = Project::find()->where([
            'id' => $projectId,
            'user_id' => Yii::$app->user->id,
        ])->one();

        if ($project === null || empty($project->root_directory)) {
            return ['success' => false, 'message' => 'The selected project has no root directory configured.'];
        }

        $pathMappings = Yii::$app->params['pathMappings'] ?? [];
        $effectiveRoot = $this->pathService->translatePath($project->root_directory, $pathMappings);

        if (!is_dir($effectiveRoot)) {
            return ['success' => false, 'message' => 'The configured root directory is not accessible.'];
        }

        $allowedExtensions = $project->getAllowedFileExtensions();
        $blacklistedDirectories = $project->getBlacklistedDirectories();

        try {
            $paths = $this->pathService->collectPaths(
                $effectiveRoot,
                $type === 'directory',
                $allowedExtensions,
                $blacklistedDirectories
            );
        } catch (UnexpectedValueException $e) {
            Yii::error($e->getMessage(), LogCategory::APPLICATION->value);
            return ['success' => false, 'message' => 'Unable to read the project root directory.'];
        }

        return [
            'success' => true,
            'root' => $project->root_directory,
            'paths' => $paths,
        ];
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionPathPreview(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        /** @var Field $field */
        $field = $this->findModel($id);

        if (!in_array(
            $field->type,
            FieldConstants::PATH_PREVIEWABLE_FIELD_TYPES,
            true
        ) || $field->project === null || empty($field->project->root_directory)) {
            return ['success' => false, 'message' => 'Preview unavailable for this field.'];
        }

        $path = trim((string) Yii::$app->request->get('path', ''));
        if ($path === '') {
            return ['success' => false, 'message' => 'Invalid file path.'];
        }

        $pathMappings = Yii::$app->params['pathMappings'] ?? [];
        $effectiveRoot = $this->pathService->translatePath($field->project->root_directory, $pathMappings);

        $absolutePath = $this->pathService->resolveRequestedPath(
            $effectiveRoot,
            $path,
            $field->project->getBlacklistedDirectories()
        );
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return ['success' => false, 'message' => 'File not accessible.'];
        }

        if (!$field->project->isFileExtensionAllowed(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            return ['success' => false, 'message' => 'File extension not allowed for this project.'];
        }

        $preview = @file_get_contents($absolutePath, false, null, 0, 100000);
        if ($preview === false) {
            return ['success' => false, 'message' => 'Unable to read file content.'];
        }

        return [
            'success' => true,
            'preview' => $preview,
        ];
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
    public function actionRenumber(int $id): Response
    {
        /** @var Field $model */
        $model = $this->findModel($id);

        if ($this->fieldService->renumberFieldOptions($model)) {
            Yii::$app->session->setFlash('success', 'Field options have been renumbered.');
        } else {
            Yii::$app->session->setFlash('error', 'Unable to renumber field options. Please try again.');
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }
}
