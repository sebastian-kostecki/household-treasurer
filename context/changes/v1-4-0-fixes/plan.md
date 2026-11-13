# v1-4-0-fixes Implementation Plan

## Overview

Six fixes after release 1.4.0, from the User's notes in `change.md`: writes on Operations and Budget return to the same filtered list (1), every write that returns to the same page keeps the scroll position (2), the Align action is called „Wyrównaj” (3), every server message has a Polish translation (4), the sidebar Budget year becomes a menu (5), and the Category description is shown read-only under the name on Budget (6). Phases 3, 5 and 6 change written decisions, so each starts with its glossary entry and ADR (0086, 0087, 0088) before the code.

## Current State Analysis

From `research.md` (the code anchors there are the base of this plan) and the checks made while planning:

- **Filters.** Operations filters, the `section` tab and the page numbers live only in the URL. Every success path of the Income, Expense, Own Transfer and Capital Movement controllers ends in a bare `to_route('operations')` (`IncomeController.php:31,51,61,82` and the same in the other three), so a create, edit, conversion or delete lands on the unfiltered list, tab „Operacje”, page 1. The dialogs and row menus are mounted only on `pages/Operations.vue`. Accounts and Term deposits already carry their list state (`show_archived`) through `.form(model, { query })` and a `redirectToIndex($request)` (`AccountController.php:134-136`). On Budget, `CategoryController::store/update/destroy` redirect to a bare `to_route('budget')` (`CategoryController.php:57,71,87`); `alignCurrentPlan` reads `show_archived` (`:133`) but `BudgetCategoryRowActions.vue:122` does not send it; archive/restore return `back()` and already keep it.
- **Scroll.** Inertia 3.11 resets the window to the top after every visit without `preserveScroll` (`node_modules/@inertiajs/core/dist/index.js:1446-1450`). `preserveScroll` accepts `boolean | 'errors' | (page) => boolean` (`types.d.ts:287`) and a `visitOptions(href, options)` callback in the app config supplies defaults (`index.js:4022-4023`, `types.d.ts:623`). `resources/js/app.ts` sets none. Only `CategoryGroupAssignDialog.vue:80` preserves scroll on a write.
- **Align copy.** „Zakończ” / „Zakończyć kategorię?” in `lang/pl/ui.php:320-322`, hardcoded in `pages/dev/DialogsKitchenSink.vue:185,781,783`; PRD FR-CAT-17 (`prd-mvp.md:180`) says „w UI: „Zakończ””. Align has no glossary entry of its own; „Zakończ” is on the Year Close _Avoid_ list (`glossary.md:171`).
- **Translations.** `lang/pl.json` misses 89 of the 111 literal `__('…')` keys used in `app/`: about 40 success toasts (for example „Income recorded.”, „Account created.”, „Month plan approved.”) and about 50 import and validation messages. `flashToast.ts` shows `message` as it comes, so they reach the User in English. Two keys are group keys (`passwords.reset`, `passwords.sent` in `lang/pl/passwords.php`).
- **Years.** `BudgetYearSwitcher.vue` renders a `role="group"` of `aria-pressed` segments, three per row, ascending, plus „N +” (ADR-0055). With 2022–2027 that is three rows. `budgetYearSegments` (`lib/budget-year-segments.ts`) is also used by `useCommandPalette.ts:129` to find the next year to create. Pinned by `ui-contract.test.ts:261-268`, `budget-year-segments.test.ts`, three e2e specs (`.sidebar-year-segments button`), `SidebarKitchenSink.vue:157-187`, `LandingAppPreview.vue:135-143` and `.ai/rules/js-sidebar.md:35`.
- **Description.** `categories.description` (string 500, nullable) is shown only on „Kategorie”: `CategoryListRow.vue:52-56,81-89` joins Rhythm, archive date and description into one muted `meta` line under an `lg` `CategoryChip` (`.categories-description`, `app.css:1398-1400`, full text in `title`). ADR-0067 and `glossary.md:48` say „shown only on „Kategorie””. `BudgetController::categoryPayload` (`:195-263`) already eager-loads `category.group` (`:58`) but sends no description.

## Desired End State

- On Operations, after any successful create, edit, conversion or delete the User sees the same filters, the same tab and the same page (or the last page that still exists), and the window has not moved. On Budget, adding, editing, deleting and „Wyrównaj” keep „Pokaż zarchiwizowane”.
- A write that returns to the page it was made on keeps the scroll position everywhere (Operations, Budget, Kategorie, Plan miesiąca, Konta, Lokaty, settings, validation errors); a write that lands on another page, and a Budget year change, start at the top.
- The Budget row menu says „Wyrównaj”, the dialog asks „Wyrównać plan do wykonania?”, the toast says „Plan bieżący wyrównany do wykonania.”; the glossary has an **Align** entry with that copy.
- Every literal `__('…')` key in `app/` has a Polish translation, and a test fails when a new one does not.
- The sidebar shows „Rok budżetu” with one button „2026 ▾” that opens a menu: years newest first, the selected one checked, then a separator and „Dodaj rok 2028”.
- A Budget row of a Category with a description shows it as one truncated muted line under the name (joined with „zebrane startowe” by „ · ” when both exist), full text on hover.

### Key Discoveries:

- The operation forms post fields named `category_id`, `account_id` and `term_deposit_id` (Store/Update Income, Expense and Capital Movement requests), the same names as the Operations filters. Laravel merges the query into `$request->all()`, so a filter in the query of the form action would fill a field the form did not send. The list state therefore travels under one namespaced key, `list[...]`; Wayfinder serialises a nested `query` object as `list[q]=…` (`resources/js/wayfinder/index.ts:49-61`).
- A request without `list` keeps today's bare redirect, so the existing `assertRedirect(route('operations'))` assertions stay valid; the new behaviour is covered by new tests (research counted about 50 such assertions as "to fix"; with this contract they do not change).
- The `visitOptions` result is spread **after** the caller's options (`index.js:4048-4049`), so the default must return `{}` when the caller set `preserveScroll` itself, otherwise it would override `CategoryGroupAssignDialog` and the year switcher.
- The three paginators have their own page names: `operations_page`, `own_transfers_page`, `capital_movements_page` (`OperationsController.php`, 50 per page).

## What We're NOT Doing

- No memory of Operations filters across sidebar navigation (session, `useRemember`, localStorage): „Operacje” in the menu still opens the full list.
- The CSV Import commit keeps its bare redirect to `/operations` (`CsvImportController.php:353`); it starts on another page.
- No editing of the description on Budget, and no description on Dashboard, Month Plan, Reports or in any selector (ADR-0067 keeps that part).
- No hybrid of segments and menu, no `Select`, no new `popover` primitive.
- No change to Year Close or its copy; no Align action module (the logic stays inline in `CategoryController`).
- No removal of the explicit `preserveScroll` in `CategoryGroupAssignDialog.vue:80`.
- No `CHANGELOG.md` entry in this change; the release commit writes it from the archived change.
- No translation of validation messages that already come from `lang/pl/validation.php`.

## Implementation Approach

Bugs first, in dependency order: Phase 1 makes the server return the same list, then Phase 2 keeps the window where it was (scroll without filters would still show a different list). Phases 3 and 4 are copy; 3 goes through the glossary and ADR-0086 first, 4 adds a guard test before the translations. Phases 5 and 6 change the UI against written decisions and each opens with its ADR and rule updates, then code, tests and the kitchen sink. Each phase is one commit `type(v1-4-0-fixes): …` and leaves the gate green.

## Critical Implementation Details

**List state travels under `list`, never as top-level query keys.** On a POST/PUT/DELETE the form action query merges into the validated input; top-level `category_id` or `account_id` from the filters would silently become form fields. The server copies only an allow-list from `list` (the filter keys of `IndexOperationsRequest`, `section`, the three page names), only scalar values, and builds `to_route('operations', $query)`; anything else is dropped.

**The scroll default must defer to explicit options and compare paths, not URLs.** `visitOptions` runs before the request; it captures `window.location.pathname` then and returns `preserveScroll: (page) => new URL(page.url, origin).pathname === capturedPath` only for non-GET visits whose `options.preserveScroll` is `undefined`. The query is ignored on purpose (a write on a filtered list redirects to the same path with the same query, a validation error to the referrer). The year switcher passes `preserveScroll: false`, because selecting a year on Budget returns to the same path with different content.

## Phase 1: Writes return to the same list

### Overview

Every successful write on Operations comes back with the filters, tab and page it was made from; a page past the end falls back to the last page. Budget Category writes keep `show_archived`.

### Changes Required:

#### 1. Server: redirect to the Operations list with the returned state

**File**: `app/Concerns/RedirectsToOperationsList.php` (new trait), used by `app/Http/Controllers/IncomeController.php`, `ExpenseController.php`, `OwnTransferController.php`, `CapitalMovementController.php`

**Intent**: Replace every success-path `to_route('operations')` in the four controllers (create, update, conversion branches, delete) with one helper that rebuilds the list URL from the request's `list` query, so the four controllers share the rule instead of repeating it.

**Contract**: `redirectToOperationsList(Request $request): RedirectResponse`. Reads `$request->query('list')`; when it is an array, keeps only `q, year, category_id, account_id, term_deposit_id, date_from, date_to, section, operations_page, own_transfers_page, capital_movements_page` with scalar values; returns `to_route('operations', $kept)`. Without `list`, it is exactly today's `to_route('operations')`.

#### 2. Server: a page past the end goes to the last page

**File**: `app/Http/Controllers/OperationsController.php`

**Intent**: After a delete the returned page may no longer exist (the last row of the last page). The index redirects to the same query with the active section's page set to the last page instead of showing an empty list.

**Contract**: For the paginator of the active `section` only: when `currentPage() > lastPage()` and `lastPage() >= 1`, redirect (GET) to `operations` with the validated filters, `section` and that section's page name set to `lastPage()`. Other sections' page parameters are passed through unchanged.

#### 3. Client: build the returned list state in the one query helper

**File**: `resources/js/lib/operations-list-query.ts`, `resources/js/lib/operations-list-query.test.ts`

**Intent**: Add the builder of the returned state next to `operationsListQuery`, so the rule "build `/operations` queries only here" (`.ai/rules/js-operations.md`) still holds, and an injection key the dialogs and row menus read.

**Contract**: `operationsReturnQuery(filters, section, pages: { operations: number; own_transfers: number; capital_movements: number }): { list: Record<string, string | number | undefined> }` = `operationsListQuery(filters, section)` plus each page name whose page is above 1. Export `OPERATIONS_RETURN_QUERY: InjectionKey<ComputedRef<{ list: … }>>`. Vitest cases: empty filters on the default section give `{ list: {} }` values all `undefined`; filters, `section` and pages > 1 are carried; page 1 is omitted.

#### 4. Client: Operations provides the state, dialogs and deletes send it

**Files**: `resources/js/pages/Operations.vue`, `IncomeFormDialog.vue`, `ExpenseFormDialog.vue`, `OwnTransferFormDialog.vue`, `CapitalMovementFormDialog.vue`, `FinancialOperationActions.vue`, `OwnTransferActions.vue`, `CapitalMovementActions.vue`

**Intent**: `Operations.vue` provides `operationsReturnQuery(filters, section, current pages of the three paginators)`; every `XController.store.form(...)` / `update.form(model, ...)` binding and every delete `.form(...)` passes it as `{ query }`. Injection with a default of `undefined` keeps the kitchen sink (`/dev/operations`) working without a provider.

**Contract**: Wayfinder `.form(args, { query: returnQuery.value })`, the pattern of `AccountFormDialog.vue:73-78`. No new props on the page.

#### 5. Budget: Category writes keep „Pokaż zarchiwizowane”

**Files**: `app/Http/Controllers/CategoryController.php`, `resources/js/pages/Budget.vue`, `resources/js/components/CategoryFormDialog.vue`, `resources/js/components/BudgetCategoryRowActions.vue` (and the components between them that pass props)

**Intent**: `store`, `update` and `destroy` redirect like `alignCurrentPlan` already does (one private `redirectToBudget($request)` as in `AccountController`), and the client sends `show_archived` on the create/edit form, the delete and the Align confirm.

**Contract**: `to_route('budget', $request->boolean('show_archived') ? ['show_archived' => 1] : [])`; client `.form(args, show_archived ? { query: { show_archived: 1 } } : undefined)` with the flag passed down from `Budget.vue` as a prop (the `Accounts.vue:48-50` pattern). `show_archived` is not a field of any Category form, so no namespacing is needed here.

#### 6. Tests and rules

**Files**: `tests/Feature/OperationsReturnToListTest.php` (new), `tests/Feature/OperationsFilterTest.php`, `tests/Feature/BudgetTest.php` (or `AlignCategoryCurrentPlanTest.php`), `.ai/rules/js-operations.md`

**Intent**: Prove the returned state per write type and the guard against field collisions; record the rule so the next dialog on Operations sends the state.

**Contract**: Feature tests: store, update and delete of Income with `list[category_id]=X&list[section]=own_transfers&list[own_transfers_page]=2` redirect to exactly that query; an Expense update with `list[account_id]=A` and a body `account_id=B` saves B; an unknown or array value under `list` is dropped; Own Transfer and Capital Movement delete carry `list`; a conversion branch carries `list`; `GET operations?operations_page=9` with 1 page redirects to page 1 for the active section; Category store/update/destroy with `show_archived=1` redirect to `budget?show_archived=1`. Rule line in `js-operations.md`: writes on Operations send `operationsReturnQuery` through the injected key, the server reads only `list[...]`.

### Success Criteria:

#### Automated Verification:

- New and touched Feature tests pass on PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/OperationsReturnToListTest.php tests/Feature/OperationsFilterTest.php tests/Feature/BudgetTest.php tests/Feature/AlignCategoryCurrentPlanTest.php`
- Existing redirect assertions in Income, Expense, Own Transfer, Capital Movement, edit/delete, reclassify and rebuild tests still pass unchanged
- Vitest for the query helper passes: `npm test -- operations-list-query`
- Full gate passes: `composer ci:check` (through Sail) and `vendor/bin/sloppy ci`

#### Manual Verification:

- On Operations with a category filter, the „Przelewy własne” tab and page 2, editing, adding and deleting an operation each return to the same filters, tab and page
- Deleting the only operation on the last page lands on the previous page, not an empty list
- On Budget with „Pokaż zarchiwizowane” on, adding, editing, deleting a Category and „Zakończ”/„Wyrównaj” keep the switch on

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Scroll stays after a write

### Overview

One default in the Inertia app config keeps the window where it is after a write that returns to the same page.

### Changes Required:

#### 1. Pure rule

**File**: `resources/js/lib/visit-scroll.ts`, `resources/js/lib/visit-scroll.test.ts` (new)

**Intent**: Keep the decision in a pure, tested function so `app.ts` only wires it.

**Contract**: `defaultVisitOptions(options: VisitOptions, currentPathname: string): Partial<VisitOptions>` returns `{}` for `method` `get` (or missing) and when `options.preserveScroll !== undefined`; otherwise `{ preserveScroll: (page) => pathnameOf(page.url) === currentPathname }`. Vitest: GET → `{}`; POST/PUT/DELETE with no option → function true for the same path with another query, false for another path; explicit `true` / `false` → `{}`.

#### 2. Wire it

**File**: `resources/js/app.ts`

**Intent**: Register the default once for the whole app.

**Contract**: `createInertiaApp({ …, defaults: { visitOptions: (href, options) => defaultVisitOptions(options, window.location.pathname) } })`. Confirm the config key (`defaults.visitOptions`) with `search-docs` for Inertia v3 before writing it.

#### 3. Year switch resets

**File**: `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: Selecting a year on Budget returns to `/budget` with another year; it must start at the top.

**Contract**: The `router.post(selectYear)` options get `preserveScroll: false`.

#### 4. Rule

**File**: `.ai/rules/js.md`

**Intent**: Record that writes keep scroll through the default, so nobody adds per-dialog options or a reset by hand.

**Contract**: New section "Writes keep the scroll on the same page": the default lives in `lib/visit-scroll.ts`, wired in `app.ts`; a write that must start at the top passes `preserveScroll: false` explicitly.

### Success Criteria:

#### Automated Verification:

- Vitest passes: `npm test -- visit-scroll`
- Types pass: `npm run types:check`
- Full gate passes: `composer ci:check` (through Sail)

#### Manual Verification:

- On Budget scrolled to a Category in the middle, „Edytuj” → „Zapisz” and Align leave the window on that row
- On Operations scrolled down, editing an operation leaves the window in place (with Phase 1, the same list)
- On Kategorie, Plan miesiąca, Konta and Lokaty a save in a dialog does not jump to the top; a validation error does not jump either
- Changing the Budget year in the sidebar, committing an Import and logging in still start at the top

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: „Zakończ” becomes „Wyrównaj”

### Overview

Align gets its own glossary entry with Polish UI copy, an ADR records the rename, then the copy changes.

### Changes Required:

#### 1. Glossary

**File**: `context/foundation/glossary.md`

**Intent**: Give Align a bold entry (after Current Plan) so its Polish copy is defined like Year Close's.

**Contract**: **Align**: sets the Current Plan of one Budget Line in a Budget in execution to max(0, Execution); Initial Plan unchanged; income and expense alike; not Year Close, not a month close. Polish UI copy: verb „Wyrównaj”, question „Wyrównać plan do wykonania?”. _Avoid_: Zakończ, Zakończyć kategorię, Close (for Align). The Year Close _Avoid_ list keeps „Zakończ”.

#### 2. ADR-0086

**File**: `context/foundation/adr/0086-align-is-wyrownaj-in-the-ui.md`

**Intent**: Record why „Zakończ” is replaced: it suggests ending the Category or the year (Year Close's _Avoid_), while the action only levels the Current Plan to Execution, as `mvp.md:43` and `prd-mvp.md:55,119` already describe it.

**Contract**: Supersedes the copy in PRD FR-CAT-17 and in `context/archive/2026-11-06-cancel-button-ghost/`; no rule change beyond copy; no prop change.

#### 3. PRD and copy

**Files**: `context/foundation/prd-mvp.md` (FR-CAT-17), `lang/pl/ui.php:320-322`, `lang/pl.json`, `resources/js/pages/dev/DialogsKitchenSink.vue:185,781,783`

**Intent**: FR-CAT-17 reads „Align (w UI: „Wyrównaj”)”; the menu item and confirm button say „Wyrównaj”, the title „Wyrównać plan do wykonania?”, the body stays; the toast key `Category current plan aligned.` gets „Plan bieżący wyrównany do wykonania.”; the kitchen sink reads the same copy.

**Contract**: Keys `budget.align_current_plan`, `budget.align_current_plan_confirm_title` keep their names; only values change.

### Success Criteria:

#### Automated Verification:

- No „Zakończ” left in `lang/` and `resources/js` outside Year Close: `grep -rn "Zakończ" lang resources/js` returns nothing
- Align tests pass: `php artisan test --compact tests/Feature/AlignCategoryCurrentPlanTest.php` (through Sail)
- Full gate passes: `composer ci:check` (through Sail)

#### Manual Verification:

- On Budget in execution the row menu shows „Wyrównaj”, the dialog „Wyrównać plan do wykonania?”, and the toast after confirming is Polish
- `/dev/ui/dialogs` shows the same copy

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Polish server messages

### Overview

A test lists every literal translation key used in `app/` and fails when Polish has none; then the 88 remaining keys get translations.

### Changes Required:

#### 1. Guard test

**File**: `tests/Feature/TranslationKeysTest.php` (new)

**Intent**: Catch an English toast or error before it ships, now and later.

**Contract**: Scans `app/**/*.php` for the first argument of `__()` and `trans()` when it is a single- or double-quoted literal, and asserts `Lang::hasForLocale($key, 'pl')` for each (this covers JSON keys and group keys such as `passwords.reset`). The failure message lists the missing keys. Written first and seen failing with the 88 keys.

#### 2. Translations

**File**: `lang/pl.json`

**Intent**: Translate every missing key into Polish with the glossary's Polish copy (Operacja, Przelew własny, Ruch kapitałowy, Lokata, Plan miesiąca, Budżet, kategoria…) and the tone of the existing entries (short, past participle for toasts: „Przychód zapisany.”, „Konto dodane.”).

**Contract**: One key per message, sorted as the file is; placeholders (`:attribute`, etc.) kept verbatim; group keys stay in their PHP files.

### Success Criteria:

#### Automated Verification:

- The guard test passes: `php artisan test --compact tests/Feature/TranslationKeysTest.php` (through Sail)
- Tests that assert message text still pass: full `composer ci:check` (through Sail)

#### Manual Verification:

- Adding an income, editing an Account and approving a Month Plan show Polish toasts
- An Import with no row selected shows a Polish error
- The User reads the list of new translations in `lang/pl.json` and accepts the wording

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: The Budget year in a menu

### Overview

The year segments become one menu button with the years newest first and „Dodaj rok N” below a separator, after an ADR that amends ADR-0055.

### Changes Required:

#### 1. ADR-0087 and rules

**Files**: `context/foundation/adr/0087-budget-year-in-a-menu.md`, `.ai/rules/js-sidebar.md:35`, `.ai/rules/index.md` (lib glob), `context/foundation/design-system.md` (sidebar token table, `sidebar-strong` / `sidebar-accent` rows)

**Intent**: Record that the "Year in segments" paragraph of ADR-0055 is replaced: with 2022–2027 the segments take three rows and old years are rarely used, which is the cost ADR-0053 predicted. The menu separates choosing from creating.

**Contract**: The ADR names the shape (menu button with the selected year, `DropdownMenuRadioGroup` newest first, separator, „Dodaj rok N”), the empty state (no Budget: one button „Dodaj rok <current year>” that opens the dialog directly), the pending state, and that `shell.budgetYears` does not change. The rule describes the menu and its kitchen sink states instead of segments.

#### 2. Pure logic

**Files**: `resources/js/lib/budget-year-menu.ts`, `resources/js/lib/budget-year-menu.test.ts` (replace `budget-year-segments.ts` and its test), `resources/js/composables/useCommandPalette.ts`

**Intent**: One function gives the menu its years and the year to create; the palette keeps using the same "next year" rule.

**Contract**: `budgetYearMenu(budgetYears, selectedYear, currentYear): { years: { year: number; selected: boolean }[]; createYear: number }`, years unique and descending, `createYear` = highest + 1 or `currentYear` when empty. `useCommandPalette` reads `.createYear`.

#### 3. Component and styles

**Files**: `resources/js/components/BudgetYearSwitcher.vue`, `resources/css/app.css` (replace `.sidebar-year-segments` / `.sidebar-year-segment` by a trigger class on `--sidebar-*` tokens), `resources/js/components/LandingAppPreview.vue`

**Intent**: The `SidebarGroupLabel` „Rok budżetu” stays; under it a full-width trigger shows the selected year and a chevron and opens a `DropdownMenu` (the `NavUser.vue` pattern). Choosing a year runs today's `router.post(selectYear)` with the pending guard, error toast, `destination` and `preserveScroll: false`; „Dodaj rok N” (icon `Plus`, `aria-label` from `t.shell.add_budget_for_year`) opens `useCreateBudgetDialog().openForYear(createYear)`. `shell` is read only through props from `AppSidebar` / `useShell()` (`js-sidebar.md:41`). The landing preview draws a static trigger instead of segments.

**Contract**: The trigger is named by the label and the year (`aria-labelledby` of both) and carries `aria-busy` while pending; selected text `sidebar-strong`, open/hover `sidebar-accent`. No literal colours (`npm run check:ui`).

#### 4. Tests, kitchen sink and e2e

**Files**: `resources/js/ui-contract.test.ts:261-268`, `resources/js/pages/dev/SidebarKitchenSink.vue`, `tests/e2e/sidebar-year-refresh.spec.ts`, `tests/e2e/collected-year-start.spec.ts`, `tests/e2e/reports.spec.ts`, `scripts/check-ui-literals.mjs` (if it lists the CSS class names)

**Intent**: The contract asserts the menu (`DropdownMenu`, `DropdownMenuRadioGroup`, `t.shell.add_budget_for_year`, no `aria-pressed`); the kitchen sink shows one year, many years (2022–2027), no Budget, pending and the open menu; the e2e specs open the trigger and pick the `menuitemradio` by its year.

**Contract**: The e2e selection goes through one helper in `tests/e2e/support/` used by the three specs.

### Success Criteria:

#### Automated Verification:

- Vitest passes: `npm test -- budget-year-menu ui-contract`
- UI literal check passes: `npm run check:ui`
- Types pass: `npm run types:check` (after `npm run build`)
- E2E specs pass: `npx playwright test tests/e2e/sidebar-year-refresh.spec.ts tests/e2e/collected-year-start.spec.ts tests/e2e/reports.spec.ts`
- Full gate passes: `composer ci:check` (through Sail)

#### Manual Verification:

- The sidebar shows „Rok budżetu” and one „2026 ▾” button; the menu lists 2027…2022 with 2026 checked and „Dodaj rok 2028” under a line
- Picking 2024 on Reports stays on Reports, on Trend stays on Trend, elsewhere opens Budget at the top
- On a phone the menu works inside the sidebar sheet; keyboard: Enter opens, arrows move, Escape closes, shortcuts stay silent while it is open
- `/dev/ui/sidebar` shows all five states in both themes; the landing page preview matches

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 6: Category description on Budget

### Overview

The Budget row shows the description read-only, in the same form as the „Kategorie” row, after an ADR that narrows ADR-0067's "only on „Kategorie”".

### Changes Required:

#### 1. ADR-0088, glossary and rules

**Files**: `context/foundation/adr/0088-category-description-on-budget.md`, `context/foundation/glossary.md:48`, `.ai/rules/categories.md:27`, `.ai/rules/js.md` (the `lg` chip sentence), `context/foundation/design-system.md:93`

**Intent**: Record that the description is also shown, read-only, under the name in the Budget row, as on „Kategorie”: one truncated `meta` line, full text in `title`; still never in a selector and edited only on „Kategorie”. This is a second exception to the small chip on Budget (ADR-0060), next to ADR-0076.

**Contract**: Glossary sentence becomes "shown on „Kategorie” and, read-only, in the Budget row (ADR-0088); never in a selector". The ADR supersedes only that sentence of ADR-0067.

#### 2. Prop on both sides

**Files**: `app/Http/Controllers/BudgetController.php` (`categoryPayload` and its docblock shape), `resources/js/components/BudgetCategoryList.vue:40-74` (row type), Budget kitchen sink fixtures, `tests/Feature/BudgetTest.php`

**Intent**: Send `description` (string or null) with each Budget row, read through the already eager-loaded `$line->category`.

**Contract**: `description: string|null` in the PHP array shape and the TS type in one commit; `assertInertia` checks a row with a description and one with null.

#### 3. Row

**Files**: `resources/js/components/BudgetCategoryRow.vue:117-170`, `resources/css/app.css`

**Intent**: Build the line under the name like `CategoryListRow`: the parts are „zebrane startowe X” (existing span, kept with its `data-collected-at-year-start` and hint) and the description, joined by „ · ”; with any part the chip is `lg` with the `#subtitle`, without one it stays small. The line is truncated to one line with the full description in `title`.

**Contract**: A named class in `app.css` with the `.categories-description` recipe (`meta text-muted-foreground block truncate`), no utilities for the truncation in the template.

#### 4. Kitchen sink

**File**: `resources/js/pages/dev/BudgetKitchenSink.vue` (the `/dev/kitchen-sink/budget` page) and its fixtures

**Intent**: Add states: description only, description with „zebrane startowe”, a long description (truncation), archived row with a description.

### Success Criteria:

#### Automated Verification:

- Budget Feature tests pass on PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/BudgetTest.php`
- UI literal check and types pass: `npm run check:ui` and `npm run types:check`
- Full gate passes: `composer ci:check` (through Sail) and `vendor/bin/sloppy ci`

#### Manual Verification:

- After adding a description „Tankowanie obu aut, myjnia, AdBlue” to Paliwo on „Kategorie”, the Budget row of Paliwo shows it under the name; a long one is cut with „…” and the full text shows on hover
- A row with „zebrane startowe” shows „zebrane startowe X · <opis>”
- Rows without a description look as before; `/dev/kitchen-sink/budget` shows the new states in both themes and on a phone width

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `operationsReturnQuery`: empty, filters + section, pages > 1, page 1 omitted.
- `defaultVisitOptions`: GET, non-GET same path, other path, explicit `true` / `false`.
- `budgetYearMenu`: empty list, one year, unsorted with duplicates (descending, unique), selected flag, `createYear`.

### Integration Tests:

- Feature: returned list state per write type, the field-collision case, unknown and array values under `list`, the page-past-the-end redirect, Budget `show_archived` on store/update/destroy/align, the Budget `description` prop, the translation key guard.
- E2E: the three year-switcher specs, with one shared helper for picking a year.

### Manual Testing Steps:

1. Operations: filter by a category and year, switch to „Przelewy własne”, go to page 2, scroll down, edit a transfer: same list, same place.
2. Delete the last operation on the last page: previous page.
3. Budget with „Pokaż zarchiwizowane”, scrolled to the middle: „Wyrównaj” keeps the switch, the scroll and shows the Polish toast.
4. Sidebar: open the year menu, pick 2024, then „Dodaj rok 2028” opens the dialog with 2028.
5. Add a description on „Kategorie”, see it on Budget.

## Performance Considerations

None beyond the existing ones: the description comes from the already eager-loaded `category`; the page-past-the-end check uses the paginator already built.

## Migration Notes

No schema or data migration. Stale `.sidebar-year-segment` classes disappear with the component; no persisted state depends on them.

## References

- Research: `context/changes/v1-4-0-fixes/research.md`
- List state precedent: `app/Http/Controllers/AccountController.php:134-136`, `resources/js/components/AccountFormDialog.vue:73-78`
- Description row precedent: `resources/js/components/CategoryListRow.vue:52-89`, `resources/css/app.css:1398-1400`
- Menu precedent in the sidebar: `resources/js/components/NavUser.vue`
- Decisions amended: ADR-0055 (years), ADR-0067 and ADR-0060 (description), PRD FR-CAT-17 (Align copy)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Writes return to the same list

#### Automated

- [x] 1.1 New and touched Feature tests pass on PostgreSQL
- [x] 1.2 Existing redirect assertions in operation tests still pass unchanged
- [x] 1.3 Vitest for the query helper passes
- [x] 1.4 Full gate passes: composer ci:check and sloppy ci

#### Manual

- [x] 1.5 Edit, add and delete on Operations return to the same filters, tab and page
- [x] 1.6 Deleting the only operation on the last page lands on the previous page
- [x] 1.7 Budget Category writes keep „Pokaż zarchiwizowane”

### Phase 2: Scroll stays after a write

#### Automated

- [ ] 2.1 Vitest for visit-scroll passes
- [ ] 2.2 Types pass
- [ ] 2.3 Full gate passes: composer ci:check

#### Manual

- [ ] 2.4 Budget edit and Align leave the window on the row
- [ ] 2.5 Operations edit leaves the window in place
- [ ] 2.6 Kategorie, Plan miesiąca, Konta and Lokaty saves and validation errors do not jump
- [ ] 2.7 Year change, Import commit and login still start at the top

### Phase 3: „Zakończ” becomes „Wyrównaj”

#### Automated

- [ ] 3.1 No „Zakończ” left in lang and resources/js
- [ ] 3.2 Align tests pass
- [ ] 3.3 Full gate passes: composer ci:check

#### Manual

- [ ] 3.4 Budget menu, dialog and toast show the new Polish copy
- [ ] 3.5 Dialogs kitchen sink shows the same copy

### Phase 4: Polish server messages

#### Automated

- [ ] 4.1 Translation key guard test passes
- [ ] 4.2 Full gate passes: composer ci:check

#### Manual

- [ ] 4.3 Income, Account and Month Plan toasts are Polish
- [ ] 4.4 Import error without selected rows is Polish
- [ ] 4.5 User accepts the wording of the new translations

### Phase 5: The Budget year in a menu

#### Automated

- [ ] 5.1 Vitest for budget-year-menu and ui-contract passes
- [ ] 5.2 UI literal check passes
- [ ] 5.3 Types pass after build
- [ ] 5.4 Year switcher e2e specs pass
- [ ] 5.5 Full gate passes: composer ci:check

#### Manual

- [ ] 5.6 Sidebar shows one year button and the menu with years newest first and „Dodaj rok”
- [ ] 5.7 Year choice keeps Reports and Trend and opens Budget at the top elsewhere
- [ ] 5.8 Menu works on a phone and with the keyboard
- [ ] 5.9 Sidebar kitchen sink and landing preview show the menu states

### Phase 6: Category description on Budget

#### Automated

- [ ] 6.1 Budget Feature tests pass on PostgreSQL
- [ ] 6.2 UI literal check and types pass
- [ ] 6.3 Full gate passes: composer ci:check and sloppy ci

#### Manual

- [ ] 6.4 Budget row shows the description under the name, truncated with full text on hover
- [ ] 6.5 Row with „zebrane startowe” joins both parts with „ · ”
- [ ] 6.6 Rows without a description unchanged; Budget kitchen sink shows the new states
