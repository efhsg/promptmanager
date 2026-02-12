# Mac Mini Server — DEV Environment Setup

| | |
|---|---|
| Status | Draft |
| Date | 2026-02-12 |
| Platform | Mac Mini (macOS) |
| Scope | DEV environment only |

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
| Repo cloned | `git@github.com:efhsg/promptmanager.git` pulled into `/opt/promptmanager/dev` |
| Docker / Docker Compose | NOT installed |
| Tailscale | Assumed configured |

## Ports

| Service | Port | Binding |
|---------|------|---------|
| NGINX | 8503 | `127.0.0.1` + Tailscale IP |
| MySQL (host) | 3307 | `127.0.0.1` |
| PHP-FPM | 9001 | internal (container-to-container) |

---

## Step 1 — Install Homebrew (if missing)

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

After install, follow the printed instructions to add Homebrew to your PATH. On Apple Silicon:

```bash
echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
eval "$(/opt/homebrew/bin/brew shellenv)"
```

Verify:

```bash
brew --version
```

## Step 2 — Install Docker Desktop

```bash
brew install --cask docker
```

After install:

1. Open Docker Desktop from Applications (or Spotlight: `Cmd+Space` → "Docker")
2. Accept the license agreement and complete the setup wizard
3. Wait for Docker Desktop to finish starting (whale icon in menu bar stops animating)
4. Enable auto-start: Docker Desktop → Settings → General → **"Start Docker Desktop when you sign in"** → On

Verify:

```bash
docker --version
docker compose version
docker info
```

All three commands should succeed without errors.

## Step 3 — Install required tools

```bash
brew install jq rclone
```

- `jq` — used inside the PHP container (already baked into the Dockerfile), but useful on the host for debugging
- `rclone` — used by `scripts/backup-db.sh` to upload backups to Google Drive

Verify:

```bash
jq --version
rclone --version
```

## Step 4 — Determine macOS user ID

Docker containers run as a non-root user with a matching UID. Find your macOS UID:

```bash
id -u
```

On macOS, the first user is typically `501`. Note this value for the `.env` file.

## Step 5 — Create the `.env` file

```bash
cd /opt/promptmanager/dev
cp .env.example .env
```

Edit `.env` with these values:

```env
USER_ID=501
USER_NAME=appuser
TIMEZONE=Europe/Amsterdam

# Database
DB_ROOT_PASSWORD=<generate-strong-password>
DB_HOST=pma_mysql
DB_DATABASE=promptmanager
DB_DATABASE_TEST=promptmanager_test
DB_USER=promptmanager
DB_PASSWORD=<generate-strong-password>
DB_APP_PORT=3306
COMPOSE_PROFILES=local

# Ports
NGINX_PORT=8503
DB_PORT=3307
PHP_FPM_PORT=9001

# Xdebug — enabled for DEV
XDEBUG_MODE=debug,develop
XDEBUG_START_WITH_REQUEST=trigger
XDEBUG_CLIENT_HOST=host.docker.internal
XDEBUG_PORT=9003

# Auth
IDENTITY_DISABLE_CAPTCHA=TRUE

# Tests
INCLUDED_TEST_MODULES=["modules/identity/tests"]

# Tailscale — NGINX binds to this IP, container won't start if invalid
# Find your IP with: tailscale ip -4
TAILSCALE_IP=<mac-mini-tailscale-ip>

# YouTube Transcript Import (keep defaults unless you use this feature)
YTX_PATH=./ytx
YTX_PYTHON_PATH=/usr/bin/python3
YTX_SCRIPT_PATH=/opt/ytx/ytx.py

# Claude CLI Integration
# Absolute host path to projects directory (mounted as /projects in container)
PROJECTS_ROOT=/Users/<username>/projects
PATH_MAPPINGS={"/Users/<username>/projects": "/projects"}
```

> Replace `<username>` with your macOS username. Replace `<generate-strong-password>` with actual strong passwords (use `openssl rand -base64 24` to generate).

### Optional: create the ytx directory if it doesn't exist

The `YTX_PATH=./ytx` volume mount will fail if the directory doesn't exist:

```bash
mkdir -p /opt/promptmanager/dev/ytx
```

## Step 6 — Prepare host volume mounts

The `docker-compose.yml` mounts several host directories into the PHP container. These must exist before starting:

```bash
# Projects directory (for Claude CLI integration)
mkdir -p /Users/<username>/projects

# Claude CLI config (if not already present)
mkdir -p ~/.claude
touch ~/.claude.json

# Local bins and share (if not already present)
mkdir -p ~/.local/bin
mkdir -p ~/.local/share

# GitHub CLI config (if not already present)
mkdir -p ~/.config/gh
```

Verify SSH and git config exist (these are mounted read-only):

```bash
ls ~/.ssh/id_ed25519 || ls ~/.ssh/id_rsa   # At least one SSH key
ls ~/.gitconfig                             # Git config
```

## Step 7 — Build and start the stack

```bash
cd /opt/promptmanager/dev
docker compose up -d --build
```

This builds and starts four containers:

| Container | Image | Purpose |
|-----------|-------|---------|
| `pma_yii` | Custom (PHP 8.2-FPM) | PHP application server |
| `pma_nginx` | nginx:latest | Web server, reverse proxy |
| `pma_mysql` | mysql:8.0 | Database (via `local` profile) |
| `pma_npm` | Custom (Node 20) | Frontend build (runs once, then exits) |

The first build takes several minutes (PHP extensions, Composer, Node.js install).

Verify all services are running:

```bash
docker compose ps
```

Expected: `pma_yii`, `pma_nginx`, `pma_mysql` should show `running`. `pma_npm` will show `exited (0)` — this is normal (it runs the build and stops).

## Step 8 — Run database migrations

```bash
# Application schema
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0

# Test schema
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
```

Both should complete with `Migrated up successfully`.

## Step 9 — Verify the application

```bash
# HTTP health check
curl -s -o /dev/null -w "%{http_code}" http://localhost:8503
# Expected: 200 (or 302 redirect to login)

# Tailscale access (from another Tailscale device)
curl -s -o /dev/null -w "%{http_code}" http://<mac-mini-tailscale-ip>:8503
```

Open in browser: `http://localhost:8503` or `http://<mac-mini-tailscale-ip>:8503`

## Step 10 — Run tests

```bash
docker exec pma_yii vendor/bin/codecept run unit
```

All tests should pass. This confirms the application, database, and test database are all working correctly.

## Step 11 — Register a user

The application requires authentication. With `IDENTITY_DISABLE_CAPTCHA=TRUE`, you can register directly through the web interface:

1. Open `http://localhost:8503`
2. Click "Sign Up" / register
3. Fill in email, username, password
4. Log in

---

## Post-Setup: macOS Hardening

These settings ensure the Mac Mini stays running as a server:

```bash
# Disable sleep (display, disk, system)
sudo pmset -a sleep 0 disksleep 0 displaysleep 0

# Auto-restart after power failure
sudo pmset -a autorestart 1

# Enable firewall
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --setglobalstate on
```

## Post-Setup: Data Migration (optional)

If you have an existing database to import:

```bash
# On the source machine — export
docker exec -e MYSQL_PWD="<source-db-password>" pma_mysql mysqldump \
    -u promptmanager --single-transaction --routines --triggers \
    promptmanager | gzip > /tmp/promptmanager.sql.gz

# Copy to Mac Mini via Tailscale
scp /tmp/promptmanager.sql.gz <user>@<mac-mini-tailscale-ip>:/tmp/

# On Mac Mini — import
gunzip -c /tmp/promptmanager.sql.gz | \
    docker exec -i -e MYSQL_PWD="<dev-db-password>" pma_mysql mysql \
    -u promptmanager promptmanager
```

## Post-Setup: Backups (optional)

The existing `scripts/backup-db.sh` uploads database dumps to Google Drive via `rclone`.

### Configure rclone for Google Drive

```bash
rclone config
# Follow the interactive wizard to add a remote named "gdrive"
# Or copy an existing ~/.config/rclone/rclone.conf from another machine
```

### Test a manual backup

```bash
cd /opt/promptmanager/dev
./scripts/backup-db.sh
```

### Automate with launchd (daily at 02:00)

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

To update the DEV environment after pushing changes:

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
# Check if Docker socket exists
ls /var/run/docker.sock

# Reset Docker Desktop (last resort)
# Docker Desktop → Troubleshoot → Reset to factory defaults
```

### NGINX fails to start (Tailscale IP binding)

Docker Desktop for Mac may not bind to specific host IPs the same way Linux does. If `pma_nginx` fails to start:

1. Check logs: `docker compose logs pma_nginx`
2. Verify Tailscale IP: `tailscale ip -4`
3. If binding fails, the `TAILSCALE_IP` in `.env` may be incorrect or Tailscale may not be running

### Container can't resolve host.docker.internal

This is handled by `extra_hosts` in `docker-compose.yml`. If it still fails:

```bash
# Verify from inside the container
docker exec pma_yii ping -c1 host.docker.internal
```

### Permission errors on mounted volumes

macOS UID mismatch. Verify `USER_ID` in `.env` matches your macOS UID:

```bash
id -u   # Should match USER_ID in .env
```

### MySQL init script doesn't run

The init script in `docker/init-scripts/` only runs on first container start (empty data directory). If MySQL was already initialized:

```bash
# Remove data and restart (destroys all data!)
rm -rf /opt/promptmanager/dev/data/db/mysql
docker compose up -d --build pma_mysql
```

---

## Checklist

- [ ] Homebrew installed
- [ ] Docker Desktop installed and auto-start enabled
- [ ] `jq` and `rclone` installed
- [ ] `.env` created with correct values (USER_ID, passwords, TAILSCALE_IP, PROJECTS_ROOT)
- [ ] Host directories exist for volume mounts (~/.claude, ~/.ssh, etc.)
- [ ] `docker compose up -d --build` succeeds
- [ ] All containers running (`docker compose ps`)
- [ ] Migrations run on both schemas
- [ ] `http://localhost:8503` returns 200/302
- [ ] Tailscale access works from remote device
- [ ] Tests pass (`codecept run unit`)
- [ ] User registered and can log in
- [ ] macOS hardening applied (sleep, autorestart, firewall)
- [ ] Backups configured (optional)
