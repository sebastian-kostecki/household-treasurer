#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

# shellcheck source=lib/production-deploy.sh
source "$(dirname "$0")/lib/production-deploy.sh"

COMPOSE="docker compose --env-file .env.prod -f docker-compose.prod.yml"

if [[ ! -f .env.prod ]]; then
    echo "ERROR: .env.prod is missing. Run: cp .env.prod.example .env.prod" >&2
    echo "Then set APP_KEY, DB_PASSWORD, and mail secrets." >&2
    exit 1
fi

production_pull_latest

echo "==> Building app image..."
$COMPOSE build app

BACKUP_HOST_DIR="${BACKUP_HOST_DIR:-/storage/household-treasurer-backups}"

echo "==> Ensuring backup directory exists..."
sudo mkdir -p "$BACKUP_HOST_DIR"
sudo chown -R "$(id -u)":"$(id -g)" "$BACKUP_HOST_DIR"

echo "==> Starting stack..."
$COMPOSE up -d

echo "==> Fixing storage permissions..."
$COMPOSE exec -T -u root app chown -R sail:sail storage bootstrap/cache

echo "==> Running migrations..."
$COMPOSE exec -T app php artisan migrate --force

echo "==> Discovering packages and caching..."
$COMPOSE exec -T app php artisan package:discover --ansi
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> Done. Check https://treasurer.kostecki.dev/up"
