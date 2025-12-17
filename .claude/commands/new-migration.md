---
allowed-tools: Read, Edit, Write, Bash(docker exec:*)
description: Create a database migration following PromptManager patterns
---

# Create Migration

Create a database migration following PromptManager patterns.

## Patterns

- Location: `yii/migrations/`
- Naming: `m<YYMMDD>_<HHMMSS>_<description>.php`
- Namespace: `app\migrations`
- Use `{{%table_name}}` for table prefix support
- Extend `yii\db\Migration`

## Example Structure

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

## Common Column Patterns

```php
// Primary key
'id' => $this->primaryKey(),

// Foreign key (user owner)
'user_id' => $this->integer()->notNull(),

// Timestamps (integer for Unix timestamps)
'created_at' => $this->integer()->notNull(),
'updated_at' => $this->integer()->notNull(),
'deleted_at' => $this->integer()->null(),

// Status enum (stored as string)
'status' => $this->string(32)->notNull(),

// Optional foreign key
'related_id' => $this->integer()->null(),

// Text fields
'description' => $this->text()->null(),
'content' => $this->text()->null(),
```

## Running Migrations

```bash
# Apply migrations
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0

# Apply to test database
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
```

## Task

Create migration: $ARGUMENTS

Describe the table structure needed.
