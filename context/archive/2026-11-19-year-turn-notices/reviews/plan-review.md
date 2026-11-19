<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Year Turn Notices Implementation Plan

- **Plan**: context/changes/year-turn-notices/plan.md
- **Mode**: Deep
- **Date**: 2026-11-19
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 0 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

19/19 paths ✓, 8/8 symbols ✓ (forDashboard, findBudgetForYear, openForYear, budgetYearMenu, sidebar-year-state-current, isPast, checkYearInExecution, select-year destinations), brief↔plan ✓, Progress↔Phase ✓ (18/18 rows).

Confirmed against the code:
- The notices zone renders for a draft and for a missing Budget (`Dashboard.vue:191`).
- `openForYear` works on the Dashboard and in the kitchen sink (dialog mounted in `AppSidebarLayout.vue:38-42`).
- `select-year` accepts a draft year. A missing year gets a 422, so the plan's dialog path for that state is the right one.
- Import (`FinancialOperationImportValidator.php:91-111`) and hand entry (`OperationCategoryChoice.php:80-88`) refuse both draft and missing years.
- The six-card limit has „Pokaż wszystkie”.
- Budget factory years are random (2020–2030), so no December CI flake.

## Findings

### F1 — Draft current-year card also fires during first-run setup

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1 (glossary, Budget; ADR-0109 examples), Phase 2 §5 Tests
- **Detail**: "Card whenever the current year's Budget is a draft" holds in every month. A new user drafting a first Budget sees a fourth draft signal next to the badge, the status line and the draft card. `DashboardTest::test_dashboard_has_no_month_notices_without_a_budget_in_execution` (:1312-1356) asserts `has('dashboardNotices', 1)` with a draft 2026 and will go red, but the plan named only `YearTurnTest:224` as a changed requirement.
- **Fix A ⭐ Recommended**: Keep the rule (decision 5), name the test as a changed requirement and add a "draft in March" example to ADR-0109.
  - Strength: Matches the decision already made; one rule.
  - Tradeoff: First-run Dashboard gets a fourth draft signal.
  - Confidence: HIGH — test and render paths confirmed.
  - Blind spot: How the first-run checklist reads next to the warning.
- **Fix B**: Narrow the draft-current card to the year turn (e.g. only when a previous-year Budget exists).
  - Strength: First run stays quiet; the test is unchanged.
  - Tradeoff: Second condition in glossary and ADR; reverses decision 5.
  - Confidence: MED — "has a previous Budget" is a proxy, not a domain term.
  - Blind spot: A user who skipped a year.
- **Decision**: FIXED (Fix A)

### F2 — Files and spots missing from the change list

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §3–§4, Phase 3 §1
- **Detail**: Not listed in the plan:
  - `UiTranslationKeysTest.php:48-55` (`notice_label_*` list)
  - `DashboardNoticeSeverity.php:8` docblock
  - In `DashboardNotices.vue`: `isMonthNotice` (:78-94) and `noticeIdentity` (:104-110); every card is a `<Link>` (:256), but `missing` needs a button
  - Three `month_plan_not_approved` fixtures (:150, :500, :513); `draftProps` inherits an empty list (:326-330)
  - `DashboardKitchenSink.vue:27` „7 rodzajów”
  - `hasState` in `BudgetYearSwitcher.vue:107-109` (without it there is no `aria-describedby` for the mark)
  - `toEqual` in `budget-year-menu.test.ts` (:15-35, :81-95)
  - The E2E helper `tests/e2e/support/budget-year.ts:29-56` reads `/\d{4}/` from the row
- **Fix**: Added these items to the Files and Contract of Phase 2 §1, §3, §4 and Phase 3 §1.
- **Decision**: FIXED

### F3 — forDashboard grows further, and default null means "missing"

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 2 §1
- **Detail**: `forDashboard` is ~196 lines and in the Sloppy baseline (SL101, complexity 32, `.sloppy-baseline.json:51-54`). The planned `?Budget $nextYearBudget = null` makes "not passed" read as "no next-year Budget", which would emit a `missing` card.
- **Fix**: Private helper `budgetNotInExecutionNotices($budget, $nextYearBudget, $today)`, with the parameter required, placed right after `$budget`.
- **Decision**: FIXED

### F4 — The status line example has no weekday

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Desired End State
- **Detail**: `formatLongDate` (`resources/js/lib/format.ts:140-150`) includes the weekday, so the real line reads „środa, 30 grudnia 2026 · …”.
- **Fix**: Example corrected.
- **Decision**: FIXED
