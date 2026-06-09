#!/bin/bash

# Define server and project details
#SERVER="root@84.247.186.143"
SERVER="root@195.181.245.125"
REMOTE_DIR="/var/www/api"

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

mkdir -p storage/app/flyers-incoming
find storage/app/flyers-incoming -mindepth 1 -delete 2>/dev/null || true

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

    if [ -d vendor ]; then
        chown -R www-data:www-data vendor
        chmod -R u+rwX vendor
    fi

    if ! sudo -u www-data composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader; then
        rm -rf vendor
        sudo -u www-data composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
    fi
    sudo -u www-data php artisan optimize:clear
    sudo -u www-data php artisan migrate --force
    sudo -u www-data php artisan config:cache
    sudo -u www-data php artisan route:cache
    sudo -u www-data php artisan event:cache
    sudo -u www-data php artisan view:cache
    sudo -u www-data php artisan cache:warm --type=all
    sudo -u www-data php artisan queue:restart

    supervisorctl restart nuolaidos-flyers || true
EOF

# Restart frontend
ssh $SSH_OPTS $FRONTEND_SERVER << 'EOF'
    cd /var/www/nuolaidos-front/
    mkdir -p /var/www/nuolaidos-front/public/assets
    ln -sfn /var/www/images /var/www/nuolaidos-front/public/assets/product
    pm2 restart all
    ./scripts/production-deploy.sh revalidate
EOF

echo "Deployment completed successfully!"
