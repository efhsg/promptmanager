<?php

namespace app\services;

use JsonException;

/**
 * Extracts plain text from Quill Delta JSON for search indexing.
 *
 * Concatenates all insert string values from Delta ops, producing a
 * searchable plain text representation that works regardless of formatting.
 */
class SearchTextExtractor
{
    /**
     * Extracts searchable text from content that may be Quill Delta JSON or plain text.
     *
     * Returns extracted Delta text when valid, raw content otherwise.
     */
    public static function extract(?string $content): string
    {
        if ($content === null || trim($content) === '') {
            return '';
        }

        $deltaText = self::fromDelta($content);
        if ($deltaText !== '') {
            return $deltaText;
        }

        return trim($content);
    }

    /**
     * Extracts plain text from Quill Delta JSON by concatenating all insert values.
     */
    public static function fromDelta(?string $deltaJson): string
    {
        if ($deltaJson === null || trim($deltaJson) === '') {
            return '';
        }

        try {
            $data = json_decode($deltaJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }

        $ops = $data['ops'] ?? (is_array($data) ? $data : []);
        $text = '';

        foreach ($ops as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $text .= $op['insert'];
            }
        }

        return trim($text);
    }
}
