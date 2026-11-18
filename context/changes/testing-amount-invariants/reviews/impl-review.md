<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Niezmienniki kwot — plan implementacji

- **Plan**: context/changes/testing-amount-invariants/plan.md
- **Scope**: Full plan (all Automated rows done; every phase's Manual row still pending)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-18
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Evidence

- Diff `0b4a293..HEAD`: 16 files. Every one is in the plan, and no production code changed, which fits the issue path for candidate 2.
- Pest on PostgreSQL (parity tests, report-invariant tests, PeriodReport, BudgetMonthResult, LoadBudgetSheet, EntryToSpendInputs): 108 passed, 707 assertions.
- Vitest (parity tests and warning tests): 88 passed, 1 skipped (the #273 case).
- Full gate `composer ci:check` on PostgreSQL in `sail-8.5/app`: exit 0. Vitest 779 passed, 1 skipped. Pest 1880 passed, 4 skipped.
- Pint passed. `vendor/bin/sloppy ci` passed (3 medium findings in `SheetMappingCategoryCheck.php`, outside this change). `npm run types:check` and `npm run check` passed.
- `gh issue view 273`: OPEN, label `needs-triage`.
- `grep S-14|S-11` in test-plan.md finds only rows #5, #6, #8 and stage 3, which the plan allows. `.ai/rules/index.md` lists the 4 new paths in the `month-calculators.md` row.
- Manual rows 1.5, 2.7, 3.5, 4.6 and 5.5 are `[ ]`. They are pending, none is falsely ticked.
- A documented deviation that is not a finding: the phase-4 break `<` → `<=` in `MonthCategoryActuals` was equivalent, so it did not turn any test red. The implementer used `->subDay()->month` instead and recorded it in `change.md` and §6.2. The plan's predicted break was wrong; the implementer handled it correctly.

## Findings

### F1 — Expected values written twice in the scenarios fixture, never cross-checked

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/entry-to-spend-warning.parity.test.ts:66; tests/Feature/EntryToSpendParityTest.php:268,307
- **Detail**: PHP compares `server_months[].before/after`, and TS compares `readings[].beforeMinorUnits/afterMinorUnits`. These are two hand-written copies of the same numbers. Today all 8 overlapping (year, month) pairs agree. If someone edits one copy and not the other, both suites stay green while the server and the preview disagree, which is exactly the gap that risk #2 is meant to catch.
- **Fix**: Add a fixture test (TS) that every `readings` entry has a `server_months` entry with the same year/month and equal before/after. This compares two literals, not two computed readings.
- **Decision**: FIXED — a Vitest test checks that every `readings` entry matches a `server_months` month; a deliberate drift (542000 → 542100) turns it red, restoring it turns it green

### F2 — reports.md rule does not load for the test it governs

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: .ai/rules/reports.md:1-12 (frontmatter `paths`), .ai/rules/index.md
- **Detail**: The new sentence in `reports.md:26` sets the contract for `tests/Feature/ReportAmountInvariantTest.php` (one hand-counted literal per reading; a new amount adds a case). That path is not in the rule's `paths:` or in its index row, so editing the test does not load the rule. `month-calculators.md` did add its tests and fixtures, so the two rules are inconsistent.
- **Fix**: Add `tests/Feature/ReportAmountInvariantTest.php` to the `paths:` of `reports.md` and run `php artisan boost:index-rules`.
- **Decision**: FIXED — `tests/Feature/ReportAmountInvariantTest.php` added to `paths:` of `reports.md`, index regenerated with `boost:index-rules`

### F3 — Result 12 750 asserted on only one of the year's witnesses

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/ReportAmountInvariantTest.php:197,219
- **Detail**: The plan says every witness of year 2026 gives "wydatki 3 250, przychody 16 000, wynik 12 750". The result is asserted only on `PeriodReport::forYear`. `reportInvariantYearOfMonthResults` sums only income and expense, and the sum of counters has no result. The monthly results are checked per month, so the risk is small.
- **Fix**: Add `'result' => 1_275_000` to `reportInvariantYearOfMonthResults` (sum of `month_result_minor_units`).
- **Decision**: FIXED — `reportInvariantYearOfMonthResults` also sums `month_result_minor_units`; literals 1 275 000 (2026) and −640 988 (sheet year) counted by hand

### F4 — Extra `dialog` field in every scenario, not in the plan's contract

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: tests/Fixtures/amount-invariants/entry-to-spend-scenarios.json; resources/js/lib/entry-to-spend-warning.parity.test.ts:72
- **Detail**: The plan's fixture contract has no `dialog` field, and the plan called for `handEntryToSpendChanges` only in the edit scenario. Every scenario has the field, and the test checks `handEntryToSpendChanges(dialog) == changes` for all of them. This makes the test stronger and costs nothing, but the contract in the plan (and in §6.1) does not match the file.
- **Fix**: Add a short note to the phase 2 contract in the plan (and to §6.1 of test-plan.md if it describes the fixture format): `dialog` is the dialog state from which `changes` must come.
- **Decision**: FIXED — addendum in the plan's phase 2 contract and a sentence in §6.1 of test-plan.md

### F5 — Empty fixture sections in the calculator table would not fail

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/to-spend-calculator.parity.test.ts:60-63; tests/Unit/ToSpendCalculatorParityTest.php:58-60
- **Detail**: The scenarios test guards with `expect(file.scenarios.length).toBeGreaterThan(0)` (line 57). Both calculator-table tests check only `format === 1`. An emptied `items`/`breakdowns`/`chains` section makes no assertion fail. A missing key does throw.
- **Fix**: Assert that all three sections are non-empty in both files, as the scenarios test does.
- **Decision**: FIXED — both calculator-table tests assert non-empty `items`, `breakdowns` and `chains`

### F6 — Edit branch of the Feature helper always books an expense

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/EntryToSpendParityTest.php:292-299
- **Detail**: The `remove` + `add` path always calls `ManageFinancialOperation::updateExpense`, while `entryParityBook` branches between `createIncome` and `createExpense` on the type. Today the only edit scenario (`running-edit-with-draw`) is an expense. A future income edit scenario would go through the expense update without any signal.
- **Fix**: Branch on the line's type (`updateIncome`/`updateExpense`) or throw a `LogicException` for income.
- **Decision**: FIXED — the edit branch calls `updateIncome` or `updateExpense` by the Category type

### F7 — Names and wording: generic Pest helpers, oracle from code, wrong comment

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Unit/ToSpendCalculatorParityTest.php:14,28,43; tests/Fixtures/amount-invariants/to-spend-calculator-cases.json:156; tests/Fixtures/amount-invariants/entry-to-spend-scenarios.json:269; tests/Feature/ReportAmountInvariantTest.php:270
- **Detail**:
  - `parityCalculatorCases`, `parityToSpendItem` and `parityDataset` are global across the whole suite. The plan named the `parity…` prefix, but the other new files use specific prefixes (`entryParity*`, `reportInvariant*`), and a future parity test for Month Remaining would likely want `parityDataset`.
  - The derivation of "expense without a rhythm" justifies its value with code behaviour ("takes the Running branch") instead of the glossary. The glossary says "A new expense Category is Running".
  - `fixed-charge-plan-zero-proposal` cites "Irregular Rate / Proposal" for income floor(60000/12). The source is the Month Plan Proposal rule (ADR-0033/0066).
  - The comment "Category 8 is "Codzienne wydatki"" is wrong: in `mapping.php` Category 8 is "Artykuły spożywcze", and "codzienne wydatki" is the sheet label that maps to it.
- **Fix**: Rename the helpers to `toSpendParity*`, reword the two derivations to cite the glossary/ADR, and correct the comment.
- **Decision**: FIXED — helpers renamed to `toSpendParity*`, derivations cite the Rhythm glossary entry and ADR-0033, Category 8 comment corrected

## Triage

The user chose to have every finding fixed without walking through each one ("napraw samodzielnie").

- Fixed: F1, F2, F3, F4, F5, F6, F7 (7)
- After the fixes: the parity and report tests on PostgreSQL pass (39 passed, 188 assertions); Vitest parity 45 passed, 1 skipped (#273); Pint, `npm run types:check`, `npm run check` and `vendor/bin/sloppy ci` pass.
