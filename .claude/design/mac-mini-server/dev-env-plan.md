# Mac Mini Server — DEV Environment Setup

| | |
|---|---|
| Status | Draft |
| Date | 2026-02-12 |
| Platform | Mac Mini (macOS) |
| Scope | DEV environment only |
| Executor | Claude Opus 4.6 (via Claude Code CLI) |

## Goal

Set up a single DEV Docker stack on a Mac Mini, accessible via Tailscale VPN.

```
Mac Mini (macOS)
└── /opt/promptmanager/dev   → :8503 (development)
```

## Starting Point

| Item | Status |
|------|--------|
| `/opt/promptmanager/dev` | Created |
| Git (SSH) | Working |
| Repo cloned | `git@github.com:efhsg/promptmanager.git` → `/opt/promptmanager/dev` |
| Docker / Docker Compose | NOT installed |
| Tailscale | Assumed configured and running |
| Homebrew | Unknown — check first |

## Target Architecture

```
Docker Compose stack (COMPOSE_PROFILES=local)
├── pma_yii    — PHP 8.2-FPM    (custom build, port 9001 internal)
├── pma_nginx  — nginx:latest    (port 8503 → 127.0.0.1 + Tailscale IP)
├── pma_mysql  — mysql:8.0       (port 3307 → 127.0.0.1)
└── pma_npm    — node:20-slim    (runs build-and-minify, then exits)
```

## Port Allocation

| Service | Host Port | Binding | Container Port |
|---------|-----------|---------|----------------|
| NGINX | 8503 | `127.0.0.1` + Tailscale IP | 80 |
| MySQL | 3307 | `127.0.0.1` | 3306 |
| PHP-FPM | 9001 | internal only | 9001 |
| Xdebug | 9003 | outbound to host | — |

---

## Step 1 — Install Homebrew

> Skip if `brew --version` succeeds.

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

On Apple Silicon (M-series), Homebrew installs to `/opt/homebrew`. Add to PATH:

```bash
echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
eval "$(/opt/homebrew/bin/brew shellenv)"
```

On Intel Macs, Homebrew installs to `/usr/local` and is already in PATH.

**Verify:**

```bash
brew --version
```

## Step 2 — Install Docker Desktop

```bash
brew install --cask docker
```

Post-install:

1. Open Docker Desktop from Applications (Spotlight: `Cmd+Space` → "Docker")
2. Accept the license agreement
3. Complete the setup wizard — choose "Use recommended settings"
4. Wait for Docker Desktop to finish starting (whale icon in menu bar stops animating)
5. Configure auto-start: Docker Desktop → Settings → General → **"Start Docker Desktop when you sign in"** → On

**Verify:**

```bash
docker --version          # e.g. Docker version 27.x.x
docker compose version    # e.g. Docker Compose version v2.x.x
docker info               # Should show server info without errors
```

## Step 3 — Install host utilities

```bash
brew install jq rclone
```

| Tool | Purpose |
|------|---------|
| `jq` | JSON processing — useful on host for debugging (also baked into PHP container) |
| `rclone` | Cloud storage CLI — used by `scripts/backup-db.sh` for Google Drive backups |

**Verify:**

```bash
jq --version
rclone --version
```

## Step 4 — Determine macOS user ID

The PHP container creates a non-root user with a matching UID to avoid permission conflicts on mounted volumes.

```bash
id -u
```

Typical values: `501` (first macOS user), `502` (second user). Note this value for Step 5.

## Step 5 — Create `.env`

```bash
cd /opt/promptmanager/dev
cp .env.example .env
```

Edit `.env` — replace placeholder values marked with `<...>`:

```env
# --- User ---
USER_ID=<result-of-id-u>
USER_NAME=appuser
TIMEZONE=Europe/Amsterdam

# --- Database ---
DB_ROOT_PASSWORD=<generate: openssl rand -base64 24>
DB_HOST=pma_mysql
DB_DATABASE=promptmanager
DB_DATABASE_TEST=promptmanager_test
DB_USER=promptmanager
DB_PASSWORD=<generate: openssl rand -base64 24>
DB_APP_PORT=3306
COMPOSE_PROFILES=local

# --- Ports ---
NGINX_PORT=8503
DB_PORT=3307
PHP_FPM_PORT=9001

# --- Xdebug (enabled for DEV) ---
XDEBUG_MODE=debug,develop
XDEBUG_START_WITH_REQUEST=trigger
XDEBUG_CLIENT_HOST=host.docker.internal
XDEBUG_PORT=9003

# --- Auth ---
IDENTITY_DISABLE_CAPTCHA=TRUE

# --- Tests ---
INCLUDED_TEST_MODULES=["modules/identity/tests"]

# --- Tailscale ---
# NGINX binds to this IP — container won't start if invalid.
# Find IP: tailscale ip -4
TAILSCALE_IP=<mac-mini-tailscale-ip>

# --- YouTube Transcript Import ---
YTX_PATH=./ytx
YTX_PYTHON_PATH=/usr/bin/python3
YTX_SCRIPT_PATH=/opt/ytx/ytx.py

# --- Claude CLI Integration ---
PROJECTS_ROOT=/Users/<username>/projects
PATH_MAPPINGS={"/Users/<username>/projects": "/projects"}
```

**Placeholders to replace:**

| Placeholder | How to get the value |
|-------------|----------------------|
| `<result-of-id-u>` | Output of `id -u` (Step 4) |
| `<generate: openssl rand -base64 24>` | Run that command twice, use different passwords for root and user |
| `<mac-mini-tailscale-ip>` | Run `tailscale ip -4` |
| `<username>` | Your macOS username (`whoami`) |

## Step 6 — Prepare host directories for volume mounts

`docker-compose.yml` mounts several host paths into the PHP container. These must exist before `docker compose up`:

```bash
# Required by YTX_PATH volume mount
mkdir -p /opt/promptmanager/dev/ytx

# Projects directory (Claude CLI integration)
mkdir -p /Users/<username>/projects

# Claude CLI config
mkdir -p ~/.claude
mkdir -p ~/.claude-config
[ -f ~/.claude-config/.claude.json ] || echo '{}' > ~/.claude-config/.claude.json

# GitHub CLI config directory
mkdir -p ~/.config/gh
```

**Verify critical mounts exist:**

```bash
# SSH keys (mounted read-only into container)
ls ~/.ssh/id_ed25519 2>/dev/null || ls ~/.ssh/id_rsa 2>/dev/null
# Must have at least one — git SSH won't work in container without it

# Git config (mounted read-only into container)
ls ~/.gitconfig 2>/dev/null
# If missing, create minimal: git config --global user.name "Name" && git config --global user.email "email"
```

## Step 7 — Build and start the stack

```bash
cd /opt/promptmanager/dev
docker compose up -d --build
```

**What happens:**

1. Builds `pma_yii` image — PHP 8.2-FPM with extensions (pdo_mysql, gd, xdebug, imagick, etc.), Composer, Node.js 20, GitHub CLI. Takes 5-10 minutes on first build.
2. Builds `pma_npm` image — Node 20-slim for frontend asset compilation.
3. Pulls `nginx:latest` and `mysql:8.0` from Docker Hub.
4. Starts all four containers. `pma_npm` runs `npm run build-and-minify` and exits.
5. MySQL init script (`docker/init-scripts/init-databases.sh`) creates the test database and grants privileges on first start.

**Verify:**

```bash
docker compose ps
```

| Container | Expected Status |
|-----------|----------------|
| `pma_yii` | `running` |
| `pma_nginx` | `running` |
| `pma_mysql` | `running` |
| `pma_npm` | `exited (0)` — normal, it's a one-shot build |

If any container is not running, check logs:

```bash
docker compose logs <service-name>
```

## Step 8 — Run database migrations

```bash
# Application schema (promptmanager)
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0

# Test schema (promptmanager_test)
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
```

Both commands should end with `Migrated up successfully`.

## Step 9 — Verify the application

**From the Mac Mini itself:**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8503
# Expected: 200 or 302 (redirect to login)
```

**From another device on the Tailscale network:**

```bash
curl -s -o /dev/null -w "%{http_code}" http://<mac-mini-tailscale-ip>:8503
```

**In a browser:** `http://localhost:8503` or `http://<mac-mini-tailscale-ip>:8503`

## Step 10 — Run tests

```bash
docker exec pma_yii vendor/bin/codecept run unit
```

All tests should pass. This validates that the application, database, test database, and PHP extensions are all working correctly.

## Step 11 — Register a user

With `IDENTITY_DISABLE_CAPTCHA=TRUE`, registration works without CAPTCHA:

1. Open `http://localhost:8503` in a browser
2. Click "Sign Up"
3. Fill in username, email, password
4. Log in with the new account

---

## Post-Setup: macOS Hardening

Prevent the Mac Mini from sleeping or failing to recover from power loss:

```bash
# Disable all sleep modes
sudo pmset -a sleep 0 disksleep 0 displaysleep 0

# Auto-restart after power failure
sudo pmset -a autorestart 1

# Enable macOS firewall
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --setglobalstate on
```

## Post-Setup: Data Migration (optional)

Import an existing database from another machine:

```bash
# On SOURCE machine — export
docker exec -e MYSQL_PWD="<source-db-password>" pma_mysql mysqldump \
    -u promptmanager --single-transaction --routines --triggers \
    promptmanager | gzip > /tmp/promptmanager.sql.gz

# Copy to Mac Mini
scp /tmp/promptmanager.sql.gz <user>@<mac-mini-tailscale-ip>:/tmp/

# On MAC MINI — import
gunzip -c /tmp/promptmanager.sql.gz | \
    docker exec -i -e MYSQL_PWD="<dev-db-password>" pma_mysql mysql \
    -u promptmanager promptmanager
```

## Post-Setup: Automated Backups (optional)

### 1. Configure rclone

```bash
rclone config
# Add remote named "gdrive" following the interactive wizard
# Or copy existing ~/.config/rclone/rclone.conf from another machine
```

### 2. Test manual backup

```bash
cd /opt/promptmanager/dev && ./scripts/backup-db.sh
```

### 3. Schedule daily backup via launchd

Create `~/Library/LaunchAgents/com.promptmanager.backup.plist`:

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
        <string>/opt/promptmanager/dev/scripts/backup-db.sh</string>
    </array>
    <key>StartCalendarInterval</key>
    <dict>
        <key>Hour</key>
        <integer>2</integer>
        <key>Minute</key>
        <integer>0</integer>
    </dict>
    <key>StandardOutPath</key>
    <string>/opt/promptmanager/dev/data/backup.log</string>
    <key>StandardErrorPath</key>
    <string>/opt/promptmanager/dev/data/backup-error.log</string>
    <key>EnvironmentVariables</key>
    <dict>
        <key>PATH</key>
        <string>/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin</string>
    </dict>
</dict>
</plist>
```

```bash
launchctl load ~/Library/LaunchAgents/com.promptmanager.backup.plist
```

## Post-Setup: Deployment Workflow

To update after pushing changes:

```bash
cd /opt/promptmanager/dev
git pull origin <branch>
docker compose up -d --build pma_yii pma_nginx
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
```

---

## Troubleshooting

### Docker Desktop won't start

```bash
ls /var/run/docker.sock    # Check if Docker socket exists
```

If not present, open Docker Desktop manually. Last resort: Docker Desktop → Troubleshoot → Reset to factory defaults.

### NGINX container fails to start

Most likely cause: invalid `TAILSCALE_IP` in `.env`.

```bash
docker compose logs pma_nginx    # Check error message
tailscale ip -4                  # Get correct IP
```

Update `TAILSCALE_IP` in `.env`, then restart:

```bash
docker compose up -d pma_nginx
```

> Note: Docker Desktop for Mac handles IP binding differently from Linux. If Tailscale IP binding doesn't work, this is a known issue — may need to bind `0.0.0.0` and rely on Tailscale ACLs instead.

### Permission errors on mounted volumes

UID mismatch between host and container user:

```bash
id -u                     # Host UID
grep USER_ID .env         # Container UID — must match
```

Update `USER_ID` in `.env` and rebuild: `docker compose up -d --build pma_yii`

### MySQL test database not created

The init script (`docker/init-scripts/init-databases.sh`) only runs when the MySQL data directory is empty (first start). If MySQL was previously initialized without the test DB:

```bash
# WARNING: destroys all database data
rm -rf /opt/promptmanager/dev/data/db/mysql
docker compose down pma_mysql
docker compose up -d pma_mysql
```

### Container can't resolve host.docker.internal

Handled by `extra_hosts` in `docker-compose.yml`. Verify:

```bash
docker exec pma_yii ping -c1 host.docker.internal
```

### Composer/npm dependencies missing after clone

The `pma_yii` Dockerfile runs `composer install` during build. If packages are missing:

```bash
docker exec pma_yii composer install
```

For frontend assets, re-run the npm build:

```bash
docker compose run --rm pma_npm
```

---

## Checklist

### Core Setup
- [ ] Homebrew installed and in PATH
- [ ] Docker Desktop installed, running, auto-start enabled
- [ ] `jq` and `rclone` installed via Homebrew
- [ ] `.env` created with all placeholders replaced
- [ ] `ytx/` directory created
- [ ] Host directories exist (`~/.claude`, `~/.claude-config`, `~/.config/gh`)
- [ ] SSH keys and `.gitconfig` present on host

### Stack Running
- [ ] `docker compose up -d --build` completes without errors
- [ ] `pma_yii` running
- [ ] `pma_nginx` running
- [ ] `pma_mysql` running
- [ ] `pma_npm` exited with code 0

### Application Working
- [ ] App schema migrated (`yii migrate`)
- [ ] Test schema migrated (`yii_test migrate`)
- [ ] `http://localhost:8503` responds (200 or 302)
- [ ] Tailscale access works from remote device
- [ ] All unit tests pass (`codecept run unit`)
- [ ] User registered and logged in

### Hardening (post-setup)
- [ ] Sleep disabled (`pmset`)
- [ ] Auto-restart on power failure enabled
- [ ] macOS firewall enabled

### Optional
- [ ] Data migrated from existing database
- [ ] rclone configured for Google Drive
- [ ] Automated daily backup via launchd
