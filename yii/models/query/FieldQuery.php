<?php

namespace app\models\query;

use app\models\Field;
use yii\db\ActiveQuery;

/**
 * @extends ActiveQuery<Field>
 */
class FieldQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this->andWhere([Field::tableName() . '.user_id' => $userId]);
    }

    public function searchByTerm(string $term): self
    {
        return $this->andWhere(['or',
            ['like', Field::tableName() . '.name', $term],
            ['like', Field::tableName() . '.label', $term],
            ['like', Field::tableName() . '.content', $term],
        ]);
    }

    public function searchByKeywords(array $keywords): self
    {
        $conditions = ['or'];
        foreach ($keywords as $keyword) {
            $conditions[] = ['or',
                ['like', Field::tableName() . '.name', $keyword],
                ['like', Field::tableName() . '.label', $keyword],
                ['like', Field::tableName() . '.content', $keyword],
            ];
        }

        return $this->andWhere($conditions);
    }

    public function sharedFromProjects(int $userId, array $projectIds): self
    {
        return $this
            ->with('project')
            ->where(['user_id' => $userId])
            ->andWhere(['project_id' => $projectIds, 'share' => 1]);
    }
}
