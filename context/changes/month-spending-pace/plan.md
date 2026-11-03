# S-05: Tempo wydatków względem upływu dni miesiąca — plan implementacji

## Overview

Strona „Plan miesiąca” dostaje odczyt **tempa**: sumę kwot faktycznych przez sumę planów puli pozycji „bieżących”, które nie są kategoriami jednorazowymi. Obok jest **upływ dni miesiąca** (pasek ze znacznikiem i tekst). Gdy tempo wyprzedza upływ dni o co najmniej 10 pp, odczyt ma kolor `warning` i podpis. Podczas edycji propozycji albo zmiany zatwierdzonego planu tempo reaguje na wpisywane kwoty, tak jak „do wydania”. Predykat progu i odczyt powstają na serwerze, żeby S-07 mógł z nich zbudować powiadomienie Dashboardu bez nowej definicji.

## Current State Analysis

- Kodu tempa nie ma (research, Summary 3). Definicje są w `CONTEXT.md` (Month Pace, Month Elapsed) i nie wymagają nowej decyzji domenowej.
- Wiersze miesiąca i ich kwoty mają jedno źródło: `MonthToSpend::forMonth` (`app/Support/MonthToSpend.php:32-49`) i `rowAmounts` (`:83-90`). `toSpendMinorUnits` (`:54-75`) składa z nich elementy dla czystego kalkulatora. Tempo pójdzie tą samą drogą.
- `YearElapsed` (`app/Support/YearElapsed.php:9-25`) to wzorzec daty odniesienia z wstrzykniętym „dziś” (`CarbonImmutable::today('UTC')`).
- `MonthPlanController::monthPlanPayload` (`app/Http/Controllers/MonthPlanController.php:143-203`) składa prop `monthPlan`. Kontroler zna już `$timing` (`:56`, `:276-286`).
- `MonthPlan.vue` liczy „do wydania” na żywo z kopii TS, gdy `isEditable` (`resources/js/pages/MonthPlan.vue:260-279`). Poza edycją bierze liczbę z serwera. Propozycja, którą można zatwierdzić, jest zawsze w trybie edycji (`:93-98`).
- Zmiana rodzaju „Rachunek / Bieżąca” idzie przez serwer (`MonthPlan.vue:304-320`) i przeładowuje propsy, więc odczyt z serwera za nią nadąża.
- Propozycja pomija kategorie zarchiwizowane przed 1. dniem miesiąca (`app/Support/MonthPlanProposal.php:116`). Taka kategoria wchodzi do wierszy tylko przez operację, wtedy z planem 0.

## Desired End State

- `monthPlan.month_pace` jest wysyłany dla bieżącego miesiąca i minionego miesiąca z planem. Dla przyszłego miesiąca jest `null`, a przy stanie `none` cały `monthPlan` i tak pokazuje pusty stan.
- Strona pokazuje pod liczbą „do wydania” pasek tempa ze znacznikiem upływu dni i tekst, np. „Tempo 59% · upływ dni 15 z 30 (50%)”.
- Przy `is_ahead` pasek i procent są w `warning` i pojawia się podpis. Przy sumie planu puli 0 strona pokazuje komunikat, że tempa nie ma.
- W trybie edycji odczyt przelicza się z wpisanych kwot.
- Na fixturze kanonicznej 15 czerwca 2026: pula 190 000 / 112 000, tempo 59%, upływ 15/30, `is_ahead = false`.

### Key Discoveries:

- Kategoria dochodu też zapisuje `is_paced = true` i `month_item_kind = running` (research 2, `tests/Feature/CategoryMonthItemKindTest.php:60`), więc predykat puli musi jawnie wymagać typu „wydatek”.
- One-off = `Expense ∧ ! is_paced` (`MonthPlanController.php:232-233`), a wiersz TS ma gotowe `is_one_off` (`resources/js/types/month-plan.ts`).
- Konwencja procentu wyświetlanego to `CategoryExecutionMetrics::percentComplete`: `null` przy planie 0, inaczej `(int) round(…)` (`app/Support/CategoryExecutionMetrics.php:12-19`).
- Wzorzec pary PHP/TS z testami na tej samej fixturze: `app/Support/ToSpendCalculator.php`, `resources/js/lib/to-spend-calculator.ts`, `tests/Unit/ToSpendCalculatorTest.php`, `resources/js/lib/to-spend-calculator.test.ts`.
- Testy strony zamrażają czas na `2026-06-15` (`tests/Feature/MonthPlanPageTest.php:25`), a kanoniczny budżet daje `to_spend_minor_units = 373_000` (`:52`). Pula tempa w tej fixturze: Jedzenie 150 000 / 60 000 i Paliwo 40 000 / 52 000.

## What We're NOT Doing

- **Dashboard i strona Budżet** nie dostają tempa. Dashboard pokaże je dopiero przez powiadomienie S-07 (decyzja: tylko strona „Plan miesiąca”).
- **Powiadomienie `warning` na Dashboardzie** (`DashboardNotices`) to S-07. S-05 dostarcza tylko predykat `is_ahead` i odczyt `MonthPace`.
- **Tempo per kategoria i procent per wiersz** odrzucił ADR-0033 (`.docs/adr/0033-month-plan-beside-annual-budget.md:11`), a S-04 przekazał to S-05 jako jedną pulę.
- **Tempo przyszłych miesięcy** jest ukryte: operacje nie mogą mieć przyszłej daty, więc zawsze byłoby „0% · 1 z n dni”.
- **Kolor `destructive` dla tempa > 100%** nie powstaje. Przekroczenie pozycji ma już własny sygnał „ponad plan” w wierszu (`is_over_plan`), a tempo jest sygnałem wyprzedzenia, nie przekroczenia.
- **Wykluczanie kategorii zarchiwizowanych i z planem 0** nie wchodzi w zakres. Decyzja: pula obejmuje wszystkie wiersze „bieżące” miesiąca, tak jak „do wydania”.
- **Spłata zduplikowanej klasyfikacji miesiąca** (miniony, bieżący, przyszły) w `MonthPlanController`, `MonthRemainingCalculator` i `MonthPlanProposal` jest poza zakresem. `MonthElapsed` zwraca datę, nie etykietę, a kontroler zostaje przy swoim `monthTiming`.
- Brak migracji, zapisu tempa i zmian snapshotu `user:backup` / `user:restore`.

## Implementation Approach

Odczyt liczony jest na serwerze z tych samych wierszy co „do wydania” i trafia do propsów. Kopia TS istnieje wyłącznie dla podglądu na żywo, z tym samym uzasadnieniem co para „do wydania”: propozycji nie da się zapisać przed zatwierdzeniem, a zmiana zatwierdzonego planu nadpisuje plan końcowy, więc podgląd musi reagować przed wysłaniem. Wzór to dwie sumy i jeden predykat całkowity, więc koszt utrzymania pary jest mały.

Próg porównuje wartości dokładne przez mnożenie krzyżowe na liczbach całkowitych, bez floatów:

```
isAhead ⇔ planSum > 0 ∧ 10·actualSum·daysInMonth − 10·elapsedDay·planSum ≥ planSum·daysInMonth
```

To przekształcenie `100·actual/plan − 100·day/days ≥ 10` po pomnożeniu przez `plan·days / 10` (plan > 0, days > 0). Procent tempa i procent upływu są zaokrąglane wyłącznie do wyświetlania. Dzięki temu „Tempo 40% · … (30%)” przy surowym 39,6% nie świeci się na bursztynowo.

## Critical Implementation Details

- **Zaokrąglenie w obu kopiach.** PHP `round()` zaokrągla połówki od zera, a JS `Math.round(-2.5)` daje `-2`. Suma faktyczna puli może być ujemna (zwroty netto), więc kopia TS zaokrągla `Math.sign(x) * Math.round(Math.abs(x))`, a obie kopie testują przypadek ujemny.
- **Zakres liczb w TS.** Iloczyn `10·actualSum·daysInMonth` przy rozsądnych kwotach domowych mieści się w `Number.MAX_SAFE_INTEGER` (≈ 9·10¹⁵). Granica pojedynczej kwoty (10¹² Minor units) daje 3,1·10¹⁴ na pozycję, więc nie dodajemy `BigInt`.

## Phase 1: Odczyt tempa na serwerze i wzór w parze PHP/TS

### Overview

Helper upływu dni, czysty kalkulator tempa w parze PHP/TS z testami, odczyt `MonthPace` złożony z `MonthToSpend` oraz prop `monthPlan.month_pace` (PHP i typ TS w jednym commicie) z `assertInertia`.

### Changes Required:

#### 1. Upływ dni miesiąca

**Files**: `app/Support/MonthElapsed.php` (nowy, `php artisan make:class`), `tests/Unit/MonthElapsedTest.php` (nowy)

**Intent**: Dać datę odniesienia miesiąca jak `YearElapsed`, bez czytania zegara: dziś dla bieżącego, ostatni dzień dla minionego, pierwszy dla przyszłego.

**Contract**: `MonthElapsed::asOfDate(int $year, int $month, CarbonImmutable $today): CarbonImmutable` porównuje `[year, month]` z `[today->year, today->month]` i zwraca `startOfDay()`. Dzień daty odniesienia (`->day`) i `->daysInMonth` to liczby całkowite dla wołających. Testy (Pest, wstrzyknięta data, jak `tests/Unit/YearElapsedTest.php`):
- bieżący miesiąc daje dziś,
- miniony miesiąc tego roku i miniony rok dają ostatni dzień (luty 2028 ma 29 dni),
- przyszły miesiąc i przyszły rok dają 1. dzień,
- pora dnia nie ma znaczenia.

#### 2. Kalkulator tempa (PHP)

**Files**: `app/Support/MonthPaceCalculator.php` (nowy), `tests/Unit/MonthPaceCalculatorTest.php` (nowy)

**Intent**: Jedno miejsce definicji puli, procentu i progu, które przejmie S-07.

**Contract**: Klasa statyczna jak `ToSpendCalculator`; docblock wskazuje kopię TS i każe zmieniać obie razem.
- `poolSums(array $items): array{plan_minor_units: int, actual_minor_units: int}` z `@param list<array{type: CategoryType, kind: MonthItemKind, is_one_off: bool, plan: int, actual: int}> $items`. Do puli wchodzi element `type === Expense ∧ kind === Running ∧ ! is_one_off`.
- `percent(int $planSum, int $actualSum): ?int`: `null` przy `planSum <= 0`, inaczej `(int) round(100 * $actualSum / $planSum)`.
- `isAheadOfMonth(int $planSum, int $actualSum, int $elapsedDay, int $daysInMonth): bool`: `false` przy `planSum <= 0`, inaczej predykat całkowity z „Implementation Approach”.

Testy:
- **Fixtura kanoniczna** jak `canonicalToSpendItems`, z dochodem, rachunkiem, dwiema bieżącymi i bieżącą jednorazową: sumy 190 000 / 112 000, procent 59; przy 15/30 `false`. Każde wykluczenie (dochód, rachunek, jednorazowa) zmieniłoby wynik.
- **F-01:** plan 180 000, faktyczne 75 000; procent 42; dzień 10/30 daje `false`, dzień 9/30 daje `true`.
- **Granica:** dokładnie 10 pp (100 000 / 60 000, 15/30) daje `true`.
- **Pułapka zaokrąglenia:** 100 000 / 39 600 przy 9/30 daje procent 40, ale `isAheadOfMonth` zwraca `false`.
- **Plan 0:** procent `null`, `isAheadOfMonth` zwraca `false`; pozycja bieżąca z planem 0 i wydatkiem podnosi tylko sumę faktyczną.
- **Ujemna suma faktyczna:** procent ujemny, zaokrąglony od zera przy połówce, np. 200 000 / −5 000 daje −3.
- **Miniony miesiąc:** dzień = liczba dni (31/31) i tempo 115% daje `true`, a tempo 105% daje `false`.

#### 3. Kalkulator tempa (TS)

**Files**: `resources/js/lib/month-pace-calculator.ts` (nowy), `resources/js/lib/month-pace-calculator.test.ts` (nowy)

**Intent**: Kopia wzoru wyłącznie dla podglądu na żywo na stronie „Plan miesiąca”.

**Contract**:
- `export type MonthPaceItem = { type: 'income' | 'expense'; kind: 'bill' | 'running' | null; isOneOff: boolean; plan: number; actual: number }`.
- `monthPacePoolSums(items): { planMinorUnits: number; actualMinorUnits: number }`, `monthPacePercent(planSum, actualSum): number | null`, `isMonthPaceAhead(planSum, actualSum, elapsedDay, daysInMonth): boolean`.
- Zaokrąglenie połówek od zera (sekcja „Critical Implementation Details”).
- Docblock jak `to-spend-calculator.ts:9-14`, z oboma powodami kopii.
- Test: te same przypadki co test PHP, importy z `vite-plus/test` i ścieżki względne.

#### 4. Odczyt tempa miesiąca

**Files**: `app/Support/MonthPace.php` (nowy), `tests/Feature/MonthPaceTest.php` (nowy)

**Intent**: Złożyć odczyt z wierszy `MonthToSpend` i upływu dni, w kształcie wysyłanym jako prop. S-07 weźmie go dla bieżącego miesiąca bez nowego zapytania.

**Contract**: `MonthPace::forMonth(MonthToSpend $toSpend, int $year, int $month, CarbonImmutable $today): ?array`.
- Zwraca `null`, gdy `$toSpend->reading->state === 'none'`.
- W innym wypadku buduje elementy z `$toSpend->categories` i `$toSpend->rowAmounts($category)`, z `is_one_off = type === Expense && ! is_paced` (jak `MonthPlanController.php:233`), i woła `MonthPaceCalculator`.
- Kształt (docblock array shape): `array{as_of: string, elapsed_day: int, days_in_month: int, pool_plan_minor_units: int, pool_actual_minor_units: int, percent: int|null, is_ahead: bool}`; `as_of` w formacie `Y-m-d`.
- Nie ukrywa przyszłych miesięcy, bo to decyzja strony. Sam odczyt jest poprawny dla każdego miesiąca z planem.

Testy na kanonicznej fixturze z `tests/Feature/MonthToSpendTest.php:97-126`:
- propozycja czerwca 15.06 daje 190 000 / 112 000 / 59 / `false` / `2026-06-15` / 15 / 30;
- zatwierdzony plan liczy kwoty bieżące, nie zatwierdzone;
- zarchiwizowana kategoria bieżąca z operacją w miesiącu podnosi sumę faktyczną puli przy planie 0;
- stan `none` (miniony bez planu) daje `null`.

#### 5. Prop strony i typ TS

**Files**: `app/Http/Controllers/MonthPlanController.php`, `resources/js/pages/MonthPlan.vue` (tylko typ `MonthPlanReading`), `tests/Feature/MonthPlanPageTest.php`

**Intent**: Wysłać odczyt tempa dla bieżącego i minionego miesiąca. Typ TS dochodzi w tym samym commicie (reguła „Inertia props contract” w `AGENTS.md`).

**Contract**:
- W `monthPlanPayload` nowy klucz `month_pace` = `$timing === 'future' ? null : MonthPace::forMonth($toSpend, $budget->year, $month, $today)`; docblock tablicy dostaje pole.
- W TS `MonthPlanReading.month_pace: MonthPace | null`, gdzie typ `MonthPace` (snake_case jak prop) leży w `resources/js/types/month-plan.ts`.

Testy `assertInertia`:
- `test_current_month_proposal_exposes_the_canonical_to_spend_number` (albo nowy test obok) dostaje wartości `month_pace.*` z fixtury kanonicznej;
- przyszły miesiąc ma `month_pace = null`;
- `test_past_approved_month_is_read_only` dostaje `month_pace.elapsed_day == days_in_month` i `as_of` = ostatni dzień miesiąca;
- nowy test wyprzedzenia: wydatki bieżące wcześnie w miesiącu, np. `travelTo` na 3. dzień, dają `is_ahead = true`;
- miesiąc bez planu pozycji bieżących (tylko rachunek i jednorazowa) ma `percent = null`, `is_ahead = false`.

### Success Criteria:

#### Automated Verification:

- `tests/Unit/MonthElapsedTest.php` i `tests/Unit/MonthPaceCalculatorTest.php` przechodzą
- `tests/Feature/MonthPaceTest.php`, `tests/Feature/MonthPlanPageTest.php` i `tests/Feature/MonthToSpendTest.php` przechodzą na PostgreSQL (obraz Sail na worktree, `AGENTS.md` „Test database”)
- `npm test` przechodzi, w tym `resources/js/lib/month-pace-calculator.test.ts`
- `vendor/bin/pint --dirty --format agent` przechodzi
- `npm run build`, potem `npm run types:check` i `npm run check` przechodzą
- `vendor/bin/sloppy ci` na hoście nie zgłasza nowych znalezisk

**Implementation Note**: Faza 1 nie ma kroków ręcznych; po przejściu automatycznych przejdź do fazy 2.

---

## Phase 2: Tempo na stronie „Plan miesiąca”

### Overview

Pasek tempa ze znacznikiem dni i tekst pod liczbą „do wydania”, kolor `warning` z podpisem przy wyprzedzeniu, podgląd na żywo w trybie edycji, stan „brak planu bieżących”, teksty PL i wpisy w dokumentach.

### Changes Required:

#### 1. Komponent odczytu

**File**: `resources/js/components/MonthPaceMeter.vue` (nowy)

**Intent**: Pokazać lukę między wydatkami a upływem dni na pierwszy rzut oka. Tekst ma nieść całą informację, a kolor jest tylko dodatkiem.

**Contract**:
- Propsy: `percent: number | null`, `isAhead: boolean`, `elapsedDay: number`, `daysInMonth: number`.
- Pasek: tor i znacznik według stylu `CategoryMeterRow.vue:95-116`. Wypełnienie ma szerokość `clamp(percent, 0, 100)%` i kolor `bg-warning`, gdy `isAhead`, w innym razie `bg-primary`. Znacznik leży w `left: 100·elapsedDay/daysInMonth %`. Pasek ma `aria-hidden="true"`.
- Tekst `pace_reading`: `:pace` = `percent`, `:day`, `:days`, `:elapsed` = upływ zaokrąglony do całości. Procent jest w `text-warning`, gdy `isAhead`. Kwoty nie są wyświetlane, a liczby mają `tabular-nums`.
- `isAhead` dodaje podpis `pace_ahead` (`text-warning text-xs`).
- `percent === null` pokazuje zamiast paska i tekstu `pace_none` (`text-muted-foreground text-xs`) oraz upływ dni.
- Tylko tokeny (`.ai/rules/js.md`), bez zmian w `components/ui/*`.

#### 2. Strona

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Wpiąć odczyt pod liczbę „do wydania” i przeliczać go na żywo w trybie edycji, tak jak `toSpendPreviewMinorUnits`.

**Contract**:
- Nowe `pacePreview` (computed): `null`, gdy `monthPlan.month_pace === null`.
- Poza edycją (`!isEditable`) `pacePreview` bierze `percent` i `is_ahead` z serwera.
- W edycji `pacePreview` buduje elementy z `allRows`: `plan` = `isEditableRow(row) ? (parsedAmount(row) ?? 0) : 0`, jak `:273`; `actual` = `row.actual_minor_units`; `isOneOff` = `row.is_one_off`. Woła `monthPacePoolSums`, `monthPacePercent` i `isMonthPaceAhead` z `elapsed_day`/`days_in_month` z propsa.
- `MonthPaceMeter` stoi w karcie „do wydania” (`:461-492`), pod podpowiedzią, oddzielony od niej (np. `border-t pt-4`), z etykietą `pace` w stylu etykiety „do wydania”.

#### 3. Teksty

**File**: `lang/pl/ui.php` (sekcja `month_plan`)

**Intent**: Polska kopia odczytu. Typ `Record<string, string>` (`resources/js/types/shell.ts:41`) zostaje bez zmian.

**Contract**:
- `pace` → „Tempo”
- `pace_reading` → „Tempo :pace% · upływ dni :day z :days (:elapsed%)”
- `pace_ahead` → „Wydatki bieżące wyprzedzają upływ dni o co najmniej 10 pkt proc.”
- `pace_none` → „Pozycje bieżące nie mają planu, więc tempa nie ma.”
- `pace_days` → „Upływ dni :day z :days (:elapsed%)” (dla stanu bez tempa)

#### 4. Dokumenty

**Files**: `AGENTS.md` (sekcja „Month logic lives twice”), `CONTEXT.md` (wpis Month Elapsed)

**Intent**: Zapisać czwartą parę PHP/TS z powodem kopii i dodać polską kopię dla Month Elapsed, której wpis nie podaje.

**Contract**:
- `AGENTS.md` dostaje akapit: „`app/Support/MonthPaceCalculator.php` and `resources/js/lib/month-pace-calculator.ts` are a fourth pair, covered by `tests/Unit/MonthPaceCalculatorTest.php` and `resources/js/lib/month-pace-calculator.test.ts`. Change both copies and both tests together. The TS copy exists for the live preview on the month plan page, for the same two reasons as the To Spend pair.”
- `CONTEXT.md`, Month Elapsed, dostaje: „Polish UI copy: „upływ dni”.”

### Success Criteria:

#### Automated Verification:

- `tests/Feature/MonthPlanPageTest.php` i `tests/Feature/MonthPaceTest.php` przechodzą na PostgreSQL
- `npm test` przechodzi
- `npm run build`, potem `npm run types:check` i `npm run check` przechodzą
- Pełna bramka CI (`composer ci:check` w obrazie Sail na worktree) przechodzi
- `vendor/bin/sloppy ci` na hoście nie zgłasza nowych znalezisk

#### Manual Verification:

- W podglądzie worktree (`AGENTS.md` „Preview from a worktree”) bieżący miesiąc pokazuje pod „do wydania” pasek tempa ze znacznikiem dni i tekst „Tempo X% · upływ dni d z n (Y%)”
- W propozycji (albo w trybie „Zmień plan”) zmiana kwoty pozycji bieżącej przesuwa tempo na żywo; zmiana kwoty rachunku, jednorazowej albo dochodu go nie zmienia
- Przy tempie wyprzedzającym upływ dni o ≥ 10 pp pasek i procent są bursztynowe i widać podpis ostrzeżenia; po podniesieniu planu bieżących poniżej progu kolor i podpis znikają
- Wyzerowanie planów wszystkich pozycji bieżących pokazuje komunikat „Pozycje bieżące nie mają planu…” zamiast paska
- Miniony zatwierdzony miesiąc pokazuje tempo z upływem „n z n”, a przyszły miesiąc nie pokazuje tempa
- Na szerokości telefonu karta „do wydania” z tempem nie przewija się poziomo, a tempo > 100% nie wychodzi poza tor

**Implementation Note**: Po przejściu automatycznych kroków zbuduj podgląd i poproś o potwierdzenie kroków ręcznych przed zamknięciem fazy.

---

## Testing Strategy

### Unit Tests:

- `MonthElapsedTest`: data odniesienia dla bieżącego, minionego i przyszłego miesiąca oraz roku, luty przestępny, niezależność od pory dnia.
- `MonthPaceCalculatorTest` i `month-pace-calculator.test.ts` z tymi samymi przypadkami:
  - wykluczenia z puli: dochód, rachunek, jednorazowa;
  - przykład F-01 (dzień 10 bez ostrzeżenia, dzień 9 z ostrzeżeniem);
  - granica dokładnie 10 pp;
  - pułapka zaokrąglenia 39,6% / 30%;
  - plan 0;
  - ujemna suma faktyczna;
  - miniony miesiąc.

### Integration Tests:

- `MonthPaceTest`: odczyt z `MonthToSpend` na kanonicznej fixturze (propozycja, zatwierdzony plan z kwotami bieżącymi, zarchiwizowana kategoria z operacją, stan `none`).
- `MonthPlanPageTest`: kontrakt propsa `month_pace` przez `assertInertia`:
  - bieżący miesiąc z wartościami kanonicznymi;
  - przyszły miesiąc z `null`;
  - miniony zatwierdzony z pełnym upływem;
  - wyprzedzenie z `is_ahead = true`;
  - pula bez planu z `percent = null`.

### Manual Testing Steps:

1. Zaloguj się w podglądzie jako `test@example.com` / `password` i otwórz „Plan miesiąca” dla bieżącego miesiąca.
2. Sprawdź pasek, znacznik i tekst tempa. W propozycji zmień kwotę pozycji bieżącej i obserwuj tempo, potem kwotę rachunku (tempo bez zmian).
3. Obniż plan pozycji bieżących tak, żeby tempo wyprzedziło upływ o ≥ 10 pp, i sprawdź kolor oraz podpis. Następnie wyzeruj plany bieżących i sprawdź komunikat.
4. Przełącz na miniony (zatwierdzony, jeśli jest) i przyszły miesiąc. Sprawdź wąski ekran.

## Performance Considerations

`MonthPace` czyta wyłącznie kategorie i kwoty już wczytane przez `MonthToSpend::forMonth`, więc liczba zapytań strony się nie zmienia.

## Migration Notes

Brak migracji, zapisu tempa i zmian snapshotu. S-07 (month-plan-notices) użyje `MonthPace::forMonth` dla bieżącego miesiąca i predykatu `is_ahead` do powiadomienia `warning`. Zgodnie z `.ai/rules/support.md` termin i próg są już w `CONTEXT.md`.

## References

- Research: `context/changes/month-spending-pace/research.md`
- Domena: `CONTEXT.md` (Month Pace, Month Elapsed, To Spend, One-off Category, Month Item Kind)
- F-01: `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:30-31,157`
- ADR: `.docs/adr/0033-month-plan-beside-annual-budget.md:9,11`
- PRD: `context/foundation/prd.md:116-117` (FR-007)
- Wzorzec daty odniesienia: `app/Support/YearElapsed.php:9-25`, `tests/Unit/YearElapsedTest.php`
- Wzorzec pary PHP/TS: `app/Support/ToSpendCalculator.php`, `resources/js/lib/to-spend-calculator.ts:9-14`
- Szew odczytu: `app/Support/MonthToSpend.php:32-90`
- Podgląd na żywo: `resources/js/pages/MonthPlan.vue:260-279`
- Znacznik: `resources/js/components/CategoryMeterRow.vue:95-116`
- Kolory: `.docs/design-system.md:27-30`, `.ai/rules/js.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Odczyt tempa na serwerze i wzór w parze PHP/TS

#### Automated

- [x] 1.1 `tests/Unit/MonthElapsedTest.php` i `tests/Unit/MonthPaceCalculatorTest.php` przechodzą
- [x] 1.2 `tests/Feature/MonthPaceTest.php`, `tests/Feature/MonthPlanPageTest.php` i `tests/Feature/MonthToSpendTest.php` przechodzą na PostgreSQL (obraz Sail na worktree, `AGENTS.md` „Test database”)
- [x] 1.3 `npm test` przechodzi, w tym `resources/js/lib/month-pace-calculator.test.ts`
- [x] 1.4 `vendor/bin/pint --dirty --format agent` przechodzi
- [x] 1.5 `npm run build`, potem `npm run types:check` i `npm run check` przechodzą
- [x] 1.6 `vendor/bin/sloppy ci` na hoście nie zgłasza nowych znalezisk

### Phase 2: Tempo na stronie „Plan miesiąca”

#### Automated

- [ ] 2.1 `tests/Feature/MonthPlanPageTest.php` i `tests/Feature/MonthPaceTest.php` przechodzą na PostgreSQL
- [ ] 2.2 `npm test` przechodzi
- [ ] 2.3 `npm run build`, potem `npm run types:check` i `npm run check` przechodzą
- [ ] 2.4 Pełna bramka CI (`composer ci:check` w obrazie Sail na worktree) przechodzi
- [ ] 2.5 `vendor/bin/sloppy ci` na hoście nie zgłasza nowych znalezisk

#### Manual

- [ ] 2.6 W podglądzie worktree (`AGENTS.md` „Preview from a worktree”) bieżący miesiąc pokazuje pod „do wydania” pasek tempa ze znacznikiem dni i tekst „Tempo X% · upływ dni d z n (Y%)”
- [ ] 2.7 W propozycji (albo w trybie „Zmień plan”) zmiana kwoty pozycji bieżącej przesuwa tempo na żywo; zmiana kwoty rachunku, jednorazowej albo dochodu go nie zmienia
- [ ] 2.8 Przy tempie wyprzedzającym upływ dni o ≥ 10 pp pasek i procent są bursztynowe i widać podpis ostrzeżenia; po podniesieniu planu bieżących poniżej progu kolor i podpis znikają
- [ ] 2.9 Wyzerowanie planów wszystkich pozycji bieżących pokazuje komunikat „Pozycje bieżące nie mają planu…” zamiast paska
- [ ] 2.10 Miniony zatwierdzony miesiąc pokazuje tempo z upływem „n z n”, a przyszły miesiąc nie pokazuje tempa
- [ ] 2.11 Na szerokości telefonu karta „do wydania” z tempem nie przewija się poziomo, a tempo > 100% nie wychodzi poza tor
