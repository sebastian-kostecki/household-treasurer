<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Podpowiedź kategorii w podglądzie Importu

- **Plan**: context/changes/import-category-suggestions/plan.md
- **Mode**: Deep
- **Date**: 2026-11-04
- **Verdict**: REVISE → SOUND (po poprawkach)
- **Findings**: 1 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
17/17 paths ✓, 8/8 symbols ✓, brief↔plan ✓, Progress↔Fazy 19/19 ✓

## Findings

### F1 — Indeks odwrócony nie mieści się w 2 s, a fallback ma warunek nie do spełnienia

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 2 §1, Performance Considerations, Phase 4 §1
- **Detail**: Tokeny szumu (`PLN`, `POL`) zwracają z indeksu całą historię typu. W benchmarku syntetycznym (PHP 8.4, 10 000 wpisów, 200 sklepów, 25 kategorii, 300 wierszy × 2 typy) daje to 28 s bez przycinania i 0,1 s z kandydatami tylko z tokenów o w(t) ≥ 0,05. Plan dopuszczał przycinanie tylko przy „tych samych wynikach” w eval, a tego nie da się zagwarantować. Do tego wzór bez przycinania daje podpowiedź tekstom z samego szumu (sim 0,8 przy k=5), co przeczy regule „bez wspólnego słowa o dużej wadze brak podpowiedzi”.
- **Fix**: Reguła kandydatów `w(t) ≥ WEIGHT_FLOOR` w kontrakcie modelu, test Unit dla szumu przy 5–8 kategoriach, decyzja zamiast fallbacku w Performance Considerations, a eval mierzy tę wersję względem progów.
  - Strength: 280× szybciej w benchmarku; kod zgodny z regułą z change.md.
  - Tradeoff: Wyniki mogą odbiec od prototypu dla wierszy z samym szumem.
  - Confidence: HIGH — zmierzone.
  - Blind spot: Rozkład tokenów w prawdziwych danych (sprawdza 4.4).
- **Decision**: FIXED (Fix in plan)

### F2 — „Brak opisu”: martwa gałąź w wierszu, szum w historii

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §2, §5; Phase 3 §7
- **Detail**: `ImportRowFields::omitLiveFields` (`:105-107`) wpisuje kontrahenta do pustego opisu, więc „Brak opisu” oznacza brak kontrahenta, a gałąź „tylko counterparty” zawsze daje pusty tekst. Historia uczyła się z zapisanych operacji „Brak opisu”. `operation_date` nigdy nie jest null (`CsvImportController.php:85/:477`).
- **Fix**: Wiersz „Brak opisu” daje oba null, historia pomija takie wpisy (także w eval), a przypadek bez daty zostaje tylko jako obronny w TS.
- **Decision**: FIXED (Fix in plan)

### F3 — Wymagany klucz `ImportRow` psuje literały w dwóch testach TS

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §4, Migration Notes
- **Detail**: `import-submit-block.test.ts:9-20` i `import-to-spend-warnings.test.ts:61-72` budują pełne literały `ImportRow`. Migration Notes zakładały brak klucza w starej sesji przy typie wymaganym.
- **Fix**: `category_suggestion?:` opcjonalne, helper czyta przez `?.`, Migration Notes zamknięte decyzją.
- **Decision**: FIXED (Fix in plan)

### F4 — Trzecie zapytanie o kategorie w execution obok istniejących

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 2 §2 (Mapowanie)
- **Detail**: `CsvImportController::incomeCategories()`/`expenseCategories()` (`:523-569`) już ładują te same dane; serwis dodawał równoległe zapytanie z tymi samymi filtrami.
- **Fix**: Nowa sekcja Phase 2 §2a: `ImportCategoryOptions` wspólne dla `showPreview` i mapowania.
- **Decision**: FIXED (Fix in plan)
