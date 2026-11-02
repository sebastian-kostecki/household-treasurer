# S-10: Plany miesięcy a budżet roczny — plan implementacji

## Overview

Użytkownik na stronie „Plan miesiąca” widzi pod każdym wierszem kategorii, ile z planu rocznego jest już „zajęte” do oglądanego miesiąca i ile zostaje na resztę roku (FR-010). Odczyt jest liczony na serwerze, reaguje na żywo podczas edycji propozycji i niczego nie zapisuje. Budżet roczny się nie zmienia (ryzyko z `context/foundation/roadmap.md:216`), a różnica między planami miesięcy a rokiem jest widoczna, a nie zerowana (ADR-0033).

## Current State Analysis

- Nic nie zestawia planów miesięcy z rokiem. Jedynym czytelnikiem planów jest `MonthPlanProposal`; `reading(int $month)` daje odczyt dowolnego miesiąca w stanie `approved` / `proposal` / `none`, memoizowany, bez dodatkowych zapytań po `forBudget` (`app/Support/MonthPlanProposal.php:40-64,83-122`).
- Roczne Execution to licznik `categories.execution_minor_units` (`app/Actions/ManageFinancialOperation.php:212-246`). Obejmuje już bieżący miesiąc, bo wszystkie ścieżki wejścia mają datę ≤ dziś, więc „licznik + plan miesiąca” liczyłby wydatki bieżącego miesiąca dwa razy (research, sekcja 3).
- Cięcie Execution według miesiąca daje tylko `BudgetMonthExecutionIndex`, wyłącznie dla wydatków i z czterema innymi wywołującymi (`app/Support/BudgetMonthExecutionIndex.php:81`). `MonthCategoryActuals::forMonth` daje faktyczne kwoty jednego miesiąca dla obu typów plus `hasPositiveAmount` (`app/Support/MonthCategoryActuals.php:16-39`).
- Strona planu buduje wiersze z odczytu i z kategorii z operacjami w miesiącu (`app/Http/Controllers/MonthPlanController.php:115-127`); wiersz ma siatkę Kategoria / Plan / Faktycznie (`resources/js/pages/MonthPlan.vue:505-624`) i żadnych liczb rocznych.
- Reguła pozycji „do wydania” (dochód: plan, po wpływie faktyczna; Bill: plan, po pierwszej płatności dodatniej faktyczna; Running: większa z planu i faktycznej) siedzi w pętli `toSpendMinorUnits` w parze PHP/TS (`app/Support/ToSpendCalculator.php:16-34`, `resources/js/lib/to-spend-calculator.ts:14-37`) i nie jest dostępna dla pojedynczej pozycji.
- Podgląd propozycji reaguje na wpisane kwoty przez `toSpendMinorUnits` w TS (`resources/js/pages/MonthPlan.vue:224-243`).

## Desired End State

Na stronie „Plan miesiąca” budżetu w realizacji każdy wiersz kategorii (dochody i wydatki, także jednorazowe i zarchiwizowane, które strona już pokazuje) ma drugą linię:

- miesiąc 1–11: „Rok: 22 000,00 zł z 24 000,00 zł · zostaje na resztę roku 2 000,00 zł”; dla dochodu „zostaje do otrzymania …”; przy przekroczeniu „ponad plan roczny …”, w `text-destructive` tylko dla wydatku;
- grudzień: „Rok: … z … · różnica do planu rocznego …” (ze znakiem), ujemna dla wydatku w `text-destructive`.

Podczas edycji propozycji linia zmienia się od razu po wpisaniu kwoty. Miniony miesiąc z zatwierdzonym planem pokazuje linię z samego wykonania; miniony miesiąc bez planu (stan `none`) zostaje pustym stanem bez wierszy, więc nie ma linii (`resources/js/pages/MonthPlan.vue:351-357`). Strona Budżet, Dashboard, licznik Execution i Current Plan nie zmieniają się. Weryfikacja: testy jednostkowe odczytu z tabeli przykładów, `assertInertia` trzech nowych propsów, ręczny podgląd z worktree.

### Key Discoveries:

- `MonthPlanProposal::reading` jest memoizowany (`app/Support/MonthPlanProposal.php:78-81`), więc czytanie miesięcy pośrednich w pętli nie dodaje zapytań.
- Kontroler już ma `MonthCategoryActuals` oglądanego miesiąca (`app/Http/Controllers/MonthPlanController.php:117`); odczyt roczny ma go przyjąć zamiast pytać drugi raz.
- Zapytanie „suma na kategorię przed dniem X” (`operation_date < 'YYYY-MM-01'`, `GROUP BY category_id`) jest przenośne między SQLite a PostgreSQL, tak jak w `MonthCategoryActuals`; nie trzeba wyciągać miesiąca z daty.
- Klucze `month_plan.*` są typowane jako `Record<string, string>` (`resources/js/types/shell.ts:41`), więc nowe etykiety to tylko wpisy w `lang/pl/ui.php:279+`.
- `tests/Feature/MonthPlanPageTest.php` ustawia dziś na 2026-06-15 (`:21-26`); `tests/Feature/Nfr/NfrPerf01Test.php` nie otwiera strony planu.

## What We're NOT Doing

- Strona Budżet, Dashboard i inne widoki: bez zmian (wybór: tylko „Plan miesiąca”).
- Initial Plan: porównanie używa Current Plan („Plan roczny” w UI).
- Wiersz Set-aside nie wchodzi do porównania (to nie kategoria).
- Brak wierszy sum na typ i paska postępu; tylko linia tekstowa.
- Brak nowych wierszy: kategoria, której strona dziś nie pokazuje w danym miesiącu, nie dostaje porównania. Miniony miesiąc w stanie `none` zostaje pustym stanem, bez wierszy i bez linii rocznej.
- Zwinięta sekcja kategorii jednorazowych z saldem rocznym (FR-008) zostaje dla S-04; S-10 daje tylko linię roczną w istniejących wierszach.
- Bez migracji, nowych kolumn i zmian snapshotu `user:backup` / `user:restore`.
- `BudgetMonthExecutionIndex` bez zmian (inni wywołujący).
- Odczyt „zatwierdzony vs bieżący plan miesiąca” (S-03): porównanie czyta ten sam `current_amount_minor_units` co „do wydania”.

## Implementation Approach

Nowa klasa odczytu w `app/Support` liczy dla oglądanego miesiąca M i każdej kategorii dwie liczby: „zajęte przed M” i „zajęte do M włącznie”. Kontroler dokłada je z Current Plan do wiersza. Vue pokazuje liczby z propsów, a w trybie edycji propozycji składa „przed M + reguła pozycji(wpisany plan)”, korzystając z reguły pozycji wydzielonej z pary To Spend. Nie powstaje trzecia kopia logiki.

## Critical Implementation Details

**Definicja „zajęte” (decyzja planu, wiąże obie fazy).** Niech f będzie pierwszym nieminionym miesiącem budżetu: miesiąc dzisiejszy dla budżetu bieżącego roku, 13 dla budżetu z minionego roku, 1 dla budżetu z przyszłego roku. Składnik miesiąca m dla kategorii:

- m < f: faktyczne Execution miesiąca (suma operacji, oba typy, zwroty pomniejszają);
- m = f: reguła pozycji „do wydania” z planem z `reading(m)` i faktyczną kwotą m;
- m > f: kwota z `reading(m)` (zatwierdzona albo propozycja), brak pozycji = 0.

„Zajęte przed M” = suma składników m < M; „zajęte” = to plus składnik M. Gdy M > f, składnik M liczony jest tak jak dla m = f (reguła pozycji z faktyczną kwotą M, w praktyce 0), żeby wynik serwera zgadzał się z podglądem w Vue. Nie używać licznika `execution_minor_units`, bo zawiera bieżący miesiąc.

**Zgodność podglądu.** Vue przelicza tylko wtedy, gdy propozycja jest edytowalna (M ≥ f). Wtedy „zajęte” = `year_used_before_month_minor_units + toSpendItemMinorUnits(...)`, czyli to samo co serwer dla niezmienionej kwoty. Dla minionego M Vue nigdy nie liczy, bo serwer używa tam faktycznej kwoty, a nie reguły pozycji.

## Phase 1: Odczyt roczny i propsy

### Overview

Wydzielić regułę pozycji z pary To Spend, dodać odczyt roczny z testami jednostkowymi i dołożyć trzy propsy wiersza po obu stronach kontraktu Inertii.

### Changes Required:

#### 1. Reguła pozycji „do wydania” (para PHP/TS)

**File**: `app/Support/ToSpendCalculator.php`, `resources/js/lib/to-spend-calculator.ts`, `tests/Unit/ToSpendCalculatorTest.php`, `resources/js/lib/to-spend-calculator.test.ts`

**Intent**: Wydzielić kwotę pojedynczej pozycji (bez znaku) jako publiczną funkcję, z której korzysta `toSpendMinorUnits` i odczyt roczny; zachowanie „do wydania” bez zmian. Obie kopie i oba testy zmieniają się razem (`AGENTS.md`, „Month logic lives twice”).

**Contract**: PHP `ToSpendCalculator::itemMinorUnits(CategoryType $type, MonthItemKind $kind, int $plan, int $actual, bool $hasPositiveAmount): int`; TS `toSpendItemMinorUnits(item: ToSpendItem): number`. Dochód: `hasPositiveAmount ? actual : plan`; Bill: tak samo; Running: `max(plan, actual)`. `toSpendMinorUnits` = Σ dochodów − Σ wydatków − Set-aside, jak dotąd. Komentarz w TS o parze z PHP obejmuje nową funkcję.

#### 2. Odczyt roczny

**File**: `app/Support/MonthPlanYearComparison.php` (nowy, `php artisan make:class`), `tests/Unit/MonthPlanYearComparisonTest.php` (nowy)

**Intent**: Policzyć „zajęte przed M” i „zajęte do M” na kategorię według definicji z „Critical Implementation Details”, w stałej liczbie zapytań, bez zapisu.

**Contract**:
- `MonthPlanYearComparison::forMonth(Budget $budget, MonthPlanProposal $proposal, MonthCategoryActuals $viewedMonthActuals, int $month, CarbonImmutable $today): self`
- `usedBeforeMonthMinorUnits(Category $category): int`, `usedMinorUnits(Category $category): int`
- Zapytania: jedno grupowane `SUM(amount_minor_units)` na `category_id` dla `budget_id` i `operation_date < pierwszy dzień min(M, f)` (pomijane, gdy min(M, f) = 1), plus `MonthCategoryActuals::forMonth(f)` tylko wtedy, gdy f < M ≤ 12. Plany miesięcy z `$proposal->reading($m)`; typ i rodzaj pozycji z `Category`.

#### 3. Propsy wiersza

**File**: `app/Http/Controllers/MonthPlanController.php`, `resources/js/pages/MonthPlan.vue` (tylko typ `MonthPlanItemRow`), `tests/Feature/MonthPlanPageTest.php`

**Intent**: Dołożyć do każdego wiersza trzy liczby roczne; typ TS zmienia się w tym samym commicie (`AGENTS.md`, „Inertia props contract”).

**Contract**: w `itemRow` i w typie `MonthPlanItemRow` nowe pola `annual_plan_minor_units: int` (= `planned_amount_minor_units`, Current Plan), `year_used_before_month_minor_units: int`, `year_used_minor_units: int`. `monthPlanPayload` tworzy `MonthPlanYearComparison` raz z już załadowanych `$proposal` i `$actuals` i przekazuje go do `itemRow`. PHPDoc kształtu tablicy rozszerzony.

### Success Criteria:

#### Automated Verification:

- `tests/Unit/ToSpendCalculatorTest.php` przechodzi: dotychczasowe przypadki bez zmian oraz `itemMinorUnits` dla dochodu (plan / po wpływie), Bill (plan / po płatności / tylko zwrot = plan) i Running (plan > faktyczna, faktyczna > plan).
- `npm test` przechodzi, w tym `to-spend-calculator.test.ts` z tymi samymi przypadkami `toSpendItemMinorUnits`.
- `tests/Unit/MonthPlanYearComparisonTest.php` przechodzi i obejmuje wszystkie przypadki z tabeli w „Testing Strategy” oraz liczbę zapytań (≤ 2 ponad `forBudget`).
- `tests/Feature/MonthPlanPageTest.php` przechodzi i asercjami `assertInertia` sprawdza trzy nowe propsy dla miesiąca bieżącego, przyszłego i minionego z zatwierdzonym planem (`MonthPlanFactory`), w tym dla wiersza dochodu i kategorii jednorazowej.
- Testy fazy przechodzą na PostgreSQL: `docker run … sail-8.5/app artisan test --compact tests/Unit/ToSpendCalculatorTest.php tests/Unit/MonthPlanYearComparisonTest.php tests/Feature/MonthPlanPageTest.php`.
- `npm run build`, `npm run check` i `npm run types:check` przechodzą.
- `vendor/bin/pint --dirty --format agent` i `vendor/bin/sloppy ci` (host) przechodzą.

#### Manual Verification:

- Wartości w testach odczytu odpowiadają tabeli przykładów z „Testing Strategy”.

**Implementation Note**: Po zielonej automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Linia roczna w widoku planu

### Overview

Pokazać linię roczną pod każdym wierszem, z podglądem na żywo w edycji propozycji, polskimi etykietami i jedną podpowiedzią wyjaśniającą, z czego składa się „Rok”.

### Changes Required:

#### 1. Linia roczna w wierszu

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Pod każdym wierszem w siatce (pełna szerokość, `text-xs`, `text-muted-foreground`, `tabular-nums`) pokazać „Rok: {zajęte} z {plan roczny} · {etykieta pozostałego}”. W trybie edycji „zajęte” liczy się z wpisanej kwoty, poza nim pochodzi z propsu.

**Contract**:
- zajęte = `isEditable && row.in_plan ? row.year_used_before_month_minor_units + toSpendItemMinorUnits({ type, kind, plan: parsedAmount(row) ?? 0, actual, hasPositiveAmount }) : row.year_used_minor_units`;
- pozostałe = `annual_plan_minor_units − zajęte`;
- M < 12: pozostałe ≥ 0 → `year_left_expense` / `year_left_income`; pozostałe < 0 → `year_over_annual` z |pozostałe|;
- M = 12: `year_gap_december` z pozostałym ze znakiem;
- `text-destructive` tylko dla wydatku z pozostałym < 0; dochód bez koloru alarmu;
- kwoty przez `formatMoney` (`.ai/rules/js.md`).

#### 2. Podpowiedź

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Raz nad listami kategorii krótki tekst `year_hint`, który mówi, że „Rok” to wykonanie minionych miesięcy, bieżący miesiąc według zasady „do wydania” i plany (także propozycje) kolejnych miesięcy do oglądanego. Dzięki temu dodatnia reszta nie jest czytana jako oszczędność, a propozycje jako zatwierdzone.

**Contract**: `<p class="text-muted-foreground text-xs">` przed `ListSurface`, tylko gdy `monthPlan !== null`.

#### 3. Teksty

**File**: `lang/pl/ui.php` (sekcja `month_plan`)

**Intent**: Dodać etykiety linii rocznej; nie nazywać planu miesiąca „plan bieżący” (`CONTEXT.md:73`).

**Contract**: klucze `year_line` („Rok: :used z :annual”), `year_left_expense` („zostaje na resztę roku :amount”), `year_left_income` („zostaje do otrzymania :amount”), `year_over_annual` („ponad plan roczny :amount”), `year_gap_december` („różnica do planu rocznego :amount”), `year_hint`. Podstawianie przez `.replace(':x', …)` jak przy `draw_warning`.

#### 4. Wydajność

**File**: `tests/Feature/Nfr/NfrPerf01Test.php`

**Intent**: Objąć stronę „Plan miesiąca” progiem 2 s przy 10 000 operacji, bo dochodzi zapytanie grupowane.

**Contract**: dwa nowe bloki `assertRequestWithinSeconds` obok istniejących (test nie ma pętli stron): `get(route('month-plan'))` (M = f, czerwiec przy `travelTo('2026-06-01')`) i `get(route('month-plan', ['month' => 12]))` (M > f, z dodatkowym `MonthCategoryActuals`), oba z `assertOk()`. Test jest w grupie `nfr`, którą `phpunit.xml:20-24` wyklucza i której CI nie uruchamia, więc uruchamia się go przez `--configuration=phpunit.nfr.xml`.

### Success Criteria:

#### Automated Verification:

- `npm run build`, `npm run check` i `npm run types:check` przechodzą.
- `npm test` przechodzi.
- `tests/Feature/MonthPlanPageTest.php` i `tests/Feature/Nfr/NfrPerf01Test.php` przechodzą na PostgreSQL, dwiema komendami (domyślny `phpunit.xml` pomija grupę `nfr`, więc wspólna komenda zgłosiłaby sukces bez uruchomienia testu wydajności): `docker run … sail-8.5/app artisan test --compact tests/Feature/MonthPlanPageTest.php` oraz `docker run … sail-8.5/app artisan test --compact --configuration=phpunit.nfr.xml tests/Feature/Nfr/NfrPerf01Test.php`; wynik drugiej pokazuje test jako wykonany, nie pominięty.
- `vendor/bin/pint --dirty --format agent` i `vendor/bin/sloppy ci` (host) przechodzą.

#### Manual Verification:

- Propozycja bieżącego miesiąca: każdy wiersz ma linię „Rok: … z … · zostaje …”, a zmiana kwoty planu zmienia linię od razu, zgodnie z regułą pozycji (Running poniżej faktycznej kwoty nie obniża „zajęte”).
- Wydatek ponad plan roczny pokazuje „ponad plan roczny …” w kolorze destructive; dochód ponad plan roczny nie ma koloru alarmu.
- Grudzień pokazuje „różnica do planu rocznego” ze znakiem.
- Miesiąc przyszły uwzględnia plany miesięcy pośrednich; miniony miesiąc bez planu pokazuje pusty stan jak dotąd (linię minionego miesiąca z zatwierdzonym planem sprawdza test Feature, bo w podglądzie takiego miesiąca nie ma).
- Kategoria jednorazowa i zarchiwizowany wiersz z operacją mają linię.
- Na szerokości 360 px linia się zawija, bez poziomego przewijania.
- Strona Budżet po przejrzeniu planu pokazuje te same Plan bieżący, Wykonanie i Pozostały limit co przed zmianą.

**Implementation Note**: Po zielonej automatycznej weryfikacji zbuduj podgląd z worktree (sekcja „Preview from a worktree” w `AGENTS.md`) i zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

`MonthPlanYearComparisonTest`, dziś 2026-06-15, budżet 2026 w realizacji, bez zatwierdzonych planów, chyba że podano inaczej. Kategoria „Jedzenie” (Running, Current Plan 2 400 000, operacje po 200 000 w styczniu–maju, 30 000 w czerwcu; propozycja czerwca i lipca 200 000):

| Przypadek | M | przed M | zajęte |
| --- | --- | --- | --- |
| bieżący, faktyczna < plan | 6 | 1 000 000 | 1 200 000 |
| bieżący, czerwiec 250 000 zamiast 30 000 | 6 | 1 000 000 | 1 250 000 |
| przyszły (czerwiec reguła + lipiec plan) | 8 | 1 400 000 | 1 600 000 |
| miniony (tylko wykonanie) | 3 | 400 000 | 600 000 |
| miniony z zatwierdzonym planem marca 500 000 | 3 | 400 000 | 600 000 |

Pozostałe przypadki:
- Bill „Czynsz”, plan czerwca 200 000: bez płatności → składnik 200 000; zapłacone 195 000 → 195 000; tylko zwrot −10 000 → 200 000.
- Dochód „Pensja”, plan 800 000: bez wpływu → 800 000; wpływ 810 000 → 810 000.
- Jednorazowa „Prezent” (Current Plan 100 000, plan 0, 40 000 do maja, 30 000 w czerwcu): M = 6 → przed 40 000, zajęte 70 000.
- Zatwierdzony plan czerwca 200 000 i lipca 150 000 (czerwiec też zatwierdzony, bo `ApproveMonthPlan.php:44` nie pozwala zatwierdzić lipca przy propozycji czerwca): M = 8 → przed 1 350 000.
- Budżet 2025 (miniony rok), M = 12: zajęte = suma operacji roku.
- Budżet 2027 (przyszły rok), M = 2: przed = propozycja stycznia, zajęte = styczeń + luty.
- Liczba zapytań: najwyżej 2 ponad `MonthPlanProposal::forBudget` dla M > f, najwyżej 1 dla M ≤ f, 0 dla budżetu z przyszłego roku przy M = 1.

`ToSpendCalculatorTest` / `to-spend-calculator.test.ts`: przypadki `itemMinorUnits` / `toSpendItemMinorUnits` z kryteriów fazy 1, identyczne w obu plikach.

### Integration Tests:

- `MonthPlanPageTest`: trzy nowe propsy na wierszach istniejącego scenariusza kanonicznego (bieżący miesiąc), dla `?month=8` i dla minionego miesiąca z zatwierdzonym planem (`MonthPlanFactory`); wartości wyliczone z fixture testu. `canonicalBudget()` ma operacje tylko w czerwcu, więc nowe testy dokładają operacje sprzed czerwca (np. Jedzenie 2026-05-10 i wpływ Pensji w maju, operacja typu Income) i sprawdzają niezerowe `year_used_before_month_minor_units`, żeby odróżnić `<` od `<=` i wykryć podwójne liczenie bieżącego miesiąca. Kanoniczny fixture zostaje bez zmian.
- `NfrPerf01Test`: strona planu poniżej 2 s przy 10 000 operacji.

### Manual Testing Steps:

1. Podgląd z worktree, zaloguj `test@example.com` / `password`, otwórz „Plan miesiąca”.
2. Zmień kwotę wydatku Running w propozycji i obserwuj linię roczną.
3. Przejdź do grudnia, przyszłego miesiąca i minionego miesiąca.
4. Sprawdź wiersz jednorazowy, wiersz dochodu i wąski ekran.
5. Porównaj stronę Budżet przed i po.

## Performance Considerations

Jedno zapytanie grupowane na budżet (agregat po stronie bazy, nie wiersze do PHP jak w `BudgetMonthExecutionIndex`) i najwyżej jedno `MonthCategoryActuals` więcej. Pętla po miesiącach i kategoriach działa na memoizowanych odczytach, bez zapytań (Sloppy SL1xx). Próg 2 s potwierdza `NfrPerf01Test`.

## Migration Notes

Brak. Odczyt bez nowych danych; snapshot `user:backup` / `user:restore` bez zmian.

## References

- Research: `context/changes/month-plans-vs-year/research.md`
- ADR: `.docs/adr/0033-month-plan-beside-annual-budget.md`
- PRD FR-010: `context/foundation/prd.md:125-126`
- Roadmap S-10: `context/foundation/roadmap.md:207-217`
- Odczyt planu: `app/Support/MonthPlanProposal.php:62,83-122`
- Faktyczne kwoty miesiąca: `app/Support/MonthCategoryActuals.php:16-39`
- Para To Spend: `app/Support/ToSpendCalculator.php:16-34`, `resources/js/lib/to-spend-calculator.ts:14-37`
- Strona planu: `app/Http/Controllers/MonthPlanController.php:113-208`, `resources/js/pages/MonthPlan.vue:29-66,224-243,505-624`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Odczyt roczny i propsy

#### Automated

- [x] 1.1 ToSpendCalculatorTest z przypadkami itemMinorUnits, dotychczasowe bez zmian — eb2ea35
- [x] 1.2 npm test z przypadkami toSpendItemMinorUnits — eb2ea35
- [x] 1.3 MonthPlanYearComparisonTest z tabelą przykładów i liczbą zapytań — eb2ea35
- [x] 1.4 MonthPlanPageTest: trzy nowe propsy dla miesiąca bieżącego, przyszłego i minionego — eb2ea35
- [x] 1.5 Testy fazy przechodzą na PostgreSQL — eb2ea35
- [ ] 1.6 npm run build, check i types:check przechodzą
- [x] 1.7 Pint i Sloppy przechodzą — eb2ea35

#### Manual

- [x] 1.8 Wartości testów odczytu odpowiadają tabeli przykładów — eb2ea35

### Phase 2: Linia roczna w widoku planu

#### Automated

- [ ] 2.1 npm run build, check i types:check przechodzą
- [x] 2.2 npm test przechodzi
- [x] 2.3 MonthPlanPageTest i NfrPerf01Test przechodzą na PostgreSQL
- [x] 2.4 Pint i Sloppy przechodzą

#### Manual

- [x] 2.5 Linia roczna w propozycji reaguje na zmianę kwoty
- [x] 2.6 Destructive tylko dla wydatku ponad plan roczny
- [x] 2.7 Grudzień pokazuje różnicę ze znakiem
- [x] 2.8 Miesiąc przyszły z planami pośrednimi, miniony z wykonania
- [x] 2.9 Jednorazowa i zarchiwizowana mają linię
- [x] 2.10 Wąski ekran bez poziomego przewijania
- [x] 2.11 Strona Budżet bez zmian
