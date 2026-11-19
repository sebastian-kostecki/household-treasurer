<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: MCP server for the budget (read, then cash entry)

- **Plan**: context/changes/mcp-read-only-server/plan.md
- **Scope**: Full plan (completed phases only; Phase 4 waits for the production deploy, its docs were checked for drift but the phase is not counted)
- **Reviewed phases**: 1, 2, 3, 5, 6
- **Date**: 2026-11-19
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 8 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Evidence for Success Criteria: plan-scoped tests on SQLite (Mcp, Auth, RecordCashOperation, Expense, Income, edit/delete, hand-entry, parity, readers) 215 passed, 2 skipped; `vendor/bin/sloppy ci` exit 0, nothing at high (3 medium outside the change, 3 low SL217 in the published Passport migrations); full `composer ci:check` on PostgreSQL exit 0 (vp check, check:ui, PHPStan no errors, 2124 tests passed, 4 skipped). The WARNING comes only from the test-contract gaps in F8. Manual rows 1.6, 2.4, 2.5, 3.4, 3.5, 5.4, 6.3–6.5 are unchecked and pending; none is ticked without evidence.

## Findings

### F1 — The `mcp:use` scope is never enforced on `/mcp`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: routes/ai.php:25
- **Detail**: The route stack is `auth:api, throttle:mcp, LogMcpCall`. Passport's `TokenGuard` validates the JWT and loads the user but checks no scope. The DCR grant does nothing: `OAuthRegisterController::grantMcpScope` (vendor/laravel/mcp/src/Server/Http/Controllers/OAuthRegisterController.php:118-120) returns early because `oauth_clients` has no `scopes` column, and `Client::hasScope()` (vendor/laravel/passport/src/Client.php:214) is then true for every scope. A token issued without `scope` passes `/mcp`. Today the only clients are public PKCE clients from DCR that need consent and the Passport JSON API is off, so exposure is low, but any future Passport token of the user opens the budget. Tests always use `Passport::actingAs($user, ['mcp:use'])`, so nothing covers a token without the scope. ADR-0110 and the plan say "one scope `mcp:use`", which reads as enforced.
- **Fix A ⭐ Recommended**: Add `CheckToken::using('mcp:use')` after `auth:api` and a test where `Passport::actingAs($user, [])` gets 403.
  - Strength: Makes the documented scope real; defence in depth before the endpoint goes public.
  - Tradeoff: If a Claude client requests a token without `scope`, the connector breaks with 403.
  - Confidence: MED — discovery advertises `scopes_supported: [mcp:use]` (Registrar.php:174,188) and MCP clients normally request it, but not verified against claude.ai.
  - Blind spot: Which scope claude.ai and Claude Code actually request; manual check 3.4 in the preview would show it before production.
- **Fix B**: Keep `auth:api` only and record in ADR-0110 that any Passport token of the user is accepted, because DCR clients are the only clients.
  - Strength: No risk of breaking the connector.
  - Tradeoff: The guarantee depends on never adding another Passport client type.
  - Confidence: HIGH — matches current behaviour exactly.
  - Blind spot: A later change adding personal access tokens would silently widen access.
- **Decision**: FIXED via Fix A — `CheckToken::using('mcp:use')` after `auth:api`, plus `Passport::defaultScopes(['mcp:use'])` so a client that omits `scope` still works; test: a token without the scope gets 403 (break-check red).

### F2 — Consent page promises a revoke and an approval the server does not provide

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: lang/pl/ui.php:123-124
- **Detail**: `outro` says "Dostęp możesz cofnąć w każdej chwili", but the app has no screen, route or command that revokes OAuth tokens or clients (the JSON API routes are off; only the prune command touches them). Removing the connector in claude.ai does not revoke the 30-day refresh token on the server; today revoking means editing the database or rotating the keys. `scope_write` says each operation is recorded "po Twoim zatwierdzeniu", but that approval is only the `anthropic/requiresUserInteraction` hint, honoured by Claude clients and not enforced by the server. Phase 3 asked only for the client name, what access means, and the two buttons.
- **Fix A ⭐ Recommended**: Change the copy: drop the revoke promise and phrase the write line as "Claude poprosi Cię o potwierdzenie każdej operacji".
  - Strength: Two string edits; the page states only what is true.
  - Tradeoff: No way to revoke a single connector remains a gap.
  - Confidence: HIGH — strings are in one file, covered by the kitchen sink and `check:ui`.
  - Blind spot: Other Claude clients' handling of the approval hint.
- **Fix B**: Add a "Połączone aplikacje" settings list that revokes a client's tokens.
  - Strength: Keeps the promise and gives a real kill switch per connector.
  - Tradeoff: New screen, routes, tests and kitchen-sink states — outside the plan and M-5's freeze.
  - Confidence: MED — Passport has the models, but the UI is new work.
  - Blind spot: Not planned; would need its own plan phase or change.
- **Decision**: FIXED via Fix A — revoke promise removed from `outro`; `scope_write` now says Claude asks for confirmation of each operation.

### F3 — A reused `request_id` with different data silently returns the first operation

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/RecordCashOperation.php:47-53
- **Detail**: The replay matches only `(user_id, request_id)`. If Claude reuses an id for a new entry (a model mistake, not a retry), the call returns the earlier operation with `replayed: true` and writes nothing; the new expense is lost while the answer looks like success. The plan specified the replay-before-validation order but not a payload check.
- **Fix A ⭐ Recommended**: On replay, compare `kind`, the parsed `amount` and `category_id` (and `account_id`/`operation_date` when given) with the stored operation; a mismatch is a validation error on `request_id` ("already used for another operation").
  - Strength: No schema change; keeps the replay-after-midnight and replay-after-archive behaviour because only given fields are compared.
  - Tradeoff: A few lines and tests in the Action; replay now parses the amount before validation.
  - Confidence: HIGH — the stored operation carries all compared fields.
  - Blind spot: Description/counterparty differences are ignored on purpose.
- **Fix B**: Store a hash of the normalised input in `mcp_operation_requests` and refuse a mismatch.
  - Strength: Compares the whole payload exactly.
  - Tradeoff: Migration change and a hash definition to maintain; a retry with a reworded description is refused.
  - Confidence: MED — stricter than a retry needs.
  - Blind spot: How Claude rephrases retries.
- **Decision**: FIXED via Fix A — replay compares kind, amount and Category (account and date when given) with the stored operation, on both replay paths; mismatch is a validation error on `request_id` (5 data sets, break-check red).

### F4 — Static methods called directly on traits are deprecated in PHP 8.1+

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/RecordCashOperation.php:131-132, 142-143 (also tests/Feature/HandEntryValidationWithoutHttpTest.php:97,122,128)
- **Detail**: `ExpenseValidationRules::expenseStoreRulesFor`, `IncomeValidationRules::incomeStoreRulesFor`, `::expensePayloadFrom`, `::incomePayloadFrom` are called on `App\Concerns` traits. PHP 8.4.1 emits "Calling static trait method … is deprecated" (reproduced). Laravel routes it to the null `deprecations` channel, so it is silent until a PHP version makes it an error. The precedent the plan cited (`IndexOperationsRequest::listRules`) is a class method.
- **Fix**: Move the four pure functions into classes (e.g. `App\Support\ExpenseEntryRules`, `IncomeEntryRules`), have the traits delegate, and call the classes from the Action and the test.
- **Decision**: FIXED — rules and payload builders moved to `App\Support\ExpenseEntryRules` / `IncomeEntryRules`; the traits delegate.

### F5 — Expired tokens will flood the error log, while the `mcp` log cannot see refused calls

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: bootstrap/app.php:63-67, routes/ai.php:25, app/Http/Middleware/LogMcpCall.php:29
- **Detail**: Passport's `TokenGuard::getPsrRequestViaBearerToken` calls `report($e)` on every `OAuthServerException` (vendor/laravel/passport/src/Guards/TokenGuard.php:180), and nothing marks it as not reportable. With 1-hour access tokens every call with an expired token before a refresh writes an error with a stack trace to the production log (`LOG_LEVEL=error`). At the same time `LogMcpCall` runs after `auth:api` and `throttle:mcp`, so 401 and 429 never reach the `mcp` log, and tool errors show `status 200` because laravel/mcp puts `isError` in the JSON-RPC body.
- **Fix**: Add `$exceptions->dontReport(OAuthServerException::class)`, move `LogMcpCall` before `auth:api` so 401/429 are logged (user id null), and log `result.isError`/`error.code` from the body (codes only), with tests.
  - Strength: Expected auth failures stay visible in the `mcp` log as info lines instead of error stack traces; tool failures become visible.
  - Tradeoff: A malformed or forged token is no longer a stack trace in the error log — only a 401 line.
  - Confidence: HIGH — library behaviour read in vendor; middleware order is in one line.
  - Blind spot: Volume of 401s from Claude's refresh pattern is unknown until production.
- **Decision**: FIXED — `dontReport(OAuthServerException)`, `LogMcpCall` first in the `/mcp` stack (401/403/429 logged), `tool_error` and `error_code` fields; tests incl. invalid bearer token not reported (break-check red).

### F6 — New schedule entries: wrong ADR, split comment, failures reach no one

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: bootstrap/app.php:41-43
- **Detail**: The comment cites ADR-0108 (report years range) instead of ADR-0110, missed in the renumbering. The block sits between the backup comment ("Backups run in maintenance mode too…") and `$backupCommands`, so that comment now describes the Passport lines. Unlike the backups, neither entry has `environments(['production'])`, `sendOutputTo` or `emailOutputOnFailure`.
- **Fix**: Move the block after the backup loop, cite ADR-0110, and add `environments(['production'])`, `sendOutputTo(storage_path('logs/schedule-…log'))` and `emailOutputOnFailure(config('backup.notifications.mail.to'))` like the backups.
- **Decision**: FIXED — block moved after the backup loop, ADR-0110, `environments(['production'])`, output files and failure mail.

### F7 — Server instructions still say "Read-only" with the write tool registered

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Mcp/Servers/TreasurerServer.php:20
- **Detail**: `#[Instructions('Read-only access to the household budget…')]` while `$tools` includes `AddCashOperationTool` (:33), registered when `MCP_WRITE_ENABLED` is on. With the switch on, Claude is told the server is read-only and may refuse to record.
- **Fix**: Reword to "Access to the household budget of one user: reading, and recording a cash operation when `add-cash-operation-tool` is listed."
- **Decision**: FIXED — instructions describe reading and recording one cash operation when the tool is listed.

### F8 — Gaps in the planned test contract

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/Mcp/ListCategoriesToolTest.php:81, tests/Feature/Mcp/RecentOperationsToolTest.php:83, tests/Feature/Mcp/OAuthTest.php:13
- **Detail**: Phase 2 asks every tool to cover "no Budget and draft Budget for the App Day year"; ListCategories and RecentOperations have only the no-Budget case (AccountBalances does not depend on a Budget). Discovery is tested only at `/.well-known/oauth-protected-resource`; the nested `/.well-known/oauth-protected-resource/mcp`, which `WWW-Authenticate` points to and Phase 4 check 4.1 curls, has no test.
- **Fix**: Add a draft-Budget case to both tool tests and a test that the nested path returns `resource = <APP_URL>/mcp`.
- **Decision**: FIXED — draft-Budget cases for ListCategories and RecentOperations, nested discovery path test.

### F9 — An oversized amount ends in an internal error instead of a validation message

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/RecordCashOperation.php:109
- **Detail**: `MinorUnits::fromMajorInput("99999999999999999999")` overflows to float and the `?int` return throws `TypeError`; values just below can overflow the `bigint` balance. Both are reported (not hidden), but Claude gets "internal error". The same gap exists in the HTTP form, so it is pre-existing in the parser.
- **Fix**: Add an upper bound (e.g. amount ≤ 1 000 000 000 zł) in `validatedEntry` with a validation message.
- **Decision**: FIXED — amount with more than 10 integer digits or above 1 000 000 000,00 zł is a validation error on `amount` (break-check: TypeError again without the digit check).

### F10 — Small cleanups and doc drift

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: several (below)
- **Detail**:
  - `context/foundation/roadmap.md` `### S-47` says `Status: planning` while the At a glance row says `in-progress`.
  - Plan Phase 5 names `payloadFrom`; code has `expensePayloadFrom`/`incomePayloadFrom` (justified: both traits are used together in `UpdateExpenseRequest`).
  - `config/mcp.php` keeps the published stub's `clients` (github/linear/playwright) — unused.
  - `RecordCashOperation::read()` (:203-205) uses `findOrFail` for rows it just wrote; `ModelNotFoundException` is not reported, so a broken invariant would be invisible (`.ai/rules/errors.md`).
  - `MonthPlanTool` answers a year without a Budget in execution with `Response::error` text, while the other tools return a structured answer with `app_day` and `budget_phase`.
  - `PasskeyVerify.vue:23` checks only the pathname of the redirect; adding `origin === window.location.origin` costs nothing.
  - `tests/TestCase.php:23` depends on `Tests\Feature\Mcp\Support\PassportKeys`; move it to `tests/Support`.
  - `DashboardController::show` loads the year's Budget and `CurrentMonthReading::forUser` loads it again (one extra query).
  - `PruneOAuthClientsCommand` writes with `DB::table` inline; other commands delegate to `app/Actions`.
  - Deleting an operation in the UI cascades its receipt, so a late retry with the same `request_id` records it again — acceptable, worth one line in ADR-0110.
- **Fix**: Batch these as one cleanup commit (roadmap status, plan names, config stub, `InvariantViolation` in `read()`, structured MonthPlan refusal, origin check, helper move); leave the extra Dashboard query and the prune Action as optional.
- **Decision**: FIXED (partly) — roadmap S-47 status, plan note on F4 names, `config/mcp.php` `clients` emptied (ClientManager reads the key), `InvariantViolation` in `read()` and replay, structured MonthPlan answer for a year not in execution, origin check in PasskeyVerify, `PassportKeys` moved to `tests/Support`, ADR-0110 lines on scope, token logging and receipt cascade. Skipped as optional: extra Dashboard Budget query, prune command as an Action.

## Triage

2026-11-19, user's decision: fix by the recommendations. Fixed: F1 (Fix A), F2 (Fix A), F3 (Fix A), F4, F5, F6, F7, F8, F9, F10 (partly; two optional items skipped). Break-checks went red for F1, F3, F5 and F9 and the files were restored before the commit.
