<?php

namespace app\widgets;

use app\assets\QuillAsset;
use app\services\CopyFormatConverter;
use common\enums\CopyType;
use common\enums\LogCategory;
use Throwable;
use Yii;
use yii\base\InvalidArgumentException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;

class QuillViewerWidget extends Widget
{
    public ?string $content = '';
    public bool $enableCopy = false;
    public array $copyButtonOptions = [];
    public string $copyButtonLabel = '<i class="bi bi-clipboard"> </i>';
    public string $copyFormat = 'text';
    public array $options = [];
    public string $theme = 'snow';

    public bool $enableExport = false;
    public ?int $exportProjectId = null;
    public ?string $exportEntityName = null;
    public ?string $exportRootDirectory = null;

    public function init(): void
    {
        parent::init();
        QuillAsset::register($this->getView());

        if (isset($this->copyButtonOptions['copyFormat'])) {
            $this->copyFormat = $this->copyButtonOptions['copyFormat'];
            unset($this->copyButtonOptions['copyFormat']);
        }

        $defaultBtn = [
            'class' => 'btn btn-sm btn-outline-secondary',
            'title' => 'Copy to clipboard',
            'aria-label' => 'Copy content to clipboard',
        ];
        $this->copyButtonOptions = array_merge($defaultBtn, $this->copyButtonOptions);
        Html::addCssClass($this->options, 'quill-viewer-container');
    }

    /**
     * @throws Throwable
     */
    public function run(): string
    {
        if ($this->content === null || trim($this->content) === '') {
            return Html::tag('div', '<p>No content available.</p>', $this->options);
        }

        $id = $this->getId();
        $viewerId = "$id-viewer";
        $hiddenId = "$id-hidden";

        $copyFormat = '';
        $copyContent = '';
        if ($this->enableCopy) {
            $copyFormat = strtolower($this->copyFormat);
            $copyType = CopyType::tryFrom($copyFormat) ?? CopyType::TEXT;
            $converter = new CopyFormatConverter();
            $copyContent = $converter->convertFromQuillDelta($this->content, $copyType);
        }

        /* viewer shell with fallback content */
        $style = rtrim(($this->options['style'] ?? ''), ';');
        // Only add overflow if not already specified in style
        if (!str_contains($style, 'overflow:')) {
            $style .= ';overflow:hidden;';
        }

        // Extract plain text as fallback for when JavaScript is not available
        $fallbackContent = $this->extractPlainText($this->content);

        $viewerDiv = Html::tag(
            'div',
            Html::tag('noscript', Html::encode($fallbackContent))
            . Html::tag('div', '', ['class' => 'ql-editor', 'style' => 'display:none;']),
            array_merge(
                $this->options,
                [
                    'id' => $viewerId,
                    'style' => $style,
                    'data-delta-content' => $this->content,
                ]
            )
        );

        /* hidden raw JSON for copy-as-text */
        $hiddenTextarea = $this->enableCopy
            ? Html::tag('textarea', Html::encode($copyContent), ['id' => $hiddenId, 'style' => 'display:none;'])
            : '';

        /* action buttons (export + copy) */
        $buttonsHtml = $this->renderActionButtons($viewerId, $hiddenId, $copyFormat, $copyContent);

        /* container */
        $html = Html::tag('div', $hiddenTextarea . $viewerDiv . $buttonsHtml, ['class' => 'position-relative']);

        /* initialise Quill with decoded delta */
        $this->registerInitScript($viewerId, $this->content, $this->theme);

        return $html;
    }

    /**
     * Render export and copy buttons in a floating container.
     *
     * @throws Throwable
     */
    private function renderActionButtons(
        string $viewerId,
        string $hiddenId,
        string $copyFormat,
        string $copyContent
    ): string {
        if (!$this->enableExport && !$this->enableCopy) {
            return '';
        }

        $buttons = [];

        if ($this->enableExport) {
            $buttons[] = $this->renderExportButton($viewerId);
        }

        if ($this->enableCopy) {
            $buttons[] = CopyToClipboardWidget::widget([
                'targetSelector' => "#$hiddenId",
                'copyFormat' => $copyFormat,
                'copyContent' => $copyContent,
                'buttonOptions' => $this->copyButtonOptions,
                'label' => $this->copyButtonLabel,
            ]);
        }

        return Html::tag('div', implode('', $buttons), ['class' => 'quill-viewer-actions']);
    }

    /**
     * Render export button that opens the ExportModal.
     */
    private function renderExportButton(string $viewerId): string
    {
        $buttonId = $viewerId . '-export-btn';
        $hasRoot = $this->exportProjectId && $this->exportRootDirectory;

        $btn = Html::button('<i class="bi bi-box-arrow-up"></i>', [
            'id' => $buttonId,
            'class' => 'btn btn-sm btn-outline-secondary',
            'title' => 'Export content',
            'aria-label' => 'Export content',
        ]);

        $config = Json::htmlEncode([
            'projectId' => $this->exportProjectId,
            'entityName' => $this->exportEntityName ?? '',
            'hasRoot' => $hasRoot,
            'rootDirectory' => $this->exportRootDirectory,
        ]);

        $this->getView()->registerJs(
            <<<JS
                (function() {
                    var btn = document.getElementById('$buttonId');
                    if (!btn) return;
                    btn.addEventListener('click', function() {
                        var container = document.getElementById('$viewerId');
                        var config = $config;
                        config.getContent = function() {
                            return container ? container.dataset.deltaContent : null;
                        };
                        if (window.ExportModal) {
                            window.ExportModal.open(config);
                        }
                    });
                })();
                JS
        );

        return $btn;
    }

    /**
     * Extract plain text from Quill Delta JSON for fallback display
     */
    private function extractPlainText(string $jsonString): string
    {
        try {
            $delta = Json::decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            if (!isset($delta['ops']) || !is_array($delta['ops'])) {
                return '';
            }

            $text = '';
            foreach ($delta['ops'] as $op) {
                if (isset($op['insert']) && is_string($op['insert'])) {
                    $text .= $op['insert'];
                }
            }
            return trim($text);
        } catch (InvalidArgumentException) {
            return '';
        }
    }

    /**
     * Initialise Quill viewer with the stored delta.
     */
    protected function registerInitScript(string $containerId, string $jsonString, string $theme): void
    {
        try {
            $delta = Json::decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (InvalidArgumentException $e) {
            Yii::error("Invalid QuillViewerWidget JSON: " . $e->getMessage(), LogCategory::APPLICATION->value);
            return;
        }

        $encoded = Json::htmlEncode($delta);
        $this->getView()->registerJs(
            <<<JS
                (function () {
                    const container = document.getElementById('$containerId');
                    if (!container) return;
                    
                    // Hide noscript fallback and show editor
                    const noscript = container.querySelector('noscript');
                    const editor = container.querySelector('.ql-editor');
                    if (noscript) noscript.style.display = 'none';
                    if (editor) editor.style.display = 'block';
                    
                    const quill = new Quill('#$containerId', {
                        readOnly: true,
                        theme: '$theme',
                        modules: { toolbar: false }
                    });
                    quill.setContents({$encoded})
                })();
                JS
        );
    }
}
