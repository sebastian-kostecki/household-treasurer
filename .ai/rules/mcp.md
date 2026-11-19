---
paths:
    - 'app/Mcp/**/*.php'
    - 'routes/ai.php'
    - 'tests/Feature/Mcp/**'
---

# MCP server

ADR-0108: `laravel/mcp`, a local stdio server and a public `/mcp` endpoint behind Passport OAuth. Tools are thin: they read through `app/Support` and write through `app/Actions`.

## Every query starts at the user

- A tool reaches data only through `$request->user()` relations (`$user->budgets()`, `$user->accounts()`, …). An id from the input is checked against those relations (or `IndexOperationsRequest::listRules($user->id)`) and a foreign id is a validation error naming the field, never another user's row.
- No session, no `SelectedBudgetYear`, no `ShellState`: a tool never reads the web session. Over stdio there is no middleware, so the only user comes from the server's `boot()`.

## The App Day in every answer

- "Today" comes from `AppDay::today()` and every answer carries `app_day` (`YYYY-MM-DD`), plus the year and the Budget phase it used where relevant (`.ai/rules/app-day.md`).
- A year without a Budget in execution is reported with its phase; tools do not fall back to another year (ADR-0075 stays out of tools).

## Amounts come from the readers

- Amounts are taken from the `app/Support` readers (`SpendableLiquidity`, `TotalFunds`, `MonthToSpend`, `BudgetPlanSummary`, `CategoryExecutionMetrics`, …), never recomputed in a tool. The answer carries `*_minor_units` integers and a display string from `MinorUnits::toMajorDisplay` with " zł".
- Month Category Group sums come only from `App\Support\MonthPlanGroupSums`.

## Read and write

- Read tools carry `#[IsReadOnly]`; results are bounded (operations at most 50 per call, one year or one month per call).
- The write tool (cash operation) is registered only when `MCP_WRITE_ENABLED` is true, always asks the user for approval (`requiresUserInteraction`) and is idempotent by `request_id`.
- Logs never carry arguments, amounts or descriptions (`LogMcpCall`: user id, JSON-RPC method, tool name, status, duration).
