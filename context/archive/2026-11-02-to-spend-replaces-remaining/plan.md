# S-06: „Do wydania” zamiast `Pozostało w miesiącu` — plan implementacji

## Overview

Wycinek S-06 roadmapy M-1. Użytkownik widzi liczbę „do wydania” z planu miesiąca (S-01) w dwóch miejscach:

- **Dashboard**: kafelek bieżącego miesiąca zastępuje kafelek Month Remaining puli równomiernych,
- **strona Budżet**: pasek miesiąca dostaje wiersz „Do wydania” dla oglądanego miesiąca.

Wiersz pokazuje stan planu i prowadzi do strony „Plan miesiąca”. Miniony miesiąc bez planu jest wyraźnie oznaczony i nie ma liczby.

Liczba pochodzi z jednego odczytu po stronie serwera, wydzielonego z kodu strony planu. Dashboard, Budżet i Plan miesiąca pokazują więc tę samą wartość.

PRD: FR-011, FR-013 (`context/foundation/prd.md:127,136`). Domena: `CONTEXT.md` (Month Remaining, Month Plan, To Spend) i `.docs/adr/0033-month-plan-beside-annual-budget.md`.

## Current State Analysis

- **Dashboard pokazuje kafelek Month Remaining** tylko dla budżetu bieżącego roku UTC w wykonaniu z widoczną kategorią równomierną (`app/Http/Controllers/DashboardController.php:87-111`). Vue wyświetla go, gdy `paced_pool_month_remaining_minor_units !== null`, i przełącza siatkę 3/2 kolumny (`resources/js/pages/Dashboard.vue:119-149,250-265`). Etykieta to `dashboard.paced_pool_month_remaining` = `':month — pozostało'` (`lang/pl/ui.php:134`).
- **`current_month` ma drugiego konsumenta.** `DashboardNotices` buduje z niego link powiadomienia `paced_month_remaining` (`Dashboard.vue:296-301`). Wyłączenie tego powiadomienia to S-07.
- **Strona Budżet nie wyświetla Month Remaining.** `pacedPoolMonthRemainingMinorUnits` jest tylko typowany (`resources/js/pages/Budget.vue:77`). Pasek miesiąca to `BudgetMonthStrip` z Dochody / Wydatki / `Wynik` (`resources/js/components/BudgetMonthStrip.vue:57-61`, `BudgetMonthResultSummary.vue`). Strona nie linkuje do planu miesiąca.
- **Liczba i stan planu powstają tylko w prywatnym `MonthPlanController::monthPlanPayload`** (`app/Http/Controllers/MonthPlanController.php:103-153`):
  - `MonthPlanProposal::forBudget` → `reading($month)`,
  - `MonthCategoryActuals::forMonth`,
  - kategorie z planu i z operacji (`whereIn`, `ordered()`),
  - elementy kalkulatora,
  - `ToSpendCalculator::toSpendMinorUnits`, z wynikiem `null` dla stanu `none`.
- **Stany odczytu** (`app/Support/MonthPlanProposal.php:83-127`):
  - `approved`: zapisany plan, także w minionym miesiącu,
  - `proposal`: bieżący lub przyszły miesiąc bez zapisu,
  - `none`: miniony miesiąc bez planu.

  Dla budżetu w wykonaniu bieżący miesiąc nigdy nie jest `none`.
- **Strona planu bierze rok z sesji** (`SelectedBudgetYear`), a miesiąc z `?month=` z domyślnym dzisiejszym dla bieżącego roku (`MonthPlanController.php:28-53`). Dashboard bierze bieżący rok z kalendarza i przełącza rok sesji przyciskiem „Otwórz budżet”: `BudgetController.selectYear.form()` z ukrytym `year` (`Dashboard.vue:338-356`). `selectYear` zawsze przekierowuje na `budget` (`app/Http/Controllers/BudgetController.php:168-175`).
- **Gałęzie równoległe** S-02 (`set-aside-and-draw`), S-03, S-04 i S-08 mają na razie tylko commity z dokumentacją. Set-aside wejdzie do liczby w S-02 (`app/Support/ToSpendCalculator.php:11`).

## Desired End State

- **Dashboard, budżet bieżącego roku w wykonaniu:** pierwszy kafelek to `„listopad — do wydania”` z liczbą „do wydania” bieżącego miesiąca, kolorowaną jak dziś (`text-success` ≥ 0, `text-destructive` < 0).
  - Przy propozycji ma odznakę „Niezatwierdzony”, przy planie zatwierdzonym nie ma odznaki.
  - Link „Plan miesiąca” przełącza rok sesji na bieżący i otwiera stronę planu na dzisiejszym miesiącu.
  - Kafelek nie zależy od kategorii równomiernych: budżet z samymi jednorazowymi też go ma.
- **Dashboard bez budżetu albo ze szkicem:** brak kafelka i siatka 2 kolumn, jak dziś.
- **Strona Budżet, budżet w wykonaniu, miesiąc z planem** (propozycja albo zatwierdzony, także miniony zatwierdzony): w karcie „Miesiąc”, nad Dochody / Wydatki / `Wynik`, jest wiersz z:
  - etykietą „Do wydania” i kwotą (`text-destructive` < 0),
  - odznaką „Niezatwierdzony” (outline) albo „Zatwierdzony” (secondary), jak na stronie planu,
  - linkiem „Plan miesiąca” na ten sam `?month=`.
- **Strona Budżet, miniony miesiąc bez planu:** zamiast wiersza jest wyciszony tekst „Ten miesiąc nie ma planu”, bez liczby i bez linku. `Wynik` zostaje.
- **Strona Budżet, szkic:** pasek bez zmian.
- **Ta sama liczba w trzech widokach.** Dla przykładu kontrolnego (czerwiec 2026, 3730,00 zł = `373_000`) Dashboard, Budżet i Plan miesiąca dają tę samą wartość, sprawdzoną testami Feature.
- **Dokumenty:**
  - `.docs/specs/dashboard-hierarchy.md` opisuje kafelek „do wydania”,
  - `.docs/prd.md` FR-REP-13 mówi, kiedy obowiązuje Month Remaining, a kiedy To Spend.

### Key Discoveries:

- `MonthPlanController.php:103-153` to jedyne miejsce składania liczby. Wydzielenie go usuwa potrójne kopiowanie i daje S-02 jedno miejsce na Set-aside.
- `MonthPlanPageTest` asertuje `to_spend_minor_units = 373_000` (`tests/Feature/MonthPlanPageTest.php:50,247,331`) na scenariuszu `canonicalBudget()` (`:370-399`). Po refaktorze te testy przechodzą bez zmian, co dowodzi zachowania strony planu.
- Bez `?month` strona planu otwiera dla bieżącego roku dzisiejszy miesiąc (`BudgetViewedMonth::resolve`), więc link z Dashboardu nie musi podawać miesiąca.
- Gotowe tłumaczenia:
  - `month_plan.to_spend` „Do wydania”,
  - `month_plan.state_proposal` / `state_approved`,
  - `month_plan.none_title` „Ten miesiąc nie ma planu” (`lang/pl/ui.php:281-306`),
  - `nav.month_plan` „Plan miesiąca” (`lang/pl/ui.php:7`).
- Test `test_dashboard_sends_paced_pool_month_remaining_for_execution_with_paced_categories` (`tests/Feature/DashboardTest.php:709`) ma scenariusz: 2026-09-23, kategoria równomierna bieżąca 120 000 rocznie, wydatek 40 000 we wrześniu. Daje on „do wydania” = 0 − max(10 000, 40 000) = `-40_000` przy stanie `proposal`.
- `test_dashboard_does_not_send_paced_pool_month_remaining_without_paced_categories` (`:741`): sama kategoria jednorazowa → propozycja 0 → `0`, a kafelek jest teraz widoczny.

## What We're NOT Doing

- **Powiadomienia.** Nie zmieniamy powiadomień Dashboardu: `paced_month_remaining` zostaje do S-07, które też doda „brak planu”, „do wydania poniżej zera” i „pozycję ponad plan”. `DashboardNotices.php` i `DashboardPacedCategoryMonthAlerts.php` zostają bez zmian.
- **Ostrzeżenia przy operacjach i w Imporcie.** Ani ich, ani kopii TS Month Remaining (`month-remaining-calculator.ts`, `hand-entry-month-remaining-preview.ts`, `import-month-remaining-warnings.ts`) nie zmieniamy. To S-08.
- **Propsy Month Remaining strony Budżet.** `pacedPoolMonthRemainingMinorUnits`, per kategoria `month_remaining_minor_units` / `month_execution_minor_units` i `BudgetMonthReadings` zostają bez zmian. Nie są renderowane i stoją za `BudgetMonthRemainingTest`. Sprzątanie należy do zmiany, która wyłączy Month Remaining po S-07/S-08.
- **Pula Month Remaining na stronie Budżet** dla minionego miesiąca bez planu. Decyzja: historia wygląda jak dziś, czyli sam `Wynik`, plus oznaczenie braku planu.
- **Komunikat w szkicu** na stronie Budżet.
- **Podsumowanie miesiąca** (plan końcowy, odkładanie zamierzone i realne) to S-09. Miniony zatwierdzony miesiąc pokazuje tylko „do wydania” na żywo (późna operacja zmienia liczbę, zgodnie z PRD „Rozliczenie końca miesiąca”).
- **Set-aside w liczbie** to S-02.
- **Brak nowej kopii TS.** „Do wydania” na Dashboardzie i Budżecie nie reaguje przed zapisem, więc według `AGENTS.md` („Month logic lives twice”) liczba idzie tylko z serwera.
- **Bez zmian w `.ai/rules/js.md` (S-08) i w ADR-0031 (S-07).**

## Implementation Approach

Najpierw refaktor bez zmiany zachowania: nowa klasa `App\Support\MonthToSpend` przejmuje składanie stanu i liczby, a `MonthPlanController` buduje z niej wiersze. Istniejące testy strony planu są siatką bezpieczeństwa.

Potem dwa niezależne widoki, każdy w osobnej fazie:

- Dashboard: prop `month_to_spend` zamiast puli Month Remaining oraz `destination` w `budget.select-year` dla linku,
- Budżet: prop `monthToSpend` i wiersz w `BudgetMonthStrip`.

Każda faza zmienia prop PHP i typ `defineProps` w jednym commicie, asertuje prop przez `assertInertia` i aktualizuje swój dokument z listy ADR-0033:7.

## Critical Implementation Details

- **Odczyt tylko dla budżetu w wykonaniu.** `MonthPlanProposal` nie sprawdza fazy. Jak w `MonthPlanController.php:64`, każdy controller woła `MonthToSpend` tylko przy `$budget->isExecution()`, a w pozostałych przypadkach wysyła `null`.
- **Link z Dashboardu musi przejść przez `select-year`.** Strona planu bierze rok z sesji, a sesja może wskazywać inny rok niż Dashboard. Zwykły `<Link>` do `month-plan` otworzyłby plan złego roku.

## Phase 1: Wspólny odczyt „do wydania”

### Overview

Wydzielenie składania stanu planu i liczby „do wydania” z `MonthPlanController` do klasy w `app/Support`, bez zmiany zachowania strony planu.

**Przed refaktorem**: uruchom podgląd z worktree (`AGENTS.md` „Preview from a worktree”) na kodzie sprzed fazy 1 i zanotuj liczbę „do wydania” oraz wiersze (kategorie, plan, faktyczne) bieżącego miesiąca strony „Plan miesiąca”. To punkt odniesienia dla kryterium 1.5; baza podglądu zostaje ta sama.

### Changes Required:

#### 1. Klasa odczytu

**File**: `app/Support/MonthToSpend.php` (nowy, `php artisan make:class Support/MonthToSpend --no-interaction`)

**Intent**: Jedno miejsce, które dla budżetu w wykonaniu i miesiąca składa odczyt planu, kwoty faktyczne, kategorie wierszy i liczbę „do wydania”. Kolejność i reguły przenosi 1:1 z `monthPlanPayload`: kategorie to suma id z planu i z operacji, `ordered()`, a liczba `null` dla `none`. Docblock zaznacza, że Set-aside (S-02) wejdzie tutaj.

**Contract**:

```php
final class MonthToSpend
{
    // Wołane tylko dla budżetu w wykonaniu.
    public static function forMonth(Budget $budget, int $month, CarbonImmutable $today): self;

    public readonly MonthPlanProposal $proposal;
    public readonly MonthPlanReading $reading;
    public readonly MonthCategoryActuals $actuals;
    /** @var Collection<int, Category> kategorie wierszy w kolejności budżetu */
    public readonly Collection $categories;

    public function toSpendMinorUnits(): ?int; // null, gdy $reading->state === 'none'

    /** @return array{state: 'proposal'|'approved'|'none', to_spend_minor_units: int|null} */
    public function summary(): array;
}
```

#### 2. Strona planu na wspólnym odczycie

**File**: `app/Http/Controllers/MonthPlanController.php`

**Intent**: `monthPlanPayload` korzysta z `MonthToSpend::forMonth`: wiersze buduje z `categories` / `reading` / `actuals`, a `to_spend_minor_units` bierze z `toSpendMinorUnits()`. `can_approve` używa `$toSpend->proposal->isBlockedByPreviousMonth`. Lokalne składanie `calculatorItems` znika. Kształt propsa `monthPlan` się nie zmienia.

**Contract**: prop `monthPlan` identyczny jak dziś (`MonthPlanController.php:92-102`); `itemRow` bez zmian.

#### 3. Test odczytu

**File**: `tests/Feature/MonthToSpendTest.php` (nowy; Feature, bo potrzebuje bazy i fabryk; styl klasy z metodami `test_*` jak `MonthPlanPageTest`)

**Intent**: Wąski test klasy:
- `summary()` dla propozycji bieżącego miesiąca,
- `summary()` dla zatwierdzonego minionego miesiąca,
- `none` → `to_spend_minor_units` `null`,
- zarchiwizowana kategoria z operacją w miesiącu wchodzi do `categories`.

Zgodność z przykładem 3730,00 pokrywa już `MonthPlanPageTest`.

**Contract**: przypadki brzegowe stanów z `MonthPlanProposal.php:83-127`.

### Success Criteria:

#### Automated Verification:

- Testy strony planu przechodzą bez zmian w pliku testu: `tests/Feature/MonthPlanPageTest.php` (obraz Sail na worktree, polecenie z `AGENTS.md` „Test database”)
- Nowy `tests/Feature/MonthToSpendTest.php` przechodzi
- `tests/Unit/ToSpendCalculatorTest.php` przechodzi
- Pint bez zmian po `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- Strona „Plan miesiąca” w podglądzie z worktree pokazuje dla bieżącego miesiąca tę samą liczbę i te same wiersze co zanotowane przed refaktorem

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznego sprawdzenia przed fazą 2.

---

## Phase 2: Kafelek „do wydania” na Dashboardzie

### Overview

Kafelek Month Remaining puli równomiernych zastępuje kafelek „do wydania” bieżącego miesiąca z odznaką przy propozycji i linkiem do strony planu.

### Changes Required:

#### 1. Prop Dashboardu

**File**: `app/Http/Controllers/DashboardController.php`

**Intent**: Dla budżetu w wykonaniu liczy `MonthToSpend::forMonth($budget, $today->month, $today)->summary()` i wysyła go jako `month_to_spend`. Wysyła `null`, gdy budżetu nie ma, gdy jest szkicem albo gdy stan to `none`; ten ostatni warunek jest strażnikiem typu, bo dla bieżącego miesiąca nie występuje. Przy okazji:
- `current_month` = `$today->month` dla każdego budżetu w wykonaniu (etykieta kafelka i linki powiadomień),
- prop `paced_pool_month_remaining_minor_units` znika,
- wywołanie `BudgetMonthReadings` i bramka `hasVisiblePacedExpenseCategory` znikają.

**Contract**: prop `month_to_spend: array{state: 'proposal'|'approved', to_spend_minor_units: int}|null`; `current_month: int|null` (`null` poza wykonaniem).

#### 2. Cel przekierowania `select-year`

**Files**: `app/Http/Requests/SelectBudgetYearRequest.php`, `app/Http/Controllers/BudgetController.php` (`selectYear`)

**Intent**: Opcjonalne pole `destination` pozwala po przełączeniu roku wrócić na stronę planu zamiast na Budżet. To ten sam ruch co „Otwórz budżet” (ADR-0019). Brak pola zachowuje dzisiejsze przekierowanie na `budget`.

**Contract**: reguła `destination` → `['sometimes', Rule::in(['budget', 'month-plan'])]`; `month-plan` → `to_route('month-plan')`, w pozostałych przypadkach `to_route('budget')`. Trasa się nie zmienia, więc Wayfinder nie wymaga regeneracji poza zwykłym `npm run build`.

#### 3. Kafelek w Vue

**File**: `resources/js/pages/Dashboard.vue`

**Intent**: Typ propsa `month_to_spend` zamiast `paced_pool_month_remaining_minor_units`. Widoczność kafelka i siatka 3/2 zależą od `month_to_spend !== null`. Kafelek ma:
- etykietę z nazwą miesiąca (nowy klucz),
- kwotę w obecnym kolorze tego kafelka,
- `Badge variant="outline"` z `t.month_plan.state_proposal` tylko przy `state === 'proposal'`,
- link „Plan miesiąca” (`t.nav.month_plan`) jako `<Form v-bind="BudgetController.selectYear.form()">` z ukrytymi `year=budget_year` i `destination=month-plan`, z przyciskiem `variant="link"` jak przy „Otwórz budżet”.

**Contract**: `month_to_spend: { state: 'proposal' | 'approved'; to_spend_minor_units: number } | null`; `current_month` bez zmian typu.

#### 4. Tłumaczenia

**File**: `lang/pl/ui.php`

**Intent**: Nowy klucz etykiety kafelka zamiast nieużywanego już `paced_pool_month_remaining`.

**Contract**: `dashboard.month_to_spend` = `':month — do wydania'` (mianownik nazwy miesiąca, jak dotąd); usunąć `dashboard.paced_pool_month_remaining`, jeśli grep nie znajdzie innego użycia.

#### 5. Testy

**Files**: `tests/Feature/DashboardTest.php`, `tests/Feature/BudgetTest.php`, `tests/Feature/MonthPlanPageTest.php`

**Intent**:
- `DashboardTest`:
  - `:31` i `:682`: `month_to_spend` `null`, `current_month` `null`.
  - `:709` przepisać na `month_to_spend` = `{state: proposal, to_spend_minor_units: -40_000}`, `current_month` 9. Nazwę zmienić na opis nowego zachowania.
  - `:741` przepisać: budżet z samą kategorią jednorazową ma kafelek z `0`, `current_month` 9.
  - Asercje `paced_pool_month_remaining_minor_units` usunąć.
- Nowy test w `DashboardTest`: zatwierdzony plan bieżącego miesiąca daje `state: approved`.
- `BudgetTest`, przy `:234`:
  - `destination=month-plan` przekierowuje na `month-plan` i ustawia rok sesji,
  - nieznany `destination` daje błąd walidacji,
  - brak pola nadal przekierowuje na `budget`.
- `MonthPlanPageTest` (nowy przypadek obok istniejących, na tym samym pomocniku `canonicalBudget()`; istniejące przypadki bez zmian): Dashboard 2026-06-15 daje `month_to_spend.to_spend_minor_units` = `373_000`, tyle samo co `monthPlan.to_spend_minor_units` ze strony planu w tym samym teście. Faza 3 dopisze tu Budżet.

**Contract**: asercje `assertInertia` na `month_to_spend` i `current_month`; brak klucza `paced_pool_month_remaining_minor_units` (`->missing(...)`).

#### 6. Dokumentacja Dashboardu

**File**: `.docs/specs/dashboard-hierarchy.md`

**Intent**: Opis kafelka zgodny z kodem (ADR-0033:7). Zmieniają się:
- historia 15: kafelek „do wydania” bieżącego miesiąca z odznaką przy propozycji i linkiem do planu,
- historia 16: kafelek nieobecny bez budżetu w wykonaniu, bez warunku kategorii równomiernych,
- decyzja „Headline tiles”: źródło `MonthToSpend`, etykieta `':month — do wydania'`,
- linia w Testing Decisions o puli Month Remaining.

Wzmiankę o powiadomieniach Paced zostawić dla S-07.

**Contract**: sekcje User Stories 15–16, Implementation Decisions „Headline tiles”, Testing Decisions „Cover:”.

### Success Criteria:

#### Automated Verification:

- `tests/Feature/DashboardTest.php`, `tests/Feature/BudgetTest.php`, `tests/Feature/MonthPlanPageTest.php` przechodzą (obraz Sail na worktree)
- `npm run build`, potem `npm run types:check` bez błędów
- `npm run check` bez ostrzeżeń
- Pint bez zmian po `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- Podgląd z worktree (`AGENTS.md` „Preview from a worktree”, `test@example.com` / `password`): kafelek „listopad — do wydania” z kwotą równą tej ze strony „Plan miesiąca”
- Przy niezatwierdzonym planie widać odznakę „Niezatwierdzony”; po zatwierdzeniu na stronie planu odznaka znika
- Przy roku sesji ustawionym na inny rok link „Plan miesiąca” otwiera plan bieżącego miesiąca bieżącego roku
- Siatka kafelków na telefonie (375 px) ułożona w kolumnę, od `sm` trzy kafelki obok siebie

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznego sprawdzenia przed fazą 3.

---

## Phase 3: „Do wydania” w pasku miesiąca strony Budżet

### Overview

Wiersz „Do wydania” ze stanem planu i linkiem w karcie „Miesiąc” strony Budżet oraz oznaczenie minionego miesiąca bez planu.

### Changes Required:

#### 1. Prop strony Budżet

**File**: `app/Http/Controllers/BudgetController.php` (`show`)

**Intent**: Dla budżetu w wykonaniu wysyła `MonthToSpend::forMonth($budget, $viewedMonth, $today)->summary()` jako `monthToSpend`. Wysyła `null` dla szkicu i braku budżetu. Pozostałe propsy miesiąca zostają bez zmian.

**Contract**: prop `monthToSpend: array{state: 'proposal'|'approved'|'none', to_spend_minor_units: int|null}|null`; gałąź bez budżetu (`:38-57`) dostaje `'monthToSpend' => null`.

#### 2. Pasek miesiąca

**Files**: `resources/js/pages/Budget.vue`, `resources/js/components/BudgetMonthStrip.vue`

**Intent**: `Budget.vue` typuje `monthToSpend` i przekazuje go do `BudgetMonthStrip`. Prop jest opcjonalny w pasku, więc warunek renderowania paska się nie zmienia. Pasek, między wyborem miesiąca a `BudgetMonthResultSummary`:
- `null` → nic,
- `state === 'none'` → wyciszony tekst `t.month_plan.none_title`,
- w pozostałych stanach wiersz z:
  - etykietą `t.month_plan.to_spend`,
  - kwotą `formatMoney` z `tabular-nums`, `text-destructive` < 0, jak na stronie planu,
  - odznaką `state_approved` (`secondary`) albo `state_proposal` (`outline`),
  - `<Link>` „Plan miesiąca” (`t.nav.month_plan`) na `monthPlan({ query: { month: viewedMonth } })` z `@/routes`.

Rok sesji jest tu ten sam co na stronie planu, więc `select-year` nie jest potrzebne.

**Contract**: `monthToSpend: { state: 'proposal' | 'approved' | 'none'; to_spend_minor_units: number | null } | null` w `Budget.vue`; ten sam typ jako opcjonalny prop `BudgetMonthStrip`.

#### 3. Testy

**Files**: `tests/Feature/MonthPlanPageTest.php` (zgodność liczby, na pomocniku `canonicalBudget()`), `tests/Feature/BudgetMonthResultTest.php` (pozostałe stany propsa `monthToSpend` paska miesiąca); `BudgetMonthRemainingTest.php` zostaje bez nowych przypadków, tylko jako regresja w kryterium 3.1

**Intent**: Dla strony Budżet:
- `MonthPlanPageTest`: scenariusz kontrolny (`?month=6`, 2026-06-15) → `monthToSpend` = `{proposal, 373_000}`, równe liczbie strony planu,
- `BudgetMonthResultTest`:
  - przyszły miesiąc → `proposal`,
  - miniony miesiąc z zatwierdzonym planem → `approved` i liczba z operacji na żywo,
  - miniony miesiąc bez planu → `{none, null}`,
  - szkic → `null`,
  - brak budżetu → `null`.

**Contract**: `assertInertia` na `monthToSpend.state` i `monthToSpend.to_spend_minor_units`.

#### 4. Dokumentacja PRD

**File**: `.docs/prd.md` (FR-REP-13, `:296`)

**Intent**: FR-REP-13 dopowiada, że Month Remaining jest odczytem tylko minionych miesięcy bez planu miesiąca. Bieżący i przyszłe miesiące oraz minione z zatwierdzonym planem pokazują na Dashboardzie i stronie Budżet „do wydania” (ADR-0033). Inne wzmianki o Month Remaining (FR-TRX-08 i ostrzeżenia) zostają dla S-08.

**Contract**: treść punktu FR-REP-13.

### Success Criteria:

#### Automated Verification:

- `tests/Feature/MonthPlanPageTest.php`, `tests/Feature/BudgetMonthRemainingTest.php`, `tests/Feature/BudgetMonthResultTest.php`, `tests/Feature/BudgetTest.php` przechodzą (obraz Sail na worktree)
- `npm run build`, potem `npm run types:check` bez błędów
- `npm run check` bez ostrzeżeń
- Pełna bramka CI na PostgreSQL: `composer ci:check` w obrazie Sail na worktree (`AGENTS.md` „Test database”)

#### Manual Verification:

- Podgląd z worktree, bieżący miesiąc: wiersz „Do wydania” z kwotą równą tej na Dashboardzie i stronie planu, odznaka zgodna ze stanem, link otwiera plan tego samego miesiąca
- Przyszły miesiąc (wybór w pasku): wiersz z odznaką „Niezatwierdzony”
- Miniony miesiąc bez planu: tekst „Ten miesiąc nie ma planu”, `Wynik` bez zmian
- Budżet w szkicu: pasek miesiąca wygląda jak przed zmianą
- Telefon (375 px): wiersz zawija się bez poziomego przewijania

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznego sprawdzenia.

---

## Testing Strategy

### Unit Tests:

- `ToSpendCalculatorTest` i jego bliźniak TS zostają bez zmian; wzór się nie zmienia.

### Integration Tests:

- `MonthToSpendTest`: stany `proposal` / `approved` / `none` i kategorie wierszy.
- `MonthPlanPageTest` (nowe przypadki): ta sama liczba (`373_000`) na Dashboardzie, stronie Budżet i stronie planu.
- `BudgetMonthResultTest`: stany i `null` propsa `monthToSpend` strony Budżet.
- `DashboardTest`: bramka kafelka (brak budżetu, szkic, wykonanie bez kategorii równomiernych), stan `approved`, `current_month`.
- `BudgetTest`: `destination` w `select-year`.
- `MonthPlanPageTest`: istniejące przypadki bez zmian jako dowód braku regresji refaktoru (faza 1 nie zmienia pliku; fazy 2–3 tylko dopisują przypadki zgodności).

### Manual Testing Steps:

1. Uruchomić podgląd z worktree według `AGENTS.md` i zalogować się jako `test@example.com` / `password`.
2. Porównać liczbę na Dashboardzie, stronie Budżet (bieżący miesiąc) i stronie planu.
3. Zatwierdzić plan i sprawdzić odznaki na Dashboardzie i stronie Budżet.
4. Przełączyć rok w powłoce na inny i kliknąć „Plan miesiąca” na Dashboardzie.
5. Na stronie Budżet wybrać przyszły i miniony miesiąc i sprawdzić odznakę oraz oznaczenie braku planu.

## Performance Considerations

Dashboard zamienia `BudgetMonthReadings` (zapytania wykonania rocznego) na `MonthToSpend`, czyli:
- najwyżej 5 zapytań `MonthPlanProposal` (`MonthPlanProposalTest`),
- 1 agregat `MonthCategoryActuals`,
- 1 zapytanie o kategorie.

Strona Budżet dokłada te same zapytania. Koszt jest stały i niezależny od liczby miesięcy. `MonthPlanProposal::forBudget` przeładowuje `$budget->categories`, co jest akceptowalne przy obecnej skali.

## Migration Notes

Brak migracji i zmian danych. Propsy Inertia zmieniają się razem z typami Vue w tym samym commicie każdej fazy.

## References

- Research: `context/changes/to-spend-replaces-remaining/research.md`
- Plan S-01: `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:75,79`
- Składanie liczby dziś: `app/Http/Controllers/MonthPlanController.php:103-153`
- Stany planu: `app/Support/MonthPlanProposal.php:83-127`
- Kafelek dziś: `app/Http/Controllers/DashboardController.php:87-111`, `resources/js/pages/Dashboard.vue:119-149,250-265`
- Pasek miesiąca: `resources/js/components/BudgetMonthStrip.vue`, `resources/js/pages/Budget.vue:277-297`
- Wzorzec przełączenia roku: `resources/js/pages/Dashboard.vue:338-356`, `app/Http/Controllers/BudgetController.php:168-175`
- Domena: `CONTEXT.md` (Month Remaining, To Spend), `.docs/adr/0033-month-plan-beside-annual-budget.md:7,9`
- Reguły: `AGENTS.md` („Inertia props contract”, „Month logic lives twice”), `.ai/rules/js.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Wspólny odczyt „do wydania”

#### Automated

- [x] 1.1 Testy strony planu przechodzą bez zmian w pliku testu: `tests/Feature/MonthPlanPageTest.php` — 02d0077
- [x] 1.2 Nowy `tests/Feature/MonthToSpendTest.php` przechodzi — 02d0077
- [x] 1.3 `tests/Unit/ToSpendCalculatorTest.php` przechodzi — 02d0077
- [x] 1.4 Pint bez zmian po `vendor/bin/pint --dirty --format agent` — 02d0077

#### Manual

- [x] 1.5 Strona „Plan miesiąca” w podglądzie z worktree pokazuje dla bieżącego miesiąca tę samą liczbę i te same wiersze co zanotowane przed refaktorem — 02d0077

### Phase 2: Kafelek „do wydania” na Dashboardzie

#### Automated

- [x] 2.1 `tests/Feature/DashboardTest.php`, `tests/Feature/BudgetTest.php`, `tests/Feature/MonthPlanPageTest.php` przechodzą — ee52811
- [x] 2.2 `npm run build`, potem `npm run types:check` bez błędów — ee52811
- [x] 2.3 `npm run check` bez ostrzeżeń — ee52811
- [x] 2.4 Pint bez zmian po `vendor/bin/pint --dirty --format agent` — ee52811

#### Manual

- [x] 2.5 Kafelek „listopad — do wydania” z kwotą równą tej ze strony „Plan miesiąca” — ee52811
- [x] 2.6 Odznaka „Niezatwierdzony” przy propozycji znika po zatwierdzeniu planu — ee52811
- [x] 2.7 Link „Plan miesiąca” przy innym roku sesji otwiera plan bieżącego miesiąca bieżącego roku — ee52811
- [x] 2.8 Siatka kafelków na telefonie w kolumnie, od `sm` trzy kafelki obok siebie — ee52811

### Phase 3: „Do wydania” w pasku miesiąca strony Budżet

#### Automated

- [x] 3.1 `tests/Feature/MonthPlanPageTest.php`, `BudgetMonthRemainingTest.php`, `BudgetMonthResultTest.php`, `BudgetTest.php` przechodzą — bababa4
- [x] 3.2 `npm run build`, potem `npm run types:check` bez błędów — bababa4
- [x] 3.3 `npm run check` bez ostrzeżeń — bababa4
- [x] 3.4 Pełna bramka CI na PostgreSQL: `composer ci:check` w obrazie Sail na worktree — bababa4

#### Manual

- [x] 3.5 Bieżący miesiąc: wiersz „Do wydania” zgodny z Dashboardem i stroną planu, odznaka zgodna ze stanem, link na ten sam miesiąc — bababa4
- [x] 3.6 Przyszły miesiąc: wiersz z odznaką „Niezatwierdzony” — bababa4
- [x] 3.7 Miniony miesiąc bez planu: „Ten miesiąc nie ma planu”, `Wynik` bez zmian — bababa4
- [x] 3.8 Budżet w szkicu: pasek miesiąca jak przed zmianą — bababa4
- [x] 3.9 Telefon (375 px): wiersz zawija się bez poziomego przewijania — bababa4
