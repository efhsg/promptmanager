# Technisch Ontwerp: Tailscale + Centrale Webapplicatie voor PromptManager

## 1) Document Metadata

| Eigenschap | Waarde |
|------------|--------|
| Versie | 2.5 |
| Status | Draft |
| FO Versie | 2.2 |
| Laatste update | 2026-01-19 |
| Eigenaar | Development Team |

---

## 2) Genomen Besluiten

De volgende besluitpunten uit het FO zijn definitief afgesloten:

| ID | Besluit | Gekozen optie | Rationale | Datum |
|----|---------|---------------|-----------|-------|
| D1 | Server firewall configuratie | **Optie A: Alleen Tailscale interface + Docker hardening** | Minimale attack surface; HTTP (poort 8080) is alleen bereikbaar via Tailscale tunnel. Docker hardening via IP binding of DOCKER-USER chain vereist omdat UFW alleen niet voldoende is. | 2026-01-19 |
| D2 | Tailscale ACLs | **Default ACLs (geen custom configuratie)** | Voor 1-2 machines onder dezelfde account is default (all-to-all) voldoende. Custom ACLs introduceren onnodige beheerlast zonder meerwaarde. | 2026-01-18 |
| D3 | Canonieke bron selectie | **Machine met hoogste `MAX(updated_at)` over kernentiteiten** | Objectief meetbaar criterium; zie sectie 6.1 voor exacte query en procedure. Bij gelijke timestamps: machine met meeste records. | 2026-01-18 |

### Eerder Genomen Besluiten (uit FO)

| ID | Besluit | Rationale | Datum | Status |
|----|---------|-----------|-------|--------|
| B1 | Centrale webapplicatie als enige toegangspunt na cut-over | Voorkomt data-inconsistentie | 2026-01-18 | **Definitief** |
| B2 | Geen fallback naar lokale installatie | Eenvoud, dev-only scenario | 2026-01-18 | **Definitief** |
| B3 | Cron-based backup (geen real-time replicatie) | Voldoende voor 1 user | 2026-01-18 | **Definitief** |
| B4 | Last-write-wins bij concurrency | Standaard MySQL gedrag; awareness is mitigatie | 2026-01-18 | **Definitief** |

---

## 3) Prerequisites

### Software Versies

| Component | Minimum Versie | Aanbevolen Versie | Verificatie Commando |
|-----------|---------------|-------------------|---------------------|
| **Server** |
| Ubuntu Server | 22.04 LTS | 24.04 LTS | `lsb_release -a` |
| Docker | 24.0.0 | 25.0+ | `docker --version` |
| Docker Compose | 2.20.0 | 2.24+ | `docker compose version` |
| Tailscale | 1.50.0 | Latest | `tailscale version` |
| jq | 1.6 | 1.7 | `jq --version` |
| curl | 7.81.0 | Latest | `curl --version` |
| gzip | 1.10 | Latest | `gzip --version` |
| **Client** |
| Tailscale | 1.50.0 | Latest | `tailscale version` |
| Webbrowser | Modern (Chrome/Firefox/Safari) | Latest | N/A |

### Dependencies per Machine

**Server (ubuntu-server):**
```bash
# Vereiste packages
docker             # Container runtime
docker-compose     # Container orchestratie (of docker compose plugin)
tailscale          # VPN daemon
ufw                # Firewall
jq                 # JSON parser (voor health checks)
gzip               # Backup compressie
coreutils          # sha256sum, stat, etc.
git                # Repository clone
```

**Client (laptop/desktop):**
```bash
# Vereiste packages
tailscale          # VPN daemon

# Geen Docker, geen MySQL client, geen code vereist
# Clients gebruiken alleen een webbrowser
```

### Hardware/Netwerk Vereisten

| Resource | Server | Client | Verificatie |
|----------|--------|--------|-------------|
| RAM | >= 2GB vrij | >= 1GB vrij | `free -h` |
| Disk | >= 10GB vrij | N/A | `df -h` |
| Network | Stabiele internet | Stabiele internet | `ping 8.8.8.8` |
| Tailscale latency | N/A | < 100ms naar server | `tailscale ping ubuntu-server` |

### Benodigde Credentials/Accounts

| Item | Waar aan te maken | Doel |
|------|-------------------|------|
| Tailscale account | https://login.tailscale.com | VPN beheer |
| PromptManager .env | Kopieer van bestaande installatie of genereer | Docker environment |
| SSH key (optioneel) | `ssh-keygen` | Server toegang |

---

## 4) Server Configuratie

### 4.1 Tailscale Installatie

```bash
#!/bin/bash
# === TAILSCALE INSTALLATIE (SERVER) ===
# Bestand: /tmp/install-tailscale-server.sh
# Uitvoeren als: sudo bash /tmp/install-tailscale-server.sh

set -euo pipefail

echo "=== Tailscale Server Installatie ==="

# Stap 1: Installeer Tailscale
if ! command -v tailscale &> /dev/null; then
    echo "[1/4] Tailscale installeren..."
    curl -fsSL https://tailscale.com/install.sh | sh
else
    echo "[1/4] Tailscale al geïnstalleerd: $(tailscale version)"
fi

# Stap 2: Start en authenticeer Tailscale
echo "[2/4] Tailscale starten..."
if ! tailscale status &> /dev/null; then
    sudo tailscale up
    echo "    Browser opent voor authenticatie. Wacht tot voltooid..."
fi

# Stap 3: Verkrijg en toon IP
echo "[3/4] Tailscale IP ophalen..."
TAILSCALE_IP=$(tailscale ip -4)
echo "    Tailscale IP: ${TAILSCALE_IP}"

# Stap 4: Toon status
echo "[4/4] Tailscale status:"
tailscale status

# Validatie output
NGINX_PORT="${NGINX_PORT:-8080}"
echo ""
echo "=== VALIDATIE ==="
echo "Tailscale IP: ${TAILSCALE_IP}"
echo "Hostname: $(tailscale status --self --json | jq -r '.Self.HostName')"
echo ""
echo "Clients kunnen verbinden via: http://${TAILSCALE_IP}:${NGINX_PORT}"
echo "Of via MagicDNS: http://$(hostname):${NGINX_PORT}"

# Log naar syslog
logger -t tailscale-setup "Tailscale installed and configured. IP: ${TAILSCALE_IP}"
```

**Verificatie:**
```bash
tailscale status
# Verwacht: ubuntu-server   100.64.x.x   linux   -

tailscale ip -4
# Verwacht: 100.64.x.x
```

### 4.2 Docker Installatie

```bash
#!/bin/bash
# === DOCKER INSTALLATIE (SERVER) ===
# Bestand: /tmp/install-docker.sh
# Uitvoeren als: sudo bash /tmp/install-docker.sh

set -euo pipefail

echo "=== Docker Installatie ==="

# Stap 1: Installeer Docker
if ! command -v docker &> /dev/null; then
    echo "[1/3] Docker installeren..."
    curl -fsSL https://get.docker.com | sh
else
    echo "[1/3] Docker al geïnstalleerd: $(docker --version)"
fi

# Stap 2: Voeg huidige gebruiker toe aan docker groep
echo "[2/3] Gebruiker toevoegen aan docker groep..."
usermod -aG docker $SUDO_USER || usermod -aG docker $USER

# Stap 3: Start Docker service
echo "[3/3] Docker service starten..."
systemctl enable docker
systemctl start docker

# Validatie
echo ""
echo "=== VALIDATIE ==="
docker --version
docker compose version

echo ""
echo "Log uit en opnieuw in om docker zonder sudo te gebruiken."
```

### 4.3 PromptManager Installatie

```bash
#!/bin/bash
# === PROMPTMANAGER INSTALLATIE (SERVER) ===
# Bestand: /tmp/install-promptmanager.sh
# Uitvoeren als: bash /tmp/install-promptmanager.sh

set -euo pipefail

INSTALL_DIR="/opt/promptmanager"

echo "=== PromptManager Installatie ==="

# Stap 1: Clone of kopieer repository
echo "[1/5] Repository klonen..."
if [ -d "${INSTALL_DIR}" ]; then
    echo "    Directory bestaat al, git pull uitvoeren..."
    cd "${INSTALL_DIR}"
    git pull
else
    # Optie A: Clone van repository
    # git clone <repository-url> "${INSTALL_DIR}"

    # Optie B: Kopieer van lokale installatie (via scp)
    echo "    Kopieer repository naar ${INSTALL_DIR} via scp:"
    echo "    scp -r /path/to/promptmanager ubuntu-server:/opt/"
    exit 1
fi

cd "${INSTALL_DIR}"

# Stap 2: Configureer .env
echo "[2/5] Environment configureren..."
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "    .env gekopieerd van .env.example"
        echo "    EDIT: Pas .env aan met juiste database credentials!"
    else
        echo "    WAARSCHUWING: Geen .env of .env.example gevonden"
        echo "    Kopieer .env van bestaande installatie"
    fi
else
    echo "    .env bestaat al"
fi

# Stap 3: Build en start containers
echo "[3/5] Docker containers starten..."
docker compose up -d --build

# Stap 4: Wacht op containers
echo "[4/5] Wachten op containers (30 sec)..."
sleep 30

# Stap 5: Run migraties
echo "[5/5] Database migraties uitvoeren..."
docker exec pma_yii ./yii migrate --interactive=0
docker exec pma_yii ./yii_test migrate --interactive=0

# Validatie
echo ""
echo "=== VALIDATIE ==="
docker compose ps
echo ""

# Lees NGINX_PORT uit .env (default 8080)
NGINX_PORT="${NGINX_PORT:-8080}"

echo "Applicatie test:"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost:${NGINX_PORT}

echo ""
echo "=== SETUP COMPLEET ==="
echo "Applicatie bereikbaar via:"
echo "  - Lokaal: http://localhost:${NGINX_PORT}"
echo "  - Tailscale: http://$(hostname):${NGINX_PORT}"
echo "  - Tailscale IP: http://$(tailscale ip -4):${NGINX_PORT}"
echo ""
echo "BELANGRIJK: Voer sectie 4.4.1 (Docker Firewall Hardening) uit!"
```

### 4.4 UFW Firewall Configuratie

> **Let op:** UFW alleen is niet voldoende voor Docker security. Docker manipuleert iptables direct en kan UFW regels omzeilen. Na deze sectie moet je ook sectie 4.4.1 (Docker Firewall Hardening) uitvoeren.

```bash
#!/bin/bash
# === UFW FIREWALL CONFIGURATIE ===
# Bestand: /tmp/configure-ufw.sh
# Uitvoeren als: sudo bash /tmp/configure-ufw.sh
#
# BELANGRIJK: Dit is stap 1 van 2. Voer ook sectie 4.4.1 uit!

set -euo pipefail

NGINX_PORT="${NGINX_PORT:-8080}"

echo "=== UFW Firewall Configuratie ==="

# Stap 1: Reset UFW naar defaults (idempotent)
echo "[1/5] UFW resetten naar defaults..."
ufw --force reset

# Stap 2: Default policies
echo "[2/5] Default policies instellen..."
ufw default deny incoming
ufw default allow outgoing

# Stap 3: SSH toestaan (belangrijk: doe dit EERST!)
echo "[3/5] SSH toestaan..."
ufw allow ssh

# Stap 4: HTTP alleen via Tailscale interface
echo "[4/5] HTTP via Tailscale toestaan (poort ${NGINX_PORT})..."
ufw allow in on tailscale0 to any port ${NGINX_PORT}

# Stap 5: Activeer UFW
echo "[5/5] UFW activeren..."
ufw --force enable

# Validatie output
echo ""
echo "=== VALIDATIE ==="
ufw status verbose

# Security waarschuwing
echo ""
echo "=== WAARSCHUWING ==="
echo "UFW alleen is NIET voldoende voor Docker security!"
echo "Docker kan UFW regels omzeilen via iptables."
echo ""
echo "Voer sectie 4.4.1 (Docker Firewall Hardening) uit!"

# Log naar syslog
logger -t ufw-setup "UFW configured: SSH allowed, HTTP ${NGINX_PORT} on tailscale0 only"
```

**UFW Rules Overzicht:**

| Rule # | To | Action | From | Interface |
|--------|-----|--------|------|-----------|
| 1 | 22/tcp | ALLOW | Anywhere | Any |
| 2 | ${NGINX_PORT} | ALLOW | Anywhere | tailscale0 |

**Verificatie:**
```bash
# Check UFW status
sudo ufw status numbered
# Verwacht: Rules zoals hierboven

# Verify HTTP bereikbaar via Tailscale
curl -I http://localhost:${NGINX_PORT:-8080}
# Verwacht: HTTP/1.1 200 OK (of redirect)

# Verify HTTP niet bereikbaar via publiek IP (van externe machine)
# LET OP: Deze test kan nog steeds slagen tot sectie 4.4.1 is uitgevoerd!
curl -I http://<PUBLIC_IP>:${NGINX_PORT:-8080}
# Verwacht: Connection refused of timeout (na Docker hardening)
```

### 4.4.1 Docker Firewall Hardening

**Belangrijk:** UFW alleen is niet voldoende voor Docker security. Docker manipuleert iptables direct en kan UFW regels omzeilen.

#### Stap 0: MySQL Poort Verwijderen (Aanbevolen)

De repository's `docker-compose.yml` exposed MySQL op `${DB_PORT:-3306}`. Voor de centrale server is dit niet nodig en een security risico.

**Actie:** Verwijder of comment-out de `ports` sectie van de `pma_mysql` service:

```yaml
# In docker-compose.yml, bij pma_mysql service:
# ports:
#   - "${DB_PORT:-3306}:3306"  # Verwijder deze regel
```

**Verificatie:**
```bash
# Na docker compose up -d, check dat MySQL NIET extern luistert:
ss -tlnp | grep 3306
# Verwacht: GEEN output (MySQL alleen binnen Docker network)
```

> **Rationale:** MySQL hoeft alleen bereikbaar te zijn vanuit de pma_yii container via het Docker network. Externe toegang is niet nodig en vergroot de attack surface.

#### NGINX Hardening

Er zijn twee opties voor NGINX hardening:

#### Optie A: Bind NGINX aan Tailscale IP (Aanbevolen)

De meest robuuste oplossing is NGINX alleen te laten luisteren op het Tailscale IP.

**Stap 1:** Verkrijg Tailscale IP en zet in `.env`:
```bash
# Voeg toe aan .env
TAILSCALE_IP=$(tailscale ip -4)
echo "TAILSCALE_IP=${TAILSCALE_IP}" >> /opt/promptmanager/.env
```

**Stap 2:** Pas `docker-compose.yml` aan:
```yaml
# Wijzig de pma_nginx ports sectie van:
ports:
  - "${NGINX_PORT:-8080}:80"

# Naar:
ports:
  - "${TAILSCALE_IP:-127.0.0.1}:${NGINX_PORT:-8080}:80"
```

**Stap 3:** Herstart containers:
```bash
cd /opt/promptmanager
docker compose down
docker compose up -d
```

**Verificatie:**
```bash
# Check dat NGINX alleen op Tailscale IP luistert
ss -tlnp | grep 8080
# Verwacht: 100.x.x.x:8080 (Tailscale IP), NIET 0.0.0.0:8080

# Test van publiek IP (van externe machine)
curl -I http://<PUBLIC_IP>:8080
# Verwacht: Connection refused (niet timeout)
```

#### Optie B: DOCKER-USER iptables Chain

Als binding aan Tailscale IP niet mogelijk is, gebruik de DOCKER-USER chain:

```bash
#!/bin/bash
# === DOCKER-USER FIREWALL RULES ===
# Bestand: /tmp/configure-docker-firewall.sh
# Uitvoeren als: sudo bash /tmp/configure-docker-firewall.sh

set -euo pipefail

TAILSCALE_INTERFACE="tailscale0"
HTTP_PORT="${NGINX_PORT:-8080}"  # Gebruik NGINX_PORT uit environment of default 8080

echo "=== Docker Firewall Hardening ==="
echo "HTTP_PORT: ${HTTP_PORT}"

# Stap 1: Flush bestaande DOCKER-USER regels (behalve RETURN)
echo "[1/3] DOCKER-USER chain opschonen..."
iptables -F DOCKER-USER 2>/dev/null || true

# Stap 2: Sta verkeer van Tailscale interface toe
echo "[2/3] Tailscale verkeer toestaan..."
iptables -I DOCKER-USER -i ${TAILSCALE_INTERFACE} -j RETURN

# Stap 3: Blokkeer HTTP poort van andere interfaces
echo "[3/3] HTTP poort blokkeren voor externe interfaces..."
iptables -I DOCKER-USER -p tcp --dport ${HTTP_PORT} ! -i ${TAILSCALE_INTERFACE} -j DROP

# Stap 4: Voeg RETURN toe aan het einde (verplicht)
iptables -A DOCKER-USER -j RETURN

# Toon resultaat
echo ""
echo "=== DOCKER-USER Chain ==="
iptables -L DOCKER-USER -v -n

# Maak regels persistent
echo ""
echo "Om regels persistent te maken na reboot:"
echo "  apt install iptables-persistent"
echo "  netfilter-persistent save"
```

**Verificatie:**
```bash
# Check DOCKER-USER chain
sudo iptables -L DOCKER-USER -v -n
# Verwacht: RETURN voor tailscale0, DROP voor tcp dport 8080 van andere interfaces

# Test via Tailscale
curl -I http://ubuntu-server:8080
# Verwacht: HTTP 200/302

# Test via publiek IP (van externe machine)
curl -I http://<PUBLIC_IP>:8080
# Verwacht: Timeout of connection refused
```

#### Keuze Rationale

| Criterium | Optie A (IP Binding) | Optie B (DOCKER-USER) |
|-----------|---------------------|----------------------|
| Complexiteit | Laag | Medium |
| Persistentie | Automatisch (compose) | Vereist iptables-persistent |
| Tailscale IP wijziging | .env update + restart | iptables update |
| Verificatie | `ss -tlnp` | `iptables -L` |

**Aanbeveling:** Gebruik Optie A (IP Binding) voor eenvoud en betrouwbaarheid.

### 4.5 Backup Script

```bash
#!/bin/bash
# === MYSQL BACKUP SCRIPT (DOCKER) ===
# Bestand: /opt/scripts/mysql-backup.sh
# Uitvoeren als: sudo /opt/scripts/mysql-backup.sh
# Cron: 0 2 * * * root /opt/scripts/mysql-backup.sh

set -euo pipefail

# Configuratie
BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7
BACKUP_FILE="${BACKUP_DIR}/promptmanager_${DATE}.sql.gz"
CHECKSUM_FILE="${BACKUP_FILE}.sha256"
MIN_SIZE_BYTES=1024  # Minimum 1KB
PROMPTMANAGER_DIR="/opt/promptmanager"

# Laad .env voor database password
if [ -f "${PROMPTMANAGER_DIR}/.env" ]; then
    source "${PROMPTMANAGER_DIR}/.env"
fi

DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}"
if [ -z "$DB_ROOT_PASSWORD" ]; then
    echo "ERROR: DB_ROOT_PASSWORD niet gevonden in .env"
    exit 1
fi

# Logging functie
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    logger -t mysql-backup "$1"
}

# Error handler
error_exit() {
    log "ERROR: $1"
    exit 1
}

log "=== MySQL Backup Start ==="

# Stap 1: Maak backup directory
mkdir -p "${BACKUP_DIR}" || error_exit "Kan backup directory niet maken"

# Stap 2: Maak backup via Docker
log "Database backup maken..."
docker exec pma_mysql mysqldump -u root -p"${DB_ROOT_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    promptmanager 2>/dev/null | gzip > "${BACKUP_FILE}" || error_exit "Backup gefaald"

# Stap 3: Genereer checksum
log "Checksum genereren..."
sha256sum "${BACKUP_FILE}" > "${CHECKSUM_FILE}"

# Stap 4: Valideer bestandsgrootte
SIZE=$(stat -c%s "${BACKUP_FILE}" 2>/dev/null || stat -f%z "${BACKUP_FILE}")
if [ "$SIZE" -lt "$MIN_SIZE_BYTES" ]; then
    error_exit "Backup te klein (${SIZE} bytes) - mogelijk leeg of corrupt"
fi

# Stap 5: Verwijder oude backups
log "Oude backups opruimen (ouder dan ${RETENTION_DAYS} dagen)..."
find "${BACKUP_DIR}" -name "*.sql.gz" -mtime +${RETENTION_DAYS} -delete
find "${BACKUP_DIR}" -name "*.sha256" -mtime +${RETENTION_DAYS} -delete

# Stap 6: Rapporteer
BACKUP_COUNT=$(find "${BACKUP_DIR}" -name "*.sql.gz" | wc -l)
TOTAL_SIZE=$(du -sh "${BACKUP_DIR}" | cut -f1)

log "=== Backup Compleet ==="
log "Bestand: ${BACKUP_FILE}"
log "Grootte: ${SIZE} bytes"
log "Totaal backups: ${BACKUP_COUNT}"
log "Totale grootte: ${TOTAL_SIZE}"
```

### 4.6 Health Check Script

```bash
#!/bin/bash
# === HEALTH CHECK SCRIPT ===
# Bestand: /opt/scripts/healthcheck.sh
# Uitvoeren als: /opt/scripts/healthcheck.sh
# Cron: */5 * * * * root /opt/scripts/healthcheck.sh

set -euo pipefail

PROMPTMANAGER_DIR="/opt/promptmanager"
ERRORS=0

# Logging functie
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    logger -t healthcheck "$1"
}

log_error() {
    log "ERROR: $1"
    ERRORS=$((ERRORS + 1))
}

log "=== Health Check Start ==="

# Check 1: Docker daemon
if ! docker info &> /dev/null; then
    log_error "Docker daemon is niet bereikbaar"
else
    log "OK: Docker daemon actief"
fi

# Check 2: pma_yii container
if ! docker ps --format '{{.Names}}' | grep -q '^pma_yii$'; then
    log_error "pma_yii container draait niet"
else
    log "OK: pma_yii container actief"
fi

# Check 3: pma_nginx container
if ! docker ps --format '{{.Names}}' | grep -q '^pma_nginx$'; then
    log_error "pma_nginx container draait niet"
else
    log "OK: pma_nginx container actief"
fi

# Check 4: pma_mysql container
if ! docker ps --format '{{.Names}}' | grep -q '^pma_mysql$'; then
    log_error "pma_mysql container draait niet"
else
    log "OK: pma_mysql container actief"
fi

# Check 5: HTTP endpoint
NGINX_PORT="${NGINX_PORT:-8080}"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${NGINX_PORT} 2>/dev/null || echo "000")
if [ "$HTTP_STATUS" != "200" ] && [ "$HTTP_STATUS" != "302" ]; then
    log_error "HTTP endpoint niet bereikbaar (status: ${HTTP_STATUS})"
else
    log "OK: HTTP endpoint bereikbaar (status: ${HTTP_STATUS})"
fi

# Check 6: Tailscale
if ! tailscale status --json 2>/dev/null | jq -e '.Self.Online' > /dev/null; then
    log_error "Tailscale is offline"
else
    log "OK: Tailscale online"
fi

# Check 7: Disk space
DISK_USAGE=$(df -h /var/lib/docker | tail -1 | awk '{print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 90 ]; then
    log_error "Disk usage kritiek: ${DISK_USAGE}%"
else
    log "OK: Disk usage: ${DISK_USAGE}%"
fi

# Check 8: Database migratie status
if ! docker exec pma_yii ./yii migrate/status 2>/dev/null | grep -q "No new migrations"; then
    log_error "Database migraties pending of fout"
else
    log "OK: Database schema up-to-date"
fi

# Resultaat
log "=== Health Check Compleet ==="
if [ $ERRORS -gt 0 ]; then
    log "WAARSCHUWING: ${ERRORS} problemen gevonden"
    exit 1
else
    log "Alle checks OK"
    exit 0
fi
```

### 4.7 Systemd Timer voor Health Checks

```ini
# /etc/systemd/system/promptmanager-healthcheck.service
[Unit]
Description=PromptManager Health Check
After=docker.service

[Service]
Type=oneshot
ExecStart=/opt/scripts/healthcheck.sh
User=root
```

```ini
# /etc/systemd/system/promptmanager-healthcheck.timer
[Unit]
Description=PromptManager Health Check Timer

[Timer]
OnCalendar=*:0/5
Persistent=true

[Install]
WantedBy=timers.target
```

**Activeren:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable promptmanager-healthcheck.timer
sudo systemctl start promptmanager-healthcheck.timer
```

---

## 5) Client Configuratie

### 5.1 Tailscale Installatie

Clients hebben alleen Tailscale nodig. Geen Docker, geen code, geen database configuratie.

```bash
#!/bin/bash
# === TAILSCALE INSTALLATIE (CLIENT) ===
# Bestand: /tmp/install-tailscale-client.sh
# Uitvoeren als: sudo bash /tmp/install-tailscale-client.sh

set -euo pipefail

echo "=== Tailscale Client Installatie ==="

# Stap 1: Installeer Tailscale
if ! command -v tailscale &> /dev/null; then
    echo "[1/3] Tailscale installeren..."

    # Detecteer OS
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        curl -fsSL https://tailscale.com/install.sh | sh
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        if command -v brew &> /dev/null; then
            brew install tailscale
        else
            echo "Installeer Tailscale via: https://tailscale.com/download"
            exit 1
        fi
    else
        echo "Onbekend OS. Installeer Tailscale via: https://tailscale.com/download"
        exit 1
    fi
else
    echo "[1/3] Tailscale al geïnstalleerd: $(tailscale version)"
fi

# Stap 2: Start en authenticeer Tailscale
echo "[2/3] Tailscale starten..."
if ! tailscale status &> /dev/null; then
    sudo tailscale up
    echo "    Browser opent voor authenticatie. Wacht tot voltooid..."
fi

# Stap 3: Test verbinding met server
echo "[3/3] Verbinding met server testen..."
if tailscale ping ubuntu-server --timeout=5s &> /dev/null; then
    echo "    OK: Server bereikbaar"
else
    echo "    WAARSCHUWING: Server niet bereikbaar via 'ubuntu-server'"
    echo "    Probeer met IP: tailscale ping <server-tailscale-ip>"
fi

# Validatie output
# Vraag server poort op (standaard 8080 voor centrale server)
SERVER_PORT="${NGINX_PORT:-8080}"

echo ""
echo "=== SETUP COMPLEET ==="
echo ""
echo "Open PromptManager in je browser:"
echo "  http://ubuntu-server:${SERVER_PORT}"
echo ""
echo "Of gebruik het Tailscale IP van de server."
```

**Windows installatie:**
1. Download Tailscale van https://tailscale.com/download
2. Installeer en start Tailscale
3. Log in via de browser popup
4. Open browser naar `http://ubuntu-server:8080` (of de geconfigureerde NGINX_PORT)

**macOS installatie:**
```bash
# Via Homebrew
brew install tailscale
tailscale up

# Of download van https://tailscale.com/download
```

### 5.2 Verificatie

```bash
# Check Tailscale status
tailscale status
# Verwacht: Toont server en andere devices

# Test bereikbaarheid server
tailscale ping ubuntu-server
# Verwacht: pong from ubuntu-server

# Test HTTP (optioneel, vanuit terminal)
curl -I http://ubuntu-server:8080
# Verwacht: HTTP/1.1 200 OK of 302 redirect
```

### 5.3 Browser Configuratie

Geen speciale configuratie nodig. Open gewoon:

```
http://ubuntu-server:8080
```

Tip: Maak een bookmark voor snelle toegang.

---

## 6) Migratie Procedure

### 6.0 Data Integriteit Model

> **Belangrijk:** Dit is een referentie naar het FO (sectie "Delete Cascade Gedrag"). De volledige specificatie staat in het FO document.

**Kernpunten:**

| Aspect | Specificatie |
|--------|--------------|
| Soft-deletes | **Nee** - Dit ontwerp gebruikt geen soft-deletes |
| Delete gedrag | **CASCADE DELETE** via database foreign keys |
| Data integriteit | Gegarandeerd door database constraints |

**Cascade Delete Hiërarchie:**

```
project (root) ─┬── context         → CASCADE DELETE
                ├── field           → CASCADE DELETE
                │   └── field_option → CASCADE DELETE
                ├── prompt_template → CASCADE DELETE
                │   ├── template_field   → CASCADE DELETE
                │   └── prompt_instance  → CASCADE DELETE
                ├── scratch_pad     → CASCADE DELETE
                └── project_linked_project → CASCADE DELETE
```

**Implicaties voor Migratie:**
- Bij import worden foreign key constraints gevalideerd
- Import volgorde moet parent-first zijn (project → children)
- Referentiële integriteit fouten blokkeren de import

### 6.1 Pre-Migratie: Canonieke Bron Selectie (D3)

**Criterium:** De machine met de meest recente data wordt de canonieke bron.

```bash
#!/bin/bash
# === CANONIEKE BRON SELECTIE ===
# Uitvoeren op ELKE machine met lokale PromptManager data
# Vergelijk output om canonieke bron te bepalen

set -euo pipefail

# Laad environment variabelen
PROMPTMANAGER_DIR="${PROMPTMANAGER_DIR:-/opt/promptmanager}"
if [ -f "${PROMPTMANAGER_DIR}/.env" ]; then
    source "${PROMPTMANAGER_DIR}/.env"
elif [ -f ".env" ]; then
    source ".env"
else
    echo "ERROR: .env niet gevonden in ${PROMPTMANAGER_DIR} of huidige directory"
    exit 1
fi

echo "=== Data Inventarisatie ==="
echo "Machine: $(hostname)"
echo "Datum: $(date)"
echo ""

# Record counts
echo "Record counts:"
docker exec pma_mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e "
    SELECT 'project', COUNT(*) FROM promptmanager.project
    UNION ALL
    SELECT 'prompt_template', COUNT(*) FROM promptmanager.prompt_template
    UNION ALL
    SELECT 'prompt_instance', COUNT(*) FROM promptmanager.prompt_instance
    UNION ALL
    SELECT 'context', COUNT(*) FROM promptmanager.context
    UNION ALL
    SELECT 'field', COUNT(*) FROM promptmanager.field
    UNION ALL
    SELECT 'scratch_pad', COUNT(*) FROM promptmanager.scratch_pad;
"

echo ""
echo "Meest recente update per entiteit:"
docker exec pma_mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e "
    SELECT 'project', MAX(updated_at) FROM promptmanager.project
    UNION ALL
    SELECT 'prompt_template', MAX(updated_at) FROM promptmanager.prompt_template
    UNION ALL
    SELECT 'context', MAX(updated_at) FROM promptmanager.context
    UNION ALL
    SELECT 'field', MAX(updated_at) FROM promptmanager.field
    UNION ALL
    SELECT 'scratch_pad', MAX(updated_at) FROM promptmanager.scratch_pad;
"

echo ""
echo "=== Vergelijk output tussen machines ==="
echo "Kies machine met:"
echo "  1. Meest recente MAX(updated_at)"
echo "  2. Bij gelijke timestamps: meeste records"
```

### 6.2 Pre-Migratie Backup

```bash
#!/bin/bash
# === PRE-MIGRATIE BACKUP ===
# Uitvoeren op ELKE machine vóór migratie

set -euo pipefail

# Laad environment variabelen
PROMPTMANAGER_DIR="${PROMPTMANAGER_DIR:-/opt/promptmanager}"
if [ -f "${PROMPTMANAGER_DIR}/.env" ]; then
    source "${PROMPTMANAGER_DIR}/.env"
elif [ -f ".env" ]; then
    source ".env"
else
    echo "ERROR: .env niet gevonden in ${PROMPTMANAGER_DIR} of huidige directory"
    exit 1
fi

DATE=$(date +%Y%m%d_%H%M%S)
HOSTNAME=$(hostname)
BACKUP_FILE="pre_migration_${HOSTNAME}_${DATE}.sql"

echo "=== Pre-Migratie Backup ==="
echo "Machine: ${HOSTNAME}"
echo "Bestand: ${BACKUP_FILE}"

# Maak backup
docker exec pma_mysql mysqldump -u root -p"${DB_ROOT_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    promptmanager > "${BACKUP_FILE}"

# Genereer checksum
sha256sum "${BACKUP_FILE}" > "${BACKUP_FILE}.sha256"

# Validatie
echo ""
echo "Backup grootte: $(stat -c%s "${BACKUP_FILE}") bytes"
echo "Checksum: $(cat "${BACKUP_FILE}.sha256")"
echo ""
echo "BEWAAR DIT BESTAND OP EEN VEILIGE LOCATIE!"
```

### 6.3 Data Export van Canonieke Bron

```bash
#!/bin/bash
# === CANONIEKE EXPORT ===
# Uitvoeren op de CANONIEKE machine

set -euo pipefail

# Laad environment variabelen
PROMPTMANAGER_DIR="${PROMPTMANAGER_DIR:-/opt/promptmanager}"
if [ -f "${PROMPTMANAGER_DIR}/.env" ]; then
    source "${PROMPTMANAGER_DIR}/.env"
elif [ -f ".env" ]; then
    source ".env"
else
    echo "ERROR: .env niet gevonden in ${PROMPTMANAGER_DIR} of huidige directory"
    exit 1
fi

DATE=$(date +%Y%m%d_%H%M%S)
EXPORT_FILE="promptmanager_canonical_${DATE}.sql"

echo "=== Canonieke Export ==="

# Standaard export (geen --insert-ignore: import vereist lege database)
docker exec pma_mysql mysqldump -u root -p"${DB_ROOT_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    promptmanager > "${EXPORT_FILE}"

# Genereer checksum
sha256sum "${EXPORT_FILE}" > "${EXPORT_FILE}.sha256"

echo ""
echo "Export bestand: ${EXPORT_FILE}"
echo "Grootte: $(stat -c%s "${EXPORT_FILE}") bytes"
echo "Checksum: $(cat "${EXPORT_FILE}.sha256")"
echo ""
echo "Kopieer naar server:"
echo "  scp ${EXPORT_FILE} ${EXPORT_FILE}.sha256 ubuntu-server:/tmp/"
```

### 6.4 Data Import op Server

> **KRITIEK - Lege Database Vereist (FO-004):**
>
> De centrale server MOET starten met een **lege database** (alleen schema, geen data). Dit voorkomt duplicatie van records.
>
> **Wat NIET te doen:**
> - Kopieer GEEN database bestanden handmatig van een lokale machine naar de server
> - Importeer NIET meerdere database dumps naar dezelfde centrale database
> - Voer NIET meerdere keren dezelfde import uit zonder database reset
>
> **Waarom:** Elke lokale machine heeft eigen auto-increment IDs. Bij import van meerdere bronnen ontstaan ID-conflicten of duplicaten met verschillende IDs voor dezelfde logische content.
>
> **Database Reset (indien nodig):**
> ```bash
> # ALLEEN uitvoeren als database niet leeg is
> docker exec pma_mysql mysql -u root -p"$DB_ROOT_PASSWORD" -e \
>     "DROP DATABASE IF EXISTS promptmanager; CREATE DATABASE promptmanager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
> docker exec pma_yii ./yii migrate --interactive=0
> ```

```bash
#!/bin/bash
# === CENTRALE IMPORT ===
# Uitvoeren op de SERVER na kopiëren van export bestand
# VOORWAARDE: Database moet leeg zijn (alleen schema)!

set -euo pipefail

# Laad environment variabelen
PROMPTMANAGER_DIR="${PROMPTMANAGER_DIR:-/opt/promptmanager}"
if [ -f "${PROMPTMANAGER_DIR}/.env" ]; then
    source "${PROMPTMANAGER_DIR}/.env"
elif [ -f ".env" ]; then
    source ".env"
else
    echo "ERROR: .env niet gevonden in ${PROMPTMANAGER_DIR} of huidige directory"
    exit 1
fi

IMPORT_FILE="$1"

if [ -z "$IMPORT_FILE" ]; then
    echo "Gebruik: $0 <import-bestand.sql>"
    exit 1
fi

echo "=== Centrale Import ==="
echo "Bestand: ${IMPORT_FILE}"

# Stap 1: Verifieer checksum
echo "[1/5] Checksum verifiëren..."
if [ -f "${IMPORT_FILE}.sha256" ]; then
    if sha256sum -c "${IMPORT_FILE}.sha256"; then
        echo "    OK: Checksum correct"
    else
        echo "    FOUT: Checksum mismatch!"
        exit 1
    fi
else
    echo "    WAARSCHUWING: Geen checksum bestand gevonden"
fi

# Stap 2: Controleer dat database leeg is (alleen schema)
echo "[2/5] Controleren dat database leeg is..."
# Check alle core tabellen (project, prompt_template, context, field, scratch_pad)
TOTAL_RECORDS=$(docker exec pma_mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e "
    SELECT COALESCE(SUM(cnt), 0) FROM (
        SELECT COUNT(*) as cnt FROM promptmanager.project
        UNION ALL SELECT COUNT(*) FROM promptmanager.prompt_template
        UNION ALL SELECT COUNT(*) FROM promptmanager.context
        UNION ALL SELECT COUNT(*) FROM promptmanager.field
        UNION ALL SELECT COUNT(*) FROM promptmanager.scratch_pad
    ) counts;" 2>/dev/null || echo "0")
if [ "$TOTAL_RECORDS" != "0" ]; then
    echo "    FOUT: Database bevat al ${TOTAL_RECORDS} record(s) in core tabellen!"
    echo ""
    echo "    Record counts per tabel:"
    docker exec pma_mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e "
        SELECT 'project', COUNT(*) FROM promptmanager.project
        UNION ALL SELECT 'prompt_template', COUNT(*) FROM promptmanager.prompt_template
        UNION ALL SELECT 'context', COUNT(*) FROM promptmanager.context
        UNION ALL SELECT 'field', COUNT(*) FROM promptmanager.field
        UNION ALL SELECT 'scratch_pad', COUNT(*) FROM promptmanager.scratch_pad;" 2>/dev/null
    echo ""
    echo "    Import vereist een lege database om duplicatie te voorkomen."
    echo "    Reset de database eerst met:"
    echo "      docker exec pma_mysql mysql -u root -p\"\$DB_ROOT_PASSWORD\" -e \\"
    echo "        \"DROP DATABASE IF EXISTS promptmanager; CREATE DATABASE promptmanager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
    echo "      docker exec pma_yii ./yii migrate --interactive=0"
    exit 1
fi
echo "    OK: Database is leeg (alle core tabellen)"

# Stap 3: Backup huidige schema (voor rollback)
echo "[3/5] Huidige schema backuppen..."
docker exec pma_mysql mysqldump -u root -p"${DB_ROOT_PASSWORD}" \
    --single-transaction \
    --no-data \
    promptmanager > "/tmp/pre_import_schema_$(date +%Y%m%d_%H%M%S).sql"

# Stap 4: Import data
echo "[4/5] Data importeren..."
docker exec -i pma_mysql mysql -u root -p"${DB_ROOT_PASSWORD}" promptmanager < "${IMPORT_FILE}"

# Stap 5: Validatie
echo "[5/5] Validatie..."
docker exec pma_mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e "
    SELECT 'project', COUNT(*) FROM promptmanager.project
    UNION ALL
    SELECT 'prompt_template', COUNT(*) FROM promptmanager.prompt_template
    UNION ALL
    SELECT 'prompt_instance', COUNT(*) FROM promptmanager.prompt_instance;
"

echo ""
echo "=== Import Compleet ==="
echo "Verifieer data via browser: http://localhost:${NGINX_PORT:-8080}"
```

### 6.5 Post-Migratie Validatie

```bash
#!/bin/bash
# === POST-MIGRATIE VALIDATIE ===
# Uitvoeren op SERVER na import

set -euo pipefail

# Laad environment variabelen
PROMPTMANAGER_DIR="${PROMPTMANAGER_DIR:-/opt/promptmanager}"
if [ -f "${PROMPTMANAGER_DIR}/.env" ]; then
    source "${PROMPTMANAGER_DIR}/.env"
elif [ -f ".env" ]; then
    source ".env"
else
    echo "ERROR: .env niet gevonden in ${PROMPTMANAGER_DIR} of huidige directory"
    exit 1
fi

echo "=== Post-Migratie Validatie ==="

# Check 1: Record counts
echo "[1/5] Record counts:"
docker exec pma_mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e "
    SELECT 'project', COUNT(*) FROM promptmanager.project
    UNION ALL
    SELECT 'prompt_template', COUNT(*) FROM promptmanager.prompt_template
    UNION ALL
    SELECT 'prompt_instance', COUNT(*) FROM promptmanager.prompt_instance
    UNION ALL
    SELECT 'context', COUNT(*) FROM promptmanager.context
    UNION ALL
    SELECT 'field', COUNT(*) FROM promptmanager.field
    UNION ALL
    SELECT 'scratch_pad', COUNT(*) FROM promptmanager.scratch_pad;
"

# Check 2: Steekproef data
echo ""
echo "[2/5] Steekproef - eerste en laatste project:"
docker exec pma_mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -e "
    SELECT id, name, created_at FROM promptmanager.project ORDER BY id ASC LIMIT 1;
    SELECT id, name, created_at FROM promptmanager.project ORDER BY id DESC LIMIT 1;
"

# Check 3: Schema migraties
echo ""
echo "[3/5] Schema migratie status:"
docker exec pma_yii ./yii migrate/status

# Check 4: HTTP endpoint
echo ""
echo "[4/5] HTTP endpoint:"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:${NGINX_PORT:-8080}")
echo "HTTP Status: ${HTTP_STATUS}"

# Check 5: Applicatie logs
echo ""
echo "[5/5] Recente applicatie logs:"
docker logs pma_yii --tail 10 2>&1 | grep -v "^$" || echo "(geen logs)"

echo ""
echo "=== Validatie Compleet ==="
echo ""
echo "Handmatige check:"
echo "  1. Open http://ubuntu-server:${NGINX_PORT:-8080} in browser"
echo "  2. Controleer of projecten zichtbaar zijn"
echo "  3. Maak een test-prompt aan"
echo "  4. Verwijder de test-prompt"
```

---

## 7) Cut-over Procedure

### 7.1 Cut-over Checklist

| Stap | Criterium | Verificatie | Status |
|------|-----------|-------------|--------|
| 1 | Pre-migratie backups compleet | Alle machines gebackupt met checksum | ☐ |
| 2 | Canonieke bron geselecteerd | Machine met meest recente data geïdentificeerd | ☐ |
| 3 | Server operationeel | Docker containers draaien, HTTP bereikbaar | ☐ |
| 4 | Data geïmporteerd | Record counts geverifieerd | ☐ |
| 5 | Tailscale op clients | Alle clients kunnen server pingen | ☐ |
| 6 | Browser test | Applicatie werkt via browser | ☐ |
| 7 | CRUD test | Create, Read, Update, Delete werkt | ☐ |

### 7.2 Client Cut-over

Na succesvolle migratie en validatie:

**Op elke client:**

1. **Tailscale verificatie:**
   ```bash
   tailscale status
   tailscale ping ubuntu-server
   ```

2. **Browser openen:**
   ```
   http://ubuntu-server:8080
   ```

3. **Verificatie:**
   - Check of bestaande data zichtbaar is
   - Maak een test-prompt
   - Verwijder de test-prompt

4. **Opruimen lokale installatie (optioneel, na 7 dagen):**
   ```bash
   # Stop lokale containers
   cd /path/to/local/promptmanager
   docker compose down

   # Bewaar backup voor noodgevallen
   # Verwijder later volgens decommissioning procedure
   ```

---

## 8) Rollback Procedure

### 8.1 Rollback naar Lokale Installatie

Indien cut-over faalt of centrale server niet beschikbaar:

```bash
#!/bin/bash
# === ROLLBACK PROCEDURE ===
# Uitvoeren op CLIENT machine

set -euo pipefail

LOCAL_PROMPTMANAGER_DIR="/path/to/local/promptmanager"
PRE_MIGRATION_BACKUP="pre_migration_$(hostname)_YYYYMMDD_HHMMSS.sql"

echo "=== Rollback naar Lokale Installatie ==="

cd "${LOCAL_PROMPTMANAGER_DIR}"

# Laad environment variabelen
if [ -f ".env" ]; then
    source ".env"
else
    echo "ERROR: .env niet gevonden in ${LOCAL_PROMPTMANAGER_DIR}"
    exit 1
fi

# Stap 1: Start lokale containers
echo "[1/4] Lokale containers starten..."
docker compose up -d

# Stap 2: Wacht op MySQL
echo "[2/4] Wachten op MySQL..."
sleep 30

# Stap 3: Restore pre-migratie backup
echo "[3/4] Pre-migratie backup restoren..."
docker exec -i pma_mysql mysql -u root -p"${DB_ROOT_PASSWORD}" promptmanager < "${PRE_MIGRATION_BACKUP}"

# Stap 4: Validatie
echo "[4/4] Validatie..."
docker exec pma_yii ./yii migrate/status
curl -I "http://localhost:${NGINX_PORT:-8080}"

echo ""
echo "=== Rollback Compleet ==="
echo "Lokale installatie actief op http://localhost:${NGINX_PORT:-8080}"
```

### 8.2 Rollback Criteria

| Trigger | Drempel | Actie |
|---------|---------|-------|
| Server niet bereikbaar | > 4 uur | Rollback overwegen |
| Data corruptie | Enig signaal | Rollback naar pre-migratie backup |
| Performance issues | Latency > 500ms consistent | Investigate, mogelijk rollback |
| Kritieke functionaliteit broken | Enig signaal | Rollback |

---

## 9) Troubleshooting

### 9.1 Verbindingsproblemen

| Symptoom | Mogelijke Oorzaak | Oplossing |
|----------|-------------------|-----------|
| Browser: "Connection refused" | Docker containers down | `docker compose up -d` op server |
| Browser: "Connection timeout" | Tailscale niet verbonden | `tailscale up` op client |
| Browser: "Connection timeout" | UFW blokkeert | `sudo ufw allow in on tailscale0 to any port 8080` |
| "Cannot resolve ubuntu-server" | MagicDNS niet actief | Gebruik Tailscale IP direct |
| HTTP 500 error | Applicatie error | Check `docker logs pma_yii` |

### 9.2 Diagnostische Commando's

**Op server:**
```bash
# Docker status
docker compose ps
docker logs pma_yii --tail 50
docker logs pma_mysql --tail 50

# Tailscale status
tailscale status

# Firewall status
sudo ufw status verbose

# HTTP endpoint test (gebruik NGINX_PORT uit .env of default 8080)
curl -I "http://localhost:${NGINX_PORT:-8080}"

# Database migratie status
docker exec pma_yii ./yii migrate/status
```

**Op client:**
```bash
# Tailscale status
tailscale status

# Server bereikbaarheid
tailscale ping ubuntu-server

# HTTP test (indien curl beschikbaar)
curl -I http://ubuntu-server:8080
```

### 9.3 Veelvoorkomende Problemen

**Docker containers starten niet:**
```bash
# Check logs
docker compose logs

# Restart containers
docker compose down
docker compose up -d

# Check disk space
df -h
```

**Database migration errors:**
```bash
# Check status
docker exec pma_yii ./yii migrate/status

# Force run migrations
docker exec pma_yii ./yii migrate --interactive=0
```

**Tailscale reconnect issues:**
```bash
# Force reconnect
sudo tailscale down
sudo tailscale up

# Check authenticatie
tailscale status
```

---

## 10) Monitoring & Alerting

### 10.1 Health Check Endpoints

| Endpoint | Methode | Verwachte Response | Interval |
|----------|---------|-------------------|----------|
| `http://localhost:${NGINX_PORT}` | GET | HTTP 200 of 302 | 5 min |
| `docker compose ps` | CLI | Beide containers "Up" | 5 min |
| `tailscale status` | CLI | "Online" status | 5 min |

### 10.2 Log Locaties

| Log | Locatie | Retentie |
|-----|---------|----------|
| Applicatie logs | `docker logs pma_yii` | Docker default |
| MySQL logs | `docker logs pma_mysql` | Docker default |
| Backup logs | `/var/log/syslog` (tag: mysql-backup) | Syslog default |
| Health check logs | `/var/log/syslog` (tag: healthcheck) | Syslog default |
| UFW logs | `/var/log/ufw.log` | Syslog default |

### 10.3 Cron Jobs

```bash
# /etc/cron.d/promptmanager

# Dagelijkse backup om 02:00
0 2 * * * root /opt/scripts/mysql-backup.sh

# Health check elke 5 minuten (alternatief voor systemd timer)
*/5 * * * * root /opt/scripts/healthcheck.sh
```

---

## 11) Decommissioning Lokale Installaties

### 11.1 Retentie Beleid

| Item | Bewaartermijn | Locatie | Actie na termijn |
|------|---------------|---------|------------------|
| Pre-migratie backup (canoniek) | **Permanent** | Veilige locatie | Archiveren |
| Pre-migratie backups (overig) | 30 dagen | Lokale machine | Verwijderen |
| Lokale Docker containers | 7 dagen na cut-over | Docker | Stoppen en verwijderen |
| Lokale MySQL data (bind mount) | 14 dagen na cut-over | `./data/db/mysql` | Archiveren dan verwijderen |

### 11.2 Decommissioning Script

```bash
#!/bin/bash
# === DECOMMISSIONING LOKALE INSTALLATIE ===
# Uitvoeren op CLIENT na succesvolle cut-over (wacht minimaal 7 dagen)

set -euo pipefail

LOCAL_DIR="/path/to/local/promptmanager"

echo "=== Lokale Installatie Decommissioning ==="
echo ""
echo "WAARSCHUWING: Dit verwijdert de lokale PromptManager installatie!"
echo "Zorg dat je een backup hebt en de centrale server werkt."
echo ""
read -p "Doorgaan? (ja/nee): " CONFIRM

if [ "$CONFIRM" != "ja" ]; then
    echo "Geannuleerd."
    exit 0
fi

cd "${LOCAL_DIR}"

# Stap 1: Stop containers
echo "[1/4] Containers stoppen..."
docker compose down

# Stap 2: Archiveer MySQL data (bind mount)
echo "[2/4] MySQL data archiveren..."
# Note: repo gebruikt bind mount ./data/db/mysql, geen Docker volume
if [ -d "./data/db/mysql" ]; then
    tar czf "local_db_archive_$(date +%Y%m%d).tar.gz" ./data/db/mysql
    echo "    Archief: local_db_archive_$(date +%Y%m%d).tar.gz"
else
    echo "    WAARSCHUWING: ./data/db/mysql niet gevonden"
fi

# Stap 3: Verwijder containers en networks
echo "[3/4] Containers en networks verwijderen..."
docker compose rm -f

# Stap 4: Instructies voor data verwijdering
echo "[4/4] Data verwijdering (handmatig na 14 dagen):"
echo "    rm -rf ./data/db/mysql"
echo "    (Bewaar het archief bestand voor noodgevallen)"

echo ""
echo "=== Decommissioning Compleet ==="
echo "Bewaar het archief bestand voor noodgevallen."
```

---

## 12) Acceptance Criteria Verificatie

### 12.1 Must Have (MVP)

| AC ID | Criterium | Verificatie Commando | Verwacht Resultaat |
|-------|-----------|---------------------|-------------------|
| AC-1 | Tailscale op server + client | `tailscale status` | Beide machines zichtbaar |
| AC-2 | Docker containers draaien | `docker compose ps` | pma_yii, pma_nginx en pma_mysql "Up" |
| AC-3 | Firewall blokkeert publiek | `curl http://<public-ip>:${NGINX_PORT}` van extern | Timeout of refused (vereist §4.4.1 Docker hardening) |
| AC-4 | Client bereikt webapp | Browser naar `http://ubuntu-server:8080` | Pagina laadt |
| AC-5 | Applicatie werkt | CRUD test in browser | Alle operaties OK |
| AC-6 | Data sync | Prompt maken op laptop, zien op desktop | Data zichtbaar |
| AC-7 | Data gemigreerd | Record count vergelijking | Counts matchen |

### 12.2 Should Have

| AC ID | Criterium | Verificatie | Verwacht Resultaat |
|-------|-----------|-------------|-------------------|
| AC-8 | MagicDNS | `tailscale ping ubuntu-server` | pong response |
| AC-9 | Backup geconfigureerd | `ls /var/backups/mysql/` | Backup files aanwezig |
| AC-10 | Health check actief | `systemctl status promptmanager-healthcheck.timer` | Active |
| AC-11 | Backup restore test | Restore naar test DB | Data intact |

### 12.3 Negatieve Scenario's

| AC ID | Scenario | Test | Verwacht |
|-------|----------|------|----------|
| AC-N1 | Docker containers offline | `docker compose stop` | Browser toont error, geen crash |
| AC-N2 | Tailscale offline (client) | `tailscale down` | Timeout, geen data corruptie |
| AC-N3 | Recovery na Docker restart | `docker compose up -d` | App werkt na refresh |
| AC-N4 | Recovery na Tailscale reconnect | `tailscale up` | App werkt na refresh |

### 12.4 Validatie Criteria

| AC ID | Entiteit | Verificatie | Pass Criteria |
|-------|----------|-------------|---------------|
| AC-V1 | Alle tabellen | Record count vergelijking | Gelijk aan pre-migratie |
| AC-V2 | project, prompt_template | Eerste + laatste record | Data identiek |
| AC-V3 | prompt_template | CRUD in browser | Alle operaties OK |
| AC-V4 | migration tabel | `./yii migrate/status` | "No new migrations" |
| AC-V5 | file fields | N/A - beperking gedocumenteerd | Documentatie aanwezig |

### 12.5 Concurrency Criteria

| AC ID | Scenario | Test | Pass Criteria |
|-------|----------|------|---------------|
| AC-C1 | Gelijktijdige writes (verschillende records) | Twee browsers, twee prompts | Beide saves OK |
| AC-C2 | Last-write-wins | Twee browsers, zelfde prompt | Laatste save zichtbaar |

### 12.6 Regressietests

| REG ID | Kernflow | Test Stappen | Pass Criteria |
|--------|----------|--------------|---------------|
| REG-1 | Project aanmaken | Create project via browser | Project in lijst |
| REG-2 | Prompt template aanmaken | Create template met content | Template zichtbaar |
| REG-3 | Context beheren | CRUD context | Alle operaties OK |
| REG-4 | Field beheren | Create field, add options | Field werkt |
| REG-5 | Prompt instance genereren | Generate instance | Content correct |
| REG-6 | Scratch pad | Create/edit scratch pad | Content persistent |
| REG-7 | Zoeken/filteren | Zoek op naam | Juiste resultaten |
| REG-8 | Project linking | Link twee projecten | Link functioneel |

---

## 13) FO Review Bevindingen - Resoluties

### FO v2.2 Bevindingen (Recent)

| FO ID | Beschrijving | Resolutie in TO | Sectie |
|-------|--------------|-----------------|--------|
| FO-003 | Cascade-delete gedrag voor parent-child relaties onduidelijk | Data Integriteit Model sectie toegevoegd met cascade hiërarchie | §6.0 |
| FO-004 | Risico op duplicatie bij import naar server met bestaande data | Kritieke waarschuwing + database reset instructie toegevoegd | §6.4 |

### Eerder Opgeloste Bevindingen

| FO ID | Beschrijving | Resolutie in TO | Sectie |
|-------|--------------|-----------------|--------|
| FO-001 | Geen mechanisme om delta tussen machines te identificeren | Diff-rapport script (zie FO §Merge & Conflict Strategie) | FO |
| FO-002 | File paths breken bij verschillende home directories | File fields werken niet na centralisatie (gedocumenteerd) | FO §Impact |
| FO-005 | Decision points D1-D3 niet afgesloten | Alle besluitpunten afgesloten met concrete opties | §2 |
| FO-006 | Test database isolatie | Test database in zelfde Docker setup, aparte DB | §4.3 |
| FO-007 | Atomiciteit mid-request disconnect | Gedocumenteerd als "mogelijk partial write", acceptabel risico | FO §Gedrag bij Uitval |
| FO-008 | root_directory update | N/A - file fields werken niet na centralisatie | FO §Impact |
| FO-009 | jq dependency | Toegevoegd aan prerequisites | §3 |
| FO-010 | D3 canonieke bron criteria | Concreet: `MAX(updated_at)` over kernentiteiten | §6.1 |
| FO-011 | Geen initiële backup restore test | Toegevoegd als AC-11 | §12.2 |
| FO-012 | UX bij "losing" client in conflict | Gedocumenteerd: laatste save overschrijft, awareness via documentatie | FO §Concurrency |

---

## 14) Appendix

### 14.1 docker-compose.yml Referentie

De centrale server gebruikt dezelfde `docker-compose.yml` als het repository met twee belangrijke aanpassingen:

#### Aanpassing 1: NGINX poort binding (verplicht)

**Origineel (repository):**
```yaml
# pma_nginx service
ports:
  - "${NGINX_PORT:-8502}:80"  # Bindt aan 0.0.0.0 (alle interfaces)
```

**Centrale server (aanbevolen):**
```yaml
ports:
  - "${TAILSCALE_IP}:${NGINX_PORT:-8080}:80"  # Bindt alleen aan Tailscale interface
```

#### Aanpassing 2: MySQL poort verwijderen (aanbevolen)

**Origineel (repository):**
```yaml
# pma_mysql service
ports:
  - "${DB_PORT:-3306}:3306"  # ONVEILIG: MySQL exposed naar buiten
```

**Centrale server (aanbevolen):**
```yaml
# Verwijder of comment-out de ports sectie voor pma_mysql
# ports:
#   - "${DB_PORT:-3306}:3306"  # Niet nodig - MySQL alleen via Docker network
```

> **Rationale:** MySQL hoeft niet extern bereikbaar te zijn. Alle database toegang gaat via de applicatie container (pma_yii) binnen het Docker network. Door de port mapping te verwijderen is MySQL alleen bereikbaar binnen Docker, wat de security significant verbetert.

**Container namen (uit repo `docker-compose.yml`):**

| Container | Service | Functie |
|-----------|---------|---------|
| `pma_yii` | yii | PHP/Yii applicatie (PHP-FPM) |
| `pma_nginx` | nginx | Webserver (reverse proxy) |
| `pma_mysql` | mysql | MySQL 8.0 database |
| `pma_npm` | npm | Frontend build (alleen voor development) |

**Belangrijke environment variabelen:**

| Variabele | Gebruikt door | Doel |
|-----------|---------------|------|
| `NGINX_PORT` | pma_nginx | HTTP poort (default: 8502) |
| `DB_ROOT_PASSWORD` | pma_mysql | MySQL root password |
| `DB_HOST` | pma_yii | Database hostname (`pma_mysql`) |
| `DB_DATABASE` | pma_mysql, pma_yii | Database naam |
| `DB_USER` | pma_mysql, pma_yii | Database gebruiker |
| `DB_PASSWORD` | pma_mysql, pma_yii | Database wachtwoord |

Voor de volledige `docker-compose.yml` zie het repository.

### 14.2 .env Template (Server)

```bash
# PromptManager Centrale Server Configuration
# Gebaseerd op .env.example uit repository

# User settings (voor docker container permissions)
USER_ID=1000
USER_NAME=appuser
TIMEZONE=Europe/Amsterdam

# Database credentials
DB_ROOT_PASSWORD=<genereer-sterk-password>
DB_HOST=pma_mysql
DB_DATABASE=promptmanager
DB_DATABASE_TEST=promptmanager_test
DB_USER=promptmanager
DB_PASSWORD=<genereer-sterk-password>

# Network ports
NGINX_PORT=8080           # HTTP poort voor centrale server
DB_PORT=3306              # MySQL poort (niet exposed naar buiten)
PHP_FPM_PORT=9000

# Tailscale binding (optioneel, voor extra beveiliging)
# Verkrijg met: tailscale ip -4
TAILSCALE_IP=100.x.x.x

# Xdebug (uitzetten voor productie)
XDEBUG_MODE=off
XDEBUG_START_WITH_REQUEST=no

# Identity module
IDENTITY_DISABLE_CAPTCHA=FALSE
```

**Verschil met lokale development:**
- `NGINX_PORT=8080` (centrale server) vs `8502/8503` (lokale development)
- `XDEBUG_MODE=off` (productie)
- `TAILSCALE_IP` toegevoegd voor firewall binding

### 14.3 UFW Rules Summary

```bash
sudo ufw status numbered
# Status: active
#
#      To                         Action      From
#      --                         ------      ----
# [ 1] 22/tcp                     ALLOW IN    Anywhere
# [ 2] 8080                       ALLOW IN    on tailscale0
```

### 14.4 Quick Reference Card

**Server:**
```bash
# Start/stop
docker compose up -d
docker compose down

# Logs
docker logs pma_yii --tail 50
docker logs pma_mysql --tail 50
docker logs pma_nginx --tail 50

# Status
docker compose ps
tailscale status

# Security check (moet Tailscale IP tonen, niet 0.0.0.0)
ss -tlnp | grep ${NGINX_PORT:-8080}

# Backup
/opt/scripts/mysql-backup.sh

# Health check
/opt/scripts/healthcheck.sh
```

**Client:**
```bash
# Verbinden
tailscale up

# Status
tailscale status
tailscale ping ubuntu-server

# Applicatie
# Open browser: http://ubuntu-server:8080
```

**Security Verificatie:**
```bash
# Check dat NGINX gebonden is aan Tailscale IP (niet 0.0.0.0)
ss -tlnp | grep 8080
# Verwacht: 100.x.x.x:8080 (Tailscale IP)

# Test van extern (moet falen)
curl -I http://<PUBLIC_IP>:8080
# Verwacht: Connection refused
```

---

## Document History

| Versie | Datum | Wijzigingen |
|--------|-------|-------------|
| 2.5 | 2026-01-19 | Rollback script .env sourcing, empty-DB check valideert alle core tabellen, resterende hardcoded 8080 gefixed |
| 2.4 | 2026-01-19 | Script fixes: .env sourcing in alle migratie scripts, verwijder --insert-ignore, lege DB check in import, NGINX_PORT variabele i.p.v. hardcoded 8080 |
| 2.3 | 2026-01-19 | Alignment met FO v2.2: Data Integriteit Model sectie (§6.0), lege database waarschuwing (§6.4), FO-003/FO-004 resoluties |
| 2.2 | 2026-01-19 | MySQL port exposure fix (verwijder DB_PORT mapping), decommissioning fix (bind mount i.p.v. Docker volume), DOCKER-USER port variabel |
| 2.1 | 2026-01-19 | Alignment met actual repo: container names (pma_mysql, pma_nginx), env vars (DB_ROOT_PASSWORD), Docker firewall hardening (§4.4.1) |
| 2.0 | 2026-01-18 | Volledig herschreven voor centrale webapplicatie architectuur |
| 1.0 | 2026-01-18 | Initiële versie (centrale database architectuur) |
