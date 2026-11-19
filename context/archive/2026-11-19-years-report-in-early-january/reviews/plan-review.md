<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Years report in early January

- **Plan**: context/changes/years-report-in-early-january/plan.md
- **Mode**: Deep (verification inline, no sub-agent)
- **Date**: 2026-11-19
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 1 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding

18/18 paths ✓, 9/9 symbols ✓ (`ReportCategoryPanel.php:49/:85-87/:102`, `ReportYearsRange::resolve/throughOf`, `ReportYearsView.vue:21-23`, `ReportComparisonPeriod` `subYearNoOverflow`, `ReportsPageTest.php:206`, `PeriodReportTest.php:449`), brief↔plan ✓, ADR number 0108 unused in every worktree ✓.

Verified claims: Full Years rows are a superset of the Same-day rows (`YearsLineActuals` `full_count` ⊇ `cut_count` per line, the current year counts to 31 December in both modes), so the panel's `LogicException` is not reachable; no test asserts the exact key set of the `years` prop, so adding `default_range` breaks no existing assertion.

## Findings

### F1 — January rows in ReportComparisonPeriodTest do not match the fixture

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §1 (Comparison range)
- **Detail**: The dataset at `tests/Feature/ReportComparisonPeriodTest.php:59-75` always creates Budget 2026 with 2025 before it (the existing row is `[null, '2026-01-01', '2025-01-01', …]`). The planned rows `[1, '2027-01-01', '2026-01-01', '2026-01-01', true]` run against Budget 2026 on a day of 2027: `isInProgress` is false, the result is 2025-01-01..2025-01-31, not partial, so the test is red against unchanged code while Phase 3 forbids production changes.
- **Fix**: Rows `[1, '2026-01-01', '2025-01-01', '2025-01-01', true]` and `[1, '2026-01-31', '2025-01-01', '2025-01-31', false]`.
- **Decision**: FIXED

### F2 — The panel range rule lives in ADR-0101:11, the plan amends only ADR-0097

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 1 §1, §3; What We're NOT Doing
- **Detail**: ADR-0101:11 states that in the current year the tab counts up to the same day so the bars equal the tiles — the sentence ADR-0108 changes for January; ADR-0097:25 adds „by default with the Same-day Cut”. The plan named only ADR-0097 as amended and said „ADR-0101 stands”, which leaves ADR-0101 contradicting the code.
- **Fix**: ADR-0108 names ADR-0097:9/:25 and ADR-0101:11 as amended; Phase 1 §4 adds an „Amended in part by ADR-0108” note to ADR-0101; criterion 1.1 covers ADR-0101; „NOT Doing” keeps only the no-mode-switch rule of ADR-0101.
- **Decision**: FIXED

### F3 — Phase 2 writes the tests after the code, with no red step

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §7 (Tests)
- **Detail**: Gap 5 is a defect; the project's debugging rule requires a failing test before the fix. The tests were §7, after the predicate, shape, panel and page, with nothing proving the page and panel tests were red first.
- **Fix**: Phase 2 Overview gets an „Order (test first)” paragraph: §7 tests first, run red (panel on `mode: 'same_day'`, page on missing `years.default_range`, range on missing `opensOnSameDay`), then §1–§6.
- **Decision**: FIXED

### F4 — Comments and text anchors not covered by the plan

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §3, Phase 1 Manual, Phase 2 §3–§4
- **Detail**: (a) The docblock `ReportCategoryPanel.php:25-28` and the comment `resources/js/types/reports.ts:236` would go stale; (b) the ADR-0097 note „after line 3” would land before the ADR-0103 note on line 5; (c) Manual 1.3 cited „decisions 1–3 of the brief”, whose decision table is unnumbered.
- **Fix**: (a) added to Phase 2 §3 and §4; (b) „after the ADR-0103 note (line 5)”; (c) the decisions named in the criterion and Progress 1.3.
- **Decision**: FIXED
