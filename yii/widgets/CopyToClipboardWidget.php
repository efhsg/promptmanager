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
    public string $copyFormat = 'text';
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

        // Default extraction is plain text
        $jsExtract = "var text = element.innerText;";

        // Handle different copy formats
        switch (strtolower($this->copyFormat)) {
            case 'html':
                $jsExtract = "var text = element.innerHTML;";
                break;
            case 'quilldelta':
                // For quill delta, get from data attribute or try to extract
                $jsExtract = "
                    var text;
                    if (element.dataset.deltaContent && element.dataset.deltaContent !== '') {
                        // Use the pre-generated delta content from data attribute
                        text = element.dataset.deltaContent;
                    } else if (element.querySelector('.ql-editor')) {
                        // Try to get from Quill instance
                        var quillInstance = Quill.find(element);
                        if (quillInstance) {
                            text = JSON.stringify(quillInstance.getContents());
                        } else {
                            text = element.querySelector('.ql-editor').innerHTML;
                        }
                    } else {
                        text = element.innerText;
                    }
                ";
                break;
            case 'md':
                // For markdown, use pre-generated content from data attribute
                $jsExtract = "
                    var text;
                    if (element.dataset.mdContent && element.dataset.mdContent !== '') {
                        text = element.dataset.mdContent;
                    } else {
                        // Fallback to plaintext if markdown not available
                        text = element.innerText;
                    }
                ";
                break;
            case 'text':
            default:
                // Default is already set to innerText
                break;
        }

        $js = <<<JS
            document.getElementById('$buttonId').addEventListener('click', function(){
                var element = document.querySelector('$this->targetSelector');
                if (!element) return;
                
                $jsExtract
                
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
                    // Fallback for older browsers
                    var textarea = document.createElement('textarea');
                    textarea.value = text;
                    document.body.appendChild(textarea);
                    textarea.select();
                    
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
                    
                    document.body.removeChild(textarea);
                }
            });
        JS;
        Yii::$app->view->registerJs($js);
        return $button;
    }
}