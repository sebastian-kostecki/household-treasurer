<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Restore snapshotu na istniejącego użytkownika: tożsamość importu

- **Plan**: context/changes/restore-import-identity-accounts/plan.md
- **Mode**: Deep
- **Date**: 2026-11-12
- **Verdict**: SOUND
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
7/7 paths ✓, 6/6 symbols ✓ (2 numery linii przesunięte, F5), brief↔plan ✓

Zweryfikowane w kodzie (sub-agent): digest zawsze liczony z tych samych pól, które są zapisane (ImportFinancialOperations, AttachImportPairOwnTransfer, ConvertImportPairFinancialOperation, RebuildOperation); data zawsze `Y-m-d` we wszystkich wersjach `BackupUserData`; brak unikalnych indeksów na digestach; `ImportDuplicateDetector::committedDigests()` czyta wszystkie cztery nogi; konto tożsamości może różnić się od konta nogi; fixture'y `tests/Fixtures/snapshots/*.json` nie mają pól tożsamości.

## Findings

### F1 — Brak testu odmowy dla nogi bez digestu z nieznanym kontem

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §1 i §3, Testing Strategy
- **Detail**: §3 wprowadza odmowę dla nogi bez digestu z nieznanym kontem (nowe zachowanie dla operacji i przelewów), ale §1 jej nie testuje; „po jednym przypadku na rodzaj rekordu” kłóci się z Testing Strategy (4 czy 16 przypadków).
- **Fix**: Pełna macierz data providera: 4 rodzaje rekordu × 4 braki przy digeście oraz 4 przypadki „bez digestu, nieznane konto”.
- **Decision**: FIXED

### F2 — Próba produkcji liczy digesty tylko operacji

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §2 krok 4, kryterium 3.4
- **Detail**: Kontrola porównuje tylko 1269 operacji; nogi przelewu i ruchy kapitału zostają niezliczone.
- **Fix**: Liczyć digesty i konta spoza użytkownika per tabela i noga.
- **Decision**: FIXED

### F3 — Stary klucz raw_statement_description trafia tylko do nogi source

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Key Discoveries (RestoreUserData.php:830–831)
- **Detail**: Migracja d823cdc kładła opis na destination; taki przelew zostałby odrzucony. Ścieżka martwa, bo te snapshoty mają `*_grosze` i nie wczytują się od :796.
- **Fix**: Zdanie w „What We're NOT Doing”.
- **Decision**: FIXED

### F4 — „Niepusty surowy opis” ostrzejszy niż compute()

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §3
- **Detail**: `compute()` odrzuca tylko null; reguła „niepusty” mogłaby odrzucić rekord przyjęty przez import.
- **Fix**: Wymagać `raw !== null`.
- **Decision**: FIXED

### F5 — Przesunięte numery linii i polecenie bez pełnej postaci

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State, Key Discoveries, Faza 2 kryterium 2.3
- **Detail**: `:827` → `:830–831`, `:1528` → `:1526`; „tests/Feature z filtrem Import” nie było poleceniem.
- **Fix**: Poprawione odwołania i pełne polecenie worktree z `--filter=Import`.
- **Decision**: FIXED
