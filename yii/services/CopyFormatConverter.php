<?php

namespace app\services;

use common\enums\CopyType;
use JsonException;
use League\HTMLToMarkdown\HtmlConverter;

class CopyFormatConverter
{
    public function convertFromQuillDelta(string $content, CopyType $type): string
    {
        $delta = $this->decodeDelta($content);
        if ($delta === null) {
            return '';
        }

        return match ($type) {
            CopyType::MD => $this->deltaToMarkdown($delta),
            CopyType::TEXT => $this->deltaToPlainText($delta),
            CopyType::HTML => $this->deltaToHtml($delta),
            CopyType::LLM_XML => $this->buildLlmXml($this->deltaToMarkdown($delta)),
            CopyType::QUILL_DELTA => $this->encodeDelta($delta),
        };
    }

    public function convertFromHtml(string $content, CopyType $type): string
    {
        return match ($type) {
            CopyType::MD => $this->htmlToMarkdown($content),
            CopyType::TEXT => $this->htmlToText($content),
            CopyType::HTML => $content,
            CopyType::LLM_XML => $this->buildLlmXml($this->htmlToMarkdown($content)),
            CopyType::QUILL_DELTA => $content,
        };
    }

    public function convertFromPlainText(string $content, CopyType $type): string
    {
        return match ($type) {
            CopyType::MD, CopyType::TEXT => trim($content),
            CopyType::HTML => nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            CopyType::LLM_XML => $this->buildLlmXml(trim($content)),
            CopyType::QUILL_DELTA => trim($content),
        };
    }

    private function decodeDelta(string $content): ?array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (isset($decoded['ops']) && is_array($decoded['ops'])) {
            return ['ops' => $decoded['ops']];
        }

        if (is_array($decoded)) {
            return ['ops' => $decoded];
        }

        return null;
    }

    private function encodeDelta(array $delta): string
    {
        return json_encode($delta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function deltaToMarkdown(array $delta): string
    {
        $blocks = $this->buildBlocks($delta['ops'] ?? []);

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
            $lineText = $this->renderSegmentsMarkdown($block['segments'], $attrs);

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
                $indent = (int)($attrs['indent'] ?? 0);
                $indent = $indent < 0 ? 0 : $indent;
                $indentSpaces = str_repeat('  ', $indent);

                $listCounters = array_slice($listCounters, 0, $indent + 1);
                $prefix = '- ';

                if ($attrs['list'] === 'ordered') {
                    $count = $listCounters[$indent] ?? 1;
                    $prefix = $count . '. ';
                    $listCounters[$indent] = $count + 1;
                } elseif ($attrs['list'] === 'checked' || $attrs['list'] === 'unchecked') {
                    $prefix = '- [' . ($attrs['list'] === 'checked' ? 'x' : ' ') . '] ';
                    $listCounters[$indent] = $listCounters[$indent] ?? 1;
                } else {
                    $listCounters[$indent] = $listCounters[$indent] ?? 1;
                }

                $lines[] = $indentSpaces . $prefix . trim($lineText);
                continue;
            }

            if ($listActive) {
                $endList();
            }

            if (!empty($attrs['header'])) {
                $headerLevel = (int)$attrs['header'];
                $headerLevel = $headerLevel < 1 ? 1 : ($headerLevel > 6 ? 6 : $headerLevel);
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

    private function deltaToPlainText(array $delta): string
    {
        $blocks = $this->buildBlocks($delta['ops'] ?? []);
        $lines = [];
        $listActive = false;
        $listCounters = [];
        $codeActive = false;
        $codeLines = [];

        $flushCode = static function () use (&$lines, &$codeActive, &$codeLines): void {
            if (!$codeActive) {
                return;
            }

            foreach ($codeLines as $codeLine) {
                $lines[] = $codeLine;
            }
            $codeActive = false;
            $codeLines = [];
        };

        $endList = static function () use (&$listActive, &$listCounters): void {
            $listActive = false;
            $listCounters = [];
        };

        foreach ($blocks as $block) {
            $attrs = $block['attrs'] ?? [];
            $lineText = $this->renderSegmentsPlain($block['segments'], true);

            if (!empty($attrs['code-block'])) {
                $endList();
                $codeActive = true;
                $codeLines[] = $lineText;
                continue;
            }

            $flushCode();

            if (!empty($attrs['list'])) {
                $listActive = true;
                $indent = (int)($attrs['indent'] ?? 0);
                $indent = $indent < 0 ? 0 : $indent;
                $indentSpaces = str_repeat('  ', $indent);

                $listCounters = array_slice($listCounters, 0, $indent + 1);
                $prefix = '- ';

                if ($attrs['list'] === 'ordered') {
                    $count = $listCounters[$indent] ?? 1;
                    $prefix = $count . '. ';
                    $listCounters[$indent] = $count + 1;
                } elseif ($attrs['list'] === 'checked' || $attrs['list'] === 'unchecked') {
                    $prefix = '- [' . ($attrs['list'] === 'checked' ? 'x' : ' ') . '] ';
                    $listCounters[$indent] = $listCounters[$indent] ?? 1;
                } else {
                    $listCounters[$indent] = $listCounters[$indent] ?? 1;
                }

                $lines[] = $indentSpaces . $prefix . trim($lineText);
                continue;
            }

            if ($listActive) {
                $endList();
            }

            $lines[] = trim($lineText);
        }

        $flushCode();
        return trim(implode("\n", array_filter($lines, static fn($line) => $line !== '')));
    }

    private function deltaToHtml(array $delta): string
    {
        $blocks = $this->buildBlocks($delta['ops'] ?? []);
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
                : $this->renderSegmentsHtml($block['segments'], $attrs);

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
                $indent = (int)($attrs['indent'] ?? 0);
                $indent = $indent < 0 ? 0 : $indent;

                $closeListsTo($indent);
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

            $closeListsTo(0);

            if (!empty($attrs['header'])) {
                $level = (int)$attrs['header'];
                $level = $level < 1 ? 1 : ($level > 6 ? 6 : $level);
                $html[] = '<h' . $level . '>' . $lineText . '</h' . $level . '>';
                continue;
            }

            if (!empty($attrs['blockquote'])) {
                $html[] = '<blockquote><p>' . $lineText . '</p></blockquote>';
                continue;
            }

            $html[] = '<p>' . $lineText . '</p>';
        }

        $flushCode();
        $closeListsTo(0);

        return implode("\n", $html);
    }

    private function buildBlocks(array $ops): array
    {
        $blocks = [];
        $segments = [];

        $pushLine = static function (?array $attrs) use (&$blocks, &$segments): void {
            $blocks[] = [
                'segments' => $segments,
                'attrs' => $attrs ?? [],
            ];
            $segments = [];
        };

        foreach ($ops as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $parts = explode("\n", $op['insert']);
                foreach ($parts as $idx => $part) {
                    if ($part !== '') {
                        $segments[] = [
                            'text' => $part,
                            'attrs' => $this->pickInlineAttributes($op['attributes'] ?? null),
                        ];
                    }

                    if ($idx < count($parts) - 1) {
                        $pushLine($this->pickBlockAttributes($op['attributes'] ?? null));
                    }
                }
            } elseif (isset($op['insert']) && is_array($op['insert'])) {
                $segments[] = ['embed' => $op['insert']];
            }
        }

        if (!empty($segments)) {
            $pushLine([]);
        }

        return $blocks;
    }

    private function pickInlineAttributes(?array $attrs): ?array
    {
        if (!$attrs) {
            return null;
        }

        $keys = ['bold', 'italic', 'underline', 'strike', 'code', 'link'];
        $inline = [];
        foreach ($keys as $key) {
            if (!empty($attrs[$key])) {
                $inline[$key] = $attrs[$key];
            }
        }

        return $inline ?: null;
    }

    private function pickBlockAttributes(?array $attrs): array
    {
        if (!$attrs) {
            return [];
        }

        $keys = ['header', 'blockquote', 'list', 'code-block', 'align', 'indent'];
        $block = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $attrs) && $attrs[$key] !== null) {
                $block[$key] = $attrs[$key];
            }
        }

        return $block;
    }

    private function renderSegmentsMarkdown(array $segments, array $blockAttrs): string
    {
        $parts = [];
        $skipEscape = !empty($blockAttrs['code-block']);
        foreach ($segments as $segment) {
            if (isset($segment['embed'])) {
                $parts[] = $this->renderEmbedMarkdown($segment['embed']);
                continue;
            }
            $parts[] = $this->applyInlineMarkdown($segment['text'] ?? '', $segment['attrs'] ?? [], $skipEscape);
        }

        return implode('', $parts);
    }

    private function renderSegmentsPlain(array $segments, bool $wrapInlineCode = false): string
    {
        $parts = [];
        foreach ($segments as $segment) {
            if (isset($segment['embed'])) {
                $parts[] = $this->renderEmbedPlain($segment['embed']);
                continue;
            }

            $text = $segment['text'] ?? '';
            if ($wrapInlineCode && !empty($segment['attrs']['code'])) {
                $text = '`' . $text . '`';
            }
            $parts[] = $text;
        }

        return implode('', $parts);
    }

    private function renderSegmentsHtml(array $segments, array $blockAttrs): string
    {
        $parts = [];
        foreach ($segments as $segment) {
            if (isset($segment['embed'])) {
                $parts[] = $this->renderEmbedHtml($segment['embed']);
                continue;
            }
            $parts[] = $this->applyInlineHtml($segment['text'] ?? '', $segment['attrs'] ?? []);
        }

        return implode('', $parts);
    }

    private function applyInlineMarkdown(string $text, array $attrs, bool $skipEscape = false): string
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

    private function applyInlineHtml(string $text, array $attrs): string
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
            $href = htmlspecialchars((string)$attrs['link'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = '<a href="' . $href . '">' . $value . '</a>';
        }

        return $value;
    }

    private function renderEmbedMarkdown(array $embed): string
    {
        if (isset($embed['image'])) {
            return '![](' . $embed['image'] . ')';
        }

        if (isset($embed['video'])) {
            return (string)$embed['video'];
        }

        return '';
    }

    private function renderEmbedHtml(array $embed): string
    {
        if (isset($embed['image'])) {
            $src = htmlspecialchars($embed['image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<img src="' . $src . '" alt=""/>';
        }

        if (isset($embed['video'])) {
            $src = htmlspecialchars((string)$embed['video'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<a href="' . $src . '">' . $src . '</a>';
        }

        return '';
    }

    private function renderEmbedPlain(array $embed): string
    {
        if (isset($embed['image'])) {
            return (string)$embed['image'];
        }

        if (isset($embed['video'])) {
            return (string)$embed['video'];
        }

        return '';
    }

    private function escapeMarkdown(string $text): string
    {
        return preg_replace('/([\\\\])/', '\\\\$1', $text);
    }

    private function htmlToMarkdown(string $content): string
    {
        $converter = new HtmlConverter(['strip_tags' => true]);
        return trim($converter->convert($content));
    }

    private function htmlToText(string $content): string
    {
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return trim(strip_tags($decoded));
    }

    private function buildLlmXml(string $markdown): string
    {
        $instructions = $this->buildInstructionList($markdown);
        if (empty($instructions)) {
            return '<instructions></instructions>';
        }

        $parts = ['<instructions>'];
        foreach ($instructions as $instruction) {
            $parts[] = '  <instruction>' . htmlspecialchars($instruction, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</instruction>';
        }
        $parts[] = '</instructions>';

        return implode("\n", $parts);
    }

    private function buildInstructionList(string $text): array
    {
        $instructions = [];
        $buffer = [];
        $lines = preg_split('/\r?\n/', $text) ?: [];

        foreach ($lines as $line) {
            $normalized = $this->normalizeInstructionLine($line);
            if ($normalized['text'] === '') {
                if (!empty($buffer)) {
                    $instructions[] = $this->collapseBuffer($buffer);
                    $buffer = [];
                }
                continue;
            }

            if ($normalized['isListItem']) {
                if (!empty($buffer)) {
                    $instructions[] = $this->collapseBuffer($buffer);
                    $buffer = [];
                }
                $instructions[] = $normalized['text'];
                continue;
            }

            $buffer[] = $normalized['text'];
        }

        if (!empty($buffer)) {
            $instructions[] = $this->collapseBuffer($buffer);
        }

        return $instructions;
    }

    private function normalizeInstructionLine(string $line): array
    {
        $raw = trim($line);
        if ($raw === '') {
            return ['text' => '', 'isListItem' => false];
        }

        $isListItem = (bool)preg_match('/^[-*+]\s+|^\d+\.\s+|^\[(?:\s|x|X)]\s+/', $raw);
        $raw = preg_replace('/^[-*+]\s+/', '', $raw);
        $raw = preg_replace('/^\d+\.\s+/', '', $raw);
        $raw = preg_replace('/^\[(?:\s|x|X)]\s+/', '', $raw);
        $raw = preg_replace('/^>+\s*/', '', $raw);
        $raw = preg_replace('/^#{1,6}\s+/', '', $raw);

        return [
            'text' => trim($raw),
            'isListItem' => $isListItem,
        ];
    }

    private function collapseBuffer(array $buffer): string
    {
        $combined = trim(preg_replace('/\s+/', ' ', implode(' ', $buffer)));
        return $combined;
    }
}
