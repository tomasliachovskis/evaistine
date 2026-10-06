#!/bin/bash
echo "This script still targets the superakcijos.lt server; set up the vaistines server first." >&2; exit 1

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ "${DEPLOY_PHOTOS_ON_SERVER:-}" = "1" ]; then
    bash "$SCRIPT_DIR/scripts/sync-product-photos-to-frontend.sh"
    cd "${API_DIR:-/var/www/api}"
    sudo -u www-data php artisan cache:warm --type=all
    exit 0
fi

SERVER="root@195.181.245.125"
API_DIR="/var/www/api"

SSH_KEY=""
if [ -f "$SCRIPT_DIR/deploy_key" ]; then
    chmod 600 "$SCRIPT_DIR/deploy_key"
    SSH_KEY="-i $SCRIPT_DIR/deploy_key"
fi

SSH_OPTS="-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null"
if [ -n "$SSH_KEY" ]; then
    SSH_OPTS="$SSH_KEY $SSH_OPTS"
fi

echo "Running photo sync from API server ($SERVER) ..."
ssh $SSH_OPTS $SERVER "cd $API_DIR && DEPLOY_PHOTOS_ON_SERVER=1 bash deploy-photos.sh"

echo "Photo deploy completed successfully!"
