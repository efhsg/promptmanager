<?php

namespace app\services;

use app\models\Field;

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
        return array_reduce($fields, function (array $fieldsMap, Field $field) {
            $prefix = $field->project_id ? 'PRJ:' : 'GEN:';
            $placeholder = $prefix . '{{' . $field->name . '}}';
            $label = $field->label ?: $field->name;
            $fieldsMap[$placeholder] = [
                'label' => $label,
                'isProjectSpecific' => $field->project_id !== null,
            ];
            return $fieldsMap;
        }, []);
    }
}
