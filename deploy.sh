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
rsync -avz -e "ssh $SSH_OPTS" --exclude='.env' --exclude='storage' --exclude='node_modules'  --exclude='.idea' --exclude='.git' . $SERVER:$REMOTE_DIR

# Upload flyer images
FRONTEND_SERVER="deploy@84.247.186.143"
REMOTE_IMAGES_DIR="/var/www/images"
ssh $SSH_OPTS $FRONTEND_SERVER "mkdir -p $REMOTE_IMAGES_DIR"
rsync -avz --omit-dir-times -e "ssh $SSH_OPTS" storage/app/public/products/ $FRONTEND_SERVER:$REMOTE_IMAGES_DIR/

# Run Laravel commands on the server
ssh $SSH_OPTS $SERVER << 'EOF'
    cd /var/www/api
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    php artisan optimize
    php artisan config:clear
    php artisan migrate --force
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
