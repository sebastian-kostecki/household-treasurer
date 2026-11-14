<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Report Redesign Decisions (F-07)

- **Plan**: context/changes/report-redesign-decisions/plan.md
- **Mode**: Deep
- **Date**: 2026-11-14
- **Verdict**: REVISE → SOUND after fixes
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
9/9 paths ✓, 13/13 cited ADR lines ✓, 5/5 code symbols ✓, Progress↔Phase 19/19 ✓, brief↔plan ✓

## Findings

### F1 — Cięcie bieżącego roku do dziś przeczy równości z raportem roku

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Key Decisions — Same-day Cut, „Równości”, decyzja 4; Integration Tests
- **Detail**: Plan cut the current year in „Lata” to today and promised equality with the year report; ADR-0074:7: „The current column is not cut … also with operations dated after today”.
- **Fix A ⭐ Recommended**: current year not cut, earlier years cut to D.
- **Fix B**: current year cut, equality reworded, ADR-0074:7 amended.
- **Decision**: FIXED (Fix A)

### F2 — Nowe znaki wykresu bez decyzji o bibliotece i dostępnej formie

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Architectural Fitness
- **Location**: Key Decisions — Sparkline, Ranking, Category Panel; ADR-0071 clauses
- **Detail**: ADR-0071:13 (Unovis), :17 (chart never carries data the table lacks), :29 (CSS bars rejected) untouched; panel month bars had no accessible numbers.
- **Fix A ⭐ Recommended**: CSS/SVG marks without Unovis, `sr-only` list of 12 months in the panel, ADR-0071:13/17/29 amended.
- **Fix B**: Unovis for every mark, a month table in the panel.
- **Decision**: FIXED (Fix A)

### F3 — Sprzeczności poza listą zmienianych dokumentów

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 1, Phase 2, Phase 3 §1
- **Detail**: ADR-0079:5 (reports in list order), design-system.md:72 (whole złoty only in Trend), design-system.md:154 (table in the same order), glossary.md:174 (Trend in the order of the year report).
- **Fix**: ADR-0079 added to the amended ADRs and checks; design-system :72/:154 in Phase 3; Trend sentence in Phase 2.
- **Decision**: FIXED

### F4 — Co robi nazwa kategorii w „Latach” (wejście S-36)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Key Decisions 3 and 5; ADR „Navigation.”
- **Detail**: Row-name behaviour in „Lata” undecided while S-36 needs it as its entry point.
- **Fix**: S-34 plain text; S-36 links to the current-year report with the panel open on „Lata”.
- **Decision**: FIXED

### F5 — Nierozstrzygnięte przypadki brzegowe statystyk i panelu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Key Decisions — Change since First Year, Fastest Growing, Full Years, Category Panel
- **Detail**: 0 cell as „first amount”, January average ÷0, all-zero „Najwięcej”, panel without expenses, Full Years without a full year, statistics per tab.
- **Fix**: „Przypadki brzegowe” decision added and listed as test cases.
- **Decision**: FIXED

### F6 — Dwie kontrole fazy 3 nie mierzą kontraktu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §1, checks 3.1 and 3.2
- **Detail**: `track` row already exists (design-system.md:161); 3.2 counted exactly 2 while S-35 Unknown is also closed.
- **Fix**: existing `track` row changed, checks 3.1/3.2 rewritten, contrast note for the `track` dash.
- **Decision**: FIXED

### F7 — Ranking nie może sortować w `ReportCategoryTree`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Key Decisions — Ranking
- **Detail**: `ReportCategoryTree` feeds PeriodReport, ReportTrend and OperationsFilterOptions (ADR-0093:9).
- **Fix**: one sentence: the Ranking sorts the report result, the reader stays in list order.
- **Decision**: FIXED
