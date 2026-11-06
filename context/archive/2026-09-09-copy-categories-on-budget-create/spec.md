# Copy Categories on Budget create

## Problem Statement

The User can already hold a Budget per calendar year. Creating the next year still opens an empty draft: every Category and Current Plan must be typed again. Category belongs to one Budget; there is no year-spanning identity, no monthly plan, and no copy path. Starting 2026 should not mean rebuilding 2025 by hand.

## Solution

Extend the existing “Dodaj rok” / “Nowy budżet” dialog so that, when the User has an earlier Budget with at least one non-archived Category, they can create the new draft and its Categories in one save. The copy inserts new Category records on the target Budget. It copies Current Plan, not Initial Plan or Execution. The new Budget stays in draft and does not start execution.

Recorded in ADR-0014. Requirements: FR-BUD-11–14, FR-CAT-12–13, BR-16.

## User Stories

1. As a User with a 2025 Budget who is creating 2026, I want the add-year dialog to default to copying 2025’s non-archived Categories and Current Plans, so that I do not rebuild the year from scratch.
2. As a User who has 2024 but no 2025 and is creating 2026, I want the default source to be 2024, so that a skipped calendar year does not force an empty start.
3. As a User creating 2026 while a 2027 Budget already exists, I do not want 2027 offered as a source, so that copy always runs from an earlier year.
4. As a User with no earlier Budget that has a non-archived Category, I want the add-year dialog unchanged (year only), so that the first Budget is still a blank draft.
5. As a User who wants a clean year, I want an explicit “Bez kopiowania” option even when a source exists, so that I can still start empty.
6. As a User whose source has an archived Category, I want that Category omitted from the new draft, so that a retired bucket stays retired.
7. As a User, I want every non-archived source Category copied (including Current Plan of 0), so that I am not picking buckets in the create dialog.
8. As a User, I want name, type, icon, color, sort order, and Current Plan copied, so that the new Budget page looks like the source list, not a reset of appearance.
9. As a User, I want the new Budget to remain a draft with unfrozen Initial Plan and zero Execution, so that last year’s books do not become this year’s plan freeze or actuals.
10. As a User, I want a failed copy to leave no new Budget, so that I never land on a half-created year.
11. As a User, I want this control on every add-year entry point (sidebar switcher and Budget empty state), so that starting a year is one behaviour.
12. As a User, I want to edit, delete, or add Categories on the new draft afterwards, so that copy is a start, not a lock-in.

## Implementation Decisions

- Keep creating a Budget through `POST /budgets` (`BudgetController@store`, Wayfinder `BudgetController.store`). Do not add a second copy endpoint or a “copy into existing Budget” action.
- Optional request field `source_budget_id` (nullable). Omit or null means empty draft, same as today. When present it must belong to the signed-in User, have `year` strictly less than the new `year`, and have at least one Category with `archived_at` null. Otherwise 422; create nothing.
- One database transaction: insert the draft Budget, then insert copied Categories, then set the selected year and redirect to `budget` with the existing success toast. Roll back the whole write on any failure.
- Copied Category fields: `name`, `type`, `icon`, `color`, `sort_order`, `planned_amount_minor_units` (source Current Plan). Set `initial_plan_minor_units` to 0 and `execution_minor_units` to 0, matching a Category created by hand on a draft. Leave `archived_at` null. New `id` values. Do not copy timestamps. Skip every source Category with `archived_at` set. Preserve relative `sort_order` per type as stored on the source.
- Do not call `StartBudgetExecution`. Do not copy source `initial_plan_minor_units` or `execution_minor_units`. Do not touch Accounts, Financial Operations, or the source Budget.
- Extract the duplicated create-Budget dialog from `BudgetYearSwitcher.vue` (empty and non-empty branches) and `Budget.vue` into one Vue component used in all three places. Both chrome and the Budget empty state must send the same payload.
- Shared Inertia shell (or an equivalent payload already available wherever the dialog mounts) must expose copyable sources as `{ id, year }[]` for the signed-in User: Budgets with at least one non-archived Category. Keep `shell.budgetYears` as the list of years that exist. Do not put other Users’ Budgets in either list.
- Dialog control: year number as today, plus one select shown only when at least one copyable source has `year` less than the year currently entered. First option is “Bez kopiowania” (no `source_budget_id`). Remaining options are those earlier copyable years, labelled as the calendar year. Default selection: previous calendar year if that year is in the list, otherwise the newest year in the list. Changing the target year rebuilds the option list and resets the selection to that default (including leaving “Bez kopiowania”).
- Polish copy in `lang/pl/ui.php` (and the English strings file if the create toast/validation messages live there). Glossary in code and docs stays English (`Budget`, `Category`, `Current Plan`). Proposed keys: a select label for the source, a “Bez kopiowania” option, and a short description that the copy brings Categories and Current Plan onto a new draft. Do not say “szablon”, “klon”, or that it is the same Category across years.
- Validation errors for `source_budget_id` render next to the select, same as `year` next to the year field. Existing unique-year and 2000–2100 rules stay.
- Wayfinder remains how Vue posts to `store`. Follow neighbouring Form Request + Action style: keep authorization on the User’s own Budget; put the copy loop in an Action called from `store`, not a fat controller, if that matches how `StartBudgetExecution` already sits.

## Testing Decisions

- Feature tests in `tests/Feature/BudgetTest.php` (or a sibling Pest file in Feature if that file grows past neighbouring size). HTTP seam: `actingAs`, `post` to `route('budgets.store')`, `RefreshDatabase`, factories. Assert Inertia/redirects and the database, not Vue internals, Tailwind, or screenshots. No `pest-plugin-browser`.
- Cover: copy from previous calendar year; gap year defaults to the newest earlier copyable Budget (assert the default by posting that `source_budget_id` as the client would); omit `source_budget_id` still creates an empty draft; archived source Categories are absent on the target; Current Plan copied, Execution stays 0, Initial Plan stays 0, source Execution unchanged; new Category ids; draft phase; future-year Budget rejected as source; other User’s Budget rejected as source; source with only archived Categories rejected; ineligible `source_budget_id` creates no Budget; unique year still blocks a second Budget for the same year.
- Assert shell (or dialog) payload lists copyable `{ id, year }` and omits Budgets with no non-archived Category.
- Guest cannot post `budgets.store` (existing auth coverage is enough if it already hits this route; do not duplicate unless this change opens a new path).
- Do not add a unit test that restates the copy field list unless the Action grows logic that HTTP cannot see (it should not).

## Out of Scope

- A Category template, kind, or identity that spans Budgets.
- Copying into an already-created Budget (empty or not).
- A checklist of Categories in the create dialog.
- Copying archived Categories, Execution, or Initial Plan onto the new year.
- Starting execution as part of create.
- Copying Accounts (they are already independent of Budget).
- Monthly plans.
- Cross-year reports that treat same-named Categories as one series.
- Changing First Run rules; a copy that creates the User’s first Categories may complete the Category checklist item as a consequence of existing First Run, not as a new rule.

## Further Notes

- Glossary: `CONTEXT.md`. Product requirements: `.docs/prd.md` (FR-BUD-11–14, FR-CAT-12–13), `.docs/mvp.md`. Decision: `.docs/adr/0014-copy-categories-as-new-records-on-budget-create.md`.
- Next step is `/to-tickets` or implement this spec. Do not invent a second copy verb while implementing.
- Visual language stays the OpenShip surface recipe (ADR-0001 / ADR-0003). This spec adds a select to an existing dialog; it does not restyle the shell.
