# PromptManager Codebase Analysis

This document is informational (architecture/domain overview). If anything here conflicts with project instructions, follow `.claude/rules/` first, then `CLAUDE.md`.

## 1. Project Overview

**PromptManager** is a PHP 8.2 / Yii2 web application for organizing LLM prompts into projects, contexts, fields, and templates.

| Aspect | Details |
|--------|---------|
| **Type** | Web application for LLM prompt management |
| **Stack** | PHP 8.2, Yii2 framework, MySQL 8.0, Bootstrap 5 |
| **Architecture** | MVC with Service layer pattern |
| **Frontend** | Bootstrap 5, Quill rich text editor |
| **Infrastructure** | Docker (pma_yii, pma_mysql, pma_nginx, pma_npm) |
| **Testing** | Codeception (unit/functional) |

### Purpose

The application allows users to:
- Create **Projects** as organizational containers for prompt-related resources
- Define reusable **Contexts** (boilerplate content prepended to prompts)
- Configure **Fields** with various types (text, select, multi-select, code, file, directory)
- Build **Prompt Templates** using placeholders that reference fields
- Generate **Prompt Instances** by filling in template fields with actual values

---

## 2. Domain Entities

### Entity Relationship Diagram

```
┌─────────────┐       ┌─────────────┐       ┌─────────────────┐
│    User     │──1:N──│   Project   │──1:N──│     Context     │
└─────────────┘       └──────┬──────┘       └─────────────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
        ▼                    ▼                    ▼
┌───────────────┐   ┌────────────────┐   ┌─────────────────────┐
│     Field     │   │PromptTemplate  │   │ProjectLinkedProject │
└───────┬───────┘   └───────┬────────┘   └─────────────────────┘
        │                   │
        ▼                   ▼
┌───────────────┐   ┌───────────────┐
│  FieldOption  │   │ TemplateField │──M:N──┐
└───────────────┘   └───────────────┘       │
                            │               │
                            ▼               │
                    ┌───────────────┐       │
                    │PromptInstance │       │
                    └───────────────┘       │
                                            │
                    ┌───────────────────────┘
                    │ (links templates to fields)
                    ▼
```

### Core Entities

#### Project (`yii/models/Project.php`)
The top-level organizational unit that groups related prompts, contexts, and fields.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `user_id` | int | Owner reference |
| `name` | string | Project name |
| `description` | string\|null | Optional description |
| `root_directory` | string\|null | File system root for file/directory fields |
| `allowed_file_extensions` | string\|null | Comma-separated whitelist (e.g., `php,js,md`) |
| `blacklisted_directories` | string\|null | Blacklisted paths with optional exceptions |
| `prompt_instance_copy_format` | string | Output format (md, text, html, quilldelta, llm-xml) |
| `label` | string\|null | Unique label per user for cross-project referencing |

**Key Features:**
- File extension validation and whitelist
- Directory blacklisting with exception syntax: `path/[exception1,exception2]`
- Linked projects via many-to-many relationship
- Copy format enum for output formatting

**Relationships:**
- `belongsTo User`
- `hasMany Project` (via `project_linked_project` pivot)

---

#### Context (`yii/models/Context.php`)
Reusable content blocks that can be prepended to prompts.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `project_id` | int | Parent project |
| `name` | string | Context name |
| `content` | string\|null | Quill Delta JSON content |
| `is_default` | bool | Auto-selected when generating prompts |
| `share` | bool | Shareable with linked projects |
| `order` | int | Display/selection order |

**Relationships:**
- `belongsTo Project`

---

#### Field (`yii/models/Field.php`)
Template placeholders that get replaced with user-provided values during prompt generation.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `user_id` | int | Owner reference |
| `project_id` | int\|null | Optional project scope (null = global) |
| `name` | string | Field name (placeholder key) |
| `type` | string | Field type |
| `content` | string\|null | Default content (Quill Delta JSON) |
| `share` | bool | Shareable with linked projects |
| `label` | string\|null | Display label |
| `render_label` | bool | Include label as header in output |

**Field Types** (from `FieldConstants::TYPES`):
- `text` - Free-form text input (Quill editor)
- `select` - Single-choice dropdown
- `multi-select` - Multi-choice checkboxes
- `code` - Code block with syntax highlighting
- `select-invert` - Select with inverted output (shows selected + unselected labels)
- `file` - File path selector
- `directory` - Directory path selector

**Relationships:**
- `belongsTo User`
- `belongsTo Project` (optional)
- `hasMany FieldOption`

---

#### FieldOption (`yii/models/FieldOption.php`)
Predefined options for select-type fields.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `field_id` | int | Parent field |
| `value` | string | Option value (Quill Delta JSON) |
| `label` | string\|null | Display label |
| `selected_by_default` | bool | Pre-selected when form loads |
| `order` | int | Display order |

**Relationships:**
- `belongsTo Field`

---

#### PromptTemplate (`yii/models/PromptTemplate.php`)
Template definitions with field placeholders.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `project_id` | int | Parent project |
| `name` | string | Template name |
| `template_body` | string | Quill Delta JSON with placeholders |

**Placeholder Format:**
- `GEN:{{field_name}}` - Global field (project_id = null)
- `PRJ:{{field_name}}` - Project-specific field
- `EXT:{{project_label: field_name}}` - External/linked project field

Placeholders are converted to `TYPE:{{field_id}}` format when saved.

**Relationships:**
- `belongsTo Project`
- `hasMany PromptInstance`
- `hasMany Field` (via `template_field` pivot)

---

#### TemplateField (`yii/models/TemplateField.php`)
Pivot table linking templates to their referenced fields.

| Attribute | Type | Description |
|-----------|------|-------------|
| `template_id` | int | Template reference |
| `field_id` | int | Field reference |

---

#### PromptInstance (`yii/models/PromptInstance.php`)
Generated/saved prompt outputs.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `template_id` | int | Source template |
| `label` | string | Instance label |
| `final_prompt` | string | Generated content (Quill Delta JSON) |

**Relationships:**
- `belongsTo PromptTemplate`

---

#### ProjectLinkedProject (`yii/models/ProjectLinkedProject.php`)
Many-to-many relationship for project linking.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `project_id` | int | Source project |
| `linked_project_id` | int | Linked project |

**Validation:**
- Cannot link project to itself
- Unique constraint on (project_id, linked_project_id)

---

#### ScratchPad (`yii/models/ScratchPad.php`)
Workspace for prompt composition and editing.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `user_id` | int | Owner reference |
| `project_id` | int\|null | Optional project scope |
| `name` | string | Scratch pad name |
| `content` | string\|null | Quill Delta JSON content |
| `created_at` | int | Unix timestamp |
| `updated_at` | int | Unix timestamp |

**Relationships:**
- `belongsTo User`
- `belongsTo Project` (optional)

---

## 3. Data Layer

### Query Classes

Query classes provide chainable scopes for common filtering patterns.

#### ProjectQuery (`yii/models/query/ProjectQuery.php`)
```php
forUser(int $userId): static
orderedByName(): static
availableForLinking(?int $excludeProjectId, int $userId): static
findUserProject(int $projectId, int $userId): ?Project
```

#### ContextQuery (`yii/models/query/ContextQuery.php`)
```php
forUser(int $userId): static
forProject(int $projectId): static
forProjectWithLinkedSharing(int $projectId, array $linkedProjectIds): static
orderedByName(): static
orderedByOrder(): static
defaultOrdering(): static
withIds(array $contextIds): static
onlyDefault(): static
```

#### FieldQuery (`yii/models/query/FieldQuery.php`)
```php
sharedFromProjects(int $userId, array $projectIds): static
```

#### ProjectLinkedProjectQuery (`yii/models/query/ProjectLinkedProjectQuery.php`)
```php
linkedProjectIdsFor(int $projectId, int $userId): static
```

#### ScratchPadQuery (`yii/models/query/ScratchPadQuery.php`)
```php
forUser(int $userId): static
forProject(?int $projectId): static
```

### Search Models

Search models handle GridView/ListView filtering using `SearchModelTrait`:
- `ProjectSearch` - Filter projects by name, description
- `ContextSearch` - Filter contexts by project, name
- `FieldSearch` - Filter fields by project, name, type
- `PromptTemplateSearch` - Filter templates by project, name
- `PromptInstanceSearch` - Filter instances by template, label
- `ScratchPadSearch` - Filter scratch pads by project, name

### Traits

#### TimestampTrait (`yii/models/traits/TimestampTrait.php`)
Shared timestamp handling for `created_at`/`updated_at` fields:
```php
public function handleTimestamps(bool $insert): void
{
    if ($insert) {
        $this->created_at = time();
    }
    $this->updated_at = time();
}
```

---

## 4. Service Layer

### PromptGenerationService (`yii/services/PromptGenerationService.php`)
Core service for generating final prompts from templates and field values.

**Responsibilities:**
- Replace placeholders with field values
- Handle context prepending
- Process different field types (text, select, multi-select, select-invert, file, directory)
- Build Quill Delta output
- Remove consecutive newlines

**Key Method:**
```php
generateFinalPrompt(
    int $templateId,
    array $selectedContexts,
    array $fieldValues,
    int $userId
): string  // Returns Quill Delta JSON
```

**Placeholder Pattern:** `/\b(?:GEN|PRJ|EXT):\{\{(\d+)}}/`

---

### PromptTemplateService (`yii/services/PromptTemplateService.php`)
Manages template CRUD and placeholder conversion.

**Responsibilities:**
- Save templates with field associations
- Validate placeholder references
- Convert human-readable placeholders to IDs and vice versa
- Detect duplicate placeholders
- Update `template_field` pivot records

**Key Methods:**
```php
saveTemplateWithFields(PromptTemplate $model, array $postData, array $fieldsMapping): bool
validateTemplatePlaceholders(string $template, array $fieldsMapping): array
convertPlaceholdersToIds(string $template, array $fieldsMapping): string
convertPlaceholdersToLabels(string $template, array $fieldsMapping): string
getTemplateById(int $templateId, int $userId): ?PromptTemplate
```

---

### PromptInstanceService (`yii/services/PromptInstanceService.php`)
Handles prompt instance persistence and ownership validation.

**Key Methods:**
```php
saveModel(PromptInstance $model, array $postData): bool
findModelWithOwner(int $id, int $userId): ActiveRecord
parseRawFieldValues(array $fieldValues): array
```

---

### ContextService (`yii/services/ContextService.php`)
Manages context CRUD and querying with project/linked project support.

**Key Methods:**
```php
saveContext(Context $model): bool
deleteContext(Context $model): bool
fetchContexts(int $userId): array
fetchProjectContexts(int $userId, ?int $projectId): array
fetchContextsContentById(int $userId, array $contextIds): array
fetchDefaultContextIds(int $userId, ?int $projectId): array
renumberContexts(int $projectId): bool
```

---

### FieldService (`yii/services/FieldService.php`)
Manages fields and their options with transactional saves.

**Key Methods:**
```php
fetchFieldsMap(int $userId, ?int $projectId): array
fetchExternalFieldsMap(int $userId, ?int $projectId): array
saveFieldWithOptions(Field $field, array $options): bool
deleteField(Field $field): bool
renumberFieldOptions(Field $field): bool
```

**Placeholder Generation:**
- Global: `GEN:{{field_name}}`
- Project: `PRJ:{{field_name}}`
- External: `EXT:{{project_label: field_name}}`

---

### ProjectService (`yii/services/ProjectService.php`)
Manages project listing and linked project synchronization.

**Key Methods:**
```php
fetchProjectsList(int $userId): array
fetchAvailableProjectsForLinking(?int $excludeProjectId, int $userId): array
syncLinkedProjects(Project $project, array $linkedProjectIds): void
```

---

### CopyFormatConverter (`yii/services/CopyFormatConverter.php`)
Converts Quill Delta JSON to various output formats.

**Supported Formats** (`CopyType` enum):
| Format | Value | Description |
|--------|-------|-------------|
| Markdown | `md` | GitHub-flavored markdown |
| Plain Text | `text` | Stripped plain text |
| HTML | `html` | HTML markup |
| Quill Delta | `quilldelta` | Raw Quill Delta JSON |
| LLM XML | `llm-xml` | XML with `<instructions>` tags |

**Key Methods:**
```php
convertFromQuillDelta(string $content, CopyType $type): string
convertFromHtml(string $content, CopyType $type): string
convertFromPlainText(string $content, CopyType $type): string
```

---

### Other Services

| Service | Purpose |
|---------|---------|
| `EntityPermissionService` | RBAC permission checking for controller actions |
| `FileFieldProcessor` | Process file/directory field values |
| `ModelService` | Generic model CRUD operations |
| `PathService` | File system path validation |
| `PromptFieldRenderer` | Render field inputs |
| `PromptTransformationService` | Transform field content for AI models |
| `UserDataSeeder` | Seed initial user data |
| `UserPreferenceService` | Manage user preferences |

---

## 5. Identity Module

Located in `yii/modules/identity/`, handles authentication and user management.

### User Model (`yii/modules/identity/models/User.php`)
Implements `IdentityInterface` for Yii2 authentication.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `username` | string | Unique username |
| `email` | string | Unique email |
| `password_hash` | string | Bcrypt hash |
| `auth_key` | string | Cookie auth key |
| `password_reset_token` | string\|null | Password reset token |
| `access_token` | string\|null | API access token |
| `status` | int | 0=inactive, 10=active |

**Key Methods:**
```php
findIdentity($id): ?self
findByUsername(string $username): ?self
findByPasswordResetToken(string $token): ?self
validatePassword($password): bool
setPassword($password): void
generateAuthKey(): void
```

### Authentication Flow
1. Login form validates against `LoginForm`
2. `UserService` authenticates via `User::validatePassword()`
3. Session created with `auth_key`
4. Access control via Yii2 RBAC with owner rules

### Module Structure
- **Controllers:** `AuthController` - Login, logout, signup actions
- **Models:** `User`, `UserQuery`, `LoginForm`, `SignupForm`
- **Services:** `UserService`, `UserDataSeederInterface`

### RBAC Rules (`yii/rbac/`)
| Rule Class | Purpose |
|------------|---------|
| `ProjectOwnerRule` | Check if user owns the project |
| `ContextOwnerRule` | Check if user owns the context (via project) |
| `FieldOwnerRule` | Check if user owns the field |
| `PromptTemplateOwnerRule` | Check if user owns the template (via project) |
| `PromptInstanceOwnerRule` | Check if user owns the instance (via template->project) |

---

## 6. Controllers

### Main Controllers (`yii/controllers/`)

| Controller | Purpose |
|------------|---------|
| `SiteController` | Home, login, logout, error handling |
| `ProjectController` | Project CRUD, project switching |
| `ContextController` | Context CRUD |
| `FieldController` | Field CRUD with options |
| `PromptTemplateController` | Template CRUD |
| `PromptInstanceController` | Instance generation and management |
| `ScratchPadController` | Scratch pad CRUD and content management |

### PromptInstanceController Actions

| Action | Method | Description |
|--------|--------|-------------|
| `actionIndex` | GET | List instances |
| `actionView` | GET | View single instance |
| `actionCreate` | GET/POST | Create instance form |
| `actionUpdate` | GET/POST | Update instance |
| `actionDelete` | POST | Delete with confirmation |
| `actionGeneratePromptForm` | POST/AJAX | Get template fields form |
| `actionGenerateFinalPrompt` | POST/AJAX | Generate prompt from template + fields |
| `actionSaveFinalPrompt` | POST/AJAX | Save generated prompt |

### Controller Pattern
Controllers follow a consistent pattern:
1. Inject services via constructor
2. Configure access control via `behaviors()`
3. Delegate business logic to services
4. Handle form loading and validation
5. Render views with model data

---

## 7. Code Patterns

### Service Pattern
Business logic resides in services; controllers delegate:

```php
// Controller
public function actionCreate(): Response|string
{
    $model = new Project();
    if ($this->projectService->saveProject($model, Yii::$app->request->post())) {
        return $this->redirect(['view', 'id' => $model->id]);
    }
    return $this->render('create', ['model' => $model]);
}
```

### Query Scopes
Chainable, return `static`:

```php
public function forUser(int $userId): static
{
    return $this->andWhere(['user_id' => $userId]);
}
```

### Timestamp Trait
Shared trait for `created_at`/`updated_at` handling:

```php
use TimestampTrait;

public function beforeSave($insert): bool
{
    $this->handleTimestamps($insert);
    return parent::beforeSave($insert);
}
```

### DI in Services
Constructor injection over `Yii::$app` access:

```php
public function __construct(
    private readonly PromptTemplateService $templateService,
) {}
```

### Transactional Operations
Database transactions for multi-model saves:

```php
$transaction = Yii::$app->db->beginTransaction();
try {
    // operations
    $transaction->commit();
} catch (Throwable $e) {
    $transaction->rollBack();
}
```

---

## 8. Architecture Diagram

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
│  │  SiteController, ProjectController, ContextController,    │  │
│  │  FieldController, PromptTemplateController,               │  │
│  │  PromptInstanceController                                 │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│  ┌───────────────────────────┴──────────────────────────────┐  │
│  │                     Services                              │  │
│  │  PromptGenerationService, PromptTemplateService,          │  │
│  │  PromptInstanceService, ContextService, FieldService,     │  │
│  │  ProjectService, CopyFormatConverter, PathService         │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│  ┌───────────────────────────┴──────────────────────────────┐  │
│  │              Models + Query Classes                       │  │
│  │  Project, Context, Field, FieldOption, PromptTemplate,    │  │
│  │  TemplateField, PromptInstance, ProjectLinkedProject      │  │
│  │  + ProjectQuery, ContextQuery, FieldQuery                 │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│  ┌───────────────────────────┴──────────────────────────────┐  │
│  │                    Database (MySQL 8.0)                   │  │
│  └──────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────┤
│  Module: Identity (Authentication + User Management)            │
│  └─ User, UserQuery, UserService, AuthController               │
├─────────────────────────────────────────────────────────────────┤
│  RBAC: ProjectOwnerRule, ContextOwnerRule, FieldOwnerRule,      │
│        PromptTemplateOwnerRule, PromptInstanceOwnerRule         │
├─────────────────────────────────────────────────────────────────┤
│  Infrastructure: Docker (pma_yii, pma_mysql, pma_nginx, pma_npm)│
└─────────────────────────────────────────────────────────────────┘
```

---

## 9. Key Paths

| Path | Contents |
|------|----------|
| `yii/controllers/` | Application controllers |
| `yii/services/` | Business logic services |
| `yii/models/` | ActiveRecord models |
| `yii/models/query/` | Query classes |
| `yii/views/` | View templates |
| `yii/migrations/` | Database migrations |
| `yii/tests/` | Codeception tests |
| `yii/modules/identity/` | Auth module |
| `yii/common/enums/` | Enums (CopyType) |
| `yii/common/constants/` | Constants (FieldConstants) |
| `yii/rbac/` | RBAC rules |
| `yii/widgets/` | Custom widgets |
| `yii/presenters/` | Presenter classes |
| `yii/components/` | Application components |
| `yii/helpers/` | Helper classes |
| `npm/` | Frontend build scripts |

---

## 10. Testing Structure

```
yii/tests/
├── codeception.yml        # Test configuration
├── unit/                  # Unit tests
│   ├── models/
│   ├── services/
│   ├── commands/
│   ├── widgets/
│   └── modules/identity/
├── functional/            # Functional tests
│   └── controllers/
├── fixtures/              # Test fixtures
│   └── data/
├── _support/              # Test helpers
└── _data/                 # Test data
```

---

## 11. Database Tables

| Table | Description |
|-------|-------------|
| `user` | User accounts |
| `project` | Projects |
| `project_linked_project` | Project-to-project links |
| `context` | Context content |
| `field` | Field definitions |
| `field_option` | Select field options |
| `prompt_template` | Template definitions |
| `template_field` | Template-field associations |
| `prompt_instance` | Generated prompt instances |
| `scratch_pad` | Scratch pad workspaces |
| `user_preference` | User preferences |

---

## 12. Key Insights

1. **Quill Delta Format**: All rich text content (contexts, field content, templates, instances) uses Quill Delta JSON format
2. **Placeholder System**: Three-tier placeholders (GEN/PRJ/EXT) enable field reuse across projects
3. **Project Linking**: Projects can share contexts and fields with linked projects via `share` flag
4. **Output Formats**: Five output formats supported via `CopyType` enum for copying generated prompts
5. **Owner Validation**: All entities validated against current user via RBAC rules
6. **File System Integration**: File/directory fields integrate with project root directories with blacklist support
7. **Service Layer**: Business logic centralized in services, controllers are thin
