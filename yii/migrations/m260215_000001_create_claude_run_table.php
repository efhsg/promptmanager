<?php

namespace app\migrations;

use yii\db\Migration;

class m260215_000001_create_claude_run_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%claude_run}}', [
            'id' => $this->primaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'project_id' => $this->integer()->notNull(),
            'session_id' => $this->string(191)->null(),
            'status' => "ENUM('pending','running','completed','failed','cancelled') NOT NULL DEFAULT 'pending'",
            'prompt_markdown' => 'LONGTEXT NOT NULL',
            'prompt_summary' => $this->string(255)->null(),
            'options' => $this->json()->null(),
            'working_directory' => $this->string(500)->null(),
            'stream_log' => 'LONGTEXT NULL',
            'result_text' => 'LONGTEXT NULL',
            'result_metadata' => $this->json()->null(),
            'error_message' => $this->text()->null(),
            'pid' => $this->integer()->unsigned()->null(),
            'started_at' => $this->dateTime()->null(),
            'completed_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        // Foreign keys
        $this->addForeignKey(
            'fk_claude_run_user',
            '{{%claude_run}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_claude_run_project',
            '{{%claude_run}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Indexes
        $this->createIndex('idx_claude_run_user_status', '{{%claude_run}}', ['user_id', 'status']);
        $this->createIndex('idx_claude_run_project', '{{%claude_run}}', ['project_id']);
        $this->createIndex('idx_claude_run_session', '{{%claude_run}}', ['session_id']);
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_claude_run_project', '{{%claude_run}}');
        $this->dropForeignKey('fk_claude_run_user', '{{%claude_run}}');
        $this->dropTable('{{%claude_run}}');
    }
}
