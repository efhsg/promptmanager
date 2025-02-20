<?php

namespace app\controllers;

use app\models\Field;
use app\models\PromptInstance;
use app\models\PromptInstanceForm;
use app\models\PromptInstanceSearch;
use app\services\ContextService;
use app\services\EntityPermissionService;
use app\services\ModelService;
use app\services\PromptInstanceService;
use app\services\PromptTemplateService;
use app\services\PromptTransformationService;
use Yii;
use yii\db\Exception;
use yii\filters\AccessControl;
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

    public function __construct(
        $id,
        $module,
        private readonly ModelService $modelService,
        private readonly PromptInstanceService $promptInstanceService,
        private readonly PromptTemplateService $promptTemplateService,
        private readonly EntityPermissionService $permissionService,
        private readonly ContextService $contextService,
        private readonly PromptTransformationService $promptTransformationService,
        $config = []
    )
    {
        parent::__construct($id, $module, $config);
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('promptInstance');
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
                            if (in_array($action->id, ['generate-prompt-form', 'generate-final-prompt', 'save-final-prompt'])) {
                                $callback = function () {
                                    $templateId = Yii::$app->request->post('template_id');
                                    if ($templateId) {
                                        return $this->promptTemplateService->getTemplateById($templateId, Yii::$app->user->id);
                                    }
                                    return null;
                                };
                                return $this->permissionService->hasActionPermission('promptTemplate', 'view', $callback);
                            } elseif ($this->permissionService->isModelBasedAction($action->id)) {
                                $callback = fn() => $this->promptInstanceService->findModelWithOwner((int)Yii::$app->request->get('id'), Yii::$app->user->id);
                            } else {
                                $callback = null;
                            }
                            return $this->permissionService->hasActionPermission('promptInstance', $action->id, $callback);
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
        return $this->render('view', ['model' => $this->promptInstanceService->findModelWithOwner($id, Yii::$app->user->id)]);
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
        $templates = $this->promptTemplateService->getTemplatesByUser($userId);
        $templatesDescription = $this->promptTemplateService->getTemplatesDescriptionByUser($userId);
        $contexts = $this->contextService->fetchContexts($userId);
        $contextsContent = $this->contextService->fetchContextsContent($userId);
        return $this->render('create', [
            'model' => $formModel,
            'templates' => $templates,
            'templatesDescription' => $templatesDescription,
            'contexts' => $contexts,
            'contextsContent' => $contextsContent,
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
        $templateId = Yii::$app->request->post('template_id');
        $template = $this->promptTemplateService->getTemplateById($templateId, Yii::$app->user->id);
        $templateBody = $template->template_body;
        $fields = [];
        foreach ($template->fields as $field) {
            $fieldKey = (string)$field->id;
            $fieldData = [
                'type' => $field->type,
                'label' => $field->label,
            ];
            if (in_array($field->type, ['select', 'multi-select'])) {
                $options = [];
                $default = [];
                foreach ($field->fieldOptions as $option) {
                    $options[$option->value] = $option->label ?: $option->value;
                    if ($option->selected_by_default) {
                        $default[] = $option->value;
                    }
                }
                $fieldData['options'] = $options;
                $fieldData['default'] = $field->type === 'multi-select'
                    ? $default
                    : (count($default) ? reset($default) : null);
            } elseif ($field->type === 'text') {
                $fieldData['default'] = $field->content;
            } else {
                $fieldData['default'] = '';
            }
            $fields[$fieldKey] = $fieldData;
        }
        return $this->renderPartial('_template_form', [
            'templateBody' => $templateBody,
            'fields' => $fields,
        ]);
    }

    /**
     * Generates the final prompt based on submitted data.
     * This implementation replaces each placeholder (e.g. {{1}}) in the original template
     * with the corresponding value from POST data (under PromptInstanceForm[fields]),
     * and prepends the selected contexts' content to the generated prompt.
     *
     * @return array
     * @throws NotFoundHttpException if the template cannot be found.
     */
    public function actionGenerateFinalPrompt(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $templateId = Yii::$app->request->post('template_id');
        $selectedContextIds = Yii::$app->request->post('context_ids') ?? [];
        if (!$templateId) {
            throw new NotFoundHttpException("Template ID not provided.");
        }
        $template = $this->promptTemplateService->getTemplateById($templateId, Yii::$app->user->id);
        if (!$template) {
            throw new NotFoundHttpException("Template not found or access denied.");
        }
        $templateBody = $template->template_body;
        $postData = Yii::$app->request->post('PromptInstanceForm');
        $fieldsValues = $postData['fields'] ?? [];
        $fieldIds = array_keys($fieldsValues);
        /** @var Field[] $fields */
        $fields = Field::find()->where(['id' => $fieldIds])->indexBy('id')->all();
        $displayPrompt = preg_replace_callback('/(?:GEN:)?\{\{(\d+)}}/', function ($matches) use ($fieldsValues, $fields): string {
            $fieldKey = $matches[1];
            if (isset($fieldsValues[$fieldKey])) {
                $value = $fieldsValues[$fieldKey];
                if (isset($fields[$fieldKey]) && $fields[$fieldKey]->type === 'code') {
                    return $this->promptTransformationService->wrapCode(is_array($value) ? implode(', ', $value) : $value);
                }
                $valueStr = is_array($value) ? implode(', ', $value) : $value;
                return $this->promptTransformationService->detectCode($valueStr)
                    ? $this->promptTransformationService->wrapCode($valueStr)
                    : $valueStr;
            }
            return $matches[0];
        }, $templateBody);
        $allContextsContent = $this->contextService->fetchContextsContent(Yii::$app->user->id);
        $contextsArr = [];
        foreach ($selectedContextIds as $id) {
            if (!empty($allContextsContent[$id])) {
                $contextsArr[] = $allContextsContent[$id];
            }
        }
        $contextsText = !empty($contextsArr) ? implode("\n\n", $contextsArr) : '';
        $displayPrompt = $contextsText ? $contextsText . "\n\n" . $displayPrompt : $displayPrompt;
        $aiPrompt = $this->promptTransformationService->transformForAIModel($displayPrompt);
        return [
            'displayPrompt' => $displayPrompt,
            'aiPrompt' => $aiPrompt,
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
        $templateId = Yii::$app->request->post('template_id');
        if (!$finalPrompt) {
            return ['success' => false];
        }
        $model = new PromptInstance();
        if ($templateId) {
            $model->template_id = $templateId;
        }
        $model->final_prompt = $finalPrompt;
        if ($model->save()) {
            return ['success' => true, 'redirectUrl' => Url::to(['view', 'id' => $model->id])];
        }
        return ['success' => false];
    }
}
