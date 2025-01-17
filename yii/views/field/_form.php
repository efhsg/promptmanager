<?php

use common\constants\FieldConstants;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Field $modelField */
/** @var app\models\FieldOption[] $modelsFieldOption */
/** @var array $projects */
/** @var yii\widgets\ActiveForm $form */

?>

<?php if ($modelField->hasErrors()): ?>
    <div class="alert alert-danger">
        <strong>Validation Errors:</strong>
        <ul>
            <?php foreach ($modelField->getErrors() as $attribute => $errors): ?>
                <?php foreach ($errors as $error): ?>
                    <li><?= Html::encode($attribute . ': ' . $error) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="field-form">
    <?php $form = ActiveForm::begin(['id' => 'field-form']); ?>

    <?= $form->field($modelField, 'project_id')->dropDownList(
        $projects,
        ['prompt' => '(not set)']
    )->label('Project') ?>

    <?= $form->field($modelField, 'name')->textInput(['maxlength' => true])->label('Field Name') ?>

    <?= $form->field($modelField, 'type')->dropDownList(
        array_combine(FieldConstants::TYPES, FieldConstants::TYPES),
        [
            'onchange' => 'toggleFieldOptions(this.value)'
        ]
    )->label('Field Type') ?>

    <?= $form->field($modelField, 'selected_by_default')->dropDownList(
        [0 => 'No', 1 => 'Yes']
    )->label('Default on') ?>

    <?= $form->field($modelField, 'label')->textInput(['maxlength' => true])->label('Label (Optional)') ?>

    <div id="field-content-wrapper" style="display: none;">
        <?= $form->field($modelField, 'content')->textarea(['rows' => 10])->label('Content') ?>
    </div>

    <div id="field-options-wrapper" style="display: none;">
        <?php
        echo $this->render('_fieldOptionsForm', [
            'form' => $form,
            'modelField' => $modelField,
            'modelsFieldOption' => $modelsFieldOption,
        ]);;
        ?>
    </div>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<script>
    function toggleFieldOptions(value) {
        const contentWrapper = document.getElementById('field-content-wrapper');
        const optionsWrapper = document.getElementById('field-options-wrapper');
        const optionInputs   = optionsWrapper.querySelectorAll('input, textarea, select');

        // Show/hide the "Content" field
        if (value === 'text') {
            contentWrapper.style.display = 'block';
            contentWrapper.querySelector('textarea').disabled = false;
        } else {
            contentWrapper.style.display = 'none';
            contentWrapper.querySelector('textarea').disabled = true;
        }

        // Show/hide the "Options" form
        if (['select', 'multi-select'].includes(value)) {
            optionsWrapper.style.display = 'block';
            // Enable all fields so they are validated & submitted
            optionInputs.forEach(el => el.disabled = false);
        } else {
            optionsWrapper.style.display = 'none';
            // Disable all fields to prevent validation & submission
            optionInputs.forEach(el => el.disabled = true);
        }
    }
    toggleFieldOptions('<?= $modelField->type ?>');
</script>
