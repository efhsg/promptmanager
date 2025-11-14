<?php

namespace app\migrations;

use yii\db\Migration;

class m250610_000002_add_fk_prompt_instance_template extends Migration
{
    private const TABLE_PROMPT_INSTANCE = '{{%prompt_instance}}';
    private const TABLE_PROMPT_TEMPLATE = '{{%prompt_template}}';
    private const FK_NAME = 'fk_prompt_instance_prompt_template';

    public function safeUp(): void
    {
        $table = $this->db->getTableSchema(self::TABLE_PROMPT_INSTANCE, true);

        if ($table === null) {
            // Table does not exist in this environment; nothing to do.
            return;
        }

        $targetRefTableRaw = $this->db->schema->getRawTableName(self::TABLE_PROMPT_TEMPLATE);

        // If there is already *any* FK on template_id that points to prompt_template, we skip.
        foreach ($table->foreignKeys as $name => $fk) {
            // $fk[0] = referenced table name, rest are column mappings
            $currentRefTableRaw = $this->db->schema->getRawTableName($fk[0] ?? '');
            if ($currentRefTableRaw === $targetRefTableRaw && isset($fk['template_id'])) {
                // Already has a FK from template_id -> prompt_template(id)
                return;
            }
        }

        $this->addForeignKey(
            self::FK_NAME,
            self::TABLE_PROMPT_INSTANCE,
            'template_id',
            self::TABLE_PROMPT_TEMPLATE,
            'id',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $table = $this->db->getTableSchema(self::TABLE_PROMPT_INSTANCE, true);

        if ($table === null) {
            return;
        }

        if (!isset($table->foreignKeys[self::FK_NAME])) {
            return;
        }

        $this->dropForeignKey(
            self::FK_NAME,
            self::TABLE_PROMPT_INSTANCE
        );
    }
}
