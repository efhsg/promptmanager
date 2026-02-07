<?php
/** @noinspection JSUnresolvedReference */

use app\assets\QuillAsset;
use common\enums\CopyType;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\ScratchPad $model */
/** @var array $projectList */

QuillAsset::register($this);
$copyTypes = CopyType::labels();
$isUpdate = !$model->isNewRecord;
?>

<div class="scratch-pad-form focus-on-first-field">
    <?php $form = ActiveForm::begin(['id' => 'scratch-pad-form']); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'project_id')->dropDownList(
        $projectList,
        ['prompt' => '(not set)']
    )->label('Project') ?>

    <?= $form->field($model, 'content')->hiddenInput(['id' => 'scratch-pad-content'])->label(false) ?>
    <?= $form->field($model, 'response')->hiddenInput(['id' => 'scratch-pad-response'])->label(false) ?>

    <div class="accordion mb-3" id="scratchPadAccordion">
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
                    <div class="d-flex justify-content-end p-2 border-bottom">
                        <div class="input-group input-group-sm" style="width: auto;">
                            <?= Html::dropDownList('copyFormat', CopyType::MD->value, $copyTypes, [
                                'id' => 'copy-format-select',
                                'class' => 'form-select',
                                'style' => 'width: auto;',
                            ]) ?>
                            <button type="button" id="copy-content-btn" class="btn btn-primary btn-sm text-nowrap" title="Copy to clipboard">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div class="resizable-editor-container">
                        <div id="scratch-pad-editor" class="resizable-editor" style="min-height: 300px;"></div>
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
                        <div id="scratch-pad-response-editor" class="resizable-editor" style="min-height: 200px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
$response = json_encode($model->response);
$saveUrl = Url::to(['/scratch-pad/save']);
$importTextUrl = Url::to(['/scratch-pad/import-text']);
$importMarkdownUrl = Url::to(['/scratch-pad/import-markdown']);
$suggestNameUrl = Url::to(['/scratch-pad/suggest-name']);
$script = <<<JS
    var quill = new Quill('#scratch-pad-editor', {
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

    var hidden = document.getElementById('scratch-pad-content');
    var urlConfig = {
        importTextUrl: '$importTextUrl',
        importMarkdownUrl: '$importMarkdownUrl'
    };
    window.QuillToolbar.setupClearEditor(quill, hidden);
    window.QuillToolbar.setupSmartPaste(quill, hidden, urlConfig);
    window.QuillToolbar.setupLoadMd(quill, hidden, urlConfig);

    // Enable sticky/fixed toolbar on page scroll
    var contentContainer = document.querySelector('#scratch-pad-editor').closest('.resizable-editor-container');
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

    // Response Quill editor
    var responseQuill = new Quill('#scratch-pad-response-editor', {
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

    var responseHidden = document.getElementById('scratch-pad-response');
    window.QuillToolbar.setupClearEditor(responseQuill, responseHidden);
    window.QuillToolbar.setupSmartPaste(responseQuill, responseHidden, urlConfig);
    window.QuillToolbar.setupLoadMd(responseQuill, responseHidden, urlConfig);

    // Enable sticky/fixed toolbar on page scroll
    var responseContainer = document.querySelector('#scratch-pad-response-editor').closest('.resizable-editor-container');
    if (responseContainer && window.QuillToolbar.setupFixedToolbar)
        window.QuillToolbar.setupFixedToolbar(responseContainer);

    try {
        responseQuill.setContents(JSON.parse($response))
    } catch (error) {
        console.error('Error loading response content:', error);
    }

    responseQuill.on('text-change', function() {
        responseHidden.value = JSON.stringify(responseQuill.getContents());
    });

    // Setup copy buttons
    window.QuillToolbar.setupCopyButton('copy-content-btn', 'copy-format-select', () => JSON.stringify(quill.getContents()));
    window.QuillToolbar.setupCopyButton('copy-response-btn', 'response-copy-format-select', () => JSON.stringify(responseQuill.getContents()));

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
            const responseContent = JSON.stringify(responseQuill.getContents());

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
                    project_id: document.getElementById('scratchpad-project_id')?.value || null
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('saveAsModal')).hide();
                    window.location.href = '/scratch-pad/update?id=' + data.id;
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
