# Lokaty w systemie „C · Atrament i morski” — Plan Brief

> Full plan: `context/changes/term-deposits-atrament-ui/plan.md`
> Research: `context/changes/term-deposits-atrament-ui/research.md`

## What & Why

Widok Lokaty i dialog lokaty to ostatnie ekrany posiadań sprzed Atramentu: jedna karta `StatTile` z `text-3xl` i lista bez nagłówków na `.holdings-list-row`. Do tego `Checkbox` zamiast `Switch` i dialog z ręcznymi polami 32 px bez „zł”. ADR-0045 ustalił, że widoki idą za stronami design systemu, więc Lokaty przechodzą na kartę `PageTermDeposits`, jak Konta.

## Starting Point

Konta już mają ten wzorzec: wiersz tytułu ze `Switch`, `StatPanel`, tabelę `.accounts-grid` z „zł” i kafelek `AccountMarkCompact` ze skrótem banku (ADR-0044/0045/0047). Lokaty używają jeszcze `StatTile`, `.holdings-list-row`, `AccountIdentityBlock` z logo (`mark_url`) i `Checkbox`. Kontroler nie wysyła Łącznych środków.

## Desired End State

Na `/term-deposits` są:
- tytuł z opisem, a obok niego `Switch` (gdy jest co pokazać) i „Dodaj lokatę”;
- panel z „Suma lokat” (z „W tym zarchiwizowane: …”) i „Łączne środki”;
- tabela Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje z „zł” w saldach, dwiema liniami na telefonie i „—” przy braku daty.

Dialog lokaty ma pola `FormField` 34 px, „zł” w saldzie i „Anuluj” `ghost`. `StatTile` i `.holdings-list-row` znikają z repo.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Wzorzec | Karta `PageTermDeposits`, odejścia w ADR-0051 | ADR-0045: strona design systemu jest domyślna, odejście wymaga ADR | Research |
| Jednostka w tabeli | „zł” w każdym saldzie, bez stopki „Kwoty w zł” | Jedna kolumna kwot i sumy w panelu, jak Konta (ADR-0044) | Research |
| Siatka | Własna `.term-deposits-grid` (kolumny z karty: `1fr 150 150 150 96`) | Repo nazywa siatki per widok, Lokaty mają 5 kolumn | Research |
| Telefon | Dwie linie: nazwa + saldo; bank, data i akcje | Karta nie ma układu mobilnego; żadna kolumna nie znika | Plan |
| Brak daty | Wyciszone „—” + `sr-only` „Bez daty zakończenia” | Równa kolumna, czytelne dla czytnika ekranu | Plan |
| `Switch` | W wierszu tytułu, tylko gdy są zarchiwizowane albo filtr włączony | Warunek ADR-0047 rozciągnięty na Lokaty | Research |
| Znak banku | `AccountMarkCompact` ze skrótem (ADR-0047), bank tekstem w kolumnie | Ten sam kafelek co Konta i dialogi | Research |
| Ton miar | `amountToneClass` na obu miarach | Spójność z Kontami; karta nie toni „Suma lokat” (odejście w ADR) | Plan |
| `mark_url` | Usunięty z payloadu Lokat w fazie widoku | Czytał go tylko `AccountIdentityBlock` w Lokatach | Research |
| Ostrzeżenie w dialogu | `Alert` `warning` zostaje | `design-system.md` i `js.md` wprost; brak odczytu „przed → po” | Research |
| „Anuluj” | `ghost` (ADR-0045), nie `outline` z karty | Nowszy ADR wygrywa z artefaktem (ADR-0047) | Research |
| Martwe elementy | Usunąć `StatTile` i `.holdings-list-row` z wpisami w strażnikach i dokumentach | Inaczej następny agent użyje martwego kafelka | Research |
| Oprocentowanie, notka stopki | Pominięte | Brak pola w modelu; notka to objaśnienie podglądu | Research |

## Scope

**In scope:**
- ADR-0051, `design-system.md`, `js.md`
- prop `total_funds_minor_units` (PHP, typ, fixtures, `assertInertia`) i usunięcie `mark_url` z Lokat
- `TermDeposits.vue`, `.term-deposits-grid` w `app.css`, nowe klucze `term_deposits.*`
- `TermDepositFormDialog.vue` z propsem `form` i stanami w `/dev/ui/dialogs`
- `TermDepositsHardcodedUiTest`, usunięcie `StatTile.vue` i `.holdings-list-row`
- scenariusze `/dev/ui/term-deposits`, zrzuty 390/1280 px w obu motywach

**Out of scope:**
- „Anuluj” `ghost` w innych dialogach (zmiana wdrażająca ADR-0045)
- `AccountIdentityBlock` i `mark_url` Dashboardu
- `FormPreview` w dialogu lokaty
- oprocentowanie
- definicja „Suma lokat”
- test E2E Lokat

## Architecture / Approach

Kolejność jak w zmianach Kont: dokumenty → kontrakt propsów → widok → dialog → stany i bramka. Faza 2 tylko dodaje prop. `mark_url` znika w fazie 3 w jednym commicie z widokiem, bo wymaga go `AccountIdentityBlock`. Układ siedzi w nazwanych klasach `@layer components` w `app.css`, wartości w tokenach, a straże (`check:ui`, `TermDepositsHardcodedUiTest`) pilnują oczyszczonych plików.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0051, `design-system.md`, `js.md` | Sprzeczność z ADR-0044/0047 albo z kartą bez zapisanego powodu |
| 2. Kontrakt propsów | `total_funds_minor_units` z testami na PostgreSQL | Fixtures sinka bez nowego pola łamią `types:check` |
| 3. Widok Lokaty | Tytuł ze `Switch`, `StatPanel`, tabela, usunięcie martwych elementów, straż | Druga linia na 390 px zbyt gęsta przy długiej nazwie banku |
| 4. Dialog lokaty | `FormField`, `money`, `ghost`, prop `form`, stany w sinku dialogów | Zgubienie decyzji F1 (computed dla ukrytego pola) przy przepisaniu |
| 5. Stany i bramka | Scenariusz `long-names`, zrzuty, `ci:check`, `sloppy` | Stany interakcji widoczne tylko na zrzutach |

**Prerequisites:** ADR-0045 i ADR-0047 zatwierdzone (`0d071b4`, `55b0585` w historii gałęzi). Numer ADR-0051 zarezerwowany w poleceniu zmiany. Worktree po `composer install`, `npm ci`, `npm run build`.
**Estimated effort:** ~3–4 sesje w 5 fazach (wzorzec przetarty przez Konta).

## Open Risks & Assumptions

- Numery 0046 i 0048–0050 rezerwują równoległe zmiany, więc ADR-0051 nie musi być najwyższym plikiem w `adr/` w chwili zapisu.
- `term_deposits.list_description` dostaje dłuższy tekst; zakładam, że czyta go tylko widok Lokat (sprawdzić `grep` przed zmianą).
- Układ telefonu nie ma wzorca w karcie; o jego czytelności rozstrzygają zrzuty z fazy 5.

## Success Criteria (Summary)

- `/term-deposits` wygląda jak karta `PageTermDeposits` (z zapisanymi odejściami) na 1280 px i czyta się bez przewijania w poziomie na 390 px, w obu motywach.
- „Łączne środki” na Lokatach są równe tej mierze na Kontach i Dashboardzie.
- `composer ci:check`, `sloppy ci`, `check:ui` i nowa straż przechodzą; w repo nie ma `StatTile` ani `.holdings-list-row`.
