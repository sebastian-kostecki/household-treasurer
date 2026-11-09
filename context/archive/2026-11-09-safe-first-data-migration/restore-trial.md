# Próba przywracania kopii z wdrożenia F-04

Dowód dla S-18, że droga powrotu z kopii przed migracją działa. Bez danych osobowych i kwot pojedynczych operacji.

## Przebieg

- **Data:** 2026-11-09.
- **Wdrożenie:** `main` @ `57af4c5` na VPS. Pierwsze uruchomienie `scripts/deploy.sh` poszło starą wersją skryptu (patrz „Uwagi”), drugie zrobiło kopię przed migracją.
- **Archiwum:** `2026-11-09-11-06-30.zip` (UTC), 150 961 B, 6 plików (dump `db-dumps/postgresql-household_treasurer.sql` 589 980 B, `private/`, `public/`, `.gitignore`).
- **Przywrócenie:** lokalnie, Sail, baza `restore_trial` (wcześniej `config:clear` i `db:show` pokazujący `restore_trial`). `backup:restore --force` zakończył się sukcesem w kilka sekund, bez roli `treasurer` i bez żadnego obejścia.
- **Baza deweloperska:** nietknięta (nadal ma budżety o id 1, 2 i 5, w tym 2027, którego produkcja nie ma).
- **Sprzątanie:** zip, baza `restore_trial` usunięte; lokalne `storage/app/private` i `storage/app/public` odtworzone z kopii i porównane `diff -r`.

## Liczby kontrolne (produkcja przed wdrożeniem = produkcja po wdrożeniu = `restore_trial`)

Wszystkie sześć zapytań z README dało identyczny wynik w trzech miejscach.

| Pomiar | Wartość |
| --- | --- |
| users / budgets / categories | 1 / 2 / 77 |
| financial_operations (wiersze, max id) | 1271 / 1330 |
| own_transfers / capital_movements | 52 / 9 |
| term_deposits / accounts | 5 / 9 |
| month_plans / month_plan_items | 1 / 38 |
| migrations | 20 |
| budżet 1: operacje = execution | 27 793 145 (grosze) |
| budżet 1: initial / planned plan | 27 109 680 / 30 482 229 |
| budżet 2: planned plan | 29 190 302 (operacje i execution 0) |
| month_plans set_aside (approved = current) | 120 000 |
| month_plan_items approved / current (budżet 1) | 2 382 238 / 2 378 748 |
| own_transfers suma | 3 129 990 |
| capital_movements: from_term_deposit / to_term_deposit | 1 724 714 / 2 333 237 |

Wykonanie per kategoria (77 wierszy) oraz salda 9 kont i 5 lokat także zgodne co do grosza.

## Uwagi

- **Pierwsze wdrożenie nowego `deploy.sh` biegnie starym skryptem.** Skrypt robi `git pull` sam na sobie, więc pierwsze uruchomienie po scaleniu zmiany nie ma kroku kopii (tu: w logu brakowało „Backing up the instance…”, kopii nie było). Drugie uruchomienie zadziałało poprawnie. Dopisane do README.
- **Archiwum z produkcji nie wymagało hasła:** przywrócenie zadziałało także z fałszywym `BACKUP_ARCHIVE_PASSWORD`, więc na produkcji `BACKUP_ARCHIVE_PASSWORD` jest najpewniej puste i archiwa nie są szyfrowane (`config/backup.php:76`). Do rozstrzygnięcia osobno (nie część F-04).
- Archiwum nie zawiera `private/user-snapshots/` (produkcja ich nie ma).
