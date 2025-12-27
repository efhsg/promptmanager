# Development Workflow

## Before Coding

1. Read project rules in `docs/rules/`
2. Check `docs/skills/index.md` for relevant skills
3. Understand existing patterns in codebase
4. Plan changes before implementing

## Migrations

- Use `{{%table_name}}` syntax for table prefix support.
- Migrations must be atomic and reversible with `up()` and `down()` methods.
- Migration filenames use a timestamp prefix (e.g., `m251123_123456_add_new_table.php`).
- Include a short class-level comment describing the migration's purpose.
- Run on both schemas:
  ```bash
  docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
  docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
  ```

## Transactions

- Wrap multi-model saves in `Yii::$app->db->beginTransaction()`.
- Always `rollBack()` on exception, `commit()` on success.
- Keep transaction scope minimal; don't wrap read-only operations.

## Error Handling

- Services throw domain-specific exceptions; controllers catch and show user-friendly messages.
- Never silently swallow exceptions; log or re-throw.
- Use `Yii::error()` for unexpected failures, `Yii::warning()` for recoverable issues.

## Dependencies

- Coordinate before adding Composer dependencies or running `composer update`.

## Repo Hygiene

- New environment variables must be documented in `.env.example`.
- Don't hand-edit compiled frontend assets in `yii/web`; change sources in `npm/` and rebuild.

## Definition of Done

- Change is minimal and scoped to the request
- Change follows `docs/rules/`
- Targeted unit test added/updated when behavior changes
- Migrations run on both `yii` and `yii_test` schemas
