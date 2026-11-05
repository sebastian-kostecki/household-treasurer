---
paths:
    - 'resources/js/**/*.vue'
---

# Js

## Colours come from tokens; only money and alerts carry meaning

Use token utilities (bg-primary, text-destructive, text-warning, text-success, text-muted-foreground), never hex values or Tailwind palette colours (red-500, amber-700).
primary = action (ink); brand = identity and navigation, never status; brand-text = links and strokes in the brand colour; success = income; destructive = exceeded (negative balance, over limit, negative Month Remaining) and delete actions; warning = within bounds but close to breaking.
Category colour is identity only (CategoryChip tile at 15%), never status. Full table: .docs/design-system.md.

## Amounts go through formatMoney with tabular-nums

Money is integer Minor units. Display with formatMoney from @/lib/format (1 234,56 zł), right-aligned, tabular-nums; edit with minorUnitsToMoneyInput / parseMoneyInputToMinorUnits. Do not format money with toFixed or Intl in components.

## Reuse surface building blocks before writing new markup

Check ListSurface, CategoryChip, CategoryMeterRow, OperationTypeTile, IconAction (icon button with tooltip and aria-label), IndexEmptyState, Heading, AccountIdentityBlock and SegmentedControl first. Surfaces follow ADR-0003: grey floor, white rounded-2xl cards with border/60, no resting shadows. Do not edit components/ui/* for a single screen.

## Non-blocking warnings use the warning Alert variant

Warnings that do not block saving (over limit, over Month Remaining, negative To Spend after an entry, negative balance, import duplicates) use <Alert variant="warning"> or bg-warning/10. variant="destructive" is only for real errors (AlertError) and delete actions; field errors use InputError. Operation type tiles use the refund and transfer tokens.

## Bind the reka-ui Checkbox with v-model, never :checked

reka-ui `Checkbox` takes `modelValue` and emits `update:modelValue`; `:checked`, `@update:checked` and `v-model:checked` are radix-vue names and are silently ignored, leaving an uncontrolled box. Use `v-model` (see Dashboard.vue) or `:model-value` with `@update:model-value`. `resources/js/ui-contract.test.ts` fails on the old names; its exception list (issue #186) only shrinks.

## Confirmations use ConfirmActionDialog

Actions that need confirmation (delete, archive, restore) use `ConfirmActionDialog` with a Wayfinder `.form(...)` and a `trigger` slot, not a hand-written `Dialog` + `Form`. It gives a spinner on the confirm button, blocks closing while the request runs and shows server errors in the dialog.

## Responsive lists are one markup on a grid, inside ListSurface

Do not build a second mobile tree hidden with `md:hidden`. Use one row on a grid-template-areas class (`.operations-grid`, `.budget-category-grid`) inside a single `ListSurface`, with a `hidden md:grid` header row. States of the Budget page (the 7-state matrix) are shown in the kitchen sink at `/dev/kitchen-sink/budget` (`local` only); add a new Budget state there.

## Tokens and shared components come first; the UI literal check guards cleaned views

Values live in resources/css/app.css (:root / .dark, published by @theme inline). Base components are in components/ui/*, domain ones in components/. Before a new component check StatTile, IndexEmptyState and the "Gotowe elementy" list in .docs/design-system.md.
Signed amount colour goes through amountToneClass, column labels through the column-caption class (the only caption recipe; `.column-header` is an alias kept for the month plan), severity pills through Badge (variants warning / destructive-soft).
Archived rows: no tone, except a term deposit balance < 0 (FR-TD-08) — then `amountToneClass`.
No colour literals, palette classes or arbitrary px/rem values in views; npm run check:ui guards the cleaned files (add newly cleaned views to scripts/check-ui-literals.mjs). Component sandboxes: /dev/ui/dashboard, /dev/ui/accounts, /dev/ui/term-deposits, /dev/operations and /dev/import-preview.
Dropdowns: use the native `.native-select` class for simple enums without icons; use the reka-ui `Select` when an option shows an icon or logo (e.g. AccountBankSelect). Both are `rounded-xl`.

## Sidebar

Sidebar colours are `--sidebar-*` tokens from resources/css/app.css (roles in .docs/design-system.md, "Sidebar"); there is no `--sidebar` token, the background is `--sidebar-background`.
Whatever goes into the `SidebarHeader` / `SidebarFooter` slots is built from `SidebarMenuButton` or handles `group-data-[collapsible=icon]` itself, otherwise it breaks in the 3rem collapsed strip.
The active menu item uses `sidebar-primary` (also on hover); the app name, the user and the selected year use `sidebar-strong`; the sidebar has no `variant="inset"`, it sits flush against the content. The active menu item comes from `isCurrentSection` (resources/js/lib/nav-active.ts), not from `isCurrentUrl`, so sub-pages like `/operations/import` keep their section highlighted.
No `dark:` variants and no literals in sidebar files: `npm run check:ui` guards them (they are on the list in scripts/check-ui-literals.mjs). Check the icon mode, the empty year state and both themes in the kitchen sink at `/dev/ui/sidebar` (`local` only).

## Views take values from app.css, not from literals

Tokens live in resources/css/app.css. Named classes in `@layer components` cover repeated layout: `.month-plan-grid`, `.column-header`, `.section-label`, `.segmented-control*`. When a view needs a new grid, size or label style, add a named class there instead of an arbitrary value (`text-[11px]`, `grid-cols-[1fr_auto]`) in the template.
A value switch (one option selected, as in put aside / take) is `SegmentedControl` (tone `primary` for a form choice, `neutral` for a setting saved immediately), not a `Button` with `aria-pressed`.
The month plan view is checked by tests/Unit/MonthPlanHardcodedUiTest.php (part of `composer ci:check`); it lists the view files and fails with file:line. Add a new month plan component to its file list. The kitchen sink at /dev/month-plan (`APP_ENV=local` only) renders every state of the view from resources/js/lib/month-plan-fixtures.ts; extend it when you add a state.

## Operations list: named classes, one primary action, visible reason for a disabled action

The Operations view uses `.operations-grid`, `.operations-header-row` (hidden below md, caption through `column-caption`) and `.operations-row` (hover `foreground/3%`); do not rebuild them from utilities. The kitchen sink at `/dev/operations` (`local` only) renders the view's 7-state matrix from `resources/js/lib/operations-fixtures.ts`; add new states there. `npm run check:ui` also catches opacity literals in the form `/[0.03]`.
The reason a create action is disabled is visible text referenced by `aria-describedby`, never `title` on a disabled `Button` (`pointer-events-none` hides it and it is not a description). A domain page title row has at most one primary `Button`; the other actions are `outline`, all with an icon.

## Import preview: named classes, the `pair` tint, no lost row alert

The import preview uses `.import-preview-grid`, `.import-preview-header-row` (hidden below `xl`, caption through `column-caption`) and `.import-preview-row` with the modifiers `-duplicate` (warning tint) and `-pair` (own transfer pair candidate); do not rebuild them from utilities. The `pair` token is only the tint of a pair candidate row, never text, amounts or the operation type (that is `transfer`).
A new alert inside a row needs a new kind in `importRowHasNotes` (resources/js/lib/import-preview-rows.ts); otherwise the notes block does not render and the alert is silently lost.
`CategorySelect` and `AccountSelect` used without a `<Label for>` get an `ariaLabel`; the reka `Select` trigger has no other accessible name.
The kitchen sink at `/dev/import-preview` (`local` only) renders the 7-state matrix from `resources/js/lib/import-preview-fixtures.ts`; add new states there.
A category suggestion (`row.category_suggestion`) enters row state only through `suggestedCategoryId` (resources/js/lib/import-preview-rows.ts); it never changes the type or `selected`. The marker is muted text under `CategorySelect` ("✦ podpowiedź"), linked by `aria-describedby`, shown only while the value equals the suggestion; it is not a `Badge` or an alert.
