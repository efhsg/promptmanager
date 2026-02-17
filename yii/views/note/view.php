<?php

use app\widgets\QuillViewerWidget;
use common\enums\NoteType;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Note $model */
/** @var app\models\Note[] $children */

$canRunClaude = $model->project_id !== null;
$claudeTooltip = $canRunClaude ? 'Talk to AI' : 'Project required';
$aiChatUrl = $canRunClaude
    ? Url::to(['/ai-chat/index', 'p' => $model->project_id, 'breadcrumbs' => json_encode([
        ['label' => 'Notes', 'url' => Url::to(['/note/index'])],
        ['label' => $model->name, 'url' => Url::to(['/note/view', 'id' => $model->id])],
    ])])
    : '#';
$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Notes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->name;
$children ??= [];
?>

<div class="note-view container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <h1 class="h3 mb-0 me-3"><?= Html::encode($model->name) ?></h1>
        <div class="d-flex flex-shrink-0">
            <?= Html::button('<i class="bi bi-terminal-fill"></i> Claude', [
                'class' => 'btn btn-outline-primary me-2 claude-launch-btn' . (!$canRunClaude ? ' disabled' : ''),
                'title' => $claudeTooltip ?: null,
                'data-bs-toggle' => $claudeTooltip ? 'tooltip' : null,
                'data-delta' => 'content',
                'disabled' => !$canRunClaude,
            ]) ?>
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
        <div class="card-header"><strong>Content</strong></div>
        <div class="card-body">
            <?= QuillViewerWidget::widget([
                'content' => $model->content,
                'enableExport' => true,
                'exportProjectId' => $model->project_id,
                'exportEntityName' => $model->name,
                'exportRootDirectory' => $model->project?->root_directory,
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
                'enableExport' => true,
                'exportProjectId' => $model->project_id,
                'exportEntityName' => $child->name,
                'exportRootDirectory' => $model->project?->root_directory,
            ]) ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$contentDelta = json_encode($model->content, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$aiChatUrlJs = json_encode($aiChatUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

// Build child deltas map
$childDeltas = [];
foreach ($children as $child) {
    $childDeltas['child-' . $child->id] = $child->content;
}
$childDeltasJs = json_encode($childDeltas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

$script = <<<JS
        var deltas = Object.assign({content: $contentDelta}, $childDeltasJs);
        document.querySelectorAll('.claude-launch-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.disabled) return;
                var content = deltas[this.dataset.delta];
                if (content) {
                    sessionStorage.setItem('aiPromptContent', typeof content === 'string' ? content : JSON.stringify(content));
                }
                window.location.href = $aiChatUrlJs;
            });
        });
    JS;
$this->registerJs($script);
?>
