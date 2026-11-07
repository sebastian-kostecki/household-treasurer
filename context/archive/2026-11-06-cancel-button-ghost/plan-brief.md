# „Anuluj” jako ghost — Plan Brief

> Full plan: `context/changes/cancel-button-ghost/plan.md`

## What & Why

ADR-0045 says "Anuluj" is Button `ghost` in every dialog, in the edit bar and in the Month Plan in change mode; `outline` is for secondary actions that are not a way out. The docs already say so, the code does not: seven form dialogs still have an `outline` cancel. This change brings the code in line and adds a test so it cannot drift again, as it did after ADR-0040.

## Starting Point

`ConfirmActionDialog` and `TermDepositFormDialog` are already `ghost`; the edit bar uses `ghost-inverse`, the ghost of the dark `primary` surface. Seven form dialogs use `outline`, the "Zakończyć kategorię?" confirmation is a hand-written Dialog with a `secondary` cancel, and `PasskeyItem` has a `secondary` English "Cancel".

## Desired End State

Every dialog closes through a ghost "Anuluj", including "Zakończyć kategorię?", which now has a spinner, a close lock and server errors like the other confirmations. `/dev/ui/dialogs` shows that confirmation too. `npm test` fails if a Button inside `DialogClose` is anything but `ghost`, with `PasskeyItem` as the only listed exception.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| "Zakończyć kategorię?" | Move to `ConfirmActionDialog`, delete `BudgetCategoryAlignPlanAction.vue` | The rule says confirmations use `ConfirmActionDialog`, and it brings ghost cancel, spinner and close lock in one step. |
| Passkeys dialog | Out of scope, the only guard exception | ADR-0049 keeps 2FA/passkeys for their own change, where copy and look change together. |
| Edit bar | Keep `ghost-inverse` | Plain `ghost` hovers in `accent` on the ink bar; design-system.md and the month-plan rule already define `ghost-inverse` as the secondary button there. |
| Regression guard | Static case in `ui-contract.test.ts` with a self-checking exception list | Runs in `npm test` / `ci:check` and covers dialogs added later. |
| ADR | None | The change implements ADR-0045; no decision or glossary term changes. |

## Scope

**In scope:**

- `outline` → `ghost` in Expense, Income, OwnTransfer, CapitalMovement, Account, Category and CreateBudget dialogs
- "Zakończyć kategorię?" on `ConfirmActionDialog`, wrapper deleted, lists updated
- New state `confirm-align-plan` in `/dev/ui/dialogs`
- Guard in `ui-contract.test.ts`
- `design-system.md` (lines 15 and 20) and `.ai/rules/js-dialogs.md`

**Out of scope:**

- `PasskeyItem` / `PasskeyRegister` (passkeys change)
- `MonthPlanEditBar` code, `components/ui/*`, new variants or tokens
- Backend of aligning a category

## Architecture / Approach

Test first: the guard scans every `.vue` outside `components/ui` for `<DialogClose>` followed by a `<Button>` and requires `variant="ghost"`. It fails on offenders, on stale exceptions and when it finds nothing. Phase 1 lands it green by fixing the seven dialogs, with the align confirmation as a temporary exception; Phase 2 removes that exception by moving the confirmation to `ConfirmActionDialog`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Guard and form dialogs | Failing-then-green guard, seven dialogs on `ghost` | A regex that misses a multi-line Button and passes vacuously (the non-empty check guards it) |
| 2. "Zakończyć kategorię?" + kitchen sink | Confirmation on `ConfirmActionDialog`, new sink state, wrapper gone | The dialog now closes on success; check the Budget row still updates |
| 3. Docs | design-system.md and js-dialogs rule match the code | None significant |

**Prerequisites:** worktree set up (`composer install`, `npm ci`, `npm run build`); PostgreSQL via the worktree Docker command for `AlignCategoryCurrentPlanTest`.
**Estimated effort:** one short session across 3 phases.

## Open Risks & Assumptions

- Assumes every dialog cancel is the direct `<Button>` child of `<DialogClose as-child>`; a cancel written another way would escape the guard.
- `ConfirmActionDialog` closes on success, the old wrapper did not; this is the intended behaviour of every confirmation.

## Success Criteria (Summary)

- Every dialog in `/dev/ui/dialogs` and in the app has a ghost "Anuluj"; the edit bar's "Anuluj" is unchanged.
- "Zakończ" on a Budget row shows a spinner, locks closing and closes when done.
- `composer ci:check` passes, and changing any dialog cancel back to `outline` makes `npm test` fail.
