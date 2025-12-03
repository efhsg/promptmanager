<?php

namespace app\models\query;

use app\models\Project;
use yii\db\ActiveQuery;

/**
 * @extends ActiveQuery<Project>
 */
class ProjectQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this->andWhere(['user_id' => $userId]);
    }

    public function orderedByName(): self
    {
        return $this->orderBy(['name' => SORT_ASC]);
    }

    public function availableForLinking(?int $excludeProjectId, int $userId): self
    {
        $query = $this
            ->forUser($userId)
            ->andWhere(['deleted_at' => null]);

        if ($excludeProjectId !== null) {
            $query->andWhere(['!=', 'id', $excludeProjectId]);
        }

        return $query;
    }
}
