<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Dashboard według strony design systemu (ADR-0045)

- **Plan**: context/changes/dashboard-design-system-page/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-06
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS (one small EXTRA, F4) |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING (F1, F5) |
| Success Criteria | PASS (manual items pending, F6) |

## Phase coverage (Changes Required vs code)

| Phase | Verdict | Note |
| --- | --- | --- |
| 1 ADR-0050 and docs | MATCH | ADR-0050 is the only `0050-*`; design-system.md and js.md describe the final code (container-query switch, block layout, storage rule). |
| 2 Client logic and texts | MATCH | `year-elapsed-mark-storage.ts` and `dashboard-notice-presentation.ts` have tests; both catch blocks log the cause with `console.warn`. |
| 3 Three measures and notice card | MATCH | Presentation function drives label, identity, value; no `notice-title` / `notice-text` / `yearElapsedCaption` left. |
| 4 Block "Budżet <rok>" | MATCH with deliberate deviations | Container-query switch (a) and `.category-meter-sum-row` (c) are coherent with ADR-0050, design-system.md and the grid CSS. |
| 5 Sink and visual gate | MATCH | `year-mark-switch` section, stateMatrix updated, orphan keys removed, 21 screenshots present (20 required plus `overrun-1024-light`). `segmented-control-tabs` correctly kept (not planned for removal if used elsewhere). |

Deviation (b): `month_item_over_plan` and `one_off_over_limit` fixtures are negative, which matches the PHP payload (`plan - actual`, remaining). Coherent.

## Automated checks (run on the committed tree)

| Check | Result |
| --- | --- |
| `npm run test` | PASS, 30 files, 289 tests |
| `npm run types:check` | PASS |
| `npm run check:ui` | PASS |
| `npm run check` | PASS (261 files formatted, 0 lint warnings) |
| grep `yearElapsedCaption\|notice-title\|notice-text` in resources | no hits |
| grep `category-meter-cell-status\|category-meter-group-row\|execution_tab_all` in resources | no hits |
| grep `severity only in the icon tile` in .ai/rules | no hits |
| ADR-0050 uniqueness | one file |
| `composer ci:check` (PostgreSQL, 902 passed) and `sloppy ci` (0 findings) | not rerun, taken from the orchestrator |

## Cross-phase checks

- Translation keys: every static `t.dashboard.X` / `t.budget.X` in the Dashboard files exists in `lang/pl/ui.php` (scripted comparison, 0 missing). The dynamic keys `notice_label_${labelKey}` resolve to all six `notice_label_*` keys that exist. Dynamic `item.labelKey` (checklist) is untouched.
- Orphans: `planned`, `executed`, `year_elapsed_as_of`, `month_pace_percent`, `month_pace_none`, `execution_tabs_aria`, `execution_tab_all`, `execution_status`, `notice_title_*`, `notice_text_*` have no users under `resources/`, `app/`, `tests/` or `lang/`. Hits on `planned`/`executed`/`year_elapsed_as_of` are unrelated identifiers (`planned_amount_minor_units`, the payload prop `year_elapsed_as_of`, local variables), not `t.dashboard.*` keys. The only `month_pace_percent` hit is a PHP test name.
- Empty catch: none in the change. Both `catch` blocks (`year-elapsed-mark-storage.ts:18,33`) call `console.warn` with the error.
- Switch id: `useId()` in `DashboardBudgetBlock.vue`, so the two sink blocks and the page do not collide.
- Docs vs code: ADR-0050, design-system.md and js.md all describe the container-query switch (38rem) and the sum row; the only stale claim is in a CSS comment (F1).
- stateMatrix text matches the UI (przekroczono beside the percent and in sums, Tab order Switch, Otwórz budżet, rows, notice cards, draft and no-budget under `empty`), except stale "fazy 6" wording (F2).
- Screenshots viewed: `all-notice-kinds-1280-light.png` (seven kinds with label, identity, right-hand value in severity colour; no clipping; two tables with sum rows and footer), `draft-1280-light.png` (title row without Switch, plan measures, empty-state card, aside beside), `year-mark-switch-390-dark.png` (Switch on shows the mark, off hides it; badge sits beside the percent, no horizontal overflow, Plan and Realizacja hidden).

## Findings

### F1 — CSS comment above the meter grid still says the table switches at `md`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/css/app.css:1058-1062
- **Detail**: The block comment over `.category-meter-header-row` / `.category-meter-sum-row` / `.category-meter-row` says "od `md` jedna linia kolumn". The rules below it switch on `@container (min-width: 38rem)`, as the new comment a few lines lower, ADR-0050 and design-system.md say. A future agent reading the first comment would reintroduce `@variant md`, the exact regression the container query fixed (clipped badge at 1280 px, collapsed category at 1024 px).
- **Fix**: Change the comment to "od szerokości karty 38rem (container query, ADR-0050)".
- **Decision**: FIXED — comment names the 38rem container (ADR-0050)

### F2 — Sink stateMatrix refers to "fazy 6" that does not exist

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/DashboardKitchenSink.vue:38-52
- **Detail**: The `disabled`, `hover` and `focus-visible` rows say "Zrzuty z fazy 6 z działającej aplikacji". This plan has five phases and the Phase 5 screenshots are of the sink, not the running app. The text predates the change, but Phase 5 rewrote the `hover` and `focus-visible` rows and kept it.
- **Fix**: Replace "z fazy 6" with "z działającej aplikacji (checklista ręczna zmiany)" or drop the phase reference.
- **Decision**: FIXED — „Zrzuty ręczne” instead of „Zrzuty z fazy 6”

### F3 — "14 000,00 zł" wraps in the narrow main column at 1280 and 1024 px (known)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: resources/js/components/BudgetPlanSummaryStrip.vue (visible in screenshots/all-notice-kinds-1280-light.png and draft-1280-light.png)
- **Detail**: "Planowane dochody" shows "14 000,00" / "zł" on two lines next to the single-line other two measures, so the strip looks uneven. The plan forbids reworking the component because the Budget view shares it, and ADR-0050 records that. No clipping or overflow, only a wrap. Already flagged to the user.
- **Fix**: Follow-up change: let the strip use a container-query font size or `whitespace-nowrap` with a smaller value size; check the Budget view in the same change.
- **Decision**: DEFERRED — out of scope (plan: no rework of BudgetPlanSummaryStrip, also used by Budżet); left to the user as a follow-up change

### F4 — Unplanned change to `.accounts-cell-name` in the Phase 5 commit

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/css/app.css:729-731
- **Detail**: `.accounts-cell-name` gained `flex-wrap items-center gap-x-2 gap-y-1`. The Accounts view is not in this plan and has no screenshot in this change, and the commit message and plan do not mention it. The change is harmless (wrap instead of clipping) but it touches a page covered by ADR-0044 and `/dev/ui/accounts`.
- **Fix**: Mention it in the plan or commit notes, and confirm at `/dev/ui/accounts` that long bank names still look right; otherwise revert.
- **Decision**: FIXED — reverted to `flex min-w-0 items-center gap-2` (only Accounts.vue uses it; AccountsHardcodedUiTest green)

### F5 — js.md "hidden md:grid header row" rule does not mention the Dashboard exception

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: .ai/rules/js.md:38-39
- **Detail**: The section states the general rule "a `hidden md:grid` header row", and the Dashboard sentence below it does not say that its tables switch by container width (`.category-meter-table`, 38rem), unlike Operations, Budget and Accounts. The Dashboard sentence does list the five columns but not the switch. An agent adding a Dashboard column could copy the `md` pattern.
- **Fix**: Add "switching by the table's own width (`.category-meter-table`, container query 38rem, ADR-0050), not by `md`" to the Dashboard sentence.
- **Decision**: FIXED — js.md:39 says the Dashboard tables switch by their own width (38rem), not by `md`; boost:index-rules without index diff

### F6 — Manual Progress items open; label key mapping untested

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/dashboard-design-system-page/plan.md (Progress 1.4, 1.5, 2.5, 3.5-3.7, 4.7-4.11, 5.5-5.7)
- **Detail**: All manual items stay `[ ]`, which is correct until the user confirms. The review's evidence from screenshots supports 3.5, 4.8, 5.5 and 5.6 at 1280/390; 4.9 (reload persistence, private mode) and 4.10 (toast on error) have no evidence in the diff beyond unit tests and the page wiring. Separately, `dashboard-notice-presentation.test.ts` checks `labelKey` values but nothing checks that `notice_label_<labelKey>` exists in `lang/pl/ui.php`, and vue-tsc does not catch a typo in the dynamic key (the earlier `t.budget.income_sum` class of bug). It holds today.
- **Fix**: User ticks the manual items after the browser pass; optionally add a PHP or TS assertion that every `NoticeLabelKey` has a `notice_label_*` entry.
- **Decision**: ACCEPTED — manual items wait for the user; key-existence test not added (the presentation test pins labelKey, types:check would not catch a missing key — candidate for a follow-up)
