# Dashboard UI contract pass — Implementation Plan

## Overview

Przeprowadzamy jeden widok, Dashboard (`resources/js/pages/Dashboard.vue` i komponenty renderowane tylko przez niego), przez kontrakt 10x-ui. Najpierw powstaje kitchen sink i brakujące elementy biblioteki. Potem dochodzi wartość kontraktu dla podpisów kolumn, poprawki zarzutów C1–C4 w widoku i wypełniona macierz 7 stanów, łącznie z komunikatem błędu dla C5. Na końcu zabezpieczenie: skan wartości zakodowanych na sztywno w `composer ci:check` i reguła dla agentów. Wejście: `research.md` (`## Charges` C1–C5) i decyzje z sesji planowania.

## Current State Analysis

- **Tokeny są w porządku, kompozycja nie.** Skan 10x-ui daje 2 trafienia w 8 plikach: `text-[11px]` w `CategoryMeterRow.vue:56,65`. Dryf siedzi w powtarzanych kafelkach, helperach tonu, ręcznych pigułkach i typografii podpisów (research, „Liczby per plik”).
- **C1:** trzy kafelki to ręczne kopie `Card` → `CardDescription` → `CardTitle text-3xl tabular-nums` (`Dashboard.vue:252-320`). Jest sześć helperów „znak → kolor”: `Dashboard.vue:141-160` (zero → `text-success`), `BudgetPlanSummaryStrip.vue:16-26` i `DashboardHoldings.vue:91-113` (zero bez koloru). `is_negative` kont i lokat to dokładnie `current_balance_minor_units < 0` (`app/Models/Account.php:127-130`).
- **C2:** pusty panel kont to dwa zdania bez akcji (`DashboardHoldings.vue:155-166`). Link „Otwórz konta” jest tylko w nagłówku od `lg` (`:122`). Na telefonie panel pusty jest rozwinięty (`holdingsOpenOnMobile`, `:73-74`). Stan „brak budżetu” (`Dashboard.vue:399-418`) ma akcję, ale nie używa `IndexEmptyState`. W szkicu sekcje kategorii znikają bez tekstu (`Dashboard.vue:430`, `DashboardController.php:89-110`).
- **C3:** podpisy Plan/Wykonanie w `CategoryMeterRow.vue:56,65` mają `md:hidden` w komórkach `max-md:hidden`, więc są martwe. „Pozostało” ma `hidden` (`:80-82`). `.category-meter-header-row` ma `display: none` poniżej `md` (`app.css:447-449`). Podpisy kolumn mają cztery warianty typografii (`DashboardHoldings.vue:179,236`, `DashboardCategorySection.vue:87`, `BudgetPlanSummaryStrip.vue:34`). Kontrakt `design-system.md:16` to 11 px wersalikami, a w `app.css:207` ten rozmiar żyje tylko jako literał.
- **C4:** fokus kart powiadomień to `focus-visible:ring-2 focus-visible:ring-ring` (`DashboardNotices.vue:312`), a kontrakt mówi o 3 px `ring/50` (`design-system.md:15`, `components/ui/button/index.ts:6`). Pigułki wagi są zbudowane ręcznie (`DashboardNotices.vue:274-292`). `Badge` (`components/ui/badge/index.ts`) ma tylko `default/secondary/destructive/outline`.
- **C5:** trzy kontrolki POST `budget.select-year` (`Dashboard.vue:271-290,365-383`, `DashboardNotices.vue:297-311`) przy błędzie nic nie pokazują. Rok jest stanem sesji (`SelectedBudgetYear.php:24-34`).
- **Narzędzia:** nie ma kitchen sinka, Playwrighta ani testów zrzutów. Frontend ma `vp test` (`*.test.ts` obok kodu, import z `vite-plus/test`, importy względne) i `npm run check` (lint, ostrzeżenia blokują). `composer ci:check` uruchamia `npm run check`, `types:check`, `npm test` i testy PHP. CI wywołuje go po `npm ci && npm run build`.
- **Reguły agentów:** `.ai/rules/js.md` (Vue) i `.ai/rules/css.md` (`app.css`), indeks w `.ai/rules/index.md`. `CLAUDE.md` zawiera tylko blok 10x-cli, nadpisywany przez CLI.
- **Tłumaczenia:** `lang/pl/ui.php` (jedyny locale) oraz ręcznie pisany typ `UiTranslations` w `resources/js/types/shell.ts:21`.
- **Toasty:** `vue-sonner` (`<Toaster>` w `layouts/app/AppSidebarLayout.vue` i `AppHeaderLayout.vue`, wzorzec w `lib/flashToast.ts`).

## Desired End State

- Dashboard jest zbudowany z tokenów i komponentów repo. Kafelki KPI to jeden `StatTile`. Ton kwoty w całym widoku i w pasku planu pochodzi z jednego helpera: > 0 → `text-success`, 0 → bez koloru, < 0 → `text-destructive`. Pigułki wagi to `Badge`. Fokus kart powiadomień wygląda jak fokus przycisków.
- Na telefonie listy kategorii mają wiersz nagłówka „Kategoria · Pozostało”, a czytnik ekranu dostaje nazwę kwoty pozostałej. Podpisy kolumn w całym widoku mają jedną klasę (`column-caption`).
- Puste stany (brak kont, brak budżetu, szkic) używają `IndexEmptyState`. Pusty panel kont ma akcję, a szkic mówi, dlaczego nie widać kategorii.
- Nieudany POST wyboru roku pokazuje toast z komunikatem.
- `/dev/ui/dashboard` (tylko środowisko `local`) pokazuje widok we wszystkich scenariuszach. W folderze zmiany są zrzuty „przed” i „po” (desktop i 390 px), a macierz 7 stanów jest wypełniona.
- `npm run check:ui` jest częścią `composer ci:check` i nie przepuszcza literałów kolorów, klas palety ani wartości arbitralnych w px/rem w plikach Dashboardu. `.ai/rules/js.md` i `.docs/design-system.md` opisują kontrakt.

Weryfikacja: `composer ci:check` przechodzi, skan na plikach widoku daje 0 trafień, a człowiek ogląda kitchen sink i zrzuty.

### Key Discoveries:

- Precedens rozszerzania `components/ui` dla całej aplikacji: `Alert variant="warning"` (`components/ui/alert/index.ts:16`, commit 3c80780).
- `components/ui/*` jest wyłączony z lint i fmt (`vite.config.ts`, `lint.ignorePatterns`, `fmt.ignorePatterns`). Trzeba ręcznie zachować styl pliku (podwójne cudzysłowy, bez średników).
- `IndexEmptyState` (`components/IndexEmptyState.vue`) przyjmuje `title`, `description?` i slot `actions`.
- Vue 3.3+ obsługuje importowany typ w `defineProps<T>()`, więc typ propsów strony można przenieść do `resources/js/types/` i użyć go w fixture'ach kitchen sinka.
- Po dodaniu trasy trzeba uruchomić `npm run build` przed `npm run types:check`, bo Wayfinder generuje akcje (`AGENTS.md`, „Wayfinder”).

## What We're NOT Doing

- **C5: rok budżetu w URL** (np. `month-plan?year=`). Zmienia architekturę roku powłoki (Budżet, Plan miesiąca, Agenda, `BudgetYearSwitcher`) i wymaga własnej zmiany oraz ADR. Kontrolki zostają jako POST; dodajemy tylko komunikat błędu.
- **Wiersze kont i lokat jako linki do Operacji** (`operations?account_id=`, `term_deposit_id`). To zmiana zachowania, odroczona.
- **`column-caption` w innych widokach** (`Operations.vue:232,272,305`, `MonthPlan.vue:697`, `OperationsImportPreview.vue:301`, `ImportPreviewRow.vue:296+`, `PasskeyItem.vue:47`). Ta zmiana dotyczy jednego widoku; reszta to kolejne przebiegi 10x-ui. Zmieniamy tylko `.list-surface-table thead th`, który już ma ten wygląd, więc tabele nie zmieniają wyglądu.
- **Helper tonu w `Accounts.vue:58,75` i `TermDeposits.vue:75`.** Odroczone do przebiegów tych widoków.
- **Hover `hover:bg-foreground/[0.03]` na wierszach.** To udokumentowana konwencja list, pod-zarzut odrzucony w researchu.
- **Układ kafelków** (siatka, kolejność). Poza zakresem według `.ai/skills/openship-ui-development/SKILL.md:55`. Zmienia się tylko to, z czego są zbudowane.
- **Skeletony i stan loading.** Strona nie ma deferred props, a spec je wyklucza (`dashboard-hierarchy.md:78`).
- **Playwright / `toHaveScreenshot`.** Nie instalujemy narzędzia tylko pod bramkę.
- **Etykiety Plan/Wykonanie na telefonie.** Te komórki są poniżej `md` ukryte z założenia (historyjka 23), więc martwe podpisy usuwamy, nie ożywiamy.

## Implementation Approach

Kolejność według routera 10x-ui: środowisko i biblioteka → wartości kontraktu → jeden widok → stany → zabezpieczenie. Kitchen sink powstaje w fazie 1, żeby zrobić zrzuty „przed”, zanim cokolwiek się zmieni. Każda kolejna faza wizualna jest oglądana w nim i kończy się ponownym skanem. Nowe elementy biblioteki (`amountToneClass`, `StatTile`, warianty `Badge`) dochodzą najpierw i jeszcze nie są używane. Wymiana w widoku następuje w fazie 3, więc fazy 1–2 nie zmieniają wyglądu Dashboardu. Wyjątkiem jest `.list-surface-table`, którego wygląd zostaje identyczny.

## Critical Implementation Details

**`@apply` własnej klasy w Tailwind v4.** Żeby `.list-surface-table thead th` mógł użyć `column-caption` przez `@apply`, klasa musi być zarejestrowana przez `@utility column-caption { … }`, a nie jako zwykła reguła w `@layer components`.

**Grubość pierścienia fokusu bez wartości arbitralnej.** Kontrakt 3 px zapisujemy jako `focus-visible:ring-3` (Tailwind v4 przyjmuje liczby w szerokości ringu), nie `ring-[3px]`. Ten drugi zapis łapie wzorzec `-\[[0-9.]+(px|rem)\]` skanu z fazy 5.

**Pierwsze zrzuty przed fazą 3.** Zrzuty „przed” muszą powstać na kodzie widoku sprzed fazy 3, bo potem nie ma już czego porównać.

## Phase 1: Kitchen sink i biblioteka

### Overview

Dodajemy stronę dev-only, która renderuje Dashboard w każdym scenariuszu danych, i robimy na niej zrzuty „przed”. Dochodzą elementy biblioteki potrzebne w fazie 3: helper tonu kwoty, `StatTile` i warianty `Badge`. Widok Dashboardu jeszcze się nie zmienia.

### Changes Required:

#### 1. Typ propsów strony Dashboard

**File**: `resources/js/types/dashboard.ts` (nowy), `resources/js/pages/Dashboard.vue`

**Intent**: przenosimy typy `AccountSummary`, `TermDepositSummary`, `BudgetSummary`, `PlanSummary` i propsy strony do modułu typów. Dzięki temu fixture'y kitchen sinka są sprawdzane przez `vue-tsc` względem tego samego kontraktu co strona.

**Contract**: eksport `DashboardPageProps` (pola dokładnie jak dziś w `Dashboard.vue:73-93`) oraz typów składowych. `Dashboard.vue` używa `defineProps<DashboardPageProps>()`. `DashboardHoldings.vue` importuje `AccountSummary` i `TermDepositSummary` stąd, zamiast trzymać własne kopie. Strona PHP bez zmian.

#### 2. Kontroler i trasa kitchen sinka

**File**: `app/Http/Controllers/Dev/DashboardKitchenSinkController.php` (nowy), `routes/web.php`

**Intent**: strona dostępna tylko lokalnie, za logowaniem (żeby działały wspólne propsy powłoki: `shell`, `translations`), dla każdego innego środowiska 404.

**Contract**: `GET dev/ui/dashboard`, nazwa `dev.ui.dashboard`, w grupie `auth` + `verified`. Kontroler wywołuje `abort_unless(app()->isLocal(), 404)` i renderuje Inertia `dev/DashboardKitchenSink` bez własnych propsów.

#### 3. Strona kitchen sinka i fixture'y

**File**: `resources/js/pages/dev/DashboardKitchenSink.vue` (nowy), `resources/js/pages/dev/dashboard-fixtures.ts` (nowy)

**Intent**: jeden ekran, na którym widać widok Dashboard w każdym scenariuszu danych naraz, z nagłówkiem nad każdym scenariuszem. To bramka wizualna i dowód do przeglądu.

**Contract**: fixture'y typu `DashboardPageProps` dla scenariuszy:
- `default`: budżet w realizacji, konta i lokaty, kategorie z wartościami dodatnimi, powiadomienia `exceeded` i `warning`;
- `zero-amounts`: płynność, środki i „do wydania” = 0, saldo planu 0, konto z saldem 0;
- `negative`: ujemne kwoty w kafelkach i na koncie, kategoria ponad limit;
- `empty-new-user`: brak kont, lokat i budżetu;
- `no-budget`: konta są, budżetu brak;
- `draft`: budżet w szkicu, pusty `incomeCategories` / `expenseCategories`, `planSummary` ustawione;
- `no-categories`: budżet w realizacji bez kategorii.

Strona renderuje komponent `Dashboard.vue` z każdym fixture'em. Elementy zależne od prawdziwej powłoki (checklista pierwszego uruchomienia, `shell.budgetYears`) pokazują stan zalogowanego użytkownika, co trzeba zaznaczyć w nagłówku strony. Zduplikowane `year-input-id` w `CreateBudgetDialog` są tu dopuszczalne.

#### 4. Helper tonu kwoty

**File**: `resources/js/lib/amount-tone.ts` (nowy), `resources/js/lib/amount-tone.test.ts` (nowy)

**Intent**: jedno źródło reguły koloru kwoty ze znakiem (decyzja C1: neutralne zero).

**Contract**: `amountToneClass(minorUnits: number): 'text-success' | 'text-destructive' | ''`. Wynik: `> 0` → `'text-success'`, `0` → `''`, `< 0` → `'text-destructive'`. Test obejmuje 1, 0, −1 i dużą wartość ujemną.

#### 5. Komponent kafelka KPI

**File**: `resources/js/components/StatTile.vue` (nowy)

**Intent**: zastępuje trzy ręczne kopie kafelka z `Dashboard.vue:252-320` (C1).

**Contract**: propsy `label: string`, `amountMinorUnits: number`. Kwota przez `formatMoney`, `text-3xl tabular-nums`, ton z `amountToneClass`. Sloty: `label-extra` (np. `Badge` „Propozycja” obok etykiety) i `footer` (np. akcja „Plan miesiąca” w `CardContent class="pt-0"`, renderowany tylko gdy slot jest podany). Budowa na `Card` / `CardHeader` / `CardDescription` / `CardTitle`, bez nowych klas kolorów.

#### 6. Warianty `Badge`

**File**: `resources/js/components/ui/badge/index.ts`

**Intent**: pigułki wagi z jednego źródła dla całej aplikacji (decyzja C4), tak jak `Alert variant="warning"`.

**Contract**: dwa nowe warianty w `badgeVariants`: `warning` (`border-transparent bg-warning/10 text-warning`) i `destructive-soft` (`border-transparent bg-destructive/10 text-destructive`). Istniejące warianty bez zmian, styl pliku zachowany (plik jest poza lint i fmt).

#### 7. Zrzuty „przed”

**File**: `context/changes/dashboard-ui-contract/screenshots/before/` (nowy katalog)

**Intent**: punkt odniesienia dla bramki, zrobiony przed jakąkolwiek zmianą widoku.

**Contract**: zrzuty kitchen sinka i prawdziwego `/dashboard` (demo użytkownik z `DemoHouseholdSeeder`) przy szerokości desktopowej (1440 px) i 390 px, w trybie jasnym i ciemnym, nazwane `<scenariusz>-<szerokość>-<motyw>.png`.

### Success Criteria:

#### Automated Verification:

- Test helpera tonu przechodzi: `npm test`
- Lint i format przechodzą: `npm run check`
- Typy przechodzą po przebudowie Wayfindera: `npm run build && npm run types:check`
- Test Feature kitchen sinka przechodzi (404 w `testing`, strona `dev/DashboardKitchenSink` przy `local`, gość przekierowany do logowania): `./vendor/bin/sail artisan test --compact tests/Feature/DashboardKitchenSinkTest.php`
- Istniejące testy Dashboardu przechodzą bez zmian: `./vendor/bin/sail artisan test --compact tests/Feature/DashboardTest.php`

#### Manual Verification:

- `/dev/ui/dashboard` lokalnie pokazuje wszystkie 7 scenariuszy z nagłówkami, a Dashboard wygląda tak samo jak przed fazą
- Zrzuty „przed” (desktop i 390 px, jasny i ciemny) są w `screenshots/before/`

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 2: Wartości tokenów

### Overview

Dodajemy brakującą wartość kontraktu (typografia podpisu kolumny) i zapisujemy w `design-system.md` reguły ustalone w tej zmianie. Wygląd tabel się nie zmienia.

### Changes Required:

#### 1. Klasa podpisu kolumny

**File**: `resources/css/app.css`

**Intent**: zamienić literał 11 px na jedną nazwaną klasę (C3), do użycia w nagłówkach kolumn i podpisach mobilnych.

**Contract**: `@utility column-caption`: `muted-foreground`, 11 px, `font-medium`, `tracking-wider`, `uppercase`, czyli dokładnie dzisiejszy styl `app.css:207`. `.list-surface-table thead th` korzysta z niej przez `@apply column-caption` (zostają `pb-2.5 text-left`), więc wygląd tabel jest identyczny. Komentarz przy bloku wskazuje `design-system.md`.

#### 2. Notatki systemu projektowego

**File**: `.docs/design-system.md`

**Intent**: zapisać kontrakt w repo, żeby kolejna sesja nie wymyślała go od nowa.

**Contract**:
- „Listy i tabele”: nagłówki kolumn i podpisy mobilne przez `column-caption`.
- Tabela „Kolor znaczy coś…” / „Kwoty”: kwota ze znakiem przez `amountToneClass` (`> 0` success, `0` bez koloru, `< 0` destructive). Zero nigdy nie jest zielone.
- „Alerty”: pigułki wagi to `Badge variant="destructive-soft"` / `variant="warning"`.
- „Gotowe elementy”: dopisany `StatTile`.

### Success Criteria:

#### Automated Verification:

- Lint, format i sortowanie klas Tailwind przechodzą: `npm run check`
- Build CSS przechodzi: `npm run build`

#### Manual Verification:

- Nagłówki tabel na `/accounts` i `/term-deposits` (`.list-surface-table`) wyglądają tak samo jak przed zmianą
- `design-system.md` opisuje `column-caption`, regułę zera, warianty `Badge` i `StatTile`

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 3: Jeden widok (C1–C4)

### Overview

Przebudowujemy widok na elementach z faz 1–2: kafelki, ton kwot, pigułki, fokus, podpisy kolumn i puste stany. Pasek planu (`BudgetPlanSummaryStrip`) jest też na stronie Budżet, więc jego zmiany (zero bez koloru już jest, typografia podpisu) widać i tam.

### Changes Required:

#### 1. Kafelki i ton kwot (C1)

**File**: `resources/js/pages/Dashboard.vue`, `resources/js/components/BudgetPlanSummaryStrip.vue`, `resources/js/components/DashboardHoldings.vue`

**Intent**: trzy kafelki z jednego komponentu, ton kwoty z jednej reguły. Zero przestaje być zielone.

**Contract**:
- `Dashboard.vue`: trzy kafelki to `StatTile`. Kafelek „do wydania” dostaje `Badge` „Propozycja” w `label-extra`, a formularz „Plan miesiąca” w `footer`. Usuwamy `monthToSpendClass`, `spendableLiquidityClass` i `totalFundsClass`.
- `BudgetPlanSummaryStrip.vue`: `balanceClass` zastąpiony przez `amountToneClass`.
- `DashboardHoldings.vue`: `amountClass` i `groupTotalClass` zastąpione przez `amountToneClass(current_balance_minor_units)` / `amountToneClass(total)`. Równoważne, bo `is_negative` ≡ saldo < 0.
- Siatka i kolejność kafelków bez zmian.

#### 2. Pigułki wagi i fokus kart powiadomień (C4)

**File**: `resources/js/components/DashboardNotices.vue`

**Intent**: pigułki z `Badge`, fokus kart jak na przyciskach obok.

**Contract**: dwie ręczne pigułki (`:274-292`) to `<Badge variant="destructive-soft">` i `<Badge variant="warning">`, z ikoną i `tabular-nums`. Treść i warunki (`exceededCount > 0`, `warningCount > 0`) bez zmian. Klasy fokusu karty (`:312`): `focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-3 focus-visible:outline-none` zamiast `focus-visible:ring-2 focus-visible:ring-ring`.

#### 3. Podpisy kolumn i nagłówek kategorii na telefonie (C3)

**File**: `resources/css/app.css`, `resources/js/components/CategoryMeterRow.vue`, `resources/js/components/DashboardCategorySection.vue`, `resources/js/components/DashboardHoldings.vue`, `resources/js/components/BudgetPlanSummaryStrip.vue`

**Intent**: na telefonie kwota w wierszu kategorii ma podpis, a podpisy kolumn w widoku mają jedną typografię.

**Contract**:
- `app.css`: `.category-meter-header-row` jest widoczny poniżej `md` jako siatka dwóch kolumn (`minmax(0, 1fr) auto`, jak `'category remaining'` w wierszu). Od `md` zostaje dzisiejszy układ sześciu kolumn.
- `DashboardCategorySection.vue:87`: nagłówek używa `column-caption` zamiast `text-xs … tracking-wide uppercase`. Komórki Plan, Wykonanie, pasek i % mają `max-md:hidden`, więc na telefonie widać „Kategoria” i „Pozostało” (wyrównane do prawej). Wiersz sumy dostaje w komórce pozostałej podpis `sr-only` „Pozostało”.
- `CategoryMeterRow.vue`: usuwamy martwe podpisy Plan/Wykonanie (`:56`, `:65`); znikają oba `text-[11px]`. Podpis „Pozostało” (`:80-82`) zmienia `hidden` na `sr-only`.
- `DashboardHoldings.vue:179,236` i `BudgetPlanSummaryStrip.vue:34`: podpisy kolumn przez `column-caption`.

#### 4. Puste stany (C2)

**File**: `resources/js/components/DashboardHoldings.vue`, `resources/js/pages/Dashboard.vue`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: puste stany mówią, co się stało i co zrobić dalej, na każdej szerokości.

**Contract**:
- Brak kont i lokat (`DashboardHoldings.vue:155-166`): `Card` z `IndexEmptyState`, `title` = `t.dashboard.no_accounts`, `description` = `t.accounts.empty`. W slocie `actions` jest `Button` z `Link` do `accountsIndex()` (`t.dashboard.open_accounts`), widoczny na każdej szerokości.
- Brak budżetu (`Dashboard.vue:399-418`): `Card` z `IndexEmptyState`, `title` = `noBudgetMessage`. W slocie `actions` jest dzisiejszy `CreateBudgetDialog` z przyciskiem `t.budget.create_budget`.
- Szkic (`budget.phase === 'draft'`): pod `BudgetPlanSummaryStrip` jest `IndexEmptyState` (w `Card`) z nowymi kluczami `dashboard.draft_categories_title` = „Budżet jest w szkicu” i `dashboard.draft_categories_description` = „Kategorie i ich wykonanie pojawią się tutaj po rozpoczęciu realizacji budżetu.”. Bez akcji, bo „Otwórz budżet” jest w nagłówku sekcji tuż nad nim.
- Nowe klucze w `lang/pl/ui.php` i w `UiTranslations` (`types/shell.ts`).

#### 5. Zrzuty i skan po fazie

**File**: `context/changes/dashboard-ui-contract/screenshots/after-phase-3/`

**Intent**: porównanie z „przed” po każdej fazie wizualnej (router 10x-ui, krok 4).

**Contract**: te same scenariusze, szerokości i motywy co w fazie 1.

### Success Criteria:

#### Automated Verification:

- Lint i format przechodzą: `npm run check`
- Typy przechodzą: `npm run types:check`
- Testy frontendu przechodzą: `npm test`
- Testy Dashboardu przechodzą: `./vendor/bin/sail artisan test --compact tests/Feature/DashboardTest.php`
- Skan wartości zakodowanych na sztywno daje 0 trafień w plikach widoku (`Dashboard.vue`, `DashboardHoldings.vue`, `DashboardNotices.vue`, `DashboardCategorySection.vue`, `BudgetPlanSummaryStrip.vue`, `CategoryMeterRow.vue`, `CreateBudgetDialog.vue`, `StatTile.vue`) — wzorzec grep z 10x-ui
- W widoku nie ma już helperów tonu poza `amountToneClass`: `grep -nE "monthToSpendClass|spendableLiquidityClass|totalFundsClass|balanceClass|groupTotalClass|function amountClass" resources/js/pages/Dashboard.vue resources/js/components/{BudgetPlanSummaryStrip,DashboardHoldings}.vue` nic nie zwraca

#### Manual Verification:

- W scenariuszu `zero-amounts` żadna kwota 0,00 zł nie jest zielona (kafelki, suma kont, saldo planu); `default` i `negative` mają zielone dodatnie i czerwone ujemne
- Przy 390 px listy kategorii mają nagłówek „Kategoria · Pozostało”, a nad wierszem sumy jest ten sam nagłówek
- Czytnik ekranu (lub drzewo dostępności w DevTools) czyta „Pozostało” przy kwocie w wierszu kategorii i w sumie
- Pigułki „Przekroczone · n” i „Ostrzeżenia · n” wyglądają jak w zrzucie „przed” (dopuszczalna różnica: ikona 12 px zamiast 14 px, padding `Badge`)
- Fokus klawiatury (Tab) na karcie powiadomienia wygląda jak na przycisku „Otwórz budżet” (3 px, `ring/50`)
- Puste stany `empty-new-user`, `no-budget` i `draft` pokazują tytuł, opis i akcję; przy 390 px link „Otwórz konta” jest widoczny w pustym panelu
- Strona Budżet (`/budget`): pasek planu ma podpisy 11 px i neutralne saldo 0, poza tym bez zmian
- Zrzuty `after-phase-3` porównane z `before`; każda różnica jest zamierzona i opisana w commicie

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 4: Stany

### Overview

Wypełniamy macierz 7 stanów: dodajemy komunikat błędu dla trzech kontrolek POST wyboru roku (C5, bez zmiany architektury) i pokazujemy wszystkie stany w kitchen sinku lub w zrzutach z działającej aplikacji. Loading oznaczamy jako N/A z powodem.

### Changes Required:

#### 1. Komunikat przy nieudanym wyborze roku

**File**: `resources/js/lib/budget-year-selection.ts` (nowy), `resources/js/pages/Dashboard.vue`, `resources/js/components/DashboardNotices.vue`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: dziś nieudany POST `budget.select-year` zostawia użytkownika na Dashboardzie bez słowa. Ma pokazać toast.

**Contract**: `notifyBudgetYearSelectionFailed(message: string): void` wywołuje `toast.error(message)` z `vue-sonner` (wzorzec `lib/flashToast.ts`). Podpięte w obsłudze błędu dwóch `<Form>` w `Dashboard.vue` (`:271`, `:365`) i `Link` kart miesięcznych w `DashboardNotices.vue` (`:297`), po potwierdzeniu nazwy zdarzenia błędu `<Form>` i `<Link>` w dokumentacji Inertia v3 (Boost `search-docs`). Nowy klucz `dashboard.select_year_failed` = „Nie udało się otworzyć wybranego roku. Odśwież stronę i spróbuj ponownie.”. Zachowanie przy sukcesie bez zmian.

#### 2. Macierz stanów w kitchen sinku

**File**: `resources/js/pages/dev/DashboardKitchenSink.vue`

**Intent**: każda komórka macierzy jest pokazana albo oznaczona jako N/A z powodem.

**Contract**: na górze strony tabela macierzy (stan → gdzie go widać), a pod nią:
- przycisk „Pokaż błąd wyboru roku”, który wywołuje `notifyBudgetYearSelectionFailed` z prawdziwym kluczem;
- scenariusze z fazy 1 (default, empty, zero, negative, draft, no-categories, no-budget).

Stany zależne od interakcji (hover, focus-visible, disabled podczas POST) dokumentujemy zrzutami z działającej aplikacji. Loading: N/A, bo strona nie ma deferred props, a skeletony wyklucza `dashboard-hierarchy.md:78`. Jedyny „loading” to `processing`, pokazany jako disabled.

#### 3. Zrzuty stanów

**File**: `context/changes/dashboard-ui-contract/screenshots/after-phase-4/`, `context/changes/dashboard-ui-contract/states.md` (nowy)

**Intent**: dowód bramki do przeglądu.

**Contract**: `states.md` to tabela 7 stanów (stan, gdzie pokazany, plik zrzutu albo N/A z powodem). Zrzuty desktop i 390 px. Disabled kart powiadomień i przycisków z `processing` łapiemy przy spowolnionej sieci (DevTools, „Slow 3G”). Error: toast oraz `InputError` w `CreateBudgetDialog` (rok, który już ma budżet).

### Success Criteria:

#### Automated Verification:

- Lint i format przechodzą: `npm run check`
- Typy przechodzą: `npm run types:check`
- Testy frontendu przechodzą: `npm test`
- Testy Dashboardu i kitchen sinka przechodzą: `./vendor/bin/sail artisan test --compact tests/Feature/DashboardTest.php tests/Feature/DashboardKitchenSinkTest.php`
- Skan wartości zakodowanych na sztywno nadal daje 0 trafień w plikach widoku i w `resources/js/pages/dev/DashboardKitchenSink.vue`

#### Manual Verification:

- Nieudany POST wyboru roku (np. budżet usunięty w drugiej karcie, potem klik „Plan miesiąca”) pokazuje toast z komunikatem, a udany POST działa jak dotąd
- Karty powiadomień miesięcznych podczas POST są wyłączone i czytelne (`disabled:opacity-60`), w obu motywach
- `states.md` ma wszystkie 7 wierszy, każdy ze zrzutem albo z N/A i powodem
- Zrzuty `after-phase-4` są dla desktopu i 390 px, w jasnym i ciemnym motywie

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 5: Zabezpieczenie

### Overview

Zostawiamy kontrolę i regułę, które utrzymają kolejnego agenta przy kontrakcie, i zapisujemy odroczone zarzuty w researchu.

### Changes Required:

#### 1. Skrypt skanu

**File**: `scripts/check-ui-literals.mjs` (nowy), `package.json`, `composer.json`

**Intent**: nieudana kontrola zatrzymuje literał w oczyszczonych plikach, zanim przejdzie przez CI. Skrypt jest w Node, bez zależności i bez założenia o powłoce, więc działa tak samo na hoście, w obrazie Sail i w CI.

**Contract**:
- Skrypt ma listę plików objętych kontrolą (8 plików widoku z fazy 3 oraz `pages/dev/DashboardKitchenSink.vue`) i komentarz, że kolejne przebiegi 10x-ui ją rozszerzają. Argumenty CLI zastępują listę.
- Stosuje wzorzec skanu 10x-ui (hex, `rgb/rgba/hsl/hsla/oklch(`, `-[<n>px|rem]`, klasy palety Tailwind) do każdej linii. Wypisuje `plik:linia: dopasowanie` oraz wskazówkę „użyj tokenu z `resources/css/app.css` lub klasy komponentu — zob. `.docs/design-system.md`”. Kod wyjścia 1 przy trafieniu, 0 bez trafień.
- `package.json`: skrypt `"check:ui": "node scripts/check-ui-literals.mjs"`.
- `composer.json`: `ci:check` wywołuje `npm run check:ui` zaraz po `npm run check`.

#### 2. Reguła dla agentów

**File**: `.ai/rules/js.md` (przez Boost `record-rule` albo edycją pliku, zgodnie z `design-system.md`, „Zmiana systemu”)

**Intent**: kolejny agent wie, gdzie są tokeny i komponenty i że literały w widokach nie przejdą.

**Contract**: nowa sekcja (krótka, w stylu pliku):
- tokeny w `resources/css/app.css` (`:root` / `.dark` → `@theme inline`), komponenty bazowe w `components/ui/*`, domenowe w `components/`;
- przed nowym komponentem sprawdź `StatTile`, `IndexEmptyState` i listę „Gotowe elementy”;
- kwota ze znakiem przez `amountToneClass`, podpis kolumny przez `column-caption`, pigułka wagi przez `Badge` (`warning` / `destructive-soft`);
- bez literałów kolorów, klas palety i wartości arbitralnych w px/rem w widokach (`npm run check:ui` pilnuje oczyszczonych plików);
- kitchen sink pod `/dev/ui/dashboard`.

`CLAUDE.md` bez zmian (blok 10x-cli jest nadpisywany).

#### 3. Notatki systemu i odroczone zarzuty

**File**: `.docs/design-system.md`, `context/changes/dashboard-ui-contract/research.md`

**Intent**: zamknąć pętlę dokumentacji i nie zgubić zarzutów, których plan nie rozwiązuje.

**Contract**:
- `design-system.md`, „Zmiana systemu”: kitchen sink `/dev/ui/dashboard` i `npm run check:ui`.
- `research.md`, `## Charges`: przy C5 adnotacja **deferred** (rok w URL, osobna zmiana z ADR; w tej zmianie tylko toast). Przy uwadze o wierszach kont: **deferred** (zmiana zachowania). Dopisek o `column-caption` i `amountToneClass` w pozostałych widokach (lista z „What We're NOT Doing”) jako **deferred** do kolejnych przebiegów 10x-ui.

### Success Criteria:

#### Automated Verification:

- Kontrola przechodzi na oczyszczonych plikach: `npm run check:ui`
- Kontrola zatrzymuje literał: `node scripts/check-ui-literals.mjs resources/js/pages/Operations.vue` kończy się kodem 1 i wskazuje `text-[11px]`
- Lint i format przechodzą (także dla nowego skryptu): `npm run check`
- Pełna bramka CI przechodzi: `./vendor/bin/sail composer ci:check`

#### Manual Verification:

- `.ai/rules/js.md` ma nową sekcję, a `.ai/rules/index.md` nadal wskazuje ten plik dla `resources/js/**/*.vue`
- `research.md` oznacza C5, linki wierszy kont i pozostałe widoki jako deferred z powodem
- Przegląd z `references/ui-quality-checklist.md` (10x-ui): wszystkie pozycje zaznaczone albo uzasadnione

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka. Potem `/10x-impl-review dashboard-ui-contract`; ustaleń UI nie pomija się jako „kosmetycznych”.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/amount-tone.test.ts`: 1 → `text-success`, 0 → `''`, −1 → `text-destructive`, duża wartość ujemna → `text-destructive`.

### Integration Tests:

- `tests/Feature/DashboardKitchenSinkTest.php`: gość jest przekierowany do logowania; zalogowany użytkownik w środowisku `testing` dostaje 404; przy środowisku ustawionym na `local` (`$this->app['env'] = 'local'`) dostaje stronę Inertia `dev/DashboardKitchenSink`.
- `tests/Feature/DashboardTest.php` bez zmian: propsy strony się nie zmieniają, więc istniejące `assertInertia` pilnują kontraktu.

### Manual Testing Steps:

1. `/dev/ui/dashboard` lokalnie: przejrzeć każdy scenariusz przy 1440 px i 390 px, w jasnym i ciemnym motywie.
2. `/dashboard` jako demo użytkownik: Tab przez kafelki, powiadomienia i przyciski, sprawdzając jednolity fokus.
3. Przy spowolnionej sieci kliknąć kartę powiadomienia miesięcznego i „Plan miesiąca”, sprawdzając wygląd disabled.
4. Usunąć budżet w drugiej karcie, potem kliknąć „Otwórz budżet” w pierwszej i sprawdzić, czy pojawia się toast.
5. `/budget`: pasek planu z neutralnym zerem i podpisami 11 px.

## Performance Considerations

Bez wpływu: zmiany są w markupie i klasach. Kitchen sink renderuje stronę kilka razy, ale tylko w środowisku `local`.

## Migration Notes

Brak migracji danych i zmian propsów Inertia. Trasa `dev/ui/dashboard` zwraca 404 poza `local`. Skrypt `check:ui` dochodzi do `ci:check`, więc CI zacznie go uruchamiać po merge'u.

## References

- Research (zarzuty, liczby, macierz stanów): `context/changes/dashboard-ui-contract/research.md`
- Identyfikator zmiany: `context/changes/dashboard-ui-contract/change.md`
- Kontrakt 10x-ui i lista kontrolna: `.claude/skills/10x-ui/SKILL.md`, `.claude/skills/10x-ui/references/ui-quality-checklist.md`
- System projektowy: `.docs/design-system.md`, `resources/css/app.css:10-70,99-182,193-462`
- Reguły agentów: `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/rules/index.md`
- Wzorzec wariantu `components/ui`: `resources/js/components/ui/alert/index.ts:16`
- Wzorzec podpisu mobilnego: `resources/js/components/ImportPreviewRow.vue:296`
- Wzorzec toasta: `resources/js/lib/flashToast.ts`
- Precedens neutralnego zera: `context/archive/2026-11-02-month-items-plan-vs-actual/plan.md:28`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kitchen sink i biblioteka

#### Automated

- [x] 1.1 Test helpera tonu przechodzi: `npm test` — e84e65f
- [x] 1.2 Lint i format przechodzą: `npm run check` — e84e65f
- [x] 1.3 Typy przechodzą po przebudowie Wayfindera: `npm run build && npm run types:check` — e84e65f
- [x] 1.4 Test Feature kitchen sinka przechodzi (404 w `testing`, strona `dev/DashboardKitchenSink` przy `local`, gość przekierowany do logowania) — e84e65f
- [x] 1.5 Istniejące testy Dashboardu przechodzą bez zmian — e84e65f

#### Manual

- [x] 1.6 `/dev/ui/dashboard` lokalnie pokazuje wszystkie 7 scenariuszy z nagłówkami, a Dashboard wygląda tak samo jak przed fazą — e84e65f
- [x] 1.7 Zrzuty „przed” (desktop i 390 px, jasny i ciemny) są w `screenshots/before/` — e84e65f

### Phase 2: Wartości tokenów

#### Automated

- [x] 2.1 Lint, format i sortowanie klas Tailwind przechodzą: `npm run check`
- [x] 2.2 Build CSS przechodzi: `npm run build`

#### Manual

- [x] 2.3 Nagłówki tabel na `/accounts` i `/term-deposits` wyglądają tak samo jak przed zmianą
- [x] 2.4 `design-system.md` opisuje `column-caption`, regułę zera, warianty `Badge` i `StatTile`

### Phase 3: Jeden widok (C1–C4)

#### Automated

- [ ] 3.1 Lint i format przechodzą: `npm run check`
- [ ] 3.2 Typy przechodzą: `npm run types:check`
- [ ] 3.3 Testy frontendu przechodzą: `npm test`
- [ ] 3.4 Testy Dashboardu przechodzą
- [ ] 3.5 Skan wartości zakodowanych na sztywno daje 0 trafień w plikach widoku
- [ ] 3.6 W widoku nie ma już helperów tonu poza `amountToneClass`

#### Manual

- [ ] 3.7 W scenariuszu `zero-amounts` żadna kwota 0,00 zł nie jest zielona; `default` i `negative` mają zielone dodatnie i czerwone ujemne
- [ ] 3.8 Przy 390 px listy kategorii mają nagłówek „Kategoria · Pozostało”, także nad wierszem sumy
- [ ] 3.9 Czytnik ekranu czyta „Pozostało” przy kwocie w wierszu kategorii i w sumie
- [ ] 3.10 Pigułki wagi wyglądają jak w zrzucie „przed” (dopuszczalna różnica ikony i paddingu `Badge`)
- [ ] 3.11 Fokus klawiatury na karcie powiadomienia wygląda jak na przycisku „Otwórz budżet”
- [ ] 3.12 Puste stany `empty-new-user`, `no-budget` i `draft` pokazują tytuł, opis i akcję; przy 390 px link „Otwórz konta” jest widoczny
- [ ] 3.13 Strona Budżet: pasek planu ma podpisy 11 px i neutralne saldo 0, poza tym bez zmian
- [ ] 3.14 Zrzuty `after-phase-3` porównane z `before`; każda różnica zamierzona i opisana w commicie

### Phase 4: Stany

#### Automated

- [ ] 4.1 Lint i format przechodzą: `npm run check`
- [ ] 4.2 Typy przechodzą: `npm run types:check`
- [ ] 4.3 Testy frontendu przechodzą: `npm test`
- [ ] 4.4 Testy Dashboardu i kitchen sinka przechodzą
- [ ] 4.5 Skan wartości zakodowanych na sztywno nadal daje 0 trafień w plikach widoku i w kitchen sinku

#### Manual

- [ ] 4.6 Nieudany POST wyboru roku pokazuje toast, a udany działa jak dotąd
- [ ] 4.7 Karty powiadomień miesięcznych podczas POST są wyłączone i czytelne w obu motywach
- [ ] 4.8 `states.md` ma wszystkie 7 wierszy, każdy ze zrzutem albo z N/A i powodem
- [ ] 4.9 Zrzuty `after-phase-4` są dla desktopu i 390 px, w jasnym i ciemnym motywie

### Phase 5: Zabezpieczenie

#### Automated

- [ ] 5.1 Kontrola przechodzi na oczyszczonych plikach: `npm run check:ui`
- [ ] 5.2 Kontrola zatrzymuje literał: skrypt na `Operations.vue` kończy się kodem 1 i wskazuje `text-[11px]`
- [ ] 5.3 Lint i format przechodzą (także dla nowego skryptu): `npm run check`
- [ ] 5.4 Pełna bramka CI przechodzi: `./vendor/bin/sail composer ci:check`

#### Manual

- [ ] 5.5 `.ai/rules/js.md` ma nową sekcję, a `.ai/rules/index.md` nadal wskazuje ten plik
- [ ] 5.6 `research.md` oznacza C5, linki wierszy kont i pozostałe widoki jako deferred z powodem
- [ ] 5.7 Przegląd z `ui-quality-checklist.md`: wszystkie pozycje zaznaczone albo uzasadnione
