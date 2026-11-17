# Integralność Importu i bramka trafności podpowiedzi — plan implementacji

## Overview

Etap 1 planu testów (`context/foundation/test-plan.md` §3) ma dowieść czterech rzeczy, testami na PostgreSQL ze stanem bazy (liczba operacji, salda kont, wykonanie, pozycje budżetu), a nie samą odpowiedzią HTTP:

- zatwierdzenie Importu nie dubluje operacji;
- nie gubi wierszy przy zmianie układu wyciągu;
- błąd jednego wiersza wycofuje cały zestaw;
- trafność podpowiedzi kategorii nie spada.

Ostatnia gwarancja dostaje bramkę: stały, pseudonimizowany zestaw referencyjny z Twojej historii Importu i poziom bazowy zapisany w repo.

Jedyna zmiana kodu produkcyjnego to reguła `distinct` dla `rows.*.index`, czyli luka A z research. Luki B (wiersz znika po zmianie formatu komórki) i C (BNP ignoruje `Waluta`) trafiają na GitHuba jako issue.

## Current State Analysis

Pełne ustalenia: `context/changes/testing-import-integrity/research.md`. Najważniejsze dla planu:

- **Atomowość jest w kodzie, ale testy jej nie pokazują.**
  - Wszystkie wiersze zapisuje jedna `DB::transaction` (`app/Actions/ImportFinancialOperations.php:78`), a w sprawdzonej ścieżce żaden `catch` nie połyka błędu wiersza.
  - Testy wycofania (`tests/Feature/CsvImportTest.php:632`, `:1041`) liczą tylko rekordy. Nie sprawdzają sald, wykonania, dołączonych pozycji budżetu ani przywrócenia rozbitej pary (`ConvertImportPairFinancialOperation`).
- **Duplikaty to ostrzeżenie, nie ograniczenie.**
  - Tożsamość to SHA-256 z (user, account, data z pliku, kwota, surowy tekst) (`app/Support/CsvImport/ImportIdentityDigest.php:239-245`), na zwykłych, nieunikalnych indeksach.
  - Kontrola przy zatwierdzeniu porównuje tylko z zapisanymi rekordami i działa przed transakcją (`app/Http/Controllers/CsvImportController.php:321-338`).
  - `rows.*.index` nie ma `distinct` (`app/Http/Requests/StoreCsvImportRequest.php:23`), więc powtórzony indeks tworzy dwie operacje z jednego wiersza pliku.
  - Test „bez potwierdzenia” (`CsvImportTest.php:735`) sprawdza tylko błąd sesji. Liczby rekordów i salda nie sprawdza.
- **Gubienie wierszy jest zgodne z FR-IMP-08** (`context/foundation/prd-mvp.md:255`). `isOperationShapedRow` (`CsvImportController.php:472-479`) pomija wiersz z nieparsowalną datą albo kwotą i nic tego nie liczy.
  - Zmiana nagłówka daje głośny błąd `file`.
  - Zmiana formatu komórki gubi wiersz bez śladu (luka B).
  - Wyrocznią „kompletu wierszy” może być więc tylko liczba policzona ręcznie z pliku.
- **Fixture.**
  - Prawdziwy układ pokazują tylko `tests/Fixtures/mbank-basic.csv` (UTF-8 z BOM, preambuła 25 linii, nagłówek w linii 26, 7 wierszy) i `tests/Fixtures/bnp-paribas-basic.xlsx` (1 arkusz, 13 kolumn, 7 wierszy, w tym 2 blokady bez daty księgowania).
  - Pozostałe CSV w testach są syntetyczne.
- **Trafność podpowiedzi.**
  - `ImportCategorySuggestionAccuracy::measure(User, int $year)` (`app/Support/CsvImport/ImportCategorySuggestionAccuracy.php:62-86`) robi chronologiczny replay historii użytkownika.
  - `differences($before, $after)` (`:99-139`) czerwieni każdy spadek odsetka z podpowiedzią albo trafnych wśród podpowiedzianych, w okresach `year` i `aug_nov`, na poziomach `model` i `suggestion`. Wymaga przy tym tych samych `operation_id`.
  - Model widzi tylko tokeny `[A-Z]{3,}` po normalizacji (`ImportCategorySuggestionModel::tokens`, `app/Support/CsvImport/ImportCategorySuggestionModel.php:99-116`). Ma publiczne `fromHistory`, `rank` i `suggest`.
  - Żaden test nie sprawdza progu, a S-23 świadomie go pominął, żeby tekst z banku nie trafił do repo (`context/archive/2026-11-12-import-suggestions-on-category-list/plan.md:42`).
- **Dane do zestawu.** Lokalny snapshot `storage/app/private/user-snapshots/1-20261112070312.json` w głównym checkoutcie (v10, gitignored) ma 1033 operacje, z czego 1031 z Importu, wszystkie z 2026.
- **CI uruchamia cały zestaw testów na PostgreSQL 18.** `.github/workflows/tests.yml:37` ustawia `DB_CONNECTION=pgsql`, a `<env>` w `phpunit.xml:32-33` nie ma `force`. Nowe testy w `tests/Feature` same wchodzą do bramki `composer ci:check`.

## Desired End State

- W `tests/Feature/ImportIntegrityTest.php` działają testy na PostgreSQL dla atomowości, duplikatów i formatów. Każdy sprawdza stan bazy przed i po. Wyroczniami są wartości policzone ręcznie z pliku albo saldo pochodne (`App\Support\AccountBalanceOnDate`), nie ten sam kod.
- Powtórzony `rows.*.index` odpada na walidacji: 0 operacji, saldo bez zmian.
- `tests/Fixtures/suggestions/reference-2026.json` zawiera 1031 pseudonimizowanych wierszy bez tekstu z banku i bez kwot. Plik bazowy obok zawiera wynik.
  - `tests/Feature/ImportSuggestionAccuracyBenchmarkTest.php` czerwieni każdy spadek i wypisuje różniące się wiersze.
  - Poprawa wyniku przechodzi. Bazę podnosi się świadomie, zmienną `SUGGESTION_BASELINE_WRITE=1`.
- Issue B i C są założone na GitHubie.
- Twoje świeże eksporty mBank i BNP, zanonimizowane i przejrzane przez Ciebie, są fixture z ręcznie policzoną liczbą wierszy i nakładką okresów.
- `test-plan.md` ma przeniesione korekty z research: §2 #1 i #4, §3, §5, §6.2, §6.6 i §7.

Weryfikacja: `composer ci:check` na PostgreSQL w kontenerze (worktree), `vendor/bin/sloppy ci` i `pint` są zielone. Każdy nowy test, na którym opiera się dowód, czerwienieje przy celowym zepsuciu kodu, które w nim opisano.

### Key Discoveries:

- Najbardziej naturalny wiersz, który przechodzi podgląd i pada przy zatwierdzeniu, ma datę w roku bez Budżetu w wykonaniu albo w roku wczytanym z arkusza. Podgląd tego nie sprawdza (`app/Support/CsvImport/CsvImportPreviewValidator.php:18-50`), a zatwierdzenie odrzuca (`app/Support/CategoryList/OperationCategoryChoice.php:42-46,72-80`). To wyciąg przez Nowy Rok.
- Kategoria listy bez pozycji w roku operacji dołącza do Budżetu w czasie zapisu (`ManageFinancialOperation::lockLine`, `app/Actions/ManageFinancialOperation.php:222-253`). Wycofanie musi usunąć tę nową pozycję, więc liczba `budget_lines` jest mocną asercją.
- Wyrocznia salda: `AccountBalanceOnDate::for(Account, CarbonInterface): ?int` (`app/Support/AccountBalanceOnDate.php:17`) liczy saldo z salda otwarcia i ruchów, niezależnie od zapisanego `current_balance_minor_units`.
- Benchmark nie potrzebuje nowej logiki porównania. Jeśli `operation_id` w pliku bazowym i w pomiarze zastąpi klucz wiersza fixture, `differences()` stosuje dokładnie regułę „zero spadku” z S-23.
- Lata łączy id kategorii listy, nigdy nazwa (`.ai/rules/categories.md`). Zestaw referencyjny trzyma więc klucz kategorii, a nazwa służy tylko do czytelności.
- `lang/pl/validation.php` nie ma komunikatu `distinct`.
- Testy Importu to klasy PHPUnit z prywatnymi helperami (`CsvImportTest.php:4171-4206`, `createImportFixture`, `previewCsv`). Ten plik ma 4356 linii, więc nowe testy idą do osobnych plików z własnymi małymi helperami.

## What We're NOT Doing

- Nie naprawiamy luki B (wiersz znika przy `+12,34`, kropce tysięcy, U+202F, Enterze w cudzysłowie, innym formacie daty, `0,00` w pustej kolumnie formatu Inny) ani nie zmieniamy FR-IMP-08 (licznik pominiętych wierszy). Idzie to jako issue na osobną zmianę z PRD i ADR.
- Nie naprawiamy luki C (BNP z `Waluta ≠ PLN` wchodzi jako PLN). Idzie to jako issue.
- Nie zamykamy wyścigu równoczesnego podwójnego zatwierdzenia (`->block()`, idempotencja). Trafia do §7 jako zaakceptowane ryzyko. Test pokrywa tylko ponowne wysłanie po sukcesie.
- Nie zmieniamy dopasowania tożsamości z ADR-0007: inne mapowanie tego samego wyciągu nie jest duplikatem. Nie piszemy też testu, który by tę granicę utrwalał.
- Nie dodajemy unikalnego ograniczenia na `import_identity_digest`. Byłoby sprzeczne z ADR-0007: świadomy ponowny Import i wspólny digest ruchu kapitału z odsetkami.
- Nie zmieniamy `ImportSuggestionAccuracyCommand` ani pomiaru na produkcji. Nie poprawiamy kodu podpowiadacza.
- Nie poprawiamy nieaktualnych odwołań do S-14, S-16 i „snapshot v6” w ryzykach #5 i #6 oraz w etapie 3 planu testów. To należy do `/10x-test-plan --refresh`.
- Nie rozbijamy ani nie refaktoryzujemy `tests/Feature/CsvImportTest.php`.

## Implementation Approach

Fazy idą od najtańszego dowodu z największym sygnałem do najdroższego. Fazy 1–3 to testy Feature na zatwierdzeniu Importu. W fazie 2 jest jedyna zmiana produkcyjna, napisana po czerwonym teście. Faza 4 buduje bramkę trafności. Faza 5 czeka na Twoje pliki i nie blokuje pozostałych. Faza 6 przenosi korekty z research do planu testów.

Każda asercja sprawdza stan bazy, a oczekiwane wartości pochodzą z ręcznego liczenia albo z niezależnego odczytu, nigdy z kodu, który testujemy. Fixture powstają z prawdziwych plików przez ręczne edycje, nie z wyjścia parsera.

Polecenie testów PostgreSQL w tym worktree (dalej: `PGTEST <ścieżka>`). Baza `testing_testing_import_integrity` powstaje raz: `docker exec household-treasurer-pgsql-1 psql -U sail -d postgres -c 'create database testing_testing_import_integrity'`.

```sh
docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql \
  -e DB_DATABASE=testing_testing_import_integrity -e DB_USERNAME=sail -e DB_PASSWORD=password \
  --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html \
  --entrypoint php sail-8.5/app artisan test --compact <ścieżka>
```

Pint i Sloppy uruchamiamy w obrazie `sail-8.5/app` z podanymi ścieżkami plików (host ma PHP 8.3).

## Critical Implementation Details

- **Kolejność wierszy w teście wycofania.** Wiersz, który ma paść, musi być przetwarzany po wierszu, który już coś zapisał: operację, dołączoną pozycję budżetu, rozbitą parę. W pliku i w żądaniu zatwierdzenia wiersz poprawny stoi przed wierszem padającym. Bez tego test wycofania niczego nie dowodzi.
- **Stała data testów.** Podgląd odrzuca daty z przyszłości. Każdy test ustawia `now()` jawnie (np. 2026-03-31) i używa jawnych dat w plikach.
- **Klucze zamiast id w benchmarku.** Sekwencje PostgreSQL nie cofają się przy `RefreshDatabase`, więc id operacji zmieniają się między przebiegami. Plik bazowy i pomiar porównujemy wyłącznie po kluczu wiersza fixture.

## Faza 1: Atomowość i salda

### Overview

Dowieść, że błąd jednego wybranego wiersza wycofuje cały zestaw razem ze skutkami w pieniądzach (saldo, wykonanie, dołączona pozycja budżetu, rozbita para), że podgląd zostaje w sesji do ponowienia, i że zapisane saldo zgadza się z saldem pochodnym.

### Changes Required:

#### 1. Plik testów integralności

**File**: `tests/Feature/ImportIntegrityTest.php` (nowy, `php artisan make:test --pest ImportIntegrityTest --no-interaction`)

**Intent**: Wspólne miejsce na testy fazy 1–3 i 5. Małe helpery: konto z saldem otwarcia, Budżet 2026 w wykonaniu, kategoria listy bez pozycji 2026, podgląd pliku, zatwierdzenie wybranych wierszy oraz migawka stanu (liczności `financial_operations`, `own_transfers`, `capital_movements`, `budget_lines`, saldo każdego konta, wykonanie każdej pozycji).

**Contract**: Pest z `RefreshDatabase`, jak w testach Feature w repo. Asercja „stan bez zmian” porównuje całą migawkę sprzed i po zatwierdzeniu. Nie importuje helperów z `CsvImportTest.php`.

#### 2. Wyciąg przez Nowy Rok wycofuje całość

**File**: `tests/Feature/ImportIntegrityTest.php`

**Intent**: Konto otwarte 2025-12-01, Budżet 2026 w wykonaniu, brak Budżetu 2025. Plik formatu Inny:
- wiersz 0: 2026-01-05, −30,00, kategoria listy bez pozycji 2026, która dołączy przy zapisie;
- wiersz 1: 2025-12-30, −50,00.

Warunek wstępny: podgląd pokazuje oba wiersze jako poprawne. Zatwierdzenie obu daje błąd `rows`, migawka jest bez zmian (0 operacji, saldo, wykonanie, brak nowej pozycji 2026) i sesja `csv_import` wciąż istnieje. Ponowienie z samym wierszem 0 zapisuje dokładnie 1 operację: saldo −30,00, nowa pozycja z wykonaniem 30,00.

**Contract**: Wyrocznia to wartości policzone ręcznie z dwóch kwot pliku.

#### 3. Wycofanie przywraca rozbitą parę

**File**: `tests/Feature/ImportIntegrityTest.php`

**Intent**: Na koncie A jest operacja z Importu, która jest pierwszą nogą pary (układ jak w `CsvImportTest.php:3109`). Podgląd na koncie B:
- wiersz 0: kandydat pary, zatwierdzany jako przelew własny z `pair_first_leg_kind=financial_operation`;
- wiersz 1: wydatek z kategorią przychodu (zły typ).

Po zatwierdzeniu: błąd `rows`, operacja pierwszej nogi istnieje z tym samym digestem, `own_transfers` = 0, salda A i B oraz wykonanie bez zmian.

**Contract**: Migawka obejmuje oba konta i pozycję budżetu pierwszej nogi.

#### 4. Zapisane saldo równa się pochodnemu

**File**: `tests/Feature/ImportIntegrityTest.php`

**Intent**: Mieszane zatwierdzenie: przychód, wydatek, zwrot, przelew własny na konto B, ruch kapitału z odsetkami na lokatę. Po nim, a także po wycofanym zatwierdzeniu z punktu 2, dla każdego dotkniętego konta `current_balance_minor_units === AccountBalanceOnDate::for($account, now())`. Saldo lokaty porównujemy z wartością policzoną ręcznie.

**Contract**: Niezależna wyrocznia salda; brak asercji „odpowiedź HTTP = sukces” jako jedynego dowodu.

### Success Criteria:

#### Automated Verification:

- Testy fazy przechodzą na PostgreSQL: `PGTEST tests/Feature/ImportIntegrityTest.php`
- Celowe zepsucie: zastąpienie `DB::transaction(...)` w `ImportFinancialOperations::handle` bezpośrednim wywołaniem zamyka czerwienią testy z punktów 2 i 3; po cofnięciu zepsucia testy są znów zielone
- `pint` na nowym pliku testów nie zgłasza zmian

---

## Faza 2: Duplikaty

### Overview

Dowieść, że bez świadomego potwierdzenia ponowny Import ani powtórne wysłanie nie tworzą nowej operacji, oraz zamknąć lukę A (powtórzony `index`) w kolejności czerwony test → `distinct` → zielony.

### Changes Required:

#### 1. Ponowne wysłanie po sukcesie

**File**: `tests/Feature/ImportIntegrityTest.php`

**Intent**: Po udanym zatwierdzeniu to samo żądanie POST, bez nowego podglądu, przekierowuje do `operations.import.create`. Liczba operacji i saldo zmieniły się dokładnie raz.

**Contract**: Migawka po pierwszym zatwierdzeniu = migawka po drugim.

#### 2. Brak potwierdzenia nic nie zapisuje

**File**: `tests/Feature/ImportIntegrityTest.php`

**Intent**: Plik zaimportowany raz. Ponowny podgląd tego samego pliku i zatwierdzenie wiersza oznaczonego jako duplikat bez `duplicate_warning_acknowledged` daje błąd tego pola, a migawka jest bez zmian (wciąż 1 operacja, saldo bez zmian).

**Contract**: Uzupełnia `CsvImportTest.php:735`, który sprawdza tylko błąd sesji.

#### 3. Dwa identyczne wiersze w jednym pliku

**File**: `tests/Feature/ImportIntegrityTest.php`

**Intent**: Plik z dwoma wierszami `2026-03-15, Kawa, −12,00`.
- Pierwsze zatwierdzenie obu daje 2 operacje z tym samym digestem i saldo −24,00.
- Ponowny podgląd oznacza oba wiersze jako duplikaty.
- Zatwierdzenie bez potwierdzenia: błąd, nadal 2 operacje, saldo bez zmian.

**Contract**: Liczności i saldo z ręcznego liczenia.

#### 4. Powtórzony `index` (luka A): najpierw czerwony test

**File**: `tests/Feature/ImportIntegrityTest.php`

**Intent**: Zatwierdzenie z `rows: [{index: 0, selected: true, …}, {index: 0, selected: true, …}]` daje błąd walidacji na `rows.1.index`, a migawka jest bez zmian (0 operacji, saldo bez zmian) i sesja zostaje. Test powstaje i jest uruchamiany przed poprawką; musi być czerwony (dziś: 2 operacje).

**Contract**: Błąd walidacji na kluczu `rows.1.index`.

#### 5. Reguła `distinct`

**File**: `app/Http/Requests/StoreCsvImportRequest.php`, `lang/pl/validation.php`

**Intent**: Indeks wiersza podglądu może wystąpić w żądaniu zatwierdzenia tylko raz. Dla `distinct` dochodzi polski komunikat walidacji.

**Contract**: `'rows.*.index' => ['required', 'integer', 'min:0', 'distinct']`; klucz `distinct` w `lang/pl/validation.php` z polskim zdaniem w stylu sąsiednich komunikatów.

#### 6. Nakładka na prawdziwym układzie mBank

**File**: `tests/Fixtures/import/mbank-overlap.csv` (nowy), `tests/Feature/ImportIntegrityTest.php`

**Intent**: Kopia `tests/Fixtures/mbank-basic.csv` z tą samą preambułą i nagłówkiem. Zostają w niej 3 ostatnie wiersze danych, a dochodzą 2 nowe, ręcznie przepisane z istniejących linii (inna data, opis i kwota w tym samym formacie). Test:
1. Import `mbank-basic.csv` (7 operacji).
2. Podgląd nakładki oznacza dokładnie 3 wiersze jako duplikaty.
3. Zatwierdzenie samych nieoznaczonych daje razem 9 operacji.
4. Zatwierdzenie z oznaczonym wierszem bez potwierdzenia daje błąd, a migawka jest bez zmian.

**Contract**: Liczby 7, 3, 2 i 9 wynikają z ręcznej edycji pliku, nie z parsera.

### Success Criteria:

#### Automated Verification:

- Test z punktu 4 jest czerwony przed zmianą z punktu 5 (2 operacje zamiast błędu walidacji)
- Testy fazy przechodzą na PostgreSQL po zmianie: `PGTEST tests/Feature/ImportIntegrityTest.php`
- Istniejące testy Importu przechodzą: `PGTEST tests/Feature/CsvImportTest.php`
- `pint` i `vendor/bin/sloppy ci` (w obrazie `sail-8.5/app`) są zielone dla zmienionych plików

---

## Faza 3: Formaty na istniejących fixture i issue B, C

### Overview

Dowieść, że obsługiwane zmiany układu nie gubią wierszy i że nieobsługiwane zmiany nagłówka padają głośno, nigdy częściowo. Do tego zgłosić luki B i C.

### Changes Required:

#### 1. Warianty mBank z prawdziwego pliku

**File**: `tests/Fixtures/import/mbank-no-preamble.csv`, `mbank-long-preamble.csv`, `mbank-reordered-columns.csv`, `mbank-renamed-amount.csv` (nowe; ręcznie edytowane kopie `tests/Fixtures/mbank-basic.csv`)

**Intent**: Każdy plik zachowuje kodowanie, BOM, separatory i format kwot oryginału i różni się jedną świadomą edycją:
- bez preambuły;
- dłuższa preambuła (dodatkowy blok filtra i okresu);
- kolumny przestawione plus dodatkowa kolumna `#Saldo po operacji`;
- nagłówek `#Kwota` zmieniony na `#Kwota operacji`.

**Contract**: Linie danych w trzech pierwszych wariantach są bajtowo te same co w oryginale, poza przestawieniem kolumn.

#### 2. Testy formatów

**File**: `tests/Feature/ImportIntegrityTest.php`

**Intent**:
- **Zachowujące warianty mBank** dają w podglądzie dokładnie 7 wierszy, a (data, kwota w groszach) każdego wiersza równa się ręcznie spisanej liście 7 krotek z `mbank-basic.csv`.
- **BNP:** test ładuje `tests/Fixtures/bnp-paribas-basic.xlsx` przez PhpSpreadsheet i robi jedną edycję: dodatkowy arkusz za danymi albo przestawione i dodane kolumny. Wynik to 7 wierszy, w tym obie blokady bez daty księgowania (FR-IMP-06), z ręcznie spisanymi krotkami.
- **Warianty głośne:**
  - `mbank-renamed-amount.csv`;
  - BNP z wierszem tytułowym nad nagłówkiem;
  - BNP z arkuszem okładki jako pierwszym;
  - BNP ze zmienionym nagłówkiem `Kwota`.

  Każdy daje błąd `file`, brak przekierowania do podglądu i brak `csv_import` w sesji.
- **Pełny przebieg** dla jednego wariantu mBank i dla BNP: podgląd → zatwierdzenie wszystkich 7 → +7 operacji, saldo = saldo otwarcia + ręczna suma kwot = saldo pochodne.

**Contract**: Ręczna lista krotek leży w teście jako literał z komentarzem, że spisano ją z pliku, nie z parsera.

#### 3. Issue B i C

**File**: GitHub `sebastian-kostecki/household-treasurer` (`gh issue create`, etykieta `needs-triage`, procedura `context/foundation/agents/issue-tracker.md`)

**Intent**:
- **Issue B** („Import gubi bez śladu wiersze po zmianie formatu komórki”): lista sprawdzonych wariantów z research §4 i napięcie z FR-IMP-08. Propozycja to licznik pominiętych wierszy, co wymaga PRD i ADR.
- **Issue C** („BNP: kwota z `Waluta ≠ PLN` wchodzi jako PLN”): FR-IMP-10.

Oba odsyłają do `context/changes/testing-import-integrity/research.md`.

**Contract**: Numery issue trafiają do sekcji Notes w `change.md` i do §7 planu testów w fazie 6.

### Success Criteria:

#### Automated Verification:

- Testy formatów przechodzą na PostgreSQL: `PGTEST tests/Feature/ImportIntegrityTest.php`
- Celowe zepsucie: zmiana `MbankCsvMappingResolver` tak, by mapował kolumny po pozycji zamiast po nagłówku, czerwieni test przestawionych kolumn; po cofnięciu zielony
- `gh issue view <B>` i `gh issue view <C>` pokazują oba zgłoszenia z etykietą `needs-triage`

#### Manual Verification:

- Przeglądasz cztery nowe pliki mBank (`git diff --no-index tests/Fixtures/mbank-basic.csv tests/Fixtures/import/<plik>`): każdy różni się tylko zamierzoną edycją

---

## Faza 4: Benchmark trafności podpowiedzi

### Overview

Zbudować stały zestaw referencyjny z Twojej historii Importu bez tekstu z banku, zapisać jego wynik jako poziom bazowy i dodać test, który czerwieni każdy spadek z listą różniących się wierszy.

### Changes Required:

#### 1. Generator zestawu referencyjnego

**File**: `app/Support/CsvImport/ImportCategorySuggestionReference.php` (nowy), `app/Console/Commands/ImportSuggestionReferenceCommand.php` (nowy, `php artisan make:command --no-interaction`)

**Intent**: Komenda `import:suggestion-reference {snapshot} {--year=} {--output=}` czyta snapshot JSON (`user:backup`, wersja ≥ 10) i działa tak:
1. Wybiera operacje z Importu z danego roku z tym samym filtrem i tym samym tekstem co historia podpowiadacza (`ImportCategorySuggester::loadHistory`, `:79-105`: niepusty `raw_statement_description`, opis różny od zastępczego, tekst = opis + nadawca/odbiorca, ten sam typ).
2. Normalizuje tekst przez `ImportCategorySuggestionModel::tokens`.
3. Zamienia każdy token na deterministyczny, różnowartościowy pseudonim `[A-Z]{3,}` bez polskich liter.
4. Zapisuje zestaw.

Przed zapisem robi replay chronologiczny jak `measure()`: dla każdego wiersza buduje `fromHistory` z wierszy wcześniejszych, osobno na oryginalnych i na pseudonimizowanych tokenach. Jeśli `rank()` różni się choćby w jednym wierszu, komenda kończy się kodem 1 bez zapisu.

**Contract**:
- Wyjście JSON: `{format: 1, source: {snapshot: <nazwa pliku>, sha256, created_at}, year, categories: [{key, name, type, list_archived, line_archived}], rows: [{key, date, type, text, category_key}]}`.
- `key` wiersza to pozycja 1..n w kolejności (data, id). Bez kwot, bez id z produkcji i bez oryginalnych tokenów.
- Filtr i budowa tekstu historii są współdzielone z `ImportCategorySuggester`, nie skopiowane (Sloppy łapie duplikację).
- Kategorie są kluczowane po id kategorii listy (`.ai/rules/categories.md`: lata łączy id, nie nazwa).
- Kod wyjścia 1 przy brakującym pliku, nieobsługiwanej wersji, braku wierszy albo niezgodności rankingu.

#### 2. Testy generatora

**File**: `tests/Feature/ImportSuggestionReferenceCommandTest.php` (nowy)

**Intent**: Na małym snapshocie zbudowanym w teście sprawdzamy:
- wyjście nie zawiera żadnego oryginalnego tokenu;
- odwzorowanie jest różnowartościowe;
- liczba wierszy = liczba operacji z Importu w roku;
- dwa uruchomienia dają identyczny plik;
- operacje wpisane ręcznie i z arkusza (bez `raw_statement_description`) są pominięte;
- brakujący plik, zła wersja i rok bez wierszy kończą się kodem 1.

**Contract**: Wzorzec jak `tests/Feature/ImportSuggestionAccuracyCommandTest.php`.

#### 3. Wygenerowanie zestawu ze snapshotu

**File**: `tests/Fixtures/suggestions/reference-2026.json` (nowy, wygenerowany)

**Intent**: Snapshot `1-20261112070312.json` jest kopiowany z głównego checkoutu do `storage/app/private/user-snapshots/` worktree (gitignored). Komendę uruchamia się w kontenerze z `--year=2026`.

**Contract**: 1031 wierszy. Snapshot nie trafia do gita.

#### 4. Test benchmarku i plik bazowy

**File**: `tests/Feature/ImportSuggestionAccuracyBenchmarkTest.php` (nowy), `tests/Fixtures/suggestions/reference-2026-baseline.json` (nowy)

**Intent**:
1. Test zasiewa bazę z zestawu: użytkownik, konto, Budżet 2026 w wykonaniu, kategorie listy i pozycje 2026 z odtworzonym stanem archiwum, operacje z `description` = `raw_statement_description` = pseudonimizowany tekst, `counterparty` = null. Wstawianie hurtowe.
2. Uruchamia `ImportCategorySuggestionAccuracy::measure($user, 2026)`.
3. Zamienia `operation_id` na klucz wiersza fixture.
4. Porównuje z plikiem bazowym przez `differences($baseline, $measured)`. Błąd danych albo niepuste `drops` czerwieni test z listą różniących się wierszy (klucz, data, typ, flagi przed i po). Poprawa przechodzi.

Plik bazowy powstaje tym samym testem uruchomionym z `SUGGESTION_BASELINE_WRITE=1`: test zapisuje wynik z kluczami, oznacza się jako pominięty z komunikatem „baseline written” i odmawia zapisu, gdy ustawione jest `CI`.

**Contract**:
- Plik bazowy ma format wyjścia `measure()` (`format: 1`), z `operation_id` = klucz wiersza fixture.
- Reguła zatrzymania to `differences()`: dokładne ułamki, spadek odsetka z podpowiedzią albo trafnych wśród podpowiedzianych w `year` i `aug_nov` na poziomach `model` i `suggestion`. Top 3 nie jest spadkiem.

### Success Criteria:

#### Automated Verification:

- Testy generatora przechodzą: `PGTEST tests/Feature/ImportSuggestionReferenceCommandTest.php`
- Generator na snapshocie kończy się kodem 0 i zapisuje 1031 wierszy
- `grep` kilkunastu losowo wybranych oryginalnych tokenów ze snapshotu w `tests/Fixtures/suggestions/reference-2026.json` nic nie znajduje
- Test benchmarku przechodzi na PostgreSQL: `PGTEST tests/Feature/ImportSuggestionAccuracyBenchmarkTest.php`
- Celowe zepsucie: `NEIGHBOURS` z 5 na 1 w `ImportCategorySuggestionModel` czerwieni benchmark z listą wierszy; po cofnięciu zielony
- `pint`, PHPStan (`composer types:check` w kontenerze) i `vendor/bin/sloppy ci` są zielone dla nowych plików

#### Manual Verification:

- Przeglądasz próbkę `reference-2026.json`: żadnego czytelnego tekstu z banku, nazwy kategorii akceptowalne w repo
- Liczby z pliku bazowego (rok i sierpień–listopad, z podpowiedzią i trafne) są w rzędzie wielkości odczytu S-23 z 2026-11-12 (77,7% i 92,9% rok). Różnica jest możliwa, bo zestaw zasiewa wszystkie kategorie w jednym Budżecie 2026 i nie ma stanu po późniejszych scaleniach

---

## Faza 5: Twoje świeże eksporty

### Overview

Dodać fixture z dzisiejszego eksportu banków, żeby testy formatów sprawdzały obecny układ, kodowanie i format kwot, a nie wrześniowy plik. Faza startuje, gdy dostarczysz pliki. Nie blokuje faz 1–4 ani 6.

### Changes Required:

#### 1. Pliki od Ciebie

**File**: `storage/app/private/statements/` w głównym checkoutcie (gitignored)

**Intent**: Dostarczasz pobrane z banku, bez edycji:
- mBank CSV za pełny miesiąc M;
- drugi eksport mBank z tego samego rachunku za okres nachodzący na M (np. od połowy M do połowy M+1), czyli prawdziwa nakładka;
- BNP XLSX za pełny miesiąc.

Notujesz liczbę operacji w każdym pliku według banku albo potwierdzasz liczbę policzoną przez agenta z oryginału.

**Contract**: Oryginały nigdy nie trafiają do gita ani do worktree poza `storage/app/private/`.

#### 2. Anonimizacja i fixture

**File**: `tests/Fixtures/import/mbank-<rrrr-mm>.csv`, `mbank-<rrrr-mm>-overlap.csv`, `bnp-<rrrr-mm>.xlsx` (nowe)

**Intent**: Agent najpierw zapisuje kodowanie oryginału (`file -i`, BOM). Potem zamienia nazwiska, adresy, numery rachunków i kart oraz nazwy kontrahentów i tytuły przelewów na wymyślone wartości tej samej klasy (tekst na tekst, cyfry na cyfry). Zostawia:
- układ, preambułę i kodowanie;
- BOM, separatory i końce linii;
- format kwot i dat;
- wszystkie wiersze, także podsumowania.

**Contract**: Liczba linii danych i każda (data, kwota) są identyczne z oryginałem.

#### 3. Testy na świeżych eksportach

**File**: `tests/Feature/ImportIntegrityTest.php`

**Intent**:
- Podgląd każdego pliku daje ręcznie policzoną liczbę wierszy.
- Zatwierdzenie wszystkich daje tyle operacji, a saldo = saldo pochodne.
- Podgląd nakładki oznacza jako duplikaty dokładnie ręcznie policzoną liczbę wspólnych wierszy. Zatwierdzenie nieoznaczonych daje liczbę unikalnych operacji z obu plików.

Jeśli podgląd zgubi wiersz, co jest luką B na prawdziwych danych, test zostaje z liczbą ręczną i jest oznaczony jako pominięty z odnośnikiem do issue B. Agent dopisuje do issue B komentarz z wariantem formatu, który to spowodował.

**Contract**: Liczby ręczne są literałami w teście z komentarzem o źródle.

### Success Criteria:

#### Automated Verification:

- Testy na świeżych eksportach przechodzą albo są pominięte z odnośnikiem do issue B: `PGTEST tests/Feature/ImportIntegrityTest.php`
- `git status` pokazuje tylko zanonimizowane fixture; żaden plik z `storage/app/private/statements/` nie jest śledzony

#### Manual Verification:

- Dostarczasz trzy eksporty do `storage/app/private/statements/` w głównym checkoutcie
- Przeglądasz zanonimizowane fixture obok oryginałów i potwierdzasz, że nie zostały w nich dane osobowe ani numery rachunków
- Potwierdzasz ręcznie policzone liczby wierszy i liczbę wierszy wspólnych w nakładce

---

## Faza 6: Plan testów

### Overview

Przenieść korekty z research do `context/foundation/test-plan.md` i zapisać wzorzec cookbooka, żeby kolejne testy Importu i zmiany podpowiadacza poszły tą samą drogą.

### Changes Required:

#### 1. §2 Mapa ryzyk

**File**: `context/foundation/test-plan.md`

**Intent**: Zmiany:
- **Wiersz #4** dostaje sformułowanie „Podpowiedzi kategorii w Imporcie trafiają gorzej po zmianie kodu podpowiadacza albo listy kategorii (scalenie, archiwum), a spadku nie łapie żadna bramka”, ze źródłami `prd-v3.md` FR-020 i Guardrails oraz S-23 `measurements.md` zamiast `prd-v2.md` FR-013 i „roadmap S-16 Risk”.
- **Risk Response Guidance #4:** kontekst to głos po id kategorii listy plus oferta `CategoryChoiceOptions` oraz zestaw pseudonimizowany bez tekstu z banku z poziomem bazowym w repo. Anty-wzorzec to „próg obniżony do bieżącego wyniku po spadku albo poziom bazowy spoza repo”.
- **Risk Response Guidance #1:** „Must challenge” dostaje „wiersz pomijany po cichu (FR-IMP-08) przy zmianie formatu komórki”. „What would prove protection” dostaje ręcznie policzoną liczbę wierszy jako wyrocznię kompletu.

**Contract**: Wiersze tabel w §2 (`test-plan.md:47-63`), bez zmiany kolumn.

#### 2. §3, §5, nagłówek

**File**: `context/foundation/test-plan.md`

**Intent**: Zmiany:
- **Cel etapu 1** zmienia końcówkę na „…i zapisać poziom bazowy trafności podpowiedzi na stałym zestawie referencyjnym”.
- **Akapit „Kolejność”** traci zdanie o poziomie „przed” dla #4 przed S-16.
- **Bramka w §5:** „testy integralności Importu i benchmark trafności podpowiedzi” zmienia `required after §3 Phase 1` na `required`.
- **Nagłówek:** „Last updated” → 2026-11-17.

**Contract**: Status etapu w §3 zostawiamy orkiestratorowi `/10x-test-plan`.

#### 3. §6.2, §6.6, §7

**File**: `context/foundation/test-plan.md`

**Intent**:
- **§6.2:** zamiast „TBD — see §3 Phase 1” wzorzec testu Importu:
  - plik `ImportIntegrityTest.php`;
  - migawka stanu bazy przed i po;
  - wyrocznie: ręczna liczba wierszy i ręczne krotki z pliku, saldo pochodne `AccountBalanceOnDate`;
  - fixture z prawdziwego pliku przez ręczną edycję w `tests/Fixtures/import/`;
  - wiersz padający po wierszu zapisanym;
  - jak dodać nowy wariant banku.
- **Nowy punkt** o benchmarku:
  - regeneracja zestawu: `import:suggestion-reference` na snapshocie;
  - świadome podniesienie bazy przez `SUGGESTION_BASELINE_WRITE=1`;
  - spadek czerwieni z listą wierszy.
- **§6.6:** 2–3 linie o tym, czego nauczył etap.
- **§7:**
  - równoczesne podwójne zatwierdzenie jako świadomie nietestowane (jeden użytkownik, przycisk blokowany, ponowne wysłanie po sukcesie ma test);
  - warunek ponownej oceny: drugi użytkownik w gospodarstwie albo Import przez API;
  - odnośniki do issue B i C jako znanych, otwartych luk.

**Contract**: Sekcje §6.2, §6.6, §7.

### Success Criteria:

#### Automated Verification:

- Pełna bramka na PostgreSQL w kontenerze: `docker run … --entrypoint composer sail-8.5/app ci:check` z `DB_DATABASE=testing_testing_import_integrity`
- `vendor/bin/sloppy ci` w obrazie `sail-8.5/app` jest zielone
- `grep -n "S-16" context/foundation/test-plan.md` nie zwraca linii w wierszu #4, w Risk Response Guidance #4, w §3 etap 1 ani w akapicie „Kolejność”

#### Manual Verification:

- Czytasz zmienione §2, §3, §6 i §7 i potwierdzasz, że oddają Twoje decyzje z planowania

---

## Addendum z przeglądu implementacji (2026-11-17)

Przegląd: `reviews/impl-review.md`. Repo jest publiczne, więc zestaw referencyjny zmienił się przed pierwszym pushem, a poprawka weszła do commita fazy 4 (`0ff90fc`).

### Zestaw referencyjny bez nazw i bez kolejności id

- Kategorie nazywają się „Kategoria <klucz>”. Pomiar nazw nie czyta, a prawdziwe nazwy z datami wierszy dawały dziennik wypłat, leczenia i rekolekcji.
- Klucz kategorii nie jest id z produkcji ani pozycją w snapshocie. Numeracja idzie w kolejności HMAC id z sekretem wyliczonym z treści snapshotu, bo `database/sheet-load/mapping.php` i `production-categories.md` nazywają id. Ten sam snapshot daje te same klucze. To odchodzi od kontraktu fazy 4 §1 („kluczowane po id kategorii listy”): wiersze nadal łączy klucz, nigdy nazwa.
- Słowa wiersza są posortowane. Model czyta wiersz jako zbiór słów, więc ranking się nie zmienia. Generator porównuje go z precyzją modelu (9 miejsc), bo inna kolejność dodawania wag przesuwa wynik o ostatni bit. Plik bazowy po regeneracji jest bajtowo identyczny.

### Ryzyko resztkowe zestawu referencyjnego

Świadomie zostaje w publicznym repo:
- data, typ i klucz kategorii każdego wiersza;
- liczba słów w wierszu i to, które pseudonimy wiersze dzielą.

Ktoś, kto zna jedną prawdziwą transakcję (dzień i rodzaj), może zgadnąć jej wiersz i odczytać, w których innych wierszach występują te same słowa, ale nie same słowa. Warunek ponownej oceny: zestaw z drugiego gospodarstwa albo zestaw z opisami dłuższymi niż wyciąg bankowy (np. notatki użytkownika).

### Pozostałe odchylenia od planu

- Benchmark zapisuje w `raw_statement_description` klucz wiersza, a nie pseudonimizowany tekst, żeby odwzorować id na klucz. Model tego pola nie czyta. 13 wierszy z pustym tekstem dostaje `description = '-'` (kolumna jest `NOT NULL`).
- Filtr historii jest zapisany dwa razy: SQL w `loadHistory` i `learnsFrom` dla snapshotu. Zgodność pilnuje test „a backup of a user gives the rows the suggester learns from” (backup prawdziwego użytkownika kontra `loadHistory`).
- Warianty BNP obejmują dodaną kolumnę na początku i przestawienie (`Kwota` jako pierwsza).
- Numery issue B i C są w osobnej sekcji `change.md` („Issue (faza 3)”), a nie w Notes.

### Faza 5: świeże eksporty poza gitem (2026-11-17)

Decyzja użytkownika: zanonimizowane fixture zostają poza gitem, a anonimizacja obejmuje też kwoty. Zmienia to kontrakty fazy 5 §2 i §3:

- **Miejsce.** Fixture leżą w `storage/app/private/import-fixtures/` (gitignored): `mbank-2026-09.csv`, `mbank-2026-10.csv`, `mbank-2026-09-overlap.csv`, `bnp-2026-10.xlsx`. Testy są pomijane z komunikatem, gdy plików brak, więc w CI się nie uruchamiają. Skrypty `tools/anonymize.php` i `tools/verify.php` leżą obok, bo tabela zamian zawiera prawdziwe nazwy.
- **Kwoty.** Każda kwota ma nowe cyfry w tym samym formacie i z tą samą liczbą cyfr. Ta sama kwota daje tę samą nową, więc przelew BNP → mBank dalej się zgadza. Podsumowania mBanku i saldo BNP są przeliczone. Daty, układ, kodowanie, BOM, CRLF i liczba wierszy są bez zmian. Wyrocznią są liczby i sumy zapisane przez generator, sprawdzone ręcznie z podsumowaniem pliku, nie odczyt parsera.
- **Nakładka.** Eksporty mBanku obejmują wrzesień i październik bez części wspólnej. Nakładkę 15.09–15.10 złożono ręcznie z linii obu zanonimizowanych plików, bajt w bajt: 6 wierszy października i 3 wiersze września. Test sprawdza też, że sąsiednie miesiące nie dają fałszywych duplikatów.
- **Co wykryły.** mBank ma dziś końce linii CRLF, kwotę z sufiksem ` PLN` i dodatkowy `;;`. BNP ma 14 kolumn (`Nadawca` i `Odbiorca` osobno) i wieloliniowe komórki. Import czyta wszystkie wiersze: 9, 6 i 125, w tym blokadę bez daty księgowania. Luki B na tych plikach nie ma, więc komentarz do #271 nie był potrzebny.

---

## Testing Strategy

### Integration Tests (Feature, PostgreSQL):

- Atomowość: Nowy Rok z dołączaną pozycją, rozbita para, zapisane = pochodne saldo.
- Duplikaty: ponowne wysłanie po sukcesie, brak potwierdzenia, dwa identyczne wiersze, powtórzony `index`, nakładka na prawdziwym układzie mBank.
- Formaty: warianty mBank i BNP zachowujące oraz głośne, pełny przebieg do stanu bazy.
- Trafność: generator (pseudonimizacja bez utraty informacji) i benchmark (zero spadku).
- Świeże eksporty: ręczna liczba wierszy, prawdziwa nakładka.

### Celowe zepsucie (dowód, że testy coś łapią):

- Usunięta transakcja → czerwone wycofania.
- Mapowanie mBank po pozycji → czerwone przestawione kolumny.
- `NEIGHBOURS = 1` → czerwony benchmark z listą wierszy.
- Brak `distinct` → czerwony test powtórzonego indeksu (naturalnie, przed poprawką).

### Manual Testing Steps:

1. Przejrzyj diff czterech wariantów mBank względem `mbank-basic.csv`.
2. Przejrzyj próbkę `tests/Fixtures/suggestions/reference-2026.json`.
3. Dostarcz i przejrzyj świeże eksporty (faza 5).
4. Przeczytaj zmienione sekcje planu testów.

## Performance Considerations

`measure()` buduje model od nowa dla każdego wiersza (replay chronologiczny), czyli koszt kwadratowy w liczbie wierszy. Na produkcji 1268 wierszy przeszło w komendzie (S-23). Generator robi ten replay dwa razy (oryginał i pseudonimy). Jeśli benchmark na PostgreSQL w CI zajmie więcej niż kilkanaście sekund, implementujący zgłasza czas i pyta przed jakąkolwiek zmianą zakresu zestawu. Okresu ani liczby wierszy nie przycina sam.

## Migration Notes

Brak migracji. Jedyna zmiana zachowania: żądanie zatwierdzenia z powtórzonym indeksem wiersza dostaje błąd walidacji. UI wysyła każdy indeks raz (`resources/js/components/ImportPreviewRow.vue:376`), więc użytkownik tego nie zobaczy.

## References

- Research: `context/changes/testing-import-integrity/research.md`
- Plan testów: `context/foundation/test-plan.md` (§2 #1, #4; §3 etap 1; §5; §6.2; §7)
- Pomiar S-23: `context/archive/2026-11-12-import-suggestions-on-category-list/measurements.md`, `plan.md:42,51`
- ADR-0007 (tożsamość Importu, dopasowanie zbioru), ADR-0002 (nazwane formaty Importu), ADR-0063 (jedna lista kategorii)
- Wzorce testów: `tests/Feature/CsvImportTest.php:554,632,735,3109,4171-4206`, `tests/Feature/ImportSuggestionAccuracyCommandTest.php`
- Procedury: `context/foundation/agents/parallel-changes.md`, `context/foundation/agents/issue-tracker.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Atomowość i salda

#### Automated

- [x] 1.1 Testy fazy przechodzą na PostgreSQL: `PGTEST tests/Feature/ImportIntegrityTest.php` — d9fa871
- [x] 1.2 Celowe zepsucie transakcji czerwieni testy wycofania; po cofnięciu zielone — d9fa871
- [x] 1.3 `pint` na nowym pliku testów nie zgłasza zmian — d9fa871

### Phase 2: Duplikaty

#### Automated

- [x] 2.1 Test powtórzonego `index` jest czerwony przed dodaniem `distinct` — dc2fcea
- [x] 2.2 Testy fazy przechodzą na PostgreSQL po zmianie: `PGTEST tests/Feature/ImportIntegrityTest.php` — dc2fcea
- [x] 2.3 Istniejące testy Importu przechodzą: `PGTEST tests/Feature/CsvImportTest.php` — dc2fcea
- [x] 2.4 `pint` i `vendor/bin/sloppy ci` są zielone dla zmienionych plików — dc2fcea

### Phase 3: Formaty na istniejących fixture i issue B, C

#### Automated

- [x] 3.1 Testy formatów przechodzą na PostgreSQL: `PGTEST tests/Feature/ImportIntegrityTest.php` — d4c28ab
- [x] 3.2 Celowe zepsucie mapowania mBank czerwieni test przestawionych kolumn; po cofnięciu zielony — d4c28ab
- [x] 3.3 Issue B i C istnieją na GitHubie z etykietą `needs-triage` — d4c28ab

#### Manual

- [x] 3.4 Przegląd diffu czterech wariantów mBank: tylko zamierzone edycje

### Phase 4: Benchmark trafności podpowiedzi

#### Automated

- [x] 4.1 Testy generatora przechodzą: `PGTEST tests/Feature/ImportSuggestionReferenceCommandTest.php` — 0ff90fc
- [x] 4.2 Generator na snapshocie kończy się kodem 0 i zapisuje 1031 wierszy — 0ff90fc
- [x] 4.3 Oryginalne tokeny ze snapshotu nie występują w zestawie referencyjnym — 0ff90fc
- [x] 4.4 Test benchmarku przechodzi na PostgreSQL — 0ff90fc
- [x] 4.5 Celowe zepsucie `NEIGHBOURS` czerwieni benchmark z listą wierszy; po cofnięciu zielony — 0ff90fc
- [x] 4.6 `pint`, PHPStan i `vendor/bin/sloppy ci` są zielone dla nowych plików — 0ff90fc

#### Manual

- [x] 4.7 Przegląd próbki zestawu referencyjnego: brak tekstu z banku
- [x] 4.8 Liczby z pliku bazowego są w rzędzie wielkości odczytu S-23

### Phase 5: Twoje świeże eksporty

#### Automated

- [x] 5.1 Testy na świeżych eksportach przechodzą albo są pominięte z odnośnikiem do issue B — f7b3fea
- [x] 5.2 Żaden oryginał z `storage/app/private/statements/` nie jest śledzony w gicie — f7b3fea

#### Manual

- [x] 5.3 Trzy eksporty dostarczone do `storage/app/private/statements/`
- [x] 5.4 Przegląd zanonimizowanych fixture: brak danych osobowych i numerów rachunków
- [x] 5.5 Potwierdzone ręczne liczby wierszy i wierszy wspólnych w nakładce

### Phase 6: Plan testów

#### Automated

- [x] 6.1 Pełna bramka `composer ci:check` na PostgreSQL w kontenerze — 78873e3
- [x] 6.2 `vendor/bin/sloppy ci` w obrazie `sail-8.5/app` jest zielone — 78873e3
- [x] 6.3 `S-16` nie występuje w poprawionych wierszach i akapitach planu testów — 78873e3

#### Manual

- [x] 6.4 Przeczytane §2, §3, §6 i §7 oddają decyzje z planowania
