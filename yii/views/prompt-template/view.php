<?php /** @noinspection JSUnresolvedReference */
/** @noinspection DuplicatedCode */

/** @noinspection PhpUnhandledExceptionInspection */

use app\assets\QuillAsset;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\PromptTemplate $model */

QuillAsset::register($this);

$this->title = 'View - ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);

?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"></h1>
            <div>
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
                <strong>Template Details</strong>
            </div>
            <div class="card-body">
                <?= DetailView::widget([
                    'model' => $model,
                    'options' => ['class' => 'table table-borderless'],
                    'attributes' => [
                        [
                            'attribute' => 'project_id',
                            'label' => 'Project',
                            'value' => function ($model) {
                                return $model->project ? $model->project->name : 'N/A';
                            },
                        ],
                        'name',
                        [
                            'attribute' => 'description',
                            'format' => 'ntext',
                            'label' => 'Description',
                        ],
                        [
                            'attribute' => 'template_body',
                            'format' => 'raw',
                            'label' => 'Template Body',
                            'value' => '<div id="editor-container" style="height: 300px;"></div>',
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
$templateBody = json_encode($model->template_body);
$script = <<<JS
var quill = new Quill('#editor-container', {
    theme: 'snow',
    readOnly: true,
    modules: {
        toolbar: false
    }
});

quill.root.innerHTML = $templateBody;
JS;
$this->registerJs($script);
?>