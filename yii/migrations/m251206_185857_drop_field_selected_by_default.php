<?php

namespace app\migrations;

use yii\db\Migration;

class m251206_185857_drop_field_selected_by_default extends Migration
{
    public function safeUp(): void
    {
        $this->dropColumn('field', 'selected_by_default');
    }

    public function safeDown(): void
    {
        $this->addColumn('field', 'selected_by_default', $this->boolean()->notNull()->defaultValue(false));
    }
}
