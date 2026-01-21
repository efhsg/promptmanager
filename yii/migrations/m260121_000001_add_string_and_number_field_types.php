<?php

namespace app\migrations;

use common\constants\FieldConstants;
use yii\db\Migration;

/**
 * Updates the field.type ENUM to include 'string' and 'number' types.
 *
 * These types were added to FieldConstants::TYPES but the original migration
 * that created the ENUM was run before they existed.
 */
class m260121_000001_add_string_and_number_field_types extends Migration
{
    public function safeUp(): void
    {
        $enumValues = implode(
            ',',
            array_map(
                static fn(string $type): string => "'$type'",
                FieldConstants::TYPES
            )
        );

        $this->alterColumn('{{%field}}', 'type', "ENUM($enumValues) NOT NULL");
    }

    public function safeDown(): void
    {
        $oldTypes = ['text', 'select', 'multi-select', 'code', 'select-invert', 'file', 'directory'];

        $enumValuesOld = implode(
            ',',
            array_map(
                static fn(string $type): string => "'$type'",
                $oldTypes
            )
        );

        $this->alterColumn('{{%field}}', 'type', "ENUM($enumValuesOld) NOT NULL");
    }
}
