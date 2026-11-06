# Awatar konta z logo banku wszędzie (ADR-0054) — plan implementacji

## Overview

Konto finansowe (Account) i lokata (Term Deposit) dostają jeden znak: `AccountAvatar`. Pokazuje logo banku (`mark_url`, `public/marks/*`), a gdy logo brak (gotówka, bank „Inny”, bank bez prawdziwego logo, błąd obrazu), kafelek ze skrótem `mark-label` albo ikoną `Building2`. Zastępuje oba dzisiejsze kafelki: `AccountMarkCompact` (sam skrót, 20/28 px) i `AccountIdentityBlock` (logo tylko na Dashboardzie, 24/40 px). Pojawia się też tam, gdzie dziś stoi sama nazwa: wiersz operacji, filtr i chipy Operacji, lokata w ruchu kapitału, kandydat na parę w imporcie, ostrzeżenia Dashboardu.

Zmiana odwraca ADR-0047 („skrót zamiast logo”, logo tylko na Dashboardzie), więc zaczyna się od ADR-0054 i przepisania reguł. Dopiero potem dane, komponent i widoki.

## Current State Analysis

Pełny audyt (zarzuty 1–5) jest w `context/changes/account-avatar/research.md`. Najważniejsze:

- `AccountMarkCompact.vue:5-45` to tekstowy kafelek bez `mark_url` (`size-5`/`size-7`, `bg-muted rounded-sm`, `aria-hidden`). `AccountIdentityBlock.vue:1-62` to logo `<img :alt="name">` z `object-cover`, ramką `border-border/60`, `rounded-md/lg`, bez `@error`, a dla gotówki pokazuje `Coins`. `CompactAccountIdentity.vue:1-22` przyjmuje `mark_url` i go gubi.
- Stare kafelki są w: `AccountBankSelect`, `AccountSelect`, `CapitalMovementRow`, `CompactAccountIdentity`, `DashboardHoldings`, `OwnTransferRow`, `pages/Accounts.vue`, `pages/TermDeposits.vue`, `pages/dev/AccountsKitchenSink.vue`, oraz na listach strażników: `scripts/check-ui-literals.mjs:37,103`, `tests/Unit/AccountsHardcodedUiTest.php:5,13`.
- Payloady kont niosą już `name/type/bank/mark_url` (`app/Support/AccountIdentity.php:11-43`). Braki: strona Lokat (`TermDepositController.php:165`, a `TermDepositTest.php:133,138` asertuje brak `mark_url`), ruchy kapitału (`OperationsController.php:102` ładuje `termDeposit:id,name`, `:121` wysyła tylko `term_deposit_name`), kandydaci na parę (`app/Support/CsvImport/ImportPairCandidateDetector.php:178-223`, tylko `account_id/account_name`).
- `app/Enums/Bank.php:18-33`: `markPath()` mapuje ING, Pekao i Santander na 300-bajtowe SVG z tekstem „ING/PEK/SAN” na kolorowym kwadracie (`public/marks/ing.svg`), czyli nie na logo. Asercje są w `tests/Feature/OperationsIdentityTest.php:101-145` (dataset `:104-109`).
- `FinancialOperationRow.vue:23` typuje `account_type/bank/mark_url`, ale `:44-51` skleja nazwę konta w string `detail`, który `OperationDescriptionCell.vue:29-32` łączy z opisem przez „ · ”.
- Filtr konta (`OperationsFilters.vue:554-570`) i kandydat na parę (`ImportPreviewRow.vue:440-462`) to natywne `<select>`. Typy `OperationsFilterAccount` (`OperationsFilters.vue:41-45`) i `ChipAccount` gubią tożsamość, choć `filterAccounts` niesie ją z serwera (`OperationsFilterOptions.php:67`).
- `noticePresentation` (`lib/dashboard-notice-presentation.ts:31-36`) redukuje ostrzeżenie o ujemnym saldzie do `{type:'name'}`, choć `types/dashboard.ts:14-27` ma `type/bank/mark_url` (lokata: `bank` i `mark_url`).
- Sinki: `dashboard-fixtures.ts` ma wszędzie `mark_url: null`, więc logo nigdy się tam nie renderuje. Prawdziwe URL-e są tylko w `accounts-fixtures.ts` i `import-start-fixtures.ts`.

## Desired End State

- Jeden komponent `AccountAvatar` (xs 16 / sm 20 / md 28 px, `muted`, `radius-sm`, `object-contain`, bez ramki, `aria-hidden`, `alt=""`) w każdym miejscu z listy w „Key Discoveries”. `AccountMarkCompact` i `AccountIdentityBlock` nie istnieją.
- Konto w mBanku pokazuje logo mBanku na Dashboardzie, w Kontach, w wierszu operacji, w filtrze i chipie, w przelewie, w ruchu kapitału i w imporcie. Lokata w Lokaty Facto pokazuje logo w tabeli Lokat, na Dashboardzie i w ruchu kapitału.
- ING, Pekao i Santander, gotówka („zł”), bank „Inny” (`Building2`) i obraz, który się nie wczytał, pokazują kafelek skrótu, ten sam co dziś w Kontach.
- ADR-0054, `.ai/rules/js.md`, `design-system.md` opisują jeden znak z logo. Nowy agent nie odtworzy podziału „skrót tu, logo tam”.
- Kitchen sinki `/dev/ui/accounts`, `/dev/ui/term-deposits`, `/dev/ui/dashboard`, `/dev/operations`, `/dev/import-preview` renderują logo i każdy stan fallbacku w obu motywach.

Weryfikacja: `composer ci:check`, `vendor/bin/sloppy ci`, testy Feature payloadów na PostgreSQL, `npm run check:ui`, `npm run types:check`, vitest, przegląd sinków w jasnym i ciemnym motywie.

### Key Discoveries:

- Miejsca awatara i rozmiar: Dashboard (konta, lokaty) md; ostrzeżenia Dashboardu xs; tabela Kont md; tabela Lokat md; wiersze przelewów i ruchów kapitału md (konto i lokata); wiersz operacji finansowej xs w linii meta; filtr konta sm (w `AccountSelect`); chip filtra xs; `AccountSelect` w dialogach i imporcie sm; kandydat na parę sm; `AccountBankSelect` sm (zawsze skrót, patrz NOT Doing).
- `AccountIdentity::prefixedRowFields($account, 'account')` (`AccountIdentity.php:19-28`) daje `account_name/type/bank/mark_url` i to jest gotowy kontrakt dla kandydata na parę.
- `TermDeposit::bank` to rzutowany enum `Bank`, nie relacja (`.ai/rules/sloppy.md`). `AccountIdentity::markUrl($termDeposit->bank)` jest już użyte w `DashboardController.php:206` i `DashboardNotices.php:98`.
- reka-ui `SelectItem` nie przyjmuje pustej wartości. Opcja „Wszystkie konta” i „bez pary” potrzebują wartości-wartownika mapowanej na `''`/`null` w komponencie.
- `mark-label` to `font-mono text-[9px]/none font-semibold` (`app.css:273-275`). Trzy znaki mono 9 px mają ok. 16 px szerokości, więc w xs 16 px są na granicy.
- `meta` ma 11/14 px (`app.css:269-271`). Awatar xs 16 px w linii meta podnosi ją o 2 px.

## What We're NOT Doing

- `AccountBankSelect` (wybór banku w dialogach Konta i Lokaty) nie pokazuje logo. Wybiera Bank, nie konto, i nie ma payloadu z URL-ami, a ADR-0047 odrzucił drugą listę po stronie klienta. Używa `AccountAvatar` z `mark_url: null`, więc wygląda jak dziś.
- Selecty lokat (filtr lokaty w Operacjach, `CapitalMovementFormDialog`, lokata w wierszu importu) zostają natywne, z samą nazwą. Payloady `termDeposits`/`filterTermDeposits` w Operacjach i imporcie się nie zmieniają.
- Zdanie podsumowania importu (`OperationsImportPreview.vue:250-257`) zostaje tekstem. Konto jest wewnątrz zdania, a kafelek w zdaniu nic nie dodaje.
- `AccountFormDialog`: nowe konto nie ma znaku do pokazania.
- Nie szukamy prawdziwych logo ING, Pekao i Santander ani licencji plików w `public/marks`. Bank z nowym logo w przyszłości dostaje plik i wpis w `markPath()` w osobnej zmianie.
- Nie zmieniamy awatara użytkownika w sidebarze (`UserInfo`, ADR-0053) ani `BrandMark`. Okrągły kształt zostaje przy nich.
- Bez nowego tokenu i bez „płytki logo”: logo stoi na `muted`.
- Bez zmian w `glossary.md`: znak jest cechą wyglądu, nie regułą Banku.

## Implementation Approach

Kontrakt przed pikselami, zgodnie z łańcuchem `/10x-ui`. Faza 1 zapisuje decyzję (ADR-0054) i przepisuje reguły, które dziś każą budować tekstowe kafelki. Faza 2 domyka dane: obie strony każdego propa Inertii w jednym commicie, z `assertInertia`. Faza 3 buduje komponent i podmienia go tam, gdzie kafelek już stoi, więc widoki nie zmieniają układu, tylko znak. Faza 4 dodaje awatar tam, gdzie go nie było, w tym dwa natywne selecty na reka `Select`. Faza 5 zamyka macierz stanów w sinkach i bramkę.

Wybór treści awatara (logo / skrót / ikona) to czysta funkcja w `lib/account-identity.ts` z testem. Komponent trzyma tylko stan „obraz się nie wczytał”.

## Critical Implementation Details

- **Błąd obrazu i ponowne użycie komponentu.** Vue używa ponownie instancji `AccountAvatar` przy zmianie wiersza (paginacja, filtr, zmiana wybranej opcji w `SelectValue`). Stan `imageFailed` musi się zerować, gdy zmienia się `mark_url`. Inaczej jeden zepsuty obraz chowa logo innego konta.
- **xs i trzy znaki.** W 16 px skrót „PKO”/„SAN” mieści się tylko przy ciasnym odstępie liter. Wariant xs dostaje `tracking-tighter` (utility Tailwinda, nie literał). Czy kafelek mieści skrót, sprawdzamy w sinku przy każdym banku.

## Faza 1: Kontrakt — ADR-0054 i reguły

### Overview

Decyzja i dokumenty, zanim powstanie kod. Po tej fazie reguły opisują jeden awatar z logo.

### Changes Required:

#### 1. ADR-0054

**File**: `context/foundation/adr/0054-account-avatar-with-bank-logo.md`

**Intent**: Zapisuje decyzję: jeden `AccountAvatar` z logo dla każdego konta i lokaty pokazanej jako wiersz lub opcja, fallback do skrótu. Nazywa, które fragmenty starszych ADR zastępuje, a które zostają.

**Contract**: Treść w stylu ADR-0047:
- skala xs 16 / sm 20 / md 28 i gdzie która stoi;
- `muted`, `radius-sm`, `object-contain`, bez ramki;
- `aria-hidden` i `alt=""`, bo nazwa stoi obok;
- fallback: skrót `mark-label` (lista z ADR-0047 bez zmian), „zł” dla gotówki (także na Dashboardzie, zamiast `Coins`), `Building2` dla „Inny”/braku banku, a ten sam skrót po błędzie obrazu;
- ING, Pekao i Santander bez pliku logo, bo pliki były skrótem na kolorowym tle;
- selecty z awatarem to reka `Select`;
- odrzucone: zostawienie trzech SVG, biała płytka z nowym tokenem, `object-cover`, kolumna Konto w wierszu operacji, logo w `AccountBankSelect`.

Zastępuje w części ADR-0047 (akapit „instead of the logo” i akapit o `AccountIdentityBlock`), ADR-0045 (kafelek „zamiast `AccountIdentityBlock`”) i ADR-0051 (to samo dla Lokat). Glossary bez zmian.

#### 2. Reguła js

**File**: `.ai/rules/js.md`

**Intent**: Reguła „The bank or cash tile is `AccountMarkCompact` … `AccountIdentityBlock` shows the logo only on the Dashboard” zostaje przepisana na `AccountAvatar` (ADR-0054). Z listy „Reuse surface building blocks” znika `AccountIdentityBlock`, a pojawia się `AccountAvatar`.

**Contract**: Sekcja „Reuse surface building blocks before writing new markup”. Treść nowej reguły: znak konta lub lokaty to `AccountAvatar`, rozmiar wedle roli (xs w linii tekstu, sm w listach wyboru, md w tabelach i wierszach), wąskie propsy (`type`, `bank`, `mark_url`, bez `v-bind` całego obiektu), nowy bank dostaje skrót w `account-identity.ts`, a logo w `Bank::markPath()` w tym samym commicie. Po edycji `php artisan boost:index-rules`.

#### 3. Strona design systemu

**File**: `context/foundation/design-system.md`

**Intent**: Wpisy o `AccountMarkCompact` i `AccountIdentityBlock` opisują teraz `AccountAvatar`. Lista „Gotowe elementy” ma jeden wpis zamiast dwóch.

**Contract**: Linie 18 (Konta i Lokaty: „znakiem banku `AccountAvatar` 28 px z logo”), 35 (tabela typografii: `mark-label` to „skrót w kafelku `AccountAvatar`”), 86 („Gotowe elementy”), 143 (lista plików `check:ui`) oraz pozostałe trafienia `grep -n "AccountMarkCompact\|AccountIdentityBlock" context/foundation/design-system.md`. Bez nowego tokenu.

### Success Criteria:

#### Automated Verification:

- `grep -rn "AccountMarkCompact\|AccountIdentityBlock" .ai/rules context/foundation/design-system.md` zwraca tylko odwołania historyczne (ADR-0054 je cytuje)
- `php artisan boost:index-rules` przechodzi, a `CLAUDE.md`/`.ai/rules/index.md` są aktualne

#### Manual Verification:

- ADR-0054 czyta się samodzielnie: decyzja, skala, fallback, co zastępuje i co odrzucono

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie przeglądu ADR.

---

## Faza 2: Dane za awatarem

### Overview

Każdy payload, którego awatar potrzebuje, niesie `type`/`bank`/`mark_url`. Każda zmiana propa idzie z typem `defineProps<>` i `assertInertia` w jednym commicie (`.ai/rules/inertia-props.md`).

### Changes Required:

#### 1. Trzy banki bez pliku logo

**Files**: `app/Enums/Bank.php`, `public/marks/ing.svg`, `public/marks/pekao.svg`, `public/marks/santander.svg`, `tests/Feature/OperationsIdentityTest.php`

**Intent**: `markPath()` zwraca `null` dla `Ing`, `Pekao`, `Santander`, tak jak dla `Other`. Trzy pliki są usuwane, a awatar pokaże dla nich skrót.

**Contract**: `Bank::markPath(): ?string`. Dataset `test_operations_page_exposes_mark_url_for_named_retail_banks` zostawia Alior, PKO BP, Revolut z URL-em, a ING, Pekao, Santander przechodzą do asercji `account_mark_url === null`: rozszerzony test „null mark_url for cash and other” albo nowy przypadek. To zmiana wymagania z ADR-0054, nie dopasowanie testu do kodu.

#### 2. Strona Lokat z `mark_url`

**Files**: `app/Http/Controllers/TermDepositController.php`, `resources/js/pages/TermDeposits.vue` (typ wiersza), `resources/js/pages/dev/term-deposits-fixtures.ts`, `tests/Feature/TermDepositTest.php`

**Intent**: `termDepositPayload` dodaje `mark_url` z `AccountIdentity::markUrl($termDeposit->bank)`, więc tabela Lokat może pokazać logo.

**Contract**: Pole `termDeposits.*.mark_url: string|null` (PHPDoc i typ TS). `TermDepositTest.php:133,138` z `missing(...mark_url)` przechodzi na `where(...mark_url, url('/marks/...'))` dla banku z logo i `null` dla banku bez logo. Fixtures sinku dostają `mark_url`.

#### 3. Lokata w ruchu kapitału

**Files**: `app/Http/Controllers/OperationsController.php`, `resources/js/components/CapitalMovementRow.vue` (typ wiersza), `resources/js/lib/operations-fixtures.ts`, test Feature ruchów kapitału na stronie Operacji (`tests/Feature/OperationsIdentityTest.php` albo `CapitalMovementTest.php`, gdzie dziś asertowane jest `capitalMovements`)

**Intent**: Wiersz ruchu kapitału wysyła bank i logo lokaty, żeby lokata dostała awatar obok konta.

**Contract**: Eager load `termDeposit:id,name,bank`. Nowe pola `capitalMovements.data.*.term_deposit_bank: string` i `term_deposit_mark_url: string|null`. Typ wiersza w `CapitalMovementRow.vue` zyskuje te dwa pola.

#### 4. Tożsamość konta u kandydata na parę

**Files**: `app/Support/CsvImport/ImportPairCandidateDetector.php`, typ kandydata w `resources/js/components/ImportPreviewRow.vue` (lub w `lib`, gdzie jest zdefiniowany), `resources/js/lib/import-preview-fixtures.ts`, `tests/Feature/CsvImportTest.php`

**Intent**: Kandydat niesie pełną tożsamość konta z serwera, więc nie trzeba jej szukać na froncie w `otherAccounts`/`account`. To zamyka też pytanie, czy kandydat może wskazać konto importu.

**Contract**: `financialOperationToCandidate` i `ownTransferToCandidate` zamieniają `account_name` na `...AccountIdentity::prefixedRowFields($account, 'account')` (`account_name`, `account_type`, `account_bank`, `account_mark_url`). PHPDoc obu metod rozszerzony. Relacje konta muszą być załadowane z `type,bank`: sprawdź eager load detektora, bez nowego N+1 (`vendor/bin/sloppy ci`). Przy okazji poprawny PHPDoc `otherAccounts` (`CsvImportController.php:358`).

#### 5. Typy filtra konta

**Files**: `resources/js/components/OperationsFilters.vue`, `resources/js/components/OperationsActiveFilterChips.vue`, `tests/Feature/OperationsFilterTest.php`

**Intent**: Typy po stronie klienta przyjmują tożsamość, którą `filterAccounts` już niesie. Test asertuje ją jawnie.

**Contract**: `OperationsFilterAccount = AccountPickerOption & { is_archived: boolean }`, a `ChipAccount` z tego samego typu. `OperationsFilterTest.php:441-445` dokłada `type`, `bank`, `mark_url`.

### Success Criteria:

#### Automated Verification:

- Testy payloadów na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/OperationsIdentityTest.php tests/Feature/TermDepositTest.php tests/Feature/CsvImportTest.php tests/Feature/OperationsFilterTest.php tests/Feature/CapitalMovementTest.php tests/Feature/DashboardTest.php tests/Feature/AccountTest.php`
- `npm run types:check` przechodzi
- `vendor/bin/pint --dirty --format agent` i `vendor/bin/sloppy ci` bez nowych znalezisk
- `test ! -e public/marks/ing.svg -a ! -e public/marks/pekao.svg -a ! -e public/marks/santander.svg`

**Implementation Note**: Faza bez zmian wizualnych poza tym, że Dashboard przestaje pokazywać trzy kolorowe SVG. Pokazuje wtedy `Building2` (stara gałąź `AccountIdentityBlock`) aż do Fazy 3.

---

## Faza 3: Komponent `AccountAvatar` i podmiana istniejących kafelków

### Overview

Powstaje jeden komponent i zastępuje oba stare w każdym miejscu, gdzie kafelek już stoi. Widoki zachowują układ, zmienia się znak.

### Changes Required:

#### 1. Logika treści awatara

**Files**: `resources/js/lib/account-identity.ts`, `resources/js/lib/account-identity.test.ts`

**Intent**: Jedna czysta funkcja decyduje, co pokazać, więc komponent ma tylko markup.

**Contract**:

```ts
export type AccountAvatarContent =
    | { kind: 'logo'; src: string }
    | { kind: 'mark'; text: string }
    | { kind: 'icon' };

export function accountAvatarContent(
    account: Pick<AccountIdentityFields, 'type' | 'bank' | 'mark_url'>,
    imageFailed: boolean,
): AccountAvatarContent;
```

Kolejność: `type === 'bank' && mark_url !== null && !imageFailed` → logo. Inaczej `accountShortMark` (gotówka „zł”, skrót banku) → mark, a `null` → icon. Testy obejmują: logo, gotówkę z (błędnym) `mark_url`, bank „Inny”, bank bez logo (ING), błąd obrazu banku z logo (→ skrót, nie ikona).

#### 2. Komponent

**File**: `resources/js/components/AccountAvatar.vue` (nowy)

**Intent**: Kafelek `muted`, `radius-sm`, `overflow-hidden`, `aria-hidden`. Logo jako `<img alt="" class="size-full object-contain">` z `@error`, który ustawia `imageFailed`. Skrót w `mark-label`, ikona `Building2` w `muted-foreground`.

**Contract**: Props `type: AccountTypeValue`, `bank: AccountBank | null`, `mark_url: string | null`, `size?: 'xs' | 'sm' | 'md'` (domyślnie `sm`): xs `size-4` z `tracking-tighter` dla skrótu i ikoną `size-2.5`, sm `size-5` z ikoną `size-3`, md `size-7` z ikoną `size-3.5`. `imageFailed` zeruje się przy zmianie `mark_url` (patrz Critical Implementation Details). Bez literałów px/kolorów.

#### 3. Podmiana w obecnych miejscach

**Files**: `CompactAccountIdentity.vue`, `AccountSelect.vue`, `AccountBankSelect.vue`, `OwnTransferRow.vue`, `CapitalMovementRow.vue` (konto), `DashboardHoldings.vue`, `pages/Accounts.vue`, `pages/TermDeposits.vue`

**Intent**: Każde użycie `AccountMarkCompact`/`AccountIdentityBlock` przechodzi na `AccountAvatar` z `mark_url`. Dashboard rezygnuje ze slotu `AccountIdentityBlock` na rzecz `AccountAvatar` md obok własnego bloku nazwy i podpisu. Lokaty (tabela i Dashboard) podają `type="bank"`.

**Contract**: Wąskie propsy (`:type`, `:bank`, `:mark_url`), bez `v-bind` obiektu. `CompactAccountIdentity` przekazuje `mark_url` dalej, a jego `markSize` przyjmuje `'xs' | 'sm' | 'md'`. `AccountBankSelect` podaje `:mark_url="null"`. Rozmiary: Dashboard 24 → 28 px (md).

#### 4. Usunięcie starych komponentów i strażnicy

**Files**: `resources/js/components/AccountMarkCompact.vue`, `resources/js/components/AccountIdentityBlock.vue` (usunięte), `scripts/check-ui-literals.mjs`, `tests/Unit/AccountsHardcodedUiTest.php`

**Intent**: Stare komponenty znikają. `AccountAvatar.vue` trafia na listę `check:ui` w miejsce `AccountMarkCompact.vue` i do `AccountsHardcodedUiTest.php`, a docblock tego testu przestaje wspominać `AccountIdentityBlock`.

**Contract**: `grep -rn "AccountMarkCompact\|AccountIdentityBlock" resources/js scripts tests` zwraca 0 trafień.

#### 5. Sinki Kont, Lokat, Dashboardu

**Files**: `resources/js/pages/dev/AccountsKitchenSink.vue`, `pages/dev/accounts-fixtures.ts`, `pages/dev/term-deposits-fixtures.ts`, `pages/dev/dashboard-fixtures.ts` (+ `dashboard-fixtures.test.ts`, jeśli asertuje `mark_url`)

**Intent**: Sekcja „Znaki banków” w sinku Kont pokazuje `AccountAvatar` w xs, sm i md dla każdego banku i gotówki: logo, gdzie jest, skrót dla ING/Pekao/Santander/„Inny”, „zł”, oraz jeden przypadek z celowo złym URL-em (błąd obrazu). Fixtures Dashboardu i Lokat dostają prawdziwe `mark_url` (`/marks/mbank.jpg`, `/marks/pko-bp.svg`, `/marks/lokaty-facto.svg`, `/marks/revolut.svg`).

**Contract**: URL-e w fixtures to ścieżki z `public/marks`. Usunięte pliki ING/Pekao/Santander nie mogą się w nich pojawić.

### Success Criteria:

#### Automated Verification:

- `npx vitest run resources/js/lib/account-identity.test.ts` przechodzi, z przypadkami logo, gotówka, „Inny”, bank bez logo, błąd obrazu
- `npm run types:check`, `npm run lint` (lub skrypt lint z `package.json`) i `npm run check:ui` przechodzą
- `grep -rn "AccountMarkCompact\|AccountIdentityBlock" resources/js scripts tests` zwraca 0 trafień
- `php artisan test --compact tests/Unit/AccountsHardcodedUiTest.php tests/Unit/DashboardHardcodedUiTest.php tests/Unit/TermDepositsHardcodedUiTest.php` przechodzi

#### Manual Verification:

- `/dev/ui/accounts`: siatka znaków w xs/sm/md, logo nie jest przycięte (PKO BP, Lokaty Facto), skrót mieści się w xs, przypadek błędu obrazu pokazuje skrót
- `/dev/ui/dashboard` i `/dev/ui/term-deposits`: logo w wierszach kont i lokat, gotówka „zł”, wiersze bez skoku wysokości po zmianie 24 → 28 px

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie sinków.

---

## Faza 4: Awatar tam, gdzie była sama nazwa

### Overview

Awatar trafia do miejsc z samą nazwą. Dwa natywne selecty przechodzą na reka `Select`, zgodnie z regułą „opcja z ikoną lub znakiem → reka `Select`”.

### Changes Required:

#### 1. Wiersz operacji finansowej

**Files**: `resources/js/components/OperationDescriptionCell.vue`, `resources/js/components/FinancialOperationRow.vue`

**Intent**: Linia meta pokazuje „opis · [awatar xs] mBank”, a przy zwrocie „Zwrot · [awatar xs] mBank”. Nazwa konta przestaje być częścią sklejanego stringa.

**Contract**: `OperationDescriptionCell` dostaje opcjonalny prop `account?: AccountIdentityFields | null`, renderowany na końcu drugiej linii po separatorze „ · ” jako `CompactAccountIdentity` xs (inline, `meta`), w obu gałęziach szablonu (z podpowiedzią i bez). Linia meta pojawia się też wtedy, gdy jedyną jej treścią jest konto. `FinancialOperationRow` podaje w `detail` tylko „Zwrot” (lub nic), a konto w `account`. Wiersze przelewów i ruchów kapitału nie podają `account`. Siatka `.operations-grid` bez zmian (ADR-0039).

#### 2. Filtr konta w Operacjach

**Files**: `resources/js/components/AccountSelect.vue`, `resources/js/components/OperationsFilters.vue`

**Intent**: Natywny `<select id="operations-filter-account">` zastępuje `AccountSelect` z opcją „Wszystkie konta” i dopiskiem „(zarchiwizowane)”.

**Contract**: `AccountSelect` dostaje opcjonalne `allOptionLabel?: string` (pierwsza pozycja z wartością-wartownikiem, emitowana jako `''`). `AccountPickerOption` przyjmuje opcjonalne `is_archived?: boolean` z propem `archivedSuffix?: string` doklejanym do nazwy w opcji i w `text-value`. Bez `allOptionLabel` i `archivedSuffix` zachowanie dzisiejszych użyć się nie zmienia. `OperationsFilters` podłącza `:model-value` z `filters.account_id ?? ''` i `@update:model-value` do `applyFilters({ account_id })`. `id` i `<Label for>` zostają, więc wyzwalacz ma nazwę dostępną.

#### 3. Chip aktywnego filtra konta

**File**: `resources/js/components/OperationsActiveFilterChips.vue`

**Intent**: Chip konta pokazuje awatar xs przed nazwą.

**Contract**: Chip typu konto renderuje `AccountAvatar` xs z danych `ChipAccount`. Etykieta i `aria-label` usuwania bez zmian.

#### 4. Lokata w ruchu kapitału

**File**: `resources/js/components/CapitalMovementRow.vue`

**Intent**: Nazwa lokaty po obu stronach strzałki dostaje awatar md, jak konto.

**Contract**: `CompactAccountIdentity` md z `type="bank"`, `:bank="movement.term_deposit_bank"`, `:mark_url="movement.term_deposit_mark_url"`, `:name="movement.term_deposit_name"`. Dzisiejsze `font-medium` nazwy lokaty zostaje wyrównane do stylu nazwy konta w tym samym wierszu.

#### 5. Kandydat na parę w imporcie

**File**: `resources/js/components/ImportPreviewRow.vue`

**Intent**: Natywny select kandydata zastępuje reka `Select`. Każda opcja ma awatar sm konta i dotychczasową etykietę.

**Contract**: Wartości opcji to dzisiejsze `pairCandidateKey(candidate)`, a „bez pary” to wartownik mapowany na `''` w `onPairCandidateChange`. Ukryte `input`-y `rows[i][pair_first_leg_kind]` i pozostałe zostają bez zmian, więc payload formularza się nie zmienia. `ariaLabel` z `controlLabel(...)` przechodzi na `SelectTrigger` (`.ai/rules/js-import.md`). `SelectValue` pokazuje awatar i etykietę wybranego kandydata.

#### 6. Ostrzeżenia Dashboardu

**Files**: `resources/js/lib/dashboard-notice-presentation.ts`, `resources/js/lib/dashboard-notice-presentation.test.ts`, `resources/js/components/DashboardNotices.vue`

**Intent**: Ostrzeżenie o ujemnym saldzie konta lub lokaty pokazuje awatar xs przed nazwą.

**Contract**: `NoticeIdentity` dostaje wariant `{ type: 'account'; name: string; avatar: Pick<AccountIdentityFields, 'type' | 'bank' | 'mark_url'> }`. `negative_account` mapuje swoje pola, a `negative_term_deposit` dostaje `type: 'bank'`. Test prezentacji asertuje oba warianty. `DashboardNotices` renderuje awatar w linii tożsamości `.dashboard-notice`, bez nowego markupu karty.

#### 7. Sinki Operacji i importu

**Files**: `resources/js/lib/operations-fixtures.ts`, `resources/js/lib/import-preview-fixtures.ts`, `resources/js/pages/dev/dashboard-fixtures.ts`, `resources/js/pages/dev/OperationsSink.vue` i `ImportPreviewSink.vue` (jeśli stan wymaga nowej sekcji)

**Intent**: Fixtures niosą prawdziwe `mark_url` i komplet stanów: operacja na koncie z logo, na gotówce, na banku bez logo i jako zwrot; aktywny filtr konta; ruch kapitału z lokatą z logo; kandydat na parę z logo i bez; ostrzeżenie o ujemnym koncie i lokacie z logo.

**Contract**: Kształt fixtures zgodny z typami z Fazy 2.

### Success Criteria:

#### Automated Verification:

- `npx vitest run resources/js/lib/dashboard-notice-presentation.test.ts resources/js/lib/account-identity.test.ts` przechodzi
- `npm run types:check` i `npm run check:ui` przechodzą
- `php artisan test --compact tests/Unit/DashboardHardcodedUiTest.php tests/Unit/ImportPreviewHardcodedUiTest.php` przechodzi
- `npx playwright test tests/e2e/import-other-format-without-header.spec.ts tests/e2e/account-edit-keeps-bank.spec.ts` przechodzi

#### Manual Verification:

- `/dev/operations`: awatar xs w linii meta przy wydatku, dochodzie i zwrocie; linia meta nie łamie się przy długiej nazwie konta (obcięcie `truncate`)
- Filtr konta: „Wszystkie konta” czyści filtr, konto zarchiwizowane ma dopisek, wybór klawiaturą (strzałki, Enter, Esc) działa, `?section=` zostaje w URL-u
- `/dev/import-preview`: kandydat na parę z awatarem, wybór i „bez pary” wysyłają te same pola co przed zmianą (sprawdź payload w devtools albo w teście Feature importu z parą)
- `/dev/ui/dashboard`: ostrzeżenie o ujemnym koncie i lokacie z awatarem

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Faza 5: Stany i bramka

### Overview

Macierz stanów w sinkach jest kompletna i sprawdzona w obu motywach. Pełna bramka przechodzi na PostgreSQL.

### Changes Required:

#### 1. Macierz stanów awatara

**Files**: sinki z Faz 3–4 (dopisanie brakujących przypadków)

**Intent**: Każdy z siedmiu stanów jest widoczny w co najmniej jednym sinku: (1) logo kwadratowe nieprzezroczyste (mBank, BNP), (2) logo nie-kwadratowe lub przezroczyste (PKO BP, Lokaty Facto, Revolut), (3) bank bez logo (ING/Pekao/Santander), (4) gotówka „zł”, (5) bank „Inny”, (6) błąd obrazu, (7) zarchiwizowane konto i długa nazwa obok awatara. Wszystko w jasnym i ciemnym motywie.

**Contract**: Brak nowych komponentów. Jeśli przezroczyste ciemne logo (Revolut) ma za słaby kontrast na `muted` w trybie ciemnym, zapisz to w ADR-0054 jako znane ograniczenie albo podmień plik. Nowy token tylko z decyzją użytkownika.

#### 2. Screenshoty bramki

**Files**: brak w repo (artefakty w raporcie implementacji)

**Intent**: Zrzuty `/dev/ui/accounts`, `/dev/ui/dashboard`, `/dev/operations`, `/dev/import-preview` w obu motywach jako dowód do przeglądu (`/10x-ui` screenshot gate).

**Contract**: `playwright-cli` albo podgląd worktree według `context/foundation/agents/worktree-preview.md`.

### Success Criteria:

#### Automated Verification:

- `composer ci:check` przechodzi (Sail, PostgreSQL wg AGENTS.md)
- `vendor/bin/sloppy ci` na hoście przechodzi
- `npm run check:ui` przechodzi

#### Manual Verification:

- Siedem stanów widocznych w sinkach w jasnym i ciemnym motywie, logo nieprzycięte, skrót czytelny w xs
- Kontrast JPEG-ów (biała podstawa mBanku/BNP) i przezroczystego Revoluta na `muted` w trybie ciemnym zaakceptowany albo zapisany w ADR-0054
- Rzeczywista aplikacja: Operacje, Konta, Lokaty, Dashboard i podgląd importu pokazują ten sam znak dla tego samego konta

---

## Testing Strategy

### Unit Tests:

- `accountAvatarContent`: logo, gotówka (także z niepustym `mark_url`), „Inny”, bank bez logo, błąd obrazu → skrót.
- `noticePresentation`: warianty `account` dla `negative_account` i `negative_term_deposit`.
- Strażnicy `*HardcodedUiTest.php` z `AccountAvatar.vue` na liście.

### Integration Tests:

- `assertInertia` dla każdego zmienionego propa: `mark_url` null dla ING/Pekao/Santander (`OperationsIdentityTest`), `termDeposits.*.mark_url` (`TermDepositTest`), `capitalMovements.data.*.term_deposit_bank/mark_url`, tożsamość kandydata na parę (`CsvImportTest`), `filterAccounts.*.type/bank/mark_url` (`OperationsFilterTest`). Na PostgreSQL.
- E2E bez nowych testów. Istniejące `account-edit-keeps-bank` i `import-other-format-without-header` muszą przejść, bo dotykają `AccountBankSelect` i importu.

### Manual Testing Steps:

1. W aplikacji dodaj konto mBank, konto ING i gotówkę. Sprawdź znak na Dashboardzie, w Kontach, w Operacjach (wiersz, filtr, chip) i w imporcie.
2. Zmień w devtools `src` jednego logo na zły adres i potwierdź, że pokazuje się skrót.
3. Przełącz motyw na ciemny i przejrzyj sinki.

## Performance Considerations

Logo to małe pliki statyczne (największy `alior.svg` ma 42 KB) z tego samego hosta, ładowane raz i cache'owane przez przeglądarkę. Lista Operacji ma paginację, więc liczba `<img>` na stronę jest ograniczona. Bez `loading="lazy"`, bo obrazy są małe i w widoku.

## Migration Notes

Bez migracji danych. Usunięcie trzech plików z `public/marks` nie wpływa na dane: URL wylicza się z enumu przy każdym żądaniu.

## References

- Research: `context/changes/account-avatar/research.md`
- Decyzja zastępowana: `context/foundation/adr/0047-bank-short-marks-and-accounts-title-row.md`
- Poprzednia zmiana kafelka: `context/archive/2026-11-06-accounts-design-system-page/plan.md`
- Wzorzec reka `Select` z kafelkiem: `resources/js/components/AccountSelect.vue:55-90`
- Tożsamość konta na serwerze: `app/Support/AccountIdentity.php:11-43`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kontrakt — ADR-0054 i reguły

#### Automated

- [x] 1.1 `grep -rn "AccountMarkCompact\|AccountIdentityBlock" .ai/rules context/foundation/design-system.md` zwraca tylko odwołania historyczne (ADR-0054 je cytuje) — f434942
- [x] 1.2 `php artisan boost:index-rules` przechodzi, a `CLAUDE.md`/`.ai/rules/index.md` są aktualne

#### Manual

- [x] 1.3 ADR-0054 czyta się samodzielnie: decyzja, skala, fallback, co zastępuje i co odrzucono — f434942

### Phase 2: Dane za awatarem

#### Automated

- [x] 2.1 Testy payloadów na PostgreSQL (OperationsIdentity, TermDeposit, CsvImport, OperationsFilter, CapitalMovement, Dashboard, Account) — 964e342
- [x] 2.2 `npm run types:check` przechodzi — 964e342
- [x] 2.3 `vendor/bin/pint --dirty --format agent` i `vendor/bin/sloppy ci` bez nowych znalezisk — 964e342
- [x] 2.4 Pliki `public/marks/ing.svg`, `pekao.svg`, `santander.svg` nie istnieją — 964e342

### Phase 3: Komponent `AccountAvatar` i podmiana istniejących kafelków

#### Automated

- [x] 3.1 `npx vitest run resources/js/lib/account-identity.test.ts` przechodzi, z przypadkami logo, gotówka, „Inny”, bank bez logo, błąd obrazu — 2392171
- [x] 3.2 `npm run types:check`, `npm run lint` (lub skrypt lint z `package.json`) i `npm run check:ui` przechodzą — 2392171
- [x] 3.3 `grep -rn "AccountMarkCompact\|AccountIdentityBlock" resources/js scripts tests` zwraca 0 trafień — 2392171
- [x] 3.4 `php artisan test --compact tests/Unit/AccountsHardcodedUiTest.php tests/Unit/DashboardHardcodedUiTest.php tests/Unit/TermDepositsHardcodedUiTest.php` przechodzi — 2392171

#### Manual

- [x] 3.5 `/dev/ui/accounts`: siatka znaków w xs/sm/md, logo nieprzycięte, skrót mieści się w xs, błąd obrazu pokazuje skrót — 2392171
- [x] 3.6 `/dev/ui/dashboard` i `/dev/ui/term-deposits`: logo w wierszach kont i lokat, gotówka „zł”, wiersze bez skoku wysokości — 2392171

### Phase 4: Awatar tam, gdzie była sama nazwa

#### Automated

- [x] 4.1 `npx vitest run resources/js/lib/dashboard-notice-presentation.test.ts resources/js/lib/account-identity.test.ts` przechodzi — c8aec59
- [x] 4.2 `npm run types:check` i `npm run check:ui` przechodzą — c8aec59
- [x] 4.3 `php artisan test --compact tests/Unit/DashboardHardcodedUiTest.php tests/Unit/ImportPreviewHardcodedUiTest.php` przechodzi — c8aec59
- [x] 4.4 `npx playwright test tests/e2e/import-other-format-without-header.spec.ts tests/e2e/account-edit-keeps-bank.spec.ts` przechodzi — c8aec59

#### Manual

- [x] 4.5 `/dev/operations`: awatar xs w linii meta przy wydatku, dochodzie i zwrocie; długa nazwa konta obcięta — c8aec59
- [x] 4.6 Filtr konta: „Wszystkie konta”, dopisek zarchiwizowanego, obsługa klawiaturą, `?section=` zachowany — c8aec59
- [x] 4.7 `/dev/import-preview`: kandydat na parę z awatarem, wybór i „bez pary” wysyłają te same pola co przed zmianą — c8aec59
- [x] 4.8 `/dev/ui/dashboard`: ostrzeżenie o ujemnym koncie i lokacie z awatarem — c8aec59

### Phase 5: Stany i bramka

#### Automated

- [x] 5.1 `composer ci:check` przechodzi (Sail, PostgreSQL) — 4bf5441
- [x] 5.2 `vendor/bin/sloppy ci` na hoście przechodzi — 4bf5441
- [x] 5.3 `npm run check:ui` przechodzi — 4bf5441

#### Manual

- [x] 5.4 Siedem stanów widocznych w sinkach w jasnym i ciemnym motywie — 4bf5441
- [x] 5.5 Kontrast JPEG-ów i przezroczystego Revoluta na `muted` w trybie ciemnym zaakceptowany albo zapisany w ADR-0054 — 4bf5441
- [x] 5.6 Rzeczywista aplikacja: ten sam znak dla tego samego konta we wszystkich widokach — 4bf5441
