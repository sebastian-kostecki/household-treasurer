---
change_id: month-plan-ui-contract
title: Kontrakt UI widoku Plan miesiąca
status: preparing
created: 2026-11-03
updated: 2026-11-03
archived_at: null
---

## Notes

Zmiana wizualna przez `/10x-ui` — jeden widok, kontrakt istniejącego systemu projektowego.

- **Widok:** `resources/js/pages/MonthPlan.vue` (trasa `/month-plan`) wraz z komponentami tylko tego widoku: `MonthPlanItemRow`, `MonthPaceMeter`, `MonthSummaryCard`, `MonthYearSummary`, `BudgetMonthPicker`.
- **Źródło tokenów:** `resources/css/app.css` — wartości w `:root` / `.dark`, publikowane przez `@theme inline`.
- **Komponenty:** `resources/js/components/ui` (shadcn-vue, `components.json`) oraz bloki domenowe z `.ai/rules/js.md`.
- **Wariant kontraktu:** istniejący system projektowy — rozszerzamy, bez drugiej palety i bez `shadcn init`.
- **Audyt wstępny (2026-11-03):** skan wartości zakodowanych na sztywno na 10 plikach widoku: 1 trafienie (`text-[11px]`, `MonthPlan.vue:697`) + 7 arbitralnych szablonów siatki w 2 plikach; 0 hex / 0 klas palety. Pięć zarzutów do weryfikacji w `research.md` pod `## Charges`.
