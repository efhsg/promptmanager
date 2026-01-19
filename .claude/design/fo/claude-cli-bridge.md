# Launch Claude CLI from Browser - Design Document

## Problem Statement

### Current Situation
Gebruikers van PromptManager moeten handmatig prompts kopieren naar het clipboard en vervolgens Claude CLI opstarten in een terminal om de prompt te gebruiken. Dit vereist meerdere stappen en context-switching tussen browser en terminal.

### Desired Situation
Met één klik op een "Launch" button in PromptManager opent een terminal met Claude CLI, waarbij de prompt automatisch is geladen en klaar staat voor gebruik.

### Measurable Goals
- Reductie van stappen van 4+ (kopieer → open terminal → type commando → plak) naar 1 (klik)
- Bridge server response time < 500ms
- Terminal opent binnen 2 seconden na klik

---

## Out of Scope

De volgende items worden **niet** opgelost in deze implementatie:

| Item | Reden |
|------|-------|
| Windows support | Initiële focus op Linux/macOS; Windows kan later worden toegevoegd |
| Remote server deployment | Bridge draait uitsluitend op localhost |
| Session management | Elke launch is een nieuwe onafhankelijke sessie |
| Prompt history in bridge | Claude CLI beheert eigen history |
| Authentication in bridge | Vertrouwt op Claude CLI's eigen auth |
| Automatische bridge updates | Handmatige update via git pull |

---

## Glossary

| Term | Definitie |
|------|-----------|
| **Bridge** | Lokale HTTP server die verzoeken van de browser ontvangt en terminal-processen spawnt |
| **Prompt** | De tekstinhoud (Quill Delta geconverteerd naar platte tekst) die naar Claude CLI wordt gestuurd |
| **Plan mode** | Claude CLI modus (`--permission-mode plan`) voor read-only codebase exploratie |
| **Launch** | De actie waarbij een terminal opent met Claude CLI en de prompt automatisch geladen |
| **Origin** | De bron-URL van een HTTP request, gebruikt voor security validation |

---

## Options Analysis

### Option 1: Custom URL Protocol Handler

**How it works:**
- Register a custom protocol like `claude-launch://` on the OS
- Browser opens URLs like `claude-launch://prompt?text=...`
- A registered handler script receives the URL and spawns Claude CLI in a terminal

**Pros:**
- Native OS integration
- Works from any browser
- No server needed

**Cons:**
- Requires one-time OS-level setup per machine
- URL length limits (~2000 chars) may truncate long prompts
- Different setup for Linux/Mac/Windows
- Security prompts on first use

**Implementation:**
- Linux: `.desktop` file in `~/.local/share/applications/`
- macOS: App bundle with URL handler in `Info.plist`
- Windows: Registry entries

---

### Option 2: Local HTTP Bridge Server (Selected)

**How it works:**
- Small local server listens on localhost:PORT
- Browser sends POST request with prompt content
- Server spawns Claude CLI in a new terminal window

#### Docker Considerations

**Can we Dockerize the bridge?**

The challenge: Claude CLI needs to run in an **interactive terminal on the host**, not inside Docker. The bridge server's job is to spawn that terminal.

| Scenario | Feasible? | Notes |
|----------|-----------|-------|
| Bridge in Docker, Claude CLI in Docker | No | No interactive terminal possible |
| Bridge in Docker, spawn CLI on host | Risky | Requires host socket/API access - defeats Docker isolation |
| Bridge on host, Claude CLI on host | Yes | Simple, works, but not containerized |

**Recommendation:** Don't Dockerize the bridge. It needs to spawn host processes anyway. A simple install script is more practical.

#### Cross-Platform Implementation

```
┌─────────────────┐     POST /launch      ┌─────────────────┐
│     Browser     │ ──────────────────────│   Bridge Server │
│  (PromptManager)│     {prompt: "..."}   │   (localhost)   │
└─────────────────┘                       └────────┬────────┘
                                                   │
                                          spawns terminal
                                                   │
                                          ┌────────▼────────┐
                                          │   Claude CLI    │
                                          │   (interactive) │
                                          └─────────────────┘
```

**Platform-specific terminal spawning:**
- **Linux:** `gnome-terminal`, `konsole`, `xterm`, or `$TERMINAL`
- **macOS:** `osascript` to open Terminal.app or iTerm

#### Pros
- No URL length limits
- Full control over terminal options
- Works with existing Claude CLI auth
- Can pass complex data as JSON

#### Cons
- Requires running a background service (but lightweight)
- Not containerized (but for good reason)

---

### Option 3: Browser Extension

**How it works:**
- Chrome/Firefox extension with native messaging
- Extension communicates with a native host application
- Native host spawns Claude CLI

**Pros:**
- Deep browser integration
- Can access page content directly
- Works with any URL length

**Cons:**
- Requires extension installation
- Native messaging host setup
- Different implementation per browser

---

## API Contract

### Endpoint

```
POST /launch HTTP/1.1
Host: localhost:9872
Content-Type: application/json
Origin: http://localhost:8080
```

### API Version

Current version: `1.0`

Versioning strategy:
- Version in response header: `X-Bridge-Version: 1.0`
- Breaking changes increment major version
- Backwards-compatible changes increment minor version
- Clients should check version header for compatibility warnings

### Request Schema

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "object",
    "required": ["prompt"],
    "properties": {
        "prompt": {
            "type": "string",
            "minLength": 1,
            "maxLength": 1048576,
            "description": "Plain text prompt content (max 1MB)"
        },
        "mode": {
            "type": "string",
            "enum": ["plan", "normal"],
            "default": "plan",
            "description": "Claude CLI permission mode"
        },
        "workingDirectory": {
            "type": "string",
            "description": "Optional working directory for Claude CLI"
        }
    }
}
```

**Example Request:**
```json
{
    "prompt": "Analyze the authentication flow in this codebase",
    "mode": "plan",
    "workingDirectory": "/home/user/projects/myapp"
}
```

### Success Response

```
HTTP/1.1 200 OK
Content-Type: application/json
X-Bridge-Version: 1.0
```

```json
{
    "success": true,
    "data": {
        "launched": true,
        "terminal": "gnome-terminal",
        "tempFile": "prompt-1705612345678-a1b2c3.txt",
        "timestamp": "2024-01-18T14:30:00.000Z"
    }
}
```

### Error Responses

See [Error Handling](#error-handling) for complete error response specifications.

### Idempotency

`/launch` is **NOT idempotent**:
- Each call spawns a new terminal instance
- Duplicate requests result in multiple terminals
- Client is responsible for preventing accidental double-clicks (UI debouncing)

**Rationale:** Idempotency keys would add complexity without benefit; the use case (user-initiated click) naturally prevents rapid duplicates, and UI debouncing provides sufficient protection.

---

## Data Ownership & Lineage

### System of Record

| Data | System of Record | Owner | Retention |
|------|------------------|-------|-----------|
| Prompt content (Quill Delta) | PromptManager database | User | Permanent (in app) |
| Prompt content (plain text) | Bridge temp file | Bridge process | Transient (5 seconds) |
| Launch metadata | None (not persisted) | N/A | None |

### Data Flow & Lineage

```
┌─────────────────────┐
│   PromptManager     │  Source of truth: Quill Delta JSON
│   (PostgreSQL)      │
└──────────┬──────────┘
           │
           │ Read by widget
           ▼
┌─────────────────────┐
│   Browser/Widget    │  Conversion: Quill Delta → Plain text
│   (JavaScript)      │  Owner: Client-side code
└──────────┬──────────┘
           │
           │ POST /launch
           ▼
┌─────────────────────┐
│   Bridge Server     │  Transient storage: temp file
│   (Node.js)         │  Owner: Bridge process
└──────────┬──────────┘
           │
           │ Read by Claude CLI
           ▼
┌─────────────────────┐
│   Claude CLI        │  Final consumer
│   (Terminal)        │  Owner: User session
└─────────────────────┘
```

### Audit Metadata

Geen audit logging in MVP. Zie [Risks & Decision Points](#risks--decision-points) voor logging-besluitpunt.

Minimale audit-metadata indien logging wordt geactiveerd:

| Field | Description |
|-------|-------------|
| timestamp | ISO 8601 launch time |
| origin | Request origin header |
| promptLength | Character count (niet de inhoud) |
| terminal | Spawned terminal type |
| success | Boolean launch result |

---

## Quill Delta Conversion

### Conversion Rules

De conversie van Quill Delta naar plain text gebeurt client-side in JavaScript voordat de prompt naar de bridge wordt gestuurd.

| Quill Element | Plain Text Output | Semantisch Verlies |
|---------------|-------------------|-------------------|
| Regular text | Ongewijzigd | Geen |
| Bold/Italic/Underline | Tekst zonder formatting | Ja - emphasis |
| Headers (h1-h6) | Tekst met newline | Ja - hierarchy |
| Lists (ordered/unordered) | Tekst met newlines | Ja - structure |
| Code blocks | Tekst met newlines | Gedeeltelijk - whitespace behouden |
| Links | Alleen link tekst | Ja - URL verloren |
| Images | `[Image]` placeholder | Ja - volledig |
| Embeds | Genegeerd | Ja - volledig |

### Conversion Implementation

```javascript
// Simplified conversion logic
function deltaToPlainText(delta) {
    return delta.ops
        .map(op => {
            if (typeof op.insert === 'string') {
                return op.insert;
            }
            if (op.insert?.image) {
                return '[Image]';
            }
            return '';
        })
        .join('');
}
```

### Acceptabel Verlies

**Besluit:** Verlies van rich-text semantiek is acceptabel voor de launch use-case omdat:

1. Claude CLI werkt met plain text input
2. De kern-instructie (tekst) blijft behouden
3. Gebruiker kan originele formatted content zien in PromptManager
4. Code blocks behouden whitespace, wat cruciaal is voor code

**Niet acceptabel voor:** Use-cases waar formatting essentieel is (bijv. structured data, tables). Deze worden niet ondersteund in MVP.

---

## Roles & Permissions

### User Roles

| Rol | Mag Launch Button Zien | Mag Launch Uitvoeren | Notitie |
|-----|------------------------|----------------------|---------|
| Authenticated User | Ja | Ja | Standaard gedrag |
| Guest/Anonymous | Nee | Nee | Geen toegang tot prompts |
| Admin | Ja | Ja | Geen extra privileges voor launch |

### Access Control

Launch-functionaliteit volgt bestaande PromptManager RBAC:
- Gebruiker moet ingelogd zijn
- Gebruiker moet eigenaar zijn van de prompt (of prompt moet gedeeld zijn)
- Geen aparte permission voor launch; wie prompt kan lezen, kan launchen

### Operational Ownership

| Verantwoordelijkheid | Eigenaar | Notitie |
|---------------------|----------|---------|
| Bridge installatie | Eindgebruiker | Self-service via install script |
| Bridge updates | Eindgebruiker | Handmatig via git pull |
| Service beheer (start/stop) | Eindgebruiker | Systemd/LaunchAgent optioneel |
| Troubleshooting | Eindgebruiker + docs | README met FAQ |
| Security patches | Project maintainer | Via git repository |

---

## Feature Toggle & Rollback

### Feature Toggle Strategy

De launch-functionaliteit kan worden uitgeschakeld via configuratie:

**Server-side (Yii2):**
```php
// config/params.php
return [
    'features' => [
        'claudeBridgeLaunch' => true,  // Toggle feature on/off
    ],
];
```

**Widget-level check:**
```php
// In widget render logic
if (!Yii::$app->params['features']['claudeBridgeLaunch']) {
    return ''; // Don't render launch button
}
```

### Rollback Procedure

| Scenario | Actie | Downtime |
|----------|-------|----------|
| Bug in frontend | Set `claudeBridgeLaunch = false` in params | < 1 minuut |
| Bug in bridge server | Stop bridge service | Geen (graceful degradation) |
| Security issue | Beide bovenstaande | < 1 minuut |
| Complete removal | Revert git commits | Deploy cycle |

### Graceful Degradation

Wanneer bridge offline is:
- Launch button toont "offline" state
- Functionaliteit degradeert naar copy-to-clipboard
- Geen errors, alleen informatieve tooltip

---

## Risks & Decision Points

### Risk Register

| ID | Risk | Impact | Kans | Mitigatie | Eigenaar |
|----|------|--------|------|-----------|----------|
| R1 | Bridge service niet gestart | Feature werkt niet | Hoog | Duidelijke status indicator + install instructions | User |
| R2 | Port conflict met andere service | Bridge start niet | Laag | Configureerbare port + duidelijke error message | User |
| R3 | Terminal emulator niet gevonden | Launch faalt | Medium | Detectie cascade + fallback lijst | Bridge |
| R4 | Temp file permission issues | Launch faalt | Laag | Permission check + instructies | Bridge |
| R5 | Malicious origin request | Security breach | Laag | Origin validation + localhost-only | Bridge |
| R6 | Large prompt causes timeout | Poor UX | Laag | Size limit (1MB) + progress indicator | Widget |

### Decision Points

| ID | Besluitpunt | Opties | Aanbeveling | Eigenaar | Status |
|----|-------------|--------|-------------|----------|--------|
| D1 | Option 2 als target-oplossing | Option 1/2/3 | Option 2 (HTTP Bridge) | Architect | **BESLOTEN** |
| D2 | Feature toggle mechanisme | Config param / env var / DB | Config param | Developer | **BESLOTEN** |
| D3 | Logging/monitoring scope | Geen / Minimal / Full | Minimal (alleen errors) | Security | **BESLOTEN** |
| D4 | Windows support in MVP | Ja / Nee | Nee (later toevoegen) | PO | **BESLOTEN** |
| D5 | Auto-start bridge service | Verplicht / Optioneel / Geen | Optioneel | Developer | **BESLOTEN** |

### Besluit: Logging Scope (D3)

**Besluit:** Minimal logging (alleen errors) naar stderr.

**Policy:**
- Errors worden gelogd naar stderr met timestamp en error code
- Geen prompt-content wordt gelogd (privacy)
- Geen success-launches worden gelogd (alleen errors)
- Log format: `[ISO-timestamp] [ERROR] error_code: message`

**Audit metadata bij errors:**

| Field | Gelogd | Reden |
|-------|--------|-------|
| timestamp | Ja | Debugging |
| error_code | Ja | Categorisatie |
| error_message | Ja | Debugging |
| origin | Ja | Security audit |
| promptLength | Nee | Niet relevant voor errors |
| promptContent | **Nee** | Privacy |

**Rationale:** Minimal logging biedt voldoende debugging-informatie voor incident-analyse zonder privacy-risico's. Volledige audit trail is niet nodig voor een lokale developer tool.

---

## Security Model

### Origin Validation

De bridge server accepteert alleen requests van vertrouwde origins:

```javascript
const ALLOWED_ORIGINS = [
    'http://localhost',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:8080',
    'https://promptmanager.local'  // Configurable via env
];
```

**Implementatie:**
- Check `Origin` header op elke request
- Reject requests zonder Origin header of met onbekende origin
- Return `403 Forbidden` bij ongeldige origin

### CORS Restrictie

```javascript
// CORS headers alleen voor toegestane origins
res.setHeader('Access-Control-Allow-Origin', validatedOrigin);
res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
```

### CSRF Mitigatie

| Maatregel | Implementatie |
|-----------|---------------|
| Origin check | Verplicht op alle requests |
| POST-only | GET requests worden niet geaccepteerd voor /launch |
| Content-Type check | Alleen `application/json` geaccepteerd |
| Localhost binding | Server bindt alleen aan `127.0.0.1`, niet `0.0.0.0` |

### Threat Model

| Threat | Mitigatie |
|--------|-----------|
| Malicious website triggert launch | Origin validation blokkeert externe sites |
| XSS in PromptManager | Standaard Yii2 CSRF/XSS bescherming |
| Man-in-the-middle | Localhost-only, geen network exposure |
| Temp file exposure | Restrictieve permissions (0600), zie Temp File Lifecycle |

---

## Error Handling

| Scenario | HTTP Status | Response | Gebruikersfeedback |
|----------|-------------|----------|-------------------|
| Bridge offline | - | Connection refused | Tooltip: "Bridge offline - run install script" |
| Invalid origin | 403 | `{"error": "origin_forbidden"}` | Console warning, button disabled |
| Invalid JSON body | 400 | `{"error": "invalid_json"}` | Toast: "Invalid request format" |
| Missing prompt | 400 | `{"error": "prompt_required"}` | Toast: "No prompt content to launch" |
| Prompt too large (>1MB) | 413 | `{"error": "prompt_too_large"}` | Toast: "Prompt exceeds maximum size" |
| Temp file write failure | 500 | `{"error": "temp_file_failed"}` | Toast: "Failed to prepare prompt file" |
| Terminal spawn failure | 500 | `{"error": "terminal_spawn_failed"}` | Toast: "Failed to open terminal" |
| Terminal not found | 500 | `{"error": "no_terminal_found"}` | Toast: "No compatible terminal found" |
| Port already in use | - | Server fails to start | CLI output: "Port 9872 in use, try CLAUDE_BRIDGE_PORT=9873" |

### Error Response Format

```json
{
    "success": false,
    "error": "error_code",
    "message": "Human-readable error description",
    "details": {}  // Optional additional context
}
```

---

## Temp File Lifecycle

### Location

| OS | Path |
|----|------|
| Linux | `$XDG_RUNTIME_DIR/claude-bridge/` of `/tmp/claude-bridge/` |
| macOS | `$TMPDIR/claude-bridge/` |

### File Naming

Format: `prompt-{timestamp}-{random}.txt`

Voorbeeld: `prompt-1705612345678-a1b2c3.txt`

### Permissions

```javascript
// Restrictieve permissions: alleen eigenaar kan lezen/schrijven
fs.writeFileSync(tempPath, promptContent, { mode: 0o600 });
```

### Cleanup Strategy

| Trigger | Actie |
|---------|-------|
| Na terminal spawn (success) | Wacht 5 seconden, verwijder temp file |
| Na terminal spawn (failure) | Verwijder temp file onmiddellijk |
| Server startup | Verwijder alle files ouder dan 1 uur in bridge directory |
| Server shutdown (graceful) | Verwijder alle temp files |

### Security Considerations

- Temp directory wordt aangemaakt met `0700` permissions
- Files worden nooit in world-readable directories geplaatst
- Filenames bevatten geen user-input (voorkomt path traversal)
- Content wordt niet gelogd

---

## Assumptions

| Assumption | Validation | Fallback |
|------------|------------|----------|
| Node.js 18+ is geinstalleerd | Check bij server start | Error met installatie-instructies |
| Claude CLI is geinstalleerd en geconfigureerd | Check bij spawn | Error: "claude command not found" |
| Gebruiker heeft terminal emulator | Detectie via env vars | Lijst van te installeren terminals |
| Port 9872 is beschikbaar | Check bij bind | Suggereer alternatieve port |
| $TERMINAL of bekende terminal beschikbaar | Detectie cascade | Fallback lijst: gnome-terminal, konsole, xterm |
| Temp directory is writable | Check bij file write | Error met permissions instructies |

---

## Implementation Plan

### 1. Bridge Server (`scripts/claude-bridge/`)

Create a simple Node.js server (portable, no compilation needed):

```
scripts/claude-bridge/
├── server.js          # HTTP server, terminal spawner
├── package.json       # Minimal dependencies
├── config.js          # Configuration with defaults
├── terminals.js       # Terminal detection and spawning
├── security.js        # Origin validation, CORS
├── install.sh         # Linux/macOS installer
└── README.md          # Usage documentation
```

**server.js features:**
- Listen on `localhost:9872` (configurable)
- Accept POST `/launch` with `{ prompt: "...", mode: "plan" }`
- Validate origin header against allowlist
- Detect OS and spawn appropriate terminal
- Write prompt to temp file with restrictive permissions
- Run: `claude --permission-mode plan -p "$(cat /tmp/claude-bridge/prompt-xxx.txt)"`
- Cleanup temp file after spawn

### 2. PromptManager Integration

Add "Launch in Claude CLI" button next to existing copy buttons:

- **Icon:** `bi bi-box-arrow-up-right` (launch icon)
- **Action:** POST to `http://localhost:9872/launch`
- **Fallback:** If bridge unavailable, show message with install instructions

### 3. Files to Create/Modify

| File | Action |
|------|--------|
| `scripts/claude-bridge/server.js` | Create - HTTP bridge server |
| `scripts/claude-bridge/package.json` | Create - Dependencies |
| `scripts/claude-bridge/config.js` | Create - Configuration |
| `scripts/claude-bridge/security.js` | Create - Security utilities |
| `scripts/claude-bridge/install.sh` | Create - Linux/macOS installer |
| `yii/widgets/CopyToClipboardWidget.php` | Modify - Add launch mode |
| `yii/widgets/QuillViewerWidget.php` | Modify - Add launch button option |
| `yii/widgets/ContentViewerWidget.php` | Modify - Add launch button option |
| `yii/views/scratch-pad/view.php` | Modify - Add launch button |
| `yii/views/prompt-instance/_form.php` | Modify - Add launch button |

---

## Configuration

### Server Configuration

| Setting | Env Var | Default | Description |
|---------|---------|---------|-------------|
| Port | `CLAUDE_BRIDGE_PORT` | 9872 | Server listen port |
| Allowed Origins | `CLAUDE_BRIDGE_ORIGINS` | localhost variants | Comma-separated origins |
| Terminal | `TERMINAL` | Auto-detect | Preferred terminal emulator |
| Temp Dir | `CLAUDE_BRIDGE_TMPDIR` | OS default | Temp file directory |

### Port Conflict Handling

```javascript
server.on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
        console.error(`Port ${port} is already in use.`);
        console.error(`Options:`);
        console.error(`  1. Stop the other process using port ${port}`);
        console.error(`  2. Use a different port: CLAUDE_BRIDGE_PORT=9873 node server.js`);
        console.error(`  3. Find process: lsof -i :${port} (Linux/macOS)`);
        process.exit(1);
    }
});
```

### Auto-start Options

- **Systemd (Linux):** Installer creates optional user service
- **LaunchAgent (macOS):** Installer creates optional plist
- **Manual:** User starts server when needed

### Status Indicator

Button tooltip reflects bridge status:
- Online: "Launch in Claude CLI"
- Offline: "Bridge offline - click for instructions"

---

## UI Behavior

### Launch Button States

| State | Appearance | Behavior |
|-------|------------|----------|
| Ready | Normal icon | Click triggers launch |
| Launching | Spinner icon | Click disabled |
| Success | Checkmark (2s) | Reverts to Ready |
| Error | Red icon | Tooltip shows error message |
| Offline | Grayed out | Click shows install modal |

### Error Display

| Error Type | Display Method | Duration |
|------------|----------------|----------|
| Bridge offline | Modal with instructions | Until dismissed |
| Launch failed | Toast notification | 5 seconds |
| Invalid response | Toast notification | 5 seconds |
| Network error | Tooltip on button | Persistent until retry |

### Button Placement

- Position to the left of CLI copy button (right: 100px)
- Icon: `bi bi-box-arrow-up-right`
- Consistent styling with existing copy buttons

### Accessibility

- Button has `aria-label` describing action
- Error states announced via `aria-live` region
- Keyboard accessible (Tab + Enter)

---

## Verification

### Positive Test Cases

| # | Test Case | Expected Result |
|---|-----------|-----------------|
| 1 | Install bridge and start server | Server starts, logs "Listening on 127.0.0.1:9872" |
| 2 | Click launch button with valid content | Terminal opens, Claude CLI starts with prompt |
| 3 | Launch with special characters in prompt | Characters preserved correctly |
| 4 | Launch with multi-line prompt | Line breaks preserved |
| 5 | Launch with Unicode content | Unicode rendered correctly |
| 6 | Server receives valid origin | Request processed |

### Negative Test Cases

| # | Test Case | Expected Result |
|---|-----------|-----------------|
| 7 | Click launch when bridge offline | Modal shows install instructions |
| 8 | Request from invalid origin | 403 Forbidden response |
| 9 | POST with invalid JSON | 400 Bad Request |
| 10 | POST with empty prompt | 400 error, toast shown |
| 11 | Start server when port in use | Clear error message with alternatives |
| 12 | Temp directory not writable | 500 error with permissions message |

### Edge Cases

| # | Test Case | Expected Result |
|---|-----------|-----------------|
| 13 | Very large prompt (~500KB) | Launch succeeds |
| 14 | Prompt at size limit (1MB) | Launch succeeds |
| 15 | Prompt over limit (>1MB) | 413 error, toast shown |
| 16 | Rapid successive clicks | Second click ignored during launch |
| 17 | Terminal closed immediately | No error (expected user action) |
| 18 | Bridge crashes mid-request | Connection error handled gracefully |

### Security Verification

| # | Test Case | Expected Result |
|---|-----------|-----------------|
| 19 | Request from external site | 403 Forbidden |
| 20 | Request without Origin header | 403 Forbidden |
| 21 | Temp file permissions | File readable only by owner |
| 22 | Temp file cleanup | File deleted after launch |

### Performance Verification

| # | Test Case | Target | Measurement Method | AC Link |
|---|-----------|--------|-------------------|---------|
| 23 | Bridge server response time | < 500ms | `console.time()` in browser, measured from fetch start to response received | AC-P1 |
| 24 | Terminal open time | < 2 seconds | Stopwatch from button click to terminal window visible | AC-P2 |
| 25 | Response time under load (10 sequential requests) | < 500ms each | Automated test script with timing | AC-P1 |
| 26 | Large prompt (500KB) response time | < 1000ms | `console.time()` measurement | AC-P3 |

**Test Environment:**
- Hardware: Standard developer laptop (4+ cores, 8GB+ RAM)
- OS: Linux (Ubuntu 22.04) or macOS (Ventura+)
- Network: localhost (no network latency)
- Load: Idle system, no heavy background processes

---

## Acceptance Criteria

### Must Have (MVP)

| ID | Criterion | Verification |
|----|-----------|--------------|
| AC-F1 | Bridge server starts and listens on localhost | Test #1 |
| AC-F2 | Origin validation blocks requests from non-allowed origins | Test #8, #19, #20 |
| AC-F3 | Launch button visible in scratch-pad view | Manual inspection |
| AC-F4 | Click on launch button opens terminal with Claude CLI | Test #2 |
| AC-F5 | Prompt content correctly passed to Claude CLI | Test #3, #4, #5 |
| AC-F6 | Temp files created with 0600 permissions | Test #21 |
| AC-F7 | Temp files cleaned up after launch | Test #22 |
| AC-F8 | Clear error message when bridge is offline | Test #7 |
| AC-F9 | Install script works on Linux and macOS | Manual test |

### Performance Criteria (Must Have)

| ID | Criterion | Target | Verification |
|----|-----------|--------|--------------|
| AC-P1 | Bridge server response time | < 500ms | Test #23, #25 |
| AC-P2 | Terminal open time from click | < 2 seconds | Test #24 |
| AC-P3 | Large prompt handling | < 1000ms for 500KB | Test #26 |

### Should Have

| ID | Criterion | Verification |
|----|-----------|--------------|
| AC-S1 | Configurable port via environment variable | Test #11 |
| AC-S2 | Configurable allowed origins | Config test |
| AC-S3 | Button in prompt-instance view | Manual inspection |
| AC-S4 | Visual feedback during launch (spinner) | Manual inspection |
| AC-S5 | Success confirmation (checkmark) | Manual inspection |
| AC-S6 | Systemd/LaunchAgent for auto-start | Install script test |

### Could Have

| ID | Criterion | Verification |
|----|-----------|--------------|
| AC-C1 | Windows support | N/A (out of scope) |
| AC-C2 | Multiple terminal emulator fallbacks | Terminal detection test |
| AC-C3 | Health check endpoint for monitoring | Endpoint test |
| AC-C4 | Launch statistics/logging | Log inspection |

---

## Appendix: Terminal Detection

```javascript
// Terminal detection cascade for Linux
const LINUX_TERMINALS = [
    { cmd: 'gnome-terminal', args: ['--', 'bash', '-c'] },
    { cmd: 'konsole', args: ['-e', 'bash', '-c'] },
    { cmd: 'xfce4-terminal', args: ['-e', 'bash -c'] },
    { cmd: 'xterm', args: ['-e', 'bash', '-c'] },
    { cmd: 'alacritty', args: ['-e', 'bash', '-c'] },
    { cmd: 'kitty', args: ['bash', '-c'] }
];

// Use $TERMINAL env var first if set
if (process.env.TERMINAL) {
    // Attempt to use user's preferred terminal
}
```
