# Project Rules (PromptManager)

This document is the source of truth for project behavior. It overrides conflicting documentation or tool instructions.

## Non-negotiables

- Apply SOLID/DRY only when it eliminates duplication or tight coupling; stop when the goal is met.
- Code comments: CLASS - explain intent (2-3 lines); FUNCTIONS - PHPDoc with `@throws` only.
- All new code must be fully type-hinted and PSR-12 compliant.
- If any file, config, or dependency is missing, stop and request it.
- No unnecessary curly braces.
- Never use FQCNs inside method bodies; always `use` imports at the top.
- NEVER use `declare(strict_types=1)`.

## Code style (PHP 8.2 / Yii2)

- PSR-12, full type hints on params/returns/properties.
- Use `use` imports; avoid FQCNs inline.
- Prefer DI in services over `Yii::$app` access.
- Avoid explanatory comments; docblocks only for Yii2 magic or `@throws`.
- ActiveRecord columns are magic attributes: don't add typed public properties for DB columns.

## Services & architecture

- Name services after their responsibility (e.g., `CopyFormatConverter`, not `CopyService`).
- If a service exceeds ~300 lines, consider splitting by responsibility.
- Prefer typed objects/DTOs over associative arrays for complex data structures.
- Query logic belongs in Query classes (`models/query/`), not services.
- External API integrations belong in dedicated client classes (`clients/`).

## Migrations

- Use `{{%table_name}}` syntax for table prefix support.
- Migrations must be atomic and reversible with `up()` and `down()` methods.

## Dependencies

- Coordinate before adding Composer dependencies or running `composer update`.

## Repo hygiene

- Never stage `RULES.md`, `AGENTS.md`, or `.claude/` contents (local operating instructions).
