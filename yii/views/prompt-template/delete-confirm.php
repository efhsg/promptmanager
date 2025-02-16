<?php /** @noinspection PhpUnhandledExceptionInspection */

use app\widgets\ContentViewerWidget;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\PromptTemplate $model */

$this->title = 'Delete - ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>
<div class="template-delete-confirm container py-4">

    <div class="card">
        <div class="card-header">
            <strong>Are you sure you want to delete the template: <?= Html::encode($model->name) ?>?</strong>
        </div>
        <div class="card-body">
            <div class="template-description mb-3">
                <strong>Description:</strong>
                <?= ContentViewerWidget::widget([
                    'content'    => $model->description,
                    'enableCopy' => false,
                ]) ?>

                <strong>Content:</strong>
                <?= ContentViewerWidget::widget([
                    'content'    => $model->template_body,
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
