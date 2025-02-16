<?php

namespace app\services;

use app\models\Field;
use app\models\FieldOption;
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

    private function createFieldData(Field $field): array
    {
        return [
            'id' => $field->id,
            'label' => $field->label ?: $field->name,
            'isProjectSpecific' => $field->project_id !== null,
        ];
    }

    public function saveFieldWithOptions(Field $field, array $options): bool
    {
        $oldIDs = ArrayHelper::map($field->fieldOptions, 'id', 'id');
        $newIDs = ArrayHelper::map($options, 'id', 'id');
        $deletedIDs = array_diff($oldIDs, array_filter($newIDs));

        $valid = $field->validate() && Model::validateMultiple($options);
        if (!$valid) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$field->save(false)) {
                throw new Exception('Failed to save field.');
            }

            if (!empty($deletedIDs)) {
                FieldOption::deleteAll(['id' => $deletedIDs]);
            }

            foreach ($options as $option) {
                $option->field_id = $field->id;
                if (!$option->save(false)) {
                    throw new Exception('Failed to save field option.');
                }
            }

            $transaction->commit();
            return true;
        } catch (Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), 'database');
            return false;
        }
    }

    public function deleteField(Field $field): bool
    {
        try {
            return $field->delete();
        } catch (Throwable $e) {
            Yii::error($e->getMessage(), 'database');
            return false;
        }
    }

}
