---
paths:
    - 'resources/js/pages/Operations.vue'
    - 'resources/js/components/Operation*.vue'
    - 'resources/js/components/Operations*.vue'
    - 'resources/js/components/FinancialOperation*.vue'
    - 'resources/js/components/CapitalMovement*.vue'
    - 'resources/js/lib/operations-*.ts'
---

# Operations list

## Named classes, day rows and server day sums

The Operations view (ADR-0038, ADR-0039) uses one grid, `.operations-grid`, for the rows (`.operations-row`, hover `secondary`) and the day row (`.operations-day-row`), with no column header row and no date column: all three sections are grouped by day (`groupRowsByDay`; financial operations add the server day sum through `groupOperationsByDay`, transfers and capital movements have a day row without a sum), inside `ListSurface` `flush` (rows reach the card edges and carry `px-4`); do not rebuild them from utilities. Row actions are one "⋯" menu, `OperationRowMenu` (edit, delete through `ConfirmActionDialog` with `v-model:open`, because the menu content unmounts on close); the filter bar has a full-width search, the presets and "Filtry" on the right and no result counter. A day sum comes only from the `operationDaySums` prop (server, whole day, filters applied), never from the rows of the page. Day sum and operation amount: `+` in `success` or `−` in `foreground` (`formatOperationDaySum` / `operationDaySumClass`, `formatFinancialOperationAmount` / `financialOperationAmountClass`), not `amountToneClass`; amounts carry "zł" (transfers and capital movements through `formatMoney`) and the day label comes from `formatDayLabel`.

## Tabs keep `section`; one primary action; a disabled action shows its reason

The sections are tabs: `SegmentedControl` neutral with `count` and the `segmented-control-tabs` class (no border), the choice in `?section=`. Build every `/operations` query only with the helper from `resources/js/lib/operations-list-query.ts`, which keeps `section` (filters, "Wyczyść filtry" and tabs lose it otherwise). The add control is one primary "Dodaj operację" with a menu; an unavailable item shows its reason as text in the item and stays focusable: `as-child` with `aria-disabled` on the child and a `@select` that calls `preventDefault()`, because reka skips `disabled` items during keyboard navigation (and its own `aria-disabled` overrides one passed as an attribute). The kitchen sink at `/dev/operations` (`local` only) renders the view's 7-state matrix from `resources/js/lib/operations-fixtures.ts`; add new states there (tabs, menu, empty filter result, silenced section, empty section, loading). `npm run check:ui` also catches opacity literals in the form `/[0.03]`.
The reason a create action is disabled is visible text referenced by `aria-describedby`, never `title` on a disabled `Button` (`pointer-events-none` hides it and it is not a description). A domain page title row has at most one primary `Button`; the other actions are `outline`, all with an icon.
