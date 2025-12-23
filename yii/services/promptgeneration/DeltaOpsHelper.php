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
        return $result;
    }
}
