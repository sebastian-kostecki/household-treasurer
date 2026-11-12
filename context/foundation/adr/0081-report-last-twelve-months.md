# Report last twelve months: a closed window across Budgets

S-32 (FR-025) adds a fourth view to Reports, „12 miesięcy”: the Period Report of the last 12 closed months counted from today, whatever Budget year is selected. ADR-0070 fixed what a report row is, ADR-0074 added the year before and ADR-0077 the Trend; this ADR fixes the window, what covers it, where the numbers come from and how the view is reached, before the code. It adds to ADR-0070 and changes none of its clauses for the Year and the Month.

Window. The window is the 12 closed calendar months ending with the month before today (UTC, like ADR-0074 and ADR-0077). On 12 November 2026 it is 2025-11-01 to 2026-10-31; on 1 January 2027 it is 2026-01-01 to 2026-12-31 and equals the year report of 2026. The current month is never in it, so the window has no „w toku” mark. The start is computed from the first day of the last month with `subMonthsNoOverflow`, so 31 March does not skip February.

Coverage. Only months whose Budget is in execution are counted. A month without such a Budget is not read and not estimated; the page names the missing stretch and says how many of the 12 months the amounts cover. The covered part and the missing part are each one continuous stretch (a window touches at most two Budget years). With no covered month there is no report and the page shows an empty state. The segment is always available when the User has any Budget; it is never hidden or disabled for partial coverage.

Source. For every covering Budget one `MonthCategoryActuals::forRange` over the part of the window in its year, plus the Budget Lines; the rows are joined by list Category id, never by name, and go through `ReportCategoryTree` like the Year and the Trend. The number of queries is constant per covering Budget, whatever the number of Categories.

Result. The result of the window is the sum of Month Result of the covered months, to the grosz (a test proves it against `BudgetMonthResult`). It is not Year Result: the UI says „Wynik 12 miesięcy”. The share of an expense row is that of the expenses of the window.

Archived mark. A row follows the line of its Category in the newest covering Budget; when that Budget has no line of the Category, the row follows the archive date of the list Category. An older line is not used for the mark: archiving a Category (ADR-0063) archives only the line of the current year's Budget in execution and leaves older lines active, so a Category archived after its last line would otherwise read as active. This extends ADR-0070 and ADR-0074.

No comparison and no Trend. The window has no year-over-year comparison (ADR-0074 stays in the Year and the Month) and no month-by-month Trend (ADR-0077 stays the Trend of a Budget year). A full comparison of the window would be possible in production at the earliest on 1 January 2028, so it returns, if at all, as its own slice.

Navigation. `?view=last-12` is the fourth segment, after „Miesiąc” and before „Trend”. It is resolved before the phase of the selected Budget, so it works also when the selected year is in preparation. Changing the Budget year in the sidebar from this view leads to the year report of the chosen year.

Link to Operations. A category name links to Operations with the range of the whole window. This relies on Financial Operations existing only in a Budget in execution (`OperationCategoryChoice::check`), so the date filter shows what the report counts; the accepted exception is a restore, whose snapshot comes from the application.

We rejected:

- the current month in progress inside the window (B), a window of 365 days (C) and an end of the window chosen with the month bar (D): a closed window is the same figure all month and equals a sum of Month Results;
- a segment disabled until the window is fully covered: a User with one Budget would never see it;
- a comparison with the same window a year earlier when fully covered, or on the months both sides share: two rules for one column, and the figure would change meaning with the data;
- staying on the view when the Budget year changes (`reports-last-12`): the window does not depend on the year, so the switch would do nothing;
- the segment before „Rok”: the Year stays the first and the default view.
