<?php

use app\widgets\ContentViewerWidget;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Field $model */

$this->title = 'Delete ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model'       => null,
    'actionLabel' => $this->title,
]);
?>
<div class="field-delete-confirm container py-4">

    <div class="card">
        <div class="card-header">
            <strong>Are you sure you want to delete the field: <?= Html::encode($model->name) ?>?</strong>
        </div>
        <div class="card-body">
            <div class="field-content mb-3">
                <p>
                    <strong>Project:</strong>
                    <?= $model->project ? Html::encode($model->project->name) : Yii::$app->formatter->nullDisplay ?>
                </p>
                <?php if ($model->type === 'text' && !empty($model->content)): ?>
                    <p><strong>Field Content:</strong></p>
                    <?= ContentViewerWidget::widget([
                        'content'    => $model->content,
                        'enableCopy' => false,
                    ]) ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end">
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
