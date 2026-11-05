---
change_id: budget-atrament-ui
title: Widok Budżetu w systemie Atrament i morski
status: implementing
created: 2026-11-05
updated: 2026-11-05
archived_at: null
---

## Notes

Zmiana UI prowadzona przez `/10x-ui`: jeden widok, kontrakt systemu projektowego.

- **Widok:** `/budget`: `resources/js/pages/Budget.vue` oraz komponenty, które renderuje (`resources/js/components/Budget*.vue`, `CreateBudgetDialog.vue`).
- **Źródło tokenów:** `resources/css/app.css`: wartości w `:root` / `.dark`, publikowane przez `@theme inline` (wdrożone w ADR-0035).
- **Wzorzec:** design system „C · Atrament i morski” (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA): README, komponenty DataTable, PageTitle, Panel, Badge, Switch, StatPanel, Meter, Amount; mockup MockupCAtrament → Budżet. Artefakt to podgląd, nie źródło wartości.
- **Komponenty:** `resources/js/components/ui/` (shadcn-vue na reka-ui; brakujący Switch dodajemy przez shadcn-vue CLI) oraz współdzielone `ListSurface`, `StatTile`, `IndexEmptyState`, `CategoryChip`, `IconAction`.
- **Wariant kontraktu:** istniejący system projektowy: rozszerzamy go, bez drugiej palety i bez `shadcn init`.
- **Bramka wizualna:** kitchen sink `/dev/kitchen-sink/budget` (repo nie ma Playwrighta).
- **Decyzje z `/10x-ui` (2026-11-05):** punkty 3 i 5 z README systemu (przepis panelu, kontrolki 32 px, `column-caption` → `table-header`) robimy globalnie w tej zmianie, z nowym ADR (ADR-0036 jest zarezerwowany przez `period-report-by-category`, więc bierzemy następny wolny); `BudgetMonthStrip` usuwamy z Budżetu, jak w mockupie.
