# Review follow-ups: new-budget-from-category-list

Source: `reviews/impl-review.md` (2026-11-10). These items are out of scope for S-20 and belong in a separate change.

## F5 — Concurrent duplicate budget submit ends in a 500

- **Where**: `app/Actions/CreateBudget.php:24` (`$user->budgets()->create(...)` inside `DB::transaction`)
- **What**: Two requests for the same year can both pass `StoreBudgetRequest`'s `Rule::unique('budgets', 'year')`. The second one then hits the `unique(user_id, year)` constraint (`2026_09_07_180000_create_budgets_table.php:21`), and the unhandled `UniqueConstraintViolationException` becomes a 500. This predates S-20.
- **Pattern to follow**: `CreateCategory` / `UpdateCategory` catch `UniqueConstraintViolationException`, `report()` it with its cause and throw a `ValidationException` on the field.
- **Start with**: a failing Feature test (simulate the race, for example by creating the budget between validation and the action, or by calling the action twice), then the catch with `report()`, and a `ValidationException` on `year`.
