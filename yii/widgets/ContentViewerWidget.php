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
    public string $copyFormat = 'text';

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
    private ?string $markdownContent = null;

    /**
     */
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

    /**
     * Post-processes Markdown content to fix common conversion issues
     * @param string $markdown The raw markdown content
     * @return string Processed markdown content
     */
    protected function postProcessMarkdown(string $markdown): string
    {
        // First fix code blocks
        $pattern = '/```(.*?)```(\s*?)```(.*?)```/s';
        while (preg_match($pattern, $markdown)) {
            $markdown = preg_replace($pattern, "```$1$2$3```", $markdown);
        }

        // Simple direct string replacements - force double newlines in key locations
        $rules = [
            // After headers
            '/^(# .+)$/m' => "$1\n\n",
            '/^(## .+)$/m' => "$1\n\n",
            '/^(### .+)$/m' => "$1\n\n",

            // Before and after lists
            '/\n([^-\n]+)\n(- )/' => "\n$1\n\n$2",
            '/\n([^0-9\n]+)\n([0-9]+\. )/' => "\n$1\n\n$2",

            // Before and after blockquotes
            '/([^\n>])\n(> )/' => "$1\n\n$2",
            '/^(>.+)$/m' => "$1\n\n",

            // Before and after code blocks
            '/([^\n])\n```/' => "$1\n\n```",
            '/```\n([^\n])/' => "```\n\n$1",

            // Between paragraphs (text followed by text)
            '/([a-zA-Z0-9.,:;!?"\'])(\n)([a-zA-Z0-9])/' => "$1\n\n$3",
        ];

        foreach ($rules as $pattern => $replacement) {
            $markdown = preg_replace($pattern, $replacement, $markdown);
        }

        // Fix any triple or more newlines (replace with double newlines)
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);

        return $markdown;
    }

    protected function processContent(): void
    {
        if (empty($this->content)) {
            $this->processedContent = '<div class="alert alert-info">No content available</div>';
            $this->markdownContent = 'No content available';
            return;
        }

        try {
            $decoded = Json::decode($this->content);
            if (is_array($decoded) && isset($decoded['ops'])) {
                $this->isQuillDelta = true;
                $this->deltaContent = $decoded;

                // Generate HTML from Delta if needed for Markdown conversion
                try {
                    // Use nadar/quill-delta-parser to get HTML
                    $parser = new \nadar\quill\Lexer($this->content);
                    $html = $parser->render();

                    // Convert HTML to Markdown using league/html-to-markdown
                    $converter = new \League\HTMLToMarkdown\HtmlConverter([
                        'strip_tags' => false,
                        'header_style' => 'atx', // Use # style headers instead of underlines
                        'use_autolinks' => true,
                    ]);
                    $markdownContent = $converter->convert($html);

                    // Apply post-processing to fix common conversion issues
                    $this->markdownContent = $this->postProcessMarkdown($markdownContent);
                } catch (\Exception $e) {
                    Yii::warning("Error converting delta to markdown: {$e->getMessage()}", __METHOD__);
                    $this->markdownContent = 'Error converting content to markdown';
                }

                return;
            }
        } catch (Exception $e) {
            Yii::warning("Invalid Delta JSON: {$e->getMessage()}", __METHOD__);
        }

        $this->processedContent = $this->content;

        // If it's HTML content, convert to Markdown
        try {
            $converter = new \League\HTMLToMarkdown\HtmlConverter([
                'strip_tags' => false,
                'header_style' => 'atx', // # style headers
                'use_autolinks' => true,
            ]);
            $markdownContent = $converter->convert($this->content);

            // Apply post-processing to fix common conversion issues
            $this->markdownContent = $this->postProcessMarkdown($markdownContent);
        } catch (\Exception $e) {
            Yii::warning("Error converting HTML to markdown: {$e->getMessage()}", __METHOD__);
            $this->markdownContent = strip_tags($this->content); // Fallback to plain text
        }
    }

    /**
     * @throws Throwable
     */
    public function run(): string
    {
        $id = $this->getId();
        $viewerId = "$id-viewer";
        $hiddenId = "$id-hidden";

        // Prepare viewer options with data attributes for different formats
        $viewerOptions = array_merge($this->viewerOptions, [
            'id' => $viewerId,
            'data-md-content' => $this->markdownContent ?? '',
            'data-delta-content' => $this->isQuillDelta ? Json::encode($this->deltaContent) : '',
        ]);

        // Render the viewer itself
        $viewerHtml = $this->isQuillDelta
            ? Html::tag('div', '', $viewerOptions)
            : Html::tag('div', $this->processedContent, $viewerOptions);

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
                'buttonOptions' => $this->copyButtonOptions,
                'label' => $this->copyButtonLabel,
            ]);

            $copyBtnHtml = Html::tag('div', $btn, ['class' => 'copy-button-container']);
        }

        // Wrap everything in a relative container so our absolute wrapper can position itself
        return Html::tag('div', $hidden . $viewerHtml . $copyBtnHtml, [
            'class' => 'position-relative',
        ]);
    }
}