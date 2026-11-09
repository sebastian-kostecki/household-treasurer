#!/usr/bin/env bash

PRODUCTION_BRANCH="${PRODUCTION_BRANCH:-main}"

production_assert_branch() {
    local current_branch
    current_branch="$(git rev-parse --abbrev-ref HEAD)"

    if [[ "${current_branch}" != "${PRODUCTION_BRANCH}" ]]; then
        echo "ERROR: Production deploy must run on branch '${PRODUCTION_BRANCH}', currently on '${current_branch}'." >&2
        echo "Run: git checkout ${PRODUCTION_BRANCH}" >&2
        exit 1
    fi
}

production_pull_latest() {
    echo "==> Fetching and pulling ${PRODUCTION_BRANCH}..."
    git fetch origin "${PRODUCTION_BRANCH}"
    production_assert_branch
    git pull --ff-only origin "${PRODUCTION_BRANCH}"
}

# The functions below run `docker compose` through $COMPOSE, which scripts/deploy.sh sets
# before it calls them.

# Ids of all users, one per line. Read straight from PostgreSQL because the code in a
# running (old) container may predate `user:backup --all`. Empty when the schema has no
# `users` table yet. A psql error fails the call (`sh -e`, ON_ERROR_STOP), so it never
# reads as "no users".
production_user_ids() {
    $COMPOSE exec -T pgsql sh -ec '
        users_table="$(psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At -c "SELECT to_regclass('"'"'public.users'"'"')")"
        if [ -n "$users_table" ]; then
            psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At -c "SELECT id FROM users ORDER BY id"
        fi
    '
}

# Before `up -d`: the OLD container still holds the old code and the old schema.
# `artisan down` goes first and writes the maintenance file into the bind-mounted
# `storage`, which survives `up -d`, so nothing is written between the snapshots and the
# migration and the new code never serves a request on the old schema. Then a snapshot
# of every user (v5, the fallback after a rollback); artisan commands run in maintenance
# mode. Each assignment from a command substitution stands on its own line, so a failing
# `docker compose` or psql stops the deployment (set -e) instead of reading as empty.
# Only an absent app container counts as a first deployment; a stopped one stops the
# deployment unless DEPLOY_WITHOUT_USER_SNAPSHOTS=1 (README, "Wdrożenie").
# Sets MAINTENANCE_STARTED=1 when it put the application in maintenance mode.
production_prepare_running_instance() {
    local running_app any_app user_ids user_id snapshot_count=0

    running_app="$(${COMPOSE:?} ps --status running --quiet app)"

    if [[ -z "${running_app}" ]]; then
        any_app="$(${COMPOSE} ps --all --quiet app)"

        if [[ -n "${any_app}" && "${DEPLOY_WITHOUT_USER_SNAPSHOTS:-0}" != "1" ]]; then
            echo "ERROR: The app container exists but is not running, so user:backup cannot snapshot the users with the old code." >&2
            echo "Start it (${COMPOSE} start app) and run scripts/deploy.sh again, or set DEPLOY_WITHOUT_USER_SNAPSHOTS=1 to deploy with the instance backup only." >&2
            exit 1
        fi

        echo "==> No running app container (first deployment or DEPLOY_WITHOUT_USER_SNAPSHOTS=1): skipping artisan down and user:backup on the old container."
        return 0
    fi

    # Read before `down`: a psql error stops the deployment with the old code still serving.
    user_ids="$(production_user_ids)"

    echo "==> Entering maintenance mode in the old container..."
    $COMPOSE exec -T -u sail app php artisan down
    MAINTENANCE_STARTED=1

    echo "==> Snapshotting every user with the running (old) code..."
    for user_id in ${user_ids}; do
        $COMPOSE exec -T -u sail app php artisan user:backup "${user_id}"
        snapshot_count=$((snapshot_count + 1))
    done
    echo "==> Snapshotted ${snapshot_count} user(s)."
}

# After `up -d` on a first deployment there was no old container to put in maintenance
# mode, so do it in the new one right away. Runs as root because `chown` comes later.
production_ensure_maintenance_mode() {
    if [[ "${MAINTENANCE_STARTED:-0}" == "1" ]]; then
        return 0
    fi

    echo "==> Entering maintenance mode in the new container..."
    $COMPOSE exec -T -u root app php artisan down
    MAINTENANCE_STARTED=1
}

# `production_control_numbers --output=<path>` / `--compare=<path>`: runs in the NEW
# container, which reads both the old and the new shape of the schema (ControlNumbers).
production_control_numbers() {
    $COMPOSE exec -T -u sail app php artisan app:control-numbers "$@"
}

production_leave_maintenance_mode() {
    echo "==> Leaving maintenance mode..."
    $COMPOSE exec -T -u sail app php artisan up
    MAINTENANCE_STARTED=0
}

# EXIT trap: a failed deployment keeps the application in maintenance mode on purpose
# (the control numbers did not match, or a migration stopped halfway).
production_report_failure() {
    local status=$?

    if [[ ${status} -ne 0 && "${MAINTENANCE_STARTED:-0}" == "1" ]]; then
        echo "ERROR: Deployment failed. The application STAYS in maintenance mode." >&2
        echo "Do not run scripts/deploy.sh again before reading README.md → \"Wdrożenie i przywracanie\" (rollback of S-18)." >&2
    fi
}
