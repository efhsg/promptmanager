<?php /** @noinspection PhpUnhandledExceptionInspection */
/** @var yii\web\View $this */
/** @var app\models\FieldSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use app\widgets\MobileCardView;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Fields';
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => null,
]);
?>

<div class="field-index container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Create Field', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Field List</strong>
        </div>
        <div class="card-body p-0">
            <?= MobileCardView::widget([
                'dataProvider' => $dataProvider,
                'titleAttribute' => 'name',
                'metaAttributes' => [
                    static fn($model) => $model->project?->name,
                    'type',
                    static fn($model) => $model->share ? 'Shared' : null,
                ],
                'metaLabels' => [0 => 'Project', 1 => 'Type'],
            ]) ?>
            <?php

            echo GridView::widget([
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
                    'aria-label' => 'Field Table',
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
                        'format' => 'raw',
                        'value' => function ($model) {
                            return $model->project
                                ? Html::encode($model->project->name)
                                : Yii::$app->formatter->nullDisplay;
                        },
                    ],
                    [
                        'attribute' => 'name',
                        'label' => 'Field Name',
                        'enableSorting' => true,
                    ],
                    [
                        'attribute' => 'type',
                        'label' => 'Type',
                        'enableSorting' => true,
                    ],
                    [
                        'attribute' => 'share',
                        'label' => 'Share',
                        'value' => static fn($model) => $model->share ? 'Yes' : 'No',
                    ],
                    [
                        'class' => yii\grid\ActionColumn::class,
                        'urlCreator' => function ($action, $model) {
                            $id = is_array($model) ? $model['id'] : $model->id;
                            return Url::to([$action, 'id' => $id]);
                        },
                        'template' => '{update} {delete}',
                        'buttonOptions' => ['data-confirm' => false],
                    ],
                ],
            ]);
?>
        </div>
    </div>
</div>
