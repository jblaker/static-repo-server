# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Docker-based server that serves static files from a private GitHub repo behind a login wall. Nginx handles routing and delegates auth checks to PHP-FPM via `auth_request`. Admins can trigger a live `git pull` + rsync via `POST /refresh`.

## Build and run

```bash
# Build and start
docker compose up -d --build

# View logs
docker compose logs -f

# Rebuild after changes
docker compose up -d --build
```

Site runs at http://localhost:8081 (mapped from container port 80).

## Configuration

Before running, edit `docker-compose.yml`:
- `REPO_URL` — SSH clone URL (`git@github.com:user/repo.git`)
- `REPO_BRANCH` — branch to track (default: `main`)
- `REPO_SUBDIR` — optional subdirectory to serve (e.g. `public`)

The SSH private key at `~/.ssh/id_ed25519` must be a GitHub Deploy Key on the target repo.

## User management

Users are in `data/users.txt` (format: `username:bcrypt_hash:role`, roles: `user` or `admin`). The file is bind-mounted read-only — edit it on the host, no rebuild needed, changes apply on next login.

Generate a bcrypt hash:
```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT) . PHP_EOL;"
```

## Architecture

```
Browser → Nginx (port 80)
             ├── /login, /logout, /refresh  → PHP-FPM (/app/*.php)
             ├── /auth (internal)           → php/auth_check.php (session check)
             └── /* (static files)          → /var/www/html  (auth-gated via auth_request)
```

**Request flow for static files:**
1. Nginx receives request for `/*`
2. Makes an internal subrequest to `/auth` → `auth_check.php`
3. `auth_check.php` returns 200 (valid session) or 401 (no session)
4. On 401, nginx redirects to `/login?next=<original_uri>`
5. On 200, nginx serves the file from `/var/www/html`

**Startup flow (`scripts/start.sh`):**
1. Clones the repo into `/repo` (or pulls if it already exists)
2. Copies SSH credentials to `www-data`'s home so PHP-FPM can run `git pull`
3. Rsyncs `/repo` (or `REPO_SUBDIR`) → `/var/www/html`
4. Injects `REPO_BRANCH`/`REPO_SUBDIR` env vars into PHP-FPM's pool config (since `clear_env=yes` strips them by default)
5. Starts PHP-FPM as a daemon, then nginx in the foreground

**Refresh flow (`php/refresh.php`):**
- Admin-only (checks `$_SESSION['role'] === 'admin'`)
- Runs `git pull` in `/repo` then rsyncs to `/var/www/html`
- Logs results to `/data/refresh.log` (volume-mounted to `./data/`)
- Accepts GET or POST

## Key paths inside the container

| Path | Purpose |
|---|---|
| `/repo` | Cloned GitHub repo |
| `/var/www/html` | Nginx web root (rsynced from `/repo`) |
| `/app/*.php` | PHP application files |
| `/data/users.txt` | User database (bind-mounted) |
| `/data/refresh.log` | Refresh audit log |
| `/var/lib/php/sessions` | PHP sessions (lost on restart unless volume-mounted) |
| `/var/www/.ssh` | SSH credentials copied for `www-data` at startup |
