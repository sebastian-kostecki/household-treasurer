# Wczytanie historycznych budżetów 2022–2025 z arkuszy xlsx — plan implementacji

## Overview

Jednorazowe wczytanie lat 2022–2025 ze starego arkusza budżetu (`Budżet_RRRR.xlsx`) do produkcyjnej bazy jako zwykłe Budżety w fazie execution ze zwykłymi Financial Operations, tak żeby żadna zapisana wartość 2026/2027 się nie zmieniła: salda kont i lokat, linie i plany budżetów 2026 i 2027, plany miesięcy, zebrane, ostrzeżenie o deficycie szkicu 2027. Każdy rok trafia na własne, zarchiwizowane konto arkusza („Arkusz RRRR”) oznaczone kolumną `accounts.sheet_year`, sumuje się co do grosza do arkusza „Analiza” danego roku i da się wycofać jedną komendą. Zmiana odwraca zapisaną decyzję PRD v3 („dane 2025 wchodzą tylko jako mock na devie”).

## Current State Analysis

Pełne ustalenia: `context/changes/legacy-budget-import/research.md` (sekcje 1–7 i „Addendum 2026-11-12: earlier years”).

- **Arkusz to sumy miesięczne, nie transakcje.** 12 arkuszy miesięcy: wydatki (A grupa, B pozycja, D wykonanie), przychody (G źródło, I wykonanie), lista nieregularnych (J kategoria, K nazwa, M wydatek), lista leczenia i lista badań. Żaden wiersz operacji nie ma daty. 2022 ma inny układ niż 2023–2025 (sumy w D31/D32, 8 źródeł przychodu, rozpisanie Codziennych w G25:I31, przesunięcie stycznia o 2 wiersze), a etykiety różnią się między latami i wewnątrz 2022 (research, addendum, reguły 1–8).
- **Sumy kontrolne arkusza.** We wszystkich czterech latach suma wydatków bez wpłat na oszczędności i suma przychodów bez wiersza „oszczędności” równa się `Analiza!C16` / `Analiza!B16`: 2022 101 219,38 / 106 233,93; 2023 122 115,75 / 117 847,66; 2024 126 760,34 / 110 559,17; 2025 134 852,23 / 134 670,58 zł.
- **Rozbieżności wewnątrz arkuszy:** 2022 czerwiec (wpisana suma nieregularnych 4 244,37 przy liście 161,16; Codzienne 2 154,77 przy rozpisaniu 674,52; leczenie 430 przy liście 230; badania 66 przy pustej liście), 2024 wrzesień (bilans 86,58). W każdym przypadku „Analiza” sumuje kolumnę D.
- **Żadna ścieżka zapisu się nie nada bez zmian.** Wpis ręczny i Import odrzucają datę sprzed otwarcia konta (`app/Support/OperationEntryValidation.php:36`, `app/Support/CsvImport/FinancialOperationImportValidator.php:40-47`), odrzucają konta zarchiwizowane (`app/Actions/ManageFinancialOperation.php:138-143`) i przesuwają saldo (`:255-289`). Siedem z dziewięciu prawdziwych kont otwiera się 2025-12-31. Wzorem jest `database/seeders/PreviousYearMockSeeder.php:46-50, 117-134, 232-250`: Budget od razu w execution, bezpośredni insert operacji, `execution_minor_units` = suma operacji, salda nietknięte.
- **Odczyty płynności liczą konta zarchiwizowane:** `SpendableLiquidity` (`app/Support/SpendableLiquidity.php:14`), `TotalFunds`, a `BudgetDeficitWarning::liquidityAtYearStart` sumuje salda otwarcia kont otwartych do 1 stycznia (`app/Support/BudgetDeficitWarning.php:50-57`). Konta arkusza z saldem otwarcia −(wynik roku) podniosłyby płynność szkicu 2027 o +15 636,36 zł.
- **Konta zarchiwizowane są widoczne:** dashboard (`app/Http/Controllers/DashboardController.php:38-40, 181-188`), strona kont z liczbą zarchiwizowanych (`app/Http/Controllers/AccountController.php:22-47`), filtr kont operacji (`app/Support/OperationsFilterOptions.php:86-95`). `AccountPolicy` pozwala przywrócić, edytować i usunąć zarchiwizowane konto (`app/Policies/AccountPolicy.php:25-46`).
- **Usunięcie operacji działa na koncie zarchiwizowanym** (`ManageFinancialOperation.php:108-114`), a edycja sprawdza tylko konto docelowe (`:189-194` wymaga aktywnego konta wybranego w formularzu, nie konta operacji), więc operację z konta zarchiwizowanego da się przenieść na prawdziwe konto otwarte nie później niż jej data.
- **Wpis i Import roku odblokowuje sam Budget w execution:** dziś data z 2025 odpada tylko dlatego, że nie ma Budżetu 2025 (`app/Support/CategoryList/OperationCategoryChoice.php:42-49`); po wczytaniu wpis ręczny i wiersz Importu z datą 2025-12-31 na siedmiu kontach otwartych 2025-12-31 przechodzą (`OperationEntryValidation.php:36` porównuje tylko `<`), `JoinBudgetAtFirstUse` dokłada linię do Budżetu 2025, a saldo prawdziwego konta się przesuwa. Operacje grudnia 2025 z arkusza mają datę 2025-12-31.
- **Tworzenie kategorii dołącza do szkiców** (`app/Actions/CreateListCategory.php:55`, `JoinDraftBudgets`) i zapisuje Rytm od bieżącego miesiąca (`:58-64`). Restore snapshotu odrzuca kategorię wydatku bez wpisu Rytmu (`.ai/rules/snapshot.md`, wersja 9).
- **Numery kontrolne nie odróżniają dodania od zmiany:** `ControlNumbers::differences()` raportuje każde dodane id jako `(missing) -> x` (`app/Support/ControlNumbers.php:84-107`), a lista kategorii jest tylko licznikiem w `meta`.
- **Przychód musi mieć kwotę ≥ 1** (`app/Concerns/IncomeValidationRules.php:22`), a arkusze mają ujemne komórki przychodu (przeniesienia między miesiącami: 2022 darowizny, 2023 czerwiec Jana −293,68).
- **Snapshot** jest w wersji 11 (`app/Actions/BackupUserData.php:20`); konta zapisuje `BackupUserData.php:37, 110-120`, odtwarza `RestoreUserData.php:178-199`.

## Desired End State

Na produkcji istnieją Budżety 2022, 2023, 2024 i 2025 w fazie execution. Każdy ma linie tylko dla kategorii z operacjami (Initial Plan = Current Plan = Execution = suma operacji linii) i operacje na koncie „Arkusz RRRR” (`sheet_year = RRRR`, otwarte 1 stycznia, zarchiwizowane, saldo otwarcia = −wynik roku, saldo bieżące 0). Suma przychodów i wydatków każdego roku równa się `Analiza!B16` / `C16` jego pliku. `app:control-numbers --compare=<przed> --allow-additions` przechodzi: nic istniejącego się nie zmieniło, doszły tylko nowe Budżety, ich linie, konta arkusza, operacje i jedna zarchiwizowana kategoria „Kosmetyki”.

Zmieniają się celowo (zaakceptowane, ADR-0083): raport „ostatnie 12 miesięcy” (pełne pokrycie, bez uwagi), kolumna roku poprzedniego w raportach 2026 (i kolejnych lat wstecz), blokady „Usuń” i zmiany typu na kategoriach z historią, lata 2022–2027 w selektorze roku i pasku bocznym, lista operacji (domyślnie bez filtra roku, więc pokazuje operacje z arkusza i większe liczniki `OperationsSectionTotals`), propsy wpisu i Importu (`EntryToSpendInputs`, `HandEntryMonthRemainingInputs`, `ImportMonthRemainingContext` mają wpis na każdy wczytany Budget; kształt bez zmian), odmowa wpisu i Importu z datą w roku wczytanym. Bez zmian: salda, płynność i fundusze, dashboard, strona kont, Budżety 2026/2027, plany miesięcy, zebrane, ostrzeżenie o deficycie 2027, domyślny rok.

### Key Discoveries:

- `PreviousYearMockSeeder::writeExecution()` (`database/seeders/PreviousYearMockSeeder.php:232-245`) to wzór zapisu Execution po bulk insercie; `StartBudgetExecution` zeruje Execution (`app/Actions/StartBudgetExecution.php:94-114`), więc nie może być wywołany po wczytaniu.
- `BnpParibasXlsxParser::parseAmountCell()` (`app/Support/CsvImport/BnpParibasXlsxParser.php:213-216`) zamienia liczbę z komórki na grosze od razu (`(int) round($value * 100)`); od tej granicy dalej wszystko jest `int`.
- Arkusz trzeba wczytać w całości (pojedynczy arkusz psuje odwołania do `Ustawienia!` i `Kalendarz!`), czytając zapisane wartości wyliczone, bez przeliczania formuł (research §1, addendum).
- Komendy: `#[Signature]`/`#[Description]`, `ResolvesConsoleUser`, `ReadsAndWritesJsonFiles`, potwierdzenie bez `--force` (`app/Console/Commands/RestoreUserDataCommand.php:42-48`, `ControlNumbersCommand.php`).
- `CategoryNameGuard::assertFree`, `CategoryListSequence::nextSortOrder` i `ChangeCategoryRhythm` (`app/Actions/CreateListCategory.php:40-64`) pozwalają utworzyć kategorię bez `JoinDraftBudgets`.
- `backup:restore` (`app/Console/Commands/RestoreApplicationBackupCommand.php:11`) odtwarza pełną kopię `backup:run` z zachowaniem id; `user:restore` nadaje nowe id (research §3), więc próba z mapowaniem po id idzie przez `backup:restore`.
- `scripts/artisan.sh` uruchamia artisan w kontenerze produkcji; `scripts/deploy.sh` robi kopię i numery kontrolne wokół `migrate` (`scripts/deploy.sh:47-66`).

## What We're NOT Doing

- Lata sprzed 2022 i pliki o innym układzie niż cztery przeczytane: parser odmawia z nazwą komórki; nowy układ to nowa zmiana.
- Transakcje z formuł „Codziennych wydatków” (`=a+b+…`): bez dat, jedna operacja na wiersz arkusza i miesiąc.
- Faktyczne rachunki z `Oszczędności!T` zamiast odkładań `**` (decyzja: „Analiza jest prawdą”).
- Plany miesięcy, Rytmy (poza jednym wpisem nowej kategorii „Kosmetyki”), zebrane, Cash Expectations, przelewy własne i ruchy kapitału dla lat z arkusza.
- Salda prawdziwych kont na koniec lat 2022–2025 i konta, które w tamtych latach istniały.
- Nowe aktywne kategorie i zmiana listy kategorii poza zarchiwizowaną „Kosmetyki”; kategoria „Codzienne wydatki” (decyzja: do Artykułów spożywczych).
- Edycja i zmiana kategorii operacji z arkusza w UI (blokuje ją polityka z fazy 2, przycisk zostaje i kończy się 403) oraz wpis i Import w latach wczytanych (blokada z fazy 2); poprawka mapowania idzie przez `budget:unload-sheet` i ponowne `budget:load-sheet`. Usuwanie pojedynczej operacji zostaje jak dziś.
- Zmiany widoków i propsów Inertia (strona kont i dashboard tylko filtrują konta arkusza w PHP; kształt propsów bez zmian); ukrywanie starszych lat w selektorze roku.
- `raw_statement_description`, tożsamość importu i marker w opisie: operacje z arkusza mają je puste (podpowiedzi Importu i `import:suggestion-accuracy` ich nie widzą).
- Uruchomienie wczytania przez `scripts/deploy.sh`.

## Implementation Approach

Najpierw dokumenty (twarda zasada AGENTS.md: zmiana zapisanej decyzji i nowe terminy glosariusza idą przed kodem). Potem konto arkusza w schemacie i odczytach, bo od niego zależy „nic się nie zmienia”, i tryb „tylko dodano” numerów kontrolnych, którym testy późniejszych faz sprawdzają zapis. Czytnik xlsx i wyliczenie planu wczytania są czystymi klasami bez bazy (testy na syntetycznych skoroszytach budowanych w teście, bez prawdziwych plików), zapis i wycofanie to akcje wywoływane z dwóch komend. Na końcu próba na odtworzonej pełnej kopii produkcji i wczytanie na produkcji przez użytkownika.

Podjęte decyzje (rejestr z planowania 2026-11-12):

1. Zakres: 2022, 2023, 2024, 2025; jeden parser szukający bloków po etykietach, mapowanie z wariantami etykiet.
2. Konto: osobne konto arkusza na rok z flagą `accounts.sheet_year`, wykluczane z płynności na początek roku (oraz `SpendableLiquidity` i `TotalFunds`), ukryte na dashboardzie i stronie kont, widoczne w filtrze kont operacji.
3. Wiersze: „Analiza jest prawdą” — każdy rok sumuje się do `Analiza!B16`/`C16`; wpłaty na oszczędności i przychód „oszczędności” pomijane; odkładania `**` (i 2022 `*`) są wydatkiem w swojej kategorii; brak w liście to jedna operacja „reszta bez rozpisania”.
4. Codzienne wydatki → Artykuły spożywcze (8); w 2022 według rozpisania (Higiena i kosmetyki → Rzeczy osobiste 29, Pieluchy → Dzieci 32, reszta → 8).
5. Tabela mapowania zaakceptowana (sekcja „Mapowanie” w fazie 5), z wyjątkiem: Kosmetyki*, Kosmetyczka, Fryzjer, „Kosmetyczka i fryzjer” i nieregularne „kosmetyki” → nowa zarchiwizowana kategoria „Kosmetyki”.
6. Plan linii = wykonanie (Initial = Current = suma operacji).
7. Data operacji: ostatni dzień miesiąca; pozycje list jako osobne operacje z nazwą z K, sumy miesięczne jako jedna operacja na wiersz arkusza.
8. Zmiany odczytów z „Desired End State” zaakceptowane.

## Critical Implementation Details

### State sequencing

W zapisie roku kolejność jest ustalona: konto arkusza i Budget, linie z planem 0, bulk insert operacji, potem Execution, Initial i Current Plan każdej linii = suma jej operacji i saldo otwarcia konta = −(przychody − wydatki), na końcu asercja salda bieżącego 0 i Execution = suma. Wszystko w jednej transakcji z blokadą użytkownika; żadna akcja domenowa (`CreateBudget`, `StartBudgetExecution`, `ManageFinancialOperation`, `CreateListCategory`, `JoinBudgetAtFirstUse`) nie jest wywoływana, bo każda z nich zmienia szkic 2027, Rytmy albo salda prawdziwych kont.

### Debug & observability

`--dry-run` jest jedynym podglądem: wypisuje każde ostrzeżenie z adresem komórki, każde przeniesienie ujemnego przychodu, każdą resztę bez rozpisania, sumy roczne per kategoria i porównanie z „Analizą”. Odmowa nigdy nie jest cicha: komenda kończy się kodem 1 z nazwą arkusza i komórki, a transakcja się wycofuje.

## Faza 1: Decyzja i słownik

### Overview

Zapisuje odwrócenie decyzji PRD v3 i nowe terminy przed kodem.

### Changes Required:

#### 1. ADR-0083

**File**: `context/foundation/adr/0083-sheet-load-of-past-years.md`

**Intent**: Zapisuje, dlaczego i jak lata 2022–2025 wchodzą z arkusza na produkcję wbrew PRD v3, z rejestrem decyzji 1–8 z „Implementation Approach”, konsekwencjami (zmienione odczyty, operacje z arkusza nieedytowalne w UI, rok wczytany zamknięty dla wpisu i Importu, poprawka przez wycofanie i ponowne wczytanie) i odrzuconymi wariantami (historia na prawdziwych kontach, konto techniczne bez flagi, pomijanie kont zarchiwizowanych przed 1 stycznia, kategoria „Codzienne wydatki”, plan 0 i 12 × Ustawienia, pierwszy i piętnasty dzień miesiąca).

**Contract**: Format i numeracja jak sąsiednie ADR (`0082-proposal-set-aside-from-default-percent.md`); status Accepted; odwołania do ADR-0063, ADR-0064, ADR-0068, ADR-0074, ADR-0081.

#### 2. Glosariusz

**File**: `context/foundation/glossary.md`

**Intent**: Dodaje **Sheet Account** („konto arkusza”: zarchiwizowane Account jednego roku z arkusza, otwarte 1 stycznia, saldo otwarcia = −wynik roku, saldo bieżące 0 po wczytaniu; poza płynnością na początek roku, Spendable Liquidity i Total Funds; niewidoczne na dashboardzie i stronie kont; bez edycji, przywrócenia i usunięcia; _Avoid_: technical account, Import account, historical account) i **Sheet Load** („wczytanie arkusza”: jednorazowa komenda administracyjna zapisująca miniony rok z xlsx jako Budget w execution ze zwykłymi Financial Operations na Sheet Account; nie Import, bez podglądu i Raw Statement Description; _Avoid_: Import, migration, archival data). Wpis **Account** dostaje zdanie o Sheet Account; reguła daty otwarcia zostaje.

**Contract**: Kolejność i format wpisów jak w glosariuszu (alfabetycznie, `**Term**:` + `_Avoid_:`).

#### 3. PRD v3 i roadmapa

**File**: `context/foundation/prd-v3.md`, `context/foundation/roadmap.md`

**Intent**: Wpisy mówiące, że dane 2025 wchodzą tylko jako mock na devie i produkcja porównuje lata od 2027 (`prd-v3.md:199`, `:256`, `:287`), dostają adnotację „zmienione przez ADR-0083: lata 2022–2025 wczytane z arkusza (legacy-budget-import)”; pozycja parkingu „Import arkusza budżetu, liczniki mediów, wynagrodzenia” (`roadmap.md:379`) traci część o arkuszu budżetu i dostaje odwołanie do ADR-0083.

**Contract**: Treść pozostałych wymagań bez zmian; liczniki mediów i wynagrodzenia zostają w parkingu.

### Success Criteria:

#### Automated Verification:

- ADR istnieje i ma kolejny wolny numer: `ls context/foundation/adr/0083-*.md`
- Glosariusz definiuje oba terminy: `grep -n "^\*\*Sheet Account\*\*\|^\*\*Sheet Load\*\*" context/foundation/glossary.md`

#### Manual Verification:

- ADR-0083 i wpisy glosariusza oddają decyzje 1–8 bez sprzeczności z PRD v3 i roadmapą

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 2: Konto arkusza

### Overview

Kolumna `accounts.sheet_year` i wszystkie miejsca, w których konto arkusza musi zniknąć albo być zablokowane, zanim jakikolwiek rok zostanie wczytany.

### Changes Required:

#### 1. Migracja

**File**: `database/migrations/<timestamp>_add_sheet_year_to_accounts_table.php`

**Intent**: Dodaje nullable `sheet_year` (rok, którego operacje konto trzyma) z unikalnością na użytkownika; `down()` odmawia (`RuntimeException` z liczbą kont), gdy istnieje choć jedno konto arkusza, bo usunięcie kolumny zamieniłoby konta arkusza w zwykłe i zmieniło płynność; komunikat wskazuje `budget:unload-sheet` dla każdego roku, kopię `user:backup` i sekcję README „Wycofanie legacy-budget-import po wdrożeniu” (`.ai/rules/migrations.md:21`).

**Contract**: `accounts.sheet_year` `smallInteger` nullable, `unique(user_id, sheet_year)`; istniejące wiersze `null`; numery kontrolne przed i po `migrate` identyczne. Reguła `.ai/rules/migrations.md` (query builder, test na PostgreSQL wzorem `tests/Feature/Migrations/`).

#### 2. Model i odczyty płynności

**File**: `app/Models/Account.php`, `app/Support/BudgetDeficitWarning.php`, `app/Support/SpendableLiquidity.php`, `app/Support/TotalFunds.php`

**Intent**: Model zna konto arkusza (`isSheetAccount()`, scope kont bez kont arkusza); trzy odczyty płynności pomijają konta arkusza, więc wczytanie nie zmienia płynności szkicu 2027, a późniejsze usunięcie starej operacji nie pokaże pieniędzy, których nie ma.

**Contract**: `sheet_year` w `$fillable`/casts jako `int|null`; `liquidityAtYearStart`, `SpendableLiquidity::forUser`, `TotalFunds::forUser` z warunkiem `sheet_year IS NULL`. Wynik dla użytkownika bez kont arkusza identyczny jak dziś.

#### 3. Widoki i blokady

**File**: `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/AccountController.php`, `app/Policies/AccountPolicy.php`, `app/Policies/FinancialOperationPolicy.php`, `app/Support/CategoryList/OperationCategoryChoice.php`, `app/Console/Commands/{UpdateAccountCommand,RestoreAccountCommand,ArchiveAccountCommand,DeleteAccountCommand}.php`

**Intent**: Dashboard i strona kont nie pokazują kont arkusza (lista i `archived_accounts_count`), polityka odmawia `update`, `delete`, `archive` i `restore` konta arkusza, a komendy CLI kont odmawiają z komunikatem wskazującym `budget:unload-sheet`. Filtr kont na liście operacji zostaje bez zmian (konto arkusza jako zarchiwizowane). Rok wczytany (użytkownik ma konto z `sheet_year` = rok operacji) odmawia wpisu ręcznego i wiersza Importu z własnym komunikatem, a `FinancialOperationPolicy::update` odmawia operacji na koncie arkusza; usuwanie operacji zostaje jak dziś.

**Contract**: Kształt propsów `Dashboard` i `Accounts` bez zmian (tylko mniej elementów); `assertInertia` w testach obu stron potwierdza brak konta arkusza; `account:list --archived` może je pokazywać. Blokada roku w `OperationCategoryChoice` (jedno miejsce dla wpisu i Importu); przycisk edycji operacji z arkusza zostaje w UI i kończy się 403 (bez zmiany propsów).

#### 4. Snapshot w wersji 12

**File**: `app/Actions/BackupUserData.php`, `app/Actions/RestoreUserData.php`, `.ai/rules/snapshot.md`, `tests/Feature/UserSnapshotCommandTest.php`

**Intent**: Snapshot zapisuje `sheet_year` każdego konta, a restore go odtwarza; wersje 1–11 odtwarzają `null`. Restore odrzuca `sheet_year`, który nie jest `null` ani liczbą całkowitą 1–9999, i dwa konta z tym samym rokiem.

**Contract**: `SNAPSHOT_VERSION = 12`; po podbiciu stała oznacza 12, więc literalne `11` trafia obok `BackupUserData::SNAPSHOT_VERSION` we wszystkich siedmiu listach wersji `RestoreUserData` (`LIST_FORMAT_VERSIONS` `:36`, `RHYTHM_VERSIONS` `:44`, akceptowane wersje `:129`, sekcje `:139`, `:143`, `:147`, `:155`), inaczej `:129` odrzuci każdą istniejącą kopię v11; test restore snapshotu w wersji 11 (bez pola) i test round-tripu konta arkusza w wersji 12. Nowy akapit „Version 12” w `.ai/rules/snapshot.md`.

#### 5. README

**File**: `README.md`

**Intent**: Sekcja „Wycofanie legacy-budget-import po wdrożeniu” wzorem „Wycofanie S-33”: kolumna nullable, numery kontrolne identyczne; `down()` odmawia przy istniejących kontach arkusza, więc najpierw `budget:unload-sheet` dla każdego roku; snapshot w wersji 12 nie wczyta się w starym kodzie.

**Contract**: Sekcja pod „Produkcja → Wdrożenie i przywracanie”.

### Success Criteria:

#### Automated Verification:

- Testy migracji, płynności, dashboardu, strony kont, polityki, komend kont, blokady roku wczytanego (wpis, Import, edycja operacji konta arkusza) i snapshotu na PostgreSQL przechodzą (polecenie worktree z AGENTS.md, baza `testing_legacy_budget_import`)
- Test `down()` odmawia przy istniejącym koncie arkusza i usuwa kolumnę bez niego
- Pełna bramka przechodzi: `composer ci:check` i `vendor/bin/sloppy ci`
- `vendor/bin/pint --dirty --format agent` bez zmian

#### Manual Verification:

- Na bazie podglądu worktree ręcznie dodane konto z `sheet_year` nie pojawia się na dashboardzie ani na stronie kont (także z „pokaż zarchiwizowane”), a liczby płynności i funduszy się nie zmieniają

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 3: Numery kontrolne „tylko dodano”

### Overview

Dowód, że wczytanie tylko dodało wiersze: sekcja kategorii w numerach kontrolnych i porównanie, które przepuszcza wyłącznie dozwolone dodania.

### Changes Required:

#### 1. Sekcja kategorii

**File**: `app/Support/ControlNumbers.php`

**Intent**: Numery kontrolne obejmują każdą kategorię listy po id (typ, klucz nazwy, archiwum, grupa, liczba wpisów Rytmu), bo dziś lista i Rytmy są tylko licznikami w `meta`, które `differences()` pomija. Każde konto w sekcji `accounts` dostaje `archived` i `sheet_year`, żeby klasyfikator rozpoznał konto arkusza.

**Contract**: Nowy klucz `categories` w `collect()`, `VERSION` podbite; `accounts.<id>.archived` (bool) i `accounts.<id>.sheet_year` (`null`, gdy kolumny nie ma, więc „przed” na starym schemacie i „po” migracji tego wdrożenia są identyczne); numery przed i po `migrate` tego samego kodu nadal identyczne (`tests/Feature/ControlNumbersCommandTest.php`).

#### 2. Klasyfikator dodań

**File**: `app/Support/ControlNumbers.php`, `app/Console/Commands/ControlNumbersCommand.php`

**Intent**: `--compare=<plik> --allow-additions` kończy się kodem 0 tylko wtedy, gdy żadna istniejąca ścieżka nie zmieniła wartości ani nie zniknęła, a nowe ścieżki są wyłącznie: nowe Budżety, linie nowych Budżetów (linia dołączona do istniejącego Budżetu jest naruszeniem, nawet z planem 0), nowe konta zarchiwizowane z `sheet_year` i saldem bieżącym 0, nowe kategorie zarchiwizowane (wpisy Rytmu widać w sekcji `categories`); `row_counts` rosną tylko w `budgets`, `budget_lines`, `financial_operations`, `accounts`; `max_financial_operation_id` może rosnąć. Wypisuje podsumowanie dodań i każde naruszenie.

**Contract**: Metoda w rodzaju `ControlNumbers::additionViolations(array $before, array $after): list<string>` plus podsumowanie liczności dodań; bez `--allow-additions` zachowanie komendy bez zmian (deploy).

### Success Criteria:

#### Automated Verification:

- Testy klasyfikatora: dodanie Budżetu z liniami i kontem przechodzi; zmiana kwoty istniejącej linii, linia w istniejącym Budżecie, nowa aktywna kategoria, zmiana istniejącej kategorii, nowe konto z saldem ≠ 0, nowe aktywne konto (albo bez `sheet_year`) z saldem 0 i wzrost `month_plans` są naruszeniami
- `tests/Feature/ControlNumbersCommandTest.php` przechodzi na PostgreSQL
- Pełna bramka przechodzi: `composer ci:check` i `vendor/bin/sloppy ci`

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych przejdź dalej (brak kroków ręcznych).

---

## Faza 4: Czytnik arkusza

### Overview

Czysta klasa, która z pliku xlsx robi strukturę roku z adresami komórek, znajdując bloki po etykietach, i zbiera ostrzeżenia z asercji arkusza.

### Changes Required:

#### 1. Czytnik

**File**: `app/Support/SheetLoad/BudgetSheetReader.php` (i klasy wartości w `app/Support/SheetLoad/`)

**Intent**: Czyta cały skoroszyt (zapisane wartości wyliczone, bez przeliczania), rok z `Ustawienia!B2`, 12 arkuszy miesięcy po nazwach, `Analiza!B16`/`C16`. W każdym miesiącu znajduje bloki po etykietach: wiersze wydatków w B od wiersza 3 do wiersza „Wydatki nieregularne” (puste wiersze dozwolone), wiersz bilansu zaraz po nim; przychody w G od wiersza 3 do wiersza „oszczędności” włącznie; listę nieregularnych od wiersza po J „kategoria” do wiersza przed J „Opieka zdrowotna”; listę leczenia pod nagłówkiem „data | nazwa | plan | wydatek” po J „Leczenie prywatne” do J „Badania”; listę badań pod „Badania” do pierwszego pustego wiersza; w 2022 rozpisanie Codziennych pod G „Codzienne” (G25:I31). Kwoty zamienia na grosze od razu przy odczycie komórki.

**Contract**: `read(string $path): BudgetSheet`; każda pozycja niesie etykietę znormalizowaną (trim, małe litery Unicode, bez końcowych `*`, „praca:”≡„work:”) i surową, kwotę w groszach i adres `arkusz!komórka`. W listach pomija wiersze bez K/L/M i wiersze z kreskami (`^-{2,}$`) w K lub M w dowolnym miejscu listy; J puste lub „-” to brak kategorii; pozycja bez nazwy jest dozwolona; kwoty ujemne są dozwolone. Odmowa (wyjątek z adresem): brak arkusza miesiąca, brak wymaganej etykiety, tekst inny niż kreski w komórce kwoty. Ostrzeżenia (nie odmowa): suma listy ≠ suma bloku (dla nieregularnych po odjęciu „Wydatki z funduszu”), rozpisanie Codziennych ≠ D, bilans miesiąca ≠ 0.

#### 2. Fabryka skoroszytów testowych

**File**: `tests/Support/SheetWorkbookFactory.php` (albo zgodnie z konwencją katalogu pomocników testów)

**Intent**: Buduje w teście małe pliki xlsx w dwóch układach (2023–2025 i 2022) z wartościami literalnymi, bez prawdziwych danych użytkownika w repo.

**Contract**: Pliki tymczasowe tworzone i usuwane w teście; parametry pozwalają wstawić przypadki brzegowe.

### Success Criteria:

#### Automated Verification:

- Testy czytnika przechodzą dla obu układów: przesunięcie stycznia (1 i 2 wiersze), 8 źródeł przychodu i puste H, wiersz kresek na początku listy, kategoria „-” i pusta, pozycja bez nazwy, kwota ujemna, luka listy jako ostrzeżenie, bilans ≠ 0 jako ostrzeżenie, brak „Opieka zdrowotna” jako odmowa z adresem, tekst w komórce kwoty jako odmowa
- Pełna bramka przechodzi: `composer ci:check` i `vendor/bin/sloppy ci`

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych przejdź dalej (brak kroków ręcznych; czytnik na prawdziwych plikach sprawdza faza 5).

---

## Faza 5: Mapowanie i plan wczytania

### Overview

Plik mapowania z zaakceptowaną tabelą i czyste wyliczenie operacji, linii i salda konta arkusza z asercją zgodności z „Analizą”; komenda w trybie `--dry-run`.

### Changes Required:

#### 1. Plik mapowania

**File**: `database/sheet-load/mapping.php`

**Intent**: Jedno miejsce z tabelą zaakceptowaną przez użytkownika. Wiersze wydatków i przychodów, kategorie J listy nieregularnych i pozycje rozpisania Codziennych 2022 wskazują id kategorii listy (z oczekiwaną nazwą i typem do sprawdzenia na bazie docelowej) albo `skip` (tylko wpłata na oszczędności „Oszczędności długoterminowe” i przychód „oszczędności”) albo nową kategorię `kosmetyki`. Zawiera też nadpisania kwot w komórkach i specyfikację nowej kategorii.

**Contract**: Mapowanie (warianty etykiet w jednym wpisie):

| Arkusz | Kategoria |
| --- | --- |
| Rata kredytu | Kredyt (9) |
| Czynsz | Czynsz (11) |
| Telefony; Internet | Telefon + Internet (14) |
| Abonamenty | Rozrywka (23) |
| Prąd | Prąd (15) |
| Przedszkole; Żłobek; Żłobek/przedszkole | Przedszkole (18) |
| Ubezpieczenie | Ubezpieczenia (19) |
| Codzienne wydatki | Artykuły spożywcze (8); w 2022 według rozpisania niżej |
| 2022 rozpisanie: Jedzenie; Słodycze i inne; Środki czystości; Pomoce kuchenne / Higiena i kosmetyki / Pieluchy | 8 / Rzeczy osobiste (29) / Dzieci (32) |
| 2022 Środki czystości | Artykuły spożywcze (8) |
| 2022 Jedzenie poza domem | Restauracje (24) |
| Kosmetyki; Kosmetyczka; Fryzjer; Kosmetyczka i fryzjer | Kosmetyki (nowa, zarchiwizowana) |
| Apteka | Apteka (12) |
| Badania; Dentysta - ortodoncja; Leczenie prywatne (oraz listy leczenia i badań) | Leczenie prywatne (20) |
| Bilety - parking - inne | Bilety i parking (22) |
| Ubezpieczenie auta | Ubezpieczenie samochodu (21) |
| Fundusz remontowy | Serwis samochodu (17) |
| Paliwo | Paliwo (16) |
| Rozrywka i wyjścia | Rozrywka (23) |
| Wyjazdy i wycieczki | Wakacje (25, zarchiwizowana) |
| Amortyzacja; Fundusz Wydatków Nieregularnych | Pozostałe (10) |
| Oszczędności długoterminowe | skip |
| Wydatki nieregularne | według J listy: Ubrania 27; Osobiste 29; kosmetyki → Kosmetyki; Usługi 33; Prezenty 31; Edukacja 30; Darowizny 28; Książki 34; Dziecko 32; Elektronika 36; Eksploatacja 35; Dom, wyposażenie, wyposażanie, meble 37; Praca 10; puste i „-” 10 |
| Reszta bez rozpisania: nieregularne / leczenie i badania / Codzienne | 10 / 20 / 8 |
| Przychody: Sebastian 1; Jana 2; 500+ 3; darowizny 4; odsetki 5; handel 39; Ukraina, przychody nieregularne, nieregularne, pozostałe, inne 7; oszczędności skip | jak obok |
| Nadpisania: 2023-02 pozostałe 6 831,07, 2024-02 pozostałe 5 823,20, 2025-02 pozostałe 5 790,12 | Zwrot podatku (6), opis „Zwrot podatku” |

Nowa kategoria: nazwa „Kosmetyki”, typ wydatek, ikona i kolor z dozwolonego zestawu walidacji kategorii, Rytm Running od 2022-01. Id 1–38 pochodzą z kopii produkcji na devie; oczekiwane nazwy (np. „Restaruacje”, „Wynagrodzenie Janeczka”) potwierdza próba w fazie 7.

#### 2. Plan wczytania

**File**: `app/Support/SheetLoad/SheetLoadPlanner.php` (i klasy wartości)

**Intent**: Z `BudgetSheet` i mapowania wylicza listę operacji (data, typ, kategoria, kwota, opis, adres źródła), sumy per kategoria, saldo otwarcia konta arkusza i ostrzeżenia, bez dostępu do bazy. Odmawia, gdy etykieta nie ma wpisu w mapowaniu, gdy nadpisanie przekracza kwotę komórki, gdy roczna suma źródła przychodu jest ≤ 0, i gdy suma przychodów lub wydatków ≠ `Analiza!B16`/`C16` co do grosza.

**Contract**: Reguły:
- Data każdej operacji: ostatni dzień miesiąca arkusza.
- Wiersz z kategorią: jedna operacja na miesiąc, gdy kwota ≠ 0; ujemny wydatek to zwrot wydatku.
- Bloki z listą (nieregularne, leczenie, badania, Codzienne 2022 z rozpisaniem): jedna operacja na pozycję listy, a różnica suma bloku − suma listy (dla nieregularnych po odjęciu „Wydatki z funduszu”) ≠ 0 daje jedną operację „reszta bez rozpisania” w kategorii reszty i ostrzeżenie.
- Ujemna komórka przychodu przechodzi do najbliższego późniejszego miesiąca tego samego źródła (gdy żaden nie ma dość, do najbliższego wcześniejszego), aż każda operacja przychodu ma kwotę ≥ 1; każde przeniesienie to ostrzeżenie.
- Nadpisanie odejmuje kwotę od operacji komórki i dodaje osobną operację w kategorii nadpisania.
- Opisy: wiersz „<etykieta arkusza> (arkusz RRRR)”; pozycja listy nazwą z K, a bez nazwy „bez nazwy (arkusz RRRR)”; reszta „reszta bez rozpisania: <blok> (arkusz RRRR, <miesiąc>)”; długość w limicie kolumny `description`.
- Saldo otwarcia konta arkusza = −(suma przychodów − suma wydatków).

#### 3. Komenda w trybie podglądu

**File**: `app/Console/Commands/LoadBudgetSheetCommand.php`

**Intent**: `budget:load-sheet {user} {path} {--dry-run} {--force}`; w tej fazie działa tylko `--dry-run` (bez niego kończy się kodem 1 z informacją, że zapis przychodzi w fazie 6). Wypisuje ostrzeżenia z adresami, przeniesienia, reszty, sumy roczne per kategoria (id, nazwa, kwota), porównanie z „Analizą”, liczbę operacji i saldo otwarcia konta arkusza; sprawdza na bazie, że każde id mapowania należy do użytkownika i ma oczekiwaną nazwę i typ.

**Contract**: Konwencje komend projektu (`#[Signature]`, `ResolvesConsoleUser`); kod 0 przy poprawnym planie, kod 1 przy odmowie; bez zapisu do bazy.

### Success Criteria:

#### Automated Verification:

- Testy planu: sumy równe „Analizie”, nadpisanie zwrotu podatku, przeniesienie ujemnego przychodu (do przodu i wstecz), reszta bez rozpisania w trzech blokach, rozpisanie Codziennych 2022, „-” i puste J do Pozostałych, kosmetyki do nowej kategorii, odmowa przy nieznanej etykiecie, przy niezgodności z „Analizą” i przy złej nazwie kategorii na bazie
- Test komendy `--dry-run` nie zmienia numerów kontrolnych
- Pełna bramka przechodzi: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- `budget:load-sheet <user> <plik> --dry-run` na czterech prawdziwych plikach (baza dev) kończy się kodem 0, a sumy przychodów i wydatków równają się tabeli z `research.md` (addendum) dla 2022, 2023, 2024 i 2025
- Ostrzeżenia obejmują 2022 czerwiec (nieregularne, Codzienne, leczenie, badania) i 2024 wrzesień (bilans 86,58), a sumy per kategoria wyglądają sensownie dla użytkownika

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 6: Zapis i wycofanie

### Overview

Akcje zapisu i wycofania roku oraz pełne działanie obu komend, z testami na PostgreSQL dowodzącymi „tylko dodano” i odwracalności.

### Changes Required:

#### 1. Zapis roku

**File**: `app/Actions/LoadBudgetSheet.php`, `app/Console/Commands/LoadBudgetSheetCommand.php`

**Intent**: Zapisuje plan wczytania w jednej transakcji (kolejność z „State sequencing”). Odmawia, gdy użytkownik ma już Budget tego roku albo konto arkusza tego roku, gdy kategoria mapowania nie ma oczekiwanej nazwy i typu. Kategorię „Kosmetyki” bierze istniejącą (kategoria wydatku o tym kluczu nazwy) bez zapisu Rytmu albo tworzy zarchiwizowaną, bez `JoinDraftBudgets`, z jednym wpisem Rytmu Running od 2022-01 przez `ChangeCategoryRhythm`. `ChangeCategoryRhythm` wołany jest tylko dla kategorii utworzonej w tej transakcji, bo kasuje wpisy od podanego miesiąca (`app/Actions/ChangeCategoryRhythm.php:106-111`) i na istniejącej kategorii skasowałby jej historię Rytmu. Komenda bez `--dry-run` pokazuje podsumowanie i pyta o potwierdzenie (bez `--force`), po zapisie wypisuje id Budżetu, konta i liczbę operacji.

**Contract**: Budget `phase = execution`; konto „Arkusz RRRR”, typ gotówka (bez banku), `opening_date` = RRRR-01-01, `archived_at` = chwila zapisu, `sheet_year` = RRRR; linie tylko dla kategorii z operacjami, Initial = Current = Execution = suma; operacje z `raw_statement_description`, tożsamością importu i kontrahentem `null`, wstawiane paczkami; żadnych planów miesięcy.

#### 2. Wycofanie roku

**File**: `app/Actions/UnloadBudgetSheet.php`, `app/Console/Commands/UnloadBudgetSheetCommand.php`

**Intent**: `budget:unload-sheet {user} {year} {--force}` usuwa operacje, linie i Budget roku oraz jego konto arkusza; odmawia, gdy Budget ma operacje na innym koncie, plany miesięcy albo gdy konto arkusza ma inne rekordy. Usuwa też „Kosmetyki”, gdy jest zarchiwizowana, nie ma już linii ani pozycji planów miesięcy, a jej historia Rytmu to dokładnie jeden wpis Running od 2022-01 (czyli wpis wczytania); kategoria utworzona przez użytkownika zostaje.

**Contract**: Jedna transakcja; po wycofaniu wszystkich wczytanych lat `app:control-numbers --compare=<przed wczytaniem>` nie pokazuje różnic.

### Success Criteria:

#### Automated Verification:

- Test na PostgreSQL: użytkownik z Budżetem 2026 w execution (operacje, plany miesięcy), szkicem 2027 i kontami; wczytanie syntetycznego 2025 i 2022 przez komendę; `app:control-numbers --compare … --allow-additions` przechodzi; `BudgetDeficitWarning` 2027, `SpendableLiquidity`, `TotalFunds`, propsy dashboardu i strony kont identyczne jak przed
- Test: linie wczytanego roku mają Initial = Current = Execution = suma operacji, saldo bieżące konta arkusza 0, szkic 2027 bez nowych linii, brak nowej aktywnej kategorii, „Kosmetyki” zarchiwizowana z jednym wpisem Rytmu
- Test: raport roku 2026 ma kolumnę roku poprzedniego z kwotami 2025 (zaakceptowana zmiana)
- Test: wycofanie obu lat przywraca numery kontrolne bez różnic; drugie wczytanie tego samego roku odmawia; zła nazwa kategorii odmawia bez zapisu; po wczytaniu wpis ręczny i wiersz Importu z datą 2025-12-31 na prawdziwym koncie otwartym 2025-12-31 oraz edycja operacji z arkusza odmawiają, a istniejąca „Kosmetyki” zachowuje swoje wpisy Rytmu
- Pełna bramka przechodzi: `composer ci:check` i `vendor/bin/sloppy ci`

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych przejdź dalej (kroki ręczne są w fazie 7).

---

## Faza 7: Próba i produkcja

### Overview

Procedura w README, próba na odtworzonej pełnej kopii produkcji i wczytanie czterech lat na produkcji przez użytkownika.

### Changes Required:

#### 1. Procedura w README

**File**: `README.md`

**Intent**: Sekcja „Wczytanie arkusza (lata 2022–2025)” pod „Produkcja”: wdrożenie zmiany (`scripts/deploy.sh`, migracja `sheet_year`), skopiowanie plików do `storage/app/private/` kontenera produkcji, `scripts/artisan.sh user:backup`, `app:control-numbers --output=<przed>`, `--dry-run` każdego pliku, wczytanie 2022, 2023, 2024, 2025, `app:control-numbers --compare=<przed> --allow-additions`, przegląd w aplikacji, wycofanie (`budget:unload-sheet` dla każdego roku, potem porównanie bez `--allow-additions`). Próba lokalna: `backup:restore` archiwum `backup:run` z produkcji według istniejących kroków README „Próba lokalna (bez nadpisania bazy deweloperskiej)” (osobna baza `restore_trial`, `config:clear` i `db:show` pokazujące bazę docelową przed restore, odłożenie `storage/app/private` i `public`, bo restore kopiuje też pliki, sprzątanie), potem ta sama procedura wczytania na `restore_trial`.

**Contract**: Polecenia przez `scripts/artisan.sh` (próba lokalna przez `docker compose exec -e DB_DATABASE=restore_trial`, jak w README); ścieżki plików w kontenerze; nazwy plików kontrolnych z datą jak w `scripts/deploy.sh`.

### Success Criteria:

#### Automated Verification:

- README ma sekcję procedury i sekcję wycofania: `grep -n "Wczytanie arkusza" README.md`

#### Manual Verification:

- Próba na odtworzonej kopii produkcji: `--dry-run` czterech plików przechodzi (oczekiwane nazwy kategorii zgodne z produkcją), wczytanie przechodzi, `--allow-additions` przechodzi, a raport „ostatnie 12 miesięcy”, raport 2026 z kolumną 2025, Budget 2024 i „Kategorie” wyglądają poprawnie
- Na próbie wycofanie wszystkich lat przywraca numery kontrolne bez różnic, a ponowne wczytanie przechodzi
- Na produkcji: `user:backup`, numery kontrolne przed, wczytanie 2022–2025, `--allow-additions` przechodzi, saldo „Na kontach”, dashboard i szkic 2027 bez zmian

**Implementation Note**: Krok produkcyjny wykonuje użytkownik; agent przygotowuje polecenia i sprawdza wyniki, które użytkownik wklei.

---

## Testing Strategy

### Unit Tests:

- Czytnik na syntetycznych skoroszytach obu układów (faza 4) i plan wczytania (faza 5): granice list, kreski, puste i „-” kategorie, kwoty ujemne, reszty, przeniesienia przychodów, nadpisania, zgodność z „Analizą”.
- Klasyfikator dodań numerów kontrolnych (faza 3).

### Integration Tests:

- PostgreSQL (baza worktree `testing_legacy_budget_import`): migracja z `down()`, odczyty płynności, propsy dashboardu i strony kont, snapshot v11 → v12, wczytanie i wycofanie z numerami kontrolnymi przed i po (fazy 2, 6).

### Manual Testing Steps:

1. `--dry-run` czterech prawdziwych plików na devie; sumy zgodne z tabelą addendum.
2. Próba na odtworzonej kopii produkcji: wczytanie, `--allow-additions`, przegląd raportów i „Kategorii”, wycofanie, ponowne wczytanie.
3. Produkcja według README.

## Performance Considerations

Około 2 000 operacji na cztery lata; bulk insert paczkami jak w seederze. Wczytanie skoroszytu (ok. 1 MB) trwa sekundy; komenda wczytuje jeden plik naraz.

## Migration Notes

Migracja dodaje jedną nullable kolumnę i nie zmienia wierszy; numery kontrolne przed i po `migrate` identyczne. Wycofanie po wdrożeniu bez utraty operacji: `budget:unload-sheet` dla każdego wczytanego roku (operacje z arkusza nie są pracą użytkownika i dają się wczytać ponownie), potem `migrate:rollback` nowym kodem i cofnięcie kodu jak w „Wycofanie S-18”. Snapshot w wersji 12 nie wczyta się w kodzie sprzed zmiany. Kopia `user:backup` sprzed wczytania jest drogą awaryjną, gdy wycofanie odmówi.

## References

- Research: `context/changes/legacy-budget-import/research.md` (z addendum dla 2022–2024)
- Wzór zapisu: `database/seeders/PreviousYearMockSeeder.php:38-250`
- Odczyt xlsx: `app/Support/CsvImport/BnpParibasXlsxParser.php`
- Numery kontrolne: `app/Support/ControlNumbers.php`, `app/Console/Commands/ControlNumbersCommand.php`
- Reguły: `.ai/rules/snapshot.md`, `.ai/rules/migrations.md`, `.ai/rules/categories.md`, `.ai/rules/sloppy.md`, `.ai/rules/reports.md`
- ADR-0063, ADR-0064, ADR-0068, ADR-0074, ADR-0081; PRD v3 „Migracja danych” (`context/foundation/prd-v3.md:245-251`)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzja i słownik

#### Automated

- [x] 1.1 ADR istnieje i ma kolejny wolny numer — baf4405
- [x] 1.2 Glosariusz definiuje oba terminy — baf4405

#### Manual

- [x] 1.3 ADR-0083 i wpisy glosariusza oddają decyzje 1–8 bez sprzeczności z PRD v3 i roadmapą — baf4405

### Phase 2: Konto arkusza

#### Automated

- [x] 2.1 Testy migracji, płynności, dashboardu, strony kont, polityki, komend kont, blokady roku wczytanego i snapshotu na PostgreSQL przechodzą — bae1d56
- [x] 2.2 Test down() odmawia przy istniejącym koncie arkusza i usuwa kolumnę bez niego — bae1d56
- [x] 2.3 Pełna bramka przechodzi: composer ci:check i vendor/bin/sloppy ci — bae1d56
- [x] 2.4 vendor/bin/pint --dirty --format agent bez zmian — bae1d56

#### Manual

- [x] 2.5 Konto z sheet_year niewidoczne na dashboardzie i stronie kont, płynność i fundusze bez zmian — bae1d56

### Phase 3: Numery kontrolne „tylko dodano”

#### Automated

- [x] 3.1 Testy klasyfikatora dodań i naruszeń przechodzą — ddddabd
- [x] 3.2 tests/Feature/ControlNumbersCommandTest.php przechodzi na PostgreSQL — ddddabd
- [x] 3.3 Pełna bramka przechodzi: composer ci:check i vendor/bin/sloppy ci — ddddabd

### Phase 4: Czytnik arkusza

#### Automated

- [x] 4.1 Testy czytnika przechodzą dla obu układów i przypadków brzegowych — a3cc98f
- [x] 4.2 Pełna bramka przechodzi: composer ci:check i vendor/bin/sloppy ci — a3cc98f

### Phase 5: Mapowanie i plan wczytania

#### Automated

- [x] 5.1 Testy planu wczytania przechodzą
- [x] 5.2 Test komendy --dry-run nie zmienia numerów kontrolnych
- [x] 5.3 Pełna bramka przechodzi: composer ci:check i vendor/bin/sloppy ci

#### Manual

- [x] 5.4 --dry-run czterech prawdziwych plików daje sumy z tabeli addendum
- [x] 5.5 Ostrzeżenia obejmują 2022 czerwiec i 2024 wrzesień, sumy per kategoria sensowne

### Phase 6: Zapis i wycofanie

#### Automated

- [ ] 6.1 Wczytanie przechodzi --allow-additions, odczyty 2026/2027 identyczne
- [ ] 6.2 Linie, konto arkusza, szkic 2027 i Kosmetyki zgodne z kontraktem
- [ ] 6.3 Raport roku 2026 ma kolumnę roku poprzedniego z kwotami 2025
- [ ] 6.4 Wycofanie przywraca numery kontrolne, drugie wczytanie, zła nazwa, wpis 2025-12-31 i edycja operacji z arkusza odmawiają
- [ ] 6.5 Pełna bramka przechodzi: composer ci:check i vendor/bin/sloppy ci

### Phase 7: Próba i produkcja

#### Automated

- [ ] 7.1 README ma sekcję procedury i sekcję wycofania

#### Manual

- [ ] 7.2 Próba na odtworzonej kopii produkcji przechodzi z przeglądem raportów i Kategorii
- [ ] 7.3 Na próbie wycofanie przywraca numery kontrolne, ponowne wczytanie przechodzi
- [ ] 7.4 Produkcja: wczytanie 2022–2025, --allow-additions przechodzi, salda, dashboard i szkic 2027 bez zmian
