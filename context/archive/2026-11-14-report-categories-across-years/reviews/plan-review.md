<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Raporty „Lata”: kategorie przez lata

- **Plan**: context/changes/report-categories-across-years/plan.md
- **Mode**: Deep
- **Date**: 2026-11-14
- **Verdict**: REVISE (po poprawkach: SOUND)
- **Findings**: 1 critical, 4 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding

29/29 paths ✓, 9/9 symbols ✓ (1 stale line ref: `ReportComparisonPeriod.php:230-255` → :51), brief↔plan ✓, Progress↔Faza ✓

## Findings

### F1 — Faza 1: `types:check` padnie, sam `years: null` nie wystarczy

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §4, kryterium 1.6
- **Detail**: Poszerzenie `ReportPeriod.kind` o 'years' łamie `Reports.vue:217-219` (`:period-kind` do `ReportStatPanel`, prop `'year'|'month'|'last-12'`) i prawdopodobnie setter `periodKind` (:43, v-model na `SegmentedControl<T>`). 28 scenariuszy fiksur bez wspólnego buildera.
- **Fix**: Faza 1 §4 obejmuje `Reports.vue` (zawężenie `:period-kind`, typ settera) i 28 miejsc `years: null`; `years` w trzech renderach kontrolera.
- **Decision**: FIXED

### F2 — Pięć segmentów psuje `reports-view.spec.ts`, którego plan nie zna

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §1/§6, Faza 3 kryterium 3.1
- **Detail**: `tests/e2e/reports-view.spec.ts:34,:55` `toHaveCount(4)`, tytuł :21 „z czterema segmentami”; 3.1 uruchamia tylko `reports.spec.ts`. `stateMatrix` sinka (:33) „cztery segmenty w 22rem”.
- **Fix**: Plik w Fazie 2 §1 (4→5, tytuł), opis `last-12-phone` w §6, polecenie 3.1 obejmuje `reports-view.spec.ts`.
- **Decision**: FIXED

### F3 — E2E ma „stworzyć Budżet poprzedniego roku w realizacji”, a UI tego nie umie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §1
- **Detail**: UI tworzy tylko szkic następnego roku; rok poprzedni w realizacji daje `demo:seed-previous-year` (reports.spec.ts:10-11, test rok do roku).
- **Fix**: Warunek wstępny `demo:seed-previous-year`; test tworzy tylko operację (i grupę) przez UI i usuwa w `finally`.
- **Decision**: FIXED

### F4 — Suma „całego roku” bez ograniczenia daty nie jest równa raportowi roku z konstrukcji

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details, Faza 1 §2
- **Detail**: `MonthCategoryActuals::forMonths` filtruje datę do roku Budżetu; plan sumował cały rok tylko po `budget_id`. `RestoreUserData` (:860-888) nie sprawdza roku daty, brak CHECK w bazie.
- **Fix**: Oba tryby jako `CASE WHEN` z parami dat (01.01–31.12 i 01.01–D); test operacji z datą spoza roku Budżetu.
- **Decision**: FIXED

### F5 — Reguła `reports.md` każe ładować „wykres” asynchronicznie, a sparkline tego nie robi

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 2 §8
- **Detail**: Pierwsza sekcja reguły wymaga `defineAsyncComponent` i placeholdera dla wykresu; sparkline S-34 to znacznik CSS/SVG (ADR-0097 „Colour”). Reguła nie mówi też, że nazwa w „Latach” jest zwykłym tekstem.
- **Fix**: Nowa sekcja „Years” zawiera wyjątek dla sparkline i zdanie o nazwie jako tekście do S-36.
- **Decision**: FIXED

### F6 — Logika wierszy z wielu Budżetów: trzecia kopia zamiast reuse

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 1 §3
- **Detail**: `ReportWindowRows::of` (:253-290) i `PeriodReport::categoryEntry` (:267) mają już kształt `ReportRow` i regułę archiwum; zapytanie `BudgetLine::whereIn` nie miało właściciela.
- **Fix**: §3 wskazuje reuse `ReportRow` i `categoryEntry`; `ReportYears` czyta linie jednym zapytaniem.
- **Decision**: FIXED

### F7 — Nierozstrzygnięte zaokrąglenie i remis „Najszybciej rośnie”

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details, Faza 1 §3
- **Detail**: „od zera” vs „połowa od zera”; remis na float lub na zaokrąglonych dziesiątych niezdefiniowany.
- **Fix**: „połowa od zera” dla zmiany i wzrostu; remis na `int` dziesiątych po zaokrągleniu.
- **Decision**: FIXED

### F8 — Drobne nieścisłości w treści planu

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State Analysis, Faza 2 §2/§4
- **Detail**: (a) `ReportComparisonPeriod.php:230-255` → :51; (b) otwarty wybór „reuse albo przemianowanie `.report-trend-*`” (przemianowanie dotyka `ReportTrendSummary.vue` i `reports-view.spec.ts`); (c) `SegmentedControl` ma `tone="neutral"`, nie wariant; (d) podpis „wzrost 2020–2026” w „Pełnych latach” powinien być `first_year–final_year`.
- **Fix**: Wszystkie cztery poprawione w planie; reuse klas bez przemianowania.
- **Decision**: FIXED
