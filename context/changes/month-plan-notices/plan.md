# S-07 Month Plan Notices — Implementation Plan

## Overview

Dashboard dostaje cztery powiadomienia liczone z planu bieżącego miesiąca (ADR-0033:9, FR-012): „do wydania” < 0 i pozycja ponad plan (`exceeded`) oraz tempo ≥ 10 pp przed upływem dni i niezatwierdzony plan od 3. dnia (`warning`, pierwsze w aplikacji). Jednocześnie znika `paced_month_remaining`, które według ADR-0033:7 nie ma już zastosowania, a dokumenty Dashboardu przestają opisywać Month Remaining jako powiadomienie.

## Current State Analysis

- `DashboardController::show` buduje `MonthToSpend::forMonth($budget, $today->month, $today)` dla budżetu w realizacji, ale przekazuje dalej tylko `summary()` (`app/Http/Controllers/DashboardController.php:99-107`). `DashboardNotices::forDashboard` jest wołane osobno i tego obiektu nie dostaje (`:111-116`).
- `App\Support\DashboardNotices` (`app/Support/DashboardNotices.php:46-160`) emituje cztery rodzaje, wszystkie `exceeded`, w kolejności dopisywania: `negative_account`, `negative_term_deposit`, `paced_month_remaining` (`:83-104`, przez `DashboardPacedCategoryMonthAlerts`), `one_off_over_limit` (`:106-156`). Żaden rodzaj nie emituje `warning` (`app/Enums/DashboardNoticeSeverity.php` docblock).
- Wejścia nowych powiadomień są już policzone na tym samym `MonthToSpend`: `toSpendMinorUnits()` (`app/Support/MonthToSpend.php:54-75`), `rowAmounts()` + `MonthItemDeviation::isOverPlan()` (`MonthToSpend.php:83-90`, `app/Support/MonthItemDeviation.php:46-49`), `MonthPace::forMonth(...)['is_ahead']` (`app/Support/MonthPace.php:19-56`), `$toSpend->reading->state`. Progi są w `CONTEXT.md:64,80,92`; próg tempa liczy się na liczbach całkowitych w `MonthPaceCalculator::isAheadOfMonth` (`app/Support/MonthPaceCalculator.php:55-62`).
- `DashboardPacedCategoryMonthAlerts` ma jedynego konsumenta, `DashboardNotices`. `BudgetMonthReadings` i `MonthRemainingCalculator` mają innych (`OperationsController`, `HandEntryMonthRemainingInputs`).
- `resources/js/components/DashboardNotices.vue` wybiera etykietę (`:105-122`), link (`:147-187`), klucz (`:189-190`) i blok tożsamości (`:249-276`) po `kind`. Nieznany `kind` dostałby link do Operacji za cały rok i `CategoryChip` bez ikony. Styl `warning` (karta, ikona, pigułka w nagłówku) już działa (`:85-103, 200-245`).
- Strona Plan miesiąca czyta rok z powłoki (`app/Http/Controllers/MonthPlanController.php:39`). Kafelek „do wydania” linkuje do niej przez POST `budget.select-year` z `destination=month-plan` (`resources/js/pages/Dashboard.vue:270-289`, `app/Http/Controllers/BudgetController.php:179-181`).

## Desired End State

Dla budżetu w realizacji `dashboardNotices` zawiera, obok powiadomień kont, lokat i `one_off_over_limit`, nowe rodzaje: `month_to_spend_negative`, `month_item_over_plan`, `month_plan_not_approved` i `month_pace_ahead`, w kolejności „najpierw waga, potem rodzaj”. Rodzaju `paced_month_remaining` nie ma w kodzie, testach, copy ani w aktualnych dokumentach. Na Dashboardzie każda nowa karta ma etykietę, tożsamość (kategoria albo ikona kalendarza z nazwą miesiąca), właściwą wartość i link do przyczyny. Weryfikacja: `composer ci:check` przechodzi, a ręczny przegląd Dashboardu 3 listopada 2026 bez zatwierdzonego planu listopada pokazuje pomarańczową kartę „Plan niezatwierdzony”, która otwiera Plan miesiąca bieżącego roku.

### Key Discoveries:

- Wszystkie cztery predykaty mają już jedno miejsce w PHP, więc S-07 ich nie powtarza i nie potrzebuje nowej kopii w TS (`MonthItemDeviation.php:46-49`, `MonthPaceCalculator.php:55-62`).
- Dla budżetu w realizacji bieżący miesiąc ma zawsze stan `proposal` albo `approved`. `none` pojawia się tylko jako strażnik typu (`app/Support/MonthPlanProposal.php:93-123`).
- W propozycji kategorie One-off startują od 0 (`MonthPlanProposal.php:150-152`), więc każdy ich wydatek w miesiącu jest „ponad planem”. To zamierzone (`CONTEXT.md:80`).
- `MonthPlan::factory()` tworzy plan zatwierdzony, a brak wiersza oznacza propozycję (`tests/Feature/DashboardTest.php:769`). Wiele istniejących testów działa 23 września z budżetem w realizacji bez wiersza `MonthPlan`, czyli po zmianie dostanie `month_plan_not_approved`.
- Procent upływu dni w UI liczy `Math.round(100 * elapsedDay / daysInMonth)` (`resources/js/components/MonthPaceMeter.vue:14-16`).

## What We're NOT Doing

- Nie tłumimy żadnego powiadomienia przez inne: One-off ponad plan miesiąca i ponad roczny limit dają dwie karty (to dwa fakty z dwóch okresów).
- Nie dajemy powiadomienia „ponad plan” dla kategorii zarchiwizowanych. Ich kwoty nadal wchodzą do „do wydania” i do tempa (`dashboard-hierarchy.md:49`, „Only non-archived … Categories produce notices”).
- Nie dodajemy podnagłówków wag w siatce. Zostają pigułki w nagłówku, a ADR-0032 zostaje do tego dostosowany.
- Nie zmieniamy kafelka „do wydania” (czerwona kwota, plakietka „Niezatwierdzony”). Powiadomienia pojawiają się mimo niego, zgodnie z FR-012 i ADR-0033.
- Nie dodajemy powiadomień o dobieraniu ponad Spendable Liquidity ani z porównania z rokiem (`set-aside-and-draw/plan.md:59`, `month-plans-vs-year/plan.md:61`).
- Nie zmieniamy `CONTEXT.md`: definicje i progi już tam są.
- Nie ruszamy `BudgetMonthReadings`, `MonthRemainingCalculator`, nieużywanej copy `dashboard.month_remaining` ani propsów Month Remaining na stronie Budżet (S-06/S-08 i dalsze sprzątanie).
- Nie przepisujemy historii ADR-0033 (zdanie „Until S-06, S-07 and S-08 update them” zostaje jako zapis decyzji).

## Implementation Approach

Kontroler zachowuje obiekt `MonthToSpend` i przekazuje go do `DashboardNotices`. Klasa buduje powiadomienia w kolejności docelowej: najpierw wszystkie `exceeded`, potem `warning`. Nie potrzebuje globalnego sortowania, bo kolejność dopisywania jest kontraktem. Powiadomienia bez kategorii niosą `month` zamiast `id`/`name`. Vue dostaje nowe gałęzie unii typów i rozpoznaje karty miesiąca po braku kategorii. Usunięcie Paced odbywa się w tych samych fazach co jego konsumenci, więc żaden commit nie zostawia martwej gałęzi.

## Critical Implementation Details

- **Kolejność dopisywania jest kontraktem:** `negative_account` → `negative_term_deposit` → `month_to_spend_negative` → `month_item_over_plan` → `one_off_over_limit` → `month_plan_not_approved` → `month_pace_ahead`. Wewnątrz `month_item_over_plan` najpierw najbardziej ujemna kwota (plan − faktyczna), przy remisie kolejność budżetu (`$toSpend->categories` jest już `ordered()`). To ten sam wzorzec co `one_off_over_limit` (`DashboardNotices.php:132-143`).
- **Istniejące testy z dokładną listą powiadomień** (`DashboardTest.php:1150` i każdy inny `where('dashboardNotices', [...])`/`has('dashboardNotices', N)` z budżetem w realizacji 23 września) muszą jawnie wybrać stan planu: albo utworzyć zatwierdzony `MonthPlan` września, albo uwzględnić `month_plan_not_approved` w oczekiwanej liście. Nie wolno rozluźniać asercji do „zawiera”.
- **Link do Planu miesiąca to POST**, nie GET: `<Link>` z Wayfinder `BudgetController.selectYear()`, `data: { year: budgetYear, destination: 'month-plan' }` i `as="button"`, inaczej strona otworzy rok z powłoki.

## Phase 1: Backend — powiadomienia planu miesiąca i usunięcie Paced

### Overview

`DashboardNotices` emituje cztery nowe rodzaje z `MonthToSpend` bieżącego miesiąca w docelowej kolejności, a `paced_month_remaining` znika razem ze swoją klasą i testami.

### Changes Required:

#### 1. Kontroler przekazuje odczyt miesiąca

**File**: `app/Http/Controllers/DashboardController.php`

**Intent**: Zachować obiekt `MonthToSpend` zbudowany dla budżetu w realizacji i przekazać go do `DashboardNotices`, bez drugiego wywołania `MonthToSpend::forMonth` (każde ładuje plany, kategorie i poprzedni budżet).

**Contract**: Zmienna obiektu (`null` bez budżetu w realizacji) obok dotychczasowego propsu `month_to_spend` (nadal `{state, to_spend_minor_units}` albo `null`). Wywołanie `DashboardNotices::forDashboard($budget, $allAccounts, $allTermDeposits, $monthToSpendReading, $today)`.

#### 2. Cztery nowe rodzaje i kolejność

**File**: `app/Support/DashboardNotices.php`

**Intent**: Usunąć blok `paced_month_remaining` i zbudować nowe powiadomienia z przekazanego `MonthToSpend`, gdy nie jest `null` i jego stan nie jest `none`. Kolejność według Critical Implementation Details. Blok `one_off_over_limit` zostaje bez zmian merytorycznych (wciąż tylko budżet w realizacji).

**Contract**: Sygnatura `forDashboard(?Budget $budget, Collection $accounts, Collection $termDeposits, ?MonthToSpend $monthToSpend, CarbonImmutable $today): array`. PHPDoc kształtu rozszerzony o nowe gałęzie:

- `month_to_spend_negative`: `{kind, severity: 'exceeded', month: int, amount_minor_units: int}`, emitowane gdy `toSpendMinorUnits() < 0`, kwota = „do wydania”.
- `month_item_over_plan`: `{kind, severity: 'exceeded', id, name, icon, color, category_name, amount_minor_units}`, jedno na każdą niezarchiwizowaną kategorię z `$toSpend->categories`, dla której `MonthItemDeviation::isOverPlan($category->type, plan, actual)` z `rowAmounts()`. Kwota = plan − faktyczna (< 0). Pola wyglądu z `CategoryAppearance::fields`.
- `month_plan_not_approved`: `{kind, severity: 'warning', month: int}`, gdy `$toSpend->reading->state === 'proposal'` i `$today->day >= 3`.
- `month_pace_ahead`: `{kind, severity: 'warning', month: int, percent: int, elapsed_day: int, days_in_month: int}`, gdy `MonthPace::forMonth($toSpend, $budget->year, $today->month, $today)['is_ahead']`. Pola z tego samego wyniku.

`month` to `$today->month`.

#### 3. Usunięcie źródła Paced

**File**: `app/Support/DashboardPacedCategoryMonthAlerts.php` (usunięty)

**Intent**: Klasa nie ma już konsumenta. Przed usunięciem potwierdzić grepem, że nikt inny jej nie używa.

**Contract**: Brak klasy; `BudgetMonthReadings` zostaje.

#### 4. Docblock wagi

**File**: `app/Enums/DashboardNoticeSeverity.php`

**Intent**: Usunąć „no notice kind emits it yet” i wskazać, że `warning` emitują `month_plan_not_approved` i `month_pace_ahead`.

**Contract**: Tylko docblock.

#### 5. Testy HTTP

**File**: `tests/Feature/DashboardTest.php`

**Intent**: Usunąć testy Paced (`:850-1115`) i przepisać `:1183` oraz `:1217`, zachowując ich intencję (brak duplikatu, kolejność między rodzajami) dla nowych rodzajów. Dodać testy nowych powiadomień i dostosować istniejące testy z dokładną listą do stanu planu (Critical Implementation Details). Wzorce fixture'ów: propozycja `:711`, zatwierdzony plan `:769`.

**Contract**: Nazwane scenariusze (data 2026-09-23, chyba że podano inaczej; wartości są przykładami, implementujący może je przeliczyć, byle sprawdzały te same granice):

- `test_dashboard_notices_to_spend_below_zero`: zatwierdzony plan, przychód plan 250 000 bez wpływu, wydatek Running plan 200 000 / faktycznie 300 000 → `month_to_spend_negative`, kwota −50 000, `month` 9. „Do wydania” = 0 → brak.
- `test_dashboard_notices_month_item_over_plan`: ta sama kategoria → `month_item_over_plan` z kwotą −100 000 i polami wyglądu; faktyczna = plan → brak; dochód ponad plan → brak; kategoria Bill ponad plan → jest.
- `test_dashboard_notices_one_off_over_plan_at_zero_and_over_limit`: One-off plan miesiąca 0, wydatek 300 000 w miesiącu, roczny limit przekroczony → obie karty, `month_item_over_plan` i `one_off_over_limit`, każda raz.
- `test_dashboard_does_not_notice_archived_category_over_plan`: zarchiwizowana kategoria z wydatkiem ponad plan → brak `month_item_over_plan`, ale „do wydania” nadal ją liczy.
- `test_dashboard_orders_month_item_over_plan_by_most_over_then_budget_order`: zastępuje test kolejności Paced (`:1059`): większe przekroczenie pierwsze, remis według kolejności budżetu.
- `test_dashboard_notices_unapproved_plan_from_the_third_day`: propozycja 2 września → brak; 3 września → `month_plan_not_approved`, `severity` `warning`; zatwierdzony plan 23 września → brak.
- `test_dashboard_notices_pace_ahead_of_month_elapsed`: przykłady progu z `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:169` (8,3 pp → brak, 11,7 pp → `month_pace_ahead` z `percent`, `elapsed_day`, `days_in_month`); suma planu puli 0 → brak.
- `test_dashboard_has_no_month_notices_without_a_budget_in_execution`: szkic i brak budżetu → tylko konta i lokaty (zastępuje przypadki `:990-1055`).
- `test_dashboard_orders_notices_by_severity_then_kind`: zastępuje `:1217` i obejmuje wszystkie siedem rodzajów w kolejności kontraktu. Przy obu wagach wszystkie `exceeded` są przed `warning`.
- Żaden test nie zawiera `paced_month_remaining`.

### Success Criteria:

#### Automated Verification:

- Dashboard feature tests pass: `php artisan test --compact tests/Feature/DashboardTest.php`
- Full PHP suite passes: `php artisan test --compact`
- PHPStan passes: `composer types:check`
- Pint passes: `composer lint:check`
- No PHP/test reference to the Paced notice remains: `grep -rn "paced_month_remaining\|DashboardPacedCategoryMonthAlerts" app tests` returns nothing

**Implementation Note**: Phase 1 changes the payload consumed by `DashboardNotices.vue`; until Phase 2 lands, the new kinds render with the fallback branch. Do not ship Phase 1 alone.

---

## Phase 2: Frontend — karty nowych rodzajów

### Overview

`DashboardNotices.vue` renderuje cztery nowe rodzaje w istniejącym szkielecie karty, z właściwą etykietą, tożsamością, wartością i linkiem, a gałąź Paced znika.

### Changes Required:

#### 1. Typ, etykiety, klucz

**File**: `resources/js/components/DashboardNotices.vue`

**Intent**: Zastąpić gałąź `paced_month_remaining` w unii `DashboardNotice` czterema nowymi, dokładnie według kontraktu payloadu z fazy 1. `noticeKindLabel` zwraca etykietę dla każdego rodzaju (bez `null` dla znanych). `noticeKey` daje `${kind}-${id}` dla rodzajów z `id`, a dla rodzajów miesiąca `${kind}-${month}`.

**Contract**: Unia z siedmioma gałęziami; `noticeKindLabel(notice): string` jest wyczerpujące (TS wyłapuje brakującą gałąź).

#### 2. Linki

**File**: `resources/js/components/DashboardNotices.vue`

**Intent**: `month_item_over_plan` linkuje do Operacji z `category_name`, `category_type=expense` i zakresem bieżącego miesiąca, czyli dotychczasową gałęzią Paced z nową nazwą. `month_to_spend_negative`, `month_plan_not_approved` i `month_pace_ahead` otwierają Plan miesiąca bieżącego roku przez POST `budget.select-year` z `destination=month-plan`, tym samym ruchem co kafelek (`Dashboard.vue:270-289`). `one_off_over_limit` przestaje być gałęzią domyślną i dostaje jawną gałąź.

**Contract**: Dla rodzajów miesiąca `<Link>` dostaje Wayfinder `BudgetController.selectYear()`, `:data="{ year: budgetYear, destination: 'month-plan' }"` i `as="button"`. Karta-przycisk zachowuje wygląd karty-linku (`text-left`, pełna szerokość).

#### 3. Tożsamość i wartość

**File**: `resources/js/components/DashboardNotices.vue`

**Intent**: `month_item_over_plan` używa `CategoryChip`, nazwy kategorii i kwoty jak `one_off_over_limit`. Rodzaje miesiąca pokazują neutralny kafelek z ikoną `CalendarDays` (bez koloru z rodzaju, `.ai/rules/support.md:10`) i nazwę miesiąca w mianowniku (`t.budget.month_name_N`, jak `Dashboard.vue` przy kafelku). Wartość: `month_to_spend_negative` → `formatMoney(amount_minor_units)`; `month_pace_ahead` → copy `notice_month_pace_value` z `percent` i procentem upływu dni; `month_plan_not_approved` → bez wartości. Kolor wartości nadal tylko z wagi.

**Contract**: Procent upływu dni liczony formułą `MonthPaceMeter.vue:14-16`, wyciągniętą do jednej funkcji (np. `monthElapsedPercent(elapsedDay, daysInMonth)` w `resources/js/lib/month-pace-calculator.ts`), której używają oba komponenty.

#### 4. Copy

**File**: `lang/pl/ui.php`

**Intent**: Dodać etykiety nowych rodzajów i wartość tempa pod `dashboard.*`, usunąć `notice_paced_month_remaining`.

**Contract**: `notice_month_to_spend_negative` = „Do wydania poniżej zera”, `notice_month_item_over_plan` = „Ponad plan miesiąca”, `notice_month_plan_not_approved` = „Plan niezatwierdzony”, `notice_month_pace_ahead` = „Tempo przed upływem dni”, `notice_month_pace_value` = „:pace% · upływ dni :elapsed%”.

### Success Criteria:

#### Automated Verification:

- Type check passes: `npm run types:check`
- Lint/format check passes: `npm run check`
- JS unit tests pass: `npm test`
- No frontend reference to the Paced notice remains: `grep -rn "paced_month_remaining" resources lang` returns nothing
- Full CI check passes: `composer ci:check`

#### Manual Verification:

- On 2026-11-03 with an unapproved November plan, Dashboard shows an amber „Plan niezatwierdzony” card with the calendar icon and „Listopad”, and the heading shows the „Ostrzeżenia · n” pill
- Clicking that card while the shell year is a different year opens Plan miesiąca for 2026, November
- An expense over its month plan shows a red „Ponad plan miesiąca” card with the Category chip and a negative amount; clicking it opens Operations filtered to that Category and November
- With exceeded and warning notices together, every red card comes before every amber card; cards look the same height in the grid at 375 px and at desktop width
- Keyboard focus is visible on the button-cards and Enter activates them

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Dokumenty

### Overview

Dokumenty, które ADR-0033 tymczasowo nadpisuje albo które opisują stan sprzed S-07, opisują teraz powiadomienia planu miesiąca.

### Changes Required:

#### 1. Specyfikacja Dashboardu

**File**: `.docs/specs/dashboard-hierarchy.md`

**Intent**: Usunąć Month Remaining jako powiadomienie i opisać rodzaje planu miesiąca. Każda zmiana z dopiskiem „(Superseded by S-07 / ADR-0033)”, tak jak robią to wcześniejsze poprawki w tym pliku. Dopisek nie powtarza identyfikatora `paced_month_remaining` (kryterium 3.1).

**Contract**: `:11`: „Dashboard notice” jest już terminem używanym w `CONTEXT.md`. Historie 8, 10, 12 (`:22,24,26`) opisują nowe rodzaje, kolejność „exceeded, potem warning” i linki; historia 11 (`:25`) mówi o sześciu kartach. `:49` lista rodzajów, kolejność i wyjątek archiwalny; `:50` linki; `:51` płaska siatka bez podnagłówków; `:66-67` testy powiadomień.

#### 2. ADR-0031

**File**: `.docs/adr/0031-dashboard-always-shows-current-calendar-year.md`

**Intent**: Zdanie o „Month Remaining alerts” (`:5`) odnosi się teraz do powiadomień planu miesiąca, z dopiskiem, że zmianę wprowadził S-07 zgodnie z ADR-0033.

**Contract**: Tylko to zdanie.

#### 3. ADR-0032

**File**: `.docs/adr/0032-dashboard-notice-severity.md`

**Intent**: `:3`: `warning` emitują `month_plan_not_approved` i `month_pace_ahead` (progi w `CONTEXT.md`). `:5`: zamiast podnagłówków jest płaska siatka uporządkowana „exceeded first” z pigułkami w nagłówku; widocznych kart jest sześć (zgodnie z `dashboard-hierarchy.md:51`).

**Contract**: Dopiski w stylu „(Amended by S-07: …)”; reszta decyzji bez zmian.

#### 4. Reguła Support

**File**: `.ai/rules/support.md`

**Intent**: Zastąpić „All four current kinds are exceeded” zdaniem o tym, które rodzaje są `warning`, i dodać, że kolejność dopisywania w `DashboardNotices` (najpierw waga, potem rodzaj) jest kontraktem listy.

**Contract**: Sekcja „Every Dashboard notice carries a server-decided severity”.

### Success Criteria:

#### Automated Verification:

- Current docs no longer describe the Paced notice: `grep -rn "paced_month_remaining" .docs/specs .ai CONTEXT.md` returns nothing
- `.ai/rules/support.md` no longer says all kinds are exceeded: `grep -n "All four current kinds are exceeded" .ai/rules/support.md` returns nothing

#### Manual Verification:

- Reading `dashboard-hierarchy.md`, ADR-0031 and ADR-0032 gives one consistent picture: seven kinds, order exceeded → warning, six visible cards, no severity sub-headings, links as in Phase 2

---

## Testing Strategy

### Unit Tests:

- Bez nowych testów jednostkowych PHP: predykaty mają już własne testy (`tests/Unit/MonthItemDeviationTest.php`, `MonthPaceCalculatorTest.php`, `ToSpendCalculatorTest.php`), a spec każe nie testować kolejności osobno (`dashboard-hierarchy.md:68`).
- Wyciągnięta funkcja procentu upływu dni w TS: jeśli obok `month-pace-calculator.ts` jest plik testów, dopisać przypadek zaokrąglenia.

### Integration Tests:

- Scenariusze HTTP z fazy 1, punkt 5: granice „do wydania” 0/−1, przekroczenie o 1 vs remis, plan 0 dla One-off, kategoria zarchiwizowana, dochód, dzień 2/3, tempo 8,3/11,7 pp, suma planu puli 0, szkic i brak budżetu, pełna kolejność siedmiu rodzajów.

### Manual Testing Steps:

1. 3 listopada 2026, listopad niezatwierdzony: karta „Plan niezatwierdzony” i pigułka „Ostrzeżenia”.
2. Przełączyć powłokę na inny rok i kliknąć kartę: otwiera się Plan miesiąca 2026 / listopad.
3. Zatwierdzić plan: karta znika po odświeżeniu Dashboardu.
4. Zaksięgować wydatek ponad plan pozycji: czerwona karta „Ponad plan miesiąca” prowadzi do Operacji z listopadem.

## Performance Considerations

Bez nowych zapytań: `MonthToSpend` jest budowany raz w kontrolerze, a `MonthPace::forMonth` liczy na nim w pamięci (`MonthPace.php:19`). Usunięcie Paced zdejmuje `BudgetMonthReadings::forBudget` z każdego wejścia na Dashboard.

## Migration Notes

Bez migracji danych. Zmienia się tylko payload Inertia `dashboardNotices` (nowe rodzaje, brak `paced_month_remaining`), a jedynym konsumentem jest `DashboardNotices.vue` w tym samym wydaniu.

## References

- Research: `context/changes/month-plan-notices/research.md`
- Decyzje i progi: `.docs/adr/0033-month-plan-beside-annual-budget.md:7,9`, `CONTEXT.md:64,80,92`, `.docs/adr/0032-dashboard-notice-severity.md`
- F-01 przykłady progów: `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:27-31,53,169`
- S-05 próg całkowitoliczbowy: `context/archive/2026-11-03-month-spending-pace/plan.md:48-54`
- Wzorzec sortowania kategorii w powiadomieniach: `app/Support/DashboardNotices.php:132-143`
- Wzorzec linku POST do Planu miesiąca: `resources/js/pages/Dashboard.vue:270-289`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend — powiadomienia planu miesiąca i usunięcie Paced

#### Automated

- [x] 1.1 Dashboard feature tests pass: `php artisan test --compact tests/Feature/DashboardTest.php` — e862652
- [x] 1.2 Full PHP suite passes: `php artisan test --compact` — e862652
- [x] 1.3 PHPStan passes: `composer types:check` — e862652
- [x] 1.4 Pint passes: `composer lint:check` — e862652
- [x] 1.5 No PHP/test reference to the Paced notice remains — e862652

### Phase 2: Frontend — karty nowych rodzajów

#### Automated

- [x] 2.1 Type check passes: `npm run types:check`
- [x] 2.2 Lint/format check passes: `npm run check`
- [x] 2.3 JS unit tests pass: `npm test`
- [x] 2.4 No frontend reference to the Paced notice remains
- [ ] 2.5 Full CI check passes: `composer ci:check`

#### Manual

- [x] 2.6 Unapproved November plan shows an amber „Plan niezatwierdzony” card with the warning pill
- [x] 2.7 Month-plan card opens Plan miesiąca 2026 / November regardless of shell year
- [x] 2.8 Over-plan card shows Category chip, negative amount and links to Operations for the month
- [x] 2.9 Red cards come before amber cards; grid holds at 375 px and desktop
- [x] 2.10 Button-cards have visible focus and activate with Enter

### Phase 3: Dokumenty

#### Automated

- [ ] 3.1 Current docs no longer describe the Paced notice
- [ ] 3.2 `.ai/rules/support.md` no longer says all kinds are exceeded

#### Manual

- [ ] 3.3 Spec, ADR-0031 and ADR-0032 give one consistent picture of the notices
