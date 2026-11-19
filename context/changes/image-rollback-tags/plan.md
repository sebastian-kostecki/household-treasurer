# Tagi obrazu i szybkie wycofanie — plan implementacji

## Overview

Zmiana realizuje krok 2 „Szybki rollback obrazu” z `context/foundation/infrastructure.md` (ryzyko M/M „Wycofanie wadliwego wdrożenia trwa minuty (rebuild obrazu)”). Każde wdrożenie zostawia niezmienny obraz `household-treasurer-app:<sha>` obok wskaźnika `household-treasurer-app:prod`. Nowy `scripts/rollback.sh <tag>` przestawia `:prod` na poprzedni obraz i podnosi kontener bez budowania. Odmawia, gdy w bazie są migracje, których obraz docelowy nie zna. Na serwerze zostają trzy najnowsze tagi.

Decyzje z wywiadu (2026-11-19):

1. **Mechanizm tagu: `:prod` jako wskaźnik.** Compose zostaje przy `image: household-treasurer-app:prod`. Tagi SHA są historią obok. Odrzucona zmienna `${APP_IMAGE_TAG:-prod}` z infrastructure.md: każde wywołanie Compose bez niej po cichu podniosłoby przestarzały `:prod`.
2. **Migracje przy wycofaniu: twarda blokada.** Gdy tabela `migrations` zawiera nazwę, której nie ma w `database/migrations` obrazu docelowego, skrypt przerywa z listą tych migracji i poleceniem `scripts/artisan.sh migrate:rollback --force`. Nie pyta o potwierdzenie.
3. **Obraz sprzed tagów i sprzątanie.** Dawny `:prod` bez etykiety `revision` dostaje tag `legacy-<RRRRMMDD daty utworzenia obrazu>`. Po udanym wdrożeniu zostają 3 najnowsze tagi, a `docker image prune -f` zdejmuje wiszące obrazy. Cache budowania zostaje nietknięty.
4. **Testy:** Pest na czystych funkcjach bash (wybór tagów do usunięcia, diff migracji). Kolejność wywołań `docker` sprawdza ręczna próba na halina175.

## Current State Analysis

- `docker-compose.prod.yml:10`: `image: household-treasurer-app:prod`, `build:` z `docker/8.5/Dockerfile.prod`. `scripts/deploy.sh:21` robi `$COMPOSE build app`, więc każde wdrożenie nadpisuje jedyny obraz, a poprzedni staje się wiszący (`<none>`). Nikt nie robi `docker image prune` (infrastructure.md, Devil's advocate 2).
- Wycofanie dziś (README „Przywrócenie produkcji”, „Wycofanie S-18…”) zawsze kończy się `git revert` → push → `git pull` na serwerze → `scripts/deploy.sh`, czyli pełnym buildem na współdzielonym CPU, z aplikacją w trybie serwisowym.
- `$COMPOSE` (`docker compose --env-file .env.prod -f docker-compose.prod.yml`) ustawiają osobno `scripts/deploy.sh:10` i `scripts/artisan.sh:7`. Funkcje w `scripts/lib/production-deploy.sh` go używają: `production_user_ids`, `production_prepare_running_instance`, `production_ensure_maintenance_mode`, `production_leave_maintenance_mode`, `production_report_failure`.
- `.dockerignore` wyklucza `.git`, więc obraz zna swój commit tylko z argumentu budowania. `Dockerfile.prod` ma w `runtime` jedynie `LABEL maintainer=…` (`:79`).
- Cache konfiguracji, tras i widoków (`bootstrap/cache`) żyje w kontenerze, a plik trybu serwisowego w bind-mountowanym `storage`. `deploy.sh` po `up -d` robi `chown storage bootstrap/cache`, a na końcu `package:discover` + `config:cache` + `route:cache` + `view:cache`.
- Skrypty powłoki nie mają testów ani shellchecka w CI. Pliki wdrożenia testuje jako tekst tylko `tests/Unit/ProductionPostgresClientVersionTest.php` (styl klasowy, `extends Tests\TestCase`). `tests/Pest.php` wiąże `TestCase` tylko z `Feature`.
- `deploy.sh` aktualizuje się sam w kroku 1, więc README każe zawsze zrobić `git pull --ff-only origin main` przed skryptem.

## Desired End State

- Po każdym udanym `scripts/deploy.sh` w `docker image ls household-treasurer-app` jest `:prod` i ten sam obraz pod `:<sha HEAD>`, z etykietą `org.opencontainers.image.revision=<sha>`. Tagów innych niż `prod` jest najwyżej 3.
- Pierwsze wdrożenie po zmianie zostawia dawny obraz jako `:legacy-<RRRRMMDD>`, więc od razu jest do czego wracać.
- `scripts/rollback.sh` bez argumentu wypisuje dostępne tagi z datą, rewizją i znacznikiem obrazu `:prod`. `scripts/rollback.sh <tag>` w ciągu sekund (bez buildu) stawia aplikację na tym obrazie, przez tryb serwisowy, z przebudowanym cache. Przerywa przed jakąkolwiek zmianą, gdy baza ma migracje nieznane obrazowi docelowemu.
- README, ADR-0107 i infrastructure.md opisują nowy przebieg. Ręczna próba na halina175 przechodzi: wdrożenie → wycofanie → powrót.

### Key Discoveries:

- `scripts/deploy.sh:20-21`: build po `production_pull_latest`, czyli HEAD jest już nowym commitem. SHA obecnego `:prod` nie da się odczytać z gita, stąd etykieta `revision` i tag `legacy-…` dla obrazu bez niej.
- `scripts/lib/production-deploy.sh:27-35`: wzorzec odczytu z PostgreSQL przez `$COMPOSE exec -T pgsql sh -ec` z `to_regclass` i `ON_ERROR_STOP=1`. Blokada migracji czyta tabelę `migrations` tak samo.
- `scripts/lib/production-deploy.sh:113-121`: wzorzec pułapki EXIT, która zostawia aplikację w trybie serwisowym i mówi, co dalej.
- Dzisiejszy przebieg `build` → `up -d` dowodzi, że Compose odtwarza kontener, gdy ten sam tag `:prod` wskazuje nowy obraz. Wycofanie korzysta z tego samego mechanizmu (`docker tag` → `up -d --no-build`).
- Następny wolny numer ADR to 0107 (`tests/Unit/AdrNumberingTest.php` pilnuje ciągłości).

## What We're NOT Doing

- Budowania obrazu w CI i `docker compose pull` z GHCR (krok 3 infrastructure.md, osobna zmiana).
- Czyszczenia cache budowania (`docker builder prune`) i kontroli zajętości dysku w `uptime-check.sh` (osobne ryzyko „Dysk VPS zapełnia się”). Cache celowo zostaje, bo przyspiesza przebudowę po `git revert`.
- Zmiennej `${APP_IMAGE_TAG}` w `docker-compose.prod.yml` (odrzucona, decyzja 1).
- Automatycznego `migrate:rollback` w `rollback.sh`. Skrypt tylko blokuje i mówi, co zrobić, bo `down()` bywa stratny (S-26) i wymaga decyzji człowieka.
- `user:backup`, kopii instancji i liczb kontrolnych w `rollback.sh`. Przełączenie obrazu nie zmienia danych, a blokada gwarantuje zgodny schemat.
- Przywracania git na serwerze. `rollback.sh` nie robi `git checkout`; kod na serwerze dalej wskazuje zły commit, a drogą powrotu jest `git revert` + `deploy.sh` (komunikat skryptu i README).
- Wdrażania przez agenta. Próba na halina175 należy do człowieka (infrastructure.md, „Approval”).

## Implementation Approach

Logika decyzyjna (które tagi usunąć, które migracje są nieznane) trafia do czystych funkcji w nowym `scripts/lib/production-images.sh`. Dostają listy na wejściu i zwracają listy, więc testy Pest uruchamiają je przez `bash` bez Dockera. Wywołania `docker` są cienkimi funkcjami w tym samym pliku, używanymi przez `deploy.sh` (tagowanie, sprzątanie) i nowy `rollback.sh` (lista, blokada, przełączenie). Kolejność kroków wycofania powtarza `deploy.sh`: tryb serwisowy w starym kontenerze → przełączenie → `up -d` → uprawnienia → cache → `artisan up`.

## Critical Implementation Details

- **Kolejność w `deploy.sh`.** Tag `legacy-…` trzeba nadać **przed** `build`, bo po buildzie dawny obraz traci tag `:prod` i staje się wiszący. Tag SHA idzie zaraz po `build`, a sprzątanie dopiero na końcu udanego wdrożenia. Nieudane wdrożenie nie może usunąć obrazu, do którego trzeba wrócić.
- **Ochrona przy sprzątaniu.** Nigdy nie zdejmuj tagu, którego obraz ma to samo ID co `:prod`. Po udanym wdrożeniu to obraz działającego kontenera, a po wycofaniu obraz, na który wrócono. Bez tej reguły wdrożenie następujące po wycofaniu mogłoby skasować jedyny dobry obraz, jeśli jest starszy niż 3 najnowsze tagi.
- **Etykieta na końcu `Dockerfile.prod`.** `ARG GIT_REVISION` + `LABEL org.opencontainers.image.revision=$GIT_REVISION` jako ostatnie instrukcje etapu `runtime`. Zmienna wartość wyżej unieważniłaby cache wszystkich kolejnych warstw przy każdym commicie.
- **Blokada przed jakąkolwiek zmianą.** `rollback.sh` sprawdza istnienie tagu, różnicę od `:prod` i migracje, zanim wejdzie w tryb serwisowy. Odmowa zostawia działającą aplikację nietkniętą.

## Phase 1: Obrazy z tagiem SHA w `deploy.sh`

### Overview

Każde wdrożenie buduje obraz z etykietą rewizji, nadaje mu tag SHA, przed buildem ratuje dawny obraz bez etykiety jako `legacy-…`, a po sukcesie zostawia 3 najnowsze tagi.

### Changes Required:

#### 1. Etykieta rewizji w obrazie

**File**: `docker/8.5/Dockerfile.prod`, `docker-compose.prod.yml`

**Intent**: Obraz ma znać commit, z którego go zbudowano, bo `.git` nie trafia do kontekstu budowania. Etykieta odróżnia obraz otagowany od legacy.

**Contract**: `Dockerfile.prod`, koniec etapu `runtime`: `ARG GIT_REVISION=` i `LABEL org.opencontainers.image.revision=$GIT_REVISION`. `docker-compose.prod.yml` → `app.build.args`: `GIT_REVISION: ${GIT_REVISION:-}`. `image:` zostaje `household-treasurer-app:prod`.

#### 2. Funkcje obrazów

**File**: `scripts/lib/production-images.sh` (nowy)

**Intent**: Jedno miejsce na nazwę repozytorium obrazu, czyste funkcje decyzyjne i cienkie wrappery `docker`, wspólne dla `deploy.sh` i `rollback.sh`.

**Contract**:
- `PRODUCTION_IMAGE_REPOSITORY="household-treasurer-app"`.
- `production_image_tags_to_remove <keep> <protected-image-id>` (czysta). Stdin: wiersze `<tag>\t<created ISO-8601 UTC>\t<image-id>`. Pomija `prod` i `<none>`. Pozostałe sortuje malejąco po dacie utworzenia, pierwsze `<keep>` zostają. Z reszty wypisuje (po jednym w wierszu) tylko tagi, których ID ≠ `<protected-image-id>`.
- `production_unknown_migrations <db-list-file> <image-list-file>` (czysta). Wypisuje nazwy migracji z bazy, których nie ma w obrazie, posortowane, po jednym w wierszu. Wiersze listy obrazu mogą mieć sufiks `.php`; puste wiersze pomija. Migracje obecne tylko w obrazie (oczekujące) nie są błędem.
- `production_image_listing`: wiersze w formacie wejścia `production_image_tags_to_remove` dla wszystkich tagów repozytorium (`docker image ls` + `docker image inspect --format '{{.Created}}'`/`{{.Id}}`).
- `production_tag_legacy_prod_image`: gdy `:prod` istnieje i nie ma etykiety `revision` (pusta albo brak), taguje go `legacy-<RRRRMMDD z .Created>` i loguje to. W przeciwnym razie nic.
- `production_tag_built_image <revision>`: `docker tag …:prod …:<revision>`.
- `production_remove_old_image_tags <keep>`: liczy tagi z `production_image_tags_to_remove`, zdejmuje je (`docker rmi <repo>:<tag>`), loguje każdy, potem `docker image prune -f`.

#### 3. Tagowanie i sprzątanie w `deploy.sh`

**File**: `scripts/deploy.sh`

**Intent**: Wpiąć funkcje w obecny przebieg bez ruszania bramek (tryb serwisowy, snapshoty, liczby kontrolne, kopia instancji).

**Contract**: `source` nowego pliku obok `production-deploy.sh`. Po `production_pull_latest`: `export GIT_REVISION="$(git rev-parse --short HEAD)"` (osobna linia, żeby błąd zatrzymał skrypt), potem `production_tag_legacy_prod_image`. Po `$COMPOSE build app`: `production_tag_built_image "$GIT_REVISION"`. Po cache, przed „Done”: `production_remove_old_image_tags 3`. Bez `|| true` nigdzie.

#### 4. Testy funkcji

**File**: `tests/Unit/ProductionImagesScriptTest.php` (nowy)

**Intent**: Przypiąć reguły wyboru tagów i diffu migracji, czyli tę część, której pomyłka kasuje cel wycofania albo przepuszcza stary kod na nowy schemat.

**Contract**: Styl jak `ProductionPostgresClientVersionTest` (klasa `extends Tests\TestCase`). Funkcje uruchamiane przez `Symfony\Component\Process\Process` / `Illuminate\Support\Facades\Process`: `bash -c 'source scripts/lib/production-images.sh; …'` z wejściem na stdin albo w plikach tymczasowych. Przypadki dla `production_image_tags_to_remove`:
- 5 tagów SHA: usuwa 2 najstarsze;
- `prod` nigdy nie jest liczony ani wypisany;
- tag o ID równym chronionemu zostaje, nawet gdy jest najstarszy;
- `legacy-…` jest sortowany po dacie jak inne tagi;
- ≤3 tagi: pusto.

Przypadki dla `production_unknown_migrations`:
- baza ma migrację spoza obrazu: wypisana;
- obraz ma migrację spoza bazy (oczekującą): pusto;
- równe listy z sufiksem `.php` po stronie obrazu: pusto.

### Success Criteria:

#### Automated Verification:

- Testy funkcji przechodzą: `php artisan test --compact tests/Unit/ProductionImagesScriptTest.php` (Sail albo `docker run … sail-8.5/app` z AGENTS.md)
- Składnia skryptów poprawna: `bash -n scripts/deploy.sh scripts/lib/production-images.sh`
- Istniejący test plików wdrożenia przechodzi: `php artisan test --compact tests/Unit/ProductionPostgresClientVersionTest.php`
- Pint bez uwag: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- Lokalny `docker compose -f docker-compose.prod.yml build app` z `GIT_REVISION=test123` daje obraz z etykietą `org.opencontainers.image.revision=test123` (`docker image inspect`)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przed fazą 2.

---

## Phase 2: `scripts/rollback.sh` z blokadą migracji

### Overview

Nowy skrypt operatora: bez argumentu wypisuje tagi, z tagiem przełącza produkcję na ten obraz bez budowania, przez tryb serwisowy. Odmawia, gdy baza ma migracje nieznane obrazowi docelowemu.

### Changes Required:

#### 1. Odczyt migracji z bazy i obrazu

**File**: `scripts/lib/production-images.sh`

**Intent**: Zasilić czystą funkcję diffu prawdziwymi listami, w tym samym stylu co `production_user_ids`.

**Contract**:
- `production_database_migrations`: `$COMPOSE exec -T pgsql sh -ec`, `to_regclass('public.migrations')`, potem `SELECT migration FROM migrations ORDER BY migration` (`-At`, `ON_ERROR_STOP=1`). Pusto, gdy tabeli nie ma. Błąd psql zatrzymuje skrypt.
- `production_image_migrations <tag>`: `docker run --rm --entrypoint ls <repo>:<tag> /var/www/html/database/migrations` (implementer potwierdza `WORKDIR` w `Dockerfile.prod`).

#### 2. Skrypt wycofania

**File**: `scripts/rollback.sh` (nowy, wykonywalny)

**Intent**: Wycofanie kodu w sekundach zamiast przebudowy, z tymi samymi zabezpieczeniami trybu serwisowego co `deploy.sh`.

**Contract**: Szkielet jak `deploy.sh`: `set -euo pipefail`, `cd` do roota, `source` obu bibliotek, `COMPOSE`, sprawdzenie `.env.prod`. Przebieg:
1. Bez argumentu: tabela tagów (tag, data utworzenia, rewizja z etykiety, `← prod` przy obrazie `:prod`) i użycie, wyjście 0.
2. `<tag>`: odmowa, gdy tag nie istnieje albo wskazuje ten sam obraz co `:prod`. Zapamiętanie bieżącego tagu `:prod` (tag SHA/legacy o tym samym ID) jako drogi powrotu.
3. Blokada: `production_unknown_migrations` na listach z kroku 1. Niepusto → wypisz migracje, polecenie `scripts/artisan.sh migrate:rollback --force` (sprawdź listę w wyjściu, kolejność z README) i wyjście 1. Nic się wcześniej nie zmieniło.
4. Pułapka EXIT (wzór `production_report_failure`): przy błędzie po wejściu w tryb serwisowy komunikat, że aplikacja zostaje w trybie serwisowym, plus tag, z którego wyszliśmy (`scripts/rollback.sh <poprzedni>`).
5. Tryb serwisowy: gdy `app` działa, `exec -T -u sail app php artisan down` i `MAINTENANCE_STARTED=1`. Bez `user:backup`, więc **nie** `production_prepare_running_instance`.
6. `docker tag <repo>:<tag> <repo>:prod`, `$COMPOSE up -d --no-build app`, `production_ensure_maintenance_mode`.
7. `chown` storage i `bootstrap/cache` (jak `deploy.sh`), `package:discover`, `config:cache`, `route:cache`, `view:cache`, potem `production_leave_maintenance_mode`.
8. Komunikat końcowy: produkcja stoi na `<tag>`, a kod na serwerze nadal na złym commicie, więc następny `deploy.sh` zbuduje go z powrotem. Dalej `git revert` lokalnie → push `main` → `git pull --ff-only` na serwerze → `deploy.sh`. Sprawdź `https://budget.kostecki.dev/up`.

### Success Criteria:

#### Automated Verification:

- Testy funkcji przechodzą (z przypadkami diffu migracji): `php artisan test --compact tests/Unit/ProductionImagesScriptTest.php`
- Składnia poprawna: `bash -n scripts/rollback.sh scripts/lib/production-images.sh`
- Skrypt ma bit wykonywalny w gicie: `git ls-files -s scripts/rollback.sh` pokazuje `100755`

#### Manual Verification:

- Lokalnie, bez `.env.prod`: `scripts/rollback.sh` kończy się czytelnym błędem o braku `.env.prod`, nie niczym innym

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przed fazą 3.

---

## Phase 3: Dokumentacja i próba na halina175

### Overview

Runbooki i decyzja opisują nowy przebieg, a człowiek raz przechodzi go na produkcji.

### Changes Required:

#### 1. README

**File**: `README.md`

**Intent**: Operator ma znaleźć szybkie wycofanie tam, gdzie dziś szuka wycofania, i wiedzieć, kiedy wystarcza.

**Contract**:
- „Kroki `scripts/deploy.sh`”: krok 1 dopisuje etykietę rewizji, tag `legacy-…` przed buildem i tag SHA po nim. Nowy krok 10: zostają 3 najnowsze tagi, potem `docker image prune -f`.
- Nowa sekcja `#### Wycofanie obrazu (bez budowania)` po „Kroki `scripts/deploy.sh`”: kiedy wystarcza (wdrożenie bez migracji albo po `migrate:rollback` nowym kodem), `scripts/rollback.sh` / `scripts/rollback.sh <tag>`, co znaczy blokada migracji, że git na serwerze zostaje na złym commicie i trzeba dokończyć przez `git revert` + `git pull` + `deploy.sh` (przebudowa trafia w cache warstw, bo treść plików jest jak przed złym commitem), pierwsze wdrożenie daje `legacy-<data>`.
- „Wycofanie S-18 po wdrożeniu” i pokrewne: jedno zdanie odsyłające. Po `migrate:rollback` można od razu `scripts/rollback.sh <poprzedni tag>`, a `git revert` + `deploy.sh` przestają blokować powrót aplikacji.

#### 2. ADR-0107

**File**: `context/foundation/adr/0107-image-tags-and-rollback-without-build.md` (nowy, po angielsku jak pozostałe)

**Intent**: Zapisać, czemu `:prod` zostaje wskaźnikiem (odejście od zapisu w infrastructure.md), czemu blokada jest twarda i czemu cache budowania zostaje.

**Contract**: Format jak ADR-0064 (akapity decyzji, „We rejected:”). Odrzucone: `${APP_IMAGE_TAG}` w Compose, ostrzeżenie z potwierdzeniem, sam opis w README, `docker builder prune` w tej zmianie. Numeracja przechodzi `AdrNumberingTest`.

#### 3. infrastructure.md

**File**: `context/foundation/infrastructure.md`

**Intent**: Dokument opisuje stan faktyczny, więc „Rollback” i krok 2 mają mówić, co jest.

**Contract**: „Operational Story” → „Rollback”: najpierw `scripts/rollback.sh <tag>` (sekundy), potem `git revert` + `deploy.sh`. „Getting Started” krok 2: dopisek, że zrobione inaczej niż zapisano (`:prod` jako wskaźnik, ADR-0107). Wiersz rejestru ryzyk: mitygacja wskazuje `scripts/rollback.sh`.

### Success Criteria:

#### Automated Verification:

- Numeracja ADR przechodzi: `php artisan test --compact tests/Unit/AdrNumberingTest.php`
- Pełna bramka przechodzi na PostgreSQL w worktree: `composer ci:check` przez `docker run … --entrypoint composer sail-8.5/app ci:check` (AGENTS.md) oraz `vendor/bin/sloppy ci`

#### Manual Verification:

- Na halina175 po `git pull --ff-only origin main` i `scripts/deploy.sh`: log pokazuje tag `legacy-…`, tag SHA i sprzątanie, a `docker image ls household-treasurer-app` pokazuje `prod`, `<sha>` i `legacy-…`
- `scripts/rollback.sh` wypisuje tagi z `← prod` przy bieżącym SHA
- `scripts/rollback.sh legacy-<data>` przechodzi blokadę (ta zmiana nie ma migracji), a `/up` zwraca 200 na starym obrazie
- `scripts/rollback.sh <sha>` wraca na nowy obraz, a `/up` zwraca 200
- Kolejne wdrożenie (po następnym merge'u do `main`) zostawia najwyżej 3 tagi poza `prod`

**Implementation Note**: Próbę na halina175 wykonuje człowiek (infrastructure.md, „Approval”). Agent przygotowuje polecenia i czyta wynik.

---

## Testing Strategy

### Unit Tests:

- `tests/Unit/ProductionImagesScriptTest.php`: `production_image_tags_to_remove` (więcej niż `keep`, `prod` pomijany, ochrona ID `:prod` dla najstarszego tagu, `legacy-…` w kolejności dat, mniej niż `keep`) i `production_unknown_migrations` (migracja tylko w bazie, tylko w obrazie, równe listy z `.php`).

### Integration Tests:

- Brak automatycznych. Kolejność wywołań `docker` i `$COMPOSE` w `deploy.sh`/`rollback.sh` sprawdza próba ręczna (decyzja 4).

### Manual Testing Steps:

1. Lokalny build obrazu produkcyjnego z `GIT_REVISION`, sprawdzenie etykiety.
2. Na halina175: `git pull`, `scripts/deploy.sh`, `docker image ls household-treasurer-app`.
3. `scripts/rollback.sh`, `scripts/rollback.sh legacy-<data>`, `curl -I https://budget.kostecki.dev/up`.
4. `scripts/rollback.sh <sha>`, `/up`.

## Performance Considerations

Wycofanie nie buduje, więc trwa tyle, co `up -d` i cache (sekundy zamiast minut buildu). Trzy zachowane obrazy dzielą większość warstw (te same etapy `vendor`/`assets` przy niezmienionych zależnościach), więc zajmują na dysku niewiele ponad jeden obraz.

## Migration Notes

Brak migracji bazy. Pierwsze wdrożenie po zmianie wymaga `git pull --ff-only origin main` przed `scripts/deploy.sh` (jak zawsze), inaczej pobiegnie stary skrypt bez tagowania, a dawny `:prod` zostanie nadpisany bez tagu `legacy-…`.

## References

- Źródło: `context/foundation/infrastructure.md` (rejestr ryzyk, „Getting Started” krok 2, „Operational Story” → „Rollback”)
- Wzorce: `scripts/deploy.sh`, `scripts/lib/production-deploy.sh:27-35` (odczyt z psql), `:113-121` (pułapka EXIT)
- Test-wzór: `tests/Unit/ProductionPostgresClientVersionTest.php`
- Powiązane ADR: `context/foundation/adr/0064-instance-backup-before-every-production-migration.md`
- Runbooki: `README.md` → „Wdrożenie i przywracanie”

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Obrazy z tagiem SHA w deploy.sh

#### Automated

- [ ] 1.1 Testy funkcji przechodzą: `php artisan test --compact tests/Unit/ProductionImagesScriptTest.php`
- [ ] 1.2 Składnia skryptów poprawna: `bash -n scripts/deploy.sh scripts/lib/production-images.sh`
- [ ] 1.3 Istniejący test plików wdrożenia przechodzi: `php artisan test --compact tests/Unit/ProductionPostgresClientVersionTest.php`
- [ ] 1.4 Pint bez uwag: `vendor/bin/pint --dirty --format agent`

#### Manual

- [ ] 1.5 Lokalny build z `GIT_REVISION=test123` daje obraz z etykietą `org.opencontainers.image.revision=test123`

### Phase 2: scripts/rollback.sh z blokadą migracji

#### Automated

- [ ] 2.1 Testy funkcji przechodzą (z przypadkami diffu migracji): `php artisan test --compact tests/Unit/ProductionImagesScriptTest.php`
- [ ] 2.2 Składnia poprawna: `bash -n scripts/rollback.sh scripts/lib/production-images.sh`
- [ ] 2.3 Skrypt ma bit wykonywalny w gicie: `git ls-files -s scripts/rollback.sh` pokazuje `100755`

#### Manual

- [ ] 2.4 Lokalnie bez `.env.prod` `scripts/rollback.sh` kończy się czytelnym błędem o braku `.env.prod`

### Phase 3: Dokumentacja i próba na halina175

#### Automated

- [ ] 3.1 Numeracja ADR przechodzi: `php artisan test --compact tests/Unit/AdrNumberingTest.php`
- [ ] 3.2 Pełna bramka przechodzi na PostgreSQL w worktree: `composer ci:check` oraz `vendor/bin/sloppy ci`

#### Manual

- [ ] 3.3 Na halina175 `scripts/deploy.sh` loguje tag `legacy-…`, tag SHA i sprzątanie, a `docker image ls` pokazuje `prod`, `<sha>` i `legacy-…`
- [ ] 3.4 `scripts/rollback.sh` wypisuje tagi z `← prod` przy bieżącym SHA
- [ ] 3.5 `scripts/rollback.sh legacy-<data>` przechodzi blokadę, a `/up` zwraca 200 na starym obrazie
- [ ] 3.6 `scripts/rollback.sh <sha>` wraca na nowy obraz, a `/up` zwraca 200
- [ ] 3.7 Kolejne wdrożenie zostawia najwyżej 3 tagi poza `prod`
