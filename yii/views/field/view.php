<?php /** @noinspection PhpUnhandledExceptionInspection */

use app\widgets\QuillViewerWidget;
use common\constants\FieldConstants;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Field $model */

$this->title = 'View ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);

$isPathBasedField = in_array($model->type, ['file', 'directory'], true);
$pathPreviewItems = [];

if ($isPathBasedField) {
    $rootDirectory = $model->project?->root_directory;
    $normalizePath = static function (?string $basePath, string $relativePath): string {
        $basePath = $basePath ? rtrim($basePath, "\\/\t\n\r") : null;
        $relativePath = trim($relativePath);

        if ($relativePath === '') {
            return $basePath ?? '';
        }

        $relativePath = ltrim($relativePath, "\\/");
        return $basePath ? $basePath . '/' . $relativePath : $relativePath;
    };

    if (!empty($model->fieldOptions)) {
        foreach ($model->fieldOptions as $option) {
            $pathPreviewItems[] = $normalizePath($rootDirectory, (string)$option->value);
        }
    } elseif ($rootDirectory) {
        $pathPreviewItems[] = $rootDirectory;
    }
}
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
                    (in_array($model->type, [FieldConstants::TYPES[0], FieldConstants::TYPES[4]], true) && !empty($model->content))
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
                            <td><?= Html::encode($option->value) ?></td>
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

    <?php if ($isPathBasedField): ?>
        <div class="card mb-4">
            <div class="card-header">
                <strong>Path Preview</strong>
            </div>
            <div class="card-body">
                <?php if (!empty($pathPreviewItems)): ?>
                    <?php foreach ($pathPreviewItems as $path): ?>
                        <?php
                        $normalizedPath = str_replace('\\', '/', $path);
                        $fileName = basename($normalizedPath);
                        $directoryName = dirname($normalizedPath);
                        if ($directoryName === '.') {
                            $directoryName = '/';
                        }
                        ?>
                        <div class="path-preview mb-3">
                            <div class="path-preview__meta">
                                <div class="path-preview__window-controls" aria-hidden="true">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                                <div class="path-preview__labels">
                                    <div class="path-preview__filename"><?= Html::encode($fileName ?: $directoryName) ?></div>
                                    <div class="path-preview__directory text-truncate"><?= Html::encode($directoryName) ?></div>
                                </div>
                                <span class="badge bg-secondary text-uppercase path-preview__badge"><?= Html::encode($model->type) ?></span>
                            </div>
                            <pre class="path-preview__body mb-0"><?= Html::encode($normalizedPath ?: 'Path will appear here once configured.') ?></pre>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">Add options or configure a project root directory to preview the final path.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
