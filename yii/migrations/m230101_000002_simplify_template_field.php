<?php /** @noinspection DuplicatedCode */

namespace app\migrations;

use yii\db\Migration;

class m230101_000002_simplify_template_field extends Migration
{
    public function safeUp(): void
    {
        $this->dropForeignKey('fk_template_field_field', '{{%template_field}}');
        $this->dropForeignKey('fk_template_field_template', '{{%template_field}}');
        $this->dropTable('{{%template_field}}');

        $this->createTable('{{%template_field}}', [
            'template_id' => $this->integer()->notNull(),
            'field_id' => $this->integer()->notNull(),
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
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_template_field_field', '{{%template_field}}');
        $this->dropForeignKey('fk_template_field_template', '{{%template_field}}');
        $this->dropTable('{{%template_field}}');

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
    }
}
