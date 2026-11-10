# Raport okresu po grupach — plan implementacji

## Overview

S-28 z roadmapy M-3 (`context/foundation/prd-v3.md`, FR-014, FR-015): nowa strona „Raporty”. Użytkownik wybiera rok albo miesiąc budżetu i widzi przychody, wydatki i wynik oraz grupy z rozwinięciem do kategorii, z kwotą (i udziałem % dla wydatków), jako wykres i tabelę. Kwoty są co do grosza równe Wynikowi miesiąca i wykonaniu w Budżecie, a raport roku otwiera się w ≤ 2 s przy 10 000 operacji. Ten wycinek ustala też bibliotekę wykresów dla reszty strumienia B (S-29 rok do roku, S-30 trend, S-31 przejście do operacji, S-32 ostatnie 12 miesięcy).

## Current State Analysis

- **Raportów nie ma.** Nie ma trasy, kontrolera, strony ani pozycji „Raporty” w menu. Wynik miesiąca liczy tylko `BudgetMonthResult::forViewedMonth` (`app/Support/BudgetMonthResult.php:18-36`) i pokazuje go tylko Podsumowanie miesiąca (`app/Support/MonthSummary.php:65`). ADR-0033:13 i ADR-0037:5 zapowiadają, że te odczyty przejmie raport okresu.
- **Źródło kwot.** Pieniądze raportu są tylko w `financial_operations` (`type` income/expense, `amount_minor_units`, `budget_line_id`, `budget_id`, `operation_date`). Zwrot to wydatek z kwotą ujemną, więc `SUM` liczy netto. Transfery własne i ruchy kapitału są w osobnych tabelach (`own_transfers`, `capital_movements`) i nie mogą trafić do sum. Jeden budżet = jeden rok kalendarzowy, a `budget_id` operacji wynika z roku daty (`app/Actions/ManageFinancialOperation.php:222-227`).
- **Odczyt do ponownego użycia.** `MonthCategoryActuals::forMonths(Budget, list<int>)` (`app/Support/MonthCategoryActuals.php:26-60`) daje kwotę netto i `hasOperations` każdej linii budżetu w jednym zapytaniu dla dowolnego zakresu miesięcy, bez filtra typu i archiwum. Klucze to id linii (`BudgetLine`), nie kategorii listy.
- **Model.** Kategoria listy (`app/Models/Category.php`: `category_group_id`, `sort_order`, `type`, `archived_at`) jest jedna przez lata. Rok niesie `BudgetLine` (`execution_minor_units` to licznik prowadzony tylko przez `ManageFinancialOperation::applyEffect/revertEffect`, `:255-289`; fabryki go nie aktualizują). Grupa (`app/Models/CategoryGroup.php`) dzieli z kategoriami bez grupy jeden ciąg `sort_order` typu (ADR-0069). Archiwizacja nie zdejmuje kategorii z grupy (`app/Actions/ArchiveCategory.php`); archiwum ma dwa poziomy, a widoki roku czytają `archived_at` linii (`.ai/rules/categories.md`).
- **Kolejność.** `CategoryListOrder::tree(groups, categories)` (`app/Support/CategoryList/CategoryListOrder.php:55`) buduje drzewo listy na serwerze z tą samą regułą remisów co `buildCategoryTree` na kliencie (`resources/js/lib/category-tree.ts:41`).
- **Budżet sumuje grupy inaczej.** `BudgetPlanSummary::groupsOfType` (`app/Support/BudgetPlanSummary.php:142-172`) liczy w wierszu grupy tylko aktywne linie, a zarchiwizowane pokazuje w osobnej sekcji (ADR-0069:11). Raport celowo liczy inaczej (decyzja tego planu, niżej).
- **Okres i rok.** Rok jest w sesji (`SelectedBudgetYear::resolve`, `app/Support/SelectedBudgetYear.php:9-34`), miesiąc w `?month` (`BudgetViewedMonth::resolve`, `app/Support/BudgetViewedMonth.php:9-20`). Przełącznik roku w sidebarze (`resources/js/components/BudgetYearSwitcher.vue:64-78`) nie wysyła `destination`, więc zawsze przekierowuje na `/budget`. `SelectBudgetYearRequest` (`app/Http/Requests/SelectBudgetYearRequest.php:27`) zna tylko `budget|month-plan`, a `BudgetController::selectYear` jest w `:142-153`.
- **Menu.** Jedna lista widoków `resources/js/composables/useAppViews.ts:21-38`; numer skrótu to pozycja, a 7 widoków kończy się na Operacjach. Etykiety są w `lang/pl/ui.php:4-13`.
- **Frontend.** Gotowe: `StatPanel.vue` (`toneClass`), `SegmentedControl.vue`, `BudgetMonthPicker.vue` (`monthUrl`), `ListSurface`, `CategoryChip`, `IndexEmptyState`, `Badge`, `amountToneClass`, `formatMoney`/`formatMoneyAmount`. Wzorzec zwijanego wiersza grupy (`aria-expanded`, `aria-controls`) jest w `BudgetCategoryGroupRow.vue:78-92`, ale sam komponent jest związany z kolumnami Budżetu. Nie ma formatera procentów.
- **Wykresy.** Nie ma biblioteki ani `resources/js/components/ui/chart`. Tokeny `--chart-1..5` (`resources/css/app.css:151-155`, `:226-230`) to domyślne `hsl()` z shadcn, bez sprawdzonego kontrastu. `.ai/rules/reports.md` mówi „category colours from `category-appearance.ts`”, a design system zabrania, by kolor kategorii malował paski (`context/foundation/design-system.md`, sekcja Kolor).
- **Test wydajności jest zepsuty.** `tests/Feature/Nfr/NfrPerf01Test.php:160` wstawia nieistniejącą kolumnę `category_id` (po S-18 jest `budget_line_id`). Grupa `nfr` uruchamia się tylko na PostgreSQL z `phpunit.nfr.xml`, więc nikt tego nie zauważył.
- **Poprzednie podejście.** Zrecenzowany plan S-11 „period-report-by-category” (usunięty w `f7da16d`, czytelny przez `git show f7da16d^:context/changes/period-report-by-category/plan.md`) nie wiąże (`context/foundation/shape-notes.md:85`). Ten plan przejmuje z niego mechanikę okresu, etykiety wyniku i dowód równości przez trasy zapisu; zmienia mianownik udziału i kolejność.

## Desired End State

„Raporty” są ósmą pozycją menu (skrót 8). `/reports` pokazuje raport roku budżetu wybranego w sidebarze, a `/reports?month=N` raport miesiąca N tego roku. Przełącznik „Rok | Miesiąc” i picker miesiąca zmieniają okres, a zmiana roku w sidebarze zostaje na „Raportach”.

Na stronie jest panel miar Przychody, Wydatki i „Wynik” (miesiąc) albo „Wynik roku” (rok), z podpisem „okres w toku”, gdy okres obejmuje dziś. Pod nim są dwie sekcje: Wydatki, potem Przychody. Każda ma poziomy wykres słupkowy pierwszego poziomu (grupy i kategorie bez grupy) i pod nim tabelę z tymi samymi wierszami w kolejności listy, z grupami rozwijanymi do kategorii. Tabela wydatków ma kolumnę „Udział”. Zarchiwizowana kategoria stoi w swojej grupie z „zarchiwizowana”, a jej wiersz jest w `muted-foreground`. Puste stany obejmują: brak budżetu, budżet w przygotowaniu, okres bez operacji.

Weryfikacja:
- dla każdego miesiąca: przychody, wydatki i wynik raportu = `BudgetMonthResult`;
- dla roku: kwota każdej kategorii = `execution_minor_units` jej linii, a sumy typów = suma wykonania wszystkich linii (z zarchiwizowanymi);
- suma grupy = suma jej wierszy;
- test `nfr` ≤ 2 s;
- kitchen sink `/dev/ui/reports` z macierzą 7 stanów;
- `composer ci:check`, `vendor/bin/sloppy ci`.

### Key Discoveries:

- Jeden odczyt kwot na okres wystarczy: `MonthCategoryActuals::forMonths($budget, [m])` dla miesiąca i `forMonths($budget, range(1, 12))` dla roku (`app/Support/MonthCategoryActuals.php:26`).
- Drzewo listy na serwerze daje gotowa funkcja: `CategoryListOrder::tree` (`app/Support/CategoryList/CategoryListOrder.php:55`). Wejście: `groups: list<{id, sort_order, name_key}>`, `categories: list<{id, group_id, sort_order, name_key}>`.
- Licznik wykonania działa tylko przez `ManageFinancialOperation`, więc test równości z wykonaniem tworzy operacje trasami zapisu albo tą akcją, nie fabryką (lekcja F6 z S-10, przejęta przez plan S-11).
- `budget_line_id` operacji to linia roku. Na granicy HTTP i w propsach `category_id` to zawsze id kategorii listy (`.ai/rules/categories.md`).
- Skrót 8 działa bez zmian: `resolveGlobalShortcut` przyjmuje cyfry 1–9 (`resources/js/lib/global-shortcuts.ts:69`). Dopisanie na końcu nie przesuwa Operacji z 7 (`tests/e2e/global-shortcuts.spec.ts:25-31`).
- `npm run check:ui` skanuje tylko listę `FILES` w `scripts/check-ui-literals.mjs:9-156`; nowe pliki raportu trzeba dopisać.

## What We're NOT Doing

- Porównania z rokiem poprzednim (S-29), trendu miesięcy (S-30), przejścia z wiersza do Operacji (S-31) ani okresu „ostatnie 12 miesięcy” (S-32). Wiersz tabeli nie jest klikalny.
- Własnego zakresu dat, eksportu ani druku.
- Planu obok wykonania. Raport pokazuje tylko wykonanie (kwoty z operacji), bez limitów i „ponad plan”.
- Koloru kategorii na słupkach ani wykresu pierścieniowego. Kolor kategorii jest tylko w `CategoryChip`.
- Sortowania po kwocie. Kolejność to kolejność listy (decyzja użytkownika).
- Wierszy kategorii bez operacji w okresie ani grup bez takich kategorii.
- Zmian schematu bazy, snapshotu (`user:backup`) i Dashboardu.
- Zmiany wiersza grupy w Budżecie: Budżet zostaje przy ADR-0069 (tylko aktywne linie).
- Opisu kategorii w raporcie (ADR-0067:13).
- Indeksu `(budget_id, operation_date)`, chyba że test wydajności przekroczy 2 s (wtedy osobna decyzja z migracją na PostgreSQL).

## Implementation Approach

Najpierw reguły na papierze (słownik + ADR-0070), potem odczyt na serwerze z testami równości, następnie strona z tabelami (raport działa i jest testowany bez wykresu), a na końcu biblioteka wykresów z własnym ADR-0071. Odczyt `App\Support\PeriodReport` liczy wszystko na serwerze w minor units: sumy typów, drzewo w kolejności listy, kwoty grup i udziały w dziesiątych procenta. Klient tylko formatuje i rysuje. Ten sam kształt `entries` posłuży wykresowi i tabeli, a w S-29/S-30 dostanie kolejne kolumny.

ADR-y idą w kolejności powstania: ADR-0070 (reguły raportu) w Fazie 1, ADR-0071 (wykresy) w Fazie 3.

## Critical Implementation Details

**Reguły odczytu (ustalone w planowaniu, zapisywane w ADR-0070):**

- **Wiersze.** Kategoria ma wiersz, gdy jej linia ma w okresie co najmniej jedną operację (`hasOperations`), także z kwotą netto 0 po zwrocie. Tak samo dla zarchiwizowanych i jednorazowych. Grupa ma wiersz, gdy ma co najmniej jedną taką kategorię.
- **Grupa w raporcie.** Sumuje wszystkie swoje kategorie z wierszem, także zarchiwizowane. Zarchiwizowana kategoria stoi w swojej grupie (aktualne `category_group_id`, jak każda: przeniesienie zmienia wszystkie raporty, ADR-0063:15). Różni się to celowo od wiersza grupy w Budżecie (ADR-0069:11). „Razem” typu jest w obu widokach równe przy „Pokaż zarchiwizowane”.
- **Znacznik „zarchiwizowana”.** Bierzemy `archived_at` linii budżetu okresu, jak Budżet tego roku (`.ai/rules/categories.md`, „Archive has two levels”).
- **Kolejność.** Kolejność listy z `CategoryListOrder::tree` na obu poziomach, w tabeli i na wykresie.
- **Udział %.** Tylko dla wydatków, w każdym wierszu (grupa i kategoria), liczony od wydatków okresu netto (kwota z kafelka „Wydatki”). Wynik jest liczbą całkowitą w dziesiątych procenta, zaokrągloną od połowy od zera, każdy wiersz osobno. Ujemna kwota daje ujemny udział (bez paska). Gdy wydatki okresu ≤ 0, każdy udział to `null` („—”). Przychody nie mają udziału (FR-015).
- **Arytmetyka całkowita.** Dla `t > 0`: `share(a) = sign(a) * intdiv(abs(a) * 2000 + t, 2 * t)`. Przykład z planowania: wydatki 1 500, Meble 500 → 333 (33,3%), Elektronika −200 → −133 (−13,3%), Dom 300 → 200 (20,0%). Suma udziałów może odbiegać od 1000 o kilka dziesiątych przez zaokrąglenia, więc wiersz „Razem” pokazuje „100,0%” tylko gdy `t > 0`.
- **Okres.** `?month` w 1…12 (liczba całkowita) daje miesiąc. Brak albo inna wartość daje rok. To własne parsowanie, nie `BudgetViewedMonth::resolve`, które przy błędzie podstawia miesiąc (plan-review F4 z S-11). Domyślny miesiąc po przełączeniu na „Miesiąc” daje `BudgetViewedMonth::resolve(null, year, today)` (bieżący miesiąc w bieżącym roku, 1 albo 12 dla innych lat).

**Wydajność.** Raport roku to stała liczba zapytań: linie budżetu z `category.group` (z zarchiwizowanymi), grupy użytkownika i jedno `MonthCategoryActuals::forMonths`. Żadnego zapytania w pętli (Sloppy SL1xx). `forMonths` grupuje po (linia, dzień), więc rok daje najwyżej ~365 × liczba linii wierszy. Jeśli test `nfr` przekroczy 2 s, dodaj odczyt grupowany po linii z testem równości z `forMonths` i zapisz to odstępstwo w planie.

## Phase 1: Decyzje i odczyt raportu na serwerze

### Overview

Zapisuje reguły raportu w słowniku i ADR-0070, a potem buduje `App\Support\PeriodReport` z testami reguł i równości (SQLite i PostgreSQL). Po tej fazie odczyt jest gotowy i udowodniony, choć nikt go jeszcze nie wyświetla.

### Changes Required:

#### 1. Słownik i ADR

**File**: `context/foundation/glossary.md`, `context/foundation/adr/0070-period-report-reading.md`

**Intent**: Nazwać odczyt, zanim powstanie kod (AGENTS.md: zmiana reguły słownika zaczyna się od wpisu i ADR). ADR zapisuje decyzje z planowania i ich uzasadnienie, z odrzuconymi opcjami (osobna sekcja archiwum jak w Budżecie, mianownik z samych kwot dodatnich, sortowanie po kwocie).

**Contract**:
- Nowy wpis słownika **Period Report** (UI: „Raport”): odczyt za miesiąc albo rok jednego Budżetu; kwoty to Execution z Financial Operations datowanych w okresie, zwroty netto, bez Own Transfer i Capital Movement; wynik miesiąca = Month Result, roku = Year Result; reguły wierszy, grupy, kolejności i udziału jak w „Critical Implementation Details”. _Avoid_: „Period” jako nazwa Budżetu (bez zmian), „udział przychodów”.
- W **Category Group** dopisek: w Period Report grupa sumuje wszystkie swoje Categories z Execution w okresie, także zarchiwizowane (odsyłacz do ADR-0070).
- ADR-0070 w formacie sąsiednich ADR (Status, Context, Decision, Consequences), z odsyłaczami do ADR-0063, ADR-0069 i ADR-0033.

#### 2. Odczyt raportu

**File**: `app/Support/PeriodReport.php` (nowy, `php artisan make:class`)

**Intent**: Jedno miejsce, które z budżetu i okresu liczy cały raport w minor units, na `MonthCategoryActuals::forMonths` i `CategoryListOrder::tree`, w stałej liczbie zapytań.

**Contract**:
- `PeriodReport::forMonth(Budget $budget, int $month): self` i `PeriodReport::forYear(Budget $budget): self`.
- `toArray()` zwraca kształt, od którego zależą Fazy 2 i 3:

```php
/**
 * @return array{
 *   income_minor_units: int, expense_minor_units: int, result_minor_units: int,
 *   has_operations: bool,
 *   income: list<PeriodReportEntry>, expense: list<PeriodReportEntry>,
 * }
 * PeriodReportEntry = array{kind: 'group', id: int, name: string, amount_minor_units: int,
 *     share_tenths_percent: int|null, categories: list<PeriodReportCategory>}
 *   | PeriodReportCategory & array{kind: 'category'}
 * PeriodReportCategory = array{id: int (list Category id), name: string, icon: string|null,
 *     color: string, amount_minor_units: int, share_tenths_percent: int|null, is_archived: bool}
 */
```

- Kolejność przez `CategoryListOrder::tree`, wywołane osobno dla każdego typu. `tree()` oddaje same id (`{kind, id, category_ids}`), nie filtruje archiwum i nie patrzy na typ, a `name_key` jest wymagane (rozstrzyga remisy, `CategoryListOrder.php:357-363`). Wejście:
  - kategorie = `$line->category` linii z wierszem w okresie → `{id, group_id: category_group_id, sort_order, name_key}`;
  - grupy = grupy użytkownika tego typu → `{id, sort_order, name_key}`;
  - wpisy grup z pustym `category_ids` odpadają.
  Nazwy, ikony, kolory i kwoty dołącza `PeriodReport` po id.
- Sumy typów to sumy wierszy danego typu. `result = income − expense`. `share_tenths_percent` jest zawsze `null` dla przychodów.
- Typ wiersza bierzemy z typu kategorii. Test równości z `BudgetMonthResult` (grupującym po typie operacji) wykryje rozjazd, gdyby walidacja go przepuściła.

#### 3. Testy odczytu

**File**: `tests/Feature/PeriodReportTest.php` (nowy, `php artisan make:test --pest PeriodReportTest`)

**Intent**: Udowodnić reguły i równość z istniejącymi odczytami. Przypadki równości tworzą operacje przez `ManageFinancialOperation` (licznik wykonania), a przypadki reguł mogą używać fabryk.

**Contract**: przypadki opisane w „Testing Strategy” → „Integration Tests”. Plik uruchamia się na SQLite i na PostgreSQL (zakresy dat i `SUM` w surowym SQL).

### Success Criteria:

#### Automated Verification:

- Testy odczytu przechodzą na SQLite: `php artisan test --compact tests/Feature/PeriodReportTest.php`
- Testy odczytu przechodzą na PostgreSQL (worktree): `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature/PeriodReportTest.php`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- ADR-0070 i wpis Period Report w słowniku czyta się zgodnie z decyzjami tego planu (grupa z zarchiwizowanymi, udział od wydatków z kafelka, kolejność listy)

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Phase 2: Strona „Raporty” z tabelami

### Overview

Podłącza odczyt do nowej strony: trasa, kontroler, wybór okresu, pozycja w menu, cel przełącznika roku, panel miar, tabele Wydatki i Przychody z grupami, puste stany, kitchen sink i strażnicy UI. Naprawia `NfrPerf01Test` i dodaje test 2 s raportu. Po tej fazie raport działa w pełni bez wykresu.

### Changes Required:

#### 1. Trasa i kontroler

**File**: `routes/web.php`, `app/Http/Controllers/ReportController.php` (nowy)

**Intent**: Strona raportu w grupie `auth` + `verified`, z rokiem z sesji jak Budżet i Plan miesiąca oraz okresem z `?month`.

**Contract**:
- `GET reports` → `ReportController@show`, nazwa `reports`.
- Budżet przez `SelectedBudgetYear::resolve` i `$user->budgets()`, potem `authorize('view', $budget)`.
- Okres według reguły z „Critical Implementation Details”.
- Propsy Inertia `Reports`:
  - `budgetYear: int|null`
  - `budgetPhase: 'draft'|'execution'|null`
  - `period: {kind: 'year'|'month', month: int|null, default_month: int, is_in_progress: bool}`
  - `report: PeriodReport::toArray()|null` (null bez budżetu albo dla budżetu w przygotowaniu)

#### 2. Przełącznik roku zostaje na Raportach

**File**: `app/Http/Requests/SelectBudgetYearRequest.php`, `app/Http/Controllers/BudgetController.php`, `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: Zmiana roku w sidebarze na „Raportach” nie wyrzuca na `/budget`.

**Contract**:
- `destination` przyjmuje też `reports`, a `selectYear` przekierowuje wtedy na `reports` (rok, bez `month`).
- `BudgetYearSwitcher` wysyła `destination: 'reports'` tylko wtedy, gdy bieżąca strona to „Raporty” (`isCurrentSection` z `lib/nav-active.ts`). Na innych stronach zachowanie zostaje bez zmian.

#### 3. Menu i teksty

**File**: `resources/js/composables/useAppViews.ts`, `lang/pl/ui.php`, `resources/js/types/shell.ts`

**Intent**: „Raporty” jako ósma pozycja (skrót 8), ikona z `@lucide/vue` (np. `ChartColumn`).

**Contract**:
- `nav.reports => 'Raporty'` w `lang/pl/ui.php` i klucz `reports` w ręcznym typie `nav` (`resources/js/types/shell.ts:17`); `tests/Unit/UiTranslationKeysTest.php` pilnuje, że oba są zgodne.
- Listę widoków czytają też `AppSidebar.vue`, paleta poleceń (`AppSidebarLayout.vue`, `lib/command-palette-items.ts`), `useGlobalShortcuts.ts`, `SidebarKitchenSink.vue:54` i `LandingAppPreview.vue` (`:21`, `:34`, `:150`). Kod tych plików się nie zmienia. Podgląd na publicznej stronie Welcome pokazuje „Raporty” jak aplikacja, z podpowiedzią „1–8” (sprawdzić w kitchen sinku sidebara i na landingu).
- Nowy blok `reports.*` z tekstami strony: tytuł, „Rok”, „Miesiąc”, „Przychody”, „Wydatki”, „Wynik”, „Wynik roku”, „Udział”, „Razem”, „zarchiwizowana”, „okres w toku”, puste stany, „Kwoty w zł”.

#### 4. Strona i komponenty

**File**: `resources/js/pages/Reports.vue`, `resources/js/components/ReportStatPanel.vue`, `resources/js/components/ReportEntryTable.vue`, `resources/js/components/ReportGroupRow.vue`, `resources/js/types/reports.ts`, `resources/js/lib/format-share.ts` (+ `format-share.test.ts`), `resources/css/app.css`

**Intent**: Strona z istniejących elementów design systemu. Jedna tabela na typ, w jednym markupie dla każdej szerokości, z grupami rozwijanymi jak w Budżecie (wzorzec `aria-expanded`/`aria-controls`, domyślnie rozwinięte).

**Contract**:
- `types/reports.ts` jest lustrem `PeriodReport::toArray()` i propsów strony, a `defineProps<>` korzysta z tego typu (AGENTS.md: prop zmienia się po obu stronach w jednym commicie).
- `Reports.vue`:
  - `SegmentedControl` „Rok | Miesiąc” (`tone="neutral"`);
  - `BudgetMonthPicker` w trybie miesiąca, z `monthUrl` przez Wayfinder `reports({ query: { month } })`;
  - `StatPanel` z trzema miarami (wynik przez `amountToneClass`, wydatki bez tonu) i podpisem „okres w toku”, gdy `period.is_in_progress`;
  - sekcje Wydatki i Przychody;
  - puste stany przez `IndexEmptyState`.
- `ReportEntryTable`: `ListSurface` z nową klasą siatki `.report-grid` w `@layer components` (Kategoria | Kwota | Udział; Przychody bez kolumny Udział), nagłówek `table-header`, kwoty przez `formatMoneyAmount` i `amount`/`amount-sum`, wiersz „Razem”, stopka „Kwoty w zł”. Zarchiwizowany wiersz ma `text-muted-foreground` na wierszu i `Badge variant="neutral"` „zarchiwizowana”, jak `BudgetCategoryRow.vue:73,94` (bez tonu kwoty, bez `opacity`).
- `format-share.ts`: `formatShareTenths(value: number | null): string` daje `333 → "33,3%"`, `−133 → "−13,3%"`, `null → "—"`.

#### 5. Kitchen sink i strażnicy UI

**File**: `app/Http/Controllers/Dev/ReportsKitchenSinkController.php`, `routes/web.php`, `resources/js/pages/dev/ReportsKitchenSink.vue`, `resources/js/lib/reports-fixtures.ts`, `scripts/check-ui-literals.mjs`, `tests/Unit/ReportsHardcodedUiTest.php`, `.ai/rules/reports.md`, `.ai/rules/index.md`

**Intent**: Każdy stan widoku w `/dev/ui/reports` (AGENTS.md), nowe pliki pod strażnikami literałów, a reguła raportów obejmuje nowe komponenty.

**Contract**:
- Trasa `dev/ui/reports` (nazwa `dev.ui.reports`), 404 poza `local`, wzorzec `DashboardKitchenSinkController`.
- Macierz 7 stanów (default, empty, loading, error, disabled, hover, focus-visible, z „N/A” i powodem), wzorzec `dev/DashboardKitchenSink.vue`.
- Scenariusze: rok w toku, miesiąc zamknięty, grupa z zarchiwizowaną kategorią, zwrot netto z ujemnym udziałem, wydatki ≤ 0, okres bez operacji, budżet w przygotowaniu, brak budżetu, telefon.
- Nowe pliki trafiają do `FILES` w `check-ui-literals.mjs`, a `ReportsHardcodedUiTest` powtarza wzorzec `MonthPlanHardcodedUiTest`.
- `paths:` w `.ai/rules/reports.md` dostaje `resources/js/components/Report*.vue` i `resources/js/lib/format-share*.ts`. Potem `php artisan boost:index-rules`.

#### 6. Testy strony i wydajności

**File**: `tests/Feature/ReportsPageTest.php`, `tests/Feature/ReportsKitchenSinkTest.php`, `tests/Feature/BudgetTest.php` (testy `budget.select-year`, `:268-298`), `tests/Feature/Nfr/NfrPerf01Test.php`, `tests/Feature/Nfr/NfrPerf03ImportPreviewTest.php`, `tests/Feature/Nfr/NfrPerfReportTest.php`, `tests/Feature/Nfr/MeasuresRequestTime.php` (nowy trait)

**Intent**: Kontrakt propsów, okres, puste stany, izolacja użytkowników i 2 s, a przy okazji naprawa obu istniejących testów wydajności (cała grupa `nfr` musi być zielona, kryterium 2.2).

**Contract**:
- `NfrPerf01Test.php:160` dostaje `'budget_line_id' => $category->id` (linia budżetu).
- `NfrPerf03ImportPreviewTest.php:158` dostaje `'budget_line_id'` zamiast `'category_id'`; wartość zostaje (to już id linii, `:91-93`).
- Prywatne `assertRequestWithinSeconds` z `NfrPerf01Test.php:212-225` przechodzi do traitu `MeasuresRequestTime` w `tests/Feature/Nfr`, którego używają `NfrPerf01Test` i `NfrPerfReportTest` (bez kopii).
- `NfrPerfReportTest`: grupa `nfr`, 10 000 operacji rozłożonych na 12 miesięcy i ≥ 30 kategorii w ≥ 5 grupach, z zarchiwizowaną linią i zwrotami. `GET /reports` i `GET /reports?month=6` w ≤ 2 s (przez `MeasuresRequestTime`).
- Przypadki stron opisuje „Testing Strategy”.

### Success Criteria:

#### Automated Verification:

- Testy strony, kitchen sinka i wyboru roku przechodzą na PostgreSQL: `docker run … --entrypoint php sail-8.5/app artisan test --compact tests/Feature/ReportsPageTest.php tests/Feature/ReportsKitchenSinkTest.php tests/Feature/BudgetTest.php tests/Unit/ReportsHardcodedUiTest.php` (pełne polecenie jak w Fazie 1)
- Testy wydajności `nfr` (z naprawionym `NfrPerf01Test`) przechodzą na PostgreSQL: `docker run … --entrypoint php sail-8.5/app artisan test --group=nfr --configuration=phpunit.nfr.xml`
- Test formatera udziału przechodzi: `npm run test -- resources/js/lib/format-share.test.ts`
- Wayfinder i typy: `npm run build && npm run types:check`
- Strażnik literałów UI: `npm run check:ui`
- Pełna bramka w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- Na danych z devu „Raporty” (skrót 8) pokazują rok 2026; „Wydatki” równają się sumie wykonania wydatków na stronie Budżet przy „Pokaż zarchiwizowane”
- Raport zamkniętego miesiąca pokazuje „Wynik” równy „Faktycznie (Wynik)” w Podsumowaniu tego miesiąca w Planie miesiąca
- Grupy rozwijają się i zwijają, kolejność wierszy jest taka jak w Budżecie, zarchiwizowana kategoria stoi w swojej grupie wyszarzona
- Zmiana roku w sidebarze na „Raportach” zostaje na „Raportach”; na Budżecie i Planie miesiąca działa jak dotąd
- Kitchen sink `/dev/ui/reports` pokazuje wszystkie scenariusze w trybie jasnym i ciemnym, na telefonie bez poziomego przewijania strony

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Phase 3: Wykres

### Overview

Wprowadza bibliotekę wykresów z ADR-0071 i kolory wykresu w tokenach Atrament, a nad każdą tabelą stawia poziomy wykres słupkowy pierwszego poziomu. Dokumentuje wykresy w design systemie i dodaje test E2E strony.

### Changes Required:

#### 1. ADR i dokumentacja wykresów

**File**: `context/foundation/adr/0071-report-charts-shadcn-vue-chart-unovis.md`, `context/foundation/design-system.md`, `.ai/rules/reports.md`, `.ai/rules/js.md`

**Intent**: Zapisać wybór biblioteki dla całego strumienia B z odrzuconymi opcjami (paski CSS, własny SVG, pierścień) oraz usunąć sprzeczność reguł o kolorze kategorii.

**Contract**:
- ADR-0071 zawiera:
  - shadcn-vue Chart przez CLI na `@unovis/vue` i `@unovis/ts`, wersje dokładne (ustalone przy instalacji);
  - kolory: przychody `--success`, wydatki `--chart-1` z nową wartością Atrament w obu trybach;
  - kolor kategorii nie maluje słupków;
  - wykres jest `aria-hidden`, a dostępną formą danych jest tabela obok;
  - wygenerowane `components/ui/chart/**` są prymitywem shadcn: nie edytujemy ich dla jednego ekranu i nie trafiają do `check:ui`, a kod aplikacji używa tylko tokenów.
- `design-system.md` dostaje sekcję „Wykresy” (role `chart-1`, `success`, `track`, kontrast ≥ 3:1 dla grafiki w obu trybach, zasada „wykres + tabela”).
- `.ai/rules/reports.md`: zdanie o kolorach kategorii z `category-appearance.ts` zastępuje „category colour only in `CategoryChip`, never on bars”.

#### 2. Biblioteka i tokeny

**File**: `package.json`, `package-lock.json`, `resources/js/components/ui/chart/**` (CLI), `resources/css/app.css`

**Intent**: Dodać komponent `chart` przez CLI shadcn-vue zgodnie z `components.json` oraz nadać `--chart-1` wartość Atrament w trybie jasnym i ciemnym ze sprawdzonym kontrastem wobec tła panelu.

**Contract**:
- Zależności `@unovis/vue` i `@unovis/ts` w dokładnych wersjach. Menedżerem pakietów zostaje npm.
- `--chart-1` w hex w obu blokach motywu. `--chart-2..5` zostają bez zmian (nieużywane, do S-29/S-30).

#### 3. Wykres słupkowy

**File**: `resources/js/components/ReportBarChart.vue`, `resources/js/pages/Reports.vue`, `resources/js/pages/dev/ReportsKitchenSink.vue`, `scripts/check-ui-literals.mjs`

**Intent**: Poziome słupki pierwszego poziomu (grupy i kategorie bez grupy) nad tabelą każdej sekcji, z tych samych `entries` i w tej samej kolejności, jeden ton na typ.

**Contract**:
- Props: `entries: PeriodReportEntry[]`, `type: 'income' | 'expense'`.
- Etykieta osi to nazwa wiersza, wartość to kwota przez `formatMoney`.
- Kwoty ≤ 0 nie dostają słupka (wiersz zostaje w tabeli).
- Brak wierszy oznacza brak wykresu.
- Wykres jest `aria-hidden="true"`.
- Strona ładuje wykres asynchronicznie (`defineAsyncComponent`), żeby unovis nie trafił do bundla innych stron.
- Kitchen sink pokazuje wykres w scenariuszach z Fazy 2, a `ReportBarChart.vue` trafia do `FILES`.

#### 4. Test E2E

**File**: `tests/e2e/reports.spec.ts`

**Intent**: Smoke w przeglądarce na danych seed, wzorzec `tests/e2e/categories.spec.ts`.

**Contract**:
- Skrót 8 otwiera „Raporty”.
- Widać panel miar, oba wykresy i tabele.
- Przełączenie na „Miesiąc” i wybór miesiąca zmienia URL na `?month=N`.
- Zwinięcie grupy ukrywa jej kategorie.
- Zmiana roku w sidebarze zostaje na `/reports`.

### Success Criteria:

#### Automated Verification:

- Wayfinder, build i typy: `npm run build && npm run types:check`
- Strażnik literałów UI: `npm run check:ui`
- Testy strony nadal przechodzą na PostgreSQL: `docker run … --entrypoint php sail-8.5/app artisan test --compact tests/Feature/ReportsPageTest.php tests/Unit/ReportsHardcodedUiTest.php`
- Test E2E przechodzi: `npx playwright test tests/e2e/reports.spec.ts`
- Pełna bramka w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- Słupki wydatków i przychodów zgadzają się z tabelą pod nimi (te same wiersze, ta sama kolejność), w trybie jasnym i ciemnym, z czytelnym kontrastem
- Na telefonie wykres mieści się w szerokości ekranu, a długie nazwy grup nie nachodzą na słupki
- Inne strony (Budżet, Dashboard) nie ładują unovis (karta Network w przeglądarce)

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przez człowieka.

---

## Testing Strategy

### Unit Tests:

- `formatShareTenths`: 333 → „33,3%”, 1000 → „100,0%”, 0 → „0,0%”, −133 → „−13,3%”, `null` → „—”.
- Zaokrąglenie udziału w `PeriodReportTest`: połowa od zera (1 z 2 000 = 0,05% → 1 dziesiąta, −1 z 2 000 → −1, 1 z 4 000 = 0,025% → 0), duże kwoty bez przepełnienia.

### Integration Tests:

`tests/Feature/PeriodReportTest.php` (SQLite i PostgreSQL):
- Miesiąc: dla 12 miesięcy roku z operacjami w różnych dniach (także 1. i ostatni dzień miesiąca) przychody, wydatki i wynik = `BudgetMonthResult::forViewedMonth`.
- Rok: operacje przez `ManageFinancialOperation`, potem kwota każdej kategorii = `execution_minor_units` jej linii, sumy typów = suma wykonania wszystkich linii typu (z zarchiwizowanymi), wynik roku = suma 12 wyników miesięcy.
- Zwrot liczy się netto. Kategoria z kwotą 0 po zwrocie ma wiersz. Transfer własny i ruch kapitału nie zmieniają raportu. Operacje z sąsiedniego roku i innego użytkownika nie wchodzą.
- Grupa: suma = suma jej wierszy. Zarchiwizowana kategoria (linia zarchiwizowana) stoi w grupie z `is_archived: true` i wchodzi do sumy grupy. Grupa bez kategorii z operacjami nie ma wiersza. Kategoria bez operacji w okresie nie ma wiersza.
- Kolejność: grupy i kategorie bez grupy przeplatane według listy (ADR-0069), kategorie w grupie według listy, remis `sort_order` rozstrzygnięty jak w `CategoryListOrder`.
- Udział: przykład z planowania (wydatki 1 500; Żywność 1 000 → 667, Dom 300 → 200, Meble 500 → 333, Elektronika −200 → −133, Kino 200 → 133). Wydatki ≤ 0 → wszystkie `null`. Przychody zawsze `null`.

`tests/Feature/ReportsPageTest.php`:
- `assertInertia` na komponent `Reports` i kształt propsów dla roku i dla miesiąca.
- `?month=0`, `?month=13` i `?month=abc` dają rok.
- `is_in_progress` dla bieżącego roku i miesiąca (z `travelTo`).
- `default_month` (1, 12, bieżący).
- Brak budżetu oraz budżet w przygotowaniu dają `report: null` i odpowiednią fazę.
- Izolacja: drugi użytkownik z tym samym rokiem w sesji widzi tylko swoje kwoty; budżet innego użytkownika jest niedostępny (test-plan, ryzyko #8).
- Gość jest przekierowany na logowanie.

Wybór roku: `destination: 'reports'` przekierowuje na `reports`, a nieznana wartość daje błąd walidacji.

Kitchen sink: 200 w `local`, 404 poza nim.

`NfrPerfReportTest` (grupa `nfr`): rok i miesiąc w ≤ 2 s przy 10 000 operacji.

### Manual Testing Steps:

1. Na devie otwórz „Raporty” skrótem 8 i porównaj „Wydatki” roku z sumą wykonania wydatków na Budżecie (z „Pokaż zarchiwizowane”).
2. Przełącz na „Miesiąc”, wybierz zamknięty miesiąc z podsumowaniem i porównaj „Wynik” z Podsumowaniem miesiąca.
3. Zwiń i rozwiń grupę; sprawdź zarchiwizowaną kategorię w grupie i jej udział.
4. Zmień rok w sidebarze na „Raportach”, potem na Budżecie.
5. Przejrzyj `/dev/ui/reports` w trybie jasnym, ciemnym i na szerokości telefonu.

## Performance Considerations

Budżet to 2 s dla raportu roku przy 10 000 operacji (PRD v3, Guardrails; `prd-mvp.md` §10). Odczyt robi stałą liczbę zapytań (linie z `category.group`, grupy, jedno `forMonths`), a składanie drzewa i udziałów odbywa się w PHP na ≤ kilkuset wierszach. Indeks `(user_id, operation_date)` istnieje, ale filtr idzie po `budget_id` (indeks `budget_id`). Indeks złożony dodajemy tylko wtedy, gdy test `nfr` tego wymaga, z decyzją w planie i testem migracji na PostgreSQL. Wykres ładuje się asynchronicznie, więc unovis nie obciąża innych stron.

## Migration Notes

Brak migracji schematu i danych. Nowa trasa i nowa pozycja menu. Wdrożenie nie zmienia żadnej kwoty ani istniejącego widoku poza przełącznikiem roku (nowa wartość `destination`, wysyłana tylko z „Raportów”).

## References

- PRD: `context/foundation/prd-v3.md` (FR-014, FR-015, Guardrails, Business Logic Changes)
- Roadmapa: `context/foundation/roadmap.md` (S-28)
- Reguły: `.ai/rules/reports.md`, `.ai/rules/categories.md`, `.ai/rules/js.md`, `.ai/rules/inertia-props.md`, `.ai/rules/wayfinder.md`, `.ai/rules/js-sidebar.md`
- ADR: 0033, 0037, 0055, 0063, 0067, 0069
- Poprzednie podejście (bez grup, niewiążące): `git show f7da16d^:context/changes/period-report-by-category/plan.md`
- Grupy kategorii (S-22): `context/archive/2026-11-10-category-groups/plan.md`
- Odczyty: `app/Support/MonthCategoryActuals.php:26`, `app/Support/BudgetMonthResult.php:18`, `app/Support/CategoryList/CategoryListOrder.php:55`, `app/Support/BudgetPlanSummary.php:142`
- Wzorce testów: `tests/Feature/MonthPlanPageTest.php`, `tests/Feature/CategoriesPageTest.php:48-129`, `tests/Feature/BudgetMonthResultTest.php`, `tests/Feature/Nfr/NfrPerf01Test.php`, `tests/e2e/categories.spec.ts`
- Design system: `context/foundation/design-system.md`; artefakt Atrament (komponenty DataTable, StatPanel, Meter)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i odczyt raportu na serwerze

#### Automated

- [x] 1.1 Testy odczytu przechodzą na SQLite — ab78378
- [x] 1.2 Testy odczytu przechodzą na PostgreSQL (worktree) — ab78378
- [x] 1.3 Formatowanie PHP — ab78378
- [x] 1.4 Sloppy bez nowych znalezisk — ab78378

#### Manual

- [x] 1.5 ADR-0070 i wpis Period Report w słowniku czyta się zgodnie z decyzjami tego planu — ab78378

### Phase 2: Strona „Raporty” z tabelami

#### Automated

- [x] 2.1 Testy strony, kitchen sinka i wyboru roku przechodzą na PostgreSQL
- [x] 2.2 Testy wydajności nfr (z naprawionym NfrPerf01Test) przechodzą na PostgreSQL
- [x] 2.3 Test formatera udziału przechodzi
- [x] 2.4 Wayfinder i typy
- [x] 2.5 Strażnik literałów UI
- [x] 2.6 Pełna bramka w worktree
- [x] 2.7 Sloppy bez nowych znalezisk

#### Manual

- [x] 2.8 „Wydatki” roku równe sumie wykonania wydatków w Budżecie przy „Pokaż zarchiwizowane”
- [x] 2.9 „Wynik” zamkniętego miesiąca równy realnemu odłożeniu w Podsumowaniu miesiąca
- [x] 2.10 Grupy się zwijają, kolejność jak w Budżecie, zarchiwizowana kategoria w swojej grupie
- [x] 2.11 Zmiana roku w sidebarze na Raportach zostaje na Raportach
- [x] 2.12 Kitchen sink /dev/ui/reports we wszystkich scenariuszach, jasny, ciemny, telefon

### Phase 3: Wykres

#### Automated

- [ ] 3.1 Wayfinder, build i typy
- [ ] 3.2 Strażnik literałów UI
- [ ] 3.3 Testy strony nadal przechodzą na PostgreSQL
- [ ] 3.4 Test E2E przechodzi
- [ ] 3.5 Pełna bramka w worktree
- [ ] 3.6 Sloppy bez nowych znalezisk

#### Manual

- [ ] 3.7 Słupki zgadzają się z tabelą w trybie jasnym i ciemnym
- [ ] 3.8 Wykres mieści się na telefonie, długie nazwy nie nachodzą na słupki
- [ ] 3.9 Inne strony nie ładują unovis
