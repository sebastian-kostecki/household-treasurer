# Review fixes: accounts-atrament-ui

Source: `context/changes/accounts-atrament-ui/reviews/impl-review.md` (2026-11-06).

## Queued

- **F6: shared helper for the hardcoded-UI guards.** `tests/Unit/AccountsHardcodedUiTest.php`, `DashboardHardcodedUiTest.php` and `MonthPlanHardcodedUiTest.php` each copy the same forbidden-pattern list and the same scan function. Move both into one shared test helper and keep only the file lists in each guard. This is its own small `chore`, outside this change, because it touches the Dashboard and Month Plan guards.
