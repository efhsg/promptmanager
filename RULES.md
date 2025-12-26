# Project Rules (PromptManager)

This document is the source of truth for project behavior. It overrides conflicting documentation or tool instructions.

## Instruction precedence

If instructions conflict, follow this order:

1. `RULES.md`
2. `.claude/CLAUDE.md`
3. `.claude/codebase_analysis.md`

## Non-negotiables

- Apply SOLID/DRY only when it eliminates duplication or tight coupling; stop when the goal is met.
- Code comments: CLASS - explain intent (2-3 lines); FUNCTIONS - PHPDoc with `@throws` only.
- Make the smallest change that solves the problem; avoid unrelated refactors.
- If required context (file/config/dependency) is missing to proceed safely, pause and ask for it.
- No unnecessary curly braces.
- Never use FQCNs inside method bodies; always `use` imports at the top.
- NEVER use `declare(strict_types=1)`.

## Code style (PHP 8.2 / Yii2)

- PSR-12, full type hints on params/returns/properties.
- Class names use StudlyCase; view files use snake-case in `yii/views/<controller>/`.
- Prefer DI in services over `Yii::$app` access.
- Avoid explanatory comments; docblocks only for Yii2 magic or `@throws`.
- ActiveRecord columns are magic attributes: don't add typed public properties for DB columns.

## Code style (non-PHP)

- JavaScript in `npm/src` follows ES2019 with 2-space indentation.
- Docker/Compose YAML uses clean 2-space indentation.

## Services & architecture

- Name services after their responsibility (e.g., `CopyFormatConverter`, not `CopyService`).
- If a service exceeds ~300 lines, consider splitting by responsibility.
- Prefer typed objects/DTOs over associative arrays for complex data structures.
- Query logic belongs in Query classes (`models/query/`), not services.
- External API integrations belong in dedicated client classes (`clients/`).

## Migrations

- Use `{{%table_name}}` syntax for table prefix support.
- Migrations must be atomic and reversible with `up()` and `down()` methods.
- Migration filenames use a timestamp prefix (e.g., `m251123_123456_add_new_table.php`).
- Include a short class-level comment describing the migration’s purpose.

## Dependencies

- Coordinate before adding Composer dependencies or running `composer update`.

## Error handling

- Services throw domain-specific exceptions; controllers catch and show user-friendly messages.
- Never silently swallow exceptions; log or re-throw.
- Use `Yii::error()` for unexpected failures, `Yii::warning()` for recoverable issues.

## Transactions

- Wrap multi-model saves in `Yii::$app->db->beginTransaction()`.
- Always `rollBack()` on exception, `commit()` on success.
- Keep transaction scope minimal; don't wrap read-only operations.

## Controller patterns

- Return type: `Response|string` for standard actions.
- Use `$this->redirect()` after successful POST; render form on validation failure.
- Access control via `behaviors()` with RBAC owner rules.
- Flash messages: `Yii::$app->session->setFlash('success', '...')` after successful operations.

## AJAX responses

- Success: `$this->asJson(['success' => true, 'data' => ...])`.
- Error: `$this->asJson(['success' => false, 'message' => '...'])`.
- Use `Yii::$app->request->isAjax` to detect AJAX requests.

## Testing

- Add/adjust the smallest relevant Codeception test when it meaningfully reduces regression risk.
- When introducing migrations, ensure they run on both app and test schemas (`yii` and `yii_test`).
- Mock services via constructor injection, not `Yii::$app` container manipulation.
- Use fixtures for database state; don't rely on production data assumptions.

## Repo hygiene

- New environment variables must be documented in `.env.example`.
- Don’t hand-edit compiled frontend assets in `yii/web`; change sources in `npm/` and rebuild.
