# Development Workflow

## Skill-Driven Development

For every task:

1. Read project rules first — `.claude/rules/` applies to everything
2. Check `.claude/skills/index.md` — find relevant skills for your task
3. Load only needed skills — minimize context
4. Follow DoD — skill is done when Definition of Done passes
5. Create skills for gaps — if behavior isn't covered, write a skill
6. Update index — keep skill registry in `skills/index.md` current

## Before Coding

1. Read project rules in `.claude/rules/`
2. Check `.claude/skills/index.md` for relevant skills
3. Understand existing patterns in codebase
4. Plan changes before implementing

## Design Directory per Feature

For complex features, create a design directory:

```
.claude/design/[feature-name]/
├── spec.md         # Functional specification
├── plan.md         # Technical plan
└── insights.md     # Decisions, edge cases
```

### Implementation Memory

During longer implementations, track progress:

```
.claude/design/[feature-name]/impl/
├── context.md      # Goal, scope, key references
├── todos.md        # Steps checklist
└── insights.md     # Findings, deviations
```

This preserves context across session resets.

## Migrations

- Use `{{%table_name}}` syntax for table prefix support.
- Use `safeUp()` and `safeDown()` methods (not `up()`/`down()`) for automatic transaction handling.
- Migration filenames use a timestamp prefix (e.g., `m251123_123456_add_new_table.php`).
- Run on both schemas (vanuit `/var/www/html/yii`):
  ```bash
  ./yii migrate --migrationNamespaces=app\\migrations --interactive=0
  ./yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
  ```

## Transactions

- Wrap multi-model saves in `Yii::$app->db->beginTransaction()`.
- Always `rollBack()` on exception, `commit()` on success.
- Keep transaction scope minimal; don't wrap read-only operations.

## Error Handling

- Services throw domain-specific exceptions; controllers catch and show user-friendly messages.
- Never silently swallow exceptions; log or re-throw.
- Use `Yii::error()` for unexpected failures, `Yii::warning()` for recoverable issues.
- See `skills/error-handling.md` for detailed patterns.

## Dependencies

- Coordinate before adding Composer dependencies or running `composer update`.

## Repo Hygiene

- New environment variables must be documented in `.env.example`.
- Don't hand-edit compiled frontend assets in `yii/web`; change sources in `npm/` and rebuild.

## Code Review Checklist

Before approving changes:

- [ ] Follows folder structure in `architecture.md`
- [ ] Type hints present on params/returns/properties
- [ ] Query methods use naming conventions (byX, withX, hasX)
- [ ] Tests for new/changed logic
- [ ] No silent failures (exceptions logged or thrown)
- [ ] Security rules followed (RBAC, input validation, Html::encode)
- [ ] Commit message follows format in `commits.md`
- [ ] Migrations run on both schemas (if applicable)
