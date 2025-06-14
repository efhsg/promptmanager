<?php
declare(strict_types=1);

namespace app\widgets;

use app\assets\QuillAsset;
use Yii;
use yii\base\InvalidArgumentException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;

class QuillViewerWidget extends Widget
{
    public string $content = '';
    public bool $enableCopy = true;
    public array $copyButtonOptions = [];
    public string $copyButtonLabel = '<i class="bi bi-clipboard"> </i>';
    public string $copyFormat = 'text';
    public array $options = [];
    public string $theme = 'snow';

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

    public function run(): string
    {
        $id = $this->getId();
        $viewerId = "$id-viewer";
        $hiddenId = "$id-hidden";

        if (trim($this->content) === '') {
            return Html::tag('div', '<p>No content available.</p>', $this->options);
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
            Html::tag('noscript', Html::encode($fallbackContent)) .
            Html::tag('div', '', ['class' => 'ql-editor', 'style' => 'display:none;']),
            array_merge(
                $this->options,
                ['id' => $viewerId, 'style' => $style]
            )
        );

        /* hidden raw JSON for copy-as-text */
        $hiddenTextarea = $this->enableCopy && $this->copyFormat !== 'md'
            ? Html::tag('textarea', $this->content, ['id' => $hiddenId, 'style' => 'display:none;'])
            : '';

        /* copy button */
        $copyBtnHtml = $this->enableCopy
            ? CopyToClipboardWidget::widget([
                'targetSelector' => $this->copyFormat === 'md' ? "#$viewerId .ql-editor" : "#$hiddenId",
                'copyFormat' => $this->copyFormat,
                'buttonOptions' => $this->copyButtonOptions,
                'label' => $this->copyButtonLabel,
            ])
            : '';

        /* container */
        $html = Html::tag('div', $hiddenTextarea . $viewerDiv . $copyBtnHtml, ['class' => 'position-relative']);

        /* initialise Quill with decoded delta */
        $this->registerInitScript($viewerId, $this->content, $this->theme);

        return $html;
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
            Yii::error("Invalid QuillViewerWidget JSON: " . $e->getMessage());
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
