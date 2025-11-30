<?php

namespace app\migrations;

use app\rbac\PromptTemplateOwnerRule;
use app\services\EntityPermissionService;
use Yii;
use yii\db\Migration;
use yii\rbac\DbManager;

class m250715_120000_add_generate_prompt_permission extends Migration
{
    private const NEW_PERMISSION = 'generatePrompt';
    private const LEGACY_PERMISSION = 'generatePromptForm';
    private const PERMISSION_DESCRIPTION = 'Generate a Prompt Instance';

    public function safeUp(): void
    {
        $auth = Yii::$app->authManager;
        if (!$auth instanceof DbManager) {
            throw new \RuntimeException('The authManager must use DbManager to run this migration.');
        }

        $rule = new PromptTemplateOwnerRule();
        $ruleName = $rule->name;
        if ($auth->getRule($ruleName) === null) {
            $auth->add($rule);
        }

        $permission = $auth->getPermission(self::NEW_PERMISSION);
        if ($permission === null) {
            $permission = $auth->createPermission(self::NEW_PERMISSION);
            $permission->description = self::PERMISSION_DESCRIPTION;
            $permission->ruleName = $ruleName;
            $auth->add($permission);
        } elseif ($permission->ruleName === null) {
            $permission->ruleName = $ruleName;
            $auth->update(self::NEW_PERMISSION, $permission);
        }

        $legacyPermission = $auth->getPermission(self::LEGACY_PERMISSION);
        foreach ($auth->getRoles() as $role) {
            $permissionsByRole = $auth->getPermissionsByRole($role->name);
            $roleHasLegacy = isset($permissionsByRole[self::LEGACY_PERMISSION]);

            if (($role->name === 'user' || $roleHasLegacy) && !$auth->hasChild($role, $permission)) {
                $auth->addChild($role, $permission);
            }

            if ($legacyPermission !== null && $roleHasLegacy && $auth->hasChild($role, $legacyPermission)) {
                $auth->removeChild($role, $legacyPermission);
            }
        }

        if ($legacyPermission !== null) {
            $auth->remove($legacyPermission);
        }

        EntityPermissionService::invalidatePermissionCache();
    }

    public function safeDown(): void
    {
        $auth = Yii::$app->authManager;
        if (!$auth instanceof DbManager) {
            return;
        }

        $permission = $auth->getPermission(self::NEW_PERMISSION);
        if ($permission !== null) {
            foreach ($auth->getRoles() as $role) {
                if ($auth->hasChild($role, $permission)) {
                    $auth->removeChild($role, $permission);
                }
            }
            $auth->remove($permission);
        }

        $rule = new PromptTemplateOwnerRule();
        $ruleName = $rule->name;
        if ($auth->getRule($ruleName) === null) {
            $auth->add($rule);
        }

        $legacyPermission = $auth->getPermission(self::LEGACY_PERMISSION);
        if ($legacyPermission === null) {
            $legacyPermission = $auth->createPermission(self::LEGACY_PERMISSION);
            $legacyPermission->description = self::PERMISSION_DESCRIPTION;
            $legacyPermission->ruleName = $ruleName;
            $auth->add($legacyPermission);
        } elseif ($legacyPermission->ruleName === null) {
            $legacyPermission->ruleName = $ruleName;
            $auth->update(self::LEGACY_PERMISSION, $legacyPermission);
        }

        foreach ($auth->getRoles() as $role) {
            if (!$auth->hasChild($role, $legacyPermission)) {
                $auth->addChild($role, $legacyPermission);
            }
        }

        EntityPermissionService::invalidatePermissionCache();
    }
}
