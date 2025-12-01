<?php

namespace app\migrations;

use yii\db\Expression;
use yii\db\Migration;

class m251202_000001_add_label_to_prompt_instance extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%prompt_instance}}', 'project_id', $this->integer()->null());

        $this->update(
            '{{%prompt_instance}}',
            ['project_id' => new Expression('(SELECT project_id FROM {{%prompt_template}} pt WHERE pt.id = {{%prompt_instance}}.template_id)')]
        );

        $this->alterColumn('{{%prompt_instance}}', 'project_id', $this->integer()->notNull());

        $this->addForeignKey(
            'fk_prompt_instance_project',
            '{{%prompt_instance}}',
            'project_id',
            '{{%project}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx_prompt_instance_project_label',
            '{{%prompt_instance}}',
            ['project_id', 'label'],
            true
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_prompt_instance_project_label', '{{%prompt_instance}}');
        $this->dropForeignKey('fk_prompt_instance_project', '{{%prompt_instance}}');
        $this->dropColumn('{{%prompt_instance}}', 'project_id');
    }
}
