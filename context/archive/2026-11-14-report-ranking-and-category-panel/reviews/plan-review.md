<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Ranking kategorii i panel kategorii

- **Plan**: context/changes/report-ranking-and-category-panel/plan.md
- **Mode**: Deep
- **Date**: 2026-11-14
- **Verdict**: REVISE → SOUND (po poprawkach)
- **Findings**: 1 critical, 6 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | WARNING |
| Architectural Fitness | FAIL |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

35/35 paths ✓, 6/6 symbols ✓ (router.replace z `props`-funkcją w @inertiajs/core 3.11, CategoryRhythms::forCategoryIds/rhythmIn na id kategorii z listy w jednym zapytaniu, defaultForYear, shareTenthsPercent, Sheet `side="bottom"`, selectYear), brief↔plan ✓

## Findings

### F1 — Siatka po oknie (lg), tryb panelu po szerokości treści

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Faza 3 §3 i §4 vs Critical Implementation Details
- **Detail**: `.dashboard-grid` przełącza się przez `@variant lg` (okno, app.css:2311) z kolumną `--aside-width` (clamp z 22vw), a tryb JS liczy się z szerokości elementu. W ramce telefonu 22rem w sinku CSS daje dwie kolumny, a JS wybiera Sheet: pusta kolumna ≥18.75rem zgniata Ranking. Raporty już przełączają układ po szerokości karty (`.report-table`, `container-type`, app.css:1680).
- **Fix**: Siatka na `@container` z progiem jako tokenem w `:root`, ten sam próg w JS; aside pokazuje i chowa CSS; szerokość panelu bez vw.
- **Decision**: FIXED (rekomendacja)

### F2 — Rola dla aria-selected nierozstrzygnięta

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Plan Completeness
- **Location**: Critical Implementation Details; Faza 3 §3
- **Detail**: Plan wylicza ograniczenia ról, ale nie wybiera. Wiersze raportu to dziś divy bez ról. grid/treegrid i tablist wymagają nawigacji strzałkami, a przyciski grup nie mogą leżeć w listbox ani tablist.
- **Fix A**: role="grid"/treegrid z roving tabindex i aria-selected na row
  - Strength: Dosłownie spełnia ADR-0097:23.
  - Tradeoff: Nawigacja klawiaturą i nowy wzorzec; treegrid w trybie Grupy jest ciężki.
  - Confidence: MED
  - Blind spot: Czytniki przy linkach Operacji w tym samym komponencie.
- **Fix B ⭐ Recommended**: aria-current="true" na przycisku nazwy + aria-controls, nowy ADR-0100
  - Strength: Bez ról tabeli i strzałek; pasuje do obecnych divów.
  - Tradeoff: Dodatkowy ADR.
  - Confidence: HIGH
  - Blind spot: Czy karta PageReports zakłada aria-selected z innego powodu.
- **Decision**: FIXED via Fix B

### F3 — Szerokość 0 przed pierwszym pomiarem otwiera arkusz

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §3
- **Detail**: useElementSize zaczyna od 0; wejście na ?category na desktopie najpierw otwiera modalny Sheet, potem przełącza na aside.
- **Fix**: Tryb `unknown | aside | sheet`; Sheet dopiero po pomiarze; E2E (e) sprawdza brak dialogu w szerokim oknie.
- **Decision**: FIXED

### F4 — Wybrana kategoria ukryta w zwiniętej grupie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §2, Faza 3 §3, E2E (d)
- **Detail**: Przy ?ranking=groups wybrany wiersz leży w zwiniętej grupie i nie widać zaznaczenia.
- **Fix**: Grupa wybranej kategorii startuje rozwinięta; scenariusz `panel-groups`; asercja w E2E (d).
- **Decision**: FIXED

### F5 — Arkusz od dołu bez przewijania i z angielskim „Close”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §3 i §4
- **Detail**: SheetContent bottom bez max-height/overflow (SheetContent.vue:47-48), sr-only „Close” (:58); components/ui/* nie edytujemy dla jednego ekranu.
- **Fix**: Klasa panelu-arkusza (max-height w svh z tokenu, overflow-y: auto) przez `class`; własny SheetClose z polską etykietą.
- **Decision**: FIXED

### F6 — Reguła reports.md: paths: i index nie obejmą nowych plików

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §9, Faza 3 §7
- **Detail**: Globy reguły nie obejmują lib/report-ranking* ani lib/report-category-panel*; report-chart* zostałby martwy.
- **Fix**: Podmiana globów w `paths:`, `php artisan boost:index-rules`, nowe kryterium 2.7.
- **Decision**: FIXED

### F7 — Najwyższy miesiąc liczony na serwerze i w kliencie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Faza 1 §3, Faza 3 §1
- **Detail**: Dwie implementacje reguły „najwyższy, remis → wcześniejszy”.
- **Fix**: reportMonthBars przyjmuje highestMonth z propsów; test remisu zostaje w Pest.
- **Decision**: FIXED

### F8 — Nieaktualne numery linii w Current State Analysis

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State Analysis
- **Detail**: BudgetViewedMonth::defaultForYear jest w :22-33, nie :75-86; router.replace bez preserveFlash zeruje flash (core 3.11).
- **Fix**: Poprawione odwołanie; preserveFlash: true w obu kontraktach router.replace.
- **Decision**: FIXED
