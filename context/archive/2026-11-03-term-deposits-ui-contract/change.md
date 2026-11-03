---
change_id: term-deposits-ui-contract
title: Term deposits ui contract
status: archived
created: 2026-11-03
updated: 2026-11-03
archived_at: 2026-11-03T22:26:37Z
---

## Notes

Zmiana wizualna prowadzona przez `/10x-ui term-deposits` — jeden widok, kontrakt istniejącego systemu projektowego.

- **Widok (jeden):** `resources/js/pages/TermDeposits.vue` (trasa `term-deposits.index`, `/term-deposits`) wraz z dialogiem `TermDepositFormDialog.vue`. Pole `AccountBankSelect.vue` i kafelek `AccountIdentityBlock.vue` są współdzielone z Kontami — zmiany w nich tylko w zakresie, jakiego wymaga ten widok.
- **Źródło tokenów:** `resources/css/app.css` — wartości w `:root` / `.dark`, publikowane przez `@theme inline`; notatki w `.docs/design-system.md`.
- **Wariant kontraktu:** istniejący system projektowy (Tailwind v4 + shadcn-vue, `resources/js/components/ui/*` + komponenty domenowe w `resources/js/components/`). Rozszerzamy, bez drugiej palety i bez `shadcn init`.
- **Reguły agenta:** `CLAUDE.md`, `AGENTS.md`, `.ai/rules/js.md`, `.ai/rules/css.md`.
- **Dziedziczone z `dashboard-ui-contract`:** `amountToneClass` (`resources/js/lib/amount-tone.ts`), `StatTile`, `column-caption`, wzorzec kitchen sink (`/dev/ui/dashboard`, `/dev/ui/sidebar`), `npm run check:ui` + lista plików w `scripts/check-ui-literals.mjs`.

### Wstępny audyt (wejście do `/10x-research`)

Skan literałów na plikach widoku: 0 hex, 0 klas palety, 0 arbitralnych `px`/`rem`; jedyne trafienie `hover:bg-foreground/[0.03]` (`TermDeposits.vue:159`) to udokumentowana konwencja hovera wiersza. Problem nie leży w brakujących tokenach, tylko w łamaniu kontraktu, braku współdzielonego komponentu i architekturze.

1. **Przypadkowa architektura — martwy przełącznik „Pokaż zarchiwizowane”** (`TermDeposits.vue:126-130`): `:checked` / `@update:checked` nie istnieją w reka-ui 2.11. Formularze archiwizuj/przywróć (`:260`, `:307`) nie przekazują `show_archived`, choć kontroler je obsługuje (`TermDepositController.php:116,130`). Użytkownik nie zobaczy ani nie przywróci zarchiwizowanej lokaty bez edycji URL. Issue #186.
2. **Przypadkowa architektura — suma ≠ lista** (`TermDepositController.php:23-39`): „Suma lokat” liczy `$allTermDeposits`, także ukryte zarchiwizowane; archiwizacja nie wymaga zerowego salda (`can_archive`, `:166`). Użytkownik widzi sumę, której nie złoży z widocznych wierszy. Do rozstrzygnięcia w research: celowe czy błąd.
3. **Brakujący współdzielony komponent — trzy ręczne dialogi potwierdzenia** (`TermDeposits.vue:203-344`): ten sam Dialog+Form+Footer; brak spinnera; `errors.term_deposit` z `destroy` (`TermDepositController.php:90-92`) nigdzie nie jest wyświetlany — nieudane usunięcie jest ciche.
4. **Kolor kwot niezgodny z kontraktem** (`TermDeposits.vue:60-64`, `:75-85`, `:160`): `totalClass` maluje 0 zł na zielono („Zero nigdy nie jest zielone”), `balanceClass` to lokalny helper zamiast `amountToneClass`, zarchiwizowane wiersze mają `opacity-60` + kolory statusu zamiast całego wiersza w `muted-foreground`.
5. **Typografia / komponent kafelka** (`TermDeposits.vue:108`, `:114-123`, `:153`): `h1` `text-lg` zamiast `text-xl`; karta sumy ręcznie z `Card` + `CardTitle text-2xl` zamiast `StatTile`; tytuł „Lokaty” powtórzony w `ListSurface`.

Pytania o stany: pusty stan, gdy wszystkie lokaty są zarchiwizowane, a przełącznik wyłączony (`IndexEmptyState`, `:139` zachęca do dodania pierwszej lokaty); brak spinnera przy zapisie w `TermDepositFormDialog.vue:244`.

### Koordynacja z `accounts-ui-contract`

Lokaty to niemal klon Kont: zarzuty 1, 3, 4 odpowiadają C1/C2/C5 w `context/changes/accounts-ui-contract/research.md`. Tamta zmiana ma research, nie ma planu; `ConfirmActionDialog` jeszcze nie istnieje. Współdzielony dialog potwierdzenia i ujednolicony kafelek banku (`AccountBankSelect.vue:66,89` `text-[10px]`) powstają w `accounts-ui-contract`; ta zmiana je **przyjmuje**, nie buduje drugiej wersji. Jeśli Konta nie zdążą przed planem tej zmiany — uzgodnić w `/10x-plan`, która zmiana tworzy komponent.
