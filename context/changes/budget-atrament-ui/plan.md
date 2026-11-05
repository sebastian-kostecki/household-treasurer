# Widok Budżetu w systemie „C · Atrament i morski” — plan implementacji

## Overview

Widok `/budget` przechodzi na system „C · Atrament i morski” (artefakt https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, wersja 1793873124-2686; podgląd, nie źródło wartości). Zmiana realizuje punkty 3 i 5 z sekcji „Zmiany względem obecnej aplikacji” README systemu **globalnie**: panel `radius-lg` z pełnym `border`, kontrolki 32 px, `column-caption` → `table-header`. Na samym Budżecie zamyka zarzuty C1–C5 z `research.md`: typografia i kwoty mono bez „zł”, Switch, warianty Badge, panel miar z czwartą miarą i postępem roku, pasek realizacji w wierszach, usunięcie `BudgetMonthStrip` i jedna akcja „Dodaj kategorię”. Decyzje zapisujemy w ADR-0037 i dokumentach przed kodem.

## Current State Analysis

Pełne ustalenia: `context/changes/budget-atrament-ui/research.md` (zarzuty C1–C5 z kotwicami). Najważniejsze:

- Style `page-title`, `section-title`, `table-header`, `label-caps`, `meta`, `kpi`, `kpi-lg`, `amount`, `amount-sum` nie istnieją; nie ma `--control-height` ani `--row-height`. `--font-mono` jest zdefiniowane (`app.css:14`), ale nie używa go żaden plik Budżetu. `ui-contract.test.ts:11` zabrania literałów w plikach Budżetu, więc style muszą powstać w `app.css` przed Vue.
- `formatMoney` zawsze dopisuje „zł” (`resources/js/lib/format.ts:4-13`), więc „zł” stoi w każdej komórce tabeli (`BudgetAmountCell.vue`).
- Przepisy globalne: `ui/card/Card.vue:15` `rounded-2xl border-border/60 py-6`; `app.css:231-233` `@utility column-caption` (11 px wersaliki), dziedziczony przez `@apply` w `.column-header`, `.list-surface-table thead th`, `.budget-category-header-row`, `.operations-header-row`, `.import-preview-header-row`; wiersz sumy `bg-foreground/[0.02]` (`app.css:417-419`, sticky `:476-485`); hover `hover:bg-foreground/[0.03]` w sześciu regułach (`app.css:304,404,524,529,541,682`). `ui/button/index.ts:7,24-29`: baza `rounded-xl`, default `h-9`, sm `h-8`, icon `size-9`. `Input.vue:27` i `SelectTrigger.vue:24` `h-9 rounded-xl`, `.native-select` (`app.css:237`).
- Badge (`ui/badge/index.ts:13-30`) nie ma `ok`, `brand`, `neutral`; `ui/switch` nie istnieje (prymityw jest w `reka-ui`).
- `BudgetController::show` (`:33-133`) liczy odczyty miesiąca (`BudgetMonthReadings`, `BudgetMonthResult`, `MonthToSpend`) wyłącznie dla `BudgetMonthStrip`. `pacedPoolMonthRemainingMinorUnits` i `month_*` w `categoryPayload` (`:277-280`) są martwe już dziś. `BudgetMonthReadings` ma jednego wywołującego (ten kontroler). `BudgetMonthResult` nie ma własnego testu: jego przypadki sprawdza tylko `tests/Feature/BudgetMonthResultTest.php` przez propsy strony, a korzystają z niego `MonthSummary.php:39` i plan `period-report-by-category`.
- `BudgetMonthPicker` zostaje dla `MonthPlan.vue:539`; jego domyślna gałąź (trasa Budżetu + `showArchived`) staje się nieosiągalna.
- Postęp roku: `App\Support\YearElapsed::asOfDate($budgetYear, $today)` i `percent($asOf)` (CONTEXT.md:60 zakłada już Budżet wybranego roku). Procent wykonania wydatków jest w `expenseTotals.percent_complete`.
- Pasek z kreską roku istnieje tylko w `CategoryMeterRow.vue:80-110` (Dashboard): tor `bg-muted`, wypełnienie z `meterBarClass` (`bg-primary` w normie, `dashboard-categories.ts:81-99`), kreska `bg-foreground`. Reguła kreski: `showsYearElapsedMark` (`dashboard-categories.ts:53-62`).
- Akcje: dwa przyciski „Dodaj …” w nagłówkach paneli (`BudgetCategoryList.vue:204-211`) i dwa dialogi tworzenia (`Budget.vue:373-389`); `CategoryFormDialog` ma już pole typu (`:193-214`) i prop `default-type`.
- Szkic: tylko kolumna planu (`BudgetCategoryList.vue:268-274`), choć `month_item_kind` jest w payloadzie. Klucze `month_item_kind_bill` / `month_item_kind_running` istnieją w `lang/pl/ui.php:241-242`.
- Kitchen sink `/dev/kitchen-sink/budget` istnieje (`routes/web.php:33-35`, `pages/dev/BudgetKitchenSink.vue`), renderuje `BudgetMonthStrip` trzy razy i `Checkbox`.
- `scripts/check-ui-literals.mjs` nie obejmuje `pages/Budget.vue` ani większości komponentów Budżetu (lista w `research.md`, sekcja `check-ui-literals`); dziś są na nich czyste.

## Desired End State

- `/budget` w realizacji: tytuł `page-title` „Budżet RRRR” z odznaką `brand` „Realizacja” (`warning` „Szkic”), opis jednym zdaniem, po prawej jeden zarysowany „Dodaj kategorię” z ikoną, w szkicu dodatkowo primary „Rozpocznij realizację” i menu `…`. Pod nim jeden `StatPanel`: Planowane dochody (`kpi-lg`), Planowane wydatki, Planowane saldo i, poza szkicem, Wykonanie wydatków N% z podpisem „Postęp roku M%”. Brak paska miesiąca.
- Panele Dochody i Wydatki: nagłówek `section-title` z meta „wykonanie z planu” (w szkicu: suma planu), nagłówki kolumn `table-header` w jednej linii, kwoty JetBrains Mono bez „zł”, stopka „Kwoty w zł”, wiersz sumy na `muted` z `amount-sum`, hover `secondary`. W realizacji wiersz ma `ExecutionMeter` (tor `track`, wypełnienie `foreground` albo `destructive`, kreska `brand-text` wg `showsYearElapsedMark`, procent), a przekroczona kategoria wydatkowa ma Badge `destructive-soft` „przekroczono”. W szkicu kolumna „Typ” z Badge `neutral`: „dochód”, „rachunek”, „bieżąca”.
- „Pokaż zarchiwizowane” to Switch z `v-model`; archiwum zostaje osobną sekcją (CONTEXT.md:36), wiersze w `muted-foreground`, odznaka `neutral`.
- W całej aplikacji: Card `radius-lg` z pełnym `border`, Button default/icon 32 px, sm/icon-sm 26 px, `radius-md`; Input, SelectTrigger, `.native-select` 32 px i `radius-md`; nagłówki kolumn przez `table-header`; Dashboard używa tego samego `ExecutionMeter`.
- `BudgetController` nie wysyła propsów miesiąca, wysyła `year_elapsed_percent` (null w szkicu i bez budżetu); `BudgetMonthReadings`, `BudgetMonthStrip`, `BudgetMonthResultSummary` nie istnieją.
- ADR-0037 opisuje decyzje; ADR-0003, ADR-0033, ADR-0035, PRD, `.docs/design-system.md` i `.ai/rules` są z nim zgodne.
- Weryfikacja: `composer ci:check` (Sail), `vendor/bin/sloppy ci`, `npm run check`, `npm run check:ui`, `npm run types:check`, `npm test` zielone; zrzuty w obu motywach w `context/changes/budget-atrament-ui/screenshots/`.

### Key Discoveries:

- Wzorzec dodania stylu/koloru: `--name` w `:root`/`.dark` + `--color-name` w `@theme inline`, nazwane klasy w `@layer components` albo `@utility` (`.ai/rules/css.md`, `.ai/rules/js.md` „Views take values from app.css”).
- W obu motywach `primary` i `foreground` mają dziś tę samą wartość (`#0f1b2d` / `#e6ecf3`, ADR-0035), więc zmiana wypełnienia paska z `bg-primary` na `bg-foreground` jest semantyczna, nie wizualna.
- `vite.config.ts:71,94` wyłącza `resources/js/components/ui/*` z lint i fmt, więc wygenerowany Switch nie zawiedzie `npm run check`.
- `components.json` wskazuje nieistniejący `tailwind.config.js`; precedens CLI: `npx shadcn-vue@<wersja> add …` po sprawdzeniu wersji przez `npm view` (`context/changes/period-report-by-category/plan.md:277-280`).
- Konwencja ADR: angielski, `# Tytuł` + 1–3 akapity z „We rejected …”; częściowe zastąpienie to dopisana linia „Superseded in part by ADR-NNNN” w starym i „This supersedes in part …” w nowym (`0035-…md`).
- ADR-0036 trzyma `period-report-by-category`; 0037 jest wolny.

## What We're NOT Doing

- Punktów 7 i 8 README (kolor wydatku w Operacjach, wiersze dnia), palety ⌘K, promienia Dialog (`radius-xl`).
- Progu „blisko limitu” i stanu `warn` paska: domena go nie definiuje (`.ai/rules/support.md` wymaga terminu w CONTEXT.md przed kodem). Słowo „w normie” nie jest pokazywane.
- Przeniesienia wierszy archiwalnych do głównej tabeli (zmiana domeny CONTEXT.md:36 / FR-REP-15).
- Nowej powierzchni dla Month Result, Month Remaining i „do wydania” z linkiem do planu: przejmuje je raport okresu (S-11, `period-report-by-category`), Dashboard i Plan miesiąca. Do scalenia S-11 Month Result miesiąca bez zatwierdzonego planu nie ma w UI (zapisane w ADR-0037).
- Migracji `StatTile` na Dashboardzie, w Kontach i Lokatach na `StatPanel`, typografii (`page-title`, `kpi`, kwoty mono) i jednostki w stopce w innych widokach; dostaną własne `/10x-ui`. Globalnie zmieniają się tylko przepisy z punktów 3 i 5 oraz pasek Dashboardu (wspólny `ExecutionMeter`).
- `MonthPaceMeter` (kreska tempa) zostaje bez zmian.
- Ręcznych literałów `rounded-2xl` / `h-9` poza `components/ui` i `app.css` (`OperationsFilters.vue:389`, `MonthPlanEditBar.vue:31`, `ManagePasskeys.vue`, `DashboardNotices.vue`, `AppHeader.vue`, `AuthCardLayout.vue`, `AuthSimpleLayout.vue`): zostają do widoków, które je renderują.
- Testu pozytywnego renderu kitchen sinka w `local` (istniejąca luka).

## Implementation Approach

Faza 1 zapisuje decyzje, bo README systemu i `.docs/design-system.md` wymagają ADR przed kodem. Faza 2 zmienia przepisy i komponenty bazowe dla całej aplikacji i sprawdza je zrzutami innych widoków, zanim Budżet zacznie z nich korzystać. Faza 3 zmienia kontrakt propsów po obu stronach w jednym commicie (AGENTS.md, „Inertia props contract”): kontroler, typy i usunięcie paska z `Budget.vue`. Faza 4 przebudowuje widok na nowych stylach i komponentach, rozszerza kitchen sink i straż literałów oraz robi zrzuty. Między fazami 2 a 4 Budżet działa, ale wygląda pośrednio; nie scalamy do `main` przed Fazą 4.

## Faza 1: Decyzje i dokumenty

### Overview

ADR-0037 i zgodne z nim dokumenty, zanim zmieni się kod.

### Changes Required:

#### 1. ADR-0037

**File**: `.docs/adr/0037-panel-controls-table-header-and-budget-layout.md` (nazwa opisowa, numer 0037)

**Intent**: Zapisać decyzje globalne (punkty 3 i 5 README) i decyzje układu Budżetu wraz z odrzuconymi opcjami.

**Contract**: Treść po angielsku, format jak ADR-0035. Musi zawierać: (a) panel = `card`, pełny `border`, `radius-lg` (10 px), bez cienia, zamiast `rounded-2xl` + `border/60` (zastępuje część ADR-0003); (b) `--control-height` 32 px dla Button default/icon, Input, SelectTrigger, `.native-select`, `--control-height-sm` 26 px dla Button sm/icon-sm, promień `radius-md`; (c) `table-header` (Inter 600, 12/16, zwykła wielkość liter, jedna linia, `muted-foreground`) zastępuje `column-caption`, wersaliki (`label-caps`) tylko nad miarami i w sidebarze; (d) Budżet nie pokazuje odczytów miesiąca (Month Result, Month Remaining, „do wydania”): przejmuje je raport okresu (S-11), Dashboard i Plan miesiąca, a do scalenia S-11 Month Result miesiąca bez zatwierdzonego planu nie ma powierzchni (zastępuje część ADR-0033 o stronie Budżet); (e) pasek realizacji kategorii ma dwa stany (`foreground` w normie, `destructive` przy `is_over_limit` z Badge „przekroczono”), bez „blisko limitu” do czasu reguły w CONTEXT.md; (f) kwoty w tabelach bez jednostki, stopka „Kwoty w zł”. „We rejected”: zachowanie paska miesiąca na Budżecie, Month Result w Planie miesiąca, sam pasek bez słowa przy przekroczeniu, zmianę tylko przycisków bez pól, Dialog `radius-xl` w tej zmianie.

#### 2. Adnotacje starszych ADR

**File**: `.docs/adr/0003-*.md`, `.docs/adr/0033-*.md`, `.docs/adr/0035-*.md`

**Intent**: Wzajemne odsyłacze częściowego zastąpienia.

**Contract**: ADR-0003 i ADR-0033: linia „Superseded in part by ADR-0037” (obok istniejącej dla 0035 w 0003). ADR-0035: dopisek, że punkty 3 i 5 rozstrzyga ADR-0037. ADR-0037: zdanie „This supersedes in part ADR-0003 … and ADR-0033 …”.

#### 3. PRD

**File**: `.docs/prd.md:297-298`

**Intent**: FR-REP-13 i FR-REP-14 nie mogą już obiecywać odczytu na stronie Budżet.

**Contract**: FR-REP-13: usunąć „i na stronie Budżet”; FR-REP-14: dopisać powierzchnię (raport okresu, ADR-0037). Treść reguł obliczania bez zmian.

#### 4. Design system i reguły

**File**: `.docs/design-system.md`, `.ai/rules/js.md`, `.ai/rules/css.md`

**Intent**: Dokumenty opisują nowy przepis panelu, wysokości kontrolek, style typograficzne, `table-header`, warianty Badge, Switch, jednostkę w stopce i `ExecutionMeter`.

**Contract**: `design-system.md`: L3 (lista ADR), L14-19 (panel, 32/26 px, selecty `radius-md`, `table-header` zamiast `column-caption`), L24 (mono: Budżet wdrożony), L55-56 (jednostka w stopce, `formatMoneyAmount`), sekcja kitchen sinków / `check-ui-literals` (L79, L104-107), nowa tabela stylów typograficznych, „Gotowe elementy”: `StatPanel`, `ExecutionMeter`, Switch. `js.md`: reguła kwot (w tabelach `formatMoneyAmount` + `amount`, w miarach i dialogach `formatMoney`), reguła powierzchni (ADR-0037 zamiast „rounded-2xl with border/60”), obok reguły Checkbox zdanie o Switch (`v-model`, ustawienia zapisywane od razu), `table-header` jako jedyny przepis podpisu kolumn, Badge `ok`/`brand`/`neutral`, kreska `brand-text`, selecty `radius-md`, opis kitchen sinka Budżetu bez paska miesiąca. `css.md`: wiersz o stylach typograficznych i `--control-height`/`--row-height` jako tokenach.

### Success Criteria:

#### Automated Verification:

- ADR-0037 istnieje, a ADR-0003, 0033 i 0035 odsyłają do niego: `grep -l "ADR-0037" .docs/adr/0003-* .docs/adr/0033-* .docs/adr/0035-*`
- Żaden dokument w `.docs/design-system.md` ani `.ai/rules/js.md` nie opisuje już `rounded-2xl`/`border/60` ani `column-caption` jako obowiązującego przepisu: `grep -n "rounded-2xl\|border/60\|column-caption" .docs/design-system.md .ai/rules/js.md` zwraca tylko wzmianki historyczne
- `npm run check` przechodzi (formatowanie Markdown nie jest objęte, ale gałąź pozostaje zielona)

#### Manual Verification:

- Użytkownik akceptuje treść ADR-0037 i poprawkę FR-REP-13/14

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika.

---

## Faza 2: Warstwa globalna

### Overview

Style, tokeny wymiarów i komponenty bazowe dla całej aplikacji; Budżet jeszcze z nich nie korzysta poza tym, co dziedziczy.

### Changes Required:

#### 1. Style i tokeny w `app.css`

**File**: `resources/css/app.css`

**Intent**: Dodać style typograficzne z README i tokeny wymiarów, zmienić przepis nagłówka kolumn, sumy i hover.

**Contract**: `@utility` (albo klasy w `@layer components`, zgodnie z sąsiednimi) `page-title` (Inter 600 18/24, -0.01em), `section-title` (600 13/18), `table-header` (600 12/16, `muted-foreground`, `whitespace-nowrap`), `label-caps` (500 11/14, wersaliki, +0.06em), `meta` (400 11/14), `kpi-lg` (mono 500 26/32), `kpi` (mono 500 22/28), `amount` (mono 500 13/18, `tabular-nums`), `amount-sum` (mono 600 13/18, `tabular-nums`). `--control-height: 32px`, `--control-height-sm: 26px`, `--row-height: 34px` w `:root` (bez wariantu `.dark`, bo to wymiary) i opublikowane tak, by dało się ich użyć jako utility (`h-control`, `h-control-sm`, `min-h-row` lub odpowiednik). `column-caption` przemianowane na `table-header` we wszystkich regułach `@apply` (`.column-header`, `.list-surface-table thead th`, `.budget-category-header-row`, `.operations-header-row`, `.import-preview-header-row`); klasa `column-caption` znika. `.budget-category-totals-row` i wariant sticky `:476-485` na `bg-muted`; sześć reguł hover na `hover:bg-secondary`. `.native-select` na `--control-height` i `radius-md`.

#### 2. Konsumenci `column-caption` w Vue

**File**: `MonthPlan.vue`, `ImportPreviewRow.vue`, `DashboardHoldings.vue`, `DashboardCategorySection.vue`, `BudgetPlanSummaryStrip.vue`, `pages/dev/{Accounts,Dashboard,ImportPreview,MonthPlan,Sidebar,TermDeposits}*Sink.vue`, `pages/dev/ImportPreviewSink.vue:74`

**Intent**: Te same miejsca używają nowej nazwy; opis „tłem foreground/3%” w sinku importu odpowiada nowemu hover.

**Contract**: `column-caption` → `table-header` w szablonach; brak innych zmian. W `BudgetPlanSummaryStrip.vue` (podpis nad miarą) wstaw `label-caps`, bo tam README dopuszcza wersaliki; komponent i tak zastąpi Faza 4.

#### 3. Card, Button, pola

**File**: `resources/js/components/ui/card/Card.vue`, `ui/button/index.ts`, `ui/input/Input.vue`, `ui/select/SelectTrigger.vue`

**Intent**: Przepis panelu i wysokości kontrolek z ADR-0037.

**Contract**: Card: `rounded-lg border` (pełny `border`, bez `/60`), pozostałe klasy bez zmian. Button: baza `rounded-md`; `default` i `icon` na `--control-height`, `sm` i `icon-sm` na `--control-height-sm`, `lg` zostaje większy niż default; poziome paddingi tak, by etykieta mieściła się w jednej linii. Input i SelectTrigger: `--control-height` i `rounded-md`. Edycja `components/ui/*` jest tu zamierzona (zmiana całego systemu, nie jednego ekranu).

#### 4. Badge i Switch

**File**: `resources/js/components/ui/badge/index.ts`, nowy `resources/js/components/ui/switch/*`

**Intent**: Warianty z README Badge i komponent Switch.

**Contract**: Badge: nowe `ok` (`accent` + `accent-foreground`), `brand` (`brand` + `brand-foreground`), `neutral` (`muted` + `muted-foreground`); istniejące warianty bez zmian wyglądu. Switch: `npm view shadcn-vue version`, potem `npx shadcn-vue@<ta wersja> add switch`; sprawdź `git status` (bez zmian w `package.json`/lockfile poza oczekiwanymi, bez `pnpm-workspace.yaml`). Jeśli CLI zawiedzie przez brakujący `tailwind.config.js`, przepisz komponent new-york-v4 ręcznie na `reka-ui` `SwitchRoot`/`SwitchThumb` w tym samym miejscu i zanotuj to w Progress. Po wygenerowaniu tokeny: tor `bg-track`, włączony `bg-primary`, gałka `bg-card`, focus `ring`.

#### 5. Kwota bez jednostki

**File**: `resources/js/lib/format.ts`, `resources/js/lib/format.test.ts`

**Intent**: Komórki tabel potrzebują liczby bez „zł”.

**Contract**: `formatMoneyAmount(minorUnits: number): string` (te same twarde spacje, dwa miejsca, `−` U+2212 jak dziś); `formatMoney` = `formatMoneyAmount` + „ zł”, wynik dla istniejących konsumentów bez zmian. Testy: dodatnia, zero, ujemna, tysiące.

#### 6. Wspólny pasek realizacji

**File**: nowy `resources/js/components/ExecutionMeter.vue`, `resources/js/components/CategoryMeterRow.vue`, `resources/js/lib/dashboard-categories.ts`

**Intent**: Jeden pasek z README Metera dla Dashboardu i Budżetu.

**Contract**: Props: `percentComplete: number | null`, `categoryType`, `isOverLimit: boolean`, `yearElapsedPercent: number | null` (null = brak kreski), `muted?: boolean` (archiwum: bez kolorów statusu). Tor `bg-track` 6 px, wypełnienie z `meterBarClass` (w normie `bg-foreground` zamiast `bg-primary`; dochód > 100% `bg-success`, przekroczenie `bg-destructive` bez zmian), kreska 2 px `bg-brand-text`, procent po prawej przez `meterPercentClass`. `CategoryMeterRow` renderuje `ExecutionMeter` zamiast własnego markupu (komórki `bar` i `percent` siatki `.category-meter-row` zostają, więc meter może wypełniać dwie komórki albo siatka łączy je w jedną; wybierz wariant, który nie zmienia szerokości kolumn Dashboardu). Kreskę pokazuje konsument według `showsYearElapsedMark`.

#### 7. Zrzuty innych widoków

**File**: `context/changes/budget-atrament-ui/screenshots/`

**Intent**: Sprawdzić skutki zmian globalnych przed przebudową Budżetu.

**Contract**: Jednorazowy skrypt Playwright jak w `design-tokens-atrament` (bez commitu skryptu), podgląd z worktree według AGENTS.md „Preview from a worktree”, jeśli praca idzie w worktree. Strony: `/dashboard`, `/operations`, `/month-plan`, `/dev/import-preview`, dialog tworzenia operacji; jasny i ciemny motyw, 1280 i 390 px.

### Success Criteria:

#### Automated Verification:

- `npm test` przechodzi, w tym nowe przypadki `formatMoneyAmount` w `format.test.ts`
- `npm run check`, `npm run check:ui` i `npm run types:check` przechodzą (po `npm run build`)
- `grep -rn "column-caption" resources/` nic nie zwraca
- `composer ci:check` przechodzi w obrazie Sail (w tym `MonthPlanHardcodedUiTest`)
- `git status` po CLI shadcn-vue nie pokazuje `pnpm-workspace.yaml` ani nieoczekiwanych zmian zależności

#### Manual Verification:

- Zrzuty Dashboardu, Operacji, Planu miesiąca, podglądu importu i dialogu w obu motywach przy 1280 i 390 px leżą w `screenshots/`; nagłówki kolumn mieszczą się w jednej linii, przyciski i pola w jednym wierszu mają tę samą wysokość
- Hover `secondary` jest widoczny na jasnym `card` (jeśli nie, zapisać to w ADR-0037 jako znany kontrast, bez zmiany tokenu)
- Pasek Dashboardu wygląda jak przed zmianą poza kreską `brand-text` i torem `track`

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika.

---

## Faza 3: Kontrakt propsów Budżetu

### Overview

Serwer przestaje liczyć odczyty miesiąca i wysyła postęp roku; front w tym samym commicie przestaje czytać usunięte propsy i traci pasek miesiąca.

### Changes Required:

#### 1. Kontroler

**File**: `app/Http/Controllers/BudgetController.php`

**Intent**: Usunąć odczyty miesiąca i dodać postęp roku.

**Contract**: Usunięte propsy w obu gałęziach: `viewedMonth`, `pacedPoolMonthRemainingMinorUnits`, `monthIncomeExecutionMinorUnits`, `monthExpenseExecutionMinorUnits`, `monthResultMinorUnits`, `currentMonth`, `viewedMonthTiming`, `monthToSpend`; z `categoryPayload` znika parametr `$monthReading` i pola `month_remaining_minor_units`, `month_execution_minor_units`. Usunięte wywołania `BudgetViewedMonth::resolve`, `BudgetMonthReadings::forBudget`, `BudgetMonthResult::forViewedMonth`, `MonthToSpend::forMonth` i nieużywane importy. Nowy prop `year_elapsed_percent: float|null` = `YearElapsed::percent(YearElapsed::asOfDate($budget->year, $today))` dla budżetu w realizacji, null w szkicu i bez budżetu.

#### 2. Usunięcie osieroconego kodu

**File**: `app/Support/BudgetMonthReadings.php`, `resources/js/components/BudgetMonthStrip.vue`, `resources/js/components/BudgetMonthResultSummary.vue`, `resources/js/components/BudgetMonthPicker.vue`

**Intent**: Usunąć kod bez konsumenta.

**Contract**: Usunąć `BudgetMonthReadings.php` (sprawdź `grep -rn BudgetMonthReadings app tests` po zmianie kontrolera) oraz oba komponenty Vue. `BudgetMonthPicker`: `monthUrl` staje się wymagany, znika domyślna gałąź z trasą Budżetu i prop `showArchived`; `MonthPlan.vue:539` bez zmian zachowania. Klasy `BudgetMonthResult`, `BudgetViewedMonth`, `MonthToSpend` zostają (inni wywołujący). Wpis `.sloppy-baseline.json` dla `BudgetMonthReadings`, jeśli istnieje: po usunięciu uruchom `vendor/bin/sloppy baseline`, żeby wpis zniknął.

#### 3. Front: propsy i pasek

**File**: `resources/js/pages/Budget.vue`, `resources/js/pages/dev/BudgetKitchenSink.vue`, `resources/js/ui-contract.test.ts`, `scripts/check-ui-literals.mjs`

**Intent**: Typy propsów odpowiadają serwerowi; pasek znika z widoku i sinka.

**Contract**: `Budget.vue`: z `defineProps` znikają usunięte propsy, dochodzi `year_elapsed_percent: number | null`; usunięty blok `BudgetMonthStrip` (`:276-297`) i import; przełącznik archiwum nie dokłada już `month` do URL (`:155`, `:169-170`). Sink: usunięte trzy paski miesiąca. `ui-contract.test.ts` i `check-ui-literals.mjs`: usunięte wpisy `BudgetMonthStrip` i `BudgetMonthResultSummary` (brak pliku daje w skrypcie kod 2).

#### 4. Testy

**File**: `tests/Feature/BudgetMonthResultTest.php`, `tests/Feature/BudgetMonthRemainingTest.php`, `tests/Feature/BudgetTest.php`

**Intent**: Zachować pokrycie obliczeń `BudgetMonthResult`, usunąć testy propsów, które nie istnieją, i przypiąć nowy kontrakt.

**Contract**: `BudgetMonthResultTest`: trzy przypadki obliczeń (wykonanie i wynik wybranego miesiąca, zarchiwizowane kategorie przy ukrytym archiwum, zwrot netto) przepisane na bezpośrednie wywołanie `BudgetMonthResult::forViewedMonth` na tych samych danych; przypadki `monthToSpend` usunięte (pokrywa je `tests/Feature/MonthToSpendTest.php`, sprawdź przed usunięciem, że każdy przypadek ma tam odpowiednik, inaczej przenieś go). `BudgetMonthRemainingTest`: usunięty w całości po sprawdzeniu, że domyślne miesiące pokrywa `tests/Unit/BudgetViewedMonthTest.php`, a obliczenia Month Remaining `tests/Unit/MonthRemainingCalculatorTest.php`; test `…switching_budget_year_drops_month_query` przenieś, jeśli sprawdza zachowanie przełącznika roku, które nadal istnieje. `BudgetTest` (lub nowy test w tym pliku): `assertInertia` z `missing` dla usuniętych propsów, `year_elapsed_percent` dla budżetu w realizacji z zamrożoną datą (rok bieżący, przeszły → 100, przyszły → wartość 1 stycznia) i null w szkicu i bez budżetu. Zgoda użytkownika na usunięcie testów: udzielona w planowaniu (2026-11-05).

### Success Criteria:

#### Automated Verification:

- Testy Budżetu przechodzą na PostgreSQL w obrazie Sail: `… artisan test --compact tests/Feature/BudgetTest.php tests/Feature/BudgetMonthResultTest.php tests/Unit/BudgetViewedMonthTest.php tests/Feature/MonthToSpendTest.php`
- `composer ci:check` przechodzi w obrazie Sail
- `vendor/bin/sloppy ci` na hoście bez nowych znalezisk
- `npm run build`, potem `npm run types:check`, `npm run check`, `npm run check:ui` i `npm test` przechodzą
- `grep -rn "BudgetMonthReadings\|BudgetMonthStrip\|BudgetMonthResultSummary\|monthToSpend\|viewedMonthTiming" app resources/js tests` nie zwraca nic poza `MonthPlan`/Dashboard, które używają własnych propsów o tej nazwie (sprawdź każdy wynik)

#### Manual Verification:

- `/budget` w realizacji i w szkicu renderuje się bez paska miesiąca, przełącznik archiwum działa, Plan miesiąca dalej przełącza miesiące przez `BudgetMonthPicker`

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika.

---

## Faza 4: Widok Budżetu w Atramencie

### Overview

Przebudowa `/budget` na stylach i komponentach z Fazy 2, kitchen sink z pełną macierzą stanów, straż literałów i zrzuty.

### Changes Required:

#### 1. Wiersz tytułu i akcje

**File**: `resources/js/pages/Budget.vue`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: Wzorzec PageTitle: tytuł, odznaka, opis, najwyżej jedna akcja główna.

**Contract**: h1 z `page-title`; Badge `brand` „Realizacja”, `warning` „Szkic” (mapowanie fazy → wariant obok `phaseLabel`); zdanie opisu w `muted-foreground` (realizacja: jak liczy się Pozostały limit; szkic: plan można zmieniać do startu realizacji). Akcje: zarysowany „Dodaj kategorię” z ikoną `Plus` (otwiera jeden `CategoryFormDialog` `mode="create"` z `default-type="expense"`; drugi dialog tworzenia i `createIncomeDialogOpen` znikają), w szkicu primary „Rozpocznij realizację” i menu `…` bez zmian. Nowe klucze w grupie `budget` i ich typy w `shell.ts`; `add_income`/`add_expense` usunięte, jeśli nie mają innych konsumentów.

#### 2. Panel miar

**File**: nowy `resources/js/components/StatPanel.vue` (i ewentualnie `StatPanelMetric.vue`), `resources/js/components/BudgetPlanSummaryStrip.vue`, `app.css`

**Intent**: README StatPanel: jedna powierzchnia, 2–4 miary rozdzielone pionowymi liniami, pierwsza największa.

**Contract**: `StatPanel` = Card z siatką miar (nazwana klasa w `app.css`, na wąskim ekranie miary jedna pod drugą z poziomą linią). Miara: etykieta `label-caps`, wartość `kpi-lg` (pierwsza) albo `kpi`, opcjonalny podpis `meta`, kolor kwoty ze znakiem przez `amountToneClass`, kwota przez `formatMoney` (z „zł”). `BudgetPlanSummaryStrip` renderuje `StatPanel` z Planowanymi dochodami, wydatkami, saldem, a gdy `year_elapsed_percent !== null`: „Wykonanie wydatków” = `expenseTotals.percent_complete` % (gdy null: „—”) z podpisem „Postęp roku M%” (zaokrąglone). Nowe propsy komponentu: `expensePercentComplete`, `yearElapsedPercent`.

#### 3. Panele kategorii i wiersze

**File**: `BudgetCategoryList.vue`, `BudgetCategoryHeaderRow.vue`, `BudgetCategoryRow.vue`, `BudgetAmountCell.vue`, `app.css` (`.budget-category-grid*`)

**Intent**: README DataTable, Panel i Meter na Budżecie.

**Contract**: Nagłówek panelu: tytuł `section-title`, po prawej meta `amount`: realizacja „{wykonanie} z {plan}” (`formatMoney`), szkic suma planu; przycisk „Dodaj …” znika z panelu. Komórki: `formatMoneyAmount` + `amount`, suma `amount-sum`, stopka panelu `meta` „Kwoty w zł”. Wiersz ma `min-h` `--row-height`. Realizacja: nowa kolumna `meter` w `.budget-category-grid` (md: po kolumnie pozostałego limitu, przed akcjami; uchwyt przeciągania zostaje), w wierszu `ExecutionMeter` z `yearElapsedPercent` tylko gdy `showsYearElapsedMark(categoryType, category)`, w wierszu sumy pasek z procentem sumy bez kreski; procent w komórce wykonania znika (jest w pasku). Przekroczona kategoria wydatkowa: Badge `destructive-soft` „przekroczono” przy nazwie. Szkic: kolumna „Typ” z Badge `neutral` (dochód: nowy klucz „dochód”; wydatek: `month_item_kind_bill` / `month_item_kind_running`, małą literą zgodnie z README Badge, jeśli klucz to dopuszcza, inaczej nowe klucze). Archiwum: wiersz `muted-foreground` bez kolorów statusu, `ExecutionMeter` z `muted`, Badge `neutral`; sekcja `Collapsible` zostaje, z nagłówkiem w `table-header`. `BudgetCategoryRow` przestaje czytać `percentClass` dla procentu w komórce; `budget-category-cells` (lib + `budget-category-cells.test.ts`) zaktualizowane, jeśli helpery stają się nieużywane.

#### 4. Switch

**File**: `resources/js/pages/Budget.vue`

**Intent**: „Pokaż zarchiwizowane” to przełącznik ustawienia zapisywanego od razu.

**Contract**: `Switch id="show-archived-budget" v-model="showArchived"` z `Label` (`muted-foreground`) w miejscu `Checkbox`; watcher i `toggleShowArchived` bez zmian. Import `Checkbox` znika, jeśli nie ma innego użycia.

#### 5. Kitchen sink i straż literałów

**File**: `resources/js/pages/dev/BudgetKitchenSink.vue`, `scripts/check-ui-literals.mjs`, `.ai/rules/js.md`

**Intent**: Macierz 7 stanów pokazuje nowy widok; oczyszczone pliki są pilnowane.

**Contract**: Sink: realizacja z wierszami w normie, dochód > 100%, przekroczenie, zarchiwizowany, kategoria jednorazowa (bez kreski), równomierna (z kreską); szkic z kolumną Typ; pusty budżet; brak kategorii; Switch wł./wył.; panel miar z 3 i 4 miarami. `check-ui-literals.mjs` `FILES`: dodać `pages/Budget.vue`, `pages/dev/BudgetKitchenSink.vue`, `BudgetCategoryList`, `BudgetCategoryHeaderRow`, `BudgetCategoryRow`, `BudgetAmountCell`, `BudgetEmptyState`, `BudgetCategoryRowActions`, `BudgetCategoryAlignPlanAction`, `BudgetMonthPicker`, `StatPanel`, `ExecutionMeter`. `ui-contract.test.ts` `BUDGET_VIEW_FILES`: dodać `StatPanel`, `ExecutionMeter`. `js.md`: opis sinka Budżetu odpowiada nowym stanom.

#### 6. Zrzuty Budżetu

**File**: `context/changes/budget-atrament-ui/screenshots/`

**Intent**: Bramka wizualna widoku.

**Contract**: `/dev/kitchen-sink/budget` i `/budget` (realizacja i szkic, dane z `DemoHouseholdSeeder`) w obu motywach przy 1280 i 390 px, porównane z mockupem MockupCAtrament → Budżet.

### Success Criteria:

#### Automated Verification:

- `npm run build`, potem `npm run types:check`, `npm run check`, `npm run check:ui` (z nowymi plikami na liście) i `npm test` przechodzą
- `composer ci:check` przechodzi w obrazie Sail, a testy Budżetu na PostgreSQL (`tests/Feature/BudgetTest.php`, `tests/Feature/BudgetKitchenSinkTest.php`)
- `vendor/bin/sloppy ci` na hoście bez nowych znalezisk
- `vendor/bin/pint --dirty --format agent` bez zmian do zrobienia

#### Manual Verification:

- Zrzuty `/dev/kitchen-sink/budget` i `/budget` (realizacja, szkic) w obu motywach przy 1280 i 390 px leżą w `screenshots/` i odpowiadają mockupowi: nagłówki w jednej linii, przecinki groszy w kolumnie, kreska roku tylko przy dochodach i wydatkach równomiernych, „przekroczono” tylko przy przekroczonych wydatkach
- Dodanie kategorii dochodowej i wydatkowej przez jeden przycisk „Dodaj kategorię” działa; Switch przełącza archiwum klawiaturą (spacja) i myszą
- Na 390 px tabela nie przewija strony w poziomie

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie użytkownika.

---

## Testing Strategy

### Unit Tests:

- `format.test.ts`: `formatMoneyAmount` (dodatnia, zero, ujemna z `−`, tysiące) i `formatMoney` bez zmian.
- `BudgetMonthResultTest`: obliczenia `BudgetMonthResult::forViewedMonth` bez strony (zarchiwizowane, zwrot netto).
- `budget-category-cells.test.ts` i testy `dashboard-categories`, jeśli zmienia się `meterBarClass` (w normie `bg-foreground`).

### Integration Tests:

- `BudgetTest`: `assertInertia` dla `year_elapsed_percent` (realizacja w roku bieżącym, przeszłym, przyszłym; null w szkicu i bez budżetu) i `missing` dla usuniętych propsów.
- `BudgetKitchenSinkTest`: 404 poza `local` bez zmian.

### Manual Testing Steps:

1. Otwórz `/budget` dla budżetu w realizacji: panel miar z 4 miarami, paski z kreską roku, „przekroczono” przy przekroczonej kategorii, stopka „Kwoty w zł”.
2. Przełącz „Pokaż zarchiwizowane”: sekcja archiwum pojawia się, sumy planu się zmieniają.
3. Otwórz budżet w szkicu: 3 miary, kolumna Typ, „Rozpocznij realizację” jako jedyny primary.
4. Dodaj kategorię dochodową przez „Dodaj kategorię” (zmiana typu w dialogu).
5. Sprawdź Dashboard i Operacje po zmianach globalnych w obu motywach.

## Performance Considerations

Kontroler Budżetu traci zapytania `BudgetMonthReadings`, `BudgetMonthResult` i `MonthToSpend` na każde wejście; `YearElapsed` nie odpytuje bazy.

## Migration Notes

Bez migracji danych. URL `/budget?month=N` dalej działa (parametr jest ignorowany).

## References

- Research: `context/changes/budget-atrament-ui/research.md`
- Wzorzec: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (README, Badge, Button, DataTable, Meter, Panel, PageTitle, StatPanel, Switch, Amount, MockupCAtrament → Budżet)
- Poprzednia zmiana: `context/archive/2026-11-05-design-tokens-atrament/plan.md` (ADR-0035, wzorzec zrzutów)
- Pasek z kreską: `resources/js/components/CategoryMeterRow.vue:80-110`, `resources/js/lib/dashboard-categories.ts:53-99`
- Postęp roku: `app/Support/YearElapsed.php`, `app/Http/Controllers/DashboardController.php:81-83`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0037 istnieje, a ADR-0003, 0033 i 0035 odsyłają do niego — 2e88f25
- [x] 1.2 design-system.md i js.md nie opisują rounded-2xl/border/60 ani column-caption jako obowiązującego przepisu — 2e88f25
- [x] 1.3 npm run check przechodzi — 2e88f25

#### Manual

- [ ] 1.4 Użytkownik akceptuje treść ADR-0037 i poprawkę FR-REP-13/14

### Phase 2: Warstwa globalna

#### Automated

- [x] 2.1 npm test przechodzi, w tym formatMoneyAmount — b4a7b9d
- [x] 2.2 npm run check, check:ui i types:check przechodzą po build — b4a7b9d
- [x] 2.3 grep column-caption w resources/ nic nie zwraca — b4a7b9d
- [x] 2.4 composer ci:check przechodzi w obrazie Sail — b4a7b9d
- [x] 2.5 git status po CLI shadcn-vue bez pnpm-workspace.yaml i nieoczekiwanych zależności — b4a7b9d

#### Manual

- [ ] 2.6 Zrzuty Dashboardu, Operacji, Planu miesiąca, importu i dialogu w obu motywach przy 1280 i 390 px
- [ ] 2.7 Hover secondary widoczny na jasnym card albo zapisany w ADR-0037
- [ ] 2.8 Pasek Dashboardu bez zmian poza kreską brand-text i torem track

### Phase 3: Kontrakt propsów Budżetu

#### Automated

- [x] 3.1 Testy Budżetu przechodzą na PostgreSQL w obrazie Sail
- [x] 3.2 composer ci:check przechodzi w obrazie Sail
- [x] 3.3 sloppy ci bez nowych znalezisk
- [x] 3.4 build, types:check, check, check:ui i npm test przechodzą
- [x] 3.5 grep usuniętych nazw nie zwraca nic poza własnymi propsami MonthPlan/Dashboard

#### Manual

- [ ] 3.6 /budget bez paska miesiąca, przełącznik archiwum działa, Plan miesiąca przełącza miesiące

### Phase 4: Widok Budżetu w Atramencie

#### Automated

- [ ] 4.1 build, types:check, check, check:ui z nowymi plikami i npm test przechodzą
- [ ] 4.2 composer ci:check w obrazie Sail i testy Budżetu na PostgreSQL przechodzą
- [ ] 4.3 sloppy ci bez nowych znalezisk
- [ ] 4.4 pint --dirty bez zmian do zrobienia

#### Manual

- [ ] 4.5 Zrzuty kitchen sinka i /budget w obu motywach przy 1280 i 390 px zgodne z mockupem
- [ ] 4.6 Jeden przycisk Dodaj kategorię tworzy dochód i wydatek; Switch działa klawiaturą i myszą
- [ ] 4.7 Na 390 px brak poziomego przewijania strony
