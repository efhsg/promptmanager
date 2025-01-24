<?php

namespace app\services;

use app\models\Project;
use yii\helpers\ArrayHelper;

class ProjectService
{
    public function fetchProjectsList(int $userId): array
    {
        return ArrayHelper::map(
            Project::find()->where(['user_id' => $userId])->all() ?: [],
            'id',
            'name'
        );
    }
}
