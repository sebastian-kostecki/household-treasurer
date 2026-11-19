---
paths:
    - 'app/**/*.php'
    - 'resources/js/**/*.{ts,vue}'
---

# The App Day

## One calendar day, Europe/Warsaw

"Today" is the calendar date in Europe/Warsaw (ADR-0103). The year, the month, the Budget year and every deadline change at 00:00 Polish time, not at 00:00 UTC. Timestamps stay what they were: `now()` in UTC for `created_at`, `archived_at` and the like.

## Server: AppDay

- "Today" in `app/` comes only from `AppDay::today()`; the day of a given instant (an archiving, a stored timestamp) from `AppDay::of($instant)`. Both return the Polish date at 00:00 in the application zone, so `->year`, `->month` and `->toDateString()` give the Polish day.
- Never `today()`, `Carbon::today()`, `CarbonImmutable::today()`, `now()->year`, `now()->month`, `now()->toDateString()` or `before_or_equal:today` for a domain decision. `tests/Unit/AppDayClockGuardTest.php` fails on them; its exception list (`AppDay`, `RehearsalClock`, its command) can only shrink.

## Browser: shell.today

- The browser takes the day only from `shell.today` ('YYYY-MM-DD'): a component in the layout reads it through `useShell()`, a page from its props. No `new Date()` without an argument, no `Date.now()`; `new Date(value)` on a passed date is fine. The same test guards `resources/js` (test files excluded).
- Logic in `resources/js/lib` takes the day (or the year derived from it) as an argument and never reads the clock; `resources/js/lib/app-day.ts` has the helpers (`appDayYear`, `appDayMonth`, `appDayMonthRange`).

## Tests

- Freeze time with `travelTo` at the Polish midnight boundaries: 22:59:59 and 23:00:00 UTC in winter (31 December), 21:59:59 and 22:00:00 UTC in summer (30 June). Never at 00:00 UTC, which proves nothing about the Polish day.
- Vitest passes the day explicitly and uses no fake timers (`resources/js/lib/app-day.test.ts`).
- `app:rehearsal-clock` is a visual check in the browser, not proof.
