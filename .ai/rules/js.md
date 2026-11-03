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

Check ListSurface, CategoryChip, CategoryMeterRow, OperationTypeTile, IconAction (icon button with tooltip and aria-label), IndexEmptyState, Heading and AccountIdentityBlock first. Surfaces follow ADR-0003: grey floor, white rounded-2xl cards with border/60, no resting shadows. Do not edit components/ui/* for a single screen.

## Non-blocking warnings use the warning Alert variant

Warnings that do not block saving (over limit, over Month Remaining, negative To Spend after an entry, negative balance, import duplicates) use <Alert variant="warning"> or bg-warning/10. variant="destructive" is only for real errors (AlertError) and delete actions; field errors use InputError. Operation type tiles use the refund and transfer tokens.

## Tokens and shared components come first; the UI literal check guards cleaned views

Values live in resources/css/app.css (:root / .dark, published by @theme inline). Base components are in components/ui/*, domain ones in components/. Before a new component check StatTile, IndexEmptyState and the "Gotowe elementy" list in .docs/design-system.md.
Signed amount colour goes through amountToneClass, column labels through the column-caption class, severity pills through Badge (variants warning / destructive-soft).
No colour literals, palette classes or arbitrary px/rem values in views; npm run check:ui guards the cleaned files (add newly cleaned views to scripts/check-ui-literals.mjs). Component sandbox: /dev/ui/dashboard.
