<?php

namespace app\services;

use app\models\Field;
use app\exceptions\InvalidDeltaFormatException;
use League\HTMLToMarkdown\HtmlConverter;
use nadar\quill\Lexer;
use yii\web\NotFoundHttpException;
use yii\helpers\Json;

class PromptGenerationService
{
    private const PLACEHOLDER_PATTERN = '/\b(?:GEN|PRJ):\{\{(\d+)}}/';
    private const FORMAT_HTML = 'displayHtml';
    private const FORMAT_MARKDOWN = 'displayText';
    private const FORMAT_DELTA = 'displayPrompt';
    private const CONTEXT_SEPARATOR = "\n\n";

    public function __construct(
        private readonly PromptTemplateService $templateService,
        private readonly ContextService $contextService,
        private readonly PromptTransformationService $transformationService
    ) {}

    /**
     * @throws NotFoundHttpException
     * @throws InvalidDeltaFormatException
     */
    public function generateFinalPrompt(int $templateId, array $selectedContextIds, array $fieldValues, int $userId): array
    {
        $template = $this->templateService->getTemplateById($templateId, $userId)
            ?? throw new NotFoundHttpException('Template not found or access denied.');

        $delta = Json::decode($template->template_body);

        if (!isset($delta['ops'])) {
            throw new InvalidDeltaFormatException('Template is not in valid Delta format.');
        }

        // Early-exit optimization: if no placeholders and no contexts, skip processing
        $hasPlaceholders = $this->hasPlaceholders($delta['ops']);
        if (!$hasPlaceholders && empty($selectedContextIds)) {
            return $this->convertToFormats($delta);
        }

        $fields = Field::find()
            ->where(['id' => array_keys($fieldValues)])
            ->indexBy('id')
            ->all();

        $processedDelta = $hasPlaceholders
            ? $this->processFieldPlaceholders($delta, $fieldValues, $fields)
            : $delta;

        $finalDelta = !empty($selectedContextIds)
            ? $this->prependContexts($processedDelta, $selectedContextIds, $userId)
            : $processedDelta;

        return $this->convertToFormats($finalDelta);
    }

    /**
     * Check if delta ops contain any placeholders that need replacement
     */
    private function hasPlaceholders(array $ops): bool
    {
        foreach ($ops as $op) {
            if (isset($op['insert']) && is_string($op['insert']) &&
                preg_match(self::PLACEHOLDER_PATTERN, $op['insert'])) {
                return true;
            }
        }
        return false;
    }

    private function processFieldPlaceholders(array $delta, array $fieldValues, array $fields): array
    {
        foreach ($delta['ops'] as &$op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $op['insert'] = preg_replace_callback(self::PLACEHOLDER_PATTERN, function ($matches) use ($fieldValues, $fields): string {
                    $fieldKey = $matches[1];
                    if (empty($fieldValues[$fieldKey])) {
                        return '';
                    }
                    $value = $fieldValues[$fieldKey];
                    $val = is_array($value) ? implode(', ', $value) : $value;

                    if (isset($fields[$fieldKey]) && $fields[$fieldKey]->type === 'code') {
                        return $this->transformationService->wrapCode($val);
                    }

                    return $this->transformationService->detectCode($val)
                        ? $this->transformationService->wrapCode($val)
                        : $val;
                }, $op['insert']);
            }
        }

        return $delta;
    }

    private function prependContexts(array $delta, array $selectedContextIds, int $userId): array
    {
        if (empty($selectedContextIds)) {
            return $delta;
        }

        $allContexts = $this->contextService->fetchContextsContent($userId);
        $contextDeltaOps = [];

        foreach ($selectedContextIds as $contextId) {
            if (empty($allContexts[$contextId])) {
                continue;
            }

            $contextDelta = Json::decode($allContexts[$contextId]);

            if (!empty($contextDelta['ops'])) {
                $contextDeltaOps = array_merge($contextDeltaOps, $contextDelta['ops']);
                $contextDeltaOps[] = ['insert' => self::CONTEXT_SEPARATOR];
            }
        }

        if (!empty($contextDeltaOps)) {
            return [
                'ops' => array_merge($contextDeltaOps, $delta['ops'])
            ];
        }

        return $delta;
    }

    private function convertToFormats(array $delta): array
    {
        $deltaJson = Json::encode($delta);

        $lexer = new Lexer($deltaJson);
        $html = $lexer->render();

        $converter = new HtmlConverter();
        $markdown = $converter->convert($html);

        return [
            self::FORMAT_DELTA => $deltaJson,
            self::FORMAT_HTML => $html,
            self::FORMAT_MARKDOWN => $markdown
        ];
    }
}