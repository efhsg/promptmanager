<?php

namespace app\migrations;

use common\enums\ClaudePermissionMode;
use yii\db\Migration;

class m260128_000001_add_claude_permission_mode_to_project extends Migration
{
    public function safeUp(): void
    {
        $enumValues = implode(',', array_map(
            static fn(ClaudePermissionMode $mode): string => "'{$mode->value}'",
            ClaudePermissionMode::cases()
        ));

        $this->addColumn(
            '{{%project}}',
            'claude_permission_mode',
            "ENUM($enumValues) NOT NULL DEFAULT '" . ClaudePermissionMode::PLAN->value . "' AFTER `prompt_instance_copy_format`"
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%project}}', 'claude_permission_mode');
    }
}
