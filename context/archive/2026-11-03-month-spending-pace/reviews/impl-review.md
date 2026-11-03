<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: S-05 Tempo wydatków względem upływu dni miesiąca

- **Plan**: context/changes/month-spending-pace/plan.md
- **Scope**: Full plan (Phase 1, Phase 2 of 2)
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-03
- **Verdict**: APPROVED
- **Findings**: 0 critical, 0 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Evidence

Reviewed commits: 88c5ff1 (p1), 849d4a3 (p2), 45e41c7 (epilogue). Review done directly (diff ≈ 700 lines of code and tests), without subagents.

- `tests/Unit/MonthElapsedTest.php`, `MonthPaceCalculatorTest.php`, `tests/Feature/MonthPaceTest.php`, `MonthPlanPageTest.php`, `MonthToSpendTest.php` on PostgreSQL (Sail image on the worktree): 59 passed, 653 assertions. A first run showed 6 failures with `column "month_item_kind" ... does not exist`; the rerun was green. Cause: the shared `testing` PostgreSQL database is also used by other parallel sessions, so a concurrent migrate/refresh breaks it. Environment race, not a code defect.
- `npm test`: 6 files, 49 tests passed.
- `npm run types:check` and `npm run check`: pass (206 files formatted, 0 warnings).
- `vendor/bin/sloppy ci` on the host: 0 findings, 26 baselined hidden.
- `composer ci:check` in the Sail image on the worktree: 778 passed, 2 skipped, 5991 assertions.
- Manual items 2.6–2.11 are ticked in Progress. They cannot be proven from the diff; a `month-pace-preview` container is running, which is consistent with the preview step having been done.

Plan vs code: every file in "Changes Required" exists and matches the contract (MonthElapsed, MonthPaceCalculator PHP/TS, MonthPace, controller key `month_pace` with `future → null`, TS type in `types/month-plan.ts` and `MonthPlanReading`, `MonthPaceMeter.vue`, `pacePreview`, `lang/pl/ui.php`, `AGENTS.md`, `CONTEXT.md`). No file outside the plan except plan/roadmap bookkeeping. "What We're NOT Doing" is respected: no Dashboard/Budget changes, no `DashboardNotices`, no per-category pace, no migration or snapshot change.

## Findings

### F1 — Edit-mode pace preview and meter wording are untested

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/pages/MonthPlan.vue:292, resources/js/components/MonthPaceMeter.vue:36
- **Detail**: The calculator pair is covered, but the mapping from rows to pace items in `pacePreview` (`isEditableRow` → plan 0, parsed amount, `is_one_off`) and the `pace_reading` split around `:pace` in `MonthPaceMeter` have no automated test. This matches AGENTS.md, which already notes that the preview files have no tests, and the plan's manual steps 2.7–2.8 cover it. Risk: a later change to row fields or to the translation string breaks the preview silently.
- **Fix**: Accept for now, or extract the rows → `MonthPaceItem[]` mapping into `resources/js/lib` and test it with `vite-plus/test`.
- **Decision**: FIXED — mapping and the three calls extracted to `resources/js/lib/month-pace-preview.ts` (`monthPacePreview`), used by `pacePreview` in `MonthPlan.vue`; new `month-pace-preview.test.ts` (4 tests: canonical pace, bill/one-off/income plan changes do not move it, lowering a running plan flags ahead, zero running plan gives null). The `pace_reading` split in `MonthPaceMeter` stays untested (no component test setup in the project).

### F2 — No page-level assertion for a past month with a running plan

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/MonthPlanPageTest.php:210
- **Detail**: `test_past_approved_month_is_read_only` asserts `elapsed_day == days_in_month`, the last-day `as_of` and `percent = null`, but its fixture has only income and a bill, so the past-month path with a non-null percent is never asserted through `assertInertia`. The calculator test (31/31, 115% vs 105%) and `MonthPaceTest` cover the maths; the plan's "pool without running plan" case (bill and one-off) is covered only by this past-month fixture, without a one-off row.
- **Fix**: Add one past-month `assertInertia` case with a running item and an operation, asserting `percent` and `is_ahead`.
- **Decision**: FIXED — added `test_past_approved_month_reads_the_pace_against_the_whole_month` (March, food plan 100 000, operation 130 000: `as_of` 2026-03-31, percent 130, `is_ahead` true). `MonthPlanPageTest` + `MonthPaceTest` pass on PostgreSQL (41 tests).
