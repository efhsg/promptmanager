<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Note $model */

$this->title = 'Delete ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Notes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="note-delete-confirm container py-4">
    <div class="card">
        <div class="card-header">
            <strong>Are you sure you want to delete: <?= Html::encode($model->name) ?>?</strong>
        </div>
        <div class="card-body">
            <p class="mb-3">This action cannot be undone.</p>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <?= Html::a('Cancel', Yii::$app->request->referrer ?: ['index'], ['class' => 'btn btn-secondary me-2']) ?>
            <?= Html::beginForm(['delete', 'id' => $model->id], 'post', ['class' => 'd-inline']) ?>
            <?= Html::hiddenInput('confirm', 1) ?>
            <?= Html::submitButton('Yes, Delete', ['class' => 'btn btn-danger']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
