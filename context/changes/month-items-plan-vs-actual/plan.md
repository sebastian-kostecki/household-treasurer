# S-04: Pozycje miesiąca — plan i wykonanie — plan implementacji

## Overview

Strona „Plan miesiąca” pokazuje już plan i kwotę faktyczną każdej pozycji (S-01), ale bez odchylenia, bez znacznika realizacji i bez rozróżnienia kategorii jednorazowych. Ten plan dodaje cztery rzeczy:

- odchylenie (faktycznie − plan) z wyróżnieniem pozycji ponad plan (FR-006),
- plakietkę rozliczenia rachunku albo dochodu (FR-006, „znacznik realizacji”),
- Pozostały limit roczny przy każdej jednorazowej (FR-008),
- zwiniętą sekcję jednorazowych bez planu i bez operacji w miesiącu (FR-008, ryzyko z roadmapy: „długa lista kategorii z zerowym planem”).

Wszystkie odczyty liczy serwer. W edytowalnej propozycji odchylenie przelicza się na żywo z wpisanej kwoty przez bliźniaczą kopię TS.

## Current State Analysis

- `MonthPlanController::itemRow` (`app/Http/Controllers/MonthPlanController.php:173-195`) wysyła per wiersz `plan_minor_units`, `actual_minor_units`, `has_positive_amount`, `month_item_kind`, `is_one_off`, `is_archived`, `in_plan`, `can_change_kind`. Brakuje odchylenia, flagi ponad plan, znacznika, limitu i podziału na sekcje.
- `MonthPlan.vue` (480 linii) rysuje siatkę Kategoria | Plan | Faktycznie (`:349-371`), plakietki „Jednorazowa”, „Zarchiwizowane”, „Poza planem” (`:379-405`), przełącznik rodzaju (`:407-442`), pole kwoty w edytowalnej propozycji (`:446-453`) i ukryte pola zatwierdzenia dla każdego wiersza `in_plan` (`:324-340`).
- Propozycja wstawia każdą niezarchiwizowaną jednorazową z kwotą 0 (`app/Support/MonthPlanProposal.php:113-119,148-150`). `ApproveMonthPlan` wymaga dokładnie tego zbioru id (`app/Actions/ApproveMonthPlan.php:47-55`). Zwinięta sekcja musi więc dalej wysyłać ukryte pola swoich wierszy.
- `MonthCategoryActuals` (`app/Support/MonthCategoryActuals.php`) zna sumę ze znakiem, flagę kwoty dodatniej i zbiór kategorii z jakąkolwiek operacją w miesiącu (`categoryIds()`). Nie ma metody „ma operację” dla jednej kategorii.
- Pozostały limit = `CategoryExecutionMetrics::remainingMinorUnits(planned_amount_minor_units, execution_minor_units)` (`app/Support/CategoryExecutionMetrics.php:7-10`). Oba pola są już w modelu `Category`, który kontroler pobiera (`MonthPlanController.php:114-117`). `execution_minor_units` to suma roczna do dziś.
- Podgląd „do wydania” w edytowalnej propozycji działa w TS (`MonthPlan.vue:136-154`, `resources/js/lib/to-spend-calculator.ts`).
- Znaczenie „znacznika realizacji” pochodzi z arkusza użytkownika `storage/app/private/budget_szablon.xlsx` (zakładki miesięcy, kolumna E): `IF(plan=faktycznie,"✓"," ")`, przy czym plan to `IF(faktycznie=0, domyślna, faktycznie)`. ✓ oznacza więc, że kwota faktyczna zastąpiła plan, czyli regułę Rachunku. Wiersz „Codzienne wydatki” pokazuje zamiast ✓ procent. Ten procent nie wchodzi do zakresu (patrz Key Decisions w briefie).

## Desired End State

Na stronie „Plan miesiąca”, w każdym stanie z planem (propozycja, zatwierdzony, miniony zatwierdzony):

- każdy wiersz dochodu i wydatku ma odchylenie = faktycznie − plan. Rachunek i dochód przed pierwszą kwotą dodatnią pokazują „—”. Wydatek ponad plan (faktycznie > plan, także przy planie 0 i dla jednorazowej) ma odchylenie w `text-destructive`. Dochód z dodatnim odchyleniem ma `text-success`. Odchylenie ujemne albo zerowe nie ma koloru,
- rachunek po pierwszym wydatku z kwotą dodatnią ma plakietkę „Zapłacone”, a dochód po pierwszym wpływie plakietkę „Wpłynęło”. Pozycja bieżąca nie ma znacznika,
- każda jednorazowa (na liście i w sekcji) pokazuje „Pozostały limit roczny” na dziś, w `text-destructive`, gdy jest ujemny,
- jednorazowe z planem 0 i bez żadnej operacji w miesiącu są w zwiniętej domyślnie sekcji pod listą wydatków, z licznikiem. Gdy takich nie ma, sekcji nie ma,
- w edytowalnej propozycji wpisanie kwoty planu od razu zmienia odchylenie i wyróżnienie ponad plan. Wiersze nie zmieniają sekcji podczas pisania, a zatwierdzenie wysyła kwoty wszystkich pozycji planu, także tych ze zwiniętej sekcji,
- miniony miesiąc bez planu (`state=none`) wygląda jak dziś.

Weryfikacja: testy jednostkowe pary PHP/TS, `assertInertia` nowych pól na fixturze czerwcowej, pełna bramka CI i ręczny przegląd w podglądzie z worktree.

### Key Discoveries:

- Predykat „pozycja ponad plan” jest już zdefiniowany w `CONTEXT.md:80` (To Spend). S-07 użyje go do powiadomienia `exceeded`, więc liczymy go raz, w PHP.
- Fixtura `tests/Feature/MonthPlanPageTest.php:370-399` daje gotowe przypadki:
  - Pensja: brak wpływu, więc „—”,
  - Czynsz: Rachunek 200 000 / 195 000, więc −5 000 i „Zapłacone”,
  - Jedzenie: −90 000,
  - Paliwo: +12 000, ponad plan,
  - Prezent: jednorazowa 0 / 30 000, więc +30 000, ponad plan, na liście.
- `FinancialOperation::factory()` nie zwiększa `execution_minor_units` (`tests/Feature/MonthPlanPageTest.php:401-410`), więc test limitu ustawia je jawnie.
- Wzorzec zwiniętej sekcji: `resources/js/components/BudgetCategoryList.vue:451-475` (`Collapsible`, `CollapsibleTrigger` z `Button variant="ghost"`, licznik `Badge variant="secondary"`, obracany `ChevronDown`).
- `UiTranslations.month_plan` to `Record<string, string>` (`resources/js/types/shell.ts:41`), więc nowe klucze `lang/pl/ui.php` nie wymagają zmiany typu.
- `Badge` ma tylko warianty `default|secondary|destructive|outline` (`resources/js/components/ui/badge/index.ts:9-20`). Plakietki „Wpłynęło” nie robimy przez nowy wariant w `components/ui/*` (`.ai/rules/js.md`), tylko klasami tokenów na `Badge variant="outline"`.
- `format.ts` nie ma formatu kwoty ze znakiem plus. `formatFinancialOperationAmount` ma inną semantykę (typ operacji), więc go nie używamy.

## What We're NOT Doing

- Procentu realizacji dla pozycji bieżących (arkusz: „Codzienne wydatki”). Tempo to S-05, a ADR-0033 odrzucił tempo per kategoria.
- Powiadomień na Dashboardzie (S-07). S-04 tylko wystawia predykat `is_over_plan`.
- Zmian dla minionego miesiąca bez planu (`state=none`). Należy do S-06.
- „Reszty limitu na koniec przeglądanego miesiąca” ani limitu po odjęciu planu miesiąca. Pokazujemy Available Limit na dziś, tak jak strona Budżet.
- Przenoszenia wierszy między listą a zwiniętą sekcją podczas wpisywania kwot.
- Zmiany kontraktu `ApproveMonthPlan` ani schematu bazy.
- Wyróżnienia jednorazowych na stronie Budżet (`budget.one_off_badge` zostaje nieużywane).

## Implementation Approach

Serwer dokłada do `itemRow` pięć pól liczonych z istniejących danych. Reguła odchylenia i ponad plan trafia do małej czystej klasy `App\Support\MonthItemDeviation`. Jej bliźniak TS istnieje tylko dla podglądu edytowalnej propozycji. Uzasadnienie wymagane przez `AGENTS.md`: propozycji nie da się zapisać przed zatwierdzeniem, więc podgląd musi reagować przed wysłaniem, tak jak „do wydania” obok.

Zwinięta sekcja to flaga `is_idle_one_off` na wierszu, a nie osobna tablica. Wiersze zostają w `expense_items`, więc `allRows` (podgląd „do wydania”) i `planRows` (ukryte pola zatwierdzenia) w `MonthPlan.vue` dalej obejmują wszystkie pozycje. Vue tylko dzieli wydatki do wyświetlenia. Faza 1 zmienia więc propsy bez zmiany wyglądu ani zachowania strony. Faza 2 buduje widok.

Markup wiersza przenosimy do komponentu, bo lista i zwinięta sekcja rysują ten sam wiersz.

### Dokładne predykaty (`MonthItemDeviation`, kopia TS identyczna)

Wejście: `type` (dochód/wydatek), `kind` (Bill/Running, dla dochodu ignorowany), `plan`, `actual`, `has_positive_amount`.

- **Odchylenie** (`deviationMinorUnits`, `int|null`): `null`, gdy (`type` = dochód albo `kind` = Bill) i nie ma kwoty dodatniej. W pozostałych przypadkach `actual − plan`. Rachunek z samym zwrotem (faktycznie −10 000, brak dodatniej) daje więc `null`.
- **Ponad plan** (`isOverPlan`, `bool`): `type` = wydatek i `actual > plan`, dowolnie, każdego rodzaju, także przy planie 0 (`CONTEXT.md:80`). Dla dochodu zawsze `false`.

Pola wiersza poza tą klasą (w kontrolerze):

- **Rozliczone** (`is_settled`): (`type` = dochód albo `kind` = Bill) i `has_positive_amount`.
- **Pozostały limit** (`available_limit_minor_units`, `int|null`): dla `is_one_off` `CategoryExecutionMetrics::remainingMinorUnits(planned_amount_minor_units, execution_minor_units)`, poza tym `null`.
- **Bezczynna jednorazowa** (`is_idle_one_off`): `is_one_off` i `plan_minor_units === 0` i kategoria nie ma żadnej operacji w miesiącu. Każdy taki wiersz jest `in_plan`, bo wiersze spoza planu istnieją tylko dzięki operacjom.

Przypadki graniczne:

| Wiersz | plan | faktycznie | dodatnia | odchylenie | ponad plan | rozliczone | bezczynna |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Jedzenie (bieżąca) | 150 000 | 60 000 | tak | −90 000 | nie | nie | — |
| Paliwo (bieżąca) | 40 000 | 52 000 | tak | +12 000 | tak | nie | — |
| Czynsz (rachunek), bez wpłaty | 200 000 | 0 | nie | `null` | nie | nie | — |
| Czynsz (rachunek), sam zwrot | 200 000 | −10 000 | nie | `null` | nie | nie | — |
| Czynsz (rachunek), zapłacony | 200 000 | 195 000 | tak | −5 000 | nie | tak | — |
| Pensja, bez wpływu | 800 000 | 0 | nie | `null` | nie | nie | — |
| Pensja, wpłynęło więcej | 800 000 | 820 000 | tak | +20 000 | nie | tak | — |
| Prezent (jednorazowa), wydatek | 0 | 30 000 | tak | +30 000 | tak | nie | nie |
| Zwrot (jednorazowa), sam zwrot | 0 | −5 000 | nie | −5 000 | nie | nie | nie |
| RTV (jednorazowa), zakup i zwrot | 0 | 0 | tak | 0 | nie | nie | nie |
| Wakacje (jednorazowa), brak operacji | 0 | 0 | nie | 0 | nie | nie | tak |
| Wakacje (jednorazowa), plan 2 000 zatwierdzony | 200 000 | 0 | nie | −200 000 | nie | nie | nie |

## Critical Implementation Details

- **Sekcja ustalana przy wczytaniu.** W edytowalnej propozycji przynależność do zwiniętej sekcji wynika z kwot propozycji z serwera, a nie z wpisanych. Wiersz w sekcji ma własne pole kwoty i zostaje w sekcji także po wpisaniu kwoty. Pole kwoty, ukryte pola i podgląd „do wydania” korzystają z tej samej mapy `amountInputs` co lista.
- **Odchylenie na żywo tylko w trybie edycji.** Gdy `isEditable`, Vue liczy odchylenie i ponad plan z kopii TS, a jako planu używa `parsedAmount(row)` (dla wierszy spoza planu 0). Gdy `parsedAmount(row) === null` (pole puste albo kwota niepoprawna), wiersz dostaje `{ deviation: null, overPlan: false }`, czyli „—” bez koloru, a nie odchylenie liczone od 0. Podgląd „do wydania” zostaje przy `?? 0` (`MonthPlan.vue:149`). Poza edycją używa pól z serwera.

## Phase 1: Odczyt pozycji na serwerze

### Overview

Nowa para `MonthItemDeviation` (PHP i TS z testami), pięć nowych pól wiersza, typ `MonthPlanItemRow` w Vue i asercje `assertInertia`. Strona wygląda i działa bez zmian.

### Changes Required:

#### 1. Reguła odchylenia (PHP)

**File**: `app/Support/MonthItemDeviation.php` (nowy, `php artisan make:class Support/MonthItemDeviation --no-interaction`)

**Intent**: Jedno miejsce na predykaty odchylenia i ponad plan, z którego skorzystają strona, a później S-07 i S-09.

**Contract**: dwie metody statyczne według „Dokładne predykaty” powyżej:
`deviationMinorUnits(CategoryType $type, ?MonthItemKind $kind, int $plan, int $actual, bool $hasPositiveAmount): ?int` oraz `isOverPlan(CategoryType $type, int $plan, int $actual): bool`. PHPDoc odsyła do `CONTEXT.md` (To Spend, „item over plan”) i do kopii TS.

#### 2. Reguła odchylenia (TS)

**File**: `resources/js/lib/month-item-deviation.ts` (nowy)

**Intent**: Kopia dla podglądu edytowalnej propozycji. Komentarz w nagłówku jak w `to-spend-calculator.ts:10-13` mówi, że to kopia i że obie zmienia się razem.

**Contract**: `monthItemDeviationMinorUnits(item: { type: 'income' | 'expense'; kind: 'bill' | 'running' | null; plan: number; actual: number; hasPositiveAmount: boolean }): number | null` i `isMonthItemOverPlan(item: { type; plan; actual }): boolean`.

#### 3. Testy pary

**Files**: `tests/Unit/MonthItemDeviationTest.php`, `resources/js/lib/month-item-deviation.test.ts` (nowe)

**Intent**: Te same przypadki w obu plikach: każdy wiersz z tabeli „Przypadki graniczne” (bez kolumny „bezczynna”), plus dochód nigdy nie jest ponad plan.

**Contract**: Pest, wzór `tests/Unit/ToSpendCalculatorTest.php`. `vite-plus/test` z importami względnymi, wzór `resources/js/lib/to-spend-calculator.test.ts`.

#### 4. Operacje kategorii w miesiącu

**File**: `app/Support/MonthCategoryActuals.php`

**Intent**: Pytanie „czy kategoria ma jakąkolwiek operację w miesiącu” dla `is_idle_one_off`, bez przeszukiwania `categoryIds()` w pętli.

**Contract**: `hasOperations(int $categoryId): bool` (klucz istnieje w `$byCategory`). Zapytanie bez zmian.

#### 5. Pola wiersza

**File**: `app/Http/Controllers/MonthPlanController.php`

**Intent**: `itemRow` dokłada pięć pól według „Dokładne predykaty”. Kolejność i zbiór wierszy, `in_plan`, `to_spend_minor_units` i podział na `income_items` / `expense_items` się nie zmieniają.

**Contract**: nowe klucze w zwracanej tablicy i w PHPDoc shape: `deviation_minor_units: int|null`, `is_over_plan: bool`, `is_settled: bool`, `available_limit_minor_units: int|null`, `is_idle_one_off: bool`.

#### 6. Typ propsów strony

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Typ `MonthPlanItemRow` (`:26-40`) dostaje te same pięć pól w tym samym commicie co PHP (`AGENTS.md`, Inertia props contract). Template bez zmian. W fazie 2 typ przechodzi do `resources/js/types/month-plan.ts` jako `MonthPlanItem`.

**Contract**: `deviation_minor_units: number | null; is_over_plan: boolean; is_settled: boolean; available_limit_minor_units: number | null; is_idle_one_off: boolean;`

#### 7. Testy strony

**File**: `tests/Feature/MonthPlanPageTest.php`

**Intent**: `assertInertia` nowych pól na fixturze czerwcowej i przypadki jednorazowych.

**Contract**: nowe metody testowe:
- `test_rows_expose_deviation_over_plan_and_settled_flags`. Kanoniczna propozycja czerwca, wartości z tabeli przypadków: Pensja `null`/nierozliczona, Czynsz −5 000 i rozliczony, Jedzenie −90 000, Paliwo +12 000 ponad plan, Prezent +30 000 ponad plan i niebezczynny.
- `test_one_off_rows_expose_the_annual_available_limit`. `execution_minor_units` ustawione jawnie na Prezencie, limit = plan roczny − wykonanie, także wartość ujemna. Kategoria bieżąca ma `null`.
- `test_one_off_without_plan_and_operations_is_idle`. Dodatkowa jednorazowa „Wakacje” bez operacji ma `is_idle_one_off: true` i `in_plan: true`. Jednorazowa z samym zwrotem nie jest bezczynna.
- `test_one_off_with_an_approved_plan_amount_is_not_idle`. Zatwierdzony czerwiec z kwotą > 0 na jednorazowej bez operacji.

#### 8. Reguła repozytorium

**File**: `AGENTS.md`

**Intent**: Sekcja „Month logic lives twice” wymienia trzecią parę, tak aby kolejny agent zmieniał obie kopie razem.

**Contract**: akapit dla `app/Support/MonthItemDeviation.php` i `resources/js/lib/month-item-deviation.ts` z testami i uzasadnieniem (podgląd edytowalnej propozycji, której nie da się zapisać przed zatwierdzeniem), w stylu akapitu o `ToSpendCalculator`.

### Success Criteria:

#### Automated Verification:

- `tests/Unit/MonthItemDeviationTest.php` i `tests/Feature/MonthPlanPageTest.php` przechodzą na PostgreSQL przez obraz Sail (`docker run … sail-8.5/app artisan test --compact tests/Unit/MonthItemDeviationTest.php tests/Feature/MonthPlanPageTest.php` z `AGENTS.md`).
- `npm test` przechodzi, w tym `resources/js/lib/month-item-deviation.test.ts`.
- `vendor/bin/pint --dirty --format agent`, `npm run check` i `npm run types:check` (po `npm run build`) przechodzą.

**Implementation Note**: Faza 1 nie ma ręcznej weryfikacji. Template się nie zmienia, a brak regresji pokrywają `types:check` i istniejące testy zatwierdzenia w `MonthPlanPageTest`. Podgląd z worktree stawiamy raz, w fazie 2.

---

## Phase 2: Widok pozycji i zwinięta sekcja

### Overview

Komponent wiersza, kolumna odchylenia, plakietki rozliczenia, Pozostały limit przy jednorazowych, zwinięta sekcja jednorazowych, odchylenie na żywo w edytowalnej propozycji, tłumaczenia.

### Changes Required:

#### 1. Komponent wiersza

**File**: `resources/js/components/MonthPlanItemRow.vue` (nowy)

**Intent**: Markup wiersza z `MonthPlan.vue:368-462` (chip, plakietki, przełącznik rodzaju, pole albo kwota planu, kwota faktyczna) przeniesiony 1:1, żeby lista i zwinięta sekcja go współdzieliły. Dochodzi do niego odchylenie, plakietka rozliczenia i limit.

**Contract**:
- Propsy: `row` (typ `MonthPlanItem`), `editable: boolean`, `deviation: number | null`, `overPlan: boolean`. Zmiana rodzaju wychodzi zdarzeniem `change-kind`.
- Pole kwoty: `v-model` na kwotę wejściową (`defineModel<string>`).
- Typ wiersza z `MonthPlan.vue` przenosimy do nowego `resources/js/types/month-plan.ts` pod nazwą `MonthPlanItem` (razem z `MonthItemKind`). Strona i komponent importują go stamtąd. Zmiana nazwy jest potrzebna, bo strona importuje też komponent `MonthPlanItemRow`, a ta sama nazwa typu i komponentu daje w TS błąd „Duplicate identifier”.

#### 2. Kolumna odchylenia

**File**: `resources/js/components/MonthPlanItemRow.vue`, nagłówek w `resources/js/pages/MonthPlan.vue`

**Intent**: Od `lg` czwarta kolumna „Odchylenie” (`lg:grid-cols-[1fr_9rem_9rem_9rem]`). Poniżej `lg` siatka zostaje trzykolumnowa jak dziś (`grid-cols-[1fr_7rem_7rem] sm:grid-cols-[1fr_9rem_9rem]`), a odchylenie stoi drugą linią pod kwotą faktyczną (`text-xs`), bez osobnego nagłówka. Powód progu `lg`: trzy kolumny 9rem z odstępami zajmują 468 px. Przy 640 px bez sidebaru zostaje na kategorię ~92 px, a przy 768 px z rozwiniętym sidebarem inset 16rem dostępne jest tylko ~424 px. `AppContent` ma `overflow-x-clip` (`AppSidebarLayout.vue:21`), więc nadmiar zostałby ucięty bez przewijania.

**Contract**: `null` daje „—” w `text-muted-foreground`. Liczba dodatnia idzie przez nowy `formatSignedMoney`. Kolor: `overPlan` → `text-destructive`, dochód z odchyleniem > 0 → `text-success`, poza tym bez klasy koloru. Zawsze `tabular-nums`, do prawej.

#### 3. Format kwoty ze znakiem

**Files**: `resources/js/lib/format.ts`, `resources/js/lib/format.test.ts` (nowy)

**Intent**: Odchylenie dodatnie pokazuje „+”. `formatMoney` daje tylko minus.

**Contract**: `formatSignedMoney(minorUnits: number): string`: dodatnie `+1 234,56 zł`, ujemne jak `formatMoney`, zero `0,00 zł`. Test trzech przypadków.

#### 4. Plakietka rozliczenia i limit

**File**: `resources/js/components/MonthPlanItemRow.vue`

**Intent**: W rzędzie plakietek (dziś `:379-405`): gdy `row.is_settled`, rachunek dostaje `Badge variant="secondary"` „Zapłacone”, a dochód `Badge variant="outline"` „Wpłynęło” z klasami tokenu `success` (design-system: `success` = dochód zaksięgowany, nie „wszystko OK”). Ikona `Check` z `@lucide/vue`. Pod plakietkami jednorazowej: podpis `text-xs text-muted-foreground` „Pozostały limit roczny: <kwota>”, z kwotą w `text-destructive`, gdy < 0.

**Contract**: warunek wyświetlenia limitu to `row.available_limit_minor_units !== null`. Kwota przez `formatMoney`, `tabular-nums`.

#### 5. Podział wydatków i zwinięta sekcja

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Sekcja wydatków rysuje na liście wiersze z `!is_idle_one_off`. Pod nimi, w tym samym `ListSurface`, jest `Collapsible` z wierszami `is_idle_one_off`, domyślnie zamknięty, z licznikiem. Wzorzec: `BudgetCategoryList.vue:451-475`. Gdy takich wierszy nie ma, sekcji nie ma. `allRows`, `planRows` i ukryte pola (`:324-340`) zostają bez zmian, więc zatwierdzenie dalej wysyła kwoty wierszy ze zwiniętej sekcji.

**Contract**: komunikat pustej listy (`empty_list`) dotyczy tylko wierszy na liście. Gdy wszystkie wydatki są bezczynnymi jednorazowymi, lista pokazuje komunikat, a sekcja zwinięta pod nim swoje wiersze. Stan otwarcia to lokalny `ref(false)`, bez zapisu.

#### 6. Odchylenie na żywo

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Strona przekazuje do wiersza `deviation` i `overPlan`. W edytowalnej propozycji liczy je z `monthItemDeviationMinorUnits` / `isMonthItemOverPlan` na kwocie `parsedAmount(row)` (dla wierszy spoza planu 0, jak dziś). Gdy `parsedAmount(row) === null`, zwraca `{ deviation: null, overPlan: false }`. Poza edycją przekazuje `row.deviation_minor_units` i `row.is_over_plan`.

**Contract**: jedna funkcja na stronie, np. `rowReading(row): { deviation: number | null; overPlan: boolean }`. Bez nowych zapytań do serwera.

#### 7. Tłumaczenia

**File**: `lang/pl/ui.php` (`month_plan.*`)

**Intent**: Teksty nowych elementów.

**Contract**: klucze i proponowane brzmienie:
- `deviation` „Odchylenie”,
- `settled_bill` „Zapłacone”,
- `settled_income` „Wpłynęło”,
- `available_limit` „Pozostały limit roczny”,
- `idle_one_offs` „Jednorazowe bez planu i wydatków”.

Typ `UiTranslations` się nie zmienia.

### Success Criteria:

#### Automated Verification:

- `npm test` przechodzi, w tym `resources/js/lib/format.test.ts`.
- `npm run build`, `npm run check` i `npm run types:check` przechodzą.
- `tests/Feature/MonthPlanPageTest.php` przechodzi na PostgreSQL przez obraz Sail.
- Pełna bramka CI przechodzi na obrazie Sail: `docker run … --entrypoint composer sail-8.5/app ci:check`.

#### Manual Verification:

- W bieżącej propozycji (konto `test@example.com` z `DemoHouseholdSeeder`) odchylenia, kolory, „—” i plakietki zgadzają się z tabelą przypadków. Jednorazowe z wydatkiem są na liście z Pozostałym limitem rocznym.
- Zwinięta sekcja jest domyślnie zamknięta, ma licznik i po rozwinięciu pokazuje bezczynne jednorazowe z limitem. Gdy jej nie ma, nie zostaje po niej pusty nagłówek.
- W edytowalnej propozycji zmiana planu pozycji bieżącej poniżej kwoty faktycznej od razu daje dodatnie odchylenie w kolorze destructive. Kwota wpisana w zwiniętej sekcji zmienia „do wydania”, wiersz zostaje w sekcji, a po zatwierdzeniu ta kwota jest w planie (wiersz po przeładowaniu jest na liście).
- Zatwierdzony i miniony zatwierdzony miesiąc pokazują to samo bez pól edycji. Miniony miesiąc bez planu wygląda jak przed zmianą.
- Przy szerokości 360 px nie ma poziomego przewijania, a odchylenie stoi pod kwotą faktyczną. Przy 768 px i 1024 px z rozwiniętym sidebarem nic nie jest ucięte (przełącznik rodzaju i plakietki mieszczą się w kolumnie kategorii), a od 1024 px odchylenie ma własną kolumnę. W trybie ciemnym kolory `destructive` i `success` są czytelne.

**Implementation Note**: Przed prośbą o ręczne sprawdzenie zbuduj i uruchom podgląd z worktree według `AGENTS.md` („Preview from a worktree”) i podaj adres oraz login.

---

## Testing Strategy

### Unit Tests:

- `MonthItemDeviation` (PHP) i `month-item-deviation.ts`: wszystkie wiersze tabeli przypadków. Przypadki graniczne: rachunek z samym zwrotem (`null`), zakup i zwrot netto 0 (0, nie ponad plan), plan 0 z wydatkiem (ponad plan), dochód ponad plan (nie `isOverPlan`).
- `formatSignedMoney`: dodatnie, ujemne, zero.

### Integration Tests:

- `MonthPlanPageTest`: nowe pola na fixturze czerwcowej, limit ujemny, bezczynna jednorazowa vs jednorazowa z samym zwrotem, zatwierdzona kwota > 0 bez operacji. Istniejące testy zatwierdzenia potwierdzają, że kontrakt `ApproveMonthPlan` się nie zmienił.

### Manual Testing Steps:

1. Otwórz bieżący miesiąc w propozycji. Sprawdź „—” przy niezapłaconym rachunku i dochodzie bez wpływu.
2. Dodaj wydatek na rachunek i sprawdź plakietkę „Zapłacone” i odchylenie.
3. Wpisz plan pozycji bieżącej niższy od kwoty faktycznej i sprawdź kolor destructive bez przeładowania.
4. Rozwiń zwiniętą sekcję, wpisz kwotę jednorazowej, sprawdź „do wydania” i zatwierdź. Po przeładowaniu wiersz jest na liście.
5. Przejdź do minionego zatwierdzonego miesiąca i do miesiąca bez planu.
6. Zmień szerokość na 360 px, potem na 768 px i 1024 px z rozwiniętym sidebarem, i przełącz tryb ciemny.

## Performance Considerations

Bez nowych zapytań: limit korzysta z kolumn już pobranych kategorii, a bezczynność z istniejącego agregatu miesiąca.

## Migration Notes

Brak zmian schematu i danych.

## References

- Research: `context/changes/month-items-plan-vs-actual/research.md`
- PRD: `context/foundation/prd.md:44,114,118`
- Roadmap S-04: `context/foundation/roadmap.md:134-144`
- Definicje: `CONTEXT.md` (Available Limit, Month Item Kind, To Spend — „item over plan”), ADR-0033 `.docs/adr/0033-month-plan-beside-annual-budget.md`
- Kolory: `.docs/design-system.md` („Kolor znaczy coś tylko w trzech miejscach”), `.ai/rules/js.md`
- Poprzednia zmiana: `context/archive/2026-11-02-month-plan-and-to-spend/plan.md`
- Wzorce: `app/Support/ToSpendCalculator.php`, `resources/js/lib/to-spend-calculator.ts`, `resources/js/components/BudgetCategoryList.vue:451-475`
- Źródło „znacznika realizacji”: `storage/app/private/budget_szablon.xlsx` (nieśledzony, główny checkout), zakładki miesięcy, kolumna E

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Odczyt pozycji na serwerze

#### Automated

- [x] 1.1 `tests/Unit/MonthItemDeviationTest.php` i `tests/Feature/MonthPlanPageTest.php` przechodzą na PostgreSQL przez obraz Sail — 8eb5b24
- [x] 1.2 `npm test` przechodzi, w tym `resources/js/lib/month-item-deviation.test.ts` — 8eb5b24
- [x] 1.3 `vendor/bin/pint --dirty --format agent`, `npm run check` i `npm run types:check` (po `npm run build`) przechodzą — 8eb5b24

### Phase 2: Widok pozycji i zwinięta sekcja

#### Automated

- [x] 2.1 `npm test` przechodzi, w tym `resources/js/lib/format.test.ts`
- [x] 2.2 `npm run build`, `npm run check` i `npm run types:check` przechodzą
- [x] 2.3 `tests/Feature/MonthPlanPageTest.php` przechodzi na PostgreSQL przez obraz Sail
- [x] 2.4 Pełna bramka CI przechodzi na obrazie Sail (`composer ci:check`)

#### Manual

- [x] 2.5 Odchylenia, kolory, „—”, plakietki i Pozostały limit roczny zgadzają się z tabelą przypadków
- [x] 2.6 Zwinięta sekcja: domyślnie zamknięta, licznik, bezczynne jednorazowe z limitem, brak pustego nagłówka
- [x] 2.7 Edytowalna propozycja: odchylenie na żywo, kwota w zwiniętej sekcji zmienia „do wydania” i trafia do zatwierdzonego planu
- [x] 2.8 Zatwierdzony i miniony miesiąc bez pól edycji; miniony miesiąc bez planu bez zmian
- [x] 2.9 360 px bez poziomego przewijania; 768 i 1024 px z sidebarem bez ucięć; tryb ciemny czytelny
