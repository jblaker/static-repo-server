# docker-static-site

Serves static files (HTML, CSS, JS, JSON) from a private GitHub repo, behind
a login wall. Admins can trigger a live refresh via `POST /refresh`.

## Architecture

```
Browser → Nginx (port 80)
             ├── /login, /logout, /refresh  → PHP-FPM (/app/*.php)
             ├── /auth (internal)           → PHP-FPM (session check)
             └── /* (static files)          → /var/www/html (auth-gated)
```

## Quick start

### 1. Add your SSH key to GitHub

Generate a key (if you don't have one):
```bash
ssh-keygen -t ed25519 -C "docker-static-site" -f ~/.ssh/id_ed25519
```

Add `~/.ssh/id_ed25519.pub` as a Deploy Key on your GitHub repo:
> Repo → Settings → Deploy keys → Add deploy key (read-only is fine)

### 2. Configure docker-compose.yml

Edit `docker-compose.yml`:
- Set `REPO_URL` to your repo's SSH clone URL
- Set `REPO_SUBDIR` if you only want to serve a subdirectory (e.g. `public`)
- Adjust the SSH key path if yours differs from `~/.ssh/id_ed25519`

### 3. Set up users

Edit `data/users.txt`. Generate bcrypt hashes:
```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT) . PHP_EOL;"
```

File format — one user per line:
```
username:bcrypt_hash:role
```
Roles: `user` (read-only) or `admin` (can trigger /refresh).

The users file is volume-mounted, so you can edit it on the host without
rebuilding the container. Changes take effect on next login attempt.

### 4. Build and run

```bash
docker compose up -d --build
```

Site will be at http://localhost:8080 (or your server's IP/domain).

## Triggering a refresh

Only `admin` users can trigger a refresh. Send a POST request:

```bash
# From the browser console (when logged in as admin):
fetch('/refresh', { method: 'POST' })
  .then(r => r.json())
  .then(console.log)
```

Or via curl (you'll need a valid session cookie):
```bash
curl -X POST http://localhost:8080/refresh \
  -b "PHPSESSID=your_session_id"
```

Response:
```json
{ "status": "ok", "message": "Already up to date.", "time": "2025-01-01 12:00:00" }
```

Refresh activity is logged to `data/refresh.log` on the host.

## Adding a refresh button to your site

If your static site is in the repo, you can add an admin-only button.
The simplest approach: add this snippet to any page and show/hide it based
on a session indicator you inject via PHP, or just always show it (the
endpoint enforces role checks server-side regardless).

```html
<button onclick="triggerRefresh()">↻ Refresh</button>
<script>
async function triggerRefresh() {
  const res = await fetch('/refresh', { method: 'POST' });
  const data = await res.json();
  alert(data.message || data.error);
}
</script>
```

## File structure

```
.
├── Dockerfile
├── docker-compose.yml
├── data/
│   ├── users.txt          # user database (host-managed)
│   └── refresh.log        # auto-created on first refresh
├── nginx/
│   └── site.conf          # nginx virtual host config
├── php/
│   ├── auth_check.php     # internal session validator
│   ├── login.php          # login form + handler
│   ├── logout.php         # session destroy
│   └── refresh.php        # git pull + rsync trigger
└── scripts/
    └── start.sh           # entrypoint: clone/pull, rsync, start services
```

## Updating users without rebuilding

The users file is bind-mounted from `./data/users.txt`. Edit it on the host
anytime — no restart needed. Changes apply on the next login attempt.

## Production notes

- Put this behind a reverse proxy (Traefik, Caddy, or nginx on the host)
  that terminates TLS. Do not run auth over plain HTTP in production.
- The SSH private key is mounted read-only and never baked into the image.
- Sessions are stored in `/var/lib/php/sessions` inside the container.
  They will be lost on container restart (users will need to log in again).
  Mount that path as a volume if you want sessions to survive restarts.
