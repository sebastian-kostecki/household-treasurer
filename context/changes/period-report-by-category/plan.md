# S-11: Raport okresu według kategorii — plan implementacji

## Overview

Nowa strona „Raporty”. Dla wybranego miesiąca albo całego roku budżetu (rok z globalnego przełącznika) pokazuje przychody, wydatki i wynik oraz kategorie przychodów i wydatków z kwotą i udziałem %, jako tabelę i wykres. Kwoty liczy serwer w `app/Support` i są co do grosza równe Wynikowi miesiąca (`BudgetMonthResult`) oraz wykonaniu kategorii w budżecie rocznym (`categories.execution_minor_units`). Wykres to shadcn-vue Chart na unovis, a decyzję zapisuje ADR-0036 dla całego strumienia A (S-11, S-12, S-13). Roadmapa: S-11; PRD v2: US-01, FR-001, FR-002, FR-003.

## Current State Analysis

- Aplikacja nie ma widoku raportów ani wykresów. Najbliższe odczyty to Podsumowanie miesiąca (karta na stronie „Plan miesiąca”, tylko minione miesiące) i kolumny wykonania na stronie Budżet.
- **Wynik miesiąca:** `BudgetMonthResult::forViewedMonth` (`app/Support/BudgetMonthResult.php:18`) to jedno zapytanie po `budget_id` z `whereYear`/`whereMonth`, grupowane po `type`. Wlicza kategorie zarchiwizowane i jednorazowe. Zwroty to ujemne operacje `expense`, więc same odejmują się od wydatków.
- **Kwoty kategorii w okresie:** `MonthCategoryActuals::forMonths` (`app/Support/MonthCategoryActuals.php:27`) czyta jednym zapytaniem dowolny zakres miesięcy roku budżetu i zwraca kwotę netto każdej kategorii. Nie filtruje po typie.
- **Wykonanie roczne to licznik** `categories.execution_minor_units`. Prowadzi go wyłącznie `ManageFinancialOperation` (`applyEffect`/`revertEffect`, `app/Actions/ManageFinancialOperation.php:212-246`). Fabryki go nie aktualizują (lekcja F6 z S-10: `context/archive/2026-11-03-month-plans-vs-year/reviews/impl-review.md:97-114`), a seed `NfrPerf01Test` wstawia operacje z pominięciem akcji.
- **Sumy na stronie Budżet pomijają zarchiwizowane kategorie,** gdy przełącznik `show_archived` jest wyłączony (`app/Http/Controllers/BudgetController.php:82-88`). Wynik miesiąca zawsze je wlicza.
- **Transfery własne i ruchy kapitału** leżą w osobnych tabelach bez kategorii, więc żaden odczyt z `financial_operations` ich nie widzi. Odsetki z lokaty importują się jako przychód w kategorii (`app/Actions/ImportFinancialOperations.php:221-242`) i do raportu wchodzą, tak samo jak do Wyniku.
- **Typ operacji równa się typowi kategorii** dzięki walidacji (`app/Concerns/ExpenseValidationRules.php:45-47`), ale schemat tego nie wymusza. Operacja zawsze dziedziczy `budget_id` z kategorii, a walidacja nie pozwala na datę spoza roku budżetu.
- **Wybór roku:** `SelectedBudgetYear::resolve` (`app/Support/SelectedBudgetYear.php:10`) czyta sesję `budget_year` i spada na najwcześniejszy rok. Przełącznik w sidebarze wysyła POST `budget.select-year` z `destination` z listy `['budget','month-plan']` (`app/Http/Requests/SelectBudgetYearRequest.php:27`, `BudgetController::selectYear` `:173`). Miesiąc: `BudgetViewedMonth::resolve` (`app/Support/BudgetViewedMonth.php:9`) i `BudgetMonthPicker` z propem `monthUrl` (`resources/js/components/BudgetMonthPicker.vue:13`).
- **UI:** `SegmentedControl` (reka ToggleGroup), `StatTile`, `CategoryChip`, `ListSurface`, `IndexEmptyState`. W `components/ui` nie ma `table` ani `chart`. Tokeny `--chart-1..5` są w `resources/css/app.css` (`:57-61`, `:128-132`, `:172-176`), ale nikt ich nie używa. Pozycje nawigacji to tablica `mainNavItems` w `resources/js/components/AppSidebar.vue:41-72`, a etykiety leżą w `lang/pl/ui.php` (`nav`).
- **Dokumenty są ze sobą sprzeczne:** `context/foundation/design-system.md:38` mówi, że kolor kategorii „nie maluje pasków”, a `AGENTS.md:52` każe brać kolory wykresu z `category-appearance.ts`.
- **Test wydajności:** `tests/Feature/Nfr/NfrPerf01Test.php` (grupa `nfr`, tylko PostgreSQL, `phpunit.nfr.xml`) wkłada 10 000 operacji do jednej kategorii, wszystkie z jednego dnia, i mierzy żądania `microtime` z limitem 2,0 s.

## Desired End State

Użytkownik klika w sidebarze „Raporty” (`GET /reports`) i widzi raport roku wybranego budżetu: kafelki „Przychody”, „Wydatki”, „Wynik roku” z wykresem przychodów i wydatków obok, a pod nimi sekcje „Wydatki” i „Przychody”. Każda sekcja ma wykres pasków kategorii i tabelę Kategoria | Kwota | Udział. Przełącznik „Miesiąc | Rok” i picker miesiąca otwierają `/reports?month=N`, a wtedy kafelek wyniku nazywa się „Wynik”. Zmiana roku w sidebarze na stronie raportu wraca do raportu.

Weryfikacja:
- dla każdego miesiąca suma przychodów, suma wydatków i wynik raportu są równe `BudgetMonthResult::forViewedMonth`;
- w raporcie roku suma kategorii każdego typu jest równa sumie `execution_minor_units` kategorii tego typu, także zarchiwizowanych, gdy operacje przeszły przez `ManageFinancialOperation`;
- raport roku przy 10 000 operacji rozłożonych na kategorie i miesiące otwiera się w ≤ 2 s na PostgreSQL;
- `composer ci:check` przechodzi w obrazie Sail, a `vendor/bin/sloppy ci` na hoście.

### Key Discoveries:

- Kwoty kategorii daje `MonthCategoryActuals::forMonths($budget, range(1, 12))` albo `[$month]`, jednym zapytaniem dla obu okresów. S-12 (trend) użyje tego samego odczytu (`app/Support/MonthCategoryActuals.php:27-59`).
- Wzór kontrolera dla strony z rokiem z sesji i miesiącem z query: `MonthPlanController::show` (`app/Http/Controllers/MonthPlanController.php:37-77`), razem z gałęzią „brak budżetu”.
- Wzór testu propsów: `tests/Feature/MonthPlanPageTest.php` (`travelTo`, `withSession(['budget_year' => 2026])`, `assertInertia`).
- `context/foundation/glossary.md:111-113`: rok to „Wynik roku”, a samo „Wynik” jest dla roku _Avoid_. Budget ma _Avoid_ „Period”, więc w kodzie nie nazywamy niczego `Period` w znaczeniu budżetu.
- Shadcn-vue `chart` (`new-york-v4`) instaluje `ChartContainer`, `ChartTooltipContent`, `ChartLegendContent`, `ChartStyle` i `index.ts`. Sam zależy od `reka-ui` i `@vueuse/core`, które już są, a wykresy rysuje `@unovis/vue` z peer dependency `@unovis/ts` (wersja 1.7.1 z 2026-09-29). SSR nie jest włączony (brak `resources/js/ssr.ts`), więc unovis, który działa na DOM, nie ma tu konfliktu.
- `composer ci:check` uruchamia `npm run check`, `check:ui`, `types:check`, `npm test` i testy PHP.

## What We're NOT Doing

- Trend kategorii w miesiącach (S-12), przejścia z kategorii do Operacji (S-13) ani porównania rok do roku (S-17).
- Własnego zakresu dat, eksportu CSV/PDF ani układu do druku (PRD v2 Non-Goals).
- Grup kategorii ani łączenia kategorii między latami (raport czyta jeden budżet).
- Planu obok wykonania: raport pokazuje tylko wykonanie.
- Pełnej dostępności wykresu dla czytników ekranu: wykres ma `aria-hidden`, a dostępną formą jest tabela (PRD v2 Non-Goals).
- Kitchen sinka, macierzy 7 stanów i bramki zrzutów ekranu. To nowy widok, a `CLAUDE.md` kieruje dopracowanie wyglądu do `/10x-ui` po jego powstaniu.
- Kopii odczytu w TS: serwer liczy wszystko, łącznie z udziałem %.
- Zmian schematu, snapshotu backupu i Dashboardu (`context/archive/2026-09-23-dashboard-hierarchy/spec.md:76` zabrania tam wykresów).
- Indeksu `(budget_id, operation_date)`, chyba że test wydajności z fazy 2 przekroczy limit.
- Kolorowania pasków kolorem kategorii.

## Implementation Approach

Najpierw czysty odczyt na serwerze, `App\Support\CategoryReport`, z regułami i dowodami równości. Potem strona z tabelami i kafelkami, czyli pełny, dostępny odczyt bez wykresu. Na końcu wykres w osobnej fazie, razem z ADR, nową zależnością i aktualizacją dokumentów o kolorach. Dzięki takiej kolejności ryzyko nowej biblioteki nie blokuje odczytu liczb, a test wydajności mierzy zapytania, zanim dojdzie JS.

Reguły raportu (te same dla miesiąca i roku):
- **Kwota kategorii** to suma `amount_minor_units` jej operacji z okresu, netto po zwrotach (`MonthCategoryActuals`).
- **Lista kategorii:** wiersz ma każda kategoria budżetu z co najmniej jedną operacją w okresie (`hasOperations`), także zarchiwizowana z widocznym tekstem „zarchiwizowana” i jednorazowa. Kategoria z kwotą 0 (zakup i zwrot) zostaje na liście z „—” w kolumnie udziału.
- **Sumy:** przychody to suma kategorii przychodów, wydatki to suma kategorii wydatków, wynik = przychody − wydatki. Etykieta wyniku to „Wynik” dla miesiąca i „Wynik roku” dla roku.
- **Udział %:** mianownik to suma kwot kategorii danego typu z kwotą > 0. Kategoria z kwotą ≤ 0 ma udział `null` („—”) i nie ma paska. Udział jest intem w dziesiątych częściach procenta, zaokrąglanym w górę od połowy, każda kategoria osobno (suma może wyjść 99,9% albo 100,1%). Przykład: Żywność 1000 zł i Elektronika −200 zł (sam zwrot) dają mianownik 1000 zł, Żywność 1000 (100,0%), Elektronika `null`, a kafelek „Wydatki” pokazuje 800 zł.
- **Kolejność wierszy:** kwota malejąco, przy równej kwocie kolejność kategorii jak w `Category::ordered` (najpierw aktywne, potem `sort_order`), na końcu `id`.

## Critical Implementation Details

- **Równość z wykonaniem rocznym trzeba udowodnić, nie zakładać.** Test musi tworzyć, edytować i usuwać operacje przez trasy HTTP albo `ManageFinancialOperation`, a nie przez fabryki ani `DB::insert`, bo tylko akcja aktualizuje licznik (lekcja F6 z S-10).
- **Daty i PostgreSQL:** `MonthCategoryActuals` filtruje zakresem dat, a `BudgetMonthResult` przez `whereYear`/`whereMonth`. Testy granic miesiąca i równości z Wynikiem trzeba uruchomić na PostgreSQL (`AGENTS.md`, „Test database”).
- **Udział bez float:** licz `intdiv(amount * 2000 + base, base * 2)` albo równoważnie na intach. Kwoty to `bigint`, a mnożenie przez 2000 mieści się w int64 przy kwotach domowego budżetu. Kafelki i tabela dostają minor units i formatują je przez `formatMoney`, a udział przez helper formatowania procentu z jednym miejscem po przecinku i przecinkiem dziesiętnym.

## Phase 1: Odczyt raportu na serwerze

### Overview

Klasa `CategoryReport` z regułami okresu, udziału, listy i kolejności oraz wpis w `context/foundation/glossary.md`. Testy dowodzą równości z Wynikiem miesiąca i z licznikiem wykonania.

### Changes Required:

#### 1. Słownik

**File**: `context/foundation/glossary.md`

**Intent**: Dodaj termin raportu, żeby kolejne wycinki strumienia A (S-12, S-13, S-17) używały jednej definicji udziału i listy kategorii. To nowy odczyt bez zmiany żadnej reguły, więc nie wymaga ADR przed kodem.

**Contract**: nowy wpis **Category Report** obok Month Result i Year Result. Opis: odczyt wykonania dla jednego miesiąca albo całego roku jednego budżetu (budżet może mieć wiele raportów, raport nie jest budżetem); kwoty netto po zwrotach; kategorie z operacjami w okresie, także zarchiwizowane i jednorazowe; bez Own Transfer i Capital Movement; wynik równy Month Result dla miesiąca i Year Result dla roku; Share = kwota / suma kwot dodatnich kategorii tego typu, `null` dla kwoty ≤ 0. Polskie UI: „Raporty”, „udział”. _Avoid_: Period (jako nazwa budżetu), Bilans, Planned Balance.

#### 2. Odczyt raportu

**File**: `app/Support/CategoryReport.php` (nowa klasa przez `php artisan make:class`)

**Intent**: Policz raport miesiąca albo roku dwoma zapytaniami (kategorie budżetu i `MonthCategoryActuals::forMonths`), bez zapytań w pętli, i oddaj kształt propa w minor units.

**Contract**:
- `public static function forMonth(Budget $budget, int $month): self`, `public static function forYear(Budget $budget): self`.
- `toArray()` zwraca tablicę opisaną przez `@phpstan-type`:
  - `period`: `{kind: 'month'|'year', month: int|null}`;
  - `income` i `expense`: `{total_minor_units: int, share_base_minor_units: int, categories: list<ReportCategoryRow>}`;
  - `result_minor_units: int`;
  - `has_operations: bool`.
- `ReportCategoryRow` = `{id, name, icon, color, is_archived: bool, amount_minor_units: int, share_tenths_percent: int|null}`, a `icon` i `color` pochodzą z `CategoryAppearance::fields`.
- Reguły w „Implementation Approach”: lista, udział, kolejność.

#### 3. Testy odczytu

**File**: `tests/Feature/CategoryReportTest.php` (nowy, PHPUnit jak `tests/Feature/MonthSummaryTest.php`)

**Intent**: Przypnij reguły i dwie równości z wymagań PRD v2.

**Contract** (nazwane testy):
- `test_month_totals_equal_budget_month_result`: każdy z 12 miesięcy, z operacjami na granicach miesiąca, w kategorii zarchiwizowanej i ze zwrotem.
- `test_year_category_totals_equal_annual_execution_counters`: operacje dodawane, edytowane (zmiana kwoty i kategorii), usuwane i zamieniane na zwrot przez trasy `incomes.*`/`expenses.*`, jedna kategoria zarchiwizowana po zaksięgowaniu. Suma kategorii typu = suma `execution_minor_units` kategorii tego typu, a każdy wiersz = licznik swojej kategorii.
- `test_share_uses_positive_categories_as_base`: przykład 1000 / −200, czyli udział 1000 dla Żywności, `null` dla Elektroniki, suma 800 i mianownik 1000.
- `test_share_rounds_half_up_to_tenths_of_percent`: kategorie 1 zł i 2 zł (mianownik 3 zł) dają 333 i 667, a kategoria 1 zł przy mianowniku 2000 zł daje 1 (0,05% → 0,1%).
- `test_lists_only_categories_with_operations_in_period`: kategoria bez operacji w okresie nie ma wiersza; kategoria z zakupem i zwrotem do 0 ma wiersz z udziałem `null`.
- `test_orders_rows_by_amount_then_category_order`.
- `test_own_transfers_and_capital_movements_do_not_affect_report`.
- `test_ignores_other_budgets_and_other_users`.
- `test_period_without_operations_reports_zero_and_no_rows`.

### Success Criteria:

#### Automated Verification:

- Testy odczytu przechodzą na SQLite: `./vendor/bin/sail artisan test --compact tests/Feature/CategoryReportTest.php`
- Testy odczytu przechodzą na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/CategoryReportTest.php`
- Formatowanie: `vendor/bin/pint --dirty --format agent`
- PHPStan/Larastan i pełny gate przechodzą: `composer ci:check` w obrazie Sail
- Sloppy nie zgłasza nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- Wpis Category Report w `context/foundation/glossary.md` jest zgodny z decyzjami (udział wśród dodatnich, lista kategorii z operacjami, „Wynik” / „Wynik roku”)

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Strona „Raporty” z tabelami

### Overview

Trasa, kontroler, wybór okresu, nawigacja, kafelki sum, tabele kategorii, puste stany, typy TS, `assertInertia` i test wydajności. Po tej fazie raport da się w pełni odczytać, jeszcze bez wykresu.

### Changes Required:

#### 1. Trasa i kontroler

**File**: `routes/web.php`, `app/Http/Controllers/ReportController.php` (nowy, `php artisan make:controller`)

**Intent**: Wystaw stronę raportu dla roku z sesji, a okres weź z query: brak `month` oznacza rok, `month=1..12` oznacza miesiąc.

**Contract**:
- `Route::get('reports', [ReportController::class, 'show'])->name('reports')` w grupie `auth`, `verified`.
- `show(Request $request): Response` renderuje `Inertia::render('Reports', [...])`.
- Rok przez `SelectedBudgetYear::resolve`, autoryzacja `view` na budżecie jak w `MonthPlanController::show`.
- `month`: wartość spoza 1..12 albo niecyfrowa daje raport roku. Bez błędu walidacji, łagodnie jak `BudgetViewedMonth`, ale **bez** `BudgetViewedMonth::resolve`, bo ta zamienia złą wartość na domyślny miesiąc. Okres liczy prywatna metoda kontrolera: `ctype_digit` i zakres 1..12 dają miesiąc, wszystko inne (także brak, `0`, `13`, `abc`) daje `null`, czyli raport roku. `BudgetViewedMonth::defaultForYear` służy tylko do propa `defaultMonth`.
- Propsy:
  - `budget: {id, year, phase} | null`;
  - `viewedMonth: int | null` (`null` dla raportu roku);
  - `currentMonth: int | null` (miesiąc dzisiejszej daty, gdy rok budżetu jest bieżący);
  - `defaultMonth: int | null` (`BudgetViewedMonth::defaultForYear`, miesiąc otwierany po przełączeniu na „Miesiąc”);
  - `periodInProgress: bool` (okres zawiera dzisiejszą datę);
  - `report: CategoryReport::toArray() | null` (`null` bez budżetu albo dla budżetu w szkicu).

#### 2. Przełącznik roku wraca na raport

**File**: `app/Http/Requests/SelectBudgetYearRequest.php`, `app/Http/Controllers/BudgetController.php` (`selectYear`), `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: Zmiana roku w sidebarze na stronie raportu ma przekierować z powrotem na raport (roku), a nie na Budżet.

**Contract**:
- `destination` dostaje wartość `'reports'` (`Rule::in(['budget', 'month-plan', 'reports'])`), a `selectYear` przekierowuje na `to_route('reports')`.
- Przełącznik dziś wysyła tylko `{ year }` (`BudgetYearSwitcher.vue:65`), więc z każdej strony wraca na Budżet; `destination: 'month-plan'` wysyła jedynie `DashboardNotices.vue:322`. Nie ma logiki do skopiowania. Dodaj ją tak: `isCurrentSection(reports().url, usePage().url)` z `@/lib/nav-active` (ten sam matcher co `NavMain.vue:30`, ignoruje query, więc pasuje też `/reports?month=3`). Gdy zwraca `true`, POST dostaje `destination: 'reports'`. Na pozostałych stronach payload zostaje bez zmian (Plan miesiąca dalej wraca na Budżet, poza zakresem).
- Test: `BudgetTest` dla `destination=reports` oraz test przełącznika (Vue albo `*.test.ts` dla wydzielonej funkcji wyboru `destination`), że na `/reports?month=3` wysyła `'reports'`, a na `/budget` nie wysyła `destination`.

#### 3. Nawigacja i tłumaczenia

**File**: `resources/js/components/AppSidebar.vue`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: Dodaj pozycję „Raporty” do głównej nawigacji i wszystkie teksty strony.

**Contract**:
- Nowy element `mainNavItems` po „Plan miesiąca” z `href: reports()` (Wayfinder `@/routes`) i ikoną `@lucide/vue` `ChartColumn` (albo `ChartBar`, jeśli tej nazwy nie ma w zainstalowanej wersji).
- Klucz `nav.reports` i sekcja `reports` w `ui.php`: tytuł, etykiety okresu, kafelków („Przychody”, „Wydatki”, „Wynik”, „Wynik roku”), kolumn, tekst „zarchiwizowana”, podpowiedź o udziale przy zwrotach, „okres w toku”, puste stany.
- `UiTranslations` w `resources/js/types/shell.ts` jest ręcznym lustrem `ui.php`: dopisz `nav.reports: string` (`:22-30`) i sekcję `reports: Record<string, string>` obok `month_plan` (`:42`).
- Asercje tłumaczeń w teście strony.

#### 4. Strona i komponenty

**File**: `resources/js/pages/Reports.vue`, `resources/js/components/StatTile.vue`, `resources/js/components/ReportTotals.vue`, `resources/js/components/ReportCategoryTable.vue`, `resources/js/types/reports.ts`, `resources/js/lib/format.ts`, `resources/css/app.css`

**Intent**: Pokaż raport zgodnie z kontraktem OpenShip: tokeny, istniejące komponenty i jeden markup tabeli na grid-areas.

**Contract**:
- Typy TS lustrzane z `CategoryReport::toArray()` i propsami kontrolera.
- **Wiersz tytułu** jak w `Budget.vue`: `h1` „Raporty” i rok.
- **Wybór okresu:** `SegmentedControl` (tone `neutral`) „Miesiąc | Rok”. Komponent obsługuje tylko `v-model` (`SegmentedControl.vue:4-14`, bez linków), więc strona trzyma `period = ref<'month' | 'year'>(viewedMonth === null ? 'year' : 'month')`. Zmiana wywołuje `router.get`: „Miesiąc” na `reports({ query: { month: defaultMonth } }).url`, a „Rok” na `reports().url`. Dla miesiąca `BudgetMonthPicker` z wymaganymi propsami jak w `MonthPlan.vue:539-545`: `:budget-year="budget.year"`, `:viewed-month="viewedMonth"`, `:show-archived="false"`, `:current-month="currentMonth"`, `:month-url="(m) => reports({ query: { month: m } }).url"`.
- **`ReportTotals`:** trzy `StatTile`: Przychody, Wydatki, a Wynik albo Wynik roku z `amountToneClass`. `StatTile.vue` dziś nakłada `amountToneClass` na każdą kwotę, więc „Wydatki” wyszłyby `text-success` wbrew `design-system.md:31`. Dodaj do `StatTile` opcjonalny prop `signed?: boolean` (domyślnie `true`, istniejące strony bez zmian). Gdy `false`, kwota nie ma koloru. „Przychody” i „Wydatki” dostają `:signed="false"`, a wynik zostaje ze znakiem.
- **`ReportCategoryTable`:** sekcja „Wydatki”, potem „Przychody”. Wiersz: `CategoryChip`, tekst „zarchiwizowana” dla zarchiwizowanej (cały wiersz `muted-foreground`), kwota przez `formatMoney` (`tabular-nums`, do prawej), udział przez nowy `formatShareTenths(value: number | null): string` w `format.ts` („12,3%”, „—” dla `null`).
  - Markup: jedna klasa grid-template-areas w `app.css` wewnątrz `ListSurface`, nagłówek `hidden md:grid`, role ARIA `table`/`row`/`columnheader`/`cell`, bo tabela jest dostępną formą wykresu.
  - Pod tabelą wydatków podpowiedź, że udział liczy się wśród kategorii z kwotą dodatnią, widoczna tylko wtedy, gdy któraś kategoria ma udział `null`.
- **Podpis „okres w toku”** przy `periodInProgress`.
- **Puste stany** (`IndexEmptyState`): brak budżetu (link do Budżetu), budżet w szkicu („raport po rozpoczęciu wykonania”) i okres bez operacji.
- Nowe pliki widoku dopisane do `FILES` w `scripts/check-ui-literals.mjs`.

#### 5. Testy strony i wydajności

**File**: `tests/Feature/ReportsPageTest.php` (nowy), `tests/Feature/BudgetTest.php` (select-year), `tests/Feature/Nfr/NfrPerfReportTest.php` (nowy), `resources/js/lib/format.test.ts` (jeśli istnieje, inaczej nowy obok `format.ts`)

**Intent**: Przypnij kontrakt propsów po obu stronach (`AGENTS.md`, „Inertia props contract”), przekierowanie przełącznika roku i budżet 2 s.

**Contract**:
- `ReportsPageTest`:
  - gość jest przekierowany;
  - bez budżetu `report` jest `null`;
  - szkic daje `report` `null`;
  - raport roku: `report.period.kind = 'year'`, sumy, wiersze, `share_tenths_percent`, `is_archived`;
  - `?month=3` daje raport miesiąca z wynikiem równym `BudgetMonthResult`;
  - `?month=13` i `?month=abc` dają raport roku;
  - `periodInProgress` dla bieżącego i minionego okresu;
  - rok z sesji: drugi budżet nie przecieka;
  - tłumaczenia `translations.reports.*` i `translations.nav.reports`.
- `BudgetTest`: `destination=reports` przekierowuje na `reports`.
- `NfrPerfReportTest` (grupa `nfr`, tylko pgsql, jak `NfrPerf01Test`): 10 000 operacji rozłożonych na co najmniej 30 kategorii obu typów i 12 miesięcy, plus transfery własne; `GET reports` i `GET reports?month=6` mieszczą się w ≤ 2,0 s.
- Test `formatShareTenths`: 0, 5, 123, 1000 i `null`.

### Success Criteria:

#### Automated Verification:

- Testy strony i przełącznika roku przechodzą: `./vendor/bin/sail artisan test --compact tests/Feature/ReportsPageTest.php tests/Feature/BudgetTest.php`
- Testy strony przechodzą na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/ReportsPageTest.php`
- Test wydajności raportu przechodzi: `./vendor/bin/sail artisan test --compact --group=nfr --configuration=phpunit.nfr.xml tests/Feature/Nfr/NfrPerfReportTest.php`
- Build generuje trasę Wayfinder `reports`: `npm run build`
- Pełny gate przechodzi (check, check:ui, types:check, npm test, testy PHP): `composer ci:check` w obrazie Sail
- Sloppy nie zgłasza nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- Na danych 2026 z devu „Raporty” w sidebarze otwiera raport roku, a suma wydatków jest równa sumie kolumny „Wykonanie” wydatków na stronie Budżet z włączonym „pokaż zarchiwizowane”. Przy rozbieżności najpierw porównaj `SUM(amount_minor_units)` operacji z `execution_minor_units` rozbieżnej kategorii (Boost `database-query`). `RestoreUserData.php:209` przepisuje licznik ze snapshotu, a operacje wstawia bez przeliczenia (`:313`), więc rozbieżność może pochodzić z danych, nie z raportu.
- Raport miesiąca (np. marzec) pokazuje „Wynik” równy „Wynikowi” tego miesiąca na stronie Budżet
- Zmiana roku w sidebarze na stronie raportu zostaje na raporcie; picker miesiąca i przełącznik „Miesiąc | Rok” działają z klawiatury
- Widok jest czytelny na 390 px (bez poziomego przewijania) i w trybie ciemnym; zarchiwizowane wiersze mają widoczny tekst „zarchiwizowana”

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: Wykres

### Overview

ADR-0036, komponent shadcn-vue Chart i unovis oraz dwa rodzaje wykresów obok tabel. Do tego aktualizacja dokumentów, które dziś przeczą sobie w sprawie kolorów.

### Changes Required:

#### 1. Decyzja

**File**: `context/foundation/adr/0036-report-charts-shadcn-vue-chart-unovis.md` (nowy)

**Intent**: Zapisz wybór biblioteki dla strumienia A, zanim trafi do kodu (`AGENTS.md`, „Charts and reports”).

**Contract**:
- Kontekst: brak wykresów, 37 kategorii, trend w S-12.
- Decyzja: shadcn-vue Chart (CLI do `components/ui/chart`) na `@unovis/vue` i `@unovis/ts`, w przypiętej wersji 1.7.1.
- Każdy wykres ma obok tabelę z tymi samymi liczbami i `aria-hidden`.
- Kolory: przychody `--success`, wydatki `--chart-1`, kolor kategorii tylko w `CategoryChip`.
- Odrzucone: paski CSS bez biblioteki (S-12 potrzebuje wykresu wielu serii), własne SVG, kolor kategorii na paskach (kontrast, tryb ciemny, `design-system.md`), cykliczne `chart-1..5` (powtarzają się przy 37 kategoriach).
- Konsekwencje: nowa zależność i rozmiar bundla strony raportów; wygenerowane klasy w `components/ui/chart` są poza regułą arbitrary values aplikacji.

#### 2. Zależność i komponent

**File**: `package.json`, `package-lock.json`, `resources/js/components/ui/chart/*`

**Intent**: Dodaj komponent przez CLI zgodnie z `components.json`, a unovis w przypiętej wersji.

**Contract**:
- Najpierw sprawdź wersje, bo żadna z nich nie występuje jeszcze w repo: `npm view shadcn-vue@2.9.0 version`, `npm view @unovis/vue@1.7.1 version`, `npm view @unovis/ts@1.7.1 version` oraz to, że rejestr `https://shadcn-vue.com/r/styles/new-york-v4/chart.json` zawiera `chart`. Gdy wersja nie istnieje albo jest młodsza niż dwa tygodnie, weź najnowszą starszą, zapisz ją w ADR-0036 i odnotuj odchylenie w planie.
- `npx shadcn-vue@2.9.0 add chart`.
- `npm install @unovis/vue@1.7.1 @unovis/ts@1.7.1` (npm jako jedyny menedżer).
- Wygenerowanych plików nie edytuj ręcznie poza autofixem `npm run check:fix`. Jeśli `vp check` zgłasza w nich ostrzeżenia, rozwiąż to bez wyłączania reguł dla całego projektu i zapisz sposób w ADR.

#### 3. Wykresy raportu

**File**: `resources/js/components/ReportTotalsChart.vue`, `resources/js/components/ReportCategoryChart.vue`, `resources/js/pages/Reports.vue`

**Intent**: Pokaż te same liczby co tabele jako poziome paski.

**Contract**:
- `ReportTotalsChart`: dwa poziome paski, Przychody (`--success`) i Wydatki (`--chart-1`), obok kafelków.
- `ReportCategoryChart`: jeden wykres na sekcję typu, poziome paski kategorii z `share_tenths_percent !== null` w kolejności z serwera. Wysokość rośnie z liczbą wierszy, oś kwot przez `formatMoney`, tooltip z nazwą, kwotą i udziałem.
- Kolor jednego typu przez `ChartConfig` z odwołaniem do tokenu, bez hexów w komponentach aplikacji.
- Kontener wykresu ma `aria-hidden="true"`. Brak dodatnich kategorii oznacza brak wykresu, a tabela i pusty stan zostają.
- Nowe komponenty trafiają do `scripts/check-ui-literals.mjs`.

#### 4. Dokumenty i reguły

**File**: `AGENTS.md`, `context/foundation/design-system.md`, `.ai/rules/js.md`

**Intent**: Usuń sprzeczność w sprawie kolorów i zostaw regułę następnemu agentowi (strumień A).

**Contract**:
- `AGENTS.md` „Charts and reports”: biblioteka jest już wybrana (ADR-0036); kolory wykresu to tokeny `--success` (przychody) i `--chart-1` (wydatki); kolor kategorii tylko w `CategoryChip`; wykres ma `aria-hidden` i tabelę obok.
- `context/foundation/design-system.md`: wiersz tabeli znaczeń dla wykresów (`chart-1` = wydatki w wykresach raportu) i zdanie przy kolorze kategorii bez zmiany zakazu.
- `.ai/rules/js.md`: krótka reguła „wykresy przez `components/ui/chart`, kolor jednego typu, tabela obok”. Wiersz w `.ai/rules/index.md` nie jest potrzebny, bo glob `resources/js/**/*.vue` już tam jest.

### Success Criteria:

#### Automated Verification:

- Zależności są zainstalowane z lockiem: `npm ci`
- Build przechodzi: `npm run build`
- Pełny gate przechodzi (check bez ostrzeżeń, check:ui, types:check, npm test, testy PHP): `composer ci:check` w obrazie Sail
- Test wydajności raportu nadal przechodzi: `./vendor/bin/sail artisan test --compact --group=nfr --configuration=phpunit.nfr.xml tests/Feature/Nfr/NfrPerfReportTest.php`

#### Manual Verification:

- Wykres kategorii pokazuje te same kategorie, kolejność i kwoty co tabela obok, w trybie jasnym i ciemnym
- Kategoria ze zwrotem (udział „—”) nie ma paska, a tabela ją pokazuje
- Na 390 px wykres mieści się bez poziomego przewijania strony, a tooltip pokazuje kwotę w formacie `formatMoney`
- Raport roku na danych dev z ~1000 operacjami otwiera się bez zauważalnego opóźnienia po dodaniu wykresu

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `formatShareTenths`: zera, małe udziały, 100,0% i `null`.
- Reguły `CategoryReport` testowane w Feature (potrzebna baza): udział, zaokrąglenie, lista i kolejność.

### Integration Tests:

- Równość z `BudgetMonthResult` dla 12 miesięcy oraz równość z licznikiem `execution_minor_units` przez trasy zapisu operacji (dodanie, edycja, usunięcie, zwrot, archiwizacja), na SQLite i PostgreSQL.
- Strona: propsy, okres z query, rok z sesji, szkic i brak budżetu, przekierowanie przełącznika roku.
- Wydajność: 10 000 operacji rozłożonych na kategorie i miesiące, ≤ 2 s.

### Manual Testing Steps:

1. Otwórz „Raporty” na danych dev 2026 i porównaj sumę wydatków z kolumną „Wykonanie” na stronie Budżet z włączonym „pokaż zarchiwizowane”.
2. Przełącz na „Miesiąc”, wybierz marzec i porównaj „Wynik” z paskiem miesiąca na stronie Budżet.
3. Znajdź kategorię z samym zwrotem albo zakupem i zwrotem w miesiącu i sprawdź „—” i podpowiedź pod tabelą.
4. Zmień rok w sidebarze na stronie raportu.
5. Sprawdź 390 px i tryb ciemny.

## Performance Considerations

Raport to dwa zapytania: kategorie budżetu i jedno grupowane zapytanie `MonthCategoryActuals` po `(category_id, operation_date)` w zakresie dat. Przy 10 000 operacji to najwyżej około 10 000 wierszy wyniku, czyli dni × kategorie, hydratowanych w PHP. Jeśli test z fazy 2 przekroczy 2 s, poprawką jest odczyt grupowany tylko po `category_id` dla roku z testem równości z `MonthCategoryActuals`, ewentualnie indeks `(budget_id, operation_date)`. Taką zmianę zapisz w planie jako odchylenie.

## Migration Notes

Bez migracji danych i zmian snapshotu. Wycofanie to wdrożenie poprzedniej wersji. Nowa zależność JS znika razem z nią.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-11)
- PRD: `context/foundation/prd-v2.md` (US-01, FR-001–FR-003, Wymagania jakościowe, Business Logic Changes „Raport okresu”)
- Wzór kontrolera: `app/Http/Controllers/MonthPlanController.php:37-77`
- Wzór testu propsów: `tests/Feature/MonthPlanPageTest.php`
- Wzór testu wydajności: `tests/Feature/Nfr/NfrPerf01Test.php`
- Lekcja o liczniku wykonania: `context/archive/2026-11-03-month-plans-vs-year/reviews/impl-review.md:97-114`
- Kontrakt UI: `context/foundation/design-system.md`, `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/skills/openship-ui-development/SKILL.md`
- Rejestr shadcn-vue chart: `https://shadcn-vue.com/r/styles/new-york-v4/chart.json`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Odczyt raportu na serwerze

#### Automated

- [ ] 1.1 Testy odczytu przechodzą na SQLite
- [ ] 1.2 Testy odczytu przechodzą na PostgreSQL
- [ ] 1.3 Formatowanie Pint
- [ ] 1.4 PHPStan/Larastan i pełny gate przechodzą (composer ci:check)
- [ ] 1.5 Sloppy nie zgłasza nowych znalezisk

#### Manual

- [ ] 1.6 Wpis Category Report w context/foundation/glossary.md jest zgodny z decyzjami

### Phase 2: Strona „Raporty” z tabelami

#### Automated

- [ ] 2.1 Testy strony i przełącznika roku przechodzą
- [ ] 2.2 Testy strony przechodzą na PostgreSQL
- [ ] 2.3 Test wydajności raportu przechodzi
- [ ] 2.4 Build generuje trasę Wayfinder reports
- [ ] 2.5 Pełny gate przechodzi (composer ci:check)
- [ ] 2.6 Sloppy nie zgłasza nowych znalezisk

#### Manual

- [ ] 2.7 Suma wydatków roku równa kolumnie Wykonanie na stronie Budżet (z zarchiwizowanymi)
- [ ] 2.8 Wynik raportu miesiąca równy Wynikowi na stronie Budżet
- [ ] 2.9 Zmiana roku zostaje na raporcie; wybór okresu działa z klawiatury
- [ ] 2.10 Widok czytelny na 390 px i w trybie ciemnym; tekst „zarchiwizowana” widoczny

### Phase 3: Wykres

#### Automated

- [ ] 3.1 Zależności zainstalowane z lockiem (npm ci)
- [ ] 3.2 Build przechodzi
- [ ] 3.3 Pełny gate przechodzi (composer ci:check)
- [ ] 3.4 Test wydajności raportu nadal przechodzi

#### Manual

- [ ] 3.5 Wykres kategorii zgodny z tabelą w trybie jasnym i ciemnym
- [ ] 3.6 Kategoria ze zwrotem bez paska, widoczna w tabeli
- [ ] 3.7 Wykres mieści się na 390 px, tooltip w formacie formatMoney
- [ ] 3.8 Raport roku na danych dev bez zauważalnego opóźnienia po dodaniu wykresu
