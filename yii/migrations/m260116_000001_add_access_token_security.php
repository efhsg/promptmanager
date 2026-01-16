<?php

namespace app\migrations;

use yii\db\Migration;

class m260116_000001_add_access_token_security extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%user}}', 'access_token_hash', $this->string(255)->null());
        $this->addColumn('{{%user}}', 'access_token_expires_at', $this->integer()->null());

        // Invalidate all existing plaintext tokens (users must regenerate)
        $this->update('{{%user}}', ['access_token' => null]);
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%user}}', 'access_token_hash');
        $this->dropColumn('{{%user}}', 'access_token_expires_at');
    }
}
