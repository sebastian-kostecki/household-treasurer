<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: ui-fixes-2 — plan implementacji

- **Plan**: context/changes/ui-fixes-2/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-07
- **Verdict**: APPROVED
- **Findings**: 0 critical 0 warnings 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Verified by the reviewer: `npm test` (433/433), `npm run check:ui`, `npm run types:check`, `AppNameIndependentConfigTest` + `MonthPlanHardcodedUiTest` (host SQLite, 8/8), ADR-0058..0061 exist, apple-touch 180x180, favicon.ico 32x32, no old-exception grep hits, no `PlanChangedHint`/`History` in resources/js.
Not re-run: `composer ci:check`, `vendor/bin/sloppy ci`, PostgreSQL sink tests, Playwright E2E, manual screenshots (Progress marks them [x]; no screenshots are committed, so they are not independently verifiable).

## Findings

### F1 — Dead translation key dashboard.plan_changed

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: lang/pl/ui.php:198
- **Detail**: `dashboard.plan_changed` ("Plan zmieniony") is unused in resources/ and app/. It was already unused before this change.
- **Fix**: Delete the key (check UiTranslationKeysTest still passes).
- **Decision**: FIXED via Fix (key removed)

### F2 — ADR-0060 does not record the chosen mobile layout

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/adr/0060-plan-change-direction-arrow.md
- **Detail**: Plan (Phase 4 §4) says the chosen variant goes into ADR-0060. The ADR still lists the 2x2 grid only as a fallback, while the implementation kept the four-column layout.
- **Fix**: Add one sentence to ADR-0060: four-column layout kept below `xl`, 2x2 fallback not needed.
- **Decision**: FIXED via Fix (ADR-0060 sentence updated)

### F3 — Unplanned execution-meter track change

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/css/app.css (.execution-meter), commit 3423054
- **Detail**: Track went from `w-14 flex-none` to `min-w-14 flex-1`. Not in Phase 5 §1, but documented in ADR-0061 and design-system.md and consistent with the goal that the meter fills free width.
- **Fix**: None needed; optionally note it as an addendum in plan.md.
- **Decision**: SKIPPED

### F4 — Config pin test depends on live Dotenv

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/AppNameIndependentConfigTest.php
- **Detail**: The test overrides $_ENV/$_SERVER/putenv and requires config files; it would false-green if env() were served from a cached repository.
- **Fix**: Accept as is (currently valid, env restored in afterEach).
- **Decision**: SKIPPED
