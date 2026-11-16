# Wczytanie roku 2019 ze starego arkusza — Plan Brief

> Full plan: `context/archive/2026-11-16-sheet-load-2019/plan.md`
> Research: `context/archive/2026-11-16-sheet-load-2019/research.md`

## What & Why

Lata 2020–2025 są już na produkcji, wczytane z arkuszy `Budżet_RRRR.xlsx` (ADR-0085, ADR-0096). Użytkownik chce mieć także 2019, żeby raporty (rok wcześniej dla 2020, widok „Lata”) miały dłuższą historię. Skoroszyt 2019 ma inny układ, a jego własna suma wydatków jest niespójna, więc trzeba go najpierw poprawić, a potem dodać czytnik.

## Starting Point

`budget:load-sheet` odrzuca plik 2019 przy rozpoznaniu układu: szuka arkuszy `styczeń`…`grudzień` i `Analiza`. Plik 2019 ma `styczeń_19`…`grudzień_19`, `grudzień_18` i `Podsumowanie`, a w jednym roku trzy warianty miesięcy (I–II, III–VI, VII–XII).

`Podsumowanie!H16` (64 035,95 zł) liczy:
- w styczniu–czerwcu plan;
- w lipcu dwie pensje;
- w sierpniu–listopadzie wykonanie z celami;
- w grudniu wykonanie bez celów.

Planer, zapis, konto arkusza i wycofanie są wspólne dla wszystkich lat i zostają.

## Desired End State

Poprawiony `Budżet_2019.xlsx` przechodzi `--dry-run` z kodem 0. Przychody to 78 039,29 zł = `Podsumowanie!I16`, wydatki 67 286,75 zł = `Podsumowanie!H16`, saldo otwarcia „Arkusz 2019” −10 752,54 zł, a w wydruku są dwie reszty bez rozpisania. Na produkcji jest Budżet 2019 w realizacji i zarchiwizowana kategoria przychodów „Stypendium”. Raport 2020 ma kolumnę 2019, a widok „Lata” zaczyna się od 2019. Lata 2020–2025 i szkic 2027 się nie zmieniają.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Prawda roku | Poprawić `Podsumowanie` u źródła (H = wykonanie, `marzec_19!B3` z „inne”) | Zasada „Analiza jest prawdą” zostaje bez wyjątku w kodzie, jak przy pliku 2021. | Research / użytkownik |
| Cele (6 144,80 zł) | Pomijane (SKIP), poza wydatkami i poza uzgodnieniem | Wpłaty na cele nie są wydatkami roku (zmiana użytkownika z „ruchu funduszu” w trakcie planowania). | Plan / użytkownik |
| Oszczędności, „z poprzedniego”, przychód „oszczędności” | Pomijane | Przeniesienia, nie przychód ani wydatek. | Użytkownik |
| Marcowe „inne” 125 zł | Przychód | Blok „Podział” je liczy; formuła sumy była za krótka. | Użytkownik |
| Szczegółowość | Listy osobiste i „Dom i akcesoria” rozbite na pozycje w kategorii wiersza | Pojedyncze pozycje można potem przenieść „Zmień kategorię” (ADR-0089). | Użytkownik |
| Rachunki VII–XII | Podział Telefony / Internet / Prąd według składników `D10` (nadpisania wydatków) | Kwoty z arkusza; lipiec ma tylko dwa składniki, więc 117,05 zł idzie do Prądu. | Plan |
| Siłownia | 49 „Sport” z produkcji (odczyt 2026-11-16) | Jedna kategoria ze sportem z lat późniejszych. | Użytkownik |
| Stypendium | Nowa zarchiwizowana kategoria przychodów tworzona przez wczytanie | Tylko dla 2019; wycofanie roku ją usuwa. | Użytkownik |
| Pozycje „do oddania” | Wczytane jak są (29) | Korekty po wczytaniu w UI. | Użytkownik |
| Czytnik | Osobna klasa dla 2019, ten sam `BudgetSheet`/`MonthSheet` | Trzy warianty w roku nie mieszczą się w `SheetLayout`; planer zostaje wspólny. | Research / plan |

## Scope

**In scope:**
- ADR-0102;
- poprawka pliku z dowodem;
- czytnik 2019;
- listy wiersza i nadpisania wydatków w planerze;
- nowe kategorie jako zbiór definicji („Kosmetyki”, „Stypendium”);
- etykiety 2019 w `mapping.php`;
- testy na PostgreSQL;
- README i roadmapa;
- wczytanie na produkcji.

**Out of scope:**
- `grudzień_18`, `Oszczędności`, `Urlopy` i bloki boczne arkusza;
- kwoty sum w kodzie;
- zmiany mapowania 2020–2025;
- zmiany schematu, UI i glosariusza;
- opcja `--year`.

## Architecture / Approach

`BudgetSheetReader` rozpoznaje skoroszyt 2019 (`Podsumowanie` i arkusze `_19`, rok z `grudzień_19!C31`) i oddaje go nowemu czytnikowi miesiąca. Ten czytnik rozpoznaje wariant po etykietach i czyta wykonanie z C, D albo B wierszy list. `MonthSheet` dostaje `rowLists` (adres wiersza → pozycje), a `SheetLoadPlanner::listedBlock` daje z nich operacje i resztę w kategorii wiersza. Nadpisania wydatków dzielą „Rachunki”. `LoadBudgetSheet`/`UnloadBudgetSheet` obsługują nowe kategorie kluczowane nazwą.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR-0102, kategorie z produkcji, poprawka pliku | Decyzje na piśmie, `H16` = 67 286,75 i `I16` = 78 039,29 w pliku | Formuły współdzielone i komórki `TODAY()` przy dowodzie poprawki |
| 2. Czytnik 2019 | Rozpoznanie, rok, trzy warianty, listy wiersza, rozpisanie XI–XII, fabryka `'2019'` | Wiersz z planem zamiast wykonania (odmowa z adresem zamiast cichego błędu) |
| 3. Planer, mapowanie, nowe kategorie | `--dry-run` prawdziwego pliku z sumami z planu; 2020–2025 bez zmian | Wycofanie „Stypendium” bez naruszenia ścieżki „Kosmetyki” |
| 4. Dokumentacja i bramka | README „lata 2019–2025”, roadmapa, `ci:check` i Sloppy | — |
| 5. Wczytanie na produkcji | Próba na kopii, produkcja, notatki | Dev DB to nie kopia produkcji; próba musi iść na odtworzonej kopii |

**Prerequisites:** lista kategorii z produkcji (już odczytana: `production-categories.md`), plik `storage/app/private/Budżet_2019.xlsx` w głównym checkoucie, stos Sail.
**Estimated effort:** ~3–4 sesje: Faza 1 z udziałem użytkownika, Fazy 2–3 to większość kodu, Faza 5 na serwerze.

## Open Risks & Assumptions

- Kwoty z planu pochodzą ze skryptów roboczych na zapisanych wartościach; żaden czytnik aplikacji jeszcze nie przetworzył pliku. Rozbieżność w `--dry-run` to STOP.
- Znak reszt (marzec −10,00, lipiec −35,00) to wniosek z researchu; potwierdza go wydruk.

## Success Criteria (Summary)

- `--dry-run` 2019 zgadza się z poprawionym `Podsumowaniem` co do grosza, a lata 2020–2025 dają te same sumy co dziś.
- Wczytanie na produkcji przechodzi „tylko dodano”, a wycofanie przywraca stan sprzed wczytania.
- Raporty pokazują 2019 obok 2020; salda kont i szkic 2027 bez zmian.
