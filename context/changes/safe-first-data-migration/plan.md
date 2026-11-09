# Safe First Data Migration Implementation Plan

## Overview

Element przygotowawczy F-04 z `context/foundation/roadmap.md`, poprawki 1 i 2 z `context/foundation/health-check.md`. Wdrożenie robi pełną kopię instancji przed każdym `php artisan migrate --force`, dump bazy da się wczytać pod dowolną rolą (`--no-owner --no-privileges`), przywracanie produkcji jest opisane w README i ADR-0064 i raz sprawdzone na kopii z produkcji wczytanej lokalnie, a agent dostaje trzy reguły z `.ai/rules` (migracje danych, tożsamość kategorii, konwersja starszego snapshotu) zgodne z ADR-0063. Odblokowuje S-18 (pierwsza nieodwracalna migracja na danych produkcyjnych) i S-24 (przejście z flag na rytm etapami).

## Current State Analysis

- **Wdrożenie migruje bez kopii.** `scripts/deploy.sh:35-36` robi `migrate --force` zaraz po `up -d` (`:30`) i `chown` katalogu `storage` (`:33`). Jedyna kopia to `backup:run` z harmonogramu o 03:00 Europe/Warsaw (`bootstrap/app.php:30-35`), więc przywrócenie po nieudanej migracji cofa do doby zmian.
- **Katalog kopii z dwóch źródeł.** `deploy.sh:23-27` tworzy i `chown`uje `BACKUP_HOST_DIR` ze zmiennej powłoki z domyślną wartością, a Compose montuje `${BACKUP_HOST_DIR:-…}` z `--env-file .env.prod` (`deploy.sh:10`, `docker-compose.prod.yml:18`, `.env.prod.example:77`). Inna wartość w `.env.prod` bez eksportu w powłoce = skrypt przygotowuje zły katalog.
- **`exec` działa jako root.** `docker/8.5/Dockerfile.prod` nie ma `USER`, więc `$COMPOSE exec app …` biegnie jako root, a harmonogram (`schedule:work` w `docker/8.5/supervisord.prod.conf:23-29`) jako `sail`. Kopia uruchomiona jako root mogłaby zostawić katalogi `backup-temp` / `Household Treasurer` należące do roota.
- **Archiwum i retencja.** `config/backup.php`: nazwa `Household Treasurer` (`:25`, ADR-0059), dysk `backups` = `storage/app/backups` = `${BACKUP_HOST_DIR}` na hoście (`config/filesystems.php:63-68`), plik `YYYY-MM-DD-HH-ii-ss.zip` w UTC, w środku `db-dumps/postgresql-<DB_DATABASE>.sql`. Szyfrowanie hasłem `BACKUP_ARCHIVE_PASSWORD` (`:73-75`). Retencja (`:141-156`): 7 dni wszystko, potem 7 dni po jednym (najnowszym) archiwum dziennie. Archiwum samej bazy z wdrożenia wyparłoby więc pełne archiwum z 03:00 tego dnia.
- **Dump ma właściciela.** `vendor/spatie/db-dumper/src/Databases/PostgreSql.php:33-68` woła `pg_dump` bez `--no-owner`, więc dump zawiera `OWNER TO treasurer` (rola z `.env.prod.example:26`). Lokalnie rola to `sail`, a przywracanie działa z `ON_ERROR_STOP=on`, więc się przerwie. Spatie przekazuje dodatkowe opcje z `database.connections.pgsql.dump` (`vendor/spatie/laravel-backup/src/Tasks/Backup/DbDumperFactory.php:71-72,106-119`); `config/database.php:86-99` nie ma dziś klucza `dump`.
- **Przywracanie już istnieje.** `php artisan backup:restore {path} {--force}` (`app/Console/Commands/RestoreApplicationBackupCommand.php:11`, `app/Actions/RestoreApplicationBackup.php`) wypakowuje zip (z hasłem z konfiguracji), robi `DROP SCHEMA public CASCADE` / `CREATE SCHEMA public` (`:189-191`) i `psql --single-transaction --set=ON_ERROR_STOP=on` (`:201-217`), potem kopiuje pliki do `storage/app`. Testy (`tests/Feature/ApplicationBackupRestoreTest.php`) pokrywają tylko SQLite. `DROP SCHEMA` działa poza transakcją `psql`, więc nieudany `psql` zostawia pustą bazę.
- **Klient PostgreSQL w obrazie** to `postgresql-client-18` (`docker/8.5/Dockerfile.prod:82-83,100-101,118`), serwer `postgres:18-alpine` (`docker-compose.prod.yml:35`). Sail lokalnie ma też klienta 18 i `pgsql` na `postgres:18-alpine` (`compose.yaml:27-38`).
- **README o produkcji ma jedno zdanie** (`README.md:124-126`) i podaje Caddy, choć Compose używa etykiet Traefika. `backup:restore` jest w tabeli „Komendy administracyjne” (`README.md:111-122`). Brak instrukcji wdrożenia i przywracania.
- **Reguły z oceny stosu odstają od ADR-0063.** Teksty w `context/foundation/stack-assessment.md:111-167`:
  - mówią o „merge rows” i „per-Budget categories”, a ADR-0063 odrzuca scalanie duplikatów, a słownik zakazuje tego terminu (`glossary.md:37,41`; poprawnie: Budget Line);
  - niezmienniki są słabsze niż ADR-0063 krok (e): brak pozycji planu miesiąca, flag i `archived_at`, sumy per budżet zamiast kwot każdej Budget Line;
  - „`down()` rzuca wyjątek” jako jedyne wycofanie nie spełnia PRD v3 (`prd-v3.md:249`: wycofanie bez utraty operacji dodanych po wdrożeniu) i pomija liczby kontrolne (`prd-v3.md:248`);
  - „łączenie po nazwie tylko w migracji” pomija konwersję snapshotu v1–v5 i unikalność klucza nazwy (ADR-0063, „Names”, „Migration and snapshot”);
  - proponowany `grep -rn "'name'"` nie łapie 4 z 6 miejsc łączenia lat (`MonthPlanProposal.php:198`, `OperationsFilterOptions.php:44`, `ImportCategorySuggester.php:87`, `ImportCategorySuggestionModel.php:61`);
  - sekcja o rytmie zakłada, gdzie rytm będzie żył, a ADR-0063 zostawia to F-05; glob `categories.md` nie obejmuje plików czytających flagi.
- **Kod nadal łączy lata po nazwie i typie** w sześciu miejscach: `app/Actions/CreateBudget.php:30-47`, `app/Support/MonthPlanProposal.php:198`, `app/Support/OperationsListQuery.php:144-145`, `app/Support/OperationsFilterOptions.php:44`, `app/Support/CsvImport/ImportCategorySuggester.php:87,112,122-124`, `app/Support/CsvImport/ImportCategorySuggestionModel.php:61,133-160`, plus wejście filtra `app/Http/Requests/IndexOperationsRequest.php:29-30`. S-18 je zastępuje; do tego czasu reguła musi opisywać stan przejściowy, nie docelowy.
- **Jedyna migracja danych** `database/migrations/2026_09_16_091233_backfill_import_born_statement_text_omit_payment_instruments.php` woła `app(BackfillImportBornStatementTextOmit::class)` i ma pusty `down()`. Nowa reguła traktuje ją jako wyjątek sprzed reguły.
- **Indeks reguł jest generowany.** `php artisan boost:index-rules` przebudowuje `.ai/rules/index.md` z frontmattera `paths:` (`vendor/laravel/boost/src/Rules/RuleRepository.php:137-160`); ręczny wiersz zostanie nadpisany. Claude Code ładuje reguły przez dowiązanie `.claude/rules/project → ../../.ai/rules`.
- **Snapshot ma wersję 5** (`app/Actions/BackupUserData.php:18`); `RestoreUserData.php:79` akceptuje 1–5 i starszym wersjom tylko uzupełnia domyślne wartości. `.ai/rules/snapshot.md:12` mówi „give new fields defaults for older snapshots”.

## Desired End State

- Każde `scripts/deploy.sh` robi pełne `backup:run --disable-notifications` jako `sail` po `up -d` / `chown` i przed `migrate --force`. Nieudana kopia zatrzymuje wdrożenie.
- `deploy.sh` przygotowuje ten sam katalog kopii, który montuje Compose.
- Każdy dump PostgreSQL (z wdrożenia i nocny) powstaje z `--no-owner --no-privileges`; test to pilnuje.
- README ma sekcję „Wdrożenie i przywracanie”: kroki wdrożenia, gdzie leży kopia, przywrócenie produkcji (z cofnięciem kodu), próba lokalna w osobnej bazie i zapytania z liczbami kontrolnymi.
- ADR-0064 zapisuje decyzję: pełna kopia przed każdą migracją i dump bez właściciela, uzupełnia ADR-0013.
- `.ai/rules/migrations.md` i `.ai/rules/categories.md` istnieją, `snapshot.md` ma sekcję o konwersji. Teksty są zgodne z ADR-0063 i słownikiem, a `index.md` jest wygenerowany przez `boost:index-rules`.
- Próba przywracania kopii z wdrożenia F-04 w lokalnej bazie `restore_trial` przeszła, a liczby kontrolne zgadzają się z produkcją. Wynik jest zapisany w `context/changes/safe-first-data-migration/restore-trial.md`.

Weryfikacja: test dumpa przechodzi, `bash -n scripts/deploy.sh` jest czyste, bramka `composer ci:check` zielona, reguły ładują się przy edycji `database/migrations/*.php`, a próba z fazy 3 jest udokumentowana.

### Key Discoveries:

- `backup:restore` już jest i obsługuje PostgreSQL, więc instrukcja opiera się na nim, a nie na ręcznym `psql` (`app/Actions/RestoreApplicationBackup.php:180-217`).
- `pgsql.dump.add_extra_option` zamienia się na `addExtraOption()` w dumperze (`DbDumperFactory.php:106-119`), a `PostgreSql::getDumpCommand(string $dumpFile)` (`PostgreSql.php:34`) pozwala sprawdzić opcje testem bez uruchamiania `pg_dump`.
- `backup:run` zwraca kod błędu przy wyjątku, a `set -euo pipefail` w `deploy.sh:2` przerwie wtedy wdrożenie.
- Compose bierze wartość zmiennej z powłoki przed `--env-file`, więc `deploy.sh` musi rozwiązywać `BACKUP_HOST_DIR` w tej samej kolejności: powłoka, potem `.env.prod`, potem domyślna.
- `index.md` jest generowany; edytujemy frontmatter, nie indeks.

## What We're NOT Doing

- **Wzorca testu migracji na PostgreSQL** (poprawka 3, `tests/Feature/Migrations`) nie piszemy. Roadmapa zostawia go S-18, żeby powstał na prawdziwej migracji. Reguła `migrations.md` go tylko wymaga.
- **Nie zmieniamy `RestoreApplicationBackup`.** `DROP SCHEMA` poza transakcją `psql` zostaje; instrukcja mówi, że po nieudanym przywróceniu powtarza się je z tego samego archiwum. Jeśli próba z fazy 3 pokaże błąd w tej ścieżce, poprawka idzie osobną zmianą.
- **Brak testu automatycznego ścieżki PostgreSQL `backup:run` / `backup:restore`.** Runner CI ma klienta `pg_dump` 16, a serwer to 18; rolę tego testu pełni ręczna próba z fazy 3.
- **Nie dodajemy kopii warunkowej** (tylko przy oczekujących migracjach), wariantu `--only-db` ani osobnego dysku czy retencji dla kopii z wdrożenia (decyzja: zawsze pełna kopia).
- **Nie automatyzujemy wdrożenia w CI** ani shellcheck/bats dla skryptów (health-check: Category B).
- **Nie projektujemy wycofania S-18.** Reguła wymaga tylko, żeby plan migracji danych je nazwał. Tak samo liczby kontrolne zatrzymujące wdrożenie (PRD v3) należą do S-18.
- **Nie dodajemy `user:backup` każdego użytkownika przed migracją** (PRD v3:247). Należy do S-18 razem ze snapshotem po zmianie kształtu i jego konwersją. Kopia instancji z tej zmiany go nie zastępuje: przywrócenie z niej traci operacje dodane po wdrożeniu, a snapshot jest drogą wycofania z PRD v3:249.
- **Nie zmieniamy kodu łączącego lata po nazwie** (sześć miejsc), snapshotu ani flag `is_paced` / `month_item_kind`; to S-18, S-21 i S-24 / F-05.
- **Nie poprawiamy innych poprawek z health-check** (4–10) ani liczby ADR w `stack-assessment.md`.

## Implementation Approach

Najpierw siatka bezpieczeństwa, potem reguły, które się do niej odwołują, na końcu dowód na prawdziwych danych. Faza 1 zmienia konfigurację dumpa (z testem) i `deploy.sh` oraz zapisuje decyzję i instrukcję. Faza 2 tworzy reguły z linkami do sekcji README i ADR-0064 z fazy 1. Faza 3 jest ręczna: wdrożenie F-04 na produkcję samo tworzy archiwum przed migracją (już bez właściciela), które przywracamy lokalnie do osobnej bazy i porównujemy liczbami kontrolnymi.

## Critical Implementation Details

- **Kolejność w `deploy.sh`.** Kopia musi stać po `up -d` (kontener `app` działa) i po `chown` katalogu `storage` (`:33`), a przed `migrate --force`. Uruchamiaj ją jako `sail` (`exec -T -u sail`), tak jak harmonogram, żeby katalogi robocze kopii nie należały do roota. Nowy obraz robi dump starego schematu; to w porządku dla `pg_dump`.
- **Próba lokalna nie może nadpisać lokalnej bazy deweloperskiej.** `backup:restore` przywraca do domyślnego połączenia i robi `DROP SCHEMA public CASCADE`, więc próba działa na osobnej bazie `restore_trial` (`-e DB_DATABASE=restore_trial` na `exec`). Archiwum z produkcji jest zaszyfrowane, więc `BACKUP_ARCHIVE_PASSWORD` przekazuj tylko na czas polecenia (`-e`), bez zapisu w `.env`. `backup:restore` kopiuje też pliki z archiwum do lokalnego `storage/app` (`RestoreApplicationBackup.php:271-296`), a pełne archiwum z produkcji ma `private/` i `public/`. Przed próbą odłóż więc lokalne `storage/app/private` i `storage/app/public` (np. `cp -a` poza repo), a po próbie przywróć je w miejsce plików z produkcji. Po próbie usuń zip i bazę `restore_trial`.
  - `RestoreApplicationBackup` bierze bazę z konfiguracji (`:181,203-216`), nie z env, więc przy `bootstrap/cache/config.php` `-e DB_DATABASE=restore_trial` jest ignorowane, a `DROP SCHEMA` trafia w bazę deweloperską. Przed próbą `php artisan config:clear`, potem `php artisan db:show` z tymi samymi `-e` musi pokazać `restore_trial`; inaczej stop.
  - `restoreFiles` (`:271-296`) scala pliki przez `File::copy` i niczego nie usuwa, więc przed przywróceniem odłożonych katalogów `rm -rf storage/app/private storage/app/public`; bez tego pliki z produkcji (np. `private/user-snapshots/`) zostają lokalnie.
  - `storage/app/restore-trial` musi być w `exclude` kopii (`config/backup.php:29-37`), żeby lokalny `backup:run` w trakcie próby nie spakował archiwum z produkcji.
- **Przywrócenie produkcji wymaga też cofnięcia kodu.** Stary schemat z nowym kodem nie działa, a przywrócona tabela `migrations` nie ma nowej migracji. Kolejność w instrukcji: tryb serwisowy, `git revert` na `main` lokalnie i `git push origin main` (`production_pull_latest` robi `git pull --ff-only origin main`, `scripts/lib/production-deploy.sh:16-21`, więc revert zrobiony tylko na VPS rozjedzie się z origin i zablokuje następne wdrożenie), `backup:restore`, `scripts/deploy.sh` (zrobi kolejną kopię, a `migrate` nie ma nic do uruchomienia), wyjście z trybu serwisowego, `/up` i liczby kontrolne porównane z wynikiem zapisanym przed wdrożeniem (README każe go zebrać przed wdrożeniem zmiany z migracją danych).

## Phase 1: Kopia przed migracją

### Overview

Dump bez właściciela, pełna kopia przed migracją w `deploy.sh`, ADR-0064 i instrukcja w README.

### Changes Required:

#### 1. Dump PostgreSQL bez właściciela i uprawnień

**File**: `config/database.php`

**Intent**: Dump z produkcji ma się wczytać pod każdą rolą (lokalnie `sail`, na nowym serwerze dowolną). Dotyczy też kopii nocnych.

**Contract**: `connections.pgsql.dump.add_extra_option` = `'--no-owner --no-privileges'`. Spatie zamienia to na `PostgreSql::addExtraOption()`.

#### 1a. Katalog próby poza kopią

**File**: `config/backup.php`

**Intent**: Archiwum z produkcji leżące na czas próby w `storage/app/restore-trial/` nie trafia do lokalnej kopii.

**Contract**: `storage_path('app/restore-trial')` dopisane do `backup.source.files.exclude` obok `app/backups`, `app/backup-temp` i `app/framework`.

#### 2. Test opcji dumpa

**File**: `tests/Feature/ApplicationBackupRestoreTest.php`

**Intent**: Regresja: ktoś usuwa klucz `dump` i archiwa przestają się wczytywać pod inną rolą, a wychodzi to dopiero w awarii.

**Contract**: test budujący dumper przez `Spatie\Backup\Tasks\Backup\DbDumperFactory::createFromConnection('pgsql')` sprawdza, że `getDumpCommand(...)` zawiera `--no-owner` i `--no-privileges`. Nie uruchamia `pg_dump`, więc przechodzi na SQLite i PostgreSQL. Przed napisaniem przeczytaj skill `testing-best-practices`.

#### 3. Kopia przed migracją w skrypcie wdrożenia

**File**: `scripts/deploy.sh`

**Intent**: Po `up -d` i `chown` katalogu storage, a przed `Running migrations`, robi pełną kopię instancji, żeby nieudaną migrację cofnąć do stanu sprzed minuty, a nie sprzed doby. Katalog kopii ma być tym samym, który montuje Compose.

**Contract**:
- nowy krok `echo "==> Backing up the instance before migrations..."` i `$COMPOSE exec -T -u sail app php artisan backup:run --disable-notifications`, bez `--only-db`;
- nieudana kopia kończy skrypt przez `set -euo pipefail`; bez `|| true`;
- `BACKUP_HOST_DIR`: wartość z powłoki, jeśli ustawiona, inaczej wiersz `BACKUP_HOST_DIR=` z `.env.prod`, inaczej `/storage/household-treasurer-backups`. Wartość jest eksportowana, żeby Compose i `mkdir`/`chown` widziały to samo;
- `bash -n scripts/deploy.sh` przechodzi.

#### 4. ADR-0064

**File**: `context/foundation/adr/0064-instance-backup-before-every-production-migration.md`

**Intent**: Zapisuje decyzję i jej powody: pełna kopia przed każdym `migrate --force` (nie `--only-db`, bo przy retencji „najnowsze archiwum dnia” wyparłoby pełne archiwum z 03:00), kopia zawsze (nie tylko przy oczekujących migracjach), dump z `--no-owner --no-privileges`, przywracanie przez `backup:restore` według README. Zapisuje też, że kopia instancji nie zastępuje `user:backup` każdego użytkownika przed migracją danych (PRD v3:247); ten krok dodaje S-18. Uzupełnia ADR-0013, którego nie zmienia.

**Contract**: format i język jak ADR-0013 / ADR-0063 (tytuł jako zdanie, akapity, sekcja odrzuconych opcji: `--only-db`, kopia warunkowa, rola `treasurer` lokalnie zamiast `--no-owner`). Numer 0064 to następny wolny (ostatni jest 0063).

#### 5. Instrukcja wdrożenia i przywracania

**File**: `README.md`

**Intent**: Człowiek w awarii ma jedną stronę z krokami: gdzie jest kopia, jak przywrócić produkcję, jak sprawdzić, że się udało, i jak zrobić próbę lokalnie bez nadpisania bazy deweloperskiej.

**Contract**: sekcja `## Produkcja` dostaje poprawkę (Traefik zamiast Caddy) i podsekcję `### Wdrożenie i przywracanie` po polsku:
- kolejność kroków `scripts/deploy.sh` z kopią przed migracją;
- położenie archiwum: `${BACKUP_HOST_DIR}/Household Treasurer/RRRR-MM-DD-GG-MM-SS.zip` (czas UTC, cudzysłowy przez spację w nazwie), w kontenerze `/var/www/html/storage/app/backups/Household Treasurer/…`;
- kiedy przywracać z kopii przed migracją: awaria przy wdrożeniu albo zaraz po nim. Przywrócenie traci wszystko, co powstało po czasie archiwum, dlatego wycofanie po dniach pracy na nowym schemacie należy do planu danej migracji (link do `.ai/rules/migrations.md`);
- przywrócenie produkcji w kolejności z „Critical Implementation Details” (`scripts/artisan.sh down`, `git revert` lokalnie i `git push origin main`, `scripts/artisan.sh backup:restore "<ścieżka w kontenerze>" --force`, `scripts/deploy.sh`, `scripts/artisan.sh up`, `/up`); nieudany `backup:restore` zostawia pustą bazę, więc powtarza się go z tego samego archiwum;
- próba lokalna: kopia zip do `storage/app/restore-trial/` (ignorowane przez git, wyłączone z kopii), odłożenie lokalnych `storage/app/private` i `storage/app/public`, `createdb restore_trial` w kontenerze `pgsql`, `php artisan config:clear` i `docker compose exec -e DB_DATABASE=restore_trial laravel.test php artisan db:show` pokazujące `restore_trial` (inaczej stop), `docker compose exec -e DB_DATABASE=restore_trial -e BACKUP_ARCHIVE_PASSWORD=… laravel.test php artisan backup:restore <zip> --force`, porównanie liczb kontrolnych, `rm -rf storage/app/private storage/app/public` i przywrócenie odłożonych katalogów (restore scala pliki, niczego nie usuwa), usunięcie zip i bazy;
- archiwa sprzed ADR-0064 mają `OWNER TO treasurer`: do ich wczytania lokalnie najpierw `CREATE ROLE treasurer`;
- zapytania z liczbami kontrolnymi do uruchomienia po obu stronach:
  - liczba wierszy każdej tabeli domenowej: `users`, `budgets`, `categories`, `financial_operations`, `own_transfers`, `capital_movements`, `term_deposits`, `accounts`, `month_plans`, `month_plan_items`, oraz `migrations`;
  - per `budget_id`: suma `financial_operations.amount_minor_units`, sumy `categories.initial_plan_minor_units`, `planned_amount_minor_units` i `execution_minor_units`, sumy `month_plans.approved_set_aside_minor_units` / `current_set_aside_minor_units` i `month_plan_items.approved_amount_minor_units` / `current_amount_minor_units`;
  - per kategoria: `execution_minor_units` (PRD v3:248, wykonanie per kategoria);
  - sumy `own_transfers.amount_minor_units` i `capital_movements.amount_minor_units` (per `direction`);
  - `opening_balance_minor_units` i `current_balance_minor_units` per konto i per lokata (`accounts`, `term_deposits`);
  - `max(id)` w `financial_operations`;
- przed wdrożeniem zmiany z migracją danych uruchom te zapytania na produkcji i zapisz wynik poza serwerem; przywrócenie produkcji porównuje się z tym zapisem (automatyczny zapis i zatrzymanie wdrożenia przy różnicy to S-18).

Tabela „Komendy administracyjne” zostaje; wiersz `backup:restore` linkuje do nowej podsekcji.

### Success Criteria:

#### Automated Verification:

- Test opcji dumpa przechodzi na SQLite i na PostgreSQL: `php artisan test --compact tests/Feature/ApplicationBackupRestoreTest.php` (PostgreSQL: polecenie z AGENTS.md → Commands)
- Składnia skryptu jest poprawna: `bash -n scripts/deploy.sh`
- Kopia stoi przed migracją: `grep -n "backup:run\|migrate --force" scripts/deploy.sh` pokazuje `backup:run` (z `-u sail`, bez `--only-db`) w wierszu przed `migrate --force`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Pełna bramka przechodzi: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Lokalnie `sail artisan backup:run --only-db` tworzy archiwum, a `db-dumps/postgresql-*.sql` w środku nie zawiera `OWNER TO` ani `GRANT`
- README „Wdrożenie i przywracanie” i ADR-0064 przeczytane i zaakceptowane (kroki zrozumiałe bez tej rozmowy)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie, zanim przejdziesz do fazy 2.

---

## Phase 2: Reguły agenta

### Overview

Trzy reguły z oceny stosu, przepisane zgodnie z ADR-0063, słownikiem i decyzjami tego planu, plus wygenerowany indeks.

### Changes Required:

#### 1. Reguła migracji

**File**: `.ai/rules/migrations.md`

**Intent**: Agent piszący migrację danych używa query buildera, testuje na PostgreSQL, rozkłada zmianę na expand → backfill → drop i nie udaje wycofania, a jego plan nazywa wycofanie po wdrożeniu i liczby kontrolne.

**Contract**: frontmatter `paths:` = `'database/migrations/**/*.php'`, `'tests/Feature/Migrations/**'`. Sekcje (każda `## <zdanie z regułą>` + akapit, styl jak `.ai/rules/snapshot.md`):
- **query builder i PostgreSQL:** `DB::table()` i tablice, bez modeli Eloquent i `app/Actions`; migracja `2026_09_16_091233_backfill_import_born_statement_text_omit_payment_instruments.php` to wyjątek sprzed reguły. Test w `tests/Feature/Migrations/` seeduje stary kształt, uruchamia migrację i sprawdza niezmienniki, a dla migracji listy kategorii są to te z ADR-0063 krok (e): każda Budget Line zachowuje kwoty, flagi i `archived_at`, operacje i pozycje planu miesiąca wskazują tę samą kategorię listy w tym samym budżecie, każda kwota co do grosza. Uruchamiany na PostgreSQL (AGENTS.md → Commands), bo SQLite nic tu nie dowodzi;
- **expand → backfill → drop:** nowe tabele i kolumny w jednej migracji, wypełnienie w drugiej, usunięcie starych kolumn dopiero, gdy kod ich nie czyta, w późniejszej fazie. Przykład: przejście z `is_paced` / `month_item_kind` na rytm (F-05 / S-24) dodaje rytm obok flag, przełącza czytelników jeden obszar na fazę przy zielonych testach i usuwa flagi w ostatniej fazie; para kalkulatorów PHP/TS przełącza się w jednym commicie (`.ai/rules/month-calculators.md`);
- **dwa rodzaje wycofania:** `scripts/deploy.sh` robi pełną kopię przed każdym `migrate --force` (ADR-0064). Kopia chroni przed awarią przy wdrożeniu albo zaraz po nim (instrukcja: README → „Wdrożenie i przywracanie”), ale traci operacje dodane po wdrożeniu. Dlatego plan zmiany z migracją danych nazywa, jak ją wycofać po wdrożeniu bez utraty nowych operacji (PRD v3: migracja odwracalna albo przywrócenie ze snapshotu `user:backup` zrobionego przed migracją), i jakie liczby kontrolne porównuje przed i po. `down()` albo naprawdę odwraca migrację i ma test, albo rzuca `RuntimeException` ze wskazaniem kopii i instrukcji; nigdy nie udaje odwrócenia. Słowo „merge” nie opisuje migracji listy kategorii: ADR-0063 nie scala Budget Lines, a scalanie kategorii to S-21.

#### 2. Reguła kategorii

**File**: `.ai/rules/categories.md`

**Intent**: Agent ruszający kod kategorii wie, że decyzja ADR-0063 już zapadła, ale kod jeszcze jej nie wdraża: nie dodaje nowego łączenia lat po nazwie, nie myli archiwum listy z archiwum Budget Line i nie używa zakazanych terminów.

**Contract**: frontmatter `paths:` = `'app/Actions/*Budget*.php'`, `'app/Actions/*Categor*.php'`, `'app/Models/Category*.php'`, `'app/Support/MonthPlanProposal.php'`, `'app/Support/Operations*.php'`, `'app/Support/CsvImport/ImportCategory*.php'`, `'app/Http/Requests/*Categor*.php'`, `'app/Http/Requests/IndexOperationsRequest.php'`, `'app/Http/Controllers/CategoryController.php'`, `'app/Concerns/CategoryValidationRules.php'`. Sekcje:
- **tożsamość:** od ADR-0063 kategoria listy należy do Użytkownika przez wszystkie lata, a rekord roku to Budget Line (słownik; nie „per-Budget Category”). Do czasu S-18 kod łączy lata po nazwie i typie w sześciu miejscach (lista z „Current State Analysis” tego planu z `file:line`). Nowy kod nie dodaje siódmego, a S-18 zastępuje je łączeniem po id;
- **klucz nazwy:** porównanie po nazwie zostaje tylko w trzech miejscach: kroki migracji i konwersji snapshotu v1–v5 (ADR-0063, „Migration and snapshot”) oraz unikalność (klucz nazwy, typ) na liście, z archiwalnymi włącznie. Klucz nazwy = nazwa przycięta, bez rozróżniania wielkości liter (Unicode), znaki diakrytyczne się liczą;
- **dwa poziomy archiwum i plan roku:** archiwum kategorii listy decyduje tylko o wyborze (operacje, plan miesiąca, nowy budżet). Sumy budżetu, liczniki Dashboardu, sekcja archiwum budżetu i propozycja planu miesiąca czytają `archived_at` Budget Line. Initial Plan, Current Plan i Execution, a do F-05 także flagi, żyją na Budget Line.

Bez sekcji o rytmie (trafia do `migrations.md`, punkt expand → backfill → drop) i bez proponowanego `grep -rn "'name'"` (nie łapie 4 z 6 miejsc).

#### 3. Sekcja o konwersji starszego snapshotu

**File**: `.ai/rules/snapshot.md`

**Intent**: Gdy zmienia się kształt modelu, przywrócenie starszego snapshotu przechodzi tę samą konwersję co migracja danych, a nie tylko dostaje wartości domyślne.

**Contract**: nowa sekcja na końcu: `## An older snapshot is converted, not just defaulted`. Treść:
- przy zmianie kształtu modelu (S-18: Budget Lines z kategorią listy) restore v1–v5 przechodzi kroki ADR-0063 (a)–(f) i używa tych samych reguł co migracja, więc przywrócony snapshot równa się temu, co migracja zrobiłaby z tych samych danych;
- test w `tests/Feature/UserSnapshotCommandTest.php` ma fixture v5 z czterema przypadkami: ta sama nazwa w dwóch budżetach, ten sam klucz dwa razy w jednym budżecie („Rozrywka (2)”), kategoria tylko w starym roku (archiwalna), różnica wielkości liter lub spacji w nazwie;
- test pisze S-18.

Zdanie o domyślnych wartościach w istniejącej sekcji (`snapshot.md:12`) dostaje dopowiedzenie „for added fields; a changed shape is converted (below)”, żeby obie sekcje sobie nie przeczyły.

#### 4. Indeks reguł

**File**: `.ai/rules/index.md` (generowany)

**Intent**: Indeks zawiera dwie nowe reguły z ich globami.

**Contract**: `php artisan boost:index-rules`, bez ręcznej edycji `index.md`.

### Success Criteria:

#### Automated Verification:

- Indeks wygenerowany i zawiera obie reguły: `php artisan boost:index-rules` bez ostrzeżeń, potem `grep -c "migrations.md\|categories.md" .ai/rules/index.md` zwraca 2
- Każdy glob z `categories.md` trafia w istniejący plik: `ls` każdej ścieżki z frontmattera zwraca co najmniej jeden plik
- Reguły nie używają zakazanych terminów: `grep -rniE "per-Budget categor|merge rows|merges rows" .ai/rules/` nic nie zwraca
- Pełna bramka przechodzi: `composer ci:check`

#### Manual Verification:

- W nowej sesji Claude Code odczyt dowolnego pliku z `database/migrations/` ładuje treść `migrations.md`, a odczyt `app/Support/MonthPlanProposal.php` ładuje `categories.md`
- Treść trzech reguł przeczytana i zgodna z ADR-0063 oraz decyzjami tego planu

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie, zanim przejdziesz do fazy 3.

---

## Phase 3: Próba przywracania (ręczna)

### Overview

Wdrożenie F-04 na produkcję tworzy pierwsze archiwum przed migracją, już bez właściciela. Przywracamy je lokalnie do osobnej bazy według README, porównujemy liczby kontrolne z produkcją i zapisujemy wynik. Fazę wykonuje użytkownik (dostęp do VPS); agent przygotowuje polecenia i zapisuje wynik.

### Changes Required:

#### 1. Wdrożenie i archiwum

**File**: — (operacja na produkcji)

**Intent**: Po scaleniu fazy 1 i 2 do `main` uruchom `scripts/deploy.sh` na VPS i potwierdź krok „Backing up the instance before migrations…” oraz nowy zip w `${BACKUP_HOST_DIR}/Household Treasurer/`. Zaraz po wdrożeniu zbierz liczby kontrolne na produkcji (zapytania z README).

**Contract**: archiwum z czasem UTC tuż sprzed `migrate --force` tego wdrożenia; liczby kontrolne zebrane, zanim pojawi się nowa operacja.

#### 2. Przywrócenie lokalne i porównanie

**File**: — (lokalny Sail)

**Intent**: Skopiuj zip do projektu (plik ignorowany przez git, np. `storage/app/restore-trial/`) i wykonaj próbę lokalną z README w bazie `restore_trial`, zaczynając od `config:clear` i `db:show` potwierdzającego `restore_trial`. Porównaj liczby kontrolne, potem usuń `storage/app/private` i `storage/app/public` z plikami z produkcji, przywróć odłożone katalogi, usuń zip i bazę.

**Contract**: `backup:restore` kończy się sukcesem bez obejścia z rolą `treasurer`, a każda liczba kontrolna jest równa wartości z produkcji. Jeśli trzeba było poprawić instrukcję, poprawka trafia do README w tej fazie.

#### 3. Zapis wyniku

**File**: `context/changes/safe-first-data-migration/restore-trial.md`

**Intent**: Dowód dla S-18, że droga powrotu działa: data, nazwa archiwum, rozmiar, czas przywrócenia, tabela liczb kontrolnych (produkcja / lokalnie), ewentualne poprawki instrukcji. Bez danych osobowych i kwot pojedynczych operacji; sumy i liczby wierszy wystarczą.

**Contract**: plik markdown w folderze zmiany; README nie zmienia się, jeśli instrukcja zadziałała bez poprawek.

### Success Criteria:

#### Automated Verification:

- Wynik próby zapisany: `test -f context/changes/safe-first-data-migration/restore-trial.md`
- Po próbie nie zostało archiwum w repo: `git status --porcelain` nie pokazuje pliku `.zip`

#### Manual Verification:

- Wdrożenie F-04 na produkcji pokazało krok kopii przed migracją, a w katalogu kopii jest nowe archiwum
- `backup:restore` archiwum z produkcji do lokalnej bazy `restore_trial` zakończył się sukcesem bez tworzenia roli `treasurer`
- Wszystkie liczby kontrolne z README są równe na produkcji i w `restore_trial`
- Lokalna baza deweloperska nie została nadpisana (dane dev, np. budżet 2025, nadal są)
- Archiwum z produkcji, baza `restore_trial` i pliki z produkcji w `storage/app` usunięte po próbie, lokalne `private/` i `public/` przywrócone

---

## Testing Strategy

### Unit Tests:

- Brak nowych testów jednostkowych; logika to konfiguracja i skrypt.

### Integration Tests:

- Test opcji dumpa w `tests/Feature/ApplicationBackupRestoreTest.php`: `DbDumperFactory::createFromConnection('pgsql')->getDumpCommand(...)` zawiera `--no-owner` i `--no-privileges`. Działa bez serwera PostgreSQL i bez `pg_dump`.

### Manual Testing Steps:

1. Lokalnie `sail artisan backup:run --only-db`, rozpakuj archiwum i sprawdź, że dump nie ma `OWNER TO` ani `GRANT`.
2. Wdrożenie F-04 na produkcję i obserwacja kroku kopii.
3. Próba lokalna z README na archiwum z wdrożenia, porównanie liczb kontrolnych.
4. Nowa sesja agenta: odczyt pliku migracji i `MonthPlanProposal.php` ładuje nowe reguły.

## Performance Considerations

Pełna kopia wydłuża każde wdrożenie o czas `pg_dump` + zip `storage/app` (lokalnie ~14 MB) i dodaje jedno archiwum na wdrożenie do limitu 5000 MB w `config/backup.php:153`. Przy kilku wdrożeniach w tygodniu to pomijalne.

## Migration Notes

Zmiana nie migruje danych. Pierwsze archiwum bez właściciela powstaje przy wdrożeniu F-04 (faza 3). Starsze archiwa (nocne do dnia wdrożenia) mają `OWNER TO treasurer` i wczytują się lokalnie po `CREATE ROLE treasurer`; README to opisuje. Po 14 dniach retencja usunie ostatnie z nich.

## References

- Roadmap: `context/foundation/roadmap.md` → F-04
- Health-check: `context/foundation/health-check.md:150-179` (poprawki 1 i 2)
- Ocena stosu: `context/foundation/stack-assessment.md:111-167` (teksty źródłowe reguł)
- ADR-0063: `context/foundation/adr/0063-one-category-list-across-years.md`; ADR-0013: `context/foundation/adr/0013-application-backups-via-spatie.md`
- PRD v3: `context/foundation/prd-v3.md:244-252` (migracja danych, wycofanie, liczby kontrolne)
- Poprzednia zmiana: `context/archive/2026-11-09-category-list-domain-decisions/plan.md`
- Przywracanie: `app/Actions/RestoreApplicationBackup.php:180-217`; wywołanie produkcyjne: `scripts/artisan.sh`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kopia przed migracją

#### Automated

- [x] 1.1 Test opcji dumpa przechodzi na SQLite i na PostgreSQL — 9be0aff
- [x] 1.2 Składnia skryptu jest poprawna: `bash -n scripts/deploy.sh` — 9be0aff
- [x] 1.3 Kopia stoi przed migracją (`backup:run` z `-u sail`, bez `--only-db`, przed `migrate --force`) — 9be0aff
- [x] 1.4 Formatowanie PHP: `vendor/bin/pint --dirty --format agent` — 9be0aff
- [x] 1.5 Pełna bramka przechodzi: `composer ci:check` i `vendor/bin/sloppy ci` — 9be0aff

#### Manual

- [x] 1.6 Lokalny dump nie zawiera `OWNER TO` ani `GRANT` — 9be0aff
- [x] 1.7 README „Wdrożenie i przywracanie” i ADR-0064 przeczytane i zaakceptowane — 9be0aff

### Phase 2: Reguły agenta

#### Automated

- [x] 2.1 Indeks wygenerowany i zawiera obie reguły — 07c50a4
- [x] 2.2 Każdy glob z `categories.md` trafia w istniejący plik — 07c50a4
- [x] 2.3 Reguły nie używają zakazanych terminów — 07c50a4
- [x] 2.4 Pełna bramka przechodzi: `composer ci:check` — 07c50a4

#### Manual

- [x] 2.5 Nowa sesja ładuje `migrations.md` i `categories.md` przy odczycie pasujących plików — 07c50a4
- [x] 2.6 Treść trzech reguł zgodna z ADR-0063 i decyzjami planu — 07c50a4

### Phase 3: Próba przywracania (ręczna)

#### Automated

- [x] 3.1 Wynik próby zapisany w `restore-trial.md`
- [x] 3.2 Po próbie nie zostało archiwum w repo

#### Manual

- [x] 3.3 Wdrożenie F-04 pokazało krok kopii, a w katalogu kopii jest nowe archiwum
- [x] 3.4 `backup:restore` do `restore_trial` bez roli `treasurer` zakończył się sukcesem
- [x] 3.5 Liczby kontrolne równe na produkcji i w `restore_trial`
- [x] 3.6 Lokalna baza deweloperska nie została nadpisana
- [x] 3.7 Archiwum, baza `restore_trial` i pliki z produkcji usunięte, lokalne `private/` i `public/` przywrócone
