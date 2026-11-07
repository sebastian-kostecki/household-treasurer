<!-- PLAN-REVIEW-REPORT -->
# Plan Review: ui-fixes-2 — plan implementacji

- **Plan**: context/changes/ui-fixes-2/plan.md
- **Mode**: Deep
- **Date**: 2026-11-07
- **Verdict**: REVISE → SOUND (po poprawkach)
- **Findings**: 2 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | FAIL |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding

Grounding: 33/34 paths (`Dockerfile.prod` → `docker/8.5/Dockerfile.prod`), 5/6 symbols (`MonthPlanRowState` nie niesie kwoty na żywo), brief↔plan ✓

## Findings

### F1 — Zakresy minmax zabierają miejsce kategorii przy 1280 px

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Faza 5 §1–2, Faza 1 §4 (ADR-0061)
- **Detail**: Siatka CSS rozciąga `minmax(a, b)` do `b`, zanim reszta trafi do `1fr`. Budżet od `xl` przy 1280 px: kategoria spadłaby z ok. 236 px do ok. 12 px. Dashboard przy 38rem: kategoria z ok. 176 do ok. 140 px, a miernik zostawał na 9rem. Kryteria 5.4 i 5.5 by nie przeszły.
- **Fix A ⭐ Recommended**: Wagi `fr` zamiast stałych maksimów, wymóg „kategoria przy 1280 px ≥ dziś”.
  - Strength: Kolumny rosną proporcjonalnie, bez nowego progu.
  - Tradeoff: Kolumny kwot nie mają górnej granicy.
  - Confidence: HIGH — standardowy rozkład `fr` po minimach.
  - Blind spot: Wagi do potwierdzenia na zrzutach.
- **Fix B**: Szersze maksima w `@container` od szerokiej karty.
  - Strength: Przy 1280 px układ identyczny jak dziś.
  - Tradeoff: Nowy próg w Budżecie.
  - Confidence: MED.
  - Blind spot: Próg nieobliczony.
- **Decision**: FIXED (Fix A)

### F2 — Strażnik formatMoney sprzeczny z planem w dwóch plikach

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §7, kryterium 3.2
- **Detail**: `ImportPreviewRow.vue:708` używa `formatMoney` w Alercie (alerty zachowują „zł”). `MonthPlanItemRow.vue:176` trzyma „Zatwierdzono:” do Fazy 4. Strażnik i grep 3.2 by padły.
- **Fix**: `ImportPreviewRow` poza strażnikiem i grepem; `MonthPlanItemRow` dołącza w Fazie 4.
- **Decision**: FIXED

### F3 — Cztery kwoty w wierszu przy 390 px bez planu awaryjnego

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 4 §4
- **Detail**: Ok. 72 px na kolumnę przy 390 px, „12 345,67” to ok. 68 px. Strzałka obok kwoty się nie mieści.
- **Fix**: Poniżej `xl` strzałka w linii podpisu mobilnego; wariant awaryjny 2×2, zapisany też w ADR-0060.
- **Decision**: FIXED

### F4 — Nieokreślone źródło kwoty planu na żywo dla strzałki

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 4 §4, Critical Implementation Details
- **Detail**: `MonthPlanRowState` nie ma kwoty planu. `parsedAmount()` zwraca `null` przy błędnym wpisie i 0 dla wiersza bez pola. Fabryka w `MonthPlanSink.vue:200` nie była wymieniona.
- **Fix**: Pole `planMinorUnits: number | null` w `MonthPlanRowState`, `null` = brak strzałki, fabryka sinka w tej samej fazie.
- **Decision**: FIXED

### F5 — Błędna ścieżka Dockerfile.prod

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State, Faza 2 §2, kryterium 2.2
- **Detail**: Plik to `docker/8.5/Dockerfile.prod`; grep z kryterium 2.2 kończył się błędem.
- **Fix**: Ścieżka poprawiona we wszystkich miejscach.
- **Decision**: FIXED
