<?php /** @noinspection BadExpressionStatementJS */
/** @noinspection JSUnnecessarySemicolon */

/** @noinspection JSDeprecatedSymbols */

namespace app\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;

class CopyToClipboardWidget extends Widget
{
    public string $targetSelector;
    public string $copyFormat = 'delta';
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
        Html::addCssClass($this->buttonOptions, $this->defaultClass);

        $button = Html::button($this->label, $this->buttonOptions);

        $jsExtract = "var text = element.value || element.innerText;";
        if ($this->copyFormat === 'html') {
            $jsExtract = "var text = element.innerHTML;";
        }

        $js = <<<JS
document.getElementById('$buttonId').addEventListener('click', function(){
    var element = document.querySelector('$this->targetSelector');
    if (!element) return;
    {$jsExtract}
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function(){
            var btn = document.getElementById('$buttonId');
            btn.classList.remove(...'$this->defaultClass'.split(' '));
            btn.classList.add(...'$this->successClass'.split(' '));
                setTimeout(function(){
                btn.classList.remove(...'$this->successClass'.split(' '));
                btn.classList.add(...'$this->defaultClass'.split(' '));
            }, $this->successDuration);
            }).catch(function(err){
                console.error('Failed to copy text: ', err);
            });
        } else {
        if (element.select) element.select();
            try {
                if (document.execCommand('copy')) {
                var btn = document.getElementById('$buttonId');
                btn.classList.remove(...'$this->defaultClass'.split(' '));
                btn.classList.add(...'$this->successClass'.split(' '));
                    setTimeout(function(){
                    btn.classList.remove(...'$this->successClass'.split(' '));
                    btn.classList.add(...'$this->defaultClass'.split(' '));
                }, $this->successDuration);
                }
            } catch (err) {
                console.error('Fallback: Unable to copy', err);
            }
        }
});
JS;
        Yii::$app->view->registerJs($js);
        return $button;
    }

}
