#!/bin/bash
set -euo pipefail

# Define server and project details
#SERVER="root@84.247.186.143"
SERVER="root@195.181.245.125"
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
  --exclude='storage/logs/**' \
  --exclude='storage/framework/cache/**' \
  --exclude='storage/framework/sessions/**' \
  --exclude='storage/framework/views/**' \
  --exclude='bootstrap/cache/**' \
  --exclude='.phpunit.result.cache' \
  --exclude='.cursor' \
  --exclude='.env' \
  --exclude='storage' \
  --exclude='vendor' \
  --exclude='node_modules' \
  --exclude='.idea' \
  --exclude='.git' \
  . "$SERVER:$REMOTE_DIR"

# Upload flyer images
FRONTEND_SERVER="deploy@84.247.186.143"
REMOTE_IMAGES_DIR="/var/www/images"
ssh $SSH_OPTS $FRONTEND_SERVER "mkdir -p $REMOTE_IMAGES_DIR"
rsync -avz --omit-dir-times --no-perms --no-owner --no-group -e "ssh $SSH_OPTS" storage/app/public/products/ $FRONTEND_SERVER:$REMOTE_IMAGES_DIR/

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

    mkdir -p storage/app/flyers-incoming
    mkdir -p storage/app/temp/flyers
    mkdir -p storage/logs
    mkdir -p storage/framework/cache
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p bootstrap/cache

    chown -R www-data:www-data storage bootstrap/cache
    chmod -R ug+rwx storage bootstrap/cache

    if [ -f deploy_key ]; then
        chown www-data:www-data deploy_key
        chmod 600 deploy_key
    fi

    if [ -d scripts ]; then
        chown -R www-data:www-data scripts
    fi

    mkdir -p vendor
    chown -R www-data:www-data vendor storage bootstrap/cache
    chmod -R ug+rwx storage bootstrap/cache

    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
    chown -R www-data:www-data vendor
    chmod -R u+rwX vendor
    sudo -u www-data php artisan optimize:clear
    sudo -u www-data php artisan migrate --force
    sudo -u www-data php artisan config:cache
    sudo -u www-data php artisan route:cache
    sudo -u www-data php artisan event:cache
    sudo -u www-data php artisan view:cache
    sudo -u www-data php artisan cache:warm --type=all
    sudo -u www-data php artisan queue:restart

    sudo systemctl restart php8.4-fpm

    supervisorctl restart nuolaidos-flyers || true
EOF

# Restart frontend + clear Next.js cache (in-memory stores cache + ISR)
ssh $SSH_OPTS $FRONTEND_SERVER << 'EOF'
    cd /var/www/nuolaidos-front/
    mkdir -p /var/www/nuolaidos-front/public/assets
    ln -sfn /var/www/images /var/www/nuolaidos-front/public/assets/product
    ./scripts/production-deploy.sh restart
    ./scripts/production-deploy.sh revalidate
EOF

mkdir -p storage/app/flyers-incoming
find storage/app/flyers-incoming -mindepth 1 -delete 2>/dev/null || true

echo "Deployment completed successfully!"
