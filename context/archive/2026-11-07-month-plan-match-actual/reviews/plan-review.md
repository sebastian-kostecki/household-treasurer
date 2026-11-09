<!-- PLAN-REVIEW-REPORT -->
# Plan Review: month-plan-match-actual — plan implementacji

- **Plan**: context/changes/month-plan-match-actual/plan.md
- **Mode**: Deep (weryfikacja kodu inline, bez sub-agenta)
- **Date**: 2026-11-07
- **Verdict**: SOUND
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
16/16 paths ✓, symbols ✓, brief↔plan ✓, Progress↔Phases ✓. No lessons.md or contract-surfaces.md.

## Findings

### F1 — Kitchen sink pokaże podniesiony wiersz ze starym odchyleniem

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3, pkt 6 (Kitchen sink)
- **Detail**: `stateOf()` w `MonthPlanSink.vue:199-223` bierze `deviation`, `overPlan` i `status` z wiersza serwera, tylko `planMinorUnits` z pola. `overPlanExpense` z polem = wydatki i znacznikiem pokaże plakietkę „ponad plan” i odchylenie −110 zł, choć plan mówi, że po podniesieniu odchylenie → 0 i plakietka znika.
- **Fix**: Dla nowego `tableConfig` liczyć w `stateOf` odchylenie, `overPlan` i `status` z kwoty w polu (jak `rowReading` na stronie) albo dać fixturze wiersze spójne z podniesieniem.
- **Decision**: FIXED (Fix in plan)

### F2 — „offer” nie ma górnego limitu 1e12

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2, pkt 1 (`matchActualState`)
- **Detail**: `raisesToActualOnChange` odrzuca `actual > MAX_PLAN_AMOUNT_MINOR_UNITS`, a `offer` nie. Przycisk wpisałby kwotę odrzucaną przez `parsePlanAmountInput` → pole nieprawidłowe, „Zapisz zmiany” zablokowane (`MonthPlan.vue:585`). Test MAX jest tylko dla podniesienia.
- **Fix**: `offer` wymaga `actual ≤ MAX`; dopisać test i zdanie do ADR-0062.
- **Decision**: FIXED (Fix in plan)

### F3 — Automat podnosi też zarchiwizowane pozycje w planie

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2, `raisesToActualOnChange`
- **Detail**: `isEditableRow` = `in_plan || !is_archived`, więc zarchiwizowana kategoria z planem i wydatkami ponad plan też startuje podniesiona. `DashboardNotices.php` pomija zarchiwizowane, więc zysku nie ma, a plan cicho się zmienia przy zapisie.
- **Fix**: Automat pomija `is_archived` (przycisk zostaje), test w liście.
- **Decision**: FIXED (Fix in plan)

### F4 — Reguła ścieżkowa `js-month-plan.md` nie jest w planie

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3, pkt 7 (Design system)
- **Detail**: Trwałe konwencje trafiają do `.ai/rules/`. Plan aktualizuje `design-system.md`, ale nie `.ai/rules/js-month-plan.md` (komórki „Zatwierdzony”/„Faktycznie”, lista komponentów, kitchen sink).
- **Fix**: Dopisać akapit do reguły i uruchomić `php artisan boost:index-rules`.
- **Decision**: FIXED (Fix in plan)
