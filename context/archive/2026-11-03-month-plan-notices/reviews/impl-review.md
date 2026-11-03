<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: S-07 Month Plan Notices

- **Plan**: context/changes/month-plan-notices/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-03
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verification run

| Check | Result |
|-------|--------|
| `php artisan test --compact tests/Feature/DashboardTest.php` | pass (44 tests, 849 assertions) |
| `php artisan test --compact` | pass (817 tests, 815 passed, 2 skipped) |
| `composer types:check` | internal PHPStan error (stale shared `/tmp/phpstan` cache points at a deleted worktree); with an isolated `tmpDir`: pass, 0 errors |
| `composer lint:check` | pass |
| `npm run types:check` | pass |
| `npm run check` | pass |
| `npm test` | pass (84 tests) |
| grep for `paced_month_remaining` / `DashboardPacedCategoryMonthAlerts` in app, tests, resources, lang, `.docs/specs`, `.ai`, `CONTEXT.md` | empty |
| grep for "All four current kinds are exceeded" in `.ai/rules/support.md` | empty |
| `composer ci:check` (step 2.5) | not run as one command; its parts were run individually, see F2 |

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — design-system.md still describes the pre-S-07 notices

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: .docs/design-system.md:42-49
- **Detail**: The "Alerty (Dashboard notices)" section says "Żaden rodzaj alertu go [warning] jeszcze nie emituje", describes "trzy widoczne wiersze", sub-headings per group, a grey reason under the amount and the reason "Pozostało w miesiącu". After S-07 two kinds emit `warning`, the grid is flat with six visible cards, and Month Remaining is not a notice. The plan updated ADR-0031/0032, the spec and support.md but missed this file, so the plan itself had a gap.
- **Fix**: Update the paragraph: name `month_plan_not_approved` and `month_pace_ahead` as `warning`, describe the flat grid with header pills and six visible cards, drop the sub-headings and the "Pozostało w miesiącu" reason.
- **Decision**: FIXED (Fix now) — .docs/design-system.md "Alerty" paragraph rewritten

### F2 — Step 2.5 (`composer ci:check`) is unchecked in Progress

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/month-plan-notices/plan.md:301
- **Detail**: Phase 2 was closed and committed (7c16c58) with 2.5 still `[ ]`. Every component of the check passes when run separately (see table), but `composer types:check` fails in this environment because the shared PHPStan result cache in `/tmp/phpstan` references a removed worktree path. This is an environment problem, not a defect in the change.
- **Fix**: Clear `/tmp/phpstan` (`vendor/bin/phpstan clear-result-cache` did not help; remove the directory), run `composer ci:check`, then tick 2.5 in the plan.
- **Decision**: FIXED (Fix now) — removed /tmp/phpstan/cache, `composer ci:check` passes (PHPStan 0 errors, 815/817 tests, 2 skipped), step 2.5 ticked in plan.md

### F3 — Month notices rely on the controller for the year/execution guard

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/DashboardNotices.php:~99-101, ~196
- **Detail**: `one_off_over_limit` checks `isExecution()` and `$today->year === $budget->year` itself. The new month notices are guarded only by a non-null `MonthToSpend` with state != `none`. Correct today (the controller builds it only for the current-year execution budget; the only caller is `DashboardController.php:113`), but another caller could pair a wrong `$budget->year` with `$today->month`.
- **Fix**: Add `$budget !== null && $today->year === $budget->year` to the shared `$monthReading` condition.
- **Decision**: FIXED (Fix now) — guard added to `$monthReading`, redundant `$budget !== null` removed from the warning block; Pint, PHPStan and the full suite pass

### F4 — `one_off_over_limit` link is still the default fall-through

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/DashboardNotices.vue:242-249
- **Detail**: The plan asked for `one_off_over_limit` to stop being the default branch and get an explicit one. Behaviour is correct (the final `return` is the full-year Operations link, and TS checks it through `notice.category_name`); only the stated intent is unmet.
- **Fix**: Wrap the last `return` in `if (notice.kind === 'one_off_over_limit')`, or accept as-is.
- **Decision**: FIXED (Fix now) — explicit `one_off_over_limit` branch in `noticeHref`, ending in `return notice satisfies never` so a new kind fails type-check; vue-tsc, vp check and JS tests pass

### F5 — Button-cards have no processing/disabled state

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/DashboardNotices.vue:291-302
- **Detail**: The "do wydania" tile (`Dashboard.vue:270-289`) disables itself while the POST is in flight. The notice cards do not, so a double click sends two POSTs. The action is idempotent, so the effect is harmless.
- **Fix**: Use the `processing` slot prop of `<Link>` to disable the card, or accept as-is.
- **Decision**: FIXED (Fix now) — month button-cards are disabled while the POST is in flight (local `monthPlanRequestPending` ref driven by Link `@start`/`@finish`, `disabled:opacity-60`); type check, lint and JS tests pass; not yet checked in a browser
