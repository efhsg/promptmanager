<?php /** @noinspection BadExpressionStatementJS */

/** @noinspection JSUnresolvedReference */

use common\constants\FieldConstants;
use app\assets\QuillAsset;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Field $modelField */
/** @var app\models\FieldOption[] $modelsFieldOption */
/** @var array $projects */
/** @var yii\widgets\ActiveForm $form */

QuillAsset::register($this);
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

<div class="field-form focus-on-first-field">
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
        <?= $form->field($modelField, 'content')->hiddenInput(['id' => 'field-content'])->label(false) ?>
        <div class="resizable-editor-container mb-3">
            <div id="editor" class="resizable-editor">
                <?= $modelField->content ?>
            </div>
        </div>
    </div>

    <div id="field-options-wrapper" style="display: none;">
        <?php
        echo $this->render('_fieldOptionsForm', [
            'form'              => $form,
            'modelField'        => $modelField,
            'modelsFieldOption' => $modelsFieldOption,
        ]);
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
        const optionInputs   = optionsWrapper.querySelectorAll('input, textarea, select, code');

        if (value === 'text' || value === 'code') {
            contentWrapper.style.display = 'block';
            document.querySelector('#field-content').disabled = false;
        } else {
            contentWrapper.style.display = 'none';
            document.querySelector('#field-content').disabled = true;
        }

        if (['select', 'multi-select'].includes(value)) {
            optionsWrapper.style.display = 'block';
            optionInputs.forEach(el => el.disabled = false);
        } else {
            optionsWrapper.style.display = 'none';
            optionInputs.forEach(el => el.disabled = true);
        }
    }
    toggleFieldOptions('<?= $modelField->type ?>');
</script>

<?php
$templateContent = json_encode($modelField->content);
$script = <<<JS
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'indent': '-1' }, { 'indent': '+1' }],
            [{ 'direction': 'rtl' }],
            [{ 'size': ['small', false, 'large', 'huge'] }],
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'font': [] }],
            [{ 'align': [] }],
            ['link', 'image'],
            ['clean']
        ]
    }
});

try {
    quill.clipboard.dangerouslyPasteHTML($templateContent)
} catch (error) {
    console.error('Error injecting content:', error);
}

quill.on('text-change', function() {
    document.querySelector('#field-content').value = quill.root.innerHTML;
});
JS;
$this->registerJs($script);
?>


