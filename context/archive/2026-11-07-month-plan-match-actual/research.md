---
date: 2026-11-07T22:50:00+01:00
researcher: Claude (Sonnet 5.5), z trzema pomocnikami tylko do odczytu
git_commit: a10e35c78c9e022ed6706e172ad08412cbb3b437
branch: development
repository: household-treasurer
topic: "Plan = wydatki w trybie zmian Planu miesiąca (przycisk + automatyczne podniesienie)"
tags: [research, codebase, month-plan, MonthPlanItemRow, MonthPlan, ToSpend, glossary, adr]
status: partial
last_updated: 2026-11-07
last_updated_by: Claude (Sonnet 5.5)
---

# Research: plan = wydatki w trybie zmian Planu miesiąca

**Date**: 2026-11-07T22:50:00+01:00
**Researcher**: Claude (Sonnet 5.5)
**Git Commit**: a10e35c78c9e022ed6706e172ad08412cbb3b437
**Branch**: development
**Repository**: household-treasurer

## Research Question

W trybie zmian Planu miesiąca ma być łatwo ustawić plan pozycji na obecną sumę wydatków. Decyzja użytkownika (to nie jest pytanie badawcze): przycisk „plan = wydatki” w wierszu ORAZ automatyczne podniesienie planu do wydatków, gdy w trybie zmian wydatki pozycji przekraczają plan, z ikoną potwierdzającą w wierszu. Wszystko widoczne przed zapisem, nic nie zapisuje się samo. To zmiana reguły domenowej, więc najpierw glosariusz i nowy ADR (AGENTS.md, „Hard rules”), potem kod. Siedem pytań do ustalenia jest w treści zlecenia; poniżej odpowiedzi w tej samej kolejności.

## Summary

- **Nic po stronie serwera nie musi się zmienić.** Obecne endpointy zmiany i zatwierdzenia przyjmują dowolną kwotę 0…1 000 000 000 000 j. mn. i nie porównują jej z wydatkami ani z poprzednim planem (`app/Http/Requests/UpdateMonthPlanRequest.php:306-310`, bez reguły porównawczej). Wszystkie potrzebne pola już są w propsach pozycji (`MonthPlanController.php:245-269`). Nowy prop nie jest potrzebny, więc nie ma obowiązku `assertInertia` z reguły `.ai/rules/inertia-props.md` (dotyczy zmiany propów).
- **„Wydatki” = `actual_minor_units`**: suma netto (ze znakiem) `amount_minor_units` operacji finansowych budżetu z danego miesiąca kalendarzowego dla kategorii; zwroty (kwoty ujemne) pomniejszają sumę; oczekiwania gotówkowe, przelewy własne i ruchy kapitału nie wchodzą (`MonthCategoryActuals.php:36-42`).
- **Zmiana reguły jest realna.** To Spend już liczy pozycję Running jako `max(plan, wydatki)` i pozycję Bill jako wydatki po pierwszej dodatniej operacji (`ToSpendCalculator.php:76-80`), więc podniesienie planu do wydatków **nie zmienia To Spend** w tych przypadkach. Zmienia za to: odchylenie (do 0), znacznik „ponad plan” i plakietkę statusu, tempo (Pace) oraz — po zapisie — powiadomienie Dashboardu `month_item_over_plan` (poziom `exceeded`), które dziś mówi, że „przekroczenie to już fakt” (`DashboardNotices.php:122-160`, `prd.md:132`, ADR-0033). Do ADR trzeba wpisać, że świadome podniesienie planu przekreśla ten sygnał dla danej pozycji.
- **Obecny kod ma jedno miejsce odczytu kwoty planu w trybie zmian** (`parsedAmount`, `MonthPlan.vue:156`), z którego korzystają To Spend, Pace, odchylenie, status, strzałka i wysyłka. Pole wejściowe trzyma jednak tekst wpisany przez użytkownika. Dlatego najprostszy punkt zaczepienia to **jednorazowe wpisanie wartości do `amountInputs` przy wejściu w tryb zmian** (`startChanging`, `MonthPlan.vue:133`) i ta sama czysta funkcja pod przyciskiem.
- **UI:** w wierszu planu nie ma dziś przycisku ani miejsca na ikonę; rachunek szerokości (mój, niezmierzony) mówi, że poniżej `xl` ikona nie zmieści się obok pola. Nie ma tokenu na „plan podniesiony automatycznie”; ADR-0060 daje strzałkę w kolumnie „Zatwierdzony”, która dla wydatku ze wzrostem planu już świeci `destructive`.
- **Zależność od `ui-fixes-2` jest spełniona:** zmiana jest zamknięta (`a49c8c6 chore(archive): close ui-fixes-2`), ADR-0060 i kolumna „Zatwierdzony” są na `development`. Archiwalny plan sam odkłada „Plan = wydatki” do osobnej zmiany z glosariuszem i ADR (`context/archive/2026-11-07-ui-fixes-2/plan.md:67`). Najbliższy wolny numer ADR to 0062 (ostatni plik to `0061-wide-screen-columns.md`; numery 0025-0028, 0036 i 0046 w katalogu nie występują).

## Detailed Findings

### 1. Co znaczy „wydatki” pozycji

- Prop pozycji `actual_minor_units` pochodzi z `MonthPlanController::itemRow()` (`app/Http/Controllers/MonthPlanController.php:270-314`, przypisanie w :288) z `MonthToSpend::rowAmounts()` (`app/Support/MonthToSpend.php:206-213`) i `MonthCategoryActuals::actual($id)` (domyślnie 0).
- Zapytanie: `FinancialOperation` budżetu z `operation_date` w przedziale od 1. dnia miesiąca do 1. dnia następnego, grupowane po `category_id` i dacie, `SUM(amount_minor_units)` oraz liczba rekordów z kwotą > 0 (`app/Support/MonthCategoryActuals.php:36-42`). To jest ścieżka sprawdzona tylko dla jednego miesiąca widoku.
- Wartość jest ze znakiem. Zwrot wydatku to kwota ujemna (import: `'refund' => -abs(...)`, `app/Actions/ImportFinancialOperations.php:252`; glosariusz, wpis „Expense refund”), więc pomniejsza sumę. Wynik netto może być ujemny (sam zwrot); reguła walidacji kwoty planu to `min:0`, więc przycisk dla ujemnych wydatków nie może wpisać wartości dosłownie (limit do 0 albo wyłączenie, do decyzji w planie).
- Zapytanie nie filtruje po typie ani po zarchiwizowaniu: kategorie dochodów też dostają `actual` (suma wpływów), a kategorie zarchiwizowane są w zapytaniu. Zbiór wierszy to kategorie z planu plus kategorie z jakąkolwiek operacją w miesiącu (`MonthToSpend.php:151-159`); w trybie zmian dochodzą nieobjęte planem, niezarchiwizowane kategorie jako kandydaci do dołączenia (`MonthPlanController.php:169-184`).
- Rachunek (Bill) nie zmienia znaczenia `actual`: różnica jest w regule To Spend, nie w zapytaniu. Nieopłacony Bill w podsumowaniu zamkniętego miesiąca liczy się jako minus plan (glosariusz, wpis „Month Summary”) i to dotyczy tylko widoku podsumowania, nie edycji.
- Operacje wprowadzane ręcznie mają `operation_date` z regułą `before_or_equal:today` (`app/Concerns/ExpenseValidationRules.php:24,67`), więc dla ręcznie dodanych wydatków miesiąc przyszły ma `actual` 0. **Luka:** nie sprawdziłem, czy ścieżka importu dopuszcza daty przyszłe.
- Dokładny kształt pozycji (to jedyny kontrakt klienta): `category_id, name, icon, color, type ('income'|'expense'), month_item_kind (null dla dochodów), is_one_off, is_archived, plan_minor_units, actual_minor_units, has_positive_amount, can_change_kind, in_plan, deviation_minor_units|null, is_over_plan, is_settled, available_limit_minor_units|null, is_idle_one_off, approved_minor_units|null, is_join_candidate, year_comparison|null` (`MonthPlanController.php:245-269`; typ `MonthPlanItem` w `resources/js/types/month-plan.ts`).

### 2. Których pozycji to dotyczy

Wnioski wynikają z kodu, ale granice zakresu to **propozycja do ADR**, nie ustalony fakt.

- **Wydatki (expense): tak.** Dla pozycji Running To Spend używa `max(plan, actual)`, więc podniesienie planu do `actual` jest dla To Spend neutralne, a podniesienie powyżej `actual` obniża To Spend (`ToSpendCalculator.php:76-80`, kopia `resources/js/lib/to-spend-calculator.ts:104-133`). Dla Bill po pierwszej dodatniej operacji liczą się wydatki, więc plan nie wpływa na To Spend; przed nią `actual` jest ≤ 0 i nie ma czego podnosić.
- **Dochody: nie, powód z kodu.** „Ponad plan” to wyłącznie wydatek (`MonthItemDeviation.php:46-49`: `type === Expense && actual > plan`); dochód powyżej planu jest dobrą wiadomością (`text-success` w kolumnie odchylenia, `MonthPlanItemRow.vue:69-71`), a To Spend liczy dochód po pierwszym wpływie z `actual` niezależnie od planu. Podniesienie planu dochodu do wpływów nie ma więc sygnału do zgaszenia. Proponowana reguła: tylko kategorie wydatków.
- **Odkładanie (Set-aside): nie.** To osobne pole, nie pozycja (`set_aside_minor_units` na poziomie odczytu, `MonthPlanController.php:215`; osobne pole formularza `MonthPlan.vue` ~:636), bez `actual`.
- **Pozycja z planem 0 i wydatkami > 0:** jest „ponad plan” (`isOverPlan` bez progu, ADR-0033; `DashboardNotices.php` bierze „każdy rozmiar, także plan 0”). Pozycja w planie z kwotą 0 aktualizuje się normalnie; pozycja spoza planu (kandydat do dołączenia) z kwotą 0 jest pomijana, a z kwotą > 0 dołącza z `approved` 0 (`ChangeMonthPlan.php:251-259`).
- **Kategoria jednorazowa (One-off):** nie ma w kodzie wyjątku od `isOverPlan`; Proposal zaczyna od 0 (`MonthPlanProposal.php:174-176`), więc pierwszy wydatek na niej od razu jest „ponad plan”. Wypada więc w zakresie funkcji. To samo dotyczy Paced bez osobnej obsługi w wierszu.
- **Pozycja zarchiwizowana:** pole kwoty jest tylko dla `in_plan || !is_archived` (`MonthPlanItemRow.vue:46-48`, `MonthPlan.vue:66`); serwer dopuszcza zarchiwizowaną kategorię tylko, jeśli ma już pozycję (`ChangeMonthPlan.php:227-237`). Dla wiersza bez pola nie ma co podnosić. Powiadomienie Dashboardu pomija kategorie zarchiwizowane (`DashboardNotices.php` pętla z `isArchived()` → `continue`).
- **Miesiące:** zmiana jest możliwa tylko dla planu zatwierdzonego i miesiąca nie-przeszłego (`$canChange = state === 'approved' && timing !== 'past'`, `MonthPlanController.php:164`; serwer odrzuca miesiąc wcześniejszy niż bieżący, `ChangeMonthPlan.php:205-207`). Miesiąc przeszły jest widokiem tylko do odczytu. Miesiąc przyszły jest edytowalny i ma `actual` zwykle 0, więc funkcja go w praktyce nie dotknie (por. luka o imporcie w pkt 1).
- **Pozycje „kandydaci do dołączenia”:** widoczne tylko w trybie zmian (`MonthPlan.vue:54-58`). Ich `plan_minor_units` to 0, a `actual` może być > 0 — czy je podnosić automatycznie, to pytanie otwarte (pkt „Open Questions”).

### 3. Stan Planu: Proposal a Approved

- **Zatwierdzony plan miesiąca (Approved) jest niezmienny; zmienia się tylko Bieżący.** `ChangeMonthPlan` zapisuje tylko `current_amount_minor_units` istniejących pozycji i `current_set_aside_minor_units` (`app/Actions/ChangeMonthPlan.php:222,246`); kolumna `approved_amount_minor_units` nowej, dołączanej pozycji dostaje 0 (:254), a docblock deklaruje, że `approved` istniejącej pozycji nie jest nigdy zapisywane (:186). Sprawdziłem to tylko w tej akcji; nie szukałem innych miejsc zapisu tych kolumn poza akcjami (np. seedery, przywracanie).
- **Propozycja (Proposal) nie jest zapisywana.** Jest liczona na żywo (`MonthPlanProposal::computePlan`, `:107-148`); jedyny zapis dla miesiąca bez zatwierdzonego planu to `ApproveMonthPlan`, który wymaga dokładnie zestawu kategorii z propozycji (:130-137) i ustawia `approved = current = kwota` (:141-156). Potwierdza to regułę z `.ai/rules/month-calculators.md` („a proposal cannot be saved before approval”).
- **Tryb zmian istnieje tylko dla planu zatwierdzonego.** W stanie `proposal` pola są od razu edytowalne (`isEditable` w `MonthPlan.vue:72-77` jest prawdziwe, gdy `state === 'proposal' && can_approve`), a przycisku „Zmień” nie ma (`monthPlan.can_change && !isChanging`, `MonthPlan.vue:597-605`). Sformułowanie użytkownika „w trybie zmian” oznacza zatem Approved; czy podnoszenie ma działać także w propozycji, to pytanie otwarte.
- **Walidacja kwoty:** `items.*.amount_minor_units` jest `required|integer|min:0|max:1e12`; `set_aside_minor_units` `integer`, od −1e12 do 1e12; zmiana nie wymaga wszystkich kategorii, zatwierdzenie tak (`UpdateMonthPlanRequest.php:306-310`; `ApproveMonthPlanRequest` analogicznie). Autoryzacja: `BudgetPolicy::manageMonthPlan` (budżet użytkownika w realizacji, `app/Policies/BudgetPolicy.php:41-44`).

### 4. Gdzie żyje tryb zmian i gdzie wpiąć podniesienie

Stan jest w stronie `resources/js/pages/MonthPlan.vue`, nie w komponentach ani composables (sprawdzone: katalog `composables` nie ma niczego dla planu miesiąca).

- `isChanging = ref(false)` (:52); `startChanging()` (:133) ustawia flagę, przebudowuje `amountInputs` z propsów i zeruje „dotknięte” pola; `stopChanging()` (:139) robi to samo z flagą false. Po udanym zapisie `<Form @success="stopChanging">` (:551), serwer przekierowuje na `month-plan`, więc propsy są świeże (`MonthPlanController.php:105-131`).
- Wartości pól to **teksty** w `amountInputs: Record<number,string>` (:88), początkowo `minorUnitsToMoneyInput(row.plan_minor_units)` (:79-86). To `<Form>` Inertii, nie `useForm` (:546). Parsowanie: `parsedAmount(row)` (:156) → `parsePlanAmountInput` (0…1e12 j. mn. albo `null`).
- `parsedPlanAmounts` oznacza wiersz jako `changed`, gdy `amount !== row.plan_minor_units` (:175-186). W trybie zmian wysyłane są tylko wiersze `changed` (`submittedAmounts`, :191). Przycisk zapisu jest wyłączony, gdy `submittedAmounts.length === 0` albo któraś kwota jest nieczytelna (`:can-submit`, :697). Konsekwencja: **wiersz podniesiony automatycznie od razu liczy się jako zmiana i odblokowuje „Zapisz zmiany”**, bez żadnego działania użytkownika; nadal nic nie jest zapisane, dopóki użytkownik nie kliknie (spełnia warunek „nic nie zapisuje się samo”).
- Przepływ jednej zmiany: `<input v-model>` w `MonthPlanItemRow.vue:191-201` → `MonthPlanTable.vue` (`update-amount(categoryId, value)`, `amount-blur(categoryId)`) → `updateAmount` w stronie (:497: `amountInputs.value[categoryId] = value`) → każdy odczyt reaktywnie woła `parsedAmount`. `rowState(row)` (:441) jest propem funkcyjnym wołanym przy renderze i zwraca `planMinorUnits`, `deviation`, `overPlan`, `status`, `yearStale`, `invalid`.
- Anulowanie: `MonthPlanEditBar` emituje `cancel` (`MonthPlanEditBar.vue:255-257`), co woła `stopChanging` i przywraca pola z propsów; **zapowiedź podniesienia przepada razem z anulowaniem**. Zauważone przy okazji (nieweryfikowane w przeglądarce): ani `startChanging`, ani `stopChanging` nie resetuje pola odkładania (`setAsideInput`) — niezwiązane z tą funkcją.
- Zmiana rodzaju pozycji (Bill/Running) to osobny `router.patch` (`MonthPlan.vue:~361`) z `preserveState`; propsy się odświeżają, `isChanging` zostaje, a `amountInputs` nie jest przebudowywane. Nowe operacje z innej karty nie dojdą do pól w trakcie edycji.

Warianty punktu zaczepienia i skutki (z kodu; wybór należy do planu i ADR):

| Wariant | Skutek w obecnym kodzie |
| --- | --- |
| Jednorazowo w `startChanging` (wpisanie `minorUnitsToMoneyInput(actual)` do `amountInputs` dla wierszy z `actual > plan`) | Pole trzyma wartość, wiersz jest `changed`, wysyłka i strzałka działają bez zmian. Ręczne obniżenie zostaje (nic nie nadpisuje). Anuluj zrzuca podniesienie. Późny przypływ `actual` nie jest uwzględniany. |
| Przy każdym `updateAmount` | Uniemożliwia obniżenie planu poniżej wydatków (każde wpisanie jest nadpisywane). Sprzeczne z „decyzją użytkownika” o możliwości ręcznej zmiany. |
| Przy blur (`touchAmount`) | Obniżenie poniżej wydatków jest cofane przy opuszczeniu pola. |
| Obserwator propsów/wydatków | Wymaga nowego obserwatora obok istniejących (:114-131); odpowiada na późny przypływ `actual`, ale nadpisywałby ręczną decyzję, jeśli wywoływany po edycji. |
| Warstwa „efektywnego planu” w `parsedAmount` | Zasila wszystkie odczyty, ale pole nadal pokazuje wpisany tekst, więc użytkownik nie widzi, że liczy się inna kwota. Odpada. |

Marker „plan podniesiony do wydatków” może być **wyprowadzony bez nowego stanu**: wiersz wydatku, w którym `row.actual_minor_units > row.plan_minor_units` i `parsedAmount(row) === row.actual_minor_units`. Wtedy ten sam znacznik pokazuje się po automacie i po kliknięciu przycisku, a znika po ręcznej zmianie. Alternatywa to zbiór id podniesionych wierszy w stanie strony (wymaga nowego propa/emit w `MonthPlanTable`).

**Propozycja reguły do ADR (wynika z powyższego, do potwierdzenia):** (1) podniesienie następuje raz, przy wejściu w tryb zmian, dla wierszy wydatków z polem kwoty, w których wydatki przekraczają bieżący plan; (2) przycisk „plan = wydatki” w wierszu wpisuje `max(0, wydatki)` do pola w dowolnym momencie trybu zmian; (3) ręczne obniżenie planu poniżej wydatków jest decyzją użytkownika i żaden mechanizm jej nie cofa przed zapisem; (4) Zatwierdzony plan nie zmienia się nigdy, zmienia się tylko Bieżący, i dopiero po „Zapisz zmiany”; (5) Anuluj zrzuca podniesienia.

### 5. Wpływ na podgląd na żywo

Ponieważ `isEditable` jest prawdziwe w trybie zmian, następujące odczyty zasilają się z pól i same podchwycą podniesiony plan (strona: `MonthPlan.vue`; pliki lib wskazane w nawiasach):

- **To Spend i jego dwa składniki** (`toSpendReading`, :252-280; `lib/to-spend-calculator.ts`), pokazywane w `MonthPlanStatPanel`. Plan = `parsedAmount`, wydatki = `actual_minor_units`, `hasPositiveAmount = has_positive_amount`. Skutek podniesienia do `actual`: dla Running bez zmian (`max`), dla Bill bez zmian.
- **Tempo** (`pacePreview`, :282; `lib/month-pace-preview.ts` nad `month-pace-calculator.ts`): licznik to wydatki, mianownik suma planów puli Running bez One-off. Podniesienie planu pozycji z puli **zmniejsza** procent tempa i może zgasić `is_ahead`.
- **Odchylenie i „ponad plan”** (`rowReading`, :401; `lib/month-item-deviation.ts`; `isOverPlan` = wydatek i `actual > plan`) oraz **plakietka statusu** (`rowState` → `monthItemStatus`, `lib/month-plan-status.ts`, „ponad plan” wygrywa z innymi, `month-plan-status.ts:36`): po podniesieniu do `actual` odchylenie jest 0, a plakietka „ponad plan” znika.
- **Strzałka zmiany** (`PlanChangeArrow`, `current = rowState.planMinorUnits`): dla wydatku wzrost względem zatwierdzonego daje `ArrowUp` w `text-destructive` (ADR-0060).
- **Nie reagują:** kwota „Zatwierdzony”, `year_comparison`, `available_limit_minor_units` i `month_pace.*` poza trybem zmian (wartości z serwera). Linia roczna jest oznaczana jako nieaktualna (`yearStale`, :433), ale jej liczby nie są przeliczane.
- **Ostrzeżenia przy wpisywaniu operacji** (`lib/entry-to-spend-warning.ts`, `lib/import-to-spend-warnings.ts`) liczą na danych serwera, nie z pól tej strony, więc biorą plan dopiero po zapisie. Nie wymagają zmian, jeśli funkcja nie dodaje nowego odczytu (reguła `.ai/rules/month-calculators.md`: kopia PHP i TS tylko dla nowego odczytu).
- **Po zapisie:** powiadomienie Dashboardu `month_item_over_plan` dla pozycji z `actual > plan` (`DashboardNotices.php:122-160`, liczone tylko dla bieżącego miesiąca kalendarzowego i stanu planu różnego od `none`, :103-108) przestanie dotyczyć podniesionych pozycji. W Podsumowaniu miesiąca (`Month Summary`, tylko przeszłe miesiące z zatwierdzonym planem) odchylenie sum to `wydatki − Bieżący plan miesiąca`, więc podniesiony plan zeruje odchylenie pozycji także tam (glosariusz, wpis „Month Summary”). To skutek uboczny do wpisania w ADR.

### 6. Ikona potwierdzenia i przycisk

- **Przycisk:** w katalogu jest `IconAction` (`resources/js/components/IconAction.vue`): `Tooltip` (opóźnienie 200) wokół `Button variant="ghost" size="icon-sm"` z `aria-label` równym etykiecie i tooltipowi; rozmiar `icon-sm` to `--control-height-sm` 26 px (`app.css:158`, `ui/button/index.ts:37`), ikony 16 px. Nigdzie w wierszu Planu miesiąca nie jest używany (użycia: `pages/Accounts.vue:233-288`, `pages/TermDeposits.vue:269-333`). Jest wskazany jako gotowy element w `.ai/rules/js.md`. Nie ma go na listach `scripts/check-ui-literals.mjs` i `tests/Unit/MonthPlanHardcodedUiTest.php` (musi trafić na obie, jeśli zostanie użyty w widoku).
- **Strzałka z ADR-0060** jest gołym `<button type="button">` z `ArrowUp`/`ArrowDown` (`size-3.5`), `aria-label` równym tekstowi tooltipu; kierunek i ton z `lib/plan-change-direction.ts`. Stoi w komórce „Zatwierdzony”, przed kwotą, i tylko gdy `approved_minor_units !== null && planMinorUnits !== null` (`MonthPlanItemRow.vue:159-169`). Poniżej `xl` ta komórka to wiersz z podpisem „Zatwierdzony”, strzałką i kwotą.
- **Układ komórki planu** (`MonthPlanItemRow.vue:187-212`): podpis `meta` (`xl:sr-only`), pole `.cell-input` (`h-7 w-full text-right`, `app.css:801`) albo kwota, `InputError`. `.month-plan-cell-plan` to zwykły blok bez flexa (`app.css:902`). Siatka: poniżej `xl` cztery równe kolumny z odstępem 12 px (obszary `approved plan actual deviation`), od `xl` kolumna planu ma 8 rem (`app.css:864-885`).
- **Miejsce (rachunek własny, nie pomiar):** przy 390 px zostaje około 72 px na kolumnę, więc 26-pikselowa ikona obok pola się nie zmieści; od `xl` z 128 px zostaje około 96 px na pole po odjęciu ikony i odstępu, co jest ciasne dla dużych kwot. ADR-0060 odrzucił kwotę zatwierdzoną wewnątrz komórki planu właśnie dlatego, że w trybie zmian „crowds the input field”. Wniosek do planu: ikony i przycisk w linii podpisu komórki planu (analogicznie do strzałki w podpisie „Zatwierdzony” poniżej `xl`) albo osobna kolumna; każde wymaga nazwanej klasy w `app.css`, nie wartości dowolnej (`.ai/rules/js-month-plan.md`).
- **Kolory (reguły z `context/foundation/design-system.md:49-63`, ADR-0060, ADR-0042, `.ai/rules/js.md`):** `success` to dochód i strzałka korzystna; `destructive` to przekroczenie, usuwanie i strzałka niekorzystna; `warning` to „blisko przekroczenia”, nie przekroczenie już zaksięgowane; `brand`/`brand-text` to tożsamość, stan dokumentu i jedna akcja zatwierdzająca, „nigdy status pieniędzy”; `accent` jako potwierdzenie bez kwoty tylko w `Alert ok` i `Badge ok` (ADR-0056); `muted-foreground` to podpisy i wiersze zarchiwizowane. Tokenu `info` nie ma. Kandydaci dla znacznika „plan podniesiony automatycznie” bez nowego tokenu: (a) ta sama strzałka w górę w tonie `destructive` według ADR-0060 (spójne, ale identyczna z ręcznym podniesieniem); (b) neutralna ikona `muted-foreground` jako podpis; (c) `brand-text` — sprzeczny z „nigdy status pieniędzy”; (d) `warning` — sprzeczny z „nie dla przekroczeń już zaksięgowanych”; (e) `accent` — tooltip niesie kwotę, a ADR-0056 dopuszcza ten kolor tylko dla potwierdzenia bez kwoty; (f) `success` — sprzeczny, bo oznacza dochód i korzystny kierunek planu. Rozstrzygnięcie to decyzja do ADR; nowy kolor lub nowe znaczenie koloru wymaga wpisu w `design-system.md` i, jeśli zmienia decyzję, ADR.
- **Dostępna nazwa:** wzorzec to etykieta w `lang/pl/ui.php` w tablicy `month_plan` (od :368), klucze płaskie w `snake_case`, parametry `:nazwa` podstawiane `.replace(':count', n)`; typ `month_plan: Record<string,string>` (`resources/js/types/shell.ts:44`) nie wymaga zmiany typu przy nowym kluczu. Etykieta kolumny wydatków to „Faktycznie” (`lang/pl/ui.php:439`), plan to „Plan” (:438). Przykładowe klucze (moja propozycja, nie istniejące): `plan_raised_to_actual => 'Plan podniesiony do wydatków: :amount'` i `plan_match_actual => 'Ustaw plan na wydatki'`.

### 7. Testy

- **Logika jako czysta funkcja:** w `resources/js/lib/` leżą `month-plan-amount.ts`, `month-plan-status.ts`, `plan-change-direction.ts` ze zwykłymi testami obok (`*.test.ts`, import z `'vite-plus/test'`, `test(...)` albo `describe/it`, `toEqual`/`toBe` na literałach). Nowa funkcja (np. wybór kwoty „plan = wydatki” z ograniczeniem do 0 i warunek podniesienia dla typu, pola i `actual > plan`) pasuje w `lib/month-plan-*.ts`.
- **Serwer:** jeśli nie dodaje się nowego odczytu ani propu, to nie ma nowego testu PHP z samej funkcji. `tests/Feature/MonthPlanPageTest.php` (`assertInertia` m.in. `actual_minor_units`, `plan_minor_units`, `approved_minor_units`, :42-89) i `ChangeMonthPlanTest.php` (16 przypadków) już pokrywają zapis kwoty większej niż poprzednia pośrednio; nie sprawdzałem, czy któryś przypadek dosłownie podnosi kwotę powyżej `actual`.
- **Kitchen sink:** `resources/js/pages/dev/MonthPlanSink.vue` buduje tabele z `tableConfig(...)` (read, read-open, edit, edit-errors, past, empty, linie 152-197) z fixtur `lib/month-plan-fixtures.ts` (m.in. wiersz `overPlanExpense`: plan 30 000, wydatki 41 000, zatwierdzony 35 000). Nowe stany (przycisk, wiersz podniesiony, wyłączony, błąd) dochodzą jako nowe `tableConfig` i wiersz w `stateMatrix` (:49-78). Uwaga: `stateOf` w sinku liczy status z `row.plan_minor_units`, nie z wpisanej wartości (:199-223), więc stan „podniesiony” trzeba tam odwzorować świadomie.
- **Listy stróżujące:** każdy nowy komponent Planu miesiąca trafia do `tests/Unit/MonthPlanHardcodedUiTest.php` i `scripts/check-ui-literals.mjs` (`js-month-plan.md`).
- **E2E:** w `tests/e2e/` nie ma testu Planu miesiąca (są m.in. `sidebar-year-refresh`, `command-palette`); katalog sprawdzony tylko po nazwach plików.

## Code References

- `resources/js/pages/MonthPlan.vue:52,72-77,79-90,133-145,156-163,175-196,497-503` — stan trybu zmian, `amountInputs`, `parsedAmount`, `submittedAmounts`, `updateAmount`
- `resources/js/components/MonthPlanItemRow.vue:155-212` — komórki „Zatwierdzony” (strzałka) i „Plan” (pole `.cell-input`)
- `resources/js/components/PlanChangeArrow.vue` — strzałka zmiany planu (ADR-0060)
- `resources/js/components/IconAction.vue` — przycisk-ikona z tooltipem i `aria-label`
- `app/Support/MonthCategoryActuals.php:36-42` — zapytanie o wydatki z miesiąca
- `app/Support/ToSpendCalculator.php:76-80` — Running = `max(plan, actual)`; Bill/dochód = `actual` po pierwszej dodatniej operacji
- `app/Support/MonthItemDeviation.php:46-49` — „ponad plan” = wydatek i `actual > plan`
- `app/Support/DashboardNotices.php:122-160` — powiadomienie `month_item_over_plan`
- `app/Actions/ChangeMonthPlan.php:186,205-207,222,246,254` — zapis tylko Bieżącej kwoty, odrzucenie miesiąca przeszłego
- `app/Http/Requests/UpdateMonthPlanRequest.php:306-310` — walidacja kwot
- `app/Http/Controllers/MonthPlanController.php:164,245-269` — `can_change` i kształt pozycji
- `app/Concerns/ExpenseValidationRules.php:24,67` — data operacji `before_or_equal:today`
- `context/archive/2026-11-07-ui-fixes-2/plan.md:67` — „Plan = wydatki” odłożony do osobnej zmiany
- `context/foundation/adr/0060-plan-change-direction-arrow.md` — kolor strzałki i kolumna „Zatwierdzony”

## Architecture Insights

- Plan miesiąca jest „miękki”: serwer nie waliduje kwoty względem wydatków ani zatwierdzonej, więc cała logika podniesienia jest decyzją warstwy prezentacji; reguła dla użytkownika wynika z glosariusza i ADR, nie z ograniczenia bazy.
- Rozdział „pole = tekst, odczyt = `parsedAmount`” sprawia, że każdy podgląd na żywo reaguje na wartość pola; wpisanie wartości do `amountInputs` jest najtańszym sposobem, żeby wszystkie podglądy (To Spend, tempo, odchylenie, status, strzałka) zareagowały bez zmian w kalkulatorach.
- Zmiana domenowa dotyczy znaczenia sygnału „pozycja ponad plan”, nie arytmetyki: To Spend jest dla tych pozycji już liczony według `max(plan, actual)`.

## Historical Context (from prior changes)

- `context/archive/2026-11-07-ui-fixes-2/plan.md:67` — „‚Plan = wydatki’ w Planie miesiąca (przycisk i automatyczne podniesienie planu). Osobna zmiana: najpierw glosariusz i ADR.” — **poparte** (zakres wyłączony świadomie). Ten sam wpis w `plan-brief.md` (wg raportu pomocnika, linie 33 i 57; nie otwierałem go sam).
- `context/foundation/adr/0060-plan-change-direction-arrow.md` — strzałka porównuje kwotę zatwierdzoną z wartością widoczną w wierszu (pole), nie z `plan_minor_units` serwera — **poparte** i nadal obowiązuje; to pozwala, żeby strzałka reagowała na podniesienie bez zmian. Odrzucenie „kwoty zatwierdzonej wewnątrz komórki planu, bo w trybie zmian ściska pole” — **poparte**, ogranicza miejsce na ikonę.
- `context/foundation/adr/0033-month-plan-beside-annual-budget.md` — „pozycja ponad plan (wydatki powyżej planu, dowolny rozmiar, także plan 0) to powiadomienie `exceeded`, bo przekroczenie to już fakt, mimo że plan miesiąca jest miękki” — **poparte**; to zdanie jest tym, co podniesienie planu osłabia dla pozycji świadomie podniesionych.
- `context/foundation/prd.md:132` — FR-012 wymienia „pozycja ponad plan” wśród powiadomień planu miesiąca — **poparte**; nie ma tam wymagania o „plan = wydatki” ani o podnoszeniu. `roadmap.md`, `prd-v2.md` i `prd-mvp.md` nie zawierają takiego wymogu (wg raportu pomocnika; nie przeszukałem ich sam).
- `context/changes/month-plan-match-actual/change.md` — tylko szkielet (status `new`, bez notatek); nie ma wcześniejszych prób tej funkcji w `context/changes/**` ani `context/archive/**` (wg pomocnika, przeszukane po frazach „plan = wydatki”, „dopasuj plan”, „podnieś plan”).

## Related Research

Brak powiązanych `research.md` dla tej funkcji. Obok w `context/changes/` jest tylko `period-report-by-category` (nie dotyczy).

## Open Questions

Decyzje użytkownika do podjęcia w `/10x-plan` lub w ADR (nie rozstrzygałem ich sam):

1. **Zakres stanów:** czy podniesienie i przycisk działają tylko w trybie zmian planu zatwierdzonego (tak rozumiem „w trybie zmian”), czy także w edytowalnej propozycji, gdzie nie ma trybu zmian ani kolumny „Zatwierdzony”?
2. **Kandydaci do dołączenia** (kategorie spoza planu z wydatkami > 0, widoczni tylko w trybie zmian): czy też podnosić automatycznie, skoro zapis ich dołącza z `approved` 0 (`ChangeMonthPlan.php:251-259`)?
3. **Wydatki ujemne (sam zwrot):** przycisk wyłączony czy wpisuje 0?
4. **Znacznik:** wyprowadzony ze stanu pola (nie wymaga nowego stanu) czy zbiór id (znika po ręcznej zmianie od razu w obu wariantach — różnica to zachowanie po wpisaniu ręcznie dokładnie kwoty wydatków); oraz który z kandydatów kolorystycznych (pkt 6) albo nowe znaczenie koloru z wpisem do `design-system.md`.
5. **Miejsce w wierszu:** podpis komórki planu poniżej `xl` i flex-wrapper z polem od `xl`, czy osobna kolumna/obszar siatki; wymiary do zmierzenia w przeglądarce (mój rachunek nie jest pomiarem).
6. **Skutek uboczny na Dashboardzie i w Podsumowaniu miesiąca:** czy akceptujemy, że podniesiony plan gasi `month_item_over_plan` i zeruje odchylenie pozycji (glosariusz, ADR-0033)? Jeśli nie, ADR musi to zawęzić albo pozostawić ślad podniesienia (to byłby nowy zapis i nowy prop).
7. **Luki w badaniu:** nie sprawdziłem, czy import dopuszcza daty przyszłe; nie sprawdziłem, czy w stronie jest ochrona przed opuszczeniem z niezapisanymi zmianami (wejście w tryb zmian z podniesieniami od razu jest „brudne”); szerokości przy 390 px i `xl` to rachunek, nie pomiar; nie otwierałem sam `plan-brief.md` ani ADR-0035 w całości.
