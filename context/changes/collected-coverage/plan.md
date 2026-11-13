# Pokrycie „zebranych” płynnością — plan implementacji

## Overview

„Zebrane” kategorii nieregularnych to odczyt planu: nie rusza sald i nic dziś nie sprawdza, czy te pieniądze fizycznie leżą na rachunkach. Ta zmiana dodaje odczyt **Collected Coverage** („pokrycie zebranych”): suma „zebranych” z wierszy planu bieżącego miesiąca wobec płynności dysponowalnej. Pulpit pokazuje sumę stale w podpisie pod „Płynność dysponowalna”, a gdy płynność jest niższa od sumy, dodaje ostrzeżenie „Zebrane bez pokrycia” w strefie „Wymaga uwagi”. Bez migracji, bez nowego rekordu, bez kopii kalkulatora w TypeScript.

## Current State Analysis

- „Zebrane” liczy `IrregularCollected` (łańcuch miesięcy, ADR-0073) i `CollectedCalculator` (`app/Support/CollectedCalculator.php:20-31`). Wiersz planu miesiąca pokazuje „zebrane X” jako `collectedAtEndMinorUnits(start, plan, actual)` (`app/Support/MonthPlanItemRow.php:94-99`), w bieżącym miesiącu stan na dziś razem ze stawką tego miesiąca (ADR-0073:17).
- Te same trzy kwoty wiersza daje `MonthToSpend::rowAmounts()` (`app/Support/MonthToSpend.php:112-124`); działa dla dowolnej pozycji budżetu, także spoza `categories` odczytu (plan 0, wykonanie 0).
- Pulpit buduje `MonthToSpend` tylko dla budżetu bieżącego roku w realizacji (`app/Http/Controllers/DashboardController.php:95-117`), ma już niezarchiwizowane pozycje wydatkowe (`$visibleExpenseCategories`, `:83-85`) i płynność dysponowalną (`SpendableLiquidity::forUser`, `:59`, prop `spendable_liquidity_minor_units`, `:130`).
- Powiadomienia składa `DashboardNotices::forDashboard` (`app/Support/DashboardNotices.php:65-249`). Kolejność dopisywania jest kontraktem listy: najpierw `exceeded`, potem `warning`; ostatni jest dziś `month_pace_ahead` (`:236-245`). Rodzaje `warning` to `month_plan_not_approved` i `month_pace_ahead` (`.ai/rules/support.md`, ADR-0032).
- Frontend: typ unii `DashboardNotice` (`resources/js/types/dashboard.ts:10-62`), prezentacja w `noticePresentation()` (`resources/js/lib/dashboard-notice-presentation.ts:34-102`, `NoticeLabelKey` `:5-11`, `NoticeValue` `:22-25`), jedyny renderer `resources/js/components/DashboardNotices.vue` (`isMonthNotice` `:78-92`, `noticeValue` `:116-130`, `noticeHref` z `satisfies never` `:159-205`, `noticeKey` `:207-213`). Etykieta jest składana dynamicznie jako `t.dashboard['notice_label_' + labelKey]` (`:98-100`), więc `UiTranslationKeysTest` nie złapie brakującego klucza.
- Panel liczb: `DashboardStatPanel.vue:82-87` pokazuje „Płynność dysponowalna” z podpisem `t.dashboard.liquidity_caption` („konta, bez lokat”, `lang/pl/ui.php:192`). „Zebrane” nie ma dziś nigdzie na pulpicie.
- Kitchen sink: `resources/js/pages/dev/dashboard-fixtures.ts` (`allNoticeKindsProps` `:450-515`, scenariusz `all-notice-kinds` `:585-589`); test fixtur zakłada 7 rodzajów (`dashboard-fixtures.test.ts:51-56`).
- Testy pulpitu: `tests/Feature/DashboardTest.php`; `test_dashboard_orders_notices_by_severity_then_kind` (`:1481-1530`) zakłada 7 powiadomień i stałe indeksy; kilka testów porównuje `dashboardNotices` z dokładną listą.

## Desired End State

- Słownik ma hasło **Collected Coverage**, a ADR-0090 regułę: suma, próg, które kategorie, kiedy odczyt istnieje.
- Dla budżetu bieżącego roku w realizacji, który ma co najmniej jedną niezarchiwizowaną pozycję wydatkową o rytmie „nieregularna” w bieżącym miesiącu, pulpit dostaje `collected_minor_units` i pokazuje pod płynnością „konta, bez lokat · w tym zebrane 600,00 zł”. Bez takiej pozycji prop ma `null` i podpis zostaje „konta, bez lokat”.
- Suma równa się co do grosza sumie `collected.end_minor_units` wierszy nieregularnych strony planu bieżącego miesiąca.
- Gdy płynność dysponowalna < suma, lista powiadomień kończy się kartą `collected_not_covered` (`warning`): etykieta „Zebrane bez pokrycia”, wartość „brakuje 100,00 zł · zebrane 600,00 zł”, klik prowadzi do planu miesiąca. Przy równości i przy nadwyżce karty nie ma.
- Przykład (decyzja użytkownika, 3 XI): Prąd 450 + 150 − 0 = 600, Ubezpieczenie 1 100 + 100 − 1 200 = 0, suma 600,00 zł; płynność 500,00 zł daje „brakuje 100,00 zł · zebrane 600,00 zł”; płynność 600,00 zł nie daje karty.

### Key Discoveries:

- Suma zgodna z wierszami z konstrukcji: `MonthToSpend::rowAmounts()` + `CollectedCalculator::collectedAtEndMinorUnits` to dokładnie wzór wiersza (`MonthPlanItemRow.php:94-99`), bez drugiej arytmetyki i bez nowych zapytań.
- Wzór karty z dwiema liczbami już istnieje: `month_pace_ahead` ma własny typ wartości `pace` i tekst `notice_value_pace` (`lang/pl/ui.php:214`); nowy rodzaj dodaje typ wartości w ten sam sposób (ADR-0050: nowy rodzaj to etykieta, nie nowy markup).
- `noticeKey()` wymaga `id` albo `month`; nowy rodzaj niesie `month` i wchodzi do `isMonthNotice`, więc dostaje ikonę kalendarza i przejście do planu miesiąca bez nowej gałęzi w `noticeHref`.
- Ostrzeżenie „dobieram” już porównuje kwotę planu z płynnością dysponowalną (`lang/pl/ui.php:509`), więc ta strona porównania ma precedens.

## What We're NOT Doing

- Porównanie z łącznymi środkami (z lokatami) i dwa progi: odrzucone przez użytkownika 2026-11-13.
- Odczyt „zebrane do końca poprzedniego miesiąca” (bez bieżącej stawki): odrzucony przez użytkownika 2026-11-13; suma ma się zgadzać z wierszami planu.
- Blokada, przelew, rezerwa ani żaden zapis: to odczyt i ostrzeżenie, jak „do wydania”.
- Kategorie zarchiwizowane i kategorie nieregularne bez pozycji w budżecie bieżącego roku nie wchodzą do sumy.
- Odczyt bez budżetu bieżącego roku w realizacji (szkic, brak budżetu).
- Podpis i karta poza pulpitem (Rachunki, Plan miesiąca, Budżet).
- Kopia odczytu w TypeScript: nic nie wymaga podglądu przed zapisem (`.ai/rules/month-calculators.md`).
- Ostrzeżenie przy wpisie operacji i w Imporcie.

## Implementation Approach

Dokumenty przed kodem (twarda reguła AGENTS.md), potem test-first: czysta arytmetyka niedoboru, odczyt z istniejącego `MonthToSpend`, karta, podpis. Kontroler liczy odczyt raz i przekazuje go do `DashboardNotices` oraz do propa, żeby karta i podpis nie mogły się rozjechać.

## Phase 1: Słownik i ADR-0090

### Overview

Termin, próg i zakres kategorii zapisane przed kodem; dokumenty, które wymieniają rodzaje `warning`, dostają dopisek.

### Changes Required:

#### 1. Słownik

**File**: `context/foundation/glossary.md`

**Intent**: Dodać hasło **Collected Coverage** po „Opening Collected” i zdanie odsyłające w „Collected” oraz „Spendable Liquidity”, żeby reguła `support.md` (termin i próg w słowniku przed kodem) była spełniona.

**Contract**: Hasło mówi: odczyt, nigdy niezapisywany; suma = suma Collected na koniec bieżącego miesiąca (stan na dziś, wzór wiersza planu, ADR-0073) po niezarchiwizowanych pozycjach wydatkowych budżetu bieżącego roku w realizacji, których rytm w bieżącym miesiącu to Irregular; niedobór = max(0, suma − Spendable Liquidity); powiadomienie `warning` gdy Spendable Liquidity < suma; brak odczytu bez takiej pozycji albo bez budżetu w realizacji; nie zmienia sald, Execution ani To Spend. Polish UI copy: „pokrycie zebranych”, „Zebrane bez pokrycia”, „w tym zebrane”. _Avoid_: Reserve, Earmark, Fund, funded, backed, Envelope balance, Total funds (dla tej strony porównania), Available Limit.

#### 2. ADR

**File**: `context/foundation/adr/0090-collected-coverage.md`

**Intent**: Zapisać decyzję i odrzucone warianty w stylu ADR-0082 (akapit reguły, przykłady, relacja do innych ADR, „We rejected”).

**Contract**: Reguła jak w słowniku; przykłady z „Desired End State”; relacja do ADR-0073 (ten sam wzór wiersza), ADR-0032 (waga `warning`: nic nie jest jeszcze przekroczone, rachunek nie przyszedł), ADR-0050 (etykieta i format wartości), ADR-0045 (panel liczb). Odrzucone: łączne środki, dwa progi, zebrane bez bieżącej stawki, sama karta bez podpisu, waga `exceeded`.

#### 3. Dopiski w istniejących dokumentach

**File**: `context/foundation/adr/0032-dashboard-notice-severity.md`, `context/foundation/adr/0050-dashboard-notice-labels-and-budget-block.md`, `.ai/rules/support.md`, `context/foundation/design-system.md`

**Intent**: Każde miejsce, które wylicza rodzaje `warning` albo etykiety kart, ma znać `collected_not_covered`.

**Contract**: ADR-0032 i ADR-0050: jedno zdanie „Amended by ADR-0090” (ADR-0050 także wiersz tabeli etykiet: `collected_not_covered` | Zebrane bez pokrycia | month | „brakuje 100,00 zł · zebrane 600,00 zł”). `support.md`: lista rodzajów `warning` z trzecim rodzajem; po edycji `php artisan boost:index-rules`. `design-system.md` (sekcja „Alerty”): trzeci rodzaj `warning` i podpis „w tym zebrane” w panelu liczb.

### Success Criteria:

#### Automated Verification:

- Hasło i ADR istnieją: `grep -n "Collected Coverage" context/foundation/glossary.md context/foundation/adr/0090-collected-coverage.md`
- Każdy dokument z listą rodzajów `warning` zna nowy rodzaj: `grep -ln "collected_not_covered" .ai/rules/support.md context/foundation/design-system.md context/foundation/adr/0032-dashboard-notice-severity.md context/foundation/adr/0050-dashboard-notice-labels-and-budget-block.md` zwraca cztery pliki
- Indeks reguł bez różnic po `php artisan boost:index-rules`: `git diff --exit-code .ai/rules/index.md`

#### Manual Verification:

- Użytkownik czyta hasło słownika i ADR-0090 i potwierdza regułę (suma, próg, które kategorie)

**Implementation Note**: Po tej fazie zatrzymaj się na potwierdzenie użytkownika, że reguła w ADR-0090 jest taka, jak chciał, zanim powstanie kod.

---

## Phase 2: Odczyt Collected Coverage

### Overview

Czerwony test arytmetyki niedoboru, potem odczyt z `MonthToSpend`, z testem równości z wierszami planu miesiąca.

### Changes Required:

#### 1. Test jednostkowy (najpierw, czerwony)

**File**: `tests/Unit/CollectedCoverageTest.php`

**Intent**: Ustalić próg, zanim powstanie klasa: niedobór tylko gdy płynność jest niższa od sumy.

**Contract**: Przypadki `shortfallMinorUnits(collected, liquidity)`: (60_000, 50_000) → 10_000; (60_000, 60_000) → 0; (60_000, 70_000) → 0; (0, −5_000) → 0 (ujemna płynność bez zebranych nie daje niedoboru); (60_000, −5_000) → 65_000.

#### 2. Odczyt

**File**: `app/Support/CollectedCoverage.php`

**Intent**: Jedno miejsce, które liczy sumę „zebranych” i niedobór dla pulpitu, z tych samych kwot co wiersz planu.

**Contract**: `final class CollectedCoverage` z `shortfallMinorUnits(int $collectedMinorUnits, int $spendableLiquidityMinorUnits): int` (= `max(0, collected − liquidity)`) oraz `forMonth(MonthToSpend $month, Collection $expenseLines, int $spendableLiquidityMinorUnits): ?array` zwracającym `array{collected_minor_units: int, shortfall_minor_units: int}` albo `null`. `null`, gdy `$month->reading->state === 'none'` albo żadna z `$expenseLines` nie ma w tym miesiącu rytmu Irregular (`$month->rhythmOf`). `$expenseLines` to niezarchiwizowane pozycje wydatkowe budżetu; suma = Σ `CollectedCalculator::collectedAtEndMinorUnits(start, plan, actual)` z `$month->rowAmounts($line)`. Żadnych zapytań w pętli (Sloppy SL1xx).

#### 3. Test równości z planem miesiąca

**File**: `tests/Feature/CollectedCoverageTest.php`

**Intent**: Dowieść, że suma równa się temu, co użytkownik widzi w wierszach planu, i że zakres kategorii jest taki jak w ADR-0090.

**Contract**: Scenariusz z decyzji (Prąd 45_000 start, pozycja planu 15_000, bez rachunku; Ubezpieczenie 110_000 start, pozycja 10_000, rachunek 120_000) → `collected_minor_units === 60_000`, równe sumie `collected.end_minor_units` wierszy z propa strony `month-plan` dla bieżącego miesiąca. Dalsze przypadki: zarchiwizowana pozycja nieregularna nie wchodzi do sumy; pozycja nieregularna bez pozycji w zatwierdzonym planie wchodzi z samym „zebranym na początek”; budżet bez pozycji nieregularnej → `null`. Wzór danych: `tests/Feature/DashboardTest.php:1620-1668` (`irregular()`, `rhythms()->update`, `approvedMonthPlan`, `recordOperation`).

### Success Criteria:

#### Automated Verification:

- Test jednostkowy przechodzi z czerwonego na zielony: `php artisan test --compact tests/Unit/CollectedCoverageTest.php`
- Test równości z planem miesiąca przechodzi: `php artisan test --compact tests/Feature/CollectedCoverageTest.php`
- Te same testy przechodzą na PostgreSQL (baza `testing_collected_coverage`, polecenie worktree z AGENTS.md)
- Pint bez zmian na nowych plikach: `vendor/bin/pint --format agent app/Support/CollectedCoverage.php tests/Unit/CollectedCoverageTest.php tests/Feature/CollectedCoverageTest.php`

---

## Phase 3: Karta ostrzeżenia „Zebrane bez pokrycia”

### Overview

Nowy rodzaj powiadomienia po obu stronach: serwer, typ, prezentacja, etykieta, fixtury, testy.

### Changes Required:

#### 1. Serwer

**File**: `app/Support/DashboardNotices.php`, `app/Http/Controllers/DashboardController.php`, `app/Enums/DashboardNoticeSeverity.php`

**Intent**: Kontroler liczy `CollectedCoverage::forMonth` raz (gdy istnieje `$monthToSpendReading`) i przekazuje wynik do `forDashboard`; ta dopisuje kartę na końcu listy, po `month_pace_ahead`.

**Contract**: `forDashboard` dostaje szósty parametr `?array $collectedCoverage`. Gdy `shortfall_minor_units > 0`, dopisuje `{kind: 'collected_not_covered', severity: 'warning', month: int, shortfall_minor_units: int, collected_minor_units: int}` jako ostatni element. Docblock kształtu tablicy i komentarz enumu (`DashboardNoticeSeverity.php:6-8`) wymieniają nowy rodzaj.

#### 2. Typ i prezentacja

**File**: `resources/js/types/dashboard.ts`, `resources/js/lib/dashboard-notice-presentation.ts`, `resources/js/components/DashboardNotices.vue`

**Intent**: Karta w stałym kształcie (ADR-0050): etykieta, tożsamość „miesiąc”, wartość z dwiema kwotami, kolor z wagi.

**Contract**: Nowy człon unii `DashboardNotice` o polach z punktu 1. `NoticeLabelKey` dostaje `'collected_not_covered'`, `NoticeValue` typ `{ type: 'collected_shortfall'; shortfallMinorUnits: number; collectedMinorUnits: number }`, `noticePresentation()` nowy `case` z tożsamością `{type: 'month'}`. W komponencie: rodzaj dopisany do `isMonthNotice` (przejście do planu miesiąca, ikona kalendarza), `noticeValue()` formatuje nowy typ przez `t.dashboard.notice_value_collected_shortfall` i `formatMoney`. Bez nowego markupu i bez nowych klas.

#### 3. Teksty

**File**: `lang/pl/ui.php`

**Intent**: Etykieta i format wartości karty w grupie `dashboard`.

**Contract**: `'notice_label_collected_not_covered' => 'Zebrane bez pokrycia'`, `'notice_value_collected_shortfall' => 'brakuje :shortfall · zebrane :collected'`.

#### 4. Kitchen sink

**File**: `resources/js/pages/dev/dashboard-fixtures.ts`

**Intent**: Stan karty widoczny w `/dev/ui/dashboard` (reguła design systemu: każdy stan w kitchen sinku).

**Contract**: `allNoticeKindsProps` dostaje dziewiątą kartę, ósmy rodzaj (`month: 9`, `shortfall_minor_units: 10_000`, `collected_minor_units: 60_000`) na końcu listy.

#### 5. Testy

**File**: `tests/Feature/DashboardTest.php`, `resources/js/lib/dashboard-notice-presentation.test.ts`, `resources/js/pages/dev/dashboard-fixtures.test.ts`, `tests/Unit/UiTranslationKeysTest.php`

**Intent**: Próg, ładunek, kolejność i etykieta pod testem, zanim powstanie kod karty.

**Contract**: Nowe testy pulpitu: `test_dashboard_notices_collected_not_covered_below_spendable_liquidity` (płynność 50_000 przy sumie 60_000 → dokładny ładunek; 60_000 → brak karty), `test_dashboard_has_no_collected_notice_without_an_irregular_line`. `test_dashboard_orders_notices_by_severity_then_kind` dostaje pozycję nieregularną z niedoborem: 8 powiadomień, nowy rodzaj na indeksie 7 z wagą `warning`. Test prezentacji: nowy `it` dla rodzaju. Test fixtur: 8 rodzajów. `UiTranslationKeysTest`: asercja, że oba nowe klucze istnieją w `ui.php` (klucz etykiety jest dynamiczny, więc skan go nie widzi).

### Success Criteria:

#### Automated Verification:

- Testy pulpitu przechodzą: `php artisan test --compact tests/Feature/DashboardTest.php tests/Unit/UiTranslationKeysTest.php`
- Testy pulpitu przechodzą na PostgreSQL (baza `testing_collected_coverage`)
- Testy JS przechodzą: `npm run test -- dashboard-notice-presentation dashboard-fixtures`
- Typy przechodzą (wyczerpanie `switch` i `satisfies never`): `npm run types:check`
- Strażnik literałów UI przechodzi: `npm run check:ui`

#### Manual Verification:

- W `/dev/ui/dashboard`, scenariusz `all-notice-kinds`: karta „Zebrane bez pokrycia” stoi ostatnia, kwota w kolorze ostrzeżenia, tekst „brakuje 100,00 zł · zebrane 600,00 zł” mieści się na telefonie i w trybie ciemnym

---

## Phase 4: Podpis w panelu liczb i bramka

### Overview

Suma „zebranych” stale widoczna pod płynnością dysponowalną; domknięcie zmiany pełną bramką.

### Changes Required:

#### 1. Prop po obu stronach

**File**: `app/Http/Controllers/DashboardController.php`, `resources/js/types/dashboard.ts`, `resources/js/pages/Dashboard.vue`, `resources/js/components/DashboardStatPanel.vue`

**Intent**: Pulpit dostaje sumę z tego samego odczytu co karta i pokazuje ją w podpisie metryki płynności.

**Contract**: Prop `collected_minor_units: int|null` (`null` bez odczytu) w `Inertia::render('Dashboard')` i w `DashboardPageProps`; `DashboardStatPanel` dostaje `collectedMinorUnits: number | null`. Podpis metryki `liquidity`: przy `null` bez zmian („konta, bez lokat”), inaczej „konta, bez lokat · w tym zebrane 600,00 zł” (także dla 0, jak „zebrane startowe” w ADR-0076). Zmiana propa po obu stronach w jednym commicie z testem `assertInertia` (`.ai/rules/inertia-props.md`).

#### 2. Tekst

**File**: `lang/pl/ui.php`

**Intent**: Fragment podpisu w grupie `dashboard`.

**Contract**: `'liquidity_collected_caption' => 'w tym zebrane :amount'`, łączony z `liquidity_caption` separatorem „ · ”.

#### 3. Kitchen sink

**File**: `resources/js/pages/dev/dashboard-fixtures.ts`, `resources/js/pages/dev/dashboard-fixtures.test.ts`

**Intent**: Oba stany podpisu w `/dev/ui/dashboard`.

**Contract**: `defaultProps` i `allNoticeKindsProps` z `collected_minor_units: 60_000`; co najmniej jeden istniejący scenariusz z `null`. Test fixtur sprawdza, że oba stany występują.

#### 4. Test propa

**File**: `tests/Feature/DashboardTest.php`

**Intent**: Prop pod `assertInertia` w trzech stanach.

**Contract**: `test_dashboard_passes_collected_total_for_irregular_lines`: 60_000 w scenariuszu z fazy 2; `null` bez pozycji nieregularnej; `null` dla budżetu w szkicu.

#### 5. Changelog

**File**: `CHANGELOG.md`

**Intent**: Wpis w `[Unreleased]` → `### Added`.

**Contract**: Jedno zdanie po angielsku w stylu pliku, z `collected-coverage` i ADR-0090.

### Success Criteria:

#### Automated Verification:

- Pełna bramka na PostgreSQL: `composer ci:check` (polecenie worktree z AGENTS.md, baza `testing_collected_coverage`)
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci` (w obrazie Sail)
- Strażnik literałów UI przechodzi: `npm run check:ui`
- E2E przechodzą: `npx playwright test` (własna baza `e2e_collected_coverage` i port, `parallel-changes.md`)

#### Manual Verification:

- Na pulpicie z kategorią nieregularną podpis brzmi „konta, bez lokat · w tym zebrane X” i X równa się sumie „zebrane” z wierszy planu miesiąca
- Na pulpicie bez kategorii nieregularnej podpis jest bez zmian

---

## Testing Strategy

### Unit Tests:

- `CollectedCoverage::shortfallMinorUnits`: próg (niżej, równo, wyżej), ujemna płynność z zebranymi i bez.
- `noticePresentation()` dla nowego rodzaju; fixtury z ośmioma rodzajami i dwoma stanami podpisu.

### Integration Tests:

- Suma równa sumie wierszy strony planu miesiąca; zakres kategorii (archiwum, pozycja bez pozycji planu, brak nieregularnych).
- Pulpit: ładunek karty, brak karty przy równości, kolejność (nowy rodzaj ostatni), prop `collected_minor_units` w trzech stanach.
- Wszystko, co dotyka dat i łańcucha „zebranych”, także na PostgreSQL.

### Manual Testing Steps:

1. Oznacz kategorię jako nieregularną z „zebranym startowym” wyższym niż saldo rachunków; pulpit pokazuje kartę i podpis.
2. Dodaj przychód podnoszący płynność ponad sumę; karta znika, podpis zostaje.
3. Kliknij kartę; otwiera się plan bieżącego miesiąca, a suma „zebrane” wierszy równa się kwocie z karty.

## Performance Considerations

Odczyt używa już załadowanego `MonthToSpend` i kolekcji pozycji wydatkowych z kontrolera; nie dodaje zapytań.

## References

- Decyzje użytkownika z 2026-11-13: porównanie z płynnością dysponowalną, waga `warning`, suma jak w wierszach planu, karta i podpis.
- Wzór rodzaju `warning` z dwiema liczbami: `app/Support/DashboardNotices.php:236-245`, `resources/js/lib/dashboard-notice-presentation.ts:89-100`
- Wzór wiersza „zebrane X”: `app/Support/MonthPlanItemRow.php:94-99`, ADR-0073:17
- Reguły: `.ai/rules/support.md`, `.ai/rules/inertia-props.md`, `.ai/rules/month-calculators.md`, `context/foundation/agents/parallel-changes.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Słownik i ADR-0090

#### Automated

- [x] 1.1 Hasło i ADR istnieją (grep „Collected Coverage”) — f58eff0
- [x] 1.2 Każdy dokument z listą rodzajów `warning` zna nowy rodzaj — f58eff0
- [x] 1.3 Indeks reguł bez różnic po `boost:index-rules` — f58eff0

#### Manual

- [x] 1.4 Użytkownik potwierdza regułę w słowniku i ADR-0090 — f58eff0

### Phase 2: Odczyt Collected Coverage

#### Automated

- [x] 2.1 Test jednostkowy przechodzi z czerwonego na zielony
- [x] 2.2 Test równości z planem miesiąca przechodzi
- [x] 2.3 Te same testy przechodzą na PostgreSQL
- [x] 2.4 Pint bez zmian na nowych plikach

### Phase 3: Karta ostrzeżenia „Zebrane bez pokrycia”

#### Automated

- [ ] 3.1 Testy pulpitu przechodzą
- [ ] 3.2 Testy pulpitu przechodzą na PostgreSQL
- [ ] 3.3 Testy JS przechodzą
- [ ] 3.4 Typy przechodzą
- [ ] 3.5 Strażnik literałów UI przechodzi

#### Manual

- [ ] 3.6 Karta w kitchen sinku: ostatnia, kolor ostrzeżenia, tekst mieści się na telefonie i w trybie ciemnym

### Phase 4: Podpis w panelu liczb i bramka

#### Automated

- [ ] 4.1 Pełna bramka na PostgreSQL
- [ ] 4.2 Sloppy bez nowych znalezisk
- [ ] 4.3 Strażnik literałów UI przechodzi
- [ ] 4.4 E2E przechodzą

#### Manual

- [ ] 4.5 Podpis z kategorią nieregularną równa się sumie wierszy planu miesiąca
- [ ] 4.6 Podpis bez kategorii nieregularnej jest bez zmian
