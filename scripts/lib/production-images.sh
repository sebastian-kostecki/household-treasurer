#!/usr/bin/env bash

PRODUCTION_IMAGE_REPOSITORY="household-treasurer-app"

# Pure. Usage: production_image_tags_to_remove <keep> [<protected-image-id>...]
# Stdin: lines "<tag>\t<deployed-at ISO-8601 UTC>\t<image-id>". `prod` and `<none>` are
# ignored. The rest is sorted newest first by the first 19 characters of the time (to the
# second: the `.Created` of a legacy image has a fractional part), the first <keep> stay,
# and of the others the tags whose image id is not protected are printed, one per line.
production_image_tags_to_remove() {
    local keep="$1"
    shift
    local protected_ids="$*"

    awk -F '\t' '
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

# ID of the image tagged :prod, empty when there is none (first deployment).
production_prod_image_id() {
    if docker image inspect --format '{{.Id}}' "${PRODUCTION_IMAGE_REPOSITORY}:prod" 2>/dev/null; then
        return 0
    fi
}

# Rows "<tag>\t<deployed-at>\t<image-id>" for every tag of the repository. An image
# without the deployed-at label (legacy) falls back to its creation time.
production_image_listing() {
    local tag deployed_at image_id

    while IFS= read -r tag; do
        deployed_at="$(docker image inspect --format '{{index .Config.Labels "household-treasurer.deployed-at"}}' "${PRODUCTION_IMAGE_REPOSITORY}:${tag}")"
        if [[ -z "${deployed_at}" ]]; then
            deployed_at="$(docker image inspect --format '{{.Created}}' "${PRODUCTION_IMAGE_REPOSITORY}:${tag}")"
        fi
        image_id="$(docker image inspect --format '{{.Id}}' "${PRODUCTION_IMAGE_REPOSITORY}:${tag}")"
        printf '%s\t%s\t%s\n' "${tag}" "${deployed_at}" "${image_id}"
    done < <(docker image ls --format '{{.Tag}}' "${PRODUCTION_IMAGE_REPOSITORY}")
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

production_tag_built_image() {
    local revision="$1"

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
