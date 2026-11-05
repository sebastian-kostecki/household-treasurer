# Widok Operacji w systemie „C · Atrament i morski” — Implementation Plan

## Overview

`/operations` przechodzi na wybrany design system: zakładki sekcji z licznikami (wybór w `?section=`), pasek filtrów w panelu z licznikiem „X z Y”, lista operacji finansowych pogrupowana po dniach z sumą dnia liczoną na serwerze, wydatek bez czerwieni, jedna akcja główna „Dodaj operację” z menu i kolumny jak w mockupie (MockupCAtrament → Operacje). Punkty 7 i 8 README systemu zapisuje najpierw ADR-0038.

## Current State Analysis

Audyt (`research.md`, zarzuty C1–C5):

- **C1** Typografia i przepis panelu: tytuł `text-lg` (`pages/Operations.vue:151`), kwoty bez kroju mono i z „zł” w każdej komórce (`app.css:656-659`, `lib/format.ts:131-142`), kafelek typu 40 px (`OperationTypeTile.vue:52,59`), ręczne `rounded-2xl`/`border-border/60`/`rounded-xl` w `OperationsFilters.vue:389,446,461,629`, `OperationsActiveFilterChips.vue:256`, `PaginationLinks.vue:29`.
- **C2** Wydatek jako status: `financialOperationAmountClass` → `text-destructive` (`lib/format.ts:147-156`), kafelek wydatku `destructive` (`OperationTypeTile.vue:28-31`).
- **C3** Brak wiersza dnia, formatera „data słowami”, licznika w `SegmentedControl` (`SegmentedControl.vue:42`) i licznika „X z Y”.
- **C4** Trzy panele jeden pod drugim (`Operations.vue:210-320`) i pięć przycisków z „Import operacji” jako primary (`OperationsPageActions.vue:46-87`) — ślad kryteriów issue #19/#35/#80, nie ADR.
- **C5** `check-ui-literals.mjs:39-53` nie obejmuje `OperationTypeTile`, `OperationsDateCell`, `CompactAccountIdentity`, `SegmentedControl`; sink kopiuje wiersze ręcznie (`dev/OperationsSink.vue:176-345`).

Tokeny (ADR-0035) i style/kontrolki (ADR-0037: `page-title`, `table-header`, `meta`, `amount`, `amount-sum`, `size-control-sm`, `min-h-row`, `app.css:234-291`) już są. `OperationsListQuery::apply()` dodaje tylko WHERE (`app/Support/OperationsListQuery.php:122-162`), więc sumę dnia da się policzyć tym samym filtrem.

## Desired End State

`/operations` wygląda jak mockup w obu motywach przy 1280 i 390 px:

- wiersz tytułu: `page-title` + opis `meta`, zarysowany „Importuj” i primary „Dodaj operację” otwierający menu (Dochód, Wydatek, Transfer własny, Ruch kapitału; wyłączone pozycje z widocznym powodem);
- pod tytułem zakładki Operacje finansowe / Transfery własne / Ruchy kapitału z licznikami (mono); wybór w `?section=`, który przetrwa paginację, zmianę filtrów i odświeżenie;
- jeden panel aktywnej sekcji: na górze pasek filtrów (szukaj, Ten miesiąc / Poprzedni miesiąc, Filtry z licznikiem, pigułki aktywnych filtrów, „Wyczyść filtry”, po prawej „X z Y”), pod nim tabela, stopka „Kwoty w zł”;
- operacje finansowe: wiersz dnia („środa, 4 listopada”, suma dnia w kolumnie kwot: `+` `success` lub `−` `foreground`), wiersze Opis (kafelek 26 px, kontrahent/opis, w drugiej linii opis i konto) | Kategoria | Data | Kwota | akcje; wydatek `−` w `foreground`, dochód i zwrot `+` w `success`;
- transfery i ruchy kapitału: dotychczasowe kolumny w nowym stylu, stopka z objaśnieniem;
- stany: brak rekordów, pusty wynik filtrów, sekcja wyciszona filtrem (licznik 0, notka i „Wyczyść filtry”), sekcja bez rekordów, ładowanie (`aria-busy`).

Weryfikacja: testy Feature propsów, `npm test` formaterów i grupowania, `check:ui` na wszystkich plikach widoku, kitchen sink `/dev/operations` z 7 stanami i zrzuty w `context/changes/ui-operations-atrament/screenshots/`.

### Key Discoveries:

- Suma ze znakiem jak w `BudgetMonthResult::forViewedMonth` (`app/Support/BudgetMonthResult.php:20-34`): zwrot to wydatek z ujemną kwotą (`CONTEXT.md:131-133`), więc `SUM(CASE WHEN type='income' THEN amount ELSE -amount END)` liczy zwrot jako `+`.
- SQLite zapisuje daty z modelu jako `Y-m-d H:i:s`; grupowanie i klucz dnia jak w `app/Support/MonthCategoryActuals.php:40-57` (`whereDate`, `toDateString()` w PHP, `+=`).
- `apply()` mutuje builder, a `orderByDesc('id')` z listy psuje `GROUP BY` na PostgreSQL — suma dnia potrzebuje świeżego zapytania.
- Wyciszenie sekcji jest w kontrolerze (`OperationsController.php:37-39`), nie w `apply()`.
- `OperationsFilters.vue:231-244` buduje query z 8 kluczy filtrów i gubi `section`; „Wyczyść filtry” w pustym stanie linkuje bez query (`Operations.vue:203`).
- `OperationDescriptionCell` renderuje też podgląd importu (`ImportPreviewRow.vue:6,353`), więc zmiana kafelka i opisu musi zostać zgodna wstecz dla importu.
- `OperationsController::index` jest w baseline Sloppy (SL101), a nowa logika w kontrolerze grozi SL102 — liczenie idzie do `app/Support`.
- `Intl.DateTimeFormat('pl-PL', {weekday:'long', day:'numeric', month:'long'})` daje „środa, 4 listopada” w Node tego repo; `.ai/rules/js.md:16` zabrania `Intl` tylko dla kwot.

## What We're NOT Doing

- Transfery własne nadal znikają przy każdym aktywnym filtrze (`OperationsListQuery.php:90-92`, D3 z research) — to zmiana domeny.
- Paleta ⌘K i skróty `N`, `/`, `1`–`6` (README MockupCAtrament: osobna zmiana).
- Jeden dialog dochód/wydatek z wyborem typu; dialogi dodawania i edycji bez zmian.
- Kolumny transferów i ruchów kapitału z mockupu (`Z konta | Na konto`) — zostają dotychczasowe w nowym stylu.
- Wiersze dnia w zakładkach transferów i ruchów kapitału.
- Klikalna pigułka kategorii filtrująca listę (mockup `data-cat-go`) — filtr kategorii zostaje w panelu filtrów.
- Przepis wiersza `text-sm` / `border-border/60` wspólny z Budżetem (`app.css:481`) — zostaje jak w Budżecie, bez rozjazdu między widokami.
- Wpis w `CONTEXT.md` dla „sumy dnia” (nie zmienia reguły ani terminu _Avoid_; definiuje ją ADR-0038).
- Nieaktualny skill `.ai/skills/openship-ui-development/SKILL.md`.

## Implementation Approach

Kolejność z `/10x-ui`: dokumenty → warstwa współdzielona → kontrakt → jeden widok i stany. Faza 1 zapisuje decyzje (README wymaga ADR przed kodem). Faza 2 zmienia elementy współdzielone (formatery, kafelek, `SegmentedControl`, siatka) i sprawdza zrzutami import i Plan miesiąca, które z nich korzystają. Faza 3 dodaje propsy po obu stronach w jednym commicie z `assertInertia` (AGENTS.md). Faza 4 przebudowuje widok, sink i straż. Między fazami 2 a 4 Operacje działają, ale wyglądają pośrednio; nie scalamy do `main` przed Fazą 4.

## Critical Implementation Details

- **Zakres sumy dnia**: zapytanie bierze dni z bieżącej strony i filtruje `whereDate` od najmniejszego do największego; dni wewnątrz zakresu są na stronie w całości, a dwa skrajne mogą sięgać sąsiednich stron — suma obejmuje cały dzień (decyzja 4). Nie używać `whereIn('operation_date', …)` (SQLite).
- **Zmiana zakładki** to `router.get` z `preserveState`, `preserveScroll`, `replace` na URL z bieżącymi filtrami i nową sekcją, bez numerów stron sekcji. `listenForOperationsListVisits` ustawi `aria-busy` jak przy filtrach.
- **Wyłączona pozycja menu**: reka pomija wyłączone pozycje przy nawigacji klawiaturą, więc powód musi być widoczny w samej pozycji (druga linia `meta`), a `aria-describedby` wskazuje ten tekst (`.ai/rules/js.md:63`).

## Phase 1: Decyzje i dokumenty

### Overview

ADR-0038 i zgodne dokumenty przed kodem.

### Changes Required:

#### 1. ADR-0038

**File**: `.docs/adr/0038-operations-tabs-day-rows-and-expense-colour.md`

**Intent**: Zapisać pkt 7 i 8 README oraz układ widoku, bo odwracają kryteria zamkniętych issue #19, #35, #80, których nie trzyma żaden ADR.

**Contract**: Format jak `0037-…md` (angielski, `# Title`, 1–3 akapity). Treść: (1) kwota operacji: dochód i zwrot `+` w `success`, wydatek `−` w `foreground`; `destructive` tylko dla przekroczeń, ujemnych odczytów i usuwania; kafelek typu: wydatek `muted`, dochód `accent`+`success`, zwrot `warning-soft`+`refund`, przelew `transfer`; (2) lista operacji finansowych grupowana po dniach; „suma dnia” = dochód − wydatek, zwroty netto, bez transferów i ruchów kapitału, przy aktywnych filtrach, liczona na serwerze dla całego dnia (także gdy dzień przekracza stronę); znak i kolor jak kwota operacji, nie `amountToneClass`; (3) sekcje jako zakładki z `?section=` (nieznana wartość = błąd walidacji), wyciszona sekcja zostaje wybrana z notką; (4) jedna akcja główna „Dodaj operację” z menu, „Importuj” zarysowany; (5) kolumny operacji finansowych Opis | Kategoria | Data | Kwota. Zdanie: „This reverses the acceptance criteria of issues #19, #35 and #80 (three stacked lists, four separate add controls, red expenses); no earlier ADR held them.” Sekcja „We rejected”: jeden dialog z wyborem typu, suma ze strony po stronie klienta, kolor `amountToneClass` dla sumy dnia.

#### 2. Dopisek w ADR-0035

**File**: `.docs/adr/0035-atrament-tokens-inter-jetbrains-mono.md`

**Intent**: Zamknąć odroczenie pkt 7 i 8.

**Contract**: Nowy akapit na końcu, wzorem linii o ADR-0037: „Points 7 and 8 (expense colour in Operations, day rows) are settled by ADR-0038.”

#### 3. Design system

**File**: `.docs/design-system.md`

**Intent**: Dokument opisuje stan po zmianie.

**Contract**: :3 lista ADR + 0038; :17-18 siatka Operacji (kolumny, `.operations-day-row`, jeden panel z zakładkami); :24 mono: Budżet i Operacje; :44-47 role kafelka typu i brak `destructive` dla wydatku; :59-63 „Kwoty”: kwota operacji i suma dnia (znak z typu, `success`/`foreground`), `formatFinancialOperationAmount` bez „zł”; :78-85 `SegmentedControl` z licznikiem i nowe użycie neutral jako zakładki sekcji w URL; menu „Dodaj operację”; :91 sink z nowymi stanami; :119 lista plików `check:ui`.

#### 4. Reguły agenta

**File**: `.ai/rules/js.md`

**Intent**: Następny agent buduje Operacje na tych samych elementach.

**Contract**: :11 dopisać, że wydatek w Operacjach jest `foreground`; :24 kafelek typu w kolorach z ADR-0038; sekcja „Operations list” (:60-63): jedna siatka `.operations-grid` dla wierszy i `.operations-day-row`, suma dnia tylko z propa `operationDaySums`, zakładki przez `SegmentedControl` neutral z `count`, query listy tylko przez helper z `lib/operations-list-query.ts` (zachowuje `section`), menu „Dodaj operację” z powodem wyłączenia jako tekst w pozycji, nowe stany do `/dev/operations`. Zapis przez `record-rule` z Boost, a gdy narzędzia nie ma, edycja wprost; `.ai/rules/index.md` bez zmian.

### Success Criteria:

#### Automated Verification:

- ADR-0038 istnieje, a ADR-0035 odsyła do niego: `grep -l "ADR-0038" .docs/adr/0035-*.md .docs/design-system.md .ai/rules/js.md`
- `npm run check` zielone (formatowanie Markdown)

#### Manual Verification:

- ADR-0038, `.docs/design-system.md` i `.ai/rules/js.md` mówią to samo o kolorze kwoty, sumie dnia i zakładkach

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie ręcznych kroków.

---

## Phase 2: Warstwa współdzielona

### Overview

Formatery, kafelek, licznik w `SegmentedControl`, siatka i wiersz dnia w `app.css`, straż literałów — zanim widok z nich skorzysta.

### Changes Required:

#### 1. Formatery kwot i dnia

**File**: `resources/js/lib/format.ts`, `resources/js/lib/format.test.ts`

**Intent**: Kwota operacji bez „zł” i bez czerwieni dla wydatku (pkt 7), suma dnia w tej samej konwencji, data słowami do wiersza dnia (pkt 8).

**Contract**:
- `formatFinancialOperationAmount(type, amount)`: `+`/`−` jak dziś, liczba z `formatMoneyAmount(Math.abs(amount))` (bez „zł”).
- `financialOperationAmountClass(type, amount)`: dochód i zwrot `text-success`, wydatek `text-foreground`.
- nowe `formatOperationDaySum(minorUnits)`: `> 0` → `+1 234,56`, `< 0` → `−1 234,56` (U+2212), `0` → `0,00`; nowe `operationDaySumClass(minorUnits)`: `> 0` → `text-success`, inaczej `text-foreground`.
- nowe `formatDayLabel(isoDate, today = new Date())`: `Intl.DateTimeFormat('pl-PL', {weekday:'long', day:'numeric', month:'long'})`, z rokiem (`year:'numeric'`) gdy rok daty ≠ rok `today`; parsowanie `${date}T12:00:00` jak `formatDate`.
- Testy: wydatek / dochód / zwrot (znak, klasa), suma dodatnia / ujemna / zero, etykieta dnia w roku bieżącym i poprzednim.

#### 2. Kafelek typu i komórka opisu

**File**: `resources/js/components/OperationTypeTile.vue`, `resources/js/components/OperationDescriptionCell.vue`

**Intent**: Kafelek 26 px w kolorach systemu (C1, C2); druga linia opisu niesie konto (decyzja 3) bez zmiany zachowania importu.

**Contract**:
- kafelek: `size-control-sm rounded-md`, ikona `size-3.5`, bez `self-stretch`; tła: dochód `bg-accent text-success`, wydatek `bg-muted text-muted-foreground`, zwrot `bg-warning-soft text-refund`, przelew `bg-transfer/10 text-transfer`; `title`/`aria-label` bez zmian.
- komórka: nowy opcjonalny prop `detail?: string | null`; druga linia = `[opis gdy jest kontrahent, detail]` złączone „ · ”, w `meta text-muted-foreground`; pierwsza linia `font-medium truncate` (body); bez `detail` (import) wynik jak dziś poza stylem.

#### 3. Licznik w `SegmentedControl`

**File**: `resources/js/components/SegmentedControl.vue`, `resources/css/app.css`

**Intent**: README Tabs wymaga licznika przy zakładce; przepis kontenera dostaje pełną ramkę i tło `background` (ADR-0037), co obejmuje też Plan miesiąca.

**Contract**: opcja `{ value, label, count?: number }`; gdy `count` podany, po etykiecie `<span class="segmented-control-count">` z liczbą. CSS: `.segmented-control` → `border bg-background rounded-lg` (zamiast `border-border/60 bg-muted/40 rounded-xl`), `.segmented-control-count` → `font-mono tabular-nums text-muted-foreground`, w zakładce `on` `text-foreground`. Bez zmian API dla obecnych konsumentów.

#### 4. Siatka, wiersz dnia, komórki

**File**: `resources/css/app.css`

**Intent**: Kolumny mockupu na jednej siatce dla wierszy, nagłówka i wiersza dnia (DataTable: jeden markup na każdą szerokość).

**Contract**:
- `.operations-grid`: telefon `minmax(0,1fr) auto`, obszary `'description amount' 'category actions'` (data ukryta, bo niesie ją wiersz dnia); md: `minmax(0,1fr) 11.25rem 6.5rem 8rem 4.5rem`, obszary `'description category date amount actions'`.
- `.operations-grid-transfer`: bez zmian kolumn (Data | Opis | Konta | Kwota | akcje), data widoczna na telefonie.
- `.operations-cell-date` na `.operations-grid`: `hidden md:block`, a w `-transfer` widoczna; `.operations-cell-amount` → `amount text-right`.
- nowy `.operations-day-row` (stoi po `.operations-grid`): `bg-muted text-muted-foreground border-b min-h-row py-1.5` + `text-xs`; obszary telefon `'label amount'`, md `'label label label amount actions'`; `.operations-day-label` (`grid-area: label`, `first-letter:uppercase`), suma w `.operations-cell-amount` + `amount-sum`.
- `.operations-header-row`, `.operations-row`: bez zmian przepisu poza nowymi obszarami.

#### 5. Straż literałów

**File**: `scripts/check-ui-literals.mjs`

**Intent**: Zamknąć lukę C5 przed przebudową widoku.

**Contract**: Do `FILES` dodać `resources/js/components/OperationTypeTile.vue`, `OperationsDateCell.vue`, `CompactAccountIdentity.vue`, `SegmentedControl.vue`. Nowe komponenty z Fazy 4 dochodzą w Fazie 4.

#### 6. Zrzuty konsumentów

**File**: `context/changes/ui-operations-atrament/screenshots/`

**Intent**: Kafelek i `SegmentedControl` zmieniają podgląd importu i Plan miesiąca.

**Contract**: `/dev/import-preview` i `/dev/month-plan` w obu motywach przy 1280 i 390 px (Playwright z cache `~/.cache/ms-playwright`, bez dodawania zależności; wzorzec plików z `context/archive/2026-11-05-design-tokens-atrament/screenshots/`).

### Success Criteria:

#### Automated Verification:

- Testy formaterów zielone: `npm test -- resources/js/lib/format.test.ts`
- `npm run check`, `npm run check:ui`, `npm run types:check` zielone
- `npm test` zielone

#### Manual Verification:

- Zrzuty podglądu importu i Planu miesiąca w obu motywach przy 1280 i 390 px: kafelek 26 px, wydatek bez czerwieni, `SegmentedControl` z pełną ramką bez regresji

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie ręcznych kroków.

---

## Phase 3: Kontrakt serwera

### Overview

Parametr `section`, sumy dni i niefiltrowane liczby sekcji jako propsy, po obu stronach w jednym commicie.

### Changes Required:

#### 1. Parametr `section`

**File**: `app/Http/Requests/IndexOperationsRequest.php`

**Intent**: Wybrana zakładka w URL (decyzja 1), zły parametr jak inne filtry (decyzja z research).

**Contract**: reguła `'section' => ['nullable', Rule::enum(OperationsListSection::class)]`, `blankToNull('section')` w `prepareForValidation`; nowa metoda `section(): OperationsListSection` (domyślnie `FinancialOperations`), poza `filters()`, więc `section` nie wpływa na `isActive()` ani `toArray()`.

#### 2. Sumy dni

**File**: `app/Support/OperationsDaySums.php` (nowy, `php artisan make:class`)

**Intent**: Suma dnia na serwerze dla całego dnia z tymi samymi filtrami (decyzja 4, ADR-0038), poza kontrolerem (Sloppy SL102).

**Contract**: `public static function forDates(User $user, OperationsListQuery $filters, list<string> $dates): array<string, int>` — pusta lista → `[]` bez zapytania; jedno zapytanie na świeżym `$user->financialOperations()` przez `$filters->apply(...)`, `whereDate` ≥ min i ≤ max dat, `selectRaw('operation_date, SUM(CASE WHEN type = ? THEN amount_minor_units ELSE -amount_minor_units END) AS day_sum', ['income'])`, `groupBy('operation_date')`; wynik kluczowany `CarbonImmutable::parse(...)->toDateString()` z `+=` i rzutem `(int)`, ograniczony do podanych dat.

#### 3. Liczby sekcji

**File**: `app/Support/OperationsSectionTotals.php` (nowy)

**Intent**: Y w liczniku „X z Y” (rekomendacja 9) i wspólne źródło `hasAnyRecords`.

**Contract**: `public static function forUser(User $user): array{financial_operations: int, own_transfers: int, capital_movements: int}` — trzy `count()`. `hasAnyRecords` = suma > 0 (zastępuje trzy `exists()`).

#### 4. Kontroler i propsy

**File**: `app/Http/Controllers/OperationsController.php`, `resources/js/pages/Operations.vue` (tylko `defineProps`)

**Intent**: Przekazać nowe odczyty; przy wyciszonych operacjach finansowych bez zapytania sumy.

**Contract**: nowe propsy `section: 'financial_operations' | 'own_transfers' | 'capital_movements'`, `operationDaySums: Record<string, number>` (data `Y-m-d` → grosze; daty z bieżącej strony operacji), `sectionTotals: Record<OperationsListSection, number>`; `hasAnyRecords` z `OperationsSectionTotals`. Typy w `defineProps` w `Operations.vue` w tym samym commicie.

#### 5. Testy Feature

**File**: `tests/Feature/OperationsDaySumsTest.php` (nowy), `tests/Feature/OperationsSectionTest.php` (nowy)

**Intent**: Pokryć kontrakt i tryby awarii (AGENTS.md: `assertInertia` dla każdego nowego propa).

**Contract**:
- sumy: dochód − wydatek + zwrot dla dnia; filtr konta zmienia sumę; dzień z 51 operacjami (granica strony) daje tę samą sumę na stronie 1 i 2; operacje innego użytkownika pominięte; sam filtr lokaty (sekcja wyciszona) → `operationDaySums` puste.
- sekcja: domyślnie `financial_operations`; `?section=own_transfers` w propie i w `ownTransfers.links` / `operations.links`; zła wartość → `assertSessionHasErrors('section')`; `sectionTotals` niefiltrowane przy aktywnym filtrze, `hasAnyRecords` bez zmian w `OperationsEmptyStateTest`.

### Success Criteria:

#### Automated Verification:

- Nowe i istniejące testy Operacji zielone na SQLite: `./vendor/bin/sail artisan test --compact tests/Feature/OperationsDaySumsTest.php tests/Feature/OperationsSectionTest.php tests/Feature/OperationsFilterTest.php tests/Feature/OperationsPaginationTest.php tests/Feature/OperationsEmptyStateTest.php`
- Te same testy zielone na PostgreSQL (komenda z AGENTS.md, „Test database”)
- NFR na PostgreSQL zielony: `tests/Feature/Nfr/NfrPerf01Test.php`
- `vendor/bin/pint --dirty --format agent` bez zmian po formatowaniu, `vendor/bin/sloppy ci` bez nowych znalezisk (host)
- `npm run types:check` zielone

#### Manual Verification:

- Na `/operations` (podgląd lub Sail) props `operationDaySums` w devtools odpowiada ręcznie policzonej sumie dnia dla dnia ze zwrotem

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie ręcznych kroków.

---

## Phase 4: Widok Operacji i stany

### Overview

Przebudowa `/operations` na elementach z Faz 2–3, kitchen sink z macierzą 7 stanów, straż, zrzuty.

### Changes Required:

#### 1. Query listy

**File**: `resources/js/lib/operations-list-query.ts` (nowy), `resources/js/lib/operations-list-query.test.ts`

**Intent**: Jedno miejsce budowania query `/operations`, żeby filtry, „Wyczyść filtry” i zakładki nie gubiły `section`.

**Contract**: `operationsListQuery(filters: OperationsListFilters, section: OperationsListSection): Record<string, string | number | undefined>` — 8 kluczy filtrów (null → undefined) + `section` (domyślna sekcja → undefined, żeby URL był krótki); bez numerów stron. Test: pomija null, zachowuje sekcję, domyślna sekcja bez parametru.

#### 2. Grupowanie po dniach

**File**: `resources/js/lib/operations-day-groups.ts` (nowy), `resources/js/lib/operations-day-groups.test.ts`

**Intent**: Wiersze dnia z wierszy strony i sum z serwera, bez liczenia kwot w TS.

**Contract**: `groupOperationsByDay<T extends { operation_date: string }>(rows: T[], daySums: Record<string, number>): { date: string; sumMinorUnits: number; rows: T[] }[]` — kolejność wierszy zachowana, nowa grupa przy zmianie daty, `sumMinorUnits = daySums[date] ?? 0`. Test: dwa dni, brak sumy w mapie, pusta lista.

#### 3. Wiersz tytułu z menu

**File**: `resources/js/components/OperationsPageActions.vue`, `lang/pl/ui.php`

**Intent**: Jedna akcja główna (decyzja 2, C4).

**Contract**: zarysowany `Button` „Importuj” (`Upload`, `t.operations.import_csv`, link do importu) i primary „Dodaj operację” (`Plus`, nowy klucz `operations.add_operation`) jako `DropdownMenuTrigger`; pozycje: Dochód (`ArrowDownLeft`), Wydatek (`ArrowUpRight`), Transfer własny (`ArrowLeftRight`), Ruch kapitału (`Landmark`) z `@select` → dotychczasowe emity; wyłączone pozycje (`operationsCreateAvailability`) mają drugą linię `meta` z powodem i `aria-describedby` na nią. Etykiety pozycji: nowe klucze `operations.menu_income`, `menu_expense`, `menu_own_transfer`, `menu_capital_movement`.

#### 4. Zakładki, panel i pasek filtrów

**File**: `resources/js/pages/Operations.vue`, `resources/js/components/OperationsFilters.vue`, `resources/js/components/OperationsActiveFilterChips.vue`, `resources/js/components/PaginationLinks.vue`, `lang/pl/ui.php`

**Intent**: Zakładki zamiast trzech paneli (decyzja 1), filtry w panelu nad wierszami (decyzja z research), licznik „X z Y”, przepis panelu zamiast ręcznych literałów (C1).

**Contract**:
- `Operations.vue`: `<h1 class="page-title">` + `<p class="meta text-muted-foreground">` (`t.operations.list_description`); `SegmentedControl` neutral (`aria-label` = nowy klucz `operations.sections_aria`) z opcjami trzech sekcji i `count` = `total` paginatora; zmiana → `router.get(operationsRoute({ query: operationsListQuery(filters, value) }), {}, { preserveState: true, preserveScroll: true, replace: true })`; jeden `ListSurface` aktywnej sekcji (`aria-busy`), slot `header` z `OperationsFilters` gdy `hasAnyRecords`.
- `OperationsFilters`: bez własnego panelu (`rounded-2xl`, `border-border/60`, `p-4` znikają); pasek `flex flex-wrap items-center gap-2 border-b pb-3`; nowe propsy `section`, `resultCount`, `sectionTotal`; `visit()` i „Wyczyść filtry” przez `operationsListQuery(next, section)`; po prawej `<span class="meta font-mono tabular-nums text-muted-foreground">` z kluczem `operations.result_count` („:shown z :total”); Badge licznika bez `rounded-xl`.
- `OperationsActiveFilterChips`: pigułka `bg-background border rounded-full h-control-sm` bez `border-border/60`/`rounded-xl`/`text-sm`; „Wyczyść filtry” bez zmian.
- `PaginationLinks`: `border-t` bez `/60`.
- Puste stany w panelu (`IndexEmptyState`): brak rekordów (jak dziś, przyciski „Importuj” outline + „Dodaj dochód”); pusty wynik filtrów w aktywnej sekcji („Wyczyść filtry” zachowuje `section`); sekcja wyciszona (`suppressedSections` zawiera wybraną) — nowy klucz `operations.section_suppressed_title` + istniejący `filter_suppressed_sections`, „Wyczyść filtry”; sekcja bez rekordów (`sectionTotals[section] === 0`) — nowe klucze `operations.empty_own_transfers`, `empty_capital_movements`, `empty_financial_operations`.
- Stopka panelu: `<p class="meta text-muted-foreground pt-3">` z `t.budget.amounts_in_zl`, a w transferach i ruchach kapitału przed nią objaśnienie (nowe klucze `operations.own_transfers_footer` „Transfery własne nie wpływają na budżet ani na płynność dysponowalną.”, `capital_movements_footer` „Ruch kapitału przenosi środki między kontem a lokatą bez wpływu na budżet.”).

#### 5. Wiersze

**File**: `resources/js/components/OperationsDayRow.vue` (nowy), `FinancialOperationRow.vue`, `OwnTransferRow.vue`, `CapitalMovementRow.vue`, `OperationsDateCell.vue`, `resources/js/pages/Operations.vue`

**Intent**: Wiersz dnia (pkt 8) i kolumny mockupu (decyzja 3); kwoty mono bez „zł” (C1).

**Contract**:
- `OperationsDayRow`: props `date`, `sumMinorUnits`; `.operations-grid .operations-day-row`, etykieta `formatDayLabel(date)`, suma `formatOperationDaySum` + `operationDaySumClass` + `amount-sum` w `.operations-cell-amount`; `role="row"` nie jest wymagane (lista na `div`, jak dziś).
- `Operations.vue`: operacje finansowe przez `groupOperationsByDay(operations.data, operationDaySums)` → `OperationsDayRow` + wiersze; nagłówek kolumn Opis | Kategoria | Data | Kwota | Akcje.
- `FinancialOperationRow`: kolejność komórek opis, kategoria, data, kwota, akcje; `detail` = nazwa konta (dla zwrotu „Zwrot · konto” z `t.operations.type_refund`); `CompactAccountIdentity` znika z wiersza; kwota z `formatFinancialOperationAmount` + `financialOperationAmountClass`.
- `OwnTransferRow`, `CapitalMovementRow`: kwota `formatMoneyAmount`, reszta bez zmian struktury.
- `OperationsDateCell`: jedna linia `formatDate(date)` w `font-mono tabular-nums text-muted-foreground`, bez daty względnej.

#### 6. Kitchen sink i straż

**File**: `resources/js/pages/dev/OperationsSink.vue`, `resources/js/lib/operations-fixtures.ts`, `scripts/check-ui-literals.mjs`

**Intent**: Bramka wizualna na prawdziwych komponentach (C5) z pełną macierzą stanów.

**Contract**: Sink renderuje `OperationsDayRow` i trzy wiersze zamiast ręcznych kopii; fikstury z dwoma dniami (dzień ze zwrotem i dodatnią sumą, dzień z ujemną sumą) i `operationDaySums`. Macierz: default (trzy zakładki), hover (wiersz), focus-visible (zakładka, przycisk menu, pozycja menu, pigułka), disabled (pozycje menu Transfer i Ruch kapitału z powodem, paginacja), error (N/A z powodem: lista nie ma pól), empty (brak rekordów, pusty wynik filtrów, sekcja wyciszona, sekcja bez rekordów), loading (`aria-busy`). Do `FILES` w `check-ui-literals.mjs` dodać `OperationsDayRow.vue`.

#### 7. Zrzuty

**File**: `context/changes/ui-operations-atrament/screenshots/`

**Intent**: Bramka wizualna z `/10x-ui`.

**Contract**: `/dev/operations` i `/operations` (dane demo `DemoHouseholdSeeder`) w obu motywach przy 1280 i 390 px, w tym zakładki transferów i ruchów kapitału, otwarte menu „Dodaj operację” i stan z filtrem.

### Success Criteria:

#### Automated Verification:

- Testy query i grupowania zielone: `npm test -- resources/js/lib/operations-list-query.test.ts resources/js/lib/operations-day-groups.test.ts`
- `npm run check`, `npm run check:ui`, `npm run types:check`, `npm test` zielone
- Skan literałów z `/10x-ui` na plikach widoku daje 0 trafień
- Pełna bramka CI w obrazie Sail: `composer ci:check`; `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- Zrzuty `/dev/operations` i `/operations` w obu motywach przy 1280 i 390 px odpowiadają mockupowi: nagłówki w jednej linii, przecinki groszy w kolumnie, suma dnia w kolumnie kwot, wydatek bez czerwieni
- Zakładka przetrwa paginację, zmianę filtra, „Wyczyść filtry” i odświeżenie; zakładka wyciszona filtrem pokazuje licznik 0 i notkę
- Menu „Dodaj operację” działa z klawiatury; wyłączona pozycja pokazuje powód
- Link z raportu/Dashboardu z filtrem kategorii otwiera Operacje z filtrem

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie ręcznych kroków.

---

## Testing Strategy

### Unit Tests:

- `format.test.ts`: kwota operacji i klasa (wydatek, dochód, zwrot), suma dnia (+, −, 0), etykieta dnia (rok bieżący i inny).
- `operations-list-query.test.ts`, `operations-day-groups.test.ts`.

### Integration Tests:

- `OperationsDaySumsTest`, `OperationsSectionTest` na SQLite i PostgreSQL; istniejące testy Operacji bez zmian asercji (poza `hasAnyRecords`, które ma zostać zgodne).

### Manual Testing Steps:

1. Otwórz `/operations` z danymi demo: wiersze dnia z sumą, wydatki w kolorze atramentu.
2. Przełącz zakładki, przejdź na stronę 2 transferów, zmień filtr — zakładka zostaje.
3. Ustaw filtr kategorii i wybierz Transfery własne — licznik 0, notka, „Wyczyść filtry”.
4. Otwórz menu „Dodaj operację” klawiaturą przy jednym koncie — Transfer wyłączony z powodem.
5. Zrób zrzuty w obu motywach przy 1280 i 390 px.

## Performance Considerations

Jedno dodatkowe zapytanie sumy (GROUP BY na indeksie `user_id, operation_date`) i trzy `count()` zamiast trzech `exists()`. NFR `NfrPerf01Test` (10 000 operacji, < 2 s) musi zostać zielony na PostgreSQL.

## Migration Notes

Bez migracji. Stare linki bez `section` otwierają domyślną zakładkę.

## References

- Research: `context/changes/ui-operations-atrament/research.md`
- Wzorzec: artefakt `https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA` (DataTable, FilterBar, Tabs, CategoryChip, Amount, MockupCAtrament → Operacje)
- Poprzednia zmiana UI: `context/archive/2026-11-05-budget-atrament-ui/plan.md` (wzorzec komórki kwoty, stopki, zrzutów)
- Suma ze znakiem: `app/Support/BudgetMonthResult.php:20-34`; grupowanie po dniu: `app/Support/MonthCategoryActuals.php:40-57`
- Menu z ikonami: `resources/js/components/BudgetCategoryRowActions.vue:25-126`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0038 istnieje, a ADR-0035 odsyła do niego — 105d005
- [x] 1.2 npm run check zielone (formatowanie Markdown) — 105d005

#### Manual

- [x] 1.3 ADR-0038, design-system.md i js.md mówią to samo o kolorze kwoty, sumie dnia i zakładkach — 105d005

### Phase 2: Warstwa współdzielona

#### Automated

- [x] 2.1 Testy formaterów zielone — 74dff9d
- [x] 2.2 npm run check, check:ui, types:check zielone — 74dff9d
- [x] 2.3 npm test zielone — 74dff9d

#### Manual

- [x] 2.4 Zrzuty podglądu importu i Planu miesiąca w obu motywach przy 1280 i 390 px bez regresji — 74dff9d

### Phase 3: Kontrakt serwera

#### Automated

- [x] 3.1 Nowe i istniejące testy Operacji zielone na SQLite — f062455
- [x] 3.2 Te same testy zielone na PostgreSQL — f062455
- [x] 3.3 NFR na PostgreSQL zielony — f062455
- [x] 3.4 Pint bez zmian, sloppy ci bez nowych znalezisk — f062455
- [x] 3.5 npm run types:check zielone — f062455

#### Manual

- [x] 3.6 operationDaySums odpowiada ręcznie policzonej sumie dnia ze zwrotem — f062455

### Phase 4: Widok Operacji i stany

#### Automated

- [x] 4.1 Testy query i grupowania zielone — 08791cc
- [x] 4.2 npm run check, check:ui, types:check, npm test zielone — 08791cc
- [x] 4.3 Skan literałów z /10x-ui na plikach widoku daje 0 trafień — 08791cc
- [x] 4.4 composer ci:check w obrazie Sail i sloppy ci na hoście zielone — 08791cc

#### Manual

- [x] 4.5 Zrzuty /dev/operations i /operations w obu motywach przy 1280 i 390 px zgodne z mockupem — 08791cc
- [x] 4.6 Zakładka przetrwa paginację, filtry, Wyczyść filtry i odświeżenie; wyciszona pokazuje 0 i notkę — 08791cc
- [x] 4.7 Menu Dodaj operację działa z klawiatury; wyłączona pozycja pokazuje powód — 08791cc
- [x] 4.8 Link z filtrem kategorii otwiera Operacje z filtrem — 08791cc
