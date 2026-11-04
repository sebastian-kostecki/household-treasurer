<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Kontrakt UI podglądu importu CSV

- **Plan**: context/changes/import-preview-ui-contract/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5, 6
- **Date**: 2026-11-04
- **Verdict**: APPROVED
- **Findings**: 0 critical 2 warnings 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated: npm test (159 passed), npm run check, types:check, check:ui OK; grep rowAmountClass/native-input empty; PHP CsvImportTest+AccountPickerTest+DevImportPreviewSinkTest on PostgreSQL: 97 passed. Not re-run: composer ci:check, sloppy, build.

## Findings

### F1 — Natywne `required` na selectach wiersza niezaznaczonego

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ImportPreviewRow.vue:463,537
- **Detail**: Selecty (m.in. lokata przy ruchu kapitału) mają bezwarunkowe `required`. Niezaznaczony wiersz (np. duplikat) z takim typem blokuje natywną walidacją cały formularz, choć logika blokady i serwer dopuszczają wiersze niezaznaczone.
- **Fix**: Zmień na `:required="selection.selected"`.
- **Decision**: FIXED (Fix now)

### F2 — Wiersz niebędący tablicą daje 500 zamiast 422

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/StoreCsvImportRequest.php:44,95
- **Detail**: `fn (array $row)` w `prepareForValidation` i `rowSelections` rzuca TypeError przy `rows[0]=x`. Tylko dla zalogowanych, zachowanie sprzed zmiany, ale domykacze rozszerzono w tej zmianie.
- **Fix**: Odfiltruj `! is_array` w `prepareForValidation` i pozwól regule `rows.*.index` zawieść.
- **Decision**: FIXED (Fix now)

### F3 — Zduplikowane aria-label w wierszu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ImportPreviewRow.vue:405-533
- **Detail**: Kilka kontrolek (para, konto, lokata, kategoria odsetek) dostaje ten sam `controlLabel(import_classification)`, więc czytnik ich nie rozróżnia.
- **Fix**: Użyj istniejących kluczy kolumn (`import_other_account`, `import_term_deposit`, `import_interest_category`...) per kontrolka.
- **Decision**: FIXED (Fix now)

### F4 — Potwierdzenie duplikatów przeżywa odznaczenie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/OperationsImportPreview.vue
- **Detail**: Po odznaczeniu i ponownym zaznaczeniu duplikatu `duplicateWarningAcknowledged` zostaje true. Serwer i tak wymaga flagi; tylko niuans UX.
- **Fix**: Zeruj potwierdzenie, gdy liczba zaznaczonych duplikatów spada do 0.
- **Decision**: FIXED (Fix now)
