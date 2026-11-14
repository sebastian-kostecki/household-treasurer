# Raporty „Lata”: kategorie przez lata — plan implementacji

## Overview

S-34 (roadmapa M-4, MS-01) dodaje piąty segment Raportów „Lata” (`?view=years`): tabelę kategorie × lata Budżetów w realizacji (dziś 2020–2026). Każdy wcześniejszy rok jest liczony do tego samego dnia albo w pełnych latach, z „Zmianą od 2020”, sparkline w wierszu, grupami z sumami, zarchiwizowanymi kategoriami, zakładkami Wydatki | Przychody i panelem statystyk („Średnio rocznie”, „Najszybciej rośnie”). Wszystkie reguły odczytu ustalił F-07 w ADR-0097 i w akapicie „Years” wpisu Period Report w `context/foundation/glossary.md`; ten plan ich nie zmienia, tylko je wdraża. Wzorem układu jest karta `PageReportsYears` (wariant A) w design systemie Atrament.

## Current State Analysis

- `ReportController::show` (`app/Http/Controllers/ReportController.php:22-69`) czyta `view` bez klasy żądania: `last-12` idzie do `lastTwelveMonths()` przed fazą wybranego Budżetu (:49-51), `trend` do `ReportTrend`, każda inna wartość daje rok/miesiąc (test `ReportsPageTest.php:397`). Bez Budżetu wczesny render ma `period.kind = 'year'` (:29-43). Każdy render ustawia `report` i `trend` jawnie.
- Odczyty raportów są per Budżet: `MonthCategoryActuals::forMonths`/`forRange` (`app/Support/MonthCategoryActuals.php:27-86`) to jedno zapytanie `where budget_id = X`, grupowane po `budget_line_id` (i `operation_date`), bez funkcji daty w SQL. `ReportWindowRows` (`app/Support/ReportWindowRows.php:144-181`) łączy kilka Budżetów po `category_id`, ale robi jedno `forRange` na Budżet; przy 7 latach to 7 zapytań w pętli (SL204 tego nie łapie, bo woła helper, nie model, ale reguła `.ai/rules/reports.md` „queries out of loops” tak).
- Wspólny spacer drzewa wierszy: `ReportCategoryTree::entryNodesOfType` (`app/Support/ReportCategoryTree.php:94`) daje kolejność listy (ADR-0069) z grupami.
- Znak zarchiwizowania przez wiele Budżetów: lekcja S-32 (`context/archive/2026-11-12-rolling-twelve-month-report/reviews/impl-review.md` F1) — linia najnowszego Budżetu, a gdy go nie ma, kategoria listy (`ArchiveCategory` archiwizuje tylko linię bieżącego Budżetu).
- Cięcie N − 1 to `$today->startOfDay()->subYearNoOverflow()` (`app/Support/ReportComparisonPeriod.php:51`). Wielokrotne wywołanie z rzędu dałoby 29.02.2028 → 28.02.2024, a ADR-0097:7 wymaga 29.02.2024.
- Liczby udziału w dziesiątych procenta liczy się całkowitoliczbowo, połowa od zera (`PeriodReport::shareTenthsPercent`, `app/Support/PeriodReport.php:316-325`).
- Front: `Reports.vue` ma `SegmentedControl` z czterema okresami (41-67, 139-145), warunek szkicu `budgetPhase !== 'execution' && period.kind !== 'last-12'` (150-153). Najbliższy wzór tabeli: `ReportTrendTable.vue` / `ReportTrendRow.vue` na `.report-trend-grid` z przypiętą komórką `.report-trend-name` i `.report-trend-scroll` (`resources/css/app.css:1744-1792`), grupy zwinięte z `aria-expanded`/`aria-controls`. Zakładki sekcji: `SegmentedControl` z `segmented-control-tabs` (`resources/js/components/OperationsListPanel.vue:201-209`). Formatowanie: `formatWholeMoneyAmount` (`resources/js/lib/format.ts:33-40`), ton zmiany `reportDifferenceTone` + `planChangeToneClass`. `ReportStatPanel` zna tylko `year | month | last-12`.
- Menu roku: `BudgetYearSwitcher.vue:48-61` wysyła `destination: 'reports-trend'` tylko dla `view=trend`; `SelectBudgetYearRequest.php:27` i `BudgetController.php:154-159` znają `budget, month-plan, reports, reports-trend`.
- Kitchen sink `/dev/ui/reports` renderuje prawdziwy `Reports.vue` ze scenariuszy w `resources/js/lib/reports-fixtures.ts` (każdy z pełnymi propsami). Strażnicy: `tests/Unit/ReportsHardcodedUiTest.php`, `scripts/check-ui-literals.mjs`, `tests/Unit/UiTranslationKeysTest.php`, `resources/js/ui-contract.test.ts`.
- Token `--track` istnieje (`app.css:146/228`, `--color-track`), role kolorów „Lat” są już w `context/foundation/design-system.md:154-169` (F-07).

## Desired End State

Na `/reports?view=years` użytkownik widzi tytuł „Raport: lata 2020–2026”, piąty aktywny segment „Lata”, cztery kafelki, zakładki Wydatki | Przychody, przełącznik „Do 14 listopada | Pełne lata” i tabelę kategorii w kolejności listy z grupami, kolumnami lat, „Zmianą od 2020” i sparkline. Liczby spełniają ADR-0097: w „Pełnych latach” kwota zakończonego roku i „Razem” równają się raportowi roku co do grosza, w „Do D” rok bieżący równa się raportowi roku w toku. Zmiana roku w sidebarze zostawia „Lata”, szkic wybranego roku nie przejmuje widoku. Odczyt idzie stałą liczbą zapytań niezależnie od liczby Budżetów i mieści się w 2 s przy 7 Budżetach × 10 000 operacji na PostgreSQL. Każdy stan widoku jest scenariuszem `years-*` w `/dev/ui/reports`, a reguła `.ai/rules/reports.md` i `design-system.md` opisują nowy widok.

### Key Discoveries:

- Widok rozstrzygany przed fazą Budżetu: wzór `lastTwelveMonths()` (`ReportController.php:49-51, 75-92`).
- Jedno zapytanie po wielu Budżetach z cięciem dla każdego roku osobno daje warunkowa suma po zakresach dat (bez funkcji daty, więc SQLite = PostgreSQL); patrz Critical Implementation Details.
- `BudgetLine` ma `unique(budget_id, category_id)` i `$with = ['category']` (`app/Models/BudgetLine.php:50`): jedno `BudgetLine::whereIn('budget_id', …)` daje „—” kontra 0 dla wszystkich lat bez N+1.
- Własne przelewy i ruchy kapitału są w osobnych tabelach, więc suma `financial_operations.amount_minor_units` wyklucza je z konstrukcji, a zwroty (ujemne kwoty) się netują.
- Remis najwyższego roku: karta pogrubia wszystkie lata z maksimum (Czynsz 9 900 w 2025 i 2026, Internet 1 320 w 2020 i 2021).

## What We're NOT Doing

- Link z nazwy kategorii (S-36: nazwa prowadzi do panelu kategorii z zakładką „Lata”); w S-34 nazwa jest zwykłym tekstem, bez linku do Operacji (ADR-0097:11). Stopka bez zdania „Kliknij kategorię…” z karty (to też S-36).
- Ranking, panel kategorii i usunięcie `ReportBarChart` (S-35); zdania reguły o wykresie słupkowym, `ReportBarChartPlaceholder` i „chart is `aria-hidden` with the table as its only accessible form” przepisuje S-35.
- Stan zakładki i zakresu w adresie: oba są lokalne na stronie (decyzja planu 4), po odświeżeniu wraca „Wydatki” i „Do D”.
- Zmiana reguły cięcia z powodu dat arkusza (operacje z ostatniego dnia miesiąca w latach 2020–2025): zostaje ADR-0097:7 (decyzja planu 2); przesunięcie jest ryzykiem danych i krokiem ręcznej kontroli.
- Poprawki danych 2020–2025 (kategorie 42 Chemia i kosmetyki, 44 Internet, 45 Streaming z kwotami w 2020–2021 i „—” w 2022–2025): poza S-34 (decyzja planu 3); przegląd po wdrożeniu daje wejście do osobnej zmiany.
- „Lata” dla Miesiąca i 12 miesięcy, wykres zmiany od 2020, kafelki kategorii (Parked w roadmapie).
- Nowy ADR lub wpis słownika: ADR-0097 i akapit „Years” są kompletne.

## Implementation Approach

Kolejność jak w S-30 i S-32: najpierw odczyt na serwerze z dowodami równości i liczby zapytań, potem widok, na końcu E2E i przegląd na produkcji. Serwer liczy wszystko, co jest liczbą (komórki, „—”/0, zmiana, „Średnio rocznie”, „Najszybciej rośnie”, najwyższe lata) dla obu trybów i obu typów w jednym propsie `years`; klient tylko wybiera tryb i zakładkę i formatuje (reguła: „do not recompute differences in the client”). Odczyt dzieli się na trzy klasy w `app/Support`, żeby żadna nie urosła w god class (Sloppy): zakres lat i cięcia (czysta wartość), czytnik sum linii po wielu Budżetach (jedno zapytanie) i sam raport (wiersze, komórki, statystyki).

## Critical Implementation Details

**Jedno zapytanie z cięciem dla każdego roku.** Czytnik bierze wszystkie Budżety zakresu naraz (`whereIn('budget_id', …)`), grupuje po `budget_line_id` i liczy dwie pary sumy i liczby warunkami `CASE WHEN (operation_date >= ? AND operation_date <= ?) OR (…) THEN …` z jedną parą dat na rok: „cały rok” z parą 01.01–31.12 każdego roku i „w cięciu” z parą 01.01–dzień D (rok bieżący dostaje parę na cały rok, bo nie jest cięty). Suma „całego roku” ma więc te same granice dat co `MonthCategoryActuals::forMonths` w raporcie roku, a równość nie zależy od tego, że każda operacja Budżetu Y ma datę w roku Y (`RestoreUserData` tego nie sprawdza, baza nie ma CHECK). Daty idą jako parametry `toDateString()`, porównanie stringów dat działa tak samo w SQLite i PostgreSQL, więc nie ma `EXTRACT`/`strftime`. Liczba wierszy wyniku to liczba linii, nie dni. Tryb „Pełne lata” czyta sumy całego roku, tryb „Do D” sumy w cięciu.

**Cięcie liczone dla każdego roku osobno od dzisiejszej daty.** Data końca roku Y to `CarbonImmutable::create(Y, D.month, min(D.day, daysInMonth(Y, D.month)))`, nigdy wielokrotne `subYearNoOverflow()`. Sprawdzian: 29.02.2028 → 2027 do 28.02.2027, 2024 do 29.02.2024.

**Procent wzrostu rocznego to jedyne miejsce z `float`.** „Średnio rocznie” i „Najszybciej rośnie” wymagają pierwiastka n-tego stopnia: `float` wolno tylko w tym stosunku (nie w kwocie), wynik zaokrąglany do dziesiątych procenta połową od zera (jak `shareTenthsPercent`, nie „sufit od zera”) i zwracany jako `int`; każde porównanie wzrostów (remis „Najszybciej rośnie”) idzie na tych `int`, nigdy na surowym `float`. „Zmiana od pierwszego roku” zostaje całkowitoliczbowa, jak `shareTenthsPercent`. Hard rule AGENTS.md (pieniądze bez `float`) jest zachowana: kwoty nigdy nie przechodzą przez `float`.

**Pięć segmentów na telefonie.** Przełącznik „Rok | Miesiąc | 12 miesięcy | Trend | Lata” musi się zmieścić w ramce 22rem albo przewijać jak zakładki Operacji (`div.max-w-full self-start overflow-x-auto`), bez zmian w `components/ui`. `SegmentedControl` nie ma osobnego `aria-label` opcji, więc widoczna etykieta „Lata” jest nazwą dostępną (S-32 plan-review F1).

## Faza 1: Odczyt „Lata” na serwerze

### Overview

Nowy odczyt `ReportYears` z obu trybami i statystykami, gałąź `?view=years` w kontrolerze przed fazą Budżetu, props `years` z typem TS w jednym commicie, cel `reports-years` dla zmiany roku, testy równości, cięcia, przypadków brzegowych, liczby zapytań i NFR na PostgreSQL.

### Changes Required:

#### 1. Zakres lat i cięcia

**File**: `app/Support/ReportYearsRange.php` (nowy, `php artisan make:class`)

**Intent**: Czysta wartość: które lata są kolumnami, który jest rokiem bieżącym, jaki jest dzień D i jaki zakres dat liczy każdy rok w każdym trybie (ADR-0097:5, :7, :9).

**Contract**: `ReportYearsRange::resolve(User $user, CarbonImmutable $today): self`. Kolumny to lata Budżetów użytkownika w fazie `BudgetPhase::Execution` z `year <= $today->year`, rosnąco (szkice i lata przyszłe odpadają; rok bez Budżetu w realizacji nie ma kolumny). `currentYear(): ?int` to `$today->year`, gdy ma Budżet w realizacji, inaczej `null` (np. styczeń 2027 ze szkicem 2027: wszystkie lata pełne, brak trybu „Do D”). `cut(): ?array{month:int, day:int}` to dzień D (`null`, gdy `currentYear` jest `null`). `throughOf(int $year, bool $sameDay): CarbonImmutable` daje koniec liczonego zakresu roku: rok bieżący i każdy rok w „Pełnych latach” do 31.12, wcześniejszy rok w „Do D” do dnia D tego roku bez przepełnienia. `budgets(): list<Budget>` w kolejności kolumn. `today` przychodzi z kontrolera (`CarbonImmutable::today('UTC')`).

#### 2. Czytnik sum linii po wielu Budżetach

**File**: `app/Support/YearsLineActuals.php` (nowy)

**Intent**: Jedno zapytanie po `financial_operations` wszystkich Budżetów zakresu, z sumą i liczbą operacji dla całego roku i dla cięcia, tak żeby liczba zapytań nie rosła z liczbą lat (`.ai/rules/reports.md`, ostatnia sekcja).

**Contract**: `YearsLineActuals::read(ReportYearsRange $range): self`; odczyt `total(int $lineId, bool $sameDay): int` i `hasOperations(int $lineId, bool $sameDay): bool`. Zapytanie: `whereIn('budget_id', ids)`, `groupBy('budget_line_id')`, `selectRaw` z `SUM`/`COUNT` w `CASE WHEN` dla obu trybów: „Pełne lata” po parach 01.01–`throughOf($year, false)`, „Do D” po parach 01.01–`throughOf($year, true)` (patrz Critical Implementation Details); żadnej sumy bez granic dat. Brak Budżetów: pusty wynik bez zapytania.

#### 3. Raport „Lata”

**File**: `app/Support/ReportYears.php` (nowy)

**Intent**: Buduje wiersze w kolejności listy z grupami, komórki „—”/0, „Razem”, zmianę, najwyższe lata, „Średnio rocznie” i „Najszybciej rośnie” dla obu trybów i obu typów (ADR-0097:11-17) oraz zwraca kształt propsa `years`.

**Contract**: `ReportYears::for(ReportYearsRange $range): self`, `toArray(): array` o kształcie (PHPDoc `@phpstan-type`, od którego zależy Faza 2):

```php
/**
 * @phpstan-type YearsCells array{cells: list<int|null>, change_tenths: int|null, change_first_year: int|null, max_years: list<int>}
 * @phpstan-type YearsCategory YearsCells&array{kind: 'category', id: int, name: string, icon: string, color: string, is_archived: bool}
 * @phpstan-type YearsGroup YearsCells&array{kind: 'group', id: int, name: string, categories: list<YearsCategory>}
 * @phpstan-type YearsFastest array{id: int, name: string, icon: string, color: string, growth_tenths: int, first_year: int}
 * @phpstan-type YearsSection array{entries: list<YearsCategory|YearsGroup>, total: YearsCells, first_year: int|null, average_yearly_growth_tenths: int|null, fastest_growing: YearsFastest|null}
 * @phpstan-type YearsMode array{final_year: int|null, expense: YearsSection, income: YearsSection}
 * @phpstan-type YearsShape array{columns: list<int>, current_year: int|null, cut: array{month: int, day: int}|null, same_day: YearsMode|null, full: YearsMode}
 */
```

Reguły (każda z ADR-0097; tu tylko to, czego ADR nie nazywa wprost):
- `cells` idą w kolejności `columns`, w minor units; `null` = „—” (kategoria bez Budget Line w tym roku), `0` = linia jest, a netto 0 lub brak operacji w zakresie. Komórka grupy jest `null`, gdy żadna z jej kategorii (wierszy) nie ma linii w tym roku, inaczej sumą. „Razem” (`total`) to suma typu w roku, nigdy `null`.
- Wiersz ma kategoria listy z co najmniej jedną operacją w którymkolwiek zakresie liczonym w danym trybie (więc zbiór wierszy może się różnić między trybami); grupa ma wiersz, gdy ma choć jedną taką kategorię. Kolejność i grupy przez `ReportCategoryTree::entryNodesOfType`, grupy jednego zapytania `CategoryGroup` użytkownika.
- Linie wszystkich Budżetów zakresu czyta `ReportYears` jednym `BudgetLine::whereIn('budget_id', …)` (z `$with` kategoria) i z nich mapuje `budget_line_id` → (rok, `category_id`) dla wyniku `YearsLineActuals`. Wiersz wejściowy dla `entryNodesOfType` ma kształt `ReportRow` z `PeriodReport` (`category`, `line` = linia ostatniej kolumny lub `null`, …), jak w `ReportWindowRows::of` (:253-290), unikalny po `category_id`.
- `is_archived`: linia Budżetu ostatniej kolumny, a gdy jej nie ma, kategoria listy (lekcja S-32 F1) — ta sama reguła co `PeriodReport::categoryEntry` (:267, `line?->isArchived()`, inaczej `category->isArchived()`); użyj jej, nie pisz trzeciej kopii.
- `final_year`: „Do D” — ostatnia kolumna; „Pełne lata” — ostatni zakończony rok (bez `current_year`), `null`, gdy nie ma żadnego. `same_day` jest `null`, gdy `current_year` jest `null`.
- `change_first_year` to pierwsza kolumna z kwotą > 0 (0 i „—” się nie liczą), `change_tenths` = (końcowy − pierwszy) ÷ pierwszy w dziesiątych procenta, zaokrąglenie połową od zera (jak `shareTenthsPercent`), całkowitoliczbowo; `null`, gdy brak kwoty w roku końcowym, pierwszy rok nie istnieje lub jest rokiem końcowym. To samo dla grup i „Razem”.
- `max_years`: lata z największą kwotą wiersza wśród lat liczonych do zmiany (w „Pełnych latach” bez roku w toku), porównanie dokładnych minor units, przy remisie wszystkie; pusta lista, gdy maksimum ≤ 0.
- `first_year` i `average_yearly_growth_tenths` sekcji: start to pierwszy rok „Razem” > 0; wzrost = (koniec ÷ start)^(1 ÷ (rok końcowy − start)) − 1 w dziesiątych procenta; `null`, gdy brak startu, start = rok końcowy albo koniec < 0.
- `fastest_growing`: kategoria (nie grupa) z kwotą > 0 w co najmniej 4 kolumnach liczonych do zmiany i w roku końcowym, z najwyższym wzrostem rocznym od jej pierwszego roku z kwotą; remis (równe `int` dziesiątych procenta po zaokrągleniu) → wcześniejsza na liście; `null`, gdy brak kandydata.

#### 4. Kontroler i props

**File**: `app/Http/Controllers/ReportController.php`, `resources/js/types/reports.ts`, `resources/js/pages/Reports.vue`, `resources/js/lib/reports-fixtures.ts`

**Intent**: `?view=years` renderuje „Lata” przed sprawdzeniem fazy wybranego Budżetu (jak `last-12`); każdy render ma nowy props `years` (PHP i TS w jednym commicie, reguła `inertia-props.md`).

**Contract**: Nowa gałąź obok `last-12` (:49): `budgetYear`/`budgetPhase` wybranego Budżetu (tylko dla sidebara), `period = {kind: 'years', month: null, default_month, is_in_progress: false, window: null}`, `report: null`, `trend: null`, `years: ReportYears::for(ReportYearsRange::resolve($user, $today))->toArray()`. Pozostałe rendery (bez Budżetu, rok/miesiąc/trend, `last-12`) dostają `years: null`; bez Budżetu `period.kind` zostaje `'year'` także dla `view=years` (jak `last-12`). Brak Budżetu w realizacji: `years.columns === []` (pusty stan po stronie widoku). TS: `ReportPeriod.kind` dostaje `'years'`, nowe typy `ReportYearsCells`, `ReportYearsCategory`, `ReportYearsGroup`, `ReportYearsEntry`, `ReportYearsSection`, `ReportYearsMode`, `ReportYearsData` lustrzane do `@phpstan-type`, `ReportsPageProps.years: ReportYearsData | null`. `years` dochodzi do wszystkich trzech `Inertia::render` kontrolera (bez Budżetu :30-42, główny :56-68, `last-12` :79-91). Poszerzenie unii o `'years'` wymaga w tym samym commicie dwóch zmian w `Reports.vue`, inaczej `types:check` padnie: `:period-kind` dla `ReportStatPanel` (:217-219, prop `'year'|'month'|'last-12'`) zawęża też `'years'` (gałąź `report !== null` i tak go nie dostaje), a typ parametru settera `periodKind` (:43) dostaje `'years'` (getter zwraca pełną unię, v-model na `SegmentedControl<T>`). 28 scenariuszy w `reports-fixtures.ts` ma pełne propsy wpisane ręcznie: każdy dostaje `years: null` (scenariusze „Lat” dochodzą w Fazie 2).

#### 5. Zmiana roku zostawia „Lata”

**File**: `app/Http/Requests/SelectBudgetYearRequest.php`, `app/Http/Controllers/BudgetController.php`

**Intent**: Nowy cel `reports-years` (ADR-0097:5: zmiana roku w sidebarze zostawia użytkownika w „Lata”).

**Contract**: `Rule::in([... 'reports-trend', 'reports-years'])`; `match` w `BudgetController` (:154-159): `'reports-years' => to_route('reports', ['view' => 'years'])`. Frontowa część (switcher) jest w Fazie 2.

#### 6. Testy serwera

**File**: `tests/Feature/ReportYearsTest.php` (nowy, Pest), `tests/Feature/ReportYearsRangeTest.php` (nowy, Pest), `tests/Feature/ReportsPageTest.php`, `tests/Feature/BudgetTest.php`, `tests/Feature/Nfr/NfrPerfReportTest.php`

**Intent**: Dowody z ADR-0097 i z ryzyka roadmapy: równość z raportem roku, cięcie dnia, „—”/0, zarchiwizowane i scalone bez gubienia i bez podwójnego liczenia, zmiana i statystyki na wyroczni policzonej ręcznie (test-plan ryzyko #3: nie tylko „raport == inny odczyt”), stała liczba zapytań, NFR.

**Contract**: Przypadki w Testing Strategy. Operacje przez `ManageFinancialOperation` (jak `bookReportOperation`, `tests/Feature/PeriodReportTest.php:67`), żeby wykonanie linii było prawdziwe. Test strony: `assertInertia` z `period.kind = 'years'` i pełnym kształtem `years` bez `->etc()` na wierszu kategorii i grupy (lekcja S-30 F1). NFR: 7 Budżetów × 10 000 operacji, ≤ 2 s (`MeasuresRequestTime`), grupa `nfr`, PostgreSQL.

### Success Criteria:

#### Automated Verification:

- Testy serwera na SQLite przechodzą: `php artisan test --compact tests/Feature/ReportYearsTest.php tests/Feature/ReportYearsRangeTest.php tests/Feature/ReportsPageTest.php tests/Feature/BudgetTest.php`
- Te same testy przechodzą na PostgreSQL (własna baza `testing_report_categories_across_years`, polecenie worktree z AGENTS.md)
- Liczba zapytań odczytu „Lat” jest taka sama dla 2 i 7 Budżetów oraz dla 2 i 24 kategorii (test w `ReportYearsTest.php`)
- NFR „Lata” ≤ 2 s przy 7 Budżetach × 10 000 operacji na PostgreSQL: `artisan test --group=nfr --configuration=phpunit.nfr.xml --filter=NfrPerfReportTest`
- Formatowanie i Sloppy: `vendor/bin/pint --dirty --format agent`, `vendor/bin/sloppy ci` bez nowych znalezisk
- Typy frontu po zmianie propsa: `npm run build && npm run types:check`

#### Manual Verification:

- W lokalnej aplikacji `/reports?view=years` zwraca props `years` (Vue devtools lub odpowiedź Inertia) z kolumnami wszystkich lat w realizacji, a wybrany szkic w sidebarze nie zmienia odpowiedzi

**Implementation Note**: Po zielonej automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Faza 2: Widok „Lata”

### Overview

Piąty segment, tytuł z podtytułem, kafelki, zakładki i przełącznik zakresu (stan lokalny), tabela z grupami, sparkline i zmianą na nowej siatce, klucze języka, zmiana roku w switcherze, scenariusze w kitchen sink, strażnicy oraz przepisanie reguły i design systemu razem z kodem (ADR-0097:29).

### Changes Required:

#### 1. Strona Raportów

**File**: `resources/js/pages/Reports.vue`, `lang/pl/ui.php` (blok `reports`), `tests/e2e/reports-view.spec.ts`

**Intent**: Segment „Lata” jako ostatni (`?view=years` przez Wayfinder `reports({ query: { view: 'years' } })`), tytuł „Raport: lata <pierwszy>–<ostatni>”, widok niezależny od szkicu wybranego roku, pusty stan bez Budżetu w realizacji.

**Contract**: Unia rodzajów w setterze `periodKind` i `periodOptions` z pięcioma pozycjami (`period_years` = „Lata”). Warunek szkicu pomija `'years'` jak `'last-12'`. Gałąź `period.kind === 'years' && years !== null`: przy `years.columns.length === 0` `IndexEmptyState` „Brak budżetu w realizacji” (ten sam tekst co ADR-0097:5), inaczej komponent widoku `ReportYearsView`. Tytuł `title_years` z `:from` i `:to`. Pięć segmentów mieści się w 22rem albo przewija (Critical Implementation Details). `tests/e2e/reports-view.spec.ts` liczy segmenty: `toHaveCount(4)` (:34, :55) → 5, tytuł testu (:21) „z czterema segmentami” → „z pięcioma segmentami”.

#### 2. Komponenty widoku „Lata”

**File**: `resources/js/components/ReportYearsView.vue`, `ReportYearsStatPanel.vue`, `ReportYearsTable.vue`, `ReportYearsRow.vue`, `ReportYearsSpark.vue` (nowe)

**Intent**: Układ karty `PageReportsYears`: podtytuł, `StatPanel` z czterema kafelkami, pasek z zakładkami „Wydatki | Przychody” (`SegmentedControl` z `segmented-control-tabs`) i zakresem „Do 14 listopada | Pełne lata” (`SegmentedControl` z `tone="neutral"`, ukryty, gdy `same_day === null`), tabela „Wydatki według kategorii” / „Przychody według kategorii”. Stan zakładki i trybu jest lokalnym `ref` w `ReportYearsView` (decyzja planu 4); domyślnie „Wydatki” i „Do D” (albo „Pełne lata”, gdy `same_day === null`).

**Contract**:
- `ReportYearsView` props `years: ReportYearsData`; wybiera `mode = same_day ?? full` i `section = mode[type]`, niczego nie przelicza.
- Podtytuł: „Do D” — „Każdy rok liczony do 14 listopada, żeby rok 2026 w toku dało się porównać z poprzednimi.”; „Pełne lata” — „Zakończone lata liczone w całości; 2026 w toku nie wchodzi do zmiany.”; bez roku bieżącego — „Lata liczone w całości.”
- Kafelki (decyzja planu 1, kafelki to końce zmiany): 1) „Wydatki <final_year>” (w „Do D”: „Wydatki 2026 do 14.11”), kwota `total.cells` roku końcowego, podpis „okres w toku” gdy rok końcowy = `current_year`, inaczej „ostatni pełny rok”; 2) „Wydatki <first_year>” (w „Do D” z „do 14.11”), podpis „pierwszy rok w aplikacji”, gdy `first_year` to pierwsza kolumna, inaczej „pierwszy rok z wydatkami”; 3) „Średnio rocznie” z `average_yearly_growth_tenths`, podpis „wzrost wydatków <first_year>–<final_year>” (w „Do D” 2020–2026, w „Pełnych latach” 2020–2025) albo „spadek wydatków …” wg znaku, ton `reportDifferenceTone` + `planChangeToneClass`; 4) „Najszybciej rośnie” z chipem kategorii i „+25,5% rocznie od 2020”. Brak wartości: „—”. Na zakładce Przychody „Przychody…”, „przychodów”, ton odwrócony. Kwoty kafelków przez `formatMoney` (złote z groszami, ADR-0097:19).
- `ReportYearsTable` na wzór `ReportTrendTable`: `ListSurface flush`, region przewijany `.report-trend-scroll` (`role="region"`, `tabindex="0"`, nazwa), role tabeli ARIA, grupy zwijane z `aria-expanded`/`aria-controls` i liczbą kategorii, wiersz „Razem”, legenda w nagłówku panelu „pogrubiony rok = najwyższy w wierszu”, stopka „Kwoty w zł, zaokrąglone do pełnych złotych · „—” = kategoria bez pozycji w budżecie tego roku”, sekcja bez wierszy: tekst `section_empty`.
- `ReportYearsRow` (`variant: 'header' | 'row' | 'group' | 'total'`): nazwa (`CategoryChip`, zwykły tekst, `Badge` „zarchiwizowana” i wyciszenie), komórka na kolumnę (`formatWholeMoneyAmount`, `null` → „—”, `font-semibold` dla `max_years`), kolumna zmiany (`formatYearsChange`, dopisek „od 2022” przy `change_first_year` różnym od pierwszej kolumny, ton wg typu, wiersz zarchiwizowany wyciszony) i komórka sparkline. Nagłówek: lata, rok bieżący w `foreground` z dopiskiem „w toku” w „Pełnych latach”, nagłówek zmiany „Zmiana od 2020” albo „Zmiana 2020–2025”.
- `ReportYearsSpark`: inline SVG lub CSS, `aria-hidden`, słupek na kolumnę skalowany do maksimum wiersza, wypełnienie `chart-1` (przychód `success`), rok bieżący `foreground`, rok „—” kreska 2 px w `track`, kwota ujemna jako słupek zerowy; tylko klasy tokenów (`fill-chart-1`, `bg-track` itp.), bez hex i `opacity-*`.

#### 3. Logika czysta

**File**: `resources/js/lib/report-years.ts`, `resources/js/lib/report-years.test.ts` (nowe)

**Intent**: Formatowanie i dane znaczników bez logiki w szablonach, z testem Vitest (reguła `frontend-toolchain.md`).

**Contract**: `reportYearsCutLabel(cut, monthGenitive)` → „Do 14 listopada” / „Do 29 lutego”; `reportYearsCutShort(cut)` → „14.11”; `formatYearsChange(tenths | null)` → „+19,4%”, „−8,3%” (U+2212), „—”; `reportYearsSparkBars(cells, columns, currentYear)` → lista `{year, heightRatio, isEmpty, isCurrent}` (`null` → `isEmpty`, ujemne i 0 → wysokość 0, skala do maksimum wiersza, wszystko ≤ 0 → same zera).

#### 4. Siatka CSS

**File**: `resources/css/app.css`

**Intent**: Nazwana siatka „Lat” obok `.report-trend-grid` (reguła `css.md`, bez dowolnych wartości w szablonach).

**Contract**: `.report-years-grid`: kolumna nazwy, `repeat(var(--report-years-count), …)` kolumn lat, kolumna zmiany i sparkline; liczbę lat ustawia `ReportYearsTable` przez `:style="{ '--report-years-count': columns.length }"` (bez literałów px/rem w szablonie, więc `check:ui` i `ReportsHardcodedUiTest` przechodzą), bo liczba kolumn rośnie co rok. `min-width` taka, żeby przy 1280 px z rozwiniętym sidebarem 7 lat mieściło się bez przewijania. Reuse `.report-trend-scroll` i przypiętej `.report-trend-name` (z poprawką wcięcia z S-30 F4) bez zmiany ich nazw: przemianowanie dotknęłoby `ReportTrendRow.vue`, `ReportTrendSummary.vue`, `ReportTrendTable.vue` i selektorów `tests/e2e/reports-view.spec.ts`. Klasy sparkline (`.report-years-spark`).

#### 5. Zmiana roku w sidebarze

**File**: `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: Z „Lat” zmiana roku wysyła `destination: 'reports-years'`.

**Contract**: `staysOnTrend` uogólnione do odczytu `view` (`trend` → `reports-trend`, `years` → `reports-years`, inaczej `reports`).

#### 6. Kitchen sink i fiksury

**File**: `resources/js/lib/reports-fixtures.ts`, `resources/js/pages/dev/ReportsKitchenSink.vue`

**Intent**: Każdy stan „Lat” jako scenariusz prawdziwego `Reports.vue` (reguła `reports.md`), z liczbami z karty (Razem 35 980 → 68 240, Wakacje +25,5%, Meble „od 2022”, Kino zarchiwizowana, grupa Dom).

**Contract**: Builder `buildYears(...)` i scenariusze: `years-same-day` (domyślny, dane karty w `same_day`, przykład ADR 42 000 → 74 000, +76,2% / +12,0% w `full`; tryb „Pełne lata” i zakładkę Przychody ogląda się kliknięciem w tym scenariuszu, bo stan jest lokalny), `years-no-current-year` (styczeń 2027 ze szkicem: brak przełącznika), `years-only-current` (jeden rok w realizacji: zmiana i statystyki „—”), `years-leap` („Do 29 lutego”), `years-no-coverage` (`columns: []`), `years-no-operations` (sekcja pusta), `years-draft-selected` (wybrany szkic, widok „Lata” zostaje), `years-phone` (`phone: true`). `stateMatrix` dostaje wpisy „Lata” w default, empty, hover, focus-visible i disabled (ukryty przełącznik), a opis `last-12-phone` (`ReportsKitchenSink.vue:33`, „cztery segmenty w 22rem”) mówi „pięć segmentów”.

#### 7. Strażnicy

**File**: `tests/Unit/ReportsHardcodedUiTest.php`, `scripts/check-ui-literals.mjs`, `resources/js/ui-contract.test.ts`

**Intent**: Nowe komponenty pod tymi samymi strażnikami co Trend.

**Contract**: Pięć nowych plików `ReportYears*.vue` na listach `REPORTS_VIEW_FILES` i `FILES`; `ReportYearsRow.vue` na `UNITLESS_TABLE_ROW_FILES` (tabela bez „zł” w wierszach, ADR-0058). Klucze języka przechodzą `UiTranslationKeysTest`.

#### 8. Reguła i design system

**File**: `.ai/rules/reports.md`, `.ai/rules/index.md` (przez `php artisan boost:index-rules`), `context/foundation/design-system.md`

**Intent**: Przepisać zdania, które z S-34 przestają być prawdą (ADR-0097:29, część S-34), i opisać widok.

**Contract**: `reports.md`: nowa sekcja „## The Years view is `ReportYears` in whole zloty, independent of the selected year” (props `years`, liczby tylko z serwera, oba tryby w jednym propsie, stan lokalny, `reports-years`, scenariusze `years-*`, stała liczba zapytań; sparkline to znacznik CSS/SVG z tokenów, `aria-hidden`, bez `defineAsyncComponent` i bez placeholdera, bo powtarza liczby wiersza — wyjątek od pierwszej sekcji reguły wg ADR-0097 „Colour”; nazwa kategorii to zwykły tekst do S-36); „four segments” / „(Rok | Miesiąc | 12 miesięcy | Trend)” → pięć segmentów; „Amounts are whole zloty and only here” → Trend i „Lata”; glob `resources/js/lib/report-years*.ts` w `paths:`; zdania o wykresie słupkowym zostają dla S-35. `design-system.md:20`: przewijana tabela dozwolona dla Trendu (kolumny miesięcy) i „Lat” (kolumny lat), opis `.report-years-grid`, sparkline i stopki „Lat”; sekcja kitchen sink wymienia scenariusze `years-*`.

#### 9. Testy widoku

**File**: `tests/Feature/ReportsKitchenSinkTest.php`

**Intent**: Sink renderuje się z nowymi scenariuszami; zachowanie widoku (przełączenia, zmiana roku) sprawdza E2E w Fazie 3, a props — testy Fazy 1.

**Contract**: Bez nowych testów PHP w tej fazie; `ReportsKitchenSinkTest` zostaje zielony.

### Success Criteria:

#### Automated Verification:

- Testy Vitest „Lat” przechodzą: `npm run test -- resources/js/lib/report-years.test.ts`
- Budowanie i typy: `npm run build && npm run types:check`
- Strażnicy UI: `npm run check:ui` oraz `php artisan test --compact tests/Unit/ReportsHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php tests/Feature/ReportsKitchenSinkTest.php`
- Kontrakt UI: `npm run test -- resources/js/ui-contract.test.ts`
- Indeks reguł zaktualizowany: `php artisan boost:index-rules` bez różnic po ponownym uruchomieniu
- Pełna bramka na PostgreSQL: `composer ci:check` (polecenie worktree z AGENTS.md) i `vendor/bin/sloppy ci`

#### Manual Verification:

- `/dev/ui/reports` w jasnym i ciemnym motywie: wszystkie scenariusze `years-*` zgodne z kartą `PageReportsYears` (kafelki, pasek, tabela, pogrubione lata, „od 2022”, Kino wyciszone, grupa Dom z sumami, sparkline z rokiem bieżącym w `foreground` i kreską `track`)
- Ramka telefonu 22rem: pięć segmentów mieści się lub przewija, tabela przewija się z przypiętą kolumną Kategoria bez utraty wcięcia
- 1280 px z rozwiniętym sidebarem: tabela 7 lat bez poziomego przewijania
- Klawiatura: focus-visible na segmentach, zakładkach, przełączniku, regionie przewijanym i przyciskach grup

**Implementation Note**: Po zielonej automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed Fazą 3.

---

## Faza 3: E2E i przegląd na produkcji

### Overview

Test Playwright prawdziwej aplikacji na własnych danych (lekcje S-30 F2, S-32 F2) i ręczny przegląd po wdrożeniu, który zamyka Unknown roadmapy i daje wejście do osobnej zmiany danych.

### Changes Required:

#### 1. E2E

**File**: `tests/e2e/reports.spec.ts`

**Intent**: Ścieżka użytkownika w przeglądarce: segment „Lata”, przełączenia, zmiana roku.

**Contract**: Warunek wstępny jak w teście „rok do roku” (`reports.spec.ts:10-11`): baza E2E ma Budżet poprzedniego roku w realizacji z `demo:seed-previous-year` (UI tworzy tylko szkic następnego roku, więc test nie zakłada Budżetu w realizacji sam); komentarz w nagłówku pliku to wymienia. Test tworzy przez UI tylko własną operację (i w razie potrzeby grupę) w kategorii obecnej w obu latach i usuwa je w `finally`; zmiana roku tworzy i usuwa szkic następnego roku jak `reports.spec.ts:418-446`. Kroki: klik `getByRole('button', { name: 'Lata', exact: true })` → URL `/reports?view=years`, tytuł „Raport: lata …”, wiersz kategorii z kwotą w obu latach; klik „Przychody” i „Pełne lata” zmienia tabelę bez nowego żądania (URL bez zmian); zmiana roku w sidebarze zostaje na `/reports?view=years` (wzór `reports.spec.ts:418-469`). Asercja na treść raportu, nie tylko na URL (S-32 plan-review F2).

#### 2. Przegląd na produkcji

**File**: `context/changes/report-categories-across-years/production-review.md` (nowy, notatki z przeglądu)

**Intent**: Warunek „Done when” kamienia (część S-34) i niewiadoma roadmapy o latach 2020–2021: użytkownik porównuje kategorię przez 2020–2026 i zapisuje, co widać.

**Contract**: Notatka z datą: porównana kategoria i jej liczby; wiersze 42 Chemia i kosmetyki, 44 Internet, 45 Streaming oraz etykiety kosmetyczne w Rzeczach osobistych 2022–2025 (luka po wczytaniu: tak/nie, decyzja o osobnej zmianie danych); przesunięcie listopada w „Do D” (lata z arkusza bez listopada, 2026 z połową) z odczytem „Zmiany od 2020” w obu trybach; „Razem” 2020 i 2021 w „Pełnych latach” równe raportowi roku (65 239,23 / 84 700,99 zł wydatków, 69 913,54 / 91 448,99 zł przychodów, bez ruchów funduszu).

### Success Criteria:

#### Automated Verification:

- E2E „Lat” przechodzi: `npx playwright test tests/e2e/reports.spec.ts tests/e2e/reports-view.spec.ts`

#### Manual Verification:

- Na produkcji po wdrożeniu użytkownik porównał co najmniej jedną kategorię przez lata 2020–2026 w „Lata”, a `production-review.md` zapisuje wynik przeglądu luk 42/44/45 i przesunięcia listopada

---

## Testing Strategy

### Unit Tests:

- `ReportYearsRangeTest`: kolumny tylko z Budżetów w realizacji ≤ roku bieżącemu (szkic 2027 i rok przyszły odpadają, rok bez Budżetu nie ma kolumny); `throughOf` dla 14.11.2026 (2020 → 14.11.2020, 2026 → 31.12.2026) i dla 29.02.2028 (2027 → 28.02.2027, 2024 → 29.02.2024, 2028 → 31.12.2028); styczeń 2027 ze szkicem 2027 → `currentYear` i `cut` `null`; brak Budżetów w realizacji → puste kolumny.
- `report-years.test.ts`: etykieta cięcia (14 listopada, 29 lutego), format zmiany (+, U+2212, „—”), słupki sparkline (`null`, ujemne, wszystkie ≤ 0, rok bieżący).

### Integration Tests:

- `ReportYearsTest` (wyrocznie policzone ręcznie w teście, nie przez inny odczyt):
  - Pełne lata: kwota kategorii zakończonego roku = kwota w `PeriodReport::forYear` co do grosza, „Razem” = wydatki/przychody raportu roku; Do D: rok bieżący = raport roku w toku (z operacją datowaną po dziś).
  - Cięcie: operacja 15.11.2020 poza „Do 14 listopada”, w „Pełnych latach”; operacja 15.11.2026 liczona w obu trybach.
  - Granice dat: operacja Budżetu 2025 z datą w 2024 (wstawiona przez `DB::table`, jak po restore) nie wchodzi do żadnej sumy, tak jak w `PeriodReport::forYear`.
  - „—” kontra 0: kategoria bez linii w roku → `null`; z linią i zwrotem netującym do 0 → `0`; grupa `null`, gdy żadna kategoria nie ma linii.
  - Wiersze: kategoria z operacją tylko w zakresie wyciętym w „Do D” jest wierszem tylko w „Pełnych latach”; zarchiwizowana Kino (operacje 2020–2024, bez linii 2025–2026) ma wiersz, `is_archived`, zmianę `null`; po `MergeCategories` (ADR-0078) historia źródła jest w wierszu celu, źródło nie ma wiersza, sumy lat bez zmian.
  - Zmiana: „od 2022” (Meble, `change_first_year` 2022), 0 przed pierwszą kwotą się nie liczy, pierwszy rok = rok końcowy → `null`; ton nie jest testowany na serwerze (klient).
  - Statystyki: „Średnio rocznie” 35 980 → 68 240 przez 6 lat = 113 (+11,3%); Pełne lata 42 000 → 74 000: zmiana +76,2% (762), średnio +12,0% (120); „Najszybciej rośnie” z progiem 4 kolumn (kategoria z 3 kolumnami odpada), remis → wcześniejsza na liście, brak kandydata → `null`; jeden rok w realizacji → wszystko `null`.
  - `max_years`: remis → oba lata; w „Pełnych latach” bez roku w toku; maksimum ≤ 0 → pusta lista.
  - Wykluczenia: własny przelew, ruch kapitału, dane innego użytkownika, szkic.
  - Liczba zapytań stała dla 2 i 7 Budżetów oraz 2 i 24 kategorii (`DB::enableQueryLog()`).
- `ReportsPageTest`: `?view=years` z wybranym szkicem → `period.kind = 'years'` i props `years`; bez Budżetu → `kind = 'year'`, `years: null`; pozostałe widoki `years: null`; brak Budżetu w realizacji (tylko szkic) → `years.columns = []`; kształt wiersza kategorii i grupy bez `->etc()`.
- `BudgetTest`: `destination=reports-years` przekierowuje do `/reports?view=years`; nieznany cel odrzucony.
- NFR: 7 Budżetów × 10 000 operacji, ≤ 2 s, PostgreSQL.

### Manual Testing Steps:

1. `/dev/ui/reports`: przejrzyj scenariusze `years-*` w obu motywach i w ramce telefonu.
2. Lokalnie `/reports?view=years`: przełącz Przychody i Pełne lata, sprawdź, że adres się nie zmienia, a podtytuł, kafelki i nagłówek zmiany idą za trybem.
3. Wybierz szkic w sidebarze: „Lata” zostaje i pokazuje te same lata.
4. Po wdrożeniu: przegląd z Fazy 3 (luki 42/44/45, listopad).

## Performance Considerations

Jedno zapytanie agregujące po `financial_operations` z `whereIn budget_id` zwraca wiersz na linię (kilkaset), nie na dzień, plus po jednym zapytaniu na linie Budżetów i grupy. Kategorie przychodzą z linii (`$with = ['category']`). Statystyki liczone w pamięci na kilkudziesięciu wierszach. Budżet: 2 s przy 7 × 10 000 operacji (NFR); indeks na `financial_operations(budget_id, …)` używany jak w dotychczasowych odczytach — jeśli NFR nie przejdzie, sprawdź `EXPLAIN` na PostgreSQL przed jakąkolwiek migracją indeksu (migracja byłaby decyzją poza planem).

## Migration Notes

Brak migracji i zmian danych. Widok czyta istniejące Budżety 2020–2026 (ADR-0085, ADR-0096) na jednej liście kategorii (ADR-0063).

## Open Risks & Assumptions

- Lata z arkusza (2020–2025) mają operacje datowane na ostatni dzień miesiąca (ADR-0085 decyzja 7), więc w „Do D” w środku miesiąca tracą cały bieżący miesiąc, a 2026 go częściowo ma; „Zmiana od 2020” jest wtedy zawyżona o kilka punktów. Przyjęte (decyzja planu 2), sprawdzane w przeglądzie Fazy 3.
- Kategorie 42, 44, 45 mogą mieć kwoty tylko w 2020–2021 i „—” w 2022–2025 (ADR-0096 decyzja 6 nie przeładowała 2022–2025); „—” jest tam luką po wczytaniu, nie brakiem pozycji. Poza S-34 (decyzja planu 3).
- „Razem” w aplikacji różni się od arkusza „Analiza” o ruchy funduszu (ADR-0096:10) — to zgodne z definicją Execution, nie błąd.
- Siatka 7 lat przy 1280 px z sidebarem: jeśli się nie mieści, tabela przewija się jak Trend; to dopuszczalne wg ADR-0097:19, ale kryterium ręczne Fazy 2 tego pilnuje.

## References

- ADR-0097: `context/foundation/adr/0097-report-years-ranking-and-chart-colours.md` (Years, Same-day Cut, Full Years, Columns, Cells, Change, Statistics, Amounts, Navigation, Colour, Rules to change)
- Słownik: `context/foundation/glossary.md` (Period Report, akapit „Years”)
- Roadmapa: `context/foundation/roadmap.md` (S-34, MS-01)
- F-07: `context/archive/2026-11-14-report-redesign-decisions/plan.md`, `reviews/impl-review.md`
- Wzory: `context/archive/2026-11-11-report-trend-by-month/plan.md` (+ `reviews/impl-review.md`), `context/archive/2026-11-12-rolling-twelve-month-report/plan.md` (+ `reviews/impl-review.md`, `reviews/plan-review.md`)
- Dane: `context/archive/2026-11-13-sheet-load-2020-2021/production-load-notes.md`, ADR-0085, ADR-0096
- Karta: design system Atrament, `project/components/PageReportsYears/README.md` i `preview.html` (link w `context/foundation/design-system.md`)
- Kod: `app/Http/Controllers/ReportController.php:22-92`, `app/Support/MonthCategoryActuals.php:27-86`, `app/Support/ReportWindowRows.php:144-181`, `app/Support/ReportCategoryTree.php:94`, `app/Support/PeriodReport.php:316-325`, `resources/js/pages/Reports.vue`, `resources/js/components/ReportTrendTable.vue`, `resources/js/components/ReportTrendRow.vue`, `resources/js/components/BudgetYearSwitcher.vue:48-61`, `resources/css/app.css:1744-1792`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Odczyt „Lata” na serwerze

#### Automated

- [x] 1.1 Testy serwera na SQLite przechodzą — 7585580
- [x] 1.2 Te same testy przechodzą na PostgreSQL — 7585580
- [x] 1.3 Liczba zapytań odczytu „Lat” jest taka sama dla 2 i 7 Budżetów oraz dla 2 i 24 kategorii — 7585580
- [x] 1.4 NFR „Lata” ≤ 2 s przy 7 Budżetach × 10 000 operacji na PostgreSQL — 7585580
- [x] 1.5 Formatowanie i Sloppy bez nowych znalezisk — 7585580
- [x] 1.6 Typy frontu po zmianie propsa — 7585580

#### Manual

- [ ] 1.7 W lokalnej aplikacji `/reports?view=years` zwraca props `years`, a wybrany szkic nie zmienia odpowiedzi

### Phase 2: Widok „Lata”

#### Automated

- [x] 2.1 Testy Vitest „Lat” przechodzą
- [x] 2.2 Budowanie i typy
- [x] 2.3 Strażnicy UI
- [x] 2.4 Kontrakt UI
- [x] 2.5 Indeks reguł zaktualizowany
- [x] 2.6 Pełna bramka na PostgreSQL i Sloppy

#### Manual

- [ ] 2.7 `/dev/ui/reports` w obu motywach: scenariusze `years-*` zgodne z kartą `PageReportsYears`
- [ ] 2.8 Ramka telefonu 22rem: segmenty i przewijana tabela z przypiętą kolumną
- [ ] 2.9 1280 px z rozwiniętym sidebarem: tabela 7 lat bez poziomego przewijania
- [ ] 2.10 Klawiatura: focus-visible na wszystkich kontrolkach widoku

### Phase 3: E2E i przegląd na produkcji

#### Automated

- [ ] 3.1 E2E „Lat” przechodzi

#### Manual

- [ ] 3.2 Na produkcji porównano kategorię przez 2020–2026, a `production-review.md` zapisuje przegląd luk 42/44/45 i listopada
