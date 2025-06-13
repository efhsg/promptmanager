<?php

namespace app\presenters;

use JsonException;

/**
 * Extracts and truncates Quill deltas for display.
 */
class PromptInstancePresenter
{
    /**
     * Turn a Quill‐style delta JSON into plain text.
     *
     * @param string $deltaJson
     * @return string
     */
    public static function extractPlain(string $deltaJson): string
    {
        try {
            $delta = json_decode(
                $deltaJson,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return strip_tags($deltaJson);
        }

        $text = '';
        foreach ($delta['ops'] ?? [] as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $text .= $op['insert'];
            }
        }

        return trim($text);
    }
}
