<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: budget-view-ui

- **Plan**: context/changes/budget-view-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5, 6
- **Date**: 2026-11-03
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical 2 warnings 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated checks re-run in this review: `npm test` (106 passed), `npm run check`, `npm run types:check`, literal scan (0 hits), `grep :checked|@update:checked Budget.vue` (none), `grep md:hidden BudgetCategoryList.vue` (none), one `<draggable`; Feature tests (BudgetKitchenSink, Budget, ArchiveCategory, BudgetMonthRemaining, MonthPlanPage) 118 passed on PostgreSQL. Earlier same-session `composer ci:check`: 818 passed, 2 skipped.

Note: runs against the shared `testing` database produced random deadlocks (SQLSTATE 40P01) while another session's `php artisan test` container was running. Not a code defect; use a dedicated database when other sessions run tests.

## Findings

### F1 — Lista kategorii bez ról tabeli (a11y)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/BudgetCategoryList.vue:223-322, BudgetCategoryRow.vue:122, BudgetCategoryHeaderRow.vue:262
- **Detail**: Prawdziwy `<table>` zastąpiony siatką `div` bez `role="table|row|columnheader|cell"`, więc nawigacja po tabeli w czytniku ekranu zniknęła. Od `md` wiersz nagłówka zostaje w drzewie dostępności, a każda komórka ma jeszcze `sr-only` etykietę (`BudgetAmountCell`), więc nazwa kolumny jest czytana dwa razy. `.operations-grid` ma ten sam kompromis.
- **Fix A ⭐ Recommended**: Oznaczyć wiersz nagłówka `aria-hidden="true"` od `md` (etykiety `sr-only` w komórkach zostają źródłem nazwy).
  - Strength: Usuwa podwójne czytanie bez zmiany układu; spójne z jednym markupem na każdej szerokości.
  - Tradeoff: Czytnik nadal nie ma semantyki tabeli, tylko listę komórek z etykietami.
  - Confidence: MED — nie sprawdzono z prawdziwym czytnikiem ekranu.
  - Blind spot: Zachowanie `aria-hidden` razem z tooltipami `ColumnHeaderHint` w nagłówku.
- **Fix B**: Dodać role `table/row/columnheader/cell` do siatki.
  - Strength: Zachowuje nawigację po tabeli.
  - Tradeoff: Role na `display: grid` i `contents` bywają niespójne między przeglądarkami; ten sam wzorzec trzeba by zastosować w Operations.
  - Confidence: LOW — wymaga testu z czytnikiem.
  - Blind spot: Wpływ na `draggable` (wrapper `div` między wierszem a tabelą).
- **Decision**: PENDING — Fix A wybrany, ale NIE zastosowany: nagłówek zawiera fokusowalne przyciski ColumnHeaderHint, więc aria-hidden na wierszu łamałby WCAG, a inert zabiłby tooltipy. Wymaga innego podejścia (np. aria-hidden tylko na tekście etykiety w ColumnHeaderHint albo role tabeli).

### F2 — Ręczne kroki oznaczone jako wykonane bez śladu w repo

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/budget-view-ui/plan.md (Progress 3.7, 4.6, 5.9; otwarte 1.5, 4.10)
- **Detail**: 3.7 (`p3-*.png`) i 5.9 (`p5-*.png`) są `[x]`, ale katalog `context/changes/budget-view-ui/screenshots/` nie istnieje i w historii gita nie ma żadnego PNG tej zmiany. 1.5 i 4.10 są uczciwie `[ ]`. Zrzuty „przed” nigdy nie powstały, więc 4.6 („odpowiadają zrzutowi przed”) nie miało punktu odniesienia. Pozostałe kroki ręczne (przeglądarka) nie mają zapisu poza potwierdzeniem użytkownika.
- **Fix**: Albo dodać zrzuty (przynajmniej p5 desktop + 390 px) do `screenshots/`, albo odznaczyć 3.7, 4.6 i 5.9 i zamknąć plan z jawnie otwartymi punktami.
- **Decision**: FIXED — odznaczono 3.7, 4.6, 5.9 w Progress (brak zrzutów w repo).

### F3 — Lokalny stan UI bez resetu po błędzie lub anulowaniu wizyty

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/Budget.vue:93-106, :150-162; resources/js/components/BudgetMonthPicker.vue:17-51
- **Detail**: (a) `showArchived` to lokalny ref zsynchronizowany watcherem; gdy `router.get` zawiedzie, pole zostaje rozjechane z `props.show_archived`. (b) `pendingMonth` jest czyszczone w `onFinish`; przy szybkich kliknięciach `onFinish` anulowanej wizyty może wyzerować wartość ustawioną przez nową wizytę (migotanie zaznaczenia). Nie zweryfikowano kolejności callbacków Inertii.
- **Fix**: W `Budget.vue` dodać `onFinish: () => (showArchived.value = props.show_archived)`; w pickerze czyścić `pendingMonth` tylko gdy `pendingMonth.value === month`.
- **Decision**: FIXED — Budget.vue onFinish resetuje showArchived; BudgetMonthPicker czyści pendingMonth tylko gdy === month.

### F4 — Drobne odstępstwa od brzmienia planu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: BudgetAmountCell.vue:7-10; BudgetCategoryList.vue:92, ~213; BudgetMonthPicker.vue (onStart)
- **Detail**: `BudgetAmountCell` ma propsy `amount` i `labelHidden` spoza kontraktu planu; `canDrag` nie zawiera `!reorderHttp.processing` (zamiast tego `:disabled="!canDrag || reorderHttp.processing"`, zachowanie to samo); `pendingMonth` ustawiane w `onStart`, nie „przy kliknięciu”. Intencja planu zachowana. Odroczone elementy (klawiatura, tooltip menu, podpowiedzi mobile, kicker) nietknięte.
- **Fix**: Dopisać krótki addendum do planu lub zostawić; bez zmian w kodzie.
- **Decision**: FIXED — addendum w plan.md przed sekcją Progress.

### F5 — Wiersz „disabled” w kitchen sinku każe wysłać prawdziwy POST

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/dev/BudgetKitchenSink.vue:211-212
- **Detail**: Opis stanu disabled każe wysłać dialog tworzenia budżetu w sekcji „Brak budżetu”, a nagłówek strony mówi, że akcje na fiksurach nie działają. Taki submit uderza w prawdziwą trasę (strona tylko `local`, ale zakłada budżet na koncie dewelopera).
- **Fix**: Zmienić opis na stan z `processing` pokazany fiksturą albo ostrzec w opisie, że submit tworzy prawdziwy budżet.
- **Decision**: FIXED — ostrzeżenie w opisie stanu disabled w BudgetKitchenSink.vue.
