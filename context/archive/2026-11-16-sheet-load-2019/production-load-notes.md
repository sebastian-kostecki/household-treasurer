# Wczytanie roku 2019 na produkcji — notatki

Procedura: `README.md`, „Wczytanie arkusza (lata 2019–2025)”. Wzór: `context/archive/2026-11-13-sheet-load-2020-2021/production-load-notes.md`.

## 0. Wydanie v1.5.1 (2026-11-16)

- PR #236 (`development` → `main`: fazy 1–4, poprawki z przeglądu implementacji `cd1aeb0`, styl `verify.php` `19db0eb`, changelog `7d72120`) scalony jako `382e7e1`. CI zielone. Pierwszy przebieg CI padł na Pint w `workbook-fix/verify.php`, bo lokalny `pint --parallel --test` w stałym kontenerze `laravel.test` przepuszczał ten plik, a świeży kontener i CI nie.
- Tag `v1.5.1` (z opisem) na `382e7e1` i wydanie na GitHubie „v1.5.1 — Sheet Load of 2019”.
- Wdrożenie na halina175 (`scripts/deploy.sh`, uruchomił użytkownik). Wdrożony commit `382e7e1f`. Liczby kontrolne `20261116-092103-before/after.json`, kopia instancji `2026-11-16-09-21-06.zip` (zaszyfrowana hasłem `BACKUP_ARCHIVE_PASSWORD`). Bez migracji.

## 1. Próba na kopii produkcji — Progress 5.1

- Archiwum `2026-11-16-09-21-06.zip` pobrane do `storage/app/restore-trial/`. SHA-256 lokalnie = na serwerze (`376b0c4c15833a42812a…`).
- Lokalne `storage/app/private` i `public` odłożone poza repozytorium. Utworzona baza `restore_trial_sheet_2019`; `db:show` przed restore pokazał tę bazę i 0 tabel.
- `backup:restore … --force` kodem v1.5.1 z hasłem pobranym z `.env.prod` na czas polecenia. Brak oczekujących migracji. `app:control-numbers --compare=<produkcyjny 20261116-092103-after.json>`: „Control numbers match: no differences”, więc kopia = produkcja po deployu.
- `app:control-numbers --output=trial-before.json`, potem `--dry-run` 2019:
  - kod 0, „Kategorie z mapowania istnieją u użytkownika z oczekiwaną nazwą i typem”;
  - przychody 78 039,29 i wydatki 67 286,75 zł, zgodne z `Podsumowanie!I16`/`H16`;
  - saldo otwarcia −10 752,54 zł, 352 operacje;
  - 2 ostrzeżenia i 2 reszty (`marzec_19!B24` −10,00, `lipiec_19!D21` −35,00);
  - „Kategoria „Stypendium” zostanie utworzona (zarchiwizowana).”
- Wczytanie (`--force`): Budżet 9, konto 19, 352 operacje. `--compare --allow-additions`: kod 0, „Only additions: budgets +1, lines +21, accounts +1, categories +1, financial_operations +352”.
- Wycofanie 2019: „usunięto 352 operacji i kategorię „Stypendium””. `--compare` bez `--allow-additions`: „Control numbers match: no differences”.
- Ponowne wczytanie: Budżet 10, konto 20, te same liczniki dodań. Baza próby po nim:
  - konto 20 „Arkusz 2019”: zarchiwizowane, otwarcie −1 075 254 gr, saldo 0;
  - Budżet 2019: execution;
  - „Stypendium”: przychód, zarchiwizowana, 0 wpisów Rytmu, 1 linia.
- Sprzątanie: baza `restore_trial_sheet_2019` usunięta, archiwum i pliki próby usunięte z `storage/app/restore-trial/`, `storage/app/private` i `public` przywrócone (49/49 plików). Uwaga do procedury: `backup:restore` w `laravel.test` działa jako root, więc pliki z archiwum należą do roota. `rm -rf storage/app/private` z hosta usuwa tylko pliki lokalne i odmawia przy tych z archiwum. Usunąć je trzeba przez `docker compose exec -u root laravel.test rm -rf storage/app/private`, a dopiero potem przywrócić odłożone katalogi.

## 2. Plik na serwerze — Progress 5.2

- `Budżet_2019.xlsx` przesłany przez `/root/sheet-load-2019/` (usunięty po kopiowaniu) do `storage/app/private/sheet-load/` (`sail:sail`, 640).
- SHA-256 w kontenerze: `73fc70cf915fbc623e18089b8a74219e35fa6975aad02190d84fb3ade4464c86`, czyli plik po poprawce z `workbook-fix.md`.

## 3. Produkcja — Progress 5.3

Wykonane 2026-11-16 ok. 09:25–09:27 UTC:

- Przed wczytaniem:
  - `user:backup 1` → `storage/app/private/user-snapshots/1-20261116092514.json`;
  - `app:control-numbers` → `storage/app/private/control-numbers/20261116-sheet-2019-before.json` (SHA-256 `dcfc3991baf0159f…`). To plik „przed” do `--compare` i do wycofania; kopia leży lokalnie w `storage/app/private/control-numbers/`.
- `--dry-run` 2019: kod 0, wydruk identyczny z próbą (sumy, 352 operacje, saldo otwarcia −10 752,54, dwie reszty, kategorie zgodne, „Stypendium” zostanie utworzona).
- Wczytanie (`--force`): „Wczytano rok 2019: Budżet 9, konto 19, operacji 352.”
- `app:control-numbers --compare=storage/app/private/control-numbers/20261116-sheet-2019-before.json --allow-additions`: „Only additions: budgets +1, lines +21, accounts +1, categories +1, financial_operations +352.”
- Baza (odczyt przez `tinker`):
  - konto 19 „Arkusz 2019”: zarchiwizowane, otwarcie −1 075 254 gr, saldo bieżące 0;
  - Budżety: 2019 (id 9, 21 linii) i 2020–2026 w execution, 2027 draft (48 linii);
  - kategoria 51 „Stypendium”: przychód, zarchiwizowana, 0 wpisów Rytmu, 1 linia.
- Wycofanie, gdyby było potrzebne: `scripts/artisan.sh budget:unload-sheet 1 2019 --force`, potem `app:control-numbers --compare=storage/app/private/control-numbers/20261116-sheet-2019-before.json` bez `--allow-additions`.

## 4. Przegląd w aplikacji — Progress 5.4

Salda zwykłych kont i szkic 2027 są potwierdzone liczbami kontrolnymi (zmieniły się tylko dodania). Obejrzane w UI i potwierdzone przez użytkownika 2026-11-16:

- [x] Selektor roku: 2019–2027
- [x] Raport 2020 z kolumną 2019
- [x] Widok „Lata” od 2019
- [x] „Stypendium” zarchiwizowana na „Kategoriach”
- [x] Saldo „Na kontach” i szkic 2027 bez zmian
