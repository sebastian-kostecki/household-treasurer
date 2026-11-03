# Accounts UI contract pass — Plan Brief

> Full plan: `context/changes/accounts-ui-contract/plan.md`
> Research: `context/changes/accounts-ui-contract/research.md`

## What & Why

Widok Kont (`Accounts.vue` z formularzem konta i selektorem banku) przechodzi przez kontrakt 10x-ui. Najpoważniejsza wada jest funkcjonalna: przełącznik „Pokaż zarchiwizowane” nigdy nie działał (martwe wiązanie reka-ui, issue #186). Zarchiwizowanego konta nie da się więc zobaczyć ani przywrócić z UI. Do tego potwierdzenia nie dają informacji zwrotnej, a kafelek banku, kolor kwot i dwa różne dropdowny odbiegają od systemu projektowego.

## Starting Point

Widok jest prawie czysty tokenowo (0 hex, 0 klas palety, 2 literały `text-[10px]`). Dryf siedzi w kopiach: 3 ręcznie sklejone dialogi potwierdzenia, 2 kopie kafelka banku, lokalne helpery „znak → kolor” (0 zł na zielono). Z `dashboard-ui-contract` są już `amountToneClass`, `StatTile`, kitchen sink `/dev/ui/dashboard` i skan `npm run check:ui` w CI.

## Desired End State

Przełącznik działa, pokazuje prawdziwy stan i każda akcja wraca z zachowanym filtrem. Usuń, archiwizuj i przywróć to jeden `ConfirmActionDialog` ze spinnerem, blokadą zamykania i błędem serwera. Selektor banku pokazuje ten sam kafelek co lista. Suma to `StatTile` „Płynność dysponowalna” z neutralnym zerem. Lista, w której zostały same zarchiwizowane konta, ma własny pusty stan z wyjściem, a nieparsowalna kwota pokazuje polski błąd i blokuje zapis. `/dev/ui/accounts` pokazuje macierz 7 stanów, a w folderze zmiany są zrzuty po każdej fazie wizualnej.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Martwy przełącznik (C1) | `v-model` + lokalny ref; `show_archived` w query wszystkich 5 akcji | Wzorzec z `budget-view-ui`; Wayfinder `.form(…, { query })` i `$request->boolean()` już to obsługują | Research + Plan |
| Pusty stan „same archiwa” | Nowy prop `archived_accounts_count`; wariant „Wszystkie konta są zarchiwizowane”; przełącznik widoczny tylko przy archiwach lub włączonym filtrze | Każdy osiągalny stan mówi prawdę i ma wyjście; nowy użytkownik nie widzi martwego filtra | Plan |
| Dropdowny w formularzu (C4) | Natywny dla prostych wyliczeń, Reka `Select` gdy opcja ma logo; przepisać `SKILL.md:45` | Zgodne z kodem (19 natywnych, 5 Reka) i nie pozwala „naprawić” selektora logo z powrotem | Plan |
| Prymitywy `components/ui` | `Select` → `rounded-xl`; X dialogu → `focus-visible` 3 px `ring/50` + „Zamknij” | Kontrakt kontrolek i fokusu dla całej aplikacji, precedens `Badge` | Plan |
| Potwierdzenia (C2) | `ConfirmActionDialog` na `ui/dialog`, tylko w Kontach | Bez nowego prymitywu; 13 pozostałych kopii przejdzie w swoich przebiegach | Research + Plan |
| Błąd salda otwarcia | Walidacja na żywo, polski komunikat przy polu, `aria-invalid`, blokada Zapisz | Dziś można zapisać inną kwotę niż wpisana, bez żadnego sygnału | Plan |
| Zrzuty (bramka) | Jednorazowy, niecommitowany skrypt z `playwright-core` z cache npx, 1280/390 px, jasny/ciemny; PNG w folderze zmiany | Dowód w repo bez nowej zależności, zgodnie z konwencją `design-system.md`; dashboard zamknięto bez zrzutów (F4) | Plan |
| Błąd salda — brzmienie | „Wpisz kwotę, np. 1200,50.” | To samo co `month_plan.amount_invalid` na `development` | Plan |
| Rozmiar `h1` | Dokument → `text-lg` (jak 7 stron domenowych) | Rozbieżność doc–kod, nie wada Kont; zero zmian wizualnych gdzie indziej | Plan |
| Etykieta sumy | „Płynność dysponowalna” | Ta sama liczba co na Dashboardzie (`SpendableLiquidity`, z archiwami); `CONTEXT.md` odradza samą „płynność” | Research |
| Kafelek w selektorze (C3) | `AccountMarkCompact` bez zmian (`rounded-md`, `Building2`) | Komponent jest źródłem wyglądu; brak decyzji do podjęcia | Research |

## Scope

**In scope:**
- `Accounts.vue`, `AccountFormDialog.vue`, `AccountBankSelect.vue`, nowy `ConfirmActionDialog.vue`
- `AccountController` (przekierowania z filtrem, `SpendableLiquidity`, `archived_accounts_count`) i testy Feature
- `ui/select` (promień), `ui/dialog` (X), `/dev/ui/accounts`, skrypt zrzutów
- `.ai/rules/js.md`, `SKILL.md:45`, `.docs/design-system.md`, `check-ui-literals.mjs`

**Out of scope:**
- Przełączniki w TermDeposits / OperationsImport, 13 innych dialogów potwierdzenia, walidacja kwot w innych formularzach
- `ui/alert-dialog`, X w `ui/sheet`, `DropdownMenuContent`, rozmiar `h1` na innych stronach
- i18n komunikatów serwera, duplikat mapy znaków banków, usunięcie `is_negative`, Playwright jako zależność

## Architecture / Approach

Kolejność 10x-ui: najpierw kitchen sink i nieużywany jeszcze `ConfirmActionDialog` (zrzuty „przed”), potem prymitywy i notatki (jedyna faza zmieniająca inne widoki), potem markup widoku (C1–C5), potem stany i jeden nowy prop (PHP + TS + `assertInertia` w jednym commicie), na końcu reguła i skan. Każda faza wizualna kończy się zrzutami i skanem literałów.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kitchen sink i biblioteka | `/dev/ui/accounts`, typy propsów, `ConfirmActionDialog`, skrypt i zrzuty „przed” | Podgląd z worktree (osobna baza, build) musi działać przed zrzutami |
| 2. Wartości kontraktu | `Select` `rounded-xl`, X z fokusem i „Zamknij”, notatki systemu | Zmienia 7 formularzy z `Select` i wszystkie dialogi |
| 3. Jeden widok (C1–C5) | Działający przełącznik z filtrem, potwierdzenia, kafelek, `StatTile`, ton kwot | Blokada zamykania dialogu w trakcie żądania na reka-ui |
| 4. Stany | Pusty stan „same archiwa”, przełącznik w trakcie wizyty, błąd kwoty, macierz 7 stanów | Zmiana kontraktu propsów; stany interakcyjne trzeba wywołać w skrypcie zrzutów |
| 5. Zabezpieczenie | `check:ui` na plikach Kont, reguły agentów, odroczone zarzuty | Trzy różne straże literałów w repo; Konta idą do `check:ui` zgodnie z `js.md` |

**Prerequisites:** gałąź `accounts-ui-contract` przewinięta do `development` (`git merge --ff-only development`; jest jego przodkiem); w worktree `composer install`, `.env`, `key:generate`, `npm ci`, `npm run build`; działający stos Sail (PostgreSQL, sieć `household-treasurer_sail`) dla podglądu i `ci:check`.
**Estimated effort:** ~3–4 sesje w 5 fazach (fazy 1 i 4 najcięższe przez kitchen sink i zrzuty stanów).

## Open Risks & Assumptions

- `resources/js/ui-contract.test.ts` (z `budget-view-ui`, już w `development`) failuje, gdy `Accounts.vue` straci martwe wiązanie, a zostanie na liście wyjątków; faza 3 usuwa wpis w tym samym commicie.
- Na `development` jest już `SegmentedControl`; typ konta zostaje natywnym selectem (decyzja C4), zamiana byłaby osobnym przebiegiem.
- Zakładamy, że skrypt z `playwright-core` z cache npx działa jak w `sidebar-ui-contract`; jeśli cache zniknie, `npx -y playwright-core` pobierze go ponownie bez zmiany repo.
- Zmiana `ui/select` i `ui/dialog` jest globalna; sprawdzenie innych formularzy jest ręczne (brak testów zrzutów).

## Success Criteria (Summary)

- Użytkownik może zarchiwizować konto, zobaczyć je, przywrócić i nie zgubić filtra.
- Każdy stan widoku Kont (7 stanów, desktop i 390 px, oba motywy) jest pokazany w kitchen sinku i na zrzutach.
- `composer ci:check` i `vendor/bin/sloppy ci` przechodzą; `check:ui` pilnuje plików Kont.
