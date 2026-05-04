#!/bin/bash

# Agrolytix API Deployment Script
# Run this on your Ubuntu VPS inside /var/www/agrolytix

set -e

echo "Starting deployment..."

# Go to app directory
cd /var/www/agrolytix

# Put the application into maintenance mode
php artisan down || true

# Pull latest changes from the git repository
# Ensure you are on the correct branch
git pull origin main

# Install composer dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Run database migrations
php artisan migrate --force

# Optimize Laravel for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers so they pick up new code
php artisan queue:restart

# Bring the application out of maintenance mode
php artisan up

echo "Deployment finished successfully!"
