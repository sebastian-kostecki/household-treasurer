# Widok Raporty: stany, miejsce na wykres i sink na prawdziwej stronie — plan implementacji

## Overview

Zmiana UI z `/10x-ui reports-view`. Usuwa pięć zarzutów z `research.md` (C1–C5) w `resources/js/pages/Reports.vue`, jego komponentach `Report*` i w kitchen sinku `/dev/ui/reports`:

- **C1:** wiersz tytułu (`h1`) i przełącznik okresu w każdym stanie z Budżetem; w drafcie „12 miesięcy” jest osiągalne z przełącznika i ma drogę powrotną.
- **C2 + C4:** asynchroniczne wykresy dostają placeholder o dokładnej wysokości, więc tabela nie zjeżdża po doładowaniu fragmentu; stałe rozmiarów wykresu przechodzą do `@/lib/report-chart`.
- **C3:** sink renderuje prawdziwy `Reports.vue` z propsami z fixture, zamiast trzech ręcznych kopii kompozycji.
- **C5:** w karcie węższej niż 36rem Udział schodzi pod kwotę, więc nazwa kategorii mieści się w 22rem.

Serwer się nie zmienia: żaden prop, kontroler ani test `assertInertia` nie dochodzi.

## Current State Analysis

**Stany puste wychodzą przed wierszem tytułu (C1).**
- `Reports.vue:125-130`: przy `budgetYear === null` i przy `budgetPhase !== 'execution' && period.kind !== 'last-12'` strona renderuje tylko `ReportEmptyState`; `h1.page-title` i `SegmentedControl` żyją w `v-else` (`:132-150`).
- Serwer już dziś daje w drafcie pełne `period` (`kind`, `month`, `default_month`) i `report: null` / `trend: null` (`app/Http/Controllers/ReportController.php:56-68`), a `?view=last-12` zwraca okno niezależnie od fazy (`:49-50`, `:75-92`). Bez Budżetu każda kwerenda daje `budgetYear: null` (`:29-43`).
- Tytuł strony (`Reports.vue:93-118`) już liczy rok, miesiąc, last-12 i Trend z `period` i `budgetYear`, więc w drafcie da poprawny tekst bez zmian.

**Wykresy bez miejsca (C2, C4).**
- `Reports.vue:18-20` i `ReportTrendSummary.vue:8-10`: goły `defineAsyncComponent`, bez `loadingComponent`; `ReportBarChart.vue:97` i `ReportTrendChart.vue:72` (`v-if="bars.length > 0"`) pojawiają się dopiero po fragmencie. Zmierzone przesunięcie tabeli: 414 px (1440), 410 px (352), Trend 314 px (`research.md`, C2).
- Wysokość słupkowego to `bars.length × (3 | 4.5) + 2` rem w inline style (`ReportBarChart.vue:75-83`); Trend ma `h-64` w opakowaniu `p-4` (`ReportTrendChart.vue:72`, `:91`). Obie legendy (`meta mb-3`) stoją nad wykresem: słupkowa tylko z porównaniem (`ReportBarChart.vue:98-114`), Trendu zawsze (`ReportTrendChart.vue:73-89`).
- Vue renderuje `loadingComponent` z propsami komponentu asynchronicznego (`createInnerComp`, `node_modules/@vue/runtime-core/dist/runtime-core.cjs.js:2908-2915`, `:2898-2902`), a domyślne `delay` to 200 ms.
- `reportChartBars` (`resources/js/lib/report-chart.ts:19-35`) i `reportTrendChartBars` (`@/lib/report-trend-chart`) są czystymi funkcjami, więc liczba słupków jest znana przed fragmentem.
- `Skeleton` istnieje (`resources/js/components/ui/skeleton/Skeleton.vue`), żaden plik Raportów go nie używa.

**Sink kopiuje stronę (C3).**
- `ReportsKitchenSink.vue:150-437`: trzy własne kopie wiersza tytułu i ciała, tytuł jako `<p>`, sześć scenariuszy miesiąca z tytułem „Raporty”. Fixture (`resources/js/lib/reports-fixtures.ts:14-22`) to `{ key, caption, phone?, kind, period, report, trend }`, bez `budgetYear`/`budgetPhase`.
- `Reports.vue` bierze dane tylko z `defineProps<ReportsPageProps>()` (`:22`); tłumaczenia z `usePage().props.translations` (`useTranslations.ts:6-10`) są współdzielone i dostępne na stronie sinka; layout przypina `app.ts:12` po nazwie strony, więc osadzony komponent nie dostaje drugiego layoutu. Żaden komponent `Report*` nie woła `usePage`.
- Precedens: `/dev/ui/auth` renderuje prawdziwy komponent strony z propsami fixture (ADR-0056:37).

**Wąska tabela ucina nazwy (C5).**
- `.report-grid` (`resources/css/app.css:1462-1465`) to `minmax(0,1fr) minmax(5.5rem,auto) 4.5rem`, więc przy treści ~288 px nazwa dostaje ~96 px. Wąski wiersz porównania ma obszary `'name amount share' / 'previous difference .'` (`:1498-1503`), więc też ~96 px. `.report-cell-name` to `flex` bez zawijania (`:1602-1604`), więc Badge „zarchiwizowana” zjada nazwę.
- Mechanizm `@container` na `.report-table` dla 36rem już działa dla porównania (`:1488-1580`, ADR-0074). Etykieta przed kwotą w wąskim układzie ma wzorzec `.report-previous-label` (`:1531-1533`, ukryta od 36rem `:1571-1573`).

**Testy.** Vitest bez `@vue/test-utils` (brak testów komponentów). E2E (`playwright.config.ts`) idzie na buildzie produkcyjnym przez `scripts/e2e-server.sh`, z `.env` (`APP_ENV=local`), więc `/dev/ui/reports` działa w E2E. Baza E2E nie ma Budżetu w drafcie ani użytkownika bez Budżetu.

## Desired End State

- Na `/reports` w drafcie (rok, miesiąc, Trend) widać `h1` z tytułem widoku, przełącznik z czterema segmentami i kartkę „Budżet w przygotowaniu” pod nimi; „12 miesięcy” pokazuje raport okna, a „Rok” z niego wraca do kartki z przełącznikiem. Bez Budżetu widać `h1` „Raporty” i kartkę „Brak budżetu”, bez przełącznika.
- Przy pierwszym wejściu na `/reports` i `?view=trend` placeholder (`Skeleton` w `ListSurface`) zajmuje dokładnie miejsce wykresu z legendą; nagłówek tabeli „Wydatki” (i tabeli miar Trendu) stoi w tym samym miejscu przed i po doładowaniu fragmentu. Przy braku słupków nie ma ani wykresu, ani placeholdera.
- `/dev/ui/reports` renderuje `Reports.vue` w każdym scenariuszu, więc tytuły i znaczniki są takie jak na stronie; jest scenariusz `draft-last-12`; wiersz `loading` macierzy wskazuje sekcję z placeholderami.
- W 22rem tabela „Wydatki” pokazuje pełne nazwy kategorii, a „udział 66,7%” stoi pod kwotą (bez porównania w drugiej linii, z porównaniem w trzeciej); Badge „zarchiwizowana” schodzi pod nazwę, gdy się nie mieści. Od 36rem karty wiersz wygląda jak dziś.
- Weryfikacja: nowe testy E2E (`tests/e2e/reports-view.spec.ts`) i test jednostkowy `report-chart.test.ts` przechodzą z czerwonego na zielony; `composer ci:check` i `vendor/bin/sloppy ci` są zielone.

### Key Discoveries:

- Serwer już daje w drafcie wszystko, czego potrzebuje wiersz tytułu (`ReportController.php:56-68`), więc C1 to zmiana tylko w szablonie `Reports.vue`.
- `loadingComponent` dostaje te same propsy co wykres (`runtime-core.cjs.js:2898-2915`), więc placeholder sam liczy swoją wysokość z `entries`/`comparisonYear` albo `months`, bez nowego propa w stronie.
- Sink może osadzić `<Reports v-bind="scenario.props" />` bez kontrolera i trasy na scenariusz, bo strona nie czyta `usePage()` poza współdzielonymi tłumaczeniami.
- Ramka telefonu sinka (22rem) z prawdziwą stroną (`p-4`) daje treść 320 px, tak jak okno 352 px. Dziś sink pokazuje tabelę szerszą o 32 px niż telefon.

## What We're NOT Doing

- Żadnych zmian po stronie serwera, propsów Inertia ani `ReportsPageTest.php`; draft nadal dostaje `report: null` dla roku, miesiąca i Trendu.
- Bez linku do „12 miesięcy” w kartce draftu, na Dashboardzie, w pasku bocznym czy palecie poleceń (Decisions 1 odrzuciły drugie wejście); bez segmentów wyłączonych.
- Bez `errorComponent` dla nieudanego fragmentu wykresu: błąd ładowania idzie jak dziś przez obsługę błędów Vue (`handleError`), placeholder zostaje na miejscu; tabela obok nadal niesie dane.
- Bez zmian w `CategoryChip`, `components/ui/*` (także `Skeleton`) i bez nowych tokenów rozmiaru w `app.css` dla wykresu (Decisions 3 odrzuciły `calc()`).
- Bez łamania nazwy kategorii w kilku liniach i bez zmian w tabeli Trendu (`.report-trend-grid` przewija się jak dziś).
- Bez Budżetu w drafcie i użytkownika bez Budżetu w bazie E2E; stany te sprawdza E2E na scenariuszach sinka, który po fazie 1 jest prawdziwą stroną.
- Bez pomiaru CLS przy wizytach Inertia między okresami (research mierzył tylko pełne wczytanie).

## Implementation Approach

Najpierw architektura: sink zaczyna renderować `Reports.vue`, więc każda kolejna poprawka ląduje w jednym pliku, a scenariusze sinka stają się bramką E2E dla stanów, których nie ma w bazie E2E. Potem trzy poprawki widoku, każda zaczyna się od testu, który nie przechodzi na obecnym kodzie (CLAUDE.local.md: „Błąd najpierw staje się nieprzechodzącym testem”). ADR-0083 powstaje w fazie 1, zanim zmieni się zachowanie (zmiana zakresu S-32, `rolling-twelve-month-report/plan.md:102`). Reguły agenta (`.ai/rules/reports.md`) i listy sinków dochodzą w fazie 4, gdy wszystkie mechanizmy istnieją.

## Critical Implementation Details

- **Kolejność w fazie 3:** `defineAsyncComponent({ loader, loadingComponent, delay: 0 })`. Bez `delay: 0` przez pierwsze 200 ms nie ma ani placeholdera, ani wykresu, a fragment z cache przychodzi właśnie w tym czasie — przeskok zostaje. Placeholder musi mieć ten sam warunek obecności co wykres (`bars.length > 0`), inaczej przy pustym raporcie pojawi się pusty prostokąt, który potem zniknie.
- **`Head` w sinku:** każdy osadzony `Reports.vue` ustawia `<Head :title="t.reports.title">`, więc karta przeglądarki sinka pokaże „Raporty”. To akceptowane; sink zachowuje własny `h1` „Reports kitchen sink”.

## Phase 1: ADR-0083 i sink na prawdziwym Reports.vue (C3)

### Overview

Zapisuje decyzje C1–C5 w ADR, przebudowuje fixture na pełne propsy strony i zastępuje ręczne kopie w sinku prawdziwym `Reports.vue`. Zachowanie strony się nie zmienia; sink zaczyna pokazywać dokładnie to, co strona (w tym dzisiejsze błędy C1, które naprawi faza 2).

### Changes Required:

#### 1. ADR

**File**: `context/foundation/adr/0083-reports-view-states-chart-space-and-narrow-share.md`

**Intent**: Jeden ADR w stylu repo („adds to ADR-0070, ADR-0071, ADR-0074 and ADR-0081”), zapisany przed kodem, bo zmienia zakres przyjęty w S-32.

**Contract**: Akapity: (1) wiersz tytułu i przełącznik nad każdym stanem z Budżetem, kartka draftu pod nimi, „12 miesięcy” z przełącznika w drafcie, bez Budżetu sam `h1` — zmienia `rolling-twelve-month-report/plan.md:102` i doprecyzowuje ADR-0081:7; (2) miejsce na wykres: `loadingComponent` z `delay: 0`, `Skeleton` w `ListSurface` o wysokości z `reportChartHeightRem`, wspólna legenda; (3) Udział w karcie < 36rem pod kwotą (druga linia bez porównania, trzecia z porównaniem, „udział X%”), nazwa zawija Badge; (4) sink renderuje `Reports.vue` z fixture (jak ADR-0056:37). Sekcja odrzuconych wariantów z `research.md` Decisions 1–4 i z wyboru w planie (Udział w drugiej linii trzech kolumn, Udział bez zmian przy porównaniu). Zdanie: nie zmienia słownika ani terminu _Avoid_, nie zmienia propsów Inertia, nie dodaje pakietu.

#### 2. Fixture jako pełne propsy strony

**File**: `resources/js/lib/reports-fixtures.ts`

**Intent**: Każdy scenariusz niesie gotowe `ReportsPageProps`, żeby sink mógł je podać stronie bez rozgałęzień.

**Contract**: `ReportScenario = { key: string; caption: string; phone?: boolean; props: ReportsPageProps }` (pole `kind` i luźne `period`/`report`/`trend` znikają). Scenariusze roku/miesiąca/Trendu/last-12: `budgetYear: 2026`, `budgetPhase: 'execution'`; `draft`: `budgetYear: 2027`, `budgetPhase: 'draft'`, `period.kind: 'year'`, `report: null`; `no-budget`: jak odpowiedź `ReportController.php:30-42`. Nowy scenariusz `draft-last-12`: `budgetYear: 2027`, `budgetPhase: 'draft'`, `period` = `lastTwelvePeriod(windowPartial)`, `report: yearReport` (podpis: szkic 2027, okno z poprzedniego roku w realizacji, z przełącznika wraca się do kartki). Wartości liczbowe istniejących scenariuszy bez zmian.

#### 3. Sink renderuje stronę

**File**: `resources/js/pages/dev/ReportsKitchenSink.vue`

**Intent**: Usuwa ręczne kopie (`:150-437`) i osadza prawdziwą stronę w każdej sekcji scenariusza.

**Contract**: W sekcji scenariusza zostaje `h2` z kluczem, podpis i ramka `report-phone-frame` dla `phone`; w środku `<Reports v-bind="scenario.props" />` (import `@/pages/Reports.vue`). Znika własny `defineAsyncComponent`, `periodOptions`, `windowRange`, `trendMonths`, `monthUrl` i importy komponentów raportu. Macierz stanów zostaje; wiersz `empty` dopisuje `draft-last-12`. Kliknięcie przełącznika lub miesiąca w sinku nawiguje na prawdziwe `/reports` (akceptowane w Decisions 2).

#### 4. Test sinka

**File**: `tests/Feature/ReportsKitchenSinkTest.php`

**Intent**: Bez zmian w asercjach; uruchomić, by potwierdzić, że strona sinka nadal się renderuje lokalnie i jest 404 poza `local`.

**Contract**: —

### Success Criteria:

#### Automated Verification:

- Typy przechodzą po przebudowie fixture: `npm run types:check`
- Lint i strażniki: `npm run check` i `npm run check:ui`
- Test sinka i strażnik Raportów przechodzą: `php artisan test --compact tests/Feature/ReportsKitchenSinkTest.php tests/Unit/ReportsHardcodedUiTest.php` (w Sail / obrazie `sail-8.5/app`)
- Istniejące E2E Raportów przechodzą: `npx playwright test tests/e2e/reports.spec.ts`
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- `/dev/ui/reports`: sześć scenariuszy miesiąca ma tytuł „Raport: <miesiąc> 2026” w `h1`, scenariusze Trendu i last-12 mają tytuły jak strona; `phone` i `last-12-phone` nie przewijają się poziomo
- Scenariusze `draft`, `draft-last-12` i `no-budget` w sinku wyglądają dokładnie jak `/reports` w tych stanach na podglądzie worktree (dziś: bez `h1` w `draft` i `no-budget`)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Wiersz tytułu i przełącznik w każdym stanie z Budżetem (C1)

### Overview

Najpierw test E2E na scenariuszach sinka, który nie przechodzi na obecnym `Reports.vue`, potem przestawienie szablonu tak, by stany puste leżały pod wierszem tytułu.

### Changes Required:

#### 1. Nieprzechodzący test E2E

**File**: `tests/e2e/reports-view.spec.ts` (nowy)

**Intent**: Utrwala C1 na prawdziwej stronie osadzonej w sinku, bo baza E2E nie ma draftu ani użytkownika bez Budżetu.

**Contract**: Nagłówek pliku w stylu `reports.spec.ts` (ryzyko, które chroni). Testy na `/dev/ui/reports`, sekcja `[data-scenario="<key>"]`:
- `draft`: `h1` „Raport roku 2027”, `radiogroup`/przełącznik okresu z czterema segmentami, nagłówek „Budżet w przygotowaniu” pod `h1`.
- `draft-last-12`: `h1` „Raport: ostatnie 12 miesięcy”, przełącznik z czterema segmentami, tabela „Wydatki” widoczna.
- `no-budget`: `h1` „Raporty”, nagłówek „Brak budżetu”, brak przełącznika okresu.
Uruchomiony przed zmianą 2 test `draft` i `no-budget` nie przechodzi (brak `h1`), co zapisuje się w raporcie fazy.

#### 2. Szablon strony

**File**: `resources/js/pages/Reports.vue`

**Intent**: Wiersz tytułu staje się pierwszym elementem każdego stanu; kartki pustego stanu idą pod nim.

**Contract**: Kolejność w szablonie: wiersz tytułu (`h1` zawsze; zakres last-12 i `SegmentedControl` tylko przy `budgetYear !== null`), potem `ReportEmptyState kind="no-budget"` przy `budgetYear === null`, potem `ReportEmptyState kind="draft"` przy `budgetPhase !== 'execution' && period.kind !== 'last-12'`, potem dotychczasowe gałęzie (`hasNoCoverage`, Trend, raport). `title` (`:93-118`) bez zmian. `ReportEmptyState` i `IndexEmptyState` bez zmian (kartka zostaje `h2`).

### Success Criteria:

#### Automated Verification:

- Nowy test E2E przechodzi z czerwonego na zielony: `npx playwright test tests/e2e/reports-view.spec.ts`
- Istniejące E2E Raportów przechodzą: `npx playwright test tests/e2e/reports.spec.ts`
- Feature testy Raportów przechodzą na PostgreSQL: `docker run … --entrypoint php sail-8.5/app artisan test --compact tests/Feature/ReportsPageTest.php` (z `DB_DATABASE=testing_reports_view`)
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Podgląd worktree z draftem 2027 (`preview_reports_view`): `/reports`, `?month=3` i `?view=trend` pokazują `h1` widoku, przełącznik i kartkę; „12 miesięcy” pokazuje raport okna; „Rok” z niego wraca do kartki z przełącznikiem (brak drogi w jedną stronę)
- Użytkownik `nobudget@example.com`: `h1` „Raporty” i kartka „Brak budżetu”, bez przełącznika; w 352 px widać, że to Raporty

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: Miejsce na wykres z `reportChartHeightRem` (C2 + C4)

### Overview

Najpierw test jednostkowy funkcji wysokości i test E2E z opóźnionym fragmentem, oba czerwone; potem funkcja w `@/lib/report-chart`, wspólna legenda, dwa placeholdery i `loadingComponent` w obu miejscach importu.

### Changes Required:

#### 1. Nieprzechodzące testy

**File**: `resources/js/lib/report-chart.test.ts`

**Intent**: Ustala formułę wysokości jako kontrakt, zanim powstanie.

**Contract**: `reportChartHeightRem(barCount, compared)`: 6 słupków → 20, 2 → 8, 6 z porównaniem → 29, 0 → 0 (wartości z danych demo 2026, Decisions 3).

**File**: `tests/e2e/reports-view.spec.ts`

**Intent**: Utrwala brak przeskoku tabeli przy fragmencie w drodze.

**Contract**: Dwa testy na `/reports` i `/reports?view=trend` (dane demo): `page.route('**/build/assets/ReportBarChart-*.js')` (Trend: `ReportTrendChart-*.js`) wstrzymuje odpowiedź do zwolnienia; po wczytaniu strony zapisz `boundingBox().y` nagłówka tabeli „Wydatki” (Trend: `[data-trend-table="summary"] .report-trend-scroll`, czyli tabeli miar pod wykresem w tej samej karcie), zwolnij fragment, poczekaj na `report-chart-expense` (`report-trend-chart`) i sprawdź, że `y` różni się o ≤ 1 px. Na obecnym kodzie oba testy nie przechodzą (przesunięcie ~414 i ~314 px).

#### 2. Funkcja wysokości i stałe

**File**: `resources/js/lib/report-chart.ts`

**Intent**: Jedno źródło rozmiarów wykresu słupkowego, wspólne dla wykresu i placeholdera (C4).

**Contract**: `export function reportChartHeightRem(barCount: number, compared: boolean): number` (`barCount × (compared ? 4.5 : 3) + 2`, przy 0 zwraca 0) oraz eksport stałych `REPORT_CHART_NAME_WIDTH_PX = 110` i `REPORT_CHART_VALUE_TICKS = 3`. Stałe z `ReportBarChart.vue:75-79` znikają z komponentu.

#### 3. Wspólna legenda

**File**: `resources/js/components/ReportChartLegend.vue` (nowy)

**Intent**: Legenda wykresu i placeholdera to ten sam znacznik, więc placeholder rezerwuje jej wysokość dokładnie, bez zgadywania.

**Contract**: Props `items: { key: string; color: string; label: string }[]`, `note?: string | null`, `testId: string`; renderuje dzisiejsze `ul.meta.text-muted-foreground.mb-3…` z kwadratem koloru (`ReportBarChart.vue:98-114`, `ReportTrendChart.vue:73-89`). `ReportBarChart` i `ReportTrendChart` używają go z dotychczasowymi `data-testid` (`report-chart-legend-<type>`, `report-trend-chart-legend`).

#### 4. Placeholdery

**File**: `resources/js/components/ReportBarChartPlaceholder.vue` (nowy)

**Intent**: Stan „wykres w drodze” dla słupkowego, o wysokości z tej samej funkcji.

**Contract**: Te same props co `ReportBarChart` (`entries`, `type`, `budgetYear?`, `comparisonYear?`), bo dostaje je od `defineAsyncComponent`. Renderuje tylko przy `reportChartBars(entries, compared).length > 0`: `ListSurface aria-hidden="true"`, `ReportChartLegend` przy porównaniu (te same etykiety lat i kolory), potem `Skeleton` o wysokości `reportChartHeightRem(...)` rem w inline style (jak dziś wykres), `data-testid="report-chart-placeholder-<type>"`.

**File**: `resources/js/components/ReportTrendChartPlaceholder.vue` (nowy)

**Intent**: Stan „wykres w drodze” dla Trendu.

**Contract**: Te same props co `ReportTrendChart` (`months`, `inProgressMonth`); renderuje tylko przy `reportTrendChartBars(months).length > 0`: opakowanie `p-4 aria-hidden`, `ReportChartLegend` (Przychody, Wydatki, notka „w toku”), `Skeleton class="h-64"`, `data-testid="report-trend-chart-placeholder"`.

#### 5. Import asynchroniczny z placeholderem

**File**: `resources/js/pages/Reports.vue`, `resources/js/components/ReportTrendSummary.vue`

**Intent**: Placeholder stoi od pierwszej klatki.

**Contract**: `defineAsyncComponent({ loader: () => import('@/components/ReportBarChart.vue'), loadingComponent: ReportBarChartPlaceholder, delay: 0 })`; analogicznie `ReportTrendChart` z `ReportTrendChartPlaceholder`. Placeholdery importowane statycznie (są lekkie, bez Unovis).

#### 6. Wykres słupkowy na funkcji

**File**: `resources/js/components/ReportBarChart.vue`

**Intent**: Wykres bierze wysokość i stałe z `@/lib/report-chart`, legendę z `ReportChartLegend`.

**Contract**: `chartStyle.height = `${reportChartHeightRem(bars.length, compared)}rem``; `:tick-text-width="REPORT_CHART_NAME_WIDTH_PX"`, `:num-ticks="REPORT_CHART_VALUE_TICKS"`. Wygląd bez zmian.

#### 7. Sink: stan `loading`

**File**: `resources/js/pages/dev/ReportsKitchenSink.vue`

**Intent**: Macierz pokazuje stan „wykres w drodze” zamiast N/A.

**Contract**: Wiersz `loading`: „Sekcja chart-loading: placeholdery wykresów (słupkowy bez i z porównaniem, Trend) o wysokości wykresu, widoczne do doładowania fragmentu.” Nowa sekcja `data-section="chart-loading"` renderuje bezpośrednio `ReportBarChartPlaceholder` (wydatki `yearReport`, wydatki raportu z porównaniem) i `ReportTrendChartPlaceholder` (miesiące scenariusza `trend-in-progress`). Fixture eksportuje potrzebne raporty, jeśli dziś są prywatne.

#### 8. Strażniki

**File**: `tests/Unit/ReportsHardcodedUiTest.php`, `scripts/check-ui-literals.mjs`

**Intent**: Nowe pliki widoku są pod tymi samymi strażnikami.

**Contract**: Dopisać `ReportChartLegend.vue`, `ReportBarChartPlaceholder.vue`, `ReportTrendChartPlaceholder.vue` do obu list.

### Success Criteria:

#### Automated Verification:

- Test jednostkowy przechodzi z czerwonego na zielony: `npm test -- resources/js/lib/report-chart.test.ts`
- Testy E2E przeskoku przechodzą z czerwonego na zielony: `npx playwright test tests/e2e/reports-view.spec.ts`
- Istniejące E2E Raportów (wykresy, legenda porównania, Trend) przechodzą: `npx playwright test tests/e2e/reports.spec.ts`
- Strażniki obejmują nowe pliki: `npm run check:ui` i `php artisan test --compact tests/Unit/ReportsHardcodedUiTest.php`
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- `/reports` w 1440 i 352 px z fragmentem opóźnionym w DevTools: placeholder pulsuje w miejscu wykresu, tabela „Wydatki” nie zjeżdża; z porównaniem legenda stoi od początku
- `/dev/ui/reports` sekcja `chart-loading`: trzy placeholdery o wysokości odpowiadających wykresów w scenariuszach obok; raport bez słupków (`non-positive-expenses`) nie ma ani wykresu, ani placeholdera

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Phase 4: Udział pod kwotą w karcie < 36rem (C5) i reguły

### Overview

Najpierw test E2E na scenariuszach `phone` i `compared-phone`, który nie przechodzi; potem obszary siatki w `app.css`, etykieta „udział” w komórce i zawijanie nazwy; na koniec reguły agenta i listy sinków.

### Changes Required:

#### 1. Nieprzechodzący test E2E

**File**: `tests/e2e/reports-view.spec.ts`

**Intent**: Utrwala C5 w ramce 22rem.

**Contract**: W `[data-scenario="phone"]` i `[data-scenario="compared-phone"]`, tabela „Wydatki”: nazwa „Żywność” i „Stara prenumerata” nie jest ucięta (`scrollWidth <= clientWidth` elementu `.truncate` w `CategoryChip`), komórka udziału wiersza „Żywność” zawiera tekst „udział 66,7%” i leży niżej niż komórka kwoty. Na obecnym kodzie test nie przechodzi.

#### 2. Siatka tabeli

**File**: `resources/css/app.css`

**Intent**: Poniżej 36rem karty Udział nie zabiera nazwie kolumny; od 36rem układ jak dziś.

**Contract**: `.report-grid` (z Udziałem) w karcie < 36rem: dwie kolumny `minmax(0, 1fr) minmax(5.5rem, auto)`, obszary `'name amount' / '. share'`; z porównaniem `'name amount' / 'previous difference' / '. share'`. Wiersz nagłówka bez kolumny Udział w wąskim układzie (jak `.report-head-previous`), wiersz grupy i sumy w tym samym układzie. Klasa etykiety `.report-share-label` (wzorzec `.report-previous-label`): widoczna < 36rem, ukryta od 36rem. `.report-cell-name` w wąskim układzie z `flex-wrap`, żeby Badge zszedł pod nazwę. Od 36rem: bez porównania dzisiejsze `.report-grid` (trzy kolumny w jednej linii), z porównaniem dzisiejsze `.report-grid-compare` z `:1555-1580`. Komentarz nad blokiem (ADR-0083) z liczbami szerokości (~288 px treści, nazwa ~184 px). Wymaga obszarów `report-area-*` także w tabeli bez porównania, więc klasy obszarów nie mogą zależeć od `.report-grid-compare`.

#### 3. Etykieta udziału w komórkach

**File**: `resources/js/components/ReportCategoryRow.vue`, `resources/js/components/ReportGroupRow.vue`, `resources/js/components/ReportEntryTable.vue`, `lang/pl/ui.php`

**Intent**: W wąskim układzie komórka mówi, co to za liczba.

**Contract**: Komórka udziału: `<span class="report-share-label">{{ t.reports.share_label }}</span>` przed `formatShareTenths(...)` w wierszu kategorii, grupy i sumy. Nowy klucz `reports.share_label` => `'udział'` (typ `reports: Record<string, string>` w `types/shell.ts:42` nie wymaga zmiany).

#### 4. Reguły i listy sinków

**File**: `.ai/rules/reports.md`, `context/foundation/design-system.md`, `.ai/rules/js.md`

**Intent**: Następny agent nie powtórzy przeskoku, kopii sinka ani ucinania nazw.

**Contract**: `reports.md`: w akapicie o wykresach — import z `loadingComponent` (placeholder z `Skeleton` o wysokości z `reportChartHeightRem` / `h-64`, `delay: 0`, legenda `ReportChartLegend`); w akapicie o tabeli (`:28`) — Udział w karcie < 36rem pod kwotą z „udział”, `/dev/ui/reports` renderuje `Reports.vue` z pełnych `ReportsPageProps` w fixture (nowy stan = nowy scenariusz, nie nowy znacznik w sinku), wiersz tytułu nad każdym stanem pustym; w akapicie last-12 — zdanie o drafcie z przełącznikiem (ADR-0083) zamiast „the draft state never takes it over” w obecnym brzmieniu. `design-system.md` (`:104-127`, `:177`) i `js.md` („Component sandboxes”): dopisać `/dev/ui/reports`. Potem `php artisan boost:index-rules`.

#### 5. Sink: podpisy scenariuszy telefonu

**File**: `resources/js/lib/reports-fixtures.ts`

**Intent**: Podpisy `phone` i `compared-phone` mówią, co pokazują.

**Contract**: Podpisy dopisują: pełna nazwa, Udział pod kwotą, Badge pod nazwą (bez zmian w danych).

### Success Criteria:

#### Automated Verification:

- Test E2E C5 przechodzi z czerwonego na zielony: `npx playwright test tests/e2e/reports-view.spec.ts`
- Istniejące E2E Raportów przechodzą (porównanie, nagłówki kolumn): `npx playwright test tests/e2e/reports.spec.ts`
- Strażniki i indeks reguł: `npm run check:ui`, `php artisan boost:index-rules` bez zmian po drugim uruchomieniu
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- `/reports` w 352 px: tabela „Wydatki” pokazuje pełne nazwy („Żywność”, „Mieszkanie”, „Transport”), „udział X%” pod kwotą; Przychody bez zmian
- `/dev/ui/reports` `phone` i `compared-phone`: „Stara prenumerata” w całości, Badge pod nazwą; w `compared-phone` trzy linie wiersza (kwota | rok wcześniej i różnica | udział)
- `/reports` w 1440 px (z porównaniem i bez): wiersz w jednej linii, nagłówek „Udział” jak dziś, bez etykiety „udział” w komórkach

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie; potem zrzuty po zmianie do `context/changes/reports-view/screenshots/` w tych samych szerokościach co audyt.

---

## Testing Strategy

### Unit Tests:

- `reportChartHeightRem`: 0, 2 i 6 słupków, z porównaniem i bez (`report-chart.test.ts`).
- Strażnik `ReportsHardcodedUiTest.php` obejmuje trzy nowe komponenty.

### Integration Tests:

- `tests/e2e/reports-view.spec.ts` (nowy): stany C1 na scenariuszach sinka `draft`, `draft-last-12`, `no-budget`; brak przeskoku tabeli na `/reports` i `?view=trend` z wstrzymanym fragmentem; pełne nazwy i „udział X%” w `phone` / `compared-phone`. Każdy test powstaje przed poprawką i zapisuje się jako czerwony w raporcie fazy.
- `tests/e2e/reports.spec.ts` i `tests/Feature/ReportsPageTest.php` bez zmian, jako regresja.

### Manual Testing Steps:

1. Podgląd worktree (`preview_reports_view`, draft 2027, `nobudget@example.com`): przejść C1 w 1440 i 352 px.
2. `/reports` z dławieniem sieci: placeholder bez przeskoku, wykres na jego miejscu.
3. `/dev/ui/reports` w całości: scenariusze wyglądają jak strona, sekcja `chart-loading`, ramki telefonu bez poziomego przewijania.
4. Zrzuty po zmianie obok `audit-*.png`.

## Performance Considerations

Placeholdery są importowane statycznie i nie ładują Unovis, więc ADR-0071 (Unovis poza innymi stronami) zostaje spełniony; `reportChartBars` liczy się dwa razy (placeholder i wykres) na tych samych danych, co jest pomijalne. Sink renderuje ~31 instancji `Reports.vue`, tyle samo wykresów co dziś.

## Migration Notes

Nie dotyczy: bez migracji i danych. Zmiana fixture jest wewnętrzna dla sinka.

## References

- Research i decyzje: `context/changes/reports-view/research.md` (Charges C1–C5, Decisions 1–4)
- Zrzuty i pomiary audytu: `context/changes/reports-view/screenshots/`
- Wzorzec sinka na prawdziwej stronie: `app/Http/Controllers/Dev/AuthKitchenSinkController.php`, ADR-0056:37
- Wąski układ z porównaniem: `resources/css/app.css:1488-1580`, ADR-0074
- Zakres S-32 zmieniany przez C1: `context/archive/2026-11-12-rolling-twelve-month-report/plan.md:102`, ADR-0081:7
- Async wykresu: ADR-0071:18, `.ai/rules/reports.md:20`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0083 i sink na prawdziwym Reports.vue (C3)

#### Automated

- [x] 1.1 Typy przechodzą po przebudowie fixture: `npm run types:check` — b9c22fc
- [x] 1.2 Lint i strażniki: `npm run check` i `npm run check:ui` — b9c22fc
- [x] 1.3 Test sinka i strażnik Raportów przechodzą — b9c22fc
- [x] 1.4 Istniejące E2E Raportów przechodzą — 263b090
- [x] 1.5 Bramka: `composer ci:check` i `vendor/bin/sloppy ci` — b9c22fc

#### Manual

- [x] 1.6 Tytuły scenariuszy sinka zgodne ze stroną, ramki telefonu bez poziomego przewijania — 263b090
- [x] 1.7 Scenariusze draft, draft-last-12 i no-budget w sinku wyglądają jak /reports — 263b090

### Phase 2: Wiersz tytułu i przełącznik w każdym stanie z Budżetem (C1)

#### Automated

- [x] 2.1 Nowy test E2E przechodzi z czerwonego na zielony — 263b090
- [x] 2.2 Istniejące E2E Raportów przechodzą — 263b090
- [x] 2.3 Feature testy Raportów przechodzą na PostgreSQL — 263b090
- [x] 2.4 Bramka: `composer ci:check` i `vendor/bin/sloppy ci` — 263b090

#### Manual

- [x] 2.5 Draft 2027 na podglądzie: h1, przełącznik, kartka, droga z 12 miesięcy i z powrotem — 263b090
- [x] 2.6 Użytkownik bez Budżetu: h1 „Raporty” i kartka, bez przełącznika — 263b090

### Phase 3: Miejsce na wykres z reportChartHeightRem (C2 + C4)

#### Automated

- [x] 3.1 Test jednostkowy przechodzi z czerwonego na zielony — eca833b
- [x] 3.2 Testy E2E przeskoku przechodzą z czerwonego na zielony — eca833b
- [x] 3.3 Istniejące E2E Raportów (wykresy, legenda porównania, Trend) przechodzą — eca833b
- [x] 3.4 Strażniki obejmują nowe pliki — eca833b
- [x] 3.5 Bramka: `composer ci:check` i `vendor/bin/sloppy ci` — eca833b

#### Manual

- [x] 3.6 /reports z opóźnionym fragmentem: placeholder w miejscu wykresu, tabela nie zjeżdża — eca833b
- [x] 3.7 Sekcja chart-loading w sinku i brak placeholdera bez słupków — eca833b

### Phase 4: Udział pod kwotą w karcie < 36rem (C5) i reguły

#### Automated

- [x] 4.1 Test E2E C5 przechodzi z czerwonego na zielony — 15c421a
- [x] 4.2 Istniejące E2E Raportów przechodzą (porównanie, nagłówki kolumn) — 15c421a
- [x] 4.3 Strażniki i indeks reguł — 15c421a
- [x] 4.4 Bramka: `composer ci:check` i `vendor/bin/sloppy ci` — 15c421a

#### Manual

- [x] 4.5 /reports w 352 px: pełne nazwy i „udział X%” pod kwotą — 15c421a
- [x] 4.6 Sink phone i compared-phone: pełna nazwa, Badge pod nazwą, trzy linie z porównaniem — 15c421a
- [x] 4.7 /reports w 1440 px: wiersz w jednej linii jak dziś — 15c421a
