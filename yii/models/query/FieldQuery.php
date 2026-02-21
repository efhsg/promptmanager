<?php

namespace app\models\query;

use app\models\Field;
use yii\db\ActiveQuery;
use yii\db\Expression;

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
            ['like', Field::tableName() . '.search_text', $term],
        ]);
    }

    /**
     * Orders results so name/label matches appear before content-only matches.
     */
    public function prioritizeNameMatch(string $term): self
    {
        $tableName = Field::tableName();
        $escapedTerm = '%' . addcslashes($term, '%_\\') . '%';

        return $this->orderBy(new Expression(
            "CASE WHEN {$tableName}.name LIKE :nameTerm OR {$tableName}.label LIKE :nameTerm THEN 0 ELSE 1 END ASC, {$tableName}.updated_at DESC",
            [':nameTerm' => $escapedTerm]
        ));
    }

    public function searchByKeywords(array $keywords): self
    {
        $conditions = ['or'];
        foreach ($keywords as $keyword) {
            $conditions[] = ['or',
                ['like', Field::tableName() . '.name', $keyword],
                ['like', Field::tableName() . '.label', $keyword],
                ['like', Field::tableName() . '.search_text', $keyword],
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
