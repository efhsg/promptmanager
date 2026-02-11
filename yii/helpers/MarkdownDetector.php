<?php

namespace app\helpers;

/**
 * Detects if text content is markdown formatted using weighted scoring.
 * Structural patterns (headers, lists) score higher per occurrence;
 * inline patterns (bold, italic) are capped to prevent false positives.
 */
class MarkdownDetector
{
    private const SCORE_THRESHOLD = 3;

    private const STRUCTURAL_PATTERNS = [
        'header' => [
            'pattern' => '/^#{1,6}\s/m',
            'weight' => 2,
            'max' => 0, // 0 = unlimited
        ],
        'unordered_list' => [
            'pattern' => '/^[\-\*\+]\s+\S/m',
            'weight' => 1,
            'max' => 3,
        ],
        'ordered_list' => [
            'pattern' => '/^\d+\.\s+\S/m',
            'weight' => 1,
            'max' => 3,
        ],
        'code_block' => [
            'pattern' => '/```/',
            'weight' => 3,
            'max' => 1,
        ],
        'blockquote' => [
            'pattern' => '/^>\s/m',
            'weight' => 2,
            'max' => 0,
        ],
        'horizontal_rule' => [
            'pattern' => '/^(?:---+|\*\*\*+|___+)\s*$/m',
            'weight' => 2,
            'max' => 0,
        ],
    ];

    private const INLINE_PATTERNS = [
        'bold' => [
            'pattern' => '/\*\*.+?\*\*/s',
            'weight' => 1,
            'max' => 2,
        ],
        'italic' => [
            'pattern' => '/(?<!\*)\*(?!\*)[^*\n]+(?<!\*)\*(?!\*)/s',
            'weight' => 1,
            'max' => 2,
        ],
        'inline_code' => [
            'pattern' => '/`[^`\n]+`/',
            'weight' => 1,
            'max' => 2,
        ],
        'link' => [
            'pattern' => '/\[.+?\]\(.+?\)/',
            'weight' => 2,
            'max' => 2,
        ],
        'strikethrough' => [
            'pattern' => '/~~.+?~~/s',
            'weight' => 1,
            'max' => 2,
        ],
    ];

    public static function isMarkdown(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        $score = 0;
        $allPatterns = array_merge(self::STRUCTURAL_PATTERNS, self::INLINE_PATTERNS);

        foreach ($allPatterns as $config) {
            $count = preg_match_all($config['pattern'], $text);
            if ($count === 0) {
                continue;
            }

            $effective = $config['max'] > 0 ? min($count, $config['max']) : $count;
            $score += $effective * $config['weight'];

            if ($score >= self::SCORE_THRESHOLD) {
                return true;
            }
        }

        return false;
    }
}
