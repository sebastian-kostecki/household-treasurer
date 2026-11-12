# Z raportu do operacji — plan implementacji

## Overview

S-31 z roadmapy M-3 (`context/foundation/prd-v3.md`, FR-018, nice-to-have): w Raportach nazwa kategorii staje się linkiem do listy Operacji przefiltrowanej po tej kategorii listy i po okresie raportu. W widoku roku zakres to cały rok budżetu, w widoku miesiąca ten miesiąc, w Trendzie cały rok. Serwer, propsy Inertia i filtr Operacji zostają bez zmian. Test Feature dowodzi, że lista pokazuje dokładnie te operacje, z których raport liczy kwotę kategorii.

## Current State Analysis

- **Filtr kategorii listy już istnieje.** `OperationsListQuery::apply` filtruje operacje finansowe po `budgetLine.category_id`, a więc po wszystkich budżetach kategorii (`app/Support/OperationsListQuery.php:134-136`). Daty działają włącznie przez `whereDate` (`date_from`/`date_to`, `app/Http/Requests/IndexOperationsRequest.php`). `category_id` musi należeć do użytkownika (`IndexOperationsRequest.php:28-32`, bez warunku na archiwum).
- **Ta sama tabela i te same znaki.** Raport sumuje `financial_operations.amount_minor_units` per Budget Line dla `budget_id` i zakresu dat, bez filtra po typie (`app/Support/MonthCategoryActuals.php:35-41`). Zwrot to ujemna kwota wydatku i wchodzi netto. Transfery własne i ruchy kapitału leżą w osobnych tabelach. Przy aktywnym filtrze lista ukrywa transfery własne, a przy `category_id` także ruchy kapitału (`OperationsListQuery.php:78-95`).
- **Rok daty = rok Budżetu na każdej ścieżce zapisu.** Ręczny zapis wybiera Budżet po roku `operation_date` (`app/Actions/ManageFinancialOperation.php:222-227`). Import idzie przez `ManageFinancialOperation` (`app/Actions/ConvertImportPairFinancialOperation.php:19`), a walidator importu sprawdza kategorię względem roku daty (`app/Support/CsvImport/FinancialOperationImportValidator.php:49-55`). Restore odrzuca operację, której Budget Line należy do innego Budżetu (`app/Actions/RestoreUserData.php:786-788`). Filtr po samej dacie wybiera więc te same wiersze co `budget_id` + data w raporcie.
- **Wzorzec linku.** `categoryOperationsQuery(category, year)` buduje `{category_id, date_from: <rok>-01-01, date_to: <rok>-12-31}` przez jedyny budowniczy query `operationsListQuery` (`resources/js/lib/dashboard-categories.ts:35-48`, `resources/js/lib/operations-list-query.ts:24-37`). Używają go wiersz Pulpitu (`CategoryMeterRow.vue:27-31`, cały wiersz jako `<Link>`, klasa `.category-meter-row-link` w `resources/css/app.css:2013-2015`) i paleta ⌘K.
- **Wiersze raportu.** `ReportCategoryRow.vue` renderuje komórkę nazwy jako `CategoryChip` + Badge archiwum (`:56-64`). Kwota to „—”, gdy `has_operations` jest `false`, czyli dla wiersza istniejącego tylko przez porównanie (`:65-71`, ADR-0074). Wiersz dostaje `id` kategorii listy (`resources/js/types/reports.ts:4-5`). `ReportEntryTable` dostaje `budgetYear`, ale nie dostaje miesiąca (`ReportEntryTable.vue:13-26`).
- **Trend.** `ReportTrendTable.vue` renderuje wiersze kategorii (bez grupy `:72-88` i w grupie `:103-139`) w `ReportTrendRow` z rolami ARIA `row`/`rowheader`/`cell`. Nazwa leży w przypiętej komórce `.report-trend-name` (`app.css:1626-1636`). Tabela nie dostaje roku (`ReportTrendTable.vue:11-19`). Wiersz Trendu istnieje tylko dla kategorii z operacjami w roku (wiersze roku `PeriodReport` bez porównania), więc nie ma tam `has_operations`.
- **Strona.** `Reports.vue` zna `budgetYear` i `period.month` (`:146-147`) i przekazuje je do tabel (`:170-198`, `:124-139`). Kitchen sink `/dev/ui/reports` (`resources/js/pages/dev/ReportsKitchenSink.vue`, scenariusze w `resources/js/lib/reports-fixtures.ts:605`) renderuje te same tabele. Fixtures mają już wiersze z `has_operations: false` (`reports-fixtures.ts:146`, `:298`).
- **Strażnicy.** `tests/Unit/ReportsHardcodedUiTest.php:7-21` i `scripts/check-ui-literals.mjs:120-132` blokują w plikach raportu literały kolorów i wartości arbitralne, więc wygląd linku musi być nazwaną klasą w `app.css`. `resources/js/ui-contract.test.ts:519-528` pilnuje, żeby wiersz listy Kategorii linkował do Operacji przez Wayfinder, a nie ręcznym `href="/operations`.
- **Teksty.** `t.reports.*` pochodzi z `lang/pl/ui.php:380-` i jest typowane jako `Record<string, string>` (`resources/js/types/shell.ts:42`). Nazwy miesięcy to `t.budget.month_name_N` (`lang/pl/ui.php:345`). Chip zakresu dat na liście Operacji pokaże „01.03.2026 – 31.03.2026” (`OperationsActiveFilterChips.vue:108-125`).
- **Testy.** Testy raportu to Pest (`tests/Feature/PeriodReportTest.php`) i klasy PHPUnit (`tests/Feature/ReportsPageTest.php`). Rok raportu bierze się z sesji (`withSession(['budget_year' => 2026])`, `ReportsPageTest.php:178`). Lista Operacji podaje operacje finansowe w propsie `operations` (`app/Http/Controllers/OperationsController.php:172`) z polami `amount_minor_units`, `category_id`, `operation_date` (`:48-60`).

## Desired End State

W tabelach Wydatki i Przychody widoku roku i miesiąca oraz w tabelach kategorii Trendu nazwa każdej kategorii z kwotą w bieżącym okresie jest linkiem. Kliknięcie „Jedzenie” w „Raport: marzec 2026” otwiera `/operations?category_id=<id>&date_from=2026-03-01&date_to=2026-03-31` z chipami „Jedzenie” i „01.03.2026 – 31.03.2026”. Widoczne operacje sumują się do kwoty z raportu, zwroty liczą się netto, a transfery własne i ruchy kapitału są ukryte. W widoku roku i w Trendzie zakres to `2026-01-01..2026-12-31`.

Wiersz grupy, kolumna „rok wcześniej”, komórki miesięcy i „Razem” Trendu oraz wiersz istniejący tylko przez porównanie (kwota „—”) linku nie mają. Link w spoczynku wygląda jak dzisiejszy chip. Po najechaniu nazwa jest podkreślona, a pod chipem pojawia się tło `secondary`. Fokus z klawiatury pokazuje obwódkę `ring`. Czytnik ekranu ogłasza „Operacje kategorii Jedzenie, marzec 2026” (w widoku roku i Trendzie „…, 2026”).

Weryfikacja: test Vitest funkcji celu, test Feature równości kwot (PostgreSQL), strażnik `ui-contract`, `composer ci:check` i ręczne kliknięcie w przeglądarce.

### Key Discoveries:

- Wiersz raportu ma już identyfikator, którego potrzebuje filtr (`id` = kategoria listy, `resources/js/types/reports.ts:4-5`), więc link składa się po stronie klienta bez nowych danych z serwera.
- `categoryOperationsQuery` (`resources/js/lib/dashboard-categories.ts:35`) daje zakres roku. Miesiąc wymaga tylko wyliczenia ostatniego dnia, łącznie z lutym roku przestępnego.
- W Trendzie link nie może być całym wierszem: `role="row"` z linkiem w środku łamie semantykę tabeli ARIA. Link idzie do komórki `rowheader`.
- Raport liczy miesiąc w toku w całości, bez cięcia na dziś (`MonthCategoryActuals.php`), więc zakres do ostatniego dnia miesiąca albo roku pokazuje ten sam zbiór także w okresie w toku.

## What We're NOT Doing

- Link z wiersza grupy (Raport i Trend). Grupa zostaje przełącznikiem rozwiń/zwiń, a filtr Operacji zostaje płaski (ADR-0069 bez zmian). Nie powstaje filtr `category_group_id` ani ADR-0079.
- Link z kolumny „rok wcześniej” i z wiersza istniejącego tylko przez porównanie (`has_operations: false`).
- Link z komórek miesięcy i z „Razem” w Trendzie. Link z nagłówka miesiąca odrzucił już ADR-0077.
- Link z wykresów (są `aria-hidden`) i z `ReportStatPanel`.
- Suma przefiltrowanej listy Operacji. Widok Operacji się nie zmienia, a równość kwot dowodzi test Feature.
- Link „Wróć do raportu” na liście Operacji. Powrót załatwia historia przeglądarki.
- Zmiany w `PeriodReport::toArray()`, `ReportTrend::toArray()`, `resources/js/types/reports.ts` i w propsach Inertia.
- Nowy test E2E. Kliknięcie sprawdzamy ręcznie, a cel linku pokrywają testy jednostkowe i Feature.

## Implementation Approach

Cel linku powstaje w jednej czystej funkcji w `resources/js/lib`, która zwraca query `/operations` dla kategorii, roku budżetu i opcjonalnego miesiąca albo `null`, gdy wiersz nie ma kwoty w bieżącym okresie. Funkcja deleguje do `categoryOperationsQuery`, a dla miesiąca do `operationsListQuery`, żeby query `/operations` nadal powstawało w jednym miejscu. Komponenty wierszy owijają `CategoryChip` w Inertia `<Link>` z Wayfinder `operations({ query })` tylko wtedy, gdy funkcja zwraca query. W przeciwnym razie zostaje dzisiejszy chip. Rok i miesiąc przechodzą propsami z `Reports.vue` (i kitchen sinka) przez tabele do wierszy.

Faza 1 najpierw dowodzi, że zbiór wierszy listy jest zbiorem raportu. Ten test przechodzi od razu, bo zachowanie istnieje, a jego zadaniem jest przypięcie kontraktu, na którym stoi link. Funkcja celu powstaje test-first. Faza 2 podłącza linki w widokach.

## Phase 1: Cel linku i dowód równości

### Overview

Funkcja `reportCategoryOperationsQuery` z testem Vitest oraz test Feature, który dowodzi, że lista Operacji z tym query pokazuje dokładnie operacje składające się na kwotę kategorii w raporcie miesiąca i roku.

### Changes Required:

#### 1. Funkcja celu linku

**File**: `resources/js/lib/report-category-operations.ts` (nowy), `resources/js/lib/report-category-operations.test.ts` (nowy)

**Intent**: Jedno miejsce, które zamienia wiersz raportu i okres na query listy Operacji i rozstrzyga regułę „link tylko przy kwocie w bieżącym okresie”. Komponenty nie liczą dat same.

**Contract**: `reportCategoryOperationsQuery(category: { id: number; has_operations?: boolean }, year: number, month: number | null): Record<string, string | number | undefined> | null`.
- `has_operations === false` → `null`. Brak pola (wiersz Trendu) oznacza wiersz z operacjami w roku.
- `month === null` → wynik `categoryOperationsQuery(category, year)` (`<rok>-01-01..<rok>-12-31`).
- `month` 1–12 → `operationsListQuery({ ...EMPTY_OPERATIONS_FILTERS, category_id, date_from: <rok>-<MM>-01, date_to: <rok>-<MM>-<ostatni dzień> }, DEFAULT_OPERATIONS_SECTION)`, z miesiącem i dniem dopełnionymi zerem.
- Ostatni dzień liczony w UTC (`new Date(Date.UTC(year, month, 0)).getUTCDate()`), żeby strefa czasowa przeglądarki go nie przesuwała.

Przypadki testowe: miesiąc (marzec 2026 → `2026-03-01..2026-03-31`), luty zwykły (`2026-02-28`), luty przestępny (`2028-02-29`), styczeń i grudzień (dopełnienie zerem, `-12-31`), rok (`month = null`, równy `categoryOperationsQuery`), `has_operations: false` → `null` dla roku i miesiąca, brak `has_operations` → query. Pozostałe klucze (`q`, `year`, `account_id`, `term_deposit_id`, `section`) mają być `undefined`, jak w `dashboard-categories.test.ts:25-36`.

#### 2. Dowód „kwota raportu = wiersze listy”

**File**: `tests/Feature/ReportToOperationsTest.php` (nowy, Pest: `php artisan make:test --pest ReportToOperationsTest`)

**Intent**: Przypiąć kontrakt, na którym stoi link: dla kategorii listy i zakresu dat okresu lista Operacji pokazuje dokładnie operacje raportu. Suma ich `amount_minor_units` równa się `amount_minor_units` wiersza kategorii w raporcie, a transfery własne i ruchy kapitału są ukryte.

**Contract**: Dane jednego użytkownika: Budżet 2026 w wykonaniu i Budżet 2025, kategoria wydatku „Jedzenie” z liniami w obu budżetach i druga kategoria wydatku w 2026. Operacje „Jedzenie”: wydatki 2026-03-01 i 2026-03-31 (granice miesiąca), zwrot (ujemna kwota) w marcu 2026, wydatek w kwietniu 2026 i wydatek w marcu 2025. Do tego wydatek drugiej kategorii w marcu 2026, transfer własny i ruch kapitału w marcu 2026 oraz operacja „Jedzenie” innego użytkownika w marcu 2026. Helpery z prefiksem pliku, jak w `PeriodReportTest.php:26-77`.
- Miesiąc: `GET /reports?month=3` z `withSession(['budget_year' => 2026])` → kwota wiersza „Jedzenie” w `report.expense`. `GET /operations?category_id=<id>&date_from=2026-03-01&date_to=2026-03-31` → suma `operations.data.*.amount_minor_units` równa się tej kwocie, identyfikatory to dokładnie trzy marcowe operacje 2026, a `suppressedSections` zawiera transfery własne i ruchy kapitału.
- Rok: `GET /reports` (2026) i zakres `2026-01-01..2026-12-31` → suma równa kwocie roku. Operacja z marca 2025 i operacja drugiej kategorii nie występują.
- Kategoria zarchiwizowana z operacjami w okresie: filtr ją przyjmuje (200, bez błędu walidacji), a suma równa się kwocie jej wiersza.
- Uruchomić na PostgreSQL (`whereDate` i daty, AGENTS.md).

### Success Criteria:

#### Automated Verification:

- Test Vitest funkcji celu przechodzi: `npm run test -- resources/js/lib/report-category-operations.test.ts`
- Test Feature równości przechodzi na PostgreSQL (polecenie dla worktree z AGENTS.md): `… artisan test --compact tests/Feature/ReportToOperationsTest.php`
- Kontrola typów przechodzi: `npm run types:check`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie przed fazą 2.

---

## Phase 2: Linki w widokach raportu

### Overview

Nazwa kategorii w tabelach roku i miesiąca oraz w Trendzie staje się linkiem z wyglądem z kitchen sinka. Do tego strażnik kontraktu UI i aktualizacja reguły raportów.

### Changes Required:

#### 1. Klasa linku nazwy

**File**: `resources/css/app.css` (przy klasach `.report-cell-name` / `.report-trend-name`, `@layer components`)

**Intent**: Nazwany wygląd linku z nazwy w wierszu raportu, bo strażnicy raportu blokują utility z wartościami arbitralnymi w widokach.

**Contract**: `.report-name-link`: `inline-flex min-w-0 max-w-full items-center rounded-sm`, `transition-colors`, `hover:bg-secondary`, przy `hover` podkreślona nazwa chipa, `focus-visible` z obwódką `ring` jak `.category-meter-row-link` (`app.css:2013-2015`). Bez nowych tokenów i kolorów. Link w wierszu zarchiwizowanym dziedziczy wyciszony kolor tekstu.

#### 2. Tekst etykiety

**File**: `lang/pl/ui.php` (sekcja `reports`)

**Intent**: Dostępna nazwa linku z celem i okresem. Zawiera widoczną nazwę kategorii, więc zachowuje zasadę „label in name”.

**Contract**: `reports.operations_link_label` => `'Operacje kategorii :name, :period'`. `:period` to `<nazwa miesiąca> <rok>` (`t.budget.month_name_N`, np. „marzec 2026”) albo `<rok>`.

#### 3. Wiersz kategorii raportu okresu

**File**: `resources/js/components/ReportCategoryRow.vue`, `resources/js/components/ReportEntryTable.vue`

**Intent**: Nazwa kategorii z kwotą w bieżącym okresie linkuje do jej operacji w okresie raportu. Wiersz istniejący tylko przez porównanie zostaje bez linku.

**Contract**: `ReportCategoryRow` dostaje propsy `budgetYear: number` i `month: number | null`. Gdy `reportCategoryOperationsQuery(category, budgetYear, month)` zwraca query, `CategoryChip` siedzi w `<Link :href="operations({ query })" class="report-name-link" :aria-label="…">` (`operations` z `@/routes`). Badge archiwum zostaje poza linkiem. `ReportEntryTable` dostaje `month: number | null` i przekazuje `budgetYear` i `month` obu miejscom użycia `ReportCategoryRow` (bez grupy i w grupie). `ReportGroupRow` bez zmian.

#### 4. Wiersze kategorii Trendu

**File**: `resources/js/components/ReportTrendTable.vue`

**Intent**: Nazwa kategorii w Trendzie linkuje do operacji z całego roku. Grupy, komórki miesięcy i „Razem” zostają bez linku.

**Contract**: `ReportTrendTable` dostaje prop `budgetYear: number`. W obu wierszach kategorii (bez grupy i w grupie) `CategoryChip` w slocie `rowheader` siedzi w `<Link>` z `reportCategoryOperationsQuery(entry, budgetYear, null)`, klasą `.report-name-link` i etykietą z `:period` = rok. Badge archiwum poza linkiem. `ReportTrendRow.vue` bez zmian (link idzie przez slot). Powtórzony markup linku można wydzielić do lokalnej funkcji lub computed w tym pliku. Bez nowego pliku komponentu, żeby listy strażników zostały aktualne.

#### 5. Strona i kitchen sink

**File**: `resources/js/pages/Reports.vue`, `resources/js/pages/dev/ReportsKitchenSink.vue`, `resources/js/lib/reports-fixtures.ts` (tylko podpisy, jeśli trzeba)

**Intent**: Rok i miesiąc trafiają do tabel na stronie i w sandboxie, żeby każdy stan linku (z linkiem, bez linku przy „—”, zarchiwizowany z linkiem, Trend) był widoczny w `/dev/ui/reports`.

**Contract**: `Reports.vue` przekazuje `:month="period.kind === 'month' ? period.month : null"` do obu `ReportEntryTable` i `:budget-year="budgetYear"` do obu `ReportTrendTable`. Kitchen sink przekazuje to samo z `scenario.period` i roku scenariusza. Scenariusze z `has_operations: false` (`reports-fixtures.ts:146`, `:298`) już pokazują stan bez linku. Jeśli podpis scenariusza nie mówi, że nazwa jest linkiem, dopisz to w `caption`, bez nowego scenariusza.

#### 6. Strażnik kontraktu UI

**File**: `resources/js/ui-contract.test.ts`

**Intent**: Link z raportu zawsze idzie przez Wayfinder i wspólną funkcję celu, a grupa linku nie dostaje.

**Contract**: Nowy `it` w stylu `:519-528`. `ReportCategoryRow.vue` i `ReportTrendTable.vue` zawierają `from '@/routes'` i `reportCategoryOperationsQuery`, a nie zawierają `href="/operations`. `ReportGroupRow.vue` nie zawiera `reportCategoryOperationsQuery` ani `operations(`.

#### 7. Reguła raportów

**File**: `.ai/rules/reports.md`

**Intent**: Następny agent wie, gdzie są linki do Operacji, gdzie ich nie ma i jak są budowane.

**Contract**: W sekcji „The Reports page is tables…” jedno-dwa zdania: nazwa kategorii z kwotą w bieżącym okresie (tabele okresu i Trend) to `<Link>` z `.report-name-link` na `operations({ query: reportCategoryOperationsQuery(...) })`, zakres = miesiąc raportu albo cały rok budżetu. Grupy, „rok wcześniej”, wiersze tylko z porównania oraz komórki miesięcy i „Razem” Trendu nie linkują, a filtr Operacji zostaje płaski (ADR-0069). Następnie `php artisan boost:index-rules`.

### Success Criteria:

#### Automated Verification:

- Testy Vitest przechodzą, w tym strażnik linku w `ui-contract.test.ts`: `npm run test`
- Kontrola typów przechodzi: `npm run build && npm run types:check`
- Strażnik literałów UI przechodzi: `npm run check:ui`
- Pełna bramka przechodzi na PostgreSQL (polecenie dla worktree z AGENTS.md, `composer ci:check`) oraz `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- W `/reports?month=3` (dane dev) kliknięcie nazwy kategorii otwiera Operacje z chipami kategorii i „01.03.<rok> – 31.03.<rok>”, a suma widocznych operacji zgadza się z kwotą w raporcie
- W widoku roku i w Trendzie link prowadzi do zakresu `01.01–31.12` roku budżetu; grupy, „rok wcześniej”, komórki miesięcy i wiersz z „—” nie są klikalne
- W `/dev/ui/reports` hover pokazuje podkreśloną nazwę i tło `secondary`, Tab daje widoczną obwódkę fokusu, wiersz zarchiwizowany zostaje wyciszony, a przypięta kolumna nazwy w Trendzie nie zmienia szerokości (jasny i ciemny motyw)
- Czytnik ekranu albo inspektor dostępności pokazuje nazwę linku „Operacje kategorii <nazwa>, <okres>”

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie (podgląd worktree: `context/foundation/agents/worktree-preview.md`).

---

## Testing Strategy

### Unit Tests:

- `report-category-operations.test.ts`: zakres miesiąca i roku, luty zwykły i przestępny, dopełnienie zerem, `has_operations: false` → `null`, brak pola → query, pozostałe filtry `undefined`.
- `ui-contract.test.ts`: link przez Wayfinder i funkcję celu w obu plikach wierszy, brak linku w `ReportGroupRow.vue`.

### Integration Tests:

- `tests/Feature/ReportToOperationsTest.php` (PostgreSQL): kwota kategorii w raporcie miesiąca i roku równa się sumie listy Operacji z query linku, z operacjami na granicach miesiąca, zwrotem, transferem własnym, ruchem kapitału, operacją z innego roku, inną kategorią, cudzą operacją i kategorią zarchiwizowaną.

### Manual Testing Steps:

1. Otwórz `/reports?month=3` i kliknij nazwę kategorii wydatku z kwotą. Porównaj sumę operacji na liście z kwotą w raporcie.
2. Przełącz na „Rok” i „Trend” i sprawdź zakres dat w chipie po kliknięciu.
3. Włącz porównanie z rokiem wcześniej i sprawdź, że wiersz z „—” i kolumna roku wcześniej nie są klikalne.
4. W `/dev/ui/reports` przejdź Tabem po nazwach i sprawdź fokus, hover i oba motywy.

## Performance Considerations

Brak nowych zapytań. Link liczy się po stronie klienta z propsów, które strona już ma. Lista Operacji z filtrem działa jak dziś (paginacja po 50).

## References

- Research: `context/changes/report-to-operations/research.md` (z decyzjami użytkownika 1–5)
- Wzorzec celu linku: `resources/js/lib/dashboard-categories.ts:35-48`, `resources/js/components/CategoryMeterRow.vue:27-31`
- Budowniczy query: `resources/js/lib/operations-list-query.ts:24-37`
- Odczyt raportu: `app/Support/MonthCategoryActuals.php:27-60`, `app/Support/PeriodReport.php:100-138`
- Filtr Operacji: `app/Support/OperationsListQuery.php:78-151`, `app/Http/Requests/IndexOperationsRequest.php`
- Strażnik linku do Operacji: `resources/js/ui-contract.test.ts:519-528`
- ADR-0069 (filtr Operacji płaski), ADR-0070 (reguła liczenia raportu), ADR-0074 (wiersz tylko z porównania), ADR-0077 (Trend, bez linku z nagłówka miesiąca)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Cel linku i dowód równości

#### Automated

- [x] 1.1 Test Vitest funkcji celu przechodzi: `npm run test -- resources/js/lib/report-category-operations.test.ts`
- [x] 1.2 Test Feature równości przechodzi na PostgreSQL (polecenie dla worktree z AGENTS.md): `… artisan test --compact tests/Feature/ReportToOperationsTest.php`
- [x] 1.3 Kontrola typów przechodzi: `npm run types:check`
- [x] 1.4 Formatowanie PHP: `vendor/bin/pint --dirty --format agent`

### Phase 2: Linki w widokach raportu

#### Automated

- [ ] 2.1 Testy Vitest przechodzą, w tym strażnik linku w `ui-contract.test.ts`: `npm run test`
- [ ] 2.2 Kontrola typów przechodzi: `npm run build && npm run types:check`
- [ ] 2.3 Strażnik literałów UI przechodzi: `npm run check:ui`
- [ ] 2.4 Pełna bramka przechodzi na PostgreSQL (polecenie dla worktree z AGENTS.md, `composer ci:check`) oraz `vendor/bin/sloppy ci` na hoście

#### Manual

- [ ] 2.5 W `/reports?month=3` (dane dev) kliknięcie nazwy kategorii otwiera Operacje z chipami kategorii i „01.03.<rok> – 31.03.<rok>”, a suma widocznych operacji zgadza się z kwotą w raporcie
- [ ] 2.6 W widoku roku i w Trendzie link prowadzi do zakresu `01.01–31.12` roku budżetu; grupy, „rok wcześniej”, komórki miesięcy i wiersz z „—” nie są klikalne
- [ ] 2.7 W `/dev/ui/reports` hover pokazuje podkreśloną nazwę i tło `secondary`, Tab daje widoczną obwódkę fokusu, wiersz zarchiwizowany zostaje wyciszony, a przypięta kolumna nazwy w Trendzie nie zmienia szerokości (jasny i ciemny motyw)
- [ ] 2.8 Czytnik ekranu albo inspektor dostępności pokazuje nazwę linku „Operacje kategorii <nazwa>, <okres>”
