<?php
/** @noinspection PhpUnhandledExceptionInspection */

use app\models\Project;
use app\widgets\QuillViewerWidget;
use yii\helpers\Html;
use yii\widgets\DetailView;

// include the widget

/** @var yii\web\View $this */
/** @var app\models\Project $model */

$this->title = 'View - ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"></h1>
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
            <strong>Project Details</strong>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'options' => ['class' => 'table table-borderless'],
                'attributes' => [
                    'name',
                    'root_directory',
                    [
                        'attribute' => 'allowed_file_extensions',
                        'label' => 'Allowed File Extensions',
                        'value' => static function ($model) {
                            $extensions = $model->getAllowedFileExtensions();
                            return $extensions === [] ? 'All extensions allowed' : implode(', ', $extensions);
                        },
                    ],
                    [
                        'attribute' => 'blacklisted_directories',
                        'label' => 'Blacklisted Directories',
                        'value' => static function ($model) {
                            $directories = $model->getBlacklistedDirectories();
                            return $directories === [] ? 'None' : implode(', ', $directories);
                        },
                    ],
                    [
                        'attribute' => 'prompt_instance_copy_format',
                        'label' => 'Prompt Instance Copy Format',
                        'value' => static fn(Project $model) => $model->getPromptInstanceCopyFormatEnum()->label(),
                    ],
                    [
                        'attribute' => 'description',
                        'format' => 'raw',
                        'value' => function ($model) {
                            return QuillViewerWidget::widget([
                                'content' => $model->description,
                                'copyButtonOptions' => [
                                    'class' => 'btn btn-sm position-absolute',
                                    'style' => 'bottom: 10px; right: 20px;',
                                    'title' => 'Copy to clipboard',
                                    'aria-label' => 'Copy content to clipboard',
                                    'copyFormat' => 'md',
                                ],
                            ]);
                        },
                    ],
                    [
                        'attribute' => 'created_at',
                        'format' => ['datetime', 'php:Y-m-d H:i:s']
                    ],
                    [
                        'attribute' => 'updated_at',
                        'format' => ['datetime', 'php:Y-m-d H:i:s']
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>
