#!/bin/bash

set -e

API_DIR="${API_DIR:-/var/www/api}"
FRONTEND_SERVER="${FRONTEND_SERVER:-deploy@84.247.186.143}"
REMOTE_IMAGES_DIR="${REMOTE_IMAGES_DIR:-/var/www/images}"
SOURCE_DIR="$API_DIR/storage/app/public/products"

SSH_OPTS="-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null"
if [ -f "$API_DIR/deploy_key" ]; then
    chmod 600 "$API_DIR/deploy_key"
    SSH_OPTS="-i $API_DIR/deploy_key $SSH_OPTS"
elif [ -f "$HOME/.ssh/id_ed25519" ]; then
    SSH_OPTS="-i $HOME/.ssh/id_ed25519 $SSH_OPTS"
elif [ -f "$HOME/.ssh/id_rsa" ]; then
    SSH_OPTS="-i $HOME/.ssh/id_rsa $SSH_OPTS"
fi

if [ ! -d "$SOURCE_DIR" ]; then
    echo "Error: $SOURCE_DIR does not exist."
    exit 1
fi

echo "Syncing $SOURCE_DIR -> $FRONTEND_SERVER:$REMOTE_IMAGES_DIR"
ssh $SSH_OPTS $FRONTEND_SERVER "mkdir -p $REMOTE_IMAGES_DIR"
rsync -avz --omit-dir-times --no-perms --no-owner --no-group -e "ssh $SSH_OPTS" \
    "$SOURCE_DIR/" \
    "$FRONTEND_SERVER:$REMOTE_IMAGES_DIR/"

ssh $SSH_OPTS $FRONTEND_SERVER << 'EOF'
    cd /var/www/nuolaidos-front/
    mkdir -p /var/www/nuolaidos-front/public/assets
    ln -sfn /var/www/images /var/www/nuolaidos-front/public/assets/product
    pm2 restart all
    ./scripts/production-deploy.sh revalidate
EOF

echo "Product photos synced from API server to frontend."
