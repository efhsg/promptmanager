# Coding Standards

## Instruction Precedence

If instructions conflict, follow this order:
1. `.claude/rules/` (this directory)
2. `CLAUDE.md` (root)
3. `.claude/codebase_analysis.md`

## Non-negotiables

- Apply SOLID/DRY only when it eliminates duplication or tight coupling; stop when the goal is met.
- Code comments: CLASS - explain intent (2-3 lines); FUNCTIONS - PHPDoc with `@throws` only.
- Make the smallest change that solves the problem; avoid unrelated refactors.
- If required context (file/config/dependency) is missing to proceed safely, pause and ask for it.
- No unnecessary curly braces.
- Never use FQCNs inside method bodies; always `use` imports at the top.
- NEVER use `declare(strict_types=1)`.

## PHP 8.2 / Yii2

- PSR-12, full type hints on params/returns/properties.
- Class names use StudlyCase; view files use snake-case in `yii/views/<controller>/`.
- Prefer DI in services over `Yii::$app` access.
- Avoid explanatory comments; docblocks only for Yii2 magic or `@throws`.
- ActiveRecord columns are magic attributes: don't add typed public properties for DB columns.

## Non-PHP

- JavaScript in `npm/src` follows ES2019 with 2-space indentation.
- Docker/Compose YAML uses clean 2-space indentation.
