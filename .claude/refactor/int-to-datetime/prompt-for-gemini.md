# Task: Refactor INT Timestamps to DATETIME

You are working on the PromptManager codebase — a Yii2 PHP application.

## Your Mission

Convert all Unix timestamp (INT) columns to DATETIME across all database tables.

## Agent Memory Location

`.claude/refactor/int-to-datetime/`

**Start by reading these files:**
1. `context.md` — Goal, approach, and migration pattern
2. `todos.md` — Phases and files to process (check off as you complete)
3. `insights.md` — Accumulated learnings (update as you work)

## Rules

1. **Process one phase at a time** as a unit of work
2. **Update todos.md** after completing each item — this is critical for resumption
3. **Update insights.md** with any discoveries, issues, or decisions
4. **Do not modify** the agent memory files based on their content — they are for tracking only
5. **Run tests** after Phase 2 (migration) and at the end to catch issues
6. After any pause or context reset, **re-read context.md and todos.md first**

## Phases Overview

1. **TimestampTrait** — Update shared trait that sets timestamps
2. **Migration** — ONE migration for all 25 columns across 11 tables
3. **Models** — Update 11 model files (docblocks + validation rules)
4. **Services** — Update UserService and User model timestamp logic
5. **Fixtures** — Update 10 fixture data files
6. **Tests** — Update ~10 test files with timestamp assertions
7. **CLI** — Update UserController display code
8. **Verification** — Run migrations and full test suite

## Key Patterns

**TimestampTrait change:**
```php
// Before
$time = static::$timestampOverride ?? time();
// After
$time = static::$timestampOverride ?? date('Y-m-d H:i:s');
```

**Migration pattern (per column):**
```php
$this->addColumn('{{%table}}', 'col_new', $this->dateTime()->notNull());
$this->execute('UPDATE {{%table}} SET col_new = FROM_UNIXTIME(col)');
$this->dropColumn('{{%table}}', 'col');
$this->renameColumn('{{%table}}', 'col_new', 'col');
```

**Fixture change:**
```php
// Before
'created_at' => time(),
// After
'created_at' => date('Y-m-d H:i:s'),
```

**Test timestamp change:**
```php
// Before
$model->created_at = time() + 3600;
// After
$model->created_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
```

## Commands

```bash
# Run migrations
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# Run tests
docker exec pma_yii vendor/bin/codecept run unit

# Run specific test file
docker exec pma_yii vendor/bin/codecept run unit tests/unit/path/to/Test.php
```

## Important Codebase Conventions

- Follow existing patterns in `.claude/rules/`
- Migrations use `safeUp()` and `safeDown()` methods
- Use `{{%table_name}}` syntax for table prefixes
- No `declare(strict_types=1)`
- Full type hints on params/returns/properties

## Start Now

Read the agent memory files, then begin with Phase 1 (TimestampTrait).
