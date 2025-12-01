<?php

namespace app\models\query;

use app\models\ProjectLinkedProject;
use yii\db\ActiveQuery;

/**
 * @extends ActiveQuery<ProjectLinkedProject>
 */
class ProjectLinkedProjectQuery extends ActiveQuery
{
    public function linkedProjectIdsFor(int $projectId, int $userId): self
    {
        return $this
            ->select('linked_project_id')
            ->innerJoinWith('linkedProject', false)
            ->andWhere([
                'project_id' => $projectId,
                'linkedProject.user_id' => $userId,
                'linkedProject.deleted_at' => null,
            ]);
    }
}
