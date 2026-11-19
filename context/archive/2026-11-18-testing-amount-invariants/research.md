---
date: 2026-11-18T06:48:56+01:00
researcher: Claude (Sonnet 5.5), four read-only workers plus parent verification
git_commit: 0b4a29350c98fda155cbcca4ecfd91af68116176
branch: worktree-testing-amount-invariants
repository: household-treasurer
topic: "Rollout Phase 2 „Niezmienniki kwot” (test-plan §2 risks #2 and #3): PHP↔TS parity of „do wydania” and report = annual Execution = Month Result"
tags: [research, test-plan, to-spend, period-report, month-result, parity, oracle]
status: complete
last_updated: 2026-11-18
last_updated_by: Claude (Sonnet 5.5)
---

# Research: Niezmienniki kwot (test-plan §2 risks #2 and #3)

**Date**: 2026-11-18T06:48:56+01:00
**Git Commit**: 0b4a29350c98fda155cbcca4ecfd91af68116176
**Branch**: worktree-testing-amount-invariants
**Repository**: household-treasurer

Nothing was run: no Pest, no Vitest, no build. Every claim below is from reading code and docs, labelled **observed** (read in source) or **inferred** (reasoned, not reproduced). Parent re-read the decisive anchors (calculators, `MonthPlanProposal::lastPaidAmount`, `IrregularCollected` actual query, `BudgetController` archived filter, `BudgetMonthResult`, old roadmap lines, CI workflow); the rest comes from worker reports and is marked by its anchors.

## Research Question

Ground rollout Phase 2 of `context/foundation/test-plan.md`. For risk #2 („do wydania” in the browser preview (TS) differs from the server number (PHP)) and risk #3 (period-report totals differ to the grosz from annual Execution or Month Result): verify the Risk Response Guidance, find the real failure paths and existing tests, pick the cheapest useful layer, flag speculative or misleading evidence, and settle what replaced S-11/S-14 as the reference point.

## Summary

1. **Reference-point correction confirmed.** S-11 (`period-report-by-category`) and S-14 (`irregular-expense-in-month-plan`) belonged to the abandoned M-2 and were never `done`. Their delivered successors are on `origin/main`: the irregular „do wydania” rule is S-26 (ADR-0066 as amended by ADR-0073, with F-05 and S-24 before it); the period report is S-28 (ADR-0070) plus S-29, S-30, S-32 and M-4 S-34/S-35/S-36 (ADR-0074, 0077, 0081, 0097, 0101). Phase 2 is therefore no longer „before S-14 / after S-11”: both rules have already landed and the phase is a regression net around them.
2. **Risk #2: the arithmetic is identical; the risk lives elsewhere.** `ToSpendCalculator` and `CollectedCalculator` match their TS copies branch for branch and match the glossary rule (no departure found). The two test suites share no fixture and no case table, and several glossary cases are untested on one or both sides. The more plausible divergences are in the **inputs** the TS copy receives (`EntryToSpendInputs`) and in what only the server recomputes, not in the formula. The guidance „do not assume the TS copy computes the same” is right as a stance, but for the two calculator functions the answer is „it does”; the unproven part is „the same data in → the same number out across the DB → inputs → TS chain”.
3. **Risk #3: „both numbers come from the same reading” is only partly true.** Three independent computations exist (stored counter `budget_lines.execution_minor_units`; live aggregation in `MonthCategoryActuals`/`YearsLineActuals`; `BudgetMonthResult`), but Trend, Panel, `MonthToSpend` and the report share one query, so a report-vs-Trend equality cannot catch a bug in it. Existing tests mostly use another reader as the oracle. No single scenario compares report, annual Execution and Month Result against one hand-computed literal for refund, own transfer, capital movement, archived line and the month and year boundaries together.
4. **One observed behavioural difference to decide before writing the invariant:** the annual Budget page leaves archived lines out of its totals unless `?show_archived`, while the report and Month Result include them.

## Detailed Findings

### Risk #2 — To Spend PHP vs TS

**The two calculators are the same function (observed).**
- PHP `ToSpendCalculator::itemMinorUnits`: income or Fixed Charge → `hasPositiveAmount ? actual : plan`; Irregular → `plan + surplus(collectedAtStart, plan, actual)`; anything else → `max(plan, actual)` (`app/Support/ToSpendCalculator.php:84-92`). `breakdown` adds income and expense and returns `income - expense - setAside` (`:48-58`).
- TS `toSpendBreakdown` branches in the same order (`resources/js/lib/to-spend-calculator.ts:34-62`) and returns the same difference (`:68-69`).
- `CollectedCalculator` (`collectedAtEnd = max(0, opening + rate - actual)`, `surplus = max(0, actual - (opening + rate))`, chain with floor at 0 every month) and `collected-calculator.ts` are structurally identical (worker read: PHP `:20-55`, TS `:16-64`).
- Neither file uses division, rounding or floats; integer overflow differs only above 2^53−1 minor units in TS (worker read; not reachable for household PLN amounts).
- Against the glossary rule (To Spend, Irregular Rate, Collected): no departure found for either copy (worker read, parent spot-check of both calculators).
- A null rhythm on an expense falls through to `max(plan, actual)` in both copies (PHP `:92`, TS `:62`); `entry-to-spend-warning.ts` produces it with `category.rhythms?.[month] ?? null` (worker read, `:172`).

**The existing tests do not tie the two sides together (observed).**
- PHP: `tests/Unit/ToSpendCalculatorTest.php` (canonical example 373 000, income received, refund-only bill, negative result, Set-aside and Draw, an 11-row `itemMinorUnits` dataset, four Irregular cases) and `tests/Unit/CollectedCalculatorTest.php` (6 cases). TS: `to-spend-calculator.test.ts` and `collected-calculator.test.ts`, hand-ported with the same literals. All expected values are hand-written integers; none is computed by the code under test.
- Shared fixture between PHP and TS: none. The word „parity” appears only in two unrelated comments (`tests/Feature/UserSnapshotCommandTest.php:1281`, `resources/js/lib/import-start.test.ts:27`). The only coupling is the rule text in `.ai/rules/month-calculators.md` („change both copies and both tests together”).
- Glossary cases with **no test on either side**: Fixed Charge whose first positive operation is later netted negative by a refund (both copies return the net negative `actual`, which raises To Spend; pinned nowhere); Fixed Charge with plan 0; Irregular with a plan different from the chain rate (every TS warning test sets `rate_minor_units: 0`); Even Annual with a refund. Cases present on one side only: the PHP `itemMinorUnits` dataset has no TS counterpart (TS has no such function), and TS lacks the Running `0/-5000` row and single-case Fixed Charge rows.
- `entry-to-spend-warning.test.ts` (31 tests) and `import-to-spend-warnings.test.ts` (9 tests) run on hand-built inputs (`inputsWith`, `irregularInputs`, repeated in each file) with literal expectations; none uses inputs produced by the server.

**Where the browser number can differ from the server number (observed sources, inferred effects).**
- Surfaces using TS: expense and income hand-entry dialogs and the Import preview (`entryToSpend` prop from `EntryToSpendInputs::forUser`, `OperationsController.php:201-202`, `CsvImportController.php:145`), and the Month Plan page only while a proposal is edited or an approved plan changed (`MonthPlan.vue:296-318`). Surfaces using PHP: Month Plan outside edit mode, Dashboard `month_to_spend`, notice `month_to_spend_negative`, Month Pace, Month Summary, Collected Coverage (`MonthToSpend.php:172-218`, `DashboardController.php:109-122`, `DashboardNotices.php:111-129`). Own Transfer and Capital Movement dialogs pass no readings (`OwnTransferFormDialog.vue:144`, `CapitalMovementFormDialog.vue:173`).
- No test builds one scenario in the database, reads the server number, and runs the TS function on the server-built inputs (workers searched `tests/` and `resources/js`; found none). `EntryToSpendInputsTest.php` (390 lines) checks the inputs shape by literals; `MonthToSpendTest.php` checks the PHP number (including an Irregular case, `:136-143`); the e2e spec `tests/e2e/irregular-collected.spec.ts` checks the Month Plan page (PHP), not the dialog warning.
- Ranked candidates, none reproduced:
  1. **Fixed Charge proposal after a backdated operation (inferred effect).** `MonthPlanProposal::lastPaidAmount` takes the previous month's net actual (when it had a positive operation and net > 0) as the next proposal month's plan (`MonthPlanProposal.php:236-242`, `:266-285`; parent read). TS re-reads later months only for Irregular periods (worker: `entry-to-spend-warning.ts:391-404`), so a preview for an operation in month m−1 does not move the proposal plan of month m.
  2. **January proposal with a category that has no line yet (inferred).** TS assumes plan 0 for a joining category (worker: `entry-to-spend-warning.ts:220`, `CategoryChoiceOptions.php:100-121`); after the save, the January proposal may take its amount from `matchPreviousCategory` (`MonthPlanProposal.php:248-258`).
  3. **Irregular: two sources of „actual” (observed sources, divergence inferred).** The month item actual comes from `MonthCategoryActuals` (filtered by `budget_id` and date range); the Collected chain actual comes from a separate query by `budget_line_id` from the earliest Irregular start, with no `budget_id` filter (`IrregularCollected.php:285-298`, parent read). They differ only for an operation whose `budget_id` and date year do not match its line's Budget; no write path found that produces one (`ManageFinancialOperation::lockLine` derives both from the date), but the restore path and the sheet loader set fields directly (worker: `RestoreUserData.php:885`, `LoadBudgetSheet.php:185`).
  4. **Irregular rate source (observed).** For a month without an approved plan the chain rate is `AnnualPlanShare` of today's planned amount (`IrregularCollected.php:166-172`, worker), frozen into the inputs at page load; To Spend uses the item `plan` where the chain uses `rate`. Both copies do this identically, so it is a consistency risk inside each copy, not a PHP/TS difference.
  5. **Stale page across a month boundary (inferred):** inputs are cut at the server's UTC today at load (`EntryToSpendInputs.php:314-330`, worker); a page left open across midnight UTC has no reading for the new month until reload.
  6. **Edit with zero or incomplete amount** returns no changes, so no `remove` of the original is previewed (worker: `entry-to-spend-warning.ts:517-523`); whether the form allows it was not checked.
- Months included in the inputs: only Budgets in Execution, months 1 to today's month, and only months whose Month Plan state is not `none` (worker: `EntryToSpendInputs.php:314-330`). An entry dated in a future month or in a past month without a plan gets no To Spend reading in TS; the glossary says the warning checks „the month of the operation date and the later months with a plan on which the entry acts through Collected”.

**Verdict on the response guidance for #2.** Keep: one hand-computed case table, same cases on both sides, no values taken from PHP output. Correct: (a) the „TS is a 1:1 copy” challenge resolves to „yes for `toSpendBreakdown` and `CollectedCalculator`”, so a table of calculator cases proves the copies agree today and guards drift, but does not by itself protect the input path; (b) the guidance should add the **inputs link**: the same hand-computed scenario seeded in the database, asserted in PHP through `MonthToSpend` (number) and `EntryToSpendInputs::forUser` (inputs), and in TS through the warning/preview functions run on those inputs, all against one literal.

**Cheapest layers (recommendation, feasibility partly unverified).**
- Calculator parity: one JSON case table in `tests/Fixtures/` read by a Pest unit test and a Vitest test. Pest already reads fixture JSON with `json_decode(File::get(base_path(...)))` (`tests/Feature/ImportSuggestionAccuracyBenchmarkTest.php:27,94`); Pest `dataset()->with()` exists in `ToSpendCalculatorTest.php:65`; Vitest already reads files outside `resources/js` with `readFileSync` and `fileURLToPath` (`resources/js/ui-contract.test.ts:1-7,78`); `tsconfig.json` has `resolveJsonModule` (worker). `tests/Pest.php` binds `TestCase` only to `Feature`, so a Unit test must use `__DIR__`, not `base_path()` (worker). Not verified: that the typed lint (`vite.config.ts:73-76`) accepts the chosen read.
- Inputs link: a Feature test on PostgreSQL plus a Vitest test sharing a fixture that holds the scenario, the hand-written `EntryToSpendInputs` it must produce, and the expected readings.

### Risk #3 — report vs annual Execution vs Month Result

**Readers and what they share (observed unless noted).**

| Reader | Source | Shares a query with |
| --- | --- | --- |
| Stored counter `budget_lines.execution_minor_units` (annual Budget page, Dashboard, Month Plan rows) | incremental `increment/decrement` by signed amount for both types in `ManageFinancialOperation.php:255-289` (worker); no recomputation from operations after the fact | nothing |
| `MonthCategoryActuals::forMonths` / `forRange` (`PeriodReport`, `ReportTrend`, `ReportCategoryPanel`, `MonthToSpend`, `MonthPlanProposal`, `EntryToSpendInputs`, window rows) | `financial_operations`, `budget_id`, half-open date range, `SUM` by `budget_line_id`, no type or archived filter (worker: `MonthCategoryActuals.php:27-86`) | all of the left column |
| `YearsLineActuals` (Years) | own `SUM(CASE …)` grouped by `budget_line_id` (worker: `YearsLineActuals.php:24-86`) | nothing |
| `BudgetMonthResult::forViewedMonth` (Month Result, `MonthSummary`) | `where budget_id`, `whereYear`, `whereMonth`, `selectRaw('type, sum(amount)')`, `groupBy type` (parent read, `BudgetMonthResult.php:21-36`) | `MonthSummary` |
| `BudgetMonthExecutionIndex` (hand-entry and Import Month Remaining) | own Eloquent query, expense only (worker) | nothing |

- Consequence for the oracle problem: a test of `PeriodReport` against `ReportTrend`, `ReportCategoryPanel` or `MonthToSpend` cannot detect a bug in `MonthCategoryActuals`. `PeriodReport` against `BudgetMonthResult`, against the stored counter, or against `ReportYears` are independent pairs. `MonthSummary` against Month Result is a tautology (same reader). So „both numbers come from the same reading” is false for the pairs the invariant needs, but the existing equality tests are still readers-versus-readers: a bug common to all of them (for example a wrong year clamp) passes.
- Two classification keys: `BudgetMonthResult` splits by operation `type`; report, Trend, Years and Panel split by the Category type of the line (worker: `ReportCategoryTree`, `PeriodReport.php:125`). They agree because validation keeps the two aligned (`ExpenseValidationRules.php:39-45`, `OperationRebuildValidationRules.php:129,167`, `CategoryTypeChange.php`), not by a database constraint; `ManageFinancialOperation::create` does not check it, and the sheet loader and restore set `type` directly (worker).

**Treatment of each input (observed, worker reads with parent spot-checks).**
- *Expense refund:* an expense with a negative amount (`ExpenseValidationRules.php:22`; income requires `min:1`, `IncomeValidationRules.php:22,59`). The readers in the table above sum without a sign filter, and the counter increments by the signed amount. No reader difference found. Only the `positive_count` flag ignores refunds, and it feeds To Spend, not totals.
- *Own Transfer, Capital Movement:* separate tables with no `budget_id` or `budget_line_id`; no reader in `app/Support` that the workers read selects from them for Execution. They are excluded by construction, so the test is a guard against a future change, not a known failing path. `BudgetMonthResult`, the stored counter and `MonthSummary` are not covered by any own-transfer or capital-movement test; Report, Trend, Years and `MonthCategoryActuals` are (`PeriodReportTest.php:151-189`, `ReportTrendTest.php:249-279`, `ReportYearsTest.php:447-476`, `MonthCategoryActualsTest.php:146-163`).
- *Archived Category or line:* report, Trend, Years, Panel, window and Month Result include archived lines (`PeriodReport.php:145` loads all lines; `BudgetMonthResult` has no archive filter). **The annual Budget page filters archived lines out before totals unless `?show_archived`** (`BudgetController.php:62-71`, parent read; `BudgetPlanSummary` also skips archived in `groupsOfType`, worker `:145`). The glossary (Category entry) says Budget „omits them from plan totals unless the User asks”; it says nothing explicit about Execution totals.
- *Month boundary:* `operation_date` is a `date` column cast to `date` (worker: `create_financial_operations_table.php:21`, `FinancialOperation.php:60`). `MonthCategoryActuals` uses `>= first` and `< first_of_next`; `BudgetMonthResult` uses `whereYear`/`whereMonth` (PostgreSQL `EXTRACT`, SQLite `strftime`); both include the whole last day. No disagreement found on 31st/1st dates.
- *Year boundary:* BR-13 is enforced at write time (`ManageFinancialOperation::lockLine`, worker `:222-231`); readers additionally clamp by the Budget year, so an operation with a mismatched `budget_id` is counted by the stored counter but by no report reader (`ReportYearsTest.php:198-214` pins this for Years vs report).
- *Midnight / timezone:* app timezone UTC, `today('UTC')` in controllers; operations are plain dates, no reader converts. No test for a midnight edge (inferred: `before_or_equal:today` is evaluated in UTC).
- *PostgreSQL-specific SQL:* `whereYear`/`whereMonth` compile to different SQL per driver; `date_trunc`, `to_char` and JSON are not used in these readers (worker). `phpunit.xml` defaults to SQLite; CI runs `composer ci:check` against PostgreSQL 18 (`.github/workflows/tests.yml:23,37,102`, parent read).

**Existing tests, and the oracle each uses (observed).**
- `PeriodReportTest.php:96-117`: month report vs `BudgetMonthResult` for 12 months with operations on the 1st and last day; oracle = another reader; no refund, archived or own-transfer data (parent read).
- `PeriodReportTest.php:119-149`: year report vs `$line->refresh()->execution_minor_units` and vs the sum of 12 `BudgetMonthResult`s; includes a refund `-4_000` and an archived line; dates inside one year; oracle = readers (parent read).
- `PeriodReportTest.php:151-189`: refund netting, own transfer 50 000 and capital movement 60 000, neighbour year and stranger excluded; **literal** `7_500`/`-7_500`, but only the report is read.
- `BudgetMonthResultTest.php:19-190`: literals for archived (10 000) and refund (8 000/12 000), Month Result only. `ExpenseTest.php` has counter literals for refunds. In the inspected tests they are not combined in one scenario.
- `ReportYearsTest.php:348` is the one hand-computed oracle over seven years (Years statistics); `context/archive/2026-11-14-report-categories-across-years/plan.md:131,309` already cites test-plan risk #3 as „nie tylko raport == inny odczyt”.
- No inspected test covers: a sheet-loaded year against report or Month Result (sheet operations are dated on the last day of each month, including 31 December, `SheetLoadPlanner.php:470-473`; `LoadBudgetSheetTest.php:190` checks counter = sum of operations only); the annual Budget page total against the report total; a midnight edge.
- Lesson already recorded: equality tests must book operations through `ManageFinancialOperation` (or the write routes), not factories, because the counter is maintained by writers (`context/archive/2026-11-10-period-report-by-group/plan.md:40`). `PeriodReportTest.php:96-149` uses `bookReportOperation` for that.

**Verdict on the response guidance for #3.** Keep: one scenario, hand-computed literals, report = annual Execution = Month Result. Correct: (a) the three values must each be asserted against the same hand-computed literal, not against each other; (b) „the same reading” is false for the stored counter and `BudgetMonthResult`, so those two are the useful independent witnesses, while Trend/Panel add nothing against a `MonthCategoryActuals` bug; (c) the scenario must include a month boundary pair and a year boundary pair in the same dataset, which no current test does; (d) „annual Execution” needs a definition for archived lines (see Open Questions).

### Reference point: S-11 / S-14

- Old roadmap v2 (`git show 58bcb11:context/foundation/roadmap.md`): S-11 `period-report-by-category` (Risk, `:105`: „Sumy muszą być co do grosza równe wykonaniu w budżecie rocznym i Wynikowi miesiąca…”); S-12 `category-trend-by-month`; S-13 `report-to-operations`; S-14 `irregular-expense-in-month-plan` (Risk, `:141`: „…dwie kopie kalkulatorów (PHP i TS), podglądy wpisu i Importu oraz snapshot w wersji 6…”); S-15 `irregular-collected-year-end`; S-16 `category-identity-across-years`; S-17 `year-over-year-comparison`; F-02 `irregular-expense-domain-decisions`.
- Abandonment: `context/foundation/roadmap.md:20` and Milestone History (M-2 abandoned 2026-11-08, replaced by PRD v3; none of F-02, S-11…S-17 `done`); the S-11 change was deleted in f7da16d (2026-11-07). The test plan was written 2026-11-06 (85f689d) against that roadmap and was not refreshed.
- Successors (all archived under `context/archive/`): F-02/S-14/S-15 → F-05 (ADR-0066), S-24 (ADR-0072), **S-26** `irregular-expense-collected` (ADR-0073), S-27 (ADR-0076; no TS copy of the carry-over, plan `:201`); S-11 → **S-28** `period-report-by-group` (ADR-0070, ADR-0071); S-12 → S-30 (ADR-0077); S-13 → S-31; S-17 → S-29 (ADR-0074); plus S-32 (ADR-0081), S-34/S-35/S-36 (ADR-0097, ADR-0101).
- On `main`: `origin/main` is at f25e104; `ToSpendCalculator.php`, `CollectedCalculator.php`, `PeriodReport.php`, `ReportYears.php` exist on both `origin/main` and this branch (worker `git ls-tree`). Local `main` is 42 behind `origin/main`; this branch is 20 ahead of and 5 behind `origin/main` (the 5 are infra and dependabot); it is 2 ahead of local `development`, which is 3 behind and 18 ahead of `origin/development` (worker `git rev-list --left-right --count`; parent re-ran `origin/main...HEAD` = `5 20`).
- What replaces the two phrases: „przed S-14” → „na regule S-26 (ADR-0066 zmieniony ADR-0073; hasła `To Spend` i `Collected` w glosariuszu), z `collected_at_start` w obu kalkulatorach; następna zmiana reguły zaczyna się od wpisu w glosariuszu i nowego ADR (AGENTS.md)”. „po S-11” → „na istniejącym raporcie S-28 i jego następcach (ADR-0070, 0074, 0081, 0097), z precedensem ręcznej wyroczni w `ReportYearsTest.php:348`”. The invariant source is `prd-v3.md:91-101` Guardrails (worker: „suma kategorii raportu za rok = wykonanie Budżetu, wynik miesiąca raportu = Wynik miesiąca; suma grupy = suma jej kategorii”).

## Code References

- `app/Support/ToSpendCalculator.php:84-92` — per-Rhythm item amount; `:48-58` breakdown.
- `resources/js/lib/to-spend-calculator.ts:34-69` — TS copy of the same.
- `app/Support/CollectedCalculator.php`, `resources/js/lib/collected-calculator.ts` — Collected end, surplus, chain.
- `app/Support/EntryToSpendInputs.php:273-387` — server-built inputs for the TS warnings (worker anchors).
- `resources/js/lib/entry-to-spend-warning.ts`, `import-to-spend-warnings.ts` — TS consumers.
- `app/Support/MonthPlanProposal.php:229-285` — Fixed Charge `lastPaidAmount`.
- `app/Support/IrregularCollected.php:143-190,285-298` — rate and actual sources of the chain.
- `app/Support/MonthCategoryActuals.php:27-86` — shared aggregation behind report, Trend, Panel, To Spend.
- `app/Support/BudgetMonthResult.php:21-36` — Month Result, grouped by operation `type`.
- `app/Http/Controllers/BudgetController.php:62-71` — archived lines left out of annual totals by default.
- `tests/Unit/ToSpendCalculatorTest.php`, `tests/Unit/CollectedCalculatorTest.php`, `resources/js/lib/to-spend-calculator.test.ts`, `collected-calculator.test.ts` — per-side hand literals, no shared table.
- `tests/Feature/PeriodReportTest.php:96-189`, `BudgetMonthResultTest.php`, `ReportYearsTest.php:198-214,348` — existing equality and oracle tests.
- `.ai/rules/month-calculators.md`, `.ai/rules/reports.md` (section „Report amounts equal the existing readings to the grosz”) — current rules; both accurate for this phase.

## Architecture Insights

- The TS To Spend copy exists only for previews that must react before submit (`.ai/rules/month-calculators.md`); new month readings are computed on the server and passed as props. Phase 2 does not change that.
- The stored counter is the only annual Execution that never reads operations; in the writers the workers read (`ManageFinancialOperation`, `ChangeSheetOperationCategory`, `LoadBudgetSheet`, `MergeCategories`, `RestoreUserData`) the counter is kept in step by the writer, and only `LoadBudgetSheet` recomputes it from operations (`assertSums`, at load time). Any invariant that includes it is also a guard on those writers.
- Phase 1 conventions to reuse: hand-counted oracles written into the test, fixtures edited by hand not generated by the code under test, fixed `now()`, keyed lookups because PostgreSQL sequences do not roll back, PostgreSQL run with the worktree command in `AGENTS.md`, Pint and Sloppy through the `sail-8.5/app` image (host PHP is 8.3) (`context/archive/2026-11-17-testing-import-integrity/plan.md:83-90`, `reviews/impl-review.md:24`).
- PHP `round()` and JS `Math.round` differ for negative halves (`context/archive/2026-11-03-month-spending-pace/plan.md:58`); this is Month Pace, not To Spend, and the To Spend functions use no rounding.

## Historical Context (from prior changes)

- `context/archive/2026-11-17-testing-import-integrity/research.md:242`, `plan.md:74` — phase 1 left stale S-14/S-16/„snapshot v6” references in risks #5, #6 and phase 3 for `--refresh`; `plan.md` ended with a test-plan backport phase.
- `context/archive/2026-11-11-irregular-expense-collected/plan.md:71` — `CollectedCalculator` is a „para PHP/TS”; the plan is the true successor of old S-14.
- `context/archive/2026-11-10-period-report-by-group/plan.md:20,40`, `plan-brief.md:35` — carried the old S-11 plan; equality tests must write through `ManageFinancialOperation`.
- `context/archive/2026-11-02-plan-based-entry-warnings/research.md:178` — „Parzystość kalkulatora `MonthRemainingCalculator` PHP/TS nie była sprawdzana linia po linii.” (still true for the pairs other than To Spend and Collected, outside this phase).

## Related Research

- `context/archive/2026-11-17-testing-import-integrity/research.md` — phase 1 research, same shape and backport section.

## Backport to test-plan.md (for the plan; not applied here)

Edits to `context/foundation/test-plan.md`, §2/§3 wording and Source cells only, no file anchors added. Lines 51, 52, 54 and 79 (#5, #6, #8, phase 3) stay for `--refresh`, as phase 1 decided.

- Line 48 (#2 Source): replace „roadmap S-14 Risk („dwie kopie kalkulatorów (PHP i TS)”)” with the rule in `.ai/rules/month-calculators.md` and S-26 (ADR-0066, ADR-0073); risk wording „zwłaszcza po zmianie reguły dla wydatku nieregularnego” → „zwłaszcza przy zmianie reguły dla wydatku nieregularnego (reguła S-26 już obowiązuje)”.
- Line 49 (#3 Source): replace „`prd-v2.md` Guardrails i „najszybszy sygnał awarii”, US-01, US-02; roadmap S-11 (status planning), S-12” with `prd-v3.md` Guardrails (sumy raportu = wykonanie Budżetu i Wynik miesiąca), ADR-0070, S-28, S-34.
- Line 61 (guidance #2, „Context”): „jak S-14 ją zmieni” → „gdzie reguła S-26 jest zapisana w obu kopiach, skąd TS bierze wejścia (`EntryToSpendInputs`)”; add to „Must challenge” the inputs path.
- Line 62 (guidance #3, „Context”): „co testy S-11 już pokrywają” → „co testy `PeriodReportTest`, `ReportYearsTest` i `BudgetMonthResultTest` już pokrywają; które odczyty dzielą `MonthCategoryActuals`”.
- Line 78 (§3 phase 2 goal): „przed S-14 … po S-11” → „na regule S-26 i istniejącym raporcie S-28 (oba już na `main`)”.
- Line 83: „Etap 2 musi wylądować przed zmianą reguły w S-14; część #3 czeka na S-11” → both parts can run now; keep „Etap 3 stoi przed v6 snapshotu” for `--refresh`.
- §6.2 placeholders at lines 135 and 152 are filled by this phase.

## Open Questions

1. **Archived lines in „annual Execution”.** The annual Budget page omits archived lines from Execution totals by default; report and Month Result include them (`BudgetController.php:62-71`). The invariant should compare the per-line stored counter and the sum over **all** lines; whether the page total should also include archived lines is a product question (glossary silent on Execution totals). If the answer changes a glossary rule it starts with the glossary entry and an ADR (AGENTS.md). For `/10x-plan`: decide, or state that the page total is out of scope.
2. **Where the shared fixture lives and how Vitest reads it** (`tests/Fixtures/…json` via `readFileSync`, or an import under `resolveJsonModule`); typed lint acceptance not verified.
3. **Scope of the inputs-link test** (DB scenario → `EntryToSpendInputs` → TS warnings): which scenarios (Fixed Charge refund-netted, plan 0, Irregular with plan ≠ rate, joining category) and whether candidates 1–2 above are tests that go red first (then a fix or an issue, as phase 1 did with #271/#272) or documented behaviour.
4. **Sheet-loaded year** in the report/Month Result/counter scenario: include or leave out (no test today; boundary dates fall on 31 December).
5. **PostgreSQL database** `testing_testing_amount_invariants` must be created per `context/foundation/agents/parallel-changes.md`; the pgsql container was not running when a worker checked it.
6. **Whether CI runs the whole Feature suite on PostgreSQL** (workflow sets `DB_CONNECTION: pgsql` and runs `composer ci:check`; not confirmed that no test is skipped on that driver).
