<?php

namespace app\migrations;

use yii\db\Migration;

class m251130_000008_add_label_to_project extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%project}}',
            'label',
            $this->string(64)->null()->after('name')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%project}}', 'label');
    }
}
