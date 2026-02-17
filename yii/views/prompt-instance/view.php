<?php
/** @noinspection JSUnresolvedReference */

/** @noinspection DuplicatedCode */

/** @noinspection PhpUnhandledExceptionInspection */

use app\models\PromptInstance;
use app\widgets\QuillViewerWidget;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\PromptInstance $model */

$projectId = $model->template?->project_id;
$canRunAi = $projectId !== null;
$aiTooltip = $canRunAi ? 'Talk to AI' : 'Project required';
$aiChatUrl = $canRunAi
    ? Url::to(['/ai-chat/index', 'p' => $projectId, 'breadcrumbs' => json_encode([
        ['label' => 'Prompt Instances', 'url' => Url::to(['/prompt-instance/index'])],
        ['label' => $model->label ?: 'Instance #' . $model->id, 'url' => Url::to(['/prompt-instance/view', 'id' => $model->id])],
    ])])
    : '#';

$this->title = 'View - ' . Yii::$app->formatter->asDatetime($model->updated_at, 'php:Y-m-d H:i:s');
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>
<div class="container py-4">
    <div class="d-flex justify-content-end align-items-center mb-4">
        <div>
            <?= Html::button('<i class="bi bi-terminal-fill"></i> AI', [
                'class' => 'btn btn-primary me-2 text-nowrap ai-launch-btn' . (!$canRunAi ? ' disabled' : ''),
                'title' => $aiTooltip,
                'data-bs-toggle' => 'tooltip',
                'disabled' => !$canRunAi,
            ]) ?>
            <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary me-2']) ?>
            <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger me-2',
                'data' => [
                    'method' => 'post',
                ],
            ]) ?>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header">
            <strong>Prompt Instance Details</strong>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'options' => ['class' => 'table table-borderless'],
                'attributes' => [
                    [
                        'attribute' => 'label',
                        'label' => 'Label',
                        'value' => static function (PromptInstance $model): string {
                            $label = $model->label;
                            return $label === null || $label === '' ? 'N/A' : $label;
                        },
                    ],
                    [
                        'attribute' => 'template_name',
                        'label' => 'Template',
                        'value' => static function (PromptInstance $model): string {
                            return $model->template ? $model->template->name : 'N/A';
                        },
                    ],
                    [
                        'attribute' => 'final_prompt',
                        'format' => 'raw',
                        'label' => 'Prompt',
                        'value' => static function (PromptInstance $model) {
                            return QuillViewerWidget::widget([
                                'content' => $model->final_prompt,
                                'options' => [
                                    'style' => 'height: 300px;',
                                ],
                                'enableExport' => true,
                                'exportProjectId' => $model->template?->project_id,
                                'exportEntityName' => $model->label ?: 'Prompt Instance #' . $model->id,
                                'exportRootDirectory' => $model->template?->project?->root_directory,
                            ]);
                        },
                    ],
                    [
                        'attribute' => 'created_at',
                        'format' => ['datetime', 'php:Y-m-d H:i:s'],
                    ],
                    [
                        'attribute' => 'updated_at',
                        'format' => ['datetime', 'php:Y-m-d H:i:s'],
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>

<?php
$contentDelta = json_encode($model->final_prompt, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$aiChatUrlJs = json_encode($aiChatUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

$script = <<<JS
        document.querySelectorAll('.ai-launch-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.disabled) return;
                var content = $contentDelta;
                if (content) {
                    sessionStorage.setItem('aiPromptContent', typeof content === 'string' ? content : JSON.stringify(content));
                }
                window.location.href = $aiChatUrlJs;
            });
        });
    JS;
$this->registerJs($script);
?>
