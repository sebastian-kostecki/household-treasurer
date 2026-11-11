# Trend w miesiącach roku w Raportach — plan implementacji

## Overview

S-30 z roadmapy M-3 (`context/foundation/prd-v3.md`, FR-017, nice-to-have): strona „Raporty” dostaje trzeci widok „Trend”, który rozpisuje wybrany rok na 12 miesięcy. Pokazuje przychody, wydatki i wynik każdego miesiąca (wykres i tabela miar) oraz kwoty grup i kategorii w kolejnych miesiącach, domyślnie po grupach, z rozwinięciem do kategorii. Kwoty są co do grosza równe raportowi roku i Wynikowi miesięcy; tabela pokazuje je zaokrąglone do pełnych złotych, żeby 12 miesięcy mieściło się na ekranie laptopa.

## Current State Analysis

- **Odczyt roku już ma miesiące.** `PeriodReport::forYear` woła `MonthCategoryActuals::forMonths($budget, range(1, 12))` (`app/Support/PeriodReport.php:50-53`, `:103`) i dostaje kwotę oraz flagę „ma operacje” per miesiąc i linia budżetu, po czym sumuje miesiące w pętli (`:109-115`). Wymiar miesięcy ginie dopiero w tym miejscu.
- **Jedno zapytanie na cały rok.** `MonthCategoryActuals::forMonths` (`app/Support/MonthCategoryActuals.php:27-60`) grupuje po `(budget_line_id, operation_date)` i składa miesiące w PHP; akcesory `actual($lineId)` i `hasOperations($lineId)` (`:88-101`). Zwroty to ujemne kwoty wydatku, transfery własne i ruchy kapitału są w osobnych tabelach.
- **Wynik miesiąca.** `BudgetMonthResult::forViewedMonth` (`app/Support/BudgetMonthResult.php:18`) robi jedno zapytanie na miesiąc, więc nie nadaje się do liczenia 12 miesięcy (Sloppy: zapytanie w pętli). Jest wzorcem w testach równości (`tests/Feature/PeriodReportTest.php:91`, `:115`).
- **Drzewo listy.** Kolejność, grupy i znacznik archiwum składa `PeriodReport::entriesOfType` i `categoryEntry` (`app/Support/PeriodReport.php:190-275`) na `CategoryListOrder::tree`. Ta logika jest prywatna.
- **Kontroler.** `ReportController::show` (`app/Http/Controllers/ReportController.php:20-57`) zna tylko `?month=1..12`, bez walidacji (inna wartość = rok). `report` jest `null` dla budżetu w przygotowaniu; strona rozpoznaje ten stan po `report === null` (`resources/js/pages/Reports.vue:74`).
- **Zmiana roku.** `BudgetController::selectYear` (`app/Http/Controllers/BudgetController.php:147-158`) dla celu `reports` robi `to_route('reports')` i gubi parametry; `BudgetYearSwitcher.vue:31-33`, `:72-76` wysyła `destination: 'reports'`, gdy użytkownik jest w Raportach.
- **Strona.** `Reports.vue` ma przełącznik `Rok | Miesiąc` (`SegmentedControl`, `:26-46`), `ReportStatPanel`, dwa wykresy `ReportBarChart` (poziome słupki, `VisGroupedBar`) i dwie tabele `ReportEntryTable` na `.report-grid` (`resources/css/app.css:1412-1562`). Grupy startują rozwinięte (`ReportEntryTable.vue:51-64`). Tabele to siatki `div` bez ról tabeli.
- **Szerokość.** W aplikacji nie ma tabeli z 12 kolumnami kwot ani przewijania liczb w poziomie; `overflow-x-auto` wewnątrz `ListSurface flush` występuje tylko w podglądzie importu (`resources/js/pages/OperationsImportPreview.vue:319`). Fixture telefonu w Raportach ma kontrakt „bez poziomego przewijania” (`resources/js/lib/reports-fixtures.ts:446`).
- **Format kwot.** `resources/js/lib/format.ts` nie ma formatu bez groszy. Design system („Kwoty”) i `.ai/rules/js.md` wymagają dwóch miejsc po przecinku „bez wyjątków”.
- **Wykresy.** ADR-0071 obejmuje S-30 i przewiduje „więcej słupków i linii” na shadcn-vue Chart z Unovis 1.7.1. Użyte tokeny: `--success` (przychody), `--chart-1` (wydatki), `--chart-2` (rok wcześniej). `--chart-3..5` są nieużyte i niesprawdzone (`context/foundation/design-system.md:164`).
- **Strażnicy i sandbox.** Pliki Raportów są na listach `tests/Unit/ReportsHardcodedUiTest.php:7-17` i `scripts/check-ui-literals.mjs:117-125`; stany w `/dev/ui/reports` (`resources/js/pages/dev/ReportsKitchenSink.vue`, fixtures w `reports-fixtures.ts`).
- **Testy.** Helpery Pest są funkcjami globalnymi per plik z własnym prefiksem (`reportFixture`, `reportLine`, `bookReportOperation` w `tests/Feature/PeriodReportTest.php:26-77`). Test 2 s to `tests/Feature/Nfr/NfrPerfReportTest.php` (PostgreSQL, grupa `nfr`). E2E: `tests/e2e/reports.spec.ts`.
- **Dane dev.** `demo:seed-previous-year` (S-29) daje drugi rok z operacjami we wszystkich miesiącach, więc trend zakończonego roku da się obejrzeć na devie.
- **ADR.** Najwyższy numer to 0075.

## Desired End State

Na stronie „Raporty” przełącznik ma trzy segmenty: `Rok | Miesiąc | Trend`. Widok „Trend” (`/reports?view=trend`) dla budżetu w wykonaniu pokazuje:

- tytuł „Trend 2026” i kafelki roku (Wynik roku, Przychody, Wydatki, podpis „okres w toku”), bez linii „rok wcześniej”;
- kartę „Miesiące”: wykres z dwoma pionowymi słupkami na miesiąc (Przychody, Wydatki) z legendą i tabelę miar Przychody / Wydatki / Wynik × 12 miesięcy + „Razem”;
- tabelę Wydatki i tabelę Przychody: grupy i kategorie bez grupy × 12 miesięcy + „Razem”, w kolejności listy; grupy startują zwinięte i rozwijają się do kategorii;
- kwoty w pełnych złotych ze stopką „Kwoty w zł, zaokrąglone do pełnych złotych”; „—” w komórce bez operacji; podpis „w toku” pod nazwą miesiąca w toku;
- przy wąskiej karcie tabele przewijają się w poziomie, a kolumna z nazwą stoi w miejscu.

Zmiana roku w sidebarze zostawia użytkownika w trendzie. Budżet w przygotowaniu, brak budżetu i rok bez operacji pokazują te same puste stany co dziś.

Weryfikacja:

- suma miesięcy każdej kategorii i grupy = jej kwota w raporcie roku (`PeriodReport::forYear`), co do grosza;
- przychody, wydatki i wynik każdego miesiąca = `BudgetMonthResult` tego miesiąca;
- wiersze, ich kolejność i znacznik archiwum są takie same jak w raporcie roku bez porównania;
- liczba zapytań odczytu jest stała (2 i 24 kategorie) i nie większa niż w raporcie roku;
- test `nfr`: trend ≤ 2 s przy 10 000 operacji;
- kitchen sink `/dev/ui/reports` ze stanami trendu; `composer ci:check`, `npm run check:ui`, `vendor/bin/sloppy ci`, E2E.

### Key Discoveries:

- Trend to odczyt roku bez ostatniego sumowania: te same dane wejściowe (`forMonths(range(1, 12))`, linie z kategorią, grupy), więc żadnego nowego SQL-a (`app/Support/PeriodReport.php:103-115`).
- `hasOperations($lineId)` per miesiąc (`app/Support/MonthCategoryActuals.php:98-101`) rozróżnia „brak operacji” od „netto 0”, czego potrzebuje reguła „—” kontra „0”.
- `ReportComparisonPeriod::isInProgress($year, $month, $today)` (`app/Support/ReportComparisonPeriod.php:176`) i `CarbonImmutable::today('UTC')` w kontrolerze (`app/Http/Controllers/ReportController.php:43`) dają spójne „w toku” dla roku i miesiąca.
- Przełącznik grupy ma gotowy wzorzec dostępności: `button.budget-category-group-toggle` z `aria-expanded` i `aria-controls` (`resources/js/components/ReportGroupRow.vue:67-81`).
- Wykres ładowany asynchronicznie i dane wykresu w czystej funkcji z testem to ustalony wzorzec (`Reports.vue:14-16`, `resources/js/lib/report-chart.ts`).
- `ReportStatPanel` z `periodKind="year"` daje etykietę „Wynik roku” i nie wymaga zmian (`resources/js/components/ReportStatPanel.vue:28-49`).

## What We're NOT Doing

- Linku z nagłówka miesiąca do raportu tego miesiąca (odrzucone przez użytkownika).
- Kolumny „Średnio / mies.” (odrzucone; dzielnik nie ma jednego znaczenia).
- Roku wcześniej w trendzie: ani w tabelach, ani na wykresie, ani w kafelkach widoku „Trend”. Porównanie zostaje w widokach Rok i Miesiąc (ADR-0074).
- Wykresu wybranej grupy lub kategorii, linii na grupę, sparkline'ów w wierszach.
- Kolorów ani strzałek zmian miesiąc do miesiąca. Kolor ma tylko wiersz „Wynik” (`amountToneClass`).
- Wyszarzania miesiąca w toku i cięcia kwot na dziś.
- Groszy w tabeli trendu oraz przełącznika dokładności. Dokładne kwoty są w widokach Rok i Miesiąc.
- Zmiany dokładności kwot gdziekolwiek poza trendem.
- Okresu „ostatnie 12 miesięcy” (S-32) i przejścia do Operacji (S-31).
- Nowych tokenów koloru, edycji `components/ui/chart/**` i nowej biblioteki wykresów.
- Zmian schematu bazy, snapshotu i `PeriodReport::toArray()`.

## Implementation Approach

Najpierw reguły na papierze (słownik, ADR-0076), potem odczyt z testami równości, bez dotykania strony. Druga faza podaje odczyt stronie i buduje cały widok z tabelami, bo prop i jego typ muszą wejść jednym commitem z testem `assertInertia`. Wykres i E2E są na końcu: tabela miar z fazy 2 jest dostępną formą tych samych liczb, więc wykres tylko ją ilustruje.

Serwer liczy wszystko w minor units; klient tylko zaokrągla do wyświetlenia i rysuje. Numer ADR: 0076. Przed zapisem sprawdź, czy równoległa zmiana go nie zajęła; jeśli tak, weź kolejny wolny i popraw odsyłacze.

## Critical Implementation Details

**Reguły komórek (ustalone z użytkownikiem, zapisywane w ADR-0076):**

- Zawsze 12 kolumn miesięcy, także dla miesięcy przyszłych.
- Komórka kategorii to `null` („—”), gdy kategoria nie ma operacji w tym miesiącu; `0`, gdy operacje były i netto wyszło zero. Komórka grupy to `null`, gdy żaden jej wiersz nie ma operacji w tym miesiącu, inaczej suma wierszy.
- Miara miesiąca: przychody `null` bez operacji przychodowych, wydatki `null` bez operacji wydatkowych, wynik `null` tylko wtedy, gdy miesiąc nie ma żadnej operacji; w pozostałych przypadkach brakującą stronę liczy się jako 0.
- Wiersze jak w raporcie roku (ADR-0070): kategoria z co najmniej jedną operacją w roku, także zarchiwizowana; grupa z co najmniej jednym takim wierszem.
- Miesiąc w toku to miesiąc dzisiejszej daty (UTC), gdy rok budżetu jest rokiem bieżącym; inaczej brak.

**Zaokrąglenie.** Każda komórka zaokrągla własną dokładną kwotę do pełnych złotych, połowa od zera, na liczbach całkowitych; „Razem” zaokrągla dokładną sumę, a nie sumuje zaokrąglonych komórek. Wynik bliższy zeru niż 50 groszy to „0”, nigdy „-0”. Zaokrągla wyłącznie klient przy wyświetlaniu; propsy zostają w minor units.

**Przypięta kolumna.** Komórka nazwy ma `position: sticky` wewnątrz przewijanego kontenera i musi mieć nieprzezroczyste tło równe tłu swojego wiersza (`card`, `muted` dla grupy), inaczej przewijane kwoty prześwitują pod nazwą. Przewijany obszar jest osiągalny z klawiatury (fokus, nazwa dostępna).

## Phase 1: Reguły i odczyt trendu na serwerze

### Overview

Zapisuje reguły w słowniku i ADR-0076 oraz dodaje odczyt `ReportTrend` z testami równości. Po tej fazie odczyt jest udowodniony, a strona wygląda jak dotąd.

### Changes Required:

#### 1. Słownik i ADR

**File**: `context/foundation/glossary.md`, `context/foundation/adr/0076-report-trend-by-month.md`

**Intent**: Nazwać trend jako odczyt i zapisać jego reguły przed kodem (AGENTS.md). ADR zbiera decyzje z planowania wraz z odrzuconymi opcjami: sekcja pod raportem roku i dwa poziomy przełącznika; grosze z przewijaniem na laptopie i format mieszany; wykres na typ i wykres wybranego wiersza; wyszarzenie miesiąca w toku; średnia miesięczna, rok wcześniej w trendzie i link z miesiąca.

**Contract**:
- Wpis **Period Report** dostaje akapit o trendzie: rok budżetu rozpisany na 12 miesięcy kalendarzowych, te same wiersze i kolejność co raport roku, reguły komórek z „Critical Implementation Details”, równość z Month Result i z raportem roku, wyświetlanie w pełnych złotych. Polski tekst UI: „Trend”.
- ADR-0076 w formacie sąsiednich ADR, z odsyłaczami do ADR-0058 (jedna zasada kwot), ADR-0061 (kolumny przez `fr`), ADR-0070, ADR-0071 i ADR-0074. Zapisuje też: wyjątek od „zawsze dwa miejsca” ograniczony do trendu i jego regułę zaokrąglenia; pierwszą tabelę przewijaną w poziomie z przypiętą kolumną nazwy; pionowe słupki miesięcy jako formę wykresu trendu (ADR-0071 opisuje słupki poziome dla raportu okresu); brak nowych tokenów koloru.

#### 2. Wspólne drzewo kategorii raportu

**File**: `app/Support/ReportCategoryTree.php` (nowy, `php artisan make:class`), `app/Support/PeriodReport.php`

**Intent**: Kolejność listy i przynależność do grup mają pochodzić z jednego kodu dla raportu okresu i trendu, a nie z kopii (Sloppy zgłasza zduplikowaną logikę; rozjazd kolejności między widokami byłby błędem).

**Contract**: `ReportCategoryTree::ofType(CategoryType $type, list<Category> $categories, list<CategoryGroup> $groups)` zwraca węzły pierwszego poziomu w kolejności `CategoryListOrder::tree`: kategorię bez grupy albo grupę z listą jej kategorii; grupa bez kategorii jest pomijana. `PeriodReport::entriesOfType` przechodzi na ten krok bez zmiany wyniku `toArray()`; istniejące testy `PeriodReportTest` są dowodem.

#### 3. Odczyt trendu

**File**: `app/Support/ReportTrend.php` (nowy, `php artisan make:class`)

**Intent**: Oddać rok rozpisany na miesiące w minor units, z tych samych danych co raport roku i bez zapytań w pętli.

**Contract**:
- `ReportTrend::forYear(Budget $budget, ?int $inProgressMonth = null): self` i `toArray()`.
- Czyta: `MonthCategoryActuals::forMonths($budget, range(1, 12))`, linie budżetu z kategorią listy, grupy użytkownika. Nie woła `BudgetMonthResult`.
- Kształt, od którego zależą fazy 2 i 3:

```php
/**
 * TrendCategory = array{id: int, name: string, icon: string|null, color: string, is_archived: bool,
 *   amount_minor_units: int,          // suma roku, równa raportowi roku
 *   months: list<int|null>}           // 12 pozycji, indeks 0 = styczeń; null = brak operacji w miesiącu
 * TrendGroup = array{kind: 'group', id: int, name: string, amount_minor_units: int,
 *   months: list<int|null>, categories: list<TrendCategory>}
 * TrendEntry = TrendGroup | (TrendCategory + array{kind: 'category'})
 * TrendMonth = array{month: int, income_minor_units: int|null, expense_minor_units: int|null,
 *   result_minor_units: int|null}
 * TrendShape = array{income_minor_units: int, expense_minor_units: int, result_minor_units: int,
 *   has_operations: bool, in_progress_month: int|null,
 *   months: list<TrendMonth>,          // zawsze 12
 *   income: list<TrendEntry>, expense: list<TrendEntry>}
 */
```

- `id` to id kategorii listy albo grupy, jak w `PeriodReport`. `is_archived` pochodzi z linii budżetu.
- Reguły `null` według „Critical Implementation Details”. `in_progress_month` jest przepisany z argumentu.

#### 4. Testy odczytu

**File**: `tests/Feature/ReportTrendTest.php` (nowy, `php artisan make:test --pest ReportTrendTest`)

**Intent**: Udowodnić równość trendu z raportem roku i z Wynikiem miesięcy oraz reguły komórek; przypadki opisuje „Testing Strategy”.

**Contract**: Przypadki równości tworzą operacje przez `ManageFinancialOperation` (licznik wykonania). Helpery pliku mają własny prefiks, jak w pozostałych plikach Pest.

### Success Criteria:

#### Automated Verification:

- Testy trendu i raportu okresu przechodzą na SQLite: `php artisan test --compact tests/Feature/ReportTrendTest.php tests/Feature/PeriodReportTest.php`
- Te same testy przechodzą na PostgreSQL (worktree): `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature/ReportTrendTest.php tests/Feature/PeriodReportTest.php`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Analiza statyczna i reszta bramki w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- ADR-0076 i akapit o trendzie we wpisie Period Report czyta się zgodnie z decyzjami tego planu (pełne złote, „—” kontra „0”, 12 kolumn, brak kolorów zmian, przewijana tabela)

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Phase 2: Widok „Trend” z tabelami

### Overview

Podaje odczyt stronie pod `?view=trend`, dodaje trzeci segment przełącznika i buduje widok bez wykresu: kafelki, kartę „Miesiące” z tabelą miar oraz tabele Wydatki i Przychody w pełnych złotych, przewijane z przypiętą nazwą. Uzupełnia kitchen sink, strażników, reguły i design system. Po tej fazie trend jest w pełni czytelny z tabel.

### Changes Required:

#### 1. Parametr widoku, propsy i typy

**File**: `app/Http/Controllers/ReportController.php`, `resources/js/types/reports.ts`

**Intent**: Wybrać trend adresem i podać go stronie osobnym propem, z lustrem typów w tym samym commicie (`.ai/rules/inertia-props.md`).

**Contract**:
- `?view=trend` (dokładnie ta wartość) wybiera trend; każda inna wartość `view` jest ignorowana. Przy `view=trend` parametr `month` jest ignorowany.
- W trendzie: `period.kind = 'trend'`, `period.month = null`, `period.is_in_progress` jak dla roku, `report = null`, `trend = ReportTrend::forYear(...)->toArray()` dla budżetu w wykonaniu, a `null` dla budżetu w przygotowaniu. Miesiąc w toku kontroler wyznacza z tego samego `$today`.
- Klucz `trend` jest obecny w każdej odpowiedzi strony: `null` w widokach Rok i Miesiąc oraz bez budżetu. W trendzie nie liczy się `PeriodReport` ani porównania.
- `types/reports.ts`: `ReportPeriod.kind` dostaje `'trend'`; nowe typy `ReportTrendCategory`, `ReportTrendGroupEntry`, `ReportTrendEntry`, `ReportTrendMonth`, `ReportTrendData`; `ReportsPageProps.trend: ReportTrendData | null`.

#### 2. Zmiana roku zostaje na trendzie

**File**: `app/Http/Requests/SelectBudgetYearRequest.php`, `app/Http/Controllers/BudgetController.php`, `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: Po zmianie roku w sidebarze użytkownik ma zostać w trendzie, tak jak dziś zostaje w Raportach.

**Contract**: Nowa dozwolona wartość `destination: 'reports-trend'`, która przekierowuje na `reports` z `view=trend`. Przełącznik roku wysyła ją, gdy bieżący adres to Raporty z `view=trend`; w pozostałych widokach Raportów nadal wysyła `reports`. Przed edycją przełącznika przeczytaj `.ai/rules/js-sidebar.md`.

#### 3. Format pełnych złotych

**File**: `resources/js/lib/format.ts`, `resources/js/lib/format.test.ts`

**Intent**: Jedna funkcja zaokrąglenia do wyświetlania trendu, żeby komórki, sumy i oś wykresu zaokrąglały tak samo.

**Contract**: `formatWholeMoneyAmount(minorUnits: number): string` (bez jednostki, separator tysięcy i znak minus jak w `formatMoneyAmount`) i `formatWholeMoney(minorUnits: number): string` (z „zł”, dla osi wykresu w fazie 3). Reguła według „Zaokrąglenie”: `149 → "1"`, `150 → "2"`, `-150 → "-2"`, `49 → "0"`, `-49 → "0"`, `1234567 → "12 346"`.

#### 4. Siatka trendu

**File**: `resources/css/app.css`

**Intent**: Jeden markup na każdej szerokości: 14 kolumn w jednej linii, które mieszczą się w karcie Raportów obok sidebara przy oknie 1280 px, a węziej przewijają się w poziomie z przypiętą nazwą.

**Contract**:
- Nazwane klasy w `@layer components` obok `.report-grid`: przewijany kontener, siatka trendu (nazwa + 12 miesięcy + „Razem”), komórka nazwy przypięta do lewej z tłem wiersza, komórka nagłówka miesiąca z podpisem „w toku” w stylu `meta`.
- Kolumny rosną wagami `fr` z minimami (ADR-0061); siatka ma minimalną szerokość, poniżej której kontener przewija. Minimum dobrane tak, by przy oknie 1280 px (sidebar rozwinięty) nie było przewijania, a ramka telefonu z kitchen sinka (22rem) przewijała; uzasadnienie w komentarzu przy klasie.
- Wiersze używają istniejących recept `.report-header-row`, `.report-row`, `.report-group-row`, `.report-row-nested`, `.report-total-row` tam, gdzie pasują. Żadnych wartości dowolnych w szablonach (strażnicy UI). Przed edycją przeczytaj `.ai/rules/css.md`.

#### 5. Komponenty trendu

**File**: `resources/js/components/ReportTrendSummary.vue`, `resources/js/components/ReportTrendTable.vue`, `resources/js/components/ReportTrendRow.vue` (nowe)

**Intent**: Tabela miar i tabele typów na wspólnym wierszu, żeby nagłówek miesięcy, „—”, zaokrąglenie i przypięta nazwa działały w jednym miejscu.

**Contract**:
- `ReportTrendRow`: jeden wiersz siatki ze slotem na komórkę nazwy, 12 komórkami miesięcy i komórką „Razem”. Kwoty przez `formatWholeMoneyAmount` w stylu `amount` (`amount-sum` w grupie i sumach), `null` jako „—”. Opcja tonu ze znaku dla wiersza „Wynik” (`amountToneClass` per komórka); wiersz zarchiwizowany cały `muted` bez tonu.
- `ReportTrendTable`: props `type`, `title`, `entries`, sumy typu per miesiąc, suma roku, `inProgressMonth`. `ListSurface flush` z nagłówkiem `section-title`, wierszem nagłówka (Kategoria, „sty”…„gru” z `t.budget.month_chip_N` i pełną nazwą miesiąca jako nazwą dostępną, „Razem”), wierszami grup i kategorii w kolejności z serwera, wierszem „Razem” i stopką o zaokrągleniu. Kategoria pokazuje `CategoryChip` i Badge „zarchiwizowana” jak w `ReportCategoryRow`.
- Grupy startują zwinięte; stan żyje tylko w tej wizycie. Przełącznik grupy jak w `ReportGroupRow` (`aria-expanded`, `aria-controls`, etykiety `group_expand` / `group_collapse`, licznik kategorii).
- `ReportTrendSummary`: `ListSurface flush` z tytułem „Miesiące”, trzema wierszami miar (Przychody, Wydatki, Wynik) na tej samej siatce i stopką o zaokrągleniu; „Razem” wiersza Wynik to wynik roku. Ma miejsce na wykres nad wierszami (wypełnia je faza 3).
- Dostępność: role ARIA tabeli (`table`, `rowgroup`, `row`, `columnheader`, `rowheader`, `cell`), bo wykres jest `aria-hidden`, a 14 kolumn bez powiązania z nagłówkami nie da się odczytać. Przewijany kontener ma `tabindex="0"`, rolę regionu i nazwę dostępną.
- Selektory dla testów jak w istniejących wierszach: `data-report-row="group" | "category"`.

#### 6. Strona i przełącznik

**File**: `resources/js/pages/Reports.vue`

**Intent**: Trzeci segment i gałąź widoku trendu obok dotychczasowych.

**Contract**:
- Przełącznik ma opcje `year`, `month`, `trend`; wybór trendu nawiguje na `reports` z `view=trend` (Wayfinder, `preserveScroll` jak dziś).
- Stan „budżet w przygotowaniu” zależy od `budgetPhase`, nie od `report === null`, bo w trendzie `report` jest zawsze `null`.
- W trendzie: tytuł z `title_trend`, `ReportStatPanel` z sumami roku z `trend` i `period-kind="year"` (bez `comparison`), potem pusty stan „brak operacji”, gdy `!trend.has_operations`, a inaczej `ReportTrendSummary`, `ReportTrendTable` dla wydatków i `ReportTrendTable` dla przychodów. `BudgetMonthPicker` się nie pokazuje.
- Widoki Rok i Miesiąc renderują to samo co dziś.

#### 7. Teksty

**File**: `lang/pl/ui.php`

**Intent**: Nowe teksty w bloku `reports.*`.

**Contract**: klucze dla segmentu „Trend”, tytułu „Trend :year”, tytułu karty „Miesiące”, podpisu „w toku”, stopki „Kwoty w zł, zaokrąglone do pełnych złotych”, nazwy dostępnej przewijanej tabeli (z `:title`) i notki legendy „:month w toku” (używana w fazie 3). `UiTranslationKeysTest` pilnuje zgodności kluczy.

#### 8. Kitchen sink, strażnicy, reguły i design system

**File**: `resources/js/lib/reports-fixtures.ts`, `resources/js/pages/dev/ReportsKitchenSink.vue`, `scripts/check-ui-literals.mjs`, `tests/Unit/ReportsHardcodedUiTest.php`, `.ai/rules/reports.md`, `.ai/rules/js.md`, `context/foundation/design-system.md`

**Intent**: Każdy stan trendu w `/dev/ui/reports`, nowe pliki pod strażnikami, a reguły i design system opisują wyjątek pełnych złotych i przewijaną tabelę.

**Contract**:
- Scenariusze trendu: rok w toku (miesiąc „w toku”, miesiące przyszłe z „—”); rok zakończony; kategoria nieregularna (rachunki w 3 miesiącach, reszta „—”); kategoria z netto 0 („0”); zwrot dający ujemną komórkę; miesiąc z ujemnym wynikiem; zarchiwizowana kategoria w grupie; rok bez operacji; telefon (ramka 22rem, przewijanie z przypiętą nazwą). Scenariusze Rok i Miesiąc dostają `trend: null`.
- Macierz stanów dostaje wiersz o trendzie.
- Każdy nowy plik `ReportTrend*.vue` trafia na listy w `scripts/check-ui-literals.mjs` i `ReportsHardcodedUiTest`.
- `.ai/rules/reports.md`: akapit o trendzie (odczyt `ReportTrend`, równość z raportem roku i Month Result, tabela na siatce trendu z przewijaniem, pełne złote tylko tutaj) i ścieżka `resources/js/lib/report-trend*.ts` w `paths:`; potem `php artisan boost:index-rules`.
- `.ai/rules/js.md`: zdanie „there are no exceptions” przy kwotach w tabelach dostaje jedyny wyjątek, trend w Raportach (`formatWholeMoneyAmount`, ADR-0076).
- `design-system.md`: w „Kwoty” wyjątek pełnych złotych ze stopką; przy tabelach opis tabeli trendu (przewijanie w karcie, przypięta nazwa, kiedy wolno go użyć: tylko tabela z kolumną na każdy miesiąc).

#### 9. Testy strony, zmiany roku i wydajności

**File**: `tests/Feature/ReportsPageTest.php`, `tests/Feature/BudgetTest.php`, `tests/Feature/Nfr/NfrPerfReportTest.php`

**Intent**: Udowodnić kontrakt propsów, przekierowanie po zmianie roku i budżet 2 s dla trendu; przypadki opisuje „Testing Strategy”.

**Contract**: `assertInertia` na `trend` i `period.kind`; `NfrPerfReportTest` mierzy dodatkowo `reports?view=trend` na tych samych 10 000 operacji.

### Success Criteria:

#### Automated Verification:

- Testy jednostkowe frontu przechodzą: `npm run test -- resources/js/lib/format.test.ts`
- Wayfinder, build i typy: `npm run build && npm run types:check`
- Strażnik literałów UI: `npm run check:ui`
- Testy strony, zmiany roku i strażników przechodzą na PostgreSQL: `docker run … --entrypoint php sail-8.5/app artisan test --compact tests/Feature/ReportsPageTest.php tests/Feature/BudgetTest.php tests/Feature/ReportsKitchenSinkTest.php tests/Unit/ReportsHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php`
- Test wydajności trendu przechodzi na PostgreSQL: `docker run … --entrypoint php sail-8.5/app artisan test --group=nfr --configuration=phpunit.nfr.xml --filter=NfrPerfReportTest`
- Pełna bramka w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- Na devie segment „Trend” otwiera `?view=trend` z tytułem „Trend 2026”, kafelkami roku i trzema tabelami; „Razem” wiersza Wydatki i Przychody zgadza się po zaokrągleniu z kafelkami, a wynik zamkniętego miesiąca z raportem tego miesiąca
- Przy oknie 1280 px z rozwiniętym sidebarem tabele trendu mieszczą 12 miesięcy i „Razem” bez przewijania w poziomie
- Na szerokości telefonu tabele przewijają się w poziomie, nazwa stoi w miejscu i kwoty nie prześwitują pod nią; przewijanie działa z klawiatury
- Grupy startują zwinięte i rozwijają się do kategorii; miesiąc w toku ma podpis „w toku”, miesiące bez operacji mają „—”, a kolor ma tylko wiersz Wynik
- Zmiana roku w sidebarze zostawia widok „Trend”; widoki Rok i Miesiąc wyglądają jak przed zmianą; kitchen sink `/dev/ui/reports` pokazuje scenariusze trendu w trybie jasnym i ciemnym

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Phase 3: Wykres i E2E

### Overview

Dodaje wykres miesięcy do karty „Miesiące”, opisuje go w design systemie i dodaje test E2E trendu.

### Changes Required:

#### 1. Dane wykresu

**File**: `resources/js/lib/report-trend-chart.ts` (nowy, z `report-trend-chart.test.ts`)

**Intent**: Dać wykresowi dwie wartości na miesiąc z tych samych `months` co tabela miar.

**Contract**: `reportTrendChartBars(months: ReportTrendMonth[]): ReportTrendChartBar[]`, gdzie słupek ma `month`, `income` i `expense` jako `max(0, kwota ?? 0)`. Zawsze 12 pozycji w kolejności miesięcy; pusta lista, gdy żadna wartość nie jest dodatnia (wtedy zostaje sama tabela).

#### 2. Wykres miesięcy

**File**: `resources/js/components/ReportTrendChart.vue` (nowy), `resources/js/components/ReportTrendSummary.vue`, `resources/js/pages/dev/ReportsKitchenSink.vue`, `scripts/check-ui-literals.mjs`, `tests/Unit/ReportsHardcodedUiTest.php`

**Intent**: Pokazać nad tabelą miar, jak szły przychody i wydatki miesiąc po miesiącu, na wspólnej skali.

**Contract**:
- `VisGroupedBar` w orientacji pionowej: oś X to 12 miesięcy z etykietami `t.budget.month_chip_N`, dwie serie na miesiąc w kolejności Przychody (`--success`), Wydatki (`--chart-1`). Oś wartości przez `formatWholeMoney`, z liniami siatki jak w `ReportBarChart`.
- Legenda z dwiema próbkami („Przychody”, „Wydatki”) stoi zawsze; gdy `inProgressMonth` nie jest `null`, w wierszu legendy stoi notka „:month w toku” z pełną nazwą miesiąca.
- Cały wykres jest `aria-hidden`, ładowany przez `defineAsyncComponent` z `ReportTrendSummary`, z `data-testid="report-trend-chart"` i `report-trend-chart-legend`. Wysokość stała, niezależna od liczby wierszy.
- `ui/chart/**` nie jest edytowane; nie dochodzi żaden token. Nowy plik trafia na listy strażników. Scenariusze kitchen sinka z fazy 2 pokazują wykres.

#### 3. Design system i reguły wykresu

**File**: `context/foundation/design-system.md`, `.ai/rules/reports.md`

**Intent**: Zapisać nową formę wykresu przy istniejących tokenach.

**Contract**: Sekcja „Wykresy” dostaje opis wykresu trendu (pionowe słupki miesięcy, `success` i `chart-1`, notka „w toku” w legendzie); zdanie o `chart-3..5` zmienia się na „nieużywane” bez odsyłacza do S-30. `.ai/rules/reports.md` dopisuje, że wykres trendu ma te same liczby co wiersze Przychody i Wydatki tabeli miar.

#### 4. Test E2E

**File**: `tests/e2e/reports.spec.ts`

**Intent**: Smoke trendu w przeglądarce na danych `demo:seed-household`.

**Contract**: Nowy test: klik „Trend” daje adres z `view=trend` i tytuł „Trend <rok>”; widać kartę „Miesiące” z wykresem (`svg` w `report-trend-chart`) i wierszami Przychody, Wydatki, Wynik; tabela Wydatki ma 12 nagłówków miesięcy i „Razem”; grupa jest zwinięta, a po rozwinięciu pokazuje swoje kategorie; stopka mówi o zaokrągleniu. Drugi test, wzorem istniejącego testu zmiany roku (`tests/e2e/reports.spec.ts:255`): zmiana roku w sidebarze zostawia `view=trend` w adresie.

### Success Criteria:

#### Automated Verification:

- Test danych wykresu przechodzi: `npm run test -- resources/js/lib/report-trend-chart.test.ts`
- Wayfinder, build i typy: `npm run build && npm run types:check`
- Strażnik literałów UI: `npm run check:ui`
- Testy E2E przechodzą: `npx playwright test tests/e2e/reports.spec.ts`
- Pełna bramka w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- Na devie każdy miesiąc ma dwa słupki zgodne z wierszami Przychody i Wydatki tabeli miar, a legenda pokazuje obie serie i notkę o miesiącu w toku
- Wykres jest czytelny w trybie jasnym i ciemnym i mieści się na szerokości telefonu bez przewijania strony
- Rok zakończony (np. 2025 z mocka) nie ma notki „w toku”; rok bez operacji pokazuje pusty stan bez wykresu

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Testing Strategy

### Unit Tests:

- `formatWholeMoneyAmount` / `formatWholeMoney`: `149 → 1`, `150 → 2`, `-150 → -2`, `49` i `-49 → 0` (bez „-0”), `0 → 0`, separator tysięcy przy `1234567 → 12 346`, jednostka w wariancie z „zł”.
- `reportTrendChartBars`: 12 pozycji w kolejności; `null` i kwoty ≤ 0 dają 0; brak dodatnich daje pustą listę.

### Integration Tests:

`tests/Feature/ReportTrendTest.php` (SQLite i PostgreSQL):
- Równość z raportem roku: dla każdej kategorii i grupy `amount_minor_units` i suma niepustych `months` = kwota tego wiersza w `PeriodReport::forYear`; sumy roku trendu = sumy raportu roku; lista wierszy, kolejność, `id` i `is_archived` identyczne z raportem roku bez porównania.
- Równość z Wynikiem miesięcy: dla 12 miesięcy przychody, wydatki i wynik (z `null` liczonym jako 0) = `BudgetMonthResult::forViewedMonth`, z operacjami pierwszego i ostatniego dnia miesiąca.
- Reguły komórek: kategoria nieregularna z rachunkami w marcu, lipcu i październiku ma kwoty w tych trzech miesiącach i `null` w pozostałych; zakup i zwrot w jednym miesiącu daje `0`, nie `null`; sam zwrot daje ujemną komórkę.
- Grupa: komórka = suma komórek jej kategorii; `null`, gdy żadna nie ma operacji w miesiącu; zarchiwizowana kategoria liczy się do grupy.
- Miary miesiąca: miesiąc tylko z przychodem ma `expense: null` i wynik równy przychodowi; miesiąc bez operacji ma trzy `null`.
- Kategoria bez operacji w roku nie ma wiersza; rok bez operacji daje `has_operations: false`, puste listy i 12 miesięcy z `null`.
- Transfery własne, ruchy kapitału i dane innego użytkownika nie wchodzą do trendu.
- `in_progress_month` jest przepisany z argumentu.
- Stała liczba zapytań (2 i 24 kategorie), równa liczbie zapytań `PeriodReport::forYear` bez porównania.

`tests/Feature/ReportsPageTest.php`:
- `?view=trend`: `period.kind = 'trend'`, `report = null`, `trend` z 12 miesiącami i kluczami wiersza; `in_progress_month` dla roku bieżącego (z `travelTo`) i `null` dla innego roku.
- `?view=trend&month=3` to nadal trend; `?view=cokolwiek` to rok z `trend = null`.
- Widoki Rok i Miesiąc mają `trend = null`; budżet w przygotowaniu i brak budżetu mają `trend = null` także z `view=trend`.
- Izolacja: trend pokazuje tylko dane zalogowanego użytkownika.

`tests/Feature/BudgetTest.php`: `destination: 'reports-trend'` przekierowuje na Raporty z `view=trend`; nieznany cel nadal jest odrzucany.

`NfrPerfReportTest` (grupa `nfr`): `reports?view=trend` ≤ 2 s przy 10 000 operacji.

E2E: przypadki z fazy 3.

### Manual Testing Steps:

1. Na devie otwórz „Raporty” i wybierz „Trend”; sprawdź adres, tytuł i kafelki.
2. Porównaj wynik dwóch zamkniętych miesięcy w tabeli miar z raportem tych miesięcy (widok Miesiąc), a „Razem” grupy z jej kwotą w widoku Rok.
3. Rozwiń grupę z kategorią nieregularną i sprawdź „—” w miesiącach bez rachunku.
4. Zmień rok w sidebarze na 2025 (mock) i sprawdź, że widok „Trend” zostaje, a podpis „w toku” znika.
5. Zwęź okno do 1280 px, potem do szerokości telefonu; sprawdź brak przewijania na 1280 px i przypiętą nazwę na telefonie.
6. Sprawdź wykres i tabele w trybie jasnym i ciemnym oraz `/dev/ui/reports`.

## Performance Considerations

Budżet to 2 s dla raportu roku przy 10 000 operacji (`.ai/rules/reports.md`, PRD v3). Trend robi te same zapytania co raport roku bez porównania (kwoty po dniu, linie z kategorią, grupy) i nie liczy porównania, więc jest tańszy niż widok Rok z porównaniem. Składanie w PHP obejmuje wiersze × 12 komórek, czyli kilkaset do kilku tysięcy liczb całkowitych. Żadnego zapytania w pętli po miesiącach ani kategoriach. Wykres jest ładowany asynchronicznie, więc Unovis nie wchodzi do paczki innych stron.

## Migration Notes

Brak migracji schematu i danych. Nowy parametr adresu i nowy cel zmiany roku są addytywne; istniejące adresy Raportów działają bez zmian.

## References

- PRD: `context/foundation/prd-v3.md` (FR-017, Guardrails), `context/foundation/prd-v2.md` (US-02: kryteria trendu)
- Roadmapa: `context/foundation/roadmap.md` (S-30)
- Poprzednie wycinki: `context/archive/2026-11-10-period-report-by-group/plan.md`, `context/archive/2026-11-11-year-over-year-report/plan.md`
- Reguły: `.ai/rules/reports.md`, `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/rules/js-sidebar.md`, `.ai/rules/inertia-props.md`, `.ai/rules/wayfinder.md`, `.ai/rules/sloppy.md`
- ADR: 0058, 0061, 0070, 0071, 0074, 0075
- Odczyty: `app/Support/PeriodReport.php:101`, `app/Support/MonthCategoryActuals.php:27`, `app/Support/BudgetMonthResult.php:18`, `app/Http/Controllers/ReportController.php:20`, `app/Http/Controllers/BudgetController.php:147`
- Wzorce frontu: `resources/js/pages/Reports.vue:26`, `resources/js/components/ReportEntryTable.vue:51`, `resources/js/components/ReportGroupRow.vue:67`, `resources/js/components/ReportBarChart.vue:116`, `resources/js/lib/report-chart.ts`, `resources/css/app.css:1412`, `resources/js/pages/OperationsImportPreview.vue:319`
- Wzorce testów: `tests/Feature/PeriodReportTest.php`, `tests/Feature/ReportsPageTest.php`, `tests/Feature/BudgetTest.php:391`, `tests/Feature/Nfr/NfrPerfReportTest.php`, `tests/e2e/reports.spec.ts`
- Design system: `context/foundation/design-system.md` (sekcje Kwoty, Wykresy, tabele)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Reguły i odczyt trendu na serwerze

#### Automated

- [x] 1.1 Testy trendu i raportu okresu przechodzą na SQLite — ba7cbff
- [x] 1.2 Te same testy przechodzą na PostgreSQL (worktree) — ba7cbff
- [x] 1.3 Formatowanie PHP — ba7cbff
- [x] 1.4 Analiza statyczna i reszta bramki w worktree — ba7cbff
- [x] 1.5 Sloppy bez nowych znalezisk — ba7cbff

#### Manual

- [x] 1.6 ADR-0076 i akapit o trendzie w słowniku czyta się zgodnie z decyzjami tego planu — ba7cbff

### Phase 2: Widok „Trend” z tabelami

#### Automated

- [x] 2.1 Testy jednostkowe frontu przechodzą — d9337c6
- [x] 2.2 Wayfinder, build i typy — d9337c6
- [x] 2.3 Strażnik literałów UI — d9337c6
- [x] 2.4 Testy strony, zmiany roku i strażników przechodzą na PostgreSQL — d9337c6
- [x] 2.5 Test wydajności trendu przechodzi na PostgreSQL — d9337c6
- [x] 2.6 Pełna bramka w worktree — d9337c6
- [x] 2.7 Sloppy bez nowych znalezisk — d9337c6

#### Manual

- [x] 2.8 Segment „Trend” otwiera widok z kafelkami i trzema tabelami, a kwoty zgadzają się z widokami Rok i Miesiąc — d9337c6
- [x] 2.9 Przy oknie 1280 px tabele trendu mieszczą się bez przewijania w poziomie — d9337c6
- [x] 2.10 Na telefonie tabele przewijają się z przypiętą nazwą, także z klawiatury — d9337c6
- [x] 2.11 Grupy startują zwinięte, miesiąc w toku ma podpis, puste komórki mają „—”, kolor ma tylko Wynik — d9337c6
- [x] 2.12 Zmiana roku zostawia „Trend”, Rok i Miesiąc bez zmian, kitchen sink w obu trybach — d9337c6

### Phase 3: Wykres i E2E

#### Automated

- [x] 3.1 Test danych wykresu przechodzi
- [x] 3.2 Wayfinder, build i typy
- [x] 3.3 Strażnik literałów UI
- [x] 3.4 Testy E2E przechodzą
- [x] 3.5 Pełna bramka w worktree
- [x] 3.6 Sloppy bez nowych znalezisk

#### Manual

- [x] 3.7 Każdy miesiąc ma dwa słupki zgodne z tabelą miar, legenda pokazuje serie i miesiąc w toku
- [x] 3.8 Wykres czytelny w trybie jasnym i ciemnym i mieści się na telefonie
- [x] 3.9 Rok zakończony nie ma notki „w toku”, rok bez operacji nie ma wykresu
