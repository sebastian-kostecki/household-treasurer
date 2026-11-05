---
change_id: category-dialog-atrament
title: Category dialog atrament
status: archived
created: 2026-11-05
updated: 2026-11-05
archived_at: 2026-11-05T16:47:59Z
---

## Notes

Zmiana UI z `/10x-ui` (wstępny audyt 2026-11-05).

- **Widok:** `resources/js/components/CategoryFormDialog.vue` (dialog dodawania/edycji kategorii na stronie Budżetu).
- **W tym samym kontrakcie:** `ConfirmActionDialog.vue`, `CreateBudgetDialog.vue`; prymitywy `resources/js/components/ui/dialog/*` (zmiana globalna, dotyka każdego modala).
- **Źródło tokenów / wzorzec:** design system Atrament, https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA — komponenty Dialog, Field, Button (`ht-dialog`, `ht-field`, `ht-choice` → `SegmentedControl` tone `primary`, `ht-input--money`, `ht-preview`, stopka). Wartości w repo: `resources/css/app.css` (`:root` / `.dark`, `@theme inline`).
- **Wariant kontraktu:** istniejący system projektowy — rozszerzamy `app.css` i `components/ui/*`, bez drugiej palety ani `shadcn init`.
- **Odroczone:** pola pozostałych dialogów formularzy (Expense, Income, Account, TermDeposit, OwnTransfer, CapitalMovement) — dostają nową powłokę z `ui/dialog`, pola na osobną zmianę.
