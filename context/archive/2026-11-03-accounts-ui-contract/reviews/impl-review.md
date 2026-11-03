<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Accounts UI contract pass (tokens, shared components, states)

- **Plan**: context/changes/accounts-ui-contract/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-03
- **Verdict**: APPROVED
- **Findings**: 0 critical 1 warnings 1 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Verified: `npm run check`, `npm run check:ui`, `npm test` (124 passed), `npm run types:check`, Feature tests `AccountTest` + `ArchiveAccountTest` + `AccountsKitchenSinkTest` on the Sail image with PostgreSQL (36 passed), `vendor/bin/sloppy ci` (0 findings). Not re-run: `composer ci:check` (covered by the above pieces), manual checks (ticked in Progress; before/after-phase-2/3/4 screenshots present in the diff).

## Findings

### F1 — Renaming `accounts.liquidity_total` leaks to the Dashboard

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: lang/pl/ui.php:382, resources/js/components/DashboardHoldings.vue:175
- **Detail**: The plan changed `liquidity_total` to "Płynność dysponowalna" for the Accounts page only. `DashboardHoldings.vue` uses the same key for its footer total, so the Dashboard now shows that label twice: the `StatTile` (`dashboard.spendable_liquidity`) and the holdings footer. The footer sums the accounts passed to the component (`DashboardHoldings.vue:52`), while `SpendableLiquidity` includes archived accounts, so the same label can sit above two different numbers. The plan did not list this consumer.
- **Fix**: Give the Accounts page its own key and put the old wording back for the Dashboard.
  - Strength: Leaves the Dashboard untouched, which is what the plan intended.
  - Tradeoff: One more translation key.
  - Confidence: HIGH — `grep` shows only these two consumers.
  - Blind spot: Whether the Dashboard footer should be renamed deliberately (separate decision).
- **Decision**: FIXED

### F2 — `aria-invalid` styling added to `.native-select` in app.css

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/css/app.css:205
- **Detail**: The plan lists no `app.css` change, but `aria-invalid` on the native type select needs these classes to be visible. The change is small and needed, and it affects every `.native-select`.
- **Fix**: Add one line to the plan's addendum or `design-system.md` noting that `.native-select` styles `aria-invalid`.
- **Decision**: FIXED
