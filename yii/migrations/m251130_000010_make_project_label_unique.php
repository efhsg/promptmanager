<?php

namespace app\migrations;

use yii\db\Migration;

class m251130_000010_make_project_label_unique extends Migration
{
    private const INDEX_NAME = 'idx-project-user_label-unique';

    public function safeUp(): void
    {
        $this->createIndex(
            self::INDEX_NAME,
            '{{%project}}',
            ['user_id', 'label'],
            true
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex(self::INDEX_NAME, '{{%project}}');
    }
}
