<?php /** @noinspection JSUnresolvedReference */
use app\assets\QuillAsset;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var yii\widgets\ActiveForm $form */

QuillAsset::register($this);
?>

<div class="project-form focus-on-first-field">
    <?php $form = ActiveForm::begin([
        'id' => 'project-form',
        'enableClientValidation' => true,
    ]); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'root_directory')->textInput(['maxlength' => true]) ?>

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
            [{ 'direction': 'rtl' }],
            [{ 'size': ['small', false, 'large', 'huge'] }],
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'font': [] }],
            [{ 'align': [] }],
            ['link', 'image'],
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
