# Category Edit and Archive Implementation Plan

## Overview

S-19 (FR-002, FR-003, FR-019, FR-022): widok „Kategorie” przestaje być tylko do odczytu. Użytkownik tworzy w nim kategorię listy, edytuje nazwę, ikonę, kolor, kolejność, typ i nowy opis, archiwizuje ją, przywraca i usuwa (gdy nie ma historii). Selektory w operacjach (ręcznie i w Imporcie) oraz w planie miesiąca biorą aktywne kategorie listy, a kategoria bez pozycji w budżecie danego roku dołącza do niego z planem 0 przy pierwszym użyciu (ADR-0063, „Joining Budget Lines”). Archiwalna kategoria na istniejącej operacji zostaje widoczna w selektorze i da się ją zapisać.

## Current State Analysis

- Kategoria to wiersz listy `categories` (`user_id`, `name`, `name_key`, `icon`, `color`, `sort_order`, `type`, `archived_at`), a rok to `budget_lines` (ADR-0063, ADR-0065). Kolumny `description` nie ma nigdzie (`database/migrations/2026_11_09_122524_expand_categories_into_category_list_and_budget_lines.php:48-62`).
- „Kategorie” (`app/Http/Controllers/CategoryController.php:34-37`, `app/Support/CategoryListReading.php:24-73`, `resources/js/pages/Categories.vue`) to sam odczyt: bez akcji, bez `can_*`, bez `categoryAppearanceOptions`.
- Tworzenie i edycja są przypięte do budżetu: `POST budgets/{budget}/categories`, `PATCH budgets/{budget}/categories/{category}` (`routes/web.php:70-72`). `CreateCategory` (`app/Actions/CreateCategory.php:28-114`) wymaga budżetu, a istniejący aktywny klucz dołącza do niego; `UpdateCategory` (`app/Actions/UpdateCategory.php:25-97`) pisze pola listy na `categories`, plan i flagi na pozycję, pilnuje typu (`CategoryTypeChange`) i klucza (`assertKeyFree`). Zmiana kolejności (`app/Actions/ReorderCategories.php:20-87`) jest per budżet, choć pisze `categories.sort_order` dla całej listy typu.
- Archiwum i przywracanie są już na liście (`POST categories/{category}/archive|restore`, `routes/web.php:76-77`; `app/Actions/ArchiveCategory.php:18-41`, `app/Actions/RestoreCategory.php:20-45`) i robią dwa poziomy z ADR-0063, ale kończą `to_route('budget')` (`CategoryController.php:90-116`), a `CategoryPolicy::archive` (`app/Policies/CategoryPolicy.php:16-21`) wymaga pozycji w budżecie w wykonaniu, czego ADR nie wymaga.
- Wybór kategorii operacji (`app/Http/Controllers/OperationsController.php:150-185`) i Importu (`app/Support/CsvImport/ImportCategoryOptions.php:22-68`) to dwa zdublowane zapytania o pozycje budżetów w wykonaniu (`openForChoice()`), filtrowane na froncie po `budget_year`. Kategorii bez pozycji w danym roku nie da się wybrać; dołączania przy pierwszym użyciu nie ma.
- Walidacja `category_id` przy zapisie operacji odrzuca każdą archiwalną kategorię (`app/Concerns/ExpenseValidationRules.php:41-48, :77-84, :112-131`; to samo w `IncomeValidationRules.php`, `OperationRebuildValidationRules.php:129-135, :168-174, :358`, `app/Support/CsvImport/FinancialOperationImportValidator.php:49-58, :111-117`). Dialog edycji czyści kategorię spoza listy (`ExpenseFormDialog.vue:395-403`, `IncomeFormDialog.vue:288-296`), więc operacji na „Wakacjach” nie da się dziś zapisać.
- Wszystkie zapisy operacji (ręczne, Import, przebudowa, para importu) przechodzą przez `ManageFinancialOperation::lockLine` (`app/Actions/ManageFinancialOperation.php:209-222`), który wymaga istniejącej, niezarchiwizowanej pozycji.
- Plan miesiąca dołącza wiersze tylko w trybie zmiany: kandydaci to pozycje budżetu z `budget_lines.archived_at` null (`app/Http/Controllers/MonthPlanController.php:167-182`), bez sprawdzenia archiwum listy; `ChangeMonthPlan` (`app/Actions/ChangeMonthPlan.php:54-75`) i `ApproveMonthPlan` (`app/Actions/ApproveMonthPlan.php:49-62`) przyjmują tylko kategorie z pozycją w budżecie (`amountsByLine`). Zatwierdzenie wymaga dokładnie zbioru propozycji.
- Snapshot pisze v6 (`app/Actions/BackupUserData.php:19`), `RestoreUserData` (`:88-112`) zna wersje 1–6.

## Desired End State

Otwierasz „Kategorie” i dodajesz „Domeny” (wydatek, opis „OVH, home.pl”). Pojawia się w Wydatkach z latami „2027” (szkic dostał pozycję z planem 0). 12 listopada wpisujesz operację 80 zł na „Domeny” z datą 2026-11-12: zapis tworzy pozycję 2026 z planem 0 i wykonaniem 80, Budżet 2025 bez zmian. W zmianie planu grudnia „Domeny” jest od razu jako wiersz dołączenia. Przeciągasz „Domeny” na górę listy, zmieniasz kolor, archiwizujesz literówkę „Domeyn” albo ją usuwasz. Edycja „Hotel Zakopane” na zarchiwizowanych „Wakacjach” zapisuje poprawiony opis. `user:backup` pisze v7 z opisem, v1–v6 nadal się wczytują.

Weryfikacja: testy Feature i E2E z tego planu, `composer ci:check`, `vendor/bin/sloppy ci`, testy migracji na PostgreSQL, kitchen sink `/dev/ui/categories`, `/dev/ui/dialogs`, `/dev/month-plan`.

### Key Discoveries:

- Jedno miejsce dołączania dla operacji: `ManageFinancialOperation::lockLine` (`app/Actions/ManageFinancialOperation.php:209`), wołane z `ExpenseController`, `IncomeController`, `ImportFinancialOperations`, `RebuildOperation`, `ConvertImportPairFinancialOperation`.
- Warunek usuwania z ADR-0063 to dokładnie warunek blokady typu: `CategoryTypeChange::lockedIds` (`app/Support/CategoryList/CategoryTypeChange.php:27-52`), dwa zapytania dla dowolnej liczby kategorii. Cash Expectation nie ma na `main`.
- `budget_lines.category_id` ma `cascadeOnDelete` (`database/migrations/2026_11_09_122525_backfill_category_list.php:36`), więc usunięcie kategorii zabiera jej pozycje w szkicach.
- Flagi nowej pozycji bierze się z najnowszej pozycji kategorii, a bez niej `is_paced = true`, `MonthItemKind::Running` (`app/Actions/RestoreCategory.php:33-44`); `JoinDraftBudgets` (`app/Actions/JoinDraftBudgets.php:17-44`) wstawia linie planu 0.
- `MonthToSpend::rowAmounts` (`app/Support/MonthToSpend.php:93-98`) zwraca zera dla nieznanego id, ale nie przyjmie niezapisanej pozycji: `MonthCategoryActuals::actual|hasPositiveAmount|hasOperations` (`app/Support/MonthCategoryActuals.php:62-75`) mają parametr `int`, więc `id = null` to TypeError. `BudgetLine` nie ma domyślnych atrybutów, a PATCH rodzaju idzie przez `CategoryController::lineOf()->firstOrFail()` (`:69, :154`). Wiersz dołączenia nie może więc przechodzić przez `itemRow` / `rowAmounts`. Front kluczuje wiersze po `category_id` (`MonthPlan.vue:84-90, :672-678`).
- Przeciąganie w Budżecie: `vuedraggable` z `.drag-handle` i `useHttp` (`resources/js/components/BudgetCategoryList.vue:6, :232-255`).
- `.ai/rules/categories.md` mówi, że `BudgetLineResolver::forBudget()` „czeka na S-19”.
- W `components/ui` nie ma `textarea`.

## What We're NOT Doing

- Grupy kategorii i grupowanie selektorów (S-22), rytm (F-05, S-24), scalanie (S-21), nowa zasada tworzenia budżetu (S-20), pomiar i zmiana podpowiedzi Importu (S-23): `ImportCategorySuggester` zostaje bez zmian, więc kategoria bez pozycji w roku nie jest jeszcze podpowiadana.
- Opis w selektorach, w Budżecie i w palecie poleceń (decyzja: opis tylko w „Kategoriach”).
- Zmiany dialogu Budżetu (decyzja: oba dialogi zostają; dialog Budżetu bez opisu).
- Edycja zarchiwizowanej kategorii w „Kategoriach”: wiersz archiwalny ma tylko „Przywróć” i (gdy wolno) „Usuń”.
- Dołączanie zarchiwizowanej kategorii do budżetu, w którym nie ma pozycji, w jakiejkolwiek ścieżce.
- Zmiana reguły „do wydania” i `MonthPlanProposal`: kategoria bez pozycji wchodzi do planu tylko kwotą > 0 wpisaną przez użytkownika.
- Cash Expectation w warunku usuwania i blokady typu (nie ma go na `main`).
- E2E w CI.

## Implementation Approach

Najpierw domena i dane (ADR-0066, opis, snapshot v7), potem zapisy listy na serwerze, potem widok na nich. Dopiero wtedy wybór w operacjach i Imporcie, bo zmienia ścieżkę zapisu każdej operacji, i na końcu plan miesiąca, który korzysta z tego samego mechanizmu dołączania. Dołączanie przy pierwszym użyciu to jedna akcja (`JoinBudgetAtFirstUse`), wołana z `lockLine`, `ChangeMonthPlan` i `ApproveMonthPlan`. Wybór i walidacja to dwie klasy w `app/Support/CategoryList`: opcje (`CategoryChoiceOptions`) i jedna reguła wyboru (`OperationCategoryChoice`), które zastępują cztery kopie walidacji i dwie kopie zapytań o opcje.

## Critical Implementation Details

- **Kolejność w `lockLine`.** `update` najpierw cofa efekt starej pozycji (`revertEffect`), potem bierze nową. Dołączenie (insert pozycji) musi stać w tej samej transakcji przed `lockForUpdate`, a wyścig dwóch zapisów na tę samą parę (`unique(budget_id, category_id)`) kończy się ponownym odczytem, nie błędem 500.
- **Odczyty ostrzeżeń przed zapisem.** `HandEntryMonthRemainingInputs`, `EntryToSpendInputs` i `ImportMonthRemainingContext` budują dane po pozycjach budżetu; kategoria z opcji bez pozycji musi w nich wyglądać jak pozycja z planem 0 i flagami, które dostanie przy dołączeniu. Inaczej podgląd „przed → po” pokaże co innego niż stan po zapisie.

## Phase 1: Domena i dane

### Overview

Zapisuje decyzje S-19, których słownik i ADR-0063 nie mówią, dodaje kolumnę opisu i snapshot v7.

### Changes Required:

#### 1. ADR-0066 i słownik

**File**: `context/foundation/adr/0066-category-choice-and-archive-on-the-list.md`, `context/foundation/glossary.md`

**Intent**: Zapisać przed kodem (twarda zasada AGENTS.md) reguły wyboru i zapisu, które S-19 dodaje do ADR-0063: (1) wybór w operacjach i Imporcie to aktywne kategorie listy w każdym budżecie w wykonaniu, także bez pozycji (dołącza z planem 0 i flagami z najnowszej pozycji, domyślnie równomierna i bieżąca); pozycja zarchiwizowana w roku Y nie jest do wyboru w Y; (2) zapis istniejącej operacji z niezmienioną zarchiwizowaną kategorią (archiwum listy albo pozycji) przechodzi, gdy rok daty ma pozycję tej kategorii, a zarchiwizowana nie dołącza nigdzie; (3) plan miesiąca dołącza kategorię bez pozycji przy kwocie > 0, w zatwierdzeniu i w zmianie; kwota 0 nic nie tworzy; (4) archiwizacja jest dostępna dla każdej aktywnej kategorii, bez warunku pozycji w budżecie w wykonaniu; (5) usuwanie z listy: warunek ADR-0063 bez Cash Expectation, dopóki go nie ma; (6) opis: zwykły tekst, nieobowiązkowy, do 500 znaków, widoczny tylko w „Kategoriach”. Odrzucone: tylko operacje bez planu miesiąca, brak dołączania, blokada edycji operacji na archiwalnej kategorii, zapis tylko w tym samym roku, archiwum bez usuwania, opis w selektorach.

**Contract**: Wpis **Category** w słowniku dostaje zdania o (2), (3) i opisie (500 znaków); zdania o archiwum i usuwaniu bez zmiany znaczenia. ADR-0066 odwołuje się do ADR-0063 jako uzupełnienie, nie zastąpienie.

#### 2. Kolumna opisu

**File**: nowa migracja `database/migrations/<ts>_add_description_to_categories_table.php`, `app/Models/Category.php`

**Intent**: Dodać nieobowiązkowy opis kategorii listy (FR-002).

**Contract**: `categories.description` `string(500)` nullable; `down()` usuwa kolumnę. `Category::$fillable` + `description`. Migracja według `.ai/rules/migrations.md`, test w `tests/Feature/Migrations/` uruchamiany na PostgreSQL.

#### 3. Snapshot v7

**File**: `app/Actions/BackupUserData.php`, `app/Actions/RestoreUserData.php`, `tests/Feature/UserSnapshotCommandTest.php`, `tests/Fixtures/snapshots/v6-category-list.json` (nowy)

**Intent**: FR-022: snapshot obejmuje opis; v6 i starsze nadal się wczytują (`.ai/rules/snapshot.md`).

**Contract**: `SNAPSHOT_VERSION = 7`; kategoria w snapshocie ma `description`; `RestoreUserData` dopisuje 6 do każdej listy wersji, a dwa porównania przez równość, które dziś znaczą „format listy” (`$isCurrentFormat` w `RestoreUserData.php:30` i wymóg `budget_lines` w `:112`), zamieniają się na `in_array($version, [6, BackupUserData::SNAPSHOT_VERSION], true)`; inaczej v6 poszedłby przez `withBudgetLineKeys` i konwersję v1–v5. v6 dostaje `description = null`, konwersja v1–v5 (krok (f)) daje `null`. Ręcznie napisana fixture v6 (nie z fabryk) i test jej przywrócenia.

#### 4. Reguły agenta

**File**: `.ai/rules/categories.md`, `.ai/rules/snapshot.md`

**Intent**: Reguły mówią o kolumnie opisu, v7 i o tym, że wybór idzie przez klasy z fazy 4.

**Contract**: Zdanie o `description` w akapicie modelu; w snapshot.md wersja 7; po zmianie `php artisan boost:index-rules`.

### Success Criteria:

#### Automated Verification:

- Test migracji opisu przechodzi na PostgreSQL (polecenie z AGENTS.md → Commands)
- `php artisan test --compact tests/Feature/UserSnapshotCommandTest.php` przechodzi, w tym przywrócenie fixture v6 i v5
- `vendor/bin/pint --dirty --format agent` bez zmian po formatowaniu

#### Manual Verification:

- ADR-0066 i wpis słownika czytają się zgodnie z ADR-0063 i decyzjami z planu

**Implementation Note**: Po zielonej automatyce w tej fazie zatrzymaj się na ręczne potwierdzenie, zanim przejdziesz dalej.

---

## Phase 2: Zapisy listy (backend)

### Overview

Trasy i akcje listy bez budżetu: tworzenie, edycja z opisem, kolejność, usuwanie; archiwum bez warunku pozycji w wykonaniu; propsy „Kategorii” z opisem i uprawnieniami.

### Changes Required:

#### 1. Trasy i kontroler listy

**File**: `routes/web.php`, nowy `app/Http/Controllers/CategoryListController.php`, `app/Http/Controllers/CategoryController.php`

**Intent**: Endpointy listy, niezależne od budżetu, obok istniejących tras Budżetu (które zostają). Archiwum i przywracanie wracają tam, skąd przyszło żądanie.

**Contract**: `POST categories` → `store`, `PATCH categories/reorder` → `reorder` (przed trasą z `{category}`), `PATCH categories/{category}` → `update`, `DELETE categories/{category}` → `destroy`; zapisy kończą `back()`, `reorder` zwraca 204 jak `CategoryController::reorder`. `CategoryController::archive|restore` → `back()` (z Budżetu wraca z `show_archived`, z „Kategorii” do „Kategorii”). Po zmianie tras `npm run build` przed `npm run types:check` (`.ai/rules/wayfinder.md`).

#### 2. Akcje listy

**File**: nowe `app/Actions/CreateListCategory.php`, `app/Actions/UpdateListCategory.php`, `app/Actions/ReorderListCategories.php`, `app/Actions/DeleteCategory.php`; `app/Actions/CreateCategory.php`, `app/Actions/UpdateCategory.php`, `app/Actions/ReorderCategories.php`; nowe `app/Support/CategoryList/CategoryNameGuard.php`, `app/Support/CategoryList/CategoryListOrder.php`

**Intent**: Zapis pól listy w jednym miejscu, z którego korzysta też Budżet, bez kopii sprawdzania klucza.

**Contract**:
- `CategoryNameGuard`: sprawdzenie, czy klucz (`name_key`, typ) jest wolny dla użytkownika z pominięciem danej kategorii, z dwoma komunikatami (zajęty przez aktywną / przez zarchiwizowaną z podpowiedzią przywrócenia); przeniesione z `UpdateCategory::assertKeyFree` i używane przez obie ścieżki tworzenia i edycji. `UniqueConstraintViolationException` dalej zamienia się na błąd `name`.
- `CreateListCategory::handle(User, array{name, icon, color, type, description?}): Category` — nowa kategoria (sort_order = max + 1 w typie), dołącza do wszystkich szkiców przez `JoinDraftBudgets` z domyślnymi flagami; do budżetu w wykonaniu nie dołącza. Istniejący klucz (aktywny lub archiwalny) jest odrzucany, bo na liście nie ma „dołączenia”.
- `UpdateListCategory::handle(Category, array{name, icon, color, type, description?})` — pisze pola listy, pilnuje typu (`CategoryTypeChange::isLocked`) i klucza; zmiana na przychód zeruje flagi pozycji jak dziś (`UpdateCategory.php:52-56`). `UpdateCategory` deleguje do niej część listy (bez opisu, który Budżet nie wysyła i nie czyści).
- `ReorderListCategories::handle(User, CategoryType, list<int>)` — przesłane id muszą być dokładnie zbiorem aktywnych kategorii listy typu. Numeracja jak w Budżecie: algorytm slotów z `ReorderCategories::listOrder` (`app/Actions/ReorderCategories.php:62-86`, cała lista typu od 0 do n-1 pod `lockForUpdate`, archiwalne zostają w swoich slotach) wydzielony do `CategoryListOrder` i używany przez obie akcje, razem z jednym `UPDATE … CASE`.
- `DeleteCategory::handle(Category)` — w transakcji ponowne `CategoryTypeChange::isLocked` (błąd walidacji, gdy zablokowana), potem `delete()` (pozycje w szkicach znikają kaskadą).

#### 3. Polityki i żądania

**File**: `app/Policies/CategoryPolicy.php`, `app/Concerns/CategoryValidationRules.php`, nowe `app/Http/Requests/StoreListCategoryRequest.php`, `UpdateListCategoryRequest.php`, `ReorderListCategoriesRequest.php`

**Intent**: Archiwum według ADR-0066 (4), usuwanie według (5), edycja listy tylko aktywnej kategorii.

**Contract**: `archive`: właściciel i nie zarchiwizowana. Nowe `delete`: właściciel i `! CategoryTypeChange::isLocked`. `UpdateListCategoryRequest` autoryzuje `update` i wymaga aktywnej kategorii. `listCategoryRules()`: `name` required|string|max:255, `icon` in `CategoryIcons`, `color` in `CategoryColors`, `type` enum, `description` nullable|string|max:500 (pusty tekst zapisuje `null`). Reorder: `type` + `category_ids` (distinct int).

#### 4. Propsy „Kategorii”

**File**: `app/Support/CategoryListReading.php`, `app/Http/Controllers/CategoryController.php`, `resources/js/types/categories.ts`

**Intent**: Widok dostaje to, czego potrzebują dialog i akcje wiersza.

**Contract**: Każda kategoria + `description: string|null`, `can_change_type: bool`, `can_delete: bool` (jedno `CategoryTypeChange::lockedIds` dla całej listy, bez zapytań w pętli); strona + `categoryAppearanceOptions` (`CategoryAppearance::formOptions()`). PHP i typ TS w jednym commicie z `assertInertia` (`.ai/rules/inertia-props.md`).

#### 5. Testy

**File**: nowy `tests/Feature/CategoryListWriteTest.php`, `tests/Feature/CategoriesPageTest.php`, `tests/Feature/ArchiveCategoryTest.php`

**Intent**: Zachowanie zapisów listy i zmieniona polityka archiwum.

**Contract**: Przypadki: utworzenie dołącza do szkicu 2027, nie do 2026 w wykonaniu; nazwa zajęta przez aktywną i przez archiwalną (podpowiedź); edycja nazwy widoczna we wszystkich latach; zmiana typu zablokowana przy operacji w dowolnym roku i przy pozycji w wykonaniu; zmiana na przychód zeruje flagi; opis 500 / 501 znaków; kolejność: dokładny zbiór, archiwalne nie przyjęte; usunięcie świeżej kategorii (znika pozycja szkicu) i odmowa przy historii; archiwum kategorii bez pozycji w wykonaniu (asercja w `ArchiveCategoryTest` zmienia się, bo zmieniło się wymaganie, ADR-0066); `back()` z obu stron; cudza kategoria → 403. `CategoriesPageTest` asercje nowych propsów.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/CategoryListWriteTest.php tests/Feature/CategoriesPageTest.php tests/Feature/ArchiveCategoryTest.php tests/Feature/CategoryListBudgetPageTest.php tests/Feature/CategoryReorderTest.php` przechodzi
- Te same testy przechodzą na PostgreSQL
- `vendor/bin/sloppy ci` bez nowych znalezisk
- `npm run build && npm run types:check` przechodzi

#### Manual Verification:

- Na stronie Budżet dodanie, edycja, archiwum i przywracanie kategorii działają jak przed zmianą

**Implementation Note**: Po zielonej automatyce w tej fazie zatrzymaj się na ręczne potwierdzenie, zanim przejdziesz dalej.

---

## Phase 3: Widok „Kategorie”

### Overview

Dialog kategorii listy, akcje wiersza, przeciąganie, opis w wierszu, stany w kitchen sinku, aktualizacja design systemu i E2E.

### Changes Required:

#### 1. Dialog kategorii listy

**File**: nowy `resources/js/components/CategoryListFormDialog.vue`, wspólne pola wyglądu wydzielone z `resources/js/components/CategoryFormDialog.vue`, `resources/js/components/ui/textarea/*` (shadcn-vue CLI)

**Intent**: Tworzenie i edycja pól listy z opisem, bez planu i flag (decyzja: oba dialogi zostają). Nazwa, kolor, ikona i podgląd chipa mają jedno źródło dla obu dialogów, zamiast kopii.

**Contract**: Pola: nazwa, typ (`SegmentedControl` `block` z `name`; wyłączony z podpowiedzią, gdy `!can_change_type`), kolor, ikona, opis (`FormField` z `Textarea`, licznik/limit 500). Posty przez Wayfinder `CategoryListController.store.form()` / `.update.form(category)`. Reguły `.ai/rules/js-dialogs.md` (FormField, „Anuluj” `ghost`, spinner). `Textarea` dodany przez shadcn-vue CLI, bez nowej zależności npm; nie edytowany pod ten widok.

#### 2. Wiersz, akcje i przeciąganie

**File**: `resources/js/pages/Categories.vue`, `resources/js/components/CategoryListRow.vue`, `resources/js/components/CategoryArchiveSection.vue`, nowy `resources/js/components/CategoryListRowActions.vue`, `resources/css/app.css` (`.categories-grid`), `lang/pl/ui.php`

**Intent**: Akcje z wiersza i zmiana kolejności na liście (decyzja: kolejność także w „Kategoriach”).

**Contract**: „Dodaj kategorię” w tytule panelu Przychody i Wydatki (typ z panelu). Wiersz aktywny: uchwyt `.drag-handle`, `IconAction` „Edytuj”, „Archiwizuj” i „Usuń” (`ConfirmActionDialog` z Wayfinder `.form`; „Usuń” tylko gdy `can_delete`, przycisk `outline-destructive`). Wiersz archiwalny: „Przywróć” i „Usuń” (gdy `can_delete`), bez uchwytu i edycji. Opis: druga, przygaszona linia pod chipem, ucięta, pełny tekst w `title`. Przeciąganie jak `BudgetCategoryList.vue` (`vuedraggable`, `useHttp` do `CategoryListController.reorder`, przywrócenie kolejności i toast przy błędzie). `.categories-grid` dostaje kolumny uchwytu i akcji jako nazwane obszary (`.ai/rules/css.md`), bez wartości arbitralnych w szablonie. Teksty z `t.categories.*`.

#### 3. Kitchen sink, design system i straże

**File**: `resources/js/lib/categories-fixtures.ts`, `resources/js/pages/dev/CategoriesKitchenSink.vue`, `resources/js/pages/dev/DialogsKitchenSink.vue`, `context/foundation/design-system.md` (akapit `/dev/ui/categories`), `scripts/check-ui-literals.mjs`, `tests/Unit/CategoriesHardcodedUiTest.php`

**Intent**: Każdy stan widoku jest w sinku (twarda zasada), a strona design systemu nie mówi już „tylko do odczytu”.

**Contract**: Scenariusze: z opisami (krótki, długi ucięty), zablokowany typ, `can_delete` wł./wył., archiwum z „Przywróć” i „Usuń”; dialog: tworzenie, edycja, typ zablokowany, błąd nazwy zajętej przez archiwalną. Macierz 7 stanów (hover, focus-visible, disabled, error, loading, empty, default) opisana w akapicie design systemu. Nowe pliki widoku w liście `check:ui` i `CategoriesHardcodedUiTest`.

#### 4. E2E

**File**: `tests/e2e/categories.spec.ts`

**Intent**: Przepływ w przeglądarce zamiast `post()` z testu.

**Contract**: Utworzenie kategorii z opisem → widoczna w Wydatkach; edycja opisu; archiwizacja z UI → w „Archiwum (n)”; przywrócenie; usunięcie świeżej kategorii.

### Success Criteria:

#### Automated Verification:

- `npm run build && npm run types:check` przechodzi
- `npm run check` i `npm run check:ui` przechodzą
- `npm run test` przechodzi (w tym `ui-contract.test.ts`)
- `php artisan test --compact tests/Unit/CategoriesHardcodedUiTest.php tests/Feature/CategoriesKitchenSinkTest.php` przechodzi
- `npx playwright test tests/e2e/categories.spec.ts` przechodzi lokalnie

#### Manual Verification:

- `/dev/ui/categories` i `/dev/ui/dialogs` pokazują wszystkie stany w jasnym i ciemnym motywie
- Przeciąganie działa myszą i na telefonie (wąskie okno), po odświeżeniu kolejność zostaje
- Na telefonie wiersz z opisem i akcjami mieści się bez poziomego przewijania

**Implementation Note**: Po zielonej automatyce w tej fazie zatrzymaj się na ręczne potwierdzenie, zanim przejdziesz dalej.

---

## Phase 4: Wybór w operacjach i Imporcie

### Overview

Opcje z aktywnych kategorii listy, dołączanie przy pierwszym użyciu w `lockLine`, jedna reguła wyboru zamiast czterech, zapis niezmienionej archiwalnej kategorii i opcja „(zarchiwizowana)” w dialogach edycji.

### Changes Required:

#### 1. Dołączenie przy pierwszym użyciu

**File**: nowy `app/Actions/JoinBudgetAtFirstUse.php`

**Intent**: Jedno miejsce tworzenia pozycji z planem 0 w budżecie (ADR-0063), używane przez operacje i plan miesiąca.

**Contract**: `handle(Budget, Category): BudgetLine` — zwraca istniejącą aktywną pozycję albo tworzy nową (plan 0, wykonanie 0, flagi z najnowszej pozycji kategorii, domyślnie `is_paced = true`, `Running`); odmawia dla kategorii zarchiwizowanej na liście i dla istniejącej pozycji zarchiwizowanej (zapis na niej idzie tylko przez `lockLine` dla niezmienionej kategorii, tabela w #3). Wyścig na `unique(budget_id, category_id)` kończy się ponownym odczytem. Wspólne źródło flag z `RestoreCategory` (bez kopii zapytania o najnowszą pozycję).

#### 2. Opcje wyboru

**File**: nowy `app/Support/CategoryList/CategoryChoiceOptions.php`, `app/Http/Controllers/OperationsController.php:150-185`, `app/Support/CsvImport/ImportCategoryOptions.php`, `app/Support/CategoryAppearance.php`

**Intent**: Wybór to aktywne kategorie listy w każdym budżecie w wykonaniu, także bez pozycji (ADR-0066 (1)); usuwa dwie kopie zapytania.

**Contract**: Dla typu i użytkownika lista wspólnych wierszy `{category: Category, budget_year: int, line: ?BudgetLine, is_paced: bool}`: pozycje `openForChoice()` + aktywne kategorie bez pozycji w tym budżecie (`line = null`, `is_paced` jak przy dołączeniu). Każdy wołający mapuje wiersze na swój dzisiejszy kształt, który się nie zmienia:
- Operacje, wydatek: `{id, name, icon, color, budget_year, planned_amount_minor_units, execution_minor_units}` (`OperationsController.php:177-184`), zera przy `line = null`;
- Import, wydatek: `{id, name, icon, color, budget_year, is_paced}` (`ImportCategoryOptions.php:59-64`);
- przychód (oba miejsca): `{id, name, icon, color, budget_year}`.
`CategoryAppearance::fields()` / `pickerOption()` (`app/Support/CategoryAppearance.php:18, :30`) przyjmują dziś `BudgetLine`; dostają wejście z `Category`, z którego korzysta też wariant z pozycją. `assertInertia` na `expenseCategories` / `incomeCategories` i na opcje Importu z wpisem dołączenia. Front dalej filtruje po `budget_year`. Stała liczba zapytań niezależna od liczby budżetów.

#### 3. Jedna reguła wyboru przy zapisie

**File**: nowy `app/Support/CategoryList/OperationCategoryChoice.php`, `app/Concerns/ExpenseValidationRules.php`, `app/Concerns/IncomeValidationRules.php`, `app/Concerns/OperationRebuildValidationRules.php`, `app/Support/CsvImport/FinancialOperationImportValidator.php`

**Intent**: Te same błędy co dziś dla nowych operacji, plus dołączanie i wyjątek dla niezmienionej archiwalnej kategorii (ADR-0066 (2)).

**Contract**: `check(User, int $categoryId, CategoryType, int $year, ?int $currentCategoryId): ?string` (komunikat błędu albo `null`). Kategoria musi należeć do użytkownika i mieć typ. Tabela decyzji („ta sama” = `$categoryId === $currentCategoryId`):

| Budżet roku daty | Pozycja kategorii w roku | Kategoria listy | Ta sama | Zmieniona / nowa operacja |
| --- | --- | --- | --- | --- |
| brak albo szkic | dowolna | dowolna | dzisiejszy komunikat roku / fazy | dzisiejszy komunikat roku / fazy |
| w wykonaniu | aktywna | aktywna | zapis | zapis |
| w wykonaniu | aktywna | zarchiwizowana | zapis | błąd archiwum (dzisiejszy) |
| w wykonaniu | zarchiwizowana | dowolna | zapis na tej pozycji | błąd archiwum (dzisiejszy) |
| w wykonaniu | brak | aktywna | dołączenie + zapis | dołączenie + zapis |
| w wykonaniu | brak | zarchiwizowana | błąd „nie należy do budżetu roku” | błąd „nie należy do budżetu roku” |

Dziś `BudgetLineResolver::forYear` zwraca `null` i dla braku budżetu, i dla braku pozycji (jeden komunikat, `ExpenseValidationRules.php:112-130` i trzy kopie); reguła rozdziela te przypadki. `Rule::exists(... ->whereNull('archived_at'))` zamienia się na `exists` bez warunku archiwum + ta reguła w `after`. `$currentCategoryId` to kategoria listy edytowanej operacji (żądania update i przebudowy), `null` przy tworzeniu i w Imporcie.

#### 4. Zapis operacji

**File**: `app/Actions/ManageFinancialOperation.php:124-222`

**Intent**: Pierwsza operacja dołącza pozycję; edycja na niezmienionej archiwalnej kategorii zapisuje się na jej (zarchiwizowanej) pozycji.

**Contract**: `lockLine(User, int $categoryId, string $operationDate, ?int $currentCategoryId)`: pozycję roku czyta bez filtra archiwum; zarchiwizowana pozycja przy zmienionej kategorii → odmowa (obrona za walidacją), przy niezmienionej → zapis na niej; brak pozycji → `JoinBudgetAtFirstUse` w budżecie roku (w wykonaniu). `update` przekazuje kategorię listy operacji sprzed zmiany. Import przechodzi tą samą ścieżką, więc pozycja powstaje przy zatwierdzeniu importu.

#### 5. Odczyty ostrzeżeń dla kategorii, która dołączy

**File**: `app/Support/HandEntryMonthRemainingInputs.php`, `app/Support/EntryToSpendInputs.php`, `app/Support/CsvImport/ImportMonthRemainingContext.php`

**Intent**: Podgląd „przed → po” i ostrzeżenia Importu dla kategorii bez pozycji liczą jak dla pozycji z planem 0 i flagami dołączenia (zob. Critical Implementation Details).

**Contract**: Żaden z trzech odczytów nie czyta dziś opcji; każdy sam pyta o pozycje, a front po cichu pomija nieznane `category_id` (`entry-to-spend-warning.ts:107-113`, `hand-entry-month-remaining-preview.ts:188-193`, `import-month-remaining-warnings.ts:147-153`). Każdy odczyt dokleja więc wpisy dla aktywnych kategorii listy typu wydatek bez pozycji w danym budżecie w wykonaniu (jedno zapytanie na listę dla wszystkich budżetów, bez zapytań w pętli), z flagami z tego samego źródła co `JoinBudgetAtFirstUse`:
- `HandEntryMonthRemainingInputs` (`:31-58`): wpis `{id: category_id, is_paced, planned_amount_minor_units: 0, monthly_execution_minor_units: 12 × 0}` w `categories` budżetu roku;
- `ImportMonthRemainingContext` (`:35-39, :64-82`): ten sam wpis i zera w `execution_by_category_and_month`;
- `EntryToSpendInputs` (`:82-117`): wpis w `categories` (plan 0, flagi) bez elementu planu miesiąca (kategoria bez pozycji nie ma kwoty w propozycji).
Po zapisie ten sam odczyt daje to samo co podgląd (test na każdy z trzech).

#### 6. Front: wybrana archiwalna kategoria

**File**: `resources/js/components/ExpenseFormDialog.vue:145-161, :366, :395-403`, `resources/js/components/IncomeFormDialog.vue:188-204, :261, :288-296`, `app/Http/Controllers/OperationsController.php:58-61`, typ wiersza operacji w `resources/js/types` / `resources/js/lib/operations-*.ts`, nowa czysta funkcja w `resources/js/lib/operations-category-options.ts` (+ `.test.ts`)

**Intent**: Na istniejącej operacji archiwalna kategoria zostaje i jest widoczna w selektorze tylko, póki jest wybrana (FR-019).

**Contract**: Wiersz operacji dostaje `category_archived: bool` (archiwum listy albo pozycji roku operacji); PHP + TS + `assertInertia` w jednym commicie. Funkcja `withSelectedCategory(options, selected)` dokleja opcję z nazwą, ikoną i kolorem wiersza oraz dopiskiem „(zarchiwizowana)”, gdy wybrana kategoria nie jest w opcjach roku; watcher edycji nie zeruje już takiej kategorii. Po wyborze innej opcji doklejona znika. Ostrzeżenie limitu dostaje nieprzefiltrowane `expenseCategories` (`ExpenseFormDialog.vue:178`), a `findCategory` (`resources/js/lib/hand-entry-warning-preview.ts:75-83`) szuka tylko po id; po S-19 każda nowa kategoria ma wpis w każdym budżecie w wykonaniu, więc `HandEntryPreviewCategory` dostaje `budget_year` (dziś go nie ma), a `findCategory` dopasowuje po (`id`, rok daty operacji), z testem w `hand-entry-warning-preview.test.ts` dla dwóch budżetów w wykonaniu.

#### 7. Testy

**File**: `tests/Feature/ExpenseTest.php`, `tests/Feature/IncomeTest.php`, `tests/Feature/FinancialOperationEditDeleteTest.php`, `tests/Feature/CsvImportTest.php` (zatwierdzenie i opcje), `tests/Feature/OperationRebuildTest.php`, `tests/Feature/OperationsRowRecipeTest.php` (prop `category_archived`), `resources/js/lib/operations-category-options.test.ts`

**Intent**: Regresja dla nowych reguł i zachowanie dotychczasowych błędów.

**Contract**: Przypadki: operacja 2026-11-12 na „Domenach” bez pozycji 2026 tworzy pozycję plan 0 / wykonanie 80, a 2025 bez zmian; rok bez budżetu i rok w szkicu → dzisiejsze błędy; nowa operacja na archiwalnej → błąd (istniejący test zostaje); edycja „Hotel Zakopane” na archiwalnych „Wakacjach”: zmiana opisu i kwoty zapisuje (wykonanie pozycji zmienia się o różnicę), data do 2027 bez pozycji → błąd, data do roku z pozycją → zapis; zmiana na inną archiwalną → błąd; edycja z niezmienioną aktywną kategorią na rok w wykonaniu bez jej pozycji → dołączenie; brak budżetu roku i brak pozycji zarchiwizowanej kategorii dają różne komunikaty; Import: wiersz na kategorii bez pozycji tworzy ją przy zatwierdzeniu, opcje Importu zawierają wpis dołączenia; podgląd ostrzeżenia wydatku na kategorii, która dołączy, równy stanowi po zapisie.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/ExpenseTest.php tests/Feature/IncomeTest.php tests/Feature/FinancialOperationEditDeleteTest.php tests/Feature/CsvImportTest.php tests/Feature/OperationRebuildTest.php tests/Feature/OperationsRowRecipeTest.php` przechodzi
- Te same testy przechodzą na PostgreSQL
- `npm run test` przechodzi, w tym `operations-category-options.test.ts`
- `npm run build && npm run types:check` przechodzi
- `vendor/bin/sloppy ci` bez nowych znalezisk

#### Manual Verification:

- W Operacjach nowa kategoria z „Kategorii” jest do wyboru w dialogu wydatku dla daty 2026, a po zapisie pojawia się w Budżecie 2026 z planem 0
- Edycja operacji na zarchiwizowanej kategorii pokazuje ją z dopiskiem „(zarchiwizowana)” i zapisuje poprawkę opisu
- Import mBank z wierszem na nowej kategorii zatwierdza się i tworzy pozycję

**Implementation Note**: Po zielonej automatyce w tej fazie zatrzymaj się na ręczne potwierdzenie, zanim przejdziesz dalej.

---

## Phase 5: Plan miesiąca

### Overview

Wiersze dołączenia z aktywnych kategorii listy (z poprawką archiwum listy), w propozycji i w trybie zmiany; pozycja powstaje przy kwocie > 0.

### Changes Required:

#### 1. Wiersze dołączenia

**File**: `app/Http/Controllers/MonthPlanController.php:158-200, :270-300`, `resources/js/pages/MonthPlan.vue:58-80`, `resources/js/components/MonthPlanItemRow.vue`, fiksury `/dev/month-plan`

**Intent**: Kategoria listy bez pozycji w budżecie jest wierszem dołączenia, a kategoria zarchiwizowana na liście nim nie jest (ADR-0066 (3)).

**Contract**: Kandydaci = pozycje budżetu z `openForChoice()` spoza planu (przez `itemRow` jak dziś) + aktywne kategorie listy bez pozycji w budżecie, budowane osobną funkcją `joinCandidateRow(Category, bool $isPaced, MonthItemKind $kind)` w `MonthPlanController`: ten sam kształt co `itemRow`, ze stałymi (plan 0, wykonanie 0, `has_positive_amount = false`, `can_change_kind = false`, flagi z tego samego źródła co `JoinBudgetAtFirstUse`), bez `rowAmounts`, `MonthCategoryActuals` i niezapisanej `BudgetLine`; `in_plan = false`, `is_join_candidate = true`. Test `assertInertia` sprawdza, że wiersz z `joinCandidateRow` i wiersz z `itemRow` mają te same klucze. Wiersze dołączenia widać w trybie zmiany (jak dziś) i w edytowalnej propozycji przed zatwierdzeniem. Kształt wiersza bez zmian (asercja `assertInertia`). Nowy stan w sinku `/dev/month-plan`.

#### 2. Zapis kwot

**File**: `app/Actions/ChangeMonthPlan.php:54-120`, `app/Actions/ApproveMonthPlan.php:49-80`, `app/Support/BudgetLineResolver.php`

**Intent**: Kwota > 0 na kategorii bez pozycji tworzy pozycję i element planu; kwota 0 nic nie tworzy; zbiór propozycji przy zatwierdzeniu dalej musi być kompletny.

**Contract**: Kwoty dzielą się na kategorie z pozycją (`amountsByLine` dla nich) i kategorie do dołączenia (aktywne, bez pozycji); te drugie z kwotą > 0 → `JoinBudgetAtFirstUse` + element planu, z kwotą 0 pomijane; każda inna nieznana kategoria → „The month plan items are invalid.”. `ApproveMonthPlan`: przesłane ⊇ id propozycji, nadmiarowe tylko do dołączenia. `ChangeMonthPlan`: dozwolone istniejące pozycje to niezarchiwizowane na obu poziomach albo już w planie. `forBudget()` używany przy dołączaniu; zdanie „czeka na S-19” w `.ai/rules/categories.md` znika.

#### 3. Testy

**File**: `tests/Feature/ChangeMonthPlanTest.php`, `tests/Feature/ApproveMonthPlanTest.php`, `tests/Feature/MonthPlanPageTest.php`, `tests/Feature/DevMonthPlanSinkTest.php`

**Intent**: Dołączanie w planie i brak zmian w „do wydania” istniejących planów.

**Contract**: Przypadki: zmiana planu grudnia 2026 z „Domeny” 80 → pozycja plan 0 i element 80; „Domeny” 0 → brak pozycji; zatwierdzenie propozycji z nadmiarową „Domeny” 80 i 0; kategoria zarchiwizowana na liście z aktywną pozycją nie jest kandydatem i jest odrzucana przy zapisie; „do wydania” miesięcy bez nowych kwot bez zmian (istniejące testy `MonthPlanProposalTest` przechodzą bez edycji).

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/ChangeMonthPlanTest.php tests/Feature/ApproveMonthPlanTest.php tests/Feature/MonthPlanPageTest.php tests/Feature/MonthPlanProposalTest.php tests/Feature/DevMonthPlanSinkTest.php` przechodzi
- Te same testy przechodzą na PostgreSQL
- Pełna bramka: `composer ci:check` i `vendor/bin/sloppy ci`
- `npm run build && npm run types:check && npm run test && npm run check:ui` przechodzą

#### Manual Verification:

- W zmianie planu bieżącego miesiąca nowa kategoria jest wierszem dołączenia, a wpisana kwota pojawia się po zapisie i w Budżecie jako pozycja z planem 0
- „Do wydania” miesięcy, w których nic nie wpisano, jest takie jak przed zmianą

**Implementation Note**: Po zielonej automatyce w tej fazie zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `operations-category-options.test.ts`: doklejona opcja archiwalna, jej zniknięcie po zmianie, brak doklejenia dla kategorii z listy.
- Klasy `OperationCategoryChoice` i `CategoryChoiceOptions` testowane przez Feature (granica HTTP), bez osobnych testów jednostkowych, chyba że przypadek brzegowy wymaga.

### Integration Tests:

- Feature dla zapisów listy, operacji, Importu, planu miesiąca, propsów (`assertInertia`), snapshotu v7 i migracji (PostgreSQL).
- E2E `categories.spec.ts` dla przepływu w „Kategoriach”.

### Manual Testing Steps:

1. Utwórz „Domeny” w „Kategoriach” z opisem, sprawdź lata „2027” i opis w wierszu.
2. Dodaj wydatek 80 zł na „Domeny” z datą w 2026, sprawdź Budżet 2026 (plan 0, wykonanie 80).
3. W zmianie planu bieżącego miesiąca wpisz kwotę dla innej nowej kategorii i zapisz.
4. Zarchiwizuj „Wakacje”-podobną kategorię z operacjami, otwórz edycję jej operacji, popraw opis, zapisz.
5. Utwórz i usuń „Domeyn”; sprawdź, że nazwa jest znów wolna.
6. Przeciągnij kategorię w „Kategoriach”, sprawdź kolejność w Budżecie i w selektorze operacji.

## Performance Considerations

`CategoryChoiceOptions` i `CategoryListReading` mają stałą liczbę zapytań (lista kategorii, pozycje budżetów w wykonaniu, `lockedIds`), bez zapytań w pętli (Sloppy). Przy ~40 kategoriach i 1–2 budżetach w wykonaniu opcji jest najwyżej ~80.

## Migration Notes

Jedna migracja dodająca nullable kolumnę z prawdziwym `down()`; bez zmiany danych. Snapshot v6 zrobiony po S-18 wczytuje się w kodzie S-19; snapshot v7 nie wczyta się w kodzie przed S-19 (wycofanie przez `down()`).

## References

- Roadmapa: `context/foundation/roadmap.md` (S-19)
- PRD: `context/foundation/prd-v3.md` (FR-002, FR-003, FR-019, FR-022; decyzje strukturalne 2 i 4)
- ADR-0063: `context/foundation/adr/0063-one-category-list-across-years.md`; ADR-0065 (operacje przez Budget Line)
- Poprzednia zmiana: `context/archive/2026-11-09-category-list-after-migration/plan-brief.md`
- Wzorce: `app/Actions/JoinDraftBudgets.php`, `app/Actions/RestoreCategory.php:33-44`, `resources/js/components/BudgetCategoryList.vue:232-255`, `resources/js/components/BudgetCategoryRowActions.vue:94-148`
- Reguły: `.ai/rules/categories.md`, `.ai/rules/snapshot.md`, `.ai/rules/migrations.md`, `.ai/rules/js.md`, `.ai/rules/js-dialogs.md`, `.ai/rules/js-month-plan.md`, `.ai/rules/js-operations.md`, `.ai/rules/inertia-props.md`, `.ai/rules/wayfinder.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Domena i dane

#### Automated

- [x] 1.1 Test migracji opisu przechodzi na PostgreSQL — 768bd7d
- [x] 1.2 UserSnapshotCommandTest przechodzi, w tym przywrócenie fixture v6 i v5 — 768bd7d
- [x] 1.3 Pint bez zmian po formatowaniu — 768bd7d

#### Manual

- [x] 1.4 ADR-0066 i wpis słownika zgodne z ADR-0063 i decyzjami z planu — 768bd7d

### Phase 2: Zapisy listy (backend)

#### Automated

- [x] 2.1 Testy zapisów listy, strony Kategorie, archiwum, Budżetu i kolejności przechodzą — 75627f7
- [x] 2.2 Te same testy przechodzą na PostgreSQL — 75627f7
- [x] 2.3 Sloppy bez nowych znalezisk — 75627f7
- [x] 2.4 npm run build i types:check przechodzą — 75627f7

#### Manual

- [x] 2.5 Strona Budżet: dodanie, edycja, archiwum i przywracanie działają jak przed zmianą — 75627f7

### Phase 3: Widok „Kategorie”

#### Automated

- [x] 3.1 npm run build i types:check przechodzą — 5f5fc0e
- [x] 3.2 npm run check i check:ui przechodzą — 5f5fc0e
- [x] 3.3 npm run test przechodzi — 5f5fc0e
- [x] 3.4 CategoriesHardcodedUiTest i CategoriesKitchenSinkTest przechodzą — 5f5fc0e
- [x] 3.5 E2E categories.spec.ts przechodzi lokalnie — 5f5fc0e

#### Manual

- [x] 3.6 Kitchen sinki Kategorii i dialogów pokazują wszystkie stany w obu motywach — 5f5fc0e
- [x] 3.7 Przeciąganie działa myszą i na telefonie, kolejność zostaje po odświeżeniu — 5f5fc0e
- [x] 3.8 Wiersz z opisem i akcjami mieści się na telefonie bez poziomego przewijania — 5f5fc0e

### Phase 4: Wybór w operacjach i Imporcie

#### Automated

- [ ] 4.1 Testy wydatków, przychodów, Importu i przebudowy przechodzą
- [ ] 4.2 Te same testy przechodzą na PostgreSQL
- [ ] 4.3 npm run test przechodzi, w tym operations-category-options.test.ts
- [ ] 4.4 npm run build i types:check przechodzą
- [ ] 4.5 Sloppy bez nowych znalezisk

#### Manual

- [ ] 4.6 Nowa kategoria do wyboru w wydatku 2026 i po zapisie w Budżecie 2026 z planem 0
- [ ] 4.7 Edycja operacji na zarchiwizowanej kategorii pokazuje dopisek i zapisuje poprawkę
- [ ] 4.8 Import z wierszem na nowej kategorii zatwierdza się i tworzy pozycję

### Phase 5: Plan miesiąca

#### Automated

- [ ] 5.1 Testy zmiany, zatwierdzenia, strony i propozycji planu miesiąca oraz sinka przechodzą
- [ ] 5.2 Te same testy przechodzą na PostgreSQL
- [ ] 5.3 Pełna bramka composer ci:check i sloppy ci
- [ ] 5.4 npm run build, types:check, test i check:ui przechodzą

#### Manual

- [ ] 5.5 Nowa kategoria jako wiersz dołączenia w zmianie planu, kwota zapisana i widoczna w Budżecie
- [ ] 5.6 Do wydania miesięcy bez nowych kwot bez zmian
