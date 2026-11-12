<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Rhythm and Irregular Decisions Implementation Plan

- **Plan**: context/changes/rhythm-and-irregular-decisions/plan.md
- **Mode**: Deep
- **Date**: 2026-11-10
- **Verdict**: REVISE → SOUND after triage
- **Findings**: 0 critical, 4 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

6/6 paths ✓, 0066 free ✓, Progress↔Phases ✓ (1.1–1.6, 2.1–2.10), symbols 7/10 ✓ (3 stale line numbers), brief↔plan ✓

## Findings

### F1 — Three more ADRs describe the flags and get no back-link

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 1 §2, checks 1.2, 1.3, 2.5
- **Detail**: Three ADRs outside the plan's list describe the flags. ADR-0043:11 gives the Year Elapsed mark to "income and Paced expense Categories, not One-off". ADR-0050:12,16 defines `one_off_over_limit`. ADR-0030:3 says "(Paced or One-off)". ADR-0066 changes all three, but the plan only added back-links to 0024/0033/0062/0063.
- **Fix**: Add ADR-0030, 0043 and 0050 to the amended clauses and back-links, extend the loops in 1.2/1.3, and say "seven" in 2.5 (plan and brief).
- **Decision**: FIXED

### F2 — After a mid-year plan change the rates do not sum to Current Plan

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Blind Spots
- **Location**: Key Decision 3, rhythm × reading table
- **Detail**: The KD3 example (Prąd) collects 6 × 150 + 5 × 200 + 200 = 2 100 of 2 400. The remainder in December only absorbs rounding. The missing 300 shows up as a surplus in a bill month. The plan did not say this.
- **Fix A ⭐ Recommended**: Keep ÷ 12 and state the consequence in ADR-0066. Add "rate from the remaining amount ÷ remaining months" to the rejected options.
  - Strength: Matches PRD FR-009 and structural decision 9 word for word.
  - Tradeoff: The User sees an overrun unless they raise the rates in change mode.
  - Confidence: MED — follows from the KD3 numbers.
  - Blind spot: None significant.
- **Fix B**: Rate = (Current Plan − earlier rates) ÷ remaining months.
  - Strength: The rates always sum to the plan.
  - Tradeoff: Departs from the PRD, needs a second chain, and a lowered plan needs its own rule.
  - Confidence: MED.
  - Blind spot: A plan lowered below the rates already collected.
- **Decision**: FIXED (Fix A)

### F3 — Four edges left to the code

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: KD 4, KD 5, table (deviation in Month Summary)
- **Detail**: The plan left four cases undecided:
  - (a) The rhythm of a month before the first history entry.
  - (b) Correcting Opening Collected after the marking month.
  - (c) The Month Summary deviation of totals for an Irregular item.
  - (d) A Fixed Charge proposal when the previous month nets ≤ 0.
- **Fix**: Settle each one in the plan:
  - (a) The first entry also covers earlier months.
  - (b) Opening Collected can be edited on the entry without moving the marking month.
  - (c) Totals keep plain sums; only the row shows the surplus.
  - (d) The proposal uses the net amount only when it is > 0, otherwise the previous month's plan.
- **Decision**: FIXED

### F4 — Checks 1.3 and 2.3 let through what they should catch

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 (1.3), Phase 2 (2.3)
- **Detail**: Both greps are too narrow:
  - 1.3: `grep '^-[^-]'` misses deleted list items.
  - The back-link line without a blank line before it merges into the list that closes the file.
  - 2.3: the pattern 'until rhythm (F-05)' misses "until rhythm, F-05" and "until rhythm (F-05;".
- **Fix**: Use `git diff --numstat … | awk '$2!=0'` in 1.3, require a blank line before "Amended by", and grep 'until rhythm' in 2.3.
- **Decision**: FIXED

### F5 — A Current Plan change moves To Spend of approved Irregular months

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Key Decision 3
- **Detail**: The rate of an unapproved month comes from the live Current Plan. A later plan change therefore shifts Collected at the start of the next approved month, and with it that month's surplus and To Spend.
- **Fix**: State it in KD3 and in the Collected contract, next to FR-011.
- **Decision**: FIXED

### F6 — The Collected formula refines the PRD, while check 1.4 says "does not contradict PRD v3"

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: ADR-0066 contract (Collected), check 1.4
- **Detail**: The PRD gives one sum, "nie mniej niż 0". The plan applies the floor in every month. In the KD3 example that gives 150 instead of 100 on 1 July.
- **Fix**: Call it a deliberate refinement of the PRD, both in the contract and in 1.4.
- **Decision**: FIXED

### F7 — Stale line numbers and missing flag readers

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State Analysis, References
- **Detail**: Four references were stale:
  - MonthPaceCalculator.php :102-119 → :26-29
  - MonthItemDeviation.php :165-194 → :27-53
  - ToSpendCalculator.php :69-81 → :76-80
  - ui.php :426 → :422

  Eight flag readers were missing: MonthPace, MonthToSpend, MonthYearComparison, EntryToSpendInputs, HandEntryMonthRemainingInputs, ImportMonthRemainingContext, DashboardController and BudgetController.
- **Fix**: Correct the numbers and list the remaining readers for S-24.
- **Decision**: FIXED
