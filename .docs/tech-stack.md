# Household Treasurer — Tech stack

Decyzje technologiczne MVP. Wymagania produktowe pozostają w `.docs/prd.md` i `.docs/mvp.md`.

## 1. Wybrany stack

- **Laravel 13** — backend, routing, uwierzytelnianie, trwały zapis, reguły biznesowe.
- **PHP 8.5** — wersja wymagana przez Laravel 13.
- **PostgreSQL** — jedyny silnik bazy w developmentcie i na produkcji.
- **Vue 3** (Composition API) + **TypeScript** — warstwa UI.
- **Inertia 3** — most między Laravel a Vue. Aplikacja jest monolitem full-stack, nie osobnym SPA z REST API.
- **Vite** — bundler i serwer deweloperski assetów frontendu.
- **Tailwind CSS** + **shadcn-vue** — stylowanie i gotowe komponenty UI.
- **Pest** — testy PHP.
- **Docker** — środowisko uruchomieniowe.
- **Laravel Sail** — lokalne środowisko deweloperskie na Dockerze (WSL2).

Projekt powstaje z oficjalnego starter kitu Laravel Vue (`laravel new --vue`): auth e-mail/hasło, Inertia, TypeScript, Tailwind, shadcn-vue. WorkOS i inne zewnętrzne identity providery są poza zakresem.

## 2. Podział odpowiedzialności

| Warstwa | Odpowiedzialność |
| --- | --- |
| Laravel | HTTP, sesja, autoryzacja, walidacja, transakcje, modele, migracje, testy serwerowe |
| PostgreSQL | trwały zapis, ograniczenia integralności, atomowe transakcje transferów i importu CSV |
| Inertia | przekazywanie props z kontrolerów do stron Vue, nawigacja bez osobnego API |
| Vue + TypeScript | widoki, formularze, interakcje w przeglądarce |
| Tailwind + shadcn-vue | layout, komponenty, stany (w tym WCAG 2.2 AA na kluczowych przepływach) |
| Vite | kompilacja i HMR w developmentcie; `npm run build` na produkcję |

Logika budżetu, sald, atomowość transferów i importu CSV żyje po stronie Laravela. Vue prezentuje stan i zbiera dane wejściowe. Inertia nie zastępuje reguł biznesowych.

Kwoty są przechowywane i liczone jako **liczba całkowita groszy** (`bigint`). PHP nie używa `float`/`double` do pieniędzy. TypeScript na froncie dostaje kwoty jako string albo integer groszy i tylko je formatuje do wyświetlenia (PLN).

Osobne REST/JSON API nie powstaje w MVP.

## 3. Baza, cache, kolejki

PostgreSQL jest źródłem prawdy. Sail instaluje usługę `pgsql`. Testy feature/integracyjne też idą na PostgreSQL w Sail. SQLite nie jest bazą aplikacji.

Redis nie wchodzi do MVP. Sesje, cache i kolejka zostają na defaultach Laravel wystarczających przy jednym kontenerze aplikacji:

- sesje: `database`,
- cache: `database`,
- queue: `database` (maile resetu hasła i ewentualnie cięższy import).

Worker kolejki (`queue:work`) działa obok aplikacji w Sail i na produkcji. Horizon i Redis odkładamy, dopóki jeden worker i kolejka w Postgresie nie staną się wąskim gardłem.

Pliki CSV trzymamy na lokalnym dysku w wolumenie Dockera. Object storage (S3/MinIO) jest poza MVP.

## 4. Poczta

Reset hasła wymaga prawdziwego mailera, nie logowania do pliku na produkcji.

- **Development:** Mailpit w Sail. Żaden mail nie wychodzi na zewnątrz.
- **Produkcja:** transakcyjny SMTP. Domyślny dostawca: **Resend**. Adres, klucz i from są wyłącznie w zmiennych środowiskowych. Zmiana dostawcy nie wymaga zmiany kodu, tylko konfiguracji.

## 5. Development: Sail

Lokalny development działa wyłącznie przez Sail. PHP, Composer, Node, Artisan, Pest i kolejka uruchamia się w kontenerach Sail, nie na hoście.

`sail:install` obejmuje `pgsql` i `mailpit`. To jedyne dodatkowe usługi deweloperskie na start. `compose.yaml` Saila jest źródłem usług lokalnych.

Środowisko: WSL2 + Docker. Vite HMR wymaga konfiguracji pod Sail na WSL2 zgodnie z dokumentacją Laravel Vite.

Sail nie jest obrazem produkcyjnym.

## 6. Produkcja: Docker na VPS

Produkcja to własny `Dockerfile` i `compose` (aplikacja + PostgreSQL + Caddy jako TLS reverse proxy + worker kolejki). Nie używamy obrazu Sail.

Hosting: pojedynczy VPS (rekomendacja: **Hetzner**) z Docker Compose. Backupy: zrzuty PostgreSQL poza VPS (storage Hetzner albo inny off-site).

Laravel Cloud, Forge i PaaS zostają jako alternatywa, jeśli utrzymanie VPS przestanie się opłacać. Stack aplikacji się wtedy nie zmienia — zmienia się tylko sposób uruchomienia kontenerów.

## 7. Uzasadnienie wyborów

| Decyzja | Wybrane | Odrzucone | Powód |
| --- | --- | --- | --- |
| Baza | PostgreSQL | MySQL, SQLite | Lepsze typy i transakcje dla pieniędzy i atomowego importu; ten sam silnik lokalnie i na produkcji. SQLite jest za słabe na multi-user MVP. MySQL działa, ale nic tu nie wnosi. |
| Pieniądze | integer grosze | `numeric` + float w PHP, JS `number` | PRD zabrania arytmetyki zmiennoprzecinkowej. Integer groszy nie da się przypadkiem zrzutować na float. |
| CSS | Tailwind + shadcn-vue | Vuetify, Naive UI, czysty CSS | To default oficjalnego starter kitu Vue. Mniej tarcia, komponenty pod WCAG łatwiej utrzymać niż pisać od zera. |
| JS | TypeScript | czysty JavaScript | Default starter kitu; kontrakt props Inertia i formatowanie kwot jest wtedy jawny. |
| Redis | brak w MVP | Redis od dnia zerowego | Jeden użytkownik naraz na instancję, brak real-time, brak ciężkich jobów. Kolejka w Postgresie wystarcza na maile. |
| Poczta prod | Resend (SMTP) | własny MTA, Mailgun jako wymóg | Transakcyjne maile bez utrzymywania serwera poczty. SMTP w `.env` pozwala zmienić dostawcę. |
| Hosting | VPS + Docker Compose | Laravel Cloud, Forge jako wymóg | Zgodne z decyzją „na Dockerze”. Hetzner jest tani i przewidywalny na MVP. |
| API | brak | osobne REST API | Inertia pokrywa UI. API byłoby drugim kontraktem bez konsumenta. |

## 8. Świadomie odłożone

Nie są częścią MVP i nie instalujemy ich „na zapas”:

- Redis, Horizon, WebSockets,
- object storage,
- Laravel Octane / FrankenPHP jako wymóg (można dodać później bez zmiany stacku UI),
- osobne REST API,
- WorkOS / SSO,
- Kubernetes.
