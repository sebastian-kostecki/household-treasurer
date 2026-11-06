# S-11: Raport okresu według kategorii — Plan Brief

> Full plan: `context/changes/period-report-by-category/plan.md`

## What & Why

Użytkownik nie ma w aplikacji odczytu „ile i na co wydaję i zarabiam” w miesiącu ani w roku, więc do analizy wstecz wraca do arkusza. Nowa strona „Raporty” pokazuje za wybrany miesiąc albo rok budżetu przychody, wydatki i wynik oraz kategorie z kwotą i udziałem %, jako tabelę i wykres (US-01, FR-001–FR-003). To gwiazda przewodnia M-2: pierwszy odczyt na ~1000 prawdziwych operacjach 2026.

## Starting Point

Raportów i wykresów nie ma. Są już:
- Wynik miesiąca (`BudgetMonthResult`, jedno zapytanie);
- kwoty kategorii w dowolnym zakresie miesięcy (`MonthCategoryActuals::forMonths`, jedno zapytanie);
- licznik wykonania rocznego `categories.execution_minor_units`, prowadzony tylko przez `ManageFinancialOperation`.

Rok wybiera globalny przełącznik w sidebarze (sesja), a miesiąc parametr `?month=` i `BudgetMonthPicker`.

## Desired End State

„Raporty” w sidebarze otwierają raport roku: kafelki Przychody, Wydatki i Wynik roku z wykresem obok, a pod nimi sekcje Wydatki i Przychody z wykresem pasków i tabelą Kategoria | Kwota | Udział. Przełącznik „Miesiąc | Rok” i picker miesiąca pokazują raport miesiąca, w którym wynik nazywa się „Wynik”. Sumy są co do grosza równe Wynikowi miesiąca i wykonaniu w budżecie rocznym, a raport roku przy 10 000 operacji otwiera się w ≤ 2 s.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Wykres | shadcn-vue Chart (CLI) na `@unovis/vue` i `@unovis/ts` 1.7.1, ADR-0036 dla strumienia A | Jedna decyzja dla S-11 i trendu S-12 z wykresem wielu serii | Plan (użytkownik) |
| Kolory wykresu | Jeden ton na typ: przychody `--success`, wydatki `--chart-1`; kolor kategorii tylko w `CategoryChip` | Zgodne z „kolor = znaczenie”; poprawiamy `AGENTS.md`, który przeczył design-system | Plan |
| Udział % | Mianownik = suma kategorii typu z kwotą > 0; kwota ≤ 0 daje „—” bez paska (Żywność 1000 i Elektronika −200 dają 100,0% i „—”, suma 800 zł) | Udziały nigdy nie są ujemne ani nie przekraczają 100% | Plan |
| Dokładność % | Int w dziesiątych procenta, zaokrąglany w górę od połowy, każda kategoria osobno („12,3%”) | Małe kategorie nie znikają jako 0%, bez float | Plan |
| Lista kategorii | Kategorie z ≥ 1 operacją w okresie, także zarchiwizowane (z tekstem „zarchiwizowana”) i jednorazowe; 0 zł po zwrocie zostaje z „—” | Suma wierszy zawsze równa sumie typu i Wynikowi | Plan |
| Kolejność | Kwota malejąco, potem kolejność kategorii, potem `id` | Rozstrzyga remisy decyzją, nie przypadkiem | Plan |
| Okres | Rok z sesji (jak Budżet i Plan miesiąca); `/reports` = rok, `/reports?month=N` = miesiąc; domyślnie rok | Spójne z aplikacją, a US-01 zaczyna od roku | Plan |
| Źródło kwot | Serwer: `CategoryReport` na `MonthCategoryActuals`, bez kopii w TS | Reguła `AGENTS.md`; ten sam odczyt posłuży S-12 | Plan |
| Dowód równości | Test przez trasy zapisu operacji z porównaniem z licznikiem; 12 miesięcy porównanych z `BudgetMonthResult` | Fabryki nie aktualizują licznika (lekcja F6 z S-10) | Plan |
| Dostępność | Tabela z rolami ARIA, wykres `aria-hidden` | Tabela jest dostępną formą wykresu (PRD Non-Goals) | Plan |
| Etykieta wyniku | „Wynik” dla miesiąca, „Wynik roku” dla roku | `context/foundation/glossary.md`: samo „Wynik” jest dla roku _Avoid_ | Plan |

## Scope

**In scope:**
- `App\Support\CategoryReport` i wpis Category Report w `context/foundation/glossary.md`;
- trasa `reports`, `ReportController`, `destination: 'reports'` w przełączniku roku;
- pozycja „Raporty” w sidebarze i teksty w `lang/pl/ui.php`;
- `Reports.vue`, kafelki, tabele kategorii, puste stany i podpis „okres w toku”;
- ADR-0036, komponent `chart` i unovis, wykresy sum i kategorii;
- testy: odczyt, strona, select-year, wydajność `nfr`, `formatShareTenths`;
- poprawki `AGENTS.md`, `context/foundation/design-system.md` i `.ai/rules/js.md`.

**Out of scope:**
- trend (S-12), przejście do Operacji (S-13), rok do roku (S-17);
- własny zakres dat, eksport i druk;
- plan obok wykonania;
- grupy kategorii;
- kitchen sink, macierz 7 stanów i zrzuty (później `/10x-ui`);
- zmiany schematu, snapshotu i Dashboardu;
- kolor kategorii na paskach.

## Architecture / Approach

`ReportController::show` bierze budżet z `SelectedBudgetYear`, a okres z `?month`. `CategoryReport::forMonth` albo `::forYear` robi dwa zapytania (kategorie budżetu z zarchiwizowanymi i `MonthCategoryActuals::forMonths`) i oddaje `toArray()` w minor units: sumy typów, mianownik udziału, wiersze z `share_tenths_percent`, wynik i `has_operations`. Typ TS w `resources/js/types/reports.ts` jest lustrem tego kształtu. Strona składa się z istniejących komponentów (`SegmentedControl`, `BudgetMonthPicker`, `StatTile`, `CategoryChip`, `ListSurface`, `IndexEmptyState`). Wykresy unovis dostają te same wiersze co tabela.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Odczyt raportu na serwerze | `CategoryReport`, wpis w `context/foundation/glossary.md`, testy reguł i równości (SQLite i PostgreSQL) | Równość z licznikiem testowana przez fabryki zamiast przez akcję |
| 2. Strona „Raporty” z tabelami | Trasa, kontroler, wybór okresu, nawigacja, kafelki, tabele, puste stany, `assertInertia`, test 2 s | Hydratacja wierszy (dzień × kategoria) przy 10 000 operacji |
| 3. Wykres | ADR-0036, shadcn-vue Chart i unovis, paski obok tabel, spójne dokumenty o kolorach | Wygenerowany kod chart a `vp check` bez ostrzeżeń; rozmiar bundla |

**Prerequisites:** gałąź z `development`; działający Sail z PostgreSQL; `composer install`, `.env`, `npm ci`, `npm run build` (`AGENTS.md`). Dostęp do sieci dla CLI shadcn-vue i npm w fazie 3.
**Estimated effort:** ~2–3 sesje w 3 fazach.

## Open Risks & Assumptions

- Zakładamy, że typ operacji równa się typowi kategorii. Gwarantuje to walidacja, nie schemat; test równości z `BudgetMonthResult` to wykryje.
- Odczyt `MonthCategoryActuals` grupuje po dniu. Jeśli test wydajności przekroczy 2 s, plan przewiduje odczyt grupowany po kategorii z testem równości (odchylenie zapisane w planie).
- Wygenerowane komponenty `components/ui/chart` mają klasy arbitrary. Zakładamy, że reguła tokenów obejmuje kod aplikacji, nie wygenerowane prymitywy (ADR-0036 to zapisze).
- Kolor `--chart-1` (wydatki) to wciąż stary turkus. Zmiana `design-tokens-atrament` (ADR-0035) wprowadza nową paletę, więc kolor wydatków trzeba ustalić wobec niej przed Fazą z wykresem.
- Dane kategorii z devu są częściowo testowe (PRD v2), więc ręczna weryfikacja porównuje z Budżetem, a nie z oczekiwanymi kwotami.

## Success Criteria (Summary)

- Użytkownik otwiera „Raporty”, wybiera rok albo miesiąc i widzi przychody, wydatki, wynik oraz kategorie z kwotą i udziałem %, jako wykres i tabelę.
- Suma wydatków roku jest równa wykonaniu na stronie Budżet (z zarchiwizowanymi), a wynik miesiąca jest równy Wynikowi tego miesiąca.
- `composer ci:check`, testy PostgreSQL, test `nfr` (≤ 2 s) i `sloppy ci` przechodzą.
