<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Jeden dzień aplikacji (App Day) — plan implementacji

- **Plan**: context/changes/app-day-decision/plan.md
- **Mode**: Deep
- **Date**: 2026-11-19
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 0 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
12/12 paths ✓, 7/7 cytowanych zdań ADR ✓, liczby 20/15/7 ✓, Progress↔Phase ✓, brief↔plan ~ (5 vs 8 miejsc z czasem lokalnym)

## Findings

### F1 — Nierozstrzygnięty „dzień chwili” (archived_at) w decyzjach domeny

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots / End-State Alignment
- **Location**: Faza 1 — ADR-0103 (Server, Data), spis miejsc
- **Detail**: ADR mówił „znaczniki czasu to chwile w UTC, nie App Day”, ale nie mówił, jaki dzień ma chwila. `MonthPlanProposal.php:180-184` i `IrregularCollected.php:168` porównują `archived_at` z pierwszym dniem miesiąca w UTC (Kategoria wypada z Propozycji, linia przestaje zbierać); `CategoryListReading.php:75` pokazuje `archived_at->toDateString()` w UTC. Archiwizacja 1.02 00:30 w Polsce liczy się jako styczniowa. Akapit Data zakładał zapisany miesiąc archiwizacji, którego nie ma.
- **Fix**: Reguła „dzień chwili” w ADR-0103 (data znacznika w Europe/Warsaw), poprawiony akapit Data i wpis słownika, 3 miejsca w spisie, test w strategii S-39.
  - Strength: Zamyka jedyną drogę rozjazdu „wszędzie 1 stycznia”, bez zmiany schematu.
  - Tradeoff: 3 pozycje więcej w S-39.
  - Confidence: HIGH — porównania potwierdzone w kodzie; innych porównań `archived_at` z datą w `app/` nie ma.
  - Blind spot: None significant.
- **Decision**: FIXED

### F2 — Wyjątek dla stron gościa opiera się na fałszywym założeniu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: What We're NOT Doing; ADR-0103 Browser; wpis App Day
- **Detail**: Plan zostawiał logowanie i stronę powitalną przy zegarze przeglądarki, bo „nie mają shell”. `HandleInertiaRequests.php:48` wysyła `shell` w całej grupie `web`, `ShellState.php:25-45` zwraca dla gościa nulle.
- **Fix A ⭐ Recommended**: Usunąć wyjątek, strony gościa też biorą `shell.today`.
  - Strength: Reguła bez wyjątków w słowniku, ADR i przyszłej regule `.ai/rules`.
  - Tradeoff: +2 miejsca w S-39.
  - Confidence: HIGH — `shell` już jest na tych stronach.
  - Blind spot: None significant.
- **Fix B**: Zostawić wyjątek z uzasadnieniem „kosmetyczne”.
  - Strength: Zakres S-39 bez zmian.
  - Tradeoff: Wyjątek w słowniku bez powodu.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F3 — Spis dla S-39: drobne luki i ścieżka do ADR, która się przeniesie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Spis miejsc w kodzie; ADR-0103 Consequences; Faza 2 #2
- **Detail**: `CsvImportPreviewValidator.php:21` to `CarbonImmutable::today()`, nie gołe `today()`; brak `lib/auth-year-progress.ts:16-24` i `lib/balance-reconciliation.ts:19-26`; `before_or_equal:today` rozwiązywane przez `Date::parse('today')` w strefie aplikacji, reguła tekstowa nie przyjmuje strefy; częściowe przeładowania z `only` nie odświeżają `shell`; brief 5 vs plan 8 miejsc lokalnych; ADR wskazywał ścieżkę planu, która przejdzie do archiwum; Faza 2 #2 kazała poprawiać liczby w Consequences, których tam nie ma.
- **Fix**: Poprawione pozycje spisu, zdanie o `before_or_equal` i „pełnej wizycie”, odwołanie do spisu bez ścieżki, Faza 2 #2 wskazuje liczby w „What We're NOT Doing”, brief zsynchronizowany.
- **Decision**: FIXED

### F4 — Dwie kontrole automatyczne nie sprawdzają tego, co obiecują

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: 1.5, 2.4, 2.1
- **Detail**: `git diff --name-only` nie widzi nieśledzonego ADR-0103; 2.1 policzy 8, jeśli ADR-0103 zacytuje konwencję.
- **Fix**: 1.5/2.4: `git status --porcelain --untracked-files=all | grep -v ' context/'` nic nie wypisuje; 2.1: `grep -v '/0103-'` przed `wc -l`.
- **Decision**: FIXED
