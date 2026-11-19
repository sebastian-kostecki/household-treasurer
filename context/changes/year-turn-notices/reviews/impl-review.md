<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Year Turn Notices Implementation Plan

- **Plan**: context/changes/year-turn-notices/plan.md
- **Scope**: Full plan (Automated rows of every phase are done; every Manual row is still pending)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-19
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 5 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Automated verification (run 2026-11-19)

| Check | Result |
|---|---|
| Phase 1 greps (ADR 0109 count, glossary, amended ADRs, rule files) | PASS: 1 / 1 / 1 / 3 / 1 / 1 |
| `composer ci:check` on PostgreSQL (`testing_year_turn_notices`) | PASS: exit 0, 1982 passed, 4 skipped |
| `npm run test` (Vitest) | PASS: 810 passed, 1 skipped |
| `npm run types:check`, `npm run check:ui` | PASS |
| `vendor/bin/sloppy ci` (Sail image) | PASS: 3 medium findings, none in files of this change |

## Findings

### F1 — Notice order contract is under-tested

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/DashboardTest.php:1503-1531
- **Detail**: The plan said to extend `test_dashboard_orders_notices_by_severity_then_kind`. That test was left unchanged; a new test, `test_dashboard_lists_budget_not_in_execution_notices_last_in_year_order`, was added instead. Its last check, `array_search('month_plan_not_approved', $kinds, true) < count($kinds) - 1`, is true even when the kind is missing, because `false < n` holds. The fixture has a 2026 Budget in execution, so only one budget card appears. As a result, the two-card year-order case named in ADR-0109 and in the plan's Critical Implementation Details (a draft 2026 Budget in December plus a draft or missing 2027 Budget → `[2026 draft, 2027 …]`) is not tested anywhere, and the kitchen sink has no fixture for it either.
- **Fix**: Add a feature test on 2026-12-10 with 2026 and 2027 both drafts (or 2027 missing) that asserts the last two notices are `{2026, draft}` and then `{2027, …}`. Replace the `array_search` check with `!== false` plus a position comparison.
- **Decision**: FIXED — the assertion now requires `month_plan_not_approved` to be present (`!== false`), and the new `test_dashboard_lists_a_draft_current_year_before_the_next_year_in_december` asserts the exact list `[2026 draft, 2027 missing]`, then `[2026 draft, 2027 draft]` (green on PostgreSQL).

### F2 — Phase 1 roadmap edit is uncommitted and mixed with unrelated status edits

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: context/foundation/roadmap.md:154, :231 (working tree only)
- **Detail**: The Phase 1 contract asked for two edits: the S-41 Unknowns line marked as answered, and the Backlog Handoff cell noting that MPL-1 was closed by `1659a87`. Both exist only in the uncommitted working tree. The p2 commit message says "roadmap.md left unstaged (pre-existing edits)". The same diff also contains status edits that do not belong to this change: S-40 and S-43 set to `ready`, the Ready column set to `yes` for S-40, S-41 and S-43, and S-41 set to `in-progress` (:59, :156) while `change.md` says `implemented`. The plan states that status sync is not part of this change.
- **Fix**: Commit only the two S-41 lines (Unknowns :154 and the Handoff cell :231) as `docs(year-turn-notices): …` and leave the other status edits uncommitted for whoever owns them, or drop them if they were copied from the main checkout.
  - Strength: The roadmap change that Phase 1 planned gets into history, and nothing unrelated rides along.
  - Tradeoff: The hunks have to be split by hand, because interactive `git add -p` is not available to agents.
  - Confidence: HIGH — the diff shows exactly which lines are S-41.
  - Blind spot: It is not verified whether the S-40/S-43 `ready` edits are the user's work in progress in the main checkout.
- **Decision**: FIXED — only the S-41 Unknowns line and the S-41 Handoff cell are committed (`docs(year-turn-notices)`); the S-40/S-43 `ready` edits, the Ready column and the S-41 `in-progress` stay uncommitted in the working tree for their owner.

### F3 — Helper became a public class, and the support rule does not cover it

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Support/BudgetNotInExecutionNotices.php:12; .ai/rules/support.md:2-3; .ai/rules/index.md (support row)
- **Detail**: The plan (`plan.md:179`) specified a private helper `budgetNotInExecutionNotices()` inside `DashboardNotices`. The code adds a new class with a public static `for()`. The intent is kept: `forDashboard` does not grow, and the Sloppy baseline is unaffected. But the plan was never updated, and the severity and order rule in `.ai/rules/support.md` has `paths:` set to `DashboardNotices.php` only, so it does not load when someone edits the new file.
- **Fix**: Add `app/Support/BudgetNotInExecutionNotices.php` to `support.md` `paths:` and to the index row, run `php artisan boost:index-rules`, and add a one-line addendum to the plan recording the class.
- **Decision**: FIXED — `.ai/rules/support.md` covers `app/Support/BudgetNotInExecutionNotices.php` and names it in the order contract, `boost:index-rules` regenerated `.ai/rules/index.md`, and the plan has an addendum.

### F4 — ADR-0050 and design-system.md contradict their own amended lines

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/adr/0050-dashboard-notice-labels-and-budget-block.md:20; context/foundation/design-system.md:87
- **Detail**:
  - ADR-0050:20 still says "`month_plan_not_approved` has no value in the payload, so its right slot stays empty", while its amended table at :15 gives the value „zatwierdź do DD.MM, 23:59”.
  - design-system.md:87 still says the month-plan card has an empty right slot and that every card is a link, while the missing-year card is now a button that opens a dialog (:85).
  - AGENTS.md: the glossary and docs describe only what the code has.
- **Fix**: Rewrite both sentences: the deadline value for `month_plan_not_approved`, and "every card leads to its cause (a link, or the „Dodaj rok” dialog for a missing year)".
- **Decision**: FIXED — ADR-0050 names the deadline value; design-system.md names the deadline, the Budget card value, its labels and the button card with `aria-haspopup="dialog"`.

### F5 — Every Manual verification row is pending while change.md said implemented

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/year-turn-notices/plan.md:395, :408-409, :423-424
- **Detail**: Rows 1.5 (docs confirmed before Phase 2), 2.5, 2.6 (kitchen sink scenarios and the links of the draft and missing cards), 3.6 (sidebar frame and waiting scenario) and 3.7 (rehearsal-clock browser check around the Polish midnight) are unchecked. The epilogue commit set `change.md` to `implemented` anyway. The diff holds no evidence for these checks, and the open questions in F6 and F9 (pill weight in the open menu, card links) can only be settled by them.
- **Fix**: Run the kitchen sink checks (`/dev/ui/dashboard`, `/dev/ui/sidebar`, light and dark) and the `app:rehearsal-clock` steps from Phase 3, then tick the rows.
- **Decision**: ACCEPTED — the user accepted all findings on 2026-11-19 and archived the change without running Manual rows 1.5, 2.5, 2.6, 3.6 and 3.7 (kitchen sinks in both themes, `app:rehearsal-clock`); the rows stay unchecked in Progress because the checks were not run. The pill weight from F9 is unchecked too.

### F6 — Kitchen sink scenarios are not fully realistic

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/dev/dashboard-fixtures.ts:139, :329-333, :551-579; resources/js/pages/dev/DashboardKitchenSink.vue:26; .ai/rules/js-sidebar.md (kitchen sink list)
- **Detail**:
  - `december-before-turn` and `next-year-missing` spread `defaultProps`, so `year_elapsed_as_of` is 2026-09-23. The status line reads September next to December-only cards.
  - The existing `draft` scenario keeps an empty notice list, which the server can no longer produce, because a draft current year always gets the card.
  - No scenario shows the two-card case (see F1).
  - The state-matrix copy and the `js-sidebar.md` kitchen sink list do not name `next-year-waiting` or `years-next-waiting`.
- **Fix**: Set December dates in the two scenarios, give `draft` its `budget_not_in_execution` card, add a two-card scenario, and name the new scenario and frame in the copy and in the rule.
- **Decision**: FIXED — `onDecemberDay()` gives `december-before-turn` (29.12), `next-year-missing` (10.12) and `next-year-waiting` (30.12) a December status line and month pace; `draft` has its 2026 card; new scenario `december-draft-turn` with two cards; the sink copy and `js-sidebar.md` name the new scenarios and the waiting frame; fixture tests pin them.

### F7 — DashboardNotices.vue: card branching is loosely typed and the pending flag name is stale

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/DashboardNotices.vue:41, :78, :103-114, :195, :337-356
- **Detail**:
  - `monthPlanRequestPending` now also covers the year card. Sharing one flag between the month cards existed before, so behaviour is consistent, but the name no longer says what it covers.
  - The missing-year `<button>` stays enabled while another card's POST is in flight.
  - `cardAttributes(): Record<string, unknown>` passed through `v-bind` takes the Link props (`href`, `data`, `as`, `disabled`) out of vue-tsc checking.
  - `@start/@error/@finish` are bound on the native button too, where they never fire.
  - The `DD.MM` formatting of `approve_by` is inline in the component and has no test. Per the js rules, pure logic belongs in `resources/js/lib` next to a `*.test.ts`.
- **Fix**: Split the template into `<button v-if="missing">` and `<Link v-else>` with shared inner markup, rename the flag (e.g. `selectYearPending`) and disable the button while it is set, and move the date formatting into `dashboard-notice-presentation.ts` with a Vitest case.
  - Strength: The Link props get type checking again, the dead listeners go, and the deadline format gets a test.
  - Tradeoff: It touches a component that already works, and the markup of the card body has to stay identical for both branches.
  - Confidence: MED — the behaviour is green today; this is maintainability.
  - Blind spot: Whether `check:ui` or the E2E suite selects cards by the current element structure is not checked.
- **Decision**: FIXED — the card body is `DashboardNoticeCardBody.vue` (added to both Dashboard UI guards), shared by `<button v-if>` and `<Link v-else>` with typed props; the flag is `selectYearRequestPending` and disables the missing-year button; `formatDayMonth` in `@/lib/format` with a Vitest case.

### F8 — Year menu and Dashboard line define "waiting" differently

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: resources/js/lib/budget-year-menu.ts:52; app/Http/Controllers/DashboardController.php:161
- **Detail**: The menu marks any Budget in execution with a year later than the current year. The Dashboard line names only `currentYear + 1`. A 2028 Budget in execution in 2026 is allowed, because `StartBudgetExecution` only requires a draft. In that case the menu says „od 1.01” for 2028 and the Dashboard says nothing. This is rare and probably acceptable.
- **Fix**: State in ADR-0109 that the Dashboard line names only the next year, or accept the difference as it is.
- **Decision**: FIXED — ADR-0109 states that the status line names only the next year, while the menu marks every later year in execution.

### F9 — Small consistency and accessibility nits

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Controllers/DashboardController.php:161; resources/css/app.css:650-655; resources/js/components/DashboardNotices.vue (missing card); resources/js/components/BudgetYearSwitcher.vue:282-294
- **Detail**:
  - The controller uses `$nextYearBudget?->phase === BudgetPhase::Execution` while the rest of the change uses `isExecution()`.
  - `.sidebar-year-state-waiting` duplicates `.sidebar-year-state-current` exactly.
  - The missing card opens a dialog but has no `aria-haspopup="dialog"`.
  - A screen reader reads „od 1.01” literally.
  - In the open menu „bieżący” is an unframed note, while „od 1.01” is a framed pill, so the future year looks stronger than the current one (check this in the F5 kitchen sink pass).
- **Fix**: Use `$nextYearBudget?->isExecution() === true` and drop the import, combine the two CSS selectors, add `aria-haspopup="dialog"`, and give the pill an sr-only „od 1 stycznia”.
- **Decision**: FIXED in part — `isExecution()` in the controller, one CSS rule for both outline pills, `aria-haspopup="dialog"` on the missing card. Not applied: an sr-only „od 1 stycznia”; the pill is read through `aria-describedby` of an `aria-hidden` container, and a second hidden text node would be read twice in some browsers. The pill weight in the open menu goes to the F5 check.

### F10 — Card copy overstates what Import refuses

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: lang/pl/ui.php:228 (`notice_value_import_refused`); ADR-0109
- **Detail**: „Import nie przyjmie wierszy z :year” reads as "no rows at all". Own transfers and capital movements are accepted in a draft year (`YearTurnTest::test_own_transfer_and_capital_movement_are_accepted_in_a_draft_year`). The glossary entry is precise (income, expense and refund), and the short copy was approved in ADR-0109, so this is a note only.
- **Fix**: Keep the copy as decided, or narrow it (for example „Import nie przyjmie dochodów ani wydatków z :year”) through an ADR-0109 amendment.
- **Decision**: ACCEPTED — the copy stays as decided in ADR-0109; the glossary keeps the precise scope (income, expense, refund).
