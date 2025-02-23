<?php
use yii\db\Migration;
use common\constants\FieldConstants;

class m230102_000004_add_select_invert_field_type extends Migration
{
    public function safeUp(): void
    {
        $enumValues = implode(',', array_map(fn(string $type): string => "'$type'", FieldConstants::TYPES));
        $this->alterColumn('{{%field}}', 'type', "ENUM($enumValues) NOT NULL");
    }

    public function safeDown(): void
    {
        $oldTypes = array_filter(FieldConstants::TYPES, fn(string $type): bool => $type !== 'select-invert');
        $enumValuesOld = implode(',', array_map(fn(string $type): string => "'$type'", $oldTypes));
        $this->alterColumn('{{%field}}', 'type', "ENUM($enumValuesOld) NOT NULL");
    }
}
