<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Tagi obrazu i szybkie wycofanie

- **Plan**: context/changes/image-rollback-tags/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-19
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated criteria run during the review: `bash -n` on `deploy.sh`, `rollback.sh` and `lib/production-images.sh` OK; `rollback.sh` is `100755`; `ProductionImagesScriptTest`, `ProductionPostgresClientVersionTest` and `AdrNumberingTest` 12 passed (sail-8.5/app); `composer ci:check` on PostgreSQL (`testing_image_rollback_tags`) 1936 passed, 4 skipped; `vendor/bin/sloppy ci` passed (3 medium, all in `app/`, untouched by this change). All plan items MATCH (Dockerfile labels last, compose args, all lib functions, deploy order legacy → build → SHA tag → cleanup, the 8-step rollback with every refusal before maintenance mode, all 9 test cases, README / ADR-0107 / infrastructure.md).

## Findings

### F1 — Production trial (3.3–3.7) is ticked but has not happened

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/image-rollback-tags/plan.md:311-315
- **Detail**: Commit 3c253a7 ticks Manual 3.3–3.7 "on the user's instruction; the agent did not run them". The branch `worktree-image-rollback-tags` is in neither `origin`, `main` nor `development` (`git branch -r --contains 1c0d49c` is empty), and the server deploys with `git pull --ff-only origin main`. So the first deploy on halina175 (legacy tag, SHA tag, cleanup, rollback both ways, `/up` 200) cannot have run on this code. 3.7 also needs "the next merge to `main`" after this one. Once the change is archived, nothing reminds anyone to run the trial, and it is the only check of the `docker`/`$COMPOSE` call order (plan, decision 4).
- **Fix**: Untick 3.3–3.7 and mark them "pending — after merge to main". Keep the change at `implemented` until the trial on halina175 has run.
- **Decision**: FIXED — 3.3–3.7 unticked in plan.md Progress with a note that they wait for the merge to `main` and the first deploy on halina175.

### F2 — Docker errors read as "no image" or as an empty row

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: scripts/lib/production-images.sh:33-37, :41-52, :92; scripts/deploy.sh:22-25; scripts/rollback.sh:49, :76
- **Detail**:
  - `production_prod_image_id` sends stderr to `/dev/null` and returns 0 with empty output on any error, including a daemon or permission failure. In `deploy.sh` this makes `PREVIOUS_PROD_IMAGE_ID` empty, despite the comment at :22-23 that says a failure stops the deployment. `production_tag_legacy_prod_image` then returns early. The old `:prod` is neither tagged `legacy-…` nor protected, and the label-filtered `prune` removes it at the end.
  - `production_image_listing` runs inside `$(...)`. Without `inherit_errexit`, bash turns off `set -e` there. Reproduced with a fake `docker` whose `inspect` of one tag fails: the listing returns status 0 with the row `bbb\t\t`. Because the time is empty the row sorts as the oldest, and because the ID is empty it matches no protected ID. The cleanup then runs `docker rmi …:bbb` on it.
  - A failing `docker image ls` inside `< <(...)` also gives an empty listing with no error.
  - This breaks the project rule "Nigdy nie ukrywaj dowodów" (never hide evidence of a failure).
- **Fix**: Separate "absent" from "error", and make the listing fail loudly.
  - Check for the image with `docker image ls -q "${REPO}:prod"`, which is empty when the image is absent and fails on a daemon error.
  - In `production_image_listing`, read the `docker image ls` output into a variable first and add `|| return 1` after each `inspect`.
  - In `production_image_tags_to_remove`, abort on a row whose time or ID is empty.
  - Cover the listing with a fake `docker` shell function in the test.
  - Strength: The deploy stops with a visible error before the cleanup can pick a bad row. This matches the "psql error stops the deployment instead of reading as empty" pattern in `production-deploy.sh:45`.
  - Tradeoff: About 15 lines in the lib plus a test that stubs `docker`. A transient daemon error now stops the deploy after `up -d`, and the operator reruns the cleanup by hand.
  - Confidence: HIGH — reproduced with a fake `docker`, and both subagents found it independently.
  - Blind spot: How often `docker image inspect` fails in a race on halina175 (probably never).
- **Decision**: FIXED — `production_prod_image_id` tells absence (`docker image ls -q` empty) from a docker error; every docker call in the listing ends in `|| return 1`; `production_image_tags_to_remove` refuses a row without time or ID before printing anything. Red→green tests: daemon error, missing :prod, failed inspect stops the cleanup before `rmi`, row without time or ID.

### F3 — Redeploying the same commit prunes the image from before the deployment

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: scripts/lib/production-images.sh:80, :100; context/foundation/adr/0107-image-tags-and-rollback-without-build.md (Cleanup); README.md step 10
- **Detail**:
  - ADR-0107 and README say the cleanup never removes the image that was `:prod` before this deployment. When `deploy.sh` runs again on the same `HEAD`, `production_tag_built_image` moves the existing `:<sha>` tag to the new build. The previous image then has no tag at all, and `docker image prune --filter label=maintainer=household-treasurer` removes it. `PREVIOUS_PROD_IMAGE_ID` only protects against `rmi`, not against `prune`.
  - Running the same commit again is routine: infrastructure.md rotates a secret in `.env.prod` and then runs `deploy.sh`, and a failed deploy is simply rerun.
  - The build is not reproducible (unpinned `apt`, `composer:2`, `npm`). If such a rebuild breaks, the fast rollback to the working build of the same commit is gone. Rolling back to an older commit's tag still works.
- **Fix A ⭐ Recommended**: In `production_tag_built_image`, if `:<sha>` already points to another image, first tag that image `<sha>-<deployed-at as YYYYMMDDHHMMSS>`, then move `:<sha>`.
  - Strength: The ADR's guarantee becomes true as written. The extra tag counts toward the 3 newest, so the cleanup trims it like any other.
  - Tradeoff: A new tag format (`rollback.sh` lists it as is) and a few lines with one more test case.
  - Confidence: MED — the logic is simple, but the call order on a real server is only checked by the production trial (F1).
  - Blind spot: Whether the user ever wants to roll back to an earlier build of the same commit.
- **Fix B**: Correct ADR-0107 and README step 10: redeploying the same commit replaces the image under that SHA, and the previous build is pruned.
  - Strength: No code change. The documentation matches the behaviour.
  - Tradeoff: The fast rollback is missing after a rebuild of the same commit that breaks because of dependency drift.
  - Confidence: HIGH — documentation only.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A — `production_tag_built_image` keeps the previous build of the same commit as `<sha>-<YYYYMMDDHHMMSS>`; ADR-0107 and README step 10 describe it. Red→green test plus a first-build test.

### F4 — Retrying a rollback misses a stopped container, and the trap's advice can be wrong

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: scripts/rollback.sh:85, :34-43
- **Detail**:
  - `$COMPOSE ps -q app` lists only running containers; `-a` is needed to see stopped ones, as `production-deploy.sh:55` does with `--all`. The comment at :83-84 targets a retry after `up -d` failed. In that case the recreated container has usually exited, so the script compares with `:prod`. `:prod` already points at the target, and the script refuses with "already runs. Nothing to roll back" while the app is down. The trap stays silent because `MAINTENANCE_STARTED` is still 0.
  - A failure after `artisan down` but before `docker tag` makes the trap suggest `scripts/rollback.sh <WAY_BACK>`. That command refuses, because the container still runs WAY_BACK; only `artisan up` is needed.
- **Fix**: Use `$COMPOSE ps --all --quiet app` at :85. Set `PROD_SWITCHED=1` right after `docker tag` (:142) and print the way back only when it is set.
- **Decision**: FIXED — `ps --all --quiet app` and `PROD_SWITCHED` gating the way-back advice. Red→green tests run a copy of `rollback.sh` with a fake `docker`; a third test pins the advice after `up -d` fails.

### F5 — Script tests run with shell options other than production's

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Unit/ProductionImagesScriptTest.php:64-74 (fractional-seconds test) and the `bash -c` runners
- **Detail**:
  - The functions run as `bash -c 'source …; fn'` without `set -euo pipefail`, while `deploy.sh` and `rollback.sh` run under it. Example: without the `keep` argument, the function prints every tag with status 0 in the test, but production would fail with "unbound variable".
  - The fractional-seconds test would also pass without the `substr(…,1,19)` truncation, because the times already differ before the fraction. It does not pin the rule the plan describes.
- **Fix**: Start each `bash -c` with `set -euo pipefail;`, and give the legacy case the same second as a SHA tag so that only the truncation decides the order.
- **Decision**: FIXED (partly) — every `bash -c` in the test runs under `set -euo pipefail`. The truncation test was dropped: SHA tags are hex and sort before `legacy-…`, and a SHA tag without a fraction sorts after a legacy time with one, so the 19-character cut cannot change the order of real tags and no test can pin it.

### F6 — Nothing stops `deploy.sh` and `rollback.sh` from running at once; `LOCK_DIR` is not a lock

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: scripts/rollback.sh:25, :109
- **Detail**: `LOCK_DIR` is a `mktemp -d` holding the two migration lists; it locks nothing. A rollback started while a deploy is running (or two rollbacks) could move `:prod` under it. With a single operator this is unlikely, but the name suggests a guarantee the script does not have.
- **Fix**: Rename it to `WORK_DIR`. A shared `flock` for both scripts belongs in a follow-up if it is ever needed.
- **Decision**: FIXED — renamed to `WORK_DIR`; a shared `flock` was not added (one operator, no evidence of need).
