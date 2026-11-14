# Zakładka „Lata” w panelu kategorii — plan implementacji

## Overview

S-36 (M-4, MS-03) łączy dwa gotowe odczyty Raportów: tabelę „Lata” (S-34) i Panel kategorii widoku Rok (S-35). W panelu etykieta „Miesiące 2026” staje się przełącznikiem „Miesiące 2026 | Lata”. Zakładka „Lata” pokazuje słupki lat 2020–2026 tej kategorii z tego samego odczytu `ReportYears`, liczone do tego samego dnia, a pod nimi wiersze „Zmiana od 2020”, „Najwięcej” i podpis zakresu. Nazwa kategorii w tabeli „Lata” prowadzi do Roku bieżącego (albo ostatniego w realizacji) z panelem tej kategorii otwartym na zakładce „Lata”. To zamyka warunek „Done when” kamienia M-4: z widoku „Lata” dochodzi się do panelu kategorii.

## Current State Analysis

- Widok Rok buduje panel przez `ReportCategoryPanel::forYear` (`app/Support/ReportCategoryPanel.php:31`) tylko dla Budżetu w realizacji z operacjami (`app/Http/Controllers/ReportController.php:82-84`). Panel ma 12 miesięcy na kategorię i nie wie nic o latach. `?panel` nie jest nigdzie czytany; S-35 tylko zarezerwował tę nazwę (`context/archive/2026-11-14-report-ranking-and-category-panel/plan.md:48`).
- `ReportCategoryPanel.vue:236-243` pokazuje w nagłówku sekcji etykietę `panel_months_label` („Miesiące :year”, `lang/pl/ui.php:488`) i pod nią `ReportMonthBars` z wierszami średniej, najwyższego miesiąca i miesiąca w toku.
- Odczyt „Lat” to `ReportYears::for(ReportYearsRange::resolve($user, $today))` (`app/Support/ReportYears.php:52`): stała liczba zapytań (lata w realizacji, pozycje, grupy, jedno zapytanie sumujące z `YearsLineActuals::read`). Zakres nie zależy od wybranego roku. Kategoria ma wiersz, gdy ma operację w zakresie liczonym przez tryb (`ReportYears.php:118-145`). Każda kategoria ma już `cells`, `change_tenths`, `change_first_year` i `max_years` (`ReportYears.php:259-293`).
- Raport roku daje kategorii wiersz, gdy ma ona operację w roku albo w okresie porównania (`app/Support/PeriodReport.php:160`). Porównanie roku bieżącego to rok N − 1 do dzisiejszego dnia przesuniętego o rok (`ReportComparisonPeriod.php:46-52`), czyli ten sam zakres co odcięcie N − 1 w trybie „Do D” (`ReportYearsRange::throughOf`, `ReportYearsRange.php:83-92`). Dla roku, który nie jest bieżący, porównanie obejmuje cały rok N − 1.
- Wybrany rok żyje w sesji (`SelectedBudgetYear::resolve`, `app/Support/SelectedBudgetYear.php:12`), więc zwykły link GET otworzyłby rok z sidebara. `POST budget/year` (`BudgetController::selectYear`, `app/Http/Controllers/BudgetController.php:148-164`) zapisuje rok w sesji i dla `destination: 'reports'` przenosi `report_category` i `report_ranking` (walidacja `app/Http/Requests/SelectBudgetYearRequest.php:27-29`). Klient zbiera te parametry w `reportYearSwitchParams` (`resources/js/lib/budget-year-menu.ts:75`).
- W tabeli „Lata” nazwa jest zwykłym tekstem w slocie `ReportYearsRow` (`resources/js/components/ReportYearsTable.vue:109` i `:183`). Wiersze grup nie są linkami.
- Wybór kategorii i trybu w Roku to `router.replace` całego adresu przez `currentQuery()` i `replaceInPlace()` (`resources/js/pages/Reports.vue:71-96`, `setRequestedCategory` `:227`).
- Karta Atramentu `PageReports` rysuje tylko stan „Miesiące 2026”. Dla „Lat” są przełącznik `ht-seg` w wierszu `ht-detail__sechead` i nieużyte klasy słupków (`--n:7`, `is-now` w `foreground`, `is-none` jako kreska 2 px w `track`). README: „„Lata” pokazuje słupki 2020–2026 tej kategorii (wiersz tabeli `PageReportsYears`)”. Karta `PageReportsYears` ma w stopce „Kliknij kategorię, aby zobaczyć jej lata w panelu”, a nazwa jest linkiem podkreślanym przy najechaniu, bez zmiany koloru.

## Desired End State

- `/reports?category=<id>&panel=years` w Roku 2026 pokazuje panel tej kategorii z wciśniętym „Lata”: 7 słupków 2020–2026 (rok bieżący w `foreground`, rok bez pozycji jako kreska w `track`), listę `sr-only` z kwotami lat, wiersze „Zmiana od 2020” (albo „od 2022”), „Najwięcej: 2025 · 14 230,00 zł” i podpis „Każdy rok do 14 listopada”. Kafelki 2026, 2025, Różnica i Udział oraz przycisk „Operacje: <kategoria>, 2026” są takie same jak na zakładce „Miesiące”.
- W Roku innym niż bieżący (np. 2024 wybrany w sidebarze) zakładka „Lata” pokazuje pełne lata z podpisem „Pełne lata”, bo kafelki takiego roku i jego porównanie obejmują całe lata (`ReportComparisonPeriod.php:51-53`): słupki 2024 i 2023 są wtedy równe kafelkom 2024 i 2023.
- Przełączenie zakładki zmienia adres bez żądania (`router.replace`), odświeżenie i zmiana roku w sidebarze zachowują zakładkę, a wybór innej kategorii w rankingu pokazuje od razu jej lata.
- W tabeli „Lata” każda kategoria, która ma wiersz w raporcie roku docelowego (ostatnia kolumna tabeli), ma nazwę-link. Klik ustawia rok docelowy w sesji i otwiera `/reports?category=<id>&panel=years`. Kategoria bez takiego wiersza (np. zarchiwizowane Kino z kwotami tylko 2020–2023) zostaje zwykłym tekstem. Stopka ma drugą linię „Kliknij kategorię, aby zobaczyć jej lata w panelu”.
- Kwoty lat w panelu są równe co do grosza komórkom tabeli „Lata” w trybie panelu („Do D” w Roku bieżącym, „Pełne lata” w innym; test porównuje oba odczyty), a flaga linku zgadza się z wierszami prawdziwego `PeriodReport::forYear` (test).
- Każdy nowy stan jest scenariuszem w `/dev/ui/reports`; reguła `reports.md` i `design-system.md` opisują link i zakładkę zamiast „do S-36”.

### Key Discoveries:

- Panel i odczyt „Lat” liczą wiersze po tej samej kategorii listy (`ReportCategoryTree::entryNodesOfType` w `ReportYears.php:151`, `PeriodReport` po kategorii listy), więc lata do panelu bierze się po `id` bez mapowania.
- Flaga „ma wiersz w raporcie roku docelowego” wynika z liczników, które `YearsLineActuals` już ma (`hasOperations($lineId, $sameDay)`, `app/Support/YearsLineActuals.php:63`): operacja w roku docelowym w całym roku albo operacja w roku wcześniejszym w zakresie porównania. Nie potrzeba nowego zapytania.
- `ReportYears::toArray()['same_day']` jest `null`, gdy nie ma roku bieżącego w realizacji (`ReportYears.php:85`). Panel bierze wtedy tryb `full`, w którym wszystkie lata są pełne.
- Kafelki panelu w Roku innym niż bieżący liczą cały rok i cały rok wcześniej (porównanie jest ucięte tylko dla roku „w toku”, `ReportComparisonPeriod.php:51-53`), dlatego panel w takim roku bierze tryb `full`. Wtedy każda kategoria panelu ma wiersz w trybie panelu: w Roku bieżącym operacja w T liczy się do 31 grudnia, a operacja w T − 1 w zakresie porównania jest w zakresie „Do D”; w innym roku Y operacje Y i Y − 1 są w `full`. `years` kategorii panelu nie bywa więc `null`.
- Domyślne przewijanie zapisów porównuje ścieżki (`lib/visit-scroll.ts`): `POST budget/year` z `/reports?view=years` wraca na `/reports`, więc link musi jawnie wysłać `preserveScroll: false`, jak przełącznik roku.
- `SegmentedControl` z `tone="neutral"` to przyjęty przełącznik wartości (reguła `js.md`), a `ui-contract.test.ts:590` pilnuje, że wybór w rankingu ma `aria-current`, nie `aria-selected` (ADR-0100).

## What We're NOT Doing

- Przełącznika „Do D” / „Pełne lata” w panelu: tryb wynika z oglądanego roku („Do D” w Roku bieżącym, pełne lata w każdym innym i bez roku bieżącego w realizacji); przełącznik zostaje w widoku „Lata”.
- Przenoszenie trybu tabeli („Do D” / „Pełne lata”) albo jej zakładki (Wydatki / Przychody) do adresu linku.
- Linku dla kategorii bez wiersza w raporcie roku docelowego i panelu dla kategorii spoza rankingu.
- Linku do ostatniego roku, w którym kategoria ma wiersz (zostaje rok docelowy z ADR-0097:11).
- Linków z wierszy grup, z komórek lat, z kafelków statystyk („Najszybciej rośnie”) ani z wiersza „Razem”.
- Doczytywania lat osobnym żądaniem (`Inertia::optional`, `Inertia::defer`) i szkieletu zakładki.
- Zakładki „Lata” w Miesiącu i 12 miesiącach (panel istnieje tylko w Roku, ADR-0097) oraz przenoszenia `panel` przez przełącznik okresu (tak jak dziś `category`).
- Zmian w Trendzie, w kolejności i treści rankingu, w kafelkach panelu i w przycisku „Operacje”.
- Wpisu w słowniku: terminy Same-day Cut, Full Years i Change since First Year już istnieją (ADR-0097).

## Implementation Approach

Serwer liczy lata razem z panelem przy każdym wejściu w Rok z panelem i wysyła je w tym samym propsie `categoryPanel`, więc przełączenie zakładki i zmiana kategorii są natychmiastowe. Lata pochodzą z tego samego obiektu `ReportYears` co tabela, a ten sam obiekt oznacza w tabeli „Lata”, które kategorie mogą otworzyć panel. Link z tabeli korzysta z istniejącej ścieżki przełącznika roku (`POST budget/year`, `destination: 'reports'`), dokładając jeden parametr `report_panel`, który przenosi też zakładkę przy zmianie roku w sidebarze. Klient tylko formatuje i układa słupki; zakładka jest w adresie (`?panel=years`) i w propsie, zmieniana przez `router.replace` jak wybór kategorii.

Kolejność faz: najpierw kontrakt serwera i typy (z fiksturami na wartościach domyślnych, żeby typy się zgadzały), potem panel, na końcu link, reguły i E2E.

## Critical Implementation Details

**Flaga linku musi równać się walidacji `?category`.** Panel przyjmuje tylko kategorię z wierszem w raporcie roku (`ReportCategoryPanel::requestedCategoryId`), więc `opens_panel` liczy dokładnie tę samą regułę dla roku docelowego T (ostatnia kolumna): operacja w T w całym roku (także datowana po dziś) albo, gdy T − 1 jest kolumną, operacja w T − 1 w zakresie porównania (do dnia D w T − 1, gdy T jest rokiem bieżącym, inaczej cały rok T − 1). Flaga ma tę samą wartość w obu trybach tabeli. Rozbieżność daje link, który po cichu otwiera inną kategorię, dlatego test porównuje flagę z prawdziwym `PeriodReport::forYear` na przypadkach brzegowych.

**Wydajność roku.** Rok z panelem czyta teraz także odczyt „Lat”. Przy 7 Budżetach to dwa odczyty pełnej skali w jednym żądaniu; test NFR musi objąć ten przypadek na PostgreSQL.

## Phase 1: Serwer — lata w panelu i flaga linku

### Overview

Kontrakt serwera: `categoryPanel` niesie zakładkę i lata każdej kategorii, odczyt „Lat” niesie `opens_panel`, a przełącznik roku przenosi `panel`. Do tego ADR-0101, typy TS i fikstury na wartościach domyślnych. Po tej fazie strona wygląda jak dziś, a propsy są gotowe.

### Changes Required:

#### 1. ADR-0101

**File**: `context/foundation/adr/0101-report-panel-years-tab.md`

**Intent**: Zapisać decyzje S-36, które zawężają albo dopowiadają ADR-0097, zanim powstanie kod (AGENTS.md: zmiana decyzji i znaczenia koloru przez ADR).

**Contract**: Po angielsku, w stylu ADR-0097/0100. Treść:
- nazwa kategorii w „Latach” jest linkiem tylko wtedy, gdy kategoria ma wiersz w raporcie roku docelowego (zawęża zdanie ADR-0097 „S-36 makes it a link”); reszta zostaje tekstem;
- link przechodzi przez wybór roku (`POST budget/year`), bo rok jest w sesji (ADR-0075);
- zakładka „Lata” panelu liczy lata do tego samego dnia w Roku bieżącym, a w każdym innym roku (i bez roku bieżącego w realizacji) pełne lata, żeby słupki oglądanego roku i roku wcześniej równały się kafelkom; bez przełącznika trybu w panelu;
- pod słupkami są wiersze „Zmiana od <rok>”, „Najwięcej” (lata remisu razem) i podpis zakresu; kafelki i „Operacje” bez zmian;
- słupki lat: wydatek `chart-1`, przychód `success`, rok bieżący `foreground`, rok bez pozycji kreska 2 px w `track`, kwota ujemna słupek zerowy, `aria-hidden` z listą `sr-only`;
- kwoty panelu w złotych z groszami;
- `?panel=years` w adresie;
- odrzucone: link zawsze z panelem spoza rankingu, link do ostatniego roku z wierszem, przełącznik „Pełne lata” w panelu, tryb tabeli w adresie, lata doczytywane osobnym żądaniem.

#### 2. Flaga `opens_panel` w odczycie „Lat”

**File**: `app/Support/ReportYears.php`

**Intent**: Każdy wiersz kategorii (nie grupy) w obu trybach mówi, czy jego nazwa może otworzyć panel roku docelowego.

**Contract**: `YearsCategory` dostaje `opens_panel: bool` (PHPDoc `@phpstan-type`). Reguła z „Critical Implementation Details”, liczona z pozycji wiersza (`$byYear`) i `YearsLineActuals::hasOperations`, bez nowego zapytania. Rok docelowy to ostatnia kolumna; T − 1 liczy się tylko, gdy jest kolumną (Budżet w realizacji). Oba człony zawsze z `$sameDay = true`, niezależnie od trybu wiersza: `hasOperations(<pozycja T>, true)` (dla T `throughOf` daje 31 grudnia w obu trybach) albo `hasOperations(<pozycja T − 1>, true)` (do dnia D, a bez roku bieżącego cały rok, bo `cut` jest `null`). Flagę liczy się raz na kategorię, poza pętlą trybów, i wstawia do wiersza w obu trybach; `$sameDay` trybu `full` dałby cały T − 1 i link do kategorii, którą `requestedCategoryId` odrzuci.

#### 3. Lata w danych panelu

**File**: `app/Support/ReportCategoryPanel.php`, `app/Support/ReportYears.php`

**Intent**: Panel dostaje lata każdej swojej kategorii z tego samego odczytu co tabela oraz wybraną zakładkę.

**Contract**:
- `ReportCategoryPanel::forYear` przyjmuje dodatkowo zakładkę (`'months' | 'years'`) i obiekt `ReportYears`.
- `PanelShape` dostaje:
  - `tab: 'months'|'years'`;
  - `years: array{columns: list<int>, current_year: int|null, cut: array{month: int, day: int}|null, mode: 'same_day'|'full'}`, gdzie `mode` to `same_day`, gdy oglądany rok jest rokiem bieżącym odczytu (`year === current_year`), inaczej `full` (rok przeszły i brak roku bieżącego).
- `PanelCategory` dostaje `years: array{cells: list<int|null>, change_tenths: int|null, change_first_year: int|null, max_years: list<int>}`: kształt wiersza kategorii z odczytu „Lat” w trybie panelu, odszukany po `id` w obu typach (także w grupach). Wiersz zawsze istnieje (Key Discoveries); gdyby go zabrakło, `forYear` rzuca `LogicException` z `id` kategorii i rokiem zamiast wysłać puste lata.
- `ReportYears` udostępnia publiczny dostęp do wierszy kategorii trybu po `id` (np. `categoriesById(bool $sameDay): array<int, YearsCells>`). Panel nie przelicza niczego sam.

#### 4. Kontroler: odczyt „Lat” przy panelu i `?panel`

**File**: `app/Http/Controllers/ReportController.php`

**Intent**: Gdy powstaje panel, kontroler buduje też `ReportYears::for(ReportYearsRange::resolve($user, $today))` i czyta zakładkę z adresu.

**Contract**:
- `?panel=years` daje `tab: 'years'`, każda inna wartość albo brak daje `'months'` (prywatna metoda obok `rankingMode`).
- Odczyt „Lat” powstaje tylko wtedy, gdy `categoryPanel` nie jest `null`; widoki bez panelu nie czytają lat.
- `years()` bez zmian poza tym, że jego `years` ma teraz `opens_panel`.

#### 5. Przełącznik roku przenosi `panel`

**File**: `app/Http/Requests/SelectBudgetYearRequest.php`, `app/Http/Controllers/BudgetController.php`

**Intent**: Link z „Lat” i zmiana roku w sidebarze z otwartą zakładką trafiają na `/reports?…&panel=years`.

**Contract**: Reguła `'report_panel' => ['sometimes', Rule::in(['years'])]`; gałąź `'reports'` w `selectYear` dokłada `'panel' => $validated['report_panel'] ?? null` do `array_filter([...])`.

#### 6. Typy i fikstury

**File**: `resources/js/types/reports.ts`, `resources/js/lib/reports-fixtures.ts`

**Intent**: Typy odbijają nowy kształt w tym samym commicie co PHP (reguła `inertia-props.md`); fikstury dostają wartości domyślne, żeby `types:check` przechodził przed fazą 2.

**Contract**:
- `ReportCategoryPanelData` dostaje `tab` i `years`, `ReportCategoryPanelItem` dostaje `years`, typ kategorii „Lat” dostaje `opens_panel`.
- `buildPanel` i `yearInProgressPanel` ustawiają `tab: 'months'`, `years` z kolumnami scenariusza i na kategorii `years` z komórkami spójnymi z jej kwotą roku; `buildYears` ustawia `opens_panel: true`.
- Scenariusze stanów S-36 dochodzą w fazach 2 i 3.

#### 7. Testy serwera

**File**: `tests/Feature/ReportCategoryPanelTest.php`, `tests/Feature/ReportYearsTest.php`, `tests/Feature/ReportsPageTest.php`, `tests/Feature/BudgetTest.php`, `tests/Feature/Nfr/NfrPerfReportTest.php`

**Intent**: Udowodnić równość liczb panelu z tabelą, poprawność flagi, kształt propsów i wydajność.

**Contract** (przypadki):
- **Panel:**
  - komórki, zmiana, rok początku zmiany i `max_years` każdej kategorii panelu Roku bieżącego są równe wierszowi tej kategorii w `ReportYears::toArray()['same_day']` (także w grupie i dla przychodu);
  - w Roku przeszłym (2025 przy bieżącym 2026) panel ma `mode: 'full'`, komórki z `full`, a komórki 2025 i 2024 każdej kategorii równają się jej `amount_minor_units` i `previous_minor_units` z raportu roku (kategoria z operacjami tylko po D w 2025 też);
  - bez roku bieżącego (wszystkie Budżety w realizacji < dziś) panel ma `mode: 'full'` i komórki z `full`;
  - każda kategoria panelu ma `years` (także wiersz tylko z porównania z operacją w T − 1 przed D);
  - `countQueries(20) === countQueries(2)` nadal (stała liczba zapytań przy większej liczbie kategorii) oraz stała liczba zapytań przy 2 i 7 Budżetach.
- **Flaga** (`ReportYearsTest`), dla każdej kategorii `opens_panel === array_key_exists(id, wiersze PeriodReport::forYear(T, ReportComparisonPeriod::resolve(T, null, today)))`. Przypadki:
  - operacja w T;
  - operacja tylko w T − 1 przed D (prawda);
  - operacja tylko w T − 1 po D, gdy T jest bieżący (fałsz);
  - operacja w T datowana po dziś (prawda);
  - zarchiwizowana z operacjami tylko w 2020–2022 (fałsz);
  - bez roku bieżącego z operacją w T − 1 po D (prawda, porównanie pełne);
  - ta sama wartość w obu trybach.
- **`assertInertia`:**
  - `categoryPanel.tab` to `'years'` dla `?panel=years` i `'months'` dla braku i dla `?panel=foo`;
  - `categoryPanel.years.columns`, `categoryPanel.years.mode`, `categoryPanel.categories.0.years.cells`;
  - `years.same_day.expense.entries.*.opens_panel` w `?view=years`;
  - istniejące domknięte zakresy wpisów „Lat” (`ReportsPageTest.php:768-797`, wiersze grupy i kategorii w `years.full.expense.entries`) dostają `->where('opens_panel', …)` z oczekiwaną wartością; to zmiana wymagania, nie dopisywanie `->etc()`;
  - Miesiąc, 12 miesięcy, Trend i „Lata” nadal mają `categoryPanel: null`.
- **Przełącznik roku:**
  - `POST budget.select-year` z `destination: 'reports'`, `report_category` i `report_panel: 'years'` przekierowuje na `/reports?category=<id>&panel=years`;
  - `report_panel: 'months'` daje 422.
- **NFR:**
  - istniejący test roku sprawdza `categoryPanel.years.columns`;
  - nowy przypadek: Rok 2026 z panelem przy 7 Budżetach × 10 000 operacji mieści się w 2 s na PostgreSQL.

### Success Criteria:

#### Automated Verification:

- Testy serwera przechodzą na PostgreSQL w worktree: `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing_report_panel_years_tab -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature/ReportCategoryPanelTest.php tests/Feature/ReportYearsTest.php tests/Feature/ReportsPageTest.php tests/Feature/BudgetTest.php tests/Feature/Nfr/NfrPerfReportTest.php`
- Typy TS zgadzają się z fiksturami: `npm run build && npm run types:check`
- PHPStan i styl: `vendor/bin/pint --dirty --format agent` bez zmian oraz `composer types:check`
- Sloppy bez nowych zgłoszeń: `vendor/bin/sloppy ci`

#### Manual Verification:

- ADR-0101 czyta się jako dopowiedzenie ADR-0097 bez sprzeczności (przegląd treści przez użytkownika)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Panel — zakładka „Lata”

### Overview

Przełącznik „Miesiące <rok> | Lata” w sekcji „Wydatki w czasie” / „Przychody w czasie”, słupki lat z wierszami pod nimi, zakładka w adresie i w przełączniku roku, scenariusze w sinku.

### Changes Required:

#### 1. Logika słupków i podpisów

**File**: `resources/js/lib/report-category-panel.ts`, `resources/js/lib/report-category-panel.test.ts`

**Intent**: Czyste funkcje układają słupki lat i podpisy z danych serwera, tak jak `reportMonthBars` dla miesięcy.

**Contract**:
- `reportYearBars(cells, columns, currentYear)` → lista `{ year, ratio, state: 'amount' | 'none', isCurrent }`:
  - `ratio` to kwota ÷ maksimum wiersza;
  - kwota ujemna, 0 i wiersz bez dodatniej kwoty dają `ratio: 0`;
  - `null` daje stan `none`.
- `reportYearBarsAccessibleList(bars, cells, format, noLineLabel)` → „2020: 10 280,00 zł”, a rok `null` jako „2021: <noLineLabel>”. Lista obejmuje wszystkie kolumny.
- `reportPanelYearsChangeLabel(changeFirstYear, firstColumn, template)` → „Zmiana od 2022” (bez zmiany: „Zmiana od <pierwsza kolumna>” z wartością „—”). Wartość formatuje `formatYearsChange` z `@/lib/report-years`, ton daje `reportDifferenceTone` + `planChangeToneClass` jak w `ReportYearsRow.vue:79-83`.
- `reportPanelHighestYearsLabel(maxYears, cells, columns, format)` → „2025 · 14 230,00 zł”, przy remisie „2025, 2026 · 14 230,00 zł”, bez lat „—”.
- Podpis zakresu: „Każdy rok do <dzień miesiąca>” z formatowania odcięcia `@/lib/report-years` dla `mode: 'same_day'`, „Pełne lata” dla `full`.
- Testy Vitest: rok `none`, kwota ujemna, wszystkie kwoty niedodatnie, remis, rok bieżący, „od 2022”, oba tryby podpisu.

#### 2. Komponent słupków lat

**File**: `resources/js/components/ReportYearBars.vue` (nowy), `resources/css/app.css`

**Intent**: Słupki lat w panelu, wzorem `ReportMonthBars.vue`: znaczniki CSS z tokenów, `aria-hidden`, obok lista `sr-only`.

**Contract**:
- Props: `type`, `columns`, `currentYear`, `cells`.
- Etykiety pod słupkami to pełny rok („2020”).
- `data-report-year-bars` na korzeniu, `data-year` i `data-state` na słupku.
- Klasy w `@layer components` (przed edycją przeczytaj `.ai/rules/css.md`): siatka z liczbą kolumn w zmiennej (np. `.report-year-bars` z `--report-year-count`, wspólny tor i słupek z `.report-month-*`), rok bieżący `foreground`, rok bez pozycji kreska 2 px w `track`, przychód `success`. Bez literałów kolorów i wartości arbitralnych.
- Plik trafia na listy `tests/Unit/ReportsHardcodedUiTest.php` i `scripts/check-ui-literals.mjs`.

#### 3. Panel z przełącznikiem

**File**: `resources/js/components/ReportCategoryPanel.vue`, `lang/pl/ui.php`

**Intent**: Nagłówek sekcji ma przełącznik zamiast etykiety; zakładka „Lata” pokazuje słupki lat i wiersze lat, „Miesiące” zostają jak dziś.

**Contract**:
- Nowy prop `tab` i zdarzenie `update:tab`.
- `SegmentedControl` `tone="neutral"`: opcje `months` (etykieta z `panel_months_label`, „Miesiące 2026”) i `years` (`panel_tab_years`, „Lata”), `ariaLabel` z `panel_tab_label` („Zakres wykresu”).
- Na „Latach” zamiast `ReportMonthBars` i trzech wierszy miesięcy jest `ReportYearBars` i wiersze `data-panel-row="years-change"` („Zmiana od 2020”, wartość z tonem), `"years-highest"` (`panel_highest` „Najwięcej”, rok i kwota w złotych z groszami) oraz `"years-range"` (podpis zakresu w `meta`).
- Kafelki i przycisk „Operacje” bez zmian.
- Nowe klucze w sekcji `reports` w `lang/pl/ui.php`:
  - `panel_tab_label`, `panel_tab_years`;
  - `panel_years_list` („Kwoty według lat”), `panel_years_change` („Zmiana od :year”);
  - `panel_years_range_same_day` („Każdy rok do :date”), `panel_years_range_full` („Pełne lata”);
  - `panel_years_no_line` („brak pozycji w budżecie”).

#### 4. Zakładka w adresie i w przełączniku roku

**File**: `resources/js/pages/Reports.vue`, `resources/js/lib/budget-year-menu.ts`, `resources/js/lib/budget-year-menu.test.ts`

**Intent**: Zmiana zakładki zapisuje `?panel=years` bez żądania, a zmiana roku w sidebarze ją przenosi.

**Contract**:
- `setPanelTab(tab)` wzorem `setRankingMode`: `currentQuery()` z dodanym albo usuniętym `panel`, `replaceInPlace` z łatką `categoryPanel.tab`. Oba miejsca panelu (aside i arkusz) dostają `:tab` i `@update:tab`.
- Wybór kategorii i zamknięcie arkusza zachowują `panel` (zamknięcie usuwa tylko `category`).
- `reportYearSwitchParams` zwraca `report_panel: 'years'` dla `?panel=years`, każdą inną wartość pomija; typ `ReportYearSwitchParams` dostaje to pole; test Vitest obok istniejącego dla `report_category`.

#### 5. Scenariusze w sinku

**File**: `resources/js/lib/reports-fixtures.ts`

**Intent**: Każdy stan zakładki da się obejrzeć w `/dev/ui/reports` na prawdziwym `Reports.vue`.

**Contract**: Scenariusze z `tab: 'years'`:
- `panel-years` (Rok 2026 w toku, kategoria z 7 latami i jednym rokiem „—”);
- `panel-years-younger` („Zmiana od 2022”);
- `panel-years-tie` (dwa lata z maksimum);
- `panel-years-negative` (rok z kwotą ujemną po zwrotach);
- `panel-years-no-current-year` (`mode: 'full'`, podpis „Pełne lata”);
- `panel-years-past-year` (Rok 2025 przy bieżącym 2026, `mode: 'full'`, słupki 2025 i 2024 równe kafelkom);
- `panel-years-income`;
- `panel-years-phone` (arkusz otwarty na „Latach”).

Liczby lat w fiksturach są spójne z fiksturami `years-same-day` tam, gdzie kategoria się powtarza.

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą: `npm test`
- Typy: `npm run build && npm run types:check`
- Strażnicy UI: `npm run check:ui` oraz `tests/Unit/ReportsHardcodedUiTest.php` w `php artisan test --compact tests/Unit/ReportsHardcodedUiTest.php`
- Lint i format frontu: `npm run check`

#### Manual Verification:

- W `/dev/ui/reports` scenariusze `panel-years*` wyglądają jak karta `PageReports` (przełącznik `ht-seg`, 7 słupków, rok bieżący w `foreground`, kreska „—” w `track`) w jasnym i ciemnym motywie, przy oknie 1280 px z rozwiniętym sidebarem i w ramce telefonu
- Przełączenie „Miesiące 2026 | Lata” w scenariuszu nie przesuwa strony i nie zmienia wysokości kafelków; fokus klawiatury widać na obu segmentach
- Czytnik ekranu (albo drzewo dostępności) czyta listę „Kwoty według lat” z rokiem bez pozycji jako „brak pozycji w budżecie”

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: Link z tabeli „Lata”, reguły i E2E

### Overview

Nazwa kategorii w tabeli „Lata” otwiera panel na zakładce „Lata”, stopka to mówi, reguły i design system opisują nowy stan, a Playwright sprawdza ścieżkę w uruchomionej aplikacji.

### Changes Required:

#### 1. Nazwa jako link

**File**: `resources/js/components/ReportYearsTable.vue`, `resources/js/components/ReportYearsView.vue` (jeśli tam leży stopka), `lang/pl/ui.php`

**Intent**: Kategoria z `opens_panel` ma nazwę, która wybiera rok docelowy i otwiera jej panel na zakładce „Lata”; reszta zostaje tekstem.

**Contract**:
- Inertia `<Link>` z `as="button"` i `method="post"` na trasę Wayfinder `budget.select-year` (wzór wywołania jak w `BudgetYearSwitcher.vue`).
- Dane: `{ year: <ostatnia kolumna>, destination: 'reports', report_category: String(id), report_panel: 'years' }`, `:preserve-scroll="false"` i `:preserve-state="false"` (`<Link>` dla metody innej niż GET domyślnie zachowuje stan, a `BudgetYearSwitcher` robi `router.post` bez niego; obie drogi do `budget.select-year` mają zachowywać się tak samo, a lokalne refy `Reports.vue` z widoku „Lata”, np. `sheetOpen`, nie mogą przejść do Roku).
- Klasa `.report-name-link`: podkreślenie samej nazwy przy najechaniu, bez zmiany koloru. Wiersz zarchiwizowany zostaje wyciszony.
- `aria-label` z nowego klucza `years_open_panel` („:name — lata w panelu roku :year”).
- Wiersze grup, „Razem” i kafelki bez linku.
- Stopka tabeli dostaje drugą linię `years_footer_panel_hint` („Kliknij kategorię, aby zobaczyć jej lata w panelu”), gdy choć jedna kategoria otwiera panel.

#### 2. Scenariusze „Lat” w sinku

**File**: `resources/js/lib/reports-fixtures.ts`

**Intent**: Sink pokazuje nazwy-linki i nazwę bez linku obok siebie.

**Contract**: W `years-same-day` zarchiwizowana kategoria bez kwoty w ostatnim roku (Kino) ma `opens_panel: false`, pozostałe `true`. W sinku link wysyła POST jak w aplikacji; `/dev/ui/reports` jest tylko lokalny, więc to dopuszczalne.

#### 3. Reguły i design system

**File**: `.ai/rules/reports.md`, `context/foundation/design-system.md`

**Intent**: Zastąpić zdania „do S-36” opisem tego, co istnieje.

**Contract**:
- **`reports.md`, sekcja „The Years view…”:** zdanie „The category name is plain text until S-36 links it to the category panel.” zamienić na opis linku (`opens_panel` liczone na serwerze, `POST budget.select-year` z `report_panel`, `preserveScroll: false`, wiersze grup bez linku, podpowiedź w stopce).
- **`reports.md`, sekcja panelu:** dopisać `tab` z `?panel=years`, lata z `ReportYears` w trybie „Do D” w Roku bieżącym i `full` w każdym innym (słupki oglądanego roku równe kafelkom), `ReportYearBars` (`aria-hidden` z listą `sr-only`), przenoszenie `report_panel` przez `reportYearSwitchParams` i scenariusze `panel-years-*`.
- **`design-system.md:20`:** „Nazwa kategorii jest zwykłym tekstem do S-36” zamienić na link z podkreśleniem przy najechaniu i drugą linią stopki.
- **`design-system.md`, sekcja „Wykresy”:**
  - w tabeli tokenów `chart-1` i `foreground` dopisać słupki lat panelu;
  - w zdaniu o liście `sr-only` dopisać słupki lat;
  - w opisie panelu dopisać przełącznik „Miesiące <rok> | Lata”.
- Odwołanie do ADR-0101 w obu plikach.

#### 4. E2E

**File**: `tests/e2e/reports.spec.ts`, `tests/e2e/reports-category-panel.spec.ts`, `tests/e2e/reports-view.spec.ts`

**Intent**: Ścieżka z „Lat” do panelu i zachowanie zakładki działają w przeglądarce.

**Contract**:
- **`reports.spec.ts`** (blok „Lata”, `:627`): klik nazwy kategorii z kwotą w roku bieżącym → adres `/reports?category=<id>&panel=years`, aside „Panel kategorii” z wciśniętym „Lata”, tyle słupków `[data-report-year-bars] [data-year]`, ile kolumn miała tabela.
- **`reports-category-panel.spec.ts`:**
  - przełączenie „Miesiące | Lata” zmienia `panel` w adresie bez nawigacji (`expectReportsQuery`);
  - odświeżenie zostaje na „Latach”;
  - zmiana roku w sidebarze zachowuje `category` i `panel`, a w roku przeszłym podpis zakresu to „Pełne lata”;
  - na telefonie link z „Lat” otwiera arkusz na „Latach”.
- **`reports-view.spec.ts`:** scenariusze `panel-years`, `panel-years-past-year` i `years-same-day` (Kino bez linku, inne nazwy z linkiem).
- Dane: seed `demo:seed-household` + `demo:seed-previous-year` i operacje dodawane przez UI z helperów `tests/e2e/support`, sprzątane po teście.

### Success Criteria:

#### Automated Verification:

- E2E przechodzą: `npx playwright test tests/e2e/reports.spec.ts tests/e2e/reports-category-panel.spec.ts tests/e2e/reports-view.spec.ts`
- Pełna bramka na PostgreSQL w worktree: `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing_report_panel_years_tab -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint composer sail-8.5/app ci:check`
- Sloppy: `vendor/bin/sloppy ci`
- Styl PHP: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- W aplikacji: Raporty → „Lata” → klik „Wakacje” (albo innej kategorii z kwotą w 2026) otwiera Rok 2026 z panelem tej kategorii na „Latach”, a słupki i „Zmiana od 2020” zgadzają się z wierszem tabeli w trybie „Do D” (w złotych z groszami zamiast pełnych złotych)
- Z wybranym w sidebarze innym rokiem (np. 2024 albo szkic 2027) klik nazwy w „Latach” przełącza sidebar na rok docelowy i otwiera jego Rok
- Kategoria zarchiwizowana bez wiersza w 2026 nie jest linkiem; podpowiedź w stopce jest widoczna
- Po wdrożeniu użytkownik na produkcji porównał kategorię w „Latach” 2020–2026 i doszedł z niej do panelu tej kategorii (warunek „Done when” M-4)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie; zamknięcie zmiany (`/10x-archive`) dopiero po kroku na produkcji.

---

## Testing Strategy

### Unit Tests:

- Vitest dla `reportYearBars`, listy `sr-only`, podpisów zmiany, „Najwięcej” i zakresu oraz dla `reportYearSwitchParams` z `panel`.
- Strażnicy UI (`ReportsHardcodedUiTest`, `check-ui-literals`, `ui-contract.test.ts`) obejmują nowy komponent.

### Integration Tests:

- Pest: równość lat panelu z odczytem „Lat”, flaga `opens_panel` ↔ wiersze `PeriodReport::forYear`, kształt propsów (`assertInertia`), przekierowanie przełącznika roku, stała liczba zapytań, NFR 7 × 10 000 dla Roku z panelem.
- Playwright: link z „Lat”, przełączanie zakładki, zmiana roku, arkusz na telefonie, scenariusze sinka.

### Manual Testing Steps:

1. Otwórz `/reports?view=years`, kliknij kategorię z kwotą w 2026, sprawdź panel na „Latach” i porównaj liczby z wierszem tabeli.
2. Przełącz „Miesiące 2026” i z powrotem; odśwież stronę na „Latach”.
3. W sidebarze zmień rok na 2025; sprawdź, że panel tej samej kategorii jest na „Latach” z podpisem „Pełne lata”, a słupki 2025 i 2024 równają się kafelkom.
4. Wybierz w sidebarze szkic 2027, przejdź do „Lat” i kliknij nazwę; sprawdź, że otwiera się Rok 2026.
5. Na telefonie (390 px) powtórz krok 1; arkusz otwiera się na „Latach”.

## Performance Considerations

Rok z panelem czyta teraz odczyt „Lat”: 4 zapytania o stałej liczbie (lata w realizacji, pozycje, grupy, jedno zapytanie sumujące wszystkie lata) obok raportu roku. Test NFR dla Roku przy 7 Budżetach × 10 000 operacji pilnuje 2 s na PostgreSQL. Widoki bez panelu (Miesiąc, 12 miesięcy, Trend, szkic) nie czytają lat.

## Migration Notes

Brak migracji i zmian danych. Nowe pola propsów i parametr `report_panel` są dodatkowe; stare adresy (`?category=…` bez `panel`) otwierają panel na „Miesiącach” jak dziś.

## References

- Decyzje: `context/foundation/adr/0097-report-years-ranking-and-chart-colours.md` (akapity „Columns”, „Category Panel”, „Colour”), `context/foundation/adr/0100-report-ranking-selection-uses-aria-current.md`
- Poprzednie wycinki: `context/archive/2026-11-14-report-categories-across-years/plan.md`, `context/archive/2026-11-14-report-ranking-and-category-panel/plan.md`
- Roadmapa: `context/foundation/roadmap.md` (S-36, MS-03)
- Karty Atramentu: `PageReports`, `PageReportsYears` (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, tylko podgląd)
- Kod: `app/Support/ReportCategoryPanel.php:31`, `app/Support/ReportYears.php:52`, `app/Support/YearsLineActuals.php:63`, `app/Http/Controllers/ReportController.php:82`, `app/Http/Controllers/BudgetController.php:148`, `resources/js/pages/Reports.vue:82`, `resources/js/components/ReportCategoryPanel.vue:236`, `resources/js/components/ReportMonthBars.vue`, `resources/js/components/ReportYearsTable.vue:109`, `resources/js/lib/budget-year-menu.ts:75`
- Reguły: `.ai/rules/reports.md`, `.ai/rules/inertia-props.md`, `.ai/rules/js.md`, `.ai/rules/wayfinder.md`, `.ai/rules/css.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Serwer — lata w panelu i flaga linku

#### Automated

- [x] 1.1 Testy serwera przechodzą na PostgreSQL w worktree — 1018ea1
- [x] 1.2 Typy TS zgadzają się z fiksturami — 1018ea1
- [x] 1.3 PHPStan i styl — 1018ea1
- [x] 1.4 Sloppy bez nowych zgłoszeń — 1018ea1

#### Manual

- [ ] 1.5 ADR-0101 czyta się jako dopowiedzenie ADR-0097 bez sprzeczności

### Phase 2: Panel — zakładka „Lata”

#### Automated

- [x] 2.1 Testy JS przechodzą — 8dccc83
- [x] 2.2 Typy — 8dccc83
- [x] 2.3 Strażnicy UI — 8dccc83
- [x] 2.4 Lint i format frontu — 8dccc83

#### Manual

- [ ] 2.5 Scenariusze `panel-years*` wyglądają jak karta `PageReports` w obu motywach, przy 1280 px i w ramce telefonu
- [ ] 2.6 Przełączenie zakładki nie przesuwa strony i nie zmienia kafelków; fokus widoczny
- [ ] 2.7 Lista „Kwoty według lat” czytana z rokiem bez pozycji

### Phase 3: Link z tabeli „Lata”, reguły i E2E

#### Automated

- [x] 3.1 E2E przechodzą — 59a9db0
- [x] 3.2 Pełna bramka na PostgreSQL w worktree — 59a9db0
- [x] 3.3 Sloppy — 59a9db0
- [x] 3.4 Styl PHP — 59a9db0

#### Manual

- [ ] 3.5 Klik kategorii w „Latach” otwiera Rok 2026 z panelem na „Latach”, liczby zgodne z wierszem
- [ ] 3.6 Z innym rokiem w sidebarze klik przełącza na rok docelowy
- [ ] 3.7 Zarchiwizowana bez wiersza w 2026 nie jest linkiem; podpowiedź w stopce widoczna
- [ ] 3.8 Na produkcji użytkownik doszedł z „Lat” do panelu kategorii
