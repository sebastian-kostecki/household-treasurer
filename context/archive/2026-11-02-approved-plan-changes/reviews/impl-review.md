<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: S-03 Zmiana zatwierdzonego planu miesiąca

- **Plan**: context/changes/approved-plan-changes/plan.md
- **Scope**: Full plan (Phase 1 and 2 of 2)
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-02
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warnings, 3 observations (all fixed in triage)

## Verification run

- `ChangeMonthPlanTest`, `ApproveMonthPlanTest`, `MonthPlanPageTest`, `MonthPlanProposalTest` on PostgreSQL (Sail image on worktree): 62 passed (435 assertions)
- `route:list --name=month-plans`: `PATCH budgets/{budget}/month-plans/{month}` `month-plans.update` present
- `pint --test`: passed
- `npm run types:check`: passed; `npm run check`: passed (195 files formatted, no warnings)
- Not re-run: full `composer ci:check` (2.4) and manual preview steps 2.5-2.10 (ticked in Progress with commit f30ae85; a preview container was running)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Findings

### F1 — Zapis wysyła wszystkie wiersze: ostatni zapis wygrywa

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/MonthPlan.vue (ukryte inputy `planRows`, ok. 372-385); app/Actions/ChangeMonthPlan.php:69
- **Detail**: Formularz zmiany przesyła każdy edytowalny wiersz, także nietknięty i kandydata do dołączenia jako 0. Akcja bezwarunkowo ustawia `current = kwota` dla istniejącej pozycji. Przy dwóch kartach/urządzeniach późniejszy zapis cicho cofa zmiany z wcześniejszego. Gdy w międzyczasie kategoria dołączyła w innej karcie, a stara karta wysyła ją jako 0, kwota spada do 0. Brak kontroli wersji (`updated_at`). Plan świadomie zakładał „formularz przesyła wszystkie wiersze”, więc to ryzyko planu, nie odchylenie.
- **Fix**: Wysyłać tylko wiersze, których sparsowana kwota różni się od `plan_minor_units` (akcja już obsługuje częściowe mapy).
  - Strength: Zawęża okno nadpisania do pól faktycznie zmienionych; `ChangeMonthPlan` nie wymaga zmian.
  - Tradeoff: Zmiana logiki ukrytych inputów i indeksów w `MonthPlan.vue` (podgląd musi nadal czytać wszystkie wiersze); trzeba zaktualizować plan/test, bo „zawsze wszystkie wiersze” jest w planie.
  - Confidence: MED — agent czytał kod, nie odtwarzał scenariusza w dwóch kartach.
  - Blind spot: Czy pusty `items` (nic nie zmieniono) ma być blokowany przycisk „Zapisz zmiany”; reguła `required|array` odrzuci pustą tablicę.
- **Decision**: FIXED via Fix now — tryb zmiany wysyła tylko zmienione wiersze (`submittedAmounts`), „Zapisz zmiany” wyłączony bez zmian; plan.md zaktualizowany

### F2 — Luki w testach

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/ChangeMonthPlanTest.php:82; tests/Feature/MonthPlanPageTest.php:253
- **Detail**: (a) test „kwota 0 bez pozycji” sprawdza tylko brak rekordu, nie że wiersz na stronie zostaje `in_plan=false` (plan to wymieniał); (b) „zarchiwizowana nie jest kandydatem” opiera się tylko na liczbie wierszy; (c) brak asercji `can_change=true` dla przyszłego zatwierdzonego miesiąca na stronie i testu trasy dla minionego/niezatwierdzonego miesiąca (przekierowanie + błąd `items`).
- **Fix**: Dodać te asercje do istniejących testów.
- **Decision**: FIXED via Fix now — asercje wiersza `in_plan=false`/kandydata, jawny brak zarchiwizowanej kategorii, test strony przyszłego miesiąca (`can_change=true`), test trasy dla minionego i niezatwierdzonego miesiąca; 37 testów na PostgreSQL przechodzi

### F3 — Nieplanowany `catch UniqueConstraintViolationException`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: app/Actions/ChangeMonthPlan.php:39,86-88
- **Detail**: Plan nie przewidywał mapowania naruszenia unikalności na błąd `items`. Jest poza transakcją, więc nie psuje transakcji PostgreSQL; to nieszkodliwa siatka bezpieczeństwa przy blokadzie. Poza tym `context/foundation/roadmap.md` ma niezacommitowaną modyfikację niezwiązaną z trzema commitami zmiany.
- **Fix**: Zostawić; dopisać jedno zdanie do planu (addendum) i zdecydować osobno o `roadmap.md`.
- **Decision**: FIXED via Fix — catch zostaje, addendum w plan.md (sekcja akcji); roadmap.md poza tą zmianą

### F4 — Dodatkowe zapytania o kategorie przy `can_change`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Http/Controllers/MonthPlanController.php:140-146
- **Detail**: `MonthPlanProposal::forBudget` już wczytuje `$budget->categories`, a kontroler przy `can_change` robi dwa dodatkowe zapytania o kategorie (stała liczba, bez N+1; plan dopuszczał „najwyżej jedno”).
- **Fix**: Wyznaczyć kandydatów z wczytanej relacji: `$budget->categories->whereNull('archived_at')->whereNotIn('id', $rowIds)`.
- **Decision**: FIXED via Fix now — kandydaci z wczytanej relacji `$budget->categories`

## Triage

Fixed: F1, F2, F3, F4. Po poprawkach: 64 testy na PostgreSQL, pint, `types:check`, `check` przechodzą.
