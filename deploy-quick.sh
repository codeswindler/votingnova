#!/bin/bash

# ============================================
# Quick Deployment - Minimal Steps
# ============================================
# Use this if you've already run deploy.sh once
# or if you just need to update the code

set -e

PROJECT_DIR="/var/www/novatech"
REPO_URL="https://github.com/codeswindler/votingnova.git"

echo "Quick deployment to $PROJECT_DIR..."

# Create directory if it doesn't exist
mkdir -p $PROJECT_DIR

# Navigate to directory
cd $PROJECT_DIR

# Clone or pull
if [ -d ".git" ]; then
    echo "Pulling latest changes..."
    git pull
else
    echo "Cloning repository..."
    git clone $REPO_URL .
fi

# Set permissions
chown -R www-data:www-data $PROJECT_DIR
find $PROJECT_DIR -type d -exec chmod 755 {} \;
find $PROJECT_DIR -type f -exec chmod 644 {} \;
[ -f ".env" ] && chmod 640 .env

# Reload Apache
systemctl reload apache2

echo "Deployment complete!"
