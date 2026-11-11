---
paths:
    - 'app/Support/**/*.php'
    - 'resources/js/pages/**/*.vue'
    - 'resources/js/components/ui/chart/**'
    - 'tests/Feature/Nfr/*.php'
    - 'resources/js/components/Report*.vue'
    - 'resources/js/lib/format-share*.ts'
    - 'resources/js/lib/report-chart*.ts'
    - 'resources/js/lib/reports-fixtures.ts'
    - 'tests/Unit/ReportsHardcodedUiTest.php'
---

# Reports and charts

## A chart library is a recorded decision, not a dependency you add on the way

The chart library is shadcn-vue Chart on Unovis (ADR-0071, exact versions in `package.json`); `resources/js/components/ui/chart/**` is the generated primitive, never edited for one screen. Adding any other library needs a decision in the change plan and an ADR. Every chart has a table with the same numbers next to it, so the data reads without the chart. Chart colours come from tokens in `resources/css/app.css` (income `--success`, expense `--chart-1`, the same period a year earlier `--chart-2`); category colour only in `CategoryChip`, never on bars. A chart is `aria-hidden` and loaded with `defineAsyncComponent`; amounts go through `formatMoney` from `@/lib/format`.

## Report amounts equal the existing readings to the grosz

Compute report amounts on the server in `app/Support` and pass them as props in minor units. Reuse `BudgetMonthResult`, `CategoryExecutionMetrics` and `MonthCategoryActuals`, or prove equality with them in a test: a month's report result equals Month Result, and a year's category total equals the annual execution. Refunds count net; own transfers and capital movements are excluded.

## The Reports page is tables on the `.report-grid`, guarded and shown in a kitchen sink

`Reports.vue` shows `ReportStatPanel` and one `ReportEntryTable` per type (Wydatki, then Przychody) on the `.report-grid` classes in `app.css` (Kategoria | Kwota | Udział; income has no Udział column), groups as `ReportGroupRow` with `aria-expanded`/`aria-controls`, archived rows muted with the `zarchiwizowana` Badge. With a comparison (`report.comparison`, ADR-0074) the columns are rok | rok wcześniej | Różnica | Udział on `.report-grid-compare` inside `.report-table`, which switches by the card's own width (below it the row gets a second line with the previous amount and the difference); the server computes the comparison and joins the years by the list Category, never by name, and the page only formats, so do not recompute differences in the client. The difference tone is `reportDifferenceTone` + `planChangeToneClass` (archived rows stay muted). The share is `formatShareTenths` from `@/lib/format-share` (tenths of a percent from the server, `null` is "—"). The types in `resources/js/types/reports.ts` mirror `PeriodReport::toArray()`; change both in one commit with an `assertInertia` test. The Report components are on the list in `tests/Unit/ReportsHardcodedUiTest.php` and in `scripts/check-ui-literals.mjs`; every state goes to `/dev/ui/reports` (`local` only, fixtures in `resources/js/lib/reports-fixtures.ts`).

## A year report loads within 2 seconds at 10,000 operations

Add a test next to `tests/Feature/Nfr/NfrPerf01Test.php` and keep per-month and per-category queries out of loops (Sloppy flags them).
