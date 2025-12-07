<?php /** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection BadExpressionStatementJS */
/** @noinspection JSUnresolvedReference */
/** @noinspection JSUnnecessarySemicolon */

use app\assets\QuillAsset;
use app\models\PromptInstance;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;

/** @var View $this */
/** @var PromptInstance $model */
QuillAsset::register($this);

$this->title = 'Update - ' . Yii::$app->formatter->asDatetime($model->updated_at, 'php:Y-m-d H:i:s');
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>
    <div class="prompt-instance-update container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-10">
                <div class="border rounded p-4 shadow bg-white mt-4">
                    <?php $form = ActiveForm::begin([
                        'id' => 'prompt-instance-update-form',
                        'enableClientValidation' => true,
                    ]); ?>

                    <?= $form->field($model, 'label')->textInput([
                        'maxlength' => true,
                        'placeholder' => 'Enter a label',
                    ]) ?>

                    <?= $form->field($model, 'final_prompt')->hiddenInput(['id' => 'final-prompt'])->label(false) ?>

                    <div class="form-group"><?= Html::encode($model->template->name ?? 'N/A') ?></div>

                    <div class="resizable-editor-container mb-3">
                        <div id="editor" class="resizable-editor">
                        </div>
                    </div>

                    <div class="form-group mt-4 text-end">
                        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
                        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
<?php
$script = <<<JS
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline', 'strike', 'code'],
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

var finalPromptData = $model->final_prompt;
try {
    if (finalPromptData && typeof finalPromptData === 'object' && finalPromptData.ops) {
        quill.setContents(finalPromptData);
    } else {
        quill.clipboard.dangerouslyPasteHTML(finalPromptData);
    }
} catch (error) {
    console.error('Error loading delta data, falling back to HTML:', error);
    quill.clipboard.dangerouslyPasteHTML(finalPromptData);
}

quill.on('text-change', function() {
    var deltaObj = quill.getContents();
    document.querySelector('#final-prompt').value = JSON.stringify(deltaObj);
});
JS;
$this->registerJs($script);
?>
