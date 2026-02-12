<?php

namespace app\migrations;

use yii\db\Migration;

class m260211_000001_rename_scratch_pad_to_note extends Migration
{
    public function safeUp(): void
    {
        // 1. Drop existing foreign keys
        $this->dropForeignKey('fk_scratch_pad_user', '{{%scratch_pad}}');
        $this->dropForeignKey('fk_scratch_pad_project', '{{%scratch_pad}}');

        // 2. Drop existing index
        $this->dropIndex('idx_scratch_pad_user_project', '{{%scratch_pad}}');

        // 3. Rename table
        $this->renameTable('{{%scratch_pad}}', '{{%note}}');

        // 4. Recreate foreign keys with new names
        $this->addForeignKey(
            'fk_note_user',
            '{{%note}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_note_project',
            '{{%note}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // 5. Add type column
        $this->addColumn('{{%note}}', 'type', $this->string(50)->notNull()->defaultValue('note')->after('name'));

        // 6. Add parent_id column
        $this->addColumn('{{%note}}', 'parent_id', $this->integer()->null()->after('project_id'));

        // 7. Add parent foreign key
        $this->addForeignKey(
            'fk_note_parent',
            '{{%note}}',
            'parent_id',
            '{{%note}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        // 8. Add indexes
        $this->createIndex('idx_note_user_id_project_id', '{{%note}}', ['user_id', 'project_id']);
        $this->createIndex('idx_note_user_id_type', '{{%note}}', ['user_id', 'type']);
        $this->createIndex('idx_note_parent_id', '{{%note}}', ['parent_id']);
    }

    public function safeDown(): void
    {
        // 1. Drop new indexes
        $this->dropIndex('idx_note_parent_id', '{{%note}}');
        $this->dropIndex('idx_note_user_id_type', '{{%note}}');
        $this->dropIndex('idx_note_user_id_project_id', '{{%note}}');

        // 2. Drop parent FK
        $this->dropForeignKey('fk_note_parent', '{{%note}}');

        // 3. Drop new columns
        $this->dropColumn('{{%note}}', 'parent_id');
        $this->dropColumn('{{%note}}', 'type');

        // 4. Drop new FK's
        $this->dropForeignKey('fk_note_project', '{{%note}}');
        $this->dropForeignKey('fk_note_user', '{{%note}}');

        // 5. Rename table back
        $this->renameTable('{{%note}}', '{{%scratch_pad}}');

        // 6. Recreate original FK's
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

        // 7. Recreate original index
        $this->createIndex('idx_scratch_pad_user_project', '{{%scratch_pad}}', ['user_id', 'project_id']);
    }
}
