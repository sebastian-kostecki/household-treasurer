<!-- PLAN-REVIEW-REPORT -->
# Plan Review: S-04: Pozycje miesiąca — plan i wykonanie

- **Plan**: context/changes/month-items-plan-vs-actual/plan.md
- **Mode**: Deep
- **Date**: 2026-11-02
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | WARNING |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

9/9 paths ✓, 7/7 symbols ✓, brief↔plan ✓, Progress↔Phases ✓. Codebase verification was done inline, without a sub-agent. Checked: reka-ui `CollapsibleTrigger` sets `type="button"`; the hidden approval fields sit outside the sections; no strict `assertInertia` without `etc()`; the June fixture matches the case table.

## Findings

### F1 — Czterokolumnowa siatka od `sm` nie mieści się do ~1000 px

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 2 §2 — Kolumna odchylenia; Manual 2.9
- **Detail**: Three 9rem columns with gaps take 468 px. At 640 px without the sidebar about 92 px is left for the category, while the kind toggle needs about 140 px. At 768 px with the expanded 16rem inset sidebar the grid gets about 424 px. `AppContent` has `overflow-x-clip` (`AppSidebarLayout.vue:21`), so the overflow is clipped silently. The 360 px manual check does not cover these widths.
- **Fix A ⭐ Recommended**: The fourth column starts at `lg`. Below `lg`, the deviation sits under the actual amount, and 2.9 also checks 768 and 1024 px with the sidebar expanded.
  - Strength: Changes one breakpoint. About 212 px is left for the category at lg.
  - Tradeoff: Three grid variants instead of two.
  - Confidence: HIGH — computed from the CardContent, SidebarInset and SIDEBAR_WIDTH classes.
  - Blind spot: The toggle width is estimated, not measured.
- **Fix B**: No fourth column. The deviation is always a second line under the actual amount.
  - Strength: One layout at every width.
  - Tradeoff: No vertical column to scan on desktop.
  - Confidence: MED — changes the plan's layout decision.
  - Blind spot: Readability of `text-xs` next to the Plan column.
- **Decision**: FIXED (Fix A)

### F2 — Komponent i typ mają tę samą nazwę `MonthPlanItemRow`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §1 — Komponent wiersza
- **Detail**: `MonthPlan.vue` would import both the component `MonthPlanItemRow.vue` and the type `MonthPlanItemRow`. TypeScript reports "Duplicate identifier" or forces an alias. The plan also left the type's file to the implementer.
- **Fix**: The type is named `MonthPlanItem` and lives in `resources/js/types/month-plan.ts` together with `MonthItemKind`.
- **Decision**: FIXED

### F3 — Puste pole kwoty podświetla wiersz jako „ponad plan”

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details — „Odchylenie na żywo”; Phase 2 §6
- **Detail**: With `parsedAmount(row) ?? 0`, clearing a field or typing an invalid amount briefly marks every row with an actual amount > 0 in red.
- **Fix**: When `parsedAmount(row) === null`, `rowReading` returns `{ deviation: null, overPlan: false }`, so the row shows „—” without colour. The To Spend preview keeps `?? 0`.
- **Decision**: FIXED

### F4 — Ręczny przystanek po fazie 1 sprawdza tylko brak zmian

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Phase 1 — Manual 1.4, Implementation Note
- **Detail**: Phase 1 leaves the template unchanged. Its manual check would still need a full worktree preview: a separate database, migrate --seed and a build that takes several minutes.
- **Fix**: Remove 1.4 and the stop after phase 1. The preview is set up once, in phase 2.
- **Decision**: FIXED
