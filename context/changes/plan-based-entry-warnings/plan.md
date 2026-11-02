# S-08: Ostrzeżenia przy operacjach liczone z planu — plan implementacji

## Overview

Wycinek S-08 roadmapy M-1 (PRD: FR-014, FR-017). Gdy miesiąc daty operacji ma plan miesiąca, także niezatwierdzoną propozycję, ręczny formularz operacji i wiersz podglądu Importu ostrzegają, jeśli po tej operacji „do wydania” tego miesiąca jest ujemne i niższe niż przed nią. Komunikat podaje kwotę „do wydania” po operacji. Miesiące bez planu zachowują obecne ostrzeżenia `Pozostało w miesiącu`. Nic nie blokuje zapisu i nie dochodzi żaden krok.

Przy okazji znika martwy `ImportMonthRemainingWarningAnnotator`: jego flagi liczy serwer, a UI ich nie czyta.

Wzór „do wydania” jest rozstrzygnięty w `CONTEXT.md:79-81` i ADR-0033, a zaimplementowany w S-01 (`app/Support/ToSpendCalculator.php`, `resources/js/lib/to-spend-calculator.ts`). Ten plan go nie zmienia.

## Current State Analysis

- **Ostrzeżenia miesiąca liczy klient w TS:**
  - ręcznie tylko w `ExpenseFormDialog.vue` (`resources/js/components/ExpenseFormDialog.vue:220-237`, render `:587-618`), tylko dla `kind === 'expense'` i kategorii równomiernej (`resources/js/lib/hand-entry-month-remaining-preview.ts:157-197`);
  - w Imporcie `computeImportMonthRemainingWarnings` (`resources/js/pages/OperationsImportPreview.vue:144-152`, logika `resources/js/lib/import-month-remaining-warnings.ts:94-246`) liczy zaznaczone wiersze wydatków z kategorią równomierną, kumulując je w kolejności indeksu wiersza;
  - żadne nie blokuje zapisu; oba podglądy nie mają testów.
- **Wejścia serwera dla Month Remaining:** `HandEntryMonthRemainingInputs::forUser` (`app/Http/Controllers/OperationsController.php:180`) i `ImportMonthRemainingContext::forUser` (`app/Http/Controllers/CsvImportController.php:148`) obejmują budżety w wykonaniu. Zostają bez zmian dla miesięcy bez planu.
- **Martwy annotator:** `ImportMonthRemainingContext::annotateRows` (`app/Support/CsvImport/ImportMonthRemainingContext.php:96-130`) dodaje do wierszy `default_type`, `default_category_id` i flagi `show_*_month_remaining_warning` / `month_remaining_period_label` z `ImportMonthRemainingWarningAnnotator`. Grep po `resources/js` i `app` nie znajduje żadnego czytelnika tych pól, poza opcjonalnymi polami typu `resources/js/lib/import-preview-rows.ts:21-23`. Jedynym konsumentem jest `tests/Feature/ImportMonthRemainingWarningTest.php`. Ten test zawiera też asercję propsa `monthRemainingContext` (`:164`), którą trzeba zachować.
- **Klocki S-01:**
  - `MonthPlanProposal::forBudget($budget, $today)->reading($month)` zwraca stan `approved` / `proposal` / `none` i kwoty planu (`app/Support/MonthPlanProposal.php:40-122`); ładuje `monthPlans.items` i `categories` budżetu w stałej liczbie zapytań (`tests/Feature/MonthPlanProposalTest.php:229-248`);
  - `MonthCategoryActuals::forMonth` wykonuje jedno zapytanie na miesiąc (`app/Support/MonthCategoryActuals.php:16-39`); `positive_count` jest prywatne, na zewnątrz jest tylko bool `hasPositiveAmount`;
  - wiersze odczytu to suma zbiorów kategorii z planu i z faktycznych kwot (`app/Http/Controllers/MonthPlanController.php:107-112`).
- **Daty operacji nie mogą być przyszłe** (`app/Concerns/ExpenseValidationRules.php:24`, `app/Concerns/IncomeValidationRules.php:24`). Miesiąc z planem jest więc bieżącym miesiącem (stan `approved` albo `proposal`) albo minionym miesiącem z zapisanym planem (`approved`). Zapisane miesiące mogą mieć luki (blokada zatwierdzania przepuszcza M, gdy M−1 minął bez planu), więc plan trzeba rozstrzygać per miesiąc.
- **Wzór nie jest monotoniczny** (`app/Support/ToSpendCalculator.php:15-36`). Pierwszy wydatek Bill mniejszy od planu podnosi „do wydania”, a wydatek Running w granicach planu go nie zmienia. Pierwszy wpływ dochodu mniejszy od planu je obniża, a zwrot nigdy nie obniża. Dlatego ostrzeżenie porównuje „przed” i „po” całego miesiąca, a nie samą kwotę wpisu.
- **Znaki kwot:** w zapisie `income` i `expense` dają `abs`, a `refund` daje `-abs` (`app/Actions/ImportFinancialOperations.php:249-253`). Ręczny zwrot to ujemny wydatek.
- **Tłumaczenia:** `UiTranslations.operations` to `Record<string, string>` (`resources/js/types/shell.ts:44`), więc nowy klucz w `lang/pl/ui.php` nie wymaga zmiany typu. Nazwy miesięcy `t.budget.month_name_N` są w mianowniku. Wzorzec etykiety miesiąca: `ExpenseFormDialog.vue:255-265`.
- **Testy:**
  - testy feature S-01 to klasy PHPUnit z `RefreshDatabase` (`tests/Feature/MonthCategoryActualsTest.php`, `tests/Feature/MonthPlanProposalTest.php`, wzorzec liczenia zapytań `DB::listen` w `:236-247`);
  - testy TS leżą obok kodu, importują z `vite-plus/test` i używają importów względnych (`resources/js/lib/to-spend-calculator.test.ts`).

## Desired End State

- **Ręczna operacja** (`ExpenseFormDialog` dla wydatku, zwrotu i edycji „zamień na wpływ”; `IncomeFormDialog` dla dochodu i edycji „zamień na zwrot”), w każdej kategorii, także jednorazowej:
  - gdy miesiąc daty operacji ma plan i po operacji „do wydania” tego miesiąca jest `< 0` i `<` niż przed nią, lista „Uwaga” pokazuje: „Po tej operacji do wydania wyniesie −130,00 zł (listopad 2026).”;
  - edycja sprawdza osobno stary miesiąc operacji (usunięcie starej kwoty) i nowy (dodanie nowej). Ten sam miesiąc liczy się raz, z obiema zmianami naraz. Każdy miesiąc, który spełnia warunek, daje własny wiersz listy;
  - w miesiącu z planem nie pokazują się ostrzeżenia `Pozostało w miesiącu`, a w miesiącu bez planu zostają jak dziś;
  - ostrzeżenie nie blokuje zapisu.
- **Podgląd Importu:**
  - każdy zaznaczony wiersz dochodu, wydatku albo zwrotu z kategorią jest sprawdzany według miesiąca swojej daty;
  - zaznaczone wiersze z kategorią sumują się w kolejności indeksu wiersza: wiersz liczy „przed” i „po” z uwzględnieniem zaznaczonych wierszy powyżej w tym samym miesiącu;
  - ostrzeżenie ma ten sam tekst; w miesiącu z planem nie ma ostrzeżeń `Pozostało w miesiącu` w tym wierszu, a w miesiącu bez planu zostają jak dziś;
  - `ImportMonthRemainingWarningAnnotator`, `annotateRows`, jego test i opcjonalne pola `ImportRow` nie istnieją.
- **Dokumenty:** FR-TRX-08 i `.docs/prd.md:345`, `.ai/rules/js.md:24` oraz definicja To Spend w `CONTEXT.md` opisują ostrzeżenie z planu.

Weryfikacja: testy z faz 1–4 przechodzą na SQLite i na PostgreSQL. `composer ci:check`, `npm run check`, `npm run types:check` i `npm test` są zielone. Ręczne scenariusze w podglądzie (Manual Testing Steps) zachowują się jak wyżej.

### Key Discoveries:

- `MonthPlanProposal::forBudget` wywołuje `$budget->load(['monthPlans.items', 'categories'])` (`app/Support/MonthPlanProposal.php:42`), więc po nim `$budget->categories` nie kosztuje dodatkowego zapytania.
- `reading()` jest memoizowane (`MonthPlanProposal.php:76-81`), więc odczyt 12 miesięcy budżetu nie kosztuje dodatkowych zapytań.
- `forMonth` filtruje po zakresie dat `>= pierwszy dzień` i `< pierwszy dzień następnego miesiąca`, bez funkcji dat SQL (`MonthCategoryActuals.php:18-27`). Wariant wielomiesięczny musi zachować tę przenośność SQLite/PostgreSQL.
- `operation_date` ma cast `date` (`app/Models/FinancialOperation.php:60`).
- Domyślna data w formularzu jest liczona w UTC klienta (`ExpenseFormDialog.vue:307`), a serwer liczy `today` w UTC. Zbiór miesięcy z planem od serwera wystarcza dla każdej daty, którą przepuści walidacja.

## What We're NOT Doing

- Bez zmian we wzorze „do wydania”, w `ToSpendCalculator` i jego kopii TS ani w Month Remaining (kalkulatory i ich testy zostają nietknięte).
- Bez ostrzeżeń „do wydania” przy przelewie własnym, ruchu kapitału i odsetkach z ruchu kapitału w Imporcie (`interest_category_id`). Przelewy i ruchy kapitału nie zmieniają tej liczby. Odsetki zapisują osobną operację Income w `interest_category_id` (`ImportFinancialOperations.php:218-243`), więc zmieniają „do wydania”. Wiersz z odsetkami nie dostaje własnego ostrzeżenia, ale odsetki trafiają do `pending` (faza 4).
- Bez ostrzeżenia przy usuwaniu operacji. Usuwanie nie ma podglądu przed zapisem.
- Bez zmian w ostrzeżeniu rocznym (`over_limit_warning`, Available Limit) i w ostrzeżeniu ujemnego salda.
- Bez stałego bloku „Do wydania: przed → po” w formularzu, bo ostrzeżenie pokazuje się tylko przy spełnionym warunku.
- Bez blokady zapisu, potwierdzenia ani dodatkowego kroku (FR-017); `canSubmit` Importu się nie zmienia.
- Bez zmian w `.docs/adr/0033-…` (zapis decyzji), `.docs/design-system.md` (`:51` jest ogólne, `:29` dotyczy odczytów), FR-REP-13, specyfikacji Dashboardu i ADR-0031 (S-06/S-07).
- Bez zmian w `HandEntryMonthRemainingInputs`, `ImportMonthRemainingContext::forUser` i obu podglądach Month Remaining poza wygaszeniem ich w miesiącach z planem. Znane uproszczenie podglądu ręcznego (stara operacja porównywana po samym miesiącu, bez roku) zostaje.

## Implementation Approach

Serwer liczy dla każdego budżetu w wykonaniu surowe wejścia „do wydania” miesięcy z planem i podaje je jednym propsem `entryToSpend` stronom Operacje i podgląd Importu. Klient przy każdej zmianie formularza albo wyboru wiersza zamienia operację na zmiany pozycji (`dodaj` / `usuń` w kategorii i miesiącu) i liczy „przed” i „po” istniejącą kopią `toSpendMinorUnits`.

Kopia po stronie klienta jest uzasadniona warunkiem `AGENTS.md`. Ostrzeżenie musi reagować na datę, kategorię i kwotę przed zapisem, a podgląd Importu na zmianę zaznaczenia i kategorii przed zapisem. Wzór już ma kopię TS, więc nowy kod TS to tylko składanie pozycji i warunek, w czystych funkcjach z testami.

Rozstrzygnięcie „plan czy Month Remaining” zależy wyłącznie od tego, czy miesiąc daty jest w `entryToSpend`.

## Critical Implementation Details

- **Kontrakt propsa w jednym commicie.** Faza 1 dodaje `entryToSpend` po stronie PHP i w `defineProps` obu stron, choć używają go dopiero fazy 3 i 4. Jeśli `npm run check` zgłasza nieużytą zmienną, prop zostaje w typie `defineProps<>` bez destrukturyzacji do fazy, która go używa, bo ostrzeżenia lintera przerywają przebieg.
- **Usuwanie zmniejsza licznik, a nie zeruje flagi.** Zmiana `usuń` odejmuje kwotę od `actual` i zmniejsza `positive_amount_count` o 1, gdy usuwana kwota jest dodatnia. Licznik nie schodzi poniżej 0. Bill z dwoma wydatkami po edycji jednego nadal ma kwotę dodatnią.
- **Niepełny formularz nie ostrzega.** Bez daty, bez kategorii albo przy kwocie 0 lista ostrzeżeń „do wydania” jest pusta, także w edycji. Inaczej samo „usuń starą kwotę” dawałoby fałszywe ostrzeżenie w trakcie wpisywania.

## Phase 1: Wejścia serwera i usunięcie martwego annotatora

### Overview

Serwer podaje `entryToSpend` stronom Operacje i podgląd Importu. Martwy annotator Importu znika, a asercja propsa `monthRemainingContext` przenosi się do zachowanego testu.

### Changes Required:

#### 1. Faktyczne kwoty wielu miesięcy i licznik kwot dodatnich

**File**: `app/Support/MonthCategoryActuals.php`

**Intent**: Wystawić liczbę kwot dodatnich, potrzebną przy edycji, i odczyt faktycznych kwot kilku miesięcy budżetu jednym zapytaniem. Dzięki temu liczba zapytań strony nie rośnie z liczbą zatwierdzonych miesięcy.

**Contract**:
- `public function positiveCount(int $categoryId): int`, 0 dla nieznanej kategorii.
- `public static function forMonths(Budget $budget, list<int> $months): array<int, self>`: klucz to numer miesiąca, wartość ma tę samą semantykę co `forMonth($budget, $month)`. Wynik obejmuje każdy żądany miesiąc, także bez operacji.
- Jedno zapytanie po `financial_operations` w zakresie od pierwszego dnia najmniejszego do pierwszego dnia po największym miesiącu, grupowane po `category_id` i `operation_date`, składane w PHP po miesiącu daty. Bez funkcji dat SQL.
- `forMonth` może delegować do `forMonths`.

#### 2. Builder wejść „do wydania”

**File**: `app/Support/EntryToSpendInputs.php` (nowy, `php artisan make:class`)

**Intent**: Złożyć dla każdego budżetu użytkownika w fazie wykonania kategorie i miesiące z planem do dziś włącznie, z pozycjami jak w odczycie strony „Plan miesiąca”.

**Contract**: `public static function forUser(User $user, ?CarbonImmutable $today = null): array`, domyślnie `CarbonImmutable::today('UTC')`. Kształt (PHPDoc array shape):

```
{
  budgets: list<{
    year: int,
    categories: list<{ id: int, type: 'income'|'expense', month_item_kind: 'bill'|'running'|null }>,
    months: list<{
      month: int,
      items: list<{ category_id: int, plan_minor_units: int, actual_minor_units: int, positive_amount_count: int }>
    }>
  }>
}
```

- `categories` to wszystkie kategorie budżetu, także zarchiwizowane. `month_item_kind` dla dochodu jest `null`.
- `months` to miesiące `[year, month] <= [today.year, today.month]`, dla których `MonthPlanProposal::reading($month)->state !== 'none'`, rosnąco.
- `items` to suma zbiorów `array_keys($reading->amounts)` i `actuals->categoryIds()`. Plan kategorii spoza planu wynosi 0, jak w `MonthPlanController::monthPlanPayload`.
- Faktyczne kwoty przez `MonthCategoryActuals::forMonths` dla zbioru tych miesięcy.
- Budżety bez miesięcy z planem zostają na liście z pustym `months`.

#### 3. Props stron

**File**: `app/Http/Controllers/OperationsController.php`, `app/Http/Controllers/CsvImportController.php`

**Intent**: Podać `entryToSpend` obu stronom, a wiersze podglądu Importu przekazywać bez martwych adnotacji.

**Contract**:
- `'entryToSpend' => EntryToSpendInputs::forUser($user)` w `OperationsController::index` (obok `handEntryMonthRemaining`, `:180`) i w `CsvImportController::showPreview` (obok `monthRemainingContext`, `:148`).
- `rows` w `showPreview` to wiersze z sesji bez `ImportMonthRemainingContext::annotateRows`.

#### 4. Usunięcie annotatora

**File**: `app/Support/CsvImport/ImportMonthRemainingWarningAnnotator.php` (usunąć), `app/Support/CsvImport/ImportMonthRemainingContext.php`, `tests/Feature/ImportMonthRemainingWarningTest.php` (usunąć; zgoda użytkownika z sesji planowania), `resources/js/lib/import-preview-rows.ts`

**Intent**: Usunąć czwartą, martwą kopię logiki miesiąca, zanim obok niej pojawi się logika z planu.

**Contract**:
- Usunąć metodę `annotateRows` i klasę annotatora.
- `ImportMonthRemainingContext::forUser` zostaje.
- Z typu `ImportRow` znikają `show_category_month_remaining_warning`, `show_pool_month_remaining_warning` i `month_remaining_period_label`.
- Asercja `->has('monthRemainingContext.budgets', 1)` przenosi się do testu podglądu w `tests/Feature/CsvImportTest.php`, razem z asercją `entryToSpend`.

#### 5. Typy TS propsa

**File**: `resources/js/lib/entry-to-spend-warning.ts` (nowy, w tej fazie tylko typy), `resources/js/pages/Operations.vue`, `resources/js/pages/OperationsImportPreview.vue`

**Intent**: Strona TS kontraktu propsa w tym samym commicie co PHP (`AGENTS.md`, „Inertia props contract”).

**Contract**: `export type EntryToSpendInputs` odwzorowuje kształt z punktu 2 (pola snake_case jak w PHP). `entryToSpend: EntryToSpendInputs` w `defineProps<>` obu stron (zob. Critical Implementation Details).

#### 6. Testy

**File**: `tests/Feature/MonthCategoryActualsTest.php`, `tests/Feature/EntryToSpendInputsTest.php` (nowy, klasa PHPUnit jak siostrzane testy S-01), `tests/Feature/CsvImportTest.php`, test strony Operacje (np. `tests/Feature/HandEntryMonthRemainingTest.php` obok asercji `handEntryMonthRemaining`)

**Intent**: Pokryć nowe odczyty, granice zbioru miesięcy, stałą liczbę zapytań i oba propsy.

**Contract**: przypadki opisane w Testing Strategy → faza 1.

### Success Criteria:

#### Automated Verification:

- Testy `MonthCategoryActualsTest`, `EntryToSpendInputsTest`, `CsvImportTest` i testu strony Operacje przechodzą na SQLite: `php artisan test --compact tests/Feature/MonthCategoryActualsTest.php tests/Feature/EntryToSpendInputsTest.php tests/Feature/CsvImportTest.php tests/Feature/HandEntryMonthRemainingTest.php`
- Te same testy przechodzą na PostgreSQL (obraz Sail na worktree, polecenie z `AGENTS.md`, „Test database”)
- Grep `annotateRows|ImportMonthRemainingWarningAnnotator|show_category_month_remaining_warning` po `app`, `resources/js`, `tests` nie daje trafień
- `vendor/bin/pint --dirty --format agent`, `npm run check` i `npm run types:check` (po `npm run build`) są zielone

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej przejdź do fazy 2. Ta faza nie ma zmian widocznych w UI, więc nie wymaga potwierdzenia ręcznego.

---

## Phase 2: Czysta logika ostrzeżenia w TS

### Overview

Czyste funkcje zamieniają stan ręcznego formularza na zmiany pozycji, a zmiany na ostrzeżenia „do wydania” per miesiąc, z testami obok kodu. Dochodzi też klucz tłumaczenia. W tej fazie nic jeszcze nie jest podłączone do UI.

### Changes Required:

#### 1. Logika ostrzeżenia

**File**: `resources/js/lib/entry-to-spend-warning.ts`

**Intent**: Jedno miejsce, które z wejść `entryToSpend` i listy zmian liczy „przed” i „po” każdego dotkniętego miesiąca z planem istniejącym `toSpendMinorUnits` i zwraca ostrzeżenia spełniające warunek.

**Contract**:

```ts
export type ToSpendEntryChange = {
    operationDate: string; // YYYY-MM-DD
    categoryId: number;
    amountMinorUnits: number; // ze znakiem jak w zapisie: dochód i wydatek > 0, zwrot < 0
    direction: 'add' | 'remove';
};

export type ToSpendWarning = { year: number; month: number; toSpendAfterMinorUnits: number };

export function monthHasPlan(inputs: EntryToSpendInputs, operationDate: string): boolean;

export function previewToSpendWarnings(
    inputs: EntryToSpendInputs,
    changes: ToSpendEntryChange[],
    pending?: ToSpendEntryChange[],
): ToSpendWarning[];

export type HandEntryToSpendState = {
    kind: 'income' | 'expense' | 'refund'; // rodzaj zapisu po edycji
    operationDate: string;
    categoryId: number | null;
    amountMinorUnits: number; // kwota z pola formularza, bez znaku
    original: { operationDate: string; categoryId: number; amountMinorUnits: number } | null; // kwota ze znakiem jak w bazie
};

export function handEntryToSpendChanges(state: HandEntryToSpendState): ToSpendEntryChange[];
```

- `handEntryToSpendChanges` zamienia stan ręcznego formularza na zmiany:
  - `add` w `categoryId` z kwotą `abs` dla `income` i `expense` oraz `-abs` dla `refund`;
  - w edycji dochodzi `remove` z `original` bez przeliczania znaku, bo `original.amountMinorUnits` to kwota zapisana (dochód i wydatek > 0, zwrot < 0);
  - bez daty, bez kategorii albo przy kwocie 0 zwraca `[]`, także w edycji (Critical Implementation Details).
- Nie kopiować `editedRecord` z `IncomeFormDialog.vue:117-126`: przy „zamień na zwrot” podaje `-Math.abs(original)` w kategorii dochodu, a `remove` musi odjąć `+original` w kategorii dochodu, a `add` dać `-abs` w wybranej kategorii wydatku.

- `previewToSpendWarnings` grupuje `changes` po roku i miesiącu daty i pomija miesiące bez planu. Dla każdego miesiąca:
  - baza = pozycje miesiąca + `pending` tego miesiąca;
  - przed = `toSpendMinorUnits(baza)`, po = `toSpendMinorUnits(baza + changes)`;
  - ostrzeżenie, gdy `po < 0 && po < przed`.
- Kategoria spoza `items` dostaje pozycję z planem 0, kwotą 0 i licznikiem 0, z `type`/`month_item_kind` z `categories` budżetu. Kategorię nieznaną w budżecie pomija.
- `hasPositiveAmount = positive_amount_count > 0` po zastosowaniu zmian. Reguła licznika jest opisana w Critical Implementation Details.
- Wynik jest uporządkowany chronologicznie.
- Import kalkulatora jest względny (`./to-spend-calculator`), bo w testach alias `@` nie działa.

#### 2. Testy

**File**: `resources/js/lib/entry-to-spend-warning.test.ts` (nowy)

**Intent**: Pokryć warunek, niemonotoniczność wzoru, edycję i kumulację.

**Contract**: przypadki opisane w Testing Strategy → faza 2.

#### 3. Tłumaczenie

**File**: `lang/pl/ui.php`

**Intent**: Tekst ostrzeżenia zgodny z decyzją „zdanie + kwota po operacji”, w formie z nawiasem jak obecne ostrzeżenia miesiąca (mianownik nazwy miesiąca).

**Contract**: `operations.to_spend_negative_warning` => `'Po tej operacji do wydania wyniesie :amount (:month_year).'`

### Success Criteria:

#### Automated Verification:

- `npm test` przechodzi, w tym `entry-to-spend-warning.test.ts`
- `npm run check` jest zielone

**Implementation Note**: Po zielonej weryfikacji automatycznej przejdź do fazy 3. Ta faza nie ma zmian widocznych w UI.

---

## Phase 3: Ostrzeżenie przy ręcznej operacji

### Overview

`ExpenseFormDialog` i `IncomeFormDialog` pokazują ostrzeżenie „do wydania” dla starego i nowego miesiąca. Ostrzeżenia `Pozostało w miesiącu` znikają w miesiącu z planem.

### Changes Required:

#### 1. Formularz wydatku

**File**: `resources/js/components/ExpenseFormDialog.vue`

**Intent**: Zamienić stan formularza na zmiany „do wydania” i dopisać wiersze ostrzeżenia do istniejącej listy „Uwaga”.

**Contract**:
- Nowy prop `entryToSpend: EntryToSpendInputs`.
- Zmiany liczy `handEntryToSpendChanges` z fazy 2: `kind` (`expense`, `refund` albo `income` przy „zamień na wpływ”, `:134-150`), data, wybrana kategoria, kwota z pola i w edycji `original` z `props.operation.operation_date`, `originalCategoryId` i `originalAmountMinorUnits` (kwota ze znakiem jak w bazie, `:300`). Komponent nie składa zmian sam.
- `showOverMonthCategoryWarning` / `showOverMonthPoolWarning` są fałszywe, gdy `monthHasPlan(entryToSpend, operationDate)`.
- Każdy `ToSpendWarning` daje `<li>` z `to_spend_negative_warning`, kwotą przez `formatMoney` i etykietą miesiąca ostrzeżenia (wzorzec `operationMonthYearLabel`, `:255-265`).
- `showExpenseWarnings` uwzględnia nowe wiersze.

#### 2. Formularz dochodu

**File**: `resources/js/components/IncomeFormDialog.vue`

**Intent**: Te same ostrzeżenia dla dochodu i dla edycji „zamień na zwrot”.

**Contract**:
- Nowy prop `entryToSpend: EntryToSpendInputs`.
- Zmiany liczy `handEntryToSpendChanges`: `kind` to `refund`, gdy zaznaczone jest `isExpenseRefund` (`:357-374`, kategoria z listy wydatków), a w pozostałych przypadkach `income`. Kwota pochodzi z `amountMinorUnits`. W edycji `original` to `originalCategoryId` i `originalAmountMinorUnits` (dodatni dochód, `:197-199`) z oryginalną datą. `editedRecord` z `:117-126` nie jest tu źródłem.
- Wiersze ostrzeżenia trafiają do istniejącego `<Alert variant="warning">` (`:446-460`), którego warunek uwzględnia nowe wiersze.

#### 3. Strona Operacje

**File**: `resources/js/pages/Operations.vue`

**Intent**: Przekazać prop do obu montowań każdego dialogu (tworzenie i edycja).

**Contract**: `:entry-to-spend="entryToSpend"` na `ExpenseFormDialog` (`:346-362`) i `IncomeFormDialog` (`:330-345`).

### Success Criteria:

#### Automated Verification:

- `npm run check`, `npm run types:check` (po `npm run build`) i `npm test` są zielone
- Testy strony Operacje przechodzą: `php artisan test --compact tests/Feature/HandEntryMonthRemainingTest.php`

#### Manual Verification:

- W podglądzie z worktree (`AGENTS.md`, „Preview from a worktree”) wydatek Running ponad plan, który sprowadza „do wydania” bieżącego miesiąca poniżej zera, pokazuje ostrzeżenie z kwotą zgodną z liczbą na stronie „Plan miesiąca” po zapisie, a zapis się udaje
- Wydatek Running w granicach planu w miesiącu z ujemnym „do wydania” nie pokazuje ostrzeżenia „do wydania”
- Wydatek w kategorii równomiernej w bieżącym miesiącu (z planem) nie pokazuje ostrzeżeń `Pozostało w miesiącu`; ten sam wydatek z datą minionego miesiąca bez planu pokazuje je jak dziś
- Dochód mniejszy od planu, wpisany jako pierwszy w miesiącu, który sprowadza „do wydania” poniżej zera, pokazuje ostrzeżenie w formularzu dochodu
- Edycja przenosząca jedyny wydatek Bill z bieżącego miesiąca do minionego miesiąca bez planu ostrzega o bieżącym miesiącu, gdy plan Bill przewyższa kwotę i wynik jest ujemny

**Implementation Note**: Po zielonej weryfikacji automatycznej zbuduj i uruchom podgląd i zatrzymaj się na ręczne potwierdzenie przez człowieka przed fazą 4.

---

## Phase 4: Podgląd Importu i dokumenty

### Overview

Wiersze Importu dostają ostrzeżenie „do wydania” z kumulacją w kolejności wierszy. Dokumenty opisują nowe ostrzeżenie.

### Changes Required:

#### 1. Ostrzeżenia wierszy Importu

**File**: `resources/js/lib/import-to-spend-warnings.ts` (nowy), `resources/js/lib/import-to-spend-warnings.test.ts` (nowy)

**Intent**: Czysta funkcja, która przechodzi zaznaczone wiersze w kolejności indeksu, buduje dla każdego zmianę `add` i kumuluje wcześniejsze wiersze jako `pending`. Reguła jest taka sama jak dzisiejsze kumulowanie Month Remaining, ale obejmuje wszystkie rodzaje dochodu i wydatku.

**Contract**:
- `computeImportToSpendWarnings(rows: ImportRow[], selections: Record<number, RowSelection>, inputs: EntryToSpendInputs): Record<number, ToSpendWarning | null>`.
- Wiersz jest liczony, gdy jest `valid`, ma `operation_date`, jest zaznaczony, ma typ `income` / `expense` / `refund` i kategorię.
- Kwota: `income` i `expense` dają `abs`, `refund` daje `-abs` (`ImportFinancialOperations.php:249-253`).
- Wynik wiersza to pierwszy element `previewToSpendWarnings(inputs, [zmiana], pending)` albo `null`, po czym zmiana trafia do `pending`.
- Zaznaczony, ważny wiersz `capital_movement` z `interest_category_id` i `interestSplitValid(row, selection)` (`import-preview-rows.ts:152`) dopisuje do `pending` zmianę `add` w `interest_category_id` z kwotą `parsedInterestAmountMinorUnits(selection)` (dodatnia). Jego wynik to `null`.
- Typy `ImportRow` / `RowSelection` są importowane przez `import type`.

#### 2. Strona i wiersz podglądu

**File**: `resources/js/pages/OperationsImportPreview.vue`, `resources/js/components/ImportPreviewRow.vue`

**Intent**: Pokazać ostrzeżenie w wierszu i wygasić w nim `Pozostało w miesiącu`, gdy miesiąc daty ma plan.

**Contract**:
- Strona liczy `computeImportToSpendWarnings` w `computed` (jak `monthRemainingWarnings`, `:144-152`).
- Strona przekazuje wierszowi nowy prop `toSpendWarning?: ToSpendWarning | null`, a `monthRemainingWarnings` tylko wtedy, gdy `!monthHasPlan(entryToSpend, row.operation_date)`.
- `ImportPreviewRow` renderuje `<Alert variant="warning">` z `to_spend_negative_warning` (kwota przez `formatMoney`, etykieta miesiąca z `t.budget.month_name_N`) obok istniejących alertów (`:543-579`).

#### 3. Dokumenty

**File**: `.docs/prd.md`, `.ai/rules/js.md`, `CONTEXT.md`

**Intent**: Dokumenty opisują to, co robi kod (ADR-0033:7 przypisuje FR-TRX-08 i `js.md:24` do S-08).

**Contract**:
- `.docs/prd.md:191` FR-TRX-08: zostaje zdanie o Available Limit. Zdanie o Month Remaining dotyczy miesięcy bez planu miesiąca. Nowe zdanie: gdy miesiąc daty operacji ma plan miesiąca (także propozycję), dochód, wydatek albo zwrot, po którym To Spend tego miesiąca jest ujemne i niższe niż przed operacją, daje ostrzeżenie z kwotą To Spend po operacji; to samo dotyczy wiersza podglądu Importu.
- `.docs/prd.md:345`: lista ostrzeżeń bez blokady obejmuje zejście To Spend poniżej zera przy operacji.
- `.ai/rules/js.md:24`: lista „Warnings that do not block saving” obejmuje negative To Spend after an entry.
- `CONTEXT.md:80` (To Spend): zdanie, że w miesiącu z planem operacja albo wiersz Importu, po którym To Spend jest ujemne i niższe niż przed, daje ostrzeżenie bez blokady.

### Success Criteria:

#### Automated Verification:

- `npm test` przechodzi, w tym `import-to-spend-warnings.test.ts`
- `npm run check` i `npm run types:check` (po `npm run build`) są zielone
- Pełna bramka CI na PostgreSQL przechodzi: `composer ci:check` w obrazie Sail na worktree (`AGENTS.md`, „Test database”)

#### Manual Verification:

- W podglądzie Importu trzy zaznaczone wiersze wydatków w bieżącym miesiącu, które razem sprowadzają „do wydania” poniżej zera, pokazują ostrzeżenie od pierwszego wiersza, który sprowadza „do wydania” poniżej zera, z kwotą kumulowaną
- Wiersz z datą minionego miesiąca bez planu i kategorią równomierną pokazuje ostrzeżenia `Pozostało w miesiącu` jak dziś, a wiersz bieżącego miesiąca ich nie pokazuje
- Odznaczenie wiersza albo zmiana kategorii przelicza ostrzeżenia natychmiast, a Import z ostrzeżeniami zapisuje się bez dodatkowego kroku

**Implementation Note**: Po zielonej weryfikacji automatycznej przebuduj podgląd i zatrzymaj się na ręczne potwierdzenie przez człowieka.

---

## Testing Strategy

### Unit Tests:

- **Faza 1 — `MonthCategoryActualsTest`:**
  - `positiveCount` liczy dodatnie kwoty i pomija zwroty;
  - `forMonths` daje dla każdego miesiąca to samo co `forMonth` (granice miesięcy, inne budżety pominięte), zwraca miesiące bez operacji i wykonuje jedno zapytanie.
- **Faza 1 — `EntryToSpendInputsTest`:**
  - bieżący miesiąc bez zapisu ma pozycje propozycji i faktyczne kwoty;
  - miniony miesiąc z zapisanym planem jest obecny z `current_amount_minor_units`; miniony bez planu i miesiące przyszłe są nieobecne;
  - kategoria z operacją spoza planu ma plan 0; zarchiwizowana kategoria jest w `categories`;
  - budżet poza wykonaniem jest pominięty; `month_item_kind` dochodu to `null`;
  - liczba zapytań jest taka sama dla budżetu z 1 i z 4 miesiącami z planem.
- **Faza 2 — `entry-to-spend-warning.test.ts`:**
  - miesiąc bez planu: `monthHasPlan` false, brak ostrzeżeń;
  - Running ponad plan z dodatniego na ujemne: ostrzeżenie z kwotą po; Running w granicach planu przy ujemnym „do wydania”: brak;
  - wydatek pogłębiający ujemne „do wydania”: ostrzeżenie;
  - pierwszy wydatek Bill mniejszy od planu: brak; pierwszy dochód mniejszy od planu sprowadzający poniżej zera: ostrzeżenie; zwrot: brak;
  - kategoria spoza pozycji (jednorazowa, plan 0): wydatek obniża;
  - edycja w tym samym miesiącu liczona raz z `remove` + `add`;
  - edycja przenosząca jedyny wydatek Bill do innego miesiąca ostrzega o starym miesiącu; Bill z dwoma wydatkami po `remove` jednego zachowuje kwotę dodatnią;
  - zmiany w grudniu roku Y−1 i styczniu roku Y trafiają do właściwych budżetów;
  - `pending` przesuwa „przed”;
  - `handEntryToSpendChanges`: wydatek (`+abs`), zwrot (`-abs`), dochód (`+abs`); edycja wydatku na wpływ (`remove` +stara kwota w kategorii wydatku, `add` +abs w kategorii dochodu); edycja dochodu na zwrot (`remove` +stara kwota w kategorii dochodu, `add` −abs w kategorii wydatku); edycja zwrotu (`remove` z ujemną kwotą zapisaną); brak daty, kategorii albo kwota 0 → `[]`, także w edycji.
- **Faza 4 — `import-to-spend-warnings.test.ts`:**
  - kumulacja w kolejności indeksu. Przykład: miesiąc ma „do wydania” 50 zł (dochód z faktycznym wpływem 50 zł). Wiersze to wydatek 30 zł i wydatek 40 zł w kategorii Running z planem 0, potem dochód 100 zł w kategorii dochodu, która ma już wpływ. Ostrzeżenie pokazuje tylko drugi wiersz, z kwotą −20 zł;
  - wiersz odznaczony, bez kategorii albo `own_transfer` jest pominięty i nie zasila `pending`;
  - ruch kapitału z odsetkami nie ostrzega, ale jego odsetki zmieniają „przed” późniejszego wiersza w tym samym miesiącu;
  - zwrot z `-abs`;
  - wiersze z różnych miesięcy nie wpływają na siebie.

### Integration Tests:

- Propsy `entryToSpend` (Operacje, podgląd Importu) i zachowany `monthRemainingContext` przez `assertInertia` (faza 1).
- Istniejący test „expense exceeding month remaining is still saved” (`tests/Feature/HandEntryMonthRemainingTest.php:82`) dalej potwierdza brak blokady. Serwer nie dostaje nowej walidacji.

### Manual Testing Steps:

1. Zbuduj podgląd z worktree (osobna baza, `migrate --seed`, `npm run build`, `php artisan serve` na porcie 8090) i zaloguj się jako `test@example.com` / `password`.
2. Na stronie „Plan miesiąca” odczytaj „do wydania” bieżącego miesiąca (propozycja). Dodaj wydatek Running przekraczający tę liczbę: ostrzeżenie z kwotą, zapis działa, a nowa liczba na stronie planu jest zgodna z kwotą z ostrzeżenia.
3. Dodaj wydatek w kategorii równomiernej z datą minionego miesiąca bez planu: widać ostrzeżenia `Pozostało w miesiącu`, a ostrzeżenia „do wydania” nie ma.
4. Zatwierdź plan bieżącego miesiąca i powtórz krok 2: zachowanie jest takie samo dla planu zatwierdzonego.
5. Zaimportuj CSV z kilkoma wydatkami bieżącego miesiąca i jednym minionego miesiąca; zaznacz wiersze i wybierz kategorie. Sprawdź kumulację, wygaszanie `Pozostało w miesiącu` i zapis bez dodatkowego kroku.

## Performance Considerations

- „Wprowadzanie operacji nie może stać się dłuższe” (ryzyko S-08 w roadmapie):
  - liczenie po stronie klienta to kilka przejść po pozycjach jednego lub dwóch miesięcy przy każdej zmianie;
  - w Imporcie jest to jedno przejście po wierszach z kosztem liniowym w liczbie pozycji miesiąca.
- Koszt serwera na budżet w wykonaniu to `MonthPlanProposal::forBudget` (stała liczba zapytań) plus jedno zapytanie o faktyczne kwoty, niezależnie od liczby miesięcy z planem. Pilnuje tego test z fazy 1.

## Migration Notes

Brak migracji i zmian danych. Usunięcie annotatora nie zmienia zapisu Importu, bo jego pola nie trafiały do `store`.

## References

- Research: `context/changes/plan-based-entry-warnings/research.md`
- Roadmapa: `context/foundation/roadmap.md` (S-08); PRD: `context/foundation/prd.md:138` (FR-014), `:147-148` (FR-017), `:206`
- Domena: `CONTEXT.md:79-81` (To Spend), `:63-65` (Month Plan), `:55-57` (Month Remaining); `.docs/adr/0033-month-plan-beside-annual-budget.md:7`
- Poprzedni wycinek: `context/archive/2026-11-02-month-plan-and-to-spend/plan.md`
- Wzorce: `app/Http/Controllers/MonthPlanController.php:103-153` (pozycje odczytu), `resources/js/lib/import-month-remaining-warnings.ts:94-246` (kumulacja w Imporcie), `tests/Feature/MonthPlanProposalTest.php:229-248` (liczenie zapytań), `resources/js/lib/to-spend-calculator.test.ts` (test TS)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Wejścia serwera i usunięcie martwego annotatora

#### Automated

- [x] 1.1 Testy `MonthCategoryActualsTest`, `EntryToSpendInputsTest`, `CsvImportTest` i testu strony Operacje przechodzą na SQLite — 6b4b706
- [x] 1.2 Te same testy przechodzą na PostgreSQL (obraz Sail na worktree) — 6b4b706
- [x] 1.3 Grep `annotateRows|ImportMonthRemainingWarningAnnotator|show_category_month_remaining_warning` po `app`, `resources/js`, `tests` nie daje trafień — 6b4b706
- [x] 1.4 `vendor/bin/pint --dirty --format agent`, `npm run check` i `npm run types:check` (po `npm run build`) są zielone — 6b4b706

### Phase 2: Czysta logika ostrzeżenia w TS

#### Automated

- [x] 2.1 `npm test` przechodzi, w tym `entry-to-spend-warning.test.ts` — 8e0399b
- [x] 2.2 `npm run check` jest zielone — 8e0399b

### Phase 3: Ostrzeżenie przy ręcznej operacji

#### Automated

- [x] 3.1 `npm run check`, `npm run types:check` (po `npm run build`) i `npm test` są zielone
- [x] 3.2 Testy strony Operacje przechodzą

#### Manual

- [x] 3.3 Wydatek Running ponad plan sprowadzający „do wydania” poniżej zera pokazuje ostrzeżenie z kwotą zgodną ze stroną „Plan miesiąca” i zapisuje się
- [x] 3.4 Wydatek Running w granicach planu przy ujemnym „do wydania” nie pokazuje ostrzeżenia
- [x] 3.5 Miesiąc z planem nie pokazuje ostrzeżeń `Pozostało w miesiącu`, miniony miesiąc bez planu pokazuje je jak dziś
- [x] 3.6 Pierwszy dochód mniejszy od planu sprowadzający „do wydania” poniżej zera ostrzega w formularzu dochodu
- [x] 3.7 Edycja przenosząca jedyny wydatek Bill z bieżącego miesiąca ostrzega o bieżącym miesiącu

### Phase 4: Podgląd Importu i dokumenty

#### Automated

- [ ] 4.1 `npm test` przechodzi, w tym `import-to-spend-warnings.test.ts`
- [ ] 4.2 `npm run check` i `npm run types:check` (po `npm run build`) są zielone
- [ ] 4.3 Pełna bramka CI na PostgreSQL przechodzi (`composer ci:check` w obrazie Sail)

#### Manual

- [ ] 4.4 Zaznaczone wiersze bieżącego miesiąca kumulują się i ostrzegają od pierwszego wiersza, który sprowadza „do wydania” poniżej zera
- [ ] 4.5 Wiersz minionego miesiąca bez planu pokazuje `Pozostało w miesiącu`, wiersz bieżącego miesiąca nie
- [ ] 4.6 Odznaczenie albo zmiana kategorii przelicza ostrzeżenia natychmiast, a Import zapisuje się bez dodatkowego kroku
