#!/bin/bash

# Define server and project details
SERVER="root@195.181.245.125"
REMOTE_DIR="/var/www/api"

# Sync project files to the server
rsync -avz --exclude='.env' --exclude='storage' --exclude='.idea' --exclude='.git' . $SERVER:$REMOTE_DIR

# Run Laravel commands on the server
ssh $SERVER << 'EOF'
    cd /var/www/api
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    php artisan optimize
    php artisan config:clear
    php artisan migrate
EOF

echo "Deployment completed successfully!"
