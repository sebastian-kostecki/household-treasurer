<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: dashboard-design-system-page

- **Plan**: context/changes/dashboard-design-system-page/plan.md
- **Scope**: Phase 4 of 5 ("Faza 4: Blok „Budżet <rok>”", commit 547565f)
- **Reviewed phases**: 4
- **Date**: 2026-11-06
- **Verdict**: APPROVED
- **Findings**: 0 critical 1 warnings 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Plan items vs commit

| Item | Verdict | Note |
|------|---------|------|
| 1. `useYearElapsedMark` (default true, read in `onMounted`, write only in `setYearElapsedMark`) | MATCH | No `watch`. Helper logs storage errors with the cause (`console.warn(..., error)`), no empty catch. |
| 2. `DashboardBudgetBlock` (header, Switch only for `phase === 'execution'`, Otwórz budżet form or `CreateBudgetDialog` with `dashboard-open-budget-year`, strip in both phases, draft Card, panel gets `showYearElapsedMark ? yearElapsedPercent : null`) | MATCH | Props and emits as in the contract. Single `Card` in the draft branch, siblings in `space-y-3`, so no Card in a Card. |
| 2. `Dashboard.vue` (button removed from title row, handlers kept, `budget === null` still shows its own card and dialog) | MATCH | `@start/@finish/@error` go to `onYearSelectionStart/Finish/Failed`. The error toast (`notifyBudgetYearSelectionFailed`, `listenForBudgetYearSelectionFailure`) is untouched. Switch wired as prop plus `@update:show-year-elapsed-mark="setYearElapsedMark"`, so the write happens in the handler (a valid adaptation). |
| 3. `DashboardExecutionPanel`: two `ListSurface flush` tables, `h3.section-title`, header Kategoria / Plan bieżący / Realizacja / Pozostało / Wykonanie, `no_categories`, sum row, footer only under Wydatki | MATCH | Footer is `execution_footer` or `execution_footer_no_mark` and only for `table.type === 'expense'`. The sum row's `ExecutionMeter` gets `:year-elapsed-percent="null"`, so no mark. `SegmentedControl`, `activeTab`, `groups`, `tabOptions` are gone. |
| 3. `CategoryMeterRow`: status cell removed, badge in the meter cell | MATCH | |
| 3. `app.css`: five areas, mobile `category remaining` / `meter meter`, sum row, status cell removed | MATCH (adaptation confirmed) | The sum-row class is `.category-meter-sum-row` and `.category-meter-group-row` is gone, which satisfies the grep criterion. Column is `minmax(11rem, 1fr)`. Plan and Realizacja stay hidden below `md`. |
| 3. `dashboard.execution_column` added | MATCH | Orphaned `executed`, `planned`, `execution_status`, `execution_tabs_aria`, `execution_tab_all` are left for Phase 5 cleanup (planned). |
| 4. Guards | MATCH | The new file is in `FILES` (`scripts/check-ui-literals.mjs`) and in `DASHBOARD_VIEW_FILES` (`tests/Unit/DashboardHardcodedUiTest.php`). |
| PHP side | MATCH | No PHP controller or prop change, so Inertia props are unchanged and `DashboardTest` runs without assertion edits. |

EXTRA: none. MISSING: none.

## Automated verification

Run on the working tree. `git status` shows only `plan.md` modified, so Phase 5 files were not touched at run time and the results apply to the commit.

| Check | Result |
|-------|--------|
| `npm run test` | PASS, 30 files, 289 tests |
| `npm run types:check` | PASS |
| `npm run check:ui` | PASS |
| `npm run check` | PASS, 261 files formatted, no lint warnings |
| `grep -rn "category-meter-cell-status\|category-meter-group-row\|execution_tab_all" resources` | PASS, no hits |
| PHP (PostgreSQL, Docker): `DashboardHardcodedUiTest`, `DashboardTest`, `DashboardKitchenSinkTest` | PASS, 52 tests, 905 assertions |

Manual items in Progress were not checked against a browser. The areas where no evidence exists in the diff are listed in F2 and F3.

## Findings

### F1 — Double line above the sum row

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/css/app.css (`.category-meter-sum-row`, around line 1099) and `.category-meter-row` (`border-b` from the shared rule at line ~1066)
- **Detail**: The shared rule gives every row `border-b`. The sum row adds `border-t`. When a table has at least one category, the last `.category-meter-row` draws its bottom line and the sum row draws its top line directly under it, so two adjacent 1px lines (visually a 2px line) appear. This is confirmed from the CSS and markup (`DashboardExecutionPanel.vue` renders `CategoryMeterRow` items and then `.category-meter-sum-row` as consecutive siblings). With zero categories only one line appears (the `no_categories` paragraph has no border). The plan asked for `border-t`, and `border-b-0` on `:last-child` covers only Dochody. In Wydatki the footer div is a later sibling, so the sum row is not `:last-child` and keeps its `border-b`, which is intended (it separates the row from the footer, per the `ListSurface` comment). The double line is therefore a planned-but-unexamined side effect.
- **Fix**: Drop `border-t` from `.category-meter-sum-row` and rely on the last category row's `border-b` (keep a top border only via `.category-meter-header-row + .category-meter-sum-row` if the empty state needs it). Confirm in the Phase 5 screenshot.
- **Decision**: FIXED in the Phase 5 commit — `.category-meter-row + .category-meter-sum-row { border-t-0 }`; the empty-category case keeps the top border; confirmed on screenshots

### F2 — Fixed `id` on the Switch will duplicate when the block is rendered twice

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/DashboardBudgetBlock.vue:69-77
- **Detail**: `Switch id="dashboard-year-elapsed-mark"` and `Label for="dashboard-year-elapsed-mark"` are literals. Phase 5 plans to render `DashboardBudgetBlock` twice in the kitchen sink (Switch on and off). Two elements then share one id, so the second `Label` toggles the first Switch, which is an accessibility bug. It is not a problem on the dashboard itself (one block). The Switch is labelled (`for` plus `id`), and Tab order is Switch, then Otwórz budżet, then rows, which is sensible.
- **Fix**: Build the id with `useId()` (Vue 3.5) or a prop with a default, so every block instance has a unique pair.
  - Strength: Fixes the Phase 5 duplicate before it exists; no behaviour change on the dashboard.
  - Tradeoff: Hydration-stable id is needed with SSR; `useId` handles that.
  - Confidence: HIGH — the plan states the sink renders the block twice.
  - Blind spot: Whether the sink's tests or screenshots rely on the literal id.
- **Decision**: FIXED in the Phase 5 commit — Switch id and Label `for` from `useId()`

### F3 — Meter cell may overflow with the "przekroczono" badge at the narrow end of `md`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/css/app.css (`.category-meter-cell-meter`, `.category-meter-cell-meter .execution-meter-track`)
- **Detail**: The last column is `minmax(11rem, 1fr)`. Inside it are a 3.5rem track, 0.5rem gap, a percent of at least 2.25rem, `gap-2`, and the badge (about 5-6rem). That is more than 11rem when the row is at its minimum width (the `md` to `lg` range, where the sidebar leaves little room). The cell is `flex min-w-0` without wrap, so the badge could spill past the column edge. At wider widths `1fr` absorbs it. This was not verified in a browser (the 390 px and `md` manual item is open).
- **Fix**: Check at 768-1024 px in the Phase 5 screenshots. If it overflows, raise the minimum to about `13rem` or allow `flex-wrap` on the cell.
- **Decision**: FIXED in the Phase 5 commit — confirmed in screenshots (badge clipped at 1280, category collapsed at 1024); the table switches to five columns on its own width (container query) instead of the window `md`, with the meter cell wrapping the badge; docs updated to match

### F4 — Two `import type` statements from the same module

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/DashboardBudgetBlock.vue:15-16
- **Detail**: `DashboardTypeTotals` and `DashboardCategory` are imported on two lines from `@/lib/dashboard-categories`. `DashboardExecutionPanel.vue` uses one combined import. Lint passes, so this is cosmetic.
- **Fix**: Merge into `import type { DashboardCategory, DashboardTypeTotals } from '@/lib/dashboard-categories';`.
- **Decision**: FIXED in the Phase 5 commit — one import

## Checks that passed (no finding)

- The read of `localStorage` happens only in `onMounted`. The initial value is `true`, so SSR markup and the first client render match. The write happens only in `setYearElapsedMark`, which is called from the `@update:` handler (no `watch`).
- The Switch renders only for `budget.phase === 'execution'`. The block passes `showYearElapsedMark ? yearElapsedPercent : null` to the panel.
- The footer text depends on `yearElapsedPercent === null`, so it switches to `execution_footer_no_mark` when the Switch is off.
- "Otwórz budżet" sits in the block. Both the `selectYear` form and `CreateBudgetDialog` (`dashboard-open-budget-year`) are present, and the year-selection error toasts still go through the page handlers. `budget === null` still renders the page's own empty-state card and dialog.
- The planned-plan strip is now rendered in both phases (per the plan and ADR-0050). The previous condition `planSummary && phase === 'draft'` was dropped intentionally.
- Mobile: the grid areas are `category remaining` and `meter meter`, with Plan and Realizacja `hidden` below `md`. The header row is hidden below `md`.

## Addendum (orchestrator, Phase 5)

- This review and `vue-tsc` both missed a bug. `DashboardExecutionPanel.vue` read `t.budget.income_sum` / `t.budget.expense_sum`, but those keys live under `dashboard`, so the sum row label rendered empty. The bug surfaced on the Phase 5 screenshots and is fixed in the Phase 5 commit (`t.dashboard.*`).
