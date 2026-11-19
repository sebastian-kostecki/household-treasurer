# Years report in early January — Plan Brief

> Full plan: `context/changes/years-report-in-early-january/plan.md`

## What & Why

Roadmap S-43 (gap 5 of the year-turn rehearsal): on the first days of January the Years view („Lata”) opens in „Do D” and cuts every earlier year to 1–N January, so „Zmiana od 2020” and „Średnio rocznie” compare a day with a day. The user should get a reading that makes sense, and the year-over-year result on 1 January, skipped in rehearsal step T3, should be proven.

## Starting Point

`ReportYearsRange` cuts each earlier year at the App Day's month and day with no January case, and the page opens „Do D” whenever the server sends `same_day`; the Category Panel uses `same_day` for the current Year. The Year and Month reports on 1 January compare with 1 January of the year before (ADR-0074), with one unit test for the year only.

## Desired End State

From 1 to 31 January (the current year has no closed month), „Lata” and the panel's „Lata” tab open in „Pełne lata”: finished years whole, the current year „w toku” outside the change. „Do <D> stycznia” stays in the table's switch. From 1 February „Do D” is the default again. Year over year is unchanged and proven by tests at Polish midnight and with hand-counted amounts.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| What „Lata” show in early January | Full Years by default, the switch stays | A meaningful reading at once, reusing the existing mode and its equality with the Year report. |
| How long „early January” lasts | The whole of January (no closed month yet) | A named rule, consistent with „Średnio na pełny miesiąc” being „—” in January; 7 or 14 days would be magic numbers. |
| Category Panel „Lata” tab | Follows the same rule (`mode: 'full'` in January) | One rule for both readings; the panel has no mode switch (ADR-0101). |
| Panel tiles vs bars in January | N−1 bar = whole year, N−1 tile = year over year to the same day | The tile is the year-over-year reading, the tab the years reading; ADR-0108 records the exception. |
| Year over year on 1 January | Tests only, no behaviour or copy change | ADR-0074 is right (same part of the calendar) and rejected the whole previous year for a period in progress. |
| Where the rule lives | Server field `years.default_range`; client helper `reportYearsInitialRange` | The browser reads no clock (`.ai/rules/app-day.md`). |
| Test scope | Feature on PostgreSQL at 31.12/31.01 22:59:59 and 23:00:00 UTC, Vitest, kitchen sink, `ReportAmountInvariantTest` case | A hand-counted literal per reading, as the reports rule asks for a new boundary; no E2E. |

## Scope

**In scope:**
- ADR-0108, glossary entry Years, notes in ADR-0097 and ADR-0101
- `ReportYearsRange::opensOnSameDay`, `default_range` in the `years` prop (PHP + TS), panel mode, `ReportYearsView` default
- `.ai/rules/reports.md`, fixtures and scenarios `years-january`, `panel-years-january`
- Year-over-year tests on 1 January (range, amounts, page, invariant)

**Out of scope:**
- Any change to the year-over-year comparison or its note
- A month-end cut, a hint banner, a mode switch in the panel
- E2E in January, session year (S-40), year-turn notices (S-41)

## Architecture / Approach

One predicate in `ReportYearsRange` („the cut month is after January”) becomes `default_range` in `ReportYears::toArray`; `ReportCategoryPanel` reads the same field for its mode, and `ReportYearsView` starts its local range from it through a pure helper. `same_day` is still computed in January, so „Do D” stays one click away. Docs land first; the year-over-year proof is a separate test-only phase.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decision in the documents | ADR-0108, glossary, ADR-0097 note | ADR number collides with parallel S-40/S-41 (merge-second renames) |
| 2. Full Years by default in January | Server field, panel mode, page default, rule, kitchen sink, tests | Prop changed on one side only; fixtures missing the new field |
| 3. Year over year on 1 January: proof | Range, amount, page and invariant tests | Tests pinned to 00:00 UTC instead of Polish midnight |

**Prerequisites:** S-39 (`AppDay`, ADR-0103) merged; test database `testing_years_report_in_early_january` created.
**Estimated effort:** ~1–2 sessions across 3 phases.

## Open Risks & Assumptions

- In January the panel's N−1 bar (whole year) differs from its N−1 tile (year over year to the same day); accepted and recorded in ADR-0108.
- The main checkout holds an uncommitted roadmap edit (S-43 `ready`); this branch moves S-43 to `planning`, so the merge needs both sides kept.

## Success Criteria (Summary)

- On 2 January the user opens „Lata” and sees full years with a sensible „Zmiana 2020–2026”, with „Do 2 stycznia” one click away.
- The panel's „Lata” tab in January reads „Pełne lata”; from 1 February everything is as today.
- The year-over-year result on 1 January is pinned by tests at Polish midnight with hand-counted amounts.
