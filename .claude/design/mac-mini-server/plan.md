# Mac Mini Server — PRD & DEV op macOS

| | |
|---|---|
| Status | Draft |
| Datum | 2026-02-07 |
| Platform | Mac Mini (macOS 13+) |

## Wat

Twee geïsoleerde Docker stacks (PRD + DEV) op één Mac Mini, bereikbaar via Tailscale VPN.

```
Mac Mini (macOS)
├── /opt/promptmanager/prd   → :8502 (productie)
├── /opt/promptmanager/dev   → :8503 (ontwikkeling)
└── /opt/promptmanager/scripts → backup & health check
```

Beide stacks gebruiken de bestaande `docker-compose.yml` + een `docker-compose.override.yml` per omgeving. Isolatie via `COMPOSE_PROJECT_NAME` en unieke poorten.

## Poorten

| Service | PRD | DEV | Binding |
|---------|-----|-----|---------|
| NGINX | 8502 | 8503 | `127.0.0.1` + Tailscale IP |
| MySQL (host) | 3307 | 3308 | `127.0.0.1` |
| PHP-FPM | 9001 | 9002 | intern |

## Vereisten

- macOS 13+, 8 GB RAM, 50 GB vrij
- Docker Desktop (`brew install --cask docker`), "Start on login" aan
- Tailscale (reeds geconfigureerd)
- `brew install git jq rclone`

---

## Stap 1: Directories & repo

```bash
sudo mkdir -p /opt/promptmanager/{prd,dev,scripts,backups}
sudo chown -R $(whoami):staff /opt/promptmanager

git clone <repository-url> /opt/promptmanager/prd
git clone <repository-url> /opt/promptmanager/dev
```

## Stap 2: PRD configureren

### .env

```bash
cd /opt/promptmanager/prd && cp .env.example .env
```

Wijzig:

```env
COMPOSE_PROJECT_NAME=prd
USER_ID=501
DB_ROOT_PASSWORD=<sterk-prd>
DB_HOST=prd_mysql
DB_PASSWORD=<sterk-prd>
DB_PORT=3307
NGINX_PORT=8502
PHP_FPM_PORT=9001
TAILSCALE_IP=<mac-mini-tailscale-ip>
XDEBUG_MODE=off
XDEBUG_START_WITH_REQUEST=no
IDENTITY_DISABLE_CAPTCHA=FALSE
INCLUDED_TEST_MODULES=[]
PROJECTS_ROOT=/Users/<username>/projects
PATH_MAPPINGS={"/Users/<username>/projects": "/projects"}
```

### docker-compose.override.yml

```yaml
services:
  pma_yii:
    container_name: prd_yii
  pma_nginx:
    container_name: prd_nginx
  pma_mysql:
    container_name: prd_mysql
  pma_npm:
    container_name: prd_npm
```

> De base `docker-compose.yml` heeft al `restart: always` en de juiste volume mounts. Override alleen container names.

### YII_ENV

Maak PRD-specifiek `index.php` zonder debug:

```bash
# Comment YII_DEBUG en YII_ENV regels uit in yii/web/index.php
```

> **TODO:** Beter: lees `YII_ENV` uit een environment variable zodat `git pull` het niet overschrijft. Eenmalige refactor.

### Starten

```bash
cd /opt/promptmanager/prd
docker compose up -d --build
docker exec prd_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
curl -s -o /dev/null -w "%{http_code}" http://localhost:8502
```

> Geen `yii_test migrate` op PRD — niet nodig.

## Stap 3: DEV configureren

### .env

```bash
cd /opt/promptmanager/dev && cp .env.example .env
```

Wijzig (verschil met PRD: **project name, poorten, xdebug aan, captcha uit**):

```env
COMPOSE_PROJECT_NAME=dev
USER_ID=501
DB_ROOT_PASSWORD=<sterk-dev>
DB_HOST=dev_mysql
DB_PASSWORD=<sterk-dev>
DB_PORT=3308
NGINX_PORT=8503
PHP_FPM_PORT=9002
TAILSCALE_IP=<mac-mini-tailscale-ip>
XDEBUG_MODE=debug,develop
XDEBUG_START_WITH_REQUEST=trigger
IDENTITY_DISABLE_CAPTCHA=TRUE
INCLUDED_TEST_MODULES=["modules/identity/tests"]
PROJECTS_ROOT=/Users/<username>/projects
PATH_MAPPINGS={"/Users/<username>/projects": "/projects"}
```

### docker-compose.override.yml

```yaml
services:
  pma_yii:
    container_name: dev_yii
  pma_nginx:
    container_name: dev_nginx
  pma_mysql:
    container_name: dev_mysql
  pma_npm:
    container_name: dev_npm
```

### Starten

```bash
cd /opt/promptmanager/dev
docker compose up -d --build
docker exec dev_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec dev_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
curl -s -o /dev/null -w "%{http_code}" http://localhost:8503
```

## Stap 4: Data migratie

Vanaf de canonieke bron:

```bash
# Export
docker exec -e MYSQL_PWD="$DB_PASSWORD" pma_mysql mysqldump \
    -u"$DB_USER" --single-transaction --routines --triggers \
    promptmanager | gzip > /tmp/promptmanager.sql.gz

# Kopieer naar Mac Mini
scp /tmp/promptmanager.sql.gz <user>@<tailscale-ip>:/tmp/

# Import in PRD
gunzip -c /tmp/promptmanager.sql.gz | \
    docker exec -i prd_mysql mysql -u root -p"<PRD_ROOT_PASSWORD>" promptmanager
```

## Stap 5: Backups

### backup-db.sh aanpassen

Het bestaande script hardcodet `pma_mysql`. Maak de container name configureerbaar:

```bash
# In backup-db.sh, vervang:
#   docker exec -e MYSQL_PWD="$DB_PASSWORD" pma_mysql mysqldump ...
# Door:
MYSQL_CONTAINER="${MYSQL_CONTAINER:-pma_mysql}"
docker exec -e MYSQL_PWD="$DB_PASSWORD" "$MYSQL_CONTAINER" mysqldump ...
```

Zelfde fix in `restore-db.sh`.

### launchd backup (dagelijks 02:00)

`~/Library/LaunchAgents/com.promptmanager.backup.plist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN"
  "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.promptmanager.backup</string>
    <key>ProgramArguments</key>
    <array>
        <string>/opt/promptmanager/prd/scripts/backup-db.sh</string>
    </array>
    <key>StartCalendarInterval</key>
    <dict>
        <key>Hour</key>
        <integer>2</integer>
        <key>Minute</key>
        <integer>0</integer>
    </dict>
    <key>StandardOutPath</key>
    <string>/opt/promptmanager/backups/backup.log</string>
    <key>StandardErrorPath</key>
    <string>/opt/promptmanager/backups/backup-error.log</string>
    <key>EnvironmentVariables</key>
    <dict>
        <key>PATH</key>
        <string>/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin</string>
        <key>MYSQL_CONTAINER</key>
        <string>prd_mysql</string>
    </dict>
</dict>
</plist>
```

```bash
launchctl load ~/Library/LaunchAgents/com.promptmanager.backup.plist
```

> rclone config: kopieer bestaande `~/.config/rclone/rclone.conf` naar Mac Mini.

## Stap 6: Health check

`/opt/promptmanager/scripts/healthcheck.sh`:

```bash
#!/bin/bash
# Geen set -e: we willen alle checks doorlopen, ook bij failures
ERRORS=0

check() {
    if eval "$1" &>/dev/null; then
        echo "[$(date '+%H:%M:%S')] OK: $2"
    else
        echo "[$(date '+%H:%M:%S')] FAIL: $2"
        ERRORS=$((ERRORS + 1))
    fi
}

check "docker info" "Docker"
check "tailscale status" "Tailscale"

for c in prd_yii prd_nginx prd_mysql dev_yii dev_nginx dev_mysql; do
    check "docker ps --format '{{.Names}}' | grep -q '^${c}$'" "$c"
done

for port in 8502 8503; do
    check "curl -sf -o /dev/null http://localhost:${port}" "HTTP :${port}"
done

DISK=$(df -h / | awk 'NR==2{print $5}' | tr -d '%')
[ "$DISK" -gt 90 ] && echo "[$(date '+%H:%M:%S')] WARN: Disk ${DISK}%" && ERRORS=$((ERRORS + 1))

echo "[$(date '+%H:%M:%S')] Done: ${ERRORS} errors"
exit $ERRORS
```

launchd plist: zelfde structuur als backup, maar met `<key>StartInterval</key><integer>300</integer>`.

## Stap 7: macOS hardening

```bash
# Geen slaapstand
sudo pmset -a sleep 0 disksleep 0 displaysleep 0

# Herstart na stroomuitval
sudo pmset -a autorestart 1

# Firewall aan
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --setglobalstate on
```

Docker Desktop → Settings → "Start Docker Desktop when you sign in" → Aan.

## Stap 8: Deployment

```bash
# PRD
cd /opt/promptmanager/prd && git pull origin main
docker compose up -d --build pma_yii pma_nginx
docker exec prd_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0

# DEV
cd /opt/promptmanager/dev && git pull origin <branch>
docker compose up -d --build pma_yii pma_nginx
docker exec dev_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
```

---

## Bekende issues

| Issue | Actie |
|-------|-------|
| `backup-db.sh` / `restore-db.sh` hardcoden `pma_mysql` | Maak container name configureerbaar via `MYSQL_CONTAINER` env var |
| Docker Desktop for Mac bindt niet aan specifieke host IPs zoals Linux | Valideer of `TAILSCALE_IP:port` binding werkt; zo niet, bind `0.0.0.0` en vertrouw op Tailscale ACLs |
| `yii/web/index.php` YII_DEBUG wordt overschreven bij `git pull` | Refactor naar env var check |
| Health check logt zonder rotatie | Voeg newsyslog config toe of truncate in script |

## Open vragen

| Vraag | Impact |
|-------|--------|
| Git repository URL? | Clone commando |
| macOS versie op Mac Mini? | Compatibiliteit |
| rclone al geconfigureerd? | Backup setup |
