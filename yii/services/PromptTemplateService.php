<?php

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
        $originalTemplate = $postData['PromptTemplate']['template_body'] ?? '';
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
        if (!preg_match_all('/(GEN|PRJ):\{\{(\d+)}}/', $template, $matches)) {
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
     * Returns an associative array of template descriptions for the given user,
     * mapping template id to template description.
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
        return ArrayHelper::map($templates, 'id', 'description');
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
            ->where([
                'prompt_template.id' => $templateId,
                'project.user_id' => $userId,
            ])
            ->one();

        return $template instanceof PromptTemplate ? $template : null;
    }


    public function convertPlaceholdersToIds(string $template, array $fieldsMapping): string
    {
        $mapping = $this->normalizeFieldsMapping($fieldsMapping);
        return preg_replace_callback('/(GEN|PRJ):\{\{(.+?)}}/', function ($matches) use ($mapping) {
            $prefix = $matches[1];
            $placeholderName = $matches[2];
            if (isset($mapping[$prefix][$placeholderName])) {
                return "$prefix:{{{$mapping[$prefix][$placeholderName]['id']}}}";
            }
            return $matches[0];
        }, $template);
    }

    private function normalizeFieldsMapping(array $fieldsMapping): array
    {
        $normalized = [];
        foreach ($fieldsMapping as $placeholder => $data) {
            if (preg_match('/^(GEN|PRJ):\{\{(.+)}}$/', $placeholder, $parts)) {
                $normalized[$parts[1]][$parts[2]] = $data;
            }
        }
        return $normalized;
    }


    /**
     * Converts internal ID placeholders in a template back to descriptive placeholders.
     * For example, "GEN:{{3}}" becomes "GEN:{{codeType}}" if field ID 3 corresponds to "codeType".
     *
     * @param string $template The stored template content.
     * @param array  $fieldsMapping An associative array where keys are placeholders (e.g. "GEN:{{codeType}}")
     *                              and values include at least ['id' => <field_id>].
     * @return string The transformed template for display.
     */
    function convertPlaceholdersToLabels(string $template, array $fieldsMapping): string {
        $normalizedMapping = [];
        foreach ($fieldsMapping as $placeholder => $data) {
            if (preg_match('/^(GEN|PRJ):\{\{(.+)}}$/', $placeholder, $matches)) {
                $prefix = $matches[1];
                $fieldName = $matches[2];
                $fieldId = $data['id'];
                $normalizedMapping[$prefix][$fieldId] = $fieldName;
            }
        }

        // Replace ID-based placeholders with descriptive ones.
        return preg_replace_callback('/(GEN|PRJ):\{\{(\d+)}}/', function ($matches) use ($normalizedMapping) {
            $prefix = $matches[1];
            $fieldId = (int)$matches[2];
            if (isset($normalizedMapping[$prefix][$fieldId])) {
                $fieldName = $normalizedMapping[$prefix][$fieldId];
                return "$prefix:{{{$fieldName}}}";
            }
            // If not found, return the original match.
            return $matches[0];
        }, $template);
    }


}
