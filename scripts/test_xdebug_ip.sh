#!/usr/bin/env bash
set -euo pipefail

# Quick connectivity check for Xdebug from the pma_yii container.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
SERVICE_NAME="pma_yii"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing .env in $ROOT_DIR"
  exit 1
fi

read_env_var() {
  local key=$1
  grep -E "^${key}=" "$ENV_FILE" | tail -n1 | cut -d= -f2-
}

detect_gateway_ip() {
  local ip
  ip=$(ip route show default 0.0.0.0/0 2>/dev/null | awk 'NR==1 {print $3; exit}')
  if [[ -z "$ip" ]]; then
    ip=$(ip route show 2>/dev/null | awk '/default/ {print $3; exit}')
  fi
  if [[ -z "$ip" && -f /etc/resolv.conf ]]; then
    ip=$(grep -m1 '^nameserver' /etc/resolv.conf | awk '{print $2}')
  fi
  echo "$ip"
}

CURRENT_IP="$(detect_gateway_ip)"
if [[ -z "$CURRENT_IP" ]]; then
  echo "Failed to determine current host IP (default gateway)."
  exit 1
fi

XDEBUG_HOST="$(read_env_var "XDEBUG_CLIENT_HOST")"
XDEBUG_PORT="$(read_env_var "XDEBUG_PORT")"
[[ -z "$XDEBUG_PORT" ]] && XDEBUG_PORT="$(read_env_var "XDEBUG_CLIENT_PORT")"
[[ -z "$XDEBUG_HOST" ]] && XDEBUG_HOST="host.docker.internal"
[[ -z "$XDEBUG_PORT" ]] && XDEBUG_PORT="9003"

echo "Detected host IP: $CURRENT_IP"
echo "XDEBUG_CLIENT_HOST in .env: $XDEBUG_HOST"
echo "XDEBUG_PORT in .env: $XDEBUG_PORT"

if [[ "$CURRENT_IP" != "$XDEBUG_HOST" ]]; then
  echo "WARNING: Current host IP differs from .env. Update XDEBUG_CLIENT_HOST if needed."
fi

CONTAINER_ID="$(cd "$ROOT_DIR" && docker compose ps -q "$SERVICE_NAME" || true)"
if [[ -z "$CONTAINER_ID" ]]; then
  echo "Container $SERVICE_NAME is not running. Start it with: docker compose up -d $SERVICE_NAME"
  exit 1
fi

echo "Testing connection from $SERVICE_NAME to $XDEBUG_HOST:$XDEBUG_PORT ..."
if cd "$ROOT_DIR" && docker compose exec -T "$SERVICE_NAME" bash -c "nc -z -w 2 $XDEBUG_HOST $XDEBUG_PORT" >/dev/null 2>&1; then
  echo "Connection successful."
else
  echo "Connection failed. Ensure PHPStorm is listening on $XDEBUG_PORT and the host IP is correct."
fi
