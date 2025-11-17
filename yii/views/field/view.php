<?php /** @noinspection PhpUnhandledExceptionInspection */

use app\assets\HighlightAsset;
use app\widgets\QuillViewerWidget;
use common\constants\FieldConstants;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Field $model */

$this->title = 'View ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);

HighlightAsset::register($this);

$this->registerCss(<<<CSS
#path-preview-modal .modal-content {
    background-color: #2b2b2b;
    color: #dcdcdc;
    border: 1px solid #3c3f41;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
}

#path-preview-modal .modal-header {
    background: linear-gradient(180deg, #3c3f41 0%, #313335 100%);
    border-bottom: 1px solid #4c5052;
    color: #e0e0e0;
}

#path-preview-modal .modal-title {
    font-family: "JetBrains Mono", "Fira Code", Menlo, Consolas, monospace;
    font-size: 0.95rem;
    letter-spacing: 0.02em;
}

#path-preview-modal .badge {
    background-color: #4c5052;
    color: #f0f0f0;
}

#path-preview-modal .modal-body {
    background-color: #2b2b2b;
    border-top: 1px solid #1f1f1f;
    padding: 1.5rem;
}

#path-preview-modal pre {
    font-family: "JetBrains Mono", "Fira Code", Menlo, Consolas, monospace;
    font-size: 0.9rem;
    background-color: #2b2b2b;
    color: #dcdcdc;
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid #3a3d40;
    max-height: 70vh;
}

#path-preview-modal code {
    color: inherit;
    white-space: pre;
}

#path-preview-modal .btn-close-white {
    filter: invert(1) grayscale(100%);
}
CSS);

$resolveLanguage = static function (string $path): array {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
    return match ($extension) {
        'php' => ['php', 'PHP'],
        'js' => ['javascript', 'JavaScript'],
        'ts' => ['typescript', 'TypeScript'],
        'json' => ['json', 'JSON'],
        'css' => ['css', 'CSS'],
        'html', 'htm' => ['xml', 'HTML'],
        'xml' => ['xml', 'XML'],
        'md' => ['markdown', 'Markdown'],
        'yaml', 'yml' => ['yaml', 'YAML'],
        'sh', 'bash', 'zsh' => ['bash', 'Shell'],
        'py' => ['python', 'Python'],
        default => ['plaintext', 'Plain Text'],
    };
};

$showPath = in_array($model->type, FieldConstants::PATH_FIELD_TYPES, true) && !empty($model->content);
$canPreviewPath = $showPath && in_array($model->type, FieldConstants::PATH_PREVIEWABLE_FIELD_TYPES, true);
$pathLanguage = $canPreviewPath ? $resolveLanguage($model->content) : null;
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
        <div>
            <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary me-2']) ?>
            <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger me-2',
            ]) ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Field Details</strong>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'options' => ['class' => 'table table-borderless'],
                'attributes' => array_filter([
                    [
                        'attribute' => 'projectName',
                        'label' => 'Project Name',
                        'format' => 'raw',
                        'value' => static function ($model): string {
                            return $model->project
                                ? Html::encode($model->project->name)
                                : Yii::$app->formatter->nullDisplay;
                        },
                    ],
                    'name',
                    'type',
                    [
                        'attribute' => 'selected_by_default',
                        'value' => $model->selected_by_default ? 'Yes' : 'No',
                    ],
                    'label',
                    [
                        'attribute' => 'created_at',
                        'format' => ['datetime', 'php:Y-m-d H:i:s'],
                    ],
                    [
                        'attribute' => 'updated_at',
                        'format' => ['datetime', 'php:Y-m-d H:i:s'],
                    ],
                    ($showPath)
                        ? [
                        'label' => 'Path',
                        'format' => 'raw',
                            'value' => $canPreviewPath
                                ? Html::button(
                                    Html::encode($model->content),
                                    [
                                        'type' => 'button',
                                        'class' => 'btn btn-link p-0 text-decoration-underline path-preview font-monospace',
                                        'data-url' => Url::to([
                                            'field/path-preview',
                                            'id' => $model->id,
                                            'path' => $model->content,
                                        ]),
                                        'data-language' => $pathLanguage[0] ?? 'plaintext',
                                        'data-language-label' => $pathLanguage[1] ?? 'Plain Text',
                                        'data-path-label' => $model->content,
                                    ]
                                )
                                : Html::tag(
                                    'span',
                                    Html::encode($model->content),
                                    [
                                        'class' => 'font-monospace text-break',
                                        'title' => $model->content,
                                    ]
                                ),
                    ]
                        : null,
                    (in_array($model->type, FieldConstants::CONTENT_FIELD_TYPES, true) && !empty($model->content))
                        ? [
                        'attribute' => 'content',
                        'format' => 'raw',
                        'label' => 'Field Content',
                            'value' => static function ($model): string {
                                return QuillViewerWidget::widget([
                                'content' => $model->content,
                                    'copyButtonOptions' => [
                                        'class' => 'btn btn-sm position-absolute',
                                        'style' => 'bottom: 10px; right: 20px;',
                                        'title' => 'Copy to clipboard',
                                        'aria-label' => 'Copy content to clipboard',
                                        'copyFormat' => 'md',
                                    ],
                            ]);
                        },
                    ]
                        : null,
                ]),
            ]) ?>
        </div>
    </div>

    <?php if (!empty($model->fieldOptions)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <strong>Field Options</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th>Value</th>
                        <th>Label</th>
                        <th>Default on</th>
                        <th>Order</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($model->fieldOptions as $option): ?>
                        <tr>
                            <td><?= Html::encode($option->value) ?></td>
                            <td><?= Html::encode($option->label) ?></td>
                            <td><?= $option->selected_by_default ? 'Yes' : 'No' ?></td>
                            <td><?= Html::encode($option->order) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
echo <<<HTML
<div class="modal fade" id="path-preview-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0 flex-grow-1 text-truncate">
                    <span id="path-preview-title">File Preview</span>
                    <span id="path-preview-language" class="badge text-bg-secondary ms-2 d-none"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre class="mb-0 border-0">
                    <code id="path-preview-content" class="font-monospace"></code>
                </pre>
            </div>
        </div>
    </div>
</div>
HTML;

$js = <<<JS
function pathPreview() {
    var modalElement = document.getElementById('path-preview-modal');
    if (!modalElement || !window.bootstrap) {
        return;
    }
    var modalBody = document.getElementById('path-preview-content');
    var modalTitle = document.getElementById('path-preview-title');
    var modal = new bootstrap.Modal(modalElement);
    var languageBadge = document.getElementById('path-preview-language');
    var defaultErrorMessage = 'Preview error: Unable to load preview.';

    function resetLanguageBadge() {
        if (!languageBadge) {
            return;
        }
        languageBadge.textContent = '';
        languageBadge.classList.add('d-none');
    }

    function updateLanguageBadge(label) {
        if (!languageBadge) {
            return;
        }
        languageBadge.textContent = label;
        languageBadge.classList.remove('d-none');
    }

    function applyLanguageClass(language) {
        var classes = ['font-monospace', 'hljs', 'text-light', 'language-' + language];
        modalBody.className = classes.join(' ');
    }

    function setPreviewContent(text) {
        modalBody.textContent = text;
        if (window.hljs) {
            window.hljs.highlightElement(modalBody);
        }
    }

    function showError(message) {
        resetLanguageBadge();
        setPreviewContent(message ? 'Preview error: ' + message : defaultErrorMessage);
    }

    document.querySelectorAll('.path-preview').forEach(function (button) {
        button.addEventListener('click', function () {
            var language = button.getAttribute('data-language') || 'plaintext';
            var languageLabel = button.getAttribute('data-language-label') || language.toUpperCase();
            if (modalTitle) {
                modalTitle.textContent = button.getAttribute('data-path-label') || 'File Preview';
            }
            resetLanguageBadge();
            applyLanguageClass(language);
            setPreviewContent('Loading...');
            modal.show();
            fetch(button.getAttribute('data-url'), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.success) {
                        showError(data.message || '');
                        return;
                    }
                    updateLanguageBadge(languageLabel);
                    setPreviewContent(data.preview);
                })
                .catch(function (error) {
                    console.error('Path preview failed:', error);
                    showError('');
                });
        });
    });
}
pathPreview();
JS;
$this->registerJs($js);
?>
