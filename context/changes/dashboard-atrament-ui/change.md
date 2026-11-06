---
change_id: dashboard-atrament-ui
title: Dashboard atrament ui
status: implemented
created: 2026-11-05
updated: 2026-11-06
archived_at: null
---

## Notes

Zmiana UI przez `/10x-ui`, jeden widok: `resources/js/pages/Dashboard.vue` (oraz jego komponenty: `StatTile`, `BudgetPlanSummaryStrip`, `DashboardNotices`, `DashboardCategorySection`, `CategoryMeterRow`, `ExecutionMeter`, `DashboardHoldings`, `IndexEmptyState`).

- **Wariant kontraktu:** istniejący system projektowy. Rozszerzamy go, bez drugiej palety i bez `shadcn init`.
- **Źródło tokenów:** `resources/css/app.css` (`:root` / `.dark`, `@theme inline`, `@utility` typografii); wartości z design systemu C · Atrament i morski: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA.
- **Wzorzec:** komponenty `StatPanel` (zamiast osobnych `StatTile`), `Notice`, `DataTable` z `Tabs`, `Meter`, `Panel`; mockup `MockupCAtrament` → Dashboard.
- **Kitchen sink:** `/dev/ui/dashboard` (`resources/js/pages/dev/DashboardKitchenSink.vue`, `resources/js/pages/dev/dashboard-fixtures.ts`).

Wstępny audyt (2026-11-05): skan literałów 0 trafień (widoki są już w `scripts/check-ui-literals.mjs`); 11 stylów sprzed Atramentu (`text-3xl`, `text-base`, `rounded-2xl`, `border-border/60`, `divide-border/60`, `opacity-60/70`). Zarzuty do potwierdzenia w researchu:

1. Brakujący `StatPanel`: `Dashboard.vue:191–241` (trzy karty `StatTile`, `StatTile.vue:29` `text-3xl` zamiast `kpi-lg`) i drugi panel miar `BudgetPlanSummaryStrip` (`Dashboard.vue:341–348`); brak miary Postęp roku.
2. Brakujący `DataTable` z `Tabs`: `Dashboard.vue:359–377` dwa osobne `ListSurface` zamiast jednego panelu „Realizacja budżetu” z zakładkami Wszystko / Dochody / Wydatki; suma na dole sekcji zamiast wiersza grupy (`DashboardCategorySection.vue:115–170`), ręczny pasek w wierszu sumy zamiast `ExecutionMeter`, `border-border/60` (`:116`, `:186`).
3. `Notice`: `DashboardNotices.vue:331` (`rounded-2xl`, `disabled:opacity-60`), `:393`, `:404` (`text-base font-semibold`), ręczny kafelek ikony ostrzeżenia miesiąca.
4. `Panel` / architektura: `DashboardHoldings.vue:16–29` drugie drzewo na telefon (akordeon `lg:hidden`, `opacity-60` w `:97`), jeden nagłówek „Konta i lokaty” zamiast dwóch paneli ze stopką sumy (`:173`, `:241`); szerokość prawej kolumny (`aside-width` 300 px vs `lg:grid-cols-3`).
5. Architektura: brak `PageTitle` (`Dashboard.vue:131`), nagłówek sekcji `text-sm font-semibold` (`:254`) zamiast `section-title`; kreska postępu roku za `Checkbox` w `localStorage` (`:269–283`) zamiast miary Postęp roku i stałej kreski.

Prawdopodobnie odroczone: „Dodaj operację” / „Importuj wyciąg” w wierszu tytułu (dialogi operacji na Dashboardzie); pasek dni w mierze Do wydania (nowy props z serwera). Zmiany układu mogą wymagać ADR-0043.
