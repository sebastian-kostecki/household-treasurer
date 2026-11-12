# Wczytanie na produkcji — notatki z 2026-11-12

Stan na chwilę przerwania przez użytkownika. Niczego z arkusza nie wczytano na produkcji.

## Produkcja (halina175, `/srv/apps/household-treasurer`)

- Wdrożone `main` = `b72673c` (scalenie `worktree-legacy-budget-import` → `development` `3a97c21` → `main`). Migracje `2026_11_12_094753_add_default_set_aside_percent_to_users_table` i `2026_11_12_120000_add_sheet_year_to_accounts_table` wykonane; aplikacja poza trybem serwisowym; pliki `control-numbers/20261112-181833-before/after.json` z `deploy.sh`.
- Kont arkusza: 0. „Konto Osobiste” (id 5): saldo bieżące 6 886,72 zł, bez zmian.
- Decyzja użytkownika: wariant z ADR-0085, osobne zarchiwizowane konto „Arkusz RRRR” na każdy rok (nie „Konto Osobiste”).
- Pliki na serwerze: `storage/app/private/sheet-load/Budżet_{2022,2023,2024,2025}.xlsx` (właściciel sail, 640), skopiowane z `C:\Users\sebas\Downloads`. SHA-256 zgodne z lokalnymi.
- Zabezpieczenie przed zapisem (zrobione, przed wczytaniem): `user:backup 1` → `storage/app/private/user-snapshots/1-20261112182051.json`; `app:control-numbers` → `storage/app/private/control-numbers/20261112-sheet-load-before.json`. To jest plik „przed” do `--compare … --allow-additions` i do wycofania.
- `budget:load-sheet 1 <plik> --dry-run` dla wszystkich czterech lat: kod 1, `styczeń!D3: tekst „=…+…” w komórce kwoty`. Nic nie zapisano.

## Wersje pliku 2025

`Budżet_2025.xlsx` ma dwie wersje: `Downloads` (2026-11-12 09:58, SHA `1da883ff…`, ta jest na serwerze) i `storage/app/private` checkoutu głównego (2026-11-07, SHA `837093fd…`). 2022–2024 są identyczne w obu miejscach. Wybrana nowsza; `--dry-run` i tak sprawdza sumy z „Analizą” co do grosza.

## Błąd 1: formuły czytane jako tekst (naprawiony)

- Przyczyna: `SheetCells::value()` brał `getValue()`, który dla komórki z formułą (typ `f`, np. `=569.48+495.94`) zwraca tekst formuły, nie zapisaną wartość. Kontrakt fazy 4 mówi „zapisane wartości wyliczone, bez przeliczania”.
- Odtworzone lokalnie w obrazie `sail-8.5/app` na prawdziwym pliku 2022: `getValue()` = `"=569.48+495.94"`, `getOldCalculatedValue()` = `1065.42`.
- Test regresji (najpierw czerwony z tym samym komunikatem co na produkcji, potem zielony): `tests/Unit/BudgetSheetReaderTest.php`, „reads the stored value of a formula cell…”.
- Poprawka: `app/Support/SheetLoad/SheetCells.php`; dla formuły `getOldCalculatedValue()`, a gdy plik nie ma zapisanej wartości, odmowa z adresem komórki („otwórz plik w Excelu i zapisz go ponownie”).
- `tests/Unit/BudgetSheetReaderTest.php` i `tests/Unit/SheetLoadPlannerTest.php`: 29 passed. Pint i PHPStan (`app/Support/SheetLoad`) czyste. Pełna bramka po tej poprawce nie była uruchomiona.
- Wniosek o procesie: punkty Progress 5.4 i 5.5 („`--dry-run` czterech prawdziwych plików”) są odhaczone, ale na tym kodzie nie mogły przejść. Syntetyczne skoroszyty z `SheetWorkbookFactory` miały same wartości, bez formuł.

## Błąd 2: odczyt prawdziwego pliku trwał ponad 5 minut (naprawiony)

- Przyczyna: pętle w `MonthSheetReader` wołają `$cells->highestRow()` w warunku każdego obrotu, a `Worksheet::getHighestDataRow()` przechodzi po wszystkich komórkach arkusza: koszt kwadratowy, niewidoczny na małych skoroszytach testowych.
- Poprawka: `SheetCells::highestRow()` liczy wynik raz na arkusz (`??=`).
- Pomiar na prawdziwym pliku 2022 po obu poprawkach: odczyt 23,5 s, plan 0,0 s, 402 operacje, 3 ostrzeżenia. Wcześniej ponad 5 minut na 100% CPU bez wyniku.


- Test regresji: `tests/Unit/BudgetSheetReaderTest.php`, „reading a month walks the sheet for its last row once…” (liczy wywołania `getHighestDataRow()` na podklasie `Worksheet`); bez poprawki 53 przejścia, z poprawką 1.

## Błąd 3: rozpisanie Codziennych 2022 nie było czytane (naprawiony)

- Objaw: plan 2022 po poprawkach 1 i 2 zgadzał się z „Analizą”, ale nie miał ostrzeżenia `czerwiec!D13` (Codzienne 2 154,77 przy rozpisaniu 674,52, `plan.md:13`) ani kategorii Dzieci (32); całe Codzienne szły do Artykułów spożywczych (8). Kontrola sum tego nie łapie, bo przesuwa kwoty między kategoriami bez zmiany sumy.
- Przyczyna: czytnik szukał w G etykiety „codzienne”, a prawdziwe arkusze 2022 mają w każdym miesiącu „Codzienne wydatki” (G25), pozycje G26:G31 i pod nimi „Do kiedy wydatki” z datą w I32 (44 592…). `SheetWorkbookFactory` wpisywała „Codzienne” w G24 bez wiersza z datą. Lata 2023–2025 nie mają bloku w G (sprawdzone skanem wszystkich miesięcy).
- Poprawka: `MonthSheetReader::dailyBreakdown()` przyjmuje „codzienne wydatki” i kończy listę na pustym G albo na „Do kiedy wydatki”; fabryka odwzorowuje prawdziwy układ. Testy 2022 czytnika i planu były czerwone (3) przed poprawką czytnika.
- Przy tej okazji: `database/sheet-load/mapping.php` miał `'słodycze i inne' => 29`, a decyzja 4 planu (`plan.md:62`, tabela `plan.md:287`) mówi „Higiena i kosmetyki → 29, Pieluchy → 32, reszta → 8”. Poprawione na 8; asercja testu planu z fazy 5 kodowała błędne mapowanie (29: 25 000 gr) i teraz sprawdza decyzję 4 (29: 15 000, 8: 100 000), czerwona przed zmianą mapowania.

## Plan czterech prawdziwych plików po poprawkach 1–3

| Rok | Przychody = Analiza | Wydatki = Analiza | Operacji | Saldo otwarcia „Arkusz RRRR” | Ostrzeżenia |
| --- | --- | --- | --- | --- | --- |
| 2022 | 106 233,93 | 101 219,38 | 438 | −5 014,55 | czerwiec D31, D13, D17, D19 + 4 reszty |
| 2023 | 117 847,66 | 122 115,75 | 432 | 4 268,09 | przeniesienie czerwiec!I4 −293,68 → sierpień |
| 2024 | 110 559,17 | 126 760,34 | 398 | 16 201,17 | wrzesień!D31 bilans 86,58 |
| 2025 | 134 670,58 | 134 852,23 | 381 | 181,65 | brak |

Odczyt każdego pliku ok. 22 s. Kategorie z mapowania sprawdza na bazie dopiero `--dry-run` na produkcji lub próba.

## Dalsze kroki

1. Plan na czterech prawdziwych plikach lokalnie: sumy = „Analiza”, ostrzeżenia 2022 czerwiec i 2024 wrzesień.
2. Commit poprawek (`fix(legacy-budget-import): …`), pełna bramka (`ci:check` na `testing_legacy_budget_import`, Sloppy), scalenie do `development` i `main`, `scripts/deploy.sh` (bez migracji).
3. Na produkcji `--dry-run` czterech plików (kod 0, kategorie zgodne), wczytanie 2022 → 2025 po potwierdzeniu użytkownika, `app:control-numbers --compare=storage/app/private/control-numbers/20261112-sheet-load-before.json --allow-additions`, przegląd w aplikacji.
4. Jeśli między krokiem 2 a 3 ktoś pracował w aplikacji, zrobić nowy snapshot i nowy plik „przed”.
