<!-- PLAN-REVIEW-REPORT -->
# Plan Review: S-11: Raport okresu według kategorii

- **Plan**: context/changes/period-report-by-category/plan.md
- **Mode**: Deep
- **Date**: 2026-11-04
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 0 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
22/22 paths ✓, 7/8 symbols ✓ (switcher `destination` ✗), brief↔plan ✓, Progress↔Phase ✓

Potwierdzone: licznik `execution_minor_units` prowadzą wszystkie ścieżki runtime (import, edycja ze zmianą kategorii, zwrot, archiwizacja), `MonthCategoryActuals::forMonths` czyta rok jednym zapytaniem, wzór udziału `intdiv(a*2000+b, b*2)` daje 333/667/1 dla przykładów z testów, `--success` i `ChartColumn` istnieją.

## Findings

### F1 — Przełącznik roku nie ma „logiki month-plan” do skopiowania

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Faza 2 §2
- **Detail**: `BudgetYearSwitcher.vue:65` wysyła tylko `{year}`, więc z każdej strony wraca na `/budget`. `destination: 'month-plan'` wysyła wyłącznie `DashboardNotices.vue:322`.
- **Fix**: przełącznik używa `isCurrentSection(reports().url, page.url)` z `@/lib/nav-active` i wtedy wysyła `destination: 'reports'`; inne strony bez zmian; test przekierowania.
- **Decision**: FIXED (Fix in plan)

### F2 — SegmentedControl i BudgetMonthPicker mają inny kontrakt

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §4 „Wybór okresu”
- **Detail**: `SegmentedControl` to tylko v-model; `BudgetMonthPicker` wymaga `budgetYear` i `showArchived`.
- **Fix**: ref `period` + `router.get`; propsy pickera jak `MonthPlan.vue:539-545`.
- **Decision**: FIXED (Fix in plan)

### F3 — StatTile koloruje każdą kwotę znakiem

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 2 §4 „ReportTotals”
- **Detail**: `StatTile.vue` zawsze nakłada `amountToneClass`, więc „Wydatki” byłyby `text-success` wbrew `design-system.md:31`.
- **Fix**: prop `signed?: boolean` (domyślnie `true`) w `StatTile`; Przychody i Wydatki z `:signed="false"`.
- **Decision**: FIXED (Fix in plan)

### F4 — Parsowanie `?month` różni się od `BudgetViewedMonth::resolve`

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §1
- **Detail**: `resolve()` zamienia złą wartość na domyślny miesiąc, a plan chce raportu roku.
- **Fix**: prywatne parsowanie w kontrolerze (`ctype_digit` + 1..12, inaczej `null`).
- **Decision**: FIXED (Fix in plan)

### F5 — Typ tłumaczeń `shell.ts` poza listą plików

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §3
- **Detail**: `UiTranslations` w `resources/js/types/shell.ts:21-47` jest ręcznym lustrem `ui.php`.
- **Fix**: dopisać `nav.reports` i sekcję `reports` w `shell.ts`.
- **Decision**: FIXED (Fix in plan)

### F6 — Restore kopiuje licznik; ręczne 2.7 może dać fałszywy alarm

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2, Manual 2.7
- **Detail**: `RestoreUserData.php:209` przepisuje `execution_minor_units` ze snapshotu, operacje wstawia bez przeliczenia (`:313`).
- **Fix**: przy rozbieżności najpierw porównać SUM operacji z licznikiem kategorii.
- **Decision**: FIXED (Fix in plan)

### F7 — Wersje zależności Fazy 3 niezweryfikowane

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §2
- **Detail**: `shadcn-vue@2.9.0` i `@unovis/*@1.7.1` nie występują w repo; rejestr `chart` niesprawdzony.
- **Fix**: pierwszy krok Fazy 3: `npm view` wersji i sprawdzenie rejestru; różnicę zapisać w ADR-0036.
- **Decision**: FIXED (Fix in plan)
