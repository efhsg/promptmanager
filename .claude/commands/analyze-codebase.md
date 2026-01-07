---
allowed-tools: Bash(find:*), Bash(ls:*), Bash(grep:*), Bash(wc:*), Bash(du:*), Bash(head:*), Bash(tail:*), Bash(cat:*), Bash(touch:*), Bash(xargs:*), Bash(basename:*)
description: Generate comprehensive analysis and documentation of entire codebase
---
# PromptManager Codebase Analysis

## Project Discovery Phase

### Application Directory Structure (excluding vendor)
!`find ./yii -type d -not -path "*/vendor/*" -not -path "*/runtime/*" -not -path "*/_output/*" -not -path "*/_generated/*" -not -path "*/web/assets/*" | sort`

### File Counts
- Total PHP files (app code): !`find ./yii -name "*.php" -not -path "*/vendor/*" -not -path "*/runtime/*" | wc -l`
- Controllers: !`find ./yii -name "*Controller.php" -not -path "*/vendor/*" | wc -l`
- Models (ActiveRecord): !`find ./yii/models -maxdepth 1 -name "*.php" 2>/dev/null | wc -l`
- Query classes: !`find ./yii/models/query -name "*.php" 2>/dev/null | wc -l`
- Services: !`find ./yii/services -name "*.php" 2>/dev/null | wc -l`
- Unit tests: !`find ./yii/tests/unit -name "*Test.php" 2>/dev/null | wc -l`
- Fixtures: !`find ./yii/tests/fixtures -name "*Fixture.php" 2>/dev/null | wc -l`
- Migrations: !`find ./yii/migrations -name "*.php" 2>/dev/null | wc -l`

## Configuration Analysis

### Composer Dependencies
@yii/composer.json

### Docker Configuration
@docker-compose.yml

### Yii2 Main Configuration
@yii/config/web.php

## Application Structure

### Controllers
!`ls -1 yii/controllers/*.php 2>/dev/null | xargs -I {} basename {}`

### ActiveRecord Models
!`ls -1 yii/models/*.php 2>/dev/null | xargs -I {} basename {}`

### Query Classes
!`ls -1 yii/models/query/*.php 2>/dev/null | xargs -I {} basename {}`

### Services
!`ls -1 yii/services/*.php 2>/dev/null | xargs -I {} basename {}`

### Presenters
!`ls -1 yii/presenters/*.php 2>/dev/null | xargs -I {} basename {}`

### Components
!`ls -1 yii/components/*.php 2>/dev/null | xargs -I {} basename {}`

### Widgets
!`ls -1 yii/widgets/*.php 2>/dev/null | xargs -I {} basename {}`

### Helpers
!`ls -1 yii/helpers/*.php 2>/dev/null | xargs -I {} basename {}`

### Enums
!`ls -1 yii/common/enums/*.php 2>/dev/null | xargs -I {} basename {}`

### Views Structure
!`ls -1 yii/views/`

## Modules

### Module List
!`ls -1 yii/modules/ 2>/dev/null`

### Identity Module Controllers
!`ls -1 yii/modules/identity/controllers/*.php 2>/dev/null | xargs -I {} basename {}`

### Identity Module Models
!`ls -1 yii/modules/identity/models/*.php 2>/dev/null | xargs -I {} basename {}`

### Identity Module Services
!`ls -1 yii/modules/identity/services/*.php 2>/dev/null | xargs -I {} basename {}`

## RBAC System
!`ls -1 yii/rbac/ 2>/dev/null`

## Database

### Recent Migrations (last 10)
!`ls -1 yii/migrations/*.php 2>/dev/null | tail -10 | xargs -I {} basename {}`

## Testing Structure

### Test directories
!`find ./yii/tests -type d -not -path "*/_output/*" -not -path "*/_generated/*" -not -path "*/_support/_generated/*"`

### Test configuration
@yii/tests/codeception.yml

## Frontend Build

### NPM Package Configuration
@npm/package.json

## Documentation
@.claude/rules/coding-standards.md
@.claude/rules/architecture.md

## Entry Points
@yii/web/index.php

---

## Analysis Instructions

Based on the discovered information, create a comprehensive `codebase_analysis.md` with:

### 1. Project Overview
- **Type**: PHP 8.2 / Yii2 web application for organizing LLM prompts
- **Stack**: PHP 8.2 / Yii2 framework / Bootstrap 5 / Quill editor
- **Architecture**: MVC with Service layer pattern
- **Database**: MySQL
- **Frontend**: Quill rich text editor built via npm/

### 2. Domain Entities
For each core model (Project, Context, Field, PromptTemplate, PromptInstance):
- Purpose and relationships
- Key attributes
- Business logic handled in services

### 3. Data Layer
- ActiveRecord models and relationships
- Query classes with chainable scopes
- Search models for filtering

### 4. Service Layer
For each service:
- Responsibilities
- Dependencies
- Key methods

### 5. Identity Module
- Authentication flow
- User management
- Session handling

### 6. Code Patterns
- Service pattern: business logic in services, controllers delegate
- Query scopes: chainable, return `static`
- Tests mirror app structure
- Codeception for unit/functional testing

### 7. Development Commands
```bash
# Tests
docker exec pma_yii vendor/bin/codecept run unit
docker exec pma_yii vendor/bin/codecept run unit services/MyServiceTest:testMethod

# Migrations
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# Frontend build
docker compose run --entrypoint bash pma_npm -c "npm run build-and-minify"
```

### 8. Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                       PromptManager                             │
├─────────────────────────────────────────────────────────────────┤
│  Views (Bootstrap 5 + Quill Editor)                             │
│  ┌────────┐ ┌─────────┐ ┌───────┐ ┌──────────┐ ┌────────────┐  │
│  │Project │ │ Context │ │ Field │ │ Template │ │  Instance  │  │
│  └───┬────┘ └────┬────┘ └───┬───┘ └────┬─────┘ └──────┬─────┘  │
│      └───────────┴──────────┴──────────┴──────────────┘        │
│                              │                                  │
│  ┌───────────────────────────┴──────────────────────────────┐  │
│  │                    Controllers                            │  │
│  │         (SiteController, ProjectController, etc.)         │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│  ┌───────────────────────────┴──────────────────────────────┐  │
│  │                     Services                              │  │
│  │    (PromptGenerationService, PromptTemplateService, etc.) │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│  ┌───────────────────────────┴──────────────────────────────┐  │
│  │              Models + Query Classes                       │  │
│  │        (Project, Context, Field, PromptTemplate, etc.)    │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│  ┌───────────────────────────┴──────────────────────────────┐  │
│  │                    Database (MySQL)                       │  │
│  └──────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────┤
│  Module: Identity (Authentication + User Management)            │
├─────────────────────────────────────────────────────────────────┤
│  Infrastructure: Docker (pma_yii, pma_mysql, pma_npm)           │
└─────────────────────────────────────────────────────────────────┘
```

### 9. Key Insights
- Code follows PSR-12, full type hints, no `declare(strict_types=1)`
- DI preferred in services over `Yii::$app` access
- ActiveRecord columns are magic attributes
- Codeception for testing with fixtures

Write the full analysis to `.claude/codebase_analysis.md`
