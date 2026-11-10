<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Grupy kategorii — plan implementacji

- **Plan**: context/changes/category-groups/plan.md
- **Mode**: Deep
- **Date**: 2026-11-10
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 0 critical · 3 warnings · 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | PASS |

## Grounding
35/35 paths ✓, 7/7 symbols ✓ (ReorderCategories:22-88, scopeOrdered:112-123, RestoreUserData :30/:42/:90/:100/:104/:108/:112, CreateCategory:103, UpdateCategory:70-76), brief↔plan ✓, Progress↔Faza ✓ (5/5 faz, 22/22 kryteriów)

## Findings

### F1 — Grupa tylko z archiwalnymi pozycjami łamie „grupy = Razem”

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: End-State Alignment
- **Location**: Desired End State; Faza 4 §1 i §5
- **Detail**: Przy `show_archived=1` „Razem” liczy archiwalne pozycje (`BudgetController.php:57-65`), a grupa bez aktywnej pozycji nie ma wiersza, więc jej archiwalne kwoty nie trafiają ani do wiersza grupy, ani do „bez grupy”. Obiecany niezmiennik „grupy + bez grupy = Razem” i test Fazy 4 §5 nie mogły przejść.
- **Fix A**: Wiersz grupy, gdy grupa ma pozycję w kolekcji sumowanej do „Razem”
  - Strength: Zostaje decyzja „suma grupy idzie za Razem”.
  - Tradeoff: Wiersze grup bez widocznych kategorii; payload przeciągania różni się od widoku.
  - Confidence: MED — dokłada stan do drzewa i payloadu.
  - Blind spot: Wygląd pustego wiersza.
- **Fix B ⭐ Recommended**: Suma grupy tylko z aktywnych pozycji grupy; archiwalne w sekcji archiwum
  - Strength: Suma grupy = suma widocznych wierszy, niezależna od przełącznika; grupy + bez grupy + archiwum = „Razem”.
  - Tradeoff: Zmienia decyzję planu i ADR-0069.
  - Confidence: MED — decyzja produktowa.
  - Blind spot: Definicja sumy grupy w S-25 i S-28.
- **Decision**: FIXED (Fix B) — Desired End State, Key Discoveries, What We're NOT Doing, kontrakt ADR-0069, Faza 4 §1 i §5, Testing Strategy, brief

### F2 — Założenie o czystym ciągu sort_order jest fałszywe; brak reguły remisu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 §2, §4, §5; Faza 3 §3
- **Detail**: `UpdateCategory.php:44-50` nie przenumerowuje przy zmianie typu, `DemoHouseholdSeeder.php:267` numeruje oba typy razem, a `createListCategory` liczy max bez blokady. Mogą być dziury i remisy, a plan nie określał rozstrzygania remisu w `CategoryListOrder` i `buildCategoryTree`.
- **Fix**: Jedna reguła remisu (`sort_order` → grupa przed kategorią → `name_key` → `id`) w PHP i TS z testem, poprawione zdanie o migracji, max pod `lockForUpdate`.
- **Decision**: FIXED

### F3 — Nowe komponenty poza strażą check:ui; kitchen sink Budżetu wysyła PATCH

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §5, Faza 4 §4
- **Detail**: `scripts/check-ui-literals.mjs:96-108` skanuje stałą listę `FILES`; nowe komponenty, `ui-contract.test.ts:16` i `CategoriesHardcodedUiTest.php` ich nie obejmą. `BudgetKitchenSink.vue:279-414` renderuje `BudgetCategoryList` 8 razy z `:can-reorder="true"`; fixtury potrzebują propsów grup.
- **Fix**: Dopisać te pliki i fixtury (`categories-fixtures.ts`, BudgetKitchenSink) do Faz 3 i 4.
- **Decision**: FIXED

### F4 — Kryteria 1.3 i 3.1 bez uruchamialnych komend

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §6 i 1.3; Faza 3 §6 i 3.1
- **Detail**: Brak ścieżek; plan wskazywał nieistniejący `CategoryListPageTest.php`. Istniejące testy: `CategoryListBudgetPageTest.php:194,218,254`, `BudgetTest.php:689`, `CategoriesPageTest.php`.
- **Fix**: Konkretne pliki i komendy w §6 i kryteriach.
- **Decision**: FIXED
