<!-- PLAN-REVIEW-REPORT -->
# Plan Review: S-10: Plany miesięcy a budżet roczny

- **Plan**: context/changes/month-plans-vs-year/plan.md
- **Mode**: Deep
- **Date**: 2026-11-02
- **Verdict**: REVISE → SOUND (po triage)
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | PASS |
| Plan Completeness | WARNING |

## Grounding
12/12 paths ✓ (MonthPlanYearComparison.php nowy, zgodnie z planem), 6/6 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — Miniony miesiąc bez planu nie pokaże żadnej linii

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Desired End State; Phase 1 §3 (test minionego miesiąca); Phase 2 manual 2.8
- **Detail**: Dla stanu `none` szablon pokazuje tylko IndexEmptyState bez wierszy (`resources/js/pages/MonthPlan.vue:351-357`). Minionego miesiąca nie da się zatwierdzić, plany miesięcy istnieją od S-01, a `DemoHouseholdSeeder` nie tworzy `MonthPlan`, więc obietnica „miniony miesiąc pokazuje linię z samego wykonania” dotyczy tylko minionych miesięcy z zatwierdzonym planem; check 2.8 jest w podglądzie nie do wykonania, a test Feature minionego miesiąca na kanonicznym fixture potwierdzi propsy niewidocznych wierszy.
- **Fix A ⭐ Recommended**: Zawęzić obietnicę do minionych miesięcy z zatwierdzonym planem; test Feature tworzy zatwierdzony plan przez `MonthPlanFactory`; 2.8 mówi, jak to sprawdzić.
  - Strength: Zgodne z „Brak nowych wierszy”; bez zmian szablonu.
  - Tradeoff: Linia minionego miesiąca przez pewien czas rzadko widoczna.
  - Confidence: HIGH — gałąź `none` jest jednoznaczna w szablonie.
  - Blind spot: None significant.
- **Fix B**: Renderować wiersze tylko do odczytu z linią roczną także w stanie `none`.
  - Strength: Linia widoczna dla każdego minionego miesiąca.
  - Tradeoff: Zmienia ekran `none` z S-01; rozszerza zakres fazy 2.
  - Confidence: MED — trzeba pogodzić z `none_description`.
  - Blind spot: Układ strony bez kart „do wydania”.
- **Decision**: FIXED (Fix A)

### F2 — Komenda weryfikacji nie uruchamia NfrPerf01Test

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §4 i Automated 2.3; Performance Considerations
- **Detail**: `phpunit.xml:20-24` wyklucza grupę `nfr`, więc komenda z 2.3 nie uruchomi `NfrPerf01Test`; CI też go nie odpala. Test nie ma „pętli stron”, tylko kolejne `assertRequestWithinSeconds` (`:52-85`); przy `travelTo('2026-06-01')` domyślny miesiąc to M = f, więc ścieżka M > f nie jest mierzona.
- **Fix**: Rozdzielić komendę 2.3 (NfrPerf01Test przez `--configuration=phpunit.nfr.xml`) i w §4 opisać nowy blok `assertRequestWithinSeconds` dla `route('month-plan', ['month' => 12])`.
- **Decision**: FIXED

### F3 — Fixture „zatwierdzony lipiec przy propozycji czerwca” jest nieosiągalny

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Testing Strategy — „Zatwierdzony plan lipca 150 000”
- **Detail**: `app/Actions/ApproveMonthPlan.php:44` blokuje zatwierdzenie lipca przy propozycji czerwca; test sprawdziłby stan, którego aplikacja nie wytworzy.
- **Fix**: Zatwierdzić też czerwiec (200 000); oczekiwane „przed” = 1 350 000 bez zmian.
- **Decision**: FIXED

### F4 — Fixture testu Feature nie sprawdza granicy dat

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §3 / Integration Tests (MonthPlanPageTest)
- **Detail**: `canonicalBudget()` ma operacje tylko w czerwcu (`tests/Feature/MonthPlanPageTest.php:539-542`), więc „przed” wynosi 0 dla każdego wiersza i asercje nie odróżnią `<` od `<=` ani podwójnego liczenia.
- **Fix**: W nowych testach Feature dodać operację sprzed czerwca (np. Jedzenie 2026-05-10) i wpływ Pensji, i sprawdzać niezerowe „przed”.
- **Decision**: FIXED
