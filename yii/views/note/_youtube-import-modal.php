<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\Project|null $currentProject */
/** @var array $projectList */
?>

<div class="modal fade" id="youtubeImportModal" tabindex="-1" aria-labelledby="youtubeImportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="youtubeImportModalLabel">Import from YouTube</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="youtube-import-form">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="youtube-import-error-alert"></div>

                    <div class="mb-3">
                        <label for="youtube-video-id" class="form-label">YouTube Video ID or URL <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="youtube-video-id"
                               name="videoId"
                               placeholder="e.g., dQw4w9WgXcQ or https://www.youtube.com/watch?v=...">
                        <div class="form-text">Supports youtube.com, youtu.be, and embed URLs</div>
                        <div class="invalid-feedback" id="youtube-video-id-error"></div>
                    </div>

                    <div class="mb-3">
                        <label for="youtube-import-project" class="form-label">Project</label>
                        <?= Html::dropDownList('project_id', $currentProject?->id, $projectList, [
                            'id' => 'youtube-import-project',
                            'class' => 'form-select',
                            'prompt' => 'No project',
                        ]) ?>
                        <div class="form-text">Optionally associate with a project</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="youtube-import-submit-btn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Import Transcript
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$importUrl = Url::to(['/note/import-youtube']);
$viewUrlBase = '/note/view';
$js = <<<JS
    document.getElementById('youtube-import-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const form = this;
        const submitBtn = document.getElementById('youtube-import-submit-btn');
        const spinner = submitBtn.querySelector('.spinner-border');
        const errorAlert = document.getElementById('youtube-import-error-alert');

        // Clear previous errors
        errorAlert.classList.add('d-none');
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        // Validate required fields
        const videoIdInput = document.getElementById('youtube-video-id');
        const videoId = videoIdInput.value.trim();
        if (!videoId) {
            videoIdInput.classList.add('is-invalid');
            document.getElementById('youtube-video-id-error').textContent = 'Please enter a YouTube video ID or URL.';
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        spinner.classList.remove('d-none');

        const projectId = document.getElementById('youtube-import-project').value || null;

        fetch('$importUrl', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                videoId: videoId,
                project_id: projectId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '$viewUrlBase' + '?id=' + data.id;
            } else {
                if (data.errors) {
                    if (data.errors.videoId) {
                        videoIdInput.classList.add('is-invalid');
                        document.getElementById('youtube-video-id-error').textContent = data.errors.videoId[0];
                    } else {
                        // Show first error in alert
                        const firstError = Object.values(data.errors)[0];
                        errorAlert.textContent = Array.isArray(firstError) ? firstError[0] : firstError;
                        errorAlert.classList.remove('d-none');
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

    document.getElementById('youtubeImportModal').addEventListener('hidden.bs.modal', function() {
        const form = document.getElementById('youtube-import-form');
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.getElementById('youtube-import-error-alert').classList.add('d-none');
    });
    JS;
$this->registerJs($js);
?>
