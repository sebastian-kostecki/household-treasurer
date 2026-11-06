# Konta w systemie „C · Atrament i morski” — plan implementacji

## Overview

Widok Konta (`resources/js/pages/Accounts.vue`) i dialog konta (`AccountFormDialog.vue`) przechodzą na system „C · Atrament i morski”, jak już Budżet, Operacje, Plan miesiąca i Dashboard. W widoku pojawiają się:

- wiersz tytułu z `page-title` i opisem;
- jeden `StatPanel` z miarami Płynność dysponowalna i Łączne środki;
- `Switch` „Pokaż zarchiwizowane” w osobnym wierszu;
- tabela na siatce `.accounts-grid` z nagłówkami kolumn i „zł” w każdej kwocie;
- wiersz zarchiwizowany w `muted-foreground` bez przezroczystości.

Dialog dostaje pełny kontrakt ADR-0040. Dwie decyzje odchodzą od zapisanych reguł („zł” w komórkach zamiast stopki i ton ujemnego salda zarchiwizowanego konta), więc najpierw powstaje ADR-0044.

## Current State Analysis

Pełny audyt jest w `context/changes/accounts-atrament-ui/research.md` (zarzuty C1–C5). Najważniejsze:

- Miara to jedna karta `StatTile` z `text-3xl` (`Accounts.vue:99-102`, `StatTile.vue:29`). Kontroler wysyła tylko `liquidity_total_minor_units` (`AccountController.php:37-44`).
- Lista to `ListSurface` z wierszami `.holdings-list-row` (`app.css:811-814`, `flex-wrap`, `border-border/60`) bez nagłówków kolumn. Typ konta jest podpisem pod nazwą (`Accounts.vue:171-173`), a akcje na telefonie spadają do osobnej linii (`:186`).
- Zarchiwizowany wiersz ma `opacity-60` (`:156`), saldo z `amountToneClass` (`:177-181`) i Badge `secondary` (`:167`).
- „Pokaż zarchiwizowane” to `Checkbox` (`:104-119`), wbrew `js.md` („A setting saved at once … is a `Switch`”).
- Tytuł ma `text-lg font-semibold` (`:93`), a opis strony jest schowany w podpisie listy (`:151`).
- `AccountFormDialog.vue:139-249` ma ręczne bloki `div.grid > Label + Input + InputError`, „Typ” jako `native-select`, saldo otwarcia bez „zł” w polu, „Anuluj” `secondary`, bez spinnera i bez blokady zamykania. To łamie `.ai/rules/js-dialogs.md` (ADR-0040) i jest długiem F7 z `term-deposits-ui-contract`.

Weryfikacja podczas planowania dołożyła:

- Łączne środki liczy inline tylko `DashboardController.php:56-57,128` (`SpendableLiquidity::forUser` + suma wszystkich lokat). `TermDepositController.php:40` sumuje tylko lokaty i nie jest kopią Łącznych środków.
- Mock `PageAccounts` zmienia w dialogu tylko „Anuluj” na `outline`, ale komponenty `Dialog` i `Field` mocka (wspólne dla wszystkich dialogów) wymagają pól 34 px, „zł” w polu kwoty, blokady zamykania w trakcie zapisu i `SegmentedControl` dla wyboru z kilku opcji.
- Konto da się zarchiwizować z ujemnym saldem (`AccountController.php:107`). `SpendableLiquidity` je liczy (`SpendableLiquidity.php:12-15`), a FR-ACC-10 (`prd-mvp.md:143`) każe jednoznacznie oznaczyć ujemne saldo konta.

## Desired End State

Widok Konta od góry:

- **Wiersz tytułu:** `h1.page-title` „Konta”, pod nim `text-muted-foreground text-sm` „Rachunki bankowe i konta gotówkowe.”, po prawej „Dodaj konto” (`primary`). Wzorzec: `Budget.vue:172-186`.
- **Panel miar:** `StatPanel` z dwiema miarami. Pierwsza to Płynność dysponowalna w `kpi-lg` z podpisem „konta, bez lokat”, druga to Łączne środki z podpisem „z lokatami”. Obie przez `formatMoney` i `amountToneClass`.
- **Switch:** `Switch` + `Label` „Pokaż zarchiwizowane” w osobnym wierszu pod panelem miar, gdy są konta zarchiwizowane albo filtr jest włączony (jak `Budget.vue:248-256`). Na czas żądania jest wyłączony.
- **Tabela:**
    - jeden `ListSurface` z wierszem nagłówka `table-header` (Nazwa | Typ | Saldo bieżące | Akcje), widocznym od `md`;
    - wiersze `.accounts-grid`: znak banku `AccountMarkCompact size="md"` (28 px), nazwa i pod nią podpis `meta` (bank albo „Portfel”), Typ, saldo `formatMoney` z „zł” w `amount` do prawej, akcje `IconAction`;
    - na telefonie: w pierwszej linii nazwa i saldo, w drugiej typ i akcje;
    - bez stopki „Kwoty w zł” (ADR-0044).
- **Wiersz zarchiwizowany:** cały w `muted-foreground`, bez `opacity` i bez tonu. Badge `neutral` „Zarchiwizowane” przy nazwie. Wyjątek: ujemne saldo zostaje `text-destructive` (ADR-0044, FR-ACC-10).
- **Stany puste:** jak dziś. W stanie „wszystkie zarchiwizowane” drugi przycisk „Dodaj konto” zmienia się na `outline` (`design-system.md`, „pozostałe są `outline`”).
- **Dialog konta:**
    - pola przez `FormField` (34 px), „Typ” jako `SegmentedControl` `block` w tonie `primary` z `name="type"`;
    - saldo otwarcia w `FormField money` z „zł” w polu;
    - „Anuluj” `outline`, spinner na „Zapisz” i blokada zamykania podczas zapisu.
- **Straże i podglądy:** `AccountsHardcodedUiTest` blokuje style sprzed Atramentu w plikach Kont. `/dev/ui/accounts` ma scenariusz zarchiwizowanego konta z ujemnym saldem, a `/dev/ui/dialogs` pokazuje dialog konta w nowych stanach.

Weryfikacja: testy faz 2–4, pełny `composer ci:check` w obrazie Sail, `vendor/bin/sloppy ci` na hoście oraz zrzuty z podglądu worktree przy 390 i 1280 px w obu motywach.

### Key Discoveries:

- `StatPanel` przyjmuje 2–4 miary `{id?, label, value, caption?, toneClass?}` (`StatPanel.vue:6-24`). Dwie miary układają się 50/50 od `md` (`app.css:1148-1188`).
- Podpis wiersza (bank albo gotówka) jest już w `DashboardHoldings.vue:25-47`. `bankLabel` czyta `t.accounts.bank_*`.
- Wzorzec blokady zamykania i spinnera jest w `CategoryFormDialog.vue:74,152,166-172,356-372` (`busy`, `:show-close-button="!busy"`, `@start`/`@finish`, `Spinner v-if="processing"`).
- `FormField` ma wariant `money` (`FormField.vue:12,32,44`). Konsumenci: `CategoryFormDialog`, `ExpenseFormDialog`, `IncomeFormDialog`, `OwnTransferFormDialog`, `CapitalMovementFormDialog`, `CreateBudgetDialog`.
- `AccountMarkCompact` `md` to `size-7` (28 px, `AccountMarkCompact.vue:17-23`), ale ma `border-border/60` (`:42`). Używają go też `AccountSelect` i `AccountBankSelect`, więc zostaje bez zmian i poza listą nowej straży.
- `scripts/check-ui-literals.mjs:32-37` już pilnuje `Accounts.vue`, `AccountFormDialog.vue`, `AccountMarkCompact.vue`, `ConfirmActionDialog.vue` i sinka Kont.
- Asercje propsów strony Kont są w `tests/Feature/AccountTest.php:178-205, 390-427` i `ArchiveAccountTest.php:118-136`.

## What We're NOT Doing

- Lokaty (`TermDeposits.vue`, `TermDepositFormDialog.vue`): własne `/10x-ui`. Do tego czasu zostają przy `.holdings-list-row`, `StatTile` i `Checkbox`.
- Zmiany w `.holdings-list-row`, `AccountIdentityBlock`, `AccountMarkCompact` (`border-border/60`) i `StatTile`, bo mają konsumentów poza Kontami.
- Skróty banku i tekst „zł” w kafelku znaku (jak w mocku `ht-mark`): każdy nazwany bank ma logo (`Bank.php:18-31`), gotówka ma `Coins`, „Inny” ma `Building2`.
- Podpis „z lokatami X zł” z kwotą lokat: podpis jest statyczny, jak na Dashboardzie.
- `Switch` w wierszu tytułu (jak w mocku): stoi w osobnym wierszu, jak w Budżecie.
- Stopka „Kwoty w zł” i wiersz sumy w tabeli Kont: sumy niesie panel miar.
- Wspólny composable `useShowArchivedToggle` (F7, refaktor trzech kopii).
- `TermDepositController.php:40` (suma lokat, nie Łączne środki).
- Zmiany treści dialogów i potwierdzeń („Usunąć konto?” itd.) oraz flag `can_delete` / `can_archive` / `can_restore`.
- Rozbieżność stopek Dashboardu z `design-system.md:63` (ADR-0043:23): sprawa dokumentacji Dashboardu.

## Implementation Approach

Ta sama kolejność co w poprzednich zmianach Atramentu:

1. dokumenty przed kodem (AGENTS.md: zmiana decyzji to najpierw ADR);
2. kontrakt propsów z testami;
3. widok;
4. dialog;
5. macierz stanów i bramka wizualna.

Każda faza kończy się zielonymi `npm run check`, `npm run types:check` i testami, których dotyka, więc stan pośredni da się wdrożyć. Nowy układ trafia do nazwanej klasy w `@layer components` w `app.css`, a nie do utility w szablonie (`.ai/rules/js.md`, „Views take values from app.css”).

## Critical Implementation Details

- **Kolejność kontraktu propsów:** `total_funds_minor_units` jest wymagany w `AccountsPageProps`, a fixtures sinka są tym typem typowane. Kontroler, typ, wszystkie 6 scenariuszy w `accounts-fixtures.ts:84-153` i `assertInertia` idą w jednym commicie (`.ai/rules/inertia-props.md`), inaczej `types:check` padnie.
- **Typ jako segmenty a pole Bank:** `SegmentedControl` z `name="type"` musi zostać związany z tym samym `ref` `type`, który dziś steruje `v-if="type === 'bank'"` pola Bank i wysyłką `bank`. W trybie edycji wartość startowa pochodzi z konta, jak dziś.
- **Saldo otwarcia:** ukryte pole `opening_balance_minor_units` i walidacja `openingBalanceInvalid` zostają. `FormField money` zmienia tylko wygląd pola widocznego.

## Faza 1: Decyzje i dokumenty

### Overview

Zapisać w ADR-0044 decyzje, które odchodzą od ADR-0037 i od wyjątku FR-TD-08, i uaktualnić `design-system.md` oraz `js.md`. Bez zmian w kodzie aplikacji.

### Changes Required:

#### 1. ADR-0044

**File**: `context/foundation/adr/0044-accounts-table-follows-atrament.md` (nowy)

**Intent**: Zapisać układ widoku Kont i dwa odstępstwa od zapisanych reguł, w stylu ADR-0042/0043 (krótkie akapity).

**Contract**: ADR mówi, że:

- tabela Kont ma kolumny Nazwa | Typ | Saldo bieżące | Akcje z wierszem nagłówka `table-header`, a saldo w każdej komórce idzie przez `formatMoney` z „zł”, bez stopki „Kwoty w zł”. To drugi wyjątek od ADR-0037 obok listy Operacji (ADR-0039). Powód: jedna kolumna kwot, brak wiersza sumy, sumy w panelu miar powyżej i wzorzec `PageAccounts`;
- ujemne saldo zarchiwizowanego konta zostaje `text-destructive` (FR-ACC-10), a reszta wiersza jest `muted-foreground`. Wyjątek FR-TD-08 obejmuje więc salda lokat i kont;
- panel miar ma dwie miary (Płynność dysponowalna, Łączne środki „z lokatami”);
- `Switch` stoi w osobnym wierszu pod panelem, jak w Budżecie;
- znak banku to logo w `AccountMarkCompact` 28 px, bez skrótów z podglądu;
- dialog konta przyjmuje pełne ADR-0040 z „Typ” jako `SegmentedControl` `block`.

Odrzucone alternatywy: stopka „Kwoty w zł” (ADR-0037), `Switch` w wierszu tytułu, podpis z kwotą lokat, skróty banku w kafelku, zgaszony ton ujemnego salda. ADR zastępuje część ADR-0037 (jednostka w tabeli Kont) i wskazuje to.

#### 2. Design system

**File**: `context/foundation/design-system.md`

**Intent**: Opisać Konta jako widok Atramentu i rozszerzone wyjątki.

**Contract**:

- ADR-0044 na liście ADR (`:3`).
- Zdanie o zarchiwizowanych wierszach (`:17`) i wiersz `muted-foreground` w tabeli kolorów (`:55`): wyjątek obejmuje ujemne saldo zarchiwizowanej lokaty i zarchiwizowanego konta.
- „Kwoty” (`:63`): drugi wyjątek, tabela Kont ma „zł” w każdej kwocie i nie ma stopki (ADR-0044).
- „Listy i tabele”: siatka `.accounts-grid` z wierszem nagłówka.
- Typografia: Konta wśród widoków wdrożonych.
- „Gotowe elementy”: `StatTile` tylko w Lokatach, `StatPanel` używają także Konta.
- Kitchen sink: akapit o `/dev/ui/accounts` i jego stanach.

#### 3. Reguły

**File**: `.ai/rules/js.md`

**Intent**: Uaktualnić reguły, które dziś opisują stary widok Kont albo wąski wyjątek.

**Contract**:

- „Amounts go through formatMoney”: wyjątek „except the Operations list” obejmuje też tabelę Kont (ADR-0044).
- „Archived rows: no tone, except a term deposit balance < 0”: dopisać ujemne saldo konta (ADR-0044, FR-ACC-10).
- „Responsive lists”: `.accounts-grid` wśród klas siatek.
- „Tokens and shared components”: zdanie o `tests/Unit/AccountsHardcodedUiTest.php` obok zdania o Dashboardzie.

Po edycji uruchomić `php artisan boost:index-rules`, jeśli zmienia się frontmatter albo indeks (tu nie powinien).

### Success Criteria:

#### Automated Verification:

- ADR-0044 istnieje i jest najwyższym numerem: `ls context/foundation/adr | tail -1`
- Formatowanie dokumentów przechodzi: `npm run check`

#### Manual Verification:

- ADR-0044, `design-system.md` i `js.md` opisują decyzje z tabeli „Key Decisions” briefu, bez sprzeczności między sobą

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Kontrakt propsów

### Overview

Wydzielić odczyt Łącznych środków do `app/Support`, użyć go w Dashboardzie i dodać props `total_funds_minor_units` do strony Kont po obu stronach kontraktu. Widok jeszcze go nie renderuje.

### Changes Required:

#### 1. Odczyt Total funds

**File**: `app/Support/TotalFunds.php` (nowy, `php artisan make:class`), `app/Http/Controllers/DashboardController.php`

**Intent**: Jedno miejsce definicji Łącznych środków (`glossary.md:167-169`) zamiast drugiej kopii sumy w `AccountController` (AGENTS.md: „`app/Support` readings and calculations”).

**Contract**:

- `TotalFunds::forUser(User $user): int` zwraca `SpendableLiquidity::forUser($user)` plus sumę `current_balance_minor_units` wszystkich lokat użytkownika, także zarchiwizowanych i ujemnych. Docblock tak jak w `SpendableLiquidity`.
- `DashboardController`: `total_funds_minor_units` pochodzi z `TotalFunds::forUser($user)`. Zmienna `$termDepositsTotalMinorUnits` znika, jeśli nie ma innego użycia. Wartość propsa Dashboardu bez zmian (`DashboardTest.php:170-191` pozostaje zielony).

#### 2. Props strony Kont

**File**: `app/Http/Controllers/AccountController.php`, `resources/js/types/accounts.ts`, `resources/js/pages/dev/accounts-fixtures.ts`

**Intent**: Dać stronie drugą miarę z serwera (C1).

**Contract**:

- `index()` wysyła `'total_funds_minor_units' => TotalFunds::forUser($request->user())`.
- `AccountsPageProps.total_funds_minor_units: number`.
- Każdy z 6 scenariuszy fixtures dostaje wartość zgodną ze scenariuszem: równą płynności, gdy fixture nie ma lokat, a w `default` większą o przykładową sumę lokat.

#### 3. Testy

**File**: `tests/Feature/TotalFundsTest.php` (nowy, `php artisan make:test --pest`), `tests/Feature/AccountTest.php`, `tests/Feature/ArchiveAccountTest.php`

**Intent**: Przypiąć definicję odczytu i kształt propsa (AGENTS.md).

**Contract**:

- `TotalFundsTest`:
    - suma kont (także zarchiwizowanych) i lokat (także zarchiwizowanych i ujemnych);
    - zero bez kont i lokat;
    - dane innego użytkownika nie wchodzą do sumy.
- `AccountTest` (`:178-205`): `assertInertia` z `total_funds_minor_units` dla użytkownika z kontami i lokatą.
- `ArchiveAccountTest` (`:118-136`): `total_funds_minor_units` obejmuje saldo zarchiwizowanego konta.
- Testy na PostgreSQL (sumy w SQL).

### Success Criteria:

#### Automated Verification:

- Testy na PostgreSQL w obrazie Sail: `docker run … artisan test --compact tests/Feature/TotalFundsTest.php tests/Feature/AccountTest.php tests/Feature/ArchiveAccountTest.php tests/Feature/DashboardTest.php tests/Feature/AccountsKitchenSinkTest.php`
- Typy po buildzie: `npm run build && npm run types:check`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

**Implementation Note**: Faza nie ma kroków ręcznych; po zielonej weryfikacji przejdź do fazy 3.

---

## Faza 3: Widok Konta

### Overview

Wiersz tytułu, panel miar, `Switch`, tabela na `.accounts-grid` z wierszem zarchiwizowanym i straż literałów Kont.

### Changes Required:

#### 1. Siatka tabeli

**File**: `resources/css/app.css`

**Intent**: Dać tabeli Kont nazwany układ bez utility i wartości w szablonie (`css.md`, `js.md`), bez ruszania `.holdings-list-row` Lokat.

**Contract**: w `@layer components`, obok `.budget-category-grid` i w jego stylu:

- `.accounts-grid` z `grid-template-areas`:
    - na telefonie dwie linie: `"name balance"` i `"type actions"`;
    - od `md` jedna linia `"name type balance actions"`, z kolumnami nazwa elastyczna, typ, saldo i akcje o stałych szerokościach z mocka (`minmax(0,1fr) 180px 150px 96px`) zapisanymi w klasie;
- `.accounts-header-row` w `table-header`, ukryty poniżej `md`;
- `.accounts-row`: wysokość `--row-height`, separator `border-soft`, hover `secondary`;
- `.accounts-row-archived`: `muted-foreground` dla całego wiersza, bez `opacity`;
- komórki `.accounts-cell-*` dla obszarów. Saldo i akcje są do prawej.

#### 2. Widok

**File**: `resources/js/pages/Accounts.vue`, `lang/pl/ui.php`

**Intent**: Usunąć zarzuty C1–C5 w widoku zgodnie z ADR-0044.

**Contract**:

- Wiersz tytułu: `h1.page-title` „Konta” i `p.text-muted-foreground.text-sm` z `t.accounts.list_description` po lewej, „Dodaj konto” po prawej (`Budget.vue:172-186`).
- `StatPanel` z miarami:
    - `id: 'liquidity'`: `t.accounts.spendable_liquidity`, podpis `t.accounts.liquidity_caption` „konta, bez lokat”;
    - `id: 'total-funds'`: `t.accounts.total_funds` „Łączne środki”, podpis `t.accounts.total_funds_caption` „z lokatami”.

    Obie wartości przez `formatMoney`, ton przez `amountToneClass`. Nowe klucze w przestrzeni `accounts.*`, bez pożyczania `dashboard.*` (lekcja F1). `StatTile` znika z importów.

- `Switch` (`components/ui/switch`) + `Label` „Pokaż zarchiwizowane”, `v-model="showArchived"`, `:disabled="isTogglingArchived"`, ten sam `v-if` i `id`, w osobnym wierszu pod panelem (`Budget.vue:248-256`). `Checkbox` znika z importów.
- `ListSurface` bez `description`, z wierszem `.accounts-header-row` (`t.accounts.name`, `t.accounts.type`, `t.accounts.current_balance`, nowy `t.accounts.actions` „Akcje”), w układzie wierszy jak `BudgetCategoryList.vue`.
- Wiersz `.accounts-row .accounts-grid`:
    - nazwa: `AccountMarkCompact size="md"`, `font-medium` nazwa, Badge `neutral` „Zarchiwizowane” przy zarchiwizowanym, podpis `meta` z bankiem albo nowym `t.accounts.cash_caption` „Portfel” (logika jak `DashboardHoldings.vue:25-47`, bez podpisu przy `bank === null`);
    - typ: `accountTypeLabel`;
    - saldo: `formatMoney` w `amount`, przy koncie aktywnym z `amountToneClass`. Przy zarchiwizowanym bez tonu, a przy ujemnym `text-destructive`. Klasę liczy jedna funkcja w widoku;
    - akcje: te same `IconAction` i `ConfirmActionDialog` co dziś, bez `w-full`.
- `AccountIdentityBlock`, `opacity-60` i Badge `secondary` znikają z widoku.
- Stan „wszystkie zarchiwizowane”: drugi przycisk „Dodaj konto” `outline` zamiast `secondary`.
- Klucze bez użycia po zmianie (`grep` w `resources/js`) zostają, jeśli używa ich inny widok. `accounts.liquidity_total` zostaje (`DashboardHoldings.vue:149`).

#### 3. Straż literałów Kont

**File**: `tests/Unit/AccountsHardcodedUiTest.php` (nowy, `php artisan make:test --pest --unit`)

**Intent**: Zatrzymać powrót typografii, ramek i przezroczystości sprzed Atramentu w Kontach (research, „Kitchen sink i zabezpieczenia”).

**Contract**:

- Na wzór `DashboardHardcodedUiTest.php`: te same wzorce i komunikat `plik:linia: rodzaj: dopasowanie`.
- Lista plików: `Accounts.vue`, `pages/dev/AccountsKitchenSink.vue`, `pages/dev/accounts-fixtures.ts`. Faza 4 dopisuje `AccountFormDialog.vue`.
- Docblock wyjaśnia, że `AccountMarkCompact.vue`, `AccountIdentityBlock.vue` i `StatTile.vue` są pominięte, bo mają konsumentów poza Kontami.
- Jeśli sink ma style sprzed Atramentu, faza je czyści.

### Success Criteria:

#### Automated Verification:

- Statyka, typy i straże: `npm run check`, `npm run build && npm run types:check`, `npm run check:ui`
- Vitest (`ui-contract.test.ts` i inne): `npm test`
- `AccountsHardcodedUiTest`, `AccountsPageTest` i testy Kont w obrazie Sail: `docker run … artisan test --compact tests/Unit/AccountsHardcodedUiTest.php tests/Unit/AccountsPageTest.php tests/Feature/AccountTest.php tests/Feature/AccountsKitchenSinkTest.php`
- W widoku nie ma starych elementów: `grep -n "StatTile\|Checkbox\|AccountIdentityBlock\|holdings-list-row\|opacity-60" resources/js/pages/Accounts.vue` zwraca pusto

#### Manual Verification:

- W `/dev/ui/accounts` (podgląd z worktree) panel ma dwie miary, tabela ma nagłówki kolumn od `md`, a saldo stoi w jednej kolumnie z „zł”
- Zarchiwizowany wiersz w `with-archived` jest wyciszony bez przezroczystości, a ikona „Przywróć” ma pełny kontrast
- Na 390 px wiersz ma dwie linie (nazwa + saldo, typ + akcje), bez przewijania w poziomie
- Lokaty (`/dev/ui/term-deposits`) i panele Kont na Dashboardzie wyglądają jak przed zmianą

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Dialog konta

### Overview

`AccountFormDialog` przyjmuje pełny kontrakt ADR-0040 (`.ai/rules/js-dialogs.md`), a „Typ” staje się segmentami. Treść, pola i walidacja bez zmian.

### Changes Required:

#### 1. Dialog

**File**: `resources/js/components/AccountFormDialog.vue`

**Intent**: Dialog konta wygląda i działa jak dialogi kategorii i operacji. Zamyka dług F7 i odroczenia z `category-dialog-atrament` i `operation-dialogs-atrament`.

**Contract**:

- Nazwa, Data otwarcia i Bank przez `FormField` z tymi samymi `id`, `name` i błędami. `AccountBankSelect` jest kontrolką w slocie `FormField`.
- „Typ”: `SegmentedControl` `block`, ton `primary`, `name="type"`, opcje `bank` / `cash` z `t.accounts.type_bank` / `t.accounts.type_cash`, związany z `type`. Pole Bank pokazuje się jak dziś.
- Saldo otwarcia: `FormField money` (Mono, do prawej, „zł” w polu), ukryte pole minor units i `openingBalanceInvalid` bez zmian.
- Stopka: „Anuluj” `variant="outline"`, `:disabled="processing"`. „Zapisz” ze `Spinner v-if="processing"`.
- Blokada zamykania jak w `CategoryFormDialog.vue:74,152,166-172`: `busy` z `@start`/`@finish` formularza, `update:open` ignoruje zamknięcie przy `busy`, `DialogContent :show-close-button="!busy"`.
- Bez ręcznych `div.grid > Label + Input + InputError`.

#### 2. Kitchen sink dialogów i straż

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`, `tests/Feature/DialogsKitchenSinkTest.php`, `tests/Unit/AccountsHardcodedUiTest.php`

**Intent**: Stany dialogu konta są do obejrzenia przed przeglądem (`js-dialogs.md`: „Add new dialog states to … `/dev/ui/dialogs`”).

**Contract**:

- W sekcji „Konto” (`DialogsKitchenSink.vue:49,86`) są tryby: tworzenie (bank), tworzenie (gotówka, bez pola Bank), edycja i błędy walidacji serwera. Stan zapisu w toku opisuje tabela stanów i pokazują zrzuty z fazy 5.
- `DialogsKitchenSinkTest` dostaje asercję nowych kluczy, jeśli asertuje listę.
- `AccountsHardcodedUiTest` obejmuje `AccountFormDialog.vue`.

### Success Criteria:

#### Automated Verification:

- Statyka, typy i straże: `npm run check`, `npm run build && npm run types:check`, `npm run check:ui`
- Vitest: `npm test`
- Testy w obrazie Sail: `docker run … artisan test --compact tests/Unit/AccountsHardcodedUiTest.php tests/Feature/AccountTest.php tests/Feature/DialogsKitchenSinkTest.php`
- Dialog bez ręcznych pól: `grep -n "variant=\"secondary\"\|<Label" resources/js/components/AccountFormDialog.vue` zwraca pusto

#### Manual Verification:

- W podglądzie utworzenie konta bankowego i gotówkowego zapisuje poprawne `type` i `bank`, a przełączenie segmentu na „Gotówka” chowa pole Bank
- Edycja konta otwiera dialog z zaznaczonym typem konta i zapisuje zmiany
- W trakcie zapisu „Zapisz” ma spinner, a `Esc`, klik w tło i „Anuluj” nie zamykają dialogu
- Błędne saldo otwarcia daje komunikat pod polem i wyłącza „Zapisz”

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 5.

---

## Faza 5: Stany i bramka wizualna

### Overview

Dopisać brakujące stany do `/dev/ui/accounts`, uruchomić podgląd z worktree, zrobić zrzuty w obu motywach i przejść pełną bramkę CI.

### Changes Required:

#### 1. Scenariusze sinka

**File**: `resources/js/pages/dev/accounts-fixtures.ts`, `resources/js/pages/dev/AccountsKitchenSink.vue`, `tests/Feature/AccountsKitchenSinkTest.php`

**Intent**: Każda komórka macierzy 7 stanów (default, hover, focus-visible, disabled, error, empty, loading) ma miejsce przed przeglądem.

**Contract**:

- Nowy scenariusz `archived-negative`: zarchiwizowane konto z ujemnym saldem (`text-destructive`) obok zarchiwizowanego z dodatnim (bez tonu). Płynność i Łączne środki obejmują oba.
- Nowy scenariusz `long-names`: długa nazwa konta i banku, sprawdza zawijanie w obszarze `name` na 390 px.
- Tabela macierzy (`AccountsKitchenSink.vue:12-41`) wymienia nowe scenariusze, `Switch` wł./wył. i panel z dwiema miarami. Hover, focus-visible, disabled i loading odsyła do zrzutów z tej fazy.
- `AccountsKitchenSinkTest` dostaje asercję nowych kluczy, jeśli asertuje listę.

#### 2. Podgląd i zrzuty

**File**: brak zmian w kodzie (procedura `context/foundation/agents/worktree-preview.md`)

**Intent**: Pokazać stany, których sink nie pokaże statycznie (przeglądy budget F7, month-plan F11).

**Contract**:

- Podgląd z worktree na osobnej bazie: `migrate --seed`, `npm run build`, logowanie `test@example.com` / `password`.
- Zrzuty 390 px i 1280 px w jasnym i ciemnym motywie: `/accounts` i każdy scenariusz `/dev/ui/accounts`, do tego dialog konta w `/dev/ui/dialogs`.
- Stany interakcji:
    - hover wiersza;
    - focus-visible na `Switch`, `IconAction` i segmentach Typu;
    - disabled `Switch` w trakcie żądania;
    - spinner zapisu w dialogu.
- Zrzuty trafiają do `context/changes/accounts-atrament-ui/screenshots/`. Jednorazowy skrypt Playwright nie jest commitowany.

### Success Criteria:

#### Automated Verification:

- Pełna bramka CI w obrazie Sail: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy na hoście: `vendor/bin/sloppy ci`
- Statyka, typy, straże i Vitest: `npm run check`, `npm run types:check`, `npm run check:ui`, `npm test`

#### Manual Verification:

- Każdy scenariusz renderuje się w `/dev/ui/accounts` bez błędów w konsoli
- Zrzuty 390/1280 px w obu motywach nie pokazują przepełnienia ani tekstu poniżej czytelności, a ujemne saldo zarchiwizowanego konta jest czytelne jako `destructive` na wyciszonym wierszu
- Hover, focus-visible i disabled są widoczne na zrzutach dla wymienionych elementów
- Płynność i Łączne środki na `/accounts` z `DemoHouseholdSeeder` są równe miarom na Dashboardzie

**Implementation Note**: Po zielonej weryfikacji automatycznej i potwierdzeniu zrzutów zmiana jest gotowa do przeglądu (`/10x-impl-review`).

---

## Testing Strategy

### Unit Tests:

- `AccountsHardcodedUiTest`: wzorce literałów sprzed Atramentu w plikach Kont, z wynikiem `plik:linia`.
- `AccountsPageTest` (istniejący): bez zmian.
- `ui-contract.test.ts` (Vitest): Konta nadal bez `:checked`. `Switch` przez `v-model`.

### Integration Tests:

- `TotalFundsTest`:
    - suma kont i lokat, w tym zarchiwizowanych i ujemnych;
    - zero bez danych;
    - izolacja użytkowników.
- `AccountTest` i `ArchiveAccountTest`: `total_funds_minor_units` w `assertInertia`. Istniejące asercje `liquidity_total_minor_units` i `archived_accounts_count` bez zmian.
- `DashboardTest`: istniejący przypadek `total_funds_minor_units` (`:170-191`) zielony po przepięciu na `TotalFunds`.
- `AccountsKitchenSinkTest`, `DialogsKitchenSinkTest`: nowe scenariusze, jeśli testy asertują listę.

### Manual Testing Steps:

1. Otwórz `/accounts` w podglądzie: tytuł z opisem, dwie miary, tabela z nagłówkami.
2. Włącz „Pokaż zarchiwizowane”: przełącznik jest wyłączony w trakcie żądania, potem pojawiają się wyciszone wiersze z Badge „Zarchiwizowane”.
3. Zarchiwizuj konto z ujemnym saldem: wiersz wyciszony, saldo w `destructive`, Płynność bez zmian.
4. Dodaj konto gotówkowe przez dialog: segment „Gotówka” chowa Bank, zapis ma spinner i nie da się go przerwać zamknięciem.
5. Zwęź okno do 390 px: dwie linie na wiersz, bez przewijania w poziomie.
6. Przejdź po stronie klawiszem Tab: fokus widoczny na „Dodaj konto”, `Switch` i akcjach wiersza.

## Performance Considerations

`TotalFunds::forUser` to dwa zapytania `SUM` (konta i lokaty). Strona Kont zyskuje jedno zapytanie (suma lokat), Dashboard zostaje przy tej samej liczbie. Bez wpływu na payload poza jedną liczbą.

## Migration Notes

Bez migracji danych i bez zmian tras. `#show-archived-accounts` zostaje jako `id` przełącznika; żaden test E2E od niego nie zależy.

## References

- Research: `context/changes/accounts-atrament-ui/research.md`
- Wzorzec: artefakt https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (PageAccounts, StatPanel, DataTable, Switch, Dialog, Field)
- Poprzednie zmiany Atramentu: `context/archive/2026-11-05-dashboard-atrament-ui/plan.md`, `context/archive/2026-11-05-budget-atrament-ui/plan.md`
- Wiersz tytułu i `Switch`: `resources/js/pages/Budget.vue:172-186, 248-256`
- Podpis konta: `resources/js/components/DashboardHoldings.vue:25-47`
- Blokada zamykania dialogu: `resources/js/components/CategoryFormDialog.vue:74,152,166-172`
- Straż literałów: `tests/Unit/DashboardHardcodedUiTest.php`
- Reguły: `.ai/rules/js.md`, `.ai/rules/js-dialogs.md`, `.ai/rules/inertia-props.md`; ADR-0037, ADR-0039, ADR-0040, ADR-0043

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0044 istnieje i jest najwyższym numerem
- [x] 1.2 Formatowanie dokumentów przechodzi (npm run check)

#### Manual

- [ ] 1.3 ADR-0044, design-system.md i js.md są zgodne z decyzjami briefu

### Phase 2: Kontrakt propsów

#### Automated

- [ ] 2.1 Testy TotalFunds, Kont, archiwizacji, Dashboardu i sinka Kont na PostgreSQL przechodzą
- [ ] 2.2 Typy po buildzie przechodzą
- [ ] 2.3 Formatowanie PHP (Pint)
- [ ] 2.4 Sloppy bez nowych znalezisk

### Phase 3: Widok Konta

#### Automated

- [ ] 3.1 Statyka, typy i straże przechodzą
- [ ] 3.2 Vitest przechodzi
- [ ] 3.3 AccountsHardcodedUiTest i testy Kont w obrazie Sail przechodzą
- [ ] 3.4 W widoku nie ma StatTile, Checkbox, AccountIdentityBlock, holdings-list-row ani opacity-60

#### Manual

- [ ] 3.5 Dwie miary, nagłówki kolumn od md i saldo z „zł” w jednej kolumnie
- [ ] 3.6 Zarchiwizowany wiersz wyciszony bez przezroczystości, „Przywróć” w pełnym kontraście
- [ ] 3.7 Na 390 px wiersz ma dwie linie, bez przewijania w poziomie
- [ ] 3.8 Lokaty i panele Kont na Dashboardzie bez zmian

### Phase 4: Dialog konta

#### Automated

- [ ] 4.1 Statyka, typy i straże przechodzą
- [ ] 4.2 Vitest przechodzi
- [ ] 4.3 AccountsHardcodedUiTest, testy Kont i sinka dialogów w obrazie Sail przechodzą
- [ ] 4.4 Dialog bez ręcznych pól i bez Anuluj secondary

#### Manual

- [ ] 4.5 Konto bankowe i gotówkowe zapisują poprawne type i bank, Gotówka chowa pole Bank
- [ ] 4.6 Edycja otwiera dialog z typem konta i zapisuje zmiany
- [ ] 4.7 W trakcie zapisu spinner, a Esc, tło i Anuluj nie zamykają dialogu
- [ ] 4.8 Błędne saldo otwarcia daje komunikat i wyłącza Zapisz

### Phase 5: Stany i bramka wizualna

#### Automated

- [ ] 5.1 Pełna bramka CI (composer ci:check w obrazie Sail)
- [ ] 5.2 Sloppy na hoście
- [ ] 5.3 Statyka, typy, straże i Vitest

#### Manual

- [ ] 5.4 Każdy scenariusz renderuje się w /dev/ui/accounts bez błędów
- [ ] 5.5 Zrzuty 390/1280 px w obu motywach bez przepełnienia, ujemne saldo zarchiwizowanego konta czytelne
- [ ] 5.6 Hover, focus-visible i disabled widoczne na zrzutach
- [ ] 5.7 Płynność i Łączne środki na Kontach równe miarom Dashboardu
