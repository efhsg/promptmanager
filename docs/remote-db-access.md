# Remote Database Access

Connect to the PromptManager MySQL database from a remote machine using an SSH tunnel over Tailscale.

## Prerequisites

- Device authorized on the Tailscale tailnet
- SSH key access to the server (`esg@100.104.97.118` / `zenbook`)
- DB credentials from `.env` (`DB_USER`, `DB_PASSWORD`)

## Server Setup

MySQL container (`pma_mysql`) binds to `127.0.0.1:3307` on the host, forwarding to container port `3306`. It is not exposed to the network — SSH tunnel required.

## Option A: DBeaver (Built-in Tunnel)

DBeaver manages the SSH tunnel internally. No manual tunnel needed.

### SSH Tab

| Setting        | Value              |
|----------------|--------------------|
| Use SSH Tunnel | Enabled            |
| Host           | `100.104.97.118`   |
| Port           | `22`               |
| Username       | `esg`              |
| Authentication | Public Key          |
| Private Key    | `~/.ssh/id_ed25519` (or your key) |

### Main Tab

| Setting  | Value             |
|----------|-------------------|
| Host     | `127.0.0.1`       |
| Port     | `3307`            |
| Database | `promptmanager`   |
| Username | value of `DB_USER` from `.env` |
| Password | value of `DB_PASSWORD` from `.env` |

### SSL Tab

- **Use SSL**: unchecked

## Option B: Manual SSH Tunnel

Use this for CLI clients or tools without built-in SSH tunnel support.

Port `3307` is used locally by the dev MySQL container, so use `3308` to avoid conflicts.

### Open the tunnel

```bash
ssh -fNL 3308:127.0.0.1:3307 esg@100.104.97.118
```

- `-f` — background after authentication
- `-N` — no remote command (tunnel only)
- `-L 3308:127.0.0.1:3307` — local `3308` -> server's `127.0.0.1:3307`

### Connect

```bash
mysql -h 127.0.0.1 -P 3308 -u <DB_USER> -p <DB_DATABASE>
```

Or use any MySQL client with host `127.0.0.1`, port `3308`.

### Close the tunnel

```bash
kill $(pgrep -f "ssh.*3308.*100.104.97.118")
```

### Verify tunnel is active

```bash
ss -tlnp | grep 3308
```

## Security Layers

1. **Tailscale** — device must be authorized on the tailnet
2. **SSH** — public key authentication to the server
3. **MySQL** — database credentials required
4. **Localhost binding** — MySQL not exposed to network, only reachable via tunnel

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `bind: Address already in use` | Local port conflict | Use a different local port (e.g., `3308`) |
| SSH auth failed in DBeaver | Wrong key path or missing passphrase | Browse to correct private key, enter passphrase |
| `Expected to read 4 bytes, read 0 bytes` | SSL mismatch | Uncheck "Use SSL" in DBeaver SSL tab |
| `Can't connect to server (115)` | Tunnel not established | Run `ss -tlnp \| grep <port>` to check; retry SSH with `-v` for diagnostics |
| `tailscale ping` fails | Device not on tailnet | Check Tailscale is running and device is authorized |
