<?php /** @noinspection PhpUnused */

use yii\db\Migration;

/**
 * Class m230101_000001_initial_migration
 *
 * This migration sets up:
 *  - project
 *  - context
 *  - field
 *  - prompt_template
 *  - template_field
 *  - prompt_instance
 *  - prompt_instance_field
 */
class m230101_000001_initial_migration extends Migration
{
    public function safeUp(): void
    {
        /*******************************************************
         * 1. project
         *******************************************************/
        $this->createTable('{{%project}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'deleted_at' => $this->integer()->null(),
        ]);

        // FK: project -> user
        $this->addForeignKey(
            'fk_project_user',
            '{{%project}}',
            'user_id',
            '{{%user}}',  // Adjust if your user table is named differently
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Optional index on user_id
        $this->createIndex(
            'idx_project_user_id',
            '{{%project}}',
            'user_id'
        );

        /*******************************************************
         * 2. context
         *******************************************************/
        $this->createTable('{{%context}}', [
            'id' => $this->primaryKey(),
            'project_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'content' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        // FK: context -> project
        $this->addForeignKey(
            'fk_context_project',
            '{{%context}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Optional index on project_id
        $this->createIndex(
            'idx_context_project_id',
            '{{%context}}',
            'project_id'
        );

        /*******************************************************
         * 3. field (generic fields)
         *******************************************************/
        $this->createTable('{{%field}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),      // e.g. "codeBlock", "codeType"
            'type' => $this->string()->notNull(),      // e.g. "free", "single_select"
            'content' => $this->text()->null(),        // JSON or text for enumerated options
            'label' => $this->string()->null(),        // default label
            'is_generic' => $this->boolean()->defaultValue(true),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        /*******************************************************
         * 4. prompt_template
         *******************************************************/
        $this->createTable('{{%prompt_template}}', [
            'id' => $this->primaryKey(),
            'project_id' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'template_body' => $this->text()->notNull(), // e.g. "Given this code: {codeBlock}"
            'description' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        // FK: prompt_template -> project
        $this->addForeignKey(
            'fk_prompt_template_project',
            '{{%prompt_template}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Optional index on project_id
        $this->createIndex(
            'idx_prompt_template_project_id',
            '{{%prompt_template}}',
            'project_id'
        );

        /*******************************************************
         * 5. template_field (pivot table between prompt_template and field)
         *******************************************************/
        $this->createTable('{{%template_field}}', [
            'id' => $this->primaryKey(),
            'template_id' => $this->integer()->notNull(),
            'field_id' => $this->integer()->notNull(),
            'order' => $this->integer()->defaultValue(0),
            'override_label' => $this->string()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        // FKs: template_field -> prompt_template, field
        $this->addForeignKey(
            'fk_template_field_template',
            '{{%template_field}}',
            'template_id',
            '{{%prompt_template}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_template_field_field',
            '{{%template_field}}',
            'field_id',
            '{{%field}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        /*******************************************************
         * 6. prompt_instance (references only prompt_template)
         *******************************************************/
        $this->createTable('{{%prompt_instance}}', [
            'id' => $this->primaryKey(),
            'template_id' => $this->integer()->notNull(),
            'final_prompt' => $this->text()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        // FK: prompt_instance -> prompt_template
        $this->addForeignKey(
            'fk_prompt_instance_template',
            '{{%prompt_instance}}',
            'template_id',
            '{{%prompt_template}}',
            'id',
            'RESTRICT', // or CASCADE, adjust as you prefer
            'CASCADE'
        );

        /*******************************************************
         * 7. prompt_instance_field (user-supplied field values)
         *******************************************************/
        $this->createTable('{{%prompt_instance_field}}', [
            'id' => $this->primaryKey(),
            'instance_id' => $this->integer()->notNull(),
            'field_name' => $this->string()->notNull(),  // e.g. "codeBlock"
            'field_value' => $this->text()->notNull(),   // user input
        ]);

        // FK: prompt_instance_field -> prompt_instance
        $this->addForeignKey(
            'fk_prompt_instance_field_instance',
            '{{%prompt_instance_field}}',
            'instance_id',
            '{{%prompt_instance}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        // Reverse creation order:

        // 7. prompt_instance_field
        $this->dropForeignKey('fk_prompt_instance_field_instance', '{{%prompt_instance_field}}');
        $this->dropTable('{{%prompt_instance_field}}');

        // 6. prompt_instance
        $this->dropForeignKey('fk_prompt_instance_template', '{{%prompt_instance}}');
        $this->dropTable('{{%prompt_instance}}');

        // 5. template_field
        $this->dropForeignKey('fk_template_field_field', '{{%template_field}}');
        $this->dropForeignKey('fk_template_field_template', '{{%template_field}}');
        $this->dropTable('{{%template_field}}');

        // 4. prompt_template
        $this->dropForeignKey('fk_prompt_template_project', '{{%prompt_template}}');
        $this->dropIndex('idx_prompt_template_project_id', '{{%prompt_template}}');
        $this->dropTable('{{%prompt_template}}');

        // 3. field
        $this->dropTable('{{%field}}');

        // 2. context
        $this->dropForeignKey('fk_context_project', '{{%context}}');
        $this->dropIndex('idx_context_project_id', '{{%context}}');
        $this->dropTable('{{%context}}');

        // 1. project
        $this->dropForeignKey('fk_project_user', '{{%project}}');
        $this->dropIndex('idx_project_user_id', '{{%project}}');
        $this->dropTable('{{%project}}');
    }
}
