<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Wczytanie historycznych budżetów 2022–2025 z arkuszy xlsx

- **Plan**: context/changes/legacy-budget-import/plan.md
- **Mode**: Deep
- **Date**: 2026-11-12
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 0 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
23/23 paths ✓, 10/10 symbols ✓, brief↔plan ✓, Progress↔Phase 7/7 phases, 25/25 criteria ✓

## Findings

### F1 — Wczytane lata zostają otwarte na zapis na prawdziwych kontach

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Current State Analysis; What We're NOT Doing; Faza 2 §3
- **Detail**: Dziś datę z 2025 odrzuca tylko brak Budżetu 2025 (`OperationCategoryChoice.php:42-49`). Po wczytaniu wpis ręczny i Import z datą 2025-12-31 na siedmiu kontach otwartych 2025-12-31 przechodzą (`OperationEntryValidation.php:36` sprawdza tylko `<`), `JoinBudgetAtFirstUse` dokłada linię do 2025, saldo prawdziwego konta się przesuwa. Edycja operacji grudnia 2025 z arkusza może ją przenieść na prawdziwe konto, bo `update()` sprawdza tylko konto docelowe (`ManageFinancialOperation.php:189-194`); twierdzenie planu „edycja nie” było prawdziwe tylko w połowie.
- **Fix A ⭐ Recommended**: blokada zapisu w roku wczytanym (`OperationCategoryChoice`) i `FinancialOperationPolicy::update` odmawiająca operacji na koncie arkusza; usuwanie bez zmian
  - Strength: obietnica „tylko dodano” trwa po wczytaniu; mała zmiana w dwóch istniejących miejscach.
  - Tradeoff: dodatkowe testy; przycisk edycji zostaje i kończy się 403.
  - Confidence: HIGH — potwierdzone w kodzie.
  - Blind spot: komunikat 403 w UI nie sprawdzony.
- **Fix B**: zaakceptować ryzyko i opisać je w ADR-0085 i README
  - Strength: zero kodu.
  - Tradeoff: jedna data i jeden import po cichu przesuwają saldo 2026.
  - Confidence: MED — zależy od dyscypliny użytkownika.
  - Blind spot: zakres grudniowego eksportu banku nie sprawdzony.
- **Decision**: FIXED (Fix A) — Current State, NOT Doing, Desired End State, Faza 1 §1, Faza 2 §3 i kryterium 2.1, Faza 6 test 6.4, brief

### F2 — Snapshot v12: siedem list wersji, nie dwie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §4
- **Detail**: `RestoreUserData.php:36, 44, 129, 139, 143, 147, 155` używają `BackupUserData::SNAPSHOT_VERSION`; po podbiciu do 12 wersja 11 wypada ze wszystkich, a `:129` odrzuca każdą kopię v11.
- **Fix**: kontrakt wymienia wszystkie siedem miejsc z literalnym `11` obok stałej.
- **Decision**: FIXED

### F3 — Klasyfikator nie odróżni konta arkusza, a liczniki kategorii są w `meta`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §1–2
- **Detail**: Sekcja `accounts` ma tylko opening/current (`ControlNumbers.php:161`); liczniki `categories` i `category_rhythms` są w `meta`, które `differences()` pomija.
- **Fix**: `archived` i `sheet_year` w każdym koncie (`null` bez kolumny), nowe konto musi być zarchiwizowane z `sheet_year`; kategorie i Rytmy sprawdza sekcja `categories`, nie `row_counts`.
- **Decision**: FIXED

### F4 — Gałąź „istniejąca Kosmetyki” nie może wołać ChangeCategoryRhythm

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 6 §1
- **Detail**: `ChangeCategoryRhythm` kasuje wpisy od podanego miesiąca (`:106-111`); z 2022-01 na istniejącej kategorii skasowałby jej historię Rytmu.
- **Fix**: Rytm zapisuje tylko gałąź tworząca; test, że istniejąca zachowuje wpisy.
- **Decision**: FIXED

### F5 — down() zgodnie z migrations.md i zdublowany test

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §1, Faza 6
- **Detail**: `.ai/rules/migrations.md:21` wymaga, by wyjątek wskazywał kopię i instrukcję; test 6.5 powtarzał 2.2.
- **Fix**: komunikat wskazuje `budget:unload-sheet`, `user:backup` i sekcję README; 6.5 usunięty, bramka przenumerowana na 6.5.
- **Decision**: FIXED

### F6 — Lista operacji i propsy wpisu poza listą zmienianych odczytów

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Desired End State
- **Detail**: Lista operacji nie ma domyślnego filtra roku (`OperationsController.php:35-45`), więc pokazuje ~2 000 operacji z arkusza; `EntryToSpendInputs`, `HandEntryMonthRemainingInputs`, `ImportMonthRemainingContext` mają wpis na każdy Budget.
- **Fix**: dopisane do „Zmieniają się celowo”.
- **Decision**: FIXED

### F7 — Próba na backup:restore musi iść istniejącymi krokami README

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 7 §1
- **Detail**: `backup:restore` przywraca też `storage/app` (`RestoreApplicationBackup.php:271-295`), a bazę docelową bierze z env tylko po `config:clear`; README „Próba lokalna” już to opisuje.
- **Fix**: procedura odsyła do „Próba lokalna (bez nadpisania bazy deweloperskiej)” i bazy `restore_trial`.
- **Decision**: FIXED
