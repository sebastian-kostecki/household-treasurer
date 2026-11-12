---
paths:
    - 'app/Support/**/*.php'
    - 'resources/js/pages/**/*.vue'
    - 'resources/js/components/ui/chart/**'
    - 'tests/Feature/Nfr/*.php'
    - 'resources/js/components/Report*.vue'
    - 'resources/js/lib/format-share*.ts'
    - 'resources/js/lib/report-chart*.ts'
    - 'resources/js/lib/report-trend*.ts'
    - 'resources/js/lib/report-window*.ts'
    - 'resources/js/lib/reports-fixtures.ts'
    - 'tests/Unit/ReportsHardcodedUiTest.php'
---

# Reports and charts

## A chart library is a recorded decision, not a dependency you add on the way

The chart library is shadcn-vue Chart on Unovis (ADR-0071, exact versions in `package.json`); `resources/js/components/ui/chart/**` is the generated primitive, never edited for one screen. Adding any other library needs a decision in the change plan and an ADR. Every chart has a table with the same numbers next to it, so the data reads without the chart. Chart colours come from tokens in `resources/css/app.css` (income `--success`, expense `--chart-1`, the same period a year earlier `--chart-2`); category colour only in `CategoryChip`, never on bars. A chart is `aria-hidden` and loaded with `defineAsyncComponent({ loader, loadingComponent, delay: 0 })`: the placeholder (`ReportBarChartPlaceholder`, `ReportTrendChartPlaceholder`: a `Skeleton` in `ListSurface`, height from `reportChartHeightRem` or `h-64`, legend from `ReportChartLegend`, shown only when the chart has bars) holds the chart's exact room so the table does not jump when the fragment arrives (ADR-0084); amounts go through `formatMoney` from `@/lib/format`.

## Report amounts equal the existing readings to the grosz

Compute report amounts on the server in `app/Support` and pass them as props in minor units. Reuse `BudgetMonthResult`, `CategoryExecutionMetrics` and `MonthCategoryActuals`, or prove equality with them in a test: a month's report result equals Month Result, and a year's category total equals the annual execution. Refunds count net; own transfers and capital movements are excluded.

## The Reports page is tables on the `.report-grid`, guarded and shown in a kitchen sink

`Reports.vue` shows `ReportStatPanel` and one `ReportEntryTable` per type (Wydatki, then Przychody) on the `.report-grid` classes in `app.css` (Kategoria | Kwota | Udział; income has no Udział column), groups as `ReportGroupRow` with `aria-expanded`/`aria-controls`, archived rows muted with the `zarchiwizowana` Badge. With a comparison (`report.comparison`, ADR-0074) the columns are rok | rok wcześniej | Różnica | Udział on `.report-grid-compare` inside `.report-table`, which switches by the card's own width (below 36rem the row has two columns: the previous amount and the difference go to a second line and the Udział to the last line under the amount behind the `udział` label, `.report-share-label`; the archived Badge wraps under the name, ADR-0084); the server computes the comparison and joins the years by the list Category, never by name, and the page only formats, so do not recompute differences in the client. The difference tone is `reportDifferenceTone` + `planChangeToneClass` (archived rows stay muted). The share is `formatShareTenths` from `@/lib/format-share` (tenths of a percent from the server, `null` is "—"). The types in `resources/js/types/reports.ts` mirror `PeriodReport::toArray()`; change both in one commit with an `assertInertia` test. The Report components are on the list in `tests/Unit/ReportsHardcodedUiTest.php` and in `scripts/check-ui-literals.mjs`; every state goes to `/dev/ui/reports` (`local` only), which renders the real `Reports.vue` with full `ReportsPageProps` from the fixtures in `resources/js/lib/reports-fixtures.ts`: a new state is a new scenario there, not new markup in the sink. The title row (`h1`, and the period switch whenever there is a Budget) stands above every empty state (draft, no Budget). The category name with an amount in the current period (the period tables and the Trend) is a `<Link>` with `.report-name-link` to `operations({ query: reportCategoryOperationsQuery(...) })`, the range being the report's month, the whole Budget year or the last-12 window through `reportRangeOperationsQuery`; groups, the previous-year column, rows that exist only through the comparison, and the Trend's month cells and "Razem" do not link, and the Operations filter stays flat (ADR-0069).

## The Trend view is `ReportTrend` in whole zloty, scrolled in its card

`?view=trend` (ADR-0077) is the third segment of the Reports switch: the page gets the `trend` prop from `ReportTrend::forYear` (the rows and the order of the year `PeriodReport`, 12 months per row, `null` is a month without an operation and `0` a net-zero month) and `report` is `null`; do not recompute rows or months in the client. Its year amounts equal the year report and its closed month result equals Month Result (tests prove both). The tables are `ReportTrendSummary` (Przychody, Wydatki, Wynik) and one `ReportTrendTable` per type on `ReportTrendRow` and the `.report-trend-grid` classes in `app.css`: 14 columns in one line inside `.report-trend-scroll` (focusable region) that scrolls below the grid's minimum width, the name cell pinned left, ARIA table roles because the chart is `aria-hidden`; groups start collapsed. The month chart `ReportTrendChart` (vertical bars, income `--success`, expense `--chart-1`, `aria-hidden`, async, legend note "<month> w toku") draws the same numbers as the Przychody and Wydatki rows of the measures table (`reportTrendChartBars` over the same `months`), never other ones; a negative or empty month is drawn as a zero bar, while the table keeps its exact amount. Amounts are whole zloty and only here: `formatWholeMoneyAmount` / `formatWholeMoney` from `@/lib/format`, with the footer "Kwoty w zł, zaokrąglone do pełnych złotych". The tone is only in the Wynik row; a year change on the Trend keeps the Trend (`destination: 'reports-trend'`).

## The last twelve months is `PeriodReport::forWindow`, without comparison and Trend

`?view=last-12` (ADR-0081) is the fourth segment of the Reports switch (Rok | Miesiąc | 12 miesięcy | Trend): the page gets `period.kind = 'last-12'` and `period.window` (`ReportWindow`, the dates of the 12 closed months, the covered and the missing stretch and `covered_month_count`) from `ReportTwelveMonthWindow`, and `report` from `PeriodReport::forWindow` with `comparison: null`. The window is resolved on the server, before the phase of the selected Budget, so the "draft" state does not take it over: in a draft Budget the switch stays and "12 miesięcy" shows the window, with "Rok" leading back to the draft card (ADR-0084); do not compute window dates or coverage in the client. There is no month bar and no Trend. `ReportCoverageNote` (only for `0 < covered_month_count < 12`) names the missing months and how many months the amounts cover, its text from `reportCoverageNote` in `@/lib/report-window`; `covered_month_count === 0` shows the empty state "Brak budżetu w realizacji w tym okresie" instead of the panel and tables. `ReportStatPanel` takes `period-kind="last-12"` and labels the result "Wynik 12 miesięcy" (it is the sum of Month Result, not Year Result). The category name links through `reportRangeOperationsQuery` with the `range` prop of `ReportEntryTable` / `ReportCategoryRow` (`aria-label` from `range.label`; the page and the kitchen sink build the range with `reportWindowRange` in `@/lib/report-window`). A Budget year change in the sidebar from this view leads to the year report of the chosen year. Every window state is a scenario `last-12-*` in `/dev/ui/reports`.

## A year report loads within 2 seconds at 10,000 operations

Add a test next to `tests/Feature/Nfr/NfrPerf01Test.php` and keep per-month and per-category queries out of loops (Sloppy flags them).
