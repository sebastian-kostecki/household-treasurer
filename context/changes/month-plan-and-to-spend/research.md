---
date: 2026-11-02T09:11:41+01:00
researcher: Claude (claude-sonnet-5-5)
git_commit: 59c7fdc972a70dba5878f9069839a5d3c21b43d4
branch: change/month-plan-and-to-spend
repository: household-treasurer
topic: "S-01 month-plan-and-to-spend: co w obecnym kodzie plan miesiąca i „do wydania” dotykają, wykorzystują albo muszą zachować"
tags: [research, codebase, month-plan, to-spend, budget, category, snapshot, dashboard, month-remaining]
status: complete
last_updated: 2026-11-02
last_updated_by: Claude (claude-sonnet-5-5)
---

# Research: S-01 — plan miesiąca i „do wydania”: stan kodu

**Date**: 2026-11-02T09:11:41+01:00
**Researcher**: Claude (claude-sonnet-5-5)
**Git Commit**: 59c7fdc972a70dba5878f9069839a5d3c21b43d4 (lokalny commit worktree; nie sprawdzano, czy jest na remote, więc kotwice są lokalne `plik:linia`)
**Branch**: change/month-plan-and-to-spend
**Repository**: household-treasurer

## Research Question

Użytkownik uruchomił `/10x-research month-plan-and-to-spend` bez osobnego pytania. Folder zmiany miał tylko `change.md`, więc pytanie wyprowadzono z roadmapy `context/foundation/roadmap.md:98-108` (S-01: „użytkownik może zobaczyć propozycję planu, poprawić ją, zatwierdzić i odczytać «do wydania»”; PRD: US-01, FR-001, FR-002, FR-005, FR-015, FR-018, FR-019).

Pytanie robocze: **co w kodzie na tym commicie S-01 dotyka, może wykorzystać albo musi zachować, i które fakty z ADR-0033 / PRD nie mają dziś pokrycia w kodzie albo są niejednoznaczne.** Badanie nie wybiera projektu rozwiązania; to zadanie `/10x-plan`.

## Summary

1. **Kodu planu miesiąca nie ma.** Przeszukanie `app`, `database`, `resources`, `routes`, `tests`, `lang` pod kątem `item_kind`, `MonthPlan`, `month_plan` nie dało trafień (badacz oraz mój własny `grep -rIl` po `app database resources/js routes tests lang`, 2026-11-02; `resources/views` nie sprawdzano osobno). Istnieje tylko `is_paced` na `categories` (migracja `2026_09_10_195354_add_is_paced_to_categories_table.php`) i dwie migracje tworzące `budgets` oraz `categories`; **żadnej tabeli planu per miesiąc nie ma**. Roczny plan to jedna liczba `categories.planned_amount_minor_units`.
2. **Model danych jest przyjazny dla nowej tabeli potomnej.** `budgets` ma `unique(user_id, year)`; `categories.budget_id` ma `cascadeOnDelete` (`2026_09_07_180001_create_categories_table.php:17`). Jeśli nowe tabele będą miały FK do `budgets` z `cascadeOnDelete`, `ResetUserData` (`app/Actions/ResetUserData.php:14-26`, usuwa `budgets` jednym `delete()`) usunie je bez zmian; bez kaskady trzeba dodać krok usuwania przed `budgets`.
3. **Reguła „średnia z rocznego” z ADR-0033 ma w kodzie gotowy odpowiednik arytmetyczny**, ale prywatny: `MonthRemainingCalculator.php:81-90` (`floorDivide(plan,12)`, miesiące 1–11 = floor, grudzień = `plan − 11×floor`).
4. **Operacje dochodów i wydatków leżą w jednej tabeli `financial_operations`; przelewy własne i ruchy kapitału w osobnych** (`own_transfers`, `capital_movements`), a `FinancialOperationType` ma tylko `Income` i `Expense` (`app/Enums/FinancialOperationType.php`). Dlatego zapytanie wyłącznie po `financial_operations` samo wyklucza transfery i ruchy kapitału, zgodnie z wymogiem „nie zmieniają liczby”.
5. **Istniejący indeks wykonania miesiąca nie wystarczy dla „do wydania”.** `BudgetMonthExecutionIndex` (`app/Support/BudgetMonthExecutionIndex.php:71-93`) sumuje wyłącznie wydatki, per kategoria i miesiąc, w PHP; nie niesie informacji „pierwszy wydatek dodatni” (potrzebnej dla Bill) ani „pierwszy wpływ” (dochód).
6. **Snapshot wymaga podbicia wersji do 5.** `BackupUserData::SNAPSHOT_VERSION = 4` (`app/Actions/BackupUserData.php:17`); przywracanie przyjmuje `[1,2,3,<stała>]` (`app/Actions/RestoreUserData.php:76`), więc wersja 4 jest dopuszczona tylko przez stałą, a po jej podbiciu wersja 4 **wypadnie z listy**, jeśli nie dopisze się jej jawnie.
7. **Są trzy rozbieżności albo luki dokumentów względem kodu** (sekcja Open Questions): unikalność nazwy kategorii, „minione miesiące z planem” (ADR vs PRD), oraz zakres Month Item Kind dla dochodów.

## Detailed Findings

### 1. Model danych: Budget, Category, plan roczny

- `budgets`: kolumny `user_id`, `year`, `phase`; `unique(['user_id','year'])` (`database/migrations/2026_09_07_180000_create_budgets_table.php:14-22`, raport badacza). `BudgetPhase` ma tylko `Draft` i `Execution` (`app/Enums/BudgetPhase.php`). ADR-0033 ogranicza plan miesiąca do budżetu w wykonaniu; kod ma `isExecution()` (`app/Models/Budget.php:158-166`).
- `categories`: `budget_id` (kaskada), `type` (`CategoryType::Income|Expense`), `is_paced` (bool, domyślnie true), `planned_amount_minor_units` (bigint), `initial_plan_minor_units`, `execution_minor_units` (bieżąca suma roczna), `archived_at`. Indeksy `(budget_id,type)` i `(budget_id,type,sort_order)` są nieunikalne (`2026_09_07_180001_create_categories_table.php:29-30`, sprawdzone osobiście: w pliku migracji nie ma `unique`).
- **Tożsamość kategorii między latami:** nie ma. `CONTEXT.md:36` stwierdza, że kategoria na innym budżecie to inny rekord, a dopasowanie po nazwie i typie to zapytanie. Ponieważ w sprawdzonych migracjach `categories` nie ma `unique(budget_id,name,type)` (to samo potwierdza raport o walidacji w `CategoryValidationRules.php:200`, niepowtórzone przeze mnie), zapytanie „grudzień poprzedniego budżetu dla kategorii o tej samej nazwie i typie” **może zwrócić więcej niż jeden wiersz** (także zarchiwizowany duplikat).
- `CreateBudget::copyCategories` (`app/Actions/CreateBudget.php:36-48`, sprawdzone): kopiuje `name, type, icon, color, sort_order, is_paced, planned_amount_minor_units`, zeruje plan początkowy i wykonanie, **źródło to tylko kategorie niezarchiwizowane** (`whereNull('archived_at')`, raport badacza). Nowy Month Item Kind ma być kopiowany obok `is_paced` (linia 44) — ADR-0033 wprost to wskazuje.
- Gdzie powstają i zmieniają się kategorie: `CategoryController::store` (`:22`, `is_paced` wymuszone na `true` dla dochodu), `update` (`:46`, biała lista pól `$updatableFields` `:48-58`), `archive` (`:84`), `CategoryValidationRules.php:200-239` (`is_paced` `['sometimes','boolean']`, `withPacedFlagValidation` odrzuca flagę dla dochodu i zarchiwizowanej), `CategoryFormDialog.vue` (pole `is_paced` w typie `:33-41`, checkbox tylko dla wydatku `:268-275`). `StartBudgetExecution` (`app/Actions/StartBudgetExecution.php:18-31`) przepisuje plan do planu początkowego i zmienia fazę — naturalne miejsce przejścia „budżet wszedł w wykonanie”.
- `CategoryPolicy::update` nie sprawdza fazy; kategoria w budżecie wykonywanym może więc zmienić `planned_amount_minor_units` w trakcie roku. Konsekwencja dla ADR-0033 („zatwierdzone kwoty nie zmieniają się po zmianie planu rocznego”): kwoty propozycji muszą być wyliczone w chwili utworzenia planu i zapisane, nie liczone na żywo. To wniosek z lektury, nie zaobserwowane zachowanie.
- Izolacja danych: kategoria nie ma `user_id`; własność to `$category->budget->user_id` (`CategoryPolicy::ownsCategory`, raport badacza `:178`). Bez globalnych scope'ów w `app/` (wynik grepa badacza). Nowy model planu z `budget_id` dziedziczy własność przez budżet; polityka wzorowana na `BudgetPolicy::ownsBudget`.

### 2. Odczyty miesiąca: kalkulator, konsumenci, duplikaty

- `MonthRemainingCalculator` to czysta klasa bez dostępu do bazy; trzy gałęzie: szkic (`:81-90`), nadrabianie (`:92-102`), „plaster” dla przyszłości (`:104-121`) (`app/Support/MonthRemainingCalculator.php`). TS: `resources/js/lib/month-remaining-calculator.ts` ma tę samą logikę (`:54-97`). Testy: `tests/Unit/MonthRemainingCalculatorTest.php` (8 testów) i `resources/js/lib/month-remaining-calculator.test.ts` (11 testów) — liczby wg raportu badacza.
- **Czterokrotna duplikacja logiki ostrzeżeń Month Remaining** (wg badacza): kalkulator PHP+TS, `hand-entry-month-remaining-preview.ts` (TS), `ImportMonthRemainingWarningAnnotator` (PHP), `import-month-remaining-warnings.ts` (TS). Dwa pliki podglądu nie mają testów (potwierdza `AGENTS.md`, sekcja „Month logic lives twice”). Dotyczy to S-08, nie S-01, ale S-01 nie może tych odczytów zepsuć dla miesięcy bez planu.
- Gdzie `Pozostało w miesiącu` / `Wynik` trafiają do Inertia:
  - Dashboard: `DashboardController.php:57-142`; props `paced_pool_month_remaining_minor_units`, `current_month` ustawiane tylko gdy budżet jest w wykonaniu i istnieje widoczna kategoria paced (`:96-111`, sprawdzone osobiście). Typy: `Dashboard.vue:87-88`; kafelek: `showMonthRemainingTile` (`Dashboard.vue:119-124`), liczba kafelków 3 albo 2 zależy od tej flagi.
  - Powiadomienie `paced_month_remaining`: `app/Support/DashboardNotices.php:83-104` z `DashboardPacedCategoryMonthAlerts`; waga `Exceeded`.
  - Strona Budżet: `BudgetController::show` (`:33-130`); `Wynik` z `BudgetMonthResult::forViewedMonth` (`app/Support/BudgetMonthResult.php:19-37`, grupuje `financial_operations` po `type` bez filtra kategorii). Prop `pacedPoolMonthRemainingMinorUnits` jest typowany w `Budget.vue:76-82`, ale wg badacza **nigdzie nie jest renderowany** (zgodne z notatką roadmapy: strona Budżet go nie wyświetla). Pola per kategoria `month_remaining_minor_units` / `month_execution_minor_units` też nie mają odczytu w Vue (wynik grepa badacza).
  - Operacje ręczne: `OperationsController.php:180` → `HandEntryMonthRemainingInputs.php:165-211` → `ExpenseFormDialog.vue:200-255`. Import: `CsvImportController.php:139-148`.
- **Znaczenie „bieżącego miesiąca”:** Dashboard używa `CarbonImmutable::today('UTC')` i budżetu z roku `today.year`, a `config/app.php:68` ustawia strefę UTC (wg badacza). Strona Budżet bierze rok z sesji (`SelectedBudgetYear`) i miesiąc z `BudgetViewedMonth::resolve` (`app/Support/BudgetViewedMonth.php:103-127`). Kolumna daty: `financial_operations.operation_date` (typ `date`).
- Zapytania „wykonanie per kategoria i miesiąc”: `BudgetMonthExecutionIndex` (jedno zapytanie na budżet: `type=Expense`, `whereYear(operation_date, rok)`, sumowanie w PHP; sprawdzone `:71-93`). Ograniczenia dla S-01: tylko wydatki; brak flagi pierwszego dodatniego wydatku/wpływu; brak filtra archiwalnych (decyduje wywołujący). Zwroty są wierszami wydatku z ujemną kwotą (`ManageFinancialOperation.php:62-65`, `ImportFinancialOperations.php:252` wg badacza), więc sumowanie ze znakiem netuje zwroty — zgodnie z definicją „refunds netted” w `CONTEXT.md`.
- Indeksy `financial_operations`: `(user_id, operation_date)`, osobne `category_id`, `budget_id`; brak indeksu złożonego `(category_id, operation_date)` (wg badacza). Istniejące zapytania miesięczne używają `whereYear`/`whereMonth`, a nie zakresu dat.

### 3. Snapshot `user:backup` / `user:restore`

- Wersja: `BackupUserData::SNAPSHOT_VERSION = 4` (`:17`, sprawdzone). `RestoreUserData::readSnapshot` (`:72-95`, sprawdzone): `in_array($version, [1,2,3,BackupUserData::SNAPSHOT_VERSION], true)`; wymagane klucze dla wszystkich wersji: `source, accounts, budgets, categories, financial_operations, own_transfers`; dla wersji 2, 3 i bieżącej także `term_deposits`; dla 3 i bieżącej `capital_movements`. **Po podbiciu stałej do 5 wersja 4 przestanie przechodzić walidację**, o ile lista nie zostanie rozszerzona o 4 (wniosek z odczytu tych linii).
- Tolerancja starszych danych działa po kluczu, nie po wersji: `is_paced` ma domyślną wartość przez `restoredCategoryIsPaced` (`:344-351`, sprawdzone: dla nie-wydatku `true`, dla wydatku `?? true`); `bank` i `end_date` lokat tak samo (wg badacza). Wzorzec na Month Item Kind: `$categoryData['month_item_kind'] ?? <domyślna>`, domyślna „bieżąca” wg PRD FR-018.
- Kolejność odtwarzania w jednej transakcji (wg badacza, `RestoreUserData::handle :21-47`): reset → konta → lokaty → budżety → kategorie → operacje → przelewy → ruchy kapitału; mapy `[stareId => noweId]`. Plan miesiąca (potrzebuje map budżetów i kategorii) wejdzie po kategoriach.
- Eksport kategorii: `BackupUserData.php:138-156` (lista pól wg badacza); trzeba dodać Month Item Kind i `#[Fillable]` w `app/Models/Category.php`.
- **Potencjalna luka odtwarzania poza zakresem S-01, ale wzorzec do uniknięcia:** `import_identity_account_id` na operacjach i przelewach jest zapisywany bez przemapowania ID (`RestoreUserData.php:245,279,283`, sprawdzone), podczas gdy ruchy kapitału go mapują (`:312-331`). Skutek w bazie (naruszenie FK albo wskazanie na obce konto) nie został zweryfikowany.
- Testy: `tests/Feature/UserSnapshotCommandTest.php` (339 linii wg badacza) — brak plików fixtur; wersje 1 i 3 budowane w teście przez mutację JSON; **brak testu wersji 2 i brak testu specyficznego dla wersji 4**; asercja `version === 4` w `:43`. `CategoryPacedFlagTest.php:221-269` pokrywa okrągłą podróż i brak flagi. `ResetUserDataCommandTest` pokrywa czyszczenie.
- Dokumenty z numerem wersji do aktualizacji: `README.md:106` („wersja 4”), `.docs/prd.md:304-305` (FR-ADM-01/02 mówi „JSON wersji 4” i „starsze wersje 1–3”) — wg badacza.

### 4. Strony, trasy, UI i konwencje

- Trasy: `routes/web.php` w grupie `auth`+`verified`; `dashboard`, `budget`, trasy kategorii; brak trasy planu miesiąca. Wzorzec strony+kontrolera+FormRequest+Wayfinder: `Budget.vue` + `CategoryController` + `UpdateCategoryRequest` + `CategoryFormDialog.vue` (formularz `<Form v-bind="CategoryController.update.form(category)">`, pole kwoty jako widoczny `Input` + ukryty `input name="*_minor_units"`; brak wspólnego komponentu pola kwoty). Wayfinder generowany jest do katalogów ignorowanych przez git (`.gitignore:10-12`, sprawdzone) — w świeżym worktree wymaga `npm run build` (`AGENTS.md`).
- Nawigacja: sztywna tablica `mainNavItems` w `AppSidebar.vue:30-52`, etykiety z `lang/pl/ui.php` (`nav`). Powiadomienia Dashboardu: serwer w `DashboardNotices.php` (`severity` ustala serwer), klient `DashboardNotices.vue` z unią typów; `Warning` istnieje w enumie `DashboardNoticeSeverity`, ale nic go nie emituje (ADR-0032, `support.md`). `<CategoryChip>` w komponencie zakłada `icon` i `color`, więc rodzaj powiadomienia niebędący kategorią (np. „brak zatwierdzonego planu”) wymaga nowej gałęzi (wg badacza; to dotyczy S-07, nie S-01).
- Kwoty: `App\Support\MinorUnits::fromMajorInput` obsługuje liczby ujemne (potrzebne dla znakowanego Set-aside), `toMajorDisplay`; TS: `formatMoney`, `minorUnitsToMoneyInput`, `parseMoneyInputToMinorUnits` (`resources/js/lib/format.ts`). Reguły UI w `.ai/rules/js.md` (tylko tokeny kolorów, `formatMoney`, `ListSurface`, `CategoryChip` itd.) i `.ai/rules/css.md`.
- Testy: mieszany styl — klasy PHPUnit (`BudgetTest`, `DashboardTest`) i closures Pest (`HandEntryMonthRemainingTest`); `assertInertia` do asercji propsów; `travelTo` do czasu; brak testów przeglądarkowych (brak `tests/Browser`, Dusk/Playwright). Fabryki `Budget::factory()->execution()`, `Category::factory()->expense()|income()|oneOff()|archived()` — fabryka kategorii ustawia `is_paced => true` w `CategoryFactory.php:60`, tam też trafi domyślny Month Item Kind. Fabryka operacji nie aktualizuje `categories.execution_minor_units` ani sald (wg badacza).

## Code References

- `app/Actions/CreateBudget.php:36-48` — kopiowanie kategorii na nowy budżet; `is_paced` w linii 44; źródło: tylko niezarchiwizowane.
- `app/Actions/StartBudgetExecution.php:18-31` — przejście do wykonania.
- `app/Support/MonthRemainingCalculator.php:81-90` — arytmetyka „średniej z rocznego” z resztą w grudniu (prywatna).
- `app/Support/BudgetMonthExecutionIndex.php:71-93` — wykonanie wydatków per kategoria/miesiąc.
- `app/Support/BudgetMonthResult.php:19-37` — `Wynik` miesiąca.
- `app/Http/Controllers/DashboardController.php:96-111` — warunek wyliczenia Month Remaining na Dashboardzie.
- `resources/js/pages/Dashboard.vue:119-124` — kafelek Month Remaining i liczba kolumn nagłówka.
- `app/Actions/BackupUserData.php:17` — `SNAPSHOT_VERSION = 4`.
- `app/Actions/RestoreUserData.php:76,86,90` — lista wersji i wymagane klucze; `:344-351` — domyślna `is_paced`.
- `app/Actions/ResetUserData.php:14-26` — kolejność usuwania; kategorie znikają kaskadą z `budgets`.
- `database/migrations/2026_09_07_180001_create_categories_table.php:17,29-30` — kaskada, brak unikalności.
- `app/Enums/FinancialOperationType.php` — tylko `Income` i `Expense`.
- `context/foundation/prd.md:187-208` — wzór „do wydania” i rozstrzygnięcia domenowe.

## Architecture Insights

- **Dzielenie odczytów:** `AGENTS.md` każe liczyć nowe odczyty miesiąca na serwerze i dodawać kopię TS tylko tam, gdzie podgląd musi reagować przed wysłaniem. Strona planu miesiąca (poprawianie kwot) z natury potrzebuje podglądu „do wydania” w trakcie edycji; czy to wymaga kopii TS, jest decyzją planu, którą `AGENTS.md` każe uzasadnić.
- **„Do wydania” nie jest przechowywane** (ADR-0033; `CONTEXT.md`), więc jego źródłem są: zapisane kwoty planu + operacje miesiąca. Brakuje dziś składnika „pierwszy wpływ / pierwszy dodatni wydatek w miesiącu per kategoria”.
- **Dwa światy odczytów współistnieją** (stary Month Remaining dla minionych miesięcy bez planu, nowy To Spend): Dashboard i Budżet mają obecnie jeden kanał propsów (`paced_pool_month_remaining_minor_units`); odczyt „do wydania” w S-01 wymaga albo nowych propsów obok, albo zmiany znaczenia istniejących — decyzja dla planu i S-06.
- **Wzorzec domyślnych wartości przy przywracaniu to tolerancja po kluczu** (`?? default`), nie rozgałęzienie po wersji; spójny z tym wzorcem jest nowy Month Item Kind.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-domain-decisions/plan-brief.md` — F-01: nowy ADR-0033 + terminy w `CONTEXT.md`; zapisuje, że reguła propozycji Set-aside „jest celowo otwarta do S-02; S-01 do tego czasu przyjmuje Set-aside = 0” oraz że „przechowywanie propozycji i zaokrąglanie” są poza zakresem F-01, czyli należą do S-01. Dokument zakłada też numerację 0033 kolidującą z lokalną gałęzią `feat/backfill` (czytane z briefu, nie sprawdzane).
- `.docs/adr/0033-month-plan-beside-annual-budget.md` — model; wprost zastępuje klauzulę ADR-0024 o „drugim budżecie miesięcznym” i wymienia dokumenty nadpisane do czasu S-06/S-07/S-08 (`.docs/prd.md` FR-TRX-08 i FR-REP-13, `dashboard-hierarchy.md`, ADR-0031, `.ai/rules/js.md`).
- `.docs/adr/0032-dashboard-notice-severity.md` — wagi powiadomień; to S-07, nie S-01.
- Weryfikacja kodu względem ADR-0033, osobno dla każdego twierdzenia: odwołanie do `MonthRemainingCalculator.php:81-90` — poparte; odwołanie do `CreateBudget.php:44` — poparte; „Dashboard always shows the current calendar month (ADR-0031)” — częściowo: kod bierze `today->month` (`DashboardController.php:106`), ale ADR-0031 dotyczy roku kalendarzowego (wg badacza), nie miesiąca.

## Related Research

Brak: w `context/changes/**` i `context/archive/**` nie znaleziono innych `research.md` (archiwum zawiera jedną zmianę, F-01, bez `research.md`; sprawdzono listing katalogu).

## Open Questions

Nierozstrzygnięte fakty i wybory dla `/10x-plan` (żaden nie jest rozstrzygnięty tym badaniem):

1. **Unikalność nazwy i typu kategorii w budżecie.** Brak ograniczenia unikalności w migracji (sprawdzone); jak ma zachować się reguła „grudzień poprzedniego budżetu dla tej samej nazwy i typu”, gdy pasuje więcej wierszy, albo gdy pasujący wiersz jest zarchiwizowany (`CreateBudget` ich nie kopiuje)? Wybór produktowy/techniczny.
2. **„Minione miesiące z zatwierdzonym planem”.** ADR-0033 mówi „Past months stay without a Month Plan” i jednocześnie „Month Remaining stays only for past months without a Month Plan”; PRD FR-013 i `prd.md:189` mówią wprost o minionych miesiącach z zatwierdzonym planem (podsumowanie i końcowe „do wydania”). Najprostsze zgodne czytanie: „minione” w pierwszym zdaniu to miesiące minione w chwili wprowadzenia funkcji. Nie ma potwierdzenia w tekście; wpływa na to, co S-01 musi pokazać dla miesiąca, który minął po zatwierdzeniu (domena S-06/S-09).
3. **Month Item Kind dla dochodów.** `CONTEXT.md:75-77` mówi, że kategoria dochodu go nie ma („income always counts like Bill”), a PRD FR-019 mówi o „kategorii” bez ograniczenia typu. Kolumna na `categories` będzie więc dla dochodu nieistotna albo wymuszona; reguła walidacji analogiczna do `withPacedFlagValidation` jest decyzją planu.
4. **Mapowanie `is_paced` ↔ Month Item Kind.** To dwie niezależne cechy wg `CONTEXT.md:75`. Czy domyślne `Running` ma być ustawiane niezależnie od `is_paced` dla istniejących kategorii (PRD FR-018: tak, „bieżąca”)? Dotychczasowa praktyka kopiowania flagi sugeruje kopiowanie także rodzaju.
5. **Gdzie i kiedy powstaje rekord propozycji.** ADR-0033 mówi, że plan miesiąca „istnieje” dla bieżącego i przyszłych miesięcy, a „zawsze istnieje propozycja”. Czy propozycja jest wyliczana leniwie przy odczycie (bez zapisu) czy zapisywana (dla „wyliczone w chwili tworzenia”, patrz Detailed Findings §1)? Brief F-01 przypisuje przechowywanie propozycji i zaokrąglanie do S-01.
6. **Rok budżetu a „bieżący miesiąc”.** Dashboard tylko dla roku kalendarzowego; strona Budżet dla roku z sesji. Gdzie ma leżeć strona planu miesiąca i który rok/miesiąc obsługuje (przyszłe miesiące tego roku? następny rok, jeśli ma budżet w wykonaniu?) — nie sprawdzono interakcji `ShellState` / `SelectedBudgetYear` z nową trasą.
7. **Podbicie wersji snapshotu a lista wersji.** Po `SNAPSHOT_VERSION = 5` trzeba rozszerzyć listę w `RestoreUserData.php:76` o 4, inaczej stracimy zgodność wstecz wymaganą przez PRD (wniosek z odczytu kodu; test tego nie istnieje).
8. **Zakres kopii TS.** Czy podgląd „do wydania” na stronie planu reaguje przed wysłaniem (kopia TS + test, zgodnie z `AGENTS.md`), czy odświeża się przez Inertia po zapisie (tylko serwer).
9. **Nie zbadano:** `.docs/specs/dashboard-hierarchy.md`, `.docs/prd.md` FR-TRX-08 / FR-REP-13, `DashboardPacedCategoryMonthAlerts`, `resources/js/types/shell.ts` (czy klucze tłumaczeń są typowane), fabryk poza `Category`. Wydajność zapytań na 10 000 operacji (wymaganie jakościowe PRD) nie była mierzona; uwaga o `whereYear` i indeksach pochodzi z odczytu schematu.
