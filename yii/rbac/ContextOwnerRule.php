<?php

namespace app\rbac;

use app\models\Context;
use yii\rbac\Rule;

class ContextOwnerRule extends Rule
{
    public $name = 'isContextOwner';

    public function execute($user, $item, $params): bool
    {
        if (!isset($params['model']) || !($params['model'] instanceof Context)) {
            return false;
        }
        return $params['model']->project->user_id === (int) $user;
    }
}
