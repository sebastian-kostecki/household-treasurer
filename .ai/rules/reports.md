---
paths:
    - 'app/Support/**/*.php'
    - 'resources/js/pages/**/*.vue'
    - 'resources/js/components/ui/chart/**'
    - 'tests/Feature/Nfr/*.php'
    - 'resources/js/components/Report*.vue'
    - 'resources/js/lib/format-share*.ts'
    - 'resources/js/lib/reports-fixtures.ts'
    - 'tests/Unit/ReportsHardcodedUiTest.php'
---

# Reports and charts

## A chart library is a recorded decision, not a dependency you add on the way

There is no chart library yet. Adding one (Chart.js, ApexCharts, ECharts or another) needs a decision in the change plan and an ADR. If shadcn-vue Chart is chosen, add it with the shadcn-vue CLI into `resources/js/components/ui` and follow `components.json`. Every chart has a table with the same numbers next to it, so the data reads without the chart. Chart colours come from tokens in `resources/css/app.css` and category colours from `resources/js/lib/category-appearance.ts`; amounts go through `formatMoney` from `@/lib/format`.

## Report amounts equal the existing readings to the grosz

Compute report amounts on the server in `app/Support` and pass them as props in minor units. Reuse `BudgetMonthResult`, `CategoryExecutionMetrics` and `MonthCategoryActuals`, or prove equality with them in a test: a month's report result equals Month Result, and a year's category total equals the annual execution. Refunds count net; own transfers and capital movements are excluded.

## The Reports page is tables on the `.report-grid`, guarded and shown in a kitchen sink

`Reports.vue` shows `ReportStatPanel` and one `ReportEntryTable` per type (Wydatki, then Przychody) on the `.report-grid` classes in `app.css` (Kategoria | Kwota | Udział; income has no Udział column), groups as `ReportGroupRow` with `aria-expanded`/`aria-controls`, archived rows muted with the `zarchiwizowana` Badge. The share is `formatShareTenths` from `@/lib/format-share` (tenths of a percent from the server, `null` is "—"). The types in `resources/js/types/reports.ts` mirror `PeriodReport::toArray()`; change both in one commit with an `assertInertia` test. The Report components are on the list in `tests/Unit/ReportsHardcodedUiTest.php` and in `scripts/check-ui-literals.mjs`; every state goes to `/dev/ui/reports` (`local` only, fixtures in `resources/js/lib/reports-fixtures.ts`).

## A year report loads within 2 seconds at 10,000 operations

Add a test next to `tests/Feature/Nfr/NfrPerf01Test.php` and keep per-month and per-category queries out of loops (Sloppy flags them).
