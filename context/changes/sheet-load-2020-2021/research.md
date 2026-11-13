---
date: 2026-11-13T20:52:28+01:00
researcher: Claude (claude-opus-5-5)
git_commit: eb14456885a13fb54ce706df513ab321ea0fc5a0
branch: worktree-sheet-load-2020-2021
repository: household-treasurer
topic: "sheet-load-2020-2021: what must change for the Sheet Load to read Budżet_2020.xlsx and Budżet_2021.xlsx"
tags: [research, codebase, sheet-load, xlsx, budget, mapping, adr-0085, sheet-load-2020-2021]
status: complete
last_updated: 2026-11-13
last_updated_by: Claude (claude-opus-5-5)
---

# Research: loading 2020 and 2021 through the Sheet Load

**Date**: 2026-11-13T20:52:28+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: eb14456885a13fb54ce706df513ab321ea0fc5a0 (only this change folder on top; no code of this change exists)
**Branch**: worktree-sheet-load-2020-2021
**Repository**: household-treasurer

## Research Question

Can the Sheet Load (ADR-0085, `budget:load-sheet`) load `storage/app/private/Budżet_2020.xlsx` and `Budżet_2021.xlsx` (main checkout, gitignored), and what exactly has to change in the reader, the planner, `database/sheet-load/mapping.php`, tests and recorded decisions? 2019 is out of scope by the user's decision (a different workbook: `styczeń_19`…, `grudzień_18`, `Podsumowanie` instead of `Analiza`).

## Summary

- Both files hold complete, balanced data in the same shape as 2022–2025, so the work is a reader layout variant plus mapping, not data repair. 2020: rebuilt income 74 011,82 = `Analiza!B16`, expenses 69 557,28 = `Analiza!C16` (scratch reader for the 2020 layout). 2021: rebuilt income 96 348,99 = `Analiza!B16`; expenses 90 211,55 ≠ `Analiza!C16` 89 673,56 because of a formula bug in the 2021 workbook (below).
- The current code refuses both files at `BudgetSheetReader.php:25` ("Ustawienia: brak arkusza"); with the year bypassed it refuses each month at `MonthSheetReader.php:24` (income amount read from column I, which holds "kategoria" in 2020/2021). Observed by running the worktree classes on both files.
- One layout for both years ("2020/2021 layout"): income amount in **H** (2022: I); irregular and medical lists in **I/J/K/L** = kategoria/nazwa/plan/kwota (2022: J/K/L/M); medical block labels "Opieka zdrowotna" / "**Lekarze - dentysta**" / "Badania" in column I with **no "nazwa" header**; expense column B/D unchanged.
- Year: 2021 has the number 2021 in `styczeń!A1`; 2020 has no year number anywhere (only `maj!A1` = date serial 43952 = 2020-05-01, and the file name). Neither file has `Ustawienia`.
- 2020 only: no medical block in January–April (doctor visits sit in the irregular list with categories "Lekarz"/"Badania"), so the irregular list has no end label in those months; the daily breakdown ends at "Na tydzień", not "Do kiedy wydatki".
- 2021 workbook bug: from July `month!E30` is `SUM(D3:D20)+SUM(D24:D29)` (drops `D23` Rozrywka i wyjścia; June is `SUM(D23:D29)`), so `Analiza!C16` (sum of `E30`) is short by 537,99 zł (Jul 152,49, Aug 47,90, Sep 0, Oct 96,90, Nov 128,80, Dec 111,90). `Analiza!E16:K16` (category columns) sums to 90 211,55 = the planned total. "Analiza jest prawdą" (ADR-0085 decision 3) cannot hold to the grosz for 2021 expenses without an explicit decision.
- Unmapped labels (table in §4): 2020 — 24 (12 expense, 2 income, 1 daily, 9 irregular); 2021 — 13 (7 expense, 3 income, 1 daily, 2 irregular). The planner refuses on any unmapped label, including zero-amount rows (`SheetLoadPlanner.php:56,93,145,241`).
- Nothing in the domain blocks the years 2020/2021 (year validation 2000–2100, locks keyed by `accounts.sheet_year`, reports generic). ADR-0085 limits the scope to 2022–2025 only because those were the files supplied (`archive/.../plan.md:42`), so a new ADR (next free: 0092) amends decision 1 before code.

## Detailed Findings

### 1. Reader: what is hard-coded

- No branch on year or layout exists in `app/Support/SheetLoad`; blocks are found by labels (`MonthSheetReader.php:7-9` docblock) but every column letter is a literal (about 15 call sites):
  - Year from `Ustawienia!B2` (`BudgetSheetReader.php:25-29`); missing sheet → `brak arkusza` (`:63-71`). Totals from `Analiza!B16`/`C16` (`:32`, `:42-43`).
  - Expenses B/D from row 3 to B "wydatki nieregularne", balance next row (`MonthSheetReader.php:18-21`).
  - Income G/I down to G "oszczędności" (`:23-24`); fund withdrawal amount in I (`:93-104`).
  - Daily breakdown G/I under "codzienne"/"codzienne wydatki", ending at an empty G or "do kiedy wydatki" (`:107-128`); returns null when absent, which is how it is "2022 only" today.
  - Lists: J "kategoria", "opieka zdrowotna", "leczenie prywatne", "badania"; K "nazwa" header required (`:28-36`); exams header in K (`:135`); blank row = J–M empty (`:152-161`); items J category, K name, M amount (`:168-194`).
- `SheetCells::normalizeLabel()` strips trailing `*` and maps `work:` → `praca:` (`SheetCells.php:92-99`), so the 2020/2021 marks `*`, `**`, `***` need no code change.
- Parameterising: a column profile (expense label/amount, income label/amount, list category/name/plan/amount, medical labels, header optional) passed into `MonthSheetReader`, chosen by detection (e.g. what sits in `I3`, or presence of `Ustawienia`) or by a command option; plus a year source when `Ustawienia` is absent.

### 2. Budżet_2021.xlsx layout (all 12 months inspected)

- Sheets: 12 months + Analiza, Oszczędności, Media, Cele, Zaopatrzenie, Inne. Year: `styczeń!A1` = 2021; other months `A1 = DATE(styczeń!A1,m,1)`.
- Expenses B3..B28, D = actual; "Wydatki nieregularne" at B29 in all 12 months; "Pozostałe" B30 = 0 in all 12 months.
- Income G3 to G "oszczędności" (G10; G11 in czerwiec, wrzesień, grudzień), amounts in H. No "wydatki z funduszu" row in any month → fund withdrawal 0.
- Daily breakdown: header G22 (wrzesień G23), items in G/H, ends at "Do kiedy wydatki" as in 2022; items Jedzenie, Jedzenie poza domem (0), Słodycze i inne, Higiena i kosmetyki, Pieluchy, Środki czystości, Pomoce kuchenne; equals `D14` in all 12 months.
- Irregular list header I3/J3, items I4..I21 (marzec ..I19, listopad ..I27), K = L in every row, L sum = D29 in all 12 months.
- Medical: I22 "Opieka zdrowotna", I23 "Lekarze - dentysta", I27 "Badania" (shifted in marzec, wrzesień, październik, listopad); name J, amount L; `D17` "Lekarz - dentysta" and `D16` "Badania" equal their lists in all 12 months → no remainders. In październik the exams list runs into notes (I31/I32), producing a 0-amount item that is filtered out.
- Negative income: one cell, `grudzień!H8` "Międzyokresowe" −18,87 → moved as a transfer to `grudzień!H7` in the trial run.
- Scratch trial (patched copies of reader/planner + 13 mapping keys, not the repo): 517 operations, 0 sheet warnings, 0 remainders, 1 transfer; income = B16; expense check fails only on the C16 bug.

### 3. Budżet_2020.xlsx layout (all 12 months inspected)

- Sheets: 12 months + Media, Oszczędności, Cele, Analiza, Uwagi, Zaopatrzenie, Arkusz1, Arkusz2 (none of the extra sheets feeds the totals). No year number: `maj!A1` = 43952 (2020-05-01), `czerwiec..grudzień!A1 = DATE(YEAR(maj!A1),…)`, January–April have no A1.
- Expenses B/D; "Wydatki nieregularne" at B26 (Jan, Feb), B27 (Mar, Apr), B29 (May–Dec); "Pozostałe" next row = 0 in all 12 months.
- Income G/H to "oszczędności" (G8 Jan–Feb, G9 Mar–Dec). `lipiec!H9` "oszczędności" 214,20 is SKIP and outside Analiza. No fund withdrawal row.
- Daily breakdown: header G21 (Jan–Apr) / G22 (May–Dec), items G/H, **closed by "Na tydzień"** (`styczeń!H26` = 250): `MonthSheetReader.php:120` would read it as an item. Equals the Codzienne row in all 12 months.
- Irregular list I/J/K/L; dash and blank rows inside; total `D = SUM(L4:L21)` (May–Nov), `L4:L25` (Jan), `L4:L27` (Dec).
- Medical block only May–December: I22 "Opieka zdrowotna" (Dec I28), I23 "Lekarze - dentysta" (Dec I29), "Badania" I26–I28 (Dec I34); no "nazwa" header; expense rows B15 "Badania", B16 "Lekarz - dentysta" (May, Jun) / "Lekarz (dentysta)" (Jul–Dec).
- **January–April: no medical block and no medical expense rows**; doctor visits are irregular items with category "Lekarz"/"Badania". The irregular list must end at the first blank I–L row after content, because lower rows hold unrelated lists (`styczeń!K48` "Badania" `L48` 155,90, J39–J43 "Przelewy stałe" Jun–Nov). Running to `highestRow` would read them.
- No negative income cell; every block total equals its list in all 12 months → no remainders.
- Analiza: B3:B14 = month income without "oszczędności"; C3:C14 = month expenses without "Oszczędności długoterminowe", **including** Fundusz Wydatków Celowych, Nadpłata kredytu and Rezerwa na wydatki — so those three cannot be SKIP without breaking the total check (`SheetLoadPlanner.php:41`).

### 4. Unmapped labels (each one refuses today)

Existing precedents in `mapping.php` given as hints, not decisions.

| Block | Label | Year | Amounts (non-zero) | Nearest precedent |
| --- | --- | --- | --- | --- |
| expenses | hbo go | 2020 (Apr–Dec 24,90), 2021 (styczeń 24,90) | | `abonamenty` → 23 Rozrywka (`mapping.php:18`) |
| expenses | netflix | 2020 | 0 | same |
| expenses | ogrzewanie | 2020 | 0 | — |
| expenses | bilety i transport | 2020, 2021 | monthly 2,50–115,53 | `bilety - parking - inne` → 22 |
| expenses | lekarz - dentysta | 2020 (May–Jun), 2021 (all) | 2021: 600…3200; 2020: 400, 155,36 | `leczenie prywatne` → 20, with medical list |
| expenses | lekarz (dentysta) | 2020 (Jul–Dec) | 504,37 / 150 / 320 / 530 / 300 | same |
| expenses | rezerwa na wydatki | 2020, 2021 | 2020: 6,94 / 43,74 / 33,24; 2021: 195,06 / 140,74 / 50 | `fundusz wydatków nieregularnych` → 10 |
| expenses | święta | 2020, 2021 | 10–52,76 | — |
| expenses | prezenty | 2020, 2021 | 10–40 | irregular `prezenty` → 31 |
| expenses | fundusz wydatków celowych | 2020, 2021 | 2020: 20–375; 2021: 30–1120 | `fundusz wydatków nieregularnych` → 10 |
| expenses | fundusz wydatków celowych (oszcz.) | 2020 | `luty!D24` 205 | same |
| expenses | nadpłata kredytu | 2020 (Mar–Dec) | 100–846,90 | `rata kredytu` → 9 |
| income | przychody rozłożone | 2020 (8 months), 2021 (marzec 2800, kwiecień 2100) | | `przychody nieregularne` → 7 |
| income | fundusz wydatków nieregularnych | 2020 | 0 | — |
| income | pozostałe (premie, 13) | 2021 | 1894,01 / 572 / 540,19 / 2147,85 / 345 | `pozostałe` → 7 |
| income | międzyokresowe | 2021 | −18,87 | — |
| daily_breakdown | gospodarstwo domowe | 2020 | 8,49–77,05 | `środki czystości` → 8 |
| daily_breakdown | jedzenie poza domem | 2021 | 0 | expense `jedzenie poza domem` → 24 |
| irregular | lekarz | 2020 | 200 / 130 / 250 / 150 / 180 / 140 | medical → 20 |
| irregular | badania | 2020 | 42,75 / 155,80 / 42,75 | medical → 20 |
| irregular | gospodarstwo | 2020 | 41,94 / 18 | — |
| irregular | jedzenie | 2020 | 63 / 81 / 76,50 | — |
| irregular | darowizna / prezent / ubranie | 2020 | singular forms | `darowizny` 28 / `prezenty` 31 / `ubrania` 27 |
| irregular | biurowe, higiena | 2020 | 43,09; 69,95 | — |
| irregular | szkoła, kary | 2021 | 13; 100 | `edukacja` 30 / — |

Notes: the planner links the medical list to the expense row by the exact label `leczenie prywatne` (`SheetLoadPlanner.php:80`); without a change, "lekarz - dentysta" becomes one row operation per month (totals still match, item names lost). "Kosmetyczka i fryzjer" (2021, 490 zł) maps to the archived "Kosmetyki" (NEW_CATEGORY) whose Rhythm starts 2022-01 (`mapping.php:146-152`).

### 5. Domain constraints and side effects

- No minimum year blocks 2020/2021: `StoreBudgetRequest.php:22-27` and `IndexOperationsRequest.php:35` allow 2000–2100; `accounts.sheet_year` is a smallInteger unique with `user_id`; `RestoreUserData.php:189-210` accepts 1–9999.
- Loaded-year locks, liquidity exclusions and the opening balance are keyed by `sheet_year` per year (`OperationCategoryChoice.php:42`, `FinancialOperationPolicy.php:28,38`, `SpendableLiquidity.php:15`, `BudgetDeficitWarning.php:54`, `SheetLoadPlan.php:51-54`), so they apply to new years without change. `LoadBudgetSheet::assertYearFree` checks only the same year (`LoadBudgetSheet.php:87-96`), so loading older years after 2022–2025 has no cross-year dependency.
- Reading change by design: the year-before column (`ReportComparisonPeriod.php:31-56`) gives the 2022 report a 2021 column and 2021 a 2020 column.
- Kosmetyki: production has it (id 42, archived); `LoadBudgetSheet.php:102-108` reuses it. Changing `rhythm_start` from 2022-01 would break `UnloadBudgetSheet.php:92-100` (removes Kosmetyki only when its single entry equals `rhythm_start`). A month before the first Rhythm entry takes that entry's Rhythm (`CategoryRhythms.php:16-17`).
- `app:control-numbers --allow-additions` (`ControlNumberAdditions.php`) accepts added Budgets, lines, archived Sheet Accounts with balance 0 and archived Categories regardless of year.

### 6. Tests and fixtures

- `tests/Support/SheetWorkbookFactory.php` knows layouts `'2023'` (default) and `'2022'` (`:52`, `:95`), always writes `Ustawienia!B2` (`:56-58`) and `Analiza!B16/C16` (`:71-75`), with hard-coded positions (income G/I with H outside 2022 `:134-143`, lists J/K/L/M `:213-235`). A 2020/2021 layout needs a new layout value without `Ustawienia`, income in H, lists in I–L, medical labels without "nazwa", and (2020) months without a medical block.
- `BudgetSheetReaderTest.php:13-44,46,121,136`, `SheetLoadPlannerTest.php:15-31` (real `mapping.php`, overrides stripped), `LoadBudgetSheetTest.php:31-43,132-150,205,242,272` (pgsql branch `:40`), `LoadBudgetSheetCommandTest.php` already uses `year => 2021` with the 2023 layout (`:52,68,81,93,99`).
- The factory writes literal values; production-load-notes.md:25 records that this hid a formula bug — a fixture should include a formula cell. Feature tests must run on PostgreSQL (`LoadBudgetSheet.php:186-194` uses `UPDATE … FROM`).

## Code References

- `app/Support/SheetLoad/BudgetSheetReader.php:25-43,63-71` — year from Ustawienia!B2, Analiza totals
- `app/Support/SheetLoad/MonthSheetReader.php:18-36,93-128,135,152-194` — block search and hard-coded columns
- `app/Support/SheetLoad/SheetCells.php:75-99` — amount parsing, label normalisation
- `app/Support/SheetLoad/SheetLoadPlanner.php:40-41,56,80-97,145,235,241,351-364` — Analiza check, medical/daily list linking, unknown-label refusal
- `database/sheet-load/mapping.php:13-97,100-104,106-152` — labels, overrides (2023–2025 only), expected Category names, Kosmetyki
- `app/Actions/LoadBudgetSheet.php:87-128,186-194`, `app/Actions/UnloadBudgetSheet.php:92-100`
- `tests/Support/SheetWorkbookFactory.php:52-235`

## Architecture Insights

- The parser is label-driven by design ("the layout shifts between years", `MonthSheetReader.php:7-9`), so row shifts in 2020/2021 are not a problem; column positions and three labels (medical end label, "nazwa" header, daily terminator) are.
- The safety net is two totals to the grosz against `Analiza`; it caught a real bug in the 2021 workbook. Any relaxation must stay explicit per year (like `overrides`), not a tolerance.

## Historical Context (from prior changes)

- `context/foundation/adr/0085-sheet-load-of-past-years.md` — decision 1 scope "2022, 2023, 2024, 2025" and "a layout that is not one of the four read ones is refused"; decision 3 "Analiza jest prawdą"; decision 4 daily breakdown "in 2022". Supported as the current rule; needs amendment for 2020/2021.
- `context/archive/2026-11-12-legacy-budget-import/plan.md:42` — "Lata sprzed 2022 … nowy układ to nowa zmiana": supported; the only reason recorded is that only four files had been supplied and read (research.md:173,188-205; plan-brief.md:23,46). No domain reason excludes 2020/2021.
- `context/archive/2026-11-12-legacy-budget-import/production-load-notes.md:3,63-71` — 2022–2025 loaded in production on 2026-11-12 (Budgets 3–6, Sheet Accounts 12–15, 1 649 operations, Kosmetyki id 42); rollback compares against `control-numbers/20261112-sheet-before-2.json`, which will be stale after more years are added (a new "before" file is needed). Current production state was not re-checked.
- README "Wczytanie arkusza (lata 2022–2025)" (`README.md:220-252`, loop `:231`) and annotations in `prd-v3.md:199,256,287`, `roadmap.md:380` name 2022–2025.

## Related Research

- `context/archive/2026-11-12-legacy-budget-import/research.md`

## Open Questions

1. **2021 `Analiza!C16` bug (537,99 zł):** load against a corrected expected total (e.g. a per-year expected-total override recorded in `mapping.php` with the reason) or against `Analiza!E16:K16`; the user decides; ADR-0092 records it.
2. **Category for each new label** (table in §4), especially whether Fundusz Wydatków Celowych*, Rezerwa na wydatki*, Święta*, Prezenty* (2021 legend: transfers to "PNB oszczędnościowy") and Nadpłata kredytu are expenses in their own Category (ADR-0085 decision 3 precedent) — they must not be SKIP while Analiza counts them.
3. **Possible double counting** of "przychody rozłożone" (2021: marzec 2800 = `marzec!L23` ortodonta 2800; kwiecień 2100 vs 2450 does not match) — unproven; follows Analiza if mapped as income.
4. **Year source** for 2020 (no year number): command option, file name or `maj!A1`; for 2021 `styczeń!A1` works.
5. **Layout selection:** detection (e.g. no `Ustawienia` + "kategoria" in `I3`) vs an explicit option.
6. **Gap:** the real planner has not run end-to-end on 2020; the 2021 run used patched scratch copies (job tmp, not kept). The plan's first test should be the real reader on both files.
