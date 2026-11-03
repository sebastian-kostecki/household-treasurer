# Accounts UI contract pass — Implementation Plan

## Overview

Przeprowadzamy jeden widok, Konta (`resources/js/pages/Accounts.vue` z `AccountFormDialog.vue` i `AccountBankSelect.vue`), przez kontrakt 10x-ui. Kolejność: kitchen sink i biblioteka → wartości kontraktu w `components/ui` i notatkach → zarzuty C1–C5 w widoku → macierz 7 stanów → zabezpieczenie. Najważniejsza poprawka jest funkcjonalna: przełącznik „Pokaż zarchiwizowane” dziś nie działa, więc zarchiwizowanego konta nie da się zobaczyć ani przywrócić z UI (C1, issue #186). Wejście: `research.md` (`## Charges` C1–C5, macierz stanów) i decyzje z sesji planowania.

## Current State Analysis

- **Tokeny są w porządku, kompozycja i stany nie.** Skan 10x-ui na plikach widoku: 3 trafienia, z czego `check:ui` złapałby 2 (`AccountBankSelect.vue:66,89` `text-[10px]`). `hover:bg-foreground/[0.03]` (`Accounts.vue:157`) to udokumentowana konwencja list, nie zarzut.
- **C1 (martwy przełącznik):** `Accounts.vue:124-128` wiąże `:checked` / `@update:checked`, których reka-ui 2.11 `CheckboxRoot` nie zna (ma `modelValue` / `update:modelValue`). `toggleShowArchived` (`:92-98`) nigdy się nie wykonuje. Formularze archiwizacji i przywrócenia (`:242,280`) nie wysyłają `show_archived`, więc przekierowanie w `AccountController.php:110,124` zawsze ląduje na `/accounts`. `store`, `update` i `destroy` (`:57,76,96`) przekierowują na gołe `accounts.index`. Wayfinder przyjmuje query w `.form(args, { query })` (`resources/js/actions/.../AccountController.ts`, `archiveForm(args, options?: RouteQueryOptions)`), a `$request->boolean('show_archived')` czyta też query string.
- **C2 (dialogi potwierdzenia):** trzy kopie `Dialog > IconAction as-dialog-trigger > DialogContent > Form > DialogHeader > DialogFooter` (`Accounts.vue:194-309`). Brak spinnera; Anuluj, X i Escape działają w trakcie żądania; slot `Form` przy usuwaniu czyta tylko `processing` (`:204`), więc `errors.account` z `AccountController.php:83-87` nigdzie się nie pokazuje. W repo nie ma wspólnego komponentu potwierdzenia ani `ui/alert-dialog`. `DialogContent` ma prop `showCloseButton` (`components/ui/dialog/DialogContent.vue:19`).
- **C3 (kafelek znaku banku):** `AccountBankSelect.vue:56,79` to ręczne kopie kafelka z `AccountMarkCompact.vue:40-68` (inny radius `rounded-lg` vs `rounded-md` przy `size-5`, kropka `text-[10px]` zamiast `Building2` dla „Inny bank”). `AccountMarkCompact` przyjmuje `AccountIdentityFields` (`name`, `type`, `bank`, `mark_url` — `lib/account-identity.ts:15-20`).
- **C4 (dwa dropdowny, nieaktualna reguła):** `AccountFormDialog.vue:154-158` natywny `.native-select` obok Reka `Select` w `AccountBankSelect.vue:51`. `.ai/skills/openship-ui-development/SKILL.md:45` nazywa Reka Select „unused”, a ma 5 użyć w kodzie domenowym. `SelectTrigger` i `SelectContent` mają `rounded-md`, a `Input` i `Button` mają `rounded-xl` (`.docs/design-system.md:15`: kontrolki `rounded-xl`).
- **C5 (kwoty i nagłówek):** `liquidityClass` (`Accounts.vue:58-60`) maluje 0 zł na zielono, a `balanceClass` (`:75-85`) powtarza `amountToneClass` (`lib/amount-tone.ts`). Suma to ręczny `Card` + `CardTitle text-2xl` bez `tabular-nums` (`:112-121`), choć jest `StatTile`. Tytuł „Konta” powtarza się w `ListSurface` (`:151`). Etykieta „Łączna płynność” (`lang/pl/ui.php:372`) to termin, którego `CONTEXT.md:160-161` każe unikać. Ta sama liczba na Dashboardzie nazywa się „Płynność dysponowalna”: `SpendableLiquidity::forUser` sumuje wszystkie konta, także zarchiwizowane (`app/Support/SpendableLiquidity.php:10-14`), tak samo jak `AccountController.php:38`.
- **Stany:** gdy wszystkie konta są zarchiwizowane, a przełącznik jest wyłączony, strona pokazuje „Dodaj pierwsze konto” nad niezerową sumą (`Accounts.vue:137-147`, `AccountController.php:29-38`). Pole salda otwarcia po nieparsowalnym wpisie po cichu zachowuje ostatnią wartość (`AccountFormDialog.vue:100-106`). Pola nie mają `aria-invalid`. X w dialogu ma `focus:ring-2 ring-offset-2` zamiast kontraktu i angielski `sr-only` „Close” (`DialogContent.vue:46-49`).
- **Narzędzia:** kitchen sinki z kontrolerem i testem pod `/dev/ui/*` (`/dev/ui/dashboard`, `/dev/ui/sidebar`; `Dev/DashboardKitchenSinkController.php`, `tests/Feature/DashboardKitchenSinkTest.php`), obok nich `Route::inertia` tylko dla `local` (`/dev/kitchen-sink/budget`, `/dev/month-plan`). Trzy straże literałów: `check:ui` (lista `FILES`), `ui-contract.test.ts` (Budżet) i `tests/Unit/MonthPlanHardcodedUiTest.php`. `npm run check:ui` (`scripts/check-ui-literals.mjs`, lista `FILES`) działa w `composer ci:check` i CI. Nie ma testów komponentów Vue (brak `@vue/test-utils`). `playwright-core` i Chromium leżą w cache npx i `~/.cache/ms-playwright` i tak samo użył ich `sidebar-ui-contract`. Motyw jest w `localStorage` i cookie `appearance` (`composables/useAppearance.ts:40,56,111`).
- **Tłumaczenia:** `UiTranslations.accounts` to `Record<string, string>` (`types/shell.ts:42`), więc nowe klucze w `lang/pl/ui.php` nie wymagają zmiany typu.
- **Baza gałęzi:** `accounts-ui-contract` (8102669) jest przodkiem `development` (317b07f), który ma już scalone `budget-view-ui`, `month-plan-ui-contract` i `sidebar-ui-contract`. Pliki Kont, `components/ui/*` i `scripts/check-ui-literals.mjs` są na obu identyczne. Zmieniły się `.ai/rules/js.md` (jest już sekcja „Bind the reka-ui Checkbox with v-model, never :checked”), `.docs/design-system.md` (nagłówek nadal `text-xl` w `:23`, „Gotowe elementy” `:57`, „Zmiana systemu” `:98`, konwencja zrzutów), `lang/pl/ui.php` (`month_plan.amount_invalid` „Wpisz kwotę, np. 1200,50.”) i doszedł `resources/js/ui-contract.test.ts`, który ma `pages/Accounts.vue` na liście `DEAD_CHECKBOX_BINDING_EXCEPTIONS` i failuje, gdy plik z listy nie zawiera już martwego wiązania. Ten plan opisuje stan `development`.
- **Worktree:** brak `vendor/` i wygenerowanych katalogów Wayfindera. Przed pierwszą kontrolą: przewinąć gałąź do `development` (`git merge --ff-only development`), potem kroki „Fresh checkout or worktree” z `AGENTS.md`.

## Desired End State

- Przełącznik „Pokaż zarchiwizowane” działa, pokazuje prawdziwy stan, jest wyłączony w trakcie przeładowania i widać go tylko wtedy, gdy są zarchiwizowane konta albo filtr jest włączony. Każda akcja na stronie (dodaj, edytuj, usuń, archiwizuj, przywróć) wraca z zachowanym filtrem.
- Usuń, archiwizuj i przywróć to jeden `ConfirmActionDialog`: spinner w przycisku, zablokowane zamykanie w trakcie żądania, błąd serwera widoczny w dialogu.
- Selektor banku pokazuje ten sam kafelek co lista (`AccountMarkCompact`), a „Inny bank” ma ikonę `Building2`. Wyzwalacz i lista `Select` mają `rounded-xl`, jak pola obok.
- Suma to `StatTile` „Płynność dysponowalna” z `amountToneClass` (0 zł bez koloru). Salda kont mają ton z `amountToneClass`. „Konta” występuje raz jako `h1`.
- Gdy wszystkie konta są zarchiwizowane: pusty stan „Wszystkie konta są zarchiwizowane” z akcjami „Pokaż zarchiwizowane” i „Dodaj konto”.
- Nieparsowalne saldo otwarcia pokazuje polski komunikat przy polu, ustawia `aria-invalid` i blokuje Zapisz.
- X w dialogach ma pierścień fokusu z kontraktu (3 px `ring/50` na `focus-visible`) i nazwę „Zamknij”.
- `/dev/ui/accounts` (tylko `local`) pokazuje widok we wszystkich scenariuszach z wypełnioną macierzą 7 stanów. W folderze zmiany są zrzuty `before/`, `after-phase-2/`, `after-phase-3/` i `after-phase-4/` (1280 i 390 px, jasny i ciemny).
- `check:ui` obejmuje pliki widoku. `.ai/rules/js.md`, `SKILL.md` i `.docs/design-system.md` opisują nowe elementy i reguły.

Weryfikacja: `composer ci:check` (obraz Sail) i `vendor/bin/sloppy ci` (host) przechodzą, `check:ui` daje 0 trafień, człowiek ogląda kitchen sink i zrzuty.

### Key Discoveries:

- Wzorzec poprawionego przełącznika jest w `resources/js/pages/Budget.vue` na `development` (`:93-106,151-166`, z `budget-view-ui`): lokalny `ref` z propsa, `watch` propsa, wizyta tylko przy zmianie przez użytkownika, resynchronizacja w `onFinish`.
- Precedens zmiany `components/ui` dla całej aplikacji pod kontrakt: warianty `Badge` i `Alert variant="warning"`. `components/ui/*` jest poza lint i fmt (`vite.config.ts`), więc trzeba ręcznie zachować styl pliku (podwójne cudzysłowy, bez średników).
- `parseMoneyInputToMinorUnits` (`lib/format.ts:41-56`) przyjmuje `12,` i `1 234,56`, odrzuca pusty ciąg i `12,3,4`, więc walidację można liczyć na żywo z wartości pola.
- `IndexEmptyState` przyjmuje `title`, `description?` i slot `actions`. `ListSurface` ma `title?` opcjonalny (`ListSurface.vue:15-20`).
- Po dodaniu trasy `npm run build` przed `npm run types:check` (Wayfinder).

## What We're NOT Doing

- **Przełącznik w `TermDeposits.vue:126-130` i `OperationsImport.vue:351-357`.** Inne widoki, issue #186 zostaje otwarte dla nich.
- **13 dialogów potwierdzenia poza Kontami** (TermDeposits, Budget, BudgetCategoryRowActions, …). Przejdą na `ConfirmActionDialog` w przebiegach swoich widoków.
- **Walidacja kwot w innych formularzach** (TermDeposit, Category, Income, Expense, OwnTransfer, CapitalMovement). Każdy ma własne zachowanie przy nieparsowalnym wpisie.
- **`ui/alert-dialog`.** Potwierdzenie stoi na `ui/dialog`, tak jak pozostałe kopie.
- **X w `ui/sheet` i promień `DropdownMenuContent`.** Ten sam problem, ale ten widok ich nie używa.
- **Rozmiar `h1` na innych stronach.** Dokument dostosowujemy do kodu (`text-lg`), stron nie zmieniamy.
- **Angielskie komunikaty walidacji i toastów** (`AccountController.php:54,73,93,107,121`). i18n, osobna zmiana.
- **Duplikat mapy znaków banków PHP/TS** (`app/Enums/Bank.php:18-32`, `lib/account-identity.ts:45-60`). Dane, nie kontrakt UI.
- **Usunięcie `is_negative` z payloadu kont.** Po fazie 3 strona go nie czyta, ale usunięcie zmienia kontrakt propsów bez zysku dla użytkownika.
- **Playwright jako zależność i `toHaveScreenshot`.** Skrypt zrzutów jest jednorazowy i niecommitowany (konwencja `design-system.md`, „Kitchen sink i zrzuty”), bez zmiany `package.json`.
- **Typ konta jako `SegmentedControl`.** Komponent doszedł na `development` z `month-plan-ui-contract`; decyzja C4 zostawia natywny select dla typu. Ewentualna zamiana to osobny przebieg.
- **Hover wierszy `hover:bg-foreground/[0.03]`.** Udokumentowana konwencja.

## Implementation Approach

Kolejność według routera 10x-ui. Faza 1 buduje kitchen sink i `ConfirmActionDialog` bez dotykania widoku, żeby zrobić zrzuty „przed”. Faza 2 zmienia prymitywy `ui/select` i `ui/dialog` oraz notatki systemu. To jedyna faza, która zmienia wygląd innych widoków, więc ma własne zrzuty i sprawdzenie formularzy z `Select`. Faza 3 wymienia markup widoku (C1–C5). Faza 4 dodaje brakujące stany i jeden nowy prop. Faza 5 zostawia regułę i skan.

## Critical Implementation Details

**Zrzuty „przed” w fazie 1.** Muszą powstać, zanim zmieni się `ui/select`, `ui/dialog` i `Accounts.vue`. Scenariusz `all-archived` w fazie 1 celowo pokazuje dzisiejszy błędny pusty stan.

**Zamykanie dialogu w trakcie żądania.** `ConfirmActionDialog` steruje `open` lokalnie i ignoruje każde `update:open(false)`, dopóki `processing` jest prawdą. To jedno miejsce blokuje X, Escape, klik poza dialogiem i Anuluj, więc nie trzeba osobnych handlerów na każde zdarzenie reka-ui.

**Pierścień bez wartości arbitralnej.** Na X używamy `focus-visible:ring-3`, nie `ring-[3px]` (wzorzec skanu `-\[[0-9.]+(px|rem)\]`), mimo że `ui/button/index.ts:7` ma `ring-[3px]`.

## Phase 1: Kitchen sink i biblioteka

### Overview

Strona dev-only renderuje widok Kont w każdym scenariuszu danych. Dochodzi `ConfirmActionDialog` (jeszcze nieużywany) i skrypt zrzutów. Robimy zrzuty „przed”. Widok Kont się nie zmienia.

### Changes Required:

#### 1. Typy propsów strony Konta

**File**: `resources/js/types/accounts.ts` (nowy), `resources/js/pages/Accounts.vue`, `resources/js/components/AccountFormDialog.vue`

**Intent**: fixture'y kitchen sinka sprawdzane przez `vue-tsc` względem tego samego kontraktu co strona.

**Contract**: eksport `AccountFormAccount` (przeniesiony z `AccountFormDialog.vue:29-36`), `AccountRow` (`Accounts.vue:36-44`) i `AccountsPageProps` (`accounts`, `liquidity_total_minor_units`, `show_archived`, dokładnie jak `Accounts.vue:46-50`). `Accounts.vue` używa `defineProps<AccountsPageProps>()`, `AccountFormDialog.vue` importuje `AccountFormAccount` z modułu typów. PHP bez zmian.

#### 2. Kontroler, trasa i test kitchen sinka

**File**: `app/Http/Controllers/Dev/AccountsKitchenSinkController.php` (nowy), `routes/web.php`, `tests/Feature/AccountsKitchenSinkTest.php` (nowy)

**Intent**: ten sam wzorzec co `/dev/ui/dashboard`: za logowaniem, 404 poza `local`.

**Contract**: `GET dev/ui/accounts`, nazwa `dev.ui.accounts`, w grupie `auth` + `verified`, obok `dev.ui.dashboard`. Kontroler `abort_unless(app()->isLocal(), 404)` i Inertia `dev/AccountsKitchenSink` bez własnych propsów. Test kopiuje trzy przypadki z `DashboardKitchenSinkTest.php` (gość → login, 404 w `production`, strona przy `local`).

#### 3. Strona kitchen sinka i fixture'y

**File**: `resources/js/pages/dev/AccountsKitchenSink.vue` (nowy), `resources/js/pages/dev/accounts-fixtures.ts` (nowy)

**Intent**: jeden ekran ze wszystkimi scenariuszami widoku naraz. To bramka wizualna i dowód do przeglądu.

**Contract**: fixture'y typu `AccountsPageProps`:
- `default`: konto bankowe ze znakiem (np. mBank), konto „Inny bank”, gotówka; salda dodatnie, zero i ujemne; mieszanka `can_delete` / `can_archive`;
- `with-archived`: `show_archived: true`, dwa zarchiwizowane wiersze z `can_restore`;
- `zero-total`: suma 0, salda 0;
- `negative-total`: suma ujemna;
- `empty-new-user`: brak kont, suma 0;
- `all-archived`: `accounts: []`, suma ≠ 0, `show_archived: false`.

Strona renderuje `Accounts.vue` z każdym fixture'em pod nagłówkiem scenariusza (wzorzec `DashboardKitchenSink.vue`). Nagłówek strony mówi, że to strona deweloperska, a zduplikowane `id` pól w kilku `AccountFormDialog` są tu dopuszczalne. Akcje formularzy (POST) z kitchen sinka nie mają sensu, bo fixture'y nie istnieją w bazie, i nagłówek też to mówi.

#### 4. Komponent potwierdzenia

**File**: `resources/js/components/ConfirmActionDialog.vue` (nowy)

**Intent**: jedno miejsce na wygląd i zachowanie potwierdzeń (C2). Na razie nikt go nie używa.

**Contract**: propsy `form: RouteFormDefinition<…>` (wynik `Controller.action.form(...)` z `@/wayfinder`), `title: string`, `description: string`, `confirmLabel: string`, `cancelLabel: string`, `destructive?: boolean`. Slot `trigger` (np. `IconAction as-dialog-trigger`). Budowa: `Dialog` (lokalne `open`) > slot `trigger` > `DialogContent` > `Form v-bind="form"` (slot `{ processing, errors }`) > `DialogHeader` > komunikaty `errors` przez `InputError` > `DialogFooter` (Anuluj `variant="secondary"`, `:disabled="processing"`; potwierdzenie `type="submit"`, `variant="destructive"` gdy `destructive`, `:disabled="processing"`, `<Spinner v-if="processing" />` przed etykietą jak w `Login.vue:97-100`). Zamykanie ignorowane w trakcie `processing` (zob. Critical Implementation Details). Dialog zamyka się po `@success`.

#### 5. Skrypt zrzutów i zrzuty „przed”

**File**: skrypt poza repo (katalog tymczasowy sesji, np. `$CLAUDE_JOB_DIR/tmp/shoot-accounts.mjs`), `context/changes/accounts-ui-contract/screenshots/before/`

**Intent**: powtarzalne zrzuty po każdej fazie wizualnej bez nowej zależności w repo.

**Contract**: skrypt Node ładuje `playwright-core` z cache npx (jak `sidebar-ui-contract`) i headless Chromium z `~/.cache/ms-playwright`, loguje się jako `test@example.com` / `password` na podglądzie `http://localhost:8090` (przepis „Preview from a worktree” w `AGENTS.md`, osobna baza np. `accounts_ui_contract`). Argument: nazwa etapu (`before`, `after-phase-2`, …). Motyw ustawia przez `localStorage` i cookie `appearance` przed nawigacją. Zrzuty pełnej strony dla `/dev/ui/accounts` i `/accounts`, plus `/accounts` z otwartym dialogiem „Dodaj konto” i otwartym selektorem banku, przy 1280 i 390 px (jak `/dev/month-plan`), jasny i ciemny. Nazwy `<widok>-<szerokość>-<motyw>.png`. Skrypt nie jest commitowany (konwencja `design-system.md`); commitowane są tylko PNG. Gdy sesja się zmieni, odtwarza się go z tego opisu.

### Success Criteria:

#### Automated Verification:

- Lint i format przechodzą: `npm run check`
- Typy przechodzą po przebudowie Wayfindera: `npm run build && npm run types:check`
- Test Feature kitchen sinka przechodzi: `php artisan test --compact tests/Feature/AccountsKitchenSinkTest.php` (obraz Sail na worktree)
- Istniejące testy kont przechodzą bez zmian: `php artisan test --compact tests/Feature/AccountTest.php tests/Feature/ArchiveAccountTest.php`

#### Manual Verification:

- `/dev/ui/accounts` lokalnie pokazuje 6 scenariuszy z nagłówkami, a `/accounts` wygląda tak samo jak przed fazą
- Zrzuty „przed” (2 widoki + dialog, 1280 i 390 px, jasny i ciemny) są w `screenshots/before/`

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 2: Wartości kontraktu

### Overview

Prymitywy `ui/select` i `ui/dialog` przechodzą na udokumentowany kontrakt kontrolek i fokusu, a notatki systemu godzą się z kodem. To zmienia wszystkie formularze z `Select` i wszystkie dialogi w aplikacji.

### Changes Required:

#### 1. Promień `Select`

**File**: `resources/js/components/ui/select/SelectTrigger.vue`, `resources/js/components/ui/select/SelectContent.vue`

**Intent**: wyzwalacz i lista selektora mają te same rogi co `Input` i `Button` (C4, `.docs/design-system.md:15`).

**Contract**: `rounded-md` → `rounded-xl` w klasie bazowej obu plików. Reszta klas, `SelectItem` i styl pliku bez zmian.

#### 2. Przycisk zamknięcia dialogu

**File**: `resources/js/components/ui/dialog/DialogContent.vue`

**Intent**: fokus klawiatury na X wygląda jak na przyciskach, a czytnik słyszy polską nazwę.

**Contract**: w `DialogClose` (`:46`) klasy `ring-offset-background focus:ring-ring focus:ring-2 focus:ring-offset-2 focus:outline-hidden` zastępuje `outline-none focus-visible:ring-3 focus-visible:ring-ring/50` (oraz `rounded-md`, żeby pierścień miał kształt kontrolki). `sr-only` „Close” → „Zamknij” (aplikacja ma tylko locale `pl`, a prymitywy `ui` nie importują tłumaczeń). Prop `showCloseButton` bez zmian.

#### 3. Notatki systemu projektowego

**File**: `.docs/design-system.md`

**Intent**: dokument mówi to, co robi kod, i rozstrzyga regułę dropdownów (decyzje: nagłówek, C4).

**Contract**: „Typografia” (`:23`): nagłówek strony domenowej `text-lg font-semibold` (strony dev mogą mieć `text-xl`). „Powierzchnie i rytm”: nowy punkt „Selecty: `.native-select` dla prostych wyliczeń bez ikon; `Select` (Reka) gdy opcja pokazuje ikonę lub logo (np. `AccountBankSelect`). Oba mają `rounded-xl`.” Linia 15 bez zmian (już mówi o fokusie 3 px `ring/50`).

#### 4. Zrzuty po fazie

**File**: `context/changes/accounts-ui-contract/screenshots/after-phase-2/`

**Intent**: dowód zmiany prymitywów.

**Contract**: skrypt zrzutów z etapem `after-phase-2` po `npm run build` na podglądzie.

### Success Criteria:

#### Automated Verification:

- Lint, format i skan przechodzą: `npm run check && npm run check:ui`
- Typy przechodzą: `npm run types:check`
- Testy frontendu przechodzą: `npm test`

#### Manual Verification:

- W dialogu „Dodaj konto” wyzwalacz banku ma te same rogi co pole „Nazwa”, a otwarta lista też ma `rounded-xl`
- Tab do X w dowolnym dialogu pokazuje pierścień 3 px `ring/50`, a klik myszą go nie pokazuje
- Formularze z `Select` poza Kontami (wydatek z `CategorySelect`, przelew własny z `AccountSelect`, ruch kapitału) wyglądają poprawnie w obu motywach
- Zrzuty `after-phase-2/` są w folderze zmiany

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 3: Jeden widok (C1–C5)

### Overview

Widok Kont przechodzi na komponenty i helpery repo, a przełącznik zaczyna działać. Po fazie skan na plikach widoku daje 0 trafień.

### Changes Required:

#### 1. Przełącznik i zachowanie filtra (C1)

**File**: `resources/js/pages/Accounts.vue`, `resources/js/components/AccountFormDialog.vue`, `app/Http/Controllers/AccountController.php`, `tests/Feature/ArchiveAccountTest.php`, `tests/Feature/AccountTest.php`

**Intent**: przełącznik pokazuje prawdziwy stan i przeładowuje listę, a każda akcja na stronie wraca z tym samym filtrem.

**Contract**:
- `Accounts.vue`: `showArchived = ref(props.show_archived)`, `watch` propsa → ref, `<Checkbox v-model="showArchived">`. Zmiana przez użytkownika (wartość różna od propsa) robi `router.get(accountsRoute({ query: { show_archived: checked ? 1 : undefined } }), {}, { preserveState: true, preserveScroll: true, onFinish: resync })` jak w `Budget.vue`.
- `resources/js/ui-contract.test.ts`: `pages/Accounts.vue` znika z `DEAD_CHECKBOX_BINDING_EXCEPTIONS` w tym samym commicie (test failuje, dopóki wpis zostaje przy naprawionym pliku).
- Strona liczy `filterQuery` (`{ query: { show_archived: 1 } }` gdy filtr włączony, inaczej brak) i przekazuje go do każdego `.form(...)`: `destroy`, `archive`, `restore` oraz do `AccountFormDialog` przez nowy opcjonalny prop `showArchived?: boolean` (dialog dokleja query do `store.form` / `update.form`).
- `AccountController`: prywatna metoda zwracająca przekierowanie na `accounts.index` z `['show_archived' => 1]`, gdy `$request->boolean('show_archived')`. Używają jej `store`, `update`, `destroy` (gałąź sukcesu), `archive` i `restore`, zamiast pięciu osobnych `to_route`. `back()->withErrors` w `destroy` bez zmian.
- Testy Feature: dla `store`, `update` i `destroy` z `?show_archived=1` przekierowanie zachowuje query, bez niego nie (archive/restore już mają te przypadki w `ArchiveAccountTest.php:101-108`).

#### 2. Potwierdzenia przez `ConfirmActionDialog` (C2)

**File**: `resources/js/pages/Accounts.vue`

**Intent**: trzy ręczne kopie dialogu znikają, a usuwanie pokazuje błąd serwera.

**Contract**: usuń (`destructive`), archiwizuj i przywróć to `ConfirmActionDialog` z `form` = `AccountController.<action>.form(account, filterQuery)`, istniejącymi kluczami `t.accounts.*_confirm_title` / `*_confirm_body` / etykietą akcji / `t.accounts.cancel` i `IconAction as-dialog-trigger` w slocie `trigger`. Warunki `v-if="account.can_*"` bez zmian. Nieużywane importy `Dialog*` i `Form` znikają ze strony.

#### 3. Kafelek w selektorze banku (C3)

**File**: `resources/js/components/AccountBankSelect.vue`

**Intent**: ten sam bank wygląda tak samo w selektorze, na liście i w operacjach.

**Contract**: oba ręczne kafelki (`:55-70`, `:78-93`) to `<AccountMarkCompact type="bank" :bank="…" :mark_url="bankMarkPath(…)" :name="bankLabel(…)" />` w rozmiarze domyślnym `sm`. Oba literały `text-[10px]` znikają. Ukryte pole `bank` bez zmian.

#### 4. Kwoty, kafelek sumy i tytuł (C5)

**File**: `resources/js/pages/Accounts.vue`, `lang/pl/ui.php`, `app/Http/Controllers/AccountController.php`, `tests/Feature/AccountTest.php`

**Intent**: zero nie jest zielone, suma jest kafelkiem KPI i nosi nazwę ze słownika, a „Konta” pada raz.

**Contract**:
- Ręczny `Card` sumy (`:112-121`) → `<StatTile :label="t.accounts.liquidity_total" :amount-minor-units="liquidity_total_minor_units" />`. `liquidityClass` znika.
- `balanceClass` → `amountToneClass(account.current_balance_minor_units)`.
- `ListSurface` bez `title`, z `description`.
- `lang/pl/ui.php:372` `liquidity_total` → „Płynność dysponowalna”.
- Kontroler: `liquidity_total_minor_units` z `SpendableLiquidity::forUser($request->user())` zamiast własnej sumy (ta sama wartość, jedno źródło definicji). Test `AccountTest` asercją `liquidity_total_minor_units` z kontem zarchiwizowanym pokazuje, że wartość się nie zmienia.
- `h1` zostaje `text-lg font-semibold` (decyzja: dokument dostosowany w fazie 2).

#### 5. Zrzuty i skan po fazie

**File**: `context/changes/accounts-ui-contract/screenshots/after-phase-3/`

**Intent**: dowód zmian widoku i spadku licznika literałów.

**Contract**: skrypt zrzutów z etapem `after-phase-3`. `node scripts/check-ui-literals.mjs resources/js/pages/Accounts.vue resources/js/components/AccountFormDialog.vue resources/js/components/AccountBankSelect.vue resources/js/components/ConfirmActionDialog.vue` daje 0 trafień.

### Success Criteria:

#### Automated Verification:

- Testy kont przechodzą (redirect z filtrem dla 5 akcji, suma płynności): `php artisan test --compact tests/Feature/AccountTest.php tests/Feature/ArchiveAccountTest.php`
- Skan na plikach widoku daje 0 trafień: `node scripts/check-ui-literals.mjs <pliki widoku>`
- Lint, format, typy i testy frontendu (w tym `ui-contract.test.ts` bez wpisu `pages/Accounts.vue`) przechodzą: `npm run check && npm run types:check && npm test`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual Verification:

- Na podglądzie: zaznaczenie „Pokaż zarchiwizowane” pokazuje zarchiwizowane konta i zmienia URL, odznaczenie je chowa, a odświeżenie z `?show_archived=1` pokazuje zaznaczony przełącznik
- Z włączonym filtrem: archiwizacja, przywrócenie, edycja i dodanie konta wracają do listy z włączonym filtrem
- Dialog usuwania ma czerwony przycisk ze spinnerem w trakcie żądania, a Anuluj, X i Escape nie zamykają go w tym czasie
- Selektor banku pokazuje te same kafelki co lista, a „Inny bank” ma ikonę budynku
- Suma 0 zł jest bez koloru, ujemna czerwona, dodatnia zielona, z etykietą „Płynność dysponowalna”; „Konta” pada raz poza okruszkami
- Zrzuty `after-phase-3/` są w folderze zmiany

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 4: Stany

### Overview

Widok dostaje brakujące stany: pusty przy samych zarchiwizowanych kontach, przełącznik w trakcie wizyty i błąd kwoty. Kitchen sink pokazuje wypełnioną macierz 7 stanów.

### Changes Required:

#### 1. Prop `archived_accounts_count`

**File**: `app/Http/Controllers/AccountController.php`, `resources/js/types/accounts.ts`, `resources/js/pages/dev/accounts-fixtures.ts`, `tests/Feature/AccountTest.php`

**Intent**: strona wie, czy pusta lista oznacza brak kont, czy tylko ukryte zarchiwizowane.

**Contract**: `index` zwraca `archived_accounts_count: int` (liczba zarchiwizowanych kont użytkownika, niezależnie od filtra). `AccountsPageProps` dostaje to samo pole, fixture'y je ustawiają (`all-archived` > 0, `empty-new-user` 0). Test `assertInertia`: 0 bez zarchiwizowanych, 2 przy dwóch zarchiwizowanych z filtrem wyłączonym i włączonym. Obie strony kontraktu w jednym commicie.

#### 2. Pusty stan i widoczność przełącznika

**File**: `resources/js/pages/Accounts.vue`, `lang/pl/ui.php`

**Intent**: każdy pusty stan mówi prawdę i daje wyjście.

**Contract**:
- `accounts.length === 0 && archived_accounts_count === 0` → obecny `IndexEmptyState` (bez zmian).
- `accounts.length === 0 && archived_accounts_count > 0` → `IndexEmptyState` z `t.accounts.all_archived_title` „Wszystkie konta są zarchiwizowane” i `t.accounts.all_archived_description` „Pokaż zarchiwizowane konta, aby je przejrzeć lub przywrócić.”; akcje: `Button` „Pokaż zarchiwizowane” (ustawia `showArchived = true`) i `Button variant="secondary"` „Dodaj konto”.
- Przełącznik renderowany tylko gdy `archived_accounts_count > 0 || show_archived`.

#### 3. Przełącznik w trakcie wizyty

**File**: `resources/js/pages/Accounts.vue`

**Intent**: użytkownik widzi, że lista się przeładowuje, i nie klika drugi raz.

**Contract**: `ref` `isTogglingArchived` ustawiany w `onStart`, czyszczony w `onFinish` wizyty przełącznika. `Checkbox` i przycisk „Pokaż zarchiwizowane” z pustego stanu mają `:disabled` na czas wizyty. Bez skeletonu: `preserveState` trzyma układ, strona nie ma deferred props.

#### 4. Błąd salda otwarcia i `aria-invalid`

**File**: `resources/js/components/AccountFormDialog.vue`, `resources/js/components/AccountBankSelect.vue`, `lang/pl/ui.php`

**Intent**: nie da się zapisać innej kwoty niż wpisana, a pola z błędem wyglądają na błędne.

**Contract**:
- `openingBalanceInvalid` liczone na żywo: `parseMoneyInputToMinorUnits(openingBalanceInput) === null`. Ukryte `opening_balance_minor_units` aktualizowane przy każdej poprawnej wartości (nie tylko na `blur`).
- Gdy `openingBalanceInvalid`: `InputError` pokazuje `t.accounts.opening_balance_invalid` „Wpisz kwotę, np. 1200,50.” (to samo brzmienie co `month_plan.amount_invalid`) zamiast błędu serwera, `Input` ma `aria-invalid="true"`, a Zapisz ma `:disabled="processing || openingBalanceInvalid"`. Otwarcie dialogu resetuje stan razem z polem.
- `aria-invalid` na `Input` nazwy, natywnym selekcie typu, polu daty i (przez nowy prop `invalid?: boolean` w `AccountBankSelect`) na `SelectTrigger`, gdy dany klucz `errors` jest ustawiony.

#### 5. Macierz w kitchen sinku

**File**: `resources/js/pages/dev/AccountsKitchenSink.vue`

**Intent**: każda komórka macierzy jest pokazana albo ma N/A z powodem.

**Contract**: tabela stanów jak w `DashboardKitchenSink.vue:11-40`:
- **default**: scenariusze `default`, `zero-total`, `negative-total`, `with-archived`;
- **hover**: zrzuty z działającej aplikacji (stan zależny od interakcji), wiersze i `IconAction`;
- **focus-visible**: zrzuty po Tab: przycisk „Dodaj konto”, przełącznik, `IconAction`, X dialogu, wyzwalacz banku;
- **disabled**: zrzut z opóźnionym POST (przycisk potwierdzenia ze spinnerem, zablokowane Anuluj) i Zapisz zablokowany przy nieparsowalnej kwocie;
- **error**: przycisk w kitchen sinku otwiera `AccountFormDialog` w trybie tworzenia, wpisanie `12,3,4` pokazuje komunikat; `errors.account` przy usuwaniu: test Feature `destroy` z historią + ręczne sprawdzenie na nieaktualnej stronie;
- **empty**: `empty-new-user` i `all-archived`;
- **loading**: przełącznik zablokowany w trakcie wizyty (zrzut z opóźnioną odpowiedzią); skeleton N/A, bo brak deferred props.

Skrypt zrzutów dostaje ujęcia stanów interakcyjnych: Tab do kontrolek, `page.route` opóźniający POST/GET, wpis `12,3,4`.

#### 6. Zrzuty po fazie

**File**: `context/changes/accounts-ui-contract/screenshots/after-phase-4/`

**Intent**: dowód macierzy stanów.

**Contract**: skrypt zrzutów z etapem `after-phase-4`, z ujęciami stanów.

### Success Criteria:

#### Automated Verification:

- Test propsa `archived_accounts_count` przechodzi: `php artisan test --compact tests/Feature/AccountTest.php`
- Skan na plikach widoku daje 0 trafień: `node scripts/check-ui-literals.mjs <pliki widoku> resources/js/pages/dev/AccountsKitchenSink.vue`
- Lint, format, typy i testy frontendu przechodzą: `npm run check && npm run types:check && npm test`
- Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual Verification:

- Po zarchiwizowaniu wszystkich kont (filtr wyłączony) widać „Wszystkie konta są zarchiwizowane”, a „Pokaż zarchiwizowane” je pokazuje
- Nowy użytkownik bez kont nie widzi przełącznika i widzi „Brak kont bankowych ani gotówkowych”
- W trakcie przeładowania przełącznik jest wyłączony
- Wpis `12,3,4` w saldzie otwarcia pokazuje komunikat przy polu i blokuje Zapisz; poprawny wpis zdejmuje blokadę
- Każda komórka macierzy w `/dev/ui/accounts` jest pokazana albo ma N/A z powodem, w obu motywach
- Zrzuty `after-phase-4/` (z ujęciami stanów) są w folderze zmiany

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed przejściem do następnej fazy.

---

## Phase 5: Zabezpieczenie

### Overview

Następny agent dostaje regułę i skan, które trzymają go przy kontrakcie. Odroczone zarzuty są zapisane.

### Changes Required:

#### 1. Lista plików skanu

**File**: `scripts/check-ui-literals.mjs`

**Intent**: `check:ui` (w `composer ci:check` i CI) pilnuje oczyszczonych plików Kont.

**Contract**: do `FILES` dochodzą `resources/js/pages/Accounts.vue`, `resources/js/components/AccountFormDialog.vue`, `resources/js/components/AccountBankSelect.vue`, `resources/js/components/AccountMarkCompact.vue`, `resources/js/components/ConfirmActionDialog.vue`, `resources/js/pages/dev/AccountsKitchenSink.vue`. Wzorzec bez zmian.

#### 2. Reguły dla agentów

**File**: `.ai/rules/js.md`, `.ai/skills/openship-ui-development/SKILL.md`

**Intent**: reguła, która przeczyła kodowi, znika, a nowe elementy mają nazwę w regule. Boost MCP nie jest podłączony, więc zgodnie z `AGENTS.md` edytujemy plik reguły bezpośrednio (indeks bez zmian, glob ten sam).

**Contract**:
- `js.md`, istniejąca sekcja „Bind the reka-ui Checkbox with v-model, never :checked” zostaje bez zmian (już opisuje poprawkę C1 i listę wyjątków `ui-contract.test.ts`).
- `js.md`, nowa sekcja o potwierdzeniach: akcje wymagające potwierdzenia używają `ConfirmActionDialog`, nie ręcznego `Dialog` + `Form`.
- `js.md`, w sekcji „Tokens and shared components come first”: `.native-select` dla prostych wyliczeń, `Select` (Reka) gdy opcja ma ikonę lub logo; „Component sandbox” wymienia `/dev/ui/dashboard` i `/dev/ui/accounts`.
- `SKILL.md:45`: zdanie o „unused Reka Select primitive” przepisane na tę samą regułę podziału.

#### 3. Notatki systemu i odroczone zarzuty

**File**: `.docs/design-system.md`, `context/changes/accounts-ui-contract/research.md`

**Intent**: „Gotowe elementy” wymieniają nowy komponent, a niezaadresowane rzeczy nie giną.

**Contract**: „Gotowe elementy” (`:57`): `ConfirmActionDialog` (potwierdzenie z `Form`, spinner, blokada zamykania w trakcie, błędy serwera) i `AccountMarkCompact` (kafelek znaku banku/gotówki). „Zmiana systemu” punkt 4 (`:98`): dopisany `/dev/ui/accounts` obok `/dev/ui/dashboard`. W `research.md` tabela „Deferred / rejected” dostaje: X w `ui/sheet`, promień `DropdownMenuContent`, walidację kwot w innych formularzach, nieużywany `is_negative`, 13 pozostałych kopii potwierdzeń (już jest) i przełączniki TermDeposits/OperationsImport (już są).

### Success Criteria:

#### Automated Verification:

- Skan przechodzi na rozszerzonej liście: `npm run check:ui`
- Pełna bramka CI przechodzi: `composer ci:check` (obraz Sail na worktree)
- Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual Verification:

- Reguły w `js.md` i `SKILL.md` są zgodne z `design-system.md` i nie przeczą sobie
- Lista kontrolna `ui-quality-checklist` przejrzana punkt po punkcie z dowodami w zrzutach i kitchen sinku

**Implementation Note**: po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka przed `/10x-impl-review`.

---

## Testing Strategy

### Unit Tests:

- Brak nowych testów TS: logika widoku żyje w komponentach Vue, a repo nie ma testów komponentów. `parseMoneyInputToMinorUnits` i `amountToneClass` mają już testy.

### Integration Tests:

- `AccountsKitchenSinkTest`: gość → login, 404 poza `local`, strona przy `local`.
- `AccountTest` / `ArchiveAccountTest`: przekierowanie z zachowanym `show_archived` dla `store`, `update`, `destroy`, `archive`, `restore` (z query i bez); `liquidity_total_minor_units` z kontem zarchiwizowanym; `archived_accounts_count` (0, 2 z filtrem i bez); istniejący przypadek `destroy` z historią zwraca `errors.account`.
- Brak migracji i surowego SQL, więc SQLite wystarcza dla tych testów; pełna bramka i tak idzie przez `composer ci:check` na obrazie Sail z PostgreSQL.

### Manual Testing Steps:

1. Podgląd z worktree (`AGENTS.md`, „Preview from a worktree”): osobna baza, `migrate --seed`, `npm run build`, port 8090, `test@example.com` / `password`.
2. Zarchiwizuj konto z historią, włącz i wyłącz filtr, przywróć je; sprawdź URL i stan przełącznika po każdej akcji.
3. Zarchiwizuj wszystkie konta z historią (konta bez historii usuń) i sprawdź pusty stan.
4. Otwórz „Dodaj konto”, wpisz `12,3,4`, potem `1 234,56`; zmień typ na gotówkę i z powrotem.
5. Przejdź Tabem po stronie i dialogu w obu motywach.
6. Nieaktualna strona: otwórz listę, w drugiej karcie dodaj operację do konta bez historii, w pierwszej spróbuj je usunąć; dialog pokazuje błąd.

## Performance Considerations

Bez znaczenia. `archived_accounts_count` liczy się z kolekcji już pobranej w `index`; `SpendableLiquidity::forUser` to jedno zapytanie `sum` zamiast sumy w pamięci.

## Migration Notes

Bez migracji danych.

- **Baza:** `budget-view-ui`, `month-plan-ui-contract` i `sidebar-ui-contract` są już w `development` (317b07f), a `accounts-ui-contract` (8102669) jest jego przodkiem. Przed fazą 1 gałąź przewija się do `development` (`--ff-only`), więc implementacja nie ma konfliktów z tamtymi zmianami.
- **Trzy straże literałów obok siebie** (`check:ui`, `ui-contract.test.ts`, `MonthPlanHardcodedUiTest.php`). Konta trafiają do `check:ui`, bo tak każe `js.md` i `design-system.md:98`; ujednolicenie straży to osobna decyzja.
- **Równoległe gałęzie UI otwarte po tej zmianie** (np. przyszły przebieg TermDeposits) dotkną `design-system.md` „Gotowe elementy” i `js.md`; sekcje łączyć, nie nadpisywać.
- Issue #186: PR zamyka część dotyczącą Kont; issue zostaje otwarte dla TermDeposits i OperationsImport.

## References

- Research: `context/changes/accounts-ui-contract/research.md`
- Precedens planu i kitchen sinka: `context/archive/2026-11-03-dashboard-ui-contract/plan.md`, `resources/js/pages/dev/DashboardKitchenSink.vue`
- Wzorzec przełącznika: `resources/js/pages/Budget.vue:93-106,151-166` (na `development`)
- Konwencja zrzutów i kitchen sinka: `.docs/design-system.md` „Kitchen sink i zrzuty” (na `development`)
- Precedens zrzutów: gałąź `sidebar-ui-contract`, `context/changes/sidebar-ui-contract/screenshots/before/`
- Kontrakt: `.docs/design-system.md`, `.ai/rules/js.md`, `.ai/rules/css.md`, `.claude/skills/10x-ui/references/ui-quality-checklist.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kitchen sink i biblioteka

#### Automated

- [x] 1.1 Lint i format przechodzą: `npm run check` — 86a31de
- [x] 1.2 Typy przechodzą po przebudowie Wayfindera: `npm run build && npm run types:check` — 86a31de
- [x] 1.3 Test Feature kitchen sinka przechodzi: `php artisan test --compact tests/Feature/AccountsKitchenSinkTest.php` (obraz Sail na worktree) — 86a31de
- [x] 1.4 Istniejące testy kont przechodzą bez zmian: `php artisan test --compact tests/Feature/AccountTest.php tests/Feature/ArchiveAccountTest.php` — 86a31de

#### Manual

- [x] 1.5 `/dev/ui/accounts` lokalnie pokazuje 6 scenariuszy z nagłówkami, a `/accounts` wygląda tak samo jak przed fazą — 86a31de
- [x] 1.6 Zrzuty „przed” (2 widoki + dialog, 1280 i 390 px, jasny i ciemny) są w `screenshots/before/` — 86a31de

### Phase 2: Wartości kontraktu

#### Automated

- [x] 2.1 Lint, format i skan przechodzą: `npm run check && npm run check:ui` — 914fe15
- [x] 2.2 Typy przechodzą: `npm run types:check` — 914fe15
- [x] 2.3 Testy frontendu przechodzą: `npm test` — 914fe15

#### Manual

- [x] 2.4 W dialogu „Dodaj konto” wyzwalacz banku ma te same rogi co pole „Nazwa”, a otwarta lista też ma `rounded-xl` — 914fe15
- [x] 2.5 Tab do X w dowolnym dialogu pokazuje pierścień 3 px `ring/50`, a klik myszą go nie pokazuje — 914fe15
- [x] 2.6 Formularze z `Select` poza Kontami (wydatek z `CategorySelect`, przelew własny z `AccountSelect`, ruch kapitału) wyglądają poprawnie w obu motywach — 914fe15
- [x] 2.7 Zrzuty `after-phase-2/` są w folderze zmiany — 914fe15

### Phase 3: Jeden widok (C1–C5)

#### Automated

- [x] 3.1 Testy kont przechodzą (redirect z filtrem dla 5 akcji, suma płynności): `php artisan test --compact tests/Feature/AccountTest.php tests/Feature/ArchiveAccountTest.php`
- [x] 3.2 Skan na plikach widoku daje 0 trafień: `node scripts/check-ui-literals.mjs <pliki widoku>`
- [x] 3.3 Lint, format, typy i testy frontendu (w tym `ui-contract.test.ts` bez wpisu `pages/Accounts.vue`) przechodzą: `npm run check && npm run types:check && npm test`
- [x] 3.4 Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- [x] 3.5 Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual

- [x] 3.6 Na podglądzie: zaznaczenie „Pokaż zarchiwizowane” pokazuje zarchiwizowane konta i zmienia URL, odznaczenie je chowa, a odświeżenie z `?show_archived=1` pokazuje zaznaczony przełącznik
- [x] 3.7 Z włączonym filtrem: archiwizacja, przywrócenie, edycja i dodanie konta wracają do listy z włączonym filtrem
- [x] 3.8 Dialog usuwania ma czerwony przycisk ze spinnerem w trakcie żądania, a Anuluj, X i Escape nie zamykają go w tym czasie
- [x] 3.9 Selektor banku pokazuje te same kafelki co lista, a „Inny bank” ma ikonę budynku
- [x] 3.10 Suma 0 zł jest bez koloru, ujemna czerwona, dodatnia zielona, z etykietą „Płynność dysponowalna”; „Konta” pada raz poza okruszkami
- [x] 3.11 Zrzuty `after-phase-3/` są w folderze zmiany

### Phase 4: Stany

#### Automated

- [ ] 4.1 Test propsa `archived_accounts_count` przechodzi: `php artisan test --compact tests/Feature/AccountTest.php`
- [ ] 4.2 Skan na plikach widoku daje 0 trafień: `node scripts/check-ui-literals.mjs <pliki widoku> resources/js/pages/dev/AccountsKitchenSink.vue`
- [ ] 4.3 Lint, format, typy i testy frontendu przechodzą: `npm run check && npm run types:check && npm test`
- [ ] 4.4 Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual

- [ ] 4.5 Po zarchiwizowaniu wszystkich kont (filtr wyłączony) widać „Wszystkie konta są zarchiwizowane”, a „Pokaż zarchiwizowane” je pokazuje
- [ ] 4.6 Nowy użytkownik bez kont nie widzi przełącznika i widzi „Brak kont bankowych ani gotówkowych”
- [ ] 4.7 W trakcie przeładowania przełącznik jest wyłączony
- [ ] 4.8 Wpis `12,3,4` w saldzie otwarcia pokazuje komunikat przy polu i blokuje Zapisz; poprawny wpis zdejmuje blokadę
- [ ] 4.9 Każda komórka macierzy w `/dev/ui/accounts` jest pokazana albo ma N/A z powodem, w obu motywach
- [ ] 4.10 Zrzuty `after-phase-4/` (z ujęciami stanów) są w folderze zmiany

### Phase 5: Zabezpieczenie

#### Automated

- [ ] 5.1 Skan przechodzi na rozszerzonej liście: `npm run check:ui`
- [ ] 5.2 Pełna bramka CI przechodzi: `composer ci:check` (obraz Sail na worktree)
- [ ] 5.3 Sloppy bez nowych ustaleń: `vendor/bin/sloppy ci` (host)

#### Manual

- [ ] 5.4 Reguły w `js.md` i `SKILL.md` są zgodne z `design-system.md` i nie przeczą sobie
- [ ] 5.5 Lista kontrolna `ui-quality-checklist` przejrzana punkt po punkcie z dowodami w zrzutach i kitchen sinku
