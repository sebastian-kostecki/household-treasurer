# Grupy kategorii — plan implementacji

## Overview

S-22 z roadmapy M-3 (FR-004, FR-019, FR-021, FR-022): użytkownik tworzy, nazywa, porządkuje i usuwa grupy kategorii (przychodów albo wydatków), przypisuje do nich kategorie i widzi kategorie w grupach w „Kategoriach”, w Budżecie rocznym (z sumą grupy) i w selektorze kategorii operacji. Grupa tylko sumuje: nie ma planu, ustawień ani archiwum (ADR-0063, słownik „Category Group”). To pierwszy wycinek strumienia B; S-25 (plan miesiąca w grupach) i S-28 (raport po grupach) budują na tym modelu.

## Current State Analysis

- **Model.** Kategoria to rekord listy użytkownika przez lata (`app/Models/Category.php`), plan i wykonanie roku są na `BudgetLine`. Grup nie ma w kodzie; słownik i ADR-0063 opisują je w całości („Category Group”, `context/foundation/glossary.md:43-45`; ADR-0063 „Category Group” i „Type and deletion”).
- **Kolejność.** `categories.sort_order` to jedna kolejność listy typu przez lata. `ReorderCategories` (`app/Actions/ReorderCategories.php:22-58`) przyjmuje widoczny podzbiór (aktywne pozycje jednego budżetu), wpisuje go w zajęte przez niego miejsca i przenumerowuje całą listę typu 0…n−1; kategorie ukryte (inne lata, archiwalne) zostają na miejscu. `BudgetLine::scopeOrdered` (`app/Models/BudgetLine.php:112-123`) sortuje po `categories.sort_order`, a czyta go 11 miejsc (Budżet, Dashboard, plan miesiąca, `MonthToSpend`, `CreateBudget`, okna operacji, Import, paleta, `DashboardNotices`, `HandEntryMonthRemainingInputs`).
- **„Kategorie”.** `CategoryController@index` → `CategoryListReading::forUser` (`app/Support/CategoryListReading.php:24-49`): propsy `incomeCategories`, `expenseCategories`, `archivedCategories` (bez kwot). Widok `resources/js/pages/Categories.vue` jest tylko do odczytu; edycję kategorii w nim dodaje S-19.
- **Budżet.** `BudgetController@show` (`app/Http/Controllers/BudgetController.php:31-101`) ładuje pozycje `ordered()`, przy wyłączonym `show_archived` odfiltrowuje archiwalne, sumy liczy na serwerze `BudgetPlanSummary::forType` (`app/Support/BudgetPlanSummary.php:74-99`) z tej samej kolekcji, więc przy `show_archived=1` „Razem” obejmuje też archiwalne. `BudgetCategoryList.vue` renderuje aktywne wiersze w `vuedraggable` (`:232-255`), wiersz „Razem” (`:263-326`) i zwijaną sekcję archiwum (`:329-372`); przeciąganie jest wyłączone przy filtrze nazwy (`:88-89`). „Pozostało” jest ze znakiem (`CategoryExecutionMetrics::remainingMinorUnits`), więc suma wierszy równa się sumie planów minus suma wykonań.
- **Selektory.** `CategorySelect.vue` (reka-ui `Select`) w `ExpenseFormDialog.vue:541-551`, `IncomeFormDialog.vue:430-440` i `ImportPreviewRow.vue:606-645`; opcje z `OperationsController.php:150-184` i `ImportCategoryOptions.php:22-68`, typ `CategoryPickerOption` (`resources/js/lib/category-appearance.ts:297-300`). Opcje są płaskie, w kolejności rok → `ordered()`.
- **Snapshot.** `BackupUserData::SNAPSHOT_VERSION = 6` (`app/Actions/BackupUserData.php:19`). `RestoreUserData` rozpoznaje format przez `=== SNAPSHOT_VERSION` (`:30`, `:42`, `:112`) i listy wersji (`:90`, `:100`, `:104`, `:108`); podbicie stałej bez zmiany tych porównań wrzuciłoby v6 w ścieżkę v1–v5. `ResetUserData.php:24-27` usuwa budżety, potem kategorie.
- **Spójność w bazie.** Repo nie ma złożonych kluczy obcych ani CHECK; zgodność między wierszami pilnują akcje (np. `CategoryTypeChange`). Unikalność nazwy kategorii to `name_key` liczony w PHP (`CategoryNameKey`) i `unique(user_id, type, name_key)` (`database/migrations/2026_11_09_122524_…php:60`).

## Desired End State

- W „Kategoriach” każda sekcja typu ma „Dodaj grupę”; grupa ma wiersz z nazwą, liczbą kategorii i menu „⋯” (Zmień nazwę, W górę, W dół, Usuń grupę); kategoria ma „Grupa…” z wyborem grupy tego typu albo „Bez grupy”. Grupy i kategorie bez grupy stoją w jednej kolejności typu, kategorie w grupie mają swoją kolejność, a przeciąganie (także między grupami i poza grupę) działa w „Kategoriach” i w Budżecie.
- W Budżecie rocznym grupa ma zwijany wiersz (domyślnie rozwinięty) z tymi samymi kolumnami co „Razem” i sygnałem „ponad plan”, gdy którakolwiek jej aktywna kategoria przekracza plan. Suma grupy liczy tylko aktywne pozycje stojące w grupie (równa się sumie jej widocznych wierszy); archiwalne pozycje zostają w sekcji archiwum. Grupy, kategorie bez grupy i sekcja archiwum razem równają się „Razem” co do grosza, także przy „Pokaż zarchiwizowane”. Grupa bez aktywnej pozycji w tym budżecie nie ma wiersza.
- `CategorySelect` w oknach wydatku/przychodu i w podglądzie Importu pokazuje kategorie bez grupy jako opcje, a grupy jako podpisy nad ich kategoriami, w kolejności listy.
- `user:backup` pisze v7 z grupami; v1–v6 wczytują się bez grup.
- Sumy Budżetu, Dashboard, plan miesiąca i liczby kontrolne (`app:control-numbers`) są takie jak przed wdrożeniem.

### Key Discoveries:

- Jeden ciąg kolejności na typ wystarcza wszystkim czytelnikom `ordered()` bez zmiany zapytania — `app/Models/BudgetLine.php:112-123`.
- Algorytm „wpisz w zajęte miejsca, ukryte zostają” do uogólnienia na drzewo — `app/Actions/ReorderCategories.php:64-88`.
- Sumy Budżetu na serwerze z jednej kolekcji (przy `show_archived=1` z archiwalnymi); suma grupy liczy aktywne pozycje tej kolekcji, archiwalne domyka sekcja archiwum — `app/Support/BudgetPlanSummary.php:74-99`, `app/Http/Controllers/BudgetController.php:56-88`.
- Zmiana typu kategorii jest tylko w `UpdateCategory::handle` — `app/Actions/UpdateCategory.php:34-56`.
- Sztywne porównania wersji snapshotu — `app/Actions/RestoreUserData.php:30`, `:42`, `:112`; reguła podbicia wersji — `.ai/rules/snapshot.md:13-19`.
- Precedens wiersza grupy w siatce: `.month-plan-group-row` (`resources/css/app.css:941`); okno potwierdzenia: `ConfirmActionDialog` (`resources/js/components/BudgetCategoryRowActions.vue:13`).

## What We're NOT Doing

- **Plan miesiąca w grupach** (FR-008): S-25. Plan miesiąca dostaje tylko nową kolejność z `ordered()` (kategorie grupy obok siebie), bez wierszy grup.
- **Raporty po grupach** (FR-014, FR-015): S-28.
- **Filtr Operacji i paleta ⌘K z grupami**: zostają płaskie (decyzja planu); filtr po grupie może przyjść z S-31.
- **Zmiana typu grupy**: typ jest stały od utworzenia; pomyłkę naprawia usunięcie grupy i nowa grupa.
- **Plan, limit, opis, ikona, kolor i archiwum grupy, podgrupy** (PRD Non-Goals, ADR-0063).
- **Edycja, archiwum i przywracanie kategorii w „Kategoriach”** oraz selektor z aktywnych kategorii listy z zachowaniem archiwalnej wybranej (FR-019, S-19). S-22 dodaje do wiersza kategorii tylko „Grupa…”; wiersze sekcji archiwum nie mają akcji.
- **Zmiana zachowania „Razem” przy „Pokaż zarchiwizowane”** (dziś liczy archiwalne): zostaje. Suma grupy liczy tylko aktywne pozycje grupy i nie zmienia się po przełączeniu; różnicę do „Razem” stanowi sekcja archiwum.
- **Zapamiętywanie zwinięcia grup** między wizytami: stan tylko na czas strony.
- **Złożony klucz obcy typu grupy**: spójność typu pilnują akcje i test na PostgreSQL.

## Implementation Approach

Najpierw dokumenty (ADR-0066, słownik), potem schemat i czysta klasa kolejności, potem snapshot, a na końcu trzy widoki. Kolejność to **jeden ciąg `sort_order` na typ**, wspólny dla `category_groups` i `categories`: grupa zajmuje miejsce, jej kategorie stoją zaraz za nią (Kredyt 0, Opłaty stałe 1, Czynsz 2, Prąd 3, Wakacje 4, Zdrowie 5, Apteka 6). Dzięki temu `ordered()` się nie zmienia, a każdy płaski czytelnik dostaje kategorie grupy obok siebie. Każdy zapis kolejności lub przynależności idzie przez jedną klasę: zbuduj drzewo, zastosuj zmianę, spłaszcz, przenumeruj 0…n−1. Drzewo do wyświetlenia buduje się z (przynależność, `sort_order`), więc jest jednoznaczne nawet przy niespójnym ciągu (np. po ręcznym restore).

## Critical Implementation Details

- **Zapis drzewa z widoku podzbioru.** Budżet widzi tylko aktywne pozycje jednego roku i grupy z co najmniej jedną z nich; „Kategorie” widzą aktywne kategorie i wszystkie grupy typu. Akcja przyjmuje drzewo widocznych elementów, sprawdza, że zbiór widocznych kategorii i grup jest dokładnie oczekiwany (jak dziś `ReorderCategories:24-41`), przypisuje widocznym kategoriom grupę z drzewa, a ukryte zostawia w ich grupie i ich miejscu: widoczne elementy wypełniają w nowej kolejności miejsca, które zajmowały w obecnym ciągu, po czym drzewo jest odbudowane z przynależności i spłaszczone. Ukryta kategoria grupy po przeniesieniu grupy jedzie razem z grupą.
- **Usunięcie grupy.** Jej kategorie zostają w ciągu na swoich miejscach jako kategorie bez grupy; znika tylko miejsce grupy. Akcja zeruje `category_group_id` jawnie przed usunięciem (FK `nullOnDelete` jest tylko asekuracją) i przenumerowuje ciąg.
- **Nowe miejsce.** Nowa grupa, nowa kategoria (`CreateCategory::createListCategory`, dziś `max(sort_order) + 1` tylko z kategorii) i kategoria po zmianie typu trafiają na koniec ciągu typu liczonego z kategorii i grup. „Grupa…” stawia kategorię na końcu wybranej grupy albo, przy „Bez grupy”, na końcu ciągu.

## Faza 1: Domena, schemat i kolejność

### Overview

Decyzje planu trafiają do ADR i słownika, powstaje tabela grup, przynależność kategorii, model z polityką i jedna klasa kolejności drzewa, na której stoją wszystkie późniejsze zapisy.

### Changes Required:

#### 1. ADR i słownik

**File**: `context/foundation/adr/0066-category-group-order-and-budget-rows.md`, `context/foundation/glossary.md`

**Intent**: Zapisać decyzje, których ADR-0063 nie rozstrzyga, zanim powstanie kod (twarda reguła AGENTS.md).

**Contract**: ADR-0066: typ grupy stały od utworzenia; jedna kolejność typu wspólna dla grup i kategorii bez grupy, kategorie grupy po niej; przeciąganie w „Kategoriach” i w Budżecie zmienia kolejność i grupę (atrybut wszystkich lat); wiersz grupy w Budżecie z sumami aktywnych pozycji grupy (archiwalne w sekcji archiwum; grupy + bez grupy + archiwum = „Razem”, także przy „Pokaż zarchiwizowane”), sygnałem „ponad plan” przy przekroczeniu którejkolwiek aktywnej kategorii i bez wiersza dla grupy bez aktywnej pozycji; odrzucone także: suma grupy z archiwalnymi przy „Pokaż zarchiwizowane” (wiersz grupy bez widocznych kategorii, suma zależna od przełącznika); selektor operacji pokazuje grupy jako podpisy; odrzucone: „Bez grupy” jako blok na końcu/początku, przeciąganie bez zmiany grupy, zmiana typu grupy, złożony FK. Słownik, „Category Group”: zdanie o stałym typie i o miejscu w kolejności listy typu; „Category”: sort order jako miejsce w tej kolejności.

#### 2. Migracja

**File**: `database/migrations/<ts>_create_category_groups_table.php`

**Intent**: Nowa tabela i przynależność kategorii, bez przenoszenia danych (po wdrożeniu grup nie ma, ADR-0063 krok (f)).

**Contract**: `category_groups`: `id`, `user_id` (FK cascade), `name` (255), `name_key`, `type`, `sort_order` (int), timestamps; `unique(user_id, type, name_key)`, `index(user_id, type, sort_order)`. `categories.category_group_id` nullable FK → `category_groups` z `nullOnDelete`, z indeksem. `down()` usuwa kolumnę i tabelę. Istniejący `sort_order` kategorii nie musi być ciągiem 0…n−1 na typ: zmiana typu nie przenumerowuje (`UpdateCategory.php:44-50`), seeder demo numeruje oba typy razem (`DemoHouseholdSeeder.php:267`), a `createListCategory` liczy max bez blokady, więc są dziury i mogą być remisy. Migracja niczego nie przenumerowuje; porządek wyznacza reguła remisu z §4, a pierwszy zapis kolejności typu przenumerowuje ciąg.

#### 3. Model, fabryka, polityka

**File**: `app/Models/CategoryGroup.php`, `database/factories/CategoryGroupFactory.php`, `app/Policies/CategoryGroupPolicy.php`, `app/Models/Category.php`, `app/Models/User.php`

**Intent**: Grupa jako model z `name_key` liczonym jak w kategorii, relacje i dostęp tylko właściciela.

**Contract**: `CategoryGroup` z castem `type` na `CategoryType`, hookiem `saving` dla `name_key` (`CategoryNameKey::of`), relacjami `user()` i `categories()`; `Category::group()` (`belongsTo`, kolumna `category_group_id`) i `@property int|null $category_group_id`; `User::categoryGroups()`. Polityka: `update`, `delete` gdy `user_id` się zgadza.

#### 4. Klasa kolejności drzewa

**File**: `app/Support/CategoryList/CategoryListOrder.php`

**Intent**: Jedno miejsce, które z obecnego stanu typu i zmiany (drzewo widocznych elementów, przypisanie do grupy, usunięcie grupy, dopisanie na koniec) liczy nowy ciąg `sort_order` i przynależność. Klasa czysta na tablicach, żeby testy jednostkowe pokryły przypadki bez bazy.

**Contract**: wejście: lista grup `{id, sort_order}` i kategorii `{id, group_id, sort_order}` jednego typu oraz operacja; wyjście: mapy `group_id → sort_order` i `category_id → {group_id, sort_order}` z ciągiem 0…n−1, grupą przed swoimi kategoriami. Operacja drzewa przyjmuje `list<{kind: 'group', id, category_ids: list<int>}|{kind: 'category', id}>` i zbiór widocznych id. Reguła remisu, jedna dla tej klasy i `buildCategoryTree` (TS): `sort_order` → grupa przed kategorią → `name_key` → `id` (zgodna z `BudgetLine::scopeOrdered`, które dla kategorii rozstrzyga remis przez `name_key`, potem id); test remisu po obu stronach.

#### 5. Zapis kolejności i przynależności w akcjach

**File**: `app/Actions/CreateCategory.php`, `app/Actions/UpdateCategory.php`, `app/Actions/ResetUserData.php`

**Intent**: Nowa kategoria na końcu ciągu z grupami; zmiana typu zdejmuje kategorię z grupy i stawia ją na końcu ciągu nowego typu (ADR-0063); reset usuwa też grupy.

**Contract**: `createListCategory` (i `CreateCategoryGroup` w fazie 3) liczy następne miejsce z `max` po kategoriach i grupach typu pod `lockForUpdate` na wierszach typu, w transakcji; `UpdateCategory` przy zmianie typu ustawia `category_group_id = null` i nowe `sort_order`; `ResetUserData` usuwa `category_groups` użytkownika po kategoriach (albo zeruje przynależność przed nimi). Kategoria dołączona do istniejącej przez `CreateCategory` (`:43-54`) nie zmienia grupy.

#### 6. Testy

**File**: `tests/Unit/CategoryListOrderTest.php`, `tests/Feature/Migrations/CategoryGroupsMigrationTest.php`, `tests/Feature/CategoryListBudgetPageTest.php` (rozszerzenie testów zmiany typu `:194`, `:218`, `:254` i tworzenia kategorii: zdjęcie z grupy, miejsce na końcu ciągu nowego typu, nowa kategoria za grupą na końcu ciągu)

**Intent**: Przypadki kolejności (przeniesienie grupy z ukrytą kategorią, kategoria do innej grupy, poza grupę, usunięcie grupy w środku ciągu, podzbiór Budżetu, odbudowa z niespójnego ciągu), migracja i `down()` na PostgreSQL, zmiana typu zdejmuje z grupy.

**Contract**: wzorzec `tests/Feature/Migrations/CategoryListMigrationTest.php`; unikalność `name_key` grupy na PostgreSQL.

### Success Criteria:

#### Automated Verification:

- Testy jednostkowe kolejności przechodzą: `php artisan test --compact tests/Unit/CategoryListOrderTest.php`
- Migracja, `down()` i unikalność nazwy grupy przechodzą na PostgreSQL (komenda worktree z AGENTS.md): `… artisan test --compact tests/Feature/Migrations/CategoryGroupsMigrationTest.php`
- Testy zmiany typu i tworzenia kategorii przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/CategoryListBudgetPageTest.php tests/Feature/BudgetTest.php`
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- ADR-0066 i wpis słownika opisują decyzje z tej rozmowy (typ stały, wspólna kolejność, przeciąganie zmienia grupę, wiersz grupy w Budżecie)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 2: Snapshot v7

### Overview

Kopia użytkownika obejmuje grupy i przynależność; każda starsza wersja nadal się wczytuje (FR-022, `.ai/rules/snapshot.md`).

### Changes Required:

#### 1. Zapis

**File**: `app/Actions/BackupUserData.php`

**Intent**: Nowy klucz `category_groups` i pole `category_group_id` kategorii.

**Contract**: `SNAPSHOT_VERSION = 7`; `category_groups: list<{id, name, type, sort_order, created_at, updated_at}>` (bez `name_key`, liczony przy restore jak w kategoriach); `serializeCategory` dodaje `category_group_id`.

#### 2. Odczyt

**File**: `app/Actions/RestoreUserData.php`

**Intent**: v7 tworzy grupy przed kategoriami i mapuje przynależność; v6 idzie ścieżką listy bez grup; v1–v5 jak dziś, bez grup.

**Contract**: porównania `=== SNAPSHOT_VERSION` (`:30`, `:42`, `:112`) zamienione na „format listy” (v6 i v7); 6 dopisane do list wersji (`:90`, `:100`, `:104`, `:108`); v7 wymaga `category_groups` i odrzuca grupę o duplikacie (type, name_key), kategorię wskazującą nieznaną grupę albo grupę innego typu; brak klucza/pola w v1–v6 daje `category_group_id = null`. Restore nie przelicza `sort_order` (zachowuje ciąg z kopii).

#### 3. Testy

**File**: `tests/Feature/UserSnapshotCommandTest.php`

**Intent**: Round trip v7 z grupami, wczytanie v6 (bieżący wynik bez grup i z `version = 6`), odrzucenie niespójnej v7; asercje wersji `6` → `7`.

**Contract**: nowy helper w stylu `asLegacySnapshot` (`:745-781`) dla v6; istniejące testy v1–v5 przechodzą bez zmian asercji poza numerem wersji.

### Success Criteria:

#### Automated Verification:

- Testy snapshotu przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/UserSnapshotCommandTest.php`
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- `user:backup` na devie z jedną grupą daje plik v7 z `category_groups`, a `user:restore` tego pliku odtwarza grupę i przynależność

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 3: Grupy w „Kategoriach”

### Overview

Użytkownik zarządza grupami i przynależnością w widoku listy: dodaje, zmienia nazwę, przesuwa, usuwa grupę, przypisuje kategorię przez „Grupa…” albo przeciąga.

### Changes Required:

#### 1. Trasy, żądania, akcje

**File**: `routes/web.php`, `app/Http/Controllers/CategoryGroupController.php`, `app/Http/Requests/StoreCategoryGroupRequest.php`, `UpdateCategoryGroupRequest.php`, `AssignCategoryGroupRequest.php`, `ReorderCategoryListRequest.php`, `app/Actions/CreateCategoryGroup.php`, `RenameCategoryGroup.php`, `DeleteCategoryGroup.php`, `AssignCategoryGroup.php`, `ReorderCategoryList.php`

**Intent**: Zapis grup i drzewa przez `CategoryListOrder`; błąd duplikatu nazwy jako błąd walidacji `name` (jak `UpdateCategory:70-76`); przekierowanie do `categories.index` z flashem.

**Contract**:
- `POST categories/groups` `{type, name}` → `category-groups.store`
- `PATCH categories/groups/{categoryGroup}` `{name}` → `category-groups.update`
- `DELETE categories/groups/{categoryGroup}` → `category-groups.destroy`
- `PATCH categories/{category}/group` `{category_group_id: int|null}` → `categories.group`; grupa musi należeć do użytkownika i mieć typ kategorii (archiwalna kategoria odrzucona)
- `PATCH categories/reorder` `{type, items}` → `categories.reorder-list`; oczekiwany zbiór: aktywne kategorie i wszystkie grupy typu
- „W górę / W dół” liczy nowe drzewo na kliencie i wysyła `categories.reorder-list` (bez osobnej trasy)
- `ReorderCategoryList::handle(User, CategoryType, items, visibleCategoryIds, visibleGroupIds)` jest wspólna z fazą 4

#### 2. Odczyt listy

**File**: `app/Support/CategoryListReading.php`, `resources/js/types/categories.ts`

**Intent**: Widok dostaje grupy i przynależność, drzewo buduje czysta funkcja na kliencie.

**Contract**: wiersze kategorii dostają `group_id: int|null`; nowe propsy `incomeGroups`, `expenseGroups`: `list<{id, name, sort_order}>`; kategorie i grupy w kolejności ciągu. Zmiana po obu stronach w jednym commicie z `assertInertia`.

#### 3. Drzewo na kliencie

**File**: `resources/js/lib/category-tree.ts`, `resources/js/lib/category-tree.test.ts`

**Intent**: Jedna funkcja buduje drzewo (grupy i kategorie bez grupy w kolejności ciągu, kategorie grupy w swojej kolejności) dla „Kategorii”, Budżetu i `CategorySelect`, druga zamienia drzewo na payload `items`, trzecia przesuwa grupę o jedno miejsce.

**Contract**: `buildCategoryTree(groups, categories)` → `list<{kind: 'group', group, categories} | {kind: 'category', category}>`, z regułą remisu z fazy 1 §4 (grupa i kategoria potrzebują w propsach `sort_order` i `name_key` albo serwer podaje je już w tej kolejności); `treeToReorderItems(tree)`; `moveTopLevelItem(tree, groupId, direction)`.

#### 4. Widok

**File**: `resources/js/pages/Categories.vue`, `resources/js/components/CategoryListRow.vue`, nowe `CategoryGroupRow.vue`, `CategoryGroupFormDialog.vue`, `CategoryGroupPicker.vue` (menu „Grupa…”), `resources/css/app.css`, `lang/pl/ui.php`

**Intent**: Sekcja typu z „Dodaj grupę”, wiersze grup z liczbą kategorii i menu „⋯”, kategorie wcięte pod grupą, „Grupa…” w wierszu aktywnej kategorii, zagnieżdżone `vuedraggable` (grupy i kategorie bez grupy na poziomie typu, kategorie w grupach; grupy nie wchodzą do grup), usunięcie przez `ConfirmActionDialog` z informacją „N kategorii zostanie bez grupy”. Pusta grupa ma wiersz z podpowiedzią i przyjmuje upuszczenie.

**Contract**: nazwane klasy `.categories-group-row`, `.categories-row-nested` w `@layer components` (tokeny, bez wartości arbitralnych, przepis `section-title`/`table-header` jak w design-system); reguły `.ai/rules/js.md`, `js-dialogs.md`, `wayfinder.md`, `inertia-props.md`.

#### 5. Kitchen sink, design system, E2E

**File**: `resources/js/pages/dev/CategoriesKitchenSink.vue` (i jego kontroler), `resources/js/lib/categories-fixtures.ts`, `context/foundation/design-system.md`, `tests/e2e/categories.spec.ts`, `scripts/check-ui-literals.mjs`, `resources/js/ui-contract.test.ts`, `tests/Unit/CategoriesHardcodedUiTest.php`

**Intent**: Każdy stan widoku w kitchen sinku (bez grup, z grupami, pusta grupa, grupa rozwinięta z wieloma kategoriami, okno grupy z błędem nazwy, potwierdzenie usunięcia, menu „Grupa…”); opis wiersza grupy w design-system; E2E: utwórz grupę, przypisz kategorię przez „Grupa…”, przeciągnij kategorię do grupy, usuń grupę.

**Contract**: `npm run check:ui` pilnuje widoków, ale skanuje stałą listę `FILES` (`scripts/check-ui-literals.mjs:96-108`): `CategoryGroupRow.vue`, `CategoryGroupFormDialog.vue` i `CategoryGroupPicker.vue` trafiają do tej listy, do `ui-contract.test.ts` i do `CategoriesHardcodedUiTest.php`. Fixtury `categories-fixtures.ts` (typ `CategoriesPageProps`) dostają `incomeGroups`/`expenseGroups` i `group_id` w tym samym commicie co propsy.

#### 6. Testy

**File**: `tests/Feature/CategoryGroupTest.php`, `tests/Feature/CategoriesPageTest.php` (istniejący test „Kategorii”, `assertInertia` kształtu i kolejności `:48-129`), `tests/Feature/CategoriesKitchenSinkTest.php`

**Intent**: Tworzenie, duplikat nazwy w typie (ta sama nazwa w drugim typie dozwolona), zmiana nazwy, usunięcie zostawia kategorie bez grupy na miejscu, przypisanie do grupy innego typu i cudzej grupy odrzucone, zapis drzewa z niepełnym zbiorem odrzucony, propsy `assertInertia`.

### Success Criteria:

#### Automated Verification:

- Testy grup i widoku „Kategorie” przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/CategoryGroupTest.php tests/Feature/CategoriesPageTest.php tests/Feature/CategoriesKitchenSinkTest.php tests/Unit/CategoriesHardcodedUiTest.php`
- Testy `category-tree.test.ts` przechodzą: `npm run test`
- `npm run build`, `npm run types:check`, `npm run check:ui` przechodzą
- E2E `tests/e2e/categories.spec.ts` przechodzi lokalnie
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- W „Kategoriach” na devie: grupa „Zdrowie” z Apteką i Badaniami, przeciągnięcie Wakacji do grupy i z powrotem, „W górę / W dół”, usunięcie grupy zostawia kategorie na miejscu
- Widok działa na telefonie (menu „Grupa…” zamiast przeciągania) i w ciemnym motywie

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 4: Budżet w grupach

### Overview

Budżet roczny pokazuje kategorie w grupach z wierszem sumy grupy, zwijaniem i sygnałem „ponad plan”, a przeciąganie w Budżecie zapisuje drzewo tą samą akcją co „Kategorie”.

### Changes Required:

#### 1. Sumy grup na serwerze

**File**: `app/Support/BudgetPlanSummary.php`, `app/Http/Controllers/BudgetController.php`

**Intent**: Suma grupy z aktywnych pozycji grupy w tej samej kolekcji co „Razem” typu, więc grupy, kategorie bez grupy i sekcja archiwum dodają się do „Razem” co do grosza (przy `show_archived=1` archiwalne pozycje są tylko w archiwum); grupy tylko z co najmniej jedną aktywną pozycją w tym budżecie.

**Contract**: wiersz kategorii dostaje `group_id: int|null` (przynależność z eager loadingu `category.group`, bez zapytań w pętli); nowe propsy `incomeGroups`, `expenseGroups`: `list<{id, name, sort_order, totals: {initial_plan_minor_units, current_plan_minor_units, execution_minor_units, remaining_minor_units, percent_complete}, has_over_limit_category: bool}>` (`totals` i `has_over_limit_category` tylko z aktywnych pozycji grupy; `has_over_limit_category` tylko dla wydatków); `BudgetPlanSummary` dostaje metodę sum grupy wspólną z `forType`. Typ TS w `BudgetCategoryList.vue`/`Budget.vue` w tym samym commicie z `assertInertia`. Zaktualizować nieaktualny phpdoc `categoryPayload` (`:179-199`).

#### 2. Zapis drzewa z Budżetu

**File**: `app/Http/Controllers/CategoryController.php` (`reorder`), `app/Http/Requests/ReorderCategoriesRequest.php`, `app/Actions/ReorderCategories.php`

**Intent**: Trasa `categories.reorder` przyjmuje drzewo widocznych elementów i woła `ReorderCategoryList` z oczekiwanym zbiorem Budżetu (aktywne pozycje typu, grupy z co najmniej jedną z nich); stary płaski algorytm znika.

**Contract**: `PATCH budgets/{budget}/categories/reorder` `{type, items}` (zamiast `category_ids`); `BudgetPolicy::reorder` bez zmian; `ReorderCategories` usunięta albo zredukowana do wyliczenia zbioru widocznego. Istniejące testy przeciągania (`tests/Feature/CategoryReorderTest.php`) przechodzą na nowy payload z tymi samymi oczekiwaniami kolejności.

#### 3. Widok

**File**: `resources/js/components/BudgetCategoryList.vue`, nowe `BudgetCategoryGroupRow.vue`, `resources/js/pages/Budget.vue`, `resources/css/app.css`, `lang/pl/ui.php`

**Intent**: Drzewo z `buildCategoryTree`; wiersz grupy na siatce `.budget-category-grid` (w szkicu kolumna planu, w wykonaniu początkowy, aktualny, wykonanie, pozostało, licznik), przycisk zwijania z `aria-expanded`, znacznik „ponad plan”, gdy `has_over_limit_category`; zagnieżdżone `vuedraggable` jak w „Kategoriach”; przy filtrze nazwy przeciąganie wyłączone jak dziś, a grupy pokazują tylko pasujące kategorie; sekcja archiwum bez grup jak dziś.

**Contract**: nazwana klasa `.budget-category-group-row` (wariant draft), stan zwinięcia lokalny w komponencie, domyślnie rozwinięty.

#### 4. Kitchen sink i design system

**File**: `resources/js/pages/dev/BudgetKitchenSink.vue`, `context/foundation/design-system.md`, `scripts/check-ui-literals.mjs`, `resources/js/ui-contract.test.ts`

**Intent**: Wszystkie 8 użyć `BudgetCategoryList` w kitchen sinku (`:279-414`, `:can-reorder="true"`, `:budget-id="1"`, więc przeciąganie wysyła prawdziwy PATCH) dostają propsy grup; `BudgetCategoryGroupRow.vue` trafia do `FILES` w `check-ui-literals.mjs` i do `ui-contract.test.ts`. Stany: grupy w szkicu i w wykonaniu, grupa zwinięta, grupa z przekroczeniem przy dodatnim saldzie (Zdrowie: Apteka 1 200/1 400, Badania 800/300 → 2 000/1 700, +300, „ponad plan”), grupa przychodów, Budżet bez grup.

**Contract**: opis wiersza grupy Budżetu obok `.month-plan-group-row` w design-system.

#### 5. Testy

**File**: `tests/Feature/BudgetCategoryGroupsTest.php`, `tests/Feature/CategoryReorderTest.php`

**Intent**: Suma grupy = suma jej aktywnych kategorii, grupy + kategorie bez grupy + archiwum = „Razem” (bez i z `show_archived`), grupa z jedną aktywną i jedną zarchiwizowaną pozycją ma tę samą sumę w obu trybach, grupa z samymi archiwalnymi pozycjami bez wiersza, a jej kwoty są w archiwum, przykład Zdrowia z sygnałem „ponad plan”, grupa bez aktywnej pozycji nieobecna, sumy Budżetu i `planSummary` identyczne jak bez grup, przeciągnięcie kategorii do grupy w Budżecie zmienia grupę i kolejność, ukryta kategoria grupy jedzie z grupą.

### Success Criteria:

#### Automated Verification:

- Testy Budżetu i przeciągania przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/BudgetCategoryGroupsTest.php tests/Feature/CategoryReorderTest.php tests/Feature/BudgetTest.php`
- `npm run build`, `npm run types:check`, `npm run check:ui` przechodzą
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Budżet 2026 na devie z grupami: zwijanie, „ponad plan” na zwiniętej grupie, „Razem” bez zmian względem stanu przed grupami
- Przeciągnięcie kategorii do grupy w Budżecie 2026 widać w „Kategoriach” i w szkicu 2027
- Szkic 2027 pokazuje grupy z kolumną planu; telefon i ciemny motyw

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 5: Grupy w selektorze

### Overview

`CategorySelect` pokazuje grupy jako podpisy nad ich kategoriami w każdym wyborze kategorii operacji.

### Changes Required:

#### 1. Opcje z grupą

**File**: `app/Http/Controllers/OperationsController.php` (`:150-184`), `app/Support/CsvImport/ImportCategoryOptions.php`, `app/Http/Controllers/CsvImportController.php`

**Intent**: Każda opcja niesie grupę, bez zapytań w pętli (eager loading `category.group`); kolejność bez zmian (rok → `ordered()`), więc kategorie grupy stoją obok siebie.

**Contract**: opcja dostaje `group: {id: int, name: string}|null`; typy TS: `ExpenseFormCategory`, `IncomeFormCategory` (`ExpenseFormDialog.vue:47-63`, `IncomeFormDialog.vue:44-52`), `CategoryRow` (`resources/js/lib/import-preview-rows.ts:25-32`) i `CategoryPickerOption` (`resources/js/lib/category-appearance.ts:297-300`) z polem opcjonalnym `group`. Uzupełnić niepełne phpdoc `ImportCategoryOptions`.

#### 2. Komponent

**File**: `resources/js/components/CategorySelect.vue`

**Intent**: Kolejne opcje tej samej grupy renderowane w `SelectGroup` z `SelectLabel` (nazwa grupy), kategorie bez grupy jako zwykłe opcje na swoim miejscu; bez grup wygląd jak dziś.

**Contract**: bez zmiany API komponentu poza polem `group` w opcji; grupowanie kolejnych opcji w czystej funkcji w `resources/js/lib/category-tree.ts` z testem.

#### 3. Testy i kitchen sink

**File**: `tests/Feature/ExpenseTest.php`, `tests/Feature/CsvImportTest.php` (asercje propsów), `resources/js/lib/category-tree.test.ts`, kitchen sink okien (`resources/js/pages/dev/DialogsKitchenSink.vue`) i podglądu Importu (`ImportPreviewSink.vue`)

**Intent**: Propsy niosą grupę; selektor z grupami widoczny w kitchen sinkach.

### Success Criteria:

#### Automated Verification:

- Testy propsów operacji i Importu przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/ExpenseTest.php tests/Feature/CsvImportTest.php`
- `npm run test`, `npm run build`, `npm run types:check`, `npm run check:ui` przechodzą
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Okno wydatku i przychodu oraz podgląd Importu na devie pokazują „Zdrowie” nad Apteką i Badaniami, a Kredyt bez podpisu; wybór z klawiatury działa jak dziś

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed zamknięciem zmiany.

---

## Testing Strategy

### Unit Tests:

- `CategoryListOrder`: przeniesienie grupy z ukrytą kategorią, kategoria do innej grupy i poza grupę, usunięcie grupy w środku ciągu, podzbiór Budżetu z kategoriami innych lat, dopisanie na koniec, odbudowa z niespójnego ciągu, remis `sort_order` (grupa z kategorią, dwie kategorie), zawsze ciąg 0…n−1 z grupą przed swoimi kategoriami.
- `category-tree.ts`: budowa drzewa, payload `items`, „W górę / W dół” na brzegach, grupowanie opcji selektora.

### Integration Tests:

- Migracja i `down()` na PostgreSQL; unikalność nazwy grupy w typie.
- Akcje grup i przynależności z autoryzacją (cudza grupa, inny typ, archiwalna kategoria).
- Budżet: grupy + bez grupy + archiwum = „Razem” co do grosza (z i bez `show_archived`), suma grupy tylko z aktywnych pozycji, sumy identyczne jak bez grup.
- Snapshot: round trip v7, wczytanie v6 i v1–v5.
- E2E „Kategorii”: grupa, „Grupa…”, przeciąganie, usunięcie.

### Manual Testing Steps:

1. Na devie utwórz w Wydatkach grupy „Opłaty stałe” i „Zdrowie”, przypisz Czynsz i Prąd oraz Aptekę i Badania.
2. Przeciągnij Wakacje do „Zdrowia” w Budżecie 2026, sprawdź „Kategorie” i szkic 2027, przeciągnij z powrotem.
3. Zwiń „Zdrowie” w Budżecie 2026 przy przekroczonej Aptece i sprawdź sygnał; porównaj „Razem” z liczbami sprzed zmiany.
4. Dodaj wydatek i sprawdź podpisy grup w selektorze; to samo w podglądzie Importu.
5. Usuń „Zdrowie” i sprawdź, że Apteka i Badania stoją na tym samym miejscu bez grupy.
6. `user:backup`, `user:reset-data`, `user:restore` i porównanie grup.

## Performance Considerations

Przynależność i nazwa grupy przez eager loading albo join (Sloppy oznacza zapytania w pętli, `.ai/rules/categories.md`). Sumy grup liczone z kolekcji już załadowanej przez `BudgetController`, bez nowych zapytań na grupę. Zapis drzewa to jedna transakcja z `lockForUpdate` na kategoriach i grupach typu i dwa `UPDATE … CASE` (jak dziś `ReorderCategories`).

## Migration Notes

Migracja tylko dodaje tabelę i kolumnę nullable, bez przenoszenia danych; liczby kontrolne (`app:control-numbers`) się nie zmieniają, a `deploy.sh` z S-18 robi kopię przed migracją. Wycofanie po wdrożeniu: `artisan down`, `migrate:rollback` ostatniej partii (usuwa kolumnę i tabelę, grupy przepadają), potem cofnięcie kodu (`git pull --ff-only` i `deploy.sh`), jak w `.ai/rules/migrations.md`. Snapshot v7 nie wczyta się w starym kodzie; zapasem są kopie v6 sprzed wdrożenia.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-22)
- PRD: `context/foundation/prd-v3.md` (FR-004, FR-019, FR-021, FR-022, decyzja strukturalna 1 i 10)
- ADR-0063: `context/foundation/adr/0063-one-category-list-across-years.md`
- Słownik: `context/foundation/glossary.md` („Category”, „Category Group”)
- Poprzednia zmiana: `context/archive/2026-11-09-category-list-after-migration/plan.md`
- Reguły: `.ai/rules/categories.md`, `migrations.md`, `snapshot.md`, `inertia-props.md`, `js.md`, `js-dialogs.md`, `wayfinder.md`, `reports.md`, `sloppy.md`
- Wzorce: `app/Actions/ReorderCategories.php:22-88`, `app/Support/BudgetPlanSummary.php:74-99`, `resources/js/components/BudgetCategoryList.vue:171-326`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Domena, schemat i kolejność

#### Automated

- [x] 1.1 Testy jednostkowe kolejności przechodzą — b5b9910
- [x] 1.2 Migracja, `down()` i unikalność nazwy grupy przechodzą na PostgreSQL — b5b9910
- [x] 1.3 Testy zmiany typu i tworzenia kategorii przechodzą na PostgreSQL — b5b9910
- [x] 1.4 Bramka: `composer ci:check` i `vendor/bin/sloppy ci` — b5b9910

#### Manual

- [x] 1.5 ADR-0066 i wpis słownika opisują decyzje z tej rozmowy — b5b9910

### Phase 2: Snapshot v7

#### Automated

- [x] 2.1 Testy snapshotu przechodzą na PostgreSQL — a674b07
- [x] 2.2 Bramka: `composer ci:check` i `vendor/bin/sloppy ci` — a674b07

#### Manual

- [x] 2.3 `user:backup` i `user:restore` na devie odtwarzają grupę i przynależność — a674b07

### Phase 3: Grupy w „Kategoriach”

#### Automated

- [x] 3.1 Testy grup i widoku „Kategorie” przechodzą na PostgreSQL — c452814
- [x] 3.2 Testy `category-tree.test.ts` przechodzą — c452814
- [x] 3.3 `npm run build`, `npm run types:check`, `npm run check:ui` przechodzą — c452814
- [x] 3.4 E2E `tests/e2e/categories.spec.ts` przechodzi lokalnie — c452814
- [x] 3.5 Bramka: `composer ci:check` i `vendor/bin/sloppy ci` — c452814

#### Manual

- [x] 3.6 Grupy, przeciąganie, „W górę / W dół” i usuwanie działają w „Kategoriach” na devie — c452814
- [x] 3.7 Widok działa na telefonie i w ciemnym motywie — c452814

### Phase 4: Budżet w grupach

#### Automated

- [x] 4.1 Testy Budżetu i przeciągania przechodzą na PostgreSQL
- [x] 4.2 `npm run build`, `npm run types:check`, `npm run check:ui` przechodzą
- [x] 4.3 Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual

- [x] 4.4 Budżet 2026 z grupami: zwijanie, „ponad plan”, „Razem” bez zmian
- [x] 4.5 Przeciągnięcie w Budżecie widać w „Kategoriach” i w szkicu 2027
- [x] 4.6 Szkic 2027 z grupami, telefon i ciemny motyw

### Phase 5: Grupy w selektorze

#### Automated

- [ ] 5.1 Testy propsów operacji i Importu przechodzą na PostgreSQL
- [ ] 5.2 `npm run test`, `npm run build`, `npm run types:check`, `npm run check:ui` przechodzą
- [ ] 5.3 Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual

- [ ] 5.4 Okna operacji i podgląd Importu pokazują podpisy grup
