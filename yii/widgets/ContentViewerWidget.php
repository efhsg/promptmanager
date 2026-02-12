<?php

namespace app\widgets;

use app\services\CopyFormatConverter;
use common\enums\CopyType;
use yii\base\Widget;
use yii\helpers\Html;
use yii\web\View;

class ContentViewerWidget extends Widget
{
    public string $content = '';
    public array $viewerOptions = [];
    public bool $enableCopy = true;
    public bool $enableExpand = false;
    public array $copyButtonOptions = [];
    public string $copyButtonLabel = '<i class="bi bi-clipboard"> </i>';
    public string $copyFormat = 'text';
    public array $cssOptions = [];

    private const DEFAULT_CSS = [
        'min-height' => '100px',
        'border' => '1px solid #e0e0e0',
        'border-radius' => '4px',
        'background' => '#fafafa',
        'padding' => '10px',
        'overflow' => 'auto',
    ];

    private ?string $processedContent = null;

    public function init(): void
    {
        parent::init();
        $this->cssOptions = array_merge(self::DEFAULT_CSS, $this->cssOptions);
        $this->processContent();

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

        $this->registerAssets();
        Html::addCssClass($this->viewerOptions, 'content-viewer');
    }

    protected function registerAssets(): void
    {
        $view = $this->getView();
        $view->registerCss($this->getContainerCss());

        if ($this->enableExpand)
            $this->registerExpandAssets($view);
    }

    protected function getContainerCss(): string
    {
        $css = ".content-viewer {\n";
        foreach ($this->cssOptions as $k => $v) {
            $css .= "    $k: $v;\n";
        }
        $css .= "}\n\n";

        $css .= ".content-viewer .ql-container.ql-snow {\n";
        $css .= "    border: none !important;\n";
        $css .= "    box-shadow: none !important;\n";
        $css .= "}\n";

        return $css;
    }

    protected function registerExpandAssets(View $view): void
    {
        $view->registerCss(<<<'CSS'
.content-viewer--expanded {
    max-height: none !important;
    overflow-y: visible !important;
}
.content-viewer__expand {
    opacity: 0.4;
    transition: opacity 0.2s;
    border: none;
    background: transparent;
    color: #6c757d;
    padding: 0.25rem 0.4rem;
    font-size: 0.85rem;
    cursor: pointer;
}
.content-viewer__expand:hover {
    opacity: 1;
    color: #0d6efd;
}
CSS);

        $view->registerJs(<<<'JS'
(function() {
    function checkExpandOverflow(viewer, btn) {
        requestAnimationFrame(function() {
            if (viewer.scrollHeight > viewer.clientHeight + 2)
                btn.classList.remove('d-none');
            else if (!viewer.classList.contains('content-viewer--expanded'))
                btn.classList.add('d-none');
        });
    }
    function resetExpandState(viewer, btn) {
        viewer.classList.remove('content-viewer--expanded');
        btn.innerHTML = '<i class="bi bi-arrows-angle-expand"></i>';
        checkExpandOverflow(viewer, btn);
    }
    document.querySelectorAll('.content-viewer__expand').forEach(function(btn) {
        var viewer = btn.closest('.position-relative').querySelector('.content-viewer');
        if (!viewer) return;
        btn.addEventListener('click', function() {
            var expanded = viewer.classList.toggle('content-viewer--expanded');
            btn.innerHTML = expanded
                ? '<i class="bi bi-arrows-angle-contract"></i>'
                : '<i class="bi bi-arrows-angle-expand"></i>';
        });
        var timer = null;
        var observer = new MutationObserver(function() {
            clearTimeout(timer);
            timer = setTimeout(function() { checkExpandOverflow(viewer, btn); }, 100);
        });
        observer.observe(viewer, { childList: true, subtree: true });
        checkExpandOverflow(viewer, btn);
        viewer._resetExpand = function() { resetExpandState(viewer, btn); };
    });
})();
JS, View::POS_READY);
    }

    protected function processContent(): void
    {
        if (empty($this->content)) {
            $this->processedContent = '<p>No content available</p>';
            return;
        }

        $this->processedContent = $this->content;
    }

    public function run(): string
    {
        $id = $this->getId();
        $viewerId = "$id-viewer";
        $hiddenId = "$id-hidden";
        $copyType = CopyType::tryFrom(strtolower($this->copyFormat)) ?? CopyType::TEXT;
        $converter = new CopyFormatConverter();
        $copyContent = $converter->convertFromHtml($this->processedContent ?? '', $copyType);

        $viewerOptions = array_merge($this->viewerOptions, [
            'id' => $viewerId,
        ]);

        $viewerHtml = Html::tag('div', $this->processedContent, $viewerOptions);

        $hidden = Html::tag('textarea', Html::encode($copyContent), [
            'id' => $hiddenId,
            'style' => 'display:none;',
        ]);

        $copyBtnHtml = '';
        if ($this->enableCopy) {
            $btn = CopyToClipboardWidget::widget([
                'targetSelector' => "#$hiddenId",
                'copyFormat' => $this->copyFormat,
                'copyContent' => $copyContent,
                'buttonOptions' => $this->copyButtonOptions,
                'label' => $this->copyButtonLabel,
            ]);
            $copyBtnHtml = Html::tag('div', $btn, ['class' => 'copy-button-container']);
        }

        $expandBtnHtml = '';
        if ($this->enableExpand) {
            $expandBtnHtml = Html::button('<i class="bi bi-arrows-angle-expand"></i>', [
                'class' => 'content-viewer__expand d-none',
                'title' => 'Expand / collapse',
                'aria-label' => 'Expand or collapse content',
            ]);
        }

        return Html::tag('div', $hidden . $viewerHtml . $expandBtnHtml . $copyBtnHtml, [
            'class' => 'position-relative',
        ]);
    }
}
