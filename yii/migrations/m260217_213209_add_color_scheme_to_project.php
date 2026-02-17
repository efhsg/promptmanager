<?php

namespace app\migrations;

use yii\db\Migration;

class m260217_213209_add_color_scheme_to_project extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%project}}',
            'color_scheme',
            $this->string(32)->null()->after('label')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%project}}', 'color_scheme');
    }
}
