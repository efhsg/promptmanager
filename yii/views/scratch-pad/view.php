<?php

use app\widgets\QuillViewerWidget;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\ScratchPad $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Saved Scratch Pads', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->name;
?>

<div class="scratch-pad-view container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($model->name) ?></h1>
        <div>
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

    <div class="card">
        <div class="card-header"><strong>Content</strong></div>
        <div class="card-body">
            <?= QuillViewerWidget::widget([
                'content' => $model->content,
                'copyButtonOptions' => [
                    'class' => 'btn btn-sm position-absolute',
                    'style' => 'bottom: 10px; right: 20px;',
                    'title' => 'Copy to clipboard',
                    'copyFormat' => 'md',
                ],
                'cliCopyButtonOptions' => [
                    'class' => 'btn btn-sm position-absolute',
                    'style' => 'bottom: 10px; right: 60px;',
                ],
            ]) ?>
        </div>
    </div>
</div>
