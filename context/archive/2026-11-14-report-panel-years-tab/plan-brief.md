# Zakładka „Lata” w panelu kategorii — Plan Brief

> Full plan: `context/changes/report-panel-years-tab/plan.md`
> Decyzje produktowe: `context/foundation/adr/0097-report-years-ranking-and-chart-colours.md` (F-07)

## What & Why

S-36 (M-4, MS-03) łączy tabelę „Lata” (S-34) z Panelem kategorii widoku Rok (S-35). Panel dostaje przełącznik „Miesiące 2026 | Lata”. Nazwa kategorii w tabeli „Lata” otwiera jej panel w Roku bieżącym na zakładce „Lata”. To ostatni krok warunku „Done when” kamienia M-4: od porównania kategorii przez lata do szczegółu tej kategorii.

## Starting Point

Panel pokazuje 12 miesięcy i etykietę „Miesiące 2026”; `?panel=years` jest tylko zarezerwowane. Tabela „Lata” ma nazwę jako zwykły tekst. Odczyt `ReportYears` liczy lata stałą liczbą zapytań, ale widok Rok go nie czyta. Wybrany rok jest w sesji, a `POST budget/year` już przenosi `category` i `ranking` do Raportów.

## Desired End State

`/reports?category=<id>&panel=years` pokazuje w panelu 7 słupków lat 2020–2026 z tego samego odczytu co tabela, liczonych do tego samego dnia (w Roku przeszłym pełne lata, równe kafelkom). Pod słupkami są „Zmiana od 2020”, „Najwięcej: <rok> · kwota” i „Każdy rok do 14 listopada”. Przełączanie zakładki jest natychmiastowe, a zakładka przetrwa odświeżenie i zmianę roku. W tabeli „Lata” nazwa kategorii z wierszem w roku docelowym jest linkiem z podpowiedzią w stopce, a pozostałe zostają tekstem.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Cel linku, słupki 2020–2026, „Do D” domyślnie, tokeny | Rok bieżący (albo ostatni w realizacji), ten sam odczyt `ReportYears` | Zapadło przed kodem | ADR-0097 |
| Kiedy liczyć lata panelu | Zawsze razem z panelem, w propsie `categoryPanel` | Natychmiastowe przełączanie bez szkieletu; liczby z tego samego odczytu co tabela | Plan |
| Kategoria bez wiersza w roku docelowym (np. Kino 2020–2023) | Zwykły tekst; serwer liczy `opens_panel` tą samą regułą co walidacja `?category` | Żaden klik nie otwiera innej kategorii niż kliknięta | Plan |
| Pod słupkami lat | „Zmiana od <rok>” z tonem, „Najwięcej” (remis: wszystkie lata), podpis zakresu; kafelki i „Operacje” bez zmian | Wiersze opisują słupki i powtarzają wiersz tabeli | Plan |
| Tryb lat w panelu | Bez przełącznika: „Do D” w Roku bieżącym, pełne lata w każdym innym (i bez roku bieżącego) | Jedna kontrolka w wąskim panelu, a słupki oglądanego roku i roku wcześniej równają się kafelkom | Plan + przegląd F1 |
| Droga linku | `POST budget/year` z `destination: 'reports'` i nowym `report_panel`, `preserveScroll: false` | Rok jest w sesji; ta sama ścieżka przenosi zakładkę przy zmianie roku | Plan |
| Zakładka w adresie | `?panel=years`, `router.replace` całego adresu, prop `categoryPanel.tab` | Ten sam wzór co `category` i `ranking` | Plan (rezerwacja S-35) |
| Lata bez wiersza w trybie | Nie występują: przy trybie z oglądanego roku każda kategoria panelu ma wiersz; brak to `LogicException` | Test niezmiennika zamiast notatki, której nie da się wywołać | Przegląd F1 |
| Zapis decyzji | ADR-0101 (zawęża ADR-0097:11, kolor roku bieżącego na słupkach panelu) | AGENTS.md: zmiana decyzji i znaczenia koloru przez ADR | Plan |

## Scope

**In scope:**
- `categoryPanel.tab` i `categoryPanel.years` (z latami każdej kategorii) z testami `assertInertia` i równości z odczytem „Lat”
- `opens_panel` w `ReportYears`, `report_panel` w przełączniku roku
- `ReportYearBars`, przełącznik w panelu, wiersze lat, scenariusze `panel-years-*`
- Link w tabeli „Lata”, podpowiedź w stopce, reguła `reports.md`, `design-system.md`, ADR-0101, E2E

**Out of scope:**
- Przełącznik „Do D” / „Pełne lata” w panelu i tryb tabeli w adresie
- Link dla kategorii spoza raportu roku, link do ostatniego roku z wierszem
- Doczytywanie lat osobnym żądaniem; zakładka w Miesiącu i 12 miesiącach
- Linki z grup, komórek lat, kafelków i „Razem”

## Architecture / Approach

Gdy powstaje panel, `ReportController` buduje też `ReportYears::for(ReportYearsRange::resolve(...))` i podaje go do `ReportCategoryPanel::forYear`. Ten bierze dla każdej kategorii jej wiersz z trybu „Do D” w Roku bieżącym (`full` w każdym innym roku i bez roku bieżącego) po `id`. W widoku „Lata” ten sam `ReportYears` oznacza `opens_panel` z liczników `YearsLineActuals`, bez nowego zapytania. Nazwa-link wysyła `POST budget/year`, a serwer przekierowuje na `/reports?category=<id>&panel=years`. Klient układa słupki czystą funkcją i przełącza zakładkę przez `router.replace`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Serwer — lata w panelu i flaga linku | Propsy `tab`, `years`, `opens_panel`, `report_panel`, ADR-0101, typy, testy Pest i NFR | Flaga różna od walidacji `?category`; dwa odczyty w jednym żądaniu przy 7 Budżetach |
| 2. Panel — zakładka „Lata” | Przełącznik, `ReportYearBars`, wiersze lat, `?panel`, sink, Vitest | 7 etykiet lat w wąskim panelu i w arkuszu |
| 3. Link z tabeli „Lata”, reguły i E2E | Link POST, stopka, reguły, design system, Playwright, pełna bramka | E2E zależne od seedu i liczby lat |

**Prerequisites:** S-34 i S-35 zamknięte (done); worktree `worktree-report-panel-years-tab` na `development` (`41a92c1`); baza testowa PostgreSQL `testing_report_panel_years_tab`.
**Estimated effort:** ~3 sesje, po jednej na fazę.

## Open Risks & Assumptions

- W trybie „Do D” lata wczytane z arkusza (operacje na koniec miesiąca) w środku miesiąca tracą cały miesiąc, jak w tabeli „Lata” (ryzyko przyjęte w S-34).
- Klik w „Latach” zmienia rok w sidebarze na rok docelowy, bo wybór roku żyje w sesji (ADR-0075).
- Pełne etykiety lat („2020”) mieszczą się przy 7–10 latach w arkuszu 390 px; sprawdzane ręcznie w fazie 2.

## Success Criteria (Summary)

- Z widoku „Lata” jednym kliknięciem dochodzi się do panelu kategorii w Roku bieżącym na zakładce „Lata”, a liczby lat zgadzają się z wierszem tabeli co do grosza.
- Przełączanie „Miesiące | Lata” jest natychmiastowe i przetrwa odświeżenie oraz zmianę roku.
- Żaden link nie otwiera innej kategorii niż kliknięta.
