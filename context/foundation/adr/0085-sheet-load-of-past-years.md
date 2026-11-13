# Sheet Load: the years 2022–2025 enter production from the old spreadsheets

PRD v3 parks the import of the budget spreadsheet: "dane 2025 wchodzą tylko jako mock na devie" (`prd-v3.md:199`, `:256`, `:287`) and the production comparison of years starts in 2027. The User has the sheets `Budżet_RRRR.xlsx` for 2022, 2023, 2024 and 2025 and wants those years in production as real Budgets, so that Reports, the year before in Reports (ADR-0074), the last 12 months (ADR-0081) and the year selector have history. This ADR reverses that PRD v3 decision for these four years, once, and fixes how they are written, before the code (change `legacy-budget-import`).

Rule. A **Sheet Load** is a one-off administrative command (`budget:load-sheet`, with `--dry-run` as the only preview and `budget:unload-sheet` to undo one year) that writes one past year from an xlsx file as a Budget already in execution with ordinary Financial Operations. Each year gets its own **Sheet Account** („Arkusz RRRR”), flagged by `accounts.sheet_year`, opened on 1 January, archived, with the opening balance equal to −(income − expense of the year) and the current balance 0 after the load. The load does not call any domain action (`CreateBudget`, `StartBudgetExecution`, `ManageFinancialOperation`, `CreateListCategory`, `JoinBudgetAtFirstUse`): each of them changes the draft of 2027, a Rhythm or the balance of a real Account. It inserts the rows directly, as `PreviousYearMockSeeder` does, in one transaction under the lock of the User, and every Budget Line gets Initial Plan = Current Plan = Execution = the sum of its operations.

Decisions of the planning (register 1–8):

1. Scope: 2022, 2023, 2024, 2025. One parser finds the blocks by their labels; a mapping file holds the label variants. A layout that is not one of the four read ones is refused with the name of the cell.
2. Account: a Sheet Account per year, left out of the liquidity at the start of the year (`BudgetDeficitWarning`), of Spendable Liquidity and of Total funds, hidden on the dashboard and the Accounts page, visible in the Operations Account filter.
3. Rows: „Analiza jest prawdą”. Each year sums to `Analiza!B16` (income) and `Analiza!C16` (expense) of its file. Payments into savings and the „oszczędności” income row are skipped; the set-aside marks `**` (in 2022 also `*`) are an expense in their own Category; a month total larger than its list is one operation „reszta bez rozpisania”. Negative income cells (transfers between months) are carried as an expense correction or a refund in the same month, because income is at least 1.
4. „Codzienne wydatki” goes to Artykuły spożywcze; in 2022 by its breakdown (hygiene and cosmetics to Rzeczy osobiste, nappies to Dzieci, the rest to Artykuły spożywcze).
5. The mapping table of Categories is accepted, except Kosmetyki*, Kosmetyczka, Fryzjer, „Kosmetyczka i fryzjer” and the irregular „kosmetyki”: they go to one new archived Category „Kosmetyki”.
6. Plan = execution (Initial = Current = sum of the operations of the line). A plan of 0 or 12 × the setting would invent a plan the User never made.
7. Operation date: the last day of the month; items of the irregular, treatment and tests lists are separate operations named by column K, the monthly totals are one operation per sheet row and month.
8. The changed readings below are accepted.

Consequences. The stored values of 2026 and 2027 do not change: balances of Accounts and Term Deposits, Budget Lines and plans of both Budgets, Month Plans, Collected, the deficit warning of the 2027 draft. The proof is `app:control-numbers --compare=<before> --allow-additions`, which accepts only added Budgets, their lines, Sheet Accounts, operations and the one archived Category.

Readings that change by design: the last-12-months report (full coverage, no note), the year-before column of every report of 2026 and of later years back, the „Usuń” and type-change locks on Categories with history, the years 2022–2027 in the year selector and the sidebar, the Operations list (without a year filter it shows the sheet operations and larger `OperationsSectionTotals`), the props of hand-entry and Import (`EntryToSpendInputs`, `HandEntryMonthRemainingInputs`, `ImportMonthRemainingContext` have an entry for each loaded Budget; the shape is the same), and the refusal of hand-entry and Import with a date in a loaded year.

Operations of a Sheet Account cannot be edited and their Category cannot be changed in the UI (the policy answers 403; amended by ADR-0089: the Category alone can be changed); a loaded year is closed for hand-entry and Import. A fix of the mapping goes through `budget:unload-sheet` and a new `budget:load-sheet`. Deleting a single operation stays as it is. Sheet operations have no Raw Statement Description and no Import identity, so Import suggestions and `import:suggestion-accuracy` do not see them.

Relation to other ADRs. ADR-0063 (one Category list across years) is what makes the Categories of the loaded Budgets the same ones as in 2026. ADR-0064 requires the backup before the migration of the Sheet Account column and the rehearsal on a restored copy. ADR-0068 (a new Budget takes active list Categories) is not touched: the archived „Kosmetyki” never joins a draft. ADR-0074 and ADR-0081 get data, their rules stay. The PRD v3 clauses are annotated, not rewritten.

We rejected:

- history on the real Accounts (the real Accounts open on 2025-12-31 and refuse earlier dates; moving their opening dates would change the balances);
- a technical Account without a flag (nothing could exclude it from liquidity and the dashboard without guessing by name);
- skipping Accounts archived before 1 January in the liquidity reading (it would change what an archived real Account means to `BudgetDeficitWarning` and to the User);
- a Category „Codzienne wydatki” (a new active Category would join the draft of 2027 and add a Rhythm);
- a plan of 0, or 12 × the setting (a plan the User never made, and Reports would show a deviation of 100 %);
- the first and the fifteenth day of the month as the date (a precision the sheet does not have; the last day of the month keeps every operation inside its month);
- running the load from `scripts/deploy.sh` (a one-off action must not run on every deploy).
