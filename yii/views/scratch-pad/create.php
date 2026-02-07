<?php
/** @noinspection BadExpressionStatementJS */
/** @noinspection JSUnresolvedReference */

use app\assets\QuillAsset;
use common\enums\CopyType;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\Project|null $currentProject */
/** @var array $projectList */

QuillAsset::register($this);

$this->title = 'Scratch Pad';
$this->params['breadcrumbs'][] = $this->title;
$copyTypes = CopyType::labels();
?>

<div class="scratch-pad-create container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-11 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0"><?= Html::encode($this->title) ?></h1>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group">
                        <?= Html::dropDownList('copyFormat', CopyType::MD->value, $copyTypes, [
                            'id' => 'copy-format-select',
                            'class' => 'form-select',
                            'style' => 'width: auto;',
                        ]) ?>
                        <button type="button" id="copy-content-btn" class="btn btn-primary text-nowrap" title="Copy to clipboard">
                            <i class="bi bi-clipboard"></i> Copy to Clipboard
                        </button>
                    </div>
                    <button type="button" id="save-content-btn" class="btn btn-primary text-nowrap" title="Save to database">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </div>
            <div class="accordion" id="scratchPadAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingContent">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapseContent" aria-expanded="true" aria-controls="collapseContent">
                            Content
                        </button>
                    </h2>
                    <div id="collapseContent" class="accordion-collapse collapse show" aria-labelledby="headingContent"
                         data-bs-parent="#scratchPadAccordion">
                        <div class="accordion-body p-0">
                            <div class="resizable-editor-container">
                                <div id="editor" class="resizable-editor" style="min-height: 300px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingResponse">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapseResponse" aria-expanded="false" aria-controls="collapseResponse">
                            Response
                        </button>
                    </h2>
                    <div id="collapseResponse" class="accordion-collapse collapse" aria-labelledby="headingResponse"
                         data-bs-parent="#scratchPadAccordion">
                        <div class="accordion-body p-0">
                            <div class="d-flex justify-content-end p-2 border-bottom">
                                <div class="input-group input-group-sm" style="width: auto;">
                                    <?= Html::dropDownList('responseCopyFormat', CopyType::MD->value, $copyTypes, [
                                        'id' => 'response-copy-format-select',
                                        'class' => 'form-select',
                                        'style' => 'width: auto;',
                                    ]) ?>
                                    <button type="button" id="copy-response-btn" class="btn btn-primary btn-sm text-nowrap" title="Copy to clipboard">
                                        <i class="bi bi-clipboard"></i> Copy
                                    </button>
                                </div>
                            </div>
                            <div class="resizable-editor-container">
                                <div id="response-editor" class="resizable-editor" style="min-height: 200px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Save Modal -->
<div class="modal fade" id="saveModal" tabindex="-1" aria-labelledby="saveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="saveModalLabel">Save Scratch Pad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="save-error-alert"></div>
                <div class="mb-3">
                    <label for="scratch-pad-name" class="form-label">Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="scratch-pad-name" placeholder="Enter a name...">
                        <button type="button" class="btn btn-outline-secondary" id="suggest-name-btn" title="Suggest name based on content">
                            <i class="bi bi-stars"></i> Suggest
                        </button>
                    </div>
                    <div class="invalid-feedback d-block d-none" id="scratch-pad-name-error"></div>
                </div>
                <div class="mb-3">
                    <label for="scratch-pad-project" class="form-label">Project</label>
                    <?= Html::dropDownList('project_id', $currentProject?->id, $projectList, [
                        'id' => 'scratch-pad-project',
                        'class' => 'form-select',
                        'prompt' => 'No project',
                    ]) ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-confirm-btn">Save</button>
            </div>
        </div>
    </div>
</div>

<label for="copy-content-hidden"></label><textarea id="copy-content-hidden" style="display: none;"></textarea>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="paste-toast" class="toast align-items-center text-bg-secondary border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<?php
$saveUrl = Url::to(['/scratch-pad/save']);
$savedListUrl = Url::to(['/scratch-pad/index']);
$importTextUrl = Url::to(['/scratch-pad/import-text']);
$importMarkdownUrl = Url::to(['/scratch-pad/import-markdown']);
$suggestNameUrl = Url::to(['/scratch-pad/suggest-name']);

$script = <<<JS
    window.quill = new Quill('#editor', {
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
                [{ 'loadMd': [] }]
            ]
        }
    });

    var urlConfig = {
        importTextUrl: '$importTextUrl',
        importMarkdownUrl: '$importMarkdownUrl'
    };
    window.QuillToolbar.setupClearEditor(window.quill, null);
    window.QuillToolbar.setupSmartPaste(window.quill, null, urlConfig);
    window.QuillToolbar.setupLoadMd(window.quill, null, urlConfig);

    // Response Quill editor
    window.responseQuill = new Quill('#response-editor', {
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
                [{ 'loadMd': [] }]
            ]
        }
    });

    window.QuillToolbar.setupClearEditor(window.responseQuill, null);
    window.QuillToolbar.setupSmartPaste(window.responseQuill, null, urlConfig);
    window.QuillToolbar.setupLoadMd(window.responseQuill, null, urlConfig);

    // Check for imported data in localStorage
    const importedData = localStorage.getItem('scratchPadContent');
    if (importedData) {
        try {
            const parsed = JSON.parse(importedData);
            if (parsed.content) {
                const delta = typeof parsed.content === 'string' ? JSON.parse(parsed.content) : parsed.content;
                window.quill.setContents(delta);
            }
            localStorage.removeItem('scratchPadContent');
        } catch (e) {
            console.error('Failed to parse imported data:', e);
        }
    }

    // Setup copy buttons
    window.QuillToolbar.setupCopyButton('copy-content-btn', 'copy-format-select', () => JSON.stringify(window.quill.getContents()));
    window.QuillToolbar.setupCopyButton('copy-response-btn', 'response-copy-format-select', () => JSON.stringify(window.responseQuill.getContents()));

    // Save functionality
    document.getElementById('save-content-btn').addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('saveModal'));
        document.getElementById('scratch-pad-name').value = '';
        document.getElementById('save-error-alert').classList.add('d-none');
        document.getElementById('scratch-pad-name').classList.remove('is-invalid');
        document.getElementById('scratch-pad-name-error').classList.add('d-none');
        modal.show();
    });

    document.getElementById('save-confirm-btn').addEventListener('click', function() {
        const nameInput = document.getElementById('scratch-pad-name');
        const name = nameInput.value.trim();
        const errorAlert = document.getElementById('save-error-alert');

        errorAlert.classList.add('d-none');
        nameInput.classList.remove('is-invalid');
        document.getElementById('scratch-pad-name-error').classList.add('d-none');

        if (!name) {
            nameInput.classList.add('is-invalid');
            document.getElementById('scratch-pad-name-error').textContent = 'Name is required.';
            document.getElementById('scratch-pad-name-error').classList.remove('d-none');
            return;
        }

        const projectSelect = document.getElementById('scratch-pad-project');
        const projectId = projectSelect.value || null;
        const deltaContent = JSON.stringify(window.quill.getContents());
        const responseContent = JSON.stringify(window.responseQuill.getContents());

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
                response: responseContent,
                project_id: projectId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('saveModal')).hide();
                window.location.href = '$savedListUrl';
            } else {
                if (data.errors) {
                    Object.entries(data.errors).forEach(([field, messages]) => {
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
            console.error('Save error:', error);
        });
    });

    document.getElementById('suggest-name-btn').addEventListener('click', function() {
        const btn = this;
        const nameInput = document.getElementById('scratch-pad-name');
        const errorDiv = document.getElementById('scratch-pad-name-error');
        const content = window.quill.getText().trim();

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
    JS;

$this->registerJs($script);
?>
