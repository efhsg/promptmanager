# CLAUDE.md

Yii2-based LLM prompt management system. Manages reusable prompt components (projects, contexts, fields, templates) and generates final prompts via placeholder substitution using Quill Delta format.

**Stack:** PHP 8.2+, Yii2 2.0.x, MySQL 8.x, Quill.js (Delta format), Codeception, Docker Compose

## Code Standards (STRICT)

### PHP Style
- PSR-12 compliant with full type hints on ALL parameters, return types, and properties
- Use `use` statements at top; NEVER fully-qualified names in method bodies
- **❌ NEVER** add `declare(strict_types=1);`
- **❌ NEVER** add `@param`/`@return` PHPDoc (only `@throws` allowed)
- **❌ NEVER** edit files in `yii/web/` (compiled from `npm/`)

### Migrations & Database Management
Database schema changes are managed through Yii migrations located in yii/migrations or module-specific migrations directories.
Each migration file is named with a timestamp prefix (e.g., m251123_123456_add_new_table.php) and includes up() and down() methods.
Migrations should be atomic, reversible, and include comments explaining their purpose at the class level.

### Architecture
- **Service Layer (REQUIRED):** ALL business logic in `yii/services/`, controllers stay thin (HTTP only)
- Services injected via constructor DI; models handle data structure only
- Apply SOLID/DRY/YAGNI pragmatically

### Testing (Codeception)
- **❌ NEVER** duplicate fixture loading - use `_fixtures()` method ONLY, not `haveFixtures()` in `_before()`
- **❌ NEVER** use magic numbers - use `$this->tester->grabFixture('users', 'user1')`
- Test both success and failure paths

## Commands

```bash
# Docker
docker compose up -d                    # Start
docker exec -it pma_yii bash            # Shell access

# Tests
docker exec pma_yii vendor/bin/codecept run unit
docker exec pma_yii vendor/bin/codecept run unit services/MyServiceTest:testMethod

# Migrations
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# Frontend (after Quill changes)
docker compose run --entrypoint bash pma_npm -c "npm run build-and-minify"
```

## Structure

```
yii/
├── controllers/    # Thin HTTP layer
├── services/       # Business logic (most code here)
├── models/         # ActiveRecord + validation
├── views/          # Templates
├── config/         # main.php, web.php, console.php, test.php, db.php
├── migrations/     # Database migrations
├── modules/identity/  # Auth module (separate migrations)
├── tests/          # Codeception suites
└── web/            # GENERATED - never edit
npm/                # Frontend source for Quill.js
```

**Key Models:** Project, Context, Field, FieldOption, PromptTemplate, TemplateField, PromptInstance, UserPreference

**Key Services:** ProjectService, ContextService, FieldService, PromptTemplateService, PromptGenerationService, PromptInstanceService, UserPreferenceService

## Domain

### Prompt Generation Flow
Project → Context → Field/FieldOption → PromptTemplate → PromptInstance → Final Prompt

### Placeholders
- `GEN:{{fieldId}}` - Generic field
- `PRJ:{{fieldId}}` - Project-specific field
- Replaced by `PromptGenerationService` using Quill Delta format

## Environment

Docker Compose with `.env`: `DB_HOST=pma_mysql`, `DB_DATABASE=promptmanager`, `DB_DATABASE_TEST=promptmanager_test`, `NGINX_PORT=8503`

## Notes

- Windows dev → Unix containers via Docker
- Prompts use Quill Delta format (JSON ops array), not plain text/HTML
- Multi-tenant with owner-based RBAC permissions
