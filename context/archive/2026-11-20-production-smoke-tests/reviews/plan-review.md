<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Production Smoke Tests (S-48)

- **Plan**: context/changes/production-smoke-tests/plan.md
- **Mode**: Deep
- **Date**: 2026-11-20
- **Verdict**: REVISE (SOUND after fixes)
- **Findings**: 0 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
14/14 paths ✓, 5/6 symbols ✓ (RehearsalClock line reference ✗), f48b8363 on origin/main ✓, Progress↔Phase ✓, brief↔plan ✓

## Findings

### F1 — S2 on production passes even if S-40 is broken

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 4 G3, Phase 5 S1–S2
- **Detail**: The session year lives only in the session (`SelectedBudgetYear.php:37-45`), and the session expires after 120 min idle (`config/session.php:33`). G3 said only "before 00:00 PL", and S1 allowed a morning check. A morning check runs in a new session, which defaults to 2027, so S2 passes whether or not the ADR-0111 switch works. "Remember me" re-authenticates but does not restore the session year.
- **Fix A ⭐ Recommended**: Time-box G3 (23:00–23:59 PL, tab stays open) and S2 (same tab, by about 01:30 PL); a later check reads "nie dowodzi (nowa sesja)" and points to C4.
  - Strength: Keeps the only real-calendar proof of S-40, and a failure becomes detectable.
  - Tradeoff: The owner must be awake around midnight for S2 to count.
  - Confidence: HIGH — the session logic and lifetime come from the code.
  - Blind spot: The production `SESSION_LIFETIME` in `.env.prod` is not checked.
- **Fix B**: Drop S2 from production; S-40 rests on C3–C4.
  - Strength: No midnight constraint.
  - Tradeoff: No S-40 evidence from the production environment.
  - Confidence: HIGH
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F2 — S-41 2.6 "missing-year card" cannot be reached at C4 or C6

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Phase 1 C-table (C1, C6); Phase 2 Manual 2.4
- **Detail**: The production copy has 2027 as a draft (`rehearsal.md:10`). The missing variant shows only when `month == 12` (`BudgetNotInExecutionNotices.php:37-44`). In January, without Budget 2027, there is no card at all. The deadline card appears only from day 3 (`DashboardNotices.php:241`), so C1 at 12-01 would not show it either.
- **Fix**: Move C1 to 2026-12-03 09:00. Add C7: set the clock back to 2026-12-03, run `user:reset-data` + `user:restore`, delete the 2027 draft (`BudgetPolicy::delete` allows drafts), and check that „Brak budżetu” opens „Dodaj rok 2027”. Set 2.4 to cover 2.6 at C1 + C7.
  - Strength: Closes all of archived row 2.6 on real data.
  - Tradeoff: One more step and one more relogin.
  - Confidence: HIGH — conditions read from the code.
  - Blind spot: The cascade of deleting the draft is not checked; the copy is dropped anyway.
- **Decision**: FIXED

### F3 — Several expected results contradict the code

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 D3, Phase 4 G1/G2, Phase 5 S1, Phase 1 S-41/S-43 bullets and criterion 1.3
- **Detail**:
  - The login screen shows no day, only the year, a percentage and month bars (`AuthCardLayout.vue:41-75`).
  - The deadline card appears only from 3.12.
  - The Dashboard date includes the weekday (`lib/format.ts:147-156`).
  - „Do 1 stycznia” and „Dodaj rok” are templates in `lang/pl/ui.php`.
  - G2's Dashboard reads „budżet 2026 w realizacji · budżet 2027 od 1 stycznia”.
- **Fix**: Correct D3, G1 (3–5.12), G2, S1, the S-39/S-41 bullets and 1.3 (grep `lang/pl/` with templates).
- **Decision**: FIXED

### F4 — Stale references and commands that are not runnable as written

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State Analysis; Phase 2 Automated 2.1; Phase 3 D2
- **Detail**:
  - `RehearsalClock.php` has 78 lines. The guard is at `:26` in `apply()`, which is called at boot rather than as a per-request hook.
  - 2.1 ran `php artisan` on the host CLI.
  - D2 had no command.
- **Fix**: Change the reference to `:24-35`, run 2.1 in the `sail-8.5/app` image, and give D2 `git merge-base --is-ancestor f48b8363 HEAD`.
- **Decision**: FIXED
