# MCP server for the budget (read, then cash entry) Implementation Plan

## Overview

Give the user a Claude-facing MCP server for Domowy Skarbnik: first a local stdio server for Claude Code on local data, then a public `/mcp` endpoint with OAuth through Passport so claude.ai, Claude Desktop and the Claude mobile app can read the user's production data, and finally one write tool that records a cash operation. Read tools reuse the existing `app/Support` readers; the little orchestration that lives in controllers moves to `app/Support` first. The change is slice S-47 of M-5; everything that reaches production lands before ~15 December 2026.

## Current State Analysis

- `laravel/mcp` v1.1.2 is only a transitive dev dependency of Boost (`composer.lock:8650,9455,9503`); production installs with `--no-dev` (`docker/8.5/Dockerfile.prod:6,9`). No `routes/ai.php`, no `app/Mcp`, no Sanctum or Passport; the only guard is `web` (`config/auth.php:40-45`).
- The readers exist and read no session: `SpendableLiquidity::forUser` (`app/Support/SpendableLiquidity.php:13`), `TotalFunds::forUser` (`app/Support/TotalFunds.php:13`), `MonthToSpend::forMonth` (`app/Support/MonthToSpend.php:36`), `MonthPlanItemRow::forLine` (`app/Support/MonthPlanItemRow.php:45`), `BudgetPlanSummary::forLines/groupsOfType` (`app/Support/BudgetPlanSummary.php:95,142`), `OperationsListQuery` (`app/Support/OperationsListQuery.php:15,35,127`) with static rules `IndexOperationsRequest::listRules` (`app/Http/Requests/IndexOperationsRequest.php:31`).
- Orchestration that a tool would otherwise copy: the current-year Budget lookup (private `findBudgetForYear`, `DashboardController.php:178-185`, copied at `BudgetController.php:182-189`), the To Spend chain (`DashboardController.php:95-123`), month timing (`MonthPlanController.php:320-330`), the operations query, ordering and row shaping (`OperationsController.php:29,43-65`).
- Month Category Group sums exist only in TypeScript (`resources/js/lib/month-plan-groups.ts:35`); ADR-0079 and `.ai/rules/js-month-plan.md:25` keep the server out of them.
- Hand-entry validation is HTTP-bound: rules read `$this->user()` (`app/Concerns/ExpenseValidationRules.php:19`), `OperationEntryValidation::attach` takes a `Request` (`app/Support/OperationEntryValidation.php:19`). The precedent for rules shared with non-HTTP code is `IndexOperationsRequest::listRules` used through `Validator::make` (`app/Concerns/RedirectsToOperationsList.php:34`).
- Login, 2FA and passkey run as Inertia XHR and end in `redirect()->intended(...)` (`vendor/laravel/fortify/src/Http/Responses/LoginResponse.php:20`, `TwoFactorLoginResponse.php:21`) or `router.visit(response.redirect)` (`resources/js/components/PasskeyVerify.vue:33`).
- Production: Traefik router is host-only (`docker-compose.prod.yml:36-43`), container 1 GB / 1 CPU (`:17-19`), PHP-FPM 4 workers at 256M (`docker/8.5/php-fpm.conf:17-31`), `LOG_LEVEL=error` (`.env.prod.example:20`), `deploy.sh` runs artisan as root and caches routes (`scripts/deploy.sh:75-86`).

## Desired End State

- In Claude Code on the user's machine, `claude mcp add treasurer --scope local -- docker compose -f <main checkout>/compose.yaml exec -T -u sail laravel.test php artisan mcp:start treasurer` gives read tools that answer from the local database (`-T` keeps stdio clean; host `php` cannot resolve `DB_HOST=pgsql`).
- On production, `https://budget.kostecki.dev/mcp` accepts Streamable HTTP requests with a Passport OAuth token; adding it as a custom connector on claude.ai, signing in with the existing login (incl. 2FA or passkey) and approving on a Polish Atrament consent page makes the same tools available on the web, Desktop and the phone.
- With `MCP_WRITE_ENABLED=true` in `.env.prod`, Claude can record a cash expense, refund or income after the user approves the call; a retried call with the same `request_id` returns the first operation and writes nothing.
- Every answer states the App Day it used; no tool reads the session or another user's data.

Verification: per-phase Success Criteria below, plus the manual phone check in Phase 4 and the cash entry check in Phase 6.

### Key Discoveries:

- `routes/ai.php` is loaded only by `McpServiceProvider::registerRoutes` with `Route::group([], $path)`, outside the `web` group (`vendor/laravel/mcp/src/Server/McpServiceProvider.php:86-98`): no session, no CSRF on `/mcp`.
- laravel/mcp 1.2 adds Origin validation (`config('app.url')` or `config('mcp.allowed_origins')`, missing Origin and loopback origins pass). `config/mcp.php` (publishable since 1.1, tag `mcp-config`) ships `redirect_domains` default `['*']` and must be published and narrowed; `allowed_origins` is not in the published file (read with default `[]`) and is added by hand.
- Over stdio there is no middleware: the only hook before tool calls is the Server's `boot()` (laravel/mcp `Server.php:188-199`), which also runs on every HTTP request of `Mcp::web`.
- `Mcp::oauthRoutes()` registers discovery endpoints and an open `POST oauth/register` (DCR, scope `mcp:use`, `vendor/laravel/mcp/src/Server/Registrar.php:31,124-156`); `passport:purge` never deletes clients.
- Passport keys belong in env (`PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY`): a key file generated by `deploy.sh` would be root-owned and unreadable by FPM, and `storage/` keys are outside the backup set.
- The approve/deny endpoints answer with a 302 to an external `redirect_uri`, so the consent page posts a native `<form>` with a CSRF token, not an Inertia visit.
- `tests/Unit/AppDayClockGuardTest.php:47` scans all of `app/`, so `app/Mcp` is guarded; the rule is `.ai/rules/app-day.md`.
- The user snapshot lists `financial_operations` columns explicitly (`app/Support/UserSnapshot/LedgerRows.php:44-62`, `SNAPSHOT_VERSION = 13`); a separate key table keeps the snapshot unchanged, and `ResetUserData` deletes operations by query (`app/Actions/ResetUserData.php:14`), so a cascading foreign key removes their keys.
- `MinorUnits::toMajorDisplay` (`app/Support/MinorUnits.php:24`) formats amounts as `1234,56`.

## What We're NOT Doing

- No Sanctum and no static-token path; no custom OAuth scopes (one scope `mcp:use`).
- No write tools other than the cash operation (no plan changes, no import, no edit or delete of operations, no own transfers such as an ATM withdrawal).
- No port of the TypeScript entry warning (`resources/js/lib/entry-to-spend-warning.ts`): the write answer warns only about the operation's month, so later months of an Irregular period whose Collected moves are not warned about.
- No fallback to an earlier year when the App Day year has no Budget in execution (no ADR-0075 default year in tools).
- No MCP resources or prompts, no streaming responses, no change to nginx.
- No change to the import validator (`app/Support/CsvImport/FinancialOperationImportValidator.php`), even though it duplicates the hand-entry rules.
- No reports tools (Trend, Years, last 12 months); S-43 owns the report code.
- No edits to `SelectedBudgetYear`, `ShellState`, `DashboardNotices` or `ReportController` (S-40, S-41, S-43).
- No error-tracking provider (S-45 stays blocked).

## Implementation Approach

Refactor first, then add. Phase 1 moves the shared orchestration into `app/Support` with no behaviour change, guarded by the existing controller tests, and records the decisions (ADR-0110, tech stack, roadmap, rule file). Phase 2 builds the server and read tools against those readers and proves them locally over stdio. Phase 3 adds OAuth without touching the tools (they only read `$request->user()`). Phase 4 is the production rollout of the read endpoint. Phase 5 makes hand-entry validation callable without HTTP, again with no behaviour change. Phase 6 adds the write tool on top of Phase 5 and deploys it behind a server switch.

All tools share one response shape: `app_day` (`YYYY-MM-DD`), the year and Budget phase they used where relevant, amounts as `*_minor_units` integers plus a display string from `MinorUnits::toMajorDisplay` with " zł", and only ids and fields that come from the user's own relations.

## Critical Implementation Details

- **Ordering against S-40 and S-41.** Phase 1 edits `DashboardController::show` (:95-123) and `BudgetController`, which S-41 is likely to touch. Before starting Phase 1, run `git log --oneline worktree-mcp-read-only-server..development` and merge `development` into this branch (the branch is not on origin yet, so `git rebase development` is allowed while it stays local, per `context/foundation/agents/parallel-changes.md:25`). After Phase 1, merge this branch into `development` early so S-41 builds on the extracted readers.
- **Deploy window.** Phases 4 and 6 deploy before ~15 December 2026, in the same window as S-40, S-41 and S-43. Do not deploy Phase 6 in the same release as another slice that changes `ManageFinancialOperation` or the expense/income requests.
- **Inertia login and external redirects.** When the intended URL is under `/oauth/authorize`, the login, 2FA and passkey responses must leave the XHR chain with a full-page navigation (`Inertia::location` on the server, `window.location.assign` in `PasskeyVerify.vue`); otherwise a consent skip that redirects straight to claude.ai or a loopback URL fails on CORS.
- **Unique-key race in the write tool.** `ManageFinancialOperation::create()` commits in its own `DB::transaction` (`app/Actions/ManageFinancialOperation.php:137`), so the operation and its receipt must be written inside one outer `DB::transaction` in `RecordCashOperation` (the inner one becomes a savepoint). A concurrent second call with the same `request_id` hits the unique index, and the outer transaction rolls back its operation and balance change. Per `.ai/rules/errors.md` catch only `UniqueConstraintViolationException`, **outside** the outer transaction (on PostgreSQL the failed statement aborts it), re-select the existing receipt to prove the expected case and return it as a replay, and `report()` otherwise.
- **Debug & observability.** Remote calls are logged by route middleware to a dedicated `mcp` channel at `info` (user id, JSON-RPC method, tool name, HTTP status, duration) — never arguments, amounts or descriptions; local stdio calls are not logged.

## Phase 1: Foundations and shared readers

### Overview

Record the decisions, add the dependency, and move the orchestration that tools need from controllers into `app/Support`, with controllers calling the new readers and every page prop unchanged.

### Changes Required:

#### 1. ADR-0110

**File**: `context/foundation/adr/0110-mcp-server-with-oauth-through-passport.md`

**Intent**: Record the MCP server: laravel/mcp, local stdio variant, `/mcp` with Passport OAuth (DCR, one scope), Atrament consent page, read tools on `app/Support` readers, the server-side month group sums for MCP (amends ADR-0079), one cash write tool with a key table, forced approval and the `MCP_WRITE_ENABLED` switch, and the App Day in every answer.

**Contract**: Supersedes the sentence "Osobne REST/JSON API nie powstaje w MVP" (`context/foundation/tech-stack.md:35`) and the "API | brak" row (`:87`). Rejected options with one-line reasons: Sanctum token (static headers beta for limited organizations), custom read/write scopes, Blade consent view, key column on `financial_operations` (snapshot 13 → 14), port of the TS entry warning, year fallback (ADR-0075) in tools. ADR-0079 gets a line "Amended by ADR-0110". `tests/Unit/AdrNumberingTest.php` must stay green.

#### 2. Tech stack, roadmap, rule file

**File**: `context/foundation/tech-stack.md`, `context/foundation/roadmap.md`, `.ai/rules/mcp.md`, `.ai/rules/index.md`

**Intent**: Add laravel/mcp and Passport to the stack with ADR-0110; add S-47 to M-5; give `app/Mcp` its own rules.

**Contract**:
- tech-stack: §1 stack list, §2 and the §7 table row "API" point at ADR-0110; §8 drops "osobne REST API" from the deferred list.
- roadmap: S-47 row in `## At a glance` (`S-47 | mcp-read-only-server | użytkownik pyta Claude'a o stan budżetu i dopisuje gotówkę z telefonu | F-08 | — | planning`), a `### S-47` section in the existing slice format (Outcome, Change ID, Prerequisites, Parallel with: S-40/S-41 after Phase 1, Risk), a row in `## Streams`, and the parked line `roadmap.md:248` annotated "wyjątek: S-47 (decyzja użytkownika 2026-11-19)". Frontmatter `updated` if present.
- `.ai/rules/mcp.md` with `paths:` `app/Mcp/**/*.php`, `routes/ai.php`, `tests/Feature/Mcp/**`. Rules: every query starts at a `$user->…` relation and ids from input are checked against them; no session and no `SelectedBudgetYear`; "today" from `AppDay` and `app_day` in every answer; amounts from `app/Support` readers, never recomputed in a tool; read tools `#[IsReadOnly]`; results bounded (operations ≤ 50); the write tool stays behind `MCP_WRITE_ENABLED` and `requiresUserInteraction`. Regenerate the index with `php artisan boost:index-rules`.

#### 3. Dependency

**File**: `composer.json`, `composer.lock`

**Intent**: Make laravel/mcp a production dependency at 1.2.x (Origin validation, JSON-RPC errors).

**Contract**: `"laravel/mcp": "^1.2"` in `require`; Boost still resolves (`^1.0` allowed).

#### 4. Shared readers

**File**: `app/Support/BudgetForYear.php`, `app/Support/CurrentMonthReading.php`, `app/Support/MonthTiming.php`, `app/Support/FinancialOperationRows.php`

**Intent**: One implementation of the pieces that both a page and a tool need.

**Contract**:
- `BudgetForYear::find(User $user, int $year): ?Budget` — `$user->budgets()->where('year', $year)->first()`; replaces the private copies in `DashboardController` and `BudgetController` (inlined lookups elsewhere stay).
- `CurrentMonthReading::forUser(User $user, CarbonImmutable $today): ?self` — Budget of `$today->year` in execution, `$today->month`, the `MonthToSpend` reading and its `summary()`; null without a Budget or with a draft. The Dashboard uses it for the To Spend chain; Month Pace, Collected coverage and notices stay in the controller, built from the reading.
- `MonthTiming::of(int $year, int $month, CarbonImmutable $today): 'past'|'current'|'future'` — the body of `MonthPlanController::monthTiming`. `MonthRemainingCalculator::monthTiming` is not touched (parity rules, `.ai/rules/month-calculators.md`).
- `FinancialOperationRows::query(User $user, OperationsListQuery $filters): Builder` (user relation, eager loads, `operation_date desc, id desc`) and `FinancialOperationRows::row(FinancialOperation $operation): array` (domain fields only); `OperationsController` paginates and adds icon, colour and account identity fields.

#### 5. Controllers

**File**: `app/Http/Controllers/DashboardController.php`, `BudgetController.php`, `MonthPlanController.php`, `OperationsController.php`, `.sloppy-baseline.json`

**Intent**: Call the readers; props stay byte-identical, so no `defineProps` change.

**Contract**: Existing `assertInertia` tests unchanged and green. If a baselined Sloppy finding of `DashboardController::show` or `OperationsController::index` disappears, run `vendor/bin/sloppy baseline` to drop it; never to hide a new one.

### Success Criteria:

#### Automated Verification:

- Unit tests for `BudgetForYear`, `CurrentMonthReading` (no Budget, draft, execution, App Day boundary 22:59:59 / 23:00:00 UTC on 31 December) and `MonthTiming` pass
- Dashboard, Budget, Month Plan and Operations feature tests pass unchanged: `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/YearTurnTest.php tests/Feature/MonthPlanPageTest.php tests/Feature/BudgetTest.php tests/Feature/OperationsFilterTest.php tests/Feature/OperationsPaginationTest.php tests/Feature/OperationsRowRecipeTest.php`
- `tests/Unit/AdrNumberingTest.php` passes with ADR-0110
- `php artisan boost:index-rules` lists `.ai/rules/mcp.md`
- Full gate on PostgreSQL in the worktree (`--entrypoint composer sail-8.5/app ci:check` with `DB_DATABASE=testing_mcp_read_only_server`) and `vendor/bin/sloppy ci` pass

#### Manual Verification:

- Dashboard, Budget, Month Plan and Operations look and behave as before in the worktree preview

**Implementation Note**: After automated verification passes, pause for manual confirmation, then merge the branch into `development` so S-40/S-41 build on the readers.

---

## Phase 2: Local read server

### Overview

A stdio MCP server with the read tools, the month group sums in PHP, and tests per tool; usable from Claude Code against the local database.

### Changes Required:

#### 1. Server and local user

**File**: `routes/ai.php`, `app/Mcp/Servers/TreasurerServer.php`, `app/Mcp/Servers/LocalTreasurerServer.php`, `config/services.php`, `.env.example`

**Intent**: Register `Mcp::local('treasurer', LocalTreasurerServer::class)`; `TreasurerServer` lists the read tools and tells Claude to answer in Polish, give amounts in zł and compute relative dates from `app_day`. `LocalTreasurerServer extends TreasurerServer` and only adds the local user.

**Contract**: `LocalTreasurerServer::boot()` sets the user named by `config('services.mcp.local_user')` (env `MCP_LOCAL_USER`, e-mail or id) with `Auth::guard('web')->setUser(...)` (not `Auth::login`, which writes the session), only when `app()->isLocal()`; otherwise, or when the user does not exist, it refuses with a clear error. `TreasurerServer` has no local-user code, and only it is registered with `Mcp::web` (Phase 3), so HTTP requests can never reach the local login. No other way to pick a user exists on stdio.

#### 2. Read tools

**File**: `app/Mcp/Tools/AccountBalancesTool.php`, `MonthToSpendTool.php`, `MonthPlanTool.php`, `YearPlanTool.php`, `RecentOperationsTool.php`, `ListCategoriesTool.php`

**Intent**: Answer the four read questions from the change with the shared readers.

**Contract** (all `#[IsReadOnly]`, `#[IsIdempotent]`, shared response shape from Implementation Approach):
- `AccountBalancesTool` — active non-Sheet accounts (id, name, type, balance, negative flag), term deposits, `SpendableLiquidity::forUser`, `TotalFunds::forUser`, and the sum held on archived accounts so the list and the totals reconcile (ADR-0085).
- `MonthToSpendTool` — `CurrentMonthReading::forUser($user, AppDay::today())`: state (proposal/approved), To Spend and its income/expense breakdown; without a Budget in execution: the year, its phase or absence, and no To Spend.
- `MonthPlanTool` — args `year?`, `month?` (default App Day): per Category plan, actual, deviation, over-plan flag, Rhythm, group id; Category Group rows from `MonthPlanGroupSums`; type totals. Refuses a year without a Budget in execution with the year and phase. Rows come from `MonthToSpend::forMonth` and `MonthPlanItemRow::forLine` with `MonthTiming::of`, without icon, colour and join-candidate fields.
- `YearPlanTool` — arg `year?` (default App Day year): per Budget Line initial plan, Current Plan, execution, remaining, percent complete, over-limit (`CategoryExecutionMetrics`), groups and type totals from `BudgetPlanSummary::groupsOfType`/`forLines`; works for draft Budgets too (plan only) and says the phase.
- `RecentOperationsTool` — args `category_id?`, `account_id?`, `date_from?`, `date_to?`, `q?`, `limit?` (default 20, max 50), validated with `IndexOperationsRequest::listRules($user->id)`; rows from `FinancialOperationRows`. A foreign id is a validation error naming the field.
- `ListCategoriesTool` — arg `type?`: the user's Categories (id, name, type, group, archived flag) from `OperationsFilterOptions::categories`, so Claude maps names to ids.

#### 3. Month Category Group sums in PHP

**File**: `app/Support/MonthPlanGroupSums.php`, `tests/Fixtures/month-plan-groups/*.json`, `tests/Unit/MonthPlanGroupSumsParityTest.php`, `resources/js/lib/month-plan-groups.parity.test.ts`, `.ai/rules/js-month-plan.md`, ADR-0079

**Intent**: The read-mode group sums of ADR-0079 on the server for MCP answers, proven equal to `monthPlanGroupTree` on shared fixtures.

**Contract**: For each Category Group with a shown row: count, literal sum of Plan and of Actual over shown rows, "over plan" when any shown row is over plan by its own Rhythm; archived rows and idle Even Annual rows are in no group; no Deviation sum. Fixtures include the ADR-0079 example (Mieszkanie: Czynsz 2 500 / 2 500, Prąd rate 150, bill 600 → Plan 2 650, Actual 3 100, over plan). `js-month-plan.md:25` becomes "the page never takes group sums from the server; `App\Support\MonthPlanGroupSums` mirrors the read mode for MCP, guarded by the parity test".

#### 4. Tests

**File**: `tests/Feature/Mcp/*Test.php`

**Intent**: One test file per tool with `TreasurerServer::actingAs($user)->tool(...)`.

**Contract**: Per tool: happy path with hand-counted minor-units literals; a second user's Category, Budget, account and operation never appear (call with and without the foreign id, `assertDontSee` of the foreign name); App Day at 22:59:59 and 23:00:00 UTC on 31 December (`travelTo`); no Budget and draft Budget for the App Day year. Server: no user → error; `tools()->assertRegistered` for the six tools on both `TreasurerServer` and `LocalTreasurerServer`. Local user: refused outside `local`; `TreasurerServer` sets no user from `MCP_LOCAL_USER` even in `local`.

#### 5. README

**File**: `README.md`

**Intent**: How to start the local server in Claude Code (and Desktop) through Sail (`docker compose … exec -T -u sail laravel.test php artisan mcp:start treasurer`, as in Desired End State), with `MCP_LOCAL_USER`; the host-PHP alternative needs `DB_HOST=127.0.0.1 DB_PORT=5434` (`FORWARD_DB_PORT`) and PHP 8.4.1; optionally on a restored production snapshot (`user:restore`).

**Contract**: A subsection under the existing commands section, in Polish like the rest of the README.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/Mcp tests/Unit/MonthPlanGroupSumsParityTest.php` passes
- `npm run test -- resources/js/lib/month-plan-groups.parity.test.ts` passes (Vitest via `vp`, per `.ai/rules/frontend-toolchain.md`)
- Full gate on PostgreSQL in the worktree and `vendor/bin/sloppy ci` pass

#### Manual Verification:

- In Claude Code with the local server added, the questions "ile mam do wydania w tym miesiącu?", "jak wygląda plan kontra wykonanie dla grupy Mieszkanie w tym miesiącu?", "jakie są salda kont?" and "pokaż ostatnie 10 operacji z kategorii Jedzenie w listopadzie" get answers that match the Dashboard, the Month Plan, the Budget page and the Operations list for the same data
- With `app:rehearsal-clock '2026-12-31 23:30'` the tools report `app_day` 2027-01-01 and say the 2027 Budget state

**Implementation Note**: Pause for the manual check in Claude Code before Phase 3.

---

## Phase 3: OAuth through Passport

### Overview

The `/mcp` route with Passport OAuth, discovery and client registration, the consent page, the login fixes, limits, call logging and cleanup — all tested locally.

### Changes Required:

#### 1. Passport

**File**: `composer.json`, `composer.lock`, `database/migrations/*oauth*`, `config/auth.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `config/passport.php` (if published)

**Intent**: Passport 13 as the authorization server for `mcp:use`.

**Contract**: `laravel/passport:^13`; published Passport migrations (auth codes, access tokens, refresh tokens, clients, device codes) with working `down()`; guard `'api' => ['driver' => 'passport', 'provider' => 'users']`; `User implements PasskeyUser, OAuthenticatable` with `Laravel\Passport\HasApiTokens`; keys from `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY`; access tokens expire after 1 hour, refresh tokens after 30 days.

#### 2. Routes and MCP config

**File**: `routes/ai.php`, `config/mcp.php`

**Intent**: Public endpoint and OAuth discovery, restricted to Claude's redirect targets.

**Contract**: `Route::middleware('throttle:10,1')->group(fn () => Mcp::oauthRoutes())` (the documented variant: 10 requests per minute per IP on DCR and discovery); `Mcp::web('/mcp', TreasurerServer::class)->middleware(['auth:api', 'throttle:mcp', LogMcpCall::class])`; published `config/mcp.php` with `redirect_domains` = `https://claude.ai`, `https://claude.com`, `http://localhost` (a loopback entry accepts any port on localhost, 127.0.0.1 and [::1]), empty `custom_schemes`, and `'allowed_origins' => []` added by hand (`APP_URL` covers the app's own origin). `npm run build` regenerates Wayfinder for the new vendor routes; `npm run types:check` passes.

#### 3. Consent page

**File**: `resources/js/pages/auth/OAuthAuthorize.vue`, `app/Providers/AppServiceProvider.php` (`Passport::authorizationView`), `app/Http/Controllers/Dev/AuthKitchenSinkController.php` and its page, `scripts/check-ui-literals.mjs`

**Intent**: A Polish Atrament page in `AuthLayout`: client name, what access means (reading balances, plan and operations; recording cash operations when enabled), "Odmów" and "Zezwól".

**Contract**: Props `client_name`, `scopes` (list of descriptions), `auth_token`, `client_id`, `state`, `csrf_token`, declared on both sides with an `assertInertia` test. Approve is a native `<form method="post">` to the Passport approve route, deny the same with `_method=DELETE`; no Inertia visit. Every state (default, long client name, narrow screen) in `/dev/ui/auth`; tokens only (`.ai/rules/js.md`, `.ai/rules/js-auth.md`, `context/foundation/design-system.md`); the page is on the `check:ui` list.

#### 4. Login, 2FA and passkey responses

**File**: `app/Http/Responses/LoginResponse.php`, `app/Http/Responses/TwoFactorLoginResponse.php`, `app/Providers/FortifyServiceProvider.php`, `resources/js/components/PasskeyVerify.vue`

**Intent**: After signing in on the way to `/oauth/authorize`, leave the Inertia XHR chain with a full-page navigation.

**Contract**: `app/Http/Responses/` does not exist yet: `LoginResponse` and `TwoFactorLoginResponse` are new classes implementing Fortify's `LoginResponse`/`TwoFactorLoginResponse` contracts, bound in `FortifyServiceProvider::register`. When the intended URL's path starts with `/oauth/authorize` and the request is an Inertia request, respond `Inertia::location($intended)`; otherwise keep Fortify's behaviour. `PasskeyVerify.vue` uses `window.location.assign` for a redirect under `/oauth/authorize` and `router.visit` otherwise.

#### 5. Limits, logging, cleanup

**File**: `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/LogMcpCall.php`, `config/logging.php`, `bootstrap/app.php`, `app/Console/Commands/PruneOAuthClientsCommand.php`

**Intent**: Bound abuse and growth; see every remote call.

**Contract**: `RateLimiter::for('mcp')` 60 per minute per user id; channel `mcp` (daily, level `info`, 14 days) independent of `LOG_LEVEL`; `LogMcpCall` writes user id, JSON-RPC method, tool name (`params.name` for `tools/call`), status and duration only; schedule `passport:purge` hourly and `app:prune-oauth-clients` daily (deletes clients older than 7 days with no live access or refresh token).

#### 6. Tests

**File**: `tests/Feature/Mcp/OAuthTest.php`, `tests/Feature/Mcp/HttpEndpointTest.php`, `tests/Feature/Auth/OAuthLoginRedirectTest.php`

**Contract**: discovery endpoints return JSON with the https resource; DCR accepts a `https://claude.ai/api/mcp/auth_callback` and a loopback redirect and refuses another domain; `POST /mcp` (`postJson`) without a token is 401 with `WWW-Authenticate` containing `resource_metadata`; with `Passport::actingAs($user, ['mcp:use'])` `tools/list` and one tool call succeed; another user's token sees only its own data; the 61st call in a minute is 429; consent page `assertInertia`; login, 2FA and passkey with intended `/oauth/authorize` return a location response for Inertia requests; in the `local` env with `MCP_LOCAL_USER` set, `POST /mcp` without a token is still 401 and a token of another user sees only that user's data; `LogMcpCall` writes no arguments; prune command keeps clients with live tokens. Passport migrations run on PostgreSQL.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/Mcp tests/Feature/Auth` passes
- `npm run build`, `npm run types:check` and `npm run check:ui` pass
- Full gate on PostgreSQL in the worktree and `vendor/bin/sloppy ci` pass

#### Manual Verification:

- In the worktree preview, Claude Code `claude mcp add --transport http treasurer-dev <preview-url>/mcp` opens the browser, the login (password + 2FA, and a passkey) leads to the consent page, "Zezwól" returns to Claude Code and a tool answers
- The consent page in `/dev/ui/auth` matches the login screen in light and dark themes and at phone width

**Implementation Note**: Pause for the manual OAuth check before deploying in Phase 4.

---

## Phase 4: Read endpoint on production

### Overview

Configure, deploy and connect the read endpoint before ~15 December 2026.

### Changes Required:

#### 1. Production configuration

**File**: `.env.prod.example`, `context/foundation/infrastructure.md`

**Intent**: Document and set what production needs.

**Contract**: `.env.prod.example` lists `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY` (multi-line keys as `\n`-escaped values) and keeps `APP_URL=https://budget.kostecki.dev`. `infrastructure.md` gets an "MCP endpoint" section: paths (`/mcp`, `/oauth/*`, `/.well-known/oauth-*`), Anthropic egress `160.79.104.0/21`, Cloudflare rules, the `mcp` log file, key rotation (new keys = every client signs in again).

#### 2. Cloudflare (manual, user)

**Intent**: Let Claude's servers reach the endpoint.

**Contract**: For `budget.kostecki.dev`: a WAF skip rule for `/mcp`, `/oauth/*` and `/.well-known/oauth-*` (managed rules and Super Bot Fight where the plan allows it; Bot Fight Mode on the free plan cannot be skipped by path and must be off), and a cache bypass for the same paths.

#### 3. Release

**Intent**: Ship through the normal path.

**Contract**: Merge into `development`, release PR `development` → `main` with Sloppy PR mode (`sloppy diff origin/main --merge-base`), on the server `git pull --ff-only origin main`, keys into `.env.prod`, then `scripts/deploy.sh` (snapshots, Passport migrations, route cache). Rollback: `scripts/artisan.sh migrate:rollback --force` for the Passport tables before `scripts/rollback.sh` (README "Wdrożenie i przywracanie").

### Success Criteria:

#### Automated Verification:

- `curl -s https://budget.kostecki.dev/.well-known/oauth-protected-resource/mcp` returns JSON with `"resource":"https://budget.kostecki.dev/mcp"`
- `curl -s -o /dev/null -w '%{http_code}' -X POST https://budget.kostecki.dev/mcp -H 'Content-Type: application/json' -d '{}'` returns 401

#### Manual Verification:

- Cloudflare rules are in place and the panel shows no challenge for a request from Claude
- On claude.ai, Customize > Connectors > add custom connector `https://budget.kostecki.dev/mcp`, sign in, approve; a question about To Spend answers with production numbers equal to the Dashboard
- The same connector works on Claude Desktop and in the Claude mobile app
- `storage/logs/mcp-*.log` on the server has one line per call, without amounts or descriptions

**Implementation Note**: Pause after the phone check; Phase 5 starts only after the read endpoint works on production.

---

## Phase 5: Hand-entry validation without HTTP

### Overview

The same expense and income validation callable from a FormRequest and from non-HTTP code, with no behaviour change for the dialogs.

### Changes Required:

#### 1. Static rules

**File**: `app/Concerns/ExpenseValidationRules.php`, `app/Concerns/IncomeValidationRules.php`

**Intent**: Rule bodies that take a user id instead of reading the request.

**Contract**: `ExpenseValidationRules::expenseStoreRulesFor(?int $userId): array` and `IncomeValidationRules::incomeStoreRulesFor(?int $userId): array`; the existing protected `storeExpenseRules()`/`storeIncomeRules()` delegate with `$this->user()?->id`. A static `payloadFrom(array $validated): array` per type replaces the request-only payload building. As built (impl review F4): the static rules and payload builders live in `App\Support\ExpenseEntryRules` / `IncomeEntryRules`; the traits delegate.

#### 2. Value-based entry validation

**File**: `app/Support/OperationEntryValidation.php`

**Intent**: The after-hook (opening date, `OperationCategoryChoice::check`) over plain values.

**Contract**: `attachValues(Validator $validator, User $user, CategoryType $type, array $input, ?FinancialOperation $editing): void`; `attach(...)` delegates with `$request->all()` and `$request->route('financial_operation')`. Messages and attribute names unchanged.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/ExpenseTest.php tests/Feature/IncomeTest.php tests/Feature/FinancialOperationEditDeleteTest.php` passes unchanged on SQLite and on PostgreSQL in the worktree
- A unit test calls `Validator::make` with `expenseStoreRulesFor` plus `attachValues` and gets the same errors as the HTTP path for: future date, date before account opening, foreign account, foreign Category, income Category for an expense, draft Budget, archived Category
- Full gate on PostgreSQL in the worktree and `vendor/bin/sloppy ci` pass

#### Manual Verification:

- Adding, editing and converting an expense and an income in the operation dialogs behaves as before in the worktree preview

**Implementation Note**: Pause for the dialog check before Phase 6.

---

## Phase 6: Cash operation write tool

### Overview

One write tool for cash expenses, refunds and income, idempotent by `request_id`, behind a server switch and forced approval, deployed before ~15 December 2026.

### Changes Required:

#### 1. Key table

**File**: `database/migrations/*_create_mcp_operation_requests_table.php`, `app/Models/McpOperationRequest.php`

**Intent**: Remember which `request_id` created which operation.

**Contract**: Columns `id`, `user_id` (FK users, cascade), `request_id` (string 64), `financial_operation_id` (FK financial_operations, cascade), `created_at`; unique `(user_id, request_id)`; working `down()` (`.ai/rules/migrations.md`). The user snapshot is unchanged; a restore drops keys, which only re-enables a replay window that has passed.

#### 2. Action

**File**: `app/Actions/RecordCashOperation.php`

**Intent**: Validate, write and read back one cash operation.

**Contract**: `handle(User $user, array $input): CashOperationResult`. Input: `kind` (`expense|refund|income`), `amount` (text such as "23,50", parsed by `MinorUnits::fromMajorInput`, must be positive; the sign comes from `kind`), `category_id`, `account_id?`, `operation_date?` (default `AppDay::today()`), `description`, `counterparty?`, `request_id` (8–64 characters). Account: the user's active non-Sheet `Cash` accounts; none → validation error telling the user to create one; two or more without `account_id` → validation error listing them; a non-cash `account_id` → validation error. Flow, in this order: (1) an existing receipt for `(user, request_id)` returns its operation with `replayed: true` before any validation, so a retry after midnight or after the Category was archived still replays; (2) validation: Phase 5 rules and `attachValues` for the kind's Category type; (3) read `MonthToSpend` for the operation's month (when its Budget is in execution); (4) in one outer `DB::transaction`: `ManageFinancialOperation::createExpense`/`createIncome` and the receipt insert; (5) on `UniqueConstraintViolationException` caught outside that transaction, re-select the receipt and return its operation with `replayed: true` (no receipt found → `report()` and rethrow); (6) read again. Result: the operation (id, date, kind, signed amount, Category, account), account balance before/after, To Spend before/after with `to_spend_warning` = `after < 0 && after < before`, and for expenses and refunds the Available Limit (`CategoryExecutionMetrics::remainingMinorUnits`).

#### 3. Tool

**File**: `app/Mcp/Tools/AddCashOperationTool.php`, `app/Mcp/Servers/TreasurerServer.php`, `app/Mcp/Tools/ListCategoriesTool.php`, `config/services.php`, `.env.example`, `.env.prod.example`

**Intent**: Expose the Action to Claude only when the server switch is on, always with approval.

**Contract**: `#[IsReadOnly(false)]`, `#[IsDestructive(false)]`, `#[IsIdempotent]`, `protected ?array $meta = ['anthropic/requiresUserInteraction' => true]` (the property, not an overridden `meta()`, which `mergeMeta` ignores); `shouldRegister` returns `config('services.mcp.write_enabled')` (env `MCP_WRITE_ENABLED`, default false). The description tells Claude to generate one `request_id` per entry and reuse it on retry, and to ask when the Category is ambiguous. `ListCategoriesTool` gains `for_entry?: expense|income`, returning the selectable Categories from `App\Support\CategoryList\CategoryChoiceOptions::forType`. The consent page text mentions recording cash operations only when the switch is on.

#### 4. Tests

**File**: `tests/Feature/Mcp/AddCashOperationToolTest.php`, `tests/Feature/RecordCashOperationTest.php`

**Contract**: the ExpenseTest matrix through the tool (future date, date before opening, foreign account and Category, draft Budget, no Budget, archived Category, income Category for an expense, invalid amount "abc", "0", "1,234"); signs for expense, refund and income; bank account refused; zero and two cash accounts; a replay with the same `request_id` returns the same operation and leaves one row and one balance change; a concurrent duplicate on the unique index resolves to the existing receipt (PostgreSQL and SQLite): a `FinancialOperation::created` listener registered in the test inserts the receipt for the same `request_id` and another operation, and the call returns that operation with `replayed: true`, leaving one new operation row and one balance change; a replay after the Category was archived still returns the first operation; switch off → `assertNotRegistered`; `app(AddCashOperationTool::class)->toArray()['_meta']['anthropic/requiresUserInteraction']` is `true` (laravel/mcp has no `_meta` assertion); default date at 22:59:59 and 23:00:00 UTC on 31 December; warning true only when To Spend goes below zero and lower; `ResetUserData` removes receipts.

#### 5. Release

**Contract**: Same release path as Phase 4, before ~15 December 2026, not in one release with another slice that changes `ManageFinancialOperation` or the expense/income requests. `MCP_WRITE_ENABLED=true` in `.env.prod` when the user decides to switch it on. `deploy.sh` caches config (`scripts/deploy.sh:83`), so every change of `MCP_WRITE_ENABLED` (on, or off in an emergency) takes effect only after `scripts/artisan.sh config:cache`; `context/foundation/infrastructure.md` (MCP endpoint section) records that step.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/Mcp tests/Feature/RecordCashOperationTest.php` passes on SQLite and on PostgreSQL in the worktree
- Full gate on PostgreSQL in the worktree and `vendor/bin/sloppy ci` pass

#### Manual Verification:

- On the phone, "wydałem 23,50 zł gotówką na pieczywo" asks for approval, records one expense on the cash account, and the answer's balance and To Spend match the Dashboard
- With `MCP_WRITE_ENABLED=false` and `scripts/artisan.sh config:cache` the tool is not offered and Claude says it cannot record operations
- In claude.ai the tool's permission shows "Needs approval" by default and stays so

**Implementation Note**: Pause after the phone check; the change is then ready for `/10x-impl-review` and archive.

---

## Testing Strategy

### Unit Tests:

- `BudgetForYear`, `CurrentMonthReading`, `MonthTiming` (App Day boundaries, no Budget, draft)
- `MonthPlanGroupSums` parity with `monthPlanGroupTree` on shared fixtures (ADR-0079 example, archived and idle Even Annual rows, income group without over-plan)
- Value-based entry validation equals the HTTP path

### Integration Tests:

- One feature test file per MCP tool via `TreasurerServer::actingAs`, each with another user's data present
- HTTP endpoint: 401 + discovery, Passport token, rate limit, log line content
- OAuth: discovery, DCR redirect rules, consent page props, login/2FA/passkey location response, client pruning
- Write tool: validation matrix, kinds and signs, idempotent replay and race, switch, warning

### Manual Testing Steps:

1. Local server in Claude Code: the four read questions against the Dashboard, Month Plan, Budget and Operations pages.
2. Rehearsal clock at 2026-12-31 23:30: tools report 2027-01-01 and the 2027 Budget state.
3. OAuth in the worktree preview from Claude Code, with password + 2FA and with a passkey.
4. Production: connector on claude.ai, then Desktop and phone; compare To Spend with the Dashboard.
5. Phone: record a cash expense, approve, compare balance and To Spend; switch off and confirm the tool disappears.

## Performance Considerations

MCP requests share the four FPM workers with the web UI. Tools return bounded results (operations ≤ 50, one year or one month per call), use the same readers as the pages, and stream nothing. The rate limiter (60/min per user) caps a runaway client. No reader in scope exceeds the existing report NFR (2 s at 10,000 operations).

## Migration Notes

- Phase 3: Passport tables (new tables only). Rollback: `migrate:rollback` of those migrations; existing data untouched.
- Phase 6: `mcp_operation_requests` (new table, cascades). Rollback: drop the table; operations stay.
- Keys: generate once, store in `.env.prod`; rotating them signs every client out.

## References

- Related research: `context/changes/mcp-read-only-server/research.md`
- ADR-0103 (App Day), ADR-0079 (month group rows), ADR-0075 (default year, not used), ADR-0085 (archived accounts in totals)
- Rules precedent for non-HTTP validation: `app/Concerns/RedirectsToOperationsList.php:34`
- Parity test precedent: `tests/Unit/ToSpendCalculatorParityTest.php`, `resources/js/lib/to-spend-calculator.parity.test.ts`
- laravel/mcp docs: https://laravel.com/docs/13.x/mcp; Passport: https://laravel.com/docs/13.x/passport
- Claude connector auth: https://claude.com/docs/connectors/building/authentication
- Parallel changes procedure: `context/foundation/agents/parallel-changes.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Foundations and shared readers

#### Automated

- [x] 1.1 Unit tests for BudgetForYear, CurrentMonthReading and MonthTiming pass — f52c80a5
- [x] 1.2 Dashboard, Budget, Month Plan and Operations feature tests pass unchanged — f52c80a5
- [x] 1.3 AdrNumberingTest passes with ADR-0110 — f52c80a5
- [x] 1.4 boost:index-rules lists .ai/rules/mcp.md — f52c80a5
- [x] 1.5 Full gate on PostgreSQL and sloppy ci pass — f52c80a5

#### Manual

- [x] 1.6 Dashboard, Budget, Month Plan and Operations behave as before in the preview — confirmed by the user in the worktree preview 2026-11-19

### Phase 2: Local read server

#### Automated

- [x] 2.1 MCP tool tests and MonthPlanGroupSums parity test pass — fb561edb
- [x] 2.2 month-plan-groups parity Vitest passes — fb561edb
- [x] 2.3 Full gate on PostgreSQL and sloppy ci pass — fb561edb

#### Manual

- [x] 2.4 Four read questions in Claude Code match the app pages — confirmed by the user in the worktree preview 2026-11-19
- [x] 2.5 Rehearsal clock at 2026-12-31 23:30 gives app_day 2027-01-01 and the 2027 Budget state — confirmed by the user in the worktree preview 2026-11-19

### Phase 3: OAuth through Passport

#### Automated

- [x] 3.1 MCP and Auth feature tests pass — 39d93511
- [x] 3.2 npm run build, types:check and check:ui pass — 39d93511
- [x] 3.3 Full gate on PostgreSQL and sloppy ci pass — 39d93511

#### Manual

- [x] 3.4 Claude Code OAuth sign-in through the preview works with 2FA and with a passkey — confirmed by the user in the worktree preview 2026-11-19
- [x] 3.5 Consent page in /dev/ui/auth matches the login screen in both themes and at phone width — confirmed by the user in the worktree preview 2026-11-19

### Phase 4: Read endpoint on production

#### Automated

- [x] 4.1 Protected-resource metadata returns the production /mcp resource — verified with curl against production 2026-11-19
- [x] 4.2 POST /mcp without a token returns 401 — verified with curl against production 2026-11-19

#### Manual

- [x] 4.3 Cloudflare rules in place without challenges for Claude — no rules needed: the claude.ai connector connected without a challenge (user, 2026-11-19); see infrastructure.md "MCP endpoint"
- [ ] 4.4 claude.ai connector answers To Spend equal to the Dashboard
- [ ] 4.5 Connector works on Claude Desktop and the mobile app
- [ ] 4.6 mcp log has one line per call without amounts or descriptions

### Phase 5: Hand-entry validation without HTTP

#### Automated

- [x] 5.1 Expense, Income and edit/delete tests pass unchanged on SQLite and PostgreSQL — e1873a1c
- [x] 5.2 Value-based validation gives the same errors as the HTTP path — e1873a1c
- [x] 5.3 Full gate on PostgreSQL and sloppy ci pass — e1873a1c

#### Manual

- [x] 5.4 Operation dialogs behave as before in the preview — confirmed by the user in the worktree preview 2026-11-19

### Phase 6: Cash operation write tool

#### Automated

- [x] 6.1 MCP and RecordCashOperation tests pass on SQLite and PostgreSQL — a0a44200
- [x] 6.2 Full gate on PostgreSQL and sloppy ci pass — a0a44200

#### Manual

- [ ] 6.3 Cash expense from the phone is approved, recorded once and matches the Dashboard
- [ ] 6.4 With MCP_WRITE_ENABLED=false the tool is not offered
- [ ] 6.5 claude.ai shows the write tool as Needs approval
