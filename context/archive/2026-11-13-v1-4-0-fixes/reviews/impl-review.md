<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: v1-4-0-fixes Implementation Plan

- **Plan**: context/changes/v1-4-0-fixes/plan.md
- **Scope**: Full plan (completed phases only; Phase 5 left out because 5.4 "Year switcher e2e specs pass" is still `[ ]`)
- **Reviewed phases**: 1, 2, 3, 4, 6
- **Date**: 2026-11-13
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 1 warning, 7 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automated checks run on 2026-11-13: the Feature tests on PostgreSQL (OperationsReturnToList, OperationsFilter, Budget, AlignCategoryCurrentPlan, TranslationKeys) passed, 124 tests. Vitest (operations-list-query, visit-scroll, budget-year-menu, ui-contract) passed, 134 tests. `composer ci:check` in Sail exited 0 (1601 passed, 34 skipped). `vendor/bin/sloppy ci` passed with 1 medium finding, none at high or above. `grep -rn "Zakończ" lang resources/js` found nothing. All manual items in the reviewed phases are ticked, and the diffs show evidence for each.

## Findings

### F1 — CSV Import success toast is still English

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/CsvImportController.php:439-461, tests/Feature/TranslationKeysTest.php
- **Detail**: `importSuccessMessage()` builds the toast with `trans_choice(':count financial operation imported.|…')`, and the same for own transfers and capital movements. None of the 3 keys is in `lang/pl.json`. The plan scoped the guard test to `__()` and `trans()` only, so the guard misses `trans_choice` and the gap stays open. The Overview promised that every server message has a Polish translation. A completed CSV Import, one of the most common writes, still shows an English toast.
- **Fix**: Add `trans_choice` to the regex in TranslationKeysTest, watch it fail, then add the 3 keys to `lang/pl.json` with the Polish forms `one|few|many` (for example „:count operacja zaimportowana.|:count operacje zaimportowane.|:count operacji zaimportowanych.”).
- **Decision**: FIXED — guard scans `trans_choice`, seen red with the 3 keys; Polish one|few|many forms added to `lang/pl.json` (checked for 1, 2, 5, 12, 22, 25).

### F2 — No Feature test for Align with show_archived

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/OperationsReturnToListTest.php:176, tests/Feature/AlignCategoryCurrentPlanTest.php
- **Detail**: Testing Strategy (plan.md:406) lists "Budget `show_archived` on store/update/destroy/align". Store, update and destroy are tested. Align is not, although the client now sends the flag (`BudgetCategoryRowActions.vue:162-168`). The only proof for Align is manual item 1.7.
- **Fix**: Add one test: Align with `?show_archived=1` redirects to `route('budget', ['show_archived' => 1])`.
- **Decision**: FIXED — `test_aligning_keeps_show_archived_on_the_budget_redirect` in AlignCategoryCurrentPlanTest.

### F3 — A stale filter in list[...] can loop the redirect

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Concerns/RedirectsToOperationsList.php:21, app/Http/Requests/IndexOperationsRequest.php:28-45
- **Detail**: The trait passes the `list` values on without validating them. If the filtered Category, Account or Term deposit no longer exists, the next save redirects to `/operations?category_id=<gone>`. There, `IndexOperationsRequest` fails `exists` and redirects to `url()->previous()`. For an Inertia XHR the Referer is the same failing document URL, so the redirect can loop. The write itself succeeds, but the User sees a network error. A crafted `list[section]=x` fails the same way. This was not reproduced: the reasoning comes from the framework code (`shouldRenderJsonWhen` is only for `api/*` and JSON). It can happen only after a merge or delete in another tab. The same loop already exists for a plain GET with a stale filter.
- **Fix A ⭐ Recommended**: Make `IndexOperationsRequest` drop filter values that fail validation (in `prepareForValidation`, or by treating a failed `exists` as no filter) instead of redirecting back.
  - Strength: Fixes the root cause for both the write redirect and a plain GET with a stale URL. The trait stays simple.
  - Tradeoff: A URL the User typed by hand silently loses an invalid filter instead of showing an error. The Operations validation tests need a review.
  - Confidence: MED — the loop has not been reproduced in a browser.
  - Blind spot: Whether Inertia or the browser stops the loop earlier than expected; whether any test asserts the current 302 on an invalid filter.
- **Fix B**: Validate the kept `list` values inside `RedirectsToOperationsList` against the `IndexOperationsRequest` rules and drop the failing ones.
  - Strength: Limited to the new code path of this change.
  - Tradeoff: Duplicates the rules in another place (see F5). A plain GET with a stale filter still loops.
  - Confidence: MED — same unverified premise.
  - Blind spot: Same as A.
- **Decision**: FIXED via Fix B (changed from A) — the loop was confirmed (GET `/operations?category_id=999999` with the same Referer → 302 to itself). Fix A would have changed 4 tests that pin "a foreign filter is rejected", so the trait now validates `list` with the shared `IndexOperationsRequest::listRules()` (no copied rules) and drops rejected keys. New red→green test `test_list_values_the_operations_list_would_reject_are_dropped`; the fixture `category_id => 7` (a non-existent Category) in the first return-to-list test became `$line->category_id`. A plain GET with a stale filter still loops (pre-existing, outside this change).

### F4 — visit-scroll.test.ts imports from vitest

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/visit-scroll.test.ts:2
- **Detail**: `.ai/rules/frontend-toolchain.md:13` requires `vite-plus/test`. This is the only test file in `resources/js` that imports `'vitest'`. It works only because vitest happens to be in `node_modules`.
- **Fix**: Change the import to `from 'vite-plus/test'`.
- **Decision**: FIXED — import from `vite-plus/test`.

### F5 — Operations list keys are listed in four places

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Concerns/RedirectsToOperationsList.php:15-19, app/Http/Controllers/OperationsController.php:137-140, app/Http/Requests/IndexOperationsRequest.php, resources/js/lib/operations-list-query.ts
- **Detail**: The filter keys and the three page names are repeated in the trait allow-list, in the past-end redirect and in the request rules, with a fourth copy in TypeScript. A new filter means four edits, and a missed one silently drops the filter after a write.
- **Fix**: Hold the filter keys and page names in one PHP constant (for example on `App\Support\OperationsListQuery`) used by the trait and the past-end redirect.
- **Decision**: FIXED — `OperationsListQuery::FILTER_KEYS` / `PAGE_KEYS`, used by the trait, the past-end redirect and `IndexOperationsRequest::filters()`; rule in `.ai/rules/js-operations.md`. The TypeScript mirror stays (noted in the constant's docblock).

### F6 — Past-end redirect: dead condition, runs after all queries

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Controllers/OperationsController.php:135-144
- **Detail**: `lastPage() >= 1` is always true for a `LengthAwarePaginator`, so it is dead code. The redirect is decided only after all three paginators and the row mapping have run. The cost is small, but it adds queries on the redirect path. It also writes `section=financial_operations` explicitly, while the client leaves the default section out of the URL (cosmetic).
- **Fix**: Drop the `lastPage() >= 1` clause. Optionally run the check right after building the active paginator, before the others are mapped.
- **Decision**: FIXED — dead `lastPage() >= 1` dropped; the check was not moved before the other paginators (optional part skipped).

### F7 — Controller redirect helper placed in app/Concerns

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Concerns/RedirectsToOperationsList.php
- **Detail**: Every other trait in `app/Concerns` is a `*ValidationRules` trait for FormRequests. This one is a controller response helper; its siblings with the same role are private `redirectToIndex()` methods in Account, TermDeposit and Category. This breaks no written rule, because "Where things live" in AGENTS.md does not list `app/Concerns`.
- **Fix**: Move it to `app/Http/Controllers/Concerns/`, or leave it and accept `app/Concerns` as the home for shared traits.
- **Decision**: ACCEPTED — the trait stays in `app/Concerns`; `app/Http/Controllers/Concerns` would be a new base folder, which AGENTS.md reserves for approval.

### F8 — `<Link method>` writes bypass the scroll default

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/visit-scroll.ts:19, .ai/rules/js.md:65-67
- **Detail**: Vue `<Link>` always sends `preserveScroll` (its prop defaults to `false`, `@inertiajs/vue3/dist/index.js:1678,1864`). `defaultVisitOptions` therefore treats it as explicit and skips it. Today no `<Link method="post|delete">` writes and stays on the same page (logout leaves), but a future one would jump to the top.
- **Fix**: Add one sentence to the "Writes keep the scroll on the same page" rule: a `<Link method=…>` write must pass `:preserve-scroll` itself.
- **Decision**: FIXED — sentence about `<Link method>` and `:preserve-scroll` added to "Writes keep the scroll on the same page" in `.ai/rules/js.md`.
