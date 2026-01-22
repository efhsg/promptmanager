<?php

/** @noinspection DuplicatedCode */

/** @noinspection PhpUnused */

namespace app\controllers;

use app\components\ProjectContext;
use app\models\PromptInstance;
use app\models\PromptInstanceForm;
use app\models\PromptInstanceSearch;
use app\services\ContextService;
use app\services\CopyFormatConverter;
use app\services\EntityPermissionService;
use app\services\FileFieldProcessor;
use app\services\ModelService;
use app\services\PromptGenerationService;
use app\services\PromptInstanceService;
use app\services\PromptTemplateService;
use app\services\PromptTransformationService;
use common\constants\FieldConstants;
use common\enums\CopyType;
use Yii;
use yii\base\InvalidArgumentException;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PromptInstanceController extends Controller
{
    /**
     * @var array
     */
    private array $actionPermissionMap;

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
        private readonly FileFieldProcessor $fileFieldProcessor,
        PromptGenerationService $promptGenerationService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('promptInstance');
        $this->promptGenerationService = $promptGenerationService;
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
                        'actions' => array_merge(
                            array_keys($this->actionPermissionMap),
                            ['generate-prompt-form', 'generate-final-prompt', 'save-final-prompt']
                        ),
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
                                    (int) Yii::$app->request->get('id'),
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
            $fields[(string) $field->id] = $this->getFieldData($field);
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
            'render_label' => $field->render_label,
        ];
        if (in_array($field->type, FieldConstants::PATH_FIELD_TYPES, true)) {
            $fieldData['project_id'] = $field->project_id;
        }
        if (in_array($field->type, FieldConstants::OPTION_FIELD_TYPES)) {
            $options = [];
            $default = [];
            $converter = new CopyFormatConverter();
            foreach ($field->fieldOptions as $option) {
                $deltaValue = $option->value;
                $plainLabel = $option->label
                    ? $this->convertDeltaStringToText($option->label, $converter)
                    : $this->convertDeltaStringToText($option->value, $converter);
                $options[$deltaValue] = $plainLabel;
                if ($option->selected_by_default) {
                    $default[] = $deltaValue;
                }
            }
            $fieldData['options'] = $options;
            if ($field->type === 'multi-select') {
                $fieldData['default'] = $default;
            } elseif ($field->type === 'select-invert') {
                $fieldData['default'] = count($default) ? reset($default) : null;
                $allOptions = array_keys($options);
                $fieldData['invert'] = $fieldData['default'] !== null
                    ? implode(',', array_values(array_diff($allOptions, [$fieldData['default']])))
                    : implode(',', $allOptions);
            } else {
                $fieldData['default'] = count($default) ? reset($default) : null;
            }
        } else {
            $fieldData['default'] = $this->promptTransformationService->transformForAIModel($field->content);
        }
        return $fieldData;
    }

    private function convertDeltaStringToText(mixed $value, CopyFormatConverter $converter): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        try {
            $decoded = Json::decode($trimmed);
        } catch (InvalidArgumentException) {
            return $value;
        }

        if (!is_array($decoded)) {
            return $value;
        }

        return $converter->convertFromQuillDelta($trimmed, CopyType::TEXT);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionGenerateFinalPrompt(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $userId = Yii::$app->user->id;
        $templateId = (int) Yii::$app->request->post('template_id');
        $contextIds = Yii::$app->request->post('context_ids') ?? [];
        $fieldValues = Yii::$app->request->post('PromptInstanceForm')['fields'] ?? [];

        $fieldValues = $this->promptInstanceService->parseRawFieldValues($fieldValues);

        $template = $this->promptTemplateService->getTemplateById($templateId, $userId);

        $fieldValues = $this->fileFieldProcessor->processFileFields($template, $fieldValues);

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
            $delta = Json::decode($finalPrompt);
        } catch (InvalidArgumentException) {
            return ['success' => false];
        }

        if (!isset($delta['ops']) || !is_array($delta['ops'])) {
            return ['success' => false];
        }

        $label = Yii::$app->request->post('label', '');
        $label = is_string($label) ? trim($label) : '';
        if ($label === '') {
            return ['success' => false];
        }

        $model = new PromptInstance([
            'template_id' => (int) Yii::$app->request->post('template_id'),
            'label' => $label,
            'final_prompt' => $finalPrompt,
        ]);

        if (!$model->save()) {
            return ['success' => false];
        }

        return ['success' => true, 'redirectUrl' => Url::to(['view', 'id' => $model->id])];
    }
}
