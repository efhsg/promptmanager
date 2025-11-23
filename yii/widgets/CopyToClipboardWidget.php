<?php
/** @noinspection BadExpressionStatementJS */

/** @noinspection JSUnnecessarySemicolon */

/** @noinspection JSDeprecatedSymbols */

namespace app\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;

class CopyToClipboardWidget extends Widget
{
    public string $targetSelector;
    public string $copyFormat = 'text';
    public ?string $copyContent = null;
    public array $buttonOptions = [];
    public string $label = '<i class="bi bi-clipboard"></i>';
    public string $defaultClass = 'btn btn-sm btn-outline-secondary';
    public string $successClass = 'btn btn-sm btn-primary';
    public int $successDuration = 250;

    public function run()
    {
        $buttonId = 'copy-btn-' . $this->getId();
        $this->buttonOptions['id'] = $buttonId;
        $this->buttonOptions['type'] = 'button';
        $this->buttonOptions['title'] = $this->buttonOptions['title'] ?? 'Copy to clipboard';
        if ($this->copyContent !== null) {
            $this->buttonOptions['data-copy-content'] = $this->copyContent;
        }
        $this->buttonOptions['data-target-selector'] = $this->targetSelector;
        Html::addCssClass($this->buttonOptions, $this->defaultClass);

        $button = Html::button($this->label, $this->buttonOptions);

        $js = <<<JS
(function () {
    var targetSelector = '$this->targetSelector';
    var defaultClass = '$this->defaultClass';
    var successClass = '$this->successClass';
    var successDuration = $this->successDuration;
    var button = document.getElementById('$buttonId');
    if (!button) {
        return;
    }
    function resolveText() {
        if (button.hasAttribute('data-copy-content')) {
            var dataContent = button.dataset.copyContent;
            return dataContent || '';
        }
        var target = targetSelector ? document.querySelector(targetSelector) : null;
        if (!target) {
            return '';
        }

        if (target.tagName === 'TEXTAREA' || target.tagName === 'INPUT') {
            return target.value || target.textContent || '';
        }

        return target.textContent || '';
    }

    function toggleSuccess(button, isSuccess) {
        var defaultClasses = defaultClass.split(' ').filter(Boolean);
        var successClasses = successClass.split(' ').filter(Boolean);

        if (isSuccess) {
            if (defaultClasses.length) {
                button.classList.remove.apply(button.classList, defaultClasses);
            }
            if (successClasses.length) {
                button.classList.add.apply(button.classList, successClasses);
            }
            setTimeout(function () {
                if (successClasses.length) {
                    button.classList.remove.apply(button.classList, successClasses);
                }
                if (defaultClasses.length) {
                    button.classList.add.apply(button.classList, defaultClasses);
                }
            }, successDuration);
        }
    }

    button.addEventListener('click', function () {
        var text = resolveText();
        if (typeof text !== 'string') {
            text = text === undefined || text === null ? '' : String(text);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                toggleSuccess(button, true);
            }).catch(function (err) {
                console.error('Failed to copy text: ', err);
            });
        } else {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();

            try {
                if (document.execCommand('copy')) {
                    toggleSuccess(button, true);
                }
            } catch (err) {
                console.error('Fallback: Unable to copy', err);
            }

            document.body.removeChild(textarea);
        }
    });
})();
JS;
        Yii::$app->view->registerJs($js);
        return $button;
    }
}
