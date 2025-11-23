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
    public array $buttonOptions = [];
    public string $label = '<i class="bi bi-clipboard"></i>';
    public string $defaultClass = 'btn btn-sm btn-outline-secondary';
    public string $successClass = 'btn btn-sm btn-primary';
    public int $successDuration = 250;

    public function run()
    {
        $buttonId = 'copy-btn-' . $this->getId();
        $copyFormat = strtolower($this->copyFormat);
        $this->buttonOptions['id'] = $buttonId;
        $this->buttonOptions['type'] = 'button';
        $this->buttonOptions['title'] = $this->buttonOptions['title'] ?? 'Copy to clipboard';
        Html::addCssClass($this->buttonOptions, $this->defaultClass);

        $button = Html::button($this->label, $this->buttonOptions);

        $js = <<<JS
(function () {
    var copyFormat = '$copyFormat';
    var targetSelector = '$this->targetSelector';
    var defaultClass = '$this->defaultClass';
    var successClass = '$this->successClass';
    var successDuration = $this->successDuration;

    function escapeMarkdown(text) {
        return (text || '').replace(/([\\\\`*_\\[\\]])/g, '\\\\$1');
    }

    function applyInlineFormats(text, attrs, skipEscape) {
        var value = skipEscape ? (text || '') : escapeMarkdown(text || '');
        if (!attrs) {
            return value;
        }

        if (attrs.code) {
            value = '`' + (text || '').replace(/`/g, '\\`') + '`';
        } else {
            if (attrs.strike) {
                value = '~~' + value + '~~';
            }
            if (attrs.bold) {
                value = '**' + value + '**';
            }
            if (attrs.italic) {
                value = '*' + value + '*';
            }
            if (attrs.underline) {
                value = '_' + value + '_';
            }
        }

        if (attrs.link) {
            value = '[' + value + '](' + attrs.link + ')';
        }

        return value;
    }

    function pickInlineAttributes(attrs) {
        if (!attrs) {
            return null;
        }

        var keys = ['bold', 'italic', 'underline', 'strike', 'code', 'link'];
        var inlineAttrs = {};
        var hasInline = false;

        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (attrs[key]) {
                inlineAttrs[key] = attrs[key];
                hasInline = true;
            }
        }

        return hasInline ? inlineAttrs : null;
    }

    function pickBlockAttributes(attrs) {
        if (!attrs) {
            return {};
        }

        var keys = ['header', 'blockquote', 'list', 'code-block', 'align', 'indent'];
        var blockAttrs = {};

        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (attrs[key] !== undefined && attrs[key] !== null) {
                blockAttrs[key] = attrs[key];
            }
        }

        return blockAttrs;
    }

    function renderEmbed(embed) {
        if (!embed || typeof embed !== 'object') {
            return '';
        }
        if (embed.image) {
            return '![](' + embed.image + ')';
        }
        if (embed.video) {
            return embed.video;
        }
        return '';
    }

    function renderSegments(segments, blockAttrs) {
        var parts = [];
        var skipEscape = !!(blockAttrs && blockAttrs['code-block']);

        for (var i = 0; i < segments.length; i++) {
            var segment = segments[i];
            if (segment.embed) {
                parts.push(renderEmbed(segment.embed));
                continue;
            }

            parts.push(applyInlineFormats(segment.text || '', segment.attrs, skipEscape));
        }

        return parts.join('');
    }

    function normalizeDelta(delta) {
        if (!delta) {
            return null;
        }
        if (Array.isArray(delta)) {
            return {ops: delta};
        }
        if (delta.ops && Array.isArray(delta.ops)) {
            return delta;
        }
        return null;
    }

    function deltaToMarkdown(delta) {
        var normalized = normalizeDelta(delta);
        if (!normalized) {
            return '';
        }

        var ops = normalized.ops;
        var blocks = [];
        var segments = [];

        function pushLine(attrs) {
            blocks.push({segments: segments, attrs: attrs || {}});
            segments = [];
        }

        for (var i = 0; i < ops.length; i++) {
            var op = ops[i];
            if (typeof op.insert === 'string') {
                var parts = op.insert.split('\\n');
                for (var j = 0; j < parts.length; j++) {
                    var part = parts[j];
                    if (part.length) {
                        segments.push({
                            text: part,
                            attrs: pickInlineAttributes(op.attributes)
                        });
                    }

                    if (j < parts.length - 1) {
                        pushLine(pickBlockAttributes(op.attributes));
                    }
                }
            } else if (op.insert && typeof op.insert === 'object') {
                segments.push({
                    embed: op.insert
                });
            }
        }

        if (segments.length) {
            pushLine({});
        }

        var lines = [];
        var listActive = false;
        var listCounters = [];
        var codeActive = false;
        var codeLang = '';
        var codeLines = [];

        function flushCode() {
            if (!codeActive) {
                return;
            }

            var fence = '```' + (codeLang && codeLang !== 'plain' ? codeLang : '');
            lines.push(fence);
            for (var idx = 0; idx < codeLines.length; idx++) {
                lines.push(codeLines[idx]);
            }
            lines.push('```');
            lines.push('');
            codeActive = false;
            codeLang = '';
            codeLines = [];
        }

        function endList() {
            if (listActive) {
                lines.push('');
            }
            listActive = false;
            listCounters = [];
        }

        for (var b = 0; b < blocks.length; b++) {
            var block = blocks[b];
            var attrs = block.attrs || {};
            var lineText = renderSegments(block.segments, attrs);

            if (attrs['code-block']) {
                endList();
                var language = typeof attrs['code-block'] === 'string' ? attrs['code-block'] : '';
                if (!codeActive || language !== codeLang) {
                    flushCode();
                    codeActive = true;
                    codeLang = language;
                }
                codeLines.push(lineText);
                continue;
            }

            flushCode();

            if (attrs.list) {
                listActive = true;

                var indent = parseInt(attrs.indent || 0, 10);
                indent = isNaN(indent) || indent < 0 ? 0 : indent;
                var indentSpaces = '';
                for (var n = 0; n < indent; n++) {
                    indentSpaces += '  ';
                }

                listCounters = listCounters.slice(0, indent + 1);
                var prefix;

                if (attrs.list === 'ordered') {
                    var count = listCounters[indent] || 1;
                    prefix = count + '. ';
                    listCounters[indent] = count + 1;
                } else if (attrs.list === 'checked' || attrs.list === 'unchecked') {
                    prefix = '- [' + (attrs.list === 'checked' ? 'x' : ' ') + '] ';
                    listCounters[indent] = listCounters[indent] || 1;
                } else {
                    prefix = '- ';
                    listCounters[indent] = listCounters[indent] || 1;
                }

                lines.push(indentSpaces + prefix + lineText.trim());
                continue;
            }

            if (listActive) {
                endList();
            }

            if (attrs.header) {
                var headerLevel = parseInt(attrs.header, 10);
                if (isNaN(headerLevel) || headerLevel < 1) {
                    headerLevel = 1;
                }
                if (headerLevel > 6) {
                    headerLevel = 6;
                }
                var hashes = '';
                for (var h = 0; h < headerLevel; h++) {
                    hashes += '#';
                }
                lines.push(hashes + ' ' + lineText.trim());
                lines.push('');
                continue;
            }

            if (attrs.blockquote) {
                var quote = lineText.trim().split('\\n');
                for (var q = 0; q < quote.length; q++) {
                    quote[q] = '> ' + quote[q].trim();
                }
                lines.push(quote.join('\\n'));
                lines.push('');
                continue;
            }

            var paragraph = lineText.trim();
            lines.push(paragraph);
            lines.push('');
        }

        flushCode();
        if (listActive) {
            endList();
        }

        while (lines.length && lines[lines.length - 1] === '') {
            lines.pop();
        }

        for (var k = 1; k < lines.length; k++) {
            if (lines[k] === '' && lines[k - 1] === '') {
                lines.splice(k, 1);
                k--;
            }
        }

        return lines.join('\\n');
    }

    function parseDeltaString(value) {
        if (!value) {
            return null;
        }

        try {
            return JSON.parse(value);
        } catch (e) {
            return null;
        }
    }

    function findDeltaSource(element) {
        if (!element) {
            return null;
        }

        var deltaFromElement = element.dataset && element.dataset.deltaContent
            ? parseDeltaString(element.dataset.deltaContent)
            : null;
        if (deltaFromElement) {
            return deltaFromElement;
        }

        var container = element.closest ? element.closest('[data-delta-content]') : null;
        if (container && container.dataset && container.dataset.deltaContent) {
            var deltaFromContainer = parseDeltaString(container.dataset.deltaContent);
            if (deltaFromContainer) {
                return deltaFromContainer;
            }
        }

        if (window.Quill) {
            try {
                var quillInstance = Quill.find(element);
                if (quillInstance && typeof quillInstance.getContents === 'function') {
                    return quillInstance.getContents();
                }
            } catch (e) {
            }

            if (container && container.__quill && typeof container.__quill.getContents === 'function') {
                return container.__quill.getContents();
            }
        }

        return null;
    }

    function extractMarkdown(element) {
        if (!element) {
            return '';
        }

        if (element.dataset && element.dataset.mdContent && element.dataset.mdContent !== '') {
            return element.dataset.mdContent;
        }

        var delta = findDeltaSource(element);
        if (delta) {
            var markdown = deltaToMarkdown(delta);
            if (markdown) {
                return markdown;
            }
        }

        return element.innerText || '';
    }

    function extractQuillDelta(element) {
        var delta = findDeltaSource(element);
        if (delta) {
            try {
                return JSON.stringify(delta);
            } catch (e) {
            }
        }

        if (element.querySelector && element.querySelector('.ql-editor')) {
            return element.querySelector('.ql-editor').innerHTML;
        }

        return element.innerText || '';
    }

    function getCopyText(element) {
        switch (copyFormat) {
            case 'html':
                return element.innerHTML;
            case 'quilldelta':
                return extractQuillDelta(element);
            case 'md':
                return extractMarkdown(element);
            case 'text':
            default:
                return element.innerText;
        }
    }

    function toggleSuccess(button, isSuccess) {
        var defaultClasses = defaultClass.split(' ');
        var successClasses = successClass.split(' ');

        if (isSuccess) {
            button.classList.remove.apply(button.classList, defaultClasses);
            button.classList.add.apply(button.classList, successClasses);
            setTimeout(function () {
                button.classList.remove.apply(button.classList, successClasses);
                button.classList.add.apply(button.classList, defaultClasses);
            }, successDuration);
        }
    }

    var button = document.getElementById('$buttonId');
    if (!button) {
        return;
    }

    button.addEventListener('click', function () {
        var element = document.querySelector(targetSelector);
        if (!element) {
            return;
        }

        var text = getCopyText(element);
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
