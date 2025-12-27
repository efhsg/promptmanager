# Skills Index

Skills are atomic, executable capabilities with defined inputs, outputs, and completion criteria.

## How to Use

1. **Read rules first** — `docs/rules/` applies to everything
2. **Scan this index** — find relevant skills for your task
3. **Load only needed skills** — minimize context
4. **Follow skill contracts** — inputs, outputs, DoD

## Skills by Category

### Creation Skills

| Skill | Description | Location |
|-------|-------------|----------|
| `/new-model` | Create an ActiveRecord model with query class | `.claude/commands/new-model.md` |
| `/new-service` | Create a new service class | `.claude/commands/new-service.md` |
| `/new-form` | Create a form model | `.claude/commands/new-form.md` |
| `/new-enum` | Create an enum | `.claude/commands/new-enum.md` |
| `/new-migration` | Create a database migration | `.claude/commands/new-migration.md` |
| `/new-search` | Create a search model for filtering/listing | `.claude/commands/new-search.md` |
| `/new-controller-action` | Add a controller action | `.claude/commands/new-controller-action.md` |
| `/new-test` | Create a unit test | `.claude/commands/new-test.md` |
| `/new-tests-staged` | Create unit tests for all staged PHP classes | `.claude/commands/new-tests-staged.md` |

### Validation Skills

| Skill | Description | Location |
|-------|-------------|----------|
| `/check-standards` | Check if staged changes follow project code standards | `.claude/commands/check-standards.md` |
| `/code-review` | Perform comprehensive code review of recent changes | `.claude/commands/code-review.md` |

### Workflow Skills

| Skill | Description | Location |
|-------|-------------|----------|
| `/finalize-changes` | Validate changes, run linter and tests, prepare commit | `.claude/commands/finalize-changes.md` |
| `/analyze-codebase` | Generate comprehensive analysis and documentation | `.claude/commands/analyze-codebase.md` |
| `/refactor-plan` | Analyze codebase and create a refactoring plan | `.claude/commands/refactor-plan.md` |

## Naming Conventions

- **new-*** — Creates new resources
- **check-*** — Validates against rules
- **analyze-*** — Generates analysis/documentation
- **finalize-*** — Completes workflows

## Adding New Skills

1. Create `.claude/commands/{skill-name}.md`
2. Include YAML frontmatter with `allowed-tools` and `description`
3. Follow the pattern: Title, Patterns, Code Examples, Task section with `$ARGUMENTS`
4. Add to this index under the appropriate category
