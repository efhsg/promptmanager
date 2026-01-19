#!/bin/bash
#
# Verificatie script voor database sync setup
# Controleert alle vereisten voor bidirectionele sync met zenbook
#

set -e

# Kleuren voor output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Laad .env als die bestaat
if [ -f .env ]; then
    export $(grep -v '^#' .env | grep -E '^SYNC_' | xargs)
fi

# Defaults
REMOTE_HOST="${SYNC_REMOTE_HOST:-}"
REMOTE_USER="${SYNC_REMOTE_USER:-esg}"
REMOTE_DB_PASSWORD="${SYNC_REMOTE_DB_PASSWORD:-}"
REMOTE_DB_NAME="${SYNC_REMOTE_DB_NAME:-yii}"
LOCAL_PORT=33099  # Test port

echo "======================================"
echo "  Database Sync Setup Verificatie"
echo "======================================"
echo ""

# Track failures
FAILURES=0

# Helper functions
check_pass() {
    echo -e "  ${GREEN}✓${NC} $1"
}

check_fail() {
    echo -e "  ${RED}✗${NC} $1"
    FAILURES=$((FAILURES + 1))
}

check_warn() {
    echo -e "  ${YELLOW}!${NC} $1"
}

# 1. Check environment variables
echo "1. Environment variabelen"
echo "   ----------------------"

if [ -z "$REMOTE_HOST" ]; then
    check_fail "SYNC_REMOTE_HOST niet geconfigureerd in .env"
else
    check_pass "SYNC_REMOTE_HOST=$REMOTE_HOST"
fi

if [ -z "$REMOTE_DB_PASSWORD" ]; then
    check_fail "SYNC_REMOTE_DB_PASSWORD niet geconfigureerd in .env"
else
    check_pass "SYNC_REMOTE_DB_PASSWORD is geconfigureerd"
fi

check_pass "SYNC_REMOTE_USER=$REMOTE_USER"
check_pass "SYNC_REMOTE_DB_NAME=$REMOTE_DB_NAME"
echo ""

# Stop hier als essentiële config ontbreekt
if [ -z "$REMOTE_HOST" ] || [ -z "$REMOTE_DB_PASSWORD" ]; then
    echo -e "${RED}Configureer eerst de environment variabelen in .env${NC}"
    echo ""
    echo "Voeg toe aan .env:"
    echo "  SYNC_REMOTE_HOST=100.x.x.x"
    echo "  SYNC_REMOTE_USER=esg"
    echo "  SYNC_REMOTE_DB_PASSWORD=<mysql-root-password>"
    echo "  SYNC_REMOTE_DB_NAME=yii"
    exit 1
fi

# 2. Check Tailscale
echo "2. Tailscale"
echo "   ---------"

if command -v tailscale &> /dev/null; then
    check_pass "Tailscale CLI geïnstalleerd"

    if tailscale status &> /dev/null; then
        check_pass "Tailscale is actief"
        LOCAL_IP=$(tailscale ip -4 2>/dev/null || echo "onbekend")
        check_pass "Lokaal Tailscale IP: $LOCAL_IP"
    else
        check_fail "Tailscale is niet actief (run: tailscale up)"
    fi
else
    check_fail "Tailscale CLI niet gevonden"
fi
echo ""

# 3. Check SSH
echo "3. SSH Verbinding"
echo "   --------------"

if command -v ssh &> /dev/null; then
    check_pass "SSH client geïnstalleerd"
else
    check_fail "SSH client niet gevonden"
    exit 1
fi

# Check SSH key
if [ -f ~/.ssh/id_ed25519 ]; then
    check_pass "SSH key gevonden: ~/.ssh/id_ed25519"
elif [ -f ~/.ssh/id_rsa ]; then
    check_warn "SSH key gevonden: ~/.ssh/id_rsa (ed25519 aanbevolen)"
else
    check_fail "Geen SSH key gevonden (run: ssh-keygen -t ed25519)"
fi

# Test SSH connection
echo "   Testen SSH verbinding naar $REMOTE_USER@$REMOTE_HOST..."
if ssh -o ConnectTimeout=5 -o BatchMode=yes "$REMOTE_USER@$REMOTE_HOST" "echo OK" &> /dev/null; then
    check_pass "SSH verbinding succesvol"
else
    check_fail "SSH verbinding mislukt"
    echo ""
    echo -e "   ${YELLOW}Mogelijke oplossingen:${NC}"
    echo "   - Controleer of Tailscale actief is op beide machines"
    echo "   - Voeg je public key toe aan zenbook:"
    echo "     ssh-copy-id $REMOTE_USER@$REMOTE_HOST"
    echo "   - Of handmatig:"
    echo "     cat ~/.ssh/id_ed25519.pub | ssh $REMOTE_USER@$REMOTE_HOST 'cat >> ~/.ssh/authorized_keys'"
fi
echo ""

# 4. Check SSH Tunnel
echo "4. SSH Tunnel"
echo "   ----------"

# Check if test port is available
if ! nc -z 127.0.0.1 $LOCAL_PORT 2>/dev/null; then
    check_pass "Test poort $LOCAL_PORT is beschikbaar"
else
    check_warn "Test poort $LOCAL_PORT is in gebruik, probeer andere poort"
    LOCAL_PORT=33098
fi

# Create tunnel
echo "   Aanmaken SSH tunnel op poort $LOCAL_PORT..."
ssh -f -N -L "$LOCAL_PORT:127.0.0.1:3306" -o ConnectTimeout=5 "$REMOTE_USER@$REMOTE_HOST" 2>/dev/null &
TUNNEL_PID=$!
sleep 2

# Check if tunnel is active
if nc -z 127.0.0.1 $LOCAL_PORT 2>/dev/null; then
    check_pass "SSH tunnel actief op poort $LOCAL_PORT"
    TUNNEL_OK=1
else
    check_fail "SSH tunnel kon niet worden opgezet"
    TUNNEL_OK=0
fi

# 5. Check MySQL connection via tunnel
echo ""
echo "5. MySQL Verbinding (via tunnel)"
echo "   -----------------------------"

if [ "$TUNNEL_OK" = "1" ]; then
    if command -v mysql &> /dev/null; then
        check_pass "MySQL client geïnstalleerd"

        echo "   Testen MySQL verbinding..."
        if mysql -h 127.0.0.1 -P "$LOCAL_PORT" -u root -p"$REMOTE_DB_PASSWORD" -e "SELECT 1" &> /dev/null; then
            check_pass "MySQL verbinding succesvol"

            # Check database exists
            if mysql -h 127.0.0.1 -P "$LOCAL_PORT" -u root -p"$REMOTE_DB_PASSWORD" -e "USE $REMOTE_DB_NAME" &> /dev/null; then
                check_pass "Database '$REMOTE_DB_NAME' bestaat"

                # Count projects
                PROJECT_COUNT=$(mysql -h 127.0.0.1 -P "$LOCAL_PORT" -u root -p"$REMOTE_DB_PASSWORD" -N -e "SELECT COUNT(*) FROM $REMOTE_DB_NAME.project" 2>/dev/null || echo "0")
                check_pass "Projecten in remote database: $PROJECT_COUNT"
            else
                check_fail "Database '$REMOTE_DB_NAME' niet gevonden"
            fi
        else
            check_fail "MySQL verbinding mislukt (controleer wachtwoord)"
        fi
    else
        check_warn "MySQL client niet geïnstalleerd (kan verbinding niet testen)"
        echo "   Install met: sudo apt install mysql-client"
    fi
else
    check_warn "Overgeslagen (tunnel niet actief)"
fi

# Cleanup: kill tunnel
pkill -f "ssh.*-L.*$LOCAL_PORT:127.0.0.1:3306" 2>/dev/null || true

# Summary
echo ""
echo "======================================"
echo "  Samenvatting"
echo "======================================"

if [ $FAILURES -eq 0 ]; then
    echo -e "${GREEN}Alle checks geslaagd!${NC}"
    echo ""
    echo "Je kunt nu de sync commando's gebruiken:"
    echo "  docker exec pma_yii ./yii sync/status"
    echo "  docker exec pma_yii ./yii sync/pull --dry-run"
    echo "  docker exec pma_yii ./yii sync/push --dry-run"
else
    echo -e "${RED}$FAILURES check(s) gefaald${NC}"
    echo ""
    echo "Los bovenstaande problemen op en run dit script opnieuw."
fi

exit $FAILURES
