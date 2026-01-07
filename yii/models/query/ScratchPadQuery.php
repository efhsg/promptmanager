<?php

namespace app\models\query;

use app\models\ScratchPad;
use yii\db\ActiveQuery;

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
}
