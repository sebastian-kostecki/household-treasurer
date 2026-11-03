# S-10: Plany miesięcy a budżet roczny — Plan Brief

> Full plan: `context/changes/month-plans-vs-year/plan.md`
> Research: `context/changes/month-plans-vs-year/research.md`

## What & Why

FR-010: użytkownik ma porównać plany miesięcy z budżetem rocznym. PRD rozstrzyga to tak: dla każdej kategorii pokazujemy wydane w roku plus plan bieżącego miesiąca względem planu rocznego. ADR-0033 dodaje, że różnica między miesiącami a rokiem ma być widoczna. Dziś aplikacja nie pokazuje jej nigdzie, poza „Pozostałym limitem rocznym” jednorazowych. Porównanie nie może zmieniać budżetu rocznego.

## Starting Point

Obie strony porównania już istnieją. Po stronie rocznej to plan bieżący i licznik wykonania kategorii. Po stronie miesiąca to `MonthToSpend` z odczytem planu (propozycja albo zatwierdzony) i kwotami faktycznymi. Reguła kwoty pozycji siedzi w pętli `ToSpendCalculator`. Daty operacji nie mogą być z przyszłości, więc dla bieżącego miesiąca wykonanie przed miesiącem = licznik − kwota faktyczna w miesiącu.

## Desired End State

Na „Planie miesiąca”, w widoku bieżącego miesiąca, każda pozycja przychodu i wydatku ma linię „W roku: X z Y · zostaje Z”, a każda sekcja wiersz „Razem w roku”. Wydatek ponad rok jest w kolorze destructive, przychód ponad plan roczny w kolorze success. Jednorazowe na liście mają linię roczną zamiast „Pozostałego limitu rocznego”, który zostaje w zwiniętej sekcji bezczynnych. Inne miesiące wyglądają jak dziś.

Przykład: „Jedzenie”, plan roczny 12 000 zł, sty–paź 10 000 zł, plan listopada 1 000 zł. Przy 400 zł wydanych w listopadzie: „W roku: 11 000 z 12 000 · zostaje 1 000”. Przy 1 300 zł: 11 300, zostaje 700.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| -------- | ------ | ---------------- | ------ |
| Formuła „W roku” | Wykonanie przed bieżącym miesiącem + kwota pozycji wg reguły „do wydania” (bieżąca: max(plan, faktyczna); rachunek/przychód: plan, po zapłacie/wpływie faktyczna) | Brak podwójnego liczenia bieżącego miesiąca, a przekroczenie miesiąca od razu widać w roku; ta sama reguła co „do wydania” | Plan |
| Wykonanie przed miesiącem | `execution_minor_units` − kwota faktyczna w miesiącu | Bez nowego zapytania; zgodne z „Wykonaniem” na stronie Budżet; poprawne, bo daty ≤ dziś i rok daty = rok budżetu | Research + Plan |
| Stan planu | Propozycja i plan zatwierdzony (kwota bieżąca) | ADR-0033: „miesiąc ma plan” obejmuje propozycję | Research |
| Kategorie | Przychody i wydatki, w tym jednorazowe i zarchiwizowane z wierszem | Widać też, czy przychody roku się dopinają; układ dwóch sekcji jak dziś | Plan |
| Miejsce | Plan miesiąca, tylko bieżący miesiąc | Skutek dla roku widać tam, gdzie ustalasz kwotę; bez nowej trasy i nawigacji | Plan |
| Prezentacja | Linia w wierszu + „zostaje” + wiersz „Razem w roku” w każdej sekcji | Odpowiada na „ile zostaje na resztę roku” i pokazuje różnicę z ADR-0033 także w sumie | Plan |
| Sygnał | Wydatek ponad rok: destructive; przychód ponad plan roczny: success; przychód poniżej: bez koloru | Wzorzec kolorów odchylenia z S-04 | Plan |
| Jednorazowe | Linia roczna zastępuje „Pozostały limit roczny” na liście; limit zostaje w zwiniętej sekcji i w innych miesiącach | Jedna liczba roczna przy wierszu; FR-008 nadal spełnione | Plan |
| Edycja | Bez kopii TS; linia przyciemniona, dopóki kwota nie jest zapisana | Zasada „month logic lives twice”; mniej logiki do utrzymania | Plan |
| Sumy | Z wszystkich wierszy sekcji wysłanych przez serwer, także ukrytych kandydatów do dołączenia | Ich plan roczny należy do roku | Plan |

## Scope

**In scope:**
- `ToSpendCalculator::itemMinorUnits` wydzielone z pętli „do wydania”
- nowa czysta klasa `MonthYearComparison` (wiersz i sumy)
- pola `year_comparison` (wiersz) i `year_totals` (plan) tylko dla bieżącego miesiąca, typy TS
- linia roczna w `MonthPlanItemRow`, wiersz sumy w sekcjach, przyciemnienie w edycji, tłumaczenia PL
- termin „Year after the Month” w `CONTEXT.md`, testy Unit i Feature

**Out of scope:**
- porównanie dla innych miesięcy i prognoza do końca roku
- wczesne ostrzeżenia i powiadomienia Dashboardu
- przeliczanie na żywo w edycji
- zmiany na stronie Budżet, w nawigacji i w trasach
- plan vs plan oraz odkładanie w skali roku (S-04, S-09)
- schemat bazy i zapisy

## Architecture / Approach

`MonthPlanController` ma już `MonthToSpend` (odczyt planu + kwoty faktyczne) i kategorie z planem rocznym i licznikiem wykonania. Dla `timing === 'current'` woła czystą funkcję `MonthYearComparison::forCategory(category, rowAmounts)`, która używa `ToSpendCalculator::itemMinorUnits`, oraz `totals(...)` dla każdej sekcji. Vue tylko wyświetla pola z serwera. Nie ma nowych zapytań ani zapisów.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| ----- | ---------------- | -------- |
| 1. Odczyt porównania na serwerze | Reguła pozycji, klasa porównania, pola payloadu, typy TS, testy, termin w `CONTEXT.md` | Regresja „do wydania” po wydzieleniu reguły; testy muszą ustawiać licznik wykonania ręcznie |
| 2. Widok porównania na Planie miesiąca | Linia roczna, zastąpienie limitu u jednorazowych, sumy sekcji, przyciemnienie, tłumaczenia | Zatłoczony wiersz przy 360 px; mylenie nieaktualnej linii w edycji |

**Prerequisites:** S-01 (plan miesiąca i „do wydania”) i S-04 (pozycje, limit jednorazowych), oba zarchiwizowane; Sail z PostgreSQL do testów.
**Estimated effort:** ~1–2 sesje w 2 fazach.

## Open Risks & Assumptions

- Tożsamość „przed miesiącem = licznik − faktyczna” zakłada brak operacji z datą po dziś (walidacja w ręcznym wpisie, przebudowie i Imporcie). Gdyby to się zmieniło, formuła wymagałaby zapytania z datą.
- Zarchiwizowana kategoria bez kwoty i bez operacji w bieżącym miesiącu nie ma wiersza i nie wchodzi do „Razem w roku”, więc suma może się różnić od strony Budżet z „pokaż zarchiwizowane”.
- W trybie edycji linia roczna pokazuje wartość zapisaną (przyciemnioną), a nie wpisaną.

## Success Criteria (Summary)

- W bieżącym miesiącu każda pozycja i każda sekcja pokazują „W roku … z … · zostaje …” zgodne z ręcznym rachunkiem i z kolumnami strony Budżet.
- Wydatek ponad rok jest widoczny jako destructive na wierszu i w sumie, bez żadnej zmiany budżetu rocznego.
- Inne miesiące i liczba „do wydania” zachowują się jak przed zmianą.
