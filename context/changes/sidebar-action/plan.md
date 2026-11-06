# Funkcjonalny sidebar i powłoka bez belki: plan implementacji

## Overview

Powłoka aplikacji traci górną belkę z tytułem i okruszkami, a sidebar przestaje się zwijać na desktopie (na telefonie zostaje wejście ☰ w wąskim pasku). Sidebar dostaje to, co pokazuje komponent `Sidebar` i `CommandPalette` z Atramentu: rok budżetu w segmentach, globalne skróty z podpowiedziami przy menu i linijką „Skróty: …” oraz paletę „Szukaj i przejdź ⌘K” z widokami, akcjami i kategoriami z realizacją. Decyzje zapisuje ADR-0054, który odwraca część ADR-0053.

## Current State Analysis

Źródło: `context/changes/sidebar-action/research.md` (zarzuty C1–C5) i decyzje z `change.md`.

- **Belka:** `layouts/app/AppSidebarLayout.vue:28` renderuje `AppSidebarHeader` (64 px, `SidebarTrigger` + `Breadcrumbs`), zasilany `setLayoutProps({ breadcrumbs })` w 11 stronach (`Dashboard.vue:97`, `Operations.vue:114`, `OperationsImport.vue:93`, `OperationsImportPreview.vue:170`, `MonthPlan.vue:52`, `Budget.vue:125`, `TermDeposits.vue:55`, `Accounts.vue:52`, `settings/Profile.vue:18`, `settings/Security.vue:25`, `settings/Appearance.vue:10`). Każda strona ma własne `h1.page-title`, więc tytuł znika tylko z belki.
- **Zwijanie:** `AppSidebar.vue:76` `collapsible="icon"`; stan z ciasteczka `sidebar_state` (`HandleInertiaRequests.php:48` → `sidebarOpen` → `AppShell.vue:14,21`, wyjątek szyfrowania `bootstrap/app.php:21`). `ui/sidebar/Sidebar.vue:24-31` przy `collapsible="none"` renderuje stałą kolumnę także na telefonie (bez `Sheet`), więc `none` nie wchodzi w grę. Jedyne wejście do arkusza na telefonie to `SidebarTrigger` w belce i Ctrl/⌘+B.
- **Rok:** `BudgetYearSwitcher.vue` to `DropdownMenu` z radiami lat i „Dodaj rok”; wybór `router.post(BudgetController.selectYear())` z `pending` i toastem błędu (`lib/budget-year-selection.ts`). `shell.budgetYears` to tylko lata z budżetem, rosnąco (`ShellState.php:26-47`). `CreateBudgetDialog.vue:45-60` sam wylicza domyślny rok (`bieżący+1` przy istniejących latach).
- **Skróty:** brak globalnej obsługi klawiszy poza `ui/` i helpera „fokus w polu”. `mainNavItems` to lokalna stała `AppSidebar.vue:41-72`. Dialogi operacji i menu „Dodaj operację” żyją tylko w `pages/Operations.vue:86-93,164-170` i `OperationsPageActions.vue`; szukajka Operacji (`OperationsFilters.vue:393-400`) nie ma `id` i nie istnieje przy pustej liście (`OperationsListPanel.vue:207`).
- **Paleta:** brak `components/ui/command`. shadcn-vue `command` (new-york-v4) stoi na reka `Listbox*` + `useFilter`; zależności są zainstalowane, ale rejestr ciągnie `dialog`, a lokalny `ui/dialog` jest dostosowany do Atramentu (`DialogContent.vue:37`, `DialogOverlay.vue:17`). `ListboxFilter` nie daje `role="combobox"`/`aria-expanded`/`aria-controls`.
- **Kategorie:** realizacja roczna to kolumna `categories.execution_minor_units` (jeden `SELECT` na budżet). Częściowy `router.reload({ only })` wykonuje całą akcję kontrolera bieżącej strony (leniwe są tylko domknięcia), więc na Operacjach przeliczyłby listę. `useHttp` jest już użyty w `components/BudgetCategoryList.vue:73-186`.

## Desired End State

- Żadna strona aplikacji nie ma belki nad treścią; `main` zaczyna się od wiersza tytułu strony. `AppSidebarHeader`, `Breadcrumbs`, `components/ui/breadcrumb/*`, typ `BreadcrumbItem` i prop `breadcrumbs` nie istnieją.
- Na desktopie sidebar jest zawsze rozwinięty (224 px), bez trybu ikon; prop `sidebarOpen` i ciasteczko `sidebar_state` nie są czytane. Na telefonie (szerokość ≤ 768 px, ten sam próg co `SidebarProvider`) nad treścią stoi pasek z ☰ i znakiem „HT”, który otwiera arkusz z sidebarem.
- „Rok budżetu” to segmenty: każdy rok z budżetem plus „<najwyższy+1> +”, po 3 w wierszu; bez budżetu jeden segment „<bieżący rok> +”. Wybór roku działa jednym kliknięciem z zachowanym oczekiwaniem i toastem błędu; „+” otwiera dialog nowego budżetu z tym rokiem.
- Pozycje menu mają znacznik skrótu `1`–`n` (numer z pozycji na liście), nad użytkownikiem stoi „Skróty: 1–n widoki, N nowa operacja, / szukaj, Esc zamknij.”. `1`–`n` przechodzą do widoku, `N` otwiera menu „Dodaj operację” na Operacjach (z innej strony po przejściu), `/` ustawia fokus w szukajce Operacji (z innej strony po przejściu). Skróty milczą w polach edycji i przy otwartym dialogu, arkuszu lub menu.
- „Szukaj i przejdź ⌘K” w sidebarze i ⌘K/Ctrl+K otwierają paletę: grupa „Widoki i akcje” (widoki ze skrótami, „Dodaj operację”, „Importuj CSV”, „Dodaj rok budżetu”, „Ustawienia”) i „Kategorie: operacje” (aktywne wydatki i dochody roku z sidebara, z realizacją roczną, wczytywane endpointem JSON przy otwarciu). Pierwsza pozycja zaznaczona, Enter uruchamia, Esc zamyka; pole ma rolę `combobox`, okno `dialog` z pułapką fokusu.
- `/dev/ui/sidebar` pokazuje pasek mobilny, segmenty (1 rok, wiele lat, pusty, oczekiwanie), znaczniki skrótów i stany palety (domyślny, filtr, brak wyników, wczytywanie, błąd, brak roku budżetu) w obu motywach.
- ADR-0054, `design-system.md` („Sidebar”, „Gotowe elementy”), `.ai/rules/js-sidebar.md` i `.ai/rules/js-import.md` opisują nowy stan.

Weryfikacja: `npm test`, `npm run check:ui`, `npm run types:check`, `npm run build`, `composer ci:check`, `vendor/bin/sloppy ci`, testy Feature na PostgreSQL, specy Playwright `sidebar-current-section`, `shell-mobile-menu`, `global-shortcuts`, `command-palette`, zrzuty 1280/390 px w obu motywach.

### Key Discoveries:

- `SidebarProvider.vue:20`: `isMobile = useMediaQuery("(max-width: 768px)")`, a Tailwind `md` to `min-width: 768px`. Przy dokładnie 768 px sidebar jest arkuszem, a `md:hidden` ukryłby pasek z ☰ — pasek musi zależeć od `isMobile` z `useSidebar()`.
- `SidebarProvider` z kontrolowanym `open` (`useVModel`, `passive: false` gdy prop podany) ignoruje `setOpen` z Ctrl/⌘+B na desktopie, a na telefonie skrót dalej otwiera arkusz — prymitywu nie trzeba zmieniać.
- `tests/e2e/sidebar-current-section.spec.ts:9-12` szuka linku po dokładnej nazwie („Dashboard”). Znacznik skrótu wewnątrz linku musi mieć `aria-hidden="true"`, a skrót idzie do `aria-keyshortcuts`.
- `/` na polskiej klawiaturze to Shift+7 (`event.key === '/'` z `shiftKey`); predykat nie może odrzucać Shift dla `/`.
- `context/changes/period-report-by-category/plan.md:182` wstawia „Raporty” po „Plan miesiąca”; numery skrótów i tekst „1–n” muszą wynikać z listy, nie z literałów.
- Teksty z parametrami mają w `lang/pl/ui.php` składnię `:name` (`:134`, `:140`), podstawianą `.replace` po stronie klienta (`lib/import-row-reasons.ts:25-26`).
- `BudgetCategoryList.vue:182` ma `catch {}` bez logowania przyczyny; nowy kod tego wzorca nie powtarza (CLAUDE.local: błąd logowany z przyczyną).
- Zmiana kontekstu reka: zamknij jedną warstwę → `nextTick` → otwórz drugą (`BudgetYearSwitcher.vue:51-55`); tak samo paleta → dialog nowego budżetu.

## What We're NOT Doing

- „Raporty” w menu: dodaje je `period-report-by-category` do wspólnej listy widoków z fazy 3 (dostanie numer skrótu z pozycji).
- Wylogowanie i zmiana motywu w palecie; osobne pozycje „Dodaj wydatek/dochód/…” w palecie (jedna pozycja „Dodaj operację” otwiera menu typów).
- Zarchiwizowane kategorie, kategorie innych lat niż wybrany w sidebarze i realizacja miesięczna w palecie.
- Konfigurowalne skróty i obsługa `Esc` poza tym, co robi reka (`DismissableLayer`).
- Zmiany w `ui/dialog`, `ui/sheet` i `SidebarProvider` (Ctrl/⌘+B zostaje; na desktopie nic nie zmienia, na telefonie otwiera arkusz).
- Nowe pakiety npm i zmiany `package.json`/`package-lock.json`.
- Nowy parametr adresu `/operations` dla `N` i `/` (intencja żyje po stronie klienta).
- Zmiany reguł glosariusza.

## Implementation Approach

Od zewnątrz do środka: najpierw powłoka (belka, zwijanie, wejście na telefonie) i ADR-0054 z całym zestawem decyzji, potem samodzielny przełącznik roku, potem wspólna lista widoków z obsługą klawiszy i intencją dla Operacji, na końcu paleta, która używa listy widoków, intencji `N` i dialogu nowego budżetu z poprzednich faz. Czysta logika (segmenty lat, predykat skrótu, budowa pozycji palety, etykieta ⌘K/Ctrl+K) siedzi w `resources/js/lib` z testami Vitest bez DOM i bez importu `@/routes`; stan współdzielony (otwarta paleta, intencja Operacji, dialog nowego budżetu) w `resources/js/composables`. Jedyna zmiana serwera poza usunięciem `sidebarOpen` to endpoint JSON kategorii palety.

## Critical Implementation Details

**Timing & lifecycle.** Intencja Operacji (`add-operation` / `focus-search`) jest jednym współdzielonym `ref`. Na stronie Operacji handler tylko ją ustawia; z innej strony ustawia ją i robi `router.visit(operations())` z `onFinish`, które ją czyści. `Operations.vue` konsumuje ją przez `watch(..., { immediate: true })` i czyści po użyciu — dzięki temu działa i po zamontowaniu, i na miejscu, a intencja z nieudanej wizyty nie wybucha przy późniejszym wejściu.

**User experience spec.** Skróty jednoklawiszowe (`1`–`n`, `N`, `/`) milczą, gdy zdarzenie ma Ctrl/⌘/Alt, gdy `isComposing` lub `repeat`, gdy cel to pole edycji (`input` tekstowy, `textarea`, `select`, `contenteditable`) albo gdy otwarta jest warstwa reka (dialog, arkusz, menu, lista `select`). ⌘K/Ctrl+K działa także w polu, ale nie przy otwartej warstwie. Każdy obsłużony skrót robi `preventDefault` (inaczej `/` wpisałby się w szukajkę, na której właśnie ustawiono fokus).

## Faza 1: Powłoka bez belki i bez zwijania

### Overview

Usuwa belkę i okruszki, wyłącza zwijanie na desktopie, daje telefonowi pasek z ☰ i zapisuje ADR-0054 z całym zestawem decyzji tej zmiany (C1, C2).

### Changes Required:

#### 1. ADR-0054

**File**: `context/foundation/adr/0054-sidebar-actions-and-shell-without-header.md`

**Intent**: Zapisz decyzje tej zmiany i wskaż, które akapity ADR-0053 (przycisk roku, pas 3rem, odrzucenie palety, skrótów i segmentów) i ADR-0052 akapit 1 (okruszki importu) przestają obowiązywać.

**Contract**: Treść jak w ADR-0053 (angielski, akapity tematyczne): powłoka bez belki i okruszków; desktop bez zwijania (kontrolowany `open`, koniec trybu ikon, `sidebarOpen` i `sidebar_state` usunięte); pasek mobilny sterowany `isMobile` (próg 768 px); segmenty lat (wszystkie lata, po 3 w wierszu, „<max+1> +”, pusty „<bieżący> +”); skróty z numerem z pozycji listy, warunki milczenia, intencja Operacji; paleta (shadcn-vue `command` bez nadpisania `ui/dialog`, rola `combobox`, endpoint JSON zamiast propa i `reload` — z powodem, zakres kategorii, akcje); wiersze w `ui/command`, jeśli faza 4 musi je zmienić dla dostępności. Bez zmian glosariusza.

#### 2. Layout bez belki

**File**: `resources/js/layouts/app/AppSidebarLayout.vue`, `resources/js/layouts/AppLayout.vue`, `resources/js/components/AppMobileBar.vue` (nowy), `resources/js/components/AppSidebarHeader.vue` (usunięty), `resources/js/components/Breadcrumbs.vue` (usunięty), `resources/js/components/ui/breadcrumb/*` (usunięte), `resources/js/types/navigation.ts`, 11 stron z `setLayoutProps({ breadcrumbs })`, `resources/js/pages/dev/SettingsKitchenSink.vue`

**Intent**: Treść zaczyna się od wiersza tytułu strony; na telefonie nad nią stoi pasek z ☰ (`SidebarTrigger`) i znakiem „HT” z nazwą produktu (link do Dashboardu), bez tytułu strony.

**Contract**: `AppSidebarLayout` i `AppLayout` bez propa `breadcrumbs`; `AppMobileBar` renderowany w `AppContent` z `v-if="isMobile"` (`useSidebar()`), sam komponent zawsze rysuje pasek (żeby kitchen sink mógł go pokazać). Przycisk ☰ ma polską nazwę dostępną `t.shell.open_menu` („Otwórz menu”). Wywołania `setLayoutProps` zawierające tylko `breadcrumbs` znikają w całości. Typ `BreadcrumbItem` usunięty. Wysokość paska z istniejącej skali (bez literałów px), kolory `--sidebar-*` albo `background`/`border` jak reszta powłoki — rozstrzyga zgodność z kitchen sinkiem w obu motywach. Tekst o okruszkach w `SettingsKitchenSink.vue:57` poprawiony.

#### 3. Sidebar bez zwijania

**File**: `resources/js/components/AppShell.vue`, `resources/js/components/AppSidebar.vue`, `resources/js/components/NavMain.vue`, `resources/js/components/NavUser.vue`, `resources/js/components/AppLogo.vue`, `resources/js/components/UserInfo.vue`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/global.d.ts`, `bootstrap/app.php`

**Intent**: Desktop pokazuje sidebar zawsze rozwinięty; kod trybu ikon w komponentach domenowych znika.

**Contract**: `AppShell` przekazuje `SidebarProvider` `:open="true"` (kontrolowany) zamiast `default-open` z `sidebarOpen`; `AppSidebar` `collapsible="offcanvas"`. Prop współdzielony `sidebarOpen` usunięty po obu stronach (PHP, `global.d.ts:21`), `'sidebar_state'` usunięty z `except` w `bootstrap/app.php:21`. Usunięte `group-data-[collapsible=icon]:*` i `:tooltip` z `NavMain`, `NavUser`, `AppLogo`, `UserInfo` (`BudgetYearSwitcher` przebudowuje faza 2). Prymitywy `ui/sidebar/*` bez zmian.

#### 4. Testy kontraktu, lista literałów, kitchen sink

**File**: `resources/js/ui-contract.test.ts`, `scripts/check-ui-literals.mjs`, `resources/js/pages/dev/SidebarKitchenSink.vue`, `tests/Feature/AppShellTest.php`, `tests/e2e/shell-mobile-menu.spec.ts` (nowy)

**Intent**: Strażnicy pilnują nowego wymagania zamiast trybu ikon; telefon ma test, że menu jest osiągalne.

**Contract**:
- `ui-contract.test.ts:239-247` (wymaganie zmienione): zamiast `mx-auto` w pasie ikon — `AppSidebar.vue` nie zawiera `collapsible="icon"`, pliki domenowe sidebara nie zawierają `group-data-[collapsible=icon]`, `AppShell.vue` przekazuje `:open="true"`, `AppSidebarLayout.vue` nie importuje `AppSidebarHeader`.
- `check-ui-literals.mjs`: wpis `AppSidebarHeader.vue` zamieniony na `AppMobileBar.vue`.
- Kitchen sink: ramki `collapsed-years` i `collapsed-empty` usunięte; nowa ramka `mobile-bar`.
- `AppShellTest`: odpowiedź nie ma propa `sidebarOpen` (`->missing('sidebarOpen')`) i ma `translations.shell.open_menu`.
- Playwright `shell-mobile-menu.spec.ts` (viewport 390×844 przez `test.use`): na `/dashboard` nie ma `[data-slot="sidebar"]` widocznego, klik „Otwórz menu” otwiera arkusz, klik „Konta” przechodzi na `/accounts`.

#### 5. Dokumenty i reguły

**File**: `context/foundation/design-system.md` („Sidebar”, akapit wstępny z listą ADR), `.ai/rules/js-sidebar.md`, `.ai/rules/js-import.md`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: Dokumenty opisują powłokę bez belki i bez pasa 3rem.

**Contract**: `design-system.md:117,129,131` bez trybu ikon, z paskiem mobilnym i ADR-0054 w liście ADR; `js-sidebar.md`: akapit „Header and footer slots survive the collapsed strip” zastąpiony zasadą „no collapse on desktop, the phone bar follows `isMobile`”, ostatnie zdanie bez „icon mode”; `paths:` dopisuje `resources/js/components/AppMobileBar.vue`, `resources/js/components/BudgetYear*.vue`, `resources/js/components/CommandPalette*.vue`, `resources/js/lib/global-shortcuts*.ts`, `resources/js/lib/command-palette*.ts`, `resources/js/lib/budget-year-segments*.ts`, po czym `php artisan boost:index-rules`; `js-import.md:27` bez okruszków. Klucz `shell.open_menu` w PHP i w typie `UiTranslations`.

### Success Criteria:

#### Automated Verification:

- Testy JS: `npm test`
- Literały: `npm run check:ui`
- Typy i build: `npm run build && npm run types:check`
- Feature: `php artisan test --compact tests/Feature/AppShellTest.php tests/Feature/SidebarKitchenSinkTest.php`
- Brak odwołań: `grep -rn "AppSidebarHeader\|Breadcrumb\|breadcrumbs\|sidebarOpen\|sidebar_state" resources/js app bootstrap tests` zwraca pusto
- Indeks reguł aktualny: `php artisan boost:index-rules && git diff --stat .ai/rules/index.md` pokazuje tylko nowe globy `js-sidebar.md`
- E2E: `npx playwright test tests/e2e/sidebar-current-section.spec.ts tests/e2e/shell-mobile-menu.spec.ts`

#### Manual Verification:

- 1280 px, oba motywy: Dashboard, Operacje, Plan miesiąca i Ustawienia zaczynają się od wiersza tytułu, bez belki; sidebar nie daje się zwinąć (⌘B nic nie zmienia)
- 390 px i dokładnie 768 px: pasek z ☰ jest widoczny, arkusz się otwiera i zamyka, nawigacja działa
- Stare ciasteczko `sidebar_state=false` w przeglądarce nie zwija sidebara

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych przed fazą 2.

---

## Faza 2: Rok budżetu w segmentach

### Overview

Zamienia menu roku na segmenty z „+” do tworzenia brakującego roku, z zachowanym oczekiwaniem i obsługą błędu (C5).

### Changes Required:

#### 1. Logika segmentów

**File**: `resources/js/lib/budget-year-segments.ts`, `resources/js/lib/budget-year-segments.test.ts` (nowe)

**Intent**: Jedno miejsce wylicza segmenty z lat z budżetem, wybranego roku i bieżącego roku kalendarzowego.

**Contract**: `budgetYearSegments(budgetYears: number[], selectedYear: number | null, currentYear: number): BudgetYearSegment[]`, gdzie `BudgetYearSegment = { year: number; kind: 'select' | 'create'; selected: boolean }`. Lata rosnąco, na końcu jeden `create` z `max(budgetYears) + 1`; przy pustej liście jeden `create` z `currentYear`. Testy: pusta lista, jeden rok, wiele lat (także nieposortowane wejście), wybrany rok poza listą.

#### 2. Przełącznik

**File**: `resources/js/components/BudgetYearSwitcher.vue`, `resources/js/components/CreateBudgetDialog.vue`, `resources/js/composables/useCreateBudgetDialog.ts` (nowy), `resources/js/components/AppSidebar.vue`, `resources/css/app.css`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: Segmenty zastępują `DropdownMenu`; wybór roku i obsługa błędu działają jak dziś; „+” otwiera dialog nowego budżetu z rokiem z segmentu.

**Contract**:
- Grupa `role="group"` nazwana etykietą „Rok budżetu” (`aria-labelledby` na `SidebarGroupLabel`); segment roku to `button` z `aria-pressed`, `tabular-nums`; segment `create` to `button` z tekstem „<rok> +” i nazwą dostępną `t.shell.add_budget_for_year` („Dodaj budżet na rok :year”).
- `pending`: grupa `aria-busy`, segmenty `aria-disabled` i brak drugiego POST; `listenForBudgetYearSelectionFailure` / `notifyBudgetYearSelectionFailed` bez zmian; `defineExpose({ pending })` zostaje.
- `CreateBudgetDialog` dostaje opcjonalny prop `initialYear?: number`, który — gdy podany — zastępuje wyliczony rok domyślny przy otwarciu. Jedna instancja dialogu dla powłoki: stan otwarcia i rok w `useCreateBudgetDialog()` (współdzielony z paletą w fazie 4), montowana w `AppSidebar`.
- Style `.sidebar-year-segments` w `@layer components`: siatka 3 kolumn, kontrolka z `--control-height-sm`, wyłącznie tokeny `--sidebar-*` (wybrany segment na `sidebar-strong` jak `.ht-yseg` we wzorcu, odpoczynek/hover `sidebar-accent`, fokus `sidebar-ring`); kontrast tekstu ≥ 4.5:1 w obu motywach.
- Teksty `shell.select_year` i `shell.no_budget_year` usunięte, jeśli bez konsumentów (sprawdzić `grep`).

#### 3. Strażnicy i kitchen sink

**File**: `resources/js/ui-contract.test.ts`, `resources/js/pages/dev/SidebarKitchenSink.vue`, `tests/Feature/AppShellTest.php`

**Intent**: Testy i kitchen sink pilnują segmentów zamiast przycisku z menu.

**Contract**: `ui-contract.test.ts:248-252` (wymaganie zmienione): przełącznik ma `role="group"`, `:aria-pressed` i nie importuje `DropdownMenu`. Ramki: `years-one` (2026 | 2027 +), `years-many` (2019–2026, trzy wiersze), `years-empty`, `years-pending` (przez `ref` i `pending.value = true` po zamontowaniu), przycisk „Pokaż błąd wyboru roku” zostaje. `AppShellTest`: `translations.shell.add_budget_for_year`.

#### 4. Dokumenty

**File**: `context/foundation/design-system.md`, `.ai/rules/js-sidebar.md`

**Intent**: Rola `sidebar-strong` i `sidebar-accent` w tabeli „Sidebar” opisuje segmenty (wybrany rok, segment w spoczynku), nie „kafelek przełącznika roku”.

**Contract**: `design-system.md:123,125` i zdanie o etykietach sekcji; `js-sidebar.md`: „the selected year segment uses `sidebar-strong`”, kitchen sink: „the year segments (one, many, empty, pending)”.

### Success Criteria:

#### Automated Verification:

- Testy JS: `npm test`
- Literały: `npm run check:ui`
- Typy i build: `npm run build && npm run types:check`
- Feature: `php artisan test --compact tests/Feature/BudgetTest.php tests/Feature/AppShellTest.php`

#### Manual Verification:

- `/dev/ui/sidebar`, oba motywy: segmenty w ramkach `years-*` mieszczą się w 224 px, wybrany rok czytelny, fokus klawiatury widoczny
- Klik innego roku przechodzi na Budżet tego roku jednym kliknięciem; podczas żądania segmenty nie przyjmują kliknięć
- „2027 +” otwiera dialog z rokiem 2027; po utworzeniu segment staje się zwykłym rokiem, a „+” przesuwa się na 2028
- Bez budżetu: jeden segment „<bieżący rok> +”

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych przed fazą 3.

---

## Faza 3: Wspólna lista widoków i skróty

### Overview

Wprowadza jedną listę widoków z numerami skrótów, globalną obsługę `1`–`n`, `N` i `/`, znaczniki przy menu, linijkę „Skróty: …” i intencję, którą konsumują Operacje (C4).

### Changes Required:

#### 1. Predykat skrótu

**File**: `resources/js/lib/global-shortcuts.ts`, `resources/js/lib/global-shortcuts.test.ts` (nowe)

**Intent**: Czysta funkcja decyduje, czy i jaki skrót obsłużyć, na podstawie opisu zdarzenia — testowalna bez DOM.

**Contract**: `resolveGlobalShortcut(input: ShortcutInput, viewCount: number): GlobalShortcut | null`, gdzie `ShortcutInput = { key: string; ctrlKey: boolean; metaKey: boolean; altKey: boolean; shiftKey: boolean; isComposing: boolean; repeat: boolean; targetIsEditable: boolean; hasOpenLayer: boolean }`, a `GlobalShortcut = { kind: 'view'; index: number } | { kind: 'add-operation' } | { kind: 'focus-search' }` (faza 4 dopisuje `{ kind: 'palette' }`). Cyfry `1`…`viewCount` → `view` z indeksem 0-based; `n`/`N` → `add-operation`; `/` (także z Shift) → `focus-search`. Testy: każda gałąź, cyfra spoza zakresu, modyfikatory Ctrl/⌘/Alt, Shift przy cyfrze i przy `/`, pole edycji, otwarta warstwa, `isComposing`, `repeat`.

#### 2. Wspólna lista widoków i znaczniki

**File**: `resources/js/composables/useAppViews.ts` (nowy), `resources/js/components/AppSidebar.vue`, `resources/js/components/NavMain.vue`, `resources/js/types/navigation.ts`, `resources/css/app.css`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: Sidebar, skróty i paleta czytają jedną listę widoków w tej samej kolejności; numer skrótu wynika z pozycji.

**Contract**: `useAppViews(): NavItem[]` przenosi `mainNavItems` z `AppSidebar.vue:41-72`; `NavItem` dostaje `shortcut?: string` (`String(index + 1)`). `NavMain` rysuje `<kbd class="kbd" aria-hidden="true">` po prawej i ustawia `aria-keyshortcuts` na linku (nazwa dostępna linku bez zmian). Nad `NavUser` w `SidebarFooter` stoi `<p class="sidebar-hint">` z `t.shell.shortcuts_hint` („Skróty: 1–:last widoki, N nowa operacja, / szukaj, Esc zamknij.”), `:last` podstawiony długością listy. Klasy `.kbd` (mono 11/16, 500, ramka `currentColor`, `radius-xs`, jak `.ht-kbd`) i `.sidebar-hint` (11/16, `sidebar-foreground`) w `@layer components`.

#### 3. Obsługa klawiszy i intencja Operacji

**File**: `resources/js/composables/useGlobalShortcuts.ts`, `resources/js/composables/useOperationsIntent.ts` (nowe), `resources/js/layouts/app/AppSidebarLayout.vue`, `resources/js/pages/Operations.vue`, `resources/js/components/OperationsPageActions.vue`, `resources/js/components/OperationsFilters.vue`

**Intent**: Jeden nasłuch `keydown` w layoucie aplikacji tłumaczy zdarzenie na `ShortcutInput`, wywołuje predykat i wykonuje akcję; `N` i `/` działają z każdej strony przez intencję.

**Contract**:
- `useGlobalShortcuts()` montowany w `AppSidebarLayout` (tylko strony aplikacji). `targetIsEditable` z `event.target` (pole tekstowe, `textarea`, `select`, `isContentEditable`); `hasOpenLayer` z obecności otwartej warstwy reka w DOM (`[role="dialog"]`, `[role="menu"]`, `[role="listbox"]` z `data-state="open"`). Obsłużony skrót → `preventDefault`. `view` → `router.visit(view.href)`.
- `useOperationsIntent`: `requestOperationsIntent(intent: 'add-operation' | 'focus-search')` i współdzielony `ref`; na stronie `Operations` (`usePage().component`) tylko ustawia intencję, inaczej ustawia ją i robi `router.visit(operations(), { onFinish: clear })`.
- `Operations.vue` obserwuje intencję (`immediate`) i czyści ją po użyciu: `add-operation` → menu „Dodaj operację” otwarte (`OperationsPageActions` dostaje `v-model:menu-open` związany z `DropdownMenu` `open`); `focus-search` → po `nextTick` fokus na `#operations-search`, jeśli istnieje (pusta lista: brak efektu).
- `OperationsFilters.vue:393`: szukajka dostaje `id="operations-search"`.

#### 4. Testy, kitchen sink, dokumenty

**File**: `tests/e2e/global-shortcuts.spec.ts` (nowy), `resources/js/ui-contract.test.ts`, `resources/js/pages/dev/SidebarKitchenSink.vue`, `tests/Feature/AppShellTest.php`, `context/foundation/design-system.md`, `.ai/rules/js-sidebar.md`, `.ai/rules/js-operations.md`

**Intent**: Skróty są sprawdzone w przeglądarce, a zasady opisują listę widoków i skróty.

**Contract**:
- Playwright: `2` na `/dashboard` → `/budget`; fokus w szukajce Operacji i wpisanie `1` → adres bez zmian, znak w polu; `N` na `/dashboard` → `/operations` z widocznym `role="menu"` „Dodaj operację”; `N` na `/operations` → to samo menu bez przejścia; `/` na `/dashboard` → `/operations` z fokusem na szukajce i pustym polem; przy otwartym dialogu wydatku `2` nie przechodzi.
- `ui-contract.test.ts`: `NavMain.vue` ma `aria-hidden="true"` na `kbd` i `aria-keyshortcuts`; `AppSidebar.vue` nie definiuje lokalnej tablicy widoków.
- Kitchen sink: ramka ze znacznikami i linijką skrótów (istniejące ramki ją pokażą, jeśli renderują `AppSidebar`). `AppShellTest`: `translations.shell.shortcuts_hint`.
- Dokumenty: `design-system.md` — styl `kbd` i `sidebar-hint` w tabeli/„Sidebar”; `js-sidebar.md` — „views come from `useAppViews`, the shortcut number is the position; shortcuts are silent in fields and open layers”; `js-operations.md` — szukajka ma `id="operations-search"`, menu „Dodaj operację” jest sterowalne intencją.

### Success Criteria:

#### Automated Verification:

- Testy JS (w tym `global-shortcuts.test.ts`): `npm test`
- Literały: `npm run check:ui`
- Typy i build: `npm run build && npm run types:check`
- Feature: `php artisan test --compact tests/Feature/AppShellTest.php`
- E2E: `npx playwright test tests/e2e/global-shortcuts.spec.ts tests/e2e/sidebar-current-section.spec.ts`

#### Manual Verification:

- Znaczniki `1`–`6` przy menu i linijka „Skróty: 1–6 …” czytelne w obu motywach, aktywna pozycja z czytelnym znacznikiem
- `N` otwiera menu typów; wyłączone typy (transfer, ruch kapitału) nadal pokazują powód
- Czytnik ekranu (lub drzewo dostępności) podaje link jako „Dashboard”, ze skrótem z `aria-keyshortcuts`

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych przed fazą 4.

---

## Faza 4: Paleta ⌘K

### Overview

Dodaje endpoint kategorii, komponent `command` z shadcn-vue, paletę „Szukaj i przejdź” z grupami i stanami, przycisk w sidebarze i skrót ⌘K/Ctrl+K (C3).

### Changes Required:

#### 1. Endpoint kategorii

**File**: `app/Support/CommandPaletteCategories.php`, `app/Http/Controllers/CommandPaletteCategoryController.php` (nowe), `routes/web.php`, `tests/Feature/CommandPaletteCategoriesTest.php` (nowy)

**Intent**: Paleta przy każdym otwarciu czyta aktywne kategorie roku wybranego w sidebarze z realizacją roczną — jednym zapytaniem, bez kosztu na zwykłych żądaniach.

**Contract**: `GET command-palette/categories`, nazwa `command-palette.categories`, w grupie `auth` + `verified`, kontroler jednometodowy zwraca JSON `{ budget_year: int|null, categories: list<{ id: int, name: string, type: 'expense'|'income', icon: string, color: string, execution_minor_units: int }> }`. Rok z `SelectedBudgetYear::resolve($request)`; kategorie budżetu tego roku zalogowanego użytkownika, `archived_at` null, wydatki przed dochodami, w każdym typie `ordered()`; wygląd z `CategoryAppearance::fields`. Test (klasa PHPUnit jak `SidebarKitchenSinkTest`): rok z sesji, rok domyślny bez sesji, brak zarchiwizowanych, brak kategorii innego użytkownika i innego roku, kolejność, `budget_year: null` i pusta lista bez budżetu, gość przekierowany na logowanie.

#### 2. Komponent `command`

**File**: `resources/js/components/ui/command/*` (nowe z rejestru)

**Intent**: Bazowy komponent palety przychodzi ścieżką shadcn-vue, bez naruszania lokalnego `ui/dialog` i zależności.

**Contract**: `npx shadcn-vue@latest add command`, odmowa nadpisania `ui/dialog`; po instalacji `git diff --exit-code package.json package-lock.json resources/js/components/ui/dialog`. Jeśli `CommandInput` nie przekazuje atrybutów do `ListboxFilter`, poprawka przekazania (dla każdego konsumenta, nie pod jeden ekran) jest nazwana w ADR-0054.

#### 3. Paleta

**File**: `resources/js/lib/command-palette-items.ts`, `resources/js/lib/command-palette-items.test.ts`, `resources/js/components/CommandPalette.vue`, `resources/js/composables/useCommandPalette.ts` (nowe), `resources/js/lib/dashboard-categories.ts`, `resources/js/layouts/app/AppSidebarLayout.vue`, `resources/css/app.css`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: Paleta z grupami „Widoki i akcje” i „Kategorie: operacje”, w kolorach sidebara, pierwsza pozycja zaznaczona, Enter uruchamia, Esc zamyka; każda pozycja prowadzi tam, gdzie obiecuje.

**Contract**:
- `command-palette-items.ts` (bez `@/routes`): typy pozycji `{ kind: 'view'; index } | { kind: 'add-operation' } | { kind: 'import' } | { kind: 'add-budget-year' } | { kind: 'settings' } | { kind: 'category'; category }`, budowa listy akcji w stałej kolejności (widoki ze skrótami, „Dodaj operację” `N`, „Importuj CSV”, „Dodaj rok budżetu”, „Ustawienia”), wartość wyszukiwania pozycji (etykieta; dla kategorii nazwa) i etykieta skrótu palety `paletteShortcutLabel(platform: string): '⌘K' | 'Ctrl+K'`. Testy: kolejność i skróty, etykieta dla macOS i innych.
- `categoryOperationsQuery` (`dashboard-categories.ts:34`) przyjmuje `{ name: string }` zamiast pełnego `DashboardCategory` (używa tylko nazwy); wołający bez zmian.
- `CommandPalette.vue` prezentacyjny: propsy `open`, `views`, `categories: { status: 'loading' } | { status: 'error' } | { status: 'loaded'; budgetYear: number | null; items: PaletteCategory[] }`, emit `select`. reka `Dialog` + lokalny `DialogContent` (`:show-close-button="false"`, klasa `command-palette`), tytuł `sr-only` „Szukaj i przejdź”; `CommandInput` z ikoną, `kbd` „Esc”, `role="combobox"`, `aria-expanded="true"`, `aria-controls` = id listy, `aria-autocomplete="list"`; `CommandEmpty` „Brak wyników”; kategoria: `CategoryChip` (bez nazwy) + nazwa + `formatMoney(execution_minor_units)` `tabular-nums` z prawej, dochód z dopiskiem `meta` „dochód”. Stany grupy kategorii: wczytywanie (wiersz szkieletu), błąd („Nie udało się wczytać kategorii.”), brak roku budżetu („Brak budżetu — dodaj rok, aby szukać kategorii.”).
- `useCommandPalette()`: współdzielony `open`; przy każdym otwarciu `useHttp` GET `command-palette.categories` (Wayfinder), błąd → stan `error` i `console.error` z przyczyną (bez pustego `catch`); odpowiedź po zamknięciu palety jest ignorowana. Wykonanie pozycji: najpierw zamknięcie palety, potem `view` → `router.visit`; `add-operation` → `requestOperationsIntent('add-operation')`; `import` → `CsvImportController.create.url()`; `add-budget-year` → `nextTick` → `useCreateBudgetDialog().open(max+1 albo bieżący rok, z `budgetYearSegments`)`; `settings` → `edit()` z `@/routes/profile`; `category` → `router.visit(operations({ query: categoryOperationsQuery(category, type, budgetYear) }))`.
- `CommandPalette` montowany raz w `AppSidebarLayout`. Klasy `.command-palette*` w `@layer components`: tło `sidebar`, tekst `sidebar-strong`, zaznaczenie `sidebar-accent`, fokus `sidebar-ring`, `radius-xl`, `shadow-dialog`; szerokość nowym tokenem wymiaru `--command-palette-width: 520px` (bez wariantu `.dark`).
- Teksty w nowej sekcji `palette` w `ui.php` (`Record<string, string>` w `UiTranslations`) i `shell.search_and_go` („Szukaj i przejdź”).

#### 4. Wejścia do palety

**File**: `resources/js/components/AppSidebar.vue`, `resources/js/lib/global-shortcuts.ts`, `resources/js/lib/global-shortcuts.test.ts`, `resources/js/composables/useGlobalShortcuts.ts`

**Intent**: Paletę otwiera przycisk „Szukaj i przejdź ⌘K” pod znakiem w sidebarze i ⌘K/Ctrl+K.

**Contract**: Przycisk to `SidebarMenuButton` z ikoną `Search`, tekstem `t.shell.search_and_go`, `kbd` z `paletteShortcutLabel` (`aria-hidden`) i `aria-keyshortcuts="Control+K Meta+K"`; tło `sidebar-accent` jak `.ht-side__search`. Predykat dostaje `{ kind: 'palette' }`: `k`/`K` z Ctrl albo ⌘, także w polu edycji, nigdy przy otwartej warstwie; testy tych gałęzi.

#### 5. Strażnicy, kitchen sink, dokumenty

**File**: `tests/e2e/command-palette.spec.ts` (nowy), `resources/js/ui-contract.test.ts`, `scripts/check-ui-literals.mjs`, `resources/js/pages/dev/SidebarKitchenSink.vue`, `tests/Feature/AppShellTest.php`, `context/foundation/design-system.md`, `.ai/rules/js-sidebar.md`

**Intent**: Paleta jest sprawdzona w przeglądarce, każdy stan widać w kitchen sinku, a dokumenty wymieniają ją w gotowych elementach.

**Contract**:
- Playwright: Ctrl+K na `/dashboard` otwiera `role="dialog"` z fokusem w `role="combobox"`; wpisanie „Lok” + Enter → `/term-deposits`; wpisanie nazwy kategorii z `demo:seed-household` + Enter → `/operations` z filtrem kategorii i roku; „Dodaj operację” + Enter → menu typów na Operacjach; Esc zamyka i oddaje fokus; klik przycisku w sidebarze otwiera paletę.
- `ui-contract.test.ts`: `CommandPalette.vue` ma `role="combobox"` i `aria-controls`; `components/ui/dialog` niezmieniony (brak importu `command` w `ui/dialog`).
- `check-ui-literals.mjs`: `CommandPalette.vue`, `BudgetYearSwitcher.vue` (już jest), `AppMobileBar.vue` (z fazy 1).
- Kitchen sink: `?palette=<stan>` otwiera paletę z danymi stałymi w stanach `default`, `filtered`, `no-results`, `loading`, `error`, `no-budget-year`.
- `AppShellTest`: `translations.shell.search_and_go`, `translations.palette.*`.
- Dokumenty: `design-system.md` — „Gotowe elementy”: `CommandPalette`; „Sidebar”: przycisk wyszukiwania, token `--command-palette-width`; `js-sidebar.md`: paleta czyta widoki z `useAppViews`, kategorie z endpointu, stany w kitchen sinku.

### Success Criteria:

#### Automated Verification:

- Feature (SQLite): `php artisan test --compact tests/Feature/CommandPaletteCategoriesTest.php tests/Feature/AppShellTest.php`
- Feature (PostgreSQL, worktree): `CommandPaletteCategoriesTest` i `tests/Feature/Nfr` przez obraz `sail-8.5/app` (polecenie „PostgreSQL tests, worktree” z `AGENTS.md`, ścieżki `tests/Feature/CommandPaletteCategoriesTest.php tests/Feature/Nfr`)
- Zależności bez zmian: `git diff --exit-code main -- package.json package-lock.json resources/js/components/ui/dialog`
- Testy JS: `npm test`
- Literały: `npm run check:ui`
- Typy i build: `npm run build && npm run types:check`
- Bramka: `composer ci:check` (w Sail) i `vendor/bin/sloppy ci` (host); `vendor/bin/pint --dirty --format agent`
- E2E: `npx playwright test`

#### Manual Verification:

- `/dev/ui/sidebar?palette=<stan>` dla sześciu stanów w obu motywach: kolory sidebara, kwoty wyrównane, szerokość 520 px na desktopie, pełna szerokość z marginesem na 390 px
- Polskie znaki: „zywnosc” znajduje „Żywność”
- Po zapisaniu operacji ponowne otwarcie palety pokazuje nową realizację kategorii
- „Dodaj rok budżetu” z palety otwiera dialog z właściwym rokiem i nie zostawia dwóch warstw
- Klawiatura: Tab nie wychodzi z palety, Esc oddaje fokus elementowi sprzed otwarcia

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych; potem zmiana idzie do `/10x-impl-review`.

---

## Testing Strategy

### Unit Tests:

- Vitest (`resources/js/lib/*.test.ts`, bez DOM): `budget-year-segments` (pusty, jeden, wiele, wybrany poza listą), `global-shortcuts` (każdy skrót, modyfikatory, Shift przy `/`, pole edycji, otwarta warstwa, `isComposing`, `repeat`, cyfra spoza zakresu, ⌘K w polu), `command-palette-items` (kolejność, skróty, etykieta ⌘K/Ctrl+K).
- `ui-contract.test.ts`: brak trybu ikon, segmenty z `aria-pressed`, `kbd` ukryty przed czytnikiem z `aria-keyshortcuts`, `combobox` w palecie.

### Integration Tests:

- Pest/PHPUnit Feature: `CommandPaletteCategoriesTest` (SQLite i PostgreSQL), `AppShellTest` (brak `sidebarOpen`, nowe klucze tłumaczeń), `BudgetTest` bez zmian jako regresja wyboru roku, `tests/Feature/Nfr` na PostgreSQL po usunięciu propa.
- Playwright: `sidebar-current-section` (regresja nazw dostępnych), `shell-mobile-menu`, `global-shortcuts`, `command-palette`.

### Manual Testing Steps:

1. 1280 px, oba motywy: przejdź przez wszystkie strony z menu — brak belki, sidebar stały, segmenty i znaczniki skrótów czytelne.
2. 390 i 768 px: ☰ otwiera arkusz, nawigacja i segmenty działają w arkuszu.
3. Na Dashboardzie naciśnij `N`, potem `Esc`; `/`; `3`; Ctrl+K, wpisz nazwę kategorii, Enter.
4. W polu kwoty dialogu wydatku wpisz `1`, `n`, `/` — nic poza wpisaniem znaków.
5. `/dev/ui/sidebar` i każdy stan `?palette=` w obu motywach.

## Performance Considerations

Zwykłe żądania tracą jedno pole współdzielone (`sidebarOpen`) i nie zyskują zapytań. Endpoint palety to odczyt `SelectedBudgetYear` (lata użytkownika) i jeden `SELECT` na `categories` jednego budżetu po indeksie `(budget_id, type, sort_order)`, wykonywany tylko przy otwarciu palety.

## Migration Notes

Brak migracji danych. Ciasteczko `sidebar_state` w przeglądarkach użytkowników zostaje, ale nikt go nie czyta; wyjęcie go z `except` w `bootstrap/app.php` jest bezpieczne, bo wartość nie jest już odczytywana po stronie serwera.

## References

- Research: `context/changes/sidebar-action/research.md`
- Zmiana i decyzje użytkownika: `context/changes/sidebar-action/change.md`
- Poprzednia zmiana widoku: `context/archive/2026-11-06-sidebar-atrament-ui/plan.md`, ADR-0053
- Równoległa zmiana dodająca „Raporty”: `context/changes/period-report-by-category/plan.md:177-184`
- Wzorzec: artefakt https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, `project/components/Sidebar`, `project/components/CommandPalette`, klasy `.ht-side*`, `.ht-kbd`, `.ht-yseg`, `.ht-palette*` w `project/components/bundle.css` (podgląd, nie źródło wartości)
- Wzorce w kodzie: `BudgetYearSwitcher.vue:51-79` (kolejność warstw, POST roku), `BudgetCategoryList.vue:73-186` (`useHttp`), `lib/dashboard-categories.ts:34-49` (link kategorii), `layouts/settings/Layout.vue:47-49` (`aria-current` ręcznie)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Powłoka bez belki i bez zwijania

#### Automated

- [x] 1.1 Testy JS: `npm test`
- [x] 1.2 Literały: `npm run check:ui`
- [x] 1.3 Typy i build: `npm run build && npm run types:check`
- [x] 1.4 Feature: `php artisan test --compact tests/Feature/AppShellTest.php tests/Feature/SidebarKitchenSinkTest.php`
- [x] 1.5 Brak odwołań: `grep -rn "AppSidebarHeader\|Breadcrumb\|breadcrumbs\|sidebarOpen\|sidebar_state" resources/js app bootstrap tests` zwraca pusto
- [x] 1.6 Indeks reguł aktualny: `php artisan boost:index-rules && git diff --stat .ai/rules/index.md` pokazuje tylko nowe globy `js-sidebar.md`
- [x] 1.7 E2E: `npx playwright test tests/e2e/sidebar-current-section.spec.ts tests/e2e/shell-mobile-menu.spec.ts`

#### Manual

- [x] 1.8 1280 px, oba motywy: Dashboard, Operacje, Plan miesiąca i Ustawienia zaczynają się od wiersza tytułu, bez belki; sidebar nie daje się zwinąć (⌘B nic nie zmienia)
- [x] 1.9 390 px i dokładnie 768 px: pasek z ☰ jest widoczny, arkusz się otwiera i zamyka, nawigacja działa
- [x] 1.10 Stare ciasteczko `sidebar_state=false` w przeglądarce nie zwija sidebara

### Phase 2: Rok budżetu w segmentach

#### Automated

- [ ] 2.1 Testy JS: `npm test`
- [ ] 2.2 Literały: `npm run check:ui`
- [ ] 2.3 Typy i build: `npm run build && npm run types:check`
- [ ] 2.4 Feature: `php artisan test --compact tests/Feature/BudgetTest.php tests/Feature/AppShellTest.php`

#### Manual

- [ ] 2.5 `/dev/ui/sidebar`, oba motywy: segmenty w ramkach `years-*` mieszczą się w 224 px, wybrany rok czytelny, fokus klawiatury widoczny
- [ ] 2.6 Klik innego roku przechodzi na Budżet tego roku jednym kliknięciem; podczas żądania segmenty nie przyjmują kliknięć
- [ ] 2.7 „2027 +” otwiera dialog z rokiem 2027; po utworzeniu segment staje się zwykłym rokiem, a „+” przesuwa się na 2028
- [ ] 2.8 Bez budżetu: jeden segment „<bieżący rok> +”

### Phase 3: Wspólna lista widoków i skróty

#### Automated

- [ ] 3.1 Testy JS (w tym `global-shortcuts.test.ts`): `npm test`
- [ ] 3.2 Literały: `npm run check:ui`
- [ ] 3.3 Typy i build: `npm run build && npm run types:check`
- [ ] 3.4 Feature: `php artisan test --compact tests/Feature/AppShellTest.php`
- [ ] 3.5 E2E: `npx playwright test tests/e2e/global-shortcuts.spec.ts tests/e2e/sidebar-current-section.spec.ts`

#### Manual

- [ ] 3.6 Znaczniki `1`–`6` przy menu i linijka „Skróty: 1–6 …” czytelne w obu motywach, aktywna pozycja z czytelnym znacznikiem
- [ ] 3.7 `N` otwiera menu typów; wyłączone typy (transfer, ruch kapitału) nadal pokazują powód
- [ ] 3.8 Czytnik ekranu (lub drzewo dostępności) podaje link jako „Dashboard”, ze skrótem z `aria-keyshortcuts`

### Phase 4: Paleta ⌘K

#### Automated

- [ ] 4.1 Feature (SQLite): `php artisan test --compact tests/Feature/CommandPaletteCategoriesTest.php tests/Feature/AppShellTest.php`
- [ ] 4.2 Feature (PostgreSQL, worktree): `CommandPaletteCategoriesTest` i `tests/Feature/Nfr` przez obraz `sail-8.5/app` (polecenie „PostgreSQL tests, worktree” z `AGENTS.md`, ścieżki `tests/Feature/CommandPaletteCategoriesTest.php tests/Feature/Nfr`)
- [ ] 4.3 Zależności bez zmian: `git diff --exit-code main -- package.json package-lock.json resources/js/components/ui/dialog`
- [ ] 4.4 Testy JS: `npm test`
- [ ] 4.5 Literały: `npm run check:ui`
- [ ] 4.6 Typy i build: `npm run build && npm run types:check`
- [ ] 4.7 Bramka: `composer ci:check` (w Sail) i `vendor/bin/sloppy ci` (host); `vendor/bin/pint --dirty --format agent`
- [ ] 4.8 E2E: `npx playwright test`

#### Manual

- [ ] 4.9 `/dev/ui/sidebar?palette=<stan>` dla sześciu stanów w obu motywach: kolory sidebara, kwoty wyrównane, szerokość 520 px na desktopie, pełna szerokość z marginesem na 390 px
- [ ] 4.10 Polskie znaki: „zywnosc” znajduje „Żywność”
- [ ] 4.11 Po zapisaniu operacji ponowne otwarcie palety pokazuje nową realizację kategorii
- [ ] 4.12 „Dodaj rok budżetu” z palety otwiera dialog z właściwym rokiem i nie zostawia dwóch warstw
- [ ] 4.13 Klawiatura: Tab nie wychodzi z palety, Esc oddaje fokus elementowi sprzed otwarcia
