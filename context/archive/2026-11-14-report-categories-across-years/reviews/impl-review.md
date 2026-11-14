<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Raporty „Lata”: kategorie przez lata — plan implementacji

- **Plan**: context/changes/report-categories-across-years/plan.md
- **Scope**: Full plan (fazy w pełni odhaczone)
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-14
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings (2 fixed), 5 observations (5 fixed)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Zakres: commity 7585580 (p1), 09d652a (p2), 39 plików w `1ce160d..HEAD`. Faza 3 (8ae3b99) nie jest w zakresie, bo ręczny wiersz 3.2 (przegląd na produkcji) czeka na wdrożenie; jej pliki służyły tylko jako kontekst. Każdy plik z planu jest w diffie. Poza planem jest tylko `app/Support/PeriodReport.php`: istniejąca reguła znaku archiwum wydzielona do publicznego `isArchivedRow`, tak jak plan kazał („użyj jej, nie pisz trzeciej kopii”), bez zmiany zachowania raportu roku i miesiąca.

Kryteria automatyczne uruchomione w przeglądzie (host PHP 8.4.1 i Node 24 z nvm, PostgreSQL przez obraz `sail-8.5/app`):

- Testy serwera fazy 1 na SQLite: 127 passed (1557 assertions). Na PostgreSQL (`testing_report_categories_across_years`): 127 passed.
- NFR (`--group=nfr --filter=NfrPerfReportTest`, PostgreSQL): 3 passed, w tym „Lata” przy 7 Budżetach × 10 000 operacji.
- Vitest `report-years.test.ts` i `ui-contract.test.ts`: 124 passed.
- `npm run build` i `npm run types:check`: ok. `npm run check:ui`: czysto.
- `ReportsHardcodedUiTest`, `UiTranslationKeysTest`, `ReportsKitchenSinkTest`: 7 passed.
- `php artisan boost:index-rules`: bez różnic.
- `composer ci:check` na PostgreSQL: 1732 passed, 2 skipped.
- `vendor/bin/sloppy ci`: przechodzi, 1 znalezisko medium (SL113 w `app/Support/SheetLoad/MonthSheetReader.php:245`, spoza tej zmiany). `pint --test` na zmienionych plikach PHP: ok.
- Ręczne 1.7 i 2.7–2.10 potwierdził użytkownik na preview (e6cd5ed).

Bezpieczeństwo i jakość bez uwag: każde zapytanie jest zawężone do Budżetów użytkownika, `CASE WHEN` używa tylko związanych parametrów, nie ma `v-html`, nie ma zapytań w pętli (test liczby zapytań 2×2 kontra 7×24), `float` jest tylko w stosunku wzrostu, zaokrąglenia idą połową od zera, nie ma migracji.

## Findings

### F1 — „Zmiana” liczy −100% przy zerze w roku końcowym, ADR-0097 każe „—”

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Support/ReportYears.php:245
- **Detail**: ADR-0097:15 mówi, że komórka zmiany to „—”, gdy wiersz „has no amount in the final year”, i definiuje „with an amount” jako „above 0; a cell 0 and a cell „—” do not count”. Kod daje „—” tylko przy `$final === null`. Przy 0 albo kwocie ujemnej w roku końcowym zwraca −100,0% lub mniej. `fastestGrowing` (:303) stosuje już `end > 0`, więc dwie reguły z tego samego zdania ADR rozjeżdżają się. Realny przypadek: `ArchiveCategory` archiwizuje linię bieżącego Budżetu, ale jej nie usuwa, więc kategoria zarchiwizowana w 2026 bez wydatków w 2026 ma komórkę 0 i pokaże „−100,0%” (ton `success`) zamiast „—”, jak ADR opisuje dla Kina. Żaden test tego nie sprawdza (Kino w teście nie ma linii w roku końcowym).
- **Fix A ⭐ Recommended**: warunek `$final !== null && $final > 0` w `cellsShape` plus przypadek w `ReportYearsTest`: zero i kwota ujemna w roku końcowym dają `change_tenths` `null`.
  - Strength: zgodne z dosłowną definicją ADR-0097:15 i z regułą, którą `fastestGrowing` już stosuje. Zmiana w jednym warunku.
  - Tradeoff: kategoria, która naprawdę przestała mieć wydatki, ale nie jest zarchiwizowana, pokaże „—” zamiast „−100%”, więc spadek do zera nie jest widoczny w kolumnie zmiany (widać go w komórkach i sparkline).
  - Confidence: HIGH — ADR definiuje frazę wprost.
  - Blind spot: nie sprawdzono, ile takich wierszy jest na produkcji.
- **Fix B**: zostawić −100% dla 0 i zmienić regułę przez nowy ADR i wpis słownika (hard rule AGENTS.md).
  - Strength: spadek do zera jest widoczny w kolumnie zmiany.
  - Tradeoff: nowy ADR, zmiana słownika i rozjazd z „Najszybciej rośnie”. Zarchiwizowana kategoria z linią 0 dalej pokazuje kolorowy spadek.
  - Confidence: MED — zależy od tego, co użytkownik chce widzieć.
  - Blind spot: karta `PageReportsYears` nie pokazuje przypadku 0 w roku końcowym.
- **Decision**: FIXED (Fix A) — `cellsShape` wymaga `$final > 0`; test „the change is null when the final year has no amount above zero” był czerwony (-1000), jest zielony. Fiksura kitchen sinka stosuje ten sam warunek.

### F2 — `.ai/rules/js.md` dalej mówi, że pełne złote ma tylko Trend

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: .ai/rules/js.md:17
- **Detail**: Reguła dla `resources/js/**/*.vue` wymienia „two exceptions”, w tym „the Trend in Reports, whose tables show whole zloty with `formatWholeMoneyAmount`” (ADR-0077). `reports.md` przepisano na „Trend i Lata”, ale `js.md` nie. Agent, który edytuje `ReportYearsRow.vue`, dostaje sprzeczne reguły. ADR-0097:19 rozszerza wyjątek na „Lata”.
- **Fix**: dopisać do wyjątku w `js.md:17` „i „Lata” (ADR-0097)”, a potem uruchomić `php artisan boost:index-rules`.
- **Decision**: FIXED — `.ai/rules/js.md:17` wymienia Trend i „Lata” (ADR-0077, ADR-0097); `boost:index-rules` bez różnic.

### F3 — Gałąź „linia ostatniej kolumny” w `is_archived` nie ma testu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/ReportYearsTest.php:244-246
- **Detail**: Kino jest zarchiwizowane przez `$cinema->update(['archived_at' => …])` i nie ma linii w ostatniej kolumnie, więc test pokrywa tylko gałąź zastępczą (kategoria listy). Gałąź główna, czyli linia ostatniego Budżetu z lekcji S-32 F1, nie ma testu. Przez to błędny wybór `line` w `rows()` (:137) nie zostałby wykryty.
- **Fix**: dodać przypadek, w którym linia ostatniej kolumny jest zarchiwizowana, a kategoria nie (i odwrotnie, gdy linia z wcześniejszego roku jest zarchiwizowana), i sprawdzić `is_archived`.
- **Decision**: FIXED — test „the archived mark follows the line of the last column and the list Category only without it” pokrywa obie gałęzie (przeszedł od razu: kod był poprawny, brakowało pokrycia).

### F4 — Fiksura `full` nie ma przykładu ADR i dubluje obliczenia serwera

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/lib/reports-fixtures.ts:683
- **Detail**: Plan chciał w `years-same-day` w trybie `full` przykładu ADR: 42 000 → 74 000, +76,2% / +12,0%. Fiksura mnoży kwoty przez `YEARS_FULL_FACTOR = 1.12` i daje około 40 297 → 70 202 (+74,2% / +11,7%). Do tego liczy zmianę, wzrost, maksima i „Najszybciej rośnie” we własnym TS (:765-940), a `Math.round` zaokrągla ujemne połówki inaczej niż serwer. Dotyczy tylko kitchen sinka.
- **Fix**: ustawić „Razem” trybu `full` na liczby z ADR (42 000 / 74 000), a wartości liczone podać jako stałe zamiast przeliczać je w fiksurze.
- **Decision**: FIXED — wiersze wydatków mają jawne `fullZloty` 2020–2025, „Razem” w „Pełnych latach” to 42 000 → 74 000 (+76,2%, +12,0%, sprawdzone tymczasowym testem Vitest); `growthTenths` fiksury zaokrągla połową od zera.

### F5 — Testy tworzą operacje fabryką, nie przez `ManageFinancialOperation`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/ReportYearsTest.php:80-90
- **Detail**: Kontrakt Fazy 1 (pkt 6) mówi „Operacje przez `ManageFinancialOperation` … żeby wykonanie linii było prawdziwe”. Tylko pierwszy test używa `yearsBook` (akcja), reszta `yearsOperation` (fabryka). `ReportYears` czyta `financial_operations` bezpośrednio, więc ryzyko jest małe. Przypadek „operacja Budżetu 2025 z datą w 2024” też idzie przez fabrykę zamiast `DB::table`, z tym samym skutkiem.
- **Fix**: zostawić i dopisać odstępstwo do planu (odczyt nie zależy od kolumn liczonych przez akcję).
- **Decision**: FIXED — odstępstwo dopisane do planu (Faza 1, pkt 6).

### F6 — `$lastLine` w `rows()` nie jest linią ostatniego roku

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/ReportYears.php:120
- **Detail**: `$byYear[array_key_last($byYear)]` bierze ostatnią linię w kolejności wyniku `BudgetLine::whereIn(...)->get()` bez `orderBy`, a nie linię ostatniego roku. Skutek jest nieszkodliwy, bo czytana jest tylko `->category`, a wszystkie linie pod kluczem mają ten sam `category_id`. Mimo to nazwa wprowadza w błąd, a `?? null` z `continue` to martwy kod (`$byYear` nigdy nie jest pusty).
- **Fix**: `$category = reset($byYear)->category;` (albo nazwa `$anyLine`) i usunąć martwy warunek.
- **Decision**: FIXED — `reset($byYear)->category`, martwy warunek usunięty, typ `non-empty-array` (PHPStan poziom 7 czysty).

### F7 — Krótka data cięcia bez zera: „do 29.2”

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/report-years.ts:18
- **Detail**: `reportYearsCutShort` daje `${day}.${month}`, więc kafelki `years-leap` mówią „Wydatki 2028 do 29.2”, a test (`report-years.test.ts:30`) utrwala „9.2”. ADR-0097:23 używa formatu `<D.MM>`, a w polskim zapisie dat miesiąc ma dwie cyfry („29.02”).
- **Fix**: dopełniać miesiąc do dwóch cyfr (`String(cut.month).padStart(2, '0')`) i poprawić oczekiwanie testu na „9.02”.
- **Decision**: FIXED — miesiąc dopełniany do dwóch cyfr; test oczekuje „9.02” (był czerwony, jest zielony).
