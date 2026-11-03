# Term Deposits UI contract pass — Plan Brief

> Full plan: `context/changes/term-deposits-ui-contract/plan.md`
> Research: `context/changes/term-deposits-ui-contract/research.md`

## What & Why

Widok Lokat (`TermDeposits.vue` z formularzem lokaty) przechodzi przez kontrakt 10x-ui. Najpoważniejsza wada jest funkcjonalna: przełącznik „Pokaż zarchiwizowane” nigdy nie działał (martwe wiązanie reka-ui, issue #186), więc zarchiwizowanej lokaty nie da się zobaczyć ani przywrócić z UI. Do tego „Suma lokat” wlicza zarchiwizowane lokaty bez słowa wyjaśnienia, potwierdzenia nie dają informacji zwrotnej, a kolor kwot i zarchiwizowane wiersze odbiegają od systemu projektowego.

## Starting Point

Widok jest czysty tokenowo (0 hex, 0 klas palety, 0 arbitralnych wartości). Dryf siedzi w kopiach z Kont: ten sam martwy przełącznik, 3 ręczne dialogi potwierdzenia, lokalne helpery „znak → kolor” (0 zł na zielono), `opacity-60` na zarchiwizowanych wierszach i ręczny kafelek sumy. Siostrzana zmiana `accounts-ui-contract` ma plan, który buduje `ConfirmActionDialog` i poprawia prymitywy `ui/select` / `ui/dialog`. Ta zmiana startuje po jej scaleniu.

## Desired End State

Przełącznik działa, pokazuje prawdziwy stan i każda akcja wraca z zachowanym filtrem. Usuń, archiwizuj i przywróć to `ConfirmActionDialog` ze spinnerem, blokadą zamykania i błędem serwera. Suma to `StatTile` z neutralnym zerem, a gdy zarchiwizowane lokaty mają saldo, stopka mówi „W tym zarchiwizowane: X zł”. Zarchiwizowany wiersz jest szary bez przezroczystości, ale ujemne saldo zostaje czerwone (FR-TD-08). Lista, w której zostały same zarchiwizowane lokaty, ma własny pusty stan z wyjściem, a nieparsowalna kwota pokazuje polski błąd i blokuje zapis. `/dev/ui/term-deposits` pokazuje macierz 7 stanów, a w folderze zmiany są zrzuty po każdej fazie wizualnej.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Kolejność względem Kont | Start po scaleniu `accounts-ui-contract` do `development` | Jeden `ConfirmActionDialog`, brak konfliktów we wspólnych listach; ta zmiana tylko przyjmuje i dopisuje | Plan |
| Suma ≠ widoczne wiersze (C2) | Arytmetyka bez zmian; stopka `StatTile` „W tym zarchiwizowane: X zł” tylko przy kwocie ≠ 0; nowy prop `archived_term_deposits_total_minor_units` | Liczba sama się tłumaczy dokładnie wtedy, gdy jest rozbieżność, niezależnie od filtra | Research + Plan |
| Zarchiwizowana lokata z ujemnym saldem | Wiersz `muted-foreground` bez `opacity`, ujemne saldo zostaje `text-destructive` (tak samo ujemna kwota w stopce sumy); wyjątek zapisany w `design-system.md` i przy regule `amountToneClass` w `js.md` | FR-TD-08 wymaga oznaczenia ujemnego salda na każdej powierzchni | Plan |
| Zachowania z Kont | Wszystkie: filtr przy 5 akcjach, `archived_term_deposits_count` + pusty stan „same archiwa” + przełącznik tylko gdy ma sens, blokada przełącznika w trakcie wizyty, walidacja salda otwarcia na żywo + `aria-invalid` | Siostrzane widoki zachowują się tak samo; zamyka każdą lukę z macierzy stanów | Plan |
| Martwy przełącznik (C1) | `v-model` + lokalny ref (wzorzec `Budget.vue`); wpis znika z `ui-contract.test.ts` w tym samym commicie | Wzorzec już w repo, test failuje, dopóki wpis zostaje | Research |
| Potwierdzenia (C3) | `ConfirmActionDialog` z Kont, bez zmian w komponencie | Brak drugiej wersji; usuwanie wreszcie pokazuje `errors.term_deposit` | Research + Plan |
| Kolor kwot (C4) | Tylko `amountToneClass`; w zarchiwizowanym wierszu tylko dla salda `< 0` | Zero nigdy nie jest zielone; brak nowych helperów „znak → kolor” | Research + Plan |
| Kafelek i tytuł (C5) | `StatTile` „Suma lokat”; `ListSurface` bez tytułu, z opisem jak w Kontach | Kafelek KPI z `tabular-nums`; „Lokaty” pada raz | Research |
| Rozmiar `h1` | Zostaje `text-lg`; dokument dostosowują Konta | Rozbieżność doc–kod dla całego repo, nie wada Lokat | Research |
| Kitchen sink i zrzuty | `/dev/ui/term-deposits` z kontrolerem i testem; jednorazowy skrypt `playwright-core`, 1280/390 px, jasny i ciemny | Ta sama konwencja co Dashboard i Konta, bez nowej zależności | Research + Plan |

## Scope

**In scope:**
- `TermDeposits.vue`, `TermDepositFormDialog.vue`, nowy `types/term-deposits.ts`
- `TermDepositController` (helper przekierowania z filtrem, 2 nowe propsy) i testy Feature
- `/dev/ui/term-deposits` (kontroler, test, strona, fixture'y), skrypt zrzutów
- `check-ui-literals.mjs`, `.ai/rules/js.md`, `.docs/design-system.md` (dopiski), `ui-contract.test.ts`

**Out of scope:**
- Budowa `ConfirmActionDialog` i zmiany `ui/select` / `ui/dialog` (robi `accounts-ui-contract`)
- „Suma lokat” na Dashboardzie, zmiana arytmetyki sumy, treść `archive_confirm_body`
- Przełącznik w `OperationsImport.vue`, i18n komunikatów serwera, usunięcie `is_negative`, Playwright jako zależność

## Architecture / Approach

Kolejność 10x-ui bez fazy prymitywów (te robią Konta): najpierw kitchen sink i zrzuty „przed”, potem markup widoku (C1, C3, C4, C5) na gotowych komponentach. Dalej dwa nowe propsy (kwota i liczba zarchiwizowanych, liczone z już pobranej kolekcji, PHP + TS + fixture'y + `assertInertia` w jednym commicie) i brakujące stany. Na końcu skan i dopiski w regułach. Wywołania wspólnych elementów dopasowuje się do kodu scalonego z Kont, nie do planu Kont.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kitchen sink i zrzuty „przed” | `/dev/ui/term-deposits`, typy propsów, skrypt i zrzuty „przed” | Podgląd (osobna baza, build) musi działać przed zrzutami |
| 2. Jeden widok (C1, C3, C4, C5) | Działający przełącznik z filtrem, `ConfirmActionDialog`, ton kwot, wyciszony wiersz, `StatTile` | Scalony `ConfirmActionDialog` może mieć inne API niż w planie Kont |
| 3. Stany i nowe propsy | Stopka sumy, pusty stan „same archiwa”, przełącznik w trakcie wizyty, walidacja salda, spinner zapisu, macierz 7 stanów | Zmiana kontraktu propsów; stany interakcyjne trzeba wywołać w skrypcie zrzutów |
| 4. Zabezpieczenie | `check:ui` na plikach Lokat, sandbox w regułach, wyjątek FR-TD-08 w notatkach | Równoległe gałęzie UI dopisują do tych samych list |

**Prerequisites:** `accounts-ui-contract` scalone do `development`; gałąź `term-deposits-ui-contract` od aktualnego `development` z folderem zmiany w pierwszym commicie; działający stos Sail (PostgreSQL) dla podglądu i `ci:check`.
**Estimated effort:** ~2–3 sesje w 4 fazach (faza 3 najcięższa przez propsy i zrzuty stanów).

## Open Risks & Assumptions

- Zakładamy, że Konta dowiozą `ConfirmActionDialog` (z `errors` i blokadą zamykania) oraz prop `invalid` w `AccountBankSelect` jak w swoim planie. Inaczej faza 2/3 dopasowuje wywołania albo zgłasza brak, zamiast budować drugą wersję.
- Do czasu przejścia przez Konta ta zmiana jest zablokowana.
- Wyjątek FR-TD-08 łamie zasadę „zarchiwizowane bez kolorów statusu” dla jednego przypadku; jest zapisany w `design-system.md`, żeby nikt go nie „naprawił”.
- Błąd usuwania w dialogu pokaże angielski tekst serwera, dopóki nie przejdzie osobna zmiana i18n.

## Success Criteria (Summary)

- Użytkownik może zarchiwizować lokatę, zobaczyć ją, przywrócić i nie zgubić filtra, a suma mówi, ile w niej jest zarchiwizowanych pieniędzy.
- Każdy stan widoku Lokat (7 stanów, desktop i 390 px, oba motywy) jest pokazany w kitchen sinku i na zrzutach.
- `composer ci:check` i `vendor/bin/sloppy ci` przechodzą; `check:ui` pilnuje plików Lokat.
