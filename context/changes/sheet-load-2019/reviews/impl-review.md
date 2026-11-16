<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Wczytanie roku 2019 ze starego arkusza budżetu

- **Plan**: context/changes/sheet-load-2019/plan.md
- **Scope**: Phases 1–4 of 5 (faza 5, wczytanie na produkcji, jeszcze nie ruszyła)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-16
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 6 observations
- **Triage**: 2026-11-16, all ten fixed at the user's request („napraw samodzielnie według rekomendacji”); F4 partly, its sign-off rows stay with the user

## Verification run (2026-11-16)

- `workbook-fix/verify.php` (w Sail) na `Budżet_2019.przed-korekta.xlsx` → `Budżet_2019.xlsx`: PASS. Różni się 16 komórek, każda z wartością zapisaną równą wynikowi silnika; `H16` = 67286.75, `I16` = 78039.29.
- SHA-256: oryginał `b99e9217…0f6` i poprawka `73fc70cf…4c86` zgodne z planem i `workbook-fix.md`.
- `grep ADR-0102` w ADR-0096: dopisek jest w decyzji 1.
- Testy Sheet Load na PostgreSQL (4 pliki z kryterium 3.1): 84 passed (631 assertions).
- `composer ci:check` na PostgreSQL w Sail: exit 0, 1793 passed, 2 skipped.
- `vendor/bin/sloppy ci` (w obrazie Sail, host ma PHP 8.3): Score 100, exit 0. Są trzy znaleziska MEDIUM: dwa nowe SL204 w `UnloadBudgetSheet.php:82,89` (F8) i stare SL113 w `MonthSheetReader.php`.
- `--dry-run` poprawionego `Budżet_2019.xlsx` z kodu `development` na dev DB:
  - przychody 78 039,29 i wydatki 67 286,75, oba „(zgodne)” z `Podsumowanie!I16`/`H16`, bez sekcji ruchów funduszu;
  - saldo otwarcia −10 752,54, 352 operacje;
  - dwie reszty: `marzec_19!B24` −10,00 i `lipiec_19!D21` −35,00;
  - wiersz „Kategoria „Stypendium” zostanie utworzona (zarchiwizowana).”;
  - podział Rachunków: Prąd 273,38; Internet 608,13 (308,14 z podziału, reszta to wiersze „internet” I–VI); Telefony 1 570,73 (790,63 z podziału).
  - Kod wyjścia 1 bierze się wyłącznie ze sprawdzenia nazw kategorii na dev DB, która nie jest kopią produkcji. Plan to przewiduje.
- `--dry-run` 2020–2025: przychody i wydatki co do grosza takie, jak w README (2020 i 2021 razem z ruchami funduszu).
- Progress: wiersze Manual 1.4, 1.5, 3.4 i 4.4 są nieodhaczone (F5). Żaden wiersz nie jest odhaczony na wyrost. Wiersz 3.2 nie ma sha commitu.
- Poza planem w diffie są dwa pliki. `UnloadBudgetSheetCommand.php` wypisuje listę usuniętych kategorii zamiast jednej, a to wynika ze zbioru definicji. `SheetLoadPlan.php` ma zmieniony tylko docblock. Oba są nieszkodliwe.

## Triage (2026-11-16)

User: „napraw samodzielnie według rekomendacji”. After the fixes:
- `composer ci:check` on PostgreSQL in Sail: exit 0, 1803 passed, 2 skipped. PHPStan: no errors. Pint: passed.
- Sheet Load tests on PostgreSQL: 94 passed. Every new regression test was run against the code without its fix and failed (F1: 3, F5/F10: 3, F6/F7: 3).
- `vendor/bin/sloppy ci`: Score 100, exit 0, 3 MEDIUM (F8).
- `--dry-run` of the real 2019–2025 files: totals, operation counts and opening balances unchanged.
- Outside the plan: `MonthSheetReader.php` and `SheetCells.php` (F8), `PlannedOperation.php` (F9).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Findings

### F1 — Wycofanie dowolnego roku może usunąć „Stypendium” założone przez użytkownika

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/UnloadBudgetSheet.php:77-121 (oraz app/Actions/LoadBudgetSheet.php:121)
- **Detail**: Dla definicji przychodu (`rhythm_start` = null) `isOnlyWhatTheLoadWrote` sprawdza tylko trzy rzeczy: kategoria jest zarchiwizowana, nie ma linii i nie ma wpisów Rytmu. Kategoria przychodów nigdy nie ma Rytmu, więc warunek sprowadza się do „zarchiwizowana kategoria przychodów „Stypendium” bez linii”. Taki stan może mieć też kategoria założona przez użytkownika: utworzona, nigdy nie zaplanowana, zarchiwizowana.
  - `removeNewCategories` przechodzi po wszystkich definicjach bez względu na rok, więc `budget:unload-sheet 1 2023` też ją usunie.
  - Wczytanie 2019 używa istniejącej kategorii o tej nazwie ponownie, także zarchiwizowanej. Wycofanie 2019 zdejmuje potem linie i usuwa kategorię razem z ikoną, kolorem i opisem.

  Testy (`tests/Feature/LoadBudgetSheetTest.php:328-349`) obejmują tylko dwa przypadki: „niezarchiwizowana” i „zarchiwizowana z inną linią”. Plan wymienia te same dwa (`plan.md:427`), więc luka pochodzi z planu. „Kosmetyki” chroni jej konkretny wpis Rytmu. Dziś na produkcji nie ma „Stypendium” (`production-categories.md`), a `--compare` bez `--allow-additions` pokazałby usunięcie.
- **Fix A ⭐ Recommended**: Wczytanie odmawia, gdy istnieje zarchiwizowana kategoria o nazwie i typie definicji bez `rhythm_start` i bez linii. Dodać do tego test „zarchiwizowana, bez linii, założona przez użytkownika” dla wczytania 2019 i wycofania innego roku.
  - Strength: zamyka ścieżkę „ponowne użycie → usunięcie” bez zmiany schematu, którą plan wyklucza. Przypadek z wycofaniem innego roku przestaje być możliwy po wczytaniu, bo po nim „Stypendium” ma linie 2019.
  - Tradeoff: zostaje wąski przypadek: użytkownik zakłada i archiwizuje „Stypendium” bez wczytanego 2019, a potem wycofuje inny rok. Trzeba go opisać w README i w Consequences ADR-0102.
  - Confidence: MED — reguła jest prosta, ale trzeba sprawdzić, czy unikalność `name_key` nie wyklucza już części scenariuszy.
  - Blind spot: nie sprawdziłem, czy UI pozwala założyć i zarchiwizować kategorię przychodów bez żadnej linii.
- **Fix B**: Zapisać, które kategorie utworzyło wczytanie (np. id przy koncie arkusza albo znacznik `sheet_year`), i usuwać tylko je.
  - Strength: usuwa całą klasę błędu, bo usuwanie opiera się na pochodzeniu kategorii, a nie na heurystyce.
  - Tradeoff: zmiana schematu i snapshotu, a plan wyklucza jedno i drugie („What We're NOT Doing”). Do tego migracja przed wczytaniem na produkcji.
  - Confidence: HIGH — to jedyna pełna poprawka.
  - Blind spot: wpływ na `user:backup`/`user:restore` i `app:control-numbers`.
- **Decision**: FIXED via Fix A, plus: the unload considers only the new Categories that had a line in the unloaded Budget (`UnloadBudgetSheet::removeNewCategories`), so unloading another year no longer touches them. The refusal is `SheetMappingCategoryCheck::newCategoryProblems`, used by both `--dry-run` and the load. ADR-0102 decision 6 and README updated. Three new tests failed before the fix.

### F2 — Test pokrycia mapowania nie obejmuje 9 etykiet 2019

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Unit/SheetLoadPlannerTest.php:468-484, tests/Support/SheetWorkbookFactory.php
- **Detail**: Plan (Faza 3 §4) mówi: „Każdą etykietę 2019 sprawdza test pokrycia”. Fixture `'2019'` nie zawiera jednak etykiet: samochód: inne, cinema city, rozrywka: inne, rozrywka - inne, wyjścia, zdrowie i uroda: inne, apteka i kosmetyki, siłownia i z poprzedniego. Wprost sprawdzona jest tylko `siłownia` (`:479`). Pozostałe potwierdza wyłącznie `--dry-run` prawdziwego pliku, który nie należy do zestawu testów.
- **Fix**: Dodać brakujące etykiety do fixture `'2019'` (albo jedną asercję tabelaryczną po `$mapping->expense()/income()` dla całej listy z decyzji 7).
- **Decision**: FIXED: test „the real mapping maps every 2019 label of ADR-0102” (all expense and income labels of decision 7).

### F3 — README: krok 6 nie wymienia „Stypendium”, zdanie o wycofaniu ma błąd gramatyczny

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: README.md:245, README.md:252
- **Detail**: Krok 6 („Dowód „tylko dodano””) wymienia wśród spodziewanych dodań tylko „ewentualnie kategoria „Kosmetyki””. Wczytanie 2019 doda „Stypendium”, a próba z fazy 5 oczekuje „categories +1”. Operator na produkcji czyta właśnie ten krok. Zdanie o wycofaniu mówi „„Kosmetyki” i „Stypendium”, gdy nic jej już nie używa”, a powinno być „ich”/„każdej z nich”.
- **Fix**: Krok 6: „ewentualnie kategorie „Kosmetyki” (2022–2025) i „Stypendium” (2019)”. W zdaniu o wycofaniu: „gdy żadnej z nich nic już nie używa”.
- **Decision**: FIXED: README step 6 („Kosmetyki” 2022–2025, „Stypendium” 2019), the unload sentence and the refusal of an archived „Stypendium” without lines.

### F4 — Wiersze Manual nieodhaczone, mimo to fazy 2–4 ruszyły

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/sheet-load-2019/plan.md:574-575, 595, 607
- **Detail**: Fazę 1 zamyka uwaga „zatrzymaj się na potwierdzenie użytkownika (lista kategorii i akceptacja poprawki), zanim ruszy Faza 2”. Mimo to 1.4 i 1.5 są nieodhaczone, a fazy 2–4 są zacommitowane. Wiersz 1.4 jest faktycznie spełniony: `production-categories.md` podaje 49 „Sport”. Wiersz 1.5 (akceptacja poprawionego `H` i `marzec_19!B3`) nie ma śladu. Pozostałe wiersze: 3.4 (przegląd tabeli kategorii 2019, wyżej w Verification run) i 4.4 (README) czekają. Wiersz 3.2 nie ma sha commitu.
- **Fix**: Przed fazą 5 potwierdzić 1.5, 3.4 i 4.4, odhaczyć 1.4 (sha 592d854) i dopisać sha do 3.2.
- **Decision**: FIXED (in part): 1.4 checked (592d854), 3.2 has its sha (4a12fc8). 1.5, 3.4 and 4.4 are the user's sign-offs and stay unchecked until the user gives them.

### F5 — Wariant C czytnika nie odmawia kwoty w wierszu bez etykiety

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/SheetLoad/MonthSheet2019Reader.php:151-163
- **Detail**: Warianty A/B odmawiają kwoty w wierszu bez etykiety z adresem komórki (`requireNoAmount`, `:106-108`). `newTemplateExpenses` (lipiec–grudzień) pomija wiersze z pustym B i nie patrzy na C/D. Zabłąkana kwota w D w zakresie `D8:D23` nie przepadnie, bo poprawione `Podsumowanie!H` sumuje cały zakres i uzgodnienie odmówi. Odmowa poda jednak tylko różnicę sum, bez adresu komórki, więc kontrola „każda kwota ma etykietę” jest w wariancie C słabsza niż w A/B. Plan wymaga jej jawnie tylko dla A/B.
- **Fix**: W `newTemplateExpenses` wiersz z pustym B przechodzi przez `requireNoAmount` dla C i D. Do tego test odmowy dla lipca.
- **Decision**: FIXED: `newTemplateExpenses` refuses an amount in C/D of a row without a label (`requireNoAmount` with columns). Regression test failed before the fix.

### F6 — Nadpisanie wydatku na wierszu SKIP/FUND daje operacje

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/SheetLoad/SheetLoadPlanner.php:75-79
- **Detail**: Wiersz FUND kończy się `continue` przed nadpisaniami. `applyExpenseOverrides` działa jednak także dla wiersza SKIP: `rowOperation` zwraca null dla reszty, a operacje nadpisań powstają. Dzisiejsze mapowanie nie ma takiego przypadku. Błąd w mapowaniu dałby po cichu częściowe operacje, choć uzgodnienie z `Podsumowanie` prawdopodobnie by go wychwyciło.
- **Fix**: Odmowa z adresem, gdy wiersz z celem SKIP/FUND ma nadpisanie wydatku (jak przy wierszu z listą, `:64-66`).
- **Decision**: FIXED: an expense override on a SKIP row is refused with the address. A FUND row was already refused through the unused override. Test failed before the fix.

### F7 — Literówka w tekstowym celu mapowania przechodzi `--dry-run`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/SheetLoad/SheetMapping.php (targetName), app/Actions/LoadBudgetSheet.php:249
- **Detail**: Teraz, gdy celem może być dowolny klucz `new_categories`, tekstowy cel spoza SKIP/FUND/kluczy (np. `'stypendum'`) daje w `--dry-run` „(brak w mapowaniu)” i kod 0. Dopiero prawdziwe wczytanie pada na niezdefiniowanym indeksie i cofa transakcję. Dane są bezpieczne, ale podgląd nie spełnia swojej roli.
- **Fix**: `SheetMapping` (przy wczytaniu mapowania) odmawia tekstowego celu, który nie jest SKIP, FUND ani kluczem `new_categories`. Do tego test.
- **Decision**: FIXED: `SheetLoadPlanner::assertTargetsDefined` refuses an operation whose target is neither in `categories` nor a `new_categories` key (also an id outside `categories`). Dataset test failed before the fix.

### F8 — Skopiowane pomocnicze metody czytnika i nowe znaleziska Sloppy

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/SheetLoad/MonthSheet2019Reader.php:338-370, app/Actions/UnloadBudgetSheet.php:82,89
- **Detail**: Metody `entry`, `requireLabel` i `compare` powtarzają prywatne metody `MonthSheetReader.php:88-115,280-289`, a `compare` ma inną sygnaturę (lista części zamiast sumy). Osobna klasa to decyzja ADR-0102, ale wspólne odczyty komórek mogą się rozjechać. Sloppy zgłasza dwa nowe SL204 („Query Inside Loop”, MEDIUM) w `removeNewCategories`. Pętla ma dwie iteracje, więc jest to szum, ale nowy.
- **Fix**: Przenieść `entry`/`requireLabel` do `SheetCells`, wspólne dla obu czytników. SL204 zostawić, bo pętla jest ograniczona do definicji z mapowania.
- **Decision**: FIXED: `entry` and `requireLabel` moved to `SheetCells` and used by both readers (`MonthSheetReader` included). Sloppy: SL204 in `UnloadBudgetSheet` down from 2 to 1. A new SL204 in `SheetMappingCategoryCheck::newCategoryProblems` (one `exists()` per new Category of the plan, at most 2) is accepted as bounded.

### F9 — Nieaktualne docblocki i komunikat

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/SheetLoad/PlannedOperation.php:8, app/Support/SheetLoad/SheetLoadPlanner.php:335, :424, app/Support/SheetLoad/SheetMapping.php:118
- **Detail**:
  - `PlannedOperation` mówi „`category` is a Category id or `SheetMapping::NEW_CATEGORY`”, a teraz to dowolny klucz nowej kategorii.
  - Kształt tablicy w PHPDoc `applyOverride` nie ma `type?: 'income'|'expense'`, które zwraca `SheetMapping::overrides()`.
  - `assertTotal` mówi „Analizy” także dla 2019, choć wydruk sum podaje `Podsumowanie!…`.
  - `newCategoryTarget()` wywołuje tylko test; `mapping.php:162` ma literał `'stypendium'`.
- **Fix**: Poprawić oba docblocki i komunikat; `newCategoryTarget()` zostawić (używa go test) albo usunąć razem z asercją.
- **Decision**: FIXED: `PlannedOperation` docblock and the `applyOverride` shape. Left on purpose: the `assertTotal` message, because the command's totals line also calls `Podsumowanie` „Analiza” and the exception carries its address; and `newCategoryTarget()`, which a test uses.

### F10 — Luźne granice list osobistych w czytniku

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/SheetLoad/MonthSheet2019Reader.php:178, :266-291
- **Detail**:
  - W A/B lista osobista kończy się na `str_starts_with($label, 'cele')`, więc pozycja, której nazwa zaczyna się od „cele…”, ucina listę. Ucięte pozycje stają się resztą z ostrzeżeniem, a nie odmową. Wariant C porównuje dokładnie (`'cele do odłożenia'`, `:211`).
  - `bindLists` pozwala przypiąć tę samą listę do dwóch wierszy (np. przy jednoczesnym „osobiste” i „osobiste: sebastian”). Pozycje zapisałyby się wtedy dwa razy, a ujemna reszta by je wyrównała.

  Na prawdziwym pliku żaden z tych przypadków nie zachodzi (dwie oczekiwane reszty).
- **Fix**: Porównywać dokładne etykiety nagłówków („cele: sebastian”/„cele (…)”) i odmawiać, gdy lista wiąże się z więcej niż jednym wierszem.
- **Decision**: FIXED: the A/B personal lists end at the exact header „Cele (odkładane przez Sebastiana)” (`GOALS_HEADER`, checked against the real file), and a list bound to a second row is refused. Two tests failed before the fix.
