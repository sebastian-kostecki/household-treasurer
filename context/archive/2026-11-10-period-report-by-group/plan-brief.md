# Raport okresu po grupach — Plan Brief

> Full plan: `context/changes/period-report-by-group/plan.md`

## What & Why

Użytkownik nie ma w aplikacji odczytu „ile i na co wydaję i zarabiam” za miesiąc ani rok, więc analizę wstecz robi w arkuszu („Analiza”: miesiące × grupy). Nowa strona „Raporty” pokazuje za wybrany rok albo miesiąc budżetu przychody, wydatki i wynik oraz grupy z rozwinięciem do kategorii, z kwotą i udziałem % wydatków, jako wykres i tabelę (S-28, FR-014, FR-015). To pierwszy krok kroku 5 z PRD v3 i fundament reszty strumienia B (rok do roku, trend, przejście do operacji).

## Starting Point

Raportów i wykresów nie ma. Są gotowe:
- `MonthCategoryActuals::forMonths` daje kwoty linii budżetu w dowolnym zakresie miesięcy w jednym zapytaniu;
- `BudgetMonthResult` liczy Wynik miesiąca;
- `CategoryListOrder::tree` buduje kolejność listy z grupami (S-22).

Rok wybiera sidebar (sesja), ale jego przełącznik zawsze przekierowuje na Budżet. `NfrPerf01Test` jest zepsuty od S-18 (stara kolumna).

## Desired End State

„Raporty” to ósma pozycja menu (skrót 8). Strona pokazuje rok wybrany w sidebarze albo miesiąc (`?month=N`), z panelem Przychody / Wydatki / Wynik („Wynik roku” dla roku) i podpisem „okres w toku”. Pod panelem są sekcje Wydatki i Przychody, każda z poziomym wykresem słupkowym grup i kategorii bez grupy oraz tabelą z tymi samymi wierszami i grupami rozwijanymi do kategorii. Kwoty są co do grosza równe Wynikowi miesiąca i wykonaniu w Budżecie, a rok przy 10 000 operacji otwiera się w ≤ 2 s.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Biblioteka wykresów | shadcn-vue Chart (CLI) na `@unovis/vue` i `@unovis/ts`, ADR-0071 | Jedna biblioteka obsłuży S-28, rok do roku (S-29) i trend (S-30) i pasuje do shadcn-vue w projekcie | Plan (użytkownik) |
| Co pokazuje wykres | Poziome słupki pierwszego poziomu nad tabelą Wydatki i nad tabelą Przychody; jeden ton na typ (`chart-1` wydatki, `success` przychody) | Wykres i tabela mają te same wiersze, a kolor znaczy typ, nie kategorię | Plan (użytkownik) |
| Zarchiwizowana kategoria | Zostaje w swojej grupie, wyszarzona z „zarchiwizowana”; grupa sumuje wszystkie swoje kategorie z kwotą w okresie | Grupa pokazuje pełną kwotę okresu i uczciwie porówna się rok do roku; różnica z wierszem grupy w Budżecie trafia do ADR-0070 | Plan (użytkownik) |
| Kolejność wierszy | Kolejność listy jak w Budżecie i „Kategoriach” (ADR-0069), na obu poziomach i na wykresie | Każda grupa zawsze w tym samym miejscu, łatwo porównać miesiące | Plan (użytkownik) |
| Udział % | Tylko wydatki; kwota ÷ wydatki okresu netto (kwota z kafelka), w dziesiątych %, połowa od zera; ujemna kwota → ujemny udział bez paska; wydatki ≤ 0 → „—” | 100% to kafelek „Wydatki”, a kategorie grupy sumują się do udziału grupy | Plan (użytkownik) |
| Wiersze | Kategorie z ≥ 1 operacją w okresie (także 0 zł po zwrocie, także zarchiwizowane); grupy z ≥ 1 takim wierszem | Suma wierszy zawsze równa sumie typu i Wynikowi | Plan (z S-11) |
| Okres | Rok z sesji; `/reports` = rok, `/reports?month=1..12` = miesiąc, każda inna wartość = rok; przełącznik roku z `destination: 'reports'` | Spójne z Budżetem i Planem miesiąca; zły parametr nie podstawia losowego miesiąca | Plan (z S-11) |
| Etykieta wyniku | „Wynik” dla miesiąca, „Wynik roku” dla roku | Słownik: samo „Wynik” dla roku jest _Avoid_ | Plan (z S-11) |
| Źródło kwot | Serwer: `App\Support\PeriodReport` na `MonthCategoryActuals::forMonths` i `CategoryListOrder::tree`, bez kopii w TS | `.ai/rules/reports.md`: kwoty i sumy na serwerze w minor units | Plan |
| Dowód równości | Operacje przez `ManageFinancialOperation`, porównanie z licznikiem wykonania i z `BudgetMonthResult` dla 12 miesięcy | Fabryki nie aktualizują licznika wykonania | Plan (z S-11) |

## Scope

**In scope:**
- wpis Period Report w słowniku, ADR-0070 (reguły raportu), ADR-0071 (wykresy);
- `PeriodReport`, trasa `reports`, `ReportController`, `destination: 'reports'`;
- „Raporty” w menu, teksty w `lang/pl/ui.php`;
- `Reports.vue`, panel miar, tabele z grupami, puste stany, „okres w toku”;
- shadcn-vue Chart, `--chart-1` w Atrament, `ReportBarChart` ładowany asynchronicznie, sekcja „Wykresy” w design systemie;
- kitchen sink `/dev/ui/reports`, `check:ui`, `ReportsHardcodedUiTest`;
- testy: odczyt (SQLite i PostgreSQL), strona, wybór roku, naprawa `NfrPerf01Test`, `NfrPerfReportTest`, E2E.

**Out of scope:**
- rok do roku (S-29), trend (S-30), przejście do Operacji (S-31), ostatnie 12 miesięcy (S-32);
- plan obok wykonania, kolor kategorii na słupkach, pierścień, sortowanie po kwocie;
- własny zakres dat, eksport, druk;
- zmiany schematu, snapshotu, Dashboardu i wiersza grupy w Budżecie.

## Architecture / Approach

`ReportController::show` bierze budżet z `SelectedBudgetYear` (z `authorize('view')`), a okres z `?month`. `PeriodReport::forMonth|forYear` robi stałą liczbę zapytań: linie budżetu z `category.group`, grupy i jedno `forMonths`. Składa drzewo w kolejności listy, sumy grup i typów oraz udziały w dziesiątych procenta, i oddaje `toArray()` w minor units. `resources/js/types/reports.ts` jest lustrem tego kształtu. Strona składa się z `StatPanel`, `SegmentedControl`, `BudgetMonthPicker`, `ListSurface`, `CategoryChip` i `IndexEmptyState`, a wykres dostaje te same `entries` co tabela.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i odczyt raportu na serwerze | Słownik, ADR-0070, `PeriodReport`, testy reguł i równości na SQLite i PostgreSQL | Równość z licznikiem sprawdzona na fabrykach zamiast przez akcję zapisu |
| 2. Strona „Raporty” z tabelami | Trasa, okres, menu, przełącznik roku, tabele z grupami, puste stany, kitchen sink, test 2 s | Przekroczenie 2 s przy 10 000 operacjach (wtedy odczyt po linii albo indeks, jako osobna decyzja) |
| 3. Wykres | ADR-0071, shadcn-vue Chart, `--chart-1` w obu trybach, słupki nad tabelami, E2E | Wygenerowany `ui/chart` a reguły tokenów; kontrast `chart-1` w ciemnym trybie |

**Prerequisites:** worktree na `development` (S-22 jest w nim); `composer install` (hook w worktree się nie powiódł, trzeba go powtórzyć), `.env`, `npm ci`, `npm run build`; Sail z PostgreSQL (sieć `household-treasurer_sail`); sieć dla CLI shadcn-vue i npm w Fazie 3.
**Estimated effort:** ~3 sesje w 3 fazach.

## Open Risks & Assumptions

- Zakładamy, że typ operacji równa się typowi kategorii (pilnuje tego walidacja, nie schemat). Test równości z `BudgetMonthResult` wykryje odstępstwo.
- Wiersz grupy w raporcie może różnić się od wiersza grupy w Budżecie o zarchiwizowane kategorie (decyzja użytkownika, ADR-0070). „Razem” jest w obu widokach równe przy „Pokaż zarchiwizowane”.
- `forMonths` grupuje po dniu. Jeśli test `nfr` przekroczy 2 s, potrzebny będzie odczyt po linii z testem równości albo indeks `(budget_id, operation_date)` z migracją na PostgreSQL.
- ADR-0070/0071 zakładają, że równoległy strumień C (`worktree-category-rhythm`) nie zajmie tych numerów. Sprawdzić przed zapisem ADR.
- Dane kategorii na devie są częściowo testowe, więc ręczna weryfikacja porównuje z Budżetem i Podsumowaniem miesiąca, a nie z oczekiwanymi kwotami.

## Success Criteria (Summary)

- Użytkownik otwiera „Raporty”, wybiera rok albo miesiąc i widzi przychody, wydatki, wynik oraz grupy rozwijane do kategorii z kwotą i udziałem %, jako wykres i tabelę.
- Wydatki i przychody roku są równe wykonaniu w Budżecie (z zarchiwizowanymi), wynik miesiąca jest równy Wynikowi tego miesiąca, a suma grupy jest równa sumie jej kategorii.
- `composer ci:check`, testy PostgreSQL, testy `nfr` (≤ 2 s), E2E i `sloppy ci` przechodzą.
