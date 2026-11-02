<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Month Plan Domain Decisions

- **Plan**: context/changes/month-plan-domain-decisions/plan.md
- **Mode**: Deep
- **Date**: 2026-11-02
- **Verdict**: REVISE → SOUND after triage (6/6 fixed)
- **Findings**: 0 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
6/8 paths ✓ (`.agents/skills/domain-modeling/ADR-FORMAT.md`, `CONTEXT-FORMAT.md` ignored by git since ce0d94c), 4/4 symbols ✓ (DashboardNotices kinds, `CreateBudget.php:44` is_paced, `MonthRemainingCalculator`, `SNAPSHOT_VERSION = 4`), brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — „Średnia z rocznego” i źródło propozycji niezdefiniowane

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1 — Contract „Proposal”; What We're NOT Doing
- **Detail**: Plan defers only rounding to S-01, but the average has two readings: Current Plan / 12 vs leftover / months left (Żywność 12 000 annual, 11 000 spent by October → 1 000 vs 500 in November). Code has both (`MonthRemainingCalculator.php:81-90`, `:98-99`); `CONTEXT.md:56` rejects "an even split of Current Plan" for Month Remaining. The first proposal after rollout is built entirely from this rule. Source for a future month whose previous month is still a Proposal is also undefined.
- **Fix A ⭐ Recommended**: floor(Current Plan / 12) per Category, plus "previous month = previous calendar month's Month Plan in any state"
  - Strength: Literal „średnia z rocznego”; same arithmetic as the existing draft reading.
  - Tradeoff: Mid-year rollout ignores spending so far; user corrects the first proposal by hand.
  - Confidence: HIGH — PRD says „średnia” and only the first month uses it.
  - Blind spot: Remainder handling needs one sentence.
- **Fix B**: Leftover Current Plan / months left, plus the same source rule
  - Strength: More realistic in a mid-year first month.
  - Tradeoff: Further from PRD wording; ties the proposal to Month Remaining arithmetic.
  - Confidence: MED — better numbers, weaker PRD fit.
  - Blind spot: Negative leftover needs a floor at 0.
- **Decision**: FIXED (Fix A)

### F2 — Docs contradicting ADR-0033 are not named

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: What We're NOT Doing; Phase 1 — ADR-0033 Contract
- **Detail**: After ADR-0033, `.docs/prd.md:191` (FR-TRX-08), `:296` (FR-REP-13), `.docs/adr/0031-…:5`, `.ai/rules/js.md:24` and the entry/import previews contradict it; the plan names only dashboard-hierarchy.md and .docs/prd.md generally.
- **Fix**: One sentence in ADR-0033 naming what it overrides for months with a Month Plan until S-06/S-07/S-08 update them; same list with owning slices in „What We're NOT Doing”. No edits to those files.
- **Decision**: FIXED (fix in plan)

### F3 — Polish UI copy collides with existing labels

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 — Current Month Plan, Month Item Kind
- **Detail**: „Plan bieżący” is Current Plan copy (`lang/pl/ui.php:121,163`); Running is „bieżąca”, and Current Month Plan has no in-month Polish copy. „Rachunek bankowy” is Account copy (`ui.php:279,289,307`) vs Bill „rachunek”.
- **Fix**: Current Month Plan copy „plan miesiąca” („plan końcowy” in Month Summary), „plan bieżący” in _Avoid_; Month Item Kind notes „rachunek” is the item kind, not an Account.
- **Decision**: FIXED (fix in plan)

### F4 — Format refs point at ignored files; two checks are weak

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State Analysis; References; criteria 1.3, 2.2
- **Detail**: `.agents/` is gitignored; format files are absent in fresh checkouts. 2.2 greps 'warning', already matched by `CONTEXT.md:117`. 1.3 has no pass/fail command.
- **Fix**: Inline the format rules; 2.2 greps "`warning` Dashboard notice" / "`exceeded` Dashboard notice"; 1.3 becomes `git diff development -- .docs/adr/0024-*.md | grep -c '^-[^-]'` → 0.
- **Decision**: FIXED (fix in plan)

### F5 — Two sign edges in To Spend / Set-aside

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 — Month Item Kind (Bill), Set-aside
- **Detail**: A refund-only month on a Bill Category (overpayment returned) makes actual −50 and raises To Spend by plan + 50 though the bill is unpaid. „Draw większy niż Spendable Liquidity” compares a negative amount.
- **Fix**: "after the first booked expense (positive amount) dated in that month; refunds net into actual" and "|Draw| > Spendable Liquidity".
- **Decision**: FIXED (fix in plan)

### F6 — Copy precedent should cite code, not ADR-0014

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 2 — Month Item Kind
- **Detail**: `CreateBudget.php:44` copies is_paced and Backup/RestoreUserData carry it, but ADR-0014 and the copy spec omit it; only ADR-0024:3 states it.
- **Fix**: Cite ADR-0024:3 and `CreateBudget.php:44` as the precedent in ADR-0033 and References.
- **Decision**: FIXED (fix in plan)
