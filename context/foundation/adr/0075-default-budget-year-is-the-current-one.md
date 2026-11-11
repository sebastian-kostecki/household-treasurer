# The default Budget year is the current one

When the session holds no Budget year, the app opened the oldest Budget. With one year that was harmless; with two (S-29 puts a second year on the dev database) it opens the past while the User lives in the present. This ADR fixes which year opens without a choice, for Budget, Month Plan, Reports, the shell and the command palette, all of which read `SelectedBudgetYear`.

Order. The year in the session, if the User has a Budget for it (unchanged). Otherwise the current calendar year, if it has a Budget. Otherwise the newest year not later than the current one. Otherwise the oldest year. „Today” is in UTC, like `ReportController` (`CarbonImmutable::today('UTC')`).

Examples. `[2025, 2026]` in 2026 opens 2026. `[2026, 2027 in preparation]` in December 2026 opens 2026. `[2025, 2026]` in January 2027 opens 2026. `[2027, 2028]` in 2026 opens 2027.

Relation to ADR-0031. Dashboard always shows the current calendar year and keeps doing so; this ADR makes the rest of the shell start from the same year when the User has not chosen another. The shell year control and the session choice are unchanged.

We rejected:

- keeping the oldest year (it opens the past as soon as a second Budget exists);
- the newest Budget (a Budget in preparation for the next year would open instead of the year in progress);
- remembering the last year outside the session (a new store for a problem the current year solves).
