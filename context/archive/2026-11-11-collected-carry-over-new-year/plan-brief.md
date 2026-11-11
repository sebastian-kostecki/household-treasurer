# Zebrane startowe z 31 grudnia w Budżecie — Plan Brief

> Full plan: `context/changes/collected-carry-over-new-year/plan.md`

## What & Why

Na ekranie Budżet kategoria nieregularna (prąd, rekolekcje) pokazuje pod nazwą „zebrane startowe” roku: stan zebranego na 31 grudnia roku poprzedniego. To FR-012 z PRD v3 i wycinek S-27 roadmapy. Użytkownik układa plan 2027 w grudniu i musi widzieć, z jakim buforem kategoria wchodzi w nowy rok; pierwsze prawdziwe przejście roku wypada na przełomie 2026 i 2027.

## Starting Point

Łańcuch zebranego przechodzi przez 31 grudnia już od S-26: `IrregularCollected` podaje zebrane na początek dowolnego miesiąca dowolnego roku, stałą liczbą zapytań. Strona Budżet tej liczby nie pokazuje, a pod nazwą kategorii w wierszu Budżetu nie stoi dziś nic.

## Desired End State

W szkicu i w budżecie w realizacji wiersz kategorii, której styczeń należy do okresu nieregularnego, ma podpis „zebrane startowe 600,00”. Dopóki rok budżetu się nie zaczął, podpis kończy się słowem „prognoza” i zmienia się z każdym grudniowym rachunkiem. Liczba równa się „zebranemu” z wiersza tej kategorii w planie grudnia, co do grosza.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego |
| --- | --- | --- |
| W których budżetach | Szkic i realizacja | W samym szkicu liczba znikałaby przy starcie realizacji, czyli wtedy, gdy staje się ostateczna. |
| Prognoza przed 1 stycznia | Słowo „prognoza” przy liczbie | Ostrzeżenie stoi tam, gdzie użytkownik czyta liczbę, z której ustawia plan, i znika samo po końcu roku. |
| Miejsce w wierszu | Podpis pod nazwą kategorii | Ten sam wzór co „zebrane” w planie miesiąca; liczba dotyczy 2–3 kategorii, więc nie dostaje kolumny. |
| Stawka na ekranie Budżet | Nie pokazujemy | FR-012 wymaga tylko zebranego; stawkę pokazuje plan miesiąca dokładnie, co do miesiąca. |
| Kiedy podpis jest | Gdy styczeń roku należy do okresu nieregularnego | Kategoria oznaczona w trakcie roku nie ma zebranego na 1 stycznia; zero w okresie pokazuje 0,00. |
| Źródło liczby | Odczyt z istniejącego łańcucha, nic nie jest zapisywane | Budżet 2027 powstaje, gdy zebrane jeszcze się zmienia (PRD v3, FR-012). |

## Scope

**In scope:**

- ADR-0076 i słownik: co „zebrane startowe” znaczy na ekranie Budżet
- Klasa odczytu w `app/Support` i pole `collected_at_year_start` w wierszu Budżetu (PHP i typ TS)
- Podpis w `BudgetCategoryRow.vue`, teksty w `lang/pl/ui.php`
- Stany w `/dev/kitchen-sink/budget`, zrzuty, E2E historii FR-012
- Reguły `.ai/rules`, `design-system.md`, roadmapa

**Out of scope:**

- Stawka, osobna kolumna i suma zebranego w grupach, „Razem” i panelu miar
- Zebrane na Dashboardzie, przy saldach i na liście „Kategorii”
- Zapis liczby przy tworzeniu budżetu, zmiana okna „Nowy budżet”
- Zmiana reguł łańcucha, migracja, snapshot

## Architecture / Approach

Kontroler Budżetu ma już załadowane rytmy i dzisiejszą datę. Nowa klasa `BudgetCollectedAtYearStart` pyta `CategoryRhythms`, czy styczeń roku budżetu należy do okresu nieregularnego kategorii, a kwotę bierze z `IrregularCollected::collectedAtStartOfMonth(kategoria, rok, 1)`. Wynik `{ minor_units, is_forecast } | null` trafia do wiersza w propsach; `is_forecast` to „rok budżetu późniejszy niż rok dzisiejszej daty”. Wiersz z odczytem przełącza kafelek kategorii na rozmiar `lg` i pokazuje podpis w slocie, jak plan miesiąca. Nie ma kopii TS kalkulatora, bo edycja planu w szkicu nie zmienia liczby.

## Phases at a Glance

| Faza | Co dostarcza | Główne ryzyko |
| --- | --- | --- |
| 1. ADR-0076, słownik i odczyt w propsach Budżetu | Decyzje zapisane przed kodem; pole w wierszu po obu stronach; testy na PostgreSQL z ręcznie policzonymi kwotami | Pomylenie zera z brakiem okresu albo warunku prognozy z fazą budżetu |
| 2. Podpis w wierszu, kitchen sink, E2E i bramka | Widoczny podpis w szkicu i realizacji; stany w kitchen sinku; E2E; dokumenty | Wyższy wiersz i dłuższy podpis na 390 px; wiersze bez podpisu muszą zostać jak dziś |

**Prerequisites:** S-20 i S-26 scalone na `development` (gałąź worktree stoi na `a99d276`); zależności worktree zainstalowane wg AGENTS.md (hook zgłosił błąd `composer install`).
**Estimated effort:** ~2 sesje, po jednej na fazę.

## Open Risks & Assumptions

- Prognoza liczy niezatwierdzony grudzień ze stawki plan ÷ 12 z dzisiejszego planu; zatwierdzenie grudnia z inną kwotą zmieni liczbę. To reguła łańcucha z ADR-0066, nie błąd.
- Rok poprzedni bez budżetu albo ze szkicem ma stawkę 0, więc zebrane stoi (ADR-0073). Nie sprawdzono, czy szkic może mieć operacje; nie wpływa to na styczeń roku, którego budżet oglądamy.
- Ta sama nazwa „zebrane startowe” oznacza w „Kategoriach” kwotę wpisaną przy oznaczeniu, a w Budżecie odczyt na 31 grudnia. Rozróżnia je słownik i podpowiedź podpisu.
- Test wydajności strony Budżet nie ćwiczy nowego odczytu; kosztu pilnuje test stałej liczby zapytań.
- Główny checkout ma niezacommitowaną zmianę `context/foundation/roadmap.md`; ten plan też zmienia roadmapę, więc scalenie może wymagać ręcznego połączenia.

## Success Criteria (Summary)

- W grudniu 2026 szkic 2027 pokazuje przy kategorii nieregularnej „zebrane startowe X · prognoza”, a X równa się „zebranemu” z planu grudnia.
- Po 1 stycznia 2027 ta sama liczba stoi bez słowa „prognoza”, także po rozpoczęciu realizacji budżetu.
- Kategorie bez okresu w styczniu i wszystkie pozostałe wiersze Budżetu wyglądają jak przed zmianą.
