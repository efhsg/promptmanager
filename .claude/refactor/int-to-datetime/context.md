# INT to DATETIME Refactoring

## Goal

Convert all Unix timestamp (INT) columns to DATETIME for better readability in queries and database dumps.

## Scope

All `*_at` columns across all tables that are currently stored as INT.

## Approach

For each table:
1. Create migration with proper data conversion (`FROM_UNIXTIME` / `UNIX_TIMESTAMP`)
2. Update model `@property` docblocks and validation rules
3. Update `TimestampBehavior` configuration to use `NOW()` expressions
4. Update any code that reads/writes these fields
5. Update related tests

## Key Files

- Models: `yii/models/*.php`, `yii/modules/identity/models/User.php`
- Migrations: `yii/migrations/`
- Tests: `yii/tests/unit/`

## Migration Pattern

```php
public function safeUp(): void
{
    // For each column: add temp → convert → drop → rename
    $this->addColumn('{{%table}}', 'column_new', $this->dateTime()->notNull());
    $this->execute('UPDATE {{%table}} SET column_new = FROM_UNIXTIME(column) WHERE column IS NOT NULL');
    $this->dropColumn('{{%table}}', 'column');
    $this->renameColumn('{{%table}}', 'column_new', 'column');
}
```

## TimestampBehavior Update

```php
'timestamp' => [
    'class' => TimestampBehavior::class,
    'value' => new Expression('NOW()'),
],
```

## Timezone

Using server-local time (MySQL `FROM_UNIXTIME` uses server timezone). All comparisons use `date('Y-m-d H:i:s')`.
