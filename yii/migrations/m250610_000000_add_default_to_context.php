<?php

namespace app\migrations;

use yii\db\Migration;

class m250610_000000_add_default_to_context extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('context', 'is_default', $this->boolean()->defaultValue(false)->after('content'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('context', 'is_default');
    }
}