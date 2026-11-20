<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Production Error Tracking Implementation Plan

- **Plan**: context/changes/production-error-tracking/plan.md
- **Mode**: Deep
- **Date**: 2026-11-20
- **Verdict**: REVISE (SOUND after fixes)
- **Findings**: 2 critical, 5 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | FAIL |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
23/23 paths ✓, 20/20 refusal lines ✓, 172.30.6/7 free ✓, ADR-0112 free ✓, brief↔plan ✓. External claims checked against Bugsink 2.6.3, sentry-laravel 4.31.0 / 5.0.0-beta1 and sentry-javascript 11.15.0 sources.

## Findings

### F1 — Bugsink rejects the internal DSN host (DisallowedHost 400)

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §3 (Bugsink environment), §5 (DSN)
- **Detail**: With only `BASE_URL=https://errors.kostecki.dev`, Bugsink derives `ALLOWED_HOSTS` as `[errors.kostecki.dev, localhost, 127.0.0.1]` (`bugsink/conf_utils.py:7-32`, `conf_templates/docker.py.template:214-217`). The host check runs on every request except `/health/` (`bugsink/wsgi.py:102-135`). Ingest at `http://bugsink:8000` from the app, the tunnel and `sentry:test` would get 400.
- **Fix**: Add `ALLOWED_HOSTS=errors.kostecki.dev,bugsink` to the Phase 3 environment contract and the DSN docs.
- **Decision**: FIXED

### F2 — Global middleware sees no route and no signed-in user

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Phase 1 §2 (AddRequestContext)
- **Detail**: Global middleware runs before `dispatchToRouter()` (`vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:172-175`) and before `StartSession`. Before `$next`, `$request->route()` and `Auth::id()` are null. After `$next`, the route pipeline has already reported the exception. `route` and `user_id` would be empty in every event, and the 403 throttle per route and the Phase 4 tags depend on them.
- **Fix A ⭐ Recommended**: Split it. The global middleware sets `request_id`, `release` and `X-Request-Id`. A middleware appended to the `web` group and to the MCP route group sets `route` and `user_id`.
  - Strength: Context is set before the controller, so it reaches the log, the tracker and dehydrated queue jobs.
  - Tradeoff: Two classes, two registration points.
  - Confidence: HIGH — Kernel order and web group order.
  - Blind spot: Passport guard inside the MCP group.
- **Fix B**: Compute `route` and `user_id` at report time (`$exceptions->context()` plus `before_send`).
  - Strength: One middleware.
  - Tradeoff: Queue jobs lose route and user id; the logic is split.
  - Confidence: MED.
  - Blind spot: The throttle has to read the route from the request.
- **Decision**: FIXED (Fix A)

### F3 — sentry:test reaches ntfy only once

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Desired End State; Phase 4 §3; Phase 7 §3 steps 3–5; row 7.3
- **Detail**: `sentry:test` sends the same exception every run (`TestCommand.php:217-222`), so it lands in one issue. Bugsink alerts only on new, regressed (resolved issue) and unmuted issues (`ingest/tasks.py:253-273`, `issues/regressions.py:41-43`). The step 3 deploy creates the issue before the webhook exists, so the step 5 rerun would send nothing and 7.3 could not pass. The command also builds its own client and skips the app's `before_send` (`TestCommand.php:76-115`).
- **Fix**: End state says Bugsink only. Phase 7 resolves the issue before the rerun, so the regression proves ntfy. `deploy.sh` prints "Check Bugsink".
- **Decision**: FIXED

### F4 — before_send as a closure breaks config:cache in deploy

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 §1
- **Detail**: A Closure in `config/sentry.php` throws `LogicException` in `config:cache` (`ConfigCacheCommand.php:65-81`), and `scripts/deploy.sh:84` runs it after leaving maintenance.
- **Fix**: `before_send` as `[App\Support\ErrorTracking\SentryEventScrubber::class, 'beforeSend']`, plus a test that no value in `config('sentry')` is a Closure.
- **Decision**: FIXED

### F5 — The maintenance-mode silence also hides failed migrations

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §1; Critical Implementation Details (Maintenance mode)
- **Detail**: `deploy.sh` runs `backup:run` and `migrate --force` in maintenance (`:72`, `:75`). Nightly backups run `evenInMaintenanceMode`, and a failed deploy stays down on purpose. "Any exception while down for maintenance" silences exactly those failures.
- **Fix**: Silence only an `HttpException` 503 and a named `ApplicationDownForMaintenance` thrown by `ApplicationHealth`, and test that an artisan exception in maintenance is reported.
- **Decision**: FIXED

### F6 — The owner's 403 at warning level drops out of the log

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Phase 1 §1 and §4; Desired End State
- **Detail**: `.env.prod.example:19` sets `LOG_LEVEL=error`, so a warning never reaches the log file or stderr. Laravel's `level()` affects only the log, so the tracker most likely receives the 403 as an error. Under a no-go the 403 policy is invisible.
- **Fix A ⭐ Recommended**: `LOG_LEVEL=warning` in `.env.prod.example` (Phase 1) and `.env.prod` (Phase 7), pinned by the static test.
  - Strength: The policy works without a tracker.
  - Tradeoff: Other warnings also reach the log.
  - Confidence: MED — the volume of other warnings is unmeasured.
  - Blind spot: The level of the tracker event.
- **Fix B**: Drop `level(WARNING)` and report the 403 as an error.
  - Strength: No change to the production log level.
  - Tradeoff: Reads like a real error.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F7 — Privacy of frame arguments rests only on php.ini

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §4, Phase 4 §1 and §5
- **Detail**: sentry-php turns backtrace arguments into frame `vars`. Only `zend.exception_ignore_args=On` in `docker/8.5/php.ini` (the prod image) prevents that. CI (setup-php), the host CLI and Sail may differ, so the test proves the environment, not the code.
- **Fix**: `before_send` strips frame `vars`, and the Phase 4 test sets the ini to `0` to prove the scrubber removes them.
- **Decision**: FIXED

### F8 — Bugsink without a network to its database

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §3
- **Detail**: The `bugsink` service lists only `proxy-bugsink` and `errors-treasurer`, while `bugsink-pgsql` sits on a stack-private network.
- **Fix**: Add the stack-private network to the `bugsink` service.
- **Decision**: FIXED

### F9 — Queue promised in the end state, untested

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 4 §5
- **Detail**: `docker/8.5/supervisord.prod.conf:27-28` runs `queue:work`, and the end state and the research (`error-tracking.md:159`) promise queue coverage. Phase 4 tests only HTTP and artisan.
- **Fix**: A test where a queued job throws on the worker, with the event carrying the dehydrated `request_id`.
- **Decision**: FIXED
