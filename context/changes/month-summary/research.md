---
date: 2026-11-03T06:40:00+01:00
researcher: Sebastian Kostecki (z Claude Sonnet 5.5)
git_commit: 61e8faafcde43d7396d1a8aa23147a8ec7e48b38
branch: development
repository: household-treasurer
topic: "S-09 month-summary: co już istnieje pod podsumowanie minionego miesiąca z planem, a czego brakuje"
tags: [research, codebase, month-summary, month-plan, set-aside, month-result]
status: complete
last_updated: 2026-11-03
last_updated_by: Sebastian Kostecki (z Claude Sonnet 5.5)
---

# Research: Podsumowanie miesiąca (S-09, `month-summary`)

**Date**: 2026-11-03
**Git Commit**: 61e8faa (kod czytany na branchu `development`; kotwice `file:line` dotyczą tego commita)
**Branch**: development
**Repository**: household-treasurer

## Research Question

`change.md` nie zawiera zamiaru, więc pytanie wynika z roadmapy (`context/foundation/roadmap.md:195-205`, S-09, PRD refs US-01, FR-009, FR-013): jakie dane, kod, powierzchnie UI i wcześniejsze decyzje istnieją pod „podsumowanie miesiąca, w tym odkładanie zamierzone i realne", a czego brakuje, żeby `/10x-plan` mógł je zaplanować bez ponownego odkrywania.

## Summary

- **Podsumowania miesiąca nie ma w kodzie.** Wzorce `month.?summary|podsumowanie|MonthSummary` w `app routes resources lang database tests` dają tylko nazwy testów (`tests/Feature/MonthToSpendTest.php:44`) oraz jedno zdanie w tekście UI (`lang/pl/ui.php:289`, podpowiedź „zapisane do podsumowania miesiąca"). Brak trasy, kontrolera, strony i kluczy tłumaczeń.
- **Wymagana zawartość jest ustalona** (`CONTEXT.md:95-97`, `prd.md:92`): plan zatwierdzony i końcowy, wykonanie, odchylenia, odkładanie zamierzone i realne, dobrane kwoty także w skali roku. Bez przenoszenia i zamykania miesiąca.
- **Większość danych już jest.** Dla minionego miesiąca z planem: kwoty zatwierdzone i bieżące (końcowe) na pozycjach, wykonanie na kategorię, Wynik miesiąca (= realne odkładanie), odchylenie pozycji. Brakuje trzech rzeczy: odczytu zatwierdzonego odkładania, sumy „dobieram" w roku i agregatu odchyleń na poziomie miesiąca.
- **Pozostaje jedna część FR-013**: PRD (`prd.md:136`) obiecuje dla minionych miesięcy z planem „podsumowanie i końcowe »do wydania«". S-06 dostarczył tylko żywe „do wydania" i jawnie zostawił podsumowanie dla S-09 (`context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:74`).
- **Kilka definicji jest nierozstrzygniętych** (sekcja Open Questions). Najważniejsza: znaczenie „dobrane kwoty w skali roku".

## Detailed Findings

### 1. Przechowywanie planu miesiąca

- Tabela `month_plans`: `budget_id`, `month`, `approved_at` (NOT NULL), `unique(budget_id, month)` (`database/migrations/2026_11_02_091919_create_month_plans_table.php:16-22`). Wiersz istnieje tylko dla zatwierdzonego planu; Propozycja nie jest zapisywana.
- Odkładanie: `approved_set_aside_minor_units` i `current_set_aside_minor_units`, bigint ze znakiem, domyślnie 0; dodatnie to odkładanie, ujemne to „dobieram" (`database/migrations/2026_11_02_112841_add_set_aside_to_month_plans_table.php:16-17`).
- Pozycje: `month_plan_items` z `approved_amount_minor_units` i `current_amount_minor_units`, `unique(month_plan_id, category_id)` (`database/migrations/2026_11_02_091922_create_month_plan_items_table.php:16-21`).
- Zapis przy zatwierdzeniu ustawia zatwierdzone i bieżące na tę samą wartość (`app/Actions/ApproveMonthPlan.php:59-74`). `ChangeMonthPlan` zapisuje wyłącznie pola `current_*` (`app/Actions/ChangeMonthPlan.php:52-53`) i odrzuca zmianę planu minionego miesiąca (`:36-37`). Plan końcowy to więc po prostu `current_*` po upływie miesiąca; nie ma flagi „zamknięty".
- Kategoria dołączona po zatwierdzeniu dostaje `approved = 0` (`ChangeMonthPlan.php:82-91`); S-09 nie odróżni „zatwierdzono 0" od „dołączono później", co zaakceptowano świadomie (`context/archive/2026-11-02-approved-plan-changes/plan-brief.md:63`).
- **Rodzaj pozycji (Bill/Running) nie jest zapisywany per miesiąc**, tylko na `Category` (`database/migrations/2026_11_02_090956_add_month_item_kind_to_categories_table.php:14`). Dla minionego miesiąca odczyt użyłby bieżącego rodzaju kategorii. UI blokuje zmianę rodzaju w minionych miesiącach (`MonthPlanController.php:250`), ale kategoria może zmienić rodzaj z innej strony (`routes/web.php:38`).

### 2. Odczyt planu i „do wydania" dla minionego miesiąca

- `MonthPlanReading` niesie `state`, `approvedAt`, `amounts` (bieżące), `setAsideMinorUnits` (tylko bieżące) i `approvedAmounts` (`app/Support/MonthPlanReading.php:15-21`). **Zatwierdzone odkładanie nie jest wystawione**; `MonthPlanProposal` zwraca `current_set_aside_minor_units` (`app/Support/MonthPlanProposal.php:104`). Kolumna jest tylko zapisywana oraz kopiowana w backupie (`BackupUserData.php:177`, `RestoreUserData.php:257`).
- Stan: istnieje wiersz → `approved`; brak wiersza i miesiąc minął → `none`; w pozostałych przypadkach `proposal` (`MonthPlanProposal.php:93-124`, `isPast` `:126-129`). „Miesiąc ma plan" obejmuje więc Propozycję (`prd.md:206`).
- `ToSpendCalculator` (`app/Support/ToSpendCalculator.php:15-36`): przychód i rachunek liczą wykonanie, jeśli jest dodatnie, inaczej plan; pozycja bieżąca liczy `max(plan, wykonanie)`; na końcu odejmuje się odkładanie ze znakiem. Dla minionego zatwierdzonego miesiąca liczba jest liczona na żywo z bieżących kwot i bieżących operacji; nie ma wartości zamrożonej (`MonthToSpend.php:54-75`; `context/archive/2026-11-02-to-spend-replaces-remaining/research.md:123`; test `tests/Feature/BudgetMonthResultTest.php:191`).
- Dla minionego miesiąca bez planu `toSpend` jest null (`MonthToSpend.php:54-75`; test `MonthToSpendTest.php:68`).

### 3. Wykonanie i Wynik (realne odkładanie)

- `BudgetMonthResult::forViewedMonth(Budget, int $month)` sumuje operacje budżetu z datą w danym miesiącu według typu i zwraca przychody, wydatki oraz `month_result = przychody − wydatki` (`app/Support/BudgetMonthResult.php:18-36`). Obejmuje wszystkie kategorie, w tym zarchiwizowane; zwroty netują się jako kwoty ujemne. Jedyny wywołujący to `BudgetController.php:89`.
- `CONTEXT.md:103-104` i `prd.md:208` definiują Wynik jako realne odkładanie na koniec miesiąca, więc nowy wzór nie jest potrzebny.
- `MonthCategoryActuals::forMonth(Budget, int $month)` daje wykonanie na kategorię (przychody i wydatki) jednym zapytaniem (`app/Support/MonthCategoryActuals.php:16-61`).
- Pensja zaksięgowana w poprzednim miesiącu zawyża tamten miesiąc i zaniża kolejny; rozstrzygnięto, że nadwyżka wchodzi do następnego miesiąca przez „dobieram", bez nowego pola (`prd.md:124`).

### 4. Odchylenia

- `MonthItemDeviation` (`app/Support/MonthItemDeviation.php:20-46`): odchylenie = wykonanie − plan, a null dla rachunku lub przychodu bez dodatniej kwoty; `isOverPlan` = wydatek z wykonaniem powyżej planu.
- Wykorzystuje je tylko `MonthPlanController::itemRow` (`:230-271`) i porównuje **plan bieżący** z wykonaniem. `itemRow` wystawia też `approved_minor_units`, ale nikt nie liczy różnicy zatwierdzony−bieżący ani zatwierdzony−wykonanie.
- Nie znaleziono agregatu odchyleń na poziomie miesiąca (brak klasy sumującej `MonthItemDeviation`; zakres: `app routes resources`).
- `CONTEXT.md` nie definiuje słowa „odchylenia" poza wpisem Month Summary (`context/archive/2026-11-02-month-items-plan-vs-actual/research.md:88`).

### 5. „Dobrane kwoty w skali roku"

- Dobieranie to ujemna wartość odkładania (kolumny powyżej). Nie znaleziono żadnej sumy tej kolumny po miesiącach: `set_aside_minor_units` w `app resources/js database/factories routes` występuje tylko przy zapisie, walidacji, backupie, fabryce i `MonthPlan.vue`; `MonthPlan::` i `monthPlans` w `app` tylko w Budget, Proposal, Approve, Change, Backup, Restore.
- Ostrzeżenie o dobieraniu ponad Spendable Liquidity istnieje tylko w TypeScript (`resources/js/lib/draw-warning.ts:6`) i dotyczy bieżących i przyszłych miesięcy, nie minionych (`context/archive/2026-11-02-set-aside-and-draw/plan-brief.md`, tabela decyzji).
- Plany zatwierdzone przed S-02 mają odkładanie 0 na stałe (`context/archive/2026-11-02-set-aside-and-draw/plan.md:56`).

### 6. Powierzchnie UI

- Stack: Laravel + Inertia + Vue 3 `<script setup lang="ts">`, Tailwind, shadcn-vue; trasy przez Wayfinder; teksty z `lang/pl/ui.php` przez `useTranslations` (`resources/js/composables/useTranslations.ts:5-9`). Kwoty to całkowite grosze, formatowanie w `resources/js/lib/format.ts` (`formatMoney`, `formatSignedMoney`).
- Trasy: `GET dashboard` (`routes/web.php:21`), `GET budget` (`:24`), `GET month-plan?month=N` (`:30`), zatwierdzanie i zmiana planu (`:31-36`).
- **Strona Plan miesiąca** (`MonthPlanController::show` `:31-71`, `monthPlanPayload` `:143-203`, `resources/js/pages/MonthPlan.vue`): dla minionego zatwierdzonego miesiąca jest tylko do odczytu (`can_change=false`), pokazuje kartę „do wydania", kartę odkładania i tabele pozycji z kolumną „Zatwierdzono". Nie ma bloku podsumowania ani etykiety „plan końcowy" (zakres: przegląd kontrolera, `MonthPlan.vue` i `lang/pl/ui.php`).
- **Strona Budżet** (`BudgetController.php:121-130`, `resources/js/components/BudgetMonthStrip.vue:64-117`): pokazuje „do wydania" ze znaczkiem Zatwierdzony/Niezatwierdzony oraz Wynik (`BudgetMonthResultSummary.vue`); nic nie rozgałęzia się po `viewedMonthTiming === 'past'` poza znaczkiem „Dziś".
- **Dashboard** pokazuje wyłącznie bieżący miesiąc (`DashboardController.php:100-107`), więc podsumowanie tam nie pasuje.
- „Pozostało w miesiącu": backend dalej wysyła `pacedPoolMonthRemainingMinorUnits` (`BudgetController.php:122`), a `Budget.vue:82` tylko typuje prop. Nie znaleziono komponentu go wyświetlającego (zakres: `resources/js/pages` i `resources/js/components`; użycia w `resources/js/lib` dotyczą ostrzeżeń przy operacji i Imporcie). Dla minionego miesiąca bez planu widoczny jest dziś tylko Wynik i komunikat „Ten miesiąc nie ma planu" (`BudgetMonthStrip.vue:64-69`).
- Naturalne miejsca podpięcia: (a) `monthPlanPayload` + `MonthPlan.vue` między kartą „do wydania" a tabelami, bo strona ma już wiersze zatwierdzone/bieżące/wykonanie i znacznik `past`; (b) `BudgetMonthStrip.vue` jako skrót lub link. S-01 zaplanował stronę Plan miesiąca tak, żeby „S-04/S-05/S-09 miały gdzie rosnąć" (`context/archive/2026-11-02-month-plan-and-to-spend/plan-brief.md:39`).

### 7. Testy, które dotykają tych powierzchni

- `tests/Feature/MonthPlanPageTest.php` (m.in. `:173` miniony zatwierdzony miesiąc tylko do odczytu, `:156` miniony bez planu bez liczby), `BudgetMonthResultTest.php` (`:191`, `:233`), `MonthToSpendTest.php`, `ApproveMonthPlanTest.php`, `ChangeMonthPlanTest.php`, `MonthPlanProposalTest.php`, `MonthCategoryActualsTest.php`, `tests/Unit/MonthItemDeviationTest.php`. Żaden nie dotyczy podsumowania miesiąca. Odpowiedniki w TypeScript: `resources/js/lib/to-spend-calculator.ts`, `month-item-deviation.ts` (testy vitest).

## Code References

- `app/Support/MonthPlanReading.php:15-21` - odczyt planu; brak zatwierdzonego odkładania
- `app/Support/MonthPlanProposal.php:93-124` - stany `approved` / `none` / `proposal`
- `app/Support/BudgetMonthResult.php:18-36` - Wynik miesiąca (realne odkładanie)
- `app/Support/MonthCategoryActuals.php:16-61` - wykonanie na kategorię w miesiącu
- `app/Support/MonthItemDeviation.php:20-46` - odchylenie pozycji i „ponad plan"
- `app/Support/ToSpendCalculator.php:15-36`, `app/Support/MonthToSpend.php:32-95` - „do wydania" i jego składanie
- `app/Actions/ChangeMonthPlan.php:36,52-53,82-91` - miniony plan niezmienny; zapis tylko `current_*`; dołączone kategorie z approved 0
- `app/Http/Controllers/MonthPlanController.php:143-271` - payload strony i `itemRow`
- `resources/js/pages/MonthPlan.vue`, `resources/js/components/BudgetMonthStrip.vue:64-117` - dotychczasowy odczyt minionego miesiąca
- `lang/pl/ui.php` - grupa `month_plan` (`:279-328`), `budget.month_result` (`:241`)

## Architecture Insights

- Czyste klasy odczytu w `app/Support/*` (bez efektów ubocznych), mutacje w `app/Actions/*`, kontrolery renderują Inertię; kwoty to całkowite grosze; `'past'|'current'|'future'` to ciągi znaków wyliczane osobno w `MonthPlanController::monthTiming` i `MonthRemainingCalculator::monthTiming`.
- Predykaty liczone po stronie PHP mają lustro w TypeScript (kalkulator „do wydania", odchylenie) dla podglądów na żywo; podsumowanie minionego miesiąca jest tylko do odczytu, więc nie wymaga lustra.
- `MonthPlanProposal::forBudget` ładuje relacje i budżet poprzedniego roku; to wniosek z kodu (nie pomiar), że odczyt całego roku powinien współdzielić jedną instancję zamiast wołać `MonthToSpend::forMonth` dla każdego miesiąca.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-domain-decisions/plan.md:124-143` - wpisy Month Summary, Current Month Plan i Month Result w `CONTEXT.md`; copy „plan miesiąca" w trakcie miesiąca, „plan końcowy" w podsumowaniu.
- `context/archive/2026-11-02-set-aside-and-draw/plan.md:64,71` - dwie kolumny odkładania zapisane właśnie „żeby S-09 porównał obie wartości"; podsumowanie (zamierzony i faktyczny Set-aside, Draw w roku) oddane do S-09. Ostrzeżenie terminologiczne: „real set-aside" z `CONTEXT.md:104` to Wynik, nie Set-aside (`.../research.md:176`).
- `context/archive/2026-11-02-approved-plan-changes/plan.md:5,43` - zmiany dotyczą tylko `current_*`, więc S-09 pokaże plan zatwierdzony i końcowy; porównanie planów oddane do S-04/S-09.
- `context/archive/2026-11-02-month-items-plan-vs-actual/plan.md:117` - `MonthItemDeviation` ma być jednym miejscem predykatów dla strony, S-07 i S-09.
- `context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:74` - miniony zatwierdzony miesiąc pokazuje tylko „do wydania" na żywo; podsumowanie to S-09; miniony bez planu pokazuje Wynik i znacznik braku planu.
- Zależności S-02, S-03, S-04 (oraz S-01, S-06, F-01) są zarchiwizowane (`context/archive/2026-11-02-*`, `status: archived`). Roadmapa nadal pokazuje w tabeli handoff dla S-09 „Czeka na S-02, S-03 i S-04" (`roadmap.md:232`), co jest nieaktualne.
- ADR: `.docs/adr/0033-month-plan-beside-annual-budget.md` (jedyny ADR o planie miesiąca; nie opisuje zawartości podsumowania), ADR-0024 (Month Remaining, częściowo zastąpiony), ADR-0030 (Year Close - osobny rytuał roczny).

### Sprzeczności w dokumentach (obie strony)

1. FR-013: `prd.md:136` „pokazują podsumowanie i końcowe »do wydania«" vs `to-spend-replaces-remaining/plan.md:74` „tylko »do wydania« na żywo". Rozstrzygnięcie: druga połowa FR-013 jest należna S-09.
2. Miniony miesiąc a plan: ADR-0033 `:3` „Past months stay without a Month Plan" i `CONTEXT.md:64` „current and future months" vs `CONTEXT.md:56`, `prd.md:136`, ADR-0033 `:7`, które zakładają plan zatwierdzony w minionym miesiącu. Kod obsługuje oba (`MonthPlanProposal.php:93-124`).
3. `shape-notes.md:195` („minione miesiące zachowują stary odczyt") to wcześniejsza wersja FR-013, zastąpiona przez `prd.md:136`.

## Related Research

- `context/archive/2026-11-02-to-spend-replaces-remaining/research.md` - najlepsza wcześniejsza analiza tych samych powierzchni i inwentarz testów.
- `context/archive/2026-11-02-set-aside-and-draw/research.md`, `.../approved-plan-changes/research.md`, `.../month-items-plan-vs-actual/research.md`.

## Open Questions

Dla `/10x-plan` (decyzje produktowe, nie fakty do dalszego badania):

1. **„Dobrane kwoty w skali roku"**: suma ujemnych odkładań po miesiącach roku budżetu? Z planu zatwierdzonego czy bieżącego? Czy liczą się miesiące bez planu (nie mają Draw)? Nic w dokumentach tego nie definiuje.
2. **Porównanie zamierzonego z realnym**: odkładanie zamierzone to wartość ze znakiem (dobieranie jest ujemne), a Wynik to przychody − wydatki. Brak ustalonej konwencji znaku i tego, co jest „odchyleniem odkładania". Potrzebne są też osobne etykiety UI dla „odkładam" i „Wynik" (ostrzeżenie `set-aside-and-draw/research.md:176`).
3. **Co znaczy „odchylenia" w podsumowaniu**: bieżący plan − wykonanie (istniejący predykat), zatwierdzony − bieżący (zmiany planu), czy oba; i czy podsumowanie ma sumę na poziomie miesiąca.
4. **Powierzchnia**: sekcja na stronie Plan miesiąca, nowa strona, czy skrót na Budżecie (z tego, co sprawdzono, wszystkie są wykonalne; wybór jest produktowy).
5. **Odczyt na żywo vs zamrożenie**: PRD (`prd.md:203`) wskazuje odczyt na żywo („późna operacja zmienia podsumowanie"); plan nie zakłada zamrażania.
6. **Rodzaj pozycji w minionym miesiącu**: bez snapshotu podsumowanie użyje bieżącego rodzaju kategorii; do decyzji, czy to akceptowalne.
7. **„Stary odczyt" dla minionego miesiąca bez planu**: dziś nie ma widocznego „Pozostało w miesiącu" (sekcja 6); do decyzji, czy FR-013 wymaga jego przywrócenia, czy wystarczy Wynik i znacznik braku planu.
8. **Niesprawdzone**: nie uruchamiano testów ani aplikacji; nie przeszukano `.docs/specs` poza ADR-0033; nie sprawdzono innych worktree i branchy (np. prace nad tempem wydatków) pod kątem kolidujących zmian w `MonthPlanController` i `lang/pl/ui.php`.
