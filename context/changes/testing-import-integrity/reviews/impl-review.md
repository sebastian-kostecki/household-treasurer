<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Integralność Importu i bramka trafności podpowiedzi

- **Plan**: context/changes/testing-import-integrity/plan.md
- **Scope**: Full plan (fazy z ukończonymi krokami automatycznymi; faza 5 czeka na pliki od użytkownika)
- **Reviewed phases**: 1, 2, 3, 4, 6
- **Date**: 2026-11-17
- **Verdict**: REJECTED
- **Findings**: 1 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | FAIL |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Weryfikacja kryteriów (2026-11-17, ponownie uruchomione w przeglądzie)

- `composer ci:check` na PostgreSQL w kontenerze (`testing_testing_import_integrity`): exit 0, 1834 passed, 2 skipped (rejestracja Fortify, sprzed zmiany). Benchmark 11,4 s.
- `vendor/bin/sloppy ci` (sail-8.5/app): passed, 3 × medium, wszystkie w kodzie SheetLoad sprzed zmiany.
- `pint --test` na 8 zmienionych plikach PHP: passed.
- `gh issue view 271/272`: oba otwarte, `needs-triage`.
- `grep S-16 test-plan.md`: tylko #5, #6 i etap 3 (wyłączone planem).
- Celowe zepsucia, każde cofnięte `git checkout`:
  - bez `DB::transaction` → 2 failed (Nowy Rok, rozbita para);
  - mBank po pozycji → 2 failed (przestawione kolumny: podgląd i pełny przebieg);
  - `NEIGHBOURS = 1` → benchmark failed z licznikami i listą wierszy;
  - bez `distinct` → 1 failed (powtórzony indeks); 2.1 nie dało się sprawdzić z historii, bo test i poprawka są w jednym commicie `dc2fcea`.
- Ręczne 3.4, 4.7, 4.8, 6.4: nieodhaczone, oczekują.

## Findings

### F1 — Nazwy kategorii z danymi osobowymi w fixture publicznego repo

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/CsvImport/ImportCategorySuggestionReference.php:199, tests/Fixtures/suggestions/reference-2026.json (`categories`)
- **Detail**: Repo `sebastian-kostecki/household-treasurer` jest publiczne (`isPrivate:false`). Generator kopiuje nazwy kategorii dosłownie: „Wynagrodzenie Sebastian”, „Wynagrodzenie Janeczka”, „800+”, „Przedszkole”, „Dzieci”, „Leczenie prywatne”, „Apteka”, „Rekolekcje”, „Kredyt”. Razem z dokładną datą i `category_key` każdego wiersza i autorem commitów daje to dziennik zachowań rozpoznawalnej rodziny: daty wypłat obu osób, wizyt lekarskich, apteki i rekolekcji (zdrowie i religia to dane szczególnej kategorii). Pomiar nazw nie czyta (`ImportCategorySuggestionAccuracy` używa id, typu, archiwum i roku), a kontrakt planu kluczuje kategorie po id; nazwa została tylko dla czytelności i jej dopuszczalność plan zostawił ręcznemu krokowi 4.7. Commit `a93cc49` nie jest jeszcze na żadnej zdalnej gałęzi (`git branch -r --contains a93cc49` pusty), więc poprawka nie wymaga przepisywania opublikowanej historii.
- **Fix**: Generator zapisuje `name` jako „Kategoria {key}”, test generatora sprawdza, że żadna nazwa kategorii ze snapshotu nie trafia do wyjścia, zestaw jest wygenerowany ponownie, a poprawka wchodzi jako fixup do `a93cc49` (rebase lokalny przed pierwszym pushem), żeby nazwy nie zostały w historii gałęzi.
  - Strength: Benchmark nie czyta nazw, więc plik bazowy i bramka zostają bez zmian; nazwy „Kategoria N” są unikalne w `(user_id, type, name_key)`.
  - Tradeoff: Lista różniących się wierszy w czerwonym benchmarku pokaże „Kategoria 20” zamiast nazwy; rebase przepisuje 3 lokalne commity (p4, p6, zapis SHA), więc SHA w Progress trzeba poprawić.
  - Confidence: HIGH — nazwy sprawdzone `jq`, publiczność repo sprawdzona `gh`, brak pushu sprawdzony gitem.
  - Blind spot: Nie sprawdzono, czy `research.md` i treść issue #271/#272 (już publiczne) nie zawierają fragmentów prawdziwego tekstu z banku.
- **Decision**: FIXED — nazwy „Kategoria <klucz>”, a klucze w kolejności HMAC id z sekretem z treści snapshotu (podczas poprawki wyszło, że klucze równe id z produkcji da się odczytać z publicznego `database/sheet-load/mapping.php`); test generatora na brak nazw i na klucze niezależne od id; poprawka wcielona do commita fazy 4 przed pierwszym pushem (`0ff90fc`), w historii fixture nie ma prawdziwej nazwy.

### F2 — Pseudonimy zachowują strukturę tekstu, a daty pozwalają łączyć wiersze ze znanymi zdarzeniami

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Safety & Quality
- **Location**: app/Support/CsvImport/ImportCategorySuggestionReference.php:213-245
- **Detail**: Mapowanie to licznik base-26 w kolejności pierwszego wystąpienia, nie hash, więc atak słownikowy na pojedynczy token nie działa. Wiersz zachowuje jednak kolejność tokenów oryginału: szablon mBank `ABP ABQ ABK _ _ ABN ABO` występuje w 379 wierszach, a jego tekst jawny jest w tym samym repo (`tests/Fixtures/mbank-basic.csv`), więc wolne pozycje izolują sprzedawcę, miasto, osobę i ulicę. Ktoś, kto zna jedną prawdziwą transakcję (pracodawca, przelew od krewnego), znajdzie ją po dacie, typie i kategorii i odczyta jej tokeny we wszystkich wierszach. Docblock (`:213`) twierdzi, że „słowa nic nie mówią o oryginałach”. Ryzyka resztkowego nie opisano w planie.
- **Fix A ⭐ Recommended**: Generator sortuje tokeny w wierszu po pseudonimie (numeracja w kolejności pierwszego wystąpienia zostaje — kolejność alfabetyczna oryginałów zdradzałaby litery), a plan albo ADR zapisuje świadomą akceptację ryzyka resztkowego (daty, kategorie i liczba tokenów w wierszu zostają)
  - Strength: `ImportCategorySuggestionModel::tokens` zwraca zbiór (`$tokens[$word] = true`), więc kolejność nie zmienia rankingu, a `assertSameRankings` generatora i tak to sprawdzi wiersz po wierszu.
  - Tradeoff: Plik bazowy trzeba wygenerować ponownie; zostaje ryzyko linkowania po dacie i kategorii.
  - Confidence: MED — usuwa szablon i kolejność, ale nie usuwa łączenia przez znane zdarzenie.
  - Blind spot: Nie oceniono, ile da się odczytać z samej liczby i częstości tokenów po sortowaniu.
- **Fix B**: Zestaw referencyjny poza publicznym repo (prywatny artefakt pobierany w CI sekretem), a benchmark w CI pada głośno, gdy go brak
  - Strength: Nic z historii gospodarstwa nie jest publiczne.
  - Tradeoff: Nowa infrastruktura (sekret, pobieranie), lokalne uruchomienie wymaga pliku; sprzeczne z decyzją planu „poziom bazowy w repo”.
  - Confidence: MED — działa, ale zmienia decyzję z planowania.
  - Blind spot: Nie sprawdzono, czy CI ma już mechanizm prywatnych artefaktów.
- **Decision**: FIXED via Fix A — słowa wiersza posortowane, numeracja pseudonimów bez zmian (alfabetyczna zdradzałaby litery); generator porównuje ranking z precyzją modelu (9 miejsc), bo inna kolejność dodawania wag przesuwa wynik o ostatni bit; plik bazowy po regeneracji bajtowo identyczny; ryzyko resztkowe zapisane w planie („Addendum z przeglądu implementacji”).

### F3 — Filtr historii skopiowany zamiast współdzielony

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Support/CsvImport/ImportCategorySuggester.php:70-73, :108-111
- **Detail**: Kontrakt fazy 4 §1: „Filtr i budowa tekstu historii są współdzielone z `ImportCategorySuggester`, nie skopiowane”. Tekst jest współdzielony (`historyEntry`), ale `learnsFrom()` to ręczna kopia warunków SQL z `loadHistory` (`whereNotNull(raw_statement_description)`, `description <> fallback`), z której `loadHistory` nie korzysta; docblock sam nazywa ją „regułą za klauzulami where”. Zmiana zapytania nie przeniesie się do generatora i nic tego nie złapie. Do tego generator sam mapuje typ (`Reference.php:136`: wszystko poza `income` to `expense`) i nie odtwarza złączenia z `budget_lines`. Drobna asymetria: SQL `<>` odrzuca `NULL` w `description`, a `learnsFrom(null)` zwraca `true`.
- **Fix A ⭐ Recommended**: Test zgodności: snapshot testowy wczytany przez `RestoreUserData` do bazy, potem zbiór (data, typ, kategoria, tekst) z `loadHistory` równy wierszom generatora sprzed pseudonimizacji
  - Strength: Łapie każdy rozjazd filtra, tekstu, typu i złączenia, niezależnie od tego, gdzie powstanie.
  - Tradeoff: Test zależy od restore snapshotu i jest wolniejszy od obecnych testów generatora.
  - Confidence: MED — restore istnieje (`app/Actions/RestoreUserData.php`), nie sprawdzono, czy przyjmuje snapshot zbudowany w teście bez zmian.
  - Blind spot: Zgodność z wersją snapshotu ≥ 10 w restore.
- **Fix B**: Dopisek w planie, że filtr jest zduplikowany świadomie (SQL kontra tablica), z odnośnikiem w docblocku `loadHistory` do `learnsFrom`
  - Strength: Zero kodu.
  - Tradeoff: Rozjazd dalej niczym niechroniony.
  - Confidence: HIGH — to tylko dokumentacja.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A (wariant) — test zgodności robi backup prawdziwego użytkownika przez `BackupUserData` i porównuje wiersze generatora z `loadHistory` (czerwony po usunięciu warunku opisu zastępczego z `learnsFrom`); `learnsFrom` odrzuca NULL jak SQL `<>`; docblocki obu metod odsyłają do siebie i do testu.

### F4 — Komenda nie chroni pliku wejściowego, testy błędów piszą w domyślną ścieżkę fixture

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Console/Commands/ImportSuggestionReferenceCommand.php:59-61, tests/Feature/ImportSuggestionReferenceCommandTest.php:165, :176
- **Detail**: `--output` równe ścieżce snapshotu nadpisze jedyną kopię zapasową zestawem referencyjnym; siostrzana `ImportSuggestionAccuracyCommand` ma do tego `optionError()` z `isSameFile()` (`:76-101`). Testy „stara wersja” i „rok nie jest liczbą” nie podają `--output`, więc regresja walidacji nadpisałaby commitowany `tests/Fixtures/suggestions/reference-2026.json` 3-wierszowym zestawem testowym. Nowa komenda waliduje `--year` dopiero po wczytaniu całego snapshotu, a siostrzana przed I/O.
- **Fix**: Przenieść `isSameFile` do wspólnego traitu `ReadsAndWritesJsonFiles` i użyć go w nowej komendzie przed odczytem; w każdym teście błędu podać `--output => referencePath(...)` i sprawdzić, że plik nie powstał.
- **Decision**: FIXED — `isSameFile` i `filledOption` we wspólnym `ReadsAndWritesJsonFiles`; komenda waliduje `--year` i `--output` przed odczytem i odmawia nadpisania snapshotu; testy błędów podają `--output` i sprawdzają, że plik nie powstał. Przy okazji: `filledOption` przyjmował tylko stringi, więc `--year` podane w teście jako liczba było ignorowane i zastępowane najnowszym rokiem; teraz przyjmuje każdą wartość skalarną.

### F5 — Drobne odchylenia od planu niezapisane w planie

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/ImportIntegrityTest.php:501-518, tests/Feature/ImportSuggestionAccuracyBenchmarkTest.php:31-32, :69, app/Support/CsvImport/ImportCategorySuggestionReference.php:156-171, context/changes/testing-import-integrity/change.md
- **Detail**: (1) Wariant BNP „przestawione i dodane kolumny” to tylko `insertNewColumnBefore('A')`: przesunięcie bez przestawienia. (2) `key` kategorii to numer w kolejności snapshotu, nie id kategorii listy (spójne z „bez id z produkcji”, ale różne od kontraktu). (3) Benchmark zapisuje w `raw_statement_description` klucz wiersza, nie pseudonimizowany tekst (opisane w docblocku), a 13 pustych tekstów dostaje `description = '-'` (nieopisane). (4) Numery issue trafiły do nowej sekcji „Issue (faza 3)” zamiast do Notes. (5) Progress 3.3 ma SHA dwa razy.
- **Fix**: Dopisać w planie krótki addendum z punktami 2–3, dodać w teście BNP prawdziwe przestawienie dwóch kolumn i usunąć zdublowane SHA w 3.3.
- **Decision**: FIXED — addendum w planie (klucz kategorii, `raw_statement_description` = klucz, `description = '-'`, miejsce numerów issue), wariant BNP z przestawioną kolumną `Kwota`, zdublowane SHA w Progress 3.3 usunięte, SHA faz 4 i 6 poprawione po przepisaniu historii.

### F6 — §6.2 planu testów obiecuje więcej, niż robią testy

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/test-plan.md:142-143
- **Detail**: „porównuje liczbę i krotki operacji” — migawka trzyma liczności, salda i wykonanie, nie krotki operacji. „ręczne krotki (data, kwota, opis)” — testy mają krotki (data, kwota w groszach), bez opisu. Cookbook poprowadzi następny test w złą stronę.
- **Fix**: Zmienić na „porównuje liczności, salda kont i wykonanie pozycji” oraz „ręczne krotki (data, kwota w groszach)”.
- **Decision**: FIXED — §6.2 opisuje liczności, salda kont i lokat, wykonanie pozycji i krotki (data, kwota w groszach); opis zestawu referencyjnego w §6 uzupełniony o sortowanie słów i nazwy „Kategoria N”.

### F7 — Komenda: drobne ścieżki błędu bez sygnału albo bez przyczyny

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/ImportSuggestionReferenceCommand.php:39, :50, :53-56; app/Support/CsvImport/ImportCategorySuggestionReference.php:136, :251-261
- **Detail**: `hash_file(...) ?: ''` zapisuje `"sha256": ""` jako sukces. `latestYear()` działa przed sprawdzeniem wersji w `build()`, więc stary snapshot bez `--year` może paść ErrorException zamiast komunikatu o wersji. Nieznany typ operacji po cichu staje się `expense`. Niezgodność rankingu (błąd logiki, nie danych) jest `InvalidArgumentException` łapanym razem z błędami danych i wypisywanym bez kontekstu; tej ścieżki nie pokrywa żaden test (mapowanie jest różnowartościowe, więc dziś nie da się jej wywołać).
- **Fix**: `hash_file` false → błąd i FAILURE; walidacja wersji przed `latestYear`; nieznany typ → wyjątek; osobny wyjątek dla błędów danych, a niezgodność rankingu jako `LogicException` z kluczem wiersza.
- **Decision**: FIXED — `ImportSuggestionReferenceException` dla błędów danych (łapany przez komendę), niezgodność rankingu jako `LogicException` z oboma rankingami w komunikacie (niełapany); wersja sprawdzana przed `latestYear`; nieznany typ i linia spoza snapshotu to błąd z testem; `hash_file` false kończy się FAILURE. Ścieżka niezgodności rankingu nadal bez testu: mapowanie jest różnowartościowe, więc nie da się jej wywołać.

### F8 — Pliki tymczasowe w teście BNP i zapis bazy bez pokazania spadków

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/ImportIntegrityTest.php:514, tests/Feature/ImportSuggestionAccuracyBenchmarkTest.php:127-135
- **Detail**: `importIntegrityBnpVariant` tworzy `tempnam(...)` i `.xlsx` i nigdy ich nie usuwa (4–5 plików na przebieg). `SUGGESTION_BASELINE_WRITE=1` nadpisuje bazę bez wypisania `differences()`, więc obniżenie poziomu widać tylko jako „skipped” i diff w gicie, co jest sprzeczne z anty-wzorcem z planu testów („próg obniżony do bieżącego wyniku po spadku”).
- **Fix**: `unlink` obu plików w `finally`/`afterEach`; przed zapisem bazy wypisać spadki z `differences()` w komunikacie pominięcia.
- **Decision**: FIXED — pliki BNP w `storage/framework/testing/import-integrity`, usuwane w `afterEach`; zapis bazy wypisuje w komunikacie pominięcia wynik `differences()` względem poprzedniej bazy.

## Triage (2026-11-17)

Użytkownik: „napraw według rekomendacji”.

- Fixed: F1, F2 (Fix A), F3 (Fix A, backup zamiast restore), F4, F5, F6, F7, F8 (8)
- Skipped: —
- Accepted: —

Weryfikacja po poprawkach: `composer ci:check` na PostgreSQL w kontenerze i `vendor/bin/sloppy ci` (wynik w commicie z poprawkami).
