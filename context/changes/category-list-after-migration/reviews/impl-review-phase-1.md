<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Category List After Migration

- **Plan**: context/changes/category-list-after-migration/plan.md
- **Scope**: Phase 1 of 7
- **Reviewed phases**: 1
- **Date**: 2026-11-09
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Findings

### F1 — Baseline Sloppy przegenerowany hurtowo

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .sloppy-baseline.json
- **Detail**: `sloppy baseline --force` przemianował trzy wpisy SL204 (Category→BudgetLine) i usunął legalnie `BudgetController::show`, ale zalegalizował też wcześniejszy dryf metryk (`DashboardNotices::forDashboard` 17→32 złożoność, `RestoreUserData` 424→503 linii itd.). `sloppy ci` jest czysty, brak nowych ukrytych znalezisk.
- **Fix**: Zostawić (dryf pochodzi z wcześniejszych commitów); przy kolejnych fazach nie odświeżać baseline'u hurtowo.
- **Decision**: SKIPPED

### F2 — Edycja pozycji rozjeżdża się z listą (do Fazy 3)

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW
- **Dimension**: Plan Adherence
- **Location**: app/Http/Controllers/CategoryController.php:77-100
- **Detail**: `update()` pisze nazwę/typ/ikonę/kolor tylko na `BudgetLine`. Plan wprost akceptuje ten rozjazd do Fazy 3 (tylko dev; produkcja jednym wdrożeniem). Konsekwencja: `down()` backfillu nadpisze nazwy z listy. Nie wdrażać Fazy 1 samodzielnie.
- **Fix**: Brak — zamyka Faza 3.
- **Decision**: SKIPPED

### F3 — Backup v5 przelicza listę z nazw pozycji

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW
- **Dimension**: Plan Adherence
- **Location**: app/Actions/BackupUserData.php:49-60, app/Actions/RestoreUserData.php:236-299
- **Detail**: Do czasu snapshotu v6 (Faza 2) round trip nie niesie listy; archiwum na poziomie listy i nazwa listy różna od pozycji giną. Zgodne z planem (restore v1–v5 liczy listę konwersją).
- **Fix**: Brak — zamyka Faza 2.
- **Decision**: SKIPPED

### F4 — Drobiazgi w CategoryController::store

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/CategoryController.php:46-67
- **Detail**: `firstOrCreate` bez obsługi `UniqueConstraintViolationException` (równoległe POST-y → 500; aplikacja jednoosobowa); zwraca też zarchiwizowaną kategorię listy (reguły archiwum dochodzą w Fazie 3); `name_key` ustawia tylko hook `saving`.
- **Fix**: Zająć się w Fazie 3, gdzie `store` jest przepisywany według ADR-0063.
- **Decision**: SKIPPED

### F5 — Martwe metody CategoryPolicy i porównania nazw do usunięcia

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW
- **Dimension**: Scope Discipline
- **Location**: app/Policies/CategoryPolicy.php; app/Support/MonthPlanProposal.php:198; app/Support/OperationsListQuery.php:144; app/Support/CsvImport/ImportCategorySuggester.php
- **Detail**: `CategoryPolicy::update/archive/restore` nie są jeszcze wołane (kontroler używa `BudgetLine`). Trzy porównania nazw istnieją od przed zmianą; plan usuwa je w Fazach 3–4.
- **Fix**: Brak — Fazy 3–4.
- **Decision**: SKIPPED
