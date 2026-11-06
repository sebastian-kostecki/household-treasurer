# Konta według strony design systemu (ADR-0045) — plan implementacji

## Overview

Widok Konta (`resources/js/pages/Accounts.vue`) wyrównujemy do strony `PageAccounts` design systemu „C · Atrament i morski”, tak jak zapisał ADR-0045. Zmieniają się dwie rzeczy:

- `Switch` „Pokaż zarchiwizowane” przechodzi z osobnego rzędu pod panelem miar do wiersza tytułu, przed „Dodaj konto”;
- kafelek `AccountMarkCompact` pokazuje skrót banku w JetBrains Mono (przepis `ht-mark`) zamiast logo, a konto gotówkowe „zł”.

ADR-0045 zostawił wartości skrótów zmianie, która go wdraża. Dlatego najpierw powstaje ADR-0047 (skróty, zakres komponentu, wariant `sm`, warunek widoczności Switcha), potem kod. Payload Inertii się nie zmienia.

## Current State Analysis

Pełny audyt jest w `context/changes/accounts-design-system-page/research.md` (zarzuty C1–C5, wszystkie potwierdzone). Najważniejsze:

- Wiersz tytułu `Accounts.vue:128-138` ma tylko „Dodaj konto”. Switch stoi w osobnym rzędzie `:142-157` z warunkiem `archived_accounts_count > 0 || show_archived`. Logika (`showArchived`, `toggleShowArchived`, `:32-46`, `:105-121`) nie zależy od miejsca markupu.
- `AccountMarkCompact.vue:29-62` renderuje `<img :src="mark_url" :alt="name">`, a w pozostałych gałęziach `Coins` (gotówka) albo `Building2` (bank bez logo). Kafelek ma `border-border/60` i `rounded-md`/`rounded-lg` (`:21-23`, `:42`), czyli coś innego niż `ht-mark`: 28 px, `radius-sm`, tło `muted`, bez obwódki, Mono 9 px / 600.
- `AccountIdentityFields` (`account-identity.ts:15-20`) zawiera już `bank`, więc skrót da się wyliczyć na kliencie. Nowy prop Inertii nie jest potrzebny.
- `bankMarkPath` (`account-identity.ts:45-60`) ma jednego użytkownika, `AccountBankSelect.vue:15,60,73`. Ten komponent jest też w `TermDepositFormDialog.vue:5`, więc skrót pojawi się również w dialogu lokaty.
- `<img :alt="name">` dubluje nazwę, która zawsze stoi obok kafelka (C5).

Weryfikacja podczas planowania dołożyła:

- Wiersze Operacji przekazują `mark-size="md"` (`OwnTransferRow.vue:39,48`, `CapitalMovementRow.vue:36,54`), więc mają 28 px. Wariant `sm` (20 px) zostaje tylko w dwóch listach wyboru: `AccountBankSelect` i `AccountSelect`. Research mówił, że `sm` mają też wiersze Operacji, ale to się nie potwierdziło.
- `AccountSelect.vue:64,76` wywołuje `<AccountMarkCompact v-bind="account" />` na `AccountPickerOption`. Pole `id`, które nie jest propem, już dziś ląduje jako atrybut HTML na kafelku, więc w DOM są zduplikowane `id`.
- W projekcie nie ma `@vue/test-utils`. Testy JS to czyste funkcje w `resources/js/lib/*.test.ts` (Vitest przez `vp test`).
- `--radius-sm` = `calc(var(--radius) - 4px)` = 6 px (`app.css:18`, `:156`), dostępne jako `rounded-sm`.

## Desired End State

- **Wiersz tytułu Kont:** po lewej `h1.page-title` i opis, po prawej jedna grupa akcji: [`Switch` + `Label` „Pokaż zarchiwizowane”] i „Dodaj konto” (`primary`). Switch jest widoczny, gdy `archived_accounts_count > 0 || show_archived`. Przy 390 px grupa akcji zawija się jako całość, a Switch nie odrywa się od etykiety. Osobnego rzędu pod `StatPanel` już nie ma.
- **Kafelek `AccountMarkCompact`:** `bg-muted`, `rounded-sm`, bez obwódki. Zawiera tekst skrótu w stylu `mark-label` (JetBrains Mono 9 px, 600, `foreground`) z `aria-hidden="true"`. Rozmiary: `md` 28 px (tabela Kont, wiersze Operacji), `sm` 20 px (listy wyboru konta i banku).
- **Skróty (ADR-0047):** `alior` ALI, `bnp_paribas` BNP, `ing` ING, `lokaty_facto` LF, `mbank` mB, `pekao` PEK, `pko_bp` PKO, `revolut` REV, `santander` SAN. Konto gotówkowe ma „zł”. Bank `other` i konto bankowe bez banku nie mają skrótu i pokazują ikonę `Building2` (`aria-hidden`). Skrót ma najwyżej 3 znaki.
- **Dashboard i Lokaty** (`AccountIdentityBlock`) na razie zostają przy logo. `mark_url`, `Bank::markPath()` i `public/marks/*` zostają, bo czyta je `AccountIdentityBlock`.
- **`/dev/ui/accounts`** ma sekcję „Znaki banków” z kafelkiem każdego banku, gotówki i banku bez nazwy, w obu rozmiarach. Macierz stanów opisuje Switch w wierszu tytułu.

Weryfikacja: `npm run test`, `npm run types:check`, `npm run check:ui`, `AccountsHardcodedUiTest` (z `AccountMarkCompact.vue` na liście), `composer ci:check`, zrzuty sinka i widoku przy 390 i 1280 px w obu motywach.

### Key Discoveries:

- `account-identity.ts:32-43`: `bankOptions` to kanoniczna lista banków w TS. Pełny `Record<AccountBank, …>` sprawia, że nowy bank bez skrótu nie przejdzie `types:check`.
- `app.css:246-318`: typografia to nazwane `@utility` z wartościami px w środku (`meta`, `amount`). `.ai/rules/css.md` każe dodać nowy styl tutaj i używać go po nazwie.
- `AccountsHardcodedUiTest.php:7-15` pomija `AccountMarkCompact.vue` „bo ma konsumentów poza Kontami”. Po usunięciu `border-border/60` komponent przechodzi ten test, więc może trafić na listę.
- `CompactAccountIdentity.vue:19-26` przekazuje `name` i `mark_url` do kafelka. Jego własne propsy zostają typem `AccountIdentityFields`, bo wiersze Operacji przekazują pełną tożsamość z payloadu.

## What We're NOT Doing

- `AccountIdentityBlock` (Dashboard, Lokaty) i jego logo: przejdzie na skrót w osobnej zmianie widoku Dashboardu albo Lokat (np. `term-deposits-atrament-ui`), z tymi samymi wartościami z ADR-0047.
- Usunięcie `mark_url` z payloadów PHP, `Bank::markPath()`, `AccountIdentity::markUrl` i `public/marks/*`, a także asercji `mark_url` w testach Feature: dopiero po wyrównaniu `AccountIdentityBlock`.
- Przycisk „Anuluj” w `AccountFormDialog` (poza zakresem z `change.md`).
- README `PageAccounts` i `CategoryChip` w artefakcie claude.ai („logo zamiast skrótu”, nieaktualne „Stan”): to treść artefaktu, nie repo. ADR-0047 tylko odnotowuje tę sprzeczność.
- Zmiana wysokości wariantu `sm` albo przejście na jeden rozmiar 28 px.
- Switch widoczny zawsze, jak na karcie.
- Wspólny helper straży „hardcoded UI” (F6 z `context/archive/2026-11-06-accounts-atrament-ui/follow-ups/`).
- Wpis w `glossary.md`: skrót to cecha wyglądu, nie reguła domeny Bank.

## Implementation Approach

Decyzja idzie przed kodem (`AGENTS.md`: zmiana na stronie design systemu, która zmienia decyzję, ląduje w ADR). Faza 1 zapisuje ADR-0047 i dokumenty. Faza 2 przebudowuje kafelek od czystej funkcji w górę: `accountShortMark()` z testem, potem styl `mark-label`, komponent i jego konsumenci, na końcu straż. Faza 3 przenosi Switch, rozszerza sink i zamyka zmianę bramką wizualną. Numer ADR 0047 wybrano w `change.md`. 0046 nie ma na żadnej gałęzi, ale zostaje wolny.

## Critical Implementation Details

- **Zawężone propsy i `v-bind`:** `AccountMarkCompact` przyjmuje tylko `type`, `bank` i `size`. Konsument, który zrobi `v-bind` całego obiektu konta, przeniesie `id`, `name` i `mark_url` na `<span>` jako atrybuty HTML (fallthrough). Dlatego `AccountSelect` przekazuje `:type` i `:bank` jawnie, a pozostali konsumenci przestają przekazywać `name` i `mark_url`. Po fazie 2 w DOM `/dev/ui/dialogs` i Operacji kafelek nie może mieć atrybutów `id`, `name` ani `mark_url`.

## Faza 1: Decyzje i dokumenty

### Overview

ADR-0047 zapisuje wartości i zakres, których ADR-0045 nie ustalił. `design-system.md` i reguła w `.ai/rules/js.md` opisują nowy kafelek, żeby następny agent nie wrócił do logo.

### Changes Required:

#### 1. ADR-0047

**File**: `context/foundation/adr/0047-bank-short-marks-and-accounts-title-row.md`

**Intent**: Zapisać decyzje, które ADR-0045 zostawił zmianie wdrażającej, oraz to, czym ta zmiana odchodzi od karty `PageAccounts`.

**Contract**: Styl i struktura jak w ADR-0044 i ADR-0045 (proza po angielsku, akapity tematyczne, na końcu „supersedes” i odrzucone opcje). Treść:
- tabela skrótów: ALI, BNP, ING, LF, mB, PEK, PKO, REV, SAN; gotówka „zł”; `other` i bank pusty: ikona `Building2`, bez tekstu;
- reguła: najwyżej 3 znaki; lista żyje w `resources/js/lib/account-identity.ts` obok `bankOptions`, a nowy bank dostaje skrót w tym samym commicie;
- Alior to „ALI”, a nie „A” z karty, bo jedna litera nie identyfikuje banku i łamie jednolitość;
- `AccountMarkCompact` pokazuje skrót we wszystkich użyciach (tabela Kont, wiersze Operacji, wybór konta, wybór banku w dialogach konta i lokaty);
- wariant `sm` 20 px zostaje dla list wyboru (karta ma tylko 28 px);
- kafelek ma `aria-hidden`, bo nazwa zawsze stoi obok;
- `AccountIdentityBlock` zostaje przy logo do czasu swojej zmiany widoku, a z nim `mark_url` i `public/marks/*`;
- Switch zostaje warunkowy (`archived_accounts_count > 0 || show_archived`) wbrew karcie, bo przełącznik bez zarchiwizowanych kont nic nie robi;
- README `CategoryChip` w artefakcie („logo zamiast skrótu”) jest sprzeczny z ADR-0045, a ADR-0045 jest rozstrzygający;
- „Supersedes in part”: ADR-0044 (logo 28 px w kafelku, już zastąpione przez ADR-0045). Odrzucone: skróty jako prop Inertii z PHP (drugie źródło listy bez zysku, dialog i tak nie ma payloadu), jeden rozmiar 28 px, skrót tylko w Kontach.

#### 2. Strona design systemu

**File**: `context/foundation/design-system.md`

**Intent**: Dopisać ADR-0047 i nowy styl, żeby strona opisywała kafelek tak, jak będzie w kodzie.

**Contract**: (a) lista ADR w akapicie wstępnym (`:3`) dostaje ADR-0047; (b) tabela stylów typografii (wiersz `meta`, `:34`) dostaje wiersz `mark-label` „JetBrains Mono 600, 9/9 | skrót banku w `AccountMarkCompact`”; (c) w „Gotowe elementy” (`:82`) `AccountMarkCompact` ma opis „kafelek znaku banku lub gotówki: `muted`, `radius-sm`, skrót `mark-label` (ADR-0047), 28 px, w listach wyboru 20 px”; (d) opis tabeli Kont (`:18`) dostaje odnośnik do ADR-0047 przy skrócie i przy warunku Switcha; (e) opis sinka (`:99`) dostaje „znaki wszystkich banków w obu rozmiarach”.

#### 3. Reguła dla widoków

**File**: `.ai/rules/js.md`, potem `php artisan boost:index-rules`

**Intent**: Zostawić trwałą regułę: znak banku to skrót z `account-identity.ts`, nie logo ani tekst wpisany w szablonie.

**Contract**: W sekcji „Reuse surface building blocks before writing new markup” jedno zdanie: „The bank or cash tile is `AccountMarkCompact`: a short text mark from `accountShortMark` in `@/lib/account-identity` in the `mark-label` style, „zł” for cash, `Building2` for Other (ADR-0047); a new Bank gets its short mark there in the same commit. `AccountIdentityBlock` still shows the logo until its own view change.” Indeks reguł po `boost:index-rules` bez innych różnic niż ta sekcja.

### Success Criteria:

#### Automated Verification:

- ADR-0047 istnieje i jest jedynym plikiem `0047-*` w `context/foundation/adr/`
- `php artisan boost:index-rules` kończy się bez błędu, a `git diff` pokazuje tylko zamierzone zmiany w regułach

#### Manual Verification:

- ADR-0047 zawiera tabelę dziewięciu skrótów, gotówkę, `other`, regułę 3 znaków, zakres komponentu, wariant `sm`, warunek Switcha i odrzucone opcje
- `design-system.md` i `.ai/rules/js.md` nie przeczą ADR-0047 (skrót, a nie logo, w `AccountMarkCompact`; logo tylko w `AccountIdentityBlock`)

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie treści ADR, zanim zaczniesz kod.

---

## Faza 2: Skrót banku w `AccountMarkCompact`

### Overview

Kafelek pokazuje skrót z jednej funkcji w `account-identity.ts`, styl `mark-label` jest w `app.css`, a konsumenci przekazują tylko `type` i `bank`. Logo znika z tego komponentu.

### Changes Required:

#### 1. Lista skrótów i funkcja

**File**: `resources/js/lib/account-identity.ts`, `resources/js/lib/account-identity.test.ts`

**Intent**: Skrót staje się drugim polem listy banków w TS (ADR-0045: „a field of the bank list”), a znak konta liczy jedna czysta funkcja, którą testuje Vitest. `bankMarkPaths` i `bankMarkPath` znikają, bo tracą ostatniego użytkownika.

**Contract**:
- `const bankShortMarks: Record<AccountBank, string | null>` z wartościami z ADR-0047 (`other: null`);
- `export function accountShortMark(account: Pick<AccountIdentityFields, 'type' | 'bank'>): string | null` zwraca `'zł'` dla `type === 'cash'`, skrót dla banku i `null` dla `other` albo `bank === null`;
- typ `AccountIdentityFields` bez zmian (`mark_url` zostaje dla `AccountIdentityBlock`).
- Testy (najpierw czerwone): gotówka zwraca „zł”; `mbank` zwraca „mB”, `pko_bp` „PKO”; `other` i bank `null` zwracają `null`; każdy bank z `bankOptions` poza `other` ma skrót o długości od 1 do 3 znaków.

#### 2. Styl `mark-label`

**File**: `resources/css/app.css`

**Intent**: Nazwany styl 9 px Mono, bo literał `text-[9px]` w szablonie łamie `AccountsHardcodedUiTest` i `.ai/rules/css.md`.

**Contract**: `@utility mark-label` obok `meta` (`:264`), z `font-mono`, rozmiarem 9 px przy interlinii 1 i `font-semibold`. Bez nowego tokena koloru.

#### 3. Komponent kafelka

**File**: `resources/js/components/AccountMarkCompact.vue`

**Intent**: Kafelek według `ht-mark`: tło `muted`, `rounded-sm`, bez obwódki, skrót tekstem albo `Building2`, cały kafelek z `aria-hidden="true"`.

**Contract**: propsy `{ type: AccountTypeValue; bank: AccountBank | null; size?: 'sm' | 'md' }` (domyślnie `sm`). `size-5` dla `sm`, `size-7` dla `md`, `rounded-sm` w obu. Tekst skrótu w `mark-label text-foreground`. Gdy `accountShortMark` zwróci `null`, w kafelku jest `Building2` w `text-muted-foreground`. Bez `<img>`, `Coins`, `name` i `mark_url`.

#### 4. Konsumenci

**File**: `resources/js/pages/Accounts.vue`, `resources/js/components/AccountBankSelect.vue`, `resources/js/components/AccountSelect.vue`, `resources/js/components/CompactAccountIdentity.vue`

**Intent**: Każde użycie przekazuje tylko `type`, `bank` (i `size`), żeby nie było fallthrough atrybutów (Critical Implementation Details).

**Contract**: `Accounts.vue:207-213` bez `:name` i `:mark_url`; `AccountBankSelect` bez importu `bankMarkPath`, kafelki z `type="bank" :bank="…"`; `AccountSelect.vue:64,76` z `:type` i `:bank` zamiast `v-bind`; `CompactAccountIdentity` przekazuje kafelkowi `type`, `bank` i `size`, a własne propsy zostawia (`AccountIdentityFields`), więc `OwnTransferRow` i `CapitalMovementRow` się nie zmieniają.

#### 5. Straż widoku

**File**: `tests/Unit/AccountsHardcodedUiTest.php`

**Intent**: Oczyszczony kafelek trafia pod straż Kont, żeby obwódka sprzed Atramentu i literały px nie wróciły.

**Contract**: `resources/js/components/AccountMarkCompact.vue` dopisany do `ACCOUNTS_VIEW_FILES`. Docblock nie wymienia już go wśród plików pominiętych (zostają `AccountIdentityBlock.vue`, `AccountBankSelect.vue`, `StatTile.vue`).

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą, w tym nowe testy `accountShortMark`: `npm run test`
- Typy przechodzą: `npm run types:check`
- Skan literałów przechodzi: `npm run check:ui`
- Straż Kont przechodzi z `AccountMarkCompact.vue` na liście: `vendor/bin/sail artisan test --compact tests/Unit/AccountsHardcodedUiTest.php`
- Nic w `resources/js` nie importuje już `bankMarkPath`: `grep -rn bankMarkPath resources/js` nie zwraca trafień
- Lint i formatowanie przechodzą: `npm run check` (Vite+), po zmianach PHP `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- W `/dev/ui/accounts`, `/dev/operations` i `/dev/ui/dialogs` (wybór konta, wybór banku w dialogu konta i lokaty) kafelki pokazują skrót albo „zł”, a „Inny bank” ikonę. DevTools nie pokazują na kafelku atrybutów `id`, `name` ani `mark_url`.
- Trzy znaki („PKO”, „BNP”) mieszczą się w kafelku 20 px bez przycięcia.

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie wyglądu, zanim przejdziesz dalej.

---

## Faza 3: Switch w wierszu tytułu i bramka wizualna

### Overview

Switch przechodzi do wiersza tytułu, sink dostaje sekcję znaków banków i zaktualizowaną macierz stanów, a zmianę zamykają zrzuty w obu motywach.

### Changes Required:

#### 1. Wiersz tytułu Kont

**File**: `resources/js/pages/Accounts.vue`

**Intent**: Przenieść markup Switcha z `:142-157` do akcji wiersza tytułu, przed „Dodaj konto”, z tym samym warunkiem, `id`, `v-model` i `:disabled`.

**Contract**: prawa strona wiersza tytułu to jeden kontener akcji `flex flex-wrap items-center gap-3`, w nim [grupa `flex items-center gap-2`: `Switch` + `Label` z `v-if` warunku] i `Button` „Dodaj konto”. Osobny rząd pod `StatPanel` znika. Logika w `<script>` bez zmian.

#### 2. Kitchen sink

**File**: `resources/js/pages/dev/AccountsKitchenSink.vue`

**Intent**: Pokazać każdy znak banku w obu rozmiarach i zaktualizować opis stanów, żeby wygląd skrótu i wiersza tytułu dało się ocenić w jednym miejscu.

**Contract**: (a) nowa sekcja `data-section="bank-marks"` „Znaki banków”: dla każdego `bankOptions`, gotówki i banku `null` kafelek `md` i `sm` z nazwą (`bankLabel`); (b) `stateMatrix`: wiersz `default` mówi, że Switch stoi w wierszu tytułu przed „Dodaj konto” i zawija się razem z nim przy 390 px, a `focus-visible` wymienia kolejność Tab: Switch, potem „Dodaj konto”. Fiksury `accounts-fixtures.ts` bez zmian (pole `mark_url` zostaje w typie `AccountRow`).

#### 3. Zrzuty

**File**: `context/changes/accounts-design-system-page/screenshots/`

**Intent**: Bramka wizualna z `/10x-ui`: widok i sink w obu szerokościach i motywach.

**Contract**: zrzuty `/dev/ui/accounts` (scenariusz z zarchiwizowanymi kontami i sekcja „Znaki banków”) przy 390 i 1280 px, w jasnym i ciemnym motywie, zrobione przez `playwright-cli` albo podgląd z `context/foundation/agents/worktree-preview.md`.

### Success Criteria:

#### Automated Verification:

- Pełna bramka przechodzi: `vendor/bin/sail composer ci:check` (w worktree wariant `docker run … ci:check` z `AGENTS.md`) oraz `vendor/bin/sloppy ci` na hoście
- Test sinka przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/AccountsKitchenSinkTest.php`
- E2E edycji konta przechodzi bez zmian: `npx playwright test tests/e2e/account-edit-keeps-bank.spec.ts`
- Zrzuty 390/1280 × jasny/ciemny są w `context/changes/accounts-design-system-page/screenshots/`

#### Manual Verification:

- Przy 1280 px Switch z etykietą stoi w wierszu tytułu przed „Dodaj konto”; przy 390 px grupa akcji zawija się pod tytuł jako całość, a Switch nie odrywa się od etykiety
- Bez zarchiwizowanych kont (scenariusz empty-new-user i konto bez archiwum) Switcha nie ma; po włączeniu filtra i zarchiwizowaniu wszystkich kont dalej jest widoczny
- Przełączenie Switcha w działającej aplikacji pokazuje i ukrywa zarchiwizowane konta, a w trakcie wizyty Switch jest wyłączony
- Skróty są czytelne w obu motywach, a kafelek nie ma obwódki

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie zrzutów.

---

## Testing Strategy

### Unit Tests:

- `account-identity.test.ts`: `accountShortMark` dla gotówki, dwóch banków ze skrótem, `other`, banku `null` i dla całej listy `bankOptions` (długość od 1 do 3 znaków).
- `AccountsHardcodedUiTest.php`: `AccountMarkCompact.vue` bez literałów i bez stylów sprzed Atramentu.

### Integration Tests:

- Payload Inertii się nie zmienia, więc istniejące `assertInertia` z `mark_url` w `AccountTest`, `AccountPickerTest`, `OperationsIdentityTest`, `TermDepositTest` i `DashboardTest` zostają i muszą przejść bez edycji asercji (część `composer ci:check`).
- E2E `account-edit-keeps-bank.spec.ts` sprawdza podpis z nazwą banku, nie kafelek, i musi przejść bez zmian.

### Manual Testing Steps:

1. `/dev/ui/accounts` przy 1280 i 390 px, jasny i ciemny motyw: wiersz tytułu, tabela, sekcja „Znaki banków”.
2. `/dev/ui/dialogs`: dialog konta (wybór banku) i dialog lokaty (wybór banku), dialog wydatku (wybór konta).
3. `/dev/operations`: wiersze przelewów własnych i ruchów kapitału.
4. Prawdziwy widok Kont: przełączenie Switcha, nawigacja Tab przez Switch i „Dodaj konto”.

## Performance Considerations

Brak. Kafelek przestaje ładować obrazy w tabeli Kont, listach wyboru i wierszach Operacji.

## Migration Notes

Brak migracji danych. `public/marks/*` i `mark_url` zostają dla `AccountIdentityBlock`.

## References

- Research: `context/changes/accounts-design-system-page/research.md`
- Decyzja nadrzędna: `context/foundation/adr/0045-dashboard-accounts-and-cancel-follow-design-system-pages.md` (akapit „Accounts”)
- Poprzednia zmiana Kont: `context/archive/2026-11-06-accounts-atrament-ui/plan.md`, ADR-0044
- Wzorzec stylu nazwanego: `resources/css/app.css:264` (`meta`)
- Wzorzec kafelka tożsamości: `resources/js/components/CategoryChip.vue`
- Design system (podgląd, nie źródło wartości): https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, strona `PageAccounts`, `ht-mark`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0047 istnieje i jest jedynym plikiem `0047-*` w `context/foundation/adr/` — c33e6d4
- [x] 1.2 `php artisan boost:index-rules` kończy się bez błędu, a `git diff` pokazuje tylko zamierzone zmiany w regułach — c33e6d4

#### Manual

- [x] 1.3 ADR-0047 zawiera tabelę dziewięciu skrótów, gotówkę, `other`, regułę 3 znaków, zakres komponentu, wariant `sm`, warunek Switcha i odrzucone opcje — c33e6d4
- [x] 1.4 `design-system.md` i `.ai/rules/js.md` nie przeczą ADR-0047 — c33e6d4

### Phase 2: Skrót banku w `AccountMarkCompact`

#### Automated

- [x] 2.1 Testy JS przechodzą, w tym nowe testy `accountShortMark`: `npm run test` — 6de2267
- [x] 2.2 Typy przechodzą: `npm run types:check` — 6de2267
- [x] 2.3 Skan literałów przechodzi: `npm run check:ui` — 6de2267
- [x] 2.4 Straż Kont przechodzi z `AccountMarkCompact.vue` na liście — 6de2267
- [x] 2.5 Nic w `resources/js` nie importuje już `bankMarkPath` — 6de2267
- [x] 2.6 Lint i formatowanie przechodzą — 6de2267

#### Manual

- [x] 2.7 Kafelki w sinkach Kont, Operacji i dialogów pokazują skrót, „zł” albo ikonę i nie mają atrybutów `id`, `name` ani `mark_url` — 6de2267
- [x] 2.8 Trzy znaki mieszczą się w kafelku 20 px bez przycięcia — 6de2267

### Phase 3: Switch w wierszu tytułu i bramka wizualna

#### Automated

- [x] 3.1 Pełna bramka przechodzi: `composer ci:check` oraz `vendor/bin/sloppy ci` — 53c374e
- [x] 3.2 Test sinka przechodzi: `tests/Feature/AccountsKitchenSinkTest.php` — 53c374e
- [x] 3.3 E2E edycji konta przechodzi bez zmian — 53c374e
- [x] 3.4 Zrzuty 390/1280 × jasny/ciemny są w `screenshots/` — 53c374e

#### Manual

- [x] 3.5 Switch stoi w wierszu tytułu przed „Dodaj konto” i zawija się z nim jako całość przy 390 px — 53c374e
- [x] 3.6 Switch jest widoczny tylko przy zarchiwizowanych kontach albo włączonym filtrze — 53c374e
- [x] 3.7 Przełączenie Switcha pokazuje i ukrywa zarchiwizowane konta, a w trakcie wizyty Switch jest wyłączony — 53c374e
- [x] 3.8 Skróty są czytelne w obu motywach, a kafelek nie ma obwódki — 53c374e
