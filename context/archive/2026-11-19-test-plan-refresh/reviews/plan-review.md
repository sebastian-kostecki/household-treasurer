<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Odświeżenie planu testów po M-4 i próbie przełomu roku

- **Plan**: context/changes/test-plan-refresh/plan.md
- **Mode**: Deep
- **Date**: 2026-11-19
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 1 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
15/17 paths ✓ (audit missing on the branch, present on development; schema only in the main checkout); symbols 1/2 ✓ (64ea4cc exists but already on development; branch worktree-app-day-decision deleted); hot-spot 370 ✓; Progress↔Phase ✓; brief↔plan ✓

## Findings

### F1 — Plan built on a base 9 commits behind development

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Current State Analysis, What We're NOT Doing, Phase 3 §6.2, References
- **Detail**: `git log HEAD..development` shows 9 commits: the F-08 merge (cbaafaf, ADR-0103 on development), the app-day-decision archive (52f4d96), the observability audit (d162bd0) and backup-signals (3c885df). The plan treated ADR-0103 as unmerged, cited a deleted branch, and cited an audit file missing from the worktree. The `git merge-base --is-ancestor 64ea4cc HEAD` check would have written a false "czeka na merge". development did not touch test-plan.md, so the §7 baseline 2141188 holds.
- **Fix**: Merge development into the worktree before Phase 1 (prerequisite, check 1.1); cite ADR-0103 directly; drop the merge-base logic; point references at context/archive/2026-11-18-app-day-decision/.
  - Strength: One move fixes 4 stale facts; §7 baseline survives.
  - Tradeoff: The merge brings app code (backup-signals) into a docs change.
  - Confidence: HIGH — verified with git log, git branch --contains, git ls-tree.
  - Blind spot: Whether local development is pushed.
- **Decision**: FIXED (Fix)

### F2 — Evidence behind #5 High is partly out of date (BKP-3 fixed)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Decision 5, Phase 1 (#5 Source, guidance), Phase 2 (stage 4 goal, ordering paragraph, §5 backup gate)
- **Detail**: 3c885df makes BackupUserData throw UserSnapshotException when the write fails; user:backup does not catch it, so the exit code is non-zero (test in BackupSignalsTest). The plan used BKP-3 to raise #5, to extend its guidance and to order stage 4 before stage 5. The open gap is the completeness guard (8 bumps v6→v13).
- **Fix A ⭐ Recommended**: Keep #5 High/High and rest it on the eight bumps without a completeness guard. Mark BKP-3 as fixed, drop the exit-code addition, and point stage 4 at completeness.
  - Strength: The rubric gives High for an area that changes weekly.
  - Tradeoff: The rating stays though one of its reasons is gone.
  - Confidence: HIGH — the test and the commit exist.
  - Blind spot: The app backup (spatie) may have its own completeness gaps.
- **Fix B**: Return #5 to Medium/High.
  - Strength: The rating matches the code.
  - Tradeoff: Changes decisions 5 and 6.
  - Confidence: MED.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F3 — test-plan will cite an M-5 roadmap that is not in git

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: plan-brief Prerequisites; Phases 1–3
- **Detail**: development has the M-4 roadmap; M-5 (S-39…S-44, MS-01…MS-05, Parked) is uncommitted in the main checkout. The new test-plan cites it in §2, §3, §5 and §6, so merging before M-5 is committed leaves dangling references.
- **Fix**: Prerequisite: M-5 committed to development via /10x-roadmap and merged into the worktree before Phase 1; check 1.1 greps `^### S-44`.
  - Strength: Every S-xx/MS-xx citation has a source in git.
  - Tradeoff: Phase 1 waits on the roadmap commit.
  - Confidence: HIGH.
  - Blind spot: Whether M-5 in the main checkout is final.
- **Decision**: FIXED (Fix)

### F4 — Year-turn gate in §5 without a §3 stage (schema rule)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Phase 2 §5, decision 2
- **Detail**: The schema forbids §5 gates with no rollout phase pointing at them. "required with roadmapa S-39" has no stage, and the orchestrator updates nothing outside §3.
- **Fix A ⭐ Recommended**: Keep decision 2 and make the exception explicit: the gate is required once the S-39 change (`one-app-day`) is archived. Name the exception in the ordering paragraph and add the rule to the plan's Schemat list.
  - Strength: Respects decision 2; S-39 owns the tests.
  - Tradeoff: The next --refresh checks the gate state by hand.
  - Confidence: MED.
  - Blind spot: Whether /10x-test-plan validates §5 against §3.
- **Fix B**: A 7th §3 row "Przełom roku" pointing at the S-39 change.
  - Strength: Meets the schema.
  - Tradeoff: Changes decision 2; the orchestrator may try to /10x-new it.
  - Confidence: MED.
  - Blind spot: Orchestrator behaviour with a folder it did not open.
- **Decision**: FIXED (Fix A)

### F5 — Automated checks 2.3 and 3.3 don't prove what they claim

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 (2.3), Phase 3 (3.3)
- **Detail**: 2.3 `grep -c` counts lines, so a correct one-row gate gives 1, not ≥ 5 (false red). 3.3 counts 22:59:59 over the whole file, so §2 and §5 satisfy it without §6.2 (false green).
- **Fix**: 2.3 → `grep -oE … | sort -u | wc -l` = 5; 3.3 → count in the `/^### 6\.2/,/^### 6\.3/` range.
- **Decision**: FIXED (Fix)

### F6 — Recount and Larastan left to guesswork

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §1/§2 Source, Phase 2 §4
- **Detail**: No command for per-directory file changes; the window "do dnia implementacji" drifts from 30 days; "Larastan … jeśli jest wymieniony" when §4 has no typecheck row.
- **Fix**: Fixed window 2026-10-20 → 2026-11-19T23:59:59, a per-directory command (gives the brief's 550 and 48), Larastan in the §5 lint/typecheck row.
- **Decision**: FIXED (Fix)

### F7 — Schema budgets exceeded without a note

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State Analysis → Schemat
- **Detail**: The schema wants 5–7 rows in §2 and 3–5 stages in §3; the plan has 9 and 6 without saying it is deliberate.
- **Fix**: One line in Schemat naming the deliberate departure (stable numbering, decisions 1 and 6).
- **Decision**: FIXED (Fix)
