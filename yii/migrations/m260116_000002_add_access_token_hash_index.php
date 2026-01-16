<?php

namespace app\migrations;

use yii\db\Migration;

class m260116_000002_add_access_token_hash_index extends Migration
{
    public function safeUp(): void
    {
        $this->createIndex(
            'idx_user_access_token_hash',
            '{{%user}}',
            'access_token_hash',
            true
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_user_access_token_hash', '{{%user}}');
    }
}
