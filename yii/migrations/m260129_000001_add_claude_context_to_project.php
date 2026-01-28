<?php

namespace app\migrations;

use yii\db\Migration;

class m260129_000001_add_claude_context_to_project extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%project}}',
            'claude_context',
            $this->text()->after('claude_options')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%project}}', 'claude_context');
    }
}
