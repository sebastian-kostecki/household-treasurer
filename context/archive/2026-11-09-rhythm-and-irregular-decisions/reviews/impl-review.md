<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Rhythm and Irregular Decisions Implementation Plan

- **Plan**: context/changes/rhythm-and-irregular-decisions/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-10
- **Verdict**: REJECTED (one critical one-word drift; see F1)
- **Findings**: 1 critical, 5 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | FAIL |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Evidence

- Automated criteria 1.1–1.3 and 2.1–2.5: all pass (0066 unique; ADR-0066 cites all 7 ADRs; each amended ADR `+2/-0` and links ADR-0066; 4 new terms, 4 predicates verbatim in both docs, 0 old phrases, Polish copy present; diff outside the change folder = glossary, 0066 and the 7 ADRs).
- Arithmetic recomputed from the rule text: July chain 150/300/50/200/0 (surplus 50)/150 → 150 on 1 July; 2 400/12 = 200, sum 2 100; US-02 50/0 and 0/150; August refund 250; 2 100/12 = 175; Rekolekcje 19 833 / 19 837; Ubezpieczenie 4 508 / 4 512; Telefon 172,15 / 180. All correct.
- Code claims verified: `SNAPSHOT_VERSION = 6`, restore accepts 1–6; `ToSpendCalculator` branches only Bill vs max(plan, actual); `MonthPaceCalculator` pool = Running ∧ Paced; `one_off_over_limit` on Available Limit < 0; dev data 29 + 2, no bills.
- Plan drift: every Contract bullet MATCH except F1 and F8; extras (draft-budget example, net-sum sentence, `month_item_over_plan` for surplus, Opening Collected _Avoid_) are consistent with the plan.

## Findings

### F1 — Month Item Kind entry inverts the flag mapping

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/glossary.md:93
- **Detail**: "Running is Running (or Even Annual with the One-off flag off, see Paced Category)". Key Decision 1, ADR-0066:32-36 and glossary:57 map Even Annual to `is_paced = false`, i.e. the One-off flag **on**. S-24 implementing the migration from this entry would map the 29 Paced + Running lines to Even Annual (tempo pool empties, Proposals become floor/12). Manual check 2.9 ("no corrected entry contradicts ADR-0066") was ticked over this line.
- **Fix**: Replace with "(or Even Annual when the Budget Line is One-off, `is_paced` false, see One-off Category)".
- **Decision**: FIXED (Fix now)

### F2 — Rate of a past month without an approved plan is undefined

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Architecture
- **Location**: context/foundation/adr/0066-category-rhythm-and-irregular-collected.md:22; context/foundation/glossary.md:109
- **Detail**: "The rate of a month without an approved plan is the Proposal amount computed from the present Current Plan". For a past month the code has no Proposal: `MonthPlanProposal.php:131-133` returns state `none` with no amounts, so an implementer reusing it gets rate 0 (or nothing). If the rate is floor(Current Plan / 12) instead, a plan change recomputes Collected of past unapproved months, which is close to the reason given for rejecting "stawka zawsze z bieżącego planu ÷ 12" (0066:55, "przelicza minione miesiące i tworzy zebrane bez pieniędzy"). Same for the Budget phase: "not in execution → rate 0" is read when the reading is made, so activating a Budget on 20 January changes January's rate after the fact. The July example holds only if January–June were approved; it does not say so. The plan's own KD 3 carries this tension (it accepts the effect for "grudzień bez zatwierdzenia"), so this is partly a plan flaw.
- **Fix A ⭐ Recommended**: State explicitly that a month without an approved plan (past or current) has rate = the Proposal formula floor(Current Plan / 12) with the December remainder, computed from the present Current Plan, independent of `MonthPlanProposal`'s `none` state; say the Budget phase is read at read time; narrow the rejected option to "also for approved months"; add "all approved" to the July example.
  - Strength: Matches KD 3 and the existing "December unapproved, January approved" sentence; Collected keeps growing in months the user forgot to approve, so a bill is not wrongly a surplus.
  - Tradeoff: A plan change still moves Collected of past unapproved months (documented, as for a late operation, FR-011).
  - Confidence: MED — consistent with the plan text, but the user chose KD 3 under a different reading.
  - Blind spot: Not checked how S-26 would compute this in one query across months (`.ai/rules/sloppy.md`).
- **Fix B**: Rate 0 for every past month without an approved plan; only approved months and the current month's Proposal carry a rate.
  - Strength: Past readings never move with a plan change; no tension with the rejected option.
  - Tradeoff: Forgetting to approve a month loses its rate and turns the next bill into a surplus; contradicts the existing "December unapproved" sentence, so ADR and glossary need rewording.
  - Confidence: MED — simpler to implement, harsher for the user.
  - Blind spot: How often months stay unapproved in real use.
- **Decision**: FIXED (Fix A)

### F3 — "0 for an archived Budget Line" has no time bound

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: context/foundation/glossary.md:109; context/foundation/adr/0066-category-rhythm-and-irregular-collected.md:22
- **Detail**: The code and ADR-0033 drop an archived line only from Proposals of months starting after archival (`MonthPlanProposal.php:140`, `archived_at < firstDay`). Read literally, the docs make every unapproved month's rate 0 retroactively. "Restoring resumes the rate" with a single `archived_at` would also give back rates for unapproved months inside the archived interval.
- **Fix**: Reword to "0 in months without an approved plan that start after archival" and add one sentence on what restore does to the archived interval (recommended: those months stay 0, the rate resumes from the month of restore).
  - Strength: Aligns with the existing archive predicate in code and ADR-0033.
  - Tradeoff: Restore needs the archive interval, not just the current `archived_at` (S-26 concern).
  - Confidence: HIGH — the predicate is already in code.
  - Blind spot: Whether ADR-0063 archive history keeps past intervals.
- **Decision**: FIXED (differently, per triage: read-time archive state — "0 in unapproved months starting after archival"; restore gives the unapproved months of the archived period their rate back)

### F4 — Rhythm entry identity: correction vs re-marking

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: context/foundation/adr/0066-category-rhythm-and-irregular-collected.md:20, :30
- **Detail**: "A change replaces all entries from the current calendar month with one entry", "Opening Collected can be corrected on an existing entry" and "Marking again starts anew" leave open: saving Irregular again in a later month with a different Opening Collected (correction or new marking?); removing and re-marking within one month; whether "identical consecutive entries merge" (stated for the migration) also applies at runtime, which would silently join two Irregular periods and break "starts anew".
- **Fix**: Add one sentence: an Opening Collected correction edits the entry that started the current Irregular period and never creates an entry; saving the same Rhythm changes nothing; removing and re-marking in the same month replaces that month's entry; merging identical entries is migration-only.
  - Strength: Gives S-24/S-26 an unambiguous write rule.
  - Tradeoff: One more rule in an already dense ADR.
  - Confidence: MED — the proposed semantics follow KD 5 but were not asked of the user.
  - Blind spot: UI flow for the correction (S-26 design).
- **Decision**: FIXED (Fix now)

### F5 — ADR-0033's rejected "carrying the remainder forward" is not addressed

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: context/foundation/adr/0033-month-plan-beside-annual-budget.md:11, :16; context/foundation/adr/0066-category-rhythm-and-irregular-collected.md:43
- **Detail**: ADR-0033 rejected both "an irregular-expense fund" and "carrying the remainder forward (a chain of months, and double counting with Draw)". Collected is a chain of months; ADR-0066 and the appended line only say the fund stays rejected. The line also omits "One-off always starts at 0 → Even Annual proposes the average", which ADR-0066:43 lists.
- **Fix**: Add a sentence to ADR-0066 (and to its clause for ADR-0033) explaining that Collected is not the rejected carry-over: per Irregular Category only, floor 0 each month, independent of Set-aside and Draw; extend the ADR-0033 appended line with the average clause.
- **Decision**: FIXED (Fix now)

### F6 — Over-plan basis worded differently in ADR and glossary

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/adr/0066-category-rhythm-and-irregular-collected.md:11; context/foundation/glossary.md:97
- **Detail**: ADR-0066 table says "actual > Current Month Plan"; the plan said "plan miesiąca" and the glossary defines plan as "Current Month Plan, or Proposal before approval", so the ADR drops Proposal months where the notice fires today. glossary:97 "A saved plan raised to the actual amount ends the item-over-plan notice" is now false for Even Annual (year-based signal stays) and Irregular (no raise).
- **Fix**: Use "actual > plan of the month" in the ADR table and scope the glossary sentence to Fixed Charge and Running.
- **Decision**: FIXED (Fix now)

### F7 — Small wording inaccuracies

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: context/foundation/glossary.md:113-114; context/foundation/adr/0066-category-rhythm-and-irregular-collected.md:5, :24, :38
- **Detail**: Collected is defined as "the running balance" while its _Avoid_ lists "balance" (also 0066:5). 0066:38 "all three Rhythms without a bill take the greater of plan and actual" — the migration yields two such Rhythms and Fixed Charge does not use max. 0066:24 "−150" / "−300" reads as an absolute To Spend value; it is the item's contribution.
- **Fix**: "running balance" → "running total"; 0066:38 → "Running and Even Annual take the greater, Fixed Charge keeps the Bill rule"; mark −150/−300 as the item's effect on To Spend.
- **Decision**: FIXED (Fix now)

### F8 — Glossary Rhythm and Month Item Kind entries are imprecise

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/glossary.md:52, :93
- **Detail**: The Contract asked each Rhythm value to state its To Spend and Proposal rule; the Irregular line gives no Proposal rule (it is in the Irregular Rate entry). Month Item Kind "A new Budget no longer copies it" reads as the current code, while `CreateBudget.php:48-49` still copies it until S-24.
- **Fix**: Add "Proposal as Even Annual (the Irregular Rate)" to the Irregular line; change to "A new Budget stops copying it once S-24 lands".
- **Decision**: FIXED (Fix now)

### F9 — Edge cases left for S-26 without a rule

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: context/foundation/adr/0066-category-rhythm-and-irregular-collected.md:20, :22, :26
- **Detail**: (a) A bill dated in a month whose Budget is a draft lowers Collected while the rate is 0, and its surplus lowers no To Spend, so the overrun disappears; no rule for a year without a Budget. (b) An approved month with no item for the Irregular Category (it joined later): rate 0, the bill becomes a surplus — intended? (c) "A month before the first entry has the Rhythm of the first entry": a Category marked Irregular in its creation month makes earlier months Irregular with no Collected chain.
- **Fix**: Record these three cases as open questions for S-26 in `roadmap.md` (or one sentence each in ADR-0066), rather than deciding them now.
- **Decision**: FIXED (Fix now — recorded as S-26 Unknowns in roadmap.md)

### F10 — Uncommitted roadmap change; F-05 left as in-progress

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: context/foundation/roadmap.md:47, :128
- **Detail**: The worktree has an uncommitted edit setting F-05 `ready → in-progress` and `updated: 2026-11-10`. The change is implemented and both phases are ticked; F-03 and F-04 show `done` after archive.
- **Fix**: Commit the roadmap edit together with the review fixes; let `/10x-archive` flip F-05 to `done`.
- **Decision**: FIXED (Fix now — committed with the review fixes)

## Triage Summary

- Fixed: F1, F2 (Fix A), F3 (read-time archive state), F4, F5, F6, F7, F8, F9 (S-26 Unknowns), F10 (10)
- Skipped: none
- Accepted: none
- Rules: none
