<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Context $model */

$this->title = 'Delete ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>
<div class="context-delete-confirm container py-4">

    <div class="card">
        <div class="card-header">
            <strong>Are you sure you want to delete the context: <?= Html::encode($model->name) ?>?</strong>
        </div>
        <div class="card-body">
            <div class="context-content mb-3">
                <strong>Content:</strong>
                <?= Html::textarea('content-preview', $model->content, [
                    'class' => 'form-control',
                    'rows' => 10,
                    'readonly' => true,
                    'style' => 'resize: none;',
                ]) ?>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end">
            <!-- Cancel Button -->
            <?= Html::a('Cancel', Yii::$app->request->referrer ?: ['index'], ['class' => 'btn btn-secondary me-2']) ?>

            <!-- Delete Form (inline) -->
            <?= Html::beginForm(['delete', 'id' => $model->id], 'post', [
                'class' => 'd-inline',
                'id' => 'delete-confirmation-form'
            ]) ?>
            <?= Html::hiddenInput('confirm', 1) ?>
            <?= Html::submitButton('Yes, Delete', ['class' => 'btn btn-danger']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
