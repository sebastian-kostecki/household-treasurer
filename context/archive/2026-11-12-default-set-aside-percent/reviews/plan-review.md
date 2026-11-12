<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Domyślny procent „odkładam” w propozycji planu

- **Plan**: context/changes/default-set-aside-percent/plan.md
- **Mode**: Deep
- **Date**: 2026-11-12
- **Verdict**: REVISE → SOUND (po triage: 4 FIXED)
- **Findings**: 0 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
Grounding: 34/34 paths ✓, 6/6 symbols ✓, brief↔plan ✓

## Findings

### F1 — Odczyt procentu w forBudget łamie budżety zapytań i woła się w pętli

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details / Faza 3 (2. Reguła propozycji)
- **Detail**: Plan zostawia „jednym zapytaniem albo przez relację”. Żaden wywołujący nie ma załadowanego `$budget->user` (`User::budgets()` bez `chaperone()`, `app/Models/User.php:81-84`; Approve ma budżet z wiązania trasy). `EntryToSpendInputs::budgetPayload` woła `forBudget` dla każdego budżetu w `map` (`app/Support/EntryToSpendInputs.php:56-62`, `:86`), więc Operacje i Import dostają N dodatkowych zapytań. `tests/Feature/MonthToSpendTest.php:160` ma `assertSame(9, $queries)`, a `tests/Feature/MonthPlanProposalTest.php:443` ma `≤ 10` przy zapasie 0–1. `MonthToSpendTest` nie jest w poleceniu 3.2, więc błąd wyjdzie dopiero w bramce 4.3.
- **Fix A ⭐ Recommended**: `User::budgets()->chaperone()` i odczyt `$budget->user` w `forBudget`
  - Strength: Sygnatura `forBudget` bez zmian. Budżety z `$user->budgets()` (MonthPlanController:43, DashboardController:174, EntryToSpendInputs:46) mają ownera bez zapytania; Approve robi jedno zapytanie raz.
  - Tradeoff: Niejawna relacja. Budżety z fabryki w testach dalej dociągają usera, więc `MonthToSpendTest:160` (9 → 10) i limit `MonthPlanProposalTest:443` trzeba świadomie podnieść z komentarzem; `MonthToSpendTest` dochodzi do 3.2.
  - Confidence: MED — `chaperone()` jest w Laravel 11.22+; liczby zapytań policzone ręcznie.
  - Blind spot: Inne ścieżki ładujące budżety bez `$user->budgets()`.
- **Fix B**: Procent przekazywany jawnie (`forBudget(..., ?int $percent)`)
  - Strength: Zero ukrytych zapytań, czytelna zależność.
  - Tradeoff: Parametr przez 4 wywołania i `MonthToSpend::forMonth`; plan zakłada niezmienioną sygnaturę.
  - Confidence: HIGH — wszyscy wywołujący znają usera.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F2 — Pole „%” nie ma komponentu z sufiksem

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 2 — 3. Strona i nawigacja
- **Detail**: Plan chce „pole liczby z sufiksem %”, a jedyny sufiks to prop `money` w `resources/js/components/FormField.vue` (`:12`, `:31-33`, `:44-54`) przypięty do `MONEY_UNIT`; `components/ui` nie ma InputGroup.
- **Fix**: Opcjonalny prop `unit` w `FormField` (`money` zostaje przy `MONEY_UNIT`, `.form-field-unit` bez zmian), `FormField.vue` i `.ai/rules/js-dialogs.md` dopisane do fazy 2; alternatywnie „%” w etykiecie.
- **Decision**: FIXED

### F3 — Brak testu migracji, choć projekt ma precedens

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 — 1. Migracja i model / 6. Testy
- **Detail**: `.ai/rules/migrations.md`: „down() either really reverses the migration and has a test”. Podobna migracja S-19, która też tylko dodaje kolumnę, ma `tests/Feature/Migrations/CategoryDescriptionMigrationTest.php`. `unsignedTinyInteger` na PostgreSQL to `smallint` bez unsigned.
- **Fix**: `tests/Feature/Migrations/DefaultSetAsidePercentMigrationTest.php` na wzór S-19 (pgsql, `migrate:rollback --path`, kolumna nullable, istniejący użytkownicy `null`, `down()` usuwa kolumnę), dopisany do polecenia 2.1.
- **Decision**: FIXED

### F4 — Pliki, które faza 2 zmieni, a których plan nie wymienia

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 — 4. i 5.
- **Detail**: `tests/Feature/UserSnapshotCommandTest.php:57,240,1018,1413` (`assertSame(10, $snapshot['version'])` → 11); `resources/js/pages/dev/SettingsKitchenSink.vue:56` (`stateMatrix` wymienia tylko trzy zakładki); `README.md:124` („wersja 9”) → 11; `auth.user` to cały model (`HandleInertiaRequests.php:45`), do sprawdzenia typ TS `User`.
- **Fix**: Dopisać te pliki do kontraktów fazy 2 (pkt 4 i 5).
- **Decision**: FIXED (typ TS `User` ma sygnaturę indeksu, nie wymaga zmiany)
