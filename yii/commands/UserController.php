<?php

namespace app\commands;

use Exception;
use Yii;
use yii\console\Controller;

class UserController extends Controller
{
    /**
     * Assigns a role to a user.
     *
     * Usage: php yii user/assign-role <user_id> <role_name>
     *
     * @param integer $userId
     * @param string $roleName
     * @throws Exception
     */
    public function actionAssignRole(int $userId, string $roleName): void
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($roleName);
        if (!$role) {
            echo "Role '$roleName' does not exist.\n";
            return;
        }

        $auth->assign($role, $userId);
        echo "Role '$roleName' has been assigned to user with ID $userId.\n";
    }
}
