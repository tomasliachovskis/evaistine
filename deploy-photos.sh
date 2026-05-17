#!/bin/bash

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ "${DEPLOY_PHOTOS_ON_SERVER:-}" = "1" ]; then
    chmod +x "$SCRIPT_DIR/scripts/sync-product-photos-to-frontend.sh"
    exec "$SCRIPT_DIR/scripts/sync-product-photos-to-frontend.sh"
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
ssh $SSH_OPTS $SERVER "cd $API_DIR && DEPLOY_PHOTOS_ON_SERVER=1 ./deploy-photos.sh"

echo "Photo deploy completed successfully!"
