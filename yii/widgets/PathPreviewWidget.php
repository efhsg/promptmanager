<?php

namespace app\widgets;

use app\assets\HighlightAsset;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;

/**
 * Renders a filesystem path and, when enabled, provides a modal preview for the
 * file contents with syntax highlighting and client-side fetching.
 */
class PathPreviewWidget extends Widget
{
    public string $path = '';
    public string $previewUrl = '';
    public ?string $language = null;
    public ?string $languageLabel = null;
    public ?string $pathLabel = null;
    public array $buttonOptions = [];
    public bool $enablePreview = true;
    private static bool $modalRendered = false;

    public function init(): void
    {
        parent::init();

        if ($this->enablePreview && $this->previewUrl !== '') {
            HighlightAsset::register($this->getView());
        }
    }

    public function run(): string
    {
        $label = $this->pathLabel ?? $this->path;

        if (!$this->enablePreview || $this->previewUrl === '') {
            return Html::tag(
                'span',
                Html::encode($label),
                [
                    'class' => 'font-monospace text-break',
                    'title' => $label,
                ]
            );
        }

        [$language, $languageLabel] = $this->resolveLanguage($this->language, $this->languageLabel, $this->path);

        $id = $this->getId();
        $buttonId = $id . '-path-preview-button';
        $modalId = 'path-preview-modal';
        $titleId = 'path-preview-title';
        $languageId = 'path-preview-language';
        $contentId = 'path-preview-content';

        $buttonOptions = array_merge(
            [
                'id' => $buttonId,
                'type' => 'button',
                'class' => 'btn btn-link p-0 text-decoration-underline path-preview font-monospace',
                'data-url' => $this->previewUrl,
                'data-language' => $language,
                'data-language-label' => $languageLabel,
                'data-path-label' => $label,
            ],
            $this->buttonOptions
        );

        $button = Html::button(Html::encode($label), $buttonOptions);
        $modal = '';

        if (!self::$modalRendered) {
            $modal = $this->renderModal($modalId, $titleId, $languageId, $contentId);
            self::$modalRendered = true;
        }

        $this->registerClientScript($buttonId, $modalId, $titleId, $languageId, $contentId);

        return Html::tag('div', $button . $modal, ['class' => 'path-preview-widget']);
    }

    private function renderModal(string $modalId, string $titleId, string $languageId, string $contentId): string
    {
        return <<<HTML
            <div class="modal fade" id="$modalId" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title mb-0 flex-grow-1 text-truncate">
                                <span id="$titleId">File Preview</span>
                                <span id="$languageId" class="badge text-bg-secondary ms-2 d-none"></span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <pre class="mb-0 border-0"><code id="$contentId" class="font-monospace"></code></pre>
                        </div>
                    </div>
                </div>
            </div>
            HTML;
    }

    private function registerClientScript(
        string $buttonId,
        string $modalId,
        string $titleId,
        string $languageId,
        string $contentId
    ): void {
        $defaultError = Json::htmlEncode('Preview error: Unable to load preview.');

        $this->getView()->registerJs(
            <<<JS
                (function () {
                    var button = document.getElementById('$buttonId');
                    var modalElement = document.getElementById('$modalId');

                    if (!button || !modalElement || !window.bootstrap) {
                        return;
                    }

                    var modalBody = document.getElementById('$contentId');
                    var modalTitle = document.getElementById('$titleId');
                    var languageBadge = document.getElementById('$languageId');
                    var modal = new bootstrap.Modal(modalElement);
                    var defaultErrorMessage = $defaultError;

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
                        if (!modalBody) {
                            return;
                        }
                        var classes = ['font-monospace', 'hljs', 'text-light', 'language-' + language];
                        modalBody.className = classes.join(' ');
                    }

                    function tweakPhpSuppressions(root) {
                        if (!root) {
                            return;
                        }

                        root.querySelectorAll('.hljs-comment').forEach(function (commentEl) {
                            if (!commentEl.querySelector('.hljs-doctag')) {
                                return;
                            }

                            var walker = document.createTreeWalker(
                                commentEl,
                                NodeFilter.SHOW_TEXT,
                                null
                            );

                            var textNodes = [];
                            while (walker.nextNode()) {
                                textNodes.push(walker.currentNode);
                            }

                            textNodes.forEach(function (node) {
                                var text = node.nodeValue;
                                var target = 'PhpUnused';
                                var index = text.indexOf(target);

                                if (index === -1) {
                                    return;
                                }

                                var before = text.slice(0, index);
                                var after = text.slice(index + target.length);

                                var span = document.createElement('span');
                                span.className = 'hljs-inspection-name';
                                span.textContent = target;

                                var parent = node.parentNode;
                                if (!parent) {
                                    return;
                                }

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

                    function setPreviewContent(text) {
                        if (!modalBody) {
                            return;
                        }
                        modalBody.textContent = text;

                        if (window.hljs) {
                            window.hljs.highlightElement(modalBody);
                            tweakPhpSuppressions(modalBody);
                        }
                    }

                    function showError(message) {
                        resetLanguageBadge();
                        setPreviewContent(message ? 'Preview error: ' + message : defaultErrorMessage);
                    }

                    button.addEventListener('click', function () {
                        var language = button.getAttribute('data-language') || 'plaintext';
                        var languageLabel = button.getAttribute('data-language-label') || language.toUpperCase();
                        var previewUrl = button.getAttribute('data-url');

                        if (modalTitle) {
                            modalTitle.textContent = button.getAttribute('data-path-label') || 'File Preview';
                        }

                        resetLanguageBadge();
                        applyLanguageClass(language);
                        setPreviewContent('Loading...');
                        modal.show();

                        if (!previewUrl) {
                            showError('');
                            return;
                        }

                        fetch(previewUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
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
                })();
                JS
        );
    }

    private function resolveLanguage(?string $language, ?string $label, string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        [$defaultLanguage, $defaultLabel] = match ($extension) {
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

        $resolvedLanguage = $language ?? $defaultLanguage;
        $resolvedLabel = $label ?? ($language !== null ? strtoupper($language) : $defaultLabel);

        return [$resolvedLanguage, $resolvedLabel];
    }
}
