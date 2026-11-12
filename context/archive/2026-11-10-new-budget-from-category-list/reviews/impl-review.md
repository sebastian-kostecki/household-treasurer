<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: New Budget From Category List

- **Plan**: context/changes/new-budget-from-category-list/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-10
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Evidence

- PostgreSQL, the four planned Feature files: 100 passed (700 assertions).
- Vitest: 43 files, 476 tests passed. `npm run check`, `npm run check:ui`, `npm run build` (host PHP 8.4.1 from Herd; the default host `php` is 8.3) and `npm run types:check` all passed. `vendor/bin/sloppy ci`: 100/100, 0 findings.
- `composer ci:check` (Sail 8.5 image, PostgreSQL): every npm step and PHPStan passed. The PHP suite failed environmentally because the shared `testing` database was in use by parallel jobs:
  - Run 1: 29 failures with `column "user_id" does not exist` (a foreign schema).
  - Run 2: 2 failures in `PasswordResetTest`.
  - That file alone, twice: 7/7 passed.
- E2E (3.8) could not start: another job holds the `household-treasurer-e2e` container.
- ADR-0068 exists, and the glossary cites ADR-0068 twice. `rg "sourceBudgetId|copyCategories" app` is empty.

## Findings

### F1 — Progress 3.7 is ticked but its grep still finds `source_budget_id`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/BudgetTest.php:1157, :1166 (plan.md:232, Progress 3.7)
- **Detail**: `rg "copyableBudgetSources|CopyableBudgetSource|source_budget_id|copy_source" app resources lang tests` returns 2 hits, both in the unplanned regression test `test_the_submitted_source_budget_id_is_ignored`. The test itself is sound: it proves the planned "submitted `source_budget_id` is ignored" behaviour. The criterion, however, says "returns nothing", yet it is marked `[x]`.
- **Fix**: Keep the test. Add a plan addendum under Phase 3 noting that 3.7 excludes this deliberate regression test (or narrow the grep to `app resources lang`).
- **Decision**: FIXED — plan.md:232 now names the excluded regression test (addendum)

### F2 — "Existing drafts are not filled up" has no test

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/BudgetTest.php:1136
- **Detail**: The Phase 2 test contract (plan.md:147) asks that creating a budget leaves other budgets untouched, "the count and plans of the 2027 draft unchanged". ADR-0068 says existing drafts are not filled up when another budget is created. `test_creating_a_budget_leaves_the_lines_of_other_budgets_and_other_users_categories_alone` only checks that one source line keeps its plan. It never has an existing draft that lacks an active category, so a future "fill up the other drafts" regression would go unnoticed.
- **Fix**: Extend that test with an active category that has no line in the existing earlier draft. Assert that the draft's line count (and plans) are unchanged after creating the next year.
- **Decision**: FIXED — the test adds an active category without a line in the 2025 draft and asserts the draft still has 1 line and no line for that category; BudgetTest on PostgreSQL 59 passed

### F3 — Duplicate-year test kept, though the plan said to delete it

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/BudgetTest.php:1175
- **Detail**: The plan (plan.md:129) lists `test_creating_budget_with_source_still_blocks_duplicate_year` for deletion, because `test_creating_second_budget_for_same_year_is_blocked` (:64) already covers the duplicate year. Instead it was rewritten as `test_creating_a_budget_still_blocks_a_duplicate_year`, which now asserts the same thing as :64. It is harmless but redundant.
- **Fix**: Delete `test_creating_a_budget_still_blocks_a_duplicate_year`, as planned.
- **Decision**: FIXED — duplicate deleted; its only extra assertion (`assertDatabaseCount('budgets', 1)`) moved into `test_creating_second_budget_for_same_year_is_blocked`

### F4 — E2E and the full PHP gate not re-verified in this review

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: N/A (Progress 3.8, 2.4, 3.9)
- **Detail**: Progress claims E2E and `composer ci:check` are green (6486944). In this review:
  - Playwright could not start its web server: a container-name conflict with `household-treasurer-e2e`, owned by another job.
  - The full PHP suite failed on a shared `testing` database with failures unrelated to this change, and those failures passed in isolation.
  - The targeted suites and every other gate step are green.
- **Fix**: When no other job uses the Sail stack, rerun `npx playwright test tests/e2e/sidebar-year-refresh.spec.ts tests/e2e/command-palette.spec.ts` and `composer ci:check` in the Sail image.
- **Decision**: FIXED (partly) — what was re-verified:
  - E2E passed (12 tests, 2.3 min). The worktree `.env` has empty `E2E_USERNAME`/`E2E_PASSWORD`, so the main checkout's values were passed through the environment.
  - `ci:check` was rerun. Every non-PHP step passed (lint, check:ui, PHPStan, Vitest).
  - The PHP suite again failed in unrelated files: 13 in `CsvImportTest`/`MonthPlanPageTest`, a third different set. Both files pass alone (143 tests).
  - Cause: the `testing` database is shared with parallel jobs. The full PHP suite cannot be shown green until it runs alone.
  - Update: after waiting until no other job used the Sail stack (60 s with no extra `sail-8.5/app` containers and no `testing` sessions), `composer ci:check` on PostgreSQL passed in full: 1056 passed, 2 skipped, exit 0. F4 is now fully FIXED.

### F5 — Concurrent duplicate submit ends in a 500 (predates this change)

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/CreateBudget.php:24
- **Detail**: The `unique(user_id, year)` constraint stops duplicates. A race past the FormRequest check, however, throws an unhandled `UniqueConstraintViolationException` (500). `CreateCategory` and `UpdateCategory` catch it, call `report()` and rethrow a `ValidationException`. This behaviour predates the change and is low risk in a single-user app.
- **Fix**: Follow-up change: catch `UniqueConstraintViolationException` in `CreateBudget`, `report()` it and throw a `ValidationException` on `year`, as `CreateCategory` does.
- **Decision**: DEFERRED — queued in `follow-ups/review-fixes.md`

### F6 — Year validation bounds are untested (predates this change)

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: app/Http/Requests/StoreBudgetRequest.php:19-29
- **Detail**: After `source_budget_id` was removed, `year` is the only rule left: required, integer, 2000–2100, unique per user. Only uniqueness has a test (BudgetTest.php:64). Required, integer and the range were untested before this change too.
- **Fix**: Follow-up: add a validation test with a data provider (missing, non-integer, 1999, 2101).
- **Decision**: FIXED — `test_creating_a_budget_rejects_an_invalid_year` with `invalidBudgetYearProvider` (4 cases); BudgetTest on PostgreSQL 63 passed

### F7 — Uncommitted roadmap status for S-20

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/roadmap.md:50, :166
- **Detail**: The working tree sets S-20 from `proposed` to `in-progress` in two places, but the change is not committed. The plan allows only status edits on the roadmap, so the content is fine. The change, though, is already `implemented`/`impl_reviewed`.
- **Fix**: Commit the status edit now (`in-progress`), and set `done` when `/10x-archive` closes the change.
- **Decision**: FIXED — committed with the review fixes; set `done` at `/10x-archive`

## Triage summary

- Fixed: F1, F2, F3, F4, F6, F7 (6); F4 after a full `ci:check` on a quiet database
- Deferred: F5, see `follow-ups/review-fixes.md` (1)
