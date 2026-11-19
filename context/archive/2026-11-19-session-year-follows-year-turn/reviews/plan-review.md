<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Session Year Follows the Year Turn

- **Plan**: context/changes/session-year-follows-year-turn/plan.md
- **Mode**: Deep
- **Date**: 2026-11-19
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
7/7 paths ✓, 5/5 symbols ✓ (`SelectedBudgetYear::resolve`, `defaultYear`, `BudgetController` writers at :121/:137-139/:153, `resolveFor`, `givenTwoBudgets`), brief↔plan ✓, Progress↔Phases ✓ (3 + 11 rows).

Verified in code: `budget_year` has no writers outside `BudgetController`; MCP does not read the session (`.ai/rules/mcp.md:15`); Reports and Month Plan take no year from the URL; session data persists across requests in feature tests (`BudgetTest.php:384-401`, `SESSION_DRIVER=array`); `(int) AppDay::today()->year` passes `AppDayClockGuardTest`; session writes in `app/Support` have precedent (`CsvImportSession.php:17,62`).

## Findings

### F1 — The rehearsal's gap 3 is a choice made after Polish midnight

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Current State Analysis (session lifetime bullet), Phase 1 ADR-0111 Examples, Phase 2 README
- **Detail**: The plan said the gap "is what the rehearsal hit at T3". At T2 (clock 2026-12-31 23:30 UTC = 1 January 00:30 PL) the clock jump over 2 h logged the User out (`rehearsal.md:28`), so the 2026 in the session at T3 was written by a menu choice after the re-login, App Day 2027. Rule A keeps that choice (S=2026 ≠ C=2027). Repeating the rehearsal steps as they are would show "2026 stays" and look like the fix failed.
- **Fix**: Correct the Current State sentence, add the T2 example to ADR-0111 (2026 chosen at 31.12 23:30 UTC is a choice in 2027 and stays), and tell README „Próba przed przełomem” to choose the year before 23:00 UTC.
  - Strength: Rule A unchanged; the planning decision stands.
  - Tradeoff: None in code; three sentences of docs.
  - Confidence: MED — `rehearsal.md` does not say when 2026 was chosen, but the logout at T2 rules out a December choice.
  - Blind spot: Whether the owner meant exactly the T2/T3 sequence by "gap 3" (rule A would then not cover it).
- **Decision**: FIXED (Fix in plan; user: "napraw samodzielnie")

### F2 — The manual pass is not feasible on preview data

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 Manual Verification (2.9, 2.10), Manual Testing Steps
- **Detail**: `DemoHouseholdSeeder` creates only a Budget for `now()->year` (2026; `DemoHouseholdSeeder.php:30,77`); `demo:seed-previous-year` adds 2025 only. Without 2027 the default after midnight stays 2026 and 2.9 proves nothing; 2.10 has no 2023. Creating a Budget in the UI writes the session year (`store`), so the tested year must be chosen afterwards. In a worktree the clock must be set through the worktree container (`storage/app/private/rehearsal-clock`), not `docker compose exec`. The plan's reason for not moving the clock backwards was wrong: the command refuses a moment earlier than the real now minus 120 min (`RehearsalClockCommand.php:53-55`).
- **Fix**: Add a preparation step (worktree container clock, re-login, create 2027 and 2023, then choose the tested year) and correct the refusal note.
- **Decision**: FIXED (Fix in plan)

### F3 — Test contracts are imprecise in two places

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §1 (YearTurnTest, BudgetTest)
- **Detail**: YearTurnTest said "2027 started" while `givenTwoBudgets()` creates 2027 as a draft (`YearTurnTest.php:299-302`), and used 22:30 as the "before" moment instead of the canonical 22:59:59 (`.ai/rules/app-day.md`). No BudgetTest covers `destroy` of the session year, so "add a test if none covers it" meant a new test.
- **Fix**: Use `givenTwoBudgets()` (2027 draft) and 22:59:59; name the create test to extend (`:34`) and a new destroy test asserting both keys are missing.
- **Decision**: FIXED (Fix in plan)
