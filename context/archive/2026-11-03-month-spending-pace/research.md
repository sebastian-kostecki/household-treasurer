---
date: 2026-11-03T06:28:58+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 61e8faafcde43d7396d1a8aa23147a8ec7e48b38
branch: worktree-month-spending-pace
repository: household-treasurer
topic: "S-05 month-spending-pace: na czym zbudować odczyt Month Pace i Month Elapsed, gdzie go pokazać i których decyzji dokumenty nie rozstrzygają"
tags: [research, codebase, month-plan, month-pace, month-elapsed, year-elapsed, to-spend, running, one-off]
status: complete
last_updated: 2026-11-03
last_updated_by: Claude (claude-opus-5-5)
---

# Research: S-05 — tempo wydatków względem upływu dni miesiąca

**Date**: 2026-11-03T06:28:58+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 61e8faafcde43d7396d1a8aa23147a8ec7e48b38 (lokalny `development`; `origin/development` wskazuje `31b6a19`, więc kotwice `plik:linia` są lokalne, bez permalinków)
**Branch**: worktree-month-spending-pace (z `development` @ `61e8faa`; jedyna zmiana to folder tej zmiany)
**Repository**: household-treasurer

## Research Question

Użytkownik uruchomił `/10x-research month-spending-pace` bez osobnego pytania, a `change.md` ma puste Notes. Pytanie wyprowadzono z roadmapy `context/foundation/roadmap.md:146-156` (S-05: „użytkownik może zobaczyć tempo wydatków względem upływu dni miesiąca”; PRD FR-007; warunek wstępny S-04; ryzyko: „Fałszywe ostrzeżenia uczą ignorowania sygnału, dlatego tempo obejmuje tylko pozycje „bieżące” bez kategorii jednorazowych”).

Pytanie robocze: **jakie definicje i decyzje dla Month Pace i Month Elapsed już obowiązują, z których istniejących odczytów, wzorców i komponentów S-05 może skorzystać, gdzie odczyt może się pojawić, co należy do S-07 i których decyzji dokumenty nie rozstrzygają.** Badanie nie wybiera rozwiązania; to zadanie `/10x-plan`.

## Summary

1. **Definicje są gotowe i nie wymagają nowej decyzji domenowej.**
   - Month Pace (`CONTEXT.md:87-89`): suma kwot faktycznych (samo wykonanie w miesiącu, nie większa z planu i faktycznej) przez sumę planów puli pozycji Running, które nie są kategoriami One-off. Brak odczytu przy sumie planu 0.
   - Month Elapsed (`CONTEXT.md:91-93`): dzień miesiąca daty odniesienia przez liczbę dni miesiąca. Data odniesienia: dziś dla bieżącego, ostatni dzień dla minionego, pierwszy dla przyszłego miesiąca („as Year Elapsed”).
   - F-01 potwierdził jedną pulę i jedno powiadomienie (`context/archive/2026-11-02-month-plan-domain-decisions/plan.md:30-31`). ADR-0033 odrzucił tempo per kategoria (`.docs/adr/0033-month-plan-beside-annual-budget.md:11`).
2. **Powiadomienie `warning` przy ≥ 10 pp należy do S-07, nie do S-05.** ADR-0033 wymienia je wśród czterech powiadomień Dashboardu (`0033…:9`), a S-07 ma S-05 jako warunek wstępny (`roadmap.md:51,176`). S-05 dostarcza odczyt; czy strona pokazuje też własny sygnał przy ≥ 10 pp, dokumenty nie rozstrzygają (Open Questions 2).
3. **Kodu tempa nie ma.** Według grepa agenta po `app resources/js lang tests routes database` (`\b[Pp]ace\b|monthPace|month_pace|MonthPace|[Tt]empo|[Ee]lapsed`) jedyne trafienia to `YearElapsed`/`year_elapsed*`, `TemporaryDirectory` i niezwiązane `$elapsedSeconds` w teście NFR.
4. **Naturalny szew po stronie serwera to `App\Support\MonthToSpend`.** `forMonth` składa plan (propozycja albo zatwierdzony), kwoty faktyczne i zbiór kategorii wierszy (`app/Support/MonthToSpend.php:32-49`), a `rowAmounts` jest wspólne dla liczby i wierszy, „so the two cannot drift apart” (`:77-90`). Pula tempa da się policzyć z tych samych danych bez nowego zapytania.
5. **Month Elapsed ma gotowy wzorzec 1:1.** `App\Support\YearElapsed` to statyczny helper `asOfDate(int, CarbonImmutable $today)` + `percent()` zwracający surowy float 0–100 (`app/Support/YearElapsed.php:9-24`). „Dziś” przekazuje wołający: `CarbonImmutable::today('UTC')` (`app/Http/Controllers/DashboardController.php:31`), strefa aplikacji `UTC` (`config/app.php:68`).
6. **Najbliższe miejsce na odczyt to strona „Plan miesiąca”** obok kafelka „do wydania” (`resources/js/pages/MonthPlan.vue:461-466`). S-01 wybrał tę stronę, żeby „S-04/S-05/S-09 mają gdzie rosnąć” (`context/archive/2026-11-02-month-plan-and-to-spend/plan-brief.md:39`). Dashboard i strona Budżet nie mają w dokumentach miejsca na tempo (Open Questions 1).
7. **Dokumenty nie rozstrzygają sześciu kwestii** (sekcja Open Questions): miejsce odczytu, sygnał na stronie przy ≥ 10 pp, zaokrąglenie a próg S-07, kategorie zarchiwizowane w puli, podgląd na żywo przy edycji planu oraz odczyt dla minionych i przyszłych miesięcy.

## Detailed Findings

### 1. Reguły domenowe, które S-05 musi zachować

- **Month Pace** (`CONTEXT.md:87-89`): procent; licznik to „Execution alone in the month, not the greater of plan and actual”, mianownik to suma planów puli. Polska kopia UI: „tempo”. _Avoid_: Year Elapsed, Year Pace, Month Remaining.
- **Month Elapsed** (`CONTEXT.md:91-93`): „Not an amount.” Polskiej kopii UI wpis nie podaje.
- **Plan i kwota faktyczna** są zdefiniowane przy To Spend (`CONTEXT.md:80`): „Plan means Current Month Plan, or Proposal before approval. Actual amount means Execution of that Category from operations dated in that month (refunds netted)”. Wpis Month Pace mówi tylko „plan amounts”; wniosek (nie zapis w dokumencie): S-05 używa tego samego znaczenia, bo „the month has a plan” obejmuje propozycję (`CONTEXT.md:64`).
- **One-off** (`CONTEXT.md:44`): „is not part of Month Pace”. PRD: „Większy jednorazowy wydatek nie daje ostrzeżenia o tempie, także gdy kategoria ma domyślny rodzaj „bieżąca”” (`context/foundation/prd.md:205`).
- **Month Item Kind** jest niezależny od flagi Paced / One-off (`CONTEXT.md:76`). Pula to więc przecięcie dwóch osi: wydatek ∧ Running ∧ nie One-off.
- **Przykład z F-01** (`context/archive/2026-11-02-month-plan-domain-decisions/plan.md:157`): pula Running bez One-off, plan 1800 (1500 + 300), wydane 750 → 41,7%; 10. dzień 30-dniowego miesiąca (33,3%) → 8,3 pp, bez ostrzeżenia; 9. dzień (30,0%) → 11,7 pp → `warning`. Próg jest włączny („at least”, `CONTEXT.md:88`).
- **Odrzucone**: tempo per kategoria (`.docs/adr/0033-month-plan-beside-annual-budget.md:11`, F-01 `plan.md:79` wg agenta).

### 2. Odczyty miesiąca po S-01…S-06 (szew dla puli tempa)

- `MonthToSpend::forMonth` (`app/Support/MonthToSpend.php:32-49`):
  - `reading` z `MonthPlanProposal::reading($month)`,
  - `actuals` z `MonthCategoryActuals::forMonth`,
  - `categories` = suma id z planu i id z operacjami w miesiącu, w kolejności budżetu. Zbiór obejmuje kategorie zarchiwizowane, jeśli mają operację (wg agenta: test `test_archived_category_with_an_operation_appears_in_categories`).
- `rowAmounts` (`MonthToSpend.php:83-90`): `plan_minor_units` = `reading->amounts[id] ?? 0`, `actual_minor_units` = `actuals->actual(id)`, `has_positive_amount`.
- `toSpendMinorUnits` (`:54-75`) zwraca `null` przy stanie `none` i przekazuje wiersze do `ToSpendCalculator`, który dla Running odejmuje `max(plan, actual)` (`app/Support/ToSpendCalculator.php:32`, wg agenta). Tempo używa samego `actual`, więc nie może korzystać z tego kalkulatora wprost.
- `summary()` (`:95-101`) zwraca `{state, to_spend_minor_units}`; z niego korzystają Dashboard i strona Budżet (wg agenta: `DashboardController.php:99-107`, `BudgetController.php:128-130`).
- `MonthCategoryActuals` (`app/Support/MonthCategoryActuals.php:41-62`): `actual(id)` zwraca sumę ze znakiem (zwroty netto) albo 0. Agregat obejmuje też dochody (wg agenta, `:16-39`), więc filtr puli musi sprawdzać typ.
- Stan planu (`app/Support/MonthPlanProposal.php`, wg agenta): zapisany plan → `approved` z `current_amount_minor_units` (`:93-105`); miniony bez planu → `none` (`:107-109`); inaczej `proposal`, w której One-off ma zawsze 0 (`:150-152`).
- **Flagi kategorii.** One-off = wydatek z `is_paced = false`: `MonthPlanController::itemRow` liczy `$isOneOff = $isExpense && ! $category->is_paced` (`app/Http/Controllers/MonthPlanController.php:232-233`). `month_item_kind` to enum `App\Enums\MonthItemKind` (`Bill`/`Running`, wg agenta `:7-8`), domyślnie `running`.
  - **Pułapka:** kategoria dochodu też zapisuje `is_paced = true` i `month_item_kind = running` (wg agenta; test `tests/Feature/CategoryMonthItemKindTest.php:60` `test_income_category_stores_running_kind`). Predykat puli musi więc jawnie wymagać `type === CategoryType::Expense`.
- Wniosek: pula = kategorie z `MonthToSpend->categories` spełniające `Expense ∧ is_paced ∧ month_item_kind === Running`; tempo = `Σ actual / Σ plan` po tej puli, `null` przy `Σ plan = 0` albo stanie `none`. Kandydaci do dołączenia (`is_join_candidate`) mają plan 0 i faktyczne 0 (wg agenta, `MonthPlanController.php:150-168`), więc nie zmieniają wyniku.

### 3. Year Elapsed jako wzorzec dla Month Elapsed

- `YearElapsed::asOfDate` (`app/Support/YearElapsed.php:9-20`): ten sam rok → `$today->startOfDay()`; rok miniony → 31 grudnia; przyszły → 1 stycznia. `percent()` (`:22-24`) = `100 * dayOfYear / daysInYear`, float bez zaokrąglenia.
- Helper nie czyta zegara. Wołający podaje `CarbonImmutable::today('UTC')` (`DashboardController.php:31`); strefa użytkownika nie istnieje (`config/app.php:68` `'timezone' => 'UTC'`; wg agenta `AppServiceProvider.php:40` `Date::use(CarbonImmutable::class)`).
- Dashboard wysyła `year_elapsed_as_of` (Y-m-d) i `year_elapsed_percent` (`DashboardController.php:136-137`). Etykieta to `t.dashboard.year_elapsed` „Postęp roku” z datą odniesienia (wg agenta: `lang/pl/ui.php:139`, `Dashboard.vue:110-116`).
- Znacznik roku rysuje `CategoryMeterRow.vue` jako kreskę `left: ${yearElapsedPercent}%`, `aria-hidden`, na stałe związaną z Year Elapsed. Nie jest ogólnym propsem znacznika (wg agenta, `resources/js/components/CategoryMeterRow.vue:15-20,107-116`). Komponent ui `Progress` nie istnieje.
- Klasyfikacja przeszły/bieżący/przyszły miesiąc jest dziś zduplikowana co najmniej w trzech miejscach (wg agenta): `MonthPlanController::monthTiming` (~`:276-286`), `MonthRemainingCalculator::monthTiming` (~`:127-145`), `MonthPlanProposal::isPast` (`:126-128`); podobny warunek ma `ChangeMonthPlan.php:36`. Żadne z nich nie zwraca daty odniesienia. Month Elapsed potrzebuje własnego `asOfDate` (dzień dzisiejszy / `endOfMonth()` / pierwszy dzień).
- Testy wzorca: `tests/Unit/YearElapsedTest.php` (Pest, wstrzyknięta data, 7 testów, w tym rok przestępny) i `tests/Feature/DashboardTest.php:409` `test_dashboard_exposes_year_elapsed_for_the_selected_budget_year` (wg agenta). Konwencja zamrażania czasu w testach funkcjonalnych to `$this->travelTo(...)` (wg agenta, m.in. `MonthPlanPageTest.php:25`).

### 4. Gdzie odczyt może się pojawić

- **Strona „Plan miesiąca”**: `MonthPlanController::show` wysyła `budget`, `viewedMonth`, `viewedMonthTiming`, `currentMonth`, `monthPlan` (wg agenta, `:31-71`). Kafelek „do wydania” to `<Card>` z `t.month_plan.to_spend` (`resources/js/pages/MonthPlan.vue:461-466`). Strona ma podgląd „do wydania” na żywo z kopii TS kalkulatora (wg agenta, `MonthPlan.vue:259-279`, `resources/js/lib/to-spend-calculator.ts`).
- **Dashboard**: kafelek `month_to_spend` (wg agenta, `Dashboard.vue:252-290`), siatka 2 lub 3 kolumn (`headlineTilesClass`, `:122-126`). Spec Dashboardu wyklucza „month grids, or a second Budget workspace on Dashboard” (`.docs/specs/dashboard-hierarchy.md:76`, wg agenta); osobnego kafelka tempa nie przewiduje.
- **Strona Budżet**: `BudgetMonthStrip.vue` z blokiem „do wydania” (wg agenta, `:64-112`).
- Historia: S-04 celowo nie dodał procentu realizacji dla pozycji bieżących, bo „Tempo to S-05, a ADR-0033 odrzucił tempo per kategoria” (`context/archive/2026-11-02-month-items-plan-vs-actual/plan.md:54`), a „procent dla bieżących dublowałby tempo S-05” (`…/plan-brief.md:22`).

### 5. Prezentacja: kolory, procenty, komponenty

- Kolory (`.docs/design-system.md:27-30`): `primary` = „pasek realizacji w normie”; `destructive` = przekroczenie, nie do „ostrzeżeń, które jeszcze nie są przekroczeniem”; `warning` (bursztyn) = „w granicach, ale blisko przekroczenia”. Tylko tokeny, nie klasy palety (`.ai/rules/js.md:10`, wg agenta; `design-system.md:36`).
- Wniosek: tempo powyżej upływu dni to sygnał `warning`, nie `destructive`, bo plan jeszcze nie jest przekroczony; przekroczenie pozycji ma osobny predykat „pozycja ponad plan” (`CONTEXT.md:80`).
- Konwencja procentu: `CategoryExecutionMetrics::percentComplete` zwraca `null` przy planie 0, inaczej `(int) round(execution / plan * 100)` (`app/Support/CategoryExecutionMetrics.php:12-19`). Year Elapsed zwraca float (`YearElapsed.php:22-24`). To dwie różne konwencje; wybór wpływa na próg S-07 (Open Questions 3).
- Gotowe elementy przed nowym markupem: `ListSurface`, `CategoryChip`, `CategoryMeterRow`, `Heading` i inne (`.docs/design-system.md:55`).
- `design-system.md:47` mówi, że żaden alert nie emituje jeszcze `warning`; to nadal prawda w kodzie (wg agenta: wszystkie rodzaje w `app/Support/DashboardNotices.php:61,74,96,148` mają `Exceeded`). Dotyczy S-07.

### 6. Reguły repozytorium dla tej zmiany

- `AGENTS.md:40`: „Compute new month readings on the server and pass them as props; add a TS copy only when a preview must react before submit, and state the reason in the plan.”
- `AGENTS.md:36` (wg agenta): typ propsów PHP i TS w jednym commicie, z `assertInertia` w teście funkcjonalnym.
- `AGENTS.md:10` (wg agenta): kwoty to całkowite Minor units, bez `float`. Tempo i upływ dni to proporcje, nie kwoty, ale sumy puli pozostają całkowite.
- `.ai/rules/support.md:10`: nowy rodzaj `warning` wymaga terminu i progów w `CONTEXT.md` przed kodem. Termin i próg już są (`CONTEXT.md:87-93`); dotyczy S-07.
- Testy dat na PostgreSQL (wg agenta, `AGENTS.md:14-22`). Spec Dashboardu: zamrażać czas tam, gdzie liczy się miesiąc, i nie sprawdzać wnętrza Vue, Tailwinda ani zrzutów (`.docs/specs/dashboard-hierarchy.md:65`, wg agenta).
- `CLAUDE.md`: praca nad interfejsem widoku, który już się renderuje, zaczyna się od `/10x-ui <view>`. S-05 dodaje odczyt do istniejącej strony „Plan miesiąca”, więc część wizualna może przejść przez `/10x-ui` (kontrakt systemu projektowego, matryca stanów, bramka zrzutu ekranu).
- Snapshot: tempo i upływ dni są wyliczane, nie zapisywane, więc snapshot `user:backup` / `user:restore` ich nie dotyczy. Wpis Month Pace nie mówi wprost „Not stored” (w odróżnieniu od To Spend, `CONTEXT.md:80`), ale nie ma też żadnego pola do zapisania.

### 7. Gotowy przypadek testowy na fixturze kanonicznej

- `tests/Feature/MonthToSpendTest.php:97-126` (`travelTo('2026-06-15')` wg agenta):
  - Jedzenie: wydatek Running Paced, plan roczny 1 800 000, operacja 60 000;
  - Paliwo: wydatek Running Paced, plan roczny 480 000, operacja 52 000;
  - Prezent: One-off, operacja 30 000;
  - Czynsz: Bill, operacja 195 000;
  - Pensja: dochód.
- Wniosek (obliczenie własne, w testach jeszcze nie sprawdzane): przy propozycji czerwca bez wcześniejszego planu kwoty to średnia roczna (ADR-0033 `:5`), czyli 150 000 i 40 000. Pula = Jedzenie + Paliwo:
  - tempo = 112 000 / 190 000 ≈ 58,95%,
  - upływ 15 czerwca = 15/30 = 50%,
  - różnica ≈ 8,9 pp, bez ostrzeżenia.
  Czynsz (Bill), Prezent (One-off) i Pensja (dochód) nie wchodzą do puli. Gdyby weszły, wynik byłby inny, więc fixtura sprawdza wszystkie trzy wykluczenia.
- Przykład z F-01 (sekcja 1) to drugi gotowy przypadek, z granicą 8,3 pp / 11,7 pp.

## Code References

- `app/Support/MonthToSpend.php:32-49` — `forMonth`: plan, kwoty faktyczne, zbiór kategorii wierszy
- `app/Support/MonthToSpend.php:83-90` — `rowAmounts`, wspólne dla liczby i wierszy
- `app/Support/MonthToSpend.php:95-101` — `summary()` dla Dashboardu i strony Budżet
- `app/Support/MonthCategoryActuals.php:41-62` — `actual`, `hasPositiveAmount`, `categoryIds`
- `app/Support/ToSpendCalculator.php:32` — Running jako `max(plan, actual)` (nie dla tempa; wg agenta)
- `app/Support/YearElapsed.php:9-24` — wzorzec daty odniesienia i procentu
- `app/Support/CategoryExecutionMetrics.php:12-19` — konwencja procentu całkowitego, `null` przy planie 0
- `app/Http/Controllers/MonthPlanController.php:232-233` — predykat One-off (`isExpense && ! is_paced`)
- `app/Http/Controllers/DashboardController.php:31,132,136-137` — `today('UTC')`, `current_month`, propsy Year Elapsed
- `config/app.php:68` — strefa `UTC`
- `resources/js/pages/MonthPlan.vue:461-466` — kafelek „do wydania”
- `resources/js/components/CategoryMeterRow.vue:107-116` — znacznik Year Elapsed (wg agenta)
- `tests/Feature/MonthToSpendTest.php:97-126` — fixtura kanoniczna
- `tests/Unit/YearElapsedTest.php` — wzorzec testu jednostkowego (wg agenta)
- `CONTEXT.md:80,87-93` — To Spend, Month Pace, Month Elapsed
- `.docs/adr/0033-month-plan-beside-annual-budget.md:9,11` — powiadomienie `warning`, odrzucone tempo per kategoria
- `.docs/design-system.md:27-30,47,55` — znaczenie kolorów, `warning`, gotowe elementy

## Architecture Insights

- Odczyty miesiąca to czyste klasy w `app/Support` składane w kontrolerze w płaskie propsy. `MonthToSpend` ma być jedynym źródłem dla każdej powierzchni z „do wydania” (docblock `MonthToSpend.php:10-16`). Tempo liczone w tej samej klasie (albo w klasie, która ją przyjmuje) korzysta z tych samych `rowAmounts` i trafia na każdą powierzchnię razem z liczbą. S-07 może wtedy użyć tego samego odczytu do powiadomienia.
- Month Elapsed pasuje do kształtu `YearElapsed` (statyczny helper, wstrzyknięte „dziś”, testy jednostkowe bez zegara). Zduplikowana klasyfikacja miesiąca (sekcja 3) to istniejący dług; S-05 nie musi go spłacać, ale nie powinien dodawać czwartej kopii bez decyzji w planie.
- Podgląd „do wydania” na stronie działa w TS. Jeśli tempo ma reagować na wpisywane kwoty planu przed zapisem, potrzebna jest para PHP/TS z testami w obu miejscach (`AGENTS.md:40`); bez tego tempo jest odczytem tylko z serwera i zmienia się po przeładowaniu Inertia.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:30-31` — jedna pula Running bez One-off, jedno powiadomienie; upływ dni z dzisiejszym dniem włącznie. Poparte: tak brzmi `CONTEXT.md:87-93`.
- `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:157` — przykład 41,7% / 8,3 pp / 11,7 pp. Poparte jako definicja; w kodzie nie ma testu (kodu tempa nie ma).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:78` — „tempo: to S-04 i S-05”. Częściowo: S-04 wykluczył tempo i przekazał je S-05 (`month-items-plan-vs-actual/plan.md:54`).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan-brief.md:39` — strona „Plan miesiąca” jako miejsce, gdzie S-05 „ma gdzie rosnąć”. Poparte: strona istnieje i ma kafelek „do wydania” (`MonthPlan.vue:461-466`).
- `context/archive/2026-11-02-month-items-plan-vs-actual/plan-brief.md:22` — pozycja bieżąca bez znacznika realizacji, bo procent „dublowałby tempo S-05”. Wniosek: S-05 nie powinien dodawać procentu per wiersz.
- `context/archive/2026-11-02-month-items-plan-vs-actual/research.md:121` — S-05, S-07 i S-09 mogą korzystać z predykatów liczonych na serwerze w tym samym miejscu. Poparte: `MonthToSpend` jest dziś tym miejscem (`MonthToSpend.php:10-16`).

## Related Research

- `context/archive/2026-11-02-month-items-plan-vs-actual/research.md` — strona „Plan miesiąca” po S-01, odczyty miesiąca, kategorie One-off.
- `context/archive/2026-11-02-month-plan-and-to-spend/research.md` — stan kodu przed S-01.
- `context/archive/2026-11-02-to-spend-replaces-remaining/research.md` — „do wydania” na Dashboardzie i stronie Budżet, powiadomienie `paced_month_remaining` (S-07).

## Open Questions

Decyzje produktowe do rozstrzygnięcia w `/10x-plan`. Dokumenty ich nie zamykają.

1. **Gdzie pokazać tempo.** Tylko strona „Plan miesiąca” (obok kafelka „do wydania”, `MonthPlan.vue:461-466`), czy także Dashboard (moment „przegląd co kilka dni”, `prd.md:58`) i strona Budżet? Spec Dashboardu nie przewiduje kafelka tempa (`dashboard-hierarchy.md:76`, wg agenta). Forma: liczba z upływem dni („tempo 59% · 15 z 30 dni”), pasek ze znacznikiem jak Year Elapsed, czy oba. `CategoryMeterRow` ma znacznik na stałe związany z Year Elapsed (sekcja 3).
2. **Sygnał na stronie przy ≥ 10 pp.** FR-007 mówi o „ostrzeżeniu” (`prd.md:117`), a powiadomienie Dashboardu to S-07 (ADR-0033 `:9`). Czy S-05 koloruje odczyt `text-warning` przy tym samym progu, czy pokazuje go neutralnie, a ostrzeżenie zostawia S-07?
3. **Zaokrąglenie a próg.** `percentComplete` zaokrągla do liczby całkowitej, `YearElapsed` zwraca float. Przy różnych konwencjach wynik progu może się różnić (np. tempo 39,6% → 40, upływ 30%: surowo 9,6 pp bez ostrzeżenia, po zaokrągleniu 10 pp z ostrzeżeniem). Plan powinien ustalić, że porównanie idzie na wartościach surowych (albo na Minor units przez mnożenie krzyżowe), a zaokrąglenie dotyczy tylko wyświetlania, i S-07 przejmie ten sam predykat.
4. **Kategorie zarchiwizowane w puli.** Zarchiwizowana kategoria Running z operacją w miesiącu, ale bez planu, jest w `MonthToSpend->categories` z planem 0 (wg agenta) i „do wydania” ją liczy. W puli tempa podniosłaby licznik bez mianownika. `CONTEXT.md:87-89` tego nie rozstrzyga; spójność z „do wydania” sugeruje włączenie.
5. **Podgląd na żywo.** Czy tempo ma się zmieniać podczas edycji propozycji lub zmiany planu przed zapisem, jak podgląd „do wydania”? To wymaga kopii TS i uzasadnienia w planie (`AGENTS.md:40`).
6. **Miniony i przyszły miesiąc.**
   - Miniony z planem: upływ 100%, tempo jest odczytem końcowym. Czy pokazywać go do czasu S-09 (podsumowanie)?
   - Miniony bez planu (`state = none`): tempa nie ma, bo nie ma planu.
   - Przyszły: upływ 1/n, a kwoty faktyczne to 0, bo operacje nie mogą mieć przyszłej daty (wg agenta S-04, `ExpenseValidationRules.php:24,129-140`). Czy pokazywać „0% · 1 z n dni”, czy ukryć?
   - Polskiej kopii dla Month Elapsed nie ma (`CONTEXT.md:91-93`); trzeba ją dodać razem z kluczem w `lang/pl/ui.php` i typem `UiTranslations`.

Luki pokrycia:
- Nie czytano szczegółowo `BudgetController::categoryPayload`, dolnej części `MonthPlanItemRow.vue` ani asercji `DashboardTest.php` poza testem Year Elapsed.
- Linie w środku `MonthPlanController` (`monthTiming` ~`:276-286`) i `MonthRemainingCalculator` (~`:127-145`) są przybliżone (wg agenta).
- Kotwice oznaczone „wg agenta” pochodzą z raportów agentów. Osobiście sprawdzono:
  - kod: `MonthToSpend.php:1-101`, `YearElapsed.php:1-26`, `CategoryExecutionMetrics.php:7-25`, `MonthCategoryActuals.php:41-62`, `MonthPlanController.php:228-240`, `DashboardController.php:31,132,136-137`, `config/app.php:68`, `MonthToSpendTest.php:95-130`, położenie kafelka w `MonthPlan.vue:461-466`;
  - dokumenty: `CONTEXT.md:62-96`, cały ADR-0033, `.docs/design-system.md:20-55`, `support.md:10`, `AGENTS.md:40`;
  - archiwum: F-01 `plan.md:28-32,155-158`, S-04 `plan.md:54` i `plan-brief.md:22`, S-01 `plan-brief.md:39` i `plan.md:78`.
