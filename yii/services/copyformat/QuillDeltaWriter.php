<?php

namespace app\services\copyformat;

class QuillDeltaWriter extends AbstractFormatWriter
{
    public function __construct(
        private readonly DeltaParser $parser = new DeltaParser()
    ) {
    }

    public function writeFromBlocks(array $blocks): string
    {
        return $this->parser->encode(['ops' => $this->blocksToOps($blocks)]);
    }

    public function writeFromHtml(string $html): string
    {
        return $html;
    }

    public function writeFromPlainText(string $text): string
    {
        return trim($text);
    }

    private function blocksToOps(array $blocks): array
    {
        $ops = [];
        foreach ($blocks as $block) {
            foreach ($block['segments'] as $segment) {
                if (isset($segment['embed'])) {
                    $ops[] = ['insert' => $segment['embed']];
                } else {
                    $op = ['insert' => ($segment['text'] ?? '') . "\n"];
                    if (!empty($segment['attrs'])) {
                        $op['attributes'] = $segment['attrs'];
                    }
                    if (!empty($block['attrs'])) {
                        $op['attributes'] = array_merge($op['attributes'] ?? [], $block['attrs']);
                    }
                    $ops[] = $op;
                }
            }
        }
        return $ops;
    }
}
