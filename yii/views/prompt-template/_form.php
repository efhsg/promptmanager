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

    <div class="accordion" id="formAccordion">

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingBasicInfo">
                <button class="accordion-button <?= $model->isNewRecord ? '' : 'collapsed' ?>"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapseBasicInfo"
                        aria-expanded="<?= $model->isNewRecord ? 'true' : 'false' ?>"
                        aria-controls="collapseBasicInfo">
                    Basic Information
                </button>
            </h2>
            <div id="collapseBasicInfo"
                 class="accordion-collapse collapse <?= $model->isNewRecord ? 'show' : '' ?>"
                 aria-labelledby="headingBasicInfo"
                 data-bs-parent="#formAccordion">
                <div class="accordion-body">
                    <?= $form->field($model, 'project_id')->dropDownList($projects, [
                        'prompt' => 'Select a Project'
                    ]) ?>

                    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

                    <?= $form->field($model, 'description')->textarea(['rows' => 6]) ?>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingEditor">
                <button class="accordion-button <?= $model->isNewRecord ? 'collapsed' : '' ?>"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapseEditor"
                        aria-expanded="<?= $model->isNewRecord ? 'false' : 'true' ?>"
                        aria-controls="collapseEditor">
                    Body
                </button>
            </h2>
            <div id="collapseEditor"
                 class="accordion-collapse collapse <?= $model->isNewRecord ? '' : 'show' ?>"
                 aria-labelledby="headingEditor"
                 data-bs-parent="#formAccordion">
                <div class="accordion-body">
                    <?= $form->field($model, 'template_body')->hiddenInput(['id' => 'template-body'])->label(false) ?>

                    <div class="resizable-editor-container">
                        <div id="editor" class="resizable-editor"></div>
                        </div>
                    </div>
                </div>
            </div>

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

$fieldsJson = $generalFieldsJson . ',' . $projectFieldsJson;
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
    option.value=key;
    option.textContent = generalFields[key].label;
    generalFieldDropdown.appendChild(option);
});

var projectFields = $projectFieldsJson;
Object.keys(projectFields).forEach(function(key) {
    var option = document.createElement('option');
    option.value=key;
    option.textContent = projectFields[key].label;
    projectFieldDropdown.appendChild(option);
});

toolbarContainer.querySelector('.ql-insertGeneralField').replaceWith(generalFieldDropdown);
toolbarContainer.querySelector('.ql-insertProjectField').replaceWith(projectFieldDropdown);

generalFieldDropdown.addEventListener('change', function () {
    var v=this.value;
    if(v){
        var pos=quill.getSelection().index;
        quill.insertText(pos,v);
        quill.setSelection(pos+v.length);
        this.value = '';
    }
});

projectFieldDropdown.addEventListener('change', function () {
    var v=this.value;
    if(v){
        var pos=quill.getSelection().index;
        quill.insertText(pos,v);
        quill.setSelection(pos+v.length);
        this.value = '';
    }
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

$script = <<<JS
$('#prompt-template-form').on('afterValidate',function(e,m,errors){
    if(!$(this).data('yiiActiveForm').submitting) return;
    if(errors.length){
        var fld=$(this).find('.has-error').first();
        if(fld.length){
            var panel=fld.closest('.accordion-collapse');
            var inst=bootstrap.Collapse.getOrCreateInstance(panel[0],{toggle:false});
            inst.show();
            panel.siblings('.accordion-header').find('button').removeClass('collapsed').attr('aria-expanded',true);
        }
    }
});
JS;
$this->registerJs($script);
?>```
