<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Grupy kategorii

- **Plan**: context/changes/category-groups/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-10
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 7 observations

## Verification run

All commands ran on commits fd20c68..7d49bd8. The PHP tests and the gate ran on PostgreSQL with the worktree command from AGENTS.md. `sloppy` and the npm steps ran in the `sail-8.5/app` container, because the host CLI is PHP 8.3.36 (the dependencies need ≥ 8.4.1) and the `npm` on the host PATH is the Windows binary, which cannot run from a UNC path.

| Command | Result |
|---|---|
| Phase tests 1–5 on PostgreSQL (CategoryListOrder, CategoryGroupsMigration, CategoryListBudgetPage, Budget, UserSnapshotCommand, CategoryGroup, CategoriesPage, CategoriesHardcodedUi, BudgetCategoryGroups, CategoryReorder, Expense, CsvImport) | PASS — 279 tests, 2461 assertions |
| `composer ci:check` (PostgreSQL) | PASS — 1103 passed, 2 skipped |
| `vendor/bin/sloppy ci` | PASS — 100/100, 0 findings |
| `npm run test` | PASS — 43 files, 498 tests |
| `npm run build`, `npm run types:check`, `npm run check:ui` | PASS |
| E2E `tests/e2e/categories.spec.ts` | not re-run in this review |

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Scope Discipline note: the files added outside the plan serve its intent. `CategoryListStore` holds the locked read and the two `UPDATE … CASE` writes that the plan names under Performance Considerations. `CategoryListSequence` is the locked "next place". `CategoryAppearance::group()` builds the option's `group` field. `BudgetCategoryRow` gets a `nested` prop. `lang/pl.json` holds the new messages. The "What We're NOT Doing" boundaries hold: the Operations filter, the ⌘K palette and the month plan stay flat, a group's type cannot change, and there is no composite FK.

## Findings

### F1 — Server rejection of a drag (422) is silent in the browser

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/Categories.vue:160-183, resources/js/components/BudgetCategoryList.vue:263-288
- **Detail**: Both `saveTree` functions expect `await reorderHttp.patch(...)` to throw on failure. In Inertia v3, `useHttp` does not throw on 422 (`node_modules/@inertiajs/vue3/dist/index.js:2112-2118`). It sets `form.errors`, calls `onError` and returns `undefined`. 422 is the main rejection path: `ReorderCategoryList` turns `InvalidArgumentException` ("The tree is not exactly the visible set") into a validation error on `items`. That happens whenever the tree no longer matches the server, for example after a category or group was added, archived or deleted in another tab. In that case the `catch` is skipped, so there is no toast and no `console.error`. The code goes on to `router.reload()`, and the dropped row snaps back with no explanation. The server does `report()` the error, but the browser shows nothing, which goes against the "never hide evidence" rule. The old Budget code had the same gap, and an empty `catch {}` as well. This change makes a 422 much more likely and copies the pattern into "Kategorie".
- **Fix**: Handle the 422 explicitly in both `saveTree` functions: pass `onError` (or check `reorderHttp.hasErrors` after `await`), then log the errors with `console.error`, show `toast.error(...)` and reload the props from the server. Write the regression test first: an E2E test in `tests/e2e/categories.spec.ts` that answers the PATCH with 422 through `page.route` and expects the toast. It is red today and turns green after the fix.
  - Strength: Follows the real Inertia v3 contract, verified in dist. It closes the one rejection path that is likely in practice.
  - Tradeoff: Two components to change, and the E2E needs route interception, which is a new technique in this spec.
  - Confidence: HIGH — the `return void 0` at 422 is visible in the installed version.
  - Blind spot: The toast copy for "the list changed elsewhere" may need a different message than `group_reorder_failed`.
- **Decision**: FIXED — `saveTree` in `Categories.vue` and `BudgetCategoryList.vue` passes `onError`, which logs the errors, restores the tree, shows the existing toast, and then reloads the props. Regression test: E2E "an order rejected by the server shows an error and the saved order" in `tests/e2e/categories.spec.ts`. It creates a group through a direct request (the other tab), then uses "W górę" on the stale tree. The real server answers 422. The test was red before the fix (no toast) and is green after. The `deleteGroup` helper now moves the pointer away from the toasts before waiting, because a hover kept them open. The Budget path has no separate E2E test; its code is the same. Verification on the worktree preview (:8090): the whole spec passes 7/7 in a 1280×1000 window. At 720 px the drag test fails, because the preview's "Zdrowie" group pushes the target row below the window; this is the preview data, not the code.

### F2 — `categories.category_group_id` has no index

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: database/migrations/2026_11_10_090000_create_category_groups_table.php:30-34
- **Detail**: The plan (Faza 1 §2) asks for a nullable FK "z indeksem". The migration has `foreignId(...)->nullable()->constrained('category_groups')->nullOnDelete()` and no `->index()`. PostgreSQL does not index an FK column on its own, so the `nullOnDelete` on group delete and the membership lookups have no index. `CategoryGroupsMigrationTest` does not check for one. The impact is small at single-user data sizes.
- **Fix**: Add `$table->index('category_group_id')` in the migration, which has not been released yet, and assert the index in `CategoryGroupsMigrationTest` on PostgreSQL.
- **Decision**: FIXED — `$table->index('category_group_id')` is in the migration, and the first test of `CategoryGroupsMigrationTest` asserts `Schema::hasIndex`. The assertion was red before the fix and is green after it on PostgreSQL. The preview database (`ht_category_groups_preview`) ran the migration before the fix, so it has no index until a `migrate:refresh`.

### F3 — „W górę / W dół” is not blocked while a save is running

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/Categories.vue:185-193, resources/js/components/CategoryGroupRow.vue:105-118
- **Detail**: Dragging is disabled while `reorderHttp.processing` is true, but the menu items are not. A second move during a save overwrites the shared `treeBeforeChange`. If the first request then fails, the rollback restores the wrong snapshot. The server stays consistent because each request sends the full tree.
- **Fix**: Disable "W górę / W dół" while `reorderHttp.processing` (a prop on `CategoryGroupRow`), or return early from `moveGroup`.
- **Decision**: FIXED — `moveGroup` returns early while `reorderHttp.processing`. Regression test: E2E "a group move does not start while the previous save is running". It holds the first PATCH back with `page.route` and clicks "W dół" meanwhile. It was red before the fix (two PATCH requests with different trees) and is green after it.

### F4 — The delete confirmation does not count the group's archived categories

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/Categories.vue:275, resources/js/components/CategoryGroupRow.vue:46-61
- **Detail**: `count` is `item.categories.length` from the tree of active categories. `DeleteCategoryGroup` clears membership for all of the group's categories, archived ones included, so "N kategorii zostanie bez grupy" understates the effect. A group with only archived members shows "0" and deletes without saying so. Once S-19 adds restore, those categories come back without a group.
- **Fix**: Count all members of the group (send an `archived_count` with the group prop or count them in `CategoryListReading`) and mention archived members in the copy, or accept it until S-19.
- **Decision**: FIXED — the groups in the „Kategorie” props carry `archived_count`. `CategoryListReading` counts it from the collection it already loads, so there is no new query. The PHP and TS types changed together. The confirmation copy comes from a pure function, `groupDeleteDescription` in `resources/js/lib/category-list.ts`, which adds "Zarchiwizowane kategorie grupy (:count) też stracą grupę." (`lang/pl/ui.php`). A group with archived Categories only is no longer "pusta". The kitchen sink shows the 5 + 1 state and an archived-only state. Tests: `CategoriesPageTest::test_a_group_counts_its_archived_categories` (`assertInertia`) and `groupDeleteDescription` in `category-list.test.ts`, red before the fix and green after.

### F5 — A concurrent group delete turns an assign or delete into a 500

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/AssignCategoryGroup.php, app/Actions/DeleteCategoryGroup.php
- **Detail**: `CategoryListOrder::assign` and `deleteGroup` throw `InvalidArgumentException` ("Unknown Category Group") when the locked state no longer contains the group. Only `ReorderCategoryList:34` maps that exception (report, then a validation error). If the group disappears between validation and the lock, the other two actions return 500. This is very unlikely for a single user.
- **Fix**: Map the exception the same way as `ReorderCategoryList` (report, then a `ValidationException` on `category_group_id`), or accept it.
- **Decision**: ACCEPTED — a race between two tabs of a single user. The error is not hidden: the 500 goes to the log with its stack trace.

### F6 — The tie-rule docblock claims to match `scopeOrdered`, but the collation differs

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/CategoryList/CategoryListOrder.php:28-30, :351-357
- **Detail**: PHP breaks a `sort_order` tie with `strcmp` (byte order). `BudgetLine::scopeOrdered:121` and `CategoryListReading:38-40` use `orderBy('name_key')` with the PostgreSQL collation, and the TS `buildCategoryTree` inherits the database order. With a legacy tie on `name_key` with diacritics, display and the first write can order the two rows differently. The effect ends after the first write of the type, which renumbers it 0…n−1.
- **Fix**: Correct the docblock to state the difference and when it matters, or use `orderByRaw('name_key COLLATE "C"')` in both readers.
- **Decision**: FIXED — the docblock of `CategoryListOrder` now names the collation difference and says it matters only before the first write of the type's order. The code is unchanged.

### F7 — The group dialog's states are missing from `/dev/ui/dialogs`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/CategoryGroupFormDialog.vue
- **Detail**: `.ai/rules/js-dialogs.md:15` says: "Add new dialog states to the kitchen sink at `/dev/ui/dialogs`". `CategoryFormDialog` is there. `CategoryGroupFormDialog` (new, rename, name error) is shown only in `/dev/...` for "Kategorie". The plan (Faza 3 §5) put it there, so the plan missed the rule.
- **Fix**: Add the states of `CategoryGroupFormDialog` to the dialogs kitchen sink next to `CategoryFormDialog`.
- **Decision**: FIXED — `DialogsKitchenSink.vue` has two new states:
  - `category-group-new` saves on the real route, like `category-new`.
  - `category-group-rename-error` saves through the sink endpoint, which waits and rejects the name, so the spinner and the name error show and no data changes.

  The states in the „Kategorie” sink stay. No new test: a kitchen sink state, guarded by `check:ui`.

### F8 — `categories.reorder-list` returns 204 instead of a redirect with a flash

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Http/Controllers/CategoryGroupController.php:79-110
- **Detail**: The Intent in Faza 3 §1 says writes redirect to `categories.index` with a flash. store/update/destroy/assign do. `reorder` copies the Budget reorder instead: `useHttp`, `noContent`, and the client reloads the props. This is a sensible choice for drag and drop, but it differs from the written plan.
- **Fix**: Record the difference as an addendum in the plan (or in ADR-0069), with no code change.
- **Decision**: FIXED — an addendum under the contract of Faza 3 §1 in `plan.md`. No code change.

### F9 — Manual items are checked without a recorded trace

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/category-groups/plan.md (Progress 2.3, 3.6, 3.7, 4.4–4.6, 5.4)
- **Detail**: The manual items are `[x]` with commit SHAs, but no commit or file records their run (device, dark theme, dev data). Automated tests partly cover 2.3 (v7 round trip) and 3.6 (the E2E spec). The rest (telephone, dark theme, "Razem" against the state before groups, keyboard in the selector) rests only on the checkbox.
- **Fix**: If they were confirmed after each phase, there is nothing to do. If not, run "Manual Testing Steps" 1–6 from the plan before merging into `development`.
- **Decision**: ACCEPTED — the user confirmed the manual items after each phase.
