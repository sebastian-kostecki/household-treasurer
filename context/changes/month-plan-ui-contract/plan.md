# Kontrakt UI widoku Plan miesiąca — plan implementacji

## Overview

Widok `/month-plan` (`resources/js/pages/MonthPlan.vue` i jego komponenty) przechodzi na kontrakt istniejącego systemu projektowego:

- wspólny przełącznik segmentowy;
- nazwane klasy siatki i etykiet w `app.css`;
- akcja główna widoczna bez przewijania;
- przełącznik rodzaju pozycji, który mówi, że zapisuje ustawienie kategorii;
- wskazanie błędnej kwoty przy polu.

Na końcu zmiany zostają kitchen sink (7 stanów), test skanujący źródło i reguła dla następnego agenta. Domena, obliczenia serwera i pary „Month logic lives twice” się nie zmieniają.

## Current State Analysis

Pełny audyt z dowodami: `context/changes/month-plan-ui-contract/research.md` (`## Charges` C1–C5, decyzje 1–5). W skrócie:

- **C1:** dwa ręcznie złożone przełączniki (`MonthPlan.vue:595-620`, `MonthPlanItemRow.vue:143-160`) z nadpisanym rozmiarem `Button`. Każda opcja to osobny przystanek Tab.
- **C2:** arbitralna siatka `grid-cols-[1fr_…]` skopiowana w nagłówku (`MonthPlan.vue:697`) i wierszu (`MonthPlanItemRow.vue:61`). Do tego `text-[11px]` w nagłówku kolumn i 3 kopie ciągu etykiety sekcji (`MonthPlan.vue:520,556,586`).
- **C3:** przełącznik rodzaju zapisuje od razu kolumnę kategorii (`CategoryController.php:76-81`), ale wygląda jak kontrolka formularza. „Anuluj” go nie cofa (`MonthPlan.vue:167-170`). Brak stanu w toku. Błąd pokazuje się nad listami (`:689`).
- **C4:** akcje są dopiero pod obiema listami (`:808-850`), a `errors.items` jest zduplikowany. Wybór miesiąca siedzi w osobnej `Card` (`:498-508`).
- **C5:** zła kwota tylko wyłącza przycisk. `Input` nie dostaje `aria-invalid` i nie ma komunikatu przy polu (`MonthPlanItemRow.vue:173-180`, `MonthPlan.vue:621-627`).

Repo nie ma narzędzia do zrzutów ani testów komponentów Vue. Ma za to precedens testu skanującego źródło (`tests/Unit/LandingPageSourceTest.php`).

## Desired End State

- **C1–C2.** Pliki widoku nie zawierają wartości arbitralnych (`-[Npx|rem]`) ani `grid-cols-[`. Pilnuje tego `tests/Unit/MonthPlanHardcodedUiTest.php` w `composer ci:check`. Oba przełączniki to `SegmentedControl`: jeden przystanek Tab i strzałki.
- **C4, tryb odczytu.** Wiersz tytułu ma `h1` + `Badge` po lewej, a dla planu zatwierdzonego z `can_change` przycisk „Zmień plan” po prawej. Pasek miesięcy stoi pod tytułem, bez `Card`.
- **C4, tryb edycji.** Dotyczy propozycji z `can_approve` i trybu „Zmień”. U dołu ekranu przykleja się pasek z bieżącym „Do wydania”, jednym `errors.items` i przyciskami „Zatwierdź” albo „Anuluj / Zapisz zmiany”.
- **C3.** Lista wydatków ma pod tytułem notę o zakresie rodzaju. Przełącznik rodzaju ma neutralny wygląd, inny niż przełączniki formularza. W czasie zapisu jest wyłączony ze spinnerem, a błąd pokazuje się w tym wierszu. Podpowiedź trybu „Zmień” mówi, że „Anuluj” nie cofa rodzaju.
- **C5.** Pole kwoty (plan i odkładanie), które straciło fokus z błędną wartością, dostaje `aria-invalid` i `InputError`. Oznaczenie znika, gdy wartość staje się poprawna. Przy wyłączonym przycisku w pasku widnieje „Niepoprawne kwoty: N”.
- **Kitchen sink.** `/dev/month-plan` (tylko `APP_ENV=local`) pokazuje każdą komórkę macierzy 7 stanów albo ma przy niej „N/A” z powodem. W folderze zmiany leżą zrzuty desktop (1280) i mobile (390) po każdej fazie wizualnej.
- **Reguła.** `.ai/rules/js.md` mówi, gdzie są klasy, `SegmentedControl` i kitchen sink, oraz że widoki nie mają wartości arbitralnych.

### Key Discoveries:

- Rodzaj pozycji istnieje tylko dla wydatków (`app/Http/Controllers/MonthPlanController.php:279,285`), więc nota C3 dotyczy tylko listy wydatków.
- `ListSurface` ma prop `description` (`resources/js/components/ListSurface.vue:15-19`). Nota C3 nie potrzebuje nowego markupu.
- `.native-select` (`resources/css/app.css:194-196`) to precedens: pierścień fokusu `ring-[3px]` żyje w klasie `@layer components`, a nie w widoku. Skan obejmuje pliki widoku, nie źródło tokenów. Tak rozstrzygamy otwarte pytanie 4 z research.
- `AppContent` ma `overflow-x-clip` (`resources/js/layouts/app/AppSidebarLayout.vue:21`). `clip` nie tworzy kontenera przewijania, więc `sticky bottom-0` zadziała. W powłoce aplikacji nie ma elementów sticky ani fixed.
- `parseMoneyInputToMinorUnits` (`resources/js/lib/format.ts:41-56`) zwraca `null` dla pustego pola, a `"12,"` przyjmuje. Dlatego oznaczamy błąd dopiero po blur: wyczyszczenie pola nie świeci na czerwono.
- Repo nie ma pomocnika liczby mnogiej. Podpis „Niepoprawne kwoty: :count” jej nie wymaga.
- Tłumaczenia to wspólny prop Inertia: `lang/pl/ui.php` ↔ `UiTranslations` (`resources/js/types/shell.ts:21`), z asercją `->where('translations.…')` jak w `tests/Feature/AppShellTest.php:64`.
- `ui/spinner` istnieje. Stan ładowania nie potrzebuje nowego komponentu.
- Strony spoza `Welcome` / `auth/` / `settings/` dostają `AppLayout` (`resources/js/app.ts:12-22`), który wymaga wspólnych propsów. Trasa kitchen sink musi więc stać w grupie `auth` + `verified` (`routes/web.php:19`).

## What We're NOT Doing

- Zmiany domeny rodzaju pozycji: rodzaj zostaje cechą kategorii, endpoint `categories.month-item-kind` i `can_change_kind` bez zmian. Nie ukrywamy przełącznika w trybie „Zmień” i „Anuluj” go nie cofa.
- Zmiany obliczeń: `MonthRemainingCalculator`, `ToSpendCalculator`, `MonthItemDeviation`, `MonthPaceCalculator` i ich kopie TS zostają nietknięte.
- Inne widoki z tymi samymi ciągami (`Operations.vue`, `OperationsImportPreview.vue`, `BudgetMonthStrip.vue`, `BudgetPlanSummaryStrip.vue`, `DashboardCategorySection.vue`). Nowe klasy są gotowe do przyjęcia w osobnych zmianach (D1).
- `AppearanceTabs.vue` jako trzeci konsument `SegmentedControl` (D2), `app.css:86` (D5), wspólny `PageHeader` (D6).
- Rozstrzyganie „nagłówek strony `text-xl`” w `design-system.md` dla wszystkich stron (D3). Ta zmiana dopisuje tylko notę o `Heading` (D4).
- `BudgetMonthPicker.vue` zostaje bez zmian. To nawigacja (`aria-current`), nie przełącznik wartości, więc nie przechodzi na `SegmentedControl`.
- Nowe zależności: shadcn-vue CLI, Playwright, Pest browser, `@vue/test-utils`. Nie ma bramki zrzutów w CI.
- Liczba mnoga w podpisach.

## Implementation Approach

Kolejność według `/10x-ui`: środowisko → kontrakt (tokeny i komponent) → widok → stany → utrwalenie.

Kitchen sink powstaje w fazie 1, żeby każda następna faza wizualna kończyła się zrzutem tego samego miejsca. W fazie 2 nowe klasy i `SegmentedControl` trafiają najpierw do kitchen sink, a dopiero w fazie 3 do widoku. Logika, którą da się wyjąć z Vue (granice kwoty), trafia do `resources/js/lib` z testem `*.test.ts`, bo komponentów Vue w tym repo nie da się testować.

Każdy nowy klucz tłumaczenia zmienia jednocześnie `lang/pl/ui.php`, `UiTranslations` i asercję `assertInertia` w tym samym commicie (AGENTS.md, „Inertia props contract”).

## Critical Implementation Details

**Trasa tylko dla `local` a Wayfinder.** Rejestracja przez `if (app()->environment('local'))` przy ładowaniu `routes/web.php` daje 404 w `testing` i `production`. Wayfinder generuje jednak pliki tras w środowisku, w którym biegnie `npm run build`. Żaden kod nie może importować wygenerowanej funkcji dla tej trasy, bo `npm run types:check` w CI złamie się, gdy jej nie będzie. Kitchen sink nie potrzebuje linku do siebie.

**`SegmentedControl` w trybie `single`.** Reka `ToggleGroupRoot` emituje `undefined`, gdy kliknie się aktywną opcję (`useSingleOrMultipleValue.js:43`). Wrapper ignoruje `undefined` i nie emituje `update:modelValue`. W przeciwnym razie odkładam / dobieram straci wartość, a przełącznik rodzaju wyśle PATCH z `null`.

**Pasek sticky wewnątrz `<Form>`.** Przycisk `type="submit"` musi być potomkiem DOM `<Form>`, więc `MonthPlanEditBar` stoi jako ostatnie dziecko formularza. Element sticky jest ograniczony swoim blokiem zawierającym, czyli przykleja się tylko wtedy, gdy formularz jest w widoku. Właśnie tego chcemy: listy są w formularzu. Pasek nie może zasłaniać ostatniego wiersza listy, więc formularz w trybie edycji potrzebuje dolnego odstępu równego wysokości paska.

**Zrzuty.** Przeglądarka jest w cache poza repo (`~/.cache/ms-playwright/chromium-1248`). Zrzuty robi jednorazowy skrypt Node uruchamiany przez `npx -y -p playwright@<wersja zgodna z rewizją chromium w cache>` i niecommitowany. Skrypt:

1. otwiera podgląd z worktree (AGENTS.md „Preview from a worktree”, http://localhost:8090);
2. loguje się jako `test@example.com` / `password`;
3. zapisuje pełne strony `/dev/month-plan` i `/month-plan` przy 1280×900 i 390×844 do `context/changes/month-plan-ui-contract/screenshots/phase-<N>-<strona>-<szerokość>.png`.

Podgląd serwuje zbudowane assety, więc przed każdym zrzutem trzeba uruchomić `npm run build`.

## Faza 1: Kitchen sink i punkt odniesienia

### Overview

Lokalna strona, która renderuje części widoku z danych testowych, oraz zrzuty obecnego stanu jako punkt odniesienia.

### Changes Required:

#### 1. Typ odczytu planu

**File**: `resources/js/types/month-plan.ts`, `resources/js/pages/MonthPlan.vue`

**Intent**: przenieść lokalny typ `MonthPlanReading` (`MonthPlan.vue:49-65`) do wspólnych typów, żeby kitchen sink i dane testowe używały tego samego kształtu co strona.

**Contract**: `export type MonthPlanReading` w `types/month-plan.ts` o identycznych polach. `MonthPlan.vue` importuje go zamiast deklarować. Propsy serwera się nie zmieniają.

#### 2. Dane testowe

**File**: `resources/js/lib/month-plan-fixtures.ts` (nowy)

**Intent**: zestaw `MonthPlanItem` i `MonthPlanReading` pokrywający przypadki widoku. Wartości wzorcowe brać z `tests/Feature/MonthPlanPageTest.php`. Przypadki:

- wydatek bieżący;
- rachunek zapłacony;
- dochód „Wpłynęło”;
- jednorazowa bezczynna;
- zarchiwizowana;
- poza planem;
- odchylenie ponad plan;
- długa nazwa kategorii;
- wiersz bez `can_change_kind`.

**Contract**: eksporty tylko z danymi, bez logiki: tablica wierszy i budowniczek odczytu z nadpisaniami. Nie trafia do kodu produkcyjnego strony.

#### 3. Trasa i strona kitchen sink

**File**: `routes/web.php`, `resources/js/pages/dev/MonthPlanSink.vue` (nowy)

**Intent**: uwierzytelniona trasa Inertia dostępna tylko w `local`, renderująca w sekcjach stan obecny elementów widoku:

- wiersze `MonthPlanItemRow` (odczyt i edycja);
- nagłówek kolumn;
- przełączniki;
- puste stany (`IndexEmptyState` ×3 i „Brak pozycji”).

Strona ma sekcję „Macierz stanów” z 7 nagłówkami, które kolejne fazy wypełniają.

**Contract**: `Route::inertia('dev/month-plan', 'dev/MonthPlanSink')` w grupie `auth` + `verified`, wewnątrz `if (app()->environment('local'))`. Wayfinder dla tej trasy nie jest importowany (patrz Critical Implementation Details). Strona nie wywołuje `setLayoutProps` z linkiem Wayfinder do siebie.

#### 4. Test trasy

**File**: `tests/Feature/DevMonthPlanSinkTest.php` (nowy)

**Intent**: zalogowany użytkownik dostaje 404 na `/dev/month-plan` poza `local`, czyli kitchen sink nie wycieknie na produkcję.

**Contract**: jeden test Pest w środowisku `testing`, `->get('/dev/month-plan')->assertNotFound()`.

#### 5. Zrzuty odniesienia

**File**: `context/changes/month-plan-ui-contract/screenshots/phase-1-*.png`

**Intent**: obecny `/month-plan` (propozycja i plan zatwierdzony w trybie „Zmień”) oraz kitchen sink. Desktop i mobile, procedurą z Critical Implementation Details.

**Contract**: pliki PNG commitowane jako dowód do przeglądu.

### Success Criteria:

#### Automated Verification:

- Test trasy przechodzi na PostgreSQL (obraz Sail): `php artisan test --compact tests/Feature/DevMonthPlanSinkTest.php`
- Testy strony bez regresji: `php artisan test --compact tests/Feature/MonthPlanPageTest.php`
- `npm run build` i `npm run types:check` przechodzą
- `npm run check` przechodzi bez ostrzeżeń
- Pint: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- `/dev/month-plan` w podglądzie z worktree renderuje wszystkie sekcje z danych testowych w trybie jasnym i ciemnym
- Zrzuty odniesienia desktop 1280 i mobile 390 zapisane w `screenshots/`

**Implementation Note**: po automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Elementy kontraktu

### Overview

Nazwane klasy w `app.css` i komponent `SegmentedControl`, pokazane w kitchen sink, zanim dotkną widoku.

### Changes Required:

#### 1. Klasy komponentowe

**File**: `resources/css/app.css` (`@layer components`)

**Intent**: nadać nazwy zatwierdzonym wartościom (C2) i chrome przełącznika (C1), wzorem `.category-meter-row` / `.category-meter-header-row` (`app.css:425-462`) i `.native-select` (`:194-196`).

**Contract**:

- `.month-plan-grid`: `display: grid`, `gap` 0.75rem, `grid-template-columns: minmax(0,1fr) 5.5rem 5.5rem` → `@variant sm` `minmax(0,1fr) 9rem 9rem` → `@variant lg` `minmax(0,1fr) 9rem 9rem 9rem`. Używana przez nagłówek kolumn i wiersz. Elementy tylko dla wiersza zostają w Vue.
- `.month-plan-grid-kind` (modyfikator, tylko lista wydatków; decyzja użytkownika po fazie 1): dodaje kolumnę „Rodzaj" zaraz po kategorii. Poniżej `sm` nie ma tej kolumny (przełącznik stoi pod nazwą kategorii, jak dziś); od `sm` `minmax(0,1fr) 9rem 9rem 9rem`; od `lg` `minmax(0,1fr) 9rem 9rem 9rem 9rem`. Odchylenie nadal pojawia się osobno dopiero od `lg`.
- `.column-header`: `text-muted-foreground text-[11px] font-medium tracking-wider uppercase`, ta sama wartość co `.list-surface-table thead th` (`app.css:206-208`, zatwierdzone w `.docs/design-system.md:16`).
- `.section-label`: `text-muted-foreground text-xs font-medium tracking-wider uppercase`.
- `.segmented-control`: chrome kontenera jak dziś (`border-border/60 bg-muted/40 inline-flex gap-1 rounded-xl border p-1`).
- `.segmented-control-item`: `h-7 rounded-lg px-2 text-xs font-medium`, hover na tokenach, fokus `focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none`, `disabled:opacity-50 disabled:pointer-events-none`.
- `.segmented-control-item` dostaje dwa tony stanu włączonego przez atrybut na kontenerze (np. `data-tone`):
  - `primary`: `data-[state=on]` → `bg-primary text-primary-foreground`;
  - `neutral`: `bg-card text-foreground` z obramowaniem `border-border`, bez wypełnienia `primary` i bez cienia spoczynkowego (ADR-0003).

  Tylko tokeny, zero klas palety.

#### 2. Komponent `SegmentedControl`

**File**: `resources/js/components/SegmentedControl.vue` (nowy)

**Intent**: jeden przełącznik wartości z roving focus (jeden przystanek Tab, strzałki, Home/End) na reka `ToggleGroupRoot` / `ToggleGroupItem`. Bez CLI shadcn-vue i bez edycji `components/ui/*` (research, Decyzja 1).

**Contract**: generyczny `<T extends string>`, `v-model` (`modelValue: T`), `options: { value: T; label: string }[]`, `ariaLabel: string`, `tone?: 'primary' | 'neutral'` (domyślnie `primary`), `disabled?: boolean`. Rodzaj `single`. Emitowane `undefined` jest ignorowane. Klasy wyłącznie z `.segmented-control*`. Semantyka: `role="group"` + `aria-pressed` z reka.

#### 3. Kitchen sink: sekcja kontraktu

**File**: `resources/js/pages/dev/MonthPlanSink.vue`

**Intent**: pokazać `SegmentedControl` w obu tonach, wyłączony i z wymuszonym fokusem/hoverem (klasa lub opis „najedź / Tab”), oraz `.month-plan-grid` z `.column-header` i długą nazwą kategorii.

**Contract**: nowa sekcja strony, bez zmian w widoku produkcyjnym.

### Success Criteria:

#### Automated Verification:

- `npm run check` i `npm run types:check` przechodzą (po `npm run build`)
- `npm test` przechodzi
- Skan wartości zakodowanych na sztywno z `/10x-ui` na `SegmentedControl.vue` daje 0 trafień

#### Manual Verification:

- W kitchen sink `SegmentedControl` ma jeden przystanek Tab, strzałki zmieniają fokus, Spacja/Enter wybiera, kliknięcie aktywnej opcji jej nie odznacza
- Pierścień fokusu ma 3 px `ring/50` w obu tonach i obu motywach
- `.month-plan-grid` i `.month-plan-grid-kind`: długa nazwa kategorii zawija się bez rozpychania kolumn kwot i rodzaju przy 390 i 1280
- Zrzuty fazy 2 zapisane w `screenshots/`

**Implementation Note**: po automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Widok (C1, C2, C4)

### Overview

Widok przechodzi na klasy i komponent z fazy 2 i dostaje nowy układ: akcja w wierszu tytułu, pasek miesięcy bez karty, przyklejony pasek edycji.

### Changes Required:

#### 1. Przełączniki i siatka (C1, C2)

**File**: `resources/js/pages/MonthPlan.vue`, `resources/js/components/MonthPlanItemRow.vue`

**Intent**: zastąpić oba ręczne przełączniki komponentem `SegmentedControl`. Odkładam / dobieram dostaje ton `primary`. Rodzaj w tej fazie też zostaje na tonie `primary`, a zmianę tonu robi faza 4. Przełącznik rodzaju przenieść z pod nazwy kategorii do osobnej kolumny „Rodzaj" (`.month-plan-grid-kind`, od `sm`; poniżej `sm` zostaje pod nazwą). Wiersz dochodu nie ma tej kolumny. Odznaki statusu (`Zapłacone`, `Wpłynęło`, `Jednorazowa`, `Zarchiwizowane`, `Poza planem`) stoją w jednej linii z nazwą kategorii (`CategoryChip` + odznaki w `flex flex-wrap items-center gap-x-2 gap-y-1`), a nie pod nią; przy wąskim ekranie zawijają się pod nazwę. Linie pomocnicze (rok, limit) zostają pod spodem. Nagłówek listy wydatków dostaje komórkę „Rodzaj" (`.column-header`, ukrytą poniżej `sm`), a tekst rodzaju bez `can_change_kind` trafia do tej samej komórki. Arbitralne siatki zastąpić `.month-plan-grid`, `text-[11px]…` klasą `.column-header`, a trzy etykiety sekcji (`MonthPlan.vue:520,556,586`) klasą `.section-label`.

**Contract**: zdarzenie `change-kind` w `MonthPlanItemRow` bez zmian. Nowy klucz `month_plan.kind_column` (np. „Rodzaj") w `ui.php`, `UiTranslations` i asercji (patrz pkt 4). Wiersz decyduje o kolumnie rodzaju po `row.type === 'expense'`. W plikach widoku zero `grid-cols-[` i `text-[`.

#### 2. Wiersz tytułu i wybór miesiąca (C4)

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**:

- wiersz tytułu wg `Budget.vue:195-210`: `flex flex-wrap items-center justify-between gap-3`, `h1` + `Badge` po lewej;
- po prawej „Zmień plan” (`startChanging`), tylko gdy `monthPlan.can_change && !isChanging`;
- `BudgetMonthPicker` bezpośrednio pod wierszem tytułu, bez `Card`.

**Contract**: przycisk „Zmień plan” to `type="button"` poza `<Form>`. Usunąć stary blok akcji `:808-838`.

#### 3. Przyklejony pasek edycji (C4)

**File**: `resources/js/components/MonthPlanEditBar.vue` (nowy), `resources/js/pages/MonthPlan.vue`

**Intent**: w trybie edycji (`isEditable`) u dołu ekranu widać bieżące „Do wydania”, jedyny `InputError` dla `errors.items` i akcje, więc przy ostatnim wierszu listy nie trzeba przewijać do góry ani do dołu. Pasek zastępuje bloki `:808-850`.

**Contract**:

- propsy: `toSpendMinorUnits: number | null`, `mode: 'approve' | 'change'`, `processing: boolean`, `canSubmit: boolean`, `error?: string`;
- emit: `cancel` (tylko `mode: 'change'`);
- renderuje „Zatwierdź” albo „Anuluj” + „Zapisz zmiany”, z przyciskiem `type="submit"`;
- kwota przez `formatMoney`, `tabular-nums`, `text-destructive` poniżej zera;
- powierzchnia `bg-card` z górnym obramowaniem `border-border/60`, `sticky bottom-0`, bez cienia spoczynkowego;
- stoi jako ostatnie dziecko `<Form>`;
- `canSubmit` liczy strona: dla approve `allAmountsValid`, dla change `allAmountsValid && submittedAmounts.length > 0`;
- nowy klucz `month_plan.edit_bar_label` jako dostępna nazwa regionu (np. „Edycja planu”).

#### 4. Tłumaczenia fazy 3

**File**: `lang/pl/ui.php`, `resources/js/types/shell.ts`, `tests/Feature/MonthPlanPageTest.php`

**Intent**: nowe klucze w obu miejscach i asercja, że trafiają do propsów strony.

**Contract**: `month_plan.edit_bar_label` i `month_plan.kind_column` w `ui.php` i w `UiTranslations.month_plan`, oraz `->where('translations.month_plan.<key>', …)` dla każdego.

#### 5. Kitchen sink

**File**: `resources/js/pages/dev/MonthPlanSink.vue`

**Intent**: pokazać wiersz tytułu (odczyt z „Zmień plan”, propozycja bez akcji) i `MonthPlanEditBar` w obu trybach, z kwotą dodatnią i ujemną.

**Contract**: nowe sekcje strony.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/MonthPlanPageTest.php` przechodzi (obraz Sail, PostgreSQL)
- `npm run check`, `npm test`, `npm run types:check` (po `npm run build`) przechodzą
- Skan wartości zakodowanych na sztywno na plikach widoku daje 0 trafień; `grid-cols-[` na plikach widoku daje 0

#### Manual Verification:

- Telefon (390), propozycja z kilkunastoma pozycjami: „Do wydania” i „Zatwierdź” widoczne u dołu ekranu przy przewijaniu list; pasek nie zasłania ostatniego wiersza
- Plan zatwierdzony bieżącego miesiąca: „Zmień plan” w wierszu tytułu; po kliknięciu pasek z „Anuluj / Zapisz zmiany”; „Anuluj” przywraca kwoty
- Odznaki statusu (np. „Zapłacone”) stoją w jednej linii z nazwą kategorii, a przy 390 zawijają się pod nią bez rozpychania kolumn
- Nagłówki „Rodzaj / Plan / Faktycznie / Odchylenie” stoją nad swoimi kolumnami przy 390, 640 i 1280; przełącznik rodzaju jest w kolumnie od 640, a przy 390 pod nazwą
- Tab przez stronę: każdy przełącznik to jeden przystanek
- Zrzuty fazy 3 (strona i kitchen sink) zapisane w `screenshots/`

**Implementation Note**: po automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Stany (C3, C5, macierz 7 stanów)

### Overview

Przełącznik rodzaju komunikuje zakres i stan zapisu. Błędna kwota jest wskazana przy polu. Kitchen sink pokazuje wszystkie komórki macierzy.

### Changes Required:

#### 1. Zakres i zapis rodzaju (C3)

**File**: `resources/js/pages/MonthPlan.vue`, `resources/js/components/MonthPlanItemRow.vue`

**Intent**:

- Przełącznik rodzaju ma ton `neutral`, więc nie wygląda jak wybór formularza.
- Lista wydatków dostaje notę o zakresie jako `description` w `ListSurface`, gdy któryś jej wiersz ma `can_change_kind`.
- Podpowiedź trybu „Zmień” mówi, że rodzaj zapisuje się od razu i „Anuluj” go nie cofa.
- W czasie PATCH ten wiersz ma wyłączony przełącznik i `Spinner` obok.
- Błąd pokazuje się `InputError` pod przełącznikiem tego wiersza, nie nad listami.

**Contract**:

- strona trzyma `kindPendingCategoryId: number | null` i `kindError: { categoryId: number; message: string } | null`;
- pending jest czyszczony w `onFinish`, błąd w `onError`;
- `MonthPlanItemRow` dostaje propsy `kindPending?: boolean`, `kindError?: string`;
- usunąć `InputError` z `MonthPlan.vue:689`;
- nowy klucz `month_plan.kind_scope_note`, proponowana treść: „Rodzaj pozycji to ustawienie kategorii: zmiana zapisuje się od razu i dotyczy wszystkich miesięcy, także minionych.”;
- zmiana treści `month_plan.changing_hint`: dopisać „Rodzaj pozycji zapisuje się od razu i „Anuluj” go nie cofa.”

#### 2. Granice kwoty planu jako funkcja

**File**: `resources/js/lib/month-plan-amount.ts` (nowy), `resources/js/lib/month-plan-amount.test.ts` (nowy), `resources/js/pages/MonthPlan.vue`

**Intent**: wyjąć ze strony parsowanie kwoty z granicą `0…MAX_PLAN_AMOUNT_MINOR_UNITS` (`MonthPlan.vue:184-201,226-234`), żeby plan i odkładanie miały jedną regułę z testem. Granica musi zgadzać się z `ApproveMonthPlanRequest` / `UpdateMonthPlanRequest`.

**Contract**: `parsePlanAmountInput(input: string): number | null` i stała `MAX_PLAN_AMOUNT_MINOR_UNITS`. Test obejmuje: `''` → `null`; `'12,'` → `1200`; `'-1'` → `null`; `MAX` → `MAX`; `MAX + 1` → `null`; `'12,3,4'` → `null`.

#### 3. Błędna kwota przy polu (C5)

**File**: `resources/js/pages/MonthPlan.vue`, `resources/js/components/MonthPlanItemRow.vue`, `resources/js/components/MonthPlanEditBar.vue`

**Intent**:

- Pole kwoty planu i pole odkładania, które straciło fokus z błędną wartością, dostaje `aria-invalid="true"` (gotowe style `Input`) i `InputError` z krótkim komunikatem pod sobą. Oznaczenie znika od razu, gdy wartość staje się poprawna.
- Pasek edycji pokazuje „Niepoprawne kwoty: N” obok wyłączonego przycisku. Liczy wszystkie błędne pola, także niedotknięte, bo objaśnia wyłączenie.

**Contract**:

- strona trzyma zbiór dotkniętych `category_id` i flagę dotknięcia odkładania; oba czyszczone w `startChanging`, `stopChanging` i przy zmianie miesiąca lub budżetu;
- `MonthPlanItemRow` dostaje prop `invalid: boolean` i emituje `amount-blur`;
- `InputError` łączy się z polem przez `aria-describedby`;
- `MonthPlanEditBar` dostaje prop `invalidCount: number`;
- obecne auto-rozwijanie bezczynnych jednorazowych (`MonthPlan.vue:437-448`) zostaje;
- nowe klucze: `month_plan.amount_invalid` (proponowane: „Wpisz kwotę, np. 1200,50.”) i `month_plan.invalid_amounts` („Niepoprawne kwoty: :count”).

#### 4. Stan w toku przy zapisie planu

**File**: `resources/js/components/MonthPlanEditBar.vue`

**Intent**: przy `processing` przycisk wysyłający jest wyłączony i pokazuje `Spinner`.

**Contract**: bez nowych propsów poza istniejącym `processing`.

#### 5. Tłumaczenia fazy 4

**File**: `lang/pl/ui.php`, `resources/js/types/shell.ts`, `tests/Feature/MonthPlanPageTest.php`

**Intent**: nowe i zmienione klucze w obu miejscach z asercjami.

**Contract**: `kind_scope_note`, `amount_invalid`, `invalid_amounts` (nowe) i `changing_hint` (zmieniony). Każdy z `->where('translations.month_plan.<key>', …)`.

#### 6. Kitchen sink: macierz 7 stanów

**File**: `resources/js/pages/dev/MonthPlanSink.vue`

**Intent**: każda komórka pokazana albo oznaczona „N/A” z powodem.

**Contract**:

| Stan          | Co pokazuje kitchen sink                                                                                                                          |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| default       | wiersze odczytu i edycji, pasek edycji, wiersz tytułu                                                                                             |
| hover         | `SegmentedControl`, przyciski, wiersz zwijania jednorazowych, z instrukcją „najedź”                                                               |
| focus-visible | przełączniki, pola kwot, przyciski paska, z instrukcją „Tab”                                                                                      |
| disabled      | przycisk paska przy błędnej kwocie z podpisem liczby, przełącznik rodzaju w toku                                                                  |
| error         | pole kwoty z `aria-invalid` i komunikatem, pole odkładania, błąd rodzaju w wierszu, `errors.items` w pasku                                         |
| empty         | `IndexEmptyState` ×3 (brak budżetu, szkic, miniony bez planu) i „Brak pozycji w tym miesiącu”                                                     |
| loading       | przełącznik rodzaju ze `Spinner`, pasek z `processing`. Zmiana miesiąca to nawigacja Inertia z paskiem postępu, a propsy przychodzą w całości: brak skeletonu i przeskoku układu, N/A z tym powodem |

### Success Criteria:

#### Automated Verification:

- `npm test` przechodzi, w tym `month-plan-amount.test.ts`
- `php artisan test --compact tests/Feature/MonthPlanPageTest.php tests/Feature/CategoryMonthItemKindTest.php` przechodzi (obraz Sail, PostgreSQL)
- `npm run check` i `npm run types:check` (po `npm run build`) przechodzą
- Skan wartości zakodowanych na sztywno na plikach widoku daje 0 trafień

#### Manual Verification:

- W trybie „Zmień” przełączenie rodzaju: spinner na czas zapisu, podpowiedź o „Anuluj”, nota pod „Wydatki”
- Błąd zapisu rodzaju pokazuje się pod przełącznikiem tego wiersza
- Wyczyszczenie kwoty nie świeci na czerwono; „12,3,4” + Tab daje pole z `aria-invalid` i komunikatem; poprawienie zdejmuje oznaczenie
- Pasek pokazuje „Niepoprawne kwoty: 1” przy wyłączonym „Zatwierdź”
- Kitchen sink pokazuje każdą komórkę macierzy (lub N/A z powodem) w trybie jasnym i ciemnym
- Zrzuty fazy 4 zapisane w `screenshots/`

**Implementation Note**: po automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed fazą 5.

---

## Faza 5: Utrwalenie

### Overview

Bramka w CI, reguła dla agentów i dokumentacja, żeby kolejna zmiana w tym widoku nie wróciła do literałów.

### Changes Required:

#### 1. Test skanujący źródło

**File**: `tests/Unit/MonthPlanHardcodedUiTest.php` (nowy)

**Intent**: test Pest wzorem `tests/Unit/LandingPageSourceTest.php`, który failuje z plik:linia, gdy plik widoku zawiera literał koloru, klasę palety, wartość arbitralną lub arbitralną siatkę (research, Decyzja 5).

**Contract**:

- jawna lista plików: `resources/js/pages/MonthPlan.vue`, `components/MonthPlanItemRow.vue`, `MonthPaceMeter.vue`, `MonthSummaryCard.vue`, `MonthYearSummary.vue`, `BudgetMonthPicker.vue`, `SegmentedControl.vue`, `MonthPlanEditBar.vue`;
- regex skanu z `/10x-ui` przeniesiony do PCRE, plus wzorzec `grid-cols-\[`;
- `app.css` nie jest skanowany (źródło tokenów).

#### 2. Reguła dla agentów

**File**: `.ai/rules/js.md`

**Intent**: blok UI dopisany do istniejącego pliku reguł dla `resources/js/**/*.vue`:

- tokeny są w `app.css`;
- klasy `.month-plan-grid`, `.column-header`, `.section-label`, `.segmented-control*` są w `@layer components`;
- przełącznik wartości to `SegmentedControl`, nie `Button` z `aria-pressed`;
- w widokach nie ma wartości arbitralnych: nazwana klasa w `app.css` zamiast literału;
- kitchen sink żyje w `/dev/month-plan` (tylko `local`), a skan pilnuje `MonthPlanHardcodedUiTest`.

Uzupełnić listę gotowych elementów w istniejącej sekcji o `SegmentedControl`.

**Contract**: nowa sekcja `## …` w `.ai/rules/js.md`. Wiersz w `.ai/rules/index.md` już pokrywa ten plik.

#### 3. Dokumentacja systemu

**File**: `.docs/design-system.md`

**Intent**:

- dopisać nowe klasy i `SegmentedControl` (z tonami `primary` / `neutral` i ich znaczeniem: wybór w formularzu albo ustawienie zapisywane od razu);
- dopisać kitchen sink i procedurę zrzutów;
- przy `Heading` w „Gotowe elementy” dodać, że służy sekcjom ustawień, a strony domenowe budują wiersz tytułu wg `Budget.vue` (D4).

**Contract**: sekcje „Powierzchnie i rytm”, „Typografia”, „Gotowe elementy”.

#### 4. Research: statusy zarzutów

**File**: `context/changes/month-plan-ui-contract/research.md`

**Intent**: przy C1–C5 dopisać „Status: planned → faza N”, a D1–D6 zostawić jako deferred.

**Contract**: tylko sekcja `## Charges`.

### Success Criteria:

#### Automated Verification:

- `MonthPlanHardcodedUiTest` przechodzi i failuje z plik:linia po tymczasowym dopisaniu `text-[11px]`
- Pełna bramka CI w obrazie Sail: `composer ci:check`
- `vendor/bin/sloppy ci` na hoście bez nowych znalezisk
- `npm run check`, `npm test`, `npm run types:check` przechodzą

#### Manual Verification:

- Zrzuty końcowe `/month-plan` i kitchen sink, desktop i mobile, porównane z fazą 1
- Reguła w `.ai/rules/js.md` i `.docs/design-system.md` czytelne i zgodne z kodem

**Implementation Note**: po tej fazie przejść do `/10x-impl-review month-plan-ui-contract`. Ustalenia UI nie są domyślnie „kosmetyczne”.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/month-plan-amount.test.ts`: granice kwoty (pusty, przecinek końcowy, ujemna, `MAX`, `MAX+1`, śmieci).
- `tests/Unit/MonthPlanHardcodedUiTest.php`: brak literałów i arbitralnych siatek w plikach widoku.

### Integration Tests:

- `tests/Feature/DevMonthPlanSinkTest.php`: 404 poza `local`.
- `tests/Feature/MonthPlanPageTest.php`: nowe i zmienione klucze `translations.month_plan.*` w propsach strony.
- Istniejące `CategoryMonthItemKindTest.php`, `ApproveMonthPlanTest.php`, `ChangeMonthPlanTest.php` bez zmian, jako sprawdzenie, że zachowanie serwera nie drgnęło.

### Manual Testing Steps:

1. Propozycja na 390 px: przewiń listy, pasek z „Do wydania” i „Zatwierdź” stale widoczny; wpisz „12,3,4”, Tab: pole czerwone z komunikatem, w pasku „Niepoprawne kwoty: 1”.
2. Plan zatwierdzony: „Zmień plan” w wierszu tytułu → zmień rodzaj „Czynsz” → spinner, nota, podpowiedź; „Anuluj” cofa kwoty, rodzaj zostaje (zgodnie z notą).
3. Klawiatura: Tab przez stronę; każdy przełącznik to jeden przystanek, strzałki zmieniają opcję, fokus widoczny w obu motywach.
4. Kitchen sink: 7 stanów w trybie jasnym i ciemnym.

## Performance Considerations

Brak istotnych. Zbiór dotkniętych pól i liczba błędnych kwot to obliczenia na kilkudziesięciu wierszach, które już dziś są parsowane w `parsedPlanAmounts`.

## Migration Notes

Brak migracji danych. Trasa kitchen sink nie istnieje poza `local`. Zmiana treści `changing_hint` jest widoczna dla użytkowników od wdrożenia.

## References

- Research: `context/changes/month-plan-ui-contract/research.md`
- Kontrakt `/10x-ui`: `.claude/skills/10x-ui/SKILL.md` (macierz 7 stanów, bramka wizualna, „Utrwal to”)
- Wzorzec siatki: `resources/css/app.css:425-462`; wzorzec fokusu w klasie: `resources/css/app.css:194-196`
- Wzorzec wiersza tytułu: `resources/js/pages/Budget.vue:195-210`
- Wzorzec testu źródła: `tests/Unit/LandingPageSourceTest.php`
- Asercja tłumaczeń: `tests/Feature/AppShellTest.php:64`
- Decyzje domeny rodzaju: `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:52,68,70`, `context/archive/2026-11-02-approved-plan-changes/plan.md:51,228`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kitchen sink i punkt odniesienia

#### Automated

- [x] 1.1 Test trasy przechodzi na PostgreSQL (obraz Sail): `php artisan test --compact tests/Feature/DevMonthPlanSinkTest.php` — efee0b6
- [x] 1.2 Testy strony bez regresji: `php artisan test --compact tests/Feature/MonthPlanPageTest.php` — efee0b6
- [x] 1.3 `npm run build` i `npm run types:check` przechodzą — efee0b6
- [x] 1.4 `npm run check` przechodzi bez ostrzeżeń — efee0b6
- [x] 1.5 Pint: `vendor/bin/pint --dirty --format agent` — efee0b6

#### Manual

- [x] 1.6 `/dev/month-plan` w podglądzie z worktree renderuje wszystkie sekcje z danych testowych w trybie jasnym i ciemnym — efee0b6
- [x] 1.7 Zrzuty odniesienia desktop 1280 i mobile 390 zapisane w `screenshots/` — efee0b6

### Phase 2: Elementy kontraktu

#### Automated

- [x] 2.1 `npm run check` i `npm run types:check` przechodzą (po `npm run build`) — a5cf11c
- [x] 2.2 `npm test` przechodzi — a5cf11c
- [x] 2.3 Skan wartości zakodowanych na sztywno z `/10x-ui` na `SegmentedControl.vue` daje 0 trafień — a5cf11c

#### Manual

- [x] 2.4 W kitchen sink `SegmentedControl` ma jeden przystanek Tab, strzałki zmieniają fokus, Spacja/Enter wybiera, kliknięcie aktywnej opcji jej nie odznacza — a5cf11c
- [x] 2.5 Pierścień fokusu ma 3 px `ring/50` w obu tonach i obu motywach — a5cf11c
- [x] 2.6 `.month-plan-grid`: długa nazwa kategorii zawija się bez rozpychania kolumn kwot przy 390 i 1280 — a5cf11c
- [x] 2.7 Zrzuty fazy 2 zapisane w `screenshots/` — a5cf11c

### Phase 3: Widok (C1, C2, C4)

#### Automated

- [x] 3.1 `php artisan test --compact tests/Feature/MonthPlanPageTest.php` przechodzi (obraz Sail, PostgreSQL) — 011128f
- [x] 3.2 `npm run check`, `npm test`, `npm run types:check` (po `npm run build`) przechodzą — 011128f
- [x] 3.3 Skan wartości zakodowanych na sztywno na plikach widoku daje 0 trafień; `grid-cols-[` na plikach widoku daje 0 — 011128f

#### Manual

- [x] 3.4 Telefon (390), propozycja z kilkunastoma pozycjami: „Do wydania” i „Zatwierdź” widoczne u dołu ekranu przy przewijaniu list; pasek nie zasłania ostatniego wiersza — 011128f
- [x] 3.5 Plan zatwierdzony bieżącego miesiąca: „Zmień plan” w wierszu tytułu; po kliknięciu pasek z „Anuluj / Zapisz zmiany”; „Anuluj” przywraca kwoty — 011128f
- [x] 3.6 Nagłówki „Plan / Faktycznie / Odchylenie” stoją nad swoimi kwotami przy 390, 640 i 1280 — 011128f
- [x] 3.7 Tab przez stronę: każdy przełącznik to jeden przystanek — 011128f
- [x] 3.8 Zrzuty fazy 3 (strona i kitchen sink) zapisane w `screenshots/` — 011128f

### Phase 4: Stany (C3, C5, macierz 7 stanów)

#### Automated

- [x] 4.1 `npm test` przechodzi, w tym `month-plan-amount.test.ts` — 495c656
- [x] 4.2 `php artisan test --compact tests/Feature/MonthPlanPageTest.php tests/Feature/CategoryMonthItemKindTest.php` przechodzi (obraz Sail, PostgreSQL) — 495c656
- [x] 4.3 `npm run check` i `npm run types:check` (po `npm run build`) przechodzą — 495c656
- [x] 4.4 Skan wartości zakodowanych na sztywno na plikach widoku daje 0 trafień — 495c656

#### Manual

- [x] 4.5 W trybie „Zmień” przełączenie rodzaju: spinner na czas zapisu, podpowiedź o „Anuluj”, nota pod „Wydatki” — 495c656
- [x] 4.6 Błąd zapisu rodzaju pokazuje się pod przełącznikiem tego wiersza
- [x] 4.7 Wyczyszczenie kwoty nie świeci na czerwono; „12,3,4” + Tab daje pole z `aria-invalid` i komunikatem; poprawienie zdejmuje oznaczenie
- [x] 4.8 Pasek pokazuje „Niepoprawne kwoty: 1” przy wyłączonym „Zatwierdź” — 495c656
- [x] 4.9 Kitchen sink pokazuje każdą komórkę macierzy (lub N/A z powodem) w trybie jasnym i ciemnym — 495c656
- [x] 4.10 Zrzuty fazy 4 zapisane w `screenshots/` — 495c656

### Phase 5: Utrwalenie

#### Automated

- [ ] 5.1 `MonthPlanHardcodedUiTest` przechodzi i failuje z plik:linia po tymczasowym dopisaniu `text-[11px]`
- [ ] 5.2 Pełna bramka CI w obrazie Sail: `composer ci:check`
- [ ] 5.3 `vendor/bin/sloppy ci` na hoście bez nowych znalezisk
- [ ] 5.4 `npm run check`, `npm test`, `npm run types:check` przechodzą

#### Manual

- [ ] 5.5 Zrzuty końcowe `/month-plan` i kitchen sink, desktop i mobile, porównane z fazą 1
- [ ] 5.6 Reguła w `.ai/rules/js.md` i `.docs/design-system.md` czytelne i zgodne z kodem
