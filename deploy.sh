#!/bin/bash
set -euo pipefail

# Define server and project details
SERVER="deploy@84.247.186.143"
#SERVER="root@195.181.245.125"
REMOTE_DIR="/var/www/api"

ensure_rsync() {
    if command -v rsync >/dev/null 2>&1; then
        return 0
    fi

    echo "rsync not found — attempting to install..."
    if command -v apt-get >/dev/null 2>&1; then
        if command -v sudo >/dev/null 2>&1; then
            sudo apt-get update -qq
            sudo apt-get install -y -qq rsync openssh-client
        elif [ "$(id -u)" -eq 0 ]; then
            apt-get update -qq
            apt-get install -y -qq rsync openssh-client
        fi
    fi

    if ! command -v rsync >/dev/null 2>&1; then
        echo "ERROR: rsync is required but not installed. Install it (e.g. apt-get install -y rsync) and retry."
        exit 1
    fi
}

ensure_rsync

# Check for private key
SSH_KEY=""
if [ -f "deploy_key" ]; then
    chmod 600 deploy_key
    SSH_KEY="-i deploy_key"
fi

# Build SSH options
SSH_OPTS="-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null"
if [ -n "$SSH_KEY" ]; then
    SSH_OPTS="$SSH_KEY $SSH_OPTS"
fi

# Sync project files to the server
rsync -avz -e "ssh $SSH_OPTS" \
  --include='storage/' \
  --include='storage/app/' \
  --include='storage/app/flyers-incoming/' \
  --include='storage/app/flyers-incoming/***' \
  --include='storage/app/public/' \
  --include='storage/app/public/flyers/' \
  --include='storage/app/public/flyers/pdfs/' \
  --include='storage/app/public/flyers/pdfs/***' \
  --exclude='storage/logs/**' \
  --exclude='storage/framework/cache/**' \
  --exclude='storage/framework/sessions/**' \
  --exclude='storage/framework/views/**' \
  --exclude='bootstrap/cache/**' \
  --exclude='public/build/**' \
  --exclude='public/hot' \
  --exclude='.phpunit.result.cache' \
  --exclude='.cursor' \
  --exclude='.env' \
  --exclude='storage' \
  --exclude='vendor' \
  --exclude='node_modules' \
  --exclude='.idea' \
  --exclude='.git' \
  . "$SERVER:$REMOTE_DIR"

# Run Laravel commands on the server
ssh $SSH_OPTS $SERVER << 'EOF'
    cd /var/www/api

    if [ ! -f .env ]; then
        echo "Missing .env on server"
        exit 1
    fi

    if grep -q '^APP_ENV=' .env; then
        sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
    else
        echo 'APP_ENV=production' >> .env
    fi

    if grep -q '^APP_DEBUG=' .env; then
        sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
    else
        echo 'APP_DEBUG=false' >> .env
    fi

    # Wrong APP_URL (e.g. http://localhost) bakes localhost into @vite() asset
    # URLs and JSON-LD — browsers then block app.js with CORS / LNA prompts.
    PROD_APP_URL="${PROD_APP_URL:-https://api.liachovskis.com}"
    if grep -q '^APP_URL=' .env; then
        sed -i "s|^APP_URL=.*|APP_URL=${PROD_APP_URL}|" .env
    else
        echo "APP_URL=${PROD_APP_URL}" >> .env
    fi

    # Group-based permissions (one-time server setup: `deploy` added to the
    # www-data group, setgid set on storage/bootstrap/cache/vendor so new
    # files/dirs inherit the www-data group automatically) — this replaces
    # the old chown-to-www-data-on-every-deploy approach entirely. That
    # approach needed root (deploy has no passwordless sudo, and an
    # automated SSH script has no TTY to prompt for one), and even with
    # root it raced against php-fpm continuously creating new session files
    # on a live site. umask 0002 here means every file this script creates
    # from now on is group-writable by default, so php-fpm (www-data) can
    # always read/write what deploy creates and vice versa — no chown, no
    # sudo, nothing to race.
    umask 0002

    mkdir -p storage/app/flyers-incoming
    mkdir -p storage/app/temp/flyers
    mkdir -p storage/logs
    mkdir -p storage/framework/cache
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p bootstrap/cache
    mkdir -p vendor

    # Never serve Vite dev-server URLs in production.
    rm -f public/hot

    if [ -f deploy_key ]; then
        chmod 600 deploy_key
    fi

    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
    php artisan optimize:clear
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache

    # Frontend now lives in this repo (Blade/Alpine/Livewire) — build its Vite
    # assets before view:cache, since @vite() needs public/build/manifest.json
    # present or the cached views bake in a stale/missing manifest reference.
    npm ci
    npm run build

    php artisan view:cache

    php artisan queue:restart

    # Restart php-fpm BEFORE warming: php-fpm's opcache has
    # opcache.validate_timestamps=0 (never re-checks file mtimes), so any
    # page rendered through it keeps using whatever bytecode it already had
    # compiled for that file path until the pool restarts. cache:warm below
    # renders every guest page over real HTTP (through nginx -> php-fpm) and
    # bakes the result into Redis with a 1h TTL — warming before the restart
    # would silently cache pre-deploy markup for up to an hour on every
    # deploy that touches a view/controller (caught this deploying a Blade
    # fix: the fix was on disk and view:cache'd, but every cached page kept
    # serving the pre-fix HTML until the next unrelated fpm restart).
    sudo systemctl restart php8.4-fpm

    # PageHtmlCache keys include CacheVersion suffix — bump so stale HTML
    # (e.g. baked with wrong APP_URL) is not served after deploy, then warm
    # guest listing HTML here so the first visitor doesn't pay a cold render.
    php artisan cache:clear-discounts
    php artisan cache:warm --type=page-html

    # No explicit supervisorctl restart needed for the flyers queue worker:
    # queue:restart above already signals every queue worker (not just the
    # default queue) to gracefully exit after its current job, and
    # supervisor's autorestart=true (deploy/supervisor-nuolaidos-flyers.conf)
    # brings it back up running the freshly deployed code. An explicit
    # `sudo supervisorctl restart nuolaidos-flyers` here always failed
    # anyway (no TTY for the sudo password over a non-interactive SSH
    # heredoc) — it was a no-op wrapped in `|| true`, not a working restart.
EOF

# NOTE (Phase 7, manual/one-time, not automated here): the public domain's
# nginx vhost still needs to be pointed at this app (deploy/nginx-public-domain.conf)
# instead of proxying to the old Next.js/PM2 app on port 3000. That vhost swap
# is a deliberate server-side step for whenever cutover happens — deploy.sh
# does not touch nginx config or DNS. Until then, /var/www/nuolaidos-front and
# its PM2 process are left running untouched by this script, by design, so
# the old and new frontends can be compared side by side before cutover.

mkdir -p storage/app/flyers-incoming
find storage/app/flyers-incoming -mindepth 1 -delete 2>/dev/null || true

echo "Deployment completed successfully!"
