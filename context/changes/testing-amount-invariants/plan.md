# Niezmienniki kwot — plan implementacji

## Overview

Etap 2 planu testów (`context/foundation/test-plan.md` §3) dla ryzyk #2 i #3. Obie reguły są już na `main`: „do wydania” z S-26 (ADR-0066 zmieniony ADR-0073) i raport okresu z S-28 i następców. Ten etap to siatka regresji wokół nich, nie warunek przed zmianą reguły.

- **#2:** „do wydania” w przeglądarce (TS) i na serwerze (PHP) dają tę samą liczbę. Dowodzą tego dwie warstwy:
  - wspólna tabela przypadków kalkulatorów, czytana przez Pest i Vitest;
  - scenariusze całej ścieżki baza → `EntryToSpendInputs` → podgląd TS, w których PHP i TS są porównywane z tą samą ręcznie policzoną liczbą.
- **#3:** na jednym zestawie operacji z ręczną wyrocznią raport okresu, wykonanie w budżecie rocznym i Wynik miesiąca są równe co do grosza.

Zmiana jest testowa. Jedyna dopuszczalna zmiana produkcyjna to poprawka kandydata 2, jeśli zmieści się w jednym miejscu bez zmiany reguły (decyzja z planowania). W przeciwnym razie kandydat 2 idzie jako issue.

## Current State Analysis

Pełne ustalenia: `context/changes/testing-amount-invariants/research.md`. Najważniejsze dla planu:

- **Kalkulatory są dziś zgodne.**
  - `ToSpendCalculator::itemMinorUnits(CategoryType, ?Rhythm, plan, actual, hasPositiveAmount, collectedAtStart = 0)` (`app/Support/ToSpendCalculator.php:76-94`) i `toSpendBreakdown` (`resources/js/lib/to-spend-calculator.ts:22-69`) rozgałęziają się tak samo.
  - Tak samo `CollectedCalculator::chain` (`app/Support/CollectedCalculator.php:39`) i `collectedChain` (`resources/js/lib/collected-calculator.ts:41`).
  - Testy obu stron (`tests/Unit/ToSpendCalculatorTest.php`, `to-spend-calculator.test.ts`, `CollectedCalculatorTest.php`, `collected-calculator.test.ts`) są przepisane ręcznie i nie dzielą żadnego fixture.
  - Przypadków z glosariusza nie testuje żadna strona: opłata stała z ujemnym netto po zwrocie, opłata stała z planem 0, Even Annual ze zwrotem, nieregularny z niezerową stawką w ostrzeżeniach (każdy test TS ma `rate_minor_units: 0`).
- **Wejścia TS buduje serwer** w `EntryToSpendInputs::forUser($user, $today)` (`app/Support/EntryToSpendInputs.php:43`). TS liczy z nich `previewToSpendReadings(inputs, changes, pending)` (`resources/js/lib/entry-to-spend-warning.ts:295`), który zwraca `{year, month, beforeMinorUnits, afterMinorUnits}`.
  - `before` na wejściach z serwera musi się równać `MonthToSpend::forMonth(...)->toSpendMinorUnits()` (`app/Support/MonthToSpend.php:36,68`).
  - `after` musi się równać temu samemu odczytowi po zaksięgowaniu zmiany.
  - Żaden test nie spina tych trzech elementów.
- **Kandydat 1 (opłata stała wpisana wstecz).** Propozycja miesiąca m bierze dla opłaty stałej netto z m−1 (`MonthPlanProposal::lastPaidAmount`, `app/Support/MonthPlanProposal.php:263-282`). Podgląd TS czyta późniejsze miesiące tylko z okresów nieregularnych (`entry-to-spend-warning.ts:313-326`).
  - Glosariusz (To Spend) zakresem ostrzeżenia obejmuje tylko „miesiąc daty operacji i późniejsze miesiące z planem, na które wpis działa przez Zebrane”. To zachowanie jest więc zgodne z regułą.
- **Kandydat 2 (kategoria dołączająca w styczniu).** `EntryToSpendInputs` podaje kategorie dołączające tylko w `budgets[].categories`, nie w `months[].items` (`EntryToSpendInputs.php:139-152`). TS przyjmuje dla nich plan 0 (`entry-to-spend-warning.ts:142`).
  - Po zapisie `JoinBudgetAtFirstUse` tworzy pozycję. Styczniowa propozycja czyta wtedy przez `matchPreviousCategory` grudzień poprzedniego Budżetu (`MonthPlanProposal.php:249-255,310-314`), co może dać plan > 0.
  - To dotyczy miesiąca operacji, czyli zakresu ostrzeżenia. Rozjazd wynika z odczytu kodu i nie był odtworzony.
- **Odczyty dla #3.** Mamy trzech niezależnych świadków:
  - zapisany licznik `budget_lines.execution_minor_units`, ruszany przez `ManageFinancialOperation`;
  - `PeriodReport` przez `MonthCategoryActuals`, z kluczami `income_minor_units`, `expense_minor_units`, `result_minor_units` (`app/Support/PeriodReport.php:123-125`);
  - `BudgetMonthResult::forViewedMonth` z kluczami `income_execution_minor_units`, `expense_execution_minor_units`, `month_result_minor_units` (`app/Support/BudgetMonthResult.php:18`).

  Strona Budżetu sumuje liczniki pokazanych pozycji (`BudgetPlanSummary::forLines`, `app/Support/BudgetPlanSummary.php:98`) w propsach `incomeTotals` / `expenseTotals`. Bez `?show_archived=1` pomija zarchiwizowane pozycje (`app/Http/Controllers/BudgetController.php:62-71`).
- **Istniejące testy równości** porównują odczyty ze sobą (`tests/Feature/PeriodReportTest.php:96-149`). Literał ma tylko sam raport (`:151-189`). Żaden scenariusz nie łączy zwrotu, transferu własnego, ruchu kapitału, archiwum, granicy miesiąca i granicy roku. Rok wczytany z arkusza ma tylko test licznik = suma operacji (`tests/Feature/LoadBudgetSheetTest.php:190`).
- **CI uruchamia cały zestaw na PostgreSQL 18** (`.github/workflows/tests.yml:23,37,102`), więc nowe testy Feature same wchodzą do bramki `composer ci:check`. Vitest (`vp test`) jest częścią tej samej bramki.

## Desired End State

- `tests/Fixtures/amount-invariants/to-spend-calculator-cases.json` zawiera ręcznie policzone przypadki kalkulatorów, każdy z wyprowadzeniem z glosariusza. Ten sam plik czytają `tests/Unit/ToSpendCalculatorParityTest.php` i `resources/js/lib/to-spend-calculator.parity.test.ts`.
- `tests/Fixtures/amount-invariants/entry-to-spend-scenarios.json` zawiera scenariusze. Każdy ma ręcznie spisane wejścia `EntryToSpendInputs` (z kluczami kategorii zamiast id), zmiany wpisu i oczekiwane odczyty.
  - `tests/Feature/EntryToSpendParityTest.php` zasiewa bazę i sprawdza, że serwer daje dokładnie te wejścia oraz te same liczby przed i po zaksięgowaniu.
  - `resources/js/lib/entry-to-spend-warning.parity.test.ts` sprawdza `previewToSpendReadings` na tych wejściach.
- Kandydat 1 jest utrwalony jako zgodny z regułą. Kandydat 2 jest zielony po małej poprawce albo pominięty z numerem issue na GitHubie.
- `tests/Feature/ReportAmountInvariantTest.php` na jednym scenariuszu porównuje z ręcznymi literałami: liczniki pozycji, sumę po wszystkich pozycjach, stronę Budżetu z `?show_archived=1` (i domyślną bez archiwum), `PeriodReport` za rok i miesiące oraz `BudgetMonthResult`. Osobny test robi to samo dla roku wczytanego z arkusza.
- `test-plan.md` ma przeniesione korekty z research, wzorce w §6.1 i §6.2, notatkę w §6.6, bramkę w §5 jako `required` i nowe wpisy w §7. Reguły `.ai/rules/month-calculators.md` i `reports.md` wskazują nowe testy.

Weryfikacja: `composer ci:check` na PostgreSQL w kontenerze (worktree), `vendor/bin/sloppy ci` i `pint` są zielone. Każdy test, na którym opiera się dowód, czerwienieje przy celowym zepsuciu opisanym w jego fazie.

### Key Discoveries:

- `tests/Pest.php:20-22` wiąże `TestCase` tylko z `Feature`. Test Unit czyta fixture przez `__DIR__`, nie przez `base_path()`. Globalne funkcje Pest muszą mieć nazwy unikalne w całym zestawie (np. `canonicalToSpendItems` już istnieje), więc nowe helpery dostają prefiks.
- Vitest czyta pliki spoza `resources/js` jak `resources/js/ui-contract.test.ts:1-7,78`: `/// <reference types="node" />`, `readFileSync`, `fileURLToPath(import.meta.url)`, import z `'vite-plus/test'`. Wzorzec `JSON.parse(...) as T` jest w repo pierwszy, więc typed lint (`vite.config.ts:60-77`) trzeba sprawdzić. Import `.json` przez `resolveJsonModule` poszerza literały do `string` i nie pasuje do `Rhythm`, dlatego nie używamy go.
- Plan miesiąca w teście Feature to wiersz `MonthPlan::factory()` z `MonthPlanItem` (wzorzec `approve()` w `tests/Feature/EntryToSpendInputsTest.php:377`). Propozycja to brak wiersza dla miesiąca nie wcześniejszego niż „dziś” (`MonthPlanProposal.php:178-180`).
- Księgowanie, które rusza licznik: `ManageFinancialOperation` jak w `bookReportOperation` (`tests/Feature/PeriodReportTest.php:67-80`). Transfer własny i ruch kapitału idą przez `ManageOwnTransfer::create` i `ManageCapitalMovement::create`, a nie przez factory.
- `ArchiveCategory::handle` (`app/Actions/ArchiveCategory.php:18`) archiwizuje pozycję tylko w Budżecie bieżącego roku kalendarzowego, dlatego `now()` w teście musi wypadać w roku scenariusza.
- `SheetWorkbookFactory::with($options, fn ($path) => …)` (`tests/Support/SheetWorkbookFactory.php:42`) buduje skoroszyt w teście. Etykiety muszą istnieć w `database/sheet-load/mapping.php`, kategorie z mapowania muszą być w bazie (wzorzec `householdBeforeSheetLoad()`, `LoadBudgetSheetTest.php:32-41`), a nadpisanie miesiąca zastępuje całą jego listę. Ładowarka daje operacjom datę ostatniego dnia miesiąca.

## What We're NOT Doing

- Nie zmieniamy reguły To Spend ani zakresu ostrzeżenia. Kandydat 1 zostaje zachowaniem zgodnym z glosariuszem. Zmiana wymagałaby wpisu w glosariuszu i ADR.
- Nie zmieniamy strony Budżetu: domyślny widok dalej pomija zarchiwizowane pozycje. Test go utrwala, a §7 opisuje go jako zamierzony.
- Nie testujemy kandydatów 3–6 z research: dwóch źródeł `actual` dla nieregularnego przy niespójnym `budget_id`, źródła stawki bez zatwierdzonego planu, nieaktualnej strony po północy UTC ani edycji z kwotą 0.
- Nie testujemy granicy północy ani stref czasowych. Operacje to zwykłe daty, a żaden odczyt ich nie przelicza.
- Nie sprawdzamy parzystości `MonthRemainingCalculator`, `MonthItemDeviation` i `MonthPaceCalculator`. Są poza ryzykiem #2.
- Nie usuwamy ani nie przepisujemy istniejących testów kalkulatorów. Tabela parzystości dochodzi obok nich.
- Nie poprawiamy nieaktualnych S-14, S-16 i „snapshot v6” w ryzykach #5, #6, #8 i etapie 3 planu testów. To należy do `/10x-test-plan --refresh`, tak jak w etapie 1.
- Nie zmieniamy Statusu etapu w §3 planu testów. Robi to orkiestrator `/10x-test-plan`.

## Implementation Approach

Fazy idą od najtańszego dowodu do najdroższego:

1. Tabela kalkulatorów: Unit i Vitest, bez bazy.
2. Scenariusze ścieżki wejść: Feature na PostgreSQL i Vitest na wspólnym fixture.
3. Kandydaci 1 i 2: rozszerzenie scenariuszy z fazy 2.
4. Scenariusz raportu: Feature na PostgreSQL.
5. Dokumentacja.

Wspólna zasada wszystkich faz: oczekiwana liczba jest literałem policzonym ręcznie z reguły w `context/foundation/glossary.md`, z zapisanym wyprowadzeniem. Nigdy nie pochodzi z wyjścia PHP ani TS. Każdy odczyt porównujemy z literałem, a nie z innym odczytem.

Polecenie testów PostgreSQL w tym worktree (dalej: `PGTEST <ścieżka>`). Bazę `testing_testing_amount_invariants` tworzy się raz: `docker exec household-treasurer-pgsql-1 psql -U sail -d postgres -c 'create database testing_testing_amount_invariants'` (`context/foundation/agents/parallel-changes.md:13,17`).

```sh
docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql \
  -e DB_DATABASE=testing_testing_amount_invariants -e DB_USERNAME=sail -e DB_PASSWORD=password \
  --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html \
  --entrypoint php sail-8.5/app artisan test --compact <ścieżka>
```

Vitest: `npm ci` raz w worktree (Node z nvm), potem `npx vp test <ścieżka>`. `npm run types:check` i `npm run check` po zmianach TS. Pint i Sloppy uruchamiamy w obrazie `sail-8.5/app` (host ma PHP 8.3).

## Critical Implementation Details

- **Klucze kategorii zamiast id w fixture scenariuszy.** Sekwencje PostgreSQL nie cofają się przy `RefreshDatabase`, więc id w bazie zmieniają się między przebiegami.
  - Fixture trzyma w `inputs` małe stałe liczby (klucze 1…n) i mapę klucz → nazwa kategorii.
  - Test PHP zamienia w wyjściu `EntryToSpendInputs::forUser` każde `category_id` na klucz po nazwie i dopiero wtedy porównuje całość z fixture.
  - Vitest używa kluczy bezpośrednio jako id.
- **Stała data.** Każdy scenariusz ma pole `today`. Test PHP robi `travelTo` na ten dzień i przekazuje go do `forUser` i `MonthToSpend::forMonth`. Bez tego stan miesiąca (propozycja albo brak) i zakres miesięcy wejść zależą od dnia uruchomienia.
- **Archiwizacja po zaksięgowaniu.** Operacji nie da się zaksięgować w zarchiwizowanej kategorii. Scenariusz #3 najpierw księguje, potem woła `ArchiveCategory::handle`, z `now()` w roku scenariusza.

## Faza 1: Tabela parzystości kalkulatorów

### Overview

Jedna ręcznie policzona tabela przypadków kalkulatorów „do wydania” i Zebranych, którą czytają test Pest i test Vitest. Rozjazd dowolnej kopii czerwieni jej stronę.

### Changes Required:

#### 1. Tabela przypadków

**File**: `tests/Fixtures/amount-invariants/to-spend-calculator-cases.json` (nowy, pisany ręcznie)

**Intent**: Przypadki pozycji, zestawienia miesiąca i łańcucha Zebranych z reguł To Spend, Irregular Rate i Collected w glosariuszu. Każdy przypadek ma pole `derivation` z rachunkiem prowadzącym do oczekiwanej liczby. Zestaw obejmuje co najmniej:
- **Pozycje:**
  - przychód przed wpływem i po nim;
  - opłata stała przed pierwszą dodatnią operacją, po niej i z ujemnym netto po zwrocie (−2 000);
  - opłata stała z planem 0;
  - bieżący 0/−5 000 i plan < actual;
  - Even Annual ze zwrotem;
  - wydatek bez rytmu (`null`);
  - nieregularny bez nadwyżki i z nadwyżką przy niezerowym `collected_at_start`.
- **Zestawienia:** przykład kanoniczny 373 000, wynik ujemny, odkładam i dobieram.
- **Łańcuch:** podłoga 0 w każdym miesiącu, nadwyżka, przejście przez grudzień → styczeń jako zwykły kolejny miesiąc.

**Contract**:
```
{ "format": 1,
  "items":      [{ "name", "type": "income|expense", "rhythm": "fixed_charge|running|even_annual|irregular|null",
                   "plan", "actual", "has_positive_amount", "collected_at_start", "expected", "derivation" }],
  "breakdowns": [{ "name", "items": [ …jak wyżej bez expected… ], "set_aside",
                   "expected": { "income", "expense", "to_spend" }, "derivation" }],
  "chains":     [{ "name", "opening", "months": [{ "rate", "actual" }],
                   "expected": [{ "start", "end", "surplus" }], "derivation" }] }
```
Kwoty są w groszach (`int`). Wartości `rhythm` to wartości enuma `App\Enums\Rhythm` i typu `Rhythm` w TS. Implementujący sprawdza, że oba się zgadzają.

#### 2. Test Pest

**File**: `tests/Unit/ToSpendCalculatorParityTest.php` (nowy, `php artisan make:test --pest --unit ToSpendCalculatorParityTest --no-interaction`)

**Intent**: Czyta tabelę przez `__DIR__.'/../Fixtures/amount-invariants/to-spend-calculator-cases.json'` (`json_decode` z `JSON_THROW_ON_ERROR`). Każdy przypadek jest osobnym wierszem datasetu nazwanym `name`:
- `items` → `ToSpendCalculator::itemMinorUnits`;
- `breakdowns` → `ToSpendCalculator::breakdown`;
- `chains` → `CollectedCalculator::chain`.

**Contract**: Dataset budowany z pliku. Helpery z prefiksem `parity…`, bez kolizji z `canonicalToSpendItems` i `setAsideExampleItems`.

#### 3. Test Vitest

**File**: `resources/js/lib/to-spend-calculator.parity.test.ts` (nowy)

**Intent**: Czyta ten sam plik wzorcem z `ui-contract.test.ts`.
- `items` sprawdza przez `toSpendBreakdown([item], 0)`. Oczekiwane `to_spend` to `expected` dla przychodu i `−expected` dla wydatku, bo TS nie ma funkcji pojedynczej pozycji.
- `breakdowns` sprawdza przez `toSpendBreakdown`.
- `chains` sprawdza przez `collectedChain`.

Każdy przypadek dostaje osobne `it` nazwane `name`.

**Contract**: `JSON.parse(readFileSync(...)) as ParityCases` z lokalnym typem. Jeśli typed lint odrzuci rzutowanie, implementujący dodaje funkcję zawężającą w teście, bez komentarza wyłączającego lint.

### Success Criteria:

#### Automated Verification:

- Test Pest przechodzi: `php artisan test --compact tests/Unit/ToSpendCalculatorParityTest.php` (Unit, bez bazy, w obrazie `sail-8.5/app`)
- Test Vitest przechodzi: `npx vp test resources/js/lib/to-spend-calculator.parity.test.ts`
- Celowe zepsucie: gałąź opłaty stałej liczona jako `max(plan, actual)` w `to-spend-calculator.ts` czerwieni Vitest na przypadku ujemnego netto. To samo w `ToSpendCalculator.php` czerwieni Pest. Po cofnięciu obie strony są zielone
- `npm run types:check`, `npm run check` i `pint` na nowych plikach są zielone

#### Manual Verification:

- Przeglądasz tabelę: każde `derivation` da się odtworzyć z glosariusza bez uruchamiania kodu

---

## Faza 2: Ścieżka wejść „do wydania”

### Overview

Scenariusze, w których ta sama ręcznie policzona liczba przechodzi trzy kontrole:
- serwer przed zmianą i po niej (`MonthToSpend`);
- wejścia, które serwer wysyła do przeglądarki (`EntryToSpendInputs`);
- podgląd TS na tych wejściach (`previewToSpendReadings`).

### Changes Required:

#### 1. Fixture scenariuszy

**File**: `tests/Fixtures/amount-invariants/entry-to-spend-scenarios.json` (nowy, pisany ręcznie)

**Intent**: Pięć scenariuszy z liczbami policzonymi ręcznie. Daty zmian nie są późniejsze niż `today`, bo dialog ich nie przyjmie (`before_or_equal:today`, `app/Concerns/ExpenseValidationRules.php:21`). W każdym scenariuszu kwoty są w złotych, a w fixture w groszach:
- **`fixed-charge-refund-netted`**
  - Stan: dziś 2026-03-20, marzec zatwierdzony. Przychód: plan 8 000, wpływ 8 000. Prąd (opłata stała): plan 150, operacje 180 i zwrot −200. Jedzenie (bieżący): plan 1 500, wydane 900. Odkładam 1 000.
  - Przed: 8 000 − (−20 + 1 500) − 1 000 = 5 520.
  - Zmiana: Jedzenie +700 z datą 2026-03-18.
  - Po: 8 000 − (−20 + 1 600) − 1 000 = 5 420.
- **`fixed-charge-refund-first`**
  - Stan: dziś 2026-03-20, marzec zatwierdzony. Przychód: plan 6 000 bez wpływu. Prąd (opłata stała): plan 150, bez operacji w marcu. Odkładam 0.
  - Przed: 6 000 − 150 = 5 850.
  - Zmiana: zwrot Prąd −50 z datą 2026-03-18.
  - Po: w miesiącu nadal nie ma dodatniej kwoty, więc Prąd liczy się planem: 6 000 − 150 = 5 850. Odczyt marca 5 850 → 5 850 (miesiąc dotknięty wprost ma odczyt także bez zmiany).
- **`fixed-charge-plan-zero-proposal`**
  - Stan: dziś 2026-01-15, styczeń jako propozycja, brak Budżetu 2025. Przychód: plan roczny 60 000 (propozycja 5 000). Internet (opłata stała): plan 0.
  - Przed: 5 000 − 0 = 5 000.
  - Zmiana: Internet +120 z datą 2026-01-10.
  - Po: 5 000 − 120 = 4 880.
- **`irregular-later-month`**
  - Stan: dziś 2026-11-20, wrzesień–listopad zatwierdzone. Przychód: plan 5 000 bez wpływu. Ubezpieczenie (nieregularny od września): zebrane startowe 600, stawka 100 co miesiąc, rachunek 900 w listopadzie.
  - Zmiana: rachunek 300 z datą 2026-09-10.
  - Łańcuch przed: koniec września 700, koniec października 800, listopad bez nadwyżki.
  - Łańcuch po: koniec września 400, koniec października 500, nadwyżka listopada 900 − 600 = 300.
  - Odczyty: wrzesień 4 900 → 4 900, listopad 4 900 → 4 600. Październik nie zmienia się, więc nie ma odczytu.
- **`running-edit-with-draw`**
  - Stan: dziś 2026-03-20, marzec zatwierdzony. Przychód: plan 6 000 bez wpływu. Jedzenie: plan 1 500, operacja 900. Dobieram 500 (odkładam −500).
  - Przed: 6 000 − 1 500 + 500 = 5 000.
  - Zmiana: edycja tej operacji z 900 na 1 700.
  - Po: 6 000 − 1 700 + 500 = 4 800.

Implementujący sprawdza każdą liczbę ręcznie z glosariusza przed pierwszym uruchomieniem. Jeśli rachunek w planie jest błędny, poprawia literał i `derivation` przed pierwszym uruchomieniem testów, nigdy po nim.

**Contract**:
```
{ "format": 1,
  "scenarios": [{ "key", "today": "YYYY-MM-DD", "categories": { "<klucz>": "<nazwa>" },
                  "inputs": <EntryToSpendInputs z category_id = klucz>,
                  "changes": [<ToSpendEntryChange z categoryId = klucz>],
                  "readings": [{ "year", "month", "beforeMinorUnits", "afterMinorUnits" }],
                  "server_months": [{ "year", "month", "before", "after" }],
                  "derivation" }] }
```
`readings` to oczekiwane wyjście TS. `server_months` to miesiące, w których test PHP sprawdza `MonthToSpend` przed i po. W fazie 2 obejmują te same miesiące co `readings`. `inputs` to pełne wyjście `forUser` na dzień `today`, spisane ręcznie.

#### 2. Test Feature

**File**: `tests/Feature/EntryToSpendParityTest.php` (nowy, `php artisan make:test --pest EntryToSpendParityTest --no-interaction`)

**Intent**: Dla każdego scenariusza test:
1. czyta fixture przez `base_path()`;
2. zasiewa bazę funkcją seedującą przypisaną do `key` (Budżety w wykonaniu, pozycje z rytmami, plany miesięcy jak w `approve()`, operacje przez `ManageFinancialOperation`);
3. robi `travelTo(today)`;
4. sprawdza, że `EntryToSpendInputs::forUser($user, $today)` po zamianie id na klucze równa się `inputs`;
5. sprawdza `MonthToSpend::forMonth(...)->toSpendMinorUnits()` dla każdego `server_months` = `before`;
6. księguje `changes` przez `ManageFinancialOperation` (`create`, a dla edycji `update`);
7. sprawdza każdy `server_months` = `after`.

**Contract**: `uses(RefreshDatabase::class)`. Asercja wejść porównuje całą strukturę, nie wycinek. Seedery to funkcje PHP w pliku testu, bo fixture opisuje wynik, nie sposób zasiania.

Przed asercją test kanonizuje wyjście `forUser`, bo kolejność wierszy na PostgreSQL nie jest ustalona. `MonthCategoryActuals` grupuje bez `ORDER BY` (`app/Support/MonthCategoryActuals.php:40`), a relacja `budgetLines` nie ma `orderBy` (`app/Models/Budget.php:50`). Kanonizacja:
- `budgets[].categories` sortuje po kluczu;
- `months[].items` sortuje po kluczu kategorii;
- `irregular_periods` sortuje po kluczu kategorii, a potem po początku okresu.

Fixture `inputs` jest spisany w tej samej kolejności.

#### 3. Test Vitest

**File**: `resources/js/lib/entry-to-spend-warning.parity.test.ts` (nowy)

**Intent**: Dla każdego scenariusza `previewToSpendReadings(inputs, changes)` równa się `readings`. Dla scenariusza z edycją test buduje zmiany przez `handEntryToSpendChanges` (stan dialogu z `original`), żeby sprawdzić ścieżkę dialogu, a wynik porównuje z `changes` z fixture.

**Contract**: Odczyt fixture jak w fazie 1.

### Success Criteria:

#### Automated Verification:

- Test Feature przechodzi na PostgreSQL: `PGTEST tests/Feature/EntryToSpendParityTest.php`
- Test Vitest przechodzi: `npx vp test resources/js/lib/entry-to-spend-warning.parity.test.ts`
- Celowe zepsucie po stronie serwera: wejścia `EntryToSpendInputs` liczą `actual_minor_units` bez zwrotów. Czerwieni to test Feature na `fixed-charge-refund-netted`, a Vitest zostaje zielony. Po cofnięciu zielone
- Celowe zepsucie po stronie TS: `applyChange` zwiększa `positiveCount` także dla kwoty ujemnej. Czerwieni to Vitest na `fixed-charge-refund-first` (podgląd liczy wtedy −50 zamiast planu 150, czyli 6 050). Po cofnięciu zielony
- Istniejące testy ostrzeżeń przechodzą: `npx vp test resources/js/lib/entry-to-spend-warning.test.ts resources/js/lib/import-to-spend-warnings.test.ts`
- `pint`, `npm run types:check` i `npm run check` są zielone

#### Manual Verification:

- Przeglądasz pięć scenariuszy: stan, zmiana i liczby odpowiadają temu, co zobaczyłbyś w dialogu wydatku i na planie miesiąca

---

## Faza 3: Kandydaci 1 i 2

### Overview

Sprawdzić dwa miejsca, w których liczba w przeglądarce może różnić się od serwerowej. Kandydat 1 zostaje utrwalony jako zgodny z regułą. Kandydat 2 idzie najpierw jako czerwony test, a potem dostaje poprawkę albo issue.

### Changes Required:

#### 1. Kandydat 1: opłata stała wpisana wstecz

**File**: `tests/Fixtures/amount-invariants/entry-to-spend-scenarios.json`, oba testy z fazy 2

**Intent**: Scenariusz `fixed-charge-backdated-proposal`:
- Stan: dziś 2026-03-10, luty zatwierdzony, marzec jako propozycja. Przychód: plan 5 000 bez wpływu, w marcu kopiowany z lutego. Prąd: plan 150, w lutym bez płatności. Odkładam 0.
- Zmiana: Prąd +180 z datą 2026-02-10.
- `readings`: tylko luty, 4 850 → 4 820.
- `server_months`: luty 4 850 → 4 820 i marzec 4 850 → 4 820, bo propozycja bierze teraz 180 z lutego zamiast kopii planu 150.

Test Vitest ma w nazwie, że brak odczytu marca jest zgodny z glosariuszem (zakres ostrzeżenia), z odnośnikiem do hasła To Spend.

**Contract**: Pole `server_months` może obejmować miesiąc, którego nie ma w `readings`. Tylko ten scenariusz tak robi i jego `derivation` to wyjaśnia.

#### 2. Kandydat 2: najpierw czerwony test

**File**: jak wyżej

**Intent**: Scenariusz `january-joining-category`:
- Stan: dziś 2027-01-12. Budżet 2026 w wykonaniu z zatwierdzonym grudniem: przychód 5 000, Fryzjer (bieżący) 400. Budżet 2027 w wykonaniu, z pozycją przychodu i bez pozycji Fryzjera, styczeń jako propozycja (przychód 5 000 z grudnia).
- Przed: 5 000.
- Zmiana: Fryzjer +100 z datą 2027-01-10.
- Po (oczekiwane z reguły): pozycja dołącza, propozycja bierze 400 z grudnia, więc 5 000 − max(400, 100) = 4 600.
- `readings` i `server_months`: styczeń 2027, 5 000 → 4 600.

Test Feature ma wtedy przejść, a Vitest być czerwony (podgląd liczy z planem 0: 4 900). Testy uruchamiamy przed jakąkolwiek zmianą kodu i zapisujemy wynik w `change.md`.

**Contract**: Jeśli test Feature też nie daje 4 600, research się mylił co do mechanizmu. Wtedy implementujący zatrzymuje się i zgłasza wynik, zanim zmieni scenariusz.

#### 3. Kandydat 2: rozstrzygnięcie

**File**: zależnie od wyniku

**Intent**: Reguła z planowania:
- **Mała poprawka.** Jeśli poprawka mieści się w jednym miejscu i nie zmienia reguły, wchodzi tutaj, a Vitest zmienia się z czerwonego na zielony. Przykład: serwer podaje w `months[].items` pozycję kategorii dołączającej z planem propozycji, bez zmiany kształtu typu.
  - Poprawka zmienia wyjście serwera, więc w tym samym commicie trzeba ręcznie poprawić `inputs` scenariusza `january-joining-category`, np. dopisać Fryzjera w `months[].items` stycznia z planem 400.
  - To zmiana wymagania co do wejścia, a nie korekta oczekiwanego odczytu. `readings` i `server_months` (5 000 → 4 600) zostają bez zmian, a `derivation` dostaje adnotację, co i dlaczego się zmieniło.
- **Issue.** Jeśli poprawka wymaga zmiany kształtu propsa (`EntryToSpendInputs` w PHP i typ w TS) albo zmiany reguły propozycji, idzie jako issue na GitHubie (`gh issue create`, etykieta `needs-triage`, procedura `context/foundation/agents/issue-tracker.md`) z odnośnikiem do scenariusza i research. Przypadek Vitest dostaje wtedy `it.skip` z numerem issue w nazwie, a przypadek Feature zostaje aktywny.

**Contract**: Wynik (poprawka z SHA albo numer issue) trafia do sekcji Notes w `change.md` i do §7 planu testów w fazie 5. Każda zmiana propsa Inertia idzie w jednym commicie po obu stronach z testem `assertInertia` (`AGENTS.md`).

### Success Criteria:

#### Automated Verification:

- Przed jakąkolwiek zmianą kodu Vitest jest czerwony na `january-joining-category`, a test Feature zielony. Wynik zapisany w `change.md`
- Po rozstrzygnięciu: `PGTEST tests/Feature/EntryToSpendParityTest.php` jest zielony, a `npx vp test resources/js/lib/entry-to-spend-warning.parity.test.ts` jest zielony albo ma jeden pominięty przypadek z numerem issue
- Przy wariancie z issue: `gh issue view <numer>` pokazuje zgłoszenie z etykietą `needs-triage`
- Przy wariancie z poprawką: `PGTEST tests/Feature/EntryToSpendInputsTest.php` i testy ostrzeżeń TS przechodzą, a `pint`, `vendor/bin/sloppy ci`, `npm run types:check` i `npm run check` są zielone

#### Manual Verification:

- Potwierdzasz rozstrzygnięcie kandydata 2 (poprawka albo issue) po zobaczeniu wyniku czerwonego testu

---

## Faza 4: Niezmiennik raportu

### Overview

Jeden scenariusz z ręczną wyrocznią, w którym każdy niezależny świadek (licznik pozycji, strona Budżetu, raport, Wynik miesiąca) równa się tej samej liczbie. Do tego test roku wczytanego z arkusza.

### Changes Required:

#### 1. Scenariusz z granicami

**File**: `tests/Feature/ReportAmountInvariantTest.php` (nowy, `php artisan make:test --pest ReportAmountInvariantTest --no-interaction`)

**Intent**: Dziś 2026-06-15. Budżety 2025 i 2026 w wykonaniu, dwa konta. Wszystko księgujemy przez akcje, kwoty w złotych:
- **Wynagrodzenie:** 8 000 dnia 2026-03-31 i 8 000 dnia 2026-04-01.
- **Jedzenie 2026:** 1 200 dnia 2026-01-01, 900 dnia 2026-03-31, 700 dnia 2026-04-01, zwrot −150 dnia 2026-04-10.
- **Jedzenie 2025** (ta sama kategoria listy): 1 000 dnia 2025-12-31.
- **Siłownia:** 150 piętnastego stycznia, lutego, marca i kwietnia 2026, potem `ArchiveCategory::handle`.
- **Transfer własny:** 500 dnia 2026-03-31 (`ManageOwnTransfer::create`).
- **Ruch kapitału:** 600 dnia 2026-04-01 (`ManageCapitalMovement::create`). Wymaga lokaty (`TermDeposit`) użytkownika podanej w `term_deposit_id`, wzorzec w `tests/Feature/AccountBalanceOnDateTest.php:43-71`.
- **Obcy użytkownik:** wydatek 999 dnia 2026-03-31.

Ręczne literały, z rachunkiem w komentarzu:
- **Liczniki:** Jedzenie 2026 = 2 650, Siłownia = 600, Wynagrodzenie = 16 000, Jedzenie 2025 = 1 000.
- **Rok 2026:** wydatki 3 250, przychody 16 000, wynik 12 750. Takie wartości muszą dać:
  - suma liczników po wszystkich pozycjach;
  - `expenseTotals.execution_minor_units` i `incomeTotals` strony Budżetu z `?show_archived=1`;
  - `PeriodReport::forYear`;
  - suma 12 × `BudgetMonthResult`.
- **Domyślna strona Budżetu** (bez archiwum): wydatki 2 650 (zamierzone, §7).
- **Miesiące 2026:** te same wartości z `PeriodReport::forMonth` i `BudgetMonthResult::forViewedMonth`:
  - styczeń: wydatki 1 350;
  - luty: 150;
  - marzec: przychody 8 000, wydatki 1 050, wynik 6 950;
  - kwiecień: przychody 8 000, wydatki 700, wynik 7 300;
  - maj–grudzień: 0.
- **Rok 2025:** grudzień i rok 2025 dają wydatki 1 000, a styczeń 2026 nie zawiera tej operacji.

**Contract**: Helper księgujący jak `bookReportOperation`, z własną nazwą. Strona Budżetu przez `get(route('budget', ['show_archived' => 1]))` z `withSession(['budget_year' => 2026])` i `assertInertia`, jak w `tests/Feature/BudgetTest.php:1123`. Żadna asercja nie porównuje dwóch odczytów ze sobą. Każdy odczyt idzie do literału.

#### 2. Rok wczytany z arkusza

**File**: `tests/Feature/ReportAmountInvariantTest.php`

**Intent**: Mały skoroszyt 2025 przez `SheetWorkbookFactory`. Jedna etykieta wydatku z mapowania dostaje 1 000 w miesiącach 1–11 i 1 200 w grudniu. Pozostałe listy wszystkich 12 miesięcy są nadpisane pustymi albo minimalnymi wartościami spełniającymi ograniczenia ładowarki. Ładowanie przez `budget:load-sheet` jak `loadSheetYear`.

Literały:
- licznik pozycji = 12 200 (11 × 1 000 + 1 200);
- raport 2025 dla tej kategorii = 12 200;
- suma 12 × `BudgetMonthResult` (wydatki) = 12 200;
- grudzień 2025 = 1 200 (operacja 31.12);
- raport stycznia 2026 nie zawiera tej kwoty (Budżet 2026 w wykonaniu, pusty).

**Contract**: Kategorie mapowania zakładane jak w `householdBeforeSheetLoad()`, bez wywoływania funkcji z innego pliku testów. Jeśli ograniczenia ładowarki wymagają przychodu, jego kwota też jest literałem i wchodzi do asercji przychodów.

### Success Criteria:

#### Automated Verification:

- Testy przechodzą na PostgreSQL: `PGTEST tests/Feature/ReportAmountInvariantTest.php`
- Celowe zepsucie: w `MonthCategoryActuals` warunek `<` pierwszego dnia następnego miesiąca zmieniony na `<=` czerwieni asercje marca raportu, bo do marca wpadają operacje z 01.04. Kwiecień zostaje zielony, bo 01.05 nie ma operacji. Po cofnięciu zielone
- Celowe zepsucie: `BudgetMonthResult` bez filtra `budget_id` czerwieni asercję marca, bo wpada obca operacja z 31.03 (sąsiedni rok i tak odcina `whereYear`). Jeśli nie czerwieni, implementujący dobiera zepsucie, które ten odczyt łapie, i opisuje je w Notes. Po cofnięciu zielone
- Istniejące testy raportu przechodzą: `PGTEST tests/Feature/PeriodReportTest.php tests/Feature/BudgetMonthResultTest.php tests/Feature/LoadBudgetSheetTest.php`
- `pint` i `vendor/bin/sloppy ci` są zielone

#### Manual Verification:

- Przeglądasz komentarze z rachunkiem w teście: każdą liczbę da się odtworzyć z listy operacji bez uruchamiania kodu

---

## Faza 5: Plan testów i reguły

### Overview

Przenieść korekty z research do `context/foundation/test-plan.md`, zapisać wzorce w cookbooku i wskazać nowe testy w regułach ścieżek, żeby następna zmiana kalkulatora albo raportu dopisała przypadek, a nie nowy test od zera.

### Changes Required:

#### 1. §2 i §3 planu testów

**File**: `context/foundation/test-plan.md`

**Intent**: Backport z research („Backport to test-plan.md”):
- **#2:**
  - Źródło: reguła `.ai/rules/month-calculators.md` i S-26 (ADR-0066, ADR-0073) zamiast „roadmap S-14 Risk”.
  - Ryzyko: „zwłaszcza przy zmianie reguły dla wydatku nieregularnego (reguła S-26 już obowiązuje)”.
  - Guidance „Context”: gdzie reguła jest zapisana w obu kopiach i skąd TS bierze wejścia (`EntryToSpendInputs`).
  - „Must challenge”: ścieżka wejść.
- **#3:**
  - Źródło: `prd-v3.md` Guardrails, ADR-0070, S-28, S-34 zamiast `prd-v2.md` i S-11/S-12.
  - Guidance „Context”: co pokrywają `PeriodReportTest`, `ReportYearsTest` i `BudgetMonthResultTest` i które odczyty dzielą `MonthCategoryActuals`.
- **§3:**
  - Cel etapu 2: „na regule S-26 i istniejącym raporcie S-28 (oba już na `main`)”.
  - Akapit „Kolejność”: zdanie o S-14/S-11 zastąpione informacją, że obie części mogą iść teraz. „Etap 3 stoi przed v6 snapshotu” zostaje.

**Contract**: Wiersze tabel §2 i akapit §3, bez zmiany kolumn i bez Statusu etapu. Wiersze #5, #6, #8 i etap 3 bez zmian.

#### 2. §5, §6, §7 i nagłówek

**File**: `context/foundation/test-plan.md`

**Intent**:
- **§5:** bramka „testy parzystości „do wydania” PHP↔TS i niezmienników raportu” → `required`.
- **§6.1:** zamiast TBD wzorzec parzystości:
  - tabela w `tests/Fixtures/amount-invariants/`;
  - ręczne `derivation` z glosariusza;
  - jeden plik czytany przez Pest (`__DIR__`) i Vitest (`readFileSync`);
  - jak dodać przypadek;
  - scenariusze ścieżki wejść z kluczami zamiast id.
- **§6.2:** zamiast TBD wzorzec niezmiennika raportu:
  - księgowanie przez akcje;
  - każdy świadek osobno do ręcznego literału;
  - pary granic 31.03/01.04 i 31.12/01.01;
  - archiwum po zaksięgowaniu;
  - rok z arkusza.
- **§6.6:** 2–3 linie o tym, czego nauczył etap, w tym wynik kandydata 2.
- **§7:**
  - domyślna strona Budżetu bez zarchiwizowanych pozycji jako zamierzona (test ją utrwala);
  - kandydat 1 jako zgodny z zakresem ostrzeżenia w glosariuszu, z warunkiem ponownej oceny: zmiana zakresu ostrzeżenia albo reguły propozycji;
  - kandydaci 3–6 z research jako świadomie nietestowani;
  - issue kandydata 2, jeśli powstało.
- **Nagłówek:** „Last updated” na dzień zamknięcia fazy.

**Contract**: Sekcje §5, §6.1, §6.2, §6.6, §7.

#### 3. Reguły ścieżek

**File**: `.ai/rules/month-calculators.md`, `.ai/rules/reports.md`, `.ai/rules/index.md` (generowany)

**Intent**:
- **`month-calculators.md`:** zmiana kalkulatora „do wydania” albo Zebranych dodaje przypadek z ręcznym `derivation` do `to-spend-calculator-cases.json`. Zmiana `EntryToSpendInputs` albo `entry-to-spend-warning.ts` aktualizuje `inputs` scenariuszy ręcznie, nigdy przez skopiowanie wyjścia serwera. Frontmatter `paths` obejmuje nowe testy i fixture.
- **`reports.md`:** w sekcji „Report amounts equal the existing readings to the grosz” dowodem równości jest przypadek w `ReportAmountInvariantTest.php` z ręcznym literałem, a nie asercja raport == inny odczyt.

Indeks regenerujemy przez `php artisan boost:index-rules` w kontenerze.

**Contract**: Treść reguł po angielsku, jak obecne pliki.

### Success Criteria:

#### Automated Verification:

- Pełna bramka na PostgreSQL w kontenerze: `docker run … --entrypoint composer sail-8.5/app ci:check` z `DB_DATABASE=testing_testing_amount_invariants`
- `vendor/bin/sloppy ci` w obrazie `sail-8.5/app` jest zielone
- `grep -n "S-14\|S-11" context/foundation/test-plan.md` nie zwraca linii w wierszach #2 i #3, w ich Risk Response Guidance, w celu etapu 2 ani w akapicie „Kolejność”
- `.ai/rules/index.md` po `boost:index-rules` wymienia nowe ścieżki w wierszu `month-calculators.md`

#### Manual Verification:

- Czytasz zmienione §2, §3, §5, §6 i §7 i potwierdzasz, że oddają decyzje z planowania

---

## Testing Strategy

### Unit Tests:

- Tabela parzystości kalkulatorów (Pest Unit i Vitest na jednym JSON): pozycje każdego rytmu, zestawienia z odkładam i dobieram, łańcuch Zebranych z podłogą 0.

### Integration Tests (Feature, PostgreSQL):

- Ścieżka wejść: 5 scenariuszy, kandydat 1 i kandydat 2. Serwer przed i po, plus pełne wejścia TS po kanonizacji kolejności.
- Niezmiennik raportu: zwrot, transfer własny, ruch kapitału, archiwum, granica miesiąca i roku, obcy użytkownik. Rok z arkusza z operacją 31.12.

### Celowe zepsucie (dowód, że testy coś łapią):

- Gałąź opłaty stałej jako `max` → czerwona tabela po stronie, którą zepsuto.
- Wejścia bez zwrotów → czerwony test Feature ścieżki wejść.
- `positiveCount` dla kwoty ujemnej → czerwony Vitest ścieżki wejść (`fixed-charge-refund-first`).
- `<=` w `MonthCategoryActuals` → czerwony raport marca (granica 31.03/01.04).
- `BudgetMonthResult` bez filtra Budżetu → czerwony Wynik marca (obca operacja).

### Manual Testing Steps:

1. Przejrzyj `derivation` w obu plikach fixture.
2. Przejrzyj scenariusze ścieżki wejść pod kątem tego, co widać w dialogu i na planie miesiąca.
3. Zdecyduj o kandydacie 2 po zobaczeniu czerwonego testu.
4. Przeczytaj zmienione sekcje planu testów.

## Performance Considerations

Nowe testy Feature zasiewają po kilka Budżetów i kilkanaście operacji. Mieszczą się w czasie istniejących testów raportu. Test arkusza buduje i ładuje skoroszyt, jak testy w `LoadBudgetSheetTest.php`.

## Migration Notes

Brak migracji. Zmiana zachowania jest możliwa tylko w fazie 3, jeśli poprawka kandydata 2 wejdzie tutaj: ostrzeżenie „do wydania” w styczniu dla kategorii dołączającej liczy wtedy z planem propozycji.

## References

- Research: `context/changes/testing-amount-invariants/research.md`
- Plan testów: `context/foundation/test-plan.md` (§2 #2, #3; §3 etap 2; §5; §6.1; §6.2; §6.6; §7)
- Glosariusz: hasła To Spend, Irregular Rate, Collected, Opening Collected (`context/foundation/glossary.md:112-137`)
- ADR-0066, ADR-0073 (reguła nieregularnego), ADR-0070 (raport okresu)
- Wzorce testów: `tests/Unit/ToSpendCalculatorTest.php:65-88`, `tests/Feature/EntryToSpendInputsTest.php:47-58,377`, `tests/Feature/MonthToSpendTest.php:25-62`, `tests/Feature/PeriodReportTest.php:67-80,96-189`, `tests/Feature/BudgetTest.php:1123`, `tests/Feature/LoadBudgetSheetTest.php:32-41,116`, `resources/js/ui-contract.test.ts:1-7,78`, `resources/js/lib/entry-to-spend-warning.test.ts:48-63,571-620`
- Etap 1 (konwencje): `context/archive/2026-11-17-testing-import-integrity/plan.md`
- Procedury: `context/foundation/agents/parallel-changes.md`, `context/foundation/agents/issue-tracker.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Tabela parzystości kalkulatorów

#### Automated

- [x] 1.1 Test Pest tabeli parzystości przechodzi — 03b9827
- [x] 1.2 Test Vitest tabeli parzystości przechodzi — 03b9827
- [x] 1.3 Celowe zepsucie gałęzi opłaty stałej czerwieni zepsutą stronę; po cofnięciu zielone — 03b9827
- [x] 1.4 `npm run types:check`, `npm run check` i `pint` są zielone — 03b9827

#### Manual

- [ ] 1.5 Przegląd `derivation` w tabeli: każdą liczbę da się odtworzyć z glosariusza

### Phase 2: Ścieżka wejść „do wydania”

#### Automated

- [x] 2.1 Test Feature ścieżki wejść przechodzi na PostgreSQL — aa300d4
- [x] 2.2 Test Vitest ścieżki wejść przechodzi — aa300d4
- [x] 2.3 Celowe zepsucie wejść (bez zwrotów) czerwieni test Feature; po cofnięciu zielony — aa300d4
- [x] 2.4 Celowe zepsucie `positiveCount` w TS czerwieni Vitest na `fixed-charge-refund-first`; po cofnięciu zielony — aa300d4
- [x] 2.5 Istniejące testy ostrzeżeń TS przechodzą — aa300d4
- [x] 2.6 `pint`, `npm run types:check` i `npm run check` są zielone — aa300d4

#### Manual

- [ ] 2.7 Przegląd pięciu scenariuszy pod kątem dialogu i planu miesiąca

### Phase 3: Kandydaci 1 i 2

#### Automated

- [x] 3.1 Vitest czerwony, a Feature zielony na `january-joining-category` przed zmianą kodu; wynik w `change.md` — d9a2e5d
- [x] 3.2 Po rozstrzygnięciu oba testy ścieżki wejść zielone albo jeden przypadek pominięty z numerem issue — d9a2e5d
- [x] 3.3 Wariant issue: zgłoszenie z etykietą `needs-triage` istnieje — d9a2e5d
- [x] 3.4 Wariant poprawki: testy wejść i ostrzeżeń przechodzą, bramki statyczne zielone — d9a2e5d

#### Manual

- [ ] 3.5 Potwierdzone rozstrzygnięcie kandydata 2 po zobaczeniu czerwonego testu

### Phase 4: Niezmiennik raportu

#### Automated

- [x] 4.1 Testy niezmiennika raportu przechodzą na PostgreSQL
- [x] 4.2 Celowe zepsucie granicy w `MonthCategoryActuals` czerwieni raport marca; po cofnięciu zielony
- [x] 4.3 Celowe zepsucie filtra Budżetu w `BudgetMonthResult` czerwieni Wynik miesiąca; po cofnięciu zielony
- [x] 4.4 Istniejące testy raportu, Wyniku miesiąca i arkusza przechodzą
- [x] 4.5 `pint` i `vendor/bin/sloppy ci` są zielone

#### Manual

- [ ] 4.6 Przegląd komentarzy z rachunkiem: każdą liczbę da się odtworzyć z listy operacji

### Phase 5: Plan testów i reguły

#### Automated

- [ ] 5.1 Pełna bramka `composer ci:check` na PostgreSQL w kontenerze
- [ ] 5.2 `vendor/bin/sloppy ci` w obrazie `sail-8.5/app` jest zielone
- [ ] 5.3 `S-14` i `S-11` nie występują w poprawionych wierszach i akapitach planu testów
- [ ] 5.4 Indeks reguł wymienia nowe ścieżki w wierszu `month-calculators.md`

#### Manual

- [ ] 5.5 Przeczytane §2, §3, §5, §6 i §7 oddają decyzje z planowania
