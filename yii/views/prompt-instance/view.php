<?php
/** @noinspection JSUnresolvedReference */

/** @noinspection DuplicatedCode */

/** @noinspection PhpUnhandledExceptionInspection */

use app\models\PromptInstance;
use common\enums\CopyType;
use app\widgets\QuillViewerWidget;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\PromptInstance $model */

$this->title = 'View - ' . Yii::$app->formatter->asDatetime($model->updated_at, 'php:Y-m-d H:i:s');
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>
<div class="container py-4">
    <div class="d-flex justify-content-end align-items-center mb-4">
        <div>
            <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary me-2']) ?>
            <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger me-2',
                'data' => [
                    'method' => 'post',
                ],
            ]) ?>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header">
            <strong>Prompt Instance Details</strong>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'options' => ['class' => 'table table-borderless'],
                'attributes' => [
                    [
                        'attribute' => 'label',
                        'label' => 'Label',
                        'value' => static function (PromptInstance $model): string {
                            $label = $model->label;
                            return $label === null || $label === '' ? 'N/A' : $label;
                        },
                    ],
                    [
                        'attribute' => 'template_name',
                        'label' => 'Template',
                        'value' => static function (PromptInstance $model): string {
                            return $model->template ? $model->template->name : 'N/A';
                        },
                    ],
                    [
                        'attribute' => 'final_prompt',
                        'format' => 'raw',
                        'label' => 'Prompt',
                        'value' => static function (PromptInstance $model) {
                            $projectCopyFormat = $model->template?->project?->getPromptInstanceCopyFormatEnum()->value
                                ?? CopyType::MD->value;

                            return QuillViewerWidget::widget([
                                'content' => $model->final_prompt,
                                'options' => [
                                    'style' => 'height: 300px;',
                                ],
                                'copyButtonOptions' => [
                                    'class' => 'btn btn-sm position-absolute',
                                    'style' => 'bottom: 10px; right: 20px;',
                                    'title' => 'Copy to clipboard',
                                    'aria-label' => 'Copy template content to clipboard',
                                    'copyFormat' => $projectCopyFormat,
                                ],
                            ]);
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
