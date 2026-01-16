<?php

namespace app\migrations;

use yii\db\Migration;

/**
 * Class m260116_000003_convert_timestamps_to_datetime
 */
class m260116_000003_convert_timestamps_to_datetime extends Migration
{
    private array $tables = [
        '{{%project}}' => ['created_at', 'updated_at', 'deleted_at'],
        '{{%context}}' => ['created_at', 'updated_at'],
        '{{%field}}' => ['created_at', 'updated_at'],
        '{{%field_option}}' => ['created_at', 'updated_at'],
        '{{%prompt_template}}' => ['created_at', 'updated_at'],
        '{{%prompt_instance}}' => ['created_at', 'updated_at'],
        '{{%user_preference}}' => ['created_at', 'updated_at'],
        '{{%project_linked_project}}' => ['created_at', 'updated_at'],
        '{{%scratch_pad}}' => ['created_at', 'updated_at'],
        '{{%user}}' => ['created_at', 'updated_at', 'deleted_at', 'access_token_expires_at'],
    ];

    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        foreach ($this->tables as $table => $columns) {
            foreach ($columns as $column) {
                // Determine if column should be NOT NULL based on name
                // created_at, updated_at -> NOT NULL
                // deleted_at, access_token_expires_at -> NULL
                $isNullable = in_array($column, ['deleted_at', 'access_token_expires_at']);

                $tempColumn = $column . '_new';
                // Add new DATETIME column (nullable initially to allow update)
                $this->addColumn($table, $tempColumn, $this->dateTime()->null());

                // Convert values from INT to DATETIME
                $this->execute("UPDATE $table SET $tempColumn = FROM_UNIXTIME($column) WHERE $column IS NOT NULL");

                // Drop old INT column
                $this->dropColumn($table, $column);

                // Rename new DATETIME column to original name
                $this->renameColumn($table, $tempColumn, $column);

                // Apply Nullable/NotNull constraint
                if (!$isNullable) {
                    $this->alterColumn($table, $column, $this->dateTime()->notNull());
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        foreach ($this->tables as $table => $columns) {
            foreach ($columns as $column) {
                $isNullable = in_array($column, ['deleted_at', 'access_token_expires_at']);

                $tempColumn = $column . '_new';
                // Add new INT column
                $this->addColumn($table, $tempColumn, $this->integer()->null());

                // Convert values from DATETIME to INT
                $this->execute("UPDATE $table SET $tempColumn = UNIX_TIMESTAMP($column) WHERE $column IS NOT NULL");

                // Drop old DATETIME column
                $this->dropColumn($table, $column);

                // Rename new INT column to original name
                $this->renameColumn($table, $tempColumn, $column);

                // Apply Nullable/NotNull constraint
                if (!$isNullable) {
                    $this->alterColumn($table, $column, $this->integer()->notNull());
                }
            }
        }
    }
}
