<!-- PLAN-REVIEW-REPORT -->
# Plan Review: S-08 Ostrzeżenia przy operacjach liczone z planu

- **Plan**: context/changes/plan-based-entry-warnings/plan.md
- **Mode**: Deep
- **Date**: 2026-11-02
- **Verdict**: SOUND (po poprawkach; przed: SOUND z ukierunkowanymi poprawkami)
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
Grounding: 20/20 paths ✓, 9/9 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

Potwierdzone w kodzie: pełne przeładowanie propsów po zapisie (`to_route('operations')`, bez `only`); propozycja nie czyta operacji (`MonthPlanProposal.php`); `month_item_kind` jak w `MonthPlanController.php:179`; usunięcie annotatora nie zmienia zachowania (nikt nie czyta `default_*` ani `show_*`); `UiTranslations.operations` to `Record<string, string>`; `CsvImportTest` ma testy podglądu z `assertInertia` (klasa PHPUnit).

## Findings

### F1 — Mapowanie stanu formularza na zmiany tylko w testach ręcznych

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 3 — ExpenseFormDialog, IncomeFormDialog
- **Detail**: Składanie zmian add/remove było inline w dwóch komponentach Vue. `IncomeFormDialog` nie ma `signedAmountMinorUnits`; „zamień na zwrot” wymaga add −abs w kategorii wydatku i remove +original w kategorii dochodu; istniejący `editedRecord` (`IncomeFormDialog.vue:117-126`) daje `-Math.abs(original)`, więc skopiowany zepsułby remove.
- **Fix**: Czysta funkcja `handEntryToSpendChanges` w `entry-to-spend-warning.ts` (faza 2) z testami znaków, konwersji i niepełnego formularza; dialogi w fazie 3 tylko ją wywołują; pułapka `:117-126` nazwana w planie.
- **Decision**: FIXED

### F2 — Odsetki z ruchu kapitału w Imporcie zmieniają „do wydania”, a nie są w pending

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: What We're NOT Doing (pkt 2), Phase 4 #1
- **Detail**: Wiersz `capital_movement` z `interest_category_id` tworzy dodatkową operację Income (`ImportFinancialOperations.php:218-243`). Plan pomijał ją także w kumulacji, więc późniejsze wiersze tego miesiąca liczyły „przed/po” bez tego dochodu.
- **Fix**: Odsetki (`parsedInterestAmountMinorUnits`, przy `interestSplitValid`) trafiają do `pending` jako `add` w `interest_category_id`, bez ostrzeżenia na tym wierszu; jeden przypadek testowy; zakres w planie i briefie zaktualizowany.
- **Decision**: FIXED

### F3 — Niejednoznaczny przypadek testowy kumulacji Importu

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Testing Strategy → Faza 4; Manual 4.4
- **Detail**: „50 zł, −30, −40, +100” nie mówiło, że 50 zł to start, a reszta to skutki wierszy; 4.4 mówiło „przekracza zero”.
- **Fix**: Przypadek zapisany w danych (dochód 50 zł z wpływem, wydatki 30 i 40 zł w Running z planem 0, dochód 100 zł → ostrzeżenie tylko w wierszu 2, −20 zł); 4.4 w fazie i w Progress: „sprowadza „do wydania” poniżej zera”.
- **Decision**: FIXED
