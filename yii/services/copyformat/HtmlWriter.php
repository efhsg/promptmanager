<?php

namespace app\services\copyformat;

class HtmlWriter extends AbstractFormatWriter
{
    public function writeFromBlocks(array $blocks): string
    {
        $html = [];
        $listStack = [];
        $codeActive = false;
        $codeLang = '';
        $codeLines = [];

        $closeListsTo = static function (int $level) use (&$listStack, &$html): void {
            while (count($listStack) > $level) {
                $list = array_pop($listStack);
                $html[] = '</' . $list['tag'] . '>';
            }
        };

        $flushCode = static function () use (&$codeActive, &$codeLang, &$codeLines, &$html): void {
            if (!$codeActive) {
                return;
            }
            $class = $codeLang && $codeLang !== 'plain' ? ' class="language-' . htmlspecialchars($codeLang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
            $html[] = '<pre><code' . $class . '>' . implode("\n", array_map(static fn($line) => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $codeLines)) . '</code></pre>';
            $codeActive = false;
            $codeLang = '';
            $codeLines = [];
        };

        foreach ($blocks as $block) {
            $attrs = $block['attrs'] ?? [];
            $lineText = !empty($attrs['code-block'])
                ? $this->renderSegmentsPlain($block['segments'])
                : $this->renderSegments($block['segments']);

            if (!empty($attrs['code-block'])) {
                $closeListsTo(0);
                $language = is_string($attrs['code-block']) ? $attrs['code-block'] : '';
                if (!$codeActive || $language !== $codeLang) {
                    $flushCode();
                    $codeActive = true;
                    $codeLang = $language;
                }
                $codeLines[] = $lineText;
                continue;
            }

            $flushCode();

            if (!empty($attrs['list'])) {
                $type = $attrs['list'] === 'ordered' ? 'ol' : 'ul';
                $indent = (int) ($attrs['indent'] ?? 0);
                $indent = $indent < 0 ? 0 : $indent;

                // Close nested lists deeper than current indent, but keep current level open
                $closeListsTo($indent + 1);
                if (!isset($listStack[$indent]) || $listStack[$indent]['tag'] !== $type) {
                    $listStack[$indent] = ['tag' => $type];
                    $html[] = '<' . $type . '>';
                }

                $checkedAttr = '';
                if ($attrs['list'] === 'checked' || $attrs['list'] === 'unchecked') {
                    $checkedAttr = ' data-checked="' . ($attrs['list'] === 'checked' ? 'true' : 'false') . '"';
                }

                $html[] = '<li' . $checkedAttr . '>' . $lineText . '</li>';
                continue;
            }

            // Skip empty paragraphs inside lists to keep list items grouped
            $isEmptyParagraph = trim($lineText) === '' && empty($attrs);
            if ($isEmptyParagraph && !empty($listStack)) {
                continue;
            }

            $closeListsTo(0);

            if (!empty($attrs['header'])) {
                $level = (int) $attrs['header'];
                $level = $level < 1 ? 1 : ($level > 6 ? 6 : $level);
                $html[] = '<h' . $level . '>' . $lineText . '</h' . $level . '>';
                continue;
            }

            if (!empty($attrs['blockquote'])) {
                $html[] = '<blockquote><p>' . $lineText . '</p></blockquote>';
                continue;
            }

            // Skip empty paragraphs outside lists too (they don't add visual value)
            if ($isEmptyParagraph) {
                continue;
            }

            $html[] = '<p>' . trim($lineText) . '</p>';
        }

        $flushCode();
        $closeListsTo(0);

        return implode("\n", $html);
    }

    public function writeFromPlainText(string $text): string
    {
        return nl2br(htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    private function renderSegments(array $segments): string
    {
        $parts = [];
        foreach ($segments as $segment) {
            if (isset($segment['embed'])) {
                $parts[] = $this->renderEmbed($segment['embed']);
                continue;
            }
            $parts[] = $this->applyInlineFormatting($segment['text'] ?? '', $segment['attrs'] ?? []);
        }

        return implode('', $parts);
    }

    private function renderSegmentsPlain(array $segments): string
    {
        $parts = [];
        foreach ($segments as $segment) {
            if (isset($segment['embed'])) {
                $parts[] = $this->renderEmbedPlain($segment['embed']);
                continue;
            }
            $parts[] = $segment['text'] ?? '';
        }

        return implode('', $parts);
    }

    private function applyInlineFormatting(string $text, array $attrs): string
    {
        $value = htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (!empty($attrs['code'])) {
            $value = '<code>' . $value . '</code>';
        } else {
            if (!empty($attrs['strike'])) {
                $value = '<s>' . $value . '</s>';
            }
            if (!empty($attrs['bold'])) {
                $value = '<strong>' . $value . '</strong>';
            }
            if (!empty($attrs['italic'])) {
                $value = '<em>' . $value . '</em>';
            }
            if (!empty($attrs['underline'])) {
                $value = '<u>' . $value . '</u>';
            }
        }

        if (!empty($attrs['link'])) {
            $href = htmlspecialchars((string) $attrs['link'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = '<a href="' . $href . '">' . $value . '</a>';
        }

        return $value;
    }

    private function renderEmbed(array $embed): string
    {
        if (isset($embed['image'])) {
            $src = htmlspecialchars($embed['image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<img src="' . $src . '" alt=""/>';
        }

        if (isset($embed['video'])) {
            $src = htmlspecialchars((string) $embed['video'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<a href="' . $src . '">' . $src . '</a>';
        }

        return '';
    }
}
