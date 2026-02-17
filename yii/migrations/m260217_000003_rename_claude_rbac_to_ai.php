<?php

namespace app\migrations;

use app\rbac\AiRunOwnerRule;
use app\services\EntityPermissionService;
use RuntimeException;
use Yii;
use yii\db\Migration;
use yii\rbac\DbManager;

class m260217_000003_rename_claude_rbac_to_ai extends Migration
{
    private const PERMISSION_MAP = [
        'viewClaudeRun' => ['name' => 'viewAiRun', 'description' => 'View an AI Run'],
        'updateClaudeRun' => ['name' => 'updateAiRun', 'description' => 'Update an AI Run'],
    ];

    private const OLD_RULE_NAME = 'isClaudeRunOwner';
    private const NEW_RULE_NAME = 'isAiRunOwner';

    public function safeUp(): void
    {
        $auth = Yii::$app->authManager;
        if (!$auth instanceof DbManager) {
            throw new RuntimeException('The authManager must use DbManager to run this migration.');
        }

        // Add new rule
        $newRule = new AiRunOwnerRule();
        $auth->add($newRule);

        // Rename permissions
        foreach (self::PERMISSION_MAP as $oldName => $newConfig) {
            $perm = $auth->getPermission($oldName);
            if ($perm === null) {
                continue;
            }
            $perm->name = $newConfig['name'];
            $perm->description = $newConfig['description'];
            $perm->ruleName = self::NEW_RULE_NAME;
            $auth->update($oldName, $perm);
        }

        // Remove old rule via SQL (old ClaudeRunOwnerRule class may not exist in future)
        $this->delete('{{%auth_rule}}', ['name' => self::OLD_RULE_NAME]);

        // Update queue channel
        $this->update('{{%queue}}', ['channel' => 'ai'], ['channel' => 'claude']);

        EntityPermissionService::invalidatePermissionCache();
    }

    public function safeDown(): void
    {
        $auth = Yii::$app->authManager;
        if (!$auth instanceof DbManager) {
            return;
        }

        // Reverse queue channel
        $this->update('{{%queue}}', ['channel' => 'claude'], ['channel' => 'ai']);

        // Re-add old rule via SQL (ClaudeRunOwnerRule class still exists at this point)
        $this->execute(
            "INSERT INTO {{%auth_rule}} (name, data, created_at, updated_at) VALUES (:name, :data, :created_at, :updated_at)",
            [
                ':name' => self::OLD_RULE_NAME,
                ':data' => serialize(new \app\rbac\ClaudeRunOwnerRule()),
                ':created_at' => time(),
                ':updated_at' => time(),
            ]
        );

        // Reverse permission renames
        foreach (self::PERMISSION_MAP as $oldName => $newConfig) {
            $perm = $auth->getPermission($newConfig['name']);
            if ($perm === null) {
                continue;
            }
            $perm->name = $oldName;
            $perm->description = str_replace('AI Run', 'Claude Run', $newConfig['description']);
            $perm->ruleName = self::OLD_RULE_NAME;
            $auth->update($newConfig['name'], $perm);
        }

        // Remove new rule
        $newRule = $auth->getRule(self::NEW_RULE_NAME);
        if ($newRule !== null) {
            $auth->remove($newRule);
        }

        EntityPermissionService::invalidatePermissionCache();
    }
}
