# AI Provider Abstraction Layer — Refactoring Plan

## Problem Statement

The AI chat feature is hardwired to the Claude CLI at every layer. "Claude" appears as a hardcoded dependency in **22+ files** across services, models, controllers, views, CSS, JS, enums, config, RBAC, and database columns. Adding a second provider (Codex, Gemini) today would require touching every one of these files.

This plan introduces a provider abstraction layer that allows new AI CLI providers to be plugged in by implementing an interface and registering in the DI container — without modifying any existing controller, view, or model code.

---

## Coupling Audit — Current State

Every file below contains hardcoded Claude references that would need to change to support a second provider:

### Service Layer
| File | Coupling |
|---|---|
| `yii/services/ClaudeCliService.php` | Shells out to `claude` binary (line 222). Builds Claude-specific flags: `--permission-mode`, `--model`, `--allowedTools`, `--continue`. Parses Claude JSON output schema (`modelUsage`, `session_id`). |
| `yii/services/ClaudeWorkspaceService.php` | Generates `CLAUDE.md` and `.claude/settings.local.json`. Storage path `@app/storage/projects/{id}`. |

### Model Layer
| File | Coupling |
|---|---|
| `yii/models/Project.php` | DB columns: `claude_options` (JSON), `claude_context` (TEXT). Accessors: `getClaudeOptions()`, `setClaudeOptions()`, `getClaudeOption()`, `getClaudeContext()`, `setClaudeContext()`, `hasClaudeContext()`, `getClaudeContextAsMarkdown()`. `afterSave()` calls `Yii::$app->claudeWorkspaceService->syncConfig()` (line 540). `afterDelete()` calls `deleteWorkspace()` (line 552). Labels: "Claude CLI Options", "Claude Project Context" (lines 140-141). |

### Controller Layer
| File | Coupling |
|---|---|
| `yii/controllers/ScratchPadController.php` | Injects `ClaudeCliService` via constructor (line 38). Actions: `actionClaude()` (line 416), `actionRunClaude()` (line 432). Routes: `/scratch-pad/claude`, `/scratch-pad/run-claude`. |
| `yii/controllers/ProjectController.php` | Injects `ClaudeCliService` (line 32). `actionCheckClaudeConfig()` (line 237). `loadClaudeOptions()` (line 276). |
| `yii/commands/ClaudeController.php` | Class name is `ClaudeController`. Commands: `claude/sync-workspaces`, `claude/diagnose`. |

### View Layer
| File | Coupling |
|---|---|
| `yii/views/scratch-pad/claude.php` | 870-line file. Hardcoded: model dropdown (sonnet/opus/haiku), permission mode dropdown, `window.ClaudeChat` JS object, all `claude-*` CSS classes, "Claude CLI" title and headers, `run-claude` fetch URL. |
| `yii/views/project/_form.php` | "Claude CLI Defaults" card header (line 64), "Claude Project Context" card header (line 123), `claude_options[*]` POST field names, hardcoded model/permission dropdowns. |

### CSS
| File | Coupling |
|---|---|
| `yii/web/css/claude-chat.css` | 275 lines, all scoped under `.claude-chat-page`, `.claude-message`, `.claude-thinking-dots`, etc. |

### Config & RBAC
| File | Coupling |
|---|---|
| `yii/config/main.php` | `claudeWorkspaceService` component (line 140). |
| `yii/config/rbac.php` | `runClaude` and `claude` action permission mappings. |

### Enum
| File | Coupling |
|---|---|
| `yii/common/enums/ClaudePermissionMode.php` | Claude-specific permission mode values. |

### Tests
| File | Coupling |
|---|---|
| `yii/tests/unit/services/ClaudeCliServiceTest.php` | Tests Claude CLI command building and output parsing. |
| `yii/tests/unit/controllers/ProjectControllerTest.php` | Tests `checkClaudeConfig` action. |

---

## Comparative Analysis: Three CLIs

| Capability | Claude CLI | Codex CLI | Gemini CLI |
|---|---|---|---|
| **Binary** | `claude` | `codex` | `gemini` |
| **Prompt via stdin** | `claude -p -` | `codex exec -` | `gemini -p -` |
| **JSON output** | `--output-format json` | `--json` (JSONL) | `--output-format json` |
| **Model selection** | `--model sonnet` | `--model gpt-5-codex` | (uses config) |
| **Permission/approval** | `--permission-mode plan` | `--approval-mode suggest` | `--approval-mode` / `-y` |
| **Session continue** | `--continue <id>` | `codex exec resume <id>` | (no native session) |
| **System prompt** | `--append-system-prompt "..."` | `-c system_prompt="..."` | (via config) |
| **Tool allow/deny** | `--allowedTools` / `--disallowedTools` | (via execpolicy) | (via extension config) |
| **Config files** | `CLAUDE.md`, `.claude/` | `codex.md`, `~/.codex/config.toml` | `.gemini/settings.json` |
| **Output schema** | `{result, modelUsage, session_id}` | JSONL events `{type, ...}` | `{result, usage}` |

### What's Common (Extract to Base)

1. **Process execution**: `proc_open` → write stdin → poll with timeout → read stdout/stderr. Identical for all CLIs.
2. **Path translation**: Host→container path mapping via `PATH_MAPPINGS`. Provider-agnostic.
3. **Working directory resolution**: Check own dir → managed workspace → default. Shared logic, but "has config" check is provider-specific.
4. **Format conversion**: Quill Delta → Markdown. All providers receive markdown. Not provider-specific at all.
5. **Workspace storage structure**: `@app/storage/projects/{id}/`. The path layout is shared; only the generated files differ.

### What's Provider-Specific (Keep in Implementations)

1. **Command construction**: Binary name, flag syntax, flag values.
2. **Output parsing**: JSON schema, token field names, model ID formatting.
3. **Permission mode vocabulary**: plan/dontAsk vs suggest/auto-edit vs approve/auto.
4. **Model vocabulary**: sonnet/opus/haiku vs gpt-5-codex/o4-mini vs gemini-pro.
5. **Config file generation**: CLAUDE.md vs codex.md vs .gemini/settings.json.
6. **Session semantics**: `--continue <id>` vs `resume <id>` vs no sessions.

---

## Architecture

### Layer Diagram

```
+-----------------------------------------------------+
|                   Views / JS                         |
|  (provider-agnostic: "AI Chat", dynamic dropdowns)   |
+-----------------------------------------------------+
|             ScratchPadController                     |
|  actionAiChat(), actionRunAi()                       |
|  (resolves provider from project, delegates)         |
+-----------------------------------------------------+
|            AiProviderInterface                       |  <-- NEW
|  execute(), getCapabilities()                        |
+--------+--------------+-----------------------------+
| Claude |    Codex     |     Gemini                   |  <-- provider impls
|Provider|   Provider   |    Provider                  |
+--------+--------------+-----------------------------+
|        AbstractCliProvider                           |  <-- NEW (shared process exec)
|  runProcess(), translatePath(), resolveWorkDir()     |
+--------+--------------+-----------------------------+
|          AiWorkspaceInterface                        |  <-- NEW
|  syncConfig(), deleteWorkspace(), hasConfig()        |
+--------+--------------+-----------------------------+
| Claude |    Codex     |     Gemini                   |
| Wksp   |   Wksp      |    Wksp                      |
+--------+--------------+-----------------------------+
|               Project Model                          |
|  ai_provider_options (JSON), ai_context (TEXT)       |
+-----------------------------------------------------+
```

### Key Design Decisions

1. **`AbstractCliProvider` base class** — Extracts the process execution loop (`proc_open`, stdin, timeout, stream polling) from `ClaudeCliService.execute()` (lines 85-166). All CLI providers share this. Each provider only implements `buildCommand()` and `parseOutput()`.

2. **`convertToPrompt()` stays outside the provider interface** — Quill Delta → Markdown conversion is pre-provider. It uses the existing `CopyFormatConverter` and is called by the controller before passing the prompt string to the provider. Providers receive a plain string.

3. **Provider key stored in `ai_provider_options` JSON** — Instead of a separate column, the provider key is embedded: `{"provider":"claude","model":"sonnet",...}`. Default is `claude` when missing.

4. **Capabilities DTO drives the UI** — Each provider returns an `AiProviderCapabilities` object. The view renders dropdowns, toggle sections, and labels entirely from this DTO. No provider-specific logic in views.

5. **Old routes kept as redirects** — `actionClaude()` redirects to `actionAiChat()`, `actionRunClaude()` delegates to `actionRunAi()`. Bookmarks and external links don't break.

6. **`ClaudeCliService` stays as-is** — It becomes the internal implementation detail of `ClaudeProvider`. No refactoring of its internals. The adapter wraps it.

---

## Core Interfaces

### `AiProviderInterface`

```php
namespace app\services\ai;

use app\models\Project;

interface AiProviderInterface
{
    /**
     * Unique provider key (e.g. 'claude', 'codex', 'gemini').
     */
    public function getKey(): string;

    /**
     * Human-readable display name.
     */
    public function getDisplayName(): string;

    /**
     * Provider capabilities for dynamic UI rendering.
     */
    public function getCapabilities(): AiProviderCapabilities;

    /**
     * Execute a prompt (blocking) and return normalized result.
     */
    public function execute(
        string $prompt,
        string $workingDirectory,
        int $timeout = 300,
        array $options = [],
        ?Project $project = null,
        ?string $sessionId = null
    ): AiExecutionResult;

    /**
     * Check if a directory has this provider's configuration files.
     *
     * @return array{hasAnyConfig: bool, details: array<string, bool>, pathStatus: string}
     */
    public function checkConfigForPath(string $hostPath): array;
}
```

### `AiProviderCapabilities` DTO

```php
namespace app\services\ai;

class AiProviderCapabilities
{
    public function __construct(
        /** @var array<string, string> value => label, e.g. ['sonnet' => 'Sonnet'] */
        public readonly array $models,
        /** @var array<string, string> value => label */
        public readonly array $permissionModes,
        public readonly bool $supportsSessionContinuation,
        public readonly bool $supportsToolAllowDeny,
        public readonly bool $supportsSystemPromptAppend,
        public readonly bool $supportsStreaming,
        /** @var int Max context window in tokens (for usage % display) */
        public readonly int $maxContextTokens = 200_000,
    ) {}
}
```

### `AiExecutionResult` DTO

Replaces the loosely-typed return array from `ClaudeCliService.execute()`:

```php
namespace app\services\ai;

class AiExecutionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output,
        public readonly string $error,
        public readonly int $exitCode,
        public readonly ?int $durationMs = null,
        public readonly ?string $model = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?int $cacheTokens = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $configSource = null,
        public readonly string $requestedPath = '',
        public readonly string $effectivePath = '',
        public readonly bool $usedFallback = false,
        /** @var array Provider-specific metadata not covered by standard fields */
        public readonly array $meta = [],
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'error' => $this->error,
            'exitCode' => $this->exitCode,
            'duration_ms' => $this->durationMs,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_tokens' => $this->cacheTokens,
            'sessionId' => $this->sessionId,
            'configSource' => $this->configSource,
            'requestedPath' => $this->requestedPath,
            'effectivePath' => $this->effectivePath,
            'usedFallback' => $this->usedFallback,
        ];
    }
}
```

### `AiWorkspaceInterface`

```php
namespace app\services\ai;

use app\models\Project;

interface AiWorkspaceInterface
{
    public function getProviderKey(): string;
    public function getWorkspacePath(Project $project): string;
    public function getDefaultWorkspacePath(): string;
    public function ensureWorkspace(Project $project): string;
    public function syncConfig(Project $project): void;
    public function deleteWorkspace(Project $project): void;
}
```

### `AbstractCliProvider`

Extracts the shared process execution from `ClaudeCliService.execute()` lines 85-166:

```php
namespace app\services\ai;

use app\models\Project;
use Yii;

abstract class AbstractCliProvider implements AiProviderInterface
{
    /**
     * Build the shell command string for this provider.
     * Each provider constructs its own binary + flags.
     */
    abstract protected function buildCommand(array $options, ?string $sessionId = null): string;

    /**
     * Parse the raw stdout into normalized fields.
     * Each provider has its own JSON schema.
     *
     * @return array{result?: string, is_error?: bool, ...}
     */
    abstract protected function parseOutput(string $stdout): array;

    /**
     * Get the workspace service for this provider.
     */
    abstract protected function getWorkspaceService(): AiWorkspaceInterface;

    /**
     * Check if a container path has this provider's config files.
     *
     * @return array{hasAnyConfig: bool, details: array<string, bool>}
     */
    abstract protected function detectConfig(string $containerPath): array;

    /**
     * Shared process execution — identical for all CLI providers.
     */
    protected function runProcess(
        string $command,
        string $prompt,
        string $cwd,
        int $timeout
    ): array {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
        if (!is_resource($process))
            return ['success' => false, 'output' => '', 'error' => 'Failed to start process', 'exitCode' => 1];

        fwrite($pipes[0], $prompt);
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $error = '';
        $startTime = time();

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $output .= stream_get_contents($pipes[1]);
                $error .= stream_get_contents($pipes[2]);
                break;
            }
            if ((time() - $startTime) > $timeout) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return ['success' => false, 'output' => $output, 'error' => "Timed out after {$timeout}s", 'exitCode' => 124];
            }
            $output .= fread($pipes[1], 8192);
            $error .= fread($pipes[2], 8192);
            usleep(100000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return ['output' => trim($output), 'error' => trim($error), 'exitCode' => $status['exitcode']];
    }

    /**
     * Shared host-to-container path translation.
     */
    protected function translatePath(string $hostPath): string
    {
        $mappings = Yii::$app->params['pathMappings'] ?? [];
        foreach ($mappings as $hostPrefix => $containerPrefix) {
            if (str_starts_with($hostPath, $hostPrefix))
                return $containerPrefix . substr($hostPath, strlen($hostPrefix));
        }
        return $hostPath;
    }

    /**
     * Shared working directory resolution logic.
     */
    protected function resolveWorkingDirectory(string $requestedDir, ?Project $project): string
    {
        $containerPath = $this->translatePath($requestedDir);
        if (is_dir($containerPath)) {
            $config = $this->detectConfig($containerPath);
            if ($config['hasAnyConfig'])
                return $requestedDir;
        }

        if ($project !== null) {
            $ws = $this->getWorkspaceService();
            $path = $ws->getWorkspacePath($project);
            return is_dir($path) ? $path : $ws->ensureWorkspace($project);
        }

        return $this->getWorkspaceService()->getDefaultWorkspacePath();
    }
}
```

### `AiProviderRegistry`

```php
namespace app\services\ai;

use InvalidArgumentException;

class AiProviderRegistry
{
    /** @var AiProviderInterface[] keyed by provider key */
    private array $providers = [];
    private string $defaultKey = 'claude';

    public function register(AiProviderInterface $provider): void
    {
        $this->providers[$provider->getKey()] = $provider;
    }

    public function get(string $key): AiProviderInterface
    {
        if (!isset($this->providers[$key]))
            throw new InvalidArgumentException("Unknown AI provider: {$key}");
        return $this->providers[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /** @return AiProviderInterface[] */
    public function getAll(): array
    {
        return $this->providers;
    }

    public function getDefault(): AiProviderInterface
    {
        return $this->get($this->defaultKey);
    }

    /**
     * Returns provider list + capabilities for UI rendering.
     *
     * @return array<string, array{name: string, capabilities: AiProviderCapabilities}>
     */
    public function getUiMetadata(): array
    {
        $result = [];
        foreach ($this->providers as $key => $provider) {
            $result[$key] = [
                'name' => $provider->getDisplayName(),
                'capabilities' => $provider->getCapabilities(),
            ];
        }
        return $result;
    }
}
```

### `AiWorkspaceManager`

Orchestrates workspace operations across providers:

```php
namespace app\services\ai;

use app\models\Project;

class AiWorkspaceManager
{
    /** @var AiWorkspaceInterface[] keyed by provider key */
    private array $workspaces = [];

    public function register(AiWorkspaceInterface $workspace): void
    {
        $this->workspaces[$workspace->getProviderKey()] = $workspace;
    }

    public function syncForProject(Project $project): void
    {
        $key = $project->getAiProviderKey();
        if (isset($this->workspaces[$key]))
            $this->workspaces[$key]->syncConfig($project);
    }

    public function deleteForProject(Project $project): void
    {
        // Delete workspaces for ALL providers (project may have switched providers)
        foreach ($this->workspaces as $workspace)
            $workspace->deleteWorkspace($project);
    }
}
```

---

## Refactoring Phases

### Phase 1: Introduce Abstraction Layer (Non-Breaking, Additive Only)

**Goal**: Create interfaces, DTOs, registry, and the Claude adapter. Existing code unchanged.

#### Step 1.1: Create directory structure and interfaces

**New files:**
```
yii/services/ai/
    AiProviderInterface.php
    AiProviderCapabilities.php
    AiExecutionResult.php
    AiWorkspaceInterface.php
    AbstractCliProvider.php
    AiProviderRegistry.php
    AiWorkspaceManager.php
    providers/
        ClaudeProvider.php
        ClaudeWorkspace.php
```

#### Step 1.2: Implement `ClaudeProvider`

Thin adapter that wraps `ClaudeCliService`:

```php
namespace app\services\ai\providers;

use app\services\ai\AbstractCliProvider;
use app\services\ai\AiProviderCapabilities;
use app\services\ai\AiExecutionResult;
use app\services\ai\AiWorkspaceInterface;
use app\services\ClaudeCliService;
use app\models\Project;

class ClaudeProvider extends AbstractCliProvider
{
    public function __construct(
        private readonly ClaudeCliService $cliService,
        private readonly ClaudeWorkspace $workspace
    ) {}

    public function getKey(): string { return 'claude'; }
    public function getDisplayName(): string { return 'Claude'; }

    public function getCapabilities(): AiProviderCapabilities
    {
        return new AiProviderCapabilities(
            models: [
                '' => '(Use default)',
                'sonnet' => 'Sonnet',
                'opus' => 'Opus',
                'haiku' => 'Haiku',
            ],
            permissionModes: [
                '' => '(Use default)',
                'plan' => 'Plan (restricted to planning)',
                'dontAsk' => "Don't Ask (fail on permission needed)",
                'bypassPermissions' => 'Bypass Permissions (auto-approve all)',
                'acceptEdits' => 'Accept Edits (auto-accept edits only)',
                'default' => 'Default (interactive, may hang)',
            ],
            supportsSessionContinuation: true,
            supportsToolAllowDeny: true,
            supportsSystemPromptAppend: true,
            supportsStreaming: true,
            maxContextTokens: 200_000,
        );
    }

    public function execute(
        string $prompt,
        string $workingDirectory,
        int $timeout = 300,
        array $options = [],
        ?Project $project = null,
        ?string $sessionId = null
    ): AiExecutionResult {
        // Delegate to existing ClaudeCliService (preserves all current behavior)
        $result = $this->cliService->execute(
            $prompt, $workingDirectory, $timeout, $options, $project, $sessionId
        );

        return new AiExecutionResult(
            success: $result['success'],
            output: $result['output'],
            error: $result['error'],
            exitCode: $result['exitCode'],
            durationMs: $result['duration_ms'] ?? null,
            model: $result['model'] ?? null,
            inputTokens: $result['input_tokens'] ?? null,
            outputTokens: $result['output_tokens'] ?? null,
            cacheTokens: $result['cache_tokens'] ?? null,
            sessionId: $result['session_id'] ?? null,
            configSource: $result['configSource'] ?? null,
            requestedPath: $result['requestedPath'] ?? '',
            effectivePath: $result['effectivePath'] ?? '',
            usedFallback: $result['usedFallback'] ?? false,
        );
    }

    public function checkConfigForPath(string $hostPath): array
    {
        return $this->cliService->checkClaudeConfigForPath($hostPath);
    }

    // Required by AbstractCliProvider but not used in delegation mode:
    protected function buildCommand(array $options, ?string $sessionId = null): string
    {
        // Not used — ClaudeProvider delegates to ClaudeCliService
        return '';
    }

    protected function parseOutput(string $stdout): array
    {
        return [];
    }

    protected function detectConfig(string $containerPath): array
    {
        return $this->cliService->hasClaudeConfig($containerPath);
    }

    protected function getWorkspaceService(): AiWorkspaceInterface
    {
        return $this->workspace;
    }
}
```

#### Step 1.3: Implement `ClaudeWorkspace`

```php
namespace app\services\ai\providers;

use app\services\ai\AiWorkspaceInterface;
use app\services\ClaudeWorkspaceService;
use app\models\Project;

class ClaudeWorkspace implements AiWorkspaceInterface
{
    public function __construct(
        private readonly ClaudeWorkspaceService $inner
    ) {}

    public function getProviderKey(): string { return 'claude'; }

    public function getWorkspacePath(Project $project): string
    {
        return $this->inner->getWorkspacePath($project);
    }

    public function getDefaultWorkspacePath(): string
    {
        return $this->inner->getDefaultWorkspacePath();
    }

    public function ensureWorkspace(Project $project): string
    {
        return $this->inner->ensureWorkspace($project);
    }

    public function syncConfig(Project $project): void
    {
        $this->inner->syncConfig($project);
    }

    public function deleteWorkspace(Project $project): void
    {
        $this->inner->deleteWorkspace($project);
    }
}
```

#### Step 1.4: Register in DI container

**File:** `yii/config/main.php` — add alongside existing `claudeWorkspaceService`:

```php
'aiProviderRegistry' => function () {
    $registry = new \app\services\ai\AiProviderRegistry();
    $claudeWorkspace = new \app\services\ai\providers\ClaudeWorkspace(
        Yii::$app->claudeWorkspaceService
    );
    $registry->register(new \app\services\ai\providers\ClaudeProvider(
        new \app\services\ClaudeCliService(null, Yii::$app->claudeWorkspaceService),
        $claudeWorkspace
    ));
    return $registry;
},
'aiWorkspaceManager' => function () {
    $manager = new \app\services\ai\AiWorkspaceManager();
    $manager->register(new \app\services\ai\providers\ClaudeWorkspace(
        Yii::$app->claudeWorkspaceService
    ));
    return $manager;
},
```

#### Step 1.5: Tests

**New files:**
- `yii/tests/unit/services/ai/AiProviderRegistryTest.php` — register, get, getAll, default, unknown throws
- `yii/tests/unit/services/ai/providers/ClaudeProviderTest.php` — capabilities, execute delegation, config check delegation

**Validation**: All existing tests pass. No behavior change. Phase 1 is purely additive.

---

### Phase 2: Database Schema Migration

**Goal**: Rename Claude-specific columns to provider-agnostic names. Embed provider key in JSON.

#### Step 2.1: Migration

**New file:** `yii/migrations/m260201_000001_rename_claude_columns_to_ai_provider.php`

```php
public function safeUp(): void
{
    // Rename columns
    $this->renameColumn('{{%project}}', 'claude_options', 'ai_provider_options');
    $this->renameColumn('{{%project}}', 'claude_context', 'ai_context');

    // Embed provider key in existing JSON data
    $rows = (new \yii\db\Query())
        ->select(['id', 'ai_provider_options'])
        ->from('{{%project}}')
        ->where(['not', ['ai_provider_options' => null]])
        ->all();

    foreach ($rows as $row) {
        $options = json_decode($row['ai_provider_options'], true);
        if (is_array($options) && !isset($options['provider'])) {
            $options['provider'] = 'claude';
            $this->update('{{%project}}', [
                'ai_provider_options' => json_encode($options),
            ], ['id' => $row['id']]);
        }
    }
}

public function safeDown(): void
{
    // Strip provider key from JSON
    $rows = (new \yii\db\Query())
        ->select(['id', 'ai_provider_options'])
        ->from('{{%project}}')
        ->where(['not', ['ai_provider_options' => null]])
        ->all();

    foreach ($rows as $row) {
        $options = json_decode($row['ai_provider_options'], true);
        if (is_array($options)) {
            unset($options['provider']);
            $this->update('{{%project}}', [
                'ai_provider_options' => json_encode($options) ?: null,
            ], ['id' => $row['id']]);
        }
    }

    $this->renameColumn('{{%project}}', 'ai_provider_options', 'claude_options');
    $this->renameColumn('{{%project}}', 'ai_context', 'claude_context');
}
```

Run on both schemas per workflow rules.

#### Step 2.2: Update Project model

**File:** `yii/models/Project.php`

Changes:
- Replace `claude_options` → `ai_provider_options` in `@property`, `rules()`, `attributeLabels()`
- Replace `claude_context` → `ai_context` in `@property`, `rules()`, `attributeLabels()`
- Rename accessors:

```php
// New primary accessors
public function getAiProviderOptions(): array { /* reads ai_provider_options JSON */ }
public function setAiProviderOptions(array|string|null $value): void { /* writes JSON */ }
public function getAiProviderKey(): string { return $this->getAiProviderOptions()['provider'] ?? 'claude'; }
public function getAiProviderOption(string $key, mixed $default = null): mixed { ... }

public function getAiContext(): ?string { return $this->ai_context; }
public function setAiContext(?string $value): void { ... }
public function hasAiContext(): bool { ... }
public function getAiContextAsMarkdown(): string { ... }

// Deprecated aliases (keep for transition, remove in future cleanup)
/** @deprecated Use getAiProviderOptions() */
public function getClaudeOptions(): array { return $this->getAiProviderOptions(); }
/** @deprecated Use setAiProviderOptions() */
public function setClaudeOptions(array|string|null $value): void { $this->setAiProviderOptions($value); }
/** @deprecated Use getAiProviderOption() */
public function getClaudeOption(string $key, mixed $default = null): mixed { return $this->getAiProviderOption($key, $default); }
/** @deprecated Use getAiContext() */
public function getClaudeContext(): ?string { return $this->getAiContext(); }
/** @deprecated Use hasAiContext() */
public function hasClaudeContext(): bool { return $this->hasAiContext(); }
/** @deprecated Use getAiContextAsMarkdown() */
public function getClaudeContextAsMarkdown(): string { return $this->getAiContextAsMarkdown(); }
```

Update attribute labels:
```php
'ai_provider_options' => 'AI Provider Options',
'ai_context' => 'AI Project Context',
```

Update `afterSave()` (line 523-544):
```php
// Replace hardcoded claudeWorkspaceService call:
$relevantFields = ['ai_context', 'ai_provider_options', 'name', 'allowed_file_extensions', 'blacklisted_directories'];
// ...
Yii::$app->aiWorkspaceManager->syncForProject($this);
```

Update `afterDelete()` (line 547-556):
```php
Yii::$app->aiWorkspaceManager->deleteForProject($this);
```

---

### Phase 3: Controller Refactoring

#### Step 3.1: ScratchPadController

**File:** `yii/controllers/ScratchPadController.php`

Constructor change:
```php
// Before:
private readonly ClaudeCliService $claudeCliService;

// After:
private readonly AiProviderRegistry $providerRegistry;
private readonly CopyFormatConverter $formatConverter;
```

New actions (alongside old ones during transition):

```php
public function actionAiChat(int $id): string
{
    $model = $this->findModel($id);
    if ($model->project_id === null)
        throw new NotFoundHttpException('AI Chat requires a project.');

    $project = $model->project;
    $providerKey = $project->getAiProviderKey();
    $provider = $this->providerRegistry->get($providerKey);

    return $this->render('ai-chat', [
        'model' => $model,
        'providerKey' => $providerKey,
        'providerName' => $provider->getDisplayName(),
        'capabilities' => $provider->getCapabilities(),
        'projectDefaults' => $project->getAiProviderOptions(),
    ]);
}

public function actionRunAi(int $id): array
{
    Yii::$app->response->format = Response::FORMAT_JSON;
    $model = $this->findModel($id);

    $requestOptions = json_decode(Yii::$app->request->rawBody, true) ?? [];
    // ... same prompt resolution logic (customPrompt / contentDelta / scratchpad content) ...
    // ... format conversion via $this->formatConverter->convertFromQuillDelta() ...

    $project = $model->project;
    $providerKey = $project?->getAiProviderKey() ?? 'claude';
    $provider = $this->providerRegistry->get($providerKey);

    $projectDefaults = $project !== null ? $project->getAiProviderOptions() : [];
    // Merge options (same whitelist logic)

    $result = $provider->execute($markdown, $workingDirectory, 300, $options, $project, $sessionId);

    return array_merge($result->toArray(), ['promptMarkdown' => $markdown]);
}
```

Old actions become redirects/aliases:
```php
public function actionClaude(int $id): Response
{
    return $this->redirect(['ai-chat', 'id' => $id]);
}

public function actionRunClaude(int $id): array
{
    return $this->actionRunAi($id);
}
```

#### Step 3.2: ProjectController

**File:** `yii/controllers/ProjectController.php`

Constructor: replace `ClaudeCliService` with `AiProviderRegistry`.

Rename `actionCheckClaudeConfig()` → `actionCheckAiConfig()`:
```php
public function actionCheckAiConfig(int $id): array
{
    // ... same logic but resolves provider from project ...
    $providerKey = $model->getAiProviderKey();
    $provider = $this->providerRegistry->get($providerKey);
    $configStatus = $provider->checkConfigForPath($model->root_directory);
    // ...
}

// Keep alias:
public function actionCheckClaudeConfig(int $id): array
{
    return $this->actionCheckAiConfig($id);
}
```

Rename `loadClaudeOptions()` → `loadAiProviderOptions()`:
```php
private function loadAiProviderOptions(Project $model): void
{
    $aiOptions = Yii::$app->request->post('ai_provider_options', []);
    $model->setAiProviderOptions($aiOptions);
}
```

#### Step 3.3: Update RBAC

**File:** `yii/config/rbac.php`

```php
'scratchPad' => [
    'actionPermissionMap' => [
        // ... existing entries ...
        'aiChat' => 'viewScratchPad',
        'runAi' => 'viewScratchPad',
        // Keep old mappings for aliases:
        'claude' => 'viewScratchPad',
        'runClaude' => 'viewScratchPad',
    ],
],
```

---

### Phase 4: View & CSS Refactoring

#### Step 4.1: Create new view file

**New file:** `yii/views/scratch-pad/ai-chat.php`

Based on `claude.php`, with these changes:

1. **Dynamic dropdowns** from capabilities:
   ```php
   // Before (hardcoded):
   $models = ['' => '(Use default)', 'sonnet' => 'Sonnet', ...];
   // After:
   $models = $capabilities->models;
   $permissionModes = $capabilities->permissionModes;
   ```

2. **Conditional sections** based on capabilities:
   ```php
   <?php if ($capabilities->supportsToolAllowDeny): ?>
       <!-- allowed/disallowed tools inputs -->
   <?php endif; ?>
   <?php if ($capabilities->supportsSystemPromptAppend): ?>
       <!-- system prompt textarea -->
   <?php endif; ?>
   ```

3. **Generic titles and headers**:
   ```php
   $this->title = Html::encode($providerName) . ' Chat';
   // Header: "<i class='bi bi-terminal-fill'></i> {providerName} Chat"
   ```

4. **JS object rename**: `window.ClaudeChat` → `window.AiChat`

5. **CSS class rename**: All `.claude-*` → `.ai-*`

6. **Fetch URL**: `run-claude` → `run-ai`

7. **Response header**: `<i class="bi bi-terminal-fill"></i> Claude` → dynamic based on provider name

8. **Max context tokens**: Use `capabilities.maxContextTokens` instead of hardcoded `200000`

#### Step 4.2: CSS rename

**Rename file:** `yii/web/css/claude-chat.css` → `yii/web/css/ai-chat.css`

Bulk rename all CSS classes:
| Old | New |
|---|---|
| `.claude-chat-page` | `.ai-chat-page` |
| `.claude-settings-summary` | `.ai-settings-summary` |
| `.claude-prompt-section` | `.ai-prompt-section` |
| `.claude-editor-compact` | `.ai-editor-compact` |
| `.claude-followup-textarea` | `.ai-followup-textarea` |
| `.claude-conversation__empty` | `.ai-conversation__empty` |
| `.claude-message` | `.ai-message` |
| `.claude-message--user` | `.ai-message--user` |
| `.claude-message--claude` | `.ai-message--assistant` |
| `.claude-message--loading` | `.ai-message--loading` |
| `.claude-message--error` | `.ai-message--error` |
| `.claude-message__header` | `.ai-message__header` |
| `.claude-message__body` | `.ai-message__body` |
| `.claude-message__meta` | `.ai-message__meta` |
| `.claude-thinking-dots` | `.ai-thinking-dots` |
| `.claude-history-item__*` | `.ai-history-item__*` |
| `@keyframes claude-dot-pulse` | `@keyframes ai-dot-pulse` |

#### Step 4.3: Project form update

**File:** `yii/views/project/_form.php`

1. Card headers:
   - "Claude CLI Defaults" → "AI Provider Defaults"
   - "Claude Project Context" → "AI Project Context"

2. Add provider selector dropdown:
   ```php
   <label class="form-label">AI Provider</label>
   <?= Html::dropDownList('ai_provider_options[provider]', $aiOptions['provider'] ?? 'claude', $providerList, [
       'id' => 'ai-provider-selector',
       'class' => 'form-select',
   ]) ?>
   ```

3. POST field names: `claude_options[*]` → `ai_provider_options[*]`

4. JavaScript: Swap model/permission dropdowns when provider selection changes (AJAX to get capabilities for selected provider, or embed all capabilities as JSON).

#### Step 4.4: Update links in other views

Update any view that links to the Claude chat page:
- `yii/views/scratch-pad/view.php` — link target `['claude', 'id' => $model->id]` → `['ai-chat', 'id' => $model->id]`

---

### Phase 5: Console Command Generalization

**Rename:** `yii/commands/ClaudeController.php` → `yii/commands/AiController.php`

```bash
./yii ai/sync-workspaces              # Sync workspaces for all providers
./yii ai/diagnose                     # Check all provider CLIs
./yii ai/diagnose --provider=claude   # Check specific provider
```

Constructor: inject `AiProviderRegistry` + `AiWorkspaceManager`.

`actionSyncWorkspaces()` iterates all projects and calls `$aiWorkspaceManager->syncForProject($project)`.

`actionDiagnose()` iterates registered providers, checks binary availability and project config status.

---

### Phase 6: Cleanup

1. **Remove deprecated aliases** from Project model (after confirming no external code uses old method names).
2. **Delete old view file** `yii/views/scratch-pad/claude.php` (after redirect is in place).
3. **Delete old CSS file** `yii/web/css/claude-chat.css`.
4. **Remove old `claudeWorkspaceService` component** from `main.php` (after all references use `aiWorkspaceManager`).
5. **Remove old action redirects** from controllers (after link update).

---

### Phase 7: Add Second Provider (Validates Abstraction)

#### Step 7.1: `CodexProvider`

**New files:**
- `yii/services/ai/providers/CodexProvider.php`
- `yii/services/ai/providers/CodexWorkspace.php`
- `yii/tests/unit/services/ai/providers/CodexProviderTest.php`

```php
class CodexProvider extends AbstractCliProvider
{
    public function getKey(): string { return 'codex'; }
    public function getDisplayName(): string { return 'Codex'; }

    public function getCapabilities(): AiProviderCapabilities
    {
        return new AiProviderCapabilities(
            models: ['' => '(Use default)', 'gpt-5-codex' => 'GPT-5 Codex', 'o4-mini' => 'o4-mini'],
            permissionModes: [
                '' => '(Use default)',
                'suggest' => 'Suggest (ask before executing)',
                'auto-edit' => 'Auto Edit (auto-approve edits)',
                'full-auto' => 'Full Auto (approve everything)',
            ],
            supportsSessionContinuation: true,
            supportsToolAllowDeny: false,
            supportsSystemPromptAppend: true,
            supportsStreaming: true,
            maxContextTokens: 192_000,
        );
    }

    protected function buildCommand(array $options, ?string $sessionId = null): string
    {
        $cmd = 'codex exec --json';
        // ... Codex-specific flag construction ...
        $cmd .= ' -';
        return $cmd;
    }

    protected function parseOutput(string $stdout): array
    {
        // Parse JSONL events, extract final result
    }
}
```

#### Step 7.2: Register in DI

```php
$registry->register(new CodexProvider());
```

That's it — the second provider appears in the UI automatically via `getCapabilities()`.

---

## File Impact Summary

### New Files

| File | Phase | Purpose |
|---|---|---|
| `yii/services/ai/AiProviderInterface.php` | 1 | Core provider contract |
| `yii/services/ai/AiProviderCapabilities.php` | 1 | Typed capabilities DTO |
| `yii/services/ai/AiExecutionResult.php` | 1 | Typed execution result DTO |
| `yii/services/ai/AiWorkspaceInterface.php` | 1 | Workspace contract |
| `yii/services/ai/AbstractCliProvider.php` | 1 | Shared process execution |
| `yii/services/ai/AiProviderRegistry.php` | 1 | Provider registry |
| `yii/services/ai/AiWorkspaceManager.php` | 1 | Workspace orchestrator |
| `yii/services/ai/providers/ClaudeProvider.php` | 1 | Claude adapter |
| `yii/services/ai/providers/ClaudeWorkspace.php` | 1 | Claude workspace adapter |
| `yii/migrations/m260201_*_rename_claude_columns.php` | 2 | Schema migration |
| `yii/views/scratch-pad/ai-chat.php` | 4 | Provider-agnostic chat view |
| `yii/web/css/ai-chat.css` | 4 | Provider-agnostic CSS |
| `yii/tests/unit/services/ai/AiProviderRegistryTest.php` | 1 | Registry tests |
| `yii/tests/unit/services/ai/providers/ClaudeProviderTest.php` | 1 | Claude adapter tests |

### Modified Files

| File | Phase | Change |
|---|---|---|
| `yii/models/Project.php` | 2 | Rename accessors, add `getAiProviderKey()`, update hooks |
| `yii/controllers/ScratchPadController.php` | 3 | Replace CLI service with registry, add new actions, keep old as aliases |
| `yii/controllers/ProjectController.php` | 3 | Replace CLI service with registry, rename methods |
| `yii/views/project/_form.php` | 4 | Provider selector, dynamic options, rename labels |
| `yii/views/scratch-pad/view.php` | 4 | Update chat link |
| `yii/config/main.php` | 1 | Add `aiProviderRegistry` + `aiWorkspaceManager` components |
| `yii/config/rbac.php` | 3 | Add new action permission mappings |
| `yii/commands/ClaudeController.php` → `AiController.php` | 5 | Generalize commands |
| `yii/tests/unit/controllers/ProjectControllerTest.php` | 3 | Update for renamed methods |

### Untouched Files

| File | Reason |
|---|---|
| `yii/services/ClaudeCliService.php` | Stays as-is; internal to `ClaudeProvider` |
| `yii/services/ClaudeWorkspaceService.php` | Stays as-is; internal to `ClaudeWorkspace` |
| `yii/common/enums/ClaudePermissionMode.php` | Internal to Claude provider implementation |
| `yii/tests/unit/services/ClaudeCliServiceTest.php` | Tests internals, unchanged |
| `.env.example` | Path mappings already provider-agnostic |
| `docker-compose.yml` | Deployment config |

---

## Implementation Order & Dependencies

```
Phase 1 ─── Interfaces + Registry + ClaudeProvider (additive, non-breaking)
  |           All existing tests pass. Zero behavior change.
  |           Can be merged and deployed independently.
  v
Phase 2 ─── Database migration + Project model
  |           Migration runs on both schemas.
  |           Deprecated aliases ensure backward compat.
  v
Phase 3 ─── Controller refactoring
  |           New actions + old redirects. Both routes work.
  |           Depends on Phase 2 (Project model accessors).
  v
Phase 4 ─── Views + CSS
  |           New view file renders from capabilities.
  |           Depends on Phase 3 (controller passes capabilities).
  v
Phase 5 ─── Console command generalization
  |           Depends on Phase 1 (registry).
  v
Phase 6 ─── Cleanup (remove old aliases, files)
  |           Depends on all above being deployed + stable.
  v
Phase 7 ─── Add second provider (validates the abstraction)
              Depends on Phase 1. Can happen in parallel with 2-6.
```

---

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Over-abstraction for hypothetical providers | Phase 7 validates with a real provider. If only Claude is ever used, the abstraction adds minimal overhead (thin adapter). |
| Migration data loss | `safeUp()`/`safeDown()` with column rename + JSON wrapper. Fully reversible. |
| Broken bookmarks/links | Old actions redirect to new ones. Remove redirects only in Phase 6 after confirming. |
| Provider-specific UX leaking into views | `getCapabilities()` drives all conditional rendering. Views have zero provider-specific logic. |
| Different output schemas | Each provider normalizes to `AiExecutionResult` in its own `execute()`. Controller never sees raw provider output. |
| Session semantics differ | Interface uses `?string $sessionId`. Providers that don't support sessions ignore it and return `null`. |
| Streaming differences (future) | Left out of initial interface. Add `executeStreaming()` when the streaming feature is built, before adding providers. |

---

## What NOT to Abstract

1. **`ClaudeCliService` internals** — Don't refactor into a generic "CLI runner". Keep it as the Claude implementation detail. The `AbstractCliProvider` extracts only the truly shared parts (process execution, path translation).

2. **Provider-specific enums** — `ClaudePermissionMode` stays as a Claude internal. Codex/Gemini will have their own enums. The interface uses `getCapabilities()` arrays, not shared enums.

3. **Workspace file formats** — `CLAUDE.md` vs `codex.md` are fundamentally different. The interface defines operations (sync, delete), not file structure.

4. **Docker/infra config** — Volume mounts for CLI binaries and auth tokens are deployment concerns, not application abstractions.

5. **Format conversion** — Quill Delta → Markdown is pre-provider. Don't put it on the provider interface. All providers receive markdown strings.
