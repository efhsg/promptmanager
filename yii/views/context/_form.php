<?php /** @noinspection BadExpressionStatementJS */

/** @noinspection JSUnresolvedReference */

use app\assets\QuillAsset;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Context $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $projects */

QuillAsset::register($this);
?>

    <div class="context-form focus-on-first-field">
        <?php $form = ActiveForm::begin([
            'id' => 'context-form',
            'enableClientValidation' => true,
        ]); ?>

        <?= $form->field($model, 'project_id')->dropDownList(
            $projects,
            ['prompt' => 'Select a Project']
        )->label('Project') ?>

        <?= $form->field($model, 'name', [
            'template' => "{label}\n<div class=\"input-group\">{input}<button type=\"button\" class=\"btn btn-outline-secondary\" id=\"suggest-name-btn\" title=\"Suggest name based on content\"><i class=\"bi bi-stars\"></i></button></div>\n{hint}\n{error}",
        ])->textInput(['maxlength' => true])->label('Context Name') ?>

        <?= $form->field($model, 'is_default')->dropDownList(
            [0 => 'No', 1 => 'Yes']
        )->label('Default on') ?>

        <?= $form->field($model, 'share')->dropDownList(
            [0 => 'No', 1 => 'Yes']
        )->label('Share') ?>

        <?= $form->field($model, 'order')->textInput(['type' => 'number'])->label('Order') ?>

        <?= $form->field($model, 'content')->hiddenInput(['id' => 'context-content'])->label(false) ?>

        <label class="form-label">Content</label>
        <div class="resizable-editor-container mb-3">
            <div id="editor" class="resizable-editor">
                <?= $model->content ?>
            </div>
        </div>

        <div class="form-group mt-4 text-end">
            <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
            <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>

<?php
$templateBody = json_encode($model->content);
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
    window.contextProjectData = $projectDataJson;

    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike', 'code'],
                ['blockquote', 'code-block'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'indent': '-1' }, { 'indent': '+1' }],
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'align': [] }],
                ['clean'],
                [{ 'clearEditor': [] }],
                [{ 'smartPaste': [] }],
                [{ 'loadMd': [] }],
                [{ 'exportContent': [] }]
            ]
        }
    });

    var hidden = document.getElementById('context-content');
    var urlConfig = {
        importTextUrl: '$importTextUrl',
        importMarkdownUrl: '$importMarkdownUrl'
    };
    var projectConfig = window.QuillToolbar.buildProjectConfig(
        'context-project_id', window.contextProjectData || {},
        { nameInputId: 'context-name', entityName: 'context' }
    );
    window.QuillToolbar.setupClearEditor(quill, hidden);
    window.QuillToolbar.setupSmartPaste(quill, hidden, urlConfig);
    window.QuillToolbar.setupLoadMd(quill, hidden, projectConfig);

    // Enable sticky/fixed toolbar on page scroll
    var editorContainer = document.querySelector('#editor').closest('.resizable-editor-container');
    if (editorContainer && window.QuillToolbar.setupFixedToolbar)
        window.QuillToolbar.setupFixedToolbar(editorContainer);

    try {
        quill.setContents(JSON.parse($templateBody))
    } catch (error) {
        console.error('Error injecting template body:', error)
    }
    quill.on('text-change', function() {
        hidden.value = JSON.stringify(quill.getContents())
    });

    // Setup export toolbar button
    window.QuillToolbar.setupExportContent(quill, hidden, projectConfig);

    // Suggest name functionality
    document.getElementById('suggest-name-btn').addEventListener('click', function() {
        const btn = this;
        const nameInput = document.getElementById('context-name');
        const content = quill.getText().trim();

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
            if (data.success && data.name) {
                nameInput.value = data.name;
            } else {
                alert(data.error || 'Could not generate name.');
            }
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
?>
