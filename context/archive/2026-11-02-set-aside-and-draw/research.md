---
date: 2026-11-02T11:49:02+01:00
researcher: Claude Code (claude-opus-5-5)
git_commit: e9fd26ea1b31868fefc2ad4730b666c9ab791d82
branch: set-aside-and-draw
repository: sebastian-kostecki/household-treasurer
topic: "S-02 set-aside-and-draw: co kod S-01 daje i czego wymaga dodanie kwoty „odkładam / dobieram” do planu miesiąca"
tags: [research, codebase, month-plan, to-spend, set-aside, spendable-liquidity, user-snapshot]
status: complete
last_updated: 2026-11-02
last_updated_by: Claude Code (claude-opus-5-5)
---

# Research: S-02 „odkładam / dobieram” w planie miesiąca

**Date**: 2026-11-02T11:49:02+01:00
**Researcher**: Claude Code (claude-opus-5-5)
**Git Commit**: e9fd26ea1b31868fefc2ad4730b666c9ab791d82
**Branch**: set-aside-and-draw
**Repository**: sebastian-kostecki/household-treasurer

## Research Question

Wycinek S-02 z `context/foundation/roadmap.md:110-120`: „użytkownik może wpisać w planie kwotę »odkładam / dobieram« i zobaczyć jej wpływ na »do wydania«” (FR-004, FR-016). Ryzyko z roadmapy: to pierwsza kwota planu spoza kategorii, a ostrzeżenie o dobieraniu ponad Spendable Liquidity nie może zmieniać sald.

Pytania badawcze:

1. Co już ustalono w domenie, a co zostawiono otwarte dla S-02?
2. Gdzie w kodzie S-01 trzeba dodać Set-aside: zapis, propozycja, wzór (PHP i TS), zatwierdzenie, strona?
3. Skąd wziąć Spendable Liquidity do ostrzeżenia i jakim wzorcem je pokazać?
4. Co nowe pole oznacza dla snapshotu `user:backup` / `user:restore`?

## Summary

- **Domena jest rozstrzygnięta poza jedną regułą.** Set-aside to jedna podpisana kwota na Month Plan. Dodatnia („odkładam”) obniża To Spend, ujemna (Draw, „dobieram”) go podnosi. Draw, którego |Draw| > Spendable Liquidity, daje ostrzeżenie bez blokady (`CONTEXT.md:83-84`, `.docs/adr/0033-month-plan-beside-annual-budget.md:3,7`). Otwarta jest reguła propozycji dla Set-aside: kopiować z poprzedniego miesiąca czy startować od 0. F-01 celowo zostawił ją dla S-02 (`context/archive/2026-11-02-month-plan-domain-decisions/plan-brief.md:64`, `plan.md:40`).
- **W kodzie nie ma jeszcze Set-aside.** Na sprawdzonej ścieżce (`app/`, `resources/js/lib`, `resources/js/pages`, `database/`) jedyna wzmianka to komentarz `app/Support/ToSpendCalculator.php:11`: „Set-aside is not a parameter yet (it equals 0 until S-02)”. Nie ma kolumny w `month_plans`, parametru we wzorze, pola w `MonthPlanReading` ani pola w formularzu.
- **Dotknięte miejsca (S-01):** migracja i model `MonthPlan`, `MonthPlanReading`, `MonthPlanProposal`, para `ToSpendCalculator` PHP/TS z testami, `ApproveMonthPlanRequest`, `ApproveMonthPlan`, `MonthPlanController`, `MonthPlan.vue`, `lang/pl/ui.php` oraz snapshot `BackupUserData` / `RestoreUserData`. Kotwice są w sekcjach niżej.
- **Spendable Liquidity jest liczona w jednym zapytaniu, zawsze na dziś.** `DashboardController.php:54` sumuje `accounts.current_balance_minor_units` użytkownika, łącznie z kontami zarchiwizowanymi. Nie ma klasy do ponownego użycia ani odczytu na inną datę. Dla miesiąca przyszłego ostrzeżenie może porównywać tylko z dzisiejszym saldem.
- **Wzorzec ostrzeżenia już istnieje.** Ostrzeżenie nieblokujące to `<Alert variant="warning">` (`.ai/rules/js.md:22-24`). Ostrzeżenia na żywo liczy klient z sald podanych w propsach (`resources/js/lib/hand-entry-warning-preview.ts`, użycie w `resources/js/components/OwnTransferFormDialog.vue:288-296`).
- **Snapshot v5 nie jest na `main`.** `main` ma `SNAPSHOT_VERSION = 4` (`git show main:app/Actions/BackupUserData.php:17`). Commit S-01 `0482082` z wersją 5 jest tylko na gałęziach `development`, `approved-plan-changes` i `set-aside-and-draw`. Pole można więc dodać bez podbijania wersji, z wartością domyślną przy odczycie (precedens `is_paced`), albo podbić wersję do 6. To wybór dla planu.
- **Do czasu S-03 Set-aside da się ustawić tylko w propozycji.** Strona edytuje kwoty wyłącznie w stanie `proposal` z `can_approve` (`MonthPlan.vue:81-85`). Po zatwierdzeniu plan jest tylko do odczytu, a jedynym zapisem jest `approve` (`routes/web.php:28-31`).

## Detailed Findings

### 1. Domena: co ustalone, co otwarte

- Set-aside: „One signed amount per Month Plan: positive („odkładam”) lowers To Spend, negative is a Draw („dobieram”) and raises it. Not a Category, not a transfer, does not change Liquidity. A Draw whose absolute value exceeds Spendable Liquidity (|Draw| > Spendable Liquidity) gives a warning without a block.” (`CONTEXT.md:83-84`).
- Wzór To Spend kończy się „− Set-aside” (`CONTEXT.md:80`, ADR-0033 `:7`, PRD `context/foundation/prd.md:191`).
- Month Plan zawiera „plus one signed Set-aside amount” (ADR-0033 `:3`). ADR rozróżnia Approved Month Plan (zamrożony) i Current Month Plan (edytowalny w trakcie miesiąca) (ADR-0033 `:3`, `CONTEXT.md:67-72`). Nie mówi jednak wprost, czy Set-aside też ma wersję zatwierdzoną i bieżącą.
- Month Summary (S-09) ma pokazać „intended Set-aside and the real set-aside (Month Result), drawn amounts (Draw) also over the year” (`CONTEXT.md:96`). Plan zatwierdzony i końcowy muszą być rozróżnialne od pierwszej zmiany (roadmapa S-03, `roadmap.md:131`).
- Reguła propozycji ADR-0033 (`:5`) dotyczy tylko kwot kategorii. Dla Set-aside: „Bez reguły propozycji dla Set-aside (czy kopiuje się z poprzedniego miesiąca, czy startuje od 0) — rozstrzyga S-02” (`month-plan-domain-decisions/plan.md:40`).
- Przykład kontrolny z F-01 (`month-plan-domain-decisions/plan.md:156`): przychód plan 6000 (bez wpływu), Bill czynsz plan 2000 zapłacone 1950, Running żywność 1500 przy 400, Running paliwo 300 przy 350, Set-aside +500. Wynik: 6000 − 1950 − 1500 − 350 − 500 = **1700,00**. Z Set-aside −300 (Draw): 6000 − 1950 − 1500 − 350 + 300 = **2500,00**. Sprawdziłem tę arytmetykę.
- PRD wiąże Draw z przesunięciem pensji: „nadwyżka wchodzi do kolejnego miesiąca przez »dobieram«” (`prd.md:124`). Draw bywa więc kwotą jednego miesiąca. To argument w wyborze reguły propozycji, a nie rozstrzygnięcie.

### 2. Zapis: `month_plans` i model

- `month_plans`: `id`, `budget_id` (FK, cascade), `month` (unsignedTinyInteger), `approved_at` (not null), timestamps, `unique(budget_id, month)` (`database/migrations/2026_11_02_091919_create_month_plans_table.php:15-21`). Brak check constraints.
- `month_plan_items` trzyma parę `approved_amount_minor_units` / `current_amount_minor_units` (bigint ze znakiem, not null) (`..._091922_create_month_plan_items_table.php:18-19`). `ApproveMonthPlan.php:64-70` ustawia obie na tę samą kwotę. Nic w `app/` nie zapisuje potem `current_*` (raport z przeszukania `app/`). Czyta ją `MonthPlanProposal.php:99`.
- `MonthPlan` ma `#[Fillable(['budget_id','month','approved_at'])]` (`app/Models/MonthPlan.php:21`). Nowa kolumna musi trafić do Fillable i casts, bo bez tego `create()` ją pominie.
- Rekord planu powstaje dopiero przy zatwierdzeniu. Propozycja jest liczona przy odczycie (`context/archive/2026-11-02-month-plan-and-to-spend/plan-brief.md:35`, `ApproveMonthPlan.php:58-62`), więc Set-aside propozycji też nie ma gdzie zapisać przed zatwierdzeniem.
- Wniosek, nie decyzja: para `approved_set_aside_minor_units` / `current_set_aside_minor_units` (bigint ze znakiem, domyślnie 0) odpowiada wzorcowi pozycji i potrzebom S-03 i S-09. Domyślne 0 obsługuje plany zatwierdzone przed S-02 bez migracji danych (FR-018).

### 3. Propozycja: `MonthPlanReading` i `MonthPlanProposal`

- `MonthPlanReading` ma tylko `state`, `approvedAt` i `amounts` (`app/Support/MonthPlanReading.php:13-17`). Set-aside potrzebuje nowego pola, np. `setAsideMinorUnits`.
- `MonthPlanProposal::computePlan` (`app/Support/MonthPlanProposal.php:83-122`):
  - plan zatwierdzony → czyta `current_amount_minor_units` (`:95-103`), tu analogicznie bieżący Set-aside;
  - miniony bez planu → `none` (`:105-107`), Set-aside bez znaczenia;
  - propozycja → kwoty z `sourceFor` (`:109-121`). Źródło to poprzedni miesiąc w dowolnym stanie albo grudzień poprzedniego budżetu (`:129-144`), a brak źródła oznacza średnią z rocznego.
- Jeśli wybrana zostanie reguła „kopiuj”, `sourceFor` daje gotowe źródło, także łańcuch przez kolejne propozycje (`:131-134`) i styczeń z grudnia poprzedniego budżetu (`:137-143`). Bez źródła 0 to jedyna sensowna wartość, bo Set-aside nie ma kwoty rocznej. Przy regule „0” `sourceFor` nie jest potrzebny.
- Testy propozycji: `tests/Feature/MonthPlanProposalTest.php:34-244`.

### 4. Wzór: para PHP/TS i testy

- PHP: `ToSpendCalculator::toSpendMinorUnits(array $items): int` (`app/Support/ToSpendCalculator.php:15-36`), bez parametru Set-aside (`:11`).
- TS: `toSpendMinorUnits(items: ToSpendItem[]): number` (`resources/js/lib/to-spend-calculator.ts:13-33`).
- `AGENTS.md:32` każe zmieniać obie kopie i oba testy razem. Uzasadnienie kopii TS (podgląd przed zatwierdzeniem) obejmuje też Set-aside, bo ta kwota również nie może zostać zapisana przed zatwierdzeniem.
- Wywołujący na produkcji: dokładnie jeden po każdej stronie, `MonthPlanController.php:147-149` i `MonthPlan.vue:145-153`. Wyszukałem `ToSpendCalculator|toSpendMinorUnits` w `app`, `resources/js` i `tests`.
- Testy: `tests/Unit/ToSpendCalculatorTest.php:21-38` i `resources/js/lib/to-spend-calculator.test.ts:49-65` mają po 5 tych samych przypadków (kanoniczny 3730,00, wpływ pensji, Bill z samym zwrotem, pusta lista, wynik ujemny). Przypadki 1700/2500 z F-01 można dodać do obu.

### 5. Zatwierdzenie: request i akcja

- `ApproveMonthPlanRequest::rules()` waliduje tylko `items.*` z `min:0` i `max:1000000000000` (`app/Http/Requests/ApproveMonthPlanRequest.php:27-31`). Set-aside potrzebuje własnej reguły ze znakiem, np. `integer` w przedziale ±1e12, czyli z dolną granicą ujemną zamiast `min:0`.
- `MonthPlanController::approve` składa `$amounts` z `items` i woła `ApproveMonthPlan::handle($budget, $month, $amounts, $today)` (`MonthPlanController.php:76-82`). Sygnatura akcji (`ApproveMonthPlan.php:20`) nie ma Set-aside. Plan tworzy się w `:58-62`.
- Ostrzeżenie o Draw nie może blokować (`CONTEXT.md:84`). Request ani akcja nie mogą więc odrzucać Draw ponad Spendable Liquidity.

### 6. Strona i propsy

- Props `monthPlan` (`MonthPlanController.php:92-152`, typ w `MonthPlan.vue:42-49`): `state`, `approved_at`, `can_approve`, `to_spend_minor_units`, `income_items`, `expense_items`. Brak Set-aside i Spendable Liquidity. `AGENTS.md` (sekcja „Inertia props contract”) wymaga zmiany obu stron i asercji `assertInertia` w teście Feature (`tests/Feature/MonthPlanPageTest.php`).
- Edycja: `isEditable` = `state === 'proposal' && can_approve` (`MonthPlan.vue:81-85`). Kwoty żyją w `amountInputs` (`:96`), są parsowane przez `parseMoneyInputToMinorUnits` z `parsed >= 0` (`:108-123`) i wysyłane w ukrytych polach `items[i][…]` (`:324-340`). Przycisk jest wyłączony, gdy któraś kwota jest nieprawidłowa (`:470`). Pole Set-aside potrzebuje własnego parsowania bez warunku `>= 0`.
- Podgląd: `toSpendPreviewMinorUnits` (`:136-154`) woła kopię TS przy edycji, a w innym razie bierze liczbę z serwera.
- Obsługa znaku: `parseMoneyInputToMinorUnits` przyjmuje `-` (`resources/js/lib/format.ts:31-46`), tak samo `minorUnitsToMoneyInput` (`:18-26`) i `MinorUnits::fromMajorInput` (`app/Support/MinorUnits.php:7-22`).
- Teksty: `lang/pl/ui.php:279-310` (jedyny plik `lang/*/ui.php`). Klucz `to_spend_hint` (`:282`) nie wspomina o odkładaniu. Nie jest używany w `resources/js`, `app` ani w innych miejscach `lang`.
- Reguły UI: tylko tokeny kolorów; `warning` oznacza „w granicach, blisko przekroczenia” (`.ai/rules/js.md:8-12`). Kwoty idą przez `formatMoney` z `tabular-nums` (`:14-16`). Ostrzeżenia nieblokujące to `<Alert variant="warning">`, a `destructive` tylko dla błędów i usuwania (`:22-24`).

### 7. Spendable Liquidity i ostrzeżenie o Draw

- Definicja: „The sum of Account current balances (bank and cash). … Does not include Term Deposit balances.” (`CONTEXT.md:159-160`). Kod liczy ją jako `(int) $user->accounts()->sum('current_balance_minor_units')` (`app/Http/Controllers/DashboardController.php:54`), bez filtra archiwizacji. PRD FR-REP-09 wprost obejmuje konta zarchiwizowane (`.docs/prd.md:292`).
- Druga kopia pod inną nazwą: `liquidity_total_minor_units` w `AccountController.php:38`. Nie ma klasy ani helpera do ponownego użycia, więc S-02 albo powtórzy zapytanie (trzecia kopia), albo wydzieli helper.
- Odczyt jest zawsze na dziś. `current_balance_minor_units` to kolumna zdenormalizowana, zmieniana przez akcje operacji, przelewów i ruchów kapitału. Operacji z przyszłą datą nie ma (`before_or_equal:today`, np. `app/Concerns/ExpenseValidationRules.php:24`). Nie ma też odczytu salda na inną datę. Jedyny „as-of”, `BudgetDeficitWarning::liquidityAtYearStart` (`app/Support/BudgetDeficitWarning.php:49-56`), sumuje salda otwarcia i nie jest prawdziwym saldem na datę.
- Zakres miesięcy: przyszły miesiąc można zatwierdzić (`MonthPlanController.php:144-146`). Ostrzeżenie dla przyszłego miesiąca porówna więc Draw z dzisiejszym saldem. Dla minionego zatwierdzonego planu, gdyby ostrzeżenie się pojawiło, porównanie z dzisiejszym saldem nie ma sensu.
- Wzorzec ostrzeżenia na żywo: klient porównuje z saldem z propsów (`hand-entry-warning-preview.ts`, np. `showNegativeBalanceWarning`). Markup to `Alert` + `AlertTriangle` + `AlertTitle` („Uwaga”) + `AlertDescription` (`resources/js/components/OwnTransferFormDialog.vue:288-296`). Styl tekstu to jedno zdanie w czasie przyszłym z kropką, np. „Operacja spowoduje ujemne saldo konta.” (`lang/pl/ui.php:539-542`).
- Warunek ostrzeżenia z definicji: `setAside < 0 && -setAside > spendableLiquidity`. Ściśle większe: przy |Draw| równym Spendable Liquidity ostrzeżenia nie ma. Gdy Spendable Liquidity jest ujemna, każdy Draw ostrzega.
- Dashboard: `.ai/rules/support.md` dotyczy tylko `app/Support/DashboardNotices.php` i wymaga terminu oraz progu w `CONTEXT.md` przed nowym powiadomieniem `warning`. `CONTEXT.md:84` mówi „gives a warning”, a nie „Dashboard notice”. Powiadomienia Dashboardu dla Draw nie ma ani w S-02, ani w liście S-07 (`prd.md:132`).

### 8. Snapshot `user:backup` / `user:restore`

- Wersja: `BackupUserData::SNAPSHOT_VERSION = 5` (`app/Actions/BackupUserData.php:18`) na tej gałęzi; `main` ma 4.
- Serializacja jest jawną listą pól: `serializeMonthPlan` zapisuje `id`, `budget_id`, `month`, `approved_at` i `items[]` z `category_id`, `approved_amount_minor_units`, `current_amount_minor_units` (`BackupUserData.php:170-185`). Nowe pole trzeba dopisać ręcznie.
- Odczyt:
  - obsługiwane wersje to `[1, 2, 3, 4, SNAPSHOT_VERSION]` (`app/Actions/RestoreUserData.php:79`);
  - `month_plans` jest wymagane, gdy `$version === SNAPSHOT_VERSION` (`:97`);
  - plany przywraca `restoreMonthPlans` (`:34`, `:226-289`), a `MonthPlan::create` dostaje tylko `budget_id`, `month` i `approved_at` (`:253-257`).
- Precedensy:
  - `is_paced` dodano bez podbijania wersji, z domyślną wartością przy odczycie `$categoryData['is_paced'] ?? true` (`RestoreUserData.php:420-429`, commit `ff76562`, test `tests/Feature/CategoryPacedFlagTest.php:248-262`);
  - podbicia wersji (v2, v3, v4, v5) dotyczyły nowych kolekcji albo kilku pól lokat (`ed62476`).
- Opcje dla planu:
  - (a) bez podbijania wersji: przy odczycie `?? 0` i test na brak klucza;
  - (b) wersja 6: zmiany w `:18`, `:79` i `:97`, a wersja 5 nadal wymagająca `month_plans`; do tego README `:106` i testy `tests/Feature/UserSnapshotCommandTest.php:46,196`;
  - (c) pole wymagane w wersji 5, skoro wersja 5 nie trafiła na `main`; ryzykowne, jeśli ktoś zrobił snapshot z `development`, czego repozytorium nie pokazuje.
- `RestoreApplicationBackup` odtwarza zrzut bazy w całości (`app/Actions/RestoreApplicationBackup.php:189-204`) i nie zależy od kolumn.
- Testy snapshotu: przypadki month_plans w `UserSnapshotCommandTest.php:172-346` (round trip, przywrócenie wersji 4, wersja 5 bez `month_plans`, wadliwe plany).

## Code References

- `CONTEXT.md:80,83-84,96,159-160` — To Spend, Set-aside, Month Summary, Spendable Liquidity
- `.docs/adr/0033-month-plan-beside-annual-budget.md:3,5,7` — Set-aside w Month Plan, reguła propozycji (tylko kategorie), wzór
- `app/Support/ToSpendCalculator.php:11-36` — wzór bez Set-aside
- `resources/js/lib/to-spend-calculator.ts:13-33` — kopia TS
- `tests/Unit/ToSpendCalculatorTest.php:21-38`, `resources/js/lib/to-spend-calculator.test.ts:49-65` — testy pary
- `app/Support/MonthPlanReading.php:13-17` — odczyt planu bez Set-aside
- `app/Support/MonthPlanProposal.php:83-144` — stan planu, źródło propozycji
- `app/Actions/ApproveMonthPlan.php:20,58-70` — zatwierdzenie, tworzenie planu i pozycji
- `app/Http/Requests/ApproveMonthPlanRequest.php:27-31` — walidacja kwot (`min:0`)
- `app/Http/Controllers/MonthPlanController.php:76-82,103-152` — approve i payload strony
- `resources/js/pages/MonthPlan.vue:42-49,81-85,105-154,324-340,465-475` — typy, edycja, podgląd, formularz
- `database/migrations/2026_11_02_091919_create_month_plans_table.php:15-21`, `app/Models/MonthPlan.php:21` — schemat i Fillable
- `app/Http/Controllers/DashboardController.php:54`, `app/Http/Controllers/AccountController.php:38` — Spendable Liquidity
- `.ai/rules/js.md:8-12,14-16,22-24` — kolory, kwoty, ostrzeżenia
- `app/Actions/BackupUserData.php:18,170-185`, `app/Actions/RestoreUserData.php:79,97,226-289,420-429` — snapshot
- `lang/pl/ui.php:279-310` — teksty strony planu

## Architecture Insights

- Plan miesiąca opiera się na trzech czystych odczytach serwera (`MonthPlanProposal`, `MonthCategoryActuals`, `ToSpendCalculator`) i jednej akcji zapisu. Set-aside przechodzi przez wszystkie poza `MonthCategoryActuals`, bo nie ma kwoty faktycznej.
- Kwoty planu mają parę zatwierdzona/bieżąca, chociaż w S-01 nic nie zmienia bieżącej. Para istnieje dla S-03 i S-09.
- Liczby miesiąca policzone na serwerze mają kopię TS tylko tam, gdzie podgląd musi reagować przed zapisem (`AGENTS.md`, sekcja „Month logic lives twice”). Porównanie |Draw| > Spendable Liquidity to jedno wyrażenie. Mieści się w uzasadnieniu istniejącej kopii, ale plan powinien to napisać wprost.
- Świat budżetu (To Spend) i świat środków (Spendable Liquidity) pozostają osobne (`prd.md:146`). Ostrzeżenie tylko czyta saldo i niczego nie zapisuje, więc wymóg FR-016 jest spełniony.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-domain-decisions/plan-brief.md:64` i `plan.md:40` — reguła propozycji Set-aside celowo otwarta do S-02. **Aktualne**: kod nie ma żadnej reguły.
- `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:156` — przykład 1700/2500. **Aktualny**: arytmetyka sprawdzona, przypadku nie ma w testach.
- `context/archive/2026-11-02-month-plan-domain-decisions/reviews/plan-review.md:74-81` (F5) — porównywanie ujemnej kwoty naprawiono przez „|Draw| > Spendable Liquidity”. **Aktualne**: `CONTEXT.md:84` ma to brzmienie.
- `context/archive/2026-11-02-month-plan-and-to-spend/plan-brief.md:43` — „Set-aside: 0 w S-01, bez kolumny”. **Aktualne** (`ToSpendCalculator.php:11`).
- `context/archive/2026-11-02-month-plan-and-to-spend/research.md:77` — `MinorUnits::fromMajorInput` obsługuje liczby ujemne, „potrzebne dla znakowanego Set-aside”. **Aktualne** (`MinorUnits.php:12-21`).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:650` — podbijanie wersji snapshotu nazwane „dotychczasową praktyką”. **Częściowo**: tak było przy nowych kolekcjach, ale `is_paced` (`ff76562`) i pola przelewów własnych (`d823cdc`) dodano bez podbijania.

## Related Research

- `context/archive/2026-11-02-month-plan-and-to-spend/research.md` — badanie S-01 (model planu, wzór, snapshot v5).

## Open Questions

Wybory produktowe i techniczne dla `/10x-plan` (fakty powyżej, decyzja nie należy do badania):

1. **Reguła propozycji Set-aside**: kopiować z poprzedniego miesiąca (jak kategorie), startować od 0 (jak jednorazowe), czy kopiować tylko „odkładam” i zerować Draw? Argument za 0 dla Draw: dobieranie wiąże się z przesunięciem pensji jednego miesiąca (`prd.md:124`).
2. **Para zatwierdzona/bieżąca dla Set-aside**: dwie kolumny jak w `month_plan_items` (zalecane ze względu na S-03 i S-09) czy jedna?
3. **Snapshot**: opcja (a), (b) czy (c) z sekcji 8. Gałąź `development` może mieć snapshoty w wersji 5, czego repozytorium nie pokazuje.
4. **Kiedy pokazywać ostrzeżenie o Draw**: tylko przy edycji propozycji, także przy odczycie planu zatwierdzonego (bieżący i przyszły miesiąc), czy nigdy dla minionego? Odczyt Spendable Liquidity jest zawsze na dziś.
5. **Spendable Liquidity**: trzecia kopia zapytania w `MonthPlanController` czy mały helper współdzielony z `DashboardController` i `AccountController`?
6. **Plan już zatwierdzony przed S-02**: ma Set-aside 0 i do S-03 nie da się go zmienić (`MonthPlan.vue:81-85`). Czy to akceptowalne? S-01 nie jest na `main`. Czy jakaś instancja z `development` ma zatwierdzone plany, nie wiadomo.
7. **Tekst**: etykieta pola („Odkładam / dobieram”), podpowiedź o znaku (np. „kwota ujemna oznacza dobieranie”) i zdanie ostrzeżenia. `to_spend_hint` (`lang/pl/ui.php:282`) nie jest używany, więc można go poprawić albo pominąć.
8. **Kolejność z S-03**: gałąź `approved-plan-changes` ma tylko commit startowy (`81e7cdd`). S-03 będzie zmieniać tę samą akcję, stronę i `current_*`, więc kolumny Set-aside z S-02 powinny trafić tam przed edycją planu zatwierdzonego.

Uwaga terminologiczna: `CONTEXT.md:104` nazywa Month Result „real set-aside” małą literą, a to inne pojęcie niż termin Set-aside. Planowanie S-09 powinno tego pilnować.
