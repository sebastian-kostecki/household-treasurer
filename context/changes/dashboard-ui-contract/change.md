---
change_id: dashboard-ui-contract
title: Dashboard UI contract pass (tokens, shared components, states)
status: impl_reviewed
created: 2026-11-03
updated: 2026-11-03
archived_at: null
---

## Notes

Zmiana wizualna prowadzona przez `/10x-ui dashboard`.

- **Widok (jeden):** `resources/js/pages/Dashboard.vue` wraz z komponentami renderowanymi tylko przez niego: `DashboardHoldings.vue`, `DashboardNotices.vue`, `DashboardCategorySection.vue`, `BudgetPlanSummaryStrip.vue`, `CategoryMeterRow.vue` (oraz `CreateBudgetDialog.vue` jako dialog wywoływany z widoku).
- **Źródło tokenów:** `resources/css/app.css` — wartości w `:root` / `.dark`, publikowane przez `@theme inline`; notatki w `.docs/design-system.md`.
- **Wariant kontraktu:** istniejący system projektowy (Tailwind v4 + shadcn-vue, `resources/js/components/ui/*` + komponenty domenowe w `resources/js/components/`). Rozszerzamy, bez drugiej palety i bez `shadcn init`.
- **Reguły agenta:** `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/skills/openship-ui-development/SKILL.md`.
