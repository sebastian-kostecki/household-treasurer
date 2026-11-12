---
paths:
    - 'app/Support/MonthRemainingCalculator.php'
    - 'app/Support/ToSpendCalculator.php'
    - 'app/Support/MonthItemDeviation.php'
    - 'app/Support/MonthPaceCalculator.php'
    - 'app/Support/CollectedCalculator.php'
    - 'resources/js/lib/month-remaining-calculator*.ts'
    - 'resources/js/lib/to-spend-calculator*.ts'
    - 'resources/js/lib/month-item-deviation*.ts'
    - 'resources/js/lib/month-pace-calculator*.ts'
    - 'resources/js/lib/collected-calculator*.ts'
    - 'resources/js/lib/hand-entry-month-remaining-preview.ts'
    - 'resources/js/lib/import-month-remaining-warnings.ts'
    - 'resources/js/lib/entry-to-spend-warning*.ts'
    - 'resources/js/lib/import-to-spend-warnings*.ts'
    - 'tests/Unit/MonthRemainingCalculatorTest.php'
    - 'tests/Unit/ToSpendCalculatorTest.php'
    - 'tests/Unit/MonthItemDeviationTest.php'
    - 'tests/Unit/MonthPaceCalculatorTest.php'
    - 'tests/Unit/CollectedCalculatorTest.php'
---

# Month calculators

## Each month reading lives in a PHP and a TS copy; change both copies and both tests together

| PHP (`app/Support`)            | TS (`resources/js/lib`)         | Tests                                                                               |
| ------------------------------ | ------------------------------- | ----------------------------------------------------------------------------------- |
| `MonthRemainingCalculator.php` | `month-remaining-calculator.ts` | `tests/Unit/MonthRemainingCalculatorTest.php`, `month-remaining-calculator.test.ts` |
| `ToSpendCalculator.php`        | `to-spend-calculator.ts`        | `tests/Unit/ToSpendCalculatorTest.php`, `to-spend-calculator.test.ts`               |
| `MonthItemDeviation.php`       | `month-item-deviation.ts`       | `tests/Unit/MonthItemDeviationTest.php`, `month-item-deviation.test.ts`             |
| `MonthPaceCalculator.php`      | `month-pace-calculator.ts`      | `tests/Unit/MonthPaceCalculatorTest.php`, `month-pace-calculator.test.ts`           |
| `CollectedCalculator.php`      | `collected-calculator.ts`       | `tests/Unit/CollectedCalculatorTest.php`, `collected-calculator.test.ts`            |

The TS copies exist only where a preview must react before submit: a proposal cannot be saved before approval, and saving a changed approved plan overwrites the final plan, so the month plan page previews To Spend, item deviation and pace live. Compute every new month reading on the server and pass it as a prop; add a TS copy only for a preview that must react before submit, and state that reason in the plan.

## The TS copies feed the entry and Import warnings

`hand-entry-month-remaining-preview.ts` and `import-month-remaining-warnings.ts` build on the Month Remaining copy and are the only Month Remaining code that runs in production (nothing calls the PHP copy outside its test); the `*.test.ts` files next to them cover the Rhythm of the month of the operation. `entry-to-spend-warning.ts` and `import-to-spend-warnings.ts` build on the To Spend copy (the "do wydania" below zero warning) and are covered by the `*.test.ts` files next to them. A change to a calculator changes the hand-entry and Import previews too, so run those tests as well.

`CollectedCalculator` (S-26, ADR-0066, ADR-0073) is the arithmetic of the irregular Category's Collected: the amount at the end of a month, the excess over it and the chain of months. It has a TS copy because the month plan previews the row live while the rate is typed, and because the entry and Import warnings recompute the chain before a bill is saved. The warnings read the periods (opening amount, then rate and actual per month) from `EntryToSpendInputs`, never a Collected of their own; the Collected at the start of a month on the server comes only from `IrregularCollected` (`.ai/rules/categories.md`).
