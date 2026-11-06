<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Ustawienia według strony design systemu (ADR-0049)

- **Plan**: context/changes/settings-atrament-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-06
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 5 observations

All five phases have their code and automated gates done (Progress automated rows `[x]` with SHAs). The 16 manual rows (1.4–1.5, 2.5–2.6, 3.5–3.10, 4.4–4.6, 5.6–5.8) are still `[ ]`, waiting for human confirmation. None is ticked without evidence.

Automated criteria, last run on the code at `fd0ddfd` (only the docs epilogue followed):

- `composer ci:check` on PostgreSQL passed: 908 PHP tests, 28 JS test files, PHPStan, lint, `check:ui`. A dedicated database `settings_atrament_testing` was used. The first run on the shared `testing` DB collided with another worktree.
- `vendor/bin/sloppy ci`: nothing flagged.
- `npm run types:check`, `npm run test` (272) and `npm run build` passed.
- Red-then-green checks:
  - lang files removed → 4 new assertions went red;
  - injected literal and missing key → the guard went red;
  - `abort_unless` removed → the sink test went red.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Findings

### F1 — Settings sink can really delete the local account

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/dev/SettingsKitchenSink.vue:137 (and :97-98, :146-170)
- **Detail**: The "default" section embeds the real `Profile` page. Its `<DeleteUser />` has no `form` override, so it posts to the real `ProfileController.destroy` (logout + `$user->delete()`). Only the two lower `DeleteUser` copies (`:249`, `:262`) go to `DialogsKitchenSinkController.validate`. The sink intro (`:97-98`) says „okna usuwania na endpoint walidacji”, which is false for the first button. The embedded `Security` copies also submit the real password update, and the `canManageTwoFactor=true` copy shows the real Fortify „Enable 2FA”. Limits on the risk: the route exists only in `local`, and a delete needs the correct password plus a destructive confirmation. `AccountsKitchenSink` also embeds a real page with real forms.
- **Fix A ⭐ Recommended**: Correct the sink intro and the "default" matrix row. Say plainly that the embedded pages (delete account, password, 2FA) hit real routes, and that only the "disabled" and "error" dialogs use the validation endpoint.
  - Strength: Matches the Accounts sink precedent of embedding the real page. Copy-only change in one dev file, with no new props on an Inertia page.
  - Tradeoff: The trap stays. It is only documented.
  - Confidence: HIGH — the sink is local-only and deletion still needs the real password.
  - Blind spot: How often a developer types the real password into a sink dialog.
- **Fix B**: In the "default" section, drop the embedded `Profile` danger zone. Render the Profile panel through the page, and `DeleteUser` separately with `:form="savingForm"`. For example, give `Profile.vue` an optional sink-only prop that forwards a `form` to `DeleteUser`.
  - Strength: The sink can no longer delete the account.
  - Tradeoff: Adds a prop to an Inertia page that the server never sends, which blurs the `inertia-props.md` contract. Password and 2FA stay real unless they get the same treatment.
  - Confidence: MED — needs a decision on page-level sink-only props.
  - Blind spot: None significant.
- **Decision**: PENDING

### F2 — Password error in the delete dialog is not tied to its field

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/DeleteUser.vue (FormField `delete-user-password`), resources/js/components/ConfirmActionDialog.vue (default slot + `InputError v-for`)
- **Detail**: The plan deliberately left `error` off the `FormField`, because `ConfirmActionDialog` prints every server error under the slot. After a wrong password, the message is a loose `InputError`. The input gets no `aria-invalid` and no `aria-describedby` pointing at the message, which breaks the `FormField` contract in `design-system.md` (label, hint and error linked through `aria-describedby`). The old markup was not linked either, so this is not a regression. But `DeleteUser` is the first `ConfirmActionDialog` consumer with a field. Focus does return to the field (verified in the browser).
- **Fix**: Expose `errors` on `ConfirmActionDialog`'s default slot (`<slot :errors="errors" />`). Bind `:error="errors.password"` in `DeleteUser`, and leave keys that a slot field already shows out of the dialog's generic error list.
  - Strength: Restores the FormField contract (red border, `aria-invalid`, described-by) for every future confirm-with-input.
  - Tradeoff: Touches a shared component used by Budget, Accounts and Operations. The "skip already shown keys" rule needs a small API, for example a `fieldErrorKeys` prop.
  - Confidence: MED — straightforward, but changes a shared dialog.
  - Blind spot: Other consumers that might later put a field in the slot.
- **Decision**: PENDING

### F3 — Sink matrix points at hover/focus screenshots that were never taken

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/SettingsKitchenSink.vue:61, :65
- **Detail**: The hover and focus-visible rows say „Zrzuty z działającej aplikacji” / „Zrzuty po Tab”. `screenshots/` holds only the 20 resting-state PNGs. `AccountsKitchenSink` words these rows the same way.
- **Fix**: Add hover and Tab-focus screenshots of `/settings/profile` (1280, both themes), or reword the two rows to point at the manual check 3.9.
- **Decision**: PENDING

### F4 — Two docs still describe the pre-ADR-0049 roles

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: context/foundation/design-system.md:83 (`FormField` „pole formularza w dialogu”), .ai/rules/js.md:20 (`Heading` listed without the 2FA/passkeys limit)
- **Detail**: Neither contradicts ADR-0049 outright. Its own sections were added elsewhere in both files. Still, the reuse lists are what the next agent reads first.
- **Fix**: `FormField` → „pole formularza w dialogu i w panelu ustawień”; in js.md change `Heading` to `Heading (only 2FA and passkeys sections)`.
- **Decision**: PENDING

### F5 — DeleteUser lost `preserveScroll` on validation errors

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/DeleteUser.vue / ConfirmActionDialog.vue (`Form` without `:options`)
- **Detail**: The old `Form` had `:options="{ preserveScroll: true }"`, and `ConfirmActionDialog` takes no visit options. After a wrong password, the page behind the open dialog may jump to the top. The settings page is short, so the effect is barely visible. The other dropped options are fine:
  - `reset-on-success`: a successful delete redirects away;
  - the manual `clearErrors`/`reset`: the dialog unmounts the `Form` on close.
- **Fix**: Give `ConfirmActionDialog` `:options="{ preserveScroll: true }"` (a sensible default for every confirmation), or accept it.
- **Decision**: PENDING

### F6 — PasswordInput toggle labels are English in four settings fields

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/components/PasswordInput.vue (unchanged)
- **Detail**: „Show password” / „Hide password” `aria-label`s are read out in the Profile delete dialog and in the three password fields. The plan excludes `PasswordInput` because the login screens and `e2e/auth.setup.ts` depend on it.
- **Fix**: A follow-up change together with the 2FA/passkeys translation (C6b), updating the e2e locators.
- **Decision**: PENDING

### F7 — Third copy of the hardcoded-UI pattern list

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Unit/SettingsHardcodedUiTest.php
- **Detail**: It copies the 13 patterns of `AccountsHardcodedUiTest`, adds `bg-destructive/N` and the `Heading` import, and has a good key-existence test and scan self-test. Dashboard, Accounts and Settings now each own a copy that can drift.
- **Fix**: Later, extract a shared pattern list (e.g. `tests/Support/HardcodedUiPatterns.php`) used by all three guards.
- **Decision**: PENDING
