# Streaming Resilience — Root Cause Analysis

## Architectuur-overzicht

```
Browser JS ──fetch()──► AiChatController::actionStream()
                              │
                              ├── createRun() + queue push
                              │
                              └── relayRunStream()
                                    │
                                    ├── wacht op stream file (max 10s)
                                    │
                                    └── AiStreamRelayService::relay()
                                          │
                                          ├── leest NDJSON uit bestand
                                          ├── stuurt SSE events naar browser
                                          └── maxWaitSeconds = 3600

Queue worker (RunAiJob)
    │
    └── ClaudeCliProvider::executeStreaming()
          │
          ├── proc_open(claude CLI)
          ├── stream_set_timeout($pipes[1], 30)
          └── onLine callback → schrijft naar NDJSON bestand
```

## Probleem 1: "Claude responding..." blijft hangen bij langere inference

### Oorzaak: SSE-verbinding verbroken door proxy/browser timeout — geen reconnect

**Scenario:**
1. Browser opent fetch() stream naar `/ai-chat/stream`
2. Controller maakt AiRun + pushed job naar queue, begint relay
3. Bij langere inference (>60-90s extended thinking) zijn er periodes zonder text_delta events
4. Keepalive comments (`: keepalive\n\n`) worden elke 15s gestuurd door de relay
5. **Maar**: als een reverse proxy (nginx) of load balancer een eigen `proxy_read_timeout` heeft die korter is dan de inference, wordt de SSE-verbinding server-side verbroken
6. De browser `reader.read()` returned `result.done = true` (of de promise rejects)
7. `onStreamEnd()` wordt aangeroepen — maar **er is geen `result` event ontvangen** (want de inference loopt nog)
8. Gevolg: `streamResultText` is `null`, `streamBuffer` is `''` of bevat alleen thinking-content → toont "(No output)" of lege response

**Bewijs in code:**
- `relayRunStream()` (controller:873-899): relay loopt max `RUN_TIMEOUT` (3600s), stuurt keepalive elke 15s
- `AiStreamRelayService::relay()` (service:47): poll-loop met `maxWaitSeconds`
- Client JS (view:942-976): `processStream()` recursive reader.read() — als `result.done` is true, roept direct `onStreamEnd()` aan
- Client JS (view:1282-1296): inactivity timer 90s — maar alleen als er **geen data** binnenkomt. Keepalives resetten de timer niet (ze komen als SSE comments, niet als `data:` lines)

**Kritiek punt:** De client heeft **geen reconnect-logica** wanneer de SSE-verbinding wegvalt tijdens een actieve run. De `connectToStream()` methode (view:580) bestaat maar wordt alleen aangeroepen voor pagina-herlaad of session-restore, niet automatisch bij verbindingsverlies.

### Aanvullende factor: keepalive comments resetten inactivity timer niet

De keepalive (`: keepalive\n\n`) is een SSE comment. De client parsed alleen regels die starten met `data: ` (view:956). De keepalive-bytes komen wel binnen via `reader.read()` en resetten de `resetInactivityTimer()` (view:948), maar als de **proxy de verbinding heeft verbroken**, komen ze niet meer aan.

## Probleem 2: Geen gerenderde response, thinking process wel zichtbaar

### Oorzaak A: `result` event niet ontvangen — stream voortijdig beëindigd

**Scenario:**
1. Claude CLI inference voltooid — `text_delta` events zijn gestreamd (thinking + response)
2. Het `result` NDJSON event (met `type: "result"`) is het **laatste** event van Claude CLI
3. Als de SSE-verbinding net vóór het `result` event verbreekt:
   - `streamResultText` blijft `null`
   - `streamBuffer` bevat de text_delta output (= de volledige response in text)
   - `onStreamEnd()` valt in de `else`-tak (view:1354): `claudeContent = this.streamBuffer`
   - Dit **zou** correct moeten renderen...

**Maar de DB-fallback mist het result event:**
- Controller (view:901-912): na relay stuurt missedLines uit `$run->stream_log`
- Het `result` event kan in `stream_log` staan maar **niet in het NDJSON bestand** als de queue worker nog niet klaar was met schrijven
- Of het `result` event staat wél in het NDJSON bestand, maar de relay was al gestopt

### Oorzaak B: Multi-turn inference met intermediate tool_use blocks

**Scenario:**
1. Claude doet meerdere turns (tool_use → tool_result → text)
2. Elke `assistant` message met `type: "assistant"` triggert `onStreamAssistant()` (view:1219)
3. De text_delta events worden cumulatief in `streamBuffer` opgebouwd
4. Maar: als de **laatste turn** alleen tool_use output heeft (geen text), is `streamBuffer` leeg voor die turn
5. Het `result` event bevat de canonieke finale tekst — als dat gemist wordt, is de response leeg

### Oorzaak C: `[DONE]` marker timing

De `[DONE]` marker wordt geschreven in het `finally`-block van RunAiJob (job:122-129), **na** `fclose($streamFile)`. Er is een race condition:
1. RunAiJob schrijft laatste NDJSON line, sluit stream file
2. RunAiJob markeert run als completed (`markCompleted`)
3. RunAiJob opent bestand opnieuw, schrijft `[DONE]`
4. **Tussen stap 1 en 3**: relay's `isRunning()` check ziet `isActive() === false`, stopt relay
5. Relay leest "remaining data" (service:74-82) maar `[DONE]` is nog niet geschreven
6. Controller's DB-fallback (controller:901-912) stuurt missedLines
7. Controller stuurt `data: [DONE]\n\n` naar client
8. Dit zou moeten werken, **mits** de SSE-verbinding nog open is

## Probleem 3: Inference stopt zonder duidelijke foutmelding

### Oorzaak A: Claude CLI niet-nul exit code zonder stderr

**Scenario:**
1. Claude CLI kan crashen of timeout-en met exit code != 0
2. RunAiJob (job:110): `$result['error'] ?: 'Claude CLI exited with code ' . $result['exitCode']`
3. `markFailed()` wordt aangeroepen — run krijgt status `failed`
4. Relay's `isRunning()` check ziet `isActive() === false`, stopt
5. Controller stuurt DB-fallback + `[DONE]`
6. **Maar**: er komt geen `server_error` SSE event — alleen `[DONE]`
7. Client's `onStreamEnd()` wordt aangeroepen met incomplete data
8. De foutmelding staat in `$run->error_message` maar wordt **niet naar de client gestuurd**

### Oorzaak B: Inactivity timer (90s) bij extended thinking

**Scenario:**
1. Claude CLI denkt langer dan 30s (stream_set_timeout)
2. `fgets()` returned false met `timed_out = true`
3. `$onLine('')` wordt aangeroepen → RunAiJob schrijft niets naar bestand (lege string check, job:62)
4. Relay ziet geen nieuwe data → stuurt keepalive comment
5. Client ontvangt keepalive → `resetInactivityTimer()` wordt gecalled via `reader.read()` (view:948)
6. **Maar**: als de keepalive niet doorkomt (proxy timeout), telt de 90s inactivity timer door
7. Na 90s: `onStreamError('Connection lost — no data received for 90 seconds.')`
8. De foutmelding is vaag — gebruiker ziet niet dat de inference nog loopt

### Oorzaak C: Queue worker crash/restart

Als de queue worker onverwacht stopt (OOM, proces kill):
1. Run blijft in status `running` met verouderde `updated_at`
2. Er wordt geen `[DONE]` naar het bestand geschreven
3. Relay's `isRunning()` check ziet `isActive() === true` → blijft wachten
4. Uiteindelijk: relay's `maxWaitSeconds` (3600s) verloopt → relay stopt
5. Controller stuurt `[DONE]` → client's `onStreamEnd()` → incomplete/lege response
6. De stale-run cleanup service (AiRunCleanupService) zou dit moeten opvangen, maar die draait op een cron-interval

## Samenvatting oorzaken

| Probleem | Primaire oorzaak | Secundaire oorzaken |
|----------|-----------------|---------------------|
| 1. Hangt in "responding" | Geen auto-reconnect bij SSE-verbindingsverlies | Proxy timeouts, keepalive niet geparsed als data |
| 2. Geen gerenderde response | `result` event gemist bij verbindingsverlies | Multi-turn met lege laatste turn, timing race |
| 3. Stopt zonder foutmelding | Failed run status niet als SSE error verstuurd | Inactivity timer vaag, queue worker crash |
