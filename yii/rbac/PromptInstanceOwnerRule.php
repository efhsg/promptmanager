<?php

namespace app\rbac;

use app\models\PromptInstance;
use yii\rbac\Rule;

class PromptInstanceOwnerRule extends Rule
{
    public $name = 'isPromptInstanceOwner';

    public function execute($user, $item, $params): bool
    {
        if (!isset($params['model']) || !($params['model'] instanceof PromptInstance)) {
            return false;
        }
        return $params['model']->template->project->user_id === (int) $user;
    }
}
