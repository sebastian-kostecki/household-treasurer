<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Zakładka „Lata” w panelu kategorii

- **Plan**: context/changes/report-panel-years-tab/plan.md
- **Mode**: Deep
- **Date**: 2026-11-14
- **Verdict**: REVISE → SOUND po poprawkach
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
22/22 paths ✓, 6/6 symbols ✓ (`hasOperations`, `forYear`, `requestedCategoryId`, `reportYearSwitchParams`, `panel_highest`, `panel_months_label`), ADR-0101 free ✓, brief↔plan ✓, Progress↔Phase ✓

Zweryfikowane w kodzie: reguła `opens_panel` jest równoważna wierszom `PeriodReport::forYear` (wiersz tylko z operacji po `budget_line_id`, daty spoza roku Budżetu odpadają po obu stronach, T − 1 porównania zawsze jest kolumną `ReportYearsRange`, 29 lutego zgodny: `subYearNoOverflow` ↔ `min(day, daysInMonth)`).

## Findings

### F1 — W Roku przeszłym słupki lat przeczą kafelkom panelu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1 §3 (mode), What We're NOT Doing (pkt 1)
- **Detail**: Panel brał lata zawsze z `same_day`. W Roku 2024 (zakładka przeżywa zmianę roku w sidebarze) kafelki liczą cały 2024 i cały 2023 (`ReportComparisonPeriod.php:51-53` ucina tylko rok w toku), a słupki 2024 i 2023 są ucięte do dnia D: dwie różne kwoty tego samego roku w jednym panelu.
- **Fix A ⭐ Recommended**: tryb `same_day` tylko w Roku bieżącym, w innym roku `full`.
  - Strength: kafelki i słupki oglądanego roku i N − 1 zawsze równe; link z „Lat” trafia do T, więc jego ścieżka bez zmian.
  - Tradeoff: tryb zależny od roku, scenariusz `panel-years-past-year`, zdanie ADR-0101.
  - Confidence: HIGH — oba tryby są w jednym `ReportYears`.
  - Blind spot: karta Atramentu może zakładać „Do D” niezależnie od roku.
- **Fix B**: zostawić „zawsze Do D” i zapisać rozbieżność w ADR-0101 jako przyjęty koszt.
  - Strength: jedna reguła, słupki zawsze równe domyślnej tabeli „Lat”.
  - Tradeoff: dwie kwoty tego samego roku w panelu.
  - Confidence: MED.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A). Skutek uboczny: przy trybie z oglądanego roku każda kategoria panelu ma wiersz, więc `years: null`, notatka `years-none`, klucze `panel_years_none_*` i scenariusz `panel-years-none` wypadły; zamiast nich test niezmiennika, `LogicException` przy braku wiersza i scenariusz `panel-years-past-year`. Brief zaktualizowany.

### F2 — Nowe klucze psują istniejące asercje bez ->etc()

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §7
- **Detail**: `ReportsPageTest.php:768-797` sprawdza wpisy „Lat” w domkniętych zakresach `->has(…, fn (Assert))` bez `->etc()`; `opens_panel` je wywali, a plan nie mówi, jak je poprawić.
- **Fix**: dopisać `->where('opens_panel', …)` w istniejących zakresach (zmiana wymagania, nie `->etc()`).
- **Decision**: FIXED

### F3 — Argument `$sameDay` dla członu T−1 flagi nie jest nazwany

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §2
- **Detail**: Liczona w pętli trybu z `$sameDay` trybu `full` flaga objęłaby cały T − 1 i dała link do kategorii, którą `requestedCategoryId` odrzuci.
- **Fix**: oba człony `hasOperations(…, true)` niezależnie od trybu, liczone raz poza pętlą trybów.
- **Decision**: FIXED

### F4 — `<Link method="post">` zachowuje stan, przełącznik roku nie

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §1
- **Detail**: Vue `<Link>` dla metod innych niż GET domyślnie wysyła `preserveState: true`, a `BudgetYearSwitcher` robi `router.post` bez niego; refy `Reports.vue` (`sheetOpen`, `closingSheetCategory`) przeżyłyby przejście z „Lat” do Roku.
- **Fix**: `:preserve-state="false"` obok `:preserve-scroll="false"`.
- **Decision**: FIXED
