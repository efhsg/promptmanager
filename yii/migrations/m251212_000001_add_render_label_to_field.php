<?php

namespace app\migrations;

use yii\db\Migration;

class m251212_000001_add_render_label_to_field extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%field}}',
            'render_label',
            $this->boolean()->notNull()->defaultValue(false)->after('label')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%field}}', 'render_label');
    }
}
