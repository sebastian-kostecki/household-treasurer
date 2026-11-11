# Scalanie kategorii — plan implementacji

## Overview

S-21 z roadmapy M-3 (FR-023): na „Kategoriach” użytkownik scala dwie kategorie listy w jedną. Kategoria źródłowa znika, a jej operacje, plany i „zebrane” przechodzą na kategorię docelową. Scalenie jest nieodwracalne w aplikacji, więc przed potwierdzeniem okno pokazuje podgląd liczb liczony na serwerze, a akcja zapisuje snapshot użytkownika, zanim cokolwiek zmieni. Na produkcji czekają dwie pary: „Jedzenie poza domem” → „Restauracje” (48 operacji w 2026) i „Eksploatacja domu” → „Utrzymanie domu” (20 operacji w 2026).

## Current State Analysis

- **Model.** Kategoria listy (`categories`) ma pozycje budżetu (`budget_lines`, `unique(budget_id, category_id)`, klucz obcy z kaskadą) i historię rytmu (`category_rhythms`, `unique(category_id, start_year, start_month)`, kaskada). Operacje i pozycje planu miesiąca wskazują pozycję budżetu: `financial_operations.budget_line_id` (usunięcie pozycji **zablokowane**, `database/migrations/2026_09_07_180003_create_financial_operations_table.php:19`) i `month_plan_items.budget_line_id` (kaskada, `unique(month_plan_id, budget_line_id)`, `database/migrations/2026_11_02_091922_create_month_plan_items_table.php:17-22`).
- **Wykonanie jest zapisane.** `budget_lines.execution_minor_units` utrzymuje przyrostowo `ManageFinancialOperation` (`app/Actions/ManageFinancialOperation.php:255-287`). Przepięcie operacji samym `UPDATE` go nie przenosi.
- **Rytm i „zebrane”.** Rytm zapisuje tylko `ChangeCategoryRhythm` (`app/Actions/ChangeCategoryRhythm.php:30-89`); przy tym samym rytmie poprawia jedynie zebrane startowe na wpisie, który zaczął okres (:62-72). „Zebrane” nie jest zapisane: `IrregularCollected::collectedAtStartOfMonth()` liczy łańcuch od wpisu okresu, ze stawką z pozycji planu miesiąca i rachunkami z operacji pozycji budżetu (`app/Support/IrregularCollected.php:61-86`, :145-186). ADR-0066 zostawia S-21 decyzję, co dzieje się z „zebranym” przy scaleniu.
- **Usuwanie i archiwum.** `DeleteCategory` odmawia, gdy kategoria ma historię (`app/Actions/DeleteCategory.php:22-25`, predykat `CategoryTypeChange::isLocked`). `RestoreCategory` zdejmuje archiwum tylko z pozycji budżetu w wykonaniu bieżącego roku (`app/Actions/RestoreCategory.php`). ADR-0063 mówi: „Otherwise there is archive or merge (S-21)”.
- **Odczyty łączą lata po id kategorii.** Raporty (`app/Support/PeriodReport.php:102-159`), filtr Operacji (`app/Support/OperationsListQuery.php:135`), podpowiedzi Importu (`app/Support/CsvImport/ImportCategorySuggester.php:77-132`) i propozycja stycznia czytają `budget_lines.category_id`, więc po scaleniu nie wymagają zmian.
- **„Kategorie”.** Strona `resources/js/pages/Categories.vue` nie pokazuje kwot, czego pilnuje `resources/js/ui-contract.test.ts:474-495`. Wiersz ma trzy ikony akcji w kolumnie 7rem (`resources/js/components/CategoryListRowActions.vue:23-81`, `resources/css/app.css:1271`); wiersz grupy ma menu „⋯” (`resources/js/components/CategoryGroupRow.vue:82-147`). Okno edycji jest jedno na stronie i otwiera je zdarzenie z wiersza (`Categories.vue:118-123`, :416-423).
- **Podgląd z serwera.** Żadne okno nie pobiera dziś danych z serwera przed potwierdzeniem. Najbliższy wzorzec to paleta poleceń: `useHttp` z GET, stan `loading | loaded | error`, licznik żądań odrzucający spóźnione odpowiedzi (`resources/js/composables/useCommandPalette.ts:36-83`, kontroler JSON `app/Http/Controllers/CommandPaletteCategoryController.php`).
- **Snapshot.** `BackupUserData::handle(User): string` zapisuje plik `storage/app/user-snapshots/<id>-<YmdHis>.json` (wersja 10) i zwraca ścieżkę (`app/Actions/BackupUserData.php:24-100`); `user:restore` go wczytuje. Scalenie nie zmienia kształtu danych.
- **Nie istnieje w kodzie:** scalanie, Cash Expectation, Earmark, Reserve.

## Desired End State

Wiersz kategorii (aktywnej i archiwalnej) ma menu „⋯” z „Scal z inną…” i „Usuń”. „Scal z inną…” otwiera okno ze źródłem w tytule i wyborem kategorii docelowej tego samego typu. Po wyborze okno pokazuje podgląd z serwera, a „Scal” jest zablokowane, dopóki podgląd się nie wczyta. Po potwierdzeniu kategoria źródłowa znika z listy, a wszystkie jej dane są na docelowej.

Reguły (ustalone w planowaniu, zapisane w ADR-0076):

1. **Kto z kim.** Ten sam użytkownik, ten sam typ, dwie różne kategorie. Cel musi być aktywny; źródło może być aktywne albo archiwalne.
2. **Pozycje budżetu.** W budżecie, w którym pozycję ma tylko źródło, pozycja zostaje przepięta na cel (id pozycji bez zmian, operacje i pozycje planu miesiąca nietknięte). W budżecie, w którym pozycję mają obie, pozycje składają się w pozycję celu: plan początkowy, plan aktualny i wykonanie sumują się, operacje przechodzą, a stan archiwum pozycji celu zostaje.
3. **Plan miesiąca.** Gdy obie pozycje mają pozycję w tym samym planie miesiąca, kwota zatwierdzona i bieżąca sumują się; inaczej pozycja planu przechodzi na pozycję celu.
4. **Archiwalne źródło** scala się tak, jakby najpierw zostało przywrócone: przepinana pozycja budżetu w wykonaniu bieżącego roku przestaje być archiwalna.
5. **Rytm.** Historia rytmu celu zostaje, historia źródła znika.
6. **„Zebrane”.** Gdy cel jest nieregularny w bieżącym miesiącu, scalenie zaczyna na nim nowy okres od bieżącego miesiąca z zebranym startowym równym sumie „zebranego” obu kategorii na 1. dzień bieżącego miesiąca (źródło liczy się jako 0, gdy nie jest wtedy nieregularne). Gdy cel nie jest nieregularny, „zebrane” źródła znika, a podgląd ostrzega kwotą, gdy jest większa od 0.
7. **Podgląd.** Wiersz dla każdego budżetu, w którym źródło ma pozycję: rok, liczba operacji, plan aktualny celu „przed → po”; pod tabelą liczba i zakres pozycji planów miesięcy, linia „zebrane”, ostrzeżenie o utracie „zebranego” i notatka o powrocie archiwalnej pozycji do sum budżetu.
8. **Powrót.** Akcja zapisuje snapshot użytkownika przed pierwszym zapisem i niczego nie scala, gdy snapshot się nie uda. Powrót to `user:restore` z tego pliku.
9. **Kontrola.** Sumy planu początkowego, planu aktualnego i wykonania oraz liczba operacji per budżet, a także sumy kwot per plan miesiąca, są równe przed i po; rozjazd wycofuje transakcję.

Weryfikacja: testy akcji i HTTP na PostgreSQL, test E2E scalenia z UI, próba obu par z produkcji na lokalnej kopii z `app:control-numbers --compare` bez różnic.

### Key Discoveries:

- Operacje blokują usunięcie pozycji budżetu, więc kolejność zapisów jest wymuszona: najpierw operacje, potem pozycje planu, sumy na pozycji celu, usunięcie złożonych pozycji źródła, przepięcie pozostałych, na końcu usunięcie kategorii (`2026_09_07_180003…:19`).
- `ChangeCategoryRhythm::handle()` nie umie zacząć nowego okresu, gdy rytm bieżącego miesiąca już jest nieregularny (:62-72). Potrzebna jest osobna metoda w tej samej klasie, żeby została jedynym miejscem zapisu rytmu (`.ai/rules/categories.md`).
- Sloppy (SL204) odrzuca zapisy w pętli; wzorzec zbiorczego `UPDATE … CASE` jest w `app/Support/CategoryList/CategoryListStore.php:59-97`.
- Blokadę całej listy typu daje `CategoryListStore::state(userId, type)` (:24-52); żadna akcja nie blokuje dziś dwóch kategorii naraz.
- `ConfirmActionDialog` renderuje slot wewnątrz `<Form>` (`resources/js/components/ConfirmActionDialog.vue:49-67`), więc pole celu (`target_id`) i podgląd mogą stać w nim bez ręcznie składanego okna; brakuje tylko blokady przycisku potwierdzenia.
- `CategorySelect` przyjmuje `CategoryPickerOption[]` w kolejności drzewa i grupuje sąsiednie opcje jednej grupy (`resources/js/lib/category-tree.ts:173-192`); propsy strony nie mają obiektu grupy przy kategorii, więc opcje trzeba zbudować pomocnikiem.
- Na produkcji obie pary leżą w różnych budżetach (źródło w 2026, cel w szkicu 2027), więc scalenie tylko przepina pozycje: id pozycji, sumy i liczba wierszy zostają, a `app:control-numbers --compare` nie pokaże różnic (`context/archive/2026-11-09-category-list-after-migration/migration-trial.md:50-58`).
- Kontroler flashuje toast i wraca `back()` (`app/Http/Controllers/CategoryListController.php:52-63`); komunikaty serwera to angielskie klucze z tłumaczeniem w `lang/pl.json`, teksty widoku są w `lang/pl/ui.php` (grupa `categories`, od :571).

## What We're NOT Doing

- **Cofanie w aplikacji** i dziennik scaleń. Powrót to `user:restore` ze snapshotu.
- **Scalanie do kategorii archiwalnej**: odrzucone z komunikatem „przywróć kategorię docelową”.
- **Scalanie więcej niż dwóch naraz** i podział kategorii (roadmapa, Parked).
- **Zmiana snapshotu**: wersja 10 zostaje, `RestoreUserData` bez zmian. Żadnej migracji.
- **Wybór, czyj rytm, nazwa, ikona, grupa albo opis zostają**: zawsze kategorii docelowej. Zmianę robi się przed albo po scaleniu na „Kategoriach”.
- **Przepisanie nazwy jako potwierdzenie** i pełne rozbicie podglądu (plan początkowy, wykonanie, każdy miesiąc osobno): odrzucone w planowaniu.
- **Przepinanie podglądu Importu w toku.** Podpowiedź w sesji może nieść id usuniętej kategorii; walidacja zapisu odrzuci taki wiersz jak dziś po usunięciu kategorii.
- **Cash Expectation, Earmark, Reserve**: nie ma ich w kodzie. ADR-0076 zapisuje, że zmiana, która je doda, dopisuje ich przepięcie do scalenia.
- **Sprzątanie plików snapshotów** i zmiana nazewnictwa plików `BackupUserData`.
- **Zmiana `components/ui/*`**, nowe tokeny kolorów, nowa biblioteka.

## Implementation Approach

Reguła domenowa stoi przed kodem (AGENTS.md): faza 1 zapisuje słownik i ADR-0076. Potem backend od środka: jedna klasa odczytu (`CategoryMergeReading`) wylicza skutek scalenia dla pary kategorii i służy zarówno podglądowi (`toArray()`), jak i akcji, więc podgląd i zapis nie mogą się rozjechać. Akcja `MergeCategories` wykonuje ten wynik zapisami zbiorczymi w jednej transakcji, pod blokadą listy typu, po snapshocie, z kontrolą sum na końcu. Granica HTTP to dwa punkty na nowym kontrolerze: podgląd JSON i scalenie. Front dokłada menu wiersza, jedno okno na stronie zbudowane na `ConfirmActionDialog` i kompozycję pobierającą podgląd wzorem palety poleceń. Kwoty żyją tylko w nowych plikach okna, więc strażnik „Kategorie bez kwot” zostaje. Każda faza ma własne testy; testy bazy idą na PostgreSQL. Na końcu próba na kopii produkcji, bo pierwsze prawdziwe użycie to dwie pary z 68 operacjami.

## Critical Implementation Details

### State sequencing

Kolejność w transakcji `MergeCategories` jest wymuszona przez klucze obce i przez to, że „zebrane” liczy się z danych sprzed zapisu:

1. blokada listy typu (`CategoryListStore::state`), ponowny odczyt obu kategorii i ponowne sprawdzenie odmów (żądanie mogło się zestarzeć), blokada pozycji budżetu obu kategorii;
2. snapshot (`BackupUserData`), po blokadach, przed pierwszym zapisem;
3. `CategoryMergeReading` policzone pod blokadą, razem z sumą „zebranego”, oraz sumy kontrolne „przed”;
4. operacje złożonych pozycji źródła → pozycje celu;
5. pozycje planu miesiąca: sumowanie tam, gdzie cel ma pozycję w tym planie (i usunięcie pozycji źródła), przepięcie w pozostałych;
6. plan początkowy, plan aktualny i wykonanie dodane do pozycji celu, usunięcie złożonych pozycji źródła;
7. przepięcie pozostałych pozycji źródła na cel (ze zdjęciem archiwum z pozycji budżetu w wykonaniu bieżącego roku, gdy źródło jest archiwalne);
8. nowy okres „zebranego” na celu, gdy jest nieregularny;
9. usunięcie kategorii źródłowej (kaskada usuwa jej historię rytmu);
10. sumy kontrolne „po” równe „przed”, inaczej wyjątek i wycofanie.

Usunięcie złożonej pozycji przed krokiem 4 kończy się błędem klucza obcego; policzenie „zebranego” po kroku 4 daje inną liczbę niż podgląd.

### Timing & lifecycle

„Bieżący miesiąc” i „bieżący rok” to `CarbonImmutable::today('UTC')` przekazane z kontrolera, tak jak w `CategoryListController` (:28, :42). Akcja i odczyt nie wołają `now()`, żeby podgląd i zapis czytały ten sam miesiąc i żeby testy mogły go ustawić.

### Debug & observability

Błąd snapshotu jest raportowany (`report($e)`) i wraca do okna jako błąd walidacji; nie zamienia się w ciche powodzenie. Rozjazd sum kontrolnych to wyjątek bez łapania: transakcja się wycofuje, a żądanie kończy się błędem 500 z wpisem w logu. Po udanym scaleniu akcja loguje (`Log::info`) id i nazwy obu kategorii oraz ścieżkę snapshotu, bo tylko tak użytkownik znajdzie właściwy plik do `user:restore`.

## Faza 1: Słownik i ADR-0076

### Overview

Zapisuje regułę scalania, zanim powstanie kod: nowy termin, zmienione klauzule i rozstrzygnięcie zostawione przez ADR-0066.

### Changes Required:

#### 1. ADR-0076

**File**: `context/foundation/adr/0076-category-merge.md`

**Intent**: Zapisać dziewięć reguł z „Desired End State” jako decyzję i wskazać, które klauzule wcześniejszych ADR-ów zmienia. ADR-0066 odsyła tu wprost („What happens to Collected on a merge (FR-023) S-21 decides”).

**Contract**: Angielski, w stylu ADR-0073 (akapit kontekstu, akapity decyzji z przykładem na liczbach, lista „We rejected”). Zawiera: reguły 1–9; zmianę klauzul „Initial Plan … Immutable afterwards” i „Approved Month Plan … immutable” (scalenie sumuje je na pozycji celu, sumy budżetu i planu miesiąca bez zmian); uzupełnienie ADR-0063 i ADR-0067 („Usuń” nadal odmawia przy historii, drugą drogą jest scalenie); uzupełnienie ADR-0066 i ADR-0073 (nowy okres na celu, zapis nadal tylko przez `ChangeCategoryRhythm`); skutek dla minionych miesięcy (operacje źródła czyta rytm celu, odczyty przeliczają się jak po spóźnionej operacji); UI (menu „⋯” w wierszu, okno na `ConfirmActionDialog`, strona „Kategorie” nadal bez kwot, kwoty tylko w oknie); zdanie, że Cash Expectation, Earmark i Reserve dopisują swoje przepięcie, gdy powstaną. Przykłady: para z produkcji (przepięcie), „Jedzenie poza domem” 3 000 + „Restauracje” 1 500 w jednym budżecie (suma 4 500), „Energia” 400 + „Prąd” 450 („zebrane” 850 od bieżącego miesiąca), „Wakacje” archiwalne → „Urlop”. „We rejected”: plan celu wygrywa; blokada przy dwóch pozycjach w jednym budżecie; samo przeliczenie łańcucha; blokada nieregularnego źródła; scalanie do archiwalnej; tylko aktywne; czwarta ikona; przycisk w nagłówku sekcji; jedno zdanie i pełne rozbicie w podglądzie; samo potwierdzenie i przepisanie nazwy; dziennik scaleń i cofanie w aplikacji.

#### 2. Słownik

**File**: `context/foundation/glossary.md`

**Intent**: Dodać termin i poprawić wpisy, których reguły scalenie zmienia, żeby kod i testy miały jedno słownictwo.

**Contract**: Nowy wpis **Category Merge** (po **Category Group**): definicja z reguł 1–9 w skrócie, odesłanie do ADR-0076, polska kopia UI „scalanie kategorii”, „Scal z inną…”, „Scal”; _Avoid_: join (to dołączenie kategorii do budżetu), link, combine, deduplicate, undo, „merge” dla migracji listy i dla łączenia wpisów rytmu. Zmiany w istniejących wpisach, po jednym zdaniu z odesłaniem do ADR-0076: **Initial Plan** i **Approved Month Plan** (wyjątek: scalenie sumuje), **Budget Line** (przepięcie albo złożenie przy scaleniu), **Category** (przy regule usuwania: inaczej archiwum albo Category Merge), **Rhythm** (historia celu zostaje), **Collected** i **Opening Collected** (nowy okres z sumą przy scaleniu).

#### 3. Reguła dla agentów

**File**: `.ai/rules/categories.md`, `.ai/rules/index.md`

**Intent**: Żeby kolejna zmiana dotykająca kategorii nie dodała tabeli wskazującej kategorię albo pozycję budżetu bez dopisania jej do scalenia.

**Contract**: Nowa sekcja „A merge folds the source Category into the target” (3–5 zdań: jedyna droga to `MergeCategories`, skutek liczy `CategoryMergeReading`, nowa tabela z `category_id` albo `budget_line_id` dopisuje swoje przepięcie i sumę kontrolną, zapisy zbiorcze). W zdaniu o zebranym startowym dopisek, że nowy okres przy scaleniu też zapisuje `ChangeCategoryRhythm`. Do `paths:` dochodzi `app/Http/Controllers/CategoryMergeController.php`; indeks odświeża `php artisan boost:index-rules`.

### Success Criteria:

#### Automated Verification:

- Indeks reguł jest aktualny: `php artisan boost:index-rules` nie zostawia zmian poza wierszem kategorii w `.ai/rules/index.md`
- Bramka przechodzi: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- ADR-0076 i słownik opisują dziewięć reguł i cztery przykłady bez sprzeczności z ADR-0063, ADR-0065, ADR-0066, ADR-0067 i ADR-0073

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 2: Odczyt scalenia i akcja

### Overview

Backend bez HTTP: klasa odczytu, nowy okres „zebranego” w `ChangeCategoryRhythm` i akcja `MergeCategories` ze snapshotem i kontrolą sum. Testy najpierw (odczyt, potem akcja), na PostgreSQL.

### Changes Required:

#### 1. Odczyt skutku scalenia

**File**: `app/Support/CategoryList/CategoryMergeReading.php`

**Intent**: Jedno miejsce, które dla pary kategorii i dnia wylicza, co scalenie zrobi w każdym budżecie i z „zebranym”. Podgląd i akcja biorą z niego te same liczby.

**Contract**: `CategoryMergeReading::for(Category $source, Category $target, CarbonImmutable $today): self`, stała liczba zapytań niezależna od liczby budżetów i operacji (pozycje obu kategorii z rokiem i fazą budżetu, liczby operacji per pozycja, pozycje planów miesięcy obu kategorii, „zebrane” przez `IrregularCollected` na `CategoryRhythms::forCategoryIds([źródło, cel])`). Udostępnia akcji listę par do złożenia (budżet, pozycja źródła, pozycja celu), id pozycji do przepięcia, pozycje planu miesiąca do zsumowania i do przepięcia oraz zebrane startowe nowego okresu (`null`, gdy cel nie jest nieregularny w miesiącu `$today`). `toArray()` zwraca ładunek podglądu:

```php
/**
 * @return array{
 *     source: array{id: int, name: string, archived: bool},
 *     target: array{id: int, name: string},
 *     budgets: list<array{year: int, phase: string, operations_count: int, target_plan_before_minor_units: int, target_plan_after_minor_units: int}>,
 *     operations_count: int,
 *     month_plan_items: array{count: int, first: array{year: int, month: int}|null, last: array{year: int, month: int}|null},
 *     collected: array{source_minor_units: int, target_minor_units: int|null, after_minor_units: int|null, since: array{year: int, month: int}}|null,
 *     restored_budget_year: int|null,
 * }
 */
```

`budgets` ma wiersz dla każdego budżetu, w którym źródło ma pozycję, rosnąco po roku; „przed” to plan aktualny pozycji celu w tym budżecie (0 bez pozycji), „po” to „przed” plus plan aktualny pozycji źródła. `month_plan_items` liczy pozycje planów miesięcy źródła. `collected`: cel nieregularny w miesiącu `$today` → wszystkie pola (źródło 0, gdy nie jest wtedy nieregularne), `since` to bieżący miesiąc; cel nie jest nieregularny, a źródło jest i ma „zebrane” > 0 → `target_minor_units` i `after_minor_units` równe `null` (utrata); inaczej `null`. `restored_budget_year` to rok budżetu w wykonaniu bieżącego roku, gdy źródło jest archiwalne i ma w nim zarchiwizowaną pozycję; inaczej `null`.

#### 2. Odmowy scalenia

**File**: `app/Support/CategoryList/CategoryMergeReading.php` (statyczna metoda) albo osobna klasa `CategoryMergeGuard` w tym samym katalogu

**Intent**: Te same odmowy sprawdza żądanie HTTP i, drugi raz pod blokadą, akcja.

**Contract**: Rzuca `ValidationException` z kluczem `target_id` i angielskim kluczem komunikatu: ta sama kategoria; inny użytkownik albo inny typ; cel archiwalny. Komunikaty: „A category cannot be merged with itself.”, „Only categories of the same type can be merged.”, „Restore the target category before merging.”.

#### 3. Nowy okres „zebranego”

**File**: `app/Actions/ChangeCategoryRhythm.php`

**Intent**: Scalenie musi zacząć nowy okres na kategorii, która już jest nieregularna, a `handle()` w tym przypadku tylko poprawia kwotę na starym wpisie.

**Contract**: Nowa publiczna metoda `startIrregularPeriod(Category $category, CarbonImmutable $today, int $openingCollectedMinorUnits): void`: pod blokadą kategorii usuwa wpisy zaczynające się w bieżącym miesiącu albo później i wstawia jeden wpis `irregular` od bieżącego miesiąca z podaną kwotą. Wpisy wcześniejszych miesięcy zostają (miniony okres kończy się miesiącem przed nowym). Wspólny kod z końcówką `handle()` (:74-87) idzie do prywatnej metody. Docblock klasy dostaje zdanie o scaleniu.

#### 4. Akcja scalania

**File**: `app/Actions/MergeCategories.php`

**Intent**: Wykonać skutek z odczytu w jednej transakcji, ze snapshotem przed zapisem i kontrolą sum po nim.

**Contract**: `handle(Category $source, Category $target, CarbonImmutable $today): void`, zależności przez konstruktor (`BackupUserData`, `ChangeCategoryRhythm`). Kolejność z „Critical Implementation Details”. Zapisy zbiorcze (`UPDATE … CASE` wzorem `CategoryListStore::write()`, `whereIn(...)->update/delete`), bez zapisu w pętli. Błąd snapshotu: `report($e)` i `ValidationException` z kluczem `category` („The safety copy could not be saved, so nothing was merged.”). Rozjazd sum kontrolnych: `LogicException` z nazwą sumy i wartościami, bez łapania. Po sukcesie `Log::info` z id i nazwami obu kategorii oraz ścieżką snapshotu. Metoda publiczna krótka, kroki w prywatnych metodach (Sloppy: god method).

#### 5. Testy odczytu i akcji

**File**: `tests/Feature/CategoryMergeReadingTest.php`, `tests/Feature/MergeCategoriesTest.php`, `tests/Feature/CategoryRhythmWriteTest.php`

**Intent**: Dowieść reguł 1–9 na bazie, w tym na ograniczeniach, które istnieją tylko w PostgreSQL w tej postaci.

**Contract**: Styl jak w sąsiednich plikach (odczyty: funkcje Pest; akcja: klasa `TestCase` z `RefreshDatabase`), fabryki `BudgetLine::factory()->for($budget)->expense()`, `->irregular($opening)`, `->archived()`. Przypadki:

- przepięcie: źródło ma pozycję tylko w 2026, cel tylko w szkicu 2027; id pozycji, operacje i pozycje planu bez zmian, kategoria źródłowa i jej wpisy rytmu usunięte;
- złożenie: obie w 2026 z planami 3 000 i 1 500 → pozycja celu ma plan początkowy, plan aktualny i wykonanie równe sumom, wszystkie operacje na pozycji celu, pozycja źródła usunięta;
- plan miesiąca: obie mają pozycję w listopadzie (250/250 i 100/150 → 350/400), tylko źródło ma pozycję w październiku (przepięta);
- „zebrane”: cel i źródło nieregularne (450 + 400 → nowy wpis od bieżącego miesiąca z 850, wpis ze stycznia zostaje); cel nieregularny, źródło nie (nowy wpis z samym „zebranym” celu); cel nie jest nieregularny (brak zapisu rytmu, odczyt zgłasza utratę 400); kategorie przychodowe (brak rytmu, `collected` równe `null`);
- archiwalne źródło: pozycja 2026 przepięta i aktywna, `restored_budget_year` równe 2026; pozycja starszego roku zachowuje `archived_at`; złożenie z aktywną pozycją celu zostawia stan celu;
- odmowy: ta sama kategoria, inny typ, kategoria innego użytkownika, cel archiwalny; stan bazy bez zmian;
- snapshot: plik powstaje przed zapisem i zawiera kategorię źródłową; gdy `BackupUserData` rzuca, nic się nie zmienia i wyjątek jest zaraportowany;
- kontrola: sekcje `budgets` i salda z `App\Support\ControlNumbers` oraz liczba operacji są równe przed i po w przypadku przepięcia i złożenia;
- snapshot po scaleniu wczytuje się przez `RestoreUserData` (żadna walidacja restore nie odrzuca stanu po scaleniu);
- `startIrregularPeriod`: z wpisem od stycznia daje dwa wpisy, z wpisem od bieżącego miesiąca zastępuje go, usuwa wpis przyszły.

### Success Criteria:

#### Automated Verification:

- Testy odczytu, akcji i zapisu rytmu przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/CategoryMergeReadingTest.php tests/Feature/MergeCategoriesTest.php tests/Feature/CategoryRhythmWriteTest.php`
- Testy „zebranego” i snapshotu nadal przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/IrregularCollectedTest.php tests/Feature/UserSnapshotCommandTest.php`
- Formatowanie i bramka: `vendor/bin/pint --dirty --format agent`, `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Przegląd `MergeCategories`: kolejność kroków zgodna z „Critical Implementation Details”, żadnego zapisu w pętli, żadnego `catch` bez `report`

**Implementation Note**: Podgląd i nowy wpis rytmu niosą „zebrane” na 1. dzień bieżącego miesiąca, a wiersz planu miesiąca pokazuje stan na koniec miesiąca (ADR-0073), więc te liczby różnią się o stawkę i rachunki bieżącego miesiąca; to zamierzone. Test ma dowieść, że „zebrane” na koniec bieżącego miesiąca po scaleniu równa się sumie obu odczytów sprzed scalenia, gdy żaden nie trafia w podłogę 0. Jeśli tak nie jest, zatrzymaj się i zapytaj; nie zmieniaj reguły 6 bez decyzji. Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 3: Granica HTTP

### Overview

Dwa punkty: podgląd JSON i scalenie. Uprawnienia, walidacja, komunikaty po polsku i testy odpowiedzi.

### Changes Required:

#### 1. Polityka

**File**: `app/Policies/CategoryPolicy.php`

**Intent**: Scalać może tylko właściciel kategorii źródłowej.

**Contract**: `merge(User $user, Category $category): bool`, sama własność (źródło może być archiwalne). Własność i stan celu sprawdza żądanie.

#### 2. Żądanie

**File**: `app/Http/Requests/MergeCategoryRequest.php`

**Intent**: Jedna walidacja dla podglądu (GET z `target_id` w zapytaniu) i dla scalenia (POST).

**Contract**: `authorize()`: `can('merge', $this->route('category'))`. Reguły: `target_id` wymagane, liczba całkowita, istnieje w `categories` tego użytkownika. `after()` woła odmowy z fazy 2. Metoda zwracająca model kategorii docelowej dla kontrolera.

#### 3. Kontroler i trasy

**File**: `app/Http/Controllers/CategoryMergeController.php`, `routes/web.php`

**Intent**: Podgląd czytany przy każdej zmianie celu w oknie i zapis scalenia.

**Contract**: `show(MergeCategoryRequest, Category): JsonResponse` zwraca `CategoryMergeReading::for(...)->toArray()`; `store(MergeCategoryRequest, Category, MergeCategories): RedirectResponse` flashuje toast `success` („Categories merged.”) i wraca `back()`. Oba przekazują `CarbonImmutable::today('UTC')`. Trasy w grupie `auth`, `verified`, obok pozostałych tras `categories/{category}`: `GET categories/{category}/merge-preview` → `categories.merge.preview`, `POST categories/{category}/merge` → `categories.merge`. Po zmianie tras `npm run build` przed `npm run types:check`.

#### 4. Komunikaty

**File**: `lang/pl.json`

**Intent**: Odmowy i toast po polsku (toasty kategorii nie mają dziś tłumaczeń; ta zmiana dodaje tylko swoje).

**Contract**: Pięć wpisów: trzy odmowy z fazy 2, błąd snapshotu i „Categories merged.” → „Kategorie scalone.”.

#### 5. Typ ładunku

**File**: `resources/js/types/categories.ts`

**Intent**: Typ TypeScript ładunku podglądu powstaje razem z punktem, który go zwraca, żeby obie strony zmieniały się w jednym commicie.

**Contract**: `CategoryMergePreviewData`, pole w pole jak `CategoryMergeReading::toArray()`.

#### 6. Testy HTTP

**File**: `tests/Feature/CategoryMergeHttpTest.php`

**Intent**: Dowieść kształtu odpowiedzi, uprawnień i tego, że odmowa nie zmienia danych.

**Contract**: Gość → przekierowanie na logowanie (oba punkty); kategoria innego użytkownika jako źródło → 403; cel innego użytkownika, cel innego typu, ta sama kategoria, cel archiwalny, brak `target_id` → błąd `target_id` (podgląd: 422 z komunikatem; scalenie: błąd sesji) i brak zmian w bazie; podgląd zwraca dokładny JSON dla przypadku przepięcia i złożenia (`assertExactJson`); scalenie z `from(route('categories.index'))` wraca na „Kategorie” z toastem, a strona nie ma już źródła w `expenseCategories` (`assertInertia`).

### Success Criteria:

#### Automated Verification:

- Testy HTTP przechodzą na PostgreSQL: `… artisan test --compact tests/Feature/CategoryMergeHttpTest.php tests/Feature/CategoriesPageTest.php tests/Feature/CategoryListWriteTest.php`
- Trasy istnieją: `php artisan route:list --name=categories.merge`
- Wayfinder i typy: `npm run build && npm run types:check`
- Formatowanie i bramka: `vendor/bin/pint --dirty --format agent`, `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Na dev `GET /categories/{id}/merge-preview?target_id={id}` dla dwóch kategorii wydatkowych zwraca liczby zgodne z „Budżetem” i „Operacjami”

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 4: Okno scalania i menu wiersza

### Overview

Menu „⋯” w wierszu kategorii, okno scalania z wyborem celu i podglądem z serwera, stany w kitchen sinkach, strażniki UI i strona design systemu. Przed edycją plików Vue przeczytać `.ai/rules/js.md`, `js-dialogs.md`, `wayfinder.md` i `css.md`.

### Changes Required:

#### 1. Logika okna

**File**: `resources/js/lib/category-merge.ts`, `resources/js/lib/category-merge.test.ts`

**Intent**: Czysta logika poza komponentami: lista celów, dostępność scalania i teksty podglądu.

**Contract**: `mergeTargetOptions(source, categories, groups): CategoryPickerOption[]` (aktywne kategorie typu źródła bez niej samej, w kolejności drzewa z `buildCategoryTree`, z obiektem grupy dla `CategorySelect`); `canMergeCategory(source, categories): boolean` (istnieje inna aktywna kategoria tego typu); pomocniki tekstów: zakres miesięcy pozycji planów („I–XI 2026”, także przez granicę roku) i polskie formy liczebnika wzorem `activeCountForm` z `lib/category-list.ts`. Testy Vitest obok, import z `vite-plus/test`.

#### 2. Pobieranie podglądu

**File**: `resources/js/composables/useCategoryMergePreview.ts`

**Intent**: Wczytać podgląd przy każdej zmianie celu i nie pokazać odpowiedzi spóźnionego żądania.

**Contract**: Wzorzec `useCommandPalette.ts:36-83`: `useHttp`, GET na `CategoryMergeController.show` z `target_id`, stan `{ status: 'idle' } | { status: 'loading' } | { status: 'loaded', preview } | { status: 'error', message }`, licznik żądań, reset przy zamknięciu okna i przy pustym celu. Odpowiedź 422 daje `error` z komunikatem serwera; inne błędy idą do `console.error` z przyczyną i dają `error` z tekstem ogólnym; `HttpCancelledError` nie jest błędem.

#### 3. Blok podglądu

**File**: `resources/js/components/CategoryMergePreview.vue`, `resources/css/app.css`

**Intent**: Pokazać ładunek podglądu zgodnie z decyzją „tabela per budżet”.

**Contract**: Komponent prezentacyjny z propem stanu z kompozycji. Stan `loading`: spinner z tekstem; `error`: `InputError`; `loaded`: nagłówek „Na „<cel>” przejdzie”, tabela Budżet | Operacje | Plan aktualny (rok z dopiskiem „szkic” dla fazy `draft`, liczba operacji, „przed → po” przez `formatMoney`, wiersz „Razem” z liczbą operacji), linia o pozycjach planów miesięcy (pominięta przy zerze), linia „Zebrane: cel + źródło → suma, od <miesiąc>” gdy `collected.after_minor_units` nie jest `null`, `Alert variant="warning"` o utracie „zebranego” gdy jest `null`, `Alert variant="note"` o powrocie pozycji do sum budżetu gdy `restored_budget_year` nie jest `null`, oraz `Alert variant="danger"` „Tej operacji nie można cofnąć…”. Tabela na nowej nazwanej klasie siatki w `@layer components` (`.category-merge-grid`), nagłówki w stylu `table-header`, liczby `tabular-nums`; żadnych wartości dowolnych w szablonie.

#### 4. Blokada potwierdzenia

**File**: `resources/js/components/ConfirmActionDialog.vue`

**Intent**: „Scal” ma być nieaktywne, dopóki podgląd wybranego celu się nie wczyta.

**Contract**: Opcjonalny prop `confirmDisabled?: boolean` (domyślnie `false`), łączony z `processing` na przycisku potwierdzenia. Pozostali użytkownicy komponentu bez zmian.

#### 5. Okno scalania

**File**: `resources/js/components/CategoryMergeDialog.vue`

**Intent**: Jedno okno na stronie: źródło w tytule, wybór celu, podgląd, potwierdzenie.

**Contract**: Propsy: `open` (`v-model`), `source: CategoryListItem | null`, `targets: CategoryPickerOption[]`, oraz dwa nadpisania tylko dla kitchen sinka, wzorem `CategoryListFormDialog.vue:46-47`: `form?` i `previewState?`. Zbudowane na `ConfirmActionDialog` (`destructive`, formularz `CategoryMergeController.store.form(source.id)`), w slocie `FormField` z `CategorySelect` o `name="target_id"` i `CategoryMergePreview`. `confirmDisabled`, gdy stan podglądu nie jest `loaded`. Zamknięcie i zmiana źródła czyszczą wybór. Bez własnego `DialogContent`.

#### 6. Menu wiersza

**File**: `resources/js/components/CategoryListRowActions.vue`, `resources/js/components/CategoryListRow.vue`, `resources/js/components/CategoryArchiveSection.vue`

**Intent**: Wejście do scalania z wiersza kategorii, która ma zniknąć; „Usuń” przenosi się do tego samego menu.

**Contract**: Edycja i archiwum (albo przywrócenie w archiwum) zostają ikonami. Za nimi `DropdownMenu` z przyciskiem „⋯” (`aria-label` z nazwą kategorii, wzorem `CategoryGroupRow.vue:82-126`): „Scal z inną…” (nieaktywne, gdy prop `canMerge` jest `false`; emituje `merge`) i, tylko przy `can_delete`, „Usuń” (klasa destrukcyjna; otwiera istniejące `ConfirmActionDialog` sterowane `v-model:open`, `design-system.md:102`). Menu nie renderuje się, gdy nie ma żadnej pozycji do pokazania. `CategoryListRow` i `CategoryArchiveSection` przekazują `canMerge` i przepuszczają zdarzenie `merge` z kategorią.

#### 7. Strona

**File**: `resources/js/pages/Categories.vue`

**Intent**: Trzymać stan jednego okna scalania, tak jak okna edycji.

**Contract**: Stan `mergeSource` i `mergeDialogOpen`, funkcja otwierająca wzorem `openEditCategoryDialog` (:118-123), cele z `mergeTargetOptions`, `canMerge` per wiersz z `canMergeCategory`, jeden `<CategoryMergeDialog>` obok `CategoryListFormDialog`. Strona nie importuje funkcji formatujących kwoty (`ui-contract.test.ts:491-495`).

#### 8. Teksty

**File**: `lang/pl/ui.php`

**Intent**: Wszystkie teksty okna i menu w grupie `categories`.

**Contract**: Klucze `merge_*` i `more_actions`: pozycja menu „Scal z inną…”, etykieta przycisku „⋯” z `:name`, tytuł „Scal kategorię „:name””, opis („Ta kategoria zniknie z listy. Jej operacje, plany i zebrane przejdą na kategorię docelową.”), etykieta pola „Scal z”, placeholder, nagłówek podglądu z `:name`, nagłówki kolumn, „Razem”, dopisek „szkic”, linia planów miesięcy w trzech formach liczebnika, linia „zebrane”, ostrzeżenie o utracie z `:amount` i `:name`, notatka o archiwum z `:year`, zdanie o nieodwracalności, stany „Wczytuję podgląd…” i błąd ogólny, „Scal”, „Anuluj”. `tests/Unit/UiTranslationKeysTest.php` sprawdza użyte klucze.

#### 9. Kitchen sinki i fixtures

**File**: `resources/js/lib/categories-fixtures.ts`, `resources/js/pages/dev/DialogsKitchenSink.vue`, `resources/js/pages/dev/CategoriesKitchenSink.vue`

**Intent**: Każdy stan okna i wiersza jest widoczny bez bazy (AGENTS.md, `js-dialogs.md`).

**Contract**: Fixtures ładunków podglądu. Stany w `/dev/ui/dialogs` (wpisy w `states` i `labels`, otwieranie przez `?open=`): `merge-idle` (bez wybranego celu), `merge-loading`, `merge-move` (para z produkcji: 2026, 48 operacji, 0,00 → 3 000,00), `merge-fold` (1 500,00 → 4 500,00 i pozycje planów miesięcy), `merge-collected` (450,00 + 400,00 → 850,00), `merge-collected-lost`, `merge-archived-source`, `merge-preview-error`, `merge-saving` i `merge-server-error` (przez `DialogsKitchenSinkController.validate.form()`). W `/dev/ui/categories` macierz stanów opisuje menu „⋯”, a potwierdzenie usunięcia otwiera się z menu.

#### 10. Strażniki UI i E2E usuwania

**File**: `scripts/check-ui-literals.mjs`, `tests/Unit/CategoriesHardcodedUiTest.php`, `resources/js/ui-contract.test.ts`, `tests/e2e/categories.spec.ts`

**Intent**: Nowe pliki pod tymi samymi strażnikami co reszta „Kategorii”; istniejący test E2E nie może zostać czerwony po przeniesieniu „Usuń” do menu.

**Contract**: `CategoryMergeDialog.vue`, `CategoryMergePreview.vue` i `lib/category-merge.ts` dopisane do `FILES` i do `CATEGORIES_VIEW_FILES` w teście PHP. W `ui-contract.test.ts` nowe pliki okna **nie** trafiają na listę „shows no amount”; dochodzi asercja, że `CategoryMergeDialog.vue` nie importuje `DialogContent`. W `categories.spec.ts` krok usuwania kategorii (test od :108) otwiera menu „⋯” i wybiera „Usuń”.

#### 11. Strona design systemu

**File**: `context/foundation/design-system.md`

**Intent**: Opis „Kategorii” i gotowych elementów zgodny z kodem.

**Contract**: W akapicie o „Kategoriach” (okolice :120): menu „⋯” wiersza kategorii, okno scalania, klasa `.category-merge-grid`, zdanie, że kwoty występują tylko w oknie scalania, i nowe stany sinka. W „Gotowych elementach” przy `ConfirmActionDialog` dopisek o `confirmDisabled`.

### Success Criteria:

#### Automated Verification:

- Testy logiki okna i kontraktu UI przechodzą: `npm test -- category-merge ui-contract`
- Testy kitchen sinków, kluczy tłumaczeń i literałów UI przechodzą: `php artisan test --compact tests/Feature/DialogsKitchenSinkTest.php tests/Feature/CategoriesKitchenSinkTest.php tests/Unit/CategoriesHardcodedUiTest.php tests/Unit/UiTranslationKeysTest.php` i `npm run check:ui`
- E2E „Kategorii” przechodzi po przeniesieniu „Usuń” do menu: `npx playwright test tests/e2e/categories.spec.ts`
- Bramka: `composer ci:check`, `npm run build && npm run types:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- `/dev/ui/dialogs` pokazuje dziesięć stanów okna scalania w trybie jasnym i ciemnym; tabela podglądu mieści się w oknie 460 px i na szerokości 390 px
- Na dev „Scal z inną…” z wiersza otwiera okno, zmiana celu przeładowuje podgląd, a „Scal” jest nieaktywne do wczytania podglądu
- Menu „⋯” działa z klawiatury (otwarcie, strzałki, Enter, Escape), a fokus po zamknięciu okna wraca w sensowne miejsce
- Wiersz kategorii mieści akcje w kolumnie 7rem, także w archiwum i w grupie

**Implementation Note**: Jeśli tabela podglądu nie mieści się w oknie 460 px bez wartości dowolnych albo bez zmiany tokenów okna, zatrzymaj się i zapytaj; nie poszerzaj okna bez decyzji. Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 5: E2E, README i bramka

### Overview

Dowód w przeglądarce, że scalenie działa od menu do listy, opis drogi powrotu i pełna bramka. Test E2E prowadzi `/10x-e2e`.

### Changes Required:

#### 1. Test E2E scalenia

**File**: `tests/e2e/categories.spec.ts`

**Intent**: Pierwsze okno z podglądem pobieranym z serwera ma dowód w prawdziwej przeglądarce (ryzyko #7 planu testów).

**Contract**: Jeden test: tworzy dwie świeże kategorie wydatkowe o unikalnych nazwach i operację na pierwszej (pomocnik `post()` z :21-36 albo UI), otwiera „⋯” → „Scal z inną…”, wybiera drugą, czeka na podgląd i sprawdza liczbę operacji, potwierdza „Scal”, widzi toast, brak wiersza źródła i operację pod filtrem kategorii docelowej (link „Operacje →”). Sprząta po sobie (usuwa operację i kategorię docelową), bo baza E2E jest współdzielona. Bez błędów w konsoli.

#### 2. README

**File**: `README.md`

**Intent**: Użytkownik po pomyłce musi wiedzieć, gdzie jest plik i jak wrócić.

**Contract**: W sekcji „Wdrożenie i przywracanie” krótki akapit „Scalanie kategorii”: akcja zapisuje snapshot w `storage/app/user-snapshots` przed scaleniem i loguje jego ścieżkę; powrót to `user:restore {user} {path}`, który cofa także wszystko, co wpisano po scaleniu, i nadaje rekordom nowe id; zmiana nie ma migracji, więc liczby kontrolne przed i po wdrożeniu są identyczne.

#### 3. Roadmapa

**File**: `context/foundation/roadmap.md`

**Intent**: Ryzyko S-21 opisuje już stan po decyzjach, nie pytanie.

**Contract**: W bloku S-21 zdanie „Jeśli zebrane jeszcze nie istnieje…” zastąpione odesłaniem do ADR-0076. Statusy zmieniają tylko `/10x-implement` i `/10x-archive`.

### Success Criteria:

#### Automated Verification:

- Cały zestaw E2E przechodzi: `npx playwright test`
- Pełna bramka na PostgreSQL przechodzi: `docker run … --entrypoint composer sail-8.5/app ci:check` (komenda z AGENTS.md, „PostgreSQL tests, worktree”)
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`

#### Manual Verification:

- Na dev scalenie dwóch kategorii z operacjami w tym samym budżecie: „Budżet” pokazuje jedną pozycję z sumą planów i wykonania, „Operacje” pod filtrem celu pokazują wszystkie operacje, suma planu budżetu bez zmian
- Na dev scalenie nieregularnej do nieregularnej: wiersz planu miesiąca pokazuje „zebrane” równe sumie z podglądu, a „Kategorie” pokazują „nieregularna od” bieżącego miesiąca
- README opisuje powrót tak, że da się go wykonać bez czytania kodu

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 6: Próba na kopii produkcji

### Overview

Ręczne scalenie obu par z produkcji na lokalnej kopii, z liczbami kontrolnymi przed i po, oraz sprawdzenie drogi powrotu. Wzór: `context/archive/2026-11-09-category-list-after-migration/migration-trial.md`.

### Changes Required:

#### 1. Zapis próby

**File**: `context/changes/category-merge/merge-trial.md`

**Intent**: Dowód, że pierwsze prawdziwe scalenie nie zmienia żadnej kwoty i że da się z niego wrócić, zanim dotknie produkcji.

**Contract**: Dokument z datą, wersją snapshotu i wynikami kroków: (1) osobna lokalna baza, `migrate`, użytkownik, `user:restore` aktualnego snapshotu produkcji; (2) `app:control-numbers --output=` „przed”; (3) scalenie w UI (podgląd worktree według `context/foundation/agents/worktree-preview.md`): „Jedzenie poza domem” → „Restauracje” i „Eksploatacja domu” → „Utrzymanie domu”, z zapisanymi liczbami z podglądu; (4) `app:control-numbers --compare=` z plikiem „przed”: brak różnic, gdy pary nadal leżą w różnych budżetach (różni się tylko pomijana sekcja `meta`); gdyby któraś para dzieliła już budżet, dozwolone różnice to wyłącznie `row_counts.budget_lines`, `row_counts.month_plan_items` i klucze `lines.<id>` złożonych pozycji, a sekcje `budgets`, liczba operacji i salda muszą się zgadzać; (5) oględziny: 39 kategorii na liście, „Operacje” pod „Restauracje” pokazują 48 operacji z 2026, pod „Utrzymanie domu” 20, budżet 2026 i szkic 2027 mają te same sumy; (6) `user:restore` z pliku zapisanego przez pierwsze scalenie wraca do 41 kategorii z obiema parami osobno. `user:restore` nadaje nowe id (`app/Actions/RestoreUserData.php:178-326`), więc tego kroku nie da się sprawdzić przez `--compare`; porównuje się sumy per rok budżetu, liczbę operacji i salda z pliku „przed”. Odstępstwa i decyzje użytkownika zapisane w dokumencie.

### Success Criteria:

#### Automated Verification:

- Liczby kontrolne po obu scaleniach nie różnią się od stanu „przed”: `php artisan app:control-numbers --compare=<plik przed>` kończy się kodem 0 (albo pokazuje tylko dozwolone różnice z kroku 4)

#### Manual Verification:

- Podgląd w oknie pokazał 48 i 20 operacji oraz plany zgodne z „Budżetem” 2026 sprzed scalenia
- Po scaleniu lista ma 39 kategorii, a operacje obu par są pod kategoriami docelowymi we wszystkich latach
- Po `user:restore` z pliku zapisanego przez pierwsze scalenie lista ma 41 kategorii, a sumy per rok budżetu, liczba operacji i salda równają się stanowi „przed”
- `merge-trial.md` zapisany, a użytkownik potwierdził gotowość do scalenia par na produkcji po wdrożeniu

**Implementation Note**: Próba wymaga aktualnego snapshotu produkcji (`user:backup` na serwerze); bez niego zatrzymaj się i poproś o plik. Scalenie par na produkcji wykonuje użytkownik po wdrożeniu i nie należy do tej fazy.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/category-merge.test.ts`: lista celów (typ, bez źródła, bez archiwalnych, kolejność drzewa, obiekt grupy), dostępność scalania przy jednej kategorii typu, zakres miesięcy przez granicę roku, formy liczebnika 1 / 2–4 / 5+.
- `resources/js/ui-contract.test.ts`: okno scalania bez `DialogContent`; „Kategorie” nadal bez kwot.

### Integration Tests:

- `tests/Feature/CategoryMergeReadingTest.php`: ładunek podglądu dla przepięcia, złożenia, planów miesięcy, czterech przypadków „zebranego”, archiwalnego źródła.
- `tests/Feature/MergeCategoriesTest.php`: każdy z dziewięciu punktów „Desired End State”, odmowy, snapshot i jego awaria, równość liczb kontrolnych, restore snapshotu zrobionego po scaleniu.
- `tests/Feature/CategoryRhythmWriteTest.php`: `startIrregularPeriod`.
- `tests/Feature/CategoryMergeHttpTest.php`: uprawnienia, walidacja, dokładny JSON, przekierowanie i toast.
- `tests/e2e/categories.spec.ts`: scalenie od menu do listy; usuwanie z menu.
- Wszystkie testy PHP z bazą uruchamiane na PostgreSQL (komenda z AGENTS.md); zielony SQLite niczego tu nie dowodzi, bo scalenie stoi na ograniczeniach unikalności i kluczach obcych.

### Manual Testing Steps:

1. `/dev/ui/dialogs`: dziesięć stanów okna, tryb jasny i ciemny, 390 px.
2. Dev: scalenie dwóch kategorii z jednego budżetu, porównanie „Budżetu” i „Operacji” przed i po.
3. Dev: scalenie nieregularnej do nieregularnej i nieregularnej do bieżącej (ostrzeżenie o utracie).
4. Dev: scalenie archiwalnej do aktywnej (notatka o powrocie do sum, pozycja aktywna w budżecie bieżącego roku).
5. Próba na kopii produkcji (faza 6), razem z `user:restore`.

## Performance Considerations

Odczyt i akcja mają stałą liczbę zapytań niezależnie od liczby budżetów i operacji; przy ok. 1 300 operacjach na produkcji scalenie to kilkanaście zapytań i jeden plik JSON snapshotu. Podgląd liczy „zebrane” tylko dla dwóch kategorii. Osobny test NFR nie jest potrzebny; Sloppy pilnuje zapytań i zapisów w pętli.

## Migration Notes

- Bez migracji schematu i bez zmiany wersji snapshotu; `scripts/deploy.sh` działa bez zmian, a liczby kontrolne przed i po wdrożeniu są identyczne.
- Wycofanie kodu: zwykły `git revert`; dane scalone przed wycofaniem zostają scalone i są poprawne dla starego kodu (to zwykłe kategorie i pozycje).
- Wycofanie pojedynczego scalenia: `user:restore` z pliku zapisanego przez akcję (ścieżka w logu aplikacji). Przywrócenie cofa także wszystko, co wpisano po scaleniu, i nadaje rekordom nowe id.
- Po wdrożeniu użytkownik scala obie pary na produkcji w UI; to porządki danych z Otwartego pytania 4 roadmapy i warunek pomiaru trafności Importu „przed” w S-23.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-21, S-23, Otwarte pytanie 4)
- PRD: `context/foundation/prd-v3.md` (FR-023, :173, :218)
- ADR: `context/foundation/adr/0063-one-category-list-across-years.md`, `0065-…`, `0066-category-rhythm-and-irregular-collected.md` (:24), `0067-…`, `0068-…` (:18), `0072-…`, `0073-irregular-collected-storage-edge-cases-and-warnings.md`
- Reguły: `.ai/rules/categories.md`, `.ai/rules/snapshot.md`, `.ai/rules/sloppy.md`, `.ai/rules/js.md`, `.ai/rules/js-dialogs.md`
- Próba migracji z parami do scalenia: `context/archive/2026-11-09-category-list-after-migration/migration-trial.md:50-58`
- Wzorzec zapisów zbiorczych i blokady listy: `app/Support/CategoryList/CategoryListStore.php:24-97`
- Wzorzec zapisu rytmu: `app/Actions/ChangeCategoryRhythm.php:30-89`
- Wzorzec podglądu z serwera: `resources/js/composables/useCommandPalette.ts:36-83`
- Wzorzec menu „⋯” z potwierdzeniem: `resources/js/components/CategoryGroupRow.vue:82-147`
- Wzorzec kontrolera zapisów „Kategorii”: `app/Http/Controllers/CategoryListController.php:52-63`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Słownik i ADR-0076

#### Automated

- [x] 1.1 Indeks reguł jest aktualny — a25a1f1
- [x] 1.2 Bramka przechodzi — a25a1f1

#### Manual

- [x] 1.3 ADR-0076 i słownik opisują dziewięć reguł i cztery przykłady bez sprzeczności z wcześniejszymi ADR-ami — a25a1f1

### Phase 2: Odczyt scalenia i akcja

#### Automated

- [x] 2.1 Testy odczytu, akcji i zapisu rytmu przechodzą na PostgreSQL — 3085eaa
- [x] 2.2 Testy „zebranego” i snapshotu nadal przechodzą na PostgreSQL — 3085eaa
- [x] 2.3 Formatowanie i bramka — 3085eaa

#### Manual

- [x] 2.4 Przegląd MergeCategories: kolejność kroków, brak zapisu w pętli, brak catch bez report — 3085eaa

### Phase 3: Granica HTTP

#### Automated

- [x] 3.1 Testy HTTP przechodzą na PostgreSQL — c7738f3
- [x] 3.2 Trasy istnieją — c7738f3
- [x] 3.3 Wayfinder i typy — c7738f3
- [x] 3.4 Formatowanie i bramka — c7738f3

#### Manual

- [x] 3.5 Podgląd JSON na dev zwraca liczby zgodne z „Budżetem” i „Operacjami” — c7738f3

### Phase 4: Okno scalania i menu wiersza

#### Automated

- [x] 4.1 Testy logiki okna i kontraktu UI przechodzą — 48a67aa
- [x] 4.2 Testy kitchen sinków, kluczy tłumaczeń i literałów UI przechodzą — 48a67aa
- [x] 4.3 E2E „Kategorii” przechodzi po przeniesieniu „Usuń” do menu — 48a67aa
- [x] 4.4 Bramka — 48a67aa

#### Manual

- [x] 4.5 Kitchen sink pokazuje dziesięć stanów okna scalania w trybie jasnym i ciemnym, tabela mieści się w 460 px i na 390 px — 48a67aa
- [x] 4.6 Na dev okno otwiera się z wiersza, zmiana celu przeładowuje podgląd, „Scal” nieaktywne do wczytania — 48a67aa
- [x] 4.7 Menu „⋯” działa z klawiatury, fokus wraca po zamknięciu okna — 48a67aa
- [x] 4.8 Wiersz kategorii mieści akcje w kolumnie 7rem, także w archiwum i w grupie — 48a67aa

### Phase 5: E2E, README i bramka

#### Automated

- [x] 5.1 Cały zestaw E2E przechodzi — f5f9e0d
- [x] 5.2 Pełna bramka na PostgreSQL przechodzi — f5f9e0d
- [x] 5.3 Sloppy bez nowych znalezisk — f5f9e0d

#### Manual

- [x] 5.4 Na dev scalenie dwóch kategorii z jednego budżetu daje jedną pozycję z sumami, suma planu budżetu bez zmian — f5f9e0d
- [x] 5.5 Na dev scalenie nieregularnej do nieregularnej daje „zebrane” równe sumie z podglądu — f5f9e0d
- [x] 5.6 README opisuje powrót tak, że da się go wykonać bez czytania kodu — f5f9e0d

### Phase 6: Próba na kopii produkcji

#### Automated

- [x] 6.1 Liczby kontrolne po obu scaleniach nie różnią się od stanu „przed”

#### Manual

- [ ] 6.2 Podgląd w oknie pokazał 48 i 20 operacji oraz plany zgodne z „Budżetem” 2026
- [ ] 6.3 Po scaleniu lista ma 39 kategorii, operacje obu par są pod kategoriami docelowymi
- [ ] 6.4 Po user:restore z pliku zapisanego przez pierwsze scalenie lista ma 41 kategorii, sumy, liczba operacji i salda równe stanowi „przed”
- [ ] 6.5 merge-trial.md zapisany, użytkownik potwierdził gotowość do scalenia par na produkcji
