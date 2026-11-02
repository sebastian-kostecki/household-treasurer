---
date: 2026-11-02T11:48:31+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 81e7cddeaa13a951152256c625369d4f005d9c75
branch: approved-plan-changes
repository: household-treasurer
topic: "S-03 approved-plan-changes: co w kodzie po S-01 trzeba otworzyć, wykorzystać i zachować, żeby użytkownik mógł zmienić zatwierdzony plan miesiąca"
tags: [research, codebase, month-plan, approved-month-plan, current-month-plan, to-spend, snapshot, s-02-overlap]
status: complete
last_updated: 2026-11-02
last_updated_by: Claude (claude-opus-5-5)
---

# Research: S-03 — zmiana zatwierdzonego planu miesiąca

**Date**: 2026-11-02T11:48:31+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 81e7cddeaa13a951152256c625369d4f005d9c75 (kod identyczny z `development` @ eff7a23; jedyna różnica to `change.md` tej zmiany). Kotwice są lokalne `plik:linia`.
**Branch**: approved-plan-changes (worktree `.claude/worktrees/approved-plan-changes`)
**Repository**: household-treasurer

## Research Question

Użytkownik uruchomił `/10x-research approved-plan-changes` bez osobnego pytania. `change.md` nie opisuje zakresu, więc pytanie wyprowadzono z roadmapy: S-03 „użytkownik może zmienić zatwierdzony plan w trakcie miesiąca”, PRD FR-003, prerekwizyt S-01, ryzyko „plan zatwierdzony i końcowy muszą pozostać rozróżnialne od pierwszej zmiany” (`context/foundation/roadmap.md:122-132`).

Pytanie robocze: **co w kodzie po S-01 blokuje zmianę zatwierdzonego planu, co można wykorzystać, czego nie wolno naruszyć, gdzie S-03 zderzy się z równoległym S-02 oraz które decyzje produktowe pozostają otwarte.** Badanie nie wybiera rozwiązania; to zadanie `/10x-plan`.

## Summary

1. **Schemat jest gotowy na zmianę kwot istniejących pozycji bez migracji.** `month_plan_items` ma osobne `approved_amount_minor_units` i `current_amount_minor_units`, obie `bigInteger NOT NULL` (`database/migrations/2026_11_02_091922_create_month_plan_items_table.php:18-19`). S-01 rozdzielił je celowo „żeby S-03 mógł je rozróżnić bez migracji danych” (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:283`). Zatwierdzenie zapisuje obie równe kwocie z formularza (`app/Actions/ApproveMonthPlan.php:64-70`). Nie ma kolumny historii, wersji ani znacznika „zmieniono” — tylko eloquentowe `updated_at` (migracje `..._091919_...:15-21`, `..._091922_...:15-22`).
2. **Ścieżki edycji nie ma.** Dla planów miesięcy istnieje jedna trasa zapisu, `POST budgets/{budget}/month-plans/{month}/approve` (`routes/web.php:29-31`). `ApproveMonthPlan` odrzuca stan `approved` błędem `items` (`ApproveMonthPlan.php:35-37`) i wymaga, by zbiór przesłanych kategorii był równy zbiorowi z odczytu (`:47-54`). Tego sprawdzenia nie da się użyć wprost do edycji, jeśli edycja ma dopuszczać kategorie spoza `month_plan_items`.
3. **Kwotę bieżącą czyta w `app/` jedno miejsce; kwoty zatwierdzonej nie czyta żadne.** `MonthPlanProposal::planFor` buduje odczyt planu zatwierdzonego z `current_amount_minor_units` (`app/Support/MonthPlanProposal.php:95-102`). Z tego odczytu korzystają: kolumna planu i „do wydania” na stronie (`app/Http/Controllers/MonthPlanController.php:105-132,189`) oraz źródło propozycji następnego miesiąca (`MonthPlanProposal.php:129-166`). `approved_amount_minor_units` w `app/` jest tylko zapisywane (ApproveMonthPlan `:68`, RestoreUserData `:284`) i serializowane (BackupUserData `:180`); nie trafia do propsów. `MonthPlanReading` ma jedną mapę `amounts` (`app/Support/MonthPlanReading.php:13-16`).
4. **Zmiana kwoty bieżącej miesiąca M zmienia propozycje kolejnych miesięcy.** Propozycja M+1 kopiuje plan M w dowolnym stanie (ADR-0033 ¶2; `MonthPlanProposal.php:129-136`), a dla zatwierdzonego M jest to kwota bieżąca (`:98-100`). Rekurencja dochodzi do pierwszego zatwierdzonego miesiąca, a w styczniu do grudnia poprzedniego budżetu (`:138-144`). Zatwierdzonych późniejszych miesięcy edycja nie zmienia. To zgodne z ADR-0033, ale plan powinien to nazwać.
5. **Strona ma jeden przełącznik tylko do odczytu, a mechanikę formularza da się użyć ponownie.** `isEditable = state === 'proposal' && can_approve` (`resources/js/pages/MonthPlan.vue:81-85`) steruje ukrytymi polami (`:324`), polem kwoty (`:447`) i podglądem (`:141`). Blok przycisku wymaga `can_approve` (`:465`). Tekst `approved_hint` „Plan jest zatwierdzony i tylko do odczytu.” (`lang/pl/ui.php:286`, użyty w `MonthPlan.vue:305`) przestanie być prawdziwy.
6. **Kopia TS wzoru jest uzasadniona w repo tylko dla propozycji.** `AGENTS.md:32` i docblock `resources/js/lib/to-spend-calculator.ts:9-12` mówią, że podgląd istnieje, bo „a proposal cannot be saved before approval”. Użycie podglądu przy edycji planu zatwierdzonego poszerza ten powód; reguła `AGENTS.md:30` wymaga podania powodu w planie. Sam wzór się nie zmienia.
7. **Kategorie bez pozycji planu nie mają dziś kwoty planu do edycji.** Odczyt planu zatwierdzonego obejmuje tylko zapisane pozycje (`MonthPlanProposal.php:95-102`). Kategoria dodana po zatwierdzeniu (tworzenie kategorii w wykonaniu jest dozwolone, `app/Http/Requests/StoreCategoryRequest.php:15-24`) albo kategoria z operacjami spoza planu jest wierszem `in_plan=false` z planem 0 (`MonthPlanController.php:109-112,189,193`). Dodanie dla niej pozycji przy edycji wymaga decyzji o `approved_amount_minor_units`, bo kolumna jest `NOT NULL`.
8. **Snapshot v5 już przenosi obie kwoty.** Backup zapisuje obie (`app/Actions/BackupUserData.php:18,180-181`), restore wymaga obu i odtwarza je bez zmian (`app/Actions/RestoreUserData.php:262,284-285`); round-trip z 150000 ≠ 160000 jest przetestowany (`tests/Feature/UserSnapshotCommandTest.php:172-218`). Jeśli S-03 nie zmieni schematu, wersji snapshotu nie trzeba podbijać. Jeśli zmieni, warunek `$version === BackupUserData::SNAPSHOT_VERSION` (`RestoreUserData.php:97`) musi objąć wersję 5 (np. `>= 5`).
9. **S-02 (`set-aside-and-draw`) nie ma jeszcze kodu, ale dotknie tych samych plików.** Gałąź ma jeden commit e9fd26e, tylko `change.md`; w worktree S-02 nie znaleziono niezatwierdzonych zmian w źródłach (porównanie `diff -rq` katalogów źródłowych; `git status` w tamtym worktree był zablokowany przez sandbox). Czy Set-aside dzieli się na zatwierdzony i bieżący, nie rozstrzyga żaden dokument (sekcja Open Questions).

## Detailed Findings

### 1. Model danych planu miesiąca

- `month_plans`: `id`, `budget_id` (FK, `cascadeOnDelete`), `month` (unsignedTinyInteger), `approved_at` (NOT NULL), timestamps; `unique(['budget_id','month'])` (`database/migrations/2026_11_02_091919_create_month_plans_table.php:15-21`).
- `month_plan_items`: `month_plan_id` (FK cascade), `category_id` (FK cascade), `approved_amount_minor_units`, `current_amount_minor_units`, timestamps; `unique(['month_plan_id','category_id'])` (`..._091922_create_month_plan_items_table.php:15-22`). Unikalność pozwala dopisać pozycję dla nowej kategorii do istniejącego planu.
- Modele: `MonthPlan` fillable `budget_id, month, approved_at` (`app/Models/MonthPlan.php:21`); `MonthPlanItem` rzutuje obie kwoty na int (`app/Models/MonthPlanItem.php:24-40`). Fabryka pozycji ustawia approved == current (`database/factories/MonthPlanItemFactory.php:20-27`).
- Usuwanie kategorii jest tylko w szkicu (`app/Policies/CategoryPolicy.php:15-18`), a plany istnieją tylko w wykonaniu, więc kaskada usuwająca pozycje planu nie zachodzi w wykonaniu. Archiwizacja jest dozwolona w wykonaniu (`CategoryPolicy.php:20-25`); pozycja zarchiwizowanej kategorii zostaje w planie z `is_archived` i `can_change_kind=false` (`MonthPlanController.php:192`).

### 2. Zapis: zatwierdzenie i brak edycji

- Trasa `month-plans.approve` (`routes/web.php:29-31`) → `MonthPlanController::approve` (`app/Http/Controllers/MonthPlanController.php:70-90`) mapuje pozycje na `[category_id => amount]` i woła akcję z `CarbonImmutable::today('UTC')` (`:82`); po sukcesie toast i przekierowanie na `month-plan?month=N`.
- `ApproveMonthPlanRequest`: autoryzacja `can('manageMonthPlan', $budget)` (`app/Http/Requests/ApproveMonthPlanRequest.php:11`), reguły `items` sometimes|array, `items.*.category_id` required|integer|distinct, `items.*.amount_minor_units` required|integer|min:0|max:1000000000000 (`:28-30`). Limit kwoty pochodzi z poprawki przeglądu S-01 (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:664`) i obowiązuje także edycję.
- `BudgetPolicy::manageMonthPlan`: właściciel i budżet w wykonaniu, bez sprawdzania stanu ani miesiąca (`app/Policies/BudgetPolicy.php:41-44`).
- `ApproveMonthPlan::handle` (`app/Actions/ApproveMonthPlan.php:20-78`), w transakcji (`:31`), na tej ścieżce:
  - budżet nie w wykonaniu → `AuthorizationException` (`:22-24`); miesiąc spoza 1..12 → błąd `items` (`:26-28`),
  - stan `approved` → „This month plan is already approved.” (`:35-37`); stan `none` → „A past month cannot be approved.” (`:39-41`),
  - poprzedni miesiąc w stanie propozycji → blokada (`:43-45`; reguła `MonthPlanProposal::isBlockedByPreviousMonth`, `app/Support/MonthPlanProposal.php:71-74`),
  - zbiór kategorii musi być równy zbiorowi propozycji (`:47-54`),
  - zapis planu z `approved_at = now()` (`:56-62`) i pozycji z approved == current (`:64-70`); wyścig na unikalności → ten sam błąd „already approved” (`:75-77`).
- Każde odrzucenie to `ValidationException` na kluczu `items` (`:80-83`), czyli 302 z błędem w sesji. Komunikaty są angielskie `__()` bez tłumaczenia w `lang/pl` — wzorzec istniejący, nie wprowadzany przez S-03.

### 3. Odczyt: gdzie kwota zatwierdzona, a gdzie bieżąca

- `MonthPlanProposal::planFor` dla zapisanego planu zwraca `MonthPlanReading('approved', approved_at, [category_id => current_amount_minor_units])` (`app/Support/MonthPlanProposal.php:95-102`). To jedyne miejsce w `app/`, które czyta `current_amount_minor_units` w logice (grep po `app/`; poza nim tylko Backup/Restore).
- Konsumenci tego odczytu:
  - strona: wiersze to suma kluczy odczytu i kategorii z operacjami w miesiącu (`MonthPlanController.php:105-112`); `plan_minor_units` = kwota z odczytu albo 0 (`:189`), `in_plan` = klucz istnieje w odczycie (`:193`); „do wydania” z PHP `ToSpendCalculator` (`:119-132`),
  - propozycja następnego miesiąca: `sourceFor` / `proposedAmount` (`MonthPlanProposal.php:129-166`), także styczeń z grudnia poprzedniego budżetu po nazwie i typie (`:138-144,160-166`).
- `approved_amount_minor_units`: brak czytelnika w `app/` poza Backup (`app/Actions/BackupUserData.php:180`); nie ma go w propsach (`MonthPlanController.php:141-152,181-194`). Pokazanie go wymagałoby nowego pola w `MonthPlanReading` (dziś `state`, `approvedAt`, `amounts`; `app/Support/MonthPlanReading.php:13-16`), nowego propsa, typu TS i asercji `assertInertia` w tym samym commicie (`AGENTS.md:26`).
- Według `CONTEXT.md:80` „plan” w To Spend i w „pozycji ponad plan” to Current Month Plan (albo propozycja przed zatwierdzeniem), więc S-03 nie zmienia, którą kwotę czyta wzór.

### 4. Stan miesiąca i istniejące reguły dostępności

- „Dziś” = `CarbonImmutable::today('UTC')` (`MonthPlanController.php:47,82`). Miniony/bieżący/przyszły: `MonthPlanController::monthTiming` (`:200`) i zdublowane `MonthPlanProposal::isPast` (`MonthPlanProposal.php:124-127`).
- Miniony miesiąc bez zapisu → `none`, nie da się go zatwierdzić (`MonthPlanProposal.php:105-107`, `ApproveMonthPlan.php:39-41`). Miniony zatwierdzony zostaje `approved` i jest pokazany tylko do odczytu (`tests/Feature/MonthPlanPageTest.php:130`).
- `can_approve` = stan `proposal` ∧ miesiąc nie miniony ∧ poprzedni nie jest propozycją (`MonthPlanController.php:144-146`).
- Precedens dla reguły „które miesiące”: `can_change_kind` = wydatek ∧ niezarchiwizowana ∧ `timing !== 'past'` (`MonthPlanController.php:192`), niezależnie od stanu planu. Przełącznik rodzaju działa więc już dziś na zatwierdzonych bieżących i przyszłych miesiącach.

### 5. Strona `MonthPlan.vue` i podgląd

- Formularz: komponent Inertia `<Form v-bind="MonthPlanController.approve.form({budget, month})">` (`resources/js/pages/MonthPlan.vue:314-323`). Widoczne `<Input v-model="amountInputs[...]">` nie ma `name` (`:446-453`); wysyłane są ukryte `items[i][category_id]` i `items[i][amount_minor_units]` renderowane tylko przy `isEditable` (`:324-340`).
- `amountInputs` startuje z `minorUnitsToMoneyInput(row.plan_minor_units)` (`:87-96`) i jest odświeżane przez watch na `[viewedMonth, monthPlan.state, budget.id]` (`:98-103`). Po zapisie edycji stan zostaje `approved`, więc watch nie zadziała; nie szkodzi, jeśli serwer zapisze dokładnie przesłane kwoty.
- Walidacja klienta (`parsedAmount`, 0..1e12) odpowiada FormRequestowi (`:108-123`); `allAmountsValid` blokuje wysłanie (`:132-134`). Podgląd: przy `!isEditable` wartość z serwera, inaczej `toSpendMinorUnits(...)` z planem 0 dla wierszy `in_plan=false` (`:136-154`).
- Przełączniki tylko do odczytu w szablonie: `isEditable` (`:81-85`, `:141`, `:324`, `:447`), blok przycisku `v-if="monthPlan.can_approve"` (`:465`), podpowiedź `approved_hint` / `proposal_hint` / `blocked_hint` (`:302-310`), badge stanu (`:256-263`).
- Wiersze `in_plan=false` są tylko do odczytu z badge „Poza planem” (`:399-404`).
- Przełącznik rodzaju: `router.patch(CategoryController.updateMonthItemKind.url(...), ..., { preserveState: true, preserveScroll: true })` (`MonthPlan.vue:177-197`) → `PATCH categories/{category}/month-item-kind` (`routes/web.php:35`) → `back()` (`app/Http/Controllers/CategoryController.php:76-81`). Przyciski są `type="button"` wewnątrz `<Form>` (`MonthPlan.vue:417-434`), więc formularz edycji współistnieje z nim tak jak dziś formularz propozycji.
- Klucze `month_plan` w `lang/pl/ui.php:279-309`: m.in. `state_approved` „Zatwierdzony” (`:284`), `approved_hint` (`:286`), `approve` „Zatwierdź” (`:288`), `plan`, `actual`, `not_in_plan`. Brak kluczy dla przycisku zapisu zmian, „plan zatwierdzony” jako kolumny i „plan końcowy”. Typ to `Record<string, string>` (`resources/js/types/shell.ts:41`), więc nowe klucze nie wymagają zmiany typu.
- Siatka wierszy `grid-cols-[1fr_7rem_7rem]` / `sm:grid-cols-[1fr_9rem_9rem]` (`MonthPlan.vue:350,371`): dodatkowa kolumna „plan zatwierdzony” oznacza zmianę w obu miejscach.

### 6. Para PHP/TS „do wydania”

- `app/Support/ToSpendCalculator.php` i `resources/js/lib/to-spend-calculator.ts`; testy `tests/Unit/ToSpendCalculatorTest.php` i `resources/js/lib/to-spend-calculator.test.ts` (wg `AGENTS.md:32`; plików testów nie otwierano).
- `AGENTS.md:30`: „Compute new month readings on the server and pass them as props; add a TS copy only when a preview must react before submit, and state the reason in the plan.” `AGENTS.md:32` uzasadnia kopię TS tylko propozycją. Edycja planu zatwierdzonego też wymaga reakcji przed zapisem, ale to nowy powód: plan powinien go podać i zaktualizować `AGENTS.md:32` oraz docblock `to-spend-calculator.ts:9-12`.
- `ToSpendCalculator.php:10` notuje „Set-aside … equals 0 until S-02” — S-02, nie S-03, zmienia wzór.

### 7. Snapshot

- Backup: `SNAPSHOT_VERSION = 5` (`app/Actions/BackupUserData.php:18`), eksport `month_plans` (`:70`) z obiema kwotami (`:180-181`); bez `created_at`/`updated_at` planów i pozycji.
- Restore: wersje 1–5; klucz `month_plans` wymagany tylko przy `$version === BackupUserData::SNAPSHOT_VERSION` (`app/Actions/RestoreUserData.php:97-98`), starsze dostają `[]` (`:34`); pozycja wymaga obu kwot (`:262`) i odtwarza je bez zmian (`:284-285`).
- Testy: round-trip z approved ≠ current (`tests/Feature/UserSnapshotCommandTest.php:172-218`), odrzucenie v5 bez `month_plans` (`:261`), odrzucenie zniekształconych planów (`:286`).

### 8. Testy dotyczące stanu zatwierdzonego

- `tests/Feature/ApproveMonthPlanTest.php`: zapis obu kwot (`:22`), bieżący i przyszły miesiąc (`:57`), odrzucenie drugiego zatwierdzenia z zachowaniem kwoty zatwierdzonej (`:93`).
- `tests/Feature/MonthPlanPageTest.php`: miniony zatwierdzony tylko do odczytu (`:130`), ponowne zatwierdzenie → błąd `items` (`:312`).
- `tests/Feature/MonthPlanProposalTest.php:77`: `test_approved_reading_uses_the_current_amount` — w sprawdzonych plikach to jedyny test odczytu z approved ≠ current (kolumna zmieniana bezpośrednio). Na poziomie strony wszystkie sprawdzone testy stanu zatwierdzonego mają approved == current, więc żaden nie dowodzi, że strona pokazuje kwotę bieżącą.
- Testy, które po S-03 zmienią znaczenie: `test_past_approved_month_is_read_only` (zostaje, jeśli miniony pozostaje zablokowany) oraz testy podwójnego zatwierdzenia (zostają — edycja to inna ścieżka niż ponowne zatwierdzenie).

### 9. Nakładanie się z S-02 `set-aside-and-draw`

- Stan na 2026-11-02: `git log development..set-aside-and-draw` → jeden commit e9fd26e „chore(set-aside-and-draw): start change”; diff tylko `context/changes/set-aside-and-draw/change.md`. Folder zmiany S-02 ma tylko `change.md` (status `new`), bez research i planu.
- S-02 według roadmapy doda kwotę „odkładam / dobieram” do planu (`context/foundation/roadmap.md:110-120`), więc prawdopodobnie dotknie tych samych miejsc co S-03:
  - migracje `month_plans` (kolumna Set-aside; ewentualnie para zatwierdzona/bieżąca),
  - `ApproveMonthPlan.php:56-70`, `ApproveMonthPlanRequest.php:28-30`,
  - `MonthPlanProposal.php:95-102` i `MonthPlanReading.php:13-16` (oba wycinki dodają pole),
  - `MonthPlanController.php:103-150` (payload, flagi),
  - `MonthPlan.vue:81-85,324,447,465` — najbardziej prawdopodobny konflikt tekstowy,
  - `routes/web.php:28-31`, `lang/pl/ui.php:279-309`,
  - snapshot: jeśli oba wycinki niezależnie podbiją wersję do 6, zderzą się (`BackupUserData.php:18`, `RestoreUserData.php:97`).
- `ToSpendCalculator` (PHP/TS) zmienia S-02; S-03 nie musi.

## Code References

- `database/migrations/2026_11_02_091922_create_month_plan_items_table.php:15-22` — pozycje z kwotą zatwierdzoną i bieżącą, unikalność (plan, kategoria)
- `database/migrations/2026_11_02_091919_create_month_plans_table.php:15-21` — plan, `approved_at` NOT NULL, unikalność (budżet, miesiąc)
- `app/Actions/ApproveMonthPlan.php:35-37,47-54,64-70` — odrzucenie stanu `approved`, równość zbioru kategorii, zapis approved == current
- `app/Support/MonthPlanProposal.php:95-102` — odczyt planu zatwierdzonego z kwoty bieżącej
- `app/Support/MonthPlanProposal.php:129-166` — źródło propozycji następnego miesiąca
- `app/Support/MonthPlanReading.php:13-16` — odczyt bez slotu na kwoty zatwierdzone
- `app/Http/Controllers/MonthPlanController.php:105-112,144-146,189-193` — wiersze, `can_approve`, `plan_minor_units`, `can_change_kind`, `in_plan`
- `app/Http/Requests/ApproveMonthPlanRequest.php:28-30` — reguły kwot
- `app/Policies/BudgetPolicy.php:41-44` — `manageMonthPlan`
- `routes/web.php:29-31,35` — zatwierdzenie, rodzaj pozycji
- `resources/js/pages/MonthPlan.vue:81-85,98-103,136-154,302-310,314-340,446-456,465` — przełącznik edycji, watch, podgląd, podpowiedź, formularz, pole kwoty, przycisk
- `resources/js/lib/to-spend-calculator.ts:9-12`, `app/Support/ToSpendCalculator.php:10` — para wzoru i uzasadnienie kopii
- `lang/pl/ui.php:284-288` — `state_approved`, `approved_hint`, `approve`
- `app/Actions/BackupUserData.php:18,180-181`, `app/Actions/RestoreUserData.php:97-98,262,284-285` — snapshot v5
- `AGENTS.md:26,30,32` — kontrakt propsów, reguła kopii TS, para To Spend

## Architecture Insights

- Plany są przechowywane tylko po zatwierdzeniu; propozycja jest zawsze wyliczana (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:66,82`). Edycja planu zatwierdzonego jest więc pierwszą operacją UPDATE na `month_plan_items`.
- Wzorzec analogiczny w domenie: roczny Initial Plan (niezmienny) i Current Plan (edytowalny) — `CONTEXT.md:67-72` wprost odwołuje się do tej pary. Wynik dla planu miesiąca: kwota zatwierdzona nie zmienia się po zatwierdzeniu, edycja dotyczy tylko kwoty bieżącej.
- Odczyty miesiąca liczy serwer i przekazuje propsami; kopia TS tylko dla podglądu przed zapisem (`AGENTS.md:30`).
- Błędy domenowe planu idą jako `ValidationException` na `items` i wracają przez `InputError :message="errors.items"` — wzorzec do powtórzenia dla edycji.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:56,77` — plan zatwierdzony „tylko do odczytu do S-03” dla bieżącego i przyszłych miesięcy; „Zmiana zatwierdzonego planu: to S-03”. Werdykt: zgodne z kodem (`MonthPlan.vue:81-85`).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:283` — rozdzielone kwoty, „żeby S-03 mógł je rozróżnić bez migracji danych”. Werdykt: zgodne (migracja `..._091922_...:18-19`).
- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:666` — kolejność zatwierdzania (M tylko po zatwierdzeniu albo minięciu M−1). Werdykt: zgodne (`MonthPlanProposal.php:71-74`, `ApproveMonthPlan.php:43-45`).
- `context/archive/2026-11-02-month-plan-and-to-spend/reviews/plan-review.md`, `reviews/impl-review.md` — wszystkie ustalenia oznaczone jako FIXED; brak otwartych pozycji przekazanych do S-03 (raport badacza; pliki nie były czytane w całości w głównym kontekście).
- `context/foundation/prd.md:105-106` i `shape-notes.md:164-165` — FR-003 z notą sokratejską: swobodna edycja mogłaby podnosić plan, żeby liczba wychodziła na plus; rozstrzygnięcie: zmiany są dozwolone, a podsumowanie pokazuje plan zatwierdzony i końcowy. Wniosek: dokumenty nie nakładają limitu na edycję; ochroną jest podsumowanie (S-09).
- `.docs/prd.md:480-487` — „formalne zatwierdzanie zmian budżetowych” jest poza zakresem MVP; argument przeciw procesowi akceptacji każdej zmiany.

## Related Research

- `context/archive/2026-11-02-month-plan-and-to-spend/research.md` — stan kodu przed S-01 (model danych, snapshot, `Month Remaining`).

## Open Questions

Decyzje produktowe dla `/10x-plan` (dokumenty ich nie rozstrzygają):

1. **Które miesiące można edytować.** „W trakcie miesiąca” (`context/foundation/prd.md:105`, `CONTEXT.md:72`, ADR-0033 ¶1) czytane dosłownie oznacza tylko bieżący; S-01 `plan.md:56` sugeruje także przyszłe zatwierdzone. Miniony: `CONTEXT.md:72` („at the end of the month it is the final plan”) sugeruje blokadę, ale nie mówi tego wprost. Precedens w kodzie: `can_change_kind` = `timing !== 'past'` (`MonthPlanController.php:192`).
2. **Kategorie bez pozycji planu.** Czy przy edycji można nadać kwotę kategorii dodanej po zatwierdzeniu albo wierszowi „Poza planem”? ADR-0033 ¶2 mówi „a Category added mid-year joins at 0” (o propozycji), a domena wydatków nieregularnych zakłada wpisanie kategorii jednorazowej do planu, „gdy znane” (`context/foundation/prd.md:201`). Jeśli tak — jaka `approved_amount_minor_units` dla nowej pozycji: 0 (bez migracji) czy `NULL` = „nie było przy zatwierdzeniu” (migracja, zmiana restore, podbicie snapshotu)? Ma to znaczenie dla porównania w S-09.
3. **Set-aside a podział zatwierdzony/bieżący.** ADR-0033 ¶1 wymienia Set-aside jako część planu i mówi o zamrożonych „amounts”; `CONTEXT.md:96` i `prd.md:92` znają jedno „odkładanie zamierzone”. Decyzja wspólna z S-02, przed planowaniem któregokolwiek z nich, razem z jednym podbiciem wersji snapshotu.
4. **Cofnięcie zatwierdzenia / ponowne zatwierdzenie.** Brak w dokumentach; „immutable afterwards” (`CONTEXT.md:68`) i odrzucenie ponownego zatwierdzenia (`ApproveMonthPlan.php:35-37`) wskazują „nie”. Cofnięcie do propozycji ponownie wywołałoby ostrzeżenie S-07 „brak zatwierdzonego planu od 3. dnia” (`CONTEXT.md:64`).
5. **Czy S-03 pokazuje plan zatwierdzony obok bieżącego.** Ryzyko z roadmapy (`roadmap.md:131`) wymaga rozróżnialności danych od pierwszej zmiany — dane są rozróżnialne już dziś (pkt 1 Summary). Widok obu kwot należy do podsumowania (S-09); czy S-03 ma dodać kolumnę lub znacznik „zmieniono”, jest wyborem zakresu.
6. **Pozycje zarchiwizowanych kategorii w planie.** Czy ich kwota bieżąca jest edytowalna (dziś `can_change_kind=false` dla zarchiwizowanych, `MonthPlanController.php:192`)?
7. **Propagacja do propozycji.** Edycja M zmienia niezatwierdzone propozycje M+1… (Summary pkt 4). Zgodne z ADR-0033; do potwierdzenia, czy UI ma to sygnalizować.

Luki badania: nie otwierano plików testów `ToSpendCalculatorTest.php` / `to-spend-calculator.test.ts`, `.docs/design-system.md` ani pełnych treści przeglądów S-01; `git status` w worktree S-02 był niedostępny (zastąpiony porównaniem plików). Żadna z luk nie zmienia odpowiedzi na pytanie badawcze.
