<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: „Anuluj” jako ghost

- **Plan**: context/changes/cancel-button-ghost/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
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

Success criteria run: `grep -n "zmianą wdrażającą ADR-0045" context/foundation/design-system.md` (no match), `npm test -- resources/js/ui-contract.test.ts` (29 passed), `npm run check:ui` (nothing flagged), `vendor/bin/sloppy ci` (exit 0). `composer ci:check` on PostgreSQL was run by the orchestrator (exit 0, 935 passed, 2 skipped) and not repeated. Manual 1.5, 2.5, 2.6, 3.3 are pending (user away), not findings.

## Docs vs code (Phase 3)

- design-system.md "Dialog": "Button bezpośrednio w `DialogClose` bez `variant="ghost"` oblewa test", sole exception `PasskeyItem`, list only shrinks. Matches `DIALOG_CLOSE_BUTTON` (`<DialogClose …><Button …>`), `variant !== 'ghost'` check and `DIALOG_CANCEL_VARIANT_EXCEPTIONS = ['components/PasskeyItem.vue']` (self-checked: a stale exception fails). MATCH.
- design-system.md "Kontrolki": on the primary surface (edit bar) "Anuluj" is `ghost-inverse`. `MonthPlanEditBar.vue:54` uses `variant="ghost-inverse"`; `.ai/rules/js-month-plan.md:15` agrees. MATCH.
- js-dialogs.md: same statement, names `DIALOG_CANCEL_VARIANT_EXCEPTIONS`. `.ai/rules/index.md` unchanged and needs no change (no new rule file, same globs). MATCH.
- Transitional note "zmianą wdrażającą ADR-0045" removed (grep empty).

## Desired End State

- Every `DialogClose` Button is ghost except PasskeyItem, enforced by test: met.
- "Zakończyć kategorię?" on `ConfirmActionDialog`: met (Phase 2 review parity check).
- `BudgetCategoryAlignPlanAction.vue` gone, no references: met.
- `/dev/ui/dialogs` state `confirm-align-plan`: present (states, labels, render). The only remaining `outline` there is a state-trigger button (allowed).
- Edit bar keeps `ghost-inverse`, doc says so, note gone: met.

## What We're NOT Doing

`git diff 5b1bc04..HEAD` touches none of: PasskeyItem/PasskeyRegister, MonthPlanEditBar, `components/ui/*`, `app/`, `lang/`, `database/`, `routes/`, ADRs, glossary. No new ADR. Respected.

## Findings

### F1 — Guard does not cover the edit bar's "Anuluj"

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/ui-contract.test.ts (DIALOG_CLOSE_BUTTON); resources/js/components/MonthPlanEditBar.vue:54
- **Detail**: The edit bar is not a dialog, so its `ghost-inverse` Anuluj is outside `DialogClose` and unguarded. design-system.md lists "w pasie edycji" in the same sentence as the guard, then says the test guards "Button bezpośrednio w `DialogClose`", which is accurate but could be read as covering the bar. The plan never promised a guard for the bar (it says no code change there), so this is not drift.
- **Fix**: Accept as is; optionally later add a one-line assertion that `MonthPlanEditBar.vue` keeps `variant="ghost-inverse"`.
- **Decision**: ACCEPTED — the plan scoped the guard to DialogClose; edit bar keeps ghost-inverse, code and docs agree (unattended triage by orchestrator)

### F2 — ADR-0049 sentence about form dialogs is now historical

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: context/foundation/adr/0049-*.md:~15 ("The form dialogs keep their current button until the change that implements ADR-0045 for them.")
- **Detail**: True when written, stale now that this change landed. ADRs are append-only records of the decision at that time; ADR-0045 plus the updated design-system.md carry the current state. Plan said no new ADR and the glossary does not change.
- **Fix**: Leave as is (optionally mention in the archive note that it was implemented here).
- **Decision**: DISMISSED — ADRs are append-only records; ADR-0045 and design-system.md carry the current state

## Notes

- Phase 1 F1/F2 and Phase 2 F1 were triaged in their own reports. The plan.md sha-suffix edits (uncommitted in the working tree) are pending bookkeeping that must go into the next commit; Progress 1.1-3.2 are backed by evidence.
