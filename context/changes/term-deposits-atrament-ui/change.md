---
change_id: term-deposits-atrament-ui
title: Term deposits atrament ui
status: preparing
created: 2026-11-06
updated: 2026-11-06
archived_at: null
---

## Notes

Zmiana UI przez `/10x-ui`, jeden widok: `resources/js/pages/TermDeposits.vue` (oraz `TermDepositFormDialog.vue` i komponenty widoku: `StatTile`, `ListSurface` z `.holdings-list-row`, `AccountIdentityBlock`, `IconAction`, `ConfirmActionDialog`, `IndexEmptyState`).

- **Wariant kontraktu:** istniejący system projektowy. Rozszerzamy go, bez drugiej palety i bez `shadcn init`.
- **Źródło tokenów:** `resources/css/app.css` (`:root` / `.dark`, `@theme inline`, `@utility` `page-title`, `table-header`, `kpi-lg`); wartości z design systemu C · Atrament i morski: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (podgląd, nie źródło wartości).
- **Wzorzec:** karta `PageTermDeposits` (sekcja „Różnice od dzisiejszej aplikacji”), precedens: zmiana `accounts-atrament-ui` i ADR-0044 (`StatPanel`, siatka z `table-header`, `Switch` pod panelem, `AccountMarkCompact`, dialog według ADR-0040).
- **Aktualizacja (2026-11-06):** obowiązują ADR-0045 i ADR-0047 — `Switch` w wierszu tytułu (warunek z ADR-0047), kafelek banku `AccountMarkCompact` ze skrótem, „Anuluj” `ghost`. Zdanie o `Switch` pod panelem w zarzucie 3 jest nieaktualne. Nowy ADR: **0051**. Szczegóły: `research.md` → „Aktualizacja 2026-11-06”.
- **Kitchen sink:** `/dev/ui/term-deposits` (`resources/js/pages/dev/TermDepositsKitchenSink.vue`, `resources/js/pages/dev/term-deposits-fixtures.ts`).

Wstępny audyt (2026-11-06): skan literałów 0 trafień (trzy pliki są już w `scripts/check-ui-literals.mjs`); użycia stylów Atramentu (`page-title`, `kpi`, `meta`, `table-header`, `FormField`, `StatPanel`, `Switch`) 0 w widoku i dialogu; style sprzed Atramentu: 8 w widoku, 7 w dialogu. Zarzuty do potwierdzenia w researchu:

1. Brakujący `StatPanel`: `TermDeposits.vue:110–136` jedna `StatTile` (`StatTile.vue:29` `text-3xl` zamiast `kpi-lg`); wzorzec ma „Suma lokat” z „W tym zarchiwizowane: …” (ujemna kwota `destructive`, czyli slot `footer-<id>`, nie `caption`) i Łączne środki („konta i lokaty”). Druga miara potrzebuje propsa `total_funds_minor_units` (`TotalFunds::forUser`, jak `AccountController.php:41`): PHP + typ w `types/term-deposits.ts` + `assertInertia` w jednym commicie.
2. Brakująca tabela: `TermDeposits.vue:185–300` `ListSurface` z `.holdings-list-row` (`app.css:869`, ostatni użytkownik) bez nagłówków kolumn, data zakończenia w podpisie (`:208–214`), akcje spadają do osobnej linii na wąskim ekranie (`:225` `w-full`). Wzorzec: Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje z `table-header`; bank przez `bankLabel` (`lib/account-identity.ts:63`). Do rozstrzygnięcia: „zł” w komórkach (ADR-0044 obejmuje tylko Konta → ADR-0045) czy `formatMoneyAmount` + stopka „Kwoty w zł” (ADR-0037, `js.md:16`); wspólna klasa siatki czy trzecia klasa per widok obok `.accounts-grid` (`app.css:692–745`). Oprocentowanie „3,80%” z wzorca to wartość podglądu (brak pola w modelu) — odpada.
3. Rząd tytułu i kontrolki: `:104` `text-lg font-semibold` zamiast `page-title`, opis „Lokaty terminowe.” schowany w podpisie listy (`:185`); `:142` `Checkbox` zamiast `Switch` (`js.md:29` już tego wymaga); `:203` Badge `secondary` zamiast `neutral`; `:167` drugi przycisk pustego stanu `secondary` zamiast `outline`. `Switch` pod panelem, jak w Kontach (ADR-0044 odrzucił go w rzędzie tytułu).
4. Dialog poza ADR-0040/0041: `TermDepositFormDialog.vue:162–246` `Label` + `div.grid gap-2` zamiast `FormField` (34 px; podpowiedź daty zakończenia `:212` bez `aria-describedby`); saldo otwarcia bez wariantu `money` („zł”, `:222`); „Anuluj” `secondary` zamiast `outline` (`:258`); `<Label for="account-bank">` (`:178`) opiera się na domyślnym id `AccountBankSelect`; ostrzeżenie o ujemnym saldzie jako `Alert` `warning` (`:248`), a ADR-0041 przenosi ostrzeżenia dialogów formularzy do bloku podglądu. Spinner i blokada zamykania już są.
5. Przypadkowa architektura — osierocone elementy DS: po zmianie `StatTile` i `.holdings-list-row` nie mają użytkowników, a `design-system.md:82` poleca `StatTile` („tylko w Lokatach”). Research rozstrzyga: usunąć (z wpisami w `check-ui-literals.mjs` i `design-system.md`) czy zostawić z powodem. `AccountIdentityBlock` zostaje (`DashboardHoldings`).

Bez zarzutu: wejście bez logowania (grupa `auth`, `verified`) i dwa stany puste (`IndexEmptyState`). „Suma lokat” z zarchiwizowanymi jest zamierzona (FR-REP-10, `context/archive/2026-11-03-term-deposits-ui-contract/research.md:29`).
