<?php

namespace app\migrations;

use app\services\EntityPermissionService;
use RuntimeException;
use Yii;
use yii\db\Migration;
use yii\rbac\DbManager;

class m260217_213210_add_set_color_scheme_permission extends Migration
{
    private const PERMISSION_NAME = 'setColorSchemeProject';
    private const PERMISSION_DESCRIPTION = 'Set Color Scheme';

    public function safeUp(): void
    {
        $auth = Yii::$app->authManager;
        if (!$auth instanceof DbManager) {
            throw new RuntimeException('The authManager must use DbManager to run this migration.');
        }

        $permission = $auth->getPermission(self::PERMISSION_NAME);
        if ($permission === null) {
            $permission = $auth->createPermission(self::PERMISSION_NAME);
            $permission->description = self::PERMISSION_DESCRIPTION;
            $auth->add($permission);
        }

        $userRole = $auth->getRole('user');
        if ($userRole !== null && !$auth->hasChild($userRole, $permission)) {
            $auth->addChild($userRole, $permission);
        }

        EntityPermissionService::invalidatePermissionCache();
    }

    public function safeDown(): void
    {
        $auth = Yii::$app->authManager;
        if (!$auth instanceof DbManager) {
            return;
        }

        $permission = $auth->getPermission(self::PERMISSION_NAME);
        if ($permission !== null) {
            foreach ($auth->getRoles() as $role) {
                if ($auth->hasChild($role, $permission)) {
                    $auth->removeChild($role, $permission);
                }
            }
            $auth->remove($permission);
        }

        EntityPermissionService::invalidatePermissionCache();
    }
}
