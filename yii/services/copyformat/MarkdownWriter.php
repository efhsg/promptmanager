<?php

namespace app\services\copyformat;

use League\HTMLToMarkdown\HtmlConverter;

class MarkdownWriter extends AbstractFormatWriter
{
    public function writeFromBlocks(array $blocks): string
    {
        $lines = [];
        $listActive = false;
        $listCounters = [];
        $codeActive = false;
        $codeLang = '';
        $codeLines = [];

        $flushCode = static function () use (&$lines, &$codeActive, &$codeLang, &$codeLines): void {
            if (!$codeActive) {
                return;
            }

            $fence = '```' . ($codeLang && $codeLang !== 'plain' ? $codeLang : '');
            $lines[] = $fence;
            foreach ($codeLines as $codeLine) {
                $lines[] = $codeLine;
            }
            $lines[] = '```';
            $lines[] = '';
            $codeActive = false;
            $codeLang = '';
            $codeLines = [];
        };

        $endList = static function () use (&$listActive, &$listCounters, &$lines): void {
            if ($listActive) {
                $lines[] = '';
            }
            $listActive = false;
            $listCounters = [];
        };

        foreach ($blocks as $block) {
            $attrs = $block['attrs'] ?? [];
            $lineText = $this->renderSegments($block['segments'], $attrs);

            if (!empty($attrs['code-block'])) {
                $endList();
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
                $listActive = true;
                $indent = (int) ($attrs['indent'] ?? 0);
                $indent = max($indent, 0);
                $indentSpaces = str_repeat('    ', $indent);

                $listCounters = array_slice($listCounters, 0, $indent + 1);
                $prefix = '- ';

                if ($attrs['list'] === 'ordered') {
                    $count = $listCounters[$indent] ?? 1;
                    $prefix = $count . '. ';
                    $listCounters[$indent] = $count + 1;
                } elseif ($attrs['list'] === 'checked' || $attrs['list'] === 'unchecked') {
                    $prefix = '- [' . ($attrs['list'] === 'checked' ? 'x' : ' ') . '] ';
                    $listCounters[$indent] ??= 1;
                } else {
                    $listCounters[$indent] ??= 1;
                }

                $lines[] = $indentSpaces . $prefix . trim($lineText);
                continue;
            }

            if ($listActive) {
                $endList();
            }

            if (!empty($attrs['header'])) {
                $headerLevel = (int) $attrs['header'];
                $headerLevel = $headerLevel < 1 ? 1 : (min($headerLevel, 6));
                $hashes = str_repeat('#', $headerLevel);
                $lines[] = $hashes . ' ' . trim($lineText);
                $lines[] = '';
                continue;
            }

            if (!empty($attrs['blockquote'])) {
                $quoteLines = preg_split('/\r?\n/', trim($lineText)) ?: [];
                foreach ($quoteLines as $quoteLine) {
                    $lines[] = '> ' . trim($quoteLine);
                }
                $lines[] = '';
                continue;
            }

            $paragraph = trim($lineText);
            $lines[] = $paragraph;
            $lines[] = '';
        }

        $flushCode();
        if ($listActive) {
            $endList();
        }

        while (!empty($lines) && end($lines) === '') {
            array_pop($lines);
        }

        $cleaned = [];
        foreach ($lines as $line) {
            if ($line === '' && (!empty($cleaned) && end($cleaned) === '')) {
                continue;
            }
            $cleaned[] = $line;
        }

        return implode("\n", $cleaned);
    }

    public function writeFromHtml(string $html): string
    {
        $converter = new HtmlConverter(['strip_tags' => true]);
        return trim($converter->convert($html));
    }

    private function renderSegments(array $segments, array $blockAttrs): string
    {
        $parts = [];
        $skipEscape = !empty($blockAttrs['code-block']);
        foreach ($segments as $segment) {
            if (isset($segment['embed'])) {
                $parts[] = $this->renderEmbed($segment['embed']);
                continue;
            }
            $parts[] = $this->applyInlineFormatting($segment['text'] ?? '', $segment['attrs'] ?? [], $skipEscape);
        }

        return implode('', $parts);
    }

    private function applyInlineFormatting(string $text, array $attrs, bool $skipEscape = false): string
    {
        $value = $skipEscape ? ($text ?? '') : $this->escapeMarkdown($text ?? '');

        if (!empty($attrs['code'])) {
            $value = '`' . ($text ?? '') . '`';
        } else {
            if (!empty($attrs['strike'])) {
                $value = '~~' . $value . '~~';
            }
            if (!empty($attrs['bold'])) {
                $value = '**' . $value . '**';
            }
            if (!empty($attrs['italic'])) {
                $value = '*' . $value . '*';
            }
            if (!empty($attrs['underline'])) {
                $value = '_' . $value . '_';
            }
        }

        if (!empty($attrs['link'])) {
            $value = '[' . $value . '](' . $attrs['link'] . ')';
        }

        return $value;
    }

    private function renderEmbed(array $embed): string
    {
        if (isset($embed['image'])) {
            return '![](' . $embed['image'] . ')';
        }

        if (isset($embed['video'])) {
            return (string) $embed['video'];
        }

        return '';
    }

    private function escapeMarkdown(string $text): string
    {
        return preg_replace('/([\\\\])/', '\\\\$1', $text);
    }
}
