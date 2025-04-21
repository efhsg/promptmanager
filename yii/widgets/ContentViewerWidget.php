<?php
/**
 * ContentViewerWidget
 *
 * Server-side rendering of Quill Delta content using nadar/quill-delta-parser
 */

namespace app\widgets;

use Exception;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use nadar\quill\Lexer;
use HTMLPurifier;
use HTMLPurifier_Config;

class ContentViewerWidget extends Widget
{
    /**
     * @var string The main text/content to display (Quill Delta JSON or raw HTML/code).
     */
    public string $content;

    /**
     * @var array HTML options for the container that displays the content text.
     */
    public array $viewerOptions = [];

    /**
     * @var bool Whether to show the copy-to-clipboard button.
     */
    public bool $enableCopy = true;

    /**
     * @var array Options to pass to CopyToClipboardWidget for the button.
     */
    public array $copyButtonOptions = [];

    /**
     * @var string The label for the copy button. Defaults to an icon.
     */
    public string $copyButtonLabel = '<i class="bi bi-clipboard"></i>';

    /**
     * @var string CSS for the content viewer. Can be overridden to change styling.
     */
    public string $viewerCss = <<<CSS
        .content-viewer {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 10px;
            background: #fafafa;
            min-height: 100px;
            max-height: 300px;
            overflow: auto;
        }
        .content-viewer p {
            padding: 0;
        }
        .content-viewer pre {
            background: #2d2d2d;
            color: #f8f8f2;
            border-radius: 4px;
            padding: 16px;
            margin: 10px 0;
            overflow: auto;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
            font-size: 14px;
            line-height: 1.5;
            tab-size: 4;
        }
        .content-viewer pre code {
            background: transparent;
            padding: 0;
            white-space: pre;
        }
        .content-viewer .code-php {
            border-left: 3px solid #8892BF;
        }
        .content-viewer .code-javascript {
            border-left: 3px solid #F0DB4F;
        }
        .content-viewer .code-html {
            border-left: 3px solid #E44D26;
        }
        .content-viewer .code-css {
            border-left: 3px solid #264DE4;
        }
        .content-viewer .code-json {
            border-left: 3px solid #1C59A5;
        }
        .content-viewer .code-plain {
            border-left: 3px solid #9E9E9E;
        }
    CSS;

    /**
     * @var bool Whether content is code
     */
    private bool $isCode = false;

    /**
     * @var string|null Detected code language
     */
    private ?string $codeLanguage = null;

    /**
     * @var string|null Processed content after format conversion
     */
    private ?string $processedContent = null;

    public function init(): void
    {
        parent::init();

        // Determine how to render content
        $this->processContent();

        // Setup copy button defaults
        $defaultCopyButtonOptions = [
            'class' => 'btn btn-sm position-absolute',
            'style' => 'bottom: 10px; right: 20px;',
            'title' => 'Copy to clipboard',
            'aria-label' => 'Copy content to clipboard',
        ];
        $this->copyButtonOptions = array_merge($defaultCopyButtonOptions, $this->copyButtonOptions);

        // Register CSS for viewer
        $this->getView()->registerCss($this->viewerCss);

        Html::addCssClass($this->viewerOptions, 'content-viewer');
    }

    protected function processContent(): void
    {
        if (empty($this->content)) {
            $this->processedContent = '<div class="alert alert-info">No content available</div>';
            return;
        }

        // Attempt Quill Delta parsing
        try {
            $decoded = Json::decode($this->content);
            if (is_array($decoded) && isset($decoded['ops']) && is_array($decoded['ops'])) {
                $this->processDeltaFormat($decoded);
                return;
            }
        } catch (Exception $e) {
            Yii::warning('Error decoding Delta JSON: ' . $e->getMessage(), __METHOD__);
        }

        // Fallback: detect code blocks
        $this->detectCode();

        if ($this->isCode) {
            $this->processCodeFormat();
            return;
        }

        // Default: treat as raw HTML/text
        $this->processedContent = $this->content;
    }

    protected function detectCode(): void
    {
        $content = $this->content;

        // Similar heuristics as before...
        if (preg_match('/^```(\w*)[\r\n]+(.*?)```$/s', $content, $matches)) {
            $this->isCode = true;
            $this->codeLanguage = !empty($matches[1]) ? $matches[1] : 'plain';
            $this->content = $matches[2];
            return;
        }
        if (preg_match('/<pre(?:[^>]+)?>\s*(?:<code[^>]+)?>?(.*?)(?:<\/code>)?\s*<\/pre>/s', $content, $m)) {
            $this->isCode = true;
            if (preg_match('/class=["\'].*?language-(\w+).*?["\']/i', $content, $lm)) {
                $this->codeLanguage = $lm[1];
            } else {
                $this->codeLanguage = 'html';
            }
            $this->content = html_entity_decode($m[1]);
            return;
        }
        if (preg_match('/^<\?php/i', trim($content))) {
            $this->isCode = true;
            $this->codeLanguage = 'php';
            return;
        }
        if (preg_match('/function\s+\w+\s*\(/s', $content) || preg_match('/\{\s*\}/', $content)) {
            $this->isCode = true;
            $this->codeLanguage = 'javascript';
            return;
        }
        if (preg_match('/^\s*[\[{]/', $content) && preg_match('/[\]}]\s*$/', $content)) {
            $this->isCode = true;
            $this->codeLanguage = 'json';
        }
    }

    protected function processCodeFormat(): void
    {
        $lang        = $this->codeLanguage ?: 'plain';
        $escapedCode = Html::encode($this->content);
        $this->processedContent = Html::tag('pre', Html::tag('code', $escapedCode), ['class' => "code-$lang"]);
    }

    protected function processDeltaFormat(array $deltaContent): void
    {
            try {
            // Convert Delta JSON to HTML
            $jsonDelta = Json::encode($deltaContent);
            $lexer     = new Lexer($jsonDelta);
            $html      = $lexer->render();
        } catch (Exception $e) {
            Yii::warning("Delta parsing error: {$e->getMessage()}", __METHOD__);
            $html = '<div class="alert alert-danger">Error converting content format</div>';
        }

        // Sanitize the HTML output
        $config    = HTMLPurifier_Config::createDefault();
        $purifier  = new HTMLPurifier($config);
        $cleanHtml = $purifier->purify($html);
                
        // Wrap in viewer container markup
        $this->processedContent = Html::tag('div', $cleanHtml, $this->viewerOptions);
    }

    public function run(): string
    {
        $display   = $this->processedContent ?? $this->content;
        $hiddenId = $this->getId() . '-hidden';
        $hidden    = Html::tag('textarea', $this->content, ['id' => $hiddenId, 'style' => 'display:none;']);
        $viewerDiv = Html::tag('div', $display, $this->viewerOptions);

        $copyBtn = '';
        if ($this->enableCopy) {
            $copyBtn = CopyToClipboardWidget::widget([
                'targetSelector' => "#$hiddenId",
                'buttonOptions'  => $this->copyButtonOptions,
                'label'          => $this->copyButtonLabel,
            ]);
        }

        return Html::tag('div', $hidden . $viewerDiv . $copyBtn, ['class' => 'position-relative']);
    }
}