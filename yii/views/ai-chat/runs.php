<?php

use app\components\ProjectContext;
use app\models\AiRun;
use app\models\AiRunSearch;
use common\enums\AiRunStatus;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var AiRunSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var app\models\Project|null $currentProject */
/** @var bool $isAllProjects */
/** @var array $projectList id => name */
/** @var array $projectOptions */
/** @var int|null $defaultProjectId */
/** @var array $providerList identifier => name */
/** @var string $defaultProvider */

$this->title = 'Dialogs';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="ai-runs-index container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <?php if ($defaultProjectId !== null): ?>
            <?php if (count($providerList) <= 1): ?>
                <?= Html::a(
                    '<i class="bi bi-plus-lg"></i> New dialog',
                    ['/ai-chat/index', 'p' => $defaultProjectId],
                    ['class' => 'btn btn-primary']
                ) ?>
            <?php else: ?>
                <div class="btn-group">
                    <?= Html::a(
                        '<i class="bi bi-plus-lg"></i> New dialog',
                        ['/ai-chat/index', 'p' => $defaultProjectId, 'provider' => $defaultProvider],
                        ['class' => 'btn btn-primary']
                    ) ?>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                        <span class="visually-hidden">Choose provider</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($providerList as $providerId => $providerName): ?>
                            <?php if ($providerId === $defaultProvider) continue; ?>
                            <li><?= Html::a(
                                Html::encode($providerName),
                                ['/ai-chat/index', 'p' => $defaultProjectId, 'provider' => $providerId],
                                ['class' => 'dropdown-item']
                            ) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($isAllProjects): ?>
        <div class="alert alert-info">
            Showing sessions from all projects.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <strong class="mb-0">Sessions</strong>
                    <?= Html::button(
                        '<i class="bi bi-arrow-clockwise auto-refresh-icon"></i>',
                        [
                            'id' => 'auto-refresh-btn',
                            'class' => 'btn btn-sm btn-outline-secondary border-0 p-1',
                            'title' => 'Auto-refresh every 5 seconds',
                        ]
                    ) ?>
                </div>
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
                        AiRunStatus::labels(),
                        [
                            'class' => 'form-select',
                            'prompt' => 'All Statuses',
                        ]
                    )->label(false) ?>
                    <?= $form->field($searchModel, 'project_id', [
                        'options' => ['class' => 'mb-0'],
                    ])->dropDownList(
                        $projectOptions,
                        [
                            'class' => 'form-select',
                        ]
                    )->label(false) ?>
                    <?php
                    $contextDefaultProjectId = $isAllProjects
                        ? ProjectContext::ALL_PROJECTS_ID
                        : ($currentProject?->id);
                    $projectChanged = $searchModel->project_id !== $contextDefaultProjectId;
                    ?>
                    <div class="d-flex align-items-center gap-2">
                        <?= Html::submitButton('Search', ['class' => 'btn btn-outline-primary']) ?>
                        <?php if ($searchModel->q !== null || ($searchModel->status !== null && $searchModel->status !== '') || $projectChanged): ?>
                            <?= Html::a('Reset', ['runs'], ['class' => 'btn btn-link px-2']) ?>
                        <?php endif; ?>
                    </div>
                <?php ActiveForm::end(); ?>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
            <?php $cleanupLink = Html::a(
                '<i class="bi bi-trash"></i> Cleanup',
                ['cleanup'],
                ['class' => 'btn btn-sm btn-outline-danger']
            ); ?>
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'summary' => '<strong>{begin}</strong> to <strong>{end}</strong> out of <strong>{totalCount}</strong>',
                'summaryOptions' => ['class' => 'text-start m-2'],
                'layout' => "{items}"
                    . "<div class='card-footer d-flex align-items-center justify-content-between py-3 px-3'>"
                    . "<div class='flex-shrink-0'>{summary}</div>"
                    . "<div class='flex-grow-1 text-center'>{pager}</div>"
                    . "<div class='flex-shrink-0'>$cleanupLink</div>"
                    . "</div>",
                'tableOptions' => [
                    'class' => 'table table-striped table-hover mb-0 ai-runs-table',
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
                'rowOptions' => fn(AiRun $model) => [
                    'data-url' => Url::to(array_filter([
                        '/ai-chat/index',
                        'p' => $model->project_id,
                        's' => $model->session_id,
                        'run' => $model->getSessionLastRunId(),
                        'provider' => $model->provider !== 'claude' ? $model->provider : null,
                    ])),
                    'onclick' => 'if (!event.target.closest("[data-method]")) window.location.href = this.dataset.url;',
                    'style' => 'cursor: pointer;',
                    'class' => in_array($model->getSessionLatestStatus(), AiRunStatus::activeValues(), true)
                        ? 'ai-run-active'
                        : 'ai-run-terminal',
                ],
                'columns' => [
                    [
                        'attribute' => 'session_latest_status',
                        'label' => 'Status',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width: 85px;'],
                        'contentOptions' => ['class' => 'text-nowrap'],
                        'value' => static function (AiRun $model): string {
                            $label = $model->getSessionLatestStatusEnum()->label();
                            $class = $model->getSessionStatusBadgeClass();
                            return Html::tag('span', Html::encode($label), ['class' => "badge $class"]);
                        },
                    ],
                    [
                        'attribute' => 'project_name',
                        'label' => 'Project',
                        'headerOptions' => ['style' => 'width: 130px;'],
                        'contentOptions' => ['class' => 'text-truncate', 'style' => 'max-width: 0;'],
                        'value' => static fn(AiRun $model): string => Html::encode($model->project?->name ?? '-'),
                        'format' => 'raw',
                    ],
                    [
                        'attribute' => 'provider',
                        'label' => 'Provider',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width: 85px;'],
                        'contentOptions' => ['class' => 'text-nowrap'],
                        'value' => static function (AiRun $model): string {
                            $provider = $model->provider ?? 'claude';
                            $badgeClass = match ($provider) {
                                'claude' => 'badge-provider-claude',
                                'codex' => 'badge-provider-codex',
                                default => 'bg-secondary',
                            };
                            return Html::tag('span', Html::encode(ucfirst($provider)), ['class' => "badge $badgeClass"]);
                        },
                    ],
                    [
                        'attribute' => 'prompt_summary',
                        'label' => 'Summary',
                        'contentOptions' => ['class' => 'text-truncate', 'style' => 'max-width: 0;'],
                        'value' => static fn(AiRun $model): string
                            => Html::encode(StringHelper::truncate($model->getDisplaySummary(), 120, '...')),
                        'format' => 'raw',
                    ],
                    [
                        'attribute' => 'session_run_count',
                        'label' => 'Runs',
                        'value' => static fn(AiRun $model): int => $model->getSessionRunCount(),
                        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 55px;'],
                        'contentOptions' => ['class' => 'text-center text-nowrap'],
                    ],
                    [
                        'attribute' => 'created_at',
                        'label' => 'Started',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width: 110px;'],
                        'contentOptions' => ['class' => 'text-nowrap'],
                        'value' => static function (AiRun $model): string {
                            $short = Yii::$app->formatter->asDatetime($model->created_at, 'php:d M H:i');
                            $relative = Yii::$app->formatter->asRelativeTime($model->created_at);
                            return Html::tag('span', Html::encode($short), ['title' => $relative]);
                        },
                    ],
                    [
                        'attribute' => 'session_total_duration',
                        'label' => 'Duration',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width: 110px;'],
                        'contentOptions' => ['class' => 'text-nowrap'],
                        'value' => static function (AiRun $model): string {
                            $duration = Html::encode($model->getFormattedSessionDuration());
                            $cost = $model->getSessionTotalCost();
                            if ($cost !== null && $cost > 0) {
                                $costStr = '$' . number_format($cost, 2);
                                return $duration . Html::tag('span', Html::encode(" · $costStr"), ['class' => 'text-muted small']);
                            }
                            return $duration;
                        },
                    ],
                    [
                        'label' => '',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width: 50px;'],
                        'contentOptions' => ['class' => 'text-center'],
                        'value' => static function (AiRun $model): string {
                            if (!in_array($model->getSessionLatestStatus(), AiRunStatus::terminalValues(), true)) {
                                return '';
                            }

                            return Html::a(
                                '<i class="bi bi-trash"></i>',
                                ['delete-session', 'id' => $model->id],
                                [
                                    'class' => 'btn btn-sm btn-outline-danger',
                                    'title' => 'Delete session',
                                    'aria-label' => 'Delete session',
                                    'data' => [
                                        'confirm' => 'Delete this session? (' . $model->getSessionRunCount() . ' runs will be removed)',
                                        'method' => 'post',
                                    ],
                                ]
                            );
                        },
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
    var timer = null;
    var INTERVAL = 5000;

    function start() {
        btn.classList.add('active');
        timer = setInterval(function () {
            window.location.reload();
        }, INTERVAL);
    }

    function stop() {
        clearInterval(timer);
        timer = null;
        btn.classList.remove('active');
    }

    btn.addEventListener('click', function () {
        if (timer) {
            stop();
            sessionStorage.removeItem('ai-runs-auto-refresh');
        } else {
            start();
            sessionStorage.setItem('ai-runs-auto-refresh', '1');
        }
    });

    // Resume auto-refresh after reload
    if (sessionStorage.getItem('ai-runs-auto-refresh') === '1')
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
    font-size: 1.1rem;
}
#auto-refresh-btn.active {
    color: var(--bs-primary) !important;
}
#auto-refresh-btn.active .auto-refresh-icon {
    animation: spin-refresh 1s linear infinite;
}
.ai-runs-table {
    table-layout: fixed;
}
.ai-runs-table tr.ai-run-active {
    border-left: 3px solid var(--bs-warning);
    background-color: rgba(var(--bs-warning-rgb), 0.05);
}
.ai-runs-table tr.ai-run-terminal {
    opacity: 0.75;
}
.ai-runs-table tr.ai-run-terminal:hover {
    opacity: 1;
}
.badge.badge-provider-claude {
    background-color: #d97757;
    color: #fff;
}
.badge.badge-provider-codex {
    background-color: #10a37f;
    color: #fff;
}
CSS;
$this->registerJs($js);
$this->registerCss($css);
?>
