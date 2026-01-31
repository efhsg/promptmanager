#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROFILE_DIR="$SCRIPT_DIR"
ACTIVE_FILE="$PROFILE_DIR/.env.db"
ENV_FILE="$SCRIPT_DIR/.env"

SSH_HOST="zenbook"
# Docker-exposed MySQL port on the remote host (zenbook maps 3307->3306 in its docker-compose)
REMOTE_FORWARD_PORT=3307

# Read tunnel port from remote profile
get_tunnel_port() {
    local port
    port=$(grep -E '^DB_APP_PORT=' "$PROFILE_DIR/.env.db.remote" 2>/dev/null | cut -d= -f2)
    echo "${port:-3307}"
}

# Set COMPOSE_PROFILES in .env so docker compose starts the right services
set_compose_profiles() {
    local profiles="$1"
    if [[ -z "$profiles" ]]; then
        sed -i '/^COMPOSE_PROFILES=/d' "$ENV_FILE"
    elif grep -q '^COMPOSE_PROFILES=' "$ENV_FILE" 2>/dev/null; then
        sed -i "s/^COMPOSE_PROFILES=.*/COMPOSE_PROFILES=$profiles/" "$ENV_FILE"
    else
        echo "COMPOSE_PROFILES=$profiles" >> "$ENV_FILE"
    fi
}

# --- Tunnel management ---

tunnel_pid() {
    local port
    port=$(get_tunnel_port)
    pgrep -f "ssh.*0\\.0\\.0\\.0:${port}:localhost:${REMOTE_FORWARD_PORT}.*${SSH_HOST}" 2>/dev/null || true
}

tunnel_is_running() {
    [[ -n "$(tunnel_pid)" ]]
}

tunnel_port_listening() {
    local port
    port=$(get_tunnel_port)
    ss -tln 2>/dev/null | grep -q ":${port} " 2>/dev/null
}

tunnel_start() {
    local port
    port=$(get_tunnel_port)

    if tunnel_is_running; then
        echo "SSH tunnel already running (pid: $(tunnel_pid))"
        return 0
    fi

    echo "Starting SSH tunnel (0.0.0.0:${port} -> ${SSH_HOST}:${REMOTE_FORWARD_PORT})..."
    ssh -fNL "0.0.0.0:${port}:localhost:${REMOTE_FORWARD_PORT}" "$SSH_HOST"

    # Wait for port to become available
    local retries=10
    while (( retries > 0 )); do
        if tunnel_port_listening; then
            echo "SSH tunnel started (pid: $(tunnel_pid), port: ${port})"
            return 0
        fi
        sleep 0.5
        (( retries-- ))
    done

    echo "Error: tunnel started but port ${port} not listening"
    return 1
}

tunnel_stop() {
    local pid
    pid=$(tunnel_pid)

    if [[ -z "$pid" ]]; then
        echo "No SSH tunnel running"
        return 0
    fi

    echo "Stopping SSH tunnel (pid: ${pid})..."
    kill $pid 2>/dev/null || true

    # Wait for process to exit
    local retries=10
    while (( retries > 0 )); do
        if ! tunnel_is_running; then
            echo "SSH tunnel stopped"
            return 0
        fi
        sleep 0.3
        (( retries-- ))
    done

    echo "Warning: tunnel process did not exit cleanly, sending SIGKILL"
    kill -9 $pid 2>/dev/null || true
}

tunnel_show_status() {
    local port
    port=$(get_tunnel_port)

    if tunnel_is_running; then
        echo "Tunnel: running (pid: $(tunnel_pid))"
    else
        echo "Tunnel: stopped"
    fi

    if tunnel_port_listening; then
        echo "Port ${port}: listening"
    else
        echo "Port ${port}: not listening"
    fi
}

# --- Main ---

usage() {
    echo "Usage: $0 {local|remote|status}"
    echo ""
    echo "  local   — Docker MySQL container (pma_mysql:3306)"
    echo "  remote  — Zenbook via SSH tunnel (host.docker.internal:3307)"
    echo "  status  — Show active profile + tunnel status"
    exit 1
}

show_status() {
    if [[ ! -f "$ACTIVE_FILE" ]]; then
        echo "No active profile (.env.db not found)"
        echo "Run: $0 local  or  $0 remote"
        exit 1
    fi

    # Detect profile by DB_HOST value
    local host
    host=$(grep -E '^DB_HOST=' "$ACTIVE_FILE" | cut -d= -f2)

    case "$host" in
        pma_mysql)
            echo "Active profile: local (Docker MySQL)"
            ;;
        host.docker.internal)
            echo "Active profile: remote (Zenbook via SSH tunnel)"
            ;;
        *)
            echo "Active profile: unknown (DB_HOST=$host)"
            ;;
    esac

    echo ""
    grep -v '^#' "$ACTIVE_FILE" | grep -v '^$'
    echo ""
    tunnel_show_status
}

switch_profile() {
    local profile="$1"
    local source_file="$PROFILE_DIR/.env.db.$profile"

    if [[ ! -f "$source_file" ]]; then
        echo "Error: profile file not found: $source_file"
        exit 1
    fi

    cp "$source_file" "$ACTIVE_FILE"

    case "$profile" in
        local)
            set_compose_profiles "local"
            tunnel_stop
            ;;
        remote)
            set_compose_profiles ""
            # Stop MySQL container if running (frees the tunnel port)
            if docker inspect pma_mysql &>/dev/null && [[ "$(docker inspect -f '{{.State.Running}}' pma_mysql 2>/dev/null)" == "true" ]]; then
                echo "Stopping pma_mysql..."
                docker stop pma_mysql >/dev/null
            fi
            tunnel_start
            ;;
    esac

    echo ""
    echo "Switched to profile: $profile"
    echo ""
    grep -v '^#' "$ACTIVE_FILE" | grep -v '^$'

    echo ""
    echo "Ready. Run: docker compose up -d"
}

[[ $# -lt 1 ]] && usage

case "$1" in
    local|remote)
        switch_profile "$1"
        ;;
    status)
        show_status
        ;;
    *)
        usage
        ;;
esac
