# Gefaseerd ontwerp: worktree-ready applicatie

## Overzicht

| Fase | Scenario | Wat is gescheiden | Wat is gedeeld |
|------|----------|-------------------|----------------|
| 1 | Fundament | Niets (alleen main) | Alles |
| 2 | Bugfix | Source code | vendor, .env, DB, sessions, nginx |
| 3 | Small feature / refactor | Source code, vendor, .env | DB, sessions (cookie-routing) |
| 4 | Big feature / spike | Alles | Niets (poort-routing) |

---

## Fase 1: Worktree-ready fundament

### Doel

De applicatie draait vanuit `/var/www/worktree/html/` in plaats van `/var/www/html/`.
Er komt een directory-niveau tussen. Met alleen branch "main" is het gedrag identiek
aan nu — transparante wijziging.

### Directorystructuur

**Voor:**
```
/var/www/html/              ← mount point, IS de repo root
├── yii/
├── docker/
├── .env
└── ...
```

**Na:**
```
/var/www/worktree/          ← mount point, bevat de repo + toekomstige worktrees
├── html/                   ← main branch (de repo zelf)
│   ├── yii/
│   ├── docker/
│   ├── .env
│   └── ...
├── html-bugfix-123/        ← (fase 2+)
└── html-feat-x/            ← (fase 3+)
```

### Wat wijzigt

**docker-compose.yml:**
- Volume mount: `.:/var/www/html` → `.:${APP_ROOT:-/var/www/worktree/html}`
  plus parent mount voor worktree-zichtbaarheid
- working_dir: `/var/www/html/yii` → `${APP_ROOT:-/var/www/worktree/html}/yii`
- PATH env: idem
- Alle services (pma_yii, pma_queue, pma_nginx)

**Dockerfile:**
- `ARG APP_ROOT=/var/www/worktree/html`
- Alle hardcoded `/var/www/html` → `${APP_ROOT}`
- mkdir, chown, error_log, symlinks

**nginx.conf.template:**
- `root /var/www/html/yii/web` → `root ${APP_ROOT}/yii/web`
- envsubst voor `$APP_ROOT`

**Shell scripts:**
- `linter.sh`, `linter-staged.sh`: `/var/www/html` → `${APP_ROOT}`

**codex-config.toml:**
- `[projects."/var/www/html"]` → `[projects."/var/www/worktree/html"]`

**.env.example:**
- Nieuwe vars: `APP_ROOT=/var/www/worktree/html`, `WORKTREE_PARENT=.`, `WORKTREE_MOUNT=/var/www/worktree`

### Host-side verandering

De repo op de host verplaatst NIET. De Docker volume mount wijzigt:

```yaml
# Oud:
- .:/var/www/html

# Nieuw:
- .:${APP_ROOT:-/var/www/worktree/html}         # repo → container
- ${WORKTREE_PARENT:-.}:${WORKTREE_MOUNT:-/var/www/worktree}  # parent → worktrees zichtbaar
```

`WORKTREE_PARENT` wijst naar de directory op de host die `html/` en toekomstige
worktrees bevat. Default: `.` (de repo IS de parent, worktrees worden siblings).

### Verificatie

- `docker compose up -d` → app draait op dezelfde poort, zelfde gedrag
- `docker exec pma_yii vendor/bin/codecept run unit` → tests slagen
- `./linter.sh check` → werkt
- Geen wijzigingen aan Yii code (alle config is al relatief)

### Risico

Dit is de enige fase met breaking change potentie. Alle hardcoded paden moeten
in één keer gemigreerd worden. Grondige test nodig.

---

## Fase 2: Bugfix — alleen source code gescheiden

### Doel

Een worktree aanmaken voor een bugfix. Alleen de PHP/JS source code verschilt.
Al het andere komt van main.

### Wat is gescheiden

- Git worktree (eigen branch, eigen code-wijzigingen)

### Wat is gedeeld

- `vendor/` — hardlink copy van main (`cp -al`)
- `.env` — symlink naar `../html/.env`
- Database — zelfde `yii_test` schema
- Sessions — zelfde (geen aparte login nodig)
- Nginx — cookie-routing (zelfde poort)

### Setup (`php yii worktree/setup --id=42`)

```
1. cp -al html/yii/vendor → html-bugfix/yii/vendor
2. ln -s ../html/.env → html-bugfix/.env
3. mkdir -p html-bugfix/yii/runtime
4. mkdir -p html-bugfix/yii/web/assets
```

Geen database setup. Geen nginx config. ~1 seconde.

### Teardown

Verwijder git worktree + DB record. Geen DB of nginx cleanup nodig.

### Test workflow

```bash
# Unit tests in de bugfix worktree:
docker exec -w /var/www/worktree/html-bugfix/yii pma_yii vendor/bin/codecept run unit

# Of een specifieke test:
docker exec -w /var/www/worktree/html-bugfix/yii pma_yii \
    vendor/bin/codecept run unit tests/unit/path/ToTest.php

# Browser testing: zet cookie _worktree=bugfix, reload pagina
```

---

## Fase 3: Small feature / refactor — DB en sessions gedeeld

### Doel

Een feature of refactor waarbij je mogelijk eigen dependencies of env vars nodig hebt,
maar de database en login deelt met main.

### Wat is gescheiden

- Git worktree (eigen branch)
- `vendor/` — `composer install` (eigen `composer.lock` mogelijk)
- `.env` — eigen kopie (nieuwe env vars mogelijk)

### Wat is gedeeld

- Database — zelfde schema (gedeelde DB)
- Sessions — gedeeld (cookie-routing, één login)
- Nginx — cookie-routing (zelfde poort)

### Setup (`php yii worktree/setup --id=42`)

```
1. Detectie: verschilt composer.lock? → composer install, anders cp -al
2. cp html/.env → html-feat/.env (kopie, niet symlink — kan afwijken)
3. mkdir -p html-feat/yii/runtime
4. mkdir -p html-feat/yii/web/assets
5. Indien nieuwe migraties: draai op gedeeld test schema
```

### Verschil met fase 2

- `composer install` i.p.v. `cp -al` wanneer dependencies afwijken
- `.env` is een kopie (bewerkbaar) i.p.v. symlink
- Migraties worden gedraaid op het gedeelde schema (nieuwe tabellen/kolommen
  voor de feature)

### Aandachtspunt: migraties op gedeeld schema

Als de feature nieuwe migraties toevoegt en die draait op het gedeelde test schema,
dan is dat schema gewijzigd. Main's code verwacht die kolommen niet, maar Yii's
ActiveRecord negeert extra kolommen — geen probleem zolang er geen breaking changes
zijn (kolom verwijderd, type gewijzigd, NOT NULL zonder default).

Als de migratie wél breaking is → escaleer naar fase 4 (eigen DB).

---

## Fase 4: Big feature / spike — alles gescheiden

### Doel

Een grote feature of spike waarbij de database-structuur wijzigt en je volledige
isolatie nodig hebt. Apart inloggen, eigen schema, eigen Nginx poort.

### Wat is gescheiden

- Git worktree (eigen branch)
- `vendor/` — `composer install`
- `.env` — eigen kopie
- Database — eigen test schema (`promptmanager_test_{suffix}`)
- Sessions — gescheiden (ander origin)
- Nginx — eigen poort (poort-routing)

### Wat is gedeeld

- Niets. Volledig geïsoleerd.

### Setup (`php yii worktree/setup --id=42`)

```
1. composer install
2. cp html/.env → html-feat/.env
3. mkdir -p html-feat/yii/runtime
4. mkdir -p html-feat/yii/web/assets
5. CREATE DATABASE promptmanager_test_{suffix}
6. php yii migrate op eigen schema
7. Genereer nginx server block op volgende vrije poort
8. nginx -s reload
```

### Teardown

```
1. Verwijder nginx server block + reload
2. DROP DATABASE promptmanager_test_{suffix}
3. Verwijder git worktree + DB record
```

### Sessions en login

Eigen poort = eigen origin = eigen `PHPSESSID` cookie. De gebruiker moet apart
inloggen in de worktree. Dit is correct: de user-tabel in de worktree's DB is een
eigen kopie.

**Data seeding:** Na `CREATE DATABASE` + `migrate` is de database leeg. De gebruiker
moet een account aanmaken of we seeden de test-user automatisch als onderdeel van
setup.

---

## Implementatievolgorde

```
Fase 1 ──→ Fase 2 ──→ Fase 3 ──→ Fase 4
fundament    bugfix     feature     full isolation
             │                       │
             └── cookie-routing ─────┘── poort-routing
```

Fase 1 is voorwaarde voor alles. Fase 2-4 zijn incrementeel maar onafhankelijk
implementeerbaar (2 kan zonder 3, 3 kan zonder 4).

### Per fase: wat bouwen

| Fase | Infra | Yii code | UI |
|------|-------|----------|-----|
| 1 | docker-compose, Dockerfile, nginx, scripts | — | — |
| 2 | nginx map block (cookie-routing) | `worktree/setup` command (cp-al, symlink) | — |
| 3 | — | setup: composer install, detectie | worktree-selector dropdown |
| 4 | nginx port range, config generatie | setup: create DB, migrate, poort-allocatie | link met poort |

---

## Componentarchitectuur

### Bestanden per component

| Component | Bestandslocatie | Verantwoordelijkheid |
|-----------|----------------|----------------------|
| Console command | `yii/commands/WorktreeController.php` | `actionSetup`, `actionTeardown` — CLI interface |
| Setup service | `yii/services/worktree/WorktreeSetupService.php` | Orchestratie van vendor, env, DB, nginx setup |
| Nginx config service | `yii/services/worktree/WorktreeNginxService.php` | Genereer/verwijder server blocks, poort-allocatie |
| Migratie | `yii/migrations/mXXXXXX_XXXXXX_add_isolation_columns_to_project_worktree.php` | Kolommen: `vendor_mode`, `env_mode`, `db_schema`, `nginx_port` |
| Nginx template | `docker/nginx-worktree.conf.template` | Server block template voor worktree poort-routing |

### Relatie met bestaande WorktreeService

`WorktreeService` (bestaand, `yii/services/worktree/WorktreeService.php`) beheert git worktree + DB record (CRUD).
`WorktreeSetupService` (nieuw) beheert de omgeving ná `WorktreeService::create()`:

```
WorktreeService::create()         → git worktree add + ProjectWorktree record
WorktreeSetupService::setup()     → vendor, .env, DB schema, nginx config
WorktreeSetupService::teardown()  → DB drop, nginx remove
WorktreeService::remove()         → roept teardown() aan, dan git worktree remove + record delete
```

**Bestaande bestanden die gewijzigd worden:**

| Bestand | Wijziging |
|---------|-----------|
| `yii/services/worktree/WorktreeService.php` | `remove()` roept `WorktreeSetupService::teardown()` aan (DI via constructor) |
| `yii/models/ProjectWorktree.php` | Nieuwe `@property` annotaties + `rules()` voor isolatieprofiel kolommen |
| `yii/config/console.php` | Registratie van `WorktreeController` console command (als niet auto-discovered) |

### WorktreeSetupService methoden

```php
class WorktreeSetupService
{
    public function __construct(
        private readonly WorktreeNginxService $nginxService,
        private readonly PathService $pathService,
    ) {}

    /** Voer volledige setup uit op basis van isolatieprofiel */
    public function setup(ProjectWorktree $worktree): void

    /** Verwijder alle setup-artefacten (DB schema, nginx config) */
    public function teardown(ProjectWorktree $worktree): void

    /** Hardlink copy of composer install, op basis van vendor_mode */
    public function setupVendor(ProjectWorktree $worktree): void

    /** Symlink of kopie, op basis van env_mode */
    public function setupEnv(ProjectWorktree $worktree): void

    /** Create database + migraties, als db_schema niet null */
    public function setupDatabase(ProjectWorktree $worktree): void

    /** Genereer nginx config + alloceer poort, als nginx_port gevraagd */
    public function setupNginx(ProjectWorktree $worktree): void

    /** Detecteer of composer.lock verschilt van main */
    public function detectVendorMode(ProjectWorktree $worktree): string
}
```

### Shell command executie

Shell commands (`cp -al`, `composer install`, `ln -s`) worden uitgevoerd via PHP's
`Process` class (Symfony Process, beschikbaar via Composer). Dit is consistent met
hoe `WorktreeService` al git commands uitvoert.

```php
use Symfony\Component\Process\Process;

$process = new Process(['cp', '-al', $source, $target]);
$process->mustRun();
```

### Cross-container nginx config

`WorktreeNginxService` draait in `pma_yii` maar nginx config staat in `pma_nginx`.
Oplossing: **gedeeld volume voor worktree nginx configs.**

```yaml
# docker-compose.yml — gedeeld volume
volumes:
  worktree-nginx-conf:

services:
  pma_yii:
    volumes:
      - worktree-nginx-conf:/var/shared/nginx/worktrees
  pma_nginx:
    volumes:
      - worktree-nginx-conf:/etc/nginx/conf.d/worktrees
```

Nginx includes alles in `conf.d/`. `WorktreeNginxService` schrijft naar
`/var/shared/nginx/worktrees/wt-{suffix}.conf` (zichtbaar in `pma_yii`), wat
in `pma_nginx` verschijnt als `/etc/nginx/conf.d/worktrees/wt-{suffix}.conf`.

**Nginx reload:** Het console command draait in `pma_yii` en kan niet rechtstreeks
`nginx -s reload` uitvoeren in `pma_nginx`. Twee opties:

1. **Signal file (aanbevolen):** `WorktreeNginxService` schrijft een marker file
   (`/var/shared/nginx/worktrees/.reload`) na config-wijziging. De nginx container
   draait een lightweight inotify-watcher (of cron) die bij detectie `nginx -s reload`
   uitvoert. Geen Docker socket nodig.

2. **Docker socket mount:** Mount `/var/run/docker.sock` in `pma_yii` en gebruik
   Docker API om reload te triggeren. Meer rechten nodig, security-implicatie.

**Keuze:** Optie 1 (signal file) — minimale rechten, geen Docker socket.

### Migratie: isolatieprofiel kolommen

Nieuwe kolommen op `project_worktree`:

| Kolom | Type | Default | Beschrijving |
|-------|------|---------|-------------|
| `vendor_mode` | `ENUM('shared','install')` | `'shared'` | `cp -al` vs `composer install` |
| `env_mode` | `ENUM('symlink','copy')` | `'symlink'` | Symlink naar main vs eigen kopie |
| `db_schema` | `VARCHAR(100)` | `NULL` | Eigen test schema naam, NULL = gedeeld |
| `nginx_port` | `SMALLINT UNSIGNED` | `NULL` | Toegewezen poort, NULL = cookie-routing |

### Environment variabelen

| Variabele | Gedefinieerd in | Default | Beschrijving |
|-----------|----------------|---------|-------------|
| `APP_ROOT` | `.env` + `docker-compose.yml` | `/var/www/worktree/html` | Container pad naar actieve app |
| `WORKTREE_PARENT` | `.env` + `docker-compose.yml` | `.` | Host pad naar parent van repo + worktrees |
| `WORKTREE_MOUNT` | `.env` + `docker-compose.yml` | `/var/www/worktree` | Container mount voor worktree parent |
| `WORKTREE_PORT_MIN` | `.env` | `8504` | Eerste poort voor worktree port-routing |
| `WORKTREE_PORT_MAX` | `.env` | `8523` | Laatste poort voor worktree port-routing |

Alle variabelen worden gedocumenteerd in `.env.example`.

### Beveiligingseisen

#### Cookie-routing (fase 2-3)

De `_worktree` cookie wordt door Nginx gebruikt als `map` input voor de document root.
Validatie is **verplicht** in het nginx `map` block:

```nginx
map $cookie__worktree $wt_root {
    default                     /var/www/worktree/html;
    ~^([a-zA-Z0-9_-]{1,100})$   /var/www/worktree/html-$1;
}
```

Alleen alfanumeriek, underscore en hyphen. Max 100 tekens. Sluit path traversal uit.
Als de directory niet bestaat geeft Nginx een 404.

#### Database schema naming (fase 4)

Schema-naam: `promptmanager_test_{suffix}`. Het suffix wordt gevalideerd:
- Regex: `/^[a-z0-9_-]{1,50}$/`
- Gewhitelist tegen bestaande DB namen (`information_schema`, `mysql`, `yii`, `yii_test`)
- `WorktreeSetupService::setupDatabase()` gebruikt prepared DDL via `Yii::$app->db->createCommand()`

#### Nginx config generatie (fase 4)

Server blocks worden gegenereerd vanuit een template (`docker/nginx-worktree.conf.template`).
Het worktree-pad wordt:
- Gevalideerd als absolute pad onder `WORKTREE_MOUNT`
- Gecontroleerd op bestaande directory (`is_dir()`)
- Ge-escaped voor nginx syntax (geen variabelen, geen regex-actieve tekens)

#### DROP DATABASE safeguard (teardown)

Vóór `DROP DATABASE` wordt gevalideerd dat:
1. De schemanaam begint met `promptmanager_test_`
2. De schemanaam in het `project_worktree.db_schema` veld staat van het betreffende record
3. Het record bij de huidige project/user hoort (RBAC)

#### Parent mount scoping

`WORKTREE_MOUNT` mount exposeert potentieel meer dan nodig. Mitigatie:
- Documenteer in `.env.example` dat `WORKTREE_PARENT` naar een dedicated worktree-parent moet wijzen, niet naar een brede projectmap
- De mount is `:rw` voor worktrees, maar alleen `html*` directories worden aangesproken
- Binnen de container draait de app als `appuser` (non-root), waardoor schrijftoegang beperkt is tot owned directories

#### RBAC voor console commands

`WorktreeController::actionSetup` en `actionTeardown`:
- CLI context: geen web-RBAC, maar validatie via `ProjectWorktree` ownership
- Het command leest `--id`, laadt het `ProjectWorktree` record, en valideert dat:
  - Het record bestaat
  - Het record's `project.user_id` matcht met de verwachte gebruiker (als `--user-id` meegegeven)
  - Voor teardown: het record's status toestaat dat teardown uitgevoerd wordt

**Opmerking:** In CLI context is er geen ingelogde gebruiker. De validatie is op basis van record-integriteit, niet op RBAC. Dit is acceptabel omdat Docker exec alleen beschikbaar is voor gebruikers met container-toegang.

#### Port binding

Poort-range in `docker-compose.yml` wordt gebonden aan `127.0.0.1`:

```yaml
ports:
  - "127.0.0.1:${NGINX_PORT:-8503}:80"
  - "127.0.0.1:${WORKTREE_PORT_MIN:-8504}-${WORKTREE_PORT_MAX:-8523}:81-100"
```

Voorkomt externe toegang tot worktree-instanties.

### Foutafhandeling per fase

| Fase | Foutscenario | Afhandeling |
|------|-------------|-------------|
| 1 | `docker compose up` faalt na padwijziging | Rollback: herstel originele docker-compose.yml, documenteer in CHANGELOG |
| 2 | `cp -al` faalt (cross-filesystem) | Fallback naar `composer install`, log waarschuwing |
| 2 | Worktree directory bestaat al | Foutmelding: "Worktree path already exists", geen actie |
| 3 | `composer install` faalt | Foutmelding + rollback (verwijder aangemaakte dirs), worktree record blijft |
| 3 | Breaking migratie op gedeeld schema | Detectie: controleer of migratie `dropColumn`/`alterColumn` bevat → adviseer fase 4 |
| 4 | Poort al in gebruik | Volgende vrije poort proberen uit range, fout als range vol |
| 4 | `CREATE DATABASE` faalt | Foutmelding, geen nginx config genereren, worktree record opschonen |
| 4 | `nginx -s reload` faalt | Config terugdraaien, foutmelding met nginx error log |

---

## UI: Worktree Selector

### Locatie

Navbar, rechts naast de bestaande project-selector. Alleen zichtbaar als er minimaal
één worktree bestaat (anders verborgen — geen lege state in de navbar).

### Wireframe

```
┌──────────────────────────────────────────────────────────────┐
│  [Logo]  [Project ▾]  [Worktree: main ▾]        [User ▾]    │
└──────────────────────────────────────────────────────────────┘
                         │
                         ▼ (dropdown open)
                    ┌─────────────────────┐
                    │ ● main              │  ← actief, bold
                    │ ○ bugfix-123        │  ← cookie-routing
                    │ ○ feat-x            │  ← cookie-routing
                    │ ─────────────────── │
                    │ ○ spike-y :8504 ↗   │  ← poort-routing, external link
                    └─────────────────────┘
```

### Component

| Aspect | Detail |
|--------|--------|
| Type | Bootstrap 5 dropdown (bestaand patroon) |
| View partial | `yii/views/layouts/_worktree_selector.php` (included in main layout) |
| JS module | `npm/src/js/worktree-selector.js` (AJAX, cookie, dropdown logica) |
| Data source | Server-side rendered initieel (PHP haalt worktrees op bij page load) |
| | AJAX call naar `/worktree/status` alleen voor refresh/polling |
| Trigger | Cookie-routing: `document.cookie` zetten + `location.reload()` |
| | Poort-routing: `window.open()` naar `localhost:{port}` |

### Cookie specificatie

```javascript
document.cookie = `_worktree=${suffix}; path=/; SameSite=Lax`;
```

- `path=/` — cookie geldt voor alle routes
- `SameSite=Lax` — voorkomt CSRF bij cross-site navigatie
- Geen `Secure` — lokale development omgeving (HTTP)
- Verwijderen: `document.cookie = '_worktree=; path=/; max-age=0'`

### Responsive gedrag

- **Desktop (>= 992px)**: Selector zichtbaar in navbar naast project-selector
- **Mobile (< 992px)**: Selector in collapsed navbar menu, volledige breedte
- Zelfde Bootstrap 5 `navbar-collapse` patroon als bestaande project-selector

### Accessibility

- Dropdown button: `aria-label="Selecteer worktree"`, `aria-expanded="true|false"`
- Dropdown items: `role="menuitem"`, actieve worktree `aria-current="true"`
- Keyboard: `Enter`/`Space` opent dropdown, `Arrow` keys navigeren, `Escape` sluit

### UI States

| State | Weergave |
|-------|----------|
| **Geen worktrees** | Selector is verborgen (geen navbar element) |
| **Loading** | Dropdown toont spinner icon + "Laden..." tekst |
| **Loaded** | Lijst met worktrees, actieve gemarkeerd met bullet (●) |
| **Error** | Dropdown toont "Kon worktrees niet laden" + retry link |
| **Na selectie (cookie)** | Pagina herlaadt, selector toont nieuwe actieve worktree |
| **Na selectie (poort)** | Nieuw tab opent, huidige tab ongewijzigd |

### Actieve worktree indicator

De huidige worktree wordt bepaald door:
- **Cookie-routing**: waarde van `_worktree` cookie (leeg = main)
- **Poort-routing**: niet van toepassing (eigen browser tab/origin)

Visuele indicator in de navbar button:
- **Main**: `[Worktree: main ▾]` (standaard stijl)
- **Worktree actief**: `[Worktree: bugfix-123 ▾]` (gekleurde badge, bijv. `badge-info`)

### Interactieflow

```
1. Gebruiker klikt op worktree dropdown
2. AJAX haalt /worktree/status op (als niet gecached)
3. Dropdown toont lijst met worktrees
4. Gebruiker selecteert worktree:
   a. Cookie-routing → cookie gezet → page reload → server serveert worktree code
   b. Poort-routing → window.open('http://localhost:{port}') → nieuw tab
5. Na reload: selector toont nieuwe actieve worktree
```

---

## Test scenarios

### Fase 1: Fundament (smoke tests)

| ID | Scenario | Verwacht resultaat |
|----|----------|--------------------|
| T1.1 | `docker compose up -d` met nieuwe padconfiguratie | Alle containers starten, app bereikbaar op bestaande poort |
| T1.2 | `vendor/bin/codecept run unit` na padwijziging | Alle bestaande tests slagen |
| T1.3 | `./linter.sh check` na padwijziging | Linter draait zonder fouten |
| T1.4 | APP_ROOT env var niet gezet (default) | App gebruikt default `/var/www/worktree/html` |

### Fase 2: WorktreeSetupService — shared vendor

| ID | Scenario | Verwacht resultaat |
|----|----------|--------------------|
| T2.1 | `setupVendor()` met zelfde composer.lock | `cp -al` succesvol, vendor bestanden zijn hardlinks |
| T2.2 | `setupEnv()` met symlink mode | `.env` symlink wijst naar main `.env` |
| T2.3 | `setup()` creëert runtime + assets dirs | `yii/runtime/` en `yii/web/assets/` bestaan |
| T2.4 | Codeception tests draaien in worktree | `docker exec -w {worktree_path}/yii` tests slagen |
| T2.5 | `teardown()` op shared worktree | Geen DB of nginx cleanup, alleen dirs verwijderd |
| T2.6 | `setupVendor()` op cross-filesystem | Fallback naar `composer install`, warning gelogd |
| T2.7 | `setup()` op niet-bestaande worktree path | RuntimeException met duidelijke foutmelding |
| T2.8 | `setup()` op worktree path die al bestaat | Foutmelding: "Worktree path already exists", geen actie |

### Fase 3: Feature worktree — shared DB

| ID | Scenario | Verwacht resultaat |
|----|----------|--------------------|
| T3.1 | `detectVendorMode()` met gewijzigde composer.lock | Retourneert `'install'` |
| T3.2 | `detectVendorMode()` met identieke composer.lock | Retourneert `'shared'` |
| T3.3 | `setupEnv()` met copy mode | Eigen `.env` kopie, wijzigbaar zonder main te raken |
| T3.4 | Cookie `_worktree=feat-x` → nginx serveert worktree | HTTP 200 met worktree's `index.php` |
| T3.5 | Cookie `_worktree=../../etc` → nginx map validatie | Cookie geweigerd, default root (main) geserveerd |
| T3.6 | Cookie `_worktree=nonexistent` → directory bestaat niet | Nginx retourneert 404 |
| T3.7 | Niet-breaking migratie op gedeeld schema | Main tests blijven slagen na extra kolom |
| T3.8 | Breaking migratie gedetecteerd (`dropColumn`/`alterColumn`) | Warning: "Breaking migration detected, consider phase 4 (isolated DB)" |

### Fase 4: Full isolation

| ID | Scenario | Verwacht resultaat |
|----|----------|--------------------|
| T4.1 | `setupDatabase()` creëert schema | Database `promptmanager_test_{suffix}` bestaat |
| T4.2 | Migraties draaien op eigen schema | Alle tabellen aanwezig, geen fouten |
| T4.3 | `setupNginx()` genereert server block | Config bestand bestaat in gedeeld volume |
| T4.4 | Nginx serveert worktree op toegewezen poort | HTTP 200 op `localhost:{port}` |
| T4.5 | Poort-allocatie bij vol bereik | RuntimeException: "No available ports in range" |
| T4.6 | `teardown()` dropt schema en verwijdert nginx config | Database weg, config weg, nginx reload geslaagd |
| T4.7 | DROP DATABASE safeguard: schema zonder prefix | Weigering: schema naam begint niet met `promptmanager_test_` |
| T4.8 | Data seeding na CREATE DATABASE | Test user bestaat in worktree DB |
| T4.9 | Signal file trigger → nginx reload | `.reload` marker file geschreven, nginx herlaadt config binnen 5 seconden |

### UI: Worktree Selector

| ID | Scenario | Verwacht resultaat |
|----|----------|--------------------|
| T5.1 | Geen worktrees in project | Selector niet zichtbaar in navbar |
| T5.2 | 1+ worktrees, dropdown openen | Lijst met worktrees, actieve gemarkeerd |
| T5.3 | Selectie cookie-routing worktree | Cookie gezet, pagina herlaadt, nieuwe worktree actief |
| T5.4 | Selectie poort-routing worktree | Nieuw tab opent op correcte poort |
| T5.5 | `/worktree/status` endpoint faalt | Dropdown toont foutmelding + retry |
| T5.6 | Terug naar main selecteren | Cookie verwijderd, pagina toont main |

### Regressie-impact

| Gewijzigd bestand | Bestaande tests | Risico |
|-------------------|----------------|--------|
| `WorktreeService.php` | `tests/unit/services/worktree/WorktreeServiceTest.php` | `remove()` wijzigt → mock `WorktreeSetupService` toevoegen |
| `ProjectWorktree.php` | `tests/unit/models/ProjectWorktreeTest.php` | Nieuwe kolommen → fixture data bijwerken |
| `docker-compose.yml` | Alle integration tests | Pad wijzigt → CI config aanpassen |

### Test fixtures benodigd

| Fixture | Beschrijving |
|---------|-------------|
| `ProjectWorktreeFixture` (uitbreiden) | Records met diverse `vendor_mode`, `env_mode`, `db_schema`, `nginx_port` waarden |
| Worktree directory mock | Temp directory structuur die een worktree simuleert (voor unit tests) |

---

## Design docs per fase

Elke fase krijgt een eigen spec + plan in:
```
.claude/design/feature/worktree-testing/
├── phases.md               ← dit document
├── phase-1/
│   ├── spec.md             ← wat + waarom
│   └── plan.md             ← hoe + welke bestanden
├── phase-2/
│   ├── spec.md
│   └── plan.md
├── phase-3/
│   └── ...
└── phase-4/
    └── ...
```
