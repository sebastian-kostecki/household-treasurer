# Ranking kategorii i panel kategorii — Implementation Plan

## Overview

Wycinek S-35 (MS-02) roadmapy M-4 wdraża kartę `PageReports` (wariant C) z design systemu Atrament według ADR-0097 (akapity „Ranking”, „Category Panel”, „Colour”):

- w widokach Rok, Miesiąc i 12 miesięcy tabela grup w kolejności listy i wykres słupkowy ustępują **Rankingowi**: kategorie od największych, numer pozycji, pasek udziału (znacznik CSS w `chart-1`, przychód `success`), Różnica jako kwota, przełącznik „Kategorie | Grupy”;
- w widoku Rok nazwa kategorii otwiera **Panel kategorii**: kwota roku i N−1, różnica z „±x,x% względem N−1”, Udział, 12 słupków miesięcy (najwyższy w `foreground`, miesiąc w toku kreskowany, przyszłe puste, lista `sr-only`), „Średnio na pełny miesiąc”, „Najwięcej”, „<Miesiąc> do <D.MM> (w toku)” i przycisk „Operacje: <kategoria>, <rok>”;
- `ReportBarChart` z placeholderem i zarezerwowanym miejscem (ADR-0084:7) znika; Unovis zostaje tylko w Trendzie.

Słownik (`glossary.md:171,173`) i design system („Wykresy”) opisują już Ranking i Panel (F-07). Zmiana nie rusza modelu danych ani migracji.

## Current State Analysis

- `ReportController::show` (`app/Http/Controllers/ReportController.php:22-69`) bez FormRequest: `?view=last-12` przed fazą Budżetu (:49-51), `?view=trend` (:53), `?month` przez `requestedMonth` z cichym fallbackiem do roku (:106-115). Propsy: `budgetYear`, `budgetPhase`, `period`, `report`, `trend`.
- `PeriodReport` (`app/Support/PeriodReport.php`): `forYear` czyta `MonthCategoryActuals::forMonths(1..12)` w `rows()` (:130-143) i sumuje miesiące, gubiąc podział. Wpis kategorii (:36-38) nie ma grupy, rytmu ani miesięcy; drzewo wpisów idzie w kolejności listy przez `ReportCategoryTree::entryNodesOfType` (:219), z wierszami tylko z porównania (`has_operations: false`, :155-163). Udział liczy `shareTenthsPercent` (:316-325, pół od zera, liczby całkowite).
- `ReportTrend::forYear` (`app/Support/ReportTrend.php:47-106`) ma 12 komórek na wiersz (`null` = brak operacji, `0` = netto zero) z tego samego odczytu; to wzorzec semantyki komórek panelu.
- Rytm zależy od miesiąca: `CategoryRhythms::rhythmIn(categoryId, year, month)` (`app/Support/CategoryRhythms.php:104`), jedno zapytanie przez `forCategoryIds` (:60). Budżet czyta rytm oglądanego miesiąca (`BudgetController.php:243`, `BudgetViewedMonth::defaultForYear`: bieżący miesiąc w roku bieżącym, 12 w minionym, 1 w przyszłym, `app/Support/BudgetViewedMonth.php:22-33`).
- Menu roku: `BudgetYearSwitcher.vue:65-72` wysyła `destination: 'reports' | 'reports-trend'`, `SelectBudgetYearRequest.php:27` je waliduje, `BudgetController::selectYear` (:148-160) przekierowuje na `to_route('reports')` bez parametrów.
- `Reports.vue` (279 linii): `SegmentedControl` okresu (:139-145) z URL-ami Wayfindera (:16-37), `ReportBarChart` ładowany `defineAsyncComponent` z `ReportBarChartPlaceholder` (:19-23), potem 2× `ReportBarChart` + `ReportEntryTable` (:231-274). Jedna kolumna (:127).
- Komponenty do zastąpienia: `ReportEntryTable`, `ReportGroupRow`, `ReportCategoryRow` (link do Operacji :48-74, ton różnicy :76-85), `ReportBarChart`, `ReportBarChartPlaceholder`; `lib/report-chart.ts` (+ test) służy tylko wykresowi słupkowemu. Zostają: `ReportStatPanel`, `ReportCoverageNote`, `ReportEmptyState`, `ReportChartLegend` (Trend) i cały Trend.
- Strażniki padają na usuniętym pliku: `tests/Unit/ReportsHardcodedUiTest.php:7-25,66` i `scripts/check-ui-literals.mjs:123-139,199-203`; `resources/js/ui-contract.test.ts:561-588` czyta `ReportCategoryRow.vue` i `ReportGroupRow.vue`.
- Kitchen sink `/dev/ui/reports` (`resources/js/pages/dev/ReportsKitchenSink.vue`, sekcja `chart-loading` :96-117) i scenariusze w `resources/js/lib/reports-fixtures.ts:651-1056`.
- E2E: `tests/e2e/reports.spec.ts` (wykresy :85-125, legenda i wiersz nagłówka :134-176, zwijanie grup :193-256) i `tests/e2e/reports-view.spec.ts` (miejsce wykresu :78-128, wąska karta :166-230).
- Wzorce: `SegmentedControl` (`components/SegmentedControl.vue`), zmiana parametru w adresie w `Categories.vue:154-185`, przełączanie układu po szerokości własnej karty (`.report-table { container-type: inline-size }`, `app.css:1680`, `@container (min-width: 36rem)`), `ui/sheet` (`side="bottom"` bez `max-height` i przewijania, sr-only „Close”: `SheetContent.vue:47-58`), `@vueuse/core` w `package.json`. `.dashboard-grid` (`app.css:2306-2315`) przełącza się po oknie (`@variant lg`), więc nie pasuje do ramki telefonu w sinku. Kreskowania ani bocznego panelu z zaznaczonym wierszem w aplikacji nie ma; wiersze raportu to divy bez ról ARIA.
- `router.replace`/`router.push` nie są dziś nigdzie używane; to pierwsza wizyta po stronie klienta. W `@inertiajs/core` 3.11 `preserveScroll` i `preserveState` domyślnie są `false`, a flash zeruje się bez `preserveFlash: true`.
- Inertia 3 ma wizyty po stronie klienta `router.replace({ url, props, preserveScroll, preserveState })`: zmieniają adres i propsy bez żądania; serwer musi umieć wyrenderować ten sam adres po odświeżeniu.

## Desired End State

- `/reports` (Rok w realizacji): dwie listy „Wydatki od największych” i „Przychody od największych” z numerem, kafelkiem, paskiem udziału, kwotą, Różnicą (z porównaniem) i Udziałem (wydatki); przełącznik „Kategorie | Grupy” w nagłówku Wydatków steruje obiema listami. Obok (szeroki ekran) panel wybranej kategorii; domyślnie pierwsza pozycja Wydatków, bez wydatków pierwsza Przychodów, bez obu brak panelu.
- `/reports?category=12` otwiera panel kategorii 12; zła, cudza albo nieobecna w raporcie roku kategoria po cichu daje wybór domyślny. `?ranking=groups` włącza Grupy; przechodzi przez przełącznik okresu (Rok, Miesiąc, 12 miesięcy) i przez zmianę roku w menu, które przenosi też `category` (serwer robi fallback, gdy kategoria nie ma wiersza w nowym roku).
- Na wąskim ekranie panel jest arkuszem od dołu (`ui/sheet`), otwieranym tapnięciem nazwy albo od razu przy `?category` w adresie; zamknięcie zdejmuje `category` z adresu.
- Miesiąc i 12 miesięcy: ten sam Ranking, nazwa linkuje do Operacji jak dziś (S-31), bez panelu. Trend bez zmian.
- Wykres słupkowy i jego placeholder nie istnieją; reguła `.ai/rules/reports.md` opisuje Ranking i Panel.
- Weryfikacja: Pest (`assertInertia`, równość miesięcy z raportem, liczba zapytań, NFR na PostgreSQL), Vitest (sortowanie, statystyki panelu), kitchen sink, E2E, `npm run check:ui`, `composer ci:check`, `vendor/bin/sloppy ci`.

### Key Discoveries:

- Miesiące panelu nie potrzebują nowego zapytania o operacje: `PeriodReport::rows()` ma już `forMonths(1..12)` (`app/Support/PeriodReport.php:130`).
- Nazwa grupy jest w drzewie raportu (kategoria leży w `categories` grupy), więc płaski Ranking niesie ją z rodzica bez nowego pola.
- Sortowanie po stronie klienta nie łamie zasady „strona tylko formatuje”: nie liczy kwot, tylko układa wiersze serwera; remis rozstrzyga kolejność tablicy, czyli kolejność listy (ADR-0097:21). `ReportCategoryTree::ofType` zostaje w kolejności listy (filtr Operacji, ADR-0093:9).
- Wybór domyślny („pierwsza pozycja Rankingu Wydatków”) zależy od sortowania; żeby nie mieć dwóch implementacji, serwer waliduje tylko żądaną kategorię, a domyślną wybiera klient tą samą funkcją, która układa Ranking.
- Przyszłe miesiące roku bieżącego mogą mieć operacje (rok bieżący nie jest cięty, ADR-0074:7), ale ADR-0097:23 rysuje je pusto; komórki serwera zostają dokładne, puste jest tylko rysowanie.

## What We're NOT Doing

- Zakładki „Lata” w panelu ani przełącznika „Miesiące 2026 | Lata”: w S-35 nagłówek sekcji ma etykietę „Miesiące <rok>”; S-36 zamieni ją na przełącznik z `?panel=years` (nazwa zarezerwowana tutaj).
- Widoku „Lata” i piątego segmentu przełącznika okresu (S-34); przełącznik okresu zostaje z czterema segmentami.
- Panelu w Miesiącu i 12 miesiącach (ADR-0097:23).
- Zmian w Trendzie, `ReportTrend`, `ReportCategoryTree::ofType`, filtrze Operacji ani w `PeriodReport::toArray()` (kształt wpisów bez zmian).
- Kolumny procentowej w Różnicy Rankingu (ADR-0074:23) i bloku „Bez grupy” w Grupach (ADR-0069).
- Nowej biblioteki wykresów; znaczniki to CSS/SVG z tokenów.
- Historii przeglądarki dla wyboru kategorii i trybu (wizyty `replace`, nie `push`).
- Edycji ADR-0097, słownika i karty w design systemie (F-07 je zamknął); odejście od `aria-selected` zapisuje nowy ADR-0100, nie edycja ADR-0097.

## Implementation Approach

Serwer dokłada dwa propsy obok niezmienionego `report`: `rankingMode` (z `?ranking`) i `categoryPanel` (tylko Rok w realizacji z operacjami). Dane panelu dla każdej kategorii roku powstają w nowym `App\Support\ReportCategoryPanel` z podziału miesięcznego, który `PeriodReport::forYear` już czyta, plus jedno zapytanie o rytmy. Klient układa Ranking czystą funkcją w `lib/report-ranking.ts` i zmienia wybór oraz tryb wizytami `router.replace`, więc klik jest natychmiastowy, a adres wystarcza do odtworzenia widoku. Fazy idą od kontraktu danych (1), przez Ranking we wszystkich trzech okresach z usunięciem wykresu (2), do panelu z układem i arkuszem (3). Każda faza przepisuje swoje zdania w `.ai/rules/reports.md` razem z kodem (ADR-0097:29).

## Critical Implementation Details

- **Semantyka komórek miesięcy.** `months` ma 12 komórek jak w Trendzie (`null` = brak operacji, `0` = netto zero) i ich suma (null = 0) równa się `amount_minor_units` wpisu w raporcie roku co do grosza. Rysowanie: miesiące po miesiącu w toku (rok bieżący) i wszystkie miesiące przyszłego roku są puste i nie wchodzą do „Najwięcej”, skali słupków ani listy `sr-only`; ujemny miesiąc to słupek zerowy (kwota na liście `sr-only` dokładna).
- **Wybór a ARIA: `aria-current`, nie `aria-selected`.** ADR-0097:23 wymaga `aria-selected`, ale ten atrybut działa tylko w rolach `row` w `grid`/`treegrid`, `option` i `tab`. Każda z nich wymaga nawigacji strzałkami (APG), a w trybie Grupy przyciski rozwijania grup nie mogą leżeć w `listbox` ani `tablist`. Dlatego nazwa kategorii w Roku jest przyciskiem (nie linkiem) z `aria-controls` panelu, a wybrany ma `aria-current="true"`; wiersze zostają divami bez ról tabeli. Zmianę atrybutu zapisuje nowy ADR-0100 (Faza 3 §7), a ADR-0097 zostaje nietknięty.
- **Tryb układu panelu zależy od szerokości obszaru treści, nie okna.** Kitchen sink renderuje `Reports.vue` w ramce telefonu (`.report-phone-frame`, 22rem) w oknie desktopu. Siatka Rok + panel jest więc zapytaniem kontenerowym (`container-type: inline-size` na opakowaniu strony, jak `.report-table`), a nie `@variant lg` jak `.dashboard-grid`. Próg to nazwany token w `:root`, a JS (`useElementSize`/ResizeObserver na tym samym opakowaniu) porównuje z tą samą wartością. Przed pierwszym pomiarem szerokość wynosi 0, więc tryb ma trzy stany: `unknown | aside | sheet`, a Sheet nie otwiera się w `unknown`.
- **Wizyty klienta niosą cały adres.** `router.replace` z `props: (current) => ({ ...current, ... })` musi zachować pozostałe parametry (`ranking`, `category`); adres budowany Wayfinderem (`reportsRoute({ query })`), nie sklejany ręcznie. Każde wywołanie ma `preserveScroll: true, preserveState: true, preserveFlash: true` (w core 3.11 domyślnie `false`, a flash się zeruje).

## Phase 1: Serwer — dane panelu i parametry

### Overview

Kontrakt danych przed widokiem: propsy `rankingMode` i `categoryPanel`, odczyt `ReportCategoryPanel`, przenoszenie `category` i `ranking` przy zmianie roku. Typy TS i fixtures dostają nowe propsy w tym samym commicie; widok jeszcze ich nie używa.

### Changes Required:

#### 1. Podział miesięczny w raporcie roku

**File**: `app/Support/PeriodReport.php`

**Intent**: Zachować 12 kwot miesięcznych każdego wiersza, które `rows()` już czyta, i udostępnić je obok raportu bez zmiany `toArray()`.

**Contract**: Wewnętrzny `ReportRow` dostaje `months: list<int|null>` (12 komórek dla `forYear`; wiersz tylko z porównania ma 12 × `null`); publiczna metoda (np. `categoryMonths(): array<int, list<int|null>>`, klucz = id kategorii listy) zwraca je tylko dla raportu roku. Liczba zapytań `forYear` bez zmian (`tests/Feature/PeriodReportTest.php:301,593`).

#### 2. Procent w dziesiątych z jednego miejsca

**File**: `app/Support/PeriodReport.php`, nowy `app/Support/TenthsPercent.php` (albo statyczna metoda w istniejącej klasie pomocniczej)

**Intent**: Udział i „±x,x% względem N−1” zaokrąglają tak samo (pół od zera, liczby całkowite); wyciągnąć logikę `shareTenthsPercent` (:316-325), żeby Sloppy nie zgłosił duplikatu.

**Contract**: `of(int $part, int $whole): ?int` — dziesiąte procenta, `null` gdy `$whole <= 0`; `PeriodReport` używa go dla udziału bez zmiany wyników (istniejące testy udziału przechodzą bez edycji).

#### 3. Odczyt panelu

**File**: `app/Support/ReportCategoryPanel.php` (nowy)

**Intent**: Dla każdej kategorii raportu roku policzyć to, czego panel potrzebuje, a czego `report` nie ma; kwoty i procenty na serwerze (reguła „Report amounts…”).

**Contract**: `ReportCategoryPanel::forYear(Budget $budget, PeriodReport $report, mixed $requestedCategory, CarbonImmutable $today): self`, `toArray()`:

```php
array{
    requested_category_id: int|null, // id z ?category, gdy ta kategoria ma wiersz w raporcie roku; inaczej null
    in_progress_month: int|null,     // miesiąc dzisiejszy, gdy rok Budżetu = rok dzisiejszy (UTC)
    through: string|null,            // dzisiejsza data 'Y-m-d' przy miesiącu w toku
    closed_month_count: int,         // 12 rok miniony, (miesiąc dzisiejszy − 1) rok bieżący, 0 rok przyszły
    categories: list<array{
        id: int,
        rhythm: string|null,                    // Rhythm w BudgetViewedMonth::defaultForYear; przychód null
        months: list<int|null>,                 // 12 komórek
        closed_average_minor_units: int|null,   // suma zamkniętych miesięcy ÷ ich liczba, pół od zera; null przy 0 zamkniętych
        highest_month: int|null,                // 1..12 wśród miesięcy nie przyszłych, remis → wcześniejszy; null gdy żaden > 0
        difference_tenths_percent: int|null,    // (kwota − N−1) ÷ N−1 przez TenthsPercent; null bez porównania albo gdy N−1 ≤ 0
    }>,
}
```

`?category` przechodzi jak `?month`: tylko ciąg cyfr, inaczej `null`; id cudzej kategorii albo bez wiersza w raporcie daje `null` (bez błędu walidacji). Rytmy jednym `CategoryRhythms::forCategoryIds`; zero zapytań w pętli.

#### 4. Kontroler Raportów

**File**: `app/Http/Controllers/ReportController.php`

**Intent**: Dodać `rankingMode` do każdego renderu i `categoryPanel` do Roku w realizacji z operacjami.

**Contract**: `'rankingMode' => 'groups'` gdy `?ranking=groups`, inaczej `'categories'` (także brak Budżetu, szkic, Trend, 12 miesięcy). `'categoryPanel' => ReportCategoryPanel::forYear(...)->toArray()` gdy okres to Rok, Budżet w realizacji i `report.has_operations`; w pozostałych przypadkach `null`. Raport roku budowany raz i podawany do panelu (bez drugiego `forYear`).

#### 5. Zmiana roku przenosi parametry Raportów

**File**: `app/Http/Requests/SelectBudgetYearRequest.php`, `app/Http/Controllers/BudgetController.php`

**Intent**: Wybór roku z Raportów zachowuje kategorię panelu i tryb Rankingu.

**Contract**: Nowe pola `report_category` (`sometimes`, `integer`, `min:1`) i `report_ranking` (`sometimes`, `in:groups`); przy `destination = 'reports'` przekierowanie `to_route('reports', [...])` z `category` i `ranking`, gdy są podane. `reports-trend` bez zmian. Istnienia kategorii nie sprawdza żądanie: robi to cichy fallback w Raportach.

#### 6. Typy i fixtures

**File**: `resources/js/types/reports.ts`, `resources/js/lib/reports-fixtures.ts`

**Intent**: Lustro nowych propsów w tym samym commicie (reguła `inertia-props.md`).

**Contract**: `ReportRankingMode = 'categories' | 'groups'`, `ReportCategoryPanelData` (kształt z punktu 3), `ReportsPageProps` z `rankingMode` i `categoryPanel: ReportCategoryPanelData | null`; każdy scenariusz fixtures dostaje `rankingMode: 'categories'` i `categoryPanel` (null albo dane dla scenariuszy Roku z operacjami).

#### 7. Testy

**File**: `tests/Feature/ReportsPageTest.php`, nowy `tests/Feature/ReportCategoryPanelTest.php`, test wyboru roku (istniejący test `selectYear`), `tests/Feature/Nfr/NfrPerfReportTest.php`

**Intent**: `assertInertia` dla nowych propsów i reguły odczytu panelu.

**Contract**: przypadki opisane w Testing Strategy (Phase 1).

### Success Criteria:

#### Automated Verification:

- Testy Raportów przechodzą na PostgreSQL (komenda dla worktree z AGENTS.md, baza `testing_report_ranking`): `tests/Feature/ReportsPageTest.php tests/Feature/ReportCategoryPanelTest.php tests/Feature/PeriodReportTest.php tests/Feature/ReportTrendTest.php`
- Test wyboru roku przechodzi (przekierowanie z `category` i `ranking`)
- NFR roku z panelem mieści się w 2 s na PostgreSQL: `--group=nfr tests/Feature/Nfr/NfrPerfReportTest.php`
- `npm run types:check` przechodzi z nowymi typami i fixtures
- `vendor/bin/pint --dirty --format agent` bez zmian i `vendor/bin/sloppy ci` bez nowego znaleziska

#### Manual Verification:

- `/reports?category=<id>` w przeglądarce zwraca w `categoryPanel.requested_category_id` to id, a `?category=abc` daje `null` (Vue devtools albo odpowiedź Inertii)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej przed fazą 2.

---

## Phase 2: Ranking zamiast tabeli i wykresu

### Overview

Rok, Miesiąc i 12 miesięcy pokazują Ranking z paskiem udziału i przełącznikiem „Kategorie | Grupy”; wykres słupkowy znika. W Roku nazwa jeszcze linkuje do Operacji (panel dochodzi w fazie 3).

### Changes Required:

#### 1. Układanie Rankingu

**File**: `resources/js/lib/report-ranking.ts` (+ `report-ranking.test.ts`)

**Intent**: Jedna czysta funkcja układa wiersze serwera według ADR-0097:21 i jest też źródłem wyboru domyślnego w fazie 3.

**Contract**:
- `rankReportCategories(entries)`: wszystkie kategorie płasko (z grup i bez grupy), każda z `position` (1..n) i `groupName: string | null`; wiersze z `has_operations` po kwocie malejąco, remis → kolejność tablicy (lista); wiersze tylko z porównania na końcu w kolejności listy.
- `rankReportGroups(entries)`: wiersze pierwszego poziomu (grupy i kategorie bez grupy) po sumie z tą samą regułą, kategorie w grupie też po kwocie; bez numerów.
- `reportShareBarRatio(amount, largestFirstLevel)`: 0..1, `0` dla kwoty niedodatniej albo niedodatniej największej; największa kwota pierwszego poziomu liczona dla trybu (płasko: największa kategoria; Grupy: największy wiersz pierwszego poziomu), także dla kategorii w grupie.

#### 2. Komponenty Rankingu

**File**: nowe `resources/js/components/ReportRanking.vue`, `ReportRankingRow.vue`, `ReportRankingGroupRow.vue`, `ReportShareBar.vue`; usunięte `ReportEntryTable.vue`, `ReportCategoryRow.vue`, `ReportGroupRow.vue`

**Intent**: Lista „Wydatki od największych” / „Przychody od największych” w `ListSurface` na klasach `.report-grid` (rozszerzonych o numer i pasek), z tym, co dziś pokazuje tabela: Razem, zarchiwizowane (Badge, wyciszenie), porównanie (rok | rok wcześniej | Różnica | Udział i dwie linie poniżej 36rem), ton różnicy (`reportDifferenceTone` + `planChangeToneClass`), stopka „Kwoty w zł” / „Kwoty w zł · różnica do <N−1>”.

**Contract**:
- `ReportRankingRow`: numer (tylko w trybie Kategorie), `CategoryChip`, `ReportShareBar` pod nazwą, link do Operacji przeniesiony z `ReportCategoryRow` (`reportCategoryOperationsQuery` / `reportRangeOperationsQuery`, `aria-label` z `reportOperationsLinkLabel`, brak linku bez operacji); `data-report-row="category"`.
- `ReportRankingGroupRow`: grupa z `aria-expanded`/`aria-controls`, startuje zwinięta, bez linku; `data-report-row="group"`.
- `ReportShareBar`: `aria-hidden`, wypełnienie `chart-1` (wydatek) albo `success` (przychód), tło `track`, szerokość z `reportShareBarRatio`; bez Unovis i bez `defineAsyncComponent`.
- `ReportRanking`: props typu, tytułu, wpisów, `mode`, sum, porównania i kontekstu okresu (`budgetYear`, `month`, `range`); przełącznik trybu tylko na liście Wydatków (`SegmentedControl`, `tone="neutral"`, etykiety „Kategorie” / „Grupy”); Przychody bez kolumny Udział.

#### 3. Strona Raportów

**File**: `resources/js/pages/Reports.vue`

**Intent**: Zastąpić blok wykresów i tabel (:231-274) dwoma `ReportRanking`, usunąć async `ReportBarChart`, przenosić `ranking` w adresie.

**Contract**: Zmiana trybu: `router.replace({ url: reportsRoute({ query }), props: (current) => ({ ...current, rankingMode }), preserveScroll: true, preserveState: true, preserveFlash: true })` z zachowaniem `month`/`view`/`category`. URL-e przełącznika okresu (rok, `?month`, `?view=last-12`) niosą `ranking=groups`, gdy aktywne; Trend go nie niesie.

#### 4. Menu roku przenosi parametry

**File**: `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: Przy `destination: 'reports'` wysłać `report_category` i `report_ranking` z bieżącego adresu, jeśli są (pole `category` pojawi się w adresie w fazie 3).

**Contract**: Payload `selectYear` z polami opcjonalnymi; `reports-trend` i inne cele bez zmian.

#### 5. Usunięcie wykresu słupkowego

**File**: usunięte `resources/js/components/ReportBarChart.vue`, `ReportBarChartPlaceholder.vue`, `resources/js/lib/report-chart.ts`, `report-chart.test.ts`

**Intent**: ADR-0097:21 — brak wykresu obok Rankingu; `--chart-2` zostaje zarezerwowany bez odbiorcy.

**Contract**: Żaden import tych plików nie zostaje (`npm run types:check`); `ReportChartLegend` zostaje dla Trendu.

#### 6. CSS

**File**: `resources/css/app.css`

**Intent**: Rozszerzyć `.report-grid` o obszar numeru i pasek udziału (pasek pod nazwą), zgodnie z regułą `css.md` (nazwane klasy, wartości z tokenów).

**Contract**: Nowe klasy rodziny `.report-*` (np. `.report-rank`, `.report-share-bar`, `.report-share-bar-fill`); zapytanie kontenerowe 36rem działa jak dziś. Klasy usuniętego wykresu, jeśli są, usunięte.

#### 7. Strażniki, kitchen sink, kontrakt UI

**File**: `tests/Unit/ReportsHardcodedUiTest.php`, `scripts/check-ui-literals.mjs`, `resources/js/ui-contract.test.ts`, `resources/js/pages/dev/ReportsKitchenSink.vue`, `resources/js/lib/reports-fixtures.ts`

**Intent**: Listy plików wskazują nowe komponenty zamiast usuniętych; kontrakt linku czyta `ReportRankingRow.vue` (linkuje przez `reportCategoryOperationsQuery` i `reportRangeOperationsQuery`) i `ReportRankingGroupRow.vue` (nie linkuje); sink traci placeholdery wykresu słupkowego (zostaje placeholder Trendu).

**Contract**: Nowe scenariusze `ranking-groups` (`rankingMode: 'groups'`) i `ranking-tie` (Czynsz 1 800 i Prąd 1 800: wyżej ten wcześniejszy na liście); `stateMatrix` sinka opisuje Ranking.

#### 8. E2E

**File**: `tests/e2e/reports.spec.ts`, `tests/e2e/reports-view.spec.ts`

**Intent**: Asercje wykresu zamienione na Ranking, test zwijania przeniesiony na tryb Grupy, testy „miejsca wykresu słupkowego” usunięte (test Trendu zostaje).

**Contract**: Skrót 8 pokazuje obie listy Rankingu z paskami i dwa „Razem”; porównanie pokazuje rok, rok wcześniej i Różnicę; Grupy: grupy zwinięte, rozwinięcie pokazuje kategorie po kwocie, `?ranking=groups` przechodzi przez Rok → Miesiąc → 12 miesięcy; testy wąskiej karty (nazwy nieucięte, „udział” pod kwotą, Badge pod nazwą) na nowych klasach.

#### 9. Reguła

**File**: `.ai/rules/reports.md`

**Intent**: Przepisać zdania o wykresie obok tabeli, `ReportBarChartPlaceholder` i miejscu wykresu, o `ReportEntryTable`/`ReportGroupRow` oraz o tym, że wykres jest `aria-hidden`, a tabela jedyną formą dostępną (ADR-0097:29).

**Contract**: Sekcja o bibliotece: Unovis tylko w Trendzie, znaczniki w wierszu to CSS z tokenów (`aria-hidden`, bo dublują liczby wiersza). Sekcja o stronie Raportów: Ranking (`report-ranking.ts`, kolejność listy z serwera, remis = lista), tryb `?ranking=groups`, komponenty `ReportRanking*`. „Cztery segmenty” zostają do S-34. We frontmatterze `paths:` glob `resources/js/lib/report-chart*.ts` zastępuje `resources/js/lib/report-ranking*.ts`; potem `php artisan boost:index-rules` regeneruje `.ai/rules/index.md`.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi, w tym `resources/js/lib/report-ranking.test.ts` i `resources/js/ui-contract.test.ts`: `npm run test`
- `npm run types:check` i `npm run lint` przechodzą
- `npm run check:ui` przechodzi z nową listą plików
- `php artisan test --compact tests/Unit/ReportsHardcodedUiTest.php tests/Feature/ReportsKitchenSinkTest.php` przechodzi
- `npm run build` przechodzi i `grep -rn "ReportBarChart\|report-chart'" resources/js` nic nie zwraca
- E2E Raportów przechodzą: `npx playwright test tests/e2e/reports.spec.ts tests/e2e/reports-view.spec.ts`
- `php artisan boost:index-rules` daje w `.ai/rules/index.md` glob `report-ranking*` zamiast `report-chart*`, bez innych różnic

#### Manual Verification:

- `/dev/ui/reports` pokazuje Ranking we wszystkich scenariuszach Roku, Miesiąca i 12 miesięcy w obu motywach, z paskiem `chart-1`/`success` i pustym `track` dla kwoty niedodatniej
- Tryb Grupy: grupy zwinięte, rozwinięcie po kwocie, tryb zostaje po zmianie okresu i roku w menu

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej przed fazą 3.

---

## Phase 3: Panel kategorii w Roku

### Overview

Widok Rok dostaje panel wybranej kategorii: obok Rankingu na szerokim ekranie, jako arkusz od dołu na wąskim. Wybór żyje w `?category` i zmienia się wizytą klienta.

### Changes Required:

#### 1. Logika panelu

**File**: `resources/js/lib/report-category-panel.ts` (+ `report-category-panel.test.ts`)

**Intent**: Czyste funkcje dla wszystkiego, co panel wylicza z propsów bez liczenia kwot.

**Contract**:
- `resolveSelectedCategory(requestedId, expenseRanking, incomeRanking)`: żądana kategoria, inaczej pierwsza pozycja Wydatków, inaczej pierwsza Przychodów, inaczej `null` (rankingi z `rankReportCategories`).
- `reportMonthBars(months, closedMonthCount, inProgressMonth, highestMonth)`: 12 słupków ze stanem `past` (m ≤ `closed_month_count`), `in-progress` (m = `in_progress_month`) albo `future` (reszta; rok przyszły ma 0 zamkniętych i brak miesiąca w toku), wysokością względem największego miesiąca nie przyszłego > 0, `isHighest` = (m = `highest_month` z serwera; klient nie liczy najwyższego ani remisu), ujemne jako zero.
- `reportClosedRangeLabel(closedMonthCount)`: „sty–paź”, „sty–gru”, `null` przy 0; `reportInProgressLabel(inProgressMonth, through)`: „Listopad do 14.11 (w toku)”.
- `reportMonthBarsAccessibleList(months, …)`: pozycje „<miesiąc>: <kwota>” bez miesięcy przyszłych.
- `reportPanelCaption(type, groupName, rhythm)`: „Wydatek · grupa Codzienne · rytm bieżąca”; bez grupy bez członu grupy; przychód bez rytmu (etykiety rytmu z istniejących tłumaczeń Kategorii).

#### 2. Komponenty panelu

**File**: nowe `resources/js/components/ReportCategoryPanel.vue`, `ReportMonthBars.vue`

**Intent**: Treść panelu z karty `PageReports` i ADR-0097:23.

**Contract**:
- Nagłówek: `CategoryChip` `lg` i podpis; KPI: kwota roku, kwota N−1 (z porównaniem), Różnica jako kwota z tonem i „±x,x% względem <N−1>” (`formatShareTenths` dla `difference_tenths_percent`, „—” przy `null`), Udział z „największa pozycja” na pozycji 1 (tylko wydatki).
- Sekcja „Wydatki w czasie” / „Przychody w czasie” z etykietą „Miesiące <rok>” (bez przełącznika; S-36).
- `ReportMonthBars`: 12 słupków `aria-hidden` (wydatek `chart-1`, przychód `success`, najwyższy `foreground`, w toku kreskowany w kolorze typu, przyszłe puste) i lista `sr-only`.
- Wiersze: „Średnio na pełny miesiąc (<zakres>)” z `closed_average_minor_units` („—” przy `null`), „Najwięcej: <miesiąc>” („—” przy `null`), „<Miesiąc> do <D.MM> (w toku)” tylko przy `in_progress_month`.
- Przycisk „Operacje: <kategoria>, <rok>” do `operations({ query: reportCategoryOperationsQuery(category, budgetYear, null) })`; brak przycisku dla wiersza tylko z porównania (`has_operations: false`).
- Kwoty w złotych z groszami (`formatMoney`).

#### 3. Układ, wybór i arkusz

**File**: `resources/js/pages/Reports.vue`, `resources/js/components/ReportRanking.vue`, `ReportRankingRow.vue`

**Intent**: W Roku nazwa kategorii wybiera panel zamiast linkować; szeroki obszar ma dwie kolumny (Rankingi | panel), wąski otwiera `ui/sheet`.

**Contract**:
- `ReportRanking` w Roku przyjmuje `selectedCategoryId` i emituje `select(id)`. Przycisk nazwy wybranej kategorii ma `aria-current="true"` i `aria-controls` panelu, a wiersz tło `secondary`; wiersze zostają divami bez ról tabeli (Critical Implementation Details, ADR-0100). Wiersz tylko z porównania też jest wybieralny. Miesiąc i 12 miesięcy bez zmian (link).
- Tryb Grupy w Roku: grupa zawierająca wybraną kategorię (domyślną albo żądaną) startuje rozwinięta, pozostałe zwinięte; zmiana wyboru nie zwija ani nie rozwija grup.
- Wybór: `router.replace({ url: reportsRoute({ query: { category, ranking? } }), props: (current) => ({ ...current, categoryPanel: { ...current.categoryPanel, requested_category_id: id } }), preserveScroll: true, preserveState: true, preserveFlash: true })`.
- Tryb układu: `unknown | aside | sheet` z `useElementSize` opakowania strony; `unknown` do pierwszego pomiaru (szerokość 0), potem porównanie z progiem tokenu (Critical Implementation Details).
- Szeroko: siatka dwóch kolumn na zapytaniu kontenerowym (nowa klasa w `app.css` z `container-type: inline-size` na opakowaniu, wzorem `.report-table`, nie `.dashboard-grid`), panel w `<aside>` przyklejony (`sticky`) do góry; podświetlony wybór domyślny albo żądany. `<aside>` pokazuje i chowa CSS, więc w `unknown` nic nie skacze.
- Wąsko (szerokość obszaru treści poniżej progu): brak `<aside>`. `Sheet` od dołu z `SheetTitle` = nazwa kategorii otwiera się tapnięciem nazwy albo, dopiero gdy tryb przejdzie z `unknown` w `sheet`, przy wejściu z `requested_category_id` różnym od `null`. Treść arkusza przewija się (klasa z §4 przez `class` na `SheetContent`, bez edycji `components/ui`). Zamknięcie ma własny `SheetClose` z polską etykietą z `lang/pl/ui.php` i robi `router.replace` bez `category` (`requested_category_id: null`). Bez otwartego arkusza żaden wiersz nie jest podświetlony.

#### 4. CSS

**File**: `resources/css/app.css`

**Intent**: Siatka Rok + panel, słupki miesięcy i kreskowanie miesiąca w toku jako nazwane klasy z tokenów.

**Contract**: Klasy rodziny `.report-*`:
- siatka na `@container` z progiem jako nazwanym tokenem w `:root` (ta sama wartość w JS);
- panel;
- słupek;
- stan `in-progress` jako `repeating-linear-gradient` z `var(--chart-1)` albo `var(--success)` i przezroczystości;
- `is-highest` w `foreground`;
- panel w arkuszu z `max-height` w `svh` z tokenu i `overflow-y: auto`.

Szerokość panelu to nazwany token w `:root` bez `vw` (reguła `css.md`). Żadnego nowego koloru.

#### 5. Kitchen sink i scenariusze

**File**: `resources/js/lib/reports-fixtures.ts`, `resources/js/pages/dev/ReportsKitchenSink.vue`, `tests/Unit/ReportsHardcodedUiTest.php`, `scripts/check-ui-literals.mjs`

**Intent**: Każdy stan panelu jako scenariusz z pełnym `categoryPanel`; nowe komponenty na listach strażników.

**Contract**: Scenariusze `panel-year-in-progress` (kreskowanie, „w toku”, „sty–paź”), `panel-year-closed` (12 zamkniętych, bez „w toku”), `panel-january` („Średnio” „—”), `panel-comparison-only` (Kino: 0,00 / 600,00 / −600,00, „−100,0%”, bez przycisku Operacje), `panel-income` (`success`, bez Udziału i rytmu), `panel-negative-month` (słupek zerowy, dokładna kwota w `sr-only`), `panel-no-previous` (bez porównania: bez N−1, Różnicy i procentu), `panel-groups` (`rankingMode: 'groups'`, wybrana kategoria w grupie: ta grupa rozwinięta, pozostałe zwinięte), `panel-phone` (wąsko: Ranking bez podświetlenia, arkusz zamknięty).

#### 6. E2E panelu

**File**: `tests/e2e/reports.spec.ts` (albo nowy `tests/e2e/reports-category-panel.spec.ts`)

**Intent**: Ścieżki, których nie widzi Pest ani Vitest: wizyta klienta, arkusz, przenoszenie parametrów.

**Contract**:
- (a) klik nazwy w Roku → adres z `?category=<id>`, panel z nazwą i kwotą wiersza, `aria-current="true"` na przycisku nazwy;
- (b) „Operacje: <kategoria>, <rok>” → lista Operacji z sumą równą kwocie panelu;
- (c) widok telefonu: tapnięcie otwiera arkusz, zamknięcie zdejmuje `category` z adresu;
- (d) zmiana roku w menu z `?category` i `?ranking=groups` zostawia oba parametry i panel tej kategorii w nowym roku, a grupa tej kategorii jest rozwinięta;
- (e) wejście na `?category=<id>` po odświeżeniu pokazuje ten sam panel; w szerokim oknie arkusz (`role="dialog"`) ani razu się nie pojawia.

#### 7. Reguła i design system

**File**: `.ai/rules/reports.md`, nowy `context/foundation/adr/0100-report-ranking-selection-uses-aria-current.md`, `context/foundation/design-system.md` (tylko lista w „Kitchen sink i zrzuty”, jeśli wymienia scenariusze Raportów)

**Intent**: Dopisać panel: `categoryPanel` z `ReportCategoryPanel`, `?category` z cichym fallbackiem, wybór domyślny po stronie klienta z `report-ranking.ts`, `aria-current` na wybranej nazwie, arkusz na wąskim obszarze, lista `sr-only` jako forma dostępna słupków miesięcy (ADR-0097:27). ADR-0100 zapisuje odejście od `aria-selected` z ADR-0097:23: wiersze bez ról `grid`/`listbox`/`tablist`, bo te role wymagają nawigacji strzałkami, a przyciski grup nie mogą leżeć w `listbox` ani `tablist`.

**Contract**: Zdania reguły zgodne z kodem; żadnego nowego tokenu ani znaczenia koloru (kreskowanie jest już w „Wykresy”). ADR-0100 odsyła do ADR-0097:23 i go zawęża; tekst ADR-0097 zostaje bez zmian. Do `paths:` reguły dochodzi `resources/js/lib/report-category-panel*.ts`, potem `php artisan boost:index-rules`.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi, w tym `resources/js/lib/report-category-panel.test.ts`: `npm run test`
- `npm run types:check`, `npm run lint` i `npm run check:ui` przechodzą
- E2E panelu i Raportów przechodzą: `npx playwright test tests/e2e/reports.spec.ts tests/e2e/reports-view.spec.ts` (oraz `reports-category-panel.spec.ts`, jeśli powstanie)
- Pełna bramka na PostgreSQL w worktree: `composer ci:check` przez obraz `sail-8.5/app` (komenda z AGENTS.md, baza `testing_report_ranking`)
- `vendor/bin/sloppy ci` na hoście bez nowego znaleziska

#### Manual Verification:

- `/dev/ui/reports`: każdy scenariusz `panel-*` w jasnym i ciemnym motywie zgadza się z kartą `PageReports` (kafelek, podpis, KPI, słupki, kreskowanie, „—” w pustych stanach)
- Aplikacja na danych 2025–2026: klik kolejnych kategorii zmienia panel bez przeładowania, odświeżenie zostawia wybór, przycisk Operacje prowadzi do tej samej sumy
- Telefon (albo wąskie okno): arkusz otwiera się i zamyka, lista nie przeskakuje, fokus wraca na nazwę
- Czytnik ekranu (albo drzewo dostępności): wybrana nazwa ogłasza się jako bieżąca (`aria-current`), słupki są ukryte, lista miesięcy czytelna

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej.

---

## Testing Strategy

### Unit Tests:

- `report-ranking.test.ts`: sortowanie malejąco; remis Czynsz 1 800 / Prąd 1 800 → kolejność listy; wiersze tylko z porównania na końcu w kolejności listy; kategorie z grup i bez grupy w jednej liście z `groupName`; tryb Grupy (grupy i kategorie bez grupy po sumie, kategorie w grupie po kwocie); `reportShareBarRatio` dla kwoty ujemnej, zerowej i niedodatniej największej.
- `report-category-panel.test.ts`: wybór domyślny (żądany, pierwszy wydatek, pierwszy przychód, `null`); słupki roku bieżącego (przyszłe puste mimo kwoty w komórce), minionego i przyszłego; `isHighest` tylko na `highest_month` z propsów (także `null` → żaden); ujemny miesiąc; etykiety „sty–paź”, „sty–gru”, `null`; „Listopad do 14.11 (w toku)”; lista `sr-only` bez przyszłych; podpis z grupą, bez grupy, przychód.

### Integration Tests:

- `ReportsPageTest` (`assertInertia`): `rankingMode` domyślny, `groups`, nieznana wartość; `categoryPanel` `null` w Miesiącu, 12 miesiącach, Trendzie, szkicu, bez Budżetu i bez operacji; w Roku `requested_category_id` dla poprawnego id, wiersza tylko z porównania, cudzego id, nieistniejącego, bez wiersza i `abc` (cztery ostatnie → `null`); `closed_month_count`, `in_progress_month`, `through` dla roku bieżącego, minionego i przyszłego (`travelTo`).
- `ReportCategoryPanelTest`: suma `months` = `amount_minor_units` wpisu raportu roku dla każdej kategorii (także z groszami i zwrotem netto); `null` vs `0`; średnia z zaokrągleniem pół od zera; `highest_month` z remisem, bez miesięcy przyszłych i `null` przy braku dodatnich; `difference_tenths_percent` (wzrost, spadek, N−1 = 0, bez porównania); rytm z miesiąca oglądanego (zmiana rytmu w lipcu: rok bieżący w listopadzie pokazuje nowy, rok miniony grudniowy); przychód bez rytmu; liczba zapytań stała względem liczby kategorii.
- Wybór roku: `destination: reports` z `report_category` i `report_ranking` → `/reports?category=…&ranking=groups`; bez nich → `/reports`; `report_ranking: 'x'` → błąd walidacji.
- NFR: rok z panelem w 2 s przy 10 000 operacjach (PostgreSQL).
- E2E: Faza 2 punkt 8 i Faza 3 punkt 6.

### Manual Testing Steps:

1. `/reports` w roku bieżącym: Ranking Wydatków i Przychodów, panel pierwszej pozycji, kreskowany listopad.
2. Klik „Kino” (tylko z porównania): panel 0,00 / 600,00, bez przycisku Operacje.
3. Przełącz „Grupy”, zmień okres na Miesiąc i z powrotem, zmień rok w menu: tryb i kategoria zostają.
4. `/reports?category=999999`: panel pierwszej pozycji, bez błędu.
5. Wąskie okno: tapnięcie otwiera arkusz, zamknięcie zdejmuje `category`.

## Performance Considerations

Panel nie dodaje zapytań o operacje (podział miesięczny z odczytu `forYear`); dochodzi jedno zapytanie o rytmy. Payload roku rośnie o 12 liczb na kategorię (rząd 60 × 12), bez znaczenia dla 2 s z NFR. Sortowanie Rankingu po stronie klienta to O(n log n) dla kilkudziesięciu wierszy.

## Migration Notes

Brak migracji danych. Stare linki `/reports` i `/reports?month=…` działają bez zmian; nowe parametry są opcjonalne.

## References

- ADR: `context/foundation/adr/0097-report-years-ranking-and-chart-colours.md` (:21 Ranking, :23 Category Panel, :27 Colour, :29 reguła)
- Słownik: `context/foundation/glossary.md:169-181`; design system: `context/foundation/design-system.md:153-170`
- Karta: claude.ai artifact Atrament, `project/components/PageReports/` (wariant C)
- Roadmapa: `context/foundation/roadmap.md` S-35
- Wzorce: `app/Support/ReportTrend.php:47-106` (komórki miesięcy), `resources/js/pages/Categories.vue:154-185` (parametr w adresie), `app/Http/Controllers/ReportController.php:106-115` (cichy fallback), `resources/css/app.css:1680` i `@container (min-width: 36rem)` (układ po szerokości karty)
- Poprzednie zmiany: `context/archive/2026-11-12-reports-view/`, `context/archive/2026-11-12-report-to-operations/`, `context/archive/2026-11-11-year-over-year-report/`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Serwer — dane panelu i parametry

#### Automated

- [x] 1.1 Testy Raportów przechodzą na PostgreSQL (ReportsPageTest, ReportCategoryPanelTest, PeriodReportTest, ReportTrendTest)
- [x] 1.2 Test wyboru roku przechodzi (przekierowanie z category i ranking)
- [x] 1.3 NFR roku z panelem mieści się w 2 s na PostgreSQL
- [x] 1.4 npm run types:check przechodzi z nowymi typami i fixtures
- [x] 1.5 Pint bez zmian i sloppy ci bez nowego znaleziska

#### Manual

- [ ] 1.6 /reports?category=<id> zwraca requested_category_id, ?category=abc daje null

### Phase 2: Ranking zamiast tabeli i wykresu

#### Automated

- [ ] 2.1 Vitest przechodzi, w tym report-ranking.test.ts i ui-contract.test.ts
- [ ] 2.2 npm run types:check i npm run lint przechodzą
- [ ] 2.3 npm run check:ui przechodzi z nową listą plików
- [ ] 2.4 ReportsHardcodedUiTest i ReportsKitchenSinkTest przechodzą
- [ ] 2.5 npm run build przechodzi i nie zostaje żaden import ReportBarChart ani report-chart
- [ ] 2.6 E2E Raportów przechodzą (reports.spec.ts, reports-view.spec.ts)
- [ ] 2.7 boost:index-rules daje glob report-ranking* zamiast report-chart*

#### Manual

- [ ] 2.8 /dev/ui/reports pokazuje Ranking we wszystkich scenariuszach w obu motywach
- [ ] 2.9 Tryb Grupy: grupy zwinięte, rozwinięcie po kwocie, tryb zostaje po zmianie okresu i roku

### Phase 3: Panel kategorii w Roku

#### Automated

- [ ] 3.1 Vitest przechodzi, w tym report-category-panel.test.ts
- [ ] 3.2 npm run types:check, npm run lint i npm run check:ui przechodzą
- [ ] 3.3 E2E panelu i Raportów przechodzą
- [ ] 3.4 Pełna bramka composer ci:check na PostgreSQL w worktree
- [ ] 3.5 sloppy ci na hoście bez nowego znaleziska

#### Manual

- [ ] 3.6 Scenariusze panel-* w kitchen sinku zgadzają się z kartą PageReports w obu motywach
- [ ] 3.7 Aplikacja na danych 2025–2026: wybór bez przeładowania, odświeżenie zostawia wybór, Operacje z tą samą sumą
- [ ] 3.8 Telefon: arkusz otwiera się i zamyka, lista nie przeskakuje, fokus wraca na nazwę
- [ ] 3.9 Czytnik ekranu: wybrana nazwa ogłasza się jako bieżąca, słupki ukryte, lista miesięcy czytelna
