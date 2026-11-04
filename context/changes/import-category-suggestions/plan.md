# Podpowiedź kategorii w podglądzie Importu — plan implementacji

## Overview

Wiersz podglądu Importu dostaje wstępnie wybraną kategorię („✦ podpowiedź”), liczoną w PHP z wag słów we wcześniejszych operacjach użytkownika pochodzących z Importu. Waga słowa zależy od tego, przy ilu różnych kategoriach to słowo występowało. Szum płatności kartą (imię i nazwisko, miasto, `POL`, `PLN`) ma wagę bliską 0, a nazwa sklepu przy jednej kategorii ma wagę bliską 1. Bez wspólnego słowa o dużej wadze podpowiedzi nie ma. Funkcja nie zmienia typu ani zaznaczenia wiersza, a walidacja zapisu działa bez zmian.

## Current State Analysis

Pełne dowody: `context/changes/import-category-suggestions/research.md`. W skrócie:

- **Potok podglądu:** `CsvImportController::preview` buduje wiersze (`app/Http/Controllers/CsvImportController.php:94-107`), wzbogaca je kolejno detektorem duplikatów i detektorem par (`:109-110`), a potem zapisuje do sesji (`:112-115`). `showPreview` przekazuje `rows` z sesji bez zmian (`:120-150`), więc nowy klucz wiersza dociera do widoku bez zmian w sesji.
- **Kategoria jest rekordem jednego Budgetu.** `CreateBudget::copyCategories` nie zapisuje źródła (`app/Actions/CreateBudget.php:29-51`, ADR-0014). Walidator zapisu wymaga kategorii z Budgetu roku **daty wiersza**, w fazie execution, zgodnego typu i bez archiwizacji (`app/Support/CsvImport/FinancialOperationImportValidator.php:56-82,125-169`). Frontend filtruje opcje tak samo (`resources/js/lib/import-preview-rows.ts:94-108`). Notatka w `change.md` („mapujemy na bieżący rok”) jest tu poprawiona: celem jest rok daty wiersza.
- **Precedens mapowania po nazwie i typie:** ADR-0016 (`app/Support/OperationsListQuery.php:139-147`, dokładne `name` i `type`) oraz ADR-0033 (`app/Support/MonthPlanProposal.php:171-187`, „dokładnie jedno dopasowanie”). Nazwy kategorii nie są unikalne (`app/Concerns/CategoryValidationRules.php:22`, brak indeksu unique).
- **Frontend:** każdy wiersz startuje z `category_id: ''` (`import-preview-rows.ts:55-92`). Zmiana typu czyści kategorię (`resources/js/components/ImportPreviewRow.vue:218-222`). `CategorySelect` ma ukryty `<input>`, więc wstępne `category_id` trafia do zapisu bez zmian w submitcie. `CategorySelect` nie ma dziś propu na `aria-describedby` (`resources/js/components/CategorySelect.vue:13-25`).
- **Prototyp** (`prototype/eval_suggest.php`) ma algorytm zmierzony na lokalnych danych. Nie filtruje `user_id`, głosuje po samej nazwie kategorii i czyta całą historię dla każdego wiersza (O(wiersze × historia)).
- **Dokumentacja:** funkcji nie ma w PRD. FR-IMP-11 (`.docs/prd.md:258`) mówi „Kategorię … użytkownik wskazuje w podglądzie”, a „Poza zakresem” zawiera „funkcje AI” (`.docs/prd.md:514`). Settlement candidate ma w `_Avoid_` „similar transaction” (`CONTEXT.md:201`).

## Desired End State

- Każdy poprawny wiersz podglądu z datą ma w propsie `rows[i].category_suggestion = {income: ?int, expense: ?int}`. To id kategorii z Budgetu roku daty wiersza (execution, bez archiwizacji, zgodny typ, dokładnie jedna kategoria o tej nazwie) albo `null`. Wiersze niepoprawne i bez daty mają `{income: null, expense: null}`.
- Wiersz startuje z kategorią podpowiedzi dla swojego typu: `income` bierze `income`, a `expense` i `refund` biorą `expense`. Wiersz `own_transfer` i `capital_movement` startuje bez kategorii. Zmiana typu wstawia podpowiedź nowego typu albo puste pole. Zaznaczenie wiersza i typ domyślny są takie jak dziś.
- Pod selektem kategorii stoi „✦ podpowiedź” (`text-muted-foreground text-xs`, połączone `aria-describedby`), dopóki wybrana kategoria jest równa podpowiedzi bieżącego typu.
- Słownik, ADR-0034 i PRD opisują funkcję. Kitchen sink `/dev/import-preview` pokazuje stan z podpowiedzią.
- Podgląd 300 wierszy przy 10 000 operacji z Importu w historii kończy się w czasie poniżej 2 s (test NFR). Ręczny pomiar na lokalnych danych daje wyniki nie gorsze niż prototyp.

### Key Discoveries:

- Wzorzec wzbogacania: `ImportPairCandidateDetector::annotateRows` (`app/Support/CsvImport/ImportPairCandidateDetector.php:42-100`): zapytania wsadowe i domyślna wartość klucza w każdym wierszu.
- Wzorzec testu NFR: `tests/Feature/Nfr/NfrPerf01Test.php` (grupa `nfr`, tylko PostgreSQL, `markTestSkipped` poza pgsql, seed przez `DB::table` i pomiar czasu żądania).
- Feature testy Pest: `uses(RefreshDatabase::class)` w pliku (np. `tests/Feature/HandEntryMonthRemainingTest.php:12`). `tests/Unit` działa bez bazy.
- Kitchen sink: `resources/js/lib/import-preview-fixtures.ts` (fabryka `row()` z domyślnymi polami `:24-39`, `importPreviewSelections()` z nadpisaniami `:187+`) oraz `resources/js/pages/dev/ImportPreviewSink.vue`.
- Kontrakt propsów Inertia: PHP i ręczny typ TS zmieniają się w jednym commicie, a test Feature potwierdza je przez `assertInertia` (AGENTS.md, „Inertia props contract”).

## What We're NOT Doing

- Uczenie się z wpisów ręcznych (historia to tylko operacje z `raw_statement_description IS NOT NULL`).
- Podpowiedź kategorii odsetek przy Capital Movement (`interest_category_id`).
- Zmiana typu wiersza albo jego zaznaczenia na podstawie podpowiedzi (ADR-0011, ADR-0015).
- Zapisywanie podpowiedzi, informacji „ta kategoria przyszła z podpowiedzi” albo odrzuconych podpowiedzi. Nie ma migracji.
- Ważenie świeżości, uczenie się z odrzuceń i telemetria trafności.
- Typesense, `pg_trgm`, pełnotekstowe wyszukiwanie w PostgreSQL ani nowa usługa.
- Tożsamość Category ponad latami. Zmiana nazwy kategorii rozdziela historię, zgodnie z ADR-0016.
- Asercja progu trafności w CI. Próg sprawdza ręczny pomiar na lokalnych danych, a dane bankowe nie trafiają do repo.
- Kolejność pierwszeństwa względem Settlement candidate (ADR-0020). Cash Expectation nie jest zaimplementowane.
- Podpowiedź w formularzu ręcznego wpisu operacji.

## Implementation Approach

Algorytm prototypu (wariant B) staje się czystą klasą bez bazy. Serwis w stylu detektorów ładuje historię jednym zapytaniem, buduje z niej model i dla każdego wiersza liczy nazwę kategorii osobno dla `income` i `expense`. Potem mapuje nazwę na id w Budgecie roku daty wiersza przez `ImportCategoryOptions`, tę samą klasę zapytań, z której `showPreview` bierze listy opcji. Wynik jest liczony raz przy `POST preview` i zamrażany w sesji jak reszta wiersza. Frontend wybiera z dwóch wartości tę dla bieżącego typu przez czyste helpery w `import-preview-rows.ts`. Helpery sprawdzają, że id jest na liście opcji wiersza. Dokumentacja domenowa idzie pierwsza, żeby kod miał nazwę i granice z `CONTEXT.md`.

## Critical Implementation Details

- **Kolejność w `onTypeChange`:** blokada `typeLockedToOwnTransfer` może nadpisać typ na `own_transfer` (`ImportPreviewRow.vue:225-227`). Kategorię z podpowiedzi trzeba ustawić po ustaleniu ostatecznego typu. Inaczej wiersz zablokowany na transfer własny dostanie kategorię.
- **Osłona listy opcji:** `CategorySelect` renderuje chip tylko dla id obecnego w przefiltrowanej liście (`CategorySelect.vue:43-45`). Sesja zamraża podpowiedź przy `POST preview`, a `showPreview` liczy listy opcji przy każdym `GET`. Kategoria zarchiwizowana w międzyczasie musi więc dawać puste pole, nie niewidoczne id. Stąd sprawdzenie w `suggestedCategoryId` przez `categoriesForRow`.
- **Remisy są deterministyczne:** sąsiadów sortujemy po podobieństwie malejąco, potem po `operation_date` malejąco, potem po `id` malejąco. Remis najwyższej sumy głosów między dwiema nazwami daje brak podpowiedzi.

## Phase 1: Słownik i decyzje domenowe

### Overview

Nazwać funkcję i zapisać granice, zanim powstanie kod: hasło w słowniku, ADR, wymaganie i kryterium akceptacji w PRD oraz wymaganie wydajności.

### Changes Required:

#### 1. Hasło w słowniku

**File**: `CONTEXT.md`

**Intent**: Dodać hasło **Import category suggestion** bezpośrednio po Settlement candidate (`CONTEXT.md:199-201`), żeby następne zmiany używały tej nazwy i nie myliły jej z nakładkami par ani z dopasowaniem rozliczeń.

**Contract**: Format hasła jak sąsiednie: `**Import category suggestion**:` → definicja → `_Avoid_: …`. Definicja mówi, że:
- to nie rekord, tylko odczyt liczony przy podglądzie, osobno dla Income i dla Expense (zwrot używa Expense);
- uczy się wyłącznie z Financial Operations tego użytkownika pochodzących z Importu (Raw Statement Description niepusty);
- słowo waży tym mniej, przy im większej liczbie par nazwa+typ kategorii występowało;
- cel to jedyna aktywna Category o tej nazwie i typie w Budgecie w execution z roku daty wiersza (zapytanie, nie tożsamość Category);
- nie zmienia typu ani zaznaczenia wiersza i niczego nie zapisuje.

`_Avoid_`: auto-categorisation, AI categorisation, category mapping (bank), category match, similar transaction.

#### 2. ADR

**File**: `.docs/adr/0034-import-category-suggestion-from-word-weights.md` (nowy)

**Intent**: Zapisać decyzję i odróżnić ją od zapisanych zastrzeżeń, żeby przyszły przegląd nie uznał jej za sprzeczną z PRD.

**Contract**: Format jak ADR-0033: tytuł, akapity bez nagłówków, akapit „We rejected …”. Treść obejmuje:
- **Decyzja:** wagi słów z historii operacji z Importu, kNN k=5, próg 0,25, podpowiedź per rodzina typu, mapowanie po nazwie i typie do roku daty wiersza.
- **ADR-0010:** tamten zakaz „no Opis preselect or badge” dotyczy typu `own_transfer`; ta funkcja nie zmienia typu.
- **ADR-0015 i FR-TRX-16:** liczona dla bieżącego typu, nigdy nie zgaduje zwrotu.
- **ADR-0011:** wiersz zostaje odznaczony.
- **FR-IMP-11:** kolumny kategorii banku nadal nie są mapowane, a funkcja uczy się z klasyfikacji samego użytkownika.
- **„Funkcje AI” poza zakresem:** to deterministyczne zliczanie, bez modelu i bez usługi zewnętrznej.
- **ADR-0014 i ADR-0016:** brak tożsamości ponad latami.
- **Odrzucone:** Typesense i `pg_trgm` / podobieństwo całego tekstu (szum płatności kartą przeważa), samo dokładne dopasowanie tekstu (niższe pokrycie, wariant A prototypu), podpowiedź bez progu, nauka z wpisów ręcznych (niezmierzona).

#### 3. PRD

**File**: `.docs/prd.md`

**Intent**: Dodać wymaganie funkcjonalne, kryterium akceptacji i wymaganie wydajności. W FR-IMP-11 dopisać odwołanie, żeby oba wymagania się nie wykluczały.

**Contract**:
- **FR-IMP-28** po FR-IMP-27 (sekcja 7.9): podgląd wstępnie wybiera kategorię poprawnego wiersza z datą dla bieżącego typu (Income albo Expense, zwrot używa Expense) na podstawie słów opisu i nadawcy / odbiorcy we wcześniejszych operacjach użytkownika z Importu. Cel to jedyna aktywna Category o tej nazwie i typie w Budgecie w execution z roku daty wiersza. Bez wspólnego słowa o dużej wadze, przy niejednoznacznej nazwie albo przy remisie podpowiedzi nie ma. Podpowiedź jest oznaczona, nie zmienia typu ani zaznaczenia, a użytkownik może ją zmienić.
- **FR-IMP-11:** dopisane zdanie „Wstępny wybór z FR-IMP-28 nie jest mapowaniem kategorii banku.”
- **Kryterium 11.6.19:** wiersz, którego sklep występował w historii przy jednej kategorii, startuje z tą kategorią i znacznikiem. Wiersz z samym szumem albo z nowym sklepem startuje z pustym polem. Zmiana typu wstawia podpowiedź nowego typu albo puste pole.
- **NFR-PERF-03** po NFR-PERF-02: przy 10 000 operacji z Importu w historii użytkownika przygotowanie podglądu pliku 300 wierszy kończy się w ciągu 2 sekund w środowisku testów akceptacyjnych.

### Success Criteria:

#### Automated Verification:

- Hasło, ADR i wpisy PRD istnieją: `grep -c "Import category suggestion" CONTEXT.md`, `ls .docs/adr/0034-*`, `grep -c "FR-IMP-28\|NFR-PERF-03" .docs/prd.md` (każde ≥ 1)

#### Manual Verification:

- Treść hasła, ADR-0034 i FR-IMP-28 zgadza się z decyzjami w `plan-brief.md` (źródło historii, per rodzina, cel w roku daty wiersza, bez zmiany typu i zaznaczenia)

**Implementation Note**: Po zakończeniu fazy i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed następną fazą.

---

## Phase 2: Podpowiedź w backendzie i kontrakt propsów

### Overview

Czysty model wag słów, serwis wzbogacający wiersze, wpięcie w `preview()`, typ `ImportRow` po stronie TS i testy. Po tej fazie podpowiedź jest w propsie, ale frontend jej jeszcze nie używa.

### Changes Required:

#### 1. Czysty model wag słów

**File**: `app/Support/CsvImport/ImportCategorySuggestionModel.php` (nowy)

**Intent**: Przenieść algorytm wariantu B z prototypu do klasy bez bazy i bez Laravela, żeby dało się go testować jednostkowo i wołać ze skryptu pomiaru. Model głosuje po nazwie kategorii w obrębie typu, nie po samej nazwie.

**Contract**:
- `static fromHistory(iterable $entries): self`. Wpis to `array{id: int, type: 'income'|'expense', category_name: string, text: string, operation_date: string}`.
- `rank(string $type, string $text): list<array{category_name: string, score: float}>`: głosy malejąco; pusta lista, gdy brak sąsiada z podobieństwem ≥ 0,25.
- `suggest(string $type, string $text): ?string`: pierwsza nazwa z `rank`, o ile jej suma jest ostro większa od drugiej, w przeciwnym razie `null`.
- `static tokens(string $text): list<string>`: publiczna, używana przez testy.

Stałe: `NEIGHBOURS = 5`, `MIN_SIMILARITY = 0.25`, `WEIGHT_FLOOR = 0.05`. Definicje (te same co w prototypie, poza kluczem kategorii):

```
tokens(text): mb_strtoupper → ĄĆĘŁŃÓŚŹŻ na ACELNOSZZ → /[^A-Z ]+/ na spację
              → słowa o długości ≥ 3 → unikalne
w(t)        = 1 / k², k = liczba różnych par (type, category_name) we wpisach z tokenem t; nieznany token → 0
sim(R, H)   = Σ_{t ∈ R∩H} w(t) / Σ_{t ∈ R∪H} max(w(t), WEIGHT_FLOOR), tylko wpisy tego samego type z R∩H ≠ ∅
sąsiedzi    = top NEIGHBOURS po sim ↓, operation_date ↓, id ↓ (tylko sim > 0)
głos        = Σ sim sąsiadów per category_name; podpowiedź tylko gdy sim najlepszego sąsiada ≥ MIN_SIMILARITY
```

Kandydaci pochodzą z indeksu odwróconego `type → token → lista wpisów`, budowanego raz w `fromHistory`, nie z pełnego przeglądu historii. **Kandydatów generują tylko tokeny wiersza o `w(t) ≥ WEIGHT_FLOOR`** (k ≤ 4). To jest „wspólne słowo o dużej wadze” z Desired End State. Bez tej reguły szum (`PLN`, `POL`) zwraca całą historię typu, a teksty z samego szumu przy k = 5–8 dostają sim ≥ 0,25 (np. 0,04·n / 0,05·n = 0,8). Sim liczy się dalej po wszystkich tokenach R∪H. Benchmark przeglądu planu (10 000 wpisów, 300 wierszy × 2 typy): 28 s bez reguły, 0,1 s z nią.

#### 2. Serwis wzbogacający wiersze

**File**: `app/Support/CsvImport/ImportCategorySuggester.php` (nowy)

**Intent**: Załadować historię użytkownika jednym zapytaniem, policzyć podpowiedzi dla `income` i `expense` każdego wiersza i zmapować nazwy na id kategorii z Budgetu roku daty wiersza.

**Contract**: `annotateRows(User $user, array $rows): array`. Do każdego wiersza dopisuje `category_suggestion: array{income: ?int, expense: ?int}`, także do niekwalifikującego się (wtedy oba `null`). Reguły:
- **Historia:** `$user->financialOperations()` z `raw_statement_description IS NOT NULL` i `description <> ImportRowFields::EMPTY_DESCRIPTION_FALLBACK`, złączone z nazwą kategorii. Tekst wpisu to `trim(description . ' ' . counterparty)`, typ to `FinancialOperationType` (zwrot jest już `expense`).
- **Wiersz kwalifikuje się, gdy** `valid === true` i `description !== ImportRowFields::EMPTY_DESCRIPTION_FALLBACK`. `ImportRowFields::omitLiveFields` (`ImportRowFields.php:105-107`) wpisuje kontrahenta do pustego opisu, więc „Brak opisu” zawsze oznacza też brak kontrahenta. Tekst to `trim(description . ' ' . counterparty)`. `operation_date` nigdy nie jest null, bo takie wiersze odpadają w `CsvImportController.php:85/:477`.
- **Mapowanie:** przez wspólną klasę `ImportCategoryOptions` (sekcja 2a), pogrupowane po `(budget_year, type, name)`. Dokładne porównanie nazwy, z rozróżnieniem wielkości liter, jak ADR-0016. Dokładnie jedno id daje to id, zero albo więcej niż jedno daje `null`.
- **Bez zapytań w pętli** (Sloppy).

#### 2a. Wspólne zapytanie o kategorie podglądu

**Files**: `app/Support/CsvImport/ImportCategoryOptions.php` (nowy), `app/Http/Controllers/CsvImportController.php`

**Intent**: Listy opcji w `showPreview` i mapowanie podpowiedzi muszą stosować te same filtry: Budget w execution, kategoria bez archiwizacji, typ. Dlatego oba miejsca korzystają z jednego zapytania zamiast trzeciego, równoległego.

**Contract**: Przenieść prywatne `CsvImportController::incomeCategories()` i `expenseCategories()` (`:523-569`) do `ImportCategoryOptions` jako `income(User $user): array` i `expense(User $user): array`. Kształt wyniku bez zmian (`id`, `name`, pola `CategoryAppearance`, `budget_year`, w expense także `is_paced`). `showPreview` woła klasę zamiast metod prywatnych, a props się nie zmieniają. Serwis buduje mapowanie z tych samych dwóch list.

#### 3. Wpięcie w podgląd

**File**: `app/Http/Controllers/CsvImportController.php`

**Intent**: Wywołać serwis jako trzeci krok wzbogacania, po detektorze par (`:109-110`), przed zapisem do sesji.

**Contract**: Nowy parametr metody `preview(..., ImportCategorySuggester $importCategorySuggester)`, tak jak wstrzykiwane są detektory. `showPreview` zmienia się tylko o wywołanie `ImportCategoryOptions` (sekcja 2a). `store` bez zmian.

#### 4. Typ wiersza w TS i fixture'y

**Files**: `resources/js/lib/import-preview-rows.ts`, `resources/js/lib/import-preview-fixtures.ts`

**Intent**: Dodać pole do ręcznego typu w tym samym commicie co PHP (kontrakt propsów) i ustawić domyślną wartość w fabryce fixture'ów, żeby `types:check` przeszedł.

**Contract**: `ImportRow.category_suggestion?: { income: number | null; expense: number | null }`. Pole jest opcjonalne, bo podgląd zapisany w sesji przed wdrożeniem go nie ma (Migration Notes). W fabryce `row()` domyślnie `{ income: null, expense: null }`. Pełne literały `ImportRow` w `resources/js/lib/import-submit-block.test.ts:9-20` i `resources/js/lib/import-to-spend-warnings.test.ts:61-72` dzięki opcjonalności nie wymagają zmian. Sprawdzić to w `types:check`.

#### 5. Testy

**Files**:
- `tests/Unit/ImportCategorySuggestionModelTest.php` (nowy, Pest, bez bazy)
- `tests/Feature/ImportCategorySuggesterTest.php` (nowy, Pest, `uses(RefreshDatabase::class)`)
- `tests/Feature/CsvImportTest.php`

**Intent**: Model testujemy na małych listach wpisów, serwis na fabrykach z jawnie spójnymi `budget_id` operacji i kategorii, a kontrakt propsu przez `assertInertia`.

**Contract**:

Model (Unit):
- `tokens` usuwa cyfry, daty, kwoty i interpunkcję, zamienia polskie znaki i odrzuca słowa 1–2-literowe;
- sklep występujący przy jednej kategorii daje tę nazwę;
- wiersz współdzielący z historią tylko szum (słowa obecne przy wielu kategoriach) daje `null`;
- wiersz z samym szumem, identyczny z wpisami z samego szumu, których słowa występują przy 5–8 kategoriach, daje `null` (reguła kandydatów `w(t) ≥ WEIGHT_FLOOR`);
- brak wspólnego słowa daje `null`;
- remis głosów daje `null`;
- wpisy `income` nie głosują dla `expense` i odwrotnie.

Serwis (Feature):
- historia z Budgetu 2025 i wiersz z 2026 dają id kategorii o tej samej nazwie i typie z 2026;
- dwie kategorie o tej nazwie w Budgecie 2026 dają `null`;
- kategoria docelowa zarchiwizowana daje `null`;
- Budget roku wiersza w szkicu albo brak Budgetu daje `null`;
- historia innego użytkownika jest ignorowana;
- wpis ręczny (`raw_statement_description` null) jest ignorowany;
- zapisana operacja z opisem „Brak opisu” nie wchodzi do historii;
- zwrot zapisany jako `expense` z ujemną kwotą uczy podpowiedzi `expense`;
- wiersz niepoprawny i wiersz „Brak opisu” dają oba `null`;
- `income` i `expense` są liczone niezależnie w jednym wierszu.

`CsvImportTest`:
- `POST preview` z historią i `GET preview` z `assertInertia` dają `where('rows.0.category_suggestion.expense', <id>)` oraz wiersz bez dopasowania z `null`;
- istniejące asercje, które porównują cały kształt wiersza, trzeba zaktualizować o nowy klucz.

### Success Criteria:

#### Automated Verification:

- Testy modelu, serwisu i podglądu przechodzą na PostgreSQL: `artisan test --compact tests/Unit/ImportCategorySuggestionModelTest.php tests/Feature/ImportCategorySuggesterTest.php tests/Feature/CsvImportTest.php` obrazem Sail (AGENTS.md „Test database”)
- Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- Typy i statyczne kontrole przechodzą: `npm run types:check`, `npm run check`
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- Na lokalnych danych podgląd prawdziwego pliku ma w propsie `rows[i].category_suggestion` z id z roku daty wiersza (Vue devtools albo `dd` w `showPreview` wycofane przed commitem)

**Implementation Note**: Po zakończeniu fazy i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed następną fazą.

---

## Phase 3: Wstępny wybór i znacznik w podglądzie

### Overview

Frontend wybiera podpowiedź dla bieżącego typu przy starcie i po zmianie typu oraz pokazuje „✦ podpowiedź” pod selektem. Kitchen sink dostaje stan z podpowiedzią, a reguła UI utrwala sposób jej pokazywania.

### Changes Required:

#### 1. Helpery stanu wiersza

**File**: `resources/js/lib/import-preview-rows.ts`

**Intent**: Trzymać całą logikę wyboru podpowiedzi w czystych funkcjach, testowalnych bez komponentów.

**Contract**:
- `suggestedCategoryId(row, type, incomeCategories, expenseCategories): number | ''`:
  - `income` bierze `row.category_suggestion.income`, a `expense` i `refund` biorą `.expense`;
  - pozostałe typy dają `''`;
  - zwraca id tylko wtedy, gdy jest na liście `categoriesForRow(row, type, …)`, w przeciwnym razie `''`.
- `initialSelection(row, incomeCategories, expenseCategories)`: nowa sygnatura. Gałąź unikalnego kandydata pary i typ zablokowany na `own_transfer` zostają z `category_id: ''`. W pozostałych przypadkach `category_id = suggestedCategoryId(row, typ domyślny, …)`, a `selected` zostaje `false`.
- `showsCategorySuggestion(row, selection, incomeCategories, expenseCategories): boolean`: `selection.category_id !== ''` i jest równe `suggestedCategoryId(row, selection.type, …)`.

#### 2. Strona i wiersz

**Files**: `resources/js/pages/OperationsImportPreview.vue`, `resources/js/components/ImportPreviewRow.vue`

**Intent**: Strona przekazuje listy kategorii do `initialSelection` (`OperationsImportPreview.vue:77-79`). `onTypeChange` po ustaleniu ostatecznego typu ustawia `category_id = suggestedCategoryId(...)` zamiast `''`. Pod `CategorySelect` w komórce klasyfikacji pojawia się znacznik, gdy `showsCategorySuggestion` jest prawdą.

**Contract**: Znacznik to `<p :id="`row-category-suggestion-${row.index}`" class="text-muted-foreground text-xs">` z `<span aria-hidden="true">✦</span>` i tekstem z `lang`. `CategorySelect` klasyfikacji dostaje `aria-describedby` z tym id tylko wtedy, gdy znacznik jest widoczny. Znacznik to nie `Badge` i nie nowy rodzaj w `importRowHasNotes` (stoi przy polu, nie w bloku notatek). Wybór kategorii odsetek bez zmian.

#### 3. `CategorySelect`

**File**: `resources/js/components/CategorySelect.vue`

**Intent**: Pozwolić podpiąć opis pola do triggera, bo `aria-describedby` przekazany z zewnątrz trafiłby na korzeń `<div>`.

**Contract**: Nowy opcjonalny prop `ariaDescribedby?: string`, przekazany jako `:aria-describedby` na `SelectTrigger`, tak jak `ariaLabel`.

#### 4. Tekst

**File**: `lang/pl/ui.php`

**Intent**: Tekst znacznika obok innych tekstów wiersza Importu.

**Contract**: Klucz `operations.import_category_suggestion` => `'podpowiedź'`. Gwiazdka jest w szablonie, ukryta przed czytnikiem.

#### 5. Kitchen sink

**Files**: `resources/js/lib/import-preview-fixtures.ts`, `resources/js/pages/dev/ImportPreviewSink.vue` (jeśli wymaga), `tests/Feature/DevImportPreviewSinkTest.php` (jeśli liczy stany)

**Intent**: Dodać stan „wiersz z podpowiedzią kategorii”, żeby znacznik dało się obejrzeć w obu motywach bez importu pliku.

**Contract**: Nowy wiersz fixture z `category_suggestion.expense` wskazującym kategorię wydatku z roku wiersza z `expenseCategories` fixture'ów. W `importPreviewSelections()` dostaje `category_id` równe tej podpowiedzi. Istniejące stany bez zmian.

#### 6. Reguła UI

**File**: `.ai/rules/js.md` (sekcja „Import preview: named classes, the `pair` tint, no lost row alert”)

**Intent**: Utrwalić dla następnego agenta, jak wolno pokazywać i ustawiać podpowiedź.

**Contract**: Jedno lub dwa zdania. Podpowiedź kategorii wchodzi do stanu wiersza tylko przez `suggestedCategoryId`, nigdy nie zmienia typu ani `selected`. Znacznik to wyciszony tekst pod `CategorySelect`, połączony `aria-describedby`, widoczny tylko wtedy, gdy wartość równa się podpowiedzi. To nie `Badge` ani alert.

#### 7. Testy TS

**File**: `resources/js/lib/import-preview-rows.test.ts`

**Intent**: Pokryć helpery przypadkami brzegowymi.

**Contract**:
- `income` → podpowiedź `income`; `expense` → `expense`; `refund` na wierszu dodatnim → `expense`;
- `own_transfer` i `capital_movement` → `''`;
- id spoza `categoriesForRow` (inny rok albo brak na liście) → `''`;
- wiersz bez daty → `''` (przypadek obronny: `categoriesForRow` zwraca `[]`, a backend takich wierszy nie wysyła);
- `initialSelection` z unikalnym kandydatem pary → `category_id: ''`;
- `initialSelection` nigdy nie ustawia `selected: true`;
- `showsCategorySuggestion` daje `false` po ręcznej zmianie kategorii i `true` po powrocie do podpowiedzianej.

### Success Criteria:

#### Automated Verification:

- Testy TS przechodzą: `npm test`
- Kontrole, literały UI, typy i build przechodzą: `npm run check`, `npm run check:ui`, `npm run types:check`, `npm run build`
- Test kitchen sinka przechodzi: `artisan test --compact tests/Feature/DevImportPreviewSinkTest.php`

#### Manual Verification:

- Podgląd prawdziwego pliku: wiersze ze znanym sklepem mają wybraną kategorię i „✦ podpowiedź”, a wiersze z nowym sklepem lub BLIK-iem „Payment for order ID …” mają puste pole
- Ręczna zmiana kategorii ukrywa znacznik, a powrót do podpowiedzianej przywraca go
- Przełączenie `expense` na `refund` zostawia podpowiedź, `income` na `refund` (wiersz +) wstawia podpowiedź wydatkową albo puste pole, a przełączenie na transfer własny czyści pole
- Zaznaczenie wiersza z podpowiedzią i zatwierdzenie zapisuje operację z tą kategorią
- Kitchen sink `/dev/import-preview` pokazuje stan z podpowiedzią w motywie jasnym i ciemnym; czytnik ekranu ogłasza „podpowiedź” przy polu kategorii

**Implementation Note**: Po zakończeniu fazy i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed następną fazą.

---

## Phase 4: Wydajność i pomiar jakości

### Overview

Test NFR potwierdza czas podglądu przy dużej historii. Skrypt pomiaru odtwarza historię chronologicznie na kodzie produkcyjnym i sprawdza progi z prototypu na lokalnych danych.

### Changes Required:

#### 1. Test NFR

**File**: `tests/Feature/Nfr/NfrPerf03ImportPreviewTest.php` (nowy)

**Intent**: Sprawdzić NFR-PERF-03: `POST operations/import/preview` z plikiem 300 wierszy przy 10 000 operacji z Importu w historii kończy się w czasie poniżej 2,0 s.

**Contract**: Wzorzec `NfrPerf01Test`:
- `#[Group('nfr')]`, `markTestSkipped` poza pgsql, `withoutVite`, pomiar czasu żądania;
- seed przez `DB::table` w paczkach: 10 000 operacji z `raw_statement_description` ustawionym, opisami złożonymi z ok. 200 nazw sklepów i wspólnego szumu (imię i nazwisko, miasto, `POL`, `PLN`), w Budgetach 2025 i 2026 w execution z kategoriami o tych samych nazwach;
- plik CSV w formacie, który `CsvImportTest` już buduje w helperach.

Asercja sprawdza też, że co najmniej jeden wiersz dostał podpowiedź, żeby test nie mierzył ścieżki bez pracy.

#### 2. Skrypt pomiaru na kodzie produkcyjnym

**File**: `context/changes/import-category-suggestions/prototype/eval_service.php` (nowy, tylko odczyt, nie kod aplikacji)

**Intent**: Powtórzyć pomiar z `change.md` na `ImportCategorySuggestionModel`, żeby próg sprawdzał implementację, a nie prototyp.

**Contract**:
- uruchomienie: `docker exec -i household-treasurer-laravel.test-1 php < …`;
- historia jednego użytkownika (pierwszy użytkownik z operacjami z Importu albo id w zmiennej na górze skryptu), z tymi samymi filtrami co serwis (bez wpisów „Brak opisu”), uporządkowana po `(operation_date, id)`;
- dla każdego wiersza model z wpisów wcześniejszych (z regułą kandydatów `w(t) ≥ WEIGHT_FLOOR`), potem `suggest` i `rank` dla jego typu;
- raport dla „od stycznia” i „sierpień–listopad”: odsetek wierszy z podpowiedzią, trafne (nazwa równa) i trafna w top 3 `rank`.

`eval_suggest.php` zostaje bez zmian jako zapis prototypu.

### Success Criteria:

#### Automated Verification:

- Test NFR przechodzi: `./vendor/bin/sail artisan test --group=nfr --configuration=phpunit.nfr.xml`
- Pełna bramka CI na PostgreSQL: `composer ci:check` obrazem Sail (AGENTS.md „Test database”)
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- Pomiar `eval_service.php` na lokalnych danych: od stycznia ≥ 78% wierszy z podpowiedzią i ≥ 92% trafnych; sierpień–listopad ≥ 79% i ≥ 95%. Gdy próg nie jest spełniony, zatrzymaj się i zgłoś wyniki zamiast stroić stałe

**Implementation Note**: Po zakończeniu fazy i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `ImportCategorySuggestionModel`: tokenizacja, wagi (szum przy wielu kategoriach, sklep przy jednej), próg, remis, rozdzielenie typów
- Helpery TS: wybór rodziny typu, osłona listy opcji, wiersz bez daty, gałąź pary, widoczność znacznika

### Integration Tests:

- `ImportCategorySuggester` na bazie: mapowanie między latami po nazwie i typie, niejednoznaczna nazwa, archiwizacja, faza Budgetu, izolacja użytkowników, wpisy ręczne, zwroty, wiersze bez kwalifikacji
- `CsvImportTest`: prop `rows[i].category_suggestion` przez `assertInertia`
- `NfrPerf03ImportPreviewTest`: czas `POST preview` przy dużej historii

### Manual Testing Steps:

1. Zaimportuj prawdziwy plik mBank albo BNP z lokalnymi danymi. Sprawdź podpowiedzi i znaczniki na znanych i nowych sklepach.
2. Zmieniaj kategorię i typ wiersza. Sprawdź znikanie i powrót znacznika oraz podpowiedź po przełączeniu na zwrot.
3. Zatwierdź kilka wierszy z podpowiedzią i sprawdź kategorie zapisanych operacji.
4. Uruchom `eval_service.php` i porównaj z progami.

## Performance Considerations

Najdroższe są tokeny wspólne dla tysięcy wpisów (`ZAKUP`, `PLN`, imię i nazwisko), bo przez indeks odwrócony dają wielu kandydatów. Dlatego model od początku generuje kandydatów tylko z tokenów o `w(t) ≥ WEIGHT_FLOOR` (Phase 2 §1). To decyzja, nie fallback. `eval_service.php` mierzy tę wersję względem progów, bez porównania z wynikami prototypu. Historia ładuje się jednym zapytaniem z kolumnami potrzebnymi do tekstu i klucza. Mapowanie korzysta z `ImportCategoryOptions`: dwa zapytania, te same, które `showPreview` wykonuje dla list opcji.

## Migration Notes

Bez migracji schematu i bez backfillu. Podgląd zapisany w sesji przed wdrożeniem nie ma klucza `category_suggestion`. Taki wiersz po deployu jest traktowany jak brak podpowiedzi. Pole `category_suggestion` jest w TS opcjonalne, a `suggestedCategoryId` czyta je przez `row.category_suggestion?.income ?? null` (analogicznie `expense`).

## References

- Research: `context/changes/import-category-suggestions/research.md`
- Prototyp i pomiar: `context/changes/import-category-suggestions/prototype/eval_suggest.php`, `change.md`
- Wzorzec wzbogacania: `app/Support/CsvImport/ImportPairCandidateDetector.php:42-100`
- Reguły celu: `app/Support/CsvImport/FinancialOperationImportValidator.php:56-82,125-169`
- Dopasowanie po nazwie i typie: `app/Support/MonthPlanProposal.php:171-187`, `app/Support/OperationsListQuery.php:139-147`
- Stan wiersza: `resources/js/lib/import-preview-rows.ts:55-108`, `resources/js/components/ImportPreviewRow.vue:218-240,525-542`
- Test wydajności: `tests/Feature/Nfr/NfrPerf01Test.php`
- Kontrakt UI podglądu: `.ai/rules/js.md` (sekcja Import preview), `context/archive/2026-11-04-import-preview-ui-contract/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Słownik i decyzje domenowe

#### Automated

- [x] 1.1 Hasło, ADR i wpisy PRD istnieją: `grep -c "Import category suggestion" CONTEXT.md`, `ls .docs/adr/0034-*`, `grep -c "FR-IMP-28\|NFR-PERF-03" .docs/prd.md` (każde ≥ 1)

#### Manual

- [x] 1.2 Treść hasła, ADR-0034 i FR-IMP-28 zgadza się z decyzjami w `plan-brief.md` (źródło historii, per rodzina, cel w roku daty wiersza, bez zmiany typu i zaznaczenia)

### Phase 2: Podpowiedź w backendzie i kontrakt propsów

#### Automated

- [ ] 2.1 Testy modelu, serwisu i podglądu przechodzą na PostgreSQL: `artisan test --compact tests/Unit/ImportCategorySuggestionModelTest.php tests/Feature/ImportCategorySuggesterTest.php tests/Feature/CsvImportTest.php` obrazem Sail (AGENTS.md „Test database”)
- [ ] 2.2 Formatowanie PHP: `vendor/bin/pint --dirty --format agent`
- [ ] 2.3 Typy i statyczne kontrole przechodzą: `npm run types:check`, `npm run check`
- [ ] 2.4 Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual

- [ ] 2.5 Na lokalnych danych podgląd prawdziwego pliku ma w propsie `rows[i].category_suggestion` z id z roku daty wiersza (Vue devtools albo `dd` w `showPreview` wycofane przed commitem)

### Phase 3: Wstępny wybór i znacznik w podglądzie

#### Automated

- [ ] 3.1 Testy TS przechodzą: `npm test`
- [ ] 3.2 Kontrole, literały UI, typy i build przechodzą: `npm run check`, `npm run check:ui`, `npm run types:check`, `npm run build`
- [ ] 3.3 Test kitchen sinka przechodzi: `artisan test --compact tests/Feature/DevImportPreviewSinkTest.php`

#### Manual

- [ ] 3.4 Podgląd prawdziwego pliku: wiersze ze znanym sklepem mają wybraną kategorię i „✦ podpowiedź”, a wiersze z nowym sklepem lub BLIK-iem „Payment for order ID …” mają puste pole
- [ ] 3.5 Ręczna zmiana kategorii ukrywa znacznik, a powrót do podpowiedzianej przywraca go
- [ ] 3.6 Przełączenie `expense` na `refund` zostawia podpowiedź, `income` na `refund` (wiersz +) wstawia podpowiedź wydatkową albo puste pole, a przełączenie na transfer własny czyści pole
- [ ] 3.7 Zaznaczenie wiersza z podpowiedzią i zatwierdzenie zapisuje operację z tą kategorią
- [ ] 3.8 Kitchen sink `/dev/import-preview` pokazuje stan z podpowiedzią w motywie jasnym i ciemnym; czytnik ekranu ogłasza „podpowiedź” przy polu kategorii

### Phase 4: Wydajność i pomiar jakości

#### Automated

- [ ] 4.1 Test NFR przechodzi: `./vendor/bin/sail artisan test --group=nfr --configuration=phpunit.nfr.xml`
- [ ] 4.2 Pełna bramka CI na PostgreSQL: `composer ci:check` obrazem Sail (AGENTS.md „Test database”)
- [ ] 4.3 Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci` na hoście

#### Manual

- [ ] 4.4 Pomiar `eval_service.php` na lokalnych danych: od stycznia ≥ 78% wierszy z podpowiedzią i ≥ 92% trafnych; sierpień–listopad ≥ 79% i ≥ 95%. Gdy próg nie jest spełniony, zatrzymaj się i zgłoś wyniki zamiast stroić stałe
