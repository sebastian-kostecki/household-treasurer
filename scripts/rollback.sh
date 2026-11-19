#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

# shellcheck source=lib/production-deploy.sh
source "$(dirname "$0")/lib/production-deploy.sh"
# shellcheck source=lib/production-images.sh
source "$(dirname "$0")/lib/production-images.sh"

COMPOSE="docker compose --env-file .env.prod -f docker-compose.prod.yml"

if [[ ! -f .env.prod ]]; then
    echo "ERROR: .env.prod is missing. Run: cp .env.prod.example .env.prod" >&2
    echo "Then set APP_KEY, DB_PASSWORD, and mail secrets." >&2
    exit 1
fi

REPO="${PRODUCTION_IMAGE_REPOSITORY}"

# The way back to the previous image, set before the first change; read by the EXIT trap.
WAY_BACK=""
MAINTENANCE_STARTED=0
# Set once :prod points at the target: only then is WAY_BACK the way back.
PROD_SWITCHED=0
WORK_DIR=""

rollback_report_failure() {
    local status=$?

    if [[ -n "${WORK_DIR}" ]]; then
        rm -rf "${WORK_DIR}"
    fi

    if [[ ${status} -ne 0 && "${MAINTENANCE_STARTED:-0}" == "1" ]]; then
        echo "ERROR: Rollback failed. The application STAYS in maintenance mode." >&2
        if [[ "${PROD_SWITCHED}" == "1" && -n "${WAY_BACK}" && "${WAY_BACK}" != sha256:* ]]; then
            echo "Way back to the previous image: scripts/rollback.sh ${WAY_BACK}" >&2
        elif [[ "${PROD_SWITCHED}" == "1" && -n "${WAY_BACK}" ]]; then
            echo "Way back to the previous image (it has no tag):" >&2
            echo "  docker tag ${WAY_BACK} ${REPO}:prod" >&2
            echo "  ${COMPOSE} up -d --no-build app" >&2
        fi
        echo "Then leave maintenance mode: ${COMPOSE} exec -T -u sail app php artisan up" >&2
    fi
}

trap rollback_report_failure EXIT

LISTING="$(production_image_listing)"

if [[ $# -eq 0 ]]; then
    PROD_ID="$(production_prod_image_id)"

    printf '%-24s %-22s %-10s\n' "TAG" "DEPLOYED AT" "REVISION"
    while IFS=$'\t' read -r tag deployed_at image_id; do
        if [[ -z "${tag}" || "${tag}" == "prod" || "${tag}" == "<none>" ]]; then
            continue
        fi
        revision="$(docker image inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' "${REPO}:${tag}")"
        marker=""
        if [[ -n "${PROD_ID}" && "${image_id}" == "${PROD_ID}" ]]; then
            marker="← prod"
        fi
        printf '%-24s %-22s %-10s %s\n' "${tag}" "${deployed_at:0:19}" "${revision}" "${marker}"
    done <<< "${LISTING}"

    echo
    echo "Usage: scripts/rollback.sh <tag>"
    echo "Switches production to the image ${REPO}:<tag> without building. It never migrates:"
    echo "the migrations of the image must equal the migrations the database has run."
    exit 0
fi

TARGET_TAG="$1"

if ! docker image inspect "${REPO}:${TARGET_TAG}" > /dev/null 2>&1; then
    echo "ERROR: Image ${REPO}:${TARGET_TAG} does not exist. Run scripts/rollback.sh without arguments to list the tags." >&2
    exit 1
fi

TARGET_IMAGE_ID="$(docker image inspect --format '{{.Id}}' "${REPO}:${TARGET_TAG}")"

# The image of the app container, also a stopped one, not :prod: a previous attempt may
# have moved :prod while `up -d` failed. Without a container, :prod is the reference.
APP_CONTAINER="$($COMPOSE ps --all --quiet app)"
if [[ -n "${APP_CONTAINER}" ]]; then
    RUNNING_IMAGE_ID="$(docker inspect --format '{{.Image}}' "${APP_CONTAINER}")"
else
    RUNNING_IMAGE_ID="$(production_prod_image_id)"
fi

if [[ -n "${RUNNING_IMAGE_ID}" && "${RUNNING_IMAGE_ID}" == "${TARGET_IMAGE_ID}" ]]; then
    echo "ERROR: ${REPO}:${TARGET_TAG} is the image production already runs. Nothing to roll back." >&2
    exit 1
fi

# The way back: a tag of the running image (SHA or legacy), its image ID when it has none.
WAY_BACK="${RUNNING_IMAGE_ID}"
if [[ -n "${RUNNING_IMAGE_ID}" ]]; then
    while IFS=$'\t' read -r tag deployed_at image_id; do
        if [[ "${image_id}" == "${RUNNING_IMAGE_ID}" && -n "${tag}" && "${tag}" != "prod" && "${tag}" != "<none>" ]]; then
            WAY_BACK="${tag}"
            break
        fi
    done <<< "${LISTING}"
fi

# Migration lock: nothing has changed yet when it refuses. WORK_DIR holds the two lists.
WORK_DIR="$(mktemp -d)"

echo "==> Comparing migrations of the database and of ${REPO}:${TARGET_TAG}..."
production_database_migrations > "${WORK_DIR}/database.txt"
production_image_migrations "${TARGET_TAG}" > "${WORK_DIR}/image.txt"

UNKNOWN_MIGRATIONS="$(production_unknown_migrations "${WORK_DIR}/database.txt" "${WORK_DIR}/image.txt")"
PENDING_MIGRATIONS="$(production_pending_migrations "${WORK_DIR}/database.txt" "${WORK_DIR}/image.txt")"

if [[ -n "${UNKNOWN_MIGRATIONS}" ]]; then
    echo "ERROR: The database has migrations that ${REPO}:${TARGET_TAG} does not know:" >&2
    printf '  %s\n' ${UNKNOWN_MIGRATIONS} >&2
    echo "Roll them back first, with the code that has them (still running now):" >&2
    echo "  scripts/artisan.sh migrate:rollback --force" >&2
    echo "Check in its output that exactly the migrations above were rolled back (order: README.md, \"Wdrożenie i przywracanie\"), then run this script again." >&2
    exit 1
fi

if [[ -n "${PENDING_MIGRATIONS}" ]]; then
    echo "ERROR: ${REPO}:${TARGET_TAG} has migrations the database has not run:" >&2
    printf '  %s\n' ${PENDING_MIGRATIONS} >&2
    echo "rollback.sh never migrates. Going back to newer code goes through scripts/deploy.sh (maintenance mode, backups, control numbers)." >&2
    exit 1
fi

RUNNING_APP="$($COMPOSE ps --status running --quiet app)"
if [[ -n "${RUNNING_APP}" ]]; then
    echo "==> Entering maintenance mode..."
    $COMPOSE exec -T -u sail app php artisan down
    MAINTENANCE_STARTED=1
fi

echo "==> Switching ${REPO}:prod to ${TARGET_TAG}..."
docker tag "${REPO}:${TARGET_TAG}" "${REPO}:prod"
PROD_SWITCHED=1

echo "==> Starting app on ${TARGET_TAG} (no build)..."
$COMPOSE up -d --no-build app

production_ensure_maintenance_mode

echo "==> Fixing storage permissions..."
$COMPOSE exec -T -u root app chown -R sail:sail storage bootstrap/cache

echo "==> Discovering packages and caching..."
$COMPOSE exec -T app php artisan package:discover --ansi
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

production_leave_maintenance_mode

echo "==> Done. Production runs ${REPO}:${TARGET_TAG}."
echo "The code on the server is still on the bad commit: the next scripts/deploy.sh would build it back."
echo "Next: git revert it locally, push main, run 'git pull --ff-only' on the server, then scripts/deploy.sh."
echo "Check https://budget.kostecki.dev/up"
