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

# Same precedence as Compose: shell, then .env.prod, then the default.
if [[ -z "${BACKUP_HOST_DIR:-}" ]]; then
    BACKUP_HOST_DIR="$(sed -n 's/^BACKUP_HOST_DIR=//p' .env.prod | tail -n 1 | tr -d '\r' | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/")"
fi
BACKUP_HOST_DIR="${BACKUP_HOST_DIR:-/storage/household-treasurer-backups}"
export BACKUP_HOST_DIR

echo "==> Ensuring backup directory exists..."
sudo mkdir -p "$BACKUP_HOST_DIR"
sudo chown -R "$(id -u)":"$(id -g)" "$BACKUP_HOST_DIR"

MAINTENANCE_STARTED=0
trap production_report_failure EXIT

# Old container, old code: user:backup of every user, then maintenance mode.
production_prepare_running_instance

echo "==> Starting stack..."
$COMPOSE up -d

production_ensure_maintenance_mode

echo "==> Fixing storage permissions..."
$COMPOSE exec -T -u root app chown -R sail:sail storage bootstrap/cache

# New code on the still old schema: the numbers every later step is compared against.
CONTROL_NUMBERS_DIR="storage/app/private/control-numbers"
CONTROL_NUMBERS_STAMP="$(date -u +%Y%m%d-%H%M%S)"
CONTROL_NUMBERS_BEFORE="${CONTROL_NUMBERS_DIR}/${CONTROL_NUMBERS_STAMP}-before.json"
CONTROL_NUMBERS_AFTER="${CONTROL_NUMBERS_DIR}/${CONTROL_NUMBERS_STAMP}-after.json"

echo "==> Recording control numbers before migrations (${CONTROL_NUMBERS_BEFORE})..."
production_control_numbers --output="${CONTROL_NUMBERS_BEFORE}"

echo "==> Backing up the instance before migrations..."
$COMPOSE exec -T -u sail app php artisan backup:run --disable-notifications

echo "==> Running migrations..."
$COMPOSE exec -T app php artisan migrate --force

echo "==> Comparing control numbers after migrations (a difference stops the deployment)..."
production_control_numbers --output="${CONTROL_NUMBERS_AFTER}" --compare="${CONTROL_NUMBERS_BEFORE}"

production_leave_maintenance_mode

echo "==> Discovering packages and caching..."
$COMPOSE exec -T app php artisan package:discover --ansi
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> Done. Check https://treasurer.kostecki.dev/up"
