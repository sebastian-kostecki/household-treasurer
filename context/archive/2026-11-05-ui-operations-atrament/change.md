---
change_id: ui-operations-atrament
title: Widok Operacji w systemie Atrament i morski
status: archived
created: 2026-11-05
updated: 2026-11-05
archived_at: 2026-11-05T15:04:41Z
---

## Notes

Zmiana UI prowadzona przez `/10x-ui`: jeden widok, kontrakt systemu projektowego.

- **Widok:** `/operations`: `resources/js/pages/Operations.vue` oraz komponenty, które renderuje (`FinancialOperationRow`, `OwnTransferRow`, `CapitalMovementRow`, `Operation*Cell`, `OperationTypeTile`, `OperationsPageActions`, `OperationsFilters`, `OperationsActiveFilterChips`, `*Actions`).
- **Źródło tokenów:** `resources/css/app.css`: wartości w `:root` / `.dark`, publikowane przez `@theme inline` (ADR-0035); style typograficzne i przepis panelu/kontrolek z ADR-0037.
- **Wzorzec:** design system „C · Atrament i morski” (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA): komponenty DataTable (wiersz dnia), FilterBar, Tabs, CategoryChip, Amount; mockup MockupCAtrament → Operacje. Artefakt to podgląd, nie źródło wartości.
- **Komponenty:** `resources/js/components/ui/` (shadcn-vue na reka-ui) oraz współdzielone `ListSurface`, `SegmentedControl`, `IndexEmptyState`, `CategoryChip`, `OperationTypeTile`, `IconAction`.
- **Wariant kontraktu:** istniejący system projektowy: rozszerzamy go, bez drugiej palety i bez `shadcn init`.
- **Bramka wizualna:** kitchen sink `/dev/operations` (`pages/dev/OperationsSink.vue`; repo nie ma Playwrighta).
- **Zakres z README systemu:** pkt 7 (wydatek w Operacjach bez czerwieni, `financialOperationAmountClass` → `text-foreground`) i pkt 8 (wiersze dnia z datą słowami i sumą dnia w kolumnie kwot). Pkt 7 najpierw ADR (następny wolny numer po 0037) i `.docs/design-system.md`.
- **Decyzje z `/10x-ui` (2026-11-05):**
  1. Zakładki Operacje finansowe / Transfery własne / Ruchy kapitału (`SegmentedControl` neutral) zamiast trzech paneli; wybrana sekcja w URL (`?section=`), zakładka wyciszona filtrem ma licznik 0.
  2. Wiersz tytułu: jedna akcja główna „Dodaj operację” z menu (Dochód, Wydatek, Transfer własny, Ruch kapitału, z powodem wyłączenia); „Importuj” zarysowany. Dialogi bez zmian.
  3. Kolumny operacji finansowych jak w mockupie: Opis (kafelek typu, konto w drugiej linii) | Kategoria | Data | Kwota | akcje.
  4. Suma dnia liczona na serwerze dla całego dnia (te same filtry), jednym zapytaniem dla dni z bieżącej strony.
