<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Report Redesign Decisions (F-07)

- **Plan**: context/changes/report-redesign-decisions/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-14
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Evidence

- Automated rows 1.1–1.5, 2.1–2.4, 3.1–3.2, 3.4 re-run on HEAD (46234da): all pass (ADR-0097 exists; 7 amended ADRs end with `Amended by ADR-0097:` and lost 0 lines — numstat `2 0` each; shared phrases present in ADR and glossary; 3 glossary paragraphs; old prohibitions gone; no change under `resources app tests routes database .ai`).
- Row 3.3 re-checked against the live Atrament artifact (version 1794655136-60cb): `project/tokens.json` has `chart-1` (`#0b6f8a` / `#2fb4d4`) and `chart-2` (`#7a8797` / `#8a97a8`), equal to `resources/css/app.css`; `project/README.md` no longer contains "Design system nie ma tokenów wykresu"; `bundle.css` paints `ht-share__fill`, `ht-colchart`, `ht-spark` with `var(--chart-1)` and takes colours from `tokens.json`; both `preview.html` use only `ht-*` classes (no `brand-text` fill).
- Cited clauses ADR-0070:11/:19, 0071:13/15/17/19/23/29, 0074:7/11/13/15/23/25, 0077:9/11, 0079:5, 0081:17, 0084:5/7, 0093:9 read at the cited lines: every quote matches.
- Numeric examples recomputed: (74 000−42 000)/42 000 = +76,2%; (74 000/42 000)^(1/5)−1 = +12,0%; 68 240/35 980 = +89,7%; ^(1/6)−1 = +11,3%. Contrast ratios in `design-system.md` recomputed from `app.css`: `foreground` on `card` 17,28:1 light / 14,70:1 dark, `chart-1` 5,75 / 7,16, `chart-2` 3,66 / 5,89, `success` 6,52 / 9,99 — all as written.
- No remaining "never by amount", "four segments", "bar chart" claims in `context/foundation/**` outside the amended ADRs (grep).
- Manual rows 1.6, 1.7, 2.5, 2.6, 3.5, 3.6 are unchecked in Progress: pending the user, not falsely ticked. Numbers (1.7) and contrasts are covered by the recomputation above; 3.5 (both themes visually) still needs a human.
- Reviewed directly, without subagents: a documents-only change of 15 files with the full diff in context.

## Findings

### F1 — „Średnio rocznie” for „Razem” has no defined first year

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/adr/0097-report-years-ranking-and-chart-colours.md (paragraph „Statistics.”), glossary.md `Years (ADR-0097)`
- **Detail**: Change since First Year and Fastest Growing start from „the first year with an amount” (first column above 0), but Average Yearly Growth of „Razem” uses „start” and „first year” without that definition and is „—” for „a start that is not positive”. That "—" can only happen if start is the first column, so on the „Przychody” tab with Razem 2020 = 0 and 2021+ positive, the Razem change cell has a value while „Średnio rocznie” shows „—”. The plan's goal was that S-34 needs no new domain decision.
- **Fix**: Add one sentence to „Statistics.”: for „Razem”, start is the Razem of the first year with an amount above 0 (the Change definition), „—” when that year is the final year or there is none.
- **Decision**: FIXED — ADR-0097 „Statistics.”: start of „Razem” is the first year with an amount above 0, „—” when none, when it is the final year or when the final amount is negative

### F2 — Atrament READMEs still say the view has no roadmap slice and no ADR

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: Atrament artifact — `project/README.md` („Aktualizacja 2026-11-14”), `project/components/PageReportsYears/README.md` („Stan”), `project/components/PageReports/README.md` („Stan”)
- **Detail**: Phase 3 replaced only the sentences the plan named. Left behind: „nie ma go jeszcze w roadmapie ani w PRD, więc przed `/10x-ui` potrzebuje slice’a, ADR i decyzji w słowniku” (project README), „Nie ma go w roadmapie ani w PRD. Przed kodem: slice …, ADR …, decyzja w `glossary.md`” (PageReportsYears) and „czeka na `/10x-ui` i ADR” (PageReports). After F-07 there are slices S-34/S-35/S-36, ADR-0097 and a glossary entry, so the shared design system tells the next agent to redo decided work.
- **Fix**: Rewrite those three „Stan” sentences to „slice S-34 / S-35 / S-36 w roadmapie, decyzje w ADR-0097 i słowniku; czeka na kod i `/10x-ui`”, read-before-publish to the same artifact `url`, never `force`.
- **Decision**: FIXED — three „Stan” sentences rewritten and published to the Atrament artifact (version 24, 1794655713-cac1); tokens.json untouched

### F3 — Examples say „2026 do 14.11” while the rule says the current year is not cut

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/adr/0097-report-years-ranking-and-chart-colours.md („Full Years.” example)
- **Detail**: „Same-day Cut” keeps the current year whole (it includes operations dated after today, equal to the year report), but the example reads „2026 to 14.11 = 68 240”. The numbers agree only when no operation is dated after 14.11. The plan turns these examples into S-34 test cases, so the fixture should say so.
- **Fix**: Word it „2026 (whole year, no operation dated after 14.11) = 68 240”.
- **Decision**: FIXED — example now reads „2026 (the whole year, no operation dated after 14.11) = 68 240”

### F4 — „Rules to change with the code” lists a sentence that `.ai/rules/reports.md` does not contain

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: context/foundation/adr/0097-report-years-ranking-and-chart-colours.md („Rules to change with the code.”)
- **Detail**: The ADR names „the reading that the report never sorts by amount” as a sentence S-34/S-35 must rewrite. `grep -ci 'sort\|by amount' .ai/rules/reports.md` returns 0; the claim lives only in ADR-0070:11 and the glossary. The other items (`ReportBarChartPlaceholder`, „only here” for whole złoty, the switch list at :36, `aria-hidden` rule at :20) do exist.
- **Fix**: Drop that clause from the list.
- **Decision**: FIXED — clause dropped from „Rules to change with the code”
