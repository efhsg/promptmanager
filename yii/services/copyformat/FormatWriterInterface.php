<?php

namespace app\services\copyformat;

interface FormatWriterInterface
{
    public function writeFromBlocks(array $blocks): string;

    public function writeFromHtml(string $html): string;

    public function writeFromPlainText(string $text): string;
}
