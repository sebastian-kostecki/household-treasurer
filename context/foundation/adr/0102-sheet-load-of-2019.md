# Sheet Load: the year 2019 from the oldest spreadsheet

ADR-0085 loads 2022–2025 and ADR-0096 adds 2020 and 2021. The User also has `Budżet_2019.xlsx`: a workbook with the sheets `styczeń_19` … `grudzień_19`, `grudzień_18` and `Podsumowanie` instead of `Analiza`, and three variants of a month sheet in one year. ADR-0096 decision 1 left it out. This ADR extends the scope of ADR-0085 and ADR-0096 to 2019 before the code (change `sheet-load-2019`).

Rule. The Sheet Load (`budget:load-sheet`, `--dry-run`, `budget:unload-sheet`, a Sheet Account per year, plan = execution) stays as it is. The reader gets a separate class for the 2019 workbook that returns the same `BudgetSheet` and `MonthSheet`; the planner learns lists attached to an expense row and expense overrides; a new Category becomes a set of definitions.

Decisions of the planning:

1. Scope and layout: 2019 from a workbook that has the sheet `Podsumowanie` and twelve sheets `<month>_19`, recognised before the refusal of an unknown layout. The year is the year of `grudzień_19!C31`, which must be 31 December and agree with the suffix; otherwise the load is refused with the name of the cell. `grudzień_18`, `Oszczędności` and `Urlopy` are not read. No third `SheetLayout` and no `--year` option.
2. Truth: the corrected `Podsumowanie!H16` (expenses) and `I16` (income) play the role of „Analiza”. `H3:H14` is the execution of the expense rows without „cele” and without „Oszczędności”; `marzec_19!B3` includes the income „inne” 125,00. The file is fixed at the source as in ADR-0096 decision 3 (record: `context/changes/sheet-load-2019/workbook-fix.md`, SHA-256 before and after, a copy of the original). After the fix `H16` is 67 286,75 and `I16` is 78 039,29. The code keeps comparing operations with these cells; there is no exception in it.
3. Skips: „cele: Sebastian” (January–June), „Cele” (July–December), „Oszczędności”, „Oszczędności (10%)”, „z poprzedniego” (June) and the income „oszczędności” (July) are skipped (SKIP). They are neither operations nor fund movements, and the corrected `Podsumowanie` does not count them.
4. Lists: the lists „Wydatki osobiste” (Sebastian, Jana) and „Dom i akcesoria” are split into the items of the sheet, in the Category of their row (personal → 29, home → 37). The part of the row that the list does not explain is a remainder in the Category of the row. The breakdown of „Codzienne wydatki” of November and December is read as in ADR-0096 decision 4.
5. „Rachunki” (July–December, one cell of the sum of the bills): split into 14 Telefony („Play”), 44 Internet („UPC”) and 15 Prąd by the components of the formula of `D10`, as expense overrides of the mapping.
6. „Stypendium”: a new Category of income, only for 2019, created by the load as archived (like the former „Kosmetyki”), not joining the draft, removed by `budget:unload-sheet`. An income Category has no Rhythm, so nothing tells the loaded one from one the user made: the unload removes a new Category only when the unloaded Budget had a line for it, and the load refuses to reuse an archived one without lines (implementation review F1). The one fixed new Category of ADR-0085 becomes a set of definitions keyed by name.
7. Mapping of the other labels: prąd+gaz → 15; samochód: inne → 17; cinema city, rozrywka: inne, rozrywka - inne, wyjścia → 23; lekarze, zdrowie i uroda: inne → 20; apteka i kosmetyki → 12; akcesoria do domu → 37; osobiste (and „jana”, „sebastian”) → 29; siłownia → 49 Sport (read from production on 2026-11-16, `production-categories.md`); income „inne” → 7. The items „do oddania” are loaded as they are (29) and corrected after the load.

Consequences. The stored values of 2020–2027 do not change. The proof is `app:control-numbers --compare=<before> --allow-additions` („categories +1”). The readings that change: the year selector (2019 first), the „year before” column of the 2020 Report and the „Lata” view starting in 2019. A fix of the mapping goes through `budget:unload-sheet` and a new `budget:load-sheet`.

We rejected:

- `Podsumowanie` without the fix and a list of deviations;
- a total computed from the rows in the code (a check that checks itself);
- the goals as an expense in categories, and as a fund movement in the reconciliation (chosen first, changed by the User: payments into goals are not expenses of the year);
- one operation per row instead of the items of the lists;
- „Rachunki” entirely as Prąd, and a split by today's charges;
- the scholarship as a source „Sebastian”;
- a third `SheetLayout` in `MonthSheetReader`.
