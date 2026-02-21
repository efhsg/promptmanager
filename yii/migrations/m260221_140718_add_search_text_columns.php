<?php

namespace app\migrations;

use yii\db\Migration;

class m260221_140718_add_search_text_columns extends Migration
{
    private const TABLES = [
        '{{%context}}' => 'order',
        '{{%prompt_template}}' => 'template_body',
        '{{%prompt_instance}}' => 'final_prompt',
        '{{%note}}' => 'content',
        '{{%field}}' => 'render_label',
    ];

    public function safeUp(): void
    {
        foreach (self::TABLES as $table => $afterColumn) {
            $this->addColumn(
                $table,
                'search_text',
                $this->text()->null()->after($afterColumn)
            );
        }
    }

    public function safeDown(): void
    {
        foreach (self::TABLES as $table => $_) {
            $this->dropColumn($table, 'search_text');
        }
    }
}
