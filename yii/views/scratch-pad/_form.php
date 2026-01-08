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

    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label mb-0">Content</label>
        <div class="d-flex align-items-center gap-2">
            <button type="button" id="paste-md-btn" class="btn btn-sm btn-primary text-nowrap" title="Paste from clipboard">
                <i class="bi bi-clipboard-plus"></i> Smart Paste
            </button>
            <div class="input-group input-group-sm" style="width: auto;">
                <?= Html::dropDownList('copyFormat', CopyType::MD->value, $copyTypes, [
                    'id' => 'copy-format-select',
                    'class' => 'form-select',
                    'style' => 'width: auto;',
                ]) ?>
                <button type="button" id="copy-content-btn" class="btn btn-primary text-nowrap" title="Copy to clipboard">
                    <i class="bi bi-clipboard"></i> Copy to Clipboard
                </button>
            </div>
        </div>
    </div>

    <div class="resizable-editor-container mb-3">
        <div id="scratch-pad-editor" class="resizable-editor" style="min-height: 300px;"></div>
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
                    <input type="text" class="form-control" id="save-as-name" value="<?= Html::encode($model->name) ?> (copy)" placeholder="Enter a name...">
                    <div class="invalid-feedback" id="save-as-name-error"></div>
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
$saveUrl = Url::to(['/scratch-pad/save']);
$script = <<<JS
    const Delta = Quill.import('delta');
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
                ['clean']
            ]
        }
    });

    try {
        quill.setContents(JSON.parse($content))
    } catch (error) {
        console.error('Error loading content:', error);
    }

    quill.on('text-change', function() {
        document.querySelector('#scratch-pad-content').value = JSON.stringify(quill.getContents());
    });

    // Toast helper
    function showToast(message) {
        const toastEl = document.getElementById('paste-toast');
        toastEl.querySelector('.toast-body').textContent = message;
        const toast = new bootstrap.Toast(toastEl, {delay: 2000});
        toast.show();
    }

    // Paste from clipboard functionality
    document.getElementById('paste-md-btn').addEventListener('click', async function() {
        const btn = this;
        const originalText = btn.innerHTML;

        try {
            const text = await navigator.clipboard.readText();
            if (!text.trim()) {
                showToast('Clipboard is empty');
                return;
            }

            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            btn.disabled = true;

            const response = await fetch('/scratch-pad/import-text', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ text: text })
            });

            const data = await response.json();

            if (data.success && data.importData && data.importData.content) {
                const delta = typeof data.importData.content === 'string'
                    ? JSON.parse(data.importData.content)
                    : data.importData.content;
                const length = quill.getLength();
                if (length <= 1) {
                    quill.setContents(delta);
                } else {
                    const range = quill.getSelection(true);
                    quill.updateContents(new Delta().retain(range.index).concat(delta));
                }
                document.querySelector('#scratch-pad-content').value = JSON.stringify(quill.getContents());
                showToast(data.format === 'md' ? 'Pasted as markdown' : 'Pasted as text');
            } else {
                showToast(data.message || 'Failed to paste content');
            }
        } catch (err) {
            console.error('Paste error:', err);
            showToast('Unable to read clipboard');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // Copy functionality with format conversion
    document.getElementById('copy-content-btn').addEventListener('click', function() {
        const formatSelect = document.getElementById('copy-format-select');
        const selectedFormat = formatSelect.value;
        const deltaContent = JSON.stringify(quill.getContents());

        fetch('/scratch-pad/convert-format', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                content: deltaContent,
                format: selectedFormat
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.content !== undefined) {
                navigator.clipboard.writeText(data.content).then(function() {
                    const btn = document.getElementById('copy-content-btn');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-check"></i> Copied';
                    setTimeout(function() {
                        btn.innerHTML = originalText;
                    }, 1000);
                });
            } else {
                console.error('Failed to convert format:', data.message);
                const text = quill.getText();
                navigator.clipboard.writeText(text);
            }
        })
        .catch(function(err) {
            console.error('Failed to copy:', err);
            const text = quill.getText();
            navigator.clipboard.writeText(text);
        });
    });

    // Save As functionality
    const saveAsBtn = document.getElementById('save-as-btn');
    if (saveAsBtn) {
        saveAsBtn.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('saveAsModal'));
            document.getElementById('save-as-error-alert').classList.add('d-none');
            document.getElementById('save-as-name').classList.remove('is-invalid');
            modal.show();
        });

        document.getElementById('save-as-confirm-btn').addEventListener('click', function() {
            const nameInput = document.getElementById('save-as-name');
            const name = nameInput.value.trim();
            const errorAlert = document.getElementById('save-as-error-alert');

            errorAlert.classList.add('d-none');
            nameInput.classList.remove('is-invalid');

            if (!name) {
                nameInput.classList.add('is-invalid');
                document.getElementById('save-as-name-error').textContent = 'Name is required.';
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
    }
    JS;

$this->registerJs($script);
?>
