---
paths:
    - app/Actions/**/*.php
    - app/Http/Controllers/**/*.php
    - app/Http/Requests/**/*.php
    - app/Support/**/*.php
---

# Errors

## A state validation already excluded throws `InvariantViolation`, never a 404, 403 or validation error

Laravel never reports `ModelNotFoundException`, `abort()`, `AuthorizationException` or `ValidationException`, so a guard inside an Action that uses them hides the one failure worth seeing: the request validation and the write disagree. When an Action re-checks something its Form Request or `OperationCategoryChoice` already refused (an archived line, a Category or Account of another user, a draft Budget), it throws `App\Support\InvariantViolation` with the ids in its context (never amounts or descriptions). The handler logs it and the transaction rolls back. Keep `firstOrFail` and `abort()` for what the user really asked for (a route model, a refusal the user can cause), and keep `ValidationException` for input the user can fix (observability audit 2026-11-19, PLB-3).

## Catch the narrowest class and report before you turn an exception into a message

A `catch` that turns an exception into a user message catches only the class it means: the import catches `App\Support\CsvImport\ImportFileRejected` (thrown with a translated message for a file the user has to fix), never the whole `InvalidArgumentException`, which libraries and bugs throw too. When the caught class can also mean a bug (`UniqueConstraintViolationException` on a table with several unique indexes), call `report($exception)` before throwing the message, unless you have proved the expected case (as `ApproveMonthPlan` checks that the parallel approval really exists). A `ValidationException` cannot carry `previous`, so without `report()` the cause is gone.

## A write that refuses what its preview or page showed as valid reports a named exception first

When a write refuses a state that its preview or page presented as valid (the import write after a valid preview, a month plan whose submitted items differ from the recomputed proposal), `report()` a named exception with ids before throwing the user message: `App\Support\CsvImport\ImportWriteRefused`, `App\Support\MonthPlanItemsRefused`, `App\Support\PastMonthPlanRefused`. The `ValidationException` and its message stay unchanged; the report is what shows "preview OK, write refused". Its `context()` holds ids and rule names only, never amounts or descriptions. A refusal a second tab legitimately causes ("already paired", "no longer available") is not reported.
