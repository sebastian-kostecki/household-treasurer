# The Category Panel gets a "Lata" tab and the Years view links its category names to it

ADR-0097 (paragraphs „Columns” and „Category Panel”) says that the Category Panel shows the months of the year and that in the Years view „S-36 makes the category name a link”. S-36 (`report-panel-years-tab`) builds both. This ADR narrows or completes ADR-0097 on the points below and changes nothing else in it; ADR-0097 is not edited.

Amended in part by ADR-0108: the sentence of „Range” about the current year (every year up to the same day) holds only from February; while the current year has no closed month (App Day in January) the tab counts Full Years in the current year too, so the N−1 bar is the whole year and no longer equals the N−1 tile, which stays the year-over-year amount up to the same day. The rest of this ADR, including no mode switch in the panel, stands.

Link. The name of a Category in the Years view is a link only when the Category has a row in the year report of the target year (the last column), the same rule that makes the panel accept `?category`: an operation in the target year, or in the year before it up to the comparison day (only when that year is a column: a year without a Budget in execution between them means no comparison, ADR-0074). Any other name stays plain text (a row of a group, the total and the cells do not link). This narrows the ADR-0097 sentence „S-36 makes it a link”. The server computes the flag (`opens_panel`) from the counters it already reads, for both modes of the table alike, and a test compares it with the rows of the real `PeriodReport::forYear`.

Route. The link goes through the year selection (`POST budget/year`, `destination: 'reports'`, with `report_category` and `report_panel: 'years'`), because the selected year lives in the session (ADR-0075), and a plain GET would open the year of the sidebar. The redirect is `/reports?category=<id>&panel=years`; the year switch in the sidebar carries `report_panel` the same way it carries `report_category`.

Tab. The label „Miesiące <rok>” of the panel becomes a switch „Miesiące <rok> | Lata”, kept in the address as `?panel=years` (any other value means the months). The years are computed by the server with the panel, from the same `ReportYears` reading as the table, so the switch and a change of Category need no request.

Range. In the current year the tab counts every year up to the same day (Same-day Cut); in every other year, and when there is no current year in execution, it counts full years (Full Years), so that the bars of the viewed year and of the year before equal the tiles of the panel, which count whole years there. The panel has no mode switch; the switch stays in the Years view. A year that is not a column of the Years report (a future year in execution) has no tab: the panel shows the label „Miesiące <rok>” as before, the server sends `years: null` and `tab: 'months'` also for `?panel=years`, because its bars could not show the viewed year and would not equal the tiles.

Content. Under the bars: the row „Zmiana od <rok>” (Change since First Year), the row „Najwięcej” (years of a tie together) and the caption of the range („Każdy rok do <dzień miesiąca>” or „Pełne lata”). The tiles and the „Operacje” button do not change. Amounts in the panel are in zloty with grosze.

Bars. An expense is `chart-1`, an income `success`, the current year `foreground`, a year without a Budget Line is a 2 px stroke in `track`, a negative amount is a zero bar. The bars are `aria-hidden`; the accessible form is a `sr-only` list with the amount of every year („brak pozycji w budżecie” for a year without a line, „<rok>, w toku” for the current year in full years, as in the Years table).

Rejected:

- a link for every Category, opening a panel without the Category in the ranking (the panel would show another Category in silence);
- a link to the last year in which the Category has a row (the target year of ADR-0097 stays the rule);
- a „Pełne lata” switch in the panel (two sources for one tile);
- the mode of the table in the address (state of another view);
- the years read by a separate request (`Inertia::defer`), which would make the switch wait;
- in a future year, the tab with the past columns only or with an empty state (bars without the viewed year, a tab with nothing to compare).
