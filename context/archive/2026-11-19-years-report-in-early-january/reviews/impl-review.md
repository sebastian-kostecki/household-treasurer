<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Years report in early January

- **Plan**: context/changes/years-report-in-early-january/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-19
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated checks run during the review:

- `grep -rn "ADR-0108" context/foundation`: glossary:184, ADR-0097:7 and ADR-0101:5 reference it (pass)
- `ls context/foundation/adr | grep ^0108`: one file (pass)
- `git show --stat b125d7ea -- app resources`: empty (pass)
- Phase 2 and Phase 3 Feature tests on PostgreSQL (`testing_years_report_in_early_january`): 149 passed, 2112 assertions (pass)
- `npx vitest run resources/js/lib/report-years.test.ts resources/js/lib/report-category-panel.test.ts`: 34 passed (pass)
- `npm run build && npm run types:check`: pass
- `npm run check:ui`: pass
- `vendor/bin/sloppy ci` (sail-8.5 image): passed, nothing at high or above; its 3 medium findings are in files outside this change
- `composer ci:check` on PostgreSQL (worktree command): exit 0, 1984 passed, 4 skipped (pass)

Unplanned diff: `context/foundation/roadmap.md` only moves S-43 through its status lifecycle (legitimate).

## Findings

### F1 — January with a single year opens on an empty Full Years table

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/ReportYearsRange.php:86 (`opensOnSameDay`), app/Support/ReportYears.php:89, app/Support/ReportCategoryPanel.php:52
- **Detail**: `opensOnSameDay()` is false for any January cut. When the current year is the only column (the first year with a Budget in execution), Full Years counts 0 columns (`ReportYears::mode`, `$counted === 0` gives `final_year: null`). „Lata” and the panel's „Lata” tab now open on a table with nothing counted: no final year for the tiles, change „—”, the only column „w toku”. Before the change the same page opened on „Do D” with that year counted. ADR-0108's reason, comparing one day with one day, does not apply when there is no earlier year. Nothing crashes, because this state was already reachable by clicking „Pełne lata”, but the default is worse. Neither the ADR nor any test covers it.
- **Fix A ⭐ Recommended**: Keep the Same-day Cut as the default when the current year is the only column: `$this->cut !== null && ($this->cut['month'] > 1 || count($this->columns()) === 1)`. Add a line to ADR-0108, a `ReportYearsRangeTest` row (January, one year → true), and a `years-january-single-year` scenario.
  - Strength: The default always shows a counted year. The rule stays in the one server predicate that both readers use.
  - Tradeoff: The predicate gains a second condition and the ADR rule gains an exception. ADR-0108 is still on this branch, so this is a cheap edit.
  - Confidence: MED — the counted-0 path is verified in `ReportYears::mode`; how the Vue full-mode view renders 0 counted years was not checked in a browser.
  - Blind spot: How often a real household starts its first Budget in January.
- **Fix B**: Accept the behaviour and record it in ADR-0108 (a single-year January opens on Full Years with the year „w toku”, and „Do D” is one click away).
  - Strength: No code change. The rule stays „January ⇒ Full Years” without exceptions.
  - Tradeoff: The first-time user sees an empty-looking report on their first visit in January.
  - Confidence: HIGH — documentation only.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A — `opensOnSameDay()` also returns true when the current year is the only column; red-first case in `ReportYearsRangeTest`; ADR-0108 (rule paragraph and panel sentence), glossary Years, `.ai/rules/reports.md` and the `ReportCategoryPanel` docblock name the exception; `buildYears` mirrors it and scenario `years-january-single-year` shows it

### F2 — Kitchen sink `panel-years-january` shows November-2026 data for 2 January 2027

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/lib/reports-fixtures.ts:2300-2347
- **Detail**: The scenario reuses `comparedYearReport`. Its comparison runs 2025-01-01..2025-11-11, and Żywność is 100 000 with a previous amount of 91 000. The „Lata” bars meanwhile say 2027 = 1 200 and 2026 = 100 000. The current-year tile (1 000 zł) therefore contradicts the current-year bar (12 zł), although the two are equal in both modes. The N−1 tile is a 2025 comparison, not 01–02.01.2026. The one thing ADR-0108 wants this scenario to show is that the N−1 bar is the whole year while the N−1 tile is the year-over-year amount up to 2 January. That cannot be checked by eye, so manual check 2.8 cannot verify it. The sibling `panel-years-past-year` has its own `pastYearComparedReport`.
- **Fix**: Add a `januaryComparedReport`: Year 2027, comparison 2026-01-01..2026-01-02, `is_partial: true`, Żywność 1 200 against a small two-day N−1 amount. Use it in `panel-years-january`.
- **Decision**: FIXED — `januaryComparedReport` (Year 2027, comparison 2026-01-01..2026-01-02, Żywność 1 200 vs 900, Media 800 vs 600) feeds `panel-years-january`, with a Full Years row for Media too

### F3 — Test assertions weaker than the contract

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/ReportsPageTest.php:785, tests/Feature/ReportAmountInvariantTest.php:279, resources/js/lib/report-years.test.ts (third case)
- **Detail**:
  - `->has('years.same_day')` also passes when the value is `null`, so the page test does not prove that the switch is offered in January. The plan contract says „`same_day` present”. `ReportYearsTest` covers it at the shape level.
  - The invariant test checks only `full…cells[0]`, not the current year's full cell (7 000), which is the bar the January panel now shows.
  - The third Vitest case `{same_day: null, default_range: 'same_day'}` is a combination the server never sends. The realistic case is `default_range: 'full'`.
- **Fix**: Make three changes:
  - use `->whereNot('years.same_day', null)` in the page test;
  - assert `->toBe([3_521_000, 7_000])` in the invariant test;
  - change the third Vitest case to `default_range: 'full'`, or add that case next to it.
- **Decision**: FIXED — `whereNot('years.same_day', null)`, `toBe([3_521_000, 7_000])`, and a realistic `{same_day: null, default_range: 'full'}` Vitest case next to the defensive one

### F4 — Manual verification still pending

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/years-report-in-early-january/plan.md (Progress 1.3, 2.7–2.9)
- **Detail**: No manual row is ticked, so none is falsely ticked, but all of them are pending:
  - Row 1.3 is the user's confirmation of ADR-0108. The plan made it a pause point before Phase 2, yet Phases 2 and 3 landed.
  - Rows 2.7–2.9 are the kitchen-sink walkthrough. Row 2.8 depends on F2.
  - `change.md` already says `implemented`, and the roadmap still lists the S-43 Unknown that ADR-0108 answers.
- **Fix**: After F2, walk `years-january`, `panel-years-january` and `years-same-day` in the worktree preview, then tick 1.3 and 2.7–2.9 and remove the answered Unknown from the S-43 roadmap entry.
- **Decision**: PARTLY FIXED — the S-43 Unknown in the roadmap is marked resolved by ADR-0108; manual rows 1.3 and 2.7–2.9 stay for the user (now with `years-january-single-year` to walk as well)
