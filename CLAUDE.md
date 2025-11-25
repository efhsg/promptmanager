# PromptManager - Claude Code Guidance

## Project Context

Yii2-based LLM prompt management system. Manages reusable prompt components (projects, contexts, fields, templates) and generates final prompts via placeholder substitution using Quill Delta format.

**Current Focus:** Placeholder replacement (`PromptGenerationService`), multi-tenant access control, field validation.

## Tech Stack

- PHP 8.2+ (Yii2 2.0.x), MySQL 8.x, Quill.js
- Testing: Codeception
- Infrastructure: Docker Compose (services: pma_yii, pma_nginx, pma_mysql, pma_npm)
- IDE: PHPStorm on Windows → Unix deployment

## Critical Rules (VIOLATIONS = REJECTED CODE)

### Code Standards
- ✅ PSR-12 compliant, full type hints on ALL parameters/returns
- ✅ Imports at top, NEVER fully-qualified names in method bodies
- ❌ **NEVER use `declare(strict_types=1);`** - Not used in this project
- ❌ **NEVER add @param/@return in PHPDoc** - Only `@throws` and special behaviors
- ❌ **NEVER edit `yii/web/` directly** - Compiled outputs from `npm/`

### Architecture
- **Service Layer Pattern**: ALL business logic in `yii/services/`, controllers stay thin
- **SOLID/DRY/YAGNI**: Apply pragmatically - stop when duplication/coupling eliminated
- See `docs/ARCHITECTURE.md` for detailed patterns and examples

### Testing (Codeception)
- ❌ **NEVER duplicate fixture loading** - Use `_fixtures()` method ONLY, NOT `haveFixtures()` in `_before()`
- ❌ **NEVER use magic numbers** - Use `grabFixture('users', 'user1')` instead of hardcoded IDs
- ✅ Test both success and failure paths
- ✅ Use `createMock()` for unit tests, fixtures for integration tests
- See `docs/TESTING.md` for detailed examples and patterns

### Comment Style (STRICT)
```php
 
