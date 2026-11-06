# „Anuluj” jako ghost — Implementation Plan

## Overview

Apply the "Anuluj" part of ADR-0045 to the code: every dialog's cancel button is Button `ghost`, the edit bar keeps its `ghost-inverse` (the ghost of the `primary` surface), and a static test in `resources/js/ui-contract.test.ts` stops an `outline` or `secondary` cancel from coming back. The hand-written "Zakończyć kategorię?" confirmation moves to `ConfirmActionDialog`, and `/dev/ui/dialogs` gains its state.

## Current State Analysis

ADR-0045 (`context/foundation/adr/0045-dashboard-accounts-and-cancel-follow-design-system-pages.md`) says "Anuluj" is `ghost` in every dialog, in the edit bar and in the Month Plan in change mode; `outline` stays for secondary actions that are not a way out. The docs already follow it (`context/foundation/design-system.md:20`, `.ai/rules/js-dialogs.md`), but the code lags:

| File | Cancel variant today |
| --- | --- |
| `resources/js/components/ExpenseFormDialog.vue:634` | `outline` |
| `resources/js/components/IncomeFormDialog.vue:523` | `outline` |
| `resources/js/components/OwnTransferFormDialog.vue:346` | `outline` |
| `resources/js/components/CapitalMovementFormDialog.vue:398` | `outline` |
| `resources/js/components/AccountFormDialog.vue:274` | `outline` |
| `resources/js/components/CategoryFormDialog.vue:355` | `outline` |
| `resources/js/components/CreateBudgetDialog.vue:195` | `outline` |
| `resources/js/components/BudgetCategoryAlignPlanAction.vue:43` | `secondary` (hand-written Dialog) |
| `resources/js/components/PasskeyItem.vue:82` | `secondary`, English "Cancel" (out of scope) |
| `resources/js/components/ConfirmActionDialog.vue:72` | `ghost` ✓ (ADR-0049) |
| `resources/js/components/TermDepositFormDialog.vue:272` | `ghost` ✓ |
| `resources/js/components/MonthPlanEditBar.vue:54` | `ghost-inverse` ✓ (edit bar on `primary`) |

`design-system.md:20` still carries the transitional note "dialogi formularzy dostają go ze zmianą wdrażającą ADR-0045" — this is that change.

The Month Plan in change mode has no cancel outside the edit bar: when `isChanging` the title row hides "Zmień" (`resources/js/pages/MonthPlan.vue:602`) and the only "Anuluj" is `MonthPlanEditBar` (`MonthPlan.vue:698`).

## Desired End State

- Every Button inside a `<DialogClose>` in a `.vue` file outside `components/ui` is `variant="ghost"`, except `PasskeyItem.vue`, and `npm test` fails if that stops being true.
- "Zakończyć kategorię?" (Budget row menu → "Zakończ") is a `ConfirmActionDialog`: ghost "Anuluj", spinner on "Zakończ", close blocked while saving, server errors in the dialog, closes on success.
- `BudgetCategoryAlignPlanAction.vue` no longer exists.
- `/dev/ui/dialogs` shows every dialog with a ghost "Anuluj", including a new state `confirm-align-plan`.
- The edit bar keeps `ghost-inverse`; `design-system.md` says that on the edit bar "ghost" means `ghost-inverse`, and the transitional note is gone.

Verify: `composer ci:check` green; open `/dev/ui/dialogs?open=<state>` for a form dialog, a confirmation and the new state, and the Month Plan in change mode.

### Key Discoveries:

- Every dialog cancel has the same shape: `<DialogClose as-child>` followed by a multi-line `<Button type="button" variant="…">` (`ExpenseFormDialog.vue:630-646`), so a template regex can find them.
- `ui-contract.test.ts` already does static source checks with a self-checking exception list (`DEAD_CHECKBOX_BINDING_EXCEPTIONS`, `resources/js/ui-contract.test.ts:47-51`) — the new guard copies that shape.
- `ConfirmActionDialog` supports `v-model:open` with no trigger (`ConfirmActionDialog.vue:32-33`), as the archive/restore/delete dialogs in `BudgetCategoryRowActions.vue:126-152` already use it.
- `BudgetCategoryAlignPlanAction.vue` is listed in `BUDGET_VIEW_FILES` (`ui-contract.test.ts:28`) and in `scripts/check-ui-literals.mjs:86`; deleting the file means removing both entries.
- Plain `ghost` hovers in `accent`, which a component class cannot override on the ink edit bar — that is why `ghost-inverse` exists (`.ai/rules/js-month-plan.md`, `design-system.md:15`).
- Copy for the confirmation is in `lang/pl/ui.php:254-256` (`align_current_plan` = "Zakończ", `…_confirm_title`, `…_confirm_body`); `t.budget.cancel` is "Anuluj".

## What We're NOT Doing

- `PasskeyItem.vue` (and `PasskeyRegister.vue`): the 2FA/passkeys sections wait for their own change (ADR-0049); `PasskeyItem.vue` is the only entry in the guard's exception list.
- `MonthPlanEditBar.vue`: no code change; `ghost-inverse` stays.
- No change to `components/ui/button` or `components/ui/dialog`, no new variant, no new token.
- No new ADR: this implements ADR-0045; the glossary does not change.
- No change to the backend of aligning a category (`tests/Feature/AlignCategoryCurrentPlanTest.php` stays as is).
- `outline` on non-exit actions ("Importuj", "Zmień", kitchen-sink state buttons) stays.

## Implementation Approach

Test first: add the guard to `ui-contract.test.ts` (with `BudgetCategoryAlignPlanAction.vue` as a temporary exception), see it fail listing the seven `outline` dialogs, then swap the variants. Phase 2 removes the last offender by moving the confirmation onto `ConfirmActionDialog` and shows it in the kitchen sink. Phase 3 brings the docs in line.

## Critical Implementation Details

The guard must not pass vacuously: assert that it found at least one `<DialogClose>` Button overall, and that every exception still offends (as the checkbox exception list does), so a stale exception fails the test. The Button's attributes span several lines, so match up to the Button's closing `>` across newlines; dialog cancel Buttons carry no `=>` in their attributes today.

## Phase 1: Guard and form dialogs

### Overview

A static test makes "Anuluj is ghost" executable, then the seven form dialogs switch from `outline` to `ghost`.

### Changes Required:

#### 1. Dialog cancel guard

**File**: `resources/js/ui-contract.test.ts`

**Intent**: Fail when a Button that closes a dialog (direct child of `<DialogClose as-child>`) is not `ghost`, naming file and variant, so ADR-0045 cannot drift back as ADR-0040 did.

**Contract**: New constant `DIALOG_CANCEL_VARIANT_EXCEPTIONS: string[] = ['components/PasskeyItem.vue', 'components/BudgetCategoryAlignPlanAction.vue']` with a comment pointing to ADR-0049 (passkeys) and to Phase 2 (the align confirmation), saying the list only shrinks. New `it` that walks `vueFilesOutsideUi(JS_ROOT)`, finds every `<DialogClose …>` immediately followed by `<Button …>`, and collects offenders whose Button lacks `variant="ghost"`. It fails on offenders outside the exception list, on exceptions that no longer offend, and if no `<DialogClose>` Button was found at all.

#### 2. Form dialogs

**Files**: `resources/js/components/ExpenseFormDialog.vue`, `IncomeFormDialog.vue`, `OwnTransferFormDialog.vue`, `CapitalMovementFormDialog.vue`, `AccountFormDialog.vue`, `CategoryFormDialog.vue`, `CreateBudgetDialog.vue`

**Intent**: The cancel Button in the dialog footer becomes `ghost` (ADR-0045).

**Contract**: `variant="outline"` → `variant="ghost"` on the Button inside `<DialogClose as-child>`; nothing else in the footer changes.

### Success Criteria:

#### Automated Verification:

- Before the variant swap the new guard fails and lists the seven form dialogs: `npm test -- resources/js/ui-contract.test.ts`
- After the swap the guard passes (both exceptions still offend, so the self-check holds): `npm test -- resources/js/ui-contract.test.ts`
- Lint, format and type check pass: `npm run check` and `npm run types:check`
- UI literal check passes: `npm run check:ui`

#### Manual Verification:

- In `/dev/ui/dialogs` the expense, account and category dialogs show "Anuluj" as a text button with no border, hover `accent`, focus ring visible (light and dark theme)

**Implementation Note**: `BudgetCategoryAlignPlanAction.vue` sits on the exception list only so the Phase 1 commit is green; Phase 2 removes it. After automated verification, pause for manual confirmation before Phase 2.

---

## Phase 2: "Zakończyć kategorię?" on ConfirmActionDialog and the kitchen sink

### Overview

The last hand-written confirmation with a non-ghost cancel moves to `ConfirmActionDialog`, and `/dev/ui/dialogs` gets its state.

### Changes Required:

#### 1. Budget row actions

**File**: `resources/js/components/BudgetCategoryRowActions.vue`

**Intent**: Render the align confirmation with `ConfirmActionDialog`, like archive/restore/delete in the same file, so it gets a ghost "Anuluj", a spinner, a close lock and server errors.

**Contract**: `<ConfirmActionDialog v-model:open="alignDialogOpen" :form="CategoryController.alignCurrentPlan.form(category)" :title="t.budget.align_current_plan_confirm_title" :description="t.budget.align_current_plan_confirm_body" :confirm-label="t.budget.align_current_plan" :cancel-label="t.budget.cancel" />` replaces `<BudgetCategoryAlignPlanAction>`; the import goes. Not `destructive`.

#### 2. Remove the wrapper

**Files**: `resources/js/components/BudgetCategoryAlignPlanAction.vue` (delete), `resources/js/ui-contract.test.ts` (`BUDGET_VIEW_FILES`), `scripts/check-ui-literals.mjs:86`

**Intent**: No dead component and no list entry pointing at a missing file.

**Contract**: File deleted; its path removed from both lists and from `DIALOG_CANCEL_VARIANT_EXCEPTIONS`, leaving only `components/PasskeyItem.vue` there.

#### 3. Kitchen sink state

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`

**Intent**: Show the moved confirmation next to the other confirmations, so every confirmation in the app has a state in `/dev/ui/dialogs`.

**Contract**: New state `'confirm-align-plan'` after `'confirm-deficit'` in `states`, label "Potwierdzenie: zakończ kategorię" in `labels`, and a `ConfirmActionDialog` with `plainForm`, title "Zakończyć kategorię?", the body from `lang/pl/ui.php:256`, confirm "Zakończ", cancel "Anuluj".

### Success Criteria:

#### Automated Verification:

- The guard and the whole UI contract pass: `npm test -- resources/js/ui-contract.test.ts`
- Frontend gate passes: `npm run check`, `npm run check:ui`, `npm run types:check`, `npm test`
- Backend of aligning still passes on PostgreSQL: `php artisan test --compact tests/Feature/AlignCategoryCurrentPlanTest.php` (worktree command from AGENTS.md)
- `BudgetCategoryAlignPlanAction` is referenced nowhere: `grep -rn BudgetCategoryAlignPlanAction resources scripts tests` returns nothing

#### Manual Verification:

- `/dev/ui/dialogs?open=confirm-align-plan` shows "Zakończyć kategorię?" with a ghost "Anuluj" and a primary "Zakończ"
- On the Budget page, a category in execution → "⋯" → "Zakończ" opens the same dialog; confirming shows the spinner, closes the dialog and sets Pozostało to 0

**Implementation Note**: After automated verification, pause for manual confirmation before Phase 3.

---

## Phase 3: Docs

### Overview

The docs stop describing ADR-0045 as pending and name the guard.

### Changes Required:

#### 1. Design system page

**File**: `context/foundation/design-system.md`

**Intent**: Line 20 drops "dialogi formularzy dostają go ze zmianą wdrażającą ADR-0045" (now done) and says the rule is guarded by `ui-contract.test.ts`, with `PasskeyItem` as the one exception until the passkeys change; line 15 says that on the edit bar "ghost" means `ghost-inverse`.

**Contract**: Sections "Kontrolki" (line 15) and "Dialog" (line 20) only.

#### 2. Dialog rule

**File**: `.ai/rules/js-dialogs.md`

**Intent**: The sentence "Anuluj is `ghost` (ADR-0045)" adds that `ui-contract.test.ts` fails on a non-ghost Button in `DialogClose` and that the exception list only shrinks; then regenerate the rule index.

**Contract**: First section of the rule; run `php artisan boost:index-rules`.

### Success Criteria:

#### Automated Verification:

- Full gate passes: `composer ci:check` (worktree command from AGENTS.md) and `vendor/bin/sloppy ci`
- No transitional note left: `grep -n "zmianą wdrażającą ADR-0045" context/foundation/design-system.md` returns nothing

#### Manual Verification:

- Month Plan in change mode: the edit bar shows "Anuluj" as `ghost-inverse` (light text, hover lightens), unchanged

---

## Testing Strategy

### Unit Tests:

- `ui-contract.test.ts`: the dialog cancel guard (red before Phase 1's swap, green after it), its exception list self-check (Phase 2 shrinks it to `PasskeyItem.vue`) and the non-vacuous check.
- Existing `BUDGET_CONFIRMATION_FILES` case keeps `BudgetCategoryRowActions.vue` free of `DialogContent`.

### Integration Tests:

- `tests/Feature/AlignCategoryCurrentPlanTest.php` unchanged and green (the endpoint does not change).

### Manual Testing Steps:

1. `/dev/ui/dialogs`: open a form dialog, a confirmation and `confirm-align-plan`; every "Anuluj" is ghost, in light and dark theme.
2. Budget → "⋯" → "Zakończ" on a category in execution: spinner, close lock, dialog closes, Pozostało = 0.
3. Month Plan → "Zmień": the edit bar's "Anuluj" is `ghost-inverse` and leaves change mode.

## Performance Considerations

None.

## Migration Notes

None; frontend and docs only.

## References

- ADR: `context/foundation/adr/0045-dashboard-accounts-and-cancel-follow-design-system-pages.md`, ADR-0049 (ghost in `ConfirmActionDialog`, passkeys held back), ADR-0040 (superseded "Anuluj is outline")
- Design system: `context/foundation/design-system.md:15`, `:20`
- Rules: `.ai/rules/js-dialogs.md`, `.ai/rules/js-month-plan.md`, `.ai/rules/js.md` ("Confirmations use ConfirmActionDialog")
- Pattern: `resources/js/components/BudgetCategoryRowActions.vue:126-152`, `resources/js/ui-contract.test.ts:47-51`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Guard and form dialogs

#### Automated

- [x] 1.1 Guard fails before the swap, listing the seven form dialogs
- [x] 1.2 After the swap the guard passes with both exceptions still offending
- [x] 1.3 npm run check and npm run types:check pass
- [x] 1.4 npm run check:ui passes

#### Manual

- [ ] 1.5 Form dialogs in /dev/ui/dialogs show a ghost Anuluj in both themes

### Phase 2: "Zakończyć kategorię?" on ConfirmActionDialog and the kitchen sink

#### Automated

- [ ] 2.1 ui-contract.test.ts passes including the guard
- [ ] 2.2 Frontend gate passes (check, check:ui, types:check, npm test)
- [ ] 2.3 AlignCategoryCurrentPlanTest passes on PostgreSQL
- [ ] 2.4 BudgetCategoryAlignPlanAction is referenced nowhere

#### Manual

- [ ] 2.5 /dev/ui/dialogs?open=confirm-align-plan shows ghost Anuluj and primary Zakończ
- [ ] 2.6 Budget row Zakończ confirms with spinner, closes and sets Pozostało to 0

### Phase 3: Docs

#### Automated

- [ ] 3.1 composer ci:check and vendor/bin/sloppy ci pass
- [ ] 3.2 No transitional ADR-0045 note left in design-system.md

#### Manual

- [ ] 3.3 Month Plan edit bar Anuluj stays ghost-inverse
