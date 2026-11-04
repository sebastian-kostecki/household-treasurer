---
date: 2026-11-04T09:47:07+01:00
researcher: Claude (Opus 5.5) z Sebastianem Kosteckim
git_commit: 557d2ba99334798b54a4e3799cc35ea24fef966a
branch: development
repository: household-treasurer
topic: "Podpowiedź kategorii w podglądzie Importu: gdzie ją liczyć i wstrzyknąć, na jakie kategorie mapować historię, jakie decyzje domenowe i kontrakt UI ją ograniczają"
tags: [research, import, category-suggestion, CsvImportController, ImportPreviewRow, CategorySelect, CONTEXT.md, ADR-0014, ADR-0016, ADR-0015]
status: complete
last_updated: 2026-11-04
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Pierwsza wersja."
---

# Research: Podpowiedź kategorii w podglądzie Importu

**Date**: 2026-11-04T09:47:07+01:00
**Researcher**: Claude (Opus 5.5) z Sebastianem Kosteckim
**Git Commit**: 557d2ba99334798b54a4e3799cc35ea24fef966a
**Branch**: development
**Repository**: household-treasurer

## Research Question

Dla zmiany `import-category-suggestions` (`change.md`): wiersz podglądu Importu dostaje wstępnie wybraną kategorię, liczoną w PHP z wag słów z wcześniejszych Financial Operations. Pytania:

1. Gdzie w potoku podglądu (backend) policzyć podpowiedź i jak ją przekazać do widoku?
2. Jak dziś wybierana, walidowana i zapisywana jest kategoria wiersza i na jaki Budget/kategorię trzeba zmapować historię?
3. Jakie teksty wiersza i historii są dostępne i jak są znormalizowane?
4. Jak wyświetlić wstępny wybór i znacznik „✦ podpowiedź” zgodnie z kontraktem UI podglądu?
5. Które decyzje domenowe (ADR, PRD, `CONTEXT.md`) ograniczają funkcję i co trzeba dopisać do słownika?
6. Jakie wzorce testów i wydajności mają zastosowanie?

## Summary

- **Miejsce w backendzie:** `CsvImportController::preview` buduje wiersze i wzbogaca je dwoma detektorami po kolei (`app/Http/Controllers/CsvImportController.php:109-110`), a potem zapisuje je do sesji (`:112-115`). Nowy serwis z tą samą sygnaturą `annotateRows(User, int $accountId, array $rows): array`, wywołany po detektorze par, to gotowy wzorzec. `CsvImportSession` przechowuje wiersze jako nietypowane tablice (`app/Support/CsvImport/CsvImportSession.php:17-24,33-60`), a `showPreview` przekazuje `$import['rows']` bez zmian, więc nowy klucz wiersza dociera do widoku bez zmian w sesji.
- **Cel mapowania to Budget roku daty wiersza, nie „bieżący rok”.** Walidacja zapisu odrzuca kategorię, której `budget->year` różni się od roku `operation_date` (`app/Support/CsvImport/FinancialOperationImportValidator.php:56,67-74`). Wymaga też Budgetu w fazie execution (`:76-82`), zgodnego typu i braku archiwizacji (`:125-137,157-169`). Frontend filtruje opcje tak samo (`resources/js/lib/import-preview-rows.ts:94-108`). Notatka w `change.md` („mapujemy na bieżący rok”) wymaga tej korekty; plik obejmujący grudzień i styczeń mapuje wiersze na dwa różne Budgety.
- **Mapowanie po nazwie i typie ma precedens:** ADR-0016 (lista operacji, `app/Support/OperationsListQuery.php:139-147`) i ADR-0033 (`app/Support/MonthPlanProposal.php:171-187`, z regułą niejednoznaczności). `CONTEXT.md:36` mówi wprost: „Matching several years by the same name and type is a query over those records, not one Category.” Nazwy kategorii nie są unikalne: walidacja to tylko `required|string|max:255` (`app/Concerns/CategoryValidationRules.php:22`) i baza nie ma indeksu unique (`database/migrations/2026_09_07_180001_create_categories_table.php:29-30`), więc duplikat nazwy w Budgecie docelowym musi dawać brak podpowiedzi.
- **Frontend:** dziś każdy wiersz startuje z `category_id: ''` (`resources/js/lib/import-preview-rows.ts:55-92`) i nic nie przychodzi z propsów. Zmiana typu czyści kategorię (`resources/js/components/ImportPreviewRow.vue:218-222`). Zapis jest wysyłany polami formularza: `CategorySelect` ma ukryty `<input>`, więc wstępne `category_id` w `initialSelection` trafi do `rows[i][category_id]` bez zmian w submitcie.
- **Najbliższe zastrzeżenia domenowe** dotyczą innych podpowiedzi, nie kategorii: ADR-0015 / FR-TRX-16 (żadnej podpowiedzi zwrotu i żadnego typu zgadywanego z kwoty), ADR-0010 („no Opis preselect or badge” dla transferu własnego), ADR-0011 (każdy wiersz startuje odznaczony). Podpowiedź kategorii jest z nimi zgodna, jeśli nie zmienia typu ani zaznaczenia wiersza. PRD (`.docs/prd.md:258`, FR-IMP-11) mówi „Kategorię … użytkownik wskazuje w podglądzie”, a lista „Poza zakresem” zawiera „funkcje AI” (`:514`) i „tożsamość Category ponad latami” (`:491`). Funkcji nie ma w PRD ani w roadmapie, więc potrzebny jest nowy wpis FR-IMP oraz ADR lub hasło w słowniku, które odróżni ją od tych pozycji.
- **Prototyp nie jest gotową specyfikacją zapytania:** brakuje filtra `user_id` (`prototype/eval_suggest.php:11-16`), a dane są per użytkownik i bez global scopes. Głosuje po nazwie kategorii, nie po parze nazwa+typ (`:12,107`). Uczy się tylko z wierszy z `raw_statement_description IS NOT NULL` (`:14`). Wyników pomiaru z `change.md` (79% / 95% / 99% oraz 78% / 92%) nie odtwarzałem w tym badaniu.

## Detailed Findings

### 1. Potok podglądu i punkt wstrzyknięcia (backend)

- Trasy: `routes/web.php:68-71`, czyli `GET operations/import`, `POST operations/import/preview` (`preview`), `GET operations/import/preview` (`showPreview`) i `POST operations/import` (`store`).
- `preview()` (`CsvImportController.php:48-118`): parsuje plik, pomija wiersze, które nie wyglądają na operację (`:84-92`), ustawia `index` i opis zastępczy (`:89-90`), buduje wiersz bazowy (`:94-107`), wzbogaca go (`:109-110`) i zapisuje do sesji (`:112-115`). Detektory są wstrzykiwane jako parametry metody (`:53-54`).
- Wzorzec detektorów:
  - `ImportDuplicateDetector::annotateRows` (`app/Support/CsvImport/ImportDuplicateDetector.php:272-316`): jedno zapytanie na tabelę dla całej partii (`:349-385`), potem `[...$row, 'import_duplicate' => …]`.
  - `ImportPairCandidateDetector::annotateRows` (`app/Support/CsvImport/ImportPairCandidateDetector.php:42-100`): filtr kwalifikujących się wierszy (`:254-269`), zapytania wsadowe (`:299-356`), dopasowanie w pamięci (`:121-167`).
  - Każdy dopisuje klucz do każdego wiersza, także tego niekwalifikującego się (wartość domyślna). Wywołanie po detektorze par daje dostęp do `import_duplicate` i `pair_candidates` danego wiersza.
- Wiersz w sesji i w propsie `rows` ma pola: `index`, `operation_date`, `description`, `counterparty`, `raw_statement_description`, `amount_minor_units` (ze znakiem), `valid`, `invalid_reason` (`CsvImportController.php:97-106`), `import_duplicate`, `import_pair_candidate` i `pair_candidates`. Nie ma w nim `type` ani `category_id`; typ domyślny ustala frontend ze znaku kwoty (`import-preview-rows.ts:45-53`).
- `showPreview()` (`CsvImportController.php:120-150`) przekazuje `rows` z sesji oraz `incomeCategories` i `expenseCategories`. Obie listy obejmują kategorie z Budgetów w fazie Execution, bez zarchiwizowanych, posortowane i opisane polami `{id, name, icon, color, budget_year}`; `is_paced` mają tylko wydatki (`:523-569`). Kod nie używa `SelectedBudgetYear`.
- Kontrakt propsów: PHP i ręczny typ TS zmienia się w jednym commicie i potwierdza `assertInertia` w teście Feature (`AGENTS.md`, sekcja „Inertia props contract”). Typ wiersza po stronie TS to `ImportRow` (`import-preview-rows.ts:9-21`).

### 2. Kategoria wiersza: dziś i cel mapowania

- Kategoria jest wymagana dla typów `income`, `expense` i `refund`. Kontroler odrzuca jej brak (`CsvImportController.php:306-310`), choć sama reguła requestu to `nullable|integer` (`app/Http/Requests/StoreCsvImportRequest.php:26`).
- `own_transfer` nie ma kategorii (`CsvImportController.php:239-250`). `capital_movement` ma tylko opcjonalne `interest_category_id` (kategoria dochodu; `app/Support/CsvImport/CapitalMovementInterestImportValidator.php:39-73`).
- `refund` używa kategorii wydatku (`FinancialOperationImportValidator.php:30`) i zapisuje się jako `expense` z ujemną kwotą (`app/Actions/ImportFinancialOperations.php:249-253`). Typy w bazie to tylko `income` i `expense` (`app/Enums/FinancialOperationType.php:7-8`).
- Reguły celu, wszystkie w `FinancialOperationImportValidator.php`: kategoria należy do użytkownika (`:58`), rok Budgetu jest równy rokowi daty wiersza (`:67-74`), Budget jest w fazie execution (`:76-82`), typ się zgadza i kategoria nie jest zarchiwizowana (`:125-137,157-169`).
- `CreateBudget::copyCategories` (`app/Actions/CreateBudget.php:29-51`) pomija zarchiwizowane (`:33`) i tworzy nowe rekordy bez identyfikatora źródła. ADR-0014: „same names across years are a coincidence of copy”.
- Precedensy dopasowania po nazwie i typie:
  - ADR-0016 i `OperationsListQuery.php:139-147`: dokładne `name = ? AND type = ?`, z rozróżnieniem wielkości liter.
  - ADR-0033 i `MonthPlanProposal::matchPreviousCategory` (`app/Support/MonthPlanProposal.php:171-187`): dokładnie jedno aktywne dopasowanie wygrywa; przy braku aktywnych jedno zarchiwizowane; w pozostałych przypadkach `null`. Dla podpowiedzi zarchiwizowany cel jest wykluczony (opcje i walidacja go odrzucają), więc ma zastosowanie tylko gałąź „dokładnie jedno aktywne”.
- Zmiana nazwy jest dozwolona zawsze (`app/Http/Controllers/CategoryController.php:53-54,66`). Zgodnie z ADR-0016 rozdziela to historię. Typ można zmienić tylko w szkicu (`app/Http/Requests/UpdateCategoryRequest.php:74-76`).
- Wiersz bez `operation_date` nie ma listy kategorii (`import-preview-rows.ts:99-101`), więc nie ma też podpowiedzi.

### 3. Teksty wiersza i historii

- Pola tekstowe pochodzą z `app/Support/CsvImport/ImportRowFields.php`:
  - `raw_statement_description` to migawka pliku, tylko przycięta (`:22-31,40-66,75-95,137-149`).
  - `description` i `counterparty` przechodzą przez `PaymentInstrumentNumberOmitter::omit` i są obcinane do 255 znaków (`:100-114,151-160`).
  - Pusty opis przy obecnym kontrahencie przyjmuje wartość kontrahenta (`:106-108`).
  - mBank nie ma kontrahenta (`app/Support/CsvImport/MbankCsvMappingResolver.php:58-67`).
- `DefaultPaymentInstrumentNumberOmitter` (`app/Support/CsvImport/DefaultPaymentInstrumentNumberOmitter.php:15-32,311-325`) usuwa NRB, IBAN i PAN (także zamaskowany) oraz scala białe znaki. Nie zmienia wielkości liter i nie usuwa słów pospolitych. Już zapisane opisy z Importu przeszły backfill (spec `.docs/specs/import-omit-payment-instrument-numbers.md:55`), więc historia i podgląd mają ten sam oczyszczony tekst.
- Commit `fa72c61` dodał `ImportRowFields::EMPTY_DESCRIPTION_FALLBACK = 'Brak opisu'` (`ImportRowFields.php:9-13`), stosowany w `CsvImportController.php:90`. Takie wiersze mają `raw_statement_description = null` (test `tests/Feature/CsvImportTest.php:287-322`). Wynikają z tego dwie rzeczy:
  1. Przy filtrze prototypu `raw_statement_description IS NOT NULL` takie operacje nie trafiają do historii.
  2. Wiersz podglądu z opisem „Brak opisu” da tokeny `BRAK` i `OPISU`. Bez wykluczenia tego tekstu może dostać podpowiedź z innych wierszy „Brak opisu”, jeśli te zostały zapisane bez filtra. Wniosek z kodu, nie sprawdzony na danych.
- Schemat `financial_operations` (`database/migrations/2026_09_07_180003_create_financial_operations_table.php`): `user_id` (`:16`), `type` (`:17`), `category_id` NOT NULL (`:19`), `budget_id` (`:20`), `description` NOT NULL (`:23`), `counterparty` i `raw_statement_description` nullable (`:24-25`). Indeksy: `(user_id, operation_date)` (`:32`) i `category_id` (`:34`). Nie ma indeksu tekstowego ani trigramowego.

### 4. Frontend: wstępny wybór i znacznik

- Strona to `resources/js/pages/OperationsImportPreview.vue`, a wiersz to `resources/js/components/ImportPreviewRow.vue`. Stan to `selections = ref(rows → initialSelection(row))` (`OperationsImportPreview.vue:77-79`).
- `initialSelection` i `emptySelection` (`import-preview-rows.ts:55-92`) zawsze ustawiają `category_id: ''`. Każdy wiersz startuje z `selected: false`, co jest zgodne z ADR-0011.
- Unikalny kandydat pary ustawia typ `own_transfer` (`import-preview-rows.ts:72-84`). Taki wiersz nie bierze kategorii, więc nie ma podpowiedzi.
- `CategorySelect` (`resources/js/components/CategorySelect.vue`) jest użyty w wierszu w `ImportPreviewRow.vue:525-542`. Etykietę wyszukuje przez `categories.find(...)` (`CategorySelect.vue:43-45`), więc podpowiedziane `id` musi być na liście przefiltrowanej typem i rokiem wiersza, inaczej chip się nie wyrenderuje.
- Zmiana typu czyści `category_id` (`ImportPreviewRow.vue:218-222`). Gdy użytkownik przełączy `expense` na `refund` (lista wydatków, `import-preview-rows.ts:105`) albo `income` na `refund`, podpowiedź znika.
- Zablokowanie zapisu: `rowSelectionValid` wymaga `category_id !== ''` dla FO (`import-preview-rows.ts:222-244`). Wiersz z podpowiedzią staje się więc kompletny od razu po zaznaczeniu.
- Kontrakt UI podglądu (`.ai/rules/js.md`, sekcja „Import preview…”, oraz `.docs/design-system.md`):
  - Wiersz używa nazwanych klas `.import-preview-row` z modyfikatorami `-duplicate` i `-pair`. Token `pair` służy tylko do tła kandydata pary.
  - Nowy alert w bloku notatek wymaga nowego rodzaju w `importRowHasNotes` (`import-preview-rows.ts:246-262`), inaczej ginie po cichu. Etykieta przy selekcie tego nie wymaga.
  - `CategorySelect` bez `<Label for>` dostaje `ariaLabel`. Znacznik, który ma być ogłaszany, potrzebuje `aria-describedby` albo wejścia do nazwy dostępnej.
  - Bez literałów kolorów i dowolnych px/rem; pliki widoku są na liście `npm run check:ui` (`scripts/check-ui-literals.mjs:52-57`). Pigułki idą przez `Badge`; jego warianty to default, secondary, destructive, outline, warning i destructive-soft (`resources/js/components/ui/badge/index.ts`), bez wariantu „info” czy „suggestion”.
  - Nowy stan trafia do kitchen sinka `/dev/import-preview` (`resources/js/lib/import-preview-fixtures.ts`).
- Precedensy znacznika:
  - Odznaki statusu: `Badge variant="outline"` dla „Duplikat importu” i „Kandydat do pary” (`ImportPreviewRow.vue:545-568`; teksty w `lang/pl/ui.php:561-564`).
  - Drobny opis pod kontrolką: `<p class="text-muted-foreground text-xs">` pod selektem lokaty (`ImportPreviewRow.vue:478-480`).
  - Znak „✦” i słowo „podpowiedź” nie występują w kodzie ani w plikach `lang/` (grep całego repo). Teksty wiersza są w `lang/pl/ui.php`, w kluczu `operations`.

### 5. Ograniczenia domenowe i słownik

- **ADR-0015**, spec `reclassify-income-as-expense-refund.md:22,56` i PRD `.docs/prd.md:199,506`: Import nie zgaduje zwrotu, a domyślny typ `+` to Income bez podpowiedzi zwrotu. Podpowiedź musi liczyć się dla bieżącego typu wiersza i nie może go zmieniać. Zwroty w historii mają typ `expense`, więc wiersz `income` ich nie zobaczy, o ile historia jest filtrowana typem (prototyp tak robi, `eval_suggest.php:77`).
- **ADR-0010**: „File-one preview chrome stays as today: default type from the signed amount, no Opis preselect or badge.” Dotyczy typu `own_transfer` (mBank „wewnętrzny”), ale jest najbliższym zapisanym sprzeciwem wobec „preselect z Opisu”. ADR tej zmiany powinien to odróżnić.
- **ADR-0011**: wiersze startują odznaczone. Podpowiedź nie może zaznaczać wiersza.
- **ADR-0020 / ADR-0017** (Cash Expectation): Settlement prefilluje kategorię tylko wtedy, gdy wiersz jej nie ma. Agenda i Cash Expectation nie są zaimplementowane (`AGENTS.md`, „Project facts”), więc to przyszły konflikt pierwszeństwa, nie dzisiejszy.
- **ADR-0007**: odcisk duplikatu nie obejmuje kategorii, więc nie ma konfliktu.
- **PRD**: `.docs/prd.md:258` (FR-IMP-11, „Kategorię … użytkownik wskazuje w podglądzie”) oraz punkty „Poza zakresem” `:491` (tożsamość Category ponad latami), `:505` (mapowanie kategorii banku), `:506` (podpowiedź zwrotu) i `:514` (funkcje AI). Funkcja nie występuje w `.docs/prd.md` ani w `context/foundation/{prd,roadmap,shape-notes}.md` (grep; wszystkie trafienia „podpowiedź” w `context/archive/**` to tooltipy UI).
- **Słownik** `CONTEXT.md`: jedna sekcja `## Language` (`:5`), wpisy w formacie `**Term**:` → definicja → `_Avoid_: …`. Hasła nakładek podglądu Importu stoją na końcu: Import duplicate (`:191-193`), Import pair candidate (`:195-197`) i Settlement candidate (`:199-201`). Lista `_Avoid_` przy Settlement candidate zawiera „auto-match, suggested pair, similar transaction” (`:201`). Nowe hasło naturalnie pasuje po Settlement candidate.
- **Zakres danych**: aplikacja jest wieloużytkownikowa z izolacją danych (`CONTEXT.md:8`). W kodzie nie ma global scopes (grep `addGlobalScope`/`ScopedBy` bez wyników). Kod zawęża dane jawnie przez `$user->financialOperations()` (`app/Models/User.php:73-75`) i `$user->budgets()` (`:65-67`). Zapytanie prototypu (`eval_suggest.php:11-16`) nie ma filtra `user_id`.

### 6. Testy i wydajność

- `tests/Feature/CsvImportTest.php` (PHPUnit, `RefreshDatabase`): pomocnik `createImportFixture()` (`:3956-3975`; konto od 2026-01-01, Budget 2026 w execution, jedna kategoria dochodu i jedna wydatku) i `previewCsv()` (`:3977+`). Wzorzec: `travelTo`, `POST preview.store`, `GET preview` z `assertInertia(... where('rows.0.…'))` (np. `:287-322`).
- Testy jednostkowe detektorów: `tests/Unit/ImportPairCandidateDetectorTest.php` (Pest, `app(ImportPairCandidateDetector::class)`). To wzorzec testu nowego serwisu.
- Fabryki: `FinancialOperationFactory` domyślnie tworzy osobny `Budget::factory()` dla kategorii, więc `budget_id` operacji i kategorii nie są spójne bez jawnego ustawienia. `CategoryFactory` ma stany `income()`, `expense()` i `archived()`. `BudgetFactory` ma stan `execution()`.
- Frontend: logika jest w `resources/js/lib/*.ts` z testami `*.test.ts` (`import-preview-rows.test.ts` testuje dziś tylko `importRowHasNotes`). Repo nie ma testów komponentów Vue ani testów przeglądarkowych.
- Wydajność: `phpunit.nfr.xml` uruchamia grupę `nfr` na PostgreSQL. Jedyny plik z grupą `nfr` w `tests/` (grep `Group('nfr')`), `tests/Feature/Nfr/NfrPerf01Test.php`, seeduje 10 000 operacji i sprawdza progi < 2,0 s dla czterech ekranów. Testu wydajności podglądu Importu nie ma. Prototyp porównuje każdy wiersz z całą historią (`eval_suggest.php:76-97`), czyli O(wiersze × historia). Przy 10 000 operacji w historii warto zbudować indeks odwrócony token → wpisy historii. To wniosek, nie pomiar.
- Sloppy (`AGENTS.md`, sekcja „Sloppy”) wykrywa zapytania w pętli, więc historię trzeba ładować jednym zapytaniem na podgląd, jak w detektorach.

## Code References

- `app/Http/Controllers/CsvImportController.php:109-115` – wzbogacanie wierszy i zapis do sesji; tu wchodzi nowy serwis
- `app/Http/Controllers/CsvImportController.php:120-150` – `showPreview`, propsy strony
- `app/Http/Controllers/CsvImportController.php:523-569` – listy opcji kategorii (execution, bez zarchiwizowanych, `budget_year`)
- `app/Http/Controllers/CsvImportController.php:306-310` – wymóg kategorii przy zapisie FO
- `app/Support/CsvImport/ImportPairCandidateDetector.php:42-100` – wzorzec `annotateRows`
- `app/Support/CsvImport/FinancialOperationImportValidator.php:50-82,125-169` – reguły celu (rok daty, execution, typ, archiwizacja)
- `app/Support/CsvImport/CsvImportSession.php:17-60` – sesja z nietypowanymi wierszami
- `app/Support/CsvImport/ImportRowFields.php:9-13,100-114` – `Brak opisu`, oczyszczanie pól
- `app/Support/MonthPlanProposal.php:171-187` – dopasowanie po nazwie i typie z regułą niejednoznaczności
- `app/Support/OperationsListQuery.php:139-147` – filtr po nazwie i typie (ADR-0016)
- `app/Actions/CreateBudget.php:29-51` – kopiowanie kategorii bez źródła
- `resources/js/lib/import-preview-rows.ts:55-108,222-262` – stan wiersza, filtr kategorii, ważność, notatki
- `resources/js/components/ImportPreviewRow.vue:218-222,525-568` – czyszczenie kategorii po zmianie typu, selekt kategorii, odznaki statusu
- `resources/js/components/CategorySelect.vue:43-45` – etykieta z przefiltrowanej listy
- `context/changes/import-category-suggestions/prototype/eval_suggest.php:11-16,76-110` – zapytanie i algorytm prototypu

## Architecture Insights

- Wzbogacanie podglądu to łańcuch serwisów z jedną sygnaturą, zapytaniami wsadowymi i domyślną wartością klucza w każdym wierszu. Wynik jest liczony raz przy `preview`, nie przy każdym `GET`. Podpowiedź pasuje do tego wzorca i jest zamrażana w sesji razem z resztą wiersza.
- Kategoria to rekord jednego Budgetu. Przechodzenie między latami rozwiązano tak samo w kodzie dwa razy (ADR-0016, `OperationsListQuery.php:139-147`; ADR-0033, `MonthPlanProposal.php:171-187`), raz w PRD (`.docs/prd.md:280`, FR-OPS-03) i raz w dokumentacji funkcji jeszcze niezaimplementowanej (ADR-0030, Year Close: „same Category name and type with a User override”). Za każdym razem jest to zapytanie po nazwie i typie, bez tożsamości ponad latami. Podpowiedź może uczyć się z historii dowolnych lat po nazwie i typie, a cel wybierać w Budgecie roku daty wiersza.
- Walidacja zapisu sprawdza kategorię niezależnie od podglądu, więc błędna podpowiedź nie może zapisać niepoprawnej kategorii. W najgorszym razie wiersz zostanie odrzucony z błędem pola.

## Historical Context (from prior changes)

- `context/archive/2026-11-04-import-preview-ui-contract/research.md` i `plan.md` – kontrakt UI podglądu (nazwane klasy, token `pair`, `importRowHasNotes`, `ariaLabel`, macierz 7 stanów w kitchen sinku). Reguły są utrwalone w `.ai/rules/js.md` i nadal obowiązują na HEAD `557d2ba`.
- `context/archive/**` – brak wcześniejszych decyzji o podpowiedzi kategorii (grep „podpowied”, „sugest”, „suggest”; trafienia dotyczą tooltipów i tokenu kandydata pary).
- `change.md` tej zmiany, twierdzenia w kolejności:
  - „Kategorie należą do roku budżetu (`CreateBudget::copyCategories` nie zapisuje źródła)”: **potwierdzone** (`CreateBudget.php:29-51`).
  - „historię mapujemy na bieżący rok po nazwie i typie”: **częściowo**. Mapowanie po nazwie i typie ma precedens, ale celem jest rok daty wiersza (`FinancialOperationImportValidator.php:67-74`), nie bieżący rok.
  - „Brak kategorii lub zarchiwizowana: brak podpowiedzi”: **zgodne** z regułami celu (`:125-137,157-169`). Do tego dochodzi brak Budgetu w execution i niejednoznaczna nazwa.
  - „Potrzebne nowe hasło w `CONTEXT.md`, bo przy Settlement candidate jest „_Avoid_: … similar transaction””: **potwierdzone** (`CONTEXT.md:201`).
  - „Liczenie w PHP na operacjach w Postgresie”, „bez Typesense”: zgodne ze stosem; repo nie ma indeksu tekstowego (`…create_financial_operations_table.php:32-36`).
  - Wyniki pomiaru: **nieodtworzone** w tym badaniu.

## Related Research

- `context/archive/2026-11-04-import-preview-ui-contract/research.md`

## Open Questions

Wybory dla `/10x-plan` (decyzje produktowe lub techniczne, nie luki w dowodach):

1. **Źródło historii:** tylko operacje z Importu (`raw_statement_description IS NOT NULL`, jak w prototypie) czy także wpisy ręczne? Ręczne nie mają szumu płatności kartą, ale mają inny styl opisu.
2. **Podpowiedź per typ:** jedna (dla domyślnego typu) czy osobna dla `income` i `expense`/`refund`, żeby przełączenie typu (`ImportPreviewRow.vue:218-222`) mogło ją przywrócić? Czy po przełączeniu wracającym do pierwotnego typu podpowiedź ma wrócić?
3. **Które wiersze dostają podpowiedź:** niepoprawne (nie da się ich wybrać), Import duplicate (poprawne, odznaczone), wiersze z kandydatem pary (typ `own_transfer` → brak), kwota odsetek przy `capital_movement` (`interest_category_id`)?
4. **„Brak opisu”:** wykluczyć ten tekst z tokenizacji, z historii, czy z obu?
5. **Miejsce znacznika „✦ podpowiedź”:** obok selektu kategorii (wzorzec `text-muted-foreground text-xs`, `ImportPreviewRow.vue:478-480`) czy jako `Badge` w kolumnie statusu (`:545-568`)? Czy znika po ręcznej zmianie kategorii? Jak go ogłosić czytnikom ekranu (`aria-describedby`)?
6. **Próg jakości w testach:** wyniki z `change.md` pochodzą z lokalnych danych, których CI nie ma. Test progu wymaga stałego zestawu danych (fixture) albo zostaje jako ręczny pomiar prototypem.
7. **Dokumentacja:** nowe FR-IMP w `.docs/prd.md` (obok FR-IMP-11) i ADR, który odróżni funkcję od ADR-0010, ADR-0015 i punktu „funkcje AI” (`.docs/prd.md:514`). Do tego hasło w `CONTEXT.md` po Settlement candidate: nie rekord, nie tożsamość Category, zapytanie po nazwie i typie w Budgecie roku daty wiersza, nie zmienia typu ani zaznaczenia.
8. **Wydajność:** czy dodać test NFR podglądu z historią rzędu 10 000 operacji (wzorzec `NfrPerf01Test.php`) i indeks odwrócony zamiast pełnego przeglądu z prototypu?
