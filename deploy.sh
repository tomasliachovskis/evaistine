#!/bin/bash
echo "deploy.sh still targets the evaistine.lt server; set up the vaistines server first." >&2; exit 1
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
rsync -avz --no-perms --no-owner --no-group -e "ssh $SSH_OPTS" \
  --include='storage/' \
  --include='storage/app/' \
  --include='storage/app/flyers-incoming/' \
  --include='storage/app/flyers-incoming/***' \
  --exclude='storage/app/public/' \
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

# Second pass, app/ only, WITH --delete: the main sync above never removes
# a file that no longer exists locally (plain rsync only adds/updates) —
# a deleted Command/Job/Service class physically stayed on the server
# indefinitely, and since Laravel's app/Console/Commands auto-discovery
# scans the filesystem regardless of git state, a deleted command kept
# working as a live, callable artisan command on prod for two full
# deploys after being deleted locally (confirmed live 2026-09-20,
# RemoveDuplicateActiveDiscounts.php). Scoped to app/ and resources/, not
# the whole project, to keep this deletion power narrow — neither has any
# excludes to worry about replicating here (checked: none of the excludes
# above fall under them). resources/ added 2026-09-28: deleted Blade views
# and JS kept piling up on the server (a dry run found 8, e.g. a removed
# content-freshness component and the retired price-index view) — harmless
# while unreferenced, but confusing when debugging on prod.
rsync -avz --no-perms --no-owner --no-group --delete -e "ssh $SSH_OPTS" \
  app/ "$SERVER:$REMOTE_DIR/app/"
rsync -avz --no-perms --no-owner --no-group --delete -e "ssh $SSH_OPTS" \
  resources/ "$SERVER:$REMOTE_DIR/resources/"

# Run Laravel commands on the server
ssh $SSH_OPTS $SERVER << 'EOF'
    # This heredoc is a SEPARATE remote bash invocation — the outer script's
    # `set -euo pipefail` (line 2) has no effect here at all. Without its
    # own copy, a failing step (e.g. the public/build-new swap below) was
    # observed live to just get silently skipped over while every later
    # command kept running, ending with the script printing "Deployment
    # completed successfully!" over a genuinely broken production site.
    set -euo pipefail

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
    PROD_APP_URL="${PROD_APP_URL:-https://evaistine.lt}"
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

    # Self-healing permission fix, no sudo needed: `deploy` already owns
    # these paths and is a member of the www-data group, so plain chmod is
    # enough (no chgrp/chown required). Setgid (g+s) on directories means
    # any file www-data (php-fpm) creates here — a Blade view compiled on
    # first real request instead of by view:cache below, a queue log, a
    # cache entry — automatically gets group www-data too, and g+w means
    # both deploy and www-data can always write/delete regardless of which
    # one created a given file. Without this, php-fpm gets "Permission
    # denied" trying to compile any view not already in view:cache — this
    # bit was getting silently reset to 0755 by an earlier version of this
    # script's rsync step (archive mode pushed the local machine's plain
    # 0755 storage/ permissions over the server's fixed ones every deploy)
    # — that's fixed too (see --no-perms above), this stays as a second
    # line of defense against any other future permission drift.
    find storage bootstrap/cache -type d -exec chmod g+ws {} + 2>/dev/null || true
    find storage bootstrap/cache -type f -exec chmod g+w {} + 2>/dev/null || true

    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
    php artisan optimize:clear
    # --force recreates existing links too, so this is safe to run on every
    # deploy — not just the first. config/filesystems.php's `links` includes
    # public/assets/product (preserves the pre-migration product image URL
    # shape for SEO — see DiscountResponseFormatter::resolveProductImageUrl()).
    php artisan storage:link --force
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache

    # Frontend now lives in this repo (Blade/Alpine/Livewire) — build its Vite
    # assets before view:cache, since @vite() needs public/build/manifest.json
    # present or the cached views bake in a stale/missing manifest reference.
    #
    # Building straight into public/build is NOT safe on a live server: Vite
    # empties that directory before writing the new assets, so real traffic
    # hitting a request during the build sees no manifest.json at all and
    # gets a 500 (seen live 6 times across today's deploys: 2026-08-27
    # 12:15, 2026-09-01 13:46/15:27/16:50/16:58). Build into a throwaway
    # directory instead, then swap it into place with two renames — a
    # rename is atomic on the same filesystem, so public/build is always
    # either the complete old build or the complete new one, never partial
    # or missing.
    npm ci
    rm -rf public/build-new
    npm run build -- --outDir public/build-new

    # Guard the swap explicitly instead of just trusting npm run build's own
    # exit code: seen live (2026-09-05) — the build reported success but
    # public/build-new didn't actually exist afterward (never fully root-
    # caused; a transient race with npm ci is the leading suspect). The
    # outer script's `set -euo pipefail` doesn't reach into this SSH heredoc
    # (a separate remote bash invocation), so without this check the
    # subsequent commands silently kept running on a broken state — the old
    # build got renamed away as "old" and then deleted by the cleanup line
    # below, leaving NO public/build at all while the script still printed
    # "Deployment completed successfully!".
    if [ ! -d public/build-new ] || [ ! -f public/build-new/manifest.json ]; then
        echo "ERROR: npm run build did not produce public/build-new/manifest.json — aborting before touching the live public/build" >&2
        exit 1
    fi

    if [ -d public/build ]; then
        mv public/build "public/build-old-$$"
    fi
    mv public/build-new public/build
    rm -rf "public/build-old-$$" 2>/dev/null || true

    # Second pass, right before view:cache: composer/npm above take long
    # enough that live traffic (still served by the old code during this
    # window) can trigger php-fpm (www-data) to compile a Blade view
    # on-demand mid-deploy. That file lands with whatever umask php-fpm's
    # systemd unit uses (typically 022, not this script's own umask 0002),
    # so it's often not group-writable — then view:cache's own attempt to
    # rewrite that exact cache filename fails with "Permission denied"
    # (seen live: 2026-09-01 deploy). The first chmod pass above runs too
    # early to catch a file created during composer/npm.
    find storage bootstrap/cache -type d -exec chmod g+ws {} + 2>/dev/null || true
    find storage bootstrap/cache -type f -exec chmod g+w {} + 2>/dev/null || true

    # Still an inherent race even right after the chmod above (seen live
    # again, same day: a request compiled a view in the split second between
    # the chmod pass and view:cache itself). view:cache aborts entirely on
    # the first unwritable file — a single unlucky file otherwise leaves
    # every OTHER view uncompiled too, not just that one. One retry after a
    # fresh chmod pass resolves it; the odds of the exact same race
    # happening twice in a row are negligible.
    php artisan view:cache || {
        echo "view:cache failed (permission race with a live request) — fixing perms and retrying once..."
        find storage bootstrap/cache -type d -exec chmod g+ws {} + 2>/dev/null || true
        find storage bootstrap/cache -type f -exec chmod g+w {} + 2>/dev/null || true
        php artisan view:cache
    }

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
