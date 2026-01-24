<?php

namespace app\models\query;

use app\models\ScratchPad;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * @extends ActiveQuery<ScratchPad>
 */
class ScratchPadQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this->andWhere([ScratchPad::tableName() . '.user_id' => $userId]);
    }

    public function forProject(?int $projectId): self
    {
        if ($projectId === null) {
            return $this->andWhere([ScratchPad::tableName() . '.project_id' => null]);
        }
        return $this->andWhere([ScratchPad::tableName() . '.project_id' => $projectId]);
    }

    public function forUserWithProject(int $userId, ?int $projectId): self
    {
        $query = $this->forUser($userId);

        if ($projectId !== null) {
            return $query->forProject($projectId);
        }

        return $query->andWhere([ScratchPad::tableName() . '.project_id' => null]);
    }

    public function orderedByUpdated(): self
    {
        return $this->orderBy([ScratchPad::tableName() . '.updated_at' => SORT_DESC]);
    }

    public function orderedByName(): self
    {
        return $this->orderBy([ScratchPad::tableName() . '.name' => SORT_ASC]);
    }

    public function searchByTerm(string $term): self
    {
        return $this->andWhere(['or',
            ['like', ScratchPad::tableName() . '.name', $term],
            ['like', ScratchPad::tableName() . '.content', $term],
        ]);
    }

    /**
     * Orders results so name matches appear before content-only matches.
     */
    public function prioritizeNameMatch(string $term): self
    {
        $tableName = ScratchPad::tableName();
        $escapedTerm = '%' . addcslashes($term, '%_\\') . '%';

        return $this->orderBy(new Expression(
            "CASE WHEN {$tableName}.name LIKE :nameTerm THEN 0 ELSE 1 END ASC, {$tableName}.updated_at DESC",
            [':nameTerm' => $escapedTerm]
        ));
    }

    public function searchByKeywords(array $keywords): self
    {
        $conditions = ['or'];
        foreach ($keywords as $keyword) {
            $conditions[] = ['or',
                ['like', ScratchPad::tableName() . '.name', $keyword],
                ['like', ScratchPad::tableName() . '.content', $keyword],
            ];
        }

        return $this->andWhere($conditions);
    }
}
