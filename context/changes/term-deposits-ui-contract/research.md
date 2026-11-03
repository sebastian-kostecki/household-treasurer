---
date: 2026-11-03T16:10:47+01:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: 317b07f8e9038f132f322a59f616018725596be6
branch: development
repository: household-treasurer
topic: "Term Deposits view UI contract audit — charges, 7-state matrix, reusable infrastructure"
tags: [research, ui, 10x-ui, term-deposits, design-system, reka-ui, checkbox, confirm-dialog, stat-tile]
status: complete
last_updated: 2026-11-03
last_updated_by: Claude (Opus 5.5)
---

# Research: Term Deposits view UI contract audit

**Date**: 2026-11-03T16:10:47+01:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: 317b07f8e9038f132f322a59f616018725596be6 (uncommitted change folder on top)
**Branch**: development
**Repository**: household-treasurer

## Research Question

Verify and sharpen the five pre-audit charges in `change.md` for `resources/js/pages/TermDeposits.vue` (with `TermDepositFormDialog.vue`), settle whether "Suma lokat" counting archived deposits is intended, map the seven UI states, and identify what the plan reuses from merged UI changes (`dashboard-ui-contract`, `budget-view-ui`, `month-plan-ui-contract`, `sidebar-ui-contract` p1–p2) and how it coordinates with the open `accounts-ui-contract`.

## Summary

- **The headline defect is functional.** The "Pokaż zarchiwizowane" checkbox binds `:checked` / `@update:checked` (`TermDeposits.vue:126-130`), names reka-ui 2.11 `CheckboxRoot` does not declare (verified for Accounts in `context/changes/accounts-ui-contract/research.md:30`). The repo already tracks this: `resources/js/ui-contract.test.ts:33-37` lists `pages/TermDeposits.vue` in `DEAD_CHECKBOX_BINDING_EXCEPTIONS`, and the test fails if a listed file is fixed but not removed from the list (`:86-89`). The fixed pattern exists in `Budget.vue:93-106,150-167,299-307` (local ref + two watches + `router.get` with `onFinish` rollback). Archive/restore forms (`TermDeposits.vue:260,307`) do not send `show_archived`, so the controller's preserving redirect (`TermDepositController.php:116,130`) lands on the plain list.
- **Charge 2 is not a bug in the arithmetic.** "Suma lokat" summing archived deposits (`TermDepositController.php:23,37`) is intended: `.docs/prd.md:293` (FR-REP-10) includes archived deposits in Total funds, `tests/Feature/ArchiveTermDepositTest.php:105-123` pins the page total including an archived deposit, and commit `70b3366` introduced it deliberately. Archiving does not require a zero balance (`TermDepositPolicy.php:35-40`, `TermDepositController.php:164`). The charge is reframed as **communication**: the page shows a total that the visible rows do not add up to, and the Dashboard's "Lokaty" group total under the same label "Suma lokat" excludes archived deposits (`DashboardHoldings.vue:59-64` over non-archived props, `DashboardController.php:49-53`).
- **Confirm dialogs: no shared component exists yet.** `TermDeposits.vue:203-344` carries 3 inline Dialog+Form confirmations; repo-wide none of the inspected confirm dialogs (TermDeposits, Accounts, Budget, BudgetCategoryRowActions) shows a spinner or a server error. `destroy` returns `errors.term_deposit` (`TermDepositController.php:90-92`), which the page never renders. `accounts-ui-contract` planned a `ConfirmActionDialog` but has research only and no commits beyond `development`.
- **Amount colour and archived rows break the contract.** `totalClass` (`TermDeposits.vue:60-64`) paints 0 zł green; `balanceClass` (`:75-85`) is a local sign→colour helper; archived rows use `opacity-60` (`:160`) and keep status colours, while `.docs/design-system.md:16,34` require the whole archived row in `muted-foreground` without opacity (pattern: `BudgetCategoryRow.vue:41-47`). `opacity-60` on archived rows remains only in `Accounts.vue:158` and `TermDeposits.vue:160` among inspected views.
- **KPI tile and title:** the total is a hand-built `Card` + `CardTitle text-2xl` (`:114-123`) without `tabular-nums`; `StatTile` (label, `amountToneClass`, `text-3xl tabular-nums`, `footer` slot) is the documented KPI tile. "Lokaty" appears in breadcrumb, `h1` (`:108`) and `ListSurface` title (`:153`).
- **Header size charge is withdrawn for this view.** `.docs/design-system.md:23` says `text-xl`, but all 7 inspected domain pages use `text-lg` (Budget, MonthPlan, Accounts, Operations, TermDeposits, OperationsImport, OperationsImportPreview); this is a repo-wide doc-vs-code decision, deferred.
- **Hardcoded-value scan** on the 2 view files: 0 hex, 0 palette classes, 0 arbitrary px/rem; one `hover:bg-foreground/[0.03]` (`TermDeposits.vue:159`), the documented row-hover convention, not matched by `scripts/check-ui-literals.mjs`.

## Charges

Status `active` = to be mapped to a plan phase; `deferred` = recorded with reason.

### C1 — Accidental architecture: archived deposits unreachable from the UI (active, highest priority)

- **Evidence:**
  - `TermDeposits.vue:126-130` — `<Checkbox :checked="show_archived" @update:checked="toggleShowArchived">`; handler `:92-100` has no local state and no rollback.
  - `TermDeposits.vue:260,307` — `TermDepositController.archive.form(termDeposit)` / `restore.form(termDeposit)` without query; `TermDepositController.php:116,130` preserve `show_archived` only when the request carries it.
  - `resources/js/ui-contract.test.ts:33-37` — `pages/TermDeposits.vue` in the dead-binding exception list; `:86-89` fails when a listed file no longer matches.
  - Coverage: backend tests for the query string (`tests/Feature/TermDepositTest.php:73-103`); no JS/browser test of the toggle.
- **User impact:** a user who archives a deposit cannot see it again or press Restore without editing the URL (`?show_archived=1`); PRD FR-TD-06 (`.docs/prd.md:222`) promises the list hides archived items "dopóki użytkownik ich nie pokaże".
- **Fix direction:** copy the `Budget.vue:93-106,150-167,299-307` pattern (`v-model` on a local ref, watch prop, visit on user change, `onFinish` resync); pass `show_archived` to archive/restore forms (query on `.form(...)`); remove `pages/TermDeposits.vue` from `ui-contract.test.ts:35` in the same commit (rule `.ai/rules/js.md:26-28`). Closes the TermDeposits part of issue #186.

### C2 — Accidental architecture (communication): total ≠ visible rows (active, copy-level; arithmetic stays)

- **Evidence:**
  - Total over all deposits: `TermDepositController.php:23,37`; list filtered when `show_archived` is false: `:28-31`.
  - Intended: `.docs/prd.md:293` (FR-REP-10, Total funds includes archived deposits "w tym zarchiwizowanych"); `tests/Feature/ArchiveTermDepositTest.php:105-123` (`test_term_deposits_total_includes_archived_term_deposits`: active 1000 zł + archived 500 zł → 150000); commit `70b3366`. Same pattern on Accounts (`AccountController.php:38`, matching `SpendableLiquidity` `app/Support/SpendableLiquidity.php:10-14`).
  - No FR defines the list page's own total (term-deposit FRs `.docs/prd.md:217-223` do not mention it); "Suma lokat" (`lang/pl/ui.php:423`) is not a `CONTEXT.md` glossary term.
  - Archive with non-zero balance is allowed: `TermDepositPolicy.php:35-40` (owner + has movements + not archived), test helper archives a 600 zł deposit (`ArchiveTermDepositTest.php:151-164`).
  - Dashboard "Lokaty" group total, same label `t.term_deposits.total`, sums only non-archived deposits (`DashboardHoldings.vue:59-64,243-250`; props from `DashboardController.php:49-53`).
  - Archive confirm copy (`lang/pl/ui.php`, `archive_confirm_body`) does not say the deposit stays in the total.
- **User impact:** with one archived deposit holding money, the page total is larger than the sum of the rows shown, and differs from the Dashboard's "Suma lokat"; nothing on screen explains why.
- **Fix direction (this view only):** keep the arithmetic; explain it in the tile — e.g. `StatTile` `footer`/`label-extra` stating the archived share ("w tym zarchiwizowane: X zł") when it is non-zero. The archived share needs either a new prop (PHP + TS + `assertInertia`, per AGENTS.md Inertia props contract) or a client-side difference, which is only correct when `show_archived` is on — so a prop is the reliable option. Exact copy is a product decision (Open Question 1). The Dashboard label mismatch is out of this view (deferred).

### C3 — Missing shared component: hand-built confirm dialogs (active)

- **Evidence:** `TermDeposits.vue:203-250` (delete, `variant="destructive"` `:242`), `:251-297` (archive), `:298-344` (restore) — identical `Dialog > IconAction as-dialog-trigger > DialogContent > Form v-slot="{ processing }" > DialogHeader > DialogFooter(DialogClose secondary + submit :disabled="processing")`. No `Confirm*` / `AlertDialog` component and no `components/ui/alert-dialog` in `resources/js` (grep, `development` @ 317b07f). Same inline structure in `Accounts.vue:194-308`, `Budget.vue:196-243,341-371`, `BudgetCategoryRowActions.vue`.
- **Gaps in the 3 copies here:** no spinner while `processing` (available: `components/ui/spinner/Spinner.vue`; precedent `MonthPlanEditBar.vue:64,73` `<Spinner v-if="processing" />` inside the submit button); Cancel stays active mid-request; the delete `Form` slot reads only `processing` (`:217`), so `errors.term_deposit` (`TermDepositController.php:90-92`) is shown nowhere. Reachable on a stale page: Delete is offered only when `can_delete` (`:163`, no movements), and a movement added in another tab makes the delete fail silently.
- **User impact:** confirm actions give no progress feedback and a failed delete closes nothing and says nothing.
- **Fix direction:** one domain component (working name `ConfirmActionDialog`) in `resources/js/components/` on `ui/dialog` + `Button` + `Spinner` + `InputError`/`AlertError` for a named error key; adopt in TermDeposits. Ownership with `accounts-ui-contract` is Open Question 3. Record it in `.docs/design-system.md` "Gotowe elementy" (`:57-59`).

### C4 — Contract drift: amount colour and archived rows (active)

- **Evidence:**
  - `TermDeposits.vue:60-64` `totalClass`: `< 0` → destructive, else `text-success` (0 zł is green), contradicting `.docs/design-system.md:44` ("Zero nigdy nie jest zielone") and `.ai/rules/js.md:34-38` (`amountToneClass`).
  - `TermDeposits.vue:75-85` `balanceClass` — local helper using server `is_negative`; `amountToneClass(current_balance_minor_units)` (`resources/js/lib/amount-tone.ts:1-13`) gives the same result for non-zero and `''` for zero. The Dashboard already colours term deposit rows with `amountToneClass` (`DashboardHoldings.vue:226,247`).
  - `TermDeposits.vue:160` archived row `opacity-60`, balance still coloured (`:189`); contract: whole archived row `text-muted-foreground`, no opacity, no status colour (`.docs/design-system.md:16,34`; pattern `BudgetCategoryRow.vue:41-47`, `lib/budget-category-cells.ts:6`).
- **User impact:** zero reads as "good"; archived rows are washed out (lower contrast for everything, including the Restore button) yet still signal green/red money status.
- **Constraint:** `.docs/prd.md:224` (FR-TD-08) — a negative deposit balance "musi być wtedy jednoznacznie oznaczone na każdej powierzchni, która je pokazuje". This collides with "archived row without status colours" (`.docs/design-system.md:34`) for an archived deposit with a negative balance. The minus sign from `formatMoney` is always present; whether it alone satisfies FR-TD-08 on a muted archived row is Open Question 7.
- **Fix direction:** `amountToneClass` for total (via `StatTile`) and active rows; archived row `text-muted-foreground` with no tone on the amount (subject to Q7); drop `opacity-60`. `is_negative` prop may become unused in this view (keep the prop unless the plan removes it with PHP + test).

### C5 — Missing shared component / hierarchy: KPI tile and repeated title (active, header size deferred)

- **Evidence:** `TermDeposits.vue:114-123` total as `Card` + `CardHeader pb-2` + `CardTitle text-2xl` without `tabular-nums` — a near-copy of `StatTile.vue` (`text-3xl tabular-nums`, `amountToneClass`, `label-extra`/`footer` slots), whose only consumer is `Dashboard.vue:192,232,237`. `TermDeposits.vue:153` `ListSurface :title="t.term_deposits.title"` repeats the `h1` (`:108`, same key) and the breadcrumb (`:66-73`); `list_description` "Lokaty terminowe." (`lang/pl/ui.php:421`) adds no information.
- **User impact:** the total does not align digit-by-digit with row balances and is smaller than KPI numbers elsewhere; "Lokaty" three times flattens the hierarchy.
- **Fix direction:** `StatTile` for the total (also hosts C2's footer); drop or replace the duplicate `ListSurface` title/description. **`h1 text-lg` → deferred** (see table).

### Deferred / rejected

| Item | Reason |
| --- | --- |
| `h1 text-lg` vs `.docs/design-system.md:23` `text-xl` | Repo-wide: 7 inspected domain pages use `text-lg`; only dev sinks use `text-xl`. Deferred in month-plan research and budget/month-plan plans; open in accounts research Q1. Keep `text-lg` here, decide once for the repo. |
| `hover:bg-foreground/[0.03]` (`TermDeposits.vue:159`) | Documented row-hover convention (`.docs/design-system.md:16`); rejected as a charge, same decision as `dashboard-ui-contract`. |
| Dashboard "Suma lokat" excludes archived, page includes them | Other view (Dashboard); `.docs/specs/dashboard-hierarchy.md:40,58` does not specify. Record for a Dashboard pass. |
| Bank picker tile copies and `text-[10px]` (`AccountBankSelect.vue:66,89`), Reka Select vs `.native-select` rule | Shared with Accounts; owned by `accounts-ui-contract` C3/C4. This form inherits the fix. |
| English flash toasts / error (`TermDepositController.php:57,79,91,99,113,127`; no `lang/pl.json`) | i18n, not UI contract; separate change. Note: C3's error display will show the English text until then. |
| Dialog close X: `focus:ring-2 ring-offset-2` on `:focus` and English `sr-only` "Close" (`components/ui/dialog/DialogContent.vue:43-49`) | Primitive used by every dialog; change it in a dedicated pass, not under one view. |
| `TermDepositPolicy::update` ownership-only; UI hides Edit for archived rows (`TermDeposits.vue:197-198`) | Backend authorization, not UI; no UI path. |
| Unparseable opening balance silently keeps the last value (`TermDepositFormDialog.vue:113-119`) | Same as Accounts (`accounts-ui-contract` matrix "error"); fix belongs to a shared money-input pattern. Candidate for the plan only if cheap. |

## Detailed Findings

### 7-state matrix (current)

| State | Current handling | Gap |
| --- | --- | --- |
| default | Title row `h1` + Add (`TermDeposits.vue:107-112`), total Card (`:114-123`), checkbox (`:125-137`), `ListSurface` rows with `AccountIdentityBlock`, archived `Badge variant="secondary"` (`:171-176`), end date (`:178-184`), balance `tabular-nums` (`:187-192`), `IconAction` group (`:194-345`) | C1, C2, C4, C5 |
| hover | Row `hover:bg-foreground/[0.03]` (`:159`); Button / IconAction variants token-driven | none material |
| focus-visible | Contract ring on Button, Checkbox, Input, SelectTrigger | Dialog close X uses the shadcn `:focus` ring (deferred, primitive) |
| disabled | Submit buttons `:disabled="processing"` (`:243,290,337`; `TermDepositFormDialog.vue:244`) | Cancel active mid-request; checkbox not disabled during the visit |
| error | `InputError` under each form field (`TermDepositFormDialog.vue:164,170,184,200,219`); non-blocking negative-balance `Alert variant="warning"` (`:222-226`, per design-system "Alerty") | `errors.term_deposit` from delete not shown (C3); inputs carry no `aria-invalid`, so the built-in `aria-invalid:border-destructive` (`ui/input/Input.vue:29`) never fires |
| empty | `IndexEmptyState` when `termDeposits.length === 0` (`:139-149`): "Brak lokat" / "Dodaj pierwszą lokatę." (`lang/pl/ui.php:419-420`) | When every deposit is archived and the toggle is off, the list is `[]` (`TermDepositController.php:28-31`) while the total includes archived balances (`:37`): "add your first deposit" under a possibly non-zero total, with no working way to reveal archived ones (C1, C2) |
| loading | none | No indicator on the toggle visit or on dialog submits (C1, C3); `ui/spinner` and `ui/skeleton` available |

Mobile (from classes, not yet screenshotted): the row wraps (`flex-wrap`, `:159`); the action group is `w-full` below `sm` (`:194-196`) so icons sit on their own line under the logo. Verify on the 390 px screenshot.

### Domain: what "Suma lokat" means

- `CONTEXT.md:15-17` defines Term Deposit (UI word „lokata”), `:159-169` Liquidity / Spendable Liquidity / Total funds; Total funds = Spendable Liquidity + current balances of Term Deposits, no archived exclusion. No glossary term for "sum of Term Deposit balances" alone.
- After archiving, new or edited Capital Movements cannot target the deposit (`app/Concerns/CapitalMovementValidationRules.php:74-76`; import `CapitalMovementImportValidator.php:57`; rebuild `OperationRebuildValidationRules.php:48-53,91-96`), but deleting an existing movement reverts the balance with no archive check (`CapitalMovementController.php:83-87`, `ManageCapitalMovement.php:77-83`) — so an archived balance can still change. Not traced: conversion of a movement on an archived deposit to another type.

### Reusable infrastructure on `development` @ 317b07f

- **Checkbox pattern:** `Budget.vue:93-106,150-167,299-307`; rule `.ai/rules/js.md:26-28`; guard `resources/js/ui-contract.test.ts:65-90` (run by `npm test` → `vite.config.ts:39-41`, part of `composer ci:check` `composer.json:67-73`, CI `.github/workflows/tests.yml:91`).
- **Literal guards (three, different regexes):** `scripts/check-ui-literals.mjs` (FILES `:9-19`, Dashboard pass only; the place rules point new views to — `.ai/rules/js.md:38`, `.docs/design-system.md:98`); `ui-contract.test.ts:10-29` (Budget files); `tests/Unit/MonthPlanHardcodedUiTest.php:7-26` (MonthPlan files, stricter: any `-[…digit…]`).
- **Kitchen sink conventions:** controller routes `dev/ui/dashboard`, `dev/ui/sidebar` (`routes/web.php:23-24`, controllers `abort_unless(app()->isLocal(), 404)`, tests `DashboardKitchenSinkTest.php`, `SidebarKitchenSinkTest.php` covering guest/non-local/local) vs conditional `Route::inertia` (`:29-31`, `:38-40`, testable only for non-local 404). The controller convention is the one prior plans recommend.
- **Screenshots:** no Playwright dependency or `toHaveScreenshot`. Precedent: one-off uncommitted script via `npx -y -p playwright@<version matching cached chromium>` against the worktree preview (AGENTS.md "Preview from a worktree", port 8090, `test@example.com` / `password`), 1280×900 and 390×844, into `context/changes/<id>/screenshots/` (month-plan `plan.md:76-82`; sidebar uses `before/` + `after-phase-N/`, light + dark, and `states.md`).
- **Components:** `StatTile.vue` (props `label`, `amountMinorUnits`; slots `label-extra`, `footer`); `amountToneClass` (`lib/amount-tone.ts`, tested in `amount-tone.test.ts`); `ui/spinner`; `IndexEmptyState` (title, description, `actions` slot).

## Code References

- `resources/js/pages/TermDeposits.vue:60-64` — `totalClass` (0 → green)
- `resources/js/pages/TermDeposits.vue:75-85` — `balanceClass` (local sign→colour helper)
- `resources/js/pages/TermDeposits.vue:92-100,126-130` — dead checkbox binding and handler
- `resources/js/pages/TermDeposits.vue:114-123` — hand-built KPI card
- `resources/js/pages/TermDeposits.vue:139-149` — empty state
- `resources/js/pages/TermDeposits.vue:153` — duplicate ListSurface title
- `resources/js/pages/TermDeposits.vue:160` — archived `opacity-60`
- `resources/js/pages/TermDeposits.vue:203-344` — three inline confirm dialogs
- `resources/js/components/TermDepositFormDialog.vue:113-119,244` — silent money parse, submit without spinner
- `app/Http/Controllers/TermDepositController.php:23-37,90-92,116,130,163-165` — index filter/total, delete error, redirects, capability flags
- `resources/js/ui-contract.test.ts:33-37,86-89` — checkbox exception list (contains TermDeposits)
- `resources/js/pages/Budget.vue:93-106,150-167,299-307` — fixed checkbox pattern
- `resources/js/components/StatTile.vue` — KPI tile
- `resources/js/components/BudgetCategoryRow.vue:41-47` — archived row pattern
- `scripts/check-ui-literals.mjs:9-19` — guard file list
- `.docs/prd.md:221-224,293` — FR-TD-05..08, FR-REP-10
- `tests/Feature/ArchiveTermDepositTest.php:105-123` — total includes archived

## Architecture Insights

- The design-system contract works here at the token level (scan clean); drift comes from copy-paste between sibling list pages (Accounts ↔ TermDeposits: same checkbox bug, helpers, dialogs, card, opacity) and from Accounts' cleanup not having happened yet.
- Fixes in this change are almost all *adoption* of things that already exist (`Budget` checkbox pattern, `StatTile`, `amountToneClass`, Budget archived-row styling). The only new shared piece is the confirm dialog — the one place ownership with `accounts-ui-contract` must be settled.
- C2 shows an "intended but unexplained" total: the domain rule is correct and tested; the UI just never says so.

## Historical Context (from prior changes)

- `context/changes/accounts-ui-contract/research.md` — same audit for Accounts. Per-claim status today: C1 checkbox (supported; its claim that the guard/rule are on unmerged branches is now contradicted — both are on `development`); C2 confirm dialog (supported; still unbuilt; that branch has no commits beyond `development`); C5 `h1` drift (supported, still open); `:142` "unmerged branches" paragraph (stale: budget-view-ui and month-plan-ui-contract merged in `e2d6f05`, `e10f64c`).
- `context/archive/2026-11-03-dashboard-ui-contract/plan.md:43-45` — deferred `amountToneClass` adoption and local sign helpers in `Accounts.vue` / `TermDeposits.vue` to their own passes (supported, still open; this change picks it up for TermDeposits).
- `context/archive/2026-11-03-month-plan-ui-contract/plan.md:76-82` — screenshot procedure without a new dependency.
- `70b3366` — introduced archive support and the "total includes archived" test; `d7227d2` (#79) introduced the page.
- `change.md` pre-audit anchor correction: `can_archive` is `TermDepositController.php:164` (not `:166`); index body is `:19-40`.

## Related Research

- `context/changes/accounts-ui-contract/research.md`
- `context/archive/2026-11-03-dashboard-ui-contract/research.md`
- `context/changes/sidebar-ui-contract/research.md`
- In flight, touching the same shared lists (`check-ui-literals.mjs`, `.docs/design-system.md`): `sidebar-ui-contract` p3–p5 (unmerged), `worktree-operations-ui-contract` (change.md only).

## Open Questions

1. **C2 copy:** how should the tile explain archived money — a footer line "w tym zarchiwizowane: X zł" only when non-zero (needs a new Inertia prop), a static hint, or nothing (accept the mismatch)?
2. **Empty state with only archived deposits:** a variant ("Wszystkie lokaty są zarchiwizowane" + action "Pokaż zarchiwizowane") — needs Polish copy and a server signal (e.g. archived count) since the list is empty when the toggle is off.
3. **Who builds `ConfirmActionDialog`:** this change (Accounts adopts it later) or `accounts-ui-contract` first (this change waits)? Accounts has no plan and no code yet.
4. **Header size:** confirm keeping `text-lg` here and leaving the doc fix (`design-system.md:23`) to a repo-wide decision.
5. **Kitchen sink:** controller route `dev/ui/term-deposits` with typed fixtures (Dashboard/Sidebar convention) — confirm, and whether screenshots include dark mode (sidebar precedent) or light only (month-plan precedent).
6. **Merge order:** if `accounts-ui-contract` or `sidebar-ui-contract` p5 lands first, both touch `scripts/check-ui-literals.mjs` FILES and `.docs/design-system.md` "Gotowe elementy" — append-only edits to minimise conflicts.
7. **Archived + negative (FR-TD-08 vs design-system muted rule):** keep `text-destructive` on a negative balance even in an archived row (exception recorded in `.docs/design-system.md`), or rely on the minus sign plus muted colour?
