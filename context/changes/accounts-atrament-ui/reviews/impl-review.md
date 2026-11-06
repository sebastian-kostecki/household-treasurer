<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Konta w systemie „C · Atrament i morski”

- **Plan**: context/changes/accounts-atrament-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-06
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 4 observations

Coverage note: phases 1, 3, 4 and 5 have their Automated steps ticked and their Manual steps still pending. Phase 2 has no Manual steps. All five phases were reviewed against `d933db0..53ee426`.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Automated criteria were all re-run green: `composer ci:check` in the Sail image (902 passed, 2 skipped), `vendor/bin/sloppy ci`, `npm run check`, `npm run types:check`, `npm run check:ui`, `npm test` (266), the targeted PostgreSQL tests of each phase, both grep checks, and a break-check for every phase that added tests. No Manual item is ticked. All of them are pending: 1.3, 3.5–3.8, 4.5–4.8, 5.4–5.7.

Unplanned but harmless additions: the sink-only props `defaultType` and `form` on `AccountFormDialog`, which follow the `CategoryFormDialog` pattern; the `account-saving` sink state; and `ListSurface flush` in `Accounts.vue`. "What We're NOT Doing" is respected. `components/ui/*`, `.holdings-list-row`, `AccountIdentityBlock`, `AccountMarkCompact`, `StatTile` and Lokaty are untouched.

## Findings

### F1 — Dialog error and saving screenshots show the idle dialog

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/accounts-atrament-ui/screenshots/dialog-account-errors-*.png, dialog-account-saving-{1280,390}-*.png
- **Detail**: Five of the six `errors`/`saving` shots are byte-identical (md5) to the matching `dialog-account-bank-new-*` shot. The two 1280-light shots are identical to each other. Both states only appear after a submit to the dev `validate` endpoint, and the script never submitted. So server validation errors have no screenshot. The spinner is covered by `dialog-account-saving-live-*`.
- **Fix**: Re-capture `dialog-account-errors-*` after submitting the sink form, so the errors render. Delete the static `dialog-account-saving-{1280,390}-*` copies, since the `-live` shots cover the spinner.
- **Decision**: FIXED. `dialog-account-errors-*` was re-captured after a submit to the dev validate endpoint: field errors show under all five fields, and the md5 differs from bank-new. The four static `dialog-account-saving-{1280,390}-*` copies are deleted. The `-live` shots stay.

### F2 — Bank field skips the FormField slot (no aria-describedby, coupled id)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/AccountFormDialog.vue:203-213, resources/js/components/AccountBankSelect.vue:55
- **Detail**: The plan puts Bank "przez `FormField` … `AccountBankSelect` jest kontrolką w slocie". The FormField has no `v-slot`, so `describedBy`/`invalid` never reach the control:
  - the `account-bank-error` text is not linked to the trigger, unlike every other field in the dialog;
  - the label works only because the FormField `id="account-bank"` matches the id hardcoded in `AccountBankSelect`.

  The old markup had the same gap, so this is not a regression. It leaves the ADR-0040 contract incomplete for this field.
- **Fix A ⭐ Recommended**: Add optional `id`, `ariaDescribedby` and `invalid` props to `AccountBankSelect`, with defaults keeping today's behaviour, and pass them from the FormField slot.
  - Strength: completes the FormField contract and removes the id coupling, as in the other fields of this dialog and in `CategoryFormDialog`.
  - Tradeoff: touches a component shared with other dialogs (`AccountBankSelect` is outside this change's file list), although the props are optional and backward compatible.
  - Confidence: MED. The other consumers were not inspected for id assumptions.
  - Blind spot: whether `AccountBankSelect` is used in Term Deposit dialogs with a different id.
- **Fix B**: Leave as is and record a follow-up for the Lokaty `/10x-ui`, where `AccountBankSelect` is touched anyway.
  - Strength: keeps this change inside its planned files.
  - Tradeoff: the bank error stays unannounced to screen readers until then.
  - Confidence: HIGH. Nothing regresses.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A). `AccountBankSelect` takes optional `id` (default `account-bank`) and `ariaDescribedby`. `AccountFormDialog` passes `id`, `describedBy` and `invalid` from the FormField slot. `TermDepositFormDialog` is unchanged and keeps the default id.

### F3 — Dashboard gained one SUM query, contrary to the plan's performance note

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/DashboardController.php:57,128, app/Support/TotalFunds.php:15-16
- **Detail**: Dashboard calls `SpendableLiquidity::forUser` for its own prop and again inside `TotalFunds::forUser`, so it now makes 3 aggregate queries instead of 2. The plan says "Dashboard zostaje przy tej samej liczbie". This is immaterial for single-user data on an indexed `user_id`.
- **Fix**: Accept it, and note the extra query in the review rather than adding an API to `TotalFunds`.
- **Decision**: ACCEPTED. One extra indexed SUM on the Dashboard is immaterial for single-user data, so `TotalFunds` gets no new API.

### F4 — Archived negative balance hardcodes `text-destructive`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/Accounts.vue:77-83
- **Detail**: `.ai/rules/js.md` says that for archived rows below zero you use `amountToneClass`, and `TermDeposits.vue:64` does. `balanceToneClass` returns the literal `'text-destructive'` instead. The output is identical today, but it would drift if the tone helper changed.
- **Fix**: Return `amountToneClass(account.current_balance_minor_units)` when the archived balance is below zero.
- **Decision**: FIXED. `balanceToneClass` returns `amountToneClass(...)` for an archived balance below zero.

### F5 — `bankLabel` duplicated a third time with a raw-enum fallback

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/Accounts.vue:65-67
- **Detail**: The same lookup exists in `DashboardHoldings.vue:45` and, as an exhaustive `Record<AccountBank, string>`, in `AccountBankSelect.vue:34`. The `?? bank` fallback would show the raw enum value for a missing translation. The Record form fails the type check instead.
- **Fix**: Move `bankLabel(t, bank)` into `resources/js/lib/account-identity.ts` and use it in the three places. This could be a follow-up, since `DashboardHoldings` is outside this change.
- **Decision**: FIXED. `bankLabel(accountTranslations, bank)` lives in `resources/js/lib/account-identity.ts`, covered by `account-identity.test.ts`. `Accounts.vue`, `DashboardHoldings.vue` and `AccountBankSelect.vue` use it.

### F6 — Third copy of the hardcoded-UI guard, without AccountBankSelect

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Unit/AccountsHardcodedUiTest.php:16-48
- **Detail**: The same 13 patterns and scan function as `DashboardHardcodedUiTest.php` and `MonthPlanHardcodedUiTest.php`. The plan asked for "na wzór", so this follows the plan, but the copies will drift. `AccountBankSelect.vue` is guarded by `check-ui-literals.mjs:34` but not by this test, and the docblock does not explain that.
- **Fix**: Follow-up: extract the patterns and scanner into a shared test helper. Meanwhile, name `AccountBankSelect.vue` in the docblock's list of excluded shared components.
- **Decision**: FIXED (docblock), with a follow-up. The docblock now names `AccountBankSelect.vue` among the excluded shared components. The shared guard helper is queued in `follow-ups/review-fixes.md`.
