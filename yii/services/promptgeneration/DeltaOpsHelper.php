<?php

namespace app\services\promptgeneration;

use JsonException;

class DeltaOpsHelper
{
    public function extractOpsFromDelta(string $deltaJson): array
    {
        if ($deltaJson === '') {
            return [];
        }

        try {
            $decoded = json_decode($deltaJson, true, 512, JSON_THROW_ON_ERROR);
            if (!isset($decoded['ops']) || !is_array($decoded['ops'])) {
                return [['insert' => $deltaJson]];
            }

            return $decoded['ops'];
        } catch (JsonException) {
            return [['insert' => $deltaJson]];
        }
    }

    public function extractPlainTextFromDelta(string $deltaJson): string
    {
        if ($deltaJson === '') {
            return '';
        }

        try {
            $decoded = json_decode($deltaJson, true, 512, JSON_THROW_ON_ERROR);
            if (!isset($decoded['ops']) || !is_array($decoded['ops'])) {
                return $deltaJson;
            }

            $text = '';
            foreach ($decoded['ops'] as $op) {
                if (isset($op['insert']) && is_string($op['insert'])) {
                    $text .= $op['insert'];
                }
            }

            return trim($text);
        } catch (JsonException) {
            return $deltaJson;
        }
    }

    public function analyzeFieldContent(array $fieldOps): array
    {
        $isListBlock = false;
        $isCodeBlock = false;

        foreach ($fieldOps as $fieldOp) {
            if (isset($fieldOp['attributes']['list'])) {
                $isListBlock = true;
            }
            if (isset($fieldOp['attributes']['code-block'])) {
                $isCodeBlock = true;
            }
        }

        return [
            'isListBlock' => $isListBlock,
            'isCodeBlock' => $isCodeBlock,
        ];
    }

    public function removeConsecutiveNewlines(array $ops): array
    {
        $result = [];
        foreach ($ops as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $op['insert'] = preg_replace('/\n{2,}/', "\n", $op['insert']);

                if ($op['insert'] !== '' || isset($op['attributes'])) {
                    $result[] = $op;
                }
            } else {
                $result[] = $op;
            }
        }

        return $this->removeEmptyLinesBetweenListItems($result);
    }

    /**
     * Strips leading newlines from text ops that follow list-attributed newlines.
     * This prevents Quill from creating separate lists for each item.
     */
    private function removeEmptyLinesBetweenListItems(array $ops): array
    {
        $result = [];

        foreach ($ops as $op) {
            // Check if previous op was a list-attributed newline
            $prevIsListNewline = !empty($result) && $this->isListAttributedNewline($result[array_key_last($result)]);

            if ($prevIsListNewline && isset($op['insert']) && is_string($op['insert'])) {
                // Strip leading newlines from this op
                $op['insert'] = ltrim($op['insert'], "\n");
                if ($op['insert'] === '') {
                    continue; // Skip if nothing left
                }
            }

            $result[] = $op;
        }

        return $result;
    }

    private function isListAttributedNewline(array $op): bool
    {
        return isset($op['insert'])
            && is_string($op['insert'])
            && str_contains($op['insert'], "\n")
            && isset($op['attributes']['list']);
    }
}
