# Fase 1: Worktree-ready fundament — Implementatieplan

## Overzicht wijzigingen

| # | Bestand | Aantal wijzigingen | Aard |
|---|---------|-------------------|------|
| 1 | `.env.example` | 1 | Nieuwe var `APP_ROOT` |
| 2 | `docker/yii/Dockerfile` | 6 | ARG + 5 hardcoded paden |
| 3 | `docker-compose.yml` | 10 | 3 services x volumes/workdir/PATH + build args + nginx env |
| 4 | `docker/nginx.conf.template` | 1 | root directive |
| 5 | `docker/yii/codex-config.toml` | 1 | trust path |
| 6 | `linter.sh` | 1 | config pad |
| 7 | `linter-staged.sh` | 2 | sed prefix + config pad |
| 8 | `.vscode/launch.json` | 1 | pathMapping |
| 9 | Documentatie (7 bestanden) | 9 | Pad-referenties in md-bestanden |

Totaal: **8 productiebestanden**, **7 documentatiebestanden**, **~30 wijzigingen**

---

## 1. `.env.example`

**Toevoegen** na `PHP_FPM_PORT`:

```env
# Application root inside the container.
# The /var/www/worktree/ parent allows sibling worktrees for isolated development
# (e.g., /var/www/worktree/html-feat-x/). Parent mount wordt toegevoegd in fase 2.
APP_ROOT=/var/www/worktree/html
```

**Waarom hier:** Tussen de poort-config en Xdebug-config in, bij de andere container-gerelateerde vars.
`APP_ROOT` bepaalt waar de app draait binnen de container. De parent mount variabelen
(`WORKTREE_PARENT`, `WORKTREE_MOUNT`) worden pas in fase 2 geïntroduceerd wanneer er
daadwerkelijk worktree-siblings bestaan.

---

## 2. `docker/yii/Dockerfile`

### 2a. ARG declaratie

**Toevoegen** voor de error logging sectie (na de PHP extensions en voor de runtime dirs):

```dockerfile
# Application root path (parameterized for worktree support)
ARG APP_ROOT=/var/www/worktree/html
```

### 2b. Error logging (regel ~81-84)

**Van:**
```dockerfile
RUN mkdir -p /var/www/html/yii/runtime/logs && \
    cat <<'EOF' > /usr/local/etc/php/conf.d/error-logging.ini
log_errors=On
error_log=/var/www/html/yii/runtime/logs/php_errors.log
EOF
```

**Naar:**
```dockerfile
RUN mkdir -p ${APP_ROOT}/yii/runtime/logs && \
    cat <<EOF > /usr/local/etc/php/conf.d/error-logging.ini
log_errors=On
error_log=${APP_ROOT}/yii/runtime/logs/php_errors.log
EOF
```

**Let op:** Heredoc verandert van `<<'EOF'` (quoted, geen variable expansion) naar `<<EOF`
(unquoted, shell expandeert `${APP_ROOT}` tijdens `RUN`). Dit is noodzakelijk omdat de
PHP ini-waarde het uiteindelijke pad moet bevatten, niet de literal string `${APP_ROOT}`.

### 2c. Chown (regel ~99)

**Van:** `chown -R $USER_NAME:$USER_NAME /var/www/html /data /home/$USER_NAME/.local`
**Naar:**
```dockerfile
RUN mkdir -p ${APP_ROOT} && \
    chown -R $USER_NAME:$USER_NAME ${APP_ROOT} /data /home/$USER_NAME/.local
```

**Let op:** `mkdir -p` is noodzakelijk omdat `/var/www/worktree/html` niet bestaat in het
base image (alleen `/var/www/html` bestaat standaard). Zonder `mkdir` faalt de `chown`.

### 2d. WORKDIR arg (regel ~102)

**Van:** `ARG WORKDIR=/var/www/html/yii`
**Naar:** `ARG WORKDIR=${APP_ROOT}/yii`

### 2e. PHPStorm symlink (regel ~113)

**Van:**
```dockerfile
RUN mkdir -p /var/www/html/yii/vendor/bin && \
    ln -s ../autoload.php /var/www/html/yii/vendor/bin/autoload.php
```

**Naar:**
```dockerfile
RUN mkdir -p ${WORKDIR}/vendor/bin && \
    ln -s ../autoload.php ${WORKDIR}/vendor/bin/autoload.php
```

Hier gebruiken we `${WORKDIR}` in plaats van `${APP_ROOT}/yii` omdat WORKDIR al correct
gedefinieerd is in 2d. Beide hardcoded paden in dit blok moeten vervangen worden.

---

## 3. `docker-compose.yml`

Drie services bevatten hardcoded paden: `pma_yii`, `pma_nginx`, `pma_queue`.

### 3a. `pma_yii` service

**Build args toevoegen:**
```yaml
build:
  args:
    - APP_ROOT=${APP_ROOT:-/var/www/worktree/html}   # NIEUW
    - USER_ID=...
```

**working_dir:**
```yaml
working_dir: ${APP_ROOT:-/var/www/worktree/html}/yii
```

**PATH in environment:**
```yaml
PATH: /home/${USER_NAME}/.local/bin:${APP_ROOT:-/var/www/worktree/html}/yii:/usr/local/sbin:...
```

**Volume mounts:**
```yaml
volumes:
  - .:${APP_ROOT:-/var/www/worktree/html}
```

### 3b. `pma_nginx` service

**Volume mounts:**
```yaml
volumes:
  - .:${APP_ROOT:-/var/www/worktree/html}
```

**Environment toevoegen:**
```yaml
environment:
  PHP_FPM_PORT: ${PHP_FPM_PORT:-9000}
  APP_ROOT: ${APP_ROOT:-/var/www/worktree/html}     # NIEUW
```

**envsubst command uitbreiden:**

```yaml
command: >
  sh -c "envsubst '$$PHP_FPM_PORT $$APP_ROOT' < /etc/nginx/nginx.conf.template \
  > /etc/nginx/nginx.conf && nginx -g 'daemon off;'"
```

`$$APP_ROOT` toevoegen aan de envsubst variabelen zodat `${APP_ROOT}` in het
nginx template gesubstitueerd wordt.

### 3c. `pma_queue` service

Identiek aan `pma_yii`:
- Build args: `APP_ROOT` toevoegen
- working_dir: parameteriseren
- PATH: parameteriseren
- Volume mounts: parameteriseren

### Fallback-patroon

Alle docker-compose referenties gebruiken fallbacks met `:-`:

| Variabele | Fallback | Toelichting |
|-----------|----------|-------------|
| `APP_ROOT` | `/var/www/worktree/html` | Container pad naar app root |

De `:-` fallback zorgt dat het werkt als de env var niet gezet is (bijv. gebruiker
heeft `.env` niet bijgewerkt). Dit is bewust — geen harde afhankelijkheid op de
env var.

**Opmerking:** Port binding op `127.0.0.1` is reeds geconfigureerd in de huidige
`docker-compose.yml`. Er is ook een Tailscale binding (`${TAILSCALE_IP}:${NGINX_PORT}:80`)
aanwezig — deze blijft ongewijzigd.

---

## 4. `docker/nginx.conf.template`

**Van:**
```nginx
root /var/www/html/yii/web;
```

**Naar:**
```nginx
root ${APP_ROOT}/yii/web;
```

`${APP_ROOT}` wordt gesubstitueerd door `envsubst` in het nginx entrypoint command
(zie 3b). Het is geen Nginx variabele maar een shell variabele die voor start
wordt ingevuld.

---

## 5. `docker/yii/codex-config.toml`

**Van:**
```toml
[projects."/var/www/html"]
trust_level = "trusted"
```

**Naar:**
```toml
[projects."/var/www/worktree/html"]
trust_level = "trusted"
```

**Opmerking:** Dit is een statisch pad, geen variabele. TOML ondersteunt geen
variabele substitutie. Het pad verandert mee met de default waarde van `APP_ROOT`.

---

## 6. `linter.sh`

**Van:**
```bash
docker exec pma_yii vendor/bin/php-cs-fixer $1 --config /var/www/html/linterConfig.php
```

**Naar:**
```bash
APP_ROOT=${APP_ROOT:-/var/www/worktree/html}
docker exec pma_yii vendor/bin/php-cs-fixer $1 --config ${APP_ROOT}/linterConfig.php
```

Het script draait op de host. `APP_ROOT` wordt gelezen uit de host-omgeving
(als de gebruiker het heeft geexporteerd of via `source .env`). Fallback op de
default waarde.

---

## 7. `linter-staged.sh`

**Van:**
```bash
CONTAINER_FILES=$(echo "$FILES" | sed "s|^|/var/www/html/|")
docker exec pma_yii vendor/bin/php-cs-fixer $1 --sequential --config /var/www/html/linterConfig.php $CONTAINER_FILES
```

**Naar:**
```bash
APP_ROOT=${APP_ROOT:-/var/www/worktree/html}
CONTAINER_FILES=$(echo "$FILES" | sed "s|^|${APP_ROOT}/|")
docker exec pma_yii vendor/bin/php-cs-fixer $1 --sequential --config ${APP_ROOT}/linterConfig.php $CONTAINER_FILES
```

---

## 8. `.vscode/launch.json`

**Van:**
```json
"pathMappings": {
    "/var/www/html": "${workspaceFolder}"
}
```

**Naar:**
```json
"pathMappings": {
    "/var/www/worktree/html": "${workspaceFolder}"
}
```

JSON ondersteunt geen variabelen. Het pad is statisch en volgt de default van `APP_ROOT`.

---

## 9. Documentatie (bulk replace)

Alle referenties naar `/var/www/html/yii` vervangen door `/var/www/worktree/html/yii`
in deze bestanden:

| Bestand | Referenties | Context |
|---------|-------------|---------|
| `CLAUDE.md` | 1 | Quick reference pad |
| `.claude/config/project.md` | 3 | Linter, tests, migrations commando's |
| `.claude/rules/testing.md` | 1 | Run all tests pad |
| `.claude/skills/onboarding.md` | 1 | Onboarding pad |
| `.claude/skills/refactor.md` | 1 | Refactor skill pad |
| `.claude/commands/analyze-codebase.md` | 1 | Analyze command pad |
| `.claude/commands/finalize-changes.md` | 2 | Finalize command paden |

**Niet wijzigen:** `.claude/design/` bestanden. Die documenteren de historische situatie
en de probleemanalyse.

---

## Implementatievolgorde

1. `.env.example` — grondslag, bepaalt de variabele
2. `docker/yii/Dockerfile` — ARG + alle interne paden
3. `docker-compose.yml` — volume mounts, workdirs, build args, nginx env
4. `docker/nginx.conf.template` — root directive
5. `docker/yii/codex-config.toml` — trust pad
6. `linter.sh` + `linter-staged.sh` — host-side scripts
7. `.vscode/launch.json` — IDE configuratie
8. Documentatie — bulk replace
9. **Verificatie** — grep, build, tests

---

## Verificatie

### Stap 1: Grep-controle

```bash
grep -r '/var/www/html' \
  --include='*.yml' --include='*.yaml' \
  --include='*.sh' --include='*.json' \
  --include='*.toml' --include='*.template' \
  --include='Dockerfile' \
  --include='*.md' \
  --exclude-dir='.claude/design' \
  .
```

Verwacht: **0 matches.**

### Stap 2: Docker rebuild

```bash
docker compose down
docker compose build --no-cache
docker compose up -d
```

### Stap 3: Padverificatie

```bash
# Working directory
docker exec pma_yii pwd
# Verwacht: /var/www/worktree/html/yii

# Nginx root
docker exec pma_nginx cat /etc/nginx/nginx.conf | grep root
# Verwacht: root /var/www/worktree/html/yii/web;

# PHP error_log
docker exec pma_yii php -i | grep error_log
# Verwacht: /var/www/worktree/html/yii/runtime/logs/php_errors.log

# Repo bestanden zichtbaar
docker exec pma_yii ls /var/www/worktree/html/yii/web/index.php
# Verwacht: bestand bestaat

# Queue worker paden
docker exec pma_queue-1 pwd
# Verwacht: /var/www/worktree/html/yii

# Port binding: alleen op localhost
docker port pma_nginx
# Verwacht: 80/tcp -> 127.0.0.1:{NGINX_PORT}
```

### Stap 4: Functionele tests

```bash
# Unit tests
docker exec pma_yii vendor/bin/codecept run unit

# Linter (vanuit host)
./linter.sh check
```

### Stap 5: Browser

Open `localhost:{NGINX_PORT}` — login pagina laadt, inloggen slaagt, projectlijst zichtbaar,
een project openen toont templates/contexts/fields.

---

## Afhankelijkheden

- **Geen code-afhankelijkheden.** Alle Yii/PHP-paden zijn relatief.
- **Gebruikersactie vereist:** Na pull moet de gebruiker:
  1. Toevoegen aan `.env` (of de default accepteren):
     - `APP_ROOT=/var/www/worktree/html`
  2. `docker compose build --no-cache && docker compose up -d`

## Rollback

Bij problemen:
1. Revert de commit — alle wijzigingen zijn in dezelfde commit, geen migraties of database-wijzigingen
2. `docker compose build --no-cache && docker compose up -d`
3. Documenteer de fout en reden van rollback in `insights.md`

De oude `.env` werkt nog met de Docker Compose fallback
(`${APP_ROOT:-/var/www/worktree/html}` valt terug op de default).
