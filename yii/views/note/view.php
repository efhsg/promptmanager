<?php

use app\widgets\QuillViewerWidget;
use common\enums\CopyType;
use common\enums\NoteType;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Note $model */
/** @var app\models\Note[] $children */

$copyTypes = CopyType::labels();
$canRunClaude = $model->project_id !== null;
$claudeTooltip = $canRunClaude ? 'Talk to Claude' : 'Project required';
$claudeUrl = $canRunClaude
    ? Url::to(['/claude/index', 'p' => $model->project_id, 'breadcrumbs' => json_encode([
        ['label' => 'Notes', 'url' => Url::to(['/note/index'])],
        ['label' => $model->name, 'url' => Url::to(['/note/view', 'id' => $model->id])],
    ])])
    : '#';
$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Notes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->name;
$children ??= [];
$fetchContentUrl = Url::to(['/note/fetch-content', 'id' => $model->id]);
?>

<div class="note-view container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <h1 class="h3 mb-0 me-3"><?= Html::encode($model->name) ?></h1>
        <div class="d-flex flex-shrink-0">
            <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary me-2']) ?>
            <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => ['method' => 'post'],
            ]) ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Details</strong></div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'options' => ['class' => 'table table-borderless'],
                'attributes' => [
                    'name',
                    [
                        'attribute' => 'type',
                        'format' => 'raw',
                        'value' => function () use ($model) {
                            $noteType = NoteType::resolve($model->type);
                            return $noteType ? $noteType->label() : $model->type;
                        },
                    ],
                    [
                        'attribute' => 'project_id',
                        'label' => 'Scope',
                        'value' => $model->project ? $model->project->name : 'Global',
                    ],
                    [
                        'attribute' => 'created_at',
                        'format' => ['datetime', 'php:Y-m-d H:i:s'],
                    ],
                    [
                        'attribute' => 'updated_at',
                        'format' => ['datetime', 'php:Y-m-d H:i:s'],
                    ],
                ],
            ]) ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Content</strong>
            <div class="d-flex gap-2">
                <?= Html::button('<i class="bi bi-terminal-fill"></i> Claude', [
                    'class' => 'btn btn-primary btn-sm text-nowrap claude-launch-btn' . (!$canRunClaude ? ' disabled' : ''),
                    'title' => $claudeTooltip ?: null,
                    'data-bs-toggle' => $claudeTooltip ? 'tooltip' : null,
                    'data-delta' => 'content',
                    'disabled' => !$canRunClaude,
                ]) ?>
                <?php if (!empty($children)): ?>
                <?= Html::button('<i class="bi bi-terminal-fill"></i> Claude (all)', [
                    'class' => 'btn btn-outline-primary btn-sm text-nowrap' . (!$canRunClaude ? ' disabled' : ''),
                    'id' => 'claude-launch-all-btn',
                    'title' => $canRunClaude ? 'Launch Claude with merged content' : 'Project required',
                    'data-bs-toggle' => 'tooltip',
                    'disabled' => !$canRunClaude,
                ]) ?>
                <?php endif; ?>
                <div class="input-group input-group-sm" style="width: auto;">
                    <?= Html::dropDownList('contentCopyFormat', CopyType::MD->value, $copyTypes, [
                        'id' => 'content-copy-format-select',
                        'class' => 'form-select',
                        'style' => 'width: auto;',
                    ]) ?>
                    <button type="button" id="copy-content-btn" class="btn btn-primary btn-sm text-nowrap" title="Copy to clipboard">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?= QuillViewerWidget::widget([
                'content' => $model->content,
                'enableCopy' => false,
            ]) ?>
        </div>
        <?php foreach ($children as $child):
            $childType = NoteType::resolve($child->type);
            ?>
        <div class="card-body border-top">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <span class="badge bg-<?= $childType === NoteType::SUMMATION ? 'info' : ($childType === NoteType::IMPORT ? 'warning text-dark' : 'secondary') ?> me-2">
                        <?= Html::encode($childType?->label() ?? $child->type) ?>
                    </span>
                    <strong><?= Html::encode($child->name) ?></strong>
                </div>
                <div class="d-flex gap-2">
                    <?= Html::button('<i class="bi bi-terminal-fill"></i> Claude', [
                            'class' => 'btn btn-primary btn-sm text-nowrap claude-launch-btn' . (!$canRunClaude ? ' disabled' : ''),
                            'data-delta' => 'child-' . $child->id,
                            'disabled' => !$canRunClaude,
                        ]) ?>
                    <?= Html::a('Edit', ['/note/update', 'id' => $child->id], ['class' => 'btn btn-outline-primary btn-sm']) ?>
                </div>
            </div>
            <?= QuillViewerWidget::widget([
                    'content' => $child->content,
                    'enableCopy' => false,
                ]) ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$contentDelta = json_encode($model->content, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$claudeUrlJs = json_encode($claudeUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$fetchContentUrlJs = json_encode($fetchContentUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

// Build child deltas map
$childDeltas = [];
foreach ($children as $child) {
    $childDeltas['child-' . $child->id] = $child->content;
}
$childDeltasJs = json_encode($childDeltas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

$script = <<<JS
        window.QuillToolbar.setupCopyButton('copy-content-btn', 'content-copy-format-select', $contentDelta);

        var deltas = Object.assign({content: $contentDelta}, $childDeltasJs);
        document.querySelectorAll('.claude-launch-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.disabled) return;
                var content = deltas[this.dataset.delta];
                if (content) {
                    sessionStorage.setItem('claudePromptContent', typeof content === 'string' ? content : JSON.stringify(content));
                }
                window.location.href = $claudeUrlJs;
            });
        });

        // Claude (all) button - fetch merged content via AJAX
        var launchAllBtn = document.getElementById('claude-launch-all-btn');
        if (launchAllBtn) {
            launchAllBtn.addEventListener('click', function() {
                if (this.disabled) return;
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';

                fetch($fetchContentUrlJs, {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.content) {
                        sessionStorage.setItem('claudePromptContent', typeof data.content === 'string' ? data.content : JSON.stringify(data.content));
                    }
                    window.location.href = $claudeUrlJs;
                })
                .catch(function() {
                    window.location.href = $claudeUrlJs;
                })
                .finally(function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-terminal-fill"></i> Claude (all)';
                });
            });
        }
    JS;
$this->registerJs($script);
?>
