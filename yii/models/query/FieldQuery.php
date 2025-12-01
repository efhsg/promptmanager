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
            ->alias('f')
            ->with('project')
            ->where(['f.user_id' => $userId])
            ->andWhere(['f.project_id' => $projectIds, 'f.share' => 1]);
    }
}
