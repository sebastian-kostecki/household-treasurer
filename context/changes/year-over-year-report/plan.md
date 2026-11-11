# Rok do roku w Raportach — plan implementacji

## Overview

S-29 z roadmapy M-3 (`context/foundation/prd-v3.md`, FR-016): strona „Raporty” pokazuje przy każdym wierszu, przy sumach, w panelu miar i na wykresie kwotę z tego samego okresu rok wcześniej oraz różnicę. Lata łączy kategoria listy (`categories.id`), nie nazwa. Na produkcji drugi rok pojawi się dopiero w 2027, więc ten wycinek dodaje polecenie, które na devie buduje mock budżetu roku poprzedniego z operacji bieżącego roku (potrzebny też S-30…S-32). Przy okazji naprawia domyślny rok po zalogowaniu, bo przy dwóch latach aplikacja otwiera dziś najstarszy.

## Current State Analysis

- **Odczyt raportu.** `App\Support\PeriodReport` (`app/Support/PeriodReport.php:54-91`) liczy raport jednego budżetu w 4 zapytaniach: `MonthCategoryActuals::forMonths`, linie budżetu z kategorią listy, grupy użytkownika. Wiersze są wewnętrznie kluczowane id linii, a na wyjściu `id` to id kategorii listy (`:160`). Wiersz ma kategoria z co najmniej jedną operacją w okresie (`:68`). Udział liczony tylko dla wydatków (`:182-213`).
- **Kwoty po dniu.** `MonthCategoryActuals::forMonths` (`app/Support/MonthCategoryActuals.php:27-60`) filtruje po `budget_id` i zakresie dat, grupuje po `(budget_line_id, operation_date)` i składa wynik po miesiącach. Nie ma odczytu dla zakresu dat kończącego się w środku miesiąca.
- **Kontroler.** `ReportController::show` (`app/Http/Controllers/ReportController.php:19-57`) bierze budżet z `SelectedBudgetYear`, okres z `?month`, liczy `is_in_progress` (`:52-53`, dziś w UTC) i oddaje `report` tylko dla budżetu w wykonaniu (`:55`).
- **Model między latami.** `budgets` ma `unique(user_id, year)`, `budget_lines` ma `unique(budget_id, category_id)`. Model nie gwarantuje, że rok N−1 ma budżet, że jest on w wykonaniu ani że kategoria ma w nim linię. Grupy, nazwy i kolejność są zawsze dzisiejsze (ADR-0063:15, ADR-0070:9). `archived_at` linii roku N−1 może różnić się od linii roku N i od listy (`app/Actions/ArchiveCategory.php:23-39`).
- **„Poprzedni rok” w kodzie.** `MonthPlanProposal` (`app/Support/MonthPlanProposal.php:52-57`) bierze dokładnie `rok − 1` w wykonaniu; `CreateBudget` (`app/Actions/CreateBudget.php:17-20`) bierze najnowszy wcześniejszy. PRD mówi „rok wcześniej”, więc raport idzie drogą `MonthPlanProposal`.
- **Front.** `Reports.vue` składa `ReportStatPanel`, `ReportBarChart` i `ReportEntryTable` (z `ReportGroupRow`, `ReportCategoryRow`). Siatka `.report-grid` (`resources/css/app.css:1412-1419`) ma 3 kolumny (2 dla przychodów) i żadnych progów szerokości. Wykres to jedna seria `VisGroupedBar` (`resources/js/components/ReportBarChart.vue:70-76`). `StatPanel` ma sloty `footer-<id>` (`resources/js/components/StatPanel.vue:57`).
- **Różnice w aplikacji.** Nie ma komponentu „różnica”. Są `formatSignedMoney` / `formatSignedMoneyAmount` (`resources/js/lib/format.ts:29-42`) i kierunek zmiany planu z tonem według typu kategorii (`resources/js/lib/plan-change-direction.ts:13-29`, ADR-0060). Reguły koloru (`.ai/rules/js.md`) rezerwują `destructive` dla przekroczeń, z wyjątkiem tej strzałki.
- **Wykresy.** `--chart-2..5` (`resources/css/app.css:152-155`, `:227-230`) to domyślne `hsl()` z shadcn, nieużywane; pierwsze użycie wymaga sprawdzonego kontrastu i wpisu w design systemie (ADR-0071:23, `context/foundation/design-system.md:162`).
- **Dane dev.** Baza dev ma prawdziwy budżet 2026 użytkownika z ok. 1 000 operacji z Importu (`context/foundation/prd-v3.md:48`). `DemoHouseholdSeeder` tworzy tylko bieżący rok przez akcje domenowe i nie ma strażnika środowiska; zasila też ręcznie przygotowaną bazę E2E. Jedyny wzorzec strażnika to `app()->isLocal()` w kontrolerach `Dev/*`.
- **Salda.** Żaden odczyt nie odtwarza salda konta z operacji; saldo to licznik `current_balance_minor_units` zmieniany przez `ManageFinancialOperation::applyEffect` (`app/Actions/ManageFinancialOperation.php:276-289`). Operacje wpisane wprost do tabeli nie zmieniają sald.
- **Domyślny rok.** `SelectedBudgetYear::resolve` (`app/Support/SelectedBudgetYear.php:34`) bez roku w sesji zwraca najstarszy rok. Korzystają z niego Budżet, Plan miesiąca, Raporty, powłoka i paleta poleceń.
- **E2E.** `tests/e2e/reports.spec.ts:258` i `tests/e2e/sidebar-year-refresh.spec.ts:96` kończą się asercją `toHaveCount(2)` na segmentach roku, czyli zakładają jeden rok w bazie. CI nie uruchamia E2E.
- **ADR.** Najwyższy numer to 0072.

## Desired End State

Gdy użytkownik ma budżet roku `N − 1` w wykonaniu, raport roku albo miesiąca budżetu `N` pokazuje:

- w tabelach kolumny `N` | `N − 1` | Różnica (i Udział dla wydatków), w wierszach kategorii, grup i „Razem”; na wąskim ekranie wiersz ma drugą linię z kwotą roku wcześniej i różnicą;
- w panelu miar pod każdą kwotą linię „`N − 1`: kwota” i różnicę;
- na wykresie dwa słupki na wiersz (bieżący okres w kolorze typu, rok wcześniej w stonowanym szarym) z legendą;
- dla okresu w toku notkę z zakresem porównania, bo rok wcześniej jest ucięty na tym samym dniu.

Gdy takiego budżetu nie ma, strona wygląda jak przed zmianą.

Na devie `php artisan demo:seed-previous-year {user}` dokłada użytkownikowi budżet roku poprzedniego z operacjami, a `--remove` go usuwa. Po zalogowaniu otwiera się bieżący rok kalendarzowy.

Weryfikacja:

- dla pełnego okresu kwoty roku wcześniej są równe raportowi budżetu `N − 1` za ten okres (kategoria = wykonanie linii, miesiąc = Month Result);
- dla okresu w toku kwoty roku wcześniej są równe sumie operacji do tego samego dnia;
- suma grupy = suma jej wierszy w obu kolumnach, `różnica = bieżący − rok wcześniej` w każdym wierszu i sumie;
- test `nfr`: raport z porównaniem ≤ 2 s przy 10 000 operacji w każdym z dwóch lat;
- kitchen sink `/dev/ui/reports` ze stanami porównania; `composer ci:check`, `vendor/bin/sloppy ci`.

### Key Discoveries:

- Drugi rok to ten sam odczyt na drugim budżecie plus złączenie po `category_id`; `PeriodReport` już oddaje `id` kategorii listy (`app/Support/PeriodReport.php:160`).
- Cięcie po dniu wymaga tylko innej granicy dat w zapytaniu, które już filtruje po `operation_date` (`app/Support/MonthCategoryActuals.php:36-41`).
- Tabela przełączana po szerokości własnej karty ma gotowy wzorzec: `.category-meter-table` z `container-type: inline-size` i `@container (min-width: 38rem)` (`resources/css/app.css:1772-1805`), w jednym markupie.
- Ton „korzystna / niekorzystna” ma gotowe typy i klasę: `PlanChangeTone` i `planChangeToneClass(tone, muted)` (`resources/js/lib/plan-change-direction.ts:1-6`, `:29`).
- Licznik wykonania aktualizuje tylko `ManageFinancialOperation`, więc testy równości tworzą operacje tą akcją (helper `bookReportOperation`, `tests/Feature/PeriodReportTest.php:63-77`), a mock ustawia licznik sam.
- `BudgetLineFactory` dla tej samej nazwy u tego samego użytkownika zwraca tę samą kategorię listy (`database/factories/BudgetLineFactory.php:38-58`), więc testy dwóch lat łączą się po id bez dodatkowej pracy; helper `reportLine` (`tests/Feature/PeriodReportTest.php:40-46`) przyjmuje `category_id`.

## What We're NOT Doing

- Procentu zmiany rok do roku. Różnica to sama kwota (decyzja użytkownika).
- Przełącznika „porównaj”. Porównanie jest zawsze widoczne, gdy jest z czym porównać.
- Porównania z rokiem innym niż dokładnie `N − 1` ani z budżetem w przygotowaniu.
- Cięcia bieżącego okresu na dziś. Kolumna bieżąca zostaje równa kafelkom i Budżetowi, także z operacjami o przyszłej dacie.
- Wykresu różnic, trendu miesięcy (S-30), przejścia do Operacji (S-31), okresu „ostatnie 12 miesięcy” (S-32).
- Importu arkusza `Budżet_2025.xlsx` ani dopasowania kategorii po nazwie. Kwoty mocka nie są prawdziwymi kwotami 2025.
- Mocka w łańcuchu `DatabaseSeeder` i w `demo:seed-household`. `DatabaseSeederTest` i podgląd worktree zostają przy jednym roku.
- Transferów własnych, ruchów kapitału i planów miesięcy w mocku.
- Zmian schematu bazy i snapshotu (`user:backup`).
- Zmian `ui/chart/**` i dodatkowej biblioteki wykresów.
- Zmiany reguły wierszy grupy w Budżecie ani odtwarzania dawnych grup w raporcie (ADR-0063).

## Implementation Approach

Najpierw reguły na papierze (słownik, ADR-0073), potem odczyt z testami równości i propsy strony. Druga faza daje dane do oglądania: mock roku poprzedniego i poprawny domyślny rok. Dopiero wtedy tabela i panel miar, a na końcu druga seria wykresu z nowym tokenem i E2E. Serwer liczy wszystkie kwoty i różnice w minor units; klient formatuje, koloruje i rysuje.

Numery ADR: 0073 (porównanie rok do roku) i 0074 (domyślny rok budżetu). Przed zapisem sprawdź, czy równoległa zmiana `irregular-expense-collected` ich nie zajęła; jeśli tak, weź kolejne wolne i popraw odsyłacze.

## Critical Implementation Details

**Reguły porównania (ustalone z użytkownikiem, zapisywane w ADR-0073):**

- **Z czym porównujemy.** Z budżetem tego samego użytkownika dla roku `N − 1` w fazie wykonania. Brak takiego budżetu oznacza brak porównania (`comparison: null`) i stronę jak przed zmianą.
- **Zakres roku wcześniej.** Okres zakończony albo przyszły: cały odpowiednik (cały rok `N − 1` albo cały miesiąc M roku `N − 1`). Okres w toku (`is_in_progress`): od początku odpowiednika do dnia `dziś − 1 rok` bez przepełnienia (29 lutego daje 28 lutego). `is_partial` jest prawdą tylko wtedy, gdy ten dzień jest wcześniejszy niż koniec odpowiednika. „Dziś” jest tym samym `CarbonImmutable::today('UTC')`, którego kontroler używa dla `is_in_progress`.
- **Wiersze.** Kategoria ma wiersz, gdy ma co najmniej jedną operację w bieżącym okresie albo w zakresie roku wcześniej. Strona bez operacji pokazuje „—”. Grupa ma wiersz, gdy ma co najmniej jeden taki wiersz. Bez porównania reguła wierszy jest jak w ADR-0070.
- **Różnica.** `bieżący − rok wcześniej`, a brak operacji liczy się jako 0. Przykład z planowania: Prezenty „—” | 1 500 | −1 500; Subskrypcje AI 300 | „—” | +300; Ubrania 5 900 | 6 547 | −647.
- **Ton różnicy.** Korzystna: mniej wydatków, więcej przychodów, wyższy wynik (zielony). Niekorzystna: odwrotnie (czerwony). Zero i wiersze zarchiwizowane bez tonu.
- **Udział.** Liczony jak dotąd od wydatków bieżącego okresu; wiersz bez operacji w bieżącym okresie ma `null` („—”).
- **Znacznik „zarchiwizowana”.** Z linii wybranego budżetu (jak dotąd); gdy kategoria nie ma w nim linii, z `archived_at` kategorii listy.
- **Puste stany.** Stan „brak operacji w okresie” pokazuje się, gdy żaden z dwóch zakresów nie ma operacji. Bieżący okres bez operacji przy operacjach rok wcześniej pokazuje tabele z „—” w kolumnie bieżącej.

**Wydajność.** Porównanie dodaje stałą liczbę zapytań: budżet `N − 1`, jego linie z kategorią i jeden odczyt zakresu. Żadnego zapytania w pętli po miesiącach ani kategoriach (Sloppy). Test `nfr` obejmuje dwa lata.

**Mock a salda.** Mock wpisuje operacje wprost do tabeli i nie dotyka kont, więc salda zostają bez zmian. Licznik `execution_minor_units` linii mocka ustawia sam na sumę swoich operacji. Wszystkie przeliczenia kwot są całkowite (`intdiv`), bez `float`.

## Phase 1: Reguły i odczyt porównania na serwerze

### Overview

Zapisuje reguły w słowniku i ADR-0073, dodaje odczyt po zakresie dat, rozszerza `PeriodReport` o porównanie i podaje je stronie jako część propsa `report`. Po tej fazie dane porównania są w propsach i udowodnione testami, a strona wygląda jak dotąd.

### Changes Required:

#### 1. Słownik i ADR

**File**: `context/foundation/glossary.md`, `context/foundation/adr/0073-period-report-year-over-year.md`

**Intent**: Nazwać porównanie i zmianę reguły wierszy, zanim powstanie kod (AGENTS.md). ADR zapisuje decyzje z „Critical Implementation Details” z uzasadnieniem i odrzuconymi opcjami: cały okres dla okresu w toku, cięcie na końcu miesiąca, wiersze tylko z bieżącego okresu, wiersz zbiorczy, procent zmiany, brak koloru, przełącznik porównania, wykres różnic.

**Contract**:
- Wpis **Period Report** dostaje akapit o porównaniu: rok wcześniej to Budget roku `N − 1` w wykonaniu, zakres dla okresu w toku, reguła wierszy z dwóch zakresów, różnica i jej znak. Polskie teksty UI: „Różnica”, nagłówki kolumn to lata. _Avoid_ dopisuje: „zmiana %”, łączenie lat po nazwie.
- Przy okazji usuwa z wpisu nieaktualne „one-off” (flaga usunięta w ADR-0072).
- ADR-0073 w formacie sąsiednich ADR, z odsyłaczami do ADR-0060 (ton według korzyści), ADR-0063, ADR-0070 i ADR-0071. Zapisuje też: rozszerzenie znaczenia `success` / `destructive` na różnicę rok do roku, token `--chart-2` jako seria „rok wcześniej” oraz układ tabeli przełączany po szerokości karty.

#### 2. Odczyt po zakresie dat

**File**: `app/Support/MonthCategoryActuals.php`

**Intent**: Dać kwoty linii budżetu dla dowolnego zakresu dni w roku budżetu, bo rok wcześniej dla okresu w toku kończy się w środku miesiąca.

**Contract**: `MonthCategoryActuals::forRange(Budget $budget, CarbonImmutable $from, CarbonImmutable $through): self` (oba dni włącznie), jedno zapytanie grupowane po `budget_line_id`, z tymi samymi akcesorami (`actual`, `hasOperations`). `forMonths` zostaje bez zmian.

#### 3. Zakres porównania

**File**: `app/Support/ReportComparisonPeriod.php` (nowy, `php artisan make:class`)

**Intent**: Jedno miejsce, które z wybranego budżetu, okresu i „dziś” wyznacza budżet roku wcześniej i zakres dat, tak aby `PeriodReport` nie znał zegara.

**Contract**:
- `ReportComparisonPeriod::resolve(Budget $budget, ?int $month, CarbonImmutable $today): ?self`; `null`, gdy użytkownik nie ma budżetu `year − 1` w fazie wykonania.
- Pola tylko do odczytu: `budget` (budżet roku wcześniej), `from`, `through`, `isPartial`, według reguły „Zakres roku wcześniej”.

#### 4. Porównanie w raporcie

**File**: `app/Support/PeriodReport.php`

**Intent**: Dołączyć do raportu kwoty roku wcześniej po id kategorii listy, policzyć różnice i sumy, zachowując dotychczasowe kwoty bieżącego okresu co do grosza.

**Contract**:
- `forMonth(Budget $budget, int $month, ?ReportComparisonPeriod $comparison = null)` i `forYear(Budget $budget, ?ReportComparisonPeriod $comparison = null)`.
- Bieżący okres dalej czyta `forMonths`; rok wcześniej czyta `forRange`.
- Kształt `toArray()`, od którego zależą fazy 3 i 4:

```php
/**
 * ReportShape += comparison: ReportComparison|null
 * ReportComparison = array{
 *   year: int, from: string, through: string, is_partial: bool,   // daty 'Y-m-d'
 *   income_minor_units: int, expense_minor_units: int, result_minor_units: int,
 *   income_difference_minor_units: int, expense_difference_minor_units: int,
 *   result_difference_minor_units: int,
 * }
 * Każdy wiersz (kategoria i grupa) += array{
 *   has_operations: bool,               // operacje w bieżącym okresie
 *   previous_minor_units: int|null,     // null: brak porównania albo brak operacji w zakresie roku wcześniej
 *   difference_minor_units: int|null,   // null tylko bez porównania
 * }
 */
```

- Grupa: `has_operations`, gdy ma je którykolwiek członek; `previous_minor_units` to suma członków albo `null`, gdy żaden nie ma operacji rok wcześniej.
- `has_operations` raportu znaczy „raport ma co najmniej jeden wiersz”.
- Bez porównania wynik jest taki jak dziś, z `comparison: null`, `has_operations: true` w każdym wierszu i `null` w obu nowych polach kwot.

#### 5. Propsy strony i typy

**File**: `app/Http/Controllers/ReportController.php`, `resources/js/types/reports.ts`, `resources/js/lib/reports-fixtures.ts`

**Intent**: Podać porównanie stronie w ramach propsa `report` i utrzymać lustro typów w tym samym commicie (AGENTS.md).

**Contract**:
- Kontroler wywołuje `ReportComparisonPeriod::resolve` z tym samym `$today` i `$month` i przekazuje wynik do `PeriodReport`.
- `types/reports.ts`: `PeriodReportComparison`, nowe pola wierszy, `comparison` w `PeriodReportData`.
- Istniejące fixtures dostają nowe pola z wartościami „bez porównania”, żeby typy się zgadzały; scenariusze porównania dochodzą w fazie 3.

#### 6. Testy odczytu, strony i wydajności

**File**: `tests/Feature/PeriodReportTest.php`, `tests/Feature/ReportComparisonPeriodTest.php` (nowy), `tests/Feature/ReportsPageTest.php`, `tests/Feature/Nfr/NfrPerfReportTest.php`

**Intent**: Udowodnić reguły porównania i równość z odczytami roku wcześniej; przypadki opisuje „Testing Strategy”.

**Contract**: Przypadki równości tworzą operacje przez `ManageFinancialOperation`. `NfrPerfReportTest` dostaje drugi budżet (rok wcześniej, w wykonaniu, te same kategorie listy) z 10 000 operacji i mierzy rok w toku oraz miesiąc.

### Success Criteria:

#### Automated Verification:

- Testy odczytu i zakresu przechodzą na SQLite: `php artisan test --compact tests/Feature/PeriodReportTest.php tests/Feature/ReportComparisonPeriodTest.php tests/Feature/ReportsPageTest.php`
- Te same testy przechodzą na PostgreSQL (worktree): `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature/PeriodReportTest.php tests/Feature/ReportComparisonPeriodTest.php tests/Feature/ReportsPageTest.php`
- Test wydajności raportu z dwoma latami przechodzi na PostgreSQL: `docker run … --entrypoint php sail-8.5/app artisan test --group=nfr --configuration=phpunit.nfr.xml --filter=NfrPerfReportTest`
- Typy i build: `npm run build && npm run types:check`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- ADR-0073 i wpis Period Report w słowniku czyta się zgodnie z decyzjami tego planu (ten sam dzień dla okresu w toku, wiersze z dwóch zakresów, różnica i jej kolor)

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Phase 2: Mock roku poprzedniego i domyślny rok

### Overview

Dodaje polecenie, które na devie buduje budżet roku poprzedniego z operacji bieżącego, oraz poprawia rok otwierany bez wyboru w sesji. Po tej fazie baza dev ma dwa lata, a aplikacja nadal otwiera bieżący.

### Changes Required:

#### 1. Mock roku poprzedniego

**File**: `database/seeders/PreviousYearMockSeeder.php` (nowy), `app/Console/Commands/SeedPreviousYearCommand.php` (nowy, `php artisan make:command`)

**Intent**: Dołożyć wskazanemu użytkownikowi budżet roku poprzedniego w wykonaniu, z operacjami na tych samych kategoriach listy, bez zmiany sald i z możliwością usunięcia. Wzorzec pary polecenie + seeder jak `SeedDemoHouseholdCommand` i `DemoHouseholdSeeder`.

**Contract**:
- Sygnatura: `demo:seed-previous-year {user} {--remove} {--force}`. Polecenie odmawia poza środowiskami `local` i `testing`. Nie wchodzi do `DatabaseSeeder`.
- **Źródło i cel.** Źródło to najnowszy budżet użytkownika w wykonaniu, który ma operacje bez znacznika mocka; cel to `rok źródła − 1`. Polecenie odmawia, gdy budżet celu już istnieje albo gdy źródła nie ma.
- **Budżet celu.** Faza wykonania; jedna linia na każdą linię źródła z tym samym `category_id`, planem, planem początkowym i `archived_at`; `execution_minor_units` równe sumie operacji mocka tej linii.
- **Operacje.** Każda operacja finansowa źródła daje jedną operację celu: ten sam typ, konto, opis i kontrahent, data o rok wcześniej (bez przepełnienia), bez pól tożsamości importu.
- **Kwota.** Stały procent na kategorię z przedziału 85–115, wyliczony z id kategorii listy, liczony całkowicie z zaokrągleniem od połowy od zera i zachowaniem znaku (zwrot zostaje zwrotem).
- **Ogon roku.** Dni po ostatniej operacji źródła do 31 grudnia wypełniają kopie operacji z okna tej samej długości tuż przed tym dniem (okno nie wychodzi przed 1 stycznia), przesunięte na ogon.
- **Znacznik.** Każda operacja mocka ma `raw_statement_description` równe stałej seedera.
- **`--remove`.** Usuwa budżety użytkownika, w których wszystkie operacje mają znacznik (operacje, plany miesięcy, linie, budżet). Odmawia dla budżetu z choć jedną operacją bez znacznika.
- Konta, transfery własne i ruchy kapitału zostają nietknięte. Wynik jest powtarzalny: ponowne uruchomienie po `--remove` daje te same kwoty.

#### 2. Dokumentacja polecenia

**File**: `README.md`

**Intent**: Opisać polecenie obok `demo:seed-household` (sekcja „Uruchomienie” i tabela poleceń), z uwagą, że działa tylko lokalnie, a kwoty roku poprzedniego są pochodną bieżących, nie prawdziwymi danymi.

**Contract**: wiersz w tabeli poleceń i krok opcjonalny po `demo:seed-household`; w części o E2E zdanie, że baza `household_treasurer_e2e` dostaje mock raz, tym samym poleceniem.

#### 3. Domyślny rok budżetu

**File**: `context/foundation/adr/0074-default-budget-year-is-the-current-one.md`, `context/foundation/glossary.md`, `app/Support/SelectedBudgetYear.php`

**Intent**: Bez wyboru w sesji otwierać rok, w którym użytkownik żyje, a nie najstarszy. Dotyczy Budżetu, Planu miesiąca, Raportów, powłoki i palety poleceń.

**Contract**:
- Kolejność: rok z sesji, jeśli ma budżet (bez zmian); inaczej bieżący rok kalendarzowy, jeśli ma budżet; inaczej najnowszy rok nie późniejszy niż bieżący; inaczej najstarszy.
- Przykłady z planowania: `[2025, 2026]` w 2026 → 2026; `[2026, 2027 w przygotowaniu]` w grudniu 2026 → 2026; `[2025, 2026]` w styczniu 2027 → 2026; `[2027, 2028]` w 2026 → 2027.
- „Dziś” w UTC, jak w `ReportController`.
- Wpis **Budget** w słowniku dostaje jedno zdanie o domyślnym roku z odsyłaczem do ADR-0074; ADR odnotowuje związek z ADR-0031 (Dashboard zawsze pokazuje bieżący rok).

#### 4. Testy mocka i domyślnego roku

**File**: `tests/Feature/SeedPreviousYearCommandTest.php` (nowy), `tests/Feature/SelectedBudgetYearTest.php` (nowy)

**Intent**: Udowodnić kontrakt mocka (spójność, brak wpływu na salda, znacznik, odmowy) i regułę domyślnego roku; przypadki opisuje „Testing Strategy”.

**Contract**: test mocka buduje dane źródła przez `DemoHouseholdSeeder` i uruchamia się także na PostgreSQL (wstawianie wsadowe, daty).

### Success Criteria:

#### Automated Verification:

- Testy mocka i domyślnego roku przechodzą na SQLite: `php artisan test --compact tests/Feature/SeedPreviousYearCommandTest.php tests/Feature/SelectedBudgetYearTest.php tests/Feature/BudgetTest.php tests/Feature/DatabaseSeederTest.php`
- Te same testy przechodzą na PostgreSQL (worktree): `docker run … --entrypoint php sail-8.5/app artisan test --compact tests/Feature/SeedPreviousYearCommandTest.php tests/Feature/SelectedBudgetYearTest.php tests/Feature/BudgetTest.php`
- Pełna bramka w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- Na bazie dev `demo:seed-previous-year` dokłada budżet 2025; salda kont na stronie Konta są takie same jak przed uruchomieniem
- Budżet 2025 pokazuje wykonanie przy kategoriach, a po świeżym zalogowaniu Budżet, Plan miesiąca i Raporty otwierają 2026
- `--remove` usuwa budżet 2025 i przełącznik roku wraca do jednego roku; ponowne uruchomienie odtwarza te same kwoty

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Phase 3: Tabela i panel miar

### Overview

Pokazuje porównanie w tabelach i w panelu miar: kolumny roku wcześniej i różnicy, druga linia na wąskim ekranie, ton według korzyści, notka o okresie w toku. Uzupełnia kitchen sink i reguły. Po tej fazie porównanie jest czytelne bez wykresu.

### Changes Required:

#### 1. Ton różnicy

**File**: `resources/js/lib/report-difference.ts` (nowy, z `report-difference.test.ts`)

**Intent**: Jedna funkcja, która z różnicy i rodzaju miary daje ton, tak aby tabela i panel miar kolorowały tak samo.

**Contract**: `reportDifferenceTone(differenceMinorUnits: number | null, kind: 'income' | 'expense' | 'result'): PlanChangeTone | null`; `null` dla zera i braku. Klasę daje istniejące `planChangeToneClass(tone, muted)` z `@/lib/plan-change-direction`, z `muted` dla wiersza zarchiwizowanego.

#### 2. Siatka tabeli z porównaniem

**File**: `resources/css/app.css`

**Intent**: Jeden markup na każdej szerokości: od progu szerokości karty wszystkie kolumny w jednej linii, poniżej druga linia wiersza z rokiem wcześniej i różnicą.

**Contract**:
- Kontener tabeli z `container-type: inline-size` i warianty siatki z porównaniem (z Udziałem i bez) na `grid-template-areas`, wzorem `.category-meter-table` i `.category-meter-row`.
- Poniżej progu: linia 1 to Kategoria, kwota bieżąca i Udział; linia 2 to kwota roku wcześniej poprzedzona etykietą roku oraz różnica. Nagłówki kolumn roku wcześniej i różnicy oraz etykieta roku w wierszu przełączają się tym samym progiem.
- Próg dobrany tak, by pięć kolumn mieściło się w karcie Raportów obok sidebara przy oknie 1280 px, a ramka telefonu z kitchen sinka (22rem) była dwuliniowa; uzasadnienie w komentarzu przy klasie.
- Kolumny rosną wagami `fr` (ADR-0061). Siatki bez porównania zostają bez zmian.

#### 3. Tabela i wiersze

**File**: `resources/js/components/ReportEntryTable.vue`, `resources/js/components/ReportGroupRow.vue`, `resources/js/components/ReportCategoryRow.vue`

**Intent**: Dodać komórki roku wcześniej i różnicy w wierszach kategorii, grup i „Razem”, gdy raport ma porównanie.

**Contract**:
- `ReportEntryTable` dostaje rok budżetu oraz, opcjonalnie, rok porównania z sumą roku wcześniej i różnicą sumy dla swojego typu. Z porównaniem nagłówek kwoty to rok budżetu, a dwie nowe kolumny to rok wcześniej i „Różnica”.
- Kwota bieżąca pokazuje „—”, gdy wiersz nie ma `has_operations`; kwota roku wcześniej pokazuje „—” dla `null`.
- Kwoty przez `formatMoneyAmount`, różnica przez `formatSignedMoneyAmount`, w stylu `amount` (`amount-sum` w grupie i „Razem”).
- Ton różnicy z `reportDifferenceTone`; wiersz zarchiwizowany zostaje cały `muted`.
- Bez porównania komponenty renderują to samo co dziś.

#### 4. Panel miar i notka

**File**: `resources/js/components/ReportStatPanel.vue`, `resources/js/pages/Reports.vue`

**Intent**: Pokazać pod każdą miarą kwotę roku wcześniej i różnicę oraz powiedzieć, z jakim zakresem porównywany jest okres w toku.

**Contract**:
- `ReportStatPanel` dostaje opcjonalne `comparison` i wypełnia sloty `footer-<id>` panelu: linia „rok: kwota” (`formatMoney`, styl `meta`, `muted-foreground`) i różnica (`formatSignedMoney`) z tonem według rodzaju miary. Podpis „okres w toku” zostaje.
- `Reports.vue` przekazuje rok i porównanie do panelu i tabel. Gdy `comparison.is_partial`, pod panelem miar stoi `<Alert variant="note">` z rokiem i zakresem dat porównania (daty przez istniejący formater z `@/lib/format`).
- Stan „brak operacji w okresie” zależy od `report.has_operations` w nowym znaczeniu.

#### 5. Teksty

**File**: `lang/pl/ui.php`

**Intent**: Nowe teksty w bloku `reports.*`.

**Contract**: klucze dla „Różnica”, etykiety kwoty roku wcześniej (z `:year`), notki o okresie w toku (z `:year`, `:from`, `:through`) i legendy wykresu (używane w fazie 4). `UiTranslationKeysTest` pilnuje zgodności kluczy.

#### 6. Kitchen sink, strażnicy i reguły

**File**: `resources/js/lib/reports-fixtures.ts`, `resources/js/pages/dev/ReportsKitchenSink.vue`, `.ai/rules/reports.md`, `context/foundation/design-system.md`

**Intent**: Każdy nowy stan w `/dev/ui/reports`, a reguły i design system opisują tabelę z porównaniem i nowe znaczenie koloru.

**Contract**:
- Nowe scenariusze: rok w toku z porównaniem i notką; miesiąc zamknięty z porównaniem; kategoria tylko rok wcześniej i tylko w bieżącym okresie; grupa z samymi kategoriami z roku wcześniej; zarchiwizowana kategoria z porównaniem; bieżący okres bez operacji przy operacjach rok wcześniej; telefon z porównaniem. Dotychczasowe scenariusze zostają jako „bez porównania”.
- Macierz stanów dostaje wiersz o porównaniu.
- `.ai/rules/reports.md`: zdanie o kolumnach i regule „porównanie liczy serwer, łączenie po kategorii listy”. Potem `php artisan boost:index-rules`, jeśli zmieniły się `paths:`.
- `design-system.md`: w sekcji o kolorze dopisek, że `success` / `destructive` oznaczają też różnicę rok do roku według korzyści (ADR-0073); przy tabelach opis układu Raportów z drugą linią.

### Success Criteria:

#### Automated Verification:

- Testy jednostkowe frontu przechodzą: `npm run test -- resources/js/lib/report-difference.test.ts`
- Wayfinder, build i typy: `npm run build && npm run types:check`
- Strażnik literałów UI: `npm run check:ui`
- Testy strony i strażników przechodzą na PostgreSQL: `docker run … --entrypoint php sail-8.5/app artisan test --compact tests/Feature/ReportsPageTest.php tests/Feature/ReportsKitchenSinkTest.php tests/Unit/ReportsHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php`
- Pełna bramka w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check`

#### Manual Verification:

- Na devie z mockiem raport roku 2026 pokazuje kolumny 2026 | 2025 | Różnica | Udział; „Razem” w kolumnie 2025 raportu zamkniętego miesiąca równa się „Razem” raportu tego miesiąca w 2025
- Rok i miesiąc w toku pokazują notkę z zakresem do dzisiejszego dnia rok wcześniej; okres zakończony jej nie ma
- Różnica jest zielona przy mniejszych wydatkach i większych przychodach, czerwona odwrotnie; zero i wiersze zarchiwizowane bez koloru
- Kafelki pokazują pod kwotą rok wcześniej i różnicę; raport 2025 (bez roku 2024) wygląda jak przed zmianą
- Na telefonie wiersz ma dwie linie i strona nie przewija się w poziomie; kitchen sink `/dev/ui/reports` pokazuje wszystkie scenariusze w trybie jasnym i ciemnym

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Phase 4: Wykres i E2E

### Overview

Dodaje drugą serię wykresu z nowym tokenem koloru i legendą, dokumentuje ją w design systemie, dodaje test E2E porównania i uniezależnia dwa istniejące testy od liczby lat w bazie.

### Changes Required:

#### 1. Token serii „rok wcześniej”

**File**: `resources/css/app.css`, `context/foundation/design-system.md`, `.ai/rules/reports.md`

**Intent**: Nadać `--chart-2` stonowaną, neutralną wartość Atrament w obu trybach, bo kolor nadal znaczy typ, a rok wcześniej ma być tłem dla bieżącego.

**Contract**:
- `--chart-2` w hex w `:root` i `.dark`, z kontrastem co najmniej 3:1 wobec `card` w obu trybach i wyraźnie odróżnialne od `--chart-1` i `--success`.
- Sekcja „Wykresy” w design systemie dostaje wiersz roli `chart-2` z policzonym kontrastem; zdanie „`chart-2..5` są nieużywane” zmienia się na `chart-3..5`.
- `.ai/rules/reports.md`: zdanie o kolorach wykresu dopisuje rok wcześniej jako `--chart-2`.

#### 2. Dane słupków

**File**: `resources/js/lib/report-chart.ts` (z `report-chart.test.ts`)

**Intent**: Dać wykresowi obie wartości na wiersz z tych samych `entries` co tabela.

**Contract**: `ReportChartBar` dostaje wartość roku wcześniej (`max(0, kwota)` albo `null` bez porównania). Wiersz ma słupki, gdy którakolwiek z dwóch wartości jest dodatnia; pusta lista, gdy żadna.

#### 3. Dwie serie i legenda

**File**: `resources/js/components/ReportBarChart.vue`, `resources/js/pages/Reports.vue`, `resources/js/pages/dev/ReportsKitchenSink.vue`

**Intent**: Przy porównaniu dwa poziome słupki na wiersz, bieżący okres nad rokiem wcześniej, z legendą lat.

**Contract**:
- Props dochodzą: rok budżetu i opcjonalny rok porównania.
- `VisGroupedBar` dostaje dwa akcesory wartości i kolor według indeksu serii: bieżący okres w kolorze typu (`--chart-1` albo `--success`), rok wcześniej `--chart-2`. Wysokość pasma wiersza rośnie tak, by oba słupki były czytelne; skala osi obejmuje większą z dwóch wartości.
- Legenda z dwiema próbkami i latami stoi przy wykresie tylko z porównaniem; cały wykres zostaje `aria-hidden`.
- Bez porównania wykres jest jak dziś. `ui/chart/**` nie jest edytowane.
- Scenariusze kitchen sinka z fazy 3 pokazują wykres z dwiema seriami.

#### 4. Testy E2E

**File**: `tests/e2e/reports.spec.ts`, `tests/e2e/sidebar-year-refresh.spec.ts`

**Intent**: Smoke porównania w przeglądarce na bazie z mockiem oraz usunięcie założenia „jeden rok w bazie”.

**Contract**:
- Nowy test: na „Raportach” bieżącego roku widać nagłówki kolumn obu lat i „Różnica”, linię roku wcześniej w panelu miar, legendę wykresu z dwoma latami; po zmianie roku na poprzedni kolumn porównania nie ma. Wymaga mocka w bazie E2E (komentarz w nagłówku pliku, krok w README z fazy 2).
- Asercje `toHaveCount(2)` na segmentach roku w obu plikach stają się porównaniem z liczbą segmentów zapamiętaną na początku testu.

### Success Criteria:

#### Automated Verification:

- Test danych wykresu przechodzi: `npm run test -- resources/js/lib/report-chart.test.ts`
- Wayfinder, build i typy: `npm run build && npm run types:check`
- Strażnik literałów UI: `npm run check:ui`
- Testy E2E przechodzą na bazie z mockiem: `npx playwright test tests/e2e/reports.spec.ts tests/e2e/sidebar-year-refresh.spec.ts`
- Pełna bramka w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- Na devie z mockiem każdy wiersz wykresu ma dwa słupki zgodne z kolumnami tabeli pod nim, a legenda pokazuje oba lata
- Słupek roku wcześniej jest czytelny w trybie jasnym i ciemnym i nie myli się z kolorem typu
- Na telefonie wykres mieści się w szerokości ekranu; raport bez porównania ma wykres jak przed zmianą

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Testing Strategy

### Unit Tests:

- `reportDifferenceTone`: wydatki −647 → korzystna, +657 → niekorzystna; przychody +3 000 → korzystna, −1 → niekorzystna; wynik +4 100 → korzystna; 0 i `null` → `null`.
- `reportChartBars` z porównaniem: obie wartości; wiersz tylko z rokiem wcześniej ma słupek; kwoty ≤ 0 dają 0; brak dodatnich daje pustą listę; bez porównania wynik jak dotąd.

### Integration Tests:

`tests/Feature/PeriodReportTest.php` (SQLite i PostgreSQL), nowe przypadki:
- Bez porównania wynik jest taki jak przed zmianą, z `comparison: null` i nowymi polami w stanie „brak”.
- Pełny rok: operacje w dwóch budżetach przez `ManageFinancialOperation`; `previous_minor_units` każdej kategorii = `execution_minor_units` jej linii w roku wcześniej; sumy `comparison` = sumy `PeriodReport::forYear` budżetu roku wcześniej.
- Pełny miesiąc: `comparison.result_minor_units` = `BudgetMonthResult` tego miesiąca w roku wcześniej.
- Okres w toku: operacje rok wcześniej przed dniem cięcia, w dniu cięcia i po nim; liczą się tylko dwie pierwsze, dla roku i dla miesiąca.
- Wiersze: przykład z planowania (Ubrania 5 900 | 6 547 | −647; Prezenty bez operacji | 1 500 | −1 500 z `has_operations: false` i udziałem `null`; Subskrypcje AI 300 | `null` | +300); suma grupy 6 200 | 8 047 | −1 847.
- Grupa z samymi kategoriami z roku wcześniej ma wiersz; grupa bez operacji w obu zakresach nie ma.
- Łączenie po id: kategoria przemianowana między latami łączy się w jeden wiersz; dwie różne kategorie o podobnej nazwie nie.
- Znacznik archiwum dla kategorii bez linii w wybranym budżecie pochodzi z listy; z linią pochodzi z linii.
- `forRange` dla pełnego miesiąca i pełnego roku = `forMonths`.
- Stała liczba zapytań z porównaniem (2 i 24 kategorie).

`tests/Feature/ReportComparisonPeriodTest.php`:
- Brak budżetu `N − 1` i budżet `N − 1` w przygotowaniu dają `null`; budżet innego użytkownika nie jest brany.
- Zakresy: rok zakończony i przyszły miesiąc → cały odpowiednik, `is_partial: false`; rok w toku 11 listopada → 1 stycznia–11 listopada; miesiąc w toku → 1–11 listopada; ostatni dzień okresu → `is_partial: false`; 29 lutego → 28 lutego.

`tests/Feature/ReportsPageTest.php`:
- `assertInertia` na `report.comparison` i nowe pola wierszy dla roku i miesiąca (z `travelTo`).
- Bez budżetu `N − 1` `report.comparison` jest `null`.
- Izolacja: budżet roku wcześniej innego użytkownika nie zasila porównania.

`tests/Feature/SeedPreviousYearCommandTest.php` (SQLite i PostgreSQL):
- Po uruchomieniu istnieje budżet `rok − 1` w wykonaniu; każda linia ma `execution_minor_units` = suma jej operacji; każda operacja ma datę w roku celu, znacznik i puste pola tożsamości importu.
- Salda wszystkich kont, transfery i ruchy kapitału są bez zmian.
- Kwota operacji mieści się w 85–115% źródła, znak zachowany; dwie operacje tej samej kategorii mają ten sam procent.
- Ogon roku ma operacje, gdy źródło kończy się przed grudniem.
- `PeriodReport` budżetu źródła z porównaniem ma `comparison` różne od `null`.
- Odmowy: środowisko `production`; budżet celu już istnieje; brak budżetu w wykonaniu.
- `--remove` usuwa mock i zostawia źródło; odmawia dla budżetu z operacją bez znacznika; ponowne uruchomienie daje te same sumy.

`tests/Feature/SelectedBudgetYearTest.php`: cztery przykłady z kontraktu (z `travelTo`), rok z sesji ma pierwszeństwo, rok z sesji bez budżetu jest pomijany, brak budżetów daje `null`.

`NfrPerfReportTest` (grupa `nfr`): rok w toku i miesiąc z porównaniem ≤ 2 s przy 10 000 operacji w każdym z dwóch lat.

E2E: przypadki z fazy 4.

### Manual Testing Steps:

1. Na devie uruchom `demo:seed-previous-year` dla swojego użytkownika; sprawdź, że salda na stronie Konta się nie zmieniły.
2. Zaloguj się od nowa i sprawdź, że Budżet, Plan miesiąca i Raporty otwierają 2026.
3. Otwórz raport roku 2026: notka o okresie w toku, kolumny obu lat, różnice z kolorem, kafelki z rokiem wcześniej.
4. Otwórz zamknięty miesiąc 2026 i porównaj kolumnę 2025 z raportem tego miesiąca po przełączeniu roku na 2025.
5. Otwórz raport 2025: brak kolumn porównania.
6. Sprawdź wykres (dwa słupki, legenda) i widok na szerokości telefonu, w trybie jasnym i ciemnym.
7. Uruchom `demo:seed-previous-year --remove` i sprawdź, że raport 2026 wraca do wyglądu sprzed zmiany.

## Performance Considerations

Budżet to 2 s dla raportu roku przy 10 000 operacji (`.ai/rules/reports.md`, PRD v3). Porównanie dodaje trzy zapytania stałe: budżet roku wcześniej, jego linie z kategorią, jeden odczyt zakresu grupowany po linii (tańszy niż odczyt po dniu). Składanie drzewa obejmuje sumę kategorii z dwóch lat, nadal kilkaset wierszy. Jeśli test `nfr` z dwoma latami przekroczy 2 s, pierwszym krokiem jest pomiar zapytania zakresu na PostgreSQL; indeks `(budget_id, operation_date)` to osobna decyzja z migracją i testem na PostgreSQL, poza tym planem.

## Migration Notes

Brak migracji schematu i danych. Na produkcji w 2026 nie ma budżetu 2025, więc strona „Raporty” nie zmienia się aż do 2027. Zmiana domyślnego roku działa od wdrożenia, ale przy jednym budżecie daje ten sam wynik co dziś. Polecenie mocka odmawia na produkcji.

## References

- PRD: `context/foundation/prd-v3.md` (FR-016, Guardrails, Business Logic Changes)
- Roadmapa: `context/foundation/roadmap.md` (S-29)
- Poprzedni wycinek: `context/archive/2026-11-10-period-report-by-group/plan.md`
- Reguły: `.ai/rules/reports.md`, `.ai/rules/categories.md`, `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/rules/inertia-props.md`, `.ai/rules/sloppy.md`
- ADR: 0031, 0060, 0061, 0063, 0068, 0070, 0071, 0072
- Odczyty: `app/Support/PeriodReport.php:54`, `app/Support/MonthCategoryActuals.php:27`, `app/Http/Controllers/ReportController.php:19`, `app/Support/SelectedBudgetYear.php:10`, `app/Support/MonthPlanProposal.php:52`
- Wzorce: `resources/css/app.css:1772` (tabela przełączana po szerokości karty), `resources/js/lib/plan-change-direction.ts:13`, `resources/js/components/StatPanel.vue:57`, `database/seeders/DemoHouseholdSeeder.php:28`, `app/Console/Commands/SeedDemoHouseholdCommand.php:14`
- Wzorce testów: `tests/Feature/PeriodReportTest.php`, `tests/Feature/ReportsPageTest.php`, `tests/Feature/Nfr/NfrPerfReportTest.php`, `tests/Feature/SeedDemoHouseholdCommandTest.php`, `tests/e2e/reports.spec.ts`
- Design system: `context/foundation/design-system.md` (sekcje Kolor, Kwoty, Wykresy)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Reguły i odczyt porównania na serwerze

#### Automated

- [x] 1.1 Testy odczytu i zakresu przechodzą na SQLite — 8c87fa4
- [x] 1.2 Te same testy przechodzą na PostgreSQL (worktree) — 8c87fa4
- [x] 1.3 Test wydajności raportu z dwoma latami przechodzi na PostgreSQL — 8c87fa4
- [x] 1.4 Typy i build — 8c87fa4
- [x] 1.5 Formatowanie PHP — 8c87fa4
- [x] 1.6 Sloppy bez nowych znalezisk — 8c87fa4

#### Manual

- [x] 1.7 ADR-0073 i wpis Period Report w słowniku czyta się zgodnie z decyzjami tego planu — 8c87fa4

### Phase 2: Mock roku poprzedniego i domyślny rok

#### Automated

- [x] 2.1 Testy mocka i domyślnego roku przechodzą na SQLite — c4362dd
- [x] 2.2 Te same testy przechodzą na PostgreSQL (worktree) — c4362dd
- [x] 2.3 Pełna bramka w worktree — c4362dd
- [x] 2.4 Sloppy bez nowych znalezisk — c4362dd

#### Manual

- [x] 2.5 Mock dokłada budżet 2025 na devie, salda kont bez zmian — c4362dd
- [x] 2.6 Budżet 2025 pokazuje wykonanie, a po świeżym zalogowaniu otwiera się 2026 — c4362dd
- [x] 2.7 --remove usuwa mock, a ponowne uruchomienie odtwarza te same kwoty — c4362dd

### Phase 3: Tabela i panel miar

#### Automated

- [x] 3.1 Testy jednostkowe frontu przechodzą
- [x] 3.2 Wayfinder, build i typy
- [x] 3.3 Strażnik literałów UI
- [x] 3.4 Testy strony i strażników przechodzą na PostgreSQL
- [x] 3.5 Pełna bramka w worktree

#### Manual

- [x] 3.6 Raport roku 2026 pokazuje kolumny obu lat, a Razem 2025 zamkniętego miesiąca równa się raportowi 2025
- [x] 3.7 Okres w toku pokazuje notkę z zakresem, okres zakończony jej nie ma
- [x] 3.8 Różnica ma kolor według korzyści, zero i wiersze zarchiwizowane bez koloru
- [x] 3.9 Kafelki pokazują rok wcześniej i różnicę, raport 2025 wygląda jak przed zmianą
- [x] 3.10 Telefon ma wiersz w dwóch liniach bez przewijania w poziomie, kitchen sink we wszystkich scenariuszach

### Phase 4: Wykres i E2E

#### Automated

- [ ] 4.1 Test danych wykresu przechodzi
- [ ] 4.2 Wayfinder, build i typy
- [ ] 4.3 Strażnik literałów UI
- [ ] 4.4 Testy E2E przechodzą na bazie z mockiem
- [ ] 4.5 Pełna bramka w worktree
- [ ] 4.6 Sloppy bez nowych znalezisk

#### Manual

- [ ] 4.7 Każdy wiersz wykresu ma dwa słupki zgodne z tabelą, legenda pokazuje oba lata
- [ ] 4.8 Słupek roku wcześniej czytelny w trybie jasnym i ciemnym
- [ ] 4.9 Wykres mieści się na telefonie, raport bez porównania ma wykres jak przed zmianą
