<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: S-06 „Do wydania” zamiast `Pozostało w miesiącu`

- **Plan**: context/changes/to-spend-replaces-remaining/plan.md
- **Scope**: Full plan (Phase 3 of 3)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-02
- **Verdict**: APPROVED
- **Findings**: 0 critical 0 warnings 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Verification run

- Targeted tests on PostgreSQL (Sail image on the worktree): MonthPlanPageTest, MonthToSpendTest, DashboardTest, BudgetTest, BudgetMonthResultTest, BudgetMonthRemainingTest, ToSpendCalculatorTest — 145 passed.
- Full suite on PostgreSQL: 703 passed, 2 skipped. (First `composer ci:check` run showed 3 failures "relation budgets does not exist" = shared `testing` DB collision with another job; rerun clean.)
- `npm run check` pass, `npm run types:check` pass, `npm test` 16 passed, `pint --test app tests lang` pass.
- Manual items 1.5, 2.5–2.8, 3.5–3.9 are ticked in Progress; not re-verifiable from the diff (UI/phone width). A `to-spend-preview` container exists locally, consistent with the previews having been run.
- Note: `context/foundation/roadmap.md` is modified but uncommitted in the worktree; not part of this change's commits.

## Findings

### F1 — Calculator inputs derived in two places

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Support/MonthToSpend.php:62-70, app/Http/Controllers/MonthPlanController.php:166-168
- **Detail**: `MonthToSpend::toSpendMinorUnits()` rebuilds plan / actual / has_positive_amount per category (`amounts[$id] ?? 0`, `actuals->actual()`, `hasPositiveAmount()`), and `MonthPlanController::itemRow` derives the same three values for the displayed rows. Today they agree (tests prove 373_000 on all three views). When S-02 changes what enters the number, the displayed rows and the headline could drift.
- **Fix**: Add a small accessor on `MonthToSpend` (e.g. `planAmount(Category)`) and use it in both places, or leave as is until S-02 touches the number.
- **Decision**: FIXED — added `MonthToSpend::rowAmounts(Category)`, used by `toSpendMinorUnits()` and `MonthPlanController::itemRow`.

### F2 — Stale "Month Remaining tile" mention in Dashboard spec

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: .docs/specs/dashboard-hierarchy.md:33
- **Detail**: Story 19 still says "the attention zone and the Month Remaining tile already answer ...". The tile is now "to spend" (plan Phase 2, item 6 aimed to make the spec match the code). Line 5 (Problem Statement) is historical and fine.
- **Fix**: Replace "Month Remaining tile" with "“to spend” tile" in story 19.
- **Decision**: FIXED — story 19 now says “to spend” tile.
