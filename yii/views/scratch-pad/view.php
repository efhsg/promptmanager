<?php

use app\widgets\QuillViewerWidget;
use common\enums\CopyType;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\ScratchPad $model */

$copyTypes = CopyType::labels();
$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Saved Scratch Pads', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->name;
?>

<div class="scratch-pad-view container py-4">
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

    <?php if (!empty($model->summation)): ?>
    <div class="accordion" id="scratchPadViewAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingContent">
                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseContent" aria-expanded="true" aria-controls="collapseContent">
                    Content
                </button>
            </h2>
            <div id="collapseContent" class="accordion-collapse collapse show" aria-labelledby="headingContent"
                 data-bs-parent="#scratchPadViewAccordion">
                <div class="accordion-body p-0">
                    <div class="d-flex justify-content-end p-2 border-bottom">
                        <div class="input-group input-group-sm" style="width: auto;">
                            <?= Html::dropDownList('contentCopyFormat', CopyType::MD->value, $copyTypes, [
                                'id' => 'content-copy-format-select',
                                'class' => 'form-select',
                                'style' => 'width: auto;',
                            ]) ?>
                            <button type="button" id="cli-copy-content-btn" class="btn btn-outline-secondary btn-sm" title="Copy as Claude CLI command">
                                <i class="bi bi-terminal"></i>
                            </button>
                            <button type="button" id="copy-content-btn" class="btn btn-primary btn-sm text-nowrap" title="Copy to clipboard">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div class="p-3">
                        <?= QuillViewerWidget::widget([
                            'content' => $model->content,
                            'enableCopy' => false,
                        ]) ?>
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
                 data-bs-parent="#scratchPadViewAccordion">
                <div class="accordion-body p-0">
                    <div class="d-flex justify-content-end p-2 border-bottom">
                        <div class="input-group input-group-sm" style="width: auto;">
                            <?= Html::dropDownList('summationCopyFormat', CopyType::MD->value, $copyTypes, [
                                'id' => 'summation-copy-format-select',
                                'class' => 'form-select',
                                'style' => 'width: auto;',
                            ]) ?>
                            <button type="button" id="cli-copy-summation-btn" class="btn btn-outline-secondary btn-sm" title="Copy as Claude CLI command">
                                <i class="bi bi-terminal"></i>
                            </button>
                            <button type="button" id="copy-summation-btn" class="btn btn-primary btn-sm text-nowrap" title="Copy to clipboard">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div class="p-3">
                        <?= QuillViewerWidget::widget([
                            'content' => $model->summation,
                            'enableCopy' => false,
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Content</strong>
            <div class="input-group input-group-sm" style="width: auto;">
                <?= Html::dropDownList('contentCopyFormat', CopyType::MD->value, $copyTypes, [
                    'id' => 'content-copy-format-select',
                    'class' => 'form-select',
                    'style' => 'width: auto;',
                ]) ?>
                <button type="button" id="cli-copy-content-btn" class="btn btn-outline-secondary btn-sm" title="Copy as Claude CLI command">
                    <i class="bi bi-terminal"></i>
                </button>
                <button type="button" id="copy-content-btn" class="btn btn-primary btn-sm text-nowrap" title="Copy to clipboard">
                    <i class="bi bi-clipboard"></i> Copy
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= QuillViewerWidget::widget([
                'content' => $model->content,
                'enableCopy' => false,
            ]) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$contentDelta = json_encode($model->content);
$summationDelta = json_encode($model->summation);
$script = <<<JS
        window.QuillToolbar.setupCopyButton('copy-content-btn', 'content-copy-format-select', $contentDelta);
        window.QuillToolbar.setupCliCopyButton('cli-copy-content-btn', 'content-copy-format-select', $contentDelta);
        window.QuillToolbar.setupCopyButton('copy-summation-btn', 'summation-copy-format-select', $summationDelta);
        window.QuillToolbar.setupCliCopyButton('cli-copy-summation-btn', 'summation-copy-format-select', $summationDelta);
    JS;
$this->registerJs($script);
?>
