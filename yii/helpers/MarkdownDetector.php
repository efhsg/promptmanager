<?php

namespace app\helpers;

/**
 * Detects if text content is markdown formatted.
 */
class MarkdownDetector
{
    private const PATTERNS = [
        'header' => '/^#{1,6}\s/m',
        'bold' => '/\*\*.+?\*\*/s',
        'italic' => '/(?<!\*)\*(?!\*)[^*\n]+(?<!\*)\*(?!\*)/s',
        'unordered_list' => '/^[\-\*\+]\s+\S/m',
        'ordered_list' => '/^\d+\.\s+\S/m',
        'code_block' => '/```/',
        'inline_code' => '/`[^`\n]+`/',
        'link' => '/\[.+?\]\(.+?\)/',
        'blockquote' => '/^>\s/m',
    ];

    private const MIN_MATCHES = 2;

    public static function isMarkdown(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        $matchCount = 0;

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                $matchCount++;
                if ($matchCount >= self::MIN_MATCHES) {
                    return true;
                }
            }
        }

        return false;
    }
}
