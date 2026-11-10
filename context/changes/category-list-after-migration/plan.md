# Category List After Migration Implementation Plan

## Overview

Wycinek S-18 (north star M-3) z `context/foundation/roadmap.md`, PRD v3: US-01, FR-001, FR-006, FR-022. Dzisiejsze `categories` (rekord jednego budżetu z kwotami i flagami) dzielimy na kategorię listy użytkownika i Budget Line (kategoria × budżet) według ADR-0063, kroki (a)–(f), bez zmiany żadnej kwoty ani żadnego wiersza operacji. Kod przechodzi z łączenia lat po nazwie i typie na id kategorii listy, snapshot dostaje wersję 6 z konwersją v1–v5 tą samą regułą co migracja, powstaje widok „Kategorie”, a wdrożenie dostaje liczby kontrolne, `user:backup` przed migracją i prawdziwy `down()`. Przed produkcją migracja przechodzi próbę na kopii danych produkcyjnych.

## Current State Analysis

- **Schemat.** `categories` (`database/migrations/2026_09_07_180001_create_categories_table.php:15-31`): `budget_id` (FK cascade), `name`, `icon`, `color(7)`, `sort_order`, `type`, `planned_amount_minor_units` (Current Plan), `initial_plan_minor_units`, `execution_minor_units`, `archived_at`, później `is_paced` (`2026_09_10_195354_…:12`) i `month_item_kind` (`2026_11_02_090956_…:12`). Indeksy `[budget_id,type]`, `[budget_id,type,sort_order]`; **żadnej unikalności nazwy**.
- **Kto wskazuje kategorię.** Tylko `financial_operations.category_id` (FK `RESTRICT`, ma też własne `budget_id`) i `month_plan_items.category_id` (FK `CASCADE`, `unique(month_plan_id, category_id)`). Tabeli Cash Expectation nie ma (`app/Actions/ResetUserData.php:18-22` sprawdza `hasTable`).
- **Wykonanie jest zapisane**, nie liczone: `ManageFinancialOperation.php:226-244` zwiększa i zmniejsza `execution_minor_units`; `StartBudgetExecution.php:19-23` kopiuje plan do Initial Plan.
- **Model i własność.** `app/Models/Category.php` (fillable z `budget_id`, `scopeOrdered` z surowym `CASE` na `archived_at` :78-83). `CategoryPolicy.php:39-41` sprawdza własność przez `$category->budget->user_id`. `User` nie ma relacji do kategorii.
- **Zasięg.** Ok. 60 plików w `app/` czyta kategorie przez `budget_id` / `->budget` (Actions 10, Controllers 7, Requests 11, walidacja 4 pliki + `FinancialOperationImportValidator`, CsvImport 5, Support ok. 17). `is_paced` czyta ok. 17 miejsc, `month_item_kind` ok. 15. W testach 42 z 114 plików i 387 wywołań `Category::factory()->for($budget)`.
- **Sześć łączeń lat po nazwie i typie** (stoją pod liniami z `.ai/rules/categories.md`): `CreateBudget.php:29-51`, `MonthPlanProposal.php:195-210` (`matchPreviousCategory`), `OperationsListQuery.php:139-147` z wejściem `IndexOperationsRequest.php:28-29`, `OperationsFilterOptions.php:44`, `ImportCategorySuggester.php:76-128`, `ImportCategorySuggestionModel.php:61,133-160` (głosy per `type\0name`, dokładna nazwa). Do tego front: `lib/dashboard-categories.ts:34-49` (`categoryOperationsQuery`, linki z Dashboardu i palety `useCommandPalette.ts:106-114`), `DashboardNotices.vue:186-201`, `OperationsActiveFilterChips.vue:83-84`, `OperationsFilters.vue:92-100,195-205`, `lib/operations-list-query.ts:30-31`.
- **Walidacja operacji** (`ExpenseValidationRules.php:42-53,122-149`, to samo w `IncomeValidationRules`, `OperationRebuildValidationRules`, `FinancialOperationImportValidator`): `category_id` istnieje w `categories` budżetów użytkownika, nie jest archiwalna, rok budżetu = rok daty, budżet w wykonaniu.
- **UI kategorii jest na stronie Budżet.** Trasy `routes/web.php:67-74` (store/reorder na `budgets/{budget}/categories`, reszta na `categories/{category}`), `CategoryController.php` zawsze przekierowuje do `budget`. Flagi `can_*` z `BudgetController::categoryPayload` (`:196-235`). Widoku „Kategorie” nie ma; nawigacja w `composables/useAppViews.ts:19-35` (pozycja = skrót klawiszowy).
- **Selektor** `CategorySelect.vue` nie filtruje; wołający filtrują opcje po `budget_year` roku daty (`ExpenseFormDialog.vue:145-161`, `IncomeFormDialog.vue:196-203`, `lib/import-preview-rows.ts:162-176`). Opcje Operacji z budżetów w wykonaniu (`OperationsController.php:150-193`), Importu z `ImportCategoryOptions.php`.
- **Snapshot v5** (`BackupUserData.php:18,146-165`): płaska lista kategorii z `budget_id`; pozycje planu z `category_id`; operacje z `category_id` i `budget_id`. `RestoreUserData.php:23-49,79` przyjmuje 1–5, remapuje id w jednej transakcji, sprawdza, że pozycja planu należy do budżetu planu (`:263-289`). Testy starszych wersji budują JSON z factory i zdejmują klucze (`UserSnapshotCommandTest.php:118,144,226`); plików fixture nie ma.
- **Wdrożenie.** `scripts/deploy.sh:18-50`: pull (skrypt sam się aktualizuje, pierwsze uruchomienie po zmianie idzie starą wersją, README:140), build, `up -d` (nowy kod już działa), `chown`, `backup:run`, `migrate --force`, cache. Brak kroku `user:backup` i liczb kontrolnych. README „Wdrożenie i przywracanie” (`:128-227`) podaje ręczne zapytania kontrolne; zapytania 3 i 4 czytają kolumny `categories`.
- **Dane.** Produkcja (próba F-04, 2026-11-09): 1 użytkownik, 2 budżety, 77 rekordów kategorii, 1 271 operacji (max id 1 330), 38 pozycji planu; wykonanie budżetu 1 = 27 793 145. Dev ma ten sam kształt u użytkownika 1: 2026 w wykonaniu (39 rekordów, „Wakacje” zarchiwizowane i tylko w 2026) i szkic 2027 (38 rekordów skopiowanych z 2026). **W obrębie jednego budżetu nie ma duplikatu klucza nazwy.**
- **Przykład z ADR-0063 jest błędny.** „2026 #46 → Rozrywka (2)” i „Odsetki (2)” to rekordy użytkownika 2 (budżet #2), nie duplikaty w budżecie użytkownika 1. Reguła (b) się nie zmienia; test buduje przypadek „(2)” sztucznie.
- Brak `tests/Feature/Migrations`; jedyna migracja danych (`2026_09_16_091233_…`) woła Action, co reguła `migrations.md` nazywa wyjątkiem.

## Desired End State

- W bazie są `categories` (lista użytkownika: `user_id`, `name`, `name_key`, `icon`, `color`, `sort_order`, `type`, `archived_at`; `unique(user_id, type, name_key)`) i `budget_lines` (id = stare id kategorii; `budget_id`, `category_id`, plany, wykonanie, `is_paced`, `month_item_kind`, `archived_at`; `unique(budget_id, category_id)`). `financial_operations.budget_line_id` i `month_plan_items.budget_line_id` mają te same wartości co dzisiejsze `category_id`.
- Migracja wykonuje kroki (a)–(f), w swojej transakcji porównuje sumy i zgodność budżetów, a `down()` każdej migracji S-18 naprawdę ją odwraca (nazwy z listy) i ma test na PostgreSQL.
- Żaden kod poza migracją, konwersją snapshotu i unikalnością listy nie porównuje nazw. Na granicy HTTP i w propsach `category_id` to zawsze id kategorii listy; serwer sam znajduje Budget Line w budżecie (roku daty albo strony).
- Strona Budżet działa jak dziś, a zapis trafia tam, gdzie mówi ADR-0063: nazwa, ikona, kolor, typ i kolejność → kategoria listy (wszystkie lata); plan i flagi → Budget Line; archiwum globalne; „Usuń” w szkicu zdejmuje pozycję; nowa kategoria dołącza do szkiców z planem 0.
- `user:backup` pisze v6 (lista + pozycje), `user:restore` wczytuje v6 i konwertuje v1–v5 tą samą klasą co migracja.
- Widok „Kategorie” pokazuje przychody i wydatki z latami pozycji i linkiem do Operacji po id, plus zwijane archiwum z datą; ma kitchen sink i wpis w nawigacji.
- `deploy.sh` robi `user:backup` każdego użytkownika (starym kodem, przed `up -d`), liczby kontrolne przed i po migracji z porównaniem, tryb serwisowy wokół migracji; różnica zatrzymuje wdrożenie.
- Próba na kopii produkcji przeszła (migracja, liczby kontrolne, `down()`, kroki „Przywrócenie produkcji”) i jest zapisana w `context/changes/category-list-after-migration/migration-trial.md`.

Weryfikacja: test migracji i testy snapshotu zielone na PostgreSQL, bramka `composer ci:check` + `vendor/bin/sloppy ci` zielona, `npm run check:ui` i E2E zielone, próba z fazy 7 udokumentowana.

### Key Discoveries:

- Powiązanie przez Budget Line pozwala nie dotykać wartości w 1 271 operacjach i 38 pozycjach: zmienia się nazwa kolumny, a `budget_lines.id` = stare `categories.id`. Wykonanie zostaje na tym samym wierszu.
- PostgreSQL w Laravelu puszcza każdą migrację we własnej transakcji (DDL też), więc wyjątek z asercji cofa całe wypełnienie danej migracji.
- `deploy.sh` uruchamia nowy kod (`up -d`) przed migracją; `user:backup` sprzed migracji musi więc pójść w starym kontenerze, przed `up -d`, a liczby kontrolne muszą działać na obu kształtach schematu.
- Dzisiejsze głosy Importu idą po dokładnej nazwie (`ImportCategorySuggestionModel.php:61`), więc po migracji wyniki są takie same, chyba że historia ma warianty wielkości liter lub spacji (wtedy głosy się łączą — na korzyść).
- `name_key` musi liczyć jeden kod PHP (migracja, konwersja, walidacja). PostgreSQL `lower()` zależy od collation bazy, więc klucz jest zapisaną kolumną.

## What We're NOT Doing

- **Grupy, opis i rytm** (FR-002 opis, FR-004, FR-007): kolumny i UI dodają S-19, S-22 i S-24. Krok (f) jest spełniony brakiem kolumn.
- **Edycja, archiwum i przywracanie w widoku „Kategorie”** (FR-002, FR-003 w „Kategoriach”): S-19. W S-18 widok jest tylko do odczytu, a edycja zostaje na stronie Budżet. Kategorii zarchiwizowanej bez pozycji w żadnym widocznym budżecie nie da się przywrócić do czasu S-19.
- **Selektor z aktywnych kategorii listy i dołączanie przy pierwszym użyciu z operacji, Importu i planu miesiąca** (FR-019): S-19. Opcje zostają pozycjami roku jak dziś.
- **Nowa zasada tworzenia budżetu** (FR-005, zastąpienie ADR-0014 w kodzie): S-20. S-18 zostawia dzisiejszy zbiór (aktywne pozycje budżetu źródłowego z planem aktualnym), łącząc je po id.
- **Scalanie kategorii** (FR-023): S-21. Migracja niczego nie scala.
- **Pomiar trafności Importu przed/po** i porządki w danych (Otwarte pytanie 4 roadmapy): S-23. S-18 dowodzi tylko równoważności testem.
- **Mock budżetu 2025** na devie: S-29.
- **Automatyczne wdrożenie i E2E w CI**: zaparkowane w roadmapie.
- **Zmiana `RestoreApplicationBackup`** i szyfrowanie archiwum (follow-up F1 z F-04): poza zakresem.

## Implementation Approach

Expand → backfill → przełączenie czytelników → drop, w jednej gałęzi i jednym wdrożeniu (reguła `migrations.md`). Faza 1 zmienia schemat, ale zostawia stare kolumny opisu kategorii na `budget_lines`, więc kod po mechanicznym przeniesieniu na `BudgetLine` działa bez zmiany zachowania. Faza 2 dodaje format v6. Faza 3 przełącza domenę i stronę Budżet na listę i usuwa stare kolumny. Faza 4 przełącza granicę HTTP pozostałych widoków na id listy. Faza 5 to nowy widok. Faza 6 to zabezpieczenia wdrożenia, a faza 7 to próba na kopii produkcji. Kroki (a)–(f) liczy jedna czysta klasa na tablicach, używana przez migrację i przez restore v1–v5, więc oba dają ten sam wynik z definicji, a test parytetu to sprawdza.

## Critical Implementation Details

- **Kolejność w `deploy.sh`.** `user:backup` sprzed migracji musi biec w starym kontenerze (stary kod pisze v5 ze starego schematu), czyli przed `$COMPOSE up -d`. Listę użytkowników bierze z `psql` w kontenerze `pgsql` (stary kod nie ma `--all`). Liczby „przed” liczy nowy kod na starym schemacie, więc komenda nie może zależeć od modeli.
- **Skrypt aktualizuje się sam.** Pierwsze wdrożenie S-18 musi iść jako `git pull --ff-only` na serwerze, a potem `scripts/deploy.sh`; inaczej migracja przejdzie starym skryptem bez bramek. README mówi to wprost.
- **Wycofanie po wdrożeniu idzie nowym kodem.** Najpierw `artisan down` i `migrate:rollback` (ostatnia partia = migracje S-18), dopiero potem cofnięcie kodu na `main`, `git pull --ff-only` na serwerze i `deploy.sh`. Odwrotna kolejność usuwa pliki migracji, których `down()` jest potrzebny. Bez `git pull` przed skryptem pobiegnie jeszcze `deploy.sh` z S-18: jego `user:backup` w kontenerze S-18 nie znajdzie `budget_lines`, a `app:control-numbers` nie ma w cofniętym kodzie, więc `set -e` przerwie wdrożenie.
- **Snapshot v6 zrobiony po wdrożeniu nie wczyta się w starym kodzie.** Droga powrotu to `down()`; snapshoty v5 sprzed migracji są zapasem.

## Phase 1: Schemat i migracja danych

### Overview

Decyzja o kształcie tabel trafia do ADR przed kodem. Migracje tworzą listę i Budget Lines z danych bez zmiany kwot, sprawdzają się w transakcji i mają prawdziwy `down()`. Kod przechodzi mechanicznie na model `BudgetLine` (stare kolumny opisu jeszcze czytane), a restore v1–v5 buduje listę tą samą klasą co migracja.

### Changes Required:

#### 1. ADR-0065

**File**: `context/foundation/adr/0065-operations-reference-budget-line-and-reversible-category-migration.md`

**Intent**: Zapisuje decyzje planu, które doprecyzowują ADR-0063 bez zmiany jego reguł: operacja i pozycja planu wskazują Budget Line (a przez nią kategorię listy w budżecie roku); `name_key` jest zapisaną kolumną liczoną w PHP; na granicy HTTP `category_id` to id listy; migracja jest odwracalna przez `down()` z nazwami z listy; liczby kontrolne zatrzymują wdrożenie. Prostuje przykład z deva z ADR-0063 („Rozrywka (2)”, „Odsetki (2)” to rekordy innego użytkownika). Odrzucone: wskazanie wprost na kategorię listy (przepisanie 1 271 operacji), `down()` z dokładnymi nazwami (martwe kolumny), sama kopia instancji (utrata operacji).

**Contract**: Format i ton jak ADR-0063/0064; ADR-0063 bez edycji treści. Słownik (`glossary.md`, wpis Budget Line) dostaje jedno zdanie: operacja i pozycja planu wskazują Budget Line.

#### 2. Nazwa klucza i konwersja (a)–(f)

**File**: `app/Support/CategoryList/CategoryNameKey.php`, `app/Support/CategoryList/LegacyCategoryConversion.php`

**Intent**: Jedno miejsce reguł ADR-0063 na zwykłych tablicach, bez modeli i akcji, wołane przez migrację, restore v1–v5 i (klucz) walidację. Klasa konwersji jest zamrożona: docblock mówi, że zmiana zmienia znaczenie migracji, która już przeszła.

**Contract**:
- `CategoryNameKey::of(string $name): string` = trim białych znaków Unicode na brzegach + `mb_strtolower`; polskie znaki zostają.
- `LegacyCategoryConversion::convert(array $budgets, array $legacyRows, CarbonImmutable $migratedAt): array` dla jednego użytkownika. Wejście: budżety `{id, year, phase}`, wiersze `{id, budget_id, name, icon, color, sort_order, type, archived_at}`. Wyjście: `categories` (lista z kluczem tymczasowym, `name`, `name_key`, `icon`, `color`, `sort_order`, `type`, `archived_at`) i `lineCategory` (stare id → klucz tymczasowy). Kroki (b) numeracja po rosnącym id w budżecie i k = (n−1)-ta wolna liczba ≥ 2 wolna wśród oryginalnych kluczy użytkownika tego typu, skracanie do 255 znaków; (c) wygląd z najnowszego budżetu (najwyższy rok, szkic też), który ma kategorię; (d) aktywna, gdy ma niezarchiwizowaną pozycję w najnowszym budżecie albo w najnowszym budżecie w wykonaniu, inaczej `archived_at` = najpóźniejsze `archived_at` pozycji albo `$migratedAt`.

#### 3. Migracje

**File**: `database/migrations/2026_11_xx_000001_expand_categories_into_category_list_and_budget_lines.php`, `…_000002_backfill_category_list.php` (nazwy według `make:migration`)

**Intent**: Expand: zmiana nazwy `categories` → `budget_lines`, nowa tabela `categories` (lista), `budget_lines.category_id` (nullable), zmiana nazwy `financial_operations.category_id` i `month_plan_items.category_id` → `budget_line_id` (FK i indeksy idą za nazwą tabeli; nazwy ograniczeń przemianowane na nowe surowym `ALTER … RENAME CONSTRAINT` / `ALTER INDEX … RENAME` tylko za strażnikiem `DB::getDriverName() === 'pgsql'`, bo `phpunit.xml` i Sail domyślnie puszczają wszystkie migracje na SQLite). Backfill: per użytkownik `DB::table()` czyta budżety i pozycje, woła `LegacyCategoryConversion`, wstawia listę, uzupełnia `category_id`, potem `NOT NULL`, FK i `unique(budget_id, category_id)`; na końcu asercje. Stare kolumny `name`, `icon`, `color`, `sort_order`, `type` zostają na `budget_lines` do fazy 3.

**Contract**:
- `categories`: `id`, `user_id` (FK cascade), `name` (255), `name_key` (255), `icon`, `color(7)`, `sort_order`, `type`, `archived_at`, timestamps; `unique(user_id, type, name_key)`, indeks `(user_id, type, sort_order)`.
- Asercje w migracji wypełniającej (wyjątek → transakcja się cofa): każda pozycja ma `category_id`; typ kategorii = stary typ pozycji; liczba pozycji, suma wykonania, Initial i Current Plan per budżet bez zmian względem stanu sprzed wypełnienia; każda operacja i pozycja planu wskazuje pozycję w tym samym budżecie (`financial_operations.budget_id` = `budget_lines.budget_id`, budżet planu = budżet pozycji) — niezgodność w danych produkcyjnych wyjdzie tu, nie później.
- `down()` wypełnienia: usuwa `category_id` i listę; `down()` expand: przywraca nazwy tabeli, kolumn i ograniczeń. Nowe kategorie utworzone po wdrożeniu mają już wiersze w `budget_lines`, więc po `down()` stają się zwykłymi rekordami swoich budżetów.

#### 4. Modele i mechaniczne przeniesienie kodu

**File**: `app/Models/BudgetLine.php` (dawny `Category`), `app/Models/Category.php` (nowy), `Budget.php`, `User.php`, `FinancialOperation.php`, `MonthPlanItem.php`, `app/Policies/*`, wszyscy czytelnicy z `app/`, `database/factories/*`, `DemoHouseholdSeeder.php`, testy

**Intent**: Przeniesienie bez zmiany zachowania: dzisiejszy model staje się `BudgetLine` (tabela `budget_lines`, relacje `budget()`, `category()`, `financialOperations()`), nowy `Category` należy do `User` (`budgetLines()`), `Budget::budgetLines()`, `FinancialOperation::budgetLine()`, `MonthPlanItem::budgetLine()`. Czytelnicy czytają te same pola z `BudgetLine` (stare kolumny jeszcze istnieją), trasy i propsy bez zmian (id na granicy = id pozycji, jak dziś). Własność w politykach przez `budget->user_id` jak dziś.

**Contract**: `BudgetLineFactory` tworzy kategorię listy dla właściciela budżetu z tymi samymi `name`/`type`/`icon`/`color`/`sort_order` (stany `income`, `expense`, `archived`, `bill`, `oneOff` jak dziś); wywołania testów zmieniają się mechanicznie z `Category::factory()->for($budget)` na `BudgetLine::factory()->for($budget)`. Seeder tworzy listę i pozycje.

Miejsca, których nazwa modelu nie przeniesie sama (zmiana tabeli i kolumn):
- `Rule::exists('categories','id')` → `budget_lines` (8×): `ExpenseValidationRules.php:45,86`, `IncomeValidationRules.php:45,86`, `OperationRebuildValidationRules.php:133,177`, `CsvImport/FinancialOperationImportValidator.php:128,160`. Po fazie 1 `categories` to lista, więc stara reguła sprawdzałaby złą tabelę.
- Surowe złączenia: `ImportCategorySuggester.php:78,87`, `OperationsFilterOptions.php:35-36`, `MonthCategoryActuals.php:40-41` (`selectRaw('category_id, …')`).
- Kolumna `category_id` → `budget_line_id` w modelach i zapisach (`FinancialOperation`, `MonthPlanItem`, `ApproveMonthPlan`, `ChangeMonthPlan`, `ImportFinancialOperations`, `ManageFinancialOperation`, `RestoreUserData`, `BackupUserData` (klucz JSON zostaje do fazy 2), `OperationsController:58` (klucz propsa zostaje), `BudgetMonthExecutionIndex`, `MonthPlanProposal:124-125`); relacje `belongsTo`/`hasMany` z jawnym kluczem obcym, bo nazwa metody `category()` wnioskuje `category_id`.
- Klucze żądań i propsów `category_id` zostają (na granicy to nadal id pozycji do fazy 4).

Zapisy tworzące pozycję od fazy 1 (`budget_lines.category_id` jest `NOT NULL` po backfillu):
- `CategoryController::store` (`:41`): find-or-create kategorii listy po (`user_id`, `type`, `CategoryNameKey::of(name)`); gdy ta kategoria ma już pozycję w tym budżecie → błąd walidacji „Ta kategoria już jest w budżecie” (backend tej reguły z fazy 3 wchodzi tu; komunikaty w kitchen sinku zostają w fazie 3).
- `CreateBudget::copyCategories` (`:38-49`): nowa pozycja dostaje `category_id` pozycji źródłowej.
- `DemoHouseholdSeeder::createCategories` (`:249-265`): najpierw lista, potem pozycje.
- Edycja nazwy, ikony, koloru, typu i kolejności w fazach 1–2 pisze tylko stare kolumny pozycji; rozjazd z listą do fazy 3 jest akceptowany, bo dotyczy tylko deva (na produkcję fazy idą jednym wdrożeniem).

#### 5. Restore v1–v5 po zmianie schematu

**File**: `app/Actions/RestoreUserData.php`, `app/Actions/ResetUserData.php`

**Intent**: `ResetUserData` po `$user->budgets()->delete()` usuwa też `$user->categories()` (lista wisi na `user_id`, kaskada z budżetów jej nie zabiera); inaczej restore, `demo:seed --fresh` i reset w ustawieniach zostawiają starą listę, a odbudowa wpada na `unique(user_id, type, name_key)`. Test: restore na koncie z istniejącą listą. Przed wstawieniem pozycji restore liczy listę użytkownika przez `LegacyCategoryConversion` na wierszach `categories` ze snapshotu (cały plik jest wczytany w `:25`), wstawia ją, a potem pozycje (jak dziś, ze starymi kolumnami) z `category_id` z `lineCategory`, więc `UserSnapshotCommandTest` zostaje zielony bez zmiany formatu. `budget_line_id` operacji i pozycji planu remapowane jak dziś `category_id`.

**Contract**: Wersje i format bez zmian (`SNAPSHOT_VERSION = 5`); `BackupUserData` czyta `budget_lines` ze starymi kolumnami.

#### 6. Test migracji na PostgreSQL

**File**: `tests/Feature/Migrations/CategoryListMigrationTest.php`

**Intent**: Pierwszy test migracji w repo (reguła `migrations.md`): cofa migracje S-18 przez `migrate:rollback --path=<pliki S-18>` (przy `RefreshDatabase` wszystkie migracje są w partii 1, więc samo `migrate:rollback` cofnęłoby całą bazę), wstawia stary kształt przez `DB::table()`, uruchamia migracje i sprawdza niezmienniki kroku (e) co do grosza, potem `down()` i powrót.

**Contract**: Przypadki: ta sama nazwa w 2026 (wykonanie) i 2027 (szkic) → jedna kategoria; dwa rekordy o tym samym kluczu w jednym budżecie → „Rozrywka” i „Rozrywka (2)”, przy czym „Rozrywka (2)” w drugim budżecie dostaje to samo k; oryginalny rekord „Rozrywka (2)” zajmuje k = 2, więc duplikat dostaje „(3)”; nazwy różniące się wielkością liter i spacjami → jedna kategoria z pisownią z najnowszego budżetu; kategoria tylko w starszym budżecie → zarchiwizowana z najpóźniejszym `archived_at` albo z momentem migracji; „Prad” i „Prąd” → dwie; nazwa 255 znaków + sufiks → skrócona; kategoria innego użytkownika o tej samej nazwie → osobna lista; operacja z niezgodnym `budget_id` → backfill rzuca wyjątek, jego transakcja się cofa (brak listy i `category_id`), expand zostaje zapisany w partii, a `migrate:rollback` przywraca stary kształt bez zmiany danych. `down()`: po migracji dodana operacja i nowa kategoria (pozycja w 2026 i w szkicu 2027), `migrate:rollback`, stary kształt z nazwami z listy, operacja jest, kwoty bez zmian.

### Success Criteria:

#### Automated Verification:

- Test migracji przechodzi na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/Migrations`
- Testy konwersji i klucza nazwy przechodzą: `php artisan test --compact --filter=LegacyCategoryConversion`
- Pełna bramka na PostgreSQL zielona: `composer ci:check` (Sail, `DB_CONNECTION=pgsql`)
- `vendor/bin/sloppy ci` bez nowych znalezisk i `vendor/bin/pint --dirty --format agent` czyste
- `grep -rn "Category::factory()->for(\$budget" tests` nie zwraca wyników

#### Manual Verification:

- `php artisan migrate` na kopii bazy deweloperskiej (zrzut przed i po): Budżet 2026 i 2027, plan miesiąca, Operacje i Dashboard pokazują to samo co przed migracją (porównanie na oko kwot Budżetu i „do wydania”)
- ADR-0065 czyta się bez ADR-0063 w drugim oknie: decyzja, odrzucone opcje i sprostowanie przykładu są jasne

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Snapshot v6

### Overview

`user:backup` pisze nowy kształt (lista + pozycje), a `user:restore` wczytuje v6 i v1–v5 przez tę samą konwersję co migracja.

### Changes Required:

#### 1. Format v6

**File**: `app/Actions/BackupUserData.php`, `app/Actions/RestoreUserData.php`

**Intent**: Nowy kształt bez starych kolumn opisu na pozycjach; v1–v5 nadal przez `LegacyCategoryConversion` (z fazy 1). Do fazy 3 restore v6 kopiuje opis kategorii także do starych kolumn pozycji, bo kod jeszcze je czyta.

**Contract**: `SNAPSHOT_VERSION = 6`; klucze `categories` (`id`, `name`, `icon`, `color`, `sort_order`, `type`, `archived_at`) i `budget_lines` (`id`, `budget_id`, `category_id`, plany, wykonanie, `is_paced`, `month_item_kind`, `archived_at`); operacje i pozycje planu z `budget_line_id`. Wymagane dla v6: `categories`, `budget_lines`; restore odrzuca pozycję wskazującą nieznaną kategorię, kategorię innego typu niż pozycja oraz dwie pozycje tej samej kategorii w jednym budżecie. Lista akceptowanych wersji `[1,2,3,4,5,SNAPSHOT_VERSION]`.

#### 2. Testy snapshotu

**File**: `tests/Feature/UserSnapshotCommandTest.php`, `tests/Fixtures/snapshots/v5-category-list.json`

**Intent**: Pierwszy plik fixture v5 (ręcznie spisany, nie z factory, żeby nie zmieniał się z kodem) z czterema przypadkami z `.ai/rules/snapshot.md`, plus round trip v6 i test parytetu: ten sam stary kształt przez migrację i przez restore v5 daje tę samą listę i te same przypisania pozycji.

**Contract**: Przypadki fixture: ta sama nazwa w dwóch budżetach; ten sam klucz dwa razy w jednym budżecie („Rozrywka (2)”); kategoria tylko w starszym roku (archiwalna); nazwy różniące się wielkością liter lub spacjami. Istniejące testy v1–v4 dalej przechodzą.

### Success Criteria:

#### Automated Verification:

- Testy snapshotu przechodzą na PostgreSQL: `… php artisan test --compact tests/Feature/UserSnapshotCommandTest.php`
- Test parytetu migracja = restore v5 przechodzi
- Pełna bramka zielona: `composer ci:check` (PostgreSQL) i `vendor/bin/sloppy ci`

#### Manual Verification:

- `php artisan user:backup 1` na devie daje plik v6 z listą i pozycjami; `user:restore 1 <plik> --force` przywraca te same kwoty Budżetu 2026

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Lista jako źródło prawdy

### Overview

Nazwa, wygląd, typ, kolejność i archiwum globalne czyta i pisze kategoria listy. Strona Budżet działa według ADR-0063, łączenia lat w domenie idą po id, a stare kolumny opisu znikają z `budget_lines`.

### Changes Required:

#### 1. Czytelnicy opisu kategorii

**File**: wszyscy czytelnicy `name`/`icon`/`color`/`sort_order`/`type` pozycji w `app/` (Support, Dashboard, MonthPlan, CsvImport, Controllers, walidacja), `BudgetLine::scopeOrdered`

**Intent**: Opis kategorii zawsze z `budgetLine->category` (eager loading albo złączenie, bez N+1 — Sloppy). Kolejność: `categories.sort_order`, potem `name_key`, potem id; pozycje archiwalne na końcu jak dziś (`archived_at` pozycji).

**Contract**: Propsy innych stron niż Budżet bez zmiany kształtu w tej fazie.

#### 2. Strona Budżet według ADR-0063

**File**: `routes/web.php`, `app/Http/Controllers/CategoryController.php`, `app/Http/Requests/{Store,Update,UpdateCategoryMonthItemKind,ReorderCategories}*Request.php`, `app/Concerns/CategoryValidationRules.php`, `app/Policies/CategoryPolicy.php` (+ `BudgetLinePolicy`), `app/Actions/{CreateCategory,ArchiveCategory,RestoreCategory}.php` (nowe, wydzielone z `CategoryController`, gdzie ta logika dziś siedzi), `app/Actions/ReorderCategories.php`, `BudgetController::categoryPayload`, `resources/js/pages/Budget.vue`, `components/{CategoryFormDialog,BudgetCategoryList,BudgetCategoryRow,BudgetCategoryRowActions}.vue`, `lang/pl/*`

**Intent**: Te same dialogi i menu, ale id na granicy to id kategorii listy, a pozycję wskazuje para (budżet, kategoria).
- **Dodaj kategorię:** nowy klucz → kategoria listy + pozycja w tym budżecie (plan jak dziś) + pozycja z planem 0 w każdym innym szkicu. Klucz aktywnej kategorii bez pozycji w tym budżecie → dołącza istniejącą z wpisanym planem (pierwsze użycie: plan wpisany w Budżecie) i komunikatem „Dołączono istniejącą kategorię”. Klucz z pozycją w tym budżecie → błąd „Ta kategoria już jest w budżecie”. Klucz archiwalnej → błąd z podpowiedzią przywrócenia.
- **Edytuj:** nazwa, ikona, kolor, typ → kategoria listy; dialog mówi „Nazwa, ikona i kolor zmieniają się we wszystkich latach”. Plan i flagi → pozycja. Zmiana nazwy na zajęty klucz → błąd (archiwalna z podpowiedzią przywrócenia). Zmiana typu zablokowana, gdy kategoria ma operację w dowolnym roku albo pozycję w budżecie w wykonaniu; zmiana na zajęty klucz odrzucona; flagi pozycji przy zmianie na przychód wracają do domyślnych.
- **Kolejność:** przeciągnięcie ustawia `sort_order` kategorii listy podanych w kolejności 0…n−1.
- **Archiwizuj** (pozycja w budżecie w wykonaniu): `archived_at` kategorii listy; `archived_at` jej pozycji w budżecie w wykonaniu bieżącego roku kalendarzowego; usunięcie jej pozycji ze szkiców; pozycje innych budżetów bez zmian.
- **Przywróć** (kategoria listy archiwalna): czyści `archived_at` listy i pozycji w budżecie w wykonaniu bieżącego roku; dodaje pozycje z planem 0 do szkiców, gdzie ich brak.
- **Usuń** (szkic): usuwa tylko pozycję; kategoria zostaje na liście.
- **Wyrównaj plan, typ pozycji w planie miesiąca:** działają na pozycji.

**Contract**: Trasy: `POST budgets/{budget}/categories`, `PATCH budgets/{budget}/categories/reorder`, `PATCH budgets/{budget}/categories/{category}`, `PATCH budgets/{budget}/categories/{category}/month-item-kind`, `DELETE budgets/{budget}/categories/{category}`, `POST budgets/{budget}/categories/{category}/align-current-plan`, `POST categories/{category}/archive`, `POST categories/{category}/restore`. Własność kategorii listy: `$category->user_id`. Unikalność: `name_key` z `CategoryNameKey::of` + `unique(user_id, type, name_key)` jako ostatnia linia obrony. `can_change_type` z nowego predykatu, `can_restore` = kategoria listy archiwalna. Propsy Budżetu: `id` wiersza = id kategorii listy; obie strony w jednym commicie z `assertInertia`. Kitchen sink Budżetu dostaje stany komunikatów „Dołączono istniejącą kategorię”, „już jest w budżecie” i „w archiwum”.

#### 3. Łączenia lat w domenie po id

**File**: `app/Actions/CreateBudget.php`, `app/Http/Requests/StoreBudgetRequest.php`, `app/Support/MonthPlanProposal.php`, `app/Support/OperationsFilterOptions.php`, `app/Support/CsvImport/ImportCategorySuggester.php`, `app/Support/CsvImport/ImportCategorySuggestionModel.php`

**Intent**: `CreateBudget` tworzy pozycje dla tych samych kategorii co dziś (aktywne pozycje budżetu źródłowego, plan aktualny jako plan, flagi z pozycji, Initial Plan i wykonanie 0), bez kopiowania rekordów. Propozycja stycznia szuka pozycji tej samej kategorii w budżecie poprzedniego roku po `category_id`. Opcje filtra Operacji grupują po id kategorii (nazwa z listy). Import liczy głosy per kategoria listy i celuje w pozycję zwycięzcy w budżecie roku daty wiersza; próg, k = 5 i remisy bez zmian (ADR-0063 o ADR-0034). Gdy zwycięzca nie ma niezarchiwizowanej pozycji w budżecie roku daty albo jest archiwalny na liście → brak podpowiedzi, jak dziś `uniqueId` (`ImportCategorySuggester.php:122-127`); bez przejścia do następnego kandydata. Dołączenie pozycji przy zatwierdzeniu (ADR-0063 o ADR-0034) to FR-019, czyli S-19. Filtr `OperationsListQuery` po nazwie zostaje do fazy 4, ale czyta nazwę z listy.

**Contract**: Test równoważności Importu: na danych bez wariantów nazwy podpowiedzi przed (głos po nazwie) i po (głos po id) są te same dla zestawu z `ImportCategorySuggesterTest`, w tym przypadek zwycięzcy bez pozycji w roku daty (brak podpowiedzi).

#### 4. Usunięcie starych kolumn

**File**: `database/migrations/2026_11_xx_000003_drop_legacy_category_columns_from_budget_lines.php`, `app/Actions/RestoreUserData.php`, `tests/Feature/Migrations/CategoryListMigrationTest.php`

**Intent**: Gdy nic w `app/` ich nie czyta, migracja usuwa `name`, `icon`, `color`, `sort_order`, `type` z `budget_lines`; restore przestaje je pisać. `down()` przywraca kolumny z kategorii listy (nazwy z listy, decyzja 2).

**Contract**: Test migracji rozszerzony o tę migrację i jej `down()`; `grep -rn "budget_lines\.\(name\|type\|icon\|color\|sort_order\)" app` pusty.

### Success Criteria:

#### Automated Verification:

- Testy strony Budżet, kategorii i archiwum przechodzą na PostgreSQL: `… php artisan test --compact tests/Feature/BudgetTest.php tests/Feature/ArchiveCategoryTest.php` oraz `--filter=Category`
- Nowe testy: dołączanie do szkiców, dołączenie istniejącej, odrzucenie archiwalnej i zajętej nazwy, predykat typu, archiwum i przywrócenie według ADR (przykład „Rekolekcje” z marca 2027 z ADR-0063), równoważność Importu, propozycja stycznia po id
- Test migracji z migracją usuwającą kolumny i `down()` przechodzi na PostgreSQL
- Pełna bramka zielona: `composer ci:check` (PostgreSQL), `vendor/bin/sloppy ci`, `npm run types:check` po `npm run build`, `npm run check:ui`

#### Manual Verification:

- Na devie po migracji: zmiana nazwy w Budżecie 2026 widoczna w szkicu 2027; nowa kategoria w 2026 pojawia się w 2027 z planem 0; archiwizacja usuwa ją ze szkicu 2027; przywrócenie oddaje pozycję 2027 z planem 0
- Kitchen sink Budżetu pokazuje trzy nowe komunikaty w Atramencie (jasny i ciemny motyw)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Granica HTTP po id listy

### Overview

Pozostałe widoki mówią id kategorii listy: filtr Operacji, linki z Dashboardu, palety i powiadomień, selektory operacji i Importu, plan miesiąca. Serwer sam znajduje pozycję w budżecie roku daty.

### Changes Required:

#### 1. Filtr Operacji po kategorii listy

**File**: `app/Http/Requests/IndexOperationsRequest.php`, `app/Support/OperationsListQuery.php`, `app/Support/OperationsFilterOptions.php`, `resources/js/lib/operations-list-query.ts`, `components/{OperationsFilters,OperationsActiveFilterChips}.vue`, `lib/dashboard-categories.ts`, `components/{CategoryMeterRow,DashboardNotices}.vue`, `app/Support/DashboardNotices.php`, `composables/useCommandPalette.ts`, `tests/e2e/command-palette.spec.ts`

**Intent**: Filtr przez wszystkie lata po `category_id` (ADR-0063 o ADR-0016); zmiana nazwy nie dzieli historii. Linki z Dashboardu i palety niosą `category_id` (plus zakres dat roku jak dziś).

**Contract**: Parametr `category_id` (istnieje w `categories` użytkownika) zastępuje `category_name` + `category_type`; opcje filtra `{id, name, type, icon, color}`; chip szuka po id. Stare parametry w URL są ignorowane (brak przekierowania). Obie strony propsów w jednym commicie z `assertInertia`.

#### 2. Wybór kategorii w operacji, Imporcie i planie miesiąca

**File**: `app/Concerns/{Expense,Income,OperationRebuild}ValidationRules.php`, `app/Support/CsvImport/{FinancialOperationImportValidator,ImportCategoryOptions}.php`, `app/Actions/{ManageFinancialOperation,ImportFinancialOperations,RebuildOperation,ApproveMonthPlan,ChangeMonthPlan}.php`, `app/Http/Controllers/{OperationsController,MonthPlanController,CsvImportController}.php`, `app/Http/Requests/{Approve,Update}MonthPlanRequest.php`, `app/Support/BudgetLineResolver.php`, `components/{ExpenseFormDialog,IncomeFormDialog,ImportPreviewRow}.vue`, `lib/import-preview-rows.ts`, `types/month-plan.ts`, `pages/MonthPlan.vue`

**Intent**: Żądanie niesie `category_id` kategorii listy; resolver znajduje pozycję tej kategorii w budżecie roku daty (operacja, Import) albo w budżecie planu (plan miesiąca). Brak pozycji, pozycja archiwalna, budżet nie w wykonaniu → te same błędy co dziś. Opcje selektora: pozycje roku bez archiwalnych jak dziś, ale z `id` kategorii listy i `budget_year`.

**Contract**: `BudgetLineResolver::forYear(User $user, int $categoryId, int $year): ?BudgetLine` i `forBudget(Budget $budget, int $categoryId): ?BudgetLine`. Klucze `v-for` w selektorach `budget_year + id`. Wiersze planu miesiąca kluczowane `category_id` listy. Paleta: `CommandPaletteCategories` zwraca id listy. Typy TS i propsy zmieniają się razem z PHP w jednym commicie na widok, z `assertInertia`.

**Addendum (przegląd implementacji fazy 4, 2026-11-09)** — odstępstwa od powyższego, uznane za zamierzone:
- Pole wiersza operacji `category_name` nazywa się `category_label` (żeby `grep category_name` z kryterium 4.3 był pusty); konsumenci: `FinancialOperationRow.vue`, `operations-fixtures.ts`.
- `OperationsFilterOptions::categories($user, $year, $selectedCategoryId)` dokłada wybraną kategorię listy, gdy nie ma w niej pozycji w wybranym roku, żeby chip filtra zawsze znalazł nazwę.
- `CategorySelect.vue` kluczuje opcje po `category.id`: selektor dostaje opcje jednego roku, więc `budget_year + id` jest potrzebne tylko tam, gdzie lista łączy lata (`OperationsFilters` dostaje po jednej opcji na kategorię listy).
- `forBudget` i `CategoryAppearance::pickerOption` nie mają jeszcze wywołującego; zostają dla S-19.

### Success Criteria:

#### Automated Verification:

- Testy Operacji, Importu, planu miesiąca, Dashboardu i palety przechodzą na PostgreSQL: `… php artisan test --compact` (pełny zestaw)
- Nowe testy: filtr po id łączy dwa lata i przeżywa zmianę nazwy; operacja z kategorią bez pozycji w roku daty jest odrzucona; plan miesiąca odrzuca kategorię spoza budżetu
- `grep -rn "category_name\|category_type" app/Http app/Support resources/js/lib resources/js/components resources/js/composables` nie zwraca wyników
- Testy jednostkowe frontu: `npm run test` (Vitest), `npm run types:check` po `npm run build`, `npm run check:ui`
- E2E: `npx playwright test tests/e2e/command-palette.spec.ts tests/e2e/seed.spec.ts`

#### Manual Verification:

- Na devie: klik w kategorię na Dashboardzie otwiera Operacje z filtrem po kategorii; chip pokazuje nazwę; dodanie wydatku w 2026 i import pliku `tests/Fixtures/mbank-basic.csv` z podpowiedziami działa jak przed zmianą

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Widok „Kategorie”

### Overview

Nowa strona pokazuje listę kategorii przez lata, bez kwot, z latami pozycji, linkiem do Operacji i zwijanym archiwum.

### Changes Required:

#### 1. Strona i dane

**File**: `routes/web.php`, `app/Http/Controllers/CategoryController.php` (`index`), `app/Support/CategoryListReading.php`, `resources/js/pages/Categories.vue`, `resources/js/components/CategoryListRow.vue`, `lang/pl/ui.php`

**Intent**: Dwie sekcje (Przychody, Wydatki) aktywnych kategorii w kolejności listy i zwijana sekcja „Archiwum (n)” z datą archiwizacji. Wiersz: `CategoryChip` (ikona, kolor, nazwa), lata, w których kategoria ma pozycję (np. „2026 · 2027”), i link „Operacje →” do Operacji z filtrem `category_id` przez wszystkie lata. Pusty stan dla użytkownika bez kategorii. Tytuł z `Badge` „41 aktywnych”, bez akcji głównej (edycja w S-19).

**Contract**: `GET categories` → `categories.index`, Inertia `Categories` z propsami `incomeCategories`, `expenseCategories`, `archivedCategories`: `{id, name, icon, color, type, budget_years: number[], archived_at: string|null}`. Lata jednym zapytaniem (agregacja po `budget_lines` z `budgets.year`), bez pętli. Składniki z design systemu (`ListSurface`, `CategoryChip`, `IndexEmptyState`); wartości tylko z tokenów.

#### 2. Nawigacja, kitchen sink i straże UI

**File**: `resources/js/composables/useAppViews.ts`, `lang/pl/ui.php` (`nav.categories`), `routes/web.php` (`/dev/ui/categories`), `app/Http/Controllers/Dev/CategoriesKitchenSinkController.php`, `resources/js/pages/dev/CategoriesKitchenSink.vue`, `resources/js/lib/categories-fixtures.ts`, `scripts/check-ui-literals.mjs` (`FILES`), `tests/Unit/CategoriesHardcodedUiTest.php`, `resources/js/ui-contract.test.ts`, `context/foundation/design-system.md`, `tests/e2e/categories.spec.ts`, `tests/e2e/global-shortcuts.spec.ts`

**Intent**: Wpis „Kategorie” zaraz po „Budżecie”; skróty późniejszych widoków przesuwają się o jeden (test skrótów aktualizowany). Kitchen sink z siedmioma stanami (domyślny, hover, focus-visible, disabled, error, pusty, ładowanie) i stanem z archiwum. Strona „Kategorie” wpisana w design system jako rozpoznany widok.

**Contract**: Test feature `assertInertia` dla propsów, test izolacji (kategorie innego użytkownika niewidoczne), E2E: wejście z menu, widoczna „Rozrywka” z latami, rozwinięcie archiwum, link do Operacji z filtrem.

### Success Criteria:

#### Automated Verification:

- Test feature widoku i izolacji przechodzi: `… php artisan test --compact --filter=CategoriesPage`
- `npm run check:ui`, `npm run test`, `npm run types:check` po `npm run build`
- E2E: `npx playwright test tests/e2e/categories.spec.ts tests/e2e/global-shortcuts.spec.ts`
- Pełna bramka zielona: `composer ci:check` (PostgreSQL), `vendor/bin/sloppy ci`

#### Manual Verification:

- Na devie po migracji „Kategorie” pokazuje „Wakacje” w archiwum z datą 9.09.2026, a kategorie 2026 z latami „2026 · 2027”; widok czytelny na telefonie (375 px) i w ciemnym motywie
- Kitchen sink `/dev/ui/categories` pokazuje wszystkie stany

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 6: Zabezpieczenia wdrożenia

### Overview

Liczby kontrolne jako komenda, `deploy.sh` z `user:backup`, porównaniem przed/po i trybem serwisowym, README z wdrożeniem i wycofaniem S-18, reguły agenta zgodne z nowym kodem.

### Changes Required:

#### 1. Komenda liczb kontrolnych

**File**: `app/Console/Commands/ControlNumbersCommand.php`, `app/Support/ControlNumbers.php`, `tests/Feature/ControlNumbersCommandTest.php`

**Intent**: Liczby z PRD v3 i z README w jednym miejscu, działające na starym i nowym schemacie (wykrywa `budget_lines`), bez modeli. Zapis do JSON i porównanie z wcześniejszym plikiem.

**Contract**: `php artisan app:control-numbers {--output=} {--compare=}`; zawartość: liczba wierszy tabel (kategorie przed = pozycje po), max id operacji, per budżet suma kwot operacji, wykonania, Initial i Current Plan, odkładania i kwot pozycji planu, wykonanie per stare id kategorii = id pozycji, suma transferów własnych, ruchy kapitału per kierunek, salda otwarcia i bieżące kont i lokat. `--compare` drukuje różnice i kończy się kodem 1 przy dowolnej różnicy (dozwolona tylko nowa tabela `categories` listy). Test: ten sam stan przed i po migracji daje zgodne liczby; zmiana jednej kwoty daje kod 1.

#### 2. `deploy.sh`

**File**: `scripts/deploy.sh`, `scripts/lib/production-deploy.sh`

**Intent**: Przed `up -d` (stary kontener) `user:backup` każdego użytkownika z listy id z `psql`, a potem `artisan down` w starym kontenerze (plik trybu serwisowego leży w `storage`, które jest bind-mountem z `docker-compose.prod.yml:17`, więc przetrwa `up -d`; nowy kod nie obsłuży żadnego żądania na starym schemacie); po `chown` liczby „przed” do `storage/app/private/control-numbers/<timestamp>-before.json`; `backup:run`, `migrate --force`, liczby „po” z `--compare`; przy różnicy skrypt kończy się błędem i aplikacja zostaje w trybie serwisowym; przy zgodności `artisan up` i cache.

**Contract**: `set -euo pipefail` zostaje; `bash -n scripts/deploy.sh` czyste. Pierwsze uruchomienie (pierwsze wdrożenie bez działającego kontenera) pomija `user:backup` i `artisan down` w starym kontenerze z komunikatem; wtedy `artisan down` idzie zaraz po `up -d`.

#### 3. README i reguły

**File**: `README.md` („Wdrożenie i przywracanie”), `.ai/rules/categories.md`, `.ai/rules/migrations.md`, `.ai/rules/snapshot.md`, `.ai/rules/index.md` (przez `php artisan boost:index-rules`)

**Intent**: README: zapytania kontrolne zastąpione komendą; wdrożenie S-18 przez `git pull --ff-only`, potem `deploy.sh`; wycofanie: `artisan down`, `migrate:rollback`, cofnięcie kodu na `main`, `git pull --ff-only` na serwerze, `deploy.sh` (jak przy pierwszym wdrożeniu, bo skrypt aktualizuje się sam); migracja przerwana w trakcie (asercja backfillu, aplikacja w trybie serwisowym, expand już zapisany): `migrate:rollback` cofa zapisane migracje partii, potem cofnięcie kodu jak przy wycofaniu; snapshot v6 nie wczyta się w starym kodzie. `categories.md` opisuje stan docelowy (lista + Budget Line, `category_id` na granicy = id listy, `CategoryNameKey` jedynym porównaniem nazw), bez listy sześciu łączeń. `migrations.md`: przykład testu z `tests/Feature/Migrations/CategoryListMigrationTest.php`, zamrożona klasa konwersji jako dozwolony wyjątek od „tylko `DB::table()`”. `snapshot.md`: v6 i fixture.

**Contract**: Indeks wygenerowany, nie edytowany ręcznie.

### Success Criteria:

#### Automated Verification:

- Test komendy przechodzi na PostgreSQL: `… php artisan test --compact tests/Feature/ControlNumbersCommandTest.php`
- `bash -n scripts/deploy.sh` i `bash -n scripts/lib/production-deploy.sh` czyste
- `php artisan boost:index-rules` nie zmienia niczego po commicie (indeks aktualny)
- Pełna bramka zielona: `composer ci:check` (PostgreSQL), `vendor/bin/sloppy ci`

#### Manual Verification:

- Kroki README dają się wykonać w kolejności bez domysłów (przeczytane przez Ciebie przed fazą 7)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 7: Próba na kopii produkcji

### Overview

Ręczna próba przed wdrożeniem: świeże archiwum produkcji wczytane lokalnie, migracja, liczby kontrolne, `down()` i kroki „Przywrócenie produkcji” (follow-up F3 z F-04). Wynik w pliku zmiany. Samo wdrożenie na produkcję robisz Ty po zatwierdzeniu wyniku.

### Changes Required:

#### 1. Zapis próby

**File**: `context/changes/category-list-after-migration/migration-trial.md`

**Intent**: Dowód, że migracja i powrót działają na prawdziwych danych: data, nazwa archiwum, czas migracji i `down()`, tabela liczb kontrolnych (przed / po migracji / po `down()`), lista kategorii z sufiksem „(k)” i archiwalnych po migracji (tylko nazwy, bez kwot operacji), wynik kroków README „Przywrócenie produkcji” w bazie `restore_trial`, poprawki instrukcji.

**Contract**: Kroki: `backup:restore` archiwum produkcji do `restore_trial` (README), `app:control-numbers --output=before.json` gałęzią S-18 przed `migrate` (komendy nie ma na `main`; działa na obu kształtach schematu), `migrate` gałęzią S-18, `app:control-numbers --compare=before.json` = 0 różnic, przejście przez Budżet 2026, plan miesiąca, Operacje, Import i „Kategorie”, `migrate:rollback`, `--compare` = 0 różnic, ponowna migracja. Bez danych osobowych.

### Success Criteria:

#### Manual Verification:

- Liczby kontrolne po migracji i po `down()` zgadzają się z liczbami przed, bez żadnej różnicy
- Budżet 2026 na kopii produkcji ma to samo wykonanie każdej pozycji i te same sumy; operacje nie zmieniły kwot ani kategorii
- Lista po migracji nie ma niespodzianek (sufiksy „(k)” i archiwalne przejrzane i zaakceptowane albo zapisane jako porządki dla S-21)
- Kroki README „Przywrócenie produkcji” przeszły w `restore_trial` (follow-up F3 zamknięty)
- `migration-trial.md` zapisany i zaakceptowany przed wdrożeniem

---

## Testing Strategy

### Unit Tests:

- `CategoryNameKey`: trim Unicode, wielkość liter, polskie znaki rozróżnione.
- `LegacyCategoryConversion`: każdy krok (b)–(d) osobno, w tym wolne k zajęte przez oryginalny rekord, skracanie do 255, kategoria tylko w starym roku, najnowszy budżet szkicem vs najnowszy w wykonaniu.
- Równoważność głosów Importu po nazwie i po id na danych bez wariantów nazwy.

### Integration Tests:

- Migracja na PostgreSQL ze starym kształtem i `down()` (fazy 1 i 3).
- Restore v5 z pliku fixture i parytet z migracją; round trip v6.
- Strona Budżet według ADR-0063 (przypadki „Domeny” i „Rekolekcje” z ADR).
- Filtr Operacji po id przez dwa lata i po zmianie nazwy; walidacja operacji przez resolver.
- Komenda liczb kontrolnych na obu kształtach schematu.

### Manual Testing Steps:

1. Dev: `migrate` na kopii bazy deweloperskiej, porównanie Budżetu 2026/2027, planu miesiąca i Dashboardu z zrzutem sprzed.
2. Dev: zmiana nazwy, dodanie, archiwizacja i przywrócenie kategorii na stronie Budżet i sprawdzenie szkicu 2027.
3. Dev: „Kategorie” → link do Operacji → filtr po kategorii przez lata.
4. Kopia produkcji: faza 7 w całości.

## Performance Considerations

Opis kategorii idzie przez złączenie albo eager loading; każdy czytelnik przeniesiony w fazie 3 sprawdza Sloppy (bez zapytań w pętli). Lata pozycji w widoku „Kategorie” liczy jedno zapytanie agregujące. Migracja na produkcji przetwarza 77 rekordów, więc czas nie jest ryzykiem; asercje sum idą jednym zapytaniem na tabelę.

## Migration Notes

- **Kolejność migracji w jednej partii:** expand, backfill (z asercjami), drop starych kolumn. Jedno wdrożenie = jedna partia, więc `migrate:rollback` cofa wszystkie trzy.
- **Liczby kontrolne:** te z komendy `app:control-numbers` (faza 6), porównane przed i po migracji w `deploy.sh`; przy różnicy wdrożenie się zatrzymuje, aplikacja zostaje w trybie serwisowym.
- **Wycofanie po wdrożeniu bez utraty operacji:** `artisan down`, `migrate:rollback` (nowym kodem), cofnięcie kodu na `main`, `git pull --ff-only` na serwerze, `deploy.sh`. Operacje i kategorie dodane po wdrożeniu zostają; nazwy wracają z pisownią listy i sufiksem „(k)”. Snapshoty v5 z `user:backup` sprzed migracji i kopia instancji z ADR-0064 są zapasem (tracą zmiany po wdrożeniu).
- **Migracja przerwana w trakcie:** każda migracja ma własną transakcję, więc przy wyjątku z asercji backfillu expand zostaje zapisany w partii. Aplikacja zostaje w trybie serwisowym; `migrate:rollback` cofa expand, potem cofnięcie kodu jak przy wycofaniu.
- **Pierwsze wdrożenie:** `git pull --ff-only` na serwerze przed `scripts/deploy.sh`, bo skrypt aktualizuje się sam.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-18)
- PRD: `context/foundation/prd-v3.md` (US-01, FR-001, FR-003, FR-006, FR-019, FR-022, „Migracja danych”)
- Decyzje: `context/foundation/adr/0063-one-category-list-across-years.md`, `0064-instance-backup-before-every-production-migration.md`, `0014`, `0016`, `0033`, `0034`
- Słownik: `context/foundation/glossary.md` (Category, Budget Line, Category Group)
- Reguły: `.ai/rules/migrations.md`, `.ai/rules/categories.md`, `.ai/rules/snapshot.md`, `.ai/rules/inertia-props.md`
- Poprzednie zmiany: `context/archive/2026-11-09-category-list-domain-decisions/`, `context/archive/2026-11-09-safe-first-data-migration/` (`restore-trial.md`, `follow-ups/review-fixes.md` F3)
- Kod: `app/Actions/CreateBudget.php:29-51`, `app/Support/MonthPlanProposal.php:195-210`, `app/Support/OperationsListQuery.php:139-147`, `app/Support/CsvImport/ImportCategorySuggester.php:76-128`, `app/Support/CsvImport/ImportCategorySuggestionModel.php:61,133-160`, `app/Actions/BackupUserData.php:18,146-165`, `app/Actions/RestoreUserData.php:23-49,79,263-289`, `scripts/deploy.sh:18-50`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Schemat i migracja danych

#### Automated

- [x] 1.1 Test migracji przechodzi na PostgreSQL — bbf49ba
- [x] 1.2 Testy konwersji i klucza nazwy przechodzą — bbf49ba
- [x] 1.3 Pełna bramka na PostgreSQL zielona — bbf49ba
- [x] 1.4 Sloppy bez nowych znalezisk i Pint czysty — bbf49ba
- [x] 1.5 Brak wywołań Category::factory()->for($budget) w testach — bbf49ba

#### Manual

- [x] 1.6 Dev po migracji pokazuje te same kwoty Budżetu, planu i Dashboardu — bbf49ba
- [x] 1.7 ADR-0065 jest jasny bez ADR-0063 — bbf49ba

### Phase 2: Snapshot v6

#### Automated

- [x] 2.1 Testy snapshotu przechodzą na PostgreSQL — cb575b2
- [x] 2.2 Test parytetu migracja = restore v5 przechodzi — cb575b2
- [x] 2.3 Pełna bramka zielona — cb575b2

#### Manual

- [x] 2.4 user:backup na devie daje v6, a restore oddaje te same kwoty — cb575b2

### Phase 3: Lista jako źródło prawdy

#### Automated

- [x] 3.1 Testy strony Budżet, kategorii i archiwum przechodzą na PostgreSQL — 6b034e1
- [x] 3.2 Nowe testy zachowania według ADR-0063 przechodzą — 6b034e1
- [x] 3.3 Test migracji z usunięciem kolumn i down() przechodzi na PostgreSQL — 6b034e1
- [x] 3.4 Pełna bramka zielona — 6b034e1

#### Manual

- [x] 3.5 Zmiana nazwy, dodanie, archiwum i przywrócenie działają między 2026 a szkicem 2027 — 6b034e1
- [x] 3.6 Kitchen sink Budżetu pokazuje nowe komunikaty — 6b034e1

### Phase 4: Granica HTTP po id listy

#### Automated

- [x] 4.1 Pełny zestaw testów przechodzi na PostgreSQL — 2c2c76a
- [x] 4.2 Nowe testy filtra po id, resolvera i planu miesiąca przechodzą — 2c2c76a
- [x] 4.3 Brak filtra po category_name i category_type w kodzie — 2c2c76a
- [x] 4.4 Testy frontu, typy i check:ui zielone — 2c2c76a
- [x] 4.5 E2E palety i seed przechodzą — 2c2c76a

#### Manual

- [x] 4.6 Linki z Dashboardu, dodanie wydatku i Import działają jak przed zmianą — 2c2c76a

### Phase 5: Widok „Kategorie”

#### Automated

- [x] 5.1 Test feature widoku i izolacji przechodzi — a3f57d3
- [x] 5.2 check:ui, testy frontu i typy zielone — a3f57d3
- [x] 5.3 E2E widoku i skrótów przechodzą — a3f57d3
- [x] 5.4 Pełna bramka zielona — a3f57d3

#### Manual

- [x] 5.5 Widok pokazuje archiwum i lata, czytelny na telefonie i w ciemnym motywie — a3f57d3
- [x] 5.6 Kitchen sink pokazuje wszystkie stany — a3f57d3

### Phase 6: Zabezpieczenia wdrożenia

#### Automated

- [x] 6.1 Test komendy liczb kontrolnych przechodzi na PostgreSQL — c096a4d
- [x] 6.2 Skrypty wdrożenia przechodzą bash -n — c096a4d
- [x] 6.3 Indeks reguł aktualny — c096a4d
- [x] 6.4 Pełna bramka zielona — c096a4d

#### Manual

- [x] 6.5 Kroki README dają się wykonać bez domysłów — c096a4d

### Phase 7: Próba na kopii produkcji

#### Manual

- [x] 7.1 Liczby kontrolne po migracji i po down() zgodne z liczbami przed
- [x] 7.2 Budżet 2026 i operacje na kopii produkcji bez zmian
- [x] 7.3 Lista po migracji przejrzana i zaakceptowana
- [x] 7.4 Kroki Przywrócenie produkcji przeszły w restore_trial
- [x] 7.5 migration-trial.md zapisany i zaakceptowany przed wdrożeniem
