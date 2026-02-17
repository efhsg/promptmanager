<?php

namespace app\migrations;

use yii\db\Migration;

class m260217_000002_rename_claude_columns_in_project extends Migration
{
    public function safeUp(): void
    {
        $this->renameColumn('{{%project}}', 'claude_options', 'ai_options');
        $this->renameColumn('{{%project}}', 'claude_context', 'ai_context');
    }

    public function safeDown(): void
    {
        $this->renameColumn('{{%project}}', 'ai_context', 'claude_context');
        $this->renameColumn('{{%project}}', 'ai_options', 'claude_options');
    }
}
