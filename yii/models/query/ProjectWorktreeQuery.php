<?php

namespace app\models\query;

use app\models\ProjectWorktree;
use common\enums\WorktreePurpose;
use yii\db\ActiveQuery;

/**
 * @extends ActiveQuery<ProjectWorktree>
 */
class ProjectWorktreeQuery extends ActiveQuery
{
    public function forProject(int $projectId): self
    {
        return $this->andWhere(['project_id' => $projectId]);
    }

    public function forUser(int $userId): self
    {
        return $this->innerJoinWith('project p', false)
            ->andWhere(['p.user_id' => $userId]);
    }

    public function withPurpose(WorktreePurpose $purpose): self
    {
        return $this->andWhere(['purpose' => $purpose->value]);
    }
}
