<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Raport okresu po grupach (S-28)

- **Plan**: context/changes/period-report-by-group/plan.md
- **Mode**: Deep
- **Date**: 2026-11-10
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 1 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding

30/30 paths ✓ (+1 nowy plik testu wskazany błędnie), 7/8 symbols ✓ (`ht-row--archived` ✗), brief↔plan ✓, ADR-0070/0071 wolne we wszystkich gałęziach ✓.

Potwierdzone bez ustaleń:
- `budget_line_id` jest NOT NULL i wskazuje budżet operacji. Do budżetu w przygotowaniu nie da się zaksięgować operacji, więc `report: null` niczego nie ukrywa.
- Import CSV idzie przez `ManageFinancialOperation`.
- Typ operacji jest równy typowi kategorii (`OperationCategoryChoice`, `CategoryTypeChange`). Grupa nie miesza typów.
- `(budget_id, category_id)` jest unikalne na `budget_lines`.
- `MonthSummary::$realSetAside` = `BudgetMonthResult`.
- Formuła udziału daje przykłady z planu.

## Findings

### F1 — Grupa nfr nie przejdzie: NfrPerf03 ma ten sam błąd

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §6, kryterium 2.2
- **Detail**: Kryterium 2.2 uruchamia całe `--group=nfr`, a plan naprawiał tylko `NfrPerf01Test.php:160`. `NfrPerf03ImportPreviewTest.php:158` też wstawia nieistniejące `category_id`. `assertRequestWithinSeconds` jest prywatne w NfrPerf01 (`:212-225`).
- **Fix**: Naprawa NfrPerf03:158 (`budget_line_id`, wartość bez zmian) i trait `MeasuresRequestTime` w `tests/Feature/Nfr` dla NfrPerf01 i NfrPerfReportTest.
- **Decision**: FIXED

### F2 — Ósmy widok dotyka plików spoza planu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §3
- **Detail**: Plan pomijał:
  - `resources/js/types/shell.ts:17` (ręczny typ `nav`);
  - `UiTranslationKeysTest`;
  - pozostałych konsumentów `useAppViews()`, w tym `LandingAppPreview.vue` na publicznej stronie Welcome i `SidebarKitchenSink.vue`.
- **Fix**: Dopisane pliki. Podgląd na landingu pokazuje „Raporty” jak aplikacja, z podpowiedzią „1–8”, bez zmiany kodu landingu.
- **Decision**: FIXED

### F3 — Nazwy z planu, których nie ma w kodzie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §4, §6, kryteria 2.1 i 2.9
- **Detail**: Plan używał trzech nazw, których nie ma w kodzie:
  - klasy `ht-row--archived`;
  - pliku `tests/Feature/SelectBudgetYearTest.php` (testy są w `BudgetTest.php:268-298`);
  - etykiety „realne odłożenie” (w UI jest „Faktycznie (Wynik)”).
- **Fix**: Zamiany na:
  - `text-muted-foreground` + Badge `neutral` (`BudgetCategoryRow.vue:73,94`);
  - `tests/Feature/BudgetTest.php`;
  - „Faktycznie (Wynik)”.
- **Decision**: FIXED

### F4 — Wejście CategoryListOrder::tree trzeba złożyć ręcznie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §2
- **Detail**: `tree()`:
  - oddaje same id;
  - wymaga `name_key`;
  - nie filtruje archiwum i nie patrzy na typ;
  - nie ma wywołującego, który budowałby wejście z `BudgetLine`.
  Wejście ma `group_id`, a kolumna to `category_group_id`.
- **Fix**: W kontrakcie `PeriodReport` opisane mapowanie wejścia na typ i odrzucanie grup z pustym `category_ids`.
- **Decision**: FIXED
