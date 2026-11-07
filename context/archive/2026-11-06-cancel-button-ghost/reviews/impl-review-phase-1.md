<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: „Anuluj” jako ghost — Phase 1

- **Plan**: context/changes/cancel-button-ghost/plan.md
- **Scope**: Phase 1 of 3
- **Reviewed phases**: 1
- **Date**: 2026-11-06
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

Success criteria run: `npm test -- resources/js/ui-contract.test.ts` (30 passed), `npm run check` (pass), `npm run types:check` (pass), `npm run check:ui` (pass). The "before the swap" step was verified by the orchestrator. Manual 1.5 is pending (user away), not a finding.

## Findings

### F1 — Guard matches only `<DialogClose …><Button …>` with `variant=` before any `>` in the Button tag

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/ui-contract.test.ts:62 (DIALOG_CLOSE_BUTTON), :166
- **Detail**: The regex `[^>]*` stops at the first `>`. In the seven swapped dialogs `@click="() => {` contains `=>`, so attributes are captured only up to there. This works because `variant` precedes `@click` in every file. If a future cancel Button puts `variant` after an `@click` with `=>`, the guard reports "no variant" (a loud false positive, not a pass). Other cases: `:variant="x"` is matched by `\bvariant=` and reported as an offender (loud); a missing `as-child` is harmless (the Button is still the direct child); a Button not directly inside DialogClose (wrapper element, comment between), or a cancel Button not in a DialogClose, is invisible to the guard (the plan accepted "direct child of DialogClose as-child"). I searched resources/js: every dialog cancel is in a DialogClose (the 7 form dialogs, TermDepositFormDialog, PasskeyItem, BudgetCategoryAlignPlanAction) or goes through ConfirmActionDialog (ghost); MonthPlanEditBar is the edit bar (ghost-inverse). No unguarded dialog cancel exists today. The guard cannot pass vacuously (found > 0 assertion, self-checking exception list).
- **Fix**: Accept as is. Optionally, if it ever bites, replace `[^>]*` on the Button tag with a pattern tolerant of `=>` (e.g. `(?:[^>=]|=(?!>))*`).
- **Decision**: ACCEPTED — the guard fails loudly (false "no variant"), never passes silently; scope "direct child of DialogClose" is the plan contract (unattended triage by orchestrator)

### F2 — Comment in the test is coupled to Phase 2

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/ui-contract.test.ts:54-57
- **Detail**: The comment says the align confirmation "is converted in Phase 2". After Phase 2 the BudgetCategoryAlignPlanAction exception is removed, so the comment must be updated then (plan Phase 2 §2 removes the entry but not the comment text).
- **Fix**: Trim the comment to the PasskeyItem/ADR-0049 reason in Phase 2.
- **Decision**: FIXED — comment trimmed in Phase 2 together with removing the exception entry

## Plan adherence

Seven form dialogs (Expense, Income, OwnTransfer, CapitalMovement, Account, Category, CreateBudget): `outline` -> `ghost` on the DialogClose Button only, nothing else in the footer changed (MATCH). Guard and exception list as specified, with a non-vacuous check and a self-checking list (MATCH). No extra files besides the plan Progress and change.md status. Plan Progress 1.1-1.4 ticked with the commit sha; 1.5 pending.
