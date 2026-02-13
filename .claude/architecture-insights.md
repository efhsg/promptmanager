# Architecture Insights

Deep understanding of how PromptManager works, covering data flows, integration points, design decisions, and operational patterns. Complements `codebase_analysis.md` (structural reference) with behavioral and conceptual knowledge.

---

## 1. Application Identity

PromptManager is a **prompt engineering workbench** — a web app for composing, parameterizing, and generating LLM prompts at scale. Users build reusable prompt templates with typed placeholders, fill them with field values at generation time, and get formatted output ready for any LLM.

The app also serves as a **Claude Code web frontend**, allowing users to run Claude CLI sessions directly from the browser against their project directories.

**Stack**: PHP 8.2 / Yii2 / MySQL 8.0 / Bootstrap 5 / Quill rich text editor, running in Docker (4 containers: `pma_yii`, `pma_nginx`, `pma_mysql`, `pma_npm`).

---

## 2. Core Data Flow: Template → Instance

The primary workflow is template-based prompt generation:

```
1. User selects a PromptTemplate
2. System loads template's Quill Delta body + associated Fields (via template_field pivot)
3. User selects Contexts (prepended boilerplate) and fills in Field values
4. PromptGenerationService:
   a. Decodes template Delta JSON → ops array
   b. Prepends context ops (each context is also Quill Delta)
   c. PlaceholderProcessor scans ops for GEN:{{id}}/PRJ:{{id}}/EXT:{{id}} patterns
   d. FieldValueBuilder constructs replacement ops per field type
   e. DeltaOpsHelper cleans up consecutive newlines
5. Output: Quill Delta JSON string → saved as PromptInstance.final_prompt
6. CopyFormatConverter converts to user's preferred format for clipboard
```

### Placeholder Lifecycle

Placeholders go through a name↔ID conversion cycle:

- **User sees**: `PRJ:{{my_field_name}}` (human-readable)
- **Stored as**: `PRJ:{{42}}` (field ID) in `template_body`
- **PromptTemplateService.convertPlaceholdersToIds()**: runs on save
- **PromptTemplateService.convertPlaceholdersToLabels()**: runs on display/edit
- **PromptGenerationService**: works with ID-based placeholders at generation time

This decouples field renames from template content — renaming a field doesn't break templates.

### Field Type Processing

Each field type produces different Quill Delta ops during generation:

| Type | Processing |
|------|-----------|
| `text`, `code` | Delta content inserted directly |
| `select` | Selected option's Delta value inserted |
| `multi-select` | Multiple option Delta values concatenated |
| `select-invert` | Selected option value + inverted (unselected) labels |
| `file` | File path validated → content read at generation time |
| `directory` | Directory path validated against project root |
| `string`, `number` | Inline text inserted (no Delta wrapper) |

File/directory fields use `FileFieldProcessor` + `PathService` for validation against the project's `root_directory`, `allowed_file_extensions`, and `blacklisted_directories`.

---

## 3. Rich Text Pipeline

**Everything is Quill Delta JSON internally.** This is the universal content format for contexts, field content, template bodies, and generated instances.

### Conversion Architecture

```
                    ┌─────────────┐
Input Sources:      │ DeltaParser │──→ Block[] (intermediate representation)
  Quill Delta ─────→│  .decode()  │
                    │  .buildBlocks()│
                    └─────────────┘
                           │
Input Sources:      ┌──────────────┐
  Markdown ────────→│MarkdownParser│──→ Block[]
                    │   .parse()   │
                    └──────────────┘
                           │
                    ┌──────┴──────────────────────────────┐
                    ▼              ▼            ▼          ▼
              MarkdownWriter  HtmlWriter  PlainTextWriter  LlmXmlWriter
              QuillDeltaWriter
                    │              │            │          │
                    ▼              ▼            ▼          ▼
                  .md            .html        .text      .xml
```

All writers implement `FormatWriterInterface`. The `CopyFormatConverter` facade selects the appropriate writer based on `CopyType` enum.

### Block IR

The intermediate `Block[]` representation bridges all formats. Each block has a type (paragraph, heading, code-block, list, blockquote) and contains inline segments with formatting attributes. This allows round-tripping between formats without losing structure.

---

## 4. Project Context System

The app is **project-scoped** — most views filter content by the currently selected project. Three special modes exist:

| Mode | ID | Behavior |
|------|----|----------|
| Specific project | `> 0` | Show only that project's entities |
| All Projects | `-1` | Show entities across all user's projects |
| No Project | `0` | Show entities with `project_id = NULL` (globals) |

### Resolution Priority

```
1. URL parameter (?p=X)  ← enables multi-tab support
2. Session value
3. User preference (persisted default)
```

`ProjectContext` component handles this. `ProjectUrlManager` extends Yii's URL manager to inject the `p` parameter into project-scoped routes, maintaining context across navigation.

---

## 5. Claude CLI Integration

### Architecture Overview

```
Browser ──SSE──→ NoteController.actionStreamClaude()
                        │
                        ▼
                 ClaudeCliService.executeStreaming()
                        │
                 proc_open('claude --output-format stream-json ...')
                        │
                        ▼
                 Claude CLI process (running in project directory)
                        │
                 stdout: NDJSON lines ──→ SSE events to browser
```

### Two Execution Modes

1. **Blocking** (`execute()`): Runs Claude CLI, collects all output, returns structured result. Used for session summarization and non-interactive calls.
2. **Streaming** (`executeStreaming()`): Opens SSE connection, pipes each stdout line to the browser in real-time. Used for the interactive Claude chat view.

### Working Directory Resolution

Claude CLI needs a working directory with project context. Resolution priority:

```
1. Project's root_directory IF it has CLAUDE.md or .claude/ config
2. Managed workspace (ClaudeWorkspaceService creates per-project workspaces)
3. Default workspace (for scratch pads without projects)
```

Path translation handles host↔container path differences via `pathMappings` in app params.

### Process Management

- PIDs stored in file cache keyed by `streamToken` (UUID)
- `cancelRunningProcess()` sends SIGTERM then SIGKILL after 200ms
- Client disconnect detected via `connection_aborted()` in streaming loop
- Session lock released (`session->close()`) before streaming to prevent blocking other requests

### Quick Handler Pattern

For lightweight, single-turn AI calls (title summarization, name suggestions):

```
ClaudeQuickHandler
  → AiCompletionClient interface
    → ClaudeCliCompletionClient (implementation)
      → ClaudeCliService.execute() in isolated temp dir (/tmp/claude-quick)
```

The isolated workdir prevents project config from leaking into quick calls. Prompt wrapped in `<document>` tags to prevent injection.

### Project-Level Claude Configuration

Projects can define via `claude_options` JSON column:
- Default model
- Permission mode
- System prompt
- Allowed/disallowed tools
- Command blacklist (hide specific slash commands)
- Command groups (organize commands in dropdown)

---

## 6. Project Linking & Sharing

Projects can link to each other via `project_linked_project` (M:N):

```
Project A ──links to──→ Project B
                         │
                         ├─ Contexts with share=true visible in A
                         └─ Fields with share=true usable in A's templates
```

External fields are referenced with `EXT:{{project_label: field_name}}` syntax. The `label` on Project must be unique per user to enable this cross-referencing.

`ContextQuery.forProjectWithLinkedSharing()` handles the query logic — fetching a project's own contexts plus shared contexts from linked projects.

---

## 7. Security Model

### RBAC Chain

Every entity access goes through an ownership check:

```
Controller behaviors() → matchCallback → EntityPermissionService
  → loads model via callback → checks RBAC permission
    → owner rule (e.g., ProjectOwnerRule) → validates user_id match
```

Six owner rules, one per entity type. Each rule resolves ownership through the entity's relationship chain (e.g., `PromptInstanceOwnerRule` checks instance → template → project → user).

### Input Boundaries

- **Model rules**: Yii2 validation rules on every model
- **Query scopes**: `forUser()` on every query class ensures data isolation
- **View output**: `Html::encode()` for all user-generated content
- **File access**: `PathService` validates against project's root_directory, extension whitelist, and directory blacklist
- **Claude CLI**: `escapeshellarg()` on all command parameters, prompt via stdin (not args)

---

## 8. Service Layer Patterns

### Constructor DI

All services use constructor injection. The DI container is configured in `config/main.php`:
- Frequently used services registered as **application components** (accessible via `Yii::$app->serviceName`)
- Interface bindings in `container.definitions` (e.g., `AiCompletionClient` → `ClaudeCliCompletionClient`)
- Controllers receive services via constructor params (Yii2 auto-resolves from container)

### Transactional Boundaries

Multi-model operations (e.g., saving a field with its options) use explicit transactions:

```php
$transaction = Yii::$app->db->beginTransaction();
try {
    // save parent, save children, update pivot
    $transaction->commit();
} catch (Throwable $e) {
    $transaction->rollBack();
    throw $e;
}
```

Transaction scope kept minimal — only wraps the mutating operations, not reads.

### Service Responsibilities

| Pattern | Example |
|---------|---------|
| CRUD + business rules | `ContextService`, `FieldService`, `ProjectService` |
| Generation/transformation | `PromptGenerationService`, `CopyFormatConverter` |
| Cross-cutting concerns | `EntityPermissionService`, `QuickSearchService` |
| External integration | `ClaudeCliService`, `YouTubeTranscriptService` |
| Infrastructure | `ClaudeWorkspaceService`, `PathService`, `UserPreferenceService` |

---

## 9. Frontend Architecture

### Quill Editor Integration

- Source: `npm/src/js/editor-init.js` → minified to `yii/web/quill/1.3.7/editor-init.min.js`
- Quill 1.3.7 with Snow theme
- Hidden form fields store Delta JSON (serialized on form submit)
- `QuillViewerWidget` renders read-only Delta content
- `ContentViewerWidget` adds copy-to-clipboard with format conversion

### AJAX Patterns

Controllers return JSON for AJAX actions:

```php
Yii::$app->response->format = Response::FORMAT_JSON;
return ['success' => bool, 'data' => ..., 'errors' => ...];
```

SSE streaming for Claude chat uses raw response format with `text/event-stream` content type.

### Asset Bundles

- `AppAsset`: Bootstrap 5, site CSS/JS
- `QuillAsset`: Quill editor + editor-init
- `HighlightAsset`: Syntax highlighting for code blocks
- `PathSelectorFieldAsset`: Autocomplete path browser

---

## 10. Data Sync System

The `services/sync/` package enables synchronizing data with a remote MySQL instance:

```
SyncService (orchestrator)
  → RemoteConnection (establishes PDO to remote DB)
  → RecordFetcher (reads local/remote records)
  → EntitySyncer (applies inserts/updates per entity)
  → ConflictResolver (handles timestamp-based conflicts)
  → EntityDefinitions (defines sync-eligible tables and their keys)
  → SyncReport (collects operation statistics)
```

Triggered via `SyncController` console command. Useful for multi-machine setups (e.g., syncing between development laptop and a Mac Mini server).

---

## 11. Console Commands

| Command | Purpose |
|---------|---------|
| `RbacController` | Initialize RBAC roles, permissions, and owner rules |
| `UserController` | Create/manage users from CLI |
| `FieldOptionController` | Renumber field option ordering |
| `PermissionCacheController` | Warm/clear RBAC permission cache |
| `SyncController` | Run data sync with remote database |
| `ClaudeController` | Claude workspace management |

---

## 12. Key Design Decisions

1. **Quill Delta as universal format**: Enables rich text everywhere while supporting clean conversion to any output format. Trade-off: all services must understand Delta JSON.

2. **ID-based placeholders in storage**: Decouples field identity from field naming. Templates survive field renames without migration.

3. **Project-scoped everything**: The `ProjectContext` component + `ProjectUrlManager` ensure consistent project filtering. URL param `?p=X` enables true multi-tab support.

4. **Claude CLI as subprocess**: Rather than using an API, the app spawns the Claude CLI binary directly. This leverages Claude Code's full tooling (file read/edit, bash, etc.) but requires the CLI to be installed in the container.

5. **Isolated quick completions**: The `ClaudeCliCompletionClient` runs in `/tmp/claude-quick` with no config files, preventing project context from affecting lightweight AI calls.

6. **Service layer over fat models**: Models are data + validation only. All business logic lives in services, keeping models testable and controllers thin.

7. **Owner rules per entity**: Rather than a generic "check ownership" method, each entity has its own RBAC rule that understands the relationship chain to the user. This is explicit and auditable.

8. **Soft delete for projects only**: Projects use `deleted_at` timestamp. Other entities use hard deletes — the assumption is that losing a project is more costly than losing individual contexts/fields.

---

## 13. Sync Service: Natural Key Matching

The `services/sync/` package uses an ID-independent synchronization algorithm:

### Semantic Keys

Entities are matched between local and remote databases using **natural keys** (not auto-increment IDs). For example, a project is matched by `(user_id, name)`, a context by `(project_semantic_key, name)`.

### Two-Phase Key Resolution

1. **Build lookup tables**: `RecordFetcher` reads both source and destination records, building maps keyed by semantic key JSON
2. **Map foreign keys**: During record transfer, `EntitySyncer` remaps foreign key values through these lookups (e.g., source `project_id=5` → destination `project_id=12`)

### Dependency Order

Entities sync in dependency order to ensure foreign key integrity:
```
project → context, field → field_option, prompt_template → template_field, prompt_instance → note
```

### Conflict Resolution

Last-write-wins based on `updated_at` timestamps. Ties go to source. `SyncReport` collects per-entity statistics (`+inserted`, `~updated`, `=skipped`, `!!errors`).

### SSH Tunneling

`RemoteConnection` dynamically scans ports 33061–33100 for an available local port, establishes an SSH tunnel via `proc_open`, detects the tunnel PID with `lsof`, and auto-cleans up on disconnect.

### Dry-Run Support

Full preview mode simulates all changes without committing the transaction.

---

## 14. Claude Quick Handler: Per-Use-Case Workdirs

The `ClaudeQuickHandler` uses a workdir-based configuration pattern for lightweight AI calls:

### Workdir Structure

Each use case gets its own directory under `.claude/workdirs/{name}/` containing a `CLAUDE.md` system prompt:
```
.claude/workdirs/
├── prompt-title/CLAUDE.md    # "Summarize this prompt into a title"
└── note-name/CLAUDE.md       # "Suggest a name for this note"
```

### Per-Use-Case Configuration

| Parameter | prompt-title | note-name |
|-----------|-------------|-----------------|
| Min input length | 120 chars | 20 chars |
| Max input length | 1000 chars | 3000 chars |
| Timeout | configured per use case | configured per use case |

### Safety Measures

- **Injection protection**: User input wrapped in `<document>` tags to prevent prompt injection
- **Auto-truncation**: Oversized input is truncated rather than rejected
- **Isolated workdir**: Each use case runs in its own directory, separate from project configs

---

## 15. Claude Workspace: Automatic Config Generation

When a project's own directory doesn't have Claude configuration, `ClaudeWorkspaceService` generates it automatically:

### Config Detection

Checks for both `CLAUDE.md` AND `.claude/` directory — either is sufficient to use the project's own directory.

### Generated Config

When falling back to a managed workspace, the service:
1. Generates `CLAUDE.md` from project metadata (name, description, allowed extensions, blacklisted directories)
2. Converts the project's `claude_context` field (Quill Delta JSON) → Markdown via `CopyFormatConverter`
3. Translates `claude_options` JSON → `.claude/settings.local.json` format
4. Reports which config source was used (`project_own:CLAUDE.md+.claude/`, `managed_workspace`, `default_workspace`)

### Path Translation

`pathMappings` in app params translate host paths to container paths (e.g., `/Users/esg/projects/foo` → `/workspaces/foo`) since Claude CLI runs inside Docker.

---

## 16. Claude CLI Process Lifecycle

### PID Management

PIDs stored in file cache with key `claude_cli_pid_{userId}_{streamToken}` (UUID). This enables cancellation from a separate HTTP request.

### Graceful Termination

```
1. Send SIGTERM (15) to process
2. Wait 200ms
3. If still running → Send SIGKILL (9)
```

### Client Disconnect Handling

The streaming loop checks `connection_aborted()` each iteration. On disconnect:
- Process receives SIGTERM → SIGKILL
- PID cache entry cleaned up
- No orphan processes left running

### Session Lock Release

PHP session is explicitly closed (`session->close()`) before the streaming loop begins. Without this, the session lock would block all other requests from the same user until streaming completes.

### Output Format Negotiation

Supports `stream-json` (NDJSON for SSE), `json` (blocking), and `text` (raw). If stdout is empty, falls back to stderr content.

---

## 17. Claude Usage Monitoring

The `ClaudeCliService` reads Claude's OAuth credentials to query the usage API:

### Credentials Flow

```
~/.claude/.credentials.json → extract claudeAiOauth.accessToken
  → curl https://api.anthropic.com/api/oauth/usage
    → normalize into 4 usage windows
```

### Usage Windows

| Window | Description |
|--------|-------------|
| `five_hour` | Rolling 5-hour token limit |
| `seven_day` | Rolling 7-day general limit |
| `seven_day_opus` | Rolling 7-day Opus-specific limit |
| `seven_day_sonnet` | Rolling 7-day Sonnet-specific limit |

Each window reports: utilization percentage, reset timestamp, and whether the limit is active.

### Frontend Integration

Powers the collapsible mini progress bars in the Claude chat view, giving users real-time visibility into their API usage.

---

## 18. Stream JSON Parsing Details

The NDJSON parser extracts rich metadata from Claude's streaming output:

### Per-Call Token Accuracy

Uses the **last non-sidechain assistant message's** `usage` block for accurate per-call context fill. This avoids inflated cumulative counts from multi-turn conversations.

### Tool Use Extraction

Scans content blocks for `tool_use` type and formats as human-readable strings:
- `Read: /path/to/file`
- `Bash: git status`
- `Edit: /path/to/file`

### Model Name Formatting

Converts full model IDs to short form: `claude-opus-4-5-20251101` → `opus-4.5`

### Session Persistence

Returns `session_id` from the stream, enabling conversation resumption via `--resume` flag on subsequent calls.

---

## 19. Placeholder Processor: Quill List Item Handling

The `PlaceholderProcessor` has special logic for Quill's internal list format:

### The Problem

Quill represents list items as separate ops: the text content in one op, the list attribute on the following newline op. When replacing a placeholder inside a list item, the processor must handle this split.

### Lookahead Pattern

```
1. Current op: { insert: "PRJ:{{42}}" }
2. Next op: { insert: "\n", attributes: { list: "bullet" } }
3. Processor detects the next op is an attributed newline
4. Consumes both ops, applies list attributes to the replacement content
```

### Content Normalization

When field content is injected into a list item:
- Leading/trailing newlines stripped
- Multiline content collapsed to single line (internal newlines → spaces)
- Empty lines between list items removed to prevent Quill from splitting into separate lists

---

## 20. LLM XML Writer: Instruction Collapsing

The `LlmXmlWriter` converts rich text to a structured XML format optimized for LLM consumption:

### Algorithm

1. Convert Delta → Markdown via the standard pipeline
2. Process Markdown line by line:
   - **List items** (`-`, `*`, `+`, numbered) → each becomes an `<instruction>` tag
   - **Non-list lines** → buffered, then collapsed to a single `<instruction>` with whitespace normalized to spaces
3. Strip markdown syntax: headers (`#`), bullets, checkboxes (`[ ]`/`[x]`), blockquotes (`>`)

### Output Structure

```xml
<instructions>
  <instruction>First collapsed paragraph text</instruction>
  <instruction>Each list item becomes its own instruction</instruction>
  <instruction>Another list item</instruction>
</instructions>
```

---

## 21. RBAC: Action-to-Permission Mapping

### How Controller Actions Map to Permissions

Each controller defines an action→permission map in its behaviors:

```php
// Example: NoteController
'checkClaudeConfig' → 'viewNote'
'streamClaude'      → 'viewNote'
'create'            → 'createNote'
'update'            → 'updateNote'
'delete'            → 'deleteNote'
```

### Ownership Chain Traversal

Owner rules navigate relationship chains to find the user:

| Rule | Chain |
|------|-------|
| `ProjectOwnerRule` | `project.user_id` |
| `ContextOwnerRule` | `context → project.user_id` |
| `FieldOwnerRule` | `field.user_id` |
| `PromptTemplateOwnerRule` | `template → project.user_id` |
| `PromptInstanceOwnerRule` | `instance → template → project.user_id` |
| `NoteOwnerRule` | `note.user_id` |

### Match Callback Pattern

Controllers use `matchCallback` in behaviors to load the model and pass it to the RBAC check:

```php
'matchCallback' => fn($rule, $action) =>
    $this->permissionService->checkPermission($action, $rule, $modelCallback)
```

---

## 22. ProjectContext: URL Injection Mechanism

### How Multi-Tab Support Works

`ProjectUrlManager` extends Yii's URL manager and automatically appends `?p=X` to **project-scoped routes**:

```
Project-scoped routes: context, field, prompt-template, prompt-instance, note
```

When generating a URL for any of these routes, the URL manager:
1. Checks if there's a current project context
2. If yes, appends `?p={projectId}` to the URL
3. Links in navigation automatically carry the project context

### Resolution Priority

```
1. URL parameter (?p=X)    ← checked first, enables multi-tab isolation
2. Session value            ← persists within a browser session
3. User preference          ← persisted default project
```

Different browser tabs can work with different projects simultaneously because each tab's links carry the `?p=X` parameter, and the URL parameter takes priority over the shared session.

### Sentinel Values

| ID | Meaning |
|----|---------|
| `> 0` | Specific project |
| `-1` | All Projects view |
| `0` | No Project (global entities only) |

---

## 23. Console Command Patterns

### Diagnostic Output

Console commands use color-coded output for scriptability:
- `Console::FG_GREEN` — success
- `Console::FG_RED` — error
- `Console::FG_YELLOW` — warning

All commands return `ExitCode::OK` (0) or `ExitCode::UNSPECIFIED_ERROR` (1).

### Claude Diagnostics

`ClaudeController::actionDiagnose()` checks:
- Claude CLI binary availability
- Path mappings validity
- Per-project config status and config source

### Batch Workspace Sync

`ClaudeController::actionSyncWorkspaces()` regenerates `CLAUDE.md` and `settings.local.json` for all projects in batch — useful after config schema changes.

### Sync Reporting

`SyncController` reports per-entity statistics:
```
project:  +2 inserted, ~1 updated, =5 skipped, !!0 errors
context:  +8 inserted, ~3 updated, =12 skipped, !!0 errors
```
