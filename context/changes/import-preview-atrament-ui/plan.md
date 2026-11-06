# Podgląd importu, krok 2 w Atramencie — Implementation Plan

## Overview

Krok 2 importu (`resources/js/pages/OperationsImportPreview.vue` z `ImportPreviewRow.vue` i `ImportPreviewSubmitBar.vue`) przechodzi na kartę `PageImportPreview` design systemu „C · Atrament i morski”. Decyzje zapisuje nowy ADR-0052. Wcześniej, osobnym commitem `fix` zaczętym od czerwonych testów, powody pary i odrzucenia wiersza przestają przychodzić z serwera po angielsku. Serwer wysyła kody i dane, a polskie zdanie z datą `dd.mm.rrrr` składa UI. Na koniec widok dostaje strażnika typografii i sink, który montuje prawdziwą stronę.

## Current State Analysis

Źródło: `context/changes/import-preview-atrament-ui/research.md` (zarzuty C1–C6 potwierdzone).

- **C1. Angielskie powody.**
  - Powód odrzucenia: `CsvImportPreviewValidator.php:24-48` ma 7 zdań `__('English key')`.
  - Powód pary: `ImportPairCandidateDetector.php:191`, `:230` składa zdanie z datą ISO.
  - Repo nie ma `lang/pl.json`, więc użytkownik czyta angielski tekst w `ImportPreviewRow.vue:605-614`.
  - Oba teksty trafiają do sesji przy POST podglądu, a do Inertii wychodzą tylko przez `showPreview` (`CsvImportController.php:139`). `store` ich nie czyta.
  - Asercje `CsvImportTest.php:311`, `:349` porównują z `__()` tego samego klucza, więc są tautologiczne. Testy par nie sprawdzają `reason`.
- **C2. Tytuł i panel poza Atramentem.**
  - Tytuł: `OperationsImportPreview.vue:219` `text-lg font-semibold`, `:222` `text-sm`.
  - Przycisk wyjścia nie ma `ArrowLeft` (`:226-230`).
  - Panel podsumowania to `Card` z `CardTitle class="text-base"` (`:250-301`), a zastrzeżenie jest w `text-xs` (`:297`).
- **C3. Odznaki i kwoty.**
  - Odznaki używają `secondary` i pełnego `destructive` (`OperationsImportPreview.vue:265`, `:273`, `ImportPreviewRow.vue:590`, `:596`).
  - Kwotę koloruje `amountToneClass` (`ImportPreviewRow.vue:361-375`), czego zabrania `design-system.md:68` i ADR-0038.
  - Helpery Operacji (`format.ts:182-207`) przyjmują tylko `income` / `expense`, a wiersz importu ma 5 typów (`import-pair-selection.ts:14`).
- **C4. Ręczne bloki.**
  - Potwierdzenie duplikatów (`OperationsImportPreview.vue:387-402`) to `flex gap-2` + `Label`, a `InputError` nie jest powiązany z checkboksem.
  - Data jest w `text-sm font-medium` (`ImportPreviewRow.vue:344`).
  - Podpisy są w `text-xs` (`:499`, `:505`, `:570`, `:606`, `:612`, `ImportPreviewSubmitBar.vue:29`).
  - Checkbox wiersza ma nadpisanie `size-6 rounded-md` (`:323`).
  - `.import-preview-row` ma `border-border/60` (`app.css:1082-1084`).
- **C5. Brak strażnika i niepełny sink.**
  - Nie ma `ImportPreviewHardcodedUiTest`.
  - Sink `/dev/import-preview` (`routes/web.php:47-51`, `Route::inertia` wewnątrz `if local`) składa wiersze ręcznie i nie montuje strony.
  - `DevImportPreviewSinkTest.php` ma jeden przypadek.
  - Fikstury mają 4 kolory hex (`import-preview-fixtures.ts:114-141`) i nie są na liście `check:ui`.
- **C6. Tytuł i cel wyjścia.**
  - h1, `<Head>` i okruszek pokazują „Pokaż podgląd” (`import_preview`), czyli etykietę przycisku z kroku 1.
  - „Wróć do operacji” prowadzi do kroku 1 (`:227`, `:240`), nie do Operacji.

## Desired End State

- Pod wierszem niepoprawnym użytkownik czyta polski powód, np. „Data operacji nie może być w przyszłości.”.
- Pod kandydatem do pary czyta np. „Konto oszczędnościowe (02.11.2026): istniejący dochód lub wydatek stanie się tym transferem własnym.”.
- Serwer wysyła `invalid_reason` jako kod (`future_date`, …), a kandydat do pary nie ma pola `reason`.
- Wiersz tytułu wygląda jak w kroku 1:
  - `h1.page-title` „Import operacji”;
  - `p.meta` z opisem kroku 2;
  - „Wróć do operacji” jako `outline` z `ArrowLeft`, prowadzi do Operacji.
- Okruszek kończy się na „Podgląd importu”. Stan pusty ma „Wybierz inny plik”, który prowadzi do kroku 1.
- Panel podsumowania to `ListSurface` z nagłówkiem `h2.section-title` + `p.meta`. Pigułki: „Poprawne” `neutral`, „Niepoprawne” `destructive-soft`, duplikaty i pary `outline`. Zastrzeżenie jest w `meta`.
- Kwota w wierszu ma znak i kolor wybranego typu (ADR-0038) i jednostkę „zł”:
  - dochód i zwrot: „+” w `success`;
  - wydatek: „−” w `foreground`;
  - transfer własny i ruch kapitałowy: znak z pliku, bez koloru.

  Zmiana typu zmienia kwotę na żywo.
- Potwierdzenie duplikatów to `.form-check-row`. Jego błąd jest powiązany z checkboksem przez `aria-describedby` / `aria-invalid`.
- Checkbox wiersza ma 24 px przez nazwaną klasę z ADR-0052.
- `tests/Unit/ImportPreviewHardcodedUiTest.php` pilnuje plików widoku, sinka i fikstur. Sink `/dev/import-preview` montuje prawdziwą stronę w macierzy 7 stanów.
- Zrzuty 1280 i 390 px w obu motywach leżą w `screenshots/`. `composer ci:check` przechodzi na PostgreSQL.

### Key Discoveries:

- Kandydat do pary niesie już `account_name`, `date` (ISO), `kind` (`ImportPairCandidateDetector.php:183-195`, `:222-234`), więc zdanie da się złożyć na kliencie. Precedens: ADR-0043, `DashboardNotices.vue:159-177`.
- `UiTranslations.operations` to `Record<string, string>` (`resources/js/types/ui.ts:45`). Nowe klucze nie zmieniają typów TS, ale `UiTranslationKeysTest` nie złapie kluczy budowanych dynamicznie z kodu.
- `formatFinancialOperationAmount(type, amount)` daje „+” dla `income` albo ujemnej kwoty (`format.ts:182-193`). Zwrot mapuje się więc na `('expense', −abs)`, tak jak serwer zapisuje `-abs()` (`ImportFinancialOperations.php:249-253`).
- Kanoniczne wzorce:
  - wiersz tytułu: `OperationsImport.vue:193-208`;
  - checkbox z błędem: `ImportColumnMapping.vue:48-71`;
  - panel z nagłówkiem: `MonthSummaryCard.vue:88-97` (prop `title` w `ListSurface` renderuje `CardTitle text-base`, więc go nie używamy);
  - sink montujący stronę: `ImportStartKitchenSink.vue:15-17`, `:63-96`;
  - kontroler sinka: `Dev/ImportStartKitchenSinkController.php`;
  - strażnik: `ImportStartHardcodedUiTest.php`.
- Trasa sinka zarejestrowana przez `if (app()->environment('local'))` nie istnieje w testach, więc dziś nie da się sprawdzić jej renderu.
- Wszystkie miejsca łamiące planowane zakazy strażnika są w plikach tej zmiany (lista w C2–C4). Strażnik da się więc zazielenić bez ruszania innych widoków.

## What We're NOT Doing

- **Pozostałe angielskie teksty serwera** (D1). Chodzi o błędy `rows.*` z `CsvImportController` i walidatorów importu, toast po zatwierdzeniu, domyślne komunikaty Laravela i mechanizm `lang/pl.json` / `lang/pl/validation.php`. To osobna decyzja dla 166 wywołań `__()`.
- **Wspólna klasa wiersza listy** (D2) i **wspólny helper strażników** (F6). Strażnik kroku 2 to piąta kopia skanera, a F6 zostaje otwarty.
- Kontrakt `store` (`StoreCsvImportRequest`, `normalizePairCandidates`), dane w bazie i logika wyboru wierszy, par, duplikatów i ostrzeżeń.
- Nowe tokeny kolorów. `--over` z makiety to `destructive-soft`. Bez zmian zostają też `components/ui/*` (`Badge`, `Checkbox`).
- Ostrzeżenia strony i wiersza (`Alert variant="warning"`). Są zgodne z `js.md`.
- Nowy test E2E. Zachowanie sprawdzają Pest i Vitest, wygląd zrzuty.
- Zmiana URL sinka. Zostaje `/dev/import-preview`, bo na ten adres wskazują reguły i `design-system.md`.

## Implementation Approach

Kolejność jak w zmianie kroku 1:
1. Decyzje w dokumentach.
2. Poprawka danych test-first w jednym commicie `fix`, bo zmienia kształt propa po obu stronach (`.ai/rules/inertia-props.md`).
3. Widok, zaczęty od czerwonego strażnika.
4. Sink, zrzuty i pełna bramka.

Logika z gałęziami (tekst powodów, kwota według typu) trafia do czystych funkcji w `resources/js/lib/` z testem Vitest obok. Widok tylko je wywołuje.

## Critical Implementation Details

- **Fallback starej sesji.** Pole zostaje pod nazwą `invalid_reason`. Podgląd zapisany w sesji przed wdrożeniem niesie tam angielskie zdanie, a funkcja tekstu pokazuje surową wartość, gdy nie zna kodu. Dowód nie ginie, a nowy podgląd daje polski tekst. Zmiana nazwy pola wyzerowałaby powód w starej sesji.
- **Dynamiczne klucze.** Klucz `import_invalid_reason_<code>` jest składany z kodu, więc tylko test PHP iterujący po przypadkach enuma pilnuje, że każdy kod ma tłumaczenie. Dodanie kodu bez klucza ma być czerwone.

## Faza 1: Decyzje i dokumenty

### Overview

Zapisać decyzje P1–P6 z planowania jako ADR-0052 i zaktualizować dokumenty Atramentu, zanim zmieni się kod.

### Changes Required:

#### 1. ADR-0052

**File**: `context/foundation/adr/0052-import-preview-follows-page-import-preview.md`

**Intent**: Krok 2 importu przyjmuje kartę `PageImportPreview` jako wzorzec (stan z 2026-11-06). ADR zapisuje odstępstwa od karty i od wcześniejszych ADR, w stylu ADR-0048 (proza, sekcje bez nagłówków, akapit odrzuconych alternatyw).

**Contract**: Akapity:
- **Wiersz tytułu.** Jak w kroku 1 (ADR-0048):
  - `<Head>` i h1 „Import operacji” (`import_title`), `meta` „Krok 2: …”;
  - „Wróć do operacji” jako `outline` z `ArrowLeft`, prowadzi do Operacji;
  - ostatni element okruszka to „Podgląd importu”;
  - stan pusty ma akcję „Wybierz inny plik” do kroku 1.

  Odstępstwo od h1 karty („Pokaż podgląd” to etykieta przycisku z kroku 1, nie nazwa strony).
- **Panel podsumowania.** `ListSurface` ze slotem `#header` (`h2.section-title` + `p.meta`), nie `.form-panel`, bo to nie formularz, i nie `Card` z `CardTitle`. Zastrzeżenie o duplikatach jest w `meta`.
- **Pigułki.** „Poprawne / Poprawny” → `neutral`; „Niepoprawne / Niepoprawny” → `destructive-soft`; duplikat i para → `outline`. `destructive-soft` dostaje drugie znaczenie: oprócz przekroczenia oznacza wiersz importu, którego nie da się zaimportować. Pełne `destructive` znika z odznak.
- **Kwota.** Znak i kolor według wybranego typu (ADR-0038):
  - dochód i zwrot `+` `success`, wydatek `−` `foreground`;
  - transfer własny i ruch kapitałowy: `formatMoney` ze znakiem z pliku, bez koloru (jak `OwnTransferRow`).

  Jednostka „zł” w komórce, styl `amount`. Podgląd importu to trzeci wyjątek od ADR-0037 po ADR-0039 i ADR-0044, bo te wiersze staną się wierszami Operacji.
- **Powody.** Serwer wysyła kod odrzucenia i dane kandydata, nie zdania (jak ADR-0043). UI składa zdanie z `lang/pl/ui.php` i `formatDate`, a nieznany kod pokazuje bez zmian.
- **Checkbox wiersza.** 24 px przez `.import-preview-select-check`, uzasadnione WCAG 2.5.8 (cel dotyku głównej akcji wiersza). Potwierdzenie duplikatów to `.form-check-row` z błędem powiązanym `aria-describedby`.
- **Odrzucone.** Tłumaczenie na serwerze przez `__('ui.…')` (pierwsze `ui.*` w PHP, drugi formater daty), `lang/pl.json` (data ISO, mechanizm bez decyzji D1), kolor według znaku z pliku (rozjazd z zapisem), `formatMoneyAmount` + stopka (rozjazd z Operacjami), usunięcie 24 px (cel poniżej WCAG 2.5.8 na telefonie).

#### 2. Strona design systemu

**File**: `context/foundation/design-system.md`

**Intent**: Strona ma mówić to samo co ADR-0052.

**Contract**:
- Lista ADR w akapicie wstępu: ADR-0052 (podgląd importu: wiersz tytułu kroku 1, panel `ListSurface`, kwoty wg typu z „zł”, powody składane w UI).
- Akapit list (`:18`): nazwana klasa `.import-preview-select-check`.
- Tabela kolorów, wiersz `warning-soft`, `destructive-soft` (`:52`): dopisek o pigułce wiersza niepoprawnego.
- Akapit kwot (`:66`): kwota w podglądzie importu według typu.
- Akapit sinka (`:102`): `/dev/import-preview` montuje stronę, a strażnikiem jest `ImportPreviewHardcodedUiTest`.

#### 3. Reguły ścieżkowe

**File**: `.ai/rules/js.md`, `.ai/rules/js-import.md`

**Intent**: Następny agent ma znać nowe zasady bez czytania ADR.

**Contract**:
- `js.md`, sekcja „Amounts go through formatMoney”: wyjątek „import preview (ADR-0052)” obok Operacji i Kont.
- `js.md`, lista wariantów `Badge`: `destructive-soft` także dla niepoprawnego wiersza importu.
- `js.md`: zdanie o strażniku kroku 2, na wzór zdania o kroku 1.
- `js-import.md`, sekcja „Step 2 (preview)”:
  - wiersz tytułu, panel `ListSurface #header`, pigułki;
  - kwota przez `importRowAmount` (`resources/js/lib/import-row-amount.ts`);
  - powody przez `resources/js/lib/import-row-reasons.ts`;
  - nowy kod odrzucenia = przypadek enuma `ImportRowInvalidReason` + klucz `import_invalid_reason_<code>` w `lang/pl/ui.php`;
  - sink montuje stronę;
  - lista plików pod strażnikiem.
- Globy `index.md` się nie zmieniają (`resources/js/lib/import-*.ts` już pokrywa nowe pliki).

### Success Criteria:

#### Automated Verification:

- ADR-0052 istnieje i jest jedynym plikiem `0052-*` w `context/foundation/adr/`
- `grep -n "ADR-0052" context/foundation/design-system.md .ai/rules/js.md .ai/rules/js-import.md` zwraca trafienie w każdym z trzech plików

#### Manual Verification:

- ADR-0052, `design-system.md` i reguły nie przeczą sobie ani ADR-0037/0038/0043/0048

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Powody po polsku test-first (C1)

### Overview

Powody odrzucenia stają się kodami, kandydat do pary traci zdanie, a UI składa oba teksty po polsku. Najpierw czerwone testy, potem zmiana PHP, typów TS, fikstur i renderu. Całość to jeden commit `fix`.

### Changes Required:

#### 1. Czerwone testy

**File**: `tests/Feature/CsvImportTest.php`, `tests/Unit/CsvImportPreviewValidatorTest.php` (nowy), `tests/Unit/ImportRowReasonTranslationsTest.php` (nowy), `resources/js/lib/import-row-reasons.test.ts` (nowy)

**Intent**: Każdy test ma paść na obecnym kodzie z powodu angielskiego tekstu albo braku kodu, a nie z powodu literówki.

**Contract**:
- `CsvImportTest.php:311` → `->where('rows.0.invalid_reason', 'foreign_currency')`; `:349` → `'future_date'`.
- `test_cross_account_import_flags_pair_candidate_for_financial_operation` (`:2843-2889`) i bliźniak own_transfer (`:3139-3186`): asercja, że kandydat ma `account_name` i `date` w ISO i nie ma klucza `reason` (`->missing('rows.N.pair_candidates.0.reason')`).
- `CsvImportPreviewValidatorTest` (Pest, dataset): po jednym wierszu na każdy z 7 powodów → oczekiwany kod. Poprawny wiersz → `invalid_reason` `null`.
- `ImportRowReasonTranslationsTest` (Pest):
  - każdy przypadek `ImportRowInvalidReason::cases()` ma niepusty klucz `operations.import_invalid_reason_<value>` w `lang/pl/ui.php`;
  - oba rodzaje pary mają `operations.import_pair_reason_<kind>`.
- `import-row-reasons.test.ts` (Vitest):
  - `importInvalidReasonText('future_date', operations)` → polskie zdanie;
  - nieznana wartość (np. `'Invalid or missing date.'`) → ta sama wartość;
  - `importPairReasonText(candidate, operations)` dla obu rodzajów → zdanie z nazwą konta i datą `02.11.2026`.

#### 2. Kody odrzucenia

**File**: `app/Support/CsvImport/ImportRowInvalidReason.php` (nowy), `app/Support/CsvImport/CsvImportPreviewValidator.php`

**Intent**: Walidator zwraca kod zamiast angielskiego zdania.

**Contract**: String-backed enum z przypadkami w TitleCase:

| Przypadek | Wartość | Dzisiejszy tekst |
| --- | --- | --- |
| `MissingDate` | `missing_date` | Invalid or missing date. |
| `FutureDate` | `future_date` | … cannot be in the future. |
| `BeforeAccountOpening` | `before_account_opening` | … earlier than the account opening date. |
| `MissingDescription` | `missing_description` | Description is required. |
| `ForeignCurrency` | `foreign_currency` | Only PLN amounts are supported. |
| `MissingAmount` | `missing_amount` | Invalid or missing amount. |
| `ZeroAmount` | `zero_amount` | Amount must not be zero. |

`validate()` zwraca `invalid_reason: ?string` (wartość enuma). Kolejność sprawdzeń bez zmian.

#### 3. Kandydat do pary bez zdania

**File**: `app/Support/CsvImport/ImportPairCandidateDetector.php`

**Intent**: Usunąć `reason` z obu kandydatów (`:191`, `:230`) i z ich PHPDoc. Pozostałe pola bez zmian.

**Contract**: Kształt kandydata: `kind`, `id`, `account_id`, `account_name`, `date` (ISO), `description`, `converts_financial_operation`.

#### 4. Tłumaczenia

**File**: `lang/pl/ui.php` (grupa `operations`, obok `import_pair_kind_*`)

**Intent**: Polskie teksty powodów.

**Contract**:
- `import_invalid_reason_missing_date` „Brak poprawnej daty operacji w wierszu pliku.”
- `…_future_date` „Data operacji nie może być w przyszłości.”
- `…_before_account_opening` „Data operacji jest wcześniejsza niż data otwarcia konta.”
- `…_missing_description` „Brak opisu operacji w wierszu pliku.”
- `…_foreign_currency` „Import obsługuje tylko kwoty w złotych.”
- `…_missing_amount` „Brak poprawnej kwoty w wierszu pliku.”
- `…_zero_amount` „Kwota nie może być zerowa.”
- `import_pair_reason_financial_operation` „:account (:date): istniejący dochód lub wydatek stanie się tym transferem własnym.”
- `import_pair_reason_own_transfer` „:account (:date): ten wiersz dołączy jako druga strona istniejącego transferu własnego.”

#### 5. Funkcje tekstu i render

**File**: `resources/js/lib/import-row-reasons.ts` (nowy), `resources/js/lib/import-pair-selection.ts`, `resources/js/lib/import-preview-rows.ts`, `resources/js/components/ImportPreviewRow.vue`

**Intent**: Czyste funkcje składają zdania. Wiersz je wywołuje zamiast czytać pola serwera.

**Contract**:
- `importInvalidReasonText(code: string, operations: Record<string, string>): string` zwraca `operations[`import_invalid_reason_${code}`] ?? code`.
- `importPairReasonText(candidate: PairCandidate, operations: Record<string, string>): string` używa klucza według `candidate.kind`, `:account` → `account_name`, `:date` → `formatDate(date)`.
- `PairCandidate` traci `reason`.
- `ImportRow.invalid_reason` zostaje `string | null`, z komentarzem o kodzie z `ImportRowInvalidReason` i o fallbacku.
- `ImportPreviewRow.vue:605-614` renderuje wynik obu funkcji. Warunki `hasNotes` bez zmian.

#### 6. Fikstury i istniejące testy JS

**File**: `resources/js/lib/import-preview-fixtures.ts`, `resources/js/lib/import-preview-rows.test.ts`

**Intent**: Fikstury pokazują to, co wysyła serwer.

**Contract**: Kandydat bez `reason` (`:21`, test `:168`), wiersz niepoprawny z `invalid_reason: 'future_date'` (`:76`).

### Success Criteria:

#### Automated Verification:

- Przed zmianą kodu: nowe i zmienione testy (`CsvImportTest` filtrowany do 4 testów, `CsvImportPreviewValidatorTest`, `ImportRowReasonTranslationsTest`, `import-row-reasons.test.ts`) są czerwone z powodu angielskiego tekstu albo braku kodu lub enuma
- Po zmianie: `php artisan test --compact tests/Feature/CsvImportTest.php tests/Unit/CsvImportPreviewValidatorTest.php tests/Unit/ImportRowReasonTranslationsTest.php tests/Unit/UiTranslationKeysTest.php` przechodzi
- `npm test` i `npm run types:check` przechodzą
- `vendor/bin/pint --dirty --format agent` bez zmian po formatowaniu
- PHP, typy TS, fikstury i testy są w jednym commicie `fix(import-preview-atrament-ui): …`

#### Manual Verification:

- Podgląd pliku z przyszłą datą i z drugą nogą transferu pokazuje polskie powody z datą `dd.mm.rrrr` (aplikacja z worktree)

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Widok w Atramencie (C2–C4, C6)

### Overview

Strona, wiersz i pasek przechodzą na przepisy ADR-0052. Najpierw czerwony strażnik dla trzech plików widoku i czerwony test kwoty.

### Changes Required:

#### 1. Strażnik typografii (czerwony)

**File**: `tests/Unit/ImportPreviewHardcodedUiTest.php` (nowy)

**Intent**: Kopia `ImportStartHardcodedUiTest.php` z własnymi nazwami stałych i funkcji. Docblock odnotowuje F6.

**Contract**:
- Lista plików: `resources/js/pages/OperationsImportPreview.vue`, `resources/js/components/ImportPreviewRow.vue`, `resources/js/components/ImportPreviewSubmitBar.vue`. Sink i fikstury dochodzą w fazie 4.
- Zakazy: cała lista wzorca plus:
  - `pre-Atrament caption size` `/\btext-(?:xs|sm)\b/`;
  - `pre-Atrament weight` `/\bfont-semibold\b/`;
  - `full-colour badge` `/variant="(?:secondary|destructive)"/`;
  - `signed tone on operation amount` `/\bamountToneClass\b/`;
  - `card title` `/\bCardTitle\b/`.
- Trzy testy jak we wzorcu. Test „catches styles” obejmuje też nowe rodzaje.

#### 2. Kwota według typu (czerwony test, potem funkcja)

**File**: `resources/js/lib/import-row-amount.ts` (nowy), `resources/js/lib/import-row-amount.test.ts` (nowy)

**Intent**: Jedno miejsce mapuje typ wiersza na tekst i kolor kwoty zgodnie z ADR-0038.

**Contract**: `importRowAmount(type: PairRowSelection['type'], amountMinorUnits: number): { text: string; className: string }`:

| Typ | Wywołanie | Przykład dla ±6400 |
| --- | --- | --- |
| `income` | `formatFinancialOperationAmount` / `financialOperationAmountClass` z `('income', abs)` | „+64,00 zł” `text-success` |
| `expense` | `('expense', abs)` | „−64,00 zł” `text-foreground` |
| `refund` | `('expense', −abs)` | „+64,00 zł” `text-success` |
| `own_transfer`, `capital_movement` | `formatMoney(amount)` | znak z pliku, `text-foreground` |

Test pokrywa każdy typ przy kwocie dodatniej i ujemnej z pliku.

#### 3. Strona

**File**: `resources/js/pages/OperationsImportPreview.vue`

**Intent**: Wiersz tytułu, panel i potwierdzenie według ADR-0052.

**Contract**:
- `<Head>` i `h1.page-title`: `import_title`. `p.meta text-muted-foreground`: `import_preview_description`.
- „Wróć do operacji”: `Button outline` z `Link` do `operationsRoute()` i `ArrowLeft aria-hidden`. Widoczny zawsze, jak w `OperationsImport.vue:193-208`.
- Okruszek: trzeci element `import_summary_title`.
- Stan pusty: akcja „Wybierz inny plik” (nowy klucz `import_choose_other_file`) do `CsvImportController.create.url()`, z `ArrowLeft aria-hidden`.
- Panel: `ListSurface` z `#header` (`h2.section-title` `import_summary_title`, `p.meta` z kontem). Ciało: pigułki `neutral` / `destructive-soft` / `outline` / `outline` i zastrzeżenie `p.meta text-muted-foreground`. Import `Card*` znika.
- Potwierdzenie duplikatów jest wydzielone do `resources/js/components/ImportPreviewDuplicateAcknowledgement.vue` (pokazuje się je w sinku):
  - `.form-check-row`, `Checkbox` z `v-model`, `name="duplicate_warning_acknowledged"` i `value="1"` jak dziś;
  - natywny `<label for>`;
  - `InputError :id size="xs"` pod wierszem, `aria-describedby` / `aria-invalid` na `Checkbox`, jak `ImportColumnMapping.vue:48-71`.

  Props: `modelValue`, `error`.
- `firstRowsError` zostaje osobnym `InputError` nad paskiem.

#### 4. Wiersz

**File**: `resources/js/components/ImportPreviewRow.vue`

**Intent**: Typografia, odznaki i kwota według ADR-0052.

**Contract**:
- Checkbox wiersza: klasa `import-preview-select-check` zamiast `size-6 rounded-md`.
- Data: `<p class="tabular-nums">`, bez rozmiaru i wagi.
- Kwota: `importRowAmount(selection.type, row.amount_minor_units)` (tekst i klasa koloru), `—` przy `null`. Import `amountToneClass` znika.
- Podpisy `:499`, `:570`, `:606`, `:612`: `meta text-muted-foreground`. Etykieta kwoty odsetek `:505`: klasa `form-field-label` zamiast `text-xs`.
- Odznaki: „Poprawny” `neutral`, „Niepoprawny” `destructive-soft`, duplikat i para `outline` jak dziś.

#### 5. Pasek zatwierdzania

**File**: `resources/js/components/ImportPreviewSubmitBar.vue`

**Intent**: Przycisk jak we wzorcu, podpis w `meta`.

**Contract**: Ikona `Check aria-hidden`, gdy nie ma `processing` (spinner zostaje). Powód blokady: `meta text-muted-foreground`, `aria-describedby` bez zmian.

#### 6. Klasy i klucze

**File**: `resources/css/app.css`, `lang/pl/ui.php`

**Intent**: Nazwane klasy zamiast literałów i jeden nowy klucz.

**Contract**:
- `.import-preview-select-check` (`size-6 rounded-md`) w `@layer components` obok `.import-preview-*`.
- `.import-preview-row`: `border-border-soft` jak w `.accounts-row` (`app.css:758`) i `.month-plan-row` (`:537`), zamiast `border-border/60`. `.operations-row` (`:918`) ma ten sam dług i zostaje poza zakresem.
- `.import-preview-cell-amount`: styl `amount`, jak `.operations-cell-amount` (`:991-994`).
- Szerokość kolumny kwoty na `xl` (`:1022-1031`) sprawdzić na zrzucie z „zł” i poszerzyć w tej samej klasie, jeśli tekst się łamie.
- `import_choose_other_file` „Wybierz inny plik”.

### Success Criteria:

#### Automated Verification:

- Przed zmianą widoku: `ImportPreviewHardcodedUiTest` i `import-row-amount.test.ts` są czerwone (strażnik wypisuje m.in. `OperationsImportPreview.vue:219`, `ImportPreviewRow.vue:363`)
- Po zmianie: `php artisan test --compact tests/Unit/ImportPreviewHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php tests/Feature/CsvImportTest.php` przechodzi
- `npm test`, `npm run check:ui`, `npm run check` i `npm run types:check` przechodzą po `npm run build`

#### Manual Verification:

- Kwota zmienia znak i kolor przy zmianie typu wiersza (wydatek → zwrot), a transfer własny pokazuje znak z pliku
- Błąd potwierdzenia duplikatów jest odczytywany razem z checkboksem (DevTools → Accessibility: opis z `aria-describedby`)
- „Wróć do operacji” prowadzi do Operacji, a „Wybierz inny plik” w stanie pustym do kroku 1

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Sink, zrzuty i bramka (C5)

### Overview

Sink `/dev/import-preview` pokazuje macierz 7 stanów na prawdziwej stronie, strażnicy obejmują sink i fikstury, zrzuty trafiają do folderu zmiany, a pełna bramka przechodzi na PostgreSQL.

### Changes Required:

#### 1. Trasa, kontroler i test sinka

**File**: `routes/web.php`, `app/Http/Controllers/Dev/ImportPreviewKitchenSinkController.php` (nowy), `tests/Feature/DevImportPreviewSinkTest.php`

**Intent**: Sink według bieżącego wzorca `/dev/ui/*`, przy zachowanym URL.

**Contract**:
- `Route::inertia('dev/import-preview', …)` z bloku `if local` (`:50`) zastąpić przez `GET dev/import-preview` → `ImportPreviewKitchenSinkController@show`, nazwa `dev.import-preview`, w grupie obok `dev.ui.import` (`:29`).
- Kontroler: `abort_unless(app()->isLocal(), 404)`, render `dev/ImportPreviewSink`.
- Test: obecny przypadek 404 zostaje, dochodzą gość → login i local → `assertInertia` z komponentem, jak `ImportStartKitchenSinkTest.php`.

#### 2. Strona sinka i fikstury

**File**: `resources/js/pages/dev/ImportPreviewSink.vue`, `resources/js/lib/import-preview-fixtures.ts`

**Intent**: Każdy stan widoku na prawdziwych komponentach. Fikstury bez literałów kolorów.

**Contract**:
- Fikstury:
  - `DEFAULT_CATEGORY_COLOR` (`lib/category-appearance.ts:145`) zamiast 4 kolorów hex;
  - propsy strony (`account`, `otherAccounts`, `termDeposits`, `monthRemainingContext`, `entryToSpend`) dla scenariusza z wierszami i scenariusza pustego;
  - wiersze: poprawny dochód i wydatek, zwrot, duplikat, kandydat do pary (oba rodzaje), niepoprawny z kodem, ruch kapitałowy, podpowiedź kategorii.
- Scenariusze (`data-scenario`):
  - cała strona z wierszami, montowana przez `defineAsyncComponent` jak `ImportStartKitchenSink.vue:15-17`;
  - cała strona bez wierszy (stan pusty);
  - `ImportPreviewDuplicateAcknowledgement` odznaczony, zaznaczony i z błędem;
  - `ImportPreviewSubmitBar` gotowy, zablokowany z powodem i w `processing`;
  - dotychczasowe sekcje wierszy, przepisane bez `text-sm font-medium` i dat ISO.
- Tabela 7 stanów jak `ImportStartKitchenSink.vue:63-96`:
  - default, disabled (wiersz niepoprawny, pasek zablokowany), error (potwierdzenie z błędem, `firstRowsError`) i empty pokazane wprost;
  - hover i focus-visible ze zrzutów z działającej aplikacji;
  - loading: pasek w `processing`, a skeleton N/A, bo strona nie ma deferred props.

#### 3. Strażnicy dla sinka

**File**: `scripts/check-ui-literals.mjs`, `tests/Unit/ImportPreviewHardcodedUiTest.php`

**Intent**: Sink, fikstury i nowy komponent pod tą samą strażą co widok.

**Contract**:
- `check:ui`: dopisać `resources/js/lib/import-preview-fixtures.ts` i `ImportPreviewDuplicateAcknowledgement.vue`. Sink już jest (`:67`).
- Strażnik: dopisać `ImportPreviewSink.vue`, fikstury i nowy komponent.

#### 4. Zrzuty

**File**: `context/changes/import-preview-atrament-ui/screenshots/`

**Intent**: Bramka wizualna jak w zmianach Atramentu. Podgląd worktree według `context/foundation/agents/worktree-preview.md`, jednorazowy skrypt Playwright, którego nie commitujemy.

**Contract**:
- Pełne zrzuty `/dev/import-preview` i `/operations/import/preview` (po wgraniu pliku z wierszem poprawnym, niepoprawnym, duplikatem i kandydatem do pary) przy 1280 i 390 px, motyw jasny i ciemny.
- Do tego zrzut strony po Tab (focus-visible) i po zaznaczeniu duplikatu i wysłaniu bez potwierdzenia (błąd).

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/DevImportPreviewSinkTest.php tests/Unit/ImportPreviewHardcodedUiTest.php` przechodzi
- `npm run check:ui`, `npm run check`, `npm test` i `npm run types:check` przechodzą po `npm run build`
- Pełna bramka na PostgreSQL w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check` (AGENTS.md, Commands) przechodzi
- `vendor/bin/sloppy ci` przechodzi na hoście
- `npx playwright test` (istniejący suite) przechodzi

#### Manual Verification:

- Zrzuty w obu motywach i obu szerokościach są w `screenshots/` i odpowiadają karcie `PageImportPreview`, z odstępstwami zapisanymi w ADR-0052
- Sink pokazuje wszystkie scenariusze bez błędów w konsoli i bez zdublowanych `id` (DevTools)

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie; potem `/10x-impl-review`.

---

## Testing Strategy

### Unit Tests:

- `CsvImportPreviewValidatorTest` (Pest): 7 kodów odrzucenia i wiersz poprawny
- `ImportRowReasonTranslationsTest` (Pest): każdy kod i rodzaj pary ma polski klucz
- `ImportPreviewHardcodedUiTest` (Pest): literały, typografia sprzed Atramentu, pełne odznaki, `amountToneClass`, `CardTitle` w plikach kroku 2
- `import-row-reasons.test.ts` (Vitest): oba zdania, data `dd.mm.rrrr`, fallback nieznanego kodu
- `import-row-amount.test.ts` (Vitest): 5 typów × znak z pliku

### Integration Tests:

- `CsvImportTest`: `invalid_reason` jako kod, kandydat do pary bez `reason` dla obu rodzajów
- `DevImportPreviewSinkTest`: gość, 404 poza `local`, render w `local`
- Regresja: `CsvImportTest.php:64`, `:241`, `AccountPickerTest.php:109`, `Nfr/NfrPerf03ImportPreviewTest.php` (komponent strony i propsy)

### Manual Testing Steps:

1. Wgraj plik z wierszem z przyszłą datą i wierszem bez kwoty: oba mają polski powód i pigułkę „Niepoprawny” w miękkiej czerwieni.
2. Zaimportuj nogę transferu na koncie A, potem wgraj plik konta B: kandydat ma tint `pair` i polski powód z datą `dd.mm.rrrr`.
3. Zmień typ wiersza z wydatku na zwrot: kwota zmienia się z „−64,00 zł” na „+64,00 zł” w kolorze `success`.
4. Zaznacz duplikat i zatwierdź bez potwierdzenia: błąd pod wierszem potwierdzenia, czytnik ekranu łączy go z checkboksem.
5. Kliknij „Wróć do operacji”: ląduje na Operacjach. Otwórz `/operations/import/preview` bez sesji: stan pusty i „Wybierz inny plik” do kroku 1.

## Performance Considerations

Brak wpływu. Zdania i kwoty liczy się na kliencie z danych już obecnych w propsie, a payload zmniejsza się o jedno zdanie na kandydata.

## Migration Notes

Brak migracji bazy. Kształt wiersza w sesji się zmienia: podgląd zapisany przed wdrożeniem pokaże angielski powód odrzucenia bez zmian (fallback), a powód pary złoży się z pól, które stara sesja już ma. Nowy podgląd daje polskie teksty. Wycofanie to revert commitów fazy 3–4, a commit fazy 2 jest samodzielną poprawką.

## References

- Research: `context/changes/import-preview-atrament-ui/research.md`
- Audyt i wzorzec: `context/changes/import-preview-atrament-ui/change.md`, https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (karta `PageImportPreview`, podgląd)
- Wzorzec zmiany: `context/archive/2026-11-06-import-start-atrament-ui/plan.md`
- Wcześniejszy kontrakt kroku 2: `context/archive/2026-11-04-import-preview-ui-contract/`
- ADR-0037, ADR-0038, ADR-0039, ADR-0043, ADR-0044, ADR-0048

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0052 istnieje i jest jedynym plikiem `0052-*` w `context/foundation/adr/` — 2f86a04
- [x] 1.2 `grep -n "ADR-0052" context/foundation/design-system.md .ai/rules/js.md .ai/rules/js-import.md` zwraca trafienie w każdym z trzech plików — 2f86a04

#### Manual

- [ ] 1.3 ADR-0052, `design-system.md` i reguły nie przeczą sobie ani ADR-0037/0038/0043/0048

### Phase 2: Powody po polsku test-first (C1)

#### Automated

- [x] 2.1 Przed zmianą kodu nowe i zmienione testy są czerwone z powodu angielskiego tekstu albo braku kodu lub enuma — 4d80457
- [x] 2.2 Po zmianie testy PHP (`CsvImportTest`, `CsvImportPreviewValidatorTest`, `ImportRowReasonTranslationsTest`, `UiTranslationKeysTest`) przechodzą — 4d80457
- [x] 2.3 `npm test` i `npm run types:check` przechodzą — 4d80457
- [x] 2.4 `vendor/bin/pint --dirty --format agent` bez zmian po formatowaniu — 4d80457
- [x] 2.5 PHP, typy TS, fikstury i testy są w jednym commicie `fix(import-preview-atrament-ui): …` — 4d80457

#### Manual

- [ ] 2.6 Podgląd pliku z przyszłą datą i z drugą nogą transferu pokazuje polskie powody z datą `dd.mm.rrrr`

### Phase 3: Widok w Atramencie (C2–C4, C6)

#### Automated

- [x] 3.1 Przed zmianą widoku `ImportPreviewHardcodedUiTest` i `import-row-amount.test.ts` są czerwone — 7bf99e6
- [x] 3.2 Po zmianie testy PHP (`ImportPreviewHardcodedUiTest`, `UiTranslationKeysTest`, `CsvImportTest`) przechodzą — 7bf99e6
- [x] 3.3 `npm test`, `npm run check:ui`, `npm run check` i `npm run types:check` przechodzą po `npm run build` — 7bf99e6

#### Manual

- [ ] 3.4 Kwota zmienia znak i kolor przy zmianie typu wiersza, a transfer własny pokazuje znak z pliku
- [ ] 3.5 Błąd potwierdzenia duplikatów jest odczytywany razem z checkboksem
- [ ] 3.6 „Wróć do operacji” prowadzi do Operacji, a „Wybierz inny plik” w stanie pustym do kroku 1

### Phase 4: Sink, zrzuty i bramka (C5)

#### Automated

- [x] 4.1 `DevImportPreviewSinkTest` i `ImportPreviewHardcodedUiTest` przechodzą — f328d11
- [x] 4.2 `npm run check:ui`, `npm run check`, `npm test` i `npm run types:check` przechodzą po `npm run build` — f328d11
- [x] 4.3 Pełna bramka `ci:check` na PostgreSQL w worktree przechodzi — f328d11
- [x] 4.4 `vendor/bin/sloppy ci` przechodzi na hoście — f328d11
- [x] 4.5 `npx playwright test` (istniejący suite) przechodzi — f328d11

#### Manual

- [ ] 4.6 Zrzuty w obu motywach i obu szerokościach są w `screenshots/` i odpowiadają karcie `PageImportPreview`
- [ ] 4.7 Sink pokazuje wszystkie scenariusze bez błędów w konsoli i bez zdublowanych `id`
