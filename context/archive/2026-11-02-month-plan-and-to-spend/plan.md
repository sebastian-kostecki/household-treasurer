# S-01: Plan miesiąca i liczba „do wydania” — plan implementacji

## Overview

Wycinek S-01 roadmapy M-1. Użytkownik otwiera nową stronę „Plan miesiąca” i widzi:

- propozycję kwot dla kategorii dochodów i wydatków, z oznaczeniem „niezatwierdzony”,
- liczbę „do wydania” liczoną już z propozycji.

Na tej stronie poprawia kwoty, a liczba reaguje od razu. Po poprawkach zatwierdza plan. Po zatwierdzeniu liczba zmienia się wraz z operacjami. Na kategorii wydatku pojawia się rodzaj pozycji: „rachunek” albo „bieżąca”. Snapshot obejmuje nowe dane i przechodzi na wersję 5.

Domena jest rozstrzygnięta w `.docs/adr/0033-month-plan-beside-annual-budget.md` oraz `CONTEXT.md` (Month Plan, Approved/Current Month Plan, Month Item Kind, To Spend). Plan nie zmienia tych reguł, tylko je implementuje.

PRD: US-01, FR-001, FR-002, FR-005, FR-015, FR-018, FR-019.

## Current State Analysis

- **Kodu planu miesiąca nie ma.** Nie ma tabeli, modelu, trasy ani rodzaju pozycji (research §Summary 1). Plan roczny to jedna liczba: `categories.planned_amount_minor_units`.
- **Model danych przyjmie tabele potomne.**
  - `categories.budget_id` ma `cascadeOnDelete` (`database/migrations/2026_09_07_180001_create_categories_table.php:17`).
  - `ResetUserData` usuwa `budgets` jednym `delete()` (`app/Actions/ResetUserData.php:14-26`).
- **Średnia z rocznego istnieje tylko jako prywatna arytmetyka** w bliźniaczym kalkulatorze `Month Remaining` (`app/Support/MonthRemainingCalculator.php:81-90,147-158`). Ten kalkulator ma kopię TS, a `AGENTS.md` wymaga zmieniania obu kopii razem.
- **Wykonanie per kategoria i miesiąc** liczy `BudgetMonthExecutionIndex` (`app/Support/BudgetMonthExecutionIndex.php:71-93`). Ma trzy ograniczenia:
  - obejmuje tylko wydatki,
  - liczy cały rok przez `whereYear`,
  - nie wie, czy w miesiącu była kwota dodatnia.
- **Znaki kwot:** dochód jest zapisywany dodatnio (`min:1`). Zwrot to wiersz wydatku z kwotą ujemną (`ExpenseValidationRules.php:25`, `ManageFinancialOperation.php:65-90`). Przelewy własne i ruchy kapitału leżą w osobnych tabelach, więc zapytanie po `financial_operations` samo je pomija.
- **Kategorie:**
  - `is_paced` to wzorzec dla nowej cechy. Ma kolumnę z domyślną wartością (`2026_09_10_195354_add_is_paced_to_categories_table.php:12`), walidację `withPacedFlagValidation` (`app/Concerns/CategoryValidationRules.php:22-54`) i białą listę w `CategoryController::update` (`:48-58`).
  - Flagę kopiuje `CreateBudget::copyCategories` (`app/Actions/CreateBudget.php:44`), a przywraca ją `restoredCategoryIsPaced` (`app/Actions/RestoreUserData.php:344-351`).
  - Para nazwa+typ nie jest unikalna (`2026_09_07_180001_create_categories_table.php:29-30`).
- **Polityki:** `BudgetPolicy` ma prywatne `ownsBudget` (`app/Policies/BudgetPolicy.php:41-44`) i metody sprawdzające fazę (`:25-39`). Kontrolery wołają `$this->authorize(...)`, a FormRequesty `user()->can(...)`.
- **UI:**
  - Strona Budżet bierze rok z sesji (`app/Support/SelectedBudgetYear.php:10-35`), a miesiąc z `?month=N` (`app/Support/BudgetViewedMonth.php:9-33`).
  - `BudgetMonthPicker.vue:27-38` ma trasę `budget` wpisaną na sztywno.
  - `categories.update` przekierowuje zawsze na `budget` (`CategoryController.php:67`).
  - Wzorzec formularza zbiorczego z kwotami to `OperationsImportPreview.vue:282-364` z indeksowanymi ukrytymi polami `*_minor_units` (`ImportPreviewRow.vue:455-475`).
  - Pomocnicze funkcje kwot leżą w `resources/js/lib/format.ts:4,18,31`.
- **Snapshot:**
  - `BackupUserData::SNAPSHOT_VERSION = 4` (`app/Actions/BackupUserData.php:17`).
  - Przywracanie przyjmuje `[1, 2, 3, SNAPSHOT_VERSION]` (`app/Actions/RestoreUserData.php:76`), więc po podbiciu stałej wersja 4 wypadłaby z listy.
- **Testy:**
  - Testy feature budżetu i kategorii to klasy PHPUnit z `RefreshDatabase`, np. `tests/Feature/CategoryPacedFlagTest.php` (store/update, `assertInertia` `:133-141`, snapshot `:220-274`).
  - Testy unit kalkulatorów to closures Pest (`tests/Unit/MonthRemainingCalculatorTest.php`).
  - Fabryki: `Budget::factory()->execution()`, `Category::factory()->expense()|income()|oneOff()|archived()`.
  - Operację w danym miesiącu tworzy się z jawnym `category_id`, `budget_id` i `operation_date` (`tests/Feature/HandEntryMonthRemainingTest.php:26-31`).

## Desired End State

- Kategoria wydatku ma `month_item_kind` (`bill` | `running`, domyślnie `running`).
  - Istniejące wiersze dostają `running` bez ręcznej migracji.
  - Rodzaj ustawia się w dialogu kategorii albo przełącznikiem na stronie planu.
  - Rodzaj kopiuje się na nowy budżet jak `is_paced`.
  - Dla dochodu i kategorii zarchiwizowanej zmiana rodzaju jest odrzucana.
- Strona `GET /month-plan?month=N` pokazuje dla roku z sesji i budżetu w wykonaniu:
  - **bieżący i przyszłe miesiące:** propozycję z oznaczeniem „niezatwierdzony” albo plan zatwierdzony (tylko do odczytu do S-03), wiersze dochodów i wydatków z planem i kwotą faktyczną oraz liczbę „do wydania”,
  - **miniony miesiąc z zatwierdzonym planem:** ten plan i jego „do wydania”, tylko do odczytu,
  - **miniony miesiąc bez planu:** komunikat „bez planu”.
- Poprawki kwot w propozycji natychmiast zmieniają podgląd „do wydania” (kopia TS wzoru). „Zatwierdź” zapisuje plan z kwotą zatwierdzoną i bieżącą, obie równe kwocie z formularza.
- Liczba „do wydania” jest zgodna z `CONTEXT.md` (To Spend) i jest aktualna przy kolejnym odczycie po każdej operacji i Imporcie. Odkładam/dobieram jest w S-01 równe 0.
- `user:backup` zapisuje wersję 5 z rodzajem pozycji i planami miesięcy. `user:restore` przyjmuje wersje 1–5.
- Dashboard, strona Budżet, `Month Remaining`, powiadomienia i ostrzeżenia przy operacjach działają jak dziś.

### Key Discoveries:

- Reguła propozycji jest rekurencyjna i obejmuje dowolny stan poprzedniego miesiąca (ADR-0033 ¶2). Ponieważ zapisujemy tylko plany zatwierdzone, „poprzedni miesiąc w stanie propozycji” jest zawsze wyliczany tą samą regułą, nigdy czytany z bazy.
- `Month Remaining` i jego cztery kopie (research §2) zostają nietknięte. Arytmetyki średniej nie wydzielamy z `MonthRemainingCalculator`, bo wymusiłoby to zmianę pary PHP/TS z `AGENTS.md`. Nowa klasa propozycji ma własną kopię `floorDivide` i własny test.
- `categories.update` przekierowuje na `budget`, więc przełącznik rodzaju na stronie planu potrzebuje osobnego endpointu z `back()`. Bez tego `preserveState` nie zachowa niezapisanych kwot.
- `BudgetMonthPicker` trzeba sparametryzować trasą, bo dziś zna tylko `budget`. `BudgetMonthStrip` nie ma własnych linków i zostaje bez zmian, a `MonthPlan.vue` używa pickera bezpośrednio.
- Rodzaj pozycji jest cechą kategorii, nie pozycji planu. Odczyt minionego miesiąca z planem zatwierdzonym używa dzisiejszego rodzaju, więc przełącznik jest dostępny tylko dla miesiąca bieżącego i przyszłych (`can_change_kind`).
- Przełącznik roku (`BudgetYearSwitcher` w `AppSidebar`) wysyła `budget.select-year`, a ten zawsze przekierowuje na `budget` (`BudgetController.php:168-175`). W S-01 zmiana roku na stronie planu świadomie ląduje na stronie Budżet.

## What We're NOT Doing

- **„Do wydania” na Dashboardzie i stronie Budżet** oraz zastąpienie `Pozostało w miesiącu`: to S-06. Propsy `paced_pool_month_remaining_minor_units` i `pacedPoolMonthRemainingMinorUnits` zostają bez zmian.
- **Odkładam/dobieram:** to S-02. W S-01 Set-aside = 0, bez kolumny i bez pola.
- **Zmiana zatwierdzonego planu:** to S-03. Plan zatwierdzony jest tylko do odczytu, choć schemat już rozdziela kwotę zatwierdzoną od bieżącej.
- **Widok plan vs faktycznie z odchyleniami, sekcja jednorazowych z resztą rocznego limitu, tempo:** to S-04 i S-05. Strona S-01 pokazuje tylko plan, kwotę faktyczną i liczbę.
- **Powiadomienia planu miesiąca i wyłączenie `paced_month_remaining`:** to S-07.
- **Ostrzeżenia przy operacji i w Imporcie:** to S-08.
- **Podsumowanie miesiąca:** to S-09. **Porównanie z rokiem:** to S-10.
- **Zapis szkicu propozycji bez zatwierdzenia:** odrzucone w wywiadzie. Propozycja jest zawsze wyliczana.
- **Wydzielenie wspólnej arytmetyki z `MonthRemainingCalculator`.**
- **Unikalność nazwy kategorii.**
- **Naprawa przemapowania `import_identity_account_id` w restore** (research §3): poza zakresem.

## Implementation Approach

Na serwerze są trzy czyste odczyty i jedna akcja:

1. **`MonthPlanProposal`.** Ustala, jakie kwoty ma miesiąc M budżetu B. Kolejność:
   - zapisany plan zatwierdzony, jeśli istnieje,
   - „brak planu” dla minionego miesiąca bez zapisu,
   - propozycja z reguły ADR-0033, w pozostałych przypadkach.
2. **`MonthCategoryActuals`.** Jedno zapytanie agregujące na miesiąc: suma ze znakiem per kategoria oraz flaga, czy była kwota dodatnia.
3. **`ToSpendCalculator`.** Czysty wzór. Ma kopię TS, która daje podgląd w trakcie edycji. Uzasadnienie kopii: poprawek propozycji nie da się zapisać bez zatwierdzenia, więc bez podglądu użytkownik zatwierdzałby na ślepo (decyzja z wywiadu, zgodna z `AGENTS.md` „Month logic lives twice”).
4. **`ApproveMonthPlan`.** Zapisuje `month_plans` i `month_plan_items` w transakcji.

Kolejność faz idzie od danych do UI:

1. cecha kategorii,
2. model i odczyty, w pełni przetestowane bez UI,
3. strona,
4. snapshot.

### Reguła propozycji (dokładne predykaty)

`today` = `CarbonImmutable::today('UTC')`, jak na stronie Budżet i Dashboardzie. Miesiąc M budżetu roku Y jest:

- minionym, gdy (Y, M) < (today.year, today.month),
- bieżącym, gdy jest równy,
- przyszłym w pozostałych przypadkach.

`planFor(B, M)`:

1. **Zapisany plan zatwierdzony.** Wynik: stan `approved`, kwoty z `current_amount_minor_units` jego pozycji.
2. **M miniony, bez zapisu.** Wynik: stan `none`, brak planu.
3. **W pozostałych przypadkach** wynik ma stan `proposal`. Najpierw ustalane jest źródło:
   - **M > 1:** źródłem jest `planFor(B, M−1)`, o ile nie zwraca `none`.
   - **M = 1:** źródłem jest grudzień poprzedniego budżetu, wyliczany tak:
     - bierzemy budżet tego samego użytkownika z roku Y−1 w fazie wykonania i liczymy `planFor(B₋₁, 12)`,
     - brak takiego budżetu albo wynik `none` oznacza brak źródła,
     - łańcuch nie sięga dalej niż B₋₁: styczeń liczony wewnątrz B₋₁ nie ma źródła (bez B₋₂), więc dostaje średnią z rocznego.

   Następnie kwota per kategoria proponowana:

   | Przypadek | Kwota |
   | --- | --- |
   | Kategoria One-off | 0 |
   | Brak źródła | średnia z rocznego: `floor(CurrentPlan / 12)` dla M ≤ 11, `CurrentPlan − 11 × floor` dla M = 12 |
   | Źródło w tym samym budżecie | kwota tej kategorii w źródle; 0, gdy jej tam nie ma (kategoria dodana w trakcie roku) |
   | Styczeń, dopasowanie po nazwie i typie | dokładna `name` + `type`; wybierz jedyną niezarchiwizowaną, a gdy brak niezarchiwizowanych, jedyną w ogóle; kwota z grudnia, 0 gdy kategorii nie ma w grudniowym planie |
   | Styczeń, brak dopasowania albo wieloznaczne dopasowanie | średnia z rocznego |

   Kategorie proponowane w M to kategorie B, które nie są zarchiwizowane albo mają `archived_at` ≥ pierwszy dzień M. Kategoria zarchiwizowana zostaje więc w miesiącu archiwizacji i znika z późniejszych propozycji.

Wiersze odczytu „do wydania” w M to suma zbiorów:

- pozycje planu (zapisane albo proponowane),
- kategorie B z operacjami datowanymi w M, z planem 0, gdy nie ma ich w planie (np. zarchiwizowana przed M, a operacja wpisana z datą M).

Jedna znana konsekwencja reguły „brak źródła → średnia”: gdy bieżący miesiąc jest niezatwierdzoną propozycją bez źródła, kategoria dodana w jego trakcie dostaje średnią, a nie 0. Dopiero po zatwierdzeniu nowe kategorie dołączają z 0. To dosłowne czytanie ADR-0033 i nie wymaga decyzji.

### Wzór „do wydania” (`CONTEXT.md`, To Spend), Set-aside = 0

- **Dochód:** `actual`, gdy w miesiącu była operacja z kwotą > 0; w przeciwnym razie `plan`. Wynik się dodaje.
- **Wydatek Bill:** `actual`, gdy w miesiącu był wydatek z kwotą > 0; w przeciwnym razie `plan`, także gdy był tylko zwrot. Wynik się odejmuje.
- **Wydatek Running:** `max(plan, actual)`. Wynik się odejmuje.
- `actual` to suma ze znakiem, czyli ze zwrotami netto. One-off liczy się według swojego rodzaju.

**Przykład kanoniczny**, używany w testach PHP i TS:

| Pozycja | Rodzaj | Plan | Faktycznie | Wkład |
| --- | --- | --- | --- | --- |
| Pensja | dochód | 8000,00 | brak wpływu | +8000,00 |
| Czynsz | Bill | 2000,00 | 1950,00 | −1950,00 |
| Jedzenie | Running | 1500,00 | 600,00 | −1500,00 |
| Paliwo | Running | 400,00 | 520,00 | −520,00 |
| Prezent | One-off, Running | 0 | 300,00 | −300,00 |

„Do wydania” = **3730,00**.

Warianty tego przykładu:

- Pensja wpływa w kwocie 7800,00: wynik 3530,00.
- Czynsz ma tylko zwrot −100,00, bez wpłaty dodatniej: wkład −2000,00, wynik 3680,00.

**Przykłady propozycji** (budżet 2026 w wykonaniu, Current Plan „Jedzenie” = 1000,01 zł = 100 001 gr):

- **Dziś 2026-11-10, brak zapisanych planów.** Listopad: brak źródła, więc 8 333 gr. Grudzień: źródłem jest propozycja listopada, więc 8 333 gr (kopia, nie reszta).
- **Dziś 2026-12-05, listopad niezatwierdzony.** Listopad jest miniony i ma `none`. Grudzień: brak źródła, więc reszta `100 001 − 11 × 8 333` = 8 338 gr.
- **Dziś 2026-12-05, listopad zatwierdzony z 9 000 gr.** Grudzień: 9 000 gr.
- **Dziś 2027-01-04, budżet 2027 w wykonaniu, grudzień 2026 zatwierdzony z „Jedzenie” 9 500 gr.**
  - Styczeń „Jedzenie”: 9 500 gr.
  - Nowa w 2027 „Siłownia”: średnia z jej Current Plan.
  - Dwie niezarchiwizowane „Jedzenie” w 2026: średnia.

## Phase 1: Rodzaj pozycji na kategorii

### Overview

Dodaje cechę Month Item Kind na kategorii wydatku: schemat, enum, walidację, kopiowanie na nowy budżet i pole w dialogu kategorii. Plan miesiąca jeszcze z niej nie korzysta.

### Changes Required:

#### 1. Schemat i enum

**File**: `database/migrations/<ts>_add_month_item_kind_to_categories_table.php` (przez `php artisan make:migration`), `app/Enums/MonthItemKind.php`

**Intent**: Nowa kolumna z domyślnym `running`, żeby istniejące kategorie nie wymagały ręcznej migracji (FR-018).

**Contract**:

- Kolumna: `string('month_item_kind')->default('running')->after('is_paced')`.
- Enum: `enum MonthItemKind: string { case Bill = 'bill'; case Running = 'running'; }`, bez metod etykiet, zgodnie z innymi enumami.
- Dochód przechowuje `running`, a kod go ignoruje. Wzorzec jak `is_paced = true` dla dochodu.

#### 2. Model i fabryka

**File**: `app/Models/Category.php`, `database/factories/CategoryFactory.php`

**Intent**: Pole przypisywalne i rzutowane na enum. Fabryka ma domyślny rodzaj i stan `bill()`.

**Contract**:

- `#[Fillable]` + `@property MonthItemKind $month_item_kind`.
- `casts()`: `'month_item_kind' => MonthItemKind::class`.
- Fabryka: `'month_item_kind' => MonthItemKind::Running` obok `is_paced` (`CategoryFactory.php:60`). Stan `bill()` = wydatek + `Bill`.

#### 3. Walidacja i zapis

**File**: `app/Concerns/CategoryValidationRules.php`, `app/Http/Requests/StoreCategoryRequest.php`, `app/Http/Requests/UpdateCategoryRequest.php`, `app/Http/Controllers/CategoryController.php`

**Intent**: Rodzaj zapisywany przy tworzeniu i edycji kategorii wydatku. Odrzucany dla dochodu i kategorii zarchiwizowanej, tak samo jak `is_paced`.

**Contract**:

- Reguła `'month_item_kind' => ['sometimes', Rule::enum(MonthItemKind::class)]`.
- `withMonthItemKindValidation` na wzór `withPacedFlagValidation`: komunikat `validation.prohibited`.
- `CategoryController::store` wymusza `running` dla dochodu.
- `update` dopisuje pole do `$updatableFields` w tej samej gałęzi co `is_paced` (`:56-58`).

#### 4. Kopiowanie na nowy budżet

**File**: `app/Actions/CreateBudget.php`

**Intent**: Rodzaj przechodzi na nowy rok jak flaga równomierna (ADR-0033, FR-019).

**Contract**: W `copyCategories` dodać `'month_item_kind' => $sourceCategory->month_item_kind` obok `is_paced` (`:44`).

#### 5. Dialog kategorii

**File**: `resources/js/components/CategoryFormDialog.vue`, `resources/js/components/BudgetCategoryList.vue` (typ `BudgetCategoryRow`), `app/Http/Controllers/BudgetController.php` (`categoryPayload` `:226-272`), `lang/pl/ui.php` (`budget`)

**Intent**: Wybór „rachunek / bieżąca” widoczny tylko dla wydatku, obok checkboxa równomiernej (`CategoryFormDialog.vue:268-275`), z krótką podpowiedzią. Wiersz kategorii przekazuje bieżący rodzaj.

**Contract**:

- Prop wiersza: `month_item_kind: 'bill' | 'running'` w `categoryPayload` i `BudgetCategoryRow`. Zmiana w tym samym commicie po obu stronach, z `assertInertia`.
- Pole formularza: `name="month_item_kind"`.
- Klucze tłumaczeń:
  - `budget.month_item_kind` „Rodzaj pozycji”,
  - `budget.month_item_kind_bill` „Rachunek”,
  - `budget.month_item_kind_running` „Bieżąca”,
  - `budget.month_item_kind_hint`.
- Słowo „rachunek” to rodzaj pozycji, nie konto (`CONTEXT.md` Month Item Kind, Avoid).

### Success Criteria:

#### Automated Verification:

- Nowy `tests/Feature/CategoryMonthItemKindTest.php` przechodzi i obejmuje:
  - domyślne `running` przy store,
  - zapis `bill` dla wydatku,
  - odrzucenie dla dochodu i kategorii zarchiwizowanej,
  - kopiowanie przez `CreateBudget`,
  - prop `month_item_kind` na stronie Budżet (`assertInertia`).
- Istniejące testy kategorii przechodzą: `CategoryPacedFlagTest`, `BudgetTest`.
- Migracja przechodzi na PostgreSQL (polecenie `docker run … sail-8.5/app artisan test --compact tests/Feature/CategoryMonthItemKindTest.php` z `AGENTS.md`).
- `vendor/bin/pint --dirty --format agent`, `npm run check` i `npm run types:check` przechodzą.

#### Manual Verification:

- W dialogu kategorii wydatku widać wybór „Rachunek / Bieżąca”, a w dialogu dochodu go nie ma.
- Zapisany rodzaj zostaje po ponownym otwarciu dialogu.

**Implementation Note**: Po zielonej automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Model planu i odczyty serwera

### Overview

Dodaje tabele planów zatwierdzonych, odczyt propozycji według reguły z sekcji „Reguła propozycji”, faktyczne kwoty miesiąca, wzór „do wydania” i akcję zatwierdzenia. Całość jest pokryta testami unit i feature, bez UI.

### Changes Required:

#### 1. Schemat planów

**File**: `database/migrations/<ts>_create_month_plans_table.php`, `database/migrations/<ts>_create_month_plan_items_table.php`

**Intent**: Przechowywane są tylko plany zatwierdzone. Kwoty zatwierdzona i bieżąca są osobno, żeby S-03 mógł je rozróżnić bez migracji danych.

**Contract**:

- `month_plans`:
  - `id`,
  - `budget_id` (FK `cascadeOnDelete`),
  - `month` (`unsignedTinyInteger`, 1–12),
  - `approved_at` (`timestamp`, not null),
  - timestamps,
  - `unique(['budget_id', 'month'])`.
- `month_plan_items`:
  - `id`,
  - `month_plan_id` (FK `cascadeOnDelete`),
  - `category_id` (FK `cascadeOnDelete`; plan istnieje tylko dla budżetu w wykonaniu, a tam kategorii się nie usuwa),
  - `approved_amount_minor_units` i `current_amount_minor_units` (`bigInteger`),
  - timestamps,
  - `unique(['month_plan_id', 'category_id'])`.
- Kaskada z `budgets` sprawia, że `ResetUserData` nie wymaga zmian.

#### 2. Modele i fabryki

**File**: `app/Models/MonthPlan.php`, `app/Models/MonthPlanItem.php`, `app/Models/Budget.php`, `database/factories/MonthPlanFactory.php`, `database/factories/MonthPlanItemFactory.php`

**Intent**: Modele w stylu `Category`: `#[Fillable]`, `casts()`, relacje z generykami w PHPDoc.

**Contract**: `Budget::monthPlans(): HasMany`, `MonthPlan::budget()`, `MonthPlan::items(): HasMany`, `MonthPlanItem::category()`. Fabryki tworzą plan dla `Budget::factory()->execution()`.

#### 3. Faktyczne kwoty miesiąca

**File**: `app/Support/MonthCategoryActuals.php`

**Intent**: Jedno zapytanie agregujące dla budżetu i miesiąca, z sumą ze znakiem i flagą kwoty dodatniej per kategoria, dla dochodów i wydatków.

**Contract**:

- `static forMonth(Budget $budget, int $month): self` i `actual(int $categoryId): int`, domyślnie 0.
- `hasPositiveAmount(int $categoryId): bool` i `categoryIds(): list<int>`.
- Zapytanie: `financial_operations` gdzie `budget_id`, a `operation_date` między pierwszym a ostatnim dniem miesiąca (zakres, nie `whereYear`/`whereMonth`).
- Grupowanie po `category_id` z `SUM(amount_minor_units)` i `SUM(CASE WHEN amount_minor_units > 0 THEN 1 ELSE 0 END)`. Działa i na SQLite, i na PostgreSQL.

#### 4. Propozycja i odczyt planu

**File**: `app/Support/MonthPlanProposal.php` (+ mały obiekt wyniku, np. `app/Support/MonthPlanReading.php`)

**Intent**: Implementacja `planFor(B, M)` z sekcji „Reguła propozycji”, łącznie z łańcuchem, styczniem, średnią z resztą w grudniu, One-off = 0, kategoriami dodanymi w trakcie roku i zarchiwizowanymi.

**Contract**:

- Wejście: `forBudget(Budget $budget, CarbonImmutable $today)` ładuje raz zapisane plany budżetu (z pozycjami), kategorie budżetu oraz budżet Y−1 w wykonaniu z jego planami i kategoriami.
- Metoda `reading(int $month): MonthPlanReading`.
- `MonthPlanReading`: `state` (`'proposal'|'approved'|'none'`), `approvedAt` i `amounts` (`array<int categoryId, int>`), czyli kategorie proponowane albo pozycje zapisane.
- Arytmetyka średniej to prywatna metoda z własnym `floorDivide`. Nie dotyka `MonthRemainingCalculator`.
- Rekursja ma memoizację per (budżet, miesiąc) i głębokość najwyżej 12 + 12.

#### 5. Wzór „do wydania”

**File**: `app/Support/ToSpendCalculator.php`

**Intent**: Czysta funkcja wzoru z sekcji „Wzór »do wydania«”. Bliźniak TS powstaje w fazie 3.

**Contract**: `static toSpendMinorUnits(list<array{type: CategoryType, kind: MonthItemKind, plan: int, actual: int, has_positive_amount: bool}> $items): int`. Set-aside nie jest jeszcze parametrem, bo S-02 go doda.

#### 6. Zatwierdzenie

**File**: `app/Actions/ApproveMonthPlan.php`, `app/Policies/BudgetPolicy.php`

**Intent**: Zapis planu zatwierdzonego z kwotami z formularza. Wolno go zrobić tylko dla budżetu w wykonaniu, dla bieżącego lub przyszłego miesiąca, który nie jest jeszcze zatwierdzony.

**Contract**:

- `handle(Budget $budget, int $month, array<int categoryId, int amount> $amounts, CarbonImmutable $today): MonthPlan` w `DB::transaction`.
- Zbiór kluczy `$amounts` musi równać się zbiorowi kategorii proponowanych z `MonthPlanProposal::reading($month)`. Inaczej leci `ValidationException` z kluczem błędu `items`, tak jak `ReorderCategories::handle` (`:25-29`). Wiersze „tylko operacje” (kategorie spoza planu z operacją w M) nie należą do tego zbioru.
- Ponowne zatwierdzenie daje `ValidationException` z kluczem `items`. Unikalny indeks jest zabezpieczeniem przy wyścigu.
- Pozycja ma `approved_amount = current_amount = kwota`, a `approved_at = now()`.
- Polityka: `BudgetPolicy::manageMonthPlan(User, Budget)`, czyli właściciel + `isExecution()`.

### Success Criteria:

#### Automated Verification:

- `tests/Unit/ToSpendCalculatorTest.php` (Pest) przechodzi i obejmuje:
  - przykład kanoniczny 3730,00 i oba warianty (3530,00, 3680,00),
  - pustą listę równą 0,
  - wynik ujemny.
- `tests/Feature/MonthPlanProposalTest.php` przechodzi i obejmuje wszystkie przykłady propozycji z sekcji „Reguła propozycji”:
  - listopad/grudzień: 8 333, 8 333, 8 338, 9 000,
  - styczeń: dopasowanie, nowa kategoria, dwuznaczność,
  - styczeń budżetu Y+1, gdy łańcuch dochodzi do stycznia B₋₁: średnia, bez sięgania do B₋₂,
  - One-off = 0,
  - kategoria dodana po zatwierdzeniu poprzedniego miesiąca = 0,
  - zarchiwizowana w M zostaje w M i znika z M+1,
  - miniony bez zapisu = `none`.
- `tests/Feature/MonthCategoryActualsTest.php` przechodzi i obejmuje:
  - sumę netto ze zwrotem,
  - flagę dla samego zwrotu = false,
  - pomijanie operacji spoza miesiąca,
  - przelewy własne i ruchy kapitału bez wpływu.
- `tests/Feature/ApproveMonthPlanTest.php` przechodzi i obejmuje:
  - zapis obu kwot,
  - odrzucenie miesiąca minionego, ponownego zatwierdzenia, budżetu w szkicu, cudzego budżetu i niepełnego lub nadmiarowego zbioru kategorii,
  - brak zmian w `categories.planned_amount_minor_units` (FR-015),
  - usunięcie planów przez `user:reset-data`.
- Powyższe testy feature przechodzą też na PostgreSQL przez `docker run … sail-8.5/app artisan test --compact tests/Feature/MonthPlanProposalTest.php tests/Feature/MonthCategoryActualsTest.php tests/Feature/ApproveMonthPlanTest.php`.
- `vendor/bin/pint --dirty --format agent` przechodzi.

#### Manual Verification:

- Brak (faza bez UI). Przegląd, czy testy propozycji odpowiadają przykładom z planu jeden do jednego.

**Implementation Note**: Po zielonej automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: Strona „Plan miesiąca”

### Overview

Nowa strona w menu z przełącznikiem miesięcy, propozycją z oznaczeniem „niezatwierdzony”, edycją kwot, podglądem „do wydania” na żywo, przełącznikiem rodzaju i zatwierdzeniem.

### Changes Required:

#### 1. Trasy i kontroler

**File**: `routes/web.php`, `app/Http/Controllers/MonthPlanController.php`, `app/Http/Requests/ApproveMonthPlanRequest.php`

**Intent**: Strona GET dla roku z sesji i miesiąca z `?month=N` oraz POST zatwierdzenia. Wzór kontrolera bierzemy z `BudgetController::show` i `startExecution`.

**Contract**:

- `GET month-plan` → `MonthPlanController@show`, nazwa `month-plan`.
- `POST budgets/{budget}/month-plans/{month}/approve` → `@approve`, nazwa `month-plans.approve`, `whereNumber('month')`, zakres 1–12.
- Obie trasy są w grupie `auth` + `verified`.
- `show`:
  - rok bierze z `SelectedBudgetYear`,
  - miesiąc z `BudgetViewedMonth::resolve`,
  - `today` = `CarbonImmutable::today('UTC')`,
  - autoryzuje `view` budżetu.
- `approve`:
  - `ApproveMonthPlanRequest` autoryzuje `manageMonthPlan`,
  - reguły: `items` jako tablica, `items.*.category_id` jako integer i `distinct`, `items.*.amount_minor_units` jako integer ≥ 0 z tym samym górnym limitem co `planned_amount_minor_units` w `CategoryValidationRules`,
  - potem `ApproveMonthPlan::handle`, toast sukcesu i `to_route('month-plan', ['month' => $month])`.

#### 2. Propsy strony

**File**: `app/Http/Controllers/MonthPlanController.php`, `resources/js/pages/MonthPlan.vue`

**Intent**: Serwer liczy wszystkie odczyty. Klient dostaje to, czego potrzebuje do wyrenderowania i do podglądu.

**Contract**: Propsy PHP i typy w `defineProps<>` zmieniane w tym samym commicie i sprawdzane przez `assertInertia`:

- `budget: { id: number; year: number; phase: 'draft' | 'execution' } | null`
- `viewedMonth: number | null`, `viewedMonthTiming: 'past' | 'current' | 'future' | null`, `currentMonth: number | null`
- `monthPlan` (null, gdy nie ma budżetu albo budżet jest w szkicu):
  - `state: 'proposal' | 'approved' | 'none'`
  - `approved_at: string | null`
  - `can_approve: boolean`, czyli `proposal` + bieżący lub przyszły miesiąc
  - `to_spend_minor_units: number | null`, null przy `none`
  - `income_items` i `expense_items`, każde jako `MonthPlanItemRow[]`, w kolejności `ordered()`
- `MonthPlanItemRow`:
  - `category_id`, `name`, `icon`, `color`
  - `type: 'income' | 'expense'`
  - `month_item_kind: 'bill' | 'running' | null`, null dla dochodu
  - `is_one_off`, `is_archived`
  - `plan_minor_units`, `actual_minor_units`
  - `has_positive_amount`
  - `can_change_kind`, czyli wydatek niezarchiwizowany i miesiąc bieżący lub przyszły
  - `in_plan`: `true` dla pozycji planu (zapisanej albo proponowanej), `false` dla wiersza „tylko operacje”

Wiersze to pozycje planu ∪ kategorie z operacjami w miesiącu (patrz „Reguła propozycji”).

#### 3. Przełącznik rodzaju

**File**: `routes/web.php`, `app/Http/Controllers/CategoryController.php`, `app/Http/Requests/UpdateCategoryMonthItemKindRequest.php`

**Intent**: Zapis rodzaju bez opuszczania strony. Odpowiedź przekierowuje wstecz, a klient przeładowuje propsy z `preserveState`, żeby nie zgubić niezapisanych kwot.

**Contract**:

- `PATCH categories/{category}/month-item-kind` → `CategoryController@updateMonthItemKind`, nazwa `categories.month-item-kind`, zwraca `back()`.
- Autoryzacja przez `update` kategorii, a walidacja przez tę samą regułę i `withMonthItemKindValidation` co w fazie 1.
- Klient: `router.patch(url, { month_item_kind }, { preserveState: true, preserveScroll: true })`. Bez `only`, bo repo nie ma precedensu partial reload po nie-GET z przekierowaniem. `preserveState` zachowuje refy kwot z §5, a pełny reload propsów jednej strony jest tani.

#### 4. Kopia TS wzoru

**File**: `resources/js/lib/to-spend-calculator.ts`, `resources/js/lib/to-spend-calculator.test.ts`, `AGENTS.md` (sekcja „Month logic lives twice”)

**Intent**: Podgląd „do wydania” przy edycji kwot propozycji. Wzór jest identyczny jak `ToSpendCalculator.php`, a testy liczą te same przypadki co `ToSpendCalculatorTest.php`.

**Contract**:

- `toSpendMinorUnits(items: { type: 'income' | 'expense'; kind: 'bill' | 'running' | null; plan: number; actual: number; hasPositiveAmount: boolean }[]): number`.
- Test importuje z `vite-plus/test` i używa importów względnych.
- W `AGENTS.md` dopisujemy parę `ToSpendCalculator.php` ↔ `to-spend-calculator.ts` z testami jako kolejną logikę miesiąca, którą zmienia się razem. Powód kopii: podgląd przed zatwierdzeniem.

#### 5. Widok

**File**: `resources/js/pages/MonthPlan.vue`, `resources/js/components/BudgetMonthPicker.vue`, `resources/js/components/AppSidebar.vue`, `lang/pl/ui.php` (`nav.month_plan`, sekcja `month_plan`)

**Intent**: Strona zgodna z `.ai/rules/js.md` i `css.md`. Ma:

- nagłówek z miesiącem,
- badge stanu „niezatwierdzony” / „zatwierdzony”,
- wyraźną liczbę „do wydania”: `formatMoney`, `tabular-nums`, `text-destructive`, gdy < 0,
- dwie listy (`ListSurface`, `CategoryChip`) z kolumnami plan (edytowalny w propozycji) i faktycznie oraz przełącznikiem rodzaju dla wydatku,
- przycisk „Zatwierdź”, gdy `can_approve`.

Stany puste:

- brak budżetu: `IndexEmptyState` z odesłaniem do strony Budżet,
- budżet w szkicu: „plan miesiąca dostępny po rozpoczęciu realizacji”,
- miniony miesiąc bez planu: „ten miesiąc nie ma planu”.

**Contract**:

- `BudgetMonthPicker` dostaje opcjonalny prop budujący URL miesiąca. Domyślnie zachowuje dzisiejsze działanie dla `budget` (`:27-38`), a strona Budżet się nie zmienia.
- Formularz: `<Form v-bind="MonthPlanController.approve.form({ budget, month })">` z ukrytymi `items[i][category_id]` i `items[i][amount_minor_units]`, z kwotami parsowanymi przez `parseMoneyInputToMinorUnits` jak w `ImportPreviewRow.vue:455-475`. Ukryte pola powstają tylko dla wierszy `in_plan`. Wiersze z `in_plan = false` mają plan 0 tylko do odczytu i wchodzą do podglądu. Błąd `errors.items` jest pokazany nad przyciskiem „Zatwierdź”.
- Stan kwot według wzorca `ExpenseFormDialog.vue` (`:88-89`, `:217-232`, `:352-360`, `:548-562`):
  - `amountInputs: ref<Record<number, string>>` (klucz `category_id`), inicjalizowany raz z `plan_minor_units` wierszy `in_plan`, z `v-model` na polach tekstowych,
  - computed z kwotami sparsowanymi przez `parseMoneyInputToMinorUnits`, które zasilają ukryte `items[i][amount_minor_units]`,
  - computed podglądu `toSpendMinorUnits` nad sparsowanymi kwotami oraz `month_item_kind`, `actual_minor_units` i `has_positive_amount` z propsów (po przełączeniu rodzaju propsy się odświeżają, a refy zostają).
- Przy stanie `approved` i miesiącu minionym podgląd pokazuje `to_spend_minor_units` z serwera.
- Nowa pozycja menu po „Budżet” w `mainNavItems`.
- Wayfinder: wzór importów z `Budget.vue:5`. Po zmianie tras `npm run build`, potem `npm run types:check`.

### Success Criteria:

#### Automated Verification:

- `tests/Feature/MonthPlanPageTest.php` przechodzi i obejmuje:
  - propsy dla bieżącej propozycji: `state`, `can_approve`, `to_spend_minor_units` równe przykładowi kanonicznemu (3730,00), wiersze z `has_positive_amount` (`assertInertia`),
  - przyszły miesiąc,
  - miniony bez planu (`none`, `to_spend` null),
  - miniony zatwierdzony (tylko do odczytu),
  - brak budżetu i szkic,
  - wiersz kategorii zarchiwizowanej z operacją w miesiącu (`in_plan = false`),
  - zatwierdzenie przez trasę i redirect, także w miesiącu z wierszem `in_plan = false` (wysyłane są tylko pozycje planu),
  - odrzucenie zduplikowanego `category_id` (`distinct`),
  - liczbę po dodaniu operacji (kolejny GET pokazuje nową wartość).
- Test przełącznika rodzaju przechodzi: zapis `bill`, odrzucenie dla dochodu i kategorii zarchiwizowanej, redirect wstecz.
- `npm test` przechodzi, w tym `to-spend-calculator.test.ts` z tymi samymi przypadkami co test PHP.
- `npm run build`, `npm run check` i `npm run types:check` przechodzą.
- `tests/Feature/MonthPlanPageTest.php` przechodzi na PostgreSQL (`docker run … sail-8.5/app artisan test --compact tests/Feature/MonthPlanPageTest.php`).
- Istniejące testy strony Budżet i Dashboardu przechodzą bez zmian asercji: `BudgetTest`, `DashboardTest`, `BudgetMonthRemainingTest`.

#### Manual Verification:

- Na bieżącym miesiącu widać propozycję z badge „niezatwierdzony” i liczbę „do wydania”. Zmiana kwoty od razu zmienia liczbę.
- Przełączenie rodzaju „Czynsz” na „Rachunek” zmienia liczbę i nie gubi wpisanych wcześniej kwot.
- „Zatwierdź” zapisuje plan, badge zmienia się na „zatwierdzony”, a pola stają się tylko do odczytu.
- Dodanie wydatku na stronie Operacje zmienia liczbę na stronie planu.
- Przełącznik miesięcy działa. Miniony miesiąc pokazuje komunikat „bez planu”. Strona Budżet nadal przełącza miesiące jak dotąd.
- Widok jest czytelny na szerokości telefonu, a liczba ujemna ma kolor `destructive`.

**Implementation Note**: Po zielonej automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Phase 4: Snapshot w wersji 5 i dokumenty

### Overview

`user:backup` / `user:restore` obejmują rodzaj pozycji i plany miesięcy, a przywracanie wersji 1–4 działa dalej (PRD, Kompatybilność wsteczna).

### Changes Required:

#### 1. Eksport

**File**: `app/Actions/BackupUserData.php`

**Intent**: Wersja 5 z rodzajem pozycji w kategoriach i nowym kluczem z planami miesięcy.

**Contract**:

- `SNAPSHOT_VERSION = 5`.
- `serializeCategory` (`:138`) dodaje `month_item_kind`.
- Nowy klucz `month_plans`: lista `{ id, budget_id, month, approved_at, items: [{ category_id, approved_amount_minor_units, current_amount_minor_units }] }` ze starymi ID.

#### 2. Przywracanie

**File**: `app/Actions/RestoreUserData.php`

**Intent**: Przyjąć 1–5, dla v5 wymagać `month_plans`, a rodzaj tolerować po kluczu jak `is_paced`.

**Contract**:

- Lista wersji `[1, 2, 3, 4, BackupUserData::SNAPSHOT_VERSION]` (`:76`), z jawnie dopisaną 4.
- Warunki `term_deposits` i `capital_movements` (`:86-92`) obejmują jawnie także 4.
- Dla v5 wymagany jest klucz `month_plans`.
- Pomocnik `restoredCategoryMonthItemKind`:
  - dochód daje `running`,
  - wydatek daje `MonthItemKind::tryFrom($data['month_item_kind'] ?? '')`, a przy braku `Running`.
- Plany są odtwarzane po kategoriach z mapami budżetów i kategorii: `($snapshot['month_plans'] ?? [])`.

#### 3. Dokumenty

**File**: `README.md:106`, `.docs/prd.md:304-305`

**Intent**: Numer wersji i treść snapshotu zgodne z kodem.

**Contract**:

- README: „wersja 5”.
- FR-ADM-01:
  - snapshot zawiera rodzaj pozycji i plany miesięcy,
  - JSON wersji 5,
  - restore przyjmuje 1–5,
  - brak `month_item_kind` daje `running`.

### Success Criteria:

#### Automated Verification:

- `tests/Feature/UserSnapshotCommandTest.php` przechodzi i obejmuje:
  - asercję `version === 5`,
  - okrągłą podróż z planem zatwierdzonym (obie kwoty, przemapowane ID) i kategorią `bill`,
  - przywrócenie snapshotu v4 (zbudowanego mutacją JSON bez `month_plans` i `month_item_kind`) z domyślnym `running` i bez planów,
  - odrzucenie v5 bez `month_plans`.
- `CategoryPacedFlagTest` (okrągła podróż `is_paced`) przechodzi.
- Pełna bramka CI przechodzi na obrazie Sail: `docker run … --entrypoint composer sail-8.5/app ci:check`.

#### Manual Verification:

- `user:backup` na danych z zatwierdzonym planem, a potem `user:restore` na innym użytkowniku. Strona planu pokazuje ten sam plan i tę samą liczbę.

**Implementation Note**: Po zielonej automatycznej weryfikacji poproś o ręczne potwierdzenie i o uruchomienie pełnego zestawu testów.

---

## Testing Strategy

### Unit Tests:

- `ToSpendCalculatorTest.php` i `to-spend-calculator.test.ts`: te same przypadki. Obejmują przykład kanoniczny i warianty, Bill z samym zwrotem, Running ponad plan, dochód po wpływie, wynik ujemny i pustą listę.

### Integration Tests:

- Propozycja: wszystkie przykłady liczbowe z planu, łańcuch przez kilka miesięcy, styczeń z trzema przypadkami dopasowania, kategoria dodana w trakcie roku i zarchiwizowana.
- Kwoty faktyczne: zakres dat, zwroty, wykluczenie przelewów i ruchów kapitału.
- Zatwierdzenie: reguły dostępu i stanu, zamrożenie (zmiana `planned_amount_minor_units` po zatwierdzeniu nie zmienia planu), brak zmian w budżecie rocznym.
- Strona: propsy i `assertInertia`, aktualność liczby po operacji.
- Snapshot: v5 w obie strony i przywracanie v4.
- Testy dotyczące zapytań, migracji i ograniczeń uruchamiane też na PostgreSQL.

### Manual Testing Steps:

1. Na budżecie w wykonaniu otwórz „Plan miesiąca”, sprawdź propozycję i liczbę.
2. Oznacz czynsz jako „Rachunek”, popraw dwie kwoty, sprawdź podgląd i zatwierdź.
3. Dodaj wydatek i dochód w bieżącym miesiącu, wróć na stronę i sprawdź liczbę z wzorem.
4. Przełącz na następny miesiąc i sprawdź, że propozycja kopiuje zatwierdzony plan, a One-off ma 0.
5. Sprawdź Dashboard i stronę Budżet: nic się nie zmieniło.

## Performance Considerations

Odczyt strony to stała liczba zapytań:

- kategorie budżetu,
- plany zatwierdzone budżetu i budżetu Y−1 z pozycjami (eager load),
- kategorie budżetu Y−1, tylko dla stycznia,
- jedno zapytanie agregujące operacji miesiąca.

Agregacja używa zakresu dat i indeksu `budget_id`, więc przy 10 000 operacji mieści się w limicie 2 s z PRD. Nowego indeksu nie dodajemy.

## Migration Notes

- Migracje tylko dodają kolumnę z domyślną wartością i dwie tabele, więc istniejące dane nie wymagają ręcznej migracji (FR-018).
- Wycofanie to wdrożenie poprzedniej wersji: nowe dane zostają w bazie nieużywane.
- Snapshot v5 nie przywróci się na starej wersji aplikacji, co jest zgodne z dotychczasową praktyką podbijania wersji.

## References

- Research: `context/changes/month-plan-and-to-spend/research.md`
- Domena: `.docs/adr/0033-month-plan-beside-annual-budget.md`, `CONTEXT.md` (Month Plan, Approved/Current Month Plan, Month Item Kind, To Spend, Set-aside)
- PRD: `context/foundation/prd.md` (US-01, FR-001, FR-002, FR-005, FR-015, FR-018, FR-019; Business Logic Changes)
- Roadmapa: `context/foundation/roadmap.md` (S-01)
- Poprzednia zmiana: `context/archive/2026-11-02-month-plan-domain-decisions/plan-brief.md`
- Wzorce: `app/Actions/StartBudgetExecution.php`, `app/Actions/ReorderCategories.php:25-29`, `tests/Feature/CategoryPacedFlagTest.php`, `resources/js/pages/OperationsImportPreview.vue:282-364`, `resources/js/components/BudgetMonthPicker.vue:27-38`

## Addenda (po przeglądzie implementacji, 2026-11-02)

- **Poza listą plików planu:** `resources/js/types/shell.ts` (ręczny typ `UiTranslations` wymaga kluczy `nav.month_plan` i `month_plan`) oraz sekcja „Preview from a worktree” w `AGENTS.md` (dodana na prośbę użytkownika po fazie 3).
- **Limit kwot (F1):** `items.*.amount_minor_units` ma `max:1000000000000` (10^12 gr), a `MonthPlan.vue` odrzuca wartości powyżej. Powód: suma w `ToSpendCalculator` mogłaby przepełnić `int`. Dotyczy tylko planu miesiąca, nie `planned_amount_minor_units`.
- **Restore planów (F2):** `RestoreUserData::restoreMonthPlans` waliduje klucze, miesiąc 1–12, duplikaty planu i kategorii oraz zgodność kategorii z budżetem planu i rzuca `UserSnapshotException`.
- **Kolejność zatwierdzania (F3, zmiana względem planu):** miesiąc M można zatwierdzić, tylko gdy M−1 tego samego budżetu jest zatwierdzony albo minął (`MonthPlanProposal::isBlockedByPreviousMonth`). Styczeń nie jest blokowany. Dotyczy `ApproveMonthPlan` i `can_approve`; zablokowany miesiąc jest tylko do odczytu z podpowiedzią `month_plan.blocked_hint`.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Rodzaj pozycji na kategorii

#### Automated

- [x] 1.1 Nowy `tests/Feature/CategoryMonthItemKindTest.php` przechodzi (domyślne `running`, zapis `bill`, odrzucenie dla dochodu i zarchiwizowanej, kopiowanie przez `CreateBudget`, prop `month_item_kind` przez `assertInertia`) — 30148ad
- [x] 1.2 Istniejące testy kategorii przechodzą: `CategoryPacedFlagTest`, `BudgetTest` — 30148ad
- [x] 1.3 Migracja i test rodzaju przechodzą na PostgreSQL — 30148ad
- [x] 1.4 `vendor/bin/pint --dirty --format agent`, `npm run check` i `npm run types:check` przechodzą — 30148ad

#### Manual

- [x] 1.5 W dialogu kategorii wydatku widać wybór „Rachunek / Bieżąca”, w dialogu dochodu nie — 30148ad
- [x] 1.6 Zapisany rodzaj zostaje po ponownym otwarciu dialogu — 30148ad

### Phase 2: Model planu i odczyty serwera

#### Automated

- [x] 2.1 `tests/Unit/ToSpendCalculatorTest.php` przechodzi (3730,00, 3530,00, 3680,00, pusta lista, wynik ujemny) — dd5727a
- [x] 2.2 `tests/Feature/MonthPlanProposalTest.php` przechodzi ze wszystkimi przykładami propozycji z planu (w tym granica łańcucha na B₋₁) — dd5727a
- [x] 2.3 `tests/Feature/MonthCategoryActualsTest.php` przechodzi (netto ze zwrotem, flaga, zakres miesiąca, przelewy i ruchy kapitału bez wpływu) — dd5727a
- [x] 2.4 `tests/Feature/ApproveMonthPlanTest.php` przechodzi (obie kwoty, odrzucenia, budżet roczny bez zmian, reset usuwa plany) — dd5727a
- [x] 2.5 Testy feature fazy 2 przechodzą na PostgreSQL — dd5727a
- [x] 2.6 `vendor/bin/pint --dirty --format agent` przechodzi — dd5727a

#### Manual

- [x] 2.7 Przegląd: testy propozycji odpowiadają przykładom z planu jeden do jednego — dd5727a

### Phase 3: Strona „Plan miesiąca”

#### Automated

- [x] 3.1 `tests/Feature/MonthPlanPageTest.php` przechodzi (propsy przez `assertInertia`, stany miesięcy, brak budżetu i szkic, zarchiwizowana z operacją i `in_plan`, zatwierdzenie z wierszem spoza planu, `distinct`, liczba po operacji) — 6b2cea1
- [x] 3.2 Test przełącznika rodzaju przechodzi (zapis `bill`, odrzucenia, redirect wstecz) — 6b2cea1
- [x] 3.3 `npm test` przechodzi, w tym `to-spend-calculator.test.ts` z przypadkami jak w PHP — 6b2cea1
- [x] 3.4 `npm run build`, `npm run check` i `npm run types:check` przechodzą — 6b2cea1
- [x] 3.5 `MonthPlanPageTest` przechodzi na PostgreSQL — 6b2cea1
- [x] 3.6 `BudgetTest`, `DashboardTest` i `BudgetMonthRemainingTest` przechodzą bez zmian asercji — 6b2cea1

#### Manual

- [x] 3.7 Bieżący miesiąc pokazuje propozycję „niezatwierdzony” i liczbę, która reaguje na zmianę kwoty — 6b2cea1
- [x] 3.8 Przełączenie rodzaju zmienia liczbę i nie gubi wpisanych kwot — 6b2cea1
- [x] 3.9 „Zatwierdź” zapisuje plan, badge „zatwierdzony”, pola tylko do odczytu — 6b2cea1
- [x] 3.10 Dodany wydatek zmienia liczbę na stronie planu — 6b2cea1
- [x] 3.11 Przełącznik miesięcy działa, miniony miesiąc pokazuje „bez planu”, strona Budżet przełącza miesiące jak dotąd — 6b2cea1
- [x] 3.12 Widok czytelny na telefonie, liczba ujemna w kolorze `destructive` — 6b2cea1

### Phase 4: Snapshot w wersji 5 i dokumenty

#### Automated

- [x] 4.1 `tests/Feature/UserSnapshotCommandTest.php` przechodzi (wersja 5, okrągła podróż z planem i `bill`, przywrócenie v4 z domyślnym `running`, odrzucenie v5 bez `month_plans`) — 0482082
- [x] 4.2 `CategoryPacedFlagTest` przechodzi — 0482082
- [x] 4.3 Pełna bramka `composer ci:check` przechodzi na obrazie Sail — 0482082

#### Manual

- [x] 4.4 Backup i restore na innym użytkowniku dają ten sam plan i tę samą liczbę — 0482082
