<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Import wyciągu z przełomu przy szkicu roku

- **Plan**: context/changes/import-across-draft-year/plan.md
- **Mode**: Deep
- **Date**: 2026-11-19
- **Verdict**: REVISE → SOUND (after fixes)
- **Findings**: 1 critical, 4 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | WARNING |
| Architectural Fitness | FAIL |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
14/14 paths ✓ (2 new files absent, as planned), 6/6 symbols ✓, ADR-0103 free ✓, brief↔plan ✓

## Findings

### F1 — Link select-year w wierszu łamie strażnik ui-contract

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 3 §1, Critical Implementation Details
- **Detail**: `resources/js/ui-contract.test.ts:589-615` wymaga `@error=` i `listenForBudgetYearSelectionFailure` w każdym `.vue` z `<Link` i `BudgetController.selectYear()`. Plan tego nie wspominał, więc `npm run test` padłby, a nieudany wybór roku przeszedłby po cichu.
- **Fix**: Wzorzec `ReportYearsTable.vue:70-84` w `ImportPreviewRow.vue`: `pending`, `@error`, `onUnmounted(listenForBudgetYearSelectionFailure(...))`.
- **Decision**: FIXED

### F2 — Wariant „brak Budżetu” prowadzi nie tam i używa niemożliwego roku

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Desired End State, Faza 3 §1–§3
- **Detail**: GET `budget` pokazuje rok z `SelectedBudgetYear::resolve`, nigdy rok bez Budżetu. Styczeń 2028 nie może wystąpić, bo przyszła data to `FutureDate` (`CsvImportPreviewValidator.php:27`) i `before_or_equal:today` przy zapisie.
- **Fix A ⭐ Recommended**: przycisk „Utwórz Budżet :year” → `useCreateBudgetDialog().openForYear(year)`; przykłady 2028 → 2025.
  - Strength: dialog jest w `AppSidebarLayout`, użyty w `BudgetYearSwitcher.vue:137`; `budget.store` przekierowuje na `budget`.
  - Tradeoff: inny element niż w wariancie szkicu; po utworzeniu Budżet jest szkicem.
  - Confidence: MED — zachowanie dialogu z wnętrza `<Form>` Importu do sprawdzenia ręcznie (3.8).
  - Blind spot: zachowanie dialogu w kitchen sinku.
- **Fix B**: zostaw GET `budget`, zmień tekst i usuń obietnicę roku.
  - Strength: zero nowego kodu. Tradeoff: użytkownik sam szuka tworzenia roku. Confidence: HIGH. Blind spot: none.
- **Decision**: FIXED (Fix A)

### F3 — Wymagany piąty parametr w fazie 2 łamie types:check

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §2
- **Detail**: Wywołujący `importSubmitBlockReason` (`OperationsImportPreview.vue:124-129`, sześć wywołań w `import-submit-block.test.ts`) zmieniały się dopiero w fazie 3, a tsconfig obejmuje testy.
- **Fix**: Strona i istniejące testy przekazują `budgetYearStates` w fazie 2.
- **Decision**: FIXED

### F4 — Walidator: brak `return` po błędzie fazy i niejawna reguła stanu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 §3
- **Detail**: Błąd z `after()` nie jest objęty wczesnym returnem (`FinancialOperationImportValidator.php:36`), a do `rows` trafia `$errors[0]`. Lata z arkusza są bezpieczne tylko dlatego, że mają Budżet w realizacji (`LoadBudgetSheet.php:58-61`).
- **Fix**: Kontrola tylko dla Draft/Missing, po dacie otwarcia konta, z `return`; `SheetAccountTest` w kryterium 1.1; ryzyko z briefu zamknięte.
- **Decision**: FIXED

### F5 — Nowy prop powiela `shell.budgetYears`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Lean Execution
- **Location**: Faza 1 §1–§2
- **Detail**: Shell już wysyła `budgetYears: {year, phase}[]` (`ShellState.php:42`).
- **Fix A ⭐ Recommended**: klient czyta stan roku z shella.
  - Strength: bez nowego kontraktu propa. Tradeoff: assertInertia na shellu. Confidence: MED. Blind spot: jak kitchen sink dostarcza shell.
- **Fix B**: zostaw dedykowany prop.
  - Strength: strona samowystarczalna, jawny kontrakt. Tradeoff: dwa źródła fazy roku. Confidence: HIGH. Blind spot: none.
- **Decision**: FIXED (Fix B) — martwy punkt Fix A potwierdził się przy naprawie: `ImportPreviewSink.vue:250` renderuje prawdziwy `OperationsImportPreview` z propsami z fixtures, a shell pochodzi z sesji dev, więc sink nie pokazałby stanów szkicu i braku Budżetu. Uzasadnienie dopisane do Key Discoveries.

### F6 — Brakujące pliki w listach zmian faz 2–3

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §1–§2
- **Detail**: Lista `kinds` w `import-preview-rows.test.ts:12-19`, jawne props wiersza w `ImportPreviewSink.vue:331`; `UiTranslationKeysTest` nie widzi `t.operations[reason.key]`.
- **Fix**: Pliki dopisane; przypadek w `ImportPreviewHardcodedUiTest` sprawdza, że klucze `import_block_*` istnieją w `lang/pl/ui.php`.
- **Decision**: FIXED
