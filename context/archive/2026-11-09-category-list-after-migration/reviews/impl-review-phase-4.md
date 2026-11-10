<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Category List After Migration

- **Plan**: context/changes/category-list-after-migration/plan.md
- **Scope**: Phase 4 of 7
- **Reviewed phases**: 4
- **Date**: 2026-11-09
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warnings, 5 observations
- **Triage**: F1, F2, F3, F5 fixed; F4 accepted; F6 skipped

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Success criteria (run during the review, commit 2c2c76a)

| Item | Result |
|------|--------|
| 4.1 Full suite on PostgreSQL (`php artisan test --compact`, Sail, `DB_CONNECTION=pgsql`) | PASS — 1026 passed, 2 skipped, 7661 assertions |
| 4.2 New tests (filter by id joins 2 years + survives rename; operation without a line in the date's year rejected; month plan rejects category outside the budget) | PASS — `OperationsFilterTest::test_category_filter_joins_the_years_by_category_id`, `::test_category_rename_does_not_split_the_history`, `ExpenseTest::test_expense_is_refused_for_a_category_without_a_line_in_the_year_of_the_date`, `ChangeMonthPlanTest::test_route_rejects_a_category_with_no_line_in_the_budget` |
| 4.3 `grep category_name\|category_type` in app/Http, app/Support, resources/js/{lib,components,composables} | PASS — empty |
| 4.4 `npm run test` / `types:check` / `check:ui` | PASS — Vitest 448/448, vue-tsc and check-ui-literals clean |
| 4.5 E2E `command-palette.spec.ts` + `seed.spec.ts` | PASS — 12 passed, but only with `E2E_PORT=8123`: port 8000 is held by another app on this machine, and `reuseExistingServer` made the first run log in to that app (429 page) and fail in `auth.setup.ts`. Environment, not code. |
| `vendor/bin/sloppy ci` | PASS — 0 findings, 100/100 |
| 4.6 Manual (marked `[x]`) | Not re-verifiable from the diff; links, expense and Import paths are covered by the feature tests above. Accepted. |

## Findings

### F1 — Import: wybór kategorii szukany po samym id w liście wszystkich lat

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/import-month-remaining-warnings.ts:75-92 (`isPacedExpenseSelection`), wywołanie :140
- **Detail**: `expenseCategories` to opcje ze wszystkich lat, a po fazie 4 `id` to id kategorii listy, więc to samo id występuje raz na rok. `.find(candidate => candidate.id === selection.category_id)` bierze pierwszy rok, a `is_paced` jest flagą pozycji danego roku. Gdy wiersz jest z 2027, a pozycja 2026 nie jest „odkładana”, ostrzeżenie „reszta miesiąca” jest po cichu pomijane, zanim dojdzie do właściwej kontroli per rok w `budget.categories.find` (:142). Przed fazą 4 id było unikalne per rok, więc to nowy skutek zmiany przestrzeni id. Reszta selektorów filtruje po `budget_year` przed użyciem (`ExpenseFormDialog`, `IncomeFormDialog`, `import-preview-rows.ts:162-175`).
- **Fix**: Zawęzić do roku wiersza (`candidate.budget_year === year`) albo usunąć wstępny filtr i zostawić `budget.categories.find`, które i tak sprawdza `is_paced`; dodać test Vitest z tym samym id w dwóch latach i różnym `is_paced`.
- **Decision**: FIXED (Fix now) — `isPacedExpenseSelection` dostaje rok wiersza i szuka po `id` + `budget_year`; nowy `import-month-remaining-warnings.test.ts` (czerwony przed poprawką, zielony po); importy modułu zamienione na względne, żeby test mógł go wczytać. Vitest 450/450, `npm run check` i `types:check` czyste. Zmiany niezacommitowane.

### F2 — Ciche zastąpienie id listy id pozycji w EntryToSpendInputs

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/EntryToSpendInputs.php:96
- **Detail**: `$listCategoryIds->get($categoryId, $categoryId)` przy braku wpisu zwróciłoby id pozycji jako id listy — dokładnie ten błąd przestrzeni nazw, który faza usuwa. Dziś martwa gałąź (`$budget->budgetLines` zawiera też archiwalne, a każde id pochodzi z pozycji tego budżetu), więc nic nie wyzwoli, ale zamaskowałaby przyszłą regresję. Wyjście poza plan.
- **Fix**: Zamienić na `$listCategoryIds[$categoryId]`, żeby brak wpisu przerywał głośno.
- **Decision**: FIXED (Fix now) — `EntryToSpendInputs.php:96` czyta `$listCategoryIds[$categoryId]`. Testy EntryToSpend/HandEntry/MonthPlanPage/CsvImport/Dashboard na PostgreSQL 219 zielonych, Pint i Sloppy czyste. Niezacommitowane.

### F3 — BudgetLineResolver bez testu bezpośredniego i z martwym kodem

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Support/BudgetLineResolver.php:668 (`forBudget`), app/Support/CategoryAppearance.php (`pickerOption`)
- **Detail**: Plan wymienia `forYear` i `forBudget`; `forBudget` nie ma żadnego wywołującego (a jako jedyna metoda nie sprawdza użytkownika), `CategoryAppearance::pickerOption` ze zmienionym id też nie ma wywołań. `forYear` i `amountsByLine` są pokryte tylko pośrednio testami Expense/ChangeMonthPlan/ApproveMonthPlan; Approve ma jedynie asercję o kategorii innego użytkownika, nie „spoza budżetu”.
- **Fix**: Dodać mały test `BudgetLineResolver` (`forYear` inny użytkownik/rok, `amountsByLine` z brakującą kategorią) i usunąć `forBudget`, jeśli S-19 go nie potrzebuje (S-19 będzie dołączać pozycje przy pierwszym użyciu — wtedy zostawić i dopisać test).
- **Decision**: FIXED (Fix now) — nowy `tests/Feature/BudgetLineResolverTest.php` (6 testów, PostgreSQL zielone, Pint czysty) pokrywa `forYear`, `forBudget` i `amountsByLine`. Martwy kod (`forBudget`, `CategoryAppearance::pickerOption`) zostaje do S-19. Niezacommitowane.

### F4 — Resolver wołany per wiersz Importu

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/CsvImport/FinancialOperationImportValidator.php:49, app/Actions/ManageFinancialOperation.php:213
- **Detail**: `forYear` robi ok. 3 zapytania (podzapytanie `whereHas`, `with('budget')`, autoload `category`) i jest wołany osobno w walidacji wiersza i przy zapisie, obok istniejących zapytań `exists`. Nie jest to regresja co do rodzaju (wcześniej `BudgetLine::find` per wiersz), a `NfrPerf01Test` przeszedł w pełnym zestawie. Przy dużym pliku warto zbudować mapę `[rok][category_id] => pozycja` raz na import (jak `ImportCategorySuggester::lineIdsByYearAndCategory`).
- **Fix A ⭐ Recommended**: Zostawić; wrócić do tego, gdy S-23/S-19 zmienią Import (mapa roku i tak będzie potrzebna do dołączania pozycji).
  - Strength: brak ryzyka w zaakceptowanej fazie; wydajność jest w granicach NFR.
  - Tradeoff: koszt zapytań rośnie liniowo z liczbą wierszy.
  - Confidence: MED — `NfrPerf01Test` zielony, ale mierzy mały plik.
  - Blind spot: czasu importu na pliku ok. 1 000 wierszy nie mierzono.
- **Fix B**: Zbudować mapę raz na import i przekazać do walidatora.
  - Strength: usuwa N zapytań z walidacji.
  - Tradeoff: zmiana API walidatora poza zakresem fazy 4.
  - Confidence: MED — wzorzec istnieje w suggesterze.
  - Blind spot: nie sprawdzono, czy `ImportFinancialOperations` zachowa spójność mapy po zapisie.
- **Decision**: ACCEPTED (Fix A) — bez zmian w kodzie; wrócić do mapy `[rok][category_id] => pozycja` przy S-19/S-23.

### F5 — Zmiany spoza planu: `category_label` i dołączanie wybranej kategorii do opcji filtra

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: app/Http/Controllers/OperationsController.php:59, app/Support/OperationsFilterOptions.php:30-79, resources/js/components/CategorySelect.vue:72
- **Detail**: (1) Pole wiersza operacji `category_name` → `category_label` (spełnia kryterium 4.3; wszyscy konsumenci i test zaktualizowani). (2) `OperationsFilterOptions::categories(..., $selectedCategoryId)` dokłada wybraną kategorię, gdy nie ma operacji w danym roku — inaczej chip byłby nierozwiązywalny; pokryte testem. (3) `CategorySelect` kluczuje po `category.id`, plan mówił `budget_year + id`; bez błędu, bo selektor dostaje opcje jednego roku. Wszystkie trzy uzasadnione; w `OperationsActiveFilterChips.vue:167-168` fallback `String(category_id)` jest nieosiągalny, ale zgodny ze wzorcem `account_id`/`term_deposit_id`.
- **Fix**: Dopisać krótki addendum do planu (faza 4), żeby kolejne przeglądy traktowały je jako zamierzone.
- **Decision**: FIXED (Fix now) — addendum w `plan.md`, sekcja Phase 4 (przed Success Criteria).

### F6 — `.ai/rules/categories.md` opisuje nieistniejący już filtr po nazwie

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: .ai/rules/categories.md:23
- **Detail**: Reguła wciąż mówi, że `OperationsListQuery` filtruje po `category_name` i `category_type`. Plan przewiduje przepisanie `categories.md` na stan docelowy w fazie 6 (pkt 3 „README i reguły”), więc to nie jest pominięcie fazy 4, ale do tego czasu agent czytający regułę dostanie nieprawdziwy opis.
- **Fix**: Zostawić do fazy 6 (już zaplanowane); ewentualnie poprawić ten jeden punkt od razu.
- **Decision**: SKIPPED — reguła `categories.md` jest przepisywana w fazie 6 (pkt 3).
