# Claude Runner Architecture Analysis

## 1. Current State

### What exists

PromptManager integrates with the Claude CLI through `ClaudeCliService`, which runs `claude` commands via `proc_open()` inside the `pma_yii` Docker container. The service handles:

- **Path translation** — converts host filesystem paths to container paths via `PATH_MAPPINGS`
- **Working directory resolution** — three-tier priority: project's own directory (if it has `CLAUDE.md`/`.claude/`) > managed workspace > default workspace
- **Managed workspaces** — per-project directories under `yii/storage/projects/{id}/` with generated `CLAUDE.md` and `.claude/settings.local.json`
- **Session continuity** — passes session IDs through for multi-turn conversations
- **Config status reporting** — tells the UI which config source was used

### What's broken

The integration silently degrades to managed workspaces for every project, even those with full Claude configuration in their actual directories. The root cause is a two-part configuration gap:

**1. `PATH_MAPPINGS` is not configured**

`params.php:22` loads mappings from the environment:
```php
'pathMappings' => getenv('PATH_MAPPINGS') ? json_decode(getenv('PATH_MAPPINGS'), true) : [],
```

The `.env` file either doesn't set `PATH_MAPPINGS` or sets it to an example value. In the container, `pathMappings` resolves to `[]`.

**2. Project directories are not mounted in the container**

`docker-compose.yml` mounts only the application directory:
```yaml
volumes:
  - .:/var/www/html
```

A project with `root_directory: /home/esg/projects/client-app` has no corresponding volume mount. The path doesn't exist inside the container.

### The failure cascade

When `actionRunClaude()` sends `root_directory = "/home/esg/projects/client-app"`:

1. `translatePath("/home/esg/projects/client-app")` — mappings are `[]`, returns the host path unchanged
2. `is_dir("/home/esg/projects/client-app")` — path doesn't exist in container, returns `false`
3. `determineWorkingDirectory()` falls through to managed workspace
4. Claude runs in `yii/storage/projects/{id}/` with a generated `CLAUDE.md` — not the project's own config

Similarly, `checkClaudeConfigForPath()` returns `hasAnyConfig: false` for every project. The frontend shows a warning suggesting the user add context, even when the project has comprehensive Claude configuration on the host.

### Impact

- Claude never sees the project's `CLAUDE.md`, `.claude/` rules, or codebase
- Claude operates on an empty managed workspace with a minimal generated context
- The config status display in the scratch pad UI is misleading
- Session context doesn't accumulate against the real project files
- The managed workspace feature (designed as a fallback) became the only mode

---

## 2. Architecture 1: Docker-Contained

### Concept

Fix the existing architecture by properly configuring volume mounts and path mappings. The code is already correct — it just needs the right environment.

### Changes required

#### A. Volume mount — `docker-compose.yml`

Add a single broad mount covering the projects parent directory:

```yaml
volumes:
  - .:/var/www/html
  - ${PROJECTS_ROOT:-~/projects}:/projects:rw    # NEW
  - ${HOME}/.claude:/home/${USER_NAME}/.claude
  # ... existing mounts
```

This makes all projects under `~/projects/` accessible at `/projects/` in the container.

#### B. Path mapping — `.env`

```env
PROJECTS_ROOT=~/projects
PATH_MAPPINGS={"~/projects": "/projects"}
```

One mapping entry translates all projects:
- `~/projects/client-app` -> `/projects/client-app`
- `~/projects/api-server` -> `/projects/api-server`

#### C. No code changes needed

`ClaudeCliService` already handles this correctly:
- `translatePath()` at line 28 iterates mappings and replaces the matching prefix
- `determineWorkingDirectory()` at line 171 checks `is_dir()` on the translated path and looks for `CLAUDE.md`/`.claude/`
- `checkClaudeConfigForPath()` at line 314 translates and checks

Once the directory exists in the container and the mapping is configured, the existing priority system works:
1. Project has own config → uses project directory (reported as `project_own:CLAUDE.md+.claude/`)
2. Project has no config → falls back to managed workspace (reported as `managed_workspace`)
3. No project → uses default workspace (reported as `default_workspace`)

### Volume strategy options

| Strategy | Mount | Scope | Restart needed |
|----------|-------|-------|----------------|
| Single parent | `~/projects:/projects:rw` | All projects under one parent | Only for new parent dirs |
| Per-project | One mount per project | Exact project dirs only | Every new project |
| Home directory | `~:/host-home:rw` | Everything in home | Never |

**Recommended: Single parent mount.** Per-project is too operationally expensive. Home directory is too broad.

### Security considerations

- **Filesystem exposure**: Claude CLI inside the container gains read/write access to everything under `~/projects/`. This includes projects not registered in PromptManager.
- **No per-project isolation**: A Claude session for Project A could theoretically read/write files in Project B's directory.
- **Mitigation**: Claude CLI's own permission modes (`plan`, `acceptEdits`, etc.) provide some guardrails, but these are enforced by Claude, not by the OS.
- **Container escape**: Not a concern — standard Docker bind mounts, no privilege escalation.
- **Secrets in project dirs**: `.env` files, API keys, etc. in mounted project directories are accessible to the Claude process.

### Operational burden

- **Setup**: Add two lines to `docker-compose.yml`, one env var to `.env`. Restart containers.
- **New projects**: No action needed if under the same parent directory. If a user has projects in a different location (e.g., `/opt/work/`), they need a second volume mount and mapping entry, plus container restart.
- **Maintenance**: Near-zero ongoing maintenance.
- **Debugging**: If something breaks, it's a simple path check: does the directory exist in the container? Does the mapping match?

### Pros

- **Zero code changes** — everything already works correctly
- **Minimal effort** — two config lines + container restart
- **Proven architecture** — `proc_open()` to Claude CLI is battle-tested
- **Session files stay local** — Claude's `.claude/` state persists via existing home dir mount
- **Managed workspaces still work** — projects without their own config gracefully fall back

### Cons

- **Broad mount** — all projects under the parent are exposed, not just registered ones
- **Tilde expansion** — `~` in `PATH_MAPPINGS` JSON may need to be absolute (`/home/esg/projects`) depending on how the shell expands the env var
- **Inflexible for scattered projects** — projects outside the mounted parent require docker-compose changes and a restart
- **Container restart required** — any volume mount change requires `docker compose down && docker compose up -d`
- **Permission modes are advisory** — Claude's `plan` mode prevents edits, but there's no OS-level enforcement

---

## 3. Architecture 2: Host Runner Service

### Concept

A lightweight HTTP service running directly on the host machine that handles all Claude CLI execution and filesystem access. The PHP application communicates with it over HTTP via `host.docker.internal`.

### Service design

**Runtime**: Node.js (same ecosystem as Claude CLI, good process management, fast HTTP)

**Location**: `runner/` directory at project root, or a separate repository

**Process management**: systemd unit or PM2 for auto-restart

```
┌─────────────────────┐     HTTP      ┌──────────────────────┐
│  pma_yii container  │──────────────>│  Claude Runner       │
│                     │  :3100        │  (host, Node.js)     │
│  ClaudeCliService   │               │                      │
│  (HTTP client)      │<──────────────│  - Execute claude    │
│                     │   JSON resp   │  - Check config      │
│                     │               │  - Native FS access  │
└─────────────────────┘               └──────────────────────┘
```

### API surface

#### `POST /execute`

Run Claude CLI with a prompt.

```json
// Request
{
  "prompt": "string",
  "workingDirectory": "/home/esg/projects/client-app",
  "timeout": 300,
  "options": {
    "permissionMode": "plan",
    "model": "opus",
    "appendSystemPrompt": "...",
    "allowedTools": "...",
    "disallowedTools": "..."
  },
  "sessionId": "abc-123"
}

// Response
{
  "success": true,
  "output": "...",
  "error": "",
  "exitCode": 0,
  "model": "opus-4.5",
  "input_tokens": 1234,
  "output_tokens": 567,
  "duration_ms": 8500,
  "configSource": "project_own:CLAUDE.md+.claude/",
  "session_id": "abc-123"
}
```

#### `GET /check-config?path=/home/esg/projects/client-app`

Check if a directory has Claude configuration.

```json
{
  "hasCLAUDE_MD": true,
  "hasClaudeDir": true,
  "hasAnyConfig": true
}
```

#### `GET /health`

Service health check.

```json
{
  "status": "ok",
  "version": "1.0.0",
  "claude_cli": "/home/esg/.local/bin/claude"
}
```

### What moves where

| Current location | Moves to | Stays in PHP |
|-----------------|----------|--------------|
| `translatePath()` | **Eliminated** — runner has native paths | — |
| `determineWorkingDirectory()` | Runner service | Request/response coordination |
| `buildCommand()` | Runner service | — |
| `proc_open()` execution | Runner service | — |
| `parseJsonOutput()` | Runner service | — |
| `hasClaudeConfig()` | Runner service (`/check-config`) | — |
| `ClaudeWorkspaceService` | **Stays in PHP** — workspace generation is app logic | Unchanged |
| Options merging | **Stays in PHP** — business logic in controller | Unchanged |

**`ClaudeCliService` becomes a thin HTTP client:**

```php
class ClaudeCliService
{
    private string $runnerBaseUrl;

    public function execute(string $prompt, string $workingDirectory, ...): array
    {
        // POST to runner, return response
    }

    public function checkClaudeConfigForPath(string $path): array
    {
        // GET /check-config?path=...
    }
}
```

### Security considerations

- **Network exposure**: Runner binds to `127.0.0.1:3100` only — not accessible from outside the host.
- **Docker access**: Container reaches runner via `host.docker.internal:3100` (already configured in `docker-compose.yml` line 23).
- **No path translation**: Host paths are native — no mapping errors possible.
- **Authentication**: Shared secret in `.env` passed as `Authorization: Bearer <token>` header. Prevents other containers or local processes from using the runner.
- **Path validation**: Runner validates that requested paths exist and optionally restricts to an allowed parent directory.
- **Per-project isolation**: Runner can enforce that only registered project directories are accessible (query PromptManager API or maintain allowlist).
- **Secrets**: Runner process runs as the host user with full home directory access — same exposure as the user's terminal.

### Deployment

- **systemd unit**: `claude-runner.service` with `User=esg`, `ExecStart=node /path/to/runner/index.js`
- **PM2 alternative**: `pm2 start runner/index.js --name claude-runner`
- **Docker Compose integration**: Could add a health check that warns if runner is unreachable
- **Logs**: stdout/stderr to journald or PM2 logs
- **Config**: `.env` file in runner directory or shared via symlink

### Pros

- **Native filesystem access** — no volume mounts, no path translation, no mapping errors
- **Clean separation** — execution concerns live outside the PHP container
- **Per-project security** — runner can enforce path restrictions independently
- **No container restarts** — adding projects never requires Docker changes
- **Scales to any project location** — `~/projects`, `/opt/work`, `/mnt/external` — all accessible natively
- **Independent deployment** — update runner without touching the PHP container
- **Simpler `ClaudeCliService`** — becomes a pure HTTP client with no process management

### Cons

- **Additional service** — another process to deploy, monitor, and keep running
- **Two codebases** — PHP app + Node.js runner; different languages, different tooling
- **Network dependency** — if runner is down, Claude integration is completely broken (managed workspaces also stop working)
- **Latency** — HTTP overhead per request (negligible compared to Claude CLI execution time, but adds a failure mode)
- **Managed workspaces need coordination** — workspace files live in the PHP container's storage, but runner runs on host. Either mount storage out or have runner write workspaces to a shared path.
- **Session state** — Claude CLI's session files are in `~/.claude/` on the host (natural for runner), but the PHP app references session IDs opaquely (no change needed)
- **Multi-day implementation** — new service, API design, HTTP client, error handling, deployment config

---

## 4. Comparison Matrix

| Dimension | Docker-Contained | Host Runner Service |
|-----------|-----------------|-------------------|
| **Implementation effort** | Config-only (2 lines) | New service + refactor client |
| **Code changes** | None | `ClaudeCliService` rewrite, new Node.js service |
| **Path handling** | Translation via `PATH_MAPPINGS` | Native — no translation needed |
| **Adding new projects** | Under same parent: nothing. Different parent: restart required | No action ever needed |
| **Filesystem exposure** | Entire parent directory mounted | Runner can enforce allowlists |
| **Per-project isolation** | None (all projects in mount) | Possible via runner config |
| **Operational overhead** | Near-zero | Service monitoring, log management |
| **Failure modes** | Path mapping misconfiguration | Runner service down, network issues |
| **Debugging** | Check mount + mapping | Check runner health + logs + HTTP |
| **Container restarts** | Required for mount changes | Never needed for project changes |
| **Config status accuracy** | Fixed — existing code works | Fixed — native `fs.existsSync()` |
| **Managed workspaces** | Work as-is | Need shared storage or runner-side generation |
| **Risk** | Low — config change only | Medium — new service, new failure modes |
| **Reversibility** | Trivial — remove volume + env var | Must maintain runner indefinitely |
| **Long-term scalability** | Limited by Docker mount model | Unlimited — full host access |

---

## 5. Recommendation

### Start with Architecture 1 (Docker-Contained)

**Rationale**: The existing code is correct and well-structured. The only problem is missing configuration. Adding a volume mount and path mapping fixes everything with zero code risk.

Architecture 2 is the superior long-term design — it eliminates an entire category of path translation bugs and provides better isolation. But it solves problems that don't exist yet (scattered project locations, per-project access control) while introducing operational complexity that will exist from day one.

### Implementation plan

**Phase 1 — Fix current architecture (Architecture 1)**:

1. Add `PROJECTS_ROOT` env var and volume mount to `docker-compose.yml`
2. Configure `PATH_MAPPINGS` in `.env` with the correct absolute paths
3. Restart containers
4. Verify: `checkClaudeConfigForPath()` returns `hasAnyConfig: true` for a real project
5. Verify: Claude runs in the project's own directory with its own `CLAUDE.md`

**Phase 2 — Evaluate (after using Phase 1 for a while)**:

If any of these become real pain points, then pursue Architecture 2:
- Frequent projects outside the mounted parent directory
- Need for per-project filesystem isolation
- Path translation bugs despite correct configuration
- Desire to run Claude CLI on a different machine than the Docker host

**Phase 2 would not discard Phase 1** — the managed workspace system and config priority logic remain valuable regardless of how Claude CLI is executed.

### Key risks to watch

- **Tilde expansion**: `PATH_MAPPINGS={"~/projects": "/projects"}` — the `~` in JSON may not expand. Use absolute paths: `{"/home/esg/projects": "/projects"}`
- **File permissions**: The container user (`appuser`, UID 1000) must have read/write access to the mounted project directories. If the host user's UID doesn't match, Claude CLI will fail with permission errors.
- **Claude CLI state**: Claude stores session state in `~/.claude/projects/`. The existing mount (`${HOME}/.claude:/home/${USER_NAME}/.claude`) handles this, but verify that sessions created against `/projects/client-app` inside the container are recognized when the same project is accessed directly on the host.
