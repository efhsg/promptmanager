<?php
/** @noinspection PhpUnhandledExceptionInspection */

use app\presenters\PromptInstancePresenter;
use app\models\PromptInstance;
use app\widgets\MobileCardView;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\PromptInstanceSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Prompt Instances';
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => null,
]);
?>

<div class="prompt-instance-index container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Create Prompt Instance', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <strong class="mb-0">Prompt Instance List</strong>
                <?php $form = ActiveForm::begin([
                    'action' => ['index'],
                    'method' => 'get',
                    'options' => ['class' => 'd-flex flex-wrap align-items-center gap-2 mb-0'],
                ]); ?>
                    <?= $form->field($searchModel, 'label', [
                        'options' => ['class' => 'mb-0'],
                    ])->textInput([
                        'class' => 'form-control',
                        'placeholder' => 'Search by label',
                    ])->label(false) ?>
                    <div class="d-flex align-items-center gap-2">
                        <?= Html::submitButton('Search', ['class' => 'btn btn-outline-primary']) ?>
                        <?php if ($searchModel->label !== ''): ?>
                            <?= Html::a('Reset', ['index'], ['class' => 'btn btn-link px-2']) ?>
                        <?php endif; ?>
                    </div>
                <?php ActiveForm::end(); ?>
            </div>
        </div>
        <div class="card-body p-0">
            <?= MobileCardView::widget([
                'dataProvider' => $dataProvider,
                'titleAttribute' => static fn(PromptInstance $model) => $model->label ?: 'Instance #' . $model->id,
                'metaAttributes' => [
                    static fn(PromptInstance $model) => $model->template?->name,
                    static fn(PromptInstance $model) => Yii::$app->formatter->asDatetime($model->updated_at, 'php:Y-m-d H:i'),
                ],
                'metaLabels' => [0 => 'Template', 1 => 'Updated'],
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
                    'aria-label' => 'Prompt Instance Table',
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
                        'attribute' => 'label',
                        'label' => 'Label',
                        'format' => 'raw',
                        'value' => static function (PromptInstance $model): string {
                            $label = $model->label;
                            if ($label === null || $label === '') {
                                $label = 'N/A';
                            }
                            $truncated = StringHelper::truncate($label, 50, '...');
                            $plain = StringHelper::truncate(
                                PromptInstancePresenter::extractPlain($model->final_prompt),
                                500,
                                '...',
                            );
                            return Html::tag('span', Html::encode($truncated), [
                                'title' => $plain,
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'bottom',
                            ]);
                        },
                    ],
                    [
                        'attribute' => 'template_id',
                        'label' => 'Template Name',
                        'enableSorting' => true,
                        'value' => static function (PromptInstance $model): string {
                            return $model->template ? $model->template->name : 'N/A';
                        },
                    ],
                    [
                        'attribute' => 'updated_at',
                        'format' => ['datetime', 'php:Y-m-d H:i:s'],
                        'label' => 'Updated At',
                    ],
                    [
                        'class' => ActionColumn::class,
                        'urlCreator' => function ($action, $model) {
                            $id = is_array($model) ? $model['id'] : $model->id;
                            return Url::toRoute([$action, 'id' => $id]);
                        },
                        'template' => '{ai} {update} {delete}',
                        'buttonOptions' => ['data-confirm' => false],
                        'buttons' => [
                            'ai' => function ($url, PromptInstance $model) {
                                $projectId = $model->template?->project_id;
                                $disabled = $projectId === null;
                                $tooltip = $disabled ? 'Project required' : 'Start Dialog';
                                $aiChatUrl = $disabled ? '#' : Url::to(['/ai-chat/index', 'p' => $projectId, 'breadcrumbs' => Json::encode([
                                    ['label' => 'Prompt Instances', 'url' => Url::to(['/prompt-instance/index'])],
                                    ['label' => $model->label ?: 'Instance #' . $model->id, 'url' => Url::to(['/prompt-instance/view', 'id' => $model->id])],
                                ])]);
                                return Html::button('<i class="bi bi-terminal-fill"></i>', [
                                    'class' => 'btn btn-link p-0 ai-launch-btn' . ($disabled ? ' disabled text-muted' : ''),
                                    'title' => $tooltip,
                                    'data-bs-toggle' => 'tooltip',
                                    'data-id' => $model->id,
                                    'data-ai-chat-url' => $aiChatUrl,
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

<?php
$fetchUrl = Json::encode(Url::to(['/prompt-instance/fetch-content']));
$script = <<<JS
        document.querySelectorAll('.ai-launch-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (this.disabled) return;

                var id = this.dataset.id;
                var aiChatUrl = this.dataset.aiChatUrl;
                var button = this;
                button.disabled = true;

                var sep = {$fetchUrl}.indexOf('?') === -1 ? '?' : '&';
                fetch({$fetchUrl} + sep + 'id=' + id, {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.content) {
                        sessionStorage.setItem('aiPromptContent', typeof data.content === 'string' ? data.content : JSON.stringify(data.content));
                    }
                    window.location.href = aiChatUrl;
                })
                .catch(function() {
                    window.location.href = aiChatUrl;
                })
                .finally(function() {
                    button.disabled = false;
                });
            });
        });
    JS;
$this->registerJs($script);
?>
