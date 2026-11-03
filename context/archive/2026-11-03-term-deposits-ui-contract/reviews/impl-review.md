<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Term Deposits UI contract pass

- **Plan**: context/changes/term-deposits-ui-contract/plan.md
- **Scope**: Full plan (Phase 4 of 4)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-03
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Automated verification (run during review)

- `php artisan test --compact` TermDepositTest, ArchiveTermDepositTest, TermDepositsKitchenSinkTest — 31 passed
- `node scripts/check-ui-literals.mjs` on the 3 view files and `npm run check:ui` — 0 hits
- `npm run check` — pass; `npm run types:check` — pass; `npm test` — 126 passed
- `vendor/bin/pint --test` — pass; `vendor/bin/sloppy ci` — 100/100, 0 findings
- `composer ci:check` (Sail image) — NOT run in this review (recorded as passed in Progress 4.2)
- Manual items 1.5–4.5 are ticked in Progress; screenshots exist in `screenshots/before`, `after-phase-2`, `after-phase-3` (observable evidence present). `after-phase-3` is not a full width × theme grid for interaction shots (plan did not prescribe one).

## Findings

### F1 — Hidden opening-balance field can diverge from the visible input

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/TermDepositFormDialog.vue:46-47,116-122,228-232
- **Detail**: Two sources of truth: `openingBalanceInput` (visible) and `openingBalanceMinorUnits` (hidden `opening_balance_minor_units`). The watch updates the hidden value only when the parse is non-null, so after typing `12,3,4` over `1200,50` the hidden field still holds `120050`. The only guard is Save being disabled (`:disabled="processing || openingBalanceInvalid"`), which also blocks Enter-submit. `showNegativeBalanceWarning` is also computed from the stale value while the input is invalid. The plan prescribed "update on every valid value", so this is plan-compliant, but the divergence is a latent stale-save path. `AccountFormDialog.vue` has the same block.
- **Fix**: Replace the second ref and the watch with `const parsed = computed(() => parseMoneyInputToMinorUnits(openingBalanceInput.value))`; hidden `:value="parsed ?? ''"`, projected balance from `parsed ?? 0`, warning hidden while invalid.
  - Strength: single source of truth; a programmatic submit with invalid text fails server `required` instead of saving a stale amount.
  - Tradeoff: touches the `Accounts` twin if you want parity (separate change), and reworks a block the plan specified literally.
  - Confidence: HIGH — verified in code; behaviour is otherwise identical for valid input.
  - Blind spot: whether `projectedCurrentBalanceMinorUnits` has other callers of the ref.
- **Decision**: FIXED — Fix now (TermDepositFormDialog.vue: `openingBalanceMinorUnits` is now a computed; second ref and watch removed; hidden field `:value="… ?? ''"`; negative warning hidden while invalid). `npm run check` and `types:check` pass.

### F2 — `parseMoneyInputToMinorUnits` has no overflow guard

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/format.ts:41-56
- **Detail**: Long digit strings give `zl * 100` beyond `Number.MAX_SAFE_INTEGER` (e.g. 25 nines → `1e+27`, rendered as `"1e+27"` in the hidden field). Live validation now treats this as valid, so Save stays enabled and the server `integer` rule rejects it with a generic message; values just above the safe range lose precision silently.
- **Fix**: Add `if (!Number.isSafeInteger(minorUnits)) return null;` before the return, and add edge-case tests for `parseMoneyInputToMinorUnits` in `format.test.ts`.
- **Decision**: FIXED — Fix now (format.ts: `Number.isSafeInteger` guard; format.test.ts: 3 parser tests; `npm test` 129 passed). Parser tests from F4 are covered here.

### F3 — Form dialog can be cancelled/closed while the request is in flight

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/TermDepositFormDialog.vue:248-262
- **Detail**: Cancel is not disabled while `processing`, and Esc/overlay also close. Cancel calls `clearErrors(); reset(); closeDialog()` mid-request; the request still completes server-side. `ConfirmActionDialog` blocks closing while busy (plan 2.8 relies on that), so the form dialog is inconsistent. Not required by the plan for the form dialog; `AccountFormDialog` has the same behaviour.
- **Fix**: `:disabled="processing"` on Cancel and ignore `update:open(false)` while `processing`.
- **Decision**: FIXED — Fix now (TermDepositFormDialog.vue: local `busy` ref via `@start`/`@finish`, `onOpenChange` blocks closing, `:show-close-button="!busy"`, Cancel `:disabled="processing"` — same pattern as `ConfirmActionDialog`). Lint, types, `check:ui` pass.

### F4 — Test gaps: cross-user scoping of archived props and money-parse edge cases

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/ArchiveTermDepositTest.php:147; resources/js/lib/format.test.ts
- **Detail**: Controller scoping is correct (`$request->user()->termDeposits()`), but no test asserts that another user's archived deposits are excluded from `archived_term_deposits_*`. `parseMoneyInputToMinorUnits` has no tests for the cases the live validation now relies on (`12,3,4`, `12,`, `1 234,56`, `-`, overflow).
- **Fix**: Add one cross-user assertion next to `test_archived_totals_are_exposed_regardless_of_show_archived_filter`, and parser cases in `format.test.ts` (pairs with F2).
- **Decision**: FIXED — Fix now (ArchiveTermDepositTest: `test_archived_totals_ignore_other_users_term_deposits`; parser tests came with F2). 12 tests pass, pint clean.

### F5 — Kitchen sink does not show a negative archived total / footer edge cases

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/term-deposits-fixtures.ts (`with-archived`, archived total +288000); TermDeposits.vue:114-135
- **Detail**: The plan says a −300 zł archived deposit should be red in both row and footer, but no fixture has a negative archived total, so the red footer is never rendered in the sink (only the red row). Also the footer is hidden when archived deposits exist but sum to 0 (plan-specified `!== 0`). Separately, fixture "Lokata Pekao ujemna" has `can_delete: false, can_archive: false`, a state the server cannot produce (no capital movements ⇒ `can_delete: true`).
- **Fix**: Add a `with-archived-negative` fixture (negative archived total) to the sink and fix the impossible flags.
- **Decision**: FIXED — Fix now (term-deposits-fixtures.ts: new `with-archived-negative` scenario, Pekao row `can_delete: true`; TermDepositsKitchenSink.vue matrix text updated). Lint, types, check:ui, sink test pass. New screenshot of the 7th scenario not taken (screenshot script is session-local).

### F6 — `Accounts.vue` still breaks the archived-row rule that `js.md` now states globally

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/Accounts.vue:156,178-180; .ai/rules/js.md:41
- **Detail**: `Accounts.vue` keeps `opacity-60` and tones archived balances, contradicting `design-system.md` ("bez opacity") and the new `js.md` sentence "Archived rows: no tone, except a term deposit balance < 0". Outside this change's scope, but the rule is worded generically and the next agent will see the conflict.
- **Fix**: Record a follow-up for Accounts (or scope the `js.md` sentence to term deposits until Accounts is fixed).
- **Decision**: FIXED (queued) — Fix now: recorded as follow-up in `follow-ups/review-fixes.md`; `Accounts.vue` and `js.md` not touched (out of scope).

### F7 — Duplicated toggle / redirect / money-input logic across views

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: Accounts.vue:31-45,69-85; TermDeposits.vue:34-48,78-96; Budget.vue:93-107; AccountController/TermDepositController `redirectToIndex()`
- **Detail**: The show-archived toggle (ref, two watches, `router.get`, `onFinish` resync), `redirectToIndex()` and the opening-balance input state exist as near-identical copies. `Budget.vue` lacks the `isToggling` guard. A composable (`useShowArchivedToggle`, `useMoneyInput`) would also fix F1/F2 in one place. Acceptable now (matches the sibling); candidate for a dedicated change.
- **Fix**: Leave as is; note as follow-up.
- **Decision**: FIXED (queued) — Fix now: left as is, composable extraction recorded in `follow-ups/review-fixes.md`.

### F8 — Pre-existing issues surfaced in touched files (out of scope)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: TermDepositController.php:173-174; TermDepositFormDialog.vue:108; UpdateTermDepositRequest / TermDepositPolicy::update
- **Detail**: (a) `can_delete`/`can_archive` call `hasCapitalMovements()` per row (2 extra queries per row; same in Accounts; the index now reloads on every toggle). (b) Default opening date uses `toISOString()` (UTC), wrong date shortly after local midnight; same in Accounts. (c) A direct PATCH can still edit an archived deposit (already on the plan's NOT-doing list). The new archived totals add no queries.
- **Fix**: Leave; file as follow-ups (e.g. `withExists('capitalMovements')`, local-date helper).
- **Decision**: FIXED (queued) — Fix now: left as is, recorded in `follow-ups/review-fixes.md`.

### F9 — Small plan wording deviations

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/TermDeposits.vue:63-71; TermDepositFormDialog.vue:221-226
- **Detail**: Plan says `balanceClass` disappears; a new `balanceClass` exists but only delegates to `amountToneClass` (no new colour mapping, intent held). `aria-invalid` on the opening-balance input is also set for the server error `errors.opening_balance_minor_units` (benign extra).
- **Fix**: None needed; optionally rename the wrapper.
- **Decision**: SKIPPED
