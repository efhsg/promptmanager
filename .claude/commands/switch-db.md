---
allowed-tools: Bash
description: Switch database profile (local/remote/status)
---

# Switch Database Profile

Switch between local Docker MySQL and remote Zenbook database via `switch-db.sh`.

The script automatically manages the SSH tunnel lifecycle — no manual tunnel setup needed.

## Task

Run the database profile switcher with the provided argument.

### Input

`$ARGUMENTS` must be one of:
- `local` — Docker MySQL container (pma_mysql:3306). Stops any running SSH tunnel.
- `remote` — Zenbook via SSH tunnel (host.docker.internal:3307). Starts tunnel automatically (binds `0.0.0.0:3307`).
- `status` — Show active profile + tunnel status (running/stopped, port listening/not)

### Steps

1. **Validate argument** — if `$ARGUMENTS` is empty or not one of `local`, `remote`, `status`, ask which profile to activate.

2. **Run switcher**:

```bash
./switch-db.sh $ARGUMENTS
```

3. **Report result** — show the output and remind to restart containers:
   - `local`: `docker compose up -d`
   - `remote`: `docker compose up -d` (tunnel is already running)
