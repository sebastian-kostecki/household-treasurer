# S-02: Odkładam / dobieram — plan implementacji

## Overview

Wycinek S-02 roadmapy (`context/foundation/roadmap.md`, „Odkładam / dobieram”). Użytkownik wpisuje w planie miesiąca jedną kwotę „odkładam / dobieram” i od razu widzi jej wpływ na „do wydania”. Kwota dodatnia („odkładam”) obniża „do wydania”, ujemna („dobieram”, Draw) je podnosi. Gdy |Draw| przekracza Spendable Liquidity, strona pokazuje ostrzeżenie, ale niczego nie blokuje i nie zmienia sald.

Domena jest rozstrzygnięta w `CONTEXT.md` (Set-aside, To Spend, Month Plan, Approved/Current Month Plan) i `.docs/adr/0033-month-plan-beside-annual-budget.md`. Otwartą regułę propozycji dla Set-aside (zostawioną przez F-01 do S-02) rozstrzyga ten plan.

PRD: FR-004, FR-016 (`context/foundation/prd.md:107,145`).

## Current State Analysis

- **Set-aside nie istnieje w kodzie.** Jedyny ślad to komentarz `app/Support/ToSpendCalculator.php:11` („equals 0 until S-02”). Nie ma kolumny, parametru wzoru, pola odczytu ani pola formularza (research §Summary).
- **Zapis planu:** `month_plans` ma `id`, `budget_id`, `month`, `approved_at`, timestamps i `unique(budget_id, month)` (`database/migrations/2026_11_02_091919_create_month_plans_table.php:14-21`). Model ma `#[Fillable(['budget_id', 'month', 'approved_at'])]` (`app/Models/MonthPlan.php:21`). Rekord powstaje dopiero przy zatwierdzeniu (`app/Actions/ApproveMonthPlan.php:58-62`), a propozycja jest liczona przy odczycie.
- **Pozycje mają parę zatwierdzona/bieżąca** (`month_plan_items.approved_amount_minor_units` / `current_amount_minor_units`). `ApproveMonthPlan.php:64-70` ustawia obie na tę samą kwotę. Odczyt planu zatwierdzonego bierze bieżącą (`app/Support/MonthPlanProposal.php:95-103`).
- **Odczyt:** `MonthPlanReading` ma `state`, `approvedAt`, `amounts` (`app/Support/MonthPlanReading.php:13-17`). Tworzy go tylko `MonthPlanProposal` (`:90,102,106,121`).
- **Źródło propozycji** to `MonthPlanProposal::sourceFor` (`:129-144`): poprzedni miesiąc w dowolnym stanie (łańcuch przez propozycje), dla stycznia grudzień poprzedniego budżetu w wykonaniu, a bez źródła `null`.
- **Wzór „do wydania”:** para `ToSpendCalculator::toSpendMinorUnits(array $items): int` (`app/Support/ToSpendCalculator.php:15-36`) i `toSpendMinorUnits(items)` (`resources/js/lib/to-spend-calculator.ts:13-33`). Każda strona ma dokładnie jednego wywołującego: `MonthPlanController.php:147-149` i `MonthPlan.vue:145-153`. Testy: `tests/Unit/ToSpendCalculatorTest.php`, `resources/js/lib/to-spend-calculator.test.ts`, po 5 tych samych przypadków.
- **Zatwierdzenie:** `ApproveMonthPlanRequest::rules()` waliduje tylko `items.*` z `min:0` (`app/Http/Requests/ApproveMonthPlanRequest.php:27-31`). `MonthPlanController::approve` składa `$amounts` i woła `handle($budget, $month, $amounts, $today)` (`:70-90`). Testy: `tests/Feature/ApproveMonthPlanTest.php` (pomocnik `approve()`), `tests/Feature/MonthPlanPageTest.php:221-322`.
- **Strona:**
  - Props `monthPlan` mają postać `state`, `approved_at`, `can_approve`, `to_spend_minor_units`, `income_items`, `expense_items` (`MonthPlanController.php:141-152`, typ `MonthPlan.vue:42-49`).
  - Edycja działa tylko przy `state === 'proposal' && can_approve` (`MonthPlan.vue:81-85`).
  - `<Form>` zaczyna się zaraz pod kartą „Do wydania” (`:285-323`).
  - Przełącznik segmentowy rodzaju pozycji (`:408-435`) jest wzorem dla przełącznika kierunku.
  - Przycisk jest wyłączony przy nieprawidłowej kwocie (`:470`).
- **Spendable Liquidity** liczy `(int) $user->accounts()->sum('current_balance_minor_units')` (`app/Http/Controllers/DashboardController.php:54`), łącznie z kontami zarchiwizowanymi. Odczyt jest zawsze na dziś. `AccountController.php:38` sumuje już załadowaną kolekcję. Polska etykieta to „Płynność dysponowalna” (`lang/pl/ui.php:112`).
- **Wzorce UI:**
  - Ostrzeżenie nieblokujące to `<Alert variant="warning">` + `AlertTriangle` + `AlertTitle` + `AlertDescription` (`.ai/rules/js.md`, `resources/js/components/OwnTransferFormDialog.vue:288-296`).
  - Zdanie z kwotami: `t.….replace(':x', formatMoney(...))` (`resources/js/pages/Budget.vue:101-120`, `lang/pl/ui.php:160`).
  - Teksty strony leżą w `lang/pl/ui.php` pod `month_plan`, a w TS mają typ `Record<string, string>` (`resources/js/types/shell.ts:41`), więc nowe klucze nie wymagają zmian typów.
- **Snapshot:** `SNAPSHOT_VERSION = 5` jest na tej gałęzi i na `development`, nie na `main`.
  - `serializeMonthPlan` zapisuje jawną listę pól (`app/Actions/BackupUserData.php:170-185`).
  - `restoreMonthPlans` tworzy plan z `budget_id`, `month`, `approved_at` (`app/Actions/RestoreUserData.php:253-257`).
  - Precedens pola bez podbicia wersji: `is_paced` z `?? true` (`ff76562`).
- **Gałęzie równoległe:** `approved-plan-changes` (S-03) i `month-items-plan-vs-actual` (S-04) mają tylko commit startowy. Nic nie koliduje.

## Desired End State

- `month_plans` ma `approved_set_aside_minor_units` i `current_set_aside_minor_units` (bigint ze znakiem, not null, domyślnie 0). Plany zatwierdzone przed S-02 czytają 0 bez migracji danych.
- **Propozycja Set-aside:** dodatnia kwota źródła propozycji przechodzi dalej. Źródłem jest poprzedni miesiąc w dowolnym stanie, a dla stycznia grudzień poprzedniego budżetu. Draw w źródle albo brak źródła dają 0. Plan zatwierdzony czyta `current_set_aside_minor_units`.
- „Do wydania” = dotychczasowy wzór − Set-aside, w PHP i TS, z tymi samymi testami. Przykład F-01 daje 1700,00 przy +500,00 i 2500,00 przy −300,00.
- Zatwierdzenie przyjmuje wymagane `set_aside_minor_units` (liczba całkowita w [−1e12, 1e12]) i zapisuje je w obu kolumnach. Draw ponad Spendable Liquidity jest przyjmowany. Zatwierdzenie nie zmienia żadnego salda konta.
- Strona „Plan miesiąca” ma pod kartą „Do wydania” kartę „Odkładam / dobieram”:
  - **edycja propozycji:** przełącznik „Odkładam” / „Dobieram” i nieujemna kwota. Liczba „do wydania” reaguje od razu, a nieprawidłowa kwota wyłącza „Zatwierdź”;
  - **tylko do odczytu** (propozycja zablokowana, plan zatwierdzony): „Odkładam 500,00 zł” albo „Dobieram 300,00 zł”;
  - **ostrzeżenie** `warning` pojawia się dla miesiąca bieżącego i przyszłych w każdym stanie, gdy kwota jest ujemna i |kwota| > dzisiejsza Spendable Liquidity. Dla miesiąca minionego nie pojawia się nigdy.
- `user:backup` zapisuje obie kwoty w wersji 5. `user:restore` przyjmuje plan bez tych kluczy z wartością 0.
- `CONTEXT.md`, ADR-0033 i PRD FR-ADM-01 opisują regułę propozycji i nowe pola snapshotu.

### Key Discoveries:

- Wyłącznie TS liczy warunek ostrzeżenia, bo musi on reagować przed zapisem. Serwer go nie sprawdza, ponieważ Draw nigdy nie jest blokowany (`CONTEXT.md:84`). Serwer podaje tylko `spendable_liquidity_minor_units` (lub `null` dla minionego miesiąca). Uzasadnienie dla sekcji „Month logic lives twice” w `AGENTS.md`: to nie jest kopia odczytu serwera, tylko jedyna implementacja porównania potrzebna podglądowi przed zatwierdzeniem.
- Karta Set-aside staje się pierwszym dzieckiem `<Form>` (`MonthPlan.vue:314`). Formularz zaczyna się zaraz pod kartą „Do wydania”, więc ukryte pole `set_aside_minor_units` trafia do wysyłki bez przenoszenia formularza.
- `parseMoneyInputToMinorUnits` przyjmuje znak minus (`resources/js/lib/format.ts:31-46`). Pole kwoty Set-aside musi więc samo odrzucać wartości ujemne, bo kierunek wyznacza przełącznik.

## What We're NOT Doing

- Zmiany Set-aside w planie zatwierdzonym. To S-03. Do tego czasu plan zatwierdzony przed S-02 ma 0 i tego nie da się zmienić.
- Powiadomienie Dashboardu o Draw. `CONTEXT.md:84` mówi o ostrzeżeniu, a nie o Dashboard notice. Liczby „do wydania” na Dashboardzie też nie ma (S-07).
- Odczytu salda na inną datę. Miesiąc przyszły porównujemy z saldem na dziś.
- Ostrzeżenia dla miesiąca minionego.
- Blokady zatwierdzenia przy Draw ponad Spendable Liquidity.
- Podbicia wersji snapshotu do 6.
- Podsumowania miesiąca (zamierzony i faktyczny Set-aside, Draw w roku). To S-09.
- Zmian w `AccountController` (suma z załadowanej kolekcji zostaje) i w nieużywanym kluczu `to_spend_hint`.

## Implementation Approach

Kolejność idzie od zapisu, przez odczyty serwera, do wzoru i strony, a kończy się formatem snapshotu. Faza 1 dodaje dane i regułę propozycji bez zmiany zachowania strony. Faza 2 zmienia razem obie kopie wzoru, request, akcję, propsy i widok. Kontrakt propsów i para kalkulatorów lądują więc w jednym commicie, jak wymaga `AGENTS.md`. Faza 3 domyka snapshot i dokumenty domeny.

Para zatwierdzona/bieżąca dla Set-aside wynika z domeny: `CONTEXT.md` liczy Set-aside jako pozycję Month Plan, a Approved i Current Month Plan dotyczą wszystkich pozycji. S-03 będzie zmieniać `current_*`, a S-09 porówna obie wartości.

## Phase 1: Zapis i propozycja

### Overview

Plan miesiąca przechowuje parę kwot Set-aside, a odczyt planu (zatwierdzonego i propozycji) niesie kwotę Set-aside według uzgodnionej reguły. Strona jeszcze z niej nie korzysta.

### Changes Required:

#### 1. Schemat

**File**: `database/migrations/<timestamp>_add_set_aside_to_month_plans_table.php` (nowa, przez `php artisan make:migration`)

**Intent**: Dodać parę kwot Set-aside do `month_plans` tak, by istniejące plany dostały 0 bez migracji danych.

**Contract**: `bigInteger('approved_set_aside_minor_units')->default(0)` i `bigInteger('current_set_aside_minor_units')->default(0)`, obie not null, ze znakiem, bez check constraint. `down()` usuwa obie kolumny. Nie edytujemy migracji tworzącej `month_plans`, bo `development` mogła już ją uruchomić.

#### 2. Model i fabryka

**File**: `app/Models/MonthPlan.php`, `database/factories/MonthPlanFactory.php`

**Intent**: Nowe kolumny dają się zapisać przez `create()` i są czytane jako `int`.

**Contract**:

- Oba pola trafiają do `#[Fillable]`, do `casts()` jako `integer` i do `@property int`.
- Fabryka ustawia w `definition()` obie wartości jawnie na 0, bo instancja po `create()` nie ma wartości domyślnych z bazy.

#### 3. Odczyt planu

**File**: `app/Support/MonthPlanReading.php`

**Intent**: Odczyt niesie kwotę Set-aside obok kwot kategorii.

**Contract**: nowe wymagane pole konstruktora `public readonly int $setAsideMinorUnits` (po `amounts`). Każde `new MonthPlanReading(...)` podaje je jawnie, a stan `none` daje 0.

#### 4. Reguła propozycji

**File**: `app/Support/MonthPlanProposal.php`

**Intent**: Plan zatwierdzony czyta bieżący Set-aside. Propozycja kopiuje tylko dodatni Set-aside ze źródła z `sourceFor`.

**Contract**:

- Gałąź `approved` (`:95-103`) przekazuje `$plan->current_set_aside_minor_units`.
- Gałąź `proposal` (`:109-121`) przekazuje `max(0, $source?->setAsideMinorUnits ?? 0)`.
- Oba wyniki `none` przekazują 0.
- `sourceFor` i liczba zapytań się nie zmieniają: kolumny są w już ładowanym `monthPlans`.

Przykłady (każdy jest przypadkiem testowym):

| Źródło | Propozycja |
| --- | --- |
| październik zatwierdzony z Set-aside +500,00 | listopad 500,00, grudzień 500,00 (łańcuch przez propozycję listopada) |
| październik zatwierdzony z −300,00 (Draw) | listopad 0 |
| brak źródła (miniony październik bez planu) | listopad 0 |
| grudzień poprzedniego budżetu zatwierdzony z +200,00 | styczeń 200,00 |
| grudzień poprzedniego budżetu zatwierdzony z −100,00 | styczeń 0 |
| plan zatwierdzony z approved 500,00 i current 700,00 | odczyt tego miesiąca 700,00 |

### Success Criteria:

#### Automated Verification:

- `tests/Feature/MonthPlanProposalTest.php` przechodzi i obejmuje 6 przykładów z tabeli reguły propozycji, a istniejący test liczby zapytań (`test_budget_loading_uses_a_fixed_number_of_queries`) przechodzi bez zmian.
- `tests/Feature/ApproveMonthPlanTest.php` i `tests/Feature/MonthPlanPageTest.php` przechodzą bez zmian (zachowanie strony i zatwierdzenia jeszcze się nie zmienia).
- `MonthPlanProposalTest` przechodzi na PostgreSQL przez obraz Sail: `docker run … sail-8.5/app artisan test --compact tests/Feature/MonthPlanProposalTest.php` (migracja z nowymi kolumnami działa na PostgreSQL).
- `vendor/bin/pint --dirty --format agent` przechodzi.

#### Manual Verification:

- Przegląd: testy propozycji odpowiadają tabeli przykładów jeden do jednego.

**Implementation Note**: Po zielonej automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed fazą 2. Bloki faz używają zwykłych punktorów; checkboxy są tylko w sekcji `## Progress`.

---

## Phase 2: Wzór, zatwierdzenie i strona

### Overview

Set-aside wchodzi do obu kopii wzoru, do zatwierdzenia i do strony: karta pod „Do wydania” z przełącznikiem, kwotą, podglądem na żywo i ostrzeżeniem o Draw.

### Changes Required:

#### 1. Wzór „do wydania” (para PHP/TS)

**File**: `app/Support/ToSpendCalculator.php`, `resources/js/lib/to-spend-calculator.ts`, `tests/Unit/ToSpendCalculatorTest.php`, `resources/js/lib/to-spend-calculator.test.ts`

**Intent**: Odjąć Set-aside na końcu wzoru, w obu kopiach razem, i usunąć komentarz „until S-02”.

**Contract**:

- PHP: `toSpendMinorUnits(array $items, int $setAsideMinorUnits): int`.
- TS: `toSpendMinorUnits(items: ToSpendItem[], setAsideMinorUnits: number): number`.
- Parametr jest wymagany, żeby żaden wywołujący go nie pominął. Wynik to dotychczasowa suma − `setAsideMinorUnits`.
- Istniejące przypadki podają 0 i zachowują swoje wartości.
- Nowe przypadki w obu testach, z przykładu F-01 (`month-plan-domain-decisions/plan.md:156`):
  - dochód: plan 600 000, bez wpływu;
  - Bill: plan 200 000, faktycznie 195 000, z kwotą dodatnią;
  - Running: plan 150 000 przy faktycznie 40 000;
  - Running: plan 30 000 przy faktycznie 35 000;
  - wyniki: Set-aside 50 000 → **170 000**, Set-aside −30 000 → **250 000**.

#### 2. Warunek ostrzeżenia o Draw (tylko TS)

**File**: `resources/js/lib/draw-warning.ts`, `resources/js/lib/draw-warning.test.ts` (nowe)

**Intent**: Jedna czysta funkcja mówi, czy Draw przekracza Spendable Liquidity. Strona woła ją w każdym stanie, a serwer jej nie potrzebuje, bo niczego nie blokuje. Leży w osobnym pliku, a nie w `to-spend-calculator.ts`, bo `AGENTS.md` opisuje ten plik jako kopię 1:1 `ToSpendCalculator.php`, a funkcja nie ma odpowiednika w PHP (wzorzec: `hand-entry-warning-preview.ts`).

**Contract**:

- `drawExceedsSpendableLiquidity(setAsideMinorUnits: number, spendableLiquidityMinorUnits: number | null): boolean` zwraca `liquidity !== null && setAside < 0 && -setAside > liquidity`. Nierówność jest ścisła.
- Przypadki testowe:
  - (−30 000, 30 000) → false (równość);
  - (−30 001, 30 000) → true;
  - (50 000, −10) → false (odkładanie nie ostrzega);
  - (−1, −500) → true (płynność ujemna, każdy Draw);
  - (0, −500) → false;
  - (−30 001, null) → false.
- Docblock mówi, że funkcja istnieje tylko w TS, bo ostrzeżenie musi reagować przed zatwierdzeniem, a serwer niczego nie blokuje.

#### 3. Spendable Liquidity jako helper

**File**: `app/Support/SpendableLiquidity.php` (nowy, przez `php artisan make:class`), `app/Http/Controllers/DashboardController.php:54`

**Intent**: Jedna definicja Spendable Liquidity dla Dashboardu i planu miesiąca zamiast trzeciej kopii zapytania.

**Contract**: `SpendableLiquidity::forUser(User $user): int` zwraca `(int) $user->accounts()->sum('current_balance_minor_units')`, łącznie z kontami zarchiwizowanymi (PRD FR-REP-09). `DashboardController` używa helpera, a jego prop `spendable_liquidity_minor_units` się nie zmienia.

#### 4. Walidacja i zatwierdzenie

**File**: `app/Http/Requests/ApproveMonthPlanRequest.php`, `app/Actions/ApproveMonthPlan.php`, `app/Http/Controllers/MonthPlanController.php` (`approve`)

**Intent**: Przyjąć podpisaną kwotę Set-aside i zapisać ją w obu kolumnach. Nic nie sprawdza jej względem sald.

**Contract**:

- Reguła `'set_aside_minor_units' => ['required', 'integer', 'min:-1000000000000', 'max:1000000000000']`.
- Sygnatura `ApproveMonthPlan::handle(Budget $budget, int $month, array $amounts, int $setAsideMinorUnits, CarbonImmutable $today): MonthPlan`. `MonthPlan::create` ustawia `approved_set_aside_minor_units` i `current_set_aside_minor_units` na tę kwotę.
- Kontroler przekazuje `(int) $request->validated('set_aside_minor_units')`.
- Pomocnik `approve()` w `ApproveMonthPlanTest` dostaje parametr Set-aside z domyślnym 0. Wysyłki w `MonthPlanPageTest` dodają `set_aside_minor_units`.

#### 5. Propsy strony

**File**: `app/Http/Controllers/MonthPlanController.php` (`monthPlanPayload`, docblock), `resources/js/pages/MonthPlan.vue` (typ `MonthPlanReading`)

**Intent**: Strona dostaje kwotę Set-aside i dzisiejszą Spendable Liquidity tam, gdzie ostrzeżenie ma sens. Liczba „do wydania” uwzględnia Set-aside.

**Contract**:

- Dwa nowe klucze `monthPlan`, zmieniane po obu stronach w jednym commicie:
  - `set_aside_minor_units: int`, czyli `$reading->setAsideMinorUnits` (0 dla `none`);
  - `spendable_liquidity_minor_units: int|null`, czyli `SpendableLiquidity::forUser($request->user())` dla `timing !== 'past'` i `state !== 'none'`, w przeciwnym razie `null`.
- `to_spend_minor_units` woła kalkulator z `$reading->setAsideMinorUnits`.
- `monthPlanPayload` dostaje użytkownika jako parametr.
- Typ TS: `set_aside_minor_units: number; spendable_liquidity_minor_units: number | null;`.

#### 6. Karta „Odkładam / dobieram”

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Pod kartą „Do wydania” użytkownik wybiera kierunek i wpisuje kwotę. Podgląd liczby i ostrzeżenie reagują od razu, a plan tylko do odczytu pokazuje kierunek i kwotę.

**Contract**:

- Karta jest pierwszym dzieckiem `<Form>` i widać ją, gdy `state !== 'none'`.
- Stan lokalny:
  - kierunek `'set_aside' | 'draw'` oraz tekst kwoty, inicjowane z `set_aside_minor_units` (ujemna → `draw` i wartość bezwzględna);
  - reset w tym samym `watch` co `amountInputs` (`:98-103`).
- Edycja (`isEditable`):
  - przełącznik segmentowy jak przy rodzaju pozycji (`:408-435`), `role="group"`, `aria-pressed`, przyciski `type="button"`;
  - `Input` kwoty z `inputmode="decimal"`. Kwota jest poprawna, gdy `parseMoneyInputToMinorUnits` daje wartość ≥ 0 i ≤ `MAX_PLAN_AMOUNT_MINOR_UNITS`;
  - wartość podpisana to `draw ? -kwota : kwota`, a 0 zapisujemy jako 0, bez −0;
  - ukryte pole `set_aside_minor_units` niesie tę wartość;
  - nieprawidłowa kwota wyłącza „Zatwierdź” (rozszerzenie `allAmountsValid`);
  - `InputError` pokazuje `errors.set_aside_minor_units`.
- Poza edycją karta pokazuje tekst „Odkładam” albo „Dobieram” i `formatMoney(|kwota|)` z `tabular-nums`. Kwota 0 daje „Odkładam 0,00 zł”.
- Podgląd `toSpendPreviewMinorUnits` w edycji przekazuje do `toSpendMinorUnits` podpisaną wartość z formularza (nieprawidłowa daje 0). Poza edycją bierze liczbę z serwera, jak dotąd.
- Ostrzeżenie:
  - `<Alert variant="warning">` z `AlertTriangle`, `AlertTitle` i `AlertDescription`, w karcie;
  - widoczne, gdy `drawExceedsSpendableLiquidity(wartość, spendable_liquidity_minor_units)`, gdzie wartość to w edycji podpisana wartość z formularza (nieprawidłowa nie ostrzega), a poza edycją prop;
  - zdanie składane przez `.replace(':draw', …)` i `.replace(':liquidity', …)` z `formatMoney`.
- Tylko tokeny kolorów, bez nowych komponentów w `components/ui/*`.

#### 7. Teksty

**File**: `lang/pl/ui.php` (`month_plan`)

**Intent**: Polskie teksty karty i ostrzeżenia, spójne z `CONTEXT.md` („odkładam / dobieram”, „Płynność dysponowalna”).

**Contract**: nowe klucze (brzmienie do dopracowania przy implementacji, sens bez zmian):

- `set_aside` → „Odkładam / dobieram”;
- `set_aside_direction` → „Kierunek”;
- `set_aside_save` → „Odkładam”;
- `set_aside_draw` → „Dobieram”;
- `set_aside_amount` → „Kwota”;
- `set_aside_hint` → „Odkładanie zmniejsza kwotę do wydania, dobieranie ją zwiększa. Nie przenosi pieniędzy między kontami.”;
- `draw_warning_title` → „Uwaga”;
- `draw_warning` → „Dobierasz :draw, więcej niż dzisiejsza płynność dysponowalna (:liquidity).”

### Success Criteria:

#### Automated Verification:

- `tests/Unit/ToSpendCalculatorTest.php` przechodzi: dotychczasowe 5 przypadków z Set-aside 0 oraz przypadki 170 000 i 250 000.
- `npm test` przechodzi, w tym `to-spend-calculator.test.ts` z tymi samymi przypadkami wzoru co test PHP oraz `draw-warning.test.ts` z 6 przypadkami `drawExceedsSpendableLiquidity`.
- `tests/Feature/ApproveMonthPlanTest.php` przechodzi i obejmuje zapis Set-aside w obu kolumnach (+50 000 i −30 000).
- `tests/Feature/MonthPlanPageTest.php` przechodzi i obejmuje (`assertInertia`):
  - `set_aside_minor_units` w propozycji skopiowanej z zatwierdzonego miesiąca z +50 000 oraz `to_spend_minor_units` niższe o 50 000 od wartości bez Set-aside;
  - `spendable_liquidity_minor_units` równe sumie sald kont, także zarchiwizowanego, dla miesiąca bieżącego i przyszłego;
  - `spendable_liquidity_minor_units` równe `null` dla minionego zatwierdzonego;
  - zatwierdzenie przez trasę z `set_aside_minor_units = -30000`, gdy suma sald kont jest mniejsza niż 30 000: przekierowanie bez błędów, −30 000 w obu kolumnach planu, `accounts.current_balance_minor_units` bez zmian (Draw ponad Spendable Liquidity nie jest blokowany na żadnej warstwie);
  - odrzucenie braku `set_aside_minor_units`, wartości niecałkowitej i wartości poza ±1e12.
- `tests/Feature/DashboardTest.php` przechodzi bez zmian asercji.
- Powyższe testy feature przechodzą na PostgreSQL: `docker run … sail-8.5/app artisan test --compact tests/Feature/ApproveMonthPlanTest.php tests/Feature/MonthPlanPageTest.php tests/Feature/DashboardTest.php`.
- `npm run build`, `npm run check` i `npm run types:check` przechodzą.
- `vendor/bin/pint --dirty --format agent` przechodzi.

#### Manual Verification:

- Na propozycji bieżącego miesiąca wybranie „Odkładam” i wpisanie 500 obniża „do wydania” o 500,00 zł od razu. Przełączenie na „Dobieram” podnosi je o 500,00 zł względem stanu bez kwoty.
- „Dobieram” z kwotą większą niż płynność dysponowalna pokazuje ostrzeżenie `warning` z obiema kwotami. Kwota równa płynności nie ostrzega. „Zatwierdź” działa mimo ostrzeżenia, a salda na stronie Konta się nie zmieniają.
- Wpisanie „-5” albo „abc” w kwotę wyłącza „Zatwierdź”.
- Po zatwierdzeniu karta pokazuje „Dobieram 300,00 zł” tylko do odczytu, a ostrzeżenie zostaje, jeśli warunek nadal zachodzi. Kolejny miesiąc proponuje 0. Po zatwierdzeniu „Odkładam 500” kolejny miesiąc proponuje 500,00 zł.
- Miniony miesiąc z zatwierdzonym planem pokazuje kwotę, nigdy ostrzeżenia.
- Widok jest czytelny na szerokości telefonu, a przełącznik da się obsłużyć klawiaturą.

**Implementation Note**: Po zielonej automatycznej weryfikacji zbuduj podgląd z worktree (sekcja „Preview from a worktree” w `AGENTS.md`) i zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: Snapshot i dokumenty

### Overview

`user:backup` / `user:restore` przenoszą parę kwot Set-aside w wersji 5. Snapshot v5 bez tych kluczy (np. zrobiony na `development` przed S-02) przywraca się z 0. Dokumenty domeny zapisują regułę propozycji.

### Changes Required:

#### 1. Eksport

**File**: `app/Actions/BackupUserData.php`

**Intent**: Plan w snapshocie niesie obie kwoty Set-aside.

**Contract**: `serializeMonthPlan` (`:170-185`) dodaje `approved_set_aside_minor_units` i `current_set_aside_minor_units` obok `approved_at`. `SNAPSHOT_VERSION` zostaje 5.

#### 2. Przywracanie

**File**: `app/Actions/RestoreUserData.php`

**Intent**: Odtworzyć obie kwoty, a brak klucza traktować jak 0 (precedens `is_paced`).

**Contract**: `MonthPlan::create` w `restoreMonthPlans` (`:253-257`) dostaje `approved_set_aside_minor_units => $monthPlanData['approved_set_aside_minor_units'] ?? 0` i analogicznie `current_…`. Każdy klucz jest domyślny osobno. Lista wersji i wymagane klucze się nie zmieniają.

#### 3. Dokumenty

**File**: `CONTEXT.md` (Set-aside, `:83-85`), `.docs/adr/0033-month-plan-beside-annual-budget.md` (akapit reguły propozycji), `.docs/prd.md` (FR-ADM-01, `:304`)

**Intent**: Regułę propozycji Set-aside i nowe pola snapshotu da się przeczytać w dokumentach, nie tylko w kodzie.

**Contract**:

- `CONTEXT.md`, wpis Set-aside: jedno zdanie o tym, że Set-aside ma Approved i Current jak kwoty kategorii, a Proposal kopiuje dodatni Set-aside ze źródła reguły propozycji (ADR-0033), Draw albo brak źródła dają 0.
- ADR-0033: jedno zdanie w akapicie reguły propozycji z tą samą regułą i oznaczeniem, że rozstrzygnął ją S-02.
- FR-ADM-01: plany miesięcy z kwotą odkładam/dobieram, a brak tej kwoty w planie przywraca 0. Wersja pozostaje 5, README bez zmian.

### Success Criteria:

#### Automated Verification:

- `tests/Feature/UserSnapshotCommandTest.php` przechodzi i obejmuje:
  - okrągłą podróż planu z approved 50 000 i current −30 000 (fabryka) z przemapowanymi ID;
  - przywrócenie snapshotu v5 z planem bez obu kluczy Set-aside, dające 0 i 0;
  - dotychczasowe przypadki (v4, v5 bez `month_plans`, wadliwe plany) bez zmian.
- `UserSnapshotCommandTest` przechodzi na PostgreSQL przez obraz Sail.
- Pełna bramka CI przechodzi na obrazie Sail: `docker run … --entrypoint composer sail-8.5/app ci:check`.

#### Manual Verification:

- `user:backup` na danych z zatwierdzonym planem z Draw, potem `user:restore` na innym użytkowniku. Strona planu pokazuje tę samą kwotę „Dobieram” i tę samą liczbę „do wydania”.
- Przegląd: zdania w `CONTEXT.md`, ADR-0033 i FR-ADM-01 zgadzają się z regułą z fazy 1.

**Implementation Note**: Po zielonej automatycznej weryfikacji poproś o ręczne potwierdzenie i o uruchomienie pełnego zestawu testów.

---

## Testing Strategy

### Unit Tests:

- Para wzoru: te same przypadki w PHP i TS, czyli dotychczasowe z Set-aside 0 oraz 170 000 i 250 000 z F-01.
- `drawExceedsSpendableLiquidity`: granica równości, płynność ujemna, odkładanie, zero, `null`.

### Integration Tests:

- `MonthPlanProposalTest`: 6 przykładów reguły propozycji, w tym łańcuch przez propozycję, styczeń z grudnia poprzedniego budżetu i odczyt bieżącej kwoty planu zatwierdzonego.
- `ApproveMonthPlanTest`: zapis obu kolumn.
- `MonthPlanPageTest`: nowe propsy po obu stronach kontraktu, `null` dla minionego miesiąca, walidacja zakresu i wymagalności, zatwierdzenie przez trasę z Draw ponad płynność bez blokady i bez zmiany sald.
- `UserSnapshotCommandTest`: okrągła podróż i brak kluczy w v5.
- Testy z migracją i zapytaniami sald uruchamiane na PostgreSQL przez obraz Sail.

### Manual Testing Steps:

1. Podgląd z worktree (`AGENTS.md`, „Preview from a worktree”), logowanie `test@example.com` / `password`.
2. Bieżący miesiąc: „Odkładam 500”, obserwacja liczby, zatwierdzenie, odczyt kolejnego miesiąca (propozycja 500).
3. Kolejny miesiąc: „Dobieram” powyżej płynności, ostrzeżenie, zatwierdzenie mimo ostrzeżenia, strona Konta bez zmian sald, następny miesiąc proponuje 0.
4. Wąski ekran i obsługa przełącznika klawiaturą.

## Performance Considerations

Jedno dodatkowe zapytanie `sum` na odczyt strony planu dla miesiąca bieżącego i przyszłego. Kolumny Set-aside przychodzą w już ładowanym `monthPlans`, więc liczba zapytań propozycji się nie zmienia.

## Migration Notes

- Nowa migracja dodaje kolumny z domyślnym 0. Plany zatwierdzone przed S-02 (możliwe na `development`) czytają 0 i do S-03 nie da się tego zmienić. To akceptowany skutek kolejności wycinków.
- Wycofanie: `down()` usuwa obie kolumny. Snapshoty v5 z nowymi kluczami przywrócone na kodzie sprzed S-02 tracą tylko Set-aside, bo klucze są ignorowane.
- S-03 (`approved-plan-changes`) powinien wyjść od gałęzi z S-02, bo zmienia te same pliki i `current_set_aside_minor_units`.

## References

- Research: `context/changes/set-aside-and-draw/research.md`
- Domena: `CONTEXT.md:67-85,159-160`, `.docs/adr/0033-month-plan-beside-annual-budget.md`
- Decyzje F-01: `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:40,156`
- Plan S-01: `context/archive/2026-11-02-month-plan-and-to-spend/plan.md`
- Wzorce: `resources/js/pages/MonthPlan.vue:408-435` (przełącznik), `resources/js/components/OwnTransferFormDialog.vue:288-296` (ostrzeżenie), `resources/js/pages/Budget.vue:101-120` (zdanie z kwotami), `app/Actions/RestoreUserData.php:420-429` (domyślna wartość przy odczycie)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Zapis i propozycja

#### Automated

- [x] 1.1 MonthPlanProposalTest obejmuje 6 przykładów reguły propozycji, test liczby zapytań bez zmian — 54cf49e
- [x] 1.2 ApproveMonthPlanTest i MonthPlanPageTest przechodzą bez zmian — 54cf49e
- [x] 1.3 MonthPlanProposalTest przechodzi na PostgreSQL — 54cf49e
- [x] 1.4 Pint przechodzi — 54cf49e

#### Manual

- [x] 1.5 Testy propozycji odpowiadają tabeli przykładów — 54cf49e

### Phase 2: Wzór, zatwierdzenie i strona

#### Automated

- [x] 2.1 ToSpendCalculatorTest z Set-aside 0 oraz 170 000 i 250 000
- [x] 2.2 npm test z parą wzoru i drawExceedsSpendableLiquidity
- [x] 2.3 ApproveMonthPlanTest: zapis obu kolumn
- [x] 2.4 MonthPlanPageTest: nowe propsy, null dla minionego, Draw ponad płynność przez trasę bez zmiany sald, walidacja
- [x] 2.5 DashboardTest bez zmian asercji
- [x] 2.6 Testy feature fazy przechodzą na PostgreSQL
- [x] 2.7 npm run build, check i types:check przechodzą
- [x] 2.8 Pint przechodzi

#### Manual

- [x] 2.9 Odkładam/dobieram zmienia „do wydania” od razu
- [x] 2.10 Ostrzeżenie o Draw ponad płynność, bez blokady i bez zmiany sald
- [x] 2.11 Nieprawidłowa kwota wyłącza Zatwierdź
- [x] 2.12 Plan zatwierdzony tylko do odczytu i reguła propozycji w kolejnym miesiącu
- [x] 2.13 Miniony miesiąc bez ostrzeżenia
- [x] 2.14 Wąski ekran i obsługa klawiaturą

### Phase 3: Snapshot i dokumenty

#### Automated

- [ ] 3.1 UserSnapshotCommandTest: okrągła podróż i v5 bez kluczy Set-aside
- [ ] 3.2 UserSnapshotCommandTest przechodzi na PostgreSQL
- [ ] 3.3 Pełna bramka CI na obrazie Sail

#### Manual

- [ ] 3.4 Backup i restore zachowują Dobieram i liczbę do wydania
- [ ] 3.5 Dokumenty zgodne z regułą propozycji
