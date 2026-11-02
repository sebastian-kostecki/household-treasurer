<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: S-01 Plan miesiąca i liczba „do wydania”

- **Plan**: context/changes/month-plan-and-to-spend/plan.md
- **Scope**: Full plan (4 of 4 phases)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-02
- **Verdict**: NEEDS ATTENTION (all 5 findings fixed in triage, details in the plan's Addenda; ci:check green, 690 passed)
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS (extras noted as observation F4) |
| Safety & Quality | WARNING (2 findings) |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Success Criteria: `composer ci:check` on PostgreSQL passed on the final code (686 passed, 2 skipped); PostgreSQL runs of the new feature tests passed per phase; all 29 Progress items are `[x]` with a SHA; manual checks of phases 1, 2, 3 and 4 were confirmed by the user.

## Findings

### F1 — No upper bound on plan amounts

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/ApproveMonthPlanRequest.php:~30, app/Support/ToSpendCalculator.php, resources/js/lib/format.ts:31-46
- **Detail**: `items.*.amount_minor_units` is `required|integer|min:0` with no `max`. A single value cannot overflow the bigint column, but several large items can overflow the sum in `ToSpendCalculator::toSpendMinorUnits`: PHP turns the int into a float and the `: int` return throws a TypeError, so the page returns 500 once such a plan is stored. In the browser, values above 2^53 lose precision in `parseMoneyInputToMinorUnits`. The plan asked for the same limit as `planned_amount_minor_units`, but that rule is also min-only today.
- **Fix**: Add a realistic `max:` (for example 10^12 minor units) to the rule, and reject values above `Number.MAX_SAFE_INTEGER` in the Vue amount parsing.
  - Strength: Closes the 500 path at the boundary and keeps the TS preview exact.
  - Tradeoff: Introduces a limit that the annual budget amounts do not have yet.
  - Confidence: HIGH — reviewer traced the overflow to the typed return.
  - Blind spot: Whether to apply the same cap to `planned_amount_minor_units`.
- **Decision**: FIXED

### F2 — Restore of `month_plans` trusts the snapshot structure

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/RestoreUserData.php (`restoreMonthPlans`)
- **Detail**: Keys `budget_id`, `month`, `approved_at` and `category_id` are read without `isset` checks, `month` is not checked against 1..12, and a duplicate `(budget_id, month)` or duplicate category in a plan gives a raw `UniqueConstraintViolationException`. An item's category is not checked to belong to the plan's budget. A malformed or hand-edited snapshot yields `ErrorException`/Carbon errors instead of `UserSnapshotException`. The restore transaction rolls back, so nothing is left half-restored.
- **Fix**: Validate keys and the month range, check that the category belongs to the plan's budget and throw `UserSnapshotException`, matching sibling `restore*` methods.
  - Strength: Consistent error handling for broken snapshots.
  - Tradeoff: More validation code for an admin-only command.
  - Confidence: MEDIUM — depends on how strict sibling methods are.
  - Blind spot: Not checked how sibling methods handle missing keys.
- **Decision**: FIXED

### F3 — Approval of a future month does not require earlier months to be approved

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/ApproveMonthPlan.php
- **Detail**: Only past and already-approved months are blocked. The proposal chains from the previous month, which may itself be only a proposal. This matches ADR-0033 (recursive proposal), so it is probably intended.
- **Fix**: None needed unless the product wants to forbid skipping months.
- **Decision**: FIXED

### F4 — Small additions beyond the plan

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: AGENTS.md ("Preview from a worktree"), resources/js/types/shell.ts
- **Detail**: The preview section was added at the user's request after Phase 3; `shell.ts` types are implied by the plan's translation keys. Both are low risk.
- **Fix**: None; optionally add a one-line addendum to the plan.
- **Decision**: FIXED

### F5 — Kind toggle ignores PATCH errors

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/MonthPlan.vue (`changeKind`)
- **Detail**: `router.patch` has no `onError`, so a validation failure is silent. The server already rejects income and archived categories, and the UI hides the toggle there.
- **Fix**: Add an `onError` that surfaces the message (toast or inline).
- **Decision**: FIXED
