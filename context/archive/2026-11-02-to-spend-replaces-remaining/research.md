---
date: 2026-11-02T11:56:23+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 49c8cac1152920a7aa631d7e3d2d015ae3c03aa9
branch: worktree-to-spend-replaces-remaining
repository: household-treasurer
topic: "S-06 to-spend-replaces-remaining: jak dziś Dashboard i strona Budżet liczą i pokazują odczyt miesiąca oraz co z S-01 da się użyć, żeby pokazać „do wydania”"
tags: [research, codebase, to-spend, month-remaining, dashboard, budget, month-plan]
status: complete
last_updated: 2026-11-02
last_updated_by: Claude (claude-opus-5-5)
---

# Research: S-06 — „do wydania” zamiast `Pozostało w miesiącu`

**Date**: 2026-11-02T11:56:23+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 49c8cac1152920a7aa631d7e3d2d015ae3c03aa9 (commit otwierający zmianę na gałęzi `worktree-to-spend-replaces-remaining`, wypchniętej na `origin`; kod aplikacji jest taki sam jak w `development` @ `eff7a23`; kotwice są lokalne `plik:linia`)
**Branch**: worktree-to-spend-replaces-remaining
**Repository**: household-treasurer

## Research Question

`/10x-research to-spend-replaces-remaining` uruchomiono bez osobnego pytania, a `change.md` ma tylko komentarz w Notes. Pytanie wyprowadzono z roadmapy (`context/foundation/roadmap.md:50` i sekcja „S-06”: „użytkownik może odczytać «do wydania» na Dashboardzie i stronie Budżet, także w miesiącu bez planu”; PRD: FR-011, FR-013).

Pytanie robocze: **jak Dashboard i strona Budżet dziś wybierają miesiąc, liczą i pokazują `Pozostało w miesiącu` / `Wynik`, co z kodu S-01 (`MonthPlanProposal`, `MonthCategoryActuals`, `ToSpendCalculator`) da się użyć, żeby pokazać tam „do wydania” ze stanem planu, oraz które reguły domenowe i dokumenty ta zmiana musi uwzględnić.** Badanie nie wybiera projektu rozwiązania; to zadanie `/10x-plan`.

## Summary

1. **Żaden z dwóch widoków nie czyta dziś planu miesiąca.** Oba biorą odczyt miesiąca z `BudgetMonthReadings::forBudget` (Month Remaining): Dashboard w `DashboardController.php:103-110`, strona Budżet w `BudgetController.php:73-78`. Grep badacza nie znalazł w `BudgetController` odwołań do `MonthPlan*` ani `ToSpendCalculator`. W `DashboardController` też ich nie ma (sprawdzony fragment `:84-125` i raport badacza).

2. **Dashboard pokazuje kafelek Month Remaining tylko przy trzech warunkach naraz** (`DashboardController.php:87-111`): budżet bieżącego roku UTC istnieje, jest w fazie wykonania i ma co najmniej jedną niezarchiwizowaną kategorię równomierną. Vue pokazuje kafelek, gdy prop nie jest `null` (`Dashboard.vue:119-121`), i przełącza siatkę 3/2 kolumny (`:123-125`, `:250-264`). Etykieta to `':month — pozostało'` (`lang/pl/ui.php:134`). Dashboard zawsze czyta bieżący miesiąc kalendarzowy w UTC (`DashboardController.php:30`, ADR-0031).
   - **Wniosek:** dla budżetu w wykonaniu bieżący miesiąc ma zawsze plan, czyli zatwierdzony albo propozycję (`MonthPlanProposal.php:93-121`; ADR-0033:9 „a Proposal always exists for the current month”). Na Dashboardzie stary odczyt nie ma więc przypadku, w którym musiałby zostać. FR-013 „minione bez planu zachowują stary odczyt” dotyczy tu w praktyce tylko strony Budżet.

3. **Strona Budżet wysyła Month Remaining, ale go nie pokazuje.** `pacedPoolMonthRemainingMinorUnits` jest tylko typowany w `Budget.vue:77`. Pola per kategoria `month_remaining_minor_units` / `month_execution_minor_units` nie mają odczytu w `resources/js`, według grepa badacza. Odczyt miesiąca na stronie to `BudgetMonthStrip` z `BudgetMonthResultSummary`: Dochody / Wydatki / `Wynik` (`BudgetMonthStrip.vue:57`, `lang/pl/ui.php:239-241`).
   - **Wniosek:** „zachowanie starego odczytu” dla minionych miesięcy bez planu znaczy na stronie Budżet dziś „nic nie pokazujemy poza `Wynik`”. To otwarte pytanie roadmapy (S-06, Unknowns): czy „do wydania” jest tam nowym odczytem i co pokazać obok dla minionego miesiąca bez planu.

4. **S-01 ma gotowe klocki, ale składanie liczby jest prywatne w `MonthPlanController::monthPlanPayload`** (`MonthPlanController.php:103-153`). Kolejność: `MonthPlanProposal::forBudget` → `reading($month)` → `MonthCategoryActuals::forMonth` → kategorie z planu i z operacji → elementy kalkulatora → `ToSpendCalculator::toSpendMinorUnits`. Dla stanu `none` wynik to `null` (`:147-149`).
   - Dashboard i Budżet potrzebują z tego tylko `{state, approved_at, to_spend}`. Wiersze pozycji są potrzebne tylko stronie planu. Wydzielenie wspólnego odczytu usuwa potrójne kopiowanie tego bloku.

5. **Stany odczytu planu** (`MonthPlanProposal.php:83-121`):
   - `approved`: zapisany plan, także w minionym miesiącu (`:93-102`).
   - `none`: miniony miesiąc bez zapisanego planu (`:105-106`) albo brak poprzedniego budżetu w łańcuchu (`:89-90`).
   - `proposal`: bieżący lub przyszły miesiąc bez zapisu (`:121`).
   - „Miniony” to `[rok, miesiąc] < [today.rok, today.miesiąc]` (`:124-127`). Ta semantyka jest zgodna z `MonthRemainingCalculator::monthTiming` (`MonthRemainingCalculator.php:126-138`), więc `viewedMonthTiming === 'past'` i stan `none` pokrywają się dla budżetu w wykonaniu.
   - Miesiąc zablokowany przez niezatwierdzony poprzedni nie jest osobnym stanem; to `proposal` z `isBlockedByPreviousMonth` (`:71-74`).

6. **Koszt odczytu jest stały.** `MonthPlanProposalTest` sprawdza najwyżej 5 zapytań dla wszystkich 12 miesięcy (wg badacza: `tests/Feature/MonthPlanProposalTest.php:229-248`). `MonthCategoryActuals::forMonth` to jedno zapytanie agregujące (`MonthCategoryActuals.php:16`). `monthPlanPayload` dokłada jedno zapytanie o kategorie (`MonthPlanController.php:114-117`).

7. **Plan miesiąca istnieje tylko dla budżetu w wykonaniu.** `MonthPlanController` wywołuje odczyt tylko przy `isExecution()` (`MonthPlanController.php:64`). `MonthPlanProposal` sam fazy nie sprawdza, wg badacza i `:40-62`. Budżet w szkicu nie ma więc „do wydania”. Dashboard w szkicu i tak nie ma kafelka (`DashboardController.php:87`). Strona Budżet w szkicu liczy dziś Month Remaining w trybie „równa dwunastka” (wg badacza: `BudgetMonthReadings.php:71-75`), ale go nie pokazuje.

8. **Reguła `AGENTS.md` „Month logic lives twice”** (`AGENTS.md:28-32`) każe liczyć nowe odczyty miesiąca na serwerze i podawać je w propsach. Kopię TS dodaje się tylko dla podglądu reagującego przed zapisem. Dla S-06 oznacza to liczbę z serwera bez nowej kopii TS. „Inertia props contract” (`AGENTS.md:24-26`) wymaga zmiany propsa PHP i typu `defineProps` w jednym commicie oraz asercji `assertInertia`.

9. **ADR-0033:7 wymienia dokumenty, które S-06, S-07 i S-08 mają zaktualizować:**
   - FR-TRX-08 i FR-REP-13 w `.docs/prd.md`,
   - kafelek i powiadomienie Month Remaining w `.docs/specs/dashboard-hierarchy.md`,
   - zdanie o alertach Month Remaining w ADR-0031,
   - ostrzeżenie „over Month Remaining” w `.ai/rules/js.md`.

   Przypisanie do wycinków to mój wniosek z ich zakresów w roadmapie:
   - S-06: FR-REP-13 (`.docs/prd.md:296`) i kafelek (`dashboard-hierarchy.md:29-30,52,65`),
   - S-07: powiadomienie (`dashboard-hierarchy.md:22,49,66`) i ADR-0031:5,
   - S-08: FR-TRX-08 i `.ai/rules/js.md:24`.

## Detailed Findings

### Dashboard: dziś

- **Budżet i miesiąc.** `$today = CarbonImmutable::today('UTC')` (`DashboardController.php:30`). Budżet to budżet roku `$today->year`, a sesyjny `budget_year` jest ignorowany (wg badacza `:31-32`, `findBudgetForYear :162-169`). Pokrywa to test `test_dashboard_ignores_shell_year_and_reads_current_calendar_year_budget` (wg badacza `DashboardTest.php:630`).
- **Bramka kafelka.** Domyślnie `$pacedPoolMonthRemainingMinorUnits = null` i `$currentMonth = null` (wg badacza `:60-61`). Wyliczenie następuje tylko w `if ($budget->isExecution())` (`:87`) i `if ($hasVisiblePacedExpenseCategory)` (`:98-111`). `BudgetMonthReadings::forBudget` dostaje wszystkie kategorie wydatkowe, także zarchiwizowane (`:103-108`). Tylko bramka patrzy na widoczne.
- **Vue** (`resources/js/pages/Dashboard.vue`):
  - Propsy `paced_pool_month_remaining_minor_units: number | null` (`:87`) i `current_month: number | null` (`:88`).
  - `showMonthRemainingTile` (`:119-121`) i `headlineTilesClass` (`:123-125`).
  - Etykieta `monthRemainingLabel` z nazwą miesiąca z `t.budget.month_name_N` (`:127-138`).
  - Kolor: `text-success` przy wartości ≥ 0, w przeciwnym razie `text-destructive` (`:140-149`).
  - Kafelek jest pierwszym `Card` w siatce (`:250-264`), przed Spendable Liquidity i Total funds. Nie jest komponentem współdzielonym.
- **Klucze tłumaczeń** (`lang/pl/ui.php`):
  - `dashboard.paced_pool_month_remaining` = `':month — pozostało'` (`:134`),
  - `dashboard.month_remaining` = `'Pozostało w miesiącu'` (`:141`): grep `resources/js` nie znalazł użycia, wygląda na nieużywany,
  - `dashboard.notice_paced_month_remaining` (`:143`).
- **`current_month` ma drugiego konsumenta.** `Dashboard.vue:300` przekazuje go do `DashboardNotices` jako `:current-month`, który buduje link powiadomienia `paced_month_remaining` do Operacji z zakresem dat miesiąca. Bez `currentMonth` link spada do niefiltrowanych Operacji (wg badacza `DashboardNotices.vue:62,160-175`). Usunięcie albo zmiana warunku `current_month` dotyka więc powiadomień.
- **Powiadomienie `paced_month_remaining`** (`app/Support/DashboardNotices.php:83-104`) ma własną bramkę: wykonanie i ten sam rok. Liczy Month Remaining drugi raz przez `DashboardPacedCategoryMonthAlerts::forBudget` (wg badacza `DashboardPacedCategoryMonthAlerts.php:27-36`). ADR-0033:7 mówi, że to powiadomienie przestaje obowiązywać dla budżetu w wykonaniu. Roadmapa przypisuje powiadomienia planu do S-07 (`roadmap.md:51`), a archiwalny plan S-01 też: „wyłączenie `paced_month_remaining`: to S-07” (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:79`).
- **Testy Dashboardu dotykające kafelka** (`tests/Feature/DashboardTest.php`):
  - `test_authenticated_users_can_visit_the_dashboard` (`:31`): oba propsy `null` bez budżetu.
  - `test_dashboard_draft_current_year_budget_sends_plan_summary_without_categories_or_pool_month_remaining` (`:682`).
  - `test_dashboard_sends_paced_pool_month_remaining_for_execution_with_paced_categories` (`:709`; wg badacza asercje `current_month` 9 i `-10_000` na `:736-737`).
  - `test_dashboard_does_not_send_paced_pool_month_remaining_without_paced_categories` (`:741`).
  - Testy powiadomień `paced_month_remaining` (wg badacza `:815`–`:1226`, 10 testów) należą do S-07.

### Strona Budżet: dziś

- **Rok** z sesji przez `SelectedBudgetYear::resolve` (wg badacza `app/Support/SelectedBudgetYear.php:12-37`).
- **Miesiąc** z `BudgetViewedMonth::resolve($request->query('month'), $budget->year, $today)` (`BudgetController.php:68-72`). Poprawne `?month=1..12` wygrywa. Domyślnie przyszły rok daje 1, miniony 12, a bieżący miesiąc dzisiejszy w UTC (wg badacza `BudgetViewedMonth.php:11-33`). Miesiąc nie trafia do sesji.
- **Odczyty miesiąca są liczone w każdej fazie budżetu.**
  - `BudgetMonthReadings::forBudget` (`BudgetController.php:73-78`) dostaje wszystkie kategorie wydatkowe, także zarchiwizowane (`:59-64`). Zwraca pulę Month Remaining, `current_month`, `viewed_month_timing` i odczyty per kategoria.
  - `BudgetMonthResult::forViewedMonth` (`:87`) daje Dochody / Wydatki / `Wynik`.
  - Bez budżetu wszystkie propsy miesiąca są `null` (wg badacza `:38-57`).
- **UI miesiąca.**
  - `BudgetMonthStrip` jest kartą „Miesiąc” z nazwą miesiąca, odznaką „Dziś” przy `viewedMonthTiming === 'current'` (`BudgetMonthStrip.vue:44-47`), wyborem 12 miesięcy (`BudgetMonthPicker`) i `BudgetMonthResultSummary` (`:57`).
  - Podsumowanie to siatka `sm:grid-cols-3` z „Dochody”, „Wydatki”, „Wynik” (wg badacza `BudgetMonthResultSummary.vue:28-53`).
  - Wg badacza `Budget.vue` renderuje pasek tylko wtedy, gdy wszystkie jego propsy są niepuste (`Budget.vue:277-297`). Puli Month Remaining do niego nie przekazuje.
  - `viewedMonthTiming` służy w Vue tylko odznace „Dziś”, wg grepa badacza.
- **Brak powiązania z „Plan miesiąca”.** Strona Budżet nie linkuje do strony planu i nie pokazuje stanu planu (grep badacza po `Budget.vue` i `components/Budget*.vue`). Strona planu ma trasę `GET month-plan` (`routes/web.php:28`, wg badacza) i przyjmuje ten sam rok z sesji oraz to samo `?month=` (`MonthPlanController.php:28-68`, `BudgetViewedMonth::resolve`). Link z paska miesiąca może więc przenieść oglądany miesiąc wprost.
- **Testy propsów miesiąca strony Budżet:**
  - `tests/Feature/BudgetMonthRemainingTest.php`: 12 testów wg badacza, w tym `test_budget_page_exposes_paced_pool_and_category_month_readings` (`:99`) i `test_draft_budget_exposes_even_twelfth_month_readings` (`:186`),
  - `tests/Feature/BudgetMonthResultTest.php`: 3 testy,
  - `BudgetTest.php` nie asertuje propsów miesiąca (grep badacza).

### Kod S-01 do ponownego użycia

- **`MonthPlanProposal::forBudget(Budget, CarbonImmutable $today)`** (`app/Support/MonthPlanProposal.php:40`).
  - Ładuje `monthPlans.items` i `categories` (`:42`) oraz poprzedni budżet w wykonaniu (wg badacza `:42-49`).
  - Zwraca `reading(int $month): MonthPlanReading{state, approvedAt, amounts}` (`:62`) i `isBlockedByPreviousMonth` (`:71-74`).
  - Wyniki są zapamiętywane w obrębie instancji (`planFor`, `:76`).
- **`MonthCategoryActuals::forMonth(Budget, int $month)`** (`MonthCategoryActuals.php:16`) z metodami `actual`, `hasPositiveAmount`, `categoryIds` (`:41-54`).
- **`ToSpendCalculator::toSpendMinorUnits(array $items): int`** (`ToSpendCalculator.php:15`). Set-aside nie jest parametrem i do S-02 wynosi 0 (`:11`). S-02 (Set-aside) i S-06 mogą iść równolegle (`roadmap.md`, S-06 „Parallel with”), więc oba dotkną sygnatury albo wejścia tego odczytu.
- **Składanie liczby w `MonthPlanController::monthPlanPayload`** (`MonthPlanController.php:103-153`):
  - Kategorie wierszy to suma id z planu i z operacji miesiąca (`:109-117`), więc wchodzą też zarchiwizowane kategorie z operacjami.
  - `kind` pochodzi z modelu `Category` (`:128`), więc zapytanie musi ładować modele, nie same id.
  - `to_spend_minor_units` jest `null` dla `none` (`:147-149`).
  - `can_approve` to `proposal`, nie miniony i nie zablokowany (`:144-146`).
  - `monthTiming` jest prywatną kopią pojęcia „past/current/future” (`:200`). Ta sama semantyka żyje w `MonthRemainingCalculator::monthTiming` (`:126-138`).
- **Miniony miesiąc z zatwierdzonym planem.** `reading` zwraca `approved`, a liczba jest liczona tym samym wzorem z bieżącymi operacjami. Nie ma zamrożonej wartości „końcowej”: późna operacja ją zmienia. Pokrywa to `test_past_approved_month_is_read_only` (`tests/Feature/MonthPlanPageTest.php:130`). Miniony bez planu daje `none` i `null` (`test_past_month_without_a_plan_has_no_number`, `:113`). PRD „Rozliczenie końca miesiąca” mówi, że „późna operacja po prostu zmienia podsumowanie” (`context/foundation/prd.md`, sekcja Rozstrzygnięcia domenowe). „Końcowe «do wydania»” z FR-013 jest więc zgodne z odczytem na żywo.
- **Gotowe tłumaczenia** (`lang/pl/ui.php`, grupa `month_plan`): `to_spend` „Do wydania” (`:281`), `state_proposal` „Niezatwierdzony” (`:283`), `state_approved` „Zatwierdzony” (`:284`). Strona planu mapuje `approved` → `state_approved`, a resztę na `state_proposal`; dla `none` nie pokazuje odznaki (wg badacza `MonthPlan.vue:167-171,257,279`).
- **Testy wzoru:**
  - `tests/Unit/ToSpendCalculatorTest.php`: 5 testów wg badacza, przykład kontrolny 3730,00 = `373_000`,
  - bliźniak `resources/js/lib/to-spend-calculator.test.ts`,
  - `MonthPlanPageTest::test_to_spend_follows_new_operations_on_the_next_read` (`:324`).

### Reguły domenowe, które obowiązują S-06

- **To Spend:** wzór, znak, „Plan means Current Month Plan, or Proposal before approval” (`CONTEXT.md:79-81`).
- **Month Remaining** „is read only for past months without a Month Plan; the current and future months (and past months with an Approved Month Plan) show To Spend instead” (`CONTEXT.md:56`).
- **„The month has a plan” includes a Proposal** (ADR-0033:7; `CONTEXT.md:64`; `prd.md:206`).
- **FR-011:** miesiąc bez planu jest wyraźnie oznaczony, a aplikacja działa dalej (`context/foundation/prd.md:127`).
- **FR-013:** „do wydania” zamiast `Pozostało w miesiącu` w bieżącym i przyszłych miesiącach; minione z zatwierdzonym planem pokazują podsumowanie i końcowe „do wydania”; minione bez planu zachowują stary odczyt (`prd.md:136`). Podsumowanie miesiąca to S-09 (`roadmap.md`, wiersz S-09).
- **`Wynik` zostaje** jako fakt (`prd.md`, Rozstrzygnięcia domenowe; `CONTEXT.md:103-105`). „Do wydania” nie zastępuje `Wynik`.
- **Kolor kwot** wg `.ai/rules/js.md:11`: ujemna wartość to `destructive`, wartość w granicach to `success` lub `primary`.

## Code References

- `app/Http/Controllers/DashboardController.php:30` — dzisiejsza data UTC; budżet bieżącego roku.
- `app/Http/Controllers/DashboardController.php:87-111` — bramka wykonania i widocznej kategorii równomiernej; wyliczenie Month Remaining.
- `resources/js/pages/Dashboard.vue:87-88,119-149,250-264,300` — propsy, widoczność kafelka, siatka, etykieta, kolor, `current_month` do powiadomień.
- `lang/pl/ui.php:134,141,143` — klucze Dashboardu dla Month Remaining.
- `lang/pl/ui.php:239-243,281-284` — klucze paska miesiąca Budżetu i planu miesiąca.
- `app/Http/Controllers/BudgetController.php:59-87` — kategorie, `$today`, oglądany miesiąc, `BudgetMonthReadings`, `BudgetMonthResult`.
- `resources/js/components/BudgetMonthStrip.vue:44-57` — odznaka „Dziś” i podsumowanie `Wynik`.
- `app/Support/DashboardNotices.php:83-104` — powiadomienie `paced_month_remaining` (S-07).
- `app/Http/Controllers/MonthPlanController.php:28-68,103-153,200` — `show`, składanie liczby i stanu, `monthTiming`.
- `app/Support/MonthPlanProposal.php:40-127` — wejście, stany, „miniony”.
- `app/Support/MonthCategoryActuals.php:16-54` — kwoty faktyczne miesiąca.
- `app/Support/ToSpendCalculator.php:11-15` — wzór; Set-aside = 0 do S-02.
- `app/Support/MonthRemainingCalculator.php:126-138` — `monthTiming`.
- `AGENTS.md:24-32` — kontrakt propsów Inertia i pary logiki miesiąca.
- `.docs/adr/0033-month-plan-beside-annual-budget.md:7,9` — zakres zastąpienia, lista dokumentów do aktualizacji, „Proposal always exists for the current month”.
- `.docs/specs/dashboard-hierarchy.md:29-30,52,65` — opis kafelka Month Remaining do zaktualizowania.
- `.docs/prd.md:296` — FR-REP-13 do zaktualizowania.

## Architecture Insights

- **Trzy controllery, jedno pojęcie miesiąca.** `BudgetController` i `MonthPlanController` używają `SelectedBudgetYear` + `BudgetViewedMonth`. `DashboardController` bierze bieżący rok i miesiąc z UTC. Pojęcie past/current/future ma dwie implementacje (`MonthRemainingCalculator::monthTiming`, `MonthPlanController::monthTiming`) o tej samej semantyce.
- **Wzorzec odczytów miesiąca to czysta klasa w `app/Support` wołana z controllera, a wynik idzie w props.** Tak działają `BudgetMonthReadings`, `BudgetMonthResult` i `MonthPlanProposal`. Naturalnym miejscem dla wspólnego odczytu „stan planu + do wydania” jest kolejna klasa w `app/Support`, wydzielona z `monthPlanPayload`. To wniosek, nie decyzja.
- **Kafelek Dashboardu jest inline i jego widoczność zależy od `null`.** Nowy odczyt może przejąć ten sam wzorzec, `null` → brak kafelka i siatka 2 kolumny, z inną bramką: wykonanie zamiast „widoczna kategoria równomierna”.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:75` — „«Do wydania» na Dashboardzie i stronie Budżet oraz zastąpienie `Pozostało w miesiącu`: to S-06. Propsy `paced_pool_month_remaining_minor_units` i `pacedPoolMonthRemainingMinorUnits` zostają bez zmian.” Poparte: propsy są nadal w kodzie (`Dashboard.vue:87`, `Budget.vue:77`).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:79` — wyłączenie `paced_month_remaining` należy do S-07. Poparte: powiadomienie działa (`DashboardNotices.php:83-104`).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:666` — kolejność zatwierdzania: M można zatwierdzić tylko po zatwierdzeniu M−1 albo po jego upływie. Poparte (`MonthPlanProposal.php:71-74`, `MonthPlanController.php:144-146`).
- `context/archive/2026-11-02-month-plan-and-to-spend/research.md:100` — „Dwa światy odczytów współistnieją … decyzja dla planu i S-06”. Nadal otwarte: S-01 dodało nowe propsy tylko na stronie planu.
- `context/archive/2026-11-02-month-plan-and-to-spend/research.md:57` — Month Remaining na stronie Budżet nie jest renderowany. Poparte bieżącym grepem badacza (`Budget.vue:77`).
- `context/foundation/lessons.md` nie istnieje, więc nie ma przyjętych wcześniej reguł do uwzględnienia.

## Related Research

- `context/archive/2026-11-02-month-plan-and-to-spend/research.md` — stan kodu przed S-01: odczyty Month Remaining, snapshot, testy.

## Open Questions

Decyzje produktowe dla `/10x-plan` (właściciel: użytkownik):

1. **Strona Budżet, miesiąc z planem.** Gdzie pokazać „do wydania”: jako czwartą komórkę w `BudgetMonthResultSummary` obok `Wynik` czy jako osobny element paska miesiąca? Czy z oznaczeniem stanu („Niezatwierdzony” / „Zatwierdzony”) i linkiem do „Plan miesiąca” z tym samym `?month=`? To pytanie roadmapy S-06, Unknowns.
2. **Strona Budżet, miniony miesiąc bez planu (`none`).** FR-013 każe zachować stary odczyt, a dziś strona nie pokazuje `Pozostało w miesiącu`. Do wyboru: zostawić sam `Wynik` z oznaczeniem „brak planu” (FR-011) albo zacząć renderować pulę Month Remaining, która już jest w propsach.
3. **Strona Budżet, budżet w szkicu.** Planu miesiąca nie ma (`MonthPlanController.php:64`). Do wyboru: brak odczytu bez komunikatu albo oznaczenie „brak planu”.
4. **Dashboard, bramka kafelka.** „Do wydania” nie zależy od kategorii równomiernych. Czy kafelek ma być widoczny dla każdego budżetu w wykonaniu, także bez kategorii (wtedy liczba 0), czy zachować jakiś warunek?
5. **Dashboard, oznaczenie stanu i link.** Czy kafelek pokazuje „Niezatwierdzony” i prowadzi do „Plan miesiąca”? Powiadomienie „brak zatwierdzonego planu od 3. dnia” to S-07, więc nie trzeba go tu dublować.
6. **Los propsów Month Remaining.** Na Dashboardzie pula Month Remaining nie ma już przypadku użycia (Summary 2), ale `current_month` musi zostać dla linków powiadomień (`Dashboard.vue:300`). Do wyboru: usunąć prop puli i zmienić asercje w `DashboardTest.php:709,741` albo zostawić go do S-07. Na stronie Budżet zależy to od odpowiedzi na pytanie 2.

Fakty do potwierdzenia w planie, bez nowych badań:

- Wspólny odczyt wydzielony z `monthPlanPayload` musi dać tę samą liczbę co strona planu. Do sprawdzenia testem zgodności (przykład 3730,00) na obu widokach.
- Gałąź S-02 (Set-aside) może równolegle zmienić wejście `ToSpendCalculator` (`ToSpendCalculator.php:11`). Kolejność scalania trzeba uzgodnić.
