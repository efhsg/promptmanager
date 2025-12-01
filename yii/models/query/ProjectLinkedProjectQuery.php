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
        $primaryTableName = $this->getPrimaryTableName();
        $projectTable = Project::tableName();

        return $this
            ->select("$primaryTableName.linked_project_id")
            ->innerJoinWith('linkedProject', false)
            ->andWhere([
                "$primaryTableName.project_id" => $projectId,
                "$projectTable.user_id" => $userId,
                "$projectTable.deleted_at" => null,
            ]);
    }
}
