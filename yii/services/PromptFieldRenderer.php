<?php

namespace app\services;

use app\assets\PathSelectorFieldAsset;
use app\widgets\PathPreviewWidget;
use app\widgets\PathSelectorWidget;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\View;

/**
 * Renders dynamic form fields for prompt templates based on field type
 * and configuration. Supports text/code editors, selects, file pickers,
 * and text areas with Quill Delta format for rich text.
 */
class PromptFieldRenderer
{
    /**
     * Matches template placeholders in format GEN:{{123}}, PRJ:{{456}}, or EXT:{{789}}
     * - GEN: indicates a general/global field
     * - PRJ: indicates a project-specific field
     * - EXT: indicates a field from a linked project
     * - The numeric ID maps to the $fields array key
     */
    public const PLACEHOLDER_PATTERN = '/(?:GEN:|PRJ:|EXT:)\{\{(\d+)}}/';

    private const SELECT2_SETTINGS = [
        'minimumResultsForSearch' => 0,
    ];

    public function __construct(
        private readonly View $view,
    ) {}

    /**
     * Normalize a value to a JSON-encoded Quill Delta or empty string
     */
    public function toDeltaJson(mixed $value): string
    {
        if (is_array($value)) {
            return isset($value['ops']) ? json_encode($value, JSON_UNESCAPED_UNICODE) : '';
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return (is_array($decoded) && isset($decoded['ops'])) ? $value : '';
        }
        return '';
    }

    /**
     * Render a single field based on its type and configuration
     */
    public function renderField(array $field, string $placeholder): string
    {
        $fieldType = (string) ($field['type'] ?? 'text');
        $name = "PromptInstanceForm[fields][$placeholder]";

        $labelHtml = '';
        if (
            !empty($field['render_label'])
            && isset($field['label'])
            && trim((string) $field['label']) !== ''
        ) {
            $labelHtml = Html::tag('h2', Html::encode($field['label']));
        }

        $fieldHtml = match ($fieldType) {
            'text', 'code' => $this->renderTextCodeField($field, $placeholder, $name),
            'select', 'multi-select', 'select-invert' => $this->renderSelectField($field, $placeholder, $name, $fieldType),
            'file' => $this->renderFileField($field, $placeholder, $name),
            default => $this->renderTextareaField($field, $placeholder, $name),
        };

        return $labelHtml . $fieldHtml;
    }

    private function buildEditorPlaceholder(string $fieldType, string $label, string $customPlaceholder): string
    {
        if ($customPlaceholder !== '') {
            return $customPlaceholder;
        }

        if ($fieldType === 'code') {
            return $label !== '' ? "Paste or write code for {$label}…" : 'Paste or write code…';
        }

        return $label !== '' ? "Write {$label}…" : 'Type your content…';
    }

    private function renderTextCodeField(array $field, string $placeholder, string $name): string
    {
        $hiddenId = "hidden-$placeholder";
        $editorId = "editor-$placeholder";
        $fieldType = (string) ($field['type'] ?? 'text');

        $defaultValue = $this->toDeltaJson($field['default'] ?? '');

        $label = trim((string) ($field['label'] ?? ''));
        $customPlaceholder = trim((string) ($field['placeholder'] ?? ''));
        $placeholderText = $this->buildEditorPlaceholder($fieldType, $label, $customPlaceholder);

        return
            Html::hiddenInput($name, $defaultValue, ['id' => $hiddenId])
            . Html::tag(
                'div',
                Html::tag('div', '', [
                    'id' => $editorId,
                    'class' => 'resizable-editor',
                    'style' => 'min-height: 150px;',
                    'data-editor' => 'quill',
                    'data-target' => $hiddenId,
                    'data-config' => json_encode([
                        'theme' => 'snow',
                        'placeholder' => $placeholderText,
                        'modules' => [
                            'toolbar' => [
                                ['bold', 'italic', 'underline', 'strike', 'code'],
                                ['blockquote', 'code-block'],
                                [['list' => 'ordered'], ['list' => 'bullet']],
                                [['indent' => '-1'], ['indent' => '+1']],
                                [['header' => [1, 2, 3, 4, 5, 6, false]]],
                                [['align' => []]],
                                ['clean'],
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]),
                ['class' => 'resizable-editor-container mb-3']
            );
    }

    private function renderSelectField(array $field, string $placeholder, string $name, string $fieldType): string
    {
        $isMultiSelect = $fieldType === 'multi-select';
        $placeholderText = $isMultiSelect ? 'Select options...' : 'Select an option...';

        $template = new JsExpression(
            "function(state) {
                if (!state.id) return state.text;
                return $('<span></span>').text(state.text);
            }"
        );

        return Select2Widget::widget([
            'name' => $isMultiSelect ? $name . '[]' : $name,
            'id' => "field-$placeholder",
            'value' => $field['default'] ?? null,
            'items' => $field['options'] ?? [],
            'options' => [
                'placeholder' => $placeholderText,
                'multiple' => $isMultiSelect,
            ],
            'settings' => array_merge(
                self::SELECT2_SETTINGS,
                [
                    'templateResult' => $template,
                    'templateSelection' => $template,
                ]
            ),
        ]);
    }

    private function renderFileField(array $field, string $placeholder, string $name): string
    {
        $hiddenInputId = "field-$placeholder";
        $pathPreviewWrapperId = "path-preview-wrapper-$placeholder";
        $changeButtonId = "change-path-btn-$placeholder";
        $modalId = "path-modal-$placeholder";
        $pathSelectorId = "path-selector-$placeholder";
        $saveButtonId = "save-path-btn-$placeholder";
        $projectId = $field['project_id'] ?? null;
        $fieldId = $field['id'] ?? $placeholder;
        $currentPath = (string) ($field['default'] ?? '');

        $html = Html::hiddenInput($name, $currentPath, ['id' => $hiddenInputId]);
        $html .= Html::beginTag('div', ['class' => 'd-flex align-items-center gap-2']);
        $html .= Html::tag('div', PathPreviewWidget::widget([
            'path' => $currentPath,
            'previewUrl' => $currentPath !== '' ? Url::to([
                'field/path-preview',
                'id' => $fieldId,
                'path' => $currentPath,
            ]) : '',
            'enablePreview' => $currentPath !== '',
        ]), ['id' => $pathPreviewWrapperId, 'class' => 'flex-grow-1']);
        $html .= Html::button('Change', [
            'id' => $changeButtonId,
            'class' => 'btn btn-sm btn-outline-secondary',
            'type' => 'button',
            'data-bs-toggle' => 'modal',
            'data-bs-target' => "#$modalId",
        ]);
        $html .= Html::endTag('div');

        $modalHtml = $this->renderPathSelectorModal(
            $modalId,
            $pathSelectorId,
            $saveButtonId,
            $hiddenInputId,
            $currentPath
        );

        $html .= $modalHtml;

        $this->registerPathSelectorScript(
            $modalId,
            $pathSelectorId,
            $saveButtonId,
            $hiddenInputId,
            $pathPreviewWrapperId,
            $projectId,
            $fieldId
        );

        return $html;
    }

    private function renderTextareaField(array $field, string $placeholder, string $name): string
    {
        return Html::textarea(
            $name,
            (string) ($field['default'] ?? ''),
            [
                'id' => "field-$placeholder",
                'class' => 'form-control custom-textarea',
                'rows' => 5,
                'cols' => 50,
                'style' => 'resize: vertical; height: 150px; overflow-y: auto;',
            ]
        );
    }

    private function renderPathSelectorModal(
        string $modalId,
        string $pathSelectorId,
        string $saveButtonId,
        string $hiddenInputId,
        string $currentPath
    ): string {
        $modalHtml = Html::beginTag('div', [
            'class' => 'modal fade',
            'id' => $modalId,
            'tabindex' => '-1',
            'aria-hidden' => 'true',
        ]);
        $modalHtml .= Html::beginTag('div', ['class' => 'modal-dialog modal-lg']);
        $modalHtml .= Html::beginTag('div', ['class' => 'modal-content']);
        $modalHtml .= Html::beginTag('div', ['class' => 'modal-header']);
        $modalHtml .= Html::tag('h5', 'Change Path', ['class' => 'modal-title']);
        $modalHtml .= Html::button('', [
            'type' => 'button',
            'class' => 'btn-close',
            'data-bs-dismiss' => 'modal',
            'aria-label' => 'Close',
        ]);
        $modalHtml .= Html::endTag('div');
        $modalHtml .= Html::beginTag('div', ['class' => 'modal-body']);
        $modalHtml .= PathSelectorWidget::widget([
            'id' => $pathSelectorId,
            'initialValue' => $currentPath,
            'pathListUrl' => Url::to(['field/path-list']),
            'hiddenContentInputId' => $hiddenInputId,
            'wrapperOptions' => [
                'style' => 'display: block;',
            ],
        ]);
        $modalHtml .= Html::endTag('div');
        $modalHtml .= Html::beginTag('div', ['class' => 'modal-footer']);
        $modalHtml .= Html::button('Cancel', [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'data-bs-dismiss' => 'modal',
        ]);
        $modalHtml .= Html::button('Ok', [
            'id' => $saveButtonId,
            'type' => 'button',
            'class' => 'btn btn-primary',
        ]);
        $modalHtml .= Html::endTag('div');
        $modalHtml .= Html::endTag('div');
        $modalHtml .= Html::endTag('div');
        $modalHtml .= Html::endTag('div');

        return $modalHtml;
    }

    private function registerPathSelectorScript(
        string $modalId,
        string $pathSelectorId,
        string $saveButtonId,
        string $hiddenInputId,
        string $pathPreviewWrapperId,
        ?string $projectId,
        string $fieldId
    ): void {
        PathSelectorFieldAsset::register($this->view);

        $basePreviewUrl = Url::to(['field/path-preview', 'id' => $fieldId]);

        $config = Json::encode([
            'modalId' => $modalId,
            'pathSelectorId' => $pathSelectorId,
            'saveButtonId' => $saveButtonId,
            'hiddenInputId' => $hiddenInputId,
            'pathPreviewWrapperId' => $pathPreviewWrapperId,
            'projectId' => $projectId,
            'fieldType' => 'file',
            'fieldId' => $fieldId,
            'basePreviewUrl' => $basePreviewUrl,
        ]);

        $script = <<<JS
            if (typeof window.PathSelectorField === 'undefined') {
                console.error('PathSelectorField asset not loaded');
            } else {
                window.PathSelectorField.init($config)
            }
            JS;

        $this->view->registerJs($script, View::POS_END);
    }
}
