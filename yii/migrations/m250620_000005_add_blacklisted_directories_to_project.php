<?php

namespace app\migrations;

use yii\db\Migration;

class m250620_000005_add_blacklisted_directories_to_project extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%project}}',
            'blacklisted_directories',
            $this->text()->null()->after('allowed_file_extensions')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%project}}', 'blacklisted_directories');
    }
}
