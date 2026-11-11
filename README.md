# Domowy Skarbnik

Aplikacja webowa do rocznego budżetu domowego: plan kategorii kontra rzeczywiste przepływy oraz płynność na kontach i lokatach. Interfejs jest po polsku. Waluta MVP: PLN.

Dwie płaszczyzny produktu:

- **Budżet** — plan początkowy, plan aktualny i wykonanie. Każda kategoria wydatkowa ma rytm (_Rhythm_): stała opłata, bieżąca, równomierna roczna albo nieregularna (stawka miesięczna i „zebrane”, które zużywa rachunek).
- **Płynność** — _Spendable Liquidity_ (salda kont) i _Total funds_ (te salda plus lokaty).

Konta logowania tworzy administrator. Samodzielna rejestracja jest wyłączona. Każdy użytkownik widzi wyłącznie własne dane.

## Stack

Laravel 13, PHP 8.5, PostgreSQL, Vue 3, Inertia 3, Tailwind CSS, Pest. Szczegóły: [`context/foundation/tech-stack.md`](context/foundation/tech-stack.md).

## Dokumentacja

Dokumentacja projektu leży w `context/` według konwencji 10x: `foundation/` to dokumenty żyjące ponad zmianami, `changes/` to zmiany w toku, `archive/` to zmiany zamknięte.

| Dokument                                                                     | Po co                                                           |
| ---------------------------------------------------------------------------- | --------------------------------------------------------------- |
| [`context/foundation/glossary.md`](context/foundation/glossary.md)           | Słownik domeny (język kodu i agentów)                           |
| [`context/foundation/prd-mvp.md`](context/foundation/prd-mvp.md)             | Wymagania produktu (MVP)                                        |
| [`context/foundation/mvp.md`](context/foundation/mvp.md)                     | Krótki zakres MVP                                               |
| [`context/foundation/prd.md`](context/foundation/prd.md), `prd-v2.md`        | PRD kolejnych zmian (plan miesiąca, raporty)                    |
| [`context/foundation/roadmap.md`](context/foundation/roadmap.md)             | Mapa drogowa                                                    |
| [`context/foundation/tech-stack.md`](context/foundation/tech-stack.md)       | Decyzje technologiczne                                          |
| [`context/foundation/adr/`](context/foundation/adr/)                         | Decyzje architektoniczne                                        |
| [`context/foundation/design-system.md`](context/foundation/design-system.md) | Design system (Atrament): tokeny, powierzchnie, typografia      |
| [`context/foundation/test-plan.md`](context/foundation/test-plan.md)         | Plan testów                                                     |
| [`AGENTS.md`](AGENTS.md)                                                     | Zasady pracy dla agentów AI; `.ai/rules/` ma reguły per ścieżka |

Słownik w `context/foundation/glossary.md` jest źródłem nazw. Unikaj synonimów, które ten plik wyklucza.

## Wymagania lokalne

- Docker (środowisko deweloperskie to [Laravel Sail](https://laravel.com/docs/sail) na WSL2)
- Git

PHP i Composer na hoście nie są potrzebne, jeśli Sail instalujesz przez obraz Dockera.

## Uruchomienie

```bash
cp .env.example .env
```

Z PHP i Composerem na hoście:

```bash
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
```

Bez PHP na hoście — najpierw zaciągnij zależności:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs
```

Dalej `./vendor/bin/sail up -d` i pozostałe komendy `sail` jak wyżej.

Aplikacja: <http://localhost> (port `APP_PORT`, domyślnie 80). Mailpit: <http://localhost:8025>.

Dev z HMR:

```bash
./vendor/bin/sail composer run dev
```

Jeśli UI nie odświeża się po zmianie frontendu, zostaw ten proces albo zbuduj assety: `./vendor/bin/sail npm run build`.

### Pierwszy użytkownik

```bash
./vendor/bin/sail artisan user:create "Imię" you@example.com --password=secret
```

Domyślnie e-mail jest od razu zweryfikowany. `--unverified` zostawia potwierdzenie na później.

Dane demonstracyjne:

```bash
./vendor/bin/sail artisan demo:seed-household you@example.com
```

Gdy użytkownik ma już dane domenowe, dodaj `--fresh` (i `--force`, żeby pominąć potwierdzenie).

Opcjonalnie, po `demo:seed-household`: budżet roku poprzedniego do porównań rok do roku.

```bash
./vendor/bin/sail artisan demo:seed-previous-year you@example.com
```

Polecenie działa tylko w środowiskach `local` i `testing`. Kwoty roku poprzedniego są pochodną operacji bieżącego roku (stały procent 85-115 na kategorię), nie prawdziwymi danymi; salda kont się nie zmieniają. `--remove` (z `--force` bez potwierdzenia) usuwa taki budżet. Baza `household_treasurer_e2e` dostaje mock raz, tym samym poleceniem.

## Testy i jakość

```bash
./vendor/bin/sail artisan test --compact
./vendor/bin/sail bin/pint --dirty --format agent
./vendor/bin/sail composer types:check
./vendor/bin/sail npm run types:check
./vendor/bin/sail bin/sloppy ci
```

Pełny zestaw lokalny: `./vendor/bin/sail composer ci:check`.

CI uruchamia dodatkowo [Sloppy](https://github.com/heyosseus/sloppy) (`vendor/bin/sloppy ci`) jako osobny krok: w PR zgłasza tylko znaleziska wprowadzone przez zmianę, na pushu skanuje cały projekt. Istniejący dług jest w `.sloppy-baseline.json`; odświeżaj go (`vendor/bin/sloppy baseline`) tylko świadomie, nie żeby uciszyć nowe znalezisko.

## Komendy administracyjne

| Komenda                          | Działanie                                                                                                                |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| `user:create`                    | Konto logowania                                                                                                          |
| `user:backup {user}`             | Snapshot JSON danych domenowych (wersja 9)                                                                               |
| `user:restore {user} {path}`     | Przywrócenie snapshotu na istniejącego użytkownika                                                                       |
| `user:reset-data {user}`         | Wyczyszczenie danych domenowych, konto logowania zostaje                                                                 |
| `demo:seed-household {user}`     | Dane demo                                                                                                                |
| `demo:seed-previous-year {user}` | Mock roku poprzedniego z bieżącego (tylko lokalnie; `--remove` usuwa)                                                    |
| `backup:restore {path}`          | Restore backupu całej instancji (Spatie), nie `user:restore`; zob. [Wdrożenie i przywracanie](#wdrożenie-i-przywracanie) |
| `app:control-numbers`            | Liczby kontrolne (zapis `--output`, porównanie `--compare`); zob. [Liczby kontrolne](#liczby-kontrolne)                  |

`{user}` to e-mail albo ID. Snapshoty lądują w `storage/app/private/user-snapshots/`.

## Produkcja

Własny Docker Compose na VPS (aplikacja + PostgreSQL + Traefik jako reverse proxy + worker kolejki). Backupy instancji: Spatie Laravel Backup. Nie używaj obrazu Sail na produkcji. Zobacz [`context/foundation/tech-stack.md`](context/foundation/tech-stack.md).

### Wdrożenie i przywracanie

Decyzja: [ADR-0064](context/foundation/adr/0064-instance-backup-before-every-production-migration.md) (uzupełnia ADR-0013).

#### Kroki `scripts/deploy.sh`

1. `git pull --ff-only origin main` i budowa obrazu `app`.
2. Przygotowanie katalogu kopii `BACKUP_HOST_DIR` (wartość z powłoki, inaczej z `.env.prod`, inaczej `/storage/household-treasurer-backups`).
3. **Tryb serwisowy i snapshot każdego użytkownika w starym kontenerze**, jeszcze przed `up -d`: lista id z tabeli `users` (z `psql`), `artisan down`, potem `user:backup` dla każdego id (log: „Snapshotted N user(s).”). Plik trybu serwisowego leży w `storage` (bind-mount), więc przetrwa `up -d`, a nowy kod nie obsłuży żądania na starym schemacie. Błąd `psql` albo `docker compose` przerywa wdrożenie. Krok pomija tylko pierwsze wdrożenie, gdy kontenera `app` w ogóle nie ma (wtedy `artisan down` idzie zaraz po `up -d`). Kontener zatrzymany zatrzymuje skrypt: uruchom go (`docker compose --env-file .env.prod -f docker-compose.prod.yml start app`) i powtórz; gdy nie da się go uruchomić, `DEPLOY_WITHOUT_USER_SNAPSHOTS=1 scripts/deploy.sh` wdraża bez snapshotów, z samą kopią instancji z kroku 6.
4. `up -d` i naprawa uprawnień `storage`.
5. **Liczby kontrolne „przed”** (nowy kod na starym schemacie) do `storage/app/private/control-numbers/<RRRRMMDD-GGMMSS>-before.json` (czas UTC).
6. **Pełna kopia instancji** (`backup:run`, baza i `storage/app`) przed migracją. Błąd kopii przerywa wdrożenie.
7. `migrate --force`.
8. **Liczby kontrolne „po”** (`…-after.json`) z `--compare` na plik „przed”. Różnica kończy skrypt błędem, a aplikacja **zostaje w trybie serwisowym** (zob. „Migracja przerwana w trakcie” niżej).
9. `artisan up`, potem cache konfiguracji, tras i widoków.

Skrypt aktualizuje sam siebie w kroku 1, więc pierwsze wdrożenie po zmianie `deploy.sh` biegnie jeszcze starą wersją. Dlatego przed `scripts/deploy.sh` zawsze zrób na serwerze `git pull --ff-only origin main` (niżej: pierwsze wdrożenie S-18). Jeśli w logu brakuje „Snapshotting every user…” albo „Recording control numbers…”, wdrożenie poszło starym skryptem bez bramek.

#### Pierwsze wdrożenie S-18 (lista kategorii i pozycje budżetu)

Migracje S-18 dzielą dawne `categories` na listę użytkownika i pozycje budżetu (`budget_lines`) bez zmiany żadnej kwoty. Wykonaj je w tej kolejności:

1. Zmiana jest na `main`, a próba na kopii produkcji zatwierdzona (`context/archive/2026-11-09-category-list-after-migration/migration-trial.md`).
2. Na serwerze: `git pull --ff-only origin main`. Dopiero potem skrypt. Bez tego migracja przejdzie starym `deploy.sh` bez snapshotów, trybu serwisowego i liczb kontrolnych.
3. `scripts/deploy.sh`. Log musi pokazać kolejno: tryb serwisowy, snapshoty użytkowników („Snapshotted N user(s).” z N równym liczbie użytkowników), liczby „przed”, kopię instancji, migracje, „Control numbers match” i wyjście z trybu serwisowego.
4. Sprawdź `/up`, Budżet 2026, plan miesiąca i widok „Kategorie”. Zachowaj plik `…-before.json` poza serwerem: jest wzorcem do porównań przy wycofaniu.

Snapshoty sprzed migracji (wersja 5) leżą w `storage/app/private/user-snapshots/`; są zapasem, gdyby wycofanie nie wystarczyło.

#### Gdzie leży kopia

Na hoście: `${BACKUP_HOST_DIR}/Household Treasurer/RRRR-MM-DD-GG-MM-SS.zip` (czas UTC, w nazwie katalogu jest spacja, więc ścieżkę podawaj w cudzysłowie). W kontenerze: `/var/www/html/storage/app/backups/Household Treasurer/…`. Archiwum jest szyfrowane hasłem `BACKUP_ARCHIVE_PASSWORD`, o ile jest ustawione; przy pustej zmiennej zip jest jawny (`config/backup.php`).

#### Kiedy przywracać z kopii przed migracją

Gdy wdrożenie się nie powiodło albo coś psuje się zaraz po nim. Przywrócenie traci wszystko, co powstało po czasie archiwum. Wycofanie po dniach pracy na nowym schemacie nie jest zadaniem tej kopii: należy do planu danej migracji (zob. `.ai/rules/migrations.md`).

#### Przywrócenie produkcji

Sprawdzone lokalnie: archiwum z produkcji wczytało się do osobnej bazy, a liczby kontrolne się zgodziły ([próba F-04](context/archive/2026-11-09-safe-first-data-migration/restore-trial.md)). Kroki 3–4 przeszły w próbie S-18 na bazie testowej po migracji: powrót z kopii sprzed migracji dał liczby kontrolne bez różnic ([próba S-18](context/archive/2026-11-09-category-list-after-migration/migration-trial.md)). Kroków 1, 2, 5 i 6 nie da się przećwiczyć lokalnie, bo dzieją się na serwerze.

1. `scripts/artisan.sh down` (tryb serwisowy).
2. Lokalnie na `main`: `git revert` zmiany z migracją i `git push origin main`. Skrypt wdrożenia robi `git pull --ff-only origin main`, więc revert zrobiony tylko na VPS rozjedzie się z origin i zablokuje następne wdrożenie. Stary schemat nie działa z nowym kodem, więc kod musi wrócić razem z bazą. Na serwerze jeszcze nie rób `git pull`: kontener ma nadal nowy kod, a on zna `app:control-numbers`.
3. `scripts/artisan.sh backup:restore "/var/www/html/storage/app/backups/Household Treasurer/RRRR-MM-DD-GG-MM-SS.zip" --force`. Nieudany `backup:restore` zostawia pustą bazę (`DROP SCHEMA` jest poza transakcją `psql`), więc powtórz go z tego samego archiwum.
4. `scripts/artisan.sh app:control-numbers --compare=storage/app/private/control-numbers/<znacznik>-before.json`: ma wyjść bez różnic (plik „przed” z wdrożenia leży w `storage` na hoście, restore niczego tam nie usuwa). Różnica oznacza STOP.
5. Na serwerze `git pull --ff-only origin main`, potem `scripts/deploy.sh` (zrobi kolejną kopię, `migrate` nie ma nic do uruchomienia: przywrócona tabela `migrations` nie zna nowej migracji). `git pull` przed skryptem jest konieczny: bez niego pobiegnie jeszcze `deploy.sh` z nowego kodu i `set -e` przerwie go na `app:control-numbers`, którego cofnięty kod nie zna.
6. `scripts/artisan.sh up`, sprawdź `/up`.

#### Wycofanie S-18 po wdrożeniu (bez utraty operacji)

Dla dni pracy na nowym schemacie przywrócenie kopii nie wchodzi w grę (traci wszystko po czasie archiwum). Migracje S-18 mają prawdziwy `down()` (test na PostgreSQL), więc wycofanie idzie przez `migrate:rollback` **nowym kodem**:

1. `scripts/artisan.sh down` (jeśli aplikacja nie jest już w trybie serwisowym).
2. `scripts/artisan.sh migrate:rollback --force`. Ostatnia partia to trzy migracje S-18; wyjście ma wymienić dokładnie: `drop_legacy_category_columns_from_budget_lines`, `backfill_category_list` i `expand_categories_into_category_list_and_budget_lines`. Nowa kategoria i operacja dodane po wdrożeniu zostają: pozycje stają się zwykłymi rekordami swoich budżetów, nazwy wracają z pisownią listy i sufiksem „(k)” (np. „Rozrywka (2)”). Nie przeżywa tylko to, co nie wisi na żadnej pozycji: kategoria listy bez pozycji (np. utworzona po wdrożeniu i zarchiwizowana, bo archiwum zdjęło jej pozycje ze szkiców) znika, a archiwum samej listy wraca do stanu pozycji (pozycja aktywna = rekord aktywny). Operacje i kwoty się nie zmieniają.
3. Jeśli od wdrożenia nic nie dopisano: `scripts/artisan.sh app:control-numbers --compare=storage/app/private/control-numbers/<znacznik>-before.json` ma dać zero różnic. Po dopisanych operacjach różnią się tylko ich sumy i liczniki.
4. Lokalnie na `main`: `git revert` zmian S-18 i `git push origin main`.
5. Na serwerze: `git pull --ff-only origin main`, dopiero potem `scripts/deploy.sh` (jak przy pierwszym wdrożeniu, bo skrypt aktualizuje się sam).
6. `scripts/artisan.sh up`, sprawdź `/up`.

Kolejność jest istotna. Cofnięcie kodu przed `migrate:rollback` usuwa pliki migracji, a właśnie ich `down()` jest potrzebny. Pominięcie `git pull` przed skryptem uruchamia jeszcze `deploy.sh` z S-18: jego `user:backup` w kontenerze S-18 nie znajdzie `budget_lines`, a `app:control-numbers` nie ma w cofniętym kodzie, więc `set -e` przerwie wdrożenie.

Snapshot wersji 6, zrobiony po wdrożeniu, nie wczyta się w starym kodzie (`user:restore` zna wtedy wersje 1–5). Drogą powrotu jest `down()`; snapshoty wersji 5 sprzed migracji są zapasem, a kopia instancji z kroku 6 `deploy.sh` ostatecznością (tracą zmiany po wdrożeniu).

#### Wycofanie S-26 po wdrożeniu

Migracja S-26 dodaje jedną kolumnę (`category_rhythms.opening_collected_minor_units`) i nie zmienia żadnego wiersza, więc liczby kontrolne przed i po `migrate` są identyczne. Jej `down()` jest stratny: zamienia wpisy rytmu `irregular` na `even_annual` i usuwa kolumnę, więc giną zebrane startowe i oznaczenia nieregularnych; operacje, plany i kwoty zostają. Wycofanie idzie jak w „Wycofanie S-18”: `scripts/artisan.sh migrate:rollback --force` nowym kodem, potem `git revert` i `git pull` przed `scripts/deploy.sh`. Snapshot wersji 10 (`user:backup` po S-26) nie wczyta się w kodzie sprzed zmiany; drogą powrotu jest `down()`, nie restore nowego snapshotu w starym kodzie.

#### Scalanie kategorii

Decyzja: [ADR-0076](context/foundation/adr/0076-category-merge.md). Scalenie („Kategorie” → „⋯” → „Scal z inną…”) nie ma migracji, więc liczby kontrolne przed i po wdrożeniu są identyczne. W aplikacji nie da się go cofnąć, ale akcja zapisuje snapshot użytkownika w `storage/app/private/user-snapshots/<id>-<RRRRMMDDGGMMSS>.json` **przed** pierwszym zapisem i niczego nie scala, gdy snapshot się nie uda. Ścieżka pliku jest w logu aplikacji (`storage/logs`, wpis o scaleniu kategorii z id i nazwami obu kategorii). Powrót: `scripts/artisan.sh user:restore {user} storage/app/private/user-snapshots/<plik>.json`. Przywrócenie cofa także wszystko, co wpisano po scaleniu, i nadaje rekordom nowe id, więc rób je od razu po pomyłce.

#### Migracja przerwana w trakcie

Każda migracja ma własną transakcję. Gdy asercja w `backfill_category_list` rzuci wyjątek (np. operacja wskazuje pozycję innego budżetu), jej zmiany się cofają, ale `expand_categories_into_category_list_and_budget_lines` jest już zapisany w partii. `deploy.sh` kończy się błędem, a aplikacja **zostaje w trybie serwisowym** (to samo, gdy liczby „po” różnią się od „przed”). Nie uruchamiaj skryptu ponownie. Zrób:

1. `scripts/artisan.sh migrate:rollback --force`: cofa tylko zapisane migracje partii (kod S-18 nadal jest w kontenerze, więc ich `down()` istnieje).
2. `scripts/artisan.sh app:control-numbers --compare=storage/app/private/control-numbers/<znacznik>-before.json`: aplikacja stała w trybie serwisowym, więc ma wyjść bez różnic. Różnica oznacza STOP i przywrócenie kopii instancji (zob. „Przywrócenie produkcji”).
3. Cofnięcie kodu jak w „Wycofanie S-18”, kroki 4–6.

#### Próba lokalna (bez nadpisania bazy deweloperskiej)

`backup:restore` robi `DROP SCHEMA public CASCADE` w bazie z konfiguracji, więc próba idzie do osobnej bazy `restore_trial`.

1. Skopiuj archiwum do `storage/app/restore-trial/` (katalog jest ignorowany przez git i wyłączony z kopii).
2. Odłóż lokalne pliki, bo restore kopiuje do `storage/app` pliki z archiwum: `cp -a storage/app/private storage/app/public` do katalogu poza repozytorium.
3. `docker compose exec pgsql createdb -U sail restore_trial`.
4. `docker compose exec laravel.test php artisan config:clear`, potem `docker compose exec -e DB_DATABASE=restore_trial laravel.test php artisan db:show`. **Polecenie musi pokazać `restore_trial`; jeśli nie, STOP.** Restore bierze bazę z konfiguracji, a przy `bootstrap/cache/config.php` zmienna `DB_DATABASE` jest ignorowana i `DROP SCHEMA` trafiłby w bazę deweloperską.
5. `docker compose exec -e DB_DATABASE=restore_trial -e BACKUP_ARCHIVE_PASSWORD=… laravel.test php artisan backup:restore storage/app/restore-trial/<plik>.zip --force` (hasło tylko na czas polecenia, nie zapisuj go w `.env`).
6. Zapisz liczby kontrolne w `restore_trial` (zob. „Liczby kontrolne”), a po migracji i po `migrate:rollback` porównaj je z zapisanymi.
7. Sprzątanie: `rm -rf storage/app/private storage/app/public` (restore scala pliki i niczego nie usuwa), przywróć odłożone katalogi, usuń zip z `storage/app/restore-trial/` i bazę: `docker compose exec pgsql dropdb -U sail restore_trial`.

Archiwa sprzed ADR-0064 zawierają `OWNER TO treasurer`: do wczytania lokalnie najpierw `CREATE ROLE treasurer;` w serwerze PostgreSQL.

#### Liczby kontrolne

Komenda `app:control-numbers` zastępuje dawne ręczne zapytania SQL. Czyta bazę przez `DB::table()` (bez modeli), więc działa na starym schemacie (`categories` z kwotami budżetu) i na nowym (`budget_lines`) i pod tymi samymi kluczami raportuje pozycje budżetu (stare `categories` = nowe `budget_lines`, to samo id). Nowa lista kategorii użytkownika trafia do sekcji `meta` i jest pomijana w porównaniu: to jedyna różnica, jaką migracja może wprowadzić.

Zawartość: liczba wierszy tabel, największe id operacji, per budżet suma operacji, Initial Plan, Current Plan, wykonanie, odkładania planów miesiąca i kwoty ich pozycji; per pozycja budżetu (id) plan, wykonanie i suma operacji; suma przelewów własnych, ruchy kapitału per kierunek, salda otwarcia i bieżące kont i lokat. Tabele, których nie ma w schemacie, są pomijane.

```bash
# wyświetl i zapisz
scripts/artisan.sh app:control-numbers --output=storage/app/private/control-numbers/reczne-before.json
# porównaj z wcześniejszym plikiem; kod wyjścia 1 przy dowolnej różnicy
scripts/artisan.sh app:control-numbers --compare=storage/app/private/control-numbers/reczne-before.json
```

Ścieżki względne liczą się od katalogu głównego aplikacji. `deploy.sh` robi to automatycznie: pliki `<RRRRMMDD-GGMMSS>-before.json` i `-after.json` leżą w `storage/app/private/control-numbers/`. Po wdrożeniu zachowaj kopię pliku „przed” poza serwerem.

Lokalnie, w `restore_trial` (po `config:clear` i sprawdzeniu `db:show`, jak w próbie lokalnej): `docker compose exec -e DB_DATABASE=restore_trial laravel.test php artisan app:control-numbers --output=storage/app/restore-trial/before.json`, a po migracji i po `migrate:rollback` to samo z `--compare=storage/app/restore-trial/before.json`. Komendy nie ma na `main` sprzed S-18, więc próba biegnie na gałęzi S-18.
