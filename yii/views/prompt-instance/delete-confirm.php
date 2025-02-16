<?php
/** @noinspection PhpUnhandledExceptionInspection */

use app\widgets\ContentViewerWidget;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\PromptInstance $model */

$this->title = 'Delete Prompt Instance: ' . Html::encode(substr($model->final_prompt, 0, 50));
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>
<div class="prompt-instance-delete-confirm container py-4">
    <div class="card">
        <div class="card-header">
            <strong>Are you sure you want to delete the prompt
                instance: <?= Html::encode(substr($model->final_prompt, 0, 50)) ?>?</strong>
        </div>
        <div class="card-body">
            <div class="prompt-instance-details mb-3">
                <strong>Final Prompt:</strong>
                <?= ContentViewerWidget::widget([
                    'content' => $model->final_prompt,
                    'enableCopy' => false,
                ]) ?>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <?= Html::a('Cancel', Yii::$app->request->referrer ?: ['index'], ['class' => 'btn btn-secondary me-2']) ?>
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
