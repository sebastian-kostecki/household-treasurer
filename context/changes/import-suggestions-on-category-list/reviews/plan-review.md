<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Podpowiedzi Importu na liście kategorii

- **Plan**: context/changes/import-suggestions-on-category-list/plan.md
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
12/12 paths ✓, 8/8 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — Test :98 też koduje starą regułę celu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, punkt 1 (Contract)
- **Detail**: Plan zmienia tylko test `:74`, a `:98` („without name variants the vote by list category gives what a vote by name gave”) traktuje jako dowód równoważności. Jego wyrocznia `$byName` zwraca null dla „ZOO LAND” (kategoria „Bez pozycji” bez pozycji w 2026), więc po fazie 1 test zrobi się czerwony bez zapowiedzi w planie. Druga połowa nazwy `:74` („does not fall through to the next candidate”) traci scenariusz.
- **Fix**: Wymienić `:98` w Contract jako zmieniany z tego samego powodu (ADR-0063:32), z wyrocznią na nowym celu albo ograniczony do poziomu modelu; brak przejścia do kolejnego kandydata przenieść na scenariusz ze zwycięzcą zarchiwizowanym.
- **Decision**: FIXED (Fix in plan: `:98` w Contract z nowym celem, osobny scenariusz bez przejścia do kolejnego kandydata)

### F2 — Plik „przed” w fazie 3 jest nieosiągalny i ginie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3, punkt 2, kroki 3, 4 i 6
- **Detail**: Krok 3 zapisuje `2026-09-11-before.json` w storage starego worktree. Krok 4 uruchamia nowy kod przez `docker run -v "$PWD"` (AGENTS.md), który widzi tylko bieżący worktree, więc ścieżka `--compare` nie istnieje w kontenerze. Krok 6 (`git worktree remove`) usuwa plik „przed” i bez `--force` odmówi (vendor/, .env, JSON). Kroki 3–4 robią `migrate --force` bez sprawdzenia `db:show`.
- **Fix**: `OUTPUT` before.php kierować do `storage/app/private/suggestion-accuracy/` tego worktree (dodatkowy mount) albo skopiować plik przed krokiem 4; w kroku 6 `git worktree remove --force` po kopii; przed każdym `migrate` w krokach 3–4 `db:show` = `suggestion_trial`, inaczej STOP.
- **Decision**: FIXED (Fix in plan: mount katalogu wyników w kontenerze starego kodu, `git worktree remove --force`, `db:show` przed każdym `migrate`)

### F3 — Cel z forType() kosztuje ~10 zapytań zamiast 1

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 1, punkt 3; Performance Considerations
- **Detail**: `forType()` to ~5 zapytań na typ (budżety, kategorie, `with('group')`, pozycje, `CategoryRhythms`), razem ~10 zamiast dzisiejszego 1 leniwego; rytmy i grupy są dla celu zbędne. Wywołanie w POST `preview()` (`CsvImportController.php:109`), opcje w osobnym GET (`:140-141`), więc bez dublowania. NfrPerf03 mierzy tylko czas.
- **Fix**: Dopisać liczby (1 → ~10, tylko gdy jest zwycięzca) w Performance Considerations; zostawić forType() jako jedno źródło.
- **Decision**: FIXED (Fix in plan: koszt zapytań w Performance Considerations, forType() zostaje)

### F4 — Kolejność i jednorazowy generator historii

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2, punkt 1 (Contract)
- **Detail**: `loadHistory()` nie ma ORDER BY i zwraca jednorazowy `\Generator`; `fromHistory()` nie ma odcięcia po dacie. Model jest niezależny od kolejności (remisy w `compareNeighbours`), ale `array_slice` w odczycie trafności od niej zależy.
- **Fix**: W Contract: Accuracy materializuje historię i sortuje po (operation_date, id) jak `eval_service.php`; `loadHistory()` bez ORDER BY.
- **Decision**: FIXED (Fix in plan: materializacja i sortowanie w Accuracy, `loadHistory()` bez ORDER BY)

### F5 — Pomiar z 11.09 nie sprawdza reguły z fazy 1

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 3; Current State Analysis („regresja”)
- **Detail**: Migracja zostawia aktywną tylko kategorię z niezarchiwizowanym rekordem w najnowszym budżecie lub najnowszym w realizacji (`LegacyCategoryConversion.php:173-199`), więc na kopii z 11.09 każda aktywna kategoria ma pozycję w 2026. Faza 3 dowodzi równoważności głosu po nazwie i po id, nie nowej reguły celu (tę pokrywają testy). W starym schemacie „oferowana w roku” = „ma rekord w roku”; rozjazd powstał, bo S-18 poszerzyło selektor.
- **Fix**: Jedno zdanie o zakresie dowodu w measurements.md (kontrakt fazy 3) i „rozjazd po poszerzeniu selektora” zamiast „regresja” w Current State.
- **Decision**: FIXED (Fix in plan: zakres dowodu w kontrakcie measurements.md, „rozjazd po poszerzeniu selektora” w Current State, Key Discoveries i briefie)
