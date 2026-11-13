---
date: 2026-11-13T09:01:52+01:00
researcher: Claude (Opus 5.5)
git_commit: 9d68e5d
branch: development
repository: household-treasurer
topic: "v1-4-0-fixes: sidebar years, Operations filters and scroll after a write, Align copy, Category description on Budget"
tags: [research, sidebar, budget-year-switcher, operations, filters, inertia, preserve-scroll, budget, align, category-description]
status: complete
last_updated: 2026-11-13
last_updated_by: Claude (Opus 5.5)
---

# Research: v1-4-0-fixes

**Date**: 2026-11-13T09:01:52+01:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: 9d68e5d
**Branch**: development
**Repository**: household-treasurer

## Research Question

The five user notes in `change.md`:

1. Sidebar: the years as a dropdown, because with many years the chips take a lot of space and historical years are rarely used.
2. Operations: after filtering and then editing an operation, the User wants to land back on that filter. They say this is a general problem.
3. While editing something the page jumps to the top, and it is hard to get back to the same place.
4. Budget: the action „Zakończ” should rather be called „Wyrównaj”.
5. Budget: the Category descriptions are missing.

For each note: what the code does today, why it does that, and which documents, rules and tests a change would touch.

## Summary

1. **Years.** `BudgetYearSwitcher.vue` renders every Budget year as a segment, in ascending order, three per row, followed by one „N +” segment. With the legacy years 2022–2027 that is seven segments in three rows. ADR-0055 decided this and explicitly accepted the unlimited growth of the list. ADR-0053 had earlier rejected segments for exactly the User's reason. A dropdown is a front-end-only change, because the `shell.budgetYears` prop already is a flat `list<int>`. It still needs an ADR that amends ADR-0055 and a rewrite of `.ai/rules/js-sidebar.md:35`. It also breaks a contract test that forbids `DropdownMenu`, and it breaks three e2e specs.
2. **Filters.** Operations filters live only in the URL query. Every successful create, update or delete of an Income, Expense, Own Transfer or Capital Movement redirects to the bare `to_route('operations')`. The edit dialogs submit to a URL without the query. The result is that the filters, the `section` tab and the page are all lost after every successful write. Accounts and Term deposits already solve the same problem for `show_archived`: the page passes the query into `.form(model, { query })` and the controller has a `redirectToIndex($request)` helper. The Budget has a smaller version of the bug: Category store/update/destroy drop `show_archived`.
3. **Scroll.** Inertia 3.11.0 resets the window to the top after every visit made without `preserveScroll: true` (`node_modules/@inertiajs/core/dist/index.js:1446-1450`, default `false` at `:4028`). Among the write call sites in the inspected pages, only `CategoryGroupAssignDialog.vue:80` passes `preserveScroll: true`. `createInertiaApp` sets no `visitOptions` default (`resources/js/app.ts:10-27`). No layout has a `scroll-region`, so the window is the scroller.
4. **Align copy.** „Zakończ” is the Polish label of Align (`lang/pl/ui.php:320-322`; PRD FR-CAT-17, `context/foundation/prd-mvp.md:180`). The glossary lists „Zakończ” in the Year Close _Avoid_ list (`glossary.md:171`). Align has no glossary entry of its own and no Polish UI copy in the glossary. So the rename to „Wyrównaj” is a glossary change: per `AGENTS.md`, a glossary entry and a new ADR come before the code. No test asserts the copy. Separately, the success toast „Category current plan aligned.” and five other `CategoryController` toasts have no key in `lang/pl.json`.
5. **Description.** The Category description exists: the `categories.description` column holds up to 500 characters and is edited on „Kategorie”. ADR-0067 says it is shown only on „Kategorie”, and the glossary repeats this at `glossary.md:48`. The Budget payload (`BudgetController::categoryPayload`) does not carry it. Showing it on Budget contradicts ADR-0067 and the design-system rule that `CategoryChip` stays small on Budget (ADR-0060; the only exception so far is ADR-0076). It needs a new ADR plus updates to the glossary, `.ai/rules/categories.md` and the Budget prop with an `assertInertia` test.

Items 1, 4 and 5 are product or design decisions recorded in ADRs. Items 2 and 3 are bugs with a clear mechanism, and 3 depends on 2: with scroll preserved, a redirect to an unfiltered list still changes the content under the viewport.

## Detailed Findings

### 1. Sidebar year switcher

- `resources/js/components/BudgetYearSwitcher.vue`:
  - props `budgetYears: number[]` and `budgetYear: number|null`: `:17-20`
  - a `destination` that keeps the User on Reports or Trend: `:30-49`
  - `activate()`, with a pending guard; „N +” opens the create dialog and a year does `router.post(selectYear)`: `:73-105`
  - template, a `role="group"` of `<button aria-pressed>`: `:108-139`
- `resources/js/lib/budget-year-segments.ts:1-31` holds the segment rule: dedupe, sort ascending, mark the selected year, then add „create” at highest + 1, or at the current year when there are no Budgets.
- CSS in `resources/css/app.css`:
  - `.sidebar-year-segments` (`grid grid-cols-3 gap-1`): `:618-620`
  - `.sidebar-year-segment`: `:637-639`
  - `[aria-pressed='true']` → `bg-sidebar-strong`: `:641-643`
  - `LandingAppPreview.vue:131-143` reuses these classes in a static replica.
- Mounting:
  - `AppSidebar.vue:72-75` mounts the switcher inside `SidebarHeader`. The sidebar is `collapsible="offcanvas"` (`:48`); ADR-0055 removed icon mode.
  - `AppMobileBar.vue` has no year UI. On a phone the same sidebar opens in a Sheet.
- Server side:
  - `HandleInertiaRequests.php:48` shares `shell` through `ShellState::forRequest`.
  - `app/Support/ShellState.php:25-46` gives the shape `{budgetYear, budgetYears: list<int>, …}`.
  - `SelectedBudgetYear.php:54-63` builds the year list with `orderBy('year')`.
  - Selecting a year goes to `POST budget/year` (`routes/web.php:62`), handled by `BudgetController::selectYear` (`:149-161`), which writes the session and redirects according to `destination`.
  - A dropdown needs no PHP or prop change.
- Decisions:
  - ADR-0053 (`context/foundation/adr/0053-*.md`, "We rejected … the year in segments (… the number of years grows without a limit and "2027 +" mixes choosing with creating)") had a single year button.
  - ADR-0055 (`adr/0055-*.md:11`, "Year in segments") replaced that button with segments: „the unlimited growth of the list is accepted and the rows wrap”.
  - ADR-0085:20 expects the years 2022–2027 in the sidebar after the sheet load.
- Rules:
  - `.ai/rules/js-sidebar.md:35` describes the switcher as segments: `role="group"`, three per row, `aria-pressed`, with states one / many / empty / pending at `/dev/ui/sidebar`.
  - `:19`: only `--sidebar-*` tokens, checked by `npm run check:ui` (`scripts/check-ui-literals.mjs:22` lists the file).
  - `:27`: global shortcuts already stay silent while a reka menu or listbox is open.
  - `:41`: read `shell` only through `useShell()`, never by destructuring.
  - `design-system.md:139,141`: `sidebar-strong` is the selected segment and `sidebar-accent` the hover state or an open menu.
- Available primitives:
  - `components/ui/dropdown-menu` (RadioGroup, RadioItem, Label, Separator). It is already used in the sidebar by `NavUser.vue:6-8,25-44`.
  - `components/ui/select`.
  - There is no `popover` or `combobox`.
- Tests that pin the segments:
  - `resources/js/lib/budget-year-segments.test.ts:4-43`.
  - `resources/js/ui-contract.test.ts:261-268`, which asserts `role="group"` and `:aria-pressed` and `not.toContain('DropdownMenu')`.
  - The e2e specs `tests/e2e/sidebar-year-refresh.spec.ts`, `collected-year-start.spec.ts` and `reports.spec.ts` (`:22,294,307,471,582`), which select `.sidebar-year-segments button`.
  - The kitchen sink `resources/js/pages/dev/SidebarKitchenSink.vue:157-187`.

### 2. Operations filters lost after a write

- Where the filters live:
  - `IndexOperationsRequest.php:21-69` validates `q, year, category_id, account_id, term_deposit_id, date_from, date_to, section`. `section` defaults to `financial_operations` (`:66-68`).
  - The paginators have their own page names and `withQueryString()` (`OperationsController.php:44-46,83-84,112-113`).
  - No session, `useRemember` or localStorage copy exists (per the worker's repo grep).
- How the query is built:
  - The client builds it only in `lib/operations-list-query.ts:22-36`, as required by `.ai/rules/js-operations.md`.
  - `OperationsFilters.vue:199-205` (filter change) and `Operations.vue:138-143` (tab change) call `router.get` with preserveState and preserveScroll.
- Editing happens in dialogs on the same page (`Operations.vue:214-278`). Each dialog is a `<Form v-bind="formBinding" reset-on-success>` with no `:options`. The binding is `XController.update.form(model)` without a query:
  - `IncomeFormDialog.vue:371`
  - `ExpenseFormDialog.vue:478`
  - `OwnTransferFormDialog.vue:200`
  - `CapitalMovementFormDialog.vue:234`
- Delete goes from `OperationRowMenu.vue:64` to `ConfirmActionDialog`, again without a query.
- **Cause.** Every success path redirects to `to_route('operations')`:
  - `IncomeController.php:31,51,61,82`
  - `ExpenseController.php:31,51,61,82`
  - `OwnTransferController.php:28,48,58,72`
  - `CapitalMovementController.php:28,45,56,70,80,94`
  - also `CsvImportController.php:353`

  So after a successful write the list comes back with no filters, on the default `section` and on page 1. A validation error returns `back()` with the referrer, so the filters survive an invalid submit and are lost only on success.
- Precedent:
  - `AccountController.php:134-136` and `TermDepositController.php:140-142` (`redirectToIndex($request)`).
  - On the client, `Accounts.vue:48-50` passes the `filterQuery` into the forms (`AccountFormDialog.vue:73-78`, `TermDepositFormDialog.vue:62-70`).
  - Tested by `tests/Feature/AccountTest.php:320-359`.
- The same bug exists on Budget for `show_archived`:
  - `CategoryController::store/update/destroy` redirect to `to_route('budget')` without it (`:57,71,87`).
  - `alignCurrentPlan` honours `show_archived` (`CategoryController.php:133`), but `BudgetCategoryRowActions.vue:122` does not send it.
- The sidebar and the global shortcuts open a bare `/operations` (`useAppViews.ts:32`, `useOperationsIntent.ts:33`), so filters do not survive leaving and coming back that way. That is a separate question from note 2. Deep links from Reports, Categories, Dashboard and the command palette do carry filters through `operationsListQuery`.
- About 50 `assertRedirect(route('operations'))` assertions across 12 Feature files pin today's bare redirect. The worker's count covers files such as `IncomeTest`, `ExpenseTest`, `OwnTransferTest`, `CapitalMovementTest`, `FinancialOperationEditDeleteTest` and `CsvImportTest`. No e2e spec covers Operations.
- `preserveUrl` alone would not fix this. It keeps the URL but renders the unfiltered redirect response (core `index.js:3051`, per the worker).

### 3. Scroll reset after a write

- Inertia 3.11.0 (`node_modules/@inertiajs/core/package.json:3`):
  - `preserveScroll` defaults to `false` in the visit defaults (`dist/index.js:4028`).
  - After the page is set, the router calls `Scroll.reset()` unless `preserveScroll` is true (`:1446-1450`).
  - A `visitOptions` callback in the app config can supply defaults (`:4022-4023`), but `resources/js/app.ts:10-27` sets none.
- No layout declares `scroll-region`, so the window scrolls (`SidebarInset.vue`, `AppSidebarLayout.vue`). `preserveScroll: true` therefore leaves the window where it is.
- Write call sites without `preserveScroll`, by page:
  - **Operations:** the four form dialogs above and delete through `ConfirmActionDialog`. `ConfirmActionDialog.vue:29-30,56` already accepts `options`.
  - **Budget:**
    - `Budget.vue:189` (start execution) and `:284` (delete budget)
    - `CategoryFormDialog.vue:140`
    - `BudgetCategoryRowActions.vue:119` (Align), `:133` (archive), `:142` (restore) and `:151` (delete)
  - **Categories:**
    - `CategoryListFormDialog.vue:208` and `CategoryGroupFormDialog.vue:80`
    - `CategoryMergeDialog.vue:60`
    - `CategoryListRowActions.vue:136,145,154` and `CategoryGroupRow.vue:135`
  - **Month Plan:** `MonthPlan.vue:601`
  - **Accounts:** `AccountFormDialog.vue:148` and `Accounts.vue:239,259,277`
  - **Term deposits:** `TermDepositFormDialog.vue:143` and `TermDeposits.vue:275,298,319`
- Call sites that already preserve scroll:
  - `CategoryGroupAssignDialog.vue:80` has `:options="{ preserveScroll: true }"`.
  - `router.reload` forces it (`BudgetCategoryList.vue:302`, `Categories.vue:239`).
  - The read `router.get` calls (filters, tabs, pagination, month picker) set it.
- `BudgetYearSwitcher.vue:88` navigates to another page, so a reset there is likely intended. A global `visitOptions` default for non-GET visits would also keep scroll on writes that redirect to another page, such as an Import commit or a year selection.
- No test (Feature, vitest or e2e) asserts the scroll or preserve options; the worker grepped `preserveScroll|preserveState` in tests and found none.

### 4. Align label „Zakończ”

- There is no `app/Actions/*Align*`. The logic is inline:
  - `CategoryController::alignCurrentPlan` (`app/Http/Controllers/CategoryController.php:118-134`) sets `planned_amount_minor_units = max(0, execution)`.
  - Route: `routes/web.php:86`, name `categories.align-current-plan`.
  - Policy: `BudgetLinePolicy.php:20-25`. Align is allowed when the Budget is in execution and the Budget Line is not archived.
  - Prop: `can_align_current_plan` in `BudgetController.php:259-261`, which also requires remaining ≠ 0.
  - Align is offered for income and expense Budget Lines alike: neither the policy nor the prop checks the type, and `tests/Feature/AlignCategoryCurrentPlanTest.php:16` covers income.
- UI:
  - The menu item is in `BudgetCategoryRowActions.vue:86-92` (icon `AlignHorizontalJustifyCenter`) and the confirm dialog at `:118-131`.
  - Copy: `lang/pl/ui.php:320` „Zakończ”, `:321` „Zakończyć kategorię?”, `:322` the body text.
  - The kitchen sink hardcodes the copy: `pages/dev/DialogsKitchenSink.vue:185,781,783`.
- Documents that name the Polish label:
  - `prd-mvp.md:180` (FR-CAT-17, „Align (w UI: „Zakończ”)”)
  - `CHANGELOG.md:93`
  - archived `context/archive/2026-11-06-cancel-button-ghost/` (plan and brief)
  - `glossary.md:171` puts „Zakończ” on the Year Close _Avoid_ list
  - `mvp.md:43` and `prd-mvp.md:55,119` already describe Align with the verb „wyrównać / wyrównuje plan aktualny do wykonania”
- Glossary state: Align is used in the Current Plan entry (`glossary.md:40`) and in Year Close (`:170`), but has no bold entry of its own and no Polish UI copy. Setting „Wyrównaj” is therefore a new Polish copy for a glossary term. Per `AGENTS.md` (Hard rules) it starts with the glossary entry and a new ADR. The next free number is 0086; the latest ADR is 0085.
- No test asserts „Zakończ” or „Zakończyć kategorię?” (searched in tests, `*.test.ts` and e2e).
- Side finding, observed in files and not seen in a browser: `__('Category current plan aligned.')` (`CategoryController.php:130`) has no key in `lang/pl.json`. The same is true of `Category created.`, `Category updated.`, `Category deleted.`, `Category archived.` and `Category restored.` The check covered every `__()` string in `CategoryController.php`; only `Existing category joined.` is translated. The app locale is `pl` and the fallback is `en` (`config/app.php:81,83`), so `__()` returns the English key and those toasts are most likely shown in English.

### 5. Category description on Budget

- Storage and edit:
  - Migration `2026_11_10_062409_add_description_to_categories_table.php`: `string(500)`, nullable.
  - Validation: `CategoryValidationRules.php:43`.
  - Writes: `CreateListCategory.php:50` and `UpdateListCategory.php:62-63`. Blank is normalised to null at `:101-105`. The docblock at `:19-21` says the Budget page sends no description.
  - Edit field: `CategoryListFormDialog.vue:251-260`.
- Shown today:
  - The only read path for the UI is `CategoryListReading.php:68`, for „Kategorie”. The backup also includes the description (`BackupUserData.php:184`).
  - In the UI it appears only in `CategoryListRow.vue:51-55,85-88`, as a one-line truncated `meta` caption, joined with the Rhythm and the archive date, with the full text in `title`.
- Budget:
  - `BudgetController::categoryPayload` (`:195-263`) has no `description`, and neither does the `BudgetCategoryRow` type (`BudgetCategoryList.vue:40-74`).
  - The row component is `BudgetCategoryRow.vue:117-170`. It uses a small `CategoryChip`. It switches to `size="lg"` with a `#subtitle` `meta` line only when `collected_at_year_start` is set (ADR-0076).
- Decisions blocking it:
  - ADR-0067 ("Description … shown and edited only on „Kategorie”", and the rejected bullet "the description in the selectors").
  - `glossary.md:48` repeats "shown only on „Kategorie” (ADR-0067)".
  - `.ai/rules/categories.md` (never in a selector; read through `$line->category` with eager loading).
  - `design-system.md:93`: `CategoryChip` stays small on Budget and Dashboard (ADR-0060), with ADR-0076 as the only exception.

  ADR-0067's rejection is about selectors. Budget is not a selector, but the "only on „Kategorie”" sentence covers it.
- Presentation primitives: `components/ui/tooltip` (pattern `ColumnHeaderHint.vue`, used in `BudgetCategoryHeaderRow.vue:55-73`), the native `title`, or the `lg` chip with a `meta` subtitle (the ADR-0076 pattern). There is no popover or hover-card.

## Code References

- `resources/js/components/BudgetYearSwitcher.vue:73-139` - year segments and select/create
- `resources/js/lib/budget-year-segments.ts:1-31` - segment list rule
- `resources/js/ui-contract.test.ts:261-268` - contract forbidding `DropdownMenu` in the switcher
- `app/Http/Controllers/IncomeController.php:31,51,61,82` - bare `to_route('operations')` (same in Expense, OwnTransfer, CapitalMovement)
- `app/Http/Controllers/AccountController.php:134-136` - `redirectToIndex($request)` precedent
- `resources/js/lib/operations-list-query.ts:22-36` - the only builder of `/operations` queries
- `resources/js/components/IncomeFormDialog.vue:371` - `<Form>` without options (same in the other three dialogs)
- `resources/js/components/CategoryGroupAssignDialog.vue:80` - the one write with `preserveScroll: true`
- `node_modules/@inertiajs/core/dist/index.js:1446-1450,4022-4028` - scroll reset and default visit options
- `resources/js/app.ts:10-27` - `createInertiaApp` without `visitOptions`
- `app/Http/Controllers/CategoryController.php:118-134` - Align
- `lang/pl/ui.php:320-322` - „Zakończ” copy
- `context/foundation/glossary.md:40,48,170-171` - Align, Category description, Year Close _Avoid_
- `app/Http/Controllers/BudgetController.php:195-263` - Budget category payload (no description)
- `resources/js/components/BudgetCategoryRow.vue:117-170` - Budget row, chip and subtitle slot

## Architecture Insights

- List state lives in the URL, and every write is meant to return the User to the same list. Accounts and Term deposits do this by carrying the list query through the form and rebuilding it in the controller. Operations and the Budget Category writes do not. The fix shape that exists in the repo is the Accounts pattern, built with `operations-list-query.ts` on the client.
- Inertia scroll is opt-in per visit. The project has no shared default, so every dialog `<Form>` and `ConfirmActionDialog` call decides for itself. There are two options: a per-call-site `options`, or a `visitOptions` default that has to tell same-page writes from cross-page ones.
- Three of the five notes reverse or extend written decisions: ADR-0055 for the segments, the glossary and PRD FR-CAT-17 for the „Zakończ” copy, and ADR-0067 with `glossary.md:48` and ADR-0060 for the description. The project rule is ADR first, code second.

## Historical Context (from prior changes)

- ADR-0053 (sidebar follows Atrament) rejected year segments because the list grows without limit. ADR-0055 reversed that once the icon strip was gone and accepted the growth. The User's note confirms the cost ADR-0053 predicted; ADR-0053's reasoning on this point holds again.
- `context/archive/2026-11-07-sidebar-year-refresh/` added `tests/e2e/sidebar-year-refresh.spec.ts` and the `useShell()` rule (`.ai/rules/js-sidebar.md:41`). Any new switcher must keep that guard green.
- `context/archive/2026-11-06-cancel-button-ghost/` set the current Align dialog copy (`plan.md:33,47,137`).
- `context/archive/2026-11-10-category-edit-and-archive/` and ADR-0067 introduced the description as „Kategorie”-only.
- `context/archive/2026-11-12-legacy-budget-import/` (ADR-0085) brought in 2022–2025, which turned the switcher into three rows.

## Related Research

Not applicable. No earlier `research.md` covers these areas.

## Open Questions

These are product and design choices for `/10x-plan`; none is a missing fact:

1. **Year switcher form.** The options are:
   - `DropdownMenu` with a `RadioGroup` of years and a separate „Dodaj rok N” item. This has an in-sidebar precedent in `NavUser`, and it separates choosing from creating, which was ADR-0053's objection.
   - `Select`, with „+” outside it.
   - A hybrid: the current or recent years as segments and older years in a menu.

   Year order in the menu also needs deciding (newest first?). Whichever is chosen, it needs an ADR amending ADR-0055, plus updates to `js-sidebar.md:35`, the contract test, the kitchen sink, three e2e specs and `LandingAppPreview`.
2. **Filter scope.** Keep filters, `section` and page after update only, or also after create and delete? After Import commit (`CsvImportController.php:353`)? Fix the Budget `show_archived` drop in the same change? Should filters also survive returning through the sidebar (session or `useRemember`)? That goes beyond note 2.
3. **Scroll scope.** Choose between per-call-site `preserveScroll` on same-page writes (Operations, Budget, Categories, Month Plan, Accounts, Term deposits) and a `visitOptions` default. A page given a tall dialog result, such as a new row at the top of a list, may still want a reset.
4. **„Wyrównaj” copy.** Decide the dialog title (for example „Wyrównać plan do wykonania?”), whether the glossary gets a proper **Align** entry with the Polish copy, and the follow-up in PRD FR-CAT-17. Also decide whether to translate the untranslated `CategoryController` toasts in the same change.
5. **Description on Budget.** Where it appears:
   - a `meta` subtitle under the name (`lg` chip, a second exception to ADR-0060)
   - an info icon with a tooltip
   - the native `title`

   Is it read-only on Budget? It needs an ADR superseding the „Kategorie”-only sentence of ADR-0067, an updated `glossary.md:48`, and the prop on both sides with an `assertInertia` test.
