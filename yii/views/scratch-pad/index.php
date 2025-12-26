<?php

use app\models\ScratchPad;
use app\models\ScratchPadSearch;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var ScratchPadSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var app\models\Project|null $currentProject */
/** @var bool $isAllProjects */

$this->title = 'Saved Scratch Pads';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="scratch-pad-index container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('New Scratch Pad', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>

    <?php if ($isAllProjects): ?>
        <div class="alert alert-info">
            Showing scratch pads from all projects.
        </div>
    <?php elseif ($currentProject): ?>
        <div class="alert alert-info">
            Showing scratch pads for project: <strong><?= Html::encode($currentProject->name) ?></strong> and global pads.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <strong>Scratch Pad List</strong>
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
                ],
                'pager' => [
                    'options' => ['class' => 'pagination justify-content-center m-3'],
                    'linkOptions' => ['class' => 'page-link'],
                    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
                    'prevPageLabel' => 'Previous',
                    'nextPageLabel' => 'Next',
                    'pageCssClass' => 'page-item',
                    'activePageCssClass' => 'active',
                    'disabledPageCssClass' => 'disabled',
                ],
                'emptyText' => 'No saved scratch pads yet.',
                'rowOptions' => fn($model) => [
                    'onclick' => 'window.location.href = "' . Url::to(['view', 'id' => $model->id]) . '";',
                    'style' => 'cursor: pointer;',
                ],
                'columns' => [
                    'name',
                    [
                        'attribute' => 'project_id',
                        'label' => 'Scope',
                        'value' => fn(ScratchPad $model) => $model->project ? $model->project->name : 'Global',
                    ],
                    [
                        'attribute' => 'updated_at',
                        'format' => ['datetime', 'php:Y-m-d H:i'],
                    ],
                    [
                        'class' => ActionColumn::class,
                        'urlCreator' => fn($action, $model) => Url::toRoute([$action, 'id' => $model->id]),
                        'template' => '{update} {delete}',
                        'buttonOptions' => ['data-confirm' => false],
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
