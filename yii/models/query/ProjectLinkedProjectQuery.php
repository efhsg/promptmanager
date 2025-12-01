<?php

namespace app\models\query;

use app\models\Project;
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
            ->alias('plp')
            ->select('plp.linked_project_id')
            ->innerJoin(Project::tableName() . ' p', 'p.id = plp.linked_project_id')
            ->where([
                'plp.project_id' => $projectId,
                'p.user_id' => $userId,
                'p.deleted_at' => null,
            ]);
    }
}
