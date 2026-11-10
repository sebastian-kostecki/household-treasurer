<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Category Edit and Archive

- **Plan**: context/changes/category-edit-and-archive/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-10
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Success criteria (run 2026-11-10)

- PostgreSQL, all Feature/Unit tests named in phases 1–5 (20 files, Sail): 363 passed.
- `composer ci:check` (Sail): exit 0; 1080 passed, 17 skipped; `npm run check`, `check:ui`, `types:check` and `npm test` pass inside it.
- `vendor/bin/sloppy ci`: 0 findings, both on the host (herd-lite PHP 8.4.1) and in Sail.
- `playwright test tests/e2e/categories.spec.ts`: 4 passed (2.2 min).
- Manual items 1.4, 2.5, 3.6–3.8, 4.6–4.8, 5.5, 5.6 are `[x]` with phase SHAs. The diff has no observable evidence for them, which is normal for checks done by hand in a browser.

## Findings

### F1 — Podgląd „do wydania” pomija przychód na kategorii, która dołączy

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/CategoryList/CategoryChoiceOptions.php:101-104, app/Support/EntryToSpendInputs.php:118-134
- **Detail**:
  - Since phase 4, the income dialog (and Import) offers active income Categories that have no line in the year's Budget.
  - `EntryToSpendInputs` includes income Categories that already have a line (`:123`). Its join entries come from `joiningEntries()`, which takes only `CategoryType::Expense` (`:104`).
  - `applyChange` in `entry-to-spend-warning.ts:107-113` quietly skips a `category_id` that is not in `budget.categories`.
  - **Failure scenario:** an income of 500 zł on a lineless income Category in a month with a plan. The preview shows "do wydania" unchanged; after the save the Category joins and "do wydania" goes up by 500 zł. The "poniżej zera" warning can stay on.
  - Import uses the same `entryToSpend` (`CsvImportController.php:145`).
  - **This is a plan gap:** Phase 4 #5 restricted the join entries to "kategorii listy typu wydatek", which is right for Month Remaining (expenses only) but not for To Spend.
- **Fix**: `joiningEntries()` takes the type (or returns both types). `EntryToSpendInputs` also adds join entries for income, with the income `month_item_kind` the way the existing branch at `:123` does it. Add a "preview = state after save" test for an income, like the one for expenses.
  - Strength: The same "przed → po" guarantee the plan already requires for expenses (Critical Implementation Details); one source for both types.
  - Tradeoff: A third variant of the entry (income) and one more query or a wider filter in `joiningEntries`.
  - Confidence: HIGH — the type filter and the early `return` in `applyChange` were both checked in the code.
  - Blind spot: Not checked whether the Import To Spend warnings (`import-to-spend-warnings*.ts`) need more than the shared input.
- **Decision**: FIXED — `joiningEntries(…, CategoryType $type)`; `EntryToSpendInputs` adds income join entries (`month_item_kind: null`). The red test `IncomeTest::test_to_spend_reading_before_the_save_equals_the_one_after_it_for_an_income_category_that_joins` is now green; 137 passed on SQLite and PostgreSQL.

### F2 — Odrzucona zmiana kolejności (422) nie cofa listy i nie pokazuje toastu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/Categories.vue:94-106
- **Detail**:
  - The rollback and the toast happen only in `catch`. Inertia 3's `useHttp.patch` does not reject on 422: it sets `errors`, calls `onError` and returns `undefined` (`node_modules/@inertiajs/vue3/dist/index.js:2112-2118`).
  - `ReorderListCategories` returns 422 when the set of ids differs from the active list, for example after a change in another tab. The page then keeps the order the server refused, without any message.
  - The bare `catch {}` also drops the cause of a 5xx or network error (CLAUDE.local.md: "Nigdy nie ukrywaj dowodów").
  - The same shape was copied from `BudgetCategoryList.vue:171-186`.
- **Fix**: roll back and toast also on `reorderHttp.hasErrors` / `onError`, and log the caught error with its cause (`console.error`). Fix `BudgetCategoryList.vue` the same way, with a test in the sink or E2E for 422.
- **Decision**: FIXED — `onError` → `restoreOrder()` in `Categories.vue` and `BudgetCategoryList.vue`; `catch` logs `new Error(…, { cause })`. New E2E `an order the server refuses (422) is rolled back with a toast`: red on HEAD, green after the fix (5/5).

### F3 — Reguła `categories.md` kieruje do martwych metod zamiast klas wyboru

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: .ai/rules/categories.md:7,9
- **Detail**:
  - The bullet at line 7 still tells agents to use `forYear()` for operations and `amountsByLine()` for the Month Plan. After phases 4–5 neither has a caller in `app/`: `lockLine` and `OperationCategoryChoice` use `forBudget()`, and both Month Plan actions use `partitionAmounts()`. Only `BudgetLineResolverTest` calls them.
  - The bullet about selectors names "`forBudget()` and the option classes built on it" instead of `CategoryChoiceOptions` / `OperationCategoryChoice` / `MonthPlanJoinCandidates`.
  - Plan Phase 1 #4 asked that the rules say choice goes through the phase 4 classes. A future agent will reach for the old methods.
- **Fix**: rewrite both bullets to name `OperationCategoryChoice::check` + `lockLine` (operations), `CategoryChoiceOptions` (options), `partitionAmounts` + `JoinBudgetAtFirstUse` (Month Plan), then run `php artisan boost:index-rules`. Removing `forYear`/`amountsByLine` with their tests is a separate decision (it deletes tests).
- **Decision**: FIXED — both bullets now name `OperationCategoryChoice`/`lockLine`/`partitionAmounts` and `CategoryChoiceOptions`/`MonthPlanJoinCandidates`/`joiningEntries`; `boost:index-rules` run. `forYear`/`amountsByLine` remain (dead in `app/`, covered by `BudgetLineResolverTest`).

### F4 — Odmowa usunięcia kategorii z historią to 403, a nie komunikat akcji

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Policies/CategoryPolicy.php:21, app/Actions/DeleteCategory.php:18
- **Detail**:
  - The `delete` policy already calls `CategoryTypeChange::isLocked`, which is what the plan prescribed. A Category with history therefore gets 403 (`CategoryListWriteTest.php:266-268`).
  - `DeleteCategory`'s friendly message ("…zarchiwizuj ją zamiast usuwać") is reachable only in the race between the policy and the lock.
  - **Scenario:** the page is open with `can_delete = true`, an operation is added in another tab, then "Usuń" is clicked. The user sees a 403 instead of the message in `ConfirmActionDialog`.
  - Single user, so it is rare.
- **Fix**: `delete` checks ownership only and the action decides (422 with the message). The 403 assertion becomes a 422 + message assertion; this changes the plan's requirement.
  - Strength: The message the plan wrote for `DeleteCategory` becomes reachable; one place for the rule.
  - Tradeoff: Changes a plan contract and an existing assertion; the `delete` policy is no longer a single gate.
  - Confidence: MED — the flow was read, not exercised in the browser.
  - Blind spot: How the 403 looks today in `ConfirmActionDialog` (Inertia error page or toast).
- **Decision**: FIXED — `CategoryPolicy::delete` checks ownership only; `CategoryListWriteTest` asserts a `category` error with the message (red → green; 29 passed on SQLite and PostgreSQL); addendum in plan Phase 2 #3.

### F5 — Ścieżka zapisu jest węższa niż walidacja (obrona w głąb)

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/ManageFinancialOperation.php:221-252, app/Actions/JoinBudgetAtFirstUse.php:39, :46
- **Detail**: All of this is safe today, because `OperationCategoryChoice` runs before every write (the Import commit re-validates in `ImportFinancialOperations`).
  - `lockLine` does not refuse a changed Category that is archived on the list while its line is active, and with an existing line it does not check the Budget phase.
  - `JoinBudgetAtFirstUse::handleAmounts` loads `Category::whereIn('id', …)` without `user_id`, and `handle()` checks neither ownership nor the phase.
  - A delete that wins the lock before a concurrent join ends in an FK violation (500), because `ON CONFLICT DO NOTHING` does not cover FKs.
  - The `insertOrIgnore` race itself was checked and is safe on PostgreSQL.
- **Fix**: in `lockLine` add `$category->isArchived() && $categoryId !== $currentCategoryId` → refusal; in `handleAmounts` scope by `$budget->user_id`; accept the delete race.
- **Decision**: FIXED — `lockLine` refuses a changed Category with an archived line or archived on the list; `handleAmounts` is scoped by `$budget->user_id` and refuses an unknown id. Two action-level tests (`ExpenseTest`, `ChangeMonthPlanTest`) red → green; 212 passed on SQLite and PostgreSQL. The delete-vs-join race (FK 500) was accepted.

### F6 — Test komunikatów jest odwrotnością zdania z planu

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/ExpenseTest.php:301
- **Detail**: Phase 4 #7 asked that "brak budżetu roku i brak pozycji zarchiwizowanej kategorii dają różne komunikaty" (and #3: "reguła rozdziela te przypadki"). The test `…for_a_year_without_a_budget_and_for_an_archived_category_without_a_line` asserts `assertSame($noBudget, $noLine)`. The code follows the plan's decision table, which gives both cases the same text, so the plan contradicted itself. The choice is not recorded anywhere.
- **Fix**: add a note to the plan (Phase 4 #7) or to `change.md` that both cases keep today's message under the decision table.
- **Decision**: FIXED — addendum in plan Phase 4 #7 (same message, following the decision table).

### F7 — Drobne odchylenia i porządki

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: various
- **Detail**:
  - `tests/Feature/DevMonthPlanSinkTest.php` was not touched, although the plan lists it; the new `edit-join` state (`MonthPlanSink.vue:199`) has no assertion.
  - `MonthPlanJoinCandidates.php:66-71`: the docblock points at `MonthPlanController::itemRow` (now `MonthPlanItemRow::forLine`), and `row()` returns `array<string, mixed>` instead of the shared shape, so key equality is guarded only by the test.
  - `CategoryListController::destroy` takes an unused `Request $request`; the same change removed exactly this parameter from `archive`/`restore`.
  - `Categories.vue` `onDragEnd` sends PATCH even when the order did not change.
  - The delete button is `destructive`, not `outline-destructive` as in the plan (consistent with Budget).
  - The plan still says ADR-0066; the file and code say 0067.
  - The drag handle has `tabindex="-1"` and no keyboard alternative (a copy of the Budget gap).
- **Fix**: one cleanup commit: an assertion for `edit-join` in `DevMonthPlanSinkTest`, the docblock and `@phpstan-type` of the row, remove `$request`, a no-change guard in `onDragEnd`, a note about 0067 in the plan.
- **Decision**: FIXED (partly):
  - Done:
    - `@phpstan-type MonthPlanRow` in `MonthPlanItemRow`, imported into `MonthPlanJoinCandidates`, with the docblock fixed; PHPStan OK.
    - `$request` removed from `destroy`.
    - A no-change guard in `onDragEnd`.
    - An ADR-0067 addendum in plan Phase 1 #1.
  - Skipped:
    - The `edit-join` assertion in `DevMonthPlanSinkTest`: the state exists only in the frontend fixtures, and a PHP test sees only the 404.
    - The delete button variant and the drag handle without a keyboard: left as they are, consistent with Budget.
