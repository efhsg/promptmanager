<?php

use yii\helpers\Html;
use yii\helpers\Json;
use Yii2\Extensions\DynamicForm\DynamicFormWidget;

/** @var yii\widgets\ActiveForm $form */
/** @var app\models\Field $modelField */
/** @var app\models\FieldOption[] $modelsFieldOption */

$quillConfig = Json::encode([
    'theme' => 'snow',
    'modules' => [
        'toolbar' => [
            ['code'],
            ['blockquote', 'code-block'],
            [['list' => 'ordered'], ['list' => 'bullet']],
            [['indent' => '-1'], ['indent' => '+1']],
            [['header' => [1, 2, 3, 4, 5, 6, false]]],
            ['clean'],
        ],
    ],
]);
?>
<hr>
<h4>Options</h4>

<?php
$hasChildErrors = false;
foreach ($modelsFieldOption as $child) {
    if ($child->hasErrors()) {
        $hasChildErrors = true;
        break;
    }
}
?>

<?php if ($hasChildErrors): ?>
    <div class="alert alert-danger">
        <strong>Validation Errors (Field Options):</strong>
        <ul>
            <?php foreach ($modelsFieldOption as $i => $child): ?>
                <?php foreach ($child->getErrors() as $attribute => $errors): ?>
                    <?php foreach ($errors as $error): ?>
                        <li>
                            <!-- e.g. "Option #1 / value: This field cannot be blank." -->
                            <?= Html::encode("Option #" . ($i + 1) . " / $attribute: $error") ?>
                        </li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
DynamicFormWidget::begin([
    'widgetContainer' => 'dynamicform_wrapper',
    'widgetBody' => '.container-options',
    'widgetItem' => '.option-item',
    'limit' => 999,
    'min' => 1,
    'insertButton' => '.add-option',
    'deleteButton' => '.remove-option',
    'model' => $modelsFieldOption[0],
    'formId' => 'field-form',
    'formFields' => [
        'value',
        'label',
        'selected_by_default',
        'order',
    ],
]);
?>

<div class="container-options">
    <?php foreach ($modelsFieldOption as $i => $option): ?>

        <div class="option-item row mb-3">
            <?php

            if (!$option->isNewRecord) {
                echo Html::activeHiddenInput($option, "[$i]id");
            }
        ?>

            <div class="col-md-4">
                <div class="form-group field-fieldoption-<?= $i ?>-value">
                    <label class="control-label">Value</label>
                    <?= Html::activeHiddenInput($option, "[$i]value", [
                        'id' => "fieldoption-{$i}-value",
                        'class' => 'field-option-value-input',
                    ]) ?>
                    <div
                        data-editor="quill"
                        data-target="fieldoption-<?= $i ?>-value"
                        data-config='<?= $quillConfig ?>'
                        class="resizable-editor field-option-editor"
                        style="min-height: 100px;"
                    ></div>
                    <div class="help-block"></div>
                </div>
            </div>

            <div class="col-md-3">
                <?= $form->field($option, "[$i]label")
                ->textInput(['maxlength' => true])
                ->label('Label')
        ?>
            </div>

            <div class="col-md-2">
                <?= $form->field($option, "[$i]selected_by_default")
            ->dropDownList([0 => 'No', 1 => 'Yes'])
            ->label('Default On')
        ?>
            </div>

            <div class="col-md-2">
                <?= $form->field($option, "[$i]order")
            ->textInput(['type' => 'number'])
            ->label('Order')
        ?>
            </div>
            <div class="col-md-1 d-flex align-items-end justify-content-end">
                <button type="button" class="remove-option btn btn-danger btn-sm mt-auto">
                    <i class="glyphicon glyphicon-minus"></i>
                </button>
            </div>

        </div>
    <?php endforeach; ?>
</div>

<div class="text-end mb-3">
    <button type="button" class="add-option btn btn-primary btn-sm">
        <i class="glyphicon glyphicon-plus"></i> Add Option
    </button>
</div>

<?php DynamicFormWidget::end(); ?>

<?php
$script = <<<JS
            jQuery(function($) {

                function getMaxOrder() {
                    let maxOrder = 0;

                    $('input[name*="[order]"]').each(function() {
                        let val = parseInt($(this).val());
                        if (!isNaN(val) && val > maxOrder) {
                            maxOrder = val;
                        }
                    });
                    return maxOrder;
                }

                function reinitEditorInItem(item) {
                    const editorEl = item.querySelector('[data-editor="quill"]');
                    const hiddenInput = item.querySelector('.field-option-value-input');

                    if (!editorEl || !hiddenInput) return;

                    delete editorEl.dataset.inited;
                    hiddenInput.value = '';

                    const newIndex = $(item).index();
                    const newHiddenId = 'fieldoption-' + newIndex + '-value';
                    hiddenInput.id = newHiddenId;
                    editorEl.dataset.target = newHiddenId;

                    const ql = editorEl.querySelector('.ql-container');
                    const tb = editorEl.querySelector('.ql-toolbar');
                    if (ql) ql.remove();
                    if (tb) tb.remove();

                    const cfg = JSON.parse(editorEl.dataset.config);
                    const quill = new Quill(editorEl, cfg);
                    editorEl.dataset.inited = '1';

                    quill.on('text-change', function() {
                        hiddenInput.value = JSON.stringify(quill.getContents());
                    });
                }

                $('.dynamicform_wrapper').on('afterInsert', function(e, item) {
                    let currentMax = getMaxOrder() || 0;
                    let newOrder = currentMax + 10;

                    $(item).find('input[name*="[order]"]').val(newOrder);

                    reinitEditorInItem(item);
                });
            });
            JS;

$this->registerJs($script);
?>



