<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection CssUnusedSymbol */

namespace app\widgets;

use yii\base\Widget;
use yii\helpers\Html;

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

    public function init(): void
    {
        parent::init();

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

    public function run()
    {
        $hiddenId = $this->getId() . '-hidden';
        $hidden = Html::tag('textarea', $this->content, [
            'id' => $hiddenId,
            'style' => 'display:none;',
        ]);

        $viewer = Html::tag('div', $this->content, $this->viewerOptions);

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
