<?php
/** @noinspection JSUnresolvedReference */

use app\assets\QuillAsset;
use common\enums\NoteType;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Note $model */
/** @var array $projectList */
/** @var app\models\Note[] $children */

QuillAsset::register($this);
$isUpdate = !$model->isNewRecord;
$children ??= [];
?>

<div class="note-form focus-on-first-field">
    <?php $form = ActiveForm::begin(['id' => 'note-form']); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'project_id')->dropDownList(
        $projectList,
        ['prompt' => '(not set)']
    )->label('Project') ?>

    <?= $form->field($model, 'content')->hiddenInput(['id' => 'note-content'])->label(false) ?>

    <div class="accordion mb-3" id="noteAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingContent">
                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseContent" aria-expanded="true" aria-controls="collapseContent">
                    Content
                </button>
            </h2>
            <div id="collapseContent" class="accordion-collapse collapse show" aria-labelledby="headingContent"
                 data-bs-parent="#noteAccordion">
                <div class="accordion-body p-0">
                    <div class="resizable-editor-container">
                        <div id="note-editor" class="resizable-editor" style="min-height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isUpdate && !empty($children)): ?>
    <div class="card mb-3">
        <div class="card-header"><strong>Children</strong></div>
        <div class="card-body">
            <?php foreach ($children as $child):
                $childType = NoteType::resolve($child->type);
                ?>
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                    <span class="badge bg-<?= $childType === NoteType::SUMMATION ? 'info' : ($childType === NoteType::IMPORT ? 'warning text-dark' : 'secondary') ?> me-2">
                        <?= Html::encode($childType?->label() ?? $child->type) ?>
                    </span>
                    <?= Html::a(Html::encode($child->name), ['/note/update', 'id' => $child->id]) ?>
                </div>
                <small class="text-muted"><?= Yii::$app->formatter->asDatetime($child->updated_at, 'php:Y-m-d H:i') ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', Yii::$app->request->referrer ?: ['index'], ['class' => 'btn btn-secondary me-2']) ?>
        <?php if ($isUpdate): ?>
        <button type="button" id="save-as-btn" class="btn btn-primary me-2">Save As</button>
        <?php endif; ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php if ($isUpdate): ?>
<!-- Save As Modal -->
<div class="modal fade" id="saveAsModal" tabindex="-1" aria-labelledby="saveAsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="saveAsModalLabel">Save As</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="save-as-error-alert"></div>
                <div class="mb-3">
                    <label for="save-as-name" class="form-label">New Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="save-as-name" value="<?= Html::encode($model->name) ?> (copy)" placeholder="Enter a name...">
                        <button type="button" class="btn btn-outline-secondary" id="suggest-as-name-btn" title="Suggest name based on content">
                            <i class="bi bi-stars"></i> Suggest
                        </button>
                    </div>
                    <div class="invalid-feedback d-block d-none" id="save-as-name-error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-as-confirm-btn">Save</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="paste-toast" class="toast align-items-center text-bg-secondary border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<?php
$content = json_encode($model->content);
$saveUrl = Url::to(['/note/save']);
$updateUrlTemplate = Url::to(['/note/update', 'id' => '__ID__']);
$importTextUrl = Url::to(['/note/import-text']);
$importMarkdownUrl = Url::to(['/note/import-markdown']);
$suggestNameUrl = Url::to(['/claude/suggest-name']);

// Build project data for export (need to know which projects have root_directory)
$projectDataForExport = [];
foreach ($projectList as $projectId => $projectName) {
    $project = \app\models\Project::find()->findUserProject($projectId, Yii::$app->user->id);
    $projectDataForExport[$projectId] = [
        'hasRoot' => $project && !empty($project->root_directory),
        'rootDirectory' => $project->root_directory ?? null,
    ];
}
$projectDataJson = json_encode($projectDataForExport);

$script = <<<JS
    window.noteProjectData = $projectDataJson;
    var quill = new Quill('#note-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike', 'code'],
                ['blockquote', 'code-block'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'indent': '-1' }, { 'indent': '+1' }],
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                [{ 'align': [] }],
                ['clean'],
                [{ 'clearEditor': [] }],
                [{ 'smartPaste': [] }],
                [{ 'loadMd': [] }],
                [{ 'exportContent': [] }]
            ]
        }
    });

    var hidden = document.getElementById('note-content');
    var urlConfig = {
        importTextUrl: '$importTextUrl',
        importMarkdownUrl: '$importMarkdownUrl'
    };
    window.QuillToolbar.setupClearEditor(quill, hidden);
    window.QuillToolbar.setupSmartPaste(quill, hidden, urlConfig);
    window.QuillToolbar.setupLoadMd(quill, hidden, urlConfig);

    // Enable sticky/fixed toolbar on page scroll
    var contentContainer = document.querySelector('#note-editor').closest('.resizable-editor-container');
    if (contentContainer && window.QuillToolbar.setupFixedToolbar)
        window.QuillToolbar.setupFixedToolbar(contentContainer);

    try {
        quill.setContents(JSON.parse($content))
    } catch (error) {
        console.error('Error loading content:', error);
    }

    quill.on('text-change', function() {
        hidden.value = JSON.stringify(quill.getContents());
    });

    // Setup export toolbar button
    var projectSelect = document.getElementById('note-project_id');
    var projectData = window.noteProjectData || {};
    window.QuillToolbar.setupExportContent(quill, hidden, {
        getProjectId: () => projectSelect ? projectSelect.value : null,
        getEntityName: () => document.getElementById('note-name')?.value || 'export',
        getHasRoot: () => {
            var selectedProjectId = projectSelect ? projectSelect.value : null;
            var projectInfo = selectedProjectId ? (projectData[selectedProjectId] || {}) : {};
            return !!projectInfo.hasRoot;
        },
        getRootDirectory: () => {
            var selectedProjectId = projectSelect ? projectSelect.value : null;
            var projectInfo = selectedProjectId ? (projectData[selectedProjectId] || {}) : {};
            return projectInfo.rootDirectory || null;
        }
    });

    // Save As functionality
    const saveAsBtn = document.getElementById('save-as-btn');
    if (saveAsBtn) {
        saveAsBtn.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('saveAsModal'));
            document.getElementById('save-as-error-alert').classList.add('d-none');
            document.getElementById('save-as-name').classList.remove('is-invalid');
            document.getElementById('save-as-name-error').classList.add('d-none');
            modal.show();
        });

        document.getElementById('save-as-confirm-btn').addEventListener('click', function() {
            const nameInput = document.getElementById('save-as-name');
            const name = nameInput.value.trim();
            const errorAlert = document.getElementById('save-as-error-alert');

            errorAlert.classList.add('d-none');
            nameInput.classList.remove('is-invalid');
            document.getElementById('save-as-name-error').classList.add('d-none');

            if (!name) {
                nameInput.classList.add('is-invalid');
                document.getElementById('save-as-name-error').textContent = 'Name is required.';
                document.getElementById('save-as-name-error').classList.remove('d-none');
                return;
            }

            const deltaContent = JSON.stringify(quill.getContents());

            fetch('$saveUrl', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    name: name,
                    content: deltaContent,
                    project_id: document.getElementById('note-project_id')?.value || null
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('saveAsModal')).hide();
                    window.location.href = '$updateUrlTemplate'.replace('__ID__', data.id);
                } else {
                    if (data.errors) {
                        Object.entries(data.errors).forEach(([, messages]) => {
                            errorAlert.textContent = messages[0];
                            errorAlert.classList.remove('d-none');
                        });
                    } else if (data.message) {
                        errorAlert.textContent = data.message;
                        errorAlert.classList.remove('d-none');
                    }
                }
            })
            .catch(error => {
                errorAlert.textContent = 'An unexpected error occurred.';
                errorAlert.classList.remove('d-none');
                console.error('Save As error:', error);
            });
        });

        document.getElementById('suggest-as-name-btn').addEventListener('click', function() {
            const btn = this;
            const nameInput = document.getElementById('save-as-name');
            const errorDiv = document.getElementById('save-as-name-error');
            const content = quill.getText().trim();

            errorDiv.classList.add('d-none');

            if (!content) {
                errorDiv.textContent = 'Write some content first.';
                errorDiv.classList.remove('d-none');
                return;
            }

            btn.disabled = true;
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
                    nameInput.classList.remove('is-invalid');
                    errorDiv.classList.add('d-none');
                } else {
                    errorDiv.textContent = data.error || 'Could not generate name.';
                    errorDiv.classList.remove('d-none');
                }
            })
            .catch(() => {
                errorDiv.textContent = 'Request failed.';
                errorDiv.classList.remove('d-none');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-stars"></i> Suggest';
            });
        });
    }
    JS;

$this->registerJs($script);
?>
