<?php /** @noinspection BadExpressionStatementJS */
/** @noinspection JSUnresolvedReference */

/** @noinspection CssUnusedSymbol */

namespace app\widgets;

use app\assets\QuillAsset;
use Exception;
use Throwable;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\View;

class ContentViewerWidget extends Widget
{
    public string $content;
    public array $viewerOptions = [];
    public bool $enableCopy = true;
    public array $copyButtonOptions = [];
    public string $copyButtonLabel = '<i class="bi bi-clipboard"> </i>';
    public string $copyFormat = 'html';

    public array $cssOptions = [
        'height' => '300px',
        'border' => '1px solid #e0e0e0',
        'border-radius' => '4px',
        'background' => '#fafafa',
        'padding' => '10px',
        'overflow' => 'auto',
    ];

    private ?string $processedContent = null;
    private bool $isQuillDelta = false;
    private ?array $deltaContent = null;

    /**
     */
    public function init(): void
    {
        parent::init();
        $this->processContent();

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

        if ($this->isQuillDelta && $this->deltaContent !== null) {
            QuillAsset::register($view);
            $this->registerQuillJs();
        }
    }

    protected function getContainerCss(): string
    {
        // Base viewer styles
        $css = ".content-viewer {\n";
        foreach ($this->cssOptions as $k => $v) {
            $css .= "    $k: $v;\n";
        }
        $css .= "}\n";

        // Quill overrides + button‐wrapper
        $css .= <<<CSS
.content-viewer .ql-editor { padding: 0; white-space: normal; }
.content-viewer .ql-toolbar { display: none; }

/* pin the copy‐button wrapper in the bottom‐right */
.copy-button-container {
    position: absolute;
    bottom: 10px;
    right: 20px;
    z-index: 100;
}
CSS;

        return $css;
    }

    protected function registerQuillJs(): void
    {
        $id = $this->getId();
        $viewerId = "$id-viewer";
        $deltaJson = Json::htmlEncode($this->deltaContent);

        $js = <<<JS
            document.addEventListener('DOMContentLoaded', function() {
                const quill = new Quill('#$viewerId', {
                    readOnly: true,
                    modules: { syntax: true, toolbar: false },
                    theme: 'snow'
                });
                quill.setContents($deltaJson)
            });
        JS;

        $this->getView()->registerJs($js, View::POS_END);
    }

    protected function processContent(): void
    {
        if (empty($this->content)) {
            $this->processedContent = '<div class="alert alert-info">No content available</div>';
            return;
        }

        try {
            $decoded = Json::decode($this->content);
            if (is_array($decoded) && isset($decoded['ops'])) {
                $this->isQuillDelta = true;
                $this->deltaContent = $decoded;
                return;
            }
        } catch (Exception $e) {
            Yii::warning("Invalid Delta JSON: {$e->getMessage()}", __METHOD__);
        }

        $this->processedContent = $this->content;
    }

    /**
     * @throws Throwable
     */
    public function run(): string
    {
        $id = $this->getId();
        $viewerId = "$id-viewer";
        $hiddenId = "$id-hidden";

        // Render the viewer itself
        $viewerHtml = $this->isQuillDelta
            ? Html::tag('div', '', array_merge($this->viewerOptions, ['id' => $viewerId]))
            : Html::tag('div', $this->processedContent, array_merge($this->viewerOptions, ['id' => $viewerId]));

        // Hidden textarea (source for copy)
        $hidden = Html::tag('textarea', $this->content, [
            'id' => $hiddenId,
            'style' => 'display:none;',
        ]);

        // Copy button, wrapped in the container that our CSS will pin
        $copyBtnHtml = '';
        if ($this->enableCopy) {
            $btn = CopyToClipboardWidget::widget([
                'targetSelector' => "#$viewerId",
                'copyFormat' => $this->copyFormat,
                'buttonOptions' => $this->copyButtonOptions, // your widget’s real API
                'label' => $this->copyButtonLabel,   // your widget’s real API
            ]);

            $copyBtnHtml = Html::tag('div', $btn, ['class' => 'copy-button-container']);
        }

        // Wrap everything in a relative container so our absolute wrapper can position itself
        return Html::tag('div', $hidden . $viewerHtml . $copyBtnHtml, [
            'class' => 'position-relative',
        ]);
    }
}
