<!-- PLAN-REVIEW-REPORT -->
# Plan Review: MCP server for the budget (read, then cash entry)

- **Plan**: context/changes/mcp-read-only-server/plan.md
- **Mode**: Deep
- **Date**: 2026-11-19
- **Verdict**: REVISE → SOUND after triage (all 6 findings fixed)
- **Findings**: 1 critical, 3 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding

15/17 paths ✓ (2 wrong directories), 18/18 symbols ✓, brief↔plan ✓, Progress↔Phase ✓. laravel/mcp claims checked against v1.2.1 source, Passport against v13.12.0 source and laravel.com/docs/13.x.

## Findings

### F1 — Concurrent request_id still writes a second operation

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details ("Unique-key race"); Phase 6 §2 Action
- **Detail**: `ManageFinancialOperation::create()` commits in its own `DB::transaction` (`app/Actions/ManageFinancialOperation.php:137`). The plan's flow is "check receipt → createExpense → store receipt". Two concurrent calls both pass the check and both commit an operation and a balance change. The second then hits the unique index on the receipt, and catching `UniqueConstraintViolationException` there leaves its operation in the ledger. Two more problems:
  - On PostgreSQL a failed statement aborts the surrounding transaction, so the "re-select" cannot run inside it.
  - The plan does not order the replay check against validation. If validation runs first, a retry after the Category was archived (or after midnight) fails instead of replaying.

  The planned "concurrent duplicate (PostgreSQL)" test does not say how it forces the race.
- **Fix**: In `RecordCashOperation`, put `createExpense`/`createIncome` and the receipt insert in one outer `DB::transaction`, so the nested create becomes a savepoint. Catch `UniqueConstraintViolationException` outside that transaction, re-select the receipt, return `replayed: true`, and `report()` otherwise. Run the receipt lookup before validation. Test the race by inserting the receipt from a `FinancialOperation::created` listener in the test; assert one operation and one balance change.
  - Strength: The operation and its receipt are atomic; matches `.ai/rules/errors.md` (catch only the expected exception).
  - Tradeoff: The Action owns a transaction around another Action's transaction (savepoint nesting).
  - Confidence: HIGH — the `create()` transaction boundary is visible in code; PostgreSQL abort semantics are standard.
  - Blind spot: None significant.
- **Decision**: FIXED. Critical Implementation Details, the Phase 6 §2 flow (replay check → validation → outer transaction → catch outside) and the Phase 6 §4 race and replay tests were updated.

### F2 — Local-user login has no named hook and also runs on HTTP

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 2 §1 Server and local user; Phase 3 §2 Routes
- **Detail**: Stdio has no middleware. The only place to set the user is the Server's `boot()` (laravel/mcp `Server.php:188-199`), and the plan named no mechanism. One `TreasurerServer` was registered as both `Mcp::local` and `Mcp::web`, and `boot()` runs on every HTTP request too. In the `local` env (worktree preview) with `MCP_LOCAL_USER` set, HTTP calls would also get the local user on the web guard. `auth:api` lowers the risk, but a security boundary resting on middleware order is fragile.
- **Fix A ⭐ Recommended**: separate `LocalTreasurerServer extends TreasurerServer`, overriding `boot()`, registered only by `Mcp::local`.
- **Fix B**: one server, `boot()` guarded by a transport check plus `app()->isLocal()`.
- **Decision**: FIXED via Fix A. Phase 2 §1 and §4, Phase 3 §6 (HTTP test in `local` with `MCP_LOCAL_USER`) and the brief's Architecture paragraph were updated.

### F3 — The local server command cannot reach the database

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Desired End State, item 1; Phase 2 §5 README
- **Detail**: `claude mcp add treasurer --scope local -- php artisan mcp:start treasurer` runs on the host. The main checkout has `DB_HOST=pgsql`, which does not resolve outside Docker (checked with getent).
- **Fix**: Start the server through `docker compose … exec -T -u sail laravel.test php artisan mcp:start treasurer`; the README notes the host alternative (`DB_HOST=127.0.0.1 DB_PORT=5434`).
- **Decision**: FIXED

### F4 — MCP_WRITE_ENABLED does nothing until config is re-cached

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 6 §5 Release; Manual 6.4; plan-brief "Read vs write"
- **Detail**: `deploy.sh` runs `config:cache` (`scripts/deploy.sh:83`), so editing `.env.prod` does nothing until the cache is rebuilt, including the emergency shut-off.
- **Fix**: Phase 6 §5, `infrastructure.md` and manual check 6.4 require `scripts/artisan.sh config:cache` after every change; the brief row was updated.
- **Decision**: FIXED

### F5 — laravel/mcp details the implementer would have to guess

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Key Discoveries; Phase 3 §2; Phase 6 §3–4
- **Detail**:
  - `config/mcp.php` was already publishable in 1.1.2, and `allowed_origins` is not in the published file.
  - The DCR throttle has two documented variants (a group around `oauthRoutes()`, or `registration: false` plus your own route).
  - `_meta` is set through the `$meta` property, and there is no assertion for it.
- **Fix**: The plan names the throttle group, adds `allowed_origins => []` by hand, sets `$meta` as a property and tests `_meta` through `toArray()`.
- **Decision**: FIXED

### F6 — Two paths point at the wrong directory; new files not marked new

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §3–4; Phase 6 §3
- **Detail**:
  - `AuthKitchenSinkController` is in `app/Http/Controllers/Dev/`.
  - `CategoryChoiceOptions` is in `app/Support/CategoryList/`.
  - `app/Http/Responses/` does not exist.
- **Fix**: Paths corrected; `LoginResponse`/`TwoFactorLoginResponse` marked as new classes bound in `FortifyServiceProvider::register`.
- **Decision**: FIXED
