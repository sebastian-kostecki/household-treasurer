<!-- PLAN-REVIEW-REPORT -->
# Plan Review: S-06: „Do wydania” zamiast `Pozostało w miesiącu`

- **Plan**: context/changes/to-spend-replaces-remaining/plan.md
- **Mode**: Deep
- **Date**: 2026-11-02
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | PASS |
| Plan Completeness | WARNING |

## Grounding

17/17 paths ✓ (2 new test files absent, as planned), 6/6 symbols ✓, brief↔plan ✓, Progress↔Phases ✓.

Verified in code (sub-agent, file:line):
- The current month of a current-year budget is never `none`: `none` comes only from `MonthPlanProposal.php:105-106` (past month without a plan); the `:89-90` path applies only to the previous budget and falls back to the annual average.
- Dashboard tests: `:709` → `-40_000` / `proposal`, `:741` → `0` / `proposal`; the plan already accounts for `current_month` 9 in `:741` (today it asserts `null` at `:756`).
- `select-year` without `destination` must keep redirecting to `budget`: `BudgetYearSwitcher.vue:46` posts to it too, and both `BudgetTest.php:234-236` and `BudgetMonthRemainingTest.php:223-225` assert the redirect.
- `MonthPlanProposal::forBudget` → `$budget->load(...)` has no effect on the controllers (they read `categories()` through the query builder).
- No other class composes `MonthPlanProposal` + `MonthCategoryActuals` + `ToSpendCalculator`; `MonthToSpend` duplicates no pattern.

## Findings

### F1 — Phase 3 does not choose a test file

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 → #3 „Testy”
- **Detail**: „`BudgetMonthResultTest.php` albo nowe przypadki w `ToSpendReadingTest`” left the choice of file to the implementer.
- **Fix**: Name one place for the tests.
- **Decision**: FIXED. After F2: the consistency case goes in `MonthPlanPageTest`, the Budget page states go in `BudgetMonthResultTest`, and `BudgetMonthRemainingTest` is regression only.

### F2 — `ToSpendReadingTest` copies the `canonicalBudget` helper

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Phase 2 → #5 „Testy”
- **Detail**: Copying the private `MonthPlanPageTest::canonicalBudget()` (:370) into a new file leaves the 3730,00 scenario in two places that S-02 would have to change.
- **Fix**: Consistency tests go in `MonthPlanPageTest` on the existing helper; no new `ToSpendReadingTest` file.
- **Decision**: FIXED. `ToSpendReadingTest` is removed from the plan (Phases 2–3, Testing Strategy, Progress 2.1 and 3.1); the Budget page states go to `BudgetMonthResultTest`.

### F3 — Phase 1 manual check has no baseline

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 → Manual Verification (1.5)
- **Detail**: „Ta sama liczba co przed zmianą” had no step that records the baseline; the worktree preview runs on a separate database.
- **Fix**: A „Przed refaktorem” step in the Phase 1 Overview (record the number and rows from the preview); 1.5 refers to that note.
- **Decision**: FIXED
