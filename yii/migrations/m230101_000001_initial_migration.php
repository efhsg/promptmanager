<?php

/** @noinspection PhpUnused */

namespace app\migrations;

use common\constants\FieldConstants;
use yii\db\Migration;

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

        $this->addForeignKey(
            'fk_project_user',
            '{{%project}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
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

        $this->addForeignKey(
            'fk_context_project',
            '{{%context}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        /*******************************************************
         * 3. field
         *******************************************************/
        $enumValues = implode(',', array_map(fn($type) => "'$type'", FieldConstants::TYPES));
        $this->createTable('{{%field}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'project_id' => $this->integer()->null(),
            'name' => $this->string()->notNull(),
            // This syntax works on MySQL but is not portable to all DBs
            'type' => "ENUM($enumValues) NOT NULL",
            'content' => $this->text()->null(),
            'selected_by_default' => $this->boolean()->notNull()->defaultValue(false),
            'label' => $this->string()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_field_user',
            '{{%field}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_field_project',
            '{{%field}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx_field_project_id_name_unique',
            '{{%field}}',
            ['project_id', 'name'],
            true
        );

        /*******************************************************
         * 3a. field_option
         * For storing select/multi-select options.
         *******************************************************/
        $this->createTable('{{%field_option}}', [
            'id' => $this->primaryKey(),
            'field_id' => $this->integer()->notNull(),
            'value' => $this->text()->notNull(),
            'label' => $this->string()->null(),
            'selected_by_default' => $this->boolean()->notNull()->defaultValue(false),
            'order' => $this->integer()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_field_option_field',
            '{{%field_option}}',
            'field_id',
            '{{%field}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        /*******************************************************
         * 4. prompt_template
         *******************************************************/
        $this->createTable('{{%prompt_template}}', [
            'id' => $this->primaryKey(),
            'project_id' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'template_body' => $this->text()->notNull(),
            'description' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_prompt_template_project',
            '{{%prompt_template}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        /*******************************************************
         * 5. template_field
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
         * 6. prompt_instance
         *******************************************************/
        $this->createTable('{{%prompt_instance}}', [
            'id' => $this->primaryKey(),
            'template_id' => $this->integer()->notNull(),
            'final_prompt' => $this->text()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_prompt_instance_template',
            '{{%prompt_instance}}',
            'template_id',
            '{{%prompt_template}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        /*******************************************************
         * 6. user_preference
         *******************************************************/
        $this->createTable('{{%user_preference}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'pref_key' => $this->string(255)->notNull(),
            'pref_value' => $this->string(255)->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex(
            'idx_user_preference_user_key',
            '{{%user_preference}}',
            ['user_id', 'pref_key'],
            true
        );

        $this->addForeignKey(
            'fk_user_preference_user',
            '{{%user_preference}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

    }

    public function safeDown(): void
    {

        /*******************************************************
         * user_preference
         *******************************************************/
        $this->dropForeignKey('fk_user_preference_user', '{{%user_preference}}');
        $this->dropIndex('idx_user_preference_user_key', '{{%user_preference}}');
        $this->dropTable('{{%user_preference}}');


        // Drop prompt_instance
        $this->dropForeignKey('fk_prompt_instance_template', '{{%prompt_instance}}');
        $this->dropTable('{{%prompt_instance}}');

        // Drop template_field
        $this->dropForeignKey('fk_template_field_field', '{{%template_field}}');
        $this->dropForeignKey('fk_template_field_template', '{{%template_field}}');
        $this->dropTable('{{%template_field}}');

        // Drop prompt_template
        $this->dropForeignKey('fk_prompt_template_project', '{{%prompt_template}}');
        $this->dropTable('{{%prompt_template}}');

        // Drop field_option
        $this->dropForeignKey('fk_field_option_field', '{{%field_option}}');
        $this->dropTable('{{%field_option}}');

        // Drop field
        $this->dropForeignKey('fk_field_user', '{{%field}}');
        $this->dropTable('{{%field}}');

        // Drop context
        $this->dropForeignKey('fk_context_project', '{{%context}}');
        $this->dropTable('{{%context}}');

        // Drop project
        $this->dropForeignKey('fk_project_user', '{{%project}}');
        $this->dropTable('{{%project}}');
    }
}
