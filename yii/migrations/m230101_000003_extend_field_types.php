<?php
/** @noinspection DuplicatedCode */

use yii\db\Migration;
use common\constants\FieldConstants;

class m230101_000003_extend_field_types extends Migration
{
    public function safeUp(): void
    {
        $enumValues = implode(',', array_map(fn($type) => "'$type'", FieldConstants::TYPES));
        $this->alterColumn('{{%field}}', 'type', "ENUM($enumValues) NOT NULL");
    }

    public function safeDown(): void
    {
        $oldTypes = ['text', 'select', 'multi-select'];
        $enumValuesOld = implode(',', array_map(fn($type) => "'$type'", $oldTypes));
        $this->alterColumn('{{%field}}', 'type', "ENUM($enumValuesOld) NOT NULL");
    }
}
