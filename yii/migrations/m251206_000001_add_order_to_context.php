<?php

namespace app\migrations;

use yii\db\Migration;

class m251206_000001_add_order_to_context extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%context}}',
            'order',
            $this->integer()->notNull()->defaultValue(0)->after('share')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%context}}', 'order');
    }
}
