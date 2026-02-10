# MCP Ask-User Server — Functional Specification

## Problem Statement

### Current Situation

PromptManager executes Claude CLI in non-interactive mode (`-p` flag) via `proc_open()`. When Claude decides it needs clarification from the user, it invokes the built-in `AskUserQuestion` tool. This tool requires a TTY to render its interactive selection UI. Since there is no TTY in `-p` mode, the tool fails silently — the user receives no question and Claude receives no answer, causing incomplete or stalled responses.

### Current Workarounds

PromptManager mitigates this via permission modes (`bypassPermissions`, `dontAsk`, `plan`) and system prompt instructions ("do not ask questions"). These suppress _permission_ prompts but cannot prevent Claude from deciding it needs domain-level clarification. When Claude's model decides a question is necessary, no amount of configuration prevents the silent failure.

### Desired Situation

When Claude needs to ask the user a question during a PromptManager-initiated session, the question appears inline in the browser chat interface. The user answers via the UI, and the answer flows back to Claude, which continues processing. The experience is seamless — as if Claude were running interactively.

### Measurable Goals

- Questions appear in the browser within 3 seconds of Claude invoking the tool
- User answers reach Claude within 2 seconds of submission
- No silent failures: every `ask_user` invocation either gets an answer or a timeout error
- Zero additional setup for end users (no separate server to install/manage)

---

## Glossary

| Term | Definition |
|------|-----------|
| **MCP** | Model Context Protocol — standard for extending Claude with custom tools via external servers |
| **MCP Server** | A process that implements the MCP protocol and provides tools to Claude CLI |
| **`ask_user`** | The custom MCP tool that replaces Claude's built-in `AskUserQuestion` |
| **Stream Token** | Unique per-request identifier (UUID) already used by PromptManager for process management |
| **SSE** | Server-Sent Events — the existing protocol PromptManager uses for streaming Claude responses |
| **Managed Workspace** | Per-project directory (`storage/projects/{id}/`) containing generated `CLAUDE.md` and `.claude/settings.local.json` |

---

## Options Analysis

### Option 1: System Prompt Instruction + Output Parsing

**How it works:**
- Instruct Claude via system prompt: "If you need clarification, output a structured JSON marker instead of using AskUserQuestion"
- PromptManager's streaming parser detects the marker and shows the question
- User answers, PromptManager sends the answer as a follow-up via `--resume`

**Pros:**
- No new infrastructure (no MCP server)
- Works with existing `executeStreaming` without modification
- Simple to implement

**Cons:**
- Relies on Claude consistently following the instruction (not guaranteed — model may still use built-in tool)
- Fragile: output format can vary across Claude versions and models
- Resume-based continuation adds latency (new CLI process per answer)
- Not the canonical tool-use pattern — a workaround, not a solution

---

### Option 2: Custom MCP Server with `ask_user` Tool (Selected)

**How it works:**
- A Node.js MCP server provides an `ask_user` tool
- Claude CLI spawns the MCP server as a child process (configured via workspace settings)
- When Claude calls `ask_user`, the MCP server relays the question to PromptManager via HTTP
- PromptManager pushes the question to the browser via the existing SSE stream
- The user answers in the browser; the answer flows back through the same chain

**Pros:**
- Uses the canonical MCP tool pattern — reliable, versioned, well-documented
- Claude treats it as a real tool (proper tool_use/tool_result lifecycle)
- No process restart needed — Claude continues within the same session
- Decoupled: MCP server has single responsibility

**Cons:**
- New Node.js process per Claude session (lightweight, ~10MB)
- Requires minor changes to `ClaudeCliService` streaming loop (reduced timeout + loop condition)
- Adds HTTP communication between MCP server and PromptManager

---

### Option 3: Claude Agent SDK with Custom Orchestrator

**How it works:**
- Replace `claude -p` with the Agent SDK (Python/TypeScript) for full programmatic control
- Intercept `AskUserQuestion` at the SDK level before it reaches the CLI

**Pros:**
- Full control over the conversation lifecycle
- Can intercept any tool call, not just `ask_user`

**Cons:**
- Replaces the entire execution model (`proc_open` → SDK subprocess)
- Massive scope — rewrites the core `ClaudeCliService`
- Python/TypeScript runtime dependency for the orchestrator
- Overkill for solving one interaction problem

---

### Decision

**Option 2** — Custom MCP server. It solves the problem within the existing architecture, uses the canonical extension mechanism, and keeps changes scoped.

---

## Architecture

### Component Diagram

```
┌────────────────────────────────────────────────────────────────────┐
│                          Browser                                    │
│  ┌──────────────────────────────────────────────────────────┐      │
│  │  Claude Chat UI (SSE client)                              │      │
│  │  ┌─────────────────┐  ┌────────────────────────────────┐ │      │
│  │  │ Message Stream   │  │ Ask-User Question Panel        │ │      │
│  │  │ (existing)       │  │ (new: renders question,        │ │      │
│  │  │                  │  │  collects answer, POSTs back)  │ │      │
│  │  └─────────────────┘  └────────────────────────────────┘ │      │
│  └──────────────────────┬──────────────────┬────────────────┘      │
│                         │ SSE              │ POST /answer            │
└─────────────────────────┼──────────────────┼───────────────────────┘
                          │                  │
┌─────────────────────────┼──────────────────┼───────────────────────┐
│  PromptManager (PHP)    │                  │                        │
│  ┌──────────────────────▼──────────────────▼────────────────────┐  │
│  │  ClaudeController                                             │  │
│  │  actionStream()  ──SSE──▶  browser                            │  │
│  │  actionRelayQuestion()  ◀──HTTP POST── MCP server             │  │
│  │  actionSubmitAnswer()   ◀──HTTP POST── browser                │  │
│  │  actionPollAnswer()     ◀──HTTP GET ── MCP server             │  │
│  └──────────────────────────────┬───────────────────────────────┘  │
│                                 │                                   │
│  ┌──────────────────────────────▼───────────────────────────────┐  │
│  │  ClaudeCliService                                             │  │
│  │  executeStreaming() — reduced stream_set_timeout(2)           │  │
│  │  + on timeout: checks cache for pending questions             │  │
│  │  + emits synthetic SSE events for ask_user questions          │  │
│  └──────────────────────────────┬───────────────────────────────┘  │
│                                 │ proc_open() with shell env prefix │
│                                 ▼                                   │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  Claude CLI  (child process)                                  │  │
│  │  --output-format stream-json -p -                             │  │
│  │  ├── reads prompt from stdin                                  │  │
│  │  ├── spawns MCP server as configured in settings.local.json   │  │
│  │  └── calls ask_user tool when clarification needed            │  │
│  │       │                                                       │  │
│  │       ▼                                                       │  │
│  │  ┌──────────────────────────────────────────┐                 │  │
│  │  │  MCP Ask-User Server (Node.js)           │                 │  │
│  │  │  - stdio transport (MCP protocol)        │                 │  │
│  │  │  - reads config from ASK_USER_CONFIG env │                 │  │
│  │  │  - POSTs question to PromptManager API   │                 │  │
│  │  │  - polls for answer via HTTP GET         │                 │  │
│  │  │  - returns answer to Claude via MCP      │                 │  │
│  │  └──────────────────────────────────────────┘                 │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  Yii Cache (file/APCu)                                             │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  ask_user_q_{userId}_{streamToken} → question JSON            │  │
│  │  ask_user_a_{userId}_{streamToken} → answer JSON              │  │
│  └──────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### Communication Flow

```
 Browser          PromptManager         Claude CLI        MCP Server
    │                  │                    │                  │
    │  POST /stream    │                    │                  │
    │─────────────────▶│                    │                  │
    │                  │  write config file │                  │
    │                  │  proc_open(env)    │                  │
    │                  │───────────────────▶│                  │
    │                  │                    │  spawn MCP       │
    │                  │                    │─────────────────▶│
    │                  │                    │                  │  read config
    │                  │                    │                  │  from env var
    │  SSE: streaming  │  stdout lines      │                  │
    │◀─────────────────│◀───────────────────│  processing...   │
    │                  │                    │                  │
    │                  │                    │ ── needs input ──│
    │                  │                    │  tool_use:       │
    │                  │                    │  ask_user(q)     │
    │                  │                    │─────────────────▶│
    │                  │                    │                  │
    │                  │  POST /relay-question                 │
    │                  │◀─────────────────────────────────────│
    │                  │  store in cache    │                  │
    │                  │  200 OK            │                  │
    │                  │─────────────────────────────────────▶│
    │                  │                    │                  │
    │                  │  fgets timeout     │                  │
    │                  │  detects question  │                  │
    │  SSE: ask_user   │  in cache          │                  │
    │◀─────────────────│                    │                  │
    │                  │                    │  poll answer     │
    │                  │◀─────────────────────────────────────│
    │                  │  (no answer yet)   │                  │
    │                  │  202 Accepted      │                  │
    │                  │─────────────────────────────────────▶│
    │                  │                    │                  │
    │  user answers    │                    │                  │
    │─────────────────▶│                    │                  │
    │  POST /answer    │  store in cache    │                  │
    │                  │                    │                  │
    │                  │                    │  poll answer     │
    │                  │◀─────────────────────────────────────│
    │                  │  200 + answer      │                  │
    │                  │─────────────────────────────────────▶│
    │                  │                    │                  │
    │                  │                    │  tool_result:    │
    │                  │                    │◀─────────────────│
    │                  │                    │  answer          │
    │                  │                    │                  │
    │  SSE: continues  │  stdout lines      │  continues...    │
    │◀─────────────────│◀───────────────────│                  │
```

---

## MCP Server Specification

### Technology

Node.js with the `@modelcontextprotocol/sdk` TypeScript/JavaScript SDK.

**Rationale:**
- Node.js v20 is already available in the `pma_yii` container (verified: `/usr/bin/node`)
- The MCP TypeScript SDK is Anthropic's reference implementation
- Single-file server, minimal dependencies
- No new runtime to install or manage

### Tool Schema

```json
{
  "name": "ask_user",
  "description": "Ask the user a question and wait for their response. Use this when you need clarification, a choice between options, or confirmation before proceeding.",
  "inputSchema": {
    "type": "object",
    "required": ["question"],
    "properties": {
      "question": {
        "type": "string",
        "description": "The question to ask the user"
      },
      "options": {
        "type": "array",
        "description": "Optional list of choices for the user",
        "items": {
          "type": "object",
          "required": ["label"],
          "properties": {
            "label": {
              "type": "string",
              "description": "Short label for the option"
            },
            "description": {
              "type": "string",
              "description": "Longer explanation of what this option means"
            }
          }
        }
      },
      "multiSelect": {
        "type": "boolean",
        "default": false,
        "description": "Whether the user can select multiple options"
      }
    }
  }
}
```

### Tool Response

```json
{
  "content": [
    {
      "type": "text",
      "text": "User selected: Option A"
    }
  ]
}
```

On timeout:

```json
{
  "content": [
    {
      "type": "text",
      "text": "User did not respond within the timeout period. Proceeding with your best judgment."
    }
  ],
  "isError": true
}
```

### Configuration

The MCP server reads its configuration from the file path specified in the `ASK_USER_CONFIG` environment variable:

```json
{
  "apiUrl": "http://localhost:8080",
  "streamToken": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "userId": 1,
  "projectId": 5,
  "csrfToken": "abc123...",
  "timeout": 300
}
```

| Field | Type | Description |
|-------|------|-------------|
| `apiUrl` | string | PromptManager base URL (localhost) |
| `streamToken` | string | Unique stream identifier (UUID) |
| `userId` | int | Owner user ID for cache key scoping |
| `projectId` | int | Project ID for RBAC-scoped endpoint URLs (`?p={projectId}`) |
| `csrfToken` | string | Yii2 CSRF token for POST requests |
| `timeout` | int | Max seconds to wait for user answer (default: 300, configurable per project) |

### MCP Server Behavior

1. **Startup:** Read `ASK_USER_CONFIG` env var → load config file → validate fields
2. **On `ask_user` call:**
   a. POST question to `{apiUrl}/claude/relay-question` with `streamToken`, question data, CSRF token
   b. Start polling `{apiUrl}/claude/poll-answer?streamToken={token}` every 2 seconds
   c. On answer received → return `tool_result` to Claude
   d. On timeout → return error `tool_result` to Claude
3. **Shutdown:** Clean (no state to persist)

### File Location

```
scripts/mcp-ask-user/
├── server.js         # MCP server implementation
├── package.json      # Dependencies (@modelcontextprotocol/sdk)
└── README.md         # Setup and usage
```

---

## PromptManager Backend Changes

### 1. ClaudeCliService — Modified Streaming Loop

**Current:** `executeStreaming()` uses blocking `fgets()` with `stream_set_timeout(30)` on Claude's stdout. The loop condition `while (($line = fgets($pipes[1])) !== false)` exits on both EOF and timeout.

**Modified:** Reduce `stream_set_timeout` from 30s to 2s, and change the loop condition from `fgets !== false` to `!feof()`. On timeout, check the cache for pending questions instead of exiting.

```
Current loop:
  stream_set_timeout(30)
  while (fgets !== false) → onLine → repeat
  // exits on EOF or 30s timeout

Modified loop:
  stream_set_timeout(2)
  while (!feof)
    → fgets (returns false after 2s if no data)
    → if false: check stream_get_meta_data() for timed_out
      → if timed_out: check cache for pending question → emit synthetic SSE event
      → continue
    → if data: onLine
    → check timeout/cancellation
  → repeat
```

**Why:** When Claude calls the `ask_user` MCP tool, Claude CLI blocks waiting for the tool result. It produces no stdout during this time. The PHP process must still check for the relayed question (which arrives via the MCP server's HTTP callback) and push it to the browser through the SSE stream. The reduced timeout has no impact on normal streaming — `fgets` returns immediately when data is available. The 2-second timeout only fires when Claude is idle (waiting for MCP tool result), which is exactly when we need to check for questions.

**Why `stream_set_timeout` over `stream_select`:** The current code already uses `stream_set_blocking(true)` + `stream_set_timeout`. Reducing the timeout and changing the loop condition is a smaller, safer diff than switching to `stream_select()` with non-blocking I/O, which has known edge cases in PHP (partial line reads, data left in buffer). When the feature toggle is off, the timeout check simply continues without action — no behavioral difference from current production.

**Risk mitigation (R1):** The question-check inside the timeout branch is wrapped in the feature toggle. When `mcpAskUserEnabled` is `false`, the reduced timeout still does not affect behavior — the loop simply continues, equivalent to the current 30s timeout but with more frequent iterations.

**New method:** `checkPendingQuestion(string $streamToken): ?array`
- Reads from cache key `ask_user_q_{userId}_{streamToken}`
- Returns question data or null
- Deletes the cache key after reading (one-time delivery)

### 2. ClaudeCliService — Environment Variable Injection

**Current:** `proc_open()` passes no env parameter (inherits all from PHP process). The command is a string, so `proc_open` runs it through `/bin/sh -c`.

**Modified:** Prepend the env var as a shell-style prefix on the command string:

```php
$cmd = 'ASK_USER_CONFIG=' . escapeshellarg($configPath) . ' ' . $command;
```

This avoids changing the `proc_open()` call signature or merging the full parent environment via `getenv()`. The shell sets the env var for the command, and all child processes (Claude CLI → MCP server) inherit it.

| Variable | Value | Purpose |
|----------|-------|---------|
| `ASK_USER_CONFIG` | `/tmp/claude-ask-user/{streamToken}.json` | Config file path for MCP server |

The config file is written before `proc_open()` and cleaned up after the process exits.

### 3. ClaudeWorkspaceService — MCP Server Registration

**Modified:** `generateSettingsJson()` includes the `ask_user` MCP server configuration.

New settings output:

```json
{
  "permissions": { "defaultMode": "plan" },
  "mcpServers": {
    "ask-user": {
      "command": "node",
      "args": ["/path/to/scripts/mcp-ask-user/server.js"]
    }
  }
}
```

The MCP server path is resolved from `Yii::$app->params['mcpAskUserServerPath']`, defaulting to `@app/../scripts/mcp-ask-user/server.js`.

**Note:** The `env` block is intentionally omitted from settings.json. Per-request env vars (`ASK_USER_CONFIG`) are inherited from the parent process (PHP → Claude CLI → MCP server) via `proc_open()`.

### 4. ClaudeController — New Actions

#### `actionRelayQuestion(int $p)` — POST

Called by the MCP server to relay a question.

**Request:**

```json
{
  "streamToken": "uuid",
  "question": "Which approach should I use?",
  "options": [
    { "label": "Option A", "description": "..." },
    { "label": "Option B", "description": "..." }
  ],
  "multiSelect": false
}
```

**Behavior:**
1. Validate `streamToken` format (UUID)
2. Store question in cache: `ask_user_q_{userId}_{streamToken}` (TTL: 600s)
3. Return `200 OK`

**Authentication:** Token-based — validates that a cache key `claude_cli_pid_{userId}_{streamToken}` exists (proves this stream is active for this user). No session required (MCP server has no browser session).

#### `actionSubmitAnswer(int $p)` — POST

Called by the browser when the user answers.

**Request:**

```json
{
  "streamToken": "uuid",
  "answer": "Option A",
  "selectedOptions": [0]
}
```

**Behavior:**
1. Validate CSRF token (browser request)
2. Validate project ownership (standard RBAC)
3. Store answer in cache: `ask_user_a_{userId}_{streamToken}` (TTL: 600s)
4. Return `200 OK`

#### `actionPollAnswer(int $p)` — GET

Called by the MCP server to retrieve the user's answer.

**Response (no answer yet):**
```json
{ "status": "waiting" }
```
HTTP 202 Accepted

**Response (answer available):**
```json
{
  "status": "answered",
  "answer": "Option A",
  "selectedOptions": [0]
}
```
HTTP 200 OK. Deletes cache key after reading.

**Authentication:** Same token-based validation as `actionRelayQuestion`.

### 5. Cache Key Design

| Key Pattern | Written By | Read By | TTL | Content |
|-------------|-----------|---------|-----|---------|
| `ask_user_q_{userId}_{streamToken}` | relay-question action | streaming loop | 600s | Question JSON |
| `ask_user_a_{userId}_{streamToken}` | submit-answer action | poll-answer action | 600s | Answer JSON |
| `claude_cli_pid_{userId}_{streamToken}` | executeStreaming (existing) | relay/poll validation | 3900s | Process PID |

The existing PID cache key serves double duty: process management AND authentication for MCP server callbacks.

---

## Frontend Changes

### SSE Event: `ask_user_question`

New synthetic SSE event type emitted by the streaming loop:

```json
{
  "type": "ask_user_question",
  "data": {
    "question": "Which approach should I use?",
    "options": [
      { "label": "Option A", "description": "Simple but limited" },
      { "label": "Option B", "description": "Complex but flexible" }
    ],
    "multiSelect": false,
    "streamToken": "uuid"
  }
}
```

### UI Behavior

The question panel renders inline in the chat stream (not as a modal), consistent with how Claude Code renders `AskUserQuestion` in the terminal.

| State | Appearance | Behavior |
|-------|------------|----------|
| Question received | Card with question text, option buttons/radio, and a free-text "Other" input | User selects or types |
| Waiting for Claude | Card shows "Answer sent, waiting for Claude..." with spinner | Read-only |
| Claude continues | Card collapses to a summary: "You answered: Option A" | Static |
| Timeout | Card shows "Question timed out" | Static |

**Option rendering:**
- If `options` present and `multiSelect: false` → radio buttons + "Other" text input
- If `options` present and `multiSelect: true` → checkboxes + "Other" text input
- If no `options` → free-text textarea only

**Submit behavior:**
1. POST to `/claude/submit-answer?p={projectId}` with `streamToken` and answer
2. Disable the form
3. Show waiting state

---

## Existing Infrastructure Reuse

| Need | Existing Solution | File |
|------|-------------------|------|
| SSE streaming to browser | `ClaudeController::actionStream()` + `beginSseResponse()` | `yii/controllers/ClaudeController.php` |
| Process PID management | `storeProcessPid()` / `clearProcessPid()` | `yii/services/ClaudeCliService.php` |
| Stream token generation/validation | `sanitizeStreamToken()` + UUID format | `yii/controllers/ClaudeController.php` |
| Workspace settings generation | `ClaudeWorkspaceService::generateSettingsJson()` | `yii/services/ClaudeWorkspaceService.php` |
| Project RBAC ownership | `EntityPermissionService` + `ProjectOwnerRule` | `yii/services/EntityPermissionService.php` |
| Cache infrastructure | `Yii::$app->cache` (already used for PID storage) | Yii2 framework |
| CSRF validation | Yii2 built-in (browser requests only) | Yii2 framework |

---

## Configuration

### Application Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `mcpAskUserServerPath` | `@app/../scripts/mcp-ask-user/server.js` | Absolute path to the MCP server script |
| `mcpAskUserEnabled` | `true` | Feature toggle |
| `mcpAskUserTimeout` | `300` | Default timeout in seconds for user responses |

### Project-Level Option

New key in `claude_options` JSON:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `askUserEnabled` | bool | `true` | Enable/disable the ask-user MCP server for this project |
| `askUserTimeout` | int | `300` | Per-project timeout override in seconds (min: 30, max: 900) |

When `askUserEnabled` is `false`, the MCP server is not included in `settings.local.json`, and Claude falls back to default behavior (silent failure of `AskUserQuestion` in `-p` mode).

The `askUserTimeout` allows per-project tuning: shorter for quick automation projects (e.g., 60s), longer for architecture/planning projects where the user may need time to consider (e.g., 600s). The app-level `mcpAskUserTimeout` (300s) is used when no project override is set.

---

## Security Model

### Authentication of MCP Server Callbacks

The MCP server is a child process of Claude CLI, which is a child process of PromptManager. It runs on the same machine, communicating over localhost only. Authentication uses the existing `streamToken` + `userId` cache key validation:

1. MCP server includes `streamToken` in every request
2. PromptManager validates that `claude_cli_pid_{userId}_{streamToken}` exists in cache
3. This proves: (a) the stream is active, (b) it belongs to this user

**No session cookie required** — the MCP server is not a browser client.

### CSRF Protection

| Endpoint | Caller | CSRF |
|----------|--------|------|
| `relay-question` | MCP server (localhost HTTP) | Disabled (token-auth) |
| `submit-answer` | Browser (AJAX) | Enabled (standard Yii2) |
| `poll-answer` | MCP server (localhost HTTP) | Disabled (token-auth) |

MCP server endpoints validate `streamToken` instead of CSRF. The CSRF token in the config file is a fallback if Yii2's CSRF cannot be selectively disabled per action.

### Localhost Binding

The MCP server only communicates with `localhost`. The `apiUrl` in the config is always a localhost URL. The MCP server should reject any config where `apiUrl` does not resolve to `127.0.0.1` or `::1`.

### Temp File Security

Config files at `/tmp/claude-ask-user/{streamToken}.json`:
- Created with `0600` permissions (owner-only read/write)
- Contain: API URL, stream token, user ID, CSRF token
- Deleted after Claude CLI process exits
- TTL: cleaned up by PromptManager if stale (>1 hour)

### Rate Limiting

- Maximum 1 pending question per stream (subsequent questions overwrite)
- MCP server polls at most every 2 seconds
- Answer endpoint requires active stream validation

---

## Error Handling

| Scenario | Handler | User Experience |
|----------|---------|-----------------|
| MCP server fails to start | Claude CLI logs warning, continues without `ask_user` | No impact unless Claude needs to ask |
| MCP server cannot reach PromptManager | MCP server returns timeout error to Claude | Claude sees "user did not respond" and proceeds with best judgment |
| User does not answer within timeout | MCP server returns timeout error to Claude | Chat shows "Question timed out" |
| Browser disconnects during question | `connection_aborted()` detection in streaming loop | Stream terminates, MCP server times out |
| Concurrent questions (same stream) | Latest question overwrites previous in cache | Only most recent question shown |
| Invalid streamToken in MCP callback | PromptManager returns 403 | MCP server returns error to Claude |

---

## Affected Entities

### Modified

| Entity | Change | Impact |
|--------|--------|--------|
| `ClaudeCliService` | `executeStreaming()` loop condition + reduced timeout; new `checkPendingQuestion()` method; shell env prefix for `ASK_USER_CONFIG` | Minimal diff — loop condition `fgets !== false` → `!feof()`, timeout 30→2s |
| `ClaudeWorkspaceService` | `generateSettingsJson()` adds `mcpServers` config | Settings file format changes — run `sync-workspaces` command after deploy |
| `ClaudeController` | 3 new actions: `relay-question`, `submit-answer`, `poll-answer` | New endpoints — add to RBAC config |
| `Project` model | New `askUserEnabled` key in `claude_options` | No schema change (JSON column) |
| Claude chat frontend (JS) | Handle `ask_user_question` SSE event, render question UI, submit answer | Frontend change — rebuild assets |

### New

| Entity | Type | Purpose |
|--------|------|---------|
| `scripts/mcp-ask-user/server.js` | Node.js script | MCP server implementation |
| `scripts/mcp-ask-user/package.json` | NPM config | Dependencies |
| Question/answer UI component | JavaScript | Inline question panel in chat |

### Not Affected

| Entity | Why |
|--------|-----|
| Database schema | No migration needed — `claude_options` is already a JSON column |
| `ClaudeCliCompletionClient` | Quick completions use `--no-session-persistence` and no tools |
| `ClaudeQuickHandler` | Single-turn completions, no interactive questions |
| All non-Claude models/services | No domain entity changes |
| `ClaudePermissionMode` enum | Permission modes remain unchanged |

---

## Not in Scope

| Item | Reason |
|------|--------|
| Automated/AI-powered default answers | Separate feature; this spec covers human-in-the-loop only |
| Question history/logging | No business need identified; questions are transient |
| Multi-question batching | Claude sends one question at a time; sequential handling is sufficient |
| `actionRun` (synchronous mode) support | Synchronous mode returns a single response; question-answer interaction requires streaming |
| Custom MCP tools beyond `ask_user` | Each tool is a separate feature; this spec covers `ask_user` only |
| Claude CLI Bridge integration | Bridge launches interactive terminals; `ask_user` solves the non-interactive case |
| Project settings UI for `askUserEnabled` | Can be added to existing Claude options form later; default is enabled |

---

## Risks & Decision Points

### Risk Register

| ID | Risk | Impact | Likelihood | Mitigation |
|----|------|--------|------------|------------|
| R1 | Streaming loop change breaks existing streaming | High | Low | Minimal diff (timeout reduction + loop condition change); feature toggle skips question check; comprehensive tests |
| R2 | Claude CLI changes MCP server spawning behavior | Medium | Low | Pin MCP SDK version; test against Claude CLI updates |
| R3 | Cache race condition between question write and read | Low | Low | Atomic cache operations; single-writer per key |
| R4 | MCP server process outlives Claude CLI | Low | Low | MCP servers are child processes — killed when parent exits |
| R5 | User answers after Claude has timed out and continued | Low | Medium | Frontend detects stale question via SSE continuation events |

### Decision Points

| ID | Decision | Options | Chosen | Rationale | Status |
|----|----------|---------|--------|-----------|--------|
| D1 | MCP server technology | Node.js / Python / PHP | **Node.js** | v20 already in `pma_yii` container; Anthropic reference MCP SDK | **DECIDED** |
| D2 | Communication: MCP ↔ PromptManager | HTTP callback / file-based / shared cache | **HTTP callback** | Real-time, reliable; env var passed via shell prefix on command string (avoids `proc_open` env merge) | **DECIDED** |
| D3 | Question UI placement | Inline in chat / modal / separate panel | **Inline in chat** | Consistent with Claude CLI native UX; non-disruptive | **DECIDED** |
| D4 | Streaming loop I/O model | `stream_select()` / reduced `stream_set_timeout()` | **Reduced `stream_set_timeout(2)`** | Smallest diff from current code; avoids `stream_select` edge cases in PHP; 2s timeout only fires when Claude is idle | **DECIDED** |
| D5 | MCP server per-request config | Env var → config file / settings.json override / shared cache | **Env var → config file** | No concurrency issues; config includes `projectId` for RBAC-scoped URLs | **DECIDED** |
| D6 | Timeout default | 120s / 300s / 600s / unlimited | **300s (configurable per project)** | Generous but bounded; overridable via `askUserTimeout` in `claude_options` (30–900s range) | **DECIDED** |

---

## Feature Toggle & Rollback

### Toggle

```php
// config/params.php
'mcpAskUserEnabled' => true,
```

When `false`:
- `ClaudeWorkspaceService` omits `mcpServers` from settings.json
- `executeStreaming()` skips question cache checks
- No MCP server process spawned
- Behavior identical to current production

### Rollback

| Scenario | Action | Downtime |
|----------|--------|----------|
| Bug in MCP server | Set `mcpAskUserEnabled = false` | None (graceful degradation) |
| Bug in streaming loop | Revert `executeStreaming()` changes | Deploy cycle |
| Frontend issue | Feature toggle hides question UI | None |

---

## Verification

### Positive Tests

| # | Test Case | Expected Result |
|---|-----------|-----------------|
| 1 | Claude calls `ask_user` during streaming session | Question appears in browser chat within 3s |
| 2 | User selects an option and submits | Answer reaches Claude, processing continues |
| 3 | User types free-text answer (no options) | Free-text delivered to Claude |
| 4 | User selects multiple options (multiSelect) | All selections delivered as answer |
| 5 | Multiple questions in one session (sequential) | Each question/answer handled independently |
| 6 | Existing streaming without `ask_user` | Behavior unchanged from current production |

### Negative Tests

| # | Test Case | Expected Result |
|---|-----------|-----------------|
| 7 | User does not answer within timeout | Claude receives timeout error, continues |
| 8 | Browser disconnects during pending question | Stream terminates cleanly, MCP server times out |
| 9 | Invalid streamToken in relay-question | 403 Forbidden returned to MCP server |
| 10 | MCP server process fails to start | Claude CLI continues without `ask_user` tool |
| 11 | Feature toggle disabled | No MCP server spawned, no question checking in loop |

### Performance Tests

| # | Test Case | Target |
|---|-----------|--------|
| 12 | Time from Claude tool_use to question in browser | < 3 seconds |
| 13 | Time from user submit to Claude receiving answer | < 2 seconds |
| 14 | Streaming throughput with question checking enabled | No measurable degradation (< 5% overhead) |

---

## Acceptance Criteria

### Must Have

| ID | Criterion | Verification |
|----|-----------|--------------|
| AC-1 | Questions from Claude appear inline in the browser chat | Test #1 |
| AC-2 | User can answer via option selection or free text | Tests #2, #3 |
| AC-3 | Answer reaches Claude and processing continues | Test #2 |
| AC-4 | Timeout produces a graceful error (not a hang) | Test #7 |
| AC-5 | Existing streaming behavior is not broken | Test #6 |
| AC-6 | Feature toggle disables all MCP ask-user behavior | Test #11 |
| AC-7 | No additional setup required for end users | MCP server auto-configured via workspace settings |

### Should Have

| ID | Criterion | Verification |
|----|-----------|--------------|
| AC-8 | Question panel collapses to summary after answer | Manual inspection |
| AC-9 | Multiple sequential questions handled correctly | Test #5 |
| AC-10 | Per-project enable/disable via `claude_options` | Config test |

---

## Implementation Sequence

1. **MCP server script** — `scripts/mcp-ask-user/server.js` with `ask_user` tool
2. **Backend: new controller actions** — relay-question, submit-answer, poll-answer
3. **Backend: ClaudeWorkspaceService** — add mcpServers to settings.json generation
4. **Backend: ClaudeCliService** — env injection + non-blocking streaming loop
5. **Frontend: question UI** — SSE event handler + inline question panel
6. **Integration test** — end-to-end flow
7. **Run `sync-workspaces`** — update all existing project workspaces

---

## Appendix: MCP Server Pseudocode

```javascript
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";

const config = JSON.parse(fs.readFileSync(process.env.ASK_USER_CONFIG));

const server = new Server({ name: "ask-user", version: "1.0.0" }, {
  capabilities: { tools: {} }
});

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [{
    name: "ask_user",
    description: "Ask the user a question via the web interface",
    inputSchema: { /* see Tool Schema section */ }
  }]
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { question, options, multiSelect } = request.params.arguments;

  // 1. Relay question to PromptManager
  await fetch(`${config.apiUrl}/claude/relay-question?p=${config.projectId}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ streamToken: config.streamToken, question, options, multiSelect })
  });

  // 2. Poll for answer
  const deadline = Date.now() + (config.timeout * 1000);
  while (Date.now() < deadline) {
    await sleep(2000);
    const res = await fetch(
      `${config.apiUrl}/claude/poll-answer?p=${config.projectId}&streamToken=${config.streamToken}`
    );
    const data = await res.json();
    if (data.status === "answered") {
      return { content: [{ type: "text", text: formatAnswer(data) }] };
    }
  }

  // 3. Timeout
  return {
    content: [{ type: "text", text: "User did not respond within timeout." }],
    isError: true
  };
});

const transport = new StdioServerTransport();
await server.connect(transport);
```
