<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Trend w miesiącach roku w Raportach (S-30)

- **Plan**: context/changes/report-trend-by-month/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-11
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 5 observations (F6 found during the triage)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Evidence

- Diff `c4331c2..HEAD` (ba7cbff p1, d9337c6 p2, 17cca24 p3, 46ed38e epilogue): every file in "Changes Required" changed. Unplanned files are workflow only: `context/foundation/roadmap.md` (S-30 status) and `plan-brief.md`. During implementation the plan changed only in its Progress ticks.
- No MISSING items, and nothing from "What We're NOT Doing" was built. The harmless extras are `reports.trend_measure` ("Miara", the name-column header of the measures table) and the `data-trend-table` / `data-trend-measure` test selectors.
- Security and performance:
  - Budget is scoped through `$request->user()->budgets()` plus `authorize`; lines by `budget_id`, groups by `user_id`.
  - `view` is only compared and `destination` is whitelisted. No `v-html`.
  - The trend runs a constant set of queries and does not compute `PeriodReport` or the comparison.
- `ReportCategoryTree` extraction keeps `PeriodReport::toArray()` identical; `PeriodReportTest` is unchanged and passes.
- Automated checks (run 2026-11-11 in the Sail image, `sail-8.5/app`, and Linux Node 24):

  | Check | Result |
  |---|---|
  | `ReportTrendTest` + `PeriodReportTest`, SQLite | 34 passed |
  | Page, budget, kitchen-sink, guard and translation tests, PostgreSQL | 132 passed |
  | `NfrPerfReportTest` (year, month and trend), PostgreSQL | passed |
  | `format.test.ts` + `report-trend-chart.test.ts` | 31 passed |
  | `npm run check:ui` | exit 0 |
  | `vendor/bin/sloppy ci` | 0 findings, baseline unchanged |
  | `npx playwright test tests/e2e/reports.spec.ts` | 9 passed |

- `composer ci:check` on PostgreSQL:
  - The first run had 28 `QueryException`s ("relation users does not exist"). Two other sessions were running `composer ci:check` against the same `testing` database at the same time.
  - The rest of the gate passed: lint, PHPStan, `npm run check`, `check:ui` and 51 frontend test files.
  - The full PHP suite rerun on a private database (`testing_trend_review`, dropped afterwards) gave 1371 passed and 2 skipped.
- Manual items were ticked in the phase commits. The running `trend-preview` container shows a browser preview was done, so nothing points to a check ticked without evidence.

## Findings

### F1 — Page test does not pin the trend row keys

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/ReportsPageTest.php:318-325
- **Detail**: The plan asks for a page test of "`trend` z 12 miesiącami i kluczami wiersza", and `.ai/rules/inertia-props.md` wants the prop asserted because `vue-tsc` cannot see the PHP side.
  - `test_it_renders_the_trend_view` checks `kind`, `name`, `amount_minor_units` and `months`, then ends with `->etc()`.
  - Nothing checks that `id`, `icon`, `color` or `is_archived` reach the page, though `ReportTrendCategory` in `types/reports.ts` requires them. No page-level test covers a group entry (`kind: 'group'`, `categories`) either.
  - `ReportTrendTest` covers `id` and `is_archived` at the reading level only.
- **Fix**: In that test, assert `id`, `icon`, `color` and `is_archived` explicitly instead of `->etc()`. Add one group entry to the fixture, or a second test, that asserts `kind: 'group'` and `categories`.
- **Decision**: FIXED: `->etc()` replaced by explicit keys, plus a new test `test_the_trend_passes_a_group_with_its_categories`. 24 passed on PostgreSQL and SQLite.

### F2 — Trend E2E creates its own expense group instead of using demo data

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/e2e/reports.spec.ts:353-420
- **Detail**: The plan describes one smoke test on `demo:seed-household` data that also covers collapsing and expanding a group. The implementation splits that check into a second test. That test creates "Grupa E2E trend", moves "Rozrywka" into it, adds an operation and deletes all of it in `finally`.
  - The code comment gives the reason: the demo household has no expense group.
  - Cleanup is safe: `DeleteCategoryGroup` clears membership and the foreign key is `nullOnDelete`.
  - It runs on the separate E2E database. Rozrywka's list position after the delete may differ from the seed.
  - The deviation is not recorded in the plan.
- **Fix**: Add a one-line note on the deviation to Phase 3 of the plan (or accept it as is).
- **Decision**: FIXED: deviation note added to Phase 3 → 4 "Test E2E" in plan.md.

### F3 — Grouping step duplicated between `ReportTrend` and `PeriodReport`

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/ReportTrend.php:116, app/Support/PeriodReport.php:189
- **Detail**: Both `entriesOfType` methods filter rows by type, build `$byCategoryId`, walk `ReportCategoryTree::ofType`, map members and sum the group. Only the entry builder differs.
  - `ReportCategoryTree` removed the risky part (list order and membership), and Sloppy reports no duplication.
  - The same walk will likely be needed a third time in S-32 (rolling 12 months).
- **Fix**: Leave it now; when S-32 needs the walk, extract a shared step (e.g. `ReportCategoryTree` taking a callback that builds the group entry).
- **Decision**: FIXED: the user chose to extract now. The new `ReportCategoryTree::entryNodesOfType` does the row filter, entries by Category id and tree walk; the callers keep only their entry shapes, and `PeriodReport` gained `groupEntry()`. PHPStan level 7 is clean, the report tests pass on PostgreSQL and SQLite (58), and Sloppy is clean.

### F4 — Pinned name cell loses the row inset when scrolled

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/css/app.css:1594-1596
- **Detail**: `.report-trend-name` is `sticky left-0` with the row's background (`bg-card`, `bg-muted` in group rows), so nothing shows through the name, as the plan requires.
  - The rows have `px-4` and `gap-x-2`. When scrolled, the cell sticks at the scroll box edge, so a non-nested name loses its 1rem inset.
  - Amounts scrolling past are visible in the 0.5rem gap to the right of the cell.
  - This is not verified in a browser. Manual check 2.10 was ticked.
- **Fix**: Look at the `trend-phone` scenario in `/dev/ui/reports`. If it bothers you, give the pinned cell its own left inset and a right padding (or a border) in the row's background.
- **Decision**: FIXED: confirmed on a screenshot of `trend-phone` scrolled 200px (names touched the card edge; "Rachu…0", "ır"). `.report-trend-name` now gets `-ml-4 pl-4 -mr-2 pr-2`, and the nested row gets `pl-10`, so it covers the row padding and the gap. Screenshots after the fix look right in light and dark. At 1280px the real page has scrollWidth = clientWidth = 1022 for all three tables. `npm run build` and `check:ui` are green.

### F5 — Rule says the chart draws "the same numbers", but negatives are drawn as 0

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: .ai/rules/reports.md:31, resources/js/lib/report-trend-chart.ts:22-24
- **Detail**: `reportTrendChartBars` uses `max(0, amount ?? 0)`, as the Phase 3 contract specifies and as `reportChartBars` does. The rule sentence says the chart "draws the same numbers as the Przychody and Wydatki rows … never other ones".
  - A month whose refunds exceed purchases shows a negative amount in the table and a zero bar.
  - The plan itself carries both statements. The code is right; the rule text is imprecise.
- **Fix**: Change the rule sentence to say that negative and empty months are drawn as 0.
- **Decision**: FIXED: the sentence in `.ai/rules/reports.md` now adds "a negative or empty month is drawn as a zero bar, while the table keeps its exact amount". `boost:index-rules` ran and the index is unchanged.

### F6 — Month labels of the trend chart overlap at phone width

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/ReportTrendChart.vue:104-110
- **Detail**: Found during the F4 triage on the screenshot of the `trend-phone` scenario (22rem frame, light and dark).
  - The X axis shows all 12 `month_chip_N` labels, which overlap on the phone: "markwimajcze", "siewrzpaź".
  - Manual check 3.8 ("Wykres czytelny … mieści się na telefonie") was ticked.
  - Unovis 1.7.1 has `tickTextHideOverlapping` on `VisAxis`.
- **Fix**: Set `:tick-text-hide-overlapping="true"` on the X axis. On the phone it then shows every other month; on a laptop it shows all 12 labels. No `ui/chart/**` change and no new tokens.
- **Decision**: FIXED: `:tick-text-hide-overlapping="true"` set on the X axis. Screenshots show the phone with every other month (sty, mar, maj, lip, wrz, lis) and the laptop with all 12. `npm run build`, `vue-tsc` and `check:ui` are green, and the Reports E2E passes (9).

## Triage

| Decision | Findings |
|---|---|
| Fixed | F1, F2, F3, F4, F5, F6 (6) |
| Skipped | none |

Checks after the fixes (2026-11-11):

| Check | Result |
|---|---|
| PHPStan level 7 on `app/Support` | clean |
| pint | clean |
| `ReportTrendTest`, `PeriodReportTest`, `ReportsPageTest` on PostgreSQL (private DB) and SQLite | 58 passed |
| `ReportsHardcodedUiTest`, `ReportsKitchenSinkTest`, `UiTranslationKeysTest` | 6 passed |
| `npm test` | 574 passed |
| `check:ui` | exit 0 |
| `vendor/bin/sloppy ci` | clean |
| Reports E2E | 9 passed |
