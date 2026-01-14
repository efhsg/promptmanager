<?php

namespace app\services\copyformat;

class MarkdownParser
{
    private const HEADER_PATTERN = '/^\s*(#{1,6})\s+(.+)$/';
    private const ORDERED_LIST_PATTERN = '/^(\s*)(\d+)\.\s+(.+)$/';
    private const UNORDERED_LIST_PATTERN = '/^(\s*)[-*+]\s+(.+)$/';
    private const CHECKED_LIST_PATTERN = '/^(\s*)[-*+]\s+\[x]\s+(.+)$/i';
    private const UNCHECKED_LIST_PATTERN = '/^(\s*)[-*+]\s+\[\s?]\s+(.+)$/';
    private const BLOCKQUOTE_PATTERN = '/^>\s?(.*)$/';
    private const CODE_FENCE_PATTERN = '/^```(\w*)$/';

    public function parse(string $markdown): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $blocks = [];
        $inCodeBlock = false;
        $codeLang = '';
        $codeLines = [];

        foreach ($lines as $line) {
            if (preg_match(self::CODE_FENCE_PATTERN, $line, $m)) {
                if ($inCodeBlock) {
                    $blocks = array_merge($blocks, $this->createCodeBlocks($codeLines, $codeLang));
                    $inCodeBlock = false;
                    $codeLines = [];
                    $codeLang = '';
                } else {
                    $inCodeBlock = true;
                    $codeLang = $m[1] ?: 'plain';
                }
                continue;
            }

            if ($inCodeBlock) {
                $codeLines[] = $line;
                continue;
            }

            $block = $this->parseLine($line);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        if ($inCodeBlock && !empty($codeLines)) {
            $blocks = array_merge($blocks, $this->createCodeBlocks($codeLines, $codeLang));
        }

        if (empty($blocks)) {
            $blocks[] = [
                'segments' => [['text' => '']],
                'attrs' => [],
            ];
        }

        return $this->collapseBlankLinesAfterHeaders($blocks);
    }

    private function collapseBlankLinesAfterHeaders(array $blocks): array
    {
        $result = [];
        $previousWasHeader = false;

        foreach ($blocks as $block) {
            $isBlankLine = $block['attrs'] === []
                && count($block['segments']) === 1
                && $block['segments'][0]['text'] === '';

            if ($previousWasHeader && $isBlankLine) {
                $previousWasHeader = false;
                continue;
            }

            $result[] = $block;
            $previousWasHeader = isset($block['attrs']['header']);
        }

        return $result;
    }

    private function parseLine(string $line): ?array
    {
        if (trim($line) === '') {
            return [
                'segments' => [['text' => '']],
                'attrs' => [],
            ];
        }

        if (preg_match(self::HEADER_PATTERN, $line, $m)) {
            return [
                'segments' => $this->parseInlineFormatting($m[2]),
                'attrs' => ['header' => strlen($m[1])],
            ];
        }

        if (preg_match(self::BLOCKQUOTE_PATTERN, $line, $m)) {
            return [
                'segments' => $this->parseInlineFormatting($m[1]),
                'attrs' => ['blockquote' => true],
            ];
        }

        if (preg_match(self::CHECKED_LIST_PATTERN, $line, $m)) {
            $indent = $this->calculateIndentLevel($m[1]);
            $attrs = ['list' => 'checked'];
            if ($indent > 0) {
                $attrs['indent'] = $indent;
            }
            return [
                'segments' => $this->parseInlineFormatting($m[2]),
                'attrs' => $attrs,
            ];
        }

        if (preg_match(self::UNCHECKED_LIST_PATTERN, $line, $m)) {
            $indent = $this->calculateIndentLevel($m[1]);
            $attrs = ['list' => 'unchecked'];
            if ($indent > 0) {
                $attrs['indent'] = $indent;
            }
            return [
                'segments' => $this->parseInlineFormatting($m[2]),
                'attrs' => $attrs,
            ];
        }

        if (preg_match(self::ORDERED_LIST_PATTERN, $line, $m)) {
            $indent = $this->calculateIndentLevel($m[1]);
            $attrs = ['list' => 'ordered'];
            if ($indent > 0) {
                $attrs['indent'] = $indent;
            }
            return [
                'segments' => $this->parseInlineFormatting($m[3]),
                'attrs' => $attrs,
            ];
        }

        if (preg_match(self::UNORDERED_LIST_PATTERN, $line, $m)) {
            $indent = $this->calculateIndentLevel($m[1]);
            $attrs = ['list' => 'bullet'];
            if ($indent > 0) {
                $attrs['indent'] = $indent;
            }
            return [
                'segments' => $this->parseInlineFormatting($m[2]),
                'attrs' => $attrs,
            ];
        }

        return [
            'segments' => $this->parseInlineFormatting($line),
            'attrs' => [],
        ];
    }

    private function parseInlineFormatting(string $text): array
    {
        if ($text === '') {
            return [['text' => '', 'attrs' => null]];
        }

        $segments = [];
        $remaining = $text;
        $offset = 0;

        $pattern = '/(\*\*(.+?)\*\*|(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)|~~(.+?)~~|`([^`]+)`|\[([^]]+)]\(([^)]+)\))/';

        while (preg_match($pattern, $remaining, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $matchStart = $m[0][1];
            $fullMatch = $m[0][0];

            if ($matchStart > $offset) {
                $beforeText = substr($remaining, $offset, $matchStart - $offset);
                if ($beforeText !== '') {
                    $segments[] = ['text' => $beforeText, 'attrs' => null];
                }
            }

            if (str_starts_with($fullMatch, '**')) {
                $segments[] = ['text' => $m[2][0], 'attrs' => ['bold' => true]];
            } elseif (str_starts_with($fullMatch, '~~')) {
                $segments[] = ['text' => $m[4][0], 'attrs' => ['strike' => true]];
            } elseif (str_starts_with($fullMatch, '`')) {
                $segments[] = ['text' => $m[5][0], 'attrs' => ['code' => true]];
            } elseif (str_starts_with($fullMatch, '[')) {
                $segments[] = ['text' => $m[6][0], 'attrs' => ['link' => $m[7][0]]];
            } elseif (str_starts_with($fullMatch, '*')) {
                $segments[] = ['text' => $m[3][0], 'attrs' => ['italic' => true]];
            }

            $offset = $matchStart + strlen($fullMatch);
        }

        if ($offset < strlen($remaining)) {
            $afterText = substr($remaining, $offset);
            if ($afterText !== '') {
                $segments[] = ['text' => $afterText, 'attrs' => null];
            }
        }

        return empty($segments) ? [['text' => $text, 'attrs' => null]] : $segments;
    }

    private function createCodeBlocks(array $lines, string $lang): array
    {
        $blocks = [];
        $codeBlockAttr = $lang === 'plain' || $lang === '' ? true : $lang;

        foreach ($lines as $line) {
            $blocks[] = [
                'segments' => [['text' => $line]],
                'attrs' => ['code-block' => $codeBlockAttr],
            ];
        }

        if (empty($blocks)) {
            $blocks[] = [
                'segments' => [['text' => '']],
                'attrs' => ['code-block' => $codeBlockAttr],
            ];
        }

        return $blocks;
    }

    private function calculateIndentLevel(string $whitespace): int
    {
        $spaces = strlen($whitespace);
        return (int) floor($spaces / 2);
    }
}
