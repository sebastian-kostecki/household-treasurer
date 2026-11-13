# Wczytanie lat 2020 i 2021 na produkcji — notatki

Szkielet do wypełniania w trakcie wykonania. Procedura: `README.md`, „Wczytanie arkusza (lata 2020–2025)”. Wzór: `context/archive/2026-11-12-legacy-budget-import/production-load-notes.md`. Nic poniżej nie jest jeszcze wykonane.

## 1. Próba na kopii produkcji (`restore_trial`) — Progress 5.1

- [ ] Archiwum `backup:run` (nazwa, czas UTC):
- [ ] Baza `restore_trial` odtworzona kodem worktree, `db:show` pokazał ją z 0 tabel przed restore, `migrate` bez zaległości:
- [ ] Pliki xlsx w `storage/app/restore-trial/`, SHA-256 zgodne z produkcyjnymi (2021 = poprawiony):
- [ ] `user:backup` i `app:control-numbers` „przed” (stan po 2022–2025, nie stary `20261112-…`):
- [ ] `--dry-run` 2020 i 2021 (kod, sumy, ostrzeżenia, kategorie z mapowania):
- [ ] Wczytanie 2020, potem 2021 (czas, liczba operacji):
- [ ] `app:control-numbers --compare=<przed> --allow-additions`, kod 0, podsumowanie dodań:
- [ ] Wycofanie 2021 → 2020 (`budget:unload-sheet --force`), `--compare` bez `--allow-additions` bez różnic:
- [ ] Ponowne wczytanie obu lat, te same liczniki dodań:

## 2. Pliki na serwerze — Progress 5.2

- [ ] Wdrożony commit (`development` / `main`):
- [ ] `sha256sum Budżet_2021.xlsx` = `6c228b92db7931e60309cb1caac7987de480051638d548859ec38213393c0e18` (oryginał `9396b6b6…c872` jest zły):
- [ ] `sha256sum Budżet_2020.xlsx`:

## 3. Produkcja — Progress 5.3

Oczekiwane sumy `--dry-run` (operacje + ruchy funduszu = „Analiza”):

| Rok | Przychody | Wydatki | Saldo otwarcia | Uwagi |
| --- | --- | --- | --- | --- |
| 2020 | 69 913,54 + 4 098,28 = 74 011,82 | 65 239,23 + 4 318,05 = 69 557,28 | −4 674,31 | 0 ostrzeżeń arkusza, 0 reszt |
| 2021 | 91 448,99 + 4 900,00 = 96 348,99 | 84 700,99 + 5 510,56 = 90 211,55 | −6 748,00 | 1 przeniesienie `grudzień!H8` → `grudzień!H7` |

- [ ] Wdrożenie (`scripts/deploy.sh`, bez migracji, „Control numbers match”):
- [ ] Pliki skopiowane do `storage/app/private/sheet-load/`, właściciel sail:
- [ ] `user:backup 1` → plik:
- [ ] `app:control-numbers --output=…-sheet-before.json` → plik (kopia poza serwerem):
- [ ] `--dry-run` 2020 i 2021, wyniki:
- [ ] Wczytanie 2020, potem 2021: id Budżetów, id kont „Arkusz RRRR”, liczba operacji:
- [ ] `app:control-numbers --compare=<przed> --allow-additions`: kod, podsumowanie dodań:
- [ ] Wycofanie, gdyby było potrzebne: `scripts/artisan.sh budget:unload-sheet 1 2021 --force`, potem `2020`, potem `--compare=<przed>` bez `--allow-additions`.

## 4. Przegląd w aplikacji — Progress 5.4

- [ ] Selektor roku: 2020–2027
- [ ] Raport 2022 z kolumną 2021
- [ ] Raport 2021 z kolumną 2020
- [ ] Saldo „Na kontach” bez zmian (6 886,72 zł przed wczytaniem 2020–2021, jeśli nic się nie zmieniło)
- [ ] Szkic 2027 bez zmian
