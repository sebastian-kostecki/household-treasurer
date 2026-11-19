<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Ten sam dzień w menu, na Dashboardzie i w dialogach (S-39)

- **Plan**: context/changes/one-app-day/plan.md
- **Mode**: Deep
- **Date**: 2026-11-19
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 1 critical, 3 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding

20/20 paths ✓, symbols ✓ (20× `today('UTC')`, 15× `before_or_equal:today`, `ShellState`, `RehearsalClock`, `HandleInertiaRequests`), brief↔plan ✓, Progress↔Phase ✓

Potwierdzone przez weryfikację kodu: spis zegarów w `resources/js` jest kompletny i nie ma tam uzasadnionego `Date.now()` (strażnik JS bez wyjątków nie zaczerwieni się na istniejącym kodzie); `shell` dociera do gościa i tras `dev/...`; `ShellState` w TS nie jest nigdzie budowany jako literał; w SQL nie ma porównań `archived_at` z dniem; żaden test nie sprawdza angielskiego komunikatu „before or equal”; reguły walidacji są budowane w metodach, więc `AppDay::today()` da się w nich wywołać.

## Findings

### F1 — Kryterium 1.3 nie może przejść w fazie 1

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 — Success Criteria (1.3) vs Phase 2 §2
- **Detail**: Faza 1 wymagała, żeby `grep -rnE "now\(\)->(year|month|toDateString)" app` nic nie wypisał, a `PreviewBalanceReconciliationRequest.php:47` (`now()->toDateString()`) plan przenosi dopiero w fazie 2.
- **Fix**: Grep w fazie 1 zawężony do `now\(\)->(year|month)`; `now()->toDateString` dopisany do kryterium 2.4.
- **Decision**: FIXED

### F2 — Strażnik PHP złapie każde `AppDay::today()`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §4 — Strażnik zegara w PHP
- **Detail**: Kontrakt zakazywał „`today()` (funkcja i `::today()`)” z wyjątkami tylko na poziomie plików, a po fazie 1 jest ok. 25 wywołań `AppDay::today()` poza listą wyjątków.
- **Fix**: Wzorce zapisane jako regexy (`/(?<!AppDay)::today\(/`, `/(?<![\w:>$])today\(/`, `now()->(year|month|toDateString)`, `before_or_equal:today`); kryterium 2.2 mówi, że strażnik przechodzi na kodzie z `AppDay::today()`. Regexy sprawdzone na obecnym `app/`.
- **Decision**: FIXED

### F3 — Trzecie wywołanie BudgetYearSwitcher bez `current-year`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §4 — Menu roku
- **Detail**: Po usunięciu zapasu `new Date().getFullYear()` (`BudgetYearSwitcher.vue:82`) wywołanie w `dev/SidebarKitchenSink.vue:503-506` przekazałoby `undefined`; plan wymieniał tylko `AppSidebar.vue`.
- **Fix**: `currentYear: number` wymagany, `SidebarKitchenSink.vue` na liście plików, `:current-year="2026"` w `:503`.
- **Decision**: FIXED

### F4 — README dalej każe ustawiać zegar przeglądarki przez `page.clock`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 4 §1 — README „Przełom roku”
- **Detail**: `README.md:318` („Próba przed przełomem”) każe ustawić zegar przeglądarki przez `page.clock`, a kontrakt zostawiał „resztę procedury bez zmian”; ADR-0103 mówi, że przeglądarka idzie za `shell.today` bez `page.clock`.
- **Fix**: Kontrakt usuwa to zdanie; grep w kryterium 4.2 obejmuje `page.clock`.
- **Decision**: FIXED

### F5 — IrregularCollected porównuje `archived_at` pozycji, nie Kategorii

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §4 — Znacznik czasu jako dzień
- **Detail**: `IrregularCollected.php:168` czyta `budget_lines.archived_at` (`:232-236`), nie znacznik Kategorii; test Collected musi ustawić `archived_at` pozycji.
- **Fix**: Kontrakt nazywa kolumny; test archiwizuje przez `ArchiveCategory`, nie przez stan fabryki Kategorii.
- **Decision**: FIXED

### F6 — `now('Europe/Warsaw')->isMonday()` poza spisem

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Current State Analysis — „Pozostałe now()”
- **Detail**: `Backup/HealthyBackupWasFoundNotification.php:23` liczy dzień tygodnia z zegara poza `AppDay`; to harmonogram kopii (ADR-0013), nie decyzja domeny.
- **Fix**: Dopisany jako świadomy wyjątek w Current State i w kontrakcie strażnika.
- **Decision**: FIXED
