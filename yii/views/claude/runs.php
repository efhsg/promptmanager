<?php

use app\models\ClaudeRun;
use app\models\ClaudeRunSearch;
use common\enums\ClaudeRunStatus;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var ClaudeRunSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $projectList id => name */
/** @var int|null $defaultProjectId */

$this->title = 'Claude Sessions';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="claude-runs-index container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <?php if (count($projectList) === 1): ?>
            <?= Html::a(
                '<i class="bi bi-plus-lg"></i> New dialog',
                ['/claude/index', 'p' => array_key_first($projectList)],
                ['class' => 'btn btn-primary']
            ) ?>
        <?php elseif ($defaultProjectId !== null && count($projectList) > 1): ?>
            <div class="btn-group">
                <?= Html::a(
                    '<i class="bi bi-plus-lg"></i> New dialog',
                    ['/claude/index', 'p' => $defaultProjectId],
                    ['class' => 'btn btn-primary']
                ) ?>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                    <span class="visually-hidden">Choose project</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($projectList as $pId => $pName): ?>
                        <?php if ($pId === $defaultProjectId) continue; ?>
                        <li><?= Html::a(
                            Html::encode($pName),
                            ['/claude/index', 'p' => $pId],
                            ['class' => 'dropdown-item']
                        ) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif (count($projectList) > 1): ?>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-plus-lg"></i> New dialog
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($projectList as $pId => $pName): ?>
                        <li><?= Html::a(
                            Html::encode($pName),
                            ['/claude/index', 'p' => $pId],
                            ['class' => 'dropdown-item']
                        ) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <strong class="mb-0">All Sessions</strong>
                <?php $form = ActiveForm::begin([
                    'action' => ['runs'],
                    'method' => 'get',
                    'options' => ['class' => 'd-flex flex-wrap align-items-center gap-2 mb-0'],
                ]); ?>
                    <?= $form->field($searchModel, 'q', [
                        'options' => ['class' => 'mb-0'],
                    ])->textInput([
                        'class' => 'form-control',
                        'placeholder' => 'Search sessions...',
                    ])->label(false) ?>
                    <?= $form->field($searchModel, 'status', [
                        'options' => ['class' => 'mb-0'],
                    ])->dropDownList(
                        ClaudeRunStatus::labels(),
                        [
                            'class' => 'form-select',
                            'prompt' => 'All Statuses',
                        ]
                    )->label(false) ?>
                    <div class="d-flex align-items-center gap-2">
                        <?= Html::submitButton('Search', ['class' => 'btn btn-outline-primary']) ?>
                        <?php if ($searchModel->q !== null || ($searchModel->status !== null && $searchModel->status !== '')): ?>
                            <?= Html::a('Reset', ['runs'], ['class' => 'btn btn-link px-2']) ?>
                        <?php endif; ?>
                        <?= Html::button(
                            '<span class="auto-refresh-icon">&#x21bb;</span> Auto',
                            [
                                'id' => 'auto-refresh-btn',
                                'class' => 'btn btn-outline-secondary',
                                'title' => 'Auto-refresh every 5 seconds',
                            ]
                        ) ?>
                    </div>
                <?php ActiveForm::end(); ?>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
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
                'emptyText' => 'No sessions yet.',
                'rowOptions' => fn(ClaudeRun $model) => [
                    'data-url' => Url::to(array_filter([
                        '/claude/index',
                        'p' => $model->project_id,
                        's' => $model->session_id,
                        'run' => $model->getSessionLastRunId(),
                    ])),
                    'onclick' => 'window.location.href = this.dataset.url;',
                    'style' => 'cursor: pointer;',
                ],
                'columns' => [
                    [
                        'label' => 'Status',
                        'format' => 'raw',
                        'value' => static function (ClaudeRun $model): string {
                            $label = $model->getSessionLatestStatusEnum()->label();
                            $class = $model->getSessionStatusBadgeClass();
                            return Html::tag('span', Html::encode($label), ['class' => "badge $class"]);
                        },
                    ],
                    [
                        'label' => 'Project',
                        'value' => static fn(ClaudeRun $model): string => Html::encode($model->project?->name ?? '-'),
                        'format' => 'raw',
                    ],
                    [
                        'label' => 'Summary',
                        'value' => static fn(ClaudeRun $model): string =>
                            Html::encode(StringHelper::truncate($model->getDisplaySummary(), 80, '...')),
                        'format' => 'raw',
                    ],
                    [
                        'label' => 'Runs',
                        'value' => static fn(ClaudeRun $model): int => $model->getSessionRunCount(),
                        'contentOptions' => ['class' => 'text-center'],
                        'headerOptions' => ['class' => 'text-center'],
                    ],
                    [
                        'attribute' => 'created_at',
                        'label' => 'Started',
                        'format' => ['datetime', 'php:Y-m-d H:i'],
                    ],
                    [
                        'label' => 'Duration',
                        'value' => static fn(ClaudeRun $model): string => $model->getFormattedSessionDuration(),
                    ],
                ],
            ]); ?>
            </div>
        </div>
    </div>
</div>

<?php
$js = <<<'JS'
(function () {
    var btn = document.getElementById('auto-refresh-btn');
    var icon = btn.querySelector('.auto-refresh-icon');
    var timer = null;
    var INTERVAL = 5000;

    function start() {
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-primary');
        icon.style.animation = 'spin-refresh 1s linear infinite';
        timer = setInterval(function () {
            window.location.reload();
        }, INTERVAL);
    }

    function stop() {
        clearInterval(timer);
        timer = null;
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-secondary');
        icon.style.animation = '';
    }

    btn.addEventListener('click', function () {
        if (timer) {
            stop();
            sessionStorage.removeItem('claude-runs-auto-refresh');
        } else {
            start();
            sessionStorage.setItem('claude-runs-auto-refresh', '1');
        }
    });

    // Resume auto-refresh after reload
    if (sessionStorage.getItem('claude-runs-auto-refresh') === '1')
        start();
})();
JS;
$css = <<<'CSS'
@keyframes spin-refresh {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
#auto-refresh-btn .auto-refresh-icon {
    display: inline-block;
}
CSS;
$this->registerJs($js);
$this->registerCss($css);
?>
