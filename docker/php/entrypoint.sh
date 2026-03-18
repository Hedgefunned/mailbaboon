#!/bin/sh
set -e

# Install dependencies if vendor/ is missing
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generate app key if .env is missing
if [ ! -f ".env" ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
    php artisan key:generate
fi

# Fix storage permissions
chmod -R 777 /var/www/storage /var/www/bootstrap/cache

exec "$@"
