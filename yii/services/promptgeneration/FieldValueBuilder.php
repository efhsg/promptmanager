<?php

namespace app\services\promptgeneration;

use common\constants\FieldConstants;
use JsonException;

class FieldValueBuilder
{
    public function __construct(
        private readonly DeltaOpsHelper $deltaHelper = new DeltaOpsHelper()
    ) {}

    public function build(mixed $fieldValue, ?string $fieldType, ?object $field): array
    {
        $labelOps = $this->buildLabelOps($fieldType, $field);

        if ($fieldType === 'select-invert') {
            $ops = $this->buildSelectInvertOperations($fieldValue, $field);
            return $labelOps === [] ? $ops : array_merge($labelOps, $ops);
        }

        if (in_array($fieldType, FieldConstants::INLINE_FIELD_TYPES, true)) {
            $ops = $this->buildInlineValue($fieldValue);
            return $labelOps === [] ? $ops : array_merge($labelOps, $ops);
        }

        if (is_array($fieldValue)) {
            $ops = $this->buildFromArray($fieldValue, $fieldType);
            return $labelOps === [] ? $ops : array_merge($labelOps, $ops);
        }

        $ops = $this->buildFromJson($fieldValue);
        return $labelOps === [] ? $ops : array_merge($labelOps, $ops);
    }

    private function buildLabelOps(?string $fieldType, ?object $field): array
    {
        if ($field === null) {
            return [];
        }

        if (!isset($field->render_label) || !$field->render_label) {
            return [];
        }

        $labelTypes = ['text', 'select', 'multi-select', 'code', 'file', 'directory', 'string', 'number'];
        if (!in_array($fieldType, $labelTypes, true)) {
            return [];
        }

        $labelText = trim((string) ($field->label ?? ''));
        if ($labelText === '') {
            return [];
        }

        return [
            [
                'insert' => $labelText . "\n",
                'attributes' => ['header' => 2],
            ],
        ];
    }

    private function buildFromArray(array $fieldValue, ?string $fieldType): array
    {
        $ops = [];

        if (array_keys($fieldValue) === range(0, count($fieldValue) - 1)) {
            if ($fieldType === 'multi-select') {
                foreach ($fieldValue as $value) {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '') {
                        continue;
                    }
                    $ops[] = [
                        'insert' => $trimmed . "\n",
                        'attributes' => ['list' => 'bullet'],
                    ];
                }
                return $ops;
            }

            foreach ($fieldValue as $value) {
                $trimmed = rtrim($value);
                $suffix = str_ends_with($trimmed, '.') ? "\n" : ".\n";
                $ops[] = ['insert' => $trimmed . $suffix];
            }
        } else {
            foreach ($fieldValue as $key => $value) {
                $ops[] = ['insert' => "$key: $value\n"];
            }
        }

        return $ops;
    }

    private function buildFromJson(mixed $fieldValue): array
    {
        try {
            $decoded = json_decode($fieldValue, true, 512, JSON_THROW_ON_ERROR);
            return $decoded['ops'] ?? [];
        } catch (JsonException) {
            return [['insert' => (string) $fieldValue]];
        }
    }

    private function buildInlineValue(mixed $fieldValue): array
    {
        $value = trim((string) $fieldValue);
        if ($value === '') {
            return [];
        }
        return [['insert' => $value]];
    }

    private function buildSelectInvertOperations(mixed $selectedValue, ?object $field): array
    {
        if ($field === null) {
            return [['insert' => (string) $selectedValue]];
        }

        $selectedValueStr = (string) $selectedValue;
        $selectedLabel = null;
        $unselectedLabels = [];

        foreach (($field->fieldOptions ?? []) as $option) {
            $optionValuePlain = $this->deltaHelper->extractPlainTextFromDelta($option->value);
            $labelPlain = !empty($option->label)
                ? $this->deltaHelper->extractPlainTextFromDelta($option->label)
                : $optionValuePlain;

            if ($optionValuePlain === $selectedValueStr) {
                $selectedLabel = $labelPlain;
            } else {
                $unselectedLabels[] = $labelPlain;
            }
        }

        if ($selectedLabel === null) {
            $selectedLabel = $selectedValueStr;
        }

        $ops = [];

        if ($selectedLabel !== '') {
            $ops[] = ['insert' => str_replace(["\r\n", "\r", "\n"], '', $selectedLabel)];
        }

        $contentOps = $this->deltaHelper->extractOpsFromDelta($field->content ?? '');
        if ($selectedLabel !== '' && $contentOps !== []) {
            $firstInsert = &$contentOps[0];
            if (
                isset($firstInsert['insert'])
                && is_string($firstInsert['insert'])
                && $firstInsert['insert'] !== ''
                && !preg_match('/^\s/', $firstInsert['insert'])
            ) {
                $firstInsert['insert'] = ' ' . $firstInsert['insert'];
            }
        }

        foreach ($contentOps as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $op['insert'] = str_replace(["\r\n", "\r", "\n"], '', $op['insert']);
            }
            $ops[] = $op;
        }

        if (!empty($unselectedLabels)) {
            $cleanedLabels = array_map(
                fn(string $label): string => str_replace(["\r\n", "\r", "\n"], '', $label),
                $unselectedLabels
            );
            $ops[] = ['insert' => implode(',', $cleanedLabels)];
        }

        return $ops;
    }
}
