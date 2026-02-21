<?php

use app\assets\AppAsset;
use common\enums\WorktreePurpose;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\Project $model */

$this->registerJsFile('@web/js/worktree-manager.js', ['depends' => [AppAsset::class]]);
?>

<div id="worktrees" class="mb-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Worktrees</strong>
            <button type="button" class="btn btn-sm btn-primary" id="btn-new-worktree"
                    onclick="WorktreeManager.openCreateModal()"
                    aria-label="Create a new worktree">
                <i class="bi bi-plus-lg"></i> New Worktree
            </button>
        </div>
        <div class="card-body" id="worktrees-list">
            <div class="d-flex justify-content-center py-3">
                <div class="spinner-border spinner-border-sm text-secondary" role="status">
                    <span class="visually-hidden">Loading worktrees...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Worktree Modal -->
<div class="modal fade" id="createWorktreeModal" tabindex="-1" aria-labelledby="createWorktreeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createWorktreeModalLabel">New Worktree</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" role="alert"></div>

                <div class="mb-3">
                    <label class="form-label fw-bold" id="wt-purpose-label">Start from</label>
                    <div class="btn-group w-100" role="group" aria-labelledby="wt-purpose-label">
                        <?php foreach (WorktreePurpose::cases() as $purpose): ?>
                            <input type="radio" class="btn-check" name="wt-purpose"
                                   id="wt-purpose-<?= Html::encode($purpose->value) ?>"
                                   value="<?= Html::encode($purpose->value) ?>"
                                   onchange="WorktreeManager.handlePurposeChange(this.value)">
                            <label class="btn btn-outline-primary" for="wt-purpose-<?= Html::encode($purpose->value) ?>">
                                <?= Html::encode($purpose->label()) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="wt-branch" class="form-label fw-bold">Branch</label>
                    <input type="text" class="form-control" id="wt-branch"
                           placeholder="e.g. feature/my-feature" maxlength="255"
                           pattern="[a-zA-Z0-9/_.\-]+" required>
                </div>

                <div class="mb-3">
                    <label for="wt-suffix" class="form-label fw-bold">Path Suffix</label>
                    <input type="text" class="form-control" id="wt-suffix"
                           placeholder="e.g. my-feature" maxlength="100"
                           pattern="[a-zA-Z0-9_\-]+" required>
                    <small class="text-muted">Worktree path: <code><?= Html::encode(rtrim($model->root_directory, '/')) ?>-<span id="wt-suffix-preview">...</span></code></small>
                </div>

                <div class="mb-3">
                    <label for="wt-source-branch" class="form-label fw-bold">Source Branch</label>
                    <input type="text" class="form-control" id="wt-source-branch"
                           value="main" placeholder="main" maxlength="255"
                           pattern="[a-zA-Z0-9/_.\-]+">
                    <small class="text-muted">Branch to sync with (merge target)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="wt-create-btn"
                        onclick="WorktreeManager.handleCreate()">
                    Create Worktree
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Remove Modal -->
<div class="modal fade" id="confirmRemoveModal" tabindex="-1" aria-labelledby="confirmRemoveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmRemoveModalLabel">Remove Worktree</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Remove worktree '<strong id="remove-worktree-path"></strong>'?</p>
                <p class="text-muted small">The worktree directory and local changes will be permanently deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="remove-confirm-btn">Remove</button>
            </div>
        </div>
    </div>
</div>

<?php
// Suffix preview live update
$this->registerJs(<<<'JS'
        document.getElementById('wt-suffix').addEventListener('input', function() {
            document.getElementById('wt-suffix-preview').textContent = this.value || '...';
        });
    JS);

// Initialize WorktreeManager
$this->registerJs("WorktreeManager.init(" . Json::encode([
    'container' => '#worktrees-list',
    'projectId' => $model->id,
    'urls' => [
        'status' => Url::to(['/worktree/status']),
        'create' => Url::to(['/worktree/create']),
        'sync' => Url::to(['/worktree/sync']),
        'remove' => Url::to(['/worktree/remove']),
        'recreate' => Url::to(['/worktree/recreate']),
        'cleanup' => Url::to(['/worktree/cleanup']),
    ],
]) . ");");
?>
