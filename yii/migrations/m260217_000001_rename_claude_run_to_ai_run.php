<?php

namespace app\migrations;

use yii\db\Migration;

class m260217_000001_rename_claude_run_to_ai_run extends Migration
{
    public function safeUp(): void
    {
        // Rename table
        $this->renameTable('{{%claude_run}}', '{{%ai_run}}');

        // Add provider column
        $this->addColumn(
            '{{%ai_run}}',
            'provider',
            $this->string(50)->notNull()->defaultValue('claude')->after('project_id')
        );

        // Create new indexes first (MySQL needs an index for FK constraints;
        // creating replacements before dropping old ones avoids "needed in FK" errors)
        $this->createIndex('idx_ai_run_user_status', '{{%ai_run}}', ['user_id', 'status']);
        $this->createIndex('idx_ai_run_project', '{{%ai_run}}', ['project_id']);
        $this->createIndex('idx_ai_run_session', '{{%ai_run}}', ['session_id']);

        // Rename foreign keys (drop + recreate)
        $this->dropForeignKey('fk_claude_run_user', '{{%ai_run}}');
        $this->dropForeignKey('fk_claude_run_project', '{{%ai_run}}');

        $this->addForeignKey('fk_ai_run_user', '{{%ai_run}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_ai_run_project', '{{%ai_run}}', 'project_id', '{{%project}}', 'id', 'CASCADE', 'CASCADE');

        // Drop old indexes (safe now that FKs use the new ones)
        $this->dropIndex('idx_claude_run_user_status', '{{%ai_run}}');
        $this->dropIndex('idx_claude_run_project', '{{%ai_run}}');
        $this->dropIndex('idx_claude_run_session', '{{%ai_run}}');
    }

    public function safeDown(): void
    {
        // Recreate old indexes first
        $this->createIndex('idx_claude_run_user_status', '{{%ai_run}}', ['user_id', 'status']);
        $this->createIndex('idx_claude_run_project', '{{%ai_run}}', ['project_id']);
        $this->createIndex('idx_claude_run_session', '{{%ai_run}}', ['session_id']);

        // Drop new foreign keys
        $this->dropForeignKey('fk_ai_run_project', '{{%ai_run}}');
        $this->dropForeignKey('fk_ai_run_user', '{{%ai_run}}');

        // Recreate old foreign keys
        $this->addForeignKey('fk_claude_run_user', '{{%ai_run}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_claude_run_project', '{{%ai_run}}', 'project_id', '{{%project}}', 'id', 'CASCADE', 'CASCADE');

        // Drop new indexes (safe now that FKs use old ones)
        $this->dropIndex('idx_ai_run_session', '{{%ai_run}}');
        $this->dropIndex('idx_ai_run_project', '{{%ai_run}}');
        $this->dropIndex('idx_ai_run_user_status', '{{%ai_run}}');

        // Drop provider column
        $this->dropColumn('{{%ai_run}}', 'provider');

        // Rename table back
        $this->renameTable('{{%ai_run}}', '{{%claude_run}}');
    }
}
