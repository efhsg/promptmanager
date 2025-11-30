<?php
/** @noinspection JSUnresolvedReference */

/** @noinspection PhpUnhandledExceptionInspection */

use app\assets\QuillAsset;
use app\services\CopyFormatConverter;
use app\widgets\PathPreviewWidget;
use app\widgets\PathSelectorWidget;
use common\enums\CopyType;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;

/* @var string $templateBody */
/* @var array $fields */

QuillAsset::register($this);
$this->registerCss('.generated-prompt-form h2{font-size:1.25rem;line-height:1.3;margin-bottom:0.5rem;}');

$delta = json_decode($templateBody, true);
if (!is_array($delta) || !isset($delta['ops'])) {
    throw new InvalidArgumentException('Template is not in valid Delta format.');
}

$copyFormatConverter = new CopyFormatConverter();
$templateHtml = $copyFormatConverter->convertFromQuillDelta($templateBody, CopyType::HTML);

/** Normalize a value to a JSON-encoded Quill Delta or '' */
$toDeltaJson = static function (mixed $value): string {
    if (is_array($value)) {
        return isset($value['ops']) ? json_encode($value, JSON_UNESCAPED_UNICODE) : '';
    }
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return (is_array($decoded) && isset($decoded['ops'])) ? $value : '';
    }
    return '';
};

$view = $this;
$templateRendered = preg_replace_callback(
        '/(?:GEN:|PRJ:)\{\{(\d+)}}/',
        static function (array $matches) use ($fields, $toDeltaJson, $view) {
            $select2Settings = [
                    'minimumResultsForSearch' => 0,
                    'templateResult' => new JsExpression(
                            "
            function(state) {
                if (!state.id) return state.text;
                return $('<span></span>').text(state.text);
            }
        "
                    ),
                    'templateSelection' => new JsExpression(
                            "
            function(state) {
                if (!state.id) return state.text;
                return $('<span></span>').text(state.text);
            }
        "
                    ),
            ];
            $placeholder = $matches[1];
            if (!isset($fields[$placeholder])) {
                return $matches[0];
            }

            $field = $fields[$placeholder];
            $fieldType = (string)($field['type'] ?? 'text');
            $name = "PromptInstanceForm[fields][$placeholder]";

            if (in_array($fieldType, ['text', 'code'], true)) {
                $hiddenId = "hidden-$placeholder";
                $editorId = "editor-$placeholder";

                $defaultValue = $toDeltaJson($field['default'] ?? '');

                $label = trim((string)($field['label'] ?? ''));
                $custom = trim((string)($field['placeholder'] ?? ''));

                $placeholderText = $custom !== ''
                        ? $custom
                        : ($fieldType === 'code'
                                ? ($label !== '' ? "Paste or write code for {$label}…" : 'Paste or write code…')
                                : ($label !== '' ? "Write {$label}…" : 'Type your content…'));

                return
                        Html::hiddenInput($name, $defaultValue, ['id' => $hiddenId]) .
                        Html::tag(
                                'div',
                                Html::tag('div', '', [
                                        'id' => $editorId,
                                        'class' => 'resizable-editor',
                                        'style' => 'min-height: 150px;',
                                        'data-editor' => 'quill',
                                        'data-target' => $hiddenId,
                                        'data-config' => json_encode([
                                                'theme' => 'snow',
                                                'placeholder' => $placeholderText,
                                                'modules' => [
                                                        'toolbar' => [
                                                                ['bold', 'italic', 'underline', 'strike'],
                                                                ['blockquote', 'code-block'],
                                                                [['list' => 'ordered'], ['list' => 'bullet']],
                                                                [['indent' => '-1'], ['indent' => '+1']],
                                                                [['header' => [1, 2, 3, 4, 5, 6, false]]],
                                                                [['align' => []]],
                                                                ['clean'],
                                                        ],
                                                ],
                                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                                ]),
                                ['class' => 'resizable-editor-container mb-3']
                        );
            }

            return match ($fieldType) {
                'select', 'multi-select', 'select-invert' => Select2Widget::widget([
                        'name' => $fieldType === 'multi-select' ? $name . '[]' : $name,
                        'id' => "field-$placeholder",
                        'value' => $field['default'],
                        'items' => $field['options'],
                        'options' => [
                                'placeholder' => 'Select ' . ($fieldType === 'multi-select' ? 'options' : 'an option') . '...',
                                'multiple' => $fieldType === 'multi-select',
                        ],
                        'settings' => $select2Settings,
                ]),
                'file' => (function () use ($field, $placeholder, $name, $view) {
                        $hiddenInputId = "field-$placeholder";
                        $pathPreviewWrapperId = "path-preview-wrapper-$placeholder";
                        $changeButtonId = "change-path-btn-$placeholder";
                        $modalId = "path-modal-$placeholder";
                        $pathSelectorId = "path-selector-$placeholder";
                        $saveButtonId = "save-path-btn-$placeholder";
                        $projectId = $field['project_id'] ?? null;
                        $currentPath = (string)($field['default'] ?? '');

                        $html = Html::hiddenInput($name, $currentPath, ['id' => $hiddenInputId]);
                        $html .= Html::beginTag('div', ['class' => 'd-flex align-items-center gap-2']);
                        $html .= Html::tag('div', PathPreviewWidget::widget([
                                'path' => $currentPath,
                                'previewUrl' => !empty($currentPath) ? Url::to([
                                        'field/path-preview',
                                        'id' => $field['id'] ?? $placeholder,
                                        'path' => $currentPath,
                                ]) : '',
                                'enablePreview' => !empty($currentPath),
                        ]), ['id' => $pathPreviewWrapperId, 'class' => 'flex-grow-1']);
                        $html .= Html::button('Change', [
                                'id' => $changeButtonId,
                                'class' => 'btn btn-sm btn-outline-secondary',
                                'type' => 'button',
                                'data-bs-toggle' => 'modal',
                                'data-bs-target' => "#$modalId",
                        ]);
                        $html .= Html::endTag('div');

                        $modalHtml = Html::beginTag('div', [
                                'class' => 'modal fade',
                                'id' => $modalId,
                                'tabindex' => '-1',
                                'aria-hidden' => 'true',
                        ]);
                        $modalHtml .= Html::beginTag('div', ['class' => 'modal-dialog modal-lg']);
                        $modalHtml .= Html::beginTag('div', ['class' => 'modal-content']);
                        $modalHtml .= Html::beginTag('div', ['class' => 'modal-header']);
                        $modalHtml .= Html::tag('h5', 'Change Path', ['class' => 'modal-title']);
                        $modalHtml .= Html::button('', [
                                'type' => 'button',
                                'class' => 'btn-close',
                                'data-bs-dismiss' => 'modal',
                                'aria-label' => 'Close',
                        ]);
                        $modalHtml .= Html::endTag('div');
                        $modalHtml .= Html::beginTag('div', ['class' => 'modal-body']);
                        $modalHtml .= PathSelectorWidget::widget([
                                'id' => $pathSelectorId,
                                'initialValue' => $currentPath,
                                'pathListUrl' => Url::to(['field/path-list']),
                                'hiddenContentInputId' => $hiddenInputId,
                                'wrapperOptions' => [
                                        'style' => 'display: block;',
                                ],
                        ]);
                        $modalHtml .= Html::endTag('div');
                        $modalHtml .= Html::beginTag('div', ['class' => 'modal-footer']);
                        $modalHtml .= Html::button('Cancel', [
                                'type' => 'button',
                                'class' => 'btn btn-secondary',
                                'data-bs-dismiss' => 'modal',
                        ]);
                        $modalHtml .= Html::button('Ok', [
                                'id' => $saveButtonId,
                                'type' => 'button',
                                'class' => 'btn btn-primary',
                        ]);
                        $modalHtml .= Html::endTag('div');
                        $modalHtml .= Html::endTag('div');
                        $modalHtml .= Html::endTag('div');
                        $modalHtml .= Html::endTag('div');

                        $html .= $modalHtml;

                        $basePreviewUrl = Url::to(['field/path-preview', 'id' => $field['id']]);
                        $script = <<<JS
;(function() {
    const modalElement = document.getElementById('$modalId');
    const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
    const saveBtn = document.getElementById('$saveButtonId');
    const hiddenInput = document.getElementById('$hiddenInputId');
    const pathPreviewWrapper = document.getElementById('$pathPreviewWrapperId');
    const projectId = '$projectId';
    const fieldType = 'file';
    const fieldId = '{$field['id']}';
    const basePreviewUrl = '$basePreviewUrl';

    function getPathSelector() {
        return window.pathSelectorWidgets && window.pathSelectorWidgets['$pathSelectorId'];
    }

    function pruneToFirstDirectory(path) {
        if (!path) return '';
        const parts = path.split('/');
        return parts.length > 0 ? parts[0] + '/' : '';
    }

    if (modalElement) {
        modalElement.addEventListener('show.bs.modal', function() {
            const pathSelector = getPathSelector();
            if (pathSelector && projectId) {
                const currentPath = hiddenInput ? hiddenInput.value : '';
                const prunedPath = pruneToFirstDirectory(currentPath);

                pathSelector.load(fieldType, projectId);

                setTimeout(function() {
                    if (prunedPath) {
                        pathSelector.render(prunedPath);
                    }
                }, 100);
            }
        });
    }

    if (saveBtn && hiddenInput && pathPreviewWrapper) {
        saveBtn.addEventListener('click', function() {
            const pathSelector = getPathSelector();
            if (pathSelector) {
                pathSelector.sync();
            }
            const newPath = hiddenInput.value;

            updatePathPreview(newPath);

            if (modal) {
                modal.hide();
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function resolveLanguage(path) {
        const extension = path.split('.').pop().toLowerCase();
        const languageMap = {
            'php': ['php', 'PHP'],
            'js': ['javascript', 'JavaScript'],
            'ts': ['typescript', 'TypeScript'],
            'json': ['json', 'JSON'],
            'css': ['css', 'CSS'],
            'html': ['xml', 'HTML'],
            'htm': ['xml', 'HTML'],
            'xml': ['xml', 'XML'],
            'md': ['markdown', 'Markdown'],
            'yaml': ['yaml', 'YAML'],
            'yml': ['yaml', 'YAML'],
            'sh': ['bash', 'Shell'],
            'bash': ['bash', 'Shell'],
            'zsh': ['bash', 'Shell'],
            'py': ['python', 'Python']
        };
        return languageMap[extension] || ['plaintext', 'Plain Text'];
    }

    function updatePathPreview(path) {
        if (!pathPreviewWrapper) return;

        if (!path) {
            pathPreviewWrapper.innerHTML = '<div class="path-preview-widget"><span class="font-monospace text-break"></span></div>';
            return;
        }

        const previewUrl = basePreviewUrl + '&path=' + encodeURIComponent(path);
        const pathLabel = escapeHtml(path);
        const [language, languageLabel] = resolveLanguage(path);

        const buttonId = 'path-preview-button-' + fieldId + '-' + Date.now();
        const buttonHtml = '<button id="' + buttonId + '" type="button" class="btn btn-link p-0 text-decoration-underline path-preview font-monospace" ' +
            'data-url="' + escapeHtml(previewUrl) + '" ' +
            'data-language="' + escapeHtml(language) + '" ' +
            'data-language-label="' + escapeHtml(languageLabel) + '" ' +
            'data-path-label="' + pathLabel + '">' +
            pathLabel +
            '</button>';

        pathPreviewWrapper.innerHTML = '<div class="path-preview-widget">' + buttonHtml + '</div>';

        const button = document.getElementById(buttonId);
        if (button) {
            attachPreviewHandler(button);
        }
    }

    function tweakPhpSuppressions(root) {
        if (!root) return;

        root.querySelectorAll('.hljs-comment').forEach(function(commentEl) {
            if (!commentEl.querySelector('.hljs-doctag')) return;

            var walker = document.createTreeWalker(commentEl, NodeFilter.SHOW_TEXT, null);
            var textNodes = [];
            while (walker.nextNode()) {
                textNodes.push(walker.currentNode);
            }

            textNodes.forEach(function(node) {
                var text = node.nodeValue;
                var target = 'PhpUnused';
                var index = text.indexOf(target);

                if (index === -1) return;

                var before = text.slice(0, index);
                var after = text.slice(index + target.length);

                var span = document.createElement('span');
                span.className = 'hljs-inspection-name';
                span.textContent = target;

                var parent = node.parentNode;
                if (!parent) return;

                if (before) {
                    parent.insertBefore(document.createTextNode(before), node);
                }
                parent.insertBefore(span, node);
                if (after) {
                    parent.insertBefore(document.createTextNode(after), node);
                }

                parent.removeChild(node);
            });
        });
    }

    function ensurePreviewModalExists() {
        const modalId = 'path-preview-modal';
        if (document.getElementById(modalId)) {
            return true;
        }

        const modalHtml = '<div class="modal fade" id="' + modalId + '" tabindex="-1" aria-hidden="true">' +
            '<div class="modal-dialog modal-xl modal-dialog-scrollable">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title mb-0 flex-grow-1 text-truncate">' +
            '<span id="path-preview-title">File Preview</span>' +
            '<span id="path-preview-language" class="badge text-bg-secondary ms-2 d-none"></span>' +
            '</h5>' +
            '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<pre class="mb-0 border-0"><code id="path-preview-content" class="font-monospace"></code></pre>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        return true;
    }

    function attachPreviewHandler(button) {
        const modalId = 'path-preview-modal';
        const titleId = 'path-preview-title';
        const languageId = 'path-preview-language';
        const contentId = 'path-preview-content';

        if (!ensurePreviewModalExists() || !window.bootstrap) return;

        const modalElement = document.getElementById(modalId);
        if (!modalElement) return;

        const modalBody = document.getElementById(contentId);
        const modalTitle = document.getElementById(titleId);
        const languageBadge = document.getElementById(languageId);
        const previewModal = new bootstrap.Modal(modalElement);

        button.addEventListener('click', function() {
            const language = button.getAttribute('data-language') || 'plaintext';
            const languageLabel = button.getAttribute('data-language-label') || language.toUpperCase();
            const previewUrl = button.getAttribute('data-url');

            if (modalTitle) {
                modalTitle.textContent = button.getAttribute('data-path-label') || 'File Preview';
            }

            if (languageBadge) {
                languageBadge.textContent = '';
                languageBadge.classList.add('d-none');
            }

            if (modalBody) {
                modalBody.className = 'font-monospace hljs text-light language-' + language;
                modalBody.textContent = 'Loading...';
            }

            previewModal.show();

            if (!previewUrl) return;

            fetch(previewUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (!data.success) {
                        if (modalBody) modalBody.textContent = 'Preview error: ' + (data.message || 'Unable to load preview.');
                        return;
                    }
                    if (languageBadge && languageLabel) {
                        languageBadge.textContent = languageLabel;
                        languageBadge.classList.remove('d-none');
                    }
                    if (modalBody) {
                        modalBody.textContent = data.preview;
                        if (window.hljs) {
                            window.hljs.highlightElement(modalBody);
                            tweakPhpSuppressions(modalBody);
                        }
                    }
                })
                .catch(function(error) {
                    console.error('Path preview failed:', error);
                    if (modalBody) modalBody.textContent = 'Preview error: Unable to load preview.';
                });
        });
    }
})();
JS;
                        $view->registerJs($script);

                        return $html;
                })(),
                default => Html::textarea(
                        $name,
                        (string)($field['default'] ?? ''),
                        [
                                'id' => "field-$placeholder",
                                'class' => 'form-control custom-textarea',
                                'rows' => 5,
                                'cols' => 50,
                                'style' => 'resize: vertical; height: 150px; overflow-y: auto;',
                        ]
                ),
            };
        },
        $templateHtml
);
?>

<div class="generated-prompt-form">
    <?= $templateRendered ?>
</div>
