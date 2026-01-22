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
                    <h2 class="accordion-header" id="headingSummation">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapseSummation" aria-expanded="false" aria-controls="collapseSummation">
                            Summation
                        </button>
                    </h2>
                    <div id="collapseSummation" class="accordion-collapse collapse" aria-labelledby="headingSummation"
                         data-bs-parent="#scratchPadAccordion">
                        <div class="accordion-body p-0">
                            <div class="d-flex justify-content-end p-2 border-bottom">
                                <div class="input-group input-group-sm" style="width: auto;">
                                    <?= Html::dropDownList('summationCopyFormat', CopyType::MD->value, $copyTypes, [
                                        'id' => 'summation-copy-format-select',
                                        'class' => 'form-select',
                                        'style' => 'width: auto;',
                                    ]) ?>
                                    <button type="button" id="copy-summation-btn" class="btn btn-primary btn-sm text-nowrap" title="Copy to clipboard">
                                        <i class="bi bi-clipboard"></i> Copy
                                    </button>
                                </div>
                            </div>
                            <div class="resizable-editor-container">
                                <div id="summation-editor" class="resizable-editor" style="min-height: 200px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 text-muted small">
                Quick edit imported markdown content. Use Copy to copy to clipboard or Save to store in the database.
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
                    <input type="text" class="form-control" id="scratch-pad-name" placeholder="Enter a name...">
                    <div class="invalid-feedback" id="scratch-pad-name-error"></div>
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
                [{ 'smartPaste': [] }],
                [{ 'loadMd': [] }]
            ]
        }
    });

    var urlConfig = {
        importTextUrl: '$importTextUrl',
        importMarkdownUrl: '$importMarkdownUrl'
    };
    window.QuillToolbar.setupSmartPaste(window.quill, null, urlConfig);
    window.QuillToolbar.setupLoadMd(window.quill, null, urlConfig);

    // Summation Quill editor
    window.summationQuill = new Quill('#summation-editor', {
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
                [{ 'smartPaste': [] }],
                [{ 'loadMd': [] }]
            ]
        }
    });

    window.QuillToolbar.setupSmartPaste(window.summationQuill, null, urlConfig);
    window.QuillToolbar.setupLoadMd(window.summationQuill, null, urlConfig);

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
    window.QuillToolbar.setupCopyButton('copy-summation-btn', 'summation-copy-format-select', () => JSON.stringify(window.summationQuill.getContents()));

    // Save functionality
    document.getElementById('save-content-btn').addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('saveModal'));
        document.getElementById('scratch-pad-name').value = '';
        document.getElementById('save-error-alert').classList.add('d-none');
        document.getElementById('scratch-pad-name').classList.remove('is-invalid');
        modal.show();
    });

    document.getElementById('save-confirm-btn').addEventListener('click', function() {
        const nameInput = document.getElementById('scratch-pad-name');
        const name = nameInput.value.trim();
        const errorAlert = document.getElementById('save-error-alert');

        errorAlert.classList.add('d-none');
        nameInput.classList.remove('is-invalid');

        if (!name) {
            nameInput.classList.add('is-invalid');
            document.getElementById('scratch-pad-name-error').textContent = 'Name is required.';
            return;
        }

        const projectSelect = document.getElementById('scratch-pad-project');
        const projectId = projectSelect.value || null;
        const deltaContent = JSON.stringify(window.quill.getContents());
        const summationContent = JSON.stringify(window.summationQuill.getContents());

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
                summation: summationContent,
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
    JS;

$this->registerJs($script);
?>
