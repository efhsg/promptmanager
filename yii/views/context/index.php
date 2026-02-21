<?php /** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @var yii\web\View $this */
/** @var app\models\ContextSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use app\widgets\MobileCardView;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Contexts';
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => null,
]);

$currentProject = Yii::$app->projectContext->getCurrentProject();
?>

<div class="context-index container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <div>
            <?php if ($currentProject !== null): ?>
                <?= Html::a('Renumber', ['renumber', 'projectId' => $currentProject->id], [
                    'class' => 'btn btn-primary me-2',
                    'data' => [
                        'method' => 'post',
                        'confirm' => 'This will renumber all contexts in this project starting from 10 with increments of 10. Contexts with the same order will retain the same new order. Continue?',
                    ],
                ]) ?>
            <?php endif; ?>
            <?= Html::a('Create Context', ['create'], ['class' => 'btn btn-primary']) ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Context List</strong>
        </div>
        <div class="card-body p-0">
            <?= MobileCardView::widget([
                'dataProvider' => $dataProvider,
                'titleAttribute' => 'name',
                'metaAttributes' => [
                    'projectName',
                    static fn($model) => $model->is_default ? 'Default' : null,
                    static fn($model) => 'Order: ' . $model->order,
                ],
                'metaLabels' => [0 => 'Project'],
            ]) ?>
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'summary' => '<strong>{begin}</strong> to <strong>{end}</strong> out of <strong>{totalCount}</strong>',
                'summaryOptions' => ['class' => 'text-start m-2'],
                'layout' => "{items}"
                    . "<div class='card-footer position-relative py-3 px-2'>"
                    . "<div class='position-absolute start-0 top-50 translate-middle-y'>{summary}</div>"
                    . "<div class='text-center'>{pager}</div>"
                    . "</div>",
                'tableOptions' => [
                    'class' => 'table table-striped table-hover mb-0',
                    'data-responsive' => 'true',
                    'aria-label' => 'Context Table',
                ],
                'pager' => [
                    'options' => ['class' => 'pagination justify-content-center m-3'],
                    'linkOptions' => ['class' => 'page-link'],
                    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
                    'prevPageLabel' => 'Previous',
                    'nextPageLabel' => 'Next',
                    'firstPageLabel' => 'First',
                    'lastPageLabel' => 'Last',
                    'pageCssClass' => 'page-item',
                    'firstPageCssClass' => 'page-item',
                    'lastPageCssClass' => 'page-item',
                    'nextPageCssClass' => 'page-item',
                    'prevPageCssClass' => 'page-item',
                    'activePageCssClass' => 'active',
                    'disabledPageCssClass' => 'disabled',
                ],

                'rowOptions' => function ($model) {
                    $id = is_array($model) ? $model['id'] : $model->id;
                    return [
                        'onclick' => 'window.location.href = "' . Url::to(['view', 'id' => $id]) . '";',
                        'style' => 'cursor: pointer;',
                    ];
                },

                'columns' => [
                    [
                        'attribute' => 'projectName',
                        'label' => 'Project Name',
                        'enableSorting' => true,
                        'value' => 'projectName',
                    ],

                    [
                        'attribute' => 'name',
                        'label' => 'Context Name',
                        'enableSorting' => true,
                    ],

                    [
                        'attribute' => 'updated_at',
                        'label' => 'Updated',
                        'enableSorting' => true,
                        'format' => ['relativeTime'],
                    ],

                    [
                        'attribute' => 'is_default',
                        'label' => 'Default on',
                        'enableSorting' => true,
                        'value' => function ($model) {
                            return $model->is_default ? 'Yes' : 'No';
                        },
                    ],

                    [
                        'attribute' => 'share',
                        'label' => 'Share',
                        'enableSorting' => true,
                        'value' => function ($model) {
                            return $model->share ? 'Yes' : 'No';
                        },
                    ],

                    [
                        'attribute' => 'order',
                        'label' => 'Order',
                        'enableSorting' => true,
                    ],

                    [
                        'class' => yii\grid\ActionColumn::class,
                        'urlCreator' => function ($action, $model) {
                            $id = is_array($model) ? $model['id'] : $model->id;
                            return Url::toRoute([$action, 'id' => $id]);
                        },
                        'template' => '{update} {delete}',
                        'buttonOptions' => ['data-confirm' => false],
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
