<?php

namespace app\migrations;

use yii\db\Migration;

class m260122_000001_add_summation_to_scratch_pad extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%scratch_pad}}',
            'summation',
            'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER content'
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%scratch_pad}}', 'summation');
    }
}
