---
paths:
    - 'resources/js/pages/MonthPlan.vue'
    - 'resources/js/components/MonthPlan*.vue'
    - 'resources/js/components/Month*.vue'
    - 'resources/js/components/BudgetMonthPicker.vue'
    - 'resources/js/lib/month-plan-*.ts'
    - 'tests/Unit/MonthPlanHardcodedUiTest.php'
---

# Month plan view

## The month plan is one table with named classes from app.css

The grid is `.month-plan-grid` with the rows `.month-plan-header-row` (applies the `table-header` style), `.month-plan-row`, `.month-plan-group-row`, `.month-plan-total-row`, `.month-plan-idle-row` and the footer `.month-plan-footer`; the past-month summary is `.month-plan-summary-grid` (with `-label`, `-head`, `-cell`); the month strip dots are `.month-strip-dot*`. The edit bar is `.month-plan-editbar`; a secondary button on it is Button `ghost-inverse`, because `ghost` hovers in `accent` and a component class cannot override it. The plan cell in edit mode is the `.cell-input` class on a native input, not `ui/Input` (a component class loses to the `ui/Input` utilities). The grid has a column "Zatwierdzony" before "Plan" (ADR-0060): the approved amount muted with the direction arrow when the plan differs from it, "—" before the approval, empty in the sum rows and in the put-aside row; there is no "Zatwierdzono:" caption, and below `xl` the arrow stands in the mobile caption line. The arrow compares the approved amount with the value the row shows now (the field value in change mode), never the server `plan_minor_units`. `CategoryChip` has the size `lg` here (the tile as tall as the name with its caption, the caption inside the chip). Amounts in the grid, the summary and the captions carry no "zł"; the unit is in the footer (ADR-0058). A new grid, size or label style gets a named class in app.css, never an arbitrary value in the template.

The "Faktycznie" cell (`.month-plan-cell-actual`) lays out like "Zatwierdzony" (caption, control, amount). `MonthPlanMatchActual` (the button "Ustaw plan na wydatki" or the marker "Plan podniesiony do wydatków", never both) stands before the amount, below `xl` in the caption line; its hit area comes from the named class `.month-plan-match-actual` in app.css (at least 24 px, WCAG 2.5.8). After the click the row moves focus to the plan field, because the button turns into the marker. The state comes from `matchActualState` (`lib/month-plan-match-actual.ts`), not from page state (ADR-0062).

## Status badges come from monthItemStatus

The table has a Status column (ADR-0042). The badge comes from `monthItemStatus` (resources/js/lib/month-plan-status.ts), called in the page's `rowState` with the live plan amount and whether the month is closed (`summary !== null`): nothing planned and nothing settled has no badge, and a closed month says „niezapłacone” / „nie wpłynęło” instead of „do zapłaty” / „czeka”. Badge `brand` marks the approved plan, `outline` a closed past month („Końcowy”).

## Every month plan component is on the hardcoded-UI test list and in the kitchen sink

tests/Unit/MonthPlanHardcodedUiTest.php (part of `composer ci:check`) lists the view files and fails with file:line on colour literals or arbitrary values; add a new month plan component to its file list and to scripts/check-ui-literals.mjs. The kitchen sink at /dev/month-plan (`APP_ENV=local` only) renders the real components (`MonthPlanTable`, `MonthPlanStatPanel`, `MonthPlanEditBar`, `MonthSummaryCard`, `BudgetMonthPicker`, `MonthPlanTitleRow`, empty states) in the 7-state matrix from resources/js/lib/month-plan-fixtures.ts: month strip with every dot state, four stat panel states (negative, live, final, pace in norm / ahead / no percent / future), table in read, edit, error, past and empty-group variants, sticky edit bar, loading spinners. Add a new month plan state there.
