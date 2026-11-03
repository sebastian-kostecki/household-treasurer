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

## Views take values from app.css, not from literals

Tokens live in resources/css/app.css. Named classes in `@layer components` cover repeated layout: `.month-plan-grid`, `.column-header`, `.section-label`, `.segmented-control*`. When a view needs a new grid, size or label style, add a named class there instead of an arbitrary value (`text-[11px]`, `grid-cols-[1fr_auto]`) in the template.
A value switch (one option selected, as in put aside / take) is `SegmentedControl` (tone `primary` for a form choice, `neutral` for a setting saved immediately), not a `Button` with `aria-pressed`.
The month plan view is checked by tests/Unit/MonthPlanHardcodedUiTest.php (part of `composer ci:check`); it lists the view files and fails with file:line. Add a new month plan component to its file list. The kitchen sink at /dev/month-plan (`APP_ENV=local` only) renders every state of the view from resources/js/lib/month-plan-fixtures.ts; extend it when you add a state.
