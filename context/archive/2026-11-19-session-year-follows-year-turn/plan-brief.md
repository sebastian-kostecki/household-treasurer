# Session Year Follows the Year Turn — Plan Brief

> Full plan: `context/changes/session-year-follows-year-turn/plan.md`

## What & Why

Roadmap S-40 (MS-02, rehearsal gap 3): a User who chose 2026 in the year menu in December and keeps the session alive over Polish midnight still opens Budget, Month Plan and Reports in 2026 in January, while the Dashboard already shows 2027. After the turn the app should open the new year without a manual switch, and still respect a year the User is deliberately browsing.

## Starting Point

`SelectedBudgetYear::resolve` returns the session `budget_year` unconditionally whenever a Budget exists for it, and only otherwise applies the default order of ADR-0075 with the App Day. The session stores the year but not when it was chosen. Sessions expire after 120 idle minutes, so the gap affects only a session kept alive across midnight. The test `SelectedBudgetYearTest::…_known_gap` pins today's behaviour green.

## Desired End State

From 00:00 Polish time on 1 January (31 December 23:00:00 UTC), a 2026 chosen during 2026 gives way to the default year (2027 when it has a Budget), silently, in every view with a year menu (`shell.budgetYear` included). A 2023 chosen for browsing, a 2027 chosen in December, or a 2026 chosen again in January stay. ADR-0111 and the glossary state the rule, and the former `_known_gap` test passes expecting 2027.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Which session year gives way | Only the year that was the current App Day year when chosen (rule A): S is ignored iff S = C and today's year > C | Matches the S-40 outcome and avoids the roadmap risk of throwing the User out of a year browsed at midnight |
| Visibility of the switch | Silent, no toast or notice | Keeps `resolve` a side-effect-free reading (called several times per request), with no new UI copy |
| How "when chosen" is known | New session key `budget_year_chosen_in` (App Day year at the choice), written with `budget_year` by one helper | A rule without a marker would forbid going back to 2026 in January |
| Sessions from before the deploy | No marker → today's precedence (session year stands) | They expire within 120 minutes; existing tests with `withSession(['budget_year' => …])` stay valid |
| Stale session year | Ignored in `resolve`, not forgotten | A reading must not write; the predicate gives the same answer on every call |
| Docs order | Glossary + ADR-0111 first, ADR-0075 superseded in part | AGENTS.md hard rule for a glossary rule change |
| Test level | Feature tests with `travelTo` at 22:59:59 / 23:00:00 UTC + one HTTP test in `YearTurnTest`; no E2E | The rule is server-side; the browser follows through `shell.budgetYear` |

## Scope

**In scope:**
- ADR-0111, glossary entry Budget, a pointer in ADR-0075
- `SelectedBudgetYear::choose` / `forget` and rule A in `resolve`
- `BudgetController` `store`, `selectYear` and `destroy` through the helper
- Tests in `SelectedBudgetYearTest`, `YearTurnTest`, `BudgetTest`
- README „Przełom roku” and `test-plan.md` line 180

**Out of scope:**
- Any toast or Dashboard notice about the switch
- Resetting browsed years (rule B)
- Changes to the ADR-0075 default order, the Dashboard, the year menu UI or Inertia props
- Session lifetime, "remember me", E2E tests

## Architecture / Approach

The year menu and Budget creation write `budget_year` plus `budget_year_chosen_in` through `SelectedBudgetYear::choose`. `resolve` treats the session year as a choice unless it equals its choice year and the App Day year is later. In that case it falls through to the unchanged `defaultYear`. All views (Budget, Month Plan, Reports, palette, `ShellState`) already read `resolve`, so no caller changes.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Glossary and ADR-0111 | The rule recorded before code; ADR-0075 points to it | Wording drifts from rule A (e.g. reads as rule B) |
| 2. The rule in code, test-first | Red `_known_gap` rewrite → green; helper, controller writes, README, test plan | Boundary tests at UTC midnight instead of 23:00 UTC prove nothing; a write slipping past the helper |

**Prerequisites:** S-39 (`AppDay`, ADR-0103) merged; worktree test database `testing_session_year_follows_year_turn` (`context/foundation/agents/parallel-changes.md`).
**Estimated effort:** ~1 session across 2 phases.

## Open Risks & Assumptions

- The task was taken from the roadmap entry S-40 because the change was opened without an intent.
- The fix matters only before the first turn on production: deploy before 31 December 2026 (roadmap open question 2).
- A User reconciling December at 00:05 in 2026 lands in 2027 and switches back once; accepted with the silent switch.
- The rehearsal's T3 symptom came from a 2026 chosen after the T2 re-login (App Day 2027), which rule A keeps; a repeated rehearsal must choose the year before 23:00 UTC to see the switch (plan-review F1).

## Success Criteria (Summary)

- After Polish midnight, a session that held the then-current 2026 opens 2027 everywhere a year menu exists, without a manual switch
- A year chosen for browsing, or chosen again after the turn, stays
- No `_known_gap` test left; gate and Sloppy green
