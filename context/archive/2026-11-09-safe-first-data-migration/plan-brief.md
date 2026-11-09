# Safe First Data Migration — Plan Brief

> Full plan: `context/changes/safe-first-data-migration/plan.md`

## What & Why

F-04 z roadmapy (poprawki 1 i 2 z health-check). Wdrożenie ma robić kopię przed migracją, droga powrotu z tej kopii ma być opisana i raz sprawdzona na prawdziwym archiwum, a agent ma dostać reguły migracji danych, tożsamości kategorii i konwersji starszego snapshotu. Bez tego pierwsza nieodwracalna migracja (S-18, jedna lista kategorii) w razie błędu cofnęłaby produkcję do kopii z 03:00, czyli o dobę zmian.

## Starting Point

`scripts/deploy.sh:36` robi `migrate --force` bez kopii, a jedyna kopia powstaje nocą o 03:00. `backup:restore` już istnieje, ale ścieżka PostgreSQL nie ma testu, a dump z produkcji (`OWNER TO treasurer`) nie wczyta się lokalnie pod rolą `sail`. Teksty reguł w ocenie stosu powstały przed ADR-0063 i są z nim sprzeczne: piszą o „scalaniu”, używają terminu „per-Budget categories”, mają za słabe niezmienniki i zbyt wąskie wycofanie.

## Desired End State

Każde wdrożenie robi pełną kopię instancji jako `sail` przed migracją, a nieudana kopia zatrzymuje wdrożenie. Dumpy wczytują się pod dowolną rolą. README ma instrukcję przywracania produkcji (z cofnięciem kodu) i próby lokalnej, a ADR-0064 zapisuje decyzję. Archiwum z wdrożenia F-04 zostało przywrócone lokalnie do bazy `restore_trial` i liczby kontrolne zgadzają się z produkcją. Trzy reguły w `.ai/rules` ładują się agentowi i są zgodne z ADR-0063.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Rodzaj i wyzwalacz kopii | Pełna `backup:run` (baza + `storage/app`) przy każdym wdrożeniu, jako `sail` | `--only-db` przy retencji „najnowsze archiwum dnia” wyparłoby pełne archiwum z 03:00, a warunek „tylko przy migracjach” mógłby po cichu pominąć kopię | Plan |
| Dump z produkcji lokalnie | `--no-owner --no-privileges` w `pgsql.dump.add_extra_option` + test | Każde przyszłe archiwum wczytuje się pod dowolną rolą, także na nowym serwerze; starsze archiwa obsługuje `CREATE ROLE treasurer` | Plan |
| Wycofanie w regule migracji | Dwa przypadki: kopia na awarię przy wdrożeniu; plan migracji danych nazywa wycofanie po wdrożeniu i liczby kontrolne | Zgodne z PRD v3 (bez utraty operacji dodanych po wdrożeniu) i nie przesądza projektu S-18 | Plan |
| Miejsce instrukcji | README „Wdrożenie i przywracanie” (po polsku) + ADR-0064 | Instrukcja jest obok istniejących komend administracyjnych, a zmiana formatu nocnych kopii jest zapisana jako decyzja | Plan |
| Treść reguł | Przepisane pod ADR-0063 i słownik, a nie skopiowane z oceny stosu | Teksty źródłowe przeczą ADR-0063 i używają zakazanych terminów | Research |
| Rytm etapami | W `migrations.md` (expand → backfill → drop), nie w `categories.md` | Glob `categories.md` nie obejmuje plików czytających flagi, a ADR-0063 zostawia F-05 decyzję, gdzie żyje rytm | Plan |
| Wzorzec testu migracji (poprawka 3) | Zostaje dla S-18 | Roadmapa: wzorzec powstaje na prawdziwej migracji | Roadmap |

## Scope

**In scope:**
- `config/database.php` (opcja dumpa) i test w `ApplicationBackupRestoreTest.php`; `config/backup.php` (`restore-trial` poza kopią)
- `scripts/deploy.sh`: kopia przed migracją, `BACKUP_HOST_DIR` w tej samej kolejności co Compose
- ADR-0064, README „Wdrożenie i przywracanie” (oraz Caddy → Traefik)
- `.ai/rules/migrations.md`, `.ai/rules/categories.md`, sekcja w `snapshot.md`, `boost:index-rules`
- Ręczna próba przywracania i `restore-trial.md`

**Out of scope:**
- `tests/Feature/Migrations` (S-18), wycofanie i liczby kontrolne S-18, `user:backup` przed migracją (S-18)
- Zmiany w `RestoreApplicationBackup` (`DROP SCHEMA` poza transakcją) i automatyczny test ścieżki PostgreSQL
- Kopia warunkowa, `--only-db`, osobna retencja; wdrożenie w CI, shellcheck
- Kod łączący lata po nazwie, snapshot v6, flagi i rytm

## Architecture / Approach

Najpierw siatka bezpieczeństwa (kopia, dump bez właściciela, instrukcja), potem reguły, które do niej linkują, na końcu dowód. Wdrożenie F-04 samo tworzy pierwsze archiwum bez właściciela. Przywracamy je lokalnie do osobnej bazy `restore_trial` i najpierw odkładamy lokalne `storage/app/private` i `public`, bo `backup:restore` kopiuje też pliki z archiwum.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kopia przed migracją | `--no-owner` z testem, kopia w `deploy.sh`, ADR-0064, README | Kopia jako root zostawiłaby katalogi roota; rozwiązuje to `-u sail` |
| 2. Reguły agenta | `migrations.md`, `categories.md`, sekcja w `snapshot.md`, indeks | Reguła opisze stan docelowy zamiast przejściowego (do S-18 kod nadal łączy po nazwie) |
| 3. Próba przywracania (ręczna) | Archiwum z wdrożenia przywrócone lokalnie, liczby kontrolne równe, `restore-trial.md` | Próba nadpisze lokalną bazę lub pliki; chroni przed tym `restore_trial` i odłożenie `private/` i `public/` |

**Prerequisites:** dostęp SSH do VPS, `BACKUP_ARCHIVE_PASSWORD` z `.env.prod`, działający Sail; fazy 1–2 scalone do `main` przed fazą 3.
**Estimated effort:** ~1 sesja na fazy 1–2, plus ~30 min ręcznej próby na fazę 3.

## Open Risks & Assumptions

- Ścieżka PostgreSQL `backup:restore` nie ma testu; jeśli próba wykaże błąd, naprawa idzie osobną zmianą, a F-04 nie jest zamknięte bez udanej próby.
- Nieudany `backup:restore` zostawia pustą bazę (`DROP SCHEMA` poza transakcją); instrukcja każe powtórzyć go z tego samego archiwum.
- Zakładamy, że `.env.prod` na VPS ma `BACKUP_HOST_DIR` zgodny z tym, gdzie dziś leżą nocne kopie; faza 3 to potwierdza.

## Success Criteria (Summary)

- Wdrożenie na produkcji pokazuje kopię przed migracją, a nowe archiwum leży w katalogu kopii.
- Archiwum z produkcji przywraca się lokalnie bez obejść i daje te same liczby kontrolne.
- Agent edytujący migrację albo kod kategorii dostaje reguły zgodne z ADR-0063.
