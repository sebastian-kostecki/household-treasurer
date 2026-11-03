---
date: 2026-11-03T06:39:35+01:00
researcher: Claude (Sonnet 5.5) for Sebastian Kostecki
git_commit: 61e8faafcde43d7396d1a8aa23147a8ec7e48b38
branch: development
repository: household-treasurer
topic: "month-plans-vs-year (S-10 / FR-010): what exists today to let a user compare monthly plans with the annual budget"
tags: [research, codebase, month-plan, annual-budget, MonthPlanProposal, BudgetMonthExecutionIndex, S-10, FR-010]
status: complete
last_updated: 2026-11-03
last_updated_by: Claude (Sonnet 5.5)
---

# Research: month-plans-vs-year (S-10 / FR-010)

**Date**: 2026-11-03T06:39:35+01:00
**Researcher**: Claude (Sonnet 5.5)
**Git Commit**: 61e8faafcde43d7396d1a8aa23147a8ec7e48b38 (working tree has only untracked `context/changes/*` folders)
**Branch**: development
**Repository**: household-treasurer

## Research Question

`change.md` has no intent text, so the question is taken from the roadmap and PRD: slice S-10 `month-plans-vs-year`, "użytkownik może porównać plany miesięcy z budżetem rocznym" (`context/foundation/roadmap.md:54`, `:207-217`), FR-010 (`context/foundation/prd.md:125-126`). PRD resolution: "dla każdej kategorii pokazywane jest wydane w roku plus plan bieżącego miesiąca względem planu rocznego". Hard constraint: the comparison must not change the annual budget (`roadmap.md:216`, `prd.md:93`).

Research question: what already exists in the code (data, aggregators, pages, navigation, tests, recorded decisions) that S-10 builds on, what is missing, and which product choices are still open for `/10x-plan`?

## Summary

- **Nothing implements the comparison yet.** On the inspected tree there is no route, controller, support class, page or test that sets month-plan amounts against the annual plan across months. `routes/web.php:28-34` has only `month-plan` (GET), `month-plans.approve` and `month-plans.update`. S-10 is a new read-only reading plus a UI surface. The roadmap Backlog Handoff row still says "Czeka na S-01" (`roadmap.md:233`), but S-01 (`month-plan-and-to-spend`) is archived, so the prerequisite is met.
- **Both sides of the comparison are available as data.**
  - Annual side, per category of one budget year: `categories.planned_amount_minor_units` (Current Plan), `initial_plan_minor_units`, and the stored running total `execution_minor_units` (updated by `ManageFinancialOperation`, lines 226-244).
  - Monthly side: `MonthPlanProposal::forBudget($budget, $today)->reading($month)` for months 1-12 returns a `MonthPlanReading` with per-category `amounts` (the *current* amount for an approved plan) in a fixed number of queries (`MonthPlanProposal.php:40-65`, `:83-124`).
- **Month plan states matter.** A `month_plans` row exists only for an approved month. A past month without a row reads `none` (empty amounts). A current or future month without a row reads `proposal`, computed on the fly and never persisted (`MonthPlanProposal.php:98-124`). Budgets in draft phase have no month plans.
- **Per-category, per-month actuals exist in two partial shapes:** `BudgetMonthExecutionIndex` (one query for the whole year, *expense categories only*, with `executionInMonth` and `executionBeforeMonth`), and `MonthCategoryActuals::forMonth` (one month, income and expense). Neither covers "all months, both types".
- **FR-010's wording leaves the main formula open.** "Wydane w roku plus plan bieżącego miesiąca" can double-count the current month if "wydane w roku" includes the current month's operations. See Open Questions #1.
- **Recorded decisions constrain the design:** ADR-0033 and PRD line 197 say the sum of month plans may differ from the year and "ta różnica ma być widoczna"; no recorded decision fixes where the comparison lives or its layout. Set-aside/draw over the year belongs to S-09, not S-10.

## Detailed Findings

### Annual budget side (inspected path: Budget, Category, BudgetController, ManageFinancialOperation)

- Each `Category` row belongs to exactly one budget year (`categories.budget_id`). It carries `planned_amount_minor_units` (annual Current Plan), `initial_plan_minor_units` (frozen when execution starts, `StartBudgetExecution.php:18-31`), `execution_minor_units`, `is_paced`, `month_item_kind` (`bill`/`running`), `archived_at` (`app/Models/Category.php`; migrations `2026_09_07_180001`, `2026_09_10_195354`, `2026_11_02_090956`).
- "One-off" is not a column: it means an expense category with `is_paced = false` (`MonthPlanController.php:233`).
- There is no per-month annual amount on a category. Month amounts exist only in `month_plan_items`.
- `execution_minor_units` is a stored total, incremented/decremented on every create/update/delete of a `FinancialOperation` (`app/Actions/ManageFinancialOperation.php:226-244`, as reported by the annual-side worker and confirmed by grep). Income and expense operations both feed it; refunds are negative amounts and reduce it. Own transfers and capital movements are separate models with no category, so they are structurally outside this total (no explicit filter was found).
- Budget phases: `Draft` and `Execution`, one-way transition (`StartBudgetExecution`). Month plans exist only in `Execution` (`MonthPlanController.php:67-69` returns `monthPlan: null` for drafts; `BudgetPolicy::manageMonthPlan` requires execution).
- Existing annual presentation: `Budget.vue` with `BudgetCategoryList.vue` (1015 lines, tightly coupled to editing/reorder), `BudgetPlanSummaryStrip`, `BudgetMonthStrip`. Reusable pieces named by the worker: `ListSurface`, `CategoryChip`, `CategoryMeterRow`, `BudgetCategoryTableHeader`, `ColumnHeaderHint`, `IndexEmptyState`, `lib/dashboard-categories.ts`, `formatMoney`. `CategoryExecutionMetrics` supplies remaining/percent/over-limit for one plan/execution pair.

### Monthly plan side (inspected path: MonthPlan models, MonthPlanProposal, Approve/ChangeMonthPlan, MonthPlanController)

- Tables: `month_plans` (`budget_id`, `month`, `approved_at` NOT NULL, `approved_set_aside_minor_units`, `current_set_aside_minor_units`; unique `(budget_id, month)`) and `month_plan_items` (`month_plan_id`, `category_id`, `approved_amount_minor_units`, `current_amount_minor_units`; unique `(month_plan_id, category_id)`). No status column. `Budget::monthPlans()` is the only relation (`Budget.php:66`).
- "Plan of the month" in the readings is the **current** amount; the approved amount is exposed separately as `approvedAmounts` (`MonthPlanProposal.php:98-104`; `MonthPlanReading.php`). `CONTEXT.md` defines Approved Month Plan (frozen) and Current Month Plan (live, "final" at month end).
- Category joining after approval is inserted with approved = 0 and current = amount (`ChangeMonthPlan.php:83-91`), so a category can be absent from earlier months' `amounts` entirely.
- Proposal rule (`MonthPlanProposal.php:83-124`, `:148-199`): copies the previous month's reading in any non-`none` state; January reads December of the previous budget by exact name+type (only if that budget is in `Execution`); with no source it uses floor(annual/12) with December taking the remainder; One-off categories propose 0; archived categories drop out of months starting after `archived_at`.
- A past month is `none` unless it has an approved row (`:107-109`). Approval is blocked while the previous month is still a proposal (`isBlockedByPreviousMonth`, `:71-74`).
- `MonthPlanProposal` is the only class that holds plans for more than one month. It has no per-category series method; a caller would loop `reading(1..12)` (`:62-65`). The reported query budget is "≤ 5 queries for 12 readings" (`MonthPlanProposalTest::test_budget_loading_uses_a_fixed_number_of_queries`, taken from the worker's grep of test names, not re-run).
- `MonthToSpend::forMonth` is single-month and builds its own proposal, so calling it twelve times would rebuild the proposal each time (`MonthToSpend.php:32`; used by `DashboardController:100`, `BudgetController:129`, `MonthPlanController:145`).
- The month plan page already shows the annual remaining limit for One-off rows only: `available_limit_minor_units = planned − execution` (`MonthPlanController.php:263-268`; test `MonthPlanPageTest.php:701`).

### Actuals per category per month (inspected path: BudgetMonthExecutionIndex, MonthCategoryActuals)

- `BudgetMonthExecutionIndex::forBudget($budget, $expenseCategories)` runs one query: `budget_id`, `type = Expense`, `whereIn category_id`, `whereYear(operation_date, $budgetYear)`, summed into `[categoryId][month]` in PHP (`BudgetMonthExecutionIndex.php:71-91`). Methods: `executionInMonth`, `executionBeforeMonth`, `pacedExecution*` (`:37-69`). It has no income data.
- `MonthCategoryActuals::forMonth(Budget, month)` is one query per call, grouped by category, with no operation-type filter, so it covers income and expense for a single month (`MonthCategoryActuals.php:16-39`).
- **Reconciliation caveat (not verified):** the whole-year total is the stored `execution_minor_units`, while the month indexes filter by the operation date's calendar year. If an operation's date can fall outside the budget year of its category, the two would not reconcile. Whether request validation prevents that was not checked.

### Placement, routing and navigation (inspected path: routes/web.php, SelectedBudgetYear, AppSidebar)

- Existing pages: Dashboard, Budget, MonthPlan, Accounts, TermDeposits, Operations, import pages. Sidebar nav lives in `resources/js/components/AppSidebar.vue:27-58` (Dashboard, Budget, Month plan, Accounts, Term deposits, Operations); `ShellState` is not navigation.
- Year selection is session-based: `budget.select-year` accepts `destination` of `budget` or `month-plan` only (`SelectBudgetYearRequest`, `BudgetController::selectYear`, lines ~271-282); the sidebar `BudgetYearSwitcher` posts only `year`, which lands on Budget. A new page that depends on the selected year would need to extend that allow-list or accept landing on Budget after a year switch.
- Month selection is the `?month=N` query, resolved by `BudgetViewedMonth::resolve` (default: month 1 for a future year, 12 for a past year, else the current month).
- Dashboard always reads the current calendar year, not the session year (ADR-0031; `DashboardController.php:32-33`).
- Existing cross-links that a comparison could sit beside: `BudgetMonthStrip.vue` → month plan with `?month`; `MonthPlan.vue` back-links to the Budget page. Which of these (or a new route/section) is right is not decided anywhere.

### Category identity

- Within one budget year everything keys on `category_id`, so a same-year comparison needs no name matching. The only cross-year name+type matching is `MonthPlanProposal::matchPreviousCategory` (`:171-188`), used for January proposals only; it does not matter for S-10 unless the comparison spans years (nothing in FR-010 asks for that).

### Repo conventions that apply (reported from `AGENTS.md` and `.ai/rules/js.md`; AGENTS.md was read by a worker, not by me)

- Props are PHP arrays from controllers with hand-written TS types; assert them with `assertInertia`. "Month logic lives twice": compute readings on the server; add a TS copy only if a preview must react before submit, and justify it in the plan.
- Money: integer minor units, `MinorUnits`, `formatMoney`, `tabular-nums`. Colours from tokens only; reuse `ListSurface`, `CategoryChip` etc.; surfaces per ADR-0003. Polish strings in `lang/pl/ui.php` (no `en`). Wayfinder routes in `resources/js/routes/`.
- Checks listed there: `npm run check`, `vendor/bin/pint --dirty`, `vendor/bin/sloppy ci`; PostgreSQL run for SQL/date behaviour.
- Tests: PHPUnit-style classes under `tests/Feature` run by Pest; page tests use `actingAs`, `withSession(['budget_year' => …])`, `travelTo`, `assertInertia`. Factories exist: `BudgetFactory::execution()`, `CategoryFactory` (`income/expense/archived/bill/oneOff`), `MonthPlanFactory`, `MonthPlanItemFactory`, `FinancialOperationFactory`. No browser/e2e tests were found.

## Code References

- `context/foundation/roadmap.md:207-217` - S-10 slice definition and risk ("porównanie nie może zmieniać budżetu rocznego")
- `context/foundation/prd.md:125-126` - FR-010 and its Socratic resolution
- `context/foundation/prd.md:197` - "Suma planów miesięcy może różnić się od roku i ta różnica ma być widoczna"
- `.docs/adr/0033-month-plan-beside-annual-budget.md` - month plan beside the annual budget; annual budget unchanged
- `app/Support/MonthPlanProposal.php:40-65` - `forBudget` / `reading(month)` (whole-year source of plan amounts)
- `app/Support/MonthPlanProposal.php:83-124` - approved vs none vs proposal logic
- `app/Support/MonthPlanReading.php` - value object (`state`, `amounts`, `approvedAmounts`, `setAsideMinorUnits`)
- `app/Support/BudgetMonthExecutionIndex.php:37-91` - per-category per-month expense actuals for the whole year
- `app/Support/MonthCategoryActuals.php:16-39` - single-month actuals, both types
- `app/Actions/ManageFinancialOperation.php:226-244` - maintenance of `categories.execution_minor_units`
- `app/Actions/ChangeMonthPlan.php:83-91` - category joining mid-year with approved = 0
- `app/Http/Controllers/MonthPlanController.php:263-268` - annual available limit on One-off rows
- `routes/web.php:28-34` - existing month-plan routes (no comparison route)
- `resources/js/components/AppSidebar.vue:27-58` - sidebar navigation
- `tests/Feature/MonthPlanPageTest.php` - page test pattern; `tests/Feature/MonthPlanProposalTest.php` - proposal rules

## Architecture Insights

- The system keeps the annual budget as the source of truth for the year and layers month plans beside it; readings are computed on the server and passed as Inertia props. S-10 fits this pattern as a new server-side reading (likely a `Support` class beside `MonthToSpend`) plus a Vue surface.
- Amounts for non-approved months are computed, not stored; any whole-year reading has to choose how to treat `proposal` months (see Open Questions #2) rather than reading rows.
- Total annual execution is a stored counter, while monthly breakdowns are re-aggregated from operations; the two sources have different type coverage (expense-only vs both) and different date semantics.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:81` and `plan-brief.md:62` - "Porównanie z rokiem: to S-10": the only explicit deferral of this comparison; no archived file records how it should look.
- `context/archive/2026-11-02-set-aside-and-draw/plan.md:64`, `research.md:50` - set-aside/draw in year scale is deferred to S-09 (Month Summary), per `CONTEXT.md:95-96`; not part of S-10.
- `context/archive/2026-11-02-approved-plan-changes/plan.md:43` - plan-vs-plan (approved vs current) comparison belongs to S-04/S-09, not S-10.
- `context/archive/2026-11-02-month-items-plan-vs-actual/plan.md:52-60,80` - S-04 chose to show only today's Available Limit (annual remaining) for One-off rows and no limit net of the month plan.
- `.docs/adr/0024-month-remaining-is-not-available-limit.md` - Available Limit stays annual Current Plan minus booked Execution; superseded by ADR-0033 only on "no second monthly budget".
- `.docs/adr/0031-dashboard-always-shows-current-calendar-year.md` - Dashboard uses the calendar year; the shell year drives Budget.
- Sibling changes `month-summary` (S-09) and `plan-based-entry-warnings` (S-08) contain only an empty `change.md`; no overlap decision with S-10 is recorded. Likely shared inputs (month plan readings, annual execution) are an inference from the domain text.

## Related Research

- `context/archive/2026-11-02-month-plan-and-to-spend/research.md`, `.../month-items-plan-vs-actual/research.md`, `.../to-spend-replaces-remaining/research.md` - earlier exploration of the same month-plan code paths (not re-read in full for this note).

## Open Questions

Product decisions for `/10x-plan` (or `/10x-frame`) before implementation; nothing in the repo settles them:

1. **What exactly is compared?** FR-010 says "wydane w roku plus plan bieżącego miesiąca względem planu rocznego". If "wydane w roku" includes the current month's operations, adding the current month's plan counts that month twice. Candidates: (a) executed *before* the current month (`executionBeforeMonth` exists, expense only) + current month plan; (b) full year-to-date execution + the *remaining* part of the current month's plan; (c) sum of 12 monthly plan amounts vs annual (ADR-0033's "sum may differ" wording), which the PRD says is only complete in December.
2. **Which month-plan states count?** Approved months use *current* amounts; unapproved current/future months are only proposals (copies/averages), and past months without a plan are `none`. Should the comparison use proposal amounts, mark them "niezatwierdzony", or only count approved plans?
3. **Which categories?** Expense only, or income too? One-off categories (proposal = 0, annual limit semantics), archived categories, and categories added mid-year all behave differently in the readings.
4. **How to show the difference?** Per-category deltas vs totals, the over-annual-limit signal (use of `destructive` token/Alert), and handling of an annual Current Plan changed after months were approved (approved month amounts do not follow it).
5. **Where does it live?** New page/route, a section on Month plan, or on Budget. This also affects the year-switcher `destination` allow-list and sidebar nav.
6. **Draft budgets and year selection:** a draft budget has no month plans (empty state needed); a past or future selected year changes which months are `none`/`proposal`.

Technical gaps not verified in this research:

- Whether operation dates can fall outside their category's budget year (affects reconciling `execution_minor_units` with the month indexes).
- Query cost of reading 12 months plus a whole-year actuals query for both income and expense (not measured; the reported ≤5-query cap applies only to `MonthPlanProposal::forBudget`).
- Internals of `MonthItemDeviation`, `ChangeMonthPlan` (only grepped by a worker), `BudgetCategoryList.vue` beyond its header, and the `.ai/skills/openship-ui-development` skill were not read.
