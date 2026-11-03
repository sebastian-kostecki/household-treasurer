---
paths:
    - 'resources/js/**/*.vue'
---

# Js

## Colours come from tokens; only money and alerts carry meaning

Use token utilities (bg-primary, text-destructive, text-warning, text-success, text-muted-foreground), never hex values or Tailwind palette colours (red-500, amber-700).
primary = action and on-track meter; success = income; destructive = exceeded (negative balance, over limit, negative Month Remaining) and delete actions; warning = within bounds but close to breaking.
Category colour is identity only (CategoryChip tile at 15%), never status. Full table: .docs/design-system.md.

## Amounts go through formatMoney with tabular-nums

Money is integer Minor units. Display with formatMoney from @/lib/format (1 234,56 zł), right-aligned, tabular-nums; edit with minorUnitsToMoneyInput / parseMoneyInputToMinorUnits. Do not format money with toFixed or Intl in components.

## Reuse surface building blocks before writing new markup

Check ListSurface, CategoryChip, CategoryMeterRow, OperationTypeTile, IconAction (icon button with tooltip and aria-label), IndexEmptyState, Heading, AccountIdentityBlock and SegmentedControl first. Surfaces follow ADR-0003: grey floor, white rounded-2xl cards with border/60, no resting shadows. Do not edit components/ui/* for a single screen.

## Non-blocking warnings use the warning Alert variant

Warnings that do not block saving (over limit, over Month Remaining, negative To Spend after an entry, negative balance, import duplicates) use <Alert variant="warning"> or bg-warning/10. variant="destructive" is only for real errors (AlertError) and delete actions; field errors use InputError. Operation type tiles use the refund and transfer tokens.

## Bind the reka-ui Checkbox with v-model, never :checked

reka-ui `Checkbox` takes `modelValue` and emits `update:modelValue`; `:checked`, `@update:checked` and `v-model:checked` are radix-vue names and are silently ignored, leaving an uncontrolled box. Use `v-model` (see Dashboard.vue) or `:model-value` with `@update:model-value`. `resources/js/ui-contract.test.ts` fails on the old names; its exception list (issue #186) only shrinks.

## Responsive lists are one markup on a grid, inside ListSurface

Do not build a second mobile tree hidden with `md:hidden`. Use one row on a grid-template-areas class (`.operations-grid`, `.budget-category-grid`) inside a single `ListSurface`, with a `hidden md:grid` header row. States of the Budget page (the 7-state matrix) are shown in the kitchen sink at `/dev/kitchen-sink/budget` (`local` only); add a new Budget state there.

## Tokens and shared components come first; the UI literal check guards cleaned views

Values live in resources/css/app.css (:root / .dark, published by @theme inline). Base components are in components/ui/*, domain ones in components/. Before a new component check StatTile, IndexEmptyState and the "Gotowe elementy" list in .docs/design-system.md.
Signed amount colour goes through amountToneClass, column labels through the column-caption class (the only caption recipe; `.column-header` is an alias kept for the month plan), severity pills through Badge (variants warning / destructive-soft).
No colour literals, palette classes or arbitrary px/rem values in views; npm run check:ui guards the cleaned files (add newly cleaned views to scripts/check-ui-literals.mjs). Component sandbox: /dev/ui/dashboard.

## Views take values from app.css, not from literals

Tokens live in resources/css/app.css. Named classes in `@layer components` cover repeated layout: `.month-plan-grid`, `.column-header`, `.section-label`, `.segmented-control*`. When a view needs a new grid, size or label style, add a named class there instead of an arbitrary value (`text-[11px]`, `grid-cols-[1fr_auto]`) in the template.
A value switch (one option selected, as in put aside / take) is `SegmentedControl` (tone `primary` for a form choice, `neutral` for a setting saved immediately), not a `Button` with `aria-pressed`.
The month plan view is checked by tests/Unit/MonthPlanHardcodedUiTest.php (part of `composer ci:check`); it lists the view files and fails with file:line. Add a new month plan component to its file list. The kitchen sink at /dev/month-plan (`APP_ENV=local` only) renders every state of the view from resources/js/lib/month-plan-fixtures.ts; extend it when you add a state.

## Operations list: named classes, one primary action, visible reason for a disabled action

The Operations view uses `.operations-grid`, `.operations-header-row` (hidden below md, caption through `column-caption`) and `.operations-row` (hover `foreground/3%`); do not rebuild them from utilities. The kitchen sink at `/dev/operations` (`local` only) renders the view's 7-state matrix from `resources/js/lib/operations-fixtures.ts`; add new states there. `npm run check:ui` also catches opacity literals in the form `/[0.03]`.
The reason a create action is disabled is visible text referenced by `aria-describedby`, never `title` on a disabled `Button` (`pointer-events-none` hides it and it is not a description). A domain page title row has at most one primary `Button`; the other actions are `outline`, all with an icon.
