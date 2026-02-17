<?php

namespace app\services;

use app\models\Field;
use app\models\FieldOption;
use app\models\ProjectLinkedProject;
use common\enums\LogCategory;
use Exception;
use Throwable;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class FieldService
{
    public function fetchFieldsMap(int $userId, ?int $projectId): array
    {
        $fields = $this->getFields($userId, $projectId);
        return $this->mapFields($fields);
    }

    public function fetchExternalFieldsMap(int $userId, ?int $projectId): array
    {
        if ($projectId === null) {
            return [];
        }

        $linkedProjectIds = ProjectLinkedProject::find()
            ->linkedProjectIdsFor($projectId, $userId)
            ->column();

        if (empty($linkedProjectIds)) {
            return [];
        }

        $fields = Field::find()
            ->sharedFromProjects($userId, $linkedProjectIds)
            ->all();

        $mappedFields = [];

        foreach ($fields as $field) {
            $placeholder = $this->createExternalPlaceholder($field);
            $mappedFields[$placeholder] = $this->createExternalFieldData($field);
        }

        return $mappedFields;
    }

    private function getFields(int $userId, ?int $projectId): array
    {
        $query = Field::find()->where(['user_id' => $userId]);

        if ($projectId === null) {
            $query->andWhere(['project_id' => null]);
        } else {
            $query->andWhere(['project_id' => $projectId]);
        }

        return $query->all();
    }

    private function mapFields(array $fields): array
    {
        $mappedFields = [];

        foreach ($fields as $field) {
            $mappedFields[$this->createPlaceholder($field)] = $this->createFieldData($field);
        }

        return $mappedFields;
    }

    private function createPlaceholder(Field $field): string
    {
        $prefixType = $field->project_id ? 'PRJ:' : 'GEN:';
        return sprintf('%s{{%s}}', $prefixType, $field->name);
    }

    private function createExternalPlaceholder(Field $field): string
    {
        $projectLabel = $field->project->label ?: $field->project->name;
        $projectName = $this->sanitizePlaceholderSegment($projectLabel ?? 'Project ' . $field->project_id);
        $fieldName = $this->sanitizePlaceholderSegment($field->name);

        return sprintf('EXT:{{%s: %s}}', $projectName, $fieldName);
    }

    private function sanitizePlaceholderSegment(string $value): string
    {
        return trim(str_replace(['{{', '}}'], '', $value));
    }

    private function createFieldData(Field $field): array
    {
        return [
            'id' => $field->id,
            'label' => $field->label ?: $field->name,
            'isProjectSpecific' => $field->project_id !== null,
        ];
    }

    private function createExternalFieldData(Field $field): array
    {
        $projectName = $field->project->label ?: $field->project->name ?: ('Project ' . $field->project_id);
        $fieldLabel = $field->label ?: $field->name;

        return [
            'id' => $field->id,
            'label' => sprintf('%s: %s', $projectName, $fieldLabel),
            'isProjectSpecific' => true,
        ];
    }

    public function saveFieldWithOptions(Field $field, array $options): bool
    {
        $oldIDs = ArrayHelper::map($field->fieldOptions, 'id', 'id');
        $newIDs = ArrayHelper::map($options, 'id', 'id');
        $deletedIDs = array_diff($oldIDs, array_filter($newIDs));

        if (!$field->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$field->save(false)) {
                throw new Exception('Failed to save field.');
            }

            foreach ($options as $option) {
                $option->field_id = $field->id;
            }

            if (!Model::validateMultiple($options)) {
                $transaction->rollBack();
                return false;
            }

            if (!empty($deletedIDs)) {
                FieldOption::deleteAll(['id' => $deletedIDs]);
            }

            foreach ($options as $option) {
                if (!$option->save(false)) {
                    throw new Exception('Failed to save field option.');
                }
            }

            $transaction->commit();
            return true;
        } catch (Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), LogCategory::DATABASE->value);
            return false;
        }
    }

    public function deleteField(Field $field): bool
    {
        try {
            return $field->delete();
        } catch (Throwable $e) {
            Yii::error($e->getMessage(), LogCategory::DATABASE->value);
            return false;
        }
    }

    public function renumberFieldOptions(Field $field): bool
    {
        $fieldOptions = $field->getFieldOptions()->all();

        if (empty($fieldOptions)) {
            return true;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $order = 10;
            foreach ($fieldOptions as $option) {
                $option->order = $order;
                if (!$option->save(false)) {
                    throw new Exception('Failed to save field option.');
                }
                $order += 10;
            }

            $transaction->commit();
            return true;
        } catch (Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), LogCategory::DATABASE->value);
            return false;
        }
    }

}
