<?php

namespace app\services\copyformat;

class PlainTextWriter extends AbstractFormatWriter
{
    public function writeFromBlocks(array $blocks): string
    {
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
            $lineText = $this->renderSegments($block['segments']);

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
                $indent = max($indent, 0);
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

    public function writeFromHtml(string $html): string
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return trim(strip_tags($decoded));
    }

    private function renderSegments(array $segments): string
    {
        $parts = [];
        foreach ($segments as $segment) {
            if (isset($segment['embed'])) {
                $parts[] = $this->renderEmbedPlain($segment['embed']);
                continue;
            }

            $text = $segment['text'] ?? '';
            if (!empty($segment['attrs']['code'])) {
                $text = '`' . $text . '`';
            }
            $parts[] = $text;
        }

        return implode('', $parts);
    }
}
