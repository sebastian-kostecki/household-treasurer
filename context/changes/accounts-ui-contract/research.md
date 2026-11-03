---
date: 2026-11-03T15:33:35+01:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: 1d2a529ad3e9ff9e5b3271bf8104d0ceada2580b
branch: accounts-ui-contract
repository: household-treasurer
topic: "Accounts view UI contract audit — charges, 7-state matrix, reusable infrastructure"
tags: [research, ui, 10x-ui, accounts, design-system, reka-ui, checkbox, confirm-dialog]
status: complete
last_updated: 2026-11-03
last_updated_by: Claude (Opus 5.5)
---

# Research: Accounts view UI contract audit

**Date**: 2026-11-03T15:33:35+01:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: 1d2a529ad3e9ff9e5b3271bf8104d0ceada2580b (`development` @ a137d79 + change folder)
**Branch**: accounts-ui-contract
**Repository**: household-treasurer

## Research Question

Verify and sharpen the five pre-audit charges recorded in `change.md` for `resources/js/pages/Accounts.vue` (with `AccountFormDialog.vue` and `AccountBankSelect.vue`), map the current handling of the seven UI states, and identify what the plan can reuse from `dashboard-ui-contract` and what conflicts with parallel UI branches.

## Summary

- **The headline defect is functional, not cosmetic.** The "Pokaż zarchiwizowane" checkbox cannot fire since its introduction in `a0ac907` (2026-09-07, reka-ui ^2.9.8): it binds `:checked` / `@update:checked` (`Accounts.vue:124-128`), which reka-ui 2.11 `CheckboxRoot` does not declare (props `modelValue`/`defaultValue`, single emit `update:modelValue` — `node_modules/reka-ui/dist/Checkbox/CheckboxRoot.js`, `dist/index3.d.ts:2261,2282`). On top of that, the archive/restore confirm forms (`Accounts.vue:242,280`) do not send `show_archived`, so the controller's preserving redirect (`AccountController.php:110,124`) always lands on plain `/accounts`. Today archived accounts are reachable only by typing `?show_archived=1`. Tracked in open GitHub issue #186 (Accounts, TermDeposits, OperationsImport).
- **Confirm dialogs are the biggest missing shared component.** Accounts carries 3 inline Dialog+Form confirmations (`Accounts.vue:194-309`); the repo carries 16 Form-based confirmations across 9 files (15 in 8 files plus `DeleteUser` with a password field) and no shared confirm component or `ui/alert-dialog` primitive. None of the 3 in Accounts shows a loading indicator or the server error `errors.account` returned by `destroy` (`AccountController.php:83-87`).
- **Amount colour diverges from the inherited contract.** `liquidityClass` (`Accounts.vue:58-60`) paints a 0 zł total green, contradicting "Zero nigdy nie jest zielone" (`.docs/design-system.md`, "Kwoty") and the rule to use `amountToneClass` (`resources/js/lib/amount-tone.ts`) instead of local helpers (`.ai/rules/js.md:26-30`). `balanceClass` (`Accounts.vue:75-85`) duplicates `amountToneClass` behaviour. The dashboard change explicitly deferred both to this view's pass.
- **Bank mark tile exists in 4 markup copies** (`AccountIdentityBlock.vue:37-58`, `AccountMarkCompact.vue:40-68`, `AccountBankSelect.vue:56` and `:79`); the two in `AccountBankSelect` differ (size-5 `rounded-lg` vs `AccountMarkCompact` size-5 `rounded-md`, and a `•` in `text-[10px]` instead of the `Building2` icon for "Inny bank").
- **The agent rule on Reka Select is stale.** `.ai/skills/openship-ui-development/SKILL.md:45` calls the Reka Select primitive "unused"; it has 5 roots in domain code (`AccountBankSelect`, `AccountSelect`, `CategorySelect`, 2 inline in `CapitalMovementFormDialog`). `AccountFormDialog` mixes a native `.native-select` (`:154-158`) and a Reka `Select` (`AccountBankSelect.vue:51`) with different radius (`rounded-xl` vs `SelectTrigger` `rounded-md shadow-xs`).
- **Hardcoded-value scan** on the 4 view files: 3 candidate hits; `scripts/check-ui-literals.mjs` would flag 2 of them (`AccountBankSelect.vue:66,89` `text-[10px]`) — `hover:bg-foreground/[0.03]` (`Accounts.vue:157`) is the documented row-hover convention and was rejected as a charge by the dashboard change.
- **Reusable infrastructure exists in `development`:** kitchen sink pattern (`routes/web.php:22`, controller gated by `isLocal()`, typed fixtures), `npm run check:ui` wired into `composer ci:check` and CI, `amountToneClass`, `StatTile`, `column-caption`.

## Charges

Each charge: category, evidence (file:line), user impact, proposed fix direction. Status `active` = to be mapped to a plan phase; `deferred` = recorded with reason.

### C1 — Accidental architecture: archived accounts unreachable from the UI (active, highest priority)

- **Evidence:**
  - `Accounts.vue:124-128` — `<Checkbox :checked="show_archived" @update:checked="toggleShowArchived">`; `toggleShowArchived` (`:92-98`) never runs because reka-ui 2.11 `CheckboxRoot` emits only `update:modelValue`. With `modelValue` undefined the checkbox is uncontrolled: it shows a tick on click while no request is made, and renders unchecked on `?show_archived=1`.
  - `Accounts.vue:242,280` — `AccountController.archive.form(account)` / `restore.form(account)` without query; the redirect at `AccountController.php:110,124` keeps `show_archived` only when the request carries it, so after archive/restore the list returns to the non-archived view.
  - Coverage: backend query-string tests only (`tests/Feature/ArchiveAccountTest.php:101-108`, `tests/Feature/AccountTest.php:261`); no JS/browser test.
- **User impact:** a user who archives an account cannot see it again or press Restore without editing the URL; the checkbox lies about its state.
- **Fix direction:** `v-model` binding as in `Dashboard.vue:273-276` (or the `budget-view-ui` pattern: local ref initialised from the prop, `watch` on prop, visit on user change); pass `show_archived` through to archive/restore forms. Close the Accounts part of issue #186.
- **Same defect elsewhere (deferred, out of view):** `TermDeposits.vue:126-130`, `OperationsImport.vue:351-357` (issue #186 rates OperationsImport higher: possible `has_header` data mismatch), `Budget.vue:307-311` (fixed on the unmerged `budget-view-ui` branch).

### C2 — Missing shared component: hand-built confirm dialogs (active for Accounts; rest deferred)

- **Evidence:** `Accounts.vue:194-232` (delete, `variant="destructive"` at `:225`), `:234-270` (archive), `:272-308` (restore) — identical structure `Dialog > IconAction as-dialog-trigger > DialogContent > Form v-slot="{ processing }" > DialogHeader > DialogFooter(DialogClose secondary + submit :disabled="processing")`. Repo-wide 16 Form-based confirmations in 9 files (Accounts 3, TermDeposits 3, Budget 2, BudgetCategoryRowActions 3, BudgetCategoryAlignPlanAction 1, CapitalMovementActions 1, OwnTransferActions 1, FinancialOperationActions 1, DeleteUser 1 with password field) plus `PasskeyItem.vue:62-93` (not a Form, hardcoded English). No `Confirm*`/`AlertDialog` component and no `components/ui/alert-dialog`.
- **Gaps carried by every copy in Accounts:** no spinner while processing (repo pattern: `<Spinner v-if="processing" />` in `Login.vue:97-100`); cancel and the dialog X stay active mid-request; the delete Form slot reads only `processing` (`Accounts.vue:204`), so `errors.account` from `AccountController.php:83-87` is displayed nowhere (reachable on a stale page, since the UI hides Delete when `can_delete` is false — `AccountController.php:166`).
- **User impact:** confirm actions give no progress feedback and fail silently; every future fix to confirmation chrome has to be repeated per copy (3 here, 16 repo-wide).
- **Fix direction:** one domain component (e.g. `ConfirmActionDialog`) in `resources/js/components/` built on existing `ui/dialog` + `Button` + `Spinner` + error slot; adopt in Accounts only. Other 13 copies deferred to their views' passes (record in `.docs/design-system.md` "Gotowe elementy").

### C3 — Missing shared component: bank mark tile duplicated and divergent (active)

- **Evidence:** wrapper class `border-border/60 bg-muted flex shrink-0 items-center justify-center overflow-hidden border` in `AccountIdentityBlock.vue:37-58` (size-6 `rounded-md` / size-10 `rounded-lg`), `AccountMarkCompact.vue:40-68` (size-5 `rounded-md` / size-7 `rounded-lg`), and two literal copies in `AccountBankSelect.vue:56,79` (size-5 `rounded-lg`, placeholder `•` in `text-[10px]` at `:66,89` instead of `Building2`).
- **User impact:** the same bank looks different in the form picker than on the list and in operation rows; "Inny bank" is a dot in the picker and a building icon everywhere else.
- **Fix direction:** `AccountBankSelect` renders `AccountMarkCompact` (pass `type: 'bank'`, `bank`, `mark_url: bankMarkPath(bank)`), removing both `text-[10px]` literals. Radius at size-5 is an open choice (see Open Questions).
- **Related, not a charge:** bank mark path map duplicated in `app/Enums/Bank.php:18-32` and `resources/js/lib/account-identity.ts:45-60` — data, not UI contract; deferred.

### C4 — Accidental architecture: stale Reka Select rule and two dropdown chromes in one form (active)

- **Evidence:** `.ai/skills/openship-ui-development/SKILL.md:45` ("do not swap in the unused Reka Select primitive for list forms") vs 5 Reka `Select` roots in domain code (`AccountBankSelect.vue:51` since `42ca46c` 2026-09-08, `AccountSelect.vue:48`, `CategorySelect.vue:48`, `CapitalMovementFormDialog.vue:277,318`); 19 native `.native-select` usages in 7 files. No ADR or spec records an exception. In one dialog: `AccountFormDialog.vue:154-158` native select (`rounded-xl`, `app.css:203`) next to `AccountBankSelect` whose `SelectTrigger` is `rounded-md shadow-xs` and `SelectContent` `rounded-md` (`components/ui/select/SelectTrigger.vue:24`).
- **User impact:** two adjacent pickers open differently (OS list vs popover), have different corners and keyboard behaviour; the next agent following the rule may "fix" the logo picker back to native and lose the logos.
- **Fix direction:** rewrite the rule to the de-facto pattern (Reka `Select` for pickers that render an icon/logo; `.native-select` for plain enums) and record it in `.docs/design-system.md`. Whether to align `SelectTrigger`/`SelectContent` radius with the documented control radius (`rounded-xl`, design-system "Powierzchnie i rytm") is a primitive change affecting 7 forms — plan decision (Open Questions).

### C5 — Missing tokens / contract drift in the header and KPI (active)

- **Evidence:**
  - `Accounts.vue:58-60` `liquidityClass` (`< 0` destructive, else success → 0 zł green) and `:75-85` `balanceClass` — local sign→colour helpers forbidden by `.ai/rules/js.md:26-30`; deferred here by `context/archive/2026-11-03-dashboard-ui-contract/plan.md:44`.
  - `Accounts.vue:112-121` liquidity total as plain `Card`+`CardTitle text-2xl` without `tabular-nums`, while `StatTile` is the documented KPI tile (`.docs/design-system.md` "Gotowe elementy").
  - `Accounts.vue:151` `ListSurface :title="t.accounts.title"` repeats the `h1` at `:106` (same key) — "Konta" appears in breadcrumb, h1 and card title. Same in `TermDeposits.vue:151-153`.
  - `Accounts.vue:106` `h1 text-lg font-semibold` vs `.docs/design-system.md:21` "nagłówek strony `text-xl font-semibold`". These 7 inspected domain pages use `text-lg` (Accounts, TermDeposits, Operations, OperationsImport, OperationsImportPreview, Budget, MonthPlan); only `pages/dev/DashboardKitchenSink.vue:52` uses `text-xl`. This is a doc-vs-code drift, not an Accounts-only defect.
- **User impact:** an empty or exactly-zero portfolio reads as "good" (green); the title is repeated three times, flattening hierarchy; the total does not align digit-by-digit with row balances.
- **Fix direction:** `amountToneClass` for both, `StatTile` for the total, drop the duplicate ListSurface title (keep description). Header size: reconcile doc and code (Open Questions) — not by editing Accounts alone.

### Deferred / rejected

| Item | Reason |
| --- | --- |
| `hover:bg-foreground/[0.03]` (`Accounts.vue:157`) | Rejected as a charge: documented row-hover convention (`.docs/design-system.md` line 16), same decision as `dashboard-ui-contract` (`research.md:30,50,97`, `plan.md:45`). Not matched by `check:ui`. |
| Checkbox in TermDeposits / OperationsImport | Other views; issue #186. |
| 13 confirm dialogs outside Accounts | Other views; adopt `ConfirmActionDialog` in their passes. |
| `h1` size on other pages | Repo-wide decision; this change records the decision, does not restyle other pages. |
| English validation messages and flash toasts (`lang/pl` has only `pagination.php`, `ui.php`; `AccountController.php:54,73,93,107,121` use English `__()` keys) | i18n, not UI contract; separate change. |
| Bank mark path map duplicated PHP/TS | Data duplication, not visual. |
| 403 on archive/restore policy failure shows Inertia default error page (`AccountController.php:101,115`, `AccountPolicy.php:35-45`) | Only reachable by stale page / forged request; no UI path. |

## Detailed Findings

### 7-state matrix (current)

| State | Current handling | Gap |
| --- | --- | --- |
| default | Header `h1` + Add button (`Accounts.vue:105-110`), liquidity Card (`:112-121`), checkbox (`:123-135`), `ListSurface` rows with `AccountIdentityBlock`, archived `Badge variant="secondary"`, balance `tabular-nums`, `IconAction` group (`:154-311`); archived row `opacity-60` (`:158`) | C1, C3, C5 |
| hover | Row `hover:bg-foreground/[0.03]`; Button variants token-driven (`ui/button/index.ts:12-21`); destructive IconAction `hover:bg-destructive/10` (`IconAction.vue:30-35`) | none material |
| focus-visible | 3px `ring/50` on Button, Checkbox (`Checkbox.vue:23`), Input, `.native-select` (`app.css:204`), SelectTrigger | Dialog close X uses `focus:ring-2 ring-offset-2` on `:focus`, not the contract ring (`components/ui/dialog/DialogContent.vue:46`) — affects every dialog; destructive Button ring is `ring-destructive/20` (shadcn default) |
| disabled | Submit buttons `:disabled="processing"` (`AccountFormDialog.vue:225`, `Accounts.vue:226,264,302`) | Cancel / X active mid-request; checkbox not disabled during visit |
| error | `InputError` under each form field (`AccountFormDialog.vue:149,167,173,187,206`) | No `aria-invalid` on inputs, so built-in `aria-invalid:border-destructive` styles never fire; unparseable opening balance silently keeps last value (`AccountFormDialog.vue:100-106`); delete `errors.account` not shown (C2) |
| empty | `IndexEmptyState` when `accounts.length === 0` (`Accounts.vue:137-147`), copy `lang/pl/ui.php:366-367` "Brak kont bankowych ani gotówkowych" / "Dodaj pierwsze konto…" | When every account is archived and the toggle is off, controller returns `accounts: []` (`AccountController.php:29-34`) while the total still sums all accounts (`:38`) — the page says "add your first account" above a non-zero total, with no working way to reveal archived ones |
| loading | none | No indicator on toggle visit (`Accounts.vue:96`) or on dialog submit; available: `ui/spinner` (used in `Login.vue:97-100`, `OperationsFilters.vue:410-413`), `ui/skeleton` |

Mobile (~375 px, from classes): row wraps; the action group is `w-full` below `sm` (`Accounts.vue:186`) and left-aligned, so icons sit under the logo; long balances wrap to their own line. Not a charge on its own — verify on the screenshot.

### Domain check: what the total means

`CONTEXT.md:163-165` defines Spendable Liquidity as the sum of Account current balances; no archived exclusion is stated, so summing archived accounts (`AccountController.php:38`) matches the glossary. The label "Łączna płynność" (`lang/pl/ui.php:372`) is "Liquidity (unqualified)", which `CONTEXT.md:160-161` lists under _Avoid_; the Dashboard uses "Płynność dysponowalna" (`ui.php:112`). Copy question, not a token one.

### Reusable infrastructure in `development`

- Kitchen sink: `routes/web.php:22` (`dev.ui.dashboard`, inside `auth`,`verified`), `app/Http/Controllers/Dev/DashboardKitchenSinkController.php:13` (`abort_unless(app()->isLocal(), 404)`), `tests/Feature/DashboardKitchenSinkTest.php:14-38`, page `resources/js/pages/dev/DashboardKitchenSink.vue` (matrix table `:11-40`, renders real page per fixture `:98-110`), typed fixtures `dashboard-fixtures.ts:282-294`.
- Guard: `scripts/check-ui-literals.mjs` (file list `:9-19`, pattern `:21-29`, exit 1 on hit); `package.json` `check:ui`; `composer.json` `ci:check` runs it; CI `.github/workflows/tests.yml:88-91` on push to `main` and PRs. No pre-commit hook in the repo.
- Screenshots were **not** taken in `dashboard-ui-contract` (owner decision at closure; `reviews/impl-review.md` F4). Precedent for taking them: `sidebar-ui-contract` used `playwright-core` from the npx cache with headless Chromium; preview recipe in `AGENTS.md:60-68` (separate DB, `migrate --seed`, build, port 8090, `test@example.com` / `password`).

## Code References

- `resources/js/pages/Accounts.vue:58-60` — `liquidityClass` (0 → green)
- `resources/js/pages/Accounts.vue:75-85` — `balanceClass` (duplicates `amountToneClass`)
- `resources/js/pages/Accounts.vue:92-98,124-128` — dead checkbox binding and its handler
- `resources/js/pages/Accounts.vue:137-147` — empty state
- `resources/js/pages/Accounts.vue:194-308` — three inline confirm dialogs
- `resources/js/components/AccountFormDialog.vue:100-106,154-158` — silent money parse, native select
- `resources/js/components/AccountBankSelect.vue:51,56,66,79,89` — Reka Select, tile copies, `text-[10px]`
- `resources/js/components/AccountMarkCompact.vue:40-68` — reusable compact tile
- `resources/js/lib/amount-tone.ts` — `amountToneClass`
- `app/Http/Controllers/AccountController.php:24-38,83-87,110,124,166-168` — index filter/total, delete error, redirects, capability flags
- `resources/js/components/ui/dialog/DialogContent.vue:46` — close-button focus style
- `scripts/check-ui-literals.mjs:9-29` — guard list and pattern

## Architecture Insights

- The repo has a working design-system contract; Accounts is mostly on it (0 hex, 0 palette classes). Drift here comes from copy-paste between sibling list pages (Accounts ↔ TermDeposits are near-identical: rows, helpers, dialogs, checkbox) and from rules that were never reconciled with later code (Reka Select, `text-xl` header).
- Fixing Accounts well means producing reusable pieces (confirm dialog, checkbox binding pattern, tile reuse) that TermDeposits can adopt in its own pass — but not restyling TermDeposits in this change.

## Historical Context (from prior changes)

- `context/archive/2026-11-03-dashboard-ui-contract/plan.md:43-45` — defers `amountToneClass` / `column-caption` adoption and local sign helpers in `Accounts.vue` / `TermDeposits.vue` to their own passes (supported, still open). `plan.md:192` claims `/accounts` uses `.list-surface-table` — contradicted: only `BudgetCategoryList.vue:248,478` uses it.
- `a0ac907` (2026-09-07) introduced the checkbox with the dead binding; `42ca46c` (2026-09-08, #28) introduced `AccountBankSelect` with Reka Select ("leading-icon bank select"); `700d7b2` moved marks to `rounded-lg`; `dcd99ef` (2026-09-24) wrote the `text-xl` header rule after all domain pages already used `text-lg`.
- Unmerged branches: `worktree-budget-view-ui` adds `resources/js/ui-contract.test.ts` whose `DEAD_CHECKBOX_BINDING_EXCEPTIONS` lists `pages/Accounts.vue` and fails if a listed file no longer contains the dead binding (`:31-37,86-89`), and a `.ai/rules/js.md` rule "Bind the reka-ui Checkbox with v-model". `month-plan-ui-contract` adds a `.column-header` duplicate of `column-caption`, a Pest literal guard, and a design-system note that `Heading` is for settings sections only and domain pages build an `h1` title row per `Budget.vue`. `sidebar-ui-contract` has research only. All three rewrite `.docs/design-system.md` line 16; budget/month-plan branched before the dashboard change.

## Related Research

- `context/archive/2026-11-03-dashboard-ui-contract/research.md`
- `sidebar-ui-contract` branch: `context/changes/sidebar-ui-contract/research.md` (measures on `/accounts`, no Accounts charges)

## Open Questions

1. **Header size:** update `.docs/design-system.md:21` to the code's `text-lg`, or move domain pages to `text-xl`? (Repo-wide; this change should only record the decision and apply it to Accounts.)
2. **Tile radius at size-5:** `rounded-md` (`AccountMarkCompact`) or `rounded-lg` (`AccountBankSelect`, `700d7b2` intent)?
3. **Select chrome:** align `components/ui/select` trigger/content radius to `rounded-xl` (affects 7 forms), or accept the difference and only fix the rule?
4. **Empty state with only archived accounts:** new copy variant (e.g. "Wszystkie konta są zarchiwizowane" + "Pokaż zarchiwizowane" action) — needs Polish copy decision.
5. **Total label:** rename "Łączna płynność" to "Płynność dysponowalna" per `CONTEXT.md`?
6. **Merge order and guard:** extend `scripts/check-ui-literals.mjs` (in `development`); if `budget-view-ui` merges first, remove `pages/Accounts.vue` from its checkbox exception list in the same change.
7. **Screenshots:** use the `sidebar-ui-contract` playwright-core-from-npx approach (no new dependency) for desktop + one mobile width, light + dark?
