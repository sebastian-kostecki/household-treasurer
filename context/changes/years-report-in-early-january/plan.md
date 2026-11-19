# Years report in early January Implementation Plan

## Overview

Roadmap item S-43 (MS-05, gap 5 of the year-turn rehearsal). On the first days of January the Years view („Lata”) opens in „Do D”, which cuts every earlier year to 1–N January, so „Zmiana od 2020” and „Średnio rocznie” compare one or two days with one or two days. This plan makes „Lata” and the „Lata” tab of the Category Panel open in Full Years („Pełne lata”) for the whole of January (the current year has no closed month yet), keeps „Do D” one click away in the table's range switch, and proves the year-over-year comparison of the Year and Month reports on 1 January with tests, without changing it.

## Current State Analysis

- `ReportYearsRange::resolve` (`app/Support/ReportYearsRange.php:33-50`) sets the cut to the App Day's month and day whenever the last Budget in execution is the current calendar year; there is no January case, so on 1 January the cut is `{month: 1, day: 1}`. `throughOf` (`:85-94`) cuts every earlier year at that day and never the current year.
- `ReportYears::toArray` (`app/Support/ReportYears.php:82-91`) sends `{columns, current_year, cut, same_day, full}`; `same_day` is `null` only without a current year. The client opens „Do D” whenever `same_day` is not `null` (`resources/js/components/ReportYearsView.vue:21-23`) and shows the range switch on the same condition.
- The Category Panel decides its years mode on the server: `$sameDay = $yearsShape['current_year'] === $budget->year` (`app/Support/ReportCategoryPanel.php:49`), sent as `years.mode` (`:102`); the panel has no mode switch (ADR-0101).
- With a draft current year (January 2027 before the start) `same_day` is `null` and every year is full: already covered by `ReportYearsRangeTest:60`, `ReportYearsTest:478`, `ReportCategoryPanelTest:410`, `ReportsPageTest:797` and the scenario `years-no-current-year`.
- Year over year (ADR-0074): `ReportComparisonPeriod::resolve` (`app/Support/ReportComparisonPeriod.php:61-86`) cuts a period in progress at `$today->subYearNoOverflow()`. On App Day 2027-01-01 both the Year 2027 and the Month January 2027 compare with 2026-01-01..2026-01-01, `is_partial: true`, note „Okres w toku: rok 2026 jest porównany do tego samego dnia, czyli od 01.01.2026 do 01.01.2026.” (`ReportStatPanel.vue:96-131`, `lang/pl/ui.php:465`). Only the year case on 1 January has a test (`ReportComparisonPeriodTest.php:74`); there is no test of the January month, of the amounts, of the page, or at Polish midnight, and `ReportAmountInvariantTest` has no comparison case.
- The glossary entry Years (`context/foundation/glossary.md:184`) and ADR-0097 („Same-day Cut … the default”) define „Do D” as the default, so the rule changes in the glossary and a new ADR first (AGENTS.md hard rule).

## Desired End State

- On App Day 1–31 January with the current year in execution, `?view=years` opens in „Pełne lata”: the finished years are counted whole, the current year is „w toku” outside the change and statistics, the switch „Do <D> stycznia | Pełne lata” is shown and „Do D” works as today. From 1 February the default is „Do D” again.
- In the same period the „Lata” tab of the Category Panel of the current Year shows Full Years (`mode: 'full'`, caption „Pełne lata”); its N−1 bar is the whole year, while the N−1 tile of the panel stays the year-over-year amount up to the same day (ADR-0108 records this exception).
- The Year and Month reports on 1 January keep comparing with 1 January of the year before, and tests prove it at 31.12 22:59:59 and 23:00:00 UTC, with hand-counted amounts in `ReportAmountInvariantTest`.
- Verify: the Phase 2 and Phase 3 tests pass on PostgreSQL, the full gate is green, and the kitchen sink scenarios `years-january` and `panel-years-january` render.

### Key Discoveries:

- The decision belongs on the server: the client reads no clock (`.ai/rules/app-day.md`), so the server sends the default range as a prop field and the client only picks it up.
- Full Years rows are a superset of the Same-day rows (every full range contains its cut range, the current year is equal in both), so switching the panel to `full` never hits the `LogicException` of `ReportCategoryPanel.php:85-87`.
- `opensPanel` (`ReportYears.php:187-209`) uses the cut counts because it mirrors the rows of the Year report with its year-over-year comparison; that comparison does not change, so `opens_panel` stays as it is.
- Polish midnight is 23:00:00 UTC on 31 December and on 31 January (winter), the boundaries `.ai/rules/app-day.md` requires for `travelTo`.

## What We're NOT Doing

- No change to the year-over-year comparison of the Year and Month reports, nor to its note text (decision 3: tests only; ADR-0074's rejection of a whole previous year for a period in progress stays).
- No cut to the end of the last closed month and no change to what „Do D” counts in any month (option B rejected).
- No hint or banner in „Lata” (option C rejected), no new UI copy.
- No mode switch in the Category Panel (that rule of ADR-0101 stands; only its range sentence, ADR-0101:11, is amended for January).
- No E2E test in January (would need a server clock in Playwright; out of S-43).
- No change for a draft current year (already full years without the switch).
- No session-year fix (S-40) and no year-turn notices (S-41).

## Implementation Approach

Docs first, then one server rule that both readers use. `ReportYearsRange` gets one predicate, „the current year has a closed month”, true when there is a cut and its month is above January. `ReportYears::toArray` turns it into a `default_range` field; `ReportCategoryPanel` uses the same field for its mode; the page's local `range` ref starts from it through a pure helper in `@/lib/report-years`. The PHP shape, the TS type, the fixtures and an `assertInertia` test change in one commit. The year-over-year proof is test-only and independent of Phase 2.

## Critical Implementation Details

- **ADR number.** S-40 and S-41 run in parallel and may also write ADR-0108; the change that merges into `development` second takes the next free number and renames every `ADR-0108` reference (`context/foundation/agents/parallel-changes.md`, „Merging into `development`”).

## Phase 1: Decision in the documents

### Overview

Record the January default and the panel tile exception before any code, as AGENTS.md requires for a glossary rule.

### Changes Required:

#### 1. New ADR

**File**: `context/foundation/adr/0108-years-open-in-full-years-in-january.md`

**Intent**: Record that „Lata” and the panel's „Lata” tab open in Full Years while the current year has no closed month (App Day in January), with „Do D” still in the table's switch; record the rejected options (cut to the end of the last closed month, a hint, a fixed 7 or 14 days) and that year over year stays as ADR-0074 with tests added.

**Contract**: Sections in the style of ADR-0097: context (gap 5, the 1-day example), rule (default range = Full Years when the cut month is January, otherwise Same-day Cut; a draft current year unchanged), Category Panel (mode follows the same rule; in January the N−1 bar is the whole year while the N−1 tile stays the year-over-year amount, which amends the reports-rule sentence „the bars of the viewed year and the year before equal the tiles” for January only), a worked example on 2 January 2027 with illustrative numbers (Full Years „Zmiana 2020–2026”), the boundary at 31 December and 31 January 23:00 UTC, „We rejected”. States the clauses it amends: ADR-0097:9 („Do D” the default) and :25 (the panel tab „by default with the Same-day Cut”), and ADR-0101:11 (the panel counts up to the same day in the current year so the bars equal the tiles); ADR-0101's „no mode switch in the panel” and ADR-0074 stay unchanged.

#### 2. Glossary

**File**: `context/foundation/glossary.md` (entry Years, line 184)

**Intent**: Replace „The Same-day Cut („Do 14 listopada”, the default)” with the default from February and Full Years as the default in January (the current year has no closed month), citing ADR-0108.

**Contract**: Only the Years paragraph changes; no new term.

#### 3. ADR-0097 note

**File**: `context/foundation/adr/0097-report-years-ranking-and-chart-colours.md`

**Intent**: Add an „Amended in part by ADR-0108” line next to the existing ADR-0103 note, naming the default of the Same-day Cut and the panel's same_day mode in January.

**Contract**: One added paragraph after the ADR-0103 note (line 5); no other text changes.

#### 4. ADR-0101 note

**File**: `context/foundation/adr/0101-report-panel-years-tab.md`

**Intent**: Add an „Amended in part by ADR-0108” line naming the range sentence (:11): in January the tab counts Full Years in the current year too, and the N−1 bar no longer equals the N−1 tile; the rest of ADR-0101, including no mode switch, stands.

**Contract**: One added paragraph under the introduction; no other text changes.

### Success Criteria:

#### Automated Verification:

- ADR-0108 exists and is referenced from the glossary, ADR-0097 and ADR-0101: `grep -rn "ADR-0108" context/foundation`
- No other ADR uses the number 0108 in this branch: `ls context/foundation/adr | grep ^0108`

#### Manual Verification:

- The user reads ADR-0108 and the glossary entry and confirms they match the brief's decisions (Full Years by default in January, for the whole of January, the panel follows the same rule with the tile exception, year over year tests only)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Full Years by default in January

### Overview

One server predicate drives the default range of „Lata” and the panel's mode; the page starts from it; the rule file, the fixtures and the kitchen sink follow.

**Order (test first, CLAUDE.local.md):** write the tests of §7 before §1–§6 and run them red: the panel test fails on `mode: 'same_day'` on 2027-01-02, the page test on the missing `years.default_range` at `2026-12-31 23:00:00`, the range test on the missing `opensOnSameDay`. Then §1–§6 turn them green.

### Changes Required:

#### 1. Range predicate

**File**: `app/Support/ReportYearsRange.php`

**Intent**: Add a method telling whether the Same-day Cut is the default: there is a cut and its month is after January (the current year has a closed month).

**Contract**: `public function opensOnSameDay(): bool` — `$this->cut !== null && $this->cut['month'] > 1`. `resolve`, `cut()` and `throughOf` do not change, so `same_day` stays computed in January.

#### 2. Years shape

**File**: `app/Support/ReportYears.php`

**Intent**: Send the default range with the shape.

**Contract**: `toArray()` adds `'default_range' => $this->range->opensOnSameDay() ? 'same_day' : 'full'`; the `YearsShape` phpstan type gets `default_range: 'same_day'|'full'`. `same_day` keeps its current rule (null only without a current year).

#### 3. Category Panel mode

**File**: `app/Support/ReportCategoryPanel.php:49`

**Intent**: The panel's „Lata” tab follows the same default: same day only for the current year when the default range is same day.

**Contract**: `$sameDay = $yearsShape['current_year'] === $budget->year && $yearsShape['default_range'] === 'same_day';` — `years.mode` and `categoriesById($sameDay)` follow from it. The class docblock (`:25-28`, „"same day" for the current year … so the bars … equal the tiles”) states the January exception (ADR-0108).

#### 4. Types and the page

**Files**: `resources/js/types/reports.ts` (`ReportYearsData`), `resources/js/lib/report-years.ts`, `resources/js/components/ReportYearsView.vue:21-23`

**Intent**: Mirror the new field and start the local range from it through a pure helper, so the component holds no rule.

**Contract**: `ReportYearsData.default_range: 'same_day' | 'full'`. `reportYearsInitialRange(years: Pick<ReportYearsData, 'same_day' | 'default_range'>): 'same-day' | 'full'` returns `'same-day'` only when `same_day !== null` and `default_range === 'same_day'`. The range switch stays shown whenever `same_day !== null`; subtitles and labels unchanged. The comment on `ReportCategoryPanelYears.mode` (`reports.ts:236`, „`same_day` for the current year”) adds „except in January (ADR-0108)”.

#### 5. Fixtures and kitchen sink

**File**: `resources/js/lib/reports-fixtures.ts`

**Intent**: Every Years fixture carries `default_range`; add the January states.

**Contract**: `buildYears` sets `default_range` from its `cut` (`'same_day'` when the cut month is above 1). New scenario `years-january` (2 January 2027, 2027 in execution, cut `{1, 2}`, `default_range: 'full'`; caption names the default „Pełne lata” and the switch „Do 2 stycznia”) and `panel-years-january` (Year 2027 panel on tab „Lata” with `mode: 'full'`, current year „w toku”).

#### 6. Reports rule

**File**: `.ai/rules/reports.md`

**Intent**: Update the two sentences this changes, with the code.

**Contract**: Years view section: the client's range starts from `default_range` (Full Years in January, ADR-0108). Panel paragraph: `same_day` in the current Year except in January, `full` otherwise; in January the N−1 bar is the whole year and the tile stays the year-over-year amount. Then `php artisan boost:index-rules` if the index changes (it should not).

#### 7. Tests

**Files**: `tests/Feature/ReportYearsRangeTest.php`, `tests/Feature/ReportYearsTest.php`, `tests/Feature/ReportCategoryPanelTest.php`, `tests/Feature/ReportsPageTest.php`, `resources/js/lib/report-years.test.ts`

**Intent**: Prove the predicate at its boundaries, the shape, the panel mode, and the prop on the page.

**Contract**:
- Range: `opensOnSameDay` false on 2027-01-01 and 2027-01-31, true on 2027-02-01 and 2028-02-29, false without a current year.
- Years: on 2027-01-02 `same_day` is not null and `default_range` is `'full'`; on 2026-11-14 it is `'same_day'`.
- Panel: on 2027-01-02 the current Year's panel has `mode: 'full'` and its years equal the Full Years row; on 2027-02-01 `'same_day'` as today.
- Page (`assertInertia`, `travelTo`): at `2026-12-31 22:59:59` `years.default_range` is `'same_day'` with cut `{12, 31}`; at `2026-12-31 23:00:00` `current_year` 2027, cut `{1, 1}`, `default_range` `'full'`, `same_day` present, and the Year 2027 `categoryPanel.years.mode` is `'full'`; at `2027-01-31 22:59:59` `'full'`, at `2027-01-31 23:00:00` `'same_day'`.
- Vitest: `reportYearsInitialRange` for the three cases (same day default, full default with same_day present, same_day null).

### Success Criteria:

#### Automated Verification:

- Phase 2 Feature tests pass on PostgreSQL (worktree command from AGENTS.md, `DB_DATABASE=testing_years_report_in_early_january`): `artisan test --compact tests/Feature/ReportYearsRangeTest.php tests/Feature/ReportYearsTest.php tests/Feature/ReportCategoryPanelTest.php tests/Feature/ReportsPageTest.php`
- Vitest passes: `npx vitest run resources/js/lib/report-years.test.ts resources/js/lib/report-category-panel.test.ts`
- Build and types pass: `npm run build && npm run types:check`
- UI guards pass: `npm run check:ui`
- Full gate on PostgreSQL and Sloppy pass: `composer ci:check` (worktree command) and `vendor/bin/sloppy ci`
- Formatting applied: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- `/dev/ui/reports` scenario `years-january` opens on „Pełne lata” with „Zmiana 2020–2026” and the switch „Do 2 stycznia | Pełne lata”; clicking „Do 2 stycznia” shows the cut table (light, dark, phone width)
- Scenario `panel-years-january` shows the „Lata” tab with the caption „Pełne lata” and 2027 „w toku”
- Scenario `years-same-day` still opens on „Do 14 listopada”

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Year over year on 1 January: proof

### Overview

Test-only: the Year and Month reports on 1 January compare with 1 January of the year before, at Polish midnight, with hand-counted amounts.

### Changes Required:

#### 1. Comparison range

**File**: `tests/Feature/ReportComparisonPeriodTest.php` (dataset at `:59-75`)

**Intent**: Add the January month rows next to the existing year row.

**Contract**: Rows `'first day of January month'` `[1, '2026-01-01', '2025-01-01', '2025-01-01', true]` and `'last day of January month'` `[1, '2026-01-31', '2025-01-01', '2025-01-31', false]` (the dataset always creates Budget 2026 with 2025 before it, like the existing `'first day of the year'` row).

#### 2. Comparison amounts

**File**: `tests/Feature/PeriodReportTest.php` (next to `:449`)

**Intent**: On App Day 2027-01-01 the year and the January comparison count an operation dated 2026-01-01 and not one dated 2026-01-02.

**Contract**: One test, Budgets 2026 and 2027 in execution, both periods asserted.

#### 3. Page at Polish midnight

**File**: `tests/Feature/ReportsPageTest.php` (next to `:206`)

**Intent**: Prove the props the user sees at the turn.

**Contract**: At `2026-12-31 22:59:59` the Year 2026 report compares with the whole 2025 (`is_partial: false`, through `2025-12-31`); at `2026-12-31 23:00:00` the Year 2027 report and `?month=1` compare with `2026-01-01..2026-01-01`, `is_partial: true`, `is_in_progress: true`.

#### 4. Amount invariant

**File**: `tests/Feature/ReportAmountInvariantTest.php`

**Intent**: A hand-counted literal per reading on 1 January (`travelTo('2026-12-31 23:00:00')`), as `.ai/rules/reports.md` asks for a new boundary.

**Contract**: One new test with operations in 2026 on 01.01, 02.01 and later months and in 2027 on 01.01: the Year 2027 comparison expense total, the January 2027 comparison total, „Lata” Same-day „Razem” 2026 and Full Years „Razem” 2026 each equal a literal counted by hand; Full Years „Razem” 2026 equals the literal of the Year 2026 report.

### Success Criteria:

#### Automated Verification:

- Phase 3 tests pass on PostgreSQL (worktree command): `artisan test --compact tests/Feature/ReportComparisonPeriodTest.php tests/Feature/PeriodReportTest.php tests/Feature/ReportsPageTest.php tests/Feature/ReportAmountInvariantTest.php`
- Full gate on PostgreSQL and Sloppy pass: `composer ci:check` (worktree command) and `vendor/bin/sloppy ci`
- No production code changed in this phase: `git diff --stat HEAD~1 -- app resources` is empty for the phase commit

**Implementation Note**: After completing this phase and all automated verification passes, the plan is complete; the rehearsal clock check under Manual Testing Steps is optional.

---

## Testing Strategy

### Unit Tests:

- `reportYearsInitialRange` in Vitest (no fake timers; the day is not an input, the server field is).
- `ReportYearsRange::opensOnSameDay` at 01.01, 31.01, 01.02, 29.02 and without a current year.

### Integration Tests:

- `ReportsPageTest` at 31.12 and 31.01 22:59:59 / 23:00:00 UTC for `years.default_range`, the panel mode and the year-over-year props.
- `ReportAmountInvariantTest` hand-counted case on 1 January.

### Manual Testing Steps:

1. In the worktree preview (`context/foundation/agents/worktree-preview.md`) open `/dev/ui/reports` and walk `years-january`, `panel-years-january`, `years-same-day`.
2. Optional: `app:rehearsal-clock` on the preview database at `2027-01-01 08:00` UTC with 2026 and 2027 in execution, open „Lata” and the Year 2027 panel; then `--off`.

## Performance Considerations

None: `default_range` is derived from data already in `ReportYearsRange`; no query is added (the query-count tests `ReportYearsTest:533` and `ReportCategoryPanelTest:446` keep guarding it).

## Migration Notes

None: no schema or stored data changes.

## References

- Roadmap item: `context/foundation/roadmap.md` S-43 (MS-05)
- Rehearsal gap 5 and skipped T3: `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md:49,60`
- Decisions: ADR-0097 (Years, Same-day Cut), ADR-0074 (year over year), ADR-0101 (panel tab), ADR-0103 (App Day)
- Similar implementation: the draft current year path `ReportYearsRange.php:44-49`, `ReportYearsView.vue:21-23`, scenario `years-no-current-year`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decision in the documents

#### Automated

- [x] 1.1 ADR-0108 exists and is referenced from the glossary, ADR-0097 and ADR-0101 — d521550
- [x] 1.2 No other ADR uses the number 0108 in this branch — d521550

#### Manual

- [ ] 1.3 The user confirms ADR-0108 and the glossary entry match the brief's decisions

### Phase 2: Full Years by default in January

#### Automated

- [x] 2.1 Phase 2 Feature tests pass on PostgreSQL
- [x] 2.2 Vitest passes
- [x] 2.3 Build and types pass
- [x] 2.4 UI guards pass
- [x] 2.5 Full gate on PostgreSQL and Sloppy pass
- [x] 2.6 Formatting applied

#### Manual

- [ ] 2.7 Scenario years-january opens on „Pełne lata” with the switch „Do 2 stycznia”
- [ ] 2.8 Scenario panel-years-january shows „Pełne lata” and 2027 „w toku”
- [ ] 2.9 Scenario years-same-day still opens on „Do 14 listopada”

### Phase 3: Year over year on 1 January: proof

#### Automated

- [ ] 3.1 Phase 3 tests pass on PostgreSQL
- [ ] 3.2 Full gate on PostgreSQL and Sloppy pass
- [ ] 3.3 No production code changed in this phase
