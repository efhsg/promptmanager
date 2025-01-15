<?php /** @noinspection PhpUnhandledExceptionInspection */

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Field $model */

$this->title = 'View ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <div>
            <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary me-2']) ?>
            <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger me-2',
            ]) ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Field Details</strong>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'options' => ['class' => 'table table-borderless'],
                'attributes' => [
                    [
                        'attribute' => 'projectName',
                        'label' => 'Project Name',
                        'enableSorting' => true,
                        'format' => 'raw',
                        'value' => function ($model) {
                            return $model->project
                                ? Html::encode($model->project->name)
                                : Yii::$app->formatter->nullDisplay;
                        },
                    ],
                    'name',
                    'type',
                    [
                        'attribute' => 'selected_by_default',
                        'value' => $model->selected_by_default ? 'Yes' : 'No',
                    ],
                    'label',
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
</div>
