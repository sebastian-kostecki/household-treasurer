# S-10: Plany miesięcy a budżet roczny — Plan Brief

> Full plan: `context/changes/month-plans-vs-year/plan.md`
> Research: `context/changes/month-plans-vs-year/research.md`

## What & Why

Użytkownik ma porównać plany miesięcy z budżetem rocznym (FR-010): dla każdej kategorii „wydane w roku plus plan bieżącego miesiąca względem planu rocznego”. Suma planów miesięcy jest pełna dopiero w grudniu, a ADR-0033 wymaga, żeby różnica między miesiącami a rokiem była widoczna, a nie zerowana. Porównanie nie może zmieniać budżetu rocznego.

## Starting Point

S-01/S-02 dały stronę „Plan miesiąca” z wierszami Plan / Faktycznie i odczytem planu dowolnego miesiąca (`MonthPlanProposal::reading`). Nic nie zestawia planów z rokiem. Licznik roczny `execution_minor_units` obejmuje już bieżący miesiąc, więc „licznik + plan miesiąca” liczyłby jego wydatki dwa razy.

## Desired End State

Pod każdym wierszem strony „Plan miesiąca” jest linia „Rok: 22 000,00 zł z 24 000,00 zł · zostaje na resztę roku 2 000,00 zł”. Dla dochodu jest „zostaje do otrzymania”, przy przekroczeniu „ponad plan roczny” (destructive tylko dla wydatku), w grudniu „różnica do planu rocznego”. Podczas edycji propozycji linia zmienia się od razu. Strona Budżet, Dashboard i liczby roczne zostają bez zmian.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Miejsce | Tylko strona „Plan miesiąca”, linia pod wierszem | Porównanie jest potrzebne w chwili ustalania kwot miesiąca, a S-04 i tak dokłada tam liczby roczne. | Plan |
| „Wydane w roku” | Execution przed miesiącem + składnik miesiąca wg reguły pozycji „do wydania” | Bez podwójnego liczenia, a przekroczenie planu miesiąca od razu widać w roku. | Research → Plan |
| Który miesiąc | Oglądany M, łańcuchowo: minione = wykonanie, bieżący = reguła pozycji, kolejne do M = plan lub propozycja | Odpowiada na „co zostaje po tym miesiącu” dla każdego planowanego miesiąca; dla bieżącego daje dosłowne FR-010. | Plan |
| Dochody | Tak, jako „otrzymane” i „zostaje do otrzymania”, bez koloru alarmu | FR-010 mówi „każda kategoria”, a strona Budżet traktuje dochody symetrycznie. | Plan |
| Plan roczny | Current Plan („Plan roczny” w UI) | Tę wartość UI nazywa planem rocznym i z niej liczy propozycja. | Research → Plan |
| Pokazanie różnicy | Linia tekstowa z resztą, bez paska i bez kolumny | Mieści się w siatce na telefonie i jasno mówi, że dodatnia reszta to nie oszczędność. | Plan |
| Wiersze | Każdy wiersz, który strona już pokazuje, także jednorazowe i zarchiwizowane | Jedna reguła bez ukrytych przypadków. | Plan |
| Podgląd na żywo | Serwer daje „przed M” i „do M”; Vue w edycji dodaje regułę pozycji wydzieloną z pary To Spend | Brak trzeciej kopii logiki, zgodnie z „Month logic lives twice”. | Plan |

## Scope

**In scope:**
- Reguła pozycji wydzielona w `ToSpendCalculator` (PHP + TS + oba testy).
- Nowy odczyt `App\Support\MonthPlanYearComparison` z testami jednostkowymi.
- Trzy propsy wiersza (`annual_plan_minor_units`, `year_used_before_month_minor_units`, `year_used_minor_units`) z `assertInertia`.
- Linia roczna i podpowiedź w `MonthPlan.vue`, etykiety w `lang/pl/ui.php`.
- Strona planu w `NfrPerf01Test`.

**Out of scope:**
- Strona Budżet, Dashboard, Initial Plan, wiersz Set-aside, sumy na typ, pasek postępu.
- Zwinięta sekcja jednorazowych z saldem rocznym (S-04), zatwierdzony vs bieżący plan miesiąca (S-03).
- Migracje, nowe kolumny, zmiany snapshotu, zmiany `BudgetMonthExecutionIndex`.

## Architecture / Approach

Kontroler planu buduje `MonthPlanYearComparison` z już załadowanych `MonthPlanProposal` i `MonthCategoryActuals`. Odczyt robi jedno grupowane `SUM` operacji przed pierwszym dniem min(M, f) i, gdy M jest przyszły, jedno `MonthCategoryActuals` miesiąca bieżącego. Plany miesięcy pośrednich bierze z memoizowanych odczytów. Wiersz dostaje trzy liczby. Vue pokazuje je, a w edycji propozycji składa „przed M + reguła pozycji(wpisana kwota)”.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Odczyt roczny i propsy | Reguła pozycji w parze To Spend, `MonthPlanYearComparison`, trzy propsy z testami | Granice miesięcy (f, M, budżet minionego i przyszłego roku) dające złą liczbę |
| 2. Linia roczna w widoku planu | Linia pod wierszem z podglądem na żywo, etykiety, podpowiedź, próg wydajności | Etykieta czytana jako przekroczenie albo oszczędność; ciasny wiersz na telefonie |

**Prerequisites:** S-01 (done). Sail i obraz `sail-8.5/app` do testów na PostgreSQL i podglądu z worktree.
**Estimated effort:** ~2 sesje, 2 fazy.

## Open Risks & Assumptions

- Miesiące po bieżącym liczą propozycje, które zmieniają się przy zmianie wcześniejszych planów (łańcuch propozycji). Podpowiedź musi to oddać.
- Po S-03 linia pójdzie za bieżącym planem miesiąca (`current_amount_minor_units`), tak jak „do wydania”.
- Odczyt sumuje operacje, a strona Budżet pokazuje licznik. Jeśli po `user:restore` licznik rozjedzie się z operacjami, grudzień minionego roku może różnić się od „Wykonania”. `RestoreUserData.php:209` przywraca licznik jako zapisaną wartość.
- Kategoria dodana po zatwierdzeniu miesiąca bez operacji w nim nie ma wiersza, więc nie ma też linii rocznej.

## Success Criteria (Summary)

- Planując miesiąc, użytkownik widzi na każdej kategorii, ile planu rocznego zostaje na resztę roku, i liczba reaguje na wpisane kwoty.
- Wydatki bieżącego miesiąca nie są liczone dwa razy, a przekroczenie planu rocznego przez wydatek jest wyróżnione.
- Budżet roczny, strona Budżet i Dashboard pokazują te same liczby co przed zmianą.
