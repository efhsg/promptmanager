# Fase 1: Worktree-ready fundament — Specificatie

## Doel

De applicatie moet draaien vanuit `/var/www/worktree/html/` in plaats van `/var/www/html/`.
Dit introduceert een directory-niveau (`worktree/`) zodat toekomstige worktrees als siblings
naast `html/` kunnen bestaan (bijv. `html-bugfix-123/`, `html-feat-x/`).

Met alleen branch "main" is het gedrag 100% identiek aan de huidige situatie.

## Waarom

Het huidige pad `/var/www/html` is 20 keer onafhankelijk hardcoded in 7 productiebestanden
(zie `problem.md` voor de oorspronkelijke inventarisatie, bijgewerkt in "Te wijzigen bestanden"). Worktrees worden door `WorktreeService` aangemaakt als siblings
(`{root}-{suffix}`), maar die vallen buiten de Docker volume mount en zijn onzichtbaar voor
Nginx, PHP-FPM en `docker exec`.

Door het pad te verschuiven naar `/var/www/worktree/html`:
1. Er is een parent directory (`/var/www/worktree/`) waar siblings in passen
2. Alle 20 hardcoded paden worden teruggebracht naar **1 bron van waarheid** (`APP_ROOT`)
3. De infra is voorbereid op fase 2-4 zonder verdere breaking changes

## Scope

### Wel

- Alle hardcoded `/var/www/html` in productiebestanden vervangen door `${APP_ROOT}`
- Nieuwe env var `APP_ROOT` met default `/var/www/worktree/html`
- Documentatie bijwerken (CLAUDE.md, skills, commands, rules)
- Verificatie dat de app identiek functioneert na rebuild

### Niet

- Geen worktree-specifieke logica (dat is fase 2+)
- Geen Nginx multi-worktree configuratie (cookie-routing, poort-routing — fase 2+)
- Geen worktree poort-range (`WORKTREE_PORT_MIN`/`WORKTREE_PORT_MAX` — fase 4)
- Geen gedeeld nginx config volume (`worktree-nginx-conf` — fase 4)
- Geen wijzigingen aan PHP/Yii applicatiecode
- Geen wijzigingen aan `.claude/design/` bestanden (die documenteren de historische situatie)
- Geen wijzigingen aan port bindings (127.0.0.1 en Tailscale zijn reeds geconfigureerd)
- Geen parent mount (uitgesteld naar fase 2)

## Directorystructuur

**Voor (huidige situatie):**
```
/var/www/html/                  <- volume mount, IS de repo root
├── yii/
├── docker/
├── .env
└── ...
```

**Na (fase 1):**
```
/var/www/worktree/              <- parent, toekomstige worktrees worden hier siblings
└── html/                       <- main branch (de repo zelf)
    ├── yii/
    ├── docker/
    ├── .env
    └── ...
```

**Na (fase 2+, ter illustratie):**
```
/var/www/worktree/
├── html/                       <- main
├── html-bugfix-123/            <- worktree (fase 2)
└── html-feat-x/                <- worktree (fase 3/4)
```

## Te wijzigen bestanden

### Productiebestanden (infra)

| # | Bestand (relatief aan repo root) | Hardcoded referenties | Wijziging |
|---|----------------------------------|----------------------|-----------|
| 1 | `docker-compose.yml` | 8 | Volume mounts, working_dir, PATH env → `${APP_ROOT}`; `APP_ROOT` env var toevoegen aan `pma_nginx`; `$$APP_ROOT` toevoegen aan `envsubst` argumenten |
| 2 | `docker/yii/Dockerfile` | 6 | ARG + alle mkdir, chown, error_log, symlinks → `${APP_ROOT}` |
| 3 | `docker/nginx.conf.template` | 1 | `root` directive → `${APP_ROOT}/yii/web` via envsubst |
| 4 | `linter.sh` | 1 | Config pad → `${APP_ROOT}`. NB: draait op host via `docker exec` — gebruik `${APP_ROOT:-/var/www/worktree/html}` fallback |
| 5 | `linter-staged.sh` | 2 | Padconversie + config → `${APP_ROOT}`. NB: draait op host via `docker exec` — gebruik `${APP_ROOT:-/var/www/worktree/html}` fallback |
| 6 | `.vscode/launch.json` | 1 | Xdebug path mapping → `/var/www/worktree/html` |
| 7 | `docker/yii/codex-config.toml` | 1 | Project sectie → `/var/www/worktree/html` |
| 8 | `.env.example` | 0 | Nieuwe var `APP_ROOT=/var/www/worktree/html` toevoegen |

**Totaal: 20 hardcoded referenties in 7 productiebestanden + 1 nieuw.**

### Documentatiebestanden

| # | Bestand | Wijziging |
|---|---------|-----------|
| 1 | `CLAUDE.md` | Quick reference pad bijwerken |
| 2 | `.claude/config/project.md` | Padverwijzingen bijwerken |
| 3 | `.claude/rules/testing.md` | Testcommando pad bijwerken |
| 4 | `.claude/skills/onboarding.md` | Padverwijzing bijwerken |
| 5 | `.claude/skills/refactor.md` | Padverwijzing bijwerken |
| 6 | `.claude/commands/finalize-changes.md` | Padverwijzingen bijwerken |
| 7 | `.claude/commands/analyze-codebase.md` | Padverwijzing bijwerken |

### Niet wijzigen

- `.claude/design/` bestanden — documenteren historische situatie (zie Scope > Niet)

## Invarianten

1. **Transparantie:** Met alleen main branch draait de app identiek. Geen functieverlies.
2. **Single source of truth:** `APP_ROOT` is de enige plek waar het pad gedefinieerd wordt.
   Alle andere referenties lezen `${APP_ROOT}`. Uitzondering: `.vscode/launch.json` en
   `codex-config.toml` gebruiken het hardcoded pad `/var/www/worktree/html` omdat die
   formaten geen env var substitutie ondersteunen.
3. **Backward compatible default:** `APP_ROOT` default is `/var/www/worktree/html`. Als de
   env var niet gezet is, werkt alles nog steeds.
4. **Geen PHP-code wijzigingen:** Yii's interne paden zijn relatief (`__DIR__`, `dirname(__DIR__)`).
   Die veranderen niet.
5. **Host-side ongewijzigd:** De repo op de host verplaatst NIET. Alleen de container-paden wijzigen.
6. **Geen parent mount:** De parent mount voor worktree-siblings is bewust uitgesteld naar
   fase 2. In fase 1 is alleen `APP_ROOT` nodig; de parent mount vereist een correct
   geconfigureerd host-pad dat pas relevant wordt wanneer er daadwerkelijk siblings bestaan.

## Configuratiecontract APP_ROOT

### Bronprioriteit

1. `.env` bevat `APP_ROOT` (expliciete projectconfiguratie)
2. Als `APP_ROOT` ontbreekt of leeg is: fallback `/var/www/worktree/html`

### Propagatieketen

1. `docker-compose.yml` levert `APP_ROOT` aan `pma_yii`, `pma_queue` en `pma_nginx`
2. `docker-compose.yml` gebruikt `${APP_ROOT:-/var/www/worktree/html}` voor mounts, `working_dir` en PATH-gerelateerde waarden
3. `docker/yii/Dockerfile` gebruikt `ARG APP_ROOT=/var/www/worktree/html` voor build-time padoperaties
4. `pma_nginx` entrypoint gebruikt `envsubst` met `APP_ROOT` zodat `docker/nginx.conf.template` runtime correct resolveert

### Build-time vs runtime

- Build-time (image build): `docker/yii/Dockerfile` via `ARG APP_ROOT`
- Runtime (container start): `docker-compose.yml` env + `envsubst` voor Nginx template
- Host scripts (`linter.sh`, `linter-staged.sh`): gebruiken `${APP_ROOT:-/var/www/worktree/html}` fallback bij `docker exec`

### Verificatiechecks per laag

1. Compose laag: `docker exec pma_yii pwd` geeft `${APP_ROOT}/yii`
2. Nginx laag: `docker exec pma_nginx cat /etc/nginx/nginx.conf | grep root` bevat `${APP_ROOT}/yii/web`
3. PHP-CLI laag: `docker exec pma_yii php -i | grep error_log` bevat `${APP_ROOT}/yii/runtime/logs/php_errors.log`

## Security guardrails APP_ROOT

1. `APP_ROOT` moet een absoluut pad zijn (start met `/`).
2. `APP_ROOT` moet beginnen met `/var/www/worktree/`.
3. `APP_ROOT` mag geen whitespace, `..` of shell-metatekens bevatten. Verboden tekens:
   `` ` $ ; & | < > ( ) { } [ ] ! * ? ' " ``.
4. Bij ongeldige waarde geldt fail-fast: compose/configuratiechecks en host-scripts stoppen met duidelijke foutmelding.

### Implementatieverdeling guardrails

1. `linter.sh` en `linter-staged.sh` voeren shell-validatie uit vóór `docker exec`.
2. `docker-compose.yml` levert defaults en variabel-expansie, maar is niet de primaire sanitization-laag.
3. `docker/yii/Dockerfile` gebruikt alleen build-time default (`ARG APP_ROOT=...`) en doet geen security-validatie.
4. Acceptatiecriteria `T1.12a` en `T1.12b` valideren respectievelijk compose-signaal en primair de script-laag.
5. Script-validatie gebruikt een allowlist-regex: `^/var/www/worktree/[A-Za-z0-9._/-]+$` plus expliciete reject op `..`.

## Operator states (CLI)

1. **Loading:** scripts en checks tonen een korte statusregel per stap (bijv. "Valideer APP_ROOT...", "Start docker compose config...").
2. **Success:** bij geslaagde validatie/checks tonen scripts een korte bevestiging met gebruikte waarde van `APP_ROOT`.
3. **Error:** bij ongeldige `APP_ROOT` tonen scripts een eenduidig foutformat met oorzaak en herstelactie.
4. **Empty/NVT:** als `APP_ROOT` niet is gezet, melden scripts expliciet dat fallback `/var/www/worktree/html` wordt gebruikt.

### Standaard foutformat

Beide scripts (`linter.sh`, `linter-staged.sh`) gebruiken exact:
`ERROR: APP_ROOT invalid | value='{...}' | reason='{...}' | fix='Gebruik pad onder /var/www/worktree/'`

### Exitcode-contract scripts

1. Geldige `APP_ROOT` en succesvolle uitvoering: exitcode `0`.
2. Ongeldige `APP_ROOT` (validatiefout): exitcode `2`.
3. Overige runtimefouten (bijv. docker command faalt): exitcode `1`.

## 8+ Checklist Dekking (fase 1)

| Checklistpunt | Status | Toelichting fase 1 |
|---------------|--------|--------------------|
| Geen interne contradicties | Gedekt | Scope, invarianten, acceptatiecriteria en risico's gebruiken hetzelfde padmodel (`APP_ROOT`) |
| Alle componenten hebben file locaties | Gedekt | Alle infra- en documentatiecomponenten staan expliciet in "Te wijzigen bestanden" |
| UI states gespecificeerd (loading/error/empty/success) | NVT | Fase 1 bevat geen UI-wijzigingen of nieuwe frontend states |
| Security validaties expliciet per endpoint | NVT | Fase 1 wijzigt geen endpoints/controllers of autorisatielogica |
| Wireframe/layout ↔ componentbeschrijvingen | NVT | Er is geen wireframe- of layoutwijziging in deze infrafase |
| Test scenarios dekken alle edge cases | Gedekt | Acceptatiecriteria T1.1-T1.16 dekken build, runtime, fallback, negatieve validatie en script-flow padverificatie |
| Herbruikbare componenten geïdentificeerd met locatie | Gedekt | `APP_ROOT` is centraal contract in `docker-compose.yml`, `docker/yii/Dockerfile`, `docker/nginx.conf.template`, `linter.sh`, `linter-staged.sh` |

## Risico's

| Risico | Impact | Mitigatie |
|--------|--------|-----------|
| Gemist hardcoded pad | App breekt op specifieke functie | Grep-verificatie na alle wijzigingen |
| Dockerfile heredoc variable expansion | PHP error_log wijst naar oud pad | Gebruik `<<EOF` (unquoted) i.p.v. `<<'EOF'` |
| Env var niet beschikbaar in build | Dockerfile ARG valt terug op default | Default waarde in ARG declaratie |
| `.env` niet bijgewerkt door gebruiker | APP_ROOT is leeg, defaults vallen terug | Fallback `${APP_ROOT:-/var/www/worktree/html}` overal |
| Xdebug/PHPStorm path mappings breken | Debugger kan geen breakpoints matchen | `.vscode/launch.json` meenemen in wijzigingen |
| codex-config.toml niet gevonden | Codex CLI trust warning | Pad bijwerken |
| APP_ROOT directory bestaat niet in base image | `chown` faalt tijdens build | `mkdir -p ${APP_ROOT}` vóór `chown` in Dockerfile |

## Regressie-impact

| Gewijzigd bestand | Bestaande tests | Risico |
|-------------------|----------------|--------|
| `docker-compose.yml` | Alle integration tests | Paden wijzigen → CI config aanpassen indien aanwezig |
| `docker/yii/Dockerfile` | Alle containers (build) | Fout in ARG/ENV → containers starten niet |
| `docker/nginx.conf.template` | Alle web requests | Verkeerd root pad → 404 op alle pagina's |
| `linter.sh` / `linter-staged.sh` | Pre-commit hooks | `APP_ROOT` moet beschikbaar zijn op host of fallback werkt |
| `.vscode/launch.json` | Xdebug sessies | Path mappings breken totdat IDE herstart |

## Acceptatiecriteria

| ID | Scenario | Verwacht resultaat |
|----|----------|--------------------|
| T1.1 | `docker compose build --no-cache && docker compose up -d` | Alle containers starten, app bereikbaar op bestaande poort |
| T1.2 | `docker exec pma_yii pwd` | Toont `/var/www/worktree/html/yii` |
| T1.3 | `docker exec pma_nginx cat /etc/nginx/nginx.conf \| grep root` | Toont `/var/www/worktree/html/yii/web` |
| T1.4 | `docker exec pma_yii vendor/bin/codecept run unit` | Alle bestaande tests slagen |
| T1.5 | `./linter.sh check` | Linter draait zonder fouten |
| T1.6 | App openen in browser op `localhost:{NGINX_PORT}` | Login pagina laadt, inloggen slaagt, projectlijst zichtbaar, een project openen toont templates/contexts/fields |
| T1.7 | `! grep -n '/var/www/html' docker-compose.yml docker/yii/Dockerfile docker/nginx.conf.template linter.sh linter-staged.sh .vscode/launch.json docker/yii/codex-config.toml .env.example` | Geen matches in de 8 doelbestanden uit "Te wijzigen bestanden" (exitcode 0 = geslaagd) |
| T1.8 | `docker exec pma_yii php -i \| grep error_log` | Toont `/var/www/worktree/html/yii/runtime/logs/php_errors.log` |
| T1.9 | `docker exec pma_yii ls /var/www/worktree/html/yii/web/index.php` | Bestand bestaat — volume mount werkt |
| T1.10 | APP_ROOT env var niet gezet (default) | App gebruikt default `/var/www/worktree/html`. Test door T1.1-T1.9 uit te voeren zonder `APP_ROOT` in `.env`. Fallback via `:-` in docker-compose en `ARG` default in Dockerfile. |
| T1.11 | `docker compose exec -T pma_queue pwd` | Toont `/var/www/worktree/html/yii` — `pma_queue` paden zijn ook gemigreerd |
| T1.12a | `APP_ROOT=../tmp docker compose config` | Compose-config check faalt of geeft duidelijk signaal dat waarde ongeldig is |
| T1.12b | `APP_ROOT='/var/www/worktree/html;rm -rf /' ./linter.sh check` | Faalt direct met duidelijke foutmelding over ongeldige `APP_ROOT` waarde |
| T1.13 | `APP_ROOT` unset en `./linter.sh check` uitvoeren | Output meldt expliciet dat fallback `/var/www/worktree/html` gebruikt wordt |
| T1.14 | Ongeldige `APP_ROOT` met `./linter.sh check` | Foutmelding gebruikt vast format met oorzaak + herstelhint, en exitcode is `2` |
| T1.15 | Preconditie: `touch yii/models/_qa_app_root_probe.php && git add yii/models/_qa_app_root_probe.php`. Daarna `APP_ROOT` unset en `./linter-staged.sh` uitvoeren | Output meldt expliciet dat fallback `/var/www/worktree/html` gebruikt wordt. Na test: `git restore --staged yii/models/_qa_app_root_probe.php && rm yii/models/_qa_app_root_probe.php` |
| T1.16 | Preconditie: `touch yii/models/_qa_app_root_probe.php && git add yii/models/_qa_app_root_probe.php`. Daarna ongeldige `APP_ROOT` met `./linter-staged.sh` | Foutmelding gebruikt exact hetzelfde standaardformat als `linter.sh`, en exitcode is `2`. Na test: `git restore --staged yii/models/_qa_app_root_probe.php && rm yii/models/_qa_app_root_probe.php` |
