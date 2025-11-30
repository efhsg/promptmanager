<?php

namespace app\migrations;

use yii\db\Migration;

class m251130_000007_create_project_linked_project_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%project_linked_project}}', [
            'id' => $this->primaryKey(),
            'project_id' => $this->integer()->notNull(),
            'linked_project_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_project_linked_project_project',
            '{{%project_linked_project}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_project_linked_project_linked_project',
            '{{%project_linked_project}}',
            'linked_project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx_project_linked_project_unique',
            '{{%project_linked_project}}',
            ['project_id', 'linked_project_id'],
            true
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_project_linked_project_unique', '{{%project_linked_project}}');
        $this->dropForeignKey('fk_project_linked_project_linked_project', '{{%project_linked_project}}');
        $this->dropForeignKey('fk_project_linked_project_project', '{{%project_linked_project}}');
        $this->dropTable('{{%project_linked_project}}');
    }
}
