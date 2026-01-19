<?php

namespace app\widgets;

use app\services\CopyFormatConverter;
use common\enums\CopyType;
use yii\base\Widget;
use yii\helpers\Html;

class ContentViewerWidget extends Widget
{
    public string $content = '';
    public array $viewerOptions = [];
    public bool $enableCopy = true;
    public array $copyButtonOptions = [];
    public string $copyButtonLabel = '<i class="bi bi-clipboard"> </i>';
    public string $copyFormat = 'text';
    public array $cliCopyButtonOptions = [];

    public array $cssOptions = [
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

        $cliBtnHtml = '';
        if ($this->enableCopy && !empty($this->cliCopyButtonOptions)) {
            $defaultCliBtn = [
                'class' => 'btn btn-sm btn-outline-secondary',
                'title' => 'Copy as Claude CLI command',
                'aria-label' => 'Copy as Claude CLI command',
            ];
            $cliButtonOptions = array_merge($defaultCliBtn, $this->cliCopyButtonOptions);
            $cliBtn = CopyToClipboardWidget::widget([
                'targetSelector' => "#$hiddenId",
                'copyFormat' => $this->copyFormat,
                'copyContent' => $copyContent,
                'buttonOptions' => $cliButtonOptions,
                'label' => '<i class="bi bi-terminal"></i>',
                'cliCommandTemplate' => 'claude --permission-mode plan -p %s',
            ]);
            $cliBtnHtml = Html::tag('div', $cliBtn, ['class' => 'cli-copy-button-container']);
        }

        return Html::tag('div', $hidden . $viewerHtml . $cliBtnHtml . $copyBtnHtml, [
            'class' => 'position-relative',
        ]);
    }
}
