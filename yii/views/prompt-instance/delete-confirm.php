<?php
/** @noinspection PhpUnhandledExceptionInspection */

use app\widgets\ContentViewerWidget;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\PromptInstance $model */

// Use updated_at for the title
$this->title = 'Delete Prompt Instance - ' . Yii::$app->formatter->asDatetime($model->updated_at, 'php:Y-m-d H:i:s');
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>
<div class="prompt-instance-delete-confirm container py-4">
    <div class="card">
        <div class="card-header">
            <strong>Are you sure you want to delete the prompt instance?</strong>
        </div>
        <div class="card-body">
            <div class="prompt-instance-details mb-3">
                <div class="form-group">
                    <strong>Template:</strong>
                    <p><?= Html::encode($model->template->name ?? 'N/A') ?></p>
                </div>
                <div class="form-group">
                    <strong>Prompt:</strong>
                    <?= ContentViewerWidget::widget([
                        'content' => $model->final_prompt,
                        'enableCopy' => false,
                    ]) ?>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <?= Html::a('Cancel', Yii::$app->request->referrer ?: ['index'], ['class' => 'btn btn-secondary me-2']) ?>
            <?= Html::beginForm(['delete', 'id' => $model->id], 'post', [
                'class' => 'd-inline',
                'id' => 'delete-confirmation-form',
            ]) ?>
            <?= Html::hiddenInput('confirm', 1) ?>
            <?= Html::submitButton('Yes, Delete', ['class' => 'btn btn-danger']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
