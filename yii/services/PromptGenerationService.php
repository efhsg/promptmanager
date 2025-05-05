<?php

namespace app\services;

use app\models\Field;
use League\HTMLToMarkdown\HtmlConverter;
use nadar\quill\Lexer;
use yii\base\InvalidArgumentException;
use yii\helpers\Json;
use yii\web\NotFoundHttpException;

class PromptGenerationService
{

    private const PLACEHOLDER_PATTERN = '/\b(?:GEN|PRJ):\{\{(\d+)}}/';

    private PromptTemplateService $promptTemplateService;
    private ContextService $contextService;
    private PromptTransformationService $promptTransformationService;

    public function __construct(
        PromptTemplateService       $promptTemplateService,
        ContextService              $contextService,
        PromptTransformationService $promptTransformationService
    )
    {
        $this->promptTemplateService = $promptTemplateService;
        $this->contextService = $contextService;
        $this->promptTransformationService = $promptTransformationService;
    }

    /**
     * @throws NotFoundHttpException
     */
    public function generateFinalPrompt(int $templateId, array $selectedContextIds, array $fieldValues, int $userId): array
    {
        if (!$templateId) {
            throw new NotFoundHttpException("Template ID not provided.");
        }

        $template = $this->promptTemplateService->getTemplateById($templateId, $userId);
        if (!$template) {
            throw new NotFoundHttpException("Template not found or access denied.");
        }

        $delta = json_decode($template->template_body, true);
        if (!$delta || !isset($delta['ops'])) {
            throw new InvalidArgumentException("Template is not in valid Delta format.");
        }

        $fieldIds = array_keys($fieldValues);
        $fields = Field::find()->where(['id' => $fieldIds])->indexBy('id')->all();

        $processedDelta = $this->processFieldPlaceholders($delta, $fieldValues, $fields);
        $finalDelta = $this->prependContexts($processedDelta, $selectedContextIds, $userId);

        return $this->convertToFormats($finalDelta);
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
                        return $this->promptTransformationService->wrapCode($val);
                    }
                    return $this->promptTransformationService->detectCode($val)
                        ? $this->promptTransformationService->wrapCode($val)
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
                $contextDeltaOps[] = ['insert' => "\n\n"];
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
        $deltaJson = json_encode($delta);

        $lexer = new Lexer($deltaJson);
        $html = $lexer->render();

        $converter = new HtmlConverter();
        $markdown = $converter->convert($html);

        return [
            'displayPrompt' => $deltaJson,
            'displayHtml' => $html,
            'displayText' => $markdown
        ];
    }
}