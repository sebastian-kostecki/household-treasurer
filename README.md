# Household Treasurer

Aplikacja webowa do rocznego budżetu domowego: plan kategorii kontra rzeczywiste przepływy oraz płynność na kontach i lokatach. Interfejs jest po polsku. Waluta MVP: PLN.

Dwie płaszczyzny produktu:

- **Budżet** — plan początkowy, plan aktualny i wykonanie. Kategorie wydatkowe są równomierne (_Paced_) albo jednorazowe (_One-off_).
- **Płynność** — _Spendable Liquidity_ (salda kont) i _Total funds_ (te salda plus lokaty).

Konta logowania tworzy administrator. Samodzielna rejestracja jest wyłączona. Każdy użytkownik widzi wyłącznie własne dane.

## Stack

Laravel 13, PHP 8.5, PostgreSQL, Vue 3, Inertia 3, Tailwind CSS, Pest. Szczegóły: [`.docs/tech-stack.md`](.docs/tech-stack.md).

## Dokumentacja

| Dokument                                     | Po co                                 |
| -------------------------------------------- | ------------------------------------- |
| [`CONTEXT.md`](CONTEXT.md)                   | Słownik domeny (język kodu i agentów) |
| [`.docs/prd.md`](.docs/prd.md)               | Wymagania produktu                    |
| [`.docs/mvp.md`](.docs/mvp.md)               | Krótki zakres MVP                     |
| [`.docs/tech-stack.md`](.docs/tech-stack.md) | Decyzje technologiczne                |
| [`.docs/adr/`](.docs/adr/)                   | Decyzje architektoniczne              |

Słownik w `CONTEXT.md` jest źródłem nazw. Unikaj synonimów, które ten plik wyklucza.

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

| Komenda                      | Działanie                                                    |
| ---------------------------- | ------------------------------------------------------------ |
| `user:create`                | Konto logowania                                              |
| `user:backup {user}`         | Snapshot JSON danych domenowych (wersja 5)                   |
| `user:restore {user} {path}` | Przywrócenie snapshotu na istniejącego użytkownika           |
| `user:reset-data {user}`     | Wyczyszczenie danych domenowych, konto logowania zostaje     |
| `demo:seed-household {user}` | Dane demo                                                    |
| `backup:restore {path}`      | Restore backupu całej instancji (Spatie), nie `user:restore` |

`{user}` to e-mail albo ID. Snapshoty lądują w `storage/app/private/user-snapshots/`.

## Produkcja

Własny Docker Compose na VPS (aplikacja + PostgreSQL + Caddy + worker kolejki). Backupy instancji: Spatie Laravel Backup. Nie używaj obrazu Sail na produkcji. Zobacz [`.docs/tech-stack.md`](.docs/tech-stack.md).
