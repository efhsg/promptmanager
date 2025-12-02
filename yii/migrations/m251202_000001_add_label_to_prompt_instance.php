<?php

namespace app\migrations;

use yii\db\Migration;

class m251202_000001_add_label_to_prompt_instance extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%prompt_instance}}',
            'label',
            $this->string(255)->notNull()->defaultValue('')->after('template_id')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%prompt_instance}}', 'label');
    }
}
