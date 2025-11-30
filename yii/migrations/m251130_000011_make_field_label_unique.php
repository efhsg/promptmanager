<?php

namespace app\migrations;

use yii\db\Migration;

class m251130_000011_make_field_label_unique extends Migration
{
    private const INDEX_NAME = 'idx-field-project_label_user-unique';

    public function safeUp(): void
    {
        $this->createIndex(
            self::INDEX_NAME,
            '{{%field}}',
            ['project_id', 'label', 'user_id'],
            true
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex(self::INDEX_NAME, '{{%field}}');
    }
}
