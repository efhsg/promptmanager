<?php

/** @noinspection BadExpressionStatementJS */
/** @noinspection JSUnresolvedReference */

use app\assets\QuillAsset;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\PromptTemplate $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $projects */
/** @var array $generalFieldsMap */
/** @var array $projectFieldsMap */
/** @var array $externalFieldsMap */
QuillAsset::register($this);
?>
<div class="prompt-template-form focus-on-first-field">
    <?php $form = ActiveForm::begin([
        'id' => 'prompt-template-form',
        'enableClientValidation' => true,
    ]); ?>
    <?= $form->field($model, 'project_id')->dropDownList($projects, [
        'prompt' => 'Select a Project',
    ]) ?>

    <?= $form->field($model, 'name', [
        'template' => "{label}\n<div class=\"input-group\">{input}<button type=\"button\" class=\"btn btn-outline-secondary\" id=\"suggest-name-btn\" title=\"Suggest name based on content\"><i class=\"bi bi-stars\"></i></button></div>\n{hint}\n{error}",
    ])->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <label class="form-label">Template Body</label>
        <?= Html::activeHiddenInput($model, 'template_body', ['id' => 'template-body']) ?>
        <div class="resizable-editor-container">
            <div id="editor" class="resizable-editor"></div>
        </div>
        <?= Html::error($model, 'template_body', ['class' => 'invalid-feedback d-block']) ?>
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
$externalFieldsJson = json_encode($externalFieldsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$importTextUrl = Url::to(['/note/import-text']);
$importMarkdownUrl = Url::to(['/note/import-markdown']);
$suggestNameUrl = Url::to(['/claude/suggest-name']);

// Build project data for export
$projectDataForExport = [];
foreach ($projects as $projectId => $projectName) {
    $project = \app\models\Project::find()->findUserProject($projectId, Yii::$app->user->id);
    $projectDataForExport[$projectId] = [
        'hasRoot' => $project && !empty($project->root_directory),
        'rootDirectory' => $project->root_directory ?? null,
    ];
}
$projectDataJson = json_encode($projectDataForExport);

$script = <<<JS
    window.templateProjectData = $projectDataJson;

    window.quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    ['bold', 'italic', 'code'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }],
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['clean'],
                    [{ 'insertGeneralField': [] }],
                    [{ 'insertProjectField': [] }],
                    [{ 'insertExternalField': [] }],
                    [{ 'clearEditor': [] }],
                    [{ 'smartPaste': [] }],
                    [{ 'loadMd': [] }],
                    [{ 'exportContent': [] }]
                ]
            }
        }
    });

    var toolbar = window.quill.getModule('toolbar');
    var toolbarContainer = toolbar.container;

    var generalFieldDropdown = document.createElement('select');
    generalFieldDropdown.classList.add('ql-insertGeneralField', 'ql-picker', 'ql-font');
    generalFieldDropdown.innerHTML = '<option value="" selected disabled>General Field</option>';

    var projectFieldDropdown = document.createElement('select');
    projectFieldDropdown.classList.add('ql-insertProjectField', 'ql-picker', 'ql-font');
    projectFieldDropdown.innerHTML = '<option value="" selected disabled>Project Field</option>';

    var externalFieldDropdown = document.createElement('select');
    externalFieldDropdown.classList.add('ql-insertExternalField', 'ql-picker', 'ql-font');
    externalFieldDropdown.innerHTML = '<option value="" selected disabled>External Field</option>';

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

    var externalFields = $externalFieldsJson;
    Object.keys(externalFields).forEach(function(key) {
        var option = document.createElement('option');
        option.value = key;
        option.textContent = externalFields[key].label;
        externalFieldDropdown.appendChild(option);
    });

    toolbarContainer.querySelector('.ql-insertGeneralField').replaceWith(generalFieldDropdown);
    toolbarContainer.querySelector('.ql-insertProjectField').replaceWith(projectFieldDropdown);
    toolbarContainer.querySelector('.ql-insertExternalField').replaceWith(externalFieldDropdown);

    var hidden = document.getElementById('template-body');
    var urlConfig = {
        importTextUrl: '$importTextUrl',
        importMarkdownUrl: '$importMarkdownUrl'
    };
    var projectConfig = window.QuillToolbar.buildProjectConfig(
        'prompttemplate-project_id', window.templateProjectData || {},
        { nameInputId: 'prompttemplate-name', entityName: 'template' }
    );
    window.QuillToolbar.setupClearEditor(window.quill, hidden);
    window.QuillToolbar.setupSmartPaste(window.quill, hidden, urlConfig);
    window.QuillToolbar.setupLoadMd(window.quill, hidden, projectConfig);

    // Enable sticky/fixed toolbar on page scroll
    var editorContainer = document.querySelector('#editor').closest('.resizable-editor-container');
    if (editorContainer && window.QuillToolbar.setupFixedToolbar)
        window.QuillToolbar.setupFixedToolbar(editorContainer);

    function insertFieldText(dropdown) {
        var value = dropdown.value;
        if (value) {
            var position = window.quill.getSelection().index;
            window.quill.insertText(position, value);
            window.quill.setSelection(position + value.length);
            dropdown.value = '';
        }
    }

    generalFieldDropdown.addEventListener('change', function() {
        insertFieldText(this);
    });

    projectFieldDropdown.addEventListener('change', function() {
        insertFieldText(this);
    });

    externalFieldDropdown.addEventListener('change', function() {
        insertFieldText(this);
    });

    // Setup export toolbar button
    window.QuillToolbar.setupExportContent(window.quill, hidden, projectConfig);

    // Suggest name functionality
    document.getElementById('suggest-name-btn').addEventListener('click', function() {
        const btn = this;
        const nameInput = document.getElementById('prompttemplate-name');
        const content = window.quill.getText().trim();

        if (!content) {
            alert('Write some content first.');
            return;
        }

        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch('$suggestNameUrl', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ content: content })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.name)
                nameInput.value = data.name;
            else
                alert(data.error || 'Could not generate name.');
        })
        .catch(() => {
            alert('Request failed.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    });
    JS;

$this->registerJs($script);

$initialDeltaJson = $model->template_body ?: '{"ops":[{"insert":"\n"}]}';
$initialDeltaEncoded = json_encode($initialDeltaJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$script = <<<JS
    (function() {
        var deltaToLoad = $initialDeltaEncoded;
        try {
            var delta = typeof deltaToLoad === 'string' ? JSON.parse(deltaToLoad) : deltaToLoad;
            window.quill.setContents(delta);
        } catch (e) {
            console.error('Failed to parse delta:', e, deltaToLoad);
        }
    })();
    window.quill.on('text-change', function() {
        document.getElementById('template-body').value = JSON.stringify(window.quill.getContents());
    });
    JS;

$this->registerJs($script);
