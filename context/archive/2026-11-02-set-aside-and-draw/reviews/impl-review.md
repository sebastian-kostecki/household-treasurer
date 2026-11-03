<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: S-02: Odkładam / dobieram

- **Plan**: context/changes/set-aside-and-draw/plan.md
- **Scope**: Full plan (3 of 3 phases)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-02
- **Verdict**: APPROVED
- **Findings**: 0 critical, 0 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Verification run (Step 3)

| Check | Result |
|-------|--------|
| PostgreSQL (Sail image): MonthPlanProposalTest, ApproveMonthPlanTest, MonthPlanPageTest, DashboardTest, UserSnapshotCommandTest, ToSpendCalculatorTest | 123 passed (1174 assertions) |
| `composer ci:check` on Sail image (npm check, types:check, npm test, full Pest suite on PostgreSQL) | 706 passed, 2 skipped (5475 assertions) |
| `npm test` (vp test) | 24 passed (3 files) |
| `npm run check` | pass (197 files formatted, no lint warnings) |
| `npm run types:check` (vue-tsc) | pass |
| PHPStan (`composer types:check`) | No errors |
| Pint on `app database tests lang` | passed, working tree unchanged |

Not re-run: the manual UI checks 2.9–2.14, 3.4 (no preview built in this review).

## Plan drift (subagent 1, static read)

No DRIFT and no MISSING. All Phase 1–3 contracts match, including the 6 proposal-rule examples (one test each), identical 170 000 / 250 000 cases in PHP and TS, the 6 `drawExceedsSpendableLiquidity` cases, the Phase 2 feature-test bullets, and all "What We're NOT Doing" limits (no version bump, no `AccountController` change, no Dashboard notice, no approval block, no past-month warning). ADR-0033 carries "(settled by S-02)". EXTRA: only the planning artifacts under `context/changes/set-aside-and-draw/` and the uncommitted `context/foundation/roadmap.md` edit (S-02 → in-progress).

## Findings

### F1 — Restore does not type-check the new snapshot keys

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/RestoreUserData.php:257-258
- **Detail**: `?? 0` covers a missing key and an explicit `null`, but a non-integer value (for example `"abc"` or `1.5`) in `approved_set_aside_minor_units` / `current_set_aside_minor_units` reaches the `bigint` column and surfaces as a `QueryException` instead of a `UserSnapshotException`. The sibling `approved_amount_minor_units` / `current_amount_minor_units` a few lines below behave the same, so this follows the existing pattern. Snapshots are admin-produced by `user:backup`, so the exposure is low.
- **Fix**: Leave as is, or guard all four month-plan amounts with `is_int()` and throw `UserSnapshotException` in one separate change.
- **Decision**: SKIPPED

### F2 — No direct "lower by 50 000" assertion on the page

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/MonthPlanPageTest.php:350-379
- **Detail**: The plan asks for `to_spend_minor_units` "lower by 50 000 than the value without Set-aside". The test asserts hard-coded values (323 000 for the approved month, 360 000 for the July proposal) and has no same-month baseline without Set-aside. The formula itself is pinned by the PHP/TS calculator tests (170 000 / 250 000), so a regression would still be caught there; the gap is only the page-level wiring `$reading->setAsideMinorUnits` → `to_spend_minor_units`.
- **Fix**: Add one assertion that approves the same month twice (or two users) with Set-aside 0 and 50 000 and compares the two `to_spend_minor_units` values.
- **Decision**: FIXED — added `test_set_aside_lowers_to_spend_by_its_amount_in_the_same_month` to tests/Feature/MonthPlanPageTest.php (approves the same month with Set-aside 0 and 50 000, asserts the `to_spend_minor_units` difference is 50 000); MonthPlanPageTest 24 passed on PostgreSQL, Pint passed. Not committed.

### F3 — Manual UI checks are ticked without evidence in the diff

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/set-aside-and-draw/plan.md (Progress 2.9–2.14, 3.4)
- **Detail**: The checkboxes for the manual browser checks (live "do wydania" preview, warning, keyboard toggle, narrow screen, backup/restore round trip) are `[x]` with a commit sha, but the diff cannot show a manual check. The code supports each of them (verified statically by the drift agent: role=group + aria-pressed, `allAmountsValid`, warning gating, `null` liquidity for past months) and this review did not build the preview, so it neither confirms nor contradicts them.
- **Fix**: No code change. If the checks were done on the preview, note it in the PR description.
- **Decision**: SKIPPED

## Dismissed during review (no finding)

- Approved read-only card keeps the Draw warning when balances later fall below it: this is the plan's explicit behavior (Desired End State: warning "w każdym stanie"; Manual 2.12 "ostrzeżenie zostaje").
- Proposal copies only a positive Set-aside: this is the rule the plan settles (Phase 1 table, ADR-0033, CONTEXT.md).
- Migration (`bigInteger` NOT NULL default 0) is metadata-only on PostgreSQL 11+ and `down()` is symmetric.
- Authz unchanged (`manageMonthPlan` policy); `SpendableLiquidity::forUser` is scoped through `$user->accounts()`; warning text is escaped; ±1e12 is within JS Number and PHP int64; `-0` is avoided.
- `context/foundation/roadmap.md` has an uncommitted edit (S-02 `proposed` → `in-progress`) outside the implementation commits. Commit it with the closing step, and set it to `done` when the change is archived.
