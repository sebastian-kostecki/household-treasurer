# Kontrakt UI widoku Operacje — plan implementacji

## Overview

Widok `/operations` (`resources/js/pages/Operations.vue`, wiersze `FinancialOperationRow` / `OwnTransferRow` / `CapitalMovementRow`, panel `OperationsFilters`) przechodzi na kontrakt istniejącego systemu projektowego:

- nazwane klasy nagłówka kolumn i wiersza listy w `app.css`, z jednym przepisem podpisu kolumny (`column-caption`);
- jedna akcja główna w wierszu tytułu (Import), pozostałe `outline`, wszystkie z ikoną;
- widoczny i dostępny powód wyłączenia „Dodaj transfer” / „Dodaj ruch kapitału”;
- pusty stan i filtry liczone z istnienia dowolnego z trzech rodzajów zapisów;
- stany z macierzy: fokus paginacji, zajętość list w czasie wizyty, spinner przy usuwaniu, dymek opisu z banku z klawiatury.

Na końcu zmiany zostają kitchen sink `/dev/operations` (7 stanów), rozszerzony strażnik `npm run check:ui` i reguła dla następnego agenta. Domena list (trzy sekcje, osobne paginatory, ukrywanie przelewów przy filtrach) się nie zmienia.

## Current State Analysis

Pełny audyt z dowodami: `context/changes/operations-ui-contract/research.md` (`## Charges` C1–C5, Deferred D1–D10, Decyzje 1–6). W skrócie:

- **C1:** wiersz nagłówka kolumn złożony ręcznie 3× (`Operations.vue:232,272,305`: `… text-[11px] font-medium tracking-wider uppercase md:grid`). Ten sam przepis 11 px jest w `app.css` 4 razy: `column-caption` (`:199`), `.column-header` (`:233`), `.list-surface-table thead th` (`:267`), `.budget-category-header-row` (`:375`).
- **C2:** identyczny ciąg klas wiersza z `hover:bg-foreground/[0.03]` w trzech wierszach (`FinancialOperationRow.vue:49`, `OwnTransferRow.vue:27`, `CapitalMovementRow.vue:26`). Wartość jest w kontrakcie (`design-system.md:17`); brakuje nazwy. Precedens: `.budget-category-row` (`app.css:371`).
- **C3:** powód wyłączenia w `:title` (`Operations.vue:144-168`), a `Button` ma `disabled:pointer-events-none` (`ui/button/index.ts:7`) — podpowiedź nigdy się nie pokaże, nie ma jej też dla klawiatury i czytnika.
- **C4:** pięć przycisków primary, dwa bez ikon (`Operations.vue:136-174`). Issue #35 kryt. 21: pięć osobnych kontrolek, bez dropdownu.
- **C5:** filtry i pusty stan zależą od `hasFinancialOperations` (`OperationsController.php:189`, `Operations.vue:178,189`). Użytkownik z samymi przelewami / ruchami kapitału widzi „Brak operacji finansowych” nad własną listą i nie ma filtrów.
- **Stany:** linki paginacji bez pierścienia 3 px, wyłączony link (`href="#"`, `pointer-events-none`) osiągalny Tabem bez `aria-disabled` (`PaginationLinks.vue:31-46`); listy nie sygnalizują wizyty (tylko globalny NProgress); potwierdzenie usunięcia bez spinnera (`FinancialOperationActions.vue:80-86` i odpowiedniki); `Spinner` ma zaszyte `aria-label="Loading"`; dymek surowego opisu z banku na nieosiągalnym z klawiatury `div` (`OperationDescriptionCell.vue:44-47`).
- **Strażnik:** `scripts/check-ui-literals.mjs` nie łapie `/[0.NN]` i nie obejmuje plików Operacji.

## Desired End State

- **C1–C2.** Pliki widoku Operacji nie zawierają `text-[11px]` ani `/[0.03]`. Nagłówki to `operations-grid operations-header-row`, wiersze `operations-grid operations-row`. W `app.css` przepis 11 px istnieje raz (`column-caption`); `.column-header`, `.list-surface-table thead th` i `.budget-category-header-row` robią `@apply column-caption`. Wygląd Planu miesiąca, Budżetu i tabel bez zmian.
- **C3–C4.** Wiersz tytułu to komponent `OperationsPageActions`: „Import operacji” w wariancie `default`, „Dodaj dochód”, „Dodaj wydatek”, „Dodaj transfer”, „Dodaj ruch kapitału” w `outline`, każdy z ikoną 16 px. Gdy przycisk jest wyłączony, pod wierszem tytułu stoi linia `text-xs text-muted-foreground` z powodem, wskazana przez `aria-describedby` przycisku. `title` na wyłączonym przycisku nie występuje.
- **C5.** Prop `hasAnyRecords` (dowolny dochód/wydatek, transfer własny albo ruch kapitału) zastępuje `hasFinancialOperations`. Pusty stan pierwszego uruchomienia pokazuje się tylko przy `!hasAnyRecords`, z „Import operacji” jako akcją główną i „Dodaj dochód” jako `outline`. Filtry pokazują się przy `hasAnyRecords`.
- **Stany.** Paginacja ma pierścień 3 px `ring/50`; wyłączony link to `span` z `aria-disabled="true"`, poza Tabem. Sekcje list mają `aria-busy="true"` i są wyciszone w czasie wizyty GET na `/operations` (paginacja, filtry). Przycisk potwierdzenia usunięcia pokazuje `Spinner` w czasie `processing`, z polskim `aria-label`. Dymek opisu z banku otwiera się z klawiatury.
- **Kitchen sink.** `/dev/operations` (tylko `APP_ENV=local`) pokazuje każdą komórkę macierzy 7 stanów albo „N/A” z powodem. W folderze zmiany leżą zrzuty desktop (1280) i mobile (390) po każdej fazie wizualnej.
- **Utrwalenie.** `npm run check:ui` łapie także `/[0.NN]` i obejmuje pliki Operacji; `DashboardHoldings.vue` nie ma już tego literału. `.ai/rules/js.md` i `.docs/design-system.md` wskazują jedną klasę podpisu, wzorzec powodu wyłączenia i kitchen sink Operacji.

### Key Discoveries:

- `column-caption` jest `@utility` (`app.css:195-201`), więc `@apply column-caption` działa w `@layer components`; commit `e4b3733` już tak robił, merge `e2d6f05` to cofnął.
- `.budget-category-header-row` (`app.css:375-383`) pokazuje, jak pogodzić `hidden` z `md:grid`: klasa po `.*-grid` w tej samej warstwie, a `display: grid` w `@variant md`.
- `.segmented-control-item` (`app.css:244`) to precedens pierścienia `focus-visible:ring-[3px]` w klasie `app.css`, a nie w pliku widoku — tak samo `.pagination-link`.
- `OperationsFilters.vue:231-255` śledzi własną wizytę przez `onStart`/`onFinish`; `lib/budget-year-selection.ts:23-29` to precedens `router.on(...)` z funkcją zwracającą „off” i testem z zamockowanym `@inertiajs/vue3` (`budget-year-selection.test.ts:1-40`).
- `MonthPlanEditBar.vue:47-74` to precedens dla dwóch rzeczy naraz: widoczny tekst obok wyłączonej akcji i `<Spinner v-if="processing" />` w przycisku submit.
- Teksty powodów istnieją: `lang/pl/ui.php:490` (`capital_movement_requires_account_and_term_deposit`), `:501` (`transfer_requires_two_accounts`). Brakuje tylko klucza ładowania (`common.loading`); typ `common: Record<string, string>` (`resources/js/types/shell.ts:45`) nie wymaga zmiany.
- `User` ma relacje `financialOperations()`, `ownTransfers()`, `capitalMovements()` (`app/Models/User.php:73,81,97`).
- `hasFinancialOperations` czytają tylko `Operations.vue` i `OperationsFilterTest.php:45,500,511`.
- Suchy przebieg rozszerzonego regexu `/\[0?\.[0-9]+\]` na liście FILES i nowych plikach: trafienia tylko `DashboardHoldings.vue:142,203`.
- `PaginationLinks` jest współdzielony (przez `ListSurface`) z ok. 9 widokami — zmiana fokusu jest tam zamierzona.
- Przeglądarka Playwright jest w cache poza repo (`~/.cache/ms-playwright/chromium-1248`).
- Worktree nie ma `vendor/`, `node_modules/`, `.env` ani katalogów Wayfindera — potrzebne kroki „Fresh checkout” z AGENTS.md.

## What We're NOT Doing

- Spłaszczenie trzech list w jedną chronologiczną, tytuł karty operacji, wspólny paginator (D2; issue #19, #35, #80).
- Zmiana reguły „przelewy własne ukryte przy aktywnym filtrze” (D3, `OperationsListQuery.php:45-54`) — dla użytkownika z samymi przelewami aktywny filtr da pustą listę z istniejącym komunikatem „Ukryte przy tych filtrach”.
- Rozmiar `h1` na stronach domenowych (D1), wspólny `PageHeader`.
- Dropdown akcji „Dodaj” (zakaz z #35).
- Kolor paska NProgress (D4), role tabeli na listach siatkowych (D5), `ConfirmActionDialog` (D6, powstaje w accounts-ui-contract), `InputError` w dialogach (D7), `Card`/`Badge` w filtrach (D8), wspólna klasa wiersza dla całej aplikacji i literały w `Accounts.vue`, `TermDeposits.vue`, `ImportPreviewRow.vue`, `OperationsImportPreview.vue` (D9), linki kont do `operations?account_id=` (D10).
- Obsługa błędu usunięcia w dialogu (403/404/500). Usunięcie nie ma walidacji; błąd serwera pokazuje domyślny modal błędu Inertia. Komórka `error` w macierzy dostaje „N/A” z tym powodem; nowy wpis Deferred **D11** w research.
- Tłumaczenie `aria-label="Pagination"` w `PaginationLinks` i globalna zmiana `ui/spinner` — `aria-label` spinnera przekazujemy atrybutem w miejscu użycia.
- Zmiany dialogów formularzy (`*FormDialog.vue`).
- Nowe zależności (Playwright w repo, `@vue/test-utils`, shadcn CLI) i bramka zrzutów w CI.

## Implementation Approach

Kolejność `/10x-ui`: środowisko → kontrakt (klasy i funkcje) → widok → stany → utrwalenie, jak w `context/archive/2026-11-03-month-plan-ui-contract/plan.md`.

Kitchen sink powstaje w fazie 1, żeby każda faza wizualna kończyła się zrzutem tego samego miejsca. Nowe klasy trafiają najpierw do kitchen sinka (faza 2), potem do widoku (faza 3). Logika z szablonów, której nie da się przetestować w Vue (dostępność akcji z powodem, rozpoznanie wizyty listy), trafia do `resources/js/lib` z testem `*.test.ts`. Wiersz tytułu wydzielamy do komponentu, żeby kitchen sink renderował prawdziwe przyciski w każdym stanie.

Zmiana propsa (`hasFinancialOperations` → `hasAnyRecords`) i nowy klucz tłumaczenia zmieniają PHP, TS i asercję `assertInertia` w tym samym commicie (AGENTS.md „Inertia props contract”).

## Critical Implementation Details

**Trasa tylko dla `local` a Wayfinder.** Rejestracja przez `if (app()->environment('local'))` daje 404 w `testing` i `production`, ale Wayfinder generuje pliki tras w środowisku, w którym biegnie `npm run build`. Żaden kod nie importuje wygenerowanej funkcji dla `/dev/operations`, bo `npm run types:check` w CI się złamie. Trasa stoi w grupie `auth` + `verified` (`routes/web.php:21`), bo `AppLayout` wymaga wspólnych propsów.

**Kolejność klas w `app.css`.** `.operations-header-row` musi stać po `.operations-grid` (oba ustawiają `display`), a `display: grid` dostaje w `@variant md` — inaczej `hidden` przegra albo nagłówek pokaże się na telefonie.

**`aria-label` spinnera.** `Spinner.vue` ma w szablonie `aria-label="Loading"` na korzeniu i deklaruje tylko prop `class`, więc `aria-label` podany przy użyciu przechodzi jako fallthrough i nadpisuje wartość z szablonu (Vue scala atrybuty korzenia z `$attrs` po propsach szablonu). Sprawdzić w DOM w kitchen sinku; nie edytować `components/ui/*`.

**Zajętość list bez migotania.** Wyciszenie sekcji przy `aria-busy="true"` ma opóźnienie przejścia (ok. 200 ms), żeby szybka odpowiedź nie mrugała. Nasłuch `router.on('start' | 'finish')` rejestrujemy przy montowaniu strony i zdejmujemy przy odmontowaniu; reaguje tylko na wizyty GET na ścieżkę Operacji, więc zapisy z dialogów (POST/PUT/DELETE) nie wyciszają list.

**Zrzuty.** Jednorazowy skrypt Node przez `npx -y -p playwright@<wersja zgodna z chromium-1248>`, niecommitowany:

1. otwiera podgląd z worktree (AGENTS.md „Preview from a worktree”, http://localhost:8090);
2. loguje się jako `test@example.com` / `password`;
3. zapisuje pełne strony `/dev/operations` i `/operations` przy 1280×900 i 390×844 do `context/changes/operations-ui-contract/screenshots/phase-<N>-<strona>-<szerokość>.png`.

## Faza 1: Kitchen sink i punkt odniesienia

### Overview

Przygotować worktree, postawić `/dev/operations` z danymi testowymi na dzisiejszych komponentach i zrobić zrzuty bazowe. Brak zmian w widoku.

### Changes Required:

#### 1. Środowisko worktree

**File**: `.env` (niecommitowany), baza podglądu

**Intent**: Kroki „Fresh checkout” (`composer install`, `.env`, `key:generate`, `npm ci`, `npm run build`) i osobna baza podglądu, żeby testy, typy i zrzuty biegły na kodzie tej gałęzi.

**Contract**: `DB_DATABASE=operations_ui_contract` (nigdy `household_treasurer`); `migrate --seed --force` obrazem Sail w sieci `household-treasurer_sail`.

#### 2. Dane testowe

**File**: `resources/js/lib/operations-fixtures.ts` (nowy)

**Intent**: Stałe dane dla kitchen sinka: wiersze każdego rodzaju i paginator z kilkoma stronami, żeby pokazać linki aktywne i wyłączone.

**Contract**: eksporty typowane istniejącymi typami (`FinancialOperationRowData`, `OwnTransferRowData`, `CapitalMovementRowData`, `PaginatedCollection<T>`): dochód, wydatek, zwrot, operacja z `raw_statement_description`, operacja z kontrahentem, transfer własny, ruch kapitału na lokatę i z lokaty; paginator `current_page: 1`, `last_page: 3` z linkiem „poprzednia” bez `url`.

#### 3. Trasa i strona kitchen sink

**File**: `routes/web.php`, `resources/js/pages/dev/OperationsSink.vue` (nowy)

**Intent**: Strona renderuje dzisiejsze nagłówki i wiersze trzech list w `ListSurface` z danymi z fixture, bez `setLayoutProps` i dialogów `Operations.vue`. Sekcje stanów dochodzą w kolejnych fazach.

**Contract**: `Route::inertia('dev/operations', 'dev/OperationsSink')` w `if (app()->environment('local'))` w grupie `auth` + `verified`, wzorzec `dev/month-plan` (`routes/web.php:37-39`); bez nazwy trasy.

#### 4. Test trasy

**File**: `tests/Feature/DevOperationsSinkTest.php` (nowy)

**Intent**: Kitchen sink nie jest serwowany poza `local`.

**Contract**: zalogowany użytkownik, `get('/dev/operations')` → 404; wzorzec `tests/Feature/DevMonthPlanSinkTest.php`.

#### 5. Zrzuty odniesienia

**File**: `context/changes/operations-ui-contract/screenshots/phase-1-*.png`

**Intent**: Punkt odniesienia przed zmianą: `/dev/operations` i `/operations` przy 1280 i 390 px.

**Contract**: skrypt z „Critical Implementation Details”, niecommitowany.

### Success Criteria:

#### Automated Verification:

- Test trasy przechodzi na PostgreSQL: `tests/Feature/DevOperationsSinkTest.php` obrazem Sail (AGENTS.md „Test database”)
- Typy przechodzą: `npm run types:check`
- Statyczne kontrole przechodzą: `npm run check`

#### Manual Verification:

- `/dev/operations` w podglądzie z worktree renderuje trzy listy z danymi z fixture przy 1280 i 390 px
- Zrzuty `phase-1-*` (4 pliki) leżą w `context/changes/operations-ui-contract/screenshots/`

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Elementy kontraktu

### Overview

Dodać nazwane klasy do `app.css`, sprowadzić przepis 11 px do jednego źródła i wyciągnąć logikę szablonów do `lib/` z testami. Widok Operacji jeszcze ich nie używa; pokazuje je kitchen sink.

### Changes Required:

#### 1. Klasy listy Operacji

**File**: `resources/css/app.css`

**Intent**: Nagłówek kolumn i wiersz listy Operacji jako nazwane klasy obok `.operations-grid`, z wartościami bez zmian (C1, C2).

**Contract**: `.operations-header-row` = `@apply column-caption border-border/60 hidden border-b pb-2.5`, `display: grid` od `md`; `.operations-row` = `@apply border-border/60 hover:bg-foreground/[0.03] gap-y-3 border-b py-3 text-sm transition-colors last:border-b-0`, `gap-y-0` od `md`. Wzorzec `.budget-category-row` / `.budget-category-header-row` (`app.css:371-383`).

#### 2. Jeden przepis podpisu kolumny

**File**: `resources/css/app.css`

**Intent**: Trzy literalne kopie przepisu 11 px przechodzą na `@apply column-caption`, żeby zmiana skali podpisów docierała wszędzie (C1). `.column-header` zostaje jako alias, bo używa go `MonthPlan.vue`.

**Contract**: `.column-header` → `@apply column-caption`; `.list-surface-table thead th` → `@apply column-caption pb-2.5 text-left`; `.budget-category-header-row` → `@apply column-caption border-border/60 hidden border-b pb-2.5`. Oczekiwana delta wizualna: zero.

#### 3. Klasy stanów

**File**: `resources/css/app.css`

**Intent**: Wartości stanów z fazy 4 żyją w źródle tokenów, nie w plikach widoku.

**Contract**:

- `.pagination-link` — bazowy wygląd linku z `PaginationLinks.vue:37` plus `outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px]`;
- `.operations-raw-trigger` — `rounded-lg outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px]` dla wyzwalacza dymka;
- `.operations-list` — `transition-opacity` z opóźnieniem ok. 200 ms; przy `[aria-busy='true']` `opacity-60`.

#### 4. Dostępność akcji tworzenia

**File**: `resources/js/lib/operations-create-availability.ts` (nowy) + `operations-create-availability.test.ts`

**Intent**: Jedna funkcja mówi, czy transfer i ruch kapitału są dostępne i jaki klucz tłumaczenia tłumaczy blokadę; szablon tylko ją czyta (C3).

**Contract**: `operationsCreateAvailability(accountsCount: number, termDepositsCount: number): { transfer: { enabled: boolean; reasonKey: 'transfer_requires_two_accounts' | null }; capitalMovement: { enabled: boolean; reasonKey: 'capital_movement_requires_account_and_term_deposit' | null } }`. Test: 0/0, 1/0, 1/1, 2/0, 2/1 — transfer włączony od 2 kont, ruch kapitału od 1 konta i 1 lokaty.

#### 5. Rozpoznanie wizyty listy

**File**: `resources/js/lib/operations-list-visit.ts` (nowy) + `operations-list-visit.test.ts`

**Intent**: Nasłuch, który zgłasza „lista zajęta / wolna” tylko dla wizyt GET na ścieżkę Operacji (paginacja i filtry), wzorzec `budget-year-selection.ts`.

**Contract**: `isOperationsListVisit(visit: { method: string; url: URL }, operationsPath: string): boolean` oraz `listenForOperationsListVisits(operationsPath: string, onBusyChange: (busy: boolean) => void): () => void` (rejestruje `router.on('start')` i `router.on('finish')`, zwraca funkcję zdejmującą oba). Test z zamockowanym `@inertiajs/vue3`: GET na `/operations?page=2` → `true`; DELETE na `/incomes/1` → `false`; GET na `/dashboard` → `false`; „off” zdejmuje oba nasłuchy.

#### 6. Kitchen sink: sekcja kontraktu

**File**: `resources/js/pages/dev/OperationsSink.vue`

**Intent**: Sekcja „Kontrakt” pokazuje nagłówek i wiersze na nowych klasach obok dzisiejszych, żeby porównać je na zrzucie.

**Contract**: osobna sekcja z nagłówkiem; dane z `operations-fixtures.ts`.

### Success Criteria:

#### Automated Verification:

- Testy TS przechodzą: `npm test`
- Statyczne kontrole przechodzą: `npm run check`
- Typy przechodzą: `npm run types:check`
- Build przechodzi: `npm run build`

#### Manual Verification:

- W `/dev/operations` wiersze na nowych klasach wyglądają identycznie jak dzisiejsze (1280 i 390 px)
- `/dev/month-plan` i `/dev/kitchen-sink/budget` bez widocznej zmiany nagłówków kolumn i tabel względem stanu przed fazą
- Zrzuty `phase-2-*` zapisane

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Widok (C1–C5)

### Overview

Przenieść widok na klasy z fazy 2, wydzielić wiersz tytułu z jedną akcją główną i widocznym powodem blokady, zmienić prop pustego stanu na `hasAnyRecords`.

### Changes Required:

#### 1. Nagłówki i wiersze (C1, C2)

**File**: `resources/js/pages/Operations.vue`, `resources/js/components/FinancialOperationRow.vue`, `OwnTransferRow.vue`, `CapitalMovementRow.vue`

**Intent**: Trzy wiersze nagłówka i trzy wiersze listy używają nazwanych klas zamiast ciągów z literałami.

**Contract**: nagłówek `operations-grid [operations-grid-transfer] operations-header-row`; wiersz `operations-grid [operations-grid-transfer] operations-row`. Brak `text-[11px]` i `/[0.03]` w tych plikach.

#### 2. Wiersz tytułu (C3, C4)

**File**: `resources/js/components/OperationsPageActions.vue` (nowy), `resources/js/pages/Operations.vue`

**Intent**: Pięć osobnych kontrolek (#35) z jedną akcją główną: „Import operacji” `default`, pozostałe `outline`, wszystkie z ikoną 16 px z `@lucide/vue` (transfer i ruch kapitału dostają ikony spójne z `OperationTypeTile`). Pod przyciskami, tylko gdy coś jest wyłączone, linia powodu `text-xs text-muted-foreground` (jedna na każdy wyłączony przycisk), wskazana przez `aria-describedby`. Bez `title`.

**Contract**: props `accountsCount: number`, `termDepositsCount: number`; emits `add-income`, `add-expense`, `add-transfer`, `add-capital-movement`; link importu przez `CsvImportController.create.url()` jak dziś. Dostępność z `operationsCreateAvailability`. `id` linii powodu unikalne w obrębie strony. `Operations.vue` renderuje `h1` + `OperationsPageActions` i otwiera dialogi na zdarzenia.

#### 3. Prop `hasAnyRecords` (C5)

**File**: `app/Http/Controllers/OperationsController.php`, `resources/js/pages/Operations.vue`, `tests/Feature/OperationsFilterTest.php`, `tests/Feature/OperationsEmptyStateTest.php` (nowy)

**Intent**: „Pierwsze uruchomienie” to brak jakiegokolwiek zapisu, nie tylko dochodów i wydatków. Ten sam prop steruje pustym stanem i filtrami.

**Contract**: `'hasAnyRecords' => $user->financialOperations()->exists() || $user->ownTransfers()->exists() || $user->capitalMovements()->exists()` zamiast `hasFinancialOperations`; TS `hasAnyRecords: boolean`. `OperationsFilters v-if="hasAnyRecords"`, `IndexEmptyState v-if="!hasAnyRecords"`. `OperationsFilterTest.php:45,500,511` przechodzą na `hasAnyRecords`. Nowy test: brak zapisów → `false`; tylko transfer własny → `true`; tylko ruch kapitału → `true`; tylko wydatek → `true`; asercje `assertInertia` na `hasAnyRecords` i brak klucza `hasFinancialOperations`.

#### 4. Pusty stan pierwszego uruchomienia

**File**: `resources/js/pages/Operations.vue`

**Intent**: Akcja główna pustego stanu zgadza się z wierszem tytułu.

**Contract**: w slocie `actions` najpierw „Import operacji” (`default`, ikona `Upload`), potem „Dodaj dochód” (`outline`, ikona `Plus`).

#### 5. Kitchen sink

**File**: `resources/js/pages/dev/OperationsSink.vue`

**Intent**: Sekcje „Wiersz tytułu” i „Puste stany” pokazują prawdziwy `OperationsPageActions` i oba `IndexEmptyState`.

**Contract**: `OperationsPageActions` dla (accounts, termDeposits) = (2, 1), (1, 1), (2, 0), (0, 0); pusty stan pierwszego uruchomienia i po filtrach; sekcja „Kontrakt” z fazy 2 zostaje tylko z nowymi klasami.

### Success Criteria:

#### Automated Verification:

- Testy Operacji przechodzą na PostgreSQL: `tests/Feature/OperationsFilterTest.php tests/Feature/OperationsEmptyStateTest.php tests/Feature/CapitalMovementTest.php tests/Feature/OwnTransferTest.php` obrazem Sail
- Testy TS, kontrole i typy przechodzą: `npm test`, `npm run check`, `npm run types:check`
- Brak literałów w plikach widoku: `node scripts/check-ui-literals.mjs resources/js/pages/Operations.vue resources/js/components/FinancialOperationRow.vue resources/js/components/OwnTransferRow.vue resources/js/components/CapitalMovementRow.vue resources/js/components/OperationsPageActions.vue`
- Brak `title=` na przyciskach w `Operations.vue` i `OperationsPageActions.vue`: `grep -n 'title=' …` zwraca tylko `Head` / tytuły kart
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- `/operations` dla `test@example.com`: jeden przycisk turkusowy (Import), cztery `outline`, każdy z ikoną; przy 390 px przyciski nie tworzą ściany turkusu
- Użytkownik z jednym kontem: pod przyciskami widać „Potrzebujesz co najmniej dwóch kont…”, czytnik ekranu (lub inspektor dostępności) podaje go jako opis przycisku
- Użytkownik z samymi transferami własnymi: brak „Brak operacji finansowych”, filtry widoczne, lista transferów pod nimi
- Zrzuty `phase-3-*` zapisane

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Stany z macierzy

### Overview

Domknąć focus-visible, disabled i loading na liście oraz wypełnić macierz 7 stanów w kitchen sinku.

### Changes Required:

#### 1. Paginacja

**File**: `resources/js/components/PaginationLinks.vue`

**Intent**: Link paginacji ma widoczny pierścień fokusu, a wyłączony link nie jest przystankiem Tab i ogłasza się jako wyłączony. Dotyczy wszystkich list z `ListSurface` — zamierzone.

**Contract**: linki z `url` → `Link` z klasą `.pagination-link`; link bez `url` i nieaktywny → `span` z `.pagination-link`, `aria-disabled="true"`, bez `href`; `aria-current="page"` na aktywnym bez zmian; `v-html` etykiet bez zmian.

#### 2. Zajętość list w czasie wizyty

**File**: `resources/js/pages/Operations.vue`

**Intent**: Przy paginacji i zmianie filtrów sekcje list mówią, że się odświeżają.

**Contract**: `listBusy` z `listenForOperationsListVisits(operationsRoute().url, …)` rejestrowanego w `onMounted`, zdejmowanego w `onUnmounted`; trzy `ListSurface` dostają klasę `operations-list` i `:aria-busy="listBusy"` (fallthrough na `Card`).

#### 3. Spinner przy usuwaniu

**File**: `resources/js/components/FinancialOperationActions.vue`, `OwnTransferActions.vue`, `CapitalMovementActions.vue`, `lang/pl/ui.php`

**Intent**: Przycisk potwierdzenia usunięcia pokazuje `Spinner` w czasie `processing`, z polskim `aria-label` (wzorzec `MonthPlanEditBar.vue:63-74`).

**Contract**: `<Spinner v-if="processing" :aria-label="t.common.loading" />` w przycisku `variant="destructive"`; nowy klucz `common.loading` = `'Ładowanie'` w `lang/pl/ui.php`; asercja `->where('translations.common.loading', 'Ładowanie')` w `OperationsEmptyStateTest.php`.

#### 4. Dymek opisu z banku z klawiatury

**File**: `resources/js/components/OperationDescriptionCell.vue`

**Intent**: Oryginalny opis z wyciągu jest dostępny z klawiatury, nie tylko myszą.

**Contract**: wyzwalacz (`TooltipTrigger as-child`) dostaje `tabindex="0"`, `role="group"` (żeby istniejący `aria-label` był ważny) i klasę `.operations-raw-trigger`; fokus otwiera dymek (reka). Komórka bez opisu z banku bez zmian i bez przystanku Tab.

#### 5. Kitchen sink: macierz 7 stanów

**File**: `resources/js/pages/dev/OperationsSink.vue`

**Intent**: Każda komórka macierzy ma przykład albo „N/A” z powodem.

**Contract**: sekcje:

- **default** — trzy listy z fixture;
- **hover** — wiersze (rytm, nie afordancja; nota przy sekcji);
- **focus-visible** — paginacja, akcje wiersza, wyzwalacz dymka (nota „przejdź Tabem”);
- **disabled** — `OperationsPageActions` z powodami, wyłączony link paginacji;
- **error** — „N/A: usunięcie nie ma walidacji; błąd serwera pokazuje modal Inertia (D11)”;
- **empty** — oba `IndexEmptyState`;
- **loading** — `ListSurface` z `aria-busy="true"` i przycisk destrukcyjny ze `Spinner`.

### Success Criteria:

#### Automated Verification:

- Testy Operacji przechodzą na PostgreSQL: `tests/Feature/OperationsEmptyStateTest.php tests/Feature/OperationsPaginationTest.php tests/Feature/FinancialOperationEditDeleteTest.php` obrazem Sail
- Testy TS, kontrole, typy i build przechodzą: `npm test`, `npm run check`, `npm run types:check`, `npm run build`
- Brak literałów w zmienionych plikach: `node scripts/check-ui-literals.mjs resources/js/components/PaginationLinks.vue resources/js/components/OperationDescriptionCell.vue resources/js/components/FinancialOperationActions.vue resources/js/components/OwnTransferActions.vue resources/js/components/CapitalMovementActions.vue resources/js/pages/dev/OperationsSink.vue`

#### Manual Verification:

- Tab po `/operations`: linki paginacji mają pierścień 3 px, wyłączona „poprzednia” jest pomijana; wiersz z opisem z banku dostaje fokus i otwiera dymek
- Przejście na stronę 2 przy dławieniu sieci (DevTools „Slow 4G”): sekcja się wycisza; szybka odpowiedź nie mruga
- Usunięcie operacji: przycisk pokazuje spinner, w DOM `aria-label="Ładowanie"`
- Fokus paginacji w `/accounts` i `/budget` wygląda tak samo jak w Operacjach (zamierzona zmiana wspólnego komponentu)
- Zrzuty `phase-4-*` zapisane

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie przed fazą 5.

---

## Faza 5: Utrwalenie

### Overview

Rozszerzyć strażnika literałów o formę `/[0.NN]` i pliki Operacji, zostawić regułę i dokumentację dla następnego agenta, nadać zarzutom statusy, przejść pełną bramkę CI.

### Changes Required:

#### 1. Strażnik literałów

**File**: `scripts/check-ui-literals.mjs`

**Intent**: `npm run check:ui` łapie odcień w formie `/[0.03]` i pilnuje oczyszczonych plików Operacji.

**Contract**: do `PATTERN` alternatywa `\/\[0?\.[0-9]+\]`; do `FILES`: `Operations.vue`, `FinancialOperationRow.vue`, `OwnTransferRow.vue`, `CapitalMovementRow.vue`, `OperationsPageActions.vue`, `OperationsFilters.vue`, `OperationsActiveFilterChips.vue`, `OperationDescriptionCell.vue`, `FinancialOperationActions.vue`, `OwnTransferActions.vue`, `CapitalMovementActions.vue`, `PaginationLinks.vue`, `pages/dev/OperationsSink.vue`.

#### 2. Poprawka `DashboardHoldings`

**File**: `resources/css/app.css`, `resources/js/components/DashboardHoldings.vue`

**Intent**: Dwa wiersze posiadań (`:142`, `:203`) przenoszą swój ciąg klas do nazwanej klasy, żeby rozszerzony strażnik przechodził. Delta wizualna: zero.

**Contract**: `.dashboard-holdings-row` = `@apply hover:bg-foreground/[0.03] flex items-center justify-between gap-x-4 gap-y-2 py-3 transition-colors`; oba wiersze używają tej klasy.

#### 3. Reguła dla agentów

**File**: `.ai/rules/js.md` (Boost `record-rule` niedostępny — edycja pliku, AGENTS.md „When Boost tools are not available”)

**Intent**: Następny agent dostaje jedną odpowiedź na pytanie o podpis kolumny i wie, jak tłumaczyć wyłączoną akcję.

**Contract**:

- sekcja „Views take values from app.css”: `column-caption` kanoniczny, `.column-header` to alias dla Planu miesiąca; klasy `.operations-header-row` / `.operations-row`;
- nowa sekcja: powód wyłączonej akcji to widoczny tekst z `aria-describedby`, nigdy `title` na wyłączonym `Button` (`pointer-events-none`); wiersz tytułu strony domenowej ma co najwyżej jeden przycisk primary;
- kitchen sink `/dev/operations` z `resources/js/lib/operations-fixtures.ts`; strażnik łapie także `/[0.NN]`.

#### 4. Dokumentacja systemu

**File**: `.docs/design-system.md`

**Intent**: Dokument przestaje wskazywać dwie klasy podpisu i opisuje nowe wzorce.

**Contract**: „Powierzchnie i rytm” — `column-caption` jedyny przepis, `.column-header` alias; klasy listy Operacji. Wiersz tytułu — jedna akcja główna, powód wyłączenia pod przyciskami. „Kitchen sink i zrzuty” — dopisany `/dev/operations`.

#### 5. Research: statusy zarzutów

**File**: `context/changes/operations-ui-contract/research.md`

**Intent**: Każdy zarzut i deferred ma status po tej zmianie.

**Contract**: C1–C5 → fixed (z fazą); D1–D10 bez zmian (deferred); nowy **D11** (błąd usunięcia w dialogu).

### Success Criteria:

#### Automated Verification:

- Strażnik przechodzi na pełnej liście: `npm run check:ui`
- Strażnik łapie odcień: `node scripts/check-ui-literals.mjs <plik tymczasowy z hover:bg-foreground/[0.03]>` kończy się kodem 1
- Pełna bramka CI na PostgreSQL: `composer ci:check` obrazem Sail (AGENTS.md „Test database”)
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- `/dashboard` i `/dev/ui/dashboard`: wiersze kont i lokat bez widocznej zmiany, hover działa
- Zrzuty `phase-5-*` zapisane; porównanie z `phase-1-*` pokazuje tylko zamierzone różnice

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie; przy scalaniu sprawdzić `app.css` względem `accounts-ui-contract` (też celuje w hover wiersza).

---

## Testing Strategy

### Unit Tests:

- `operations-create-availability.test.ts` — granice: 0, 1, 2 konta × 0, 1 lokata.
- `operations-list-visit.test.ts` — GET na ścieżkę Operacji z query, inne metody, inna ścieżka, zdjęcie nasłuchów.

### Integration Tests:

- `OperationsEmptyStateTest.php` — `hasAnyRecords` dla braku zapisów i dla każdego rodzaju osobno; brak `hasFinancialOperations`; `translations.common.loading`.
- `OperationsFilterTest.php` — istniejące asercje przechodzą na `hasAnyRecords`.
- `DevOperationsSinkTest.php` — 404 poza `local`.

### Manual Testing Steps:

1. Podgląd z worktree, `/operations` jako `test@example.com` przy 1280 i 390 px: jeden przycisk primary, ikony, nagłówki kolumn.
2. Użytkownik z jednym kontem (podgląd: archiwizacja kont lub nowy użytkownik): powód blokady widoczny, odczytany jako opis.
3. Użytkownik z samymi transferami: filtry, brak sprzecznego pustego stanu; aktywny filtr → komunikat „Ukryte przy tych filtrach”.
4. Tab po liście, paginacja przy dławieniu sieci, usunięcie ze spinnerem.
5. `/dev/operations`, `/dev/month-plan`, `/dev/kitchen-sink/budget`, `/dev/ui/dashboard` — porównanie ze zrzutami bazowymi.

## Performance Considerations

`hasAnyRecords` to do trzech zapytań `exists()` (krótkie spięcie po pierwszym trafieniu) zamiast jednego, na indeksowanym `user_id`. Pomijalne.

## Migration Notes

Brak migracji danych. Zmiana nazwy propsa dotyczy tylko `Operations.vue`; przy scalaniu z gałęziami, które dotykają `Operations.vue` lub `app.css` (`accounts-ui-contract`), rozwiązać konflikt na rzecz `hasAnyRecords` i nazwanych klas.

## References

- Research: `context/changes/operations-ui-contract/research.md`
- Wzorzec zmiany: `context/archive/2026-11-03-month-plan-ui-contract/plan.md`
- Klasy listy: `resources/css/app.css:371-383` (`.budget-category-row`, `.budget-category-header-row`)
- Tekst przy wyłączonej akcji i spinner: `resources/js/components/MonthPlanEditBar.vue:47-74`
- Nasłuch routera z testem: `resources/js/lib/budget-year-selection.ts:23-29`, `budget-year-selection.test.ts`
- Kitchen sink i test 404: `routes/web.php:37-39`, `tests/Feature/DevMonthPlanSinkTest.php`
- Ograniczenie IA: issue #35 kryt. 21, #19, #80

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kitchen sink i punkt odniesienia

#### Automated

- [x] 1.1 Test trasy przechodzi na PostgreSQL: `tests/Feature/DevOperationsSinkTest.php` obrazem Sail (AGENTS.md „Test database”) — 3c1cb0c
- [x] 1.2 Typy przechodzą: `npm run types:check` — 3c1cb0c
- [x] 1.3 Statyczne kontrole przechodzą: `npm run check` — 3c1cb0c

#### Manual

- [x] 1.4 `/dev/operations` w podglądzie z worktree renderuje trzy listy z danymi z fixture przy 1280 i 390 px — 3c1cb0c
- [x] 1.5 Zrzuty `phase-1-*` (4 pliki) leżą w `context/changes/operations-ui-contract/screenshots/` — 3c1cb0c

### Phase 2: Elementy kontraktu

#### Automated

- [x] 2.1 Testy TS przechodzą: `npm test` — b6d926a
- [x] 2.2 Statyczne kontrole przechodzą: `npm run check` — b6d926a
- [x] 2.3 Typy przechodzą: `npm run types:check` — b6d926a
- [x] 2.4 Build przechodzi: `npm run build` — b6d926a

#### Manual

- [x] 2.5 W `/dev/operations` wiersze na nowych klasach wyglądają identycznie jak dzisiejsze (1280 i 390 px) — b6d926a
- [x] 2.6 `/dev/month-plan` i `/dev/kitchen-sink/budget` bez widocznej zmiany nagłówków kolumn i tabel względem stanu przed fazą — b6d926a
- [x] 2.7 Zrzuty `phase-2-*` zapisane — b6d926a

### Phase 3: Widok (C1–C5)

#### Automated

- [x] 3.1 Testy Operacji przechodzą na PostgreSQL: `tests/Feature/OperationsFilterTest.php tests/Feature/OperationsEmptyStateTest.php tests/Feature/CapitalMovementTest.php tests/Feature/OwnTransferTest.php` obrazem Sail
- [x] 3.2 Testy TS, kontrole i typy przechodzą: `npm test`, `npm run check`, `npm run types:check`
- [x] 3.3 Brak literałów w plikach widoku: `node scripts/check-ui-literals.mjs resources/js/pages/Operations.vue resources/js/components/FinancialOperationRow.vue resources/js/components/OwnTransferRow.vue resources/js/components/CapitalMovementRow.vue resources/js/components/OperationsPageActions.vue`
- [x] 3.4 Brak `title=` na przyciskach w `Operations.vue` i `OperationsPageActions.vue`: `grep -n 'title=' …` zwraca tylko `Head` / tytuły kart
- [x] 3.5 Formatowanie PHP: `vendor/bin/pint --dirty --format agent`

#### Manual

- [x] 3.6 `/operations` dla `test@example.com`: jeden przycisk turkusowy (Import), cztery `outline`, każdy z ikoną; przy 390 px przyciski nie tworzą ściany turkusu
- [x] 3.7 Użytkownik z jednym kontem: pod przyciskami widać „Potrzebujesz co najmniej dwóch kont…”, czytnik ekranu (lub inspektor dostępności) podaje go jako opis przycisku
- [x] 3.8 Użytkownik z samymi transferami własnymi: brak „Brak operacji finansowych”, filtry widoczne, lista transferów pod nimi
- [x] 3.9 Zrzuty `phase-3-*` zapisane

### Phase 4: Stany z macierzy

#### Automated

- [ ] 4.1 Testy Operacji przechodzą na PostgreSQL: `tests/Feature/OperationsEmptyStateTest.php tests/Feature/OperationsPaginationTest.php tests/Feature/FinancialOperationEditDeleteTest.php` obrazem Sail
- [ ] 4.2 Testy TS, kontrole, typy i build przechodzą: `npm test`, `npm run check`, `npm run types:check`, `npm run build`
- [ ] 4.3 Brak literałów w zmienionych plikach: `node scripts/check-ui-literals.mjs resources/js/components/PaginationLinks.vue resources/js/components/OperationDescriptionCell.vue resources/js/components/FinancialOperationActions.vue resources/js/components/OwnTransferActions.vue resources/js/components/CapitalMovementActions.vue resources/js/pages/dev/OperationsSink.vue`

#### Manual

- [ ] 4.4 Tab po `/operations`: linki paginacji mają pierścień 3 px, wyłączona „poprzednia” jest pomijana; wiersz z opisem z banku dostaje fokus i otwiera dymek
- [ ] 4.5 Przejście na stronę 2 przy dławieniu sieci (DevTools „Slow 4G”): sekcja się wycisza; szybka odpowiedź nie mruga
- [ ] 4.6 Usunięcie operacji: przycisk pokazuje spinner, w DOM `aria-label="Ładowanie"`
- [ ] 4.7 Fokus paginacji w `/accounts` i `/budget` wygląda tak samo jak w Operacjach (zamierzona zmiana wspólnego komponentu)
- [ ] 4.8 Zrzuty `phase-4-*` zapisane

### Phase 5: Utrwalenie

#### Automated

- [ ] 5.1 Strażnik przechodzi na pełnej liście: `npm run check:ui`
- [ ] 5.2 Strażnik łapie odcień: `node scripts/check-ui-literals.mjs <plik tymczasowy z hover:bg-foreground/[0.03]>` kończy się kodem 1
- [ ] 5.3 Pełna bramka CI na PostgreSQL: `composer ci:check` obrazem Sail (AGENTS.md „Test database”)
- [ ] 5.4 Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual

- [ ] 5.5 `/dashboard` i `/dev/ui/dashboard`: wiersze kont i lokat bez widocznej zmiany, hover działa
- [ ] 5.6 Zrzuty `phase-5-*` zapisane; porównanie z `phase-1-*` pokazuje tylko zamierzone różnice
