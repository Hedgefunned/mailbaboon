#!/usr/bin/env bash
set -e

SEED=false
for arg in "$@"; do
    [ "$arg" = "--seed" ] && SEED=true
done

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example"
fi

docker compose up -d --build

echo "Waiting for database..."
until docker compose exec db healthcheck.sh --connect --innodb_initialized 2>/dev/null; do
    sleep 1
done

echo "Waiting for Composer dependencies..."
until docker compose exec app test -f vendor/autoload.php 2>/dev/null; do
    sleep 1
done

echo "Installing JS dependencies and building assets..."
docker compose exec app npm install
docker compose exec app npm run build

docker compose exec app php artisan migrate --force

if [ "$SEED" = true ]; then
    echo "Seeding database..."
    docker compose exec app php artisan db:seed
fi

APP_PORT=$(grep APP_PORT .env | cut -d= -f2)
echo ""
echo "Setup complete! Visit http://localhost:${APP_PORT:-8080}"
