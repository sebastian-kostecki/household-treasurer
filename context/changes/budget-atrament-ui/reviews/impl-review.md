<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Widok Budżetu w systemie „C · Atrament i morski”

- **Plan**: context/changes/budget-atrament-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-05
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING (automatyczne zielone: build, types:check, check, check:ui, npm test, composer ci:check na PG, sloppy ci, pint; ręczne 1.4, 2.6–2.8, 3.6, 4.5–4.7 oczekują) |

## Findings

### F1 — Wyłączony Switch ma zbyt niski kontrast

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ui/switch/Switch.vue:28
- **Detail**: Tor `bg-track` (#e2e7ee) i gałka `bg-card` bez obramowania: kontrast gałki do toru ok. 1,3:1, toru do karty ok. 1,2:1 (WCAG 1.4.11). Stan wyłączony trudno odróżnić od włączonego w jasnym motywie.
- **Fix**: Dodać `border-border` albo `ring-1` na gałce (lub ciemniejszy token toru) w `ui/switch`.
- **Decision**: PENDING

### F2 — Pusty tor paska niewidoczny w wierszu sum

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/css/app.css (`.budget-category-totals-row`, `.execution-meter-track`)
- **Detail**: Tło `muted` (#e9edf2) vs tor `track` (#e2e7ee), kontrast ok. 1,1:1; widać tylko wypełnienie. Do potwierdzenia na zrzutach.
- **Fix**: W wierszu sum dać torowi ciemniejsze tło (np. `bg-secondary`/obramowanie) po obejrzeniu kitchen sinka.
- **Decision**: PENDING

### F3 — Dashboard zmienił wygląd paska planu poza zakresem planu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: resources/js/pages/Dashboard.vue:341, resources/js/components/BudgetPlanSummaryStrip.vue
- **Detail**: `BudgetPlanSummaryStrip` jest współdzielony, więc Dashboard dostał `StatPanel` (kpi-lg mono, label-caps, 3 miary), co plan wykluczał w „What We're NOT Doing” (migracja StatTile na Dashboardzie). Brak zrzutu Dashboardu.
- **Fix A ⭐ Recommended**: Zaakceptować i dopisać do ADR-0037/notatek zmiany; obejrzeć Dashboard przy ręcznej weryfikacji 2.6.
  - Strength: Bez martwego duplikatu komponentu.
  - Tradeoff: Widoczna zmiana poza widokiem Budżetu.
  - Confidence: MED — wygląd niezweryfikowany wizualnie.
  - Blind spot: Czy użytkownik akceptuje nowy wygląd Dashboardu.
- **Fix B**: Rozdzielić komponent (stary wariant dla Dashboardu).
  - Strength: Ścisły zakres.
  - Tradeoff: Duplikat do usunięcia przy migracji Dashboardu.
  - Confidence: MED.
  - Blind spot: Brak.
- **Decision**: PENDING

### F4 — Puste panele kategorii bez akcji dodania

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/BudgetCategoryList.vue (IndexEmptyState)
- **Detail**: Zgodnie z literą planu jedyna akcja to „Dodaj kategorię” w tytule, ale pusty panel stracił CTA.
- **Fix**: Zostawić (jedna akcja jest zamierzona) albo dodać CTA w stanie pustym — decyzja produktowa.
- **Decision**: PENDING

### F5 — Martwe klucze tłumaczeń po usunięciu paska miesiąca

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: lang/pl/ui.php (budget: month_scope, month_income, month_expense, month_result, month_today_badge, add_income, add_expense)
- **Detail**: Nic ich już nie czyta.
- **Fix**: Usunąć klucze osierocone przez tę zmianę.
- **Decision**: PENDING

### F6 — `title` na niefokusowalnym div w wierszach postępu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/BudgetCategoryRow.vue:146, BudgetCategoryList.vue:315
- **Detail**: Podpowiedź niedostępna z klawiatury/czytników; nagłówek „Postęp” nie ma `HintTooltip` jak inne kolumny.
- **Fix**: Przenieść hint do nagłówka kolumny (`HintTooltip`), usunąć `title` z wierszy.
- **Decision**: PENDING

### F7 — Brak zrzutów ekranu (bramka wizualna)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/budget-atrament-ui/screenshots/ (nie istnieje)
- **Detail**: Zrzuty z pkt 7 fazy 2 i pkt 6 fazy 4 pominięto na życzenie (ręczna weryfikacja na końcu). Zmiany globalne dotykają wielu widoków; kroki 2.6–2.8, 4.5–4.7 pozostają otwarte. Plan miał też drobne błędy faktograficzne (U+2212 w formatMoney, wpisy w check-ui-literals) — adaptacje uzasadnione.
- **Fix**: Wykonać ręczną weryfikację w obu motywach przy 1280 i 390 px (Dashboard, Operacje, Plan miesiąca, import, dialog, /budget, kitchen sink).
- **Decision**: PENDING
