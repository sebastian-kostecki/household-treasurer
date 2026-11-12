---
date: 2026-11-12T14:29:31+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 736887d4183177c804ff29e7924cddfe541c7813
branch: worktree-legacy-budget-import
repository: household-treasurer
topic: "legacy-budget-import: loading past years from the old xlsx budget into production without changing any current value"
tags: [research, codebase, budget, financial-operations, accounts, reports, control-numbers, xlsx, legacy-budget-import]
status: partial
last_updated: 2026-11-12
last_updated_by: Claude (claude-opus-5-5)
---

# Research: loading past years from the old xlsx budget without changing current values

**Date**: 2026-11-12T14:29:31+01:00
**Git Commit**: 736887d4183177c804ff29e7924cddfe541c7813 (only `change.md` on top of `development` 042ee43; no code of this change exists)
**Branch**: worktree-legacy-budget-import
**Repository**: household-treasurer

## Research Question

From `change.md`: a one-off load of historical Budgets from the user's xlsx sheets (`storage/app/private/Budżet_2025.xlsx` and files of earlier years) into the production database, so that no current value changes: the 2026 (execution) and 2027 (draft) Budgets, Account balances, Term Deposits, Month Plans. Questions: (1) what exactly is in the sheet and how does it map to Budget / Budget Line / Category / Financial Operation; (2) which write path can create a past execution Budget with operations and what invariants it must keep; (3) which readings change once a past year exists; (4) which earlier decisions constrain this; (5) how to prove "nothing old changed".

## Status: partial

The user has files of earlier years, but on 2026-11-12 14:29 only `Budżet_2025.xlsx` and `budget_szablon.xlsx` (the template) are in `storage/app/private/` of the main checkout. The sheet layout is verified for 2025 and the template only. Earlier years are an explicit gap (see Open Questions).

## Production state (read-only psql on `treasurer-pgsql`, 2026-11-12)

- 1 user; `budgets`: 2026 `execution`, 2027 `draft`. Budget Lines: 39 in 2026, 38 in 2027.
- `financial_operations`: 1277 rows, `operation_date` 2026-01-01 … 2026-11-09.
- `accounts`: 9. 7 have `opening_date` 2025-12-31 (ids 1, 4-9), 2 have 2026-09-09 (ids 10, 11); none archived.
- `term_deposits`: 5, opening dates 2026-02-28 … 2026-09-09.
- `category_rhythms`: 33 rows — `running` 26, `fixed_charge` 5, `even_annual` 2, every one starting 2026-01 or later; **no `irregular` row exists**.
- `categories` (the list): 41 — 33 expense (1 archived: Wakacje), 8 income; none in a Category Group.

## Summary

1. **The sheet is monthly aggregates, not transactions.** 12 month sheets with an identical row set: 23 expense items in 7 groups (A/B labels, column D = actual), 7 income sources (G/I), and a list of irregular expenses (J kategoria, K nazwa, M wydatek; 159 items, 23 638,15 zł in 2025). No row in any month sheet carries a date; "Codzienne wydatki" is one monthly total (receipts survive only as terms of a literal `=a+b+…` formula). Every imported Financial Operation will therefore carry a date chosen by the loader. 2025 totals: income 134 670,58 zł, expense 134 852,23 zł (equal to "Analiza" B16/C16 and to `prd-v3.md:39`).
2. **No existing write path can do this unchanged.** The hand-entry and Import paths refuse a date before the Account's opening date (`app/Support/OperationEntryValidation.php:36`, `app/Support/CsvImport/FinancialOperationImportValidator.php:40-47`), refuse archived Accounts (`app/Actions/ManageFinancialOperation.php:138-143`), and move `accounts.current_balance_minor_units` (`ManageFinancialOperation.php:268-287`). The precedent that does it is `PreviousYearMockSeeder` (local/testing only): Budget created directly in execution, lines copied by `category_id`, operations bulk-inserted, `execution_minor_units` set to the sum of operations, Account balances untouched (`database/seeders/PreviousYearMockSeeder.php:46-50, 117-134, 232-250`).
3. **Every Account option changes something.** Seven of nine real Accounts open on 2025-12-31, after every 2025 operation. A technical Account "ending at 0" needs `opening_balance = −(net of the year)`, and `BudgetDeficitWarning::liquidityAtYearStart` sums opening balances of every Account with `opening_date <= 1 January`, archived included (`app/Support/BudgetDeficitWarning.php:50-57`). That changes the 2027 draft's deficit warning (shown for drafts, `app/Http/Controllers/BudgetController.php:104-106`). The options are compared under "Account options" below; the choice is a plan/product decision.
4. **Stored 2026/2027 values can stay identical; some readings change by design.** Changing readings: the last-12-months report (coverage of Nov–Dec 2025, ADR-0081), the comparison column on every 2026 report (ADR-0074), `budget_years` / Category type and delete locks on the Category list, year options in filters and the sidebar. Not changing: Collected and "zebrane startowe" (production has no Irregular rhythm), Month Plan proposals for 2026/2027, the dashboard, the default year.
5. **This reverses a recorded non-goal.** PRD v3 parks the sheet import: "dane 2025 wchodzą tylko jako mock na devie" (`context/foundation/prd-v3.md:287`, also `:199`, `:256`; roadmap parked item `context/foundation/roadmap.md:379`). The change needs a new ADR (next free number 0083) and an update of those entries before code (AGENTS.md "Hard rules").
6. **ControlNumbers cannot prove "only additions" as written.** `differences()` reports every added path as `(missing) -> x` and every row-count change as `X -> Y`, and the command exits 1 on any difference (`app/Support/ControlNumbers.php:84-107`, `app/Console/Commands/ControlNumbersCommand.php:45-59`). Verification needs a classifier over the two documents (see "Verification").

## Detailed Findings

### 1. The 2025 sheet (extraction spec)

Read with PhpSpreadsheet 5 (`phpoffice/phpspreadsheet ^5.9`, `composer.json:18`) on the whole workbook, cached values. Loading a single sheet breaks the `Ustawienia!` references and shows `#REF!`/`#VALUE!`; with the whole workbook loaded, no month-sheet cell is an error.

**Month sheet layout (identical in all 12 months of 2025):**

| Area | Cells | Meaning |
| --- | --- | --- |
| Month | A1 = `DATE(Ustawienia!B2, m, 1)` | month date |
| Expense items | A group, B item, rows 3-30; C "plan", D actual | C is `=D`, `=IF(D=0, Ustawienia!Bx, D)` or the same number retyped; the only C≠D cell in 2025 is styczeń row 23 (610 vs 0). The sheet has no independent month plan. |
| Income | G source, H plan, I actual, rows 3-9 | H = I in rows 3-9 in all 12 months |
| Irregular list | J kategoria, K nazwa, L plan, M wydatek, rows 4-20 (styczeń 4-21) | L = M; D30 = sum of the list (styczeń `SUM(M4:M21)`) |
| Medical list | header "data \| nazwa \| plan \| wydatek" at row 23 (styczeń 24) | feeds C18 Leczenie prywatne; the date column is empty in all 6 entries |
| Totals | C2 Rozchody, H2/I2 Przychody, O/P group sums, rows 31-35 | D31 = `I2 − SUM(D3:D30)` = 0 in every month |
| Noise | rows 32-35 D (account balances), 37-38 (date helpers), 40-43 (set-aside checks), I10-I24 (plan split of savings and fund envelopes), strays in styczeń J36/K39/J44, grudzień J37, listopad H52 | not operations |

**Expense items** (group → item): Opłaty stałe → Rata kredytu, Czynsz, Telefony, Abonamenty, Internet, Prąd\*\*, Przedszkole, Ubezpieczenie, Codzienne wydatki; Uroda → Kosmetyki\*, Kosmetyczka, Fryzjer; Zdrowie → Apteka, Badania, Dentysta - ortodoncja, Leczenie prywatne; Auto i transport → Bilety - parking - inne, Ubezpieczenie auta\*\*, Fundusz remontowy\*\*, Paliwo; Oszcz. → Oszczędności długoterminowe\*\*\*; Rozrywka → Rozrywka i wyjścia, Wyjazdy i wycieczki\*; Wydatki nieregularne → Amortyzacja\*, Fundusz Wydatków Nieregularnych\*, Wydatki nieregularne.

**Income sources:** work: Sebastian, work: Jana, 500+, darowizny, odsetki, pozostałe, oszczędności.

**2025 year totals (actual, zł):** Rata kredytu 21 388,25; Czynsz 9 681,54; Telefony 1 561,64; Abonamenty 601,39; Internet 731,64; Prąd 1 689,33; Przedszkole 23 177,66; Ubezpieczenie 1 464,04; Codzienne wydatki 35 282,20; Uroda 0; Apteka 5 068,13; Badania 0; Dentysta 0; Leczenie prywatne 1 500,00; Bilety 554,26; Ubezpieczenie auta 540,00; Fundusz remontowy 689,00; Paliwo 2 896,39; Rozrywka i wyjścia 1 825,16; Wyjazdy i wycieczki 2 192,87; Amortyzacja 0; FWN 370,58; Wydatki nieregularne 23 638,15 → **expense 134 852,23**. Income: Sebastian 69 828,89; Jana 33 064,74; 500+ 20 700,00; darowizny 4 700,00; odsetki 40,83; pozostałe 6 336,12 (luty includes a 5 790,12 tax refund) → **income 134 670,58**. Net −181,65.

**Irregular list by sheet category (2025, 159 items):** Ubrania 45 / 6 546,68; Dom 32 / 6 442,58; blank 20 / 2 427,97 (czerwiec rows 14-16, all of lipiec, grudzień rows 12-18); Osobiste 5 / 2 285,37; Usługi 32 / 2 034,93; Prezenty 13 / 1 500,18; "-" 1 / 1 250,00 (lipiec, "działka"); Edukacja 3 / 597,50; Darowizny 3 / 320,00; Książki 3 / 198,94; Dziecko 1 / 24,00; Praca 1 / 10,00. The "-------" terminator appears only in wrzesień and październik K18; the list is bounded by the "Opieka zdrowotna" label in J.

**Transfers mixed into the sheet (double-count risk):**

- D23 "Oszczędności długoterminowe" (11 190,11 in 2025) is a deposit to the mBank savings account and I9 income "oszczędności" (11 371,76) is money taken back from savings. Both are inside C2/I2 and outside "Analiza". Importing both inflates each side by about 11 thousand; the 134 852,23 / 134 670,58 totals above exclude them.
- I11-I14 (Oszczędności, Nadpłata kredytu, IKE, Cel) are a plan split of C23, not operations.
- Rows marked \*\* (Prąd, Ubezpieczenie auta, Fundusz remontowy) are monthly set-asides to "PNB sejf"; the real bills were paid from the sejf and appear only as yearly totals in `Oszczędności!T` (650 service, 515 car insurance, 1 576,63 electricity). D28 FWN (listopad 370,58) is a deposit to the fund account.
- Odsetki sierpień 40,83 appears both as income and as D23 in the same month.

**Money values:** cached values are clean except październik C2 12744.189, I11 1114.419, C23/C42/P10 1725.729, C31 0.000999. Values must be rounded to grosze before `MinorUnits` (AGENTS.md: money is `int` minor units, no float).

**Template vs 2025:** `budget_szablon.xlsx` has the same sheets (without Janeczka), the same labels in the same rows, including the styczeń one-row offset of the medical list. Its formulas drift slightly (I11, C23). Signals that older years may differ: the styczeń offset, hand-edited C formulas, per-month formula variants. A parser should locate blocks by header text and B/G labels, not fixed rows, and assert per month: D31 = 0, sum of the list = D30.

**Plan values:** the only plan amounts independent of actuals are the Ustawienia defaults (B3-B22; B9, B11, B12, B15, B18, B22 are not referenced by month sheets). A Budget Line's Initial/Current Plan for a past year must be chosen: 12 × Ustawienia default, the year's actual sum, or 0.

### 2. Schema and invariants

- `budgets`: `unique(user_id, year)` (`database/migrations/2026_09_07_180000_create_budgets_table.php:21`), so one Budget per year; `phase` string.
- `budget_lines`: `unique(budget_id, category_id)`, `category_id` NOT NULL FK to the list (`2026_11_09_122525_backfill_category_list.php:34-39`); `planned_amount_minor_units` NOT NULL, `initial_plan_minor_units` and `execution_minor_units` default 0 (`2026_09_07_180001_create_categories_table.php:23-25`).
- `categories`: `unique(user_id, type, name_key)` including archived rows (`2026_11_09_122524_expand_categories_into_category_list_and_budget_lines.php:48-62`).
- `financial_operations`: `account_id`, `budget_line_id`, `budget_id` NOT NULL with restrict FKs; `raw_statement_description` and every `import_identity_*` column nullable; no unique constraint (`2026_09_07_180003_create_financial_operations_table.php:14-37`).
- `accounts`: `opening_balance_minor_units` and `current_balance_minor_units` NOT NULL; `CreateAccount` validates the opening balance only as `required|integer`, so negative is allowed (`app/Actions/CreateAccount.php:34-45`).
- No CHECK constraints in `database/migrations` (grep for `check`).
- Stored aggregates kept by the write path, never recomputed by a reader: `accounts.current_balance_minor_units` and `budget_lines.execution_minor_units` (`ManageFinancialOperation.php:255-289`); `StartBudgetExecution` sets Initial Plan = Current Plan and Execution = 0 on every line (`app/Actions/StartBudgetExecution.php:94-114`), so starting execution after loading operations would zero the execution. `.ai/rules/reports.md` requires a year's Category total in Reports to equal the stored annual execution, so each loaded line's `execution_minor_units` must equal the sum of its operations.
- Execution does not require Month Plans: `PreviousYearMockSeeder` creates an execution Budget with none (`PreviousYearMockSeeder.php:46-50`), and `MonthPlanProposal::computePlan` returns state `none` for a past month without an approved plan (`app/Support/MonthPlanProposal.php:150-176`).

### 3. Write-path side effects to avoid

- `CreateBudget` gives every active list Category a line, plan from the newest earlier Budget or 0 (`app/Actions/CreateBudget.php:17-80`, ADR-0068). For 2025 that would create lines for all 40 active Categories with plan 0.
- `CreateListCategory` / `CreateCategory` call `JoinDraftBudgets` (`app/Actions/CreateListCategory.php:55`), which adds a plan-0 line to the 2027 draft, and write a Rhythm entry from the current month (`:58`). A Category that exists only in history must be written without these actions, or 2027 changes.
- `JoinBudgetAtFirstUse` refuses archived Categories and lines (`app/Actions/JoinBudgetAtFirstUse.php:169-207`).
- `raw_statement_description` feeds import Category suggestions (`app/Support/CsvImport/ImportCategorySuggester.php:78-93`) and the `import:suggestion-accuracy` measurement, which replays the whole history (`ImportCategorySuggestionAccuracy.php:62-77`). Loaded rows should leave it null (the glossary defines it as the bank file row); the seeder's MARKER in that column does feed suggestions in dev.
- Restore is an all-or-nothing replace that re-ids every row and resets the user's data and sessions (`app/Actions/RestoreUserData.php:59-102`, `app/Actions/ResetUserData.php:13-38`). "Backup, inject the year into JSON, restore" bypasses validation but breaks id-based before/after verification; not a fit for "nothing changes".

### 4. Account options

| Option | Stored values | Readings | Domain rule |
| --- | --- | --- | --- |
| A. Raw rows on the real Accounts, balances untouched (seeder style) | unchanged | unchanged | violates the glossary Account rule "Opening date cannot be later than the earliest … Financial Operation" (`context/foundation/glossary.md:12`) (7 Accounts open 2025-12-31); a later edit or delete of a loaded row through the UI reverts its amount from a real balance (`ManageFinancialOperation.php:255-289`) |
| B. One technical archived Account per year, `opening_date` = 1 January, `opening_balance` = −(net), current 0 | new rows only | 2027 deficit warning changes by the sum of the technical opening balances (`BudgetDeficitWarning.php:50-57`); Account filter lists it (`OperationsFilterOptions.php:86-95`); dashboard lists archived Accounts (`DashboardController.php:185-187`) | keeps the opening-date rule; "opening balance is not income" (`context/foundation/mvp.md:55-58`) is bent: −181,65 for 2025 is a bookkeeping offset, not money held |
| C. Technical Account with opening 0 | its current balance = net (−181,65 for 2025) | SpendableLiquidity and TotalFunds change (`app/Support/SpendableLiquidity.php`, `app/Support/TotalFunds.php`) | rejected by the requirement |
| D. B plus excluding technical Accounts from `liquidityAtYearStart` | new rows + a marker column | none of the 2026/2027 readings above change | new column (migration, snapshot version bump per `.ai/rules/snapshot.md`), glossary entry and ADR |

The normal write path refuses archived Accounts, so an Account in B/D either stays active until the rows are written or the rows are inserted directly (seeder style).

### 5. Category mapping

ADR-0063 and `.ai/rules/categories.md` link years by Category id, never by name; `CategoryNameKey` may only propose matches. The mapping sheet item → list `categories.id` must be an explicit table the user approves. Sheet items with an obvious list Category: Rata kredytu → Kredyt (9), Czynsz (11), Prąd (15), Przedszkole (18), Apteka (12), Leczenie prywatne (20), Bilety - parking - inne → Bilety i parking (22), Ubezpieczenie auta → Ubezpieczenie samochodu (21), Paliwo (16), Rozrywka i wyjścia → Rozrywka (23), Telefony/Internet → Telefon + Internet (14), irregular Ubrania (27), Prezenty (31), Usługi (33), Edukacja (30), Darowizny (28), Książki (34); income Sebastian (1), Jana (2), 500+ → 800+ (3), darowizny (4), odsetki (5), pozostałe (7) with the luty tax refund → Zwrot podatku (6). Items without an obvious target: Codzienne wydatki (35 282,20 — the list splits it into Artykuły spożywcze, Restauracje, etc.), Abonamenty, Ubezpieczenie (life?), Fundusz remontowy, Wyjazdy i wycieczki (→ archived Wakacje 25?), FWN, irregular Dom, Osobiste, Dziecko, Praca, blank and "-". These are user decisions; a historic-only Category would be inserted archived and without `JoinDraftBudgets`.

### 6. Readings that change once a past execution Budget exists

Change a value currently shown for 2026/2027:

- `ReportTwelveMonthWindow` (`app/Support/ReportTwelveMonthWindow.php:44-49`): on 2026-11-12 the window is 2025-11 … 2026-10 with `covered_month_count` 10; a 2025 execution Budget makes it 12, changes totals and shares and removes the coverage note (ADR-0081).
- `ReportComparisonPeriod::resolve` (`app/Support/ReportComparisonPeriod.php:31-56`): every 2026 Year and Month report gains the previous-year column; current-period amounts and shares stay. The in-progress month compares with the same days a year earlier (`:50-55`), so the date chosen for monthly aggregates decides whether they fall into "November so far".
- `BudgetDeficitWarning` under option B (above).
- `CategoryListReading` (`app/Support/CategoryListReading.php:72-74, 129-146`) and `CategoryTypeChange::lockedIds` (`app/Support/CategoryList/CategoryTypeChange.php:28-52`): `budget_years` gains the year; a Category referenced by a loaded line loses "can delete" / "can change type".

Add information only: `SelectedBudgetYear` default stays 2026 (ADR-0075); `ShellState` and `budget-year-segments.ts` gain a year; Operations filter year/category options and section totals grow; selectors loading every execution Budget (`CategoryChoiceOptions`, `EntryToSpendInputs`, `HandEntryMonthRemainingInputs`, `ImportMonthRemainingContext`) gain 2025, so 2025 becomes a valid year for hand entry.

Do not change: `IrregularCollected` chains start at the Irregular period start (`app/Support/IrregularCollected.php:63-73`) and production has no Irregular rhythm; `BudgetCollectedAtYearStart` likewise; `MonthPlanProposal` reads year−1 only for a January proposal and January 2026 is past (`MonthPlanProposal.php:175-177, 219-225`); `MonthYearComparison`, `ReportTrend` and the dashboard work within one Budget (ADR-0031). The loader must write no `category_rhythms` row to keep this true.

### 7. Verification

`ControlNumbers::collect()` reports row counts, `max_financial_operation_id`, per-Budget sums, per-line plans/execution/operations, per-Account opening/current balances, own transfers and capital movement totals (`app/Support/ControlNumbers.php:45-75, 169-288`). A proof of "only additions" needs a comparison that accepts: `(missing) ->` paths only under new Budget, line and Account ids; row-count deltas equal to the loaded rows; no `X -> Y` on an existing id; every new line's `budget_id` a new Budget (a line joined into 2027 would otherwise pass, since 2027 sums stay equal with plan 0). Categories, Rhythms and Groups are only `meta` counts, so the list needs its own check (no new active Category, no change to existing rows). PRD v3 "Migracja danych" (`prd-v3.md:245-251`) and ADR-0064 ask for `user:backup` before, control numbers before/after, a named rollback and a trial on a production copy; the seeder's marker-based `remove()` (`PreviousYearMockSeeder.php:67-94`) is the rollback precedent.

## Code References

- `database/seeders/PreviousYearMockSeeder.php:38-250` — closest precedent: execution Budget, copied lines, bulk insert, execution = sum, marker-based remove
- `app/Console/Commands/SeedPreviousYearCommand.php:13-27` — command shape; local/testing guard a production loader must not copy
- `app/Actions/ManageFinancialOperation.php:138-143, 224-240, 255-289` — active Account lock, Budget by year, balance and execution updates
- `app/Support/OperationEntryValidation.php:36`, `app/Support/CsvImport/FinancialOperationImportValidator.php:40-47, 95-127` — opening-date and archived-Account checks
- `app/Actions/StartBudgetExecution.php:94-114` — execution start zeroes Execution
- `app/Actions/CreateBudget.php:17-80`, `app/Actions/CreateListCategory.php:55-58` — side effects on drafts and Rhythms
- `app/Support/BudgetDeficitWarning.php:50-57` — opening balances summed for the year start
- `app/Support/ReportTwelveMonthWindow.php:44-49`, `app/Support/ReportComparisonPeriod.php:31-56` — reports that change
- `app/Support/ControlNumbers.php:45-107` — what is compared and how additions are reported
- `app/Support/CsvImport/BnpParibasXlsxParser.php` — existing PhpSpreadsheet use

## Architecture Insights

- Readers trust stored aggregates (`current_balance`, `execution`) and never recompute them, which is why the seeder's direct insert works and why a loader must write `execution_minor_units` itself.
- A Financial Operation's Budget is the Budget of its date's year, and years join by list Category id, so a loaded year plugs into Reports with no reader change.
- Console commands use `#[Signature]`/`#[Description]`, `ResolvesConsoleUser` and `ReadsAndWritesJsonFiles`; destructive ones confirm unless `--force` (`app/Console/Commands/RestoreUserDataCommand.php:42-48`).
- Glossary: history is ordinary Financial Operations (_Avoid_ "archival data / historical dump as a persisted type"); "Import" names the bank-file flow with preview, so this loader needs another name; Year Close is not on `main`.

## Historical Context (from prior changes)

- `context/foundation/prd-v3.md:33-45` — the sheet described on 2026-11-07; supported by this reading (same groups, totals 134 671 / 134 852).
- `context/foundation/prd-v3.md:199, 256, 287`, `context/foundation/roadmap.md:379` — "dane 2025 tylko jako mock na devie; produkcja porównuje lata od 2027": still the recorded decision; this change reverses it.
- `context/archive/2026-11-11-year-over-year-report/plan.md:60` — S-29 kept the sheet import and name matching out of scope.
- ADR-0064 / `prd-v3.md:245-251` — data-migration safeguards; apply by analogy (this is a data load, not a schema migration, and `scripts/deploy.sh` does not run it).
- ADR-0063, ADR-0068, ADR-0073, ADR-0074, ADR-0075, ADR-0076, ADR-0081 — as cited above.

## Related Research

- `context/archive/2026-11-11-year-over-year-report/` — comparison with the previous year
- `context/archive/2026-11-12-rolling-twelve-month-report/` — last-12-months coverage

## Open Questions

1. **Earlier years (gap).** Which files and years; whether their layout matches 2025 (the template suggests close, with drift). Needed before the parser is fixed.
2. **Account option** A / B / D (C fails the requirement). B changes the 2027 deficit warning; D needs a column, glossary entry and ADR; A breaks a glossary rule.
3. **Transfers:** exclude D23 and I9 (recommended by the sheet's own "Analiza" totals)? Treat \*\* set-asides as expenses (sheet view) or skip them?
4. **Category mapping table** for the items without an obvious target, including whether Codzienne wydatki lands in one Category and how blank/"-" irregular items are filed.
5. **Plan values** of the loaded Budget Lines: 12 × Ustawienia default, actual sum, or 0.
6. **Operation dates:** first or last day of the month (affects the in-progress month comparison), and whether irregular items become one operation each.
7. **Accepted reading changes:** the last-12-months report and the 2026 comparison change by design; confirm with the user.
8. **Name** of the loader (not "Import") and the ADR 0083 scope, plus the PRD/roadmap update.
