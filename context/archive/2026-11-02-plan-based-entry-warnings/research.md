---
date: 2026-11-02T12:00:49+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 4cedcc5c8c5ae3476c2df274e98a851a4994c8b9
branch: worktree-plan-based-entry-warnings
repository: household-treasurer
topic: "S-08 plan-based-entry-warnings: jak dziś działają ostrzeżenia przy ręcznej operacji i w podglądzie Importu, co z S-01 można wykorzystać do ostrzeżeń liczonych z planu i co dokumenty wiążą"
tags: [research, codebase, month-plan, to-spend, month-remaining, hand-entry, csv-import, warnings]
status: complete
last_updated: 2026-11-02
last_updated_by: Claude (claude-opus-5-5)
---

# Research: S-08 — ostrzeżenia przy operacjach liczone z planu: stan kodu

**Date**: 2026-11-02T12:00:49+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 4cedcc5c8c5ae3476c2df274e98a851a4994c8b9 (lokalny commit worktree; nie sprawdzano, czy jest na remote, więc kotwice są lokalne `plik:linia`)
**Branch**: worktree-plan-based-entry-warnings
**Repository**: household-treasurer

## Research Question

Pytanie wyprowadzone z roadmapy S-08 (`context/foundation/roadmap.md:183-193`) i PRD (`context/foundation/prd.md:138-139`, `:147-148`), bo `change.md` nie ma notatek:

Co w obecnym kodzie składa się na ostrzeżenia przy ręcznej operacji i w podglądzie Importu, które odczyty S-01 (plan miesiąca, „do wydania”) da się wykorzystać, żeby — gdy miesiąc daty operacji ma plan (także niezatwierdzoną propozycję) — ostrzegać, gdy „do wydania” zejdzie poniżej zera, a bez planu zostawić obecne ostrzeżenia `Pozostało w miesiącu`; oraz jakie dokumenty i reguły to ogranicza.

## Summary

- **Dzisiejsze ostrzeżenia miesiąca** (kategoria i pula kategorii równomiernych, liczone `MonthRemainingCalculator`) pokazują się w dwóch miejscach, oba liczone w TS na kliencie:
  - ręcznie: tylko w `ExpenseFormDialog.vue` (tworzenie i edycja), tylko dla `kind === 'expense'` i kategorii równomiernej (`resources/js/lib/hand-entry-month-remaining-preview.ts:160`, `:167-197`);
  - Import: `computeImportMonthRemainingWarnings` w `resources/js/pages/OperationsImportPreview.vue:144-152`, przeliczane przy każdej zmianie wyboru wiersza.
  - Żadne nie blokuje zapisu.
- **Serwerowy `ImportMonthRemainingWarningAnnotator` liczy flagi, których UI nie czyta.** Grep po `resources/js`, `app`, `tests` znalazł użycie flag `show_*_month_remaining_warning` / `month_remaining_period_label` tylko w `tests/Feature/ImportMonthRemainingWarningTest.php` i w opcjonalnych polach typu `resources/js/lib/import-preview-rows.ts:21-23`. Kopie logiki są więc cztery (kalkulator PHP+TS, dwa pliki podglądu TS), plus martwy annotator PHP; dwa pliki podglądu TS nie mają testów (`AGENTS.md`, „Month logic lives twice”).
- **S-01 daje gotowe klocki**: `MonthPlanProposal::forBudget(...)->reading($month)` (stany `approved` / `proposal` / `none`), `MonthCategoryActuals::forMonth` (jedno zapytanie na miesiąc) i `ToSpendCalculator` z kopią TS, której wejście `ToSpendItem` ma dokładnie potrzebne pola. Dziś czyta je tylko strona „Plan miesiąca” (`routes/web.php:28-31`).
- **Daty operacji nie mogą być przyszłe** (`before_or_equal:today` w `app/Concerns/ExpenseValidationRules.php:24` i `app/Concerns/IncomeValidationRules.php:24`; w Imporcie wg badacza `FinancialOperationImportValidator.php:115,147`, `CsvImportPreviewValidator.php:27`). Stąd na sprawdzonej ścieżce `MonthPlanProposal::computePlan` (`app/Support/MonthPlanProposal.php:83-122`) miesiąc daty wpisu ma jeden z trzech stanów:
  - bieżący miesiąc: `approved` albo `proposal`;
  - miniony miesiąc z zapisanym planem: `approved`;
  - miniony miesiąc bez zapisu: `none` → zostają ostrzeżenia `Pozostało w miesiącu`.
  Propozycję trzeba więc liczyć dla co najwyżej jednego miesiąca (bieżącego); pozostałe miesiące z planem to zapisane plany.
- **Ostrzeżenie z planu nie jest monotoniczne względem kwoty** (`app/Support/ToSpendCalculator.php:15-36`): pierwszy wydatek Bill mniejszy od planu podnosi „do wydania”; wydatek Running w granicach planu go nie zmienia; pierwszy wpływ dochodu mniejszy od planu je obniża. Plan musi rozstrzygnąć, które rodzaje wpisu ostrzegają (dziś tylko wydatki).
- **Edycja operacji wymaga liczby kwot dodatnich, nie flagi**: usunięcie starej kwoty może zgasić `has_positive_amount`. `MonthCategoryActuals` trzyma `positive_count` prywatnie (`app/Support/MonthCategoryActuals.php:12,34`), na zewnątrz wystawia tylko bool (`:46-49`).
- **Dokumenty do zmiany przez S-08** (przypisane w archiwum F-01, `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:39`): FR-TRX-08 w `.docs/prd.md:191` i zdanie o „over Month Remaining” w `.ai/rules/js.md:24`. `CONTEXT.md` nie definiuje osobnego terminu ani progu ostrzeżenia przy wpisie; jedyny próg to „do wydania” < 0 (`CONTEXT.md:79-81`).

## Detailed Findings

### 1. Ręczna operacja: obecne ostrzeżenie miesiąca

- **Miejsce.** `ExpenseFormDialog.vue` montowany w `resources/js/pages/Operations.vue` dwa razy (tworzenie `:346-352`, edycja `:354-362`), oba z `:hand-entry-month-remaining` (`:351`, `:361`). `IncomeFormDialog.vue` i `OwnTransferFormDialog.vue` korzystają tylko z `previewHandEntryWarnings` (limit roczny i ujemne saldo), bez ostrzeżenia miesiąca (wg badacza, grep).
- **Warunek wejścia.** `previewHandEntryMonthRemainingWarnings` zwraca brak ostrzeżeń, gdy `proposal.kind !== 'expense'` (`hand-entry-month-remaining-preview.ts:160`), czyli dla zwrotu (`'refund'`) i dla edycji „zamień na wpływ” (`'income'`), rodzaj ustawiany w `ExpenseFormDialog.vue:144-150`. Brak ostrzeżenia także bez kategorii, bez daty, bez budżetu roku daty albo dla kategorii nierównomiernej (`:167-197`, wg badacza).
- **Prop.** `OperationsController.php:180`: `'handEntryMonthRemaining' => HandEntryMonthRemainingInputs::forUser($user)`. Kształt (`app/Support/HandEntryMonthRemainingInputs.php:12-26`, wg badacza): `{today, budgets: [{year, is_draft, categories: [{id, is_paced, planned_amount_minor_units, monthly_execution_minor_units: {1..12}}]}]}`. Typ TS w `hand-entry-month-remaining-preview.ts:18-34`; prop w `Operations.vue:69` i `ExpenseFormDialog.vue:74`. `assertInertia` w `tests/Feature/HandEntryMonthRemainingTest.php:14-55`.
- **Zakres danych.** Wszystkie budżety w fazie wykonania i wszystkie kategorie wydatków, także zarchiwizowane (do puli), z wykonaniem 12 miesięcy z `BudgetMonthExecutionIndex` (`app/Support/BudgetMonthExecutionIndex.php:71-93`). Gałąź `is_draft` jest na tej ścieżce zawsze fałszywa, bo ładowane są tylko budżety w wykonaniu (wg badacza).
- **Delta.** Edycja odejmuje starą kwotę, gdy `category_id` i miesiąc starej operacji pasują (stara operacja budowana w `ExpenseFormDialog.vue:200-215`, miesiąc bez roku), potem dodaje nową kwotę ze znakiem (`hand-entry-month-remaining-preview.ts:43-73`, wg badacza).
- **Warunek i tekst.** `categoryAfter < 0` i `poolAfter < 0` (`:317-320`, wg badacza). Szablon `<Alert>` z tytułem `operations.expense_warnings_title` „Uwaga” (`ExpenseFormDialog.vue:587-618`). Klucze `lang/pl/ui.php:540-541`: `over_month_category_warning` „Operacja przekroczy pozostałość miesiąca tej kategorii (:month_year).”, `over_month_pool_warning` „…puli kategorii równomiernych w tym miesiącu (:month_year).”.
- **Nie blokuje.** Przycisk zależy tylko od `processing` (`ExpenseFormDialog.vue:636`); test „expense exceeding month remaining is still saved” (`HandEntryMonthRemainingTest.php:82`).
- **Domyślna data** w trybie tworzenia: `new Date().toISOString().slice(0,10)` — data UTC klienta (`ExpenseFormDialog.vue:307`); serwer liczy `today` w UTC (`config/app.php:68`, `MonthPlanController.php:47`).

### 2. Import: obecne ostrzeżenie miesiąca

- **Przepływ** (`routes/web.php:47-50`): `POST operations/import/preview` → `CsvImportController::preview` (:46-115, sesja `CsvImportSession`) → `GET … /preview` → `showPreview` (`app/Http/Controllers/CsvImportController.php:117-150`) → `POST operations/import` → `store` (:152-357) → `ImportFinancialOperations::handle`. Ostrzeżenia miesiąca nie wchodzą do `store` ani do `canSubmit` (`OperationsImportPreview.vue:109-129`, wg badacza).
- **Propsy.** `rows` = `ImportMonthRemainingContext::annotateRows(...)` (`CsvImportController.php:139-143`), `expenseCategories` (niezarchiwizowane, z `budget_year` i `is_paced`), `monthRemainingContext` = `ImportMonthRemainingContext::forUser` (:148). Kształt kontekstu (`app/Support/CsvImport/ImportMonthRemainingContext.php:15-94`, wg badacza): `{today, budgets: [{id, year, is_draft, categories: [{id, is_paced, planned_amount_minor_units}], execution_by_category_and_month}]}`; typ TS w `resources/js/lib/import-month-remaining-warnings.ts:7-22`.
- **Martwy annotator.** `ImportMonthRemainingWarningAnnotator` wybiera domyślną kategorię wiersza (pierwszą niezarchiwizowaną kategorię wydatku roku) i liczy flagi; UI ich nie czyta (grep opisany w Summary). Testy annotatora: `tests/Feature/ImportMonthRemainingWarningTest.php:41-184`.
- **Co widzi użytkownik.** `computeImportMonthRemainingWarnings` (`OperationsImportPreview.vue:144-152`; logika `import-month-remaining-warnings.ts:94-246`, wg badacza):
  - budżet wybierany po roku daty wiersza (`budgetForYear`, `:62-67`), miesiąc z daty wiersza — wielomiesięczny i wieloletni Import jest więc już obsługiwany per wiersz;
  - liczone tylko wiersze zaznaczone, typu `expense`, z kategorią równomierną (`:75-92`, `:136-147`); kwota `Math.abs(row.amount_minor_units)` (`:149`), zgodnie z zapisem `'expense' => abs(...)`, `'refund' => -abs(...)` (`app/Actions/ImportFinancialOperations.php:249-253`);
  - wykonanie „oczekujące” kumuluje się per budżet/kategoria/miesiąc w kolejności indeksu wiersza, nie daty (`:238-242`);
  - wybór startowy `selected: false, category_id: ''` (`import-preview-rows.ts:58-95`), więc ostrzeżeń nie ma, dopóki użytkownik nie zaznaczy wiersza i nie wybierze kategorii.
- **Render.** `ImportPreviewRow.vue:142-161` i `:548-579`: dwa `<Alert variant="warning">` z tymi samymi kluczami co ręczny wpis (wg badacza).

### 3. Odczyty S-01 przydatne dla ostrzeżeń z planu

- **`MonthPlanProposal`** (`app/Support/MonthPlanProposal.php`): `forBudget(Budget, CarbonImmutable $today)` (:40-60) ładuje plany, pozycje i kategorie budżetu oraz poprzedni budżet w wykonaniu; `reading(int $month): MonthPlanReading` (:62-65) jest memoizowane (:76-81). Stany w `computePlan` (:83-122):
  - brak budżetu/planów/kategorii (możliwe tylko dla poprzedniego budżetu) → `none` (:89-91);
  - zapisany plan miesiąca → `approved` z `current_amount_minor_units` (:93-103), także dla minionego miesiąca;
  - miesiąc miniony bez zapisu → `none` (:105-107);
  - w pozostałych przypadkach → `proposal`; kategorie zarchiwizowane przed 1. dniem miesiąca są pomijane (:113-116).
  - `forBudget` nie sprawdza fazy budżetu; robią to wywołujący (`MonthPlanController.php:64`, `ApproveMonthPlan.php:233`, wg badacza). `forBudget` modyfikuje przekazany `$budget` przez `load` (:42).
- **`MonthPlanReading`** (`app/Support/MonthPlanReading.php:13-17`, wg badacza): `state`, `approvedAt`, `amounts` (`category_id → int`).
- **`MonthCategoryActuals::forMonth(Budget, int)`** (`app/Support/MonthCategoryActuals.php:16-39`): jedno zapytanie na budżet i miesiąc, `GROUP BY category_id`, `SUM(amount)` i liczba kwot > 0; nie filtruje po typie operacji (kierunek wynika z typu kategorii). Publicznie: `actual`, `hasPositiveAmount` (bool), `categoryIds`.
- **`ToSpendCalculator::toSpendMinorUnits`** (`app/Support/ToSpendCalculator.php:15-36`): dochód `+ (has_positive ? actual : plan)`, Bill `− (has_positive ? actual : plan)`, pozostałe wydatki `− max(plan, actual)`; Set-aside jeszcze nie jest parametrem (:11, S-02). Kopia TS `resources/js/lib/to-spend-calculator.ts:1-33` z `ToSpendItem {type, kind, plan, actual, hasPositiveAmount}` (wg badacza).
- **Wiersze odczytu** (`MonthPlanController::monthPlanPayload`, `:103-153`, wg badacza): `array_keys($reading->amounts)` ∪ `$actuals->categoryIds()`; plan wiersza spoza planu = 0. Ta sama suma zbiorów jest potrzebna przy ostrzeżeniu, plus kategoria wpisu, gdy nie ma jej w żadnym z dwóch zbiorów.
- **Koszt** (wg badacza): `forBudget` ≈ 5 zapytań bez planów, do ≈ 7 z planami w obu budżetach (test liczy tylko przypadek bez planów: `tests/Feature/MonthPlanProposalTest.php:229-248`); `MonthCategoryActuals` = 1 zapytanie na miesiąc. Import obejmujący N miesięcy z planem to N zapytań o faktyczne kwoty, o ile nie powstanie wariant grupujący po miesiącu.
- **Testy S-01**: `tests/Unit/ToSpendCalculatorTest.php`, `resources/js/lib/to-spend-calculator.test.ts`, `tests/Feature/MonthPlanProposalTest.php`, `tests/Feature/MonthCategoryActualsTest.php` (m.in. zwroty netto, transfery i ruchy kapitału wyłączone `:87`), `tests/Feature/MonthPlanPageTest.php` (m.in. miniony zatwierdzony `:130`, miniony bez planu `:113`), `tests/Feature/ApproveMonthPlanTest.php`.

### 4. Jak wpis zmienia „do wydania” (wniosek z wzoru, `ToSpendCalculator.php:15-36`)

Dla wpisu kwoty `a` w kategorii c i miesiącu m: `actual_c += a`, `positive_count_c += (a > 0)`. Skutek:

| Rodzaj kategorii c | Stan przed | Skutek wpisu |
| --- | --- | --- |
| Wydatek Bill | brak kwoty dodatniej, wkład `−plan` | pierwszy wydatek `a`: wkład `−a`; `a < plan` **podnosi** „do wydania”, obniża tylko `a > plan` |
| Wydatek Bill | była kwota dodatnia | wkład `−actual`, każdy wydatek obniża o `a` |
| Wydatek Running | wkład `−max(plan, actual)` | obniża tylko o część ponad plan |
| Dochód | brak wpływu, wkład `+plan` | pierwszy wpływ `a`: wkład `+a`; `a < plan` **obniża** |
| Zwrot (ujemny wydatek) | — | nigdy nie obniża; Bill bez kwoty dodatniej zostaje przy planie (`ToSpendCalculatorTest` „bill with only a refund…”) |
| Transfer własny, ruch kapitału | — | bez wpływu (osobne tabele; `MonthCategoryActualsTest.php:87`) |

Edycja: stara (kategoria, miesiąc) dostaje `actual −= old`, `positive_count −= (old > 0)`, nowa dostaje `+= new`. Zmiana daty albo kategorii może dotknąć dwóch miesięcy i dwóch budżetów. Odsetki z ruchu kapitału (`interest_category_id`) zapisują wpływ w kategorii dochodu (wg badacza); czy liczą się do „do wydania”, zależy od tego, czy powstaje `FinancialOperation` w budżecie — niesprawdzone.

### 5. Przypadki brzegowe

- **Budżet nie w wykonaniu:** ręczny wpis i Import odrzucają wydatki i wpływy dla budżetów poza wykonaniem (`ExpenseValidationRules.php:149-153`, `IncomeValidationRules.php:149`, `CsvImport/FinancialOperationImportValidator.php:76`, wg badacza).
- **Brak budżetu dla roku daty:** dzisiejsze obie ścieżki TS nie ostrzegają (brak budżetu); kategoria jest wymagana i należy do budżetu roku operacji (`ExpenseValidationRules.php:140-147`, wg badacza).
- **Wiersz Importu bez kategorii:** dozwolony w podglądzie (`StoreCsvImportRequest.php:26`, wg badacza); nie ma typu ani rodzaju, więc nie da się go ocenić — dziś też nie ostrzega.
- **Kategorie zarchiwizowane:** niewybieralne w formularzach (listy bez zarchiwizowanych, `OperationsController.php:153-170`), ale z operacjami w miesiącu są wierszem odczytu z planem 0.
- **Kategoria jednorazowa** (`Expense && !is_paced`, `MonthPlanController.php:187`): dziś bez ostrzeżenia miesiąca; w „do wydania” liczy się według swojego rodzaju, więc wpis może wywołać ostrzeżenie z planu.
- **Kolejność wierszy Importu:** dzisiejsze kumulowanie po indeksie; „do wydania” miesiąca nie zależy od innych miesięcy (propozycja nie czyta faktycznych kwot), więc kumulacja w obrębie miesiąca daty wystarcza.
- **Źródło `today`:** serwer UTC, walidacja `before_or_equal:today` w strefie aplikacji (UTC), domyślna data klienta UTC.

### 6. Dokumenty i reguły

- **`CONTEXT.md`**:
  - To Spend `:79-81`: „May be negative and blocks nothing; Own Transfer and Capital Movement do not change it. Below zero it raises an `exceeded` Dashboard notice.”; „Plan means Current Month Plan, or Proposal before approval.”; polski tekst „do wydania”.
  - Month Plan `:63-65`: „“The month has a plan” includes a Proposal.”
  - Month Remaining `:55-57`: czytany tylko dla minionych miesięcy bez planu; dla bieżącego, przyszłych i minionych z planem zatwierdzonym — To Spend.
  - Negative Balance Warning `:151-153`: ostrzeżenie bez blokady.
  - Brak terminu ani progu dla ostrzeżenia przy wpisie lub w Imporcie (sprawdzona sekcja `## Language`).
- **ADR-0033** (`.docs/adr/0033-month-plan-beside-annual-budget.md:7`): do czasu S-06/S-07/S-08 nadpisane dla miesięcy z planem m.in. FR-TRX-08 i FR-REP-13 w `.docs/prd.md` oraz ostrzeżenie „over Month Remaining” w `.ai/rules/js.md`. Archiwum F-01 przypisuje S-08: `.docs/prd.md:191` (FR-TRX-08) i `.ai/rules/js.md:24` (`context/archive/2026-11-02-month-plan-domain-decisions/plan.md:39`); FR-REP-13 → S-06, `dashboard-hierarchy.md` → S-06/S-07, ADR-0031 → S-07.
- **`.docs/prd.md:191`** FR-TRX-08: „Przed zapisaniem wydatku system pokazuje aktualny Available Limit i przewidywany limit po operacji. Dla Paced Category pokazuje także Month Remaining przed operacją i po niej.” Poza listą ADR-0033, ale o tym samym ostrzeżeniu: `.docs/prd.md:345` (zejście Month Remaining poniżej zera jako ostrzeżenie bez blokady), `:417-418`, `.docs/design-system.md:29`, `:51`. Żaden wymóg FR-IMP (`.docs/prd.md:244-272`) nie opisuje ostrzeżeń podglądu Importu.
- **`.ai/rules/js.md:24`**: „Warnings that do not block saving (over limit, over Month Remaining, negative balance, import duplicates) use <Alert variant="warning"> or bg-warning/10.” `:10-11`: `destructive` = przekroczenie (m.in. ujemne Month Remaining). `.ai/rules/support.md:10` (termin i progi w `CONTEXT.md` przed kodem) dotyczy powiadomień Dashboardu.
- **`AGENTS.md:28-32`**: zmieniać obie kopie i oba testy razem; nowe odczyty liczyć na serwerze i przekazywać propsami, kopię TS dodawać tylko, gdy podgląd musi reagować przed zapisem, z uzasadnieniem w planie.
- **Tłumaczenia** (`lang/pl/ui.php`): `operations.expense_warnings_title` (:538), `over_limit_warning` (:539), `over_month_category_warning` (:540), `over_month_pool_warning` (:541), `negative_balance_warning` (:542); `month_plan.to_spend` „Do wydania” (:281). Klucza ostrzeżenia „do wydania” jeszcze nie ma. Ręczny typ `UiTranslations` w `resources/js/types/shell.ts` wymaga dopisania nowych kluczy (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:663`).

## Code References

- `app/Http/Controllers/OperationsController.php:180` — prop `handEntryMonthRemaining`
- `app/Support/HandEntryMonthRemainingInputs.php:12-72` — wejścia ręcznego podglądu
- `resources/js/lib/hand-entry-month-remaining-preview.ts:157-321` — ręczny podgląd (bez testów)
- `resources/js/components/ExpenseFormDialog.vue:200-276`, `:587-618` — wywołanie i render ostrzeżeń
- `app/Http/Controllers/CsvImportController.php:117-150` — propsy podglądu Importu
- `app/Support/CsvImport/ImportMonthRemainingContext.php:15-130` — kontekst i domyślna kategoria
- `app/Support/CsvImport/ImportMonthRemainingWarningAnnotator.php` — flagi nieczytane przez UI
- `resources/js/lib/import-month-remaining-warnings.ts:94-246` — podgląd Importu (bez testów)
- `resources/js/pages/OperationsImportPreview.vue:144-152` — przeliczanie przy zmianie wyboru
- `resources/js/components/ImportPreviewRow.vue:142-161`, `:548-579` — render ostrzeżeń w wierszu
- `app/Support/MonthRemainingCalculator.php`, `resources/js/lib/month-remaining-calculator.ts` — obecny wzór
- `app/Support/MonthPlanProposal.php:40-122` — stany planu miesiąca
- `app/Support/MonthCategoryActuals.php:16-56` — faktyczne kwoty miesiąca
- `app/Support/ToSpendCalculator.php:15-36`, `resources/js/lib/to-spend-calculator.ts:1-33` — wzór „do wydania”
- `app/Http/Controllers/MonthPlanController.php:103-195` — składanie wierszy odczytu
- `app/Concerns/ExpenseValidationRules.php:24`, `app/Concerns/IncomeValidationRules.php:24` — daty nie w przyszłości
- `tests/Feature/HandEntryMonthRemainingTest.php`, `tests/Feature/ImportMonthRemainingWarningTest.php` — obecne testy ostrzeżeń

## Architecture Insights

- **Dzisiejszy wzorzec ostrzeżeń**: serwer podaje surowe wejścia dla wszystkich budżetów w wykonaniu, klient liczy wynik w TS przy każdej zmianie formularza. Ostrzeżenie z planu musi reagować na zmianę daty, kategorii i kwoty przed zapisem, więc spełnia warunek `AGENTS.md` dla kopii TS; kopia wzoru „do wydania” w TS już istnieje (`to-spend-calculator.ts`), więc wystarczy podać wejścia `ToSpendItem` per miesiąc.
- **Zbiór miesięcy z planem jest ograniczony** (wniosek z §Summary): bieżący miesiąc plus minione miesiące z zapisanym planem. Serwer może więc podać wejścia „do wydania” dla tych miesięcy, a klient rozstrzygnąć: miesiąc w zbiorze → ostrzeżenie z planu, poza nim → obecne ostrzeżenia `Pozostało w miesiącu`. Liczba minionych zatwierdzonych miesięcy rośnie z czasem; plan może ograniczyć ją do budżetów w wykonaniu (jak dziś `HandEntryMonthRemainingInputs`).
- **Ostrzeżenie roczne (`over_limit_warning`, Available Limit) i ujemne saldo** pochodzą z `previewHandEntryWarnings` i nie są objęte FR-014; nic w sprawdzonych dokumentach nie każe ich zmieniać.
- **Wzorzec testów**: kalkulatory mają parę testów PHP+TS z przykładem kanonicznym; podglądy TS nie mają testów. Wydzielenie decyzji „plan czy Month Remaining” do czystej funkcji TS daje miejsce na `*.test.ts` obok kodu.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-and-to-spend/research.md:53` — „Czterokrotna duplikacja logiki ostrzeżeń Month Remaining”: **potwierdzone** (cztery pliki istnieją); nowe: wynik annotatora PHP nie trafia do UI.
- `context/archive/2026-11-02-month-plan-and-to-spend/research.md:58` — kotwice `OperationsController.php:180` (**potwierdzona**), `HandEntryMonthRemainingInputs.php:165-211` (**nieaktualna**, logika jest w `:27-72`), `CsvImportController.php:139-148` (**potwierdzona**).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:62`, `:80` — S-01 zostawił ostrzeżenia bez zmian i przekazał je S-08: **potwierdzone** (`OperationsController` i `CsvImportController` nie czytają odczytów planu; grep `MonthPlanProposal` poza stroną planu bez trafień wg badacza).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:661-666` (Addenda) — limit kwot planu 10^12 gr i blokada zatwierdzania M, gdy M−1 jest propozycją: blokada dopuszcza zatwierdzenie M, gdy M−1 minął bez planu, więc minione miesiące z zapisem mogą mieć luki; ostrzeżenia muszą rozstrzygać plan per miesiąc, nie per zakres.
- `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:39` — przypisanie dokumentów do S-06/S-07/S-08: **potwierdzone** w ADR-0033:7.
- `context/foundation/roadmap.md:192` — „Logika ostrzeżeń istnieje dziś w dwóch kopiach”: **częściowo** — dwie pary (kalkulator PHP/TS) plus dwa podglądy TS i martwy annotator PHP.
- `context/foundation/shape-notes.md:197` — FR-014 bez „także niezatwierdzoną propozycję”; PRD `:138` to dodaje. Obowiązuje PRD.

## Related Research

- `context/archive/2026-11-02-month-plan-and-to-spend/research.md` — stan kodu dla S-01.

## Open Questions

Wybory produktowe dla `/10x-plan` (kod ich nie rozstrzyga):

1. **Które wpisy ostrzegają z planu?** Dziś tylko wydatek w kategorii równomiernej. Wzór „do wydania” obniża też: wydatek w kategorii jednorazowej, pierwszy wpływ mniejszy od planu (dochód) i edycję zmieniającą kategorię albo datę. FR-017 mówi tylko „gdy do wydania zejdzie poniżej zera” (`context/foundation/prd.md:147-148`).
2. **Warunek: „po < 0” czy „przed ≥ 0 i po < 0”?** Dzisiejsze ostrzeżenie miesiąca pokazuje się przy każdym `after < 0`, także gdy było już ujemne (wg badacza `:317-320`). PRD nie rozstrzyga.
3. **Pokazywać „do wydania” przed i po** (jak FR-TRX-08 dla Month Remaining), czy samo ostrzeżenie? Wpływa na brzmienie nowego FR-TRX-08.
4. **Kolor ostrzeżenia:** `variant="warning"` (`js.md:24`, `design-system.md:51`, ostrzeżenia bez blokady) czy `destructive` jak `exceeded` na Dashboardzie (`CONTEXT.md:81`, `js.md:10-11`).
5. **Martwy annotator Importu:** usunąć razem z testem (wymaga zgody — usuwanie testów), przepisać na flagi z planu, czy zostawić. `AGENTS.md` każe liczyć odczyty na serwerze; dziś liczy klient.
6. **Kumulacja w Imporcie**: per wiersz w kolejności indeksu (jak dziś) czy ostrzeżenie o stanie miesiąca po wszystkich zaznaczonych wierszach.

Luki dowodowe (nieblokujące):

- Parzystość kalkulatora `MonthRemainingCalculator` PHP/TS nie była sprawdzana linia po linii.
- Liczba zapytań `MonthPlanProposal::forBudget` z planami w obu budżetach (≈ 7) jest szacunkiem badacza; test sprawdza tylko przypadek bez planów.
- Czy odsetki z ruchu kapitału tworzą `FinancialOperation` w kategorii dochodu (i czy formularz ruchu kapitału powinien ostrzegać) — niesprawdzone.
- Kotwice oznaczone „wg badacza” pochodzą z raportów agentów podrzędnych; sam sprawdziłem `MonthPlanProposal.php:36-130`, `MonthCategoryActuals.php`, `ToSpendCalculator.php:10-40`, `CsvImportController.php:130-150`, walidację dat, grep flag annotatora i konsumentów podglądów TS.
