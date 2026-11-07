<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Widok Operacji w systemie „C · Atrament i morski”

- **Plan**: context/changes/ui-operations-atrament/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-05
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 7 observations (F10 found during triage)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Automated checks (run 2026-11-05)

- `grep -l "ADR-0038" …`: 3/3 files ✅
- `npm run check` ✅, `npm run check:ui` ✅, `npm run types:check` ✅, `npm test` 197/197 ✅
- Operations tests on SQLite (Sail): 51 passed ✅
- The same tests on PostgreSQL: 51 passed ✅ (`NfrPerf01Test` falls under the group excluded in `phpunit.xml`; run separately with `--configuration=phpunit.nfr.xml`: passes). The first run had 7 failures from a concurrent run on the shared `testing` database (`relation "users" does not exist`); the re-run passed.
- `composer ci:check` in Sail (PostgreSQL): 875 passed, 2 skipped ✅
- `vendor/bin/sloppy ci` (host): 0 findings ✅

## Findings

### F1 — Disabled "Dodaj operację" menu items are unreachable by keyboard and screen reader

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/OperationsPageActions.vue:84-141
- **Detail**: "Transfer własny" and "Ruch kapitału" get `:disabled`. reka-ui skips `[data-disabled]` items during arrow navigation (`MenuContentImpl.js`, selector `:not([data-disabled])`), so a keyboard user never lands on them, and a screen reader never announces the reason or `aria-describedby`. When the item is reached in browse mode, the reason is read twice: once in the name (child of the item) and once through `aria-describedby`. The plan foresaw this skipping ("Critical Implementation Details") and settled on visible text only. That covers a sighted user, but manual check 4.7 ("działa z klawiatury; wyłączona pozycja pokazuje powód") holds only partly.
- **Fix A ⭐ Recommended**: Keep the item focusable: drop `disabled`, set `aria-disabled="true"`, call `event.preventDefault()` in `@select` without the emit, move the styles from `data-[disabled]:` to `aria-disabled:`, and give the label `aria-labelledby` so the reason stays only in `aria-describedby`.
  - Strength: the reason is announced on focus, matching the intent of `.ai/rules/js.md:63`.
  - Tradeoff: the pattern differs from the other menus (`BudgetCategoryRowActions.vue`, which use `disabled`), and the item stays a stop when moving through the menu.
  - Confidence: MED — reka passes `aria-*` through to the item, but the `@select` + `preventDefault` behaviour needs checking in the sink.
  - Blind spot: an NVDA/VoiceOver run has not been checked.
- **Fix B**: Keep `disabled` and show the reason outside the menu as well (for example a `meta` note under the title row when an action is unavailable), and drop the duplicating `aria-describedby`.
  - Strength: a smaller change; the menu stays consistent with the other menus.
  - Tradeoff: the reason appears in two places, and the title row grows.
  - Confidence: MED — this is a layout decision against the mockup.
  - Blind spot: whether the mockup allows a note in the title row.
- **Decision**: FIXED via Fix A — `as-child` + `aria-disabled` on the child element (reka overwrites `aria-disabled` passed as an attribute, so this has to go through `as-child`), `@select` with `preventDefault()`, `aria-labelledby` on the label. The rule in `.ai/rules/js.md:63` is updated. Checked in Playwright: the item gets arrow-key focus, Enter leaves the menu open with no dialog, and the available items open "Nowy transfer własny" / "Dodaj wydatek".

### F2 — `OperationsActiveFilterChips` has several root elements

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/OperationsActiveFilterChips.vue:251
- **Detail**: The root changed from a `<div>` to `<template v-if>` with the chips, a clear button and a note as siblings, so they flow into the `OperationsFilters` flex row. This breaks the project rule "Vue components must have a single root element". Attribute fallthrough no longer works, and any future `class` or `aria-*` on the component will trigger a Vue warning and be lost.
- **Fix**: Wrap the content in `<div v-if="chips.length > 0" class="contents">`. The layout stays the same and the component gets a single root.
- **Decision**: FIXED — root is `<div v-if="chips.length > 0" class="contents">`.

### F3 — The `section` assertion in the financial-operations links proves nothing

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/OperationsSectionTest.php:43
- **Detail**: The plan asked to check `section` in `operations.links`. The test asserts `operations.links.0.url === null`, which is the "previous" link on page 1 and is always null. The behaviour is correct (`withQueryString()`), but a regression in the financial-operations paginator would not be caught.
- **Fix**: In this test, also create 51 financial operations and assert `operations.next_page_url` contains `section=own_transfers`, in place of `links.0.url`.
- **Decision**: FIXED — the test creates 51 financial operations (one shared budget, account and category) and checks that `operations.next_page_url` contains `section=own_transfers`. Passes on SQLite and PostgreSQL.

### F4 — An empty `operationDaySums` reaches the client as `[]`, not `{}`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Controllers/OperationsController.php:65-71
- **Detail**: For a suppressed section or an empty page, PHP `[]` serialises to the JSON array `[]`, while `Operations.vue` types it as `Record<string, number>`. It works at runtime (`daySums[date] ?? 0`), but it is a quiet mismatch in the props contract (AGENTS.md, "Inertia props contract").
- **Fix**: When the map is empty, pass `(object) []` (or always `(object) $operationDaySums`). The test with `where('operationDaySums', [])` can stay, because assertInertia decodes `{}` as `[]`.
- **Decision**: FIXED — `'operationDaySums' => (object) $operationDaySums` (`OperationsController.php:202`). The 51 Operations tests pass on SQLite.

### F5 — `OperationsListPanel.vue` and the `ListSurface` footer slot are outside the plan's file list

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/components/OperationsListPanel.vue, resources/js/components/ListSurface.vue:40
- **Detail**: A new 380-line component holds the tabs, the panel, the 5 empty states and the section bodies that the plan placed in `Operations.vue` §4–5. It is a sound reorganisation (the sink renders the real panel; it removed about 590 lines from the sink), adds no behaviour, and `check:ui` covers it. `ListSurface` gained an optional `footer` slot for the planned footer below pagination. Neither the plan nor Progress records this.
- **Fix**: Add a one-line addendum to Phase 4 in the plan (the panel extracted for the sink, plus the `footer` slot in `ListSurface`).
- **Decision**: FIXED — addendum in Phase 4 of the plan (`OperationsListPanel.vue`, the `footer` slot in `ListSurface`).

### F6 — No 390 px screenshot of the open "Dodaj operację" menu

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/ui-operations-atrament/screenshots/
- **Detail**: `menu-open-{light,dark}-1280.png` exist, but the plan (Phase 4 §7, "w obu motywach przy 1280 i 390 px, w tym … otwarte menu") also covers 390 px. On a phone the menu with two-line items is the most likely place for overflow.
- **Fix**: Add `menu-open-{light,dark}-390.png` from `/dev/operations`.
- **Decision**: FIXED — `menu-open-{light,dark}-{1280,390}.png` from `/dev/operations` after F1 (focus on the unavailable "Ruch kapitału"; at 390 px the menu fits within 22–310 px, no horizontal scroll).

### F7 — Manual checks 3.6 and 4.6–4.8 ticked without recorded evidence

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/ui-operations-atrament/plan.md:441,455-457
- **Detail**: The screenshots cover 4.5. 3.6 is backed indirectly by `OperationsDaySumsTest` (refund counted net), and 4.6 by the tests and helper for `section`. 4.8 (a link with a category filter from the report or Dashboard) has no trace in the diff or tests, and 4.7 holds only partly (F1). These may be ticks without a check.
- **Fix**: Confirm 4.7 and 4.8 by hand in the app (or note the result in Progress); after F1, run 4.7 again.
- **Decision**: FIXED — 4.7 and 4.8 checked in Playwright; the result is noted under Phase 4 Progress in the plan. 3.6 and 4.6 are left with the evidence from the tests.

### F8 — `whereDate` in day sums instead of a plain range like in `MonthCategoryActuals`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/OperationsDaySums.php:26-27
- **Detail**: The plan prescribed `whereDate`. On PostgreSQL the `::date` cast on a `date` column is a no-op and the index is used (NFR green), but on SQLite it becomes `strftime(...)` with no index. The sibling `MonthCategoryActuals::forMonths` (:37-38) uses `where` with a half-open range. The effect is limited to tests; on production it works the same.
- **Fix**: Leave it (consistent with the plan), or switch to `where('operation_date', '>=', $min)->where('operation_date', '<', $max+1 day)` like `MonthCategoryActuals`.
- **Decision**: FIXED — half-open range `>= min`, `< max+1 dzień` (as in `MonthCategoryActuals`). Operations tests pass on SQLite and PostgreSQL; `NfrPerf01Test` (`--configuration=phpunit.nfr.xml`) passes on PostgreSQL.

### F9 — Small duplicates in `OperationsListPanel.vue`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/OperationsListPanel.vue:159,303-361,375
- **Detail**: `noFilters` is defined three times (the panel, `noFiltersFixture` in `operations-fixtures.ts`, `emptyFilters` in `operations-list-query.test.ts`). The header rows for transfers and capital movements are identical (copied from the old `Operations.vue`). The footer borrows `t.budget.amounts_in_zl` from the budget namespace.
- **Fix**: Export `EMPTY_OPERATIONS_FILTERS` from `lib/operations-list-query.ts` next to `DEFAULT_OPERATIONS_SECTION` and use it in all three places; leave the header and the key as they are, or move them in the next change to the panel.
- **Decision**: FIXED — `EMPTY_OPERATIONS_FILTERS` (frozen) in `lib/operations-list-query.ts`, used by the panel, the test, the fixtures and the sink; `noFilters`/`emptyFilters`/`noFiltersFixture` removed. The header row and the `budget.amounts_in_zl` key are unchanged.

### F10 — The text filter chip inherits `text-base`, while the category chip is `text-xs`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/OperationsActiveFilterChips.vue:264
- **Detail**: Found during the F7 check (`/operations?category_name=Rozrywka&…&date_from=2026-01-01&date_to=2026-12-31`). The date chip "01.01.2026 – 31.12.2026" uses a visibly larger font than the "Rozrywka" chip next to it. The plan dropped `text-sm` from the chip, so `<span class="truncate">{{ chip.label }}</span>` inherits the body size (16 px), while `CategoryChip size="sm"` has `text-xs`. The `operations-filtered-*` screenshots show only the category chip, so they did not catch this.
- **Fix**: Give the text label the same size as `CategoryChip sm` (`text-xs` on the `truncate` span), then refresh `operations-filtered-*` with a date chip.
- **Decision**: FIXED — the chip label is `truncate text-xs font-medium` (like `CategoryChip sm`); both chips measure 12 px in Playwright. `operations-filtered-{light,dark}-{1280,390}.png` were refreshed with the category chip and the date chip.

## Triage summary (2026-11-05)

- Fixed: F1 (Fix A), F2, F3, F4, F5, F6, F7, F8, F9, F10 (10)
- Skipped / accepted: none
- Note without a finding: at 390 px the third tab ("Ruchy kapitału") is cut off at the edge of the `overflow-x-auto` row. It scrolls, and the same was in the accepted screenshots, but the row gives no visual cue that it scrolls.
