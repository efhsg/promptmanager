<?php /** @noinspection PhpUnhandledExceptionInspection */

use app\assets\QuillAsset;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\web\JsExpression;

/* @var string $templateBody */
/* @var array $fields */

// Register Quill assets just once
QuillAsset::register($this);

$delta = json_decode($templateBody, true);
if (!$delta || !isset($delta['ops'])) {
    throw new \InvalidArgumentException("Template is not in valid Delta format.");
}

$templateText = '';
foreach ($delta['ops'] as $op) {
    if (isset($op['insert']) && is_string($op['insert'])) {
        $templateText .= $op['insert'];
    }
}

$templateRendered = preg_replace_callback('/(?:GEN:|PRJ:)\{\{(\d+)}}/', function ($matches) use ($fields) {
    $placeholder = $matches[1];
    if (!isset($fields[$placeholder])) {
        return $matches[0];
    }

    $field = $fields[$placeholder];
    $name = "PromptInstanceForm[fields][$placeholder]";

    $select2Settings = [
        'minimumResultsForSearch' => 0,
        'templateResult' => new JsExpression("
            function(state) {
                if (!state.id) return state.text;
                return $('<span></span>').text(state.text);
            }
        "),
        'templateSelection' => new JsExpression("
            function(state) {
                if (!state.id) return state.text;
                return $('<span></span>').text(state.text);
            }
        "),
    ];

    if (in_array($field['type'], ['text', 'code'])) {
        $hiddenId = "hidden-$placeholder";
        $editorId = "editor-$placeholder";

        // Hidden input stores Delta JSON
        return
            Html::hiddenInput($name, $field['default'], ['id' => $hiddenId]) .
            Html::tag('div', '', [
                'id' => $editorId,
                'style' => 'height:150px;border:1px solid #ccc',
                'data-editor' => 'quill',
                'data-target' => $hiddenId,
                'data-config' => json_encode([
                    'theme' => 'snow',
                    'modules' => [
                        'toolbar' => [
                            ['bold', 'italic', 'underline'],
                            ['blockquote', 'code-block'],
                            [['list' => 'ordered'], ['list' => 'bullet']],
                            ['clean']
                        ]
                    ]
                ], JSON_UNESCAPED_SLASHES)
            ]);
    }

    return match ($field['type']) {
        'select', 'multi-select', 'select-invert' => Select2Widget::widget([
            'name' => $field['type'] === 'multi-select' ? $name . '[]' : $name,
            'id' => "field-$placeholder",
            'value' => $field['default'],
            'items' => $field['options'],
            'options' => [
                'placeholder' => 'Select ' . ($field['type'] === 'multi-select' ? 'options' : 'an option') . '...',
                'multiple' => $field['type'] === 'multi-select'
            ],
            'settings' => $select2Settings,
        ]),
        default => Html::textarea(
            $name,
            $field['default'],
            [
                'id' => "field-$placeholder",
                'class' => 'form-control custom-textarea',
                'rows' => 5,
                'cols' => 50,
                'style' => 'resize: vertical; height: 150px; overflow-y: auto;'
            ]
        ),
    };
}, $templateText);
?>

<div class="generated-prompt-form">
    <?= $templateRendered ?>
</div>