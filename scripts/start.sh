#!/bin/bash
set -e

# -------------------------------------------------------
# Environment variables expected at runtime:
#   REPO_URL   — SSH clone URL, e.g. git@github.com:user/repo.git
#   REPO_SUBDIR — (optional) subdirectory within repo to serve
#                 if set, only that subdir is symlinked to webroot
# -------------------------------------------------------

REPO_DIR="/repo"
WEB_ROOT="/var/www/html"
REPO_BRANCH="${REPO_BRANCH:-main}"

# Always trust this directory regardless of which user owns it
git config --global --add safe.directory "$REPO_DIR"

echo "[start] Cloning/updating repo..."

if [ -z "$REPO_URL" ]; then
	echo "[start] ERROR: REPO_URL environment variable is not set."
	exit 1
fi

if [ -d "$REPO_DIR/.git" ]; then
	echo "[start] Repo exists, pulling latest..."
	cd "$REPO_DIR" && git pull origin "$REPO_BRANCH"
else
	echo "[start] Cloning $REPO_URL (branch: $REPO_BRANCH)..."
	git clone --branch "$REPO_BRANCH" "$REPO_URL" "$REPO_DIR"
fi

# Give www-data ownership of the repo so PHP-FPM can run git pull
chown -R www-data:www-data "$REPO_DIR"

# Copy SSH config to www-data so it can authenticate with GitHub
mkdir -p /var/www/.ssh
cp /root/.ssh/id_ed25519 /var/www/.ssh/id_ed25519
cp /root/.ssh/known_hosts /var/www/.ssh/known_hosts
chmod 700 /var/www/.ssh
chmod 600 /var/www/.ssh/id_ed25519
chmod 644 /var/www/.ssh/known_hosts
chown -R www-data:www-data /var/www/.ssh

# Point web root at repo (or subdir)
if [ -n "$REPO_SUBDIR" ]; then
	SRC="$REPO_DIR/$REPO_SUBDIR"
else
	SRC="$REPO_DIR"
fi

# Sync content into webroot
echo "[start] Syncing $SRC -> $WEB_ROOT"
rsync -a --delete "$SRC/" "$WEB_ROOT/"

# Fix webroot permissions
chown -R www-data:www-data "$WEB_ROOT"

# Pass runtime env vars through to PHP-FPM workers
# (clear_env=yes by default strips them otherwise)
sed -i 's/^;clear_env = no/clear_env = no/' /etc/php/8.2/fpm/pool.d/www.conf
echo "env[REPO_BRANCH] = $REPO_BRANCH" >> /etc/php/8.2/fpm/pool.d/www.conf
if [ -n "$REPO_SUBDIR" ]; then
	echo "env[REPO_SUBDIR] = $REPO_SUBDIR" >> /etc/php/8.2/fpm/pool.d/www.conf
fi

# Start PHP-FPM
echo "[start] Starting php8.2-fpm..."
php-fpm8.2 --daemonize

# Start nginx in foreground
echo "[start] Starting nginx..."
exec nginx -g "daemon off;"
