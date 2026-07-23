#!/bin/bash

set -e

API_DIR="${API_DIR:-/var/www/api}"
FRONTEND_SERVER="${FRONTEND_SERVER:-deploy@84.247.186.143}"
REMOTE_IMAGES_DIR="${REMOTE_IMAGES_DIR:-/var/www/images}"
SOURCE_DIR="$API_DIR/storage/app/public/products"

SSH_IDENTITY=()
if [ -f "$API_DIR/deploy_key" ]; then
    chmod 600 "$API_DIR/deploy_key" 2>/dev/null || true
    SSH_IDENTITY=(-i "$API_DIR/deploy_key")
elif [ -f "$HOME/.ssh/id_ed25519" ]; then
    SSH_IDENTITY=(-i "$HOME/.ssh/id_ed25519")
elif [ -f "$HOME/.ssh/id_rsa" ]; then
    SSH_IDENTITY=(-i "$HOME/.ssh/id_rsa")
fi

SSH_OPTS=(
    "${SSH_IDENTITY[@]}"
    -o StrictHostKeyChecking=no
    -o UserKnownHostsFile=/dev/null
    -o ConnectTimeout=30
    -o ServerAliveInterval=15
    -o ServerAliveCountMax=10
    -o TCPKeepAlive=yes
    -o BatchMode=yes
)

run_ssh() {
    ssh "${SSH_OPTS[@]}" "$@"
}

if [ ! -d "$SOURCE_DIR" ]; then
    echo "Error: $SOURCE_DIR does not exist."
    exit 1
fi

if [ ${#SSH_IDENTITY[@]} -eq 0 ]; then
    echo "Warning: no SSH key found (expected $API_DIR/deploy_key)."
fi

echo "Testing SSH to $FRONTEND_SERVER ..."
if ! run_ssh "$FRONTEND_SERVER" "echo ok"; then
    echo "Error: cannot SSH to frontend server."
    exit 1
fi

echo "Syncing $SOURCE_DIR -> $FRONTEND_SERVER:$REMOTE_IMAGES_DIR"
run_ssh "$FRONTEND_SERVER" "mkdir -p $REMOTE_IMAGES_DIR"

SSH_E_OPTS="-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=30 -o ServerAliveInterval=15 -o ServerAliveCountMax=10 -o TCPKeepAlive=yes -o BatchMode=yes"
if [ -f "$API_DIR/deploy_key" ]; then
    export RSYNC_RSH="ssh -i ${API_DIR}/deploy_key ${SSH_E_OPTS}"
elif [ -f "$HOME/.ssh/id_ed25519" ]; then
    export RSYNC_RSH="ssh -i ${HOME}/.ssh/id_ed25519 ${SSH_E_OPTS}"
elif [ -f "$HOME/.ssh/id_rsa" ]; then
    export RSYNC_RSH="ssh -i ${HOME}/.ssh/id_rsa ${SSH_E_OPTS}"
else
    export RSYNC_RSH="ssh ${SSH_E_OPTS}"
fi

RSYNC_OPTS=(-avz --omit-dir-times --no-perms --no-owner --no-group --timeout=600)

if ! rsync "${RSYNC_OPTS[@]}" "$SOURCE_DIR/" "$FRONTEND_SERVER:$REMOTE_IMAGES_DIR/"; then
    echo "Rsync failed, retrying once ..."
    sleep 3
    rsync "${RSYNC_OPTS[@]}" "$SOURCE_DIR/" "$FRONTEND_SERVER:$REMOTE_IMAGES_DIR/"
fi

unset RSYNC_RSH

run_ssh "$FRONTEND_SERVER" << 'EOF'
    cd /var/www/nuolaidos-front/
    mkdir -p /var/www/nuolaidos-front/public/assets
    ln -sfn /var/www/images /var/www/nuolaidos-front/public/assets/product
    ./scripts/production-deploy.sh restart
    ./scripts/production-deploy.sh revalidate
EOF

echo "Product photos synced from API server to frontend."
