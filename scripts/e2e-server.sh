#!/usr/bin/env bash
# Serwer aplikacji dla testów E2E (Playwright webServer, playwright.config.ts).
# Build produkcyjny assetów, migracja osobnej bazy E2E i wbudowany serwer PHP
# w obrazie Sail (PHP 8.5) na sieci stosu Sail. Baza deweloperska zostaje nietknięta.
#
# Użycie: scripts/e2e-server.sh <port>
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

PORT="${1:?Usage: $0 <port>}"
DB="${E2E_DB_DATABASE:-household_treasurer_e2e}"
# Named after the port, so E2E runs of parallel worktrees (each on its own
# E2E_PORT and E2E_DB_DATABASE) never remove each other's server.
CONTAINER="household-treasurer-e2e-${PORT}"
NETWORK="household-treasurer_sail"

if [[ -f public/hot ]]; then
    echo "ERROR: public/hot exists, so Laravel would serve Vite dev assets instead of the build." >&2
    echo "Stop 'composer run dev' (it removes public/hot on exit) and run the E2E tests again." >&2
    exit 1
fi

if ! docker network inspect "${NETWORK}" > /dev/null 2>&1; then
    echo "ERROR: Docker network ${NETWORK} is missing. Start the stack: ./vendor/bin/sail up -d" >&2
    exit 1
fi

# A container left behind by a killed run would keep the port and serve a stale build.
docker rm -f "${CONTAINER}" > /dev/null 2>&1 || true

npm run build

SAIL_RUN=(docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp
    -e DB_DATABASE="${DB}" -e APP_URL="http://localhost:${PORT}"
    --network "${NETWORK}" -v "${ROOT}":/var/www/html -w /var/www/html)

"${SAIL_RUN[@]}" --entrypoint php sail-8.5/app artisan migrate --force

# Plain `php -S` with Laravel's router instead of `artisan serve`: serve starts a
# child process without the variables defined in .env, which would drop the
# DB_DATABASE override and point the app at the development database.
# exec: Playwright's SIGTERM reaches `docker run`, which passes it into the container.
# --init: without it `php -S` is PID 1 and ignores SIGTERM, so the container
# outlives the run, keeps the port and the next run reuses its stale build.
exec "${SAIL_RUN[@]}" --init --name "${CONTAINER}" -p "${PORT}:8000" \
    -e PHP_CLI_SERVER_WORKERS=4 -w /var/www/html/public \
    --entrypoint php sail-8.5/app -S 0.0.0.0:8000 \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
