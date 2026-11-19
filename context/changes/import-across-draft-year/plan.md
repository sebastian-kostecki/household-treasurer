# Import wyciągu z przełomu przy szkicu roku — plan implementacji

## Overview

Wyciąg 29.12.2026–02.01.2027, gdy Budżet 2027 jest szkicem, ma dać się zapisać dla wierszy grudnia. Przy wierszach stycznia użytkownik ma już w podglądzie widzieć, że dochód, wydatek i zwrot z 2027 zaimportuje dopiero po rozpoczęciu realizacji Budżetu 2027, i mieć odnośnik do tego Budżetu. To samo dotyczy roku, który nie ma Budżetu wcale (np. 30.12.2025 przed pierwszym Budżetem). Rok bez Budżetu jest zawsze bieżący albo przeszły: datę po dziś podgląd odrzuca jako `FutureDate` (`CsvImportPreviewValidator.php:27`), a zapis regułą `before_or_equal:today`.

Zapis zostaje atomowy (FR-IMP-20): zaznaczony wiersz, którego nie da się zapisać, blokuje „Zatwierdź import” z własnym powodem, a serwer przy obejściu klienta dalej wycofuje cały zestaw, tylko z komunikatem, który wskazuje datę wiersza i rok. Przelew własny i ruch kapitału w roku-szkicu przechodzą jak dziś.

Roadmapa: S-42 `import-across-draft-year` (MS-04). Test `CsvImportTest::test_statement_across_the_year_turn_is_refused_as_a_whole_while_the_new_year_is_a_draft_known_gap` przestaje być `_known_gap` i staje się zwykłym testem nowego kontraktu.

## Current State Analysis

- **Serwer nie sprawdza fazy w podglądzie.** `CsvImportPreviewValidator::validate` (`app/Support/CsvImport/CsvImportPreviewValidator.php:19-52`) sprawdza tylko datę, opis, walutę i kwotę, więc oba wiersze przełomu są `valid: true`.
- **Odmowa przychodzi przy zapisie, dla całego zestawu.** `FinancialOperationImportValidator::after` (`app/Support/CsvImport/FinancialOperationImportValidator.php:49-58`) woła `OperationCategoryChoice::check`, a `checkInBudget` zwraca `phaseMessage` dla szkicu (`app/Support/CategoryList/OperationCategoryChoice.php:86-87`) albo „does not belong to the budget for this operation year” dla braku Budżetu (`:82-84`). Wszystkie wiersze zapisuje jedna `DB::transaction` (`app/Actions/ImportFinancialOperations.php:78`), a błąd trafia do `rows` bez wskazania wiersza (pierwszy komunikat, `:272-278`).
- **Klient odmawia wcześniej, ale z ogólnym powodem.** Kategorie podglądu pochodzą tylko z Budżetów w realizacji (`app/Support/CategoryList/CategoryChoiceOptions.php:29-32` przez `ImportCategoryOptions`), więc wiersz 2027 ma pusty wybór z placeholderem „Brak kategorii wydatkowej dla roku operacji” (`resources/js/components/ImportPreviewRow.vue:229-233`, `lang/pl/ui.php:1082-1083`). Zaznaczony liczy się jako niekompletny (`resources/js/lib/import-preview-rows.ts:297-319`) i blokuje przycisk tekstem „Uzupełnij klasyfikację w zaznaczonych wierszach (:count)” (`resources/js/lib/import-submit-block.ts:31-42`). To dlatego próba w przeglądarce (T5) widziała odmowę w podglądzie, a test Feature (który omija klienta) odmowę przy zapisie.
- **Klient nie odróżnia szkicu od braku Budżetu.** Żaden prop podglądu nie niesie fazy roku (`app/Http/Controllers/CsvImportController.php:134-146`). `MonthRemainingBudgetContext.is_draft` istnieje, ale `ImportMonthRemainingContext::forUser` bierze tylko lata w realizacji (`app/Support/CsvImport/ImportMonthRemainingContext.php:37-40`).
- **Szkic blokuje tylko część typów.** Przelew własny i ruch kapitału nie mają sprawdzenia fazy (`OwnTransferImportValidator.php:22-84`, `CapitalMovementImportValidator.php:22-85`), z jednym wyjątkiem: odsetki wydzielone z ruchu kapitału idą przez `FinancialOperationImportValidator` jako dochód (`ImportFinancialOperations.php:218-240`). Typ wiersza wybiera użytkownik w podglądzie, więc serwer nie może oznaczyć całego wiersza jako niepoprawnego.

## Desired End State

Na wyciągu 29.12–02.01 przy szkicu 2027 (4 wiersze grudnia, 2 stycznia):

- wiersze stycznia pokazują notatkę „Budżet 2027 jest szkicem. Dochód, wydatek i zwrot z 2027 zaimportujesz po rozpoczęciu jego realizacji.” z odnośnikiem „Przejdź do Budżetu 2027”, który ustawia rok 2027 i otwiera stronę Budżetu;
- zaznaczony wydatek, dochód albo zwrot ze stycznia (także ruch kapitału z wydzielonymi odsetkami) blokuje „Zatwierdź import” powodem, który podaje liczbę takich wierszy i rok; zaznaczony przelew własny ze stycznia go nie blokuje;
- po odznaczeniu wierszy stycznia zapisują się 4 operacje grudnia;
- wiersz z roku bez Budżetu (np. 30.12.2025) pokazuje wariant „Nie ma Budżetu 2025 …” z przyciskiem „Utwórz Budżet 2025”, który otwiera dialog tworzenia Budżetu ustawiony na ten rok i nie wysyła Importu;
- żądanie z pominięciem klienta (zapis obu wierszy) jest odrzucone w całości, z błędem `rows`, który podaje datę wiersza i rok, a stan bazy się nie zmienia.

Sprawdzenie: testy Feature na PostgreSQL, Vitest dla logiki blokady, scenariusze w `/dev/import-preview`, `npm run check:ui`, ręczny przebieg na podglądzie worktree.

### Key Discoveries:

- Wiersze stoją wewnątrz `Form` zapisu Importu (`resources/js/pages/OperationsImportPreview.vue:313`), więc odnośnik w notatce nie może być zagnieżdżonym `<form>` (jak `DashboardBudgetBlock.vue:80-88`); wzorzec `Link method="post"` jest w `resources/js/components/ReportYearsTable.vue:161`.
- Link Inertii z metodą inną niż GET renderuje `<button type="button">` z `preventDefault` (`@inertiajs/vue3` `dist/index.js:1826-1853`, `:1910-1913`), więc nie wyśle formularza Importu; `as="button"` jest zbędne.
- `resources/js/ui-contract.test.ts:589-615`: każdy `.vue` z `<Link` i `BudgetController.selectYear()` musi mieć `@error=` i `listenForBudgetYearSelectionFailure` (wzorzec `ReportYearsTable.vue:70-84`), inaczej `npm run test` pada, a nieudany wybór roku przechodzi po cichu.
- `budget.select-year` przyjmuje tylko rok z istniejącym Budżetem (`app/Http/Requests/SelectBudgetYearRequest.php:22-25`), a GET `budget` pokazuje rok z `SelectedBudgetYear::resolve`, nigdy rok bez Budżetu. Dla braku Budżetu akcją jest więc dialog tworzenia: `useCreateBudgetDialog().openForYear(year)` (`resources/js/composables/useCreateBudgetDialog.ts:13`, dialog zamontowany w `AppSidebarLayout.vue`, użycie w `BudgetYearSwitcher.vue:137`); `budget.store` przekierowuje na `budget` (`BudgetController.php:128`).
- Rok wczytany z arkusza zawsze ma Budżet w realizacji (`LoadBudgetSheet.php:58-61`), więc kontrola „szkic albo brak Budżetu” go nie przechwyci, a komunikat arkusza zostaje (`SheetAccountTest.php:182-196`).
- Shell już wysyła `budgetYears: {year, phase}[]` (`ShellState.php:42`), ale kitchen sink renderuje prawdziwy `OperationsImportPreview` z propsami z fixtures (`ImportPreviewSink.vue:250`), a shell pochodzi z sesji dev. Dlatego stan roku idzie osobnym propem strony, który sink może ustawić.
- Po udanym zapisie podgląd znika z sesji (`CsvImportController.php:346`), a po nieudanym zostaje (`CsvImportSession`), więc styczeń importuje się ponownym wgraniem pliku; grudzień wraca wtedy jako duplikaty, niezaznaczone (ADR-0011).
- Nowa notatka wiersza wymaga nowego `ImportRowNoteKind`, inaczej blok notatek jej nie wyrenderuje (`.ai/rules/js-import.md`, `import-preview-rows.ts:321-337`).
- Klucze `no_*_category_for_year` są wspólne z `ExpenseFormDialog.vue:605-608` i `IncomeFormDialog.vue:495-498`; zmiana ich treści zmieniłaby dialogi.
- `ImportIntegrityTest.php:122` (rok 2025 bez Budżetu) sprawdza tylko klucz `rows`, nie treść, więc nowy komunikat go nie łamie.
- Tekst powodu blokady składa strona z `t.operations[reason.key]` i podstawia tylko `:count` (`OperationsImportPreview.vue:124-141`).

## What We're NOT Doing

- Zapis częściowy (pomijanie wierszy roku bez realizacji przy zapisie): odrzucony, bo łamie FR-IMP-20 i ryzyko #1 planu testów.
- Start realizacji z ekranu Importu: odrzucony, nieodwracalny start zostaje na stronie Budżetu, przy planie i liście kontrolnej.
- Oznaczanie całego wiersza jako niepoprawnego w `CsvImportPreviewValidator` (nowy `ImportRowInvalidReason`): zablokowałoby przelew własny i ruch kapitału, które w roku-szkicu są przyjmowane.
- Rok wczytany z arkusza (ADR-0085): inna reguła i inny komunikat, bez zmian.
- Zmiana treści `no_income_category_for_year` / `no_expense_category_for_year` i dialogów ręcznego wpisu.
- Powrót do tego samego podglądu po starcie realizacji (np. odnośnik „wróć do Importu” na stronie Budżetu) i zbiorowe odznaczanie wierszy.
- Przypomnienie w „Wymaga uwagi”, że Import nie przyjmie wierszy roku-szkicu: to S-41 (`year-turn-notices`).
- Spec E2E: E2E w CI to dopiero S-44.

## Implementation Approach

Serwer liczy stan roku dla każdego roku obecnego w wierszach podglądu (realizacja, szkic, brak Budżetu) i wysyła go jako nowy prop; logika zależna od typu żyje w czystych funkcjach `lib/import-*` z testami Vitest, a widok tylko je wyświetla. Serwer zachowuje atomową odmowę, ale sprawdza fazę roku sam, przed `OperationCategoryChoice`, żeby komunikat wskazywał wiersz i rok. Kolejność: najpierw kontrakt serwera i prop (faza 1, obie strony propa w jednym commicie), potem logika klienta test-first (faza 2), na końcu widok i kitchen sink (faza 3).

## Critical Implementation Details

- **Odnośnik w notatce nie jest formularzem.** Notatka renderuje się wewnątrz `Form` zapisu, więc „Przejdź do Budżetu 2027” to `Link` z `method="post"` i `data: { year }` na `BudgetController.selectYear` (wzorzec `ReportYearsTable.vue:70-84`, `:161`), z `ref` `pending` (wyłącza odnośnik w locie), `@error` → `notifyBudgetYearSelectionFailed(t.shell.select_year_failed)` i `onUnmounted(listenForBudgetYearSelectionFailure(() => pending.value, t.shell.select_year_failed))` (strażnik `ui-contract.test.ts:589-615`; toast pokaże tylko wiersz z `pending`). Wariant bez Budżetu to `<Button type="button">` „Utwórz Budżet :year”, który woła `useCreateBudgetDialog().openForYear(year)`. Przycisk w `<form>` bez `type="button"` wysłałby Import.
- **Kolejność powodów blokady.** Wiersz roku bez realizacji jest jednocześnie „niekompletny” (pusta kategoria), więc nowy powód musi stać przed `import_block_incomplete_rows`, inaczej użytkownik zobaczy stary ogólny tekst.
- **Pusta mapa w JSON.** PHP `[]` koduje się jako `[]`, a mapa z kluczami-latami jako obiekt; typ i odczyt po stronie klienta muszą przyjąć oba (brak klucza = brak notatki).

## Faza 1: Serwer — stan roku w podglądzie i odmowa zapisu z datą i rokiem

### Overview

Podgląd dostaje prop `budgetYearStates`, a zapis dochodu, wydatku, zwrotu albo odsetek w roku bez realizacji jest odrzucony w całości komunikatem z datą wiersza i rokiem. Decyzja trafia do ADR-0103.

### Changes Required:

#### 1. Stan roku Budżetu

**File**: `app/Enums/ImportBudgetYearState.php` (nowy), `app/Support/CsvImport/ImportBudgetYearStates.php` (nowy)

**Intent**: Jedno miejsce, które dla użytkownika i listy wierszy podglądu mówi, w jakim stanie jest Budżet każdego roku, z którego pochodzą daty wierszy. Używane przez prop podglądu i przez walidator zapisu.

**Contract**: enum string `ImportBudgetYearState` z `Execution = 'execution'`, `Draft = 'draft'`, `Missing = 'missing'`. `ImportBudgetYearStates::forRows(User $user, array $rows): array<int, ImportBudgetYearState>` (klucz = rok z `operation_date`, wiersze bez daty pominięte, jedno zapytanie o Budżety użytkownika z tych lat) i `ImportBudgetYearStates::forYear(User $user, int $year): ImportBudgetYearState`. Stan bierze się z `Budget::isExecution()` / `isDraft()` (`app/Models/Budget.php:71-79`).

#### 2. Prop podglądu po obu stronach

**File**: `app/Http/Controllers/CsvImportController.php` (`showPreview`, `:134-146`), `resources/js/pages/OperationsImportPreview.vue` (`defineProps`, `:50-68`), `resources/js/lib/import-preview-rows.ts` (typy), `resources/js/pages/dev/ImportPreviewSink.vue` i `resources/js/lib/import-preview-fixtures.ts` (domyślna wartość propa)

**Intent**: Klient dostaje stan roku dla każdego roku z wierszy, żeby odróżnić szkic od braku Budżetu. Prop zmienia się po obu stronach w jednym commicie (`.ai/rules/inertia-props.md`); kitchen sink dostaje domyślną mapę, żeby `types:check` przechodził.

**Contract**: prop `budgetYearStates` = mapa `{ "2026": "execution", "2027": "draft" }` (wartości enumu). Typ TS `ImportBudgetYearState = 'execution' | 'draft' | 'missing'` i `budgetYearStates: Partial<Record<string, ImportBudgetYearState>>` w `import-preview-rows.ts`.

#### 3. Odmowa zapisu z datą i rokiem

**File**: `app/Support/CsvImport/FinancialOperationImportValidator.php` (`after`, `:35-58`), `lang/pl.json`

**Intent**: Przed `OperationCategoryChoice::check` walidator sprawdza stan roku operacji i dla szkicu albo braku Budżetu dodaje komunikat wskazujący wiersz i rok. Ten walidator obsługuje dochód, wydatek, zwrot i odsetki z ruchu kapitału, więc przelew własny i ruch kapitału bez odsetek dalej przechodzą. Wyjątek nadal przerywa jedną transakcję, więc zestaw jest wycofany w całości.

**Contract**: dwa nowe klucze tłumaczeń w `lang/pl.json`, z parametrami `:date` (format `d.m.Y`) i `:year`:
- `Row :date: the :year budget is a draft. Start its execution to import income, expenses and refunds from :year.` → „Wiersz z :date: Budżet :year jest szkicem. Rozpocznij jego realizację, żeby zaimportować dochód, wydatek albo zwrot z :year.”
- `Row :date: there is no :year budget. Create it and start its execution to import income, expenses and refunds from :year.` → „Wiersz z :date: nie ma Budżetu :year. Utwórz go i rozpocznij realizację, żeby zaimportować dochód, wydatek albo zwrot z :year.”

Kolejność w `after()`: kontrola fazy stoi po sprawdzeniu daty otwarcia konta (`:40-47`), odpala się tylko dla `Draft` i `Missing`, a po dodaniu błędu kończy się `return`, bez wołania `OperationCategoryChoice::check`. Błąd dodany w `after()` nie jest objęty wczesnym returnem (`:36`), a do `rows` trafia `$errors[0]` (`ImportFinancialOperations.php:151-275`), więc bez `return` doszedłby drugi błąd `category_id`. Stan roku walidator czyta przez `ImportBudgetYearStates::forYear` z pamięcią per rok na instancji (jedna instancja na żądanie, wstrzykiwana w `ImportFinancialOperations.php:25`).

Rok wczytany z arkusza (zawsze Budżet w realizacji, `LoadBudgetSheet.php:58-61`) i reszta kontroli kategorii zostają przy `OperationCategoryChoice` bez zmian. Ręczny wpis (`OperationCategoryChoice::phaseMessage`) nie zmienia się.

#### 4. Testy Feature (PostgreSQL)

**File**: `tests/Feature/CsvImportTest.php`

**Intent**: Test `_known_gap` (`:685`) staje się zwykłym testem nowego kontraktu (zmieniło się wymaganie: komunikat wskazuje wiersz i rok; atomowość zostaje), a nowe przypadki przypinają rok bez Budżetu i zależność od typu.

**Contract**:
- `test_statement_across_the_year_turn_saves_december_and_names_the_draft_year_for_january` (zastępuje `_known_gap`): `assertInertia` na `budgetYearStates` (`2026` → `execution`, `2027` → `draft`) i `rows.*.valid === true`; zapis [0,1] → błąd `rows` z komunikatem szkicu dla `02.01.2027` i 2027, 0 operacji; zapis [0] → 1 operacja z 2026-12-29.
- `test_import_preview_marks_a_year_without_a_budget_as_missing_and_store_names_it`: wiersz z przeszłego roku bez Budżetu (np. 2025-12-30 przy Budżecie 2026) → `missing` w propie i komunikat „nie ma Budżetu” przy zapisie, 0 operacji.
- `test_own_transfer_row_in_a_draft_year_is_imported`: przelew własny z datą 2027 przy szkicu 2027 zapisuje się (dziś sprawdzone tylko przez ręczne trasy, `YearTurnTest.php:232`).
- `test_capital_movement_with_interest_in_a_draft_year_is_refused_as_a_whole`: ruch kapitału z wydzielonymi odsetkami w 2027 → komunikat szkicu, 0 operacji i 0 ruchów kapitału.

#### 5. ADR

**File**: `context/foundation/adr/0103-import-row-in-a-year-without-budget-in-execution.md` (nowy)

**Intent**: Zapisuje decyzję: zestaw Importu zostaje atomowy; rok bez Budżetu w realizacji jest wskazany w podglądzie per wiersz i zależnie od typu (dochód, wydatek, zwrot, odsetki), a nie jako wiersz niepoprawny; akcja to odnośnik do Budżetu, nie start realizacji; serwerowy komunikat podaje datę i rok. Odrzucone: zapis częściowy, start w podglądzie, nowy `ImportRowInvalidReason`.

**Contract**: format i numeracja jak `0102-sheet-load-of-2019.md`; odnośniki do ADR-0011, ADR-0052, ADR-0063, ADR-0067 i FR-IMP-20.

### Success Criteria:

#### Automated Verification:

- Testy Importu przechodzą na PostgreSQL (polecenie worktree z AGENTS.md, baza `testing_import_across_draft_year`): `tests/Feature/CsvImportTest.php`, `tests/Feature/ImportIntegrityTest.php`, `tests/Feature/YearTurnTest.php`, `tests/Feature/SheetAccountTest.php`
- W `tests/` nie ma już nazwy `test_statement_across_the_year_turn_is_refused_as_a_whole_while_the_new_year_is_a_draft_known_gap`
- `vendor/bin/pint --dirty --format agent` bez zmian po formatowaniu
- `npm run build` i `npm run types:check` przechodzą
- `vendor/bin/sloppy ci` bez nowych znalezisk

---

## Faza 2: Klient — logika blokady zależna od typu

### Overview

Czyste funkcje w `lib/import-*` mówią, czy wiersz pokazuje notatkę roku i czy zaznaczony wiersz blokuje zapis; `importSubmitBlockReason` dostaje nowy powód. Testy Vitest powstają przed kodem.

### Changes Required:

#### 1. Stan roku wiersza i „wymaga realizacji”

**File**: `resources/js/lib/import-preview-rows.ts`, `resources/js/lib/import-preview-rows.test.ts`

**Intent**: Jedna funkcja odczytuje stan roku wiersza z mapy, druga mówi, czy przy danym typie wiersz potrzebuje Budżetu w realizacji. Notatka pokazuje się, gdy rok jest szkicem albo nie ma Budżetu, a wybrany typ to dochód, wydatek, zwrot, ruch kapitału z wpisaną kwotą odsetek albo typ jeszcze niewybrany; przelew własny i ruch kapitału bez odsetek jej nie mają.

**Contract**:
- `rowBudgetYearState(row: ImportRow, states): ImportBudgetYearState | null` (null bez daty albo bez klucza w mapie);
- `rowBudgetYearNotice(row, selection, states): { state: 'draft' | 'missing'; year: number } | null`;
- `rowNeedsBudgetInExecution(row, selection, states): boolean` = `selection.selected && rowBudgetYearNotice(...) !== null && selection.type !== undefined`.
- Odsetki liczą się, gdy `parsedInterestAmountMinorUnits(selection) !== null` (`import-preview-rows.ts:224-232`).

#### 2. Nowy powód blokady

**File**: `resources/js/lib/import-submit-block.ts`, `resources/js/lib/import-submit-block.test.ts`, `resources/js/pages/OperationsImportPreview.vue` (wywołanie w `blockReason`, `:124-129`)

**Intent**: Zaznaczone wiersze z roku bez realizacji blokują zapis własnym powodem, sprawdzanym po „nic nie zaznaczono”, a przed „niekompletnymi” i duplikatami. Nowy parametr jest wymagany, więc w tej samej fazie strona przekazuje `props.budgetYearStates` (prop istnieje od fazy 1), a istniejące wywołania w `import-submit-block.test.ts` (`:38`, `:51`, `:67`, `:83`, `:86`, `:93`) dostają mapę; inaczej `types:check` (tsconfig obejmuje testy) pada do fazy 3.

**Contract**: `importSubmitBlockReason(rows, selections, termDeposits, duplicateAcknowledged, budgetYearStates)`; nowy wariant unii `{ key: 'import_block_budget_year_not_in_execution'; count: number; years: number[] }` (lata rosnąco, bez powtórzeń).

Przypadki Vitest: zaznaczony wydatek 2027 przy szkicu → nowy powód, `count` i `years: [2027]`; zaznaczony przelew własny 2027 → brak powodu; ruch kapitału 2027 bez odsetek → brak, z odsetkami → powód; rok `missing` → powód; rok `execution` → zachowanie jak dziś; niezaznaczony wiersz 2027 → brak powodu; pierwszeństwo przed `import_block_incomplete_rows` i przed duplikatami; dwa lata (2025 `missing`, 2027 `draft`) → `years: [2025, 2027]`.

### Success Criteria:

#### Automated Verification:

- Nowe testy Vitest najpierw padają, potem przechodzą: `npm run test -- resources/js/lib/import-preview-rows.test.ts resources/js/lib/import-submit-block.test.ts`
- Cały zestaw Vitest przechodzi: `npm run test`
- `npm run types:check` i `npm run check` przechodzą

---

## Faza 3: Klient — notatka wiersza, teksty i kitchen sink

### Overview

Wiersz pokazuje notatkę z odnośnikiem, pasek zapisu pokazuje nowy powód, a `/dev/import-preview` ma scenariusz przełomu ze szkicem 2027 i rokiem bez Budżetu.

### Changes Required:

#### 1. Notatka w wierszu

**File**: `resources/js/components/ImportPreviewRow.vue`, `resources/js/lib/import-preview-rows.ts` (`ImportRowNoteKind`), `resources/js/lib/import-preview-rows.test.ts` (lista `kinds`, `:12-19`), `resources/js/pages/OperationsImportPreview.vue`, `resources/js/pages/dev/ImportPreviewSink.vue` (jawne props `ImportPreviewRow`, `:331`)

**Intent**: Strona podaje `budgetYearStates` do wiersza, wiersz pokazuje `rowBudgetYearNotice` jako `<Alert variant="note">` w bloku notatek, z akcją: szkic → `Link` POST na `BudgetController.selectYear` z `year`, z `pending`, `@error` i `listenForBudgetYearSelectionFailure`; brak Budżetu → `<Button type="button">`, który woła `useCreateBudgetDialog().openForYear(year)`. Placeholder wyboru kategorii zostaje jak dziś.

**Contract**: nowy `ImportRowNoteKind` `'budgetYear'` i flaga w `importRowHasNotes` (oraz w liście `kinds` testu); nowy prop wiersza `budgetYearStates`, przekazany też w `ImportPreviewSink.vue:331`. Odnośnik jest `Link`, nie `<form>`, a plik spełnia strażnika `ui-contract.test.ts:589-615` (patrz Critical Implementation Details).

#### 2. Teksty

**File**: `lang/pl/ui.php` (grupa `operations`), `resources/js/pages/OperationsImportPreview.vue` (`blockReason`, `:124-141`)

**Intent**: Treść notatki, odnośnika i powodu blokady po polsku; `blockReason` podstawia `:count` i `:years` dla nowego powodu.

**Contract**: klucze w `operations`:
- `import_budget_year_draft_note` => 'Budżet :year jest szkicem. Dochód, wydatek i zwrot z :year zaimportujesz po rozpoczęciu jego realizacji.'
- `import_budget_year_missing_note` => 'Nie ma Budżetu :year. Dochód, wydatek i zwrot z :year zaimportujesz po utworzeniu Budżetu i rozpoczęciu jego realizacji.'
- `import_budget_year_draft_link` => 'Przejdź do Budżetu :year'
- `import_budget_year_missing_action` => 'Utwórz Budżet :year'
- `import_block_budget_year_not_in_execution` => 'Zaznaczone wiersze z :years wymagają Budżetu w realizacji (:count). Odznacz je albo rozpocznij realizację Budżetu.'

Lata w `:years` łączone przecinkiem (`2025, 2027`). `UiTranslationKeysTest` widzi tylko literalne `t.group.key`, a nie `t.operations[reason.key]`, więc `tests/Unit/ImportPreviewHardcodedUiTest.php` dostaje przypadek: każdy `key: 'import_block_…'` z `resources/js/lib/import-submit-block.ts` istnieje w `lang/pl/ui.php` → `operations`.

#### 3. Kitchen sink i fixtures

**File**: `resources/js/lib/import-preview-fixtures.ts`, `resources/js/pages/dev/ImportPreviewSink.vue`

**Intent**: Nowe stany widać w kitchen sinku (`.ai/rules/js-import.md`): wiersze 2025-12-30 (brak Budżetu), 2026-12-29 (realizacja), 2027-01-02 jako wydatek i jako przelew własny (szkic), scenariusz paska zapisu zablokowanego nowym powodem; wpis w `stateMatrix`, gdzie te stany są widoczne. Sink dostaje stan roku przez prop strony, nie przez shell (Key Discoveries).

**Contract**: fixture `importPreviewBudgetYearStates` (`2025` missing, `2026` execution, `2027` draft) i scenariusz `year-turn-draft` obok istniejących (`ImportPreviewSink.vue:248-294`).

### Success Criteria:

#### Automated Verification:

- `npm run check:ui` przechodzi
- `tests/Unit/ImportPreviewHardcodedUiTest.php` przechodzi
- `npm run build`, `npm run types:check`, `npm run check` i `npm run test` przechodzą
- Pełna bramka: `composer ci:check` na `testing_import_across_draft_year` (polecenie worktree z AGENTS.md) i `vendor/bin/sloppy ci`

#### Manual Verification:

- `/dev/import-preview`: notatka szkicu i braku Budżetu oraz nowy powód blokady wyglądają zgodnie z Atramentem w jasnym i ciemnym motywie, na szerokości telefonu i od `xl`
- Podgląd worktree (`context/foundation/agents/worktree-preview.md`), konto z 2026 w realizacji i 2027 w szkicu, plik 29.12–02.01: wiersze stycznia mają notatkę; zaznaczony wydatek ze stycznia blokuje przycisk nowym powodem; zaznaczony przelew własny ze stycznia nie blokuje
- „Przejdź do Budżetu 2027” otwiera stronę Budżetu z rokiem 2027 i nie wysyła Importu
- Wiersz z 2025 bez Budżetu: „Utwórz Budżet 2025” otwiera dialog tworzenia z rokiem 2025 i nie wysyła Importu
- Po odznaczeniu stycznia zapisują się operacje grudnia; po starcie 2027 ponowne wgranie pliku pokazuje grudzień jako duplikaty, a styczeń z kategoriami i zapisuje się

---

## Testing Strategy

### Unit Tests:

- Vitest: `rowBudgetYearState`, `rowBudgetYearNotice`, `rowNeedsBudgetInExecution` i nowy powód `importSubmitBlockReason` (przypadki w fazie 2).
- `ImportPreviewHardcodedUiTest` pilnuje literałów w zmienionych plikach widoku.

### Integration Tests:

- Feature na PostgreSQL (`CsvImportTest`): prop `budgetYearStates` przez `assertInertia`, atomowa odmowa z komunikatem szkicu i braku Budżetu, przelew własny w roku-szkicu przechodzi, ruch kapitału z odsetkami w roku-szkicu wycofuje całość.
- `ImportIntegrityTest` i `YearTurnTest` dalej przechodzą bez zmian.

### Manual Testing Steps:

1. Podgląd worktree z kontem: 2026 w realizacji, 2027 w szkicu.
2. Import pliku 29.12–02.01 (4 wiersze grudnia, 2 stycznia, w tym jeden przelew własny).
3. Zaznacz wszystkie wydatki: przycisk zablokowany powodem z 2027; odznacz styczeń: zapis 4 operacji grudnia.
4. „Przejdź do Budżetu 2027”, rozpocznij realizację, wgraj plik ponownie: grudzień jako duplikaty, styczeń z kategoriami, zapis 2 operacji.

## Performance Considerations

`ImportBudgetYearStates::forRows` robi jedno zapytanie o Budżety z lat obecnych w podglądzie (zwykle 1–2 lata); walidator zapisu może czytać stan roku raz na rok, a nie raz na wiersz.

## Migration Notes

Brak migracji i zmian danych. Podgląd zapisany w sesji przed wdrożeniem dalej działa, bo prop liczy się przy każdym `showPreview`.

## References

- Roadmapa: `context/foundation/roadmap.md` S-42, MS-04 (główny checkout)
- Próba przełomu: `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md:31`, `:39`, `:45`; `research.md:38`, `:88-94`
- Integralność Importu: `context/archive/2026-11-17-testing-import-integrity/research.md:33`, `:42`; `tests/Feature/ImportIntegrityTest.php:122`
- Wymagania: `context/foundation/prd-mvp.md:256` (FR-IMP-09), `:267` (FR-IMP-20), `:342`, `:352-353`; `context/foundation/test-plan.md:60`, `:145`
- ADR: 0011 (wiersze startują niezaznaczone), 0052 (podgląd), 0063 i 0067 (kategoria i Budżet w realizacji)
- Wzorzec odnośnika POST: `resources/js/components/ReportYearsTable.vue:161`
- Reguły: `.ai/rules/js-import.md`, `.ai/rules/js.md`, `.ai/rules/inertia-props.md`, `.ai/rules/wayfinder.md`, `.ai/rules/sloppy.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Serwer — stan roku w podglądzie i odmowa zapisu z datą i rokiem

#### Automated

- [x] 1.1 Testy Importu przechodzą na PostgreSQL (CsvImportTest, ImportIntegrityTest, YearTurnTest, SheetAccountTest)
- [x] 1.2 W tests/ nie ma już nazwy testu _known_gap Importu
- [x] 1.3 Pint bez zmian po formatowaniu
- [x] 1.4 npm run build i npm run types:check przechodzą
- [x] 1.5 vendor/bin/sloppy ci bez nowych znalezisk

### Phase 2: Klient — logika blokady zależna od typu

#### Automated

- [ ] 2.1 Nowe testy Vitest najpierw padają, potem przechodzą
- [ ] 2.2 Cały zestaw Vitest przechodzi
- [ ] 2.3 npm run types:check i npm run check przechodzą

### Phase 3: Klient — notatka wiersza, teksty i kitchen sink

#### Automated

- [ ] 3.1 npm run check:ui przechodzi
- [ ] 3.2 ImportPreviewHardcodedUiTest przechodzi
- [ ] 3.3 npm run build, types:check, check i cały Vitest przechodzą
- [ ] 3.4 Pełna bramka composer ci:check na testing_import_across_draft_year i sloppy ci

#### Manual

- [ ] 3.5 Kitchen sink: notatki i powód blokady zgodne z Atramentem w obu motywach i szerokościach
- [ ] 3.6 Podgląd worktree: notatki stycznia, blokada wydatku, przelew własny nie blokuje
- [ ] 3.7 Odnośnik do Budżetu 2027 otwiera Budżet i nie wysyła Importu
- [ ] 3.8 Przycisk „Utwórz Budżet 2025” otwiera dialog z rokiem 2025 i nie wysyła Importu
- [ ] 3.9 Grudzień zapisuje się po odznaczeniu stycznia; po starcie 2027 styczeń importuje się ponownym wgraniem
