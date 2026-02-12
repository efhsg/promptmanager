<?php

use app\models\Note;
use app\models\NoteSearch;
use app\presenters\PromptInstancePresenter;
use common\enums\NoteType;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var NoteSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var app\models\Project|null $currentProject */
/** @var bool $isAllProjects */
/** @var array $projectList */
/** @var bool $showChildren */

$this->title = 'Notes';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="note-index container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <div class="btn-group">
            <?= Html::a('New Note', ['create'], ['class' => 'btn btn-primary']) ?>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="visually-hidden">Toggle Dropdown</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#youtubeImportModal">
                        <i class="bi bi-youtube me-2"></i>From YouTube
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#noteImportModal">
                        <i class="bi bi-file-earmark-text me-2"></i>From Markdown
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <?php if ($isAllProjects): ?>
        <div class="alert alert-info">
            Showing notes from all projects.
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <?php if ($showChildren): ?>
            <?= Html::a('Hide children', ['index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
        <?php else: ?>
            <?= Html::a('Show all', ['index', 'show_all' => 1], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Note List</strong>
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
                'emptyText' => 'No notes yet.',
                'rowOptions' => fn($model) => [
                    'onclick' => 'window.location.href = "' . Url::to(['view', 'id' => $model->id]) . '";',
                    'style' => 'cursor: pointer;',
                ],
                'columns' => [
                    [
                        'attribute' => 'name',
                        'format' => 'raw',
                        'value' => static function (Note $model): string {
                            $name = $model->name;
                            if ($name === null || $name === '') {
                                $name = 'N/A';
                            }
                            $truncated = StringHelper::truncate($name, 50, '...');
                            $plain = StringHelper::truncate(
                                PromptInstancePresenter::extractPlain($model->content ?? ''),
                                500,
                                '...',
                            );
                            $html = Html::tag('span', Html::encode($truncated), [
                                'title' => $plain,
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'bottom',
                            ]);
                            $childCount = (int) ($model->child_count ?? 0);
                            if ($childCount > 0) {
                                $html .= ' ' . Html::tag('span', "<i class=\"bi bi-diagram-3\"></i> $childCount", [
                                    'class' => 'badge rounded-pill bg-primary bg-opacity-75',
                                    'title' => "$childCount child note(s)",
                                    'data-bs-toggle' => 'tooltip',
                                ]);
                            }
                            return $html;
                        },
                    ],
                    [
                        'attribute' => 'type',
                        'format' => 'raw',
                        'value' => static function (Note $model): string {
                            $noteType = NoteType::resolve($model->type);
                            $label = $noteType ? $noteType->label() : $model->type;
                            $class = match ($noteType) {
                                NoteType::SUMMATION => 'bg-info',
                                NoteType::IMPORT => 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };
                            return Html::tag('span', Html::encode($label), ['class' => "badge $class"]);
                        },
                    ],
                    [
                        'attribute' => 'project_id',
                        'label' => 'Scope',
                        'value' => fn(Note $model) => $model->project ? $model->project->name : 'Global',
                    ],
                    [
                        'attribute' => 'updated_at',
                        'format' => ['datetime', 'php:Y-m-d H:i'],
                    ],
                    [
                        'class' => ActionColumn::class,
                        'urlCreator' => fn($action, $model) => Url::toRoute([$action, 'id' => $model->id]),
                        'template' => '{claude} {update} {delete}',
                        'buttonOptions' => ['data-confirm' => false],
                        'buttons' => [
                            'claude' => function ($url, Note $model) {
                                $disabled = $model->project_id === null;
                                $tooltip = $disabled ? 'Project required' : 'Talk to Claude';
                                $claudeUrl = $disabled ? '#' : Url::to(['/claude/index', 'p' => $model->project_id, 'breadcrumbs' => Json::encode([
                                    ['label' => 'Notes', 'url' => Url::to(['/note/index'])],
                                    ['label' => $model->name, 'url' => Url::to(['/note/view', 'id' => $model->id])],
                                ])]);
                                return Html::button('<i class="bi bi-terminal-fill"></i>', [
                                    'class' => 'btn btn-link p-0 claude-launch-btn' . ($disabled ? ' disabled text-muted' : ''),
                                    'title' => $tooltip,
                                    'data-bs-toggle' => 'tooltip',
                                    'data-id' => $model->id,
                                    'data-claude-url' => $claudeUrl,
                                    'disabled' => $disabled,
                                ]);
                            },
                        ],
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>

<?= $this->render('_youtube-import-modal', [
    'currentProject' => $currentProject,
    'projectList' => $projectList,
]) ?>

<?= $this->render('_import-modal') ?>

<?php
$fetchUrl = Json::encode(Url::to(['/note/fetch-content']));
$script = <<<JS
        document.querySelectorAll('.claude-launch-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (this.disabled) return;

                var id = this.dataset.id;
                var claudeUrl = this.dataset.claudeUrl;
                var button = this;
                button.disabled = true;

                var sep = {$fetchUrl}.indexOf('?') === -1 ? '?' : '&';
                fetch({$fetchUrl} + sep + 'id=' + id, {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.content) {
                        sessionStorage.setItem('claudePromptContent', typeof data.content === 'string' ? data.content : JSON.stringify(data.content));
                    }
                    window.location.href = claudeUrl;
                })
                .catch(function() {
                    window.location.href = claudeUrl;
                })
                .finally(function() {
                    button.disabled = false;
                });
            });
        });
    JS;
$this->registerJs($script);
?>
