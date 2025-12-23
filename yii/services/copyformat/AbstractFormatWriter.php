<?php

namespace app\services\copyformat;

abstract class AbstractFormatWriter implements FormatWriterInterface
{
    public function writeFromHtml(string $html): string
    {
        return $html;
    }

    public function writeFromPlainText(string $text): string
    {
        return trim($text);
    }

    protected function renderEmbedPlain(array $embed): string
    {
        if (isset($embed['image'])) {
            return (string)$embed['image'];
        }

        if (isset($embed['video'])) {
            return (string)$embed['video'];
        }

        return '';
    }
}
