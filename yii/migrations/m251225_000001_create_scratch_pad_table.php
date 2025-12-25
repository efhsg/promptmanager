<?php

namespace app\migrations;

use yii\db\Migration;

class m251225_000001_create_scratch_pad_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%scratch_pad}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'project_id' => $this->integer()->null(),
            'name' => $this->string(255)->notNull(),
            'content' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_scratch_pad_user',
            '{{%scratch_pad}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_scratch_pad_project',
            '{{%scratch_pad}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx_scratch_pad_user_project',
            '{{%scratch_pad}}',
            ['user_id', 'project_id']
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_scratch_pad_user_project', '{{%scratch_pad}}');
        $this->dropForeignKey('fk_scratch_pad_project', '{{%scratch_pad}}');
        $this->dropForeignKey('fk_scratch_pad_user', '{{%scratch_pad}}');
        $this->dropTable('{{%scratch_pad}}');
    }
}
