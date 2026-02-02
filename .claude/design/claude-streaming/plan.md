# Claude Chat Streaming — Technical Plan

## Architecture Overview

```
Browser (EventSource)          Yii2 Controller              ClaudeCliService              Claude CLI
        |                            |                            |                          |
        |--- GET /stream-claude ---->|                            |                          |
        |                            |--- executeStreaming() ---->|                          |
        |                            |                            |--- proc_open(stream) --->|
        |                            |                            |                          |
        |<-- SSE: init event --------|<-- stdout line ------------|<-- {"type":"system"} ----|
        |<-- SSE: delta event -------|<-- stdout line ------------|<-- {"type":"stream_..."} |
        |<-- SSE: delta event -------|<-- stdout line ------------|<-- {"type":"stream_..."} |
        |<-- SSE: result event ------|<-- stdout line ------------|<-- {"type":"result"} ----|
        |<-- SSE: [DONE] ------------|                            |                          |
        |                            |                            |--- proc_close() -------->|
```

Key insight: PHP acts as a thin relay. Each line from Claude CLI stdout is read, minimally validated, and flushed immediately as an SSE `data:` line. No buffering of the full output.

---

## Step 1: ClaudeCliService — Add `executeStreaming()` Method

**File**: `yii/services/ClaudeCliService.php`

### New Method Signature

```php
/**
 * Executes Claude CLI with streaming output, calling $onLine for each stdout line.
 *
 * @param string $prompt The prompt content (already converted to markdown)
 * @param string $workingDirectory Working directory (may be resolved)
 * @param callable(string): void $onLine Callback invoked for each line of stdout
 * @param int $timeout Maximum execution time in seconds
 * @param array $options Claude CLI options
 * @param Project|null $project Optional project for workspace resolution
 * @param string|null $sessionId Optional session ID to continue
 * @return array{exitCode: int, error: string} Final exit code and stderr
 */
public function executeStreaming(
    string $prompt,
    string $workingDirectory,
    callable $onLine,
    int $timeout = 300,
    array $options = [],
    ?Project $project = null,
    ?string $sessionId = null
): array
```

### Implementation

- Reuses existing `determineWorkingDirectory()` and `buildCommand()` (modified, see below)
- Same `proc_open` setup with stdin pipe for prompt
- **Key difference**: Instead of accumulating stdout in a string, reads line-by-line with `fgets()` and calls `$onLine($line)` immediately
- Collects stderr separately (for error reporting at the end)
- Returns only exit code + stderr (the caller already received all stdout via callback)

### Modify `buildCommand()` — New `$streaming` Parameter

Add a `bool $streaming = false` parameter:
- When `true`: use `--output-format stream-json --verbose --include-partial-messages`
- When `false`: use `--output-format json` (existing behavior, unchanged)

This keeps `buildCommand()` as a single source of truth for CLI flag construction.

### Read Loop Change

Current (blocking):
```php
$output .= fread($pipes[1], 8192);
usleep(100000);
```

Streaming:
```php
while (($line = fgets($pipes[1])) !== false) {
    $line = trim($line);
    if ($line !== '') {
        $onLine($line);
    }
}
```

`fgets()` blocks until a newline arrives, which is exactly the Claude CLI stream-json delimiter. This means zero CPU polling — the process sleeps until data is available.

Timeout is still enforced by checking `time() - $startTime` in the outer loop around `fgets()`. Since `fgets()` blocks, we use `stream_set_timeout()` on the pipe to ensure it doesn't block indefinitely.

---

## Step 2: ScratchPadController — Add `actionStreamClaude()`

**File**: `yii/controllers/ScratchPadController.php`

### New Action

```php
/**
 * @throws NotFoundHttpException
 */
public function actionStreamClaude(int $id): void
{
    // 1. Validate model (same as actionRunClaude)
    // 2. Parse request body (same as actionRunClaude — POST body with JSON)
    // 3. Resolve prompt markdown (same logic)
    // 4. Set SSE headers + disable output buffering
    // 5. Call ClaudeCliService::executeStreaming() with $onLine callback
    // 6. In callback: echo "data: $line\n\n"; flush();
    // 7. After completion: echo "data: [DONE]\n\n"; flush();
}
```

### SSE Header Setup

```php
$response = Yii::$app->response;
$response->format = Response::FORMAT_RAW;
$response->headers->set('Content-Type', 'text/event-stream');
$response->headers->set('Cache-Control', 'no-cache');
$response->headers->set('Connection', 'keep-alive');
$response->headers->set('X-Accel-Buffering', 'no'); // nginx proxy buffering off
$response->send();

// Disable PHP output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}
```

### Why POST with EventSource Workaround

`EventSource` only supports GET. But we need to send a JSON body (prompt, options, session ID). Two options:

**Option A — Two-step flow** (chosen):
1. Frontend `POST`s the prompt data to a small "prepare" endpoint that stores it in the PHP session and returns a `streamToken`
2. Frontend opens `EventSource` to `GET /scratch-pad/stream-claude?id=X&token=Y`
3. Backend retrieves the stored prompt data from session using the token
4. Session data is consumed (one-time use)

**Option B — fetch + ReadableStream**:
1. Use `fetch()` POST with `response.body.getReader()` to read the stream
2. No EventSource needed, direct POST with body
3. Slightly more JS code for the reader loop, but avoids the two-step flow

**Decision: Option B (fetch + ReadableStream)**. It's simpler — one request, no session storage, no prepare endpoint. The JS reader loop is straightforward and well-supported in all modern browsers.

### Revised: `actionStreamClaude()` Accepts POST Directly

```php
public function actionStreamClaude(int $id): void
{
    // Standard model/prompt resolution (same as actionRunClaude)
    // ...

    // SSE-style output over POST response
    Yii::$app->response->format = Response::FORMAT_RAW;
    Yii::$app->response->headers->set('Content-Type', 'text/event-stream');
    Yii::$app->response->headers->set('Cache-Control', 'no-cache');
    Yii::$app->response->headers->set('X-Accel-Buffering', 'no');
    Yii::$app->response->send();

    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    $onLine = function (string $line): void {
        echo "data: " . $line . "\n\n";
        flush();
    };

    $result = $this->claudeCliService->executeStreaming(
        $markdown,
        $workingDirectory,
        $onLine,
        300,
        $options,
        $project,
        $sessionId
    );

    // Send any stderr as a final error event
    if ($result['error'] !== '') {
        echo "data: " . json_encode([
            'type' => 'server_error',
            'error' => $result['error'],
            'exitCode' => $result['exitCode'],
        ]) . "\n\n";
        flush();
    }

    echo "data: [DONE]\n\n";
    flush();
}
```

### VerbFilter + AccessControl

- Add `'stream-claude' => ['POST']` to VerbFilter
- Add `'stream-claude'` to the action permission map (same RBAC as `run-claude`)

---

## Step 3: Frontend — Stream Consumer

**File**: `yii/views/scratch-pad/claude.php`

### Replace `fetch().then(json)` with Streaming Reader

In `ClaudeChat.send()`, replace the current fetch block with:

```js
send: function() {
    // ... existing prompt extraction and validation ...

    var self = this;

    // Show loading state
    this.showStreamingPlaceholder();

    fetch(streamClaudeUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(options)
    })
    .then(function(response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';

        function processStream() {
            return reader.read().then(function(result) {
                if (result.done) {
                    self.onStreamEnd();
                    return;
                }
                buffer += decoder.decode(result.value, { stream: true });
                var lines = buffer.split('\n');
                buffer = lines.pop(); // keep incomplete line in buffer

                lines.forEach(function(line) {
                    if (line.startsWith('data: ')) {
                        var payload = line.substring(6);
                        if (payload === '[DONE]') {
                            self.onStreamEnd();
                            return;
                        }
                        try {
                            self.onStreamEvent(JSON.parse(payload));
                        } catch (e) {
                            // skip unparseable lines
                        }
                    }
                });

                return processStream();
            });
        }

        return processStream();
    })
    .catch(function(error) {
        self.onStreamError(error.message);
    });
}
```

### New JS Methods

```
onStreamEvent(data)       — Routes events by type
onStreamTextDelta(text)   — Appends text chunk, triggers throttled re-render
onStreamInit(data)        — Captures session_id, model from init event
onStreamResult(data)      — Captures final metadata (duration, tokens)
onStreamEnd()             — Finalizes response, shows metadata, re-enables Send
onStreamError(msg)        — Shows error in response bubble
```

### Progressive Markdown Rendering

```js
streamBuffer: '',
renderTimer: null,

onStreamTextDelta: function(text) {
    this.streamBuffer += text;

    // Throttle re-renders to every 100ms
    if (!this.renderTimer) {
        var self = this;
        this.renderTimer = setTimeout(function() {
            self.renderTimer = null;
            self.renderStreamContent();
        }, 100);
    }
},

renderStreamContent: function() {
    var bodyEl = document.getElementById('claude-stream-body');
    if (bodyEl) {
        bodyEl.innerHTML = this.renderMarkdown(this.streamBuffer);
    }
}
```

The throttle prevents excessive DOM updates (Claude can emit 20+ deltas/second). 100ms interval gives smooth visual flow without performance impact.

### Streaming Loading Placeholder

Replace the current `showLoadingPlaceholder()` with `showStreamingPlaceholder()`:

```js
showStreamingPlaceholder: function() {
    this.hideEmptyState();
    this.streamBuffer = '';
    var responseEl = document.getElementById('claude-current-response');
    responseEl.innerHTML = '';

    var div = document.createElement('div');
    div.className = 'claude-message claude-message--claude claude-message--streaming';

    var header = document.createElement('div');
    header.className = 'claude-message__header';
    header.innerHTML = '<i class="bi bi-terminal-fill"></i> Claude';
    div.appendChild(header);

    var body = document.createElement('div');
    body.id = 'claude-stream-body';
    body.className = 'claude-message__body';
    body.innerHTML = '<div class="claude-thinking-indicator">' +
        '<span class="claude-thinking-indicator__dot"></span> Thinking...</div>';
    div.appendChild(body);

    responseEl.appendChild(div);
    responseEl.classList.remove('d-none');
}
```

When the first `content_block_delta` arrives, the "Thinking..." indicator is replaced by actual text content.

---

## Step 4: CSS Additions

**File**: `yii/web/css/claude-chat.css`

```css
/* Streaming state */
.claude-message--streaming {
    border-color: #0d6efd;
    border-left-width: 3px;
}

/* Thinking indicator (replaces bouncing dots during stream init) */
.claude-thinking-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #6c757d;
    font-size: 0.9rem;
    font-style: italic;
}

.claude-thinking-indicator__dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #6c757d;
    animation: claude-thinking-pulse 1.5s ease-in-out infinite;
}

@keyframes claude-thinking-pulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 1; }
}

/* Streaming cursor effect (blinking caret at end of streamed text) */
.claude-message--streaming .claude-message__body::after {
    content: '\2588'; /* block cursor character */
    animation: claude-cursor-blink 1s step-end infinite;
    color: #0d6efd;
    font-weight: normal;
}

@keyframes claude-cursor-blink {
    0%, 50% { opacity: 1; }
    50.01%, 100% { opacity: 0; }
}
```

---

## Step 5: Tests

**File**: `yii/tests/unit/services/ClaudeCliServiceTest.php`

### New Test Cases

1. **`testExecuteStreamingCallsOnLineForEachOutputLine`**
   - Mock a process that outputs 3 lines of stream-json
   - Verify the `$onLine` callback is called 3 times with the correct lines

2. **`testBuildCommandWithStreamingFlag`**
   - Verify `buildCommand([], null, true)` produces `--output-format stream-json --verbose --include-partial-messages`
   - Verify `buildCommand([], null, false)` still produces `--output-format json`

3. **`testExecuteStreamingReturnsExitCodeAndStderr`**
   - Verify the return value structure `{exitCode, error}`

### Testing Strategy

Since `executeStreaming()` uses `proc_open` (same as `execute()`), tests follow the same pattern as existing `ClaudeCliServiceTest` — they test the command construction and callback wiring, not the actual CLI execution.

---

## Step 6: Wire Up the View

### View Changes

1. Generate `$streamClaudeUrl` with `Url::to(['/scratch-pad/stream-claude', 'id' => $model->id])`
2. Pass it into the JS scope alongside existing `$runClaudeUrl`
3. `ClaudeChat.send()` uses `streamClaudeUrl` instead of `runClaudeUrl`
4. Keep `runClaudeUrl` available as fallback reference

### Backward Compatibility

- `actionRunClaude()` remains unchanged — can be used by other consumers or as a fallback
- The streaming endpoint returns the same logical data, just incrementally
- Session IDs work identically between both endpoints

---

## Implementation Order

1. `ClaudeCliService::buildCommand()` — add `$streaming` parameter
2. `ClaudeCliService::executeStreaming()` — new method
3. `ScratchPadController::actionStreamClaude()` — new action
4. VerbFilter + RBAC for the new action
5. CSS additions for streaming states
6. Frontend JS — stream consumer, progressive rendering
7. View wiring — URL generation, swap fetch to stream
8. Tests

---

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| nginx/reverse proxy buffers SSE | `X-Accel-Buffering: no` header; document proxy config |
| PHP output buffering prevents flush | Explicit `ob_end_flush()` loop before streaming |
| Long responses exceed PHP memory | Streaming avoids accumulation — each line is flushed immediately |
| Browser `fetch` ReadableStream support | Supported in all modern browsers (Chrome 43+, Firefox 65+, Safari 10.1+) |
| Claude CLI changes stream format | Each line is JSON-parsed independently; unknown types are silently skipped |
| Timeout during streaming | Same `stream_set_timeout` + elapsed time check as blocking mode |

---

## Files Changed Summary

| File | Change |
|------|--------|
| `yii/services/ClaudeCliService.php` | Add `executeStreaming()`, modify `buildCommand()` |
| `yii/controllers/ScratchPadController.php` | Add `actionStreamClaude()`, update `behaviors()` |
| `yii/views/scratch-pad/claude.php` | Add stream URL, rewrite `send()`, add stream handlers |
| `yii/web/css/claude-chat.css` | Streaming indicator + cursor styles |
| `yii/tests/unit/services/ClaudeCliServiceTest.php` | Tests for streaming command + callback |
