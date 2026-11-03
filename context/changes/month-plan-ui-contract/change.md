---
change_id: month-plan-ui-contract
title: Kontrakt UI widoku Plan miesiąca
status: implementing
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

- **Decyzja użytkownika (2026-11-03, po fazie 1):** przełącznik rodzaju (Rachunek / Bieżąca) ma stać w osobnej kolumnie tabeli, a nie pod nazwą kategorii. Wpływa to na `.month-plan-grid` (fazy 2) i układ wiersza `MonthPlanItemRow` (faza 3): nowa kolumna „Rodzaj" (tylko listy wydatków), nagłówek `.column-header`, a przy 390 px wraca pod nazwę. Kitchen sink i zrzuty z fazy 2/3 mają to uwzględnić. Plan.md zaktualizowany (fazy 2 i 3, klucz `month_plan.kind_column`).
- **Decyzja użytkownika (2026-11-03):** odznaki statusu (np. „Zapłacone") stoją w jednej linii z nazwą kategorii, nie pod nią. Wpisane do fazy 3 w plan.md.
