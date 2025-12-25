<?php
/** @noinspection BadExpressionStatementJS */
/** @noinspection JSUnresolvedReference */

use app\assets\QuillAsset;
use common\enums\CopyType;
use yii\helpers\Html;

/** @var yii\web\View $this */

QuillAsset::register($this);

$this->title = 'Scratch Pad';
$copyTypes = CopyType::labels();
?>

<div class="scratch-pad-create container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-11 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0"><?= Html::encode($this->title) ?></h1>
                <div class="d-flex align-items-center gap-2">
                    <?= Html::dropDownList('copyFormat', CopyType::MD->value, $copyTypes, [
                        'id' => 'copy-format-select',
                        'class' => 'form-select form-select-sm',
                        'style' => 'width: auto;',
                    ]) ?>
                    <button type="button" id="copy-content-btn" class="btn btn-sm btn-primary" title="Copy to clipboard">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <div class="resizable-editor-container">
                        <div id="editor" class="resizable-editor" style="min-height: 400px;"></div>
                    </div>
                </div>
            </div>
            <div class="mt-3 text-muted small">
                Quick edit imported markdown content. Use the Copy button to copy formatted content to your clipboard.
            </div>
        </div>
    </div>
</div>

<label for="copy-content-hidden"></label><textarea id="copy-content-hidden" style="display: none;"></textarea>

<?php
$script = <<<JS
window.quill = new Quill('#editor', {
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

// Check for imported data in localStorage
const importedData = localStorage.getItem('scratchPadContent');
if (importedData) {
    try {
        const parsed = JSON.parse(importedData);
        if (parsed.content) {
            const delta = typeof parsed.content === 'string' ? JSON.parse(parsed.content) : parsed.content;
            window.quill.setContents(delta);
        }
        localStorage.removeItem('scratchPadContent');
    } catch (e) {
        console.error('Failed to parse imported data:', e);
    }
}

// Copy functionality with format conversion
document.getElementById('copy-content-btn').addEventListener('click', function() {
    const formatSelect = document.getElementById('copy-format-select');
    const selectedFormat = formatSelect.value;
    const deltaContent = JSON.stringify(window.quill.getContents());

    // Convert delta to selected format via AJAX
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
            // Fallback to plain text
            const text = window.quill.getText();
            navigator.clipboard.writeText(text);
        }
    })
    .catch(function(err) {
        console.error('Failed to copy:', err);
        // Fallback to plain text
        const text = window.quill.getText();
        navigator.clipboard.writeText(text);
    });
});
JS;

$this->registerJs($script);
?>
