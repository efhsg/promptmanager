<?php /** @noinspection PhpUnused */

namespace app\services;

use app\models\PromptTemplate;
use app\models\TemplateField;
use Throwable;
use Yii;
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
        $convertedTemplate = $this->convertPlaceholdersToIds($originalTemplate, $fieldsMapping);
        $postData['PromptTemplate']['template_body'] = $convertedTemplate;
        $transaction = Yii::$app->db->beginTransaction();

        try {
            if (!$model->load($postData) || !$model->save()) {
                $transaction->rollBack();
                return false;
            }

            $this->updateTemplateFields($model, $convertedTemplate);
            $transaction->commit();
            return true;
        } catch (Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::error($e->getMessage(), 'database');
            throw $e;
        }
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

        if (!preg_match_all('/(GEN|PRJ):\{\{(\d+)}}/', $content, $matches)) {
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
        $delta = json_decode($template, true);
        if (!$delta || !isset($delta['ops'])) {
            // Fallback for non-delta format or invalid JSON
            return $template;
        }

        $mapping = $this->normalizeFieldsMapping($fieldsMapping);

        foreach ($delta['ops'] as &$op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $op['insert'] = preg_replace_callback('/(GEN|PRJ):\{\{(.+?)}}/', function ($matches) use ($mapping) {
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
            if (preg_match('/^(GEN|PRJ):\{\{(.+)}}$/', $placeholder, $parts)) {
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
            if (preg_match('/^(GEN|PRJ):\{\{(.+)}}$/', $placeholder, $matches)) {
                $prefix = $matches[1];
                $fieldName = $matches[2];
                $fieldId = $data['id'];
                $normalizedMapping[$prefix][$fieldId] = $fieldName;
            }
        }

        foreach ($delta['ops'] as &$op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $op['insert'] = preg_replace_callback('/(GEN|PRJ):\{\{(\d+)}}/', function ($matches) use ($normalizedMapping) {
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
}