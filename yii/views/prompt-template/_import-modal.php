<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var array $projects */
/** @var int|null $currentProjectId */
?>

<div class="modal fade" id="importMarkdownModal" tabindex="-1" aria-labelledby="importMarkdownModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importMarkdownModalLabel">Import Markdown Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="import-markdown-form" enctype="multipart/form-data">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="import-error-alert"></div>

                    <div class="mb-3">
                        <label for="import-project-id" class="form-label">Project <span class="text-danger">*</span></label>
                        <?= Html::dropDownList(
                            'MarkdownImportForm[project_id]',
                            $currentProjectId,
                            $projects,
                            [
                                'id' => 'import-project-id',
                                'class' => 'form-select',
                                'prompt' => 'Select a Project',
                            ]
                        ) ?>
                        <div class="invalid-feedback" id="import-project-error"></div>
                    </div>

                    <div class="mb-3">
                        <label for="import-name" class="form-label">Template Name</label>
                        <input type="text"
                               class="form-control"
                               id="import-name"
                               name="MarkdownImportForm[name]"
                               maxlength="255"
                               placeholder="Enter template name">
                        <div class="invalid-feedback" id="import-name-error"></div>
                    </div>

                    <div class="mb-3">
                        <label for="import-file" class="form-label">Markdown File <span class="text-danger">*</span></label>
                        <input type="file"
                               class="form-control"
                               id="import-file"
                               name="MarkdownImportForm[mdFile]"
                               accept=".md,.markdown,.txt">
                        <div class="form-text">Accepted formats: .md, .markdown, .txt (max 1MB)</div>
                        <div class="invalid-feedback" id="import-file-error"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="import-submit-btn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$importUrl = Url::to(['import-markdown']);
$js = <<<JS
    document.getElementById('import-markdown-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const form = this;
        const submitBtn = document.getElementById('import-submit-btn');
        const spinner = submitBtn.querySelector('.spinner-border');
        const errorAlert = document.getElementById('import-error-alert');

        // Clear previous errors
        errorAlert.classList.add('d-none');
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        // Validate required fields
        let hasErrors = false;
        const projectId = document.getElementById('import-project-id');
        const file = document.getElementById('import-file');

        if (!projectId.value) {
            projectId.classList.add('is-invalid');
            document.getElementById('import-project-error').textContent = 'Project is required.';
            hasErrors = true;
        }
        if (!file.files.length) {
            file.classList.add('is-invalid');
            document.getElementById('import-file-error').textContent = 'Please select a file.';
            hasErrors = true;
        }

        if (hasErrors) return;

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
                // Store import data in localStorage for the create page to pick up
                if (data.importData) {
                    localStorage.setItem('importedTemplate', JSON.stringify(data.importData));
                }
                window.location.href = data.redirectUrl;
            } else {
                if (data.errors) {
                    const fieldMap = {
                        'project_id': ['import-project-id', 'import-project-error'],
                        'name': ['import-name', 'import-name-error'],
                        'mdFile': ['import-file', 'import-file-error']
                    };
                    Object.keys(data.errors).forEach(field => {
                        if (fieldMap[field]) {
                            const [inputId, errorId] = fieldMap[field];
                            const input = document.getElementById(inputId);
                            input.classList.add('is-invalid');
                            document.getElementById(errorId).textContent = data.errors[field][0];
                        }
                    });
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

    document.getElementById('importMarkdownModal').addEventListener('hidden.bs.modal', function() {
        const form = document.getElementById('import-markdown-form');
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.getElementById('import-error-alert').classList.add('d-none');
    });
    JS;
$this->registerJs($js);
?>
