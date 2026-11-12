<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Ostatnie 12 miesięcy — plan implementacji

- **Plan**: context/changes/rolling-twelve-month-report/plan.md
- **Mode**: Deep
- **Date**: 2026-11-12
- **Verdict**: SOUND
- **Findings**: 0 critical, 3 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

19/19 paths ✓, 6/6 symbols ✓ (`MonthCategoryActuals::forRange`, `ReportCategoryTree::entryNodesOfType`, `BudgetPhase::Execution`, `BudgetYearSwitcher` destination, `SelectedBudgetYear::resolve`, next free ADR 0081), brief↔plan ✓, Progress↔Phases ✓ (1.1–1.7, 2.1–2.11).

Confirmed in code:
- year result = sum of Month Results is already proven (`tests/Feature/PeriodReportTest.php:115-149`), and `BudgetMonthResult` contains nothing beyond `PeriodReport`;
- no N+1 (`BudgetLine::$with = ['category']`, `app/Models/BudgetLine.php:50`);
- `$budget === null` in `ReportController` means a user without Budgets (`SelectedBudgetYear.php:21-48`);
- `BudgetYearSwitcher.vue:36-48` gives `reports` outside `view=trend`;
- Year Close does not change the phase (glossary:24, :155), so "covered = Execution" stays valid;
- the chart legend with a year shows only with a comparison (`ReportBarChart.vue`);
- the command palette, `nav-active.ts` and `global-shortcuts.ts` need no change.

## Findings

### F1 — Fallback „12 mies.” wymaga zmiany SegmentedControl

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §5 (phone paragraph)
- **Detail**: The plan left a condition for implementation: "if it does not fit, „12 mies.” with the full name in `aria-label`". The `SegmentedControl` option is `{ value, label, count? }` (`SegmentedControl.vue:9`) with no per-option aria-label, so this would change a shared component outside the plan. A visible name that differs from the accessible one also breaks WCAG 2.5.3.
- **Fix**: Decide in the plan: the `last-12-phone` scenario checks the fit at 22rem; otherwise „12 mies.” is both the visible and the accessible name, and `SegmentedControl.vue` does not change.
- **Decision**: FIXED (Fix in plan)

### F2 — E2E zielone także bez raportu okna

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 2 §8 (E2E)
- **Detail**: The E2E database is persistent and seeded by hand (README:92-103). `DemoHouseholdSeeder` creates the Budget for the year of seeding, so window coverage depends on the seed date. The first test checked only the heading and the range, which also render in the empty state. Operations added in the spec are dated today, an open month, so they never fall into the window.
- **Fix**: Also assert that either „Wynik 12 miesięcy” or the empty-state title is visible, with no „Wynik roku” and no draft state. Amounts stay with the Feature tests.
- **Decision**: FIXED (Fix in plan)

### F3 — Reguła i kontrakt linku znają tylko miesiąc/rok

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §4 and §7
- **Detail**: `.ai/rules/reports.md:27` limits the link range to "the report's month or the whole Budget year", and the plan did not change that sentence. `resources/js/ui-contract.test.ts:529-546`, which the plan did not list, guards only `reportCategoryOperationsQuery` in `ReportCategoryRow.vue`.
- **Fix**: §7 changes the sentence at :27; §4 adds `ui-contract.test.ts` with an assertion on `reportRangeOperationsQuery`.
- **Decision**: FIXED (Fix in plan)

### F4 — Zły dowód na „operacje tylko w realizacji”

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State, Key Discoveries, brief Open Risks, ADR-0081 contract
- **Detail**: `ManageFinancialOperation.php:236` guards only the join-at-first-use branch. The real guard is `app/Support/CategoryList/OperationCategoryChoice.php:48-50`, which covers hand entry, CSV import and rebuild. Restore (`RestoreUserData.php:228`, `:784-813`) checks neither the phase nor the year of the date.
- **Fix**: Replace the citation in the plan and the brief, and record restore as an accepted exception in ADR-0081.
- **Decision**: FIXED (Fix in plan)

### F5 — forWindow przy zerowym pokryciu nie zna użytkownika

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §3–§4
- **Detail**: `PeriodReport` takes the groups' `user_id` from the Budget (`PeriodReport.php:68`). The window carries no user, and with zero coverage `$window->budgets === []`.
- **Fix**: With an empty `budgets`, `forWindow` returns an empty report without queries.
- **Decision**: FIXED (Fix in plan)
