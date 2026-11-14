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

The Operations view (ADR-0038, ADR-0039) uses one grid, `.operations-grid`, for the rows (`.operations-row`, hover `secondary`) and the day row (`.operations-day-row`), with no column header row and no date column: all three sections are grouped by day (`groupRowsByDay`; financial operations add the server day sum through `groupOperationsByDay`, transfers and capital movements have a day row without a sum), inside `ListSurface` `flush` (rows reach the card edges and carry `px-4`); do not rebuild them from utilities. Row actions are one "⋯" menu, `OperationRowMenu` (edit, delete through `ConfirmActionDialog` with `v-model:open`, because the menu content unmounts on close); the filter bar has a full-width search, the presets and "Filtry" on the right and no result counter. A day sum comes only from the `operationDaySums` prop (server, whole day, filters applied), never from the rows of the page. Day sum and operation amount: `+` in `success` or `−` in `foreground` (`formatOperationDaySum` / `operationDaySumClass`, `formatFinancialOperationAmount` / `financialOperationAmountClass`), not `amountToneClass`; amounts and day sums carry no unit (`formatMoneyAmount`; transfers and capital movements through its signed variant) and every section has the footer "Kwoty w zł" (in transfers and capital movements one `meta` line: unit and explanation separated by " · ", ADR-0058), and the day label comes from `formatDayLabel`.

## Tabs keep `section`; one primary action; a disabled action shows its reason

The sections are tabs: `SegmentedControl` neutral with `count` and the `segmented-control-tabs` class (no border), the choice in `?section=`. Build every `/operations` query only with the helper from `resources/js/lib/operations-list-query.ts`, which keeps `section` (filters, "Wyczyść filtry" and tabs lose it otherwise). The add control is one primary "Dodaj operację" with a menu; an unavailable item shows its reason as text in the item and stays focusable: `as-child` with `aria-disabled` on the child and a `@select` that calls `preventDefault()`, because reka skips `disabled` items during keyboard navigation (and its own `aria-disabled` overrides one passed as an attribute). The kitchen sink at `/dev/operations` (`local` only) renders the view's 7-state matrix from `resources/js/lib/operations-fixtures.ts`; add new states there (tabs, menu, empty filter result, silenced section, empty section, loading). `npm run check:ui` also catches opacity literals in the form `/[0.03]`.
The search field of the filter bar has `id="operations-search"` and the "Dodaj operację" menu is controlled with `v-model:menu-open` on `OperationsPageActions`: global shortcuts (`N`, `/`) reach them through `requestOperationsIntent` (`useOperationsIntent`), which `Operations.vue` consumes and clears; do not rename the id or open the menu any other way.
The reason a create action is disabled is visible text referenced by `aria-describedby`, never `title` on a disabled `Button` (`pointer-events-none` hides it and it is not a description). A domain page title row has at most one primary `Button`; the other actions are `outline`, all with an icon.

The category filter is `CategorySelect` (ADR-0093): chips, the "Wszystkie kategorie" option, Dochody / Wydatki sections in list order with group headings and the archived suffix, built by `categorySections` (`@/lib/category-filter-options`) from `filterCategories` (server order, never sorted again in the client); the Term Deposit filter is `AccountSelect` with an avatar. Do not bring back a native select with `optgroup` there.

## Writes return to the same list

A write on Operations (create, edit, convert, delete of an income, expense, own transfer or capital movement) sends `operationsReturnQuery(filters, section, pages)` as `{ query }` of its Wayfinder `.form(...)`, read through `inject(OPERATIONS_RETURN_QUERY, undefined)` (`Operations.vue` provides it; the kitchen sink has no provider). The state travels only under `list[...]`, never as top-level keys, and the server (`RedirectsToOperationsList`) reads only `list` and drops every value the list's own rules (`IndexOperationsRequest::listRules`) reject, so a stale filter cannot bounce the redirect. The filter and page keys live once in `OperationsListQuery::FILTER_KEYS` / `PAGE_KEYS`.
