<?php /** @noinspection JSUnresolvedReference */
use app\assets\QuillAsset;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $availableProjects */

QuillAsset::register($this);
?>

<div class="project-form focus-on-first-field">
    <?php $form = ActiveForm::begin([
        'id' => 'project-form',
        'enableClientValidation' => true,
    ]); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'label')
        ->textInput(['maxlength' => true, 'placeholder' => 'Short identifier'])
        ->hint('Optional short code or label for quick identification.') ?>

    <?= $form->field($model, 'root_directory')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'blacklisted_directories')
        ->textInput(['placeholder' => 'vendor,runtime,web,npm,docker'])
        ->hint('Comma-separated directories under the root to exclude (e.g. vendor,runtime,web,npm,docker).') ?>

    <?= $form->field($model, 'allowed_file_extensions')
        ->textInput(['maxlength' => true, 'placeholder' => 'php,scss,html'])
        ->hint('Comma-separated extensions; leave blank to allow all.') ?>

    <?= $form->field($model, 'prompt_instance_copy_format')
        ->dropDownList($model::getPromptInstanceCopyFormatOptions())
        ->hint('Format used by prompt instance copy buttons (e.g. Markdown).') ?>

    <?php
    echo $form->field($model, 'linkedProjectIds')
        ->widget(Select2Widget::class, [
            'items' => $availableProjects,
            'options' => [
                'placeholder' => 'Select projects to link...',
                'multiple' => true,
            ],
            'settings' => [
                'minimumResultsForSearch' => 0,
            ],
        ])
        ->hint('Select other projects whose fields can be used as external (EXT) fields in prompt instances.');
    ?>

    <?= $form->field($model, 'description')
        ->hiddenInput(['id' => 'project-description'])
        ->label('Description') ?>

    <div class="resizable-editor-container mb-3">
        <div id="project-editor" class="resizable-editor"></div>
    </div>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', Yii::$app->request->referrer ?: ['index'], [
            'class' => 'btn btn-secondary me-2',
        ]) ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php
$templateBody = json_encode($model->description);
$script = <<<JS
var quill = new Quill('#project-editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'indent': '-1' }, { 'indent': '+1' }],
            [{ 'size': ['small', false, 'large', 'huge'] }],
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'align': [] }],
            ['clean']
        ]
    }
});

try {
    quill.setContents(JSON.parse($templateBody))
} catch (error) {
    console.error('Error injecting template body:', error);
}

quill.on('text-change', function() {
    document.querySelector('#project-description').value = JSON.stringify(quill.getContents());
});
JS;

$this->registerJs($script);
?>
