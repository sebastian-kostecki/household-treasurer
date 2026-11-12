<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Restore snapshotu na istniejącego użytkownika: tożsamość importu

- **Plan**: context/changes/restore-import-identity-accounts/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-12
- **Verdict**: APPROVED
- **Findings**: 0 critical, 3 warnings (2 fixed, 1 accepted), 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING (naprawione w 6e5df5c) |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Kryteria automatyczne uruchomione w przeglądzie: `tests/Feature/UserSnapshotCommandTest.php` na PostgreSQL (`testing_restore_import_identity_accounts`) 62 passed, `tests/Feature --filter=Import` 148 passed, ADR-0079 jeden plik, glosariusz odwołuje się do ADR-0079, README bez „jeszcze nie działa”, raport próby istnieje. Po poprawkach: snapshot 66 passed, Pint passed, Sloppy 0 findings.

Ograniczenie: próba z fazy 3 poszła na snapshocie z lokalnej bazy, nie z produkcji (zob. `restore-trial.md`).

## Findings

### F1 — Rzutowanie niezwalidowanej daty i kwoty przy liczeniu digestu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/UserSnapshot/RestoredImportIdentity.php:66-68
- **Detail**: `(string) $date` i `(int) $amount` na danych ze snapshotu. Kwota-tekst dawała 0, data w innym formacie dawała digest, którego `ImportDuplicateDetector` nigdy nie dopasuje (ciche wyłączenie wykrywania duplikatów). Dotyczy tylko ręcznie edytowanych lub obcych plików.
- **Fix**: Rekord z digestem odrzuca datę spoza `Y-m-d` i kwotę, która nie jest int, przez `UserSnapshotException` z nazwą rekordu.
- **Decision**: FIXED — 6e5df5c (walidacja w klasie + 4 przypadki testowe)

### F2 — Przelew starego snapshotu z digestem nogi destination bez jej surowego opisu jest odrzucany

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/RestoreUserData.php:~842-870
- **Detail**: Noga destination nie ma fallbacku starego klucza `raw_statement_description` (źródłowa ma). Snapshot v1–5 z digestem destination i bez jej opisu odrzuca teraz cały restore, a wcześniej wczytywał nogę ze starym digestem. Jest to zgodne z ADR-0079 i z sekcją „What We're NOT Doing” planu (ta ścieżka jest martwa: takie snapshoty mają też klucze `*_grosze` i i tak się nie wczytują).
- **Fix**: Bez zmian w kodzie.
  - Strength: Zgodne z decyzją planu i ADR-0079; odmowa jest głośna, nie cicha.
  - Tradeoff: Hipotetyczny stary backup nie wczyta się wcale.
  - Confidence: MED — brak fixture'a v1–5 z digestem, plan opiera się na analizie migracji d823cdc.
  - Blind spot: Nie sprawdzono żadnego prawdziwego starego pliku.
- **Decision**: ACCEPTED — zachowanie celowe (plan, „What We're NOT Doing”); do potwierdzenia przez użytkownika, jeśli ma stare backupy v1–5

### F3 — Test odmów łapał dowolny `UserSnapshotException`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/UserSnapshotCommandTest.php (`test_restore_action_refuses_incomplete_import_identity`)
- **Detail**: Bez asercji wiadomości test przeszedłby także po wcześniejszej, niezwiązanej odmowie, więc nie dowodził, że działa sprawdzenie tożsamości.
- **Fix**: Asercje wiadomości: rodzaj rekordu, noga (source/destination) i brakujące pole albo id konta.
- **Decision**: FIXED — 6e5df5c

### F4 — Asercja digestu w `test_restore_action_preserves_domain_values_with_new_ids` częściowo tautologiczna

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/UserSnapshotCommandTest.php
- **Detail**: Oczekiwany digest liczony jest z pól odczytanych z przywróconego wiersza. Niezależny dowód dają nowe testy z literałami i `ImportDuplicateDetector`.
- **Fix**: Bez zmian.
- **Decision**: SKIPPED

### F5 — Test odmowy sprawdza po odmowie tylko konta i operacje

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/UserSnapshotCommandTest.php
- **Detail**: Nie ma sprawdzenia liczby przelewów i ruchów kapitału po odmowie w ich nogach. Transakcja gwarantuje wycofanie, a plan wymienia „liczba operacji i nazwa konta”.
- **Fix**: Bez zmian.
- **Decision**: SKIPPED

### F6 — Test duplikatu nie obejmuje nogi source ani ruchu kapitału

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: tests/Feature/UserSnapshotCommandTest.php (`test_import_preview_flags_restored_operation_as_duplicate`)
- **Detail**: Sprawdza operację i nogę destination, co spełnia „operacja i noga przelewu” z planu. Digesty wszystkich czterech nóg są pokryte w teście restore na tego samego użytkownika.
- **Fix**: Bez zmian.
- **Decision**: SKIPPED

### F7 — Komunikat nieznanego konta interpoluje wartość `mixed`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/UserSnapshot/RestoredImportIdentity.php:40
- **Detail**: Gdyby id konta w pliku było tablicą, interpolacja dałaby „Array” z ostrzeżeniem PHP. Tylko ręcznie zepsute pliki.
- **Fix**: Bez zmian.
- **Decision**: SKIPPED
