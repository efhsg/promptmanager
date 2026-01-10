<?php

namespace app\migrations;

use yii\db\Migration;

class m260110_131732_alter_scratch_pad_content_charset extends Migration
{
    public function safeUp(): void
    {
        $this->alterColumn(
            '{{%scratch_pad}}',
            'content',
            'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL'
        );
    }

    public function safeDown(): void
    {
        $this->alterColumn(
            '{{%scratch_pad}}',
            'content',
            $this->text()->null()
        );
    }
}
