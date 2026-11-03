<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Plan based entry warnings

- **Plan**: context/changes/plan-based-entry-warnings/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-03
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Success criteria run during review (worktree, SQLite): `php artisan test` on EntryToSpendInputsTest, MonthCategoryActualsTest, CsvImportTest, HandEntryMonthRemainingTest — 110 passed; `npm test` — 44 passed; `vendor/bin/pint --test --dirty` and `npm run check` — green. All 17 Progress steps are `[x]` with SHA. Not re-run here: PostgreSQL run and `composer ci:check` (steps 1.2, 4.3 — recorded as done in Progress), manual steps 3.3–3.7 and 4.4–4.6 (recorded as done in Progress).

Plan adherence: every planned item MATCH, no MISSING. One EXTRA, harmless: `resources/js/lib/import-preview-rows.ts:1-7` switched to relative imports (needed because the `@` alias is off in vitest). No "What We're NOT Doing" boundary crossed.

## Findings

### F1 — Import warnings recompute is O(n²) in the number of rows

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/import-to-spend-warnings.ts (per-row call), resources/js/lib/entry-to-spend-warning.ts:178-203
- **Detail**: `computeImportToSpendWarnings` calls `previewToSpendWarnings` once per row; each call rebuilds the month state and replays all earlier pending changes. `CsvImportParser::MAX_ROWS` is 5000, so a recompute on a maximal file is heavy, and any selection edit re-runs it.
- **Fix**: Keep an incremental per-month state across rows instead of replaying pending changes (tests would still hold).
  - Strength: removes the quadratic replay.
  - Tradeoff: more state in the loop; changes tested code.
  - Confidence: MED — not measured on a real large file.
  - Blind spot: real files are typically hundreds of rows.
- **Decision**: ACCEPTED — follow-up. Out of scope for this change; behaviour correct and tested. Recorded in follow-ups/review-fixes.md.

### F2 — Test gaps: cross-user scoping and server/TS parity

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/EntryToSpendInputsTest.php
- **Detail**: Test uses one user, so no assertion that another user's budgets/operations never appear (scoping is correct in code: `$user->budgets()`, per-budget actuals). No test that `EntryToSpendInputs` items through `ToSpendCalculator` equal `MonthPlanController`'s `to_spend_minor_units` for the same month. Missing TS cases: edit across year boundary into a different budget; original category absent from target budget.
- **Fix**: Add a cross-user test and a PHP parity test; add the two TS cases.
- **Decision**: ACCEPTED — follow-up. Code verified correct by review; tests would add regression protection. Recorded in follow-ups/review-fixes.md.

### F3 — AGENTS.md "Month logic lives twice" and calculator comment out of date

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: AGENTS.md:32, resources/js/lib/to-spend-calculator.ts:7-8
- **Detail**: Docs still said the TS calculator copy serves only the month plan page, but it now also feeds the entry and Import warnings.
- **Fix**: Update both texts to name `entry-to-spend-warning.ts` and `import-to-spend-warnings.ts`.
- **Decision**: FIXED — both texts updated in this review.

### F4 — `EntryToSpendInputs` is computed eagerly on every Operations request

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/EntryToSpendInputs.php, app/Http/Controllers/OperationsController.php:182, app/Support/MonthCategoryActuals.php:23-60
- **Detail**: About 8 queries per execution budget, constant in months and categories (query-count test exists). `forMonths` groups by (category, date) and hydrates models; payload is unbounded for multi-year users.
- **Fix**: Optionally use `toBase()` and an optional/deferred Inertia prop later.
- **Decision**: SKIPPED — acceptable now.

### F5 — Lost server-side test coverage of Month Remaining import flags

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/ImportMonthRemainingWarningTest.php (deleted)
- **Detail**: Deleted tests covered only server flags nobody read on the frontend; the live TS Month Remaining path was already untested.
- **Fix**: None required.
- **Decision**: SKIPPED — not a regression.

### F6 — Import warnings depend on CSV row order; month label helper duplicated

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/import-to-spend-warnings.ts; ExpenseFormDialog.vue:293, IncomeFormDialog.vue:171, ImportPreviewRow.vue:561-565
- **Detail**: The row that crosses zero depends on file order (final month total is correct); allowed by the plan. The month-label building is copied three times.
- **Fix**: Optionally extract a shared label helper in `resources/js/lib`.
- **Decision**: SKIPPED — minor.
