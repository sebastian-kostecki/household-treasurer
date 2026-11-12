# Restore snapshotu na istniejącego użytkownika: tożsamość importu — plan implementacji

## Overview

`user:restore` ma wczytać snapshot także na użytkownika, który ma już dane (roadmapa F-06). Dziś pada na kluczu obcym, bo id konta w tożsamości importu operacji i obu nóg przelewu idą do bazy surowo ze snapshotu. Poprawka mapuje te id przez mapę kont, jak w ruchach kapitału. Dla każdego rekordu z importu liczy też digest od nowa z użytkownika docelowego i nowego id konta, żeby po powrocie ponowny import wyciągu nadal wykrywał duplikaty. Rekord z digestem, któremu brakuje wejścia do przeliczenia, odrzuca cały snapshot. To droga powrotu dla scalenia par kategorii na produkcji (S-21) i dla snapshotów `deploy.sh`, więc wchodzi przed wdrożeniem.

## Current State Analysis

- `RestoreUserData::handle()` (`app/Actions/RestoreUserData.php:55`) najpierw czyści dane użytkownika (`ResetUserData`), potem tworzy konta od nowa i buduje `$accountIdMap` (`:161`). Konta dostają więc **zawsze** nowe id. Do pustej bazy id przypadkiem pokrywają się ze snapshotem, dlatego próba 6b z S-21 przeszła.
- Operacje: `import_identity_account_id` jest kopiowane surowo (`:800`). Przelewy: `source_import_identity_account_id` (`:834`) i `destination_import_identity_account_id` (`:838`) tak samo. Ruchy kapitału mapują pole i odrzucają nieznane konto (`:867`–`:874`), ale digest kopiują bez zmian (`:889`).
- Po tabelach: do `accounts` prowadzą `financial_operations.account_id` i `.import_identity_account_id`, w `own_transfers` `source_`/`destination_account_id` i oba `*_import_identity_account_id`, w `capital_movements` `account_id` i `import_identity_account_id`. Restore nie mapuje tylko trzech pól wymienionych wyżej. Inne tabele snapshotu (`term_deposits`, `budgets`, `categories`, `category_groups`, `budget_lines`, `month_plans`) nie trzymają id konta. **Niewiadoma z roadmapy jest zamknięta.**
- Digest to `sha256(user_id | account_id | data | kwota ze znakiem | surowy opis)` (`app/Support/CsvImport/ImportIdentityDigest.php:28`). Duplikaty wykrywa `ImportDuplicateDetector::committedDigests()` po samym digeście (`app/Support/CsvImport/ImportDuplicateDetector.php:108`). Gdy id konta się zmienia, stary digest nie trafia już w żaden nowo liczony. Restore na użytkownika z danymi wyłączyłby więc po cichu wykrywanie duplikatów dla ~1269 z 1280 operacji produkcji (`context/archive/2026-11-11-category-merge/merge-trial.md`, O1).
- `ImportPairCandidateDetector` (`app/Support/CsvImport/ImportPairCandidateDetector.php:194`, `:300`, `:308`, `:333`, `:369`) i `ConvertImportPairFinancialOperation` (`:57`) czytają `*import_identity_account_id`, więc źle wskazane konto psuje też pary importu.
- Testy tego nie łapią. `test_restore_action_preserves_domain_values_with_new_ids` (`tests/Feature/UserSnapshotCommandTest.php:492`) restore'uje na **innego** użytkownika, którego konta źródłowe nadal istnieją, więc klucz obcy przechodzi, a operacja wskazuje konto użytkownika źródłowego. Test sprawdza dodatkowo, że digest się nie zmienił (`:521`, `:524`, `:525`). Fixture `createUserWithDomainData()` (`:1526`) ma sztuczne digesty (`'digest-paycheck'`).

## Desired End State

- `user:restore` snapshotu z operacjami, przelewami i ruchami kapitału z importu na użytkownika, który ma już dane (także na niego samego), przechodzi na PostgreSQL. Każde `*import_identity_account_id` wskazuje przywrócone konto użytkownika docelowego.
- Dla każdego rekordu, który ma digest w snapshocie, przywrócony digest równa się `ImportIdentityDigest::compute(user docelowy, nowe id konta, data tożsamości, kwota tożsamości, surowy opis)`. Ponowny podgląd importu tego samego wiersza oznacza go jako Import duplicate.
- Snapshot z rekordem, który ma digest, a brakuje mu wejścia (surowego opisu, daty lub kwoty tożsamości) albo wskazuje konto tożsamości spoza snapshotu, jest odrzucany przez `UserSnapshotException` z nazwą rekordu. Transakcja się wycofuje, a dane użytkownika zostają bez zmian.
- ADR-0079, glosariusz („Import duplicate”), `.ai/rules/snapshot.md` i README („Scalanie kategorii”) opisują to zachowanie. Próba na kopii produkcji potwierdza, że restore na bazę z danymi przechodzi, a liczby kontrolne się zgadzają.

### Key Discoveries:

- Wejścia digestu są w snapshocie: `import_identity_date` zapisany przez `toDateString()` (`app/Actions/BackupUserData.php:256`), czyli ten sam ciąg `Y-m-d` co w wierszu importu, `import_identity_amount_minor_units`, surowy opis rekordu oraz `source.user_id` (`:30`).
- Surowy opis po imporcie się nie zmienia: `raw_statement_description` jest `prohibited` we wszystkich regułach edycji (`app/Concerns/ExpenseValidationRules.php:25`, `IncomeValidationRules.php:25`, `OperationRebuildValidationRules.php`, `CapitalMovementValidationRules.php:26`). Przeliczenie z zapisanego opisu odtwarza więc wejście z commitu.
- Noga przelewu: digest nogi „source” liczony jest z `source_raw_statement_description`, a „destination” z `destination_raw_statement_description`. Nogę wybiera znak kwoty (`app/Support/CsvImport/OwnTransferImportSnapshots.php:10`, `ImportIdentityFactory::forOwnTransferImportedAccount()`). Tak samo zapisują `AttachImportPairOwnTransfer` (`:96`–`:107`) i `ConvertImportPairFinancialOperation` (`:125`–`:169`). Restore ma już fallback starego klucza `raw_statement_description` dla nogi źródłowej (`RestoreUserData.php:830`–`:831`).
- Ruch kapitału i operacja Income z tego samego wiersza mają tę samą tożsamość (glosariusz „Import duplicate”). Oba zapisują ten sam surowy opis (`app/Actions/ImportFinancialOperations.php:203`, `:224`), więc po przeliczeniu ich digesty nadal są równe.
- `RestoreUserData` jest w `.sloppy-baseline.json` jako duża klasa (`fingerprint: RestoreUserData`). Logika tożsamości trafia do nowej klasy w `app/Support/UserSnapshot/`, obok `UserSnapshotException`, zamiast powiększać akcję.

## What We're NOT Doing

- Nowa wersja snapshotu: format zostaje na 10, `BackupUserData` się nie zmienia. Przeliczenie działa dla każdej wersji, która niesie tożsamość importu.
- Weryfikacja zapisanego digestu względem `source.user_id` i starych id przed przeliczeniem. Restore zawsze liczy od nowa. Ewentualne niezgodności w pliku sprawdza tylko krok ręczny w fazie 3.
- Nadawanie tożsamości rekordom bez digestu w snapshocie. Noga bez digestu nie pochodzi z importu: restore mapuje jej id konta (jeśli jest) i nie liczy digestu.
- Zmiana `ImportIdentityDigest`, `ImportDuplicateDetector` i ścieżek importu.
- Rozszerzenie fallbacku starego klucza `raw_statement_description` (`RestoreUserData.php:830`–`:831`) na nogę destination. Migracja d823cdc kładła ten opis na destination, gdy tylko ona miała digest, więc taki przelew zostałby odrzucony. Snapshoty ze starym kluczem mają jednak też klucze `*_grosze` i już dziś się nie wczytują (`:796` czyta `amount_minor_units`), więc ta ścieżka jest martwa.
- Scalenie par na produkcji, wdrożenie i `user:backup` na produkcji: to kroki użytkownika po tej zmianie (roadmapa S-21: F-06 → `deploy.sh` → `user:backup` → scalenie → `app:control-numbers`).
- `backup:restore` (kopia instancji, ADR-0064) i `tests/Feature/ApplicationBackupRestoreTest.php`.

## Implementation Approach

Dokumenty idą przed kodem, bo zmiana doprecyzowuje „niezmienny digest” z ADR-0007 (twarda zasada AGENTS.md: glosariusz i ADR przed kodem). Następnie test regresji na PostgreSQL, czerwony przed poprawką: restore własnego snapshotu na użytkownika z danymi pada dziś na SQLSTATE 23503. Poprawka wprowadza jedną klasę, która dla jednej nogi tożsamości (operacja, noga source, noga destination, ruch kapitału) zwraca cztery pola do zapisu albo rzuca odmowę. `RestoreUserData` woła ją w trzech miejscach. Na koniec README i próba na kopii produkcji: powtórzenie kroku 6a z próby S-21, który wtedy padł.

## Critical Implementation Details

### State sequencing

Digest liczy się z id użytkownika **docelowego** i id konta **po** `restoreAccounts()`, nigdy z `source.user_id` ani z id ze snapshotu. Datę trzeba podać jako ciąg `Y-m-d` ze snapshotu, bez przechodzenia przez Carbon. Format ciągu wchodzi do hasha, więc inna postać daty daje inny digest i test duplikatu przejdzie na czerwono.

## Faza 1: Decyzja w dokumentach

### Overview

Zapisuje decyzję przed kodem: restore zachowuje tożsamość importu i liczy jej digest od nowa z przywróconymi id, a rekord bez wejść odrzuca.

### Changes Required:

#### 1. ADR-0079

**File**: `context/foundation/adr/0079-restore-re-derives-import-identity.md`

**Intent**: Nowy ADR (po angielsku, jak 0078), który dopisuje się do ADR-0007. Tożsamość to Użytkownik, Konto, data, kwota i surowy opis, a nie id wierszy. Restore nadaje nowe id, więc mapuje konto tożsamości przez mapę kont i liczy digest od nowa z zamrożonych pól. Rekord z digestem bez wejścia odrzuca cały snapshot.

**Contract**: Sekcje w stylu 0078: kontekst (O1 z próby S-21, 1269/1280 operacji, SQLSTATE 23503 i ciche wyłączenie wykrywania duplikatów), decyzja, przykład (konto 3 → 12, digest z 12 i użytkownika docelowego), odrzucone opcje (kopiowanie digestu bez zmian, czyszczenie tożsamości, zachowanie starego digestu z ostrzeżeniem) i konsekwencje (ruchy kapitału też dostają przeliczony digest; noga bez digestu nie dostaje go). Numer 0079 to następny wolny, `development` kończy się na 0078. Przed commitem sprawdź ponownie.

#### 2. Glosariusz

**File**: `context/foundation/glossary.md` (wpis **Import duplicate**, `:230`)

**Intent**: Jedno zdanie: restore (`user:restore`) zachowuje zamrożoną tożsamość i liczy jej digest od nowa dla przywróconego Użytkownika i Konta (ADR-0079). Dzięki temu „frozen at that commit” nie czyta się jako zakaz przeliczania.

**Contract**: Treść wpisu i lista _Avoid_ bez zmian. Dochodzi tylko zdanie z odwołaniem do ADR-0079.

#### 3. Reguła snapshot

**File**: `.ai/rules/snapshot.md`

**Intent**: Nowa sekcja, np. „Import identity follows the restored Account”. Każde `*import_identity_account_id` idzie przez mapę kont. Digest rekordu, który ma go w snapshocie, jest liczony przez `ImportIdentityDigest` z użytkownika docelowego. Brak wejścia albo nieznane konto oznacza odmowę. Noga bez digestu dostaje tylko mapowanie. Test regresji restore'uje na użytkownika z danymi i sprawdza duplikat w podglądzie importu.

**Contract**: Frontmatter `paths:` bez zmian. Potem `php artisan boost:index-rules` (indeks nie powinien się zmienić, bo ścieżki są te same).

### Success Criteria:

#### Automated Verification:

- ADR-0079 istnieje i nie ma drugiego pliku z numerem 0079: `ls context/foundation/adr/0079-*.md | wc -l` daje 1
- Wpis „Import duplicate” w glosariuszu odwołuje się do ADR-0079: `grep -n "ADR-0079" context/foundation/glossary.md`
- `.ai/rules/snapshot.md` opisuje mapowanie i przeliczanie tożsamości importu, a `php artisan boost:index-rules` nie zmienia `.ai/rules/index.md`

#### Manual Verification:

- Użytkownik akceptuje treść ADR-0079 (przykład, odrzucone opcje, odmowa przy brakujących wejściach)

**Implementation Note**: Faza 1 to osobny commit `docs(restore-import-identity-accounts): …` przed kodem, razem ze zmianą statusu F-06 w roadmapie. Po weryfikacji automatycznej zatrzymaj się na akceptację ADR.

---

## Faza 2: Czerwony test → poprawka restore

### Overview

Najpierw testy regresji, czerwone na PostgreSQL przed poprawką. Potem klasa tożsamości i jej użycie w `RestoreUserData` dla operacji, obu nóg przelewu i ruchów kapitału.

Wszystkie uruchomienia PostgreSQL w tej fazie idą na własną bazę zmiany `testing_restore_import_identity_accounts` (pusta, tworzona raz: `docker exec household-treasurer-pgsql-1 psql -U sail -d postgres -c 'create database testing_restore_import_identity_accounts'`), nigdy na wspólną `testing` (`context/foundation/agents/parallel-changes.md`).

### Changes Required:

#### 1. Testy regresji (najpierw, czerwone)

**File**: `tests/Feature/UserSnapshotCommandTest.php`

**Intent**: Odtworzyć O1 i ciche wyłączenie duplikatów, zanim cokolwiek zmieni się w kodzie. Zapisać wynik czerwonego uruchomienia na PostgreSQL (oczekiwany SQLSTATE 23503 na `financial_operations_import_identity_account_id_foreign`).

**Contract**: Nowe testy (nazwy orientacyjne):
- `test_restore_action_restores_import_identity_onto_user_with_existing_data`: backup użytkownika z operacją, przelewem (obie nogi) i ruchem kapitału z tożsamością importu, a potem `RestoreUserData` na **tego samego** użytkownika. Restore przechodzi, każde `*import_identity_account_id` równa się id przywróconego konta o tej nazwie, a każdy digest równa się `ImportIdentityDigest::compute()` z id użytkownika, nowym id konta, datą i kwotą tożsamości oraz surowym opisem nogi. Ruch kapitału z tożsamością dodaj w teście, nie w `createUserWithDomainData()`, bo fixture jest wspólny.
- `test_import_preview_flags_restored_operation_as_duplicate`: po restore jak wyżej `ImportDuplicateDetector::annotateRows()` dla wiersza z tą samą datą, kwotą i surowym opisem na przywróconym koncie zwraca `import_duplicate = true` (dla operacji i dla nogi przelewu). To zachowanie widzi użytkownik.
- Odmowy przez data provider, pełna macierz: 4 rodzaje rekordu (operacja, noga source, noga destination, ruch kapitału) × 4 braki przy obecnym digeście (brak surowego opisu, brak daty tożsamości, brak kwoty tożsamości, konto tożsamości spoza snapshotu), czyli 16 przypadków. Do tego po jednym przypadku na rodzaj rekordu: digestu brak, a konto tożsamości jest spoza snapshotu (dla operacji i przelewów to nowa odmowa, bo dziś id idzie surowo). Wynik każdego przypadku: `UserSnapshotException`, a dane domenowe użytkownika docelowego sprzed restore zostają (np. liczba operacji i nazwa konta).

Dla testów bezpośrednio na akcji użyj fixture z realnymi wejściami digestu. Sztuczne digesty mogą zostać, bo restore i tak liczy od nowa.

#### 2. Asercje istniejących testów

**File**: `tests/Feature/UserSnapshotCommandTest.php`

**Intent**: Zmieniło się wymaganie (ADR-0079), więc zmieniają się asercje. W `test_restore_action_preserves_domain_values_with_new_ids` (`:521`, `:524`, `:525`) „digest równy źródłowemu” staje się „digest równy przeliczonemu dla użytkownika docelowego”. Dochodzi też sprawdzenie, że `*import_identity_account_id` wskazuje konta użytkownika docelowego, a nie źródłowego. W `test_restore_action_treats_missing_import_identity_keys_as_null` (`:464`) dochodzi asercja, że noga source bez digestu ma zmapowane id konta.

**Contract**: Pozostałe asercje obu testów bez zmian, żadna nie jest usuwana.

#### 3. Klasa tożsamości importu przy restore

**File**: `app/Support/UserSnapshot/RestoredImportIdentity.php` (nowa)

**Intent**: Jedno miejsce, które dla jednej nogi tożsamości ze snapshotu zwraca pola do zapisu albo odmawia. Akcja nie rośnie i nie powtarza tej samej logiki trzy razy.

**Contract**: Wstrzykuje `App\Support\CsvImport\ImportIdentityDigest`. Jedna publiczna metoda, np. `forLeg(User $targetUser, array $accountIdMap, array $identity, ?string $rawStatementDescription, string $recordLabel): array{account_id: ?int, date: ?string, amount_minor_units: ?int, digest: ?string}`, gdzie `$identity` to cztery wartości nogi ze snapshotu (konto, data, kwota, digest). Reguły:
- digest pusty (`null` albo brak klucza): konto mapowane, jeśli jest (nieznane konto oznacza odmowę), data i kwota przepisane, digest `null`;
- digest jest: wymagane konto z mapy, data, kwota i surowy opis różny od `null` (ten sam warunek co w `ImportIdentityDigest::compute()`; pusty ciąg nie jest odmową). Wynik to zmapowane konto, ta sama data i kwota oraz `compute($targetUser->id, $mappedAccountId, $date, $amount, $raw)['digest']`;
- odmowa: `UserSnapshotException` z `$recordLabel` (np. „financial operation 17”, „own transfer 4 source”) i nazwą brakującego pola albo nieznanego id konta.

#### 4. Restore operacji, przelewów i ruchów kapitału

**File**: `app/Actions/RestoreUserData.php`

**Intent**: Trzy miejsca zapisu tożsamości przechodzą przez nową klasę: operacja (`:800`–`:803`), nogi source i destination przelewu (`:834`–`:841`, surowy opis nogi z tym samym fallbackiem co `:830`–`:831`) oraz ruch kapitału (`:867`–`:889`). Ten ostatni zastępuje obecne mapowanie, przy czym odmowa nieznanego konta zostaje.

**Contract**: Klasa wstrzyknięta w konstruktorze obok `ResetUserData`. Kolejność kroków w `handle()` bez zmian. Wersje snapshotu i `LIST_FORMAT_VERSIONS` bez zmian.

### Success Criteria:

#### Automated Verification:

- Nowe testy restore są czerwone na PostgreSQL przed poprawką (SQLSTATE 23503 albo asercja digestu), a wynik jest zapisany w raporcie fazy
- Testy snapshotu przechodzą na PostgreSQL po poprawce: polecenie worktree z AGENTS.md z `-e DB_DATABASE=testing_restore_import_identity_accounts` i `tests/Feature/UserSnapshotCommandTest.php`
- Testy importu przechodzą na PostgreSQL: `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing_restore_import_identity_accounts -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature --filter=Import`
- Bramka `composer ci:check` (polecenie worktree z AGENTS.md, `--entrypoint composer`, na `testing_restore_import_identity_accounts`) przechodzi
- `vendor/bin/pint --dirty --format agent` bez zmian po formatowaniu i `vendor/bin/sloppy ci` bez nowych znalezisk

#### Manual Verification:

- Przegląd diffu: żadna asercja nie została osłabiona poza trzema asercjami digestu z `:521`, `:524`, `:525`, a odmowy nie mają pustego `catch`

**Implementation Note**: Po weryfikacji automatycznej zatrzymaj się na potwierdzenie przeglądu przed fazą 3.

---

## Faza 3: README i próba na kopii produkcji

### Overview

Opisuje działającą drogę powrotu i dowodzi jej na prawdziwych danych: powtórzenie kroku 6a z `context/archive/2026-11-11-category-merge/merge-trial.md`, który wtedy padł.

### Changes Required:

#### 1. README, „Scalanie kategorii”

**File**: `README.md` (`:207`)

**Intent**: Droga (2) nie jest już ograniczona do pustej bazy: `user:restore {user} <plik>` działa także na użytkownika z danymi, przywraca tożsamość importu z nowymi id i liczy digest od nowa (ADR-0079), więc ponowny import wyciągu nadal oznacza duplikaty. Zdanie „jeszcze nie działa” i opis O1 znikają. Ostrzeżenie, że przywrócenie cofa wszystko wpisane po scaleniu i nadaje nowe id, zostaje.

**Contract**: Tylko ten akapit. Tabela poleceń (`:125`) już mówi „na istniejącego użytkownika”.

#### 2. Raport próby

**File**: `context/changes/restore-import-identity-accounts/restore-trial.md` (nowy)

**Intent**: Dowód w stylu `merge-trial.md`, bez danych osobowych i kwot pojedynczych operacji: data, commit, baza, kroki i wyniki.

**Contract**: Próba w bazie podglądu zmiany `preview_restore_import_identity_accounts` (`context/foundation/agents/worktree-preview.md`), nigdy w deweloperskiej, `testing`, `testing_restore_import_identity_accounts` ani `household_treasurer_e2e`. Serwer i `db:show` muszą pokazać tę bazę (`context/foundation/agents/worktree-preview.md`, pamięć „Preview DB override needs php -S”). Kroki:
1. `migrate`, użytkownik próby, `user:restore` snapshotu produkcji i liczby kontrolne „przed” (`app:control-numbers --output=`).
2. Zmiana danych w aplikacji (np. scalenie jednej pary albo nowa operacja), żeby baza miała dane różne od snapshotu.
3. `user:restore` tego samego snapshotu na tego samego użytkownika. Przechodzi, a `--compare=` do „przed” nie pokazuje różnic poza id.
4. Zapytanie dla wszystkich czterech nóg (`financial_operations`, `own_transfers.source_*`, `own_transfers.destination_*`, `capital_movements`): 0 rekordów, w których `*import_identity_account_id` wskazuje konto spoza użytkownika, a liczba rekordów z digestem w każdej nodze równa się tej ze snapshotu (np. 1269 operacji przy obecnym snapshocie; liczby nóg przelewu i ruchów kapitału odczytaj ze snapshotu przed restore).
5. Podgląd importu wyciągu z jednego miesiąca, który jest już w snapshocie: wiersze są oznaczone jako duplikaty i domyślnie odznaczone. Nie zatwierdzaj importu.
6. Sprzątanie: baza `preview_restore_import_identity_accounts` usunięta, serwer zatrzymany, `.env` worktree z powrotem bez nadpisanej bazy.

### Success Criteria:

#### Automated Verification:

- README nie mówi już, że `user:restore` na użytkownika z danymi nie działa: `grep -n "jeszcze nie działa" README.md` nic nie zwraca
- Raport próby istnieje: `test -f context/changes/restore-import-identity-accounts/restore-trial.md`

#### Manual Verification:

- `user:restore` snapshotu produkcji na użytkownika z danymi w bazie próby przechodzi, a liczby kontrolne zgadzają się z „przed”
- Żaden `*import_identity_account_id` w bazie próby nie wskazuje konta spoza użytkownika, a liczba rekordów z digestem w każdej z czterech nóg równa się tej ze snapshotu
- Podgląd importu wyciągu z miesiąca obecnego w snapshocie oznacza wiersze jako duplikaty
- Baza próby usunięta, a baza deweloperska nietknięta

**Implementation Note**: Kroki ręczne wykonuje użytkownik albo agent z jego snapshotem produkcji (plik poza repozytorium). Gotowość do wdrożenia i scalenia par na produkcji potwierdza użytkownik po tej fazie.

---

## Testing Strategy

### Unit Tests:

- Brak osobnych testów jednostkowych `RestoredImportIdentity`. Testy feature restore pokrywają każdą regułę (mapowanie, przeliczenie, noga bez digestu, odmowy) przez akcję, bo klasa nie ma użycia poza restore.

### Integration Tests:

- Restore własnego snapshotu na użytkownika z danymi: operacja, obie nogi przelewu, ruch kapitału (PostgreSQL, czerwony przed poprawką).
- Podgląd importu po restore oznacza duplikat (`ImportDuplicateDetector`).
- Odmowa dla każdego rodzaju rekordu: brak surowego opisu, daty, kwoty i nieznane konto przy digeście (16 przypadków) oraz nieznane konto bez digestu (4 przypadki). Dane użytkownika zostają.
- Istniejące testy wersji 1–10 (`asV6Snapshot` … `asV9Snapshot`, fixture'y `tests/Fixtures/snapshots/*.json`) przechodzą bez zmian.

### Manual Testing Steps:

1. Próba na kopii produkcji z fazy 3, kroki 1–6.

## Performance Considerations

Jeden SHA-256 na rekord z importu (~1300 przy produkcji) w transakcji restore. Bez nowych zapytań, a mapa kont jest już w pamięci.

## Migration Notes

Brak migracji i nowej wersji snapshotu. Snapshoty zapisane wcześniej (także przez akcję scalenia i `deploy.sh`) wczytują się nowym kodem z przeliczonym digestem. Wycofanie kodu przywraca stary błąd, ale nie psuje danych.

## References

- Roadmapa: `context/foundation/roadmap.md`, F-06
- Próba S-21, odstępstwo O1: `context/archive/2026-11-11-category-merge/merge-trial.md`
- ADR-0007 (tożsamość importu): `context/foundation/adr/0007-import-duplicate-fingerprint.md`
- ADR-0078 (scalenie i snapshot przed zapisem): `context/foundation/adr/0078-category-merge.md`
- Reguła: `.ai/rules/snapshot.md`
- Wzorzec mapowania: `app/Actions/RestoreUserData.php:867`
- Digest: `app/Support/CsvImport/ImportIdentityDigest.php:17`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzja w dokumentach

#### Automated

- [x] 1.1 ADR-0079 istnieje i nie ma drugiego pliku z numerem 0079
- [x] 1.2 Wpis „Import duplicate” w glosariuszu odwołuje się do ADR-0079
- [x] 1.3 Reguła snapshot opisuje mapowanie i przeliczanie tożsamości, indeks reguł bez zmian

#### Manual

- [x] 1.4 Użytkownik akceptuje treść ADR-0079

### Phase 2: Czerwony test → poprawka restore

#### Automated

- [ ] 2.1 Nowe testy restore czerwone na PostgreSQL przed poprawką, wynik zapisany
- [ ] 2.2 Testy snapshotu przechodzą na PostgreSQL po poprawce
- [ ] 2.3 Testy importu przechodzą na PostgreSQL
- [ ] 2.4 Bramka composer ci:check przechodzi
- [ ] 2.5 Pint bez zmian i Sloppy bez nowych znalezisk

#### Manual

- [ ] 2.6 Przegląd diffu: asercje osłabione tylko tam, gdzie zmieniło się wymaganie

### Phase 3: README i próba na kopii produkcji

#### Automated

- [ ] 3.1 README nie mówi, że restore na użytkownika z danymi nie działa
- [ ] 3.2 Raport próby istnieje

#### Manual

- [ ] 3.3 Restore snapshotu produkcji na użytkownika z danymi przechodzi, liczby kontrolne się zgadzają
- [ ] 3.4 Żadna tożsamość importu nie wskazuje konta spoza użytkownika, liczba digestów zgodna ze snapshotem w każdej nodze
- [ ] 3.5 Podgląd importu oznacza wiersze z miesiąca w snapshocie jako duplikaty
- [ ] 3.6 Baza próby usunięta, baza deweloperska nietknięta
