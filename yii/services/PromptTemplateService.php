<?php /** @noinspection PhpUnused */

namespace app\services;

use app\models\PromptTemplate;
use app\models\TemplateField;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

class PromptTemplateService
{

    /**
     * @throws Exception
     */
    public function saveTemplateWithFields(PromptTemplate $model, array $postData, array $fieldsMapping): bool
    {
        $originalTemplate = $postData['PromptTemplate']['template_body'] ?? '{"ops":[{"insert":"\n"}]}';

        $invalidPlaceholders = $this->validateTemplatePlaceholders($originalTemplate, $fieldsMapping);
        if (!empty($invalidPlaceholders)) {
            $errorMessage = 'Invalid field placeholders found: ' . implode(', ', $invalidPlaceholders);
            $model->addError('template_body', $errorMessage);
            $model->load($postData);
            return false;
        }

        $convertedTemplate = $this->convertPlaceholdersToIds($originalTemplate, $fieldsMapping);
        $postData['PromptTemplate']['template_body'] = $convertedTemplate;
        if (!$model->load($postData) || !$model->save()) {
            return false;
        }
        $this->updateTemplateFields($model, $convertedTemplate);
        return true;
    }

    /**
     * @throws Exception
     */
    private function updateTemplateFields(PromptTemplate $model, string $template): void
    {
        TemplateField::deleteAll(['template_id' => $model->id]);

        $delta = json_decode($template, true);
        if (!$delta || !isset($delta['ops'])) {
            return;
        }

        $content = '';
        foreach ($delta['ops'] as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $content .= $op['insert'];
            }
        }

        if (!preg_match_all('/(GEN|PRJ|EXT):\{\{(\d+)}}/', $content, $matches)) {
            return;
        }

        foreach ($matches[2] as $fieldId) {
            $fieldRecord = new TemplateField();
            $fieldRecord->template_id = $model->id;
            $fieldRecord->field_id = $fieldId;
            if (!$fieldRecord->save(false)) {
                throw new Exception("Failed to save template field for field ID $fieldId.");
            }
        }
    }

    /**
     * Returns an associative array of templates for the given user,
     * mapping template id to template name.
     *
     * @param int $userId
     * @param int|null $projectId
     * @return array
     */
    public function getTemplatesByUser(int $userId, ?int $projectId = null): array
    {
        $query = PromptTemplate::find()
            ->joinWith('project')
            ->where(['project.user_id' => $userId]);
        if ($projectId !== null) {
            $query->andWhere(['project.id' => $projectId]);
        }
        $templates = $query->orderBy(['name' => SORT_ASC])->all();
        return ArrayHelper::map($templates, 'id', 'name');
    }

    /**
     * Returns an associative array of template bodies for the given user,
     * mapping template id to template body content.
     *
     * @param int $userId
     * @param int|null $projectId
     * @return array
     */
    public function getTemplatesDescriptionByUser(int $userId, ?int $projectId = null): array
    {
        $query = PromptTemplate::find()
            ->joinWith('project')
            ->where(['project.user_id' => $userId]);
        if ($projectId !== null) {
            $query->andWhere(['project.id' => $projectId]);
        }
        $templates = $query->all();
        return ArrayHelper::map($templates, 'id', 'template_body');
    }

    public function getTemplatesContentByUser(int $userId): array
    {
        $templates = PromptTemplate::find()
            ->joinWith('project')
            ->where(['project.user_id' => $userId])
            ->all();

        return ArrayHelper::map($templates, 'id', 'template_body');
    }

    /**
     * Returns a PromptTemplate instance by its id if it belongs to the given user.
     *
     * @param int $templateId
     * @param int $userId
     * @return PromptTemplate|null
     */
    public function getTemplateById(int $templateId, int $userId): ?PromptTemplate
    {
        $template = PromptTemplate::find()
            ->joinWith('project')
            ->with(['fields.fieldOptions'])
            ->where([
                'prompt_template.id' => $templateId,
                'project.user_id' => $userId,
            ])
            ->one();

        return $template instanceof PromptTemplate ? $template : null;
    }


    public function convertPlaceholdersToIds(string $template, array $fieldsMapping): string
    {
        $delta = json_decode($template, true);
        if (!$delta || !isset($delta['ops'])) {
            // Fallback for non-delta format or invalid JSON
            return $template;
        }

        $mapping = $this->normalizeFieldsMapping($fieldsMapping);

        foreach ($delta['ops'] as &$op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $op['insert'] = preg_replace_callback('/(GEN|PRJ|EXT):\{\{(.+?)}}/', function ($matches) use ($mapping) {
                    $prefix = $matches[1];
                    $placeholderName = $matches[2];
                    if (isset($mapping[$prefix][$placeholderName])) {
                        return "$prefix:{{{$mapping[$prefix][$placeholderName]['id']}}}";
                    }
                    return $matches[0];
                }, $op['insert']);
            }
        }

        return json_encode($delta);
    }

    private function normalizeFieldsMapping(array $fieldsMapping): array
    {
        $normalized = [];
        foreach ($fieldsMapping as $placeholder => $data) {
            if (preg_match('/^(GEN|PRJ|EXT):\{\{(.+)}}$/', $placeholder, $parts)) {
                $normalized[$parts[1]][$parts[2]] = $data;
            }
        }
        return $normalized;
    }

    function convertPlaceholdersToLabels(string $template, array $fieldsMapping): string
    {
        $delta = json_decode($template, true);
        if (!$delta || !isset($delta['ops'])) {
            // Fallback for non-delta format or invalid JSON
            return $template;
        }

        $normalizedMapping = [];
        foreach ($fieldsMapping as $placeholder => $data) {
            if (preg_match('/^(GEN|PRJ|EXT):\{\{(.+)}}$/', $placeholder, $matches)) {
                $prefix = $matches[1];
                $fieldName = $matches[2];
                $fieldId = $data['id'];
                $normalizedMapping[$prefix][$fieldId] = $fieldName;
            }
        }

        foreach ($delta['ops'] as &$op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $op['insert'] = preg_replace_callback('/(GEN|PRJ|EXT):\{\{(\d+)}}/', function ($matches) use ($normalizedMapping) {
                    $prefix = $matches[1];
                    $fieldId = (int)$matches[2];
                    if (isset($normalizedMapping[$prefix][$fieldId])) {
                        $fieldName = $normalizedMapping[$prefix][$fieldId];
                        return "$prefix:{{{$fieldName}}}";
                    }
                    return $matches[0];
                }, $op['insert']);
            }
        }

        return json_encode($delta);
    }

    public function validateTemplatePlaceholders(string $template, array $fieldsMapping): array
    {
        $delta = json_decode($template, true);
        if (!$delta || !isset($delta['ops'])) {
            return [];
        }

        $content = '';
        foreach ($delta['ops'] as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $content .= $op['insert'];
            }
        }

        if (!preg_match_all('/(GEN|PRJ|EXT):\{\{(.+?)}}/', $content, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $invalidPlaceholders = [];
        foreach ($matches as $match) {
            $fullPlaceholder = $match[0];
            if (!isset($fieldsMapping[$fullPlaceholder])) {
                $invalidPlaceholders[] = $fullPlaceholder;
            }
        }

        return array_unique($invalidPlaceholders);
    }
}
