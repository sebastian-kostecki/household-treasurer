---
change_id: budget-view-ui
title: Budget view ui
status: preparing
created: 2026-11-03
updated: 2026-11-03
archived_at: null
---

## Notes

Zmiana UI prowadzona przez `/10x-ui` — jeden widok, kontrakt systemu projektowego.

- **Widok:** `/budget` — `resources/js/pages/Budget.vue` oraz komponenty, które renderuje (`resources/js/components/Budget*.vue`, `CreateBudgetDialog.vue`).
- **Źródło tokenów:** `resources/css/app.css` — wartości w `:root` / `.dark`, publikowane przez `@theme inline`.
- **Komponenty:** `resources/js/components/ui/` (shadcn-vue na reka-ui) oraz współdzielone `ListSurface`, `IndexEmptyState`, `CategoryChip`, `IconAction`, `Heading`.
- **Wariant kontraktu:** istniejący system projektowy — rozszerzamy go (`.docs/design-system.md`, ADR-0001, ADR-0003, `.ai/rules/js.md`, `.ai/rules/css.md`); bez drugiej palety i bez `shadcn init`.
- **Bramka wizualna:** kitchen sink (repo nie ma Playwrighta ani testów przeglądarkowych).
