---
date: 2026-11-19T13:21:28+01:00
researcher: Claude Opus 5.5 (session of the user Sebastian Kostecki)
git_commit: 05b361366f80a96a8fe0249d2457a4aa42e17b88
branch: worktree-mcp-read-only-server
repository: household-treasurer
topic: "An MCP server (laravel/mcp) for asking Claude about the budget: reuse of existing readers, auth (Sanctum vs Passport OAuth), local variant, App Day, tests, production, and the later write tool for cash operations"
tags: [research, codebase, mcp, laravel-mcp, auth, passport, sanctum, app-day, dashboard, month-plan, operations, production]
status: complete
last_updated: 2026-11-19
last_updated_by: Claude Opus 5.5 (session of the user Sebastian Kostecki)
last_updated_note: "User decisions 1 and 1a: the whole change (stages 1-3, incl. cash write) goes with M-5"
---

# Research: MCP server for asking Claude about the budget

**Date**: 2026-11-19T13:21:28+01:00
**Researcher**: Claude Opus 5.5 (session of the user Sebastian Kostecki)
**Git Commit**: 05b361366f80a96a8fe0249d2457a4aa42e17b88 (working tree: only `context/changes/mcp-read-only-server/`)
**Branch**: worktree-mcp-read-only-server (reset to local `development`, which equals `origin/development` at 05b3613)
**Repository**: household-treasurer

## Research Question

From `change.md` (Notes): a read-only MCP server so the user can ask Claude (Claude Code and Desktop first, the phone app later) about the state of the budget on production data. Read scope in glossary terms: account balances, Spendable Liquidity and Total funds; To Spend in the current month; plan vs actual for Categories and Category Groups in a month and in a year; recent operations filtered by Category and period. Six questions: (1) which existing classes compute these values, (2) Sanctum token vs Passport OAuth, incl. what the mobile app needs and whether a token can come first, (3) a local variant as a first stage, (4) "today" and the current year by the App Day (ADR-0103), (5) Pest tests incl. reading another user's data, (6) production: endpoint behind Traefik and Cloudflare, headers, rate limit, call logging, 1 GB container. Constraint: M-5 is open and new features are parked until January (roadmap, decision 2026-11-18); say what can be done before M-5 closes.

Added during research (user, 2026-11-19): „przez mcp chciałbym też wrzucać operacje gotówkowe” — the write path for cash operations is in scope of this research; its timing is a decision.

## Summary

- **The four stated facts hold.** `laravel/mcp` v1.1.2 is in `composer.lock` under `packages-dev` (section starts at `composer.lock:8650`, package at `:9503`) as a requirement of `laravel/boost` (`:9455`); `composer.json` names neither `laravel/mcp`, `laravel/sanctum` nor `laravel/passport` (only `laravel/fortify`, `composer.json:14`); `app/Mcp` and `routes/ai.php` do not exist; production installs with `--no-dev` (`docker/8.5/Dockerfile.prod:6,9`), so the package is absent there. User isolation has no global scope: it comes from starting every query at a `$user->…` relation (details below), and every MCP tool has to keep that discipline.
- **Q1 — reuse is possible for all four reading groups without recomputing**, through public `app/Support` classes that read no session or request: `SpendableLiquidity::forUser`, `TotalFunds::forUser`, `MonthToSpend::forMonth`, `BudgetPlanSummary::forLines/groupsOfType`, `MonthPlanItemRow::forLine`, `OperationsListQuery`. Three gaps: (a) the "current-year Budget + execution guard + summary" wiring of To Spend lives in `DashboardController` (private), (b) month-level Category Group sums exist only in TypeScript and ADR-0079 rejected server sums for the Month Plan, (c) the default year rule (ADR-0075) is private in `SelectedBudgetYear` and its public entry reads the session.
- **Q2 — the mobile app needs OAuth in practice.** Claude's connector infrastructure (claude.ai, Desktop, mobile, Claude Code) supports OAuth (DCR/CIMD) and authless by default; a static bearer header is "Beta, for a limited set of organizations" and meant as an organization credential. Recommendation: skip Sanctum, use the local stdio variant for Claude Code trials, and give the remote endpoint Passport OAuth via `Mcp::oauthRoutes()`. Tools that only read `$request->user()` are auth-agnostic, so starting with a token and adding OAuth later does not rewrite tools — but it builds and later removes a second auth layer.
- **Q3 — yes, separate the local variant as stage 1.** It needs no new production dependency, no endpoint and no auth; it can run on a production snapshot restored locally (`user:backup` / `user:restore`, README:125-136). It is the only part that fits before M-5 closes without touching production behaviour.
- **Q4 — tools take the day from `AppDay::today()` and never from the session.** The Dashboard already does this (`DashboardController.php:35-36`). Every response should carry the App Day it used, so Claude computes "yesterday" or "this month" from the server's day, not its own clock.
- **Q5 — laravel/mcp has a testing API** (`Server::actingAs($user)->tool(...)`, `assertHasErrors`, `assertDontSee`, `tools()->assertNotRegistered`), and the repo has isolation test patterns to copy.
- **Q6 — `/mcp` needs no Traefik change** (host-only router), skips CSRF and session by construction, and gets the Traefik security headers. Open items: Cloudflare Bot Fight Mode/WAF (not in any repo), `LOG_LEVEL=error` in production hides an info-level call log, no MCP rate limiter exists, and four FPM workers are shared with the web UI.
- **Cash write tool** — a cash operation is an Expense (or Income) on an `Account` of type `Cash`. The write Action exists (`ManageFinancialOperation::createExpense`), but the hand-entry validation is bound to the HTTP FormRequest, hand entry has no duplicate detection, and the To Spend warning after an entry exists only in TypeScript. It needs a validation extraction, an idempotency decision and an ADR; it belongs after the read stages.
- **M-5 split:** before M-5 closes — this research, the plan, and optionally the local read-only variant. After M-5 (January) — `laravel/mcp` in `require`, Passport, the production endpoint, Cloudflare and logging, controller extractions that change production code paths, the write tool.

## Detailed Findings

### 0. Facts from change.md, verified

| Claim in change.md | Verdict | Source |
| --- | --- | --- |
| laravel/mcp v1.1.2 only as a dev dependency of Boost, absent in production | supported | `composer.lock:8650` (`packages-dev`), `:9503-9504` (v1.1.2), `:9455` (Boost requires it); `composer.json:25` (Boost in require-dev); `docker/8.5/Dockerfile.prod:6,9` (`--no-dev`) |
| auth is Fortify (session) only; no Sanctum or Passport | supported | `composer.json:14`; no `sanctum`/`passport`/`HasApiTokens` in `app`, `config`, `routes`, `bootstrap` (grep) |
| no `routes/ai.php`, no `app/Mcp` | supported | `routes/` holds `console.php`, `settings.php`, `web.php` only |
| each user sees only their own data | supported as a convention, not a mechanism | no model global scopes (Category/CategoryGroup `booted()` only sets `name_key`); scoping by `$user->…` relations, policies (e.g. `app/Policies/BudgetPolicy.php:15`) and `exists` rules with `user_id` (`app/Http/Requests/IndexOperationsRequest.php:39-49`) |

Newer releases exist: laravel/mcp v1.2.0 (2026-11-10, Origin header validation on `Mcp::web` routes, JSON-RPC errors instead of HTTP 500) and v1.2.1 (2026-11-17) — https://github.com/laravel/mcp/releases. Moving the package to `require` is the moment to take 1.2.x.

### 1. Existing readers for each value (Q1)

**Balances, Spendable Liquidity, Total funds** (Dashboard: `routes/web.php:39` → `DashboardController::show`, `app/Http/Controllers/DashboardController.php:32`)

- Account balance is a stored counter `accounts.current_balance_minor_units` (cast in `app/Models/Account.php:56`); the Dashboard lists `$user->accounts()->withoutSheetAccounts()->orderBy('name')` and drops archived ones (`DashboardController.php:39-48`), shaping each through the private `accountPayload` (`:190-199`). Term deposits: `$user->termDeposits()` and private `termDepositPayload` (`:213-225`).
- `SpendableLiquidity::forUser(User): int` (`app/Support/SpendableLiquidity.php:13-16`) sums all accounts **including archived ones**, Sheet Accounts left out (ADR-0085). `TotalFunds::forUser(User): int` (`app/Support/TotalFunds.php:13`) adds term deposits.
- Consequence for a tool: a list of non-archived accounts next to these totals does not add up when an archived account holds money; the tool should say so or list archived accounts separately.
- No session or request dependency in these classes (inspected: `SpendableLiquidity`, `TotalFunds`, `AccountIdentity`).

**To Spend for the current month**

- `MonthToSpend::forMonth(Budget, int $month, CarbonImmutable $today)` (`app/Support/MonthToSpend.php:36`), then `->summary()` (`:129`) → `{state: proposal|approved|none, to_spend_minor_units}`; `->toSpendBreakdown()` (`:78`) gives income and expense parts.
- The wiring is in the controller: Budget by `$user->budgets()->where('year', $year)->first()` (private `findBudgetForYear`, `DashboardController.php:178-185`), `MonthToSpend` only when `$budget->isExecution()` (`:95`), and `month_to_spend` stays null when `state === 'none'` (`:117-118`). `MonthToSpend` itself does not enforce the execution phase (agent reading of its docblock; not contradicted).
- No Budget for the App Day year → no To Spend; Spendable Liquidity and Total funds are still returned. The Dashboard never falls back to another year.
- Do not use `EntryToSpendInputs::forUser` for this answer: it is the raw input for the browser's TypeScript calculator (`app/Support/EntryToSpendInputs.php:43`).

**Plan vs actual — year**

- Per Budget Line: `planned_amount_minor_units` (Current Plan), `initial_plan_minor_units`, `execution_minor_units` (a stored counter kept by `ManageFinancialOperation`, `app/Actions/ManageFinancialOperation.php:277-295`).
- `BudgetPlanSummary::forLines(Budget, Collection)` (`app/Support/BudgetPlanSummary.php:95`), `groupsOfType(Budget, $lines, CategoryType)` (`:142`), `forBudget` (`:52`): initial, current, execution, remaining, percent complete, over-limit flag — groups summed in PHP over active lines (ADR-0069). The Budget page uses the same reading (`app/Http/Controllers/BudgetController.php:57-100`). This is the best single entry for a yearly plan vs actual per Category and per Category Group. It needs `category.group` eager loaded.

**Plan vs actual — month**

- `MonthToSpend::forMonth(...)` plus `MonthPlanItemRow::forLine(BudgetLine, MonthToSpend, $timing, bool $isJoinCandidate, int $month, $today)` (`app/Support/MonthPlanItemRow.php:45`) per Category: plan, actual, deviation (null while a Fixed Charge is unpaid), over-plan flag, approved amount, year execution. Requires a Budget in execution (`MonthPlanController.php:73`).
- The month timing (`past|current|future`) and the row assembly are private in `MonthPlanController` (`monthTiming` `:320`, `itemRows` `:279`, `groupsByType` `:218`).
- **Month-level Category Group sums exist only in TypeScript**: `monthPlanGroupTree` (`resources/js/lib/month-plan-groups.ts:35`). `.ai/rules/js-month-plan.md:25` says the group sums come from it, "never from the server", and ADR-0079 (`context/foundation/adr/0079-category-group-row-in-the-month-plan.md:13,23`) rejected server sums because in edit mode they must follow the typed plan. That reason does not apply to a read-only MCP answer, but a PHP copy is a second implementation of the ADR-0079 rule (literal sums of shown rows, archived and idle Even Annual rows out, "any row over plan"). This is a decision (see Decisions).
- `MonthSummary::forMonth(MonthToSpend, Budget, int)` (`app/Support/MonthSummary.php:31`) gives the past-month summary by type, only after approval.
- Reports (`PeriodReport::forMonth/forYear`, `app/Support/PeriodReport.php:51,56`) carry actuals only, no plan, so they cannot answer "plan vs actual".
- Key mismatch to watch: `MonthPlanReading.amounts`, `MonthCategoryActuals` and `YearsLineActuals` key by **Budget Line id**; row payloads and `PeriodReport` use the list **Category id**.

**Recent operations with filters**

- `OperationsListQuery` (`app/Support/OperationsListQuery.php`): `FILTER_KEYS` (`:15`) are `q`, `year`, `category_id` (list Category, all years, via `budgetLine.category_id`, `:144`), `account_id`, `term_deposit_id`, `date_from`, `date_to`. No type or amount filter. `fromValidated(array)` (`:35`) is pure; `apply(Builder|Relation)` (`:127`).
- Validation rules are static: `IndexOperationsRequest::listRules(?int $userId)` (`app/Http/Requests/IndexOperationsRequest.php:31`), usable with `Validator::make` outside HTTP.
- Pagination (50, `operations_page`), sort (`operation_date desc, id desc`) and row shaping are in `OperationsController.php:29,43-65`.
- Leak risk: `apply()` on `FinancialOperation::query()` instead of `$user->financialOperations()` would filter another user's rows by `category_id`/`account_id` without a user check (`OperationsListQuery.php:144-149`). On the user relation a foreign id returns nothing.

**Session and request dependencies**

- `SelectedBudgetYear::resolve(?Request)` reads `session('budget_year')` (`app/Support/SelectedBudgetYear.php:55`); the default-year rule is private (`defaultYear`, `:74`); only `budgetYearsForUser(User)` (`:84`) is public. Callers: `MonthPlanController.php:41`, `ReportController.php:28`, `BudgetController.php:37`, `ShellState.php:30`, `CommandPaletteCategoryController.php:18`.
- Outside `SelectedBudgetYear` and `CsvImport/CsvImportSession`, no class in `app/Support` reads the session or request (agent grep of `app/Support`).

### 2. Auth: Sanctum token vs Passport OAuth (Q2)

**What laravel/mcp documents** (https://laravel.com/docs/13.x/mcp#authentication):

- Sanctum: `Mcp::web('/mcp', Server::class)->middleware('auth:sanctum')`; the client sends `Authorization: Bearer <token>`. On 401 the package sends only `WWW-Authenticate: Bearer realm="mcp", error="invalid_token"` with no discovery URL (`vendor/laravel/mcp/src/Server/Middleware/AddWwwAuthenticateHeader.php:41-45`, comment "Sanctum, can't share discover URL").
- OAuth 2.1 via Passport: `Mcp::oauthRoutes()` registers `/.well-known/oauth-protected-resource`, `/.well-known/oauth-authorization-server` and `POST /oauth/register` (DCR), with the single scope `mcp:use` (`vendor/laravel/mcp/src/Server/Registrar.php:31` and the routes around `:130-217`). The docs call OAuth the most widely supported option. Passport latest is 13.12.0 (supports Laravel 13).
- A tool reads the user with `$request->user()` (`vendor/laravel/mcp/src/Request.php:91-96`), whatever guard authenticated it.

**What Claude clients accept** (https://claude.com/docs/connectors/building/authentication, read 2026-11-19):

- One connector infrastructure backs claude.ai, Claude Desktop, Claude mobile, Claude Code and Cowork.
- Supported by default: `oauth_dcr`, `oauth_cimd`, `none`. `static_headers` (API key or bearer token entered by an organization Owner): "Beta, for a limited set of organizations"; Owners without access do not see the Request headers section; the page says to treat it as the organization's credential and to use OAuth when tools act as the individual user.
- OAuth specifics: a 401 is required to start sign-in; only the first `authorization_servers` entry is used; endpoints get 10 s (refresh 30 s); callback `https://claude.ai/api/mcp/auth_callback`; Claude Code needs a port-agnostic loopback redirect for `localhost` and `127.0.0.1`.
- Requests come from Anthropic's cloud (egress `160.79.104.0/21`, https://platform.claude.com/docs/en/api/ip-addresses), so the endpoint must be public HTTPS; localhost is not reachable from claude.ai or mobile.
- Mobile: connectors connected on web/Desktop are available "on the web, desktop, and mobile" (https://claude.com/docs/connectors/getting-started). Adding a custom connector from inside the mobile app is not documented (unverified).
- Claude Code alone accepts a static header: `claude mcp add --transport http <name> <url> --header "Authorization: Bearer …"` (https://code.claude.com/docs/en/mcp), and also OAuth via `/mcp`.

**Cost of the two paths** (estimates are inference from the documented steps):

| | Sanctum token | Passport OAuth |
| --- | --- | --- |
| Packages | `laravel/sanctum` (+ `laravel/mcp` to `require`) | `laravel/passport` 13.x (+ `laravel/mcp` to `require`) |
| Schema | `personal_access_tokens` | Passport tables (clients, auth codes, access and refresh tokens, device codes) |
| Secrets | none | Passport key pair in production (`passport:keys` or env vars) |
| UI | a way to create/revoke a token (command or settings page) | a consent screen (`vendor:publish --tag=mcp-views`, or an Inertia page that follows Atrament) behind Fortify login |
| Works in | Claude Code; claude.ai/Desktop/mobile only with the beta Request headers | every Claude client |
| Rough size | ~0.5 day | ~1–2 days incl. ADR and tests |

**Token first, OAuth later?** Tools do not change: they read `$request->user()`; only the route middleware changes (`auth:sanctum` → `auth:api`). Costs of doing both: Sanctum and Passport each ship a `HasApiTokens` trait with the same method names on `User` (conflict resolution or removal of Sanctum later), two token tables, and a token UI that is thrown away. Given that the local stdio variant already covers Claude Code trials, the token stage buys little.

**Read-only vs write scope.** Sanctum abilities (`createToken('mcp', ['mcp:read'])`, `tokenCan`) split read and write easily. laravel/mcp's OAuth grants only `mcp:use` in metadata and DCR, so a read/write split under OAuth is custom work. For one user, the client-side per-tool approval (below) may be enough; that is a decision.

### 3. Local variant (Q3)

- Boost already runs this way: `.mcp.json` → `.claude/hooks/boost-mcp.sh` → `php artisan boost:mcp` (`mcp:start laravel-boost`), registered with `Mcp::local` (`vendor/laravel/boost/src/BoostServiceProvider.php:68`), against Sail PostgreSQL on the forwarded port.
- An app server would be `Mcp::local('treasurer', TreasurerServer::class)` in `routes/ai.php`, started by `php artisan mcp:start treasurer` (Claude Code: `claude mcp add treasurer -- php artisan mcp:start treasurer`; Desktop: a `claude_desktop_config.json` command, which on Windows with WSL needs a `wsl.exe` wrapper — inference).
- stdio has no auth: `$request->user()` is null, so the local server has to pin a user (e.g. from an env value, allowed only when `app()->isLocal()`). This pattern is inference, not documented by laravel/mcp.
- Production safety (inference from code): `routes/ai.php` is loaded only by `McpServiceProvider::registerRoutes` (`vendor/laravel/mcp/src/Server/McpServiceProvider.php:86-98`); `bootstrap/app.php:13-17` loads `web`, `console` and `/up` only. With `--no-dev` the provider is not discovered in production, so the file is never loaded there. Classes in `app/Mcp` that extend laravel/mcp classes would also never be autoloaded there. No route guard is needed for that reason; a test that boots the app without the package is not available, so this stays an inference to confirm in the plan.
- Real data without an endpoint: `user:backup` on production and `user:restore {user} {path}` locally (README:125-136) give the local server the user's real numbers. A restore leaves root-owned files to fix (memory note "production ops shell quirks").
- `RehearsalClock::apply()` runs at boot (`app/Providers/AppServiceProvider.php:50`) in the local environment, also in console commands other than the clock command (`app/Support/RehearsalClock.php:140`), so the local MCP server follows `app:rehearsal-clock` — useful to try the year turn by hand (not as proof).
- PHP: `composer.json` requires `^8.4.1`; agent shells have shown both 8.3 and 8.4.1 on the host, so the `.mcp.json` command may need the Sail image (`docker compose exec -T laravel.test php artisan mcp:start treasurer`; stdout must stay clean).
- Relying on `laravel/mcp` arriving transitively through Boost is fragile; naming it in `require-dev` is a dependency change that needs the user's approval (AGENTS.md).

**Verdict:** worth a stage of its own: it proves the tool set and the answers on real numbers before any auth, endpoint or production dependency exists, and it is the part that does not compete with M-5 for production risk.

### 4. App Day and the year turn (Q4)

- Rule: "today" in `app/` only from `AppDay::today()` (`app/Support/AppDay.php:19-29`, ADR-0103, `.ai/rules/app-day.md`); `tests/Unit/AppDayClockGuardTest.php` scans `app/`, so `app/Mcp` is guarded automatically.
- Tools must not use `SelectedBudgetYear::resolve` (session). Default year and month for "now" questions = `AppDay::today()->year` / `->month`, as the Dashboard (`DashboardController.php:35-36`); an explicit `year`/`month` argument for other periods.
- Year turn: at 2026-12-31 23:00:00 UTC the App Day is 2027-01-01. If the 2027 Budget does not exist or is a draft, To Spend and the month plan have no answer (Dashboard behaviour), while balances and totals still answer. ADR-0075's fallback ("newest year not later than the current year") is the sidebar's default and is private (`SelectedBudgetYear.php:74`). Whether a tool falls back or says "Budget 2027 is a draft" is a decision; the recommendation is no silent fallback: answer with the year used and its phase.
- Every response should include the App Day (`YYYY-MM-DD`) it used, and the server `instructions` should say that relative dates ("yesterday", "this month") are relative to it. Otherwise Claude computes them from its own clock, which can be on another day for up to two hours after Polish midnight. This matters most for the write tool's default date.
- S-40 (`session-year-follows-year-turn`, proposed) changes the session year only; it does not touch the MCP path if tools never read the session.

### 5. Tests (Q5)

- Testing API at v1.1.2: `Server::actingAs($user, $guard)` → `PendingTestResponse` with `tool()`, `resource()`, `prompt()`, `tools()` (`vendor/laravel/mcp/src/Server/Testing/PendingTestResponse.php:35-237`); assertions `assertOk`, `assertSee`, `assertDontSee`, `assertStructuredContent`, `assertHasErrors`, `assertHasNoErrors`, `assertAuthenticatedAs`, `assertNotRegistered` (`vendor/laravel/mcp/src/Server/Testing/TestResponse.php:58-260`).
- Repo patterns to copy: isolation at `tests/Feature/TotalFundsTest.php:33` (a reading ignores other users), `tests/Feature/CategoriesPageTest.php:227`, `tests/Feature/CategoryMergeHttpTest.php:47,68` (foreign id → validation error), `tests/Feature/ExpenseTest.php:476` (foreign account and category refused, zero rows written); year turn at `tests/Feature/YearTurnTest.php:132` (`travelTo('2026-12-31 22:59:59')`).
- Per tool, at least:
  1. happy path with a hand-counted minor-units literal (as `.ai/rules/reports.md` requires for report amounts), not "equals the other reading";
  2. another user's data: create a second user with a Category/Budget/operation, call with the foreign id and assert an error and `assertDontSee` of the foreign name; call without ids and assert the foreign data is absent;
  3. App Day boundary: `travelTo` at 22:59:59 and 23:00:00 UTC on 31 December (never 00:00 UTC);
  4. no Budget / draft Budget for the App Day year.
- Server level: no `actingAs` → error; HTTP test of `POST /mcp` without a token → 401 with `WWW-Authenticate`; with a token → 200.
- PostgreSQL: Passport migrations and any new constraint (idempotency key) run on PostgreSQL per AGENTS.md (worktree command with `DB_DATABASE=testing_<change>`).
- Each new `app/Mcp` class passes Sloppy (`.ai/rules/sloppy.md`): no queries in loops when listing Categories with their figures.

### 6. Production (Q6)

- **Path:** the Traefik router is `Host(budget.kostecki.dev)` with no path rule (`docker-compose.prod.yml:36-43`), so `https://budget.kostecki.dev/mcp` reaches the same app container with no Traefik change. With OAuth, `/.well-known/oauth-*` and `/oauth/*` as well.
- **Chain:** Cloudflare (proxied, Full strict) → host firewall lets only Cloudflare IPs to 80/443 → Traefik v3.7.19 → nginx → PHP-FPM (sibling repo `kostecki-dev-infra`, `docs/SETUP.md:342-351`, `docker-compose.yml:3`). Anthropic's egress therefore arrives through Cloudflare; the firewall is not a problem.
- **Cloudflare:** Bot Fight Mode, WAF and cache rules live only in the Cloudflare panel (no repo records them); they can challenge non-browser POSTs from `160.79.104.0/21`. Needs a manual check and probably a rule that skips challenges and caching for `/mcp`, `/oauth/*`, `/.well-known/*`.
- **Middleware:** laravel/mcp loads `routes/ai.php` with `Route::group([], $path)` (`McpServiceProvider.php:98`), outside the `web` group, so no session and no CSRF (`PreventRequestForgery` is in the web group); the package adds `ReorderJsonAccept`, `ValidateMcpHeaders`, `AddWwwAuthenticateHeader` on the route and pushes two global middleware (`McpServiceProvider.php:51-58`). `deploy.sh:85` runs `route:cache`, which includes the ai routes because registration runs in the console (`McpServiceProvider.php:93-95`). `APP_URL` must be the public https URL (resource, issuer and the v1.2 Origin check use it); trusted proxies are `172.16.0.0/12` (`bootstrap/app.php:23-26`).
- **Security headers:** set by Traefik middleware `app-security@file` (`docker-compose.prod.yml:42`; `kostecki-dev-infra/traefik/dynamic/app-security.yml:10-23`): HSTS, nosniff, `X-Frame-Options: SAMEORIGIN`, Referrer-Policy, Permissions-Policy. They apply to `/mcp` and are harmless for JSON; no CSP and no CORS config exist, and server-to-server calls need no CORS.
- **Streaming:** nginx has no `fastcgi_buffering off` (`docker/8.5/nginx.conf:51-62`); laravel/mcp streams only when a tool returns an iterable (`vendor/laravel/mcp/src/Server/Transport/HttpTransport.php:48-65`), so plain responses are enough and no nginx change is needed.
- **Rate limit:** only Fortify's limiters exist (`app/Providers/FortifyServiceProvider.php:86-96`) and `throttle:6,1` in `routes/settings.php:24`. MCP needs its own `RateLimiter::for('mcp', …)` keyed by user, and `throttle:10,1` on DCR registration if OAuth is used.
- **Call logging:** production runs `LOG_LEVEL=error` on the `single` channel (`.env.prod.example:17-20`); nginx access log is off (`docker/8.5/nginx.conf:18`), Traefik keeps one. An info-level line per tool call (user id, tool name, duration, result size — ids only, never amounts or descriptions, in line with `.ai/rules/errors.md`) needs its own channel with its own level. Error tracking (S-45) is blocked on the provider choice (`context/foundation/roadmap.md:197-208`).
- **Memory:** container `mem_limit: 1g`, `cpus: 1.0` (`docker-compose.prod.yml:17-19`); PHP-FPM `pm = ondemand`, `pm.max_children = 4`, `memory_limit = 256M` (`docker/8.5/php-fpm.conf:17-31`), shared with the web UI, queue and scheduler under supervisord. MCP calls are short JSON requests on the same workers. Keep result sizes bounded (operations with a limit, e.g. ≤ 50 rows; no "all years" dumps); the heaviest reader in scope, the year report, has an NFR of 2 s at 10,000 operations (`.ai/rules/reports.md`). Passport adds no process.

### 7. Write path for cash operations (scope addition)

- **Representation:** `App\Enums\AccountType` has `Bank` and `Cash` (`app/Enums/AccountType.php:5-9`). A cash operation is an ordinary Expense or Income whose `account_id` is a Cash Account; a user may have several. No cash-specific rule exists; balances may go negative (warning only). An ATM withdrawal is an Own Transfer bank → cash, not an Expense (glossary).
- **Write path today:** `POST expenses` → `ExpenseController@store` (`routes/web.php:113`) → `StoreExpenseRequest` with rules from `App\Concerns\ExpenseValidationRules::storeExpenseRules()` (`app/Concerns/ExpenseValidationRules.php:17-48`) plus `OperationEntryValidation::attach(Validator, Request, User, CategoryType)` (`app/Support/OperationEntryValidation.php:19`), which adds the account opening date check and `OperationCategoryChoice::check` (sheet year, missing or draft Budget, archived line or Category) → `ManageFinancialOperation::createExpense(User, array)` (`app/Actions/ManageFinancialOperation.php:60`). Income is the twin (`routes/web.php:109`).
- **Reuse problem:** the rules read `$this->user()` (`ExpenseValidationRules.php:19`) and `OperationEntryValidation` reads an `Illuminate\Http\Request`; a laravel/mcp tool receives `Laravel\Mcp\Request`. The Action trusts its input: skipping the request checks lets through a future date, a date before the account opened, an income Category booked as an expense, and a draft Budget whose line already exists; the rest becomes `InvariantViolation` (logged 500), not a fixable message. So the write tool needs the validation extracted into a form that takes plain values and a user (e.g. a static `rulesFor(int $userId)` and an `OperationEntryValidation` variant over values) shared by the FormRequest and the tool — a production refactor covered by `tests/Feature/ExpenseTest.php`.
- **Amount:** the HTTP contract takes integer `amount_minor_units`; the tool parses "23,50" with `MinorUnits::fromMajorInput` (`app/Support/MinorUnits.php:7-21`, null on bad input). Positive = expense, negative = refund.
- **Category choice:** `CategoryChoiceOptions::forType(User, CategoryType)` (`app/Support/CategoryList/CategoryChoiceOptions.php:27`) is what the entry dialog offers (active Categories per Budget in execution). No name search exists; a `list_categories` tool returns ids and Claude picks one, asking when ambiguous.
- **Idempotency:** hand entry has no duplicate detection (glossary: hand entries are never Import duplicates), the import digest index is non-unique (`create_financial_operations_table.php:36`), so a retried tool call writes twice. Claude Code repeats a dropped call only for tools marked `idempotentHint` or `readOnlyHint` (https://code.claude.com/docs/en/mcp). Options: a nullable unique `client_request_id`-style column (migration, glossary entry, ADR) and `#[IsIdempotent]`, or no key and a non-idempotent tool with per-call approval.
- **Confirmation:** claude.ai/Desktop/mobile show "Allow once / Always allow" per call and per-tool permissions (Always allow, Needs approval, Blocked) (https://claude.com/docs/connectors/getting-started); Claude Code prompts for MCP tools without an allow rule, and `_meta["anthropic/requiresUserInteraction"] = true` forces a prompt in every mode (https://code.claude.com/docs/en/mcp), settable with laravel/mcp `setMeta`. Annotations: `#[IsReadOnly]` on read tools, `#[IsReadOnly(false)]`, `#[IsDestructive(false)]` on the write tool (`vendor/laravel/mcp/src/Server/Tools/Annotations/*`). Anthropic's review criteria ask for read and write tools to be separate.
- **Answer after the write:** no events or caches exist; the server can return the new account balance, `MonthToSpend::forMonth(...)->toSpendMinorUnits()` for the month, and the Category's Available Limit. The "warn when To Spend turns negative and lower" rule exists only in TypeScript (`resources/js/lib/entry-to-spend-warning.ts`); a server answer can report the before/after numbers without that rule, or port it with a parity test.
- **Undo:** `ManageFinancialOperation::delete()` reverts the effect and hard-deletes (`ManageFinancialOperation.php:109-115`); a delete tool is out of scope of this research and would be destructive.

## Code References

- `app/Http/Controllers/DashboardController.php:32-48,95,117-118,178-185` — Dashboard: App Day year, accounts, To Spend wiring, private Budget lookup
- `app/Support/SpendableLiquidity.php:13-16`, `app/Support/TotalFunds.php:13` — totals per user
- `app/Support/MonthToSpend.php:36,78,129` — To Spend for a month
- `app/Support/BudgetPlanSummary.php:52,95,142` — yearly plan vs execution per Category and Category Group
- `app/Support/MonthPlanItemRow.php:45` — month row per Category
- `resources/js/lib/month-plan-groups.ts:35` — month Category Group sums (TypeScript only)
- `app/Support/OperationsListQuery.php:15,35,127,144-149` — operation filters
- `app/Http/Requests/IndexOperationsRequest.php:31,39-49` — static list rules with user scoping
- `app/Support/SelectedBudgetYear.php:41-79` — session year and private default-year rule
- `app/Support/AppDay.php:19-29`, `app/Providers/AppServiceProvider.php:50`, `app/Support/RehearsalClock.php:138-149` — App Day and the local rehearsal clock
- `app/Concerns/ExpenseValidationRules.php:17-48`, `app/Support/OperationEntryValidation.php:19-40` — hand-entry validation bound to HTTP
- `app/Actions/ManageFinancialOperation.php:60,109-115,277-295` — write Action, delete, counters
- `app/Enums/AccountType.php:5-9`, `app/Support/MinorUnits.php:7-21` — cash account type, amount parsing
- `vendor/laravel/mcp/src/Server/McpServiceProvider.php:51-58,86-98` — global middleware, `routes/ai.php` loading
- `vendor/laravel/mcp/src/Server/Registrar.php:31` — the single OAuth scope `mcp:use`
- `vendor/laravel/mcp/src/Server/Middleware/AddWwwAuthenticateHeader.php:41-45` — Sanctum 401 without discovery
- `docker-compose.prod.yml:17-19,36-43`, `docker/8.5/php-fpm.conf:17-31`, `docker/8.5/Dockerfile.prod:6,9` — production limits, router, `--no-dev`
- `composer.lock:8650,9455,9503` — laravel/mcp v1.1.2 in `packages-dev`

## Architecture Insights

- User isolation is a convention: start every read at `$user->accounts()`, `$user->budgets()`, `$user->financialOperations()`, `$user->categories()`, `$user->categoryGroups()`. Budget-based Support classes trust the Budget passed in, so an MCP tool resolves the Budget from the user and a year, never from an id in the input. Ids from input (Category, Category Group, Account) are checked against the user's relations or with the `exists … where user_id` rules before use.
- The Support layer is already session-free; the obstacles to reuse are private controller methods (Budget lookup, month timing, row shaping) and TypeScript-only rules (month group sums, entry warnings). Extracting the small orchestration pieces into `app/Support` readers shared by the controller and the tool keeps one implementation; leaving them duplicated in `app/Mcp` creates the second implementation the user asked to avoid.
- The tools fit as a thin layer: a `TreasurerServer` with read tools marked `#[IsReadOnly]` (account balances and totals; To Spend now; plan vs actual for a year or a month by Category and Category Group; recent operations; list of Categories) and, later, one write tool. Output in minor units plus a formatted string, the App Day and the Budget year/phase used.

## Historical Context (from prior changes)

- `context/foundation/development-directions.md:97` — "serwer MCP do odczytu" listed under "5. Do rozważenia": "każde wymaga potwierdzenia potrzeby", status open. Not on the roadmap.
- `context/foundation/roadmap.md:248` — new features from development-directions parked: "decyzja użytkownika 2026-11-18, funkcja w styczniu z listy tarć (MS-10)". M-5 "Done when" includes the January plan approved on production and the friction list (`roadmap.md:28`).
- ADR-0103 — one App Day in Europe/Warsaw; S-39 (`one-app-day`) is done, so `AppDay` exists. S-40, S-41, S-43 are proposed, S-44 ready, S-45 blocked.
- ADR-0079 — month Category Group sums computed in the client, server sums rejected (for the edit mode reason).
- ADR-0075 — default Budget year rule; ADR-0085 — archived accounts count in Spendable Liquidity; ADR-0106 — Import refusal for a draft or missing year (hand entry gets the same refusal through `OperationCategoryChoice`).
- `.ai/rules/errors.md` (observability audit 2026-11-19, PLB-3) — Actions throw `InvariantViolation` for states the request already refused; the write tool must not rely on that as its validation.

## Related Research

- `context/archive/2026-11-12-reports-view/research.md` — Reports view; source of the report amount invariants reused here.
- No earlier research on MCP, API tokens or OAuth exists in `context/changes/**` or `context/archive/**` (grep for `mcp|sanctum|passport|api token` found mentions only in foundation docs and UI plans, none about this feature).

## Recommendation

1. **Stage 1 — local read-only server (stdio), can start before M-5 closes if the user wants it then.** `Mcp::local`, a pinned user in the local environment only, read tools that call existing public Support classes, Pest tests per tool, tried in Claude Code on a production snapshot restored locally. No production dependency, no endpoint, no auth. Small controller duplications (Budget lookup for To Spend) are either accepted temporarily or extracted with the existing controller tests as the guard — see Decisions.
2. **Stage 2 — remote read-only endpoint with Passport OAuth (January, after M-5).** `laravel/mcp` 1.2.x to `require`, Passport, consent screen behind Fortify, `Mcp::oauthRoutes()` with DCR throttled, `RateLimiter::for('mcp')`, a call-log channel, Cloudflare rules, ADRs. Then connect on claude.ai and use from Desktop and the phone. Skip a Sanctum stage.
3. **Stage 3 — write tool for cash operations (after stage 2 is used for a while).** Extract HTTP-free entry validation, decide idempotency, separate write tool with forced approval, answer with balance and To Spend after the write.

## Decisions for the user

1. **Timing:** start stage 1 now (alongside M-5) or put the whole change on the January friction-list choice (MS-10), as the roadmap parks new features? Roadmap stays unchanged either way.
   - **User decision 2026-11-19:** „chciałbym tutaj razem z M-5 wdrożyć” — the change goes with M-5, not in January. Whether the roadmap gets a slice for it is open (this research does not edit `roadmap.md`).
   - **User decision 2026-11-19 (1a, scope in M-5):** all three stages in M-5 — local read server, production endpoint with OAuth, and the cash operation write tool. This supersedes the "M-5 split" in the Summary and the stage timing in the Recommendation (stage order 1 → 2 → 3 stays). Consequences for the plan: decision 6 leans to extraction (no reason to duplicate until January), `laravel/mcp` goes to `require` within M-5, and production changes (Passport tables and keys, Cloudflare rules, the entry-validation refactor) land in the same window as S-40, S-41 and S-43, so the plan needs a deploy order and a cut-off date before 31 December.
   - **Decisions 2–9 were settled during `/10x-plan` on 2026-11-19;** see the Key Decisions table in `plan-brief.md` (Passport without a Sanctum stage, both dependencies with ADR-0110, month group sums ported to PHP, no year fallback, extraction, write tool with three kinds, key table, `MCP_WRITE_ENABLED`, local server in the user's own config). Production deploys moved before ~15.12 (option A), which supersedes the "after M-5" timing in the Recommendation.
2. **Auth for the remote endpoint:** Passport OAuth directly (recommended; the only documented path to the phone for an individual account), or a Sanctum token first? A one-minute check informs it: does Customize > Connectors > Add custom connector on claude.ai show a "Request headers" section for this account?
3. **Dependencies:** approve `laravel/mcp` in `require-dev` now (stage 1, instead of relying on Boost's transitive copy) and in `require` plus `laravel/passport` later (stage 2), each with an ADR.
4. **Month Category Group sums:** port the ADR-0079 rule to PHP for the MCP answer (with a parity test against `month-plan-groups.ts` and an ADR amendment), or answer month questions per Category only and give Category Groups for the year only (`BudgetPlanSummary::groupsOfType`)?
5. **Year without a Budget in execution (around the year turn):** answer "Budget 2027 is a draft/missing" with balances only (recommended, Dashboard behaviour), or fall back to the previous year like the sidebar (ADR-0075)?
6. **Extraction vs duplication in stage 1:** extract the small readers (current-month To Spend wiring, operation row shaping, month timing) from controllers into `app/Support` during stage 1 (touches production code paths during M-5), or duplicate them in `app/Mcp` until January and extract then?
7. **Write tool:** read/write split by token or scope, or rely on per-tool approval in the Claude clients for a single user? Idempotency key column (migration, glossary, ADR) or a non-idempotent tool that always asks? Expense only, or Income and refunds on a cash account too?
8. **Data of the local variant:** dev seed data, or a production snapshot restored locally (real numbers, needs the restore steps)?
9. **Where the local server is registered:** an entry in the committed `.mcp.json` (starts in every worktree session) or the user's own Claude Code/Desktop config only?

## Open Questions

- Cloudflare panel settings (Bot Fight Mode, WAF, cache rules) for `budget.kostecki.dev` are not recorded in any repo; they decide whether Anthropic's egress reaches `/mcp` unchallenged.
- Whether this claude.ai account has the beta Request headers section (decides if a static token could ever work outside Claude Code).
- Whether a custom connector can be added from inside the mobile app; the docs describe adding on web/Desktop and using on mobile.
- Passport's `/oauth/authorize` with Fortify login, 2FA and passkeys, and redirect back to the consent screen: not tested.
- Whether hosted Claude apps auto-approve `readOnlyHint` tools of a custom (non-directory) connector.
- The production-safety inference for stage 1 (provider absent with `--no-dev`, so `routes/ai.php` and `app/Mcp` never load) should be confirmed in the plan, e.g. by a production-image build run.
- Opcache state and the VPS RAM pool are not verified (`context/foundation/infrastructure.md:89` leaves the pool undocumented).
