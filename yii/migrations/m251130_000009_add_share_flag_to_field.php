<?php

namespace app\migrations;

use yii\db\Migration;

class m251130_000009_add_share_flag_to_field extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%field}}',
            'share',
            $this->boolean()->notNull()->defaultValue(false)->after('selected_by_default')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%field}}', 'share');
    }
}
