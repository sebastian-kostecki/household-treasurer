# Sidebar UI contract pass — Implementation Plan

## Overview

Przeprowadzamy powłokę sidebara (`AppSidebar.vue` i komponenty renderowane tylko przez nią) przez kontrakt 10x-ui. Najpierw integrujemy gałąź z `development` (wzorce zmiany `dashboard-ui-contract`) i budujemy kitchen sink `/dev/ui/sidebar`. Potem czyścimy i dokumentujemy blok tokenów `--sidebar-*`, przebudowujemy przełącznik roku tak, żeby działał w zwiniętym pasie, poprawiamy aktywną pozycję menu, usuwamy literały `dark:` i pozostałości startera. Na koniec wypełniamy macierz 7 stanów i zostawiamy zabezpieczenie: skan `check:ui` i regułę dla agentów. Wejście: `research.md` (`## Charges` C1–C5) i decyzje z sesji planowania.

## Current State Analysis

- **Gałąź jest za `development`.** Worktree odchodzi od `547d3ef`, a na `development` leżą już fazy 1–4 `dashboard-ui-contract` (`e84e65f`, `e4b3733`, `33ab475`, `2c63e29`): wzorzec kitchen sinka (`app/Http/Controllers/Dev/DashboardKitchenSinkController.php`, `tests/Feature/DashboardKitchenSinkTest.php`, trasa `dev.ui.dashboard`), zmiany w `resources/css/app.css` i `.docs/design-system.md` oraz `resources/js/lib/budget-year-selection.ts` (`notifyBudgetYearSelectionFailed`, `listenForBudgetYearSelectionFailure`). Faza 5 dashboardu (`scripts/check-ui-literals.mjs`, `npm run check:ui`) jeszcze nie wylądowała. Gałąź jest wypchnięta (`origin/sidebar-ui-contract`).
- **C1:** `BudgetYearSwitcher.vue:17-89` to `<div>` z natywnym `<select>` i przyciskiem „Dodaj rok”, wstawiony do `SidebarHeader` (`AppSidebar.vue:79`), bez obsługi `group-data-[collapsible=icon]`. W pasie 3rem (`ui/sidebar/utils.ts:8`) select ma 18 px, a „Dodaj rok” wylewa się na treść. Dane bierze z `useShell()`. Wzorzec kontrolki działającej w trybie ikon to `NavUser.vue:24-55`: `SidebarMenuButton size="lg"` + `DropdownMenu` ze stroną zależną od `useSidebar().state`.
- **C2:** `NavMain.vue:28` używa `isCurrentUrl` (równość ścieżek, `useCurrentUrl.ts:46`). `isCurrentOrParentUrl` (`:61-66`) to surowe `startsWith`, więc `/budget` pasowałoby do `/budgets/…`. Podstrony z nawigacji to `operations/import` i `operations/import/preview` (`routes/web.php:50,52`).
- **C3:** `AppLogo.vue:12` (`text-white dark:text-black`) i `UserInfo.vue:27` (`text-black dark:text-white`) nadpisują tokeny. Ciemny `--sidebar-primary-foreground` (biały) na ciemnym `--sidebar-primary` daje ok. 3,3:1.
- **C4:** `--sidebar` (`app.css:139`, `app.css:181`) nie ma czytelnika; `bg-sidebar` idzie przez `--color-sidebar: var(--sidebar-background)` (`app.css:62`). `.docs/design-system.md` nie wymienia tokenów `--sidebar-*`. Tokeny `sidebar-primary(-foreground)` i `bg-sidebar` czytają też `Welcome.vue:107,322` i `LandingAppPreview.vue:42-51`.
- **C5:** `NavFooter.vue` nie ma importerów. `AppLogoIcon.vue:16-28` to znak startera Laravel, renderowany z `fill-current` w `AppLogo.vue:12`, `Welcome.vue:109,324`, `AuthSplitLayout.vue:27`, `AuthSimpleLayout.vue:26`, `AuthCardLayout.vue:29` i `AppHeader.vue:99`.
- **Błąd wyboru roku:** `SelectBudgetYearRequest` waliduje tylko `exists` roku użytkownika, więc błąd walidacji pojawia się wyłącznie, gdy budżet roku zniknął (np. usunięty w innej karcie). Dashboard łapie go przez `onError` (walidacja) oraz `listenForBudgetYearSelectionFailure` (HTTP i sieć) z kluczem `dashboard.select_year_failed`.
- **Layout i stan zwinięcia:** `AppShell.vue:21` przekazuje `sidebarOpen` (cookie `sidebar_state`, `HandleInertiaRequests.php:48`) do `SidebarProvider :default-open`. Resolver w `resources/js/app.ts:12-23` daje stronom `dev/*` domyślny `AppLayout`.
- **Tłumaczenia:** `lang/pl/ui.php` (`shell.*`: `budget_year`, `no_budget_year`, `add_budget_year`, `select_year`) i ręczny typ `shell` w `resources/js/types/shell.ts:31-36`.
- **Testy:** `tests/Feature/AppShellTest.php` pilnuje propsów `shell.*`. Frontend: `vp test`, `*.test.ts` obok kodu, import z `vite-plus/test`, importy względne (alias `@` wyłączony w testach).

## Desired End State

- Przełącznik roku to jeden `SidebarMenuButton` (`size="lg"`) otwierający `DropdownMenu` z listą lat (`DropdownMenuRadioGroup`) i pozycją „Dodaj rok”. Po zwinięciu sidebara zostaje ikona w kafelku 32 px z podpowiedzią „Rok budżetu: 2026”, a menu otwiera się w prawo. Bez lat przycisk pokazuje „Brak roku budżetu / Dodaj rok” i otwiera `CreateBudgetDialog` bezpośrednio. Nic nie wychodzi poza pas 3rem.
- Na `/operations/import` i `/operations/import/preview` aktywna jest pozycja „Operacje”. `/budget` nie zapala się na `/budgets/…`.
- Znak w logo jest biały na turkusie w obu motywach (bez literałów), inicjały awatara biorą kolor z otoczenia. Znak Laravela zastępuje tymczasowy `Vault` z `@lucide/vue`, a `NavFooter.vue` nie istnieje.
- Blok `--sidebar-*` w `app.css` nie ma martwego `--sidebar` i jest opisany w `.docs/design-system.md`.
- Nieudany wybór roku z sidebara pokazuje toast. Podczas POST przełącznik jest wyłączony i ma `aria-busy`.
- `/dev/ui/sidebar` (tylko `local`) pokazuje sidebar w czterech ramkach (rozwinięty/zwinięty × lata/brak lat) i macierz 7 stanów. Zrzuty „przed” i „po” są w folderze zmiany, `states.md` jest wypełniony.
- `npm run check:ui` obejmuje pliki sidebara i jest w `composer ci:check`. `.ai/rules/js.md` ma sekcję o sidebarze.

Weryfikacja: `composer ci:check` przechodzi w obrazie Sail na worktree, skan na plikach widoku daje 0 trafień, a człowiek ogląda kitchen sink i zrzuty.

### Key Discoveries:

- `CreateBudgetDialog.vue` renderuje `DialogTrigger` tylko, gdy podano slot `trigger`, więc da się go otworzyć z pozycji menu przez `v-model:open` bez własnego triggera.
- `SidebarMenuButton` (`ui/sidebar/SidebarMenuButton.vue`) sam ukrywa podpowiedź poza stanem zwiniętym i na mobile; w trybie ikon `size="lg"` dostaje `p-0`, więc ikona musi siedzieć w kafelku `size-8` (jak `AppLogo.vue:10`).
- `Sidebar` na desktopie jest `position: fixed`. Przodek z `transform` (`transform-gpu`) staje się blokiem zawierającym dla potomków `fixed`, więc tak zamykamy kilka sidebarów w ramkach kitchen sinka.
- `@lucide/vue` eksportuje `Vault`; nie jest używany w nawigacji (`AppSidebar.vue:4-9`). Ikony lucide to obrys, więc `fill-current` u wywołujących wypełniłby kształt.
- Wzorzec POST do `BudgetController.selectYear()` przez Wayfinder i Inertia: `DashboardNotices.vue` i `Dashboard.vue` na `development` (faza 4 dashboardu).

## What We're NOT Doing

- **Docelowy znak produktu.** `Vault` jest tymczasowy; prawdziwe logo to osobna zmiana (deferred w `research.md`).
- **Kontrast tekstu na ciemnym `--primary`.** Biały `--primary-foreground` na ciemnym `--primary` (`hsl(189 70% 40%)`) ma ok. 3,3:1 także dla tekstu przycisków w całej aplikacji. To zmiana globalnego tokenu poza powłoką sidebara; zapisujemy jako deferred.
- **Zmiana tokenów `--sidebar-primary(-foreground)`.** Decyzja C3: glif to obiekt graficzny (WCAG 1.4.11, 3:1), więc 3,3:1 wystarcza; landing (`Welcome.vue`, `LandingAppPreview.vue`) zostaje bez zmian.
- **Pozostałe literały na landingu i w layoutach auth** (`text-white` w `Welcome.vue:109,324`, `AuthSplitLayout.vue:27`). Zdejmujemy tam tylko `fill-current`, który psuje nowy znak.
- **Edycja prymitywów `components/ui/sidebar/*` i `components/ui/dropdown-menu/*`.** Dryf jest w komponentach domenowych.
- **`useCurrentUrl` i jego `isCurrentOrParentUrl`.** Zostają jak w starterze; nowa reguła żyje w osobnym helperze.
- **Wybór roku w URL i cel przekierowania.** Zmiana roku z sidebara dalej przekierowuje na Budżet (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:71`).
- **Błąd 500 na `/dashboard` w środowisku dev** (research, Open Questions 5). Kitchen sink i zrzuty nie zależą od Dashboardu.
- **Playwright / `toHaveScreenshot`.** Zrzuty robimy jak w researchu, bez instalowania narzędzia.

## Implementation Approach

Kolejność według routera 10x-ui: środowisko i biblioteka → wartości tokenów → jeden widok → stany → zabezpieczenie. Faza 1 nie zmienia wyglądu: integruje `development`, przenosi dane lat do propsów (żeby kitchen sink mógł pokazać stan pusty obok stanu z latami), dodaje helper aktywnej sekcji i stronę `/dev/ui/sidebar`, na której powstają zrzuty „przed”. Faza 2 dotyka tylko `app.css` i dokumentacji. Faza 3 wymienia widok. Fazy 4 i 5 zaczynają się od ponownego merge'a `development`, żeby przejąć to, co dashboard dowiezie w międzyczasie (zwłaszcza `check:ui`).

## Critical Implementation Details

**Merge, nie rebase.** Gałąź jest na `origin`, a force-push jest zakazany, więc integrujemy przez `git merge development` (fazy 1, 4 i 5).

**Dialog otwierany z menu.** `CreateBudgetDialog` otwierany z `DropdownMenuItem` musi się otworzyć dopiero po zamknięciu menu (np. w `nextTick` po `@select`), inaczej reka zostawia na `body` `pointer-events: none` albo oddaje fokus w złe miejsce. Po zamknięciu dialogu fokus wraca na przycisk przełącznika, a strona jest klikalna.

**Ramki kitchen sinka bez wartości arbitralnych.** Wysokość ramki zapisujemy skalą (`h-160`), a zamknięcie `fixed` przez `transform-gpu` i `overflow-hidden`. Zapis `h-[640px]` łapie wzorzec `-\[[0-9.]+(px|rem)\]` skanu z fazy 5.

## Phase 1: Integracja i kitchen sink

### Overview

Przejmujemy wzorce z `development`, przenosimy dane lat do propsów, dodajemy helper aktywnej sekcji (jeszcze nieużywany) i stronę `/dev/ui/sidebar`. Robimy zrzuty „przed” kitchen sinka. Wygląd aplikacji się nie zmienia.

### Changes Required:

#### 1. Integracja z `development`

**File**: gałąź `sidebar-ui-contract`

**Intent**: przejąć wzorzec kitchen sinka, zmiany `app.css` / `design-system.md` i `lib/budget-year-selection.ts`, zanim cokolwiek zmienimy w tych plikach.

**Contract**: `git merge development` bez konfliktów (gałąź zmieniała dotąd tylko `context/changes/sidebar-ui-contract/*`), potem kroki ze „Fresh checkout or worktree” (`AGENTS.md`).

#### 2. Dane lat jako propsy

**File**: `resources/js/layouts/app/AppSidebarLayout.vue`, `resources/js/components/AppSidebar.vue`, `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: kitchen sink musi pokazać obok siebie sidebar z latami i bez lat, czego nie da się zrobić, gdy przełącznik czyta globalne `shell`.

**Contract**: `AppSidebar` przyjmuje `budgetYears: number[]` i `budgetYear: number | null` i przekazuje je do `BudgetYearSwitcher` (te same nazwy propsów). `AppSidebarLayout` podaje je z `useShell()`. `CreateBudgetDialog` dalej czyta `shell` (domyślny rok, źródła kopiowania). Propsy Inertia bez zmian.

#### 3. Helper aktywnej sekcji

**File**: `resources/js/lib/nav-active.ts` (nowy), `resources/js/lib/nav-active.test.ts` (nowy)

**Intent**: jedna reguła „pozycja menu jest aktywna na swojej ścieżce i jej podstronach” (C2), dopasowana po segmencie, nie po surowym prefiksie.

**Contract**: `isCurrentSection(href: string, currentPath: string): boolean`. Czysta funkcja bez importów `@/`. `href` może być absolutny (bierze `pathname`). Prawda, gdy `currentPath === path` albo `currentPath` zaczyna się od `path + '/'`; końcowy `/` w obu jest ignorowany. Test: `/operations` vs `/operations/import` i `/operations/import/preview` → true; `/budget` vs `/budgets/1` → false; `/budget` vs `/budget` → true; `/operations` vs `/operations/` → true; absolutny `http://localhost/accounts` vs `/accounts` → true; `/dashboard` vs `/` → false.

#### 4. Kontroler i trasa kitchen sinka

**File**: `app/Http/Controllers/Dev/SidebarKitchenSinkController.php` (nowy), `routes/web.php`, `tests/Feature/SidebarKitchenSinkTest.php` (nowy)

**Intent**: strona dostępna tylko lokalnie, za logowaniem (wspólne propsy `shell`, `auth`, `translations`), poza `local` 404. Ten sam wzorzec co `DashboardKitchenSinkController`.

**Contract**: `GET dev/ui/sidebar`, nazwa `dev.ui.sidebar`, w grupie `auth` + `verified` obok `dev.ui.dashboard`. Kontroler: `abort_unless(app()->isLocal(), 404)` i `Inertia::render('dev/SidebarKitchenSink')` bez propsów. Test: gość → logowanie; zalogowany w `testing` → 404; przy `$this->app['env'] = 'local'` → komponent `dev/SidebarKitchenSink`.

#### 5. Strona kitchen sinka

**File**: `resources/js/pages/dev/SidebarKitchenSink.vue` (nowy)

**Intent**: wszystkie stany przełącznika i powłoki naraz, jako bramka wizualna i dowód do przeglądu.

**Contract**: strona w domyślnym `AppLayout` (prawdziwy sidebar zostaje jako odniesienie). Nagłówek mówi, że elementy zależne od zalogowanego użytkownika (`NavUser`, źródła kopiowania w dialogu) są prawdziwe. Siatka 2×2 ramek; każda ramka to kontener `relative h-160 overflow-hidden transform-gpu` z ramką `border`, a w nim `SidebarProvider :default-open` + `AppSidebar` z fixture'ami:
- `expanded-years`: `default-open=true`, lata `[2025, 2026]`, wybrany 2026;
- `collapsed-years`: `default-open=false`, te same lata;
- `expanded-empty`: `default-open=true`, `[]`, `null`;
- `collapsed-empty`: `default-open=false`, `[]`, `null`.

Sink działa tylko na desktopie (na 390 px `useSidebar` przełącza każdą ramkę na Sheet); mobile dokumentujemy zrzutami z aplikacji.

#### 6. Zrzuty „przed” kitchen sinka

**File**: `context/changes/sidebar-ui-contract/screenshots/before/`

**Intent**: punkt odniesienia dla ramek, zanim zmieni się widok (zrzuty aplikacji z researchu już tam są).

**Contract**: `sink-desktop-light.png`, `sink-desktop-dark.png` (1440×900), z podglądu worktree (`AGENTS.md`, „Preview from a worktree”).

### Success Criteria:

#### Automated Verification:

- Merge `development` jest w historii gałęzi: `git merge-base --is-ancestor development HEAD`
- Test helpera aktywnej sekcji przechodzi: `npm test`
- Lint i format przechodzą: `npm run check`
- Typy przechodzą po przebudowie Wayfindera: `npm run build && npm run types:check`
- Testy kitchen sinka i powłoki przechodzą w obrazie Sail na worktree (`AGENTS.md`, „Test database”): `tests/Feature/SidebarKitchenSinkTest.php tests/Feature/AppShellTest.php`
- Pint i Sloppy przechodzą: `vendor/bin/pint --dirty --format agent`, `vendor/bin/sloppy ci`

#### Manual Verification:

- `/dev/ui/sidebar` pokazuje cztery ramki z nagłówkami, każda z własnym sidebarem w granicach ramki
- Sidebar w aplikacji wygląda tak samo jak na zrzutach `before/` z researchu
- Zrzuty `sink-desktop-{light,dark}.png` są w `screenshots/before/`

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 2: Wartości tokenów (C4)

### Overview

Usuwamy martwy token i zapisujemy kontrakt `--sidebar-*` w repo. Wygląd się nie zmienia.

### Changes Required:

#### 1. Blok tokenów sidebara

**File**: `resources/css/app.css`

**Intent**: kolejna osoba edytująca „tło sidebara” ma trafić w token, który coś zmienia.

**Contract**: usunięte `--sidebar` w `:root` i `.dark`. Przy bloku `--sidebar-*` (oba motywy) i przy publikacji `--color-sidebar*` w `@theme inline` krótki komentarz: tło to `--sidebar-background`, opis ról w `.docs/design-system.md`. Wartości pozostałych tokenów bez zmian.

#### 2. Notatki systemu projektowego

**File**: `.docs/design-system.md`

**Intent**: zapisać kontrakt sidebara, którego dziś nie ma w dokumentacji (`.ai/rules/css.md` tego wymaga).

**Contract**: nowa sekcja „Sidebar”:
- tabela ról: `sidebar` (tło, z `--sidebar-background`), `sidebar-foreground`, `sidebar-primary` / `-foreground` (kafelek logo; czytają je też `Welcome.vue` i `LandingAppPreview.vue`), `sidebar-accent` / `-foreground` (hover, aktywna pozycja, otwarte menu, kafelek przełącznika roku), `sidebar-border`, `sidebar-ring` (fokus);
- glif na `sidebar-primary` jest jasny w obu motywach; w ciemnym ma ok. 3,3:1, co wystarcza dla grafiki (WCAG 1.4.11), więc na `sidebar-primary` w trybie ciemnym nie kładziemy tekstu;
- wszystko w slotach `SidebarHeader` / `SidebarFooter` budujemy z `SidebarMenuButton` (albo obsługujemy `group-data-[collapsible=icon]`), żeby działało w pasie 3rem;
- aktywna pozycja menu: `isCurrentSection` (ścieżka i jej podstrony).

### Success Criteria:

#### Automated Verification:

- Martwy token zniknął: `grep -nE '^\s*--sidebar:' resources/css/app.css` nic nie zwraca
- Nic nie czyta `var(--sidebar)`: `grep -rn 'var(--sidebar)' resources` nic nie zwraca
- Lint, format i sortowanie klas przechodzą: `npm run check`
- Build CSS przechodzi: `npm run build`

#### Manual Verification:

- Sidebar i landing (`/`) wyglądają tak samo jak przed fazą, w obu motywach
- `design-system.md` ma sekcję „Sidebar” z rolami tokenów, uwagą o kontraście glifu i zasadą trybu ikon

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 3: Jeden widok (C1, C2, C3, C5)

### Overview

Wymieniamy przełącznik roku, podpinamy helper aktywnej sekcji, usuwamy literały i pozostałości startera.

### Changes Required:

#### 1. Przełącznik roku (C1)

**File**: `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: jedna kontrolka, która działa w rozwiniętym sidebarze, w pasie ikon i w Sheet na mobile (US-10 „at all times”, US-11 stan pusty).

**Contract**:
- Struktura jak `NavUser.vue`: `SidebarMenu` → `SidebarMenuItem` → `DropdownMenu` → `DropdownMenuTrigger as-child` → `SidebarMenuButton size="lg"` z `tooltip`.
- Zawartość przycisku: kafelek `size-8 rounded-md` w tokenach `sidebar-accent` z ikoną `CalendarDays` (`size-4`); dwie linie tekstu: podpis `t.shell.budget_year` (`text-xs text-muted-foreground`) i rok (`font-semibold tabular-nums`); `ChevronsUpDown` z `ml-auto`. Podpowiedź: `` `${t.shell.budget_year}: ${budgetYear}` ``.
- Menu: `side` = `'right'` w stanie zwiniętym poza mobile, inaczej `'bottom'`; `DropdownMenuLabel` `t.shell.select_year`; `DropdownMenuRadioGroup` z latami (`tabular-nums`); `DropdownMenuSeparator`; `DropdownMenuItem` z `CalendarPlus` i `t.shell.add_budget_year`, który otwiera `CreateBudgetDialog` (`year-input-id="shell-create-budget-year"`, bez slotu `trigger`).
- Wybór innego roku wysyła POST `BudgetController.selectYear()` z `{ year }` przez Inertia (bez `destination`, więc przekierowanie na Budżet jak dziś). Wybór bieżącego roku nie wysyła żądania. Stan `pending` jest wystawiony do użycia w fazie 4.
- Bez lat: bez menu. `SidebarMenuButton size="lg"` z kafelkiem `CalendarPlus`, podpisem `t.shell.no_budget_year` i etykietą `t.shell.add_budget_year`, podpowiedź `t.shell.add_budget_year`; klik otwiera `CreateBudgetDialog` (`year-input-id="shell-create-budget-year-empty"`).
- Usunięte: natywny `<select id="shell-budget-year">`, `<Form>`, `Label`, przerywana ramka.

#### 2. Aktywna pozycja menu (C2)

**File**: `resources/js/components/NavMain.vue`

**Intent**: „Operacje” zapalone podczas importu CSV.

**Contract**: `:is-active="isCurrentSection(toUrl(item.href), currentUrl)"`, z `currentUrl` z `useCurrentUrl()` i `toUrl` z `@/lib/utils`.

#### 3. Literały w logo i awatarze (C3)

**File**: `resources/js/components/AppLogo.vue`, `resources/js/components/UserInfo.vue`

**Intent**: kolor z tokenów w obu motywach, bez `dark:`.

**Contract**: `AppLogo.vue:12`: glif bez `fill-current text-white dark:text-black`, dziedziczy `text-sidebar-primary-foreground` z kafelka (`:10`). `UserInfo.vue:27`: `AvatarFallback` bez `text-black dark:text-white`, kolor dziedziczony (`sidebar-foreground` w sidebarze, `popover-foreground` w menu).

#### 4. Znak i pozostałości startera (C5)

**File**: `resources/js/components/AppLogoIcon.vue`, `resources/js/components/NavFooter.vue` (usunięty), wywołujący `AppLogoIcon`: `Welcome.vue:109,324`, `layouts/auth/AuthSplitLayout.vue:27`, `layouts/auth/AuthSimpleLayout.vue:26`, `layouts/auth/AuthCardLayout.vue:29`, `components/AppHeader.vue:99`

**Intent**: produkt nie pokazuje znaku Laravela; osierocony komponent z paletą nie jest wzorcem do kopiowania.

**Contract**: `AppLogoIcon` renderuje `Vault` z `@lucide/vue` i zachowuje dzisiejsze API (prop `className`, `inheritAttrs: false`, `v-bind="$attrs"`). Każdy wywołujący traci `fill-current` (pozostałe klasy bez zmian). `NavFooter.vue` usunięty.

#### 5. Zrzuty i skan po fazie

**File**: `context/changes/sidebar-ui-contract/screenshots/after-phase-3/`

**Intent**: porównanie z „przed” (router 10x-ui, krok 4).

**Contract**: te same nazwy co w `before/` (desktop rozwinięty i zwinięty w obu motywach, stan pusty rozwinięty i zwinięty, `/operations/import`, mobile Sheet w obu motywach, kitchen sink w obu motywach).

### Success Criteria:

#### Automated Verification:

- Lint i format przechodzą: `npm run check`
- Typy przechodzą: `npm run types:check`
- Testy frontendu przechodzą: `npm test`
- Testy powłoki i kitchen sinka przechodzą w obrazie Sail: `tests/Feature/AppShellTest.php tests/Feature/SidebarKitchenSinkTest.php tests/Feature/BudgetTest.php`
- Skan wartości zakodowanych na sztywno (wzorzec 10x-ui) daje 0 trafień w: `AppSidebar.vue`, `BudgetYearSwitcher.vue`, `NavMain.vue`, `NavUser.vue`, `UserInfo.vue`, `UserMenuContent.vue`, `AppLogo.vue`, `AppLogoIcon.vue`, `AppSidebarHeader.vue`, `layouts/app/AppSidebarLayout.vue`, `pages/dev/SidebarKitchenSink.vue`
- `NavFooter.vue` nie istnieje, a `grep -rn "NavFooter" resources/js` nic nie zwraca
- Nikt nie wypełnia znaku: `grep -rn "AppLogoIcon" -A2 resources/js | grep fill-current` nic nie zwraca

#### Manual Verification:

- W zwiniętym sidebarze (ramki `collapsed-*` i aplikacja) przełącznik to kafelek 32 px w pasie, z podpowiedzią; nic nie wychodzi na treść
- Z paska ikon menu otwiera się w prawo; wybór roku przełącza rok i przechodzi na Budżet; „Dodaj rok” otwiera dialog, a po jego zamknięciu fokus wraca na przełącznik i strona jest klikalna
- Stan bez lat (rozwinięty i zwinięty) pokazuje „Brak roku budżetu / Dodaj rok” i otwiera dialog jednym kliknięciem
- Przy 390 px przełącznik w Sheet działa jak w rozwiniętym sidebarze
- Na `/operations/import` i `/operations/import/preview` aktywna jest „Operacje”; na `/budget` tylko „Budżet”
- W trybie ciemnym glif logo jest jasny na turkusie; inicjały awatara są czytelne w obu motywach i w menu użytkownika
- Znak `Vault` widać w sidebarze, na ekranach logowania, w `AppHeader` i na landingu, bez wypełnienia
- Zrzuty `after-phase-3` porównane z `before`; każda różnica zamierzona i opisana w commicie

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 4: Stany

### Overview

Wypełniamy macierz 7 stanów: komunikat błędu wyboru roku, wygląd `processing` i pokazanie każdego stanu w kitchen sinku albo na zrzutach z aplikacji.

### Changes Required:

#### 1. Integracja z `development`

**File**: gałąź `sidebar-ui-contract`

**Intent**: przejąć zmiany dashboardu, które wylądowały od fazy 1.

**Contract**: `git merge development`; konflikty w `app.css` / `design-system.md` rozwiązane z zachowaniem obu zmian.

#### 2. Błąd i `processing` przełącznika

**File**: `resources/js/components/BudgetYearSwitcher.vue`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: nieudany wybór roku nie zostawia użytkownika bez słowa; podczas POST przełącznik wygląda na zajęty.

**Contract**: błąd walidacji (`onError` wizyty) wywołuje `notifyBudgetYearSelectionFailed(t.shell.select_year_failed)`; błędy HTTP i sieci przez `listenForBudgetYearSelectionFailure(() => pending, t.shell.select_year_failed)`, odpięte w `onUnmounted` (wzorzec `Dashboard.vue` na `development`). Nowy klucz `shell.select_year_failed` = „Nie udało się otworzyć wybranego roku. Odśwież stronę i spróbuj ponownie.” w `lang/pl/ui.php` i w typie `shell`. Podczas `pending` przycisk ma `disabled` i `aria-busy="true"`.

#### 3. Macierz stanów w kitchen sinku

**File**: `resources/js/pages/dev/SidebarKitchenSink.vue`

**Intent**: każda komórka macierzy pokazana albo N/A z powodem.

**Contract**: na górze tabela stan → gdzie widać (wzorzec `DashboardKitchenSink.vue`): default i empty → ramki; error → przycisk „Pokaż błąd wyboru roku” wywołujący `notifyBudgetYearSelectionFailed(t.shell.select_year_failed)`; hover, focus-visible, disabled → zrzuty z aplikacji; loading → N/A (powłoka nie ma deferred props; jedyne oczekiwanie to `pending`, pokazane jako disabled).

#### 4. Zrzuty i `states.md`

**File**: `context/changes/sidebar-ui-contract/screenshots/after-phase-4/`, `context/changes/sidebar-ui-contract/states.md` (nowy)

**Intent**: dowód bramki do przeglądu.

**Contract**: `states.md` to tabela 7 stanów (stan, gdzie pokazany, plik zrzutu albo N/A z powodem). Zrzuty desktop (1440 px) i 390 px w obu motywach: hover pozycji menu i przełącznika, focus-visible na przełączniku (Tab), disabled przy spowolnionej sieci, toast błędu, otwarte menu lat w stanie rozwiniętym i zwiniętym.

### Success Criteria:

#### Automated Verification:

- Lint i format przechodzą: `npm run check`
- Typy przechodzą: `npm run types:check`
- Testy frontendu przechodzą: `npm test`
- Testy powłoki i kitchen sinka przechodzą w obrazie Sail: `tests/Feature/AppShellTest.php tests/Feature/SidebarKitchenSinkTest.php`
- Skan wartości zakodowanych na sztywno nadal daje 0 trafień w plikach widoku z fazy 3

#### Manual Verification:

- Wybór roku, którego budżet usunięto w drugiej karcie, pokazuje toast z komunikatem; udany wybór działa jak dotąd
- Podczas POST przełącznik jest wyłączony i czytelny w obu motywach
- Fokus klawiatury na przełączniku ma pierścień `sidebar-ring`, tak jak pozycje menu
- `states.md` ma wszystkie 7 wierszy, każdy ze zrzutem albo z N/A i powodem
- Zrzuty `after-phase-4` są dla desktopu i 390 px, w jasnym i ciemnym motywie

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 5: Zabezpieczenie

### Overview

Zostawiamy kontrolę i regułę, które utrzymają kolejnego agenta przy kontrakcie sidebara, aktualizujemy dokumenty i zapisujemy odroczone zarzuty.

### Changes Required:

#### 1. Integracja i skan `check:ui`

**File**: gałąź `sidebar-ui-contract`, `scripts/check-ui-literals.mjs`, `package.json`, `composer.json`

**Intent**: nieudana kontrola zatrzymuje literał w plikach sidebara, zanim przejdzie przez CI.

**Contract**: najpierw `git merge development`. Jeśli skrypt już istnieje (faza 5 dashboardu), dopisujemy do jego listy pliki widoku z fazy 3. Jeśli go nie ma, tworzymy go dokładnie według kontraktu z `context/changes/dashboard-ui-contract/plan.md` (faza 5: Node bez zależności, wzorzec 10x-ui, wskazówka o tokenach, kod wyjścia 1 przy trafieniu, argumenty CLI zastępują listę; `"check:ui": "node scripts/check-ui-literals.mjs"`; `ci:check` wywołuje go zaraz po `npm run check`) z listą obejmującą pliki dashboardu z jego planu i pliki sidebara. Przy późniejszym merge'u wygrywa suma list.

#### 2. Reguła dla agentów

**File**: `.ai/rules/js.md` (Boost `record-rule` albo edycja pliku)

**Intent**: kolejny agent wie, jak dokładać elementy do sidebara.

**Contract**: krótka sekcja „Sidebar” w stylu pliku: tokeny `--sidebar-*` (role w `.docs/design-system.md`), sloty `SidebarHeader` / `SidebarFooter` tylko z `SidebarMenuButton` (albo z obsługą `group-data-[collapsible=icon]`), aktywna pozycja przez `isCurrentSection`, bez `dark:` i literałów (`npm run check:ui`), kitchen sink `/dev/ui/sidebar` do sprawdzenia trybu ikon.

#### 3. Dokumenty i odroczone zarzuty

**File**: `.docs/specs/app-shell-openship-visual-language.md`, `.ai/skills/openship-ui-development/SKILL.md`, `.docs/design-system.md`, `context/changes/sidebar-ui-contract/research.md`

**Intent**: zamknąć pętlę dokumentacji i nie zgubić zarzutów, których plan nie rozwiązuje.

**Contract**:
- spec, punkt „Budget year switcher” (`:60`): forma kontrolki (przycisk z menu lat i „Dodaj rok”, w zwiniętym pasie ikona z podpowiedzią); punkt „Shell” (`:59`): aktualna lista nawigacji (Dashboard, Budżet, Plan miesiąca, Konta, Lokaty, Operacje; Ustawienia w menu użytkownika);
- `SKILL.md:55`: wskazanie na sekcję „Sidebar” w `.ai/rules/js.md`;
- `design-system.md`, „Zmiana systemu”: kitchen sink `/dev/ui/sidebar`;
- `research.md`, `## Charges`: przy C5 **deferred** docelowy znak produktu (w tej zmianie `Vault`); nowy wpis **deferred**: kontrast tekstu na ciemnym `--primary` (ok. 3,3:1 na przyciskach w całej aplikacji, zmiana globalnego tokenu).

### Success Criteria:

#### Automated Verification:

- Kontrola przechodzi na oczyszczonych plikach: `npm run check:ui`
- Kontrola zatrzymuje literał: `node scripts/check-ui-literals.mjs resources/js/pages/Welcome.vue` kończy się kodem 1 i wskazuje `text-white`
- Lint i format przechodzą: `npm run check`
- Pełna bramka CI przechodzi w obrazie Sail na worktree: `composer ci:check`
- Sloppy przechodzi: `vendor/bin/sloppy ci`

#### Manual Verification:

- `.ai/rules/js.md` ma sekcję „Sidebar”, a `.ai/rules/index.md` nadal wskazuje ten plik dla `resources/js/**/*.vue`
- Spec, `SKILL.md` i `design-system.md` opisują nową kontrolkę roku i kitchen sink
- `research.md` oznacza docelowy znak i kontrast ciemnego `--primary` jako deferred z powodem
- Przegląd z `ui-quality-checklist.md` (10x-ui): wszystkie pozycje zaznaczone albo uzasadnione

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka. Potem `/10x-impl-review sidebar-ui-contract`; ustaleń UI nie pomija się jako „kosmetycznych”.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/nav-active.test.ts`: podstrona po segmencie (`/operations/import`, `/operations/import/preview`), brak fałszywego prefiksu (`/budget` vs `/budgets/1`), równość, końcowy `/`, absolutny `href`, `/dashboard` vs `/`.

### Integration Tests:

- `tests/Feature/SidebarKitchenSinkTest.php`: gość → logowanie; zalogowany w `testing` → 404; przy `local` → komponent `dev/SidebarKitchenSink`.
- `tests/Feature/AppShellTest.php` bez zmian: propsy `shell.*` się nie zmieniają.
- `tests/Feature/BudgetTest.php` bez zmian: `budget.select-year` zachowuje się jak dotąd.

### Manual Testing Steps:

1. `/dev/ui/sidebar` przy 1440 px w obu motywach: cztery ramki, menu lat w ramkach rozwiniętych i zwiniętych.
2. W aplikacji zwinąć sidebar (`Ctrl+B`), zmienić rok z paska ikon, dodać rok z menu, sprawdzić powrót fokusu.
3. Przy 390 px otworzyć Sheet, zmienić rok, dodać rok.
4. Przejść `/operations` → „Importuj CSV” → podgląd; „Operacje” cały czas aktywne.
5. Usunąć budżet w drugiej karcie, potem wybrać ten rok w pierwszej: toast.
6. Tab od początku strony do przełącznika i pozycji menu: pierścień fokusu w obu trybach sidebara.

## Performance Considerations

Bez wpływu: zmiany są w markupie i klasach. Kitchen sink renderuje cztery sidebary, tylko w `local`.

## Migration Notes

Brak migracji danych i zmian propsów Inertia. Id `shell-budget-year` znika (nie odwołuje się do niego żaden test). Trasa `dev/ui/sidebar` zwraca 404 poza `local`.

## References

- Research (zarzuty C1–C5, zrzuty „przed”): `context/changes/sidebar-ui-contract/research.md`
- Identyfikator zmiany: `context/changes/sidebar-ui-contract/change.md`
- Równoległa zmiana i kontrakt `check:ui`: `context/changes/dashboard-ui-contract/plan.md` (główny checkout), commity `e84e65f`, `e4b3733`, `33ab475`, `2c63e29` na `development`
- Kontrakt 10x-ui i lista kontrolna: `.claude/skills/10x-ui/SKILL.md`, `.claude/skills/10x-ui/references/ui-quality-checklist.md`
- System projektowy: `.docs/design-system.md`, `resources/css/app.css:62-69,131-139,173-181`
- Reguły agentów: `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/rules/index.md`, `.ai/skills/openship-ui-development/SKILL.md:55`
- Wzorzec kontrolki w trybie ikon: `resources/js/components/NavUser.vue:24-55`
- Wzorzec kitchen sinka: `app/Http/Controllers/Dev/DashboardKitchenSinkController.php`, `resources/js/pages/dev/DashboardKitchenSink.vue` (na `development`)
- Wzorzec błędu wyboru roku: `resources/js/lib/budget-year-selection.ts`, `resources/js/pages/Dashboard.vue` (na `development`)
- Spec powłoki: `.docs/specs/app-shell-openship-visual-language.md:24-25,59-60`; ADR-0001, ADR-0031

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Integracja i kitchen sink

#### Automated

- [x] 1.1 Merge `development` jest w historii gałęzi — cc5a14b
- [x] 1.2 Test helpera aktywnej sekcji przechodzi: `npm test` — cc5a14b
- [x] 1.3 Lint i format przechodzą: `npm run check` — cc5a14b
- [x] 1.4 Typy przechodzą po przebudowie Wayfindera: `npm run build && npm run types:check` — cc5a14b
- [x] 1.5 Testy kitchen sinka i powłoki przechodzą w obrazie Sail na worktree — cc5a14b
- [x] 1.6 Pint i Sloppy przechodzą — cc5a14b

#### Manual

- [x] 1.7 `/dev/ui/sidebar` pokazuje cztery ramki z nagłówkami, każda z własnym sidebarem w granicach ramki — cc5a14b
- [x] 1.8 Sidebar w aplikacji wygląda tak samo jak na zrzutach `before/` z researchu — cc5a14b
- [x] 1.9 Zrzuty `sink-desktop-{light,dark}.png` są w `screenshots/before/` — cc5a14b

### Phase 2: Wartości tokenów (C4)

#### Automated

- [x] 2.1 Martwy token `--sidebar` zniknął z `app.css` — 21accb5
- [x] 2.2 Nic nie czyta `var(--sidebar)` — 21accb5
- [x] 2.3 Lint, format i sortowanie klas przechodzą: `npm run check` — 21accb5
- [x] 2.4 Build CSS przechodzi: `npm run build` — 21accb5

#### Manual

- [x] 2.5 Sidebar i landing wyglądają tak samo jak przed fazą, w obu motywach — 21accb5
- [x] 2.6 `design-system.md` ma sekcję „Sidebar” z rolami tokenów, uwagą o kontraście glifu i zasadą trybu ikon — 21accb5

### Phase 3: Jeden widok (C1, C2, C3, C5)

#### Automated

- [x] 3.1 Lint i format przechodzą: `npm run check` — 14f46dc
- [x] 3.2 Typy przechodzą: `npm run types:check` — 14f46dc
- [x] 3.3 Testy frontendu przechodzą: `npm test` — 14f46dc
- [x] 3.4 Testy powłoki, kitchen sinka i budżetu przechodzą w obrazie Sail — 14f46dc
- [x] 3.5 Skan wartości zakodowanych na sztywno daje 0 trafień w plikach widoku — 14f46dc
- [x] 3.6 `NavFooter.vue` nie istnieje i nic go nie importuje — 14f46dc
- [x] 3.7 Żaden wywołujący `AppLogoIcon` nie ma `fill-current` — 14f46dc

#### Manual

- [x] 3.8 W zwiniętym sidebarze przełącznik to kafelek 32 px z podpowiedzią; nic nie wychodzi na treść — 14f46dc
- [x] 3.9 Z paska ikon menu otwiera się w prawo; wybór roku i „Dodaj rok” działają, fokus wraca na przełącznik — 14f46dc
- [x] 3.10 Stan bez lat (rozwinięty i zwinięty) otwiera dialog jednym kliknięciem — 14f46dc
- [x] 3.11 Przy 390 px przełącznik w Sheet działa jak w rozwiniętym sidebarze — 14f46dc
- [x] 3.12 Na podstronach importu aktywna jest „Operacje”; na `/budget` tylko „Budżet” — 14f46dc
- [x] 3.13 W trybie ciemnym glif logo jest jasny; inicjały awatara czytelne w obu motywach — 14f46dc
- [x] 3.14 Znak `Vault` widać we wszystkich miejscach użycia, bez wypełnienia — 14f46dc
- [x] 3.15 Zrzuty `after-phase-3` porównane z `before`; każda różnica zamierzona i opisana w commicie — 14f46dc

### Phase 4: Stany

#### Automated

- [x] 4.1 Lint i format przechodzą: `npm run check` — 741dd2d
- [x] 4.2 Typy przechodzą: `npm run types:check` — 741dd2d
- [x] 4.3 Testy frontendu przechodzą: `npm test` — 741dd2d
- [x] 4.4 Testy powłoki i kitchen sinka przechodzą w obrazie Sail — 741dd2d
- [x] 4.5 Skan wartości zakodowanych na sztywno nadal daje 0 trafień w plikach widoku — 741dd2d

#### Manual

- [x] 4.6 Wybór usuniętego roku pokazuje toast; udany wybór działa jak dotąd — 741dd2d
- [x] 4.7 Podczas POST przełącznik jest wyłączony i czytelny w obu motywach — 741dd2d
- [x] 4.8 Fokus klawiatury na przełączniku ma pierścień `sidebar-ring` — 741dd2d
- [x] 4.9 `states.md` ma wszystkie 7 wierszy, każdy ze zrzutem albo z N/A i powodem — 741dd2d
- [x] 4.10 Zrzuty `after-phase-4` są dla desktopu i 390 px, w jasnym i ciemnym motywie — 741dd2d

### Phase 5: Zabezpieczenie

#### Automated

- [x] 5.1 Kontrola przechodzi na oczyszczonych plikach: `npm run check:ui`
- [x] 5.2 Kontrola zatrzymuje literał w `Welcome.vue` (kod 1, `text-white`)
- [x] 5.3 Lint i format przechodzą: `npm run check`
- [x] 5.4 Pełna bramka CI przechodzi w obrazie Sail na worktree: `composer ci:check`
- [x] 5.5 Sloppy przechodzi: `vendor/bin/sloppy ci`

#### Manual

- [x] 5.6 `.ai/rules/js.md` ma sekcję „Sidebar”, a `.ai/rules/index.md` nadal wskazuje ten plik
- [x] 5.7 Spec, `SKILL.md` i `design-system.md` opisują nową kontrolkę roku i kitchen sink
- [x] 5.8 `research.md` oznacza docelowy znak i kontrast ciemnego `--primary` jako deferred z powodem
- [x] 5.9 Przegląd z `ui-quality-checklist.md`: wszystkie pozycje zaznaczone albo uzasadnione
