# budget-view-ui — plan implementacji

## Overview

Porządkujemy widok `/budget` według kontraktu `/10x-ui` (wariant „istniejący system projektowy”). Pięć zarzutów z `research.md` (C1–C5) dostaje fazy w kolejności **środowisko → kontrakt (tokeny/klasy) → widok → stany → zabezpieczenie**:

- C1: martwy przełącznik „Pokaż zarchiwizowane”;
- C2 + C5: dwa równoległe drzewa desktop/mobile z sześcioma kopiami komórek kwot;
- C3: pusty stan „brak budżetu” poza `IndexEmptyState`, bez `<h1>`;
- C4: brakujące stany i nazwy kontrolek.

Zmiana kończy się kitchen sinkiem (bramka wizualna), testem-strażnikiem w CI i regułą w `.ai/rules/js.md`.

## Current State Analysis

- `resources/js/pages/Budget.vue:307-311` — `<Checkbox :checked @update:checked>`. W reka-ui 2.11 tych nazw nie ma (`node_modules/reka-ui/dist/Checkbox/CheckboxRoot.js:22-83`), więc pole jest niekontrolowane, a `toggleShowArchived` (`:143-154`) nigdy się nie wykona.
- `resources/js/components/BudgetCategoryList.vue` (1015 linii) ma dwa zawsze zamontowane drzewa: desktop `<ListSurface class="hidden md:block">` z `<table class="list-surface-table">` (`:212-576`) oraz mobile `div.space-y-3 md:hidden` z kartami (`:578-1012`). W DOM są więc dwa `draggable`, dwa filtry i podwójne `BudgetCategoryRowActions`. Komórki kwot występują w 6 kopiach, które już się rozjechały (kolejność kolumn, kolor archiwum, wagi fontu; tabela w `research.md` C2).
- `Budget.vue:169-192` — gałąź `budget === null` to `Card max-w-lg` z `CardTitle` (`<h3>`), bez `<h1>`. Tekst `budget.no_budget` = „Brak budżetu na wybrany rok” (`lang/pl/ui.php:188`) jest nieprawdziwy, bo gałąź pojawia się tylko wtedy, gdy użytkownik nie ma **żadnego** budżetu (`app/Support/SelectedBudgetYear.php:18-21`). Klucz jest używany tylko w `Budget.vue` (Dashboard i MonthPlan mają własne klucze: `ui.php:129`, `:352`).
- C4 (`research.md`): filtr bez dostępnej nazwy (`BudgetCategoryList.vue:238-243`, `:597-602`); `<h4>` w `<Button>` z `hover:bg-transparent` (`:456-475`); uchwyt przeciągania z `rounded-md`, bez fokusu tokenem, z `hover:bg-foreground/[0.04]` (`:268-275`); cichy rollback przy błędzie zmiany kolejności (`:180-195`); brak stanu „brak wyników filtra”; zmiana miesiąca bez reakcji przed odpowiedzią (`BudgetMonthPicker.vue:28-57`); blok „Do wydania” zmienia wysokość między stanami (`BudgetMonthStrip.vue:64-117`).
- Skan literałów z `/10x-ui` na 11 plikach widoku daje dziś **0** trafień. To linia bazowa.

## Desired End State

- Przełącznik „Pokaż zarchiwizowane” zawsze odzwierciedla `show_archived` z serwera i przełącza widok (także po wejściu z linku `?show_archived=1`).
- Lista kategorii to **jeden markup** na siatce `.budget-category-grid` w jednym `ListSurface` na każdej szerokości. Na telefonie wiersz układa się w 2–3 linie, na desktopie w kolumny. Jeden `draggable`, jeden filtr, jedne akcje na kategorię. Komórki kwot pochodzą z jednego komponentu, a ich kolory z jednego modułu `lib/budget-category-cells.ts`.
- Kolejność kwot jest taka sama na każdej szerokości: plan początkowy → bieżący → wykonanie (+%) → pozostało (szkic: tylko plan).
- Zarchiwizowane wiersze są w całości `text-muted-foreground`: bez `success`, bez `destructive`, bez koloru % (zgodnie z `.docs/design-system.md`: „muted-foreground = rzeczy zarchiwizowane”).
- Pusty stan „brak budżetu” ma `<h1>` strony i `IndexEmptyState` z prawdziwym tekstem, na tej samej podłodze co pusta lista kategorii.
- Każdy stan z macierzy 7 stanów jest pokazany w kitchen sinku (`/dev/kitchen-sink/budget`, tylko `local`) albo oznaczony N/A z powodem.
- Test `resources/js/ui-contract.test.ts` pilnuje 0 literałów w plikach Budżetu i zakazuje `:checked`/`@update:checked` na `Checkbox` w całym repo (z kurczącą się listą wyjątków).

### Key Discoveries:

- Repo ma jeden wzorzec listy z jednym markupem: `.operations-grid` (`resources/css/app.css:297-330`) + `FinancialOperationRow.vue:49` + wiersz nagłówka `hidden md:grid` w `Operations.vue:231-252`. Ten wzorzec kopiujemy.
- `.category-meter-row` jest zastrzeżony dla Dashboardu (`.docs/specs/dashboard-hierarchy.md:57`), więc Budżet dostaje własną klasę.
- `.list-surface-table*` i `.list-surface-table-scroll` (`app.css:198-293`) są używane **wyłącznie** przez `BudgetCategoryList.vue`, więc po fazie 4 są martwe.
- `IndexEmptyState.vue:13` renderuje tytuł jako `<h2>`, więc `<h1>` strony musi dać sam widok.
- `vue-sonner` jest podłączony (`resources/js/lib/flashToast.ts`), więc `toast.error(...)` działa po stronie klienta bez zmian w backendzie.
- `UiTranslations.budget` to `Record<string, string>` (`resources/js/types/shell.ts:40`), więc nowe klucze `budget.*` nie wymagają zmian typów.
- `vp test` bierze `resources/js/**/*.test.ts` (`vite.config.ts:38-40`), a `@types/node` jest w devDependencies, więc test-strażnik może czytać pliki przez `node:fs`.
- `BudgetMonthPicker.vue` jest współdzielony z `MonthPlan.vue:8`, więc stan oczekiwania z fazy 5 zmieni też MonthPlan.
- `IconAction` obsługuje `asDialogTrigger`, ale nie wyzwalacz `DropdownMenu`, więc tooltip dla menu akcji wiersza wymaga zmiany współdzielonego komponentu (odroczone).
- Wzorzec wiązania checkboxa po naprawie `9abd259`: lokalny `ref` + `v-model` + `watch` (`Dashboard.vue:99-106`, `:353-356`).

## What We're NOT Doing

- **Accounts, TermDeposits, OperationsImport** z tym samym martwym checkboxem: nie zmieniamy ich w tej zmianie. Faza 6 otwiera jedno issue na GitHubie (OperationsImport oznaczony jako możliwy błąd danych `has_header`), a pliki trafiają na listę wyjątków strażnika.
- **Przestawianie kolejności z klawiatury** (np. „przesuń w górę/dół” w menu akcji): to osobna decyzja produktowa. Uchwyt przeciągania jest wyłącznie dla wskaźnika (`tabindex="-1"`).
- **Tooltip na menu akcji wiersza:** wymaga rozszerzenia `IconAction` o wyzwalacz `DropdownMenu`, a to inny komponent współdzielony. Tu dodajemy tylko `aria-hidden` na ikonie.
- **Podpowiedzi `ColumnHeaderHint` w etykietach mobilnych:** podpowiedź przy każdej komórce każdego wiersza jest nieużywalna na dotyku. Podpowiedzi zostają w wierszu nagłówka (od `md`).
- **Sumy przy filtrowaniu:** sumy dalej liczą cały typ (przychody/wydatki), a nie wynik filtra. Filtr to narzędzie wyszukiwania, a nie zawężenie budżetu.
- **Ujednolicenie „kickerów”** (`text-xs tracking-wider uppercase` vs `text-[11px]` vs Dashboard), **nagłówka strony** (`Heading.vue` vs `<h1 text-lg>` vs `design-system.md`) i **wartości arbitralnych w kontrakcie listy:** to decyzje globalne, które zostają odroczone (tabela „Odroczone” w `research.md`).
- **`rounded-lg` w `BudgetMonthPicker.vue:57` i `pt-6` na `CardContent`:** to nie jest dryf. `rounded-lg` wewnątrz `rounded-xl` z `p-1` to promień koncentryczny, a `pt-6` to przepis `ListSurface`/`Card`.
- **`opacity-70` archiwum w `CategoryMeterRow.vue:35` (Dashboard):** to inny widok, bez zmian.
- **`?month[]=1` → możliwy 500:** backend, odroczone.
- Playwright, testy zrzutów ekranu, nowe zależności: brak.

## Implementation Approach

Kitchen sink powstaje najpierw i renderuje **obecne** komponenty, dzięki czemu każda kolejna faza ma linię bazową „przed” i jedno miejsce do porównania. Kontrakt (klasa siatki i reguły kolorów jako czyste funkcje z testem) wchodzi przed jakąkolwiek zmianą markupu. Dopiero potem przepisujemy stronę (małe zmiany o dużym wpływie: C1, C3) i listę (C2+C5). Stany (C4) dokładamy na gotowy jeden markup, więc trafiają do jednego miejsca, a nie do dwóch drzew. Na końcu strażnik i reguła utrwalają kontrakt.

Po każdej fazie wizualnej (1, 3, 4, 5): zrzut desktop + 390 px z kitchen sinka do `context/changes/budget-view-ui/screenshots/` i ponowny skan literałów (oczekiwane: 0).

## Critical Implementation Details

- **Kitchen sink a Wayfinder:** trasa jest rejestrowana tylko w `app()->isLocal()`, więc w CI nie powstanie jej plik Wayfindera. Trasa musi być **bez nazwy** (`Route::inertia`, bez `->name()`), a strona nie może importować niczego z `@/routes`/`@/actions` dla samej siebie. Inaczej `npm run types:check` w CI padnie na brakującym module.
- **Kolejność w `onDragEnd`:** zachowaj snapshot przed `await reorderHttp.patch(...)`. Rollback i `toast.error` mają nastąpić w `catch`, a `canDrag` ma być fałszywe przez czas `reorderHttp.processing`. Inaczej drugie przeciągnięcie w trakcie PATCH nadpisze snapshot.
- **Przyklejony nagłówek/suma w siatce:** dzisiejsze reguły sticky dotyczą `thead th`/`tfoot td` (`app.css:276-293`). W siatce nagłówek i suma są zwykłymi elementami w kontenerze przewijania. Ograniczenie wysokości i sticky działają **tylko od `md`**, a na telefonie lista płynie razem ze stroną (bez zagnieżdżonego przewijania).

## Faza 1: Środowisko — kitchen sink tylko lokalnie

### Overview

Strona, która renderuje prawdziwe komponenty Budżetu z typowanymi fiksurami we wszystkich stanach obok siebie. Dostępna tylko w środowisku `local`, za `auth`. Zrzuty „przed” jako linia bazowa.

### Changes Required:

#### 1. Trasa

**File**: `routes/web.php`

**Intent**: Zarejestrować kitchen sink tylko lokalnie, w istniejącej grupie `auth` + `verified`, żeby nigdy nie trafił na produkcję (także przy `route:cache` budowanym w środowisku produkcyjnym).

**Contract**: wewnątrz grupy `Route::middleware(['auth', 'verified'])`: `if (app()->isLocal()) { Route::inertia('dev/kitchen-sink/budget', 'dev/BudgetKitchenSink'); }`, **bez nazwy trasy** (patrz Critical Implementation Details).

#### 2. Strona kitchen sink

**File**: `resources/js/pages/dev/BudgetKitchenSink.vue` (nowy)

**Intent**: Jedna strona z sekcjami, każda z nagłówkiem stanu, renderująca komponenty Budżetu (`BudgetPlanSummaryStrip`, `BudgetMonthStrip`, `BudgetCategoryList`, `Checkbox` + `Label` przełącznika archiwum) z fiksurami. Layout domyślny `AppLayout` z resolvera w `app.ts`.

**Contract**: fiksury w TS typowane jako `BudgetCategoryRow[]` / `DashboardTypeTotals` (import typów z komponentów), więc dryf propów wyłapie `vue-tsc`. Sekcje w fazie 1:

- lista w szkicu;
- realizacja przychodów (w tym przychód ponad plan);
- realizacja wydatków z kategorią ponad limit i ze zmienionym planem (`PlanChangedHint`);
- lista z kategoriami zarchiwizowanymi (rozwinięte archiwum);
- pusta lista;
- ≥ 12 kategorii (widoczny filtr);
- `canReorder: false`;
- `BudgetMonthStrip` w stanach `none` / `proposal` / `approved` i z ujemnym „Do wydania”;
- checkbox zaznaczony i odznaczony.

Akcje wierszy w kitchen sinku nie muszą działać (identyfikatory fiksur nie istnieją w bazie), co trzeba opisać jednym zdaniem na górze strony.

#### 3. Test trasy

**File**: `tests/Feature/BudgetKitchenSinkTest.php` (nowy, `php artisan make:test --pest`)

**Intent**: Udowodnić, że poza `local` trasa nie istnieje.

**Contract**: zalogowany, zweryfikowany użytkownik → `GET /dev/kitchen-sink/budget` → 404 (środowisko testów to `testing`).

#### 4. Podgląd i linia bazowa

**Intent**: Przygotować podgląd z worktree zgodnie z `AGENTS.md` → „Preview from a worktree” (osobna baza, np. `household_treasurer_budget_view_ui`, `APP_ENV=local`) i zrobić zrzuty „przed”.

**Contract**: `context/changes/budget-view-ui/screenshots/p1-before-desktop.png`, `p1-before-390.png`.

### Success Criteria:

#### Automated Verification:

- Test trasy przechodzi: `php artisan test --compact tests/Feature/BudgetKitchenSinkTest.php` (obraz Sail na worktree)
- Typy i lint frontendu przechodzą: `npm run build && npm run types:check && npm run check`
- Skan literałów `/10x-ui` na plikach widoku i `pages/dev/BudgetKitchenSink.vue` daje 0 trafień

#### Manual Verification:

- `http://localhost:8090/dev/kitchen-sink/budget` renderuje wszystkie sekcje z fiksurami w obu motywach
- Zrzuty „przed” (desktop + 390 px) zapisane w `screenshots/`

**Implementation Note**: Po fazie i przejściu automatycznych kontroli zatrzymaj się na potwierdzenie ręcznych kontroli przez człowieka.

---

## Faza 2: Kontrakt — siatka wiersza i reguły kolorów komórek

### Overview

Dodajemy do kontraktu list klasę siatki Budżetu (obok `.operations-grid`) i przenosimy logikę kolorów komórek z sześciu kopii do jednego modułu z testem. Markup widoku jeszcze się nie zmienia.

### Changes Required:

#### 1. Klasy siatki w kontrakcie list

**File**: `resources/css/app.css` (warstwa `@layer components`)

**Intent**: Jedna siatka z nazwanymi obszarami, która na telefonie układa wiersz w linie, a od `md` w kolumny. Do tego przepis wiersza (linia `border/60`, hover `foreground/3%`, `py-3`), wiersz nagłówka (11 px wersalikami, jak `.list-surface-table thead th`) i sticky od `md`.

**Contract**:

- `.budget-category-grid`: obszary `handle`, `category`, `initial`, `current`, `execution`, `remaining`, `actions`. Od `md` jedna linia w tej kolejności. Poniżej `md`: linia 1 to `handle category actions`, linie 2–3 to pary `initial current` / `execution remaining`, z uchwytem rozpiętym na wysokość wiersza.
- Modyfikator `.budget-category-grid-draft`: obszary `handle category plan actions`, jedna linia na każdej szerokości.
- Modyfikator `.budget-category-grid-no-handle`: bez kolumny `handle`.
- Klasy komórek `.budget-category-cell-<area>`: komórki kwot `text-right tabular-nums`, `category` z `min-w-0`.
- `.budget-category-row` (przepis wiersza), `.budget-category-header-row` (`hidden md:grid`, przepis nagłówka).
- `.budget-category-scroll`: od `md` `max-h-[min(32rem,70vh)] overflow-auto overscroll-contain`, nagłówek `sticky top-0 bg-card` i suma `sticky bottom-0` z dzisiejszymi cieniami inset (`app.css:276-293`).

Szerokości kolumn od `md` mają odpowiadać dzisiejszym kolumnom tabeli, żeby desktop nie „skoczył”. Kontrolujemy to zrzutem w fazie 4.

#### 2. Reguły kolorów komórek

**File**: `resources/js/lib/budget-category-cells.ts` (nowy) + `resources/js/lib/budget-category-cells.test.ts` (nowy)

**Intent**: Jedno źródło klas dla komórek wykonania, % i „pozostało” w wierszu kategorii i w wierszu sum. Zastępuje lokalne `remainingClass`, `totalsRemainingClass`, `percentSuffixClass`, `totalsAreOverLimit` (`BudgetCategoryList.vue:140-174`) i warunek `text-success` powtórzony ×4.

**Contract**: czyste funkcje bez Vue:

- `executionClass(type, isArchived)`;
- `percentClass(type, percent, isOverLimit, isArchived)`, budowana na `meterPercentClass` z `lib/dashboard-categories.ts`;
- `remainingClass(type, isOverLimit, isArchived)`;
- `totalsRemainingClass(type, remainingMinorUnits)`.

Niezmienniki:

- **archiwum** → `''` (komórka dziedziczy `text-muted-foreground` z wiersza; nigdy `text-success`/`text-destructive`);
- przychód aktywny → wykonanie `text-success`;
- wydatek ponad limit → „pozostało” `text-destructive`, w przeciwnym razie `text-muted-foreground`;
- % bez wartości lub w normie → `text-muted-foreground`;
- suma wydatków ujemna → `text-destructive`.

Test pokrywa każdy niezmiennik, w tym zarchiwizowany wydatek ponad limit (ma zwrócić `''`).

#### 3. Dokumentacja systemu

**File**: `.docs/design-system.md`

**Intent**: Zapisać decyzje: lista Budżetu używa `.budget-category-grid` (jeden markup, jak Operations), a zarchiwizowane wiersze są w całości `muted-foreground`, bez kolorów statusu i bez `opacity`.

**Contract**: sekcja „Powierzchnie i rytm” (punkt o listach) i tabela „Kolor znaczy coś…” (wiersz `muted-foreground`).

### Success Criteria:

#### Automated Verification:

- Testy frontendu przechodzą, w tym nowy `budget-category-cells.test.ts`: `npm test`
- Lint i formatowanie przechodzą: `npm run check`
- Build przechodzi: `npm run build`

---

## Faza 3: Widok — strona (C1, C3)

### Overview

Dwie zmiany w `Budget.vue` o największym wpływie przy najmniejszym ryzyku: działający przełącznik archiwum i prawdziwy pusty stan.

### Changes Required:

#### 1. Przełącznik „Pokaż zarchiwizowane” (C1)

**File**: `resources/js/pages/Budget.vue`

**Intent**: Wiązanie zgodne z reka-ui. Pole odzwierciedla `show_archived` z serwera i wywołuje wizytę po zmianie.

**Contract**: wzorzec z `Dashboard.vue:99-106`, `:353-356`: lokalny `ref` zainicjowany z `show_archived` i synchronizowany `watch`-em na propie, `<Checkbox v-model="…">`, a zmiana wartości przez użytkownika wywołuje `toggleShowArchived(value === true)`. Żadnego `:checked`/`@update:checked`.

#### 2. Pusty stan „brak budżetu” (C3)

**File**: `resources/js/components/BudgetEmptyState.vue` (nowy), `resources/js/pages/Budget.vue`, `lang/pl/ui.php`

**Intent**: Gałąź `budget === null` dostaje `<h1>` strony (`t.budget.title`, jak w gałęzi z budżetem: `text-lg font-semibold`) i `IndexEmptyState` na podłodze, z `CreateBudgetDialog` w slocie `actions`. Wydzielenie do komponentu pozwala pokazać ten stan w kitchen sinku.

**Contract**: `BudgetEmptyState` przyjmuje `v-model:open` i renderuje `IndexEmptyState` + `CreateBudgetDialog` (`year-input-id="budget-page-create-budget-year"` bez zmian, bo spec `copy-categories-on-budget-create.md:25,35` wymaga tego samego dialogu i payloadu). Tekst `budget.no_budget` → „Nie masz jeszcze budżetu”. `budget.no_budget_description` zostaje. Do kitchen sinka dochodzą sekcje „brak budżetu” i przełącznik archiwum.

#### 3. Test Feature

**File**: `tests/Feature/BudgetTest.php`

**Intent**: Zabezpieczyć kontrakt propów, od którego zależą obie poprawki.

**Contract**: `assertInertia`: `show_archived` jest `true` przy `?show_archived=1` i `false` bez niego (jeśli istniejące testy już to asertują, nie duplikować, tylko wskazać je w Progress). Użytkownik bez budżetu dostaje `budget === null` i `translations.budget.no_budget` = nowy tekst.

### Success Criteria:

#### Automated Verification:

- Testy Budżetu przechodzą: `php artisan test --compact tests/Feature/BudgetTest.php` (obraz Sail na worktree)
- `npm run build && npm run types:check && npm run check` przechodzą
- Skan literałów na plikach widoku daje 0 trafień
- `grep -n ":checked=\|@update:checked" resources/js/pages/Budget.vue` nic nie zwraca

#### Manual Verification:

- W podglądzie klik „Pokaż zarchiwizowane” pokazuje i ukrywa zarchiwizowane kategorie; wejście z `?show_archived=1` pokazuje zaznaczone pole, które da się odznaczyć; przełączenie miesiąca zachowuje stan pola
- Konto bez budżetu: wyśrodkowany pusty stan z `<h1>` „Budżet”, tekst „Nie masz jeszcze budżetu”, dialog tworzenia działa
- Zrzuty desktop + 390 px (`p3-*.png`)

**Implementation Note**: Po fazie i przejściu automatycznych kontroli zatrzymaj się na potwierdzenie ręcznych kontroli przez człowieka.

---

## Faza 4: Widok — jedna lista (C2, C5)

### Overview

`BudgetCategoryList` przechodzi z dwóch drzew na jeden markup na siatce z fazy 2. Drzewo mobilne znika, a komórki kwot powstają w jednym komponencie.

### Changes Required:

#### 1. Komórka kwoty

**File**: `resources/js/components/BudgetAmountCell.vue` (nowy)

**Intent**: Jedna komórka: etykieta (widoczna poniżej `md`, od `md` `sr-only`, więc czytnik ekranu ma nazwę kolumny na każdej szerokości) i wartość przez `formatMoney`. Używana w wierszu kategorii i w wierszu sum.

**Contract**: propsy `area` (nazwa obszaru siatki), `label`, `valueClass?`, slot domyślny na wartość (dla `%` i `PlanChangedHint`).

#### 2. Wiersz nagłówka

**File**: `resources/js/components/BudgetCategoryTableHeader.vue` → przemianowany na `BudgetCategoryHeaderRow.vue`

**Intent**: `<thead>` zamienia się w wiersz nagłówka siatki z tymi samymi etykietami i `ColumnHeaderHint`.

**Contract**: propsy `categoryType`, `phase`, `showHandle`; klasy `.budget-category-grid` (+ modyfikatory) i `.budget-category-header-row`.

#### 3. Wiersz kategorii

**File**: `resources/js/components/BudgetCategoryRow.vue` (nowy)

**Intent**: Jeden wiersz dla kategorii aktywnej i zarchiwizowanej, z uchwytem, `CategoryChip` (+ badge „Archiwum”), komórkami kwot i `BudgetCategoryRowActions`.

**Contract**:

- propsy `category: BudgetCategoryRow`, `categoryType`, `phase`, `showHandle`; emit `edit`;
- zarchiwizowany wiersz ma `text-muted-foreground`, a klasy komórek pochodzą z `lib/budget-category-cells.ts`;
- uchwyt: `<button type="button" class="drag-handle …" tabindex="-1" :aria-label>`, `rounded-xl`, hover `foreground/3%` z przepisu wiersza (bez `/[0.04]`);
- kolejność: plan początkowy → bieżący → wykonanie + % → pozostało; w szkicu tylko plan.

#### 4. Lista

**File**: `resources/js/components/BudgetCategoryList.vue`

**Intent**: Jeden `ListSurface` (bez `hidden md:block`). Nagłówek karty zawiera tytuł, formułę, przycisk dodawania i jeden filtr. Pod nim `.budget-category-scroll` z wierszem nagłówka, jednym `draggable` (`tag="div"`) wierszy `BudgetCategoryRow`, wierszem sum (`BudgetAmountCell` + klasy z lib, `font-semibold`) i `Collapsible` archiwum renderującym te same `BudgetCategoryRow`.

**Contract**:

- usunięte: drzewo `md:hidden`, `Card`/`CardHeader`/`CardContent` z mobile, lokalne helpery kolorów, drugi filtr;
- filtr ma `:aria-label="t.budget.filter_by_name"`;
- wyzwalacz archiwum: `<span>` zamiast `<h4>`, bez `hover:bg-transparent`; widoczny hover z wariantu ghost w obu motywach, a tekst wyrównany do lewej krawędzi listy;
- podczas filtrowania nagłówek i wiersze używają modyfikatora `-no-handle` (bez pustej kolumny);
- publiczne API komponentu (propsy, emity, eksport typu `BudgetCategoryRow`) bez zmian.

#### 5. Martwy kontrakt tabeli

**File**: `resources/css/app.css`, `.docs/design-system.md`

**Intent**: Usunąć `.list-surface-table*` i `.list-surface-table-scroll` (jedynym użytkownikiem była ta lista, co trzeba potwierdzić grepem przed usunięciem) i zaktualizować wzmiankę w `design-system.md`.

**Contract**: `grep -rn "list-surface-table" resources/` → 0 trafień po fazie.

### Success Criteria:

#### Automated Verification:

- Testy frontendu przechodzą: `npm test`
- `npm run build && npm run types:check && npm run check` przechodzą
- Skan literałów na plikach widoku (z nowymi `BudgetAmountCell.vue`, `BudgetCategoryRow.vue`, `BudgetCategoryHeaderRow.vue`) daje 0 trafień
- `grep -c "draggable" resources/js/components/BudgetCategoryList.vue` pokazuje jedną instancję `<draggable`, a `grep -n "md:hidden" resources/js/components/BudgetCategoryList.vue` nic nie zwraca
- Testy Feature Budżetu bez regresji: `php artisan test --compact tests/Feature/BudgetTest.php tests/Feature/ArchiveCategoryTest.php` (obraz Sail na worktree)

#### Manual Verification:

- Desktop: kolumny i wartości odpowiadają zrzutowi „przed” (poza zamierzonymi zmianami: kolor archiwum); nagłówek i suma przyklejone przy długiej liście
- 390 px: wiersze w jednym `ListSurface`, kolejność kwot jak na desktopie, suma na końcu listy, bez zagnieżdżonego przewijania
- Przeciąganie zmienia kolejność na desktopie i na telefonie (dotyk), a po odświeżeniu kolejność zostaje
- Zarchiwizowany wydatek ponad limit jest w całości wyciszony
- Zrzuty desktop + 390 px (`p4-*.png`)

**Implementation Note**: Po fazie i przejściu automatycznych kontroli zatrzymaj się na potwierdzenie ręcznych kontroli przez człowieka.

---

## Faza 5: Stany (C4) i macierz 7 stanów

### Overview

Dokładamy brakujące stany na jeden markup i domykamy macierz `/10x-ui` w kitchen sinku.

### Changes Required:

#### 1. Brak wyników filtra

**File**: `resources/js/components/BudgetCategoryList.vue`, `lang/pl/ui.php`

**Intent**: Gdy filtr nie pasuje do żadnej aktywnej ani zarchiwizowanej kategorii, w miejscu wierszy pojawia się wiersz z komunikatem zamiast pustej siatki. Sumy zostają (patrz „What We're NOT Doing”).

**Contract**: nowy klucz `budget.filter_no_results` („Brak kategorii pasujących do filtra”), komunikat w `text-muted-foreground text-sm` wewnątrz `ListSurface`.

#### 2. Błąd i trwanie zmiany kolejności

**File**: `resources/js/components/BudgetCategoryList.vue`, `lang/pl/ui.php`

**Intent**: Przy błędzie PATCH kolejność wraca do poprzedniej i użytkownik dostaje wyjaśnienie. W trakcie PATCH przeciąganie jest wyłączone.

**Contract**: `canDrag` uwzględnia `!reorderHttp.processing`; w `catch` następuje rollback + `toast.error(t.budget.reorder_failed)` (`import { toast } from 'vue-sonner'`); nowy klucz `budget.reorder_failed` („Nie udało się zapisać kolejności. Przywrócono poprzednią.”). Kolejność kroków opisują Critical Implementation Details.

#### 3. Natychmiastowa reakcja wyboru miesiąca

**File**: `resources/js/components/BudgetMonthPicker.vue`, `resources/js/components/BudgetMonthStrip.vue`

**Intent**: Klik w miesiąc od razu zaznacza ten miesiąc i oznacza grupę jako zajętą, a blok „Do wydania” nie zmienia wysokości między stanami.

**Contract**: lokalny `pendingMonth` ustawiany przy kliknięciu i czyszczony w `onFinish` wizyty. Wariant i `aria-current` liczone z `pendingMonth ?? viewedMonth`. Grupa ma `aria-busy` w trakcie. Działa tak samo przy `monthUrl` (MonthPlan). W `BudgetMonthStrip` stan `none` rezerwuje tę samą wysokość co pozostałe (bez nowych wartości arbitralnych).

#### 4. Drobne a11y

**File**: `resources/js/components/BudgetCategoryRowActions.vue`

**Intent**: Ikona menu akcji nie jest ogłaszana obok `aria-label` przycisku.

**Contract**: `aria-hidden="true"` na ikonie.

#### 5. Macierz w kitchen sinku

**File**: `resources/js/pages/dev/BudgetKitchenSink.vue`

**Intent**: Każda komórka macierzy pokazana albo opisana jako N/A z powodem, w jednej tabeli na górze strony (stan → gdzie go zobaczyć).

**Contract**: sekcje dodane w tej fazie:

- **filtr bez wyników:** lista z wstępnie wpisaną frazą; jeśli `nameFilter` jest wewnętrzny, sekcja opisuje, jaką frazę wpisać;
- **disabled:** `canReorder: false`, przycisk w trakcie wysyłki dialogu;
- **focus-visible:** opis „przejdź Tab-em” dla filtra, przycisku dodawania, wyzwalacza archiwum, menu akcji, checkboxa, miesięcy.

N/A z powodem:

- **loading listy:** dane przychodzą z serwera razem ze stroną, więc nie ma osobnego ładowania; wizyta miesiąca jest sprawdzana ręcznie;
- **error zmiany kolejności:** toast sprawdzany ręcznie w podglądzie (DevTools offline);
- **focus-visible uchwytu:** uchwyt jest wyłącznie dla wskaźnika (`tabindex="-1"`), a klawiaturowe przestawianie jest odroczone.

### Success Criteria:

#### Automated Verification:

- `npm test`, `npm run build && npm run types:check && npm run check` przechodzą
- Skan literałów na plikach widoku daje 0 trafień
- Testy Feature MonthPlan i Budżetu bez regresji: `php artisan test --compact tests/Feature/BudgetTest.php tests/Feature/BudgetMonthRemainingTest.php tests/Feature/MonthPlanPageTest.php` (obraz Sail na worktree)

#### Manual Verification:

- Kitchen sink pokazuje każdą komórkę macierzy 7 stanów (default, hover, focus-visible, disabled, error, empty, loading) albo jej N/A z powodem, w obu motywach
- Fraza bez trafień pokazuje komunikat; po wyczyszczeniu wiersze wracają
- DevTools offline + przeciągnięcie: kolejność wraca i pojawia się toast; w trakcie zapisu nie da się przeciągnąć drugi raz
- DevTools „Slow 3G” + klik w miesiąc: miesiąc zaznacza się od razu (na `/budget` i na MonthPlan), pasek nie skacze
- Tab przez stronę: każda kontrolka ma widoczny pierścień `ring` i dostępną nazwę (filtr ogłaszany z nazwą)
- Zrzuty desktop + 390 px (`p5-*.png`)

**Implementation Note**: Po fazie i przejściu automatycznych kontroli zatrzymaj się na potwierdzenie ręcznych kontroli przez człowieka.

---

## Faza 6: Zabezpieczenie — strażnik, issue, reguła

### Overview

Utrwalamy kontrakt dla następnego agenta: test w CI, issue dla pozostałych martwych checkboxów i reguła w `.ai/rules`.

### Changes Required:

#### 1. Issue na GitHubie

**Intent**: Śledzić ten sam błąd w Accounts, TermDeposits i OperationsImport, zamiast zostawiać go tylko w folderze zmiany.

**Contract**: `gh issue create` (zgodnie z `.docs/agents/issue-tracker.md`, etykieta `needs-triage`). Treść: kotwice `Accounts.vue:126-127`, `TermDeposits.vue:128-129`, `OperationsImport.vue:354-356`; wzorzec naprawy (`Dashboard.vue:353`); OperationsImport oznaczony jako możliwy błąd danych `has_header` (`research.md` → Odroczone); instrukcja usunięcia plików z listy wyjątków strażnika. Numer issue trafia do komentarza listy wyjątków.

#### 2. Test-strażnik

**File**: `resources/js/ui-contract.test.ts` (nowy)

**Intent**: Dwie kontrole tekstowe uruchamiane przez `npm test` (a więc w CI przez `composer ci:check`).

**Contract**: import z `vite-plus/test` oraz z `node:fs` i `node:path`; ścieżki liczone względem pliku testu (bez aliasu `@`). Kontrole:

1. **Literały w plikach Budżetu:** jawna lista plików widoku, czyli 11 plików z `research.md` → Skan, minus przemianowany `BudgetCategoryTableHeader.vue`, plus `BudgetCategoryHeaderRow.vue`, `BudgetCategoryRow.vue`, `BudgetAmountCell.vue`, `BudgetEmptyState.vue` i `pages/dev/BudgetKitchenSink.vue`. Każdy plik musi istnieć (przemianowanie nie może po cichu zgubić pokrycia), a regex `/10x-ui` daje 0 trafień.
2. **Martwe wiązanie Checkboxa:** każdy `.vue` pod `resources/js` poza `components/ui/` nie zawiera `:checked=`, `@update:checked` ani `v-model:checked`, z wyjątkiem listy `Accounts.vue`, `TermDeposits.vue`, `OperationsImport.vue` (z komentarzem z numerem issue). Plik z listy wyjątków, który już nie zawiera wzorca, **oblewa** test z komunikatem „usuń go z listy wyjątków”, więc lista się kurczy.

Regex `/10x-ui` trzymany w teście jako jedna stała:

```ts
const HARDCODED =
    /#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(|oklch\(|-\[[0-9.]+(px|rem)\]|\b(bg|text|border|ring|outline|from|via|to|fill|stroke|shadow|divide)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)\b/;
```

#### 3. Reguła dla agentów

**File**: `.ai/rules/js.md` (glob `resources/js/**/*.vue` już w `.ai/rules/index.md`)

**Intent**: Dopisać dwie krótkie sekcje: (a) reka-ui `Checkbox` wiąż przez `v-model`/`modelValue`, nigdy przez `:checked`/`@update:checked`, bo te nazwy to radix-vue i reka-ui je ignoruje (strażnik w `resources/js/ui-contract.test.ts`); (b) listy z wierszami responsywnymi: jeden markup z siatką obszarów (`.operations-grid`, `.budget-category-grid`) w `ListSurface`, bez osobnego drzewa mobilnego; stany widoku Budżetu w kitchen sinku `/dev/kitchen-sink/budget` (tylko `local`).

**Contract**: sekcje `##` w stylu istniejących (krótko, 2–3 zdania); spójne z `.docs/design-system.md` („Zmiana systemu” pkt 3).

#### 4. Zarzuty w researchu

**File**: `context/changes/budget-view-ui/research.md`

**Intent**: Oznaczyć w `## Charges`, co plan zaadresował, a co zostaje odroczone. Odroczone: klawiatura, tooltip menu akcji, podpowiedzi mobile, kicker. Wpisać numer issue przy odroczonych checkboxach. Niczego nie usuwać.

**Contract**: dopisek przy C1–C5 i w tabeli „Odroczone”.

### Success Criteria:

#### Automated Verification:

- Strażnik przechodzi: `npm test`
- Strażnik działa: tymczasowe dodanie `:checked="x"` do `Budget.vue` albo `text-red-500` do `BudgetCategoryRow.vue` powoduje porażkę `npm test` (zmiana cofnięta przed commitem)
- Pełna bramka CI na worktree: `composer ci:check` w obrazie Sail (`AGENTS.md` → Test database)
- `vendor/bin/sloppy ci` na hoście bez nowych ustaleń (zmiany PHP: trasa i test)
- `vendor/bin/pint --dirty --format agent` bez zmian

#### Manual Verification:

- Issue widoczne na GitHubie z kotwicami i oznaczeniem OperationsImport
- Reguła w `.ai/rules/js.md` czytelna i zgodna z `.docs/design-system.md`
- Lista kontrolna skilla `/10x-ui` (`references/ui-quality-checklist.md`) przejrzana przed `/10x-impl-review`

**Implementation Note**: Po fazie i przejściu automatycznych kontroli zatrzymaj się na potwierdzenie ręcznych kontroli przez człowieka.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/budget-category-cells.test.ts`: każdy niezmiennik kolorów, w tym zarchiwizowany wydatek ponad limit (`''`), przychód ponad plan (`text-success` dla %), `percent === null`, suma ujemna/dodatnia.
- `resources/js/ui-contract.test.ts`: literały w plikach Budżetu = 0; zakaz martwego wiązania Checkboxa z kurczącą się listą wyjątków.

### Integration Tests:

- `tests/Feature/BudgetKitchenSinkTest.php`: 404 poza `local`.
- `tests/Feature/BudgetTest.php`: `show_archived` w propsach dla obu wartości; użytkownik bez budżetu → `budget === null` + nowy tekst.
- Regresja: `ArchiveCategoryTest.php`, `BudgetMonthRemainingTest.php`, `BudgetMonthResultTest.php`, `MonthPlanPageTest.php` (współdzielony `BudgetMonthPicker`).

### Manual Testing Steps:

1. Podgląd z worktree (`AGENTS.md` → Preview), konto `test@example.com` / `password`.
2. `/budget`: przełącz archiwum, wejdź z `?show_archived=1`, odznacz.
3. Telefon (390 px w DevTools): lista w jednym `ListSurface`, przeciągnij kategorię dotykiem.
4. Filtr z frazą bez trafień; potem DevTools offline + przeciągnięcie → toast.
5. Slow 3G + klik w miesiąc na `/budget` i na MonthPlan.
6. Nowe konto bez budżetu → pusty stan z `<h1>`, utwórz budżet.
7. Kitchen sink w obu motywach: macierz 7 stanów kompletna.

## Performance Considerations

Jedno drzewo zamiast dwóch: o połowę mniej komponentów na stronie (jeden `draggable`, jedne `BudgetCategoryRowActions` z dialogami na kategorię). Bez nowych zapytań i propów.

## Migration Notes

Brak zmian w danych i schemacie. Kitchen sink nie istnieje poza `local`. Usunięcie `.list-surface-table*` jest bezpieczne, bo jedynym użytkownikiem była przebudowywana lista (potwierdzić grepem w fazie 4).

## References

- Research: `context/changes/budget-view-ui/research.md` (zarzuty C1–C5, tabela „Odroczone”)
- Kontrakt UI: skill `/10x-ui`, `.docs/design-system.md`, `.ai/rules/js.md`, `.ai/rules/css.md`
- Wzorzec jednego markupu: `resources/css/app.css:297-330`, `resources/js/components/FinancialOperationRow.vue:49`, `resources/js/pages/Operations.vue:226-264`
- Wzorzec checkboxa: `resources/js/pages/Dashboard.vue:99-106`, `:353-356` (commit `9abd259`)
- Pusty stan: `resources/js/components/IndexEmptyState.vue`, `resources/js/pages/MonthPlan.vue:455-467`
- Spec dialogu tworzenia: `.docs/specs/copy-categories-on-budget-create.md:25,35`
- Toast: `resources/js/lib/flashToast.ts`
- Issue źródłowe: GitHub #21 (story 14), #25

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Środowisko — kitchen sink tylko lokalnie

#### Automated

- [x] 1.1 Test trasy przechodzi: `php artisan test --compact tests/Feature/BudgetKitchenSinkTest.php` (obraz Sail na worktree) — 164d9c5
- [x] 1.2 Typy i lint frontendu przechodzą: `npm run build && npm run types:check && npm run check` — 164d9c5
- [x] 1.3 Skan literałów `/10x-ui` na plikach widoku i `pages/dev/BudgetKitchenSink.vue` daje 0 trafień — 164d9c5

#### Manual

- [x] 1.4 `http://localhost:8090/dev/kitchen-sink/budget` renderuje wszystkie sekcje z fiksurami w obu motywach — 164d9c5
- [ ] 1.5 Zrzuty „przed” (desktop + 390 px) zapisane w `screenshots/`

### Phase 2: Kontrakt — siatka wiersza i reguły kolorów komórek

#### Automated

- [x] 2.1 Testy frontendu przechodzą, w tym nowy `budget-category-cells.test.ts`: `npm test` — 689c64e
- [x] 2.2 Lint i formatowanie przechodzą: `npm run check` — 689c64e
- [x] 2.3 Build przechodzi: `npm run build` — 689c64e

### Phase 3: Widok — strona (C1, C3)

#### Automated

- [x] 3.1 Testy Budżetu przechodzą: `php artisan test --compact tests/Feature/BudgetTest.php` (obraz Sail na worktree) — 7b8c991
- [x] 3.2 `npm run build && npm run types:check && npm run check` przechodzą — 7b8c991
- [x] 3.3 Skan literałów na plikach widoku daje 0 trafień — 7b8c991
- [x] 3.4 `grep -n ":checked=\|@update:checked" resources/js/pages/Budget.vue` nic nie zwraca — 7b8c991

#### Manual

- [x] 3.5 W podglądzie klik „Pokaż zarchiwizowane” pokazuje i ukrywa zarchiwizowane kategorie; wejście z `?show_archived=1` pokazuje zaznaczone pole, które da się odznaczyć; przełączenie miesiąca zachowuje stan pola — 7b8c991
- [x] 3.6 Konto bez budżetu: wyśrodkowany pusty stan z `<h1>` „Budżet”, tekst „Nie masz jeszcze budżetu”, dialog tworzenia działa — 7b8c991
- [x] 3.7 Zrzuty desktop + 390 px (`p3-*.png`) — 7b8c991

### Phase 4: Widok — jedna lista (C2, C5)

#### Automated

- [x] 4.1 Testy frontendu przechodzą: `npm test` — c190eb0
- [x] 4.2 `npm run build && npm run types:check && npm run check` przechodzą — c190eb0
- [x] 4.3 Skan literałów na plikach widoku (z nowymi `BudgetAmountCell.vue`, `BudgetCategoryRow.vue`, `BudgetCategoryHeaderRow.vue`) daje 0 trafień — c190eb0
- [x] 4.4 `grep -c "draggable" resources/js/components/BudgetCategoryList.vue` pokazuje jedną instancję `<draggable`, a `grep -n "md:hidden" resources/js/components/BudgetCategoryList.vue` nic nie zwraca — c190eb0
- [x] 4.5 Testy Feature Budżetu bez regresji: `php artisan test --compact tests/Feature/BudgetTest.php tests/Feature/ArchiveCategoryTest.php` (obraz Sail na worktree) — c190eb0

#### Manual

- [x] 4.6 Desktop: kolumny i wartości odpowiadają zrzutowi „przed” (poza zamierzonymi zmianami: kolor archiwum); nagłówek i suma przyklejone przy długiej liście — c190eb0
- [x] 4.7 390 px: wiersze w jednym `ListSurface`, kolejność kwot jak na desktopie, suma na końcu listy, bez zagnieżdżonego przewijania — c190eb0
- [x] 4.8 Przeciąganie zmienia kolejność na desktopie i na telefonie (dotyk), a po odświeżeniu kolejność zostaje — c190eb0
- [x] 4.9 Zarchiwizowany wydatek ponad limit jest w całości wyciszony — c190eb0
- [ ] 4.10 Zrzuty desktop + 390 px (`p4-*.png`)

### Phase 5: Stany (C4) i macierz 7 stanów

#### Automated

- [x] 5.1 `npm test`, `npm run build && npm run types:check && npm run check` przechodzą — c543590
- [x] 5.2 Skan literałów na plikach widoku daje 0 trafień — c543590
- [x] 5.3 Testy Feature MonthPlan i Budżetu bez regresji: `php artisan test --compact tests/Feature/BudgetTest.php tests/Feature/BudgetMonthRemainingTest.php tests/Feature/MonthPlanPageTest.php` (obraz Sail na worktree) — c543590

#### Manual

- [x] 5.4 Kitchen sink pokazuje każdą komórkę macierzy 7 stanów (default, hover, focus-visible, disabled, error, empty, loading) albo jej N/A z powodem, w obu motywach — c543590
- [x] 5.5 Fraza bez trafień pokazuje komunikat; po wyczyszczeniu wiersze wracają — c543590
- [x] 5.6 DevTools offline + przeciągnięcie: kolejność wraca i pojawia się toast; w trakcie zapisu nie da się przeciągnąć drugi raz — c543590
- [x] 5.7 DevTools „Slow 3G” + klik w miesiąc: miesiąc zaznacza się od razu (na `/budget` i na MonthPlan), pasek nie skacze — c543590
- [x] 5.8 Tab przez stronę: każda kontrolka ma widoczny pierścień `ring` i dostępną nazwę (filtr ogłaszany z nazwą) — c543590
- [x] 5.9 Zrzuty desktop + 390 px (`p5-*.png`) — c543590

### Phase 6: Zabezpieczenie — strażnik, issue, reguła

#### Automated

- [x] 6.1 Strażnik przechodzi: `npm test`
- [x] 6.2 Strażnik działa: tymczasowe dodanie `:checked="x"` do `Budget.vue` albo `text-red-500` do `BudgetCategoryRow.vue` powoduje porażkę `npm test` (zmiana cofnięta przed commitem)
- [x] 6.3 Pełna bramka CI na worktree: `composer ci:check` w obrazie Sail (`AGENTS.md` → Test database)
- [x] 6.4 `vendor/bin/sloppy ci` na hoście bez nowych ustaleń (zmiany PHP: trasa i test)
- [x] 6.5 `vendor/bin/pint --dirty --format agent` bez zmian

#### Manual

- [x] 6.6 Issue widoczne na GitHubie z kotwicami i oznaczeniem OperationsImport
- [x] 6.7 Reguła w `.ai/rules/js.md` czytelna i zgodna z `.docs/design-system.md`
- [x] 6.8 Lista kontrolna skilla `/10x-ui` (`references/ui-quality-checklist.md`) przejrzana przed `/10x-impl-review`
