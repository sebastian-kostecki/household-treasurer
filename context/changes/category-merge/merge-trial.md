# Próba scalenia kategorii na kopii produkcji

Dowód dla fazy 6: scalenie obu par z produkcji na lokalnej kopii nie zmienia żadnej kwoty. Drugi dowód: droga powrotu przez `user:restore` **ma wadę** (zob. „Odstępstwa”, O1). Bez danych osobowych i bez kwot pojedynczych operacji.

## Przebieg

- **Data:** 2026-11-11.
- **Kod:** gałąź `worktree-category-merge` @ `f5f9e0d` (faza 5), lokalnie w Sail (PHP 8.5), PostgreSQL 18.6. Zbudowany `npm run build`, podgląd przez `php -S` z routerem Laravela na porcie 8140.
- **Snapshot produkcji:** `user:backup` z 2026-11-11 22:04:21 UTC, wersja formatu **6**, użytkownik źródłowy id 1 (plik poza repozytorium).
- **Baza:** osobna `merge_trial` (potem `merge_trial_restore` do kroku 6b). Baza deweloperska, `testing` i `household_treasurer_e2e` nietknięte. Serwer czytał `merge_trial`: logowanie lokalnym użytkownikiem próby (istnieje tylko tam) przeszło, a baza po scaleniach miała 39 kategorii.
- **Użytkownik próby:** utworzony w `merge_trial` z hasłem tymczasowym; restore nadpisał dane domenowe snapshotem produkcji.

| Krok | Wynik |
| --- | --- |
| 1. `migrate` + użytkownik + `user:restore` snapshotu produkcji | PASS, „Restored snapshot”, 41 kategorii listy |
| 2. `app:control-numbers --output=` „przed” | PASS, schemat `budget_lines`, 41 wierszy listy, 33 rytmy |
| 3. Scalenie w UI (dwie pary) | PASS, liczby z podglądu niżej |
| 4. `--compare=` „przed” | PASS: „Control numbers match: no differences.” po obu scaleniach |
| 5. Oględziny w UI | PASS |
| 6a. `user:restore` snapshotu sprzed pierwszego scalenia w tej samej bazie | **FAIL** (O1), transakcja wycofana, stan po scaleniach bez zmian |
| 6b. to samo w pustej bazie (obejście) | PASS, 41 kategorii, `--compare` bez różnic |

## Liczby przed (= po scaleniach)

| Pomiar | Wartość |
| --- | --- |
| users / budgets / pozycje budżetu | 1 / 2 / 77 |
| financial_operations (wiersze, max id) | 1271 / 1330 |
| own_transfers / capital_movements / accounts / term_deposits | 52 / 9 / 9 / 5 |
| month_plans / month_plan_items | 1 / 38 |
| budżet 1 (2026): operacje = wykonanie | 27 793 145 gr |
| budżet 1: Initial / Current Plan | 27 109 680 / 30 482 229 |
| budżet 1: pozycje planu miesiąca approved / current | 2 382 238 / 2 378 748 |
| budżet 2 (2027): Current Plan (operacje i wykonanie 0) | 29 190 302 |

Wartości identyczne z próbą migracji S-18 (`context/archive/2026-11-09-category-list-after-migration/migration-trial.md`): produkcja nie dostała nowych operacji. Obie pary leżą w różnych budżetach (2026 vs 2027), więc nie było złożenia pozycji i `--compare` nie potrzebował wyjątków z kroku 4.

## Podgląd w oknie scalenia

| Para | Operacje (2026) | Plan aktualny (2026) cel: przed → po | Pozycje planu miesiąca |
| --- | --- | --- | --- |
| „Jedzenie poza domem” → „Restauracje” | 48 (razem 48) | 0,00 zł → 3 000,00 zł | 1 (XI 2026) |
| „Eksploatacja domu” → „Utrzymanie domu” | 20 (razem 20) | 0,00 zł → 2 173,34 zł | 1 (XI 2026) |

Rok 2027 nie występuje w podglądzie: źródło ma tam 0 operacji, a pozycje szkicu zostają w osobnych liniach celu. Okno nie pokazało ostrzeżeń „zebranych” (obie kategorie bieżące, nie nieregularne). Tekst okna: „Przed scaleniem zapisujemy kopię bezpieczeństwa danych.”

## Snapshoty zapisane przez akcję

W `storage/app/private/user-snapshots/` worktree (gitignore), wpisy „Categories merged.” w `storage/logs/laravel.log`:

- pierwsze scalenie (id 24 → 40), snapshot wersji **10** sprzed scalenia: `1-20261111221057.json` (ok. 1,3 MB),
- drugie scalenie: kolejny plik `1-<czas>.json` (stan po pierwszym scaleniu).

## Oględziny (krok 5)

- Baza: 39 kategorii (było 41); „Jedzenie poza domem” i „Eksploatacja domu” nie istnieją; „Wakacje” nadal archiwalna.
- Widok „Kategorie”: 38 wierszy aktywnych + archiwalna „Wakacje” (jest poza wierszami `data-category-row`) = 39. Przed scaleniami 40 + 1.
- „Operacje” z linku pod „Restauracje”: zakładka „Operacje finansowe 48”, pod „Utrzymanie domu”: 20 (filtr „Wszystkie lata”).
- Baza: 48 operacji w 2026 pod „Restauracje”, 20 pod „Utrzymanie domu”. Sumy budżetu 2026 i szkicu 2027 równe „przed” wynikają z `--compare` (budgets, lines).

## Powrót (krok 6)

- **6a, w tej samej bazie:** `user:restore` snapshotu `1-20261111221057.json` kończy się `QueryException` (SQLSTATE 23503) na `financial_operations_import_identity_account_id_foreign`. Baza po nieudanym restore bez zmian (39 kategorii, liczby kontrolne jak „przed”).
- **6b, w świeżej bazie** (`merge_trial_restore`, id kont zgodne ze snapshotem): restore przeszedł, 41 kategorii z obiema parami osobno, `--compare` do pliku „przed” bez różnic (id zgodne, bo baza była pusta). Dowodzi, że **plik snapshotu jest poprawny**.

## Odstępstwa

- **O1 (blokujące dla drogi powrotu):** `RestoreUserData::restoreFinancialOperations()` (`app/Actions/RestoreUserData.php:~790`) przepisuje `account_id`, `budget_line_id`, `budget_id` przez mapy id, ale `import_identity_account_id` zostawia surowe ze snapshotu. Gdy restore nadaje kontom nowe id (a robi to zawsze, gdy użytkownik ma już dane), operacja z tożsamością importu odwołuje się do nieistniejącego konta i insert pada na kluczu obcym. Powtórzone na samym snapshocie produkcji (wersja 6) wgranym na bazę z danymi: ten sam błąd, więc to nie wada scalenia. Ten sam brak jest w `source_import_identity_account_id` i `destination_import_identity_account_id` przelewów (`RestoreUserData.php:834`, `:838`); ruchy kapitału mapują to pole poprawnie (`:867`). Błąd jest też w `main` (`RestoreUserData.php:556`, `:590`, `:594`), a snapshot produkcji ma to pole wypełnione w 1269 z 1280 operacji, więc dotyczy wersji, która jest dziś na produkcji. Skutek dla S-21: opisana w planie droga „wycofanie pojedynczego scalenia przez `user:restore` z pliku akcji” **nie działa na produkcji**, gdzie konta mają operacje z importu. Zapasem zostaje pełna kopia instancji (`backup:restore`, ADR-0064) i snapshot sprzed scalenia jako dane do ręcznego odtworzenia. Nie naprawiałem kodu; decyzja: osobna poprawka (test czerwony na restore snapshotu z importem na bazie z danymi) przed scaleniem na produkcji, albo zmiana opisu powrotu w README i planie.
- **Decyzja użytkownika (2026-11-11):** poprawka `RestoreUserData` idzie jako osobna zmiana (`/10x-new`) i wchodzi przed wdrożeniem; w tej zmianie README opisuje powrót przez `backup:restore` z kopii instancji albo `user:restore` do pustej bazy. Punkt 6.5 (gotowość do scalenia na produkcji) potwierdza użytkownik po poprawce restore i wdrożeniu. Kolejność u użytkownika: poprawka restore → wdrożenie (`deploy.sh`) → `user:backup` → scalenie obu par w UI → liczby kontrolne.
- Restore „w tej samej bazie” (6a) i „porównanie po id” (6b) różnią się od planu: plan zakładał, że 6a przejdzie i porówna sumy per rok; zamiast tego 6b w pustej bazie.
- Przykładowe zrzuty ekranu i pliki robocze (snapshoty, liczby kontrolne) leżą poza repozytorium w katalogu zadania.
- Sprzątanie: serwer `php -S` (kontener `merge-trial-server`) usunięty; bazy `merge_trial` i `merge_trial_restore` usunięte.
