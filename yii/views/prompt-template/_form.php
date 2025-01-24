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

<div class="prompt-template-form">
    <?php $form = ActiveForm::begin([
        'id' => 'prompt-template-form',
        'enableClientValidation' => true,
    ]); ?>

    <div class="accordion" id="formAccordion">

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingBasicInfo">
                <button class="accordion-button"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapseBasicInfo"
                        aria-expanded="true"
                aria-controls="collapseBasicInfo">
                Basic Information
                </button>
            </h2>
            <div id="collapseBasicInfo"
                 class="accordion-collapse collapse show"
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
                <button class="accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapseEditor"
                        aria-expanded="false"
                        aria-controls="collapseEditor">
                    Editor
                </button>
            </h2>
            <div id="collapseEditor"
                 class="accordion-collapse collapse"
                 aria-labelledby="headingEditor"
                 data-bs-parent="#formAccordion">

                <div class="accordion-body">
                    <?= $form->field($model, 'template_body')->hiddenInput(['id' => 'template-body'])->label(false) ?>

                    <div class="resizable-editor-container">
                        <div id="editor" class="resizable-editor">
                            <?= Html::encode($model->template_body) ?>
                        </div>
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

// Create custom dropdowns
var generalFieldDropdown = document.createElement('select');
generalFieldDropdown.classList.add('ql-insertGeneralField', 'ql-picker', 'ql-font');
generalFieldDropdown.innerHTML = '<option value="" selected disabled>General Field</option>';

var projectFieldDropdown = document.createElement('select');
projectFieldDropdown.classList.add('ql-insertProjectField', 'ql-picker', 'ql-font');
projectFieldDropdown.innerHTML = '<option value="" selected disabled>Project Field</option>';

// Populate general fields dropdown
var generalFields = $generalFieldsJson;
Object.keys(generalFields).forEach(function(key) {
    var option = document.createElement('option');
    option.setAttribute('value', key);
    option.textContent = generalFields[key].label;
    generalFieldDropdown.appendChild(option);
});

// Populate project fields dropdown
var projectFields = $projectFieldsJson;
Object.keys(projectFields).forEach(function(key) {
    var option = document.createElement('option');
    option.setAttribute('value', key);
    option.textContent = projectFields[key].label;
    projectFieldDropdown.appendChild(option);
});

generalFieldDropdown.classList.add('ql-picker', 'ql-font');
projectFieldDropdown.classList.add('ql-picker', 'ql-font');

// Replace toolbar placeholders with dropdowns
toolbarContainer.querySelector('.ql-insertGeneralField').replaceWith(generalFieldDropdown);
toolbarContainer.querySelector('.ql-insertProjectField').replaceWith(projectFieldDropdown);

generalFieldDropdown.addEventListener('change', function () {
    var value = this.value;
    if (value) {
        const cursorPosition = quill.getSelection().index;
        quill.insertText(cursorPosition, value);
        quill.setSelection(cursorPosition + value.length);
        this.value = '';
        this.querySelector('option[value=""]').selected = true;
    }
});

projectFieldDropdown.addEventListener('change', function () {
    var value = this.value;
    if (value) {
        const cursorPosition = quill.getSelection().index;
        quill.insertText(cursorPosition, value);
        quill.setSelection(cursorPosition + value.length);
        this.value = '';
        this.querySelector('option[value=""]').selected = true;
    }
});

JS;
$this->registerJs($script);

$templateBody = json_encode($model->template_body);
$script = <<<JS
try {
    quill.clipboard.dangerouslyPasteHTML($templateBody)
} catch (error) {
    console.error('Error injecting template body:', error);
}

quill.on('text-change', function() {
    document.querySelector('#template-body').value = quill.root.innerHTML;
});
JS;
$this->registerJs($script);
?>

<?php
$script = <<<JS
$('#prompt-template-form').on('afterValidate', function (event, messages, errorAttributes) {
    if (!$(this).data('yiiActiveForm').submitting) {
        return;
    }

    if (errorAttributes.length > 0) {
        let firstErrorField = $(this).find('.has-error').first();
        if (firstErrorField.length) {
            let accordionPanel = firstErrorField.closest('.accordion-collapse');
            if (accordionPanel.length) {
                let collapseInstance = bootstrap.Collapse.getOrCreateInstance(accordionPanel[0]);
                collapseInstance.show();

                let accordionButton = accordionPanel.siblings('.accordion-header').find('button');
                if (accordionButton.length) {
                    accordionButton.removeClass('collapsed').attr('aria-expanded', true);
                }
            }
        }
    }
});
JS;
$this->registerJs($script);
?>


