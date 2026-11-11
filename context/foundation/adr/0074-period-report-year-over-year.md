# Period Report year over year: the same period one year earlier, joined by list Category

S-29 (FR-016) puts the amount of the same period one year earlier and the difference beside every amount of the Period Report. ADR-0070 fixed what a report row is; ADR-0063 puts Categories on one list across years, so a Category id means the same thing in two Budgets. This ADR fixes what the report is compared with, how a period in progress is cut, which rows exist and what the difference means, before the code. It adds to ADR-0070 and changes none of its clauses except the row rule, which gets a second range when a comparison exists.

Compared with. The Budget of the same User for year N − 1 in execution, exactly that year: not the latest earlier Budget (as `CreateBudget` takes it) and not a Budget in preparation. This is the reading of „rok wcześniej” that `MonthPlanProposal` already uses. When there is no such Budget the report has no comparison and looks as before (`comparison: null`). Categories are joined by the list Category id (`categories.id`), never by name, so a Category renamed between years is one row and two Categories with similar names are two.

Range. A finished or future period is compared with the whole equivalent: the whole of year N − 1, or the whole month M of year N − 1. A period in progress (it contains today, in UTC like `is_in_progress`) is compared with the stretch from the start of the equivalent to today’s day minus one year without overflow (29 February gives 28 February), so both sides cover the same part of the calendar. The comparison is partial (`is_partial`) only when that day is before the end of the equivalent. The current column is not cut: it stays equal to the tiles and the Budget, also with operations dated after today.

Rows. A Category has a row when it has at least one operation in the current period or in the compared range. The side without an operation shows „—”: the current amount is 0 with `has_operations: false`, the previous amount is `null`. A group has a row when it has at least one such row. Without a comparison the rule is that of ADR-0070. The „no operations in the period” state shows only when neither range has an operation. The share stays the share of the current period; a row without a current operation has none. The archived mark comes from the Budget Line of the selected Budget; a Category without a line there takes `archived_at` of the list Category.

Difference. Current minus previous, a missing side counting as 0, as an amount in minor units; there is no percent change. Prezenty „—” | 1 500 | −1 500; Subskrypcje AI 300 | „—” | +300; Ubrania 5 900 | 6 547 | −647. The sums of a group and of the type are the sums of their rows on both sides, so the group equals its rows and the difference equals the difference of the sums. The comparison totals of a full period equal the report of the Budget of N − 1 for the same period (a Category’s Execution, a month’s Month Result).

Colour. The difference is coloured by benefit (the principle of ADR-0060): a lower expense, a higher income and a higher result are favourable (`success`), the opposite is unfavourable (`destructive`); zero, a missing side and archived rows have no tone. This extends the meaning of `success` and `destructive` to year over year; `destructive` is otherwise kept for overruns.

Chart and layout. The chart stays on the library of ADR-0071. The series „rok wcześniej” takes `--chart-2`, a muted neutral, because the colour of the current series still means the type and the previous year is the background to it. The table with a comparison has five columns where the card is wide enough and puts the previous year and the difference on a second line of the row where it is not, switched by the width of its own card (a container query, like `.category-meter-table`), not by the window.

We rejected:

- the whole previous period for a period in progress (a year in progress would look like a collapse against a finished year);
- cutting on the end of the current month (a month in progress would still compare a full month with a part);
- rows only from the current period (a Category that had spending last year and none now would disappear, and the totals would not equal the previous report);
- one summary row for Categories that exist on one side only (the user could not see which Category changed);
- a percent change (the amounts are small and a Category that did not exist last year gives no percent; the decision of the User is the amount alone);
- no colour on the difference (the sign alone does not say whether it is good for an expense);
- a „compare” toggle (the comparison is always shown when there is something to compare with);
- a chart of differences (the table already holds them; a trend belongs to S-30).
