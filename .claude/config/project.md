# Project Configuration

Single source of truth for project-specific operations.

## Environment

| Setting | Value |
|---------|-------|
| Container | `pma_yii` |
| PHP | 8.2 |
| Framework | Yii 2 |
| Test Framework | Codeception |
| Rich Text | Quill Delta JSON |

## Commands

### Docker

```bash
# Start containers
docker compose up -d

# Shell into container
docker exec -it pma_yii bash

# View logs
docker logs pma_yii

# Restart container
docker restart pma_yii
```

### Linter

```bash
# Check all files (dry run)
./linter.sh check

# Fix all files
./linter.sh fix

# Check staged files only (for pre-commit)
./linter-staged.sh check

# Fix staged files only
./linter-staged.sh fix
```

### Tests

```bash
# Run all unit tests
docker exec pma_yii vendor/bin/codecept run unit

# Run single test file
docker exec pma_yii vendor/bin/codecept run unit tests/unit/path/FooTest.php

# Run single test method
docker exec pma_yii vendor/bin/codecept run unit services/MyServiceTest:testMethod
```

### Database

```bash
# Run migrations (both schemas required)
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# Migration status
docker exec pma_yii yii migrate/history --migrationNamespaces=app\\migrations
```

### Frontend

```bash
# Build frontend assets (after Quill/JS changes)
docker compose run --entrypoint bash pma_npm -c "npm run build-and-minify"

# Watch mode (auto-rebuild editor-init.js on changes)
cd npm && npm run watch
```

## File Structure

| Type | Location |
|------|----------|
| Controllers | `yii/controllers/` |
| Services | `yii/services/` |
| Models | `yii/models/` |
| Query Classes | `yii/models/query/` |
| Views | `yii/views/` |
| Migrations | `yii/migrations/` |
| Tests | `yii/tests/` |
| Fixtures | `yii/tests/fixtures/` |
| Identity Module | `yii/modules/identity/` |
| Enums | `yii/common/enums/` |
| Constants | `yii/common/constants/` |
| Exceptions | `yii/exceptions/` |
| RBAC Rules | `yii/rbac/` |
| Widgets | `yii/widgets/` |
| Presenters | `yii/presenters/` |
| Components | `yii/components/` |
| Helpers | `yii/helpers/` |
| Frontend Source | `npm/src/` |
| Compiled Assets | `yii/web/` |

## Test Path Mapping

Source files map to test files by mirroring the directory structure:

| Source | Test |
|--------|------|
| `yii/services/FooService.php` | `yii/tests/unit/services/FooServiceTest.php` |
| `yii/models/Foo.php` | `yii/tests/unit/models/FooTest.php` |
| `yii/models/query/FooQuery.php` | `yii/tests/unit/models/query/FooQueryTest.php` |
| `yii/helpers/FooHelper.php` | `yii/tests/unit/helpers/FooHelperTest.php` |
| `yii/presenters/FooPresenter.php` | `yii/tests/unit/presenters/FooPresenterTest.php` |
| `yii/widgets/FooWidget.php` | `yii/tests/unit/widgets/FooWidgetTest.php` |

## Key Domain Concepts

| Concept | Description |
|---------|-------------|
| Project | Container for prompts, fields, contexts, and templates |
| Context | Reusable text block for prompt composition |
| Field | Variable placeholder (global, project, or external) |
| PromptTemplate | Template with placeholders for generating prompts |
| PromptInstance | Generated prompt from a template |
| Note | Workspace for prompt composition and editing (supports parent/child hierarchy) |

## Field Types

Defined in `FieldConstants::TYPES` (`yii/common/constants/FieldConstants.php`).

## Placeholder Types

| Type | Syntax | Scope |
|------|--------|-------|
| Global | `GEN:{{name}}` | All projects (project_id = null) |
| Project | `PRJ:{{name}}` | Single project |
| External | `EXT:{{label:name}}` | Linked from another project |

## RBAC Owner Rules

| Rule | Validates |
|------|-----------|
| `ProjectOwnerRule` | User owns the project |
| `ContextOwnerRule` | User owns the context's project |
| `FieldOwnerRule` | User owns the field's project |
| `PromptTemplateOwnerRule` | User owns the template's project |
| `PromptInstanceOwnerRule` | User owns the instance's project |
| `NoteOwnerRule` | User owns the note's project |
