# Session Year Follows the Year Turn Implementation Plan

## Overview

Roadmap S-40 (MS-02, rehearsal gap 3): a User who chose 2026 in the year menu in December and keeps the session alive over Polish midnight still lands in 2026 in January, while the Dashboard is already in 2027. This plan lets a session year give way to the default year (ADR-0075) after the App Day year turns. It applies only when that year was the current year at the moment it was chosen (rule A, decided in planning), and the switch is silent. A year chosen for browsing (e.g. 2023) stays.

## Current State Analysis

- `SelectedBudgetYear::resolve` (`app/Support/SelectedBudgetYear.php:11-36`) returns the session `budget_year` whenever the User has a Budget for it, unconditionally. Only without one does it fall back to `defaultYear` with `AppDay::today()->year` (ADR-0075, ADR-0103).
- Its callers are the Budget, Month Plan, Reports and command palette controllers, plus `ShellState::forRequest` (`app/Support/ShellState.php:30`, prop `shell.budgetYear`). One rule therefore covers every view that has a year menu. The Dashboard always reads the current calendar year (ADR-0031) and is not affected.
- `budget_year` is written only on an explicit choice: `BudgetController::selectYear` (`app/Http/Controllers/BudgetController.php:153`, the year menu) and `BudgetController::store` (`:121`, a new Budget). `destroy` forgets it (`:137-139`). The session does not record *when* the year was chosen. Without that, "2026 is left over from December" cannot be told apart from "in January I deliberately went back to 2026".
- Sessions expire after 120 minutes of inactivity in local and production (`.env.example:31`, `.env.prod.example:30`, `database` driver). "Remember me" re-authenticates into a fresh, empty session. The gap therefore bites only a session kept alive across Polish midnight with a year chosen before it. The rehearsal's T3 (`context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md:29`) shows the symptom, but its 2026 was chosen after the clock jump at T2 had logged the User out (`rehearsal.md:28`), i.e. at 31.12 23:30 UTC or later, App Day 1 January 2027. Under rule A that choice is a choice in 2027 and stands, so the T2/T3 steps repeated as they are will still show 2026; the rehearsal has to choose the year before 23:00 UTC to see the switch. The morning after already opens 2027 (`SelectedBudgetYearTest::test_january_without_a_session_year_selects_the_new_year_whatever_its_phase`).
- The gap is pinned green by `SelectedBudgetYearTest::test_session_year_of_the_old_year_stays_selected_in_january_known_gap` (`tests/Feature/SelectedBudgetYearTest.php:76-83`). `context/foundation/test-plan.md:180` says S-40 rewrites it to the required behaviour (2027 in January) and drops the `_known_gap` suffix, as a change of requirement, not assertion tuning.
- The glossary entry Budget (`context/foundation/glossary.md:32`) states the rule "Without a choice in the session the app opens …", and README „Przełom roku” (`README.md:339`) tells the User to switch the year by hand after the turn. Both change with this plan.

## Desired End State

On 1 January 2027 from 00:00 Polish time (31 December 2026 23:00:00 UTC), a session that holds 2026 chosen during 2026 opens Budget, Month Plan, Reports and the command palette in the default year (2027 when it has a Budget, whatever its phase), and `shell.budgetYear` is 2027. At 22:59:59 UTC it is still 2026. A session that holds 2023 (or 2027) chosen during 2026 keeps that year. Choosing 2026 again in January keeps 2026. No toast or notice appears. ADR-0111 and the glossary describe the rule, ADR-0075 points to ADR-0111, and the former `_known_gap` test passes as an ordinary test expecting 2027.

Verify: `SelectedBudgetYearTest` and `YearTurnTest` green on PostgreSQL, gate green, and a manual pass with `app:rehearsal-clock` across midnight.

### Key Discoveries:

- `SelectedBudgetYear::resolve` is a reading called at least twice per request (`ShellState` and the page controller). It must stay free of side effects so both calls agree, so the stale session year is ignored, not forgotten.
- Session writes live in `BudgetController` only (`:121`, `:137-139`, `:153`). One helper next to the reader keeps the two keys together.
- Test pattern: `SelectedBudgetYearTest::resolveFor` builds a session by hand (`:22-43`). `YearTurnTest` drives HTTP with `travelTo` and `assertInertia` (`tests/Feature/YearTurnTest.php:75-115`).
- Clock rule (`.ai/rules/app-day.md`): the year comes only from `AppDay::today()`, and tests freeze at 22:59:59 / 23:00:00 UTC on 31 December, never at 00:00 UTC. `tests/Unit/AppDayClockGuardTest.php` guards it.
- ADR numbers: 0110 is the newest (`mcp-read-only-server`); 0111 is free on every branch.

## What We're NOT Doing

- No toast, notice or other visible signal of the switch (decided in planning: silent).
- No reset of a session year that was not the current year when chosen (rule A, not B): a browsed past year or a next-year draft stays.
- No change to the default-year order of ADR-0075, to the Dashboard (ADR-0031) or to the year menu UI; no Inertia prop changes.
- No forced migration of live sessions: a session year without the new marker keeps today's behaviour (it expires within 120 minutes of the deploy anyway).
- No change to session lifetime or "remember me".
- No E2E test: the rule is server-side and the clock is frozen in feature tests; the browser follows through `shell.budgetYear`.

## Implementation Approach

Store, next to `budget_year`, the App Day year at the moment of the choice (session key `budget_year_chosen_in`). In `resolve`, the session year counts as a choice unless it was the current year when chosen and the App Day year is now later. In that case `resolve` falls through to the unchanged `defaultYear`. Rule as one predicate: with session year S chosen in App Day year C, S is ignored iff `S === C && AppDay::today()->year > C`. Documents first (glossary rule change → ADR, AGENTS.md hard rule), then code test-first.

## Phase 1: Glossary and ADR-0111

### Overview

Record the decision before code: the new rule for the session year in the glossary and in a new ADR, with ADR-0075 marked superseded in part.

### Changes Required:

#### 1. New ADR

**File**: `context/foundation/adr/0111-session-year-follows-the-year-turn.md`

**Intent**: Record rule A, the silent switch, the session marker and the handling of sessions without it, with examples on the boundaries, so S-40 has its decision before code.

**Contract**: Same shape as ADR-0103/0109 (title, context paragraph naming rehearsal gap 3 and the 120-minute session, Rule, Examples, Relation to earlier ADRs, Consequences, „We rejected”). Rule: a choice of year in the session records the App Day year in which it was made; the session year S chosen in year C is not a choice once the App Day year is later than C and S equals C, and the order of ADR-0075 applies; every other choice stands. Examples: 2026 chosen 31.12 23:40 PL → 2027 at 00:00 PL (31.12 23:00:00 UTC), still 2026 at 22:59:59 UTC; 2023 chosen in December stays in January; 2027 chosen or created in December stays; 2026 chosen again on 1 January stays; 2026 chosen at 31.12 23:30 UTC (App Day 1 January, as after the re-login at rehearsal T2) counts as a choice in 2027 and stays; a session year without a recorded choice year stands. Silent: no notice. Rejected: B (every choice made before the turn expires, which throws the User out of a year browsed at midnight); a rule without a marker, e.g. "a session year earlier than the current one is ignored" (which forbids going back to 2026 in January); a one-time toast (a side effect in a reading called several times per request, plus new UI copy); a shorter session or clearing sessions at the turn.

#### 2. ADR-0075 superseded in part

**File**: `context/foundation/adr/0075-default-budget-year-is-the-current-one.md`

**Intent**: Point readers of the precedence rule to ADR-0111.

**Contract**: Extend the existing „Superseded in part” paragraph with ADR-0111: the year in the session wins only while it is a choice under ADR-0111. The rest stands.

#### 3. Glossary entry Budget

**File**: `context/foundation/glossary.md` (entry Budget, line 32)

**Intent**: The glossary states the rule the code will have: what counts as a choice in the session.

**Contract**: Replace the sentence "Without a choice in the session the app opens …" with one that keeps the default order and adds the session rule. Proposed: "A year chosen in the session is opened while the User has a Budget for it, except the year that was the current calendar year when it was chosen, once the App Day year is later (ADR-0111). Without a choice the app opens the current calendar year when it has a Budget, else the newest year not later than it, else the oldest (ADR-0075)."

### Success Criteria:

#### Automated Verification:

- ADR file `context/foundation/adr/0111-session-year-follows-the-year-turn.md` exists and no other ADR uses number 0111: `ls context/foundation/adr | grep -c '^0111-'` prints 1
- Glossary entry Budget cites ADR-0111: `grep -c 'ADR-0111' context/foundation/glossary.md` ≥ 1

#### Manual Verification:

- The rule in ADR-0111 and in the glossary reads as rule A (only the year that was current when chosen gives way), with the 22:59:59 / 23:00:00 UTC examples, and says the switch is silent

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: The rule in code, test-first

### Overview

Turn the known gap into a failing test, add the boundary cases, then make `SelectedBudgetYear` record and read the choice year. Update README and test plan with the new behaviour.

### Changes Required:

#### 1. Regression and boundary tests (written first, red)

**File**: `tests/Feature/SelectedBudgetYearTest.php`

**Intent**: Rewrite the `_known_gap` test to the required behaviour (requirement change per `test-plan.md:180`) and pin the edges of rule A, so the fix goes red → green.

**Contract**: `resolveFor` gains an optional `?int $chosenIn` that is put in the session as `budget_year_chosen_in`. Tests (names indicative):
- `test_session_year_of_the_old_year_stays_selected_in_january_known_gap` becomes `test_the_current_year_chosen_in_the_session_gives_way_to_the_new_year_in_january`: `[2026, 2027]`, `2027-01-05`, session 2026 chosen in 2026 → 2027. Update its docblock (no longer a gap).
- `test_the_current_year_chosen_in_the_session_changes_at_polish_midnight`: session 2026 chosen in 2026, `2026-12-31 22:59:59` → 2026, `2026-12-31 23:00:00` → 2027.
- `test_a_past_year_chosen_for_browsing_stays_after_the_year_turn`: `[2023, 2026, 2027]`, session 2023 chosen in 2026, `2027-01-05` → 2023.
- `test_the_next_year_chosen_in_december_stays_in_january`: session 2027 chosen in 2026, `2027-01-05` → 2027.
- `test_the_old_year_chosen_again_in_january_stays`: session 2026 chosen in 2027, `2027-01-05` → 2026.
- `test_a_session_year_without_the_choice_year_stays_selected`: session 2026 without marker, `2027-01-05` → 2026 (sessions from before the deploy).
- Existing tests without a marker (`test_session_year_takes_precedence`, `test_session_year_without_a_budget_is_skipped`) stay as they are.

**File**: `tests/Feature/YearTurnTest.php`

**Intent**: Prove the rule end to end through the year menu route and the shell prop, the way the User hit it in the rehearsal.

**Contract**: `test_the_year_chosen_in_the_menu_in_december_gives_way_to_the_new_year_after_polish_midnight`: two Budgets from `givenTwoBudgets()` (2026 execution, 2027 draft: the default year ignores the phase), `travelTo('2026-12-31 22:59:59')`, POST `budget.select-year` with 2026, GET `budget` → `assertInertia` `shell.budgetYear` 2026; `travelTo('2026-12-31 23:00:00')`, GET `budget` → `shell.budgetYear` 2027; POST `budget.select-year` with 2026 again, GET `budget` → 2026.

**File**: `tests/Feature/BudgetTest.php`

**Intent**: Pin that a choice and a new Budget record the choice year, and that deleting the Budget of the session year forgets both keys.

**Contract**: Extend `test_user_can_select_budget_year_in_shell` (`:384`) with `assertSessionHas('budget_year_chosen_in', <App Day year>)`. Add the same assertion to `test_user_can_create_draft_budget_for_a_year_without_one` (`:34`). No test covers `destroy` of the session year today, so add `test_deleting_the_budget_of_the_session_year_forgets_the_choice`: `withSession(['budget_year' => 2026, 'budget_year_chosen_in' => 2026])`, delete the 2026 Budget, `assertSessionMissing` for both keys.

#### 2. Record and read the choice year

**File**: `app/Support/SelectedBudgetYear.php`

**Intent**: Keep both session keys in one place, and apply rule A in `resolve` without side effects.

**Contract**:
- New `public static function choose(Session $session, int $year): void`. It puts `budget_year` = `$year` and `budget_year_chosen_in` = `(int) AppDay::today()->year`. New `public static function forget(Session $session): void` removes both. (`Illuminate\Contracts\Session\Session`.)
- `resolve`: a valid session year that has a Budget is returned unless `budget_year_chosen_in` is an int (or a digit string), equals the session year, and is lower than `AppDay::today()->year`. Then it falls through to `defaultYear`. A missing or malformed marker keeps the session year. `resolve` never writes to the session.
- Update the docblock of `defaultYear`/`resolve` to cite ADR-0111.

**File**: `app/Http/Controllers/BudgetController.php`

**Intent**: Every write of the session year goes through the helper, so no choice is recorded without its year.

**Contract**: `store` (`:121`) and `selectYear` (`:153`) call `SelectedBudgetYear::choose($request->session(), …)`. `destroy` (`:137-139`) calls `SelectedBudgetYear::forget(...)` under the same condition. No other file writes `budget_year` (`grep -rn "'budget_year'" app` shows only these and the reader).

#### 3. README and test plan

**File**: `README.md` (section „Przełom roku”, line 339)

**Intent**: The procedure no longer tells the User to switch the year by hand.

**Contract**: Replace "Rok wybrany w sesji (2026) zostaje po przełomie: przełącz go ręcznie w menu na N+1." with Polish text saying that from 00:00 Polish time a year N chosen during year N gives way to N+1 on its own, while a different year chosen for browsing stays (ADR-0111). Add a line under „Co sprawdzić 1–2 stycznia” if it fits: Budget opens N+1 without changing the year. In „Próba przed przełomem” add that to see the switch the year is chosen before 23:00 UTC on 31.12; a year chosen after it (e.g. after the re-login forced by the clock jump) is a choice in N+1 and stays.

**File**: `context/foundation/test-plan.md` (line 180)

**Intent**: The test plan no longer describes `_known_gap` tests as pinning wrong behaviour: S-42 already rewrote the `CsvImportTest` one, and this change rewrites the last one.

**Contract**: Rewrite the bullet at line 180 (Polish, like the file) in the past tense: S-40 and S-42 rewrote both former `_known_gap` tests to the required behaviour (2027 in January per ADR-0111; the statement per S-42) and dropped the suffix, as a change of requirement and not assertion tuning; no `_known_gap` test remains (`grep -rn _known_gap tests` is empty). The risk #9 rows (lines 55, 71) stay as they are.

### Success Criteria:

#### Automated Verification:

- Before the code change, the rewritten January test and the midnight test fail on PostgreSQL (red): `… artisan test --compact tests/Feature/SelectedBudgetYearTest.php` via the worktree `docker run` command from AGENTS.md with `DB_DATABASE=testing_session_year_follows_year_turn`
- After the change, `SelectedBudgetYearTest`, `YearTurnTest` and `BudgetTest` pass on PostgreSQL (same command with the three paths)
- No `_known_gap` test remains in the suite: `grep -rn '_known_gap' tests` prints nothing
- Only `SelectedBudgetYear` writes the session year: `grep -rn "put('budget_year'" app` prints only `app/Support/SelectedBudgetYear.php`
- Clock guard green: `tests/Unit/AppDayClockGuardTest.php`
- `vendor/bin/pint --dirty --format agent` leaves no changes
- Gate green: `composer ci:check` (worktree `docker run … --entrypoint composer sail-8.5/app ci:check`)
- `vendor/bin/sloppy ci` on the host reports no new finding

#### Manual Verification:

- Preparation (worktree preview, `context/foundation/agents/worktree-preview.md`): the preview seed has only a 2026 Budget. Run `app:rehearsal-clock "2026-12-31 22:30"` in the worktree container (the `docker run … --entrypoint php sail-8.5/app artisan …` form from AGENTS.md; `docker compose exec` would set the main checkout's clock), log in again (a jump over 2 h logs out), and create Budgets 2027 and 2023 on the Budget screen. Creating a Budget writes the session year, so choose the tested year after the creations.
- In the year menu choose 2026, then `app:rehearsal-clock "2026-12-31 23:05"` (same container form): Budget and Month Plan open 2027 without a toast, and the menu shows 2027
- In a second session (private window) logged in at the same 22:30 clock, choose 2023; after the clock moves to 23:05 it still opens 2023
- README „Przełom roku” reads correctly in Polish

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None beyond the existing clock guard: the rule needs a session and Budgets, so it lives in the feature test `SelectedBudgetYearTest`, as today.

### Integration Tests:

- `SelectedBudgetYearTest`: rule A on the Polish midnight boundary (22:59:59 / 23:00:00 UTC), browsed past year, next year chosen in December, old year chosen again in January, session without marker.
- `YearTurnTest`: year menu POST in December → `shell.budgetYear` after midnight → choosing the old year again.
- `BudgetTest`: `choose` on select and store, `forget` on destroy.

### Manual Testing Steps:

1. Worktree preview, `app:rehearsal-clock "2026-12-31 22:30"` run in the worktree container, log in again, create Budgets 2027 and 2023, then pick 2026 in the year menu and open Budget. The rehearsal clock stands still: every step below moves it explicitly.
2. `app:rehearsal-clock "2026-12-31 23:05"`, reload Budget: 2027, no toast; open Month Plan and Reports: 2027.
3. Pick 2026 again: it stays on further visits.
4. In a second session (private window) logged in at step 1, pick 2023 before the clock moves; after step 2 it still opens 2023. (The command refuses a moment earlier than the real now minus the 120-minute session lifetime; it does not compare with the previous rehearsal moment.)

## Migration Notes

No database migration. Live sessions without `budget_year_chosen_in` keep today's precedence and expire within 120 minutes. Deploy before 31 December so the first year turn runs on the new rule (roadmap open question 2).

## References

- Roadmap item: `context/foundation/roadmap.md` S-40 (MS-02)
- Rehearsal, gap 3: `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md:29,40,58`
- ADR-0075 (default year), ADR-0103 (App Day), ADR-0031 (Dashboard year)
- Reader: `app/Support/SelectedBudgetYear.php:11-36`; writers: `app/Http/Controllers/BudgetController.php:121,137-139,153`
- Test patterns: `tests/Feature/SelectedBudgetYearTest.php:22-43`, `tests/Feature/YearTurnTest.php:75-115`
- Clock rule: `.ai/rules/app-day.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Glossary and ADR-0111

#### Automated

- [x] 1.1 ADR file `context/foundation/adr/0111-session-year-follows-the-year-turn.md` exists and no other ADR uses number 0111
- [x] 1.2 Glossary entry Budget cites ADR-0111

#### Manual

- [ ] 1.3 The rule in ADR-0111 and in the glossary reads as rule A, with the 22:59:59 / 23:00:00 UTC examples, and says the switch is silent

### Phase 2: The rule in code, test-first

#### Automated

- [ ] 2.1 Before the code change, the rewritten January test and the midnight test fail on PostgreSQL (red)
- [ ] 2.2 After the change, `SelectedBudgetYearTest`, `YearTurnTest` and `BudgetTest` pass on PostgreSQL
- [ ] 2.3 No `_known_gap` test remains in the suite
- [ ] 2.4 Only `SelectedBudgetYear` writes the session year
- [ ] 2.5 Clock guard green: `tests/Unit/AppDayClockGuardTest.php`
- [ ] 2.6 `vendor/bin/pint --dirty --format agent` leaves no changes
- [ ] 2.7 Gate green: `composer ci:check`
- [ ] 2.8 `vendor/bin/sloppy ci` on the host reports no new finding

#### Manual

- [ ] 2.9 Rehearsal clock 22:30 → 23:05 UTC: a 2026 chosen in the menu gives way to 2027 in Budget and Month Plan, no toast
- [ ] 2.10 A 2023 chosen for browsing in a second session stays after 23:00 UTC
- [ ] 2.11 README „Przełom roku” reads correctly in Polish
