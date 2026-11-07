---
date: 2026-11-07T22:20:00+01:00
researcher: Claude Sonnet 5.5
git_commit: 40d014c75a37aa733b1a199c2ff68f0c5217b0b8
branch: development
repository: household-treasurer
topic: "Sidebar pokazuje stary rok budżetu po przełączeniu roku (migawka shell w trwałym layoucie)"
tags: [research, sidebar, inertia, persistent-layout, shell, useShell, budget-year]
status: complete
last_updated: 2026-11-07
last_updated_by: Claude Sonnet 5.5
---

# Research: sidebar nadal pokazuje stary rok po przełączeniu roku

**Date**: 2026-11-07T22:20:00+01:00
**Researcher**: Claude Sonnet 5.5
**Git Commit**: 40d014c75a37aa733b1a199c2ff68f0c5217b0b8 (working tree czysty, poza folderem tej zmiany)
**Branch**: development
**Repository**: household-treasurer

## Research Question

Po kliknięciu innego roku w przełączniku „Rok budżetu” strona ładuje się dla nowego roku, ale sidebar nadal zaznacza stary rok; dopiero F5 to naprawia. Do ustalenia: (1) odtworzenie i mechanizm w Inertia 3, (2) pełna lista miejsc łapiących migawkę `shell`/`translations`, (3) czy `useTranslations` ma tę samą wadę, (4) warstwa testu regresji, (5) kształt poprawki i skutki zmiany typu zwracanego `useShell`.

## Summary

**Hipoteza z zgłoszenia jest potwierdzona, i to w przeglądarce.** `useShell()` zwraca `page.props.shell` odczytane jeden raz w `setup` (`resources/js/composables/useTranslations.ts:11-15`). Inertia przy każdej wizycie podstawia nowy obiekt strony (`page.value = options.page`, `node_modules/@inertiajs/vue3/dist/index.js:599`), więc obiekt `shell` złapany w `setup` trwałego layoutu zostaje obiektem z pierwszej wizyty. `AppSidebarLayout.vue:14` robi dokładnie to, a layout nie jest montowany ponownie między wizytami. Efekt: `<AppSidebar :budget-years="shell.budgetYears" :budget-year="shell.budgetYear">` (`AppSidebarLayout.vue:25-28`) dostaje wartości z pierwszego załadowania strony.

Dwa sprawdzone w przeglądarce scenariusze (szczegóły w „Odtworzenie”): przełączenie roku (2026 → 2027) oraz utworzenie roku („+”). W obu strona pokazuje nowy rok, a sidebar stary. Przy tworzeniu roku sidebar nie dostaje też nowego segmentu roku.

Ten sam wzorzec ma jeszcze jedno miejsce w trwałym layoucie: `CreateBudgetDialog` zamontowany w `AppSidebarLayout` (`CreateBudgetDialog.vue:46`). `useFirstRunChecklist` i `Dashboard.vue:37` łapią migawkę, ale w komponentach strony, które Inertia montuje od nowa przy każdej wizycie bez `preserveState`, więc dziś nie dają widocznego błędu. `useTranslations` ma tę samą wadę techniczną, ale bez skutku widocznego dla użytkownika, bo treść `translations` nie zależy od żądania.

Regresję najtaniej i najwierniej łapie test E2E w Playwright (infrastruktura jest, odtworzenie w tej sesji zajęło ~20 s). Testów komponentów Vue repo nie ma i nie ma do nich zależności (`@vue/test-utils`, `jsdom`, `happy-dom`). Poprawka najbezpieczniejsza dla typów: `useShell()` zwraca `ComputedRef<ShellState>`, bo `vue-tsc` wskaże wtedy każde miejsce wymagające `.value`.

## Detailed Findings

### 1. Mechanizm: `page` jest podmieniany przy każdej wizycie, layout nie jest remontowany

- `useShell()` i `useTranslations()` czytają `page.props.<klucz>` raz i zwracają surową wartość, nie `computed` (`resources/js/composables/useTranslations.ts:5-15`).
- W `@inertiajs/vue3` 3.11.0 `page` to `ref` (`dist/index.js:521`), a `swapComponent` przy każdej wizycie robi `page.value = options.page` (`:599`). `usePage()` wystawia `props` jako `computed(() => page.value?.props)` (`:685-688`). Obiekt `shell` odczytany wcześniej wskazuje więc na dane poprzedniej wizyty; nowa wizyta daje nowy obiekt, starego nie mutuje. To spójne z wynikiem przeglądarki poniżej.
- Layout jest renderowany w funkcji `render` aplikacji jako `h(layoutComponent, { ...page.value.props, ... }, () => childNode)` (`dist/index.js:656-668`). Przy tym samym typie komponentu Vue robi patch, bez ponownego `setup`. Tylko komponent strony dostaje `key` (`key.value = options.preserveState ? key.value : Date.now()`, `:600`), więc strona jest montowana od nowa, a layout nie.
- Layout wybiera `resources/js/app.ts:12-23` (`AppLayout` dla zwykłych stron i dla `settings/`, `AuthLayout` dla `auth/`, `null` dla `Welcome`), a `AppLayout.vue` tylko opakowuje `AppSidebarLayout.vue`.
- Dokumentacja Inertia 3 (search-docs, „Persistent Layouts”): instancja layoutu „is kept alive between visits”; jako wzorzec dostępu do danych współdzielonych podaje `computed(() => page.props.auth.user)` — czyli odczyt reaktywny, nie jednorazowy.
- Serwer wysyła świeży `shell` w każdej odpowiedzi: `HandleInertiaRequests::share` ustawia `'shell' => ShellState::forRequest($request)` (`app/Http/Middleware/HandleInertiaRequests.php:48`, wartość bezpośrednia, nie `fn`), a `budgetYear` jest liczony z sesji (`app/Support/SelectedBudgetYear.php`, `ShellState.php:42-44`). `selectYear` zapisuje `budget_year` w sesji i przekierowuje (`BudgetController.php:137-148`); `store` robi to samo dla nowo utworzonego roku (`:109`, `:116`). Po stronie serwera nic nie jest nieaktualne: odświeżenie strony (F5) pokazuje poprawny stan.

### 2. Odtworzenie w przeglądarce (ta sesja)

Metoda: tymczasowy spec Playwright (usunięty po użyciu) uruchomiony przez `npx playwright test` na buildzie produkcyjnym i bazie `household_treasurer_e2e` (zgodnie z `context/foundation/test-stack.md`). Baza miała jeden rok (2026, `demo:seed-household`). Wynik to opis działania tej ścieżki, nie dowód dla wszystkich stron.

| Krok | Sidebar (rok:`aria-pressed`) | Nagłówek strony |
| --- | --- | --- |
| Start na `/budget` | `2026:true`, `2027 +` | Budżet 2026 |
| Utworzenie roku 2027 przez „2027 +” (dialog „Utwórz budżet”) | **bez zmian**: `2026:true`, `2027 +` | Budżet 2027 |
| Reload (F5) | `2026:false`, `2027:true`, `2028 +` | Budżet 2027 |
| Baza z dwoma latami, start na 2026, klik „2027” | **`2026:true`**, `2027:false` | Budżet 2027 |
| Reload (F5) | `2026:false`, `2027:true` | Budżet 2027 |
| Z `/accounts` klik „2026” (ten sam układ, inny komponent strony) | **`2027:true`**, `2026:false` | Budżet 2026, URL `/budget` |

Wnioski z tej tabeli (tylko dla tych trzech przejść):
- Objaw z zgłoszenia występuje przy wyborze roku, przy tworzeniu roku i przy wyborze roku z innej strony niż Budżet; nie zależy od strony, na której kliknięto.
- Przy tworzeniu roku nieaktualna jest też lista `budgetYears` (brak segmentu nowego roku, „+” nadal ma stary rok), czyli migawka dotyczy całego obiektu `shell`, nie tylko `budgetYear`.
- Wcześniejszy przebieg z tym samym scenariuszem pokazał też, że kliknięcie roku zaznaczonego w nieaktualnym sidebarze nic nie robi (`BudgetYearSwitcher.vue:60` — `if (segment.year === props.budgetYear) return;`): użytkownik nie może „cofnąć” się do roku, który sidebar błędnie uważa za bieżący, dopóki nie przeładuje strony. To wniosek z kodu i obserwacji, że po kliknięciu 2026 przy zaznaczonym (błędnie) 2026 nagłówek został na 2027.

Sprzątanie po odtworzeniu: tymczasowy spec usunięty, budżet 2027 (draft, utworzony przeze mnie, id 2) usunięty z bazy E2E; `budgets` zawiera znów tylko rok 2026; `git status` pokazuje wyłącznie `context/changes/sidebar-year-refresh/`.

### 3. Wszystkie miejsca łapiące migawkę (sprawdzony zbiór: `resources/js`, `*.vue` i `*.ts`)

Wyszukiwanie `useShell|useTranslations(|props.shell|page.props|usePage` w `resources/js` (bez `actions/routes/wayfinder`).

**`useShell()` — trzy wywołania, plus jeden pośredni:**

| Miejsce | Czy trwałe między wizytami | Skutek |
| --- | --- | --- |
| `layouts/app/AppSidebarLayout.vue:14` (`shell.budgetYears`, `shell.budgetYear` w `:25-28`) | **tak** (layout) | **Błąd z zgłoszenia** (potwierdzony) |
| `components/CreateBudgetDialog.vue:46`; użycia w `:59` (`defaultYear`), `:67` i `:75` (`copyableBudgetSources`) | **tak, gdy jest instancją z layoutu** (`AppSidebarLayout.vue:38`); w stronach (`Dashboard.vue:202`, `BudgetEmptyState.vue:20`, `DashboardBudgetBlock.vue:97`) montuje się razem ze stroną | Dialog z layoutu proponuje rok i listę źródeł do kopiowania z pierwszej wizyty. Z kodu: po utworzeniu roku źródło kopiowania z nowego budżetu nie pojawi się na liście, a domyślny rok zostaje stary; nie sprawdzone w przeglądarce |
| `pages/Dashboard.vue:37` (`canSelectCurrentYearInShell` `:92-94`, `v-if="shell.firstRun.showChecklist"` `:119`) | nie: komponent strony ma `key` zmieniany przy każdej wizycie bez `preserveState`; Dashboard nie ma wizyt z `preserveState` (przeszukane `preserveState` w `resources/js`: TermDeposits, Budget, Accounts, Operations, MonthPlan, `BudgetMonthPicker`, `OperationsFilters`) | Dziś bez widocznego błędu; wada ukryta, jeśli kiedyś doda się `preserveState` do Dashboardu |
| `useFirstRunChecklist()` (`useTranslations.ts:17-42`), jedyny użytkownik `Dashboard.vue:38` | jak wyżej | `computed` czyta `shell` złapany poza nim, więc nigdy się nie przeliczy po zmianie `page.props.shell` (zgodnie z pytaniem 2 zgłoszenia); dziś bez skutku z tego samego powodu co wyżej |

**Czytające `page.props.shell` poprawnie:** `useCommandPalette.ts:37` i `:126-135` (`nextBudgetYear()` czyta `page.props.shell` w chwili wywołania; poprawka z `fc45ffa`, komentarz w kodzie wprost nazywa problem). Kategorie palety **nie są cache'owane per rok**: `watch(open, …)` woła `loadCategories()` przy każdym otwarciu (`useCommandPalette.ts:79-85`), rok rozstrzyga serwer (`CommandPaletteCategoryController`, trasa `command-palette.categories`, `routes/web.php:55`; odpowiedź zawiera `budget_year`). Brak migawki roku po stronie klienta.

**Nie czytają `shell` wcale:** `useAppViews.ts` (tytuły z `t.nav.*` i helpery tras), `useGlobalShortcuts.ts`, `BudgetYearSwitcher.vue` (dostaje `budgetYears` i `budgetYear` jako propsy; `segments` jest `computed` nad propsami, `:29-35`, więc po przekazaniu świeżych wartości zadziała bez zmian). Pozostałe `usePage()` w `resources/js` czytają `props.name` (`AppMobileBar.vue:9`, `AppLogo.vue:5`, `LandingAppPreview.vue:20`, `AuthCardLayout.vue:17`, `dev/SidebarKitchenSink.vue:31`; stała nazwa aplikacji), `auth.user` w `computed` (`NavUser.vue:19`, `settings/Profile.vue:15`, `Welcome.vue:23-25`), `url` w `computed` (`useCurrentUrl.ts:25-32`) lub `component`/`url` w momencie wywołania (`useOperationsIntent.ts:29`, `dev/SidebarKitchenSink.vue:74`). `auth.user` w `VerifyEmail.vue:14` jest migawką, ale na stronie `auth/*` z nowym `key`.

Odczyty przez `$page` w szablonach: `grep '\$page'` w `resources/js` (`*.vue`, `*.ts`, poza `types/`) nie zwrócił trafień. Zbiór nie obejmuje PHP ani widoków Blade.

### 4. `useTranslations` — ta sama wada techniczna, bez skutku dla użytkownika

- `useTranslations()` zwraca `page.props.translations` jednorazowo (`useTranslations.ts:5-9`); wywołuje je 88 plików łącznie z definicją (`grep -rln "useTranslations()"`), w tym komponenty trwałego layoutu: `AppSidebar`, `BudgetYearSwitcher` i inne montowane w `AppSidebarLayout`.
- Po stronie serwera `translations` to `fn (): array => UiTranslations::all()` (`HandleInertiaRequests.php:49`), czyli leniwy, ale wykonywany przy każdym żądaniu; wynik zależy tylko od `config('app.locale')` (`UiTranslations.php:10-20`, `config/app.php:81`, `env('APP_LOCALE', 'pl')`). W kodzie aplikacji nie znalazłem `setLocale` ani zmiany języka na żądanie (`grep "setLocale|locale"` w `app/Http/Middleware` i `config/app.php` — tylko wartość domyślna). Każda wizyta daje więc nowy obiekt o tej samej treści.
- Wniosek: wada ukryta (nieaktualny, ale równy obiekt), która zacznie szkodzić dopiero po wprowadzeniu zmiany języka w trakcie sesji. Poprawka dla 88 plików tylko dla tego przypadku nie jest uzasadniona dowodami z tej analizy; to decyzja do planu.

### 5. Warstwa testu regresji

- **Testy komponentów Vue: brak.** Wszystkie 38 plików `resources/js/**/*.test.ts` (`lib/`, `pages/dev/` i `ui-contract.test.ts`) to testy czystej logiki albo kontroli tekstu źródeł; żaden nie montuje komponentu. Konfiguracja `vite.config.ts:55-57` ma tylko `test.include`. W `package.json` nie ma `@vue/test-utils`, `jsdom` ani `happy-dom` (w `package-lock.json` występują tylko jako opcjonalne peer-zależności vitest). Zgodnie z `AGENTS.md` zmiana zależności wymaga zgody użytkownika.
- **Test jednostkowy bez DOM jest możliwy**: istnieje wzorzec `vi.mock('@inertiajs/vue3', …)` z `vi.hoisted` (`lib/budget-year-selection.test.ts:1-9`, `lib/operations-list-visit.test.ts:1-5`). Test `useShell` z podstawionym `usePage` zwracającym `ref`, w którym podmienia się `value.props.shell`, i asercją, że wartość zwrócona z `useShell()` odzwierciedla nową — pada przed poprawką. Pokrywa jednak tylko composable, nie trwałość layoutu ani prawdziwe przejście Inertia. Recenzja `sidebar-action` F9 zapisała dokładnie tę przeszkodę: „no unit test, the composable needs Inertia”.
- **E2E (Playwright) jest dostępny i działa**: `tests/e2e/*.spec.ts`, konfiguracja `playwright.config.ts` (1 worker, `storageState`, serwer `scripts/e2e-server.sh` z buildem produkcyjnym), komendy w `context/foundation/test-stack.md`. Sąsiedni wzorzec: `tests/e2e/sidebar-current-section.spec.ts` (lokator `[data-sidebar="sidebar"]`, `aria-current`). Specyfika dla tej zmiany: użytkownik E2E ma jeden rok (2026), więc spec musi sam utworzyć drugi rok przez dialog „Utwórz budżet” i usunąć go na końcu (wzorzec „tworzy własne dane i sprząta”: `account-edit-keeps-bank.spec.ts:6`, `:28`, `:88`). `.sidebar-year-segments button` z `aria-pressed` to wystarczający lokator (`BudgetYearSwitcher.vue:94-96`, `js-sidebar.md`). Uruchomienie wymaga działającego stosu Sail i braku `public/hot`.
- Pozostaje pytanie, czy regresja ma być pokryta jednym testem E2E, E2E plus testem composable, czy jeszcze zależnością do testów komponentów — decyzja planu, nie badania.

### 6. Kształt poprawki

Zbadane skutki zmiany typu zwracanego `useShell()` z `ShellState` na `ComputedRef<ShellState>`:

- Wywołujący są tylko ci cztery: `AppSidebarLayout.vue:14`, `CreateBudgetDialog.vue:46`, `Dashboard.vue:37` i `useFirstRunChecklist` (`useTranslations.ts:18`). Żaden test nie importuje `useShell`/`useFirstRunChecklist` (grep w `resources/js/**/*.test.ts` i `tests/`: brak trafień), a `ui-contract.test.ts` nie odwołuje się do tych nazw.
- W szablonach `<script setup>` ref najwyższego poziomu jest rozpakowywany, więc `shell.budgetYears` w `AppSidebarLayout.vue:26-27` i `shell.firstRun.showChecklist` w `Dashboard.vue:119` działają bez zmian. W skryptach potrzebne `.value`: `CreateBudgetDialog.vue:59,67,75`, `Dashboard.vue:93`, `useTranslations.ts:24-39`. `vue-tsc` wskaże każde pominięcie, bo `ComputedRef` nie ma pól `budgetYears`/`firstRun`.
- `ComputedRef` usuwa też ukrytą wadę `useFirstRunChecklist` (`computed` nad `computed` śledzi zmianę).

Warianty (bez wyboru, ze skutkami):

| Wariant | Zmiana | Skutek / koszt |
| --- | --- | --- |
| A. `useShell(): ComputedRef<ShellState>` (`computed(() => page.props.shell as ShellState)`) | 4 miejsca + `.value` w 5 odczytach skryptowych | Kompilator pilnuje; zgodne z oficjalnym wzorcem z dokumentacji Inertia; zmiana API dla wywołujących |
| B. `useShell()` zwraca reaktywny proxy o typie `ShellState` (np. `toReactive` z `@vueuse/core` ^12.8.2, który już jest w `package.json`) | tylko `useTranslations.ts` | Wywołujący bez zmian; ale pole nieaktualne „po cichu” znowu stanie się możliwe do napisania bez błędu typu, i dochodzi nieoczywisty proxy |
| C. Czytanie `usePage().props.shell` w miejscu użycia (jak `useCommandPalette.ts:126`) | `AppSidebarLayout.vue`, `CreateBudgetDialog.vue`, `Dashboard.vue`, `useFirstRunChecklist` | Bez wspólnego helpera; każde miejsce pamięta o reaktywności samo |
| D. Propsy layoutu: Inertia przekazuje `page.value.props` do komponentu layoutu przy każdym renderze (`dist/index.js:660`); `AppLayout` (Inertia ustawia `inheritAttrs=false` na komponencie layoutu, `:656`) mógłby zadeklarować `shell` jako prop i przekazać dalej | `AppLayout.vue`, `AppSidebarLayout.vue` | Nie sprawdzone w przeglądarce; zmienia kontrakt propsów layoutu, obejmuje tylko layout, a nie `Dashboard`/`useFirstRunChecklist`; reguła `inertia-props.md` mówi o propsach stron i kontrolerów, nie o layoutach |

Dla `useTranslations` (88 wywołań) wariant A oznaczałby `.value` wszędzie; wariant B byłby tu mniej inwazyjny — o tym, czy w ogóle ruszać `translations` w tej zmianie, decyduje plan (por. sekcja 4).

Ograniczenie z reguł: bez zmian wyglądu sidebara (zgłoszenie), `js-sidebar.md` — `BudgetYearSwitcher` i `AppSidebar` pozostają prezentacyjne, a `CreateBudgetDialog` zostaje w `AppSidebarLayout`, nie w `Sidebar` (`js-sidebar.md`, sekcja o aktywnym elemencie). `inertia-props.md` (para PHP + `defineProps` + `assertInertia` w jednym commicie) nie dotyczy tej poprawki, o ile nie zmienia się kształt propsu `shell`; kształt `ShellState` zostaje (`types/shell.ts:12-17`, `ShellState.php:26-47`).

## Code References

- `resources/js/composables/useTranslations.ts:5-15` — `useTranslations()` i `useShell()` zwracają jednorazową migawkę `page.props.*`
- `resources/js/composables/useTranslations.ts:17-42` — `useFirstRunChecklist`, `computed` nad migawką
- `resources/js/layouts/app/AppSidebarLayout.vue:14,25-28,38-42` — migawka `shell` w trwałym layoucie, przekazana do `AppSidebar`; `CreateBudgetDialog` zamontowany w layoucie
- `resources/js/layouts/AppLayout.vue`, `resources/js/app.ts:12-23` — wybór trwałego layoutu
- `resources/js/components/CreateBudgetDialog.vue:46,59,67,75` — druga migawka w trwałym layoucie
- `resources/js/components/BudgetYearSwitcher.vue:29-35,60,64` — `segments` jako `computed` nad propsami; `router.post(BudgetController.selectYear().url, …)`
- `resources/js/pages/Dashboard.vue:37,38,93,119` — migawka w komponencie strony
- `resources/js/composables/useCommandPalette.ts:37,79-85,126-135` — czytanie `page.props.shell` w chwili wywołania; kategorie ładowane przy każdym otwarciu
- `node_modules/@inertiajs/vue3/dist/index.js:521,599-600,655-668,685-688` — `page` jako `ref`, podmiana strony, `key`, render layoutu, `usePage`
- `app/Support/ShellState.php:26-47`, `app/Http/Middleware/HandleInertiaRequests.php:48-49` — świeży `shell` w każdej odpowiedzi; `translations` jako `fn`
- `app/Http/Controllers/BudgetController.php:109,116,137-148` — `store` i `selectYear` zapisują rok w sesji i przekierowują
- `resources/js/lib/budget-year-selection.test.ts:1-9` — wzorzec `vi.mock('@inertiajs/vue3')`
- `vite.config.ts:55-57` — konfiguracja testów (tylko `include`)
- `tests/e2e/sidebar-current-section.spec.ts`, `tests/e2e/account-edit-keeps-bank.spec.ts:6,28,88` — wzorce E2E dla sidebara i sprzątania własnych danych
- `context/foundation/test-stack.md` — komendy i warunki Playwright

## Architecture Insights

- Konwencja projektu to już reaktywny odczyt tam, gdzie autor o tym pamiętał (`NavUser.vue:19`, `useCurrentUrl.ts:25-32`, `useCommandPalette.ts:126`); błąd siedzi w jedynym współdzielonym helperze `useShell`, który tę konwencję łamie.
- Trwały layout + jednorazowy odczyt `page.props` to klasa błędu, nie pojedynczy przypadek: każdy komponent trwale zamontowany w `AppSidebarLayout` (i `useTranslations` w nim) jest narażony.
- Reguły `.ai/rules/js-sidebar.md` opisują układ, ale nie wspominają o reaktywności danych `shell`; po poprawce warto tam zapisać regułę (przez `record-rule` zgodnie z `AGENTS.md`).

## Historical Context (from prior changes)

- `context/archive/2026-11-06-sidebar-action/reviews/impl-review.md` (F9, linia 135-143 i podsumowanie ~184): ta sama klasa wady została zauważona i **celowo zostawiona** dla `AppSidebarLayout` i `CreateBudgetDialog`; naprawiono tylko `nextBudgetYear` w palecie (commit `fc45ffa`). Wtedy „not reproduced in a browser”. Ta analiza potwierdza zaobserwowany skutek w przeglądarce; część dotycząca migawki w palecie jest już naprawiona.
- `context/archive/2026-11-06-sidebar-action/research.md` (linie 92, 112): `shell.budgetYear` i `shell.budgetYears` pochodzą z sesji i z listy lat użytkownika; testy współdzielonych propsów w `tests/Feature/AppShellTest.php`, `BudgetTest.php:210-258`. Serwerowa strona kontraktu jest pokryta.
- Commit `cc5a14b` (`sidebar-ui-contract` p1) wprowadził `const shell = useShell()` do `AppSidebarLayout.vue` (znalezione przez `git log -S`). Nie badałem, dlaczego wcześniej tego nie zauważono ani czy przed zmianą sidebar czytał dane inaczej.

## Related Research

- `context/archive/2026-11-06-sidebar-action/research.md` — kontekst przełącznika roku, palety i `CreateBudgetDialog`
- `context/archive/2026-11-03-sidebar-ui-contract/research.md` — wcześniejszy kontrakt sidebara

## Open Questions

1. Wariant poprawki (A/B/C/D z sekcji 6) i czy zakres obejmuje `useTranslations` — decyzja planu.
2. Poziom regresji: sam E2E, E2E + test composable z `vi.mock`, czy dodanie zależności do testów komponentów (wymaga zgody użytkownika).
3. Czy po poprawce pokazanie rzeczywistego `CreateBudgetDialog` z layoutu po utworzeniu roku (lista źródeł kopiowania, domyślny rok) ma własną asercję E2E — skutek wywnioskowany z kodu, nie obserwowany.
4. Nie sprawdzono, czy migawka `shell` w `Dashboard.vue`/`useFirstRunChecklist` rzeczywiście staje się nieaktualna przy jakiejkolwiek obecnej wizycie z `preserveState` — według przeszukanych plików nie ma takiej wizyty na Dashboardzie.
