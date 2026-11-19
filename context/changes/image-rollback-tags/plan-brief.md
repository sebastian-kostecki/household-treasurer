# Tagi obrazu i szybkie wycofanie — Plan Brief

> Full plan: `context/changes/image-rollback-tags/plan.md`

## What & Why

Wycofanie wadliwego wdrożenia na halina175 trwa dziś minuty: `git revert`, `git pull` i pełna przebudowa obrazu na przyduszonym, współdzielonym CPU, z aplikacją w trybie serwisowym. Zmiana zostawia po każdym wdrożeniu niezmienny obraz z tagiem SHA i dodaje `scripts/rollback.sh`, który wraca na poprzedni obraz w sekundach, bez budowania. To krok 2 i ryzyko M/M z `context/foundation/infrastructure.md`.

## Starting Point

`docker-compose.prod.yml` ma jeden obraz `household-treasurer-app:prod`, który `scripts/deploy.sh` nadpisuje przy każdym `build`. Poprzedni obraz staje się wiszący i nikt go nie sprząta. Obraz nie zna swojego commitu (`.git` jest w `.dockerignore`). Skrypty powłoki nie mają testów.

## Desired End State

Po wdrożeniu `docker image ls household-treasurer-app` pokazuje `:prod` i ten sam obraz pod `:<sha>`, plus najwyżej dwa starsze tagi (przy pierwszym wdrożeniu `legacy-<data>` dla dawnego obrazu). `scripts/rollback.sh` wypisuje tagi. `scripts/rollback.sh <tag>` przełącza produkcję przez tryb serwisowy i odbudowuje cache. Odmawia, zanim cokolwiek zmieni, gdy baza ma migracje, których obraz docelowy nie zna, albo obraz ma migracje, których baza nie ma.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Mechanizm tagu | `:prod` zostaje wskaźnikiem, tagi SHA są historią obok | Zmienna `${APP_IMAGE_TAG}` w Compose sprawiłaby, że każde `up -d`/`start` bez niej po cichu podnosi przestarzały `:prod`. |
| Migracje przy wycofaniu | Twarda blokada w obie strony: nieznane migracje → `migrate:rollback --force`, oczekujące → `deploy.sh` | Ani stary kod na nowym schemacie, ani nowy na starym, także w stresie. `rollback.sh` nigdy nie migruje. |
| Obraz sprzed tagów | Dawny `:prod` bez etykiety `revision` dostaje tag `legacy-<RRRRMMDD>` przed buildem | Już pierwsze wdrożenie po zmianie ma cel wycofania. |
| Sprzątanie | 3 najnowsze tagi według etykiety `deployed-at` (nie `.Created`, które BuildKit bierze z warstwy z cache), nigdy obraz `:prod` ani obraz sprzed wdrożenia, potem `docker image prune -f --filter label=maintainer=household-treasurer`. Cache budowania nietknięty | Ogranicza dysk bez ruszania obrazów innych aplikacji współdzielonego serwera, a cache przyspiesza przebudowę po `git revert`. |
| Testy | Pest na czystych funkcjach bash (wybór tagów, diff migracji) + ręczna próba na halina175 | Tanio i stabilnie łapie pomyłki, które kasują cel wycofania albo przepuszczają zły schemat. |

## Scope

**In scope:**
- etykieta `org.opencontainers.image.revision` w `Dockerfile.prod` i argument `GIT_REVISION` w Compose;
- `scripts/lib/production-images.sh` (funkcje czyste i wrappery `docker`);
- tagowanie i sprzątanie w `scripts/deploy.sh`;
- nowy `scripts/rollback.sh`;
- `tests/Unit/ProductionImagesScriptTest.php`;
- README, ADR-0107, `infrastructure.md`;
- próba na halina175.

**Out of scope:**
- build w CI i GHCR (krok 3 infrastructure.md);
- `docker builder prune` i kontrola dysku;
- automatyczny `migrate:rollback`;
- `git checkout` na serwerze;
- `user:backup` i liczby kontrolne przy wycofaniu;
- wdrażanie przez agenta.

## Architecture / Approach

Czyste funkcje (`production_image_tags_to_remove`, `production_unknown_migrations`, `production_pending_migrations`) dostają listy i zwracają listy, więc testy uruchamiają je przez `bash` bez Dockera. Cienkie wrappery `docker` w tym samym pliku obsługują `deploy.sh`:

```
pull → tag legacy (jeśli brak etykiety) → build → tag <sha> → [bramki bez zmian] → cache → sprzątanie
```

oraz `rollback.sh`:

```
sprawdź tag → blokada migracji → down → tag <tag> jako :prod → up -d --no-build → chown → cache → up
```

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Obrazy z tagiem SHA w `deploy.sh` | Każde wdrożenie zostawia `:<sha>` (i raz `legacy-…`), po sukcesie 3 tagi | Sprzątanie usunie obraz, do którego trzeba wrócić (chroni reguła ID `:prod` + test) |
| 2. `scripts/rollback.sh` z blokadą migracji | Wycofanie w sekundach, odmowa przy nieznanych migracjach | Kolejność kroków Dockera bez testu automatycznego, sprawdza ją próba ręczna |
| 3. Dokumentacja i próba na halina175 | README, ADR-0107, infrastructure.md. Wdrożenie → wycofanie → powrót na produkcji | Pierwsze wdrożenie starym `deploy.sh`, gdy ktoś pominie `git pull` przed skryptem |

**Prerequisites:** Docker lokalnie do budowy obrazu produkcyjnego. Dostęp człowieka do halina175 dla fazy 3. Merge do `main` przed próbą.
**Estimated effort:** ~2 sesje: fazy 1–2 w jednej, faza 3 i próba w drugiej.

## Open Risks & Assumptions

- `docker compose up -d --no-build` odtwarza kontener, gdy `:prod` wskazuje inny obraz (potwierdzone lokalnie podczas przeglądu planu, Compose v2.39). Próba w fazie 3 potwierdza to na halina175.
- Wycofanie używa `docker-compose.prod.yml` z bieżącego (złego) commitu na serwerze. Jeśli zły commit zmienił sam plik Compose, przełączenie obrazu tego nie cofnie (README to zaznacza).
- Po wycofaniu następny `deploy.sh` bez `git revert` zbuduje zły commit z powrotem. Mówi o tym komunikat skryptu i README.

## Success Criteria (Summary)

- Wycofanie wadliwego wdrożenia bez migracji to jedno polecenie i sekundy przestoju, bez budowania na serwerze.
- Wycofanie przy niecofniętej migracji jest zablokowane z poleceniem, co zrobić najpierw.
- Dysk trzyma najwyżej 3 historyczne obrazy aplikacji, a obraz działający nigdy nie jest usuwany.
