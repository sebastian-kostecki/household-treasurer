---
change_id: accounts-atrament-ui
title: Accounts atrament ui
status: impl_reviewed
created: 2026-11-06
updated: 2026-11-06
archived_at: null
---

## Notes

Zmiana UI przez `/10x-ui`, jeden widok: `resources/js/pages/Accounts.vue` (oraz jego komponenty: `StatTile`, `ListSurface` z `.holdings-list-row`, `AccountIdentityBlock`, `IconAction`, `ConfirmActionDialog`, `AccountFormDialog`, `IndexEmptyState`).

- **Wariant kontraktu:** istniejący system projektowy. Rozszerzamy go, bez drugiej palety i bez `shadcn init`.
- **Źródło tokenów:** `resources/css/app.css` (`:root` / `.dark`, `@theme inline`, `@utility` `page-title`, `table-header`, `kpi-lg`); wartości z design systemu C · Atrament i morski: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA.
- **Wzorzec:** karta `PageAccounts` (sekcja „Różnice od dzisiejszej aplikacji”) oraz komponenty `StatPanel`, `DataTable` z wierszem zarchiwizowanym (`ht-row--archived`), `CategoryChip` → znak banku (`ht-mark`), `Switch`, `Dialog`, `Field`; mockup `MockupCAtrament` → Konta.
- **Kitchen sink:** `/dev/ui/accounts` (`resources/js/pages/dev/AccountsKitchenSink.vue`, `resources/js/pages/dev/accounts-fixtures.ts`).

Wstępny audyt (2026-11-06): skan literałów 0 trafień (`Accounts.vue` jest już w `scripts/check-ui-literals.mjs:32`); 5 stylów sprzed Atramentu (`text-lg font-semibold`, `opacity-60`, `border-border/60`, `text-3xl` w `StatTile`, Badge `secondary`). Zarzuty do potwierdzenia w researchu:

1. Brakujący `StatPanel`: `Accounts.vue:99–102` jedna karta `StatTile` (`StatTile.vue:29` `text-3xl` zamiast `kpi-lg`); wzorzec ma dwie miary: Płynność dysponowalna i Łączne środki („z lokatami …”). Druga miara potrzebuje propsa `total_funds_minor_units` (Dashboard liczy go w `DashboardController.php:128`): PHP + typ + `assertInertia` w jednym commicie.
2. Brakujący `DataTable`: `Accounts.vue:151–252` `ListSurface` z `.holdings-list-row` (`app.css:812`, `flex-wrap`, `border-border/60`) bez nagłówków kolumn, typ jako podpis, akcje spadają do osobnej linii na wąskim ekranie (`:186` `w-full`). Potrzebna siatka (Nazwa | Typ | Saldo bieżące | Akcje) z `table-header`. Do rozstrzygnięcia: wzorzec ma „zł” w komórkach bez stopki, `js.md` / ADR-0037 każe `formatMoneyAmount` i stopkę „Kwoty w zł”.
3. Wiersz zarchiwizowany: `Accounts.vue:156` `opacity-60` (kontrast tekstu poniżej 4.5:1, ikona „Przywróć” wygląda na wyłączoną) zamiast `muted-foreground` bez przezroczystości; Badge `secondary` (`:167`) zamiast `neutral`.
4. `Checkbox` zamiast `Switch`: `Accounts.vue:104–119` „Pokaż zarchiwizowane” (reguła `js.md` już tego wymaga, Budżet ma `Switch`); we wzorcu przełącznik stoi w akcjach wiersza tytułu.
5. Tytuł, znak banku, dialog: `:93` `text-lg font-semibold` zamiast `page-title`, opis „Rachunki bankowe i konta gotówkowe.” ukryty w podpisie listy (`:151`); `AccountIdentityBlock.vue:38` kafelek 40 px z `border-border/60` zamiast `ht-mark` 28 px (skrót banku Mono albo „zł” dla gotówki; jest `AccountMarkCompact`); `AccountFormDialog.vue:231` „Anuluj” `secondary` zamiast `outline` (ADR-0040).

Współdzielone elementy: `AccountIdentityBlock` używają też Lokaty i `DashboardHoldings`, `.holdings-list-row` także Lokaty, `StatTile` po zmianie zostaje tylko na Lokatach. Research rozstrzyga: wspólny komponent czy Lokaty odroczone. Wejście bez logowania i stan pusty bez zarzutu (`auth`, dwa `IndexEmptyState`).
