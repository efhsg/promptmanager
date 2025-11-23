<?php

namespace app\migrations;

use common\enums\CopyType;
use yii\db\Migration;

class m250711_000006_add_prompt_instance_copy_format_to_project extends Migration
{
    public function safeUp(): void
    {
        $enumValues = implode(',', array_map(
            static fn(CopyType $type): string => "'{$type->value}'",
            CopyType::cases()
        ));

        $this->addColumn(
            '{{%project}}',
            'prompt_instance_copy_format',
            "ENUM($enumValues) NOT NULL DEFAULT '" . CopyType::MD->value . "' AFTER `blacklisted_directories`"
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%project}}', 'prompt_instance_copy_format');
    }
}
