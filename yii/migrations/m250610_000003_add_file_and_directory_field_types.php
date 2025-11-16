<?php

declare(strict_types=1);

namespace app\migrations;

use common\constants\FieldConstants;
use yii\db\Migration;

class m250610_000003_add_file_and_directory_field_types extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%project}}',
            'root_directory',
            $this->string(1024)->null()->after('description')
        );

        $enumValues = implode(
            ',',
            array_map(
                static fn (string $type): string => "'$type'",
                FieldConstants::TYPES
            )
        );

        $this->alterColumn('{{%field}}', 'type', "ENUM($enumValues) NOT NULL");
    }

    public function safeDown(): void
    {
        $oldTypes = [
            'text',
            'select',
            'multi-select',
            'code',
            'select-invert',
        ];

        $enumValuesOld = implode(
            ',',
            array_map(
                static fn (string $type): string => "'$type'",
                $oldTypes
            )
        );

        $this->alterColumn('{{%field}}', 'type', "ENUM($enumValuesOld) NOT NULL");

        $this->dropColumn('{{%project}}', 'root_directory');
    }
}
