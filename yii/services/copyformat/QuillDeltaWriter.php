<?php

namespace app\services\copyformat;

class QuillDeltaWriter extends AbstractFormatWriter
{
    public function __construct(
        private readonly DeltaParser $parser = new DeltaParser()
    ) {}

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
                    $text = $segment['text'] ?? '';
                    if ($text !== '' || !empty($segment['attrs'])) {
                        $op = ['insert' => $text];
                        if (!empty($segment['attrs'])) {
                            $op['attributes'] = $segment['attrs'];
                        }
                        $ops[] = $op;
                    }
                }
            }

            $newlineOp = ['insert' => "\n"];
            if (!empty($block['attrs'])) {
                $newlineOp['attributes'] = $block['attrs'];
            }
            $ops[] = $newlineOp;
        }
        return $ops;
    }
}
