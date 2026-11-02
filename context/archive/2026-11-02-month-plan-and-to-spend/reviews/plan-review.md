<!-- PLAN-REVIEW-REPORT -->
# Plan Review: S-01: Plan miesiąca i liczba „do wydania”

- **Plan**: context/changes/month-plan-and-to-spend/plan.md
- **Mode**: Deep
- **Date**: 2026-11-02
- **Verdict**: REVISE → SOUND (after triage)
- **Findings**: 0 critical, 3 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
27/27 paths ✓, 6/6 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

Claims verified in the code: budgets Y−1 and Y can be in execution at the same time (`StartBudgetExecution.php:14-30`, `BudgetPolicy.php:32`). `planned_amount_minor_units` = Current Plan (`BudgetController.php:231`). `financial_operations.category_id` is not nullable, and the only types are Income/Expense. A category cannot be deleted during execution (`CategoryPolicy.php:17`). No test asserts the full shape of a category row, so adding `month_item_kind` is safe.

## Findings

### F1 — Formularz zatwierdzenia nie odróżnia pozycji planu od wierszy „tylko operacje”

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Faza 2 §6, Faza 3 §1, §2, §5
- **Detail**: Wiersze to pozycje planu ∪ kategorie z operacjami w M, a ApproveMonthPlan wymaga równości zbioru kluczy z kategoriami proponowanymi. MonthPlanItemRow nie miał flagi pozycji planu, więc zatwierdzenie z wierszem zarchiwizowanym z operacją zawsze by się nie powiodło. Brakowało też `distinct` i klucza błędu.
- **Fix**: `in_plan: boolean` w MonthPlanItemRow; formularz wysyła tylko wiersze `in_plan`; `distinct` dla `items.*.category_id`; klucz błędu `items`; przypadki w 3.1.
- **Decision**: FIXED

### F2 — Przełącznik rodzaju i podgląd na żywo: wzorzec klienta bez precedensu i bez kontraktu stanu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Faza 3 §3, §5
- **Detail**: `router.patch` + `only` + `back()` nie ma precedensu w repo (zero użyć `only:`). Podgląd „z bieżących wartości pól” nie działa na polach niekontrolowanych pod `<Form>`.
- **Fix**: Stan kwot według wzorca `ExpenseFormDialog.vue` (`amountInputs` ref + parsowanie + ukryte pola + computed podgląd); usunięte `only: ['monthPlan']`.
  - Strength: sprawdzony wzorzec z repo; usuwa niesprawdzoną opcję Inertii.
  - Tradeoff: pełny reload propsów strony.
  - Confidence: MED — preserveState przy nie-GET jest standardem Inertii, ale w repo nieużywany.
  - Blind spot: zachowanie refów przy zmianie liczby wierszy po reloadzie.
- **Decision**: FIXED

### F3 — Faza 4 wpisuje do PRD „restore przyjmuje 1–4”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 4 §3
- **Detail**: Kontrakt FR-ADM-01 był sprzeczny z Desired End State (1–5).
- **Fix**: „restore przyjmuje 1–5”.
- **Decision**: FIXED

### F4 — Rekursja stycznia poza budżet Y−1 nierozstrzygnięta

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Reguła propozycji (M = 1), Faza 2 §4
- **Detail**: Przy budżecie Y+1 w wykonaniu łańcuch może dojść do stycznia B₋₁, a B₋₂ nie jest ładowany.
- **Fix**: Styczeń w B₋₁ nie ma źródła; przypadek testu w MonthPlanProposalTest.
- **Decision**: FIXED

### F5 — Zmiana rodzaju przelicza wstecz „do wydania” minionych planów zatwierdzonych

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §2 (`can_change_kind`)
- **Detail**: Rodzaj jest cechą kategorii, a przełącznik był dostępny także na minionych miesiącach.
- **Fix**: `can_change_kind` tylko dla miesiąca bieżącego/przyszłego; wpis w Key Discoveries.
- **Decision**: FIXED

### F6 — Nawigacja roku i paska miesięcy na nowej stronie

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 3 §1, §5
- **Detail**: `selectYear` zawsze przekierowuje na Budżet; `BudgetMonthStrip` nie ma własnych linków („jeśli przekazuje trasę” było niejasne).
- **Fix**: Zapisane jako świadoma decyzja S-01 w Key Discoveries; `BudgetMonthStrip` usunięty z listy plików.
- **Decision**: FIXED
