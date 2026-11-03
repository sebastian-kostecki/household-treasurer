# S-09: Podsumowanie miesiąca — plan implementacji

## Overview

Miniony miesiąc z zatwierdzonym planem dostaje na stronie „Plan miesiąca” kartę „Podsumowanie miesiąca”. Karta pokazuje:

- sumy planu zatwierdzonego, planu końcowego i wykonania dla dochodów i wydatków wraz z odchyleniem;
- odkładanie zamierzone (zatwierdzone i końcowe) obok realnego (Wynik) i różnicę między nimi;
- sumę „dobieram” od stycznia do tego miesiąca.

Plan zamyka FR-009 i drugą połowę FR-013 („minione miesiące z zatwierdzonym planem pokazują podsumowanie i końcowe »do wydania«”), a z nimi US-01.

PRD: FR-009, FR-013 (`context/foundation/prd.md`). Domena: `CONTEXT.md` (Month Summary, Month Result, Set-aside, Current Month Plan). Research: `context/changes/month-summary/research.md`.

## Current State Analysis

**Gałąź.** Kod planu miesiąca (S-01…S-04, S-06) jest tylko na `development` (@61e8faa). Ten worktree (`worktree-month-summary-research`) wyszedł z `main` i zawiera tylko research. Implementację prowadzi się na gałęzi z `development`, na którą trafia folder `context/changes/month-summary/` (cherry-pick commitów z researchem i planem). Wszystkie kotwice `file:line` poniżej dotyczą `development`.

**Co już jest:**

- `MonthToSpend::forMonth` (`app/Support/MonthToSpend.php:32-49`) składa odczyt planu (`reading`), wykonanie na kategorię (`actuals`), kategorie wierszy i `MonthPlanProposal`. Kategorie wierszy to suma pozycji planu i kategorii z operacjami w miesiącu (`:38-41`).
- `MonthPlanReading` (`app/Support/MonthPlanReading.php:15-21`) niesie kwoty bieżące (`amounts`), kwoty zatwierdzone (`approvedAmounts`) i **tylko bieżące** odkładanie (`setAsideMinorUnits`). Kolumna `approved_set_aside_minor_units` jest zapisywana (`ApproveMonthPlan`), ale nigdzie nieodczytywana.
- `MonthPlanProposal::forBudget` ładuje wszystkie `monthPlans.items` budżetu (`app/Support/MonthPlanProposal.php:41`). `reading($m)` dla minionego miesiąca bez wiersza zwraca `none` z odkładaniem 0, a z wierszem zwraca `approved` (`:93-111`). Odczyt `reading(1..N)` dla minionego N nie wykonuje więc zapytań i nie liczy propozycji.
- `BudgetMonthResult::forViewedMonth` (`app/Support/BudgetMonthResult.php:18-36`) daje przychody, wydatki i Wynik miesiąca. To kanoniczna definicja realnego odkładania (`CONTEXT.md`, Month Result: „At the end of a month it is the real set-aside shown in Month Summary”).
- `MonthPlanController::monthPlanPayload` (`app/Http/Controllers/MonthPlanController.php:143-203`) buduje prop `monthPlan`. Miniony zatwierdzony miesiąc jest tam tylko do odczytu (`can_change=false`).
- `MonthPlan.vue` ma kartę „Do wydania”, kartę odkładania (dla minionego miesiąca tylko tekst „Odkładam X”) i tabele pozycji. Wiersz pozycji pokazuje „Zatwierdzono: X”, gdy kwota zatwierdzona różni się od końcowej (`resources/js/components/MonthPlanItemRow.vue:162-168`).
- `BudgetMonthStrip.vue:64-117` linkuje „Plan miesiąca” do `month-plan?month=N`, gdy miesiąc ma plan. Komponent ma już prop `viewedMonthTiming`.

**Czego brakuje:** zatwierdzonego odkładania w odczycie, klasy liczącej podsumowanie, propa, karty i tekstów UI.

**Rozstrzygnięte wcześniej (nie wracamy do nich):**

- Odczyt na żywo, bez zamrażania: późna operacja zmienia podsumowanie (`prd.md`, „Rozliczenie końca miesiąca”).
- Miniony miesiąc bez planu pokazuje Wynik i znacznik braku planu, bez „Pozostało w miesiącu” (`context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:72`).

## Desired End State

Na stronie „Plan miesiąca” dla minionego miesiąca z zatwierdzonym planem, między kartą „Do wydania (końcowe)” a tabelami pozycji, jest karta „Podsumowanie miesiąca” z trzema blokami:

1. **Tabela sum.** Wiersze Dochody i Wydatki, kolumny Zatwierdzony | Końcowy | Faktycznie | Odchylenie. Odchylenie = Faktycznie − Końcowy, liczone ze zwykłych sum. Faktycznie dochody − Faktycznie wydatki = Wynik.
2. **Odkładanie.** Zatwierdzone, końcowe, faktyczne (Wynik) i różnica = Wynik − końcowe. Przykład: zatwierdzone +1000, końcowe +500, Wynik +700 → różnica +200. Przykład z dobieraniem: końcowe −400, Wynik −100 → +300.
3. **Dobrane od początku roku.** Suma |końcowego odkładania| po zatwierdzonych planach z ujemnym odkładaniem, od stycznia do podsumowywanego miesiąca włącznie. Przykład dla lutego: styczeń zatwierdzony −500 i zmieniony na −300, luty +1000, marzec −200 → 300.

Bieżący, przyszły i miniony miesiąc bez zatwierdzonego planu nie mają karty (`monthPlan.summary === null`). W pasku miesiąca na stronie Budżet link dla minionego zatwierdzonego miesiąca ma tekst „Podsumowanie miesiąca”.

Weryfikacja: `MonthSummaryTest` (także na PostgreSQL), asercje `assertInertia` w `MonthPlanPageTest`, `composer ci:check` i ręczne obejrzenie strony.

### Key Discoveries:

- `MonthPlanReading` ma domyślny parametr `approvedAmounts = []` (`MonthPlanReading.php:20`). Nowe pole dokłada się na końcu z domyślną wartością null, więc istniejące wywołania `new MonthPlanReading(...)` się nie zmieniają.
- Kategorie wierszy obejmują wszystkie kategorie z operacjami (`MonthToSpend.php:38-41`), a `financial_operations.category_id` jest NOT NULL. Kolumnę „Faktycznie” i Wynik bierzemy jednak z `BudgetMonthResult`, żeby tabela zgadzała się z Wynikiem z konstrukcji, a nie z założenia o zgodności typu operacji z typem kategorii.
- `BudgetMonthResult` używa `whereYear`/`whereMonth`, czyli funkcji dat. Według `AGENTS.md` test trzeba więc uruchomić także na PostgreSQL.
- Testy w repo to klasy PHPUnit `test_*` z `RefreshDatabase` i `travelTo` (np. `tests/Feature/MonthToSpendTest.php:17-29`). Nowy test idzie tą samą drogą.
- `UiTranslations.month_plan` jest typowane jako `Record<string, string>` (`resources/js/types/shell.ts:41`), więc nowe klucze `lang/pl/ui.php` nie wymagają zmian w TS.
- Równoległa gałąź `worktree-month-plans-vs-year` (S-10) zmienia `MonthPlanController::itemRow`, `MonthPlan.vue`, `ToSpendCalculator` i `MonthPlanPageTest`. Zmiany są addytywne. Podsumowanie dotyka `monthPlanPayload`, a nie `itemRow`, więc konflikt powinien ograniczyć się do importów i sąsiednich linii.

## What We're NOT Doing

- **Bez snapshotu rodzaju pozycji.** Podsumowanie korzysta z bieżącego `month_item_kind` kategorii (wpływa na odchylenia wierszy i „do wydania”), tak jak dziś. Zmiana rodzaju z innej strony zmienia odczyt minionego miesiąca.
- **Bez rozróżniania „zatwierdzono 0” od „dołączono później”.** Kategoria dołączona po zatwierdzeniu ma zatwierdzone 0 i tak wchodzi do sumy zatwierdzonej (zaakceptowane w `context/archive/2026-11-02-approved-plan-changes/plan-brief.md:63`).
- **Bez podsumowania dla bieżącego miesiąca** ani dla miesięcy bez zatwierdzonego planu.
- **Bez przywracania „Pozostało w miesiącu”** dla minionego miesiąca bez planu.
- **Bez zamykania, zamrażania i przenoszenia miesiąca.**
- **Bez osobnej strony, trasy ani wpisu w nawigacji.**
- **Bez kopii logiki w TypeScript.** Podsumowanie jest tylko do odczytu i liczone na serwerze.
- **Bez licznika pozycji ponad plan i bez sumy zatwierdzony − końcowy** w karcie. Zmiany planu widać w kolumnach Zatwierdzony i Końcowy oraz per wiersz.
- **Bez „dobieram” z przyszłych miesięcy** w sumie rocznej.
- **Bez porządkowania nieużywanych propsów Month Remaining** strony Budżet (należy do zmiany po S-07/S-08).

## Implementation Approach

Jedna czysta klasa odczytu `App\Support\MonthSummary` w konwencji `app/Support/*`. Budujemy ją z już istniejącego `MonthToSpend` (ten sam odczyt, kategorie i propozycja, które strona i tak ładuje) oraz z `BudgetMonthResult` (jedno zapytanie agregujące). Kontroler decyduje o warunku „miniony” (zna `timing`), a klasa o warunku „zatwierdzony”. Wynik trafia do propa `monthPlan.summary`, zgodnie z regułą `AGENTS.md`: nowe odczyty miesiąca liczy serwer i przekazuje jako propsy. Nie ma powodu na kopię w TS, bo nic nie reaguje przed wysłaniem formularza.

## Faza 1: Odczyt podsumowania na serwerze

### Overview

Odczyt zatwierdzonego odkładania i klasa `MonthSummary` z czterema ustalonymi definicjami, przetestowana bez UI.

### Changes Required:

#### 1. Zatwierdzone odkładanie w odczycie planu

**File**: `app/Support/MonthPlanReading.php`, `app/Support/MonthPlanProposal.php`

**Intent**: Wystawić zapisane `approved_set_aside_minor_units`, żeby podsumowanie mogło porównać odkładanie zatwierdzone z końcowym.

**Contract**: `MonthPlanReading` dostaje na końcu konstruktora `public readonly ?int $approvedSetAsideMinorUnits = null`: liczba ze znakiem dla stanu `approved`, null dla `proposal` i `none`. Gałąź `approved` w `MonthPlanProposal::computePlan` przekazuje `$plan->approved_set_aside_minor_units`. Pozostałe wywołania się nie zmieniają.

#### 2. Klasa podsumowania

**File**: `app/Support/MonthSummary.php` (nowy, `php artisan make:class`)

**Intent**: Policzyć podsumowanie minionego miesiąca z zatwierdzonym planem według definicji ustalonych w planowaniu, w jednym miejscu.

**Contract**: `final class MonthSummary`:

- `public static function forMonth(MonthToSpend $toSpend, Budget $budget, int $month): ?self` zwraca null, gdy `$toSpend->reading->state !== 'approved'`. Warunek „miniony” sprawdza wywołujący.
- **Sumy:** dla każdego typu kategorii (dochód, wydatek) liczone są:
  - `approved` = suma `reading->approvedAmounts` kategorii tego typu (typ z `$toSpend->categories`);
  - `final` = suma `reading->amounts`;
  - `actual` = `income_execution_minor_units` / `expense_execution_minor_units` z `BudgetMonthResult::forViewedMonth($budget, $month)`;
  - `deviation` = `actual − final`.

  Niezapłacony rachunek (plan 300, wykonanie 0) daje w sumie wydatków −300, choć w swoim wierszu nie ma odchylenia (null).
- **Odkładanie:**
  - `approved` = `reading->approvedSetAsideMinorUnits`;
  - `final` = `reading->setAsideMinorUnits`;
  - `real` = `month_result_minor_units`;
  - `difference` = `real − final`.
- **Dobrane w roku:** `yearDraw` = Σ po `m` od 1 do `$month` z `max(0, −$toSpend->proposal->reading($m)->setAsideMinorUnits)`, tylko dla odczytów w stanie `approved`. Wartość nieujemna, kwota bezwzględna.
- `toArray()` zwraca kształt propa:

```php
/** @return array{
 *   income: array{approved_minor_units: int, final_minor_units: int, actual_minor_units: int, deviation_minor_units: int},
 *   expense: array{approved_minor_units: int, final_minor_units: int, actual_minor_units: int, deviation_minor_units: int},
 *   set_aside: array{approved_minor_units: int, final_minor_units: int, real_minor_units: int, difference_minor_units: int},
 *   year_draw_minor_units: int,
 * } */
```

#### 3. Testy klasy

**File**: `tests/Feature/MonthSummaryTest.php` (nowy, klasa PHPUnit jak `MonthToSpendTest`, `travelTo('2026-06-15')`)

**Intent**: Zablokować cztery definicje i przypadki brzegowe ustalone w planowaniu.

**Contract**: testy (nazwy orientacyjne):

- `test_totals_compare_approved_final_and_actual`:
  - pozycja zmieniona po zatwierdzeniu (zatwierdzony ≠ końcowy);
  - kategoria dołączona później (zatwierdzone 0);
  - kategoria z wydatkiem poza planem (końcowy 0, wliczona w faktycznie);
  - niezapłacony rachunek liczy się w odchyleniu wydatków jako −plan;
  - `income.actual − expense.actual === set_aside.real`.
- `test_set_aside_difference_is_month_result_minus_final`: zatwierdzone 100_000, końcowe 50_000, Wynik 70_000 → różnica 20_000.
- `test_draw_difference_uses_the_signed_final_amount`: końcowe −40_000, Wynik −10_000 → różnica 30_000.
- `test_year_draw_sums_final_draws_from_january_through_the_month`:
  - styczeń zatwierdzony −50_000, końcowy −30_000;
  - luty +100_000;
  - marzec −20_000;
  - kwiecień bez planu.

  Podsumowanie lutego → 30_000, marca → 50_000.
- `test_no_summary_without_an_approved_plan`: miniony miesiąc bez planu i bieżąca propozycja → null.

#### 4. Definicja w słowniku domeny

**File**: `CONTEXT.md` (wpis **Month Summary**)

**Intent**: Zapisać ustalone definicje, żeby kolejne zmiany ich nie odkrywały na nowo.

**Contract**: Wpis Month Summary dostaje doprecyzowanie:

- czyta się go tylko dla minionych miesięcy z Approved Month Plan;
- deviation of totals = Execution − Current Month Plan (zwykłe sumy);
- różnica odkładania = Month Result − Current Set-aside;
- Draw over the year = suma |Current Set-aside| ujemnych z Approved Month Plans od stycznia do podsumowywanego miesiąca.

Polska kopia: „Podsumowanie miesiąca”, „Dobrane od początku roku”.

### Success Criteria:

#### Automated Verification:

- Testy podsumowania przechodzą na SQLite: `php artisan test --compact tests/Feature/MonthSummaryTest.php`
- Testy podsumowania i odczytu planu przechodzą na PostgreSQL (obraz Sail z `AGENTS.md`): `... artisan test --compact tests/Feature/MonthSummaryTest.php tests/Feature/MonthToSpendTest.php tests/Feature/MonthPlanProposalTest.php`
- Pint bez zmian: `vendor/bin/pint --dirty --format agent`
- Analiza statyczna przechodzi (Larastan w `composer ci:check`)

#### Manual Verification:

- Wpis Month Summary w `CONTEXT.md` czyta się jednoznacznie i zgadza się z przykładami z tego planu

**Implementation Note**: Po przejściu automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Faza 2: Prop i strona

### Overview

Podsumowanie trafia do propa `monthPlan.summary`. Strona „Plan miesiąca” pokazuje je w nowej karcie, karta „do wydania” minionego zatwierdzonego miesiąca dostaje etykietę „Do wydania (końcowe)”, a pasek miesiąca na stronie Budżet dostaje tekst linku „Podsumowanie miesiąca”.

### Changes Required:

#### 1. Payload kontrolera

**File**: `app/Http/Controllers/MonthPlanController.php` (`monthPlanPayload`)

**Intent**: Wysłać podsumowanie tylko dla minionego miesiąca z zatwierdzonym planem.

**Contract**: Nowy klucz `'summary' => $timing === 'past' ? MonthSummary::forMonth($toSpend, $budget, $month)?->toArray() : null`. Kształt tablicy w PHPDoc `monthPlanPayload` dostaje `summary: array{...}|null` (kształt z Fazy 1).

#### 2. Typ propa

**File**: `resources/js/types/month-plan.ts`, `resources/js/pages/MonthPlan.vue`

**Intent**: Utrzymać kontrakt propsów po obu stronach w tym samym commicie (`AGENTS.md`, „Inertia props contract”).

**Contract**: `export type MonthSummary` w `types/month-plan.ts`, o kształcie odpowiadającym `toArray()`. Lokalny typ `MonthPlanReading` w `MonthPlan.vue` dostaje `summary: MonthSummary | null`.

#### 3. Karta podsumowania

**File**: `resources/js/components/MonthSummaryCard.vue` (nowy)

**Intent**: Pokazać trzy bloki podsumowania w stylu istniejących kart strony.

**Contract**:

- Prop `summary: MonthSummary`.
- `Card`/`CardContent` jak karta „Do wydania”, nagłówek w stylu `text-xs uppercase text-muted-foreground`.
- Tabela sum: wiersze Dochody i Wydatki, kolumny Zatwierdzony | Końcowy | Faktycznie | Odchylenie. Kwoty przez `formatMoney`, odchylenie przez `formatSignedMoney`, wszystkie wyrównane do prawej z `tabular-nums`.
- Blok odkładania: każda kwota ze znakiem opisana jak w karcie odkładania („Odkładam X” / „Dobieram X”), faktyczne opisane jako Wynik, różnica przez `formatSignedMoney`.
- Linia „Dobrane od początku roku: X” przez `formatMoney`.
- Kolory tylko z tokenów (`.ai/rules/js.md`). Odchylenie i różnica nie dostają koloru statusu. `text-destructive` tylko tam, gdzie strona już go używa dla liczb poniżej zera.

Przed pisaniem przeczytaj `.ai/skills/openship-ui-development/SKILL.md` i `.ai/rules/js.md`.

#### 4. Strona Plan miesiąca

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Wstawić kartę, oznaczyć końcowe „do wydania” i nie pokazywać odkładania dwa razy.

**Contract**:

- `<MonthSummaryCard v-if="monthPlan.summary" :summary="monthPlan.summary" />` bezpośrednio po karcie „Do wydania”.
- Etykieta tej karty: `monthPlan.summary ? t.month_plan.to_spend_final : t.month_plan.to_spend`.
- Gdy `monthPlan.summary !== null`, samodzielna karta odkładania się nie wyświetla, bo jej treść (tylko do odczytu) jest w podsumowaniu. Formularz zostaje, a ukrywa się tylko ta karta.

#### 5. Pasek miesiąca na stronie Budżet

**File**: `resources/js/components/BudgetMonthStrip.vue`

**Intent**: Wskazać, że dla minionego zatwierdzonego miesiąca link prowadzi do podsumowania.

**Contract**: Tekst istniejącego linku: `viewedMonthTiming === 'past' && monthToSpend.state === 'approved' ? t.month_plan.summary_title : t.nav.month_plan`. Bez nowych propsów i bez zmiany adresu.

#### 6. Teksty UI

**File**: `lang/pl/ui.php` (grupa `month_plan`)

**Intent**: Polska kopia karty.

**Contract**: Nowe klucze:

| Klucz | Tekst |
| --- | --- |
| `to_spend_final` | „Do wydania (końcowe)” |
| `summary_title` | „Podsumowanie miesiąca” |
| `summary_approved` | „Zatwierdzony” |
| `summary_final` | „Końcowy” |
| `summary_actual` | „Faktycznie” |
| `summary_deviation` | „Odchylenie” |
| `summary_set_aside` | „Odkładanie” |
| `summary_set_aside_approved` | „Zatwierdzone” |
| `summary_set_aside_final` | „Końcowe” |
| `summary_set_aside_real` | „Faktycznie (Wynik)” |
| `summary_set_aside_difference` | „Różnica” |
| `summary_year_draw` | „Dobrane od początku roku” |
| `summary_hint` | jedno zdanie: liczone na żywo z operacji, późna operacja zmienia podsumowanie |

Istniejący `approved_past_hint` zostaje.

#### 7. Testy strony

**File**: `tests/Feature/MonthPlanPageTest.php`

**Intent**: Zablokować kontrakt propa `assertInertia` (`AGENTS.md`).

**Contract**:

- `test_past_approved_month_is_read_only` (`:173`) dostaje asercje `monthPlan.summary.income.final_minor_units`, `monthPlan.summary.expense.actual_minor_units`, `monthPlan.summary.set_aside.real_minor_units` i `monthPlan.summary.year_draw_minor_units`.
- Nowe asercje `->where('monthPlan.summary', null)` dla minionego miesiąca bez planu (`:156`) i dla bieżącego zatwierdzonego miesiąca: istniejący test albo jeden nowy `test_current_approved_month_has_no_summary`.

### Success Criteria:

#### Automated Verification:

- Testy strony przechodzą: `php artisan test --compact tests/Feature/MonthPlanPageTest.php tests/Feature/MonthSummaryTest.php`
- Testy strony przechodzą na PostgreSQL (obraz Sail z `AGENTS.md`)
- Pełna bramka CI przechodzi w obrazie Sail: `composer ci:check`
- Frontend przechodzi: `npm run build`, `npm run check`, `npm run types:check`, `npm test`

#### Manual Verification:

- Miniony zatwierdzony miesiąc pokazuje kartę „Podsumowanie miesiąca” z tabelą sum, odkładaniem i „Dobrane od początku roku”, a karta „Do wydania (końcowe)” ma nową etykietę
- Bieżący miesiąc oraz miniony bez planu nie pokazują karty
- Zaksięgowanie operacji z datą w minionym miesiącu zmienia podsumowanie po odświeżeniu
- Link w pasku miesiąca na stronie Budżet ma tekst „Podsumowanie miesiąca” dla minionego zatwierdzonego miesiąca i „Plan miesiąca” w pozostałych
- Karta czyta się na szerokości telefonu, a kolumny tabeli sum się nie rozjeżdżają

**Implementation Note**: Po przejściu automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- Brak nowych testów `tests/Unit`. `MonthSummary` potrzebuje bazy (`BudgetMonthResult`, plany), więc testy są w `tests/Feature/MonthSummaryTest.php`, jak `MonthToSpendTest`.
- Przypadki brzegowe:
  - niezapłacony rachunek w sumie wydatków;
  - kategoria dołączona później;
  - wydatek poza planem;
  - dobieranie ze znakiem w różnicy;
  - granica „do podsumowywanego miesiąca” w „dobieram”;
  - miesiąc bez planu w środku roku.

### Integration Tests:

- `MonthPlanPageTest`: obecność i kształt `monthPlan.summary` dla minionego zatwierdzonego miesiąca oraz null w pozostałych stanach.

### Manual Testing Steps:

1. Ustaw zatwierdzony plan w minionym miesiącu z odkładaniem zmienionym po zatwierdzeniu i z co najmniej jednym niezapłaconym rachunkiem. Sprawdź tabelę sum i różnicę odkładania.
2. Ustaw dobieranie w dwóch minionych miesiącach. Sprawdź, że „Dobrane od początku roku” w drugim miesiącu sumuje oba, a w pierwszym tylko pierwszy.
3. Dodaj operację datowaną w minionym miesiącu i odśwież stronę: zmieniają się Faktycznie, Odchylenie, Wynik i Różnica.
4. Przejdź na bieżący miesiąc i na miniony bez planu: karty nie ma.

## Performance Considerations

Jedno dodatkowe zapytanie agregujące (`BudgetMonthResult`) na stronę minionego zatwierdzonego miesiąca. „Dobieram” w roku czyta plany załadowane już przez `MonthPlanProposal::forBudget`, więc nie robi zapytań w pętli.

## Migration Notes

Bez migracji. Plany zatwierdzone przed S-02 mają `approved_set_aside_minor_units = 0` (`context/archive/2026-11-02-set-aside-and-draw/plan.md:56`) i tak się wyświetlą.

## References

- Research: `context/changes/month-summary/research.md`
- Domena: `CONTEXT.md` — Month Summary, Month Result, Set-aside, Current Month Plan
- PRD: `context/foundation/prd.md` — FR-009, FR-013, „Rozliczenie końca miesiąca”
- Wzorzec odczytu: `app/Support/MonthToSpend.php:32-95`
- Payload strony: `app/Http/Controllers/MonthPlanController.php:143-203`
- Wzorzec testu: `tests/Feature/MonthToSpendTest.php`, `tests/Feature/MonthPlanPageTest.php:173`
- Wcześniejsze decyzje: `context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:72-74`, `context/archive/2026-11-02-set-aside-and-draw/plan.md:64,71`
- Równoległa zmiana: gałąź `worktree-month-plans-vs-year` (S-10)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Odczyt podsumowania na serwerze

#### Automated

- [x] 1.1 Testy podsumowania przechodzą na SQLite — 3a795ae
- [x] 1.2 Testy podsumowania i odczytu planu przechodzą na PostgreSQL — 3a795ae
- [x] 1.3 Pint bez zmian — 3a795ae
- [x] 1.4 Analiza statyczna przechodzi — 3a795ae

#### Manual

- [x] 1.5 Wpis Month Summary w CONTEXT.md jednoznaczny i zgodny z przykładami — 3a795ae

### Phase 2: Prop i strona

#### Automated

- [ ] 2.1 Testy strony przechodzą
- [ ] 2.2 Testy strony przechodzą na PostgreSQL
- [ ] 2.3 Pełna bramka CI przechodzi w obrazie Sail
- [ ] 2.4 Frontend przechodzi: build, check, types:check, test

#### Manual

- [ ] 2.5 Miniony zatwierdzony miesiąc pokazuje kartę podsumowania i etykietę „Do wydania (końcowe)”
- [ ] 2.6 Bieżący i miniony bez planu nie pokazują karty
- [ ] 2.7 Operacja w minionym miesiącu zmienia podsumowanie po odświeżeniu
- [ ] 2.8 Link w pasku Budżetu ma właściwy tekst
- [ ] 2.9 Karta czytelna na szerokości telefonu
