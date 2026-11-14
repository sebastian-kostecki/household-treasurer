# Ranking kategorii i panel kategorii — Plan Brief

> Full plan: `context/changes/report-ranking-and-category-panel/plan.md`
> Decyzje produktowe: `context/foundation/adr/0097-report-years-ranking-and-chart-colours.md` (F-07)

## What & Why

S-35 (MS-02) zamienia raport roku w odczyt „od największych”: Rok, Miesiąc i 12 miesięcy pokazują Ranking kategorii z paskiem udziału i przełącznikiem „Kategorie | Grupy”, a w Roku nazwa kategorii otwiera Panel kategorii z jej kwotą 2026 i 2025, różnicą, udziałem, 12 miesiącami i przejściem do Operacji. To krok do celu M-4: dojść od raportu roku do szczegółu jednej kategorii (S-36 dołoży w panelu zakładkę „Lata”).

## Starting Point

Raporty pokazują tabelę grup w kolejności listy i wykres słupkowy Unovis (`ReportEntryTable`, `ReportBarChart` z placeholderem, ADR-0070/0071/0084). `PeriodReport::forYear` czyta już operacje miesiąc po miesiącu, ale oddaje tylko sumę roku; wpisy nie mają rytmu ani miesięcy, a w adresie nie ma wyboru kategorii.

## Desired End State

`/reports` pokazuje „Wydatki od największych” i „Przychody od największych” z numerami i paskami, obok panel pierwszej pozycji; klik innej kategorii zmienia panel natychmiast i zapisuje `?category=<id>`, `?ranking=groups` włącza Grupy. Na telefonie panel jest arkuszem od dołu. Wykres słupkowy nie istnieje, Trend działa jak dziś.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Gdzie Ranking i panel | Ranking w Roku, Miesiącu, 12 miesiącach; panel tylko w Roku | Miesiąc i 12 miesięcy nie mają projektu panelu | ADR-0097 |
| Kolejność | po kwocie malejąco, remis = lista, wiersze tylko z porównania na końcu | jedyny odczyt raportu niezgodny z listą | ADR-0097 |
| Znaczniki | pasek udziału i słupki miesięcy jako CSS z tokenów (`chart-1`, `success`, `foreground`, `track`), bez Unovis | znacznik w wierszu dubluje liczby; Unovis zostaje w Trendzie | ADR-0097 |
| Wybór w adresie | `?category=<id>`, cichy fallback do pierwszej pozycji; zostaje przy zmianie roku | porównanie tej samej kategorii rok do roku jednym klikiem | Plan |
| Zakładka „Lata” przed S-36 | etykieta „Miesiące 2026”, bez przełącznika; `?panel=years` zarezerwowane | żadnej martwej kontrolki | Plan |
| Telefon | panel w `ui/sheet` od dołu; `?category` otwiera go od razu | lista się nie przesuwa, zamknięcie wraca w to samo miejsce | Plan |
| Wiersz tylko z porównania (Kino) | wybieralny, panel 0,00 / 600,00 / −100,0%, bez przycisku Operacje | każdy wiersz roku działa tak samo | Plan |
| Kategorie / Grupy | `?ranking=groups`, jeden przełącznik dla obu typów, przenoszony przez okres i rok; grupy startują zwinięte | odświeżenie i zmiana okresu nie gubią trybu | Plan |
| E2E | przepisać testy wykresu na Ranking + ścieżki panelu (klik, Operacje, arkusz, zmiana roku) | wizyta klienta i arkusz są poza zasięgiem Pest i Vitest | Plan |
| Sortowanie i wybór domyślny | czysta funkcja TS (`report-ranking.ts`); serwer waliduje tylko żądane id | jedna implementacja kolejności | Plan |
| Kwoty panelu | serwer (`ReportCategoryPanel`): 12 komórek, średnia, najwyższy miesiąc, procent N−1, rytm miesiąca oglądanego | reguła „kwoty raportu na serwerze”; zero nowych zapytań o operacje | Plan |
| Wybór i tryb bez żądania | `router.replace` z Inertii 3 (`preserveScroll`, `preserveState`, `preserveFlash`) | natychmiastowa zmiana, adres wystarcza do odtworzenia | Plan |
| Zaznaczenie wyboru | `aria-current="true"` na przycisku nazwy zamiast `aria-selected` (nowy ADR-0100) | `aria-selected` wymaga ról `grid`/`listbox`/`tablist` z nawigacją strzałkami, a te kolidują z przyciskami grup | Plan review F2 |
| Aside albo arkusz | siatka na `@container` z progiem w tokenie, JS z tym samym progiem i stanem `unknown` do pierwszego pomiaru | ten sam wynik w ramce telefonu w sinku i w aplikacji, bez migania arkusza | Plan review F1, F3 |

## Scope

**In scope:**
- propsy `rankingMode` i `categoryPanel` z testami `assertInertia`; przenoszenie `category` i `ranking` przez menu roku
- Ranking z paskiem udziału i trybem Grupy w trzech okresach; usunięcie wykresu słupkowego
- Panel kategorii z układem dwóch kolumn i arkuszem; kitchen sink, strażniki, E2E, reguła `reports.md`

**Out of scope:**
- zakładka „Lata” panelu (S-36), widok „Lata” i piąty segment (S-34)
- panel w Miesiącu i 12 miesiącach; zmiany w Trendzie i filtrze Operacji
- historia przeglądarki dla wyboru (wizyty `replace`)

## Architecture / Approach

`ReportController` buduje raport roku raz i podaje go do nowego `ReportCategoryPanel`, który bierze z `PeriodReport` podział miesięczny i dokłada rytmy jednym zapytaniem. `report` zostaje w kolejności listy; klient układa z niego Ranking (`lib/report-ranking.ts`) i wybiera domyślną kategorię tą samą funkcją. Komponenty `ReportRanking*` zastępują tabelę, `ReportCategoryPanel` i `ReportMonthBars` tworzą panel; układ (aside albo Sheet) zależy od szerokości obszaru treści.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Serwer — dane panelu i parametry | `categoryPanel`, `rankingMode`, fallback `?category`, menu roku z parametrami | suma miesięcy ≠ kwota roku; dodatkowe zapytania w pętli |
| 2. Ranking zamiast tabeli i wykresu | Ranking w trzech okresach, tryb Grupy, wykres usunięty, strażniki i E2E | zgubienie czegoś, co pokazuje dzisiejsza tabela (porównanie, zarchiwizowane, link do Operacji) |
| 3. Panel kategorii w Roku | panel, słupki z kreskowaniem i listą `sr-only`, aside/Sheet, ADR-0100, scenariusze, E2E, pełna bramka | próg kontenera przy rozwiniętym sidebarze; przewijanie arkusza na telefonie |

**Prerequisites:** F-07 (ADR-0097, słownik, design system) — done; worktree oparty na `development`; baza testowa `testing_report_ranking`.
**Estimated effort:** ~3 sesje, po jednej na fazę.

## Open Risks & Assumptions

- Przyszłe miesiące roku bieżącego z operacjami datowanymi naprzód rysują się pusto (ADR-0097:23), więc słupki mogą nie sumować się do kwoty roku; komórki serwera i kwota roku zostają dokładne.
- Rytm w podpisie to rytm miesiąca oglądanego (jak w Budżecie); kategoria, która zmieniła rytm w ciągu roku, pokazuje jeden z nich.
- Próg kontenera dla dwóch kolumn (token w `:root`) przy rozwiniętym sidebarze może dać ciasny panel; sprawdzane ręcznie w fazie 3.

## Success Criteria (Summary)

- Raport roku czyta się od największej kategorii, a klik pokazuje jej miesiące i różnicę do 2025 bez przeładowania; adres odtwarza ten sam widok.
- Kwota panelu, suma 12 komórek miesięcy z serwera i suma listy Operacji z przycisku są równe co do grosza.
- Nic, co raport pokazywał dziś (grupy, zarchiwizowane, porównanie do tego samego dnia, link do Operacji w Miesiącu i 12 miesiącach), nie zginęło.
