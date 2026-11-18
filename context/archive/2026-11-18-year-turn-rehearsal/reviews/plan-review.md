<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Próba przełomu roku 2026→2027

- **Plan**: context/changes/year-turn-rehearsal/plan.md
- **Mode**: Deep
- **Date**: 2026-11-18
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 1 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
8/8 paths ✓ (3 nowe pliki nieobecne zgodnie z planem), 9/9 symbols ✓, Progress↔Phase ✓, brief↔plan ✓. Twierdzenia sprawdzone inline (bez sub-agenta).

## Findings

### F1 — Reguła daty przyszłej koliduje z czterema oczekiwaniami

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 (YearTurnTest „Kolejność (B)”, CsvImportTest), Phase 3 (T4, T5)
- **Detail**: Wszystkie daty operacji mają `before_or_equal:today` (`ExpenseValidationRules.php:21`, `FinancialOperationImportValidator.php:92,118`), a walidacja czyta `Date::parse` (`ValidatesAttributes.php:373`), więc idzie za `travelTo` i zegarem próby. Wydatek 2027-01-02 przy zegarze 2027-01-01 00:00:01, wiersz 03.01 przy zegarze 2027-01-02 09:00 w T4/T5 oraz CsvImportTest bez zegara dostałyby błąd daty przyszłej zamiast oczekiwanego wyniku.
- **Fix**: Wyrównać daty do zegara: (B) wydatek 2027-01-01; CsvImportTest z `travelTo('2027-01-02 09:00')`; plik T4/T5 29.12–02.01; Intent CsvImportTest zgodny z kontraktem; nowa notka w Critical Implementation Details.
- **Decision**: FIXED

### F2 — Notka o wygaśnięciu sesji ma odwrócony kierunek

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details — „Zegar w próbie idzie tylko do przodu”
- **Detail**: `DatabaseSessionHandler::expired` (`:140-143`) wygasza sesję przy `last_activity < now − 120 min`. Wylogowuje skok do przodu o >2 h (T1→T2 ~37 h, T3→T4 ~32 h), nie cofnięcie.
- **Fix**: Przepisać notkę: skok >2 h do przodu wylogowuje; po T2 i T4 ponowne logowanie.
- **Decision**: FIXED

### F3 — Bazowe liczby kontrolne przed T0, więc 3.1 nie może przejść

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 — Przygotowanie krok 5, T1, kryterium 3.1
- **Detail**: T0 zatwierdza grudzień, a `month_plans` i `month_plan_items` są liczone (`ControlNumbers.php:42-43`). Faza Budżetu nie jest w liczbach kontrolnych. Wklejenie pełnego porównania do `rehearsal.md` wpisałoby do gita prawdziwe kwoty planu.
- **Fix**: `--output` w T1 tuż przed startem; oczekiwane różnice tylko w `lines` 2027; do `rehearsal.md` liczba i klucze różnic, bez wartości.
- **Decision**: FIXED

### F4 — Faza 1: czym staje się czerwony test nieblokujący

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Phase 1 — Overview, przelew i ruch kapitału, kryteria 1.1/1.3/1.4
- **Detail**: Nieblokujące znalezisko trafia do `rehearsal.md`, ale bramka wymaga zieleni, a plan nie mówi, co z testem. 1.4 pisze do `rehearsal.md`, który powstawał dopiero w fazie 3.
- **Fix A ⭐ Recommended**: Test przypina obserwowane zachowanie z nazwą „znana luka” i docblockiem do `rehearsal.md`; faza 1 tworzy szkielet `rehearsal.md`.
  - Strength: Ten sam wzorzec co test pułapki (B); bramka zielona, zmiana zachowania widoczna za rok.
  - Tradeoff: Asercja zmienia się po pierwszym uruchomieniu; wymaga jasnej notki.
  - Confidence: HIGH — spójne z decyzją „Pułapka (B)”.
  - Blind spot: None significant.
- **Fix B**: `markTestIncomplete` z odnośnikiem do kandydata
  - Strength: Oczekiwanie zostaje zapisane tak, jak było zamierzone.
  - Tradeoff: Stały „incomplete”, CI nie złapie późniejszej zmiany zachowania.
  - Confidence: MED — zależy od traktowania incomplete w ci:check.
  - Blind spot: Zachowanie incomplete w CI niesprawdzone.
- **Decision**: FIXED (Fix A)

### F5 — RehearsalClockTest pisze do prawdziwego storage deva

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 2 — RehearsalClock i RehearsalClockTest
- **Detail**: Testy dzielą `storage/app/private` z uruchomioną aplikacją. `composer ci:check` przy commicie poprawki w fazie 3 nadpisałby albo usunął zegar trwającej próby.
- **Fix**: Plik przez `Storage::disk('local')`, w teście `Storage::fake('local')`.
- **Decision**: FIXED

### F6 — Brak źródła pliku CSV 29.12–02.01

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 — T4, prerekwizyty w briefie
- **Detail**: W listopadzie żaden prawdziwy eksport nie obejmuje przełomu.
- **Fix**: Krok 5 Przygotowania: kopia świeżego eksportu z `storage/app/private/statements/` z przesuniętymi datami, rachunek z odpowiednikiem w przywróconych danych.
- **Decision**: FIXED

### F7 — SelectedBudgetYear nie czyta fazy

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Phase 1 — §3 SelectedBudgetYearTest
- **Detail**: `resolve` nie czyta fazy (`SelectedBudgetYear.php:25-50`), więc parametr faz w `resolveFor` nic nie zmienia.
- **Fix**: Bez rozszerzania helpera; komentarz, że faza nie zmienia wyniku (ADR-0075).
- **Decision**: FIXED
