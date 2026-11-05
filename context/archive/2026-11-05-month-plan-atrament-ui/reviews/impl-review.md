<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Plan miesiąca w systemie „C · Atrament i morski”

- **Plan**: context/changes/month-plan-atrament-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-05
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 7 observations (F11 found during triage)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Automated verification (2026-11-05, this review)

- `npm run build` → `types:check`, `check`, `check:ui`, `npm test` (26 files, 251 tests): pass (host Node)
- Month plan tests on PostgreSQL in the Sail image (ToSpendCalculator, MonthPlanPage, MonthToSpend, ApproveMonthPlan, ChangeMonthPlan, DevMonthPlanSink, MonthPlanHardcodedUi): 107 passed
- `composer ci:check` in the Sail image: pass (Pest 890 passed, 2 skipped; PHPStan no errors)
- `vendor/bin/sloppy ci` (host): 0 findings; `vendor/bin/pint --test`: pass
- `grep -l "ADR-0042" .docs/adr/0035-*`: hit; `grep section-label` in js.md / design-system.md: none
- `grep -rn "section-label\|MonthPaceMeter" resources/ tests/`: hits only in `tests/Unit/MonthPlanHardcodedUiTest.php` (the guard's own pattern; see F9)

## Findings

### F1 — Hover „Anuluj” na pasie edycji ignoruje override

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/css/app.css:383
- **Detail**: Reguła `.month-plan-editbar [data-slot='button'][class*='hover:bg-accent']` leży w `@layer components`, a klasy wariantu ghost (`hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50`, `ui/button/index.ts:24-25`) w `@layer utilities`. W Tailwind v4 późniejsza warstwa wygrywa niezależnie od specyficzności, więc po najechaniu „Anuluj” dostaje miętowe tło akcentu zamiast `primary-foreground/15`, wbrew planowi (Faza 2 pkt 3). W spoczynku działa, bo ghost nie ustawia koloru tekstu. Selektor po podciągu klasy jest kruchy. Statyczne zrzuty tego nie pokazują.
- **Fix**: Przenieść klasy na `<Button variant="ghost" class="text-primary-foreground hover:bg-primary-foreground/15 hover:text-primary-foreground">` w `MonthPlanEditBar.vue` (scalane przez `cn`) i usunąć regułę z `app.css`.
- **Decision**: FIXED (fix differently) — klasy na Button wymagały `dark:hover:…`, którego zakazuje `check:ui`; zamiast tego addytywny wariant Button `ghost-inverse` (`ui/button/index.ts`), użyty na „Anuluj”; reguła z `app.css` usunięta; opis w `.ai/rules/js.md:56` i `.docs/design-system.md:15`.

### F2 — Brak zrzutów minionego zatwierdzonego miesiąca w widoku

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/month-plan-atrament-ui/screenshots/
- **Detail**: Kroki 4.6 i 5.4 są odhaczone, ale zrzuty „minionego” (`past-none-*`) pokazują stan pusty „Ten miesiąc nie ma planu”, a nie miniony zatwierdzony z odznaką „Końcowy”, StatPanelem z wartościami końcowymi i siatką `MonthSummaryCard`, czego wymaga plan (Desired End State, Faza 4 Manual). `DemoHouseholdSeeder` nie ma żadnego minionego zatwierdzonego miesiąca, więc kropka `summary` i ten wariant strony nie były widziane na żywo, tylko w sinku. Brakuje też `future-proposal-dark-390` i `past-none-dark-390`.
- **Fix A ⭐ Recommended**: W bazie podglądu zatwierdzić miniony miesiąc (np. październik) i dorobić zrzuty `past-approved-{light,dark}-{1280,390}` oraz brakujące dark-390.
  - Strength: Zamyka jedyną część widoku bez dowodu na żywo (odznaka „Końcowy”, siatka podsumowania na prawdziwych danych, kropka `summary`).
  - Tradeoff: Wymaga uruchomienia podglądu i ręcznej operacji na bazie podglądu.
  - Confidence: HIGH — podgląd `month-plan-atrament-preview` nadal działa.
  - Blind spot: Nie sprawdziłem, czy zatwierdzenie minionego miesiąca jest możliwe przez UI; może trzeba to zrobić w tinkerze na bazie podglądu.
- **Fix B**: Uznać sink jako dowód i dopisać w planie, że miniony zatwierdzony jest pokryty tylko przez `/dev/month-plan`.
  - Strength: Bez dodatkowej pracy; sink renderuje `MonthSummaryCard` i odznakę „Końcowy” na prawdziwych komponentach.
  - Tradeoff: Złożenie strony (kolejność, Form, brak pasa i wiersza odkładania przy `summary`) nie jest widziane na żywo.
  - Confidence: MED — sink kopiuje wiersz tytułu zamiast go renderować (F7).
  - Blind spot: Układ minionego miesiąca na 390 px na prawdziwej stronie.
- **Decision**: FIXED via Fix A — październik zatwierdzony w bazie podglądu `month_plan_atrament_preview` przez `ApproveMonthPlan` (today 2026-10-15, kwoty propozycji, odkładanie 500,00 zł); nowe zrzuty `past-approved-{light,dark}-{1280,390}`, `future-proposal-dark-390`, `past-none-dark-390` (wrzesień); `scrollWidth` = szerokość okna we wszystkich. Zrzut 390 px ujawnił F11.

### F3 — Status „do zapłaty” / „czeka” w zamkniętym miesiącu i przy planie 0

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/month-plan-status.ts:20
- **Detail**: `monthItemStatus` patrzy tylko na typ, rodzaj, `is_settled` i `overPlan`. W minionym miesiącu z odznaką „Końcowy” niezapłacony rachunek wciąż ma „do zapłaty”, a brakujący dochód „czeka”, choć miesiąc jest zamknięty. Rachunek z planem 0 bez płatności (jednorazowa w zwiniętej grupie, kandydat do dołączenia) też dostaje „do zapłaty”. Plan nie rozstrzygał tych przypadków; to luka planu, a nie dryf.
- **Fix A ⭐ Recommended**: Dodać do `monthItemStatus` parametr kontekstu (miniony miesiąc / plan 0): przy planie 0 bez ruchu brak plakietki, w minionym miesiącu brak plakietek „czeka” / „do zapłaty” (albo osobne słowo, np. „niezapłacone”); dopisać przypadki w `month-plan-status.test.ts`.
  - Strength: Status przestaje twierdzić, że coś jest jeszcze do zrobienia w zamkniętym miesiącu.
  - Tradeoff: Nowa decyzja o słownictwie; jeśli nowe słowo, to nowy klucz `lang` i aktualizacja sinka.
  - Confidence: MED — mapowanie jest w jednym miejscu, ale wybór słowa należy do Ciebie.
  - Blind spot: Nie sprawdziłem, jak Dashboard lub Budżet nazywają niezapłacone pozycje minionych miesięcy.
- **Fix B**: Zostawić jak jest i zapisać w ADR-0042 lub `js.md`, że Status opisuje fakt rozliczenia, nie termin.
  - Strength: Zero zmian kodu; zachowanie jest spójne i przewidywalne.
  - Tradeoff: „do zapłaty” w zamkniętym miesiącu nadal może mylić.
  - Confidence: HIGH — nic nie psuje danych.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A — `monthItemStatus(row, { overPlan, planMinorUnits, isClosed })`: plan 0 bez rozliczenia → brak plakietki; w zamkniętym miesiącu nowe klucze `status_unpaid` („niezapłacone”) i `status_not_received` („nie wpłynęło”), Badge `neutral`. Status liczony w `rowState` strony (plan na żywo przez `parsedAmount`, `summary !== null`) i sinka, przekazywany do `MonthPlanItemRow` propsem `status` (`MonthPlanRowState.status`). 4 nowe przypadki w `month-plan-status.test.ts`; reguła w `.ai/rules/js.md:58`.

### F11 — Siatka podsumowania nieczytelna na 390 px (znalezione w triage F2)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/css/app.css:401, resources/js/components/MonthSummaryCard.vue:46
- **Detail**: `.month-plan-summary-grid` ma na każdej szerokości 5 kolumn `minmax(0, …fr)` bez wariantu dla wąskiego ekranu. Na 390 px (`screenshots/past-approved-{light,dark}-390.png`) jednowyrazowe nagłówki nachodzą na sąsiednie („Zatwierdzony” na „Końcowy”, „Faktycznie (Wynik)” na „Różnica”), a kwoty łamią się na spacji tysięcy („8 / 541,66”). Plan wymagał siatki „na wąskim ekranie bez poziomego przewijania strony”; przewijania nie ma, ale treść jest nieczytelna. Nad siatką jest też duża pusta przerwa pod podpisem. Wcześniejsze zrzuty nie pokazywały tego stanu (F2).
- **Fix A ⭐ Recommended**: Poniżej `sm` każdy wiersz jako blok: etykieta (Dochody / Wydatki / Odkładanie) na całą szerokość, pod nią siatka 2×2 par „nagłówek: kwota” (nagłówki powtórzone w `table-header` tylko na wąskim ekranie, ukryte od `sm`, jak w wierszach tabeli planu); kwoty `whitespace-nowrap`.
  - Strength: Ten sam wzorzec „jeden markup, układ przez obszary siatki” co `.month-plan-grid` i `.budget-category-grid`; każda kwota ma podpis na każdej szerokości.
  - Tradeoff: Zmiana markupu karty i CSS; trzeba sprawdzić sink i zrobić zrzuty ponownie.
  - Confidence: MED — wzorzec jest w repo, ale karta ma trzy wiersze z różnymi nagłówkami (Odkładanie ma własne).
  - Blind spot: Zgodność z F8 (role tabeli dla czytników ekranu), jeśli też będzie naprawiane.
- **Fix B**: Zostawić 5 kolumn, ale poniżej `sm` pierwsza kolumna na pełny wiersz, kwoty `whitespace-nowrap` w mniejszym stylu, nagłówki skrócone (np. „Zatw.”, „Końc.”, „Fakt.”, „Odch.”) przez osobne klucze.
  - Strength: Mniejsza zmiana markupu.
  - Tradeoff: Skróty gorzej się czytają; 4 kwoty mono przy 390 px nadal są na granicy szerokości (np. „-1 974,55”).
  - Confidence: LOW — przy dłuższych kwotach (dziesiątki tysięcy) dalej może się nie mieścić.
  - Blind spot: Szerokość kwot sześciocyfrowych.
- **Decision**: FIXED via Fix A — `MonthSummaryCard.vue` zbudowany z danych (wiersze Dochody, Wydatki, Odkładanie; każda kwota z podpisem kolumny); `.month-plan-summary-grid` ma 2 kolumny poniżej `sm` i 5 od `sm`, nowe klasy `.month-plan-summary-label` / `-head` / `-cell`; podpisy `sm:sr-only` (czytnik ekranu dostaje podpis przy każdej kwocie, częściowo F8), wiersze nagłówków `hidden sm:grid` z `aria-hidden`; kwoty `whitespace-nowrap`. Etykieta „Odkładanie” przeniesiona do wiersza kwot (jak Dochody i Wydatki). Zrzuty `past-approved-*` zrobione ponownie; przerwa pod podpisem karty zostaje (odstęp `Card`, poza zakresem).

### F4 — `monthStates` na klasie jednego miesiąca, trzecia kopia „miniony”

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Support/MonthToSpend.php:93
- **Detail**: Plan kazał złożyć `month_states` w kontrolerze z `proposal->reading($m)->state` i `monthTiming`. Implementacja dała `MonthToSpend::monthStates(int $year, CarbonImmutable $today)`, czyli odczyt całego roku na klasie jednego miesiąca, z własnym porównaniem `[$year, $month] < [today]`. To trzecia kopia reguły „miniony” obok `MonthPlanProposal::isPast` i `MonthPlanController::monthTiming`. `$year` i `$today` idą osobno, choć proposal już je trzyma. Test obejmuje tylko budżet bieżącego roku (brak przypadku budżetu z poprzedniego lub następnego roku). Wynik jest dziś poprawny.
- **Fix**: Przenieść do `MonthPlanProposal::monthStates()` z użyciem `isPast` i memo; dodać test `month_states` dla budżetu z poprzedniego roku.
- **Decision**: FIXED — `MonthPlanProposal::monthStates()` (rok budżetu i `isPast` z instancji, memo `reading`), kopia z `MonthToSpend` usunięta, kontroler woła `$toSpend->proposal->monthStates()`; nowy test `test_month_states_of_a_past_year_budget_are_summaries_or_missing`. Testy Planu miesiąca na PostgreSQL: 69 zielonych; Sloppy 0; Pint czysty.

### F5 — Nieudokumentowane odstępstwa od kontraktu planu

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/css/app.css (`.month-plan-grid`), resources/js/components/MonthPlanEditBar.vue:41, resources/js/components/MonthPlanStatPanel.vue:85, resources/js/lib/format.ts:35
- **Detail**: (1) Tabela przechodzi na 6 kolumn od `xl`, nie od `lg`. (2) Błąd serwera na pasie to `<p role="alert">` zamiast `InputError`, bo `text-destructive` byłby nieczytelny na `primary`; intencja „czytelny na primary” jest spełniona. (3) Tempo ma ton `text-warning`, gdy wyprzedza. (4) Dodano `formatSignedMoneyAmount`, którego plan nie wymienia, ale bez niego nie da się spełnić „odchylenie bez jednostki”. Każde odstępstwo jest uzasadnione, ale nie jest zapisane w planie ani w commitach.
- **Fix**: Dopisać do planu sekcję „Odstępstwa od planu” z tymi czterema punktami i powodami.
- **Decision**: FIXED — sekcja „Odstępstwa od planu” w `plan.md` (przed `## Progress`): 4 odstępstwa z implementacji i 4 zmiany z triage (F1, F3, F11, F4).

### F6 — Pozostałości: klucz, prop, klasa i nieaktualna reguła

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: lang/pl/ui.php:331, resources/js/pages/MonthPlan.vue:46, resources/css/app.css:499, .ai/rules/js.md:42,56
- **Detail**: `month_plan.to_spend_hint` nie ma konsumenta, a plan kazał takie klucze usunąć. Prop `currentMonth` jest wysyłany przez kontroler, typowany i sprawdzany w `MonthPlanPageTest`, ale nikt go nie czyta (`BudgetMonthPicker` go stracił). `.column-header` nie ma konsumenta, a `js.md:42,56` dalej mówi, że „stosuje go Plan miesiąca”. Lista wariantów Badge w `js.md:42` nie ma `outline` (używanego dla „Końcowy”), a lista klas w `:56` nie ma `.month-plan-header-row` / `-group-row` / `-total-row` / `-footer`.
- **Fix**: Usunąć `to_spend_hint`, `currentMonth` (kontroler, typ, asercja w teście) i `.column-header`; poprawić `js.md:42,56`.
- **Decision**: FIXED — usunięte `month_plan.to_spend_hint`, prop `currentMonth` (oba `Inertia::render` w kontrolerze, typ w `MonthPlan.vue`; test sprawdza `missing('currentMonth')`) i `.column-header`; `js.md:42` (Badge `outline` dla „Końcowy”, nagłówek przez `.month-plan-header-row`), `js.md:56` i `design-system.md:19` wymieniają klasy `.month-plan-*`.

### F7 — Sink kopiuje wiersz tytułu zamiast renderować komponent

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/dev/MonthPlanSink.vue:330
- **Detail**: Sekcja „Wiersz tytułu” powiela markup z `MonthPlan.vue:549-615`, a `js.md` mówi, że sink renderuje prawdziwe komponenty (w tym wiersz tytułu). Obie kopie się rozjadą. Fikstura `allMonthStates` (`month-plan-fixtures.ts:241`) wstawia `null` między propozycje przyszłych miesięcy, czego serwer nie wyprodukuje.
- **Fix**: Wydzielić `MonthPlanTitleRow.vue` (tytuł, odznaka, zdanie stanu, slot akcji) i użyć go w stronie i sinku.
- **Decision**: FIXED — nowy `MonthPlanTitleRow.vue` (propsy `title`, `badge`, `hint`, `headingLevel`; slot `actions`), użyty w `MonthPlan.vue` (akcje i `processing`/`errors` zostają w stronie) i w sinku w 4 stanach (propozycja, zatwierdzony, „Końcowy”, bez planu); dopisany do list `MonthPlanHardcodedUiTest` i `check-ui-literals.mjs`, do `js.md:58` i `design-system.md:96`. `allMonthStates` = sekwencja możliwa z serwera (kwiecień: null, summary×2, approved, proposal×8). Wiersz tytułu na stronie bez zmian wizualnych (zrzut kontrolny).

### F8 — Dostępność: siatka podsumowania, nagłówki, licznik na pasie

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/MonthSummaryCard.vue:46, resources/js/components/MonthPlanEditBar.vue:33
- **Detail**: Podsumowanie przeszło z `<table>` na siatkę `div`, więc czytnik ekranu traci powiązanie kwot z nagłówkami Zatwierdzony / Końcowy / Faktycznie / Odchylenie. `MonthSummaryCard` ma `h3` pod `h1` strony bez `h2` pomiędzy. Licznik błędnych kwot na pasie nie ma `id`, a „Zapisz zmiany” nie ma `aria-describedby` do niego, inaczej niż „Zatwierdź” (`MonthPlan.vue:594`).
- **Fix**: Dodać `role="table"` / `row` / `columnheader` / `rowheader` / `cell` do siatki, zmienić nagłówek na `h2` i powiązać licznik na pasie z „Zapisz zmiany” przez `aria-describedby`.
- **Decision**: FIXED — powiązanie kwot z nagłówkami załatwił F11 (podpis kolumny przy każdej kwocie, `sm:sr-only`; wiersze nagłówków `aria-hidden`), bez ról tabeli; nagłówek `MonthSummaryCard` to `h2`; licznik błędów na pasie ma id z `useId()` (pas renderuje się kilka razy w sinku), a „Zapisz zmiany” ma do niego `aria-describedby`.

### F9 — Kryterium 4.3 nie może przejść w zapisanej formie

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/month-plan-atrament-ui/plan.md:411
- **Detail**: `grep -rn "section-label\|MonthPaceMeter" resources/ tests/` trafia w `tests/Unit/MonthPlanHardcodedUiTest.php:34,88,102`, czyli w sam zakaz i jego przykład. Krok 4.3 jest odhaczony, choć polecenie zwraca wyniki. Kod jest poprawny, błędne jest kryterium.
- **Fix**: W planie dopisać `--exclude=MonthPlanHardcodedUiTest.php` do polecenia z 4.3.
- **Decision**: FIXED — polecenie w `plan.md` (Faza 4, Automated) ma `--exclude=MonthPlanHardcodedUiTest.php`; uruchomione: brak trafień.

### F10 — Zmiana samego odkładania nie da się zapisać (sprzed zmiany)

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/MonthPlan.vue:705, app/Http/Requests/UpdateMonthPlanRequest.php:29
- **Detail**: Problem istniał przed zmianą. W trybie zmiany `canSubmit` wymaga `submittedAmounts.length > 0`, a serwer wymaga `items`, więc samej zmiany kwoty odkładania nie da się zapisać. `set_aside_minor_units` jest zawsze wysyłane, więc nieaktualna karta nadpisze odkładanie. Nowy wiersz odkładania w tabeli ułatwia trafienie na ten przypadek. Poza zakresem tej zmiany.
- **Fix**: Założyć issue (`needs-triage`) na zapis samego odkładania i ochronę przed nieaktualną kartą.
- **Decision**: FIXED (issue) — https://github.com/sebastian-kostecki/household-treasurer/issues/192 (`needs-triage`, `bug`); kod bez zmian, poza zakresem tej zmiany.

## Triage summary (2026-11-05)

| Decision | Findings |
|----------|----------|
| Fixed | F1 (differently: Button `ghost-inverse`), F2 (Fix A), F3 (Fix A), F11 (Fix A), F4, F5, F6, F7, F8, F9 |
| Issue | F10 → #192 |
| Skipped | — |
