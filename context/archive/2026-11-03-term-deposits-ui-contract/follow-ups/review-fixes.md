# Review follow-ups: term-deposits-ui-contract

Queued from `reviews/impl-review.md` (2026-11-03).

## F6 — Accounts archived rows break the rule now stated in `js.md`

- **Where**: `resources/js/pages/Accounts.vue:156` (`opacity-60`) and `:178-180` (archived balances keep `amountToneClass`).
- **Rule**: `.docs/design-system.md` ("Zarchiwizowane wiersze … bez opacity") and `.ai/rules/js.md:41` ("Archived rows: no tone, except a term deposit balance < 0 (FR-TD-08)").
- **To do** (separate change, Accounts view): mute the archived row with `text-muted-foreground` instead of `opacity-60`, and drop the tone on archived account balances (or, if negative archived accounts should stay red, extend the documented exception to accounts and update `js.md` + `design-system.md`). Until then the `js.md` sentence reads as global while Accounts violates it.

## F7 — Shared composables (deferred)

- `useShowArchivedToggle` (Accounts, TermDeposits, Budget copies; Budget lacks the `isToggling` guard) and `useMoneyInput` (AccountFormDialog / TermDepositFormDialog opening-balance state). Also `AccountFormDialog` still has the stale hidden-field pattern (F1), no Save spinner, and cancellable-while-processing (F3).

## F8 — Pre-existing issues in touched files

- `TermDepositController`/`AccountController` payload: `hasCapitalMovements()` is queried per row for `can_delete`/`can_archive`; use `withExists('capitalMovements')`.
- Form dialogs default the opening date with `new Date().toISOString().slice(0, 10)` (UTC); use local date parts.
- `TermDepositPolicy::update` / `AccountPolicy::update` allow editing archived records via direct PATCH (UI hides it).
