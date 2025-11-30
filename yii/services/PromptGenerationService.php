<?php

namespace app\services;

use JsonException;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;

/**
 * Service for generating prompts by replacing placeholders with field values
 */
class PromptGenerationService
{
    /**
     * @var string
     */
    private const PLACEHOLDER_PATTERN = '/\b(?:GEN|PRJ):\{\{(\d+)}}/';

    private array $templateFieldTypes = [];
    private array $templateFields = [];

    public function __construct(
        private readonly PromptTemplateService $templateService,
    ) {
    }

    /**
     *
     * @throws NotFoundHttpException If the template is not found.
     */
    public function generateFinalPrompt(
        int $templateId,
        array $selectedContexts,
        array $fieldValues,
        int $userId
    ): string {
        $templateDelta = $this->getTemplateDelta($templateId, $userId);
        $fieldValuesCopy = $fieldValues;

        // If we have contexts, prepend them to the template delta
        if (!empty($selectedContexts)) {
            $contextOps = $this->createContextOps($selectedContexts);
            $templateDelta = array_merge($contextOps, $templateDelta);
        }

        // Process the combined template delta
        $finalOps = $this->processTemplateDelta($templateDelta, $fieldValuesCopy);
        $finalOps = $this->removeConsecutiveNewlines($finalOps);

        return json_encode(['ops' => $finalOps], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Create operations for contexts
     *
     * @param array $contexts Array of context JSON strings
     * @return array Array of operations for all contexts
     */
    private function createContextOps(array $contexts): array
    {
        $ops = [];

        foreach ($contexts as $context) {
            try {
                $contextData = json_decode($context, true, 512, JSON_THROW_ON_ERROR);
                if (isset($contextData['ops']) && is_array($contextData['ops'])) {
                    foreach ($contextData['ops'] as $op) {
                        $ops[] = $op;
                    }
                }
            } catch (JsonException) {
                // Skip invalid JSON contexts
                continue;
            }
        }

        return $ops;
    }


    /**
     *
     * @param int $templateId Template ID to retrieve
     * @param int $userId User ID for access check
     * @return array Template delta operations
     * @throws NotFoundHttpException If template not found or access denied
     */
    private function getTemplateDelta(int $templateId, int $userId): array
    {
        $templateModel = $this->templateService->getTemplateById($templateId, $userId)
            ?? throw new NotFoundHttpException('Template not found or access denied.');

        $this->templateFieldTypes = ArrayHelper::map($templateModel->fields ?? [], 'id', 'type');

        // Store full field objects for fields that need additional data (like select-invert)
        foreach (($templateModel->fields ?? []) as $field) {
            $this->templateFields[$field->id] = $field;
        }

        try {
            $templateDelta = json_decode($templateModel->template_body, true, 512, JSON_THROW_ON_ERROR);
            if (!isset($templateDelta['ops'])) {
                return [];
            }
            return $templateDelta['ops'];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Process template delta operations to replace placeholders with field values
     *
     * @param array $templateDelta Template delta operations
     * @param array &$fieldValues Field values to replace placeholders
     * @return array Processed operations
     */
    private function processTemplateDelta(array $templateDelta, array &$fieldValues): array
    {
        $finalOps = [];

        // Iterate over each operation in the template
        foreach ($templateDelta as $op) {
            // Non-text inserts are passed through
            if (!isset($op['insert']) || !is_string($op['insert'])) {
                $finalOps[] = $op;
                continue;
            }

            $text = $op['insert'];

            // If the text contains placeholders, process them
            if (preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches, PREG_SET_ORDER)) {
                $finalOps = $this->processPlaceholders($text, $matches, $fieldValues, $finalOps);
            } else {
                // No placeholders: pass through
                $finalOps[] = $op;
            }
        }

        return $finalOps;
    }

    /**
     * Process placeholders in text and replace with field values
     *
     * @param string $text Text containing placeholders
     * @param array $matches Regex matches of placeholders
     * @param array &$fieldValues Field values to replace placeholders - passed by reference
     * @param array $finalOps Current operations array
     * @return array Updated operations array
     */
    private function processPlaceholders(string $text, array $matches, array &$fieldValues, array $finalOps): array
    {
        foreach ($matches as $match) {
            $placeholder = $match[0];
            $fieldId = (int)$match[1];

            // Split the text around the placeholder
            [$beforeText, $afterText] = array_pad(explode($placeholder, $text, 2), 2, '');

            // Get field operations - using reference to consume used values
            $fieldOps = $this->getFieldOperations($fieldValues, $fieldId);

            // Analyze field content
            $fieldAnalysis = $this->analyzeFieldContent($fieldOps);

            // Process text before placeholder
            $finalOps = $this->processBeforeText($beforeText, $fieldAnalysis, $finalOps);

            // Process field content
            $finalOps = $this->processFieldContent($fieldOps, $fieldAnalysis, $beforeText, $finalOps);

            // Continue processing the remainder of the text
            $text = $afterText;
        }

        // Any leftover text
        if ($text !== '') {
            $leftover = ltrim($text, ' ');
            if ($leftover !== '') {
                $finalOps[] = ['insert' => $leftover];
            }
        }

        return $finalOps;
    }

    /**
     * Build field operations from a field value
     *
     * @param mixed $fieldValue The field value (JSON string or array)
     * @param int $fieldId Field ID being processed
     * @return array Ops in Quill delta format
     */
    private function buildFieldOperations(mixed $fieldValue, int $fieldId): array
    {
        $fieldType = $this->templateFieldTypes[$fieldId] ?? null;

        // Handle select-invert field type
        if ($fieldType === 'select-invert') {
            return $this->buildSelectInvertOperations($fieldValue, $fieldId);
        }

        // Handle array field values (non-JSON strings)
        if (is_array($fieldValue)) {
            $ops = [];
            // If it's a sequential array, present each value as individual items
            if (array_keys($fieldValue) === range(0, count($fieldValue) - 1)) {
                if ($fieldType === 'multi-select') {
                    foreach ($fieldValue as $value) {
                        $trimmed = trim((string)$value);
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
                // For associative arrays, format as key-value pairs
                foreach ($fieldValue as $key => $value) {
                    $ops[] = ['insert' => "$key: $value\n"];
                }
            }

            return $ops;
        }

        // Handle JSON string field values
        try {
            $decoded = json_decode($fieldValue, true, 512, JSON_THROW_ON_ERROR);
            return $decoded['ops'] ?? [];
        } catch (JsonException) {
            // If JSON decoding fails, treat as plain text
            return [['insert' => (string)$fieldValue]];
        }
    }

    /**
     * Build operations for select-invert field type
     * Format: selected label + field content + unselected labels (comma-separated)
     */
    private function buildSelectInvertOperations(mixed $selectedValue, int $fieldId): array
    {
        $field = $this->templateFields[$fieldId] ?? null;
        if ($field === null) {
            return [['insert' => (string)$selectedValue]];
        }

        $selectedValueStr = (string)$selectedValue;

        // DEBUG: Log what we received
        error_log("select-invert field {$fieldId}: received value = '{$selectedValueStr}'");

        $selectedLabel = null;
        $unselectedLabels = [];

        // Build mapping of values to labels and find selected/unselected
        foreach (($field->fieldOptions ?? []) as $option) {
            $label = !empty($option->label) ? $option->label : $option->value;

            error_log("  option: value='{$option->value}', label='{$label}'");

            if ($option->value === $selectedValueStr) {
                $selectedLabel = $label;
                error_log("  ^ MATCHED");
            } else {
                $unselectedLabels[] = $label;
            }
        }

        // If no match found, use the selected value as-is
        if ($selectedLabel === null) {
            $selectedLabel = $selectedValueStr;
            error_log("NO MATCH FOUND - using selected value as-is: '{$selectedValueStr}'");
        }

        error_log("Final: selectedLabel='{$selectedLabel}', unselected=" . implode(',', $unselectedLabels));

        // Build the output as Quill Delta operations
        $ops = [];

        // Add selected label
        if ($selectedLabel !== '') {
            $ops[] = ['insert' => $selectedLabel];
        }

        // Add field content operations (decode from Quill Delta JSON)
        $contentOps = $this->extractOpsFromDelta($field->content ?? '');
        foreach ($contentOps as $op) {
            $ops[] = $op;
        }

        // Add unselected labels (comma-separated)
        if (!empty($unselectedLabels)) {
            $ops[] = ['insert' => implode(',', $unselectedLabels)];
        }

        return $ops;
    }

    /**
     * Extract Quill Delta operations from JSON format
     */
    private function extractOpsFromDelta(string $deltaJson): array
    {
        if ($deltaJson === '') {
            return [];
        }

        try {
            $decoded = json_decode($deltaJson, true, 512, JSON_THROW_ON_ERROR);
            if (!isset($decoded['ops']) || !is_array($decoded['ops'])) {
                // If not valid Quill Delta, treat as plain text
                return [['insert' => $deltaJson]];
            }

            return $decoded['ops'];
        } catch (JsonException) {
            // If it's not valid JSON, treat as plain text
            return [['insert' => $deltaJson]];
        }
    }

    /**
     * Extract plain text from Quill Delta JSON format
     */
    private function extractPlainTextFromDelta(string $deltaJson): string
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

            return $text;
        } catch (JsonException) {
            // If it's not valid JSON, return as-is
            return $deltaJson;
        }
    }

    /**
     * Get field operations from field values with fallback
     *
     * @param array &$fieldValues All field values - passed by reference to consume used values
     * @param int $fieldId Field ID to retrieve
     * @return array Field operations or empty array if not found
     */
    private function getFieldOperations(array &$fieldValues, int $fieldId): array
    {
        // Direct match - use and consume the value
        if (isset($fieldValues[$fieldId])) {
            $value = $fieldValues[$fieldId];
            // Consume the value by unsetting it
            unset($fieldValues[$fieldId]);
            return $this->buildFieldOperations($value, $fieldId);
        }

        // Field not found - find the next available value if any
        if (!empty($fieldValues)) {
            // Get the first available field value
            $nextFieldId = array_key_first($fieldValues);
            $value = $fieldValues[$nextFieldId];
            // Consume the value
            unset($fieldValues[$nextFieldId]);
            return $this->buildFieldOperations($value, $nextFieldId);
        }

        // No values available
        return [];
    }

    /**
     * Analyze field content for special formatting
     *
     * @param array $fieldOps Field operations to analyze
     * @return array Analysis results
     */
    private function analyzeFieldContent(array $fieldOps): array
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
            'isCodeBlock' => $isCodeBlock
        ];
    }

    /**
     * Process text before a placeholder
     *
     * @param string $beforeText Text before placeholder
     * @param array $fieldAnalysis Field content analysis
     * @param array $finalOps Current operations array
     * @return array Updated operations array
     */
    private function processBeforeText(string $beforeText, array $fieldAnalysis, array $finalOps): array
    {
        if ($beforeText === '') {
            return $finalOps;
        }

        $beforeRaw = $beforeText;
        $beforeTrimmed = rtrim($beforeRaw);

        // Determine if list needs newline injection
        $injectListNewline = $fieldAnalysis['isListBlock']
            && !str_ends_with($beforeRaw, "\n")
            && !str_ends_with($beforeTrimmed, ':');

        // Handle special case for 'and'
        if (trim($beforeRaw) === 'and') {
            $finalOps[] = ['insert' => "and\n"];
        } else {
            $finalOps[] = ['insert' => $injectListNewline ? $beforeTrimmed : $beforeRaw];
        }

        // Add newline before list if needed
        if ($injectListNewline) {
            $finalOps[] = ['insert' => "\n"];
        }

        return $finalOps;
    }

    /**
     * Process field content and add to operations
     *
     * @param array $fieldOps Field operations
     * @param array $fieldAnalysis Field content analysis
     * @param string $beforeText Text before the field
     * @param array $finalOps Current operations array
     * @return array Updated operations array
     */
    private function processFieldContent(array $fieldOps, array $fieldAnalysis, string $beforeText, array $finalOps): array
    {
        if ($fieldAnalysis['isCodeBlock'] && !str_ends_with(trim($beforeText), "\n")) {
            $finalOps[] = ['insert' => "\n"];
        }


        // Add all field operations
        foreach ($fieldOps as $fieldOp) {
            $finalOps[] = $fieldOp;
        }

        return $finalOps;
    }

    /**
     * Remove consecutive newlines from operations
     *
     * @param array $ops Operations to process
     * @return array Processed operations
     */
    private function removeConsecutiveNewlines(array $ops): array
    {
        $result = [];
        foreach ($ops as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $op['insert'] = preg_replace('/\n{2,}/', "\n", $op['insert']);

                // Only add non-empty inserts
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
