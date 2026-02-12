<?php

namespace app\migrations;

use app\rbac\NoteOwnerRule;
use app\services\EntityPermissionService;
use RuntimeException;
use Yii;
use yii\db\Migration;
use yii\rbac\DbManager;

class m260211_000004_rename_scratch_pad_rbac_permissions extends Migration
{
    private const PERMISSION_MAP = [
        'createScratchPad' => ['name' => 'createNote', 'description' => 'Create a Note'],
        'viewScratchPad' => ['name' => 'viewNote', 'description' => 'View a Note'],
        'updateScratchPad' => ['name' => 'updateNote', 'description' => 'Update a Note'],
        'deleteScratchPad' => ['name' => 'deleteNote', 'description' => 'Delete a Note'],
    ];

    private const OLD_RULE_NAME = 'isScratchPadOwner';
    private const NEW_RULE_NAME = 'isNoteOwner';

    public function safeUp(): void
    {
        $auth = Yii::$app->authManager;
        if (!$auth instanceof DbManager) {
            throw new RuntimeException('The authManager must use DbManager to run this migration.');
        }

        // Rename permissions
        foreach (self::PERMISSION_MAP as $oldName => $newConfig) {
            $perm = $auth->getPermission($oldName);
            if ($perm === null) {
                continue;
            }
            $perm->name = $newConfig['name'];
            $perm->description = $newConfig['description'];
            $auth->update($oldName, $perm);
        }

        // Add new rule first
        $newRule = new NoteOwnerRule();
        $auth->add($newRule);

        // Update permissions to reference the new rule (before deleting the old one, due to FK constraint)
        $permissionsWithRule = ['viewNote', 'updateNote', 'deleteNote'];
        foreach ($permissionsWithRule as $permName) {
            $perm = $auth->getPermission($permName);
            if ($perm !== null) {
                $perm->ruleName = self::NEW_RULE_NAME;
                $auth->update($permName, $perm);
            }
        }

        // Remove old rule directly via SQL (the old ScratchPadOwnerRule class no longer exists,
        // so DbManager->getRule() returns __PHP_Incomplete_Class which cannot be passed to remove())
        $this->delete('{{%auth_rule}}', ['name' => self::OLD_RULE_NAME]);

        EntityPermissionService::invalidatePermissionCache();
    }

    public function safeDown(): void
    {
        $auth = Yii::$app->authManager;
        if (!$auth instanceof DbManager) {
            return;
        }

        // Reverse permission renames
        foreach (self::PERMISSION_MAP as $oldName => $newConfig) {
            $perm = $auth->getPermission($newConfig['name']);
            if ($perm === null) {
                continue;
            }
            $perm->name = $oldName;
            $perm->description = str_replace('Note', 'Scratch Pad', $newConfig['description']);
            $auth->update($newConfig['name'], $perm);
        }

        // Replace rule back
        $newRule = $auth->getRule(self::NEW_RULE_NAME);
        if ($newRule !== null) {
            $auth->remove($newRule);
        }

        // Re-add old rule manually (ScratchPadOwnerRule may not exist anymore)
        $this->execute("INSERT INTO {{%auth_rule}} (name, data, created_at, updated_at) VALUES (:name, :data, :created_at, :updated_at)", [
            ':name' => self::OLD_RULE_NAME,
            ':data' => serialize((object) ['name' => self::OLD_RULE_NAME]),
            ':created_at' => time(),
            ':updated_at' => time(),
        ]);

        // Update permissions to use old rule
        $permissionsWithRule = ['viewScratchPad', 'updateScratchPad', 'deleteScratchPad'];
        foreach ($permissionsWithRule as $permName) {
            $perm = $auth->getPermission($permName);
            if ($perm !== null) {
                $perm->ruleName = self::OLD_RULE_NAME;
                $auth->update($permName, $perm);
            }
        }

        EntityPermissionService::invalidatePermissionCache();
    }
}
