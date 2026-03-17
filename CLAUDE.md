# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

MailBaboon is a Laravel 12 + Vue 3 SPA. The PHP application lives in `src/`, with Docker providing the full runtime (PHP-FPM, Nginx, MariaDB).

## Setup

```bash
./setup.sh           # automated first-time setup
./setup.sh --seed    # setup + seed the database
```

Or manually:
```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app npm install && npm run build
docker compose exec app php artisan migrate
```

App runs at `http://localhost:8080` (override with `APP_PORT` in `.env`).

## Common Commands

All commands run inside the container:

```bash
# Development
docker compose exec app npm run dev          # Vite dev server with HMR (port 5173)
docker compose exec app npm run build        # Production asset build

# Testing
docker compose exec app php artisan test                        # all tests
docker compose exec app php artisan test --filter=TestName      # single test

# Database
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed

# Linting
docker compose exec app ./vendor/bin/pint    # PHP code style (Laravel Pint)
```

For local development without Docker, `composer dev` in `src/` runs PHP server, queue worker, log viewer, and Vite concurrently.

## Architecture

**Request flow:**
1. Nginx routes all requests to `public/index.php` (Laravel entry point)
2. `GET /` returns `resources/views/app.blade.php`, which mounts the Vue SPA
3. `routes/api.php` exposes RESTful endpoints under `/api/`
4. Vue (`resources/js/App.vue`) handles all UI navigation client-side

**Frontend structure:**
- `resources/js/app.js` — Vue app bootstrap
- `resources/js/App.vue` — root layout (sidebar with Active/Archive navigation)
- `resources/js/components/` — reusable Vue components

**Key configuration:**
- `vite.config.js` — Vite build with Laravel plugin, TailwindCSS v4, Vue 3
- `phpunit.xml` — tests use SQLite in-memory; coverage includes `app/`
- `docker/php/entrypoint.sh` — auto-installs deps and generates app key on container start

## Tech Stack

- PHP 8.4, Laravel 12
- Vue 3 (Composition API), Vite, TailwindCSS v4
- MariaDB 11 (dev), SQLite in-memory (tests)
- Docker: `app` (PHP-FPM + Node), `nginx`, `db` (MariaDB)
