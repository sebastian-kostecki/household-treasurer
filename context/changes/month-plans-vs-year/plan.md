# S-10: Plany miesięcy a budżet roczny — plan implementacji

## Overview

FR-010: „User can porównać plany miesięcy z budżetem rocznym”. Rozstrzygnięcie z PRD: „dla każdej kategorii pokazywane jest wydane w roku plus plan bieżącego miesiąca względem planu rocznego”.

Ten plan dodaje porównanie na stronie „Plan miesiąca”, ale tylko w widoku bieżącego miesiąca:

- każda pozycja (przychody i wydatki) dostaje linię „W roku: X z Y · zostaje Z”,
- każda sekcja dostaje wiersz „Razem w roku”.

„W roku” = wykonanie kategorii przed bieżącym miesiącem + kwota pozycji bieżącego miesiąca liczona regułą „do wydania”. „Zostaje” = plan bieżący roku kategorii − „W roku”. Wszystko liczy serwer. Nic nie jest zapisywane, więc budżet roczny się nie zmienia (ryzyko z roadmapy, `prd.md:93`).

## Current State Analysis

- Nic jeszcze nie porównuje planów miesięcy z rokiem. Trasy `month-plan`, `month-plans.approve` i `month-plans.update` (`routes/web.php:28-34`) nie mają odczytu rocznego, poza „Pozostałym limitem rocznym” przy jednorazowych (`MonthPlanController.php:263-268`, FR-008 z S-04).
- Strona roczna: `categories.planned_amount_minor_units` (plan bieżący) i licznik `execution_minor_units` (wykonanie roczne do dziś). Licznik utrzymują `ManageFinancialOperation` i Import, a strona Budżet pokazuje go jako „Wykonanie”.
- Strona miesięczna: `MonthToSpend::forMonth` (`app/Support/MonthToSpend.php:32-48`) daje odczyt planu (`reading`: propozycja albo zatwierdzony, kwoty bieżące), kwoty faktyczne miesiąca (`MonthCategoryActuals`) i `rowAmounts($category)` (`:80-87`). Kontroler woła to raz na stronę (`MonthPlanController.php:145`).
- Reguła kwoty pozycji jest wpisana w pętlę `ToSpendCalculator::toSpendMinorUnits` (`app/Support/ToSpendCalculator.php:15-37`):
  - przychód: plan, a po pierwszym wpływie kwota faktyczna,
  - rachunek: plan, a po pierwszym wydatku z kwotą dodatnią kwota faktyczna,
  - bieżąca: większa z planu i kwoty faktycznej.

  Kopia TS (`resources/js/lib/to-spend-calculator.ts`) dotyczy tylko sumy „do wydania”.
- Daty operacji nie mogą być późniejsze niż dziś (`before_or_equal:today`: `app/Concerns/ExpenseValidationRules.php:24,67`, `IncomeValidationRules.php:24,67`, `CsvImport/FinancialOperationImportValidator.php:115`). Rok daty musi też być rokiem budżetu kategorii (`ExpenseValidationRules.php:129-143`, analogicznie Income i `OperationRebuildValidationRules.php:375-395`). Dla bieżącego miesiąca bieżącego roku zachodzi więc tożsamość: wykonanie przed miesiącem = `execution_minor_units` − kwota faktyczna w miesiącu. Nie trzeba nowego zapytania, a liczba zgadza się z kolumną „Wykonanie” na stronie Budżet.
- Wiersze strony (`MonthPlanController::monthPlanPayload`, `:139-208`) to kategorie z odczytu planu, kategorie z operacjami w miesiącu i kandydaci do dołączenia (gdy plan można zmienić). Dla bieżącego miesiąca oznacza to wszystkie aktywne kategorie plus zarchiwizowane z kwotą albo operacją w miesiącu.
- W widoku `MonthPlanItemRow.vue:101-113` jest linia „Pozostały limit roczny” dla każdej jednorazowej. W `MonthPlan.vue:361-380` sekcje dzielą wydatki na listę i zwiniętą sekcję bezczynnych jednorazowych (`is_idle_one_off`). Tryb edycji (`isEditable`, `MonthPlan.vue:93-97`) przelicza odchylenie i „do wydania” na żywo z wpisanych kwot.

## Desired End State

Na stronie „Plan miesiąca”, gdy oglądany miesiąc jest bieżącym miesiącem kalendarzowym, a budżet jest w wykonaniu (propozycja albo plan zatwierdzony):

- każdy wiersz przychodu i wydatku, także jednorazowy w planie i zarchiwizowany na liście, ma linię „W roku: X z Y · zostaje Z”. X to „W roku”, Y to plan bieżący roku, Z to „zostaje”,
- gdy „zostaje” < 0:
  - wydatek pokazuje „ponad rok o |Z|” w `text-destructive`,
  - przychód pokazuje „ponad plan roczny o |Z|” w `text-success`,
- przychód z „zostaje” ≥ 0 nie ma koloru,
- wiersz jednorazowy na liście nie pokazuje już „Pozostałego limitu rocznego”. Bezczynne jednorazowe w zwiniętej sekcji pokazują tylko dotychczasowy „Pozostały limit roczny”,
- na końcu każdej sekcji jest wiersz „Razem w roku: X z Y · zostaje Z” liczony z wszystkich wierszy sekcji wysłanych przez serwer, z tymi samymi kolorami i z podpowiedzią z definicją,
- w trybie edycji linia roczna wiersza, którego wpisana kwota różni się od zapisanej, jest przyciemniona i ma opis „przeliczy się po zapisaniu planu”,
- w miesiącu minionym, przyszłym, w budżecie innego roku i w szkicu porównania nie ma. „Pozostały limit roczny” jednorazowych zostaje wtedy jak dziś.

Przykład kontrolny („Jedzenie”, bieżąca, plan roczny 12 000 zł, wydane sty–paź 10 000 zł, plan listopada 1 000 zł):

| W listopadzie wydane | W roku | Zostaje | Kolor |
| -------------------- | ------ | ------- | ----- |
| 400 zł | 11 000 zł | 1 000 zł | brak |
| 1 300 zł | 11 300 zł | 700 zł | brak |
| 1 300 zł, plan roczny 10 500 zł | 11 300 zł | −800 zł („ponad rok o 800 zł”) | destructive |

### Key Discoveries:

- `ToSpendCalculator.php:19-34` to jedyne miejsce w PHP z regułą kwoty pozycji. Wydzielenie jej zachowuje jedno źródło dla „do wydania” i dla „W roku” (wzorzec „the two cannot drift apart” z `MonthToSpend::rowAmounts`, `MonthToSpend.php:74-79`).
- `MonthPlanProposal::forBudget` ładuje już `$budget->categories` z `planned_amount_minor_units` i `execution_minor_units` (`MonthPlanProposal.php:42`). Porównanie nie dokłada więc zapytań.
- `CategoryFactory` ustawia `execution_minor_units = 0` (`database/factories/CategoryFactory.php:31`), a `FinancialOperationFactory` nie aktualizuje licznika. Testy muszą ustawiać licznik ręcznie, zgodnie z operacjami, jak `MonthPlanPageTest.php:705`.
- Wzorzec kolorów: wydatek ponad plan w `text-destructive`, dochód z dodatnim odchyleniem w `text-success` (S-04, `context/archive/2026-11-02-month-items-plan-vs-actual/plan.md`).

## What We're NOT Doing

- Porównania dla miesiąca innego niż bieżący (miniony, przyszły, inny rok) ani prognozy do końca roku z planów lub propozycji kolejnych miesięcy. Wariant prognozy odrzucono przy wyborze formuły.
- Wczesnego ostrzeżenia typu „reszta roku < plan × pozostałe miesiące” i powiadomień Dashboardu z porównania rocznego.
- Przeliczania linii rocznej na żywo w trybie edycji. Nie powstaje kopia TS reguły; linia jest tylko przyciemniona, dopóki kwota nie zostanie zapisana.
- Zmian na stronie Budżet, w sidebarze, w trasach i w przełączniku roku.
- Porównania sum zatwierdzonych planów miesięcy z rokiem (plan vs plan należy do S-04 i S-09) oraz odkładania i dobierania w skali roku (S-09).
- Wierszy dla zarchiwizowanych kategorii bez kwoty i bez operacji w bieżącym miesiącu. Nie ma ich na stronie, więc nie wchodzą też do „Razem w roku”.
- Zmian w schemacie bazy, migracji i zapisach. Budżet roczny pozostaje nietknięty.

## Implementation Approach

Serwer liczy porównanie w nowej, czystej klasie `App\Support\MonthYearComparison` z danych, które kontroler już ma (`Category` + `MonthToSpend::rowAmounts`). Kwotę pozycji bierze z wydzielonej metody `ToSpendCalculator::itemMinorUnits`, więc „do wydania” i „W roku” nie mogą się rozjechać.

Kontroler dodaje pole wiersza `year_comparison` i pole planu `year_totals` tylko dla `timing === 'current'`. W pozostałych przypadkach pola mają wartość `null`. Vue tylko wyświetla wartości z serwera (reguła „month logic lives twice” z `AGENTS.md` / `.ai/rules/js.md`: bez kopii TS, bo zdecydowano o odświeżeniu po zapisie). Definicję odczytu zapisujemy w `CONTEXT.md`.

## Critical Implementation Details

- **Tożsamość „przed miesiącem” obowiązuje tylko dla bieżącego miesiąca.** `execution_minor_units − actual(miesiąc)` jest wykonaniem przed miesiącem tylko wtedy, gdy oglądany miesiąc jest bieżący, bo operacje nie mają dat z przyszłości. Dlatego kontroler buduje porównanie wyłącznie przy `timing === 'current'`. Dla innych miesięcy ta formuła dawałaby błędne liczby, więc nie wolno jej uogólniać.
- **Sumy z wierszy serwera, nie z wierszy widocznych.** `year_totals` sumuje wszystkie wiersze danego typu z payloadu, także kandydatów do dołączenia ukrytych poza trybem zmiany (`MonthPlan.vue:75-79`). Mają oni plan roczny, który należy do roku. Suma może więc różnić się od sumy linii widocznych w trybie odczytu, i jest to zamierzone.

## Phase 1: Odczyt porównania na serwerze

### Overview

Wydzielenie reguły kwoty pozycji, nowa klasa porównania, dwa nowe pola payloadu, typy TS, testy i termin w `CONTEXT.md`. Strona wygląda bez zmian.

### Changes Required:

#### 1. Reguła kwoty pozycji

**File**: `app/Support/ToSpendCalculator.php`

**Intent**: Wydzielić z pętli `toSpendMinorUnits` kwotę, z jaką jedna pozycja liczy się w miesiącu, i użyć jej w samej pętli. Dzięki temu porównanie roczne korzysta z tej samej reguły co „do wydania”.

**Contract**: `public static function itemMinorUnits(CategoryType $type, ?MonthItemKind $kind, int $plan, int $actual, bool $hasPositiveAmount): int` zwraca kwotę bez znaku kierunku:

- przychód i rachunek: `$hasPositiveAmount ? $actual : $plan`,
- bieżąca: `max($plan, $actual)`.

`toSpendMinorUnits` dodaje ją dla przychodu i odejmuje dla wydatku. Wynik `toSpendMinorUnits` dla każdego wejścia się nie zmienia. Kopia TS `to-spend-calculator.ts` zostaje bez zmian. Docblock nowej metody mówi, że porównanie roczne nie ma kopii TS.

#### 2. Testy reguły

**File**: `tests/Unit/ToSpendCalculatorTest.php`

**Intent**: Pokryć `itemMinorUnits` dla każdego rodzaju pozycji i zachować istniejące testy sumy jako dowód braku regresji.

**Contract**: Przypadki:

- bieżąca: plan > faktyczna, faktyczna > plan, faktyczna ujemna (zwrot),
- rachunek: przed zapłatą, po zapłacie mniejszej i większej niż plan, sam zwrot (`hasPositiveAmount = false` → plan),
- przychód: przed wpływem i po wpływie.

#### 3. Klasa porównania

**File**: `app/Support/MonthYearComparison.php` (nowy)

**Intent**: Policzyć dla kategorii „W roku”, plan roczny, „zostaje” i flagę „ponad rok” oraz zsumować je dla sekcji. Klasa jest czysta (bez zapytań), żeby dało się ją testować jednostkowo na niezapisanych modelach `Category`.

**Contract**:

- `public static function forCategory(Category $category, array $rowAmounts): array{year_minor_units: int, annual_plan_minor_units: int, remaining_minor_units: int, is_over_year: bool}`:
  - `$rowAmounts` ma kształt `MonthToSpend::rowAmounts`,
  - `before = execution_minor_units − actual_minor_units`,
  - `year = before + ToSpendCalculator::itemMinorUnits(type, month_item_kind, plan, actual, has_positive_amount)`,
  - `annual = planned_amount_minor_units`,
  - `remaining = annual − year`,
  - `is_over_year = type === Expense && remaining < 0`.
- `public static function totals(array $comparisons, CategoryType $type): array{year_minor_units: int, annual_plan_minor_units: int, remaining_minor_units: int, is_over_year: bool}` przyjmuje listę wyników `forCategory` i sumuje pola kwot. `is_over_year = $type === Expense && suma remaining < 0`.
- Docblock: definicja z `CONTEXT.md` i zastrzeżenie, że wejście jest poprawne tylko dla bieżącego miesiąca (patrz Critical Implementation Details).

#### 4. Testy klasy

**File**: `tests/Unit/MonthYearComparisonTest.php` (nowy)

**Intent**: Pokryć tabelę z Desired End State i przypadki brzegowe na modelach `Category` budowanych w pamięci.

**Contract**: Przypadki (kwoty w zł, w teście w minor units):

- „Jedzenie”, trzy wiersze tabeli kontrolnej, w tym `is_over_year = true` przy planie rocznym 10 500 zł,
- rachunek przed zapłatą (liczy plan) i po zapłacie (liczy faktyczną),
- przychód przed i po wpływie oraz przychód ponad plan roczny (`remaining < 0`, `is_over_year = false`),
- jednorazowa z planem 0 i wydatkiem 300 zł („zostaje” = plan roczny − wykonanie, czyli równe Pozostałemu limitowi rocznemu),
- jednorazowa „Wakacje” (plan roczny 5 000 zł, wykonanie 2 000 zł, plan listopada 1 000 zł, w listopadzie 0 zł): „W roku” 3 000 zł, „zostaje” 2 000 zł,
- `totals` dla wydatków (z flagą przy ujemnej sumie) i przychodów (bez flagi).

#### 5. Pola payloadu

**File**: `app/Http/Controllers/MonthPlanController.php`

**Intent**: Przy bieżącym miesiącu dodać do każdego wiersza porównanie, a do planu sumy sekcji. Przy innym miesiącu oba pola mają wartość `null`.

**Contract**:

- `itemRow` dostaje pole `year_comparison: array{…}|null` (kształt `forCategory`). Wartość jest różna od `null` tylko przy `$timing === 'current'`. `itemRow` dostaje już `$toSpend` i `$timing`, a `$amounts` z `rowAmounts` przekazuje do `forCategory`.
- `monthPlanPayload` dostaje pole `year_totals: array{income: array{…}, expense: array{…}}|null`, liczone przez `MonthYearComparison::totals` z `year_comparison` wierszy przychodów i wydatków (wszystkich wysłanych, także kandydatów do dołączenia). Przy innym miesiącu pole ma wartość `null`.
- Aktualizacja docblocków shape. Bez nowych zapytań, a metody krótkie, żeby nie wyzwolić reguł Sloppy (god method).

#### 6. Typy TS

**File**: `resources/js/types/month-plan.ts`, `resources/js/pages/MonthPlan.vue` (typ propsów `monthPlan`)

**Intent**: Odzwierciedlić nowe pola w ręcznie pisanych typach.

**Contract**: `export type MonthYearComparison = { year_minor_units: number; annual_plan_minor_units: number; remaining_minor_units: number; is_over_year: boolean }`. `MonthPlanItem.year_comparison: MonthYearComparison | null`. Typ planu dostaje `year_totals: { income: MonthYearComparison; expense: MonthYearComparison } | null`.

#### 7. Testy strony

**File**: `tests/Feature/MonthPlanPageTest.php`

**Intent**: Potwierdzić pola w `assertInertia` dla stanów, w których porównanie jest i w których go nie ma.

**Contract**: `travelTo` np. 2026-11-15. Licznik `execution_minor_units` ustawiany zgodnie z operacjami utworzonymi fabryką. Przypadki:

- bieżąca propozycja: wiersz „Jedzenie” z wartościami z tabeli kontrolnej oraz `year_totals` dla obu sekcji,
- bieżący plan zatwierdzony ze zmienionymi kwotami bieżącymi: liczy kwotę bieżącą, nie zatwierdzoną,
- kandydat do dołączenia wchodzi do `year_totals`,
- przyszły miesiąc, miniony miesiąc zatwierdzony i budżet minionego roku: `year_comparison === null` w wierszach i `year_totals === null`,
- szkic: `monthPlan === null` (już pokryty; bez zmian),
- „do wydania” bez zmian w istniejących testach (regresja po wydzieleniu reguły).

#### 8. Termin domenowy

**File**: `CONTEXT.md`

**Intent**: Zapisać definicję odczytu, żeby kolejne zmiany (S-09, S-07) używały tej samej nazwy i formuły.

**Contract**: Nowy wpis za „To Spend”: **Year after the Month**. Treść wpisu:

- definicja: Execution of a Category before the current month + the amount its month item counts with by the To Spend rule; compared with the Category's Current Plan; Year Remainder = Current Plan − Year after the Month,
- zakres: only for the current calendar month of a Budget in execution, for income and expense Categories,
- Polish UI copy: „W roku … z …”, „zostaje”, „Razem w roku”,
- _Avoid_: forecast, Year Pace, Available Limit, Month Remaining.

### Success Criteria:

#### Automated Verification:

- `tests/Unit/ToSpendCalculatorTest.php`, `tests/Unit/MonthYearComparisonTest.php`, `tests/Feature/MonthPlanPageTest.php` i `tests/Feature/MonthToSpendTest.php` przechodzą na PostgreSQL przez Sail (polecenie z `AGENTS.md`, „Test database”)
- `vendor/bin/pint --dirty --format agent` przechodzi
- `vendor/bin/sloppy ci` na hoście nie zgłasza nowych znalezisk
- `npm run check` i `npm run types:check` przechodzą

**Implementation Note**: Faza 1 nie ma ręcznej weryfikacji. Template się nie zmienia, a brak regresji pokrywają `types:check` i istniejące testy „do wydania”. Podgląd stawiamy raz, w fazie 2.

---

## Phase 2: Widok porównania na Planie miesiąca

### Overview

Linia roczna w wierszu, zastąpienie „Pozostałego limitu rocznego” u jednorazowych na liście, przyciemnienie w trybie edycji, wiersz sumy w każdej sekcji, tłumaczenia i ręczna weryfikacja.

### Changes Required:

#### 1. Linia roczna w wierszu

**File**: `resources/js/components/MonthPlanItemRow.vue`

**Intent**: Pokazać „W roku: X z Y · zostaje Z” pod nazwą kategorii, w miejscu i stylu obecnej linii limitu (`:101-113`), z kolorami z Desired End State.

**Contract**:

- linia roczna widoczna, gdy `row.year_comparison !== null && !row.is_idle_one_off`,
- linia „Pozostały limit roczny” widoczna, gdy `row.available_limit_minor_units !== null && (row.year_comparison === null || row.is_idle_one_off)`,
- nowy prop `yearStale: boolean` (domyślnie `false`): przy `true` linia ma `opacity-60` i tekst `sr-only` / `title` z `year_stale_hint`,
- kwoty przez `formatMoney`, `tabular-nums`, kolory tylko z tokenów (`text-destructive`, `text-success`, `text-muted-foreground`).

#### 2. Przyciemnienie w trybie edycji

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Oznaczyć linię roczną jako nieaktualną, gdy wpisana kwota różni się od kwoty z serwera. Linia nie jest wtedy przeliczana.

**Contract**: `yearStale = isEditable && isEditableRow(row) && parsedAmount(row) !== row.plan_minor_units`. Przekazywany do obu użyć `MonthPlanItemRow` (lista i zwinięta sekcja). Porównanie dotyczy kwot, więc nie jest kopią reguły domenowej.

#### 3. Wiersz sumy sekcji

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Na końcu każdej `ListSurface` (po zwiniętej sekcji) pokazać „Razem w roku: X z Y · zostaje Z” z podpowiedzią definicji, gdy `monthPlan.year_totals !== null`.

**Contract**: Dane z `monthPlan.year_totals[section.key]`. Wiersz `border-t` w stylu siatki sekcji. Etykieta przez `ColumnHeaderHint` z `year_total_hint`. Te same reguły kolorów co wiersz. Przy 360 px bez poziomego przewijania; tekst może się zawinąć.

#### 4. Tłumaczenia

**File**: `lang/pl/ui.php` (sekcja `month_plan`)

**Intent**: Dodać polskie teksty porównania. Plik `en` nie istnieje i nie powstaje.

**Contract**: Klucze (implementujący może dopracować brzmienie, zachowując znaczenie):

| Klucz | Tekst |
| ----- | ----- |
| `year_line` | „W roku” |
| `year_of` | „z” |
| `year_remaining` | „zostaje” |
| `year_over_expense` | „ponad rok o” |
| `year_over_income` | „ponad plan roczny o” |
| `year_total` | „Razem w roku” |
| `year_total_hint` | „Wykonanie przed tym miesiącem plus pozycja tego miesiąca liczona jak w „do wydania”, wobec planu bieżącego na rok.” |
| `year_stale_hint` | „Przeliczy się po zapisaniu planu” |

### Success Criteria:

#### Automated Verification:

- `npm run build`, `npm run check` i `npm run types:check` przechodzą
- `npm test` przechodzi
- `tests/Feature/MonthPlanPageTest.php` przechodzi na PostgreSQL przez Sail
- Pełna bramka CI przechodzi na obrazie Sail (`composer ci:check`, polecenie z `AGENTS.md`) oraz `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- W bieżącej propozycji (konto `test@example.com` z `DemoHouseholdSeeder`) linie „W roku” zgadzają się z ręcznym rachunkiem dla jednej bieżącej, jednego rachunku, jednej jednorazowej i jednego przychodu. Wydatek ponad rok ma `destructive`, przychód ponad plan roczny ma `success`
- Jednorazowa na liście pokazuje tylko linię roczną, a bezczynna jednorazowa w zwiniętej sekcji tylko „Pozostały limit roczny”. Miesiąc przyszły i miniony nie mają linii rocznych ani sum, a jednorazowe mają tam „Pozostały limit roczny” jak przed zmianą
- W edytowalnej propozycji i w trybie zmiany zatwierdzonego planu zmiana kwoty przyciemnia linię roczną tego wiersza. Po zatwierdzeniu lub zapisaniu linia pokazuje nowe wartości, a budżet roczny (strona Budżet: plan bieżący i wykonanie) jest bez zmian
- Wiersze „Razem w roku” są na końcu obu sekcji, a podpowiedź z definicją otwiera się klawiaturą i myszą
- Przy szerokości 360 px nie ma poziomego przewijania. Przy 768 px i 1024 px z rozwiniętym sidebarem nic nie jest ucięte. W trybie ciemnym kolory `destructive` i `success` są czytelne

**Implementation Note**: Przed prośbą o ręczne sprawdzenie zbuduj i uruchom podgląd według `AGENTS.md` („Preview from a worktree”, jeśli pracujesz w worktree) i podaj adres oraz login.

---

## Testing Strategy

### Unit Tests:

- `ToSpendCalculatorTest`: `itemMinorUnits` dla bieżącej, rachunku i przychodu, w tym zwroty. Istniejące testy sumy bez zmian.
- `MonthYearComparisonTest`: tabela kontrolna „Jedzenie”, rachunek, przychód (także ponad plan), jednorazowa z planem 0 (równość z Pozostałym limitem rocznym), jednorazowa „Wakacje”, sumy z flagą tylko dla wydatków.

### Integration Tests:

- `MonthPlanPageTest`: pola `year_comparison` i `year_totals` dla bieżącej propozycji i bieżącego planu zatwierdzonego (kwota bieżąca, nie zatwierdzona). Kandydat do dołączenia w sumie. `null` dla przyszłego i minionego miesiąca oraz dla budżetu minionego roku.
- `MonthToSpendTest` i istniejące testy „do wydania” w `MonthPlanPageTest`: regresja po wydzieleniu reguły.

### Manual Testing Steps:

1. Zaloguj się kontem demo, otwórz „Plan miesiąca” na bieżącym miesiącu i sprawdź linie i sumy względem kolumn „Plan bieżący” i „Wykonanie” na stronie Budżet (wykonanie przed miesiącem = wykonanie − kwota faktyczna w miesiącu).
2. Dodaj wydatek w bieżącym miesiącu na kategorii bieżącej powyżej planu i sprawdź, że „W roku” rośnie o nadwyżkę.
3. Zmień kwotę w trybie edycji, sprawdź przyciemnienie, zapisz i sprawdź nowe wartości oraz brak zmian na stronie Budżet.
4. Przejdź na miesiąc przyszły i miniony i sprawdź brak porównania.

## Performance Considerations

Brak nowych zapytań. Porównanie liczy się z kategorii już załadowanych przez `MonthPlanProposal::forBudget` i z `MonthCategoryActuals` już pobranych przez `MonthToSpend`, w O(liczba wierszy).

## Migration Notes

Nie dotyczy: bez zmian w schemacie i danych.

## References

- Related research: `context/changes/month-plans-vs-year/research.md`
- Roadmap S-10: `context/foundation/roadmap.md:207-217`; FR-010: `context/foundation/prd.md:125-126`
- ADR-0033 (plan miesiąca obok rocznego, różnica widoczna): `.docs/adr/0033-month-plan-beside-annual-budget.md`
- Reguła „do wydania”: `app/Support/ToSpendCalculator.php:15-37`, `CONTEXT.md` („To Spend”)
- Odczyt miesiąca: `app/Support/MonthToSpend.php:32-87`
- Wiersze i limit jednorazowych: `app/Http/Controllers/MonthPlanController.php:139-268`, `resources/js/components/MonthPlanItemRow.vue:101-113`
- Wzorzec poprzedniej zmiany na tej stronie: `context/archive/2026-11-02-month-items-plan-vs-actual/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Odczyt porównania na serwerze

#### Automated

- [x] 1.1 `tests/Unit/ToSpendCalculatorTest.php`, `tests/Unit/MonthYearComparisonTest.php`, `tests/Feature/MonthPlanPageTest.php` i `tests/Feature/MonthToSpendTest.php` przechodzą na PostgreSQL przez Sail (polecenie z `AGENTS.md`, „Test database”)
- [x] 1.2 `vendor/bin/pint --dirty --format agent` przechodzi
- [x] 1.3 `vendor/bin/sloppy ci` na hoście nie zgłasza nowych znalezisk
- [x] 1.4 `npm run check` i `npm run types:check` przechodzą

### Phase 2: Widok porównania na Planie miesiąca

#### Automated

- [ ] 2.1 `npm run build`, `npm run check` i `npm run types:check` przechodzą
- [ ] 2.2 `npm test` przechodzi
- [ ] 2.3 `tests/Feature/MonthPlanPageTest.php` przechodzi na PostgreSQL przez Sail
- [ ] 2.4 Pełna bramka CI przechodzi na obrazie Sail (`composer ci:check`, polecenie z `AGENTS.md`) oraz `vendor/bin/sloppy ci` na hoście

#### Manual

- [ ] 2.5 W bieżącej propozycji (konto `test@example.com` z `DemoHouseholdSeeder`) linie „W roku” zgadzają się z ręcznym rachunkiem dla jednej bieżącej, jednego rachunku, jednej jednorazowej i jednego przychodu. Wydatek ponad rok ma `destructive`, przychód ponad plan roczny ma `success`
- [ ] 2.6 Jednorazowa na liście pokazuje tylko linię roczną, a bezczynna jednorazowa w zwiniętej sekcji tylko „Pozostały limit roczny”. Miesiąc przyszły i miniony nie mają linii rocznych ani sum, a jednorazowe mają tam „Pozostały limit roczny” jak przed zmianą
- [ ] 2.7 W edytowalnej propozycji i w trybie zmiany zatwierdzonego planu zmiana kwoty przyciemnia linię roczną tego wiersza. Po zatwierdzeniu lub zapisaniu linia pokazuje nowe wartości, a budżet roczny (strona Budżet: plan bieżący i wykonanie) jest bez zmian
- [ ] 2.8 Wiersze „Razem w roku” są na końcu obu sekcji, a podpowiedź z definicją otwiera się klawiaturą i myszą
- [ ] 2.9 Przy szerokości 360 px nie ma poziomego przewijania. Przy 768 px i 1024 px z rozwiniętym sidebarem nic nie jest ucięte. W trybie ciemnym kolory `destructive` i `success` są czytelne
