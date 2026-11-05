---
change_id: operation-dialogs-atrament
title: Operation dialogs atrament
status: archived
created: 2026-11-05
updated: 2026-11-05
archived_at: 2026-11-05T18:36:01Z
---

## Notes

Zmiana UI z `/10x-ui` (wstępny audyt 2026-11-05). Kontynuacja `category-dialog-atrament`, która dała powłokę `ui/dialog`, `FormField`, `SegmentedControl` primary i ADR-0040, a pola dialogów operacji odroczyła na tę zmianę.

- **Widok:** `resources/js/components/ExpenseFormDialog.vue` (dialog wydatku/zwrotu na stronie Operacji).
- **W tym samym kontrakcie:** `IncomeFormDialog.vue`, `OwnTransferFormDialog.vue`, `CapitalMovementFormDialog.vue`.
- **Źródło tokenów / wzorzec:** design system Atrament, https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA — komponenty Dialog (podgląd „Do wydania przed → po”, ostrzeżenie o limicie w `warning`, blokada zamykania w trakcie zapisu), Field (`ht-input--money`, `ht-choice`), Amount. Wartości w repo: `resources/css/app.css` (`:root` / `.dark`, `@theme inline`); kontrakt dialogów: ADR-0040, `.ai/rules/js.md` „Form dialogs are built on FormField…”.
- **Wariant kontraktu:** istniejący system projektowy — rozszerzamy `app.css`, `components/ui/*` i `components/`, bez drugiej palety ani `shadcn init`.
- **Wstępny audyt:** skan literałów 0 trafień w 4 plikach; `FormField` 0 użyć, `Spinner` 0, `SegmentedControl` 0 (23 ręczne `InputError`, 29 `Label`); żaden z 4 dialogów nie jest w `/dev/ui/dialogs` ani w `scripts/check-ui-literals.mjs`. Kandydaci na zarzuty C1–C5: pola bez `FormField`/`money`, checkboxy i `Select` zamiast `SegmentedControl` primary, brak podglądu „przed → po” (`handEntryToSpendChanges` liczy go, ale widać tylko ostrzeżenie), stopka niezgodna z ADR-0040 (secondary, brak spinnera i blokady, cicha zła kwota), brak bramki stanów.
- **Odroczone:** `AccountFormDialog`, `TermDepositFormDialog`.
