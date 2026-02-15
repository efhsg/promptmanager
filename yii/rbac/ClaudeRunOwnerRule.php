<?php

namespace app\rbac;

use yii\rbac\Rule;

/**
 * Checks if the user ID matches the user_id attribute of the ClaudeRun.
 */
class ClaudeRunOwnerRule extends Rule
{
    public $name = 'isClaudeRunOwner';

    public function execute($user, $item, $params): bool
    {
        if (isset($params['model']->user_id)) {
            return (int) $params['model']->user_id === (int) $user;
        }

        return false;
    }
}
