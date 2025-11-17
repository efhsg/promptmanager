<?php

namespace app\controllers;

use app\models\Field;
use app\models\FieldOption;
use app\models\FieldSearch;
use app\models\Project;
use app\services\EntityPermissionService;
use app\services\FieldService;
use app\services\ProjectService;
use common\constants\FieldConstants;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
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
    private const PATH_LIST_MAX_DEPTH = 10;

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
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            Yii::$app->user->id,
            (Yii::$app->projectContext)->getCurrentProject()?->id
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

        $projectId = (int)Yii::$app->request->get('projectId');
        $type = (string)Yii::$app->request->get('type', '');

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

        if (!is_dir($project->root_directory)) {
            return ['success' => false, 'message' => 'The configured root directory is not accessible.'];
        }

        try {
            $paths = $this->collectPaths($project->root_directory, $type === 'directory');
        } catch (UnexpectedValueException $e) {
            Yii::error($e->getMessage(), __METHOD__);
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

        if (!in_array($field->type, FieldConstants::PATH_PREVIEWABLE_FIELD_TYPES, true) || $field->project === null || empty($field->project->root_directory)) {
            return ['success' => false, 'message' => 'Preview unavailable for this field.'];
        }

        $path = trim((string)Yii::$app->request->get('path', ''));
        if ($path === '') {
            return ['success' => false, 'message' => 'Invalid file path.'];
        }

        $absolutePath = $this->resolveRequestedPath($field->project->root_directory, $path);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return ['success' => false, 'message' => 'File not accessible.'];
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

    private function collectPaths(string $rootDirectory, bool $directoriesOnly): array
    {
        $resolvedRoot = $this->resolveRootDirectory($rootDirectory);
        $normalizedBase = str_replace('\\', '/', $resolvedRoot);
        $paths = $directoriesOnly ? ['/'] : [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolvedRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $iterator->setMaxDepth(self::PATH_LIST_MAX_DEPTH);

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($directoriesOnly && !$item->isDir()) {
                continue;
            }
            if (!$directoriesOnly && !$item->isFile()) {
                continue;
            }

            $relative = $this->makeRelativePath($normalizedBase, $item->getPathname());
            if ($relative === '/') {
                continue;
            }

            $paths[] = $relative;
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }

    private function resolveRootDirectory(string $rootDirectory): string
    {
        $resolved = realpath($rootDirectory);
        if ($resolved === false) {
            $resolved = $rootDirectory;
        }

        return rtrim($resolved, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }

    private function makeRelativePath(string $normalizedBase, string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $relative = ltrim(substr($normalizedPath, strlen($normalizedBase)), '/');

        return $relative === '' ? '/' : $relative;
    }

    private function resolveRequestedPath(string $rootDirectory, string $relativePath): ?string
    {
        $base = $this->resolveRootDirectory($rootDirectory);
        $normalizedBase = str_replace('\\', '/', $base);
        $normalizedRelative = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        $candidate = $base . DIRECTORY_SEPARATOR . $normalizedRelative;
        $realPath = realpath($candidate) ?: $candidate;
        $normalizedCandidate = str_replace('\\', '/', $realPath);

        if (!str_starts_with($normalizedCandidate, $normalizedBase . '/') && $normalizedCandidate !== $normalizedBase) {
            return null;
        }

        return $normalizedCandidate;
    }
}
