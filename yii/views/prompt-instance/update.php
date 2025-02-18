<?php /** @noinspection BadExpressionStatementJS */
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

                <?= $form->field($model, 'final_prompt')->hiddenInput(['id' => 'final-prompt'])->label(false) ?>

                <div class="form-group">
                    <table class="table table-borderless" style="margin-bottom: 0;">
                        <tr>
                            <th style="width: 20%;">Template</th>
                            <td><?= Html::encode($model->template->name ?? 'N/A') ?></td>
                        </tr>
                    </table>
                </div>

                <div class="resizable-editor-container mb-3">
                    <div id="editor" class="resizable-editor">
                        <?= $model->final_prompt ?>
                    </div>
                </div>

                <div class="form-group mt-4 text-end">
                    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
                    <?= Html::submitButton('Update', ['class' => 'btn btn-primary']) ?>
                </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>
</div>
<?php
$templateBody = json_encode($model->final_prompt);
$script = <<<JS
var quill = new Quill('#editor', {
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
    quill.clipboard.dangerouslyPasteHTML($templateBody);
} catch (error) {
    console.error('Error injecting template body:', error);
}
quill.on('text-change', function() {
    document.querySelector('#final-prompt').value = quill.root.innerHTML;
});
JS;
$this->registerJs($script);
?>
