<?php

namespace app\migrations;

use yii\db\Migration;

class m250610_000004_add_allowed_file_extensions_to_project extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%project}}',
            'allowed_file_extensions',
            $this->string(255)->null()->after('root_directory')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%project}}', 'allowed_file_extensions');
    }
}
