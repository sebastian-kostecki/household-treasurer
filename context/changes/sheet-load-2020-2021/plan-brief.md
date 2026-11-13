# Wczytanie lat 2020 i 2021 ze starszych arkuszy budżetu — Plan Brief

> Full plan: `context/changes/sheet-load-2020-2021/plan.md`
> Research: `context/changes/sheet-load-2020-2021/research.md`

## What & Why

Sheet Load (ADR-0085) wczytał na produkcję lata 2022–2025. Lata 2020 i 2021 mają być w aplikacji tak samo, żeby raporty, kolumna roku poprzedniego i selektor roku sięgały dalej wstecz. Dziś oba pliki są odrzucane („Ustawienia: brak arkusza”), bo mają starszy układ, a 2021 ma dodatkowo błąd formuły w „Analizie”.

## Starting Point

Czytnik zna tylko układ 2022–2025: rok z `Ustawienia!B2`, przychody w kolumnie I, listy w J–M, blok „Leczenie prywatne” z nagłówkiem „nazwa”. Planner odmawia na każdą nieznaną etykietę i na każdą różnicę z `Analiza!B16`/`C16`, a jedyny cel „bez operacji” (`SKIP`) zakłada, że „Analiza” tej kwoty nie liczy.

## Desired End State

`--dry-run` obu plików kończy się kodem 0 bez ostrzeżeń i reszt: 2020 przychody 69 913,54 + ruchy funduszu 4 098,28 = 74 011,82, wydatki 65 239,23 + 4 318,05 = 69 557,28; 2021 przychody 91 448,99 + 4 900,00 = 96 348,99, wydatki 84 700,99 + 5 510,56 = 90 211,55. Pliki 2022–2025 dają te same sumy i wydruk co dziś. Na produkcji są Budżety 2020 i 2021 na kontach „Arkusz 2020/2021”, a liczby kontrolne pokazują tylko dodania.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Błąd `Analiza!C16` 2021 (537,99 zł) | Poprawka pliku u źródła: `E30` lip–gru i zależne komórki, z kopią oryginału i dowodem zmian | „Analiza jest prawdą” zostaje bez wyjątków w kodzie | Plan (użytkownik) |
| Fundusz celowy i „przychody rozłożone” | Ruch funduszu: bez operacji, ale liczony do uzgodnienia z „Analizą” (nowy cel `FUND`) | Przychody to zarobki, wydatki to realne zakupy, a sumy dalej zgadzają się co do grosza | Plan (użytkownik) |
| Nadpłata kredytu 2020 (2 456,89 zł) | Ruch funduszu | W 2021 ten sam ruch jest w pomijanym bloku oszczędności | Plan (użytkownik) |
| „pozostałe (premie, 13)” 2021 (5 499,05 zł) | Wynagrodzenie Sebastian (1); „międzyokresowe” −18,87 też tam | Raport wynagrodzenia pełny; ujemna komórka musi mieć źródło z dodatnią sumą | Plan (użytkownik) |
| Pozostałe nowe etykiety (29 razem) | Tabela w Fazie 3 planu (np. hbo go → Rozrywka, bilety i transport → Bilety i parking, kary → Pozostałe) | Oparte na istniejących wpisach mapowania i nazwach pozycji z arkuszy | Plan (użytkownik) |
| Rozpoznanie układu i rok | Brak `Ustawienia` + „kategoria” w `styczeń!I3`; rok z daty w `grudzień!A1` | Oba pliki mają tam 1 grudnia roku, więc bez opcji `--year` | Plan |
| „Lekarz - dentysta” | Rozbity na pozycje listy „Lekarze - dentysta” jak „Leczenie prywatne” | Decyzja 7 ADR-0085: wizyty jako osobne operacje z nazwami | Plan |
| Styczeń–kwiecień 2020 bez bloku medycznego | Lista nieregularna kończy się na pierwszym pustym wierszu I–L | Niżej leżą niepowiązane listy (`styczeń!K48`) | Research + Plan |
| Rok 2019 | Poza zakresem | Zupełnie inny skoroszyt | Research (użytkownik) |

## Scope

**In scope:** ADR-0092; poprawka `Budżet_2021.xlsx`; układ 2020–2021 w czytniku (`SheetLayout`); cel `FUND` w plannerze i wydruku komendy; 29 etykiet mapowania; fixture `'2020'` z formułami; testy; README; wczytanie na produkcji.

**Out of scope:** 2019; korekty kwot w kodzie; opcja `--year`; mapowanie per rok i zmiany wpisów 2022–2025; zmiany zapisu i wycofania, schematu, UI, glosariusza, rytmu „Kosmetyki”.

## Architecture / Approach

Enum `SheetLayout` (`Since2022`, `Before2022`) niesie kolumny i etykiety bloków; `BudgetSheetReader` rozpoznaje układ i rok, `MonthSheetReader` czyta wszystko z układu. `SheetMapping::FUND` daje w `SheetLoadPlan` listę ruchów funduszu; uzgodnienie = operacje + ruchy funduszu = „Analiza”, a saldo otwarcia i zapis dalej liczą tylko operacje, więc `LoadBudgetSheet` się nie zmienia.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR-0092 i poprawka pliku 2021 | Decyzje przed kodem; plik z `C16` = 90 211,55 i dowodem, że zmieniło się tylko to | Edycja XML psuje plik albo pomija zależną komórkę (formuły współdzielone) |
| 2. Czytnik układu 2020–2021 | Rozpoznanie, rok, kolumny, opcjonalny blok medyczny, „Na tydzień”; testy najpierw | Regres w układzie 2022–2025 |
| 3. Planner, mapowanie, ruchy funduszu | `FUND`, 29 etykiet, wydruk, `--dry-run` sześciu prawdziwych plików | „Analiza” nie liczy któregoś wiersza funduszu tak, jak ustalono → różnica sum |
| 4. Dokumentacja i bramka | README 2020–2025, `ci:check` na PostgreSQL, Sloppy | Nowe znalezisko Sloppy w czytniku |
| 5. Wczytanie na produkcji (ręczne) | Budżety 2020 i 2021 na produkcji, dowód „tylko dodano” | Na serwer trafia niepoprawiony plik 2021 (sprawdzenie SHA-256) |

**Prerequisites:** pliki `Budżet_2020.xlsx` i `Budżet_2021.xlsx` w `storage/app/private` checkoutu głównego; obraz `sail-8.5/app` i sieć `household-treasurer_sail`; baza `testing_sheet_load_2020_2021`; Excel do ręcznego sprawdzenia poprawionego pliku.
**Estimated effort:** ~3–4 sesje: faza 1 krótka, fazy 2–3 to główna praca, faza 5 to jedno okno produkcyjne.

## Open Risks & Assumptions

- Kwoty operacji w tabeli wyżej wyliczono z sum wierszy funduszu w planowaniu; autorytetem jest `--dry-run`, a różnica to STOP i badanie, nie dopasowanie mapowania.
- Zakładamy, że Excel po otwarciu poprawionego pliku (z `fullCalcOnLoad`) nie zmieni wartości czytanych przez loader; jeśli plik zostanie zapisany w Excelu, `--dry-run` sprawdza sumy ponownie.
- Lekcja z 2022–2025: przebiegi na prawdziwych plikach muszą się odbyć i trafić do raportu fazy, nie tylko do checkboxa.

## Success Criteria (Summary)

- `--dry-run` 2020 i 2021 zgodny z „Analizą” co do grosza (operacje + ruchy funduszu), bez ostrzeżeń i reszt; 2022–2025 bez zmian.
- Lata 2020 i 2021 widoczne na produkcji w raportach i selektorze roku, a `--compare --allow-additions` kod 0.
