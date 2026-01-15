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
    ) {
    }

    public function setFieldMappings(array $fieldTypes, array $fields): void
    {
        $this->templateFieldTypes = $fieldTypes;
        $this->templateFields = $fields;
    }

    public function process(array $templateDelta, array &$fieldValues): array
    {
        $finalOps = [];

        foreach ($templateDelta as $op) {
            if (!isset($op['insert']) || !is_string($op['insert'])) {
                $finalOps[] = $op;
                continue;
            }

            $text = $op['insert'];

            if (preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches, PREG_SET_ORDER)) {
                $finalOps = $this->processPlaceholders($text, $matches, $fieldValues, $finalOps);
            } else {
                $finalOps[] = $op;
            }
        }

        return $finalOps;
    }

    private function processPlaceholders(string $text, array $matches, array &$fieldValues, array $finalOps): array
    {
        foreach ($matches as $match) {
            $placeholder = $match[0];
            $fieldId = (int) $match[1];

            [$beforeText, $afterText] = array_pad(explode($placeholder, $text, 2), 2, '');

            $fieldOps = $this->getFieldOperations($fieldValues, $fieldId);
            $fieldAnalysis = $this->deltaHelper->analyzeFieldContent($fieldOps);

            $finalOps = $this->processBeforeText($beforeText, $fieldAnalysis, $finalOps);
            $finalOps = $this->processFieldContent($fieldOps, $fieldAnalysis, $beforeText, $finalOps);

            $text = $afterText;
        }

        if ($text !== '') {
            $leftover = ltrim($text, ' ');
            if ($leftover !== '') {
                $finalOps[] = ['insert' => $leftover];
            }
        }

        return $finalOps;
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
