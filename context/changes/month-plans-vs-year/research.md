---
date: 2026-11-02T14:39:12+01:00
researcher: Claude Code (claude-opus-5-5)
git_commit: 36cabd183c5ee239ed9bedb7363fe99e8482c817
branch: worktree-month-plans-vs-year
repository: sebastian-kostecki/household-treasurer
topic: "S-10 month-plans-vs-year: co kod daje i co trzeba rozstrzygnąć, aby porównać plany miesięcy z budżetem rocznym (FR-010)"
tags: [research, codebase, month-plan, annual-budget, execution, budget-page, fr-010]
status: complete
last_updated: 2026-11-02
last_updated_by: Claude Code (claude-opus-5-5)
---

# Research: S-10 plany miesięcy a budżet roczny

**Date**: 2026-11-02T14:39:12+01:00
**Researcher**: Claude Code (claude-opus-5-5)
**Git Commit**: 36cabd183c5ee239ed9bedb7363fe99e8482c817
**Branch**: worktree-month-plans-vs-year
**Repository**: sebastian-kostecki/household-treasurer

## Research Question

Wycinek S-10 z `context/foundation/roadmap.md:207-217`: „użytkownik może porównać plany miesięcy z budżetem rocznym” (FR-010). Ryzyko z roadmapy: porównanie nie może zmieniać budżetu rocznego. Reguła z PRD (`context/foundation/prd.md:125-126`): „dla każdej kategorii pokazywane jest wydane w roku plus plan bieżącego miesiąca względem planu rocznego”.

Pytania badawcze:

1. Co ustalono w domenie dla S-10, a co zostawiono otwarte?
2. Jakie dane planu miesiąca istnieją w kodzie (zapis, propozycja, stany) i czy coś już sumuje plany miesięcy?
3. Skąd wziąć „wydane w roku” i „plan roczny” na kategorię, i czy da się je uciąć na miesiącu?
4. Gdzie porównanie może trafić w UI i jakie kontrakty (propsy, testy, reguły) dotyka?

## Summary

- **Domena ustala trzy rzeczy, resztę zostawia otwartą.** Ustalone: suma planów miesięcy może różnić się od roku i „ta różnica ma być widoczna” (`.docs/adr/0033-month-plan-beside-annual-budget.md:3`, `context/foundation/prd.md:197`); budżet roczny się nie zmienia (`prd.md:73,93`, `roadmap.md:216`); reguła FR-010 z jednym zdaniem (`prd.md:126`). Nie ustalono miejsca w UI, kolumn, znaczenia „wydane w roku”, udziału kategorii dochodów, wersji planu rocznego (początkowy czy bieżący) ani tego, który miesiąc jest „bieżący”. Wyszukiwanie w `CONTEXT.md`, `.docs/`, `context/foundation/` i trzech folderach `context/archive/2026-11-02-*` nie dało żadnej decyzji o miejscu ani kolumnach. Roadmapa podaje dla S-10 „Unknowns: —” (`roadmap.md:215`), choć te pytania są otwarte.
- **„Wydane w roku” z licznika liczy bieżący miesiąc dwa razy.** Roczne Execution to zapisany licznik `categories.execution_minor_units`, zmieniany na ścieżkach operacji w `app/Actions/ManageFinancialOperation.php:212-246` (ścieżki `user:restore` nie sprawdzono), bez możliwości ucięcia na dacie. Wszystkie 7 reguł daty operacji dochodu i wydatku w `app/` ma `before_or_equal:today`: dodanie i edycja (`app/Concerns/ExpenseValidationRules.php:24,67`, `app/Concerns/IncomeValidationRules.php:24,67`), Import (`app/Support/CsvImport/FinancialOperationImportValidator.php:115,147`) i przebudowa (`app/Concerns/OperationRebuildValidationRules.php:31`), więc w budżecie bieżącego roku licznik obejmuje już operacje bieżącego miesiąca. Licznik plus plan bieżącego miesiąca policzy wydatki tego miesiąca w obu składnikach. Odczyt bez podwójnego liczenia potrzebuje Execution sprzed miesiąca, a takie cięcie daje dziś tylko `BudgetMonthExecutionIndex` (tylko wydatki).
- **Nic nie sumuje planów miesięcy ani nie zestawia ich z rokiem.** Jedynym czytelnikiem planów jest `MonthPlanProposal`, który trzyma je według miesiąca i nie sumuje (raport z przeszukania `app/` i `resources/js`; odczyt planu w `app/Support/MonthPlanProposal.php:83-122`). Budżet, Dashboard i inne kontrolery nie czytają planów miesięcy. Porównanie to nowy odczyt.
- **Propozycje nie są zapisywane.** Wiersz `month_plans` istnieje tylko po zatwierdzeniu (`approved_at` NOT NULL, `database/migrations/2026_11_02_091919_create_month_plans_table.php:14-21`). Odczyt każdego miesiąca jest liczony w locie jako `approved`, `proposal` albo `none` (`MonthPlanProposal.php:94-121`); miniony miesiąc bez zatwierdzenia to `none` z pustymi kwotami (`:105-107`). Publiczne `MonthPlanProposal::reading(int $month)` (`:62`) daje odczyt dowolnego miesiąca budżetu, więc kwotę planu dla porównania można wziąć bez nowej logiki propozycji.
- **„Plan roczny” w UI to Current Plan.** Pasek „Plan roczny” na stronie Budżet pokazuje sumy `current_plan_minor_units` (`resources/js/components/BudgetPlanSummaryStrip.vue:36,44,52`), a średnia w propozycji też liczy z Current Plan (`MonthPlanProposal.php:188-197`). Initial Plan istnieje osobno (`app/Http/Controllers/BudgetController.php:233-235`). PRD nie rozstrzyga, z którym porównywać.
- **Miejsce w UI to wybór produktowy.** Strona Budżet ma roczną tabelę kategorii (Plan początkowy, Plan bieżący, Wykonanie, Pozostały limit) i wybór miesiąca, który zmienia tylko pasek miesiąca. Strona „Plan miesiąca” ma wiersze kategorii z Plan / Faktycznie dla wybranego miesiąca i nie ma żadnych liczb rocznych. Plan S-01 wydzielił stronę planu, żeby „nie zagęszczać strony Budżet” (`context/archive/2026-11-02-month-plan-and-to-spend/plan-brief.md:39`), ale nie wymienił S-10 wśród wycinków, które mają tam rosnąć.

## Detailed Findings

### 1. Co ustalono w domenie

- ADR-0033: plan miesiąca jest obok rocznego, tylko dla bieżącego i przyszłych miesięcy budżetu w realizacji; kwoty zatwierdzone nie zmieniają się po zmianie Current Plan; „the sum of the Month Plans may differ from the year, and that gap stays visible rather than being forced to zero” (`.docs/adr/0033-month-plan-beside-annual-budget.md:3`). Ta sama treść po polsku: `context/foundation/prd.md:197`, `context/foundation/shape-notes.md:104`.
- Reguła FR-010 (`prd.md:125-126`, identycznie `shape-notes.md:184-185`): kontrargument „suma planów miesięcy jest pełna dopiero w grudniu”, rozstrzygnięcie „wydane w roku plus plan bieżącego miesiąca względem planu rocznego”. Te dwa pliki są jedynymi miejscami ze sformułowaniem „wydane w roku”. Nie znaleziono „YTD”, „od początku roku”, „narastająco” w `context/`, `.docs/`, `CONTEXT.md`, `AGENTS.md`.
- Zabezpieczenia: budżet roczny działa jak dotąd (`prd.md:73`, FR-015 `prd.md:143`), „Budżet roczny po tej ścieżce nie zmienia się” (`prd.md:93`), Non-Goal „Zmiany budżetu rocznego i sald”.
- Odroczenia do S-10: w planie S-01 „**Porównanie z rokiem:** to S-10” (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:81`) i w briefie poza zakresem (`plan-brief.md:62`). Archiwa F-01 i S-02 wymieniają S-10 tylko jako „Parallel with”.
- Pokrewne, ale należące do S-09, nie do S-10: dobrane kwoty „także w skali roku” (`CONTEXT.md:96`, `prd.md:108`; przypisane S-09 w `context/archive/2026-11-02-set-aside-and-draw/research.md:50`).
- Terminy (glosariusz `CONTEXT.md`, etykiety `lang/pl/ui.php`): Initial Plan „Plan początkowy”, Current Plan „Plan bieżący” (pasek: „Plan roczny”), Execution „Wykonanie” (Budżet) / „Realizacja” (Dashboard), Available Limit „Pozostały limit” / „Do zrealizowania” dla dochodów. Current Month Plan to „plan miesiąca”, a `CONTEXT.md:73` zabrania nazywać go „plan bieżący”, bo to nazwa rocznego Current Plan. To ważne dla etykiet porównania, bo w jednym widoku spotkają się oba plany.

### 2. Dane planu miesiąca w kodzie

- Schemat: `month_plans` (`budget_id`, `month`, `approved_at` NOT NULL, `unique(budget_id, month)`) w `database/migrations/2026_11_02_091919_create_month_plans_table.php:14-21`; Set-aside w dwóch kolumnach `approved_set_aside_minor_units` / `current_set_aside_minor_units` (`2026_11_02_112841_add_set_aside_to_month_plans_table.php:15-16`); `month_plan_items` z `approved_amount_minor_units` i `current_amount_minor_units`, `unique(month_plan_id, category_id)` (`2026_11_02_091922_create_month_plan_items_table.php:14-22`).
- FR-003 (S-03) nie jest zrobione: `ApproveMonthPlan.php:63-72` zapisuje tę samą wartość do kolumn approved i current, a odczyt używa current (`MonthPlanProposal.php:99,102`). Wniosek: porównanie zbudowane na tym odczycie pokaże po S-03 plan miesiąca po zmianach, a nie plan zatwierdzony; który z nich porównywać, to wybór dla planu.
- Stan odczytu (`MonthPlanProposal::computePlan`, `app/Support/MonthPlanProposal.php:83-122`): zapisany plan → `approved`; miniony miesiąc bez planu → `none` (`:105-107`, `isPast` `:124-127`); w pozostałych przypadkach → `proposal`. Propozycja kopiuje odczyt miesiąca poprzedniego w dowolnym stanie, więc propozycje przyszłych miesięcy łańcuchowo powtarzają kwoty miesiąca bieżącego (`sourceFor`, `:129-144`). Kategoria jednorazowa ma w propozycji 0 (`proposedAmount`, `:146-167`). Bez źródła kwotą jest floor(Current Plan / 12), a grudzień dostaje resztę (`averageFromAnnual`, `:188-197`).
- Dostęp: `MonthPlanProposal::forBudget(Budget, CarbonImmutable $today)` (`:40`) ładuje plany i kategorie stałą liczbą zapytań (≤5 według `tests/Unit/MonthPlanProposalTest.php:308`, z raportu pracownika), a `reading(int $month)` (`:62`) zwraca odczyt miesiąca.
- Budżet w szkicu nie ma planów: payload planu to null (`app/Http/Controllers/MonthPlanController.php:65-67`), a zatwierdzić można tylko w realizacji (`app/Policies/BudgetPolicy.php:41-44`). Porównanie ma więc sens tylko dla budżetu w realizacji.
- Kategorie zarchiwizowane i dodane w trakcie roku: zarchiwizowana przed pierwszym dniem miesiąca wypada z propozycji (`MonthPlanProposal.php:113-116`); zatwierdzony plan nie ma filtra archiwum. Nowa kategoria ma `initial_plan_minor_units = 0` (`app/Http/Controllers/CategoryController.php:29-31`) i 0 w propozycji, gdy miesiąc poprzedni ma odczyt. Miesiąc zatwierdzony przed jej utworzeniem nie ma dla niej pozycji, więc brak pozycji trzeba czytać jako 0.

### 3. „Wydane w roku” i „plan roczny” na kategorię

| Źródło | Co daje | Typy | Cięcie na miesiącu | Kotwica |
| --- | --- | --- | --- | --- |
| `categories.execution_minor_units` | licznik roczny, po zwrotach | dochód i wydatek | nie | `app/Actions/ManageFinancialOperation.php:212-246`; payload `BudgetController.php:231,249` |
| `BudgetMonthExecutionIndex` | suma na kategorię i miesiąc; `executionInMonth` / `executionBeforeMonth` | tylko wydatek (`->where('type', FinancialOperationType::Expense)`) | tak | `app/Support/BudgetMonthExecutionIndex.php:32,37,42,71-93` (filtr `:81`) |
| `MonthCategoryActuals::forMonth` | `SUM` na kategorię w jednym miesiącu (+ licznik kwot dodatnich) | oba (bez filtra typu) | jeden miesiąc | `app/Support/MonthCategoryActuals.php:16-39` |
| `planned_amount_minor_units` | Current Plan | oba | — | `BudgetController.php:231` |
| `initial_plan_minor_units` | Initial Plan, zamrożony przy starcie realizacji | oba | — | `BudgetController.php:233-235`, `app/Actions/StartBudgetExecution.php:22-25` |

- Execution liczy każdą operację `financial_operations` w roku budżetu. Kolumny statusu zaksięgowania nie ma. Transfery własne i ruchy kapitału są w osobnych tabelach, więc nie wchodzą (raport pracownika; migracja `..._create_financial_operations_table.php:12-34`). Zwrot to wydatek ujemny i pomniejsza Execution (`app/Concerns/ExpenseValidationRules.php:25`).
- Operacja musi mieć datę w roku budżetu kategorii (`ExpenseValidationRules.php:129-143`, analogicznie Income), a na sprawdzonych ścieżkach wejścia nie może być z przyszłości (7 reguł `before_or_equal:today`, wyliczonych w Summary). Ścieżki `user:restore` nie sprawdzono pod tym kątem.
- `BudgetMonthExecutionIndex` ładuje wiersze wydatków z całego roku do PHP i nie ma bezpośredniego testu. Dla kategorii dochodów trzeba poszerzyć filtr typu albo napisać grupowane zapytanie SQL (kategoria × miesiąc). Wymaganie wydajności: odpowiedź do 2 s przy 10 000 operacji (`prd.md`, Wymagania jakościowe; `tests/Feature/Nfr/NfrPerf01Test.php`).

**Przykład: dlaczego składnik ma znaczenie.** Dane wejściowe: kategoria wydatków typu Running, Current Plan 2 400 000 (24 000,00 zł), dziś 2026-11-02, Execution styczeń–październik 2 000 000, Execution listopada do dziś 30 000, plan listopada 200 000.

- Licznik + plan miesiąca: 2 030 000 + 200 000 = 2 230 000, różnica do planu rocznego 170 000. Wydatek listopada (30 000) jest w obu składnikach.
- Execution sprzed miesiąca + plan miesiąca: 2 000 000 + 200 000 = 2 200 000, różnica 200 000.
- Execution sprzed miesiąca + reguła pozycji z To Spend (Running: większa z planu i faktycznej; Bill: plan, po pierwszej płatności faktyczna; `.docs/adr/0033-month-plan-beside-annual-budget.md:7`): tu też 2 200 000. Wynik różni się od poprzedniego tylko wtedy, gdy faktyczna przekroczy plan albo gdy rachunek zapłacono kwotą inną niż plan.

W obu ostatnich odczytach różnica nie oznacza przekroczenia: zawiera też pieniądze na miesiące po bieżącym (tu grudzień). Etykieta i podpowiedź muszą to oddać, np. „zostaje na resztę roku”.

### 4. Gdzie porównanie może trafić w UI

- **Strona Budżet** (`BudgetController::show`, `app/Http/Controllers/BudgetController.php:32-128`; `resources/js/pages/Budget.vue`):
  - Tabela kategorii w realizacji: Kategoria | Plan początkowy | Plan bieżący | Wykonanie | Pozostały limit / Do zrealizowania (`resources/js/components/BudgetCategoryTableHeader.vue:36-74`). Na desktopie `ListSurface`, na telefonie karty `md:hidden`, plus zwinięta sekcja zarchiwizowanych (`resources/js/components/BudgetCategoryList.vue`, z raportu pracownika).
  - Miesiąc z `?month=` (`app/Support/BudgetViewedMonth.php`) zmienia dziś tylko pasek miesiąca (`BudgetMonthStrip`).
  - Kontrakt ma lukę: propsy `month_remaining_minor_units` / `month_execution_minor_units` są wysyłane dla wierszy wydatków (`BudgetController.php:268-271`) i sprawdzane w testach, ale brak ich w typie `BudgetCategoryRow`, a `pacedPoolMonthRemainingMinorUnits` jest zadeklarowany (`Budget.vue:77`) i nieużywany. S-06 zajmie to miejsce, a jego pytanie o „do wydania” na stronie Budżet jest otwarte (`roadmap.md:166-167`).
- **Strona „Plan miesiąca”** (`app/Http/Controllers/MonthPlanController.php`; `resources/js/pages/MonthPlan.vue`):
  - Każdy wiersz kategorii ma siatkę 3 kolumn: Kategoria / Plan / Faktycznie (`MonthPlan.vue:505-624`).
  - Wiersze to suma kategorii z odczytu i kategorii z operacjami w miesiącu (`MonthPlanController.php:119-127`).
  - Propsy wiersza (`itemRow`, `:195-208`; typ `MonthPlan.vue:29-66`) nie mają żadnych liczb rocznych.
  - S-04 (FR-008) doda tu resztę rocznego limitu dla kategorii jednorazowych (`prd.md:118-119`), więc roczne liczby i tak trafią na tę stronę.
  - Plan jest edytowalny przed zatwierdzeniem i liczony na żywo przez TS (`to-spend-calculator.ts`). Porównanie liczone z planu przed zatwierdzeniem musiałoby albo reagować na wpisane kwoty (kopia TS), albo pokazywać kwoty z propsów.
- **Nawigacja:** pasek boczny ma pozycje Budżet i Plan miesiąca (`resources/js/components/AppSidebar.vue:26-64`, import `@/routes` w `:28`). Trasy `budget` (`routes/web.php:23`) i `month-plan` (`routes/web.php:28`).
- **Dashboard** zawsze pokazuje bieżący rok i miesiąc kalendarzowy (`app/Http/Controllers/DashboardController.php:31-33,104-109`; ADR-0031) i nie linkuje do planu miesiąca.

### 5. Kontrakty i reguły, które porównanie dotknie

- Propsy Inertii: zmiana po obu stronach w jednym commicie oraz `assertInertia` w teście Feature (`AGENTS.md`, „Inertia props contract”). Testy do rozszerzenia: `tests/Feature/BudgetTest.php`, `tests/Feature/BudgetMonthRemainingTest.php` (strona Budżet), `tests/Feature/MonthPlanPageTest.php` (strona planu); to klasy PHPUnit `extends TestCase` (raport pracownika).
- Logika miesiąca żyje dwa razy (`AGENTS.md`, „Month logic lives twice”): nowe odczyty liczyć na serwerze i przekazywać w propsach; kopia TS tylko wtedy, gdy podgląd musi reagować przed zapisem, z uzasadnieniem w planie.
- UI: kolory tylko z tokenów, destructive znaczy „ponad limit”, warning „blisko”; kwoty przez `formatMoney`, wyrównane do prawej, `tabular-nums`; ponowne użycie `ListSurface` i `CategoryChip` (`.ai/rules/js.md`); nowy kolor jako token w `:root` i `.dark` (`.ai/rules/css.md`).
- Sloppy: nowe zapytania w pętli i możliwe N+1 są zgłaszane w CI (`AGENTS.md`, „Sloppy”). Porównanie ma wziąć Execution w jednym zapytaniu na budżet, a nie na kategorię.
- Snapshot: porównanie jest odczytem i nie dodaje danych, więc `user:backup` / `user:restore` (wersja 5, `app/Actions/BackupUserData.php:18`) nie wymaga zmian. Wniosek obowiązuje tylko wtedy, gdy plan nie doda nowych kolumn.

## Code References

- `.docs/adr/0033-month-plan-beside-annual-budget.md:3,5,7` — model planu obok roku, reguła propozycji, wzór To Spend
- `context/foundation/prd.md:125-126` — FR-010 i rozstrzygnięcie sokratejskie
- `context/foundation/roadmap.md:207-217` — wycinek S-10
- `app/Support/MonthPlanProposal.php:40,62,83-122,129-197` — dostęp, odczyt miesiąca, stany, źródło propozycji, średnia z rocznego
- `app/Support/MonthPlanReading.php:10-19` — stany `proposal` / `approved` / `none`
- `app/Support/BudgetMonthExecutionIndex.php:32-93` — Execution na kategorię i miesiąc, tylko wydatki
- `app/Support/MonthCategoryActuals.php:16-39` — faktyczne kwoty jednego miesiąca, oba typy
- `app/Actions/ManageFinancialOperation.php:212-246` — licznik `execution_minor_units`
- `app/Concerns/ExpenseValidationRules.php:24,67,129-143` — data ≤ dziś, rok operacji = rok budżetu
- `app/Http/Controllers/BudgetController.php:32-128,226-273` — propsy strony Budżet i wiersza kategorii
- `app/Http/Controllers/MonthPlanController.php:57-69,115-166,195-208` — propsy strony planu
- `resources/js/components/BudgetPlanSummaryStrip.vue:36,44,52` — „Plan roczny” = Current Plan
- `resources/js/components/BudgetCategoryTableHeader.vue:36-74` — kolumny tabeli rocznej
- `resources/js/pages/MonthPlan.vue:29-66,505-624` — typ propsów i wiersze planu
- `database/migrations/2026_11_02_091919_create_month_plans_table.php:14-21`, `2026_11_02_091922_create_month_plan_items_table.php:14-22` — schemat planu

## Architecture Insights

- Plan miesiąca jest odczytem liczonym w locie z małego zbioru zapisanych planów, a roczne Execution to licznik denormalizowany. Porównanie łączy te dwa światy i potrzebuje trzeciego odczytu: Execution według miesiąca dla obu typów kategorii.
- Projekt liczy odczyty na serwerze (`MonthPlanProposal`, `BudgetMonthReadings`, `MonthCategoryActuals`) i przekazuje gotowe liczby w propsach. Porównanie pasuje do tego wzorca jako nowa klasa w `app/Support`, karmiona jednym zapytaniem grupowanym.
- `BudgetMonthExecutionIndex` ma czterech wywołujących: `BudgetMonthReadings:32`, `HandEntryMonthRemainingInputs:44`, `ImportMonthRemainingContext:65`, `ImportMonthRemainingWarningAnnotator:322` (raport pracownika). Poszerzenie go o dochody zmieni zbiór danych tych wywołujących; bezpieczniej dodać osobne zapytanie albo parametr typu.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:81` i `plan-brief.md:62` — porównanie z rokiem jawnie odłożone do S-10 (potwierdzone).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan-brief.md:39` — osobna strona planu, żeby nie zagęszczać strony Budżet; S-04/S-05/S-09 mają tam rosnąć (potwierdzone; S-10 niewymienione).
- `context/archive/2026-11-02-month-plan-and-to-spend/research.md:43` — `execution_minor_units` to „bieżąca suma roczna” (potwierdzone: licznik w `ManageFinancialOperation.php:212-246`).
- `context/archive/2026-11-02-month-plan-domain-decisions/reviews/plan-review.md:25-41` — wybór floor(Current Plan / 12) dla propozycji zamiast „reszta / miesiące do końca” (potwierdzone w `MonthPlanProposal.php:188-197`). Dotyczy propozycji, nie porównania.
- `context/foundation/roadmap.md:233` — wiersz „Czeka na S-01” jest nieaktualny, bo S-01 ma status `done` (`roadmap.md:45`).

## Related Research

- `context/archive/2026-11-02-month-plan-and-to-spend/research.md` — S-01, plan miesiąca i To Spend
- `context/archive/2026-11-02-set-aside-and-draw/research.md` — S-02, odkładam / dobieram

## Open Questions

Wybory produktowe dla `/10x-plan` (właściciel: użytkownik):

1. **Co znaczy „wydane w roku”?** (a) Execution sprzed bieżącego miesiąca + plan miesiąca; (b) to samo, ale składnik miesiąca według reguły pozycji z To Spend (Bill/Running); (c) licznik roczny + plan miesiąca, z podwójnym liczeniem bieżącego miesiąca (przykład w sekcji 3). Wariant (c) jest najtańszy, ale liczy wydatki bieżącego miesiąca dwa razy.
2. **Który miesiąc jest „bieżący”?** Miesiąc kalendarzowy czy miesiąc wybrany przez `?month=` na stronie? Dla wybranego miesiąca przyszłego Execution sprzed miesiąca zawiera tylko część miesięcy pośrednich. Dla minionego miesiąca bez planu (`none`) nie ma kwoty planu.
3. **Gdzie pokazać porównanie?** Strona Budżet (tabela roczna i karty mobilne), strona „Plan miesiąca” (wiersze kategorii, gdzie S-04 doda roczny limit) czy osobna sekcja albo strona.
4. **Czy kategorie dochodów wchodzą?** „Dla każdej kategorii” obejmuje oba typy, ale „wydane” sugeruje wydatki. Dochody wymagają Execution według miesiąca bez filtra typu.
5. **Plan roczny: Current Plan czy Initial Plan?** UI nazywa Current Plan „Plan roczny”, a propozycja liczy z Current Plan.
6. **Jak pokazać różnicę „ma być widoczna”?** Kwota „zostaje na resztę roku” (plan roczny − wydane − plan miesiąca), procent czy znak przekroczenia. Czy wiersz Set-aside należy do porównania?
7. **Kategorie zarchiwizowane i jednorazowe:** czy porównanie pokazuje zarchiwizowane (strona Budżet ma dla nich zwiniętą sekcję) i jak czytać jednorazowe z planem miesiąca 0?

Luki faktów (niezablokowane):

- Nie sprawdzono, czy `user:restore` przywraca `execution_minor_units` jako zapisaną wartość, czy przelicza ją z operacji (`RestoreUserData.php`), ani czy restore omija regułę daty ≤ dziś. Ma to znaczenie tylko wtedy, gdy porównanie zestawi licznik z sumą według miesięcy.
- Nie zmierzono kosztu `BudgetMonthExecutionIndex` (ładuje wiersze wydatków roku do PHP) przy 10 000 operacji.
