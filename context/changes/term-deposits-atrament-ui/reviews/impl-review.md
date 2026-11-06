<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Lokaty w systemie „C · Atrament i morski”

- **Plan**: context/changes/term-deposits-atrament-ui/plan.md
- **Scope**: Full plan (all automated criteria green; manual items 1.3, 3.5–3.9, 4.5–4.8, 5.4–5.7 await the user's confirmation)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-06
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Plan adherence: every "Changes Required" item matches (drift review: no MISSING, no EXTRA). Nothing from "What We're NOT Doing" was touched (other dialogs, Dashboard, `AccountMarkCompact`). Automated criteria re-run on the final tree: `composer ci:check` in Sail (905 passed, 2 skipped), `vendor/bin/sloppy ci`, `npm run check`, `types:check`, `check:ui`, Vitest 272 passed. Break-checks went red for the new assertions in phases 2, 3 and 4.

## Findings

### F1 — Table is clipped between `md` and ~1000 px with the sidebar open

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/css/app.css:755-763 (`.term-deposits-grid` from `md`)
- **Detail**: From `md` the fixed tracks `150px 150px 150px 96px` plus gaps need ~610 px before the name column gets any width. With the 16rem sidebar the card is 480 px wide at 800 px and 580 px at 900 px. Measured in the preview (`/dev/ui/term-deposits`, scenario `default`): at 800 and 900 px the row's `scrollWidth` is 626 px, the name column is 0 px wide, and the actions column ends at x=915 while the card ends at 768/868. The flush `ListSurface` clips it, so Edit/Delete/Archive cannot be reached. At 1000 px the name column is 36 px, at 1024 px 60 px. The plan specified `md` and the gate shot only 390 and 1280 px, so it missed this. Accounts has the same mechanism but needs less width.
- **Fix A ⭐ Recommended**: Move the one-line layout and the header row from `md` to `lg` (`.term-deposits-grid`, `.term-deposits-header-row`, `.term-deposits-cell-end`), and update ADR-0051, `design-system.md` and `js.md` ("poniżej `md`" → "poniżej `lg`"). Add a 900 px shot to the gate.
  - Strength: The two-line layout already works at any width (verified at 390 px), so the range between 768 px and 1024 px gets a tested layout.
  - Tradeoff: Departs from the plan's breakpoint and from Accounts (`md`); the docs change with it.
  - Confidence: HIGH — measured overflow at 800/900 px; at `lg` (1024 px) the name column is narrow (60 px) but nothing is clipped.
  - Blind spot: How wide the name column should be at exactly 1024 px; the sidebar can also be collapsed.
- **Fix B**: Keep `md` and shrink the tracks (`minmax(0,1fr) minmax(0,8rem) 7rem 8rem 5rem` or `auto` for bank/date).
  - Strength: Keeps the plan's breakpoint and Accounts' parity.
  - Tradeoff: At 768 px with the sidebar the card is ~446 px of content, so even narrow tracks leave the name column very small; the card's 150 px widths are lost.
  - Confidence: MED — needs re-measuring; may still clip at 768 px.
  - Blind spot: Long bank labels ("Lokaty Facto", "BNP Paribas") in a narrower column.
- **Decision**: PENDING

### F2 — End date has no label for screen readers or on phones

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/TermDeposits.vue:248-259
- **Detail**: Before the change the row read "Data zakończenia: 15.01.2027". Now the cell is a bare date. The header row is plain `div`s without table roles (same as Accounts), so assistive technology cannot tie the date to its column, and below `md` the header is hidden. The missing-date case does have the sr-only "Bez daty zakończenia", so the two cases are inconsistent.
- **Fix**: Prefix the date with `<span class="sr-only">{{ t.term_deposits.end_date }}: </span>`.
- **Decision**: PENDING

### F3 — Dialog screenshots for `errors` and `saving` show the empty create form

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/term-deposits-atrament-ui/screenshots/dialog-term-deposit-{errors,saving}-*.png
- **Detail**: `dialog-term-deposit-new-1280-light.png`, `-errors-` and `-saving-` share md5 `8280a5ed…`, and the other three viewport/theme sets repeat this. `?open=term-deposit-errors` only opens the form; errors appear after a submit to `DialogsKitchenSinkController::validate`. The one-off script did not submit. The spinner is covered by `save-spinner-*`, but no screenshot shows field errors.
- **Fix**: Re-shoot both states after clicking „Zapisz” (wait for the 422 for `errors`, shoot during the 1.5 s sleep for `saving`).
- **Decision**: PENDING

### F4 — Sink matrix says the switched-off Switch is in `default`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/TermDepositsKitchenSink.vue:37
- **Detail**: The `empty` row reads "default (wyłączony, widoczny przy archived_term_deposits_count > 0 w all-archived)". `default` has `archived_term_deposits_count: 0`, so it shows no Switch; the switched-off Switch is in `all-archived`.
- **Fix**: Name `all-archived` as the scenario with the switched-off Switch.
- **Decision**: PENDING

### F5 — Two captions for the same „Łączne środki”

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: lang/pl/ui.php:517 (`term_deposits.total_funds_caption` „konta i lokaty”) vs :480 (`accounts.total_funds_caption` „z lokatami”)
- **Detail**: The same `TotalFunds` measure has different captions on Lokaty and Konta. The plan chose „konta i lokaty” from the `PageTermDeposits` card, so this is planned, but the reader sees two wordings for one number.
- **Fix**: Leave as planned and note it as a follow-up (align Accounts with the card, or record the difference in ADR-0051).
- **Decision**: PENDING

### F6 — On phones the date does not line up across rows

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/css/app.css:755 (`auto minmax(0,1fr) auto` below `md`)
- **Detail**: Each row is its own grid, so the `auto` bank track takes each row's label width and the date starts at a different x per bank ("ING" vs "BNP Paribas"). ADR-0051's "even column" argument for "—" holds only from the one-line breakpoint up.
- **Fix**: Accept (the second line is a caption line) or give the bank track a fixed width below the breakpoint, e.g. `7rem`.
- **Decision**: PENDING

### F7 — Small style inconsistencies

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/TermDeposits.vue:155, :248; tests/Unit/AccountsHardcodedUiTest.php:5-6
- **Detail**: The date cell uses ad-hoc `font-mono tabular-nums` at 14 px next to the balance in the `amount` style (13 px mono). The "W tym zarchiwizowane" amount lost the `tabular-nums` it had under `StatTile` (`meta` doesn't add it). The Accounts guard docblock lists two files without "and".
- **Fix**: Use the `amount` style (or a named date style) for the date, restore `tabular-nums` on the footer amount, fix the docblock wording.
- **Decision**: PENDING

### F8 — Files also changed on `development`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: .ai/rules/js.md, context/foundation/design-system.md, lang/pl/ui.php, resources/css/app.css, scripts/check-ui-literals.mjs
- **Detail**: `development` gained the import-start change (merge `838ddc1`) after `af0aed7`, touching the same five files. A `git merge-tree` preview of `HEAD` with `development` shows no conflict markers, but both sides added lines to js.md and design-system.md.
- **Fix**: Merge `development` into the branch before review/merge and re-run `composer ci:check`.
- **Decision**: PENDING
