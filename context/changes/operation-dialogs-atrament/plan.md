# Dialogi operacji według Atramentu: podgląd „przed → po” i kontrakt ADR-0040 — Implementation Plan

## Overview

Cztery dialogi operacji (`ExpenseFormDialog`, `IncomeFormDialog`, `OwnTransferFormDialog`, `CapitalMovementFormDialog`) przechodzą na kontrakt ADR-0040, który `category-dialog-atrament` dał dialogom Budżetu:

- pola w `FormField` z `aria-describedby` i `aria-invalid`, kwota w wariancie `money`;
- wybór typu i kierunku jako `SegmentedControl` `block` `primary`;
- „Anuluj” `outline`, spinner na „Zapisz” i blokada zamykania w trakcie zapisu;
- komunikat pod polem przy złej kwocie.

Dochodzi pierwszy w aplikacji blok podglądu „przed → po”. Pokazuje Do wydania (w każdym dotkniętym miesiącu z planem), roczny limit kategorii oraz saldo konta lub lokaty. Ostrzeżenia nieblokujące stoją w środku bloku, pod odczytem, którego dotyczą (ADR-0041). Pola ustawiają się w kolejności z Atramentu. Każdy stan dialogu można obejrzeć w `/dev/ui/dialogs`, a pliki są pod strażą literałów.

## Current State Analysis

Audyt w `research.md`, zarzuty C1–C5:

- **C1 Pola.** Żadne pole w 4 dialogach nie jest `FormField` i żadna kontrolka nie ma `aria-invalid` ani `aria-describedby` (`ExpenseFormDialog.vue:459-610`, `IncomeFormDialog.vue:319-454`, `OwnTransferFormDialog.vue:193-286`, `CapitalMovementFormDialog.vue:247-363`). `AccountSelect` nie przekazuje propów aria na trigger (`AccountSelect.vue:13-24,48`). `CategorySelect` nie ma `ariaInvalid` (`CategorySelect.vue:24,54`). „Opis z wyciągu” to ręczny `textarea readonly` z promieniem okna `rounded-xl` (`ExpenseFormDialog.vue:509-515`, `IncomeFormDialog.vue:369-375`, `OwnTransferFormDialog.vue:229-237`). Formularz ma `space-y-4` zamiast `grid gap-4`.
- **C2 Wybory.** Trzy surowe checkboxy (`ExpenseFormDialog.vue:533-588`, `IncomeFormDialog.vue:393-403`) to jeden wybór typu: flagi wykluczają się w watcherach `:379-396`. Kierunek i lokata ruchu kapitału to reka `Select` (`CapitalMovementFormDialog.vue:277-336`). Etykieta `operations.income_conversion_label` brzmi „To jest Income”.
- **C3 Podgląd.** `previewToSpendWarnings` liczy `before` i `after`, ale zwraca tylko ostrzeżenie (`entry-to-spend-warning.ts:208-218`). Ma drugiego konsumenta w Imporcie (`import-to-spend-warnings.ts:81`). `previewHandEntryWarnings` liczy saldo „po”, ale wystawia tylko flagę (`hand-entry-warning-preview.ts:274-285,312,355,394`). Dodanie dochodu zwraca same `null` (`:333-341`). Plik nie ma testów. Limit „teraz” w edycji dolicza edytowany rekord (`:398-402`, „bez tej operacji”), a Do wydania `before` to stan zapisany: dwa znaczenia „przed”. Limit to ręczne pudełko (`ExpenseFormDialog.vue:612-631`), a ostrzeżenia stoją w osobnym `Alert` (`:633-685`).
- **C4 Stopka i stany.** W żadnym dialogu nie ma blokady zamykania, spinnera ani wyłączonego „Anuluj”. „Anuluj” jest `secondary` (`ExpenseFormDialog.vue:691`, `IncomeFormDialog.vue:530`, `OwnTransferFormDialog.vue:302`, `CapitalMovementFormDialog.vue:392`). Transfer i ruch kapitału biorą etykiety stopki z `accounts`. Zła kwota w 4 wariantach `syncAmount` po cichu zamienia się na 0, a „-5” w wydatku na 5 (`ExpenseFormDialog.vue:398-406`). Grupa `operations` nie ma klucza błędu kwoty.
- **C5 Bramka.** Żadnego z 4 dialogów nie ma w `/dev/ui/dialogs` (`DialogsKitchenSink.vue:18-52`), w `scripts/check-ui-literals.mjs` ani w `ui-contract.test.ts`. Każdy dialog ma przypiętą akcję Wayfinder bez propa `form?`. Nie ma fixture'ów propsów dialogów.

## Desired End State

Każdy z 4 dialogów:

- Pola są w `FormField` (34 px, etykieta, błąd powiązany przez `aria-describedby`, `aria-invalid` przy błędzie). Kwota jest w JetBrains Mono, do prawej, z „zł”.
- Kolejność pól z Atramentu:
  - wydatek i dochód: Typ → rząd „Kwota | Data” → Kategoria → Konto → Opis → Kontrahent → Opis z wyciągu → podgląd;
  - transfer: „Kwota | Data” → Z konta → Na konto → Opis → Opis z wyciągu → podgląd;
  - ruch kapitału: Kierunek → „Kwota | Data” → Konto → Lokata → Opis → podgląd.
  Rząd „Kwota | Data” składa się w jedną kolumnę dopiero poniżej 390 px szerokości okna przeglądarki.
- Typ to `SegmentedControl`:
  - wydatek: Wydatek | Zwrot, a w edycji zwrotu Wydatek | Zwrot | Dochód;
  - dochód w edycji: Dochód | Zwrot wydatku;
  - ruch kapitału: Konto → lokata | Lokata → konto.
  Tytuł wynika z wybranego typu. Lokata to `.native-select`.
- Blok podglądu pokazuje wiersze „etykieta: przed → po”: „Do wydania w listopadzie 2026”, „Roczny limit kategorii”, „Saldo konta” / „Saldo konta źródłowego” / „Saldo lokaty”. Wartości są kolorowane przez `amountToneClass`. Ostrzeżenie stoi w kolorze `warning` pod wierszem, którego dotyczy. Ostrzeżenia Month Remaining (miesiąc bez planu) to linie na końcu bloku. „Przed” to stan zapisany: edycja bez zmian pokazuje równe wartości.
- „abc”, „12,345” i „-5” w kwocie dają „Wpisz kwotę, np. 1200,50.” pod polem i wyłączony „Zapisz”. „0,00” przechodzi do serwera, który je odrzuca jak dziś.
- W trakcie zapisu: spinner na „Zapisz”, „Anuluj” wyłączony, X ukryty, a `Esc` i nakładka nie zamykają. „Anuluj” jest `outline`.

Weryfikacja: `/dev/ui/dialogs?open=<stan>` otwiera każdy stan z fixture'ów. Zrzuty leżą w `context/changes/operation-dialogs-atrament/screenshots/`. `check:ui` i `ui-contract.test.ts` obejmują 4 dialogi. Testy Feature potwierdzają, że payload się nie zmienił.

### Key Discoveries:

- `previewHandEntryWarnings` wołają tylko te 4 dialogi (grep po `resources/js`), więc rozszerzenie `HandEntryPreviewResult` nie dotyka Importu. `previewToSpendWarnings` ma konsumenta w Imporcie (`import-to-spend-warnings.ts:81`), więc jego sygnatura i wynik zostają.
- `handEntryToSpendChanges` zwraca `[]` przy kwocie 0 albo bez kategorii (`entry-to-spend-warning.ts:240-246`), więc odczyt Do wydania istnieje dopiero przy kwocie większej od 0.
- Serwer oczekuje flag `convert_to_income` (`UpdateExpenseRequest.php:45`) i `convert_to_expense_refund` (`UpdateIncomeRequest.php:45,60`), a nie pola z typem. Kierunek ruchu kapitału to pole `direction` z wartością enumu, więc `SegmentedControl` może go wysłać przez `name`.
- `.form-field` ustawia `--control-h: var(--field-height)` (`app.css:323-326`). Nie ma klasy rzędu dwóch pól, podglądu ani tekstu tylko do odczytu.
- Trasa `dev.ui.dialogs.validate` czeka 1,5 s i zwraca błąd dla każdego przesłanego pola (`DialogsKitchenSinkController.php:24-43`). Ukryte `convert_to_*` też dostanie błąd, więc da się obejrzeć błąd pod wyborem typu.
- `amountToneClass`: `> 0` → `text-success`, `< 0` → `text-destructive`, `0` bez koloru (`lib/amount-tone.ts:1-13`). `design-system.md:66` każe kolorować nim saldo i „do wydania”.
- Klucze `to_spend_negative_warning` i `negative_balance_warning` są używane też w `ImportPreviewRow.vue`, więc zostają. `refund_label`, `income_conversion_label`, `available_limit_now`, `available_limit_after` i `expense_warnings_title` mają konsumentów tylko w tych 4 dialogach.
- Wzorce: stopka, blokada i `form?` w `CategoryFormDialog.vue:55-58,84-92,151-172,345-364`; błąd kwoty w `CategoryFormDialog.vue:77-82,274-303`; stany sinka w `DialogsKitchenSink.vue:18-87`.

## What We're NOT Doing

- Jednego dialogu „Nowa operacja” z wyborem typu (odrzucony w ADR-0039, decyzja 3 w `research.md`).
- Month Remaining przed → po (luka FR-TRX-08 zostaje). Ostrzeżenia Month Remaining zostają w obecnej postaci, tylko trafiają do bloku.
- Naprawy możliwego błędu roku w `hand-entry-month-remaining-preview.ts:435` (edycja przenosząca datę między latami). To poza UI: do zgłoszenia jako osobne issue.
- `AccountFormDialog`, `TermDepositFormDialog`.
- Salda konta docelowego transferu (konto docelowe tylko rośnie).
- Ostrzeżeń w podglądzie Importu: `Alert` i `bg-warning/10` zostają tam bez zmian.
- Warunku ostrzeżenia Do wydania (`CONTEXT.md:80`): bez zmian, więc bez wpisu w `CONTEXT.md`.
- Zmian reguł serwera (zero nadal odrzuca serwer), nazw pól payloadu, propsów Inertia i kodu PHP poza `lang/pl/ui.php`.
- Stanu „pole dotknięte” (komunikat dopiero po edycji pola).
- Nowej kopii kalkulatora w TS: tylko wystawienie wartości, które już są liczone.

## Implementation Approach

Najpierw decyzje w ADR-0041 i dokumentach oraz klasy w `app.css`. Potem odczyty w bibliotekach TS, z testami, zanim dotknie ich jakikolwiek widok. Potem wspólne elementy: blok podglądu, pomocnik wierszy z testami, propy aria w selectach, klucze `lang` i fixture'y sinka. Na końcu dialogi parami: najpierw wydatek i dochód, bo mają wszystkie trzy odczyty i wybór typu, potem transfer i ruch kapitału razem ze strażami, regułą i zrzutami.

## Critical Implementation Details

- **„Przed” to stan zapisany.** Limit „przed” to `plan − wykonanie` wybranej kategorii, bez korekty o edytowany rekord. Limit „po” i flaga przekroczenia nie zmieniają wartości. Saldo „przed” to `current_balance_minor_units` wybranego konta lub lokaty. Do wydania `before` już jest stanem zapisanym. Przypadek kontrolny: wydatek 100 zł, limit 1000 zł, realizacja 300 zł z tym wydatkiem, edycja bez zmian → „700,00 zł → 700,00 zł”.
- **Kiedy blok się pokazuje.** Blok renderuje się, gdy kwota jest poprawna i większa od 0 oraz istnieje co najmniej jeden wiersz albo ostrzeżenie. Wiersz pojawia się tylko wtedy, gdy jego odczyt jest znany:
  - saldo: wybrane konto (w ruchu kapitału także lokata);
  - limit: wybrana kategoria wydatkowa przy typie Wydatek lub Zwrot;
  - Do wydania: miesiąc z planem, po jednym wierszu na miesiąc, rosnąco (edycja przenosząca datę daje do 2 wierszy).
- **Dochód w dodawaniu.** Saldo „po” to saldo + kwota. `showNegativeBalanceWarning` dla dodania dochodu zostaje `false`, bo dochód nie powoduje ujemnego salda, nawet gdy saldo już jest ujemne.
- **Wybór typu bez `name` w wydatku i dochodzie.** Payload zostaje flagą `convert_to_income` / `convert_to_expense_refund` w ukrytym polu. Kierunek ruchu kapitału używa `name="direction"` zamiast osobnego ukrytego pola. To odstępstwo od zdania ADR-0040 „wybór z `name`” zapisuje ADR-0041.
- **Fixture'y względem dzisiejszej daty.** Dialog dodawania ustawia datę na dziś, a `EntryToSpendInputs` sięga do bieżącego miesiąca. Rok i miesiąc w fixture'ach liczy się więc z `new Date()`, inaczej wiersz Do wydania nigdy nie pojawi się w sinku.
- **Kwota bez `@before`.** Ukryte `amount_minor_units` wynika z wyliczonej wartości (znak z typu), więc `syncAmount` i `prepareSubmit` znikają razem z cichą zamianą na 0.

## Phase 1: Kontrakt w dokumentach i CSS

### Overview

ADR-0041 i strony systemu zapisują decyzje przed kodem. `app.css` dostaje nazwane klasy bloku podglądu, rzędu pól i tekstu tylko do odczytu.

### Changes Required:

#### 1. ADR-0041

**File**: `.docs/adr/0041-operation-dialogs-preview-before-after.md`

**Intent**: Zapisać kontrakt dialogów operacji i podglądu jako decyzję dla dialogów formularzy, w formacie i języku ADR-0040.

**Contract**: Treść ADR:

- Blok podglądu w dialogu formularza: wiersze „etykieta: przed → po”, czyli Do wydania w każdym dotkniętym miesiącu z planem, roczny limit kategorii oraz saldo konta lub lokaty (konto źródłowe w transferze).
- „Przed” to stan zapisany.
- Wartości kolorowane przez `amountToneClass` (odstępstwo od `foreground` w `Dialog/preview.html`, za `design-system.md:66`).
- Ostrzeżenia nieblokujące jako tekst `warning` w bloku, pod odczytem, którego dotyczą. Ostrzeżenia bez odczytu (Month Remaining) na końcu bloku. Zastępuje to `Alert warning` w dialogach formularzy, a Import i inne widoki bez zmian.
- Etykieta miesiąca w miejscowniku z rokiem.
- Kolejność pól z Atramentu i rząd „Kwota | Data”.
- Zła kwota: nieczytelna lub ujemna, a zero odrzuca serwer.
- Wybór typu jako `SegmentedControl` `block` `primary` bez `name`, gdy payload jest flagą konwersji.
- Opis z wyciągu jako blok tekstu, nie `textarea readonly`.

Odrzucone: `Alert` obok bloku, „przed” bez edytowanej operacji, etykieta w mianowniku, miejscownik bez roku, stan „pole dotknięte”, jeden dialog „Nowa operacja”.

#### 2. Strona systemu

**File**: `.docs/design-system.md`

**Intent**: Strona opisuje nowy przepis, żeby agent nie czytał zdania `:77` jako obowiązującego w dialogach.

**Contract**:

- Linia ADR w nagłówku z ADR-0041.
- `:77`: ostrzeżenia w dialogach formularzy stoją w bloku podglądu (ADR-0041), a podgląd Importu i pozostałe formularze zostają przy `Alert` `warning`.
- W „Gotowe elementy”: blok podglądu (komponent z Fazy 3), `.form-field-row`, `.form-readonly-text`.

#### 3. Reguła ostrzeżeń

**File**: `.ai/rules/js.md` (sekcja „Non-blocking warnings use the warning Alert variant”)

**Intent**: Reguła nie przeczy ADR-0041, zanim zmienią się dialogi. Boost MCP nie jest połączony, więc edycja idzie wprost do pliku (wiersz w `index.md` już istnieje).

**Contract**: Zdanie o wyjątku: w dialogach formularzy ostrzeżenia stoją w bloku podglądu jako tekst `warning` pod odczytem (ADR-0041), a nie w `Alert`.

#### 4. Klasy w `app.css`

**File**: `resources/css/app.css` (`@layer components`)

**Intent**: Wygląd bloku, rzędu i tekstu tylko do odczytu jako nazwane klasy, bez wartości arbitralnych w szablonach.

**Contract**:

- `.form-preview`: tło `background`, `radius-md`, padding 10/12, kolumna wierszy z odstępem.
- Elementy wiersza: etykieta 12/16 `muted-foreground`; wartość 600 `tabular-nums`; podpowiedź 12/16 `muted-foreground`; ostrzeżenie 12/16 `text-warning`.
- `.form-field-row`: dwie równe kolumny z odstępem pól, jedna kolumna przy oknie przeglądarki węższym niż 390 px.
- `.form-readonly-text`: tło `muted`, `radius-md`, padding pola, `text-sm`, `whitespace-pre-line`, łamanie długich słów.
- Bez nowych tokenów kolorów.

### Success Criteria:

#### Automated Verification:

- Test tokenów nadal zielony: `docker run --rm -u 1000:1000 -e HOME=/tmp --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Unit/DesignTokensTest.php`
- Formatowanie i statyka frontu: `npm run check`

#### Manual Verification:

- ADR-0041, `design-system.md` i `.ai/rules/js.md` opisują decyzje 1–4 z `research.md` oraz odpowiedzi z planowania („przed” = stan zapisany, miejscownik z rokiem, układ Atramentu, zła kwota nieczytelna lub ujemna) i nie przeczą sobie
- `text-warning` na tle `background` ma kontrast co najmniej 4,5:1 w obu motywach (tokeny z `app.css`)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Phase 2: Odczyty przed → po w bibliotekach

### Overview

Biblioteki podglądu wystawiają wartości „przed” i „po”, które już liczą. Import się nie zmienia, a `hand-entry-warning-preview.ts` dostaje pierwsze testy.

### Changes Required:

#### 1. Odczyty Do wydania

**File**: `resources/js/lib/entry-to-spend-warning.ts`

**Intent**: Dialog może pokazać Do wydania przed → po dla każdego dotkniętego miesiąca z planem bez drugiej kopii obliczeń.

**Contract**: Nowy eksport `ToSpendReading = { year: number; month: number; beforeMinorUnits: number; afterMinorUnits: number }` i `previewToSpendReadings(inputs, changes, pending = []): ToSpendReading[]`: odczyt dla każdego dotkniętego miesiąca z planem, rosnąco, z tym samym `before` i `after` co dziś. `previewToSpendWarnings` zachowuje sygnaturę i wynik i wyprowadza ostrzeżenia z odczytów (`after < 0 && after < before`).

#### 2. Testy odczytów Do wydania

**File**: `resources/js/lib/entry-to-spend-warning.test.ts`

**Intent**: Pilnować odczytów i tego, że ostrzeżenia się nie zmieniły.

**Contract**: Nowe przypadki:

- dodanie w miesiącu z planem → jeden odczyt;
- edycja bez zmian → `before === after`;
- edycja przenosząca datę z grudnia 2025 na styczeń 2026 → dwa odczyty w kolejności;
- miesiąc bez planu → brak odczytu.

Istniejące testy ostrzeżeń i `import-to-spend-warnings.test.ts` bez zmian i zielone.

#### 3. Salda i limit „przed/po”

**File**: `resources/js/lib/hand-entry-warning-preview.ts`

**Intent**: Dialog pokazuje saldo i limit przed → po z wartości już liczonych, z jednym znaczeniem „przed”.

**Contract**: `HandEntryPreviewResult` dostaje pola:

- `accountBalanceBeforeMinorUnits` i `accountBalanceAfterMinorUnits` (`number | null`): wydatek, zwrot i dochód (także dodanie) dla wybranego konta, transfer dla konta źródłowego, ruch kapitału dla konta;
- `termDepositBalanceBeforeMinorUnits` i `termDepositBalanceAfterMinorUnits` (tylko ruch kapitału);
- `availableLimitBeforeMinorUnits`: `plan − wykonanie` wybranej kategorii, bez korekty o rekord.

„Przed” to saldo zapisane. `availableLimitAfterMinorUnits` i wszystkie flagi zachowują dzisiejsze wartości. Przy dodaniu dochodu saldo „po” = saldo + kwota, a flaga zostaje `false`. `availableLimitNowMinorUnits` zostaje do Fazy 4, bo dziś czytają go dialogi.

#### 4. Pierwsze testy podglądu wpisu

**File**: `resources/js/lib/hand-entry-warning-preview.test.ts` (nowy)

**Intent**: Plik, z którego czytają 4 dialogi, ma testy przed zmianą widoków.

**Contract**: Przypadki:

- dodanie i edycja wydatku, zwrotu i dochodu, transfer (konto źródłowe), ruch kapitału w obu kierunkach;
- edycja bez zmian → przed = po dla salda i limitu (przypadek kontrolny 700 → 700);
- edycja ze zmianą konta → „przed” to saldo nowego konta;
- flagi przekroczenia limitu i ujemnego salda jak dziś;
- dodanie dochodu przy ujemnym saldzie → flaga `false`, saldo „po” = saldo + kwota.

### Success Criteria:

#### Automated Verification:

- Testy bibliotek: `npm test -- resources/js/lib/entry-to-spend-warning.test.ts resources/js/lib/import-to-spend-warnings.test.ts resources/js/lib/hand-entry-warning-preview.test.ts`
- Typy i statyka: `npm run types:check && npm run check`

**Implementation Note**: Po zielonej weryfikacji automatycznej przejdź dalej (faza bez kroków ręcznych).

---

## Phase 3: Wspólne elementy dialogów operacji

### Overview

Blok podglądu, pomocnik wierszy z testami, propy aria w selectach, klucze `lang` i fixture'y propsów są gotowe, zanim zmieni się pierwszy dialog.

### Changes Required:

#### 1. Blok podglądu

**File**: `resources/js/components/FormPreview.vue` (nowy)

**Intent**: Jedno źródło anatomii `ht-preview` dla 4 dialogów.

**Contract**:

- Propsy: `rows: FormPreviewRow[]` i `warnings?: string[]` (ostrzeżenia bez odczytu).
- Typ `FormPreviewRow = { key: string; label: string; beforeMinorUnits: number; afterMinorUnits: number; hint?: string; warnings: string[] }` eksportowany z pomocnika (pkt 2).
- Renderuje `.form-preview` tylko przy niepustych wierszach lub ostrzeżeniach, jako `role="group"` z `aria-label` `operations.preview_label`.
- Wartość: `formatMoney(before) → formatMoney(after)`, każda z kolorem `amountToneClass`. Strzałka `aria-hidden`, a obok niej ukryty tekst `operations.preview_after`.

#### 2. Pomocnik wierszy podglądu

**File**: `resources/js/lib/operation-dialog-preview.ts` (nowy) i `resources/js/lib/operation-dialog-preview.test.ts`

**Intent**: Etykiety, kolejność wierszy i przypięcie ostrzeżeń do wierszy są czystymi funkcjami z testem, bo dialogi testów nie mają.

**Contract**:

- Funkcje przyjmują odczyty z Fazy 2, flagi i teksty tłumaczeń i zwracają `FormPreviewRow[]` oraz ostrzeżenia bloku.
- Etykieta Do wydania: `operations.preview_to_spend` z `budget.month_name_locative_<n>` i rokiem („Do wydania w listopadzie 2026”).
- Kolejność wierszy: Do wydania (rosnąco po miesiącu), limit, saldo konta, saldo lokaty.
- Ostrzeżenie Do wydania pod wierszem swojego miesiąca, przekroczenie limitu pod limitem, ujemne saldo pod saldem, ujemna lokata pod lokatą. Month Remaining jako ostrzeżenia bloku.
- Test: etykieta grudnia 2025 i stycznia 2026, kolejność, przypięcie każdego ostrzeżenia, puste wejście daje pusty wynik.

#### 3. Propy aria w selectach

**File**: `resources/js/components/AccountSelect.vue`, `resources/js/components/CategorySelect.vue`

**Intent**: Select w `FormField` ogłasza błąd i podpowiedź.

**Contract**: `AccountSelect` dostaje `ariaDescribedby?: string` i `ariaInvalid?: 'true'`, a `CategorySelect` dostaje `ariaInvalid?: 'true'`. Oba trafiają na `SelectTrigger`. Bez zmian dla konsumentów, którzy ich nie podają (Import).

#### 4. Klucze tłumaczeń

**File**: `lang/pl/ui.php`

**Intent**: Teksty podglądu i błędu kwoty po polsku, w grupie `operations`.

**Contract**:

- `budget.month_name_locative_1` … `_12`: styczniu, lutym, marcu, kwietniu, maju, czerwcu, lipcu, sierpniu, wrześniu, październiku, listopadzie, grudniu.
- `operations.amount_invalid`: „Wpisz kwotę, np. 1200,50.”
- `operations.preview_label`: „Skutek operacji”.
- `operations.preview_after`: „po zapisie”.
- `operations.preview_to_spend`: „Do wydania w :month :year”.
- `operations.preview_category_limit`: „Roczny limit kategorii”.
- `operations.preview_account_balance`: „Saldo konta”.
- `operations.preview_source_account_balance`: „Saldo konta źródłowego”.
- `operations.preview_term_deposit_balance`: „Saldo lokaty”.
- `operations.to_spend_negative_preview_warning`: „Po tej operacji do wydania będzie poniżej zera.”

#### 5. Fixture'y propsów dialogów

**File**: `resources/js/lib/operation-dialogs-fixtures.ts` (nowy)

**Intent**: Sink renderuje każdy stan bez danych w bazie.

**Contract**: Eksporty typowane typami dialogów (`ExpenseFormAccount`, `ExpenseFormCategory`, `IncomeFormCategory`, `CapitalMovementFormTermDeposit` i pozostałe):

- konta (z saldem dodatnim i niskim);
- kategorie wydatkowe z planem i wykonaniem (jedna blisko limitu) i kategorie dochodowe;
- lokaty;
- `entryToSpend` z planem bieżącego miesiąca (Do wydania blisko zera);
- `handEntryMonthRemaining` dla miesiąca bez planu;
- operacje do edycji: wydatek, zwrot, dochód, transfer z `raw_statement_descriptions`, ruch kapitału.

Rok i miesiąc liczone z `new Date()`.

### Success Criteria:

#### Automated Verification:

- Literały w nowych i zmienionych plikach: `node scripts/check-ui-literals.mjs resources/js/components/FormPreview.vue resources/js/components/AccountSelect.vue resources/js/components/CategorySelect.vue`
- Testy frontu: `npm test`
- Typy i statyka: `npm run types:check && npm run check`

#### Manual Verification:

- Podgląd Importu: `AccountSelect` i `CategorySelect` w wierszach działają i wyglądają jak przed zmianą (`/dev/import-preview`)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Phase 4: Dialogi wydatku i dochodu

### Overview

`ExpenseFormDialog` i `IncomeFormDialog` w pełnym kontrakcie, z podglądem. Sink dostaje ich stany.

### Changes Required:

#### 1. `ExpenseFormDialog`

**File**: `resources/js/components/ExpenseFormDialog.vue`

**Intent**: Widok z audytu w kontrakcie ADR-0040 i ADR-0041.

**Contract**:

- **Typ.** Jeden stan `'expense' | 'refund' | 'income'` zamiast `isRefund`/`isIncome` i ich watcherów. `FormField` `group` „Typ” (`operations.type`) z `SegmentedControl` `block`. Opcje: Wydatek | Zwrot (`type_expense`, `type_refund`), a w edycji zwrotu także Dochód (`type_income`). Błąd z `errors.convert_to_income`. Ukryte `convert_to_income=1` jak dziś. Tytuł z typu.
- **Pola** w kolejności z Desired End State: rząd `.form-field-row` z kwotą (`FormField` `money`) i datą; kategoria z etykietą zależną od typu; konto; opis; kontrahent; opis z wyciągu jako `FormField` `group` z `.form-readonly-text`. Każda kontrolka wiąże `id`, `aria-describedby` i `aria-invalid` ze slotu. Formularz ma `grid gap-4`.
- **Kwota.** Wartość wyliczona z `parseMoneyInputToMinorUnits`, zła przy `null` lub `< 0`. Wtedy pole pokazuje `operations.amount_invalid` przed błędem serwera, a „Zapisz” jest wyłączony. Ukryte `amount_minor_units` ma znak z typu. Bez `@before`, `syncAmount` i `prepareSubmit`.
- **Podgląd.** `FormPreview` z pomocnika z Fazy 3: Do wydania z `previewToSpendReadings`, limit z `availableLimitBeforeMinorUnits`/`AfterMinorUnits` (z `available_limit_hint` jako podpowiedzią) przy typie Wydatek lub Zwrot, saldo konta. Ostrzeżenia przypięte jak w Fazie 3, a Month Remaining tylko w miesiącu bez planu (jak dziś). Bez `Alert`.
- **Stopka i zapis.** Wzorzec `CategoryFormDialog`: `busy` z `@start`/`@finish`, `onOpenChange` ignoruje zamknięcie przy `busy`, `:show-close-button="!busy"`, „Anuluj” `outline` `:disabled="processing"`, `Spinner` na „Zapisz”, „Zapisz” wyłączony przy `processing` lub złej kwocie.
- **Propy tylko dla sinka:** `form?: Pick<FormBinding, 'action' | 'method'>` i `initialAmountInput?: string`.
- Nazwy pól payloadu bez zmian.

#### 2. `IncomeFormDialog`

**File**: `resources/js/components/IncomeFormDialog.vue`

**Intent**: Drugi dialog finansowy w tym samym kontrakcie.

**Contract**: Jak w pkt 1, z różnicami:

- typ tylko w edycji: Dochód | Zwrot wydatku, błąd z `errors.convert_to_expense_refund`, ukryte `convert_to_expense_refund` jak dziś;
- kwota bez znaku;
- podgląd: Do wydania, saldo konta (także przy dodaniu) i limit kategorii wydatkowej przy typie Zwrot wydatku.

Propy `form?` i `initialAmountInput?`.

#### 3. Sprzątanie biblioteki i kluczy

**File**: `resources/js/lib/hand-entry-warning-preview.ts`, `resources/js/lib/hand-entry-warning-preview.test.ts`, `lang/pl/ui.php`

**Intent**: Bez martwego pola i kluczy.

**Contract**:

- `availableLimitNowMinorUnits` usunięte z `HandEntryPreviewResult` i testów (`grep -rn availableLimitNowMinorUnits resources/js` pusty).
- Z `operations` usunięte `refund_label`, `income_conversion_label`, `available_limit_now`, `available_limit_after`.

#### 4. Stany w sinku

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`

**Intent**: Stany obu dialogów do obejrzenia i zrzutów.

**Contract**: Stany z kluczem `?open=` i polską etykietą, propsy z `operation-dialogs-fixtures.ts`:

| Stan | Co pokazuje |
| --- | --- |
| `expense-new` | dodanie |
| `expense-edit` | podgląd z równymi wartościami |
| `expense-refund-edit` | trzy opcje typu |
| `expense-warnings` | edycja z `initialAmountInput` przekraczającym limit, saldo i Do wydania |
| `expense-month-without-plan` | ostrzeżenia Month Remaining |
| `expense-bad-amount` | `initialAmountInput` „abc” |
| `expense-errors` | `form` na `validate`; zapis w toku 1,5 s, potem błędy, także pod typem |
| `income-new` | dodanie |
| `income-edit` | Dochód \| Zwrot wydatku |
| `income-errors` | `form` na `validate` |

### Success Criteria:

#### Automated Verification:

- Testy Feature (payload bez zmian): `docker run --rm -u 1000:1000 -e HOME=/tmp --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature/ExpenseTest.php tests/Feature/IncomeTest.php tests/Feature/FinancialOperationReclassifyTest.php tests/Feature/FinancialOperationEditDeleteTest.php`
- Literały: `node scripts/check-ui-literals.mjs resources/js/components/ExpenseFormDialog.vue resources/js/components/IncomeFormDialog.vue resources/js/pages/dev/DialogsKitchenSink.vue`
- Brak martwego pola: `grep -rn availableLimitNowMinorUnits resources/js` nic nie zwraca
- Typy i statyka: `npm run build && npm run types:check && npm run check`
- Testy frontu: `npm test`

#### Manual Verification:

- W podglądzie z worktree (AGENTS.md „Preview from a worktree”): dodanie wydatku, zwrotu i dochodu, edycja zwrotu na dochód i dochodu na zwrot zapisują dane jak wcześniej
- Edycja bez zmian pokazuje równe wartości „przed → po” we wszystkich wierszach. Przeniesienie daty do poprzedniego miesiąca z planem daje dwa wiersze Do wydania z etykietami w miejscowniku i z rokiem
- „abc”, „12,345” i „-5” w kwocie dają komunikat pod polem, `aria-invalid` i wyłączony „Zapisz”. „0,00” daje błąd serwera
- W trakcie zapisu (`expense-errors`, `income-errors`): spinner, X ukryty, `Esc`, nakładka i „Anuluj” nie zamykają
- Stany sinka w obu motywach przy 1280 i 390 px: rząd „Kwota | Data” w dwóch kolumnach przy 390 px, ostrzeżenia w bloku pod swoim wierszem, wartość ujemna w `destructive`
- Klawiatura: fokus po otwarciu na pierwszym polu (wybór typu albo kwota), wybór typu strzałkami, czytnik ekranu ogłasza błąd kwoty

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Phase 5: Transfer i ruch kapitału, straże i zrzuty

### Overview

`OwnTransferFormDialog` i `CapitalMovementFormDialog` w kontrakcie. Straże pilnują 4 dialogów, reguła opisuje podgląd, zrzuty zamykają bramkę `/10x-ui`.

### Changes Required:

#### 1. `OwnTransferFormDialog`

**File**: `resources/js/components/OwnTransferFormDialog.vue`

**Intent**: Transfer w tym samym kontrakcie.

**Contract**:

- Pola: rząd „Kwota | Data” → Z konta → Na konto → Opis → Opis z wyciągu (`raw_statement_descriptions` złączone pustą linią w `.form-readonly-text`).
- `FormPreview` z saldem konta źródłowego i ostrzeżeniem o ujemnym saldzie pod nim.
- Kwota zła przy `null` lub `< 0`, z `operations.amount_invalid`.
- Stopka i zapis jak w Fazie 4, etykiety `operations.cancel` / `operations.save`.
- Propy `form?` i `initialAmountInput?`.
- Payload bez zmian.

#### 2. `CapitalMovementFormDialog`

**File**: `resources/js/components/CapitalMovementFormDialog.vue`

**Intent**: Ruch kapitału w tym samym kontrakcie.

**Contract**:

- Kierunek: `FormField` `group` z `SegmentedControl` `block` `name="direction"` (opcje `capital_movement_to_term_deposit` / `capital_movement_from_term_deposit`) zamiast reka `Select` i ukrytego pola.
- Lokata: `.native-select` z `name="term_deposit_id"`, pustą opcją z etykietą `operations.term_deposit` i opcjami `termDeposit.name`.
- Kolejność: Kierunek → „Kwota | Data” → Konto → Lokata → Opis.
- `FormPreview` z saldem konta (ostrzeżenie `operations.negative_balance_warning`) i saldem lokaty (`term_deposits.negative_balance_warning`).
- Kwota, stopka, etykiety i propy jak w pkt 1.
- Payload bez zmian.

#### 3. Klucze

**File**: `lang/pl/ui.php`

**Intent**: Bez martwego klucza.

**Contract**: `operations.expense_warnings_title` usunięty (po tej fazie bez konsumentów).

#### 4. Stany w sinku

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`

**Intent**: Stany obu dialogów do obejrzenia i zrzutów.

**Contract**:

| Stan | Co pokazuje |
| --- | --- |
| `transfer-new` | dodanie |
| `transfer-edit` | opis z wyciągu z dwóch wierszy |
| `transfer-negative` | `initialAmountInput` ponad saldo |
| `transfer-errors` | `form` na `validate` |
| `capital-new` | dodanie |
| `capital-edit` | edycja |
| `capital-negative-deposit` | Lokata → konto ponad saldo lokaty |
| `capital-errors` | `form` na `validate` |

#### 5. Straż literałów

**File**: `scripts/check-ui-literals.mjs`

**Intent**: Pliki oczyszczone w tej zmianie są pilnowane w CI.

**Contract**: Lista `FILES` zawiera 4 dialogi, `FormPreview.vue`, `AccountSelect.vue` i `CategorySelect.vue`.

#### 6. Test kontraktu UI

**File**: `resources/js/ui-contract.test.ts`

**Intent**: Pilnować ADR-0041 i braku literałów w dialogach operacji.

**Contract**:

- Nowa lista plików dialogów operacji skanowana regexem `HARDCODED`.
- Nowy test: żaden z 4 dialogów nie importuje `@/components/ui/alert` i nie zawiera `type="checkbox"`. Komunikat wskazuje `FormPreview` i `SegmentedControl`.

#### 7. Reguła dla agentów

**File**: `.ai/rules/js.md` (sekcja „Form dialogs are built on FormField and the ADR-0040 contract”)

**Intent**: Następny dialog z odczytem powstaje na bloku podglądu.

**Contract**: Dopisek:

- odczyt przed zapisem to `FormPreview` z wierszami z `lib/operation-dialog-preview.ts`;
- „przed” to stan zapisany;
- ostrzeżenia w bloku pod odczytem (ADR-0041);
- rząd pól `.form-field-row`, tekst tylko do odczytu `.form-readonly-text`;
- zła kwota operacji: nieczytelna lub ujemna;
- stany dialogów operacji w `/dev/ui/dialogs`.

Zgodny z `design-system.md`.

#### 8. Zrzuty

**File**: `context/changes/operation-dialogs-atrament/screenshots/`

**Intent**: Bramka zrzutu `/10x-ui`.

**Contract**:

- Każdy stan operacji z `/dev/ui/dialogs?open=<stan>` w obu motywach przy 1280 i 390 px, w plikach `<stan>-<light|dark>-<1280|390>.png`.
- Strona Operacji z otwartym dialogiem wydatku.
- Jednorazowy Playwright z cache `~/.cache/ms-playwright`, bez dodawania zależności (przepis `context/changes/category-dialog-atrament/plan.md:364`).

### Success Criteria:

#### Automated Verification:

- Testy Feature (payload bez zmian): `docker run --rm -u 1000:1000 -e HOME=/tmp --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature/OwnTransferTest.php tests/Feature/CapitalMovementTest.php`
- Straż literałów: `npm run check:ui`
- Test kontraktu UI: `npm test -- resources/js/ui-contract.test.ts`
- Pełna bramka CI na PostgreSQL: `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint composer sail-8.5/app ci:check`

#### Manual Verification:

- W podglądzie z worktree: dodanie i edycja transferu i ruchu kapitału (oba kierunki) zapisują dane jak wcześniej. Lokata wybierana klawiaturą w `.native-select`
- Zapis w toku i błędy w `transfer-errors` i `capital-errors`: spinner, blokada zamykania, błąd pod kierunkiem
- Zrzuty obejrzane: wszystkie stany operacji w obu motywach i obu szerokościach zgodne z Atramentem (Dialog, Field) i z ADR-0041
- Reguła w `.ai/rules/js.md` zgodna z `design-system.md`, ADR-0040 i ADR-0041

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `entry-to-spend-warning.test.ts`: odczyty per miesiąc, edycja bez zmian, przejście roku, miesiąc bez planu; ostrzeżenia bez zmian.
- `hand-entry-warning-preview.test.ts` (nowy): salda i limit przed → po dla każdego typu, „przed” = stan zapisany, flagi jak dziś, dodanie dochodu przy ujemnym saldzie.
- `operation-dialog-preview.test.ts` (nowy): etykieta w miejscowniku z rokiem, kolejność wierszy, przypięcie ostrzeżeń.
- `ui-contract.test.ts`: literały i brak `Alert` oraz checkboxów w 4 dialogach.

### Integration Tests:

- `ExpenseTest`, `IncomeTest`, `FinancialOperationReclassifyTest`, `FinancialOperationEditDeleteTest`, `OwnTransferTest`, `CapitalMovementTest`: payload dialogów bez zmian.
- `DialogsKitchenSinkTest`: bez zmian (trasy i kontroler sinka się nie zmieniają).

### Manual Testing Steps:

1. Otworzyć `/dev/ui/dialogs` i przejść stany operacji w obu motywach przy 1280 i 390 px.
2. W podglądzie z worktree dodać i edytować każdy typ operacji, w tym konwersje zwrot ↔ dochód, i sprawdzić zapisane dane.
3. W edycji wydatku przenieść datę do poprzedniego miesiąca z planem i sprawdzić dwa wiersze Do wydania.
4. Wpisać „abc”, „12,345”, „-5” i „0,00” w kwotę każdego dialogu.
5. Przejść dialog wydatku i ruchu kapitału klawiaturą i czytnikiem ekranu (albo drzewem dostępności).

## Performance Considerations

Brak nowych zapytań ani propsów. Podgląd liczy się po stronie klienta z danych, które już są w propsach, tymi samymi funkcjami co dziś.

## Migration Notes

Brak migracji danych i zmian payloadu. Usunięte klucze `lang` nie mają innych konsumentów (sprawdzone grepem po `resources/js`, `app`, `tests`).

## References

- Research: `context/changes/operation-dialogs-atrament/research.md`
- Atrament: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (`project/components/Dialog/README.md`, `Dialog/preview.html`, `Field/README.md`, `bundle.css` `.ht-preview*`, `.ht-field-row`)
- ADR-0040: `.docs/adr/0040-dialog-shell-form-fields-and-focus-follow-atrament.md`
- Wzorzec dialogu formularza: `resources/js/components/CategoryFormDialog.vue:55-58,77-92,151-172,274-303,345-364`
- Wzorzec sinka: `resources/js/pages/dev/DialogsKitchenSink.vue:18-87`, `app/Http/Controllers/Dev/DialogsKitchenSinkController.php:24-43`
- Poprzednia zmiana: `context/changes/category-dialog-atrament/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kontrakt w dokumentach i CSS

#### Automated

- [x] 1.1 Test tokenów nadal zielony: `docker run --rm -u 1000:1000 -e HOME=/tmp --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Unit/DesignTokensTest.php` — d1e2bc9
- [x] 1.2 Formatowanie i statyka frontu: `npm run check` — d1e2bc9

#### Manual

- [x] 1.3 ADR-0041, `design-system.md` i `.ai/rules/js.md` opisują decyzje 1–4 z `research.md` oraz odpowiedzi z planowania („przed” = stan zapisany, miejscownik z rokiem, układ Atramentu, zła kwota nieczytelna lub ujemna) i nie przeczą sobie — d1e2bc9
- [x] 1.4 `text-warning` na tle `background` ma kontrast co najmniej 4,5:1 w obu motywach (tokeny z `app.css`) — d1e2bc9

### Phase 2: Odczyty przed → po w bibliotekach

#### Automated

- [x] 2.1 Testy bibliotek: `npm test -- resources/js/lib/entry-to-spend-warning.test.ts resources/js/lib/import-to-spend-warnings.test.ts resources/js/lib/hand-entry-warning-preview.test.ts` — 5e3dd5c
- [x] 2.2 Typy i statyka: `npm run types:check && npm run check` — 5e3dd5c

### Phase 3: Wspólne elementy dialogów operacji

#### Automated

- [x] 3.1 Literały w nowych i zmienionych plikach: `node scripts/check-ui-literals.mjs resources/js/components/FormPreview.vue resources/js/components/AccountSelect.vue resources/js/components/CategorySelect.vue`
- [x] 3.2 Testy frontu: `npm test`
- [x] 3.3 Typy i statyka: `npm run types:check && npm run check`

#### Manual

- [x] 3.4 Podgląd Importu: `AccountSelect` i `CategorySelect` w wierszach działają i wyglądają jak przed zmianą (`/dev/import-preview`)

### Phase 4: Dialogi wydatku i dochodu

#### Automated

- [ ] 4.1 Testy Feature (payload bez zmian): `docker run --rm -u 1000:1000 -e HOME=/tmp --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature/ExpenseTest.php tests/Feature/IncomeTest.php tests/Feature/FinancialOperationReclassifyTest.php tests/Feature/FinancialOperationEditDeleteTest.php`
- [ ] 4.2 Literały: `node scripts/check-ui-literals.mjs resources/js/components/ExpenseFormDialog.vue resources/js/components/IncomeFormDialog.vue resources/js/pages/dev/DialogsKitchenSink.vue`
- [ ] 4.3 Brak martwego pola: `grep -rn availableLimitNowMinorUnits resources/js` nic nie zwraca
- [ ] 4.4 Typy i statyka: `npm run build && npm run types:check && npm run check`
- [ ] 4.5 Testy frontu: `npm test`

#### Manual

- [ ] 4.6 W podglądzie z worktree (AGENTS.md „Preview from a worktree”): dodanie wydatku, zwrotu i dochodu, edycja zwrotu na dochód i dochodu na zwrot zapisują dane jak wcześniej
- [ ] 4.7 Edycja bez zmian pokazuje równe wartości „przed → po” we wszystkich wierszach. Przeniesienie daty do poprzedniego miesiąca z planem daje dwa wiersze Do wydania z etykietami w miejscowniku i z rokiem
- [ ] 4.8 „abc”, „12,345” i „-5” w kwocie dają komunikat pod polem, `aria-invalid` i wyłączony „Zapisz”. „0,00” daje błąd serwera
- [ ] 4.9 W trakcie zapisu (`expense-errors`, `income-errors`): spinner, X ukryty, `Esc`, nakładka i „Anuluj” nie zamykają
- [ ] 4.10 Stany sinka w obu motywach przy 1280 i 390 px: rząd „Kwota | Data” w dwóch kolumnach przy 390 px, ostrzeżenia w bloku pod swoim wierszem, wartość ujemna w `destructive`
- [ ] 4.11 Klawiatura: fokus po otwarciu na pierwszym polu (wybór typu albo kwota), wybór typu strzałkami, czytnik ekranu ogłasza błąd kwoty

### Phase 5: Transfer i ruch kapitału, straże i zrzuty

#### Automated

- [ ] 5.1 Testy Feature (payload bez zmian): `docker run --rm -u 1000:1000 -e HOME=/tmp --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature/OwnTransferTest.php tests/Feature/CapitalMovementTest.php`
- [ ] 5.2 Straż literałów: `npm run check:ui`
- [ ] 5.3 Test kontraktu UI: `npm test -- resources/js/ui-contract.test.ts`
- [ ] 5.4 Pełna bramka CI na PostgreSQL: `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint composer sail-8.5/app ci:check`

#### Manual

- [ ] 5.5 W podglądzie z worktree: dodanie i edycja transferu i ruchu kapitału (oba kierunki) zapisują dane jak wcześniej. Lokata wybierana klawiaturą w `.native-select`
- [ ] 5.6 Zapis w toku i błędy w `transfer-errors` i `capital-errors`: spinner, blokada zamykania, błąd pod kierunkiem
- [ ] 5.7 Zrzuty obejrzane: wszystkie stany operacji w obu motywach i obu szerokościach zgodne z Atramentem (Dialog, Field) i z ADR-0041
- [ ] 5.8 Reguła w `.ai/rules/js.md` zgodna z `design-system.md`, ADR-0040 i ADR-0041
