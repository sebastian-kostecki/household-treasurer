---
date: 2026-11-03T08:49:05+01:00
researcher: Claude (claude-opus-5-5)
git_commit: f72f698fc6264abc0a6a4759ffe959495f3013ab
branch: development
repository: household-treasurer
topic: "S-07 month-plan-notices: z czego zbudować cztery powiadomienia Dashboardu planu miesiąca, co trzeba zmienić w istniejącym systemie powiadomień i których decyzji dokumenty nie rozstrzygają"
tags: [research, codebase, dashboard, dashboard-notices, month-plan, to-spend, month-pace, month-item-deviation, severity]
status: complete
last_updated: 2026-11-03
last_updated_by: Claude (claude-opus-5-5)
---

# Research: S-07 — powiadomienia Dashboardu związane z planem miesiąca

**Date**: 2026-11-03T08:49:05+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: f72f698fc6264abc0a6a4759ffe959495f3013ab (lokalny `development`; kotwice `plik:linia` są lokalne, bez permalinków)
**Branch**: development
**Repository**: household-treasurer

## Research Question

Użytkownik uruchomił `/10x-research month-plan-notices` bez osobnego pytania, a `change.md` ma puste Notes. Pytanie wyprowadzono z roadmapy `context/foundation/roadmap.md:171-181` (S-07: „użytkownik może zobaczyć na Dashboardzie powiadomienia związane z planem miesiąca”; PRD FR-012 `context/foundation/prd.md:132-133`; warunki wstępne F-01, S-04, S-05 — wszystkie `done`; ryzyko: „To pierwsze powiadomienia o wadze `warning`, a powiadomienia o drobiazgach uczą ignorowania strefy uwagi”).

Pytanie robocze: **jakie definicje i progi czterech powiadomień już obowiązują, które istniejące odczyty je liczą, jak działa dziś system powiadomień Dashboardu (backend, props, Vue, testy), co poprzednie zmiany zostawiły S-07 i których decyzji dokumenty nie rozstrzygają.** Badanie nie wybiera rozwiązania; to zadanie `/10x-plan`.

## Summary

1. **Definicje i wagi są gotowe; warunek z ADR-0032 jest spełniony.** ADR-0033 wymienia cztery powiadomienia (`.docs/adr/0033-month-plan-beside-annual-budget.md:9`): „do wydania” < 0 → `exceeded`; pozycja ponad plan → `exceeded`; Month Pace ≥ 10 pp przed Month Elapsed → `warning`; brak zatwierdzonego planu od 3. dnia → `warning`. Te same reguły są w `CONTEXT.md:64` (Month Plan), `:80` (To Spend, pozycja ponad plan) i `:92` (Month Pace). ADR-0032 i `.ai/rules/support.md:10` wymagają pojęcia i progów w `CONTEXT.md` przed kodem `warning` — to już jest.
2. **Wszystkie cztery wejścia liczy istniejący kod z jednego obiektu `MonthToSpend`.** Kontroler Dashboardu już buduje `MonthToSpend::forMonth($budget, $today->month, $today)` dla bieżącego miesiąca budżetu w realizacji (`app/Http/Controllers/DashboardController.php:99-100`), ale przekazuje do strony tylko `summary()`. `DashboardNotices::forDashboard` jest wołane osobno i nie dostaje tego obiektu (`DashboardController.php:111-116`).
   - „do wydania”: `MonthToSpend::toSpendMinorUnits()` (`app/Support/MonthToSpend.php:54-75`).
   - pozycja ponad plan: `MonthItemDeviation::isOverPlan(CategoryType, plan, actual)` = wydatek i `actual > plan` (`app/Support/MonthItemDeviation.php:46-49`) z kwotami z `MonthToSpend::rowAmounts($category)` (`MonthToSpend.php:83-90`).
   - tempo: `MonthPace::forMonth($toSpend, $year, $month, $today)['is_ahead']` (`app/Support/MonthPace.php:19-56`), próg liczony całkowitoliczbowo w `MonthPaceCalculator::isAheadOfMonth` (`app/Support/MonthPaceCalculator.php:55-62`).
   - propozycja: `$toSpend->reading->state === 'proposal'` (`app/Support/MonthPlanProposal.php:83-124`), dzień z tego samego `$today`.
3. **Żadne z czterech powiadomień nie istnieje, a żaden `kind` nie emituje `warning`** (`app/Enums/DashboardNoticeSeverity.php` docblock; `app/Support/DashboardNotices.php:54-156` — cztery rodzaje, wszystkie `exceeded`). Frontend ma już styl i grupowanie `warning` (`resources/js/components/DashboardNotices.vue:85-103, 200-245`), ale etykieta, link, klucz i blok tożsamości są wybierane po `kind` i nie obsłużą nowych rodzajów bez zmian (szczegóły niżej).
4. **S-07 dziedziczy wyłączenie `paced_month_remaining`.** ADR-0033 mówi, że to powiadomienie „no longer applies once the current Budget is in execution” (`0033…:7`), a S-01 i S-06 zostawiły jego wyłączenie S-07 (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:79`; `context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:19,69`). Kod nadal je emituje (`DashboardNotices.php:83-104`), a testy je sprawdzają (`tests/Feature/DashboardTest.php:850-1226`, ok. 10 testów). Bramka tego powiadomienia (budżet w realizacji i bieżący rok, `DashboardNotices.php:83`) pokrywa się z warunkiem, w którym bieżący miesiąc zawsze ma plan, więc według ADR-0033 nie zostaje dla niego żaden przypadek.
5. **S-07 jest też właścicielem aktualizacji dokumentów**, które ADR-0033 tymczasowo nadpisuje: powiadomienie Month Remaining w `.docs/specs/dashboard-hierarchy.md` i zdanie o alercie Month Remaining w ADR-0031 (`0033…:7`; `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:39,76`; `to-spend-replaces-remaining/plan.md:77,247`). Po zmianie nieaktualne stanie się też zdanie „All four current kinds are exceeded” w `.ai/rules/support.md:10`.
6. **Dokumenty nie rozstrzygają kilku kwestii produktowych/UI** (sekcja Open Questions): kolejność nowych powiadomień na liście, linki, kategorie zarchiwizowane, nakładanie się z `one_off_over_limit`, z czerwonym kafelkiem i plakietką „Niezatwierdzony”, oraz czy usunąć `DashboardPacedCategoryMonthAlerts` w całości.

## Detailed Findings

### Definicje i progi (ustalone)

- `CONTEXT.md:64` — Month Plan: „From the 3rd day of the current month inclusive, a Month Plan still in Proposal state raises a `warning` Dashboard notice.”
- `CONTEXT.md:80` — To Spend: „Below zero it raises an `exceeded` Dashboard notice. An item over plan (an expense Category whose actual amount in the month exceeds its plan amount, any size, including plan 0 and a One-off Category, whatever its Month Item Kind) also raises an `exceeded` Dashboard notice.” „Plan” oznacza Current Month Plan albo Proposal przed zatwierdzeniem.
- `CONTEXT.md:92` — Month Pace: pula = pozycje Running, które nie są One-off; brak odczytu przy sumie planu 0; „If Month Pace − Month Elapsed is at least 10 percentage points, a `warning` Dashboard notice is raised.”
- F-01 doprecyzował (`context/archive/2026-11-02-month-plan-domain-decisions/plan.md:27-31,53`): „brak planu” = brak zatwierdzonego planu; One-off ponad plan daje powiadomienie przy każdym przekroczeniu, także przy planie 0 („Wakacje: plan 0, wydatek 3000”); jedna pula i jedno powiadomienie o tempie; progi „≥ 10 pp” i „od 3. dnia” są włącznie. Przykłady liczbowe do testów: `plan.md:169` (To Spend 1700/2500; tempo 8,3 pp / 11,7 pp).
- S-05 ustalił, że próg tempa porównuje liczby całkowite, nie zaokrąglony procent (`context/archive/2026-11-03-month-spending-pace/plan.md:48-54`): `isAhead ⇔ planSum > 0 ∧ 10·actualSum·daysInMonth − 10·elapsedDay·planSum ≥ planSum·daysInMonth` — zaimplementowane w `MonthPaceCalculator.php:55-62` (zweryfikowane). Zarchiwizowane kategorie Running i pozycje z planem 0 zostają w puli (`month-spending-pace/plan.md:39`).
- Rzeczy wykluczone z listy S-07: brak powiadomienia Dashboardu o dobieraniu ponad Spendable Liquidity (`context/archive/2026-11-02-set-aside-and-draw/plan.md:59`; `CONTEXT.md:88` mówi „gives a warning”, nie „Dashboard notice”) i brak powiadomień z porównania z rokiem (`context/archive/2026-11-03-month-plans-vs-year/plan.md:61`).

### Istniejący system powiadomień — backend

- `App\Support\DashboardNotices::forDashboard(?Budget, Collection $accounts, Collection $termDeposits, CarbonImmutable $today): array` (`app/Support/DashboardNotices.php:46-51`) — jedyna metoda publiczna, bezstanowa.
- Kształt pozycji (PHPDoc `:18-44`): każda ma `kind`, `severity` (`DashboardNoticeSeverity->value`), `id`, `name`, `amount_minor_units` (ze znakiem). Rodzaje kategorii dokładają `icon`, `color`, `category_name`; rodzaje kont dokładają dane banku i `mark_url`. Payload nie niesie kluczy tłumaczeń ani URL-i — etykietę i link buduje frontend z `kind`.
- Cztery obecne rodzaje, wszystkie `exceeded`: `negative_account` (`:54-65`), `negative_term_deposit` (`:67-81`), `paced_month_remaining` (`:83-104`, tylko budżet w realizacji i `$today->year === $budget->year`, przez `DashboardPacedCategoryMonthAlerts::forBudget`), `one_off_over_limit` (`:106-156`, roczny Available Limit kategorii One-off, nie plan miesiąca).
- Kolejność wynika z kolejności dopisywania: konta, lokaty, Paced, One-off; w obrębie Paced i One-off najbardziej ujemna kwota pierwsza, potem kolejność budżetu (`:132-143`; `app/Support/DashboardPacedCategoryMonthAlerts.php:60-71`). Nie ma globalnego sortowania po wadze; Vue renderuje kolejność serwera. ADR-0032 wymaga podnagłówków „exceeded first” tylko przy obu wagach (`.docs/adr/0032-dashboard-notice-severity.md:5`), a komponent ich nie renderuje (wg agenta; nie sprawdzono ręcznie poza kodem z `:200-245`).
- `app/Support/BudgetDeficitWarning.php` nie zasila powiadomień — używa go tylko `BudgetController.php:117-118` dla budżetu w szkicu.

### Kontroler i miesiąc

- `$today = CarbonImmutable::today('UTC')` (`DashboardController.php:31`); budżet to budżet bieżącego roku kalendarzowego (`:33`, ADR-0031), nie rok powłoki ani miesiąc oglądany. Powiadomienia dotyczą więc wyłącznie bieżącego miesiąca.
- Tylko dla `$budget->isExecution()`: `MonthToSpend::forMonth(...)->summary()` → prop `month_to_spend = {state, to_spend_minor_units}`, ustawiany gdy `state !== 'none'` (`:88-107`); `current_month = $today->month` (`:131-132`).
- Bez budżetu albo przy szkicu: `month_to_spend` i `current_month` są null, a `DashboardNotices` emituje tylko powiadomienia kont i lokat (`DashboardNotices.php:83`; test `DashboardTest.php:50,69`).
- Dla budżetu w realizacji bieżący miesiąc nie jest `none` — jest `proposal` albo `approved` (`MonthPlanProposal.php:93-123`; `context/archive/2026-11-02-to-spend-replaces-remaining/plan-brief.md:72`). Kontroler i tak strzeże `none` jako warunku typu.
- Koszt: `MonthToSpend::forMonth` woła `MonthPlanProposal::forBudget`, które ładuje `monthPlans.items`, `categories` i wykonuje zapytanie o poprzedni budżet (`MonthPlanProposal.php:40-60`). Drugie wywołanie wewnątrz `DashboardNotices` powtórzyłoby te zapytania; `MonthPace::forMonth` przyjmuje gotowy `MonthToSpend` i nie dodaje zapytań (`MonthPace.php:19`).

### Odczyty planu miesiąca (wejścia powiadomień)

- **„do wydania”**: reguła w `ToSpendCalculator::toSpendMinorUnits` (`app/Support/ToSpendCalculator.php:21-38`), odczyt miesiąca w `MonthToSpend` (`:32-101`). Docblock klasy (`MonthToSpend.php:14-15`) wymaga, by każda powierzchnia pokazująca To Spend czytała ją z tej klasy. Kafelek Dashboardu już maluje wartość ujemną na czerwono (`resources/js/pages/Dashboard.vue:140-150`).
- **pozycja ponad plan**: `MonthItemDeviation::isOverPlan` ignoruje rodzaj pozycji i flagę Paced/One-off, wyklucza dochody (`MonthItemDeviation.php:46-49`, zweryfikowane). S-04 wskazał tę metodę jako jedyne miejsce predykatu dla S-07 i S-09 (`context/archive/2026-11-02-month-items-plan-vs-actual/plan.md:117-121`). Kwota faktyczna to suma operacji z miesiąca z odliczonymi zwrotami (`app/Support/MonthCategoryActuals.php:27-60`). `$toSpend->categories` = kategorie z planu ∪ kategorie z operacjami w miesiącu, także zarchiwizowane (`MonthToSpend.php:32-49`); kategoria spoza planu ma plan 0 (`:83-90`). W propozycji kategorie nie-Paced (One-off) startują od 0 (`MonthPlanProposal.php:150-152`), więc każdy wydatek One-off w miesiącu z propozycją jest ponad planem. Dziś jedynym konsumentem jest strona Plan miesiąca (`MonthPlanController.php:296` → `MonthPlan.vue:374`).
- **tempo**: `MonthPace::forMonth` zwraca null przy `state === 'none'`, inaczej `{as_of, elapsed_day, days_in_month, pool_plan, pool_actual, percent, is_ahead}` (`MonthPace.php:19-56`). Pokazywane dziś tylko na stronie Plan miesiąca (`MonthPlan.vue:300-312, 552-565` przez `MonthPaceMeter.vue`); S-05 celowo zostawił Dashboard S-07 (`month-spending-pace/plan.md:35-36,118,281`).
- **propozycja od 3. dnia**: `MonthPlan` (`app/Models/MonthPlan.php:13-57`) przechowuje tylko plany zatwierdzone; propozycja nie jest zapisywana, a stan wynika z `MonthPlanProposal::computePlan` (`:83-124`). Warunek dnia: `$today->day >= 3`. Nie ma cofnięcia zatwierdzenia (`context/archive/2026-11-02-approved-plan-changes/plan.md:39`), więc po zatwierdzeniu to powiadomienie nie wraca w tym miesiącu.
- **Zegar**: brak wstrzykiwanego zegara; klasy Support biorą `CarbonImmutable $today` parametrem, a testy funkcjonalne używają `$this->travelTo(...)` (np. `DashboardTest.php:35`).

### Istniejący system powiadomień — frontend

- `Dashboard.vue:323-328` renderuje `<DashboardNotices v-if="dashboardNotices.length > 0" :notices :budget-year :current-month>`; typ `DashboardNotice` jest zdefiniowany w komponencie (`resources/js/components/DashboardNotices.vue:14-54`, unia po `kind`), nie w `resources/js/types`.
- Gotowe dla `warning`: `severityTextClass`/`severityCardClass` (`:95-103`), ikony `OctagonX`/`AlertTriangle` (`:236-245`), pigułki wag w nagłówku (`:85-93, 200-220`), token `--color-warning` (`resources/css/app.css:46`, warianty `:118, :161`), copy `notices_warning_group` = „Ostrzeżenia” (`lang/pl/ui.php:147`).
- Wymaga zmian dla nowych rodzajów (zweryfikowane w `:105-190`):
  - `noticeKindLabel` (`:105-122`) zwraca null dla nieznanego `kind`, więc etykieta znika.
  - `noticeHref` (`:147-187`): nieznany `kind` spada do ostatniej gałęzi — link do Operacji z `category_name` i zakresem roku — co jest złe dla „do wydania”, tempa i propozycji.
  - `noticeKey` = `${kind}-${id}` (`:189-190`): powiadomienia bez naturalnego `id` (do wydania, tempo, propozycja) potrzebują stałego `id` albo innego klucza.
  - blok tożsamości (wg agenta `:249-276`) spada do `CategoryChip`, który potrzebuje `icon`/`color`; powiadomienia niezwiązane z kategorią wymagają nowej gałęzi.
- `VISIBLE_NOTICE_LIMIT = 6` (`:57`) zgodnie z późniejszym punktem specyfikacji (`.docs/specs/dashboard-hierarchy.md:51`, „Six visible cards … Supersedes … ADR-0032's severity pills”); ADR-0032 `:5` nadal mówi „Three visible rows”. To rozbieżność dokumentów, nie kodu.
- Copy powiadomień: `lang/pl/ui.php:142-149` (`dashboard.*`); copy planu miesiąca pod `month_plan.*` (np. `state_proposal` = „Niezatwierdzony”, `:303`).

### Reguły i dokumenty do aktualizacji

- `.ai/rules/support.md:8-10` (zakres: `app/Support/DashboardNotices.php`, mapa w `.ai/rules/index.md:9`): „The UI groups and colours notices by severity only, never by kind.” Etykiety i linki są wybierane po `kind` już dziś; reguła dotyczy koloru i grupowania.
- `.docs/design-system.md:42-49`: kwota `warning` w `text-warning`, powód szarym tekstem pod kwotą; powtarza regułę „najpierw pojęcie i progi w `CONTEXT.md`”.
- `.docs/specs/dashboard-hierarchy.md:49-51`: payload, kolejność, linki i siatka kart; `:72`: lista powiadomień „only needs to accept another kind later”. Historie 8, 10, 12 (`:22,24,26`) i `:49,50,66,67` opisują powiadomienie Paced — do aktualizacji przez S-07 (`to-spend-replaces-remaining/plan.md:247`).
- `.docs/specs/dashboard-hierarchy.md:11` nadal mówi, że „Dashboard notice is a UI term, not a glossary term”, a `CONTEXT.md` używa już „`warning` Dashboard notice” — rozbieżność dokumentów.

### Testy

- Wszystkie testy powiadomień są w `tests/Feature/DashboardTest.php` (HTTP, `assertInertia`, `RefreshDatabase`, `travelTo('2026-09-23')`): pusta lista (`:69`), dokładny payload Paced (`:850`), przypadki bez powiadomienia Paced (`:897-1040`), kolejność Paced (`:1059`), konta/lokaty (`:1119`), One-off (`:1150`), brak duplikatu Paced/One-off (`:1183`), kolejność czterech rodzajów (`:1217`). Specyfikacja mówi, by nie testować kolejności osobno jednostkowo (`dashboard-hierarchy.md:64-68`, wg agenta).
- Wzorce do ponownego użycia: To Spend z propozycji (`DashboardTest.php:711`) i zatwierdzony (`:769`, `MonthPlan::factory()->for($budget)->create(['month' => 9])` + `MonthPlanItem::query()->create([...])`). `MonthPlan::factory()` domyślnie jest zatwierdzony; propozycja = brak wiersza. Fabryki: `Category::factory()` z `income()`, `expense()`, `oneOff()`, `bill()`, `archived()` (`database/factories/CategoryFactory.php:35-64`), `Budget::factory()->execution()`.
- Testy jednostkowe odczytów: `tests/Unit/MonthPaceCalculatorTest.php`, `MonthItemDeviationTest.php`, `MonthElapsedTest.php`, `ToSpendCalculatorTest.php`.

## Code References

- `app/Support/DashboardNotices.php:18-44` — PHPDoc kształtu powiadomień (do rozszerzenia o nowe `kind`)
- `app/Support/DashboardNotices.php:83-104` — `paced_month_remaining` (do wyłączenia wg ADR-0033)
- `app/Support/DashboardNotices.php:106-156` — `one_off_over_limit` (roczny limit, nie plan miesiąca)
- `app/Support/DashboardPacedCategoryMonthAlerts.php:22-83` — źródło `paced_month_remaining`
- `app/Enums/DashboardNoticeSeverity.php` — `Exceeded`/`Warning`; docblock „no notice kind emits it yet”
- `app/Http/Controllers/DashboardController.php:88-116` — `MonthToSpend` bieżącego miesiąca i osobne `DashboardNotices::forDashboard`
- `app/Support/MonthToSpend.php:32-101` — `forMonth`, `toSpendMinorUnits`, `rowAmounts`, `summary`, `categories`
- `app/Support/MonthItemDeviation.php:46-49` — `isOverPlan`
- `app/Support/MonthPace.php:19-56` — `forMonth(...)['is_ahead']`
- `app/Support/MonthPaceCalculator.php:55-62` — `isAheadOfMonth` (próg ≥ 10 pp na liczbach całkowitych)
- `app/Support/MonthPlanProposal.php:83-124` — stan `approved|proposal|none`
- `resources/js/components/DashboardNotices.vue:14-54, 105-190` — typ, etykieta, link, klucz
- `resources/js/pages/Dashboard.vue:140-150, 252-290, 323-328` — kafelek „do wydania” i strefa uwagi
- `lang/pl/ui.php:142-149` — copy powiadomień
- `tests/Feature/DashboardTest.php:711, 769, 850-1226` — testy To Spend i powiadomień

## Architecture Insights

- Waga powiadomienia jest decydowana na serwerze obok `kind`; UI tylko koloruje i grupuje po wadze (ADR-0032 `:3`; `.ai/rules/support.md:10`).
- Każda liczba planu miesiąca pochodzi z jednego `MonthToSpend`, a predykaty (`isOverPlan`, `isAheadOfMonth`) mają jedno miejsce w PHP z bliźniakami TS do UI strony Plan miesiąca (`resources/js/lib/month-item-deviation.ts`, `month-pace-calculator.ts`). Powiadomienia liczone na serwerze nie potrzebują nowej kopii TS.
- Klasy Support są statyczne i biorą `$today` parametrem — testowalne bez zegara.
- Payload powiadomień nie niesie URL-i; link powstaje w Vue z `kind` i `current_month`/`budget_year` przez Wayfinder.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:27-31,39,41,53,76,169` — wagi, progi włącznie, przykłady liczbowe; kolejność, linki i pomijanie zarchiwizowanych kategorii zostawione S-07 (`:41`).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:79` — wyłączenie `paced_month_remaining` należy do S-07.
- `context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:19,69,71,77,184,247`; `plan-brief.md:16,31,46,72`; `research.md:81,82,88` — S-06 nie ruszał `DashboardNotices`; plakietka „Niezatwierdzony” na kafelku ma „sygnalizować tylko stan wymagający działania, bez dublowania powiadomienia S-07”; testy Paced należą do S-07; usunięcie niewyświetlanych propsów Month Remaining na stronie Budżet „po S-07/S-08”.
- `context/archive/2026-11-02-month-items-plan-vs-actual/plan.md:39,55,117-121` — `isOverPlan` jako jedyne miejsce predykatu; powiadomienia to S-07.
- `context/archive/2026-11-03-month-spending-pace/plan.md:35-36,39,48-54,118,281` — tempo poza Dashboardem; S-07 ma użyć `MonthPace::forMonth` i `is_ahead` bez nowego zapytania.
- `context/archive/2026-11-02-approved-plan-changes/plan.md:39`; `research.md:166` — brak cofnięcia zatwierdzenia.
- Uwaga porządkowa: `context/changes/month-summary/` (nieśledzony) zawiera tylko pusty `change.md` (`status: new`), a pełna zmiana jest w `context/archive/2026-11-03-month-summary/` — wygląda na pozostałość, niezwiązaną z S-07.

## Related Research

- `context/archive/2026-11-03-month-spending-pace/research.md`
- `context/archive/2026-11-02-to-spend-replaces-remaining/research.md`
- `context/archive/2026-11-02-month-items-plan-vs-actual/research.md`
- F-01 (`context/archive/2026-11-02-month-plan-domain-decisions/`) nie ma `research.md`; źródłem decyzji są `plan.md` i `plan-brief.md`.

## Open Questions

Decyzje dla `/10x-plan` (dokumenty ich nie rozstrzygają):

1. **`paced_month_remaining`: wyłączyć czy usunąć?** ADR-0033 mówi, że nie ma zastosowania; archiwa mówią „wyłączyć”, ale nie, czy usunąć `kind`, `DashboardPacedCategoryMonthAlerts`, copy `notice_paced_month_remaining` i ok. 10 testów. Wnioskowanie: przy bramce „realizacja + bieżący rok” nie zostaje żaden przypadek, w którym miałoby się pojawić.
2. **Nakładanie się z `one_off_over_limit`.** Kategoria One-off z wydatkiem w miesiącu może dać jednocześnie „pozycja ponad plan” (miesiąc, plan często 0) i „ponad limit” (rok). Czy obie zostają, czy jedno tłumi drugie?
3. **Kategorie zarchiwizowane.** `$toSpend->categories` je zawiera; spec mówi „Only non-archived … Categories produce notices” (`dashboard-hierarchy.md:49`), a F-01 zostawił to S-07 (`month-plan-domain-decisions/plan.md:41`).
4. **Kolejność** czterech nowych rodzajów względem istniejących i względem siebie (ADR-0032 chce `exceeded` przed `warning`; dziś porządek wynika tylko z kolejności dopisywania).
5. **Linki** nowych powiadomień: np. „do wydania” i propozycja → strona Plan miesiąca; pozycja ponad plan → Operacje z kategorią i zakresem miesiąca; tempo → Plan miesiąca. Do potwierdzenia w planie.
6. **Dublowanie z kafelkiem „do wydania”.** Kafelek już jest czerwony przy wartości ujemnej i ma plakietkę „Niezatwierdzony” przy propozycji (`Dashboard.vue:140-150, 252-290`). Czy powiadomienia `exceeded`/`warning` pojawiają się mimo to (dokumenty FR-012/ADR-0033 tak mówią), czy plakietkę trzeba zmienić?
7. **Kształt payloadu** dla powiadomień niezwiązanych z kategorią (`id`, `name`, `amount_minor_units` dla tempa i propozycji — np. kwota „do wydania”, procent tempa albo brak kwoty) i odpowiadająca gałąź tożsamości w Vue.
8. **Zakres aktualizacji dokumentów:** `dashboard-hierarchy.md` (Paced, `:11`, `:49-51`), ADR-0031 (zdanie o alercie Month Remaining), `.ai/rules/support.md:10`, docblock `DashboardNoticeSeverity`, oraz rozbieżność „3 vs 6 widocznych” między ADR-0032 `:5` a spec `:51`.
