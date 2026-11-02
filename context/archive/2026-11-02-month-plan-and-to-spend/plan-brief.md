# S-01: Plan miesiąca i liczba „do wydania” — Plan Brief

> Full plan: `context/changes/month-plan-and-to-spend/plan.md`
> Research: `context/changes/month-plan-and-to-spend/research.md`

## What & Why

Wycinek north star roadmapy M-1. Budżet roczny nie mówi, ile jeszcze można wydać w miesiącu. S-01 dodaje:

- plan miesiąca obok budżetu rocznego, z propozycją kwot, poprawianiem i zatwierdzeniem,
- jedną liczbę „do wydania”, liczoną według wzoru z PRD.

Ten przepływ najszybciej sprawdza wzór na prawdziwych danych.

## Starting Point

Kodu planu miesiąca nie ma. Plan roczny to jedna kwota na kategorii, a odczyty miesiąca (`Wynik`, `Pozostało w miesiącu`) liczą się z planu rocznego. Domena jest już rozstrzygnięta w ADR-0033 i `CONTEXT.md` (zmiana F-01).

## Desired End State

Na stronie „Plan miesiąca” użytkownik:

- widzi dla bieżącego lub przyszłego miesiąca propozycję z oznaczeniem „niezatwierdzony”,
- poprawia kwoty i od razu widzi zmianę „do wydania”,
- oznacza rachunki,
- zatwierdza plan.

Po zatwierdzeniu liczba zmienia się wraz z operacjami. Kategoria wydatku ma rodzaj „rachunek” albo „bieżąca”. Snapshot w wersji 5 obejmuje nowe dane. Dashboard i strona Budżet działają jak dziś.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Model i wzór | Month Plan obok rocznego; „do wydania” = dochody − Bill − Running(max) − Set-aside | Rozstrzygnięte w F-01 | ADR-0033 / PRD |
| Przechowywanie propozycji | Liczona przy odczycie; rekord powstaje dopiero przy zatwierdzeniu | Propozycja zawsze śledzi poprzedni miesiąc i plan roczny, a GET niczego nie zapisuje | Plan |
| Kwoty planu zatwierdzonego | Zatwierdzona i bieżąca w osobnych kolumnach, przy zatwierdzeniu równe | S-03 rozróżni je bez migracji danych | Plan (z ADR) |
| Podgląd przed zatwierdzeniem | Kopia TS wzoru „do wydania” z testami na tych samych przypadkach | Poprawek nie da się zapisać bez zatwierdzenia, więc bez podglądu zatwierdzenie byłoby na ślepo | Plan |
| Zakres miesięcy | Bieżący i przyszłe do zatwierdzenia; miniony z planem tylko do odczytu, bez planu z komunikatem | Zgodne z ADR-0033 i pozwala przygotować kolejny miesiąc | Plan |
| Miejsce | Nowa strona „Plan miesiąca” w menu, rok z sesji, `?month=N` | Nie zagęszcza strony Budżet; S-04/S-05/S-09 mają gdzie rosnąć | Plan |
| Rodzaj pozycji | Dialog kategorii + przełącznik na stronie planu (osobny endpoint, `back()`) | Rachunki oznacza się tam, gdzie widać ich wpływ (FR-018) | Plan |
| Styczeń przy duplikatach | Jedyna niezarchiwizowana albo jedyna w ogóle; w innym razie średnia z rocznego | Zgodne z `CreateBudget` i nigdy nie liczy podwójnie | Plan |
| Średnia z rocznego | Własna arytmetyka w nowej klasie, `MonthRemainingCalculator` nietknięty | Nie otwieramy bliźniaczej pary PHP/TS z `AGENTS.md` | Plan |
| Set-aside | 0 w S-01, bez kolumny | Reguła i pole należą do S-02 | F-01 brief |

## Scope

**In scope:**

- kolumna `categories.month_item_kind` (Bill/Running, domyślnie Running), walidacja, kopiowanie na nowy rok, pole w dialogu
- tabele `month_plans` i `month_plan_items`, propozycja z reguły ADR-0033, faktyczne kwoty miesiąca, wzór „do wydania”, zatwierdzenie
- strona „Plan miesiąca” z podglądem TS, przełącznikiem rodzaju i menu
- snapshot v5, przywracanie wersji 1–4, aktualizacja README i `.docs/prd.md`

**Out of scope:**

- „do wydania” na Dashboardzie i stronie Budżet (S-06)
- odkładam/dobieram (S-02)
- zmiana planu zatwierdzonego (S-03)
- odchylenia, sekcja jednorazowych i tempo (S-04, S-05)
- powiadomienia (S-07)
- ostrzeżenia przy operacjach (S-08)
- podsumowanie (S-09) i porównanie z rokiem (S-10)
- zapis szkicu propozycji
- unikalność nazwy kategorii

## Architecture / Approach

Na serwerze są trzy czyste odczyty:

- `MonthPlanProposal`: zapisany plan zatwierdzony, albo „brak” dla minionego miesiąca, albo rekurencyjna propozycja ze źródłem w poprzednim miesiącu lub grudniu poprzedniego budżetu,
- `MonthCategoryActuals`: jedno zapytanie agregujące miesiąca,
- `ToSpendCalculator`: wzór.

Do tego akcja `ApproveMonthPlan` w transakcji. `MonthPlanController` przekazuje wiersze z planem, kwotą faktyczną i flagą „była kwota dodatnia” oraz liczbę z serwera. `to-spend-calculator.ts` przelicza ją przy edycji.

Przykład kontrolny: Pensja 8000 bez wpływu, Czynsz (Bill) wpłacony 1950, Jedzenie (Running) 1500 przy 600 wydanych, Paliwo 400 przy 520, One-off 0 przy 300. Wynik: **3730,00**.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Rodzaj pozycji na kategorii | `month_item_kind` z walidacją, kopiowaniem i polem w dialogu | Rozjazd z wzorcem `is_paced` (dochód/archiwalna) |
| 2. Model planu i odczyty serwera | Tabele, propozycja, kwoty faktyczne, wzór, zatwierdzenie z testami | Błąd w łańcuchu propozycji lub w styczniu podważa resztę mapy |
| 3. Strona „Plan miesiąca” | Widok, edycja, podgląd TS, przełącznik rodzaju, menu | Utrata niezapisanych kwot przy przełączniku; rozjazd PHP/TS |
| 4. Snapshot v5 i dokumenty | Backup/restore planów i rodzaju; wersje 1–4 działają | Wersja 4 wypadnie z listy przy podbiciu stałej |

**Prerequisites:** F-01 zamknięte. Worktree po krokach z `AGENTS.md` (`composer install`, `.env`, `key:generate`, `npm ci`, `npm run build`). Kontener PostgreSQL Sail uruchomiony do testów na pgsql.
**Estimated effort:** ~3–4 sesje, 4 fazy. Najcięższa jest faza 2.

## Open Risks & Assumptions

- Gdy bieżący miesiąc jest niezatwierdzoną propozycją bez źródła, kategoria dodana w jego trakcie dostaje średnią, a nie 0. To dosłowne czytanie ADR-0033: po zatwierdzeniu nowe kategorie dołączają z 0.
- Miesiąc, który minął niezatwierdzony, nie zostawia śladu, więc kolejny dostaje średnią z rocznego (w grudniu z resztą). Taki jest koszt wybranego „liczona, zapis przy zatwierdzeniu”.
- Przez „bieżący miesiąc” rozumiemy dzień w strefie UTC, jak na Dashboardzie i stronie Budżet.
- Kopia TS wzoru to kolejna bliźniacza logika miesiąca. Zapisujemy ją w `AGENTS.md` obok `MonthRemainingCalculator`.

## Success Criteria (Summary)

- Od pierwszego dnia miesiąca strona planu pokazuje „do wydania” z oznaczeniem „niezatwierdzony”, a poprawki zmieniają liczbę przed zatwierdzeniem.
- Po zatwierdzeniu i każdej operacji liczba zgadza się z wzorem: przykład kontrolny 3730,00 w testach PHP i TS.
- Budżet roczny, Dashboard, strona Budżet i przywracanie snapshotów 1–4 działają jak dotąd.
