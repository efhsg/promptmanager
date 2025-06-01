<?php /** @noinspection BadExpressionStatementJS */
/** @noinspection JSUnresolvedReference */
use app\assets\QuillAsset;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
/** @var yii\web\View $this */
/** @var app\models\PromptTemplate $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $projects */
/** @var array $generalFieldsMap */
/** @var array $projectFieldsMap */
QuillAsset::register($this);
?>
    <div class="prompt-template-form focus-on-first-field">
        <?php $form = ActiveForm::begin([
            'id' => 'prompt-template-form',
            'enableClientValidation' => true,
        ]); ?>
        <?= $form->field($model, 'project_id')->dropDownList($projects, [
            'prompt' => 'Select a Project'
        ]) ?>

        <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'template_body')->hiddenInput(['id' => 'template-body'])->label(false) ?>
        <div class="resizable-editor-container">
            <div id="editor" class="resizable-editor"></div>
        </div>
        <div class="form-group mt-4 text-end">
            <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
            <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>

<?php
$generalFieldsJson = json_encode($generalFieldsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$projectFieldsJson = json_encode($projectFieldsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$script = <<<JS
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: {
            container: [
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
                ['clean'],
                [{ 'insertGeneralField': [] }],
                [{ 'insertProjectField': [] }]
            ]
        }
    }
});

var toolbar = quill.getModule('toolbar');
var toolbarContainer = toolbar.container;

var generalFieldDropdown = document.createElement('select');
generalFieldDropdown.classList.add('ql-insertGeneralField', 'ql-picker', 'ql-font');
generalFieldDropdown.innerHTML = '<option value="" selected disabled>General Field</option>';

var projectFieldDropdown = document.createElement('select');
projectFieldDropdown.classList.add('ql-insertProjectField', 'ql-picker', 'ql-font');
projectFieldDropdown.innerHTML = '<option value="" selected disabled>Project Field</option>';

var generalFields = $generalFieldsJson;
Object.keys(generalFields).forEach(function(key) {
    var option = document.createElement('option');
    option.value = key;
    option.textContent = generalFields[key].label;
    generalFieldDropdown.appendChild(option);
});

var projectFields = $projectFieldsJson;
Object.keys(projectFields).forEach(function(key) {
    var option = document.createElement('option');
    option.value = key;
    option.textContent = projectFields[key].label;
    projectFieldDropdown.appendChild(option);
});

toolbarContainer.querySelector('.ql-insertGeneralField').replaceWith(generalFieldDropdown);
toolbarContainer.querySelector('.ql-insertProjectField').replaceWith(projectFieldDropdown);

function insertFieldText(dropdown) {
    var value = dropdown.value;
    if (value) {
        var position = quill.getSelection().index;
        quill.insertText(position, value);
        quill.setSelection(position + value.length);
        dropdown.value = '';
    }
}

generalFieldDropdown.addEventListener('change', function() {
    insertFieldText(this);
});

projectFieldDropdown.addEventListener('change', function() {
    insertFieldText(this);
});
JS;

$this->registerJs($script);

$initialDelta = $model->template_body ?: '{}';
$script = <<<JS
quill.setContents($initialDelta)
quill.on('text-change', function() {
    document.getElementById('template-body').value = JSON.stringify(quill.getContents());
});
JS;

$this->registerJs($script);