# Production Error Tracking Implementation Plan

## Overview

Roadmap S-45: the owner gets a report of a production exception with its stack trace and request context without searching a log file. This includes errors the code today turns into a redirect, a 403 or a validation message. The tracker is a self-hosted Bugsink on halina175, fed by the official Sentry SDKs. A new issue triggers an ntfy notification that carries no error content. The provider choice, privacy constraints and risk register come from `context/foundation/error-tracking.md` (2026-11-20). This plan turns its "Getting Started" list into phases and records the decisions made in the planning interview.

## Current State Analysis

- **Handler.** `bootstrap/app.php:74-82` only calls `dontReport(OAuthServerException::class)` and `shouldRenderJsonWhen`. There are no report callbacks, no `stopIgnoring`, no `Context::add` and no `buildContextUsing`. Log context is therefore only `userId`. Laravel's `internalDontReport` (`vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php:170-183`) silently drops every `HttpException` (including `abort(500)`), every `AuthorizationException` and every `ValidationException`.
- **Already fixed since the 2026-11-19 audit:**
  - `/up` checks the database and maintenance mode (`app/Support/ApplicationHealth.php:17-24`).
  - The scheduler uses `evenInMaintenanceMode` and `emailOutputOnFailure` (`bootstrap/app.php:37-73`).
  - `App\Support\InvariantViolation` and `PastMonthPlanRefused` exist; `.ai/rules/errors.md` makes "report, then refuse" the rule (commit `5fe1aa62`).
- **Logging.** `config/logging.php:55-59` stacks `LOG_STACK`, and `.env.prod.example:17-20` sets `single` with `LOG_LEVEL=error`. An unwritable `laravel.log` is swallowed (audit PLB-1).
- **Release identity.** `scripts/deploy.sh:24-28` exports `GIT_REVISION`. The value only becomes an image label (`docker/8.5/Dockerfile.prod:152-154`), never a runtime value. The ARGs sit last in the file on purpose, to keep the layer cache.
- **Frontend.**
  - `resources/js/app.ts` (38 lines) has no `setup`, no `app.config.errorHandler` and no `unhandledrejection` listener.
  - The only `router.on('httpException'|'networkError')` is scoped to year selection (`resources/js/lib/budget-year-selection.ts:23-24`).
  - `useTwoFactorAuth.ts:41,54,82,92` has `catch {}` (PLB-5), and the passkey components never pass errors on (PLB-6).
  - Assets are built inside the image against a throwaway `.env` (`Dockerfile.prod:48-74`). Source maps are off (`vite.config.ts` has no `build` key).
- **Routes.** Everything except `/` and `.well-known/passkey-endpoints` sits behind `auth`. The only routes outside the `web` group (no session, no CSRF) are the MCP routes in `routes/ai.php`.
- **Outbound HTTP.** The app makes none today: no `Http::` calls and no `Http::fake` in the tests.
- **Unreported refusals after validation:**
  - `app/Actions/ImportFinancialOperations.php:151,172,210,237,275` throw `ValidationException(['rows' => $errors[0]])` (audit IMP-2). These are the only business validation of an import row at write time, so "preview OK / write refused" leaves no trace.
  - The pair actions do the same at `AttachImportPairOwnTransfer.php:58,67,85,93,113` and `ConvertImportPairFinancialOperation.php:58,74`.
  - So do month plan "items invalid" at `ApproveMonthPlan.php:74,83` and `ChangeMonthPlan.php:60,81`.
  - `Expense/IncomeController.php:74` call `abort(403)` on a type mismatch (OPS-3).
- **Infrastructure.**
  - `kostecki-dev-infra` holds stacks as compose files under `stacks/` (only `stacks/backrest`) with a `scripts/deploy-<stack>.sh`.
  - The root `docker-compose.yml:69-111` owns every `proxy-<app>` network: `internal: true`, a fixed /24 in 172.30.0.0/16. The next free subnet is `172.30.6.0/24`.
  - `scripts/uptime-check.sh:11-21` lists `slug|URL|status` checks. The `access-*` checks expect 302.
  - No compose there sets `mem_limit`. The Mikrus RAM size is recorded nowhere. Known limits add up to about 2.8 GB.
  - The infra repo works through feature branches and GitHub PRs into `master`.

## Desired End State

- An unhandled exception reaches Bugsink with its stack trace, exception class, route name, request id, user id and release (git SHA). This covers HTTP requests, artisan commands, the scheduler and the queue.
- The same exception still lands in `storage/logs/laravel-*.log` and on container stderr.
- Request bodies, cookies, query strings, frame arguments and Vue props never reach Bugsink.
- `abort(5xx)` is reported, except the 503s of maintenance mode. 403, 404, 419, 429 and validation errors stay quiet.
- An `AuthorizationException` for the signed-in owner is reported at `warning` level, at most once per route per minute.
- An import row, import pair or month plan the page showed as valid but the write refused is reported as a named exception with ids before the unchanged message reaches the user. A type mismatch on expense or income delete is an `InvariantViolation`.
- A Vue render error, an unhandled promise rejection, an Inertia network error, and a 2FA or passkey failure reach Bugsink through a same-origin tunnel route. The route also works for guests on the login, 2FA and passkey screens.
- A new, regressed or unmuted Bugsink issue sends one ntfy message. It carries the fixed title, the alert reason, the exception class and a link to the issue, and never the exception message.
- Bugsink runs on halina175 as `stacks/bugsink` behind Cloudflare Access, with fixed memory limits. Its UI host is watched by an `access-errors` uptime check.
- After every deploy, `sentry:test` appears in Bugsink with the new release. It reaches ntfy only when its issue is new or was resolved first, because Bugsink alerts only on new, regressed and unmuted issues.
- Verification: the Progress rows below, the local rehearsal of 2027-01-01 00:30, and the post-deploy production check.

### Key Discoveries:

- `PastMonthPlanRefused` (`app/Support/PastMonthPlanRefused.php`) is the "report, then refuse" shape: a `RuntimeException` with typed constructor ids and `context()`, used as `report(new …); throw $this->invalid(…)` at `ApproveMonthPlan.php:55`.
- `InvariantViolation` (`app/Support/InvariantViolation.php:17-35`) is the shape for "cannot happen after validation": ids only. Test pattern: `tests/Feature/ExpenseTest.php` asserts `shouldReport($e)` and the context ids.
- The tests use `Exceptions::fake()` / `assertReported` (`tests/Feature/ChangeMonthPlanTest.php:209-214`). Static infra-file tests read compose and Dockerfile text (`tests/Unit/ProductionPostgresClientVersionTest.php`). Env-to-config pinning is tested in `tests/Feature/AppNameIndependentConfigTest.php`.
- Every import write-refusal site has the import `account_id`, `$row['index']`, `$row['type']`, the related ids (category, term deposit, other account) and the validator's failed field keys (`$validator->errors()->keys()`, today flattened away by `->all()`).
- A `FormRequest::authorize()` that returns false (e.g. `UpdateExpenseRequest.php:29-31` on a type mismatch) throws `AuthorizationException`, so the 403 policy below covers it without a code change.
- `docker-compose.prod.yml:27-31,80-84`: the app joins `treasurer-internal` (internet egress) and the external `proxy-treasurer`. Its `mem_limit` is 1g. `.env.prod` sits in the repo root on the server and is mounted read-only as `.env`.
- ntfy is the public `ntfy.sh` with a random topic, stored as `BACKUP_ALERT_URL` in the infra `.env` (`kostecki-dev-infra/scripts/notify-failure.sh:11-26`). The topic name is the secret.

## What We're NOT Doing

- Source maps and `@sentry/vite-plugin` / `sentry-cli`. Browser stack traces stay minified in this version, with the component name and release as the anchors. Adding maps later is its own change.
- Performance tracing, profiling, session replay, log shipping, cron check-ins. Healthchecks.io stays the dead-man's switch.
- Reporting `ValidationException` for user-fixable input, `ModelNotFoundException` from route model binding, 404, 419 or 429.
- Improving the import refusal message itself (`rows.{index}` key, date in the message). That is import UI work and overlaps S-42.
- Converting the `firstOrFail()` re-locks that only fail on a concurrent delete (`ManageFinancialOperation.php:246,268,273`, `DeleteCategory:20` and similar).
- The duplicated action-level `AuthorizationException` guards (`StartBudgetExecution.php:15`, `ApproveMonthPlan.php:27`, `ChangeMonthPlan.php:32`). They become visible through the 403 policy and are not rewritten.
- The other audit findings: IMP-1, IMP-3…IMP-12 except IMP-2, OPS-1/OPS-2, BKP-*, PLB-7…PLB-10.
- The no-provider fallback (handler → ntfy + log). It is planned only if the Phase 3 gate says no-go (see Phase 3).
- sentry-laravel 5.x. Pin `^4.31`.

## Implementation Approach

The phases are ordered so that the work that is useful with any tracker comes first: reporting policy, context, log sinks and the reported guards. That work runs while the owner measures halina175. Phase 3 is the go/no-go gate. Every phase after it assumes Bugsink.

If the gate says no-go, implementation stops after Phase 3's measurement row and phases 4–6 are re-planned as the no-provider variant (`error-tracking.md` §3). They are not built speculatively.

The infra work lands in `kostecki-dev-infra` on its own branch and PR. Every step that touches halina175 or the Cloudflare panel is the owner's (`infrastructure.md`, Approval).

The SDK is inert whenever `SENTRY_LARAVEL_DSN` is empty, so local, CI and test runs never send anything. Rollback on the app side is to empty the DSN and run `config:cache`.

## Critical Implementation Details

- **Ordering in `withExceptions`.** `Integration::handles($exceptions)` must be the first reporting registration, and nothing may call `->stop()`. The tracker then receives an exception even when the log write fails (PLB-1), and the log keeps its copy. The `sentry` log channel must not be added to `LOG_STACK`, or every exception would be sent twice.
- **Maintenance mode.** Lifting `HttpException` from `internalDontReport` would report every 503 served during `artisan down` in each deploy. The `/up` maintenance failure (`ApplicationHealth`) would also fire on every uptime ping during a deploy. Only these two stay unreported while the app is down for maintenance. Everything else is still reported: `deploy.sh` runs `backup:run` and `migrate --force` in maintenance (`:72`, `:75`), and the nightly backups run `evenInMaintenanceMode`. A stuck maintenance mode is already caught by the `site-*` uptime checks.
- **Privacy defaults are permissive.**
  - In sentry-php, `max_request_body_size` defaults to `medium`.
  - A partial `data_collection` array flips every omitted key to permissive.
  - In `@sentry/vue` 11, `attachProps` defaults to true and an unset `dataCollection` collects user info, cookies and bodies.

  Set each value explicitly and guard it with a test (Phases 4 and 5).
- **Deploy ordering across repos.** The app's `docker-compose.prod.yml` joins the external `errors-treasurer` network in Phase 4. A production deploy of that commit fails until the infra root compose has created the network (Phase 3 deploy). Phase 7 deploys in that order.
- **The tunnel and the relay must stay outside the `web` group.** Without session, CSRF, `auth` and `verified`, guests' errors and Bugsink's webhook can reach them. Each has its own named rate limiter.

## Phase 1: Reporting policy and request context

### Overview

Provider-independent changes that decide what gets reported and with which context, and make the log survive an unwritable file. Each change is useful even if the Phase 3 gate fails.

### Changes Required:

#### 1. Exception reporting policy

**Files**: `bootstrap/app.php`, `app/Support/ApplicationHealth.php`, `app/Support/ApplicationDownForMaintenance.php` (new)

**Intent**: Report server-error `HttpException`s and the owner's `AuthorizationException`s, which Laravel ignores today. Keep the noisy classes quiet. Leave a slot so that Phase 4 can register the tracker before every other reporting callback.

**Contract**: In `withExceptions`:
- `stopIgnoring(HttpException::class)`, plus `dontReportWhen` for `HttpException` with status < 500.
- `dontReportWhen` the app is down for maintenance **and** the exception is an `HttpException` with status 503 or the new `App\Support\ApplicationDownForMaintenance`. `ApplicationHealth` throws that named exception instead of its `RuntimeException`. Every other exception in maintenance (migrations, backups, queue, scheduler) is still reported.
- `stopIgnoring(AuthorizationException::class)`, plus `dontReportWhen` there is no authenticated user, and `level(AuthorizationException::class, LogLevel::WARNING)`.
- `throttle()` returning `Limit::perMinute(1)->by(<exception class>|<route name>)` for `AuthorizationException` only.

`ValidationException`, `ModelNotFoundException`, `TokenMismatchException` and `AuthenticationException` stay ignored. Check the exact Laravel 13 method names with `search-docs` ("exception handling reporting throttle level").

#### 2. Request context middleware

**Files**:
- `app/Http/Middleware/AddRequestContext.php` (new): global middleware in `bootstrap/app.php`, so `/mcp` and the routes outside `web` get it too.
- `app/Http/Middleware/AddRouteContext.php` (new): appended to the `web` group, so it runs after `StartSession`, and added to the MCP route group in `routes/ai.php` after its auth middleware.

**Intent**: Every log entry and tracker event carries the route name, a request id, the user id and the release (PLB-4). The request id is returned to the client so a report can be matched to a request.

Global middleware runs before routing (`Kernel::sendRequestThroughRouter`, `vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:172-175`) and before the session starts. At that point `$request->route()` and `Auth::id()` are null, and after `$next` the exception has already been reported. That is why the keys are split between the two middleware.

**Contract**:
- `AddRequestContext`, before `$next`, calls `Context::add` with:
  - `request_id` (a fresh UUID; the incoming `X-Request-Id` is ignored)
  - `release` (`config('app.release')`)

  The response gets an `X-Request-Id` header.
- `AddRouteContext`, before `$next`, calls `Context::add` with:
  - `route` (route name, or the path pattern when unnamed)
  - `user_id` (int or null; never the e-mail)

  On MCP, check that `Auth::id()` resolves through the Passport guard inside that group.
- No request input, amounts or descriptions go into the context.
- Laravel Context is dehydrated into queued jobs automatically.

#### 3. Release identity in the image

**Files**: `docker/8.5/Dockerfile.prod`, `config/app.php`, `.env.example`, `.env.prod.example`

**Intent**: The running container knows its git SHA, so tracker events and log context can be tied to an image tag (ADR-0107).

**Contract**:
- `ENV APP_RELEASE=$GIT_REVISION` after the existing `ARG GIT_REVISION=` in the runtime stage. Keep it last, below the cache comment at 150-151, so the cache is preserved.
- `config('app.release')` reads `env('APP_RELEASE')`, null when unset.
- `docker-compose.prod.yml` already passes `GIT_REVISION`, so it needs no change.

#### 4. Log sinks and frame arguments

**Files**: `.env.prod.example`, `docker/8.5/php.ini`

**Intent**: Logs rotate and also go to stderr, so a non-writable `laravel.log` no longer means silence (PLB-1, BKP-8). The owner's 403 warnings reach the log, which drops them today at `LOG_LEVEL=error`. Exception frames stop carrying argument values, which could hold amounts.

**Contract**: `.env.prod.example`: `LOG_STACK=daily,stderr`, `LOG_LEVEL=warning`. `php.ini`: `zend.exception_ignore_args = On`. The real `.env.prod` changes in Phase 7.

#### 5. Tests

**Files**: `tests/Feature/ExceptionReportingPolicyTest.php` (new), `tests/Feature/RequestContextTest.php` (new), and a new `tests/Unit/ProductionErrorTrackingConfigTest.php` in the style of `ProductionPostgresClientVersionTest`

**Intent**: Pin the policy and context so later edits can't silently undo them.

**Contract**:
- **Reported:** `abort(500)` and `abort(503)` outside maintenance; an `AuthorizationException` for a signed-in user.
- **Reported in maintenance:** an artisan command that throws while the app is down for maintenance.
- **Not reported:** `abort(403)`, `abort(404)`, a `ValidationException`, a 503 and the `/up` `ApplicationDownForMaintenance` in maintenance mode, a guest `AuthorizationException`.
- **Throttle:** a second `AuthorizationException` on the same route within a minute is throttled.
- **Context:** an exception thrown in a named `web` route by a signed-in user carries non-null `route` and `user_id`, plus `request_id` and `release`, in its log context. The response has `X-Request-Id`.
- **Static file test:** `php.ini` has `zend.exception_ignore_args = On`, `.env.prod.example` has `LOG_STACK=daily,stderr` and `LOG_LEVEL=warning`, and `Dockerfile.prod` sets `APP_RELEASE` from `GIT_REVISION` after the ARG.

### Success Criteria:

#### Automated Verification:

- Policy, context and static config tests pass: `php artisan test --compact tests/Feature/ExceptionReportingPolicyTest.php tests/Feature/RequestContextTest.php tests/Unit/ProductionErrorTrackingConfigTest.php`
- Full gate passes on PostgreSQL in the worktree (`composer ci:check` via the worktree command in AGENTS.md, database `testing_production_error_tracking`) plus `vendor/bin/sloppy ci`

#### Manual Verification:

- In a worktree preview, `abort(500)` from a temporary tinker route lands in `storage/logs/laravel-<date>.log` and on the container's stderr, with `route`, `request_id`, `user_id` and `release` in the entry

**Implementation Note**: After automated verification passes, pause for the owner's confirmation of the manual check before Phase 2.

---

## Phase 2: Report-then-refuse guards

### Overview

Refusals that mean "the page showed this as valid, the write disagrees" become reported named exceptions with ids. The user-facing message stays unchanged. A type mismatch on delete becomes an `InvariantViolation`.

### Changes Required:

#### 1. Import write refusals (IMP-2) and pair refusals

**Files**: `app/Support/CsvImport/ImportWriteRefused.php` (new), `app/Actions/ImportFinancialOperations.php`, `app/Actions/AttachImportPairOwnTransfer.php`, `app/Actions/ConvertImportPairFinancialOperation.php`

**Intent**: Before each refusal, `report()` a named exception, so "preview OK / write refused" becomes visible, as `PastMonthPlanRefused` does for the month plan. The thrown `ValidationException` and its message stay as they are.

**Contract**:
- `ImportWriteRefused extends RuntimeException` with `context()` returning ids only:
  - `stage`: `row`, `own_transfer_pair` or `financial_operation_pair`
  - `rule`: the validator's failed field keys, or a fixed rule name at a pair mismatch site
  - `row_index`, `row_type`, `account_id`, `year`
  - the related id that applies: `category_id`, `interest_category_id`, `term_deposit_id`, `other_account_id`, `own_transfer_id` or `financial_operation_id`
- Never amounts, descriptions or dates beyond the year.
- Sites:
  - `ImportFinancialOperations.php:151,172,210,237,275`
  - `AttachImportPairOwnTransfer.php:58,67,85,93,113`
  - `ConvertImportPairFinancialOperation.php:58,74`
- Not converted: `ImportFinancialOperations.php:73` (nothing selected, user-caused), and the "already paired / gone" sites (`AttachImportPairOwnTransfer.php:44,52,122`, `ConvertImportPairFinancialOperation.php:52`), which a second tab legitimately causes.

#### 2. Month plan items refusal (MPL-4)

**Files**: `app/Support/MonthPlanItemsRefused.php` (new), `app/Actions/ApproveMonthPlan.php`, `app/Actions/ChangeMonthPlan.php`

**Intent**: When the submitted item set does not match the recomputed proposal or plan, report which ids differ before refusing with the same message.

**Contract**: `MonthPlanItemsRefused extends RuntimeException` with `context()`: `action` (`approve`/`change`), `budget_id`, `budget_year`, `month`, `missing_budget_line_ids`, `unexpected_budget_line_ids`, `server_today`. Sites: `ApproveMonthPlan.php:74,83`, `ChangeMonthPlan.php:60,81`. The ids are budget line ids, or the identifiers the item set is keyed by at that site.

#### 3. Type mismatch on delete (OPS-3)

**Files**: `app/Http/Controllers/ExpenseController.php`, `app/Http/Controllers/IncomeController.php`

**Intent**: The UI never sends a delete for the wrong operation type, so a mismatch is a client bug or a stale list, not an ownership violation. It must be reported (`.ai/rules/errors.md` rule a).

**Contract**: `abort(403)` at `:74` becomes `throw new InvariantViolation(<message>, ['user_id', 'financial_operation_id', 'expected_type', 'actual_type'])`. The `Update*Request::authorize()` mismatch stays a 403 and is now reported through the Phase 1 policy.

#### 4. Rule

**File**: `.ai/rules/errors.md`

**Intent**: Make the new pattern the default for the next refusal someone writes.

**Contract**: Add one rule: "When a write refuses a state that its preview or page presented as valid, `report()` a named exception with ids before throwing the user message (`ImportWriteRefused`, `MonthPlanItemsRefused`, `PastMonthPlanRefused`)." Then run `php artisan boost:index-rules`.

#### 5. Tests

**Files**: `tests/Feature/CsvImportTest.php` (or the existing import write tests), `tests/Feature/ApproveMonthPlanTest.php`, `tests/Feature/ChangeMonthPlanTest.php`, `tests/Feature/ExpenseTest.php`, `tests/Feature/IncomeTest.php`

**Intent**: One test per refusal kind proves the report, its ids and the unchanged user message.

**Contract**:
- With `Exceptions::fake()`, a row refused at write time (e.g. its year's Budget is a draft) asserts that `ImportWriteRefused` was reported with `stage=row`, `row_index`, `account_id`, `rule` and `year`. The context must contain no amount or description, and the session error must be the same message as before.
- One pair mismatch test, the same way.
- One month plan test where the submitted ids differ from the proposal.
- One test where a delete with the wrong type is an `InvariantViolation` that should be reported.

### Success Criteria:

#### Automated Verification:

- Touched feature tests pass on PostgreSQL (worktree command, `testing_production_error_tracking`): `CsvImportTest`, `ApproveMonthPlanTest`, `ChangeMonthPlanTest`, `ExpenseTest`, `IncomeTest`
- `php artisan boost:index-rules` leaves `.ai/rules/index.md` consistent
- Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci`

#### Manual Verification:

- In a worktree preview: preview an import with a January row, switch that year's Budget back to draft (or use a fixture year in draft), then import. The message is the same as before, and `laravel-<date>.log` holds an `ImportWriteRefused` entry with ids and no amount

**Implementation Note**: Pause for the owner's confirmation before Phase 3.

---

## Phase 3: RAM gate and Bugsink stack (kostecki-dev-infra)

### Overview

The owner measures halina175 and decides go or no-go. On go, the agent writes the Bugsink stack, both new networks, the deploy script, the uptime check and the docs in `kostecki-dev-infra` on branch `feat/bugsink`, and opens a PR. The owner merges, deploys and does the DNS and Cloudflare Access steps.

### Changes Required:

#### 1. Measurement and go/no-go (owner, before any infra code)

**File**: `kostecki-dev-infra/docs/ARCHITECTURE.md` (new section "Memory budget")

**Intent**: Record the Mikrus plan size and current usage, which closes the open item in `infrastructure.md`, and decide whether Bugsink fits.

**Contract**:
- Measured on halina175:
  - the plan size from the Mikrus panel;
  - `free -m` (`available`);
  - `docker stats --no-stream`;
  - `nproc` on the host and inside a container (`docker run --rm alpine nproc`);
  - the memory peak while `docker compose -f docker-compose.prod.yml build app` runs (watch `free -m`).
- **Go** when idle `available` minus the build peak is at least 1 GB. That is 768 MB for the Bugsink limits (512m app, 256m Postgres) plus 256 MB margin.
- **No-go** → stop here, mark the change, and re-plan phases 4–6 as the no-provider variant.

#### 2. Networks

**File**: `kostecki-dev-infra/docker-compose.yml`

**Intent**: Give Bugsink a Traefik network for its UI, and a private network shared only with the treasurer app for ingest and the webhook.

**Contract**:
- `proxy-bugsink`: `internal: true`, `172.30.6.0/24`, and Traefik joins it.
- `errors-treasurer`: `internal: true`, `172.30.7.0/24`. Traefik does **not** join it, and it is declared with a fixed `name:` so other compose projects can join it as `external`.

#### 3. Bugsink stack

**Files**: `kostecki-dev-infra/stacks/bugsink/docker-compose.yml`, `kostecki-dev-infra/scripts/deploy-bugsink.sh`, `kostecki-dev-infra/.env.example`, `kostecki-dev-infra/.github/dependabot.yml`

**Intent**: Run Bugsink and its own database following the Backrest pattern, with fixed memory limits and no internet access.

**Contract**:
- **`bugsink` service:**
  - image `bugsink/bugsink:2.6` (pinned minor), `container_name: bugsink`, `mem_limit: 512m`;
  - networks `proxy-bugsink`, `errors-treasurer` and the stack-private `bugsink-internal` (to reach `bugsink-pgsql`);
  - Traefik labels for `errors.kostecki.dev` with `app-security@file`, as for Backrest.
- **Bugsink environment:**
  - `SECRET_KEY`, the admin credentials;
  - `SINGLE_USER=True`, `PHONEHOME=False`, `BEHIND_HTTPS_PROXY=True`, `BASE_URL=https://errors.kostecki.dev`;
  - `ALLOWED_HOSTS=errors.kostecki.dev,bugsink`. Without it Bugsink derives the hosts from `BASE_URL` only, and answers 400 (DisallowedHost) to ingest at `bugsink:8000` (`bugsink/conf_utils.py`, Bugsink 2.6). The variable replaces the derived list, so the public host stays in it;
  - `MAX_EVENT_AGE_DAYS` (90), `ALERTS_WEBHOOK_DENY_NON_GLOBAL=False`, `SNAPPEA_NUM_WORKERS=1`;
  - gunicorn workers pinned to 1–2 if Bugsink exposes a setting (check its docs; otherwise record the observed count).
- **`bugsink-pgsql` service:** the Postgres image from Bugsink's official compose sample (`postgres:17-alpine` at research time), `mem_limit: 256m`, a named volume, on a stack-private network only.
- **Secrets:** in a gitignored env file on the server; the template lists the variable names with empty values.
- **Deploy script:** `deploy-bugsink.sh` mirrors `deploy-backrest.sh` (`VPS_BUGSINK_DIR`, copy compose, `pull`, `up -d`).
- **Dependabot:** add `/stacks/bugsink`.
- The data is disposable: keep it out of restic and R2.

#### 4. Uptime check

**Files**: `kostecki-dev-infra/scripts/uptime-check.sh`, `kostecki-dev-infra/docs/monitoring.md`

**Intent**: A dead Bugsink must not look like "no errors".

**Contract**: Add `"access-errors|https://errors.kostecki.dev/|302"` to `CHECKS`, and the row to the `monitoring.md` table. That is 11 of 20 checks on the free plan.

#### 5. Docs

**Files**: `kostecki-dev-infra/docs/bugsink.md` (new), `kostecki-dev-infra/docs/ARCHITECTURE.md`, `kostecki-dev-infra/README.md`

**Intent**: The owner's runbook for DNS, Access, the first login, creating the project and DSN, the webhook, upgrades and rollback.

**Contract**:
- **DNS and Access:** a `docs/bugsink.md` section with an `errors` A record, Proxied, Full (strict), and a Cloudflare Access application for the whole host (One-time PIN, as for `backup.`).
- **Project:**
  - the project is named `domowy-skarbnik`;
  - the DSN uses the internal host `http://<key>@bugsink:8000/<project>`, which `ALLOWED_HOSTS` must list;
  - the webhook targets the app relay URL from Phase 6 over `errors-treasurer`, and is configured in Phase 7.
- **Upgrade and rollback:** pin the tag, then `up -d`.
- **Architecture:** `ARCHITECTURE.md` lists the new stack and networks.

#### 6. Pull request

**Intent**: The infra change is reviewed and merged by the owner.

**Contract**: Branch `feat/bugsink` from `master`, Conventional Commits in English (`feat(bugsink): …`), a PR to `master`. The agent does not push to `master`, merge, or run anything on halina175.

### Success Criteria:

#### Automated Verification:

- `docker compose -f stacks/bugsink/docker-compose.yml config` and `docker compose -f docker-compose.yml config` validate (run locally in `kostecki-dev-infra`)
- `bash -n scripts/deploy-bugsink.sh scripts/uptime-check.sh` passes
- PR to `master` is open with the stack, networks, script, Dependabot entry, uptime line and docs

#### Manual Verification:

- Owner measured halina175, recorded it in `ARCHITECTURE.md`, and decided **go** against the threshold above (or no-go → re-plan)
- Owner merged the PR, ran `docker compose up -d` for the infra root (networks) and `deploy-bugsink.sh`, and created DNS and the Access application
- `https://errors.kostecki.dev/` answers 302 to Access, and after login the Bugsink UI shows the `domowy-skarbnik` project and its DSN
- `docker stats --no-stream` shows both Bugsink containers within their limits after 24 h idle

**Implementation Note**: Phases 4–6 start only after the go decision. Phase 4 can be written and tested before the stack is deployed, but its production deploy waits for the networks.

---

## Phase 4: Server SDK

### Overview

Install and wire `sentry/sentry-laravel` with a private configuration, register it before the logger, connect the app container to `errors-treasurer`, and record the decision in an ADR.

### Changes Required:

#### 1. Dependency and config

**Files**: `composer.json`, `composer.lock`, `config/sentry.php` (published)

**Intent**: Add the approved SDK, pinned to the 4.x line, with every privacy-relevant option set explicitly.

**Contract**:
- `"sentry/sentry-laravel": "^4.31"`.
- **DSN and identity:** `dsn` from `SENTRY_LARAVEL_DSN` (empty = inert), `release` from `config('app.release')`, `environment` from `APP_ENV`.
- **Privacy:** `send_default_pii` false, `max_request_body_size` `'never'`, no partial `data_collection` array.
- **Breadcrumbs and tracing:** SQL breadcrumbs without bindings; `traces_sample_rate` and `profiles_sample_rate` null.
- **Transport:** `http_connect_timeout` 1, `http_timeout` 2.
- **`before_send`** is a static callable `[App\Support\ErrorTracking\SentryEventScrubber::class, 'beforeSend']`, never a Closure: a Closure in config breaks `config:cache`, which `deploy.sh:84` runs. It:
  - drops `request.data`, `request.cookies`, `request.headers` and `request.query_string`, and keeps the method and the URL path;
  - removes `vars` from every stack frame, so the privacy of arguments does not rest only on `zend.exception_ignore_args`;
  - sets tags `route`, `request_id`, `user_id` and `release` from Laravel Context.

  Check with `search-docs` / the sentry-laravel docs whether 4.31 already attaches Laravel Context, so the tags are not duplicated.

#### 2. Registration before the logger

**File**: `bootstrap/app.php`

**Intent**: The tracker sees every reported exception first, including when the log write fails.

**Contract**: `Integration::handles($exceptions)` is the first line of `withExceptions`, before the Phase 1 policy. There is no `->stop()`, and `LOG_STACK` does not include the `sentry` channel.

#### 3. Production wiring

**Files**: `docker-compose.prod.yml`, `.env.prod.example`, `scripts/deploy.sh`

**Intent**: The app reaches Bugsink over the private network, and every deploy proves the path.

**Contract**:
- The `app` service joins the external network `errors-treasurer`, keeping `treasurer-internal` and `proxy-treasurer`.
- `.env.prod.example` lists `SENTRY_LARAVEL_DSN=` with a comment pointing to `kostecki-dev-infra/docs/bugsink.md`.
- After the cache steps, `deploy.sh` runs `artisan sentry:test` when the DSN is set and prints "Check Bugsink".
- `sentry:test` sends the same exception every time, so Bugsink groups it into one issue. ntfy fires only on its first run or after the issue is resolved. The command builds its own client and skips our `before_send`, so it proves the path, not the privacy settings. The Phase 4 tests prove those.
- Extend the static file test from Phase 1: compose joins `errors-treasurer` as external.

#### 4. ADR

**File**: `context/foundation/adr/0112-error-tracking-with-bugsink.md`

**Intent**: Record the decision and its reasons (dependencies, provider, privacy settings, reporting policy for 5xx and 403, alert content) so later changes don't reopen it.

**Contract**: Follow the format of the existing ADRs. Link `context/foundation/error-tracking.md`. State the pins (`^4.31`, `^11`) and that a 5.x upgrade is its own change.

#### 5. Tests

**File**: `tests/Feature/ErrorTrackingServerTest.php` (new)

**Intent**: Prove that an event leaves the app without private data and with the context tags.

**Contract**:
- **Setup:** a test DSN, plus a fake `http_client` (sentry-php's `HttpClientInterface`) bound for the test that captures envelopes.
- **HTTP request:** a POST with a body containing `1234,56` hits a route that throws. Assert that:
  - an `event` envelope has the exception class and the `route`, `request_id`, `user_id` and `release` tags;
  - it has no request body, cookies or query string;
  - the frames carry no `vars`, with `zend.exception_ignore_args` set to `0` in the test, so the scrubber is what removes them;
  - the body value does not appear anywhere in the payload.
- **Artisan:** an artisan command that throws sends an event.
- **Queue:** a queued job dispatched from a request throws on the worker. Its event carries the `request_id` dehydrated from the dispatching request.
- **Config pin:** a test pins `send_default_pii`, `max_request_body_size`, the timeouts and the absence of `data_collection`. It also checks that no value in `config('sentry')` is a Closure.

### Success Criteria:

#### Automated Verification:

- `composer show sentry/sentry-laravel` reports 4.x ≥ 4.31
- `php artisan test --compact tests/Feature/ErrorTrackingServerTest.php tests/Unit/ProductionErrorTrackingConfigTest.php` passes
- Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci`

#### Manual Verification:

- Against a local Bugsink container (the Phase 3 compose run locally), a thrown exception in the worktree preview shows up as an issue. It has the stack trace, the tags and the release, and no request body

**Implementation Note**: Pause for the owner's confirmation before Phase 5.

---

## Phase 5: Browser SDK and tunnel

### Overview

Initialise `@sentry/vue` with private settings, and send its envelopes to a same-origin tunnel route that forwards only our project's events to Bugsink over the private network. The route works for guests, so the login, 2FA and passkey screens are covered.

### Changes Required:

#### 1. Dependency and option builder

**Files**: `package.json`, `package-lock.json`, `resources/js/lib/error-tracking.ts` (new), `resources/js/lib/error-tracking.test.ts` (new)

**Intent**: Keep every privacy-relevant browser option in one pure, tested function.

**Contract**:
- `"@sentry/vue": "^11"`.
- `buildErrorTrackingOptions({ dsn, release, environment, tunnel })` returns the init options:
  - privacy: `attachProps: false`, `sendDefaultPii: false`, an explicit minimal `dataCollection` that collects nothing beyond the error;
  - integrations: no tracing, replay, profiling or session integrations;
  - transport: `sendClientReports: false`, `tunnel` set;
  - `beforeBreadcrumb` drops `console` breadcrumbs;
  - `beforeSend` strips `request.cookies`, `request.data` and the query string from `request.url`.
- The vitest test asserts each of these values.

#### 2. Runtime config and initialisation

**Files**: `resources/views/app.blade.php`, `resources/js/app.ts`

**Intent**: The DSN and release come from the server at runtime, not from the image build (which uses a throwaway `.env`), and initialisation happens before the Vue app mounts.

**Contract**:
- When the DSN is set, `app.blade.php` renders the DSN, release, environment and tunnel URL as `data-*` attributes on a single element (no Inertia prop, so no page prop contract changes).
- When the attributes are present, `app.ts` initialises `@sentry/vue` with the Vue app instance via the Inertia 3 hook that exposes the app before mount (check `search-docs`: Inertia `createInertiaApp` setup/withApp).
- `router.on('networkError')` is captured as a client error.
- `httpException` is not captured, because server 5xx are already reported server-side.

#### 3. Swallowed client errors (PLB-5, PLB-6)

**Files**: `resources/js/composables/useTwoFactorAuth.ts`, `resources/js/components/PasskeyVerify.vue`, `resources/js/components/PasskeyRegister.vue`

**Intent**: Errors these components catch for the UI also reach the tracker. The user-facing behaviour stays the same.

**Contract**: Bind each `catch {}` at `useTwoFactorAuth.ts:41,54,82,92` and pass the error to a `reportClientError(error)` helper in `resources/js/lib/error-tracking.ts`, which is a no-op when the tracker is not initialised. Do the same for the passkey `onError` paths. Follow `.ai/rules/js-auth.md`.

#### 4. Tunnel route

**Files**: `routes/error-tracking.php` (new, registered from `bootstrap/app.php` `withRouting(then: …)` without the `web` group), `app/Http/Controllers/ErrorTunnelController.php` (new), `app/Providers/AppServiceProvider.php` (rate limiter), `config/error-tracking.php` (new)

**Intent**: Browser envelopes reach Bugsink without exposing it publicly and without letting the route relay anything else.

**Contract**:
- `POST /error-tracking/tunnel`, named route, no session, CSRF, `auth` or `verified`, `throttle:error-tunnel` (named limiter, 30 per minute by IP).
- **Rejections:**
  - a body over 256 KB → 413;
  - an envelope whose header `dsn` differs from the configured DSN → 403;
  - an envelope with any item type other than `event` → 422.
- **Forwarding:** valid envelopes go with `Http::withBody(...)->timeout(2)` to the internal envelope endpoint derived from the DSN. Bugsink's status is passed through.
- **Bugsink unreachable:** 502, and the connection exception is `report()`ed, so it lands in the log even when the tracker is down.
- **Config:** `config/error-tracking.php` holds the tunnel limits. It reads the DSN from `config('sentry.dsn')`.

#### 5. Tests

**Files**: `tests/Feature/ErrorTunnelTest.php` (new)

**Intent**: Prove the tunnel accepts exactly what it should, for guests too.

**Contract**:
- With `Http::fake`, a guest POST of a valid event envelope (no CSRF token) is forwarded to the internal URL with an unchanged body.
- A foreign DSN, an oversize body and a `session` item are each rejected and nothing is forwarded.
- A failed forward returns 502 and is reported.
- The 31st request in a minute is throttled.
- `app.blade.php` renders the data attributes only when the DSN is set.

### Success Criteria:

#### Automated Verification:

- `npm test` passes, including `resources/js/lib/error-tracking.test.ts`
- `php artisan test --compact tests/Feature/ErrorTunnelTest.php` passes
- `npm run build` then `npm run types:check` pass (Wayfinder regenerated for the new route)
- Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci`

#### Manual Verification:

- Against the local Bugsink: a forced Vue render error on an authenticated page, an unhandled rejection, and a failed 2FA confirmation on the logged-out 2FA screen each appear as an issue with the release and component name, and with no props, cookies or form values
- The browser network tab shows envelopes going only to `/error-tracking/tunnel`

**Implementation Note**: Pause for the owner's confirmation before Phase 6.

---

## Phase 6: Alert relay to ntfy

### Overview

Bugsink's custom webhook POSTs the full issue JSON with no secret header. An app route with a secret in its URL receives it over `errors-treasurer` and posts a minimal message to the existing ntfy topic.

### Changes Required:

#### 1. Relay route

**Files**: `routes/error-tracking.php`, `app/Http/Controllers/ErrorAlertRelayController.php` (new), `config/error-tracking.php`, `app/Providers/AppServiceProvider.php`, `.env.prod.example`

**Intent**: The owner gets one phone notification per new, regressed or unmuted issue. It carries nothing that could hold financial data, because ntfy.sh is public.

**Contract**:
- **Route:** `POST /error-tracking/alerts/{secret}`, outside `web`, `throttle:error-alerts` (10 per minute).
- **Secret:** compared with `hash_equals` to `config('error-tracking.alert_secret')` (`ERROR_ALERT_SECRET`). A mismatch, or an empty secret in config, returns 404.
- **What is forwarded to `ERROR_ALERT_NTFY_URL`:**
  - `Title: Domowy Skarbnik: nowy błąd`;
  - `Click:` the Bugsink issue URL;
  - `Tags: warning`;
  - body `<alert_reason>: <exception class>`.
- **Payload mapping:** fields come from Bugsink's documented custom webhook payload (check `bugsink.com/docs/alerts-custom-webhook/` for the field names). Bugsink's `title` or exception value, which holds the message, is **never** forwarded.
- **Errors:** an ntfy failure propagates (500 and reported). Bugsink treats the webhook as failed, and the failure lands in the log.

#### 2. Tests

**Files**: `tests/Feature/ErrorAlertRelayTest.php` (new), `tests/Fixtures/bugsink-webhook.json` (new)

**Intent**: Prove that the relay forwards only the allowed fields.

**Contract**:
- A fixture payload in Bugsink's shape has a title and value containing `Prąd 1 234,56 zł`.
- With `Http::fake`, the ntfy request has the fixed title, the reason, the exception class and the issue URL, and the amount string appears in neither the headers nor the body.
- A wrong secret returns 404 and sends nothing.
- An empty configured secret returns 404.
- An ntfy 500 makes the relay fail and the failure is reported.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/ErrorAlertRelayTest.php` passes
- Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci`

#### Manual Verification:

- Against the local Bugsink, with its webhook pointed at the worktree's relay URL and a throwaway ntfy topic: a new issue produces exactly one notification with the fixed title, class and link, and a second event of the same issue produces none

**Implementation Note**: Pause for the owner's confirmation before Phase 7.

---

## Phase 7: Rehearsal and production rollout

### Overview

Rehearse the year turn against a local Bugsink, then deploy in dependency order and prove the production path end to end. Target production by about 15 December 2026.

### Changes Required:

#### 1. Year-turn rehearsal (agent, local)

**Intent**: Prove that the errors this slice exists for arrive before 1 January.

**Contract**:
- Worktree preview (`context/foundation/agents/worktree-preview.md`) with the local Bugsink from Phase 3's compose, and `app:rehearsal-clock` set to 2027-01-01 00:30.
- Approve December's month plan → `PastMonthPlanRefused` arrives.
- Import a statement spanning 31.12–2.01 with a January row whose Budget is a draft → `ImportWriteRefused` arrives with `year=2027`.
- A browser render error on the login page as a guest arrives through the tunnel.
- A webhook from each new issue reaches the throwaway ntfy topic.

#### 2. Docs and roadmap

**Files**: `context/foundation/infrastructure.md`, `context/foundation/roadmap.md`, `README.md` (production section, if it lists `.env.prod` keys)

**Intent**: The deployed setup is discoverable from the docs agents read.

**Contract**:
- `infrastructure.md` gets the line proposed at the end of `error-tracking.md`, and the error-tracking open item is marked closed.
- The README lists the new `.env.prod` keys: `SENTRY_LARAVEL_DSN`, `ERROR_ALERT_SECRET`, `ERROR_ALERT_NTFY_URL`, `LOG_STACK`, `LOG_LEVEL`.
- The roadmap status follows the change lifecycle.

#### 3. Production deploy (owner)

**Intent**: Go live in the order the networks and the webhook require.

**Contract**:
1. The infra root `up -d` (networks) and Bugsink are already up (Phase 3).
2. `.env.prod` changes:
   - `SENTRY_LARAVEL_DSN` (from the Bugsink project)
   - `ERROR_ALERT_SECRET` (random, at least 32 characters)
   - `ERROR_ALERT_NTFY_URL` (same topic as `BACKUP_ALERT_URL`)
   - `LOG_STACK=daily,stderr`
   - `LOG_LEVEL=warning`
3. `development` → `main` release PR and `scripts/deploy.sh`. Its `sentry:test` creates the test issue before any webhook exists.
4. In Bugsink, set the project webhook to `http://<app container name>/error-tracking/alerts/<secret>` over `errors-treasurer`.
5. In Bugsink, resolve the `sentry:test` issue, then rerun `scripts/artisan.sh sentry:test`. The regression fires the webhook and proves the ntfy path.

### Success Criteria:

#### Automated Verification:

- Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci` on the merged branch (after `npm run build`)

#### Manual Verification:

- Local rehearsal at 2027-01-01 00:30: `PastMonthPlanRefused`, `ImportWriteRefused` (year 2027) and a guest browser error each appear in the local Bugsink, and each new issue produced one ntfy message
- Production: `sentry:test` after deploy appears in Bugsink with the deployed short SHA as release. After resolving its issue, the rerun arrives on ntfy as "Domowy Skarbnik: nowy błąd" with a working link
- Production: `access-errors` is green in Healthchecks.io
- Production: a week later, `docker stats --no-stream` shows Bugsink within its limits and the app's memory unchanged

---

## Testing Strategy

### Unit Tests:

- Static production files: `php.ini`, `.env.prod.example`, `Dockerfile.prod`, `docker-compose.prod.yml` (`tests/Unit/ProductionErrorTrackingConfigTest.php`)
- Browser option builder: every privacy-relevant option has its pinned value (`resources/js/lib/error-tracking.test.ts`)

### Integration Tests:

- **Reporting policy:** 5xx outside maintenance, 403 warnings for the owner with a throttle, silence for 4xx, validation and maintenance.
- **Context and release:** on log entries and on events.
- **Report-then-refuse:** import rows and pairs, month plan items, and `InvariantViolation` on a type mismatch. Run on PostgreSQL.
- **Server events:** captured envelopes from HTTP, artisan and the queue carry no request data, cookies or frame vars.
- **Tunnel:** accepts, rejects, throttles, and works for guests.
- **Relay:** forwards only the allowed fields, refuses a wrong secret, and reports an ntfy failure.

### Manual Testing Steps:

1. Run the Phase 3 compose locally and point the worktree preview at it.
2. Trigger a server exception, an `ImportWriteRefused`, an owner 403 and a guest browser error. Inspect each issue in Bugsink for absent body, props and amounts.
3. Rehearse 2027-01-01 00:30 with `app:rehearsal-clock`.
4. After the production deploy, check `sentry:test` in Bugsink, resolve its issue and rerun it to check ntfy, and check `access-errors`.

## Performance Considerations

- **Server transport:** sentry-php sends synchronously at the end of a failing request. Timeouts of 1 s connect and 2 s total bound the extra latency when Bugsink hangs, and only failing requests pay it.
- **Browser:** `@sentry/vue` adds about 37.5 KB gzip to the bundle (errors only, no tracing or replay).
- **Bugsink:** capped at 512m plus 256m. Bugsink's page cache inflates `docker stats` up to the limit. That is reclaimable and not a leak (`error-tracking.md`, Unknown unknowns).

## Migration Notes

- No database migrations.
- **Rollback, app side:** empty `SENTRY_LARAVEL_DSN` and run `scripts/artisan.sh config:cache`. The SDK goes inert and logs continue.
- **Rollback, infra side:** the previous Bugsink tag and `up -d`. Tracker data is disposable.
- **Order of a full rollback:** remove the DSN first, then stop Bugsink. Otherwise every failing request waits up to the transport timeout.

## References

- Provider research and risk register: `context/foundation/error-tracking.md`
- Observability audit: `context/audits/observability/2026-11-19_import-operations-year-turn-backups.md` (IMP-2, MPL-4, OPS-3, PLB-1, PLB-2, PLB-4, PLB-5, PLB-6)
- Roadmap item: `context/foundation/roadmap.md` S-45
- Existing patterns:
  - `app/Support/PastMonthPlanRefused.php`
  - `app/Support/InvariantViolation.php`
  - `.ai/rules/errors.md`
  - `tests/Feature/ChangeMonthPlanTest.php:209-214`
  - `tests/Unit/ProductionPostgresClientVersionTest.php`
- Infra patterns:
  - `kostecki-dev-infra/stacks/backrest/docker-compose.yml`
  - `kostecki-dev-infra/docker-compose.yml:69-111`
  - `kostecki-dev-infra/scripts/uptime-check.sh:11-21`
  - `kostecki-dev-infra/scripts/notify-failure.sh`
- Parallel work and worktree databases: `context/foundation/agents/parallel-changes.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Reporting policy and request context

#### Automated

- [x] 1.1 Policy, context and static config tests pass: `php artisan test --compact tests/Feature/ExceptionReportingPolicyTest.php tests/Feature/RequestContextTest.php tests/Unit/ProductionErrorTrackingConfigTest.php`
- [x] 1.2 Full gate passes on PostgreSQL in the worktree (`composer ci:check` via the worktree command in AGENTS.md, database `testing_production_error_tracking`) plus `vendor/bin/sloppy ci`

#### Manual

- [ ] 1.3 In a worktree preview, `abort(500)` from a temporary tinker route lands in `storage/logs/laravel-<date>.log` and on the container's stderr, with `route`, `request_id`, `user_id` and `release` in the entry

### Phase 2: Report-then-refuse guards

#### Automated

- [ ] 2.1 Touched feature tests pass on PostgreSQL (worktree command, `testing_production_error_tracking`): `CsvImportTest`, `ApproveMonthPlanTest`, `ChangeMonthPlanTest`, `ExpenseTest`, `IncomeTest`
- [ ] 2.2 `php artisan boost:index-rules` leaves `.ai/rules/index.md` consistent
- [ ] 2.3 Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci`

#### Manual

- [ ] 2.4 In a worktree preview, an import row refused at write time shows the same message as before and logs an `ImportWriteRefused` entry with ids and no amount

### Phase 3: RAM gate and Bugsink stack (kostecki-dev-infra)

#### Automated

- [ ] 3.1 `docker compose -f stacks/bugsink/docker-compose.yml config` and `docker compose -f docker-compose.yml config` validate (run locally in `kostecki-dev-infra`)
- [ ] 3.2 `bash -n scripts/deploy-bugsink.sh scripts/uptime-check.sh` passes
- [ ] 3.3 PR to `master` is open with the stack, networks, script, Dependabot entry, uptime line and docs

#### Manual

- [ ] 3.4 Owner measured halina175, recorded it in `ARCHITECTURE.md`, and decided go against the threshold (or no-go → re-plan)
- [ ] 3.5 Owner merged the PR, ran the infra root `up -d` and `deploy-bugsink.sh`, and created DNS and the Access application
- [ ] 3.6 `https://errors.kostecki.dev/` answers 302 to Access, and after login Bugsink shows the `domowy-skarbnik` project and its DSN
- [ ] 3.7 `docker stats --no-stream` shows both Bugsink containers within their limits after 24 h idle

### Phase 4: Server SDK

#### Automated

- [ ] 4.1 `composer show sentry/sentry-laravel` reports 4.x ≥ 4.31
- [ ] 4.2 `php artisan test --compact tests/Feature/ErrorTrackingServerTest.php tests/Unit/ProductionErrorTrackingConfigTest.php` passes
- [ ] 4.3 Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci`

#### Manual

- [ ] 4.4 Against a local Bugsink, a thrown exception in the worktree preview shows up with stack trace, tags and release, and no request body

### Phase 5: Browser SDK and tunnel

#### Automated

- [ ] 5.1 `npm test` passes, including `resources/js/lib/error-tracking.test.ts`
- [ ] 5.2 `php artisan test --compact tests/Feature/ErrorTunnelTest.php` passes
- [ ] 5.3 `npm run build` then `npm run types:check` pass
- [ ] 5.4 Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci`

#### Manual

- [ ] 5.5 Against the local Bugsink, a Vue render error, an unhandled rejection and a guest 2FA failure each appear with release and component name, and without props, cookies or form values
- [ ] 5.6 The browser network tab shows envelopes going only to `/error-tracking/tunnel`

### Phase 6: Alert relay to ntfy

#### Automated

- [ ] 6.1 `php artisan test --compact tests/Feature/ErrorAlertRelayTest.php` passes
- [ ] 6.2 Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci`

#### Manual

- [ ] 6.3 Against the local Bugsink, a new issue produces exactly one ntfy notification with fixed title, class and link, and a repeat event produces none

### Phase 7: Rehearsal and production rollout

#### Automated

- [ ] 7.1 Full gate passes on PostgreSQL plus `vendor/bin/sloppy ci` on the merged branch (after `npm run build`)

#### Manual

- [ ] 7.2 Local rehearsal at 2027-01-01 00:30: `PastMonthPlanRefused`, `ImportWriteRefused` (year 2027) and a guest browser error each appear in Bugsink with one ntfy message per new issue
- [ ] 7.3 Production: `sentry:test` after deploy appears in Bugsink with the deployed short SHA as release; after resolving its issue, the rerun arrives on ntfy with a working link
- [ ] 7.4 Production: `access-errors` is green in Healthchecks.io
- [ ] 7.5 Production: a week later, `docker stats --no-stream` shows Bugsink within its limits and the app's memory unchanged
