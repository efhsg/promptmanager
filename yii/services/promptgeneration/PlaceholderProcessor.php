<?php

namespace app\services\promptgeneration;

class PlaceholderProcessor
{
    private const PLACEHOLDER_PATTERN = '/\b(?:GEN|PRJ|EXT):\{\{(\d+)}}/';

    private array $templateFieldTypes = [];
    private array $templateFields = [];

    public function __construct(
        private readonly FieldValueBuilder $fieldValueBuilder = new FieldValueBuilder(),
        private readonly DeltaOpsHelper $deltaHelper = new DeltaOpsHelper()
    ) {}

    public function setFieldMappings(array $fieldTypes, array $fields): void
    {
        $this->templateFieldTypes = $fieldTypes;
        $this->templateFields = $fields;
    }

    public function process(array $templateDelta, array &$fieldValues): array
    {
        $finalOps = [];
        $opCount = count($templateDelta);

        for ($i = 0; $i < $opCount; $i++) {
            $op = $templateDelta[$i];

            if (!isset($op['insert']) || !is_string($op['insert'])) {
                $finalOps[] = $op;
                continue;
            }

            $text = $op['insert'];

            if (preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches, PREG_SET_ORDER)) {
                $currentAttrs = $op['attributes'] ?? null;

                // If current op has no block attrs, check NEXT op for attributed newline
                // Quill stores list items as: {"insert":"text"}, {"insert":"\n","attributes":{"list":"ordered"}}
                $effectiveAttrs = $currentAttrs;
                if (empty($this->extractBlockAttributes($currentAttrs)) && $i + 1 < $opCount) {
                    $nextOp = $templateDelta[$i + 1];
                    if (
                        isset($nextOp['insert'])
                        && $nextOp['insert'] === "\n"
                        && isset($nextOp['attributes'])
                        && !empty($this->extractBlockAttributes($nextOp['attributes']))
                    ) {
                        $effectiveAttrs = $nextOp['attributes'];
                        // Append the newline to text so it gets processed with the correct attributes
                        $text .= "\n";
                        $i++; // Skip the next op since we're consuming it
                    }
                }

                $finalOps = $this->processPlaceholders($text, $matches, $fieldValues, $finalOps, $effectiveAttrs);
            } else {
                $finalOps[] = $op;
            }
        }

        return $finalOps;
    }

    private function processPlaceholders(
        string $text,
        array $matches,
        array &$fieldValues,
        array $finalOps,
        ?array $originalAttributes
    ): array {
        $originalBlockAttrs = $this->extractBlockAttributes($originalAttributes);
        $lastFieldOps = [];

        foreach ($matches as $match) {
            $placeholder = $match[0];
            $fieldId = (int) $match[1];

            [$beforeText, $afterText] = array_pad(explode($placeholder, $text, 2), 2, '');

            $fieldOps = $this->getFieldOperations($fieldValues, $fieldId);
            $fieldAnalysis = $this->deltaHelper->analyzeFieldContent($fieldOps);

            // If original op has block attributes (list, header, etc.) and field content doesn't,
            // normalize field content so it integrates cleanly into the list item.
            if (!empty($originalBlockAttrs) && !$this->fieldContentHasBlockAttributes($fieldOps)) {
                $fieldOps = $this->stripLeadingNewlineFromOps($fieldOps);
                $fieldOps = $this->stripTrailingNewlineFromOps($fieldOps);
                // Collapse multi-line content to single line to preserve list structure
                if ($this->fieldContentIsMultiLine($fieldOps)) {
                    $fieldOps = $this->collapseNewlinesToSpace($fieldOps);
                }
            }

            $lastFieldOps = $fieldOps;

            $finalOps = $this->processBeforeText($beforeText, $fieldAnalysis, $finalOps);
            $finalOps = $this->processFieldContent($fieldOps, $fieldAnalysis, $beforeText, $finalOps);

            $text = $afterText;
        }

        if ($text !== '') {
            $leftover = ltrim($text, ' ');
            if ($leftover !== '') {
                // Check if the leftover is just a newline and we have original block attributes to preserve
                if ($leftover === "\n" && !empty($originalBlockAttrs) && !$this->fieldContentHasBlockAttributes($lastFieldOps)) {
                    $finalOps[] = ['insert' => "\n", 'attributes' => $originalBlockAttrs];
                } else {
                    $finalOps[] = ['insert' => $leftover];
                }
            }
        }

        return $finalOps;
    }

    private function stripTrailingNewlineFromOps(array $ops): array
    {
        if (empty($ops)) {
            return $ops;
        }

        $lastKey = array_key_last($ops);
        $lastOp = $ops[$lastKey];

        if (!isset($lastOp['insert']) || !is_string($lastOp['insert'])) {
            return $ops;
        }

        $text = rtrim($lastOp['insert'], "\n");
        if ($text === '') {
            // If the last op was only newlines, remove it entirely
            unset($ops[$lastKey]);
            return array_values($ops);
        }

        $ops[$lastKey]['insert'] = $text;
        return $ops;
    }

    private function stripLeadingNewlineFromOps(array $ops): array
    {
        if (empty($ops)) {
            return $ops;
        }

        $firstKey = array_key_first($ops);
        $firstOp = $ops[$firstKey];

        if (!isset($firstOp['insert']) || !is_string($firstOp['insert'])) {
            return $ops;
        }

        $text = ltrim($firstOp['insert'], "\n");
        if ($text === '') {
            // If the first op was only newlines, remove it entirely
            unset($ops[$firstKey]);
            return array_values($ops);
        }

        $ops[$firstKey]['insert'] = $text;
        return $ops;
    }

    private function extractBlockAttributes(?array $attrs): array
    {
        if (!$attrs) {
            return [];
        }
        $blockKeys = ['list', 'header', 'blockquote', 'indent', 'code-block'];
        return array_filter(
            array_intersect_key($attrs, array_flip($blockKeys)),
            fn($v) => $v !== null
        );
    }

    private function fieldContentHasBlockAttributes(array $fieldOps): bool
    {
        foreach ($fieldOps as $op) {
            if (isset($op['attributes']['list']) || isset($op['attributes']['header']) || isset($op['attributes']['code-block'])) {
                return true;
            }
        }
        return false;
    }

    private function fieldContentIsMultiLine(array $fieldOps): bool
    {
        foreach ($fieldOps as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                // Check for internal newlines (not just leading/trailing)
                $text = trim($op['insert'], "\n");
                if (str_contains($text, "\n")) {
                    return true;
                }
            }
        }
        return false;
    }

    private function collapseNewlinesToSpace(array $ops): array
    {
        foreach ($ops as $key => $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $ops[$key]['insert'] = preg_replace('/\n+/', ' ', $op['insert']);
            }
        }
        return $ops;
    }

    private function getFieldOperations(array &$fieldValues, int $fieldId): array
    {
        if (isset($fieldValues[$fieldId])) {
            $value = $fieldValues[$fieldId];
            unset($fieldValues[$fieldId]);
            return $this->buildFieldOperations($value, $fieldId);
        }

        if (!empty($fieldValues)) {
            $nextFieldId = array_key_first($fieldValues);
            $value = $fieldValues[$nextFieldId];
            unset($fieldValues[$nextFieldId]);
            return $this->buildFieldOperations($value, $nextFieldId);
        }

        return [];
    }

    private function buildFieldOperations(mixed $fieldValue, int $fieldId): array
    {
        $fieldType = $this->templateFieldTypes[$fieldId] ?? null;
        $field = $this->templateFields[$fieldId] ?? null;

        return $this->fieldValueBuilder->build($fieldValue, $fieldType, $field);
    }

    private function processBeforeText(string $beforeText, array $fieldAnalysis, array $finalOps): array
    {
        if ($beforeText === '') {
            return $finalOps;
        }

        if ($beforeText === "\n" && !empty($finalOps)) {
            $lastOp = $finalOps[array_key_last($finalOps)];
            if (
                isset($lastOp['insert'])
                && is_string($lastOp['insert'])
                && str_ends_with($lastOp['insert'], "\n")
            ) {
                return $finalOps;
            }
        }

        $beforeRaw = $beforeText;
        $beforeTrimmed = rtrim($beforeRaw);

        $injectListNewline = $fieldAnalysis['isListBlock']
            && !str_ends_with($beforeRaw, "\n")
            && !str_ends_with($beforeTrimmed, ':');

        if (trim($beforeRaw) === 'and') {
            $finalOps[] = ['insert' => "and\n"];
        } else {
            $finalOps[] = ['insert' => $injectListNewline ? $beforeTrimmed : $beforeRaw];
        }

        if ($injectListNewline) {
            $finalOps[] = ['insert' => "\n"];
        }

        return $finalOps;
    }

    private function processFieldContent(array $fieldOps, array $fieldAnalysis, string $beforeText, array $finalOps): array
    {
        if ($fieldAnalysis['isCodeBlock'] && !str_ends_with(trim($beforeText), "\n")) {
            $finalOps[] = ['insert' => "\n"];
        }

        if (!empty($fieldOps) && isset($fieldOps[0]['attributes']['header'])) {
            if (!empty($finalOps)) {
                $lastOp = $finalOps[array_key_last($finalOps)];
                $needsNewline = !isset($lastOp['insert'])
                    || !is_string($lastOp['insert'])
                    || !str_ends_with($lastOp['insert'], "\n");
                if ($needsNewline) {
                    $finalOps[] = ['insert' => "\n"];
                }
            }
        }

        foreach ($fieldOps as $fieldOp) {
            $finalOps[] = $fieldOp;
        }

        return $finalOps;
    }
}
