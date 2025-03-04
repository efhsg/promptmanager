<?php /** @noinspection JSUnusedAssignment */
/** @noinspection JSUnresolvedReference */
/** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection CssUnusedSymbol */

namespace app\widgets;

use app\assets\QuillAsset;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;

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
    CSS;

    /**
     * @var bool Whether content is in Delta format
     */
    private bool $isDeltaFormat = false;

    /**
     * @var string|null Processed content after Delta format conversion
     */
    private ?string $processedContent = null;

    public function init(): void
    {
        parent::init();

        // Process content if it's in Delta format
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

    /**
     * Detects and processes Delta format content
     */
    protected function processContent(): void
    {
        if (empty($this->content)) {
            $this->processedContent = '<div class="alert alert-info">No content available</div>';
            return;
        }

        // Check if content is in Delta format
        try {
            if (is_string($this->content)) {
                $decoded = Json::decode($this->content);
                $this->isDeltaFormat = is_array($decoded) && isset($decoded['ops']) && is_array($decoded['ops']);

                if ($this->isDeltaFormat) {
                    // Register QuillAsset for Delta format conversion
                    QuillAsset::register($this->getView());

                    // Create a placeholder for the converted content
                    $id = 'delta-content-' . $this->getId();
                    $this->processedContent = '<div id="' . $id . '">Loading formatted content...</div>';

                    // Register JavaScript to convert Delta to HTML
                    $js = <<<JS
                    document.addEventListener('DOMContentLoaded', function() {
                        try {
                            var deltaContent = {$this->content};
                            var converter = new QuillDeltaToHtmlConverter(deltaContent.ops, {
                                inlineStyles: true
                            });
                            var convertedHtml = converter.convert();
                            document.getElementById('{$id}').innerHTML = convertedHtml;
                            
                            // Update the hidden textarea for copy functionality
                            var hiddenTextarea = document.getElementById('{$this->getId()}-hidden');
                            if (hiddenTextarea) {
                                hiddenTextarea.value = convertedHtml;
                            }
                        } catch (error) {
                            console.error('Error converting Delta to HTML:', error);
                            document.getElementById('{$id}').innerHTML = 
                                '<div class="alert alert-danger">Error converting content format</div>';
                        }
                    });
                    JS;

                    $this->getView()->registerJs($js, \yii\web\View::POS_END);
                    return;
                }
            }
        } catch (\Exception $e) {
            // Not valid JSON, not in Delta format
        }

        // If not Delta format or processing failed, use original content
        $this->processedContent = $this->content;
    }

    public function run(): string
    {
        $displayContent = $this->processedContent ?? $this->content;
        $originalContent = $this->isDeltaFormat ? $displayContent : $this->content;

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