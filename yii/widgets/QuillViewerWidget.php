<?php

namespace app\widgets;

use app\assets\QuillAsset;
use yii\base\Widget;
use yii\helpers\Html;
use yii\web\View;

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
        $viewerId = "{$id}-viewer";
        $hiddenId = "{$id}-hidden";

        if (trim($this->content) === '') {
            return Html::tag('div', '<p>No content available.</p>', $this->options);
        }

        // 1) Render the (empty) Quill container:
        $viewerOptions = array_merge($this->options, [
            'id' => $viewerId,
            'style' => ($this->options['style'] ?? '') . ';overflow:auto;',
        ]);
        $viewerDiv = Html::tag('div', '', $viewerOptions);

        // 2) If copyFormat ≠ 'md', render a hidden textarea with raw JSON:
        $hiddenTextarea = '';
        if ($this->enableCopy && $this->copyFormat !== 'md') {
            $hiddenTextarea = Html::tag(
                'textarea',
                $this->content,
                [
                    'id' => $hiddenId,
                    'style' => 'display:none;',
                ]
            );
        }

        // 3) Render the copy button (target either .ql-editor or the hidden <textarea>):
        $copyBtnHtml = '';
        if ($this->enableCopy) {
            $targetSelector = ($this->copyFormat === 'md')
                ? "#{$viewerId} .ql-editor"
                : "#{$hiddenId}";

            $copyBtnHtml = CopyToClipboardWidget::widget([
                'targetSelector' => $targetSelector,
                'copyFormat' => $this->copyFormat,
                'buttonOptions' => $this->copyButtonOptions,
                'label' => $this->copyButtonLabel,
            ]);
        }

        // 4) Wrap everything in a position-relative container:
        $container = Html::tag('div', $hiddenTextarea . $viewerDiv . $copyBtnHtml, [
            'class' => 'position-relative',
        ]);

        // 5) Init Quill (without toolbar) on the viewer DIV:
        $this->registerInitScript($viewerId, $this->content, $this->theme);

        return $container;
    }

    protected function registerInitScript(string $containerId, string $jsonString, string $theme): void
    {
        $encoded = json_encode($jsonString, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $js = <<<JS
(function() {
    let delta;
    try {
        delta = JSON.parse({$encoded})
    } catch (e) {
        console.error('QuillViewerWidget: Invalid JSON Delta.', e);
        return;
    }

    // Instantiate Quill in read-only mode with NO toolbar
    const quill = new Quill('#{$containerId}', {
        readOnly: true,
        theme: '{$theme}',
        modules: {
            toolbar: false    // ← disables the entire toolbar
        }
    });

    quill.setContents(delta);
})();
JS;
        $this->getView()->registerJs($js, View::POS_READY);
    }
}
