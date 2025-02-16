<?php
namespace app\helpers;

class TooltipHelper
{
    public static function prepareTexts(array $items, int $maxLength): array
    {
        return array_map(static function ($content) use ($maxLength) {
            $cleanContent = strip_tags($content);
            return (strlen($cleanContent) > $maxLength)
                ? substr($cleanContent, 0, $maxLength) . '...'
                : $cleanContent;
        }, $items);
    }
}
