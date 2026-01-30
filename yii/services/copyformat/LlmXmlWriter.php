<?php

namespace app\services\copyformat;

class LlmXmlWriter extends AbstractFormatWriter
{
    public function __construct(
        private readonly MarkdownWriter $markdownWriter = new MarkdownWriter()
    ) {
    }

    public function writeFromBlocks(array $blocks): string
    {
        $markdown = $this->markdownWriter->writeFromBlocks($blocks);
        return $this->buildXml($markdown);
    }

    public function writeFromHtml(string $html): string
    {
        $markdown = $this->markdownWriter->writeFromHtml($html);
        return $this->buildXml($markdown);
    }

    public function writeFromPlainText(string $text): string
    {
        return $this->buildXml(trim($text));
    }

    private function buildXml(string $markdown): string
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

        $isListItem = (bool) preg_match('/^[-*+]\s+|^\d+\.\s+|^\[(?:\s|x|X)]\s+/', $raw);
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
        return trim(preg_replace('/\s+/', ' ', implode(' ', $buffer)));
    }
}
