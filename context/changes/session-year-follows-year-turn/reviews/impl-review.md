<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Session Year Follows the Year Turn

- **Plan**: context/changes/session-year-follows-year-turn/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-19
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Evidence

- Every planned change is MATCH: ADR-0111 (all six examples and the four rejected alternatives), the ADR-0075 partial supersede, the glossary entry Budget (the plan's text word for word), `SelectedBudgetYear::choose/forget/resolve`, the three `BudgetController` writers, the tests in `SelectedBudgetYearTest`, `YearTurnTest` and `BudgetTest`, README „Przełom roku”, and test-plan line 180. Files outside the plan (roadmap, plan-brief, plan-review, change.md) are bookkeeping. „What We're NOT Doing” holds: no toast, no Inertia prop or `resources/js` change, no migration.
- The predicate `chosenIn === S && chosenIn < AppDay year` matches the ADR. `resolve()` only calls `session()->get`. No new queries.
- PostgreSQL (`testing_session_year_follows_year_turn`, worktree container): `SelectedBudgetYearTest`, `YearTurnTest`, `BudgetTest` and `AppDayClockGuardTest` passed: 101 tests, 757 assertions.
- Red (2.1), rechecked: the same tests run against `app/Support/SelectedBudgetYear.php` and `BudgetController.php` from `0af5e5c0`, mounted read-only. 6 failed (the January test, the midnight test, the `YearTurnTest` end-to-end test, three in `BudgetTest`) and 91 passed.
- `grep -rn _known_gap tests` is empty. `grep -rn "put('budget_year'" app` is empty: the helper writes through a constant.
- Pint `--test` on the five PHP files passed. Sloppy `ci` (Sail image) passed with nothing at high: 6 medium/low findings in OAuth migrations from before this change.
- `composer ci:check` on PostgreSQL (worktree container, `testing_session_year_follows_year_turn`): exit 0, 2154 passed, 4 skipped (16438 assertions).

## Findings

### F1 — `destroy` still reads the raw session key

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Controllers/BudgetController.php:137
- **Detail**: The plan says both session keys live in one place. `destroy` still checks `(int) $request->session()->get('budget_year') === $budget->year` with a string literal and its own `(int)` cast, while `SelectedBudgetYear` keeps the key in a private `YEAR_KEY` and parses it with `intOrNull`. It works today, because only `choose` writes and always writes an int. Still, the key string now lives in two places, and the docblock claims `SelectedBudgetYear` owns the session year. The plan's grep criterion 2.4 (`put('budget_year'`) cannot see this read.
- **Fix**: Add `SelectedBudgetYear::forgetIfChosen(Session $session, int $year)` (it uses `intOrNull` and forgets both keys) and call it from `destroy` in place of the `if`.
- **Decision**: PENDING

### F2 — Manual checks pending while change.md said `implemented`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/session-year-follows-year-turn/plan.md:225,242-244
- **Detail**: The manual rows 1.3 (reading ADR-0111 and the glossary) and 2.9–2.11 (the `app:rehearsal-clock` pass 22:30 → 23:05 UTC, 2023 in a second session, README wording) are unchecked. The epilogue had already set `change.md` to `implemented`, and the roadmap shows S-40 in progress. The automated rows are all backed by evidence (see above). No row is falsely ticked.
- **Fix**: Run the manual pass from plan "Manual Testing Steps" in the worktree preview, then tick 1.3 and 2.9–2.11 before `/10x-archive`.
- **Decision**: PENDING

### F3 — README sentence does not say what year N gives way to

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: README.md:339
- **Detail**: "Rok N … ustępuje od 00:00 czasu polskiego roku N+1 samoczynnie" reads "roku N+1" as the genitive of "00:00", so the sentence never says that N gives way *to* N+1, which is what the plan asked for. This belongs to manual check 2.11.
- **Fix**: "…w trakcie roku N od 00:00 czasu polskiego 1 stycznia ustępuje samoczynnie rokowi N+1, bez komunikatu (ADR-0111)."
- **Decision**: PENDING

### F4 — Edge cases of the rule that no test pins

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/SelectedBudgetYearTest.php, tests/Feature/BudgetTest.php:47,412
- **Detail**: Mutation reasoning on `resolve`: every clause of the predicate is killed by a test, except these:
  - (a) `<` → `!==` survives. A marker later than the App Day year (the clock moved back after a rehearsal, `--off`) must keep the year per ADR-0111, and nothing checks that.
  - (b) A malformed marker (`'abc'`, a string), which ADR-0111 says keeps the session year, is not tested.
  - (c) Deleting the Budget of a different year has no negative case: an unconditional `forget` in `destroy` would survive. This was already untested before the change.
  - (d) `assertSessionHas('budget_year_chosen_in', AppDay::today()->year)` computes the expected value with the same helper as production, where a literal `2026` after `travelTo('2026-06-15')` would be stronger.

  `<` → `<=` and dropping `$chosenIn !== null` are equivalent mutants, so they need no test.
- **Fix**: Add `resolveFor([2026, 2027], '2026-11-10', 2027, 2027)` → 2027 and a malformed-marker case (widen `$chosenIn` to `mixed`). Add a negative destroy case (session 2025, delete draft 2027, `assertSessionHas('budget_year', 2025)`). Assert the literal `2026` in `BudgetTest`.
- **Decision**: PENDING
