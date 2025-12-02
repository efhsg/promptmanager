<?php

namespace app\migrations;

use yii\db\Migration;

class m251202_000002_add_share_flag_to_context extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%context}}',
            'share',
            $this->boolean()->notNull()->defaultValue(false)->after('is_default')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%context}}', 'share');
    }
}
