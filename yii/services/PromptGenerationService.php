<?php

namespace app\services;

use app\services\promptgeneration\DeltaOpsHelper;
use app\services\promptgeneration\PlaceholderProcessor;
use JsonException;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;

readonly class PromptGenerationService
{
    public function __construct(
        private PromptTemplateService $templateService,
        private PlaceholderProcessor $placeholderProcessor = new PlaceholderProcessor(),
        private DeltaOpsHelper $deltaHelper = new DeltaOpsHelper()
    ) {}

    /**
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

        if (!empty($selectedContexts)) {
            $contextOps = $this->createContextOps($selectedContexts);
            $templateDelta = array_merge($contextOps, $templateDelta);
        }

        $finalOps = $this->placeholderProcessor->process($templateDelta, $fieldValuesCopy);
        $finalOps = $this->deltaHelper->removeConsecutiveNewlines($finalOps);

        return json_encode(['ops' => $finalOps], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @throws NotFoundHttpException If template not found or access denied
     */
    private function getTemplateDelta(int $templateId, int $userId): array
    {
        $templateModel = $this->templateService->getTemplateById($templateId, $userId)
            ?? throw new NotFoundHttpException('Template not found or access denied.');

        $fieldTypes = ArrayHelper::map($templateModel->fields ?? [], 'id', 'type');
        $fields = [];
        foreach (($templateModel->fields ?? []) as $field) {
            $fields[$field->id] = $field;
        }

        $this->placeholderProcessor->setFieldMappings($fieldTypes, $fields);

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
                continue;
            }
        }

        return $ops;
    }
}
