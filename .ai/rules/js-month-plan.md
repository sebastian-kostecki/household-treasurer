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

The grid is `.month-plan-grid` with the rows `.month-plan-header-row` (applies the `table-header` style), `.month-plan-row`, `.month-plan-group-row` (the band of a type section: Dochody / Wydatki / Odkładam, not a Category Group), `.month-plan-category-group-row`, `.month-plan-category-group-body`, `.month-plan-row-nested`, `.month-plan-total-row`, `.month-plan-idle-row` and the footer `.month-plan-footer`; the past-month summary is `.month-plan-summary-grid` (with `-label`, `-head`, `-cell`); the month strip dots are `.month-strip-dot*`. The edit bar is `.month-plan-editbar`; a secondary button on it is Button `ghost-inverse`, because `ghost` hovers in `accent` and a component class cannot override it. The plan cell in edit mode is the `.cell-input` class on a native input, not `ui/Input` (a component class loses to the `ui/Input` utilities). The grid has a column "Zatwierdzony" before "Plan" (ADR-0060): the approved amount muted with the direction arrow when the plan differs from it, "—" before the approval, empty in the sum rows and in the put-aside row; there is no "Zatwierdzono:" caption, and below `xl` the arrow stands in the mobile caption line. The arrow compares the approved amount with the value the row shows now (the field value in change mode), never the server `plan_minor_units`. `CategoryChip` has the size `lg` here (the tile as tall as the name with its caption, the caption inside the chip). Amounts in the grid, the summary and the captions carry no "zł"; the unit is in the footer (ADR-0058). A new grid, size or label style gets a named class in app.css, never an arbitrary value in the template.

Below `sm` the four amounts stand in a 2 × 2 grid (Zatwierdzony | Plan, Faktycznie | Odchylenie), from `sm` to `xl` in four columns, from `xl` in the seven-column line; below `xl` the caption line stands above the value in all four cells (ADR-0092, replacing the four-column sentence of ADR-0060). The group row (`.month-plan-grid-group`) and the put-aside row (`.month-plan-grid-set-aside`, `-set-aside-edit` with the direction switch) have their own areas below `sm`. The Deviation tone comes from `monthItemDeviationTone` and the summary card from `monthSummaryDeviationTone` (`lib/month-plan-deviation-tone.ts`), never from a local helper: an expense below plan is `success`, over plan `destructive`, an archived line has no tone. Every value the page writes into the Plan field (start, fallback of a reset, „Ustaw plan na wydatki”, the put-aside amount) goes through `minorUnitsToGroupedMoneyInput`; text typed by hand stays as typed.

The "Faktycznie" cell (`.month-plan-cell-actual`) lays out like "Zatwierdzony" (caption, control, amount). `MonthPlanMatchActual` (the button "Ustaw plan na wydatki" or the marker "Plan podniesiony do wydatków", never both) stands before the amount, below `xl` in the caption line; its hit area comes from the named class `.month-plan-match-actual` in app.css (at least 24 px, WCAG 2.5.8). After the click the row moves focus to the plan field, because the button turns into the marker. The state comes from `matchActualState` (`lib/month-plan-match-actual.ts`), not from page state (ADR-0062).

An irregular row (ADR-0066, ADR-0073) shows the rate in the „Plan” column, the bill in „Faktycznie”, the excess in „Odchylenie” and the caption `zebrane X` (`data-collected`) under the name, after the „W roku” line and a „·”; X is the Collected at the end of the month, computed live from the typed rate by the TS copy of `CollectedCalculator`. Change mode starts from the saved rate and never offers „Ustaw plan na wydatki” for it (`matchActualState`).

## Category Group rows come from monthPlanGroupTree

The tree of a type section and the sums of its group rows (Plan, Actual, count, „ponad plan”, unreadable plan) come from `monthPlanGroupTree` (`lib/month-plan-groups.ts`) over the page's `rowState`, never from the server and never from a rule of their own: the signal is "any shown row is over plan" by that row's Rhythm, so read mode and edit mode share one path (ADR-0079). Approved and Deviation stay empty on a group row. The collapse state belongs to the page (`collapsedGroupIds`), is reset on a change of month or Budget, and a group with an unreadable plan field opens by itself and cannot be collapsed while the field is unreadable (`unreadableGroupIds` and `withoutUnreadableGroups` in `lib/month-plan-groups.ts`, shared by the page and the kitchen sink).

## Status badges come from monthItemStatus

The table has a Status column (ADR-0042). The badge comes from `monthItemStatus` (resources/js/lib/month-plan-status.ts), called in the page's `rowState` with the live plan amount and whether the month is closed (`summary !== null`): nothing planned and nothing settled has no badge, and a closed month says „niezapłacone” / „nie wpłynęło” instead of „do zapłaty” / „czeka”. Badge `brand` marks the approved plan, `outline` a closed past month („Końcowy”).

## Every month plan component is on the hardcoded-UI test list and in the kitchen sink

tests/Unit/MonthPlanHardcodedUiTest.php (part of `composer ci:check`) lists the view files and fails with file:line on colour literals or arbitrary values; add a new month plan component to its file list and to scripts/check-ui-literals.mjs. The kitchen sink at /dev/month-plan (`APP_ENV=local` only) renders the real components (`MonthPlanTable`, `MonthPlanStatPanel`, `MonthPlanEditBar`, `MonthSummaryCard`, `BudgetMonthPicker`, `MonthPlanTitleRow`, empty states) in the 7-state matrix from resources/js/lib/month-plan-fixtures.ts: month strip with every dot state, four stat panel states (negative, live, final, pace in norm / ahead / no percent / future), table in read, edit, error, past and empty-group variants, Category Group states (collapsed with „ponad plan”, unreadable field, income group, 390 px), sticky edit bar, loading spinners. Add a new month plan state there.
