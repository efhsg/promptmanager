<?php
/** @noinspection PhpUnused */

namespace app\controllers;

use app\models\PromptInstance;
use app\models\PromptInstanceForm;
use app\models\PromptInstanceSearch;
use app\services\ContextService;
use app\services\EntityPermissionService;
use app\services\ModelService;
use app\services\PathService;
use app\services\PromptGenerationService;
use app\services\PromptInstanceService;
use app\services\PromptTemplateService;
use app\services\PromptTransformationService;
use app\services\CopyFormatConverter;
use common\constants\FieldConstants;
use common\enums\CopyType;
use Yii;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;


class PromptInstanceController extends Controller
{

    private const FORMAT_HTML = 'displayHtml';
    private const FORMAT_MARKDOWN = 'displayText';
    private const FORMAT_DELTA = 'displayDelta';

    /**
     * @var array
     */
    private array $actionPermissionMap;

    private readonly PathService $pathService;
    private PromptGenerationService $promptGenerationService;

    public function __construct(
        $id,
        $module,
        private readonly ModelService $modelService,
        private readonly PromptInstanceService $promptInstanceService,
        private readonly PromptTemplateService $promptTemplateService,
        private readonly EntityPermissionService $permissionService,
        private readonly ContextService $contextService,
        private readonly PromptTransformationService $promptTransformationService,
        PathService $pathService,
        PromptGenerationService $promptGenerationService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('promptInstance');
        $this->promptGenerationService = $promptGenerationService;
        $this->pathService = $pathService;
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
                            if (in_array(
                                $action->id,
                                ['generate-prompt-form', 'generate-final-prompt', 'save-final-prompt']
                            )) {
                                $callback = function () {
                                    $templateId = Yii::$app->request->post('template_id');
                                    if ($templateId) {
                                        return $this->promptTemplateService->getTemplateById(
                                            $templateId,
                                            Yii::$app->user->id
                                        );
                                    }
                                    return null;
                                };
                                return $this->permissionService->hasActionPermission(
                                    'promptTemplate',
                                    'view',
                                    $callback
                                );
                            } elseif ($this->permissionService->isModelBasedAction($action->id)) {
                                $callback = fn() => $this->promptInstanceService->findModelWithOwner(
                                    (int)Yii::$app->request->get('id'),
                                    Yii::$app->user->id
                                );
                            } else {
                                $callback = null;
                            }
                            return $this->permissionService->hasActionPermission(
                                'promptInstance',
                                $action->id,
                                $callback
                            );
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists all PromptInstance models.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        $searchModel = new PromptInstanceSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            Yii::$app->user->id,
            (Yii::$app->projectContext)->getCurrentProject()?->id
        );
        return $this->render('index', compact('searchModel', 'dataProvider'));
    }

    /**
     * Displays a single PromptInstance model.
     *
     * @param int $id
     * @return string
     * @throws NotFoundHttpException if the model is not found or not owned by the user.
     */
    public function actionView(int $id): string
    {
        return $this->render(
            'view',
            ['model' => $this->promptInstanceService->findModelWithOwner($id, Yii::$app->user->id)]
        );
    }

    /**
     * Creates a new PromptInstance using PromptInstanceForm.
     *
     * @return Response|string
     * @throws Exception
     */
    public function actionCreate(): Response|string
    {
        $formModel = new PromptInstanceForm();
        if ($formModel->load(Yii::$app->request->post()) && $formModel->validate()) {
            if ($formModel->save()) {
                /** @var PromptInstance|null $newInstance */
                $newInstance = PromptInstance::find()
                    ->where([
                        'template_id' => $formModel->template_id,
                        'final_prompt' => $formModel->final_prompt,
                    ])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();
                if ($newInstance !== null) {
                    return $this->redirect(['view', 'id' => $newInstance->id]);
                }
            }
        }
        $userId = Yii::$app->user->id;
        $projectId = (Yii::$app->projectContext)->getCurrentProject()?->id;

        return $this->render('create', [
            'model' => $formModel,
            'templates' => $this->promptTemplateService->getTemplatesByUser($userId, $projectId),
            'templatesDescription' => $this->promptTemplateService->getTemplatesDescriptionByUser($userId, $projectId),
            'contexts' => $this->contextService->fetchProjectContexts($userId, $projectId),
            'contextsContent' => $this->contextService->fetchContextsContent($userId),
            'defaultContextIds' => $this->contextService->fetchDefaultContextIds($userId, $projectId),
        ]);
    }

    /**
     * Updates an existing PromptInstance model.
     *
     * @param int $id
     * @return Response|string
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionUpdate(int $id): Response|string
    {
        /** @var PromptInstance $model */
        $model = $this->modelService->findModelById($id, PromptInstance::class);
        return $this->handleForm($model);
    }

    /**
     * Handles the common form logic for update actions.
     *
     * @throws Exception
     */
    private function handleForm(PromptInstance $model): Response|string
    {
        if ($this->promptInstanceService->saveModel($model, Yii::$app->request->post())) {
            return $this->redirect(['view', 'id' => $model->id]);
        }
        $view = $model->isNewRecord ? 'create' : 'update';
        $data = ['model' => $model];
        if ($model->isNewRecord) {
            $userId = Yii::$app->user->id;
            $data['templates'] = $this->promptTemplateService->getTemplatesByUser($userId);
            $data['templatesDescription'] = $this->promptTemplateService->getTemplatesDescriptionByUser($userId);
            $data['contexts'] = $this->contextService->fetchContexts($userId);
            $data['contextsContent'] = $this->contextService->fetchContextsContent($userId);
        }
        return $this->render($view, $data);
    }

    /**
     * Deletes an existing PromptInstance model.
     *
     * @param int $id
     * @return Response|string
     * @throws NotFoundHttpException
     */
    public function actionDelete(int $id): Response|string
    {
        /** @var PromptInstance $model */
        $model = $this->modelService->findModelById($id, PromptInstance::class);
        if (!Yii::$app->request->post('confirm')) {
            return $this->render('delete-confirm', ['model' => $model]);
        }
        if ($this->modelService->deleteModelSafely($model)) {
            Yii::$app->session->setFlash('success', "Prompt instance deleted successfully.");
        } else {
            Yii::$app->session->setFlash('error', 'Unable to delete the prompt instance. Please try again later.');
        }
        return $this->redirect(['index']);
    }

    /**
     * Retrieves the selected prompt template's content via AJAX.
     *
     * @param int $template_id
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionGetTemplate(int $template_id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $template = $this->promptTemplateService->getTemplateById($template_id, Yii::$app->user->id);
        if (!$template) {
            throw new NotFoundHttpException("Template not found or access denied.");
        }
        return ['template_body' => $template->template_body];
    }

    public function actionGeneratePromptForm(): string
    {
        $template = $this->promptTemplateService->getTemplateById(
            Yii::$app->request->post('template_id'),
            Yii::$app->user->id
        );
        $templateBody = $template->template_body;
        $fields = [];
        foreach ($template->fields as $field) {
            $fields[(string)$field->id] = $this->getFieldData($field);
        }
        return $this->renderAjax('_template_form', [
            'templateBody' => $templateBody,
            'fields' => $fields,
        ]);
    }

    private function getFieldData($field): array
    {
        $fieldData = [
            'id' => $field->id,
            'type' => $field->type,
            'label' => $field->label,
        ];
        if (in_array($field->type, FieldConstants::PATH_FIELD_TYPES, true)) {
            $fieldData['project_id'] = $field->project_id;
        }
        if (in_array($field->type, FieldConstants::OPTION_FIELD_TYPES)) {
            $options = [];
            $default = [];
            foreach ($field->fieldOptions as $option) {
                $options[$option->value] = $option->label ?: $option->value;
                if ($option->selected_by_default) {
                    $default[] = $option->value;
                }
            }
            $fieldData['options'] = $options;
            if ($field->type === 'multi-select') {
                $fieldData['default'] = $default;
            } elseif ($field->type === 'select-invert') {
                $fieldData['default'] = count($default) ? reset($default) : null;
                $allOptions = array_keys($options);
                $fieldData['invert'] = $fieldData['default'] !== null
                    ? implode(', ', array_values(array_diff($allOptions, [$fieldData['default']])))
                    : implode(', ', $allOptions);
            } else {
                $fieldData['default'] = count($default) ? reset($default) : null;
            }
        } else {
            $fieldData['default'] = $this->promptTransformationService->transformForAIModel($field->content);
        }
        return $fieldData;
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionGenerateFinalPrompt(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $userId = Yii::$app->user->id;
        $templateId = (int)Yii::$app->request->post('template_id');
        $contextIds = Yii::$app->request->post('context_ids') ?? [];
        $fieldValues = Yii::$app->request->post('PromptInstanceForm')['fields'] ?? [];

        $rawPost = file_get_contents('php://input');
        if ($rawPost !== false && $rawPost !== '') {
            parse_str($rawPost, $rawData);
            if (isset($rawData['PromptInstanceForm']['fields']) && is_array($rawData['PromptInstanceForm']['fields'])) {
                $params = [];
                foreach (explode('&', $rawPost) as $param) {
                    if (strpos($param, 'PromptInstanceForm%5Bfields%5D') !== 0) {
                        continue;
                    }
                    $parts = explode('=', $param, 2);
                    if (count($parts) !== 2) {
                        continue;
                    }
                    $key = urldecode($parts[0]);
                    $value = urldecode($parts[1]);
                    if (preg_match('/PromptInstanceForm\[fields]\[(\d+)](\[])?/', $key, $matches)) {
                        $fieldId = $matches[1];
                        $isArrayField = isset($matches[2]) && $matches[2] === '[]';
                        if ($isArrayField) {
                            $params[$fieldId][] = $value;
                        } elseif (!isset($params[$fieldId])) {
                            $params[$fieldId] = $value;
                        }
                    }
                }
                foreach ($params as $fid => $val) {
                    // Preserve multi-value submissions (e.g. multi-select) already parsed by Yii.
                    if (isset($fieldValues[$fid]) && is_array($fieldValues[$fid])) {
                        continue;
                    }
                    $fieldValues[$fid] = $val;
                }
            }
        }

        $template = $this->promptTemplateService->getTemplateById($templateId, $userId);

        if ($template && $template->project && !empty($template->project->root_directory)) {
            $blacklistedDirectories = $template->project->getBlacklistedDirectories();
            foreach ($template->fields as $field) {
                if ($field->type !== 'file') {
                    continue;
                }

                $pathToUse = $fieldValues[$field->id] ?? $field->content;
                if (empty($pathToUse)) {
                    continue;
                }

                $absolutePath = $this->pathService->resolveRequestedPath(
                    $template->project->root_directory,
                    $pathToUse,
                    $blacklistedDirectories
                );
                if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
                    continue;
                }

                if (!$template->project->isFileExtensionAllowed(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
                    continue;
                }

                $fileContent = @file_get_contents($absolutePath);
                if ($fileContent === false) {
                    continue;
                }

                $isCode = $this->promptTransformationService->detectCode($fileContent)
                    || in_array(
                        strtolower((string)pathinfo($absolutePath, PATHINFO_EXTENSION)),
                        ['php', 'js', 'ts', 'json', 'css', 'html', 'htm', 'xml', 'md', 'yaml', 'yml', 'sh', 'bash', 'zsh', 'py'],
                        true
                    );

                if ($isCode) {
                    $fieldValues[$field->id] = json_encode([
                        'ops' => [
                            [
                                'insert' => rtrim($fileContent) . "\n",
                                'attributes' => ['code-block' => true],
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } else {
                    $fieldValues[$field->id] = $fileContent;
                }
            }
        }

        $deltaJson = $this->promptGenerationService->generateFinalPrompt(
            $templateId,
            $this->contextService->fetchContextsContentById($userId, $contextIds),
            $fieldValues,
            $userId
        );

        $copyFormat = CopyType::MD;
        $copyContent = '';
        if ($template && $template->project) {
            $copyFormat = $template->project->getPromptInstanceCopyFormatEnum();
            $copyConverter = new CopyFormatConverter();
            $copyContent = $copyConverter->convertFromQuillDelta($deltaJson, $copyFormat);
        }

        return [
            'displayDelta' => $deltaJson,
            'copyContent' => $copyContent,
            'copyFormat' => $copyFormat->value,
        ];
    }

    /**
     * Saves a new PromptInstance using the generated final prompt.
     *
     * @return array
     * @throws Exception
     */
    public function actionSaveFinalPrompt(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $finalPrompt = Yii::$app->request->post('prompt');
        if ($finalPrompt === null) {
            return ['success' => false];
        }

        try {
            $delta = Json::decode($finalPrompt, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException|\yii\base\InvalidArgumentException $e) {
            return ['success' => false];
        }

        if (!isset($delta['ops']) || !is_array($delta['ops'])) {
            return ['success' => false];
        }

        $model = new PromptInstance([
            'template_id' => (int)Yii::$app->request->post('template_id'),
            'final_prompt' => $finalPrompt,
        ]);

        if (!$model->save()) {
            return ['success' => false];
        }

        return ['success' => true, 'redirectUrl' => Url::to(['view', 'id' => $model->id])];
    }
}
