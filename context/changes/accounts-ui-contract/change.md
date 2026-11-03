---
change_id: accounts-ui-contract
title: Accounts UI contract pass (tokens, shared components, states)
status: new
created: 2026-11-03
updated: 2026-11-03
archived_at: null
---

## Notes

Zmiana wizualna prowadzona przez `/10x-ui accounts`.

- **Widok (jeden):** `resources/js/pages/Accounts.vue` (trasa `accounts.index`) wraz z dialogiem `AccountFormDialog.vue` i jego polem `AccountBankSelect.vue`. Kafelek znaku banku (`AccountIdentityBlock.vue`, `AccountMarkCompact.vue`) jest współdzielony z innymi widokami — zmiany w nim tylko w zakresie, jakiego wymaga ten widok.
- **Źródło tokenów:** `resources/css/app.css` — wartości w `:root` / `.dark`, publikowane przez `@theme inline`; notatki w `.docs/design-system.md`.
- **Wariant kontraktu:** istniejący system projektowy (Tailwind v4 + shadcn-vue, `resources/js/components/ui/*` + komponenty domenowe w `resources/js/components/`). Rozszerzamy, bez drugiej palety i bez `shadcn init`.
- **Reguły agenta:** `.ai/rules/js.md`, `.ai/rules/css.md`, `.ai/skills/openship-ui-development/SKILL.md`.
- **Dziedziczone z `dashboard-ui-contract` (zarchiwizowana 2026-11-03):** `amountToneClass` (`resources/js/lib/amount-tone.ts`) jako jedyne źródło koloru kwot ze znakiem, klasa `column-caption`, `StatTile`, kitchen sink pod `/dev/ui/dashboard` (wzorzec dla bramki wizualnej), `npm run check:ui` + lista plików w `scripts/check-ui-literals.mjs`.

### Wstępny audyt (wejście do `/10x-research`)

Skan literałów na plikach widoku: 3 trafienia (`Accounts.vue:157` `hover:bg-foreground/[0.03]`; `AccountBankSelect.vue:66,89` `text-[10px]`), 0 hex, 0 klas palety.

1. **Przypadkowa architektura — martwy przełącznik „Pokaż zarchiwizowane”** (`Accounts.vue:124-128`): `:checked` / `@update:checked` nie istnieją w reka-ui 2.11 (`CheckboxRoot` ma `modelValue` / `update:modelValue`). Użytkownik nie zobaczy ani nie przywróci zarchiwizowanego konta z UI. Ten sam błąd: `TermDeposits.vue:128`, `Budget.vue:309`, `OperationsImport.vue:354` (poza widokiem — kandydaci na deferred).
2. **Brakujący współdzielony komponent — trzy ręcznie sklejone dialogi potwierdzenia** (`Accounts.vue:194-309`): usuń / archiwizuj / przywróć to ten sam Dialog+Form+Footer; przycisk potwierdzenia tylko się wyłącza, bez stanu ładowania. Wzorzec powtarza się w `TermDeposits.vue` i `Budget.vue`.
3. **Brakujący komponent / token — kafelek znaku banku w 4 kopiach i wiersz listy kopiowany między widokami** (`AccountIdentityBlock.vue:38`, `AccountMarkCompact.vue:42`, `AccountBankSelect.vue:56,79`; `Accounts.vue:157` = `TermDeposits.vue:159`). Ten sam bank wygląda inaczej w selekcie (inny radius, kropka `text-[10px]` zamiast ikony `Building2` dla „Inny bank”).
4. **Przypadkowa architektura — dwa różne dropdowny w jednym formularzu + sprzeczna reguła** (`AccountFormDialog.vue:154` natywny `.native-select` obok Reka `Select` w `AccountBankSelect.vue:51`; `SKILL.md` zabrania Reka Select w formularzach). Do decyzji: wyjątek zapisany w regule (logo banku) albo ujednolicenie.
5. **Typografia i kolor kwot niezgodne z kontraktem** (`Accounts.vue:106` nagłówek `text-lg` zamiast `text-xl` / `Heading`; `:151` tytuł „Konta” powtórzony w karcie; `:117` suma bez `tabular-nums`; `:58-60, 75-85` lokalne helpery „znak → kolor” zamiast `amountToneClass`).

Pytania o stany: pusty stan, gdy wszystkie konta są zarchiwizowane, a przełącznik wyłączony (`Accounts.vue:137-147`); brak informacji zwrotnej przy `router.get` przełącznika.

Równolegle otwarte worktree UI (`budget-view-ui`, `month-plan-ui-contract`, `sidebar-ui-contract`) mogą dotykać `app.css` — nowe tokeny uzgadniać przy scalaniu.
