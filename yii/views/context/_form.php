<?php /** @noinspection BadExpressionStatementJS */

/** @noinspection JSUnresolvedReference */

use app\assets\QuillAsset;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Context $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $projects */

QuillAsset::register($this);
?>

    <div class="context-form focus-on-first-field">
        <?php $form = ActiveForm::begin([
            'id' => 'context-form',
            'enableClientValidation' => true,
        ]); ?>

        <?= $form->field($model, 'project_id')->dropDownList(
            $projects,
            ['prompt' => 'Select a Project']
        )->label('Project') ?>

        <?= $form->field($model, 'name')->textInput(['maxlength' => true])->label('Context Name') ?>

        <?= $form->field($model, 'is_default')->dropDownList(
            [0 => 'No', 1 => 'Yes']
        )->label('Default on') ?>

        <?= $form->field($model, 'share')->dropDownList(
            [0 => 'No', 1 => 'Yes']
        )->label('Share') ?>

        <?= $form->field($model, 'content')->hiddenInput(['id' => 'context-content'])->label(false) ?>

        <div class="resizable-editor-container mb-3">
            <div id="editor" class="resizable-editor">
                <?= $model->content ?>
            </div>
        </div>

        <div class="form-group mt-4 text-end">
            <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
            <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>

<?php
$templateBody = json_encode($model->content);
$script = <<<JS
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'indent': '-1' }, { 'indent': '+1' }],
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
    console.error('Error injecting template body:', error)
}
quill.on('text-change', function() {
    document.querySelector('#context-content').value = JSON.stringify(quill.getContents())
});
JS;
$this->registerJs($script);
?>
