# Próba migracji S-18 na kopii produkcji

Dowód dla fazy 7: migracje S-18, ich `down()` i powrót z kopii instancji działają na prawdziwych danych. Bez danych osobowych i bez kwot pojedynczych operacji.

## Przebieg

- **Data:** 2026-11-10.
- **Kod:** `development` @ `8b1b3fa` (S-18 z poprawkami z pełnego przeglądu), lokalnie w Sail, PostgreSQL 18.6.
- **Archiwum:** `2026-11-10-05-13-42.zip` (UTC), 161 309 B, 6 plików (dump `db-dumps/postgresql-household_treasurer.sql` 609 609 B, `private/`, `public/`, `.gitignore`). Zrobione na VPS przez `scripts/artisan.sh backup:run --disable-notifications` tuż przed próbą, przeniesione przez `/tmp` i `scp`, kopia z `/tmp` na serwerze usunięta.
- **Baza:** osobna `restore_trial`. Przed restore `config:clear` (brak `bootstrap/cache/config.php`) i `db:show` z `DB_DATABASE=restore_trial` pokazujący `restore_trial` z 0 tabel. `backup:restore --force` bez hasła i bez roli `treasurer`.
- **Baza deweloperska:** nietknięta; lokalne `storage/app/private` i `storage/app/public` odłożone przed restore i odtworzone po próbie (`diff -r` czysty).

| Krok | Wynik | Czas (suma migracji) |
| --- | --- | --- |
| `backup:restore` archiwum | stary schemat, 20 migracji, 3 migracje S-18 `Pending` | kilka sekund |
| `app:control-numbers --output=before.json` | schemat `categories` | — |
| `migrate --force` | `expand…` 55,74 ms, `backfill_category_list` 112,82 ms, `drop_legacy…` 15,06 ms | 184 ms (ok. 0,8 s z uruchomieniem) |
| `--compare=before.json` | „Control numbers match: no differences.”, kod 0 | — |
| przejście przez aplikację (użytkownik) | Budżet 2026, plan miesiąca, Operacje z filtrem kategorii, podgląd Importu, „Kategorie”: bez uwag | — |
| `migrate:rollback --force` | dokładnie 3 migracje S-18 (`drop…` 93,79 ms, `backfill…` 67,58 ms, `expand…` 33,59 ms); 77 rekordów kategorii, 0 sufiksów „(k)” | 195 ms |
| `--compare=before.json` | bez różnic | — |
| ponowne `migrate --force` | 3 migracje (50,19 / 156,06 / 15,22 ms) | 221 ms |
| `--compare=before.json` | bez różnic | — |
| „Przywrócenie produkcji”, kroki 3–4 README: `backup:restore` tego samego archiwum na zmigrowaną bazę | stary schemat (brak `budget_lines`), 20 migracji, S-18 znów `Pending` | kilka sekund |
| `--compare=before.json` | bez różnic | — |

## Liczby kontrolne (przed = po migracji = po `down()` = po ponownej migracji = po przywróceniu kopii)

Ten sam plik `before.json` był wzorcem każdego porównania; komenda porównuje wszystkie poniższe klucze oraz wykonanie, plan i sumę operacji każdej z 77 pozycji budżetu po id.

| Pomiar | Wartość |
| --- | --- |
| users / budgets / pozycje budżetu (dawne `categories`) | 1 / 2 / 77 |
| financial_operations (wiersze, max id) | 1271 / 1330 |
| own_transfers / capital_movements | 52 / 9 |
| accounts / term_deposits | 9 / 5 |
| month_plans / month_plan_items | 1 / 38 |
| budżet 1: operacje = wykonanie | 27 793 145 (grosze) |
| budżet 1: Initial / Current Plan | 27 109 680 / 30 482 229 |
| budżet 1: odkładanie planu miesiąca (approved = current) | 120 000 |
| budżet 1: pozycje planu miesiąca approved / current | 2 382 238 / 2 378 748 |
| budżet 2: Current Plan (operacje i wykonanie 0) | 29 190 302 |
| own_transfers suma | 3 129 990 |
| capital_movements from_term_deposit / to_term_deposit | 1 724 714 / 2 333 237 |

Salda otwarcia i bieżące 9 kont i 5 lokat także zgodne. Wartości są identyczne z próbą F-04 (`context/archive/2026-11-09-safe-first-data-migration/restore-trial.md`), więc między 9 a 10 listopada produkcja nie dostała nowych operacji.

## Lista po migracji

- **41 kategorii listy:** 33 wydatki, 8 przychodów; pozycje: 39 w budżecie 2026 (wykonanie, 1 archiwalna), 38 w szkicu 2027.
- **Sufiksy „(k)”:** brak (w żadnym budżecie nie ma dwóch rekordów o tym samym kluczu nazwy).
- **Archiwalne:** „Wakacje” (wydatek), `archived_at` 2026-09-09 21:41:46, tylko w 2026 — zgodnie z regułą (d) ADR-0063.
- **36 kategorii łączy 2026 i 2027.**
- **Dwie zmiany nazw w szkicu 2027 dały osobne kategorie** (ta sama ikona, kolor, kolejność i plan; migracja łączy lata po kluczu nazwy):
  - „Jedzenie poza domem” (2026, 48 operacji) i „Restauracje” (2027),
  - „Eksploatacja domu” (2026, 20 operacji) i „Utrzymanie domu” (2027).

  **Decyzja użytkownika (2026-11-10):** zostają jako cztery kategorie i idą do scalenia w S-21 (FR-023). Do tego czasu: osobne wiersze w „Kategoriach”, filtr Operacji nie łączy lat, od stycznia 2027 brak podpowiedzi Importu i roku poprzedniego w propozycji stycznia dla „Restauracji” i „Utrzymania domu”. Kwoty i operacje nietknięte.

## Przywrócenie produkcji (follow-up F3 z F-04)

Kroki 3–4 README „Przywrócenie produkcji” (`backup:restore` archiwum sprzed migracji na bazę po migracji, potem `--compare` z plikiem „przed”) przeszły w `restore_trial`: baza wróciła do schematu i danych sprzed migracji bez różnic. Kroki 1, 2, 5 i 6 (`artisan down`, `git revert` na `main`, `git pull --ff-only` i `deploy.sh` na serwerze, `artisan up`) dzieją się tylko na serwerze; lokalnie przeczytane, nie wykonane.

## Uwagi

- **`deploy.sh` po poprawkach F3 nie był uruchomiony na prawdziwym stacku** (sprawdzony stubem `docker compose`). Pierwsze wdrożenie S-18 jest jego pierwszym prawdziwym przebiegiem: log musi pokazać tryb serwisowy, a potem „Snapshotted 1 user(s).”.
- **Na serwerze leży nieśledzony `.env-prod`** (obok `.env.prod`, którego używa skrypt). Nie blokuje `git pull --ff-only`; najpewniej zbłąkana kopia z sekretami, do usunięcia lub przeniesienia poza repozytorium.
- Archiwum znów nie wymagało hasła (jak w F-04): `BACKUP_ARCHIVE_PASSWORD` na produkcji jest puste (follow-up F1 z F-04, poza zakresem).
- Instrukcje README nie wymagały poprawek poza zdaniem o tym, że „Przywrócenia produkcji” nie ćwiczono.
