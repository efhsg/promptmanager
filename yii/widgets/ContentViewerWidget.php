<?php /** @noinspection JSUnusedAssignment */
/** @noinspection JSUnresolvedReference */
/** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection CssUnusedSymbol */

namespace app\widgets;

use app\assets\QuillAsset;
use Exception;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\View;

class ContentViewerWidget extends Widget
{
    /**
     * @var string The main text/content to display.
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

        $this->processContent();

        $defaultCopyButtonOptions = [
            'class' => 'btn btn-sm position-absolute',
            'style' => 'bottom: 10px; right: 20px;',
            'title' => 'Copy to clipboard',
            'aria-label' => 'Copy content to clipboard',
        ];
        $this->copyButtonOptions = array_merge($defaultCopyButtonOptions, $this->copyButtonOptions);

        $this->getView()->registerCss($this->viewerCss);

        Html::addCssClass($this->viewerOptions, 'content-viewer');
    }

    protected function processContent(): void
    {
        if (empty($this->content)) {
            $this->processedContent = '<div class="alert alert-info">No content available</div>';
            return;
        }

        // Try to process as Delta format
        try {
            $decoded = Json::decode($this->content);
            $isDeltaFormat = is_array($decoded) && isset($decoded['ops']) && is_array($decoded['ops']);

            if ($isDeltaFormat) {
                $this->processDeltaFormat($decoded);
                return;
            }
        } catch (Exception $e) {
            Yii::warning('Error processing content as Delta: ' . $e->getMessage(), __METHOD__);
        }

        // Check if content is code
        $this->detectCode();

        if ($this->isCode) {
            $this->processCodeFormat();
            return;
        }

        $this->processedContent = $this->content;
    }

    protected function detectCode(): void
    {
        $content = $this->content;

        // Check for common code block markers
        if (preg_match('/^```(\w*)[\r\n]+(.*?)```$/s', $content, $matches)) {
            $this->isCode = true;
            $this->codeLanguage = !empty($matches[1]) ? $matches[1] : 'plain';
            $this->content = $matches[2];
            return;
        }

        // Check for XML/HTML code blocks
        if (preg_match('/<pre(?:\s+.*?)?>\s*(?:<code(?:\s+.*?)?>)?(.*?)(?:<\/code>)?\s*<\/pre>/s', $content, $matches)) {
            $this->isCode = true;

            // Try to detect language from class attribute
            if (preg_match('/class=["\'](.*?)language-(\w+)(.*?)["\']/i', $content, $langMatches)) {
                $this->codeLanguage = $langMatches[2];
            } else {
                $this->codeLanguage = 'html';
            }

            $this->content = html_entity_decode($matches[1]);
            return;
        }

        // Heuristic detection for PHP code
        if (preg_match('/^<\?php/i', trim($content)) &&
            (str_contains($content, ';') || str_contains($content, 'class ') || str_contains($content, 'function '))) {
            $this->isCode = true;
            $this->codeLanguage = 'php';
            return;
        }

        // Heuristic detection for JavaScript
        if ((preg_match('/function\s+\w+\s*\(.*?\)\s*{/s', $content) ||
                preg_match('/const|let|var\s+\w+\s*=/', $content) ||
                preg_match('/\w+\s*\.\s*\w+\s*\(/', $content)) &&
            str_contains($content, ';')) {
            $this->isCode = true;
            $this->codeLanguage = 'javascript';
            return;
        }

        // Heuristic detection for JSON
        if (preg_match('/^\s*[{\[]/', $content) && preg_match('/[}\]]\s*$/', $content) &&
            (str_contains($content, '":') || str_contains($content, '",'))) {
            $this->isCode = true;
            $this->codeLanguage = 'json';
        }
    }

    protected function processCodeFormat(): void
    {
        $language = $this->codeLanguage ?: 'plain';
        $escapedCode = Html::encode($this->content);

        $this->processedContent = <<<HTML
        <pre class="code-$language"><code>$escapedCode</code></pre>
        HTML;
    }

    protected function processDeltaFormat(array $deltaContent): void
    {
        QuillAsset::register($this->getView());

        $id = 'delta-content-' . $this->getId();
        $this->processedContent = '<div id="' . $id . '">Loading formatted content...</div>';

        $js = $this->generateDeltaConversionJs($id, $deltaContent);
        $this->getView()->registerJs($js, View::POS_END);
    }

    protected function generateDeltaConversionJs(string $elementId, array $deltaContent): string
    {
        $jsonContent = Json::encode($deltaContent);
        $hiddenId = $this->getId() . '-hidden';

        return <<<JS
        document.addEventListener('DOMContentLoaded', function() {
            try {
                var deltaContent = $jsonContent;
                var converter = new QuillDeltaToHtmlConverter(deltaContent.ops, {
                    inlineStyles: true
                });
                var convertedHtml = converter.convert();
                document.getElementById('$elementId').innerHTML = convertedHtml;
                
                var hiddenTextarea = document.getElementById('$hiddenId');
                if (hiddenTextarea) {
                    hiddenTextarea.value = convertedHtml;
                }
            } catch (error) {
                console.error('Error converting Delta to HTML:', error);
                document.getElementById('$elementId').innerHTML = 
                    '<div class="alert alert-danger">Error converting content format</div>';
            }
        });
        JS;
    }

    public function run(): string
    {
        $displayContent = $this->processedContent ?? $this->content;
        $originalContent = $this->content;

        $hiddenId = $this->getId() . '-hidden';
        $hidden = Html::tag('textarea', $originalContent, [
            'id' => $hiddenId,
            'style' => 'display:none;',
        ]);

        $viewer = Html::tag('div', $displayContent, $this->viewerOptions);

        $copyButton = '';
        if ($this->enableCopy) {
            $copyButton = CopyToClipboardWidget::widget([
                'targetSelector' => '#' . $hiddenId,
                'buttonOptions'  => $this->copyButtonOptions,
                'label'          => $this->copyButtonLabel,
            ]);
        }

        return Html::tag('div', $hidden . $viewer . $copyButton, [
            'class' => 'position-relative',
        ]);
    }
}