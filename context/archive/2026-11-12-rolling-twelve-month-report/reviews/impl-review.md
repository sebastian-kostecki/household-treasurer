<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Ostatnie 12 miesięcy — plan implementacji

- **Plan**: context/changes/rolling-twelve-month-report/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-12
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings (2 fixed), 4 observations (3 fixed, 1 deferred to the user)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Zakres: commity 81fc0e9 (p1), dcc9eb1 (p2), fb9f147 (epilog), 33 pliki. Każdy plik z planu jest w diffie. Poza planem: `app/Support/ReportWindowRows.php` (wiersze `forWindow` wydzielone z `PeriodReport`, rozsądny refaktor) i `.ai/rules/index.md` (wynik `boost:index-rules` po nowym globie `report-window*`). Nic z „What We're NOT Doing” nie powstało.

Kryteria automatyczne uruchomione w przeglądzie (Sail `sail-8.5/app`, Node 24 z nvm; host ma PHP 8.3):

- Okno, odczyt i strona na SQLite: 77 passed (1009 assertions). Na PostgreSQL (`testing_rolling_twelve_month_report`): 77 passed.
- NFR (`--group=nfr`, PostgreSQL): 2 passed.
- `npm run test`: 55 plików, 623 testy passed. `npm run check:ui`: czysto.
- `ReportsHardcodedUiTest`, `UiTranslationKeysTest`, `ReportsKitchenSinkTest`: 6 passed.
- `npm run build` (shim PHP w Sail) i `npm run types:check`: ok.
- `composer ci:check` na PostgreSQL: 1485 passed, 2 skipped. `vendor/bin/sloppy ci` w Sail (nie na hoście): 100/100, 0 findings.
- `npx playwright test tests/e2e/reports.spec.ts`: 8 passed, 3 failed. Oba nowe testy okna przechodzą. Trzy stare testy padają na danych współdzielonej bazy `household_treasurer_e2e` (zob. F6).

## Findings

### F1 — Znacznik archiwum okna może przeczyć kategorii listy

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/ReportWindowRows.php:53
- **Detail**: Wiersz bierze `line` z najnowszego pokrywającego Budżetu, który *ma* linię tej kategorii. Znacznik czyta potem `$row['line']->isArchived()` (`PeriodReport.php:266`). `ArchiveCategory` (`app/Actions/ArchiveCategory.php:25-39`) archiwizuje tylko linię w Budżecie w realizacji bieżącego roku, usuwa linie draftów, a linie starszych Budżetów zostawia. Przykład: kategoria „Wesele” ma operacje w listopadzie 2025, a w Budżecie 2026 nie ma linii, bo nie dołączyła albo w styczniu 2026 Budżet był draftem. Użytkownik archiwizuje ją w 2026 i linia 2025 zostaje aktywna. W oknie XI 2025 – X 2026 wiersz jest więc pokazany jako aktywny, choć kategoria listy jest zarchiwizowana i nie istnieje w najnowszym Budżecie okna. Kod robi dokładnie to, co mówią plan i ADR-0081, więc luka jest w regule, nie w implementacji. Test „archived mark follows the newest covering Budget…” pokrywa tylko kategorie z liniami w obu Budżetach.
- **Fix A ⭐ Recommended**: Znacznik bierze się z linii najnowszego *pokrywającego* Budżetu. Gdy ta linia nie istnieje, wiersz dostaje `line: null`, a `categoryEntry` użyje już istniejącego `$category->isArchived()`. Zmiana idzie przez ADR-0081 i słownik (AGENTS.md), z przypadkiem testowym „kategoria tylko w starszym Budżecie, zarchiwizowana na liście”.
  - Strength: Używa gałęzi `line === null`, która już jest w `PeriodReport::categoryEntry`. Zachowuje regułę „najnowszy Budżet decyduje” i usuwa sprzeczność z listą kategorii.
  - Tradeoff: Zmiana reguły glosariusza i ADR, kilka linii w `ReportWindowRows` plus test.
  - Confidence: HIGH — ścieżka `ArchiveCategory` i fallback w `categoryEntry` przeczytane w kodzie.
  - Blind spot: Nie sprawdzone, czy `line: null` nie zmienia czegoś poza `is_archived` w wierszu okna (np. sortowania w `ReportCategoryTree`).
- **Fix B**: Zostawić regułę i dopisać w ADR-0081, że okno pokazuje stan linii z ostatniego roku, w którym kategoria była w Budżecie, tak jak raport tamtego roku.
  - Strength: Zero zmian kodu, spójne z raportem roku 2025.
  - Tradeoff: Okno „dziś” pokazuje aktywną kategorię, której nie da się już wybrać.
  - Confidence: MED — to świadomy wybór, ale prawdopodobnie nie ten, który zamierzał plan.
  - Blind spot: Nie wiemy, jak często ten układ wystąpi na produkcji (od 1.01.2027).
- **Decision**: FIXED — Fix A. ADR-0081 (akapit „Archived mark”), słownik (:151) i docblocki `PeriodReport`/`ReportWindowRows` mówią: linia kategorii w najnowszym pokrywającym Budżecie, a bez niej kategoria listy. `ReportWindowRows` bierze `line` z najnowszego Budżetu albo `null`. Test regresji „a category without a line in the newest covering Budget takes the archived mark of the list category” był czerwony przed zmianą i jest zielony po niej. Ślepy punkt sprawdzony: `line` czyta tylko `PeriodReport::categoryEntry`. ADR-0081 poprawiony w miejscu, bo powstał w tej zmianie i nie jest jeszcze w `development`.

### F2 — Test E2E zmiany roku z okna zależy od seedu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/reports.spec.ts:533
- **Detail**: Test zakłada, że baza E2E ma Budżet roku `year - 1`, i klika jego segment. Siostrzany test Trendu (`:422-470`) sam tworzy i usuwa Budżet, żeby nie zależeć od danych. Na bazie z jednym rokiem test padnie na timeout klikania. Dziś przechodzi, bo seed ma dwa lata.
- **Fix**: Utworzyć i usunąć drugi Budżet w teście jak w teście Trendu albo wyciągnąć wspólny helper z tego testu.
- **Decision**: FIXED — test tworzy następny Budżet przez „Utwórz budżet”, z okna przechodzi na rok wcześniejszy i w `finally` usuwa Budżet (wzór testu Trendu). Oba testy okna zielone w Playwright (`-g "12 months view"`: 3 passed z setupem).

### F3 — Formatowanie okna rozproszone i częściowo bez testów

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/ReportCoverageNote.vue:47-57
- **Detail**: Tekst notki pokrycia składa łańcuch `String.replace(':missing'…)`, który zastępuje tylko pierwsze wystąpienie i interpretuje `$&` w zamienniku. Sąsiednie `reportOperationsLinkLabel` i `reportMonthRangeLabel` używają jednoprzebiegowego replacera i mają testy. Tekstu notki nie sprawdza żaden test Vitest ani E2E. Tablica nazw miesięcy powstaje trzy razy (`Reports.vue:66`, `ReportCoverageNote.vue:20`, `ReportsKitchenSink.vue:31`). `windowLabel`/`windowRange` kitchen sinka (`:36-46`) powielają computed strony. Dziś to nie jest błąd, bo wejścia to przetłumaczone nazwy miesięcy.
- **Fix**: Przenieść `reportCoverageNote(...)` i `reportWindowRange(...)` do `@/lib/report-window.ts` (jednoprzebiegowy replacer, test luki na początku, na końcu i jednomiesięcznej) i użyć ich na stronie, w notce i w kitchen sinku.
- **Decision**: FIXED (tryb auto, wg rekomendacji) — `reportMonthNamesFrom`, `reportWindowRange` i `reportCoverageNote` (jednoprzebiegowy replacer) w `lib/report-window.ts`, 7 nowych przypadków Vitest: luka na początku, na końcu, jednomiesięczna, `null` przy pełnym i zerowym pokryciu, `$&` i powtórzony placeholder. `ReportCoverageNote`, `Reports.vue` i kitchen sink ich używają, a `.ai/rules/reports.md` je wymienia.

### F4 — Fixture bez pokrycia nie ma kształtu z serwera

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/reports-fixtures.ts:618-623
- **Detail**: `windowNoCoverage` rozwija `windowFull`, więc `missing_from`/`missing_through` są `null`. Serwer przy zerowym pokryciu zwraca całe okno jako brak (`ReportTwelveMonthWindow.php:52`), co sprawdzają oba testy PHP. Podpis `last-12-full` mówi „Żywność obecna w obu Budżetach”, ale scenariusz używa `yearReport`, więc tego nie pokazuje.
- **Fix**: Ustawić `missing_from: '2025-11-01'`, `missing_through: '2026-10-31'` i przeredagować podpis `last-12-full`.
- **Decision**: FIXED (tryb auto) — `windowNoCoverage` ma `missing_*` całego okna. Podpis scenariusza i wiersz macierzy w kitchen sinku nie obiecują już kategorii z dwóch lat.

### F5 — Drobna higiena: przesunięty docblock, nietypowany parametr, nagłówek klasy

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/ReportsPageTest.php:457-460
- **Detail**: Nowe testy wstawiono między docblock `bookExpense` („Books one expense through the factories…”) a metodę. Docblock opisuje teraz `test_it_renders_the_last_twelve_months_across_two_budgets`, a `bookExpense` (`:620`) go stracił. Do tego: `fn ($budget): int` bez typu parametru (`app/Support/ReportWindowRows.php:24`, reguła PHP projektu) oraz pierwsza linia docblocka `PeriodReport` (`:11`) „The Period Report of one Budget” przy trzecim okresie przez dwa Budżety.
- **Fix**: Przenieść docblock nad `bookExpense`, `fn (Budget $budget): int`, pierwsza linia docblocka bez „of one Budget”.
- **Decision**: FIXED (tryb auto) — wszystkie trzy, Pint passed.

### F6 — Ręczne kroki bez śladu, E2E na zabrudzonej bazie współdzielonej

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/rolling-twelve-month-report/plan.md:545-549
- **Detail**: Ręczne 1.7 i 2.7–2.11 są odhaczone z SHA commitu fazy, ale ani commit, ani plan nie zapisują, co zobaczono: kwoty z devu, chip zakresu w Operacjach, zrzut kitchen sinka. W przeglądzie `reports.spec.ts` dał 8 passed i 3 failed. Padają stare testy (`:83`, `:189`, `:353`), w krokach seedu i sprzątania na `/categories` i `/operations`. Zrzut pokazuje „Grupa o takiej nazwie już istnieje.”: w `household_treasurer_e2e` zostały dane po wcześniejszym przerwanym albo równoległym biegu. Zmiana nie dotyka tych testów ani stron, a oba nowe testy przechodzą.
- **Fix**: Potwierdzić ręczne kroki jednym zdaniem w planie (co i gdzie zobaczono). Bazę E2E wyczyścić (`migrate:fresh` z seedem E2E) i powtórzyć `reports.spec.ts` przed merge do `development`.
- **Decision**: DEFERRED — wymaga użytkownika. Opis tego, co zobaczono w krokach ręcznych, zna tylko on. Reset `household_treasurer_e2e` może przerwać równoległe sesje na tej bazie, więc nie wykonano go automatycznie.
