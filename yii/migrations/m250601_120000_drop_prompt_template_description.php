<?php

use yii\db\Migration;

class m250601_120000_drop_prompt_template_description extends Migration
{
    public function safeUp(): void
    {
        $this->dropColumn('{{%prompt_template}}', 'description');
    }

    public function safeDown(): void
    {
        $this->addColumn('{{%prompt_template}}', 'description', $this->text());
    }
}