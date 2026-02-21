# Probleem: Tests draaien in een worktree

## Context

We gebruiken de worktree feature om nieuwe features te ontwikkelen in een geïsoleerde git worktree.
`WorktreeService::create()` maakt worktrees aan op `{root_directory}-{suffix}` — een sibling
directory naast de main repo.

Voorbeeld: project root `/projects/promptmanager` → worktree `/projects/promptmanager-feature-x`.

## Probleem

**De worktree is niet test-ready.** Tests draaien niet out-of-the-box omdat er drie categorieën
ontbreken: dependencies, environment, en container-toegang.

---

## 1. Geen `vendor/`

`yii/vendor/` staat in `.gitignore`. Git worktrees bevatten alleen tracked files.

De worktree heeft dus:
- Geen `vendor/bin/codecept` (test runner)
- Geen `vendor/autoload.php` (class autoloading)
- Geen Yii2 framework, geen Codeception, geen enkele dependency

**Impact:** Niets kan draaien. Zelfs `php yii` werkt niet.

**Referentie:** `yii/.gitignore:18` (`/vendor`)

## 2. Geen `.env`

`.env` staat in de root `.gitignore`. De worktree heeft geen environment variables.

De test database config (`yii/config/test_db.php`) leest:
- `DB_HOST` — database server
- `DB_APP_PORT` — poort
- `DB_DATABASE_TEST` — schema naam
- `DB_USER` / `DB_PASSWORD` — credentials

Zonder `.env` zijn al deze waarden leeg. De database connectie faalt.

**Bijkomend:** Andere env-afhankelijke config (`PATH_MAPPINGS`, `INCLUDED_TEST_MODULES`,
`YTX_PYTHON_PATH`, etc.) is ook weg.

**Referentie:** `.gitignore:6` (`.env`), `yii/config/test_db.php:4-9`

## 3. Docker container ziet de worktree niet

De Docker volume mount in `docker-compose.yml:29` is:
```yaml
volumes:
  - .:/var/www/html
```

Dit mount alleen de main repo directory naar `/var/www/html`. Een worktree die als sibling
is aangemaakt (bijv. `/projects/promptmanager-feature-x` op de host) bestaat **niet** in de
container, tenzij die toevallig onder een al-gemounte volume valt.

**Scenario's:**

| Project root (host) | Worktree (host) | In container? |
|---------------------|-----------------|---------------|
| `/home/esg/projects/promptmanager` | `/home/esg/projects/promptmanager-feat` | Ja, via `/projects/promptmanager-feat` (PROJECTS_ROOT mount) |
| `/var/www/html` (is de container zelf) | `/var/www/html-feat` | Nee — buiten de volume mount |

Als PromptManager zichzelf als project beheert met root `/var/www/html`, valt de worktree
buiten elke volume mount.

**Referentie:** `docker-compose.yml:29`, `docker-compose.yml:31`

## 4. Nginx/PHP-FPM wijst naar vaste paden

Zelfs als de worktree wél in de container beschikbaar is:

- **Nginx** serveert alleen `/var/www/html/yii/web/` (via `docker/nginx.conf.template`)
- **PHP-FPM** working directory is `/var/www/html/yii` (`docker-compose.yml:12`)
- **Yii basePath** resolvet via `dirname(__DIR__)` in `main.php:30` — relatief, maar gebonden
  aan welke `main.php` geladen wordt

De webapplicatie draait altijd vanuit de main repo. Je kunt de worktree-code niet via de
browser testen.

## 5. Gedeeld test schema

Alle tests gebruiken dezelfde `yii_test` database (één MySQL instance, één schema).
`test_db.php` leest `DB_DATABASE_TEST` — standaard `promptmanager_test`.

Als je tests tegelijk draait in de main repo én een worktree, delen ze dezelfde test database.
Fixtures en teardown van de ene run kunnen de andere beïnvloeden.

Dit is alleen relevant als je parallel wilt testen.

---

## Samenvatting

| # | Probleem | Blokkerend? |
|---|----------|-------------|
| 1 | Geen `vendor/` | Ja — niets draait |
| 2 | Geen `.env` | Ja — geen DB connectie |
| 3 | Container ziet worktree niet | Hangt af van project root locatie |
| 4 | Nginx/PHP-FPM vaste paden | Ja, voor browser/functionele tests |
| 5 | Gedeeld test schema | Nee, tenzij parallel testen |

## Wat wél werkt

Alle interne paden in Codeception, bootstrap, en Yii config zijn **relatief** (`__DIR__`,
`dirname(__DIR__)`, relatieve paths in `codeception.yml`). Als de blokkers opgelost zijn,
hoeft er niets aan de testcode zelf te veranderen.

---

## Docker refactor: hardcoded paden inventarisatie

Als we de Docker setup willen refactoren zodat meerdere worktrees ondersteund worden,
moeten we weten waar `/var/www/html` hardcoded is en wat de aard van elke koppeling is.

### docker-compose.yml — 7 referenties

| Regel | Code | Aard |
|-------|------|------|
| 12 | `working_dir: /var/www/html/yii` | WORKDIR pma_yii |
| 15 | `PATH: .../var/www/html/yii:...` | PATH env var pma_yii |
| 29 | `- .:/var/www/html` | Volume mount pma_yii |
| 51 | `- .:/var/www/html` | Volume mount pma_nginx |
| 95 | `working_dir: /var/www/html/yii` | WORKDIR pma_queue |
| 101 | `- .:/var/www/html` | Volume mount pma_queue |
| 118 | `PATH: .../var/www/html/yii:...` | PATH env var pma_queue |

### docker/yii/Dockerfile — 5 referenties

| Regel | Code | Aard |
|-------|------|------|
| 81 | `mkdir -p /var/www/html/yii/runtime/logs` | Directory aanmaken |
| 84 | `error_log=/var/www/html/yii/runtime/logs/php_errors.log` | PHP ini config |
| 99 | `chown -R $USER_NAME:$USER_NAME /var/www/html` | Ownership |
| 102 | `ARG WORKDIR=/var/www/html/yii` | Build arg (default) |
| 113 | `mkdir -p /var/www/html/yii/vendor/bin` | PHPStorm helper |

**Opmerking:** Regel 102 definieert `WORKDIR` als build arg. De Dockerfile gebruikt
daarna `WORKDIR $WORKDIR` (regel 105) en `ENV PATH="$WORKDIR:${PATH}"` (regel 108).
Maar regels 81, 84, 99 en 113 negeren die variabele en hardcoden het pad opnieuw.

### docker/nginx.conf.template — 1 referentie

| Regel | Code | Aard |
|-------|------|------|
| 21 | `root /var/www/html/yii/web;` | Nginx document root |

Het template gebruikt al `envsubst` voor `$PHP_FPM_PORT` (docker-compose.yml:57).
Dezelfde techniek kan voor de root path.

### docker/yii/codex-config.toml — 1 referentie

| Regel | Code | Aard |
|-------|------|------|
| 10 | `[projects."/var/www/html"]` | Codex CLI trust path |

### Shell scripts — 3 referenties

| Bestand | Regel | Code | Aard |
|---------|-------|------|------|
| linter-staged.sh | 9 | `sed 's\|^\|/var/www/html/\|'` | Host→container path conversie |
| linter-staged.sh | 10 | `--config /var/www/html/linterConfig.php` | Config pad |
| linter.sh | 6 | `--config /var/www/html/linterConfig.php` | Config pad |

### .env / PATH_MAPPINGS — 1 referentie

PATH_MAPPINGS kan `/var/www/html` bevatten als mapping target. Dit is per-installatie
configureerbaar, geen code-wijziging nodig.

---

### Totaal: 18 hardcoded referenties

| Component | Aantal | Impact |
|-----------|--------|--------|
| docker-compose.yml | 7 | Volume mounts, working dirs, PATH |
| Dockerfile | 5 | Build paths, ownership, PHP config |
| nginx.conf.template | 1 | Document root |
| codex-config.toml | 1 | CLI trust |
| Shell scripts | 3 | Linter pad-conversie |
| .env config | 1 | Path mapping (al configureerbaar) |

### Kern van het probleem

Het pad `/var/www/html` is niet gedefinieerd als **één variabele die overal doorwerkt**.
Het is 18 keer onafhankelijk hardcoded. De Dockerfile heeft een `WORKDIR` build arg
(regel 102) maar gebruikt die slechts op 2 van de 5 plekken.

Een refactor moet deze 18 referenties terugbrengen naar **één bron van waarheid**
(env var of build arg) die overal doorwerkt, zodat een worktree met een ander
mount-punt dezelfde infrastructure kan gebruiken.

---

## Idee: mount parent directory als `/var/www/worktree`

### Het concept

Huidige situatie:
```
Host:       /home/esg/projects/promptmanager/        → Container: /var/www/html/
Worktree:   /home/esg/projects/promptmanager-feat/    → Container: ??? (niet zichtbaar)
```

Voorstel — mount de parent directory:
```
Host:       /home/esg/projects/                       → Container: /var/www/worktree/
Main repo:  /var/www/worktree/main/
Worktree:   /var/www/worktree/feat/
```

Alle worktrees (`{suffix}`) zijn siblings onder hetzelfde mountpoint. Ze zijn
allemaal zichtbaar in de container.

### Wat dit oplost

**Probleem #3 (container ziet worktree niet)** is volledig opgelost. Elke worktree
die WorktreeService aanmaakt als `{root}-{suffix}` valt automatisch onder de mount.

### Wat dit NIET oplost

| Probleem | Status | Waarom |
|----------|--------|--------|
| 1. Geen `vendor/` | **Blijft** | Gitignored, onafhankelijk van mount structuur |
| 2. Geen `.env` | **Blijft** | Gitignored, onafhankelijk van mount structuur |
| 4. Nginx/PHP-FPM vaste paden | **Blijft** | Nginx serveert nog steeds één vaste root |
| 5. Gedeeld test schema | **Blijft** | Zelfde database, onafhankelijk van mount |

### Wat er verandert aan het hoofdpad

Het app-pad verschuift van `/var/www/html` naar `/var/www/worktree/main`. Dit raakt
alle 18 hardcoded referenties. In de refactor worden die toch al variabel gemaakt,
dus de padwijziging is geen extra werk — het is dezelfde refactor.

### Nieuwe vragen

**1. Wat wordt er precies gemount?**

De host parent directory (bijv. `/home/esg/projects/`) bevat potentieel tientallen
andere projecten. Die worden allemaal zichtbaar in de container. Dit is een breder
mount dan nodig.

Alternatief: mount alleen de repo + bekende worktrees, niet de hele parent. Maar
dan moet je elke worktree als aparte volume mount toevoegen, wat dynamische
docker-compose wijzigingen vereist.

**2. Hoe heet de repo directory op de host?**

Het voorstel gaat ervan uit dat de repo directory `html` heet zodat het pad
`/var/www/worktree/main` wordt. In de praktijk heet de directory waarschijnlijk
`promptmanager` of iets dergelijks. Dan wordt het pad `/var/www/worktree/promptmanager`.

Dit maakt het pad installatie-afhankelijk. Twee opties:
- De repo directory daadwerkelijk `html` noemen (conventie afdwingen)
- Het pad configureerbaar maken via env var (bijv. `APP_DIR=html`)

**3. PROJECTS_ROOT overlap**

Er is al een `PROJECTS_ROOT` mount (`docker-compose.yml:31`):
```yaml
- ${PROJECTS_ROOT}:/projects:rw
```

Als de PromptManager repo onder `PROJECTS_ROOT` valt (bijv. `/home/esg/projects/promptmanager`),
dan is de repo al zichtbaar als `/projects/promptmanager` in de container. En worktrees
als `/projects/promptmanager-feat`.

De worktrees zijn in dat geval al zichtbaar — maar niet op het pad waar de app
draait (`/var/www/html`). WorktreeService gebruikt PathService om het host-pad te
vertalen naar het container-pad, dus de service *kan* de worktree vinden via
`/projects/promptmanager-feat`. Maar Codeception, Nginx en PHP-FPM weten niets
van `/projects/`.

**4. Claude Code sessie-pad**

Claude Code start in `/var/www/html` (het huidige working directory). Na de refactor
wordt dat `/var/www/worktree/main`. Alle CLAUDE.md paden, skill-referenties, en
`vendor/bin/codecept` commando's gaan uit van het working directory — die zijn
relatief en werken nog. Maar externe tooling (PHPStorm remote interpreter, Xdebug
path mappings) moet opnieuw geconfigureerd worden.

**5. Dockerfile build context**

De Dockerfile maakt directories aan op `/var/www/html/...` tijdens de build.
Na de refactor is dat `/var/www/worktree/main/...`. Maar de build gebeurt vóór
de volume mount — de directories worden overschreven door de mount. Dit is nu
ook al zo (de Dockerfile maakt `runtime/logs` en `vendor/bin` aan, maar de mount
overschrijft ze). De Dockerfile-regels zijn dus cosmetisch/fallback.

De enige uitzondering is de PHP error log config (`error_log=...`) — die moet
naar het juiste pad wijzen dat NA de mount bestaat.

**6. Twee worktrees tegelijk testen via browser**

Zelfs met het nieuwe mountpoint serveert Nginx nog steeds één `root` pad.
Om worktree-code via de browser te testen moet je ofwel:
- De Nginx root dynamisch wisselen (config change + reload)
- Een tweede Nginx/PHP-FPM stack starten op een andere poort
- Nginx configureren met meerdere server blocks of location prefixes

Dit is niet opgelost door het mountpoint te veranderen. Het is een apart probleem.
