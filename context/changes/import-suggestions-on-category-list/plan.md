# Podpowiedzi Importu na liście kategorii — Implementation Plan

## Overview

S-23 (FR-020): podpowiedź kategorii na wierszu podglądu Importu wskazuje kategorię listy, którą oferuje selektor wiersza, także wtedy, gdy kategoria nie ma jeszcze pozycji budżetu w roku daty wiersza (ADR-0063). Trwała komenda `import:suggestion-accuracy` mierzy trafność, a pomiar na tych samych danych (kopia produkcji z 11.09, stary i nowy kod) dowodzi, że podpowiedzi nie są gorsze niż przed zmianą listy kategorii.

## Current State Analysis

- Głosowanie już idzie po kategorii listy. S-18 przełączyło klucz modelu z pary (typ, nazwa) na (typ, id kategorii listy) (6b034e1). Test `tests/Feature/ImportCategorySuggesterTest.php:98` dowodzi równoważności ze starym głosem po nazwie, a test `:63` uczenia przez lata. Pomiar przed/po S-18 jawnie odłożyło do S-23 (`context/archive/2026-11-09-category-list-after-migration/plan.md:52`).
- Cel podpowiedzi jest ostrzejszy niż ADR-0063. `ImportCategorySuggester::lineIdsByYearAndCategory()` (`app/Support/CsvImport/ImportCategorySuggester.php:112-132`) wymaga niezarchiwizowanej pozycji budżetu zwycięzcy w budżecie w realizacji roku wiersza. ADR-0063:32 mówi: „The target is the winning Category if it is active and the year of the row's date has a Budget in execution; … the Budget Line joins at commit”. Słownik (`context/foundation/glossary.md:243`) mówi to samo.
- Selektor oferuje więcej, niż podpowiedź trafia. `CategoryChoiceOptions::forType()` (`app/Support/CategoryList/CategoryChoiceOptions.php:28-80`) oferuje aktywną kategorię listy w każdym budżecie w realizacji, z pozycją albo bez niej. Pomija tylko pozycję zarchiwizowaną w danym roku (ADR-0067). Commit dołącza kategorię bez pozycji przez `JoinBudgetAtFirstUse`: `ImportFinancialOperations` → `OperationCategoryChoice::check()` → `ManageFinancialOperation::lockLine()`.
- Ten rozjazd wprowadziło S-18. Przed nim cel brał się z oferty selektora: `categoryIdsByYearTypeAndName()` czytało `ImportCategoryOptions` (`git show bbf49ba^:app/Support/CsvImport/ImportCategorySuggester.php:106-128`). W starym schemacie oferta roku to były kategorie tego budżetu, więc „oferowana w roku” znaczyło tyle co „ma rekord w roku”, a obecny wymóg pozycji wiernie tłumaczy stare zachowanie. Rozjazd powstał, bo S-18 poszerzyło selektor o kategorie bez pozycji, a cel podpowiedzi za nim nie poszedł.
- Frontend już porównuje podpowiedź z ofertą roku wiersza. `suggestedCategoryId()` (`resources/js/lib/import-preview-rows.ts:75-109`) zwraca id tylko wtedy, gdy jest w `categoriesForRow()` dla `budget_year` z daty wiersza. Po stronie klienta nic się nie zmienia.
- Narzędzie pomiaru nie działa. `context/archive/2026-11-04-import-category-suggestions/prototype/eval_service.php` łączy się po `financial_operations.category_id` i podaje modelowi `category_name`, czyli kształt sprzed S-18. Mierzył też tylko model, bez celu w roku wiersza.
- Punkt odniesienia z poprzedniej zmiany pochodzi z bazy dev (1009 wierszy), nie z produkcji: cały rok 77,1% / 93,6%, sierpień–listopad 79,3% / 96,5% (`context/archive/2026-11-04-import-category-suggestions/reviews/impl-review.md:28-30`).
- Dane do pomiaru „przed”: kopia instancji z S-18 p7 została usunięta. Został `storage/app/backups/prod-backup.zip` z 11.09 w głównym checkoucie, w starym schemacie.
- Brak testu: historia kategorii scalonej (ADR-0078, `MergeCategories`) uczy kategorię docelową.

## Desired End State

- Wiersz podglądu dostaje podpowiedź dla aktywnej kategorii listy, którą selektor oferuje w roku daty wiersza, także bez pozycji w tym roku. Commit tego wiersza dołącza pozycję (plan 0) jak przy ręcznym wyborze. Kategoria zarchiwizowana, pozycja zarchiwizowana w roku wiersza i rok bez budżetu w realizacji nadal nie dają podpowiedzi.
- Cel podpowiedzi i oferta selektora pochodzą z jednego miejsca (`CategoryChoiceOptions`), więc nie mogą się rozjechać.
- `php artisan import:suggestion-accuracy {user}` drukuje pokrycie i trafność per okres, na poziomie modelu i podpowiedzi. `--output` zapisuje wynik, `--compare` kończy się kodem 1 przy każdym spadku i wypisuje różniące się wiersze.
- `context/changes/import-suggestions-on-category-list/measurements.md` zawiera:
  - „przed” i „po” na kopii z 11.09, z „po” nie gorszym w żadnym okresie i na żadnym poziomie;
  - odczyt na produkcji po scaleniach par.

### Key Discoveries:

- Stary cel = oferta selektora; wymóg pozycji to rozjazd po poszerzeniu selektora w S-18 (`bbf49ba^:app/Support/CsvImport/ImportCategorySuggester.php:106-128` wobec obecnego `:105-132`).
- Migracja zostawia aktywną tylko kategorię z niezarchiwizowanym rekordem w najnowszym budżecie albo w najnowszym budżecie w realizacji (`app/Support/CategoryList/LegacyCategoryConversion.php:173-199`). Na kopii z 11.09 każda aktywna kategoria ma więc pozycję w 2026: faza 3 dowodzi, że głos po id i cel z listy nie są gorsze od głosu po nazwie, ale nie ćwiczy nowej reguły celu z fazy 1. Tę pokrywają testy fazy 1.
- `CategoryChoiceOptions::forType()` to jedyne źródło oferty selektora. `.ai/rules/categories.md` zabrania selektorowi własnych zapytań, a podpowiedź jest częścią tego selektora.
- Wzorzec komendy z `--output` / `--compare` i kodem wyjścia: `app/Console/Commands/ControlNumbersCommand.php`, test `tests/Feature/ControlNumbersCommandTest.php`. `{user}` to „ID or email address”, jak w `account:list`.
- Id operacji przeżywają migrację S-18 (liczby kontrolne porównują największe id operacji, README „Liczby kontrolne”), więc „przed” i „po” da się złożyć wiersz po wierszu po `operation_id`.
- Archiwa sprzed ADR-0064 wymagają `CREATE ROLE treasurer;`. `backup:restore` robi `DROP SCHEMA public CASCADE` w bazie z konfiguracji (README „Próba lokalna”, kroki 3–4).

## What We're NOT Doing

- Strojenia stałych modelu (k = 5, próg 0,25, `WEIGHT_FLOOR`), ważenia świeżości, uczenia z odrzuceń ani z wpisów ręcznych. Przy niespełnionym kryterium zatrzymujemy się i raportujemy.
- Zmiany w UI podglądu, w propsach Inertii ani w `import-preview-rows.ts`. Kształt `category_suggestion` się nie zmienia.
- Poprawki `onTypeChange` (`resources/js/components/ImportPreviewRow.vue:281`), które przy zmianie typu nadpisuje kategorię wybraną ręcznie. To osobny temat poza FR-020.
- Asercji progu trafności w CI. Dane bankowe nie trafiają do repo, a test komendy działa na danych z fabryk.
- Nowego ADR ani zmiany słownika. ADR-0063:32 i `glossary.md:243` już opisują docelową regułę, kod do nich dochodzi.
- Scalania par kategorii na produkcji („Jedzenie poza domem” → „Restauracje”, „Eksploatacja” → „Utrzymanie domu”). To porządki przez S-21, robione przez użytkownika przed fazą 4.
- Zapisywania opisów bankowych w repo. JSON-y pomiaru leżą w `storage/` (ignorowany przez git), w repo tylko liczby zbiorcze.

## Implementation Approach

Najpierw poprawka reguły celu jako nieprzechodzący test. Potem trwałe narzędzie pomiaru, które czyta historię i cel tym samym kodem co podgląd. Pomiar „przed” robi skrypt na starym commicie, bo tylko tam stary kod działa na tej samej przywróconej bazie. Ten skrypt zapisuje JSON w formacie komendy, więc `--compare` rozstrzyga regułę „nie gorzej” mechanicznie. Odczyt po porządkach to uruchomienie tej samej komendy na produkcji: komenda tylko czyta.

Kryterium „nie gorzej” (reguła 1, decyzja użytkownika): w każdym okresie („cały rok”, „sierpień–listopad”) i na każdym poziomie („model”, „podpowiedź”) odsetek wierszy z podpowiedzią i odsetek trafnych wśród nich nie jest niższy niż „przed”. Każdy spadek zatrzymuje zmianę z listą wierszy, w których wynik się różni. Odczyt produkcyjny z fazy 4 jest informacyjny, bez progu.

## Critical Implementation Details

- **Porównanie bez zaokrągleń.** `--compare` porównuje ułamki na liczbach całkowitych (`suggested_after · rows_before ≥ suggested_before · rows_after`), nie zaokrąglone procenty. Jeśli zbiory `operation_id` w obu plikach się różnią, to nie są te same dane: błąd z kodem 1, nigdy „brak spadku”.
- **Format niezależny od id kategorii.** W starym schemacie id kategorii to id pozycji danego roku, w nowym id kategorii listy. Plik pomiaru trzyma więc per wiersz tylko wartości logiczne (jest podpowiedź / trafna / w top 3), a id kategorii najwyżej jako pole diagnostyczne, które nie bierze udziału w porównaniu.
- **Kolejność migracji w fazie 3.** Kopia z 11.09 ma schemat starszy niż `bbf49ba^`. Najpierw `migrate` starym kodem i pomiar „przed”, potem `migrate` nowym kodem na tej samej bazie i pomiar „po”. Odwrócenie kolejności niszczy „przed”.

## Phase 1: Cel podpowiedzi = oferta selektora

### Overview

Podpowiedź trafia do każdej aktywnej kategorii listy, którą selektor oferuje w roku daty wiersza. Zaczynamy od testu, który dziś nie przechodzi.

### Changes Required:

#### 1. Nieprzechodzący test reguły celu

**File**: `tests/Feature/ImportCategorySuggesterTest.php`

**Intent**: Zapisać regułę ADR-0063:32 jako test, zanim zmieni się kod. Test `:74` („a winner without a line in the budget of the row year gives null…”) zmienia oczekiwanie. Zwycięzca bez pozycji w 2026, z budżetem 2026 w realizacji, daje teraz jego id, a nie `null`. Zmiana asercji jest dozwolona, bo wymaganie zmieniło ADR-0063, a nie ta poprawka. Nazwa testu ma to mówić.

**Contract**:
- Nowe lub zmienione przypadki:
  - aktywny zwycięzca bez pozycji w roku wiersza → jego id;
  - historia kategorii źródłowej po `App\Actions\MergeCategories` głosuje na kategorię docelową;
  - suggester i `CategoryChoiceOptions::forType()` zgadzają się co do zbioru celów w jednym scenariuszu z kategorią z pozycją, bez pozycji, z pozycją zarchiwizowaną w roku wiersza i zarchiwizowaną na liście;
  - zwycięzca zarchiwizowany na liście, a drugi kandydat aktywny z pozycją w 2026 → `null`, bez przejścia do kolejnego kandydata (połowa nazwy `:74`, której zmieniony `:74` już nie pokrywa; `:87` ma jednego kandydata).
- `:98` („without name variants the vote by list category gives what a vote by name gave”) też koduje starą regułę: wyrocznia `$byName` daje `null` dla „ZOO LAND” (kategoria „Bez pozycji” bez pozycji w 2026). Zmienia się z tego samego powodu co `:74` (ADR-0063:32): wyrocznia dostaje nowy cel, czyli id kategorii „Bez pozycji” dla „ZOO LAND”. Równoważność głosu po nazwie i po id zostaje dowiedziona dla pozostałych nazw.
- Bez zmian zostają `:87` (zarchiwizowana na liście → `null`), `:126` (pozycja zarchiwizowana w roku wiersza → `null`) i `:136` (szkic albo brak budżetu → `null`).

#### 2. Podgląd oferuje i podpowiada kategorię bez pozycji

**File**: `tests/Feature/CsvImportTest.php`

**Intent**: Dowieść na granicy HTTP, że podpowiedziane id kategorii bez pozycji w 2026 jest w `expenseCategories` z `budget_year` 2026, więc klient je pokaże.

**Contract**: test `assertInertia` obok `test_preview_rows_carry_category_suggestions_from_import_history` (`:194`): `rows.0.category_suggestion.expense` równa się id opcji z `budget_year` 2026 dla kategorii bez pozycji.

#### 3. Cel z oferty selektora

**File**: `app/Support/CsvImport/ImportCategorySuggestionTargets.php` (nowy)

**Intent**: Jedno miejsce, które mówi, czy zwycięzca głosowania jest celem w danym roku. Zbudowane z `CategoryChoiceOptions::forType()` dla przychodów i wydatków, więc cel podpowiedzi = oferta selektora z konstrukcji.

**Contract**: `final class ImportCategorySuggestionTargets` z `static forUser(User $user): self` (stała liczba zapytań) i `resolve(string $type, ?int $categoryId, int $year): ?int` (id albo `null`). Klasa używana też przez fazę 2.

#### 4. Suggester na nowym celu

**File**: `app/Support/CsvImport/ImportCategorySuggester.php`

**Intent**: Zastąpić `lineIdsByYearAndCategory()` przez `ImportCategorySuggestionTargets`. Leniwe budowanie celu (tylko gdy jakiś wiersz ma zwycięzcę) zostaje, podobnie jak brak przejścia do kolejnego kandydata. Poprawić docblock, który dziś przypisuje ADR-0063 regułę, której ten nie zawiera.

**Contract**: `annotateRows(User, array): array` bez zmian, wynik `category_suggestion: {income: ?int, expense: ?int}` bez zmian.

### Success Criteria:

#### Automated Verification:

- Nowe i zmienione testy z punktów 1–2 nie przechodzą przed zmianą kodu (czerwone): `php artisan test --compact tests/Feature/ImportCategorySuggesterTest.php tests/Feature/CsvImportTest.php`
- Te same testy przechodzą po zmianie (zielone)
- Testy podpowiedzi i NFR podglądu przechodzą na PostgreSQL (polecenie worktree z AGENTS.md): `tests/Feature/ImportCategorySuggesterTest.php tests/Feature/CsvImportTest.php tests/Feature/Nfr/NfrPerf03ImportPreviewTest.php tests/Unit/ImportCategorySuggestionModelTest.php`
- `vendor/bin/pint --dirty --format agent` bez zmian i `vendor/bin/sloppy ci` bez nowych znalezisk

#### Manual Verification:

- W podglądzie Importu wiersz sklepu, którego kategoria nie ma pozycji w roku wiersza, pokazuje podpowiedź „✦”, a zatwierdzenie dołącza pozycję z planem 0

**Implementation Note**: Po automatycznej weryfikacji zatrzymaj się na potwierdzenie ręcznego sprawdzenia przed fazą 2.

---

## Phase 2: Komenda `import:suggestion-accuracy`

### Overview

Trwałe, testowane narzędzie pomiaru, które odtwarza historię chronologicznie na kodzie produkcyjnym i porównuje wynik z wcześniejszym plikiem.

### Changes Required:

#### 1. Odczyt trafności

**File**: `app/Support/CsvImport/ImportCategorySuggestionAccuracy.php` (nowy), `app/Support/CsvImport/ImportCategorySuggester.php`

**Intent**: Policzyć dla jednego użytkownika, jak trafne byłyby podpowiedzi, gdyby każdy wiersz historii importować na nowo, ucząc się tylko z wcześniejszych wierszy. Historia i cel muszą pochodzić z tego samego kodu co podgląd. Suggester udostępnia więc swoje zapytanie historii (dziś prywatne `loadHistory()`) zamiast kopii w nowej klasie.

**Contract**:
- Wiersze: historia suggestera w kolejności (`operation_date`, `id`). `loadHistory()` nie ma ORDER BY i zwraca jednorazowy `\Generator`, a `fromHistory()` nie odcina po dacie. Accuracy materializuje więc historię do tablicy i sortuje ją po (`operation_date`, `id`), jak `eval_service.php`. `loadHistory()` zostaje bez ORDER BY, bez kosztu dla podglądu (model rozstrzyga remisy sam, w `compareNeighbours`).
- Rok: `--year` albo najnowszy rok wierszy.
- Okresy: `year` (`RRRR-01-01..RRRR-12-31`) i `aug_nov` (`RRRR-08-01..RRRR-11-30`), jak w `eval_service.php`.
- Dla każdego wiersza w okresie model powstaje z wierszy wcześniejszych (`array_slice`).
- Poziom `model`: `suggest()` i `rank()` dla typu wiersza.
- Poziom `suggestion`: wynik modelu po `ImportCategorySuggestionTargets::resolve()` dla roku wiersza.
- Trafna: id podpowiedzi = id kategorii listy wiersza.
- `differences(array $before, array $after): array{drops: list<string>, rows: list<string>, error: ?string}`:
  - spadek liczony ułamkami, nie procentami;
  - różne zbiory `operation_id` → `error`.
- Format pliku (kontrakt także dla skryptu „przed” z fazy 3):

```json
{
  "format": 1,
  "user_id": 1,
  "year": 2026,
  "periods": {
    "year":    { "model": { "rows": 0, "suggested": 0, "correct": 0, "top3": 0 }, "suggestion": { "rows": 0, "suggested": 0, "correct": 0 } },
    "aug_nov": { "model": { "...": 0 }, "suggestion": { "...": 0 } }
  },
  "rows": [
    { "operation_id": 123, "operation_date": "2026-03-04", "type": "expense",
      "model": { "suggested": true, "correct": true, "top3": true },
      "suggestion": { "suggested": true, "correct": true } }
  ]
}
```

#### 2. Komenda

**File**: `app/Console/Commands/ImportSuggestionAccuracyCommand.php` (nowy)

**Intent**: Wydrukować tabelę okresów i poziomów (wiersze, z podpowiedzią %, trafne % z podpowiedzianych, top 3 % dla modelu), zapisać plik i porównać z wcześniejszym. Komenda tylko czyta bazę, więc można ją uruchomić na produkcji.

**Contract**:
- Sygnatura: `#[Signature('import:suggestion-accuracy {user : The user ID or email address} {--year= : Evaluation year; default the latest year of imported rows} {--output= : Write the result to this JSON file} {--compare= : Compare with a JSON file written earlier; exit code 1 on any drop}')]`.
- Ścieżki względne liczone od `base_path()`, jak w `app:control-numbers`.
- Kod 1 przy:
  - spadku, z wypisanymi okresami i poziomami spadku oraz różniącymi się `operation_id` i ich wynikami;
  - różnych zbiorach wierszy;
  - nieznanym użytkowniku;
  - braku wierszy.
- Różnice bez spadku są wypisane, kod 0.
- Plik nie zawiera opisów ani kontrahentów.

#### 3. Test komendy

**File**: `tests/Feature/ImportSuggestionAccuracyCommandTest.php` (nowy, `php artisan make:test --pest`)

**Intent**: Zabezpieczyć, żeby narzędzie nie zestarzało się po cichu przy następnej zmianie schematu, jak `eval_service.php`.

**Contract**: dane z fabryk (wzorzec pomocników z `ImportCategorySuggesterTest.php`). Przypadki:
- wiersz nie uczy się z siebie ani z późniejszych;
- podział na okresy;
- kategoria bez pozycji w roku wiersza liczy się na poziomie `suggestion` po fazie 1;
- `--output` zapisuje format z kontraktu;
- `--compare` z identycznym plikiem → 0;
- `--compare` ze spadkiem jednego wiersza → 1 i lista `operation_id`;
- różne zbiory wierszy → 1;
- nieznany użytkownik → 1.

#### 4. README

**File**: `README.md`

**Intent**: Dopisać komendę do tabeli „Komendy administracyjne” i krótki podrozdział „Pomiar trafności podpowiedzi Importu” w „Produkcja”:
- kiedy mierzyć: po zmianie modelu, kategorii albo po scaleniach;
- że komenda tylko czyta;
- jak porównać dwa przebiegi;
- że pliki z danymi kładziemy w `storage/app/private/suggestion-accuracy/`.

**Contract**: wiersz `import:suggestion-accuracy {user}` w tabeli `## Komendy administracyjne`; nowy nagłówek `#### Pomiar trafności podpowiedzi Importu`.

### Success Criteria:

#### Automated Verification:

- Test komendy przechodzi na PostgreSQL: `tests/Feature/ImportSuggestionAccuracyCommandTest.php`
- Testy podpowiedzi z fazy 1 nadal przechodzą na PostgreSQL
- Pełna bramka przechodzi: `composer ci:check` (wariant worktree z AGENTS.md) i `vendor/bin/sloppy ci`
- `vendor/bin/pint --dirty --format agent` bez zmian

#### Manual Verification:

- `php artisan import:suggestion-accuracy <user>` na lokalnej bazie dev drukuje czytelną tabelę dwóch okresów i dwóch poziomów, a `--output` + `--compare` na tym samym pliku daje kod 0

**Implementation Note**: Po automatycznej weryfikacji zatrzymaj się na potwierdzenie ręcznego sprawdzenia przed fazą 3.

---

## Phase 3: Pomiar przed/po na kopii z 11.09

### Overview

Rozstrzygnięcie FR-020 na tych samych danych. Stary kod (`bbf49ba^`) i nowy kod (ta gałąź) mierzą tę samą przywróconą bazę, a `--compare` stosuje regułę 1.

### Changes Required:

#### 1. Skrypt „przed”

**File**: `context/changes/import-suggestions-on-category-list/measure/before.php` (nowy)

**Intent**: Na kodzie `bbf49ba^` zrobić to samo co `ImportCategorySuggestionAccuracy` i zapisać plik w formacie z fazy 2. Podstawa to `eval_service.php`:
- ten sam zbiór wierszy i to samo odtworzenie chronologiczne;
- model głosuje po nazwie, jak wtedy;
- trafna = nazwa podpowiedzi = nazwa kategorii wiersza;
- poziom `suggestion` to stary cel: `categoryIdsByYearTypeAndName()` + `uniqueId()` starego `ImportCategorySuggester`, wywołane przez `Closure::bind`, nie przepisane.

**Contract**: uruchamiany jak `eval_service.php` (`php < before.php` w kontenerze z kodem `bbf49ba^`), argumenty przez zmienne środowiskowe: `USER_ID`, `YEAR`, `OUTPUT`. Na wyjściu plik `format: 1` oraz tabela jak w komendzie.

#### 2. Procedura pomiaru

**File**: `context/changes/import-suggestions-on-category-list/measurements.md` (nowy)

**Intent**: Wykonać i zapisać kroki pomiaru. Kroki 1–3 wymagają człowieka (hasło archiwum).
1. Przywrócić `storage/app/backups/prod-backup.zip` z głównego checkoutu do osobnej bazy `suggestion_trial` według README „Próba lokalna”:
   - najpierw `CREATE ROLE treasurer;`;
   - `db:show` musi pokazać `suggestion_trial`, inaczej STOP;
   - hasło tylko na czas polecenia.
2. `git worktree add --detach .claude/worktrees/suggestions-before bbf49ba^`, w nim `composer install` i `.env` z `.env.example`.
3. Starym kodem (obraz `sail-8.5/app`, sieć `household-treasurer_sail`, `DB_DATABASE=suggestion_trial`):
   - najpierw `mkdir -p storage/app/private/suggestion-accuracy` w worktree tej zmiany (inaczej Docker utworzy katalog jako root);
   - kontener starego kodu dostaje dodatkowy mount `-v <worktree tej zmiany>/storage/app/private/suggestion-accuracy:/var/www/html/storage/app/private/suggestion-accuracy`, więc plik „przed” od razu ląduje w worktree tej zmiany i przeżyje usunięcie starego;
   - `php artisan db:show` musi pokazać `suggestion_trial`, inaczej STOP;
   - `php artisan migrate --force`, potem `before.php` z `OUTPUT=storage/app/private/suggestion-accuracy/2026-09-11-before.json`.
4. Nowym kodem na tej samej bazie (polecenie worktree z AGENTS.md z `DB_DATABASE=suggestion_trial`):
   - `php artisan db:show` musi pokazać `suggestion_trial`, inaczej STOP;
   - `php artisan migrate --force`, potem `php artisan import:suggestion-accuracy <user> --year=2026 --output=storage/app/private/suggestion-accuracy/2026-09-11-after.json --compare=storage/app/private/suggestion-accuracy/2026-09-11-before.json`.
5. Kod 0 → zapisać obie tabele w `measurements.md`. Kod 1 → STOP: wypisać użytkownikowi okresy ze spadkiem i różniące się wiersze (id operacji, opis z bazy próbnej tylko w rozmowie, nie w repo). Nie stroić stałych.
6. Sprzątanie według README „Próba lokalna” krok 7:
   - `dropdb suggestion_trial`;
   - przywrócenie odłożonych `storage/app/private` i `storage/app/public`;
   - `git worktree remove --force .claude/worktrees/suggestions-before` (ma nieśledzone vendor/ i .env; plik „przed” leży już w worktree tej zmiany).
   - Oba JSON-y zostają tylko w `storage/app/private/suggestion-accuracy/` worktree tej zmiany (bez opisów, ale z id operacji).

**Contract**: `measurements.md` zawiera:
- datę i źródło danych (archiwum z 11.09, rok 2026, liczba wierszy);
- zakres dowodu w jednym zdaniu: na tych danych każda aktywna kategoria ma pozycję w 2026, więc pomiar porównuje głos po nazwie z głosem po id i celem z listy, a regułę „cel bez pozycji” z fazy 1 dowodzą testy;
- dwie tabele (przed / po) per okres i poziom;
- wynik `--compare` z kodem wyjścia;
- wiersze różniące się, bez opisów.

#### 3. Odpowiedź na pytanie 4 z PRD

**File**: `context/foundation/prd-v3.md`, `context/foundation/roadmap.md`

**Intent**: Zapisać decyzję użytkownika przy Otwartym pytaniu 4 i przy Unknowns S-23: porządki idą po migracji, przez S-21. „Nie gorzej” rozstrzyga kopia z 11.09 na starym i nowym kodzie, a odczyt po scaleniach jest informacyjny.

**Contract**: dopisek pod `4. **Porządek w danych przed migracją.**` w `prd-v3.md` i pod Unknowns w bloku `### S-23` roadmapy; bez zmiany numeracji i innych pól.

### Success Criteria:

#### Automated Verification:

- `import:suggestion-accuracy … --compare=…/2026-09-11-before.json` kończy się kodem 0 na bazie `suggestion_trial`
- Zbiory `operation_id` w plikach „przed” i „po” są równe (komenda nie zgłasza błędu danych)
- `git status` nie pokazuje plików JSON pomiaru ani archiwum w repo

#### Manual Verification:

- Użytkownik przegląda `measurements.md`: obie tabele i lista różniących się wierszy (jeśli są) zgadzają się z jego oceną
- Baza `suggestion_trial` usunięta, `storage/app/private` i `storage/app/public` przywrócone, stary worktree usunięty

**Implementation Note**: Przy kodzie 1 faza nie jest ukończona: zatrzymaj się i oddaj decyzję użytkownikowi przed fazą 4.

---

## Phase 4: Odczyt na produkcji po porządkach

### Overview

Po wdrożeniu S-23 (z F-06) i scaleniu dwóch par na produkcji: jeden odczyt komendą na aktualnych danych, informacyjnie.

### Changes Required:

#### 1. Odczyt i zapis liczb

**File**: `context/changes/import-suggestions-on-category-list/measurements.md`

**Intent**: Na serwerze uruchomić `scripts/artisan.sh import:suggestion-accuracy <user>` (najnowszy rok), opcjonalnie z `--output` do `storage/app/private/suggestion-accuracy/` na serwerze. Dopisać tabelę do `measurements.md` z datą, liczbą wierszy i informacją, które scalenia były już zrobione. Bez progu. Wyraźny spadek względem fazy 3 to sygnał dla użytkownika, nie blokada.

**Contract**: sekcja `## Produkcja po porządkach (<data>)` w `measurements.md`.

### Success Criteria:

#### Automated Verification:

- Komenda na produkcji kończy się kodem 0 i nie zmienia liczb kontrolnych: `scripts/artisan.sh app:control-numbers` przed i po odczycie daje te same wartości

#### Manual Verification:

- Scalenia „Jedzenie poza domem” → „Restauracje” i „Eksploatacja” → „Utrzymanie domu” wykonane na produkcji przed odczytem (albo zapisane, że jeszcze nie)
- Tabela produkcyjna dopisana do `measurements.md`, a użytkownik ją przejrzał

---

## Testing Strategy

### Unit Tests:

- `tests/Unit/ImportCategorySuggestionModelTest.php` bez zmian: model się nie zmienia.

### Integration Tests:

- `ImportCategorySuggesterTest`:
  - reguła celu (bez pozycji → podpowiedź; zarchiwizowana na liście, pozycja zarchiwizowana w roku, brak budżetu w realizacji → `null`);
  - historia po scaleniu;
  - zgodność celu z `CategoryChoiceOptions`.
- `CsvImportTest`: podpowiedź kategorii bez pozycji jest w opcjach roku wiersza (`assertInertia`).
- `ImportSuggestionAccuracyCommandTest`: odtworzenie chronologiczne, okresy, poziomy, `--output`, `--compare` (0 / spadek / inne dane), błędy.
- `NfrPerf03ImportPreviewTest` na PostgreSQL: czas podglądu po zmianie celu.

### Manual Testing Steps:

1. Lokalnie: kategoria aktywna bez pozycji w 2026, historia z jej sklepem, import wyciągu → „✦” na wierszu, zatwierdzenie tworzy pozycję z planem 0.
2. Komenda na bazie dev: tabela, `--output`, `--compare` z tym samym plikiem → 0.
3. Faza 3 i 4 według procedur powyżej.

## Performance Considerations

- `ImportCategorySuggestionTargets::forUser()` czyta `CategoryChoiceOptions::forType()` dwa razy (przychody, wydatki), w stałej liczbie zapytań, i tylko gdy któryś wiersz ma zwycięzcę. To ok. 5 zapytań na typ (budżety, kategorie, `with('group')`, pozycje, `CategoryRhythms`), razem ok. 10 zamiast dzisiejszego 1 w `lineIdsByYearAndCategory()`. Rytmy i grupy są dla celu zbędne, ale jedno źródło celu i oferty jest warte tego kosztu. Wywołanie idzie w POST `CsvImportController::preview()` (`:109`), a opcje selektora budują się w osobnym GET `showPreview()` (`:140-141`), więc niczego nie dubluje. `NfrPerf03ImportPreviewTest` (pgsql) pilnuje czasu podglądu, nie liczby zapytań.
- Komenda buduje model od nowa dla każdego wiersza w okresie (O(n²) po historii), jak `eval_service.php`. Przy ~1300 wierszach to sekundy. To komenda administracyjna, nie żądanie HTTP, więc optymalizacja nie jest potrzebna.

## Migration Notes

Brak migracji schematu i danych. Wycofanie = `git revert` commitów fazy 1–2: podpowiedź wraca do wymogu pozycji, komenda znika. Liczby kontrolne się nie zmieniają.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-23), PRD: `context/foundation/prd-v3.md` (FR-020, Otwarte pytanie 4)
- ADR: `context/foundation/adr/0063-one-category-list-across-years.md:32`, `0034-…` (ostatni akapit), `0067-category-choice-and-archive-on-the-list.md`, `0078-category-merge.md`
- Poprzedni pomiar: `context/archive/2026-11-04-import-category-suggestions/` (`plan.md:341-386`, `reviews/impl-review.md:28-30`, `prototype/eval_service.php`)
- S-18 odkłada pomiar do S-23: `context/archive/2026-11-09-category-list-after-migration/plan.md:52`
- Stary cel z oferty selektora: `git show bbf49ba^:app/Support/CsvImport/ImportCategorySuggester.php` (`:106-128`)
- Wzorzec komendy: `app/Console/Commands/ControlNumbersCommand.php`, `tests/Feature/ControlNumbersCommandTest.php`
- Procedura kopii: `README.md` „Próba lokalna (bez nadpisania bazy deweloperskiej)”

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Cel podpowiedzi = oferta selektora

#### Automated

- [x] 1.1 Nowe i zmienione testy z punktów 1–2 nie przechodzą przed zmianą kodu (czerwone) — 436e505
- [x] 1.2 Te same testy przechodzą po zmianie (zielone) — 436e505
- [x] 1.3 Testy podpowiedzi i NFR podglądu przechodzą na PostgreSQL — 436e505
- [x] 1.4 Pint bez zmian i Sloppy bez nowych znalezisk — 436e505

#### Manual

- [x] 1.5 Podpowiedź „✦” dla kategorii bez pozycji w roku wiersza, zatwierdzenie dołącza pozycję z planem 0 — 436e505

### Phase 2: Komenda `import:suggestion-accuracy`

#### Automated

- [x] 2.1 Test komendy przechodzi na PostgreSQL
- [x] 2.2 Testy podpowiedzi z fazy 1 nadal przechodzą na PostgreSQL
- [x] 2.3 Pełna bramka przechodzi: composer ci:check i sloppy ci
- [x] 2.4 Pint bez zmian

#### Manual

- [x] 2.5 Komenda na bazie dev drukuje czytelną tabelę, a --output + --compare na tym samym pliku daje kod 0

### Phase 3: Pomiar przed/po na kopii z 11.09

#### Automated

- [ ] 3.1 --compare z plikiem „przed” kończy się kodem 0 na bazie suggestion_trial
- [ ] 3.2 Zbiory operation_id w plikach „przed” i „po” są równe
- [ ] 3.3 git status nie pokazuje plików JSON pomiaru ani archiwum w repo

#### Manual

- [ ] 3.4 Użytkownik przegląda measurements.md: tabele i różniące się wiersze
- [ ] 3.5 Baza suggestion_trial usunięta, storage przywrócony, stary worktree usunięty

### Phase 4: Odczyt na produkcji po porządkach

#### Automated

- [ ] 4.1 Komenda na produkcji kończy się kodem 0 i nie zmienia liczb kontrolnych

#### Manual

- [ ] 4.2 Scalenia dwóch par wykonane przed odczytem (albo zapisane, że jeszcze nie)
- [ ] 4.3 Tabela produkcyjna dopisana do measurements.md i przejrzana
