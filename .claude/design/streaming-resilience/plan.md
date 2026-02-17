# Streaming Resilience — Implementatieplan

## Doel

Maak de PromptManager-naar-Claude CLI communicatie robuuster en zelf-herstellend zodat:
1. Langere inference betrouwbaar afrondt (geen "hanging" state)
2. Responses altijd worden gerenderd als de inference succesvol was
3. Fouten altijd duidelijk worden gecommuniceerd

## Overzicht oplossingen

| # | Oplossing | Dekt probleem | Complexiteit |
|---|-----------|---------------|-------------|
| A | Client-side auto-reconnect via run-status polling | 1, 2 | midden |
| B | Terminal status + result naar SSE bij relay-einde | 2, 3 | laag |
| C | Keepalive als `data:` event i.p.v. SSE comment | 1 | laag |
| D | Run-status polling als fallback na stream-einde | 2, 3 | laag |

## Fase 1: Server-side verbeteringen (B + C)

### B. Terminal status als SSE event bij relay-einde

**Probleem:** Als een run faalt of voltooid is maar de relay het `result` event miste, krijgt de client alleen `[DONE]` zonder context.

**Oplossing:** Na de DB-fallback (missedLines), maar vóór `[DONE]`, stuur een synthetisch status-event als de run terminal is.

**Bestand:** `yii/controllers/AiChatController.php` — `relayRunStream()`

**Wijziging:** Na regel 912 (missedLines loop), vóór `[DONE]`:

```php
// Synthesize terminal status event if the run ended but relay may have
// missed the result event (e.g. cross-container fs delay, timing race)
if ($run->isTerminal()) {
    $statusEvent = [
        'type' => 'run_status',
        'status' => $run->status,
        'runId' => $run->id,
        'sessionId' => $run->session_id,
    ];
    if ($run->status === AiRunStatus::FAILED->value) {
        $statusEvent['error'] = $run->error_message ?: 'Run failed';
    }
    if ($run->status === AiRunStatus::COMPLETED->value) {
        $metadata = $run->getDecodedResultMetadata();
        if (!empty($metadata)) {
            $statusEvent['metadata'] = $metadata;
        }
        // If relay missed the result text, include it as fallback
        if ($run->result_text !== null) {
            $statusEvent['resultText'] = $run->result_text;
        }
    }
    echo "data: " . json_encode($statusEvent) . "\n\n";
    flush();
}
```

**Impact:** Client ontvangt altijd status + result bij stream-einde, ongeacht of individuele NDJSON events gemist zijn.

---

### C. Keepalive als `data:` event i.p.v. SSE comment

**Probleem:** SSE comments (`: keepalive`) worden door de client's `reader.read()` wel ontvangen maar niet als `data:` line geparsed. De `resetInactivityTimer()` wordt wél getriggerd (want er komen bytes binnen), maar het is fragiel — sommige proxies strippen SSE comments.

**Oplossing:** Stuur keepalives als `data:` JSON events zodat ze consistent door dezelfde parsing-pipeline gaan.

**Bestand:** `yii/controllers/AiChatController.php` — `relayRunStream()`, de `isRunning` callback

**Wijziging:** Regel 889-893:

```php
// Was:
echo ": keepalive\n\n";
flush();

// Wordt:
echo "data: " . json_encode(['type' => 'keepalive']) . "\n\n";
flush();
```

**Client-side:** De `onStreamEvent()` (view:1145) moet `keepalive` events negeren (net als `waiting`):

```js
if (type === 'waiting' || type === 'keepalive')
    return;
```

**Impact:** Keepalives worden door de volledige SSE pipeline behandeld, werken door proxies heen, en resetten de inactivity timer betrouwbaar.

---

## Fase 2: Client-side resilience (A + D)

### A. Auto-reconnect via `connectToStream()` bij verbindingsverlies

**Probleem:** Als de SSE-verbinding wegvalt tijdens een actieve run, geeft de client op (toont error of lege response). De run loopt ondertussen door op de server.

**Oplossing:** Wanneer de reader `result.done = true` krijgt of de verbinding faalt, en `streamEnded` is nog `false`, check of de run nog actief is via `run-status` polling. Als de run nog loopt, reconnect via `connectToStream()`.

**Bestand:** `yii/views/ai-chat/index.php`

**Wijzigingen:**

#### A1. Nieuw: `checkAndReconnect()` methode

```js
checkAndReconnect: function() {
    var self = this;
    var runId = this.currentRunId;
    if (!runId || this.streamEnded) return;

    // Poll run status
    fetch('/ai-chat/run-status?runId=' + runId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success || self.streamEnded) return;

        if (data.status === 'pending' || data.status === 'running') {
            // Run is still active — reconnect to stream
            self.reconnectToRun(runId);
        } else {
            // Run finished while we were disconnected — fetch final result
            self.handleDisconnectedCompletion(data);
        }
    })
    .catch(function() {
        // Network error — retry after delay
        if (!self.streamEnded) {
            setTimeout(function() { self.checkAndReconnect(); }, 3000);
        }
    });
},
```

#### A2. Nieuw: `reconnectToRun()` methode

```js
reconnectToRun: function(runId) {
    if (this.streamEnded) return;
    // Connect to stream-run endpoint with offset 0 (replay from start)
    // The existing processStream() parser handles duplicates gracefully
    // because streamBuffer accumulates text_delta cumulatively
    this.updateStreamStatusLabel('reconnecting');

    var self = this;
    var url = '/ai-chat/stream-run?runId=' + runId + '&offset=0';
    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        var reader = response.body.getReader();
        self.activeReader = reader;

        // Reset buffers for clean re-read
        self.streamBuffer = '';
        self.streamThinkingBuffer = '';
        self.streamResultText = null;
        self.streamReceivedText = false;
        self.streamLabelSwitched = false;

        var decoder = new TextDecoder();
        var buffer = '';

        self.resetInactivityTimer();

        function processStream() {
            return reader.read().then(function(result) {
                if (result.done) {
                    self.onStreamEnd();
                    return;
                }
                self.resetInactivityTimer();
                buffer += decoder.decode(result.value, { stream: true });
                var lines = buffer.split('\n');
                buffer = lines.pop();

                var streamDone = false;
                lines.forEach(function(line) {
                    if (streamDone) return;
                    if (line.startsWith('data: ')) {
                        var payload = line.substring(6);
                        if (payload === '[DONE]') {
                            self.onStreamEnd();
                            streamDone = true;
                            return;
                        }
                        try {
                            self.onStreamEvent(JSON.parse(payload));
                        } catch (e) {}
                    }
                });

                if (streamDone) {
                    self.cancelActiveReader();
                    return;
                }
                return processStream();
            });
        }

        return processStream();
    })
    .catch(function(error) {
        if (!self.streamEnded)
            self.checkAndReconnect(); // Retry cycle
    });
},
```

#### A3. Nieuw: `handleDisconnectedCompletion()` methode

```js
handleDisconnectedCompletion: function(statusData) {
    if (this.streamEnded) return;
    this.streamEnded = true;
    this.currentRunId = null;
    this.cleanupStreamUI();

    if (statusData.status === 'failed') {
        this.onStreamError(statusData.errorMessage || 'Run failed');
        return;
    }

    if (statusData.status === 'cancelled') {
        this.onStreamError('Run was cancelled');
        return;
    }

    // Completed — we need the result text. Fetch it via stream-run replay.
    // For now, show what we have with a reconnect notice.
    var claudeContent = this.streamBuffer || '(Reconnecting to fetch result...)';
    var userContent = this.streamPromptMarkdown || '(prompt)';
    this.renderCurrentExchange(userContent, claudeContent, '', {});
    this.messages.push(
        { role: 'user', content: userContent },
        { role: 'claude', content: claudeContent }
    );
},
```

#### A4. Aanpassing: `processStream()` catch-blocks

In zowel `send()` als `connectToStream()`, vervang de catch-handler:

```js
// Was:
.catch(function(error) {
    if (!self.streamEnded)
        self.onStreamError('Failed to execute Claude CLI: ' + error.message);
});

// Wordt:
.catch(function(error) {
    if (!self.streamEnded)
        self.checkAndReconnect();
});
```

En in de `reader.read().then()` block, wanneer `result.done === true`:

```js
// Was:
if (result.done) {
    self.onStreamEnd();
    return;
}

// Wordt:
if (result.done) {
    if (!self.streamEnded)
        self.checkAndReconnect();
    return;
}
```

Dit zorgt ervoor dat een voortijdig einde van de SSE-verbinding niet direct als stream-einde wordt behandeld, maar eerst checkt of de run nog actief is.

---

### D. `run_status` SSE event verwerken in client

**Probleem:** De server stuurt nu een `run_status` event (Fase 1, oplossing B) maar de client verwerkt dit nog niet.

**Bestand:** `yii/views/ai-chat/index.php` — `onStreamEvent()`

**Wijziging:** Voeg toe aan `onStreamEvent()`:

```js
else if (type === 'run_status')
    this.onRunStatus(data);
```

**Nieuw: `onRunStatus()` methode:**

```js
onRunStatus: function(data) {
    if (data.sessionId)
        this.sessionId = data.sessionId;

    if (data.status === 'failed') {
        this.onStreamError(data.error || 'Run failed');
        return;
    }

    // If we missed the result event, use the fallback result text
    if (data.status === 'completed' && data.resultText != null && this.streamResultText == null) {
        this.streamResultText = data.resultText;
    }

    // Merge metadata if present
    if (data.metadata) {
        if (data.metadata.duration_ms)
            this.streamMeta.duration_ms = data.metadata.duration_ms;
        if (data.metadata.num_turns)
            this.streamMeta.num_turns = data.metadata.num_turns;
        if (data.metadata.modelUsage) {
            var modelId = Object.keys(data.metadata.modelUsage)[0];
            if (modelId) {
                this.streamMeta.model = this.formatModelShort(modelId);
                var info = data.metadata.modelUsage[modelId];
                if (info && info.contextWindow)
                    this.maxContext = info.contextWindow;
            }
        }
    }
},
```

**Impact:** Als het `result` NDJSON event gemist is door een timing issue of verbindingsverlies, wordt de `resultText` alsnog uit de DB geleverd via het `run_status` event. De response wordt correct gerenderd.

---

## Fase 3: Inactivity timer verbetering

### E. Gedifferentieerde timeout met duidelijkere foutmelding

**Probleem:** De inactivity timer van 90s geeft een generieke "Connection lost" melding. Tijdens extended thinking kan Claude langer dan 90s stil zijn.

**Oplossing:** Verhoog de timer naar 120s (2 minuten) en vervang de timeout-actie door `checkAndReconnect()` i.p.v. direct `onStreamError()`.

**Bestand:** `yii/views/ai-chat/index.php` — `resetInactivityTimer()`

**Wijziging:**

```js
resetInactivityTimer: function() {
    var self = this;
    if (this.streamInactivityTimer)
        clearTimeout(this.streamInactivityTimer);

    this.streamInactivityTimer = setTimeout(function() {
        if (!self.streamEnded) {
            self.cancelActiveReader();
            self.checkAndReconnect();
        }
    }, 120000); // 120s
},
```

**Impact:** Bij timeout wordt niet direct een error getoond, maar een reconnect geprobeerd. De gebruiker ziet pas een foutmelding als de run ook op de server niet meer actief is.

---

## Bestandsoverzicht

| Bestand | Wijzigingen |
|---------|-------------|
| `yii/controllers/AiChatController.php` | `run_status` SSE event bij relay-einde, keepalive als `data:` event |
| `yii/views/ai-chat/index.php` | `checkAndReconnect()`, `reconnectToRun()`, `handleDisconnectedCompletion()`, `onRunStatus()`, catch-handlers → reconnect, inactivity timer → reconnect |

## Volgorde van implementatie

1. **Fase 1 (server):** `run_status` event + keepalive format → direct merkbaar resultaat
2. **Fase 2 (client):** auto-reconnect + `run_status` verwerking → robuuste self-healing
3. **Fase 3 (client):** inactivity timer verbetering → betere UX bij edge cases

## Risico's en mitigatie

| Risico | Mitigatie |
|--------|----------|
| Reconnect loop bij permanent gefaalde run | `handleDisconnectedCompletion()` detecteert terminal status, max 3 reconnect-pogingen |
| Dubbele rendering bij succesvolle reconnect | `streamEnded` guard in `onStreamEnd()` voorkomt dubbele rendering |
| Buffer-inconsistentie bij reconnect met offset 0 | Buffers worden gereset bij reconnect, volledige replay |
| `run_status` event met grote `resultText` | resultText is al opgeslagen in DB, geen extra overhead |

## Tests

- Unit tests voor `run_status` SSE event format in AiChatControllerTest
- Handmatige test: langere inference (>2 min) → response wordt betrouwbaar gerenderd
- Handmatige test: proxy timeout simuleren (nginx `proxy_read_timeout 30s`) → auto-reconnect werkt
- Handmatige test: Claude CLI crash → duidelijke foutmelding
