---
change_id: operations-ui-contract
title: Operations UI contract pass (tokens, shared components, states)
status: implementing
created: 2026-11-03
updated: 2026-11-03
archived_at: null
---

## Notes

Zmiana wizualna prowadzona przez `/10x-ui operations`.

- **Widok (jeden):** `resources/js/pages/Operations.vue` (trasa `operations`) wraz z wierszami `FinancialOperationRow.vue`, `OwnTransferRow.vue`, `CapitalMovementRow.vue` i panelem `OperationsFilters.vue`. Dialogi formularzy (`IncomeFormDialog`, `ExpenseFormDialog`, `OwnTransferFormDialog`, `CapitalMovementFormDialog`) są poza zakresem, poza tym, czego wymaga stan `error` w macierzy.
- **Źródło tokenów:** `resources/css/app.css` — wartości w `:root` / `.dark`, publikowane przez `@theme inline`; nazwane klasy w `@layer components` / `@utility`; notatki w `.docs/design-system.md`.
- **Wariant kontraktu:** istniejący system projektowy (Tailwind v4 + shadcn-vue, `resources/js/components/ui/*` + komponenty domenowe w `resources/js/components/`). Rozszerzamy, bez drugiej palety i bez `shadcn init`.
- **Reguły agenta:** `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/skills/openship-ui-development/SKILL.md`.
- **Dziedziczone:** klasa `column-caption`, `ListSurface`, `IndexEmptyState`, `amountToneClass`, kitchen sinki jako wzorzec bramki (`/dev/kitchen-sink/budget`, `/dev/month-plan`, `/dev/ui/dashboard`), `npm run check:ui` + lista plików w `scripts/check-ui-literals.mjs` (Operacje jeszcze na niej nie są).

### Wstępny audyt (wejście do `/10x-research`)

Skan literałów na plikach widoku: 6 trafień, 0 hex, 0 klas palety — `text-[11px]` ×3 (`Operations.vue:232,272,305`), `hover:bg-foreground/[0.03]` ×3 (`FinancialOperationRow.vue:49`, `OwnTransferRow.vue:27`, `CapitalMovementRow.vue:26`). `OperationsFilters.vue`: 0.

1. **Brakujący token — nagłówki kolumn** (`Operations.vue:232,272,305`): ręcznie złożone `text-[11px] … uppercase`, wiersz nagłówka skopiowany 3×, choć `column-caption` istnieje (`app.css:199`) — a `.column-header` (`app.css:233`) jest jego identycznym duplikatem z równoległej zmiany. Zmiana skali podpisów nie dotrze do Operacji.
2. **Brakujący token — hover wiersza listy**: literał `foreground/[0.03]` w 3 wierszach tego widoku i 5 innych miejscach (`Accounts.vue:157`, `TermDeposits.vue:159`, `DashboardHoldings.vue:142,203`, `ImportPreviewRow.vue:266`), bez nazwanej klasy. Odcienia hover nie da się dostroić na warstwie tokenów.
3. **Przypadkowa architektura — niewidoczny powód blokady** (`Operations.vue:144-168`): powód w `title` na wyłączonym `Button`, który ma `disabled:pointer-events-none` (`ui/button/index.ts:7`) — podpowiedź nigdy się nie pojawi, niedostępna z klawiatury i dla czytnika. Użytkownik z jednym kontem nie wie, czemu „Przelew własny” jest szary.
4. **Hierarchia nagłówka strony** (`Operations.vue:134-174`): `h1` `text-lg` zamiast `text-xl font-semibold` (wzorzec wiersza tytułu z `Budget.vue`); pięć przycisków w wariancie primary, dwa bez ikon — na 390 px ściana turkusu, brak jednej głównej akcji.
5. **Przypadkowa architektura — pusty stan i sekcje** (`Operations.vue:178,189`; `OperationsController.php` `hasFinancialOperations`): pusty stan i filtry zależą tylko od dochodów/wydatków — użytkownik z samymi przelewami własnymi widzi „Brak operacji” i pod nim listę przelewów, bez filtrów. Trzy karty z osobnymi paginatorami odzwierciedlają kolejność dodawania funkcji; pierwsza bez tytułu (`:226-230`). Spłaszczenie list to decyzja domenowa — kandydat na deferred.

Pytania o stany: brak wskaźnika ładowania listy przy paginacji/zmianie filtrów (spinner tylko w polu wyszukiwania); focus-visible przycisków akcji wierszy (`*Actions.vue`); `error` dotyczy głównie dialogów.

Równolegle otwarta `accounts-ui-contract` też celuje w hover wiersza (`Accounts.vue:157`, `TermDeposits.vue:159`) i może dotykać `app.css` — nazwaną klasę wiersza uzgodnić przy scalaniu.
