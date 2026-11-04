# Kontrakt UI podglądu importu CSV — plan implementacji

## Overview

Widok podglądu importu (`resources/js/pages/OperationsImportPreview.vue`, wiersz `resources/js/components/ImportPreviewRow.vue`, trasa `operations.import.preview`) przechodzi na kontrakt istniejącego systemu projektowego:

- nazwane klasy nagłówka i wiersza w `app.css`, podpisy przez `column-caption`, tinty wiersza z tokenów (nowy token roli `pair` w obu motywach);
- pole „Kwota odsetek” na `<Input>` z `components/ui/input`, kolor kwoty przez `amountToneClass`;
- każda kontrolka wiersza ma dostępną nazwę (w tym `ariaLabel` w `CategorySelect` / `AccountSelect`);
- ostrzeżenia mówią prawdę: alert „ujemne Do wydania” w wierszu wreszcie się renderuje, alert duplikatów pojawia się tylko przy duplikatach, a zastrzeżenie o granicach wykrywania zostaje jako notka w karcie podsumowania;
- „Zatwierdź import” pokazuje widoczny powód blokady i spinner w czasie zapisu;
- zatwierdzenie podglądu z wierszem niepoprawnym przestaje kończyć się niewidocznym błędem walidacji (C6).

Na końcu zmiany zostają kitchen sink `/dev/import-preview` (7 stanów), pliki widoku na liście `npm run check:ui` i reguła dla następnego agenta. Domena importu (parowanie, wykrywanie duplikatów, liczenie ostrzeżeń) się nie zmienia.

## Current State Analysis

Pełny audyt z dowodami: `context/changes/import-preview-ui-contract/research.md` (`## Charges` C1–C6, Deferred D1–D4, `## Decisions` 1–4). W skrócie:

- **C1:** nagłówek kolumn i 6 podpisów mobilnych złożonych ręcznie z `text-[11px]` (`OperationsImportPreview.vue:301`; `ImportPreviewRow.vue:296,309,324,337,370,508`); podpisy mobilne mają `xl:hidden`, więc na `xl` znikają też dla czytnika.
- **C2:** wiersz z literałem `hover:bg-foreground/[0.03]` (`ImportPreviewRow.vue:266`) i tintem `bg-sky-500/10` (`:269`, klasa palety, bez wersji dark). `.import-preview-grid` i komórki istnieją (`app.css:567-631`), brakuje klasy nagłówka i wiersza.
- **C3:** `class="native-input"` (`ImportPreviewRow.vue:461`) nie ma definicji — surowy input przeglądarki.
- **C4:** `hasNotes` (`ImportPreviewRow.vue:157-164`) pomija `toSpendWarning`, a ostrzeżenie to-spend powstaje tylko wtedy, gdy wszystkie 5 członów `hasNotes` jest fałszywych — alert (`:551`) nie renderuje się nigdy. Alert duplikatów (`OperationsImportPreview.vue:273-281`) jest bezwarunkowy; oba alerty strony w wariancie domyślnym zamiast `warning`.
- **C5:** „Zatwierdź import” (`:374`) wyłączony bez tekstu powodu (`canSubmit`, `:116-136`; w stanie początkowym nic nie jest zaznaczone). Checkbox wiersza, selecty typu / pary / lokaty i `CategorySelect` / `AccountSelect` bez dostępnej nazwy. `rowAmountClass` (`lib/import-preview-rows.ts:212-218`) maluje zero i `null` na zielono.
- **C6:** wiersz niepoprawny wysyła ukryte `rows[N][index]` i `rows[N][selected]`, ale nie `rows[N][type]` (`ImportPreviewRow.vue:283-292,341`); reguła `rows.*.type` jest `required` (`StoreCsvImportRequest.php:25`). Strona pokazuje tylko `errors.rows` i `errors.duplicate_warning_acknowledged` (`OperationsImportPreview.vue:370-371`), więc błąd `rows.N.type` jest niewidoczny — przycisk „nic nie robi”.
- **Stany:** pusty podgląd to goły `<p>` pod nagłówkiem (`:326-331`) i stały alert; ładowanie to tylko `:disabled="processing"`. Brak kitchen sinka; pliki widoku nie są na liście `FILES` (`scripts/check-ui-literals.mjs:9-52`).

## Desired End State

- **C1–C2.** Pliki widoku nie zawierają `text-[11px]`, `/[0.03]` ani klas palety. Nagłówek to `import-preview-grid import-preview-header-row` (ukryty poniżej `xl`), wiersz to `import-preview-row` z modyfikatorem `import-preview-row-duplicate` (`bg-warning/10`) albo `import-preview-row-pair` (`bg-pair/10`). Podpisy mobilne to `column-caption xl:sr-only`. Token `--pair` istnieje w `:root` i `.dark`, publikowany jako `--color-pair`.
- **C3.** „Kwota odsetek” to `<Input>` z ramką, pierścieniem fokusu i stylem `aria-invalid`.
- **C4.** Wiersz z ostrzeżeniem to-spend pokazuje `Alert variant="warning"` z kwotą i miesiącem. Alert „Możliwe duplikaty” pokazuje się tylko przy `duplicateRowCount > 0`, w `variant="warning"`, z krótką treścią z liczbą; obecna treść o granicach wykrywania jest zawsze widoczna jako notka `text-xs text-muted-foreground` w karcie podsumowania. Alert par jest w `variant="warning"`.
- **C5.** Pod „Zatwierdź import”, gdy przycisk jest wyłączony, stoi jedna linia powodu (priorytet: brak zaznaczenia → niekompletne zaznaczone wiersze z liczbą → niepotwierdzone duplikaty), wskazana przez `aria-describedby`. W czasie `processing` przycisk pokazuje `Spinner` z `aria-label="Ładowanie"`. Checkbox i każdy select wiersza mają dostępną nazwę z nazwą kolumny i datą/opisem wiersza. Kwota ma kolor z `amountToneClass` (zero i `null` bez koloru); `rowAmountClass` nie istnieje.
- **C6.** Zatwierdzenie podglądu z wierszem niepoprawnym (niezaznaczonym) importuje zaznaczone wiersze. Zaznaczony wiersz bez `type` dalej daje błąd walidacji. Każdy błąd `rows` / `rows.N.*` jest widoczny pod listą.
- **Pusty stan.** Podgląd bez wierszy pokazuje `IndexEmptyState` z akcją powrotu do wgrywania, bez karty podsumowania, alertów, nagłówka kolumn i formularza.
- **Kitchen sink.** `/dev/import-preview` (tylko `APP_ENV=local`) pokazuje każdą komórkę macierzy 7 stanów albo „N/A” z powodem. W folderze zmiany leżą zrzuty desktop (1280) i mobile (390) po każdej fazie wizualnej.
- **Utrwalenie.** `npm run check:ui` obejmuje pliki widoku, `CategorySelect`, `AccountSelect`, nowy komponent paska i kitchen sink. `.ai/rules/js.md` i `.docs/design-system.md` opisują klasy listy importu, token `pair` i kitchen sink.

### Key Discoveries:

- Samo poluzowanie reguły `type` nie wystarczy: `rowSelections()` (`StoreCsvImportRequest.php:93-98`) rzuca `InvalidArgumentException` dla `type` innego niż 5 wartości, więc `null` z niezaznaczonego wiersza dałby 500. Kontroler i tak pomija niezaznaczone (`CsvImportController.php:167-170`), a `rowSelections()` ma jedno wywołanie.
- Istniejący test `test_user_can_import_only_selected_valid_rows_when_file_contains_invalid_rows` (`tests/Feature/CsvImportTest.php:256-297`) wysyła ręcznie tylko wiersz poprawny — dlatego nie łapie C6. Wiersz `bad-date` jest odfiltrowany w podglądzie; wiersz niepoprawny, który zostaje w sesji, daje np. data w przyszłości (`:216-254`).
- Wzorzec klas: `.operations-header-row` / `.operations-row` (`app.css:500-519`). Nagłówek importu przełącza się na `grid` dopiero od `xl` (`OperationsImportPreview.vue:301`), a `@variant xl .import-preview-grid` ustawia tylko kolumny (`app.css:592-601`).
- Wzorzec powodu blokady: `OperationsPageActions.vue` (`useId()`, `<p :id>` + `:aria-describedby` tylko gdy powód istnieje). `t.common.loading` = „Ładowanie” już istnieje (`lang/pl/ui.php:629`).
- `CategorySelect.vue:48-59` i `AccountSelect.vue:48-59` mają `<div>` jako korzeń bez `inheritAttrs: false`, więc `aria-label` z zewnątrz trafia na `div`, nie na `SelectTrigger`. Oba przechodzą `check:ui` już dziś.
- `ui/input/Input.vue` przekazuje `id`, `inputmode`, `placeholder`, `aria-*` do `<input>` i stylizuje `aria-invalid` (`:29`).
- Wszystkie wiersze startują jako niezaznaczone (`initialSelection`, `lib/import-preview-rows.ts:69-93`), także duplikaty.
- Tłumaczenia `operations` mają typ `Record<string, string>` (`resources/js/types/shell.ts:45`) — nowe klucze nie wymagają zmiany typu.
- Testy importu są w stylu klas PHPUnit (`test_*` w `CsvImportTest.php`), z helperem `createImportFixture`.

## What We're NOT Doing

- Ekran wgrywania pliku `OperationsImport.vue` i issue #186 (D3).
- Polskie komunikaty błędów serwera importu i `lang/pl.json` (D2) — błędy `rows.*` pokazujemy w takim języku, w jakim przyjdą.
- Wspólna klasa wiersza listy dla całej aplikacji (D1); rozmiar `h1` (D4).
- Zmiana domyślnego zaznaczenia wierszy (wiersze dalej startują jako niezaznaczone).
- Oznaczanie niekompletnych zaznaczonych wierszy (`aria-invalid` / obramowanie) — odrzucone na rzecz jednej linii powodu.
- Kolor kwoty według wybranego typu (jak lista Operacji) — decyzja: `amountToneClass` ze znaku z wyciągu.
- Zmiana roli tokenu `transfer` (`.docs/design-system.md:34` bez zmian).
- Zmiany dialogów używających `CategorySelect` / `AccountSelect` (prop `ariaLabel` jest opcjonalny; dialogi dalej używają `<Label for>`).
- Zmiany w `components/ui/*`, nowe zależności (Playwright w repo, `@vue/test-utils`), bramka zrzutów w CI.
- Logika parowania, wykrywania duplikatów i liczenia ostrzeżeń to-spend / month-remaining.

## Implementation Approach

Kolejność `/10x-ui`: środowisko → (poprawka funkcjonalna C6) → kontrakt (token, klasy, funkcje) → widok → stany → utrwalenie, jak w `context/archive/2026-11-03-operations-ui-contract/plan.md`.

Kitchen sink powstaje w fazie 1, żeby każda faza wizualna kończyła się zrzutem tego samego miejsca. C6 idzie osobną fazą test-first przed zmianami wizualnymi: najpierw test, który na HEAD daje błąd `rows.N.type`, potem poprawka po stronie serwera. Logika z szablonów (czy wiersz ma notatki, powód blokady, pierwszy błąd wierszy) trafia do `resources/js/lib` z testami `*.test.ts`, bo repo nie testuje komponentów Vue. Pasek zatwierdzenia wydzielamy do komponentu, żeby kitchen sink renderował prawdziwy przycisk w stanach disabled i loading.

Nowe klucze tłumaczeń zmieniają `lang/pl/ui.php` i asercję `assertInertia` w tym samym commicie (AGENTS.md „Inertia props contract”).

## Critical Implementation Details

**C6: reguła i `rowSelections()` razem.** `rows.*.type` dostaje `required_if:rows.*.selected,true` + `nullable` + `Rule::in(...)`. Po `prepareForValidation` `selected` jest PHP-owym `bool`, a `required_if` porównuje z `true` jako bool tylko dlatego, że wartość pola jest bool — test z fazy 2 to potwierdza w obie strony (niezaznaczony bez `type` przechodzi, zaznaczony bez `type` daje błąd). `rowSelections()` musi odfiltrować niezaznaczone wiersze przed `match` na `type`, inaczej 500.

**Kolejność klas w `app.css`.** `.import-preview-header-row` stoi po `.import-preview-grid` i jego `@variant md` / `@variant xl` (wszystkie ustawiają `display` albo kolumny), a `display: grid` dostaje w osobnym `@variant xl` — inaczej `hidden` przegra albo nagłówek pokaże się poniżej `xl`. Modyfikatory tintu stoją po `.import-preview-row`.

**Trasa tylko dla `local` a Wayfinder.** `Route::inertia('dev/import-preview', 'dev/ImportPreviewSink')` w istniejącym bloku `if (app()->environment('local'))` (`routes/web.php:42-45`), bez nazwy trasy — nazwana trasa tylko-lokalna łamie `npm run types:check` w CI.

**Zrzuty.** Jednorazowy skrypt Node przez `npx -y -p playwright@<wersja zgodna z chromium w ~/.cache/ms-playwright>`, niecommitowany:

1. otwiera podgląd z worktree (AGENTS.md „Preview from a worktree”, http://localhost:8090);
2. loguje się jako `test@example.com` / `password`;
3. zapisuje pełną stronę `/dev/import-preview`;
4. wgrywa przez formularz `/operations/import` mały CSV (wiersz dochodu, wydatku, wiersz z datą w przyszłości) na konto testowe i zapisuje `/operations/import/preview`;
5. oba przy 1280×900 i 390×844 do `context/changes/import-preview-ui-contract/screenshots/phase-<N>-<strona>-<szerokość>.png`.

## Faza 1: Środowisko, kitchen sink i punkt odniesienia

### Overview

Przygotować worktree z osobną bazą, postawić `/dev/import-preview` z danymi testowymi na dzisiejszych komponentach i zrobić zrzuty bazowe. Brak zmian w widoku.

### Changes Required:

#### 1. Środowisko worktree

**File**: worktree na gałęzi `import-preview-ui-contract` (z `development`), `.env` (niecommitowany)

**Intent**: Kroki „Fresh checkout” (`composer install`, `.env`, `key:generate`, `npm ci`, `npm run build`) i osobna baza podglądu, żeby testy, typy i zrzuty biegły na kodzie tej gałęzi.

**Contract**: `DB_DATABASE=import_preview_ui_contract` (nigdy `household_treasurer`); `create database` w kontenerze `household-treasurer-pgsql-1`; `migrate --seed --force` obrazem Sail w sieci `household-treasurer_sail` (AGENTS.md „Preview from a worktree”).

#### 2. Dane testowe

**File**: `resources/js/lib/import-preview-fixtures.ts` (nowy)

**Intent**: Stałe dane dla kitchen sinka pokrywające każdy wariant wiersza i notatki.

**Contract**: eksporty typowane istniejącymi typami (`ImportRow`, `RowSelection`, `CategoryRow`, `TermDepositRow`, `AccountPickerOption`, `ToSpendWarning`, `MonthRemainingWarningState`): wiersz dochodu, wydatku, wiersz z `raw_statement_description` i kontrahentem, duplikat (`import_duplicate: true`), kandydat na parę (`import_pair_candidate` + `pair_candidates` z jednym kandydatem), wiersz niepoprawny z `invalid_reason`, wiersz o kwocie `null` (waluta), wiersz uznaniowy do ruchu kapitału; gotowe `selections` (ruch kapitału z nakładką odsetek, transfer własny z wybraną parą, zaznaczony wydatek z kategorią); przykładowe `ToSpendWarning` i `MonthRemainingWarningState`; listy kategorii dochodu/wydatku, innych kont i lokat (także pusta lista lokat).

#### 3. Trasa i strona kitchen sink

**File**: `routes/web.php`, `resources/js/pages/dev/ImportPreviewSink.vue` (nowy)

**Intent**: Strona renderuje dzisiejszy nagłówek kolumn i prawdziwe `ImportPreviewRow` dla każdego wiersza z fixture w `ListSurface`, bez `setLayoutProps` i bez `Form`. Sekcje stanów dochodzą w kolejnych fazach.

**Contract**: `Route::inertia('dev/import-preview', 'dev/ImportPreviewSink')` w bloku `if (app()->environment('local'))` (`routes/web.php:42-45`); bez nazwy trasy. Każdy wiersz z własnym `v-model:selection` (lokalny `ref` z fixture).

#### 4. Test trasy

**File**: `tests/Feature/DevImportPreviewSinkTest.php` (nowy)

**Intent**: Kitchen sink nie jest serwowany poza `local`.

**Contract**: zalogowany użytkownik, `get('/dev/import-preview')` → 404; wzorzec `tests/Feature/DevOperationsSinkTest.php`.

#### 5. Zrzuty odniesienia

**File**: `context/changes/import-preview-ui-contract/screenshots/phase-1-*.png`

**Intent**: Punkt odniesienia przed zmianą: `/dev/import-preview` i `/operations/import/preview` przy 1280 i 390 px.

**Contract**: skrypt z „Critical Implementation Details”, niecommitowany.

### Success Criteria:

#### Automated Verification:

- Test trasy przechodzi na PostgreSQL: `tests/Feature/DevImportPreviewSinkTest.php` obrazem Sail (AGENTS.md „Test database”)
- Typy przechodzą: `npm run types:check`
- Statyczne kontrole przechodzą: `npm run check`

#### Manual Verification:

- `/dev/import-preview` w podglądzie z worktree renderuje wszystkie wiersze z fixture (w tym duplikat, parę, wiersz niepoprawny, ruch kapitału z odsetkami) przy 1280 i 390 px
- Zrzuty `phase-1-*` (4 pliki) leżą w `context/changes/import-preview-ui-contract/screenshots/`

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Błąd zatwierdzenia z wierszami niepoprawnymi (C6)

### Overview

Najpierw testem potwierdzić, że payload taki, jaki wysyła przeglądarka (wiersz niepoprawny bez `type`), daje dziś błąd `rows.N.type`. Potem poprawić walidację i odczyt zaznaczeń na serwerze i pokazać na stronie każdy błąd `rows` / `rows.N.*`.

### Changes Required:

#### 1. Testy (najpierw czerwone)

**File**: `tests/Feature/CsvImportTest.php`

**Intent**: Odtworzyć payload przeglądarki: podgląd z jednym wierszem niepoprawnym (np. data w przyszłości) i jednym poprawnym; POST z wierszem niepoprawnym jako `index` + `selected=0` bez `type` i wierszem poprawnym zaznaczonym z `type` i kategorią.

**Contract**: nowe metody:

- `test_user_can_import_when_preview_contains_unselected_invalid_row_without_type` — `assertRedirect(route('operations'))`, `assertDatabaseCount('financial_operations', 1)`. Na HEAD przed poprawką oczekiwany błąd sesji `rows.N.type` (zapisać w raporcie fazy jako potwierdzenie C6).
- `test_selected_row_without_type_fails_validation` — zaznaczony wiersz poprawny bez `type` → `assertSessionHasErrors('rows.N.type')`, 0 operacji.

#### 2. Walidacja i odczyt zaznaczeń

**File**: `app/Http/Requests/StoreCsvImportRequest.php`

**Intent**: `type` jest wymagany tylko dla zaznaczonych wierszy, a `rowSelections()` zwraca wyłącznie wiersze zaznaczone, więc niezaznaczony wiersz bez typu nie trafia do `match`.

**Contract**: `'rows.*.type' => ['required_if:rows.*.selected,true', 'nullable', Rule::in([...5 typów])]`; `rowSelections()` filtruje `selected === true` przed mapowaniem, PHPDoc zwracanej tablicy bez zmian. `CsvImportController::store` bez zmian (pętla dalej sprawdza `selected`).

#### 3. Widoczny błąd wierszy

**File**: `resources/js/lib/import-preview-errors.ts` (nowy) + `import-preview-errors.test.ts`, `resources/js/pages/OperationsImportPreview.vue`

**Intent**: Strona pokazuje błąd `rows` albo pierwszy błąd `rows.N.*`, zamiast tylko `errors.rows`.

**Contract**: `firstRowsError(errors: Record<string, string>): string | undefined` — najpierw `errors.rows`, potem pierwszy klucz pasujący do `/^rows\.\d+\./` w kolejności klucza; `undefined` gdy brak. Test: brak błędów; tylko `rows`; tylko `rows.3.type`; oba (wygrywa `rows`); klucz `duplicate_warning_acknowledged` ignorowany. Strona: `<InputError :message="firstRowsError(errors)" />` w miejscu `errors.rows`.

### Success Criteria:

#### Automated Verification:

- Nowy test C6 oblewa na HEAD z błędem `rows.N.type` przed poprawką (wynik zapisany w raporcie fazy)
- Testy importu przechodzą na PostgreSQL: `tests/Feature/CsvImportTest.php` obrazem Sail
- Testy TS przechodzą: `npm test`
- Typy i statyczne kontrole przechodzą: `npm run types:check`, `npm run check`
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- W podglądzie z worktree: wgranie CSV z wierszem z datą w przyszłości i wierszem poprawnym, zaznaczenie poprawnego z kategorią, „Zatwierdź import” → przekierowanie do Operacji i jedna nowa operacja

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Elementy kontraktu

### Overview

Dodać token `pair`, nazwane klasy listy importu, funkcje szablonu w `lib/` z testami, prop `ariaLabel` w selectach i klucze tłumaczeń. Widok jeszcze ich nie używa; pokazuje je kitchen sink.

### Changes Required:

#### 1. Token roli `pair`

**File**: `resources/css/app.css`

**Intent**: Tło „kandydat na parę” ma własny token w obu motywach zamiast klasy palety (C2, decyzja 1).

**Contract**: `--pair` w `:root` (startowo `hsl(199 89% 48%)`, odpowiednik dzisiejszego `sky-500`) i w `.dark` (jaśniejszy odcień tej samej barwy, czytelny jako tint `/10` na `--card` trybu ciemnego); `--color-pair: var(--pair)` w `@theme inline` obok `--color-transfer`. Token tylko do tintu tła wiersza, nie do tekstu.

#### 2. Klasy listy importu

**File**: `resources/css/app.css`

**Intent**: Nagłówek kolumn, wiersz i tinty statusu jako nazwane klasy obok `.import-preview-grid` (C1, C2).

**Contract**:

- `.import-preview-header-row` = `@apply column-caption border-border/60 hidden border-b pb-2.5`, `display: grid` w `@variant xl`;
- `.import-preview-row` = `@apply border-border/60 hover:bg-foreground/[0.03] border-b py-3 transition-colors last:border-b-0`;
- `.import-preview-row-duplicate` = `@apply bg-warning/10`; `.import-preview-row-pair` = `@apply bg-pair/10`.

Kolejność wg „Critical Implementation Details”. Wzorzec `.operations-header-row` / `.operations-row` (`app.css:500-519`).

#### 3. Notatki wiersza

**File**: `resources/js/lib/import-preview-rows.ts` + `import-preview-rows.test.ts` (nowy lub rozszerzony)

**Intent**: Jedno miejsce mówi, czy wiersz ma blok notatek; typ wymusza wpisanie każdego rodzaju notatki, więc nowy alert nie zniknie jak to-spend (C4).

**Contract**: `type ImportRowNoteKind = 'invalidReason' | 'pairReason' | 'negativeBalance' | 'toSpend' | 'categoryMonthRemaining' | 'poolMonthRemaining'`; `importRowHasNotes(flags: Record<ImportRowNoteKind, boolean>): boolean`. Test: wszystkie `false` → `false`; każdy rodzaj osobno `true` → `true` (w tym sam `toSpend`).

#### 4. Powód blokady zatwierdzenia

**File**: `resources/js/lib/import-submit-block.ts` (nowy) + `import-submit-block.test.ts`

**Intent**: Logika `canSubmit` przenosi się do `lib/` i zwraca jeden powód wg uzgodnionego priorytetu (C5).

**Contract**:

```ts
type ImportSubmitBlockReason =
    | { key: 'import_block_no_selection' }
    | { key: 'import_block_incomplete_rows'; count: number }
    | { key: 'import_block_duplicates_unacknowledged' };

function importSubmitBlockReason(
    rows: ImportRow[],
    selections: Record<number, RowSelection>,
    termDeposits: TermDepositRow[],
    duplicateAcknowledged: boolean,
): ImportSubmitBlockReason | null;
```

Priorytet: 0 zaznaczonych → `no_selection`; zaznaczone wiersze nieprzechodzące `rowSelectionValid` (albo bez odpowiadającego `row`) → `incomplete_rows` z ich liczbą; zaznaczony `import_duplicate` przy `duplicateAcknowledged === false` → `duplicates_unacknowledged`; inaczej `null`. Test: brak zaznaczeń; 2 zaznaczone, 1 bez kategorii → `count: 1`; niekompletny wiersz i niepotwierdzony duplikat naraz → `incomplete_rows`; kompletny duplikat niepotwierdzony → `duplicates_unacknowledged`; potwierdzony → `null`; zaznaczenie dla indeksu spoza `rows` → `incomplete_rows`. `processing` nie jest częścią funkcji.

#### 5. Nazwa dostępna w selectach Reka

**File**: `resources/js/components/CategorySelect.vue`, `resources/js/components/AccountSelect.vue`

**Intent**: Wrappery przyjmują nazwę dostępną dla wyzwalacza, gdy obok nie ma `<Label for>` (C5, decyzja 4).

**Contract**: opcjonalny prop `ariaLabel?: string` przekazany jako `:aria-label` do `SelectTrigger`. Bez zmian dla obecnych wywołań.

#### 6. Klucze tłumaczeń

**File**: `lang/pl/ui.php` (sekcja `operations`), `tests/Feature/CsvImportTest.php`

**Intent**: Teksty powodów blokady, nowej treści alertu duplikatów, nazw dostępnych i pustego stanu.

**Contract**:

- `import_block_no_selection` — „Zaznacz co najmniej jeden wiersz do importu.”
- `import_block_incomplete_rows` — „Uzupełnij klasyfikację w zaznaczonych wierszach (:count).”
- `import_block_duplicates_unacknowledged` — „Potwierdź import zaznaczonych duplikatów.”
- `import_duplicate_warning_found` — „Wiersze oznaczone jako duplikaty wcześniejszych importów: :count. Są odznaczone; zaznaczenie ich wymaga potwierdzenia.”
- `import_select_row` — „Zaznacz wiersz: :date, :description”
- `import_row_control_label` — „:column: :date, :description”
- `import_preview_empty_description` — „Plik nie zawiera wierszy z datą i kwotą. Wróć i wybierz inny plik.”

Asercja `assertInertia` na `translations.operations.import_block_no_selection` w teście podglądu.

#### 7. Kitchen sink: sekcja kontraktu

**File**: `resources/js/pages/dev/ImportPreviewSink.vue`

**Intent**: Sekcja „Kontrakt” pokazuje nagłówek i wiersze (zwykły, duplikat, para) na nowych klasach obok dzisiejszych, żeby porównać na zrzucie, także w trybie ciemnym.

**Contract**: osobna sekcja z nagłówkiem; statyczne wiersze na `import-preview-grid` z nowymi klasami.

### Success Criteria:

#### Automated Verification:

- Testy TS przechodzą: `npm test`
- Statyczne kontrole i typy przechodzą: `npm run check`, `npm run types:check`
- Build przechodzi: `npm run build`
- Testy importu przechodzą na PostgreSQL: `tests/Feature/CsvImportTest.php` obrazem Sail

#### Manual Verification:

- W `/dev/import-preview` nagłówek i wiersze na nowych klasach wyglądają jak dzisiejsze (1280 i 390 px); nagłówek kolumn ukryty poniżej `xl`
- Tint pary w trybie jasnym odpowiada dzisiejszemu `sky-500/10`; w trybie ciemnym jest widoczny i odróżnia się od tintu duplikatu
- Zrzuty `phase-3-*` zapisane

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Widok (C1–C5)

### Overview

Przenieść widok na klasy i funkcje z fazy 3, zamienić pole odsetek na `<Input>`, nadać nazwy kontrolkom, naprawić notatki wiersza, uporządkować alerty strony i wydzielić pasek zatwierdzenia z widocznym powodem blokady.

### Changes Required:

#### 1. Nagłówek, wiersz i podpisy (C1, C2)

**File**: `resources/js/pages/OperationsImportPreview.vue`, `resources/js/components/ImportPreviewRow.vue`

**Intent**: Nagłówek i wiersz używają nazwanych klas zamiast ciągów z literałami; podpisy mobilne zostają dla czytnika na `xl`.

**Contract**: nagłówek `import-preview-grid import-preview-header-row`; kontener wiersza `import-preview-row` z `import-preview-row-duplicate` (gdy `row.valid && row.import_duplicate`) albo `import-preview-row-pair` (gdy `row.valid && showsPairBadge && !row.import_duplicate`); 6 podpisów `column-caption xl:sr-only`. Brak `text-[11px]`, `/[0.03]`, `sky-` w obu plikach.

#### 2. Pole odsetek (C3)

**File**: `resources/js/components/ImportPreviewRow.vue`

**Intent**: „Kwota odsetek” wygląda i zachowuje się jak każde pole w dialogach.

**Contract**: `<Input>` z `@/components/ui/input` z tym samym `id`, `v-model`, `inputmode="decimal"`, `placeholder`; `<Label for>` bez zmian. Wzorzec `ExpenseFormDialog.vue:594-601`.

#### 3. Kolor kwoty (C5)

**File**: `resources/js/components/ImportPreviewRow.vue`, `resources/js/lib/import-preview-rows.ts`

**Intent**: Kwota ma kolor ze znaku z wyciągu przez wspólny helper; zero i brak kwoty bez koloru (decyzja 2).

**Contract**: `amountToneClass(row.amount_minor_units ?? 0)`; `rowAmountClass` usunięty z `lib/import-preview-rows.ts` razem z importem.

#### 4. Nazwy dostępne (C5)

**File**: `resources/js/components/ImportPreviewRow.vue`

**Intent**: Czytnik ogłasza każdą kontrolkę wiersza z nazwą kolumny i kontekstem wiersza.

**Contract**: kontekst wiersza = sformatowana data (albo „—”) i `row.description ?? '—'`. Checkbox: `:aria-label` z `import_select_row`. Select typu, select pary, select lokaty: `:aria-label` z `import_row_control_label` (`:column` = `t.operations.type` / `t.operations.import_classification`). `AccountSelect` i `CategorySelect` (klasyfikacja i kategoria odsetek): prop `aria-label` tym samym kluczem. Pole odsetek zostaje przy `<Label for>`.

#### 5. Notatki wiersza (C4)

**File**: `resources/js/components/ImportPreviewRow.vue`

**Intent**: Blok notatek pokazuje się także dla samego ostrzeżenia to-spend.

**Contract**: `hasNotes` = `importRowHasNotes({...})` z wszystkimi sześcioma rodzajami, `toSpend: toSpendWarning != null`. Szablon alertów bez zmian.

#### 6. Alerty strony i notka w karcie (C4)

**File**: `resources/js/pages/OperationsImportPreview.vue`

**Intent**: Ostrzeżenie o duplikatach pojawia się tylko przy duplikatach; wiedza o granicach wykrywania jest zawsze dostępna, ale nie udaje wyniku.

**Contract**: alert duplikatów `v-if="duplicateRowCount > 0"`, `variant="warning"`, tytuł `import_duplicate_warning_title`, treść `import_duplicate_warning_found` z `:count`. W `CardContent` karty podsumowania pod badge'ami: `<p class="text-muted-foreground text-xs">` z `import_duplicate_warning_body` (zawsze). Alert par `variant="warning"`.

#### 7. Pasek zatwierdzenia z powodem (C5)

**File**: `resources/js/components/ImportPreviewSubmitBar.vue` (nowy), `resources/js/pages/OperationsImportPreview.vue`

**Intent**: Wyłączony przycisk ma widoczny powód powiązany z `aria-describedby`; strona liczy powód funkcją z fazy 3.

**Contract**: props `reason: string | null`, `processing: boolean`; `Button type="submit"` z `:disabled="processing || reason !== null"`, `:aria-describedby` na `<p :id class="text-muted-foreground text-xs">` tylko gdy `reason !== null`; `id` z `useId()`. Wzorzec `OperationsPageActions.vue`. Strona: `blockReason = importSubmitBlockReason(rows, selections, termDeposits, duplicateWarningAcknowledged)`, tekst z `t.operations[blockReason.key]` (z `:count` dla `incomplete_rows`); `canSubmit` usunięty.

#### 8. Kitchen sink

**File**: `resources/js/pages/dev/ImportPreviewSink.vue`

**Intent**: Sink pokazuje prawdziwe wiersze po zmianie oraz `ImportPreviewSubmitBar` dla każdego powodu i bez powodu; sekcja „Kontrakt” z fazy 3 zostaje tylko z nowymi klasami.

**Contract**: wiersze z fixture z ostrzeżeniem to-spend, ujemnym saldem i month-remaining; pasek dla `null` i trzech powodów.

### Success Criteria:

#### Automated Verification:

- Testy importu przechodzą na PostgreSQL: `tests/Feature/CsvImportTest.php tests/Feature/AccountPickerTest.php` obrazem Sail
- Testy TS, kontrole i typy przechodzą: `npm test`, `npm run check`, `npm run types:check`
- Brak literałów w plikach widoku: `node scripts/check-ui-literals.mjs resources/js/pages/OperationsImportPreview.vue resources/js/components/ImportPreviewRow.vue resources/js/components/ImportPreviewSubmitBar.vue resources/js/components/CategorySelect.vue resources/js/components/AccountSelect.vue`
- `rowAmountClass` i `native-input` zniknęły: `grep -rn "rowAmountClass\|native-input" resources/js` nic nie zwraca

#### Manual Verification:

- `/operations/import/preview` po wgraniu CSV: przed zaznaczeniem pod przyciskiem widać „Zaznacz co najmniej jeden wiersz do importu.”; po zaznaczeniu wydatku bez kategorii — „Uzupełnij klasyfikację w zaznaczonych wierszach (1).”; po wyborze kategorii przycisk aktywny, powód znika
- Przy imporcie bez duplikatów nie ma alertu „Możliwe duplikaty”, a notka o granicach wykrywania jest w karcie podsumowania
- Wiersz z wydatkiem w miesiącu z planem, który zrobi ujemne „Do wydania”, pokazuje bursztynowy alert z kwotą i miesiącem
- Inspektor dostępności: checkbox, select typu, `CategorySelect` / `AccountSelect` mają nazwy z kolumną i datą/opisem wiersza; przycisk ma opis z powodem
- Zrzuty `phase-4-*` zapisane

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie przed fazą 5.

---

## Faza 5: Stany z macierzy

### Overview

Domknąć stany empty i loading w widoku, sprawdzić focus-visible i disabled na każdej kontrolce i wypełnić macierz 7 stanów w kitchen sinku.

### Changes Required:

#### 1. Pusty podgląd

**File**: `resources/js/pages/OperationsImportPreview.vue`, `tests/Feature/CsvImportTest.php`

**Intent**: Podgląd bez wierszy to prawdziwy stan pusty z drogą powrotu, a nie goły akapit pod nagłówkiem kolumn i stałym alertem.

**Contract**: przy `rows.length === 0` strona renderuje wiersz tytułu i `ListSurface` z `IndexEmptyState` (tytuł `import_preview_empty`, opis `import_preview_empty_description`, akcja `Button variant="outline" as-child` z `Link` do `CsvImportController.create.url()` i ikoną 16 px, tekst `import_back`); karta podsumowania, alerty, nagłówek kolumn i `Form` się nie renderują (`rows` ma `min:1`). Nowy test: wgranie pliku z samym nagłówkiem → podgląd `has('rows', 0)` bez błędu.

#### 2. Spinner przy zatwierdzaniu

**File**: `resources/js/components/ImportPreviewSubmitBar.vue`

**Intent**: Czas zapisu jest widoczny i ogłaszany.

**Contract**: `<Spinner v-if="processing" :aria-label="t.common.loading" />` w przycisku; wzorzec `MonthPlanEditBar.vue:63-74`.

#### 3. Focus-visible i disabled

**File**: `resources/js/components/ImportPreviewRow.vue` (tylko jeśli kontrola ujawni brak)

**Intent**: Każda kontrolka wiersza (checkbox, `.native-select`, `SelectTrigger`, `Input`) i przycisk mają pierścień 3 px `ring/50` z komponentów bazowych; wyłączony checkbox wiersza niepoprawnego i wyłączony select lokaty bez kandydatów wyglądają na wyłączone i pozostają czytelne.

**Contract**: bez nowych literałów; brak — poprawka przez klasę w `app.css`, nie w widoku.

#### 4. Kitchen sink: macierz 7 stanów

**File**: `resources/js/pages/dev/ImportPreviewSink.vue`

**Intent**: Każda komórka macierzy ma przykład albo „N/A” z powodem.

**Contract**: sekcje:

- **default** — wiersze z fixture (dochód, wydatek, para, duplikat, ruch kapitału z odsetkami, transfer własny);
- **hover** — wiersze (rytm, nie afordancja; nota przy sekcji);
- **focus-visible** — checkbox, selecty natywne, `CategorySelect` / `AccountSelect`, `Input`, przycisk (nota „przejdź Tabem”);
- **disabled** — wiersz niepoprawny (checkbox wyłączony, powód), select lokaty bez kandydatów, pasek z każdym z trzech powodów;
- **error** — `InputError` z przykładowym błędem `rows`, `rows.N.type` (przez `firstRowsError`) i `duplicate_warning_acknowledged`; wiersz niepoprawny z `invalid_reason`;
- **empty** — `IndexEmptyState` jak w widoku;
- **loading** — pasek z `processing: true` (spinner).

### Success Criteria:

#### Automated Verification:

- Testy importu przechodzą na PostgreSQL: `tests/Feature/CsvImportTest.php tests/Feature/DevImportPreviewSinkTest.php` obrazem Sail
- Testy TS, kontrole, typy i build przechodzą: `npm test`, `npm run check`, `npm run types:check`, `npm run build`
- Brak literałów w zmienionych plikach: `node scripts/check-ui-literals.mjs resources/js/pages/OperationsImportPreview.vue resources/js/components/ImportPreviewRow.vue resources/js/components/ImportPreviewSubmitBar.vue resources/js/pages/dev/ImportPreviewSink.vue`

#### Manual Verification:

- Wgranie CSV z samym nagłówkiem: pusty stan z przyciskiem powrotu, bez karty podsumowania, alertów i przycisku „Zatwierdź import”
- Zatwierdzenie importu przy dławieniu sieci (DevTools „Slow 4G”): spinner w przycisku, w DOM `aria-label="Ładowanie"`
- Tab po wierszu w `/dev/import-preview` i w widoku: każda kontrolka ma pierścień 3 px; wyłączony checkbox jest pomijany
- `/dev/import-preview` pokazuje wszystkie 7 sekcji macierzy w obu motywach
- Zrzuty `phase-5-*` zapisane

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie przed fazą 6.

---

## Faza 6: Utrwalenie

### Overview

Dopisać oczyszczone pliki do strażnika literałów, zostawić regułę i dokumentację dla następnego agenta, nadać zarzutom statusy, przejść pełną bramkę CI.

### Changes Required:

#### 1. Strażnik literałów

**File**: `scripts/check-ui-literals.mjs`

**Intent**: `npm run check:ui` pilnuje oczyszczonego widoku importu.

**Contract**: do `FILES`: `resources/js/pages/OperationsImportPreview.vue`, `resources/js/components/ImportPreviewRow.vue`, `resources/js/components/ImportPreviewSubmitBar.vue`, `resources/js/components/CategorySelect.vue`, `resources/js/components/AccountSelect.vue`, `resources/js/pages/dev/ImportPreviewSink.vue`.

#### 2. Reguła dla agentów

**File**: `.ai/rules/js.md` (przez Boost `record-rule`, jeśli dostępny; inaczej edycja pliku — AGENTS.md „When Boost tools are not available”)

**Intent**: Następny agent wie, z jakich klas i tokenów składa się lista importu i jak nie zgubić alertu wiersza.

**Contract**: nowa sekcja „Import preview”: klasy `.import-preview-grid`, `.import-preview-header-row` (ukryty poniżej `xl`), `.import-preview-row` z modyfikatorami `-duplicate` / `-pair`; token `pair` tylko do tintu wiersza kandydata na parę; nowy alert w wierszu wymaga nowego rodzaju w `importRowHasNotes`; `CategorySelect` / `AccountSelect` bez `<Label for>` dostają `ariaLabel`; kitchen sink `/dev/import-preview` z `resources/js/lib/import-preview-fixtures.ts`. Dopisać `/dev/import-preview` do listy sandboxów w sekcji „Tokens and shared components come first”.

#### 3. Dokumentacja systemu

**File**: `.docs/design-system.md`

**Intent**: Dokument zna nowy token i klasy importu.

**Contract**: w tabeli kolorów wiersz `pair` — znaczenie: „tło wiersza podglądu importu: kandydat na parę transferu własnego (tint /10)”, nie używaj do: tekstu, kwot, typu operacji (to `transfer`). „Powierzchnie i rytm” — klasy listy importu. „Kitchen sink i zrzuty” — dopisany `/dev/import-preview`.

#### 4. Research: statusy zarzutów

**File**: `context/changes/import-preview-ui-contract/research.md`

**Intent**: Każdy zarzut i deferred ma status po tej zmianie.

**Contract**: C1–C6 → fixed (z fazą); D1–D4 bez zmian (deferred). Odnotować, że zmiana zamyka odroczenia Operacje D9 (część importu), Dashboard (`column-caption`, `amountToneClass`) i Plan miesiąca D1.

### Success Criteria:

#### Automated Verification:

- Strażnik przechodzi na pełnej liście: `npm run check:ui`
- Pełna bramka CI na PostgreSQL: `composer ci:check` obrazem Sail (AGENTS.md „Test database”)
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- Dialogi z `CategorySelect` / `AccountSelect` (dodaj wydatek, dodaj transfer) bez widocznej zmiany i z nazwą z `<Label for>`
- Zrzuty `phase-6-*` zapisane; porównanie z `phase-1-*` pokazuje tylko zamierzone różnice

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymać się na ręczne potwierdzenie; przy scalaniu sprawdzić `app.css` i `lang/pl/ui.php` względem równoległych gałęzi.

---

## Testing Strategy

### Unit Tests:

- `import-preview-errors.test.ts` — `rows` przed `rows.N.*`, brak błędów, klucze spoza `rows` ignorowane.
- `import-preview-rows.test.ts` — `importRowHasNotes`: każdy rodzaj osobno, w tym sam `toSpend`.
- `import-submit-block.test.ts` — priorytet powodów, liczba niekompletnych wierszy, duplikat potwierdzony i niepotwierdzony, indeks spoza `rows`.

### Integration Tests:

- `CsvImportTest.php` — C6: niezaznaczony wiersz niepoprawny bez `type` nie blokuje importu; zaznaczony wiersz bez `type` daje `rows.N.type`; pusty podgląd `has('rows', 0)`; nowe klucze tłumaczeń w `translations.operations`.
- `DevImportPreviewSinkTest.php` — 404 poza `local`.

### Manual Testing Steps:

1. Podgląd z worktree, `/operations/import` jako `test@example.com`: wgranie CSV z wierszem dochodu, wydatku i datą w przyszłości; przejście powodów blokady aż do aktywnego przycisku; import.
2. Wiersz, który zrobi ujemne „Do wydania” w miesiącu z planem: bursztynowy alert w wierszu.
3. Plik z samym nagłówkiem: pusty stan z powrotem.
4. Inspektor dostępności i Tab po wierszu; dławienie sieci przy zatwierdzaniu.
5. `/dev/import-preview` w obu motywach — porównanie ze zrzutami bazowymi.

## Performance Considerations

`importSubmitBlockReason` przechodzi po zaznaczeniach jak dzisiejsze `canSubmit` (z `rows.find` na wiersz); pliki importu mają rzędu setek wierszy — pomijalne. `rowSelections()` filtruje zamiast mapować wszystkie wiersze — mniej pracy.

## Migration Notes

Brak migracji danych. Prop `ariaLabel` jest opcjonalny, więc dialogi z `CategorySelect` / `AccountSelect` działają bez zmian. Przy scalaniu z gałęziami, które dotykają `app.css` (sekcja `@theme inline` i `:root` / `.dark`) albo `lang/pl/ui.php` (`operations`), rozwiązać konflikt na rzecz obu zestawów kluczy.

## References

- Research: `context/changes/import-preview-ui-contract/research.md`
- Wzorzec zmiany: `context/archive/2026-11-03-operations-ui-contract/plan.md`
- Klasy listy: `resources/css/app.css:500-519` (`.operations-header-row`, `.operations-row`), `:567-631` (`.import-preview-grid`)
- Powód blokady: `resources/js/components/OperationsPageActions.vue`; spinner: `resources/js/components/MonthPlanEditBar.vue:63-74`
- Kitchen sink i test 404: `routes/web.php:42-45`, `tests/Feature/DevOperationsSinkTest.php`
- Walidacja importu: `app/Http/Requests/StoreCsvImportRequest.php:21-124`, `app/Http/Controllers/CsvImportController.php:150-180`
- Źródło ostrzeżeń to-spend: `context/archive/2026-11-02-plan-based-entry-warnings/`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Środowisko, kitchen sink i punkt odniesienia

#### Automated

- [x] 1.1 Test trasy przechodzi na PostgreSQL: `tests/Feature/DevImportPreviewSinkTest.php` obrazem Sail (AGENTS.md „Test database”) — 4e07353
- [x] 1.2 Typy przechodzą: `npm run types:check` — 4e07353
- [x] 1.3 Statyczne kontrole przechodzą: `npm run check` — 4e07353

#### Manual

- [x] 1.4 `/dev/import-preview` w podglądzie z worktree renderuje wszystkie wiersze z fixture (w tym duplikat, parę, wiersz niepoprawny, ruch kapitału z odsetkami) przy 1280 i 390 px — 4e07353
- [x] 1.5 Zrzuty `phase-1-*` (4 pliki) leżą w `context/changes/import-preview-ui-contract/screenshots/` — 4e07353

### Phase 2: Błąd zatwierdzenia z wierszami niepoprawnymi (C6)

#### Automated

- [x] 2.1 Nowy test C6 oblewa na HEAD z błędem `rows.N.type` przed poprawką (wynik zapisany w raporcie fazy) — 298c432
- [x] 2.2 Testy importu przechodzą na PostgreSQL: `tests/Feature/CsvImportTest.php` obrazem Sail — 298c432
- [x] 2.3 Testy TS przechodzą: `npm test` — 298c432
- [x] 2.4 Typy i statyczne kontrole przechodzą: `npm run types:check`, `npm run check` — 298c432
- [x] 2.5 Formatowanie PHP: `vendor/bin/pint --dirty --format agent` — 298c432

#### Manual

- [x] 2.6 W podglądzie z worktree: wgranie CSV z wierszem z datą w przyszłości i wierszem poprawnym, zaznaczenie poprawnego z kategorią, „Zatwierdź import” → przekierowanie do Operacji i jedna nowa operacja — 298c432

### Phase 3: Elementy kontraktu

#### Automated

- [x] 3.1 Testy TS przechodzą: `npm test` — 49ac516
- [x] 3.2 Statyczne kontrole i typy przechodzą: `npm run check`, `npm run types:check` — 49ac516
- [x] 3.3 Build przechodzi: `npm run build` — 49ac516
- [x] 3.4 Testy importu przechodzą na PostgreSQL: `tests/Feature/CsvImportTest.php` obrazem Sail — 49ac516

#### Manual

- [x] 3.5 W `/dev/import-preview` nagłówek i wiersze na nowych klasach wyglądają jak dzisiejsze (1280 i 390 px); nagłówek kolumn ukryty poniżej `xl` — 49ac516
- [x] 3.6 Tint pary w trybie jasnym odpowiada dzisiejszemu `sky-500/10`; w trybie ciemnym jest widoczny i odróżnia się od tintu duplikatu — 49ac516
- [x] 3.7 Zrzuty `phase-3-*` zapisane — 49ac516

### Phase 4: Widok (C1–C5)

#### Automated

- [x] 4.1 Testy importu przechodzą na PostgreSQL: `tests/Feature/CsvImportTest.php tests/Feature/AccountPickerTest.php` obrazem Sail
- [x] 4.2 Testy TS, kontrole i typy przechodzą: `npm test`, `npm run check`, `npm run types:check`
- [x] 4.3 Brak literałów w plikach widoku: `node scripts/check-ui-literals.mjs resources/js/pages/OperationsImportPreview.vue resources/js/components/ImportPreviewRow.vue resources/js/components/ImportPreviewSubmitBar.vue resources/js/components/CategorySelect.vue resources/js/components/AccountSelect.vue`
- [x] 4.4 `rowAmountClass` i `native-input` zniknęły: `grep -rn "rowAmountClass\|native-input" resources/js` nic nie zwraca

#### Manual

- [x] 4.5 `/operations/import/preview` po wgraniu CSV: przed zaznaczeniem pod przyciskiem widać „Zaznacz co najmniej jeden wiersz do importu.”; po zaznaczeniu wydatku bez kategorii — „Uzupełnij klasyfikację w zaznaczonych wierszach (1).”; po wyborze kategorii przycisk aktywny, powód znika
- [x] 4.6 Przy imporcie bez duplikatów nie ma alertu „Możliwe duplikaty”, a notka o granicach wykrywania jest w karcie podsumowania
- [x] 4.7 Wiersz z wydatkiem w miesiącu z planem, który zrobi ujemne „Do wydania”, pokazuje bursztynowy alert z kwotą i miesiącem
- [x] 4.8 Inspektor dostępności: checkbox, select typu, `CategorySelect` / `AccountSelect` mają nazwy z kolumną i datą/opisem wiersza; przycisk ma opis z powodem
- [x] 4.9 Zrzuty `phase-4-*` zapisane

### Phase 5: Stany z macierzy

#### Automated

- [ ] 5.1 Testy importu przechodzą na PostgreSQL: `tests/Feature/CsvImportTest.php tests/Feature/DevImportPreviewSinkTest.php` obrazem Sail
- [ ] 5.2 Testy TS, kontrole, typy i build przechodzą: `npm test`, `npm run check`, `npm run types:check`, `npm run build`
- [ ] 5.3 Brak literałów w zmienionych plikach: `node scripts/check-ui-literals.mjs resources/js/pages/OperationsImportPreview.vue resources/js/components/ImportPreviewRow.vue resources/js/components/ImportPreviewSubmitBar.vue resources/js/pages/dev/ImportPreviewSink.vue`

#### Manual

- [ ] 5.4 Wgranie CSV z samym nagłówkiem: pusty stan z przyciskiem powrotu, bez karty podsumowania, alertów i przycisku „Zatwierdź import”
- [ ] 5.5 Zatwierdzenie importu przy dławieniu sieci (DevTools „Slow 4G”): spinner w przycisku, w DOM `aria-label="Ładowanie"`
- [ ] 5.6 Tab po wierszu w `/dev/import-preview` i w widoku: każda kontrolka ma pierścień 3 px; wyłączony checkbox jest pomijany
- [ ] 5.7 `/dev/import-preview` pokazuje wszystkie 7 sekcji macierzy w obu motywach
- [ ] 5.8 Zrzuty `phase-5-*` zapisane

### Phase 6: Utrwalenie

#### Automated

- [ ] 6.1 Strażnik przechodzi na pełnej liście: `npm run check:ui`
- [ ] 6.2 Pełna bramka CI na PostgreSQL: `composer ci:check` obrazem Sail (AGENTS.md „Test database”)
- [ ] 6.3 Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual

- [ ] 6.4 Dialogi z `CategorySelect` / `AccountSelect` (dodaj wydatek, dodaj transfer) bez widocznej zmiany i z nazwą z `<Label for>`
- [ ] 6.5 Zrzuty `phase-6-*` zapisane; porównanie z `phase-1-*` pokazuje tylko zamierzone różnice
