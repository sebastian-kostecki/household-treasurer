# Dashboard w systemie „C · Atrament i morski” — plan implementacji

## Overview

Dashboard (`resources/js/pages/Dashboard.vue` i jego komponenty) przechodzi na system „C · Atrament i morski”, tak jak przeszły już Budżet (ADR-0037), Operacje (ADR-0038/0039) i Plan miesiąca (ADR-0042). Jeden panel miar zastępuje trzy karty `StatTile` i pasek sum planu. Jeden panel „Realizacja budżetu” z zakładkami zastępuje dwie sekcje kategorii. Karty ostrzeżeń dostają styl `ht-notice`. Konta i Lokaty to dwa panele bez akordeonu na telefonie, a kreska postępu roku stoi zawsze, bez checkboxa. Zmiany układu przeczą specowi `.docs/specs/dashboard-hierarchy.md`, więc najpierw powstają ADR-0043 i wpis w `CONTEXT.md`.

## Current State Analysis

Pełny audyt jest w `context/changes/dashboard-atrament-ui/research.md` (zarzuty C1–C7, decyzje D1–D6). Najważniejsze:

- Miary to trzy osobne `StatTile` z `text-3xl` (`Dashboard.vue:191-241`, `StatTile.vue:29`), a pod nimi drugi panel miar `BudgetPlanSummaryStrip` (`Dashboard.vue:341-348`). Postęp roku jest tylko w etykiecie checkboxa (`Dashboard.vue:60-77, 269-283`, `localStorage` bez try/catch).
- Kategorie to dwa `DashboardCategorySection` (`Dashboard.vue:359-377`), każdy z limitem 5 i rozwinięciem (`dashboard-categories.ts:24-51`), z ręcznym paskiem sumy na dole (`DashboardCategorySection.vue:115-181`) i drugą regułą koloru (`sectionBarClass`, `dashboard-categories.ts:132-149`).
- Karty ostrzeżeń: `rounded-2xl`, tinty wagi na tle, `disabled:opacity-60`, `text-base font-semibold` (`DashboardNotices.vue:136-140, 331, 375-404`).
- `DashboardHoldings.vue` ma drugie drzewo na telefon (akordeon `lg:hidden`, `localStorage` `dashboard-holdings-expanded`, `:16-38, 83-100`) i typografię sprzed Atramentu (`:138-241`).
- Strona nie ma wiersza tytułu, a siatka to `lg:grid-cols-3` (`Dashboard.vue:250-251, 381`).
- Straże: `check:ui` (`scripts/check-ui-literals.mjs:114-117`) nie łapie typografii, ramek `/N` ani `opacity-N`. Dashboard nie ma odpowiednika `MonthPlanHardcodedUiTest.php`.

Weryfikacja kodu podczas planowania dołożyła cztery fakty:

- Kontroler wysyła tylko niezarchiwizowane kategorie, konta i lokaty (`DashboardController.php:39-52, 76-78`). Gałąź `is_archived` w `CategoryMeterRow.vue:28, 38-44` to martwy kod.
- `CategoryMeterRow`, `DashboardCategorySection`, `sectionBarClass`, `sectionPercentClass`, `VISIBLE_CATEGORY_LIMIT`, `DASHBOARD_SECTION_VISIBLE_CATEGORY_LIMIT`, `dashboardVisibleCategoryLimit`, `sliceVisibleCategories` i `hiddenDashboardCategoryCount` mają jedynego konsumenta: Dashboard. Klasy `.category-meter-*` (`app.css:978-1050`) też nie są używane poza nim. `ExecutionMeter`, `meterBarClass`, `showsYearElapsedMark`, `planChanged` i typy `DashboardTypeTotals` używa Budżet.
- Konto da się zarchiwizować z saldem ≠ 0 (`AccountPolicy.php:35-40` nie sprawdza salda), a `SpendableLiquidity::forUser` sumuje wszystkie konta, także zarchiwizowane (`SpendableLiquidity.php:12-15`). Suma widocznych wierszy kont może więc różnić się od Płynności dysponowalnej.
- `MonthPlanHardcodedUiTest.php:24-35` nie łapie `text-base` ani `divide-border/N`, a oba są w plikach Dashboardu.

## Desired End State

Dashboard od góry: wiersz tytułu („Dashboard”, linia meta z datą i stanem budżetu, Badge stanu, „Otwórz budżet”), checklista First Run (gdy aktywna), jeden `StatPanel` z miarami, strefa ostrzeżeń, a pod nimi siatka: lewa kolumna z panelem „Realizacja budżetu <rok>”, prawa kolumna 300 px z panelami Konta i Lokaty.

- **Panel miar:** w realizacji z planem miesiąca 4 miary: „<miesiąc> — do wydania” (`kpi-lg`, pasek dni, podpis „upływ dni d/n”, link Plan miesiąca przez POST `select-year`, Badge „propozycja” tylko dla propozycji), Płynność dysponowalna, Łączne środki, Postęp roku („N%”, podpis „na dzień <data> · tempo miesiąca N%” albo „—”). Bez planu miesiąca i w szkicu są 3 miary (bez Do wydania). Bez budżetu są 2 (Płynność i Łączne środki).
- **Realizacja budżetu:** jeden panel z zakładkami Wszystko / Dochody / Wydatki (`SegmentedControl` neutral, `segmented-control-tabs`, stan lokalny, każda wizyta zaczyna od „Wszystko”). Kolumny: Kategoria / Plan / Zrealizowano / Pozostało / Wykonanie / Status. Nad pozycjami każdego typu stoi wiersz grupy z sumą i `ExecutionMeter`. Widać wszystkie kategorie, bez limitu i bez rozwijania. Kreska postępu roku stoi zawsze tam, gdzie pozwala `showsYearElapsedMark`. Status to Badge „przekroczono” (`destructive-soft`). Wiersz kategorii jest linkiem do Operacji z filtrem tej kategorii za bieżący rok. Stopka mówi: „Kwoty w zł · kreska = postęp roku (N%) · kliknij wiersz, aby zobaczyć operacje”.
- **Szkic budżetu:** w miejscu tabeli `BudgetPlanSummaryStrip` (sumy planu z Saldem planowanym) i obecny stan pusty szkicu. **Brak budżetu:** stan pusty z `CreateBudgetDialog`, jak dziś.
- **Ostrzeżenia:** płaska siatka kart w stylu `ht-notice`. Karta ma neutralną ramkę i `rounded-md`, a hover zmienia kolor ramki. Wagę niesie tylko kafelek ikony (`warning-soft` / `destructive-soft`) i etykieta rodzaju. Treść jak dziś: tożsamość i kwota. Zostaje limit 6 kart i „Pokaż wszystkie (n)”. Przy wysyłanym POST karta nie traci kontrastu.
- **Konta / Lokaty:** dwa panele, każdy z akcją „Wszystkie” w nagłówku, wierszami (znak banku, nazwa, kwota) i stopką z sumą widocznych wierszy („Łączna płynność” / „Suma lokat”). Jeden markup na każdą szerokość, bez akordeonu i bez `localStorage`.
- **Straże:** `DashboardHardcodedUiTest` blokuje typografię, ramki i przezroczystość sprzed Atramentu w plikach Dashboardu. `/dev/ui/dashboard` pokazuje macierz 7 stanów, a zrzuty z podglądu pokazują hover, focus-visible i disabled.

Weryfikacja: testy z faz 2, 3 i 6, pełny `composer ci:check` w obrazie Sail, `vendor/bin/sloppy ci` na hoście i zrzuty z podglądu z worktree.

### Key Discoveries:

- `StatPanel` nie ma slotów, a kluczem `v-for` jest `metric.label` (`StatPanel.vue:16-45`). Konsumenci: `MonthPlanStatPanel.vue:92` i `BudgetPlanSummaryStrip.vue:59`. Oba tylko tekstowe, więc opcjonalne sloty ich nie zmieniają.
- `MonthPace::forMonth($toSpend, $year, $month, $today)` (`app/Support/MonthPace.php:19-56`) daje `elapsed_day`, `days_in_month` i `percent`. Kontroler ma już `$monthToSpendReading` (`DashboardController.php:101`). Typ TS `MonthPace` jest w `resources/js/types/month-plan.ts:5-13`, a wzorzec propsa w `MonthPlanController.php:219-221`.
- Wzorzec wiersza sumy z `ExecutionMeter` i nagłówka panelu z akcją jest w `BudgetCategoryList.vue:198-214, 317-322`. Zarchiwizowany wiersz Budżetu i Badge „przekroczono” są w `BudgetCategoryRow.vue:63-95`.
- Zakładki jako `SegmentedControl` neutral z klasą `segmented-control-tabs` są w `OperationsListPanel.vue:190-196`. Jedyny builder query `/operations` to `operationsListQuery` (`resources/js/lib/operations-list-query.ts:23`, reguła `.ai/rules/js.md`).
- Wiersz tytułu z `page-title` jest w `Budget.vue:170-200`. Logika pending i toastu `select-year` (`Dashboard.vue:46-59`, `lib/budget-year-selection`) to poprawka F1/F2 z `2026-11-03-dashboard-ui-contract`.
- ADR-0042:9 odrzucił osobny komponent `Notice`, więc karta ostrzeżenia zostaje w `DashboardNotices.vue` na nazwanej klasie.
- `planSummary` jest asertowany w wielu testach `DashboardTest.php` (`:52, 280, 375, 405, 450-452, 478, 566-567, 678, 707`) i niesie sumy grup (`incomeTotals` / `expenseTotals` pochodzą z niego, `DashboardController.php:84-86`). Props zostaje bez zmian, a widok renderuje pasek sum planu tylko w szkicu.

## What We're NOT Doing

- „Dodaj operację” / „Importuj wyciąg” w wierszu tytułu (dialogi operacji na Dashboardzie; spec daje Dashboardowi jedno zadanie, `dashboard-hierarchy.md:9, 76`).
- Paleta `⌘K` i skróty klawiszowe.
- Status „blisko limitu” w wierszu kategorii (brak progu w `CONTEXT.md`; Status pokazuje tylko „przekroczono”).
- Zdanie z liczbami w karcie ostrzeżenia (karta zostaje przy tożsamości i kwocie, bez nowych tekstów i pól payloadu).
- Zakładka w URL (`?tab=`): zakładka to stan lokalny.
- Stopki Kont/Lokat z propsów serwera i etykiety z mockupu („Płynność dysponowalna” / „Łączne środki”): stopki sumują widoczne wiersze.
- Przepinanie linków ostrzeżeń (`DashboardNotices.vue:242-270`) na `operationsListQuery`.
- Restyle `StatTile` w Kontach i Lokatach oraz `CardTitle text-base` w `ListSurface.vue:46` (inne widoki).
- Zmiana `planSummary`, `incomeTotals`, `expenseTotals` w kontrolerze i testach.
- Nowa kopia TS logiki miesiąca: pasek dni i tempo renderują liczby z serwera.
- Stany „zarchiwizowana kategoria / konto / lokata” na Dashboardzie: serwer ich nie wysyła.

## Implementation Approach

Kolejność faz jak w poprzednich zmianach Atramentu: dokumenty przed kodem (AGENTS.md, „Domain changes come before code”), potem wspólne prymitywy i straże, kontrakt propsów, widok w dwóch krokach (góra strony; tabela i Konta/Lokaty), na końcu macierz stanów i bramka wizualna. Każda faza kończy się zielonym `npm run check`, `npm run types:check` i testami, które dotyka, więc stan pośredni da się wdrożyć. Nowe układy idą do nazwanych klas w `@layer components` w `app.css`, a nie do utility w szablonie (`.ai/rules/js.md`, „Views take values from app.css”).

## Critical Implementation Details

- **Kolejność kontraktu propsów:** `month_pace` jest wymagany w `DashboardPageProps`, a fixtures są typowane tym typem. Kontroler, typ, wszystkie fixtures w `pages/dev/dashboard-fixtures.ts` i `assertInertia` idą w jednym commicie (AGENTS.md, „Inertia props contract”), inaczej `types:check` padnie.
- **Usuwane pliki a `check:ui`:** `scripts/check-ui-literals.mjs` czyta każdy plik z listy `FILES`. Usunięcie `DashboardCategorySection.vue` bez usunięcia go z listy psuje `npm run check:ui`. Lista zmienia się w tym samym commicie co pliki.
- **Link w wierszu siatki:** wiersz kategorii jako `Link` z Inertii renderuje `<a>` z klasą siatki. Fokus to 2 px `outline` z `outline-solid`, jak w ADR-0040 (nigdy `ring-3`). Hover przez nazwaną klasę wiersza, bo klasa z `@layer components` nie nadpisze utility z wariantem hover (przegląd month-plan F1).

## Faza 1: Decyzje i dokumenty

### Overview

Zapisać decyzje układu w ADR-0043, poprawić `CONTEXT.md:60` i oznaczyć zastąpione fragmenty specu, zanim powstanie kod. Bez zmian w kodzie aplikacji.

### Changes Required:

#### 1. ADR-0043

**File**: `.docs/adr/0043-dashboard-layout-follows-atrament.md` (nowy)

**Intent**: Zapisać układ Dashboardu w systemie Atrament i odrzucone alternatywy, w stylu ADR-0042 (krótkie akapity, bez list decyzji).

**Contract**: ADR obejmuje:

- kolejność strony: tytuł → checklista → panel miar → ostrzeżenia → realizacja + Konta/Lokaty; to odchodzi od `dashboard-hierarchy.md:9`;
- jeden `StatPanel` z miarami Do wydania (z paskiem dni z `month_pace`), Płynność, Łączne środki i Postęp roku; liczba miar zależy od stanu (4/3/2); sumy planu tylko w szkicu;
- jeden panel realizacji z zakładkami (stan lokalny, start od „Wszystko”), wierszem grupy nad pozycjami, kolumną Status („przekroczono”), wszystkimi kategoriami bez limitu i klikalnym wierszem do Operacji;
- kreska postępu roku zawsze widoczna, bez checkboxa;
- karta ostrzeżenia w stylu `ht-notice` z tożsamością i kwotą; reguły ADR-0032 zostają;
- Konta i Lokaty jako dwa panele ze stopką sumy widocznych wierszy, bez akordeonu na telefonie, prawa kolumna o stałej szerokości `--aside-width` (300 px) zamiast 2/3 + 1/3.

Odrzucone alternatywy: zakładka w URL, limit 5 z rozwinięciem, zdanie z liczbami w karcie, stopki z propsów serwera (zarchiwizowane konto z saldem rozjeżdża sumę), ostrzeżenia przed miarami, „Dodaj operację” w tym widoku (osobna zmiana). Wskazuje, co zastępuje: historyjki 15–17, 19–22, 25 (w części: realizacja), 28 oraz szczegóły „Headline tiles”, „Page grid”, „Budget area”, „Category sections”, „Holdings list” specu.

#### 2. CONTEXT.md

**File**: `CONTEXT.md:60` (wpis **Year Elapsed**)

**Intent**: Zastąpić zdanie o checkboxie Dashboardu nową regułą, bo zmiana dotyka terminu ze słownika (AGENTS.md).

**Contract**: Ostatnie zdanie wpisu mówi, że na Dashboardzie Postęp roku to miara w panelu miar z datą „na dzień”, a kreska stoi zawsze na miernikach, które mogą ją pokazać, bez przełącznika. Definicja (mark, nie kwota) i reguły „Income / Paced” bez zmian.

#### 3. Spec układu

**File**: `.docs/specs/dashboard-hierarchy.md`

**Intent**: Oznaczyć zastąpione fragmenty notami w stylu istniejących „(Superseded by S-07 / ADR-0033 …)”, bez przepisywania historii.

**Contract**: Noty „(Superseded by ADR-0043: …)” przy Solution (kolejność), historyjkach 15–17, 19–22, 25, 28 i szczegółach „Headline tiles”, „Page grid”, „Budget area”, „Category sections”, „Holdings list” oraz w „Attention zone UI” (karta w stylu `ht-notice`; siatka i limit bez zmian).

#### 4. Design system i reguły

**File**: `.docs/design-system.md`, `.ai/rules/js.md`

**Intent**: Uaktualnić opis gotowych elementów i reguły, które wskazują stary Dashboard.

**Contract**:

- `design-system.md`: ADR-0043 na liście ADR (`:3`); token `--aside-width` w tabeli rozmiarów; Dashboard wśród widoków Atramentu (`:25`); `StatPanel` ze slotami i `StatTile` tylko dla Kont i Lokat (`:81`); `SegmentedControl` neutral jako zakładki także na Dashboardzie (`:85-88`); karta ostrzeżenia Dashboardu (`:68-75`); kitchen sink `/dev/ui/dashboard` i jego stany (`:92-96`, `:124`).
- `.ai/rules/js.md`: przykład `Checkbox v-model` (`:28`) wskazuje inny plik z `Checkbox v-model` (znaleźć `grep`); w „Responsive lists” i „Tokens and shared components” dopisać Dashboard (`DashboardHardcodedUiTest`, siatka `.category-meter-row` ze Statusem i wierszem grupy, karta `.dashboard-notice`).

### Success Criteria:

#### Automated Verification:

- ADR-0043 istnieje i jest najwyższym numerem: `ls .docs/adr | tail -1`
- `CONTEXT.md` nie wspomina już checkboxa Dashboardu: `grep -n "Dashboard checkbox" CONTEXT.md` zwraca pusto
- Formatowanie dokumentów przechodzi: `npm run check`

#### Manual Verification:

- ADR-0043, wpis w CONTEXT i noty w specu opisują decyzje z tabeli „Key Decisions” briefu, bez sprzeczności między sobą

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Warstwa wspólna i straże

### Overview

Rozszerzyć `StatPanel` o sloty, dodać nazwane klasy w `app.css` i straż literałów Dashboardu. Widok jeszcze się nie zmienia; Budżet i Plan miesiąca wyglądają tak samo.

### Changes Required:

#### 1. StatPanel ze slotami

**File**: `resources/js/components/StatPanel.vue`

**Intent**: Pozwolić miarze nieść treść bogatszą niż tekst (Badge, formularz linku, pasek dni), bez zmiany dla obecnych konsumentów (D1).

**Contract**:

- `StatPanelMetric` dostaje opcjonalne `id?: string`. Kluczem `v-for` jest `id ?? label`.
- Opcjonalne sloty `label-extra-<id>` (obok etykiety) i `footer-<id>` (pod wartością i podpisem). Nazwy jak w API `StatTile`.
- Każda miara ma `role="group"` i `aria-labelledby` wskazujące etykietę. Id etykiety pochodzi z `useId()`, żeby dwa panele na stronie się nie zderzyły. To przeniesienie poprawki F6 z `StatTile`.

#### 2. Nazwane klasy

**File**: `resources/css/app.css`

**Intent**: Dać widokowi gotowe układy, bez utility i wartości w szablonach (reguła `css.md`).

**Contract**:

- Token rozmiaru `--aside-width: 300px` w `:root` (rozmiar, bez wariantu `.dark`).
- `.dashboard-grid`: jedna kolumna; od `lg` `minmax(0, 1fr) var(--aside-width)`.
- `.month-days`, `.month-days-day`, `.month-days-day-past`: pasek dni (`ht-days`, `bundle.css:68-70`), komórka na dzień, tor w tokenie `track`, dzień miniony w `foreground`.
- `.category-meter-row` i `.category-meter-header-row` dostają obszar `status`: od `md` ostatnia kolumna; na telefonie w drugiej linii za miernikiem. Nowe `.category-meter-cell-status`, `.category-meter-group-row` (tło `muted`, półgruby, kwoty `amount-sum`) i `.category-meter-row-link` (hover `secondary`, fokus 2 px `outline-solid`).
- `.dashboard-notice`, `.dashboard-notice-icon` z wariantami wagi (`-exceeded` / `-warning`): karta `card` z `border`, `rounded-md`, hover zmienia `border-color` na `muted-foreground`, kafelek ikony 24 px w `destructive-soft`/`destructive` albo `warning-soft`/`warning`. Stan pending (`.dashboard-notice[aria-busy='true']`): `cursor-wait`, bez zmiany przezroczystości.

#### 3. Straż literałów Dashboardu

**File**: `tests/Unit/DashboardHardcodedUiTest.php` (nowy), `scripts/check-ui-literals.mjs`

**Intent**: Zatrzymać powrót typografii, ramek i przezroczystości sprzed Atramentu (C7).

**Contract**: Test na wzór `MonthPlanHardcodedUiTest.php`: wzorce jak tam, plus `\btext-base\b` i `\bdivide-border/\d+`; lista plików Dashboardu. W tej fazie lista zawiera tylko pliki już czyste (`StatPanel.vue`, `DashboardKitchenSink.vue`, `dashboard-fixtures.ts`), a fazy 4 i 5 dopisują każdy oczyszczony plik, aż lista obejmie cały widok. `check-ui-literals.mjs` bez zmian w tej fazie.

### Success Criteria:

#### Automated Verification:

- Testy Vitest przechodzą: `npm test`
- Statyka i typy: `npm run check` oraz `npm run build && npm run types:check`
- Straże literałów: `npm run check:ui` i `DashboardHardcodedUiTest` + `MonthPlanHardcodedUiTest` w obrazie Sail: `docker run … artisan test --compact tests/Unit/DashboardHardcodedUiTest.php tests/Unit/MonthPlanHardcodedUiTest.php`

#### Manual Verification:

- Panele miar Budżetu (`/dev/kitchen-sink/budget`) i Planu miesiąca (`/dev/month-plan`) wyglądają jak przed zmianą

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Kontrakt propsów i logika

### Overview

Dodać props `month_pace` po obu stronach kontraktu i helper linku wiersza kategorii do Operacji, z testami. Widok jeszcze ich nie renderuje.

### Changes Required:

#### 1. Props `month_pace`

**File**: `app/Http/Controllers/DashboardController.php`, `resources/js/types/dashboard.ts`, `resources/js/pages/dev/dashboard-fixtures.ts`

**Intent**: Dać pasek dni i tempo miesiąca z serwera, z odczytu `MonthToSpend`, który kontroler już ma (D2). Bez nowych zapytań i bez kopii TS.

**Contract**:

- Kontroler: `'month_pace' => $monthToSpendReading === null ? null : MonthPace::forMonth($monthToSpendReading, $budget->year, $currentMonth, $today)`. `MonthPace` zwraca null, gdy stan planu to `none`.
- `DashboardPageProps.month_pace: MonthPace | null`, typ importowany z `@/types/month-plan`.
- Każdy fixture dostaje `month_pace`: `default` z `elapsed_day` 23 i `days_in_month` 30 (wrzesień, zgodnie z `year_elapsed_as_of: '2026-09-23'`) i `percent` liczbowym; `null` tam, gdzie `month_to_spend` jest null.
- Jeśli Sloppy oznaczy `show()` jako nowy dług, wydzielić odczyt miesiąca (`month_to_spend`, `month_pace`, `current_month`) do prywatnej metody.

#### 2. Testy kontraktu

**File**: `tests/Feature/DashboardTest.php`

**Intent**: Zablokować kształt propsa po stronie PHP (AGENTS.md).

**Contract**: `assertInertia` na wzór `MonthPlanPageTest.php:60-66`:

- budżet w realizacji z planem miesiąca: `month_pace.elapsed_day`, `month_pace.days_in_month` i `month_pace.percent` dla ustalonej daty (`travelTo`);
- `month_pace.percent` null, gdy plan puli ≤ 0;
- `month_pace` null bez budżetu, w szkicu i przy planie miesiąca w stanie `none`.

#### 3. Link wiersza kategorii

**File**: `resources/js/lib/dashboard-categories.ts`, `resources/js/lib/dashboard-categories.test.ts`

**Intent**: Jedno miejsce budowy celu wiersza, oparte na `operationsListQuery`, żeby wiersz odpowiadał na pytanie „skąd ta kwota” (decyzja Q4).

**Contract**: `categoryOperationsQuery(category: DashboardCategory, categoryType: 'income' | 'expense', year: number)` zwraca wynik `operationsListQuery({ ...EMPTY_OPERATIONS_FILTERS, category_name, category_type, date_from: '<year>-01-01', date_to: '<year>-12-31' }, DEFAULT_OPERATIONS_SECTION)`. Testy: dochód i wydatek, brak `section` w wyniku, zakres roku. Dopisać też testy `showsYearElapsedMark` (dochód zawsze; wydatek tylko `is_paced`), bo kreska staje się stała.

### Success Criteria:

#### Automated Verification:

- Testy Dashboardu na PostgreSQL w obrazie Sail: `docker run … artisan test --compact tests/Feature/DashboardTest.php tests/Feature/DashboardKitchenSinkTest.php`
- Vitest: `npm test`
- Typy po buildzie: `npm run build && npm run types:check`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

**Implementation Note**: Faza nie ma kroków ręcznych; po zielonej weryfikacji przejdź do fazy 4.

---

## Faza 4: Górna część widoku

### Overview

Wiersz tytułu, panel miar z czterema miarami i paskiem dni, nowe karty ostrzeżeń i siatka strony z prawą kolumną 300 px.

### Changes Required:

#### 1. Wiersz tytułu i kolejność strony

**File**: `resources/js/pages/Dashboard.vue`, `lang/pl/ui.php`

**Intent**: Powiedzieć, jaki to widok i dla jakiego dnia są odczyty, oraz ustalić kolejność z ADR-0043 (C5).

**Contract**:

- `h1.page-title` „Dashboard”; linia `meta` z datą z `year_elapsed_as_of` (gdy jest) i stanem budżetu roku („budżet :year w realizacji” / „w szkicu” / „brak budżetu na rok :year”); Badge stanu (realizacja `brand`, szkic jak dziś).
- Po prawej „Otwórz budżet” (`outline`, przez `select-year` albo `CreateBudgetDialog`, gdy roku nie ma w powłoce), przeniesione z nagłówka obszaru budżetu. Wzorzec wiersza: `Budget.vue:170-200`.
- Kolejność: tytuł → checklista → panel miar → ostrzeżenia → `.dashboard-grid`.
- Checklista: `CardTitle text-base` zastąpić `section-title`.
- Wspólny `yearSelectionPending` i nasłuch błędu `select-year` zostają na poziomie strony, dla obu formularzy.

#### 2. Panel miar

**File**: `resources/js/components/DashboardStatPanel.vue` (nowy), `resources/js/pages/Dashboard.vue`, `lang/pl/ui.php`

**Intent**: Jedna strefa liczb zamiast trzech kart i paska planu (C1), na wzór wrappera `MonthPlanStatPanel.vue`.

**Contract**:

- `computed` buduje listę `StatPanelMetric` z `id`: `to-spend` (gdy `month_to_spend` nie jest null), `liquidity`, `total-funds`, `year-elapsed` (gdy `year_elapsed_percent` nie jest null).
- `to-spend`: wartość `formatMoney`, ton `amountToneClass`, etykieta `t.dashboard.month_to_spend` z nazwą miesiąca, slot `label-extra-to-spend` z Badge `outline` „propozycja” tylko dla propozycji, slot `footer-to-spend` z paskiem `.month-days` (`aria-hidden`, gdy podpis niesie te same liczby), podpisem „upływ dni :elapsed/:days” i linkiem Plan miesiąca (POST `select-year`, `destination=month-plan`).
- `liquidity`: podpis „konta, bez lokat”. `total-funds`: podpis „z lokatami”.
- `year-elapsed`: wartość „N%”, podpis „na dzień <formatDate(as_of)>” i, gdy `month_pace` nie jest null, „· tempo miesiąca N%” albo „—” przy `percent` null.
- Formularz linku zgłasza start, koniec i błąd do strony, żeby `yearSelectionPending` i toast błędu działały jak dziś.
- Nowe klucze w `lang/pl/ui.php` pod `dashboard.*` dla podpisów.
- `StatTile` znika z Dashboardu, ale zostaje dla `Accounts.vue` i `TermDeposits.vue`.

#### 3. Karty ostrzeżeń

**File**: `resources/js/components/DashboardNotices.vue`

**Intent**: Uspokoić strefę uwagi: wagę niesie kafelek i etykieta, a nie tło całej karty (C3).

**Contract**:

- Nagłówek `h2.section-title` z pigułkami wagi jak dziś.
- Karta na `.dashboard-notice`: kafelek ikony `.dashboard-notice-icon-<waga>` (także dla ostrzeżeń miesiąca, bez ręcznego kafelka `bg-muted`), etykieta rodzaju w kolorze wagi, tożsamość (`CategoryChip` / `AccountIdentityBlock`) półgruba, kwota `formatMoney` z `amountToneClass` w typografii nazwanej (bez `text-base font-semibold`).
- Pending: `aria-busy="true"`, `Spinner` w kafelku ikony, bez `opacity`.
- Bez zmian: waga z serwera, kolejność, limit 6, „Pokaż wszystkie (n)”, cele linków.

#### 4. Siatka strony

**File**: `resources/js/pages/Dashboard.vue`

**Intent**: Stała prawa kolumna zamiast 1/3, która rośnie z oknem (C4).

**Contract**: `lg:grid-cols-3` / `lg:col-span-*` zastąpione `.dashboard-grid`. Lewa kolumna: obszar budżetu (w tej fazie z dotychczasowymi sekcjami). Prawa: `DashboardHoldings`.

### Success Criteria:

#### Automated Verification:

- Statyka, typy i straże: `npm run check`, `npm run build && npm run types:check`, `npm run check:ui`
- `DashboardHardcodedUiTest` obejmuje `Dashboard.vue`, `DashboardStatPanel.vue`, `DashboardNotices.vue` i przechodzi (obraz Sail)
- Testy Dashboardu: `docker run … artisan test --compact tests/Feature/DashboardTest.php tests/Feature/DashboardKitchenSinkTest.php`

#### Manual Verification:

- W `/dev/ui/dashboard` (podgląd z worktree) panel miar ma 4 miary w `default`, 3 w `draft` i 2 w `no-budget`; Badge „propozycja” tylko w `zero-amounts`
- Link Plan miesiąca i „Otwórz budżet” przełączają rok i otwierają cel; przy błędzie pojawia się toast
- Karty ostrzeżeń mają neutralną ramkę, a wagę widać tylko po kafelku i etykiecie, w jasnym i ciemnym motywie

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 5.

---

## Faza 5: Realizacja budżetu i Konta/Lokaty

### Overview

Jeden panel realizacji z zakładkami i wierszami grup, szkic z paskiem sum planu, dwa panele Konta i Lokaty oraz usunięcie checkboxa, akordeonu, limitu i martwego kodu.

### Changes Required:

#### 1. Panel „Realizacja budżetu”

**File**: `resources/js/components/DashboardExecutionPanel.vue` (nowy, zastępuje `DashboardCategorySection.vue`), `resources/js/pages/Dashboard.vue`, `lang/pl/ui.php`

**Intent**: Dochody i wydatki w jednej tabeli, z sumą nad pozycjami i bez przewijania dwóch kart (C2; decyzje Q1, Q2).

**Contract**:

- Props: `incomeCategories`, `expenseCategories`, `incomeTotals`, `expenseTotals`, `yearElapsedPercent`, `budgetYear`.
- `ListSurface` z `#header`: `h2.section-title` „Realizacja budżetu :year” i `SegmentedControl` neutral `segmented-control-tabs` (Wszystko / Dochody / Wydatki). Stan to `ref`, start „Wszystko”, bez zapisu.
- Wiersz nagłówka kolumn `.category-meter-header-row` w stylu `table-header` (Kategoria / Plan / Zrealizowano / Pozostało / Wykonanie / Status), widoczny od `md`.
- Dla każdego widocznego typu: wiersz grupy `.category-meter-group-row` (nazwa typu, sumy `formatMoneyAmount` w `amount-sum`, `ExecutionMeter` z `yearElapsedPercent` null, Status „przekroczono” przy wydatkach z `remaining < 0`), potem wszystkie wiersze kategorii. Typ bez kategorii: wiersz grupy i „Brak kategorii”.
- Stopka `meta`: „Kwoty w zł · kreska = postęp roku (N%) · kliknij wiersz, aby zobaczyć operacje”.

#### 2. Wiersz kategorii

**File**: `resources/js/components/CategoryMeterRow.vue`

**Intent**: Wiersz z kolumną Status, kwotami tabeli i linkiem do Operacji. Bez martwej gałęzi archiwum.

**Contract**:

- Korzeniem jest `Link` (Inertia) do `operations` z `categoryOperationsQuery(...)`, klasy `.category-meter-row .category-meter-row-link`.
- Kwoty przez `formatMoneyAmount` w `amount`. Dochód zrealizowany bez `text-success` w komórce (kolor niesie miernik; zgodnie z `BudgetCategoryRow`).
- `ExecutionMeter` z kreską wg `showsYearElapsedMark` zawsze, bez propsa `showYearElapsedMark`.
- `.category-meter-cell-status`: Badge `destructive-soft` „przekroczono” (`t.budget.over_limit_badge`) przy `is_over_limit` wydatku.
- Usunąć `opacity-70`, Badge „zarchiwizowana” i props `yearElapsedPercent` jako opcjonalny (staje się wymagany `number | null`).

#### 3. Szkic i brak budżetu

**File**: `resources/js/pages/Dashboard.vue`

**Intent**: Zachować historyjkę 25 dla szkicu, bez paska planu w realizacji (decyzja Q3).

**Contract**: `BudgetPlanSummaryStrip` renderowany tylko przy `budget.phase === 'draft'`, z obecnym stanem pustym szkicu pod nim. W realizacji `DashboardExecutionPanel`. Bez budżetu stan pusty z `CreateBudgetDialog`, jak dziś (`IndexEmptyState`: `h2 text-base font-medium` zamienić na `section-title`, sprawdzić inne widoki ze stanem pustym w ich kitchen sinkach).

#### 4. Konta i Lokaty

**File**: `resources/js/components/DashboardHoldings.vue`, `lang/pl/ui.php`

**Intent**: Dwa panele z jednym markupem na każdej szerokości (C4; decyzja Q6).

**Contract**:

- Dwa `ListSurface`, każdy z `#header`: `h2.section-title` („Konta” / „Lokaty”) i `Button` `ghost` `size="sm"` „Wszystkie” z ikoną strzałki, linkującym do `accounts.index` / `term-deposits.index`.
- Wiersze `.dashboard-holdings-row` z `AccountIdentityBlock` i kwotą `formatMoneyAmount` w `amount`, ton `amountToneClass`; separatory `border-soft` zamiast `divide-border/60`.
- Slot `footer`: etykieta („Łączna płynność” / „Suma lokat”, istniejące klucze `accounts.liquidity_total`, `term_deposits.total`), suma widocznych wierszy (`reduce` jak dziś) w `amount-sum` i „Kwoty w zł” w `meta`.
- Reguły pustych stanów ze specu bez zmian: konta puste przy lokatach → panel Konta z „Brak kont” bez stopki; brak lokat → brak panelu Lokaty; brak obu → jeden pusty `Card`.
- Usunąć akordeon `lg:hidden`, klucz `dashboard-holdings-expanded` i `matchMedia`.

#### 5. Sprzątanie

**File**: `resources/js/pages/Dashboard.vue`, `resources/js/lib/dashboard-categories.ts`, `resources/js/components/DashboardCategorySection.vue` (usunąć), `scripts/check-ui-literals.mjs`, `tests/Unit/DashboardHardcodedUiTest.php`, `lang/pl/ui.php`

**Intent**: Bez resztek po usuniętych mechanizmach (przegląd month-plan F6).

**Contract**:

- `Dashboard.vue`: usunąć `Checkbox`, `Label`, `YEAR_ELAPSED_STORAGE_KEY`, `showYearElapsed`, `yearElapsedLabel`, nagłówek „Budżet :year” i import `StatTile`.
- `dashboard-categories.ts`: usunąć `VISIBLE_CATEGORY_LIMIT`, `DASHBOARD_SECTION_VISIBLE_CATEGORY_LIMIT`, `dashboardVisibleCategoryLimit`, `sliceVisibleCategories`, `hiddenDashboardCategoryCount`, `sectionBarClass`, `sectionPercentClass` (potwierdzić `grep`, że nie ma innych konsumentów).
- `lang/pl/ui.php`: usunąć klucze bez użycia po zmianie (sprawdzić `grep` w `resources/js`), m.in. `show_more_categories`, `show_fewer_categories`, `income_totals`, `expense_totals`, `holdings_title`, `open_accounts`.
- `check-ui-literals.mjs`: usunąć `DashboardCategorySection.vue`; dodać `DashboardStatPanel.vue`, `DashboardExecutionPanel.vue` i `IndexEmptyState.vue`. `StatTile.vue` zostaje na liście, bo używają go Konta i Lokaty.
- `DashboardHardcodedUiTest.php`: lista obejmuje `Dashboard.vue`, `DashboardStatPanel.vue`, `DashboardNotices.vue`, `DashboardExecutionPanel.vue`, `CategoryMeterRow.vue`, `DashboardHoldings.vue`, `BudgetPlanSummaryStrip.vue`, `IndexEmptyState.vue`, `StatPanel.vue`, `DashboardKitchenSink.vue` i `dashboard-fixtures.ts`. Bez `StatTile.vue`: ma `text-3xl`, a jego restyle to zmiana Kont i Lokat.

### Success Criteria:

#### Automated Verification:

- Statyka, typy i straże: `npm run check`, `npm run build && npm run types:check`, `npm run check:ui`
- Vitest: `npm test`
- Nie zostały resztki: `grep -rn "dashboard-year-elapsed\|dashboard-holdings-expanded\|DashboardCategorySection\|sliceVisibleCategories" resources/js` zwraca pusto
- `DashboardHardcodedUiTest` obejmuje wszystkie pliki widoku i przechodzi, a testy Dashboardu są zielone (obraz Sail)

#### Manual Verification:

- W `default` panel realizacji pokazuje oba typy z wierszami grup nad pozycjami; zakładki przełączają widok, a po odświeżeniu strona wraca na „Wszystko”
- Klik w wiersz kategorii otwiera Operacje z filtrem tej kategorii i typu za bieżący rok
- Na 390 px Konta i Lokaty są rozwinięte pod panelem realizacji, bez przewijania w poziomie

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 6.

---

## Faza 6: Stany i bramka wizualna

### Overview

Dopisać brakujące stany do `/dev/ui/dashboard`, uruchomić podgląd z worktree i zrobić zrzuty w obu motywach. Pełna bramka CI.

### Changes Required:

#### 1. Scenariusze fixtures

**File**: `resources/js/pages/dev/dashboard-fixtures.ts`, `resources/js/pages/dev/DashboardKitchenSink.vue`, `tests/Feature/DashboardKitchenSinkTest.php`

**Intent**: Każda komórka macierzy 7 stanów ma miejsce przed przeglądem (D5, C7).

**Contract**: Nowe scenariusze (klucze kebab-case):

- `many-categories`: ponad 5 kategorii w każdej grupie, widoczne wszystkie;
- `income-over-100`;
- `overrun`: wydatek ponad limit ze Statusem „przekroczono” i wiersz grupy wydatków ponad 100%;
- `all-notice-kinds`: 7 rodzajów, ponad 6 kart, „Pokaż wszystkie”;
- `deposits-only`: konta puste przy istniejących lokatach;
- `month-start` i `month-end`: pasek dni dla `elapsed_day` 1 i równego `days_in_month`;
- `pace-unknown`: `month_pace.percent` null, podpis „—”;
- `no-month-plan`: realizacja bez `month_to_spend` i `month_pace`, 3 miary.

W `default` kreska postępu roku stoi na dochodach i wydatkach `is_paced`, a nie na jednorazowym. Tabela stanów w `DashboardKitchenSink.vue:12-41` wymienia nowe scenariusze, a hover, focus-visible i disabled odsyła do zrzutów z tej fazy. `DashboardKitchenSinkTest` dostaje asercję nowych kluczy, jeśli dziś asertuje listę.

#### 2. Podgląd i zrzuty

**File**: brak zmian w kodzie (procedura z AGENTS.md, „Preview from a worktree”)

**Intent**: Pokazać stany, których sink nie pokaże statycznie (przeglądy budget F7, design-tokens F1, month-plan F11).

**Contract**: Osobna baza podglądu, `migrate --seed`, `npm run build`, serwer na `http://localhost:8090` (`test@example.com` / `password`). Zrzuty 390 px i 1280 px, jasny i ciemny motyw: `/dashboard` i każdy scenariusz `/dev/ui/dashboard`; hover wiersza kategorii i karty ostrzeżenia; focus-visible na zakładkach, wierszu kategorii, karcie ostrzeżenia i „Wszystkie”; disabled (POST w toku) na linku Plan miesiąca, „Otwórz budżet” i karcie ostrzeżenia z POST. Zrzuty w `context/changes/dashboard-atrament-ui/screenshots/`.

### Success Criteria:

#### Automated Verification:

- Pełna bramka CI w obrazie Sail: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy na hoście: `vendor/bin/sloppy ci`
- Statyka, typy, straże i Vitest: `npm run check`, `npm run types:check`, `npm run check:ui`, `npm test`

#### Manual Verification:

- Każdy nowy scenariusz renderuje się w `/dev/ui/dashboard` bez błędów w konsoli
- Zrzuty 390/1280 px w obu motywach nie pokazują przepełnienia ani tekstu poniżej czytelności (w tym pusty tor paska w wierszu grupy na tle `muted`, przegląd budget F2)
- Hover, focus-visible i disabled są widoczne na zrzutach dla wymienionych elementów
- Dashboard z `DemoHouseholdSeeder` zgadza się z Budżetem: sumy wierszy grup równe sumom typu na stronie Budżet

**Implementation Note**: Po zielonej weryfikacji automatycznej i potwierdzeniu zrzutów zmiana jest gotowa do przeglądu (`/10x-impl-review`).

---

## Testing Strategy

### Unit Tests:

- `DashboardHardcodedUiTest`: wzorce literałów sprzed Atramentu w plikach widoku, z wynikiem plik:linia.
- Vitest `dashboard-categories.test.ts`: `categoryOperationsQuery` (dochód, wydatek, zakres roku, brak `section`) i `showsYearElapsedMark` (dochód zawsze, wydatek tylko paced); istniejące `meterBarClass` bez zmian.

### Integration Tests:

- `DashboardTest`: `month_pace` w realizacji z planem miesiąca (dni i tempo dla ustalonej daty), `percent` null przy planie puli ≤ 0, null bez budżetu, w szkicu i przy stanie `none`. Istniejące asercje `planSummary`, `incomeTotals`, `expenseTotals` i `dashboardNotices` bez zmian.
- `DashboardKitchenSinkTest`: nowe scenariusze, jeśli test asertuje listę.

### Manual Testing Steps:

1. Otwórz `/dashboard` w podglądzie: tytuł z datą, 4 miary, pasek dni zgodny z dzisiejszym dniem.
2. Kliknij Plan miesiąca w mierze Do wydania: otwiera się Plan miesiąca bieżącego roku.
3. Przełącz zakładki Dochody i Wydatki, odśwież stronę: wraca „Wszystko”.
4. Kliknij wiersz kategorii wydatku: Operacje z filtrem kategorii i typu za bieżący rok.
5. Zwęź okno do 390 px: jedna kolumna, Konta i Lokaty rozwinięte, bez przewijania w poziomie.
6. Przejdź po stronie klawiszem Tab: fokus widoczny na zakładkach, wierszach, kartach ostrzeżeń i akcjach paneli.

## Performance Considerations

`month_pace` korzysta z istniejącego odczytu `MonthToSpend` (`DashboardController.php:101`), więc nie dodaje zapytań. `MonthPace::forMonth` liczy w PHP po raz drugi to, co już liczy `DashboardNotices.php:222`, ale bez zapytań do bazy. Usunięcie limitu kategorii zwiększa liczbę renderowanych wierszy do liczby kategorii budżetu (dziesiątki), bez wpływu na payload, bo serwer już wysyła wszystkie.

## Migration Notes

Bez migracji danych. Klucze `localStorage` `dashboard-year-elapsed` i `dashboard-holdings-expanded` przestają być czytane i zostają w przeglądarkach jako martwe wpisy, bez wpływu na działanie.

## References

- Research: `context/changes/dashboard-atrament-ui/research.md`
- Wzorzec: artefakt https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (MockupCAtrament → Dashboard, StatPanel, Notice, DataTable, Tabs, Meter, Panel)
- Poprzednie zmiany Atramentu: `context/archive/2026-11-05-budget-atrament-ui/plan.md`, `context/archive/2026-11-05-month-plan-atrament-ui/plan.md`
- Wrapper `StatPanel`: `resources/js/components/MonthPlanStatPanel.vue:61-98`
- Wiersz sumy i nagłówek z akcją: `resources/js/components/BudgetCategoryList.vue:198-214, 317-322`
- Zakładki: `resources/js/components/OperationsListPanel.vue:190-196`
- Straż literałów: `tests/Unit/MonthPlanHardcodedUiTest.php`
- Spec: `.docs/specs/dashboard-hierarchy.md`; ADR-0031, ADR-0032, ADR-0037, ADR-0042

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0043 istnieje i jest najwyższym numerem — eb5640c
- [x] 1.2 CONTEXT.md nie wspomina już checkboxa Dashboardu — eb5640c
- [x] 1.3 Formatowanie dokumentów przechodzi (npm run check) — eb5640c

#### Manual

- [x] 1.4 ADR-0043, wpis w CONTEXT i noty w specu są zgodne z decyzjami briefu — eb5640c

### Phase 2: Warstwa wspólna i straże

#### Automated

- [x] 2.1 Testy Vitest przechodzą
- [x] 2.2 Statyka i typy przechodzą
- [x] 2.3 Straże literałów przechodzą (check:ui, DashboardHardcodedUiTest, MonthPlanHardcodedUiTest)

#### Manual

- [x] 2.4 Panele miar Budżetu i Planu miesiąca wyglądają jak przed zmianą

### Phase 3: Kontrakt propsów i logika

#### Automated

- [ ] 3.1 Testy Dashboardu na PostgreSQL przechodzą
- [ ] 3.2 Vitest przechodzi
- [ ] 3.3 Typy po buildzie przechodzą
- [ ] 3.4 Formatowanie PHP (Pint)
- [ ] 3.5 Sloppy bez nowych znalezisk

### Phase 4: Górna część widoku

#### Automated

- [ ] 4.1 Statyka, typy i straże przechodzą
- [ ] 4.2 DashboardHardcodedUiTest obejmuje Dashboard.vue, DashboardStatPanel.vue, DashboardNotices.vue i przechodzi
- [ ] 4.3 Testy Dashboardu przechodzą

#### Manual

- [ ] 4.4 Panel miar ma 4/3/2 miary w default/draft/no-budget, Badge propozycji tylko w zero-amounts
- [ ] 4.5 Plan miesiąca i Otwórz budżet przełączają rok, błąd daje toast
- [ ] 4.6 Karty ostrzeżeń mają neutralną ramkę w obu motywach

### Phase 5: Realizacja budżetu i Konta/Lokaty

#### Automated

- [ ] 5.1 Statyka, typy i straże przechodzą
- [ ] 5.2 Vitest przechodzi
- [ ] 5.3 Brak resztek po checkboxie, akordeonie i limicie
- [ ] 5.4 DashboardHardcodedUiTest obejmuje cały widok, testy Dashboardu zielone

#### Manual

- [ ] 5.5 Panel realizacji z wierszami grup i zakładkami resetowanymi po odświeżeniu
- [ ] 5.6 Klik w wiersz kategorii otwiera przefiltrowane Operacje
- [ ] 5.7 Na 390 px Konta i Lokaty rozwinięte, bez przewijania w poziomie

### Phase 6: Stany i bramka wizualna

#### Automated

- [ ] 6.1 Pełna bramka CI (composer ci:check w obrazie Sail)
- [ ] 6.2 Sloppy na hoście
- [ ] 6.3 Statyka, typy, straże i Vitest

#### Manual

- [ ] 6.4 Nowe scenariusze renderują się w /dev/ui/dashboard bez błędów
- [ ] 6.5 Zrzuty 390/1280 px w obu motywach bez przepełnienia i nieczytelnego tekstu
- [ ] 6.6 Hover, focus-visible i disabled widoczne na zrzutach
- [ ] 6.7 Sumy wierszy grup zgodne ze stroną Budżet
