---
date: 2026-11-06T15:27:54+01:00
researcher: Claude (Opus 5.5)
git_commit: 9aec4af
branch: development
repository: household-treasurer
topic: "Audyt UI widoku Lokaty (TermDeposits.vue) i TermDepositFormDialog względem karty PageTermDeposits systemu Atrament"
tags: [research, ui, atrament, term-deposits, stat-panel, data-table, switch, form-field, adr-0045]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude (Opus 5.5)
---

# Research: Audyt UI widoku Lokaty względem karty PageTermDeposits

**Date**: 2026-11-06T15:27:54+01:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: 9aec4af (drzewo robocze z niezatwierdzonymi zmianami dokumentów, patrz „Stan dokumentów”)
**Branch**: development
**Repository**: household-treasurer

## Research Question

Potwierdzić pięć zarzutów z `change.md` dla `resources/js/pages/TermDeposits.vue` i `resources/js/components/TermDepositFormDialog.vue` względem karty `PageTermDeposits` design systemu Atrament (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA) i rozstrzygnąć cztery pytania: „zł” w komórkach czy stopka, wspólna klasa siatki czy klasa widoku, ostrzeżenie w dialogu (blok podglądu czy `Alert`), los `StatTile` i `.holdings-list-row`.

## Summary

Wszystkie pięć zarzutów się potwierdza. Najważniejsza zmiana tła: w drzewie roboczym leży **niezatwierdzony ADR-0045** (`context/foundation/adr/0045-dashboard-accounts-and-cancel-follow-design-system-pages.md`) z edycjami `design-system.md`, `js.md`, `js-dialogs.md` i ADR-0040/0043/0044. Ustala on, że aplikacja wraca do stron design systemu, a `design-system.md:3` (wersja robocza) dodaje regułę: gdzie strona design systemu i ADR mówią co innego, a nowszy ADR tego nie rozstrzyga, przed odejściem od strony trzeba zapisać ADR. Dla Lokat oznacza to domyślnie wzorzec `PageTermDeposits`:

- `Switch` „Pokaż zarchiwizowane” w wierszu tytułu (jak Konta po ADR-0045), nie pod panelem;
- znak banku jako krótki skrót tekstowy (`ht-mark`), nie logo; pola skrótu na liście banków jeszcze nie ma (`resources/js/lib/account-identity.ts:1-69` ma tylko `bankMarkPaths`), a ADR-0045:9 odsyła je do „zmiany wdrażającej”, której jeszcze nie ma w `context/changes/`;
- „Anuluj” `ghost` (ADR-0045:11, `js-dialogs.md:15` wersja robocza), nie `outline`.

Rozstrzygnięcia (rekomendacje dla planu):

1. **„zł” w komórkach:** tak, jak w `PageTermDeposits` i ADR-0044 (jedna kolumna kwot, brak wiersza sumy, sumy w panelu miar). Reguła `js.md:16` wymienia wyjątki od ADR-0037 z nazwy (Operacje, Konta), więc Lokaty trzeba do niej dopisać — wymaga to ADR (następny wolny numer: **0046**, bo 0045 zajmuje plik roboczy).
2. **Siatka:** własna klasa `.term-deposits-grid` na wzór `.accounts-grid` (`resources/css/app.css:688-745`); repo nazywa siatki per widok (`js.md:37`, `design-system.md:18`), a Lokaty mają 5 kolumn zamiast 4.
3. **Ostrzeżenie o ujemnym saldzie w dialogu:** dokumenty są sprzeczne (szczegóły w C4). Rekomendacja: **zostawić `Alert` `warning`**, bo `design-system.md:78` i `js.md:24` wprost wyłączają „pozostałe formularze … ujemne saldo”, a dialog nie pokazuje dziś odczytu „przed → po”, który uruchamia `js-dialogs.md:19`. Alternatywa (blok `FormPreview`) to decyzja użytkownika i wymaga ADR.
4. **`StatTile` i `.holdings-list-row`:** po zmianie nie mają użytkowników (`StatTile` tylko `TermDeposits.vue:11,110`; `.holdings-list-row` tylko `TermDeposits.vue:189`). Rekomendacja: usunąć oba wraz z wpisami w `scripts/check-ui-literals.mjs:19`, `design-system.md:82`, docblockami `tests/Unit/AccountsHardcodedUiTest.php:6-7` i `tests/Unit/DashboardHardcodedUiTest.php:6-7`.

## Charges

Oznaczenia kategorii: (T) brakujące tokeny/style, (K) brakujący współdzielony komponent, (A) przypadkowa architektura.

### C1 (K) Jedna karta `StatTile` zamiast `StatPanel` z dwiema miarami: potwierdzony

- `TermDeposits.vue:110-136`: `StatTile` z „Suma lokat” i slotem `footer` z „W tym zarchiwizowane: …” (pokazywanym, gdy `archived_term_deposits_total_minor_units !== 0`, `:115`). `StatTile.vue:29` ma `text-3xl` zamiast `kpi-lg`.
- Wzorzec: `StatPanel` z „Suma lokat” (`kpi-lg`, podpis „W tym zarchiwizowane: −12,50 zł” z kwotą w `destructive`) i „Łączne środki” (podpis „konta i lokaty”, ton dodatni).
- `StatPanel.vue` przyjmuje `caption` jako tekst; czerwona kwota w podpisie wymaga slotu `footer-<id>` (`StatPanel.vue:58`).
- Druga miara potrzebuje propsa `total_funds_minor_units`: `TotalFunds::forUser` (`app/Support/TotalFunds.php:7-18`: Płynność dysponowalna, czyli wszystkie konta z zarchiwizowanymi, plus wszystkie lokaty z zarchiwizowanymi i ujemnymi). `TermDepositController::index` (`app/Http/Controllers/TermDepositController.php:19-45`) go nie ma; wzór to commit `9e9b7ad` (Konta: kontroler, typ `types/accounts.ts:25`, fixtures, `AccountTest.php:179-207` z kontem i lokatą, by wartości się różniły).
- Podpis „konta i lokaty” nie ma klucza w `term_deposits.*` (`lang/pl/ui.php:482-519`); Konta mają `accounts.total_funds_caption` „z lokatami” (`:480`). Pożyczanie klucza z innej przestrzeni łamie lekcję F1 z `context/archive/2026-11-03-accounts-ui-contract/reviews/impl-review.md` — nowy klucz `term_deposits.total_funds` / `total_funds_caption`.
- Wpływ: miary Lokat mają inną typografię niż reszta aplikacji, a łącznych środków nie widać na tej stronie.

### C2 (K/A) Lista na `flex-wrap` zamiast tabeli z nagłówkami: potwierdzony

- `TermDeposits.vue:185-300`: `ListSurface` z `.holdings-list-row` (`app.css:868-871`, `border-border/60`, `flex-wrap`), bez nagłówków kolumn; data zakończenia jako podpis pod nazwą (`:208-214`); akcje w `w-full` spadają na telefonie do osobnej linii (`:225`).
- Wzorzec: Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje, nagłówek `ht-row--head`, saldo z „zł”, data `ht-num` do prawej, nazwa banku jako tekst kolumny, znak banku przed nazwą.
- Precedens: `.accounts-grid` (`app.css:688-745`): dwie linie na telefonie (`'name balance' / 'type actions'`), kolumny od `md`, `accounts-header-row` z `table-header` i `border-border-soft`, wiersz `min-h-row`. Lokaty potrzebują 5 kolumn; na telefonie np. `'name balance' / 'meta actions'` z bankiem i datą w jednej komórce — do ustalenia w planie.
- Nazwa banku: `bankLabel(t.accounts, bank)` (`lib/account-identity.ts:63-69`), jak w `DashboardHoldings.vue:35-42`. Nagłówki: `term_deposits.name`, `bank`, `end_date`, `current_balance` istnieją (`ui.php:489-495`); „Akcje” nie ma w `term_deposits.*` (jest w innych przestrzeniach, `ui.php:476`) — nowy klucz.
- Oprocentowanie „3,80%” i „4,10%” z wzorca: brak pola w modelu (trzy migracje `database/migrations/*term_deposits*` bez `interest`/`rate`) — wartość podglądu, odpada (do odnotowania w ADR jako odejście od strony).
- Brak daty zakończenia: dziś pomijana; w kolumnie potrzebny jest znak pusty (np. „—”) — do ustalenia w planie.

### C3 (T/K) Rząd tytułu, `Switch`, plakietka, pusty stan: potwierdzony

- `TermDeposits.vue:104` `text-lg font-semibold` zamiast `page-title`; opis „Lokaty terminowe.” (`term_deposits.list_description`, `ui.php:486`) siedzi w podpisie `ListSurface` (`:185`). Wzorzec: tytuł z opisem „Lokaty terminowe. Data zakończenia jest tylko informacyjna.” — zmiana tekstu klucza.
- `:142-152` `Checkbox` zamiast `Switch`; `js.md:29` już wymaga `Switch`. Po ADR-0045 i według wzorca `Switch` stoi w wierszu tytułu obok „Dodaj lokatę”.
- `:203` Badge `secondary` zamiast `neutral` (Konta: `Accounts.vue:217`).
- `:167` drugi przycisk pustego stanu `secondary`; Konta mają `outline` (`Accounts.vue:171`), zgodnie z `design-system.md` („pozostałe są `outline`”).
- Wiersz zarchiwizowany: `:190` już `text-muted-foreground` bez `opacity`, a `balanceClass` (`:67-71`) zostawia ujemne saldo w `destructive` (FR-TD-08) — bez zarzutu, do przeniesienia na nową siatkę.

### C4 (K) Dialog poza ADR-0040/0045: potwierdzony; ostrzeżenie wymaga decyzji

- `TermDepositFormDialog.vue:162-246`: pięć bloków `div.grid gap-2 > Label + Input + InputError` zamiast `FormField` (`js-dialogs.md:15`), więc pola mają 32 px zamiast 34 px.
- Podpowiedź daty zakończenia (`:212-214`) to `<p>` bez `aria-describedby`; `FormField` z `hint` ją wiąże (`FormField.vue:21-28,64-66`; użycia: `CategoryFormDialog.vue:315,336`, `CreateBudgetDialog.vue:162`).
- Saldo otwarcia bez wariantu `money` („zł”, `FormField.vue:44-55`). Ukryte pole ma już poprawny wzorzec (computed i `?? ''`, `:47-49,234-238`; decyzja F1 w `context/archive/2026-11-03-term-deposits-ui-contract/reviews/impl-review.md:33-45`) — zachować, nie kopiować bloku z `AccountFormDialog.vue:243-268` (ref i watch, dług opisany w `follow-ups/review-fixes.md:13`).
- `<Label for="account-bank">` (`:178`) działa tylko przez domyślne `id` `AccountBankSelect` (`AccountBankSelect.vue:28`); po migracji własne id (np. `term-deposit-bank`) przez slot `FormField`, jak `AccountFormDialog.vue:210-223`.
- „Anuluj” `secondary` (`:258`) — cel `ghost` (ADR-0045:11). W kodzie żaden dialog jeszcze nie ma `ghost` (wszystkie inne `outline`, np. `AccountFormDialog.vue:274`, `ConfirmActionDialog.vue:67`); ta zmiana dotyczy tylko `TermDepositFormDialog`, chyba że plan postanowi inaczej.
- Spinner i blokada zamykania są (`:118-130,135,275`).
- **Ostrzeżenie o ujemnym saldzie** (`:248-252`, `Alert` `warning`; jedyny `Alert` w dialogu formularza w repo). Dokumenty mówią co innego:
  - za `Alert`: `design-system.md:78` („w pozostałych formularzach (np. …, ujemne saldo) … zostają przy `<Alert variant="warning">`”), `js.md:24` (wyjątek tylko „in form dialogs of operations”), plan `context/archive/2026-11-05-operation-dialogs-atrament/plan.md:122`, który odroczył ten dialog;
  - za blokiem: ADR-0041:5,9 („replaces `Alert` `warning` in form dialogs”) i `js-dialogs.md:19`, którego glob obejmuje ten plik — ale ta reguła dotyczy „odczytu przed zapisem”, a dialog dziś takiego odczytu nie pokazuje.
  - Gdyby wybrać blok: `FormPreview` (`FormPreview.vue:8-17`) przyjmuje `rows` i `warnings` i renderuje się także z samym ostrzeżeniem; ma na sztywno `aria-label` „Skutek operacji” (`:25`) — potrzebny prop `label`. W edycji wiersz „Saldo bieżące: current → projected” (`:67-82`), przy tworzeniu blok z samym ostrzeżeniem. Bramka ADR-0041 „kwota > 0” nie pasuje, bo saldo otwarcia może być zerowe lub ujemne.
  - Rekomendacja: `Alert` (zgodny z dwoma dokumentami wprost, bez ADR); blok to odejście wymagające ADR-0046 i poprawek `design-system.md:78` i `js.md:24`.
- Kitchen sink dialogów `/dev/ui/dialogs` (`resources/js/pages/dev/DialogsKitchenSink.vue:36-73,501-529`) pokazuje stany dialogu konta przez prop `form` (`AccountFormDialog.vue:40-41,68-70`) i `DialogsKitchenSinkController::validate` (`app/Http/Controllers/Dev/DialogsKitchenSinkController.php:23-44`). `TermDepositFormDialog` nie ma propsa `form` ani wpisu w tym sinku (`js-dialogs.md:15` każe dopisywać tam stany dialogów).
- Wpływ: pola innej wysokości niż w pozostałych dialogach, podpowiedź niesłyszalna dla czytnika ekranu, brak „zł” przy kwocie.

### C5 (A) Osierocone elementy design systemu: potwierdzony

- `StatTile`: `grep -rl StatTile resources/js` zwraca tylko `TermDeposits.vue`. `.holdings-list-row`: jedyny użytkownik `TermDeposits.vue:189`; komentarz w `app.css:868` mówi „Wiersz listy Kont i Lokat”, choć Konta przeszły na `.accounts-grid`.
- Po zmianie oba są martwe, a dokumenty nadal je polecają lub opisują: `design-system.md:82` („`StatTile` … tylko w Lokatach”), `scripts/check-ui-literals.mjs:19`, docblocki `tests/Unit/AccountsHardcodedUiTest.php:6-7` i `tests/Unit/DashboardHardcodedUiTest.php:6-7` (uzasadniają pominięcie `StatTile` jego użytkownikami).
- Wpływ: następny agent przeczyta „Gotowe elementy” i użyje martwego kafelka w nowym widoku, odtwarzając dryf.
- `AccountIdentityBlock` zostaje: używa go `DashboardHoldings.vue:109,181`. Lokaty przechodzą na znak (`AccountMarkCompact` lub jego następcę z ADR-0045).

### Odroczone (poza tą zmianą)

- „Anuluj” `ghost` w pozostałych dialogach, Switch w tytule Kont i skróty banków w Kontach: zmiana wdrażająca ADR-0045 (jeszcze nieotwarta).
- Dług F1 w `AccountFormDialog` (ref i watch przy ukrytym polu): poza widokiem.
- Notka panelu wzorca „Ujemne saldo zarchiwizowanej lokaty zostaje czerwone, żeby nie zniknęło z oczu.”: objaśnienie dla czytelnika design systemu, nie tekst UI. Rekomendacja: nie przenosić; zapisać w ADR-0046 jako odejście od strony.

## Detailed Findings

### Stan dokumentów (drzewo robocze)

- `git status` przy badaniu: zmodyfikowane `.ai/rules/index.md`, `js-dialogs.md`, `js.md`, ADR-0040/0043/0044, `design-system.md`, `glossary.md`; nowy `adr/0045-…md`. Ktoś pracuje nad ADR-0045 równolegle. Plan powinien zacząć od sprawdzenia, czy ADR-0045 jest zatwierdzony (commit), i numerować własny ADR od 0046.
- ADR-0045:9 ustala skrót tekstowy banku jako „pole listy banków ustawiane w zmianie wdrażającej”. Lokaty potrzebują tego samego pola. Albo ta zmiana je dodaje (i Konta je potem przejmą), albo czeka na zmianę Kont, albo przejściowo zostaje logo `AccountMarkCompact`. Decyzja należy do użytkownika.

### Dane, props i testy

- Props dziś: `termDeposits`, `term_deposits_total_minor_units` (wszystkie lokaty, `TermDepositController.php:40`), `archived_term_deposits_total_minor_units` (`:41`), `archived_term_deposits_count` (`:42`), `show_archived` (`:43`); typ `resources/js/types/term-deposits.ts:24-30`.
- Testy `assertInertia` strony: `tests/Feature/TermDepositTest.php:104-140` (bez kont w setupie; dla nowego propsa dodać konto, by Łączne środki różniły się od sumy lokat), `:144-154`, `:350-374`; `tests/Feature/ArchiveTermDepositTest.php:95-220` (m.in. `:127-143` suma z zarchiwizowanymi, `:147-166` sumy niezależne od filtra).
- Fixtures sinka: `resources/js/pages/dev/term-deposits-fixtures.ts:75-173`, 7 scenariuszy (`default`, `with-archived`, `with-archived-negative`, `zero-total`, `negative-total`, `empty-new-user`, `all-archived`); każdy dostanie `total_funds_minor_units` (wymusi to typ). Sink: `resources/js/pages/dev/TermDepositsKitchenSink.vue:14-43` (macierz stanów), `:93-102` (dialog tworzenia).
- Strażnicy: `scripts/check-ui-literals.mjs:38-40` już skanuje trzy pliki Lokat, ale nie łapie stylów sprzed Atramentu. `tests/Unit/AccountsHardcodedUiTest.php:10-31` to wzór dla nowego `TermDepositsHardcodedUiTest` (wzorce `text-lg…3xl`, `border-border/N`, `opacity-N` itd.). `resources/js/ui-contract.test.ts:114-139` (martwy Checkbox) obejmuje oba pliki Lokat.
- E2E: `tests/e2e/` nie ma testu Lokat.

### Macierz 7 stanów (stan obecny → potrzeba)

| Stan | Dziś | Do pokazania po zmianie |
| --- | --- | --- |
| default | scenariusze sinka `:14-17` | tabela z nagłówkami, panel z dwiema miarami |
| hover | zrzuty z aplikacji | hover wiersza siatki (`hover:bg-secondary`) |
| focus-visible | zrzuty po Tab | `Switch` w tytule, `IconAction`, pola `FormField` (fokus 2 px) |
| disabled | zrzuty z opóźnionym POST | `Switch` w trakcie wizyty, „Zapisz” przy złej kwocie, „Anuluj” `ghost` wyłączony |
| error | sekcja `error-form` sinka | błędy serwera w `/dev/ui/dialogs` (wymaga propsa `form`) |
| empty | `empty-new-user`, `all-archived` | bez zmian, przycisk `outline` |
| loading | Switch zablokowany; skeleton N/A (brak deferred props) | bez zmian |

## Code References

- `resources/js/pages/TermDeposits.vue:104` — tytuł `text-lg font-semibold`
- `resources/js/pages/TermDeposits.vue:110-136` — `StatTile` z „W tym zarchiwizowane”
- `resources/js/pages/TermDeposits.vue:142-152` — `Checkbox` „Pokaż zarchiwizowane”
- `resources/js/pages/TermDeposits.vue:185-300` — `ListSurface` z `.holdings-list-row`
- `resources/js/components/TermDepositFormDialog.vue:162-246` — pola bez `FormField`
- `resources/js/components/TermDepositFormDialog.vue:248-252` — `Alert` `warning` ujemnego salda
- `resources/js/components/TermDepositFormDialog.vue:258` — „Anuluj” `secondary`
- `resources/js/components/StatPanel.vue:58` — slot `footer-<id>`
- `resources/js/components/FormField.vue:6-13` — propsy `hint`, `money`, `group`
- `resources/js/components/FormPreview.vue:8-25` — API bloku podglądu i sztywny `aria-label`
- `resources/css/app.css:688-745` — `.accounts-grid` (wzór siatki)
- `resources/css/app.css:868-871` — `.holdings-list-row`
- `app/Support/TotalFunds.php:7-18` — Łączne środki
- `app/Http/Controllers/TermDepositController.php:19-45` — props strony
- `resources/js/lib/account-identity.ts:45-69` — logo banków i `bankLabel`, bez skrótów
- `context/foundation/adr/0045-dashboard-accounts-and-cancel-follow-design-system-pages.md` — niezatwierdzony ADR

## Architecture Insights

- Kontrakt UI repo: tokeny i nazwane style w `app.css`, komponenty domenowe w `components/`, siatki per widok, strażnik literałów plus test `*HardcodedUiTest` dla oczyszczonych widoków, kitchen sink per widok i wspólny sink dialogów.
- Po ADR-0045 strona design systemu jest domyślnym wzorcem, a odejście od niej wymaga ADR (`design-system.md:3`, wersja robocza). To odwraca kierunek z ADR-0044 (który odrzucał elementy makiety).
- Lokaty i Konta dzielą: `AccountBankSelect`, znak banku (`AccountMarkCompact`), wzorzec Switch/StatPanel/siatki. Lokaty nie mają kolumny Typ, za to Bank i Data zakończenia.

## Historical Context (from prior changes)

- `context/archive/2026-11-06-accounts-atrament-ui/plan.md:66` — Lokaty odroczone do własnego `/10x-ui`, „do tego czasu zostają przy `.holdings-list-row`, `StatTile` i `Checkbox`” (wspierane: stan kodu się zgadza).
- `context/archive/2026-11-06-accounts-atrament-ui/research.md:39` — rekomendacja „zostać przy ADR-0037” dla „zł” została odrzucona: ADR-0044 wybrał „zł” w komórkach (sprzeczne z końcowym stanem; obowiązuje ADR-0044).
- `context/archive/2026-11-03-term-deposits-ui-contract/research.md:29` — „Suma lokat” z zarchiwizowanymi jest zamierzona (FR-REP-10; wspierane, `ArchiveTermDepositTest.php:127-143`).
- `context/archive/2026-11-03-term-deposits-ui-contract/reviews/impl-review.md:33-45,62-65` — F1 (computed dla ukrytego pola) i F3 (blokada zamykania) wdrożone w `TermDepositFormDialog` (wspierane).
- `context/archive/2026-11-05-operation-dialogs-atrament/change.md:19`, `plan.md:61,122` — dialog lokaty odroczony; „pozostałe formularze zostają przy `Alert` `warning`” (wspierane w `design-system.md:78`).

## Related Research

- `context/archive/2026-11-06-accounts-atrament-ui/research.md`
- `context/archive/2026-11-03-term-deposits-ui-contract/research.md`
- `context/archive/2026-11-05-operation-dialogs-atrament/research.md`

## Open Questions

Decyzje dla użytkownika przed planem:

1. **Skrót tekstowy banku (ADR-0045):** ta zmiana dodaje pole skrótu do listy banków i używa go w Lokatach, czy Lokaty przejściowo zostają przy logo `AccountMarkCompact` do zmiany wdrażającej ADR-0045 w Kontach? Rekomendacja: dodać tu, bo strona wzorca ma skróty, a pole jest wspólne (Konta przejmą je w swojej zmianie). Wartość dla banku „Inny” nie jest opisana ani we wzorcu, ani w ADR-0045.
2. **Ostrzeżenie w dialogu:** `Alert` `warning` (rekomendacja, zgodny z `design-system.md:78` i `js.md:24`) czy blok `FormPreview` z wierszem salda w edycji (wymaga ADR i propsa `label` w `FormPreview`)?
3. **Numer ADR:** 0046, o ile ADR-0045 zostanie zatwierdzony pod tym numerem. ADR-0046 obejmie: „zł” w komórkach Lokat (dopisanie do `js.md:16`), kolumny, odejścia od strony (bez oprocentowania, bez notki panelu) i usunięcie `StatTile` / `.holdings-list-row`.
4. **Telefon:** układ dwóch linii siatki (`'name balance' / 'meta actions'` z bankiem i datą w jednej komórce czy osobne komórki) — do ustalenia w planie na zrzucie.

## Aktualizacja 2026-11-06 (po ADR-0045 i ADR-0047, `55b0585`)

Po researchu zatwierdzono ADR-0045 (`0d071b4`) i zamknięto zmianę `accounts-design-system-page` (ADR-0047, archiwum `context/archive/2026-11-06-accounts-design-system-page/`). Polecenie zmiany: „wzorzec: karta PageTermDeposits; uwzględnij ADR-0045 i kafelek banku z Kont. Nowy ADR ma numer 0051”. Stan pytań:

1. **Znak banku — rozstrzygnięte (ADR-0047).** Skróty są już w `resources/js/lib/account-identity.ts:45-67` (`accountShortMark`), a `AccountMarkCompact` (`size="md"` 28 px) pokazuje je z `mark-label` na `muted`. Lokaty biorą ten kafelek bez nowego pola: `TermDepositRow.bank` jest wymagany (`types/term-deposits.ts:6`), więc `<AccountMarkCompact type="bank" :bank size="md" />`. Plan Kont odesłał to wprost tutaj (`…/accounts-design-system-page/plan.md:48`: „z tymi samymi wartościami z ADR-0047”). `AccountIdentityBlock` zostaje dla Dashboardu (`DashboardHoldings`). Wybór banku w dialogu lokaty ma skrót od `6de2267`.
2. **`mark_url` w payloadzie Lokat staje się martwy.** Czyta go tylko `TermDeposits.vue:197`; źródło `TermDepositController.php:148,166`, typ `types/term-deposits.ts:15`, asercje `TermDepositTest.php:130,135`, fixtures `term-deposits-fixtures.ts:34`. Rekomendacja: usunąć w fazie propsów razem z dodaniem `total_funds_minor_units` (jeden commit: PHP, typ, `assertInertia`). Dashboard ma własny payload i zostaje.
3. **Switch w wierszu tytułu z warunkiem ADR-0047.** Konta: `Accounts.vue:127-155` (opis pod `page-title`, Switch tylko gdy `archived_accounts_count > 0 || show_archived`, przed „Dodaj konto”). Lokaty mają `archived_term_deposits_count` i `show_archived`, więc ten sam warunek; ADR-0047 dotyczy Kont, więc ADR-0051 rozciąga go na Lokaty jako odejście od karty.
4. **„Anuluj” `ghost`:** nadal żaden dialog formularza nie ma `ghost` (`AccountFormDialog` zostaje `outline`). Ta zmiana przestawia tylko `TermDepositFormDialog` (`:258`); pozostałe dialogi zostają zmianie wdrażającej ADR-0045.
5. **Ostrzeżenie w dialogu:** bez zmian w dokumentach — `design-system.md:79` i `js.md` („Non-blocking warnings…”) nadal wymieniają „ujemne saldo” przy `Alert` `warning`. Przyjęte: `Alert` zostaje, bez ADR na tę część.
6. **Numer ADR: 0051** (polecenie zmiany; 0046 i 0048–0050 rezerwują równoległe zmiany). ADR-0051 obejmuje: Lokaty według `PageTermDeposits` (tabela Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje, „zł” w komórkach jak ADR-0044, `StatPanel` z „Suma lokat” i „Łączne środki”), Switch w tytule z warunkiem ADR-0047, kafelek `AccountMarkCompact` zamiast `AccountIdentityBlock` w Lokatach, odejścia od karty (bez oprocentowania, bez notki panelu), usunięcie `StatTile` i `.holdings-list-row`. Dopisuje Lokaty do wyjątków ADR-0037 w `js.md` („Amounts go through formatMoney…”) i `design-system.md:64`.
7. **Dokumenty polecające martwe elementy** (dochodzą do C5): `design-system.md:83` (`StatTile` „tylko w Lokatach”), `js.md` („Before a new component check StatTile…”, lista reguł w „Reuse surface building blocks”), `js.md:21` („`AccountIdentityBlock` still shows the logo…” — doprecyzować, że Lokaty mają już kafelek).
