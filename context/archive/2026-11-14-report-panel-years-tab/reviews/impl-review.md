<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Zakładka „Lata” w panelu kategorii

- **Plan**: context/changes/report-panel-years-tab/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-14
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 3 observations
- **Triage**: 2026-11-14 — all six FIXED at the user's request ("napraw samodzielnie według rekomendacji")

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Success criteria (run 2026-11-14)

- `composer ci:check` on PostgreSQL in the worktree (`testing_report_panel_years_tab`): PASS. 1770 passed, 2 skipped. The phase 1 server tests and the NFR test are included.
- `npm test`: PASS, 733 tests. `npm run check`: PASS. `npm run check:ui`: PASS. `npm run types:check` (vue-tsc): PASS.
- `composer types:check` (PHPStan): PASS, 0 errors. `vendor/bin/pint --test` on the changed PHP files: PASS.
- `vendor/bin/sloppy ci`: PASS (exit 0). Its one medium finding is in `app/Support/SheetLoad/MonthSheetReader.php`, outside this change.
- E2E `reports.spec.ts`, `reports-category-panel.spec.ts` and `reports-view.spec.ts` (port 8150, `e2e_report_panel_years_tab`): PASS, 35 passed, none skipped.
- Manual 1.5, 2.5–2.7 and 3.5–3.8 are unchecked in Progress and still pending. None of them is falsely checked.
- Unplanned in the diff: `context/foundation/roadmap.md`, S-36 moved to "w toku". This is change bookkeeping and harmless.

## Findings

### F1 — `opens_panel` takes the second-to-last column as T − 1 even when the years have a gap

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/ReportYears.php:195-198
- **Detail**: `opensPanel()` adds `$columns[count-2]` without checking that it equals `$target - 1`. The columns are only the years with a Budget in execution (`ReportYearsRange::resolve`), so a year without a Budget, or in draft, leaves a gap. `ReportComparisonPeriod::resolve` compares only with `year - 1` in execution. Example: columns [2024, 2026], and a category has operations only in 2024 before day D. It gets `opens_panel: true`, but the 2026 report has no row for it, so `requestedCategoryId()` returns `null` and the panel opens on a different category without saying so. This is the exact risk the plan names in "Critical Implementation Details" ("T − 1 liczy się tylko, gdy jest kolumną"). The tests in `ReportYearsTest` use only consecutive years.
- **Fix**: Count the previous column only when `$columns[count-2] === $target - 1`, and add a case with a gap (2025 in draft) to the test that compares `opens_panel` with `PeriodReport::forYear`.
- **Decision**: FIXED — `opensPanel` counts T − 1 only when that year is a column. Red→green: the test 'a column before the target year that is not the year before it does not count for the flag' (`ReportYearsTest`).

### F2 — A future year in execution in the "Lata" tab: undocumented fallback and "Zmiana od undefined"

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Support/ReportCategoryPanel.php:47-56,91; resources/js/components/ReportCategoryPanel.vue:87-93
- **Detail**:
  - **What the plan assumed:** every category in the panel has a row in `ReportYears` (Key Discoveries), and when one is missing `forYear` throws `LogicException`. The plan does not consider a future year in execution (for example 2027 started in November 2026). Such a year is not a column of the "Lata" report.
  - **What the code does instead:** it falls back to `$noYears` for a year that is not a column, and the test "a Budget of a future year has empty years instead of failing" locks that in. Neither the plan nor ADR-0101 records the decision.
  - **What the user sees:**
    - The tab shows the bars of 2020–2026 without the viewed year, so the bars do not equal the tiles. That breaks a promise of ADR-0101.
    - A category without a row in "Lata" gets "brak pozycji w budżecie" in every year, even when it had a Budget line without operations.
    - With no year in execution up to today, `columns` is `[]`, `columns[0]` is `undefined`, and the label reads "Zmiana od undefined".
  - **Coverage gap:** no `panel-years-*` scenario covers this state.
- **Fix A ⭐ Recommended**: In a year that is not a column, do not show the "Lata" tab: the panel stays on "Miesiące" and `?panel=years` falls back to `months`. Write the decision into ADR-0101 and the plan, and add a sink scenario.
  - Strength: no misleading bars, and the ADR's promise "słupki oglądanego roku równe kafelkom" holds wherever the tab exists. The `undefined` problem disappears together with the empty `columns`.
  - Tradeoff: one more panel state, plus a change to `assertInertia` and to the test for the future year.
  - Confidence: MED. The code shows the problem, but it is your call whether you want to see past years in a future year.
  - Blind spot: I have not checked whether you ever have a future year in execution on production.
- **Fix B**: Keep the fallback, but show an empty state with an explanation (for example "Rok :year nie jest jeszcze w zestawieniu lat") instead of "—" bars when the year is not a column. Write it into ADR-0101 and the plan, and add a sink scenario.
  - Strength: the switch looks the same in every year, and the sidebar carrying `panel=years` never changes the tab.
  - Tradeoff: one more message in the lang file, and a tab that has no content.
  - Confidence: MED.
  - Blind spot: the empty-state copy is not designed in Atrament.
- **Decision**: FIXED via Fix A — a year that is not a column has `years: null` (on the panel and on every Category) and `tab: 'months'`, and the header shows the label 'Miesiące <rok>'. Red→green: two tests in `ReportCategoryPanelTest` and an `assertInertia` check for 2027 in `ReportsPageTest`. Also the `panel-years-future-year` scenario with an E2E test in `reports-view.spec.ts`, plus ADR-0101, `reports.md`, `design-system.md` and the plan addendum.

### F3 — A failed POST from the category name in "Lata" is silent and has no pending guard

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/ReportYearsTable.vue:139-157 (and the second instance around :232-251)
- **Detail**: The new `<Link method="post">` to `budget.select-year` has no `onError` and no `pending` guard. The other route to the same endpoint, `BudgetYearSwitcher.vue:158-177`, shows `notifyBudgetYearSelectionFailed(t.shell.select_year_failed)` on error and blocks a second post while one is pending. A validation error (for example the target Budget was deleted in another tab) redirects back with session errors that the Reports page never shows. That goes against "Nigdy nie ukrywaj dowodów" in CLAUDE.local.md.
- **Fix**: Add `@error` with the same notifier and an `onStart`/`onFinish` pending state, as in `BudgetYearSwitcher`, or move the post to a shared helper.
- **Decision**: FIXED — `@start`/`@error`/`@finish` with a pending state, and `listenForBudgetYearSelectionFailure` with the `shell.select_year_failed` toast, as in `DashboardNotices`. Red→green: the contract test in `ui-contract.test.ts`.

### F4 — Small deviations from the plan's wording

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Support/ReportYears.php:170; tests/Feature/ReportYearsTest.php (~:571-609); resources/js/lib/reports-fixtures.ts (panel-years-phone)
- **Detail**:
  - The flag is computed in `rows($sameDay)`, so twice per category, not once outside the mode loop. The value is the same either way because `true` is hard-coded.
  - The test case for "zarchiwizowana z operacjami tylko w 2020–2022" uses a category that is not archived.
  - The `panel-years-phone` scenario has the sheet closed (`requested_category_id: null`), while the plan said "arkusz otwarty na „Latach”". Its caption says so honestly, and it follows the pattern of `panel-phone`.
- **Fix**: Archive the category in the "Dawna" test case, and note the other two deviations in the plan as accepted.
- **Decision**: FIXED — the 'Dawna' case is now archived. The other two deviations are accepted and recorded in the plan addendum.

### F5 — Duplicated bar logic and lang key

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/report-category-panel.ts (`reportYearBars`); lang/pl/ui.php (`panel_years_change`)
- **Detail**: `reportYearBars` repeats the maximum and ratio logic of `reportYearsSparkBars` (`resources/js/lib/report-years.ts:51-71`) under different field names. `panel_years_change` ("Zmiana od :year") duplicates `years_change_since`.
- **Fix**: Build `reportYearBars` on `reportYearsSparkBars`, and use `years_change_since` instead of the new key.
- **Decision**: FIXED — `reportYearBars` is built on `reportYearsSparkBars`, and `panel_years_change` is removed in favour of `years_change_since`.

### F6 — The panel bar for the year in progress has no "w toku" mark in "Pełne lata"

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ReportYearBars.vue
- **Detail**: When a past year is viewed (mode `full`), the 2026 bar is a partial year in `foreground`, scaled into the maximum, with nothing marking it as in progress. The "Lata" table marks the same year with `years_in_progress` in the header and the `aria-label` (`ReportYearsRow.vue:60-67`), but the panel's `sr-only` list does not.
- **Fix**: In the `sr-only` list (and, if you want, in the label under the bar) add ", w toku" for `currentYear` when `mode === 'full'`, as `ReportYearsRow` does.
- **Decision**: FIXED — the `sr-only` list in `full` mode gives '<rok>, w toku', as the table does. Red→green: a Vitest case in `report-category-panel.test.ts`, plus an E2E assertion in `panel-years-past-year`.
