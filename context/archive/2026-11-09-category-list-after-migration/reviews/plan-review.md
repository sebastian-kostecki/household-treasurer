<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Category List After Migration

- **Plan**: context/changes/category-list-after-migration/plan.md
- **Mode**: Deep
- **Date**: 2026-11-09
- **Verdict**: REVISE → SOUND po triage (wszystkie ustalenia poprawione w planie)
- **Findings**: 1 critical, 6 warnings, 2 observations

## Verdicts

Werdykty sprzed triage.

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | FAIL |

## Grounding

- 19/19 ścieżek ✓, ale `CreateCategory`, `ArchiveCategory` i `RestoreCategory` z fazy 3 nie istnieją (F8).
- 2/2 symbole ✓, brief↔plan ✓, Progress↔Fazy ✓, ADR-0065 to następny wolny numer ✓.

## Findings

### F1 — Faza 1: kod tworzący pozycje nie ma skąd wziąć category_id

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §3–§5
- **Detail**: Backfill ustawia `budget_lines.category_id` na `NOT NULL` już w fazie 1. Plan mówi jednak, że kod przechodzi „mechanicznie, bez zmiany zachowania”. Cztery miejsca wstawiają pozycje z samym `name` i `type`:
  - `CategoryController.php:41` (store jest w kontrolerze, nie w akcji)
  - `CreateBudget.php:38-49` (tworzy rekordy od zera, bez `replicate`)
  - `RestoreUserData.php:198-213`
  - `DemoHouseholdSeeder.php:265`

  Plan każe restore budować listę dopiero po wstawieniu pozycji, a to łamie `NOT NULL`. Nazwa nie jest dziś nigdzie unikalna (`CategoryValidationRules.php:22`), więc szukanie po kluczu z tworzeniem w razie braku wpada na `unique(budget_id, category_id)`.
- **Fix A ⭐ Recommended**: kontrakt zapisu w fazie 1.
  - `store`: szukanie albo tworzenie po (`user_id`, `type`, `name_key`); klucz zajęty w tym budżecie → błąd.
  - `CreateBudget`: przenosi `category_id` z pozycji źródłowej.
  - Restore: konwersja przed wstawieniem pozycji.
  - Edycje opisu w fazach 1–2 piszą tylko do starych kolumn; rozjazd jest akceptowany, bo dotyczy tylko deva.
  - Strength: `NOT NULL` i asercje działają od fazy 1, a migracja na produkcji jest ta sama co w teście.
  - Tradeoff: komunikat „już jest w budżecie” (backend) przechodzi z fazy 3 do fazy 1.
  - Confidence: HIGH — wszystkie cztery miejsca zapisu są znane.
  - Blind spot: zmiana nazwy w fazach 1–2 na devie rozjedzie listę i stare kolumny do fazy 3.
- **Fix B**: `category_id` nullable do migracji z fazy 3.
  - Strength: faza 1 jest naprawdę mechaniczna.
  - Tradeoff: migracja 3 musi wypełnić NULL-e z deva, a v6 musi je znosić.
  - Confidence: MED — więcej ruchomych części.
  - Blind spot: parytet restore = migracja przy NULL-ach.
- **Decision**: FIXED (Fix A)

### F2 — ResetUserData nie usuwa kategorii listy

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 §4–§5
- **Detail**: `ResetUserData.php:24` kasuje budżety; kaskada zabiera `budget_lines`, ale lista (FK do `user_id`) zostaje.
  - `RestoreUserData` najpierw woła reset (`:28`), potem buduje listę od nowa → naruszenie `unique(user_id, type, name_key)`.
  - Ten sam problem mają `demo:seed --fresh` i komenda resetu.
  - Reset w ustawieniach zostawia osierocone kategorie.
- **Fix**: `ResetUserData` usuwa `$user->categories()` po budżetach; test restore na koncie z istniejącą listą.
- **Decision**: FIXED

### F3 — Przerwany backfill zostawia schemat po expand

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 1 §3, §6; Faza 6 §2–3
- **Detail**: Expand i backfill to dwie migracje, każda we własnej transakcji. Gdy asercja backfillu rzuci wyjątek, expand jest już zatwierdzony i zapisany w partii. Przypadek testowy „nic nie zostaje” był fałszywy, a README nie opisywało drogi wyjścia.
- **Fix A ⭐ Recommended**: dwie migracje i nazwana droga awarii: aplikacja zostaje w trybie serwisowym → `migrate:rollback` → powrót kodu.
  - Strength: zgodne z `migrations.md`.
  - Tradeoff: ręczny krok przy awarii.
  - Confidence: HIGH — Migrator zapisuje każdą migrację zaraz po wykonaniu.
  - Blind spot: None significant.
- **Fix B**: expand i backfill w jednej migracji.
  - Strength: awaria naprawdę nic nie zostawia.
  - Tradeoff: wyjątek od `migrations.md`, wymaga zapisu w ADR-0065.
  - Confidence: HIGH.
  - Blind spot: reguła i przyszłe migracje się rozjeżdżają.
- **Decision**: FIXED (Fix A)

### F4 — Wycofanie przez deploy.sh przejdzie skryptem S-18

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details; Migration Notes; Faza 6 §3
- **Detail**: Skrypt aktualizuje się sam, więc przy wycofaniu biegnie jeszcze wersja S-18. Przy `set -e` przerywa ją każdy z dwóch kroków:
  - `user:backup` w kontenerze S-18 nie znajdzie `budget_lines`;
  - `app:control-numbers` nie istnieje w cofniętym kodzie.
- **Fix**: README: przy wycofaniu i przy awarii z F3 najpierw `git pull --ff-only` na serwerze, potem `deploy.sh`.
- **Decision**: FIXED

### F5 — Nowy kod obsługuje stary schemat, zanim włączy się artisan down

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 6 §2
- **Detail**: Tryb serwisowy włączał się dopiero po `up -d`, `chown` i liczbach „przed”. `storage` to bind-mount (`docker-compose.prod.yml:17`), więc tryb serwisowy włączony w starym kontenerze przetrwa `up -d`.
- **Fix**: `artisan down` w starym kontenerze przed `up -d`; przy pierwszym uruchomieniu zaraz po `up -d`.
- **Decision**: FIXED

### F6 — Import: zwycięzca bez pozycji w roku daty

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §3
- **Detail**: Dziś, gdy nazwy nie ma w roku albo występuje dwa razy, wynik to null (`ImportCategorySuggester.php:122-127`). Plan nie mówił, co się dzieje, gdy zwycięzca nie ma niezarchiwizowanej pozycji w roku albo jest archiwalny na liście. Dołączenie przy zatwierdzeniu to FR-019, czyli S-19.
- **Fix**: brak pozycji albo kategoria archiwalna → brak podpowiedzi; przypadek wchodzi do testu równoważności.
- **Decision**: FIXED

### F7 — Faza 7 uruchamia app:control-numbers „starym kodem”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 7 §1 Contract
- **Detail**: Komendy nie ma na `main`; powstaje w fazie 6.
- **Fix**: liczby „przed” liczy gałąź S-18, przed `migrate`.
- **Decision**: FIXED

### F8 — Zasięg zmiany nazw w fazie 1 i nieistniejące akcje fazy 3

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §4; Faza 3 §2
- **Detail**: Do zmiany poza samą nazwą modelu:
  - 8 × `Rule::exists('categories','id')`;
  - surowe złączenia w `ImportCategorySuggester:78,87`, `OperationsFilterOptions:35-36` i `MonthCategoryActuals:40-41`;
  - około 22 miejsc kolumny `category_id`;
  - relacje, które wnioskują klucz obcy z nazwy.

  Do tego `CreateCategory`, `ArchiveCategory` i `RestoreCategory` nie istnieją.
- **Fix**: lista tych miejsc w kontrakcie fazy 1; trzy akcje w fazie 3 oznaczone jako nowe pliki wydzielone z `CategoryController`.
- **Decision**: FIXED

### F9 — Migracja musi przejść na SQLite i dać się cofnąć w teście

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 1 §3, §6
- **Detail**: `phpunit.xml` i Sail domyślnie puszczają wszystkie migracje na SQLite, a `ALTER … RENAME CONSTRAINT` działa tylko w PostgreSQL. Przy `RefreshDatabase` wszystkie migracje są w partii 1, więc test potrzebuje `--path`.
- **Fix**: zmiana nazw ograniczeń tylko za strażnikiem `pgsql`; test cofa migracje przez `migrate:rollback --path=<pliki S-18>`.
- **Decision**: FIXED
