<?php

namespace app\migrations;

use yii\db\Migration;

class m260130_104444_alter_scratch_pad_content_and_summation_to_longtext extends Migration
{
    public function safeUp(): void
    {
        $this->alterColumn(
            '{{%scratch_pad}}',
            'content',
            'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL'
        );

        $this->alterColumn(
            '{{%scratch_pad}}',
            'summation',
            'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL'
        );
    }

    public function safeDown(): void
    {
        $this->alterColumn(
            '{{%scratch_pad}}',
            'content',
            'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL'
        );

        $this->alterColumn(
            '{{%scratch_pad}}',
            'summation',
            'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL'
        );
    }
}
