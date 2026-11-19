<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Niezmienniki kwot — plan implementacji

- **Plan**: context/changes/testing-amount-invariants/plan.md
- **Mode**: Deep
- **Date**: 2026-11-18
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 0 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

- 26/26 paths ✓
- 12/12 symbols ✓
- brief↔plan ✓
- Progress↔Faza ✓: 5/5 faz, 24/24 kryteria
- Liczby z planu przeliczone ręcznie z glosariusza ✓

Potwierdzone w kodzie (bez ustaleń):
- `category_id` to jedyne id w wyjściu `EntryToSpendInputs::forUser`.
- Miesiące propozycji trafiają do wejść, a późniejsze miesiące nie.
- `previewToSpendReadings` nie daje odczytu dla późniejszego miesiąca okresu nieregularnego bez zmiany.
- `ManageFinancialOperation` dołącza kategorię przez `JoinBudgetAtFirstUse`.
- `PeriodReport` i `BudgetMonthResult` liczą zarchiwizowane pozycje.
- `BudgetMonthResult` filtruje tylko po `budget_id`, więc zepsucie tego filtra złapie obcą operację.
- `UserFactory` nie ustawia `default_set_aside_percent`.
- §5, §6.1 i §6.2 planu testów wyglądają tak, jak zakłada faza 5.

## Findings

### F1 — Celowe zepsucie positiveCount nie czerwieni Vitest

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 2 — kryterium 2.4
- **Detail**:
  - `positiveCount` wpływa tylko na przychód i opłatę stałą (`to-spend-calculator.ts:34-48`).
  - Żadna zmiana w czterech scenariuszach nie ma ujemnej kwoty. Edycja daje `remove 900` / `add 1700` dla wydatku bieżącego (`entry-to-spend-warning.ts:449-463`).
  - Zepsucie zostawiłoby wszystkie scenariusze zielone, a gałąź „zwrot przed pierwszą dodatnią operacją” nie ma pokrycia.
- **Fix A ⭐ Recommended**: piąty scenariusz `fixed-charge-refund-first`, w którym zwrot −50 wpada na opłatę stałą bez operacji; odczyt 5 850 → 5 850.
  - Strength: domyka nietestowaną gałąź; ręczny wpis zwrotu jest dozwolony (`notIn([0])`).
  - Tradeoff: jeszcze jeden komplet ręcznie spisanych `inputs`.
  - Confidence: HIGH — mechanizm potwierdzony w kodzie.
  - Blind spot: nie sprawdzono, jak dialog pokazuje odczyt „przed = po” przy zwrocie.
- **Fix B**: zmienić zepsucie na takie, które łapią obecne scenariusze.
  - Strength: bez nowego scenariusza.
  - Tradeoff: ścieżka zwrotu na opłacie stałej zostaje bez dowodu.
  - Confidence: HIGH
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F2 — Porównanie całych wejść zależy od kolejności wierszy

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 — Test Feature, Contract
- **Detail**: `MonthCategoryActuals` grupuje bez `ORDER BY` (`:40`), a `Budget::budgetLines` nie ma `orderBy` (`:50`). Pełna równość z JSON-em na PostgreSQL może być niestabilna.
- **Fix**: kanonizacja (sortowanie po kluczach) przed asercją; fixture spisany w tej samej kolejności.
- **Decision**: FIXED

### F3 — Data zmiany po „dziś” jest nieosiągalna w dialogu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 — scenariusz `fixed-charge-refund-netted`
- **Detail**: zmiana z datą 2026-03-25 przy „dziś” 2026-03-20. FormRequest wymaga `before_or_equal:today` (`ExpenseValidationRules.php:21`).
- **Fix**: data 2026-03-18 i ogólna zasada w Intent fixture'u.
- **Decision**: FIXED

### F4 — Wariant poprawki K2 nie mówi o aktualizacji `inputs`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3.3 — „Mała poprawka”
- **Detail**: poprawka zmienia wyjście serwera, więc test Feature z pełną równością wejść zaczerwieni. Reguła „literał nigdy po uruchomieniu” formalnie zabrania poprawki fixture'u.
- **Fix**: `inputs` poprawiane ręcznie w commicie poprawki jako zmiana wymagania; `readings` i `server_months` bez zmian; adnotacja w `derivation`.
- **Decision**: FIXED

### F5 — Zepsucie `<=` czerwieni tylko marzec, nie kwiecień

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 4 — kryterium 4.2
- **Detail**: 01.05 nie ma żadnej operacji, więc okno kwietnia z `<=` niczego nie dodaje.
- **Fix**: opis kryterium zmieniony na „raport marca”.
- **Decision**: FIXED

### F6 — Ruch kapitału wymaga lokaty

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 4 — scenariusz
- **Detail**: `ManageCapitalMovement::create` wymaga `term_deposit_id` lokaty użytkownika (`:29`).
- **Fix**: `TermDeposit` w zasianiu, wzorzec w `AccountBalanceOnDateTest.php:43-71`.
- **Decision**: FIXED
