<?php

namespace app\migrations;

use yii\db\Migration;

class m260130_105033_rename_scratch_pad_summation_to_response extends Migration
{
    public function safeUp(): void
    {
        $this->renameColumn('{{%scratch_pad}}', 'summation', 'response');
    }

    public function safeDown(): void
    {
        $this->renameColumn('{{%scratch_pad}}', 'response', 'summation');
    }
}
