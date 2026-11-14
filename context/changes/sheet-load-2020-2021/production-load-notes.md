# Wczytanie lat 2020 i 2021 na produkcji — notatki

Procedura: `README.md`, „Wczytanie arkusza (lata 2020–2025)”. Wzór: `context/archive/2026-11-12-legacy-budget-import/production-load-notes.md`.

## 0. Wydanie v1.4.4 (2026-11-14)

- PR #229 (`worktree-sheet-load-2020-2021` z wciągniętym lokalnym `development`: v1-4-3-fixes, rhythm-hints, balance-reconciliation) scalony do `main` jako `87efe555`. Wcześniej ADR tej zmiany przemianowany z 0092 na 0096 (`5941ecb`; 0092–0095 zajęła v1-4-3-fixes). Bramka na scalonym kodzie: `composer ci:check` na PostgreSQL 1705 testów zielonych, 2 pominięte; Sloppy przechodzi (1 istniejące znalezisko średniej wagi); CI PR zielone.
- Wdrożenie na halina175 (`scripts/deploy.sh`, uruchomił użytkownik): liczby `20261114-080649-before/after.json`, kopia instancji `2026-11-14-08-06-54.zip` (bez hasła).

## 1. Próba na kopii produkcji — Progress 5.1

- Archiwum `2026-11-14-08-06-54.zip` (z deployu, SHA-256 `1a5d02bd…e26d` lokalnie = na serwerze) odtworzone **kodem worktree** (`docker run` obrazu `sail-8.5/app` z worktree zamontowanym, sieć Sail) do nowej bazy `restore_trial_sheet_2020`; `db:show` przed restore: ta baza, 0 tabel; po restore „No pending migrations”, `app:control-numbers --compare=<produkcyjny 20261114-080649-after.json>`: „Control numbers match”. Kopia = produkcja po deployu, z wczytanymi 2022–2025 (konta 12–15).
- Pliki w `storage/app/restore-trial/` worktree: `Budżet_2020.xlsx` SHA-256 `7618a81d…a5be`, `Budżet_2021.xlsx` `6c228b92…0e18` (po poprawce). Liczby „przed” próby: `storage/app/restore-trial/trial-before.json`.
- **Pierwszy `--dry-run` 2020 i 2021: kod 1.** Czytnik i sumy w porządku (2020: 435 operacji, saldo −4 674,31), ale 13 kategorii miało inne nazwy niż `mapping.php`: 13.11 o 07:40 użytkownik przebudował listę kategorii na produkcji (te same id, nowe nazwy, np. 1 „Sebastian”, 20 „Lekarze i badania”, 23 „Wyjścia i rozrywka”, 35 „Remonty i naprawy”; „Kosmetyki” id 42 odarchiwizowana jako „Chemia i kosmetyki”; nowe 43–49) i ręcznie przeniósł pojedyncze operacje 2022–2025 do 42 i 46–49 („Zmień kategorię”, po nazwie pozycji: domena, google one, decathlon…). Przy samej poprawce nazw wczytanie utworzyłoby drugą, zarchiwizowaną „Kosmetyki” obok 42.
- Decyzja użytkownika (ADR-0096, decyzja 6): nazwy jak na produkcji, a etykiety przeniesione: hbo go / netflix → 45 Streaming, internet → 44 Internet, etykiety kosmetyczne + „higiena i kosmetyki” + „środki czystości” → 42 Chemia i kosmetyki. Przy okazji poprawka komendy: wiersz „Kategoria „Kosmetyki” zostanie utworzona” tylko, gdy plan naprawdę ją tworzy (test regresji czerwony przed poprawką).
- `--dry-run` po zmianie: kod 0 dla obu lat, sumy jak w Fazie 3 (tabela niżej), 2020: 435 operacji, 42 ruchy funduszu; 2021: 491 operacji, 26 ruchów funduszu, 1 przeniesienie `grudzień!H8` → `H7`. Kategoria 42: 3 170,31 (2020) i 3 360,59 zł (2021); 44: 611,88 / 625,83; 45: 224,10 / 24,90.
- Wczytanie 2020 (Budżet 7, konto 17, 435 operacji), potem 2021 (Budżet 8, konto 18, 491 operacji). `--compare=trial-before.json --allow-additions`: kod 0, „Only additions: budgets +2, lines +64, accounts +2, categories +0, financial_operations +926”. Konta „Arkusz 2020/2021” zarchiwizowane, saldo bieżące 0, otwarcie −4 674,31 / −6 748,00 zł.
- Wycofanie 2021 → 2020 (`--force`), `--compare` bez `--allow-additions`: „Control numbers match: no differences”. Ponowne wczytanie obu lat: te same liczniki dodań, kod 0.

## 2. Pliki na serwerze — Progress 5.2

- Wydanie v1.4.5 (PR #230: poprawka mapowania, `063ea77`; CI zielone) scalone do `main` jako `799bdd6` i wdrożone przez użytkownika (`scripts/deploy.sh`: liczby `20261114-083644-before/after.json`, kopia `2026-11-14-08-36-47.zip`). Kontener `app` ma nowe mapowanie (`'internet' => 44`, `'netflix' => 45`, `42 => 'Chemia i kosmetyki'`).
- Pliki przesłane z lokalnej kopii przez `/root/sheet-load-2020-2021/` (usunięty po kopiowaniu) do `storage/app/private/sheet-load/` (sail, 640). SHA-256 w kontenerze: `Budżet_2021.xlsx` = `6c228b92db7931e60309cb1caac7987de480051638d548859ec38213393c0e18` (po poprawce, jak w `workbook-fix.md`), `Budżet_2020.xlsx` = `7618a81d2a763ba71fe7e4d0f4356f162f3a10af1562554a0e6c87f4f266a5be`.

## 3. Produkcja — Progress 5.3

Oczekiwane sumy `--dry-run` (operacje + ruchy funduszu = „Analiza”):

| Rok | Przychody | Wydatki | Saldo otwarcia | Uwagi |
| --- | --- | --- | --- | --- |
| 2020 | 69 913,54 + 4 098,28 = 74 011,82 | 65 239,23 + 4 318,05 = 69 557,28 | −4 674,31 | 0 ostrzeżeń arkusza, 0 reszt |
| 2021 | 91 448,99 + 4 900,00 = 96 348,99 | 84 700,99 + 5 510,56 = 90 211,55 | −6 748,00 | 1 przeniesienie `grudzień!H8` → `grudzień!H7` |

Wykonane 2026-11-14 ok. 08:38–08:45 UTC:

- Przed wczytaniem: `user:backup 1` → `storage/app/private/user-snapshots/1-20261114083810.json`; `app:control-numbers` → `storage/app/private/control-numbers/20261114-sheet-2020-2021-before.json` (plik „przed” do `--compare` i do wycofania; kopia pobrana poza serwer).
- `--dry-run` 2020 i 2021: kod 0, sumy jak w tabeli, „Kategorie z mapowania istnieją u użytkownika z oczekiwaną nazwą i typem”, brak wiersza o „Kosmetyki”; wydruki identyczne z próbą (poza poprawionym wierszem o „Kosmetyki”). 2020: 435 operacji, 2021: 491 operacji, 1 przeniesienie `grudzień!H8` → `H7`.
- Wczytanie (`--force`): 2020 → Budżet 7, konto 17 „Arkusz 2020”, 435 operacji; 2021 → Budżet 8, konto 18 „Arkusz 2021”, 491 operacji.
- `app:control-numbers --compare=storage/app/private/control-numbers/20261114-sheet-2020-2021-before.json --allow-additions`: kod 0, „Only additions: budgets +2, lines +64, accounts +2, categories +0, financial_operations +926”.
- Baza: konta 17 i 18 zarchiwizowane, saldo bieżące 0, otwarcie −4 674,31 / −6 748,00 zł; zwykłe konta bez zmian (np. „Konto Osobiste” 391 016 / 543 239 gr); Budżety 2020–2026 execution, 2027 draft (47 linii); 2020: 31 linii, 2021: 33 linie. Nie powstała żadna kategoria.
- Wycofanie, gdyby było potrzebne: `scripts/artisan.sh budget:unload-sheet 1 2021 --force`, potem `2020`, potem `app:control-numbers --compare=storage/app/private/control-numbers/20261114-sheet-2020-2021-before.json` bez `--allow-additions`.
- Sprzątanie lokalne: baza `restore_trial_sheet_2020` usunięta, `storage/app` worktree przywrócony, archiwum i xlsx usunięte z worktree.

## 4. Przegląd w aplikacji — Progress 5.4

- [ ] Selektor roku: 2020–2027
- [ ] Raport 2022 z kolumną 2021
- [ ] Raport 2021 z kolumną 2020
- [ ] Saldo „Na kontach” bez zmian (salda zwykłych kont potwierdzone liczbami kontrolnymi; do obejrzenia w UI)
- [ ] Szkic 2027 bez zmian
