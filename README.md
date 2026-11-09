# Domowy Skarbnik

Aplikacja webowa do rocznego budżetu domowego: plan kategorii kontra rzeczywiste przepływy oraz płynność na kontach i lokatach. Interfejs jest po polsku. Waluta MVP: PLN.

Dwie płaszczyzny produktu:

- **Budżet** — plan początkowy, plan aktualny i wykonanie. Kategorie wydatkowe są równomierne (_Paced_) albo jednorazowe (_One-off_).
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

| Komenda                      | Działanie                                                                                                                |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| `user:create`                | Konto logowania                                                                                                          |
| `user:backup {user}`         | Snapshot JSON danych domenowych (wersja 5)                                                                               |
| `user:restore {user} {path}` | Przywrócenie snapshotu na istniejącego użytkownika                                                                       |
| `user:reset-data {user}`     | Wyczyszczenie danych domenowych, konto logowania zostaje                                                                 |
| `demo:seed-household {user}` | Dane demo                                                                                                                |
| `backup:restore {path}`      | Restore backupu całej instancji (Spatie), nie `user:restore`; zob. [Wdrożenie i przywracanie](#wdrożenie-i-przywracanie) |

`{user}` to e-mail albo ID. Snapshoty lądują w `storage/app/private/user-snapshots/`.

## Produkcja

Własny Docker Compose na VPS (aplikacja + PostgreSQL + Traefik jako reverse proxy + worker kolejki). Backupy instancji: Spatie Laravel Backup. Nie używaj obrazu Sail na produkcji. Zobacz [`context/foundation/tech-stack.md`](context/foundation/tech-stack.md).

### Wdrożenie i przywracanie

Decyzja: [ADR-0064](context/foundation/adr/0064-instance-backup-before-every-production-migration.md) (uzupełnia ADR-0013).

#### Kroki `scripts/deploy.sh`

1. `git pull --ff-only origin main` i budowa obrazu `app`.
2. Przygotowanie katalogu kopii `BACKUP_HOST_DIR` (wartość z powłoki, inaczej z `.env.prod`, inaczej `/storage/household-treasurer-backups`).
3. `up -d` i naprawa uprawnień `storage`.
4. **Pełna kopia instancji** (`backup:run`, baza i `storage/app`) przed migracją. Błąd kopii przerywa wdrożenie.
5. `migrate --force`, potem cache konfiguracji, tras i widoków.

#### Gdzie leży kopia

Na hoście: `${BACKUP_HOST_DIR}/Household Treasurer/RRRR-MM-DD-GG-MM-SS.zip` (czas UTC, w nazwie katalogu jest spacja, więc ścieżkę podawaj w cudzysłowie). W kontenerze: `/var/www/html/storage/app/backups/Household Treasurer/…`. Archiwum jest szyfrowane hasłem `BACKUP_ARCHIVE_PASSWORD`.

#### Kiedy przywracać z kopii przed migracją

Gdy wdrożenie się nie powiodło albo coś psuje się zaraz po nim. Przywrócenie traci wszystko, co powstało po czasie archiwum. Wycofanie po dniach pracy na nowym schemacie nie jest zadaniem tej kopii: należy do planu danej migracji (zob. `.ai/rules/migrations.md`).

#### Przywrócenie produkcji

1. `scripts/artisan.sh down` (tryb serwisowy).
2. Lokalnie na `main`: `git revert` zmiany z migracją i `git push origin main`. Skrypt wdrożenia robi `git pull --ff-only origin main`, więc revert zrobiony tylko na VPS rozjedzie się z origin i zablokuje następne wdrożenie. Stary schemat nie działa z nowym kodem, więc kod musi wrócić razem z bazą.
3. `scripts/artisan.sh backup:restore "/var/www/html/storage/app/backups/Household Treasurer/RRRR-MM-DD-GG-MM-SS.zip" --force`. Nieudany `backup:restore` zostawia pustą bazę (`DROP SCHEMA` jest poza transakcją `psql`), więc powtórz go z tego samego archiwum.
4. `scripts/deploy.sh` (zrobi kolejną kopię, `migrate` nie ma nic do uruchomienia: przywrócona tabela `migrations` nie zna nowej migracji).
5. `scripts/artisan.sh up`, sprawdź `/up`.
6. Uruchom liczby kontrolne (niżej) i porównaj z wynikiem zapisanym przed wdrożeniem.

#### Próba lokalna (bez nadpisania bazy deweloperskiej)

`backup:restore` robi `DROP SCHEMA public CASCADE` w bazie z konfiguracji, więc próba idzie do osobnej bazy `restore_trial`.

1. Skopiuj archiwum do `storage/app/restore-trial/` (katalog jest ignorowany przez git i wyłączony z kopii).
2. Odłóż lokalne pliki, bo restore kopiuje do `storage/app` pliki z archiwum: `cp -a storage/app/private storage/app/public` do katalogu poza repozytorium.
3. `docker compose exec pgsql createdb -U sail restore_trial`.
4. `docker compose exec laravel.test php artisan config:clear`, potem `docker compose exec -e DB_DATABASE=restore_trial laravel.test php artisan db:show`. **Polecenie musi pokazać `restore_trial`; jeśli nie, STOP.** Restore bierze bazę z konfiguracji, a przy `bootstrap/cache/config.php` zmienna `DB_DATABASE` jest ignorowana i `DROP SCHEMA` trafiłby w bazę deweloperską.
5. `docker compose exec -e DB_DATABASE=restore_trial -e BACKUP_ARCHIVE_PASSWORD=… laravel.test php artisan backup:restore storage/app/restore-trial/<plik>.zip --force` (hasło tylko na czas polecenia, nie zapisuj go w `.env`).
6. Uruchom liczby kontrolne w `restore_trial` i porównaj z produkcją.
7. Sprzątanie: `rm -rf storage/app/private storage/app/public` (restore scala pliki i niczego nie usuwa), przywróć odłożone katalogi, usuń zip z `storage/app/restore-trial/` i bazę: `docker compose exec pgsql dropdb -U sail restore_trial`.

Archiwa sprzed ADR-0064 zawierają `OWNER TO treasurer`: do wczytania lokalnie najpierw `CREATE ROLE treasurer;` w serwerze PostgreSQL.

#### Liczby kontrolne

Uruchom przed wdrożeniem zmiany z migracją danych na produkcji i zapisz wynik poza serwerem; po przywróceniu (produkcja albo `restore_trial`) uruchom te same zapytania i porównaj. Automatyczny zapis i zatrzymanie wdrożenia przy różnicy to zadanie S-18.

```bash
# produkcja: docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T pgsql psql -U <DB_USERNAME> -d <DB_DATABASE>
# lokalnie:  docker compose exec -T pgsql psql -U sail -d restore_trial
```

```sql
-- 1. Liczba wierszy tabel
SELECT 'users' AS tabela, count(*) AS wiersze FROM users
UNION ALL SELECT 'budgets', count(*) FROM budgets
UNION ALL SELECT 'categories', count(*) FROM categories
UNION ALL SELECT 'financial_operations', count(*) FROM financial_operations
UNION ALL SELECT 'own_transfers', count(*) FROM own_transfers
UNION ALL SELECT 'capital_movements', count(*) FROM capital_movements
UNION ALL SELECT 'term_deposits', count(*) FROM term_deposits
UNION ALL SELECT 'accounts', count(*) FROM accounts
UNION ALL SELECT 'month_plans', count(*) FROM month_plans
UNION ALL SELECT 'month_plan_items', count(*) FROM month_plan_items
UNION ALL SELECT 'migrations', count(*) FROM migrations
ORDER BY 1;

-- 2. Największe id operacji
SELECT max(id) AS max_financial_operation_id FROM financial_operations;

-- 3. Sumy per budżet
SELECT b.id AS budget_id,
       (SELECT coalesce(sum(amount_minor_units), 0) FROM financial_operations o WHERE o.budget_id = b.id) AS operations,
       (SELECT coalesce(sum(initial_plan_minor_units), 0) FROM categories c WHERE c.budget_id = b.id) AS initial_plan,
       (SELECT coalesce(sum(planned_amount_minor_units), 0) FROM categories c WHERE c.budget_id = b.id) AS planned,
       (SELECT coalesce(sum(execution_minor_units), 0) FROM categories c WHERE c.budget_id = b.id) AS execution,
       (SELECT coalesce(sum(approved_set_aside_minor_units), 0) FROM month_plans m WHERE m.budget_id = b.id) AS approved_set_aside,
       (SELECT coalesce(sum(current_set_aside_minor_units), 0) FROM month_plans m WHERE m.budget_id = b.id) AS current_set_aside,
       (SELECT coalesce(sum(i.approved_amount_minor_units), 0) FROM month_plan_items i JOIN month_plans m ON m.id = i.month_plan_id WHERE m.budget_id = b.id) AS items_approved,
       (SELECT coalesce(sum(i.current_amount_minor_units), 0) FROM month_plan_items i JOIN month_plans m ON m.id = i.month_plan_id WHERE m.budget_id = b.id) AS items_current
FROM budgets b
ORDER BY b.id;

-- 4. Wykonanie per kategoria
SELECT id AS category_id, budget_id, execution_minor_units FROM categories ORDER BY id;

-- 5. Przelewy własne i ruchy kapitału (per direction)
SELECT 'own_transfers' AS zrodlo, NULL AS direction, coalesce(sum(amount_minor_units), 0) AS suma FROM own_transfers
UNION ALL
SELECT 'capital_movements', direction::text, sum(amount_minor_units) FROM capital_movements GROUP BY direction
ORDER BY 1, 2;

-- 6. Salda kont i lokat
SELECT 'accounts' AS zrodlo, id, opening_balance_minor_units, current_balance_minor_units FROM accounts
UNION ALL
SELECT 'term_deposits', id, opening_balance_minor_units, current_balance_minor_units FROM term_deposits
ORDER BY 1, 2;
```
