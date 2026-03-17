# Laravel app template

Laravel 12
PHP 8.4
Vue.js
Axios

# Instructions

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) with Compose plugin

## Configuration

Edit `.env` in the project root to change the port, database credentials or user:group ids before running `./setup.sh` or `docker compose up`.
The app container reads `DB_*` variables from this file, overriding Laravel's own `.env`.
The container entrypoint (`docker/php/entrypoint.sh`) handles Composer install and app key generation on first boot.

## Quick Start (automated)

```sh
./setup.sh
```

This will:

1. Copy `.env.example` → `.env` (if not present)
2. Build the Docker image and start all containers (PHP 8.4, Nginx, MariaDB 11)
3. Wait for the database to become healthy
4. Wait for Composer dependencies (installed automatically by the container entrypoint)
5. Install JS dependencies and build frontend assets (`npm install && npm run build`)
6. Run database migrations

Once complete, visit [http://localhost:8080](http://localhost:8080).

## Quick Start (manual)

If you prefer to run each step yourself:

1. **Create the environment file**

    ```sh
    cp .env.example .env
    ```

    Edit `.env` to change the port (`APP_PORT`) or database credentials if needed.

2. **Build and start containers**

    ```sh
    docker compose up -d --build
    ```

    The container entrypoint automatically runs `composer install` and generates the Laravel app key on first boot.

3. **Wait for the database**

    MariaDB has a health check configured. Wait until it reports healthy:

    ```sh
    docker compose exec db healthcheck.sh --connect --innodb_initialized
    ```

4. **Install JS dependencies and build the frontend**

    ```sh
    docker compose exec app npm install
    docker compose exec app npm run build
    ```

5. **Run migrations**

    ```sh
    docker compose exec app php artisan migrate
    ```

6. **Seed the database (optional)**

    ```sh
    docker compose exec app php artisan db:seed
    ```

7. **Open the app**

    Visit [http://localhost:8080](http://localhost:8080) (or the port you set in `APP_PORT`).

## Common Commands

```sh
# Start / stop
docker compose up -d
docker compose down

# Shell into the app container
docker compose exec app sh

# Rebuild frontend after changes
docker compose exec app npm run build

# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:seed

# Run tests
docker compose exec app php artisan test
```
