<?php

namespace app\migrations;

use yii\db\Migration;

class m260128_000002_add_claude_options_to_project extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%project}}',
            'claude_options',
            $this->json()->after('claude_permission_mode')
        );

        // Migrate existing permission mode into JSON
        $this->execute("
            UPDATE {{%project}}
            SET claude_options = JSON_OBJECT('permissionMode', claude_permission_mode)
            WHERE claude_permission_mode IS NOT NULL
        ");

        $this->dropColumn('{{%project}}', 'claude_permission_mode');
    }

    public function safeDown(): void
    {
        // Re-add the enum column
        $this->addColumn(
            '{{%project}}',
            'claude_permission_mode',
            "ENUM('plan','dontAsk','bypassPermissions','acceptEdits','default') NOT NULL DEFAULT 'plan' AFTER `prompt_instance_copy_format`"
        );

        // Extract permissionMode from JSON back to enum column
        $this->execute("
            UPDATE {{%project}}
            SET claude_permission_mode = COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(claude_options, '$.permissionMode')),
                'plan'
            )
            WHERE claude_options IS NOT NULL
        ");

        $this->dropColumn('{{%project}}', 'claude_options');
    }
}
