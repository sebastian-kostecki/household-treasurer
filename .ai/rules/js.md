---
paths:
    - 'resources/js/**/*.vue'
---

# Js

## Colours come from tokens; only money and alerts carry meaning

Use token utilities (bg-primary, text-destructive, text-warning, text-success, text-muted-foreground), never hex values or Tailwind palette colours (red-500, amber-700).
primary = action (ink); brand = identity, navigation, document state and the one approving action (ADR-0042), never money status; brand-text = links and strokes in the brand colour; success = income; destructive = exceeded (negative balance, over limit, negative Month Remaining) and delete actions, never an expense amount (in Operations an expense amount is `foreground`, ADR-0038; only the expense type tile is `destructive-soft`, ADR-0039); warning = within bounds but close to breaking.
Category colour is identity only (CategoryChip tile at 15%), never status. Full table: .docs/design-system.md.

## Amounts go through formatMoney with tabular-nums

Money is integer Minor units. Display with formatMoney from @/lib/format (1 234,56 zł) in metrics and dialogs, right-aligned, tabular-nums. In table cells use formatMoneyAmount (no unit) with the `amount` style (`amount-sum` in total rows) and put "Kwoty w zł" in the panel footer (ADR-0037), except the Operations list, whose amounts carry "zł" and which has no such footer (ADR-0039); edit with minorUnitsToMoneyInput / parseMoneyInputToMinorUnits. Do not format money with toFixed or Intl in components.

## Reuse surface building blocks before writing new markup

Check ListSurface, CategoryChip, CategoryMeterRow, OperationTypeTile, IconAction (icon button with tooltip and aria-label), IndexEmptyState, Heading, AccountIdentityBlock and SegmentedControl first. Also StatPanel and ExecutionMeter. Surfaces follow ADR-0037: grey floor, panels are `card` with a full `border` and `rounded-lg`, no resting shadows. Do not edit components/ui/* for a single screen.

## Non-blocking warnings use the warning Alert variant; a reading note uses `note`

A one-sentence note about a reading shown on the same page, under the metrics panel or under a table (month plan pace, drawing past available liquidity), uses `<Alert variant="note">` (ADR-0042). Other warnings that do not block saving (over limit, over Month Remaining, negative To Spend after an entry, negative balance, import duplicates) use <Alert variant="warning"> or bg-warning/10. Exception: in form dialogs of operations the warning is `text-warning` text inside the before → after preview block, under the reading it concerns, not an Alert (ADR-0041). variant="destructive" is only for real errors (AlertError) and delete actions; field errors use InputError. Operation type tiles follow ADR-0039: expense `destructive-soft` + `destructive`, income `success/10` + `success`, refund `warning-soft` + `refund`, own transfer `transfer`; the tile marks the type, the expense amount stays `foreground`.

## Bind the reka-ui Checkbox with v-model, never :checked

reka-ui `Checkbox` takes `modelValue` and emits `update:modelValue`; `:checked`, `@update:checked` and `v-model:checked` are radix-vue names and are silently ignored, leaving an uncontrolled box. Use `v-model` (see Dashboard.vue) or `:model-value` with `@update:model-value`. `resources/js/ui-contract.test.ts` fails on the old names; its exception list (issue #186) only shrinks.
A setting saved at once ("Pokaż zarchiwizowane") is a `Switch` from components/ui/switch, also bound with `v-model`.

## Confirmations use ConfirmActionDialog

Actions that need confirmation (delete, archive, restore) use `ConfirmActionDialog` with a Wayfinder `.form(...)` and a `trigger` slot, not a hand-written `Dialog` + `Form`. It gives a spinner on the confirm button, blocks closing while the request runs and shows server errors in the dialog.

## Responsive lists are one markup on a grid, inside ListSurface

Do not build a second mobile tree hidden with `md:hidden`. Use one row on a grid-template-areas class (`.operations-grid`, `.budget-category-grid`) inside a single `ListSurface`, with a `hidden md:grid` header row (the Operations list has no header row, ADR-0039). States of the Budget page (the 7-state matrix, without a month strip: execution rows within plan, income over 100%, overrun with the `przekroczono` Badge, archived, one-off without the year mark, paced with it; draft with a Type column; empty list; no budget; Switch on/off; StatPanel with 3 and 4 metrics) are shown in the kitchen sink at `/dev/kitchen-sink/budget` (`local` only); add a new Budget state there.

## Tokens and shared components come first; the UI literal check guards cleaned views

Values live in resources/css/app.css (:root / .dark, published by @theme inline). Base components are in components/ui/*, domain ones in components/. Before a new component check StatTile, IndexEmptyState and the "Gotowe elementy" list in .docs/design-system.md.
Signed amount colour goes through amountToneClass, column labels through the `table-header` style (the only column caption recipe; `.column-header` applies it in the month plan; capitals `label-caps` only above metrics and in the sidebar), severity and status pills through Badge (variants warning / destructive-soft / ok / brand / neutral; `brand` also marks document state, such as an approved month plan, ADR-0042), the elapsed-year mark in ExecutionMeter in `brand-text`.
Archived rows: no tone, except a term deposit balance < 0 (FR-TD-08) — then `amountToneClass`.
No colour literals, palette classes or arbitrary px/rem values in views; npm run check:ui guards the cleaned files (add newly cleaned views to scripts/check-ui-literals.mjs). Component sandboxes: /dev/ui/dashboard, /dev/ui/accounts, /dev/ui/term-deposits, /dev/operations and /dev/import-preview.
Dropdowns: use the native `.native-select` class for simple enums without icons; use the reka-ui `Select` when an option shows an icon or logo (e.g. AccountBankSelect). Both are 32px (`--control-height`) and `rounded-md`.

## Sidebar

Sidebar colours are `--sidebar-*` tokens from resources/css/app.css (roles in .docs/design-system.md, "Sidebar"); there is no `--sidebar` token, the background is `--sidebar-background`.
Whatever goes into the `SidebarHeader` / `SidebarFooter` slots is built from `SidebarMenuButton` or handles `group-data-[collapsible=icon]` itself, otherwise it breaks in the 3rem collapsed strip.
The active menu item uses `sidebar-primary` (also on hover); the app name, the user and the selected year use `sidebar-strong`; the sidebar has no `variant="inset"`, it sits flush against the content. The active menu item comes from `isCurrentSection` (resources/js/lib/nav-active.ts), not from `isCurrentUrl`, so sub-pages like `/operations/import` keep their section highlighted.
No `dark:` variants and no literals in sidebar files: `npm run check:ui` guards them (they are on the list in scripts/check-ui-literals.mjs). Check the icon mode, the empty year state and both themes in the kitchen sink at `/dev/ui/sidebar` (`local` only).

## Views take values from app.css, not from literals

Tokens live in resources/css/app.css. Named classes in `@layer components` cover repeated layout: `.month-plan-grid`, `.column-header`, `.cell-input`, `.month-plan-editbar`, `.month-strip-dot*`, `.month-plan-summary-grid`, `.segmented-control*`. When a view needs a new grid, size or label style, add a named class there instead of an arbitrary value (`text-[11px]`, `grid-cols-[1fr_auto]`) in the template.
A value switch (one option selected, as in put aside / take) is `SegmentedControl` (tone `primary` for a form choice, `neutral` for a setting saved immediately), not a `Button` with `aria-pressed`.
The month plan view is checked by tests/Unit/MonthPlanHardcodedUiTest.php (part of `composer ci:check`); it lists the view files and fails with file:line. Add a new month plan component to its file list. The month plan is one table with a Status column (ADR-0042); the plan cell in edit mode is the `.cell-input` class on a native input, not `ui/Input` (a component class loses to the `ui/Input` utilities). The kitchen sink at /dev/month-plan (`APP_ENV=local` only) renders the real components (`MonthPlanTable`, `MonthPlanStatPanel`, `MonthPlanEditBar`, `MonthSummaryCard`, `BudgetMonthPicker`, title row, empty states) in the 7-state matrix from resources/js/lib/month-plan-fixtures.ts: month strip with every dot state, four stat panels states (negative, live, final, pace in norm / ahead / no percent / future), table in read, edit, error, past and empty-group variants, sticky edit bar, loading spinners. Add a new month plan state there; it is on the lists of check:ui and `MonthPlanHardcodedUiTest`.

## Operations list: named classes, one primary action, visible reason for a disabled action

The Operations view (ADR-0038, ADR-0039) uses one grid, `.operations-grid`, for the rows (`.operations-row`, hover `secondary`) and the day row (`.operations-day-row`), with no column header row and no date column: all three sections are grouped by day (`groupRowsByDay`; financial operations add the server day sum through `groupOperationsByDay`, transfers and capital movements have a day row without a sum), inside `ListSurface` `flush` (rows reach the card edges and carry `px-4`); do not rebuild them from utilities. Row actions are one "⋯" menu, `OperationRowMenu` (edit, delete through `ConfirmActionDialog` with `v-model:open`, because the menu content unmounts on close); the filter bar has a full-width search, the presets and "Filtry" on the right and no result counter. A day sum comes only from the `operationDaySums` prop (server, whole day, filters applied), never from the rows of the page. Day sum and operation amount: `+` in `success` or `−` in `foreground` (`formatOperationDaySum` / `operationDaySumClass`, `formatFinancialOperationAmount` / `financialOperationAmountClass`), not `amountToneClass`; amounts carry "zł" (transfers and capital movements through `formatMoney`) and the day label comes from `formatDayLabel`.
The sections are tabs: `SegmentedControl` neutral with `count` and the `segmented-control-tabs` class (no border), the choice in `?section=`. Build every `/operations` query only with the helper from `resources/js/lib/operations-list-query.ts`, which keeps `section` (filters, "Wyczyść filtry" and tabs lose it otherwise). The add control is one primary "Dodaj operację" with a menu; an unavailable item shows its reason as text in the item and stays focusable: `as-child` with `aria-disabled` on the child and a `@select` that calls `preventDefault()`, because reka skips `disabled` items during keyboard navigation (and its own `aria-disabled` overrides one passed as an attribute). The kitchen sink at `/dev/operations` (`local` only) renders the view's 7-state matrix from `resources/js/lib/operations-fixtures.ts`; add new states there (tabs, menu, empty filter result, silenced section, empty section, loading). `npm run check:ui` also catches opacity literals in the form `/[0.03]`.
The reason a create action is disabled is visible text referenced by `aria-describedby`, never `title` on a disabled `Button` (`pointer-events-none` hides it and it is not a description). A domain page title row has at most one primary `Button`; the other actions are `outline`, all with an icon.

## Import preview: named classes, the `pair` tint, no lost row alert

The import preview uses `.import-preview-grid`, `.import-preview-header-row` (hidden below `xl`, caption through `table-header`) and `.import-preview-row` with the modifiers `-duplicate` (warning tint) and `-pair` (own transfer pair candidate); do not rebuild them from utilities. The `pair` token is only the tint of a pair candidate row, never text, amounts or the operation type (that is `transfer`).
A new alert inside a row needs a new kind in `importRowHasNotes` (resources/js/lib/import-preview-rows.ts); otherwise the notes block does not render and the alert is silently lost.
`CategorySelect` and `AccountSelect` used without a `<Label for>` get an `ariaLabel`; the reka `Select` trigger has no other accessible name.
The kitchen sink at `/dev/import-preview` (`local` only) renders the 7-state matrix from `resources/js/lib/import-preview-fixtures.ts`; add new states there.
A category suggestion (`row.category_suggestion`) enters row state only through `suggestedCategoryId` (resources/js/lib/import-preview-rows.ts); it never changes the type or `selected`. The marker is muted text under `CategorySelect` ("✦ podpowiedź"), linked by `aria-describedby`, shown only while the value equals the suggestion; it is not a `Badge` or an alert.

## Form dialogs are built on FormField and the ADR-0040 contract

A form field in a dialog is `FormField` (34 px control, label, hint, error with `id` and `aria-describedby`, `money` variant with "zł"), not a hand-written `div.grid > Label + input + InputError`. A choice in a form is `SegmentedControl` `block` with a `name` (exception: when the payload is a conversion flag, as in the expense and income dialogs, the choice has no `name`, ADR-0041). "Anuluj" is `outline`; saving shows a spinner and blocks closing. Focus is a 2 px `outline` with `outline-solid` (never `ring-3`/`ring-ring/50`). Dialog shell tokens (460 px, `radius-xl`, `shadow-dialog`, `scrim`) come from `ui/dialog`; do not override them per dialog. Add new dialog states to the kitchen sink at `/dev/ui/dialogs` (`local` only). `ui-contract.test.ts` fails when `pages/Budget.vue` or `BudgetCategoryRowActions.vue` import `DialogContent`; use `ConfirmActionDialog`. A reading before saving (To Spend, category limit, balance) is a `FormPreview` with rows built by `lib/operation-dialog-preview.ts`: "before" is the stored state, so an edit without changes shows equal values, and warnings stand in the block under the reading they concern (ADR-0041), not in an `Alert`. A row of two fields is `.form-field-row`, read-only text is `.form-readonly-text`. An operation amount is bad when unreadable or negative (`operations.amount_invalid` under the field, "Zapisz" disabled); the server rejects zero. Operation dialogs get this rule from `useOperationAmountInput`, so do not copy it into a dialog. States of the operation dialogs are in `/dev/ui/dialogs`. Details: ADR-0040, ADR-0041 and `.docs/design-system.md`.
