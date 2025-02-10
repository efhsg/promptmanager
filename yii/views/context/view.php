<?php /** @noinspection JSDeprecatedSymbols */
/** @noinspection PhpUnhandledExceptionInspection */

use yii\helpers\Html;
use yii\widgets\DetailView;
use app\widgets\CopyToClipboardWidget;

/** @var yii\web\View $this */
/** @var app\models\Context $model */

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
                'data' => ['method' => 'post'],
            ]) ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Context Details</strong>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'options' => ['class' => 'table table-borderless'],
                'attributes' => [
                    [
                        'attribute' => 'project_name',
                        'label' => 'Project',
                        'value' => function ($model) {
                            return $model->project ? $model->project->name : 'N/A';
                        },
                    ],
                    'name',
                    [
                        'attribute' => 'content',
                        'format' => 'raw',
                        'value' => function ($model) {
                            $textareaId = 'context-content';
                            $textarea = Html::textarea('content', $model->content, [
                                'id' => $textareaId,
                                'class' => 'form-control',
                                'rows' => 10,
                                'readonly' => true,
                                'style' => 'resize: none;',
                            ]);
                            $copyButton = CopyToClipboardWidget::widget([
                                'targetSelector' => '#' . $textareaId,
                                'buttonOptions' => [
                                    'class' => 'btn btn-sm position-absolute',
                                    'style' => 'bottom: 10px; right: 20px;',
                                    'title' => 'Copy to clipboard',
                                    'aria-label' => 'Copy content to clipboard',
                                ],
                                'label' => '<i class="bi bi-clipboard"></i>',
                            ]);
                            return '<div class="position-relative">' . $textarea . $copyButton . '</div>';
                        },
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
</div>
