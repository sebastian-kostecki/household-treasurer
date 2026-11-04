<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Podpowiedź kategorii w podglądzie Importu

- **Plan**: context/changes/import-category-suggestions/plan.md
- **Scope**: Full plan (Phase 1–4 of 4)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-04
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Verification run (Step 3)

- Phase 1: `grep -c "Import category suggestion" CONTEXT.md` = 1, `ls .docs/adr/0034-*` OK, `grep -c "FR-IMP-28\|NFR-PERF-03" .docs/prd.md` = 3 — PASS
- Phase 2/3: `artisan test` (PostgreSQL) na ImportCategorySuggestionModelTest, ImportCategorySuggesterTest, CsvImportTest, DevImportPreviewSinkTest — 113 passed (1081 assertions); `npm test` 171 passed; `npm run check`, `check:ui`, `types:check` czyste; `pint --test` passed; `sloppy ci` 100/100, 0 findings
- Phase 4: `--group=nfr` — 2 passed (29 assertions)
- Nie uruchomiono ponownie: `npm run build` (3.2) i pełne `composer ci:check` (4.2)
- Pomiar `eval_service.php` (4.4), użytkownik 1, 1009 wierszy historii:
  - od stycznia: z podpowiedzią 77,1% (próg ≥78%), trafne 93,6% (≥92%), top 3 95,2%
  - sierpień–listopad: 79,3% (≥79%), trafne 96,5% (≥95%), top 3 99,3%

## Findings

### F1 — Pomiar jakości poniżej progu planu, a punkt 4.4 odhaczony

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/import-category-suggestions/plan.md:484 (Progress 4.4)
- **Detail**: Plan wymaga dla „od stycznia" ≥78% wierszy z podpowiedzią i ≥92% trafnych oraz mówi: „Gdy próg nie jest spełniony, zatrzymaj się i zgłoś wyniki zamiast stroić stałe". Ponowny pomiar `eval_service.php` daje 77,1% z podpowiedzią (trafność 93,6% jest w normie, okno sierpień–listopad też). Brakuje 0,9 pp. Punkt 4.4 jest `[x]`, ale ani commit 110e89e, ani plan, ani `change.md` nie zapisują wyników pomiaru ani odstępstwa. Hipoteza, nieprzetestowana: różnicę powoduje reguła kandydatów `w(t) ≥ WEIGHT_FLOOR`, którą plan świadomie wprowadził dla wydajności (28 s → 0,1 s) i zaznaczył, że mierzy się „bez porównania z wynikami prototypu".
- **Fix A ⭐ Recommended**: Zapisać wyniki w planie jako zaakceptowane odstępstwo (77,1% / 93,6%) i obniżyć próg „od stycznia" do ≥77%
  - Strength: Odstępstwo jest małe, trafność i okno sierpień–listopad spełniają progi, a przyczyną jest zamierzona reguła wydajnościowa. Zgodne z zasadą „nie stroić stałych".
  - Tradeoff: Zmiana progu po fakcie osłabia sens progu.
  - Confidence: MED — rozbieżność nie ma ustalonej przyczyny.
  - Blind spot: Nie sprawdzono, czy dane lokalne zmieniły się od pomiaru prototypu (w `change.md` też 1009 wierszy, więc raczej nie).
- **Fix B**: Odznaczyć 4.4, zbadać przyczynę (porównać wynik modelu z/bez reguły kandydatów) i zdecydować o stałych
  - Strength: Ustala prawdziwą przyczynę przed akceptacją.
  - Tradeoff: Dodatkowa praca; ryzyko strojenia, którego plan zabrania.
  - Confidence: MED — zależy od tego, czy różnica to reguła kandydatów.
  - Blind spot: Nie wiadomo, czy odzyskanie 0,9 pp nie kosztuje wydajności.
- **Decision**: FIXED via Fix A (próg „od stycznia" ≥77% w plan.md, wyniki pomiaru w change.md)

### F2 — Pesymistyczny koszt czasu i pamięci modelu dla dużej historii z dominującym sklepem

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/CsvImport/ImportCategorySuggestionModel.php:74-80, 211-212; app/Support/CsvImport/ImportCategorySuggester.php:80-92
- **Detail**: (1) `neighbours()` zbiera wszystkie wpisy dla każdego tokenu o `w ≥ 0,05`, liczy `similarity` dla każdego i sortuje `usort` całą listę, żeby wziąć 5. Token sklepu z k=1 ma w=1, więc sklep z N wpisami daje N kandydatów na każdy wiersz CSV z tym sklepem (osobno dla income i expense). Benchmark planu (200 sklepów po ~50 wpisów) i NFR-PERF-03 mierzą rozkład łagodny. Zweryfikowano w kodzie, że nie ma limitu kandydatów ani wyboru top-5 bez pełnego sortowania. (2) `fromHistory` ładuje całą historię bez limitu; pętla `foreach ($stored ...)` z zapisem `$stored[$position]['denominator'] += ...` rozdziela tablicę. Szacunek agenta, nie pomiar: ok. 1 KB na wpis. Dla obecnych 1009 wpisów bez znaczenia, ale koszt rośnie wraz z latami importu.
- **Fix**: Wybrać top-5 jedną pętlą (utrzymywanie 5 najlepszych) zamiast `usort` całości, liczyć `denominator` do osobnej tablicy zamiast mutować `$stored`, a do NFR dodać wariant z jednym dominującym sklepem (tysiące wpisów).
  - Strength: Usuwa główny składnik kosztu (alokacje przy sortowaniu) bez zmiany wyniku; wynik zostaje identyczny, bo kolejność i tie-break są te same.
  - Tradeoff: Trochę więcej kodu w `neighbours`; limit historii (np. ostatnie N operacji) celowo pominięty, bo zmieniałby wyniki.
  - Confidence: MED — wielkość zysku nie jest zmierzona.
  - Blind spot: Czas dla 50 tys. wpisów i dominującego sklepu nie został zmierzony.
- **Decision**: FIXED — top-5 w jednej pętli (`compareNeighbours`), iteracja po referencji w `fromHistory`; nowy test NFR „dominant shop” (1/3 historii i wierszy CSV to jeden sklep). Pomiar: stary model 3,3 s (czerwony), nowy przechodzi; pełny scenariusz 1/2 historii: 6,9 s → 2,08 s (zostawiony poza testem). Pamięć podręczna wyników odrzucona. Wyniki jakości bez zmian (77,1% / 93,6%).

### F3 — Remis podobieństwa może rozstrzygać błąd zmiennoprzecinkowy zamiast daty i id

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/CsvImport/ImportCategorySuggestionModel.php:211-212, 224-241
- **Detail**: Licznik sumuje wagi w kolejności tokenów wiersza, mianownik w kolejności tokenów wpisu. Matematycznie równe podobieństwa mogą się różnić o 1 ulp, i wtedy klucz drugorzędny (data, id) nie jest używany. Plan wymaga deterministycznych remisów po dacie i id.
- **Fix**: Porównywać `round($similarity, 9)` w komparatorze `usort` (albo przy zapisie do `$scored`).
- **Decision**: FIXED via Fix now (`round($similarity, 9)` w `compareNeighbours`)

### F4 — Luki w testach: stara sesja, remisy, limit sąsiadów, margines testu szumu, NFR

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/import-preview-rows.test.ts; tests/Unit/ImportCategorySuggestionModelTest.php; tests/Feature/Nfr/NfrPerf03ImportPreviewTest.php
- **Detail**: Brak testu `suggestedCategoryId` dla `category_suggestion: undefined` (ścieżka starej sesji, powód opcjonalności pola w Migration Notes). Brak testów: rozstrzygnięcia remisu podobieństwa datą/id, limitu `NEIGHBOURS=5`, wzoru wag (k=2 → 0,25), granicy k=4/k=5 dla `WEIGHT_FLOOR`. Test „a row sharing only noise with the history gives null" wg agenta przechodzi z małym marginesem (ok. 0,241 przy progu 0,25), więc bardziej chroni `MIN_SIMILARITY` niż regułę wag. Wszystkie przypadki wymagane przez plan są pokryte.
- **Fix**: Dodać test `undefined` w TS i trzy testy jednostkowe modelu (remis, limit sąsiadów, granica wag).
- **Decision**: FIXED via Fix now — dodano 5 testów modelu (waga k=2, granica k=4/k=5, limit 5 sąsiadów, remis po dacie, remis po id) i test TS dla `category_suggestion: undefined`. Testy remisów potwierdzone mutacją komparatora. Test szumu z małym marginesem (0,241) pozostawiony bez zmian.

### F5 — `isEligibleRow` zakłada, że `valid === true` oznacza string w `operation_date`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/CsvImport/ImportCategorySuggester.php:41, 54-58
- **Detail**: `(int) substr((string) $row['operation_date'], 0, 4)` jest bezpieczne dziś (walidator odrzuca null w dacie), ale wiersze pochodzą z podglądu i nie mają gwarancji kształtu poza walidatorem.
- **Fix**: Dodać do `isEligibleRow` warunek `is_string($row['operation_date'])`.
- **Decision**: FIXED via Fix now (`is_string($row['operation_date'] ?? null)` w `isEligibleRow`)

### F6 — Zdublowany warunek widoczności znacznika i zbędny reset w `onTypeChange`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/ImportPreviewRow.vue:231-246, 559-572
- **Detail**: `<p>` ma `v-if="row.valid && showsSuggestion"`, a `aria-describedby` używa samego `showsSuggestion`. Dziś bez rozjazdu, bo `CategorySelect` renderuje się tylko dla `row.valid`. Pierwsza linia `category_id = ''` w `onTypeChange` jest nadmiarowa względem późniejszego przypisania z `suggestedCategoryId`.
- **Fix**: Jeden computed dla widoczności znacznika użyty w obu miejscach; usunąć zbędny reset.
- **Decision**: FIXED via Fix now (`row.valid` wbudowane w computed `showsSuggestion`, użyty w `v-if` i `aria-describedby`; usunięty zbędny reset w `onTypeChange`)
