# Kategorie w zakładkach z filtrami — Implementation Plan

## Overview

Wycinek S-37 (MS-04) roadmapy M-4 wdraża kartę `PageCategories` (wariant B) z design systemu Atrament. Widok „Kategorie” dostaje:

- zakładki Wydatki | Przychody | Archiwum z licznikami, z zakładką w adresie (`?tab=`);
- wspólną szukajkę po nazwie i opisie, bez polskich znaków i bez wielkości liter;
- filtr rytmu wydatków (chipy „Wszystkie rytmy”, Stała, Bieżąca, Roczna, Nieregularna z licznikami);
- kolumnę Rytm z `Badge` zamiast rytmu w podpisie;
- lata w budżecie jako pasek komórek na osi lat Budżetów użytkownika.

Zakładka Archiwum zastępuje zwijaną sekcję „Archiwum (n)” i ma dwie podsekcje typu. Przy aktywnej frazie albo filtrze rytmu nie ma przeciągania ani przesuwania grup, bo zapis kolejności przyjmuje tylko pełne drzewo typu.

Decyzja o układzie trafia do ADR-0099 w fazie 1, przed kodem. Zmiana nie rusza modelu danych, zapisu kolejności ani słownika.

## Current State Analysis

- `CategoryController@index` (`app/Http/Controllers/CategoryController.php:35-44`) zwraca `CategoryListReading::forUser()` oraz `rhythmChangeMonth` i `categoryAppearanceOptions`.
  - `incomeCategories` i `expenseCategories` to aktywne kategorie, `archivedCategories` to oba typy razem w kolejności listy (`app/Support/CategoryListReading.php:45-49, 95-101`).
  - Każda kategoria ma już `rhythm` z bieżącego miesiąca (null dla przychodu, :54, :69), `description`, `budget_years` (lata z Budget Line, rosnąco, :129-147), `archived_at` i `group_id`.
- Oś lat: współdzielony prop `shell.budgetYears: number[]` (`resources/js/types/shell.ts:11`, `app/Support/SelectedBudgetYear.php:54-63`) z wszystkimi latami Budżetów, szkic włącznie.
- `Categories.vue` (`resources/js/pages/Categories.vue`) renderuje dwa `ListSurface` jeden pod drugim (:294-447), z drzewem przez `buildCategoryTree` (:60-75), oraz `CategoryArchiveSection` (:449-454, `Collapsible` „Archiwum (n)”).
- Zapis kolejności:
  - `saveTree` (:214-248) wysyła całe drzewo typu na `PATCH categories/reorder`.
  - Serwer odrzuca niepełny zbiór kodem 422 (`CategoryListOrder::assertExactSet`, `app/Support/CategoryList/CategoryListOrder.php:325-348`; test `tests/Feature/CategoryGroupTest.php:238`).
  - „W górę”/„W dół” grupy (`moveGroup` :250-264) też wysyła całe drzewo.
  - „Zmień grupę…” ma własny endpoint dla jednej kategorii (`CategoryGroupAssignDialog.vue:54`, `CategoryGroupController.assign`), więc nie zależy od widocznej listy.
- `CategoryListRow.vue`:
  - Podpis to „rytm · data archiwizacji · opis” (:45-56).
  - Lata to tekst z `categoryYearsLabel` (`resources/js/lib/category-list.ts:24`, „ · ”; test `category-list.test.ts:61-66`).
  - Uchwyt i „Zmień grupę…” sterowane są tym samym propem `groups` (:70, `CategoryListRowActions.vue:97,131`).
- `CategoryGroupRow.vue` bierze `count` (:31) do etykiety „n kategorii” (:46-50) i do treści potwierdzenia usunięcia grupy (`groupDeleteDescription`, :54).
- Zakładki w repo to `SegmentedControl` (`resources/js/components/SegmentedControl.vue`, opcje z `count`) w tonie `neutral` z klasą `segmented-control-tabs`, jak w `OperationsListPanel.vue:202-208`.
- Stan sekcji w adresie: `?section=` w Operacjach, walidowane przez `IndexOperationsRequest` (`app/Http/Requests/IndexOperationsRequest.php:53,62-69`, `Rule::enum(OperationsListSection::class)`), zwracane jako prop i zmieniane przez `router.get` z `replace` (`Operations.vue:164-170`; ADR-0038).
- Szukanie bez polskich znaków: `foldDiacritics()` w `resources/js/lib/command-palette-items.ts:97-103` (test `command-palette-items.test.ts:88-99`).
- Copy: etykiety rytmu w `lang/pl/ui.php:662-669`, w tym krótkie „Stała”, „Bieżąca”, „Roczna”, „Nieregularna”. `archived_on` „Zarchiwizowana :date” jest w :648, `group_count_*` w :710-712. Kluczy szukajki i filtra jeszcze nie ma.
- Strażnicy widoku:
  - `tests/Unit/CategoriesHardcodedUiTest.php` (lista plików :7-22);
  - `resources/js/ui-contract.test.ts:492-559` (strona zawiera `<ListSurface`, `<CategoryListRow`, `<IndexEmptyState`);
  - `scripts/check-ui-literals.mjs:99-112`.
- Sink `/dev/ui/categories` (`resources/js/pages/dev/CategoriesKitchenSink.vue`) ma scenariusze z `resources/js/lib/categories-fixtures.ts:297-382` (`with-archive`, `archive-open` są do zastąpienia).
- E2E `tests/e2e/categories.spec.ts` opiera się na `[data-section="expense"]` (:84, :133, :194) i przycisku `/^Archiwum \(\d+\)$/` (:149-167).
- Opis widoku w `context/foundation/design-system.md:120` mówi o dwóch panelach, latach „ · ” i zwijanym archiwum. Żaden ADR nie opisuje sekcji archiwum na „Kategoriach”. ADR-0067 ma tylko reguły archiwizacji i usuwania, które się nie zmieniają.

## Desired End State

- `/categories` otwiera zakładkę Wydatki, a `/categories?tab=income` i `/categories?tab=archive` otwierają pozostałe. Zmiana zakładki zastępuje wpis historii, więc „Operacje” z menu ⋯ i Wstecz wraca do tej samej zakładki. Fraza i rytm żyją tylko w stanie strony.
- Liczniki zakładek pokazują kategorie zakładki pasujące do frazy, a w Wydatkach i w archiwum wydatków także do rytmu. Bez filtrów to pełne liczby. Badge „n aktywnych” w tytule zawsze liczy wszystkie aktywne kategorie.
- Przy frazie albo rytmie:
  - grupa zostaje tylko z trafieniami i pokazuje „k z n kategorii”;
  - uchwyty znikają, przeciąganie jest wyłączone, „W górę”/„W dół” grupy są niedostępne;
  - „Zmień grupę…” zostaje.
- Wydatki mają kolumnę Rytm (`Badge` z krótką etykietą). Przychody nie mają ani kolumny, ani chipów rytmu. Podpis pod nazwą to opis albo „Zarchiwizowana DD.MM.RRRR · opis”, bez rytmu.
- Archiwum ma podsekcje „Wydatki” i „Przychody”, każdą w kolejności listy. Podsekcja bez trafień znika, kolumna Rytm jest tylko w wydatkach, a filtr rytmu działa na zarchiwizowane wydatki.
- Lata w budżecie to `CategoryYearsStrip`: jedna komórka na każdy rok z `shell.budgetYears`, rok z pozycją na `accent`, rok bez pozycji z obrysem `border`. Etykieta dostępna to zakresy („Lata w budżecie: 2020–2022 · 2025”). Obok zakładek stoi legenda.
- Pusty wynik filtrów pokazuje „Brak kategorii dla „:query”.” albo „Brak kategorii o wybranym rytmie.” z przyciskiem „Wyczyść filtry”. Stopka panelu ma „n kategorii w m grupach” i podpowiedź o uchwycie, a przy filtrze „k z n kategorii” bez podpowiedzi.
- Każdy z tych stanów ma scenariusz w `/dev/ui/categories`. ADR-0099 i `design-system.md` opisują widok tak, jak go zbudowano.

### Key Discoveries:

- Zapis kolejności odrzuca częściowe drzewo (`CategoryListOrder.php:325-348`), więc wyłączenie przeciągania przy filtrze jest konieczne, a nie kosmetyczne.
- `CategoryGroupRow` używa `count` także do potwierdzenia usunięcia grupy (`CategoryGroupRow.vue:54`). Licznik po filtrze musi być osobnym propem, bo inaczej potwierdzenie podałoby złą liczbę.
- Uchwyt i „Zmień grupę…” dzielą prop `groups` (`CategoryListRow.vue:70`). Przy filtrze uchwyt ma zniknąć, a menu zostać, więc potrzebny jest osobny prop dla uchwytu.
- Wzorzec `?section=` z Operacji (`IndexOperationsRequest.php:53-69`) daje gotowy kształt dla `?tab=`: enum, `Rule::enum`, prop i `router.get` z `replace`.

## What We're NOT Doing

- Żadnych zmian w zapisie kolejności (`ReorderCategoryList`, `CategoryListOrder`). Kolejności nie liczymy z częściowej listy.
- Fraza i rytm nie trafiają do adresu ani do `localStorage`.
- Szukajka nie szuka w nazwie grupy ani w etykiecie rytmu, a filtr nie idzie na serwer.
- Brak skrótu `/` do szukajki.
- Brak grup w zakładce Archiwum (archiwum zostaje płaskie, jak dziś).
- Brak zmian w słowniku, w ADR-0067 i w modelu danych. Z backendu dochodzi tylko prop `tab`.
- Nie aktualizujemy karty `PageCategories` w artefakcie claude.ai. Użytkownik robi to w design systemie.
- Bez „Dodaj grupę” w zakładce Archiwum. „Dodaj kategorię” otwiera tam okno z typem „wydatek”.

## Implementation Approach

Najpierw decyzja i kontrakt widoku (ADR-0099 oraz `design-system.md`), potem jedyna zmiana backendu (prop `tab`), następnie czysta logika w `lib` z testami Vitest. Filtrowanie drzewa, liczniki i komórki paska to funkcje bez Vue, więc dają się sprawdzić test-first. Na końcu widok, który tylko składa wyniki tej logiki, oraz sink i E2E. Wszystko na istniejących elementach: `SegmentedControl` w tonie `neutral`, wzorzec pola szukania z `OperationsFilters.vue:329-353`, `Badge`, `ListSurface` i `CategoryChip`.

## Critical Implementation Details

- **Stan przeciągania:** „filtr aktywny” to `query.trim() !== '' || rhythm !== 'all'` (dla zakładki Przychody liczy się tylko fraza). Gdy jest aktywny, `draggable` dostaje `:disabled="true"`, wiersze nie mają uchwytu, a `CategoryGroupRow` nie ma „W górę”/„W dół”. `trees` (pełne drzewo) zostaje jedynym źródłem dla `saveTree`; widok przy filtrze renderuje osobne, wyliczone drzewo i nigdy go nie zapisuje.
- **Zmiana zakładki:** `router.get(categories.url({ query }), {}, { preserveState: true, preserveScroll: true, replace: true, only: ['tab'] })`, przy czym `expense` nie ma parametru w adresie. Fraza i rytm są w stanie komponentu, więc `preserveState` je zachowuje. Zakładka po stronie klienta zmienia się od razu (stan lokalny zsynchronizowany z propem), żeby kliknięcie nie czekało na odpowiedź.

## Phase 1: Decyzja i kontrakt widoku

### Overview

Zapisuje decyzję o układzie „Kategorii” przed kodem (zasada AGENTS.md: nowe znaczenie w design systemie i ADR, gdy zmienia decyzję).

### Changes Required:

#### 1. ADR-0099

**File**: `context/foundation/adr/0099-categories-tabs-filters-and-years-strip.md`

**Intent**: Zapisuje układ widoku „Kategorie” wybrany w karcie `PageCategories` (B) oraz decyzje z planowania, z odrzuconymi wariantami. Uzupełnia ADR-0067 i ADR-0069 i nie zmienia żadnej ich reguły.

**Contract**: Styl i język jak ADR-0067 (akapity, potem „Rejected:”). Treść:

- zakładki Wydatki | Przychody | Archiwum z licznikami i `?tab=income|archive` (Wydatki bez parametru), zastępowane w historii;
- Archiwum jako zakładka zamiast sekcji pod listą, z podsekcjami typu w kolejności listy;
- fraza wspólna dla zakładek, po nazwie i opisie, bez polskich znaków i wielkości liter; liczniki zakładek pokazują trafienia;
- rytm z bieżącego miesiąca (ADR-0072) jako kolumna i filtr pojedynczego wyboru, tylko dla wydatków, także archiwalnych;
- przy frazie albo rytmie grupy tylko z trafieniami z licznikiem „k z n”, bez przeciągania i przesuwania grup, bo kolejność zapisuje się z pełnej listy typu (ADR-0069);
- pasek lat na osi lat Budżetów użytkownika, szkic włącznie, z etykietą zakresów.

Odrzucone: płaska lista wyników, grupy zawsze widoczne, szukanie tylko w aktywnej zakładce, archiwum jako jedna lista (od najnowszych albo w kolejności listy), rytm w podpisie obok kolumny, cały stan w adresie, stan tylko w stronie, zapis kolejności z częściowej listy.

#### 2. Opis widoku w design systemie

**File**: `context/foundation/design-system.md` (akapit :120 i w razie potrzeby :121; lista klas :18)

**Intent**: Przepisuje opis widoku tak, żeby zgadzał się z docelowym widokiem. Usuwa dwa panele, lata „ · ” i `Collapsible`, a dodaje zakładki, toolbar z legendą, filtry, kolumnę Rytm, `CategoryYearsStrip`, stopkę, pusty wynik, zakładkę Archiwum i macierz 7 stanów z nowymi scenariuszami.

**Contract**: Nazwy klas i scenariuszy takie, jakie powstaną w fazach 4–5: `.categories-grid` z wariantem bez rytmu, `.categories-years*`, `.categories-toolbar`, `.categories-filters`, `.categories-footer`; scenariusze `default`, `with-groups`, `income-tab`, `archive-tab`, `search`, `rhythm-filter`, `no-results`, `one-type-empty`, `only-empty-group`, `only-archived`, `empty`. Link do ADR-0099.

### Success Criteria:

#### Automated Verification:

- ADR-0099 istnieje i jest jedynym plikiem o numerze 0099: `ls context/foundation/adr | grep -c '^0099-'` zwraca 1
- `design-system.md` nie wspomina już zwijanego archiwum Kategorii: `grep -n 'Archiwum (n)' context/foundation/design-system.md` nic nie zwraca

#### Manual Verification:

- ADR-0099 i akapit design systemu opisują te same decyzje co tabela „Key Decisions” w `plan-brief.md`

**Implementation Note**: Po fazie poczekaj na potwierdzenie ręcznej weryfikacji, zanim przejdziesz dalej.

---

## Phase 2: Prop `tab`

### Overview

Serwer waliduje `?tab=` i zwraca go jako prop, wzorem `section` w Operacjach.

### Changes Required:

#### 1. Enum zakładki

**File**: `app/Enums/CategoriesListTab.php` (nowy, `php artisan make:enum`)

**Intent**: Zamknięta lista zakładek widoku.

**Contract**: backed enum `string`: `Expense = 'expense'`, `Income = 'income'`, `Archive = 'archive'`.

#### 2. FormRequest

**File**: `app/Http/Requests/IndexCategoriesRequest.php` (nowy)

**Intent**: Waliduje parametr jak `IndexOperationsRequest`; pusty parametr to Wydatki.

**Contract**: `rules(): ['tab' => ['nullable', Rule::enum(CategoriesListTab::class)]]`, `tab(): CategoriesListTab` (null → `Expense`), `authorize()` zgodnie z `IndexOperationsRequest`, puste na null przez `prepareForValidation`, jeśli tak robi wzorzec.

#### 3. Kontroler i typ strony

**File**: `app/Http/Controllers/CategoryController.php`, `resources/js/types/categories.ts`

**Intent**: `index` przyjmuje `IndexCategoriesRequest` i dokłada prop `tab`. Typ strony dostaje `tab` w tym samym commicie (`.ai/rules/inertia-props.md`).

**Contract**: prop `tab: 'expense' | 'income' | 'archive'`. Typ `CategoriesListTab` eksportowany z `resources/js/types/categories.ts`.

#### 4. Test

**File**: `tests/Feature/CategoriesPageTest.php`

**Intent**: Sprawdza domyślną zakładkę, `?tab=archive` i odrzucenie nieznanej wartości.

**Contract**: `assertInertia` → `where('tab', 'expense')` bez parametru, `where('tab', 'archive')` z `?tab=archive`; `?tab=foo` daje błąd walidacji `tab` (tak jak Operacje dla `section`, sprawdź zachowanie w `tests/Feature` dla `section`).

### Success Criteria:

#### Automated Verification:

- Testy strony przechodzą: `php artisan test --compact tests/Feature/CategoriesPageTest.php`
- Formatowanie: `vendor/bin/pint --dirty --format agent`
- Typy: `npm run build && npm run types:check`

**Implementation Note**: Po fazie poczekaj na potwierdzenie ręcznej weryfikacji, zanim przejdziesz dalej.

---

## Phase 3: Logika w `lib` (Vitest)

### Overview

Czyste funkcje dla filtrów, liczników i paska lat, napisane test-first.

### Changes Required:

#### 1. Wspólne składanie polskich znaków

**File**: `resources/js/lib/text-search.ts` (nowy), `resources/js/lib/command-palette-items.ts`

**Intent**: Przenosi `foldDiacritics` do wspólnego pliku, dodaje `matchesSearch(query, ...texts)`, a paleta importuje stąd. Test palety przechodzi bez zmian.

**Contract**: `foldDiacritics(text: string): string` (bez zmian zachowania), `normalizeSearch(text): string` (fold + `toLocaleLowerCase('pl')` + trim), `matchesSearch(query: string, texts: Array<string | null>): boolean` (pusta fraza pasuje do wszystkiego, „prad” pasuje do „Prąd”, „ŁÓDŹ” do „lodz”).

#### 2. Filtr listy kategorii

**File**: `resources/js/lib/category-list-filter.ts` (nowy) + `category-list-filter.test.ts`

**Intent**: Z pełnych danych strony liczy to, co widok pokazuje: drzewo z trafieniami, liczniki zakładek i rytmów, archiwum w podsekcjach i informację, czy filtr jest aktywny.

**Contract**:

- `type CategoryListFilter = { query: string; rhythm: Rhythm | 'all' }`.
- `isFilterActive(filter, type): boolean`: rytm liczy się tylko dla `expense`.
- `categoryMatches(category, filter): boolean`:
  - fraza w `name` albo `description`;
  - rytm tylko, gdy `category.type === 'expense'`.
- `filterCategoryTree(tree, filter): Array<…>`:
  - grupy bez trafień znikają;
  - grupa z trafieniami niesie `visibleCount` i pełne `count`;
  - bez aktywnego filtra zwraca drzewo bez zmian, z pustymi grupami włącznie.
- `tabCounts(props, filter): Record<CategoriesListTab, number>`:
  - Wydatki to aktywne wydatki pasujące do filtra;
  - Przychody to aktywne przychody pasujące do frazy;
  - Archiwum to archiwalne obu typów pasujące do filtra.
- `rhythmCounts(categories, query): Record<Rhythm, number>`: kategorie wydatków aktywnej zakładki po frazie, bez filtra rytmu.
- `archiveSections(archived, filter)`: `{ expense: CategoryListItem[]; income: CategoryListItem[] }` w kolejności z serwera.

Testy pokrywają przykład z planowania:

- filtr „Roczna” zostawia grupę Dom z Meble jako „1 z 5”, a do tego Zdrowie i Ubrania bez grupy, natomiast grupa Codzienne znika;
- fraza „zwrot” daje liczniki 0/1/1;
- pusta grupa przy braku filtra zostaje.

#### 3. Komórki paska lat

**File**: `resources/js/lib/category-list.ts` (+ test)

**Intent**: Zastępuje `categoryYearsLabel` funkcjami dla paska: komórki na osi i etykieta dostępna z zakresów.

**Contract**:

- `categoryYearCells(axis: number[], budgetYears: number[]): { year: number; short: string; on: boolean }[]`:
  - oś to suma `axis` i `budgetYears`, rosnąco, bez duplikatów;
  - `short` to dwie ostatnie cyfry.
- `categoryYearsRangeLabel(years: number[]): string`:
  - `[2020…2027]` → „2020–2027”;
  - `[2020,2022,2024,2027]` → „2020 · 2022 · 2024 · 2027”;
  - `[2020,2021,2022,2025]` → „2020–2022 · 2025”;
  - `[]` → `''`.
- Test `category-list.test.ts:61-66` przechodzi na nową funkcję. `categoryYearsLabel` usunąć, gdy nic go nie używa (sprawdź `grep`).

### Success Criteria:

#### Automated Verification:

- Testy lib przechodzą: `npx vitest run resources/js/lib/text-search.test.ts resources/js/lib/category-list-filter.test.ts resources/js/lib/category-list.test.ts resources/js/lib/command-palette-items.test.ts`
- Typy: `npm run types:check`
- Lint: `npm run lint` (albo krok lint z `composer ci:check`)

**Implementation Note**: Po fazie poczekaj na potwierdzenie ręcznej weryfikacji, zanim przejdziesz dalej.

---

## Phase 4: Widok

### Overview

Składa nowy widok z logiki fazy 3 na istniejących elementach.

### Changes Required:

#### 1. Pasek lat

**File**: `resources/js/components/CategoryYearsStrip.vue` (nowy)

**Intent**: Rysuje komórki z `categoryYearCells` i ma wariant klucza do legendy.

**Contract**:

- props `years: number[]` (lata kategorii), `axis: number[]` (`shell.budgetYears` z `usePage()` po stronie wiersza albo strony);
- element z `role="img"` i `aria-label` „Lata w budżecie: <zakresy>”, a bez lat `no_years_label`;
- komórki `aria-hidden`, z `title` „2021” albo „2021 — bez pozycji”;
- klasy `.categories-years`, `.categories-years-cell` i `[data-state='on']`.

#### 2. Wiersz kategorii

**File**: `resources/js/components/CategoryListRow.vue`, `resources/js/components/CategoryListRowActions.vue` (bez zmian zachowania menu)

**Intent**: Podpis bez rytmu, kolumna Rytm z `Badge` dla wydatków, `CategoryYearsStrip` zamiast tekstu. Uchwyt sterowany osobnym propem, żeby przy filtrze zniknął, a „Zmień grupę…” zostało.

**Contract**:

- nowy prop `draggable?: boolean` (domyślnie `true`); uchwyt to `groups !== undefined && draggable`;
- nowy prop `showRhythm: boolean`; komórka `.categories-cell-rhythm` z `Badge variant="neutral"` i etykietą `rhythm_option_*` (krótka, `lang/pl/ui.php:666-669`);
- podpis to `[archivedOn, description]`.

#### 3. Wiersz grupy

**File**: `resources/js/components/CategoryGroupRow.vue`

**Intent**: Przy filtrze etykieta „k z n kategorii” i brak przesuwania; potwierdzenie usunięcia zawsze z pełnym `count`.

**Contract**:

- nowy prop `visibleCount?: number`: gdy różny od `count`, etykieta to `group_count_filtered` („:visible z :count kategorii”);
- nowy prop `movable?: boolean` (domyślnie `true`): bez niego nie ma „W górę”/„W dół” ani uchwytu;
- `groupDeleteDescription` dalej z `count`.

#### 4. Strona

**File**: `resources/js/pages/Categories.vue`

**Intent**: Jeden `ListSurface` na aktywną zakładkę zamiast dwóch paneli i sekcji archiwum.

**Contract**:

- Toolbar `.categories-toolbar`:
  - `SegmentedControl` `tone="neutral"` z `class="segmented-control-tabs"` i opcjami z `tabCounts`;
  - `aria-label` `t.categories.tabs_label`;
  - legenda dwóch `CategoryYearsStrip` w wariancie klucza z „rok z pozycją w budżecie” i „bez pozycji”.
- Akcje w tytule strony:
  - „Dodaj grupę” (`outline`, ukryte w Archiwum);
  - „Dodaj kategorię” (główna, typ z zakładki, w Archiwum `expense`).
- Filtry `.categories-filters` w nagłówku panelu:
  - pole szukania wzorem `OperationsFilters.vue:329-353` (`role="search"`, ikona `Search`, `Input type="search"`, placeholder „Szukaj kategorii lub opisu”, `aria-label` „Szukaj kategorii”);
  - chipy rytmu w Wydatkach i Archiwum jako `SegmentedControl` `tone="neutral"` z „Wszystkie rytmy” i czterema rytmami z liczbami z `rhythmCounts`, z `aria-label` `rhythm_filter_label`. Wybór jednej wartości to `SegmentedControl`, nie przyciski z `aria-pressed` (`.ai/rules/js.md`), więc pigułki `ht-fchip` z karty oddaje ten komponent.
- Wiersz nagłówka kolumn: Kategoria | Rytm (tylko wydatki) | Lata w budżecie.
- Drzewo:
  - bez filtra renderuje `trees[tab]` z `draggable` jak dziś;
  - z filtrem renderuje `filterCategoryTree(trees[tab], filter)` z `:disabled="true"`, wiersze z `:draggable="false"`, grupy z `movable=false` i `visibleCount`.
- Archiwum: dwie podsekcje z nagłówkiem `section-title` (`t.categories.expense_title` / `income_title`), wiersze `archived`, Rytm tylko w wydatkach; podsekcja bez wierszy znika.
- Pusty wynik:
  - akapit `no_results_query` albo `no_results_rhythm` i `Button` `outline` `sm` „Wyczyść filtry”, który czyści frazę i rytm;
  - Archiwum bez żadnej archiwalnej pokazuje `archive_empty`;
  - zakładka typu bez kategorii zostaje przy `section_empty`.
- Stopka `.categories-footer`:
  - bez filtra `footer_count` („n kategorii w m grupach”, odmiana jak `activeCountForm`) i `drag_hint`;
  - z filtrem `footer_filtered` („:visible z :total kategorii”);
  - w Archiwum tylko liczba.
- Stan zakładki zsynchronizowany z propem `tab`; zmiana przez `router.get` (zob. Critical Implementation Details). `IndexEmptyState` bez żadnej kategorii zostaje jak dziś.
- `data-section` przechodzi na `data-tab="<tab>"` na `ListSurface`, a podsekcje archiwum dostają `data-archive-type`.

#### 5. Usunięcie sekcji archiwum

**File**: `resources/js/components/CategoryArchiveSection.vue` (usunąć)

**Intent**: Zastąpiony zakładką. Usunąć import i odwołania (sink, listy strażników).

**Contract**: `grep -rn CategoryArchiveSection resources tests scripts` nic nie zwraca.

#### 6. Klasy CSS

**File**: `resources/css/app.css` (blok Kategorii :1367-1478)

**Intent**: Nowe kolumny i klasy wyłącznie na tokenach.

**Contract**:

- `.categories-grid`:
  - od `md`: `minmax(0,1fr) <kolumna rytmu> <kolumna lat> var(--control-height-sm)`;
  - na telefonie obszary `'name actions' / 'rhythm years'`.
- `.categories-grid-no-rhythm` dla przychodów (bez kolumny rytmu, lata na całą drugą linię na telefonie).
- `.categories-cell-rhythm`.
- `.categories-years`: siatka komórek, `gap-1`.
- `.categories-years-cell`:
  - `rounded-xs`, obrys `border`, tekst `meta` `tabular-nums`;
  - `[data-state='on']` z `bg-accent text-accent-foreground`.
- `.categories-years-key`: małe komórki legendy bez tekstu.
- `.categories-toolbar`, `.categories-filters`, `.categories-footer`: `meta`, `text-muted-foreground`, obrys `border` u góry.
- Szerokości kolumn jako tokeny wymiaru, jeśli wzorzec pliku tak robi (`--command-palette-width`). Bez hex, bez wartości arbitralnych w szablonach.

#### 7. Copy

**File**: `lang/pl/ui.php` (blok `categories`) i typ tłumaczeń (`resources/js/types/shell.ts`, `UiTranslations`)

**Intent**: Nowe teksty widoku.

**Contract**: klucze

- `tabs_label`;
- `search_placeholder` („Szukaj kategorii lub opisu”), `search_label` („Szukaj kategorii”);
- `rhythm_filter_label`, `rhythm_all` („Wszystkie rytmy”);
- `years_legend_on`, `years_legend_off`, `years_label` („Lata w budżecie: :years”), `year_off_title` („:year — bez pozycji”);
- `group_count_filtered`;
- `footer_count_one/few/many` (z grupami i bez);
- `footer_filtered`, `drag_hint`;
- `no_results_query`, `no_results_rhythm`, `clear_filters` („Wyczyść filtry”), `archive_empty`.

Usunąć klucze używane tylko przez `CategoryArchiveSection` (sprawdź `grep`).

#### 8. Strażnicy

**File**: `tests/Unit/CategoriesHardcodedUiTest.php`, `resources/js/ui-contract.test.ts`, `scripts/check-ui-literals.mjs`

**Intent**: Listy plików bez `CategoryArchiveSection.vue`, z `CategoryYearsStrip.vue`. Kontrakt strony nadal wymaga `<ListSurface`, `<CategoryListRow`, `<IndexEmptyState`.

**Contract**: wszystkie trzy listy zawierają `resources/js/components/CategoryYearsStrip.vue`.

### Success Criteria:

#### Automated Verification:

- Strażnicy UI: `npm run check:ui` i `php artisan test --compact tests/Unit/CategoriesHardcodedUiTest.php`
- Vitest: `npx vitest run` (w tym `ui-contract.test.ts`)
- Typy i build: `npm run build && npm run types:check`
- Brak martwej sekcji: `grep -rn CategoryArchiveSection resources tests scripts` nic nie zwraca
- Pełna bramka: `composer ci:check` (w worktree przez kontener z `DB_DATABASE=testing_categories_tabs_and_filters`, zob. AGENTS.md) i `vendor/bin/sloppy ci`

#### Manual Verification:

- W przeglądarce (`context/foundation/agents/worktree-preview.md`):
  - zakładki przełączają się od razu, `?tab=archive` jest w adresie, a „Operacje” z menu ⋯ i Wstecz wraca do Archiwum;
  - fraza „prad” znajduje „Prąd”, liczniki zakładek pokazują trafienia, uchwyty znikają, a grupa pokazuje „1 z 5 kategorii”;
  - po wyczyszczeniu filtrów przeciąganie zapisuje kolejność jak wcześniej;
  - pasek lat jest czytelny w obu motywach i na telefonie (druga linia: Rytm i pasek);
  - menu ⋯ grupy przy filtrze nie ma „W górę”/„W dół”, a potwierdzenie usunięcia grupy podaje pełną liczbę kategorii.

**Implementation Note**: Po fazie poczekaj na potwierdzenie ręcznej weryfikacji, zanim przejdziesz dalej.

---

## Phase 5: Sink i E2E

### Overview

Każdy stan w `/dev/ui/categories` i główne ryzyko w przeglądarce.

### Changes Required:

#### 1. Fiksury i sink

**File**: `resources/js/lib/categories-fixtures.ts`, `resources/js/pages/dev/CategoriesKitchenSink.vue`

**Intent**: Scenariusze dla nowych stanów i macierz 7 stanów zaktualizowana o zakładki, filtry i pasek.

**Contract**:

- `CategoriesScenario` dostaje opcjonalne `tab` i początkowy `filter` (strona przyjmuje je w sinku przez props albo przez `initialFilter`, bez osobnego markupu w sinku);
- scenariusze `income-tab`, `archive-tab` (oba typy archiwalne), `search` („zwrot”), `rhythm-filter` („Roczna”, grupa „1 z 5”), `no-results`;
- `with-archive` i `archive-open` znikają;
- osie lat w fiksurach to 2020–2027 z lukami (np. Meble 2022–2027, OC 2021–2027);
- macierz: hover i focus na chipach i zakładkach, disabled to brak uchwytów przy filtrze, empty to `no-results` i `archive_empty`.

#### 2. E2E

**File**: `tests/e2e/categories.spec.ts`

**Intent**: Istniejące kroki przechodzą na zakładki. Nowy test sprawdza, że przy frazie nie ma przeciągania, a po wyczyszczeniu filtrów kolejność dalej się zapisuje.

**Contract**:

- `[data-section="expense"]` → `[data-tab="expense"]`;
- „lands in the collapsed archive” → kategoria po archiwizacji jest w zakładce Archiwum (`?tab=archive`), a po Wstecz z Operacji zakładka zostaje;
- nowy test:
  - wpisz frazę i sprawdź, że uchwytów jest 0 oraz że liczniki zakładek mają trafienia;
  - przełącz zakładkę i sprawdź, że fraza została;
  - kliknij „Wyczyść filtry”, przeciągnij kategorię i sprawdź zapisaną kolejność po przeładowaniu.

### Success Criteria:

#### Automated Verification:

- Test sinka: `php artisan test --compact tests/Feature/CategoriesKitchenSinkTest.php`
- Vitest i typy: `npx vitest run && npm run types:check`
- E2E Kategorii: `npx playwright test tests/e2e/categories.spec.ts` (setup z `context/foundation/test-stack.md`; CI go nie uruchamia)

#### Manual Verification:

- `/dev/ui/categories` pokazuje każdy scenariusz bez błędów w konsoli, w obu motywach
- Widok zgadza się z kartą `PageCategories` (zakładki, legenda, chipy, kolumny, stopka)

**Implementation Note**: Po fazie poczekaj na potwierdzenie ręcznej weryfikacji.

---

## Testing Strategy

### Unit Tests:

- `text-search`: polskie znaki (ą, ł, Ł), wielkość liter, pusta fraza, `null` w opisie.
- `category-list-filter`:
  - grupy z trafieniami i `visibleCount`;
  - puste grupy bez filtra zostają;
  - liczniki zakładek przy frazie i rytmie (rytm nie działa na przychody);
  - liczniki rytmów po frazie;
  - archiwum w podsekcjach w kolejności z serwera.
- `category-list`: komórki na osi z luką, rok spoza osi, zakresy etykiety, pusta lista.
- Feature: `tab` domyślny, `archive`, nieznana wartość.

### Integration Tests:

- E2E z fazy 5: archiwizacja w zakładce, Wstecz, fraza bez uchwytów, zapis kolejności po wyczyszczeniu.

### Manual Testing Steps:

1. Otwórz `/categories`, wpisz „prad” i sprawdź „Prąd” i liczniki zakładek.
2. Wybierz „Roczna” i sprawdź „1 z 5 kategorii” w grupie oraz brak uchwytów.
3. Przejdź do Archiwum: obie podsekcje, data archiwizacji, filtr działa na wydatki.
4. Wyczyść filtry, przeciągnij kategorię do grupy i przeładuj stronę: kolejność została.
5. Telefon (375 px): druga linia wiersza to Rytm i pasek, zakładki się mieszczą.

## Performance Considerations

Filtrowanie po stronie klienta na kilkudziesięciu kategoriach, wyliczane w `computed`, bez debounce. Backend dokłada tylko walidację parametru.

## Migration Notes

Brak zmian danych. Stare linki `/categories` otwierają Wydatki.

## References

- Roadmap: `context/foundation/roadmap.md` (S-37, MS-04)
- Karta: `PageCategories` w artefakcie Atrament (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, `project/components/PageCategories/README.md`)
- Wzorzec zakładki w adresie: `app/Http/Requests/IndexOperationsRequest.php:53-69`, `resources/js/pages/Operations.vue:164-170`, ADR-0038
- Zakładki z licznikami: `resources/js/components/OperationsListPanel.vue:202-208`
- Pole szukania: `resources/js/components/OperationsFilters.vue:329-353`
- Zapis kolejności: `app/Support/CategoryList/CategoryListOrder.php:144,325-348`, ADR-0069
- Rytm: ADR-0066, ADR-0072; glossary „Rhythm”

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzja i kontrakt widoku

#### Automated

- [x] 1.1 ADR-0099 istnieje i jest jedynym plikiem o numerze 0099 — 8c1abb8
- [x] 1.2 design-system.md nie wspomina już zwijanego archiwum Kategorii — 8c1abb8

#### Manual

- [ ] 1.3 ADR-0099 i akapit design systemu opisują te same decyzje co plan-brief.md

### Phase 2: Prop tab

#### Automated

- [x] 2.1 Testy strony przechodzą (CategoriesPageTest) — d2802a3
- [x] 2.2 Formatowanie pint — d2802a3
- [x] 2.3 Typy: build i types:check — d2802a3

### Phase 3: Logika w lib (Vitest)

#### Automated

- [x] 3.1 Testy lib przechodzą (text-search, category-list-filter, category-list, command-palette-items) — a57def3
- [x] 3.2 Typy: types:check — a57def3
- [x] 3.3 Lint — a57def3

### Phase 4: Widok

#### Automated

- [x] 4.1 Strażnicy UI: check:ui i CategoriesHardcodedUiTest — a1fe427
- [x] 4.2 Vitest (w tym ui-contract.test.ts) — a1fe427
- [x] 4.3 Typy i build — a1fe427
- [x] 4.4 Brak martwej sekcji CategoryArchiveSection — a1fe427
- [x] 4.5 Pełna bramka: composer ci:check i sloppy ci — a1fe427

#### Manual

- [ ] 4.6 W przeglądarce: zakładki, Wstecz, fraza, liczniki, uchwyty, grupa „1 z 5”, zapis kolejności, pasek lat w obu motywach i na telefonie, menu grupy przy filtrze

### Phase 5: Sink i E2E

#### Automated

- [x] 5.1 Test sinka (CategoriesKitchenSinkTest) — 9504c6b
- [x] 5.2 Vitest i typy — 9504c6b
- [x] 5.3 E2E Kategorii — 9504c6b

#### Manual

- [ ] 5.4 /dev/ui/categories pokazuje każdy scenariusz bez błędów, w obu motywach
- [ ] 5.5 Widok zgadza się z kartą PageCategories
