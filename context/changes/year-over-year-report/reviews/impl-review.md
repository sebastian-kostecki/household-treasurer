<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Rok do roku w Raportach

- **Plan**: context/changes/year-over-year-report/plan.md
- **Scope**: Full plan (completed phases only; Phase 4 pending item 4.4 — E2E on the mock database)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-11
- **Verdict**: APPROVED
- **Findings**: 0 critical, 0 warnings, 7 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Verification run

- `composer ci:check` on PostgreSQL in the worktree: 1278 passed, 2 skipped. The first run on the shared `testing` database had 17 failures (`relation "category_rhythms" does not exist`) because another session ran `ci:check` on the same database at the same time. The rerun on a dedicated database `testing_yoy` was green.
- `NfrPerfReportTest` (two years × 10,000 operations) on PostgreSQL: passed.
- `vendor/bin/sloppy ci` (in the Sail image): 0 findings.
- `npm run test` for report-difference and report-chart: 11/11. `npm run check:ui`: clean. `npm run types:check`: clean.
- `npm run build`: passed with a PHP 8.5 shim on PATH. Host PHP 8.3 fails Composer's platform check, which is a known agent-shell limitation, not a code problem.
- Unplanned changes: `roadmap.md` and `change.md` (status changes only). The `CommandPaletteCategoriesTest` assertion moved from 2025 to 2026; this is justified because the requirement itself changed in ADR-0075.

## Findings

### F1 — `has_operations` means two different things

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/PeriodReport.php:82
- **Detail**: At report level `has_operations` is `$rows !== []`, so it is true when only the previous year has operations. On rows and groups the same name means "has operations in the current period". The plan asks for this and the tests cover it (PeriodReportTest), but neither `ReportShape` nor `types/reports.ts` documents it, so a later reader can mistake one meaning for the other.
- **Fix**: Add a one-line PHPDoc and TS comment on the report-level `has_operations` ("the report has at least one row, from either range").
- **Decision**: FIXED — class PHPDoc in PeriodReport.php and the TS comment on `PeriodReportData.has_operations` now say what each meaning is.

### F2 — Partial-period note lives in ReportStatPanel, not Reports.vue

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/ReportStatPanel.vue
- **Detail**: Plan Phase 3 §4 puts the `<Alert variant="note">` in `Reports.vue`, under the panel. The implementation renders it inside `ReportStatPanel` below `StatPanel`. The page looks the same, but every user of the panel now gets the note, and the plan no longer describes the code.
- **Fix**: Record this as a plan addendum (the note belongs to the panel because it explains the panel's numbers).
- **Decision**: FIXED — plan addendum added (plan.md, "Addendum"); code unchanged.

### F3 — Kitchen-sink scenarios merged (5 instead of 7)

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/lib/reports-fixtures.ts:189-345
- **Detail**: All seven planned states are present. `compared-one-side-only` holds three of them: a category only in the previous year, one only in the current period, and a group made only of previous-year categories. The archived category with a comparison sits inside `compared-month-closed`. Coverage is complete, but the split differs from the plan.
- **Fix**: Accept and note it in the plan addendum together with F2.
- **Decision**: FIXED — accepted; recorded in the plan addendum.

### F4 — `is_in_progress` computed twice

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/ReportComparisonPeriod.php:49-50
- **Detail**: `$today->year === $budget->year && ($month === null || $today->month === $month)` repeats the same expression from `ReportController.php:53-54`. If one copy changes and the other does not, the note and cut would disagree with the "okres w toku" caption.
- **Fix**: Extract one static helper (e.g. on `ReportComparisonPeriod` or a small period helper) and call it from the controller.
- **Decision**: FIXED — `ReportComparisonPeriod::isInProgress()` used by `resolve()` and `ReportController`; ReportsPageTest, ReportComparisonPeriodTest, PeriodReportTest green on PostgreSQL (53 passed).

### F5 — `--remove` guard checked outside the delete transaction

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/seeders/PreviousYearMockSeeder.php:67-94
- **Detail**: The "no unmarked operation" check runs before `DB::transaction`, and the delete then removes all operations of the budget. An operation added between the check and the delete would be lost. This is a dev-only tool with a local/testing guard, so the risk is negligible.
- **Fix**: Move the unmarked-operation check inside the `DB::transaction` closure (or delete only marker operations, so the FK restricts anything else).
- **Decision**: PENDING

### F6 — Mock operations are visible to other readings on dev

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/seeders/PreviousYearMockSeeder.php (operation insert)
- **Detail**: Mock rows use the real account, description and counterparty, dated a year earlier and usually before the account's `opening_date`. Readers that query by user or account (`ImportCategorySuggester`, `ImportPairCandidateDetector`, `OperationsFilterOptions`) will see them on dev. Balances are untouched, as planned. PLAUSIBLE: no effect was confirmed in the running app.
- **Fix**: Add one README sentence under the command: the mock also feeds import suggestions and operation filters on dev; use `--remove` before testing imports.
- **Decision**: PENDING

### F7 — Legend key `chart_legend_previous` also labels the current year

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: lang/pl/ui.php:395, resources/js/components/ReportBarChart.vue:72
- **Detail**: The key `reports.chart_legend_previous` ("Rok :year") is used for both legend entries, so its name says less than it does.
- **Fix**: Rename it to `reports.chart_legend_year` in `ui.php` and `ReportBarChart.vue`.
- **Decision**: PENDING
