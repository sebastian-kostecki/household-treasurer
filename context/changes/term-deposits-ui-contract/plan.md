# Term Deposits UI contract pass — Implementation Plan

## Overview

Przeprowadzamy jeden widok, Lokaty (`resources/js/pages/TermDeposits.vue` z `TermDepositFormDialog.vue`), przez kontrakt 10x-ui. Zmiana startuje **po scaleniu `accounts-ui-contract` do `development`** i przyjmuje z niej `ConfirmActionDialog`, poprawione prymitywy `ui/select` i `ui/dialog`, prop `invalid` w `AccountBankSelect` oraz decyzje w `.docs/design-system.md` i `.ai/rules/js.md`. Zachowania Kont (filtr przy 5 akcjach, pusty stan „same archiwa”, przełącznik w trakcie wizyty, walidacja salda otwarcia) powtarzamy z treścią dla lokat. Własne dla Lokat są dwie rzeczy: stopka sumy z kwotą zarchiwizowanych lokat (C2) i czerwona ujemna kwota w wyciszonym wierszu zarchiwizowanej lokaty (FR-TD-08). Wejście: `research.md` (`## Charges` C1–C5, macierz stanów) i decyzje z sesji planowania.

## Current State Analysis

- **Tokeny w porządku, kompozycja i stany nie.** Skan 10x-ui na plikach widoku: 0 hex, 0 klas palety, 0 arbitralnych `px`/`rem`. Jedyne trafienie `hover:bg-foreground/[0.03]` (`TermDeposits.vue:159`) to udokumentowana konwencja hovera.
- **C1 (martwy przełącznik):** `TermDeposits.vue:126-130` wiąże `:checked` / `@update:checked`, których reka-ui 2.11 `CheckboxRoot` nie zna. `toggleShowArchived` (`:92-100`) nie ma lokalnego stanu ani resynchronizacji. Formularze archiwizacji i przywrócenia (`:260,307`) nie wysyłają `show_archived`. `archive` / `restore` (`TermDepositController.php:116,130`) zachowują filtr tylko z żądania, a `store`, `update`, `destroy` (`:60,82,102`) przekierowują na gołe `term-deposits.index`. Testy nie sprawdzają przekierowania z `show_archived` dla żadnej akcji (jest tylko lista z filtrem, `ArchiveTermDepositTest.php:88-103`). `resources/js/ui-contract.test.ts:35` ma `pages/TermDeposits.vue` na liście `DEAD_CHECKBOX_BINDING_EXCEPTIONS` i failuje, gdy plik z listy przestaje zawierać martwe wiązanie (`:86-89`).
- **C2 (suma ≠ widoczne wiersze):** `term_deposits_total_minor_units` liczy wszystkie lokaty, także zarchiwizowane (`TermDepositController.php:23-26,37`), a lista bez filtra je chowa (`:28-31`). To zamierzone: FR-REP-10 (`.docs/prd.md:293`), test `ArchiveTermDepositTest.php:105-123`. Archiwizacja nie wymaga zerowego salda (`TermDepositPolicy.php:35-40`). Na ekranie nic tego nie wyjaśnia, a „Suma lokat” na Dashboardzie (`DashboardHoldings.vue:59-64`) pomija zarchiwizowane.
- **C3 (dialogi potwierdzenia):** trzy kopie `Dialog > IconAction as-dialog-trigger > DialogContent > Form > DialogHeader > DialogFooter` (`TermDeposits.vue:203-344`). Brak spinnera, Anuluj działa w trakcie żądania, a slot `Form` przy usuwaniu czyta tylko `processing` (`:217`), więc `errors.term_deposit` (`TermDepositController.php:90-92`) nigdzie się nie pokazuje.
- **C4 (kolor kwot, zarchiwizowane wiersze):** `totalClass` (`TermDeposits.vue:60-64`) maluje 0 zł na zielono, `balanceClass` (`:75-85`) to lokalny helper „znak → kolor” zamiast `amountToneClass` (`lib/amount-tone.ts`). Zarchiwizowane wiersze mają `opacity-60` (`:160`) i zachowują kolory statusu, a kontrakt każe wyciszyć cały wiersz `text-muted-foreground` bez `opacity` (`.docs/design-system.md:16,34`; wzorzec `BudgetCategoryRow.vue:41-47`). FR-TD-08 (`.docs/prd.md:224`) wymaga jednoznacznego oznaczenia ujemnego salda lokaty na każdej powierzchni.
- **C5 (kafelek i tytuł):** suma to ręczny `Card` + `CardTitle text-2xl` bez `tabular-nums` (`:114-123`), choć `StatTile` (`components/StatTile.vue`: `label`, `amountMinorUnits`, sloty `label-extra` i `footer`, `text-3xl tabular-nums`, `amountToneClass`) jest udokumentowanym kafelkiem KPI. „Lokaty” pada w okruszkach, `h1` (`:108`) i tytule `ListSurface` (`:153`).
- **Stany:** gdy wszystkie lokaty są zarchiwizowane, a filtr jest wyłączony, strona pokazuje „Dodaj pierwszą lokatę.” (`:139-149`) nad sumą, która może być niezerowa. Pole salda otwarcia po nieparsowalnym wpisie po cichu zachowuje ostatnią wartość (`TermDepositFormDialog.vue:113-119`, aktualizacja tylko na `blur`). Zapisz nie ma spinnera (`:244`). Pola nie mają `aria-invalid`, więc wbudowane `aria-invalid:border-destructive` (`ui/input/Input.vue:29`) nigdy nie działa.
- **Narzędzia:** kitchen sinki z kontrolerem i testem pod `/dev/ui/*` (`routes/web.php:23-24`, `app/Http/Controllers/Dev/*KitchenSinkController.php`, `tests/Feature/*KitchenSinkTest.php`), po scaleniu Kont także `/dev/ui/accounts`. `check:ui` (`scripts/check-ui-literals.mjs`, lista `FILES`) działa w `composer ci:check` i CI. Nie ma testów komponentów Vue. `UiTranslations.term_deposits` to `Record<string, string>` (`types/shell.ts:43`), więc nowe klucze w `lang/pl/ui.php` nie wymagają zmiany typu. Typy propsów strony nie mają modułu w `resources/js/types/` (są w `TermDeposits.vue:37-52` i `TermDepositFormDialog.vue:27-35`).
- **Zależność od Kont:** `accounts-ui-contract` ma plan (`context/changes/accounts-ui-contract/plan.md`), ale żadnego kodu. W jego fazach powstają: `ConfirmActionDialog` (faza 1), `ui/select` `rounded-xl` i X dialogu z fokusem i „Zamknij” (faza 2), `design-system.md:23` → `text-lg` (faza 2), prop `invalid?: boolean` w `AccountBankSelect` (faza 4), sekcja o `ConfirmActionDialog` w `js.md` i wpisy w `check-ui-literals.mjs` (faza 5).

## Desired End State

- Przełącznik „Pokaż zarchiwizowane” działa, pokazuje prawdziwy stan, jest wyłączony w trakcie przeładowania i widać go tylko wtedy, gdy są zarchiwizowane lokaty albo filtr jest włączony. Każda akcja na stronie (dodaj, edytuj, usuń, archiwizuj, przywróć) wraca z zachowanym filtrem.
- Usuń, archiwizuj i przywróć to `ConfirmActionDialog`: spinner w przycisku, zablokowane zamykanie w trakcie żądania, błąd serwera (`errors.term_deposit`) widoczny w dialogu.
- Suma to `StatTile` „Suma lokat” z `amountToneClass` (0 zł bez koloru). Gdy suma sald zarchiwizowanych lokat jest różna od zera, stopka kafelka pokazuje „W tym zarchiwizowane: X zł”, bez względu na stan filtra.
- Aktywne wiersze mają ton salda z `amountToneClass`. Zarchiwizowany wiersz jest w całości `text-muted-foreground`, bez `opacity`. Wyjątek: ujemne saldo zostaje `text-destructive` (FR-TD-08), zapisany w `.docs/design-system.md`.
- „Lokaty” występuje raz jako `h1` (`text-lg font-semibold`, zgodnie z dokumentem po Kontach).
- Gdy wszystkie lokaty są zarchiwizowane: pusty stan „Wszystkie lokaty są zarchiwizowane” z akcjami „Pokaż zarchiwizowane” i „Dodaj lokatę”.
- Nieparsowalne saldo otwarcia pokazuje polski komunikat przy polu, ustawia `aria-invalid` i blokuje Zapisz. Zapisz pokazuje spinner w trakcie żądania.
- `/dev/ui/term-deposits` (tylko `local`) pokazuje widok we wszystkich scenariuszach z wypełnioną macierzą 7 stanów. W folderze zmiany są zrzuty `before/`, `after-phase-2/` i `after-phase-3/` (1280 i 390 px, jasny i ciemny).
- `check:ui` obejmuje pliki widoku, a `js.md` i `.docs/design-system.md` wymieniają nowy sandbox i wyjątek FR-TD-08.

Weryfikacja: `composer ci:check` (obraz Sail) i `vendor/bin/sloppy ci` (host) przechodzą, `check:ui` daje 0 trafień, człowiek ogląda kitchen sink i zrzuty.

### Key Discoveries:

- Wzorzec poprawionego przełącznika: `resources/js/pages/Budget.vue:93-106,151-166` (lokalny `ref` z propsa, `watch` propsa, wizyta tylko przy zmianie przez użytkownika, resynchronizacja w `onFinish`). Konta po scaleniu dają drugi egzemplarz z `isTogglingArchived` i filtrem przekazywanym do `.form(...)`.
- `$request->boolean('show_archived')` czyta też query string, a Wayfinder przyjmuje query w `.form(args, { query })` (wygenerowane `resources/js/actions/App/Http/Controllers/TermDepositController.ts`), więc zachowanie filtra przy POST/PATCH/DELETE nie wymaga zmian w Form Requestach.
- `parseMoneyInputToMinorUnits` (`lib/format.ts:41-56`) odrzuca pusty ciąg i `12,3,4`, przyjmuje `12,` i `1 234,56`, więc walidację można liczyć na żywo z wartości pola. Brzmienie komunikatu jest już w repo: `month_plan.amount_invalid` „Wpisz kwotę, np. 1200,50.” (Konta używają tego samego).
- Wzorzec dopisku z kwotą bez interpolacji tłumaczeń: `{{ t.term_deposits.end_date }}: {{ formatDate(...) }}` (`TermDeposits.vue:182-183`).
- `IndexEmptyState` przyjmuje `title`, `description?` i slot `actions`. `ListSurface` ma `title?` opcjonalny.
- Po dodaniu trasy: `npm run build` przed `npm run types:check` (Wayfinder).

## What We're NOT Doing

- **Budowa `ConfirmActionDialog` i zmiany prymitywów `ui/select` / `ui/dialog`.** Robi to `accounts-ui-contract`. Ta zmiana je przyjmuje i niczego w nich nie zmienia.
- **„Suma lokat” na Dashboardzie** (pomija zarchiwizowane, strona je wlicza). Inny widok, zostaje w tabeli odroczeń research.
- **Zmiana arytmetyki sumy.** Wliczanie zarchiwizowanych jest zamierzone i przetestowane (FR-REP-10).
- **Dopisek w `archive_confirm_body`, że saldo nadal liczy się do sumy.** Wyjaśnia to stopka kafelka. Zmianę treści potwierdzenia można zrobić osobno.
- **Przełącznik w `OperationsImport.vue`.** Inny widok, issue #186 zostaje otwarte dla niego.
- **Usunięcie `is_negative` z payloadu lokat.** Po fazie 2 strona go nie czyta, ale usunięcie zmienia kontrakt propsów bez zysku dla użytkownika (ta sama decyzja co w Kontach).
- **Rozmiar `h1`.** Zostaje `text-lg`; dokument dostosowują Konta.
- **Angielskie toasty i błędy serwera** (`TermDepositController.php:57,79,91,99,113,127`). i18n, osobna zmiana. Błąd w `ConfirmActionDialog` pokaże do tego czasu angielski tekst.
- **`TermDepositPolicy::update` tylko z właścicielem.** Autoryzacja backendu, UI i tak chowa Edytuj dla zarchiwizowanych.
- **Playwright jako zależność i `toHaveScreenshot`.** Skrypt zrzutów jest jednorazowy i niecommitowany (konwencja `design-system.md`, „Kitchen sink i zrzuty”).
- **Hover wierszy `hover:bg-foreground/[0.03]`.** Udokumentowana konwencja.

## Implementation Approach

Kolejność według routera 10x-ui, bez fazy prymitywów, bo tę robią Konta. Faza 1 buduje kitchen sink bez dotykania widoku, żeby zrobić zrzuty „przed”. Faza 2 wymienia markup widoku (C1, C3, C4, C5) na komponenty i helpery repo. Faza 3 dodaje dwa nowe propsy (C2 i pusty stan) oraz brakujące stany. Każdy prop zmienia PHP, TS, fixture'y i test `assertInertia` w jednym commicie. Faza 4 zostawia regułę i skan. Edycje współdzielonych list (`check-ui-literals.mjs`, `design-system.md`, `js.md`) tylko dopisują, nie przepisują.

## Critical Implementation Details

**Start po scaleniu Kont.** Przed fazą 1 `accounts-ui-contract` musi być w `development`. Gałąź `term-deposits-ui-contract` odgałęzia się od aktualnego `development`, a pierwszy commit fazy 1 zabiera folder zmiany (`change.md`, `research.md`, `plan.md`, `plan-brief.md`). Przed pisaniem kodu przeczytaj scalone `ConfirmActionDialog.vue`, `AccountBankSelect.vue` i `Accounts.vue`. Wywołania w tym planie (propsy, slot `trigger`, `filterQuery`, prop `invalid`) dopasuj do tego, co faktycznie powstało, a nie do planu Kont. Rozbieżność zgłoś, zamiast budować drugą wersję.

**Ton salda zarchiwizowanej lokaty.** Kolor daje wyłącznie `amountToneClass`, ale w zarchiwizowanym wierszu tylko dla salda `< 0`. Dodatnie i zerowe saldo nie dostaje klasy i dziedziczy `text-muted-foreground` z wiersza. Ta sama reguła obowiązuje kwotę w stopce „W tym zarchiwizowane” (faza 3). Nie wolno pisać nowego mapowania „znak → kolor”.

## Phase 1: Kitchen sink i zrzuty „przed”

### Overview

Strona dev-only renderuje widok Lokat w każdym scenariuszu danych. Powstaje skrypt zrzutów i zrzuty „przed”. Widok Lokat się nie zmienia.

### Changes Required:

#### 1. Typy propsów strony Lokaty

**File**: `resources/js/types/term-deposits.ts` (nowy), `resources/js/pages/TermDeposits.vue`, `resources/js/components/TermDepositFormDialog.vue`

**Intent**: fixture'y kitchen sinka sprawdzane przez `vue-tsc` względem tego samego kontraktu co strona (wzorzec `types/accounts.ts` z Kont).

**Contract**: eksport `TermDepositFormTermDeposit` (przeniesiony z `TermDepositFormDialog.vue:27-35`), `TermDepositRow` (`TermDeposits.vue:37-46`) i `TermDepositsPageProps` (`termDeposits`, `term_deposits_total_minor_units`, `show_archived`, dokładnie jak `TermDeposits.vue:48-52`). `TermDeposits.vue` używa `defineProps<TermDepositsPageProps>()`, `TermDepositFormDialog.vue` importuje typ z modułu typów. PHP bez zmian.

#### 2. Kontroler, trasa i test kitchen sinka

**File**: `app/Http/Controllers/Dev/TermDepositsKitchenSinkController.php` (nowy), `routes/web.php`, `tests/Feature/TermDepositsKitchenSinkTest.php` (nowy)

**Intent**: ten sam wzorzec co `/dev/ui/dashboard` i `/dev/ui/accounts`: za logowaniem, 404 poza `local`.

**Contract**: `GET dev/ui/term-deposits`, nazwa `dev.ui.term-deposits`, w grupie `auth` + `verified`, obok `dev.ui.accounts`. Kontroler `abort_unless(app()->isLocal(), 404)` i Inertia `dev/TermDepositsKitchenSink` bez własnych propsów. Test kopiuje trzy przypadki z `DashboardKitchenSinkTest.php` (gość → login, 404 w `production`, strona przy `local`).

#### 3. Strona kitchen sinka i fixture'y

**File**: `resources/js/pages/dev/TermDepositsKitchenSink.vue` (nowy), `resources/js/pages/dev/term-deposits-fixtures.ts` (nowy)

**Intent**: jeden ekran ze wszystkimi scenariuszami widoku naraz. To bramka wizualna i dowód do przeglądu.

**Contract**: fixture'y typu `TermDepositsPageProps`:
- `default`: lokata w banku ze znakiem (np. mBank), lokata „Inny bank”, z datą zakończenia i bez; salda dodatnie, zero i ujemne; mieszanka `can_delete` / `can_archive`;
- `with-archived`: `show_archived: true`, aktywne wiersze plus trzy zarchiwizowane z `can_restore` o saldzie dodatnim, zerowym i ujemnym (przypadek FR-TD-08);
- `zero-total`: suma 0, salda 0;
- `negative-total`: suma ujemna;
- `empty-new-user`: brak lokat, suma 0;
- `all-archived`: `termDeposits: []`, suma ≠ 0, `show_archived: false`.

Strona renderuje `TermDeposits.vue` z każdym fixture'em pod nagłówkiem scenariusza (wzorzec `DashboardKitchenSink.vue:9,110`, który tak samo osadza całą stronę; okruszki i `<Head>` pochodzą wtedy z osadzonej strony). Nagłówek strony mówi, że to strona deweloperska, że zduplikowane `id` pól w kilku `TermDepositFormDialog` są tu dopuszczalne, że akcje formularzy nie działają, bo fixture'ów nie ma w bazie, i że przełącznik „Pokaż zarchiwizowane” (a po fazie 3 także przycisk o tej nazwie w pustym stanie) przechodzi na prawdziwe `/term-deposits`. Skrypt zrzutów nie klika ich w sinku; stany przełącznika fotografuje na `/term-deposits`.

#### 4. Skrypt zrzutów i zrzuty „przed”

**File**: skrypt poza repo (katalog tymczasowy sesji, np. `$CLAUDE_JOB_DIR/tmp/shoot-term-deposits.mjs`), `context/changes/term-deposits-ui-contract/screenshots/before/`

**Intent**: powtarzalne zrzuty po każdej fazie wizualnej bez nowej zależności w repo.

**Contract**: ten sam przepis co skrypt Kont. Node ładuje `playwright-core` z cache npx i headless Chromium z `~/.cache/ms-playwright`, a potem loguje się jako `test@example.com` / `password` na podglądzie `http://localhost:8090` (przepis „Preview from a worktree” w `AGENTS.md`, osobna baza np. `term_deposits_ui_contract`). Argumentem jest etap (`before`, `after-phase-2`, …). Motyw skrypt ustawia przez `localStorage` i cookie `appearance` przed nawigacją. Zrzuty pełnej strony robi dla `/dev/ui/term-deposits`, `/term-deposits` i `/term-deposits` z otwartym dialogiem „Dodaj lokatę”, przy 1280 i 390 px, w jasnym i ciemnym motywie. Pliki nazywa `<widok>-<szerokość>-<motyw>.png`. Commitowane są tylko PNG. Gdy sesja się zmieni, skrypt odtwarza się z tego opisu.

### Success Criteria:

#### Automated Verification:

- Lint i format przechodzą: `npm run check`
- Typy przechodzą po przebudowie Wayfindera: `npm run build && npm run types:check`
- Test Feature kitchen sinka przechodzi: `php artisan test --compact tests/Feature/TermDepositsKitchenSinkTest.php`
- Istniejące testy lokat przechodzą bez zmian: `php artisan test --compact tests/Feature/TermDepositTest.php tests/Feature/ArchiveTermDepositTest.php`

#### Manual Verification:

- `/dev/ui/term-deposits` lokalnie pokazuje 6 scenariuszy z nagłówkami, a `/term-deposits` wygląda tak samo jak przed fazą
- Zrzuty „przed” (2 widoki + dialog, 1280 i 390 px, jasny i ciemny) są w `screenshots/before/`

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 2: Jeden widok (C1, C3, C4, C5)

### Overview

Widok Lokat przechodzi na komponenty i helpery repo, a przełącznik zaczyna działać. Po fazie skan na plikach widoku daje 0 trafień.

### Changes Required:

#### 1. Przełącznik i zachowanie filtra (C1)

**File**: `resources/js/pages/TermDeposits.vue`, `resources/js/components/TermDepositFormDialog.vue`, `resources/js/ui-contract.test.ts`, `app/Http/Controllers/TermDepositController.php`, `tests/Feature/TermDepositTest.php`, `tests/Feature/ArchiveTermDepositTest.php`

**Intent**: przełącznik pokazuje prawdziwy stan i przeładowuje listę, a każda akcja na stronie wraca z tym samym filtrem.

**Contract**:
- `TermDeposits.vue`: `showArchived = ref(props.show_archived)`, `watch` propsa → ref, `<Checkbox v-model="showArchived">`. Zmiana przez użytkownika (wartość różna od propsa) robi `router.get(termDepositsRoute({ query: { show_archived: checked ? 1 : undefined } }), {}, { preserveState: true, preserveScroll: true, onFinish: resync })` jak w `Budget.vue`.
- `ui-contract.test.ts`: `pages/TermDeposits.vue` znika z `DEAD_CHECKBOX_BINDING_EXCEPTIONS` w tym samym commicie.
- Strona liczy `filterQuery` (`{ query: { show_archived: 1 } }` przy włączonym filtrze, inaczej brak) i przekazuje go do `destroy`, `archive`, `restore` oraz do `TermDepositFormDialog` przez nowy opcjonalny prop `showArchived?: boolean`. Dialog dokleja query do `store.form` / `update.form`. Kształt bierzemy z `Accounts.vue` / `AccountFormDialog.vue` po scaleniu.
- `TermDepositController`: prywatna metoda zwracająca przekierowanie na `term-deposits.index` z `['show_archived' => 1]`, gdy `$request->boolean('show_archived')`. Zastępuje pięć osobnych `to_route` w `store`, `update`, `destroy` (gałąź sukcesu), `archive` i `restore`. `back()->withErrors` w `destroy` bez zmian.
- Testy Feature: dla każdej z 5 akcji przekierowanie z `?show_archived=1` zachowuje query, a bez niego nie.

#### 2. Potwierdzenia przez `ConfirmActionDialog` (C3)

**File**: `resources/js/pages/TermDeposits.vue`

**Intent**: trzy ręczne kopie dialogu znikają, a usuwanie pokazuje błąd serwera.

**Contract**: usuń (`destructive`), archiwizuj i przywróć to `ConfirmActionDialog` z `form` = `TermDepositController.<action>.form(termDeposit, filterQuery)`. Dialog dostaje istniejące klucze `t.term_deposits.*_confirm_title` / `*_confirm_body`, etykietę akcji, `t.term_deposits.cancel` i `IconAction as-dialog-trigger` w slocie `trigger`. Warunki `v-if="termDeposit.can_*"` bez zmian. Nieużywane importy `Dialog*` i `Form` znikają ze strony. Propsy i sloty według scalonego komponentu.

#### 3. Ton kwot i zarchiwizowany wiersz (C4)

**File**: `resources/js/pages/TermDeposits.vue`

**Intent**: zero nie jest zielone, zarchiwizowany wiersz jest wyciszony bez utraty kontrastu, a ujemne saldo lokaty zawsze widać.

**Contract**:
- `balanceClass` i `totalClass` znikają.
- Saldo wiersza: `amountToneClass(current_balance_minor_units)`, gdy wiersz jest aktywny albo saldo `< 0`. Dodatnie i zerowe saldo w zarchiwizowanym wierszu nie dostaje klasy (zob. Critical Implementation Details).
- Kontener wiersza: `opacity-60` → `text-muted-foreground` dla `is_archived` (wzorzec `BudgetCategoryRow.vue:41-47`). `Badge variant="secondary"` „Zarchiwizowane” bez zmian.

#### 4. Kafelek sumy i tytuł (C5)

**File**: `resources/js/pages/TermDeposits.vue`

**Intent**: suma jest kafelkiem KPI wyrównanym cyfra w cyfrę z saldami, a „Lokaty” pada raz.

**Contract**:
- Ręczny `Card` sumy (`:114-123`) → `<StatTile :label="t.term_deposits.total" :amount-minor-units="term_deposits_total_minor_units" />`. Nieużywane importy `Card*` znikają.
- `ListSurface` bez `title`, z `description` (tak samo jak Konta; brzmienie `list_description` bez zmian).
- `h1` zostaje `text-lg font-semibold`.

#### 5. Zrzuty i skan po fazie

**File**: `context/changes/term-deposits-ui-contract/screenshots/after-phase-2/`

**Intent**: dowód zmian widoku.

**Contract**: skrypt zrzutów z etapem `after-phase-2` po `npm run build` na podglądzie. `node scripts/check-ui-literals.mjs resources/js/pages/TermDeposits.vue resources/js/components/TermDepositFormDialog.vue` daje 0 trafień.

### Success Criteria:

#### Automated Verification:

- Testy lokat przechodzą (redirect z filtrem dla 5 akcji): `php artisan test --compact tests/Feature/TermDepositTest.php tests/Feature/ArchiveTermDepositTest.php`
- Skan na plikach widoku daje 0 trafień: `node scripts/check-ui-literals.mjs resources/js/pages/TermDeposits.vue resources/js/components/TermDepositFormDialog.vue`
- Lint, format, typy i testy frontendu (w tym `ui-contract.test.ts` bez wpisu `pages/TermDeposits.vue`) przechodzą: `npm run check && npm run types:check && npm test`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual Verification:

- Na podglądzie: zaznaczenie „Pokaż zarchiwizowane” pokazuje zarchiwizowane lokaty i zmienia URL, odznaczenie je chowa, a odświeżenie z `?show_archived=1` pokazuje zaznaczony przełącznik
- Z włączonym filtrem: archiwizacja, przywrócenie, edycja, dodanie i usunięcie lokaty wracają do listy z włączonym filtrem
- Dialog usuwania ma czerwony przycisk ze spinnerem w trakcie żądania, a Anuluj, X i Escape nie zamykają go w tym czasie
- Suma 0 zł jest bez koloru, ujemna czerwona, dodatnia zielona; „Lokaty” pada raz poza okruszkami
- Zarchiwizowany wiersz jest szary bez przezroczystości (przycisk Przywróć ma pełny kontrast), a ujemne saldo w nim jest czerwone
- Zrzuty `after-phase-2/` są w folderze zmiany

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 3: Stany i nowe propsy (C2 + macierz)

### Overview

Kafelek sumy wyjaśnia kwotę zarchiwizowanych lokat. Widok dostaje brakujące stany: pusty przy samych zarchiwizowanych, przełącznik w trakcie wizyty, zapis w toku i błąd kwoty. Kitchen sink pokazuje wypełnioną macierz 7 stanów.

### Changes Required:

#### 1. Propsy `archived_term_deposits_total_minor_units` i `archived_term_deposits_count`

**File**: `app/Http/Controllers/TermDepositController.php`, `resources/js/types/term-deposits.ts`, `resources/js/pages/dev/term-deposits-fixtures.ts`, `tests/Feature/TermDepositTest.php`, `tests/Feature/ArchiveTermDepositTest.php`

**Intent**: strona zna kwotę i liczbę zarchiwizowanych lokat niezależnie od filtra, bo przy wyłączonym filtrze nie da się ich policzyć z listy.

**Contract**: `index` zwraca `archived_term_deposits_total_minor_units: int` (suma `current_balance_minor_units` zarchiwizowanych lokat użytkownika) i `archived_term_deposits_count: int`. Obie wartości liczy z `$allTermDeposits`, bez filtra. `TermDepositsPageProps` dostaje oba pola, a fixture'y je ustawiają (`all-archived`: liczba > 0 i kwota = suma; `with-archived`: kwota ≠ 0; `empty-new-user`: 0 i 0). Test `assertInertia`: bez zarchiwizowanych 0 i 0; aktywna 1000 zł + zarchiwizowana 500 zł → suma 150000, kwota zarchiwizowanych 50000, liczba 1, z filtrem wyłączonym i włączonym. Zarchiwizowana lokata z saldem 0 daje liczbę 1 i kwotę 0. Obie strony kontraktu idą w jednym commicie.

#### 2. Stopka kafelka sumy (C2)

**File**: `resources/js/pages/TermDeposits.vue`, `lang/pl/ui.php`

**Intent**: suma, która nie zgadza się z widocznymi wierszami, sama mówi dlaczego.

**Contract**: slot `footer` w `StatTile` renderowany tylko przy `archived_term_deposits_total_minor_units !== 0`: `{{ t.term_deposits.archived_total }}: {{ formatMoney(archived_term_deposits_total_minor_units) }}`. Nowy klucz `archived_total` „W tym zarchiwizowane”. Stopka ma `text-sm text-muted-foreground`, a kwota `tabular-nums` i ten sam ton co saldo zarchiwizowanego wiersza: `amountToneClass` tylko przy kwocie `< 0`, inaczej bez klasy. Dzięki temu np. −300 zł jednej zarchiwizowanej lokaty jest czerwone i w wierszu, i w stopce. Arytmetyka sumy bez zmian.

#### 3. Pusty stan i widoczność przełącznika

**File**: `resources/js/pages/TermDeposits.vue`, `lang/pl/ui.php`

**Intent**: każdy pusty stan mówi prawdę i daje wyjście.

**Contract**:
- `termDeposits.length === 0 && archived_term_deposits_count === 0` → obecny `IndexEmptyState` „Brak lokat” (bez zmian).
- `termDeposits.length === 0 && archived_term_deposits_count > 0` → `IndexEmptyState` z `t.term_deposits.all_archived_title` „Wszystkie lokaty są zarchiwizowane” i `t.term_deposits.all_archived_description` „Pokaż zarchiwizowane lokaty, aby je przejrzeć lub przywrócić.” Akcje: `Button` „Pokaż zarchiwizowane” (istniejący `show_archived`, ustawia `showArchived = true`) i `Button variant="secondary"` „Dodaj lokatę”.
- Przełącznik renderowany tylko gdy `archived_term_deposits_count > 0 || show_archived`.

#### 4. Przełącznik w trakcie wizyty

**File**: `resources/js/pages/TermDeposits.vue`

**Intent**: użytkownik widzi, że lista się przeładowuje, i nie klika drugi raz.

**Contract**: `ref` `isTogglingArchived` ustawiany w `onStart`, czyszczony w `onFinish` wizyty przełącznika. `Checkbox` i przycisk „Pokaż zarchiwizowane” z pustego stanu mają `:disabled` na czas wizyty. Bez skeletonu: `preserveState` trzyma układ, strona nie ma deferred props.

#### 5. Formularz lokaty: błąd salda otwarcia, `aria-invalid`, spinner

**File**: `resources/js/components/TermDepositFormDialog.vue`, `lang/pl/ui.php`

**Intent**: nie da się zapisać innej kwoty niż wpisana, pola z błędem wyglądają na błędne, a zapis w toku jest widoczny.

**Contract**:
- `openingBalanceInvalid` liczone na żywo: `parseMoneyInputToMinorUnits(openingBalanceInput) === null`. Ukryte `opening_balance_minor_units` aktualizowane przy każdej poprawnej wartości, nie tylko na `blur`. Ostrzeżenie o ujemnym saldzie (`showNegativeBalanceWarning`) liczy się z tej samej wartości.
- Gdy `openingBalanceInvalid`: `InputError` pokazuje `t.term_deposits.opening_balance_invalid` „Wpisz kwotę, np. 1200,50.” zamiast błędu serwera, `Input` ma `aria-invalid="true"`, a Zapisz ma `:disabled="processing || openingBalanceInvalid"`. Otwarcie dialogu resetuje stan razem z polem.
- `aria-invalid` na `Input` nazwy, datach otwarcia i zakończenia oraz (przez prop `invalid` w `AccountBankSelect`, dodany w Kontach) na wyborze banku, gdy dany klucz `errors` jest ustawiony.
- Zapisz: `<Spinner v-if="processing" />` przed etykietą (wzorzec `ConfirmActionDialog` / `MonthPlanEditBar.vue:64,73`).

#### 6. Macierz w kitchen sinku

**File**: `resources/js/pages/dev/TermDepositsKitchenSink.vue`

**Intent**: każda komórka macierzy jest pokazana albo ma N/A z powodem.

**Contract**: tabela stanów jak w `DashboardKitchenSink.vue:11-40`:
- **default**: scenariusze `default`, `zero-total`, `negative-total`, `with-archived` (stopka sumy, zarchiwizowane saldo ujemne na czerwono);
- **hover**: zrzuty z działającej aplikacji (stan zależny od interakcji), wiersze i `IconAction`;
- **focus-visible**: zrzuty po Tab: przycisk „Dodaj lokatę”, przełącznik, `IconAction`, X dialogu, wybór banku;
- **disabled**: zrzut z opóźnionym POST (przycisk potwierdzenia i Zapisz ze spinnerem, zablokowane Anuluj w potwierdzeniu) oraz Zapisz zablokowany przy nieparsowalnej kwocie;
- **error**: przycisk w kitchen sinku otwiera `TermDepositFormDialog` w trybie tworzenia, wpisanie `12,3,4` pokazuje komunikat; `errors.term_deposit` przy usuwaniu: istniejący test `test_user_cannot_delete_term_deposit_with_capital_movements` + ręczne sprawdzenie na nieaktualnej stronie;
- **empty**: `empty-new-user` i `all-archived`;
- **loading**: przełącznik zablokowany w trakcie wizyty (zrzut z opóźnioną odpowiedzią); skeleton N/A, bo brak deferred props.

Skrypt zrzutów dostaje ujęcia stanów interakcyjnych: Tab do kontrolek, `page.route` opóźniający POST/GET, wpis `12,3,4`.

#### 7. Zrzuty po fazie

**File**: `context/changes/term-deposits-ui-contract/screenshots/after-phase-3/`

**Intent**: dowód macierzy stanów.

**Contract**: skrypt zrzutów z etapem `after-phase-3`, z ujęciami stanów.

### Success Criteria:

#### Automated Verification:

- Testy propsów `archived_term_deposits_total_minor_units` i `archived_term_deposits_count` przechodzą: `php artisan test --compact tests/Feature/TermDepositTest.php tests/Feature/ArchiveTermDepositTest.php`
- Skan na plikach widoku daje 0 trafień: `node scripts/check-ui-literals.mjs resources/js/pages/TermDeposits.vue resources/js/components/TermDepositFormDialog.vue resources/js/pages/dev/TermDepositsKitchenSink.vue`
- Lint, format, typy i testy frontendu przechodzą: `npm run check && npm run types:check && npm test`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual Verification:

- Z jedną zarchiwizowaną lokatą z saldem: kafelek pokazuje „W tym zarchiwizowane: X zł” przy włączonym i wyłączonym filtrze; bez zarchiwizowanych sald stopki nie ma
- Po zarchiwizowaniu wszystkich lokat (filtr wyłączony) widać „Wszystkie lokaty są zarchiwizowane”, a „Pokaż zarchiwizowane” je pokazuje
- Nowy użytkownik bez lokat nie widzi przełącznika i widzi „Brak lokat”
- W trakcie przeładowania przełącznik jest wyłączony
- Wpis `12,3,4` w saldzie otwarcia pokazuje komunikat przy polu i blokuje Zapisz; poprawny wpis zdejmuje blokadę; Zapisz pokazuje spinner w trakcie żądania
- Każda komórka macierzy w `/dev/ui/term-deposits` jest pokazana albo ma N/A z powodem, w obu motywach
- Zrzuty `after-phase-3/` (z ujęciami stanów) są w folderze zmiany

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 4: Zabezpieczenie

### Overview

Następny agent dostaje skan i notatki, które trzymają go przy kontrakcie. Wyjątek FR-TD-08 i odroczone zarzuty są zapisane.

### Changes Required:

#### 1. Lista plików skanu

**File**: `scripts/check-ui-literals.mjs`

**Intent**: `check:ui` (w `composer ci:check` i CI) pilnuje oczyszczonych plików Lokat.

**Contract**: do `FILES` dochodzą `resources/js/pages/TermDeposits.vue`, `resources/js/components/TermDepositFormDialog.vue` i `resources/js/pages/dev/TermDepositsKitchenSink.vue`, dopisane pod wpisami Kont. Wzorzec bez zmian.

#### 2. Reguły dla agentów

**File**: `.ai/rules/js.md`

**Intent**: sandbox Lokat ma nazwę w regule. Boost MCP nie jest podłączony, więc zgodnie z `AGENTS.md` edytujemy plik reguły bezpośrednio.

**Contract**: „Component sandbox” wymienia `/dev/ui/term-deposits` obok `/dev/ui/dashboard` i `/dev/ui/accounts`. Przy regule `amountToneClass` (`js.md:34-38`, „nigdy lokalne helpery znak → kolor”) dochodzi jedno zdanie: „Zarchiwizowane wiersze: bez tonu, poza saldem < 0 lokaty (FR-TD-08) — wtedy `amountToneClass`.” Sekcje o Checkboxie i `ConfirmActionDialog` (z Kont) bez zmian.

#### 3. Notatki systemu i odroczone zarzuty

**File**: `.docs/design-system.md`, `context/changes/term-deposits-ui-contract/research.md`

**Intent**: wyjątek od reguły wyciszonego wiersza jest zapisany tam, gdzie jest reguła, a niezaadresowane rzeczy nie giną.

**Contract**:
- Tabela „Kolor znaczy coś…”, wiersz `muted-foreground`: dopisek „Wyjątek: ujemne saldo zarchiwizowanej lokaty zostaje `text-destructive` (FR-TD-08)”. Ten sam wyjątek dopisany zdaniem przy regule zarchiwizowanych wierszy w „Powierzchnie i rytm” (`:16`).
- „Zmiana systemu” punkt 4: `/dev/ui/term-deposits` dopisany obok pozostałych sandboxów.
- `research.md`, tabela „Deferred / rejected”: dopisane `archive_confirm_body` bez wzmianki o sumie, nieużywane `is_negative`; wiersz `h1` oznaczony jako rozstrzygnięty przez `accounts-ui-contract`, wiersz kafelka banku / `text-[10px]` jako zamknięty przez `accounts-ui-contract`.

### Success Criteria:

#### Automated Verification:

- Skan przechodzi na rozszerzonej liście: `npm run check:ui`
- Pełna bramka CI przechodzi: `composer ci:check` (obraz Sail)
- Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual Verification:

- Reguły w `js.md` są zgodne z `design-system.md` i nie przeczą sobie (w tym wyjątek FR-TD-08)
- Lista kontrolna `ui-quality-checklist` przejrzana punkt po punkcie z dowodami w zrzutach i kitchen sinku

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed `/10x-impl-review`.

---

## Testing Strategy

### Unit Tests:

- Brak nowych testów TS: logika widoku żyje w komponentach Vue, a repo nie ma testów komponentów. `parseMoneyInputToMinorUnits` i `amountToneClass` mają już testy. `ui-contract.test.ts` pilnuje martwego wiązania Checkboxa.

### Integration Tests:

- `TermDepositsKitchenSinkTest`: gość → login, 404 poza `local`, strona przy `local`.
- `TermDepositTest` / `ArchiveTermDepositTest`:
  - przekierowanie z zachowanym `show_archived` dla `store`, `update`, `destroy`, `archive` i `restore`, z query i bez;
  - `archived_term_deposits_total_minor_units` i `archived_term_deposits_count`: 0 i 0 bez zarchiwizowanych, 50000 i 1 przy aktywnej 1000 zł + zarchiwizowanej 500 zł z filtrem i bez, 0 i 1 przy zarchiwizowanej lokacie z saldem 0;
  - istniejące testy sumy z zarchiwizowanymi i błędu `term_deposit` przy usuwaniu bez zmian.
- Brak migracji i surowego SQL, więc SQLite wystarcza dla tych testów. Pełna bramka i tak idzie przez `composer ci:check` na obrazie Sail z PostgreSQL.

### Manual Testing Steps:

1. Podgląd (`AGENTS.md`, „Preview from a worktree”): osobna baza, `migrate --seed`, `npm run build`, port 8090, `test@example.com` / `password`.
2. Utwórz dwie lokaty, do jednej dodaj ruch kapitału (żeby dało się ją zarchiwizować), zarchiwizuj ją z niezerowym saldem. Sprawdź stopkę kafelka przy filtrze wyłączonym i włączonym.
3. Włącz i wyłącz filtr, przywróć lokatę, edytuj i dodaj inną przy włączonym filtrze; sprawdź URL i stan przełącznika po każdej akcji.
4. Zarchiwizuj wszystkie lokaty z historią (resztę usuń) i sprawdź pusty stan.
5. Doprowadź zarchiwizowaną lokatę do salda ujemnego (np. lokata z ujemnym saldem otwarcia zarchiwizowana po ruchu) i sprawdź czerwoną kwotę w wyciszonym wierszu.
6. Otwórz „Dodaj lokatę”, wpisz `12,3,4`, potem `1 234,56`, potem `-50` (ostrzeżenie o ujemnym saldzie).
7. Przejdź Tabem po stronie i dialogu w obu motywach.
8. Nieaktualna strona: otwórz listę, w drugiej karcie dodaj ruch kapitału do lokaty bez historii, w pierwszej spróbuj ją usunąć; dialog pokazuje błąd.

## Performance Considerations

Bez znaczenia. Oba nowe propsy liczą się z kolekcji `$allTermDeposits` już pobranej w `index`.

## Migration Notes

Bez migracji danych.

- **Baza:** gałąź startuje od `development` po scaleniu `accounts-ui-contract`. Bez tego faza 2 nie ma `ConfirmActionDialog`, a faza 3 propa `invalid` w `AccountBankSelect`.
- **Równoległe gałęzie UI** (`sidebar-ui-contract` p3–p5, `worktree-operations-ui-contract`) dotykają `check-ui-literals.mjs`, `design-system.md` i `js.md`. Edycje tylko dopisują; przy scalaniu łączyć sekcje, nie nadpisywać.
- **Issue #186:** PR zamyka część dotyczącą Lokat; issue zostaje otwarte dla `OperationsImport.vue`.

## References

- Research: `context/changes/term-deposits-ui-contract/research.md`
- Siostrzany plan (źródło `ConfirmActionDialog`, prymitywów i wzorców): `context/changes/accounts-ui-contract/plan.md`
- Precedens kitchen sinka: `context/archive/2026-11-03-dashboard-ui-contract/plan.md`, `resources/js/pages/dev/DashboardKitchenSink.vue`
- Wzorzec przełącznika: `resources/js/pages/Budget.vue:93-106,151-166`
- Wzorzec zarchiwizowanego wiersza: `resources/js/components/BudgetCategoryRow.vue:41-47`
- Wymagania: `.docs/prd.md:221-224` (FR-TD-05..08), `.docs/prd.md:293` (FR-REP-10)
- Kontrakt: `.docs/design-system.md`, `.ai/rules/js.md`, `.ai/rules/css.md`, `.claude/skills/10x-ui/references/ui-quality-checklist.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kitchen sink i zrzuty „przed”

#### Automated

- [x] 1.1 Lint i format przechodzą: `npm run check`
- [x] 1.2 Typy przechodzą po przebudowie Wayfindera: `npm run build && npm run types:check`
- [x] 1.3 Test Feature kitchen sinka przechodzi: `php artisan test --compact tests/Feature/TermDepositsKitchenSinkTest.php`
- [x] 1.4 Istniejące testy lokat przechodzą bez zmian: `php artisan test --compact tests/Feature/TermDepositTest.php tests/Feature/ArchiveTermDepositTest.php`

#### Manual

- [x] 1.5 `/dev/ui/term-deposits` lokalnie pokazuje 6 scenariuszy z nagłówkami, a `/term-deposits` wygląda tak samo jak przed fazą
- [x] 1.6 Zrzuty „przed” (2 widoki + dialog, 1280 i 390 px, jasny i ciemny) są w `screenshots/before/`

### Phase 2: Jeden widok (C1, C3, C4, C5)

#### Automated

- [ ] 2.1 Testy lokat przechodzą (redirect z filtrem dla 5 akcji): `php artisan test --compact tests/Feature/TermDepositTest.php tests/Feature/ArchiveTermDepositTest.php`
- [ ] 2.2 Skan na plikach widoku daje 0 trafień: `node scripts/check-ui-literals.mjs resources/js/pages/TermDeposits.vue resources/js/components/TermDepositFormDialog.vue`
- [ ] 2.3 Lint, format, typy i testy frontendu (w tym `ui-contract.test.ts` bez wpisu `pages/TermDeposits.vue`) przechodzą: `npm run check && npm run types:check && npm test`
- [ ] 2.4 Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- [ ] 2.5 Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual

- [ ] 2.6 Na podglądzie: zaznaczenie „Pokaż zarchiwizowane” pokazuje zarchiwizowane lokaty i zmienia URL, odznaczenie je chowa, a odświeżenie z `?show_archived=1` pokazuje zaznaczony przełącznik
- [ ] 2.7 Z włączonym filtrem: archiwizacja, przywrócenie, edycja, dodanie i usunięcie lokaty wracają do listy z włączonym filtrem
- [ ] 2.8 Dialog usuwania ma czerwony przycisk ze spinnerem w trakcie żądania, a Anuluj, X i Escape nie zamykają go w tym czasie
- [ ] 2.9 Suma 0 zł jest bez koloru, ujemna czerwona, dodatnia zielona; „Lokaty” pada raz poza okruszkami
- [ ] 2.10 Zarchiwizowany wiersz jest szary bez przezroczystości (przycisk Przywróć ma pełny kontrast), a ujemne saldo w nim jest czerwone
- [ ] 2.11 Zrzuty `after-phase-2/` są w folderze zmiany

### Phase 3: Stany i nowe propsy (C2 + macierz)

#### Automated

- [ ] 3.1 Testy propsów `archived_term_deposits_total_minor_units` i `archived_term_deposits_count` przechodzą: `php artisan test --compact tests/Feature/TermDepositTest.php tests/Feature/ArchiveTermDepositTest.php`
- [ ] 3.2 Skan na plikach widoku daje 0 trafień: `node scripts/check-ui-literals.mjs resources/js/pages/TermDeposits.vue resources/js/components/TermDepositFormDialog.vue resources/js/pages/dev/TermDepositsKitchenSink.vue`
- [ ] 3.3 Lint, format, typy i testy frontendu przechodzą: `npm run check && npm run types:check && npm test`
- [ ] 3.4 Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- [ ] 3.5 Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual

- [ ] 3.6 Z jedną zarchiwizowaną lokatą z saldem: kafelek pokazuje „W tym zarchiwizowane: X zł” przy włączonym i wyłączonym filtrze; bez zarchiwizowanych sald stopki nie ma
- [ ] 3.7 Po zarchiwizowaniu wszystkich lokat (filtr wyłączony) widać „Wszystkie lokaty są zarchiwizowane”, a „Pokaż zarchiwizowane” je pokazuje
- [ ] 3.8 Nowy użytkownik bez lokat nie widzi przełącznika i widzi „Brak lokat”
- [ ] 3.9 W trakcie przeładowania przełącznik jest wyłączony
- [ ] 3.10 Wpis `12,3,4` w saldzie otwarcia pokazuje komunikat przy polu i blokuje Zapisz; poprawny wpis zdejmuje blokadę; Zapisz pokazuje spinner w trakcie żądania
- [ ] 3.11 Każda komórka macierzy w `/dev/ui/term-deposits` jest pokazana albo ma N/A z powodem, w obu motywach
- [ ] 3.12 Zrzuty `after-phase-3/` (z ujęciami stanów) są w folderze zmiany

### Phase 4: Zabezpieczenie

#### Automated

- [ ] 4.1 Skan przechodzi na rozszerzonej liście: `npm run check:ui`
- [ ] 4.2 Pełna bramka CI przechodzi: `composer ci:check` (obraz Sail)
- [ ] 4.3 Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual

- [ ] 4.4 Reguły w `js.md` są zgodne z `design-system.md` i nie przeczą sobie (w tym wyjątek FR-TD-08)
- [ ] 4.5 Lista kontrolna `ui-quality-checklist` przejrzana punkt po punkcie z dowodami w zrzutach i kitchen sinku
