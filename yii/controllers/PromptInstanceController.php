<?php

namespace app\controllers;

use app\models\PromptInstance;
use app\models\PromptInstanceForm;
use app\models\PromptInstanceSearch;
use app\services\ContextService;
use app\services\EntityPermissionService;
use app\services\ModelService;
use app\services\PromptInstanceService;
use app\services\PromptTemplateService;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\filters\AccessControl;
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
                            if ($action->id === 'generate-prompt-form' || $action->id === 'generate-final-prompt') {
                                $callback = function () {
                                    $templateId = Yii::$app->request->post('template_id');
                                    if ($templateId) {
                                        return $this->promptTemplateService->getTemplateById($templateId, Yii::$app->user->id);
                                    }
                                    return null;
                                };
                                return $this->permissionService->hasActionPermission('promptTemplate', 'view', $callback);
                            } elseif ($this->permissionService->isModelBasedAction($action->id)) {
                                $callback = fn() => $this->findModel((int)Yii::$app->request->get('id'));
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
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, Yii::$app->user->id);
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
        return $this->render('view', ['model' => $this->findModel($id)]);
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

        // Retrieve the field definitions via the pivot relation (PromptTemplate::getFields())
        $fields = [];
        foreach ($template->fields as $field) {
            $fieldKey = (string)$field->id;
            $fieldData = [
                'type' => $field->type,
                'label' => $field->label,
            ];

            if (in_array($field->type, ['select', 'multi-select'])) {
                // Build options from the FieldOption records.
                $options = [];
                $default = [];
                foreach ($field->fieldOptions as $option) {
                    // Use the option's value as key; if label is set, use it as the display text.
                    $options[$option->value] = $option->label ?: $option->value;
                    if ($option->selected_by_default) {
                        $default[] = $option->value;
                    }
                }
                $fieldData['options'] = $options;
                // For multi-select, default is an array; for single select, pick the first default if available.
                $fieldData['default'] = $field->type === 'multi-select'
                    ? $default
                    : (count($default) ? reset($default) : null);
            } elseif ($field->type === 'text') {
                // Use the content attribute as the default value for text fields.
                $fieldData['default'] = $field->content;
            } else {
                // Fallback for any other types (set empty default)
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
     * @return string
     * @throws NotFoundHttpException if the template cannot be found.
     */
    public function actionGenerateFinalPrompt(): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;

        // Retrieve top-level parameters.
        $templateId = Yii::$app->request->post('template_id');
        $selectedContextIds = Yii::$app->request->post('context_ids') ?? [];

        if (!$templateId) {
            throw new NotFoundHttpException("Template ID not provided.");
        }

        // Retrieve the prompt template.
        $template = $this->promptTemplateService->getTemplateById($templateId, Yii::$app->user->id);
        if (!$template) {
            throw new NotFoundHttpException("Template not found or access denied.");
        }
        $templateBody = $template->template_body;

        // Retrieve field values from the generated prompt form.
        // (These are still inside the PromptInstanceForm array.)
        $postData = Yii::$app->request->post('PromptInstanceForm');
        $fieldsValues = $postData['fields'] ?? [];

        // Replace placeholders in the template with submitted field values.
        $generatedPrompt = preg_replace_callback('/(?:GEN:)?\{\{(\d+)}}/', function ($matches) use ($fieldsValues) {
            $fieldKey = $matches[1];
            if (isset($fieldsValues[$fieldKey])) {
                $value = $fieldsValues[$fieldKey];
                return is_array($value) ? implode(', ', $value) : $value;
            }
            return $matches[0];
        }, $templateBody);

        // Retrieve all context contents for the user.
        $allContextsContent = $this->contextService->fetchContextsContent(Yii::$app->user->id);
        $contextsArr = [];
        foreach ($selectedContextIds as $id) {
            if (!empty($allContextsContent[$id])) {
                $contextsArr[] = $allContextsContent[$id];
            }
        }
        $contextsText = !empty($contextsArr) ? implode("\n\n", $contextsArr) : '';

        // Prepend the contexts' text to the generated prompt (if any contexts are selected).
        return $contextsText ? $contextsText . "\n\n" . $generatedPrompt : $generatedPrompt;
    }


    /**
     * Finds the PromptInstance model based on its primary key value and verifies that it belongs
     * to the current user (via its associated prompt template and project).
     *
     * @param int $id
     * @return ActiveRecord
     * @throws NotFoundHttpException if the model is not found or not owned by the user.
     */
    protected function findModel(int $id): ActiveRecord
    {
        return PromptInstance::find()
            ->joinWith(['template.project'])
            ->where([
                'prompt_instance.id' => $id,
                'project.user_id' => Yii::$app->user->id,
            ])
            ->one() ?? throw new NotFoundHttpException('The requested prompt instance does not exist or is not yours.');
    }
}
