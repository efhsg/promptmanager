<?php
/** @noinspection JSUnresolvedReference */

use app\assets\QuillAsset;
use common\enums\CopyType;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\ScratchPad $model */

QuillAsset::register($this);
$copyTypes = CopyType::labels();
?>

<div class="scratch-pad-form focus-on-first-field">
    <?php $form = ActiveForm::begin(['id' => 'scratch-pad-form']); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'content')->hiddenInput(['id' => 'scratch-pad-content'])->label(false) ?>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label mb-0">Content</label>
        <div class="input-group input-group-sm" style="width: auto;">
            <?= Html::dropDownList('copyFormat', CopyType::MD->value, $copyTypes, [
                'id' => 'copy-format-select',
                'class' => 'form-select',
                'style' => 'width: auto;',
            ]) ?>
            <button type="button" id="copy-content-btn" class="btn btn-primary text-nowrap" title="Copy to clipboard">
                <i class="bi bi-clipboard"></i> Copy to Clipboard
            </button>
        </div>
    </div>

    <div class="resizable-editor-container mb-3">
        <div id="scratch-pad-editor" class="resizable-editor" style="min-height: 300px;"></div>
    </div>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', Yii::$app->request->referrer ?: ['index'], ['class' => 'btn btn-secondary me-2']) ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php
$content = json_encode($model->content);
$script = <<<JS
var quill = new Quill('#scratch-pad-editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline', 'strike', 'code'],
            ['blockquote', 'code-block'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'indent': '-1' }, { 'indent': '+1' }],
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            [{ 'align': [] }],
            ['clean']
        ]
    }
});

try {
    quill.setContents(JSON.parse($content))
} catch (error) {
    console.error('Error loading content:', error);
}

quill.on('text-change', function() {
    document.querySelector('#scratch-pad-content').value = JSON.stringify(quill.getContents());
});

// Copy functionality with format conversion
document.getElementById('copy-content-btn').addEventListener('click', function() {
    const formatSelect = document.getElementById('copy-format-select');
    const selectedFormat = formatSelect.value;
    const deltaContent = JSON.stringify(quill.getContents());

    fetch('/scratch-pad/convert-format', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            content: deltaContent,
            format: selectedFormat
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.content !== undefined) {
            navigator.clipboard.writeText(data.content).then(function() {
                const btn = document.getElementById('copy-content-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check"></i> Copied';
                setTimeout(function() {
                    btn.innerHTML = originalText;
                }, 1000);
            });
        } else {
            console.error('Failed to convert format:', data.message);
            const text = quill.getText();
            navigator.clipboard.writeText(text);
        }
    })
    .catch(function(err) {
        console.error('Failed to copy:', err);
        const text = quill.getText();
        navigator.clipboard.writeText(text);
    });
});
JS;

$this->registerJs($script);
?>
