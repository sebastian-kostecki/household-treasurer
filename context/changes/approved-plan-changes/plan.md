# S-03: Zmiana zatwierdzonego planu miesiąca — plan implementacji

## Overview

Użytkownik może zmienić kwoty zatwierdzonego planu bieżącego albo przyszłego miesiąca. Zmiana zapisuje tylko Current Month Plan (`current_amount_minor_units`); Approved Month Plan (`approved_amount_minor_units`) zostaje zamrożony od zatwierdzenia, więc S-09 pokaże plan zatwierdzony i końcowy (FR-003, roadmapa S-03, ryzyko `context/foundation/roadmap.md:131`). Na stronie „Plan miesiąca” zmiana odbywa się w trybie „Zmień plan” z podglądem „do wydania”, a pozycja o kwocie innej niż zatwierdzona pokazuje „Zatwierdzono: X”.

## Current State Analysis

Za `context/changes/approved-plan-changes/research.md`:

- Schemat jest gotowy: `month_plan_items` ma osobne `approved_amount_minor_units` i `current_amount_minor_units` (`bigint NOT NULL`, `database/migrations/2026_11_02_091922_create_month_plan_items_table.php:18-19`), zatwierdzenie zapisuje obie równe (`app/Actions/ApproveMonthPlan.php:64-70`). Unikalność (plan, kategoria) pozwala dopisać pozycję do istniejącego planu.
- Jedyny zapis to `POST budgets/{budget}/month-plans/{month}/approve` (`routes/web.php:29-31`); `ApproveMonthPlan` odrzuca stan `approved` (`ApproveMonthPlan.php:35-37`) i wymaga równości zbioru kategorii z propozycją (`:47-54`).
- Odczyt planu zatwierdzonego czyta kwotę bieżącą (`app/Support/MonthPlanProposal.php:95-102`); z niego korzystają wiersze strony, „do wydania” (`app/Http/Controllers/MonthPlanController.php:105-132,189`) i propozycja następnego miesiąca (`MonthPlanProposal.php:129-166`). Kwoty zatwierdzonej nie czyta nic w `app/` poza snapshotem; `MonthPlanReading` ma jedną mapę `amounts` (`app/Support/MonthPlanReading.php:13-16`).
- Wiersze strony to klucze odczytu ∪ kategorie z operacjami w miesiącu (`MonthPlanController.php:109-112`). Kategoria utworzona po zatwierdzeniu, bez operacji, nie pojawia się wcale; z operacjami jest wierszem `in_plan=false`.
- Strona: `isEditable = state === 'proposal' && can_approve` (`resources/js/pages/MonthPlan.vue:81-85`) steruje ukrytymi polami (`:324-340`), polem kwoty (`:446-453`) i podglądem TS (`:136-154`); przycisk wymaga `can_approve` (`:465`); `approved_hint` mówi „tylko do odczytu” (`lang/pl/ui.php:286`).
- Snapshot v5 przenosi obie kwoty (`app/Actions/BackupUserData.php:18,180-181`, `app/Actions/RestoreUserData.php:262,284-285`) — S-03 bez zmiany schematu nie dotyka snapshotu.
- S-02 (`set-aside-and-draw`) ma research, bez planu i kodu; skłania się do pary zatwierdzony/bieżący Set-aside i dotknie tych samych plików (`MonthPlan.vue`, request, akcja).

## Desired End State

- `PATCH budgets/{budget}/month-plans/{month}` (`month-plans.update`) zmienia kwoty bieżące zatwierdzonego planu miesiąca bieżącego albo przyszłego; kwoty zatwierdzone i `approved_at` zostają bez zmian.
- Kategoria budżetu niezarchiwizowana, bez pozycji planu, może dołączyć przy zmianie z kwotą > 0: powstaje pozycja z `approved = 0`, `current = podana kwota`.
- Strona dla zatwierdzonego bieżącego/przyszłego miesiąca ma przycisk „Zmień plan”; tryb zmiany pokazuje pola kwot (także dla kategorii spoza planu), podgląd „do wydania”, „Zapisz zmiany” i „Anuluj”. Miniony zatwierdzony miesiąc zostaje tylko do odczytu.
- Pozycja, której kwota bieżąca różni się od zatwierdzonej, pokazuje pod kwotą „Zatwierdzono: X” — także w minionym miesiącu.
- Weryfikacja: testy Feature akcji i strony na PostgreSQL, `assertInertia` dla nowych propsów, `npm run check` / `types:check`, ręczne sprawdzenie w podglądzie.

### Key Discoveries:

- Wzorzec przełączania `store`/`update` w jednym `<Form>`: `formBinding` computed w `resources/js/components/IncomeFormDialog.vue:87-91`, zamknięcie po sukcesie `@success` (`:260`).
- Reguła „który wiersz można edytować” wynika z istniejących pól: w propozycji każda niezarchiwizowana kategoria jest w odczycie (`MonthPlanProposal.php:113-119`), więc `in_plan=false` ⇒ zarchiwizowana; edytowalny wiersz to `in_plan || !is_archived` w obu stanach.
- Precedens reguły miesięcy: `can_change_kind = … && timing !== 'past'` (`MonthPlanController.php:192`).
- Błędy domenowe planu: `ValidationException` na kluczu `items` (`ApproveMonthPlan.php:80-83`), wyświetlane przez `InputError :message="errors.items"` (`MonthPlan.vue:466`). Komunikaty i toasty to angielskie `__()` bez tłumaczeń w `lang/pl` — istniejący wzorzec.
- Kopia TS wzoru „do wydania” jest dziś uzasadniona tylko propozycją (`AGENTS.md:32`, docblock `resources/js/lib/to-spend-calculator.ts:9-12`).

## What We're NOT Doing

- Set-aside: S-03 zmienia tylko kwoty kategorii. Który z wycinków S-02/S-03 wyląduje drugi, ten dopisuje bieżący Set-aside do ścieżki zmiany (`ChangeMonthPlan`, `UpdateMonthPlanRequest`, tryb zmiany na stronie).
- Zmiana minionego miesiąca, także zatwierdzonego (to plan końcowy, `CONTEXT.md:72`).
- Cofnięcie zatwierdzenia i ponowne zatwierdzenie (`CONTEXT.md:68` „immutable afterwards”).
- Zmiana `approved_amount_minor_units` istniejącej pozycji w jakimkolwiek przypadku.
- Dołączanie kategorii zarchiwizowanej bez pozycji planu (ADR-0033: zarchiwizowana zostaje tylko tam, gdzie ma plan).
- Migracja, kolumna historii/wersji, `NULL` jako „poza planem zatwierdzonym”, podbicie wersji snapshotu.
- Kolumna „Zatwierdzony” i porównanie planów — to S-04/S-09.
- Sygnalizacja w UI, że zmiana miesiąca M zmienia propozycje M+1… (zgodne z ADR-0033 ¶2; zatwierdzonych późniejszych miesięcy zmiana nie rusza).
- Zmiana wzoru „do wydania” i jego testów (PHP/TS).

## Implementation Approach

Osobna ścieżka zapisu obok zatwierdzenia: nowa trasa, FormRequest i akcja `ChangeMonthPlan`, wzorowane na `ApproveMonthPlan`. Akcja w transakcji blokuje wiersz `month_plans` (`lockForUpdate`), aktualizuje `current_amount_minor_units` przesłanych pozycji i tworzy pozycje dołączających kategorii z `approved = 0`. Pozycje nieprzesłane zostają bez zmian (formularz zawsze przesyła wszystkie edytowalne wiersze; tolerancja chroni przed kategorią utworzoną w innej karcie między odczytem a zapisem).

Strona dostaje z serwera: `can_change` (plan zatwierdzony ∧ miesiąc nie miniony), w wierszu `approved_minor_units` (kwota zatwierdzona albo `null`) i `is_join_candidate` (wiersz dodany tylko po to, by kategoria mogła dołączyć w trybie zmiany). Vue rozszerza istniejący mechanizm formularza propozycji o flagę `isChanging`; `<Form>` przełącza akcję między `approve` a `update` jak `IncomeFormDialog`.

**Powód kopii TS (`AGENTS.md:30`):** w trybie zmiany „do wydania” musi reagować przed zapisem, bo użytkownik ocenia skutek zmiany, zanim nadpisze plan końcowy. Wzór się nie zmienia; aktualizujemy tylko uzasadnienie w `AGENTS.md:32` i docblocku `to-spend-calculator.ts`.

### Reguły zmiany (dokładne predykaty)

Dla budżetu B, miesiąca M, „dziś” = `CarbonImmutable::today('UTC')`:

- `B` nie w wykonaniu → `AuthorizationException` (polityka `manageMonthPlan` zwraca 403 wcześniej).
- `M ∉ 1..12` → błąd `items` „The month is invalid.”
- `[B.year, M] < [today.year, today.month]` → błąd `items` „A past month plan cannot be changed.” (także gdy plan istnieje).
- Brak `month_plans` dla (B, M) → błąd `items` „Approve this month plan first.”
- Dozwolone kategorie = kategorie pozycji planu ∪ kategorie B z `archived_at = null`. Przesłana kategoria spoza tego zbioru (inny budżet, zarchiwizowana bez pozycji, nieistniejąca) → błąd `items` „The month plan items are invalid.”; nic nie jest zapisane.
- Przesłana kategoria z pozycją → `current = kwota`, `approved` bez zmian. Bez pozycji i kwota > 0 → nowa pozycja `approved = 0`, `current = kwota`. Bez pozycji i kwota 0 → bez zmian (formularz przesyła wszystkie edytowalne wiersze, więc 0 nie oznacza dołączenia; brak pozycji i pozycja 0 dają to samo „do wydania” i tę samą propozycję M+1).
- Pozycja nieprzesłana → bez zmian. `approved_at` planu → bez zmian.
- Pozycja zarchiwizowanej kategorii, która już jest w planie → edytowalna.

Przykład kontrolny (czerwiec 2026, „dziś” 2026-06-15, plan czerwca zatwierdzony: Pensja 500 000/500 000, Czynsz 100 000/100 000, Jedzenie 150 000/150 000; brak operacji; Jedzenie Running, Czynsz Bill): zmiana Jedzenie → 180 000 i nowa kategoria „Wesele” (wydatek, Running, utworzona po zatwierdzeniu) → 50 000, Czynsz nieprzesłany. Wynik: Jedzenie 150 000/180 000, Wesele 0/50 000, Czynsz 100 000/100 000. „Do wydania”: przed 500 000 − 100 000 − 150 000 = **250 000**, po 500 000 − 100 000 − 180 000 − 50 000 = **170 000**. Propozycja lipca (niezatwierdzony) kopiuje Jedzenie = 180 000.

## Phase 1: Ścieżka zmiany na serwerze

### Overview

Trasa, walidacja, akcja i kontroler zapisujące kwoty bieżące zatwierdzonego planu, z testami na PostgreSQL. Strona jeszcze się nie zmienia.

### Changes Required:

#### 1. Trasa

**File**: `routes/web.php`

**Intent**: Dodać trasę zmiany obok `month-plans.approve`.

**Contract**: `Route::patch('budgets/{budget}/month-plans/{month}', [MonthPlanController::class, 'update'])->whereNumber('month')->name('month-plans.update')`.

#### 2. Walidacja

**File**: `app/Http/Requests/UpdateMonthPlanRequest.php` (nowy, `php artisan make:request`)

**Intent**: Te same uprawnienia i limity kwot co zatwierdzenie.

**Contract**: `authorize` = `can('manageMonthPlan', $budget)` jak `ApproveMonthPlanRequest.php:11-21`; reguły `items` `required|array`, `items.*.category_id` `required|integer|distinct`, `items.*.amount_minor_units` `required|integer|min:0|max:1000000000000`.

#### 3. Akcja

**File**: `app/Actions/ChangeMonthPlan.php` (nowy)

**Intent**: Zapisać kwoty bieżące według „Reguł zmiany” w jednej transakcji, z blokadą wiersza planu, żeby równoległe zmiany się szeregowały.

**Contract**: `handle(Budget $budget, int $month, array $amounts, CarbonImmutable $today): MonthPlan` z `@param array<int, int> $amounts` (category id → kwota). Kolejność sprawdzeń jak w „Regułach zmiany”; wszystkie odrzucenia jako `ValidationException::withMessages(['items' => …])`. Plan pobierany z `lockForUpdate()`; pozycje czytane dopiero po blokadzie, w tej samej transakcji (`$plan->items()->get()`, bez wcześniejszego eager loadu), żeby równoległe dołączenie tej samej kategorii nie trafiło w unikalność (plan, kategoria). Nie czyta `MonthPlanProposal` (nie potrzebuje propozycji). Docblock klasy nazywa niezmiennik: `approved_amount_minor_units` istniejącej pozycji nigdy nie jest zapisywane.

#### 4. Kontroler

**File**: `app/Http/Controllers/MonthPlanController.php`

**Intent**: Metoda `update` jak `approve` (`:70-90`): mapuje `items` na `[category_id => amount]`, woła akcję z `today('UTC')`, toast i powrót na stronę miesiąca.

**Contract**: `update(UpdateMonthPlanRequest, Budget, int $month, ChangeMonthPlan): RedirectResponse`; toast `success` z `__('Month plan changed.')`; `to_route('month-plan', ['month' => $month])`.

#### 5. Testy

**File**: `tests/Feature/ChangeMonthPlanTest.php` (nowy, `php artisan make:test`, PHPUnit-style jak `ApproveMonthPlanTest`)

**Intent**: Pokryć reguły zmiany i trasę.

**Contract**: Przypadki:
- przykład kontrolny: kwota bieżąca zmieniona, zatwierdzona nie, `approved_at` nie; nowa kategoria dołącza z 0/50 000; pozycja nieprzesłana bez zmian,
- kategoria bez pozycji przesłana z kwotą 0 nie tworzy pozycji (wiersz nadal `in_plan=false`),
- zmiana przyszłego zatwierdzonego miesiąca działa,
- odrzucenia na `items` bez zapisu: miniony zatwierdzony miesiąc, miesiąc bez zatwierdzonego planu (propozycja), miesiąc spoza 1..12, kategoria innego budżetu, zarchiwizowana kategoria bez pozycji,
- pozycja zarchiwizowanej kategorii obecna w planie jest edytowalna,
- trasa: 403 dla cudzego i szkicowego budżetu, 302 z błędem dla kwoty ujemnej, ponad limit, zduplikowanej kategorii i pustego `items`; sukces przekierowuje na `month-plan?month=M`,
- propozycja następnego miesiąca po zmianie kopiuje nową kwotę bieżącą (`MonthPlanProposal::forBudget(...)->reading(M+1)`),
- zatwierdzony późniejszy miesiąc nie zmienia się.

### Success Criteria:

#### Automated Verification:

- `tests/Feature/ChangeMonthPlanTest.php` przechodzi na PostgreSQL (obraz Sail na worktree, `AGENTS.md` „Test database”)
- Istniejące `tests/Feature/ApproveMonthPlanTest.php`, `tests/Feature/MonthPlanPageTest.php`, `tests/Feature/MonthPlanProposalTest.php` przechodzą bez zmian
- `vendor/bin/pint --dirty --format agent` przechodzi
- `php artisan route:list --name=month-plans` pokazuje `month-plans.update` (PATCH)

**Implementation Note**: Faza 1 nie ma kroków ręcznych; po przejściu automatycznych przejdź do fazy 2.

---

## Phase 2: Tryb zmiany na stronie

### Overview

Nowe propsy (PHP i TS w jednym commicie), tryb „Zmień plan” z podglądem i podpowiedzią „Zatwierdzono: X”, teksty, uzasadnienie kopii TS i sprawdzenie w podglądzie.

### Changes Required:

#### 1. Odczyt kwot zatwierdzonych

**Files**: `app/Support/MonthPlanReading.php`, `app/Support/MonthPlanProposal.php`

**Intent**: Odczyt planu zatwierdzonego niesie też kwoty zatwierdzone, żeby kontroler mógł je przekazać.

**Contract**: `MonthPlanReading` dostaje `public readonly array $approvedAmounts = []` (`array<int, int>`, category id → `approved_amount_minor_units`), puste dla `proposal` i `none`. `computePlan` (`MonthPlanProposal.php:95-102`) wypełnia je obok `amounts`; `amounts` nadal czyta `current_amount_minor_units`.

#### 2. Propsy strony

**File**: `app/Http/Controllers/MonthPlanController.php`

**Intent**: Przekazać flagę zmiany, kwotę zatwierdzoną w wierszu i wiersze kandydatów do dołączenia.

**Contract**:
- `monthPlan.can_change: bool` = `state === 'approved' && timing !== 'past'`.
- Wiersz: `approved_minor_units: int|null` = `reading->approvedAmounts[id] ?? null`; `is_join_candidate: bool`.
- Gdy `can_change`: do `rowIds` dochodzą kategorie budżetu z `archived_at = null`, których nie ma w odczycie ani w operacjach miesiąca; mają `in_plan=false`, `is_join_candidate=true`, plan 0, faktycznie 0 (nie zmieniają „do wydania”). W pozostałych wierszach `is_join_candidate=false`.
- Docblocki tablic `monthPlanPayload` i `itemRow` z nowymi polami.

#### 3. Strona

**File**: `resources/js/pages/MonthPlan.vue`

**Intent**: Tryb zmiany zatwierdzonego planu, z ponownym użyciem pól, ukrytych inputów, walidacji i podglądu propozycji.

**Contract**:
- Typy: `MonthPlanReading.can_change: boolean`; `MonthPlanItemRow.approved_minor_units: number | null`, `is_join_candidate: boolean`.
- `isChanging` (ref, start `false`); zerowany przy zmianie `viewedMonth`/`budget.id`, po „Anuluj” i po `@success` zapisu, z ponownym `initialAmountInputs()`.
- Edytowalny wiersz: `row.in_plan || !row.is_archived`. `isEditable` = (propozycja ∧ `can_approve`) ∨ `isChanging`. `planRows`, `initialAmountInputs`, `parsedAmount`, podgląd i ukryte pola używają edytowalnych wierszy zamiast `in_plan` (w propozycji zbiór jest identyczny, bo tam `in_plan=false` ⇒ zarchiwizowana).
- Wiersze `is_join_candidate` widoczne tylko przy `isChanging`: odfiltrowane w `allRows` i `sections` (nie przez `v-if` na wierszu), żeby `section.rows.length === 0` nadal pokazywało „Brak pozycji”. W trybie zmiany kandydat ma badge „Poza planem” jak inne wiersze `in_plan=false` — do planu wchodzi dopiero po zapisie z kwotą > 0.
- `<Form>`: binding computed — `approve.form(...)` dla propozycji, `update.form(...)` dla zatwierdzonego (wzorzec `IncomeFormDialog.vue:87-91`).
- Przyciski: przy `can_change && !isChanging` „Zmień plan” (`type="button"`); przy `isChanging` „Anuluj” (`type="button"`, wariant `outline`) i „Zapisz zmiany” (`submit`, wyłączony przy `processing || !allAmountsValid`), z `InputError :message="errors.items"`. Blok `can_approve` bez zmian.
- Pod kwotą planu (tryb odczytu i zmiany): `approved_minor_units !== null && approved_minor_units !== plan_minor_units` → mała linia `text-muted-foreground text-xs tabular-nums` „{approved_amount}: {formatMoney(approved)}”. W trybie zmiany porównanie z wartością z serwera (nie z polem).
- Podpowiedź pod „do wydania”: zatwierdzony i `can_change` → `approved_hint` (nowy tekst); zatwierdzony miniony → `approved_past_hint`; w trybie zmiany → `changing_hint`.

#### 4. Teksty

**File**: `lang/pl/ui.php` (sekcja `month_plan`)

**Intent**: Usunąć nieprawdziwe „tylko do odczytu” i dodać teksty trybu zmiany.

**Contract**: `approved_hint` → „Plan jest zatwierdzony. Do końca miesiąca możesz zmienić jego kwoty.”; nowe: `approved_past_hint` „Plan minionego miesiąca jest tylko do odczytu.”, `changing_hint` „Zmieniasz plan. Kwoty zatwierdzone zostają zapisane do podsumowania miesiąca.”, `change` „Zmień plan”, `save_changes` „Zapisz zmiany”, `cancel` „Anuluj”, `approved_amount` „Zatwierdzono”. Typ `Record<string, string>` (`resources/js/types/shell.ts:41`) bez zmian.

#### 5. Uzasadnienie kopii TS

**Files**: `AGENTS.md` (sekcja „Month logic lives twice”, akapit o `ToSpendCalculator`), `resources/js/lib/to-spend-calculator.ts:9-12`

**Intent**: Dopisać drugi powód kopii TS: zmiana zatwierdzonego planu nadpisuje plan końcowy, więc podgląd musi reagować przed zapisem.

**Contract**: Tylko proza; kod i testy wzoru bez zmian.

#### 6. Testy strony

**File**: `tests/Feature/MonthPlanPageTest.php`

**Intent**: Asercje `assertInertia` nowych propsów i przepływu strona → zapis.

**Contract**: Przypadki:
- bieżący zatwierdzony miesiąc: `can_change=true`, `approved_minor_units` równe zatwierdzonej, po zmianie (approved ≠ current) `plan_minor_units` = bieżąca i `approved_minor_units` = zatwierdzona, `to_spend_minor_units` z kwoty bieżącej,
- kategoria niezarchiwizowana bez pozycji i bez operacji: wiersz `is_join_candidate=true`, `in_plan=false`, plan 0; zarchiwizowana bez pozycji nie jest kandydatem,
- `test_past_approved_month_is_read_only` dostaje `can_change=false` i brak kandydatów,
- propozycja: `can_change=false`, `approved_minor_units=null`, `is_join_candidate=false`,
- PATCH `month-plans.update` ze strony przekierowuje z toastem, a ponowny odczyt pokazuje nowe kwoty.

### Success Criteria:

#### Automated Verification:

- `tests/Feature/MonthPlanPageTest.php`, `tests/Feature/ChangeMonthPlanTest.php` i `tests/Feature/MonthPlanProposalTest.php` przechodzą na PostgreSQL
- `vendor/bin/pint --dirty --format agent` przechodzi
- `npm run build`, potem `npm run types:check` i `npm run check` przechodzą
- Pełna bramka CI (`composer ci:check` w obrazie Sail na worktree) przechodzi

#### Manual Verification:

- W podglądzie worktree (`AGENTS.md` „Preview from a worktree”): zatwierdzony bieżący miesiąc pokazuje „Zmień plan”; tryb zmiany pokazuje pola, „Zapisz zmiany” i „Anuluj”, a „do wydania” zmienia się przy wpisywaniu
- Po zapisie strona wraca do odczytu, kwoty są nowe, a zmienione pozycje pokazują „Zatwierdzono: X”
- „Anuluj” przywraca kwoty z serwera bez zapisu
- Kategoria utworzona po zatwierdzeniu pojawia się dopiero w trybie zmiany i po zapisie zostaje w planie z „Zatwierdzono: 0,00 zł”
- Miniony zatwierdzony miesiąc nie ma „Zmień plan”, a przyszły zatwierdzony ma
- Przełącznik „Rachunek / Bieżąca” działa w trybie zmiany i nie gubi wpisanych kwot

**Implementation Note**: Po przejściu automatycznych kroków zbuduj podgląd i poproś o potwierdzenie kroków ręcznych przed zamknięciem fazy.

---

## Testing Strategy

### Unit Tests:

- Brak nowych testów jednostkowych: wzór „do wydania” się nie zmienia, a `MonthPlanReading` jest pokryty przez testy Feature odczytu.

### Integration Tests:

- `ChangeMonthPlanTest`: wszystkie predykaty „Reguł zmiany” i przykład kontrolny (250 000 → 170 000), na PostgreSQL ze względu na transakcję, `lockForUpdate` i unikalność (plan, kategoria).
- `MonthPlanPageTest`: kontrakt propsów `can_change`, `approved_minor_units`, `is_join_candidate` przez `assertInertia`.

### Manual Testing Steps:

1. Zaloguj się w podglądzie jako `test@example.com` / `password`, otwórz bieżący miesiąc i zatwierdź go, jeśli jest propozycją.
2. Kliknij „Zmień plan”, zmień jedną kwotę wydatku, obserwuj „do wydania”, zapisz; sprawdź „Zatwierdzono: X”.
3. Utwórz kategorię na stronie Budżet, wróć, wejdź w „Zmień plan”, nadaj jej kwotę, zapisz.
4. Otwórz miniony i przyszły zatwierdzony miesiąc; sprawdź obecność „Zmień plan”.

## Performance Considerations

Kandydaci do dołączenia pochodzą z kategorii już wczytanych przez `MonthPlanProposal::forBudget` (`MonthPlanProposal.php:42`) albo z jednego zapytania o kategorie budżetu; liczba zapytań strony rośnie najwyżej o jedno.

## Migration Notes

Brak migracji i zmian snapshotu. Istniejące plany mają approved == current, więc nie pokazują podpowiedzi do pierwszej zmiany. Koordynacja z S-02: jeśli S-02 wyląduje po S-03, jego plan dopisuje bieżący Set-aside do `ChangeMonthPlan`, `UpdateMonthPlanRequest` i trybu zmiany; jeśli przed — S-03 przy implementacji dopisuje go tutaj (konflikt tekstowy spodziewany w `MonthPlan.vue`).

## References

- Research: `context/changes/approved-plan-changes/research.md`
- S-01: `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:56,77,283`
- ADR: `.docs/adr/0033-month-plan-beside-annual-budget.md`
- Domena: `CONTEXT.md` (Month Plan, Approved Month Plan, Current Month Plan, To Spend)
- Wzorzec akcji: `app/Actions/ApproveMonthPlan.php:20-83`
- Wzorzec przełączania formularza: `resources/js/components/IncomeFormDialog.vue:87-91,260`
- Research S-02: `../set-aside-and-draw/context/changes/set-aside-and-draw/research.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Ścieżka zmiany na serwerze

#### Automated

- [x] 1.1 `tests/Feature/ChangeMonthPlanTest.php` przechodzi na PostgreSQL (obraz Sail na worktree, `AGENTS.md` „Test database”)
- [x] 1.2 Istniejące `tests/Feature/ApproveMonthPlanTest.php`, `tests/Feature/MonthPlanPageTest.php`, `tests/Feature/MonthPlanProposalTest.php` przechodzą bez zmian
- [x] 1.3 `vendor/bin/pint --dirty --format agent` przechodzi
- [x] 1.4 `php artisan route:list --name=month-plans` pokazuje `month-plans.update` (PATCH)

### Phase 2: Tryb zmiany na stronie

#### Automated

- [ ] 2.1 `tests/Feature/MonthPlanPageTest.php`, `tests/Feature/ChangeMonthPlanTest.php` i `tests/Feature/MonthPlanProposalTest.php` przechodzą na PostgreSQL
- [ ] 2.2 `vendor/bin/pint --dirty --format agent` przechodzi
- [ ] 2.3 `npm run build`, potem `npm run types:check` i `npm run check` przechodzą
- [ ] 2.4 Pełna bramka CI (`composer ci:check` w obrazie Sail na worktree) przechodzi

#### Manual

- [ ] 2.5 W podglądzie worktree (`AGENTS.md` „Preview from a worktree”): zatwierdzony bieżący miesiąc pokazuje „Zmień plan”; tryb zmiany pokazuje pola, „Zapisz zmiany” i „Anuluj”, a „do wydania” zmienia się przy wpisywaniu
- [ ] 2.6 Po zapisie strona wraca do odczytu, kwoty są nowe, a zmienione pozycje pokazują „Zatwierdzono: X”
- [ ] 2.7 „Anuluj” przywraca kwoty z serwera bez zapisu
- [ ] 2.8 Kategoria utworzona po zatwierdzeniu pojawia się dopiero w trybie zmiany i po zapisie zostaje w planie z „Zatwierdzono: 0,00 zł”
- [ ] 2.9 Miniony zatwierdzony miesiąc nie ma „Zmień plan”, a przyszły zatwierdzony ma
- [ ] 2.10 Przełącznik „Rachunek / Bieżąca” działa w trybie zmiany i nie gubi wpisanych kwot
