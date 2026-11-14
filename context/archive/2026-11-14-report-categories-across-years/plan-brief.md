# Raporty „Lata”: kategorie przez lata — Plan Brief

> Full plan: `context/changes/report-categories-across-years/plan.md`
> Decyzje: `context/foundation/adr/0097-report-years-ranking-and-chart-colours.md` (F-07)

## What & Why

S-34, gwiazda przewodnia kamienia M-4: użytkownik może otworzyć Raporty → „Lata” i porównać każdą kategorię i grupę w latach 2020–2026, licząc każdy rok do tego samego dnia albo w pełnych latach, ze zmianą od 2020 i mini-wykresem. To brak nazwany wprost: „jest tylko porównanie lat sąsiednich, a nie ma większych trendów”.

## Starting Point

Raporty mają cztery okresy (Rok, Miesiąc, 12 miesięcy, Trend); porównanie sięga tylko roku N − 1 (ADR-0074). Aplikacja ma Budżety 2020–2026 na jednej liście kategorii (ADR-0063, ADR-0085, ADR-0096), ale każdy odczyt raportu czyta jeden Budżet naraz. ADR-0097 i słownik opisują już cały odczyt „Lata”; karta `PageReportsYears` (wariant A) pokazuje układ.

## Desired End State

`/reports?view=years` pokazuje piąty segment „Lata”: tytuł „Raport: lata 2020–2026”, cztery kafelki, zakładki Wydatki | Przychody, przełącznik „Do 14 listopada | Pełne lata” i tabelę w kolejności listy z grupami, zarchiwizowanymi, pełnymi złotymi, „Zmianą od 2020” i sparkline. Liczby zgadzają się z raportem roku co do grosza, odczyt idzie stałą liczbą zapytań, a zmiana roku w sidebarze zostawia „Lata”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres, cięcie, pełne lata, „—”/0, zmiana, statystyki, kolory, pełne złote | Jak ADR-0097, bez zmian | Reguły zapadły w F-07 przed kodem, zgodnie z AGENTS.md | Frame (ADR-0097) |
| Kafelki w „Pełnych latach” | Końce zmiany: „Wydatki 2025 · ostatni pełny rok” i pierwszy rok | Kafelki są dokładnie końcami „Średnio rocznie”, jak nagłówek „Zmiana 2020–2025” | Plan |
| Listopad w latach z arkusza (operacje na koniec miesiąca) | Reguła ADR-0097 zostaje; przesunięcie to znane ryzyko i krok przeglądu | Bez nowego ADR, rok 2026 nadal równy raportowi roku | Plan |
| Luki 42/44/45 w 2022–2025 | Poza S-34; przegląd po wdrożeniu daje wejście do osobnej zmiany danych | Widok sam pokazuje luki, wycinek UI nie miesza się z procedurą na produkcji | Plan |
| Stan zakładki i zakresu | Lokalny; serwer wysyła oba tryby i oba typy w jednym propsie | Natychmiastowe przełączanie, najmniej kodu; po odświeżeniu wraca domyślny stan | Plan |
| Odczyt wielu lat | Jedno zapytanie `whereIn budget_id` z warunkowymi sumami po zakresach dat | Stała liczba zapytań, ta sama semantyka w SQLite i PostgreSQL, brak pętli po latach | Plan |
| Remis najwyższego roku | Wszystkie lata z maksimum pogrubione | Tak robi karta (Czynsz 2025 i 2026) | Research (karta) |
| Nazwa kategorii | Zwykły tekst | Link do panelu to S-36 (ADR-0097:11) | Frame (ADR-0097) |

## Scope

**In scope:**

- `ReportYearsRange`, `YearsLineActuals`, `ReportYears` w `app/Support`; `?view=years` przed fazą Budżetu; props `years` + typy TS.
- Cel `reports-years` dla zmiany roku.
- Komponenty `ReportYears*`, siatka `.report-years-grid`, `lib/report-years.ts`.
- Scenariusze `years-*` w `/dev/ui/reports`, strażnicy.
- Przepisanie zdań `.ai/rules/reports.md` (pięć segmentów, pełne złote w Trendzie i „Latach”) i `design-system.md:20`.
- E2E, przegląd na produkcji.

**Out of scope:**

- Link z nazwy i zakładka „Lata” w panelu (S-36).
- Ranking, panel, usunięcie wykresu słupkowego (S-35).
- Stan w adresie.
- Zmiana reguły cięcia.
- Poprawki danych 2020–2025.
- „Lata” dla Miesiąca i 12 miesięcy.
- Nowy ADR.

## Architecture / Approach

Kontroler → `ReportYearsRange::resolve(user, today)` (kolumny = lata w realizacji ≤ rok bieżący, dzień D, koniec zakresu dla każdego roku osobno, bez łańcuchowego `subYearNoOverflow`) → `YearsLineActuals::read` (jedno zapytanie: suma i liczba dla całego roku i dla cięcia na linię) → `ReportYears` (wiersze przez `ReportCategoryTree::entryNodesOfType`, „—” z braku linii, zarchiwizowanie z linii ostatniej kolumny, zmiana całkowitoliczbowo, wzrost roczny jako jedyny `float` w stosunku, nie w kwocie) → props `years {columns, current_year, cut, same_day, full}`. Strona wybiera tryb i typ lokalnie i tylko formatuje.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Odczyt „Lata” na serwerze | Props `years` z obu trybami, `reports-years`, testy równości/cięcia/zapytań, NFR 7 × 10 000 | Zgubienie lub podwójne liczenie zarchiwizowanych i scalonych kategorii; 29.02 |
| 2. Widok „Lata” | Segment, kafelki, zakładki, przełącznik, tabela ze sparkline, sink, strażnicy, reguła i design system | Pięć segmentów w 22rem; siatka 7 lat przy 1280 px |
| 3. E2E i przegląd na produkcji | Playwright na własnych danych; notatka z przeglądu 42/44/45 i listopada | E2E zależne od seedu (lekcje S-30, S-32) |

**Prerequisites:** F-07 zamknięty (ADR-0097, słownik); worktree stoi na `development` (po scaleniu F-07, `a264034`); własna baza testowa PostgreSQL `testing_report_categories_across_years`.
**Estimated effort:** ~3 sesje: serwer, widok, E2E z przeglądem.

## Open Risks & Assumptions

- W „Do D” w środku miesiąca lata z arkusza tracą cały miesiąc (daty na koniec miesiąca), więc „Zmiana od 2020” jest zawyżona o kilka punktów; przyjęte, sprawdzane w przeglądzie.
- Kategorie 42, 44, 45 mogą mieć „—” w 2022–2025 z powodu wczytania, a nie braku pozycji; poza zakresem.
- „Razem” różni się od arkusza „Analiza” o ruchy funduszu (ADR-0096:10), zgodnie z definicją Execution.

## Success Criteria (Summary)

- Użytkownik na produkcji porównał kategorię przez 2020–2026 w „Lata” i przełączył Przychody oraz Pełne lata.
- W „Pełnych latach” kwota zakończonego roku i „Razem” równają się raportowi roku co do grosza; odczyt mieści się w 2 s przy 7 Budżetach.
- Przegląd po wdrożeniu zapisuje, czy luki 42/44/45 wymagają osobnej zmiany danych.
