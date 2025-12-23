<?php

namespace app\services\copyformat;

use JsonException;

class DeltaParser
{
    public function decode(string $content): ?array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (isset($decoded['ops']) && is_array($decoded['ops'])) {
            return ['ops' => $decoded['ops']];
        }

        if (is_array($decoded)) {
            return ['ops' => $decoded];
        }

        return null;
    }

    public function encode(array $delta): string
    {
        return json_encode($delta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function buildBlocks(array $ops): array
    {
        $blocks = [];
        $segments = [];

        $pushLine = static function (?array $attrs) use (&$blocks, &$segments): void {
            $blocks[] = [
                'segments' => $segments,
                'attrs' => $attrs ?? [],
            ];
            $segments = [];
        };

        foreach ($ops as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $parts = explode("\n", $op['insert']);
                foreach ($parts as $idx => $part) {
                    if ($part !== '') {
                        $segments[] = [
                            'text' => $part,
                            'attrs' => $this->pickInlineAttributes($op['attributes'] ?? null),
                        ];
                    }

                    if ($idx < count($parts) - 1) {
                        $pushLine($this->pickBlockAttributes($op['attributes'] ?? null));
                    }
                }
            } elseif (isset($op['insert']) && is_array($op['insert'])) {
                $segments[] = ['embed' => $op['insert']];
            }
        }

        if (!empty($segments)) {
            $pushLine([]);
        }

        return $blocks;
    }

    private function pickInlineAttributes(?array $attrs): ?array
    {
        if (!$attrs) {
            return null;
        }

        $keys = ['bold', 'italic', 'underline', 'strike', 'code', 'link'];
        $inline = [];
        foreach ($keys as $key) {
            if (!empty($attrs[$key])) {
                $inline[$key] = $attrs[$key];
            }
        }

        return $inline ?: null;
    }

    private function pickBlockAttributes(?array $attrs): array
    {
        if (!$attrs) {
            return [];
        }

        $keys = ['header', 'blockquote', 'list', 'code-block', 'align', 'indent'];
        $block = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $attrs) && $attrs[$key] !== null) {
                $block[$key] = $attrs[$key];
            }
        }

        return $block;
    }
}
