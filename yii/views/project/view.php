<?php
/** @noinspection PhpUnhandledExceptionInspection */

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Project $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Projects', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="project-view container py-4">

    <!-- Top Bar: Title + Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <div>
            <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary me-2']) ?>
            <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger me-2',
                'data'  => ['method' => 'post'],
            ]) ?>
            <?= Html::a('Back to List', ['index'], ['class' => 'btn btn-secondary']) ?>
        </div>
    </div>

    <!-- Card: Project Details -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Project Details</strong>
        </div>
        <div class="card-body">

            <!-- Name -->
            <div class="row mb-3">
                <div class="col-sm-3">
                    <strong>Name</strong>
                </div>
                <div class="col-sm-9">
                    <?= Html::encode($model->name) ?>
                </div>
            </div>

            <!-- Description -->
            <div class="row mb-3">
                <div class="col-sm-3">
                    <strong>Description</strong>
                </div>
                <div class="col-sm-9">
                    <!-- Convert newlines to <br> and encode to avoid XSS -->
                    <?= nl2br(Html::encode($model->description)) ?>
                </div>
            </div>

            <!-- Created At -->
            <div class="row mb-3">
                <div class="col-sm-3">
                    <strong>Created At</strong>
                </div>
                <div class="col-sm-9">
                    <?= Yii::$app->formatter
                        ->asDatetime($model->created_at, 'php:Y-m-d H:i:s') ?>
                </div>
            </div>

            <!-- Updated At -->
            <div class="row mb-3">
                <div class="col-sm-3">
                    <strong>Updated At</strong>
                </div>
                <div class="col-sm-9">
                    <?= Yii::$app->formatter
                        ->asDatetime($model->updated_at, 'php:Y-m-d H:i:s') ?>
                </div>
            </div>

        </div>
    </div>

</div>
