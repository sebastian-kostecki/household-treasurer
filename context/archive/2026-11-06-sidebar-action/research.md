---
date: 2026-11-06T23:11:00+01:00
researcher: Claude (Opus 5.5)
git_commit: a511e254fd93c6d8af89422273c381aa5c6c0c50
branch: worktree-sidebar-action
repository: household-treasurer
topic: "Powłoka bez górnej belki i funkcjonalny sidebar jak w mocku: paleta ⌘K, skróty, rok w segmentach"
tags: [research, ui, sidebar, command-palette, shortcuts, budget-year, shell, inertia-shared-props]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude (Opus 5.5)
---

# Research: powłoka bez górnej belki i funkcjonalny sidebar jak w mocku

**Date**: 2026-11-06T23:11:00+01:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: a511e254fd93c6d8af89422273c381aa5c6c0c50
**Branch**: worktree-sidebar-action
**Repository**: household-treasurer

Źródła zewnętrzne: komponenty `Sidebar`, `CommandPalette` i strona `PageDashboard` artefaktu https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (wersja 1793965592-5e9c; podgląd, nie źródło wartości) oraz rejestr shadcn-vue `https://shadcn-vue.com/r/styles/new-york-v4/command.json` (pobrany 2026-11-06). Pakiety npm czytane z `node_modules` głównego checkoutu (worktree nie ma `node_modules`). Nie uruchamiano testów ani buildów.

## Research Question

Co trzeba wiedzieć, żeby zaplanować zmianę `sidebar-action` (`change.md`): usunięcie górnej belki z tytułem z każdej strony, sidebar bez przełącznika na desktopie przy zachowaniu wejścia na telefonie, paleta „Szukaj i przejdź ⌘K” z grupami „Widoki i akcje” i „Kategorie: operacje”, skróty `1`–`6`/`N`/`/`/`Esc` z podpowiedzią oraz rok budżetu w segmentach — i jakie kontrakty, testy i decyzje (ADR-0053) to dotyka.

## Summary

- **Belka** to jeden komponent `AppSidebarHeader` (`layouts/app/AppSidebarLayout.vue:28`), zasilany przez `setLayoutProps({ breadcrumbs })` w 11 stronach. Każda strona aplikacji (8 stron domenowych + `layouts/settings/Layout.vue:34` dla 3 stron ustawień) ma własne `h1.page-title`, więc po usunięciu belki żaden widok nie traci tytułu. Razem z belką bez konsumentów zostają `Breadcrumbs.vue`, `components/ui/breadcrumb/*` i typ `BreadcrumbItem`.
- **Przełącznik**: `collapsible="none"` nie nadaje się do „bez zwijania”, bo `ui/sidebar/Sidebar.vue:24-31` sprawdza `none` przed `isMobile` i nie renderuje arkusza na telefonie. Telefon otwiera arkusz dziś wyłącznie przez `SidebarTrigger` w belce albo Ctrl/⌘+B (`SidebarRail` nie ma konsumentów). Plan musi zachować `offcanvas`/`icon` z wymuszonym `open` na desktopie i dać telefonowi własne wejście ☰ — albo zmienić prymityw.
- **Paleta**: brak `components/ui/command`; shadcn-vue `command` (new-york-v4) stoi na reka `Listbox*` + `useFilter`, a jego zależności (`reka-ui` 2.11.0, `@vueuse/core` 12.8.2, `@lucide/vue`) są już zainstalowane, więc nowych pakietów nie potrzeba. CLI ciągnie jednak `dialog` jako zależność rejestru, a lokalny `ui/dialog` jest dostosowany do Atramentu (`DialogContent.vue:37`, `DialogOverlay.vue:17`) i nie może zostać nadpisany. `ListboxFilter` nie daje `role="combobox"`/`aria-expanded`/`aria-controls`, `ComboboxInput` daje.
- **Kategorie z realizacją**: kwota z mocka („Dom 29 880,00 zł”) to realizacja roczna. Jest przechowywana w `categories.execution_minor_units` (aktualizowana w `ManageFinancialOperation.php:226-244`), więc odczyt to jeden `SELECT` na `categories` jednego budżetu, niezależny od liczby operacji. Inertia 3.7.0 obsługuje `optional`/`defer`/`once` także we współdzielonych propsach; dziś aplikacja żadnego z nich nie używa. Link do Operacji ma gotowy wzorzec `categoryOperationsQuery` (`lib/dashboard-categories.ts:34-49`).
- **Skróty**: poza `ui/` nie ma globalnej obsługi klawiszy ani helpera „fokus w polu”. `N` z innej strony nie otworzy dialogu operacji bez nowego kontraktu: dialogi i ich propsy (`accounts`, kategorie, `termDeposits`, odczyty miesiąca) są lokalne w `pages/Operations.vue` i nie są współdzielone; żadna strona aplikacji nie otwiera dialogu z adresu. `/` wymaga `id` na szukajce (`OperationsFilters.vue:393-400`), której nie ma przy pustej liście (`OperationsListPanel.vue:207`).
- **Rok w segmentach**: `shell.budgetYears` to tylko lata z budżetem, rosnąco (`app/Support/ShellState.php:26-47`); segment „+” trzeba wyliczyć na kliencie. Wybór to `router.post` na `budget.select-year` z przekierowaniem na Budżet; tworzenie przez `CreateBudgetDialog` przyjmuje dowolny wolny rok 2000–2100. `SegmentedControl` (reka `ToggleGroup`, `aria-pressed`) nie ma tonu sidebara. Segmenty odwracają akapit ADR-0053 → ADR-0054.

## Charges

Kategorie: **T** brakujący token, **K** brakujący współdzielony komponent / wzorzec, **A** przypadkowa architektura. Skan wartości zakodowanych na sztywno na 11 plikach powłoki: 0 trafień (`change.md`).

### C1 (A) Belka z tytułem nad każdą stroną: potwierdzony

- `layouts/app/AppSidebarLayout.vue:28` renderuje `<AppSidebarHeader :breadcrumbs>`; `components/AppSidebarHeader.vue:17-26`: `<header class="h-16 … border-b px-6 md:px-4">` z `SidebarTrigger` i `Breadcrumbs` (linia 18: `group-has-data-[collapsible=icon]/sidebar-wrapper:h-12`).
- Łańcuch propa: `resources/js/app.ts:12-21` przypisuje `AppLayout` (ustawienia: `[AppLayout, SettingsLayout]`, `:19`) → `layouts/AppLayout.vue:5-11` → `AppSidebarLayout.vue:10-16`.
- 11 wywołań `setLayoutProps({ breadcrumbs: … })`: `Dashboard.vue:97-98`, `Operations.vue:114-115`, `OperationsImport.vue:93-94`, `OperationsImportPreview.vue:170-171`, `MonthPlan.vue:52-53`, `Budget.vue:125-126`, `TermDeposits.vue:55-56`, `Accounts.vue:52-53`, `settings/Profile.vue:18-19`, `settings/Security.vue:25-26`, `settings/Appearance.vue:10-11`. Strony auth używają `setLayoutProps`/`defineOptions` bez `breadcrumbs`.
- Tytuły: `h1.page-title` w `Dashboard.vue:114`, `Operations.vue:158`, `Accounts.vue:130`, `TermDeposits.vue:122`, `Budget.vue:175`, `OperationsImport.vue:195`, `OperationsImportPreview.vue:214`, `MonthPlanTitleRow.vue:25` (użyty w `MonthPlan.vue:558`), `layouts/settings/Layout.vue:34`. W tym zbiorze stron nie znaleziono strony bez `h1`.
- Wzorzec: `PageDashboard/preview.html` — `main.ht-app__main` zaczyna się od `div.ht-title` (`bundle.css`: `.ht-app__main { padding: space-3.5 space-5 space-5 }`); żadna strona-wzorzec nie ma belki.
- Martwe po zmianie: `Breadcrumbs.vue` (jedyny konsument: `AppSidebarHeader.vue:2`), `components/ui/breadcrumb/*` (jedyny konsument: `Breadcrumbs.vue:4-10`), typ `BreadcrumbItem` (`types/navigation.ts:4-7`, importowany w `AppLayout.vue:3`, `AppSidebarLayout.vue:8`, `AppSidebarHeader.vue:4`, `Breadcrumbs.vue:11`).
- Lista `scripts/check-ui-literals.mjs:31` zawiera `AppSidebarHeader.vue`: wpis trzeba usunąć razem z plikiem.
- Dokumenty mówiące o okruszkach: `.ai/rules/js-import.md:27` i ADR-0052 akapit 1 („ostatni element okruszków «Podgląd importu»”); `dev/SettingsKitchenSink.vue:57` (tekst o nadpisywaniu okruszków).
- Testy: w `tests/` (Pest, e2e) i `resources/js/**/*.test.ts` nie ma odwołań do `AppSidebarHeader`, `Breadcrumbs` ani `breadcrumb` (grep agenta).
- Odstęp: strony zaczynają się od `p-4` (`Dashboard.vue:110`, `Operations.vue:154`, `settings/Layout.vue:32`); po usunięciu belki 64 px treść zaczyna się 16 px od góry.
- Wpływ: tytuł widoczny dwa razy, 64 px pionu zajęte przez pasek bez treści.

### C2 (A) Przełącznik, tryb ikon i wejście na telefonie: potwierdzony

- `ui/sidebar/Sidebar.vue:24-31`: `collapsible === 'none'` → zwykły `div` z `w-(--sidebar-width)`, sprawdzany przed gałęzią `v-else-if="isMobile"` (`:33`, `Sheet` z `openMobile`). Przy `none` telefon dostaje stałą kolumnę 224 px zamiast arkusza.
- Desktop (`:55-96`): `hidden md:block`, `data-collapsible` ustawiane tylko gdy `state === 'collapsed'`.
- `SidebarProvider.vue:20`: `isMobile = useMediaQuery("(max-width: 768px)")` (różnica 1 px z `md` = `min-width: 768px`). `:46-51`: Ctrl/⌘+B przez `useEventListener("keydown")` bez sprawdzania pola edycji; `toggleSidebar` na telefonie przełącza `openMobile`, na desktopie `open` i zapisuje ciasteczko `sidebar_state` (`:33-38`).
- Wejścia otwierające arkusz na telefonie (sprawdzony zbiór): `SidebarTrigger` (tylko w `AppSidebarHeader`), `SidebarRail` (0 konsumentów poza `ui/`) i Ctrl/⌘+B.
- Serwer: `sidebarOpen` z ciasteczka (`HandleInertiaRequests.php:48`), typ `types/global.d.ts:21`, konsument `AppShell.vue:14,21`; wyjątek szyfrowania `bootstrap/app.php:21` (`except: ['appearance', 'sidebar_state']`). Testów `sidebarOpen`/`sidebar_state` nie ma.
- Zależności trybu ikon: `AppSidebar.vue:76` (`collapsible="icon"`); `NavUser.vue:21,36,43-45`; `BudgetYearSwitcher.vue:37,87,111,136`; `NavMain.vue:36` (`:tooltip`, widoczny tylko gdy zwinięty i nie mobile, `SidebarMenuButton.vue:27-42`); `AppLogo.vue:9,11`; `UserInfo.vue:31,49`.
- Testy pilnujące trybu ikon: `resources/js/ui-contract.test.ts:239-247` (`group-data-[collapsible=icon]:mx-auto` w `AppLogo.vue` i `UserInfo.vue`). Kitchen sink: ramki `collapsed-years` i `collapsed-empty` z `defaultOpen: false` (`dev/SidebarKitchenSink.vue:99,113`, `:190`).
- Dokumenty o pasie 3rem: `.ai/rules/js-sidebar.md:15-17,21`; `design-system.md:117,129`; ADR-0053 akapity 2 i 4.
- E2E: `tests/e2e/sidebar-current-section.spec.ts` szuka linków w `[data-sidebar="sidebar"]` i nie klika przełącznika; projekt Playwright używa `devices['Desktop Chrome']` (`playwright.config.ts:33`).
- Wpływ: usunięcie belki bez nowego wejścia zostawia telefon bez nawigacji (wymóg: `prd-mvp.md:73-74`, NFR-UX-01 `:377`); ciasteczko `sidebar_state=false` z przeszłości nadal zwinie sidebar przez `defaultOpen`, jeśli `sidebarOpen` zostanie podłączony.

### C3 (K) Brak palety ⌘K: potwierdzony

- `components/ui` nie ma `command` (lista katalogów: `alert … tooltip`). Wzorzec `CommandPalette` (README + `preview.html`): pole z ikoną i `Esc`, grupy „Widoki i akcje” („Dodaj operację N”, „Dashboard 1”) i „Kategorie: operacje” (`CategoryChip` + kwota), pierwsza pozycja zaznaczona, Enter uruchamia, Esc zamyka; klasy `.ht-palette*` (`bundle.css`: tło `sidebar-background`, tekst `sidebar-strong`, zaznaczenie `sidebar-accent`, fokus `sidebar-ring`, `radius-xl`, `shadow-dialog`, szerokość 520 px). README: „Konsument dostarcza listę widoków i akcji ze skrótami, kategorie z realizacją, wybraną pozycję”.
- Pakiety (`package.json:19-24`): `reka-ui` 2.11.0, `@vueuse/core` ^12.8.2 (zainstalowane 12.8.2; reka ma własne 14.4.0), `@lucide/vue` ^1.64.0. `components.json`: `new-york-v4`, domyślny rejestr.
- shadcn-vue `command` (new-york-v4): pliki `Command`, `CommandDialog`, `CommandEmpty`, `CommandGroup`, `CommandInput`, `CommandItem`, `CommandList`, `CommandSeparator`, `CommandShortcut`; zbudowany na reka `ListboxRoot/ListboxFilter/ListboxContent/ListboxGroup/ListboxItem` + `useFilter`; `dependencies: reka-ui, @vueuse/core`, `registryDependencies: dialog`. Nowe pakiety npm: brak; CLI może jednak przepisać `package.json`/`package-lock.json` i zaproponować nadpisanie `ui/dialog`.
- Lokalny `ui/dialog` jest dostosowany do Atramentu: `DialogContent.vue:19-20` (`showCloseButton`), `:37` (`bg-card`, `--dialog-width`, `--dialog-pad-*`, `shadow-dialog`), `DialogOverlay.vue:17` (`bg-scrim`). Nadpisanie łamie regułę „`components/ui/*` bez edycji pod jeden ekran”.
- Dostępność w reka 2.11.0: `ListboxFilter` renderuje `type="text"` z `aria-activedescendant`, bez `role="combobox"`/`aria-expanded`/`aria-controls` (`dist/Listbox/ListboxFilter.js:65-96`); `ListboxContent` → `role="listbox"` (`ListboxContent.js:27`), `ListboxItem` → `role="option"` (`ListboxItem.js:72`); `ListboxRoot` eksponuje `highlightFirstItem()` (`dist/index3.d.ts:911`). `ComboboxInput` ustawia `role="combobox"`, `aria-expanded`, `aria-controls`, `aria-autocomplete="list"` (`Combobox/ComboboxInput.js:125-128`) i zaznacza pierwszą pozycję (`:113`). Pułapkę fokusu i `role="dialog"` daje reka `Dialog`.
- Esc: reka `DismissableLayer.js:72-77` (`onKeyStroke('Escape')` na `window`) zamyka najwyższą warstwę i nie robi `preventDefault`.
- Wpływ: nie da się przejść do widoku ani akcji z klawiatury; przycisk „Szukaj i przejdź” bez palety byłby martwą kontrolką.

### C4 (K) Brak skrótów, podpowiedzi i wspólnej listy widoków: potwierdzony

- `NavMain.vue:28-42` bez `ht-kbd`; `AppSidebar.vue:75-103` bez przycisku wyszukiwania i linijki „Skróty: …” (wzorzec `.ht-side__search`, `.ht-side__nav .ht-kbd`, `.ht-side__hint`: 11/16, `sidebar-foreground`; `.ht-kbd`: mono 11/16, 500, ramka `currentColor`, `radius-xs`).
- `mainNavItems` to lokalna stała `AppSidebar.vue:41-72`; skróty `1`–`6` i paleta potrzebują jednej listy (ta sama kolejność). `period-report-by-category` dopisuje „Raporty” do tej tablicy (`context/changes/period-report-by-category/plan.md:17`), co daje 7 pozycji.
- Globalna obsługa klawiszy poza `ui/`: brak (`onKeyStroke`/`useMagicKeys` 0 trafień; jedyne lokalne `@keydown` to `OperationsFilters.vue:399`). Helper „fokus w polu edycji”: brak w `lib/` i `composables/`. Skróty `1`–`6`/`N` muszą też milczeć, gdy otwarty jest dialog (fokus na przycisku w dialogu nie jest polem edycji).
- `N`: `pages/Operations.vue:86-93` trzyma 8 lokalnych flag `ref(false)`; `OperationsPageActions.vue:94-193` emituje `add-income`/`add-expense`/`add-transfer`/`add-capital-movement` (podpięte `Operations.vue:164-170`). Dialogi potrzebują propsów strony `OperationsController.php:188-191` (`accounts`, `incomeCategories`, `expenseCategories`, `termDeposits`) oraz `entryToSpend`/`handEntryMonthRemaining` (`Operations.vue:66-70`); współdzielone propsy (`HandleInertiaRequests.php:41-51`) ich nie zawierają. Otwieranie dialogu z adresu: tylko kitchen sinki (`dev/DialogsKitchenSink.vue:201`, `dev/SettingsKitchenSink.vue:39`). Warunki wyłączenia: `lib/operations-create-availability.ts:20-21` (transfer własny ≥ 2 konta, ruch kapitału ≥ 1 konto i ≥ 1 lokata).
- `/`: `OperationsFilters.vue:393-400` (`<Input v-model="search" type="search">`, bez `id`/ref; `Input.vue:23` ma jeden korzeń, więc `id` przejdzie); parametr `q`, debounce 500 ms (`:62`, `:164-181`); filtry renderowane tylko przy `hasAnyRecords` (`OperationsListPanel.vue:207`).
- Testy czystej logiki: Vitest przez Vite+ (`vite.config.ts:44-46`, `vite-plus/test`, importy względne; `.ai/rules/frontend-toolchain.md`), bez środowiska DOM (brak jsdom/happy-dom). Predykat „czy skrót ma zadziałać” musi przyjmować zwykły opis zdarzenia (klawisz, modyfikatory, `tagName`, `isContentEditable`, otwarty dialog), a moduł w `lib/` nie importuje `@/routes`.
- Wpływ: brak skrótów z mocka; `N` i `/` bez decyzji o kontrakcie działałyby tylko na stronie Operacji.

### C5 (K) Rok budżetu w segmentach: potwierdzony

- `BudgetYearSwitcher.vue` (174 linie): propsy `budgetYears: number[]`, `budgetYear: number | null` (`:29-34`); wybór `router.post(BudgetController.selectYear().url, { year })` z `pending` (`:57-79`), toast błędu przez `listenForBudgetYearSelectionFailure`/`notifyBudgetYearSelectionFailed` (`:43-49`, `lib/budget-year-selection.ts:4-30`); `aria-disabled`/`aria-busy` przy `pending` (`:113-114`); stan pusty (`:84-104`); „Dodaj rok” otwiera `CreateBudgetDialog` po `nextTick` (`:51-55`, `:158-172`).
- Serwer: `POST budget/year` → `BudgetController@selectYear` (`routes/web.php:49`; `SelectBudgetYearRequest`: `year` istnieje w budżetach użytkownika, `destination` ∈ {`budget`, `month-plan`}); zapis `session('budget_year')` i przekierowanie na `budget` (`BudgetController.php:137-148`). `POST budgets` → `store` (`routes/web.php:41`; `StoreBudgetRequest.php:21-37`: 2000–2100, unikalny dla użytkownika; opcjonalne źródło kategorii).
- `shell.budgetYears`: lata z budżetem, rosnąco; `shell.budgetYear`: rok z sesji, jeśli jest na liście, inaczej pierwszy (najstarszy), `null` przy pustej liście (`ShellState.php:26-47`, `SelectedBudgetYear.php`). Roku „bez budżetu” w propsie nie ma — segment „+” (README: „rok bez budżetu ma dopisek «+»”) trzeba wyliczyć po stronie klienta; domyślny rok w `CreateBudgetDialog` to `currentYear + 1` przy istniejących latach, inaczej `currentYear` (`CreateBudgetDialog.vue:44-60`).
- Glosariusz: Budget = jeden rok kalendarzowy, najwyżej jeden na użytkownika na rok (`glossary.md:24`); brak reguły o liczbie lat ani o tworzeniu tylko następnego roku.
- `SegmentedControl.vue` (65 linii): reka `ToggleGroupRoot type="single"`, `aria-pressed`, jedna pozycja tabulacji, strzałki; opcje `{value: string, label, count?}`, `tone: 'primary'|'neutral'`, `block`, `name`; style `app.css:566-606` z tokenów głównych (`bg-background`, `bg-primary`), bez tonu `--sidebar-*`. Wzorzec `.ht-yseg`: wybrany rok na tle `sidebar-strong`.
- Testy: `tests/Feature/BudgetTest.php:25-62` (tworzenie), `:210` (`shell.budgetYears`), `:227-258` (wybór); `resources/js/lib/budget-year-selection.test.ts`; `ui-contract.test.ts:248-252` sprawdza `aria-label` przycisku roku, a `:26` i `:263` obejmują przełącznik.
- Kitchen sink: ramki `expanded-years`, `collapsed-years`, `expanded-empty`, `collapsed-empty` (`dev/SidebarKitchenSink.vue:89-116`), przycisk „Pokaż błąd wyboru roku” (`:160-170`), samodzielny przełącznik (`:269-272`); ramki pending/disabled nie ma.
- Dokumenty do zmiany: ADR-0053 akapit 3 (przycisk roku) i akapit końcowy (odrzucenie segmentów); `design-system.md:123,125` („kafelek przełącznika roku”), `:131`; `.ai/rules/js-sidebar.md:21` („the selected year use `sidebar-strong`”, „Check the icon mode, the empty year state…”).
- Wpływ: wybór roku wymaga dziś dwóch kliknięć w menu zamiast jednego segmentu; wzorzec pokazuje lata wprost.

## Detailed Findings

### Dane kategorii dla palety

- Realizacja = Execution (`glossary.md:47-49`, w UI „Realizacja”/„wykonanie”; `lang/pl/ui.php:152`). Przechowywana w `categories.execution_minor_units` (`bigint`, `database/migrations/2026_09_07_180001_create_categories_table.php:25`, `app/Models/Category.php:28`), zmieniana `increment`/`decrement` w `app/Actions/ManageFinancialOperation.php:226-244`. Dashboard (`DashboardController.php:235,246`) i Budżet (`BudgetController.php:199,218`) czytają kolumnę bezpośrednio; Operacje wysyłają ją w `expenseCategories` (`OperationsController.php:165-182`).
- Kwota z podglądu palety („Dom 29 880,00 zł”) równa się „Realizacji” kategorii „Dom” w tabeli „Budżet 2026” na `PageDashboard/preview.html`, czyli realizacji rocznej.
- Odczyt miesięczny (`MonthCategoryActuals::forMonths`, `app/Support/MonthCategoryActuals.php:27-43`) to grupowany `SUM` po `financial_operations` — droższy wariant, nieużywany przez wzorzec.
- Koszt rocznego odczytu: jeden `SELECT` na `categories` jednego budżetu; indeksy `(budget_id, type)` i `(budget_id, type, sort_order)` (migracja `:30-31`). Shell wykonuje dziś ok. 6 małych zapytań na żądanie (`ShellState.php:28-46`). NFR-PERF-01 (`prd-mvp.md:372`, `tests/Feature/Nfr/NfrPerf01Test.php`, PostgreSQL, 2,0 s przy 10 000 operacji) mierzy żądania, w których działałby każdy prop współdzielony.
- Kategoria należy do jednego Budżetu (`budget_id`, `glossary.md:36`); typy Income/Expense; `archived_at` (`Category.php:69`); `scopeOrdered()` aktywne przed zarchiwizowanymi, potem `sort_order` (`Category.php:78-83`); wygląd `icon`/`color` (`app/Support/CategoryAppearance.php`, `CategoryChip.vue:7-14`).
- Który budżet czytają widoki: Dashboard — bieżący rok kalendarzowy (`DashboardController.php:33-35`); Budżet — wybrany rok z sesji (`BudgetController.php:32`).
- Inertia: `inertiajs/inertia-laravel` 3.7.0 (`composer.lock:1292-1293`), `@inertiajs/vue3` 3.11.0. `ResponseFactory.php` ma `optional()` (`:259`), `defer()` (`:267`), `always()` (`:297`), `once()` (`:320`) i `shareOnce`; propsy współdzielone przechodzą przez ten sam resolver (`PropsResolver.php:173-175`). W `app/` i `routes/` nie ma dziś propsów `optional`/`defer`/`once` (jedyny leniwy to domknięcie `translations`, `HandleInertiaRequests.php:50`). `useHttp` jest użyty w `composables/useTwoFactorAuth.ts:31` i `components/BudgetCategoryList.vue:73-182`; aplikacja nie ma endpointu JSON czytanego GET-em.
- Filtr Operacji po kategorii: `category_name` + `category_type` (`IndexOperationsRequest.php:29-30`), po nazwie i typie, bez okresu dopasowuje kategorie z każdego roku (`OperationsListQuery.php:139-147`). Wzorzec linku z rokiem: `categoryOperationsQuery` (`lib/dashboard-categories.ts:34-49`, używany w `CategoryMeterRow.vue:28`); reguła `.ai/rules/js-operations.md`: każde zapytanie `/operations` przez `operationsListQuery` (`lib/operations-list-query.ts:23-38`).
- Testy propsów współdzielonych: `tests/Feature/AppShellTest.php:37-89` (dostawca danych po 5 stronach, asercje `shell.*` i `translations.*`), `BudgetTest.php:210-242`, `AccountTest.php:473-482`, `DashboardTest.php:572`.

### Akcje palety i ich cele

- Widoki: `dashboard()`, `budget()`, `monthPlan()`, `operations()` z `@/routes`, `index` z `@/routes/accounts` i `@/routes/term-deposits` (`AppSidebar.vue:27-29`; trasy `routes/web.php:28,40,50,75`).
- Import: `operations/import` (`routes/web.php:76`, nazwa `operations.import.create`; na froncie `CsvImportController.create.url()`, `OperationsPageActions.vue:13,88`).
- Dodaj rok: `CreateBudgetDialog` czyta `useShell()` sam (`CreateBudgetDialog.vue:25-52`), więc da się go zamontować poza przełącznikiem; kolejność „zamknij menu → `nextTick` → otwórz dialog” (`BudgetYearSwitcher.vue:51-55`).
- Ustawienia: `edit()` z `@/routes/profile` (`UserMenuContent.vue:13,38`); wylogowanie: `logout()` jako POST `Link as="button"` + `router.flushAll()` (`UserMenuContent.vue:22-24,46-55`).

### Tłumaczenia

- `lang/pl/ui.php` → leniwy prop współdzielony `translations` (`UiTranslations::all()`, `app/Support/UiTranslations.php:10-20`) → `useTranslations()` (`composables/useTranslations.ts:5-9`). `types/shell.ts:21-48` typuje każdy klucz `nav` i `shell`; pozostałe sekcje to `Record<string,string>`. Nowy klucz w `nav`/`shell` lub nowa sekcja wymaga zmiany PHP i typu TS; `AppShellTest.php:56-89` ma już asercje `translations.*`.

## Code References

- `resources/js/layouts/app/AppSidebarLayout.vue:28` — belka nad treścią
- `resources/js/components/AppSidebarHeader.vue:17-26` — `SidebarTrigger` + `Breadcrumbs`
- `resources/js/components/ui/sidebar/Sidebar.vue:24-31,33-53,55-96` — gałęzie `none` / mobile / desktop
- `resources/js/components/ui/sidebar/SidebarProvider.vue:20,33-38,46-51` — próg mobile, ciasteczko, Ctrl/⌘+B
- `app/Http/Middleware/HandleInertiaRequests.php:41-51` — propsy współdzielone (`sidebarOpen` `:48`, `shell` `:49`)
- `app/Support/ShellState.php:26-47` — `budgetYears`, `budgetYear`, `copyableBudgetSources`, `firstRun`
- `resources/js/components/AppSidebar.vue:41-72,76` — `mainNavItems`, `collapsible="icon"`
- `resources/js/components/BudgetYearSwitcher.vue:29-172` — przełącznik roku
- `resources/js/components/SegmentedControl.vue`, `resources/css/app.css:566-606` — segmenty
- `resources/js/pages/Operations.vue:66-93,164-170` — lokalne dialogi operacji
- `resources/js/components/OperationsFilters.vue:393-400` — szukajka Operacji
- `resources/js/lib/dashboard-categories.ts:34-49` — link kategorii do Operacji
- `app/Actions/ManageFinancialOperation.php:226-244` — aktualizacja realizacji
- `resources/js/ui-contract.test.ts:239-252` — asercje trybu ikon i `aria-label` roku
- `resources/js/pages/dev/SidebarKitchenSink.vue:89-116,160-170,190` — ramki kitchen sinka
- `scripts/check-ui-literals.mjs:22,31,104` — `BudgetYearSwitcher`, `AppSidebarHeader`, `SegmentedControl`

## Architecture Insights

- Sidebar jest całą powłoką; ADR-0053 akapit 2 dopuszcza edycję prymitywów `ui/sidebar/*` z nazwaniem wierszy w ADR. Ten sam precedens obejmuje ewentualną zmianę gałęzi `none` w `Sidebar.vue` albo wymuszenie `open`.
- Paleta potrzebuje danych z trzech źródeł: lista widoków (frontend, wspólna z `NavMain`), akcje (frontend + istniejące trasy i dialogi), kategorie (serwer). Tylko kategorie dotykają propsów Inertii.
- Wszystkie kolory palety istnieją jako tokeny sidebara (`bg-sidebar`, `text-sidebar-strong`, `bg-sidebar-accent`, `sidebar-ring`; `app.css:77,81,83`); nowe wymiary (szerokość 520 px, `.ht-kbd`, linijka podpowiedzi 11/16) to nazwane klasy/tokeny w `app.css` (reguła `css.md`, `check:ui` odrzuca `-[..px]`).

## Historical Context (from prior changes)

- `context/archive/2026-11-06-sidebar-atrament-ui/change.md:16` — paleta ⌘K i skróty odłożone do osobnej zmiany (nowy komponent, globalne klawisze, nowy prop z kategoriami); **nadal trafne co do kosztu**, decyzję o odłożeniu zastępuje decyzja użytkownika z `change.md` tej zmiany.
- `…/sidebar-atrament-ui/change.md:17` i ADR-0053 akapit końcowy — segmenty odrzucone (pas 3rem, nieograniczona liczba lat, „2027 +” miesza wybór z tworzeniem). Werdykty: pas 3rem — **przestaje obowiązywać**, jeśli desktop traci zwijanie; nieograniczona liczba lat — **nadal trafne** (brak limitu w `StoreBudgetRequest.php`, zakres 2000–2100); mieszanie wyboru z tworzeniem — **trafne jako opis**, rozstrzyga użytkownik.
- `…/sidebar-atrament-ui/research.md:109` — brak wymagania palety w PRD; powiązane NFR-ACC-01/02 (`prd-mvp.md:375-376`): **nadal trafne** (nie sprawdzano ponownie PRD poza tym zdaniem).
- Wcześniejsze odłożenia palety: `2026-11-05-ui-operations-atrament/plan.md:46`, `design-tokens-atrament/plan.md:41`, `budget-atrament-ui/plan.md:45`.

## Related Research

- `context/archive/2026-11-06-sidebar-atrament-ui/research.md` — zarzuty C1–C7 poprzedniej zmiany sidebara
- `context/archive/2026-11-03-sidebar-ui-contract/research.md`
- `context/changes/period-report-by-category/plan.md` — 7. pozycja menu

## Open Questions

Decyzje dla `/10x-plan` (żadna nie jest rozstrzygnięta w repo):

1. **Wejście na telefonie** — gdzie stoi ☰ po usunięciu belki (wąski pasek tylko `<md`, przycisk w wierszu tytułu strony, pływający przycisk) i jak sidebar przestaje się zwijać na desktopie (`offcanvas`/`icon` z wymuszonym `open` vs zmiana gałęzi `none` w `Sidebar.vue`). Los Ctrl/⌘+B, ciasteczka `sidebar_state`, propa `sidebarOpen` i wyjątku w `bootstrap/app.php:21`.
2. **Dane kategorii** — sposób dostarczenia: (A) prop w `shell` przy każdym żądaniu, (B) `Inertia::optional` doczytywany `router.reload({ only })` przy otwarciu palety, (C) `defer`, (D) `once` z odświeżeniem po zapisie operacji, (E) osobny endpoint JSON + `useHttp`. Który budżet (bieżący rok kalendarzowy jak Dashboard czy `shell.budgetYear` jak Budżet), czy kategorie dochodów i zarchiwizowane wchodzą do listy, i dokąd prowadzi wybór (Operacje z `categoryOperationsQuery` dla roku).
3. **`N` poza Operacjami** — nowy kontrakt adresu (np. `/operations?new=expense`, otwarcie dialogu po załadowaniu) vs `N` przechodzi na Operacje i otwiera menu „Dodaj operację” vs dialogi operacji dostępne globalnie (nowe propsy współdzielone). Który rodzaj operacji domyślnie.
4. **`/`** — na Operacjach fokus szukajki; na innych stronach przejście na Operacje z fokusem, czy otwarcie palety; zachowanie przy pustej liście (brak szukajki).
5. **Segment „+”** — który rok proponuje (max + 1 jak `CreateBudgetDialog`, czy bieżący + 1), czy otwiera `CreateBudgetDialog`, jak zachowują się segmenty przy wielu latach w 224 px (zawijanie, przewijanie, ostatnie N lat + reszta w palecie), ton segmentów (nowy wariant `SegmentedControl` z tokenami `--sidebar-*` vs osobny komponent).
6. **Komponent palety** — `npx shadcn-vue add command` z odrzuceniem nadpisania `ui/dialog` i bez zmian `package-lock.json` (zmiana zależności wymaga zgody), czy ręczne skopiowanie plików rejestru; Listbox (shadcn) z ręcznie dodanym `role="combobox"`/`aria-expanded`/`aria-controls` vs reka Combobox.
7. **Dokumenty** — ADR-0054 (segmenty, brak zwijania, paleta i skróty, usunięcie belki i okruszków), aktualizacja `design-system.md` „Sidebar”, `.ai/rules/js-sidebar.md`, `.ai/rules/js-import.md:27`, ADR-0052 akapit 1 (okruszki), testów `ui-contract.test.ts:239-252` i ramek kitchen sinka (`collapsed-*` znikają, dochodzą stany palety).
