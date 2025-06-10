<?php /** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @var yii\web\View $this */
/** @var app\models\ContextSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Contexts';
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => null,
]);
?>

<div class="context-index container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Create Context', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Context List</strong>
        </div>
        <div class="card-body p-0">
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
                        'attribute' => 'is_default',
                        'label' => 'Default on',
                        'enableSorting' => true,
                        'value' => function ($model) {
                            return $model->is_default ? 'Yes' : 'No';
                        },
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
