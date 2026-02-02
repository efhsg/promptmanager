# Claude Chat Streaming — Functional Specification

## Overview

Replace the blocking request/response pattern in the Claude chat page with real-time streaming. The user sees Claude's response appear token-by-token as it's generated, instead of waiting for the full response to load at once.

**Affected route**: `POST /scratch-pad/run-claude?id={scratchPadId}` (new streaming variant)

---

## Current Behavior (Blocking)

1. User clicks Send
2. JS fires `fetch()` POST to `actionRunClaude()`
3. Backend calls `ClaudeCliService::execute()` which uses `proc_open` + blocking `while(true)` poll loop
4. Claude CLI runs with `--output-format json` (single JSON blob at end)
5. PHP collects all output, parses it, returns full JSON response
6. Frontend shows bouncing dots ("Running Claude CLI...") for the entire duration (often 10-60s)
7. Once the response arrives, the full rendered output replaces the loading state

**Problem**: The user stares at a static loading animation for the entire generation time. No feedback on what Claude is thinking, doing, or how far along it is. On complex prompts (code generation, analysis), this can easily be 30-60+ seconds of dead time.

---

## New Behavior (Streaming)

### What the User Sees

1. User clicks Send
2. Within ~1-2 seconds, a "Claude is thinking..." indicator appears
3. Text begins appearing token-by-token in the response area
4. As more text arrives, the response renders incrementally (with progressive markdown rendering)
5. When Claude finishes, the metadata bar appears (duration, tokens, model) and the response is finalized

### Stream Event Types (from Claude CLI)

The Claude CLI with `--output-format stream-json --verbose --include-partial-messages` emits newline-delimited JSON with these event types:

| Event | When | Contains |
|-------|------|----------|
| `{"type":"system","subtype":"init"}` | First line | `session_id`, `model`, `tools`, `permissionMode` |
| `{"type":"stream_event","event":{"type":"message_start"}}` | Generation begins | Initial usage stats |
| `{"type":"stream_event","event":{"type":"content_block_start"}}` | New content block | Block type (`text`, `tool_use`, `thinking`) |
| `{"type":"stream_event","event":{"type":"content_block_delta"}}` | Token(s) generated | `delta.text` (the actual text chunk) |
| `{"type":"stream_event","event":{"type":"content_block_stop"}}` | Block finished | — |
| `{"type":"stream_event","event":{"type":"message_delta"}}` | Turn complete | `stop_reason`, final usage |
| `{"type":"assistant"}` | Full message | Complete content array (redundant with deltas, useful for history) |
| `{"type":"result"}` | Session done | `duration_ms`, `modelUsage`, `session_id`, `is_error` |

### Response Phases in the UI

**Phase 1 — Initializing** (0-2s):
- "Claude is thinking..." with animated indicator
- Triggered: as soon as fetch connection opens, before any data arrives

**Phase 2 — Streaming text** (2s-Ns):
- Text appears progressively in the response bubble
- Markdown is rendered incrementally (simple approach: re-render full accumulated text on each chunk; throttled to ~100ms intervals)
- User sees text flowing in naturally

**Phase 3 — Complete**:
- Final markdown render with full syntax highlighting
- Metadata bar appears (duration, tokens, model, config source)
- Send button re-enabled
- Response finalized and stored in conversation history

### Error During Stream

- If the stream errors or the CLI exits non-zero, show error in the response bubble
- Partial output already shown stays visible (useful for debugging)
- Error alert appended below partial output

---

## HTTP Transport: Server-Sent Events (SSE)

### Why SSE over chunked fetch

- SSE provides automatic reconnection, event typing, and built-in `EventSource` API
- Clean separation: each line from Claude CLI stdout becomes one SSE `data:` line
- Works with Yii2 without framework changes (just output buffering + flush)
- Compatible with all modern browsers

### SSE Protocol

```
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive

data: {"type":"system","subtype":"init","session_id":"...","model":"..."}

data: {"type":"stream_event","event":{"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Hello"}}}

data: {"type":"result","duration_ms":12345,"session_id":"...","modelUsage":{...}}

data: [DONE]
```

Each line from Claude CLI stdout is forwarded as-is in a `data:` field. The final `[DONE]` sentinel signals stream end.

### Why Not WebSockets

- Overkill for unidirectional server→client streaming
- Requires additional infrastructure (ws server, proxy config)
- SSE is simpler, works over standard HTTP, and is sufficient for this use case

---

## Scope

### In Scope
- New streaming endpoint (`actionStreamClaude`)
- New streaming method in `ClaudeCliService`
- Frontend SSE consumer replacing `fetch()` for the run action
- Progressive text rendering with throttled markdown re-render
- Graceful fallback: if streaming fails, show error (not a silent fallback to blocking)

### Out of Scope
- Tool use visualization (showing which tools Claude is calling) — future enhancement
- Thinking block visualization (extended thinking content) — future enhancement
- Abort/cancel button — future enhancement (requires `proc_terminate` signaling)
- Streaming for non-scratch-pad contexts
- Changes to the existing `actionRunClaude` (kept as-is for backward compatibility)

---

## Access Control

Same RBAC as existing `run-claude` action. The new `stream-claude` action reuses the same permission mapping (`viewScratchPad` owner rule).

---

## Session Continuity

The streaming endpoint accepts and returns `sessionId` just like the blocking endpoint. The `--continue` flag works identically with stream-json output format.
