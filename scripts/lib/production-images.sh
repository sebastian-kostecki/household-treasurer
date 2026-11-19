#!/usr/bin/env bash

PRODUCTION_IMAGE_REPOSITORY="household-treasurer-app"

# Pure. Usage: production_image_tags_to_remove <keep> [<protected-image-id>...]
# Stdin: lines "<tag>\t<deployed-at ISO-8601 UTC>\t<image-id>". `prod` and `<none>` are
# ignored. The rest is sorted newest first by the first 19 characters of the time (to the
# second: the `.Created` of a legacy image has a fractional part), the first <keep> stay,
# and of the others the tags whose image id is not protected are printed, one per line.
# A row without a time or an image id fails the call before anything is printed: it would
# sort as the oldest and match no protected id.
production_image_tags_to_remove() {
    local keep="$1"
    shift
    local protected_ids="$*" rows

    rows="$(cat)"

    if ! printf '%s\n' "${rows}" | awk -F '\t' '
        $1 != "prod" && $1 != "<none>" && $1 != "" && ($2 == "" || $3 == "") {
            print "ERROR: Image tag " $1 " has no deployment time or image id, so no tag is removed." > "/dev/stderr"
            invalid = 1
        }
        END { exit invalid }
    '; then
        return 1
    fi

    printf '%s\n' "${rows}" | awk -F '\t' '
        $1 != "prod" && $1 != "<none>" && $1 != "" {
            print substr($2, 1, 19) "\t" $1 "\t" $3
        }
    ' | LC_ALL=C sort -t "$(printf '\t')" -k1,1r -k2,2 | awk -F '\t' -v keep="${keep}" -v protected_ids="${protected_ids}" '
        BEGIN {
            count = split(protected_ids, ids, " ")
            for (i = 1; i <= count; i++) {
                protected[ids[i]] = 1
            }
        }
        NR > keep && !($3 in protected) {
            print $2
        }
    '
}

# Callers read the functions below through `$(...)`, where bash drops `set -e`, so every
# docker call that can fail ends in `|| return 1`: a docker error stops the caller instead
# of reading as "no image" or as an empty row.

# ID of the image tagged :prod, empty when there is none (first deployment). Only an
# absent tag reads as empty; a docker error fails the call.
production_prod_image_id() {
    local listed

    listed="$(docker image ls -q "${PRODUCTION_IMAGE_REPOSITORY}:prod")" || return 1
    if [[ -z "${listed}" ]]; then
        return 0
    fi

    docker image inspect --format '{{.Id}}' "${PRODUCTION_IMAGE_REPOSITORY}:prod"
}

# Deployment time of <tag>: the deployed-at label, or the creation time for an image
# without it (legacy).
production_image_deployed_at() {
    local tag="$1" deployed_at

    deployed_at="$(docker image inspect --format '{{index .Config.Labels "household-treasurer.deployed-at"}}' "${PRODUCTION_IMAGE_REPOSITORY}:${tag}")" || return 1
    if [[ -z "${deployed_at}" ]]; then
        deployed_at="$(docker image inspect --format '{{.Created}}' "${PRODUCTION_IMAGE_REPOSITORY}:${tag}")" || return 1
    fi

    printf '%s\n' "${deployed_at}"
}

# Rows "<tag>\t<deployed-at>\t<image-id>" for every tag of the repository.
production_image_listing() {
    local tags tag deployed_at image_id

    tags="$(docker image ls --format '{{.Tag}}' "${PRODUCTION_IMAGE_REPOSITORY}")" || return 1

    while IFS= read -r tag; do
        if [[ -z "${tag}" || "${tag}" == "<none>" ]]; then
            continue
        fi
        deployed_at="$(production_image_deployed_at "${tag}")" || return 1
        image_id="$(docker image inspect --format '{{.Id}}' "${PRODUCTION_IMAGE_REPOSITORY}:${tag}")" || return 1
        printf '%s\t%s\t%s\n' "${tag}" "${deployed_at}" "${image_id}"
    done <<< "${tags}"
}

# An image from before the tags (:prod without the revision label) gets `legacy-<date>`
# so it survives the next build, which moves :prod.
production_tag_legacy_prod_image() {
    local prod_id revision created legacy_tag

    prod_id="$(production_prod_image_id)"
    if [[ -z "${prod_id}" ]]; then
        return 0
    fi

    revision="$(docker image inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' "${PRODUCTION_IMAGE_REPOSITORY}:prod")"
    if [[ -n "${revision}" ]]; then
        return 0
    fi

    created="$(docker image inspect --format '{{.Created}}' "${PRODUCTION_IMAGE_REPOSITORY}:prod")"
    legacy_tag="legacy-$(printf '%s' "${created}" | cut -c1-10 | tr -d '-')"

    echo "==> Tagging the pre-tag production image as ${PRODUCTION_IMAGE_REPOSITORY}:${legacy_tag}..."
    docker tag "${PRODUCTION_IMAGE_REPOSITORY}:prod" "${PRODUCTION_IMAGE_REPOSITORY}:${legacy_tag}"
}

# Tags the freshly built :prod with <revision>. When the same commit was deployed before,
# its previous build keeps a tag `<revision>-<YYYYMMDDHHMMSS of its deployment>`: moving
# the SHA tag alone would leave it untagged, and the prune at the end would remove it.
production_tag_built_image() {
    local revision="$1"
    local listed previous_id built_id previous_deployed_at kept_tag

    listed="$(docker image ls -q "${PRODUCTION_IMAGE_REPOSITORY}:${revision}")"
    if [[ -n "${listed}" ]]; then
        previous_id="$(docker image inspect --format '{{.Id}}' "${PRODUCTION_IMAGE_REPOSITORY}:${revision}")"
        built_id="$(docker image inspect --format '{{.Id}}' "${PRODUCTION_IMAGE_REPOSITORY}:prod")"

        if [[ "${previous_id}" != "${built_id}" ]]; then
            previous_deployed_at="$(production_image_deployed_at "${revision}")"
            kept_tag="${revision}-$(printf '%s' "${previous_deployed_at}" | tr -d 'TZ:-' | cut -c1-14)"

            echo "==> Keeping the previous build of ${revision} as ${PRODUCTION_IMAGE_REPOSITORY}:${kept_tag}..."
            docker tag "${PRODUCTION_IMAGE_REPOSITORY}:${revision}" "${PRODUCTION_IMAGE_REPOSITORY}:${kept_tag}"
        fi
    fi

    echo "==> Tagging the built image as ${PRODUCTION_IMAGE_REPOSITORY}:${revision}..."
    docker tag "${PRODUCTION_IMAGE_REPOSITORY}:prod" "${PRODUCTION_IMAGE_REPOSITORY}:${revision}"
}

# Usage: production_remove_old_image_tags <keep> [<extra-protected-image-id>...]
# Always protects the image behind :prod. Dangling images are pruned only for this app
# (the server is shared), the build cache stays.
production_remove_old_image_tags() {
    local keep="$1"
    shift
    local current_prod_id listing tags_to_remove tag

    current_prod_id="$(production_prod_image_id)"
    listing="$(production_image_listing)"
    tags_to_remove="$(printf '%s\n' "${listing}" | production_image_tags_to_remove "${keep}" "${current_prod_id}" "$@")"

    for tag in ${tags_to_remove}; do
        echo "==> Removing old image tag ${PRODUCTION_IMAGE_REPOSITORY}:${tag}..."
        docker rmi "${PRODUCTION_IMAGE_REPOSITORY}:${tag}"
    done

    docker image prune -f --filter label=maintainer=household-treasurer
}

# Pure. Usage: production_unknown_migrations <db-list-file> <image-list-file>
# Migration names present in the database list but absent from the image list, sorted,
# one per line. Image list lines may carry a `.php` suffix; empty lines are skipped.
production_unknown_migrations() {
    local db_list="$1" image_list="$2"

    LC_ALL=C comm -23 \
        <(sed '/^[[:space:]]*$/d' "${db_list}" | LC_ALL=C sort -u) \
        <(sed -e '/^[[:space:]]*$/d' -e 's/\.php$//' "${image_list}" | LC_ALL=C sort -u)
}

# Pure. Usage: production_pending_migrations <db-list-file> <image-list-file>
# The reverse: migrations of the image (without `.php`) that the database has not run.
production_pending_migrations() {
    local db_list="$1" image_list="$2"

    LC_ALL=C comm -13 \
        <(sed '/^[[:space:]]*$/d' "${db_list}" | LC_ALL=C sort -u) \
        <(sed -e '/^[[:space:]]*$/d' -e 's/\.php$//' "${image_list}" | LC_ALL=C sort -u)
}

# Names of the migrations the database has run, one per line. Empty when there is no
# `migrations` table yet. A psql error fails the call (`sh -e`, ON_ERROR_STOP), so it
# never reads as "no migrations". Runs `docker compose` through $COMPOSE.
production_database_migrations() {
    ${COMPOSE:?} exec -T pgsql sh -ec '
        migrations_table="$(psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At -c "SELECT to_regclass('"'"'public.migrations'"'"')")"
        if [ -n "$migrations_table" ]; then
            psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At -c "SELECT migration FROM migrations ORDER BY migration"
        fi
    '
}

# File names of the migrations baked into the image <tag> (with the `.php` suffix).
production_image_migrations() {
    local tag="$1"

    docker run --rm --entrypoint ls "${PRODUCTION_IMAGE_REPOSITORY}:${tag}" /var/www/html/database/migrations
}
