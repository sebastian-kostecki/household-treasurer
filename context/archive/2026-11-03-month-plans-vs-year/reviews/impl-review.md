<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: S-10 Plany miesięcy a budżet roczny

- **Plan**: context/changes/month-plans-vs-year/plan.md
- **Scope**: Full plan (Phase 1–2 of 2)
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-03
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Evidence

Review ran on commits 27c140f..3e74832 read from git (`git show 3e74832:<path>`), because the working tree is in the middle of a merge (UU in `MonthPlanController.php`, `MonthPlan.vue`, `roadmap.md`). Automated checks ran on a clean `git archive 3e74832` copy, with Sail image and a scratch PostgreSQL database (dropped afterwards).

- `ToSpendCalculatorTest`, `MonthYearComparisonTest`, `MonthPlanPageTest`, `MonthToSpendTest` on PostgreSQL: 68 passed (706 assertions)
- `vendor/bin/pint --test app tests`: passed
- `npm run check`: passed (207 files formatted, no lint warnings)
- `npm run types:check`: passed (after copying generated Wayfinder files, which are gitignored)
- `npm test`: 70 passed
- `vendor/bin/sloppy scan --path=app/Support` on host: no high findings; the only findings (SL203 in `BudgetMonthExecutionIndex.php`, SL303 in `PaymentInstrumentNumberOmitter.php`) are in files this change did not touch. A full `sloppy ci` was not run because the working tree is mid-merge.
- Not re-run: `npm run build`, full `composer ci:check`.
- Manual items 2.5–2.9 are ticked in Progress; the diff holds no screenshot evidence for them, so they are taken as done (a `preview-month-plans-vs-year` container was running).
- `context/foundation/lessons.md` does not exist.

## Findings

### F1 — „Razem w roku" nie jest przyciemnione, gdy wiersz jest nieaktualny

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/MonthPlan.vue:709-721 (wersja 3e74832)
- **Detail**: W trybie edycji linia roczna wiersza z zmienioną kwotą jest przyciemniona, ale pasek sum sekcji dalej wygląda na aktualny, choć opiera się na starym planie. Plan mówił tylko o wierszach, więc to luka planu, nie odchylenie.
- **Fix**: Przyciemnić pasek sum (i dodać `sr-only` z `year_stale_hint`), gdy którykolwiek wiersz sekcji ma `yearStale`.
- **Decision**: FIXED — `sectionYearStale()` w `MonthPlan.vue`; przyciemnia kwoty w pasku sum, gdy którykolwiek wiersz sekcji jest nieaktualny (tekst podpowiedzi dodany w F2). `npm run check`, `types:check`, `npm test` przechodzą. Poprawka niezacommitowana.

### F2 — Stan „nieaktualne" opiera się na samym `opacity-60` i `title`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/MonthPlanItemRow.vue:105-118 (wersja 3e74832)
- **Detail**: `opacity-60` na `text-muted-foreground` najpewniej spada poniżej 4,5:1 (wymóg `.ai/rules/css.md`; kontrastu nie zmierzyłem). `title` na niefokusowalnym `<p>` nie działa z klawiatury ani dotyku, więc widzący użytkownik na telefonie nie dostaje wyjaśnienia „przeliczy się po zapisaniu planu"; tekst `sr-only` trafia tylko do czytników. To wzorzec zapisany w planie (Phase 2, pkt 1), więc wada leży w planie.
- **Fix A ⭐ Recommended**: Zamiast samego przyciemnienia pokazać widoczny krótki znacznik w linii (np. „· nieaktualne"), a `opacity` zostawić jako dodatek.
  - Strength: stan czytelny na dotyku i dla osób słabowidzących, bez zależności od `title`.
  - Tradeoff: dłuższa linia w wąskim wierszu, tekst się zawinie przy 360 px.
  - Confidence: MED — wzorzec widocznego tekstu jest już użyty przy `settled_income` (Badge).
  - Blind spot: kontrastu `opacity-60` nie zmierzono, a wygląd przy 360 px nie był sprawdzany.
- **Fix B**: Pokazać `year_stale_hint` raz przy sekcji (nad paskiem sum), gdy jakikolwiek wiersz jest nieaktualny, i zostawić samo przyciemnienie wierszy.
  - Strength: jedna informacja zamiast powtarzania w każdym wierszu.
  - Tradeoff: komunikat oddalony od wiersza, którego dotyczy.
  - Confidence: MED — łączy się naturalnie z poprawką F1.
  - Blind spot: nie sprawdzono, jak to wygląda przy kilku sekcjach w trybie edycji.
- **Decision**: FIXED via Fix A — wiersz i pasek sum pokazują widoczny tekst `year_stale_hint` (bez `title` i `sr-only`); `opacity-60` obejmuje tylko kwoty, nie ten tekst. Wygląd przy 360 px niesprawdzony. Poprawka niezacommitowana.

### F3 — Pasek „Razem w roku" w pustej sekcji i niejasny zakres sumy

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/MonthPlan.vue:709-721; lang/pl/ui.php:324 (`year_total_hint`)
- **Detail**: Pusta sekcja pokazuje „0 zł z 0 zł · zostaje 0 zł" pod „Brak pozycji". Suma obejmuje też wiersze, których nie widać (bezczynne jednorazowe w zwiniętej sekcji, kandydaci do dołączenia), a podpowiedź tego nie mówi. Zakres jest zgodny z planem i `CONTEXT.md`, ale użytkownik nie odtworzy sumy z widocznych linii.
- **Fix**: Ukryć pasek, gdy sekcja nie ma wierszy, i dopisać do `year_total_hint`, że suma obejmuje także pozycje ukryte.
- **Decision**: FIXED — pasek „Razem w roku” ukryty w sekcji bez wierszy; `year_total_hint` dopisuje, że suma obejmuje pozycje niewidoczne na liście. Poprawka niezacommitowana.

### F4 — `is_over_year` jest liczone i testowane, ale UI go nie używa

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Support/MonthYearComparison.php:68; resources/js/components/MonthYearSummary.vue:14-20
- **Detail**: `MonthYearSummary` sam wylicza kolor i etykietę z `remaining_minor_units < 0` i `type`, więc reguła „wydatek ponad rok" istnieje w dwóch miejscach. Dziś wynik jest równoważny; przy zmianie definicji po stronie serwera UI by się rozjechało.
- **Fix**: Dla wydatku użyć `comparison.is_over_year` (kolor `destructive` i etykieta „ponad rok o"); dla przychodu zostawić sprawdzenie znaku, bo flaga jest tam zawsze `false`.
- **Decision**: FIXED — `MonthYearSummary` czyta `is_over_year` dla wydatku; przychód nadal używa znaku `remaining_minor_units`. Poprawka niezacommitowana.

### F5 — Nowy komponent `MonthYearSummary.vue` nie jest w planie

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/components/MonthYearSummary.vue
- **Detail**: Plan opisywał linię w `MonthPlanItemRow.vue` i wiersz sumy w `MonthPlan.vue`. Implementacja wydzieliła wspólny komponent formatu „X z Y · zostaje Z". Odstępstwo jest rozsądne (jedno miejsce na format i kolory), ale opisuje je tylko komunikat commita 0ff3548.
- **Fix**: Dopisać jedno zdanie do `change.md` (Notes) albo addendum do planu.
- **Decision**: FIXED — notatka o `MonthYearSummary.vue` dopisana do `change.md` (Notes).

### F6 — Luki w testach niezmiennika `execution − actual`

- **Severity**: 👁 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/MonthPlanPageTest.php (`novemberBudget`, ok. 996-1012); tests/Unit/MonthYearComparisonTest.php
- **Detail**: Liczniki `execution_minor_units` ustawia test ręcznie obok fabryki operacji (tak zalecał plan), więc test sprawdza arytmetykę, ale nie samą tożsamość, na której stoi funkcja. W teście jednostkowym brak przypadku ujemnej kwoty faktycznej (zwrot netto) w `forCategory` i osobnej asercji sumy przychodów w `totals`. Kod czytany przez recenzenta jest poprawny (zwroty wchodzą netto po obu stronach), więc to ryzyko regresji, nie błąd.
- **Fix A ⭐ Recommended**: Dodać jeden test Feature, który tworzy operację (także zwrot) przez `ManageFinancialOperation` i sprawdza `year_comparison`, oraz dwa przypadki w teście jednostkowym (zwrot netto, suma przychodów).
  - Strength: chroni niezmiennik, który inaczej mógłby zepsuć przyszły refaktor akcji operacji.
  - Tradeoff: test integracyjny wymaga więcej przygotowania niż helper z licznikami.
  - Confidence: HIGH — `ManageFinancialOperation` jest jedynym miejscem zmieniającym licznik (poza tworzeniem budżetu i odtwarzaniem danych).
  - Blind spot: nie sprawdzono, czy istniejące testy akcji już tego nie pokrywają pośrednio.
- **Fix B**: Zostawić testy i dopisać niezmiennik do docblocka `MonthYearComparison` (już tam jest).
  - Strength: zero kosztu.
  - Tradeoff: niezmiennik chroniony tylko komentarzem.
  - Confidence: MED — zależy od tego, jak często zmienia się `ManageFinancialOperation`.
  - Blind spot: brak.
- **Decision**: FIXED via Fix A — test Feature przez `ManageFinancialOperation` (w tym zwrot) w `MonthPlanPageTest`, oraz w `MonthYearComparisonTest` przypadki: zwrot netto i suma przychodów. Testy na PostgreSQL: 72 przeszły, pint przechodzi. Zmiany niezacommitowane.

### F7 — Ten sam kształt tablicy wpisany pięć razy

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/MonthYearComparison.php; app/Http/Controllers/MonthPlanController.php (`yearTotals`)
- **Detail**: Kształt `{year_minor_units, annual_plan_minor_units, remaining_minor_units, is_over_year}` jest w docblockach czterokrotnie w klasie i raz w kontrolerze, a `yearTotals` ma luźniejszy typ `array<string, int|bool>`. To daje dryf typów dla analizy statycznej.
- **Fix**: Zdefiniować `@phpstan-type YearComparison` w `MonthYearComparison` i użyć go w kontrolerze.
- **Decision**: FIXED — `@phpstan-type YearComparison` w `MonthYearComparison`, `@phpstan-import-type` w kontrolerze (wzorzec jak `CsvImportMapping`); kontroler zyskał też dokładniejszy typ `year_totals`. pint i phpstan na zmienionych plikach przechodzą. Zmiany niezacommitowane.

### F8 — Reguła `itemMinorUnits` bez wzmianki w dokumentacji o parze PHP/TS

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: AGENTS.md („Month logic lives twice"); app/Support/ToSpendCalculator.php (docblock klasy)
- **Detail**: `itemMinorUnits` jest teraz współdzielone z `MonthYearComparison` i celowo nie ma kopii TS (docblock metody to mówi). Docblock klasy i AGENTS.md o tym milczą, więc kolejny agent może uznać brak kopii za lukę.
- **Fix**: Dopisać jedno zdanie do docblocka klasy `ToSpendCalculator`.
- **Decision**: FIXED — docblock klasy `ToSpendCalculator` opisuje parę PHP/TS i brak kopii TS dla `itemMinorUnits`. AGENTS.md bez zmian. Zmiana niezacommitowana.
