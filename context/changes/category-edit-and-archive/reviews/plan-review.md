<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Category Edit and Archive

- **Plan**: context/changes/category-edit-and-archive/plan.md
- **Mode**: Deep
- **Date**: 2026-11-10
- **Verdict**: REVISE (po poprawkach: SOUND)
- **Findings**: 1 critical, 4 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | FAIL |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
45/45 paths ✓, 5/6 symbols ✓ (rowAmounts ✗ → F1), brief↔plan ✓, Progress↔Phase ✓ (5/5 faz, 26/26 kryteriów)

## Findings

### F1 — Niezapisana BudgetLine w planie miesiąca wywraca odczyty

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Key Discoveries (MonthToSpend::rowAmounts); Phase 5 #1
- **Detail**: `rowAmounts` (`MonthToSpend.php:93-98`) woła `MonthCategoryActuals::actual|hasPositiveAmount|hasOperations(int)` (`MonthCategoryActuals.php:62-75`), więc dla `id = null` jest TypeError. `BudgetLine` nie ma domyślnych atrybutów. `can_change_kind` wyszłoby true, a PATCH rodzaju kończy się 404 w `CategoryController::lineOf()->firstOrFail()`.
- **Fix A ⭐ Recommended**: osobny `joinCandidateRow(Category, flagi)` ze stałymi, bez `rowAmounts`; `assertInertia` porównuje klucze z `itemRow`.
- **Fix B**: `?int` w `MonthCategoryActuals` i `rowAmounts`.
- **Decision**: FIXED (Fix A)

### F2 — Odczyty ostrzeżeń nie biorą danych z opcji

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 4 #5, #6
- **Detail**: `HandEntryMonthRemainingInputs`, `ImportMonthRemainingContext` i `EntryToSpendInputs` same pytają o pozycje, a front po cichu pomija nieznane `category_id`. `findCategory` (`hand-entry-warning-preview.ts:75-83`) szuka po samym id w nieprzefiltrowanych `expenseCategories`.
- **Fix**: 4.5 rozpisane na trzy odczyty; `HandEntryPreviewCategory.budget_year` i dopasowanie po (id, rok) z testem dla dwóch budżetów w wykonaniu.
- **Decision**: FIXED

### F3 — Snapshot v7: v6 trafi na ścieżkę starego formatu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 #3
- **Detail**: `RestoreUserData.php:30` i `:112` porównują wersję z `SNAPSHOT_VERSION` przez równość.
- **Fix**: `in_array($version, [6, SNAPSHOT_VERSION], true)` w obu miejscach.
- **Decision**: FIXED

### F4 — Reguła wyboru nie rozstrzyga wszystkich przypadków

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 #1, #3, #4, #7
- **Detail**: Brak decyzji dla niezmienionej aktywnej kategorii w roku bez pozycji. `JoinBudgetAtFirstUse` mógł zwrócić zarchiwizowaną pozycję. Jeden dzisiejszy komunikat obejmuje i brak budżetu, i brak pozycji.
- **Fix**: tabela decyzji w 4.3 ((a) dołącza), `JoinBudgetAtFirstUse` odmawia dla zarchiwizowanej pozycji, `lockLine` czyta pozycję bez filtra, dwa nowe przypadki testów.
- **Decision**: FIXED

### F5 — Propsy opcji nie mają jednego kształtu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 #2
- **Detail**: Trzy dzisiejsze kształty opcji (Operacje wydatek, Import wydatek, przychód); `CategoryAppearance` przyjmuje `BudgetLine`.
- **Fix**: wspólne wiersze z `CategoryChoiceOptions`, mapowanie per wołający, wejście `Category` w `CategoryAppearance`, `assertInertia`.
- **Decision**: FIXED

### F6 — Druga numeracja sort_order w ReorderListCategories

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 2 #2
- **Detail**: „Od 1, archiwalne bez zmian” różni się od `ReorderCategories::listOrder` (cała lista 0..n-1, sloty archiwalnych).
- **Fix**: wspólny `CategoryListOrder` w `app/Support/CategoryList/` dla obu akcji.
- **Decision**: FIXED
