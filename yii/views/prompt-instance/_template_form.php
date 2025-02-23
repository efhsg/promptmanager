<?php /** @noinspection PhpUnhandledExceptionInspection */

use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\web\JsExpression;

/* @var string $templateBody */
/* @var array $fields */

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

    return match ($field['type']) {
        'select', 'multi-select', 'select-invert' => Select2Widget::widget([
            'name' => $field['type'] === 'multi-select' ? $name . '[]' : $name,
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
                'class' => 'form-control custom-textarea',
                'rows' => 5,
                'cols' => 50,
                'style' => 'resize: vertical; height: 150px; overflow-y: auto;'
            ]
        ),
    };

}, $templateBody);
?>

<div class="generated-prompt-form">
    <?= $templateRendered ?>
</div>
