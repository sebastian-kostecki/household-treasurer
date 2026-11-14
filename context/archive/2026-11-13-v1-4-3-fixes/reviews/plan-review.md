<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Poprawki po 1.4.3: ciemny motyw, pickery i Plan miesiąca

- **Plan**: context/changes/v1-4-3-fixes/plan.md
- **Mode**: Deep
- **Date**: 2026-11-13
- **Verdict**: REVISE → SOUND po poprawkach
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

41/41 paths ✓, 10/10 symbols ✓ (`CategoryListOrder::tree()` at :55, not :46), brief↔plan ✓, Progress↔Phase ✓. Tile contrast 3:1 is feasible for 24 colours × 2 themes when mixed in srgb (e.g. light: tile 15% / icon 70% → worst 3.14:1; dark: 15% / 90% → 3.02:1). The red baseline matches the plan (1.74:1 `#eab308` light, 2.70:1 `#be185d` dark).

## Findings

### F1 — Separator nie obejmie startu trybu zmian

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 4, §5 (P3)
- **Detail**: Tryb zmian startuje z `changeModeAmountInputs` (`MonthPlan.vue:157` → `lib/month-plan-match-actual.ts:31`), którego lista planu nie wymieniała, więc krok 4.8 by nie przeszedł. Na liście `:121` to pole odkładania, a `:199` niewidoczny fallback parsera. Brakowało też linii sinka `:219`, `:236`, `:520`. Wykrywanie zmian porównuje grosze, nie teksty, więc separator go nie psuje.
- **Fix**: Zastąpić listę wywołań: `month-plan-match-actual.ts:31`, `MonthPlan.vue:104, :121, :171`, `MonthPlanSink.vue:155, :219, :236, :520`; `:199` zostaje.
- **Decision**: FIXED (Fix in plan)

### F2 — Hidden input AccountSelect zmienia wysyłkę formularzy lokat

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2, §2 („Wysyłane wartości i nazwy pól bez zmian”)
- **Detail**: `AccountSelect` wysyła wartość przez `<input type="hidden">` (`AccountSelect.vue:123-129`). Przeglądarka nie sprawdza `required` na takim polu, a wyłączony wiersz importu wysyła `rows[i][term_deposit_id]=''` zamiast pominąć pole. `StoreCsvImportRequest.php:28,54` to przyjmuje, ale twierdzenie planu było nieprawdziwe i nic tego nie testowało.
- **Fix**: Poprawić zdanie kontraktu i dodać asercje wysyłki: ruch kapitału bez lokaty → błąd pola, import z pustym `term_deposit_id` zapisuje się jak dziś.
- **Decision**: FIXED (Fix in plan; new Progress step 2.5, manual steps renumbered 2.6–2.9)

### F3 — Kolejność filtra: istniejący czytnik drzewa pominięty

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 3, §2
- **Detail**: Wejście `CategoryListOrder::tree()` buduje już `ReportCategoryTree::ofType` (`app/Support/ReportCategoryTree.php:29`). `BudgetLine::$with` ładuje tylko `category`, więc bez `with('category.group')` Sloppy złapie N+1.
- **Fix**: Wskazać `ReportCategoryTree::ofType` i eager loading `category.group` oraz grupy wybranej kategorii.
- **Decision**: FIXED (Fix in plan)

### F4 — Pierwszeństwo „zarchiwizowany” nad „overPlan” nie jest jawne

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 4, §3 (tabela tonu)
- **Detail**: Dziś `deviationClass` ignoruje `is_archived`, więc zarchiwizowana, przekroczona linia jest czerwona. Tabela planu nie mówiła, że czyta się ją z góry na dół.
- **Fix**: Dopisać „pierwszy pasujący przypadek wygrywa” i test „zarchiwizowany + overPlan → bez tonu”.
- **Decision**: FIXED (Fix in plan; plan order kept, change goes into ADR-0092)

### F5 — CategorySelect ma więcej konsumentów niż sprawdza krok 3.8

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3, Manual 3.8
- **Detail**: `CategorySelect` używają też `CategoryMergeDialog.vue:79` (`lib/category-merge.ts:26` polega na blokach grup), `OperationCategoryDialog.vue:207` i `ImportPreviewRow.vue:606,627`.
- **Fix**: Dopisać te okna do kroku 3.8.
- **Decision**: FIXED (Fix in plan)

### F6 — Nienazwany plik testu i nieaktualne fakty w stanie obecnym

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §5; Current State Analysis; Critical Implementation Details
- **Detail**: „Test Feature strony Operacji” nie istniał (żaden test nie sprawdza propa `termDeposits`). `development` ma 4 commity rhythm-hints, nie 2. Wiersze sum to flex, nie `.month-plan-grid`. Fiksury `overPlanExpense` i `longNameExpense` też mają odwrócony znak. Odwołanie do `tree()` wskazywało :46.
- **Fix**: Wpisać `tests/Feature/CapitalMovementTest.php` i poprawić fakty.
- **Decision**: FIXED (Fix in plan)
