# Ostatnie 12 miesięcy — plan implementacji

## Overview

S-32 z roadmapy M-3 (`context/foundation/prd-v3.md`, FR-025, nice-to-have). Raporty dostają czwarty segment, „12 miesięcy”. To raport okresu (te same tabele, panel miar i wykres co raport roku) dla **12 zamkniętych miesięcy liczonych od dziś (UTC)**. 12 listopada 2026 to listopad 2025 – październik 2026, a 1 lutego 2027 to luty 2026 – styczeń 2027.

Okno nie zależy od roku wybranego w pasku bocznym i przechodzi przez co najwyżej dwa Budżety. Kwoty liczą tylko miesiące, których Budżet jest w realizacji. Brakujące miesiące nazywa notka, a gdy żaden miesiąc nie jest pokryty, strona pokazuje stan pusty.

Okres nie ma porównania z rokiem wcześniej ani Trendu. Jego wynik równa się sumie Wyników miesięcy pokrytych miesięcy, co do grosza.

## Current State Analysis

**Raport jest przywiązany do jednego Budżetu.**
- `PeriodReport::build(Budget, list<int> $months, ?ReportComparisonPeriod)` czyta linie jednego Budżetu i `MonthCategoryActuals::forMonths` tego Budżetu (`app/Support/PeriodReport.php:65-138`).
- Słownik definiuje Period Report jako „A reading of one Budget for one calendar month or for the whole year” (`context/foundation/glossary.md:149`).
- Nie ma terminu na wynik przez dwa lata: Year Result to jeden rok Budżetu (`glossary.md:144-146`).

**Kontroler (`app/Http/Controllers/ReportController.php`).**
- Rok bierze się z sesji (`SelectedBudgetYear::resolve`, `:23`).
- Brak Budżetu wybranego roku daje stan „brak budżetu” (`:28-41`).
- Budżet w przygotowaniu daje `report: null` (`:58`), a strona pokazuje stan „draft” (`resources/js/pages/Reports.vue:91-94`).
- `?view=trend` daje Trend, a każda inna wartość `view` daje rok (`:46`). `?month` 1–12 daje miesiąc (`:75-84`).

**Ta sama tożsamość wierszy przez lata.**
- Wiersze są kluczowane `category_id` (kategoria listy), a kolejność i grupy pochodzą z `ReportCategoryTree::entryNodesOfType` (`app/Support/ReportCategoryTree.php:94-114`). Te same grupy (`category_group_id`) obowiązują w każdym roku.
- Przegląd S-30 przewidział, że S-32 użyje tej wspólnej ścieżki po raz trzeci (`context/archive/2026-11-11-report-trend-by-month/reviews/impl-review.md:78-88`).

**Odczyt zakresu dat już istnieje.**
- `MonthCategoryActuals::forRange(Budget, from, through)` to jedno zapytanie na Budżet (`app/Support/MonthCategoryActuals.php:67-86`).
- Okno 12 zamkniętych miesięcy dotyka co najwyżej dwóch lat, więc wystarczą dwa takie odczyty.

**Operacje istnieją tylko w Budżecie w realizacji.**
- Walidacja odrzuca zapis do Budżetu poza realizacją: `OperationCategoryChoice::check` (`app/Support/CategoryList/OperationCategoryChoice.php:48-50`) na wpisie ręcznym, w imporcie CSV i w przebudowie. `ManageFinancialOperation.php:236` chroni tylko gałąź dołączenia kategorii przy pierwszym użyciu.
- Wyjątkiem jest restore (`app/Actions/RestoreUserData.php:228`, `:784-813`): nie sprawdza fazy ani roku daty. Przyjmujemy go, bo snapshot pochodzi z aplikacji.
- Rok daty operacji jest rokiem jej Budżetu na każdej ścieżce zapisu (`context/archive/2026-11-12-report-to-operations/plan.md:11`).
- Miesiąc bez Budżetu w realizacji nie ma więc operacji, a filtr Operacji po samych datach całego okna pokaże dokładnie to, co liczy raport.

**Porównanie i Trend są pojęciami jednego roku.**
- `ReportComparisonPeriod` szuka Budżetu `year - 1` wybranego Budżetu (`app/Support/ReportComparisonPeriod.php:31-56`).
- Trend to 12 miesięcy kalendarzowych jednego Budżetu (ADR-0077).

**Strona.**
- `Reports.vue` ma `SegmentedControl` z opcjami Rok | Miesiąc | Trend (`:51-55`). Setter nawiguje przez `router.get(reportsRoute(...).url)` (`:32-49`).
- Tytuł powstaje w computed `title` (`:61-82`).
- `ReportStatPanel` ma prop `periodKind: 'year' | 'month'`: etykieta wyniku to „Wynik roku” albo „Wynik” (`resources/js/components/ReportStatPanel.vue:19`, `:31-34`).
- `ReportEntryTable` przekazuje `month` do `ReportCategoryRow` (`ReportEntryTable.vue:119`, `:141`).
- Link nazwy kategorii to `reportCategoryOperationsQuery(category, year, month | null)`, z zakresem jednego miesiąca albo jednego roku (`resources/js/lib/report-category-operations.ts:18-43`). `aria-label` z `operations_link_label` dostaje okres „<miesiąc> <rok>” albo „<rok>” (`ReportCategoryRow.vue:45-64`).
- Filtr Operacji przyjmuje `date_from`/`date_to` bez `year` (`app/Support/OperationsListQuery.php:130-135`, `app/Http/Requests/IndexOperationsRequest.php:43-44`).

**Przełącznik roku.**
- Na Raportach poza Trendem wysyła `destination: 'reports'` (`resources/js/components/BudgetYearSwitcher.vue:30-49`).
- `BudgetController` przekierowuje wtedy do raportu roku (`app/Http/Controllers/BudgetController.php:155-160`).
- Z widoku „12 miesięcy” daje to raport wybranego roku bez zmian w backendzie, czyli dokładnie zachowanie wybrane przez użytkownika.

**Kitchen sink i strażnicy.**
- `/dev/ui/reports` (`resources/js/pages/dev/ReportsKitchenSink.vue`) powiela markup strony nad scenariuszami z `resources/js/lib/reports-fixtures.ts:605-858`.
- Pliki raportu stoją na listach `tests/Unit/ReportsHardcodedUiTest.php:7-21` i `scripts/check-ui-literals.mjs:120-132`.
- Teksty są w `lang/pl/ui.php:380-422`, pilnowane przez `tests/Unit/UiTranslationKeysTest.php`.

**Testy i dane.**
- Pest w `tests/Feature/PeriodReportTest.php` (helpery `reportFixture`, `reportLine`, `bookReportOperation`, `:26-77`, operacje przez `ManageFinancialOperation`).
- Klasa PHPUnit `tests/Feature/ReportsPageTest.php`: `withSession(['budget_year' => …])`, `travelTo`.
- NFR `tests/Feature/Nfr/NfrPerfReportTest.php`: grupa `nfr`, tylko PostgreSQL, YoY dodał drugi Budżet z 10 000 operacji.
- E2E `tests/e2e/reports.spec.ts`.
- Dev ma Budżet 2025 z `PreviousYearMockSeeder` (`php artisan demo:seed-previous-year`), a produkcja ma dziś tylko Budżet 2026.

## Desired End State

Na Raportach przełącznik ma segmenty **Rok | Miesiąc | 12 miesięcy | Trend**. 12 listopada 2026 kliknięcie „12 miesięcy” otwiera `/reports?view=last-12`. Nagłówek „Raport: ostatnie 12 miesięcy” ma pod sobą zakres „listopad 2025 – październik 2026”, bez paska miesięcy.

- **Dev (Budżety 2025 i 2026 w realizacji).** Panel miar pokazuje Przychody, Wydatki i „Wynik 12 miesięcy”, a pod nim wykres i tabele Wydatki i Przychody jak w raporcie roku, bez kolumn porównania. Wynik równa się sumie Wyników miesięcy od listopada 2025 do października 2026. Kwota kategorii obecnej w obu Budżetach sumuje oba lata, a udział liczy się od wydatków okna.
- **Produkcja (tylko Budżet 2026).** Nad panelem stoi notka: „Bez budżetu w realizacji: listopad 2025 – grudzień 2025. Kwoty obejmują styczeń 2026 – październik 2026 (10 z 12 miesięcy).”
- **Żaden miesiąc okna nie ma Budżetu w realizacji.** Zamiast raportu jest stan pusty „Brak budżetu w realizacji w tym okresie”.
- **Link nazwy kategorii.** Prowadzi do `/operations?category_id=<id>&date_from=2025-11-01&date_to=2026-10-31`, a czytnik ekranu ogłasza „Operacje kategorii Jedzenie, listopad 2025 – październik 2026”.
- **Okno nie zależy od wybranego roku.** Także przy wybranym roku z Budżetem w przygotowaniu `?view=last-12` pokazuje okno. Przełącznik roku w pasku bocznym prowadzi do raportu roku wybranego roku.
- **Rok, Miesiąc i Trend** działają bez zmian.

Weryfikacja:
- testy Feature okna i odczytu (granice dat, pokrycie, równość z sumą `BudgetMonthResult`, stała liczba zapytań) na SQLite i PostgreSQL;
- `assertInertia` strony;
- NFR ≤ 2 s;
- Vitest celu linku;
- scenariusze kitchen sinka;
- E2E;
- `composer ci:check`, `vendor/bin/sloppy ci`;
- ręczne sprawdzenie na devie.

### Key Discoveries:

- Okno 12 zamkniętych miesięcy jest zawsze w co najwyżej dwóch latach kalendarzowych. Część niepokryta jest zawsze ciągła: cała część jednego roku albo całe okno. Wystarczy więc jeden zakres pokrycia i jeden zakres braku (`MonthCategoryActuals.php:67`, `OperationCategoryChoice.php:48-50`).
- `ReportCategoryTree::entryNodesOfType` przyjmuje dowolne wiersze z `category`, więc odczyt okna podaje mu wiersze złączone po `category_id` z dwóch Budżetów. Kolejność i grupy pochodzą wtedy z tego samego miejsca co w Roku i Trendzie (`ReportCategoryTree.php:94`).
- `BudgetYearSwitcher` już wysyła `reports` poza Trendem, więc „zmiana roku → raport roku” nie wymaga nowego `destination` (`BudgetYearSwitcher.vue:30-49`, `SelectBudgetYearRequest.php:27`).
- Okno ma same zamknięte miesiące, więc nigdy nie jest „w toku”: `is_in_progress` jest zawsze `false` i nie ma znacznika „okres w toku”.

## What We're NOT Doing

- **Porównania okna z tym samym oknem rok wcześniej** (`comparison` zawsze `null`). ADR-0081 zapisuje, że porównanie lat zostaje w Roku i Miesiącu (ADR-0074). Pełne porównanie okna byłoby na produkcji możliwe najwcześniej 1.01.2028, więc wróci, jeśli w ogóle, osobnym wycinkiem.
- **Trendu okna** (12 kolumn od listopada do października). ADR-0077 zostaje rokiem Budżetu, a segment Trend działa jak dziś.
- **Bieżącego miesiąca w oknie**, okna 365 dni i wyboru końca okna paskiem miesięcy (odrzucone warianty B, C, D).
- **Ukrywania albo wyłączania segmentu**, gdy okno nie jest w pełni pokryte (odrzucony wariant). Segment jest zawsze dostępny, gdy użytkownik ma jakikolwiek Budżet.
- **Nowego `destination` w przełączniku roku** (`reports-last-12`) i zachowania widoku przy zmianie roku.
- **Dostępu do „12 miesięcy” ze stanu „Budżet w przygotowaniu”.** Ten stan nie ma przełącznika okresu i tak zostaje. Okno działa tam tylko z URL `?view=last-12`.
- **Średniej miesięcznej**, normalizacji kwot do 12 miesięcy przy częściowym pokryciu i ekstrapolacji brakujących miesięcy.
- **Linku z wiersza grupy, z wykresu i z panelu miar** (bez zmian względem S-31).
- **Własnego zakresu dat, eksportu i druku** (Parked w roadmapie).
- **Zmian w `ReportComparisonPeriod`, `ReportTrend` i w odczycie Roku i Miesiąca.** Ich wynik zostaje bajt w bajt taki sam.

## Implementation Approach

Kolejność taka sama jak w S-28…S-31: najpierw reguła (słownik + ADR-0081), potem odczyt na serwerze z dowodem równości, potem widok.

Na serwerze powstają dwie części:
- **Okno.** Mała, czysta wartość `ReportTwelveMonthWindow` w `app/Support`: daty okna liczone z dziś, Budżety użytkownika w realizacji, które pokrywają jego lata, oraz zakres pokrycia i braku.
- **Odczyt okna.** Nowy konstruktor `PeriodReport::forWindow`, który buduje wiersze z odczytów `forRange` każdego pokrywającego Budżetu, złączonych po `category_id`. Dalej idzie tą samą ścieżką co Rok: `entriesOfType`, `withShares`, sumy.

Kontroler rozpoznaje `?view=last-12` przed sprawdzeniem fazy wybranego Budżetu. Prop `period` dostaje `kind: 'last-12'` i `window` (`null` w innych widokach), a typ TS zmienia się w tym samym commicie.

Strona dodaje segment, tytuł z zakresem, etykietę wyniku, notkę pokrycia jako mały komponent (żeby kitchen sink jej nie powielał), stan pusty bez pokrycia i link z zakresem okna.

## Critical Implementation Details

**Kolejność w kontrolerze.** `?view=last-12` musi być rozpoznane, zanim brak raportu dla Budżetu w przygotowaniu (`ReportController.php:58`) i stan „draft” strony (`Reports.vue:91-94`) przejmą odpowiedź. Okno nie zależy od wybranego roku. Stan „brak budżetu” zostaje tylko dla użytkownika bez żadnego Budżetu.

**Granice okna i strefa czasowa.** „Dziś” to `CarbonImmutable::today('UTC')`, jak w `ReportController.php:45`.
- `through` to ostatni dzień miesiąca poprzedzającego dziś.
- `from` to pierwszy dzień miesiąca 11 miesięcy przed miesiącem `through`.
- Liczyć przez `startOfMonth()->subMonthsNoOverflow(...)`. Na 31 marca zwykłe `subMonth` przeskoczyłoby luty.

**Znacznik „zarchiwizowana”.** Dla wiersza z liniami w kilku Budżetach znacznik idzie za linią najnowszego pokrywającego Budżetu, który tę kategorię ma. Bez żadnej linii idzie za `archived_at` kategorii listy (przedłużenie ADR-0070 i ADR-0074, zapisane w ADR-0081).

## Phase 1: Reguły i odczyt okna na serwerze

### Overview

Słownik i ADR-0081 opisują okres „ostatnie 12 miesięcy”, a serwer liczy go i przekazuje stronie. Ta faza zmienia prop `period` po obu stronach (PHP i typ TS) i dowodzi zgodności z sumą Wyników miesięcy, ale widok jeszcze nie wystawia segmentu.

### Changes Required:

#### 1. Słownik

**File**: `context/foundation/glossary.md`

**Intent**: Period Report przestaje być z definicji „odczytem jednego Budżetu”. Dochodzi okres ostatnich 12 miesięcy i jego wynik, bo AGENTS.md wymaga wpisu w słowniku przed kodem.

**Contract**:
- Pierwsze zdanie wpisu **Period Report** (`:149`) obejmuje trzeci okres: „…for one calendar month or for the whole year of one Budget, or for the last twelve closed months across Budgets (ADR-0081)”.
- Nowy akapit **Last twelve months (ADR-0081)** zapisuje:
  - okno: 12 zamkniętych miesięcy kalendarzowych kończących się na miesiącu przed dzisiejszym (UTC), niezależne od wybranego roku;
  - pokrycie: liczą się tylko miesiące, których Budżet jest w realizacji, a reszta jest nazwana i nie jest liczona ani szacowana;
  - wynik: suma Month Result pokrytych miesięcy, w UI „Wynik 12 miesięcy”, a nie Year Result;
  - wiersze złączone po kategorii listy, znacznik archiwum z najnowszego Budżetu z linią;
  - brak porównania i brak Trendu.
- Teksty w UI: segment „12 miesięcy”, tytuł „Raport: ostatnie 12 miesięcy”.
- _Avoid_ wpisu dostaje: „rok kroczący”, „Year Result (for the twelve-month figure)”, „average month”.

#### 2. ADR-0081

**File**: `context/foundation/adr/0081-report-last-twelve-months.md` (nowy)

**Intent**: Zapisuje decyzje tej sesji planowania w formacie ADR-0077 (kontekst, decyzja w akapitach, „We rejected”). Dodaje do ADR-0070 i nie zmienia jego klauzul dla Roku i Miesiąca.

**Contract**: Akapity:
- **Okno:** zamknięte miesiące od dziś w UTC, przykłady 12.11.2026 → 2025-11-01..2026-10-31 oraz 1.01.2027 → 2026-01-01..2026-12-31, równe raportowi roku 2026.
- **Pokrycie:** Budżet w realizacji, notka, stan pusty przy zerze pokrytych miesięcy; segment zawsze dostępny.
- **Źródło:** `forRange` na pokrywający Budżet, złączenie po `category_id`, `ReportCategoryTree`, stała liczba zapytań.
- **Wynik:** suma Month Result; udział od wydatków okna.
- **Znacznik archiwum:** z najnowszego Budżetu z linią.
- **Bez porównania i bez Trendu**, z uzasadnieniem: produkcja najwcześniej 1.01.2028.
- **Nawigacja:** `?view=last-12`, czwarty segment po „Miesiąc”, zmiana roku prowadzi do raportu roku.
- **Link do Operacji:** zakres całego okna; opiera się na tym, że operacje powstają tylko w Budżecie w realizacji (`OperationCategoryChoice.php:48-50`), z restore jako przyjętym wyjątkiem (snapshot z aplikacji).
- **We rejected:** bieżący miesiąc w toku w oknie (B), 365 dni (C), koniec okna wybierany paskiem miesięcy (D), segment wyłączony do pełnego pokrycia, porównanie przy pełnym pokryciu, porównanie na wspólnych miesiącach, zostawanie na widoku przy zmianie roku (`reports-last-12`), segment przed „Rok”.

#### 3. Okno raportu

**File**: `app/Support/ReportTwelveMonthWindow.php` (nowy, `php artisan make:class`)

**Intent**: Jedno miejsce, które z dzisiejszego dnia i Budżetów użytkownika wylicza okno i jego pokrycie, żeby kontroler, odczyt i testy nie liczyły dat osobno.

**Contract**:
- `final class ReportTwelveMonthWindow` z `public static function resolve(User $user, CarbonImmutable $today): self`.
- Publiczne readonly:
  - `CarbonImmutable $from`, `CarbonImmutable $through`;
  - `list<Budget> $budgets`: Budżety użytkownika w fazie `Execution` dla lat okna, rosnąco po roku, jedno zapytanie;
  - `?CarbonImmutable $coveredFrom`, `?CarbonImmutable $coveredThrough`;
  - `int $coveredMonthCount` (0–12);
  - `?CarbonImmutable $missingFrom`, `?CarbonImmutable $missingThrough`.
- Każdy pokrywający Budżet daje swój wycinek okna: część okna w jego roku.
- `toArray()` zwraca `array{from: string, through: string, covered_from: ?string, covered_through: ?string, covered_month_count: int, missing_from: ?string, missing_through: ?string}` z datami `Y-m-d`.
- Invariant: pokrycie i brak są ciągłe i razem dają całe okno; przy pełnym pokryciu `missing_*` jest `null`, a przy zerowym `covered_*` jest `null`.

#### 4. Odczyt okna w Period Report

**File**: `app/Support/PeriodReport.php`

**Intent**: Raport okna liczy wiersze z kilku Budżetów, złączone po kategorii listy, i idzie dalej tą samą ścieżką co Rok. Grupy, kolejność, udział i sumy nie mają osobnej implementacji.

**Contract**:
- `public static function forWindow(ReportTwelveMonthWindow $window): self`.
- Wiersze (`ReportRow`) powstają z linii każdego Budżetu z `$window->budgets` i jednego `MonthCategoryActuals::forRange($budget, max(from, 1 stycznia roku), min(through, 31 grudnia roku))` na Budżet. Kwota i `has_operations` sumują się po `category_id`.
- `line` wiersza to linia najnowszego Budżetu z tą kategorią, bo stąd bierze się znacznik archiwum w `categoryEntry`.
- `comparison` jest `null`.
- Przy `$window->budgets === []` (zerowe pokrycie) `forWindow` zwraca pusty raport bez zapytań: sumy 0, `has_operations: false`, puste `income` i `expense`, `comparison: null`. Bez Budżetu nie ma skąd wziąć `user_id` grup.
- Liczba zapytań jest stała: na Budżet linie + odczyt, plus grupy, niezależnie od liczby kategorii.
- `forMonth` i `forYear` dają wynik identyczny z dzisiejszym.
- Docblock klasy wymienia trzeci okres i ADR-0081.

#### 5. Kontroler i prop `period`

**File**: `app/Http/Controllers/ReportController.php`

**Intent**: `?view=last-12` (dokładnie ta wartość) wybiera okno niezależnie od fazy wybranego Budżetu. Inne widoki działają jak dziś.

**Contract**:
- `period.kind` dostaje wartość `'last-12'`. Przy niej `period.month` jest `null`, `period.is_in_progress` jest `false`, a `period.default_month` liczy się jak dziś.
- `period.window` to `ReportTwelveMonthWindow::toArray()` przy `last-12` i `null` w każdym innym widoku, także w gałęzi „brak budżetu”.
- `report` to `PeriodReport::forWindow($window)->toArray()` zawsze przy `last-12`, gdy użytkownik ma jakikolwiek Budżet, także przy zerowym pokryciu (wtedy `has_operations: false`). `trend` jest `null`.
- `budgetYear` i `budgetPhase` zostają wartościami wybranego roku, bo pasek boczny ich używa.
- `?month` jest ignorowany przy `view=last-12`.
- Użytkownik bez Budżetów dostaje dzisiejszą odpowiedź „brak budżetu”.

**File**: `resources/js/types/reports.ts`

**Intent**: Typ strony odzwierciedla nowy prop w tym samym commicie (`.ai/rules/inertia-props.md`).

**Contract**:
- `ReportPeriod.kind: 'year' | 'month' | 'last-12' | 'trend'`.
- Nowy typ `ReportWindow` o kształcie `toArray()` okna.
- `ReportPeriod.window: ReportWindow | null`.
- Miejsca, które dziś zawężają `kind` (`Reports.vue`, kitchen sink, fixtures `ReportPeriod`), kompilują się. Fixtures dostają `window: null`.

#### 6. Testy serwera

**File**: `tests/Feature/ReportTwelveMonthWindowTest.php` (nowy, Pest)

**Intent**: Granice okna i pokrycie na konkretnych datach.

**Contract**: Przypadki:
- daty okna: 12.11.2026, 1.01.2027, 31.03.2027 (bez przeskoku lutego), 1.02.2027;
- pokrycie przy Budżetach 2025+2026 w realizacji (12, bez braku);
- pokrycie przy samym 2026 (10, brak 2025-11-01..2025-12-31);
- pokrycie przy 2025 w realizacji i 2026 w przygotowaniu (2, brak 2026-01-01..2026-10-31);
- pokrycie bez żadnego (0);
- Budżet innego użytkownika nie pokrywa.

**File**: `tests/Feature/PeriodReportTest.php`

**Intent**: Dowód równości i kształtu odczytu okna na operacjach zapisanych przez `ManageFinancialOperation`.

**Contract**: Przypadki:
- wynik okna = suma `BudgetMonthResult::forViewedMonth` pokrytych miesięcy obu Budżetów;
- kwota kategorii z liniami w obu Budżetach = suma jej kwot z odczytów miesięcy;
- granice: operacje 2025-11-01 i 2026-10-31 liczone, 2025-10-31 i 2026-11-01 nie;
- zwrot liczony netto;
- kategoria tylko w jednym Budżecie ma wiersz;
- znacznik archiwum z najnowszego Budżetu z linią (zarchiwizowana w 2025, aktywna w 2026 → aktywna, i odwrotnie);
- suma grupy = suma jej kategorii;
- udział od wydatków okna;
- `comparison: null`;
- przy zerowym pokryciu brak wierszy;
- stała liczba zapytań (2 i 24 kategorie).

**File**: `tests/Feature/ReportsPageTest.php`

**Intent**: Kształt propsów i reguły kontrolera.

**Contract**: Testy `assertInertia` bez `->etc()` na `period`. Przypadki:
- `?view=last-12` przy `travelTo('2026-11-12')` daje `period.kind`, `period.window` z datami i pokryciem oraz `report` z kwotami;
- wybrany rok w przygotowaniu nadal daje raport okna;
- użytkownik bez Budżetów dostaje „brak budżetu” z `window: null`;
- `?month=3&view=last-12` ignoruje miesiąc;
- Rok, Miesiąc i Trend mają `window: null`;
- inny użytkownik z Budżetem 2025 nie pokrywa okna.

**File**: `tests/Feature/Nfr/NfrPerfReportTest.php`

**Intent**: Okno przez dwa Budżety po 10 000 operacji otwiera się w ≤ 2 s.

**Contract**: Nowa metoda testu w tej samej klasie, na danych dwóch Budżetów, `GET /reports?view=last-12` z `travelTo` w środek drugiego roku.

### Success Criteria:

#### Automated Verification:

- Testy okna, odczytu i strony przechodzą na SQLite: `php artisan test --compact tests/Feature/ReportTwelveMonthWindowTest.php tests/Feature/PeriodReportTest.php tests/Feature/ReportsPageTest.php`
- Te same testy przechodzą na PostgreSQL (polecenie worktree z AGENTS.md, baza `testing_rolling_twelve_month_report`)
- Test NFR okna przechodzi na PostgreSQL: `artisan test --group=nfr --configuration=phpunit.nfr.xml tests/Feature/Nfr/NfrPerfReportTest.php`
- Typy kompilują się: `npm run build && npm run types:check`
- Bramka przechodzi: `composer ci:check` (docker) i `vendor/bin/sloppy ci` na hoście, po `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- Słownik i ADR-0081 opisują okno, pokrycie, wynik i odrzucone warianty tak, jak zostały wybrane w planowaniu
- Na devie (z mockiem 2025) `/reports?view=last-12` w Vue DevTools albo odpowiedzi Inertia ma `window` 2025-11-01..2026-10-31 z 12 pokrytymi miesiącami i wynik równy sumie Wyników miesięcy z planu miesiąca

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka.

---

## Phase 2: Widok „12 miesięcy” i linki

### Overview

Strona wystawia segment „12 miesięcy”, pokazuje okno z notką pokrycia i stanem pustym, a nazwy kategorii linkują do Operacji z zakresem okna. Każdy stan trafia do kitchen sinka, a E2E sprawdza przejście.

### Changes Required:

#### 1. Strona Raportów

**File**: `resources/js/pages/Reports.vue`

**Intent**: Czwarty segment i nowy okres bez zmian w Roku, Miesiącu i Trendzie.

**Contract**:
- **Segmenty:** `periodOptions` to Rok | Miesiąc | 12 miesięcy | Trend. Wybór `last-12` nawiguje do `reportsRoute({ query: { view: 'last-12' } }).url`.
- **Tytuł:** dla `last-12` tytuł to `title_last_12` „Raport: ostatnie 12 miesięcy”, a pod nim zakres okna „<month_name> <rok> – <month_name> <rok>” z `period.window`.
- **Stan „draft”:** nie przejmuje widoku przy `period.kind === 'last-12'`.
- **Bez pokrycia:** gdy `period.window.covered_month_count === 0`, strona pokazuje stan pusty (`IndexEmptyState`, `no_coverage_title` „Brak budżetu w realizacji w tym okresie” + opis) zamiast panelu i tabel.
- **Notka pokrycia:** przy częściowym pokryciu stoi nad panelem miar.
- **Panel miar:** dostaje `period-kind="last-12"`, bez porównania. Pasek miesięcy się nie pokazuje.
- **Tabele:** dostają zakres okna do linków.

#### 2. Panel miar

**File**: `resources/js/components/ReportStatPanel.vue`

**Intent**: Wynik okna ma własną etykietę, nie „Wynik roku”, bo to nie Year Result.

**Contract**: `periodKind: 'year' | 'month' | 'last-12'`. Dla `last-12` etykieta to `result_last_12` „Wynik 12 miesięcy”.

#### 3. Notka pokrycia

**File**: `resources/js/components/ReportCoverageNote.vue` (nowy)

**Intent**: Jedna notka dla strony i kitchen sinka. Mówi, których miesięcy brak i ile miesięcy liczą kwoty.

**Contract**:
- Prop `window: ReportWindow`. Renderuje się tylko przy `0 < covered_month_count < 12`.
- Tekst z `coverage_note`: „Bez budżetu w realizacji: :missing. Kwoty obejmują :covered (:count z 12 miesięcy).”
- Zakresy formatuje z `t.budget.month_name_N` i roku. Daty bierze z serwera, bez liczenia pokrycia w kliencie.
- Wygląd notki partial z `ReportStatPanel` (te same klasy i tokeny, ikona informacji).

#### 4. Link z zakresem okna

**File**: `resources/js/lib/report-category-operations.ts` (+ `report-category-operations.test.ts`)

**Intent**: Link nazwy kategorii obejmuje dowolny zakres dat, także przez granicę roku, przez ten sam budowniczy query co dziś.

**Contract**:
- Nowa funkcja `reportRangeOperationsQuery(category, from: string, through: string)` zwraca `null` przy `has_operations === false`, a w przeciwnym razie `operationsListQuery({...EMPTY_OPERATIONS_FILTERS, category_id, date_from: from, date_to: through}, DEFAULT_OPERATIONS_SECTION)`.
- Istniejąca `reportCategoryOperationsQuery` zachowuje sygnaturę i wynik.
- Testy Vitest:
  - zakres 2025-11-01..2026-10-31;
  - `null` dla wiersza bez operacji;
  - pozostałe filtry puste;
  - etykieta okresu „listopad 2025 – październik 2026”, jeśli jej formatowanie ląduje w `lib` (zalecane: czysta funkcja `reportWindowLabel(window, monthNames)` w tym samym pliku lub `lib/report-window.ts` z testem).

**File**: `resources/js/components/ReportEntryTable.vue`, `resources/js/components/ReportCategoryRow.vue`

**Intent**: Tabela i wiersz przyjmują zakres okna obok dzisiejszego `month`.

**Contract**:
- Opcjonalny prop `range: { from: string; through: string; label: string } | null` (domyślnie `null`).
- Przy `range` link używa `reportRangeOperationsQuery`, a `aria-label` dostaje `range.label` jako `:period`.
- Bez `range` zachowanie zostaje jak dziś.

**File**: `resources/js/ui-contract.test.ts`

**Intent**: Kontrakt linku z nazwy kategorii (`:529-546`) pilnuje też linku okna.

**Contract**: Test „links report category names to Operations…” dodatkowo wymaga, by `components/ReportCategoryRow.vue` zawierał `reportRangeOperationsQuery`. Obecne asercje (`@/routes`, `reportCategoryOperationsQuery`, brak `href="/operations`, brak linku w `ReportGroupRow.vue`) zostają.

#### 5. Teksty

**File**: `lang/pl/ui.php` (i typy kluczy, jeśli `UiTranslationKeysTest` tego wymaga)

**Intent**: Nowe teksty w jednym miejscu.

**Contract**: Klucze w `reports`:
- `period_last_12` „12 miesięcy”;
- `title_last_12` „Raport: ostatnie 12 miesięcy”;
- `window_range` „:from – :through”;
- `result_last_12` „Wynik 12 miesięcy”;
- `coverage_note` (tekst wyżej);
- `no_coverage_title` „Brak budżetu w realizacji w tym okresie”;
- `no_coverage_description` „Raport liczy tylko miesiące budżetu w realizacji. W ostatnich 12 miesiącach nie ma takiego budżetu.”

Na telefonie (22rem) segment mieści się z „12 miesięcy”. Scenariusz `last-12-phone` sprawdza, że cztery segmenty mieszczą się w 22rem. Jeśli nie, etykieta to „12 mies.” jako widoczna i dostępna nazwa (bez osobnego `aria-label`). `SegmentedControl.vue` się nie zmienia.

#### 6. Kitchen sink, fixtures i strażnicy

**File**: `resources/js/lib/reports-fixtures.ts`, `resources/js/pages/dev/ReportsKitchenSink.vue`

**Intent**: Każdy stan okna widać w `/dev/ui/reports` (`.ai/rules/reports.md`).

**Contract**:
- Scenariusze:
  - `last-12-full` (12 pokrytych, kategoria w obu latach);
  - `last-12-partial` (10 pokrytych, notka);
  - `last-12-partial-end` (2 pokryte na początku, brak na końcu);
  - `last-12-no-coverage` (stan pusty);
  - `last-12-no-operations` (pokryte bez operacji);
  - `last-12-phone`.
- Kitchen sink obsługuje `kind: 'last-12'` z tytułem i zakresem, notką i stanem pustym.
- `periodOptions` kitchen sinka ma cztery segmenty.
- Wiersz macierzy stanów opisuje okno.
- Pozostałe scenariusze dostają `window: null`.

**File**: `tests/Unit/ReportsHardcodedUiTest.php`, `scripts/check-ui-literals.mjs`

**Intent**: Nowy komponent jest pilnowany jak reszta raportu.

**Contract**: `ReportCoverageNote.vue` na obu listach.

#### 7. Reguła raportów

**File**: `.ai/rules/reports.md`

**Intent**: Następny agent wie, że okno to `PeriodReport::forWindow` bez porównania i Trendu, z notką pokrycia i linkiem z zakresem okna.

**Contract**:
- Nowy akapit „The last twelve months is `PeriodReport::forWindow`…” (ADR-0081): okno z serwera, bez liczenia dat i pokrycia w kliencie, `ReportCoverageNote`, „Wynik 12 miesięcy”, link `reportRangeOperationsQuery`, zmiana roku prowadzi do raportu roku.
- Zdanie o linku w akapicie o stronie Raportów (`:27`) zmienia zakres z „the report's month or the whole Budget year” na „the report's month, the whole Budget year or the last-12 window through `reportRangeOperationsQuery`”, żeby reguła sobie nie przeczyła.
- Po edycji: `php artisan boost:index-rules`.

#### 8. E2E

**File**: `tests/e2e/reports.spec.ts`

**Intent**: Przejście do okna działa w przeglądarce na prawdziwej dacie.

**Contract**: Nowe testy:
- kliknięcie „12 miesięcy” daje `?view=last-12`, nagłówek „Raport: ostatnie 12 miesięcy” i zakres liczony w teście z dzisiejszej daty UTC, oraz widoczne „Wynik 12 miesięcy” albo tytuł stanu pustego „Brak budżetu w realizacji w tym okresie” (zależnie od seedu bazy E2E), bez „Wynik roku” i bez stanu „draft”. Kwoty zostają przy testach Feature;
- zmiana roku w pasku bocznym z okna prowadzi do raportu roku (`/reports` bez `view`).

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi: `npm run test`
- Typy i build: `npm run build && npm run types:check`
- Strażnicy UI: `npm run check:ui` oraz `php artisan test --compact tests/Unit/ReportsHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php tests/Feature/ReportsKitchenSinkTest.php`
- Testy strony z fazy 1 dalej przechodzą na PostgreSQL: `artisan test --compact tests/Feature/ReportsPageTest.php` (polecenie worktree)
- E2E raportów przechodzi: `npx playwright test tests/e2e/reports.spec.ts`
- Bramka przechodzi: `composer ci:check` (docker) i `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- Na devie z Budżetami 2025 i 2026 segment „12 miesięcy” pokazuje nagłówek z zakresem, panel z „Wynik 12 miesięcy”, wykres i tabele bez kolumn porównania, a kwoty zgadzają się z sumą miesięcy
- Po usunięciu mocka (`demo:seed-previous-year --remove`) notka nazywa brakujące miesiące i liczbę pokrytych
- Kliknięcie nazwy kategorii otwiera Operacje z chipem zakresu „01.11.2025 – 31.10.2026”, a suma widocznych operacji równa się kwocie z raportu
- Zmiana roku w pasku bocznym z okna prowadzi do raportu wybranego roku
- `/dev/ui/reports` pokazuje wszystkie scenariusze okna w jasnym i ciemnym motywie, a scenariusz phone mieści cztery segmenty bez przewijania strony

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka.

---

## Testing Strategy

### Unit Tests:

- Vitest: `reportRangeOperationsQuery` (zakres przez rok, `null` bez operacji, puste pozostałe filtry) i formatowanie etykiety zakresu okna.
- Strażnicy UI (`ReportsHardcodedUiTest`, `check:ui`, `UiTranslationKeysTest`) obejmują nowy komponent i klucze.

### Integration Tests:

- `ReportTwelveMonthWindowTest`: daty okna na granicach miesięcy i lat, pokrycie we wszystkich czterech układach Budżetów, izolacja użytkownika.
- `PeriodReportTest`:
  - równość wyniku okna z sumą `BudgetMonthResult` pokrytych miesięcy;
  - kwota kategorii przez dwa Budżety;
  - granice dat;
  - zwroty netto;
  - znacznik archiwum;
  - grupy i udział;
  - stała liczba zapytań.
- `ReportsPageTest`: kształt `period` i `report` przy `last-12`, Budżet w przygotowaniu, brak Budżetów, ignorowany `month`, `window: null` w innych widokach, izolacja.
- NFR: okno ≤ 2 s przy dwóch Budżetach po 10 000 operacji (PostgreSQL).
- E2E: przejście do okna i zmiana roku z okna.

### Manual Testing Steps:

1. Na devie z mockiem 2025 otwórz Raporty → „12 miesięcy”. Sprawdź zakres listopad 2025 – październik 2026 i porównaj Wynik 12 miesięcy z sumą Wyników z planu miesiąca.
2. Usuń mock (`php artisan demo:seed-previous-year <user> --remove`) i sprawdź notkę: brak listopad–grudzień 2025, 10 z 12 miesięcy.
3. Kliknij nazwę kategorii obecnej w obu latach. Lista Operacji ma zakres całego okna i sumuje się do kwoty z raportu.
4. Wybierz w pasku bocznym inny rok: trafiasz na raport roku tego roku.
5. Przejrzyj `/dev/ui/reports` (scenariusze `last-12-*`, motyw jasny i ciemny, phone).

## Performance Considerations

Okno to co najwyżej dwa Budżety:
- na Budżet jedno zapytanie linii i jedno `forRange` (agregat `GROUP BY budget_line_id`);
- jedno zapytanie Budżetów okna;
- jedno zapytanie grup.

Liczba zapytań nie zależy od liczby kategorii ani miesięcy, a Sloppy nie dostaje zapytań w pętli. Test NFR pilnuje 2 s przy 20 000 operacji w dwóch Budżetach.

## Migration Notes

Bez migracji i bez zmian danych. Produkcja od wdrożenia pokazuje okno z notką (dziś 10 z 12 miesięcy), a pełne pokrycie przychodzi 1.01.2027.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-32, ryzyko „suma Wyników miesięcy”)
- PRD: `context/foundation/prd-v3.md:194-195`, `:222-223`
- ADR-0070 (odczyt okresu), ADR-0074 (porównanie lat), ADR-0077 (Trend), ADR-0071 (wykresy), ADR-0075 (domyślny rok)
- Wzorzec planu i odczytu: `context/archive/2026-11-11-report-trend-by-month/plan.md`, `context/archive/2026-11-12-report-to-operations/plan.md`
- Wspólna ścieżka wierszy: `app/Support/ReportCategoryTree.php:94-114`
- Odczyt zakresu: `app/Support/MonthCategoryActuals.php:67-86`
- Kontroler: `app/Http/Controllers/ReportController.php:21-61`
- Link: `resources/js/lib/report-category-operations.ts:18-43`
- Reguła: `.ai/rules/reports.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Reguły i odczyt okna na serwerze

#### Automated

- [x] 1.1 Testy okna, odczytu i strony przechodzą na SQLite — 81fc0e9
- [x] 1.2 Te same testy przechodzą na PostgreSQL — 81fc0e9
- [x] 1.3 Test NFR okna przechodzi na PostgreSQL — 81fc0e9
- [x] 1.4 Typy kompilują się — 81fc0e9
- [x] 1.5 Bramka przechodzi (ci:check, sloppy, pint) — 81fc0e9

#### Manual

- [x] 1.6 Słownik i ADR-0081 opisują okno, pokrycie, wynik i odrzucone warianty — 81fc0e9
- [x] 1.7 Na devie prop okna ma 12 pokrytych miesięcy i wynik równy sumie Wyników miesięcy — 81fc0e9

### Phase 2: Widok „12 miesięcy” i linki

#### Automated

- [x] 2.1 Vitest przechodzi — dcc9eb1
- [x] 2.2 Typy i build — dcc9eb1
- [x] 2.3 Strażnicy UI — dcc9eb1
- [x] 2.4 Testy strony z fazy 1 dalej przechodzą na PostgreSQL — dcc9eb1
- [x] 2.5 E2E raportów przechodzi — dcc9eb1
- [x] 2.6 Bramka przechodzi (ci:check, sloppy) — dcc9eb1

#### Manual

- [x] 2.7 Na devie z Budżetami 2025 i 2026 okno pokazuje zakres, Wynik 12 miesięcy, wykres i tabele bez porównania — dcc9eb1
- [x] 2.8 Bez mocka notka nazywa brakujące miesiące i liczbę pokrytych — dcc9eb1
- [x] 2.9 Link kategorii otwiera Operacje z zakresem okna i sumą równą raportowi — dcc9eb1
- [x] 2.10 Zmiana roku z okna prowadzi do raportu wybranego roku — dcc9eb1
- [x] 2.11 Kitchen sink pokazuje wszystkie scenariusze okna w obu motywach i na telefonie — dcc9eb1
