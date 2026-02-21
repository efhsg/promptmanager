<?php

namespace app\migrations;

use yii\db\Migration;

class m260222_000000_create_project_worktree_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%project_worktree}}', [
            'id' => $this->primaryKey(),
            'project_id' => $this->integer()->notNull(),
            'purpose' => $this->string(50)->notNull(),
            'branch' => $this->string(255)->notNull(),
            'path_suffix' => $this->string(100)->notNull(),
            'source_branch' => $this->string(255)->notNull()->defaultValue('main'),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_project_worktree_project',
            '{{%project_worktree}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'uk_project_suffix',
            '{{%project_worktree}}',
            ['project_id', 'path_suffix'],
            true
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_project_worktree_project', '{{%project_worktree}}');
        $this->dropTable('{{%project_worktree}}');
    }
}
