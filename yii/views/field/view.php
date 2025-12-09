<?php /** @noinspection PhpUnhandledExceptionInspection */

use app\services\CopyFormatConverter;
use app\widgets\PathPreviewWidget;
use app\widgets\QuillViewerWidget;
use common\constants\FieldConstants;
use common\enums\CopyType;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Field $model */

$this->title = 'View ' . $model->name;
echo $this->render('_breadcrumbs', [
        'model' => null,
        'actionLabel' => $this->title,
]);

$showPath = in_array($model->type, FieldConstants::PATH_FIELD_TYPES, true) && !empty($model->content);
$canPreviewPath = $showPath && in_array($model->type, FieldConstants::PATH_PREVIEWABLE_FIELD_TYPES, true);
$pathPreview = $showPath
        ? PathPreviewWidget::widget([
                'path' => $model->content,
                'previewUrl' => $canPreviewPath
                        ? Url::to([
                                'field/path-preview',
                                'id' => $model->id,
                                'path' => $model->content,
                        ])
                        : '',
                'enablePreview' => $canPreviewPath,
        ])
        : null;
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <div>
            <?php if (in_array($model->type, FieldConstants::OPTION_FIELD_TYPES, true)): ?>
                <?= Html::a('Renumber', ['renumber', 'id' => $model->id], [
                        'class' => 'btn btn-primary me-2',
                        'data' => [
                                'method' => 'post',
                                'confirm' => 'This will renumber all options starting from 10 with increments of 10. Continue?',
                        ],
                ]) ?>
            <?php endif; ?>
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
                    'attributes' => array_filter([
                            [
                                    'attribute' => 'projectName',
                                    'label' => 'Project Name',
                                    'format' => 'raw',
                                    'value' => static function ($model): string {
                                        return $model->project
                                                ? Html::encode($model->project->name)
                                                : Yii::$app->formatter->nullDisplay;
                                    },
                            ],
                            'name',
                            'type',
                            [
                                    'attribute' => 'share',
                                    'value' => $model->share ? 'Yes' : 'No',
                                    'label' => 'Share with linked projects',
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
                            ($showPath)
                                    ? [
                                    'label' => 'Path',
                                    'format' => 'raw',
                                    'value' => $pathPreview,
                            ]
                                    : null,
                            (in_array($model->type, FieldConstants::CONTENT_FIELD_TYPES, true) && !empty($model->content))
                                    ? [
                                    'attribute' => 'content',
                                    'format' => 'raw',
                                    'label' => 'Field Content',
                                    'value' => static function ($model): string {
                                        return QuillViewerWidget::widget([
                                                'content' => $model->content,
                                                'copyButtonOptions' => [
                                                        'class' => 'btn btn-sm position-absolute',
                                                        'style' => 'bottom: 10px; right: 20px;',
                                                        'title' => 'Copy to clipboard',
                                                        'aria-label' => 'Copy content to clipboard',
                                                        'copyFormat' => 'md',
                                                ],
                                        ]);
                                    },
                            ]
                                    : null,
                    ]),
            ]) ?>
        </div>
    </div>

    <?php if (!empty($model->fieldOptions)): ?>
        <?php $copyFormatConverter = new CopyFormatConverter(); ?>
        <div class="card mb-4">
            <div class="card-header">
                <strong>Field Options</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th>Value</th>
                        <th>Label</th>
                        <th>Default on</th>
                        <th>Order</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($model->fieldOptions as $option): ?>
                        <tr>
                            <td>
                                <?php $optionValue = $copyFormatConverter->convertFromQuillDelta($option->value, CopyType::TEXT); ?>
                                <?= nl2br(Html::encode($optionValue)) ?>
                            </td>
                            <td><?= Html::encode($option->label) ?></td>
                            <td><?= $option->selected_by_default ? 'Yes' : 'No' ?></td>
                            <td><?= Html::encode($option->order) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
