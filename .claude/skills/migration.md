# Migration Skill

Create database migrations following PromptManager patterns.

## Persona

Senior PHP Developer with Yii2 and MySQL expertise. Focus on atomic, reversible migrations.

## When to Use

- Creating new tables
- Adding/modifying columns
- Seeding data
- Schema changes

## Inputs

- `description`: What the migration does
- `type`: create_table, add_column, update_data, seed
- `table`: Target table name
- `columns`: Column definitions (for create/add)

## File Location

- Migration: `yii/migrations/m<YYMMDD>_<HHMMSS>_<description>.php`

Generate timestamp with: `date +%y%m%d_%H%M%S`

## Create Table Template

```php
<?php

namespace app\migrations;

use yii\db\Migration;

class m241217_120000_create_<table_name>_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%<table_name>}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_<table_name>_user',
            '{{%<table_name>}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%<table_name>}}');
    }
}
```

## Add Column Template

```php
<?php

namespace app\migrations;

use yii\db\Migration;

class m241217_120000_add_<column>_to_<table> extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%<table>}}',
            '<column>',
            $this->string(255)->null()->after('existing_column')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%<table>}}', '<column>');
    }
}
```

## Common Column Types

```php
'id' => $this->primaryKey(),
'user_id' => $this->integer()->notNull(),
'created_at' => $this->integer()->notNull(),
'updated_at' => $this->integer()->notNull(),
'deleted_at' => $this->integer()->null(),
'status' => $this->string(32)->notNull(),
'description' => $this->text()->null(),
```

## Running Migrations

See `.claude/rules/workflow.md` for migration commands.

## Key Patterns

- Use `{{%table_name}}` for table prefix support
- `safeUp()` and `safeDown()` (not `up()`/`down()`)
- Atomic and reversible
- Run on both app and test schemas
- Timestamps as integers (Unix time)

## Definition of Done

- Migration created with correct namespace
- safeUp() and safeDown() implemented
- Uses table prefix syntax
- Runs successfully on both schemas
