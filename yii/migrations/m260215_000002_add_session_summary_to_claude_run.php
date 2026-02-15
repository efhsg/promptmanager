<?php

namespace app\migrations;

use yii\db\Migration;

class m260215_000002_add_session_summary_to_claude_run extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%claude_run}}', 'session_summary', $this->string(255)->null()->after('prompt_summary'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%claude_run}}', 'session_summary');
    }
}
