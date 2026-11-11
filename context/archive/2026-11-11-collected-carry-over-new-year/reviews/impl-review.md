<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Zebrane startowe z 31 grudnia w Budżecie

- **Plan**: context/changes/collected-carry-over-new-year/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-11
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 4 observations (F8 added during the F1 fix)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Verification run

- Phase test files on PostgreSQL (`BudgetCollectedAtYearStartTest`, `BudgetTest`, `IrregularCollectedTest`, `UiTranslationKeysTest`, `BudgetKitchenSinkTest`): 94 passed.
- `composer ci:check` in the Sail image: `npm run check`, `check:ui`, `vue-tsc`, `vp test` (567 passed), Pint and PHPStan were clean. The PHP suite on the shared `testing` database had 4 failures: `relation "users" does not exist` and a deadlock in `UserSnapshotCommandTest` and `WelcomeKitchenSinkTest`, both files untouched by this change. During the run the `category-merge` worktree was running its own `composer ci:check` on `DB_DATABASE=testing`. A rerun of `php artisan test` on a dedicated database `testing_s27b` was green: 1364 passed, 2 skipped. A first attempt on `testing_s27` was unreliable and was discarded.
- `npm run build && npm run types:check` (in the Sail image): passed.
- `vendor/bin/sloppy ci` (in the Sail image): passed, nothing at high or above.
- Playwright (`npx playwright test`, criterion 2.2): not re-run. Another session's E2E server was running on the shared dev database at the same time. The spec was reviewed by reading it.
- The Critical Implementation Details all match:
  - Presence is decided by `irregularPeriodIn(…, year, 1)` (`app/Support/BudgetCollectedAtYearStart.php:131`), never by the amount.
  - `is_forecast` is `$year > $today->year`, with today in UTC and the budget phase not involved (`:139`).
  - There is one lazy `IrregularCollected` per request (`:135`, `??=`).
  - Every test bullet in "4. Testy strony" is covered with the plan's numbers.
- Unplanned changes: only `change.md` and `plan-brief.md` (planning artefacts), plus an extra "Naprawa dachu" fixture for the no-caption state, which fits the plan.

## Triage

- Fixed: F1, F2, F3, F4, F5, F6, F7, F8 (F8 with a variant of Fix A, chosen by the user) (8)
- Skipped / accepted / rules: none
- Gate after the fixes: `composer ci:check` in the Sail image on a dedicated database `testing_s27c`, exit 0. Format and lint clean, `vp test` 567 passed, PHPStan no errors, PHP suite 1365 passed and 2 skipped. The E2E spec `collected-year-start.spec.ts` passed on the proven preview. The full Playwright suite was not run, because `scripts/e2e-server.sh` would remove the other session's `household-treasurer-e2e` container.
- Incident during the triage: the first preview (`artisan serve` with `-e DB_DATABASE=s27_preview`) served the dev database `household_treasurer`, because `artisan serve` drops the `-e` override. Screenshots and two E2E runs wrote a category „Rekolekcje”, a 2027 draft and two archived E2E categories into the demo account (user 2, `test@example.com`). The user's own account (user 1) was untouched and no existing row was deleted. With the user's approval the rows were deleted in one transaction (budget 7 with its lines, categories 48–50 with their rhythms); nothing depended on them. The later preview uses `php -S` like `scripts/e2e-server.sh`, and its database was proven by a marker user name, which shows as „Preview S27” in the sidebar of the screenshots.

## Findings

### F1 — Screenshots missing, Progress 2.5 ticked

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/collected-carry-over-new-year/screenshots/ (N/A)
- **Detail**: Phase 2 "Kitchen sink i design system" requires screenshots of `/dev/kitchen-sink/budget` and `/budget` at 1280 and 390 px, light and dark, in `screenshots/`. The directory does not exist and is not gitignored, yet Progress 2.5 ("pokazuje nowe stany w obu motywach, w 1280 i 390 px") is ticked against e690a61. The manual check has no observable evidence.
- **Fix**: Take the 8 screenshots through the worktree preview (`context/foundation/agents/worktree-preview.md`) with playwright-cli and commit them to `screenshots/`. Re-tick 2.5 only after they land.
- **Decision**: FIXED — 8 screenshots taken on a preview with database `s27_preview` (demo household plus an irregular „Rekolekcje” with zebrane startowe 300,00, added through the Kategorie dialog, and the 2027 draft created again through „Nowy budżet”). In the browser, budget 2026 has no caption and the 2027 draft shows „zebrane startowe 300,00 · prognoza”. The 390 px shots exposed F8.

### F2 — E2E cleanup: one failure skips the rest and hides the original error

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/collected-year-start.spec.ts:86
- **Detail**: The `finally` first deletes the budget, then archives the category, as one chain.
  - If any budget-deletion step throws (menu not found, `toHaveCount(initialSegmentCount)` times out), the archive step never runs and the category stays in the demo database.
  - An error thrown in `finally` also replaces the original assertion error, which goes against "never hide evidence".
  - The category is created before `try` (lines 44-55), so a timeout after "Zapisz" leaves it unarchived.
  - `createdBudget = true` is set only after `toBeHidden()` (line 72), so a budget created by a POST that succeeded can leak.
  - `irregular-collected.spec.ts` and `sidebar-year-refresh.spec.ts` share these patterns.
- **Fix**: In this spec, move the category setup inside `try` behind a `createdCategory` flag. Set `createdBudget` right after the "Utwórz budżet" click, or decide in `finally` from the segment count. Run each cleanup step in its own `try/catch` that collects errors. Rethrow the original test error first, then the cleanup errors.
  - Strength: The test stays green when everything works, and a failure reports its real cause and leaves the demo database clean for the next run.
  - Tradeoff: A few more lines in the spec. The two older specs keep the weaker pattern unless they get the same change in a separate commit.
  - Confidence: HIGH — the failure modes follow directly from JS `finally` semantics and the order of the awaits.
  - Blind spot: Not run here (another session's E2E server was using the shared database).
- **Decision**: FIXED — the category setup now sits inside `try` behind `createdCategory`; `createdBudget` is set right after the click; each cleanup step runs in `runCleanup()`, which collects failures with their `cause`; the test's own error is rethrown first and cleanup failures go to `console.error` (an `AggregateError` when only the cleanup failed). Verified against a preview server: the normal run passed (2 passed, with setup). A temporary copy expecting 500,01 failed with the original `toHaveText` error (line 112), and the budget and category were cleaned up afterwards. The copy was deleted. Both runs used the dev database's demo account by mistake (the preview server ignored `-e DB_DATABASE`); the rows were deleted with the user's approval.

### F3 — Caption uses `meta`, `js.md` says money in a row uses `amount`, no exceptions

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/BudgetCategoryRow.vue:119, .ai/rules/js.md:17
- **Detail**: `.ai/rules/js.md:17` says that in every table (cells, day rows, sum rows and captions inside a row) money uses `formatMoneyAmount` with the `amount` style, "there are no exceptions". The new caption uses `meta text-muted-foreground tabular-nums`. That is what the plan prescribed, and it copies the month plan "zebrane X" (`MonthPlanItemRow.vue:155`). So two places in code now contradict a rule that claims no exceptions. Phase 2 edited `js.md` but did not settle this.
- **Fix**: Amend `js.md:17` to name the exception: a caption under the category name in a large `CategoryChip` (month plan "zebrane", Budget "zebrane startowe", ADR-0076) uses `meta` with `formatMoneyAmount`.
- **Decision**: FIXED — `.ai/rules/js.md:17` now names the one exception (the `#subtitle` line in a large `CategoryChip`: month plan "zebrane X", Budget "zebrane startowe X", ADR-0076) instead of "there are no exceptions". The frontmatter is unchanged, so `boost:index-rules` is not needed.

### F8 — At 390 px the draft caption wraps over 4–5 lines and splits the amount

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/BudgetCategoryRow.vue:119, resources/css/app.css:1588
- **Detail**: Found while fixing F1 (screenshots `budget-2027-draft-390-*.png`, `kitchen-sink-budget-390-*.png`).
  - In the draft at 390 px the row shows only the category tile, without the name; the layout predates this change.
  - The new caption still renders there, in a column about 60 px wide. It wraps into „zebrane / startowe / 300,00 / · prognoza”.
  - In the kitchen sink „1 234,56” breaks between „1” and „234,56”, because `formatMoneyAmount` separates thousands with a plain space and `meta` has no `whitespace-nowrap`.
  - The caption reads as an orphan number next to a nameless tile. Progress 2.5 claimed 390 px was checked.
- **Fix A ⭐ Recommended**: Below `md`, put the caption on its own grid line under the row (a named class in `app.css`, per `css.md`), and keep the text on one line with `whitespace-nowrap`.
  - Strength: Phone users keep the number the ADR puts in every budget, and it reads in one line.
  - Tradeoff: A grid-area change in `app.css` for the draft and execution grids, plus re-checking every kitchen-sink state at 390 px.
  - Confidence: MED — the grid areas are named (`.budget-category-cell-*`), but the draft grid template at `<md` was not inspected.
  - Blind spot: Why the draft row hides the name at 390 px; the execution rows (Czynsz) show it.
- **Fix B**: Hide the caption below the breakpoint where the draft row hides the name, and add `whitespace-nowrap` to it.
  - Strength: Two classes in the component. The row stays as it was before S-27 on a phone.
  - Tradeoff: No „zebrane startowe” in the draft on a phone, which narrows ADR-0076's "every budget" and needs a line in the ADR.
  - Confidence: HIGH — a visibility class, no layout change.
  - Blind spot: Whether the execution rows at 390 px have the same wrapping (Rekolekcje in execution was not cropped).
- **Decision**: FIXED (a variant of Fix A, chosen by the user) — the cause was wider than the caption: in the draft below `md` the grid `2.5rem | 1fr | 8rem | 4.5rem` left the category ~60 px, so `truncate` hid the name in every draft row on a phone (before S-27 too).
  - `resources/css/app.css`: `@variant max-md` gives `.budget-category-row.budget-category-grid-draft` two lines, the name (with the caption) and actions across the row and the plan on the second line, like the execution rows. Group and total rows keep one line, and `md`+ is unchanged.
  - `BudgetCategoryRow.vue`: the amount and „· prognoza” sit in one `whitespace-nowrap` span, so the caption can only break after „zebrane startowe”.
  - Verified on a preview with `php -S`, with the database proven by a marker user name („Preview S27” in the sidebar of the screenshots). At 390 px names are visible and „1 234,56 · prognoza” stays whole. At 1280 px the row is unchanged. The 8 screenshots were retaken.
  - `npm run check`, `check:ui`, `vp test` (567) and the E2E spec (2 passed) are green.

### F4 — Kitchen sink texts do not match what the section renders

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/BudgetKitchenSink.vue:584, :452
- **Detail**: The "Zebrane startowe (ADR-0076)" heading lists „realizacja „Rekolekcje” 200,00 bez prognozy”. The execution list in that section actually holds Czynsz and the archived „Stare ubezpieczenie”, while „Rekolekcje” is rendered in the earlier execution section (fixture at :190). The plan asked for the `default` matrix entry to list the new states. It (:452) names only the execution rows with and without the caption. Forecast, 0,00, long name, row under a group and archived are not listed.
- **Fix**: In the heading, point to „Rekolekcje” in the execution section instead of listing it here. In the `default` entry, list the draft forecast, forecast 0,00, long name, row under a group and archived states.
- **Decision**: FIXED — the „Zebrane startowe” heading now says that „Rekolekcje” 200,00 without a forecast is in „Realizacja wydatków” (confirmed: `executionExpense`), and notes the two-line draft row below `md` from F8. The `default` matrix entry lists the forecast, forecast 0,00, long name, row under a group, archived with caption and irregular without caption states, plus the two-line draft row.

### F5 — Badges written twice in `BudgetCategoryRow.vue`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/BudgetCategoryRow.vue:106, :137
- **Detail**: The "Zarchiwizowane" and "przekroczono" badges are written once in the `lg` `#badges` slot and once in the small-chip branch. `CategoryChip` renders `#badges` only at `lg`, so the branch is forced. The copy is not: a future badge added to only one branch would silently disappear on rows that have, or lack, "zebrane startowe".
- **Fix**: Render both branches from one computed badge list (`{ variant, label }[]`) with a `v-for`.
- **Decision**: FIXED — `nameBadges` (a computed `{ variant: BadgeVariants['variant']; label }[]`: archived, otherwise over limit, otherwise none) is rendered by `v-for` in the `lg` `#badges` slot and next to the small chip. `vue-tsc`, `npm run check` and `check:ui` are clean.

### F6 — No test at the forecast boundary

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/BudgetCollectedAtYearStartTest.php:146
- **Detail**: `is_forecast` is tested on 2026-11-11 and 2027-03-10 only. Nothing pins the boundary itself, so an accidental `>=` or a local-timezone `today()` would pass the suite. ADR-0076 deliberately puts the boundary at 1 January UTC.
- **Fix**: Add a pair for the 2027 budget: `travelTo('2026-12-31 23:59:59')` gives `is_forecast` true, and `travelTo('2027-01-01 00:00:00')` gives false.
- **Decision**: FIXED — test „the forecast mark ends exactly at 1 January of the Budget year” (60_000 with `is_forecast` true at 2026-12-31 23:59:59, false at 2027-01-01 00:00:00).
  - On PostgreSQL (dedicated `testing_s27c`) the file has 12 passed.
  - A temporary mutation `>` → `>=` in `BudgetCollectedAtYearStart.php:44` made this test (and the execution test) fail. It was restored, and `git diff -- app/` is empty.
  - `config/app.php` has `timezone` UTC, so the test pins the comparison. A local-time `today()` would only show up if the app timezone changed.
  - Pint passed.

### F7 — Exact "+5 queries" assertion couples the page test to `IrregularCollected` internals

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/BudgetCollectedAtYearStartTest.php:304
- **Detail**: The plan asked for two invariants: the same query count for 1 and 3 Irregular Categories, and no collected-read queries for the 2026 budget with a November marking. The test additionally asserts exactly `+ 5`. Any legitimate change to `IrregularCollected::load()` breaks a Budget page test that is not about that loader. The "does not grow" test (:273) already guards the cost.
- **Fix**: Replace the `+ 5` assertion with `toBeGreaterThan(yearStartQueryCount($plain, 2027))`. Keep the "none for a Budget without a January reading" equality.
- **Decision**: FIXED — the assertion is now `toBeGreaterThan(yearStartQueryCount($plain, 2027))`, and the test is renamed „the Collected is read only for a Budget with a January reading”. The equality for the 2026 Budget with a November marking stays, and the "does not grow" test still guards the constant cost. Pint passed. `BudgetCollectedAtYearStartTest`, `BudgetTest`, `BudgetKitchenSinkTest` and `UiTranslationKeysTest` on PostgreSQL: 82 passed.
