<?php

namespace app\rbac;

use yii\rbac\Rule;

/**
 * Checks if the user ID matches the user_id attribute of the AiRun.
 */
class AiRunOwnerRule extends Rule
{
    public $name = 'isAiRunOwner';

    public function execute($user, $item, $params): bool
    {
        if (isset($params['model']->user_id)) {
            return (int) $params['model']->user_id === (int) $user;
        }

        return false;
    }
}
