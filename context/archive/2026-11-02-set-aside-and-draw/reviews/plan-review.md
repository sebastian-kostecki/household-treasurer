<!-- PLAN-REVIEW-REPORT -->
# Plan Review: S-02: Odkładam / dobieram

- **Plan**: context/changes/set-aside-and-draw/plan.md
- **Mode**: Deep
- **Date**: 2026-11-02
- **Verdict**: SOUND
- **Findings**: 0 critical, 2 warnings, 0 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | PASS |

## Grounding
24/24 paths ✓, 9/9 symbols ✓, brief↔plan ✓, Progress↔Phase ✓. Risky claims verified inline: the set-aside columns arrive in the already loaded `monthPlans` (query count unchanged), each calculator copy has one caller, `MonthPlanReading` is built only by `MonthPlanProposal`, `restoreMonthPlans` passes an explicit field list, and `<Form>` starts right under the "Do wydania" card.

## Findings

### F1 — Funkcja tylko w TS trafia do pliku pary PHP/TS

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 2, sekcja 2 „Warunek ostrzeżenia o Draw”
- **Detail**: `AGENTS.md:32` opisuje `to-spend-calculator.ts` jako kopię 1:1 `ToSpendCalculator.php` („Change both copies and both tests together”). `drawExceedsSpendableLiquidity` nie ma odpowiednika w PHP, więc w tym pliku zamazuje regułę pary. W repo ostrzeżenia klienta leżą w osobnych plikach (`hand-entry-warning-preview.ts`).
- **Fix**: Przenieść funkcję do `resources/js/lib/draw-warning.ts` z `draw-warning.test.ts` obok, zmienić sekcję 2 i kryterium `npm test`.
- **Decision**: FIXED

### F2 — Brak blokady przy Draw testowany na złej warstwie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2, Success Criteria 2.3 / 2.4
- **Detail**: Kryterium 2.3 sprawdzało „Draw ponad płynność przyjęty” i „salda bez zmian” w `ApproveMonthPlanTest`, który woła akcję bezpośrednio (`ApproveMonthPlanTest.php:175-183`). Akcja nie dostaje płynności, więc asercja przechodzi zawsze. Blokada mogłaby się pojawić w `ApproveMonthPlanRequest` albo w kontrolerze, a test przez trasę nie ustawiał kont z sumą < |Draw| i nie sprawdzał sald. To jest ryzyko, które roadmapa wskazuje dla S-02.
- **Fix**: W 2.4 test przez trasę: konta z sumą sald < 30 000, POST z −30000 → przekierowanie bez błędów, −30 000 w obu kolumnach, salda bez zmian. W 2.3 zostaje tylko zapis obu kolumn.
- **Decision**: FIXED
