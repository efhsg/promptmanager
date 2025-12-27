<?php

namespace app\models\query;

use app\models\Field;
use yii\db\ActiveQuery;

/**
 * @extends ActiveQuery<Field>
 */
class FieldQuery extends ActiveQuery
{
    public function sharedFromProjects(int $userId, array $projectIds): self
    {
        return $this
            ->with('project')
            ->where(['user_id' => $userId])
            ->andWhere(['project_id' => $projectIds, 'share' => 1]);
    }
}
