<?php

namespace app\migrations;

use yii\db\Migration;

class m260211_000003_drop_note_response_column extends Migration
{
    public function safeUp(): void
    {
        $this->dropColumn('{{%note}}', 'response');
    }

    public function safeDown(): void
    {
        $this->addColumn('{{%note}}', 'response', $this->text()->null()->after('content'));
    }
}
