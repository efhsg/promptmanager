<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
?>

<div class="modal fade" id="scratchPadImportModal" tabindex="-1" aria-labelledby="scratchPadImportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scratchPadImportModalLabel">Import Markdown</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="scratch-pad-import-form" enctype="multipart/form-data">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="scratch-pad-import-error-alert"></div>

                    <div class="mb-3">
                        <label for="scratch-pad-import-file" class="form-label">Markdown File <span class="text-danger">*</span></label>
                        <input type="file"
                               class="form-control"
                               id="scratch-pad-import-file"
                               name="mdFile"
                               accept=".md,.markdown,.txt">
                        <div class="form-text">Accepted formats: .md, .markdown, .txt (max 1MB)</div>
                        <div class="invalid-feedback" id="scratch-pad-import-file-error"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="scratch-pad-import-submit-btn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$importUrl = Url::to(['/scratch-pad/import-markdown']);
$editorUrl = Url::to(['/scratch-pad/create']);
$js = <<<JS
    document.getElementById('scratch-pad-import-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const form = this;
        const submitBtn = document.getElementById('scratch-pad-import-submit-btn');
        const spinner = submitBtn.querySelector('.spinner-border');
        const errorAlert = document.getElementById('scratch-pad-import-error-alert');

        // Clear previous errors
        errorAlert.classList.add('d-none');
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        // Validate required fields
        const file = document.getElementById('scratch-pad-import-file');
        if (!file.files.length) {
            file.classList.add('is-invalid');
            document.getElementById('scratch-pad-import-file-error').textContent = 'Please select a file.';
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        spinner.classList.remove('d-none');

        const formData = new FormData(form);

        fetch('$importUrl', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Store import data in localStorage for the editor page to pick up
                if (data.importData) {
                    localStorage.setItem('scratchPadContent', JSON.stringify(data.importData));
                }
                window.location.href = '$editorUrl';
            } else {
                if (data.errors) {
                    if (data.errors.mdFile) {
                        file.classList.add('is-invalid');
                        document.getElementById('scratch-pad-import-file-error').textContent = data.errors.mdFile[0];
                    }
                } else if (data.message) {
                    errorAlert.textContent = data.message;
                    errorAlert.classList.remove('d-none');
                }
            }
        })
        .catch(error => {
            errorAlert.textContent = 'An unexpected error occurred. Please try again.';
            errorAlert.classList.remove('d-none');
            console.error('Import error:', error);
        })
        .finally(() => {
            submitBtn.disabled = false;
            spinner.classList.add('d-none');
        });
    });

    document.getElementById('scratchPadImportModal').addEventListener('hidden.bs.modal', function() {
        const form = document.getElementById('scratch-pad-import-form');
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.getElementById('scratch-pad-import-error-alert').classList.add('d-none');
    });
    JS;
$this->registerJs($js);
?>
