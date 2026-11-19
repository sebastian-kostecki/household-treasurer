#!/usr/bin/env bash
# Serwer aplikacji dla testów E2E na runnerze GitHuba (Playwright webServer,
# playwright.config.ts, gdy ustawione GITHUB_ACTIONS). Build, migrację i seed
# robi job wcześniej, więc skrypt tylko sprawdza warunki i uruchamia serwer.
# Konfiguracja bazy pochodzi ze zmiennych joba.
#
# Użycie: scripts/e2e-server-ci.sh <port>
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

PORT="${1:?Usage: $0 <port>}"

if [[ -f public/hot ]]; then
    echo "ERROR: public/hot exists, so Laravel would serve Vite dev assets instead of the build." >&2
    exit 1
fi

if [[ ! -f public/build/manifest.json ]]; then
    echo "ERROR: public/build/manifest.json is missing. Run 'npm run build' before the E2E tests." >&2
    exit 1
fi

# Plain `php -S` with Laravel's router instead of `artisan serve`: serve starts a
# child process without the variables defined in .env, which would drop the
# DB_DATABASE override and point the app at another database.
cd public
export PHP_CLI_SERVER_WORKERS=4
exec php -S "127.0.0.1:${PORT}" \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
