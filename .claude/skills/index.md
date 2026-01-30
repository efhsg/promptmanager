# Skills Index

Skills are reusable knowledge contracts with templates, patterns, and completion criteria. Commands reference these skills for consistent implementation.

## Project Configuration

For commands, paths, and environment: `.claude/config/project.md`

## How to Use

1. **Read rules first** — `.claude/rules/` applies to everything
2. **Scan this index** — find relevant skills for your task
3. **Load only needed skills** — minimize context
4. **Follow skill contracts** — inputs, outputs, DoD
5. **Create skills for gaps** — if behavior isn't covered, write a skill
6. **Update this index** — keep the registry current

## Skills by Category

### Creation Skills

| Skill | Description | Contract |
|-------|-------------|----------|
| Model | ActiveRecord model with query class + naming conventions | `skills/model.md` |
| Service | Business logic service class | `skills/service.md` |
| Form | Form model with validation | `skills/form.md` |
| Enum | String-backed enum | `skills/enum.md` |
| Frontend Design | UI components with Bootstrap/Quill | `skills/frontend-design.md` |
| Migration | Database migration | `skills/migration.md` |
| Search | Search model for filtering/listing | `skills/search.md` |
| Controller Action | Controller + AJAX patterns, RBAC config | `skills/controller-action.md` |
| Test | Unit test with Codeception | `skills/test.md` |
| Error Handling | Exception types, logging, error patterns | `skills/error-handling.md` |

### Workflow Skills

| Skill | Description | Contract |
|-------|-------------|----------|
| Onboarding | Quick start guide for new sessions | `skills/onboarding.md` |
| New Branch | Create feature/fix branch | `skills/new-branch.md` |
| Refactor | Structural improvements without behavior change | `skills/refactor.md` |

### Validation Skills

| Skill | Description | Contract |
|-------|-------------|----------|
| Review Changes | Two-phase code review (defects → design) | `skills/review-changes.md` |
| Triage Review | Critically assess external reviews against codebase | `skills/triage-review.md` |
| Improve Prompt | Analyze and improve agent prompt files | `skills/improve-prompt.md` |

## Commands Reference

Commands are thin executable wrappers that invoke skills:

| Command | Invokes Skill | Purpose |
|---------|---------------|---------|
| `/new-model` | `skills/model.md` | Create model + query class |
| `/new-service` | `skills/service.md` | Create service class |
| `/new-form` | `skills/form.md` | Create form model |
| `/new-enum` | `skills/enum.md` | Create enum |
| `/new-migration` | `skills/migration.md` | Create migration |
| `/new-search` | `skills/search.md` | Create search model |
| `/new-controller-action` | `skills/controller-action.md` | Add controller action |
| `/new-test` | `skills/test.md` | Create unit test |
| `/new-tests-staged` | `skills/test.md` | Batch test creation |
| `/new-branch` | `skills/new-branch.md` | Create feature/fix branch |
| `/onboarding` | `skills/onboarding.md` | Quick start for new sessions |
| `/refactor` | `skills/refactor.md` | Refactor code without behavior change |
| `/check-standards` | (rules-based) | Validate code standards |
| `/review-changes` | `skills/review-changes.md` | Review code changes |
| `/triage-review` | `skills/triage-review.md` | Triage external code review |
| `/improve-prompt` | `skills/improve-prompt.md` | Improve agent prompt file |
| `/audit-config` | (workflow) | Audit config completeness and consistency |
| `/finalize-changes` | (workflow) | Lint, test, commit prep |
| `/cp` | (workflow) | Commit and push to origin |
| `/analyze-codebase` | (workflow) | Generate documentation |
| `/refactor-plan` | (workflow) | Create refactoring plan |

## Naming Conventions

- **new-*** — Creates new resources
- **check-*** — Validates against rules
- **analyze-*** — Generates analysis/documentation
- **finalize-*** — Completes workflows
