<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Widok Raporty: stany, miejsce na wykres i sink na prawdziwej stronie

- **Plan**: context/changes/reports-view/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-12
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 5 observations

## Verification run (this review)

Triage done: F1-F5, F7, F8 fixed; F6 dismissed; F9 accepted. Final run after the fixes: `npm run check`, `types:check`, `check:ui`, Vitest (637 tests), PHP guards (5 tests) pass; Reports E2E 11 of 11 pass (`reports-view.spec.ts` 9, `reports.spec.ts` 11 incl. `shortcut 8`). The table below is the run before the fixes.

| Command | Result |
|---------|--------|
| `npm run check` | pass (375 files formatted, 0 lint errors) |
| `npm run types:check` | pass |
| `npm run check:ui` | pass |
| `npm test -- resources/js/lib/report-chart.test.ts` | pass (10 tests) |
| PostgreSQL: `ReportsHardcodedUiTest`, `ReportsKitchenSinkTest`, `ReportsPageTest` | pass (37 tests, 786 assertions) |
| `vendor/bin/sloppy ci` (Sail image) | pass (100/100, 0 findings) |
| `composer ci:check` (Sail image, `testing_reports_view`) | 1538 passed, 2 skipped; exit code not captured (pipe) |
| E2E `reports-view.spec.ts` (port 8150, `e2e_reports_view`) | all pass |
| E2E `reports.spec.ts` | **1 failed**: `shortcut 8 opens Raporty ...` (reproduced twice, see F1); the rest pass (19 passed in total) |

Used the host-side workaround from `agent-shell-toolchain` (php shim to `sail-8.5/app`, Linux Node 24). Host CLI is PHP 8.3, so PHP steps ran in the Sail image.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | FAIL |

## Findings

### F1 — Existing E2E `shortcut 8` fails in this review's run

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: tests/e2e/reports.spec.ts:83
- **Detail**: Progress 2.2, 3.3 and 4.2 tick "existing E2E Raportów przechodzą", but the test fails here (twice) with a 30 s timeout. The page at failure is `/operations`; the income operation was never created (only the expense row exists), so the run never reaches Raporty. The test and the Operations dialogs are not touched by `git diff 2b50fbe..HEAD`, and income categories exist in `e2e_reports_view` (4) and in the template DB. Two older leftover rows `E2E raport wydatek ...` (11:00, 11:05 today) in `e2e_reports_view` show the same test already failed in earlier runs. So it is probably environmental or pre-existing, but this is not proven: it was not run on the base commit. The failed runs left two more `E2E raport wydatek` rows (ids 245, 249) in `e2e_reports_view`; I did not delete them.
- **Fix**: Run `reports.spec.ts` "shortcut 8" on the pre-change code (`2b50fbe`) against the same database. If it is red there too, track it separately (flaky or DB state) and fix the Progress claim; if green, bisect the four commits.
  - Strength: Decides in one run whether the change caused it.
  - Tradeoff: Needs a second production build (~3 min) in a clean worktree.
  - Confidence: HIGH — the failing path is outside the diff.
  - Blind spot: The exact hanging action inside `addOperation` (the real error is masked by the `finally` cleanup timeout).
- **Decision**: FIXED via Fix. Not a regression: the test passes alone in 19-28 s and fails at the 30 s limit at changing steps (income add, delete cleanup), none in the Reports view. Added `test.slow()` with a comment in `reports.spec.ts`; assertions unchanged. Side finding: the shared template DB `household_treasurer_e2e` holds leftovers of someone's aborted runs (empty groups `Grupa E2E raport` / `Grupa E2E trend`, Rozrywka assigned to the second), which made the two group tests fail with "Grupa o takiej nazwie już istnieje". Cleaned only in `e2e_reports_view` (recreated from the template, then the groups removed); the template itself is untouched. Full Reports E2E: 11 of 11 pass.

### F2 — "nie przeskakuje" tests can pass without testing anything

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/reports-view.spec.ts:94-138
- **Detail**: Both tests hold the chunk with `page.route('**/build/assets/ReportBarChart-*.js' | 'ReportTrendChart-*.js')` but never assert that the route matched or that the placeholder was visible before `release()`. If the chunk is renamed (bundler naming, `manualChunks`), the chart loads at once, `before` is measured with the chart in place, and the test is green. The plan itself (3.2) relies on these tests as the red-to-green proof. The comparison placeholder (with legend) has no jump test.
- **Fix**: Before `release()`, assert `report-chart-placeholder-expense` (Trend: `report-trend-chart-placeholder`) is visible, and that the route handler ran at least once.
- **Decision**: FIXED. Both jump tests now assert the placeholder is visible and the chart absent before `release()`, and that the held request count is above zero (`holdFragment` returns `{ release, held }`).

### F3 — Placeholders copy legend logic from the charts

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/ReportBarChartPlaceholder.vue:36-49, resources/js/components/ReportTrendChartPlaceholder.vue:22-37
- **Detail**: The placeholder copies colours (`var(--success)`, `var(--chart-1)`, `var(--chart-2)`), `legendLabel()` and, for Trend, `legend` and `inProgressNote` from the real charts. Height of the chart has one source (`reportChartHeightRem`), the legend content has none. A different note text wraps the legend differently and brings the layout jump back.
- **Fix A ⭐ Recommended**: Export a legend builder and the colours from `@/lib/report-chart` / `@/lib/report-trend-chart` and use them in both the chart and its placeholder.
  - Strength: One source for legend items, same as the plan did for height (C4).
  - Tradeoff: A small refactor of two chart components after phase 3 was accepted.
  - Confidence: MED — `t` has to be passed into the builder, which changes the lib signature.
  - Blind spot: Not checked how `ReportTrendChart` builds the legend in detail.
- **Fix B**: Keep the copies, rely on the jump tests (after the F2 fix, plus a compared-year test).
  - Strength: No production code change.
  - Tradeoff: Drift is caught late, by a test, not prevented.
  - Confidence: MED — only holds if F2 is fixed.
  - Blind spot: Tests need demo data with positive amounts.
- **Decision**: FIXED via Fix A. `reportChartLegend`, `reportChartColor`, `REPORT_CHART_PREVIOUS_COLOR` in `@/lib/report-chart`; `reportTrendLegend`, `reportTrendInProgressNote`, `REPORT_TREND_*_COLOR` in `@/lib/report-trend-chart`; charts and placeholders use them. 7 new unit tests (637 total pass).

### F4 — After-change screenshots missing

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/reports-view/screenshots/
- **Detail**: Plan (end of phase 4, and Manual Testing item 4) asks for screenshots after the change at the same widths as the audit. The directory has only `audit-*.png` and `audit-measurements.json`; manual steps 4.5–4.7 are ticked without that artefact.
- **Fix**: Take the `after-*` shots at 1440 and 352 (draft, no-budget, chart pending, year 352, sink phone) into that directory.
- **Decision**: FIXED. 12 `after-*.png` in `context/changes/reports-view/screenshots/` (chart pending/loaded 1440 and 352, year 352, Trend pending 1440, sink: draft, draft-last-12, no-budget, month-closed, phone, compared-phone). Drafted with a temporary spec, removed afterwards.

### F5 — Narrow grid differs from the plan's contract (documented)

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/css/app.css (narrow `.report-grid` block, ~1480-1520)
- **Detail**: Plan: second column `minmax(5.5rem, auto)`, areas `'. share'`. Implemented: `minmax(4.5rem, auto)` and `'share share'`, because "udział 30,9%" in the amount column alone would widen it to ~94 px; the comment says name ≈ 200 px vs the plan's ≈ 184 px. Visible result is the same (share right-aligned under the amount). Reviewer arithmetic: 288 − 72 − 16 = 200 px, so the comment is right.
- **Fix**: Mention the 4.5rem / `share share` decision in ADR-0083 or the plan's addendum so it is not a silent drift.
- **Decision**: FIXED. ADR-0083 now records the 4.5rem amount column and the share cell spanning both columns (name about 200 px).

### F6 — Sink wraps `Reports.vue` in `defineAsyncComponent`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/ReportsKitchenSink.vue:11
- **Detail**: Plan says `Reports.vue` is imported directly and the sink's own `defineAsyncComponent` goes away. Now all ~31 embedded pages load lazily, so sections flash empty first. Works, and the E2E scenarios pass.
- **Fix**: Import `@/pages/Reports.vue` statically, or leave it and note why.
- **Decision**: DISMISSED, with evidence. I made the import static and the E2E run returned 500 on `/reports`: "Unable to locate file in Vite manifest: resources/js/pages/Reports.vue" (the static import folds the page into the sink chunk). Reverted; the lazy import stays, now with a comment saying why. The plan's "direct import" was wrong.

### F7 — `design-system.md:20` still describes the old narrow table

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/design-system.md:20
- **Detail**: The Reports tables line says narrower rows have a second line with the previous year and the difference, with no mention of Udział under the amount below 36rem (ADR-0083). The plan only asked for `/dev/ui/reports` at `:177`, so this is a gap in the plan, not in the implementation.
- **Fix**: Add one sentence: below 36rem the share goes under the amount with the "udział" label.
- **Decision**: FIXED. `design-system.md:20` now says the share goes under the amount behind the "udział" label below 36rem and the Badge wraps under the name (ADR-0083).

### F8 — E2E comment gives the wrong card width; environment not stated

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/reports-view.spec.ts:141 (and header)
- **Detail**: "the card is about 288 px wide" — 288 px is the row content; the card is ~318-320 px. Also `/dev/ui/reports` returns 404 outside `APP_ENV=local`, and a 404 would surface as "heading not found" with no hint.
- **Fix**: Correct the comment; add one header line about `APP_ENV=local` (or assert the response status in `beforeEach`).
- **Decision**: FIXED. Card width comment corrected (about 320 px card, 288 px row content), font note added for the Badge test, `APP_ENV=local` note in the file header. No status assertion added.

### F9 — Sink `chart-loading` reuses real test ids; narrow tests depend on font metrics

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/dev/ReportsKitchenSink.vue (chart-loading), tests/e2e/reports-view.spec.ts:143-205
- **Detail**: The `chart-loading` section renders the real placeholders, so `report-chart-placeholder-expense` and `report-chart-legend-expense` occur more than once on the sink page; tests are scoped by `[data-scenario]`, so it works today. The "Badge schodzi pod nazwę" test assumes "Stara prenumerata" + Badge is wider than ~200 px, which holds only with the real font.
- **Fix**: Keep as is; scope any future page-wide `getByTestId` by section.
- **Decision**: ACCEPTED as is. Tests are scoped by `[data-scenario]`; scope any future page-wide `getByTestId` by section.
