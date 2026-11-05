<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Tokeny design systemu „C · Atrament i morski”

- **Plan**: context/changes/design-tokens-atrament/plan.md
- **Scope**: Full plan (phases 1-3, commits f7486c6, 064f733, ef8ace1)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-05
- **Verdict**: APPROVED
- **Findings**: 0 critical 1 warnings 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — Manual checks 2.7, 2.8, 3.6-3.12 still unchecked

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/design-tokens-atrament/plan.md (## Progress, phases 2 and 3 Manual)
- **Detail**: All automated items are [x] with SHAs and DesignTokensTest/FontStackTest pass (5 tests, run with host PHP, SQLite-agnostic source tests). Screenshots are committed in ef8ace1 (sidebar, dashboard, operations, landing, destructive, active-hover; light/dark; 1280/390), but the manual boxes were not ticked, so the change is not formally complete and change.md is still `implementing`.
- **Fix**: User confirms the manual checks from the screenshots and ticks the boxes.
- **Decision**: PENDING

### F2 — Inertia progress colour `var(--brand-text)` unverified

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/app.ts:25
- **Detail**: Plan says to fall back to `#0b6a7b` if the screenshot shows var() does not work; item 3.12 is unchecked and no screenshot shows the bar (it is transient). The var() reference resolves on :root, so it should work.
- **Fix**: Confirm manually during 3.12; fall back to the hex and note it in ADR-0035 otherwise.
- **Decision**: PENDING

### F3 — `--chart-1..5` still old teal hsl(189 ...)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/css/app.css:147,200
- **Detail**: Intentional per "What We're NOT Doing"; the period-report plan carries the note (change.md:13, plan-brief.md:78) and ADR-0036 renumbering (5 occurrences). Listed so the report change does not forget it.
- **Fix**: None now; resolve in period-report-by-category.
- **Decision**: PENDING
