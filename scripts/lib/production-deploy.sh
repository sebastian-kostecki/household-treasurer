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

production_app_is_running() {
    [[ -n "$(${COMPOSE:?} ps --status running --quiet app)" ]]
}

# Ids of all users, one per line. Read straight from PostgreSQL because the code in a
# running (old) container may predate `user:backup --all`. Empty when the schema has no
# `users` table yet.
production_user_ids() {
    $COMPOSE exec -T pgsql sh -c '
        if [ -n "$(psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At -c "SELECT to_regclass('"'"'public.users'"'"')")" ]; then
            psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At -c "SELECT id FROM users ORDER BY id"
        fi
    '
}

# Before `up -d`: the OLD container still holds the old code and the old schema, so a
# snapshot of every user made here is a v5 snapshot (the fallback after a rollback), and
# `artisan down` writes the maintenance file into the bind-mounted `storage`, which
# survives `up -d`. The new code must not serve a request on the old schema.
# Sets MAINTENANCE_STARTED=1 when it put the application in maintenance mode.
production_prepare_running_instance() {
    if ! production_app_is_running; then
        echo "==> No running app container (first deployment): skipping user:backup and artisan down on the old container."
        return 0
    fi

    local user_ids user_id
    user_ids="$(production_user_ids)"

    echo "==> Snapshotting every user with the running (old) code..."
    for user_id in ${user_ids}; do
        $COMPOSE exec -T -u sail app php artisan user:backup "${user_id}"
    done

    echo "==> Entering maintenance mode in the old container..."
    $COMPOSE exec -T -u sail app php artisan down
    MAINTENANCE_STARTED=1
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
