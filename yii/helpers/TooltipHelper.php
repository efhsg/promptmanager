<?php

namespace app\helpers;

/**
 * Helper for preparing tooltip texts by stripping HTML and truncating long content.
 *
 * Provides a single static method to clean and shorten an array of strings for display.
 */
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
