---
change_id: month-plan-atrament-ui
title: Month plan atrament ui
status: implementing
created: 2026-11-05
updated: 2026-11-05
archived_at: null
---

## Notes

Zmiana UI przez `/10x-ui`, jeden widok: `resources/js/pages/MonthPlan.vue` (oraz komponenty z listy w `tests/Unit/MonthPlanHardcodedUiTest.php`).

- **Wariant kontraktu:** istniejący system projektowy. Rozszerzamy go, bez drugiej palety i bez `shadcn init`.
- **Źródło tokenów:** `resources/css/app.css` (`:root` / `.dark`, `@theme inline`, `@utility` typografii); wartości z design systemu C · Atrament i morski: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA.
- **Wzorzec:** komponenty `MonthStrip`, `StatPanel`, `DataTable`, `Field` → komórka planu (`ht-cell-input`), `EditBar`, `Notice` → notatka o tempie (`ht-note`), `Badge`; mockup `MockupCAtrament` → Plan miesiąca.
- **Kitchen sink:** `/dev/month-plan` (`resources/js/lib/month-plan-fixtures.ts`).

Wstępny audyt (2026-11-05): skan literałów 0 trafień; ~50 stylów sprzed Atramentu (`text-3xl/lg/sm/xs`, `uppercase tracking-wider`, `rounded-2xl/xl`, `border-border/60`, `opacity-60`). Zarzuty do potwierdzenia w researchu:

1. Brakujące tokeny: `MonthPlan.vue:516` (`page-title`), `:563` (`kpi-lg`), `MonthPlanEditBar.vue:31,38`, `MonthPlanItemRow.vue:173,195,208` (`amount`), `BudgetMonthPicker.vue:54`.
2. Brakujący `StatPanel`: `MonthPlan.vue:554–600` (Do wydania, Dochody, Wydatki, Tempo).
3. Warianty Badge: `MonthPlan.vue:519–527` (stan planu), `MonthPlanItemRow.vue:96` (ręczna pigułka zamiast `ok`).
4. Architektura: tempo w karcie Do wydania (`MonthPlan.vue:578–597`) zamiast miary + notatki; kropki stanu miesięcy w `BudgetMonthPicker.vue:68–72` (może wymagać propsa z serwera); `MonthPlanEditBar` jako pas `primary`.
5. Reguła: `.ai/rules/js.md` wskazuje `.section-label` (`app.css:402`, stary przepis 12 px wersalikami; research: usunąć).

Do rozstrzygnięcia: `MonthSummaryCard.vue:32–62` (własna tabela) — szósty zarzut czy odroczenie.
