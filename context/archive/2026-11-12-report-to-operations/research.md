---
date: 2026-11-12T06:54:04+01:00
researcher: Claude (claude-sonnet-5-5)
git_commit: 28f6581810bffee9cb85bcdc445ff34843f0e32d
branch: worktree-report-to-operations
repository: household-treasurer
topic: "S-31 / FR-018: going from a Category in a Report to its operations in the selected period"
tags: [research, codebase, reports, operations, filter, period-report, S-31]
status: complete
last_updated: 2026-11-12
last_updated_note: "Dopisano decyzje użytkownika (grupy, rok wcześniej, puste wiersze, Trend, suma listy)"
last_updated_by: Claude (claude-sonnet-5-5)
---

# Research: S-31 `report-to-operations`: from a Category in the Report to its operations in the period

**Date**: 2026-11-12T06:54:04+01:00
**Git Commit**: 28f6581810bffee9cb85bcdc445ff34843f0e32d (docs only on top of `development`; no code of this change exists yet)
**Branch**: worktree-report-to-operations
**Repository**: household-treasurer

## Research Question

`change.md` has only a title. The question was derived from `context/foundation/roadmap.md:305-316` and `prd-v3.md:202`: how do the Operations list and the Reports page work today, what exactly does the Report count per Category, and which prior decisions constrain a link "Category in a report → its operations in the selected period"? The roadmap risk says the Operations filter "goes from name and type to list Category and group (change to ADR-0016)" and that "the list must show what the Report counts (refunds net, no own transfers)".

## Summary

1. **The Category half of the roadmap risk is already done.** On the inspected path (`OperationsListQuery::apply`, `app/Support/OperationsListQuery.php:134-136`) the Operations list filters by the list Category id (`whereHas('budgetLine', category_id = ?)`), across all Budgets. ADR-0016's own footer says it is superseded in part by ADR-0063 (`context/foundation/adr/0016-…md:4`). The roadmap/PRD wording ("z nazwy i typu na kategorię listy") is stale for the Category part.
2. **Only the group filter is new.** In the inspected request class and query class (`IndexOperationsRequest`, `OperationsListQuery::toArray()` at `:100-111`) there is no group parameter; the 7 filter keys are `q, year, category_id, account_id, term_deposit_id, date_from, date_to`. ADR-0069 line 13 says "the Operations filter and the command palette stay flat", and the archived Category Groups plan defers a group filter to S-31 (`context/archive/2026-11-10-category-groups/plan.md:38`). A group filter would contradict that clause, so it should start with a new ADR amending 0069 (next free number 0079: the highest file in `context/foundation/adr` is `0078-category-merge.md`). `AGENTS.md` requires an ADR first only for a glossary rule or an _Avoid_ term, so whether 0069 forces one is a plan decision (see Open Questions).
3. **The Report period maps exactly to `date_from`/`date_to`.** On the manual write path the Budget is chosen by the year of `operation_date` (`app/Actions/ManageFinancialOperation.php:222-227`), so a year or a month of one Budget never spans two Budgets and a date-only filter selects the same rows as the Report's `budget_id` + date predicate (`app/Support/MonthCategoryActuals.php:35-39`). Import and restore paths were not inspected (gap).
4. **The Report counts the signed sum of `financial_operations.amount_minor_units`** per Budget Line, with no filter on operation `type` (`MonthCategoryActuals.php:41`). Refunds are negative amounts and net automatically; own transfers and capital movements are separate tables that the Report never reads. The Operations "financial operations" section (`$user->financialOperations()`) reads the same table, and under any active filter own transfers are suppressed and, with `category_id` or `year`, capital movements are suppressed too (`OperationsListQuery.php:78-95`). So `?category_id=<id>&date_from=<first>&date_to=<last>` already shows exactly the rows behind a Category's Report amount.
5. **A precedent for the link exists.** `categoryOperationsQuery(category, year)` (`resources/js/lib/dashboard-categories.ts:35-48`) builds `{category_id, date_from, date_to}` through the single query builder `operationsListQuery`; it is used by the Dashboard row and the command palette. No Report component links to Operations today.
6. **Gaps in what the list shows:** it has no total for the filtered set (only per-day sums computed as income minus expense, `app/Support/OperationsDaySums.php:130-163`); a visible "this sums to the Report amount" needs new server code, or is left out.
7. **Unresolved product choices** (listed at the end): group links, links on comparison-only rows, links from the Trend, the previous-year column, and the list total.

## Detailed Findings

### Operations list: what exists today

- Route `Route::get('operations', …)->name('operations')` (`routes/web.php:100`); Wayfinder function `operations` from `@/routes`. Controller `app/Http/Controllers/OperationsController.php:29-195`: filters applied to `$user->financialOperations()`, order `operation_date` desc then `id` desc, 50 per page (`operations_page`); own transfers and capital movements are separate paginators.
- Validated params (`app/Http/Requests/IndexOperationsRequest.php`): `q`, `year` (2000..2100; `whereHas('budget', year)`), `category_id` (must exist for this user), `account_id`, `term_deposit_id`, `date_from`, `date_to` (`after_or_equal:date_from`, inclusive via `whereDate`), `section`. There is no `month`, no group and no type parameter. A test pins that the old `category_name`/`category_type` parameters are ignored (`tests/Feature/OperationsFilterTest.php:184`), and another that another user's `category_id` is rejected (`:599`).
- Props to `Operations.vue` include `filters` (`OperationsListQuery::toArray()`), `filtersActive`, `suppressedSections`, `filterCategories` (`OperationsFilterOptions::categories`, shape `{id, name, type, icon, color}`, with the selected Category added even without operations, `app/Support/OperationsFilterOptions.php:32-81`), `operationDaySums`, `sectionTotals` (counts that ignore filters).
- The single place that builds the `/operations` query on the client is `resources/js/lib/operations-list-query.ts` (`operationsListQuery`, `EMPTY_OPERATIONS_FILTERS`); a new filter key must be added in `EMPTY_OPERATIONS_FILTERS`, `operationsListQuery`, `OperationsListFilters` and the filter/chip components in the same change (`.ai/rules/js-operations.md`; chip files not read in full, gap).
- Existing deep links into Operations, all with `operations({query})`: `CategoryMeterRow.vue:27` and `useCommandPalette.ts:111` (Category + whole year via `categoryOperationsQuery`), `CategoryListRow.vue:39` (Category, all years), `DashboardNotices.vue:165-191` (account; Category + month range). MonthPlan and Budget pages: none found by grep of `resources/js`. A guard test forbids a hand-written `href="/operations` in a row (`resources/js/ui-contract.test.ts:519-528`).

### Reports: what is counted

- `app/Http/Controllers/ReportController.php:21-107` (plain `Request`, no FormRequest): year from `SelectedBudgetYear::resolve` (session, not a query param); `?month=1..12` optional (digits 1–12, else the whole year); `?view=trend` ignores `month`. Props: `budgetYear`, `budgetPhase`, `period {kind, month, default_month, is_in_progress}`, `report`, `trend`.
- Amounts: `MonthCategoryActuals::forMonths` (`app/Support/MonthCategoryActuals.php:27-60`) sums `amount_minor_units` for `budget_id = $budget->id` and `operation_date` in `[first day of the first month, first day after the last month)`, grouped by `budget_line_id`; a month in progress is counted in full (no cut at today). `FinancialOperationType` has the cases Income and Expense only and the sum ignores `type`; a row is income or expense in the Report by the list Category's type (`ReportCategoryTree` entry filter, reported at `:397`, not re-read by me).
- Refund = negative `amount_minor_units` on an expense Category (`ManageFinancialOperation.php:78-82`); nets by the plain `SUM`. A net-zero Category keeps its row (`has_operations` true).
- Rows: `PeriodReport::rows` (`app/Support/PeriodReport.php:100-138`) maps Budget Lines of that Budget to list Categories (`category_id`); archived lines/Categories are included and only muted and badged; a group sums all its member rows, archived included, by the Category's current `category_group_id` (ADR-0070; `PeriodReport` docblock). A row can exist only through the comparison range (`line = null`, amount 0, `has_operations` false, `:127-135`).
- Comparison (ADR-0074): the previous-year range `comparison.from..through` is inclusive and cut at today-minus-one-year when the selected period contains today (`ReportComparisonPeriod.php:464-466`, `is_partial`).
- Props (`resources/js/types/reports.ts:3-66`, `PeriodReport::toArray()`): category entry `{id (list Category id), name, icon, color, amount_minor_units, share_tenths_percent, is_archived, has_operations, previous_minor_units, difference_minor_units}`; group entry `{kind:'group', id (CategoryGroup id), …, categories[]}`. No entry carries an href, a `budget_line_id` or a date range; the client has `budgetYear`, `period.month`, `category.id`, `comparison.from/through`.

### Reports: where a link could go (Vue)

- `ReportEntryTable.vue` is a `div` grid (not an ARIA table). Category name cell in `ReportCategoryRow.vue:367-376` is `CategoryChip` + archived `Badge`; amount cell `:377-383`; previous-year cell `:384-395`.
- `ReportGroupRow.vue:256-275`: the name cell is a `button` with `aria-expanded`/`aria-controls`; a group link would have to be a separate element.
- Trend (`ReportTrendRow.vue`, `ReportTrendTable.vue`): ARIA roles (`row`, `rowheader`, `cell`); month cells are plain `div role="cell"` (`:107-115`), year total and month cells are a calendar month / the whole year of the Budget year. ADR-0077 declined a link from a month header to the month report; it says nothing about links to Operations.
- Guards for any new/changed Report component: both lists in `tests/Unit/ReportsHardcodedUiTest.php:7-22` and `scripts/check-ui-literals.mjs:120-132`; each state in `/dev/ui/reports` through `resources/js/lib/reports-fixtures.ts`; the design system has no link-affordance rule for a report row (only `brand-text` for links in text, `design-system.md:53`).

### What the list shows versus what the Report counts

- Same table, same signed amounts, so the set of rows is identical for `category_id` + the period's first and last day (inclusive on both ends in the list; the Report's `[first, afterLast)` is the same set for date-typed `operation_date`).
- The list's day sum is `CASE WHEN type='income' THEN amount ELSE -amount END` per date (`OperationsDaySums.php:130-163`): income minus expense with refunds counting back; it is not the Report's per-Category sum and covers only the dates on the current page. No list-level total exists.
- Cross-user safety: `category_id` is validated against the User's own Categories (`IndexOperationsRequest`; test `OperationsFilterTest.php:599`); the test plan lists a cross-user filter test as risk #8 (`context/foundation/test-plan.md:54,67`).

## Code References

- `app/Support/OperationsListQuery.php:100-151` - filter keys, `apply()`, section suppression (`:78-95`)
- `app/Http/Requests/IndexOperationsRequest.php` - validation of the 7 filter keys
- `app/Support/OperationsFilterOptions.php:32-81` - Category options, selected Category kept without operations
- `app/Support/MonthCategoryActuals.php:27-60` - the Report's SQL for a month set
- `app/Support/PeriodReport.php:100-138` - row mapping to list Categories, comparison-only rows
- `app/Actions/ManageFinancialOperation.php:222-252` - Budget picked by year of `operation_date`
- `resources/js/lib/dashboard-categories.ts:35-48` - `categoryOperationsQuery` (link precedent)
- `resources/js/lib/operations-list-query.ts` - single query builder
- `resources/js/components/ReportCategoryRow.vue:356-411`, `ReportGroupRow.vue:247-309`, `ReportTrendRow.vue:78-115` - candidate link spots
- `resources/js/types/reports.ts:3-113` - report/trend prop types
- `context/foundation/adr/0069-category-group-order-and-budget-rows.md:13` - "Operations filter … stay flat"

## Architecture Insights

- Years are linked by list Category id, never by name (`.ai/rules/categories.md`); `category_id` on the HTTP boundary is always the list Category id.
- A Report row's identity (`id` = list Category id) is the same value the Operations filter accepts, so a Category link needs no new Report data and no new route; a link built on the client from `budgetYear` and `period.month` is exact. Server-side hrefs would instead change `PeriodReport::toArray()`, `reports.ts` and need an `assertInertia` test in one commit (`.ai/rules/reports.md`, `inertia-props.md`).
- The Report equals Month Result and annual Execution to the grosz (ADR-0070); the list would add no new counting rule for a Category link, only for a group link.

## Historical Context (from prior changes)

Each claim below is supported by the cited line unless marked.

- `context/archive/2026-11-10-period-report-by-group/plan.md:47` - "NOT doing … przejścia z wiersza do Operacji (S-31) … Wiersz tabeli nie jest klikalny." (supported)
- `context/archive/2026-11-11-year-over-year-report/plan.md:59` and `2026-11-11-report-trend-by-month/plan.md:56,64` - S-31 excluded; month-header link to the month report declined by the User (ADR-0077). (supported)
- `context/archive/2026-11-10-category-groups/plan.md:38` - "Filtr Operacji i paleta ⌘K z grupami: zostają płaskie (decyzja planu); filtr po grupie może przyjść z S-31." (supported; the only explicit deferral of the group filter)
- `context/archive/2026-11-09-category-list-after-migration/plan.md:273-279` - the Operations filter moved to `category_id`; old parameters ignored. (supported by code: `OperationsFilterTest.php:184`)
- `context/archive/2026-11-05-dashboard-atrament-ui/plan.md:43,237` - `operationsListQuery` as the single builder; Dashboard row links with Category + year range. (supported)
- ADR-0016 status: superseded in part by 0063 (partial: its clause on "name and type" is superseded, its clause on a Category-independent date/other filters is not touched). ADR-0063 amends 0016 for the Category filter. ADR-0070 fixes the counting rule (refunds net, no own transfers/capital movements). ADR-0074 warns that a row may exist only through the compared range (amount 0, `has_operations` false).
- `context/foundation/prd-v3.md:203` Socratic note: "filtr po kategorii listy (i grupie); zmiana ADR-0016" (partial: the Category part is done, the group part is not).
- `context/foundation/lessons.md` does not exist (checked by `ls`).

## Related Research

None under `context/changes/**/research.md`. The archived plans of S-28/S-29/S-30 above are the closest.

## Open Questions

Product/design choices this research does not settle (for `/10x-plan`; a delegate cannot decide them):

1. **Group links.** Group totals have no equivalent Operations filter. Options: no link on group rows (Category rows only); a new `category_group_id` filter (new ADR amending 0069, request/query/chips/options, i18n); or repeated `category_id[]`. FR-018 text says "(i grupie)" but is nice-to-have.
2. **Comparison-only rows** (`has_operations` false, amount shown as "—"): no link, or link to the previous-year range.
3. **Previous-year column**: link to `comparison.from..through` (inclusive, possibly partial) or not.
4. **Trend**: link from the category name (year range), from month cells (one month), both, or none.
5. **List total**: add a server-computed period total to the Operations list so "list = Report amount" is visible (needs a new reading in `app/Support` and Inertia prop on both sides), or rely on the shared rows only.
6. **Date representation in the list**: confirm how `date_from`/`date_to` for a full year or month renders as filter chips in `OperationsFilters.vue`/`OperationsActiveFilterChips.vue` (not read).
7. **ADR requirement**: ADR-0079 is needed only if the group filter stays in scope.

Verification gaps: import and restore write paths were not inspected for the invariant "year of `operation_date` = Budget year" (proved only for the manual create/update path); `ReportStatPanel`, `OperationsFilters.vue` templates and `app.css` classes were not read; no tests were run.

## Decisions (User, 2026-11-12, after the research)

Answers to Open Questions 1–5, each chosen with the recommended option:

1. **Group rows: no link.** The link is on the Category name only; a group row stays an expand/collapse toggle and its Categories carry the links. The Operations filter stays flat (ADR-0069 unchanged), so **no new filter, no ADR-0079** in this change.
2. **Previous-year column ("rok wcześniej"): no link.** Only the name leads to the current period.
3. **Comparison-only rows (`has_operations` false, amount "—"): no link on the name.** Rule: a link exists only where the row has a current-period amount. Test: a row with `has_operations = false` has no link.
4. **Trend: link on the Category name only, range = the whole Budget year.** Month cells and the year-total cell stay plain text; group rows in the Trend have no link.
5. **No total on the Operations list.** The Operations view is not changed. Equality "Report amount = sum of list rows" is proved by a Feature test (data with a refund, an own transfer and an operation of another year).

Consequences for the plan (derived from the findings above, not new research):
- Link target: `operations({ query: … })` from `@/routes` through `operationsListQuery`, reusing or generalising `categoryOperationsQuery` (`resources/js/lib/dashboard-categories.ts:35-48`): `category_id` + `date_from`/`date_to`. Year view: `<year>-01-01..<year>-12-31`; month view (`?month=N`): first..last day of that month.
- No change to `PeriodReport::toArray()` is required if the link is built on the client from `budgetYear`, `period.month` and the row `id`; if a server-side href is chosen instead, `reports.ts` and an `assertInertia` test change in the same commit.
- Touched files: `ReportCategoryRow.vue`, the Category rows of `ReportTrendRow.vue`/`ReportTrendTable.vue`, `reports-fixtures.ts` and `/dev/ui/reports`, `ui-contract.test.ts` (no hand-written `/operations` href), new tests; `ReportsHardcodedUiTest.php` and `scripts/check-ui-literals.mjs` stay valid as long as no new component file is added (if one is, add it to both lists).
- Still open for the plan (small): how `date_from`/`date_to` for a full year or month render as chips in `OperationsFilters.vue`/`OperationsActiveFilterChips.vue` (not read); the import/restore write paths were not checked for the invariant "year of `operation_date` = Budget year"; the Trend is in whole złoty while the list shows grosze.
