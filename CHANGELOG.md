# Changelog

All notable changes to Household Treasurer (Domowy Skarbnik). The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Each version is a tag on `main` and a release on GitHub.

Domain terms (_Current Plan_, _Own Transfer_, _Capital Movement_…) come from the glossary in [`context/foundation/glossary.md`](context/foundation/glossary.md). The UI is in Polish, so screen labels are quoted in Polish where it helps to find them. Details of each 10x change live in `context/archive/<date>-<change-id>/`.

## [Unreleased]

## [1.6.0] — 2026-11-20

Milestone M-5: "Pierwszy pełny cykl na produkcji" (first full cycle on production). The first year turn on real data (2026→2027) is prepared to pass without manual fixes: one App Day in Europe/Warsaw for the server, the year menu and the dialogs (ADR-0103), and the session year, "Wymaga uwagi", Import and "Lata" now handle the turn, as the rehearsal on a copy of the production data showed they had to. The app also gets a quality net: E2E gates in CI, production errors in a self-hosted Bugsink on `errors.kostecki.dev` behind Cloudflare Access (ADR-0112), image tags with a rollback that needs no build (ADR-0107), a refreshed test plan and a glossary that describes only what the code has (ADR-0104). Claude can read the budget and record cash operations through an MCP server (ADR-0110). The milestone is closed; the January 2027 Month Plan approved without manual fixes and the list of frictions from December and January stay to be confirmed in use.

### Added

- "Wymaga uwagi" (needs attention) on the Dashboard warns through December that next year's Budget is a draft ("Budżet w szkicu") or missing ("Brak budżetu") and that "Import nie przyjmie wierszy z 2027" (Import will refuse its rows), with a link to that Budget or to "Dodaj rok". The same card shows in January while the current year's Budget is still a draft. It is the last warning card, so it never pushes the Month Plan warnings out (`year-turn-notices`, ADR-0109, amends ADR-0032, ADR-0050 and ADR-0098).
- A Budget started before its year shows "od 1.01" (from 1 January) in the "Rok budżetu" menu, and the Dashboard status line adds "budżet 2027 od 1 stycznia"; both disappear at 00:00 Polish time (`year-turn-notices`, ADR-0109).
- Import preview across the year turn: a row from a year whose Budget is a draft shows "Budżet 2027 jest szkicem…" with "Przejdź do Budżetu 2027", and a year without a Budget shows "Nie ma Budżetu 2025…" with "Utwórz Budżet 2025", which opens the new Budget dialog. A selected expense, income, refund or Capital Movement interest from such a year blocks "Zatwierdź import" with the number of rows and the year; an Own Transfer does not. After unselecting those rows the rest of the statement is saved, and the set stays atomic (`import-across-draft-year`, ADR-0106).
- MCP server for Claude (`treasurer`): six read tools (Account balances with Spendable Liquidity and Total funds, To Spend of the month, Month Plan and year plan vs Execution for Categories and Category Groups, recent operations with Category and period filters, the Category list). Every answer names the `app_day` it used and reads only the signed-in User's data. Locally it runs over stdio for the user in `MCP_LOCAL_USER` (`php artisan mcp:start treasurer`, `local` only). On production `https://budget.kostecki.dev/mcp` works as a claude.ai custom connector on the web, Desktop and the phone, with Passport OAuth, the normal login (2FA and passkey included) and a Polish consent page "Zezwolić na dostęp?". Calls are limited to 60 a minute and logged without arguments in `storage/logs/mcp.log` (`mcp-read-only-server`, ADR-0110, amends ADR-0079 for the month group sums on the server).
- Cash entry from Claude: with `MCP_WRITE_ENABLED=true` Claude records a cash expense, refund or income after the user approves the call, once per `request_id` (a retry writes nothing), and answers with the new balance and To Spend before and after. The hand-entry rules are shared with the operation dialogs; the switch is off by default (`mcp-read-only-server`, ADR-0110).
- Production error tracking: server, artisan, scheduler, queue and browser errors go to a self-hosted Bugsink over a private Docker network, with route, request id, user id and release (git SHA), and without request bodies, cookies, frame arguments or Vue props. `abort(5xx)` (except maintenance mode) and a 403 of the signed-in owner are reported; refusals after a valid preview ("Import write refused", Month Plan items, a past month) are reported with ids while the user's message stays the same. Browser errors reach Bugsink through `/error-tracking/tunnel`, guests' 2FA and passkey errors included, and a new issue sends one ntfy notification with the exception class and a link, never its message. Bugsink runs on `errors.kostecki.dev` behind Cloudflare Access, deployed from `kostecki-dev-infra`; without `SENTRY_LARAVEL_DSN` the SDKs stay inert (`production-error-tracking`, ADR-0112).
- Every deployment tags the image with its commit SHA next to `:prod` (the image from before the tags becomes `legacy-<RRRRMMDD>`) and keeps three; `scripts/rollback.sh` lists the tags and `scripts/rollback.sh <tag>` switches production back in seconds without a build, refusing before any change when the database and the target image disagree on migrations (`image-rollback-tags`, ADR-0107).
- README "Przełom roku" (year turn): the order for the turn (approve December, start next year's Budget on 30–31 December, import December from 1 January, approve January) with Polish and UTC hours and the ways out, after a rehearsal of the 2026→2027 turn on a copy of the production data. `app:rehearsal-clock` sets "now" for the running app in `local` only (`year-turn-rehearsal`).

### Changed

- "Today" is the App Day: the date in Europe/Warsaw, computed once on the server (`App\Support\AppDay`) and sent to the browser in `shell.today`. The Dashboard, the year menu, the ⌘K palette, the new Budget dialog, the default dates of the operation dialogs, the month presets and the login and welcome pages use it, so at 00:30 Polish time on 1 January everything says 1 January. An operation dated on the App Day is accepted after Polish midnight, the day a Category was archived is read in Polish time, and a future date is refused with "Data nie może być w przyszłości." Stored timestamps stay in UTC (`one-app-day`, `app-day-decision`, ADR-0103, supersedes in part ADR-0074–0077, ADR-0081, ADR-0097 and ADR-0098).
- After Polish midnight on 1 January, a session that held the then-current year opens the new year (when it has a Budget) in Budget, Month Plan, Reports and the year menu without a manual switch; a year chosen for browsing, or chosen again after the turn, stays (`session-year-follows-year-turn`, ADR-0111, supersedes in part ADR-0075).
- The "Plan niezatwierdzony" card shows its deadline in every month, e.g. "zatwierdź do 30.11, 23:59" (`year-turn-notices`, ADR-0109).
- Through January, while the current year has no closed month, "Lata" (Years) and the "Lata" tab of the Category panel open in "Pełne lata" (full years) with the current year "w toku" (in progress) outside the change; "Do <D> stycznia" stays one click away, and from 1 February the Same-day Cut is the default again. The year-over-year comparison on 1 January is unchanged and now covered by tests (`years-report-in-early-january`, ADR-0108, notes in ADR-0097 and ADR-0101).
- When the server refuses an Import row from a year without a Budget in Execution, the message names the row date and the year instead of rejecting the set without a pointer (`import-across-draft-year`, ADR-0106).
- Production serves the app through nginx and PHP-FPM (at most 4 workers, 256 MB and 60 s per web request, 100 MB uploads) instead of `artisan serve`, which handled one request at a time.
- Production containers have limits (app 1 GB / 1 CPU / 256 pids, database 512 MB / 0.5 CPU / 128 pids), and the app reaches Traefik through its own network `proxy-treasurer` instead of the network shared with the other apps.
- Logs rotate daily and also go to stderr (`LOG_STACK=daily,stderr`, `LOG_LEVEL=warning`); frame arguments are left out of exceptions (`zend.exception_ignore_args`) (`production-error-tracking`).
- Backups (`backup:run`, `backup:clean`, `backup:monitor`) also run in maintenance mode and mail their output when they fail, a "healthy backup" mail every Monday is the sign of life, a `user:backup` whose file is not written fails instead of returning a path, and `/up` checks the database and answers 500 in maintenance mode (observability audit 2026-11-19).
- Refusals after validation ("the page said valid, the write refused") are logged as invariant violations with ids, and a concurrent approval of a Month Plan is reported instead of being taken as "already approved" (observability audit 2026-11-19, `.ai/rules/errors.md`).
- The glossary describes only what the code has: Cash Expectation, Agenda, Settlement candidate, Year Close, Reserve and Earmark are removed, and ADR-0017–0022, 0029 and 0030 are marked as never built. The second ADR-0008 (the cross-Import Own Transfer pair) is now ADR-0105, and a test keeps ADR numbers unique (`docs-match-code`, ADR-0104).
- Tests: a CI job `e2e` runs five Playwright gates (`seed`, `import-other-format-without-header`, `month-plan-groups`, `irregular-collected` and the new `import-approve`) on a fresh database without retries, with the report and trace as an artifact on failure (`e2e-in-ci`); Import integrity tests on PostgreSQL with mBank and BNP fixtures and an accuracy gate for Import category suggestions on 1031 pseudonymised rows (`import:suggestion-reference`) (`testing-import-integrity`); one hand-counted table for To Spend read by Pest and Vitest, and a report invariant across refunds, Own Transfers, Capital Movements, archived Categories and month and year boundaries (`testing-amount-invariants`); year-turn tests at Polish midnight; the test plan rewritten after M-4 and the rehearsal (`test-plan-refresh`).
- Dependencies: Laravel 13.42 (with the fix for the temporary signed URL expiration bypass), Fortify 1.42, spatie/laravel-backup 10.4, PhpSpreadsheet 5.11, Larastan 3.15, Vue 3.5.45, Tailwind 4.3.5, Unovis 1.8.1, `@laravel/passkeys` 0.4.1, Vite+ 1.6.0; new `laravel/mcp` 1.2, `laravel/passport` 13, `sentry/sentry-laravel` 4.31 and `@sentry/vue` 11. Dependabot opens its PRs against `development` and groups packages that must move together.
- `.env.prod` needs new keys: `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` (`/mcp`), `MCP_WRITE_ENABLED`, `SENTRY_LARAVEL_DSN`, `ERROR_ALERT_SECRET`, `ERROR_ALERT_NTFY_URL` and `ERROR_ALERT_ISSUE_URL_PREFIX`, and `LOG_STACK=daily,stderr` with `LOG_LEVEL=warning`; each change of them needs `scripts/artisan.sh config:cache`. The external networks `proxy-treasurer` and `errors-treasurer` come from `kostecki-dev-infra` and must exist before `scripts/deploy.sh`. The deployment migrates the new Passport and `mcp_operation_requests` tables and, when the DSN is set, ends with `sentry:test`.

### Fixed

- An Own Transfer or a Capital Movement saved at the same time as another write on the same Account or Term Deposit no longer overwrites its balance; the balance is changed by one `increment` instead of "read value + delta".
- After Polish midnight on 1 January, a refused approval of December shows its error in the Month Plan instead of the month coming back as "no plan" without a message, and the refusal is reported.
- An Import request that repeats a row index is refused by validation instead of creating two operations from one row (`testing-import-integrity`).
- The production image no longer carries `storage/app/private` (snapshots, control numbers, sheet-load reports), and its storage directories are created properly.
- `demo:seed-household` no longer creates operations dated after today in the first half of January (`e2e-in-ci`).

### Security

- The app trusts only the Docker networks as proxies and only `X-Forwarded-For` and `X-Forwarded-Proto`, so a visitor can no longer set their own IP (login throttling) or the host in generated links. The router uses the shared security headers of `kostecki-dev-infra` (HSTS, nosniff, frame and referrer policy), and PHP no longer sends `X-Powered-By`.

## [1.5.1] — 2026-11-16

The year 2019 can be loaded from its own budget workbook (ADR-0102).

### Added

- `budget:load-sheet` reads `Budżet_2019.xlsx`, a workbook with the sheets `<month>_19` in three variants and `Podsumowanie` instead of "Analiza" (the year comes from `grudzień_19!C31`). The totals are checked against `Podsumowanie!I16`/`H16`; the goals ("Cele") and savings are skipped, as `Podsumowanie` does not count them. The personal lists of Sebastian and Jana and "Dom i akcesoria" become one operation per item in the Category of their row, and "Rachunki" (July–December) is split into Telefony, Internet and Prąd. `Budżet_2019.xlsx` has to be the copy with the fixed `Podsumowanie!H3:H14` and `marzec_19!B3` (SHA-256 in the README, "Wczytanie arkusza") (`sheet-load-2019`, ADR-0102, extends ADR-0085 and ADR-0096).
- Loading 2019 creates the archived income Category "Stypendium"; `budget:unload-sheet 2019` removes it again when nothing else uses it (ADR-0102).

### Changed

- `--dry-run` refuses a mapping target that is neither a mapped Category nor a new Category, so a typo fails the preview instead of the write.

### Fixed

- `budget:unload-sheet` removes a new Category ("Kosmetyki", "Stypendium") only when the unloaded Budget had a line for it, and the load refuses to reuse an archived one without lines, so a Category the user made is never deleted by an unload (ADR-0102).

## [1.5.0] — 2026-11-15

Milestone M-4: "Raporty" (Reports) compare every Category across the years 2020–2026, the year report ranks Categories from the largest and opens a panel of one Category, "Kategorie" (Categories) is split into tabs with search and filters, and the "Rok budżetu" (budget year) menu shows which year is current and which is a draft. Production moves to `budget.kostecki.dev`.

### Added

- **"Lata"** (Years), the fifth Reports period (`?view=years`): a table of Categories and groups × the years 2020–2026 in list order, with archived Categories, whole złoty, "Zmiana od 2020" (change since the first year of the Category) and a sparkline per row, "Wydatki | Przychody" tabs and four tiles ("Średnio rocznie", "Najszybciej rośnie"…). Each year is counted up to the same day ("Do 14 listopada", the Same-day Cut) or as "Pełne lata" (full years, the change up to the last full year); "—" means no Budget Line in that year, 0 a line without amounts. The year totals equal the year report to the grosz, and the read takes a fixed number of queries (`report-categories-across-years`, ADR-0097).
- Category panel in the "Rok" (Year) report: the amount of the year and the year before, the difference with a percentage, the share, the 12 months with the average and the highest month, and "Operacje" (operations of that Category in the year). The choice is kept in the address (`?category=<id>`) and survives a change of year; on a phone the panel is a bottom sheet (`report-ranking-and-category-panel`, ADR-0097, ADR-0100).
- The panel has a "Miesiące 2026 | Lata" switch (`?panel=years`): the years 2020–2026 of that Category from the same read as the "Lata" table, with the change since the first year and the highest year. A Category name in the "Lata" table opens its panel in the current year on that tab (`report-panel-years-tab`, ADR-0101).
- "Kategorie": "Wydatki | Przychody | Archiwum" tabs with counters (`?tab=`), a search over the name and the description shared by the tabs ("prad" finds "Prąd"), a Rhythm filter, a Rhythm column and the years in the Budget as a strip of cells. While a filter or a search is on, reordering is off, so a partial list cannot rewrite the order (`categories-tabs-and-filters`, ADR-0099).
- The "Rok budżetu" menu marks the current year and a draft (also on the button), splits the years into "Ostatnie" (recent) and "Starsze" (older) from four years on, and labels "Dodaj rok N" as a draft with the plan of year N−1 (`budget-year-menu-state`, ADR-0098, extends ADR-0087).

### Changed

- "Rok", "Miesiąc" and "12 miesięcy" show "Wydatki od największych" and "Przychody od największych": Categories by amount with a share bar and the difference to the same period a year earlier, and a "Kategorie | Grupy" switch (`?ranking=groups`) kept across periods and years (`report-ranking-and-category-panel`, ADR-0097).
- Report bars use the chart tokens: `chart-1` for expenses, `success` for income, `foreground` for the current year and the highest month; `chart-1` and `chart-2` are now in the Atrament design system (ADR-0097).
- The archive of "Kategorie" is a tab with Expense and Income subsections instead of a collapsible section under the list (ADR-0099).
- `shell.budgetYears` carries the phase of each year (`{year, phase}`) instead of the year alone (ADR-0098).
- Production runs on `budget.kostecki.dev`; `treasurer.kostecki.dev` redirects permanently to the same path. `.env.prod` needs the new `APP_URL` and `SESSION_DOMAIN`, and existing sessions end.

### Removed

- The bar chart of the period report; Unovis stays for "Trend" (ADR-0097, replaces the chart of ADR-0071 and ADR-0084).

## [1.4.5] — 2026-11-14

The Sheet Load mapping follows the Category list as it stands on production after it was rebuilt on 2026-11-13, so the years 2020 and 2021 load into the current Categories (ADR-0096, decision 6).

### Changed

- The Sheet Load expects the current names of the mapped Categories (the same ids). Hbo go and Netflix go to "Streaming", internet to "Internet", and the cosmetics labels, "higiena i kosmetyki" and "środki czystości" to "Chemia i kosmetyki"; a load no longer creates the archived "Kosmetyki". Years already loaded do not change.

### Fixed

- `budget:load-sheet` names the new "Kosmetyki" Category only when the plan writes to it; it used to announce it on every run.

## [1.4.4] — 2026-11-14

The years 2020 and 2021 can be loaded from the older budget spreadsheets (ADR-0096), the dark theme, the pickers and the Month Plan on a phone are fixed after the 1.4.3 audit (ADR-0092–0095), and the Category dialog says what each Rhythm does.

### Added

- `budget:load-sheet` reads `Budżet_2020.xlsx` and `Budżet_2021.xlsx`, the older layout without the `Ustawienia` sheet (the year comes from `grudzień!A1`). Payments into the targeted-expense fund and returns from it are fund movements: they make no operation, but count towards the reconciliation with "Analiza", and `--dry-run` lists them in "Ruchy funduszu". The "Lekarz - dentysta" row is split into the visits of its list, as "Leczenie prywatne" is. `Budżet_2021.xlsx` has to be the copy with the fixed `E30` formula (SHA-256 in the README, "Wczytanie arkusza") (`sheet-load-2020-2021`, ADR-0096, extends ADR-0085).
- The Category dialog shows a one-sentence description of the selected Rhythm under "Stała / Bieżąca / Roczna / Nieregularna", e.g. that "Roczna" does not carry unspent money over (`rhythm-hints`).

### Changed

- The Operations category filter uses the same picker as the operation dialogs: "Wszystkie kategorie", Income and Expense sections, the list order with groups, and a suffix for an archived Category (`v1-4-3-fixes`, ADR-0093, amends ADR-0069 for the filter).
- Term deposits are chosen with the Account avatar, as Accounts are, and a bank logo stands on a light plate (ADR-0094).
- Month Plan on a phone shows the amounts 2×2 with the label above the value; the deviation and the month summary card are coloured by benefit: an overrun is red, spending below plan and income above it are green (ADR-0092).

### Fixed

- Dark theme: native controls (the calendar icon) are dark, the 24 Category colours can be told apart in the colour picker, and the Category tile icon has at least 3:1 contrast in both themes (ADR-0095).

## [1.4.3] — 2026-11-13

The Dashboard now warns when the money "collected" for irregular Categories is not backed by Spendable Liquidity (ADR-0090), and an Account can be compared with the bank statement on a chosen day (ADR-0091).

### Added

- The Dashboard shows the Collected Coverage: under "Płynność dysponowalna" the caption adds "w tym zebrane" with the Collected of the current month's irregular Categories (the same sum as the "zebrane" of the Month Plan rows), and a "Zebrane bez pokrycia" warning card appears in "Wymaga uwagi" when the Spendable Liquidity is lower than that sum (`collected-coverage`, ADR-0090).
- Accounts have "Uzgodnij saldo…" (reconcile the balance) in the row of every non-archived Account: a dialog takes the day and the balance the bank showed, and shows the Balance on a Date of that day and the Difference while you type. A Balance Reconciliation is only a record; it blocks no operation and changes no balance, Liquidity or Execution, and saving the same day again replaces the bank balance. The User's snapshot goes from version 12 to 13 to carry the reconciliations, and versions 1 to 12 still restore (`balance-reconciliation`, ADR-0091).

## [1.4.2] — 2026-11-13

Operations loaded from the old budget spreadsheets can now be moved to another Category from the Operations list, one row at a time, without reloading the year (ADR-0089).

### Added

- Operations of a Sheet Account ("Arkusz RRRR") have "Zmień kategorię" (change Category) in the row "⋯" menu instead of "Edytuj": a dialog shows the amount, date, Account and description read-only and offers the Categories of the operation's year and type. The amount moves between the Budget Lines of the loaded year in Execution, Current Plan and Initial Plan together, so the year still sums to its sheet and the Sheet Account balance stays 0; a Category without a line in that year joins the Budget, and an archived Category or one of the other type is refused (ADR-0089, amends ADR-0085).

### Changed

- The full edit of a sheet operation (amount, date, Account, description) stays locked, and a loaded year stays closed for hand-entry and Import; deleting a single sheet operation works as before.

## [1.4.1] — 2026-11-13

Fixes after 1.4.0 from everyday use: writes on Operations and the Budget come back to the same list and the same place on the page, server messages are in Polish, the sidebar year takes one line, and the Budget shows Category descriptions (`v1-4-0-fixes`).

### Added

- The Budget row shows the Category description read-only under the name, as one truncated line (joined with "zebrane startowe" by " · " when both exist), with the full text on hover; it is still edited only in "Kategorie" (ADR-0088).

### Changed

- The sidebar Budget year is one menu button with the selected year instead of rows of segments: years newest first, the selected one checked, and "Dodaj rok N" (add year N) under a separator (ADR-0087, replaces the year segments of ADR-0055).
- The _Align_ action on a Budget row is called "Wyrównaj" instead of "Zakończ", the dialog asks "Wyrównać plan do wykonania?" and the toast reads "Plan bieżący wyrównany do wykonania."; "Zakończ" stays reserved for the year close (ADR-0086).

### Fixed

- Operations: after adding, editing, converting or deleting an operation the list comes back with the same filters, tab and page; a page that no longer exists after a delete falls back to the last page, and a filter whose Category, Account or Term Deposit no longer exists is dropped instead of bouncing the redirect.
- Budget: adding, editing and deleting a Category and "Wyrównaj" keep "Pokaż zarchiwizowane" (show archived) on.
- A save that returns to the same page keeps the scroll position (Operations, Budget, "Kategorie", Month Plan, Accounts, Term Deposits, settings, validation errors); a save that opens another page and a Budget year change still start at the top.
- Server messages that still reached the screen in English (about 40 success toasts, about 50 Import and validation errors, and the CSV Import summary with Polish plural forms) are in Polish; a test fails when a new message has no Polish translation.

## [1.4.0] — 2026-11-13

Milestone M-3: one Category list kept across years, Category rhythms with Irregular Expenses and "zebrane" (collected) in the Month Plan, and a new "Raporty" (Reports) view. The years 2022–2025 are loaded from the old budget spreadsheets, so the reports have history.

### Added

- **"Kategorie"** (Categories) view with one list of Categories across years; a data migration moved every Budget, Budget Line and Financial Operation onto it without changing any amount (`category-list-after-migration`, ADR-0063, ADR-0065).
- Create a Category and edit its name, icon, colour, order, type and description; archive and restore it. An archived Category disappears from the choice in operations and the plan but stays visible on existing operations; changing the type of a Category with operations is blocked (`category-edit-and-archive`, ADR-0067).
- Category groups (income or expense): create, name, order and delete them, assign Categories, and see group totals in "Kategorie", in the yearly Budget and in the operation Category picker (`category-groups`, ADR-0069).
- Merge two Categories after a preview of the numbers (operations, plans, collected); everything moves to the target Category, with a `user:backup` snapshot as the way back (`category-merge`, ADR-0078).
- Category rhythm (four rhythms instead of the old flags): set on the list, and the Month Plan proposal computes the amount from it, including "zostało X na Y mies." (X left for Y months) for an even yearly rhythm (`category-rhythm`, ADR-0066, ADR-0072).
- Irregular Expense with **"zebrane"** (collected): a fixed monthly rate, an opening collected amount, and a bill that uses up the collected amount and lowers "do wydania" (to spend) only by the excess; shown in the Month Plan, the Month Summary, the Dashboard, the Budget and as warnings on entry and in Import (`irregular-expense-collected`, ADR-0073).
- Opening collected amount carried over from 31 December into the new Budget, shown in the Budget row, the draft and execution (`collected-carry-over-new-year`, ADR-0076).
- Month Plan in collapsible groups with a group total; an overrun Category also marks the collapsed group row (`month-plan-groups`, ADR-0079).
- A new Budget is created from the active Categories of the list, with plan amounts suggested from the Current Plan of the previous year (`new-budget-from-category-list`, ADR-0068).
- Default "odkładam" (set aside) percentage of planned income in Settings, used by the Month Plan proposal (`default-set-aside-percent`, ADR-0082).
- **"Raporty"** (Reports) view: a month or a year by groups with drill-down to Categories, amount and share of expenses, as a chart and a table (`period-report-by-group`, ADR-0070, ADR-0071 — shadcn-vue Chart on Unovis).
- Reports: comparison with the same period a year earlier on the same list Category (`year-over-year-report`, ADR-0074), a trend over the months of the year (`report-trend-by-month`, ADR-0077), a "12 miesięcy" (last 12 months) period (`rolling-twelve-month-report`, ADR-0081) and links from a Category in a report to its operations in that period (`report-to-operations`).
- Month Plan change mode raises an overrun expense plan to the actual amount, plus a "Ustaw plan na wydatki" (set plan to expenses) button per row (`month-plan-match-actual`, ADR-0062).
- Past years 2022–2025 loaded from the `Budżet_RRRR.xlsx` sheets as Budgets in execution, each with its own archived Sheet Account, via `budget:load-sheet` (with `--dry-run`) and `budget:unload-sheet` (`legacy-budget-import`, ADR-0085).
- Commands: `app:control-numbers` (control numbers before and after a migration, with `--compare`), `import:suggestion-accuracy` (Import suggestion accuracy, with `--compare`) and `demo:seed-previous-year` (dev mock of the previous year).

### Changed

- Years are linked by the list Category instead of by name and type: budget copy, the Month Plan proposal from December to January, the Operations filter (now also by group) and Import suggestions (`import-suggestions-on-category-list`, measured not worse than before).
- `scripts/deploy.sh` takes a `user:backup` snapshot of every User, enters maintenance mode, records control numbers, runs a full `backup:run` before `migrate --force` and stops the deployment when the control numbers differ afterwards; database dumps are written with `--no-owner --no-privileges` (`safe-first-data-migration`, ADR-0064).
- The default budget year is the current one (ADR-0075).
- "Kategorie": row actions moved into the "⋯" menu, and "Zmień grupę…" (change group) opens a dialog with a radio group (`category-visual-fixes`, ADR-0083).
- "Raporty": the title row and the period switch show in every state, charts keep their space while loading, and the share sits under the amount on narrow cards (`reports-view`, ADR-0084).
- User snapshot format goes from version 5 to 12; older snapshots are converted on restore with the same rule as the migration.
- Dependencies: `@unovis/ts` and `@unovis/vue` 1.7.1 for report charts.

### Fixed

- The sidebar shows the right budget year and its segments after switching or creating a year without a page reload (`sidebar-year-refresh`).
- `user:restore` loads a snapshot onto a User who already has data: the import identity accounts of operations and transfers are mapped like other account ids (`restore-import-identity-accounts`, ADR-0080).

## [1.3.1] — 2026-11-07

The Atrament design system now also covers the screens before login (sign-in and the landing page), and a round of visual fixes gives the app its Polish name and its own mark.

### Added

- Atrament sign-in screens: a dark side panel (mark, tagline, current year block) next to a 400 px card, collapsing into a bar on phones, for Login, Register, Forgot Password, Reset Password, Verify Email, Confirm Password and the two-factor challenge; the `/dev/ui/auth?screen=&state=` kitchen sink renders every screen in every state as a full page (`auth-atrament-ui`, ADR-0056).
- Atrament landing page: an app preview built from the real Dashboard building blocks, a navy call-to-action band with the `brand` button, a single steps panel and the `/dev/ui/welcome` kitchen sink with three states (`welcome-atrament-ui`, ADR-0057).
- The app is called **Domowy Skarbnik** and has a "house with a coin" mark, also used as the favicon and apple-touch icon; the session cookie, cache prefix, Redis prefix and backup name are pinned in config, so the rename logs nobody out and does not start a new empty backup folder (`ui-fixes-2`, ADR-0058).
- A changed plan shows an ↑ or ↓ arrow coloured by category type (for expenses ↓ is green and ↑ red, for income the other way round) in the Budget and in a new "Zatwierdzony" (approved) column in the Month Plan (`ui-fixes-2`, ADR-0059).
- Cash accounts use a coins icon.
- Polish server messages for login and password reset (`lang/pl/auth.php`, `lang/pl/passwords.php`).

### Changed

- One rule for amounts: tables show amounts without "zł" and end with a "Kwoty w zł" (amounts in PLN) footer, in Operations, Import, Accounts, Term Deposits, the Dashboard holdings and the Month Plan; "zł" stays in measures, dialogs and alerts (`ui-fixes-2`, ADR-0060).
- Wide screens: the aside width and the amount columns on the Dashboard and in the Budget grow with the available width, and the execution bar takes the free space (`ui-fixes-2`, ADR-0061).
- Confirmations on sign-in screens use the `ok` Alert; the remaining English copy in Verify Email, the two-factor challenge and the passkey prompt is now Polish.
- Landing page copy for guests without jargon ("klucze dostępu" instead of "passkeys", no "w MVP").
- Text styles `display`, `heading`, `lead` and `card-title` defined in `resources/css/app.css`; the `check:ui` guard and the UI contract test now cover the sign-in and landing views.

### Fixed

- Tab order on sign-in screens follows the markup instead of hard-coded `tabindex` values; the "Nie pamiętasz hasła?" link comes before the password field.
- Reset Password without `?email=` in the link has an editable e-mail field instead of an empty read-only one.
- Two-factor challenge: focus moves after switching between code and recovery mode, and the OTP error is linked with `aria-describedby`.
- The password visibility toggle no longer sets `aria-pressed` next to a changing label.
- The passkey prompt redirects through the Wayfinder `dashboard()` route instead of a literal URL, with a regression test for the redirect after passkey login.

## [1.3.0] — 2026-11-07

The interface moves to the **Atrament** design system ([`context/foundation/design-system.md`](context/foundation/design-system.md)): every view uses shared tokens, typefaces and components.

### Added

- Atrament token layer in `resources/css/app.css` (colours, typefaces, spacing, surfaces) and its consumers across the app (`design-tokens-atrament`).
- **⌘K** command palette with views, categories and actions, keyboard shortcuts for views, and the budget year picked from segments in the sidebar (`sidebar-action`).
- Account avatar (`AccountAvatar`) with bank identification wherever only the account name was shown before (`account-avatar`).
- Bank short name in `AccountMarkCompact` and a toggle in the title row of the Accounts page (`accounts-design-system-page`).
- Dashboard following the design system page: three measures, an alert card and the "Budżet &lt;year&gt;" block (`dashboard-design-system-page`).
- Playwright E2E setup with a seed test and visual gate scenarios for the rebuilt views.

### Changed

- Views rebuilt in Atrament, each with a props contract, a kitchen sink under `/dev/...` and a visual gate: Budget, Operations, Month Plan, Dashboard, Accounts, Term Deposits, Settings (Profile, Security, Appearance), Import (step 1 and preview) and the sidebar with the "HT" mark.
- Category and operation dialogs (expense, income, transfer, capital movement) on shared field elements with a "before → after" reading (`category-dialog-atrament`, `operation-dialogs-atrament`).
- Operations list following the preview from ADR-0039 (`ui-operations-atrament`).
- "Anuluj" (Cancel) buttons use the ghost variant; "Zakończyć kategorię?" (end category?) uses `ConfirmActionDialog` (`cancel-button-ghost`).
- Rejection reasons for a row and a pair in the Import preview are in Polish and composed in the UI (`import-preview-atrament-ui`).
- Sidebar without the top bar and without collapsing.
- Dependencies: Inertia 3.11 (Laravel 3.7), Vue 3.5.44, Tailwind 4.3.4, Vite+ 1.5.0, Pest 5.3.5, Larastan 3.14, Sloppy 1.8.2; Node 24 in `.nvmrc` and `engines`.
- CI: Composer cache, runner pinned to `ubuntu-24.04`, tests also on push to `development`.

### Fixed

- Editing a bank account keeps the selected bank.
- Category columns in the Budget fit without horizontal scrolling.
- The Status column in the Month Plan is right-aligned at every width.
- ⌘K palette: filtering categories loaded after typing, searching by label only, and the new budget dialog opening in the layout instead of the sidebar sheet.
- An operation row no longer shows the account avatar.
- Typed account fields of a pair candidate in `ImportPairCandidateDetector`.

### Security

- `symfony/serializer` 8.1.11 with the fixes reported by `composer audit`.

## [1.2.0] — 2026-11-04

Import with category suggestions and a tidier Import preview.

### Added

- Category suggestion in the Import preview: a preselection based on earlier operations, a suggestion marker on the row, and accuracy and performance measurement (`import-category-suggestions`).
- A generic description for imported rows without one.
- UI contract for the Import preview: kitchen sink, state matrix and gate (`import-preview-ui-contract`).

### Fixed

- Confirming an Import when the preview contains invalid rows.

### Changed

- Dependencies: Inertia (Vue) 3.9.1, Inertia Laravel 3.5.2, Fortify 1.41.0, Pest 5.3.4, `@lucide/vue` 1.64.0, `vue-input-otp` 0.4.0.

## [1.1.0] — 2026-11-03

Milestone **M-1: Month control without a spreadsheet** — the month plan and "to spend" live in the app instead of a spreadsheet ([`context/foundation/prd.md`](context/foundation/prd.md), [`context/foundation/roadmap.md`](context/foundation/roadmap.md)).

### Added

- **Month Plan** page: plan proposal, adjustment, approval and the "do wydania" (to spend) reading; item kind (bill / everyday) on the category; snapshot version 5 (`month-plan-and-to-spend`).
- A "set aside / draw" amount in the plan and its effect on "to spend" (`set-aside-and-draw`).
- Changing an approved plan during the month (`approved-plan-changes`).
- Plan and actual amount for every item, with one-off categories in a collapsed section (`month-items-plan-vs-actual`).
- Spending pace against the days elapsed in the month, calculated with the same formula in PHP and TS (`month-spending-pace`).
- "To spend" on the Dashboard and in the month bar on the Budget page, also in a month without a plan (`to-spend-replaces-remaining`).
- Month plan notices on the Dashboard (`month-plan-notices`).
- Plan-based warnings for manual operations and in the Import preview (`plan-based-entry-warnings`).
- Month summary, including intended and actual set-aside (`month-summary`).
- Comparison of month plans with the yearly budget (`month-plans-vs-year`).
- UI contracts with a kitchen sink and a 7-state matrix for the Dashboard, Budget, Month Plan, Accounts, Term Deposits, Operations and the sidebar.
- Sloppy in the dependencies and in CI.

### Changed

- Month plan domain decisions: a new ADR supersedes ADR-0024 where it described a "second monthly budget", with new terms and notice thresholds in the glossary (`month-plan-domain-decisions`).
- "To spend" replaces the _Month Remaining_ reading; month plan notices replace the _Paced_ marker in notices.
- Move to Vite+ 1.x, Laravel update with Wayfinder and Larastan; Boost MCP configuration for agents.

### Fixed

- Account and Term Deposit rows share the `holdings-list-row` class.
- Plan changes are saved with a single insert instead of inserts in a loop.

## [1.0.0] — 2026-09-24

The first version (MVP) deployed to production: a yearly household budget and liquidity across accounts and term deposits, in PLN, with a Polish interface ([`context/foundation/prd-mvp.md`](context/foundation/prd-mvp.md)).

### Added

- **Accounts and liquidity:** bank and cash accounts with bank identification (Alior, BNP Paribas, ING, Lokaty Facto, mBank, Pekao, PKO BP, Revolut, Santander), _Spendable Liquidity_ and _Total funds_, archiving instead of deleting.
- **Yearly budget:** budget draft with income and expense categories, execution start with a frozen _Initial Plan_, editing the _Current Plan_ during the year, aligning a category plan with execution, copying categories from an earlier year, category appearance and order (drag and drop), _Paced_ and _One-off_ categories.
- **Operations:** manual income, expenses, refunds and _Own Transfers_ with a preview of limits and warnings, an optional counterparty, editing and deleting, reclassifying income as an expense refund, a list with pagination, search and filters.
- **Term deposits:** _Term Deposits_ with a bank and an end date, _Capital Movement_ entered by hand and from a statement, converting an operation or transfer into a capital movement, a term deposit filter on the operations list.
- **Statement import:** CSV and XLSX, named mBank and BNP Paribas formats with automatic column mapping, skipping junk rows, duplicate detection on re-import, candidate pairs turned into _Own Transfers_, import of capital movements and interest, storing the counterparty and raw description, skipping payment instrument numbers, atomic batch save.
- **Dashboard** for the current year: liquidity tiles, an attention zone with ordered notices by severity, _Paced_ category alerts, a year progress marker, budget sections collapsed by default, a list of accounts and term deposits.
- Execution and month result reading in the month bar on the Budget page.
- Login with 2FA and passkeys through Fortify; no self-registration, users are created by an administrator with an Artisan command.
- Admin commands: `user:backup`, `user:restore`, `user:reset-data`, account management from Artisan, `demo:seed-household`; application backups (Spatie) with a schedule and restore.
- Polish landing page, the Geist typeface with `latin-ext`, the first design system with colour tokens and amber non-blocking warnings.
- Production Docker stack (behind Traefik and Cloudflare, HTTPS), a deploy script from `main`, CI in GitHub Actions, the NFR-PERF-01 performance test on PostgreSQL, PHPStan at level 7.

[Unreleased]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.5.1...HEAD
[1.5.1]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.4.5...v1.5.0
[1.4.5]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.4.4...v1.4.5
[1.4.4]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.4.3...v1.4.4
[1.4.3]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.4.2...v1.4.3
[1.4.2]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.3.1...v1.4.0
[1.3.1]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/sebastian-kostecki/household-treasurer/releases/tag/v1.0.0
