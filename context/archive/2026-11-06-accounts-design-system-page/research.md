---
date: 2026-11-06T15:38:45+01:00
researcher: Claude (Sonnet 5.5)
git_commit: 0d071b4
branch: development
repository: household-treasurer
topic: "Audyt UI widoku Konta względem ADR-0045: Switch w wierszu tytułu i skrót banku w AccountMarkCompact"
tags: [research, ui, atrament, accounts, account-mark-compact, switch, adr-0045, adr-0047]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude (Sonnet 5.5)
---

# Research: Konta według ADR-0045 (Switch w wierszu tytułu, skrót banku w AccountMarkCompact)

**Date**: 2026-11-06T15:38:45+01:00
**Researcher**: Claude (Sonnet 5.5)
**Git Commit**: 0d071b4 (drzewo robocze bez zmian w kodzie; nieśledzone są tylko foldery `context/changes/`)
**Branch**: development
**Repository**: household-treasurer

## Research Question

Czy pięć zarzutów z `change.md` się potwierdza i co rozstrzyga plan: (1) przeniesienie `Switch` „Pokaż zarchiwizowane” do wiersza tytułu, (2) skrót tekstowy banku zamiast logo w `AccountMarkCompact` (skąd się bierze, jakie wartości), (3) zgodność kafelka z `ht-mark`, (4) zakres zmiany `AccountMarkCompact` dla jego innych konsumentów, (5) dostępność. `AccountFormDialog` („Anuluj”) jest poza zakresem.

## Summary

Wszystkie pięć zarzutów się potwierdza. Zmiana nie wymaga nowego propsa Inertii: `AccountIdentityFields` ma już `bank` (`account-identity.ts:15-20`), a każde z sześciu bezpośrednich użyć `AccountMarkCompact` przekazuje ten typ, więc skrót może wynikać z `bank` po stronie klienta.

- **Switch.** Dziś stoi w osobnym rzędzie `Accounts.vue:142-157`, a wiersz tytułu (`:128-138`) ma tylko „Dodaj konto”. ADR-0045 i `PageAccounts` stawiają go w akcjach tytułu. `design-system.md:18` już opisuje nowy układ, więc kod jest za dokumentacją.
- **Skrót banku.** `AccountMarkCompact.vue:45-62` renderuje `<img>` z `mark_url` albo ikonę. Lista banków istnieje dwa razy: `Bank::markPath()` (`Bank.php:18-32`) i `bankMarkPaths` (`account-identity.ts:45-56`). `AccountBankSelect` bierze logo z wersji TS, bo nie dostaje żadnego payloadu (`:60`, `:73`); karta `PageAccounts` ma skróty tylko dla czterech banków, więc resztę ustala ADR-0047.
- **Zakres.** `AccountMarkCompact` ma sześć bezpośrednich użyć w czterech plikach (a przez `CompactAccountIdentity` jeszcze cztery w wierszach Operacji), a Dashboard i Lokaty używają osobnego `AccountIdentityBlock` z logo. Skoro skrót ma być w `AccountMarkCompact` (polecenie użytkownika), zmienia się on w pięciu miejscach: tabela Kont, wybór banku, wybór konta, wiersze przelewów własnych i wiersze ruchów kapitału. Strony design systemu pokazują skrót także na Dashboardzie (Konta i Lokaty), w Lokatach i w polu konta dialogu wydatku, więc kierunek jest spójny.
- **Rozmiar.** `ht-mark` ma 28 px; wariant `sm` (20 px) trafia do list wyboru i wierszy Operacji. Trzy znaki Mono 9 px mieszczą się w 20 px, cztery nie (obliczenie poniżej).

Zakres kodu do zmiany jest mały: `Accounts.vue`, `AccountMarkCompact.vue`, `account-identity.ts` (+ `.test.ts`), `app.css` (jeden `@utility`), kitchen sink i fiksury, `design-system.md`, ADR-0047. `mark_url` zostaje w payloadach, bo czyta go `AccountIdentityBlock`.

## Charges

Kategorie: **T** brakujące tokeny, **K** brakujący współdzielony komponent, **A** przypadkowa architektura.

### C1 (A) `Switch` w osobnym rzędzie pod panelem miar: potwierdzony

- **Gdzie:** `Accounts.vue:142-157` (rząd z `Switch` i `Label`, warunek `archived_accounts_count > 0 || show_archived`); wiersz tytułu `:128-138` (`flex flex-wrap items-center justify-between gap-3`, akcja: „Dodaj konto”).
- **Wpływ:** filtr listy jest oddzielony od akcji strony, a dodatkowy rząd odsuwa tabelę o jedną linię. Dashboard (`ADR-0045`) i karta `PageAccounts` mają ten sam przełącznik w wierszu tytułu, więc Konta wyglądają inaczej niż wzorzec.
- **Dowody:**
  - ADR-0045: „The Switch 'Pokaż zarchiwizowane' stands in the title row, next to 'Dodaj konto'.” Odwraca to ADR-0044, który odrzucił ten układ, bo „the title row holds one `primary` action only” (ADR-0044, akapit o panelu miar).
  - Karta: `.ht-title__actions` = `display:flex; gap` z przełącznikiem przed przyciskiem (`PageAccounts/preview.html`, wiersz tytułu).
  - Komponent `Switch` (`components/ui/switch/Switch.vue`) już jest używany w tym widoku; zmienia się tylko miejsce markupu, `v-model` i logika `watch` (`:35-46`) zostają.
- **Decyzje do planu:**
  - Warunek widoczności: karta pokazuje przełącznik zawsze, kod tylko gdy są zarchiwizowane konta lub filtr jest włączony. Kitchen sink opisuje to jako zamierzony stan (`AccountsKitchenSink.vue:35`, „empty-new-user (brak kont, bez przełącznika)”). Propozycja: zostawić warunek, zapisać w ADR-0047.
  - Zawijanie przy 390 px: Switch i Label muszą stanowić jedną grupę (`flex items-center gap-3`), żeby przy zawinięciu akcji przycisk nie oddzielił się od przełącznika. Weryfikacja w kitchen sinku.

### C2 (K) Skrót tekstowy banku zamiast logo: potwierdzony

- **Gdzie:** `AccountMarkCompact.vue:29-37` (trzy gałęzie: logo, `Coins` dla gotówki, `Building2` dla banku bez logo), `:45-62` (markup). Konsument w Kontach: `Accounts.vue:207-213` (`size="md"`, `:mark_url="account.mark_url"`).
- **Wpływ:** ten sam bank wygląda na Kontach inaczej niż na karcie (logo 28 px zamiast `mB`/`PKO`/`ING`).
- **Źródło skrótu — dowody:**
  - `AccountIdentityFields` (`account-identity.ts:15-20`) zawiera `bank: AccountBank | null`, więc każdy konsument `AccountMarkCompact` już ma wszystko, by wyliczyć skrót. Nowy prop Inertii nie jest potrzebny.
  - TS jest już autorytetem dla dialogu: `AccountBankSelect.vue:60,73` bierze logo z `bankMarkPath()`, bo w dialogu nie ma payloadu z serwera. PHP (`Bank::markPath()` przez `AccountIdentity::markUrl`, `AccountIdentity.php:10-15`) zasila tylko listy.
  - ADR-0045: „The short mark is a field of the bank list and is set in the implementing change.”
  - Zalecenie: `Record<AccountBank, string | null>` w `account-identity.ts` obok `bankOptions`; pełny `Record` powoduje błąd `types:check` przy nowym banku. Nie trzeba zmieniać PHP ani `assertInertia`.
- **Wartości — stan źródeł:** karta daje tylko `mB`, `PKO`, `ING`, `A` (Alior) na `PageAccounts`, `PEK` (Pekao) na `PageDashboard`/`PageTermDeposits`, `zł` dla gotówki. Nie ma BNP Paribas, Revolut, Santander, Lokaty Facto ani „Inny”. Karta jest niespójna sama ze sobą: Alior ma 1 znak (`A`), pozostałe 2-3.
  - Propozycja do ADR-0047 (do zatwierdzenia, nie fakt z repo): `mB`, `PKO`, `ING`, `PEK`, `BNP`, `REV`, `SAN`, `ALI`, `LF` (Lokaty Facto); „Inny” bez skrótu (zostaje `Building2`, bo nie ma instytucji do skrócenia). Stała reguła: najwyżej 3 znaki.
- **Wpływ na pozostałą architekturę:** `bankMarkPath` i `bankMarkPaths` (`account-identity.ts:45-60`) po zmianie nie mają użytkownika w `AccountBankSelect` (jedyny import: `AccountBankSelect.vue:15`), więc znikają razem z nim. `mark_url` zostaje w payloadach PHP, bo `AccountIdentityBlock.vue:36-48` (obwódka `border-border/60` w `:38`) (Dashboard, Lokaty) nadal pokazuje logo; pliki `public/marks/*` zostają z tego samego powodu.

### C3 (T) Kafelek poza przepisem `ht-mark`: potwierdzony

- **Gdzie:** `AccountMarkCompact.vue:17-27` (rozmiary `size-5`/`size-7`, `rounded-md`/`rounded-lg`) i `:42` (`border-border/60 bg-muted … border`).
- **Wpływ:** kafelek ma obwódkę i większy promień niż kafelki innych tożsamości (`CategoryChip`, `OperationTypeTile`), więc wiersze Kont nie przypominają wiersza karty.
- **Wzorzec (`bundle.css`, `ht-mark`):** 28×28 px, `radius-sm`, tło `muted`, bez obwódki, JetBrains Mono 9 px, waga 600, środek. `CategoryChip/README.md` mówi to samo: „skrót banku w JetBrains Mono albo 'zł' dla gotówki”.
- **Jak to wyrazić w repo:** `app.css` używa wartości px wewnątrz `@utility` (`meta` = `text-[11px]/[14px]`, `label-caps`, `section-title`); `.ai/rules/css.md` każe dodać nowy styl tam i używać go po nazwie. 9 px nie wolno wpisać w szablonie (`AccountsHardcodedUiTest.php:23` łapie `-[…px]`). Potrzebny `@utility` w `app.css` (nazwa do ustalenia w planie) oraz wpis w tabeli stylów `design-system.md`.
- **Wymiary w repo:** `--radius-sm` = `calc(var(--radius) - 4px)` (`app.css:18`), `--font-mono` = JetBrains Mono (`app.css:14`).

### C4 (A) Zakres współdzielonego komponentu: potwierdzony, wynika z polecenia

- **Gdzie:** `AccountMarkCompact` ma sześć bezpośrednich użyć w czterech plikach (sprawdzone `grep` po `resources/js`, pliki `.vue`):
  - `Accounts.vue:207` (`md`, tabela; jedyne użycie z `size="md"`);
  - `AccountBankSelect.vue:57,70` (`sm`, lista i wartość wyboru banku w dialogu konta);
  - `AccountSelect.vue:64,76` (`sm`, wybór konta w dialogach operacji i importu);
  - `CompactAccountIdentity.vue:19` (`sm` domyślnie), używany w `OwnTransferRow.vue:37,46` i `CapitalMovementRow.vue:34,52`.
- **Poza tym komponentem:** `AccountIdentityBlock` (`TermDeposits.vue:192`, `DashboardHoldings.vue:109,181`) renderuje własny kafelek 24/40 px z logo i `border-border/60` (`AccountIdentityBlock.vue:36-48`). Nie jest w zakresie tej zmiany; strony Dashboard i Lokaty karty pokazują tam skrót (`PEK`, `ING`, `mB`…), więc trzeba je później wyrównać.
- **Wpływ:** po zmianie `AccountMarkCompact` ten sam bank ma skrót w tabeli Kont, w wyborze konta i w wierszach Operacji, ale logo na Dashboardzie i w Lokatach, dopóki nie zrobi się `AccountIdentityBlock`. To przejściowa niespójność, nie błąd.
- **Dowody z kierunku DS:** skrót `ht-mark` jest na `PageDashboard` (panele Konta i Lokaty), `PageTermDeposits` i w polu konta `PageOperationExpenseDialog` (`mB`); `PageOperations` ma w podglądzie brak znaku banku, więc co do wierszy Operacji karta nic nie mówi. `CategoryChip/README.md` w DS mówi „gdy jest logo banku, logo zamiast skrótu”, co przeczy ADR-0045; to treść artefaktu poza repo.
- **Do rozstrzygnięcia w ADR-0047:** czy `AccountIdentityBlock` ma dostać skrót przy `/10x-ui` Dashboardu/Lokat (sąsiednia zmiana `term-deposits-atrament-ui` ma status `preparing` i nie decyduje o znaku banku w `change.md`), oraz los `mark_url` i `public/marks/*` po wyrównaniu obu komponentów.
- **Rozmiar `sm`:** wariant ma 20 px (`size-5`). Przy Mono 9 px przesuw znaku to 0,6 em = 5,4 px, więc 3 znaki to 16,2 px (mieszczą się), 4 znaki 21,6 px (nie). To obliczenie z metryki fontu, nie pomiar; weryfikacja wizualna w kitchen sinku. Rozmiar `sm` w `ht-mark` nie istnieje w DS.

### C5 (T/A) Dostępność: `alt` dubluje nazwę: potwierdzony

- **Gdzie:** `AccountMarkCompact.vue:45-50` (`<img :alt="name">`); ikony mają `aria-hidden="true"` (`:54-55`, `:60-61`).
- **Wpływ:** czytnik ekranu czyta nazwę konta dwa razy, bo obok kafelka stoi ta sama nazwa: `Accounts.vue:216` (`account.name`), `AccountBankSelect.vue:61-63` (`bankName` w obu), `AccountSelect.vue:64-65`, `CompactAccountIdentity.vue:19-26`.
- **Poprawka:** skrót tekstowy z `aria-hidden="true"` we wszystkich użyciach (sprawdzone na liście konsumentów wyżej: każdy ma obok widoczną nazwę). Prop `name` w `AccountMarkCompact` traci wtedy zastosowanie; usunięcie go dotyka sześciu bezpośrednich użyć i dwóch wyższych komponentów, więc decyzja w planie.

### Bez zarzutu

- Wejście bez logowania: trasa Kont w grupie `middleware(['auth', 'verified'])` (`routes/web.php:24,64`); test gościa jest dla kitchen sinku (`AccountsKitchenSinkTest.php:15-19`).
- Dwa stany puste (`Accounts.vue:159-187`) używają `IndexEmptyState`.
- Przycisk „Anuluj” w `AccountFormDialog` jest poza zakresem (polecenie użytkownika).

### Odroczone

- README `PageAccounts` w design systemie ma sekcje „Stan” i „Różnice od dzisiejszej aplikacji” z czasu przed ADR-0044 (m.in. „Anuluj” `outline`); `CategoryChip/README.md` sprzeczne z ADR-0045 (wyżej). To artefakt claude.ai, nie kod: poza tą zmianą, do poprawy osobno.
- Usunięcie `mark_url` z payloadów PHP (siedem plików źródłowych: `AccountIdentity.php`, `AccountController.php`, `DashboardController.php`, `DashboardNotices.php`, `OperationsFilterOptions.php`, `TermDepositController.php`, `Bank.php`) i pięciu plików testowych z asercjami `mark_url` (`AccountTest`, `AccountPickerTest`, `OperationsIdentityTest`, `TermDepositTest`, `DashboardTest`): dopiero po wyrównaniu `AccountIdentityBlock`.

## Detailed Findings

### Wiersz tytułu i Switch

- Aktualny wiersz tytułu `Accounts.vue:128-138`: lewa strona `h1.page-title` + opis `text-muted-foreground text-sm`, prawa „Dodaj konto”. Switch jest poniżej panelu miar (`:142-157`), z `id="show-archived-accounts"`, `v-model="showArchived"`, `:disabled="isTogglingArchived"`.
- `showArchived` i `toggleShowArchived` (`:32-46`, `:105-121`) wywołują `router.get` z `preserveState`/`preserveScroll`; przeniesienie markupu tych funkcji nie dotyka.
- E2E `tests/e2e/account-edit-keeps-bank.spec.ts` używa `.accounts-row`, przycisków po nazwie i `toContainText('mBank')` (podpis z nazwą banku, `Accounts.vue:221-226`), nie przełącznika ani kafelka; zmiana nie powinna go łamać. To odczyt kodu testu, nie jego uruchomienie.

### Skrót banku

- `bankLabel(accountTranslations, bank)` czyta `accounts.bank_*` z `lang/pl/ui.php:448-457` (Alior, BNP Paribas, ING, Lokaty Facto, mBank, Pekao, PKO BP, Revolut, Santander, Inny). Skrót jest drugim polem tej samej listy; propozycja trzymania go w TS (obok `bankOptions`) zamiast w `lang`, bo nie jest tłumaczeniem.
- `Bank` w PHP (`Bank.php:5-16`) ma dziesięć wartości, identycznych z `AccountBank` w TS (`account-identity.ts:1-11`).
- Test jednostkowy do rozszerzenia: `account-identity.test.ts` ma dziś tylko `bankLabel`. Naturalne asercje: każdy bank z `bankOptions` ma skrót albo jawnie `null`; skrót ma najwyżej 3 znaki.

### Kafelek i CSS

- `ht-mark` z `bundle.css`: `width/height 28px; border-radius var(--radius-sm); background var(--muted); font-family var(--font-mono); font-size 9px; font-weight 600`.
- W kodzie jest `AccountsHardcodedUiTest.php:7-15` z listą plików, która celowo pomija `AccountMarkCompact.vue` („consumers outside Accounts”), a `scripts/check-ui-literals.mjs:35` go obejmuje (literały kolorów). Po usunięciu `border-border/60` można rozważyć dodanie komponentu do listy testu: komponent dalej ma konsumentów poza Kontami, ale nie ma już przeszkody ze strony Atramentu.

### Fiksury i kitchen sink

- Fiksury `accounts-fixtures.ts` mają `mark_url` w sześciu kontach i pole `bank` w każdym koncie bankowym; po zmianie wystarczy `bank`, więc dane fiksur działają bez zmian. Pokrywają sześć z dziesięciu banków (`mbank`, `other`, `ing`, `pekao`, `revolut`, `bnp_paribas`); brakuje `alior`, `lokaty_facto`, `pko_bp` i `santander`, a sink powinien pokazać skrót każdego banku. Macierz stanów w sinku (`AccountsKitchenSink.vue:11-39`) wymaga dopisania „Switch w wierszu tytułu” i listy znaków banków.

## Code References

- `resources/js/pages/Accounts.vue:128-138` - wiersz tytułu (do rozszerzenia o Switch)
- `resources/js/pages/Accounts.vue:142-157` - Switch w osobnym rzędzie (do usunięcia)
- `resources/js/pages/Accounts.vue:207-213` - `AccountMarkCompact size="md"` w tabeli
- `resources/js/components/AccountMarkCompact.vue:17-27,29-37,42,45-62` - rozmiary, gałęzie, obwódka, logo
- `resources/js/lib/account-identity.ts:15-20,32-43,45-60` - typ tożsamości, `bankOptions`, ścieżki logo
- `resources/js/components/AccountBankSelect.vue:15,57-76` - jedyny użytkownik `bankMarkPath`
- `resources/js/components/AccountSelect.vue:64,76`, `CompactAccountIdentity.vue:19`, `OwnTransferRow.vue:37,46`, `CapitalMovementRow.vue:34,52` - pozostali konsumenci
- `resources/js/components/AccountIdentityBlock.vue:36-48` - kafelek z logo dla Dashboardu i Lokat (poza zakresem)
- `app/Enums/Bank.php:18-32`, `app/Support/AccountIdentity.php:10-15,26,40` - PHP strona `mark_url`
- `resources/css/app.css:14,18,246-275,692-745` - fonty, `--radius-sm`, `@utility`, `.accounts-grid`
- `tests/Unit/AccountsHardcodedUiTest.php:7-15`, `scripts/check-ui-literals.mjs:32-37` - straże widoku
- `context/foundation/design-system.md:18,82,99` - opis docelowego układu Kont i sinka

## Architecture Insights

- Dokumentacja wyprzedza kod: `design-system.md:18` mówi już o `AccountMarkCompact` ze skrótem i `Switch` w wierszu tytułu (ADR-0045), a kod jeszcze nie. Zgodnie z regułą z `AGENTS.md` („Docs describe more than the code”) zmiana ma wyrównać kod do niej, nie odwrotnie.
- Tożsamość banku ma trzy reprezentacje: `Bank` (PHP), `AccountBank` (TS) i `lang bank_*`. Skrót jest czwartą cechą tej listy; trzymanie go w TS z pełnym `Record` daje pilnowanie przez `types:check`.
- Dwa komponenty kafelka (`AccountMarkCompact`, `AccountIdentityBlock`) mają to samo rozwidlenie logo/ikona/ikona. Po tej zmianie rozjadą się (skrót vs logo); wyrównanie `AccountIdentityBlock` zdecyduje, czy zostaje jeden komponent.

## Historical Context (from prior changes)

- `context/foundation/adr/0044-accounts-table-follows-atrament.md` - tabela Kont, `StatPanel`, logo 28 px; część o `Switch` w osobnym rzędzie i o logo zastąpiona przez ADR-0045 (ostatni akapit ADR-0044).
- `context/foundation/adr/0045-dashboard-accounts-and-cancel-follow-design-system-pages.md` - akapit „Accounts”: `Switch` w wierszu tytułu, skrót `mB`/`PKO`/`ING`, „zł” dla gotówki, „short mark is a field of the bank list and is set in the implementing change”.
- `context/foundation/adr/0004-bank-on-account-not-import-format.md` - Bank jest atrybutem konta; nie jest formatem importu (skrót nie zmienia tej reguły).
- `context/archive/2026-11-06-accounts-atrament-ui/` (`research.md`, `plan.md`, `follow-ups/review-fixes.md`) - poprzednia zmiana Kont; F6 w follow-ups (wspólny helper dla straży „hardcoded UI”) jest poza tą zmianą.
- `context/changes/term-deposits-atrament-ui/change.md` - sąsiednia zmiana (status `preparing`); ten sam wzorzec `PageTermDeposits` ma skróty `PEK`/`ING`, więc wartości ustalone w ADR-0047 dotyczą też jej.
- Numer ADR: w `context/foundation/adr` na `development` ostatni to `0045`; `0046` nie występuje ani na żadnej lokalnej gałęzi, ani w worktree `import-start-atrament-ui` i `settings-atrament-ui` (sprawdzone `git ls-tree` i `ls`). Użytkownik wskazał `0047`; powód (zarezerwowany numer w innej pracy) nie wynika z repo.

## Related Research

- `context/archive/2026-11-06-accounts-atrament-ui/research.md` - audyt tego samego widoku sprzed ADR-0045.
- `context/changes/settings-atrament-ui/research.md` (worktree `settings-atrament-ui`, nieśledzony) - równoległa zmiana UI; nie czytane pod kątem treści.

## Open Questions

1. **Skróty dla BNP Paribas, Pekao, Revolut, Santander, Alior, Lokaty Facto i „Inny”**: karta niespójna (`A` vs `PEK`); propozycja w C2, potrzebne zatwierdzenie wartości (ADR-0047). „Inny” bez skrótu (ikona `Building2`) czy ze skrótem?
2. **Rozmiar `sm` (20 px)**: czy skrót w wyborach i wierszach Operacji ma 3 znaki w 20 px, czy `sm` rośnie do 28 px. Rozstrzyga ocena wizualna w kitchen sinku.
3. **Warunek widoczności Switcha**: zostaje (`archived_accounts_count > 0 || show_archived`) czy zawsze jak na karcie.
4. **Prop `name` w `AccountMarkCompact`**: zostaje (nieużywany przy skrócie) czy znika z sześciu użyć bezpośrednich.
5. **Straż**: czy `AccountMarkCompact.vue` wchodzi do `AccountsHardcodedUiTest`.
6. **Brak dowodu w tym badaniu**: nie uruchamiano aplikacji ani kitchen sinku, więc wygląd 3-znakowego skrótu w 20 px i zawijanie wiersza tytułu przy 390 px są nieweryfikowane.
