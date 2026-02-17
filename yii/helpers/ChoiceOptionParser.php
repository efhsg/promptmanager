<?php

namespace app\helpers;

/**
 * Parses AI response text for interactive choice button patterns.
 * PHP mirror of the JavaScript parseChoiceOptions() in ai-chat/index.php.
 */
class ChoiceOptionParser
{
    private const EDIT_WORDS = ['bewerk', 'edit', 'aanpassen', 'modify', 'adjust'];
    private const SLASH_MAX_LENGTH = 80;
    private const BRACKET_MAX_LENGTH = 40;

    /**
     * @return array<array{label: string, action: string}>|null
     */
    public static function parse(string $text): ?array
    {
        if (!$text) {
            return null;
        }

        $lines = explode("\n", rtrim($text));
        $lastLine = '';
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $trimmed = trim($lines[$i]);
            if ($trimmed) {
                $lastLine = $trimmed;
                break;
            }
        }
        if (!$lastLine) {
            return null;
        }

        // Format 1 & 2: slash-separated
        $result = self::parseSlash($lastLine);
        if ($result !== null) {
            return $result;
        }

        // Format 3: bracket-letter lines
        $result = self::parseBracketLines($lines);
        if ($result !== null) {
            return $result;
        }

        // Format 4: inline bracket-letter
        return self::parseInlineBracket($lastLine);
    }

    /**
     * @return array<array{label: string, action: string}>|null
     */
    private static function parseSlash(string $lastLine): ?array
    {
        if (strpos($lastLine, ' / ') === false) {
            return null;
        }

        $choicePart = $lastLine;
        if (preg_match('/\(([^)]*\/[^)]*)\)/', $lastLine, $m)) {
            $choicePart = trim($m[1]);
        }

        $cleaned = trim(preg_replace('/\??\s*$/', '', $choicePart));
        $parts = explode(' / ', $cleaned);

        // Strip context prefix: "Geen verbeterpunten — door naar Architect / Aanpassen"
        if (count($parts) >= 2 && preg_match("/[\x{2014}\x{2013}]\s/u", $parts[0])) {
            $parts[0] = preg_replace("/^.*[\x{2014}\x{2013}]\s+/u", '', $parts[0]);
        }

        if (count($parts) < 2 || count($parts) > 4) {
            return null;
        }

        $options = [];
        foreach ($parts as $part) {
            $label = self::stripMd($part);
            if (!$label || mb_strlen($label) > self::SLASH_MAX_LENGTH) {
                return null;
            }

            $options[] = [
                'label' => $label,
                'action' => in_array(mb_strtolower($label), self::EDIT_WORDS) ? 'edit' : 'send',
            ];
        }

        return $options;
    }

    /**
     * @return array<array{label: string, action: string}>|null
     */
    private static function parseBracketLines(array $lines): ?array
    {
        $options = [];
        for ($b = count($lines) - 1; $b >= 0; $b--) {
            $line = trim($lines[$b]);
            if (!$line) {
                continue;
            }
            if (preg_match('/^\[([A-Z])\]\s+(.{1,' . self::BRACKET_MAX_LENGTH . '})$/', $line, $m)) {
                $label = self::stripMd($m[2]);
                array_unshift($options, [
                    'label' => $label,
                    'action' => in_array(mb_strtolower($label), self::EDIT_WORDS) ? 'edit' : 'send',
                ]);
            } else {
                break;
            }
        }

        return (count($options) >= 2 && count($options) <= 5) ? $options : null;
    }

    /**
     * @return array<array{label: string, action: string}>|null
     */
    private static function parseInlineBracket(string $lastLine): ?array
    {
        if (!preg_match_all('/\[([A-Z])\]\s+(.+?)(?=\s*\[[A-Z]\]|$)/', $lastLine, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $options = [];
        foreach ($matches as $m) {
            $label = self::stripMd($m[2]);
            if (!$label || mb_strlen($label) > self::BRACKET_MAX_LENGTH) {
                return null;
            }

            $options[] = [
                'label' => $label,
                'action' => in_array(mb_strtolower($label), self::EDIT_WORDS) ? 'edit' : 'send',
            ];
        }

        return (count($options) >= 2 && count($options) <= 5) ? $options : null;
    }

    private static function stripMd(string $s): string
    {
        return trim(preg_replace('/[*_`~]/', '', $s));
    }
}
