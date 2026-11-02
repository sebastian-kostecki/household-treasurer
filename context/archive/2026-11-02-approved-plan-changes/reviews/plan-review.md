<!-- PLAN-REVIEW-REPORT -->
# Plan Review: S-03 — Zmiana zatwierdzonego planu miesiąca

- **Plan**: context/changes/approved-plan-changes/plan.md
- **Mode**: Deep
- **Date**: 2026-11-02
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | PASS |

## Grounding

13/13 paths ✓ (3 new files absent as expected), 6/6 symbols ✓, brief↔plan ✓, Progress↔Phase ✓.

Verified by a sub-agent: To Spend is 0 for a row with plan 0 / actual 0 in PHP and TS; `MonthPlanReading` is built only in `MonthPlanProposal`; no consumers outside the listed files; `MonthPlanPageTest` has no exact count assertion for an approved current/future month; Inertia 3.7.0 `<Form>` PATCH keeps `preserveState: true` and `onSuccess` runs after the new props are applied.

## Findings

### F1 — Every save adds all candidate rows to the plan at 0

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Reguły zmiany; Implementation Approach; Phase 2 §3
- **Detail**: The form sends hidden inputs for every editable row (`MonthPlan.vue:324-340`). The rule "no item → item 0/amount, also for 0" meant one unrelated save created 0/0 items for every join candidate and every "Poza planem" row with operations: the badge disappeared, 0,00 rows stayed in the read view, and S-09 lost the difference between a deliberate join and an incidental one. To Spend is the same with or without an item.
- **Fix A ⭐ Recommended**: The server skips "no item + amount 0"; only amount > 0 creates an item 0/amount.
  - Strength: Enforced on the server; the "Poza planem" badge keeps its meaning.
  - Tradeoff: A user can't deliberately mark "in plan at 0" (no effect on To Spend).
  - Confidence: HIGH — a missing item reads as 0 (`MonthPlanProposal.php:157`).
  - Blind spot: None significant.
- **Fix B**: Client sends only changed rows without an item.
  - Strength: Allows joining at 0 by typing it.
  - Tradeoff: The rule lives only in Vue; "changed" needs a definition.
  - Confidence: MED — no Vue tests in the repo.
  - Blind spot: Typing and then reverting to 0.
- **Decision**: FIXED (Fix A) — Reguły zmiany, Desired End State, Phase 1 test list, plan-brief decision table.

### F2 — Lock contract doesn't say where items are read

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §3 — Akcja
- **Detail**: Items eager-loaded before `lockForUpdate()` would let two tabs adding the same category hit unique(plan, category) → 500 on PostgreSQL; `ApproveMonthPlan` catches `UniqueConstraintViolationException`, the `ChangeMonthPlan` contract did not.
- **Fix**: Items read after the lock, inside the transaction (`$plan->items()->get()`).
- **Decision**: FIXED

### F3 — How candidate rows are hidden is unspecified

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §3
- **Detail**: Hiding candidates with a per-row `v-if` breaks the empty-list message (`section.rows.length === 0`, `MonthPlan.vue:361-366`); whether a candidate shows "Poza planem" was unspecified.
- **Fix**: Filter in `allRows`/`sections`; a candidate in change mode shows "Poza planem".
- **Decision**: FIXED
