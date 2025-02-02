<?php


namespace app\rbac;

use yii\rbac\Rule;

/**
 * Checks if the user ID matches the user_id attribute of the project.
 */
class ProjectOwnerRule extends Rule
{
    public $name = 'isProjectOwner';

    public function execute($user, $item, $params): bool
    {
        if (isset($params['model']->user_id)) {
            return $params['model']->user_id == $user;
        }
        return false;
    }
}
