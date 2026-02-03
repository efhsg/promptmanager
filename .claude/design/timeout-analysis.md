# Claude CLI Timeout Analyse

## Probleem

Bij grotere prompts treedt een timeout op: `Command timed out after 300 seconds`. Dit blokkeert complexe requests die meer dan 5 minuten nodig hebben.

---

## Huidige Timeout Chain

Er zijn **vier lagen** die elk op 300 seconden staan. Als één van deze eerder afloopt dan de Claude CLI klaar is, wordt het request afgekapt:

```
Browser (fetch)
  ↓
Nginx (fastcgi_read_timeout = 300s)           ← laag 1
  ↓
PHP-FPM (max_execution_time = 300s)           ← laag 2
  ↓
ClaudeCliService (timeout param = 300s)       ← laag 3
  ↓
stream_set_timeout (30s per-read)             ← laag 4 (streaming only)
  ↓
Claude CLI process (proc_open)
```

### Bronbestanden

| Laag | Instelling | Waarde | Bestand | Regel |
|------|-----------|--------|---------|-------|
| Nginx proxy | `proxy_read_timeout` | 300s | `docker/nginx.conf.template` | 14 |
| Nginx FastCGI | `fastcgi_read_timeout` | 300s | `docker/nginx.conf.template` | 39 |
| Nginx FastCGI | `fastcgi_send_timeout` | 300s | `docker/nginx.conf.template` | 38 |
| PHP | `max_execution_time` | 300s | `docker/yii/Dockerfile` | 81 |
| Service (blocking) | `$timeout` param | 300s | `yii/services/ClaudeCliService.php` | 62 |
| Service (streaming) | `$timeout` param | 300s | `yii/services/ClaudeCliService.php` | 183 |
| Controller (run) | hardcoded | 300 | `yii/controllers/ScratchPadController.php` | 455 |
| Controller (stream) | hardcoded | 300 | `yii/controllers/ScratchPadController.php` | 520 |
| Controller (summarize) | hardcoded | 120 | `yii/controllers/ScratchPadController.php` | 680 |
| PID cache TTL | cache set | 600s | `yii/services/ClaudeCliService.php` | 304 |

### Streaming vs blocking

- **`actionRunClaude()`** — blocking: PHP wacht tot Claude klaar is, stuurt daarna JSON terug. Timeout is fataal: geen partial output.
- **`actionStreamClaude()`** — SSE streaming: elke regel stdout wordt direct naar de browser geflushed. Timeout is ook fataal, maar de gebruiker ziet wél partial output tot dat moment. **Dit is het pad dat standaard wordt gebruikt.**

---

## Optie 1: Timeout verhogen naar 3000 seconden

### Wat moet er veranderen

**Alle vier lagen moeten tegelijk worden aangepast.** Als je er één mist, breekt de keten op het laagste punt.

| Bestand | Wijziging |
|---------|-----------|
| `docker/nginx.conf.template` | `proxy_read_timeout 3000;` (r14) |
| `docker/nginx.conf.template` | `fastcgi_send_timeout 3000;` (r38) |
| `docker/nginx.conf.template` | `fastcgi_read_timeout 3000;` (r39) |
| `docker/yii/Dockerfile` | `max_execution_time=3000` (r81) |
| `yii/controllers/ScratchPadController.php` | `actionRunClaude`: timeout param → 3000 (r455) |
| `yii/controllers/ScratchPadController.php` | `actionStreamClaude`: timeout param → 3000 (r520) |
| `yii/services/ClaudeCliService.php` | PID cache TTL → 3600 (r304), zodat cancel werkt gedurende de hele runtime |

Daarna: `docker compose down && docker compose up -d --build` (rebuild nodig voor Dockerfile-wijziging).

### Consequenties

**Positief:**
- Simpele fix: alleen configuratiewijzigingen, geen architectuurverandering
- Backward compatible: korte requests worden niet beïnvloed
- Streaming vermindert het probleem al: gebruiker ziet output terwijl hij wacht

**Negatief:**
- **PHP worker bezet voor 50 minuten**: `max_execution_time=3000` betekent dat één PHP-FPM worker tot 50 minuten vastgehouden wordt per request. Bij default 5 workers (pm.max_children) kan dit de applicatie blokkeren voor andere gebruikers.
- **Nginx connection open**: elke open SSE-connectie houdt een nginx worker_connection bezet. Bij `worker_connections 1024` is dit op zichzelf geen probleem, maar in combinatie met PHP-FPM worker exhaustion wel.
- **Geheugengebruik**: PHP's geheugengebruik groeit nauwelijks bij streaming (elke regel wordt direct geflushed), maar de Claude CLI zelf kan veel geheugen gebruiken bij grote contexten.
- **Geen granulariteit**: alle requests krijgen dezelfde maximale timeout, ook als ze maar 10 seconden nodig hebben. De timeout is alleen een *maximum*, dus korte requests worden niet vertraagd — maar een vastgelopen process kan wel 50 minuten een worker bezet houden.
- **Gebruikerservaring bij echte timeout**: als het na 50 minuten alsnog timeout, heeft de gebruiker zeer lang gewacht. Met streaming is dit minder erg (er was tussentijds output), maar bij blocking (`actionRunClaude`) is het 50 minuten staren naar een spinner.
- **Geen impact op Claude CLI zelf**: de Claude CLI heeft geen eigen timeout. Het is PHP dat het process killed. De CLI draait zo lang als nodig, dus verhogen werkt hier correct.

### Risicobeoordeling

**Laag risico** voor een single-user applicatie. PromptManager is (momenteel) een enkele-gebruiker tool. De kans op worker exhaustion door gelijktijdige langlopende requests is klein.

**Hoger risico** bij multi-user deployment: meerdere gelijktijdige 50-minuten requests kunnen alle PHP-FPM workers bezet houden.

### Aanbeveling bij deze optie

Verhoog niet blindelings alles naar 3000. Kies een gelaagde aanpak:

```
Nginx timeouts:  3600s  (ruimste, vangt alles op)
PHP max_exec:    3000s  (iets strakker)
Service param:   3000s  (match met PHP)
PID cache TTL:   3600s  (ruimer voor cancel-functionaliteit)
```

Zo is altijd de **service-laag** de eerste die timeout, niet nginx of PHP — waardoor de foutmelding informatief is ("Command timed out after 3000 seconds") in plaats van een generieke 504 Gateway Timeout.

---

## Optie 2: Async verwerking (queue-based)

### Concept

In plaats van het HTTP-request open te houden totdat Claude klaar is, wordt het werk naar een achtergrondproces gedelegeerd. Het request keert direct terug en de frontend pollt of luistert naar updates.

```
Browser                    PHP Controller              Queue Worker
  |                            |                           |
  |-- POST /run-claude ------->|                           |
  |                            |-- push job to queue ----->|
  |<-- 202 {jobId: "abc"} ----|                           |
  |                            |                           |-- proc_open(claude) -->
  |                            |                           |      ... loopt ...
  |-- GET /job-status/abc ---->|                           |      ... loopt ...
  |<-- {status: running} ------|                           |
  |                            |                           |
  |-- GET /job-status/abc ---->|                           |<-- stdout complete
  |<-- {status: done, ...} ---|                           |
```

### Architectuur-opties

**A. Yii2 Queue + Worker (yii2-queue)**

```
Componenten:
- yii2-queue extensie met DB of Redis driver
- ClaudeJob class (yii\queue\JobInterface)
- Queue worker process (yii queue/listen)
- Job status opslag (DB tabel of cache)
- Status polling endpoint of SSE via job status
```

Pro: past in Yii2 ecosysteem, hergebruikt bestaande DB/cache.
Con: vereist een aparte worker process, extra infra.

**B. Dedicated background process (simpeler)**

```
Componenten:
- Controller start proc_open() met nohup/setsid
- PID + output file opgeslagen in cache/DB
- Polling endpoint leest output file
- Cleanup na voltooiing
```

Pro: geen extra dependencies, werkt met bestaande proc_open code.
Con: fragiel (process management op OS-niveau), geen retry, geen queue.

**C. SSE met langere timeout (hybride — huidige aanpak + hogere limits)**

Dit is eigenlijk optie 1 + de bestaande streaming. De huidige `actionStreamClaude()` ís al een async-achtig patroon: de browser ontvangt data terwijl het process loopt, en kan op elk moment de verbinding verbreken (cancel). Het enige dat ontbreekt is een hogere timeout.

### Kan async de timeout volledig omzeilen?

**Ja, maar met trade-offs:**

| Aspect | Sync (huidig) | Async (queue) |
|--------|--------------|---------------|
| HTTP timeout relevant? | Ja — request moet open blijven | Nee — request retourneert direct |
| PHP max_execution_time | Moet hoog genoeg zijn | Niet relevant (worker draait apart) |
| Nginx timeout | Moet hoog genoeg zijn | Niet relevant (kort request) |
| Cancel support | Via PID in cache | Via queue job cancel |
| Streaming output | Direct via SSE | Via polling of WebSocket |
| Complexiteit | Laag (huidig) | Hoog (nieuwe infra) |
| Foutafhandeling | Direct in HTTP response | Uitgesteld, apart mechanisme nodig |
| Session continuity | Werkt nu | Moet session_id doorgeven via job |

### Consequenties van async

**Positief:**
- Timeouts worden irrelevant: het HTTP-request is binnen milliseconden terug
- PHP-FPM workers worden niet geblokkeerd
- Meerdere Claude-requests kunnen parallel draaien
- Schaalbaar voor multi-user scenarios

**Negatief:**
- **Significante architectuurwijziging**: nieuw queue-systeem, worker process, job status tracking, polling/WebSocket endpoint
- **Verlies van realtime streaming**: de huidige SSE-implementatie levert direct karakter-voor-karakter output. Bij een queue-based systeem moet je kiezen:
  - Polling (elke N seconden status ophalen) — verliest de streaming UX
  - WebSocket — extra infra, maar behoudt realtime karakter
  - Server output naar bestand schrijven + SSE die het bestand tailt — complexe maar werkbare hybride
- **Operationele overhead**: queue worker moet draaien, gemonitord worden, herstarten bij crashes
- **Docker complexiteit**: extra container of process in bestaande container
- **Foutafhandeling**: asynchrone fouten moeten apart worden opgehaald en getoond
- **Testcomplexiteit**: integration tests moeten nu rekening houden met asynchrone flows

### Risicobeoordeling

**Hoog risico / hoge effort** — dit is een architectuurverandering die raakt aan de controller, service, frontend JavaScript, en Docker-setup. De huidige SSE-streaming werkt goed en biedt al een async-achtige ervaring voor de gebruiker.

---

## Vergelijking

| Criterium | Optie 1: Timeout → 3000s | Optie 2: Async queue |
|-----------|--------------------------|---------------------|
| **Implementatie-effort** | ~30 min (config wijzigingen) | Dagen (nieuwe architectuur) |
| **Codewijzigingen** | 0 PHP-logica | Nieuw queue systeem, jobs, endpoints |
| **Risico** | Laag (config-only) | Hoog (nieuwe infra + flows) |
| **Lost het probleem op?** | Ja, voor requests tot ~50 min | Ja, onbeperkt |
| **Streaming UX behouden?** | Ja, ongewijzigd | Nee, tenzij extra WebSocket/tail |
| **Operationele impact** | Minimaal | Queue worker monitoring nodig |
| **Schaalbaarheid** | Beperkt door PHP-FPM workers | Uitstekend |
| **Rollback** | Triviaal (config terugzetten) | Complex |

---

## Aanbeveling

### Fase 1 — Nu: Optie 1 (timeout verhogen)

Verhoog de timeout naar 3000 seconden in alle lagen. Dit lost het directe probleem op met minimaal risico. De bestaande SSE-streaming zorgt ervoor dat:
- De gebruiker direct output ziet (geen 50 min wachten op een spinner)
- Het request geannuleerd kan worden via de bestaande cancel-knop
- PHP-FPM worker bezetting beheersbaar is bij single-user gebruik

### Fase 2 — Later, als nodig: Optie 2 (async)

Overweeg async alleen als:
- Meerdere gebruikers gelijktijdig langlopende Claude-requests draaien
- PHP-FPM worker exhaustion een echt probleem wordt
- Requests structureel langer dan 50 minuten duren

De huidige SSE-streaming is feitelijk al een "best of both worlds" — het gedraagt zich async vanuit gebruikersperspectief (directe output, cancellable) maar is technisch sync (één HTTP-connectie). De enige beperking is de geconfigureerde timeout, en die is eenvoudig te verhogen.

---

## Wijzigingsoverzicht (Fase 1)

```
docker/nginx.conf.template         → proxy/fastcgi timeouts naar 3600s
docker/yii/Dockerfile              → max_execution_time=3000
yii/controllers/ScratchPadController.php → timeout params naar 3000 (r455, r520)
yii/services/ClaudeCliService.php   → PID cache TTL naar 3600 (r304)
```

Na wijzigingen: `docker compose down && docker compose up -d --build`
