---
paths:
    - 'resources/js/**/*.vue'
---

# Js

## Colours come from tokens; only money and alerts carry meaning

Use token utilities (bg-primary, text-destructive, text-warning, text-success, text-muted-foreground), never hex values or Tailwind palette colours (red-500, amber-700).
primary = action (ink); brand = identity, navigation, document state and the one approving action (ADR-0042), never money status; brand-text = links and strokes in the brand colour; success = income; destructive = exceeded (negative balance, over limit, negative Month Remaining) and delete actions, never an expense amount (in Operations an expense amount is `foreground`, ADR-0038; only the expense type tile is `destructive-soft`, ADR-0039); warning = within bounds but close to breaking.
Exception (ADR-0056): `accent` also marks a confirmation that carries no amount, only as `<Alert variant="ok">` (as Badge `ok` already does); never `text-success` for it, because `success` is income.
Category colour is identity only (CategoryChip tile at 15%), never status. Full table: context/foundation/design-system.md.

## Amounts go through formatMoney with tabular-nums

Money is integer Minor units. Display with formatMoney from @/lib/format (1 234,56 zł) in metrics and dialogs, right-aligned, tabular-nums. In every table (a `ListSurface` with rows on a grid: cells, day rows, sum rows and captions inside a row) use formatMoneyAmount or the signed variant without the unit, with the `amount` style (`amount-sum` in total rows), and put "Kwoty w zł" in the panel footer (ADR-0037, ADR-0058); the only exception is the Trend in Reports, whose tables show whole zloty with `formatWholeMoneyAmount` and the footer "Kwoty w zł, zaokrąglone do pełnych złotych" (ADR-0077). "zł" stays in measures (`StatPanel`), dialogs and `FormField`, alert cards, the command palette and running text; edit with minorUnitsToMoneyInput / parseMoneyInputToMinorUnits. Do not format money with toFixed or Intl in components.

## Reuse surface building blocks before writing new markup

Check ListSurface, CategoryChip, CategoryMeterRow, OperationTypeTile, IconAction (icon button with tooltip and aria-label), IndexEmptyState, Heading, AccountAvatar and SegmentedControl first. Also StatPanel and ExecutionMeter. Surfaces follow ADR-0037: grey floor, panels are `card` with a full `border` and `rounded-lg`, no resting shadows. Do not edit components/ui/* for a single screen.
The Account or Term Deposit mark is `AccountAvatar` (ADR-0054): the bank logo (`mark_url`) on `muted` with `radius-sm`, `object-contain`, `aria-hidden` and `alt=""`, falling back to the short text mark from `accountShortMark` in `@/lib/account-identity` in the `mark-label` style (ADR-0047), also after an image error. A cash Account shows the `Coins` icon everywhere, also with a `mark_url` (ADR-0058), and Other shows `Building2`. Size by role: `xs` in a text line (notices, filter chip; the financial operation row shows the Account name as text, no avatar), `sm` in pick lists (`AccountSelect`, `AccountBankSelect`, which shows only the short mark), `md` in tables and rows. Pass narrow props (`type`, `bank`, `mark_url`), never `v-bind` of the whole object. A new Bank gets its short mark in `account-identity.ts` and its logo in `Bank::markPath()` in the same commit.

## Non-blocking warnings use the warning Alert variant; a reading note uses `note`

A one-sentence note about a reading shown on the same page, under the metrics panel or under a table (month plan pace, drawing past available liquidity), uses `<Alert variant="note">` (ADR-0042). Other warnings that do not block saving (over limit, over Month Remaining, negative To Spend after an entry, negative balance, import duplicates) use <Alert variant="warning"> or bg-warning/10. The Alert variants are default, destructive, warning, note, danger and ok; `ok` is a confirmation without an amount, such as "link sent" (ADR-0056). Exception: in form dialogs of operations the warning is `text-warning` text inside the before → after preview block, under the reading it concerns, not an Alert (ADR-0041). variant="destructive" is only for real errors (AlertError) and delete actions; field errors use InputError. Operation type tiles follow ADR-0039: expense `destructive-soft` + `destructive`, income `success/10` + `success`, refund `warning-soft` + `refund`, own transfer `transfer`; the tile marks the type, the expense amount stays `foreground`.

## Bind the reka-ui Checkbox with v-model, never :checked

reka-ui `Checkbox` takes `modelValue` and emits `update:modelValue`; `:checked`, `@update:checked` and `v-model:checked` are radix-vue names and are silently ignored, leaving an uncontrolled box. Use `v-model` (see CategoryFormDialog.vue) or `:model-value` with `@update:model-value`. `resources/js/ui-contract.test.ts` fails on the old names; its exception list (issue #186) only shrinks.
A setting saved at once ("Pokaż zarchiwizowane") is a `Switch` from components/ui/switch, also bound with `v-model`.

## Confirmations use ConfirmActionDialog

Actions that need confirmation (delete, archive, restore) use `ConfirmActionDialog` with a Wayfinder `.form(...)` and a `trigger` slot, not a hand-written `Dialog` + `Form`. It gives a spinner on the confirm button, blocks closing while the request runs and shows server errors in the dialog.

## Responsive lists are one markup on a grid, inside ListSurface

Do not build a second mobile tree hidden with `md:hidden`. Use one row on a grid-template-areas class (`.operations-grid`, `.budget-category-grid`, `.accounts-grid`, `.term-deposits-grid`) inside a single `ListSurface`, with a `hidden md:grid` header row (the Operations list has no header row, ADR-0039). A grid whose fixed columns do not fit next to the sidebar at `md` switches at a wider breakpoint: `.term-deposits-grid` and `.import-preview-grid` at `xl` (ADR-0051). States of the Budget page (the 7-state matrix, without a month strip: execution rows within plan, income over 100%, overrun with the `przekroczono` Badge, archived, running and even annual, both with the year mark; draft with a Type column; empty list; no budget; Switch on/off; StatPanel with 3 and 4 metrics) are shown in the kitchen sink at `/dev/kitchen-sink/budget` (`local` only); add a new Budget state there.
The Dashboard (ADR-0043, ADR-0045) follows the same rule: the block "Budżet <year>" (a title row with the Switch and "Otwórz budżet", the plan measures and the panels side by side, ADR-0050) holds two `ListSurface` tables, Dochody and Wydatki, on the `.category-meter-row` grid (Kategoria | Plan bieżący | Realizacja | Pozostało | Wykonanie, with a sum row and no tabs, group rows or Status column; the columns switch by the table's own width, `.category-meter-table` from 38rem, not by `md`, because from `lg` the block sits in the narrow main column), not a second mobile tree, and the Accounts and Term Deposits panels are one markup at every width (no accordion). New Dashboard states go to `/dev/ui/dashboard`.

## Settings pages follow ADR-0049

A settings section is `SettingsPanel` with its fields in `FormField`; the `Form` wraps the whole panel and the action sits in the panel footer. The danger zone is `<Alert variant="danger">` plus a `Button` `outline-destructive` that opens a `ConfirmActionDialog`. Texts come from `t.settings.*` (`lang/pl/ui.php`), no English literals. `Heading` stays only in the two-factor and passkeys sections until their own change. The theme choice is `SegmentedControl` with `tone="neutral"`. The settings layout owns the visible `h1` (`page-title`); settings pages have none.

## Tokens and shared components come first; the UI literal check guards cleaned views

Values live in resources/css/app.css (:root / .dark, published by @theme inline). Base components are in components/ui/*, domain ones in components/. Before a new component check IndexEmptyState and the "Gotowe elementy" list in context/foundation/design-system.md.
Signed amount colour goes through amountToneClass, column labels through the `table-header` style (the only column caption recipe; capitals `label-caps` only above metrics and in the sidebar), severity and status pills through Badge (variants warning / destructive-soft (also an invalid import row, ADR-0052) / ok / brand / neutral / outline; `brand` also marks document state, such as an approved month plan, ADR-0042; `outline` marks a closed past month, „Końcowy”), the elapsed-year mark in ExecutionMeter in `brand-text`.
Archived rows: no tone, except a term deposit balance < 0 (FR-TD-08) and an account balance < 0 (FR-ACC-10, ADR-0044) — then `amountToneClass`.
No colour literals, palette classes or arbitrary px/rem values in views; npm run check:ui guards the cleaned files (add newly cleaned views to scripts/check-ui-literals.mjs). Component sandboxes: /dev/ui/dashboard, /dev/ui/accounts, /dev/ui/settings, /dev/ui/term-deposits, /dev/ui/import, /dev/operations, /dev/import-preview and /dev/month-plan.
The Dashboard (ADR-0043) is guarded the same way by tests/Unit/DashboardHardcodedUiTest.php (part of `composer ci:check`): it lists the Dashboard files and fails on pre-Atrament typography, borders and opacity. The warning card is the `.dashboard-notice` class in `@layer components`: an icon tile in the severity colour, the kind label, the identity and the value on the right in the severity colour (ADR-0045, ADR-0050), not a utility-tinted `Card`. A new Dashboard notice kind adds its label, identity and value to the one presentation function in `@/lib/dashboard-notice-presentation`, not new card markup.
A view setting remembered in the browser (the Dashboard Switch "Postęp roku", key `dashboard-year-elapsed`) has a helper with try/catch in `@/lib` that logs the error with its cause; the page holds the state and passes it to the panel as `v-model`, the panel never reads `localStorage`.
The Accounts view (ADR-0044) is guarded the same way by tests/Unit/AccountsHardcodedUiTest.php: it lists the Accounts files and fails on pre-Atrament typography, borders and opacity (an archived row is muted, never `opacity-60`). New Accounts states go to `/dev/ui/accounts`.
The Term Deposits view (ADR-0051) is guarded the same way by tests/Unit/TermDepositsHardcodedUiTest.php: it lists the Term Deposits files and fails on pre-Atrament typography, borders and opacity. New Term Deposits states go to `/dev/ui/term-deposits`.
Import step 1 (ADR-0048) is guarded the same way by tests/Unit/ImportStartHardcodedUiTest.php. New step 1 states go to `/dev/ui/import`.
Import step 2 (ADR-0052) is guarded by tests/Unit/ImportPreviewHardcodedUiTest.php. New step 2 states go to `/dev/import-preview`.
Dropdowns: use the native `.native-select` class for simple enums without icons; use the reka-ui `Select` when an option shows an icon or mark (e.g. AccountBankSelect). Both are 32px (`--control-height`) and `rounded-md`.

## Views take values from app.css, not from literals

Tokens live in resources/css/app.css. Named classes in `@layer components` cover repeated layout: `.operations-grid`, `.budget-category-grid`, `.accounts-grid`, `.term-deposits-grid`, `.import-preview-grid`, `.month-plan-grid`, `.segmented-control*` (the per-view class lists are in js-operations.md, js-import.md and js-month-plan.md). When a view needs a new grid, size or label style, add a named class there instead of an arbitrary value (`text-[11px]`, `grid-cols-[1fr_auto]`) in the template.
A value switch (one option selected, as in put aside / take) is `SegmentedControl` (tone `primary` for a form choice, `neutral` for a setting saved immediately), not a `Button` with `aria-pressed`.
