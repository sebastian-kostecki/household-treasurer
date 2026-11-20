<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Production Error Tracking

- **Plan**: context/changes/production-error-tracking/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5, 6, 7
- **Date**: 2026-11-20
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated rows re-run in this review: 233 tests on PostgreSQL (policy, context, config, server, tunnel, relay, CsvImport, ApproveMonthPlan, ChangeMonthPlan, Expense, Income) passed; `composer show sentry/sentry-laravel` = 4.31.0; vitest `error-tracking.test.ts` 11/11; `vendor/bin/sloppy ci` no HIGH (3 medium, 3 low). `boost:index-rules` and the full `composer ci:check` were not re-run here (the latter passed at 26d7d243). Manual rows 1.3, 2.4, 3.4-3.7, 4.4, 5.5, 5.6, 6.3, 7.2-7.5 are unchecked, which is correct: they belong to the owner.

Plan drift (minor, all justified or harmless): no MISSING items. DRIFT: `MonthPlanItemsRefused` reports empty id lists at `ApproveMonthPlan.php:77` and `ChangeMonthPlan.php:61` (the `partitionAmounts() === null` branch); the interest-validator row refusal has the fixed rule `capital_movement_interest` (`ImportFinancialOperations.php:164`); ntfy Title is RFC 2047 encoded (tested); `data_collection` is `null` rather than absent; browser options have no `sendDefaultPii` because `@sentry/vue` 11 lacks it (`dataCollection.userInfo=false` instead). EXTRA: `check()` on three import validators, `ImportWriteRefused` helpers, `ERROR_ALERT_ISSUE_URL_PREFIX`, deploy `sentry:test` only warns, `BackupSignalsTest` adapted.

## Findings

### F1 — Exception messages and log breadcrumbs reach Bugsink unscrubbed

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/ErrorTracking/SentryEventScrubber.php:30-36, config/sentry.php:46
- **Detail**: The scrubber removes request data and frame vars but leaves `exception.values[].value` and breadcrumbs. Laravel's `QueryException` message contains the SQL with bindings substituted, so a constraint violation can carry an amount or description. `breadcrumbs.logs => true` turns log messages and their context into breadcrumbs. `sql_bindings=false` covers only SQL breadcrumbs. ADR-0112 documents the message residual, but the plan's "Desired End State" says request bodies and frame args never leave; amounts are the privacy goal. No test covers either path.
- **Fix**: In `beforeSend`, redact `exception.values[].value` for `QueryException` and `PDOException` (keep type and SQLSTATE), and set `breadcrumbs.logs` to false (or clear breadcrumb `data`). Add a test that throws a `QueryException` with an amount binding and logs a message with an amount.
  - Strength: Closes the only identified path by which amounts can reach the tracker; same scrubber, same test harness.
  - Tradeoff: Redacted QueryException messages are less useful for debugging; log breadcrumbs lost.
  - Confidence: HIGH — read in the scrubber (no touch of exceptions or breadcrumbs) and `config/sentry.php:46`; the QueryException message format was taken from vendor code by the reviewer, not run.
  - Blind spot: Other exception classes embedding user input in messages (e.g. validation of free text) are not enumerated.
- **Decision**: FIXED via Fix now (scrubber redacts QueryException/PDOException values keeping SQLSTATE; `breadcrumbs.logs` = false; test added; 13 tests green)

### F2 — Relay secret and ntfy topic can leak into Bugsink and logs on relay failure

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/ErrorTracking/SentryEventScrubber.php:48-50, app/Http/Controllers/ErrorAlertRelayController.php:77-89, app/Support/ErrorTracking/NtfyAlert.php
- **Detail**: The scrubber keeps the request URL path, which for the relay is `/error-tracking/alerts/<ERROR_ALERT_SECRET>`; any 5xx there (the intended ntfy failure) sends the secret to Bugsink. A `ConnectionException` from ntfy is reported unchanged and its cURL message normally includes the full ntfy URL, which is the topic secret (the topic name is the secret, `error-tracking.md`). `config/error-tracking.php` says the topic is never logged. Bugsink is behind Access, but the log and the stderr copy are not.
- **Fix**: In the scrubber replace the segment after `/error-tracking/alerts/` with `{secret}`; in `NtfyAlert` catch `ConnectionException` and rethrow a fixed-message exception without the original as `previous`. Add tests for both.
  - Strength: Small, local; matches how the tunnel already treats forwarding failures.
  - Tradeoff: Loses the cURL detail for ntfy outages (status and fixed message remain).
  - Confidence: MED — URL path retention verified in code; the cURL message content is inferred from Guzzle behaviour, not reproduced.
  - Blind spot: Traefik and access logs also contain the secret in the URL (outside the app).
- **Decision**: FIXED via Fix now (scrubber masks the relay secret in the URL path; `NtfyAlert` throws fixed-message `NtfyUnreachable` without previous; 2 tests; green)

### F3 — Tunnel size limit is checked after the body is in memory

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/ErrorTunnelController.php:27-31
- **Detail**: `strlen($request->getContent())` runs after PHP has read the whole body, so the 256 KB cap is only as strong as the web server's body limit; the throttle is the only other guard on this unauthenticated route.
- **Fix**: Check `Content-Length` first and return 413 before `getContent()`; confirm the nginx/Caddy body-size limit in front of PHP.
- **Decision**: FIXED via Fix now (413 on a declared Content-Length over the limit before `getContent()`; test; green). Still to confirm: the web-server body limit in front of PHP on halina175

### F4 — Rate limiters key on `$request->ip()`; a shared edge IP could starve the real webhook

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Providers/AppServiceProvider.php (error-tunnel, error-alerts limiters), routes/error-tracking.php
- **Detail**: Both limiters use one bucket per resolved IP. The alert route is reached from Bugsink over `errors-treasurer`, so its IP is the Bugsink container (fine), but wrong-secret probes from the public side share the same per-IP bucket only if they reach the app through the same address; the tunnel's IP depends on `trustProxies` and Cloudflare headers. Not verified in production.
- **Fix**: Verify the resolved IP in production after deploy (one request, check the limiter key); consider keying the alert limiter by secret hash plus IP.
  - Strength: Costs one production check.
  - Tradeoff: A second key adds a little complexity.
  - Confidence: LOW — no evidence of a real problem, only of an unverified dependency.
  - Blind spot: Actual `X-Forwarded-For` handling on halina175.
- **Decision**: ACCEPTED (no code change). Alert route is not starvable: Bugsink calls from its own container IP. After the production deploy, send one request to the tunnel and check the resolved client IP (limiter key) to confirm X-Forwarded-For is honoured

### F5 — Relay input hygiene: missing `D` modifier and prefix without trailing slash

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/ErrorAlertRelayController.php:99, :113
- **Detail**: `preg_match('/^...$/')` accepts a trailing newline; `str_starts_with($url, $prefix)` accepts `https://errors.example.evil.tld/` if the prefix lacks a trailing slash. Both need the secret to exploit.
- **Fix**: Add `D` to both patterns and normalise the prefix to end in `/`.
- **Decision**: FIXED via Fix now (`D` on both patterns, prefix normalised to end in `/`, 2 tests; break-check: test red without `D`)

### F6 — Internal DSN is rendered into every page, and the tunnel forwards raw envelope bytes

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/views/app.blade.php:47-57, app/Support/ErrorTracking/SentryEnvelope.php:266-282
- **Detail**: Guest pages disclose `http://<key>@bugsink:8000/<n>` (public key, but internal host and port), so anyone can forge events through the tunnel within the throttle. The envelope parser trusts the item `length`; the raw body is forwarded after the check, so a parser mismatch with Bugsink could let an extra item through.
- **Fix**: Accept and note in ADR-0112, or render only key and project and let the tunnel supply the real host.
- **Decision**: ACCEPTED via Fix A (documented in ADR-0112 "Browser DSN"; no code change)

### F7 — Missing negative tests for the paths in F1, F2 and F5

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/ErrorTrackingServerTest.php, tests/Feature/ErrorAlertRelayTest.php
- **Detail**: No test puts an amount into an exception message or a log breadcrumb; none covers an oversized or non-string `alert_reason`, a `url` with trailing newline, or a prefix without slash. Also noted from the run: the untested `partitionAmounts() === null` branches (MonthPlanItemsRefused with empty id lists) and `AddRouteContext` on `/mcp`.
- **Fix**: Add the tests alongside the fixes for F1, F2 and F5.
- **Decision**: FIXED via Fix now (tests for the `partitionAmounts() === null` branches in Approve and Change, `AddRouteContext` on `/mcp`; the F1/F2/F3/F5 fixes carry their own tests; a non-string `alert_reason` is already covered by the token fallback)
