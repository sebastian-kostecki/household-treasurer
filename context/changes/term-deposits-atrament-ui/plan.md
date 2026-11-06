# Lokaty w systemie „C · Atrament i morski” — plan implementacji

## Overview

Widok Lokaty (`resources/js/pages/TermDeposits.vue`) i dialog lokaty (`TermDepositFormDialog.vue`) przechodzą na kartę `PageTermDeposits` design systemu „C · Atrament i morski”, jak już Konta (ADR-0044, ADR-0045, ADR-0047). W widoku pojawiają się:

- wiersz tytułu z `page-title`, opisem, `Switch` „Pokaż zarchiwizowane” i „Dodaj lokatę”;
- jeden `StatPanel` z miarami „Suma lokat” (z podpisem „W tym zarchiwizowane: …”) i „Łączne środki”;
- tabela Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje na siatce `.term-deposits-grid`, z kafelkiem `AccountMarkCompact` ze skrótem banku i „zł” w każdym saldzie.

Dialog dostaje pełny kontrakt ADR-0040 z „Anuluj” `ghost` (ADR-0045). `StatTile` i `.holdings-list-row` tracą ostatniego użytkownika i znikają. Decyzje i odejścia od karty zapisuje najpierw ADR-0051.

## Current State Analysis

Pełny audyt jest w `context/changes/term-deposits-atrament-ui/research.md` (zarzuty C1–C5 i „Aktualizacja 2026-11-06”). Najważniejsze:

- Miara to jedna karta `StatTile` z `text-3xl` (`TermDeposits.vue:110-136`, `StatTile.vue:29`) ze slotem `footer` „W tym zarchiwizowane: …”, pokazywanym, gdy `archived_term_deposits_total_minor_units !== 0` (`:115`). Kontroler nie wysyła Łącznych środków (`TermDepositController.php:38-44`).
- Lista to `ListSurface` z wierszami `.holdings-list-row` (`app.css:873`, `flex-wrap`, `border-border/60`) bez nagłówków kolumn. Data zakończenia jest podpisem pod nazwą (`TermDeposits.vue:208-214`), znak banku to logo z `AccountIdentityBlock` i `mark_url` (`:192-198`), a akcje na telefonie spadają do osobnej linii (`:225`).
- Tytuł ma `text-lg font-semibold` (`:104`), opis „Lokaty terminowe.” siedzi w podpisie listy (`:185`). „Pokaż zarchiwizowane” to `Checkbox` pod miarą (`:138-153`), Badge `secondary` (`:203`), drugi przycisk pustego stanu `secondary` (`:167`).
- `TermDepositFormDialog.vue:162-246` ma pięć ręcznych bloków `div.grid > Label + Input + InputError` (32 px). Podpowiedź daty zakończenia nie jest związana `aria-describedby` (`:212`), saldo otwarcia nie ma „zł”, „Anuluj” jest `secondary` (`:258`), a `<Label for="account-bank">` (`:178`) działa tylko dzięki domyślnemu `id` `AccountBankSelect`. Spinner i blokada zamykania już są (`:118-130,135,275`). Dialog nie ma propsa `form`, więc `/dev/ui/dialogs` nie może pokazać jego błędów ani zapisu w toku.
- Ostrzeżenie o ujemnym saldzie to `Alert` `warning` (`:248-252`), zgodnie z `design-system.md:78` i `js.md` („Non-blocking warnings … negative balance”). Zostaje.

Weryfikacja podczas planowania dołożyła:

- Karta `PageTermDeposits` (artefakt, `project/components/PageTermDeposits/preview.html`) ma kolumny `minmax(0,1fr) 150px 150px 150px 96px`, nagłówki Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje (trzy ostatnie do prawej), plakietkę `neutral` po nazwie, datę `DD.MM.RRRR` w mono do prawej, bank jako zwykły tekst kolumny. „Suma lokat” jest w karcie bez tonu, a „Łączne środki” w `success`, z podpisem „konta i lokaty”.
- Karta nie ma układu mobilnego (podgląd ma stałe 1280 px, `bundle.css` bez `@media`) ani lokaty bez daty zakończenia. Oba przypadki rozstrzygnął użytkownik podczas planowania (Key Decisions w briefie).
- Karta podaje „Anuluj” `outline`. ADR-0045 jest nowszy i ustala `ghost`, więc obowiązuje `ghost` (ADR-0047:27: gdzie artefakt i nowszy ADR się różnią, wygrywa ADR).
- `AccountIdentityBlock` wymaga `mark_url` (`AccountIdentityBlock.vue:7`, `AccountIdentityFields`), więc `mark_url` może zniknąć z payloadu Lokat dopiero razem z przejściem widoku na `AccountMarkCompact`.
- `StatTile` wymieniają: `scripts/check-ui-literals.mjs:19`, `design-system.md:83`, `.ai/rules/js.md` („Before a new component check StatTile…”), docblocki `tests/Unit/AccountsHardcodedUiTest.php:4-8` i `tests/Unit/DashboardHardcodedUiTest.php:6-7`. `.holdings-list-row` ma tylko `app.css:872-875` i `TermDeposits.vue:189`.

## Desired End State

Widok Lokaty od góry:

- **Wiersz tytułu:** po lewej `h1.page-title` „Lokaty”, pod nim `text-muted-foreground text-sm` „Lokaty terminowe. Data zakończenia jest tylko informacyjna.”. Po prawej `Switch` + `Label` „Pokaż zarchiwizowane” (tylko gdy `archived_term_deposits_count > 0 || show_archived`, wyłączony w trakcie wizyty) i „Dodaj lokatę” (`primary`). Wzorzec: `Accounts.vue:128-156`.
- **Panel miar:** `StatPanel` z dwiema miarami:
    - `id: 'term-deposits-total'`: „Suma lokat” w `kpi-lg` (wszystkie lokaty, także zarchiwizowane, FR-REP-10). W slocie `footer-term-deposits-total` jest „W tym zarchiwizowane: <kwota>”, gdy suma zarchiwizowanych ≠ 0, z kwotą w `destructive` tylko przy wartości ujemnej;
    - `id: 'total-funds'`: „Łączne środki”, podpis „konta i lokaty”.

    Obie wartości przez `formatMoney` i `amountToneClass`, jak w Kontach.
- **Tabela:** jeden `ListSurface flush` z wierszem nagłówka `table-header` (Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje), widocznym od `md`. Wiersze `.term-deposits-grid`:
    - Nazwa: `AccountMarkCompact type="bank" :bank size="md"` (skrót z ADR-0047), `font-medium` nazwa, Badge `neutral` „Zarchiwizowane” przy zarchiwizowanej;
    - Bank: `bankLabel(t.accounts, bank)`;
    - Data zakończenia: `formatDate` w mono (`tabular-nums`), do prawej od `md`. Bez daty jest wyciszone „—” (`aria-hidden`) i tekst `sr-only` „Bez daty zakończenia”;
    - Saldo bieżące: `formatMoney` z „zł” w stylu `amount`, do prawej, ton jak dziś (`balanceClass`);
    - Akcje: te same `IconAction` i `ConfirmActionDialog` co dziś.
- **Telefon (poniżej `md`):** ten sam markup w dwóch liniach. W pierwszej kafelek z nazwą i saldo, w drugiej bank, data i akcje (`'name name balance' / 'bank end actions'`). Bez przewijania w poziomie na 390 px.
- **Wiersz zarchiwizowany:** cały w `muted-foreground`, bez `opacity` i bez tonu. Wyjątek: ujemne saldo zostaje `text-destructive` (FR-TD-08).
- **Stany puste:** jak dziś. W stanie „wszystkie zarchiwizowane” drugi przycisk „Dodaj lokatę” ma `outline`.
- **Dialog lokaty:**
    - pola przez `FormField` (34 px): Nazwa, Bank (`AccountBankSelect` w slocie z `id="term-deposit-bank"`), Data otwarcia (tylko tworzenie), Data zakończenia z `hint` związanym `aria-describedby`, Saldo otwarcia w `FormField money` z „zł”;
    - `Alert` `warning` o ujemnym saldzie bez zmian;
    - „Anuluj” `ghost`, spinner i blokada zamykania jak dziś;
    - opcjonalny prop `form` jak w `AccountFormDialog`.
- **Martwe elementy usunięte:** `StatTile.vue`, `.holdings-list-row` i `mark_url` w payloadzie Lokat znikają razem z odwołaniami w strażnikach i dokumentach. `AccountIdentityBlock` i `mark_url` Dashboardu zostają (ADR-0047:23).
- **Straże i podglądy:**
    - `TermDepositsHardcodedUiTest` blokuje style sprzed Atramentu w plikach Lokat;
    - `/dev/ui/term-deposits` pokazuje lokatę bez daty i długie nazwy;
    - `/dev/ui/dialogs` pokazuje dialog lokaty w stanach nowa, edycja, ujemne saldo, błędy i zapis w toku.

Weryfikacja: testy faz 2–4, pełny `composer ci:check` w obrazie Sail, `vendor/bin/sloppy ci` na hoście oraz zrzuty z podglądu worktree przy 390 i 1280 px w obu motywach.

### Key Discoveries:

- `StatPanel` przyjmuje 2–4 miary `{id?, label, value, caption?, toneClass?}` i ma sloty `label-extra-<id>` i `footer-<id>` (`StatPanel.vue:8-16,46,58`). Kolorowa kwota w podpisie potrzebuje slotu, bo `caption` jest tekstem.
- Siatka Kont jest wzorem: `.accounts-grid`, `.accounts-header-row`, `.accounts-row`, `.accounts-row-archived`, `.accounts-cell-*` (`app.css:692-745`).
- `TotalFunds::forUser` (`app/Support/TotalFunds.php`) to definicja Łącznych środków. Konta dostały ją w commicie `9e9b7ad` (kontroler, typ, fixtures, `AccountTest.php:179-207`).
- `FormField` daje `id`, `describedBy`, `invalid` i `controlClass` w slocie, a wariant `money` dokleja „zł” (`FormField.vue:6-13,44-55`). `AccountBankSelect` przyjmuje `id` z `FormField` (`AccountBankSelect.vue:22-27`, użycie `AccountFormDialog.vue:210-223`).
- Prop `form?: Pick<FormBinding, 'action' | 'method'>` i jego użycie w `formBinding`: `AccountFormDialog.vue:41,67-70`. Stany sinka dialogów to lista `states` + `labels` (`DialogsKitchenSink.vue:36-110`) i komponenty na końcu (`:501-528`). `validateForm` i `savingForm` kierują do `DialogsKitchenSinkController::validate`.
- `AccountsHardcodedUiTest.php:10-31` to wzór nowej straży (lista plików + wzorce + komunikat `plik:linia: rodzaj: dopasowanie`).
- Asercje propsów strony: `tests/Feature/TermDepositTest.php:104-140` (tu `mark_url` w `:130,135`), `:144-154`, `:350-374`; `tests/Feature/ArchiveTermDepositTest.php:110-220`.

## What We're NOT Doing

- Oprocentowanie („3,80%” w karcie): brak pola w modelu. To wartość podglądu; odejście zapisuje ADR-0051.
- Notka stopki karty („Ujemne saldo zarchiwizowanej lokaty zostaje czerwone…”): objaśnienie dla czytelnika design systemu, nie tekst UI. Odejście zapisuje ADR-0051.
- Blok `FormPreview` zamiast `Alert` `warning` w dialogu lokaty: dialog nie ma odczytu „przed → po”, a `design-system.md:78` i `js.md` zostawiają ujemne saldo przy `Alert`.
- „Anuluj” `ghost` w pozostałych dialogach (Konto, Kategoria, operacje): zmiana wdrażająca ADR-0045.
- Zmiany `AccountIdentityBlock`, `mark_url` i `public/marks/*` Dashboardu (ADR-0047:23) oraz `AccountMarkCompact`.
- Dług F1 w `AccountFormDialog` (ref i watch przy ukrytym polu) i wspólny composable przełącznika archiwum.
- Zmiany definicji „Suma lokat” (zostaje z zarchiwizowanymi, FR-REP-10) i warunku pokazywania „W tym zarchiwizowane” (≠ 0, jak dziś).
- Zmiany treści dialogów i potwierdzeń oraz flag `can_delete` / `can_archive` / `can_restore`.
- Test E2E Lokat (`tests/e2e/` go nie ma; ta zmiana nie dokłada ryzyka wymagającego przeglądarki poza zrzutami bramki).

## Implementation Approach

Ta sama kolejność co w zmianach Kont (`context/archive/2026-11-06-accounts-atrament-ui/plan.md`):

1. dokumenty przed kodem (AGENTS.md: zmiana decyzji to najpierw ADR);
2. kontrakt propsów z testami;
3. widok;
4. dialog;
5. macierz stanów i bramka wizualna.

Każda faza kończy się zielonymi `npm run check`, `npm run types:check` i testami, których dotyka, więc stan pośredni da się wdrożyć. Nowy układ trafia do nazwanych klas w `@layer components` w `app.css` (`.ai/rules/js.md`, „Views take values from app.css”), nie do utility ani wartości w szablonie.

## Critical Implementation Details

- **Kolejność usunięcia `mark_url`:** `TermDepositRow` dziedziczy pole przez `AccountIdentityBlock` (`AccountIdentityFields` wymaga `mark_url`), więc usunięcie pola z typu przed fazą 3 łamie `types:check`. Faza 2 tylko dodaje `total_funds_minor_units`. `mark_url` znika w fazie 3 w jednym commicie: kontroler (payload i docblock), typ, fixtures, asercje `TermDepositTest.php:130,135` i widok bez `AccountIdentityBlock` (`.ai/rules/inertia-props.md`).
- **Siatka na telefonie:** trzy kolumny poniżej `md` (`auto minmax(0,1fr) auto`, obszary `'name name balance' / 'bank end actions'`), pięć od `md` (`minmax(0,1fr) 150px 150px 150px 96px`, `'name bank end balance actions'`). Data jest poniżej `md` do lewej, od `md` do prawej. Komórka nazwy ma `min-w-0`, a nazwa się zawija, bez ucinania, żeby długa nazwa nie wypychała salda.

## Faza 1: Decyzje i dokumenty

### Overview

Zapisać w ADR-0051 układ Lokat według `PageTermDeposits` i odejścia od karty, uaktualnić `design-system.md` i `js.md`. Bez zmian w kodzie aplikacji.

### Changes Required:

#### 1. ADR-0051

**File**: `context/foundation/adr/0051-term-deposits-follow-design-system-page.md` (nowy)

**Intent**: Zapisać, że Lokaty idą za stroną `PageTermDeposits` (ADR-0045), w stylu ADR-0047 (krótkie akapity, po angielsku). Zapisać też odejścia, których żaden nowszy ADR nie rozstrzyga (`design-system.md:3`).

**Contract**: ADR mówi, że:

- tabela Lokat ma kolumny Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje z wierszem nagłówka `table-header` na siatce `.term-deposits-grid`. Saldo w każdej komórce idzie przez `formatMoney` z „zł”, bez stopki „Kwoty w zł”. To trzeci wyjątek od ADR-0037 obok Operacji (ADR-0039) i Kont (ADR-0044); powód jak w ADR-0044: jedna kolumna kwot, brak wiersza sumy, sumy w panelu miar;
- poniżej `md` wiersz ma dwie linie (nazwa i saldo; bank, data i akcje), bo karta nie ma układu mobilnego;
- lokata bez daty zakończenia ma w kolumnie wyciszone „—” z tekstem dla czytnika ekranu „Bez daty zakończenia”;
- panel miar to `StatPanel` z „Suma lokat” (podpis „W tym zarchiwizowane: …” ze slotem, kwota ujemna w `destructive`) i „Łączne środki” („konta i lokaty”). Obie miary mają ton z `amountToneClass` jak w Kontach, choć karta nie toni „Suma lokat”;
- `Switch` stoi w wierszu tytułu z warunkiem ADR-0047 (rozciągniętym na Lokaty), a kafelek banku to `AccountMarkCompact` ze skrótem (ADR-0047) zamiast `AccountIdentityBlock`;
- dialog lokaty przyjmuje ADR-0040 z „Anuluj” `ghost` (ADR-0045, nie `outline` z karty), a ostrzeżenie o ujemnym saldzie zostaje `Alert` `warning`;
- odejścia od karty: bez oprocentowania (brak pola) i bez notki w stopce panelu;
- `StatTile` i `.holdings-list-row` są usunięte, bo nie mają już użytkowników.

Odrzucone alternatywy: stopka „Kwoty w zł”, `Switch` pod panelem (ADR-0044), ukrycie kolumny Bank na telefonie, pusta komórka daty, `FormPreview` w dialogu lokaty. ADR nie dotyka `glossary.md` ani terminów _Avoid_. Zastępuje w części ADR-0037 (jednostka w tabeli Lokat).

#### 2. Design system

**File**: `context/foundation/design-system.md`

**Intent**: Opisać Lokaty jako widok Atramentu i zdjąć polecenie `StatTile`.

**Contract**:

- ADR-0051 na liście ADR (`:3`), z dopiskiem, co zastępuje.
- „Listy i tabele” (`:18`): siatka `.term-deposits-grid` z wierszem nagłówka (Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje), dwie linie na telefonie, „—” przy braku daty, `Switch` w wierszu tytułu z warunkiem.
- „Kwoty”: tabela Lokat ma „zł” w każdym saldzie i nie ma stopki (ADR-0051), obok Kont i Operacji.
- „Gotowe elementy” (`:83`): `StatTile` znika z listy. `StatPanel` używają także Lokaty („Suma lokat”, „Łączne środki”).
- Akapit o kitchen sinkach: `/dev/ui/term-deposits` i stany dialogu lokaty w `/dev/ui/dialogs`.

#### 3. Reguły

**File**: `.ai/rules/js.md`

**Intent**: Uaktualnić reguły, które dziś wymieniają `StatTile` albo wąskie wyjątki.

**Contract**:

- „Amounts go through formatMoney”: wyjątek obejmuje też tabelę Lokat (ADR-0051).
- „Reuse surface building blocks”: zdanie o `AccountIdentityBlock` mówi, że logo zostaje tylko na Dashboardzie, a Lokaty mają już `AccountMarkCompact`.
- „Responsive lists”: `.term-deposits-grid` wśród klas siatek.
- „Tokens and shared components”: zdanie „Before a new component check StatTile, …” bez `StatTile`. Nowe zdanie o `tests/Unit/TermDepositsHardcodedUiTest.php` obok zdania o Kontach, z nowymi stanami w `/dev/ui/term-deposits`.
- „Views take values from app.css”: `.term-deposits-grid` na liście klas.

Indeks reguł się nie zmienia (bez zmian frontmatter `paths:`), więc `php artisan boost:index-rules` nie jest potrzebny.

### Success Criteria:

#### Automated Verification:

- ADR-0051 istnieje: `ls context/foundation/adr/0051-*.md`
- Formatowanie dokumentów przechodzi: `npm run check`

#### Manual Verification:

- ADR-0051, `design-system.md` i `js.md` opisują decyzje z tabeli „Key Decisions” briefu, bez sprzeczności między sobą

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Kontrakt propsów

### Overview

Dodać `total_funds_minor_units` do strony Lokat po obu stronach kontraktu. Widok jeszcze go nie renderuje, a `mark_url` zostaje do fazy 3 (Critical Implementation Details).

### Changes Required:

#### 1. Props strony Lokat

**File**: `app/Http/Controllers/TermDepositController.php`, `resources/js/types/term-deposits.ts`, `resources/js/pages/dev/term-deposits-fixtures.ts`

**Intent**: Dać stronie drugą miarę z serwera (C1) z jednej definicji Łącznych środków.

**Contract**:

- `index()` wysyła `'total_funds_minor_units' => TotalFunds::forUser($request->user())`.
- `TermDepositsPageProps.total_funds_minor_units: number`.
- Każdy z 7 scenariuszy fixtures (`term-deposits-fixtures.ts:75-173`) dostaje wartość większą od sumy lokat o przykładowe saldo kont (`empty-new-user`: 0 albo samo saldo kont), tak żeby obie miary się różniły.

#### 2. Testy

**File**: `tests/Feature/TermDepositTest.php`, `tests/Feature/ArchiveTermDepositTest.php`

**Intent**: Przypiąć kształt propsa i to, że liczy konta i lokaty (`.ai/rules/inertia-props.md`).

**Contract**:

- `TermDepositTest` (`:104-140`): setup dostaje konto z saldem, a `assertInertia` sprawdza `total_funds_minor_units` równe saldu konta plus sumie lokat.
- `ArchiveTermDepositTest` (`:147-166`): `total_funds_minor_units` obejmuje saldo zarchiwizowanej lokaty i nie zależy od `show_archived`.
- Testy na PostgreSQL (sumy w SQL).

### Success Criteria:

#### Automated Verification:

- Testy na PostgreSQL w obrazie Sail: `docker run … artisan test --compact tests/Feature/TermDepositTest.php tests/Feature/ArchiveTermDepositTest.php tests/Feature/TotalFundsTest.php tests/Feature/TermDepositsKitchenSinkTest.php`
- Typy po buildzie: `npm run build && npm run types:check`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

**Implementation Note**: Faza nie ma kroków ręcznych; po zielonej weryfikacji przejdź do fazy 3.

---

## Faza 3: Widok Lokaty

### Overview

Siatka tabeli, wiersz tytułu ze `Switch`, `StatPanel`, tabela z kafelkiem banku, usunięcie `mark_url`, `StatTile` i `.holdings-list-row` oraz straż literałów Lokat.

### Changes Required:

#### 1. Siatka tabeli

**File**: `resources/css/app.css`

**Intent**: Dać tabeli Lokat nazwany układ (`css.md`, `js.md`) na wzór `.accounts-grid` i usunąć martwy `.holdings-list-row`.

**Contract**: w `@layer components`, zaraz po klasach Kont:

- `.term-deposits-grid` z `grid-template-areas`:
    - poniżej `md` kolumny `auto minmax(0,1fr) auto` i obszary `'name name balance' 'bank end actions'`;
    - od `md` kolumny `minmax(0,1fr) 150px 150px 150px 96px` z karty i obszar `'name bank end balance actions'`;
- `.term-deposits-header-row` w `table-header`, `border-border-soft`, ukryty poniżej `md`;
- `.term-deposits-row`: `min-h-row`, separator `border-border-soft`, hover `secondary`;
- `.term-deposits-row-archived`: `muted-foreground` dla całego wiersza;
- komórki `.term-deposits-cell-{name,bank,end,balance,actions}`. `end` jest do lewej poniżej `md` i do prawej od `md`, `balance` i `actions` zawsze do prawej, a `name` ma `min-w-0`;
- `.holdings-list-row` i jego komentarz (`:872-875`) znikają.

#### 2. Widok i tłumaczenia

**File**: `resources/js/pages/TermDeposits.vue`, `lang/pl/ui.php`

**Intent**: Usunąć zarzuty C1–C3 zgodnie z ADR-0051.

**Contract**:

- Wiersz tytułu jak `Accounts.vue:128-156`: `page-title`, opis `t.term_deposits.list_description`, `Switch` + `Label` (`id="show-archived-term-deposits"`, ten sam `v-model`, `:disabled` i `v-if` co dziś) przed „Dodaj lokatę”. `Checkbox` znika z importów.
- `StatPanel` z miarami opisanymi w Desired End State. „W tym zarchiwizowane” w slocie `footer-term-deposits-total` w `meta text-muted-foreground`, z kwotą przez `archivedAmountClass`.
- `ListSurface flush` z wierszem `.term-deposits-header-row` (`t.term_deposits.name`, `bank`, `end_date`, `current_balance`, nowy `actions`) i wierszami `.term-deposits-grid .term-deposits-row` (z `.term-deposits-row-archived` przy zarchiwizowanej). Komórki jak w Desired End State; bank przez `bankLabel` z `@/lib/account-identity`.
- Stan „wszystkie zarchiwizowane”: drugi przycisk `outline`.
- `StatTile`, `AccountIdentityBlock`, `Checkbox` i Badge `secondary` znikają z widoku.
- `lang/pl/ui.php`, przestrzeń `term_deposits.*` (bez pożyczania `accounts.*`, lekcja F1 z `accounts-ui-contract`):
    - `list_description` → „Lokaty terminowe. Data zakończenia jest tylko informacyjna.” (najpierw `grep`, czy klucza nie używa inny widok);
    - nowe: `actions` „Akcje”, `total_funds` „Łączne środki”, `total_funds_caption` „konta i lokaty”, `no_end_date` „Bez daty zakończenia”.

#### 3. Usunięcie `mark_url` z payloadu Lokat

**File**: `app/Http/Controllers/TermDepositController.php`, `resources/js/types/term-deposits.ts`, `resources/js/pages/dev/term-deposits-fixtures.ts`, `tests/Feature/TermDepositTest.php`

**Intent**: Pole czytał tylko `AccountIdentityBlock` w widoku. Po przejściu na `AccountMarkCompact` byłoby martwym propsem (research, „Aktualizacja” p. 2).

**Contract**:

- `termDepositPayload` i jego docblock bez `mark_url`. Import `AccountIdentity` znika, jeśli nie ma innego użycia.
- `TermDepositRow` bez `mark_url`; fixtures bez `markUrls`.
- `TermDepositTest.php:130,135`: asercje `mark_url` zamienione na `->missing('termDeposits.0.mark_url')`. `bank` zostaje.
- Ten sam commit co widok (`.ai/rules/inertia-props.md`).

#### 4. Usunięcie `StatTile`

**File**: `resources/js/components/StatTile.vue` (usunięty), `scripts/check-ui-literals.mjs`, `tests/Unit/AccountsHardcodedUiTest.php`, `tests/Unit/DashboardHardcodedUiTest.php`

**Intent**: Zamknąć C5, żeby następny agent nie użył martwego kafelka.

**Contract**:

- `grep -rn StatTile resources` po zmianie zwraca pusto.
- Wpis `StatTile.vue` znika z listy `FILES` (`check-ui-literals.mjs:19`).
- Docblocki obu testów straży nie wymieniają `StatTile`; `AccountsHardcodedUiTest` uzasadnia pominięcie `AccountIdentityBlock` i `AccountBankSelect` ich użytkownikami poza Kontami (Dashboard, dialog lokaty).

#### 5. Straż literałów Lokat

**File**: `tests/Unit/TermDepositsHardcodedUiTest.php` (nowy, `php artisan make:test --pest --unit`), `resources/js/pages/dev/TermDepositsKitchenSink.vue`, `resources/js/pages/dev/term-deposits-fixtures.ts`

**Intent**: Zatrzymać powrót typografii, ramek i przezroczystości sprzed Atramentu w plikach Lokat.

**Contract**:

- Na wzór `AccountsHardcodedUiTest.php`: te same wzorce i komunikat, własne nazwy funkcji (bez kolizji z funkcjami Kont i Dashboardu w tym samym procesie Pest).
- Lista plików: `TermDeposits.vue`, `pages/dev/TermDepositsKitchenSink.vue`, `pages/dev/term-deposits-fixtures.ts`. Faza 4 dopisuje `TermDepositFormDialog.vue`.
- Sink czyści style sprzed Atramentu (`text-xl font-semibold` w nagłówku, `border-b` bez `border-border-soft` itp.), a jego macierz stanów opisuje nowy widok (`Switch` w tytule, dwie miary, tabela).
- Scenariusz `default` dostaje jedną lokatę bez daty zakończenia, żeby „—” było widać od razu.

### Success Criteria:

#### Automated Verification:

- Statyka, typy i straże: `npm run check`, `npm run build && npm run types:check`, `npm run check:ui`
- Vitest (`ui-contract.test.ts` i inne): `npm test`
- Straże i testy Lokat w obrazie Sail: `docker run … artisan test --compact tests/Unit/TermDepositsHardcodedUiTest.php tests/Unit/AccountsHardcodedUiTest.php tests/Unit/DashboardHardcodedUiTest.php tests/Feature/TermDepositTest.php tests/Feature/ArchiveTermDepositTest.php tests/Feature/TermDepositsKitchenSinkTest.php`
- Brak starych elementów: `grep -rn "StatTile\|holdings-list-row" resources scripts tests` zwraca pusto, a `grep -n "Checkbox\|AccountIdentityBlock\|mark_url" resources/js/pages/TermDeposits.vue resources/js/types/term-deposits.ts` zwraca pusto

#### Manual Verification:

- W `/dev/ui/term-deposits` (podgląd z worktree) panel ma dwie miary, „W tym zarchiwizowane” ma czerwoną kwotę tylko w `with-archived-negative`, a tabela ma nagłówki kolumn od `md` i „zł” w saldach
- Kafelek pokazuje skrót banku (np. „PKO”, „LF”), dla „Inny” ikonę `Building2`, a kolumna Bank pełną nazwę
- Lokata bez daty ma wyciszone „—” w kolumnie Data zakończenia
- Na 390 px wiersz ma dwie linie (nazwa + saldo; bank, data i akcje), bez przewijania w poziomie
- Panele Lokat i Kont na Dashboardzie wyglądają jak przed zmianą (logo w `AccountIdentityBlock`)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Dialog lokaty

### Overview

`TermDepositFormDialog` przyjmuje pełny kontrakt ADR-0040 z „Anuluj” `ghost` (`.ai/rules/js-dialogs.md`). Treść, pola, walidacja i ostrzeżenie bez zmian.

### Changes Required:

#### 1. Dialog

**File**: `resources/js/components/TermDepositFormDialog.vue`

**Intent**: Dialog lokaty wygląda jak pozostałe dialogi formularzy i jest pierwszym z „Anuluj” `ghost` (C4).

**Contract**:

- Nazwa, Data otwarcia (tylko tworzenie), Data zakończenia i Bank przez `FormField` z dzisiejszymi `id`, `name` i błędami. Data zakończenia ma `hint` z `t.term_deposits.end_date_hint`, a ręczny `<p>` znika. Bank: `FormField id="term-deposit-bank"`, a w slocie `AccountBankSelect` z `:id`, `:aria-describedby` i `:invalid` jak `AccountFormDialog.vue:210-223`.
- Saldo otwarcia: `FormField money` z `id="term-deposit-opening-balance"`. Błąd to `openingBalanceInvalid ? t.term_deposits.opening_balance_invalid : errors.opening_balance_minor_units`, a ukryte pole i jego `computed` (`:47-49,234-238`, decyzja F1) zostają bez zmian.
- `Alert` `warning` z `showNegativeBalanceWarning` bez zmian, pod polami.
- Stopka: „Anuluj” `variant="ghost"`. `:disabled`, czyszczenie błędów, spinner i blokada zamykania jak dziś.
- Nowy opcjonalny prop `form?: Pick<FormBinding, 'action' | 'method'>`; gdy jest, `formBinding` zwraca go (`AccountFormDialog.vue:41,67-70`).
- Bez `Label`, `Input` w ręcznych blokach `div.grid` i bez `variant="secondary"`.

#### 2. Kitchen sink dialogów i straż

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`, `tests/Feature/DialogsKitchenSinkTest.php`, `tests/Unit/TermDepositsHardcodedUiTest.php`, `resources/js/pages/dev/TermDepositsKitchenSink.vue`

**Intent**: Stany dialogu lokaty są do obejrzenia przed przeglądem (`js-dialogs.md`: „Add new dialog states to … `/dev/ui/dialogs`”).

**Contract**:

- Nowe stany w `states` i `labels` po stanach konta: `term-deposit-new` („Lokata: nowa”), `term-deposit-edit` („Lokata: edycja”), `term-deposit-negative` („Lokata: ujemne saldo”, edycja lokaty, której saldo bieżące po zmianie salda otwarcia jest ujemne), `term-deposit-errors` (`validateForm`), `term-deposit-saving` (`savingForm`). Lokata do edycji to lokalny obiekt `TermDepositFormTermDeposit`, jak `account` (`:116`).
- `DialogsKitchenSinkTest` dostaje asercję nowych stanów, jeśli asertuje listę.
- `TermDepositsHardcodedUiTest` obejmuje `TermDepositFormDialog.vue`.
- Sekcja `error-form` w `TermDepositsKitchenSink.vue` odsyła do `/dev/ui/dialogs?open=term-deposit-errors` albo zostaje z przyciskiem `outline` zamiast `secondary`.

### Success Criteria:

#### Automated Verification:

- Statyka, typy i straże: `npm run check`, `npm run build && npm run types:check`, `npm run check:ui`
- Vitest: `npm test`
- Testy w obrazie Sail: `docker run … artisan test --compact tests/Unit/TermDepositsHardcodedUiTest.php tests/Feature/TermDepositTest.php tests/Feature/DialogsKitchenSinkTest.php`
- Dialog bez ręcznych pól: `grep -n "variant=\"secondary\"\|<Label\|class=\"grid gap-2\"" resources/js/components/TermDepositFormDialog.vue` zwraca pusto

#### Manual Verification:

- W podglądzie utworzenie lokaty zapisuje nazwę, bank, daty i saldo; pola mają 34 px, a saldo „zł” w polu
- Edycja otwiera dialog z wartościami lokaty i zapisuje zmiany; zmiana salda otwarcia na ujemne saldo bieżące pokazuje `Alert` `warning`
- W trakcie zapisu „Zapisz” ma spinner, a `Esc`, klik w tło i „Anuluj” (`ghost`) nie zamykają dialogu
- Błędne saldo otwarcia daje komunikat pod polem i wyłącza „Zapisz”; czytnik ekranu czyta podpowiedź daty zakończenia przy polu

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 5.

---

## Faza 5: Stany i bramka wizualna

### Overview

Dopisać brakujące stany do `/dev/ui/term-deposits`, uruchomić podgląd z worktree, zrobić zrzuty w obu motywach i przejść pełną bramkę CI.

### Changes Required:

#### 1. Scenariusze sinka

**File**: `resources/js/pages/dev/term-deposits-fixtures.ts`, `resources/js/pages/dev/TermDepositsKitchenSink.vue`, `tests/Feature/TermDepositsKitchenSinkTest.php`

**Intent**: Każda komórka macierzy 7 stanów (default, hover, focus-visible, disabled, error, empty, loading) ma miejsce przed przeglądem.

**Contract**:

- Nowy scenariusz `long-names`: długa nazwa lokaty i bank o najdłuższej etykiecie, lokata bez daty. Sprawdza zawijanie w obszarze `name` i drugą linię na 390 px.
- Tabela macierzy wymienia nowe scenariusze, `Switch` wł./wył., panel z dwiema miarami i stany dialogu w `/dev/ui/dialogs`. Hover, focus-visible, disabled i loading odsyła do zrzutów z tej fazy.
- `TermDepositsKitchenSinkTest` dostaje asercję nowych kluczy, jeśli asertuje listę.

#### 2. Podgląd i zrzuty

**File**: brak zmian w kodzie (procedura `context/foundation/agents/worktree-preview.md`)

**Intent**: Pokazać stany, których sink nie pokaże statycznie.

**Contract**:

- Podgląd z worktree na osobnej bazie: `migrate --seed`, `npm run build`, logowanie `test@example.com` / `password`.
- Zrzuty 390 px i 1280 px w jasnym i ciemnym motywie: `/term-deposits` i każdy scenariusz `/dev/ui/term-deposits`, do tego stany `term-deposit-*` w `/dev/ui/dialogs`.
- Stany interakcji:
    - hover wiersza;
    - focus-visible na `Switch`, `IconAction` i polach dialogu;
    - disabled `Switch` w trakcie żądania;
    - spinner zapisu w dialogu.
- Zrzuty trafiają do `context/changes/term-deposits-atrament-ui/screenshots/`. Jednorazowy skrypt Playwright nie jest commitowany.

### Success Criteria:

#### Automated Verification:

- Pełna bramka CI w obrazie Sail: `docker run … --entrypoint composer sail-8.5/app ci:check`
- Sloppy na hoście: `vendor/bin/sloppy ci`
- Statyka, typy, straże i Vitest: `npm run check`, `npm run types:check`, `npm run check:ui`, `npm test`

#### Manual Verification:

- Każdy scenariusz renderuje się w `/dev/ui/term-deposits` bez błędów w konsoli
- Zrzuty 390/1280 px w obu motywach nie pokazują przepełnienia ani przewijania w poziomie, a ujemne saldo zarchiwizowanej lokaty jest czytelne jako `destructive` na wyciszonym wierszu
- Hover, focus-visible i disabled są widoczne na zrzutach dla wymienionych elementów
- „Łączne środki” na `/term-deposits` z `DemoHouseholdSeeder` są równe tej mierze na Kontach i Dashboardzie

**Implementation Note**: Po zielonej weryfikacji automatycznej i potwierdzeniu zrzutów zmiana jest gotowa do przeglądu (`/10x-impl-review`).

---

## Testing Strategy

### Unit Tests:

- `TermDepositsHardcodedUiTest`: wzorce literałów sprzed Atramentu w plikach Lokat, z wynikiem `plik:linia`.
- `AccountsHardcodedUiTest` i `DashboardHardcodedUiTest`: bez zmian wzorców, tylko docblocki.
- `ui-contract.test.ts` (Vitest): Lokaty bez `:checked`, `Switch` przez `v-model`.

### Integration Tests:

- `TermDepositTest`:
    - `total_funds_minor_units` równe saldu konta plus sumie lokat;
    - brak `mark_url` w wierszach;
    - istniejące asercje sum i flag bez zmian.
- `ArchiveTermDepositTest`: `total_funds_minor_units` obejmuje zarchiwizowaną lokatę niezależnie od `show_archived`.
- `TotalFundsTest`, `DashboardTest`, `AccountTest`: bez zmian, zielone (wspólna definicja Łącznych środków).
- `TermDepositsKitchenSinkTest`, `DialogsKitchenSinkTest`: nowe scenariusze i stany, jeśli testy asertują listę.

### Manual Testing Steps:

1. Otwórz `/term-deposits` w podglądzie: tytuł z opisem, `Switch` obok „Dodaj lokatę” (gdy jest zarchiwizowana lokata), dwie miary, tabela z nagłówkami.
2. Włącz „Pokaż zarchiwizowane”: przełącznik jest wyłączony w trakcie żądania, potem pojawiają się wyciszone wiersze z Badge „Zarchiwizowane”.
3. Zarchiwizuj lokatę z ujemnym saldem: wiersz wyciszony, saldo w `destructive`, „W tym zarchiwizowane” z czerwoną kwotą.
4. Dodaj lokatę bez daty zakończenia: w kolumnie „—”, w dialogu podpowiedź pod polem, „zł” w saldzie otwarcia.
5. Zwęź okno do 390 px: dwie linie na wiersz, bez przewijania w poziomie.
6. Przejdź po stronie klawiszem Tab: fokus widoczny na `Switch`, „Dodaj lokatę” i akcjach wiersza.

## Performance Considerations

`TotalFunds::forUser` dokłada stronie Lokat dwa zapytania `SUM` (konta i lokaty). Payload zyskuje jedną liczbę i traci `mark_url` w każdym wierszu.

## Migration Notes

Bez migracji danych i bez zmian tras. `#show-archived-term-deposits` zostaje jako `id` przełącznika; żaden test E2E od niego nie zależy. Usunięcie `StatTile.vue` i `.holdings-list-row` jest bezpieczne, bo jedynym użytkownikiem był `TermDeposits.vue` (sprawdzone `grep` w `resources`).

## References

- Research: `context/changes/term-deposits-atrament-ui/research.md`
- Wzorzec: artefakt https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (`PageTermDeposits`, `StatPanel`, `DataTable`, `Switch`, `Dialog`, `Field`)
- Poprzednie zmiany: `context/archive/2026-11-06-accounts-atrament-ui/plan.md`, `context/archive/2026-11-06-accounts-design-system-page/plan.md`
- Wiersz tytułu, `StatPanel`, siatka: `resources/js/pages/Accounts.vue:83-98,128-156,190-305`, `resources/css/app.css:692-745`
- Dialog: `resources/js/components/AccountFormDialog.vue:41,67-70,210-223`, `resources/js/components/FormField.vue`
- Sink dialogów: `resources/js/pages/dev/DialogsKitchenSink.vue:36-110,501-528`
- Straż literałów: `tests/Unit/AccountsHardcodedUiTest.php`
- Reguły i ADR: `.ai/rules/js.md`, `.ai/rules/js-dialogs.md`, `.ai/rules/inertia-props.md`; ADR-0037, ADR-0040, ADR-0044, ADR-0045, ADR-0047

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0051 istnieje — 9829443
- [x] 1.2 Formatowanie dokumentów przechodzi (npm run check) — 9829443

#### Manual

- [ ] 1.3 ADR-0051, design-system.md i js.md są zgodne z decyzjami briefu

### Phase 2: Kontrakt propsów

#### Automated

- [x] 2.1 Testy Lokat, archiwizacji, TotalFunds i sinka Lokat na PostgreSQL przechodzą — 955f418
- [x] 2.2 Typy po buildzie przechodzą — 955f418
- [x] 2.3 Formatowanie PHP (Pint) — 955f418
- [x] 2.4 Sloppy bez nowych znalezisk — 955f418

### Phase 3: Widok Lokaty

#### Automated

- [x] 3.1 Statyka, typy i straże przechodzą — 3ce2844
- [x] 3.2 Vitest przechodzi — 3ce2844
- [x] 3.3 Straże literałów i testy Lokat w obrazie Sail przechodzą — 3ce2844
- [x] 3.4 Brak StatTile, holdings-list-row, Checkbox, AccountIdentityBlock i mark_url w Lokatach — 3ce2844

#### Manual

- [ ] 3.5 Dwie miary, „W tym zarchiwizowane”, nagłówki kolumn od md i saldo z „zł”
- [ ] 3.6 Kafelek ze skrótem banku i pełna nazwa w kolumnie Bank
- [ ] 3.7 Lokata bez daty ma wyciszone „—”
- [ ] 3.8 Na 390 px wiersz ma dwie linie, bez przewijania w poziomie
- [ ] 3.9 Panele Lokat i Kont na Dashboardzie bez zmian

### Phase 4: Dialog lokaty

#### Automated

- [x] 4.1 Statyka, typy i straże przechodzą — dbbee8f
- [x] 4.2 Vitest przechodzi — dbbee8f
- [x] 4.3 Straż Lokat, testy Lokat i sinka dialogów w obrazie Sail przechodzą — dbbee8f
- [x] 4.4 Dialog bez ręcznych pól i bez Anuluj secondary — dbbee8f

#### Manual

- [ ] 4.5 Utworzenie lokaty zapisuje pola, pola 34 px, saldo z „zł”
- [ ] 4.6 Edycja zapisuje zmiany i pokazuje ostrzeżenie o ujemnym saldzie
- [ ] 4.7 W trakcie zapisu spinner, a Esc, tło i Anuluj ghost nie zamykają dialogu
- [ ] 4.8 Błędne saldo wyłącza Zapisz, podpowiedź daty czytana przez czytnik ekranu

### Phase 5: Stany i bramka wizualna

#### Automated

- [x] 5.1 Pełna bramka CI (composer ci:check w obrazie Sail) — 928fac5
- [x] 5.2 Sloppy na hoście — 928fac5
- [x] 5.3 Statyka, typy, straże i Vitest — 928fac5

#### Manual

- [ ] 5.4 Każdy scenariusz renderuje się w /dev/ui/term-deposits bez błędów
- [ ] 5.5 Zrzuty 390/1280 px w obu motywach bez przepełnienia, ujemne saldo zarchiwizowanej lokaty czytelne
- [ ] 5.6 Hover, focus-visible i disabled widoczne na zrzutach
- [ ] 5.7 Łączne środki na Lokatach równe mierze Kont i Dashboardu
