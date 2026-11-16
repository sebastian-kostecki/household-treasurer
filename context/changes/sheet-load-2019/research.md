---
date: 2026-11-16T06:52:10+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 3a983ca9a13ca90f9b76060129f86e4098dcc672
branch: development
repository: household-treasurer
topic: "sheet-load-2019: can Budżet_2019.xlsx be loaded through the Sheet Load, and what would it take"
tags: [research, codebase, sheet-load, xlsx, budget, mapping, adr-0085, adr-0096, sheet-load-2019]
status: complete
last_updated: 2026-11-16
last_updated_by: Claude (claude-opus-5-5)
---

# Research: loading 2019 through the Sheet Load

**Date**: 2026-11-16T06:52:10+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 3a983ca9a13ca90f9b76060129f86e4098dcc672 (only this change folder on top; no code of this change exists)
**Branch**: development
**Repository**: household-treasurer

## Research Question

Can the Sheet Load (ADR-0085, ADR-0096, `budget:load-sheet`) load `storage/app/private/Budżet_2019.xlsx` (main checkout, gitignored, SHA-256 `b99e9217bc4b4bfa075e7a25e815598118c16e50c94e000a2b5a499b35fbf0f6`)? This research covers the workbook's layout, whether its totals give a sound reconciliation target, which 2019 labels map to which Categories, what the reader, planner, mapping, tests and docs would need, and the decisions the user has to make. The question comes from the conversation of 2026-11-16, where three routes were proposed: monthly totals, a full reader, or converting the workbook to the 2020–2021 layout.

## Summary

- **Not today.** The reader refuses the file at layout detection. It expects month sheets `styczeń`…`grudzień` (`app/Support/SheetLoad/BudgetSheetReader.php:15`) and an `Analiza` sheet (`:28`). It recognises only `Ustawienia` or „kategoria” in `styczeń!I3` (`:46`). The 2019 file has sheets `grudzień_18`, `styczeń_19` … `grudzień_19`, `Oszczędności`, `Podsumowanie` and `Urlopy`, and no `Analiza`.
- **Nothing in the domain blocks 2019.** Years 2000–2100 are allowed (`app/Http/Requests/StoreBudgetRequest.php:22-27`). `assertYearFree` checks only the same year (`app/Actions/LoadBudgetSheet.php:87-96`). Loaded-year locks are keyed per `sheet_year`, as found for 2020–2021 (`context/archive/2026-11-13-sheet-load-2020-2021/research.md` §5). A loaded 2019 changes two readings by design: the year-before column of the 2020 report (`app/Support/ReportComparisonPeriod.php:35`), and the „Lata” view, where 2019 becomes the first column (`app/Support/ReportYearsRange.php:37-42`).
- **The workbook has four structures, not one.** In this file, by sheet:
  - **A0** `grudzień_18`: plan only, no actual column. No formula references it, so it is outside every 2019 total.
  - **A** `styczeń_19`–`luty_19`: income from `B5`, expenses from `B10`, plan in B, actual in C.
  - **B** `marzec_19`–`czerwiec_19`: the same as A, two rows higher (income `B3`, expenses `B8`). June merges the two personal rows into one.
  - **C** `lipiec_19`–`grudzień_19`: a new template. Labels in B, plan in C, actual in D, income in C2. Personal lists have a plan/actual split from October, and a daily breakdown appears in November and December.
  - Column positions differ between A/B and C. A third `SheetLayout` case does not fit the label-driven `MonthSheetReader`, which hard-codes expenses in B/D, „wydatki nieregularne” and „oszczędności” in G (`MonthSheetReader.php:18-23`).
- **`Podsumowanie!H16` (64 035,95 zł) is not a sound „Analiza jest prawdą” target.** Its monthly cells mix plan and actual:
  - January–June read the plan column B (`Podsumowanie!H3 =styczeń_19!B10`, `styczeń_19!B10 =SUM(B11:B26)` = 4 178,83).
  - May's plan includes the goals.
  - July reads two **income** cells (`Podsumowanie!H9 =lipiec_19!C3+lipiec_19!C4` = 5 273,20; `lipiec_19!A3/A4` are „praca: Sebastian”/„praca: Jana”).
  - August–November read actual expenses including „Cele”; December stops before „Cele”.
  - Loading operations that equal H16 would mean loading plan values and July's salaries as expenses.
- **`Podsumowanie!I16` (77 914,29 zł) is nearly sound.** It is the actual income rows minus three cells:
  - `czerwiec_19!B7` „z poprzedniego” 699,00, a carry-over;
  - `lipiec_19!C6` „oszczędności” 5 420,59, a withdrawal from savings;
  - `marzec_19!B7` „inne” 125,00, which falls outside `marzec_19!B3 =B4+B5+B6`, probably a range slip.

  The first two would be skipped like the „oszczędności” income of later years. The third needs a decision.
- **Recommendation:** a dedicated 2019 reader that produces the same `BudgetSheet`/`MonthSheet` as the existing readers (`BudgetSheet.php`, `MonthSheet.php`). It would read one expense row per month from fixed per-variant columns, guarded by labels, with lists optionally split into items. The planner, `LoadBudgetSheet`, the Sheet Account and unload stay as they are, apart from the reconciliation target. Converting the workbook by hand into the 2020–2021 layout is the alternative, but it means rebuilding 12 sheets. This research did not measure that effort.
- **Before code, the user decides** (an ADR amending ADR-0096 decision 1 „2019 is out of scope”, next free number 0102):
  - what the 2019 truth is (actual totals rebuilt from rows, `Podsumowanie` with a listed set of deviations, or something else);
  - whether goals („Cele”, „cele: Sebastian”, 6 144,80 zł) and „Oszczędności (10%)” (3 988,17 zł) are fund movements or skipped;
  - the Category of each new label, including two with no existing target: siłownia and stypendium.

## Detailed Findings

Source of the workbook facts: a worker read the file with PhpSpreadsheet on the host (php 8.4.1, read-only), with formulas plus cached values. Recalculating all 818 formulas changed only 21 cells, all `TODAY()`-dependent, so cached values are trusted. Scratch scripts and dumps are in the job tmp directory (`dump.php`/`dump.txt`, `months.php`, `audit.php`, `inventory.php`, `orphans.php`) and are not kept in the repo. The parent re-checked these cells in the dump: `Podsumowanie!H3, H9, H16, I16, J16, I5`, `styczeń_19!B10`, `marzec_19!B3, A7, B7`, `lipiec_19!A2:A7, C2:C7, B23:D24` and `grudzień_19!B24:D25, C31`. The monthly rebuilt totals in §2 come from the worker's `months.php` and were not re-verified cell by cell.

### 1. Workbook structure (all 13 month sheets inspected)

- **No year number in any title.** Date constants give the last day of the month from June: `czerwiec_19!F1`, `lipiec_19!C28`, … `grudzień_19!C31` = 43830 = 2019-12-31. January–May have no date cell. Otherwise the year exists only as the `_19` suffix of the sheet names.
- **A0, `grudzień_18`:** income `B5` = 7 520,00, expenses `B10 =SUM(B11:B26)` = 4 103,00, column B only. Lists are two columns further left than in January (Sebastian D/E, Jana F/G). No formula in the workbook references this sheet; the worker scanned every cross-sheet reference.
- **A, January–February:**
  - Income: `A5` „Przychody”, `B5 =B6+B7+B8`, plus `+B9` „inne” in February.
  - Expenses: `A10` „Wydatki”, `B10 =SUM(B11:B26)`, labels in A11:A28, plan in B, actual in C, `D =C/B`.
  - The list rows hold the list sum in B and have no C:
    - `B24` akcesoria do domu `=SUM(I24:I27)`;
    - `B25` osobiste: Jana `=SUM(I7:I15)`;
    - `B26` osobiste: Sebastian `=SUM(G7:G15)`;
    - `B27` cele: Sebastian `=I19+I20+I21`, outside B10.
  - Lists:
    - „Wydatki osobiste”: Sebastian F/G, Jana H/I, rows 7–15;
    - „Cele (odkładane przez Sebastiana)”: F19:F21, amounts in I;
    - „Dom i akcesoria”: F24:F27 / I24:I27.
  - „Podział wydatków” in K5:R22 holds the hand-typed daily sums (`C15 =M13+Q11`).
- **B, March–June:**
  - Income `B3 =B4+B5+B6`, expenses `B8`, labels from A9.
  - March and April: `B8 =SUM(B9:B24)`, with cele in A25 outside.
  - May: `B8 =SUM(B9:B25)`, which includes cele 315,00.
  - June:
    - `B8 =SUM(B9:B23)`;
    - one row `A23` „osobiste” instead of two;
    - `A25` „Oszczędności (10%)”;
    - an unused actual total `C27 =SUM(C9:C24)` = 5 734,82;
    - list rows with a C value, which no other month of A/B has.
- **C, July–December:**
  - Income: `A2` „Przychody”, `C2 =C3+C4+C5+C6` from „praca: Sebastian”, „praca: Jana”, „inne”, „oszczędności”. No plan/actual split for income.
  - Expenses: `A7` „Wydatki”. Group labels are merged in A, item labels in B, plan in C, actual in D.
    - July: rows B8:B24, „Pozostałe” at B25.
    - August–December: B8:B25 with „Bilety i transport” added at B14, „Pozostałe” at B26.
    - „Cele” and „Oszczędności (10%)” are the last two item rows (`lipiec_19!B23/B24`, `grudzień_19!B24/B25`).
  - Personal lists:
    - July–September: Sebastian G/H, Jana I/J.
    - October–December: name, plan and actual, as Sebastian G/H/I and Jana J/K/L.
  - Lists „Cele do odłożenia” and „Dom i akcesoria”, whose rows move by one to three rows from month to month.
  - Side blocks „Rachunki” and „Wizyty” that no formula links to the expense rows.
  - November–December: a daily breakdown of „Codzienne wydatki” at M12:O16 (`D12 =SUM(O13:O16)`).
- **Other sheets:**
  - `Oszczędności` is a fund ledger: Oszczędności, Fundusz awaryjny, naprawy samochodu, ubezpieczenia samochodu, na prąd, spłaty kredytu, Chrzciny, Ślub, Wyprawka, Rezerwa na ogrzewanie.
  - `Urlopy` counts vacation days.
  - Neither is referenced by `Podsumowanie` or by a month sheet (worker's cross-reference scan).
- **Within the scanned expense, income and list amount cells:**
  - no stored negative amount;
  - no text in an amount cell;
  - 152 formulas made only of numbers (e.g. `styczeń_19!I8 =42+75`, `lipiec_19!C3 =2202.05+600.61+870.54-700`). The reader works on cached values, as `BudgetSheetReader` already does (`BudgetSheetReader.php:100`, `setReadDataOnly(true)`).

### 2. Totals: Podsumowanie against the rows

`Podsumowanie` row 16 sums rows 3–14:

| Cell | Value (zł) |
| --- | --- |
| `H16` (expenses) | 64 035,95 |
| `I16` (income) | 77 914,29 |
| `J16` (savings) | 11 228,06 |

What each monthly H cell reads (worker table, the cells named in the Detailed Findings header re-checked):

| Months | H reads | Example |
| --- | --- | --- |
| I–IV, VI | plan column B, goals excluded | `H3 =styczeń_19!B10` |
| V | plan including cele | `maj_19!B8 =SUM(B9:B25)` |
| VII | two income cells | `H9 =lipiec_19!C3+lipiec_19!C4` |
| VIII–XI | actual D including „Cele” | `SUM(…!D8:D24)` |
| XII | actual D without „Cele” | `SUM(grudzień_19!D8:D23)` |

Rebuilt by the worker (`months.php`). Actual is C (I–VI) or D (VII–XII), and the list sum in B where a list row has no C.

| Measure | Year (zł) |
| --- | --- |
| Actual expenses without goals and savings | 67 286,75 |
| Actual expenses including „Cele” / „cele: Sebastian” | 73 431,55 |
| Plan without goals | 67 786,02 |
| `H16` | 64 035,95 |
| Goals over the year | 6 144,80 |
| „Oszczędności (10%)”, June–December | 3 988,17 |

Per month, H minus actual without goals ranges from −95,76 (VI) to +491,19 (I), and July is −5 722,32.

Income: all income rows sum to 84 158,88 zł. Without „z poprzedniego” 699,00 and July „oszczędności” 5 420,59 they sum to 78 039,29. That is I16 + 125,00, where 125,00 is `marzec_19!B7` „inne”, outside `marzec_19!B3`.

Two list rows differ from their list (worker, `months.php`):
- `marzec_19!G14` „bilety” 10,00 lies outside `B24 =SUM(G5:G13)`;
- `lipiec_19!D21` (actual Osobiste 2 991,54) leaves out `H4` fryzjer 35,00, which the plan `C21` includes.

Balance rows: `lipiec_19!D25` = 375,27 and `sierpień_19!D26` = 10,23; September–December are 0.

Inference, not proven: in August–November, „Fundusz spłaty kredytu” sits in H (inside „Cele”) and again in J (`Podsumowanie!J10 =sierpień_19!D25+100`, with `sierpień_19!J16` = 100), so J counts it twice.

### 3. Labels (actual, non-zero, year sums from the worker's inventory)

- **Income:**
  - praca: Sebastian 33 660,08 (I–XII);
  - praca: Jana 34 511,95 (I–XII);
  - stypendium 6 000,00 (I–VI);
  - inne 3 867,26 (II, III, VII–XI);
  - z poprzedniego 699,00 (VI);
  - oszczędności 5 420,59 (VII).
- **Expense rows, January–June spelling:**
  - czynsz, prąd+gaz (0), telefony, internet, codzienne wydatki, paliwo;
  - samochód: inne 328,00; Cinema City 1 008,00; rozrywka: inne 441,89;
  - siłownia 218,00 (I–II); kosmetyczka 580,00;
  - lekarze 2 740,00 (whole year); zdrowie i uroda: inne 103,00;
  - akcesoria do domu 5 661,75 (whole year);
  - osobiste: Jana 3 521,20, osobiste: Sebastian 2 927,81 (I–V), osobiste / Osobiste 9 589,69 (VI–XII);
  - cele: Sebastian 1 288,00; Oszczędności; Oszczędności (10%).
- **Expense rows, July–December only:**
  - Rata kredytu 7 446,08;
  - Rachunki (prąd+telefon+internet) 248,05, and „Rachunki ” with a trailing space 1 124,10;
  - Ubezpieczenie 604,38; Bilety i transport 153,60; Wyjścia 334,40;
  - Kosmetyczka i fryzjer 660,00; Apteka i kosmetyki 706,51; Apteka 746,22;
  - Cele 4 856,80.
- **Daily breakdown (XI–XII):** Jedzenie 1 998,75; Higiena i kosmetyki 185,96; Środki czystości 154,28; Gospodarstwo domowe 0.
- **Items the worker flagged as possibly not consumption (inference):**
  - Jana „do oddania od U.” 180, „oddać (alb+apt+l)” 850, „Urszula” 100;
  - „wyprawka” 819,13;
  - Sebastian „kurs” 1 410, „akt notarialny” 330.
- **Already mapped**, labels normalised by `SheetCells::normalizeLabel()`: czynsz → 11, telefony → 14, internet → 44, codzienne wydatki → 8, paliwo → 16, kosmetyczka → 42, kosmetyczka i fryzjer → 42, apteka → 12, bilety i transport → 22, rata kredytu → 9, ubezpieczenie → 19, praca: sebastian → 1, praca: jana → 2, oszczędności (income) → SKIP (`database/sheet-load/mapping.php:15-127`). The daily labels jedzenie, higiena i kosmetyki, środki czystości and gospodarstwo domowe are mapped too (`:63-71`).
- **New labels, with the nearest existing target as a hint, not a decision:**

  | Label | Hint |
  | --- | --- |
  | prąd+gaz | 15 |
  | samochód: inne | 17 |
  | cinema city, rozrywka: inne, wyjścia | 23 |
  | lekarze | 20 |
  | zdrowie i uroda: inne | 20, 12 or 42 (ambiguous) |
  | akcesoria do domu | 37 |
  | osobiste, osobiste: jana, osobiste: sebastian | 29 |
  | apteka i kosmetyki | 12 or 42 |
  | rachunki | 15, 14 and 44 combined; one target or a split |
  | inne (income) | 7 |
  | z poprzedniego | SKIP |
  | cele, cele: sebastian, oszczędności, oszczędności (10%) | SKIP or FUND (decision) |
  | siłownia | no existing target |
  | stypendium | no existing target |

- **Two traps:**
  - `lekarze` is not in `MonthSheet::TREATMENT_ROWS` (`MonthSheet.php:15`), so it stays one row operation per month.
  - A new target id must also go into `mapping.php` `categories` with its production name; the name check only covers listed ids (`SheetMappingCategoryCheck.php:17-37`).

### 4. Categories on production

- The production names that can be trusted are those in `mapping.php:145-185`, checked by the production `--dry-run` on 2026-11-14 (`context/archive/2026-11-13-sheet-load-2020-2021/production-load-notes.md:37`).
- **Gap:** the names of ids 13, 26, 38, 40, 41, 43 and 46–49 on production are not in the repo, and the dev database was not running. The only real-size local snapshot is `storage/app/private/user-snapshots/1-20261112070312.json`, from before the 2026-11-13 rebuild. A production „Sport” category is plausible (`prd-v2.md:196`; operations „decathlon” moved by hand, `production-load-notes.md:14`), but unconfirmed.
- The loader can create only the one fixed archived „Kosmetyki” (`LoadBudgetSheet.php:102-129`, `mapping.php:188-194`), so a new Category for siłownia or stypendium must exist before the load. ADR-0096 decision 6 notes that no label uses NEW_CATEGORY any more. Using it for 2019 would create a second „Kosmetyki” next to 42 „Chemia i kosmetyki”.
- **Rhythm:** a month before a Category's first Rhythm entry reads that entry's Rhythm (`app/Support/CategoryRhythms.php:16`). A month before the first Irregular period belongs to no period, so it has no Collected (`:133`). 2019 behaves the same as the loaded 2020–2025.

### 5. What a 2019 route needs in code

- **Reader:**
  - a 2019 entry point that maps sheet names with the `_19` suffix and skips `grudzień_18`;
  - takes the year from `grudzień_19!C31` (2019-12-31) or from the suffix;
  - reads the three variants by fixed columns with label guards;
  - fills `MonthSheet` with `irregularTotal`/`balance` set to neutral values, lists empty or filled by choice, and the daily breakdown in XI–XII.
- **Today's construction:**
  - `BudgetSheet` carries `analysisIncome`/`analysisExpense` as `SheetEntry` (`BudgetSheet.php`);
  - the planner asserts operations + fund movements = these entries (`SheetLoadPlanner.php:42-43`);
  - a 2019 truth other than one cell needs either a computed `SheetEntry` or a recorded per-year expected total. ADR-0096 rejected the latter: „correcting the amount of Analiza in mapping.php”.
- **Planner:** unchanged if 2019 rows come out as `expenses`/`income` entries, because the irregular list totals equal the list when both are empty. Remainders for `marzec_19!G14` and `lipiec_19!D21` appear only if the lists are split into items.
- **Tests:**
  - `tests/Support/SheetWorkbookFactory.php` knows layouts `'2023'`, `'2022'`, `'2020'` (`:14-17`, `:54`) and always writes 12 sheets named without a suffix plus `Analiza`;
  - a 2019 fixture needs its own branch (`_19` names, `grudzień_18`, `Podsumowanie`), with formula cells so a range bug cannot hide, as `production-load-notes.md` of 2022–2025 asked;
  - per-layout tests to mirror: `BudgetSheetReaderTest` (one „reads the X layout” test per layout), the `SheetLoadPlannerTest` real-mapping coverage test, the `LoadBudgetSheetTest` load and unload round trip on PostgreSQL, and the `LoadBudgetSheetCommandTest` dry-run.
- **Docs:**
  - `README.md:131-132`: the anchor;
  - `README.md:220`: the heading „Wczytanie arkusza (lata 2020–2025)”;
  - `README.md:231`: the upload loop;
  - the expected totals and load and unload order (`README.md:239-252`, worker anchors);
  - `roadmap.md:38,74`: „budżety 2020–2027”;
  - a new ADR.
  - The glossary entries for Sheet Load and Sheet Account name no years.

## Code References

- `app/Support/SheetLoad/BudgetSheetReader.php:15,28,46,100` — month sheet names, `Analiza`, layout detection, cached values
- `app/Support/SheetLoad/MonthSheetReader.php:14-40` — fixed B/D expense and G income labels for every layout
- `app/Support/SheetLoad/SheetLayout.php` — two layouts, exhaustive `match`
- `app/Support/SheetLoad/SheetLoadPlanner.php:42-43,373` — reconciliation with the sheet totals
- `app/Support/SheetLoad/MonthSheet.php:15` — `TREATMENT_ROWS`
- `database/sheet-load/mapping.php:15-127,145-194` — labels, expected production names, NEW_CATEGORY
- `app/Actions/LoadBudgetSheet.php:87-96,102-129` — same-year check, the only Category the load can create
- `app/Support/ReportComparisonPeriod.php:35`, `app/Support/ReportYearsRange.php:37-42` — readings that gain 2019
- `app/Support/CategoryRhythms.php:16,133` — Rhythm before the first entry
- `tests/Support/SheetWorkbookFactory.php:14-17,54-58` — fixture layouts

## Architecture Insights

- The Sheet Load splits into a reader (xlsx → `BudgetSheet`) and a planner/loader that only see `BudgetSheet`. A second reader for one odd year keeps the planner, the loader, the Sheet Account, unload and the control-number proof untouched. A third `SheetLayout` would spread three in-year variants through the label-driven reader.
- The safety net (operations + fund movements = a total the user wrote, to the grosz) caught a real bug in 2021 (ADR-0096 decision 3). In 2019 the written total is itself inconsistent, so the net needs a deliberate, recorded replacement, not a tolerance.

## Historical Context (from prior changes)

- `context/foundation/adr/0096-sheet-load-of-2020-and-2021.md:3,9` — „Year 2019 is another workbook … and stays out”; „2019 is out of scope”. Supported as the current rule. The reason recorded is the different workbook, not a domain constraint; this research confirms that the workbook is different.
- `context/archive/2026-11-13-sheet-load-2020-2021/change.md:12`, `research.md:24`, `plan-brief.md:30,36`, `plan.md:57,91` — 2019 dropped by the user's decision because of a different layout. Supported.
- ADR-0096 rejected these options: correcting the „Analiza” amount in `mapping.php`; reconciling with a second truth (`Analiza!E16:K16`); fund payments as expense or income. All three bear directly on the 2019 truth decision.
- ADR-0096 decision 6: labels are global, so adding new keys leaves the loaded 2020–2025 unchanged. Changing an existing key's target would affect any reload.

## Related Research

- `context/archive/2026-11-13-sheet-load-2020-2021/research.md`
- `context/archive/2026-11-12-legacy-budget-import/research.md`

## Open Questions

1. **The 2019 truth (blocking):**
   - (a) actual totals rebuilt from the rows: 67 286,75 zł without goals and savings, with income 77 914,29 plus a decision on `marzec_19!B7` 125,00;
   - (b) `Podsumowanie!H16/I16` with an explicit, per-month list of deviations;
   - (c) another rule.

   Each option replaces „Analiza jest prawdą” for this year and needs the ADR.
2. **Goals and savings:** „Cele”/„cele: Sebastian” (6 144,80 zł) and „Oszczędności (10%)” (3 988,17 zł). Each is either a fund movement, like 2020–2021 Fundusz Wydatków Celowych, or SKIP, like „oszczędności długoterminowe”. The `Oszczędności` sheet shows the goals going into named funds.
3. **Granularity:** one operation per row per month, or the personal, „Dom i akcesoria” and „Cele” lists split into items as 2020–2025 do with their lists. Splitting surfaces the two list/row differences in §2.
4. **Categories:** a target for each new label in §3. Siłownia and stypendium have no existing target: does production have a Sport category (id unknown), or should one be created in the app first? Needs the production list: `user:backup 1` or `app:control-numbers` on the server.
5. **Doubtful items:** whether „do oddania”/„oddać”/„Urszula” and income „inne” (e.g. 435,39 „od Palaków”) are spending and income or money passed through. The workbook does not say.
6. **Gap:** no reader has run on the file. All totals come from scratch scripts over cached values. The plan's first test should be the real 2019 reader on the real file against the chosen truth.
