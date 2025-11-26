<?php
/** @noinspection JSUnresolvedReference */

/** @noinspection PhpUnhandledExceptionInspection */

use app\assets\QuillAsset;
use app\widgets\PathPreviewWidget;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;

/* @var string $templateBody */
/* @var array $fields */

QuillAsset::register($this);

$delta = json_decode($templateBody, true);
if (!$delta || !isset($delta['ops'])) {
    throw new InvalidArgumentException('Template is not in valid Delta format.');
}

$templateText = '';
foreach ($delta['ops'] as $op) {
    if (isset($op['insert']) && is_string($op['insert'])) {
        $templateText .= $op['insert'];
    }
}

/** Normalize a value to a JSON-encoded Quill Delta or '' */
$toDeltaJson = static function (mixed $value): string {
    if (is_array($value)) {
        return isset($value['ops']) ? json_encode($value, JSON_UNESCAPED_UNICODE) : '';
    }
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return (is_array($decoded) && isset($decoded['ops'])) ? $value : '';
    }
    return '';
};

$templateRendered = preg_replace_callback(
        '/(?:GEN:|PRJ:)\{\{(\d+)}}/',
        static function (array $matches) use ($fields, $toDeltaJson) {
            $select2Settings = [
                    'minimumResultsForSearch' => 0,
                    'templateResult' => new JsExpression(
                            "
            function(state) {
                if (!state.id) return state.text;
                return $('<span></span>').text(state.text);
            }
        "
                    ),
                    'templateSelection' => new JsExpression(
                            "
            function(state) {
                if (!state.id) return state.text;
                return $('<span></span>').text(state.text);
            }
        "
                    ),
            ];
            $placeholder = $matches[1];
            if (!isset($fields[$placeholder])) {
                return $matches[0];
            }

            $field = $fields[$placeholder];
            $fieldType = (string)($field['type'] ?? 'text');
            $name = "PromptInstanceForm[fields][$placeholder]";

            if (in_array($fieldType, ['text', 'code'], true)) {
                $hiddenId = "hidden-$placeholder";
                $editorId = "editor-$placeholder";

                $defaultValue = $toDeltaJson($field['default'] ?? '');

                $label = trim((string)($field['label'] ?? ''));
                $custom = trim((string)($field['placeholder'] ?? ''));

                $placeholderText = $custom !== ''
                        ? $custom
                        : ($fieldType === 'code'
                                ? ($label !== '' ? "Paste or write code for {$label}…" : 'Paste or write code…')
                                : ($label !== '' ? "Write {$label}…" : 'Type your content…'));

                return
                        Html::hiddenInput($name, $defaultValue, ['id' => $hiddenId]) .
                        Html::tag('div', '', [
                                'id' => $editorId,
                                'style' => 'height:150px;border:1px solid #ccc',
                                'data-editor' => 'quill',
                                'data-target' => $hiddenId,
                                'data-config' => json_encode([
                                        'theme' => 'snow',
                                        'placeholder' => $placeholderText,
                                        'modules' => [
                                                'toolbar' => [
                                                        ['bold', 'italic', 'underline'],
                                                        ['blockquote', 'code-block'],
                                                        [['list' => 'ordered'], ['list' => 'bullet']],
                                                        ['clean'],
                                                ],
                                        ],
                                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
            }

            return match ($fieldType) {
                'select', 'multi-select', 'select-invert' => Select2Widget::widget([
                        'name' => $fieldType === 'multi-select' ? $name . '[]' : $name,
                        'id' => "field-$placeholder",
                        'value' => $field['default'],
                        'items' => $field['options'],
                        'options' => [
                                'placeholder' => 'Select ' . ($fieldType === 'multi-select' ? 'options' : 'an option') . '...',
                                'multiple' => $fieldType === 'multi-select',
                        ],
                        'settings' => $select2Settings,
                ]),
                'file' => Html::hiddenInput(
                        $name,
                        (string)($field['default'] ?? ''),
                        ['id' => "field-$placeholder"]
                ) . PathPreviewWidget::widget([
                        'path' => (string)($field['default'] ?? ''),
                        'previewUrl' => !empty($field['default'] ?? '') ? Url::to([
                                'field/path-preview',
                                'id' => $field['id'] ?? $placeholder,
                                'path' => $field['default'] ?? '',
                        ]) : '',
                        'enablePreview' => !empty($field['default'] ?? ''),
                ]),
                default => Html::textarea(
                        $name,
                        (string)($field['default'] ?? ''),
                        [
                                'id' => "field-$placeholder",
                                'class' => 'form-control custom-textarea',
                                'rows' => 5,
                                'cols' => 50,
                                'style' => 'resize: vertical; height: 150px; overflow-y: auto;',
                        ]
                ),
            };
        },
        $templateText
);
?>

<div class="generated-prompt-form">
    <?= $templateRendered ?>
</div>
