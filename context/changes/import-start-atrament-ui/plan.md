# Import operacji, krok 1 w Atramencie — Implementation Plan

## Overview

Krok 1 importu (`resources/js/pages/OperationsImport.vue`) przechodzi na kartę `PageImportStart` design systemu „C · Atrament i morski”. Wcześniej, osobnym commitem `fix`, naprawiamy błąd danych w formacie „Inny”:
- martwy checkbox nagłówka;
- `has_header`, które nie dociera albo nie da się go wysłać jako `0`;
- format przestawiany z rozszerzenia pliku;
- kolumny, które się nie przeliczają;
- dwa brakujące klucze tłumaczeń.

Każdy z tych błędów najpierw dostaje czerwony test. Potem widok przechodzi na przepisy z nowego ADR-0048 i dostaje strażników oraz sink `/dev/ui/import`.

## Current State Analysis

Źródło: `context/changes/import-start-atrament-ui/research.md` (wszystkie pięć zarzutów audytu potwierdzone).

- **C1. Brakujące tłumaczenia.** `OperationsImport.vue:428` i `:439` czytają `t.operations.import_optional_column` i `import_no_column`, których nie ma w `lang/pl/ui.php` (sąsiedzi `:600-616`). W UI widać „Nadawca / odbiorca ()” i pustą pierwszą opcję. Typ grup `Record<string, string>` (`resources/js/types/shell.ts:21-48`) tego nie wyłapie, a w repo nie ma testu porównującego klucze.
- **C2. Błąd danych.**
  - `Checkbox` z `:checked` / `@update:checked` (`:351-357`) jest martwy, bo reka 2.11 zna tylko `modelValue`. Pole startuje odznaczone, a `hasHeader` zostaje `true`.
  - Pole nietknięte nie wysyła `has_header`, więc `required_if` (`PreviewCsvImportRequest.php:54`) odrzuca formularz bez widocznego błędu, bo strona nie ma `InputError` dla `has_header` ani `amount_layout`.
  - Pole kliknięte wysyła `1`, a `0` nie da się wysłać, więc plik bez nagłówka traci pierwszą operację (`CsvImportParser.php:51`).
  - Wybór pliku `.csv` przestawia format na mBank (`:98-102`) także po ręcznym wyborze „Inny”.
  - Przeładowanie pliku przez `null → file` (`:147-149`, `:154-156`) nie wywołuje watchera, więc kolumny się nie przeliczają.
  - Indeksy z poprzedniego pliku zostają, więc `canSubmit` (`:53-69`) może być `true` przy ukrytym zestawie „Kolumny”.
  - `resources/js/ui-contract.test.ts:48-52` trzyma ten plik jako ostatni wyjątek z issue #186.
- **C3. Wygląd sprzed Atramentu.**
  - Tytuł to `h1.text-lg` (`:199`), a opis `text-sm` (`:202`).
  - „Wróć do operacji” nie ma ikony (`:206-210`).
  - Panel to `Card` z `CardTitle class="text-base"` (`:213-233`).
  - „Pokaż podgląd” stoi w treści, nie w stopce (`:565-572`).
  - Osiem pól to `div.grid gap-2` + `Label` + `InputError` zamiast `FormField`.
- **C4. Ręczne bloki zamiast przepisów.**
  - „Rozpoznawane pola” powstają dwa razy jako `rounded-lg border p-4 … list-disc` (`:294-347`).
  - Układ kwoty to surowe `<input type="radio">` bez fokusu z tokenów (`:454-482`).
- **C5. Przypadkowa architektura.**
  - Bez aktywnych kont `accountId = ''` (`:37`): cały formularz się renderuje, a przycisk jest wyłączony bez podanego powodu.
  - Brak sinka kroku 1, wpisu w `check:ui` i `*HardcodedUiTest`.

Bez zarzutu: autoryzacja wejścia i wysyłki (`CsvImportController.php:38`, `:56-60`), konto obce lub zarchiwizowane (`PreviewCsvImportRequest.php:47-53`). Propsy strony (`accounts`) się nie zmieniają.

## Desired End State

Strona „Import operacji” wygląda tak:
- Wiersz tytułu ma `page-title`, opis kroku w `meta` i „Wróć do operacji” (`outline` z `ArrowLeft`).
- Pod nim stoi panel formularza o szerokości `--form-panel-width` (640 px):
  - nagłówek zależny od formatu: `h2.section-title` + `meta`;
  - pola na `FormField` (34 px, `aria-describedby`): „Format importu”, „Plik importu (CSV lub XLSX)” i „Konto” z kafelkiem banku;
  - dla mBank i BNP Paribas blok „Rozpoznawane pola” na `.form-preview`;
  - „Pokaż podgląd” w stopce panelu, ze spinnerem w trakcie wysyłki.
- Format „Inny” ma:
  - pole „Pierwszy wiersz zawiera nagłówki kolumn”, domyślnie zaznaczone i działające;
  - zestaw „Kolumny”, który przelicza się po zmianie pliku, nagłówka i formatu;
  - „Nadawca / odbiorca (opcjonalnie)” z opcją „Brak kolumny”;
  - „Układ kwoty” jako `SegmentedControl` `block` `primary`;
  - widoczne błędy `has_header` i `amount_layout`.
- Bez aktywnych kont zamiast formularza stoi stan pusty z linkiem do Kont.

Weryfikacja:
- Plik CSV bez nagłówka wysłany jako „Inny” z odznaczonym polem daje podgląd ze wszystkimi wierszami (spec Playwright).
- `ui-contract.test.ts` nie ma wyjątków, a issue #186 jest zamknięte.
- Test kluczy tłumaczeń i `ImportStartHardcodedUiTest` są zielone.
- `/dev/ui/import` pokazuje macierz 7 stanów.

### Key Discoveries:

- `FormField` generuje `id` przez `useId()`, gdy nie dostanie `id` (`resources/js/components/FormField.vue:15-16`), więc kilka instancji widoku w sinku nie zdubluje `id`, o ile strona przestanie podawać stałe `import-*`.
- `SegmentedControl` z `name` renderuje `<input type="hidden">` (`SegmentedControl.vue:63`) i wysyła się z `<Form>`. Wzorzec: `AccountFormDialog.vue:191-207`.
- reka `Checkbox` z `name` renderuje własny ukryty input, więc jawny ukryty `has_header` wymaga `Checkbox` bez `name`.
- Wzorzec sinka: kontroler `Dev/AccountsKitchenSinkController.php:13` (`abort_unless(app()->isLocal(), 404)`), fiksury `resources/js/pages/dev/accounts-fixtures.ts`, macierz w `pages/dev/AccountsKitchenSink.vue:12-41`, test `tests/Feature/AccountsKitchenSinkTest.php` (gość, poza local, local).
- Wzorzec strażnika: `tests/Unit/AccountsHardcodedUiTest.php` (Pest, stała listy plików + wzorce + funkcja skanu, nazwy globalne z prefiksem widoku). Dług F6 (wspólny helper) zostaje osobnym chore.
- Wzorzec stanu pustego: `Card` + `IndexEmptyState` + `Button as-child` `Link` (`DashboardHoldings.vue:66-79`). Wzorzec „Wróć” z `ArrowLeft`: `OperationsImportPreview.vue:238-245`.
- Szerokości mają dziś tylko dwa tokeny: `--dialog-width` i `--aside-width` (`app.css:161-162`). `max-w-xl` / `max-w-2xl` nie dają 640.
- Wzorzec E2E: `tests/e2e/seed.spec.ts` (role i etykiety, `pageerror`), środowisko w `context/foundation/test-stack.md`. Użytkownik e2e ma konta z `demo:seed-household`.

## What We're NOT Doing

- Krok 2 importu (`OperationsImportPreview.vue`) i jego sink `/dev/import-preview`.
- Wyłączanie „Importuj” w `OperationsPageActions.vue` bez kont, bo to inny widok. Stan pusty pojawia się na stronie importu.
- Zmiany walidacji i parsera po stronie serwera (`PreviewCsvImportRequest`, `CsvImportParser`) oraz propsów `CsvImportController::create`.
- Wspólny helper strażników `*HardcodedUiTest` (F6 z `accounts-atrament-ui`).
- Tekst powodu przy wyłączonym „Pokaż podgląd” (wzorzec `ImportPreviewSubmitBar`), bo jedyny przypadek bez wyjaśnienia, brak kont, zastępuje stan pusty.
- Test pustej kolumny nadawcy (`counterparty_column = ''`) po stronie serwera.
- Zmiany `components/ui/*`, `AccountSelect`, `AccountMarkCompact` i `FormField`.
- Wykrywanie kluczy dynamicznych i dostępu przez nawiasy w teście tłumaczeń (dolne ograniczenie, opisane w teście).

## Implementation Approach

Kolejność: dokumenty → czerwone testy → poprawka zachowania przy starym wyglądzie (commit `fix`, zamyka #186) → restyling na przepisach ADR-0048 ze strażnikami → sink, zrzuty i pełna bramka.

Logika kolumn i zgadywania formatu wychodzi ze strony do `resources/js/lib/import-start.ts` (glob `js-import.md`), z testem Vitest. Strona trzyma tylko stan i tekst pliku.

Stan wysyłany na serwer idzie wyłącznie jawnymi ukrytymi inputami sterowanymi przez Vue (`has_header`, `amount_layout` przez `SegmentedControl name`). Dzięki temu DOM i stan Vue się nie rozjeżdżają.

Stany zależne od pliku pokazujemy w sinku przez dwa wydzielone komponenty, bo sink nie ustawi `File`:
- `ImportRecognizedFields.vue`;
- `ImportColumnMapping.vue`.

## Critical Implementation Details

- **Timing & lifecycle:** `file.text()` jest asynchroniczne. Jeśli użytkownik wybierze drugi plik, zanim pierwszy się wczyta, wynik pierwszego nie może nadpisać tekstu. Przed zapisem porównaj plik z bieżącym `selectedFile`.
- **State sequencing:** domyślne indeksy kolumn ustawiamy tylko po wczytaniu nowego pliku i po przejściu formatu na „Inny”. Przełączenie nagłówka zmienia tylko etykiety (liczba kolumn ta sama), więc wybory użytkownika zostają. Gdy etykiet nie ma, `canSubmit` w „Inny” jest `false`.
- **Regex testu tłumaczeń:** prettier łamie dostęp na dwie linie (`t.operations\n    .import_bnp_paribas_field_counterparty`, `OperationsImport.vue:331-332`), więc wzorzec musi dopuszczać białe znaki wokół kropek: `t\s*\.\s*([a-z_]+)\s*\.\s*([a-z0-9_]+)`.

## Faza 1: Decyzje i dokumenty

### Overview

ADR-0048 zapisuje przepisy strony formularza, które dziś nie istnieją. Reguły i `design-system.md` wskazują nowe elementy, zanim powstanie kod.

### Changes Required:

#### 1. ADR-0048

**File**: `context/foundation/adr/0048-import-start-follows-page-import-start.md`

**Intent**: Zapisać, że krok 1 importu idzie za kartą `PageImportStart` i jakie przepisy dodaje do systemu (styl jak ADR-0045: po angielsku, akapity per obszar, odrzucone alternatywy na końcu).

**Contract**: Decyzje:
1. Panel formularza strony: token `--form-panel-width` 640 px. Klasy `.form-panel`, `.form-panel-header` (`h2.section-title` + `p.meta`), `.form-panel-body`, `.form-panel-footer` (ramka u góry, główna akcja do prawej). Panel to `card` z pełnym `border` i `rounded-lg`, bez cienia (ADR-0037).
2. Wiersz tytułu strony formularza: `page-title`, opis kroku w `meta`, wyjście „Wróć do operacji” jako `outline` z `ArrowLeft`, spójnie z krokiem 2 (ADR-0045 zostawia `ghost` dla „Anuluj”).
3. `FormField` (ADR-0040) obowiązuje także w formularzu na stronie, nie tylko w dialogu.
4. „Rozpoznawane pola” to `.form-preview` z listą `.form-preview-list`.
5. Wiersz checkboxa to `.form-check-row`. Zestaw pól to `fieldset.form-fieldset` z `legend.section-title`, bez własnej ramki (bez kart w kartach).
6. Wybór układu kwoty to `SegmentedControl` `block` `primary` z `name`.
7. Bez aktywnych kont: `IndexEmptyState` w panelu z linkiem do Kont zamiast formularza.
8. Rozszerzenie pliku ustawia format tylko wtedy, gdy użytkownik nie wybrał go ręcznie. Zmiana formatu na „Inny” przelicza kolumny z bieżącego pliku. Uzupełnia ADR-0002 i nie zmienia ADR-0004.

Odrzucone:
- `ListSurface flush` z własną stopką (`#footer` bez `border-t`);
- `max-w-xl` / `max-w-2xl` (576/672, nie 640);
- usunięcie zgadywania formatu;
- `ghost` dla „Wróć”.

#### 2. Design system

**File**: `context/foundation/design-system.md`

**Intent**: Dopisać panel formularza strony i nowe klasy do listy „Gotowe elementy”, poszerzyć opis `FormField` (pole w dialogu **i** w formularzu strony) oraz dopisać sink `/dev/ui/import` w akapicie o sinkach.

**Contract**: Sekcja „Gotowe elementy” (`:82`) i akapit o sinkach (`:101`); odwołania do ADR-0048.

#### 3. Reguły

**File**: `.ai/rules/js-import.md`, `.ai/rules/js.md`

**Intent**:
- W `js-import.md`: tytuł „Import” zamiast „Import preview” (glob obejmuje oba kroki). Nowa sekcja o kroku 1:
  - `has_header` tylko jawnym ukrytym inputem, `Checkbox` bez `name`;
  - logika kolumn i formatu w `lib/import-start.ts`;
  - sink `/dev/ui/import`;
  - strażnik `ImportStartHardcodedUiTest`.
- W `js.md`: `/dev/ui/import` na liście sandboxów i jedno zdanie o strażniku kroku 1 obok Kont.

**Contract**: Treść reguł. Potem `php artisan boost:index-rules`, jeśli zmienia się `index.md`.

### Success Criteria:

#### Automated Verification:

- ADR-0048 istnieje i jest jedynym plikiem `0048-*` w `context/foundation/adr/`
- `php artisan boost:index-rules` kończy się bez błędu, a `.ai/rules/index.md` nadal mapuje `OperationsImport*.vue` na `js-import.md`

#### Manual Verification:

- ADR-0048, `design-system.md` i `js-import.md` nie przeczą sobie ani ADR-0037/0040/0045

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Poprawka danych test-first (C1, C2)

### Overview

Najpierw cztery czerwone testy i test Feature pokrywający serwer, potem poprawka zachowania strony przy niezmienionym wyglądzie. Commit `fix(import-start-atrament-ui): …` zamyka issue #186.

### Changes Required:

#### 1. Test kluczy tłumaczeń (czerwony)

**File**: `tests/Unit/UiTranslationKeysTest.php`

**Intent**: Trwały strażnik dla całego repo. Każdy `t.<grupa>.<klucz>` użyty w `resources/js` istnieje w `lang/pl/ui.php`. Dziś pada dokładnie na dwóch kluczach importu.

**Contract**: Test Pest bez aplikacji:
- `require` `lang/pl/ui.php`;
- skan `resources/js/**/*.{vue,ts}` bez katalogów generowanych (`actions`, `routes`, `wayfinder`) i bez `*.test.ts`;
- regex z sekcji Critical Implementation Details;
- komunikat porażki: lista `plik:linia: grupa.klucz`;
- PHPDoc mówi, że to dolne ograniczenie (klucze dynamiczne i nawiasy niewidoczne).

#### 2. Logika kroku 1 w lib (czerwony → zielony)

**File**: `resources/js/lib/import-start.ts`, `resources/js/lib/import-start.test.ts`

**Intent**: Przenieść ze strony parsowanie linii CSV, wykrywanie separatora, etykiety kolumn i domyślne indeksy, a dodać regułę formatu „tylko gdy nietknięty”. Test powstaje przed kodem.

**Contract**: Eksporty:
- `type ImportFormat = 'mbank' | 'bnp_paribas' | 'other'`;
- `type ImportStartAccount` (dziś lokalny `AccountRow`, `OperationsImport.vue:22-24`);
- `importColumnLabels(text: string, hasHeader: boolean, columnWord: string): string[]` — `[]` dla pliku bez niepustej linii;
- `defaultColumnIndices(count: number): { date; description; amount; inflow; outflow }` — jak dziś `:132-137`;
- `formatForChosenFile(fileName: string, current: ImportFormat, chosenByUser: boolean): ImportFormat`.

Przypadki testu:
- `;` vs `,`;
- cudzysłowy z separatorem w środku;
- pusta komórka nagłówka → „Kolumna n”;
- `hasHeader=false` → „Kolumna 1..n”;
- plik jednokolumnowy (indeksy nie wychodzą poza zakres);
- `.csv` / `.xlsx` / inne rozszerzenie;
- `chosenByUser=true` zachowuje `other` dla `.csv`.

Import z `vite-plus/test`, ścieżki względne (`frontend-toolchain.md`).

#### 3. Wyjątek #186 (czerwony)

**File**: `resources/js/ui-contract.test.ts`

**Intent**: Usunąć `'pages/OperationsImport.vue'` z `DEAD_CHECKBOX_BINDING_EXCEPTIONS` (`:50`). Test pada, dopóki strona używa `:checked`.

**Contract**: Lista wyjątków pusta. Komentarz nad nią mówi, że lista ma zostać pusta.

#### 4. Spec E2E (czerwony)

**File**: `tests/e2e/import-other-format-without-header.spec.ts`

**Intent**: Chronić klasę błędu, której nie widzą Vitest ani HTTP: to, co serwer dostaje z DOM, różni się od tego, co pokazuje formularz.

**Contract**: Kroki, wzorem `seed.spec.ts` (komentarz z ryzykiem, `pageerror` pusty na końcu):
1. `/operations/import`, „Format importu” → „Inny”.
2. Plik przez `setInputFiles({ name: 'wyciag.csv', mimeType: 'text/csv', buffer })`: dwa wiersze bez nagłówka, `;`, data / opis z `Date.now()` / kwota, daty w bieżącym roku.
3. Asercje: format nadal „Inny”, checkbox „Pierwszy wiersz zawiera nagłówki kolumn” zaznaczony, a po odznaczeniu zestaw „Kolumny” pokazuje „Kolumna 1..3”.
4. „Pokaż podgląd” prowadzi do kroku 2, na którym widać oba opisy.

Spec tylko podgląda, nic nie zapisuje w bazie.

#### 5. Feature `has_header = 0`

**File**: `tests/Feature/CsvImportTest.php`

**Intent**: Pokrycie serwera dla ścieżki, której dotąd nie testowano: `has_header => '0'` zachowuje pierwszą linię jako operację. Test powinien być zielony od razu, bo błąd leży po stronie klienta. Jeśli nie jest, to nowy dowód i trzeba go zbadać przed poprawką.

**Contract**: Nowa metoda obok `test_user_can_preview_csv_with_signed_amount_column` (`:36`), ten sam kształt żądania.

#### 6. Klucze tłumaczeń

**File**: `lang/pl/ui.php`

**Intent**: Dodać brakujące klucze oraz klucze stanu pustego potrzebne w fazie 3 (jedno miejsce zmian copy).

**Contract**: W grupie `operations`, obok `import_column`:
- `import_optional_column` => „opcjonalnie”;
- `import_no_column` => „Brak kolumny”;
- `import_no_accounts` => „Brak aktywnych kont”;
- `import_no_accounts_description` => „Import przypisuje operacje do jednego konta. Dodaj konto albo przywróć zarchiwizowane, a potem wróć do importu.”;
- `import_open_accounts` => „Otwórz konta”.

#### 7. Poprawka strony

**File**: `resources/js/pages/OperationsImport.vue`

**Intent**: Naprawić stan i wysyłkę bez zmiany wyglądu.

**Contract**:
- `Checkbox` z `v-model="hasHeader"`, bez `name`, bez `value`.
- Jeden `<input type="hidden" name="has_header" :value="hasHeader ? '1' : '0'">`, renderowany tylko w formacie „Inny” (zastępuje `:362-367`).
- Flaga `formatChosenByUser` ustawiana w `@change` selecta formatu. Wybór pliku woła `formatForChosenFile`.
- Tekst pliku w `ref` wczytywany raz na plik, z ochroną przed wyścigiem. `columnLabels` jako `computed` z tekstu, `hasHeader` i formatu. Zniknęła sztuczka `null → file` i oba watchery `:140-158`.
- Indeksy domyślne według Critical Implementation Details.
- `canSubmit` w „Inny” wymaga `columnLabels.length > 0`.
- `InputError` dla `errors.has_header` i `errors.amount_layout`.
- Typy i funkcje z `@/lib/import-start`.

### Success Criteria:

#### Automated Verification:

- Przed poprawką: `UiTranslationKeysTest`, `import-start.test.ts`, `ui-contract.test.ts` i spec E2E są czerwone z powodu opisanego błędu
- `php artisan test --compact tests/Unit/UiTranslationKeysTest.php tests/Feature/CsvImportTest.php` przechodzi
- `npm test` przechodzi (w tym `import-start.test.ts` i `ui-contract.test.ts` bez wyjątków)
- `npx playwright test tests/e2e/import-other-format-without-header.spec.ts` przechodzi
- `npm run check` i `npm run types:check` przechodzą

#### Manual Verification:

- Format „Inny” z plikiem `.csv`: format zostaje „Inny”, kolumny się pojawiają, przełączenie nagłówka zmienia etykiety bez gubienia wybranych kolumn
- Wysyłka z pustym `has_header` lub `amount_layout` (np. przez DevTools) pokazuje błąd pod polem

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3. Commit fazy zamyka issue #186 (`Closes #186` w treści commita lub PR).

---

## Faza 3: Widok w Atramencie (C3–C5)

### Overview

Strona przechodzi na przepisy ADR-0048, a stany zależne od pliku wychodzą do dwóch komponentów. Pojawia się stan pusty i strażnicy literałów.

### Changes Required:

#### 1. Tokeny i klasy

**File**: `resources/css/app.css`

**Intent**: Dodać przepisy z ADR-0048 zamiast utility w szablonie.

**Contract**:
- `--form-panel-width: 640px` w `:root` obok `--dialog-width`.
- W `@layer components`: `.form-panel` (`card`, `border`, `rounded-lg`, `w-full`, `max-w-(--form-panel-width)`), `.form-panel-header`, `.form-panel-body`, `.form-panel-footer` (`border-t`, `justify-end`), `.form-preview-list` (lista punktowana `muted-foreground`), `.form-check-row`, `.form-fieldset`.
- Wartości ze skali Tailwinda i tokenów, bez arbitralnych.
- Reguła `css.md` obowiązuje.

#### 2. Komponenty

**File**: `resources/js/components/ImportRecognizedFields.vue`, `resources/js/components/ImportColumnMapping.vue`

**Intent**: Wydzielić bloki, które sink musi pokazać bez prawdziwego pliku.

**Contract**:
- `ImportRecognizedFields`: prop `format: 'mbank' | 'bnp_paribas'`. Render: `.form-preview` z tytułem i `.form-preview-list`, klucze `import_mbank_*` / `import_bnp_paribas_*` bez zmian.
- `ImportColumnMapping`: prop `columnLabels: string[]` i prop `errors: Partial<Record<string, string>>`. Modele `hasHeader`, `amountLayout`, `dateColumn`, `descriptionColumn`, `counterpartyColumn`, `amountColumn`, `inflowColumn`, `outflowColumn`. Render:
  - `.form-check-row` z `Checkbox v-model` + label + jawny ukryty `has_header` + błąd (`aria-describedby`);
  - przy `columnLabels.length > 0` `fieldset.form-fieldset` z `legend.section-title` „Kolumny” i polami `FormField` (`.native-select`);
  - nadawca z „(opcjonalnie)” i opcją „Brak kolumny”;
  - „Układ kwoty” jako `FormField group` + `SegmentedControl block tone="primary" name="amount_layout"` (`aria-labelledby` = `labelId`);
  - kolumny kwoty zależne od układu.
- Bez stałych `id`: identyfikatory daje `FormField` (`useId`).

#### 3. Strona

**File**: `resources/js/pages/OperationsImport.vue`

**Intent**: Wiersz tytułu i panel według ADR-0048, pola na `FormField`, stan pusty.

**Contract**:
- Wiersz tytułu: `h1.page-title`, `p.meta text-muted-foreground` (`import_step_mapping`), `Button variant="outline" as-child` z `ArrowLeft class="size-4" aria-hidden="true"`.
- Przy `accounts.length === 0` zamiast formularza `section.form-panel` z `IndexEmptyState` (`import_no_accounts`, `import_no_accounts_description`) i `Button as-child` `Link` do listy Kont (Wayfinder, jak `DashboardHoldings.vue:73`) z `import_open_accounts`.
- W przeciwnym razie `<Form class="form-panel">`:
  - `.form-panel-header` (tytuł i opis zależne od formatu, jak dziś `:216-231`);
  - `.form-panel-body` z polami: „Format importu” `FormField` + `.native-select`, „Plik” `FormField` + `Input type="file"` z `aria-describedby`/`aria-invalid` ze slotu, „Konto” `FormField` + `AccountSelect` (`id`, `ariaDescribedby`, `ariaInvalid` jak `ExpenseFormDialog.vue:563-576`, bez martwego `required`);
  - `ImportRecognizedFields` albo `ImportColumnMapping`;
  - `.form-panel-footer` z „Pokaż podgląd” (`Spinner` przy `processing`, `disabled` jak dziś).
- Znikają `Card*`, `Label` i ręczne `InputError` w stronie.

#### 4. Strażnicy literałów

**File**: `scripts/check-ui-literals.mjs`, `tests/Unit/ImportStartHardcodedUiTest.php`

**Intent**: Zablokować powrót literałów i typografii sprzed Atramentu w kroku 1.

**Contract**:
- `check-ui-literals.mjs`: dopisać `pages/OperationsImport.vue`, `components/ImportRecognizedFields.vue`, `components/ImportColumnMapping.vue` obok plików podglądu (`:57-62`).
- `ImportStartHardcodedUiTest.php`: kopia `AccountsHardcodedUiTest.php` z prefiksem `IMPORT_START_` / `importStart…` dla stałych i funkcji (globalne nazwy Pest nie mogą kolidować). Lista: te trzy pliki. PHPDoc odsyła do ADR-0048 i F6.

### Success Criteria:

#### Automated Verification:

- `npm run check:ui` przechodzi z nowymi plikami na liście
- `php artisan test --compact tests/Unit/ImportStartHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php tests/Feature/AccountPickerTest.php` przechodzi
- `npm test`, `npm run check` i `npm run types:check` przechodzą
- `npx playwright test tests/e2e/import-other-format-without-header.spec.ts` nadal przechodzi po zmianie markupu

#### Manual Verification:

- Strona przy 1280 i 390 px odpowiada karcie `PageImportStart`: panel 640, nagłówek h2 + `meta`, akcja w stopce, kafelek banku w „Konto”
- Tab przechodzi kolejno przez pola i segmenty z pierścieniem `ring`, a strzałki w `SegmentedControl` przenoszą fokus
- Użytkownik bez aktywnych kont widzi stan pusty z działającym linkiem do Kont

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Sink, zrzuty i bramka

### Overview

Sink `/dev/ui/import` pokazuje macierz 7 stanów kroku 1. Zrzuty idą do folderu zmiany, a pełna bramka CI przechodzi na PostgreSQL.

### Changes Required:

#### 1. Trasa i kontroler sinka

**File**: `routes/web.php`, `app/Http/Controllers/Dev/ImportStartKitchenSinkController.php`, `tests/Feature/ImportStartKitchenSinkTest.php`

**Intent**: Sink według bieżącego wzorca `/dev/ui/*`.

**Contract**:
- `GET dev/ui/import` → `ImportStartKitchenSinkController@show`, nazwa `dev.ui.import`, w grupie obok `dev.ui.accounts` (`routes/web.php:27`).
- Kontroler: `abort_unless(app()->isLocal(), 404)`, render `dev/ImportStartKitchenSink`.
- Test: trzy przypadki jak `AccountsKitchenSinkTest.php` (gość → login, poza local → 404, local → komponent).

#### 2. Strona sinka i fiksury

**File**: `resources/js/pages/dev/ImportStartKitchenSink.vue`, `resources/js/pages/dev/import-start-fixtures.ts`

**Intent**: Pokazać każdy stan widoku na prawdziwych komponentach.

**Contract**:
- Fiksury typowane `ImportStartAccount`:
  - konta mBank, BNP Paribas i gotówka;
  - pusta lista;
  - etykiety kolumn z nagłówkiem i bez;
  - obiekt błędów (`has_header`, `date_column`, `amount_layout`, `file`).
- Scenariusze (`data-scenario`):
  - strona z kontami (start „Inny”);
  - strona bez kont (stan pusty);
  - `ImportRecognizedFields` mBank i BNP;
  - `ImportColumnMapping`: bez pliku (sam checkbox), signed, split, z błędami.
- Tabela 7 stanów jak `AccountsKitchenSink.vue:12-41`:
  - default, error i empty pokazane wprost;
  - hover i focus-visible ze zrzutów z działającej aplikacji;
  - disabled: przycisk bez pliku;
  - loading: spinner przy opóźnionym POST, zrzut, a skeleton N/A, bo strona nie ma deferred props.

#### 3. Strażnicy dla plików sinka

**File**: `scripts/check-ui-literals.mjs`, `tests/Unit/ImportStartHardcodedUiTest.php`

**Intent**: Sink i fiksury pod tą samą strażą co widok (wzorzec Kont).

**Contract**: Dopisać oba pliki do obu list.

#### 4. Zrzuty

**File**: `context/changes/import-start-atrament-ui/screenshots/`

**Intent**: Bramka wizualna jak w zmianach Atramentu. Podgląd worktree według `context/foundation/agents/worktree-preview.md`, jednorazowy skrypt Playwright, którego nie commitujemy.

**Contract**:
- Pełne zrzuty `/dev/ui/import` i `/operations/import` przy 1280 i 390 px, motyw jasny i ciemny.
- Do tego `/operations/import` po wyborze „Inny” z plikiem i po Tab (focus-visible).

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/ImportStartKitchenSinkTest.php tests/Unit/ImportStartHardcodedUiTest.php` przechodzi
- `npm run check:ui`, `npm run check`, `npm test` i `npm run types:check` przechodzą po `npm run build`
- Pełna bramka na PostgreSQL w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check` (AGENTS.md, Commands) przechodzi
- `vendor/bin/sloppy ci` przechodzi na hoście
- `npx playwright test` (cały suite, w tym seed i nowy spec) przechodzi

#### Manual Verification:

- Zrzuty w obu motywach i obu szerokościach są w `screenshots/` i odpowiadają karcie `PageImportStart`
- Sink pokazuje wszystkie scenariusze bez błędów w konsoli i bez zdublowanych `id` (sprawdzenie w DevTools)

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie; potem `/10x-impl-review`.

---

## Testing Strategy

### Unit Tests:

- `UiTranslationKeysTest` (Pest): każdy `t.<grupa>.<klucz>` w `resources/js` istnieje w `lang/pl/ui.php`
- `import-start.test.ts` (Vitest): separator, cudzysłowy, puste komórki nagłówka, `hasHeader`, mało kolumn, reguła formatu „tylko gdy nietknięty”
- `ui-contract.test.ts`: brak martwych powiązań `Checkbox`, lista wyjątków pusta
- `ImportStartHardcodedUiTest` (Pest): literały i typografia sprzed Atramentu w plikach kroku 1

### Integration Tests:

- `CsvImportTest`: `has_header = '0'` zachowuje pierwszą linię
- `ImportStartKitchenSinkTest`: dostęp do sinka
- `AccountPickerTest:48-70`: propsy strony bez zmian (regresja)
- E2E `import-other-format-without-header.spec.ts`: „Inny” + `.csv` bez nagłówka → pełny podgląd

### Manual Testing Steps:

1. Wybierz `.csv` przy nietkniętym formacie: format przestawia się na mBank, a pod spodem widać „Rozpoznawane pola”.
2. Ustaw „Inny”, wybierz `.csv`: format zostaje, kolumny się pojawiają, „Nadawca / odbiorca (opcjonalnie)” ma „Brak kolumny”.
3. Odznacz nagłówek: etykiety zmieniają się na „Kolumna n”, wybrane kolumny zostają.
4. Przełącz „Układ kwoty” klawiaturą i wyślij podgląd w obu układach.
5. Zaloguj się jako użytkownik bez aktywnych kont: stan pusty i link do Kont.

## Performance Considerations

Plik czytany jest raz na wybór, a etykiety liczy się z pierwszej niepustej linii, jak dziś. Limit 5000 wierszy pilnuje serwer.

## Migration Notes

Brak migracji i zmian propsów. Wycofanie to revert commitów fazy 3–4. Commit fazy 2 jest samodzielną poprawką błędu.

## References

- Research: `context/changes/import-start-atrament-ui/research.md`
- Audyt i wzorzec: `context/changes/import-start-atrament-ui/change.md`, https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (karta `PageImportStart`, podgląd)
- Wzorzec zmiany: `context/archive/2026-11-06-accounts-atrament-ui/plan.md`, `follow-ups/review-fixes.md` (F6)
- ADR-0002, ADR-0004, ADR-0037, ADR-0040, ADR-0044, ADR-0045
- Issue #186 (martwe powiązania `Checkbox`)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0048 istnieje i jest jedynym plikiem `0048-*` w `context/foundation/adr/` — 8f24448
- [x] 1.2 `php artisan boost:index-rules` kończy się bez błędu, a `.ai/rules/index.md` nadal mapuje `OperationsImport*.vue` na `js-import.md` — 8f24448

#### Manual

- [ ] 1.3 ADR-0048, `design-system.md` i `js-import.md` nie przeczą sobie ani ADR-0037/0040/0045

### Phase 2: Poprawka danych test-first (C1, C2)

#### Automated

- [x] 2.1 Przed poprawką: `UiTranslationKeysTest`, `import-start.test.ts`, `ui-contract.test.ts` i spec E2E są czerwone z powodu opisanego błędu
- [x] 2.2 `php artisan test --compact tests/Unit/UiTranslationKeysTest.php tests/Feature/CsvImportTest.php` przechodzi
- [x] 2.3 `npm test` przechodzi (w tym `import-start.test.ts` i `ui-contract.test.ts` bez wyjątków)
- [x] 2.4 `npx playwright test tests/e2e/import-other-format-without-header.spec.ts` przechodzi
- [x] 2.5 `npm run check` i `npm run types:check` przechodzą

#### Manual

- [ ] 2.6 Format „Inny” z plikiem `.csv`: format zostaje „Inny”, kolumny się pojawiają, przełączenie nagłówka zmienia etykiety bez gubienia wybranych kolumn
- [ ] 2.7 Wysyłka z pustym `has_header` lub `amount_layout` (np. przez DevTools) pokazuje błąd pod polem

### Phase 3: Widok w Atramencie (C3–C5)

#### Automated

- [ ] 3.1 `npm run check:ui` przechodzi z nowymi plikami na liście
- [ ] 3.2 `php artisan test --compact tests/Unit/ImportStartHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php tests/Feature/AccountPickerTest.php` przechodzi
- [ ] 3.3 `npm test`, `npm run check` i `npm run types:check` przechodzą
- [ ] 3.4 `npx playwright test tests/e2e/import-other-format-without-header.spec.ts` nadal przechodzi po zmianie markupu

#### Manual

- [ ] 3.5 Strona przy 1280 i 390 px odpowiada karcie `PageImportStart`: panel 640, nagłówek h2 + `meta`, akcja w stopce, kafelek banku w „Konto”
- [ ] 3.6 Tab przechodzi kolejno przez pola i segmenty z pierścieniem `ring`, a strzałki w `SegmentedControl` przenoszą fokus
- [ ] 3.7 Użytkownik bez aktywnych kont widzi stan pusty z działającym linkiem do Kont

### Phase 4: Sink, zrzuty i bramka

#### Automated

- [ ] 4.1 `php artisan test --compact tests/Feature/ImportStartKitchenSinkTest.php tests/Unit/ImportStartHardcodedUiTest.php` przechodzi
- [ ] 4.2 `npm run check:ui`, `npm run check`, `npm test` i `npm run types:check` przechodzą po `npm run build`
- [ ] 4.3 Pełna bramka na PostgreSQL w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check` (AGENTS.md, Commands) przechodzi
- [ ] 4.4 `vendor/bin/sloppy ci` przechodzi na hoście
- [ ] 4.5 `npx playwright test` (cały suite, w tym seed i nowy spec) przechodzi

#### Manual

- [ ] 4.6 Zrzuty w obu motywach i obu szerokościach są w `screenshots/` i odpowiadają karcie `PageImportStart`
- [ ] 4.7 Sink pokazuje wszystkie scenariusze bez błędów w konsoli i bez zdublowanych `id` (sprawdzenie w DevTools)
